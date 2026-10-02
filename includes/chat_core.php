<?php
/**
 * Cripsum™ — nucleo delle chat private.
 *
 * Tutto quello che decide «si può?» e «cosa si vede?» nelle conversazioni a
 * due sta qui, e gli endpoint in api/chat/ sono sottili. Prima ogni file
 * rifaceva i controlli a modo suo e alcuni li saltavano: una risposta poteva
 * citare un messaggio di una conversazione altrui (e riceverne il testo), un
 * utente bloccato poteva continuare a mandare allegati, i messaggi privati
 * non avevano limite di lunghezza.
 *
 * Regole che valgono ovunque in questo file:
 *   - un messaggio si legge solo passando dalla conversazione di chi chiede;
 *   - una risposta o un inoltro citano solo messaggi che chi scrive può
 *     leggere;
 *   - chi può scrivere a chi lo decide sc_can_message() (blocchi e privacy);
 *   - le colonne facoltative si usano solo se esistono (rt_has_col).
 *
 * I gruppi stanno in includes/chat_groups.php e condividono formato dei
 * messaggi, allegati e reazioni.
 */

require_once __DIR__ . '/realtime.php';
require_once __DIR__ . '/social_core.php';

if (!defined('CC_MAX_LEN')) {
    define('CC_MAX_LEN', 2000);            // caratteri per messaggio
    define('CC_PAGE', 40);                 // messaggi per pagina
    define('CC_EDIT_WINDOW', 3600);        // secondi entro cui si può modificare
    define('CC_MAX_FILES', 6);             // allegati per messaggio
    define('CC_NICK_MAX', 40);
    define('CC_REQUEST_MAX_MESSAGES', 5);  // messaggi che si possono mandare prima che una richiesta venga accettata
    define('CC_BURST', 8);                 // messaggi ogni 10 secondi
}

if (!function_exists('cc_clean_text')) {

    class ChatError extends RuntimeException
    {
        public int $status;

        public function __construct(string $message, int $status = 400)
        {
            parent::__construct($message);
            $this->status = $status;
        }
    }

    // ── Testo ──────────────────────────────────────────────────────────────

    function cc_clean_text(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        // Caratteri invisibili che servono solo a nascondere testo o a
        // ribaltare la direzione di scrittura.
        $text = preg_replace('/[\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $text) ?? $text;
        $text = preg_replace("/\n{4,}/", "\n\n\n", $text) ?? $text;
        return trim($text);
    }

    function cc_validate_text(string $text, bool $allowEmpty = false, int $max = CC_MAX_LEN): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new ChatError(rt_t('Testo non valido.', 'Invalid text.'), 422);
        }
        $text = cc_clean_text($text);
        if ($text === '' && !$allowEmpty) {
            throw new ChatError(rt_t('Scrivi qualcosa.', 'Write something.'), 422);
        }
        if (mb_strlen($text, 'UTF-8') > $max) {
            throw new ChatError(rt_t("Messaggio troppo lungo (massimo $max caratteri).", "Message too long (max $max characters)."), 422);
        }
        return $text;
    }

    function cc_preview(?string $text, int $limit = 120): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text) ?? '');
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit - 1, 'UTF-8') . '…';
    }

    // ── Emoji e reazioni ───────────────────────────────────────────────────

    /** Emoji personalizzate del sito, lette dal database una volta ogni dieci minuti. */
    function cc_custom_emojis(mysqli $mysqli): array
    {
        static $list = null;
        if ($list !== null) {
            return $list;
        }

        $cached = rt_read('emoji');
        if (!empty($cached['at']) && (time() - (int)$cached['at']) < 600 && isset($cached['list'])) {
            return $list = $cached['list'];
        }

        $list = [];
        if (rt_has_table($mysqli, 'game_emojis')) {
            try {
                $result = $mysqli->query('SELECT code, url, is_animated FROM game_emojis ORDER BY id ASC');
                while ($result && ($row = $result->fetch_assoc())) {
                    $code = (string)$row['code'];
                    // Il codice finisce dentro i messaggi come :codice: — se
                    // contiene altro che lettere, cifre e trattini non è utilizzabile.
                    if (!preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $code)) {
                        continue;
                    }
                    $list[] = ['code' => $code, 'url' => (string)$row['url'], 'is_animated' => (int)$row['is_animated']];
                }
            } catch (Throwable $e) {
                $list = [];
            }
        }

        rt_update('emoji', static fn() => ['at' => time(), 'list' => $list]);
        return $list;
    }

    /** Vero se la stringa è una sola emoji Unicode (anche composta). */
    function cc_is_emoji(string $value): bool
    {
        if ($value === '' || strlen($value) > 64) {
            return false;
        }
        return (bool)preg_match(
            '/^(?:\p{Regional_Indicator}{2}|[#*0-9]\x{FE0F}?\x{20E3}|\p{Extended_Pictographic}(?:\x{FE0F}|[\x{1F3FB}-\x{1F3FF}])?(?:\x{200D}\p{Extended_Pictographic}(?:\x{FE0F}|[\x{1F3FB}-\x{1F3FF}])?)*)$/u',
            $value
        );
    }

    /**
     * Una reazione è valida se è un'emoji vera oppure il codice di un'emoji
     * del sito, e se entra nella colonna che la deve contenere.
     */
    function cc_valid_reaction(mysqli $mysqli, string $reaction, int $columnLength): bool
    {
        if ($reaction === '') {
            return false;
        }
        if ($columnLength > 0 && mb_strlen($reaction, 'UTF-8') > $columnLength) {
            return false;
        }
        if (cc_is_emoji($reaction)) {
            return true;
        }
        foreach (cc_custom_emojis($mysqli) as $emoji) {
            if ($emoji['code'] === $reaction) {
                return true;
            }
        }
        return false;
    }

    /**
     * Le tabelle delle reazioni nascevano alla prima reazione, con un ALTER
     * TABLE ripetuto a ogni chiamata successiva. Qui si creano una volta sola
     * se mancano, e poi non si tocca più nulla.
     */
    function cc_ensure_reaction_table(mysqli $mysqli, string $table, string $parent): bool
    {
        if (rt_has_table($mysqli, $table)) {
            return true;
        }
        if (!in_array($table, ['private_message_reactions', 'group_chat_reactions'], true)) {
            return false;
        }
        try {
            $mysqli->query("
                CREATE TABLE IF NOT EXISTS `$table` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `message_id` INT NOT NULL,
                    `user_id` INT NOT NULL,
                    `reaction` VARCHAR(50) NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `user_msg_reaction` (`message_id`, `user_id`, `reaction`),
                    FOREIGN KEY (`message_id`) REFERENCES `$parent`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`user_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            rt_schema_forget();
            return true;
        } catch (Throwable $e) {
            error_log('[chat] tabella reazioni: ' . $e->getMessage());
            return false;
        }
    }

    /** Reazioni di più messaggi in una query, già raggruppate. */
    function cc_reactions(mysqli $mysqli, string $table, int $viewerId, array $messageIds): array
    {
        $messageIds = array_values(array_unique(array_filter(array_map('intval', $messageIds))));
        if (!$messageIds || !rt_has_table($mysqli, $table)) {
            return [];
        }

        $marks = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $mysqli->prepare("
            SELECT r.message_id, r.reaction, COUNT(*) AS total,
                   MAX(r.user_id = ?) AS mine,
                   GROUP_CONCAT(u.username ORDER BY r.id SEPARATOR ', ') AS names
            FROM `$table` r
            INNER JOIN utenti u ON u.id = r.user_id
            WHERE r.message_id IN ($marks)
            GROUP BY r.message_id, r.reaction
            ORDER BY MIN(r.id) ASC
        ");
        $params = array_merge([$viewerId], $messageIds);
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $map = [];
        while ($row = $result->fetch_assoc()) {
            $map[(int)$row['message_id']][] = [
                'reaction' => (string)$row['reaction'],
                'count' => (int)$row['total'],
                'user_reacted' => (int)$row['mine'] === 1,
                'usernames' => (string)$row['names'],
            ];
        }
        $stmt->close();
        return $map;
    }

    /** Aggiunge o toglie una reazione. Restituisce vero se adesso c'è. */
    function cc_toggle_reaction(mysqli $mysqli, string $table, int $messageId, int $userId, string $reaction): bool
    {
        $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE message_id = ? AND user_id = ? AND reaction = ?");
        $stmt->bind_param('iis', $messageId, $userId, $reaction);
        $stmt->execute();
        $removed = $stmt->affected_rows > 0;
        $stmt->close();
        if ($removed) {
            return false;
        }

        // Al massimo otto reazioni diverse a testa per messaggio.
        $stmt = $mysqli->prepare("SELECT COUNT(*) FROM `$table` WHERE message_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $messageId, $userId);
        $stmt->execute();
        $mine = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
        if ($mine >= 8) {
            throw new ChatError(rt_t('Hai già messo troppe reazioni a questo messaggio.', 'You already added too many reactions to this message.'), 422);
        }

        $stmt = $mysqli->prepare("INSERT IGNORE INTO `$table` (message_id, user_id, reaction) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $messageId, $userId, $reaction);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    // ── Allegati ───────────────────────────────────────────────────────────

    function cc_upload_types(): array
    {
        return [
            'image/jpeg' => ['ext' => 'jpg', 'type' => 'image'],
            'image/png' => ['ext' => 'png', 'type' => 'image'],
            'image/gif' => ['ext' => 'gif', 'type' => 'image'],
            'image/webp' => ['ext' => 'webp', 'type' => 'image'],
            'video/mp4' => ['ext' => 'mp4', 'type' => 'video'],
            'video/webm' => ['ext' => 'webm', 'type' => 'video'],
            'audio/mpeg' => ['ext' => 'mp3', 'type' => 'audio'],
            'audio/ogg' => ['ext' => 'ogg', 'type' => 'audio'],
            'audio/wav' => ['ext' => 'wav', 'type' => 'audio'],
            'audio/x-wav' => ['ext' => 'wav', 'type' => 'audio'],
            'application/pdf' => ['ext' => 'pdf', 'type' => 'file'],
            'application/zip' => ['ext' => 'zip', 'type' => 'file'],
            'text/plain' => ['ext' => 'txt', 'type' => 'file'],
        ];
    }

    /** Riordina $_FILES['x'] (uno o più file) in una lista uniforme. */
    function cc_uploaded_files(array $files): array
    {
        $list = [];
        foreach (['files', 'file'] as $field) {
            if (!isset($files[$field])) {
                continue;
            }
            $entry = $files[$field];
            if (is_array($entry['name'])) {
                foreach ($entry['name'] as $i => $name) {
                    $list[] = [
                        'name' => $name,
                        'tmp_name' => $entry['tmp_name'][$i] ?? '',
                        'error' => $entry['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $entry['size'][$i] ?? 0,
                    ];
                }
            } else {
                $list[] = $entry;
            }
        }
        return array_values(array_filter($list, static fn($f) => ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
    }

    /**
     * Riscrive una foto senza i dati EXIF.
     *
     * Le foto del telefono portano con sé posizione GPS, modello e ora dello
     * scatto: chi le manda in chat di solito non lo sa. JPEG e WebP fermi
     * vengono ricodificati (tenendo conto dell'orientamento, che sta proprio
     * nell'EXIF); GIF, PNG e WebP animati restano come sono.
     */
    function cc_strip_image_metadata(string $path, string $mime): void
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return;
        }

        try {
            if ($mime === 'image/jpeg') {
                $orientation = 1;
                if (function_exists('exif_read_data')) {
                    $exif = @exif_read_data($path);
                    $orientation = (int)($exif['Orientation'] ?? 1);
                }
                $image = @imagecreatefromjpeg($path);
                if (!$image) {
                    return;
                }
                $rotated = match ($orientation) {
                    3 => imagerotate($image, 180, 0),
                    6 => imagerotate($image, -90, 0),
                    8 => imagerotate($image, 90, 0),
                    default => $image,
                };
                if ($rotated && $rotated !== $image) {
                    imagedestroy($image);
                    $image = $rotated;
                }
                imageinterlace($image, true);
                imagejpeg($image, $path, 88);
                imagedestroy($image);
                return;
            }

            if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
                $head = (string)@file_get_contents($path, false, null, 0, 64);
                if (strpos($head, 'ANIM') !== false || strpos($head, 'VP8X') === false) {
                    // Animato, oppure senza il blocco che contiene i metadati.
                    return;
                }
                $image = @imagecreatefromwebp($path);
                if (!$image) {
                    return;
                }
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                imagewebp($image, $path, 88);
                imagedestroy($image);
            }
        } catch (Throwable $e) {
            error_log('[chat] pulizia metadati: ' . $e->getMessage());
        }
    }

    /**
     * Valida e salva un file caricato. Il nome su disco è casuale e
     * l'estensione viene dal tipo rilevato sul contenuto, mai dal nome
     * scelto da chi carica.
     */
    function cc_store_upload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $tooBig = in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            throw new ChatError($tooBig
                ? rt_t('Il file è troppo grande.', 'The file is too large.')
                : rt_t('Caricamento non riuscito.', 'Upload failed.'), 422);
        }

        $tmp = (string)$file['tmp_name'];
        $size = (int)$file['size'];
        if ($size <= 0 || !is_uploaded_file($tmp)) {
            throw new ChatError(rt_t('File non valido.', 'Invalid file.'), 422);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string)finfo_file($finfo, $tmp);
        finfo_close($finfo);

        $types = cc_upload_types();
        if (!isset($types[$mime])) {
            throw new ChatError(rt_t('Tipo di file non consentito.', 'File type not allowed.'), 422);
        }

        $kind = $types[$mime]['type'];
        if ($kind === 'image' && @getimagesize($tmp) === false) {
            throw new ChatError(rt_t('Il file non è un\'immagine valida.', 'The file is not a valid image.'), 422);
        }

        $limit = in_array($kind, ['image', 'audio'], true) ? 20 * 1024 * 1024 : 50 * 1024 * 1024;
        if ($size > $limit) {
            $mb = (int)($limit / 1048576);
            throw new ChatError(rt_t("Il file supera il limite di $mb MB.", "The file exceeds the $mb MB limit."), 422);
        }

        $name = mb_substr(basename((string)$file['name']), 0, 180, 'UTF-8');
        $name = preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]/u', '', $name) ?: 'allegato';

        $folder = date('Y/m/');
        $dir = dirname(__DIR__) . '/uploads/chat/' . $folder;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new ChatError(rt_t('Impossibile salvare il file.', 'Could not save the file.'), 500);
        }

        $stored = bin2hex(random_bytes(20)) . '.' . $types[$mime]['ext'];
        $dest = $dir . $stored;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new ChatError(rt_t('Impossibile salvare il file.', 'Could not save the file.'), 500);
        }

        if ($kind === 'image') {
            cc_strip_image_metadata($dest, $mime);
            clearstatcache(true, $dest);
            $size = (int)(@filesize($dest) ?: $size);
        }

        return [
            'file_name' => $name,
            'file_path' => '/uploads/chat/' . $folder . $stored,
            'file_size' => $size,
            'file_mime' => $mime,
            'file_type' => $kind,
        ];
    }

    /** Dove sta la copertina di un video: accanto al file, stesso nome. */
    function cc_poster_path(string $videoPath): string
    {
        return preg_replace('/\.[a-z0-9]{2,5}$/', '.poster.jpg', $videoPath);
    }

    /**
     * Salva la copertina di un video, preparata dal browser di chi lo invia.
     *
     * Senza copertina l'anteprima in chat resta nera finché il browser non ha
     * scaricato abbastanza video da disegnarne un fotogramma (con i file dei
     * telefoni può voler dire tutto il file). L'immagine viene ricodificata
     * qui: su disco finisce solo un JPEG scritto dal server, piccolo.
     */
    function cc_store_poster(string $videoPath, string $tmp, int $size): bool
    {
        if (!function_exists('imagecreatefromjpeg') || $size <= 0 || $size > 1024 * 1024 || !is_uploaded_file($tmp)) {
            return false;
        }
        if (!preg_match('#^/uploads/chat/\d{4}/\d{2}/[a-f0-9]{40}\.(mp4|webm)$#', $videoPath)) {
            return false;
        }

        try {
            $info = @getimagesize($tmp);
            if (!$info || ($info['mime'] ?? '') !== 'image/jpeg') {
                return false;
            }
            $image = @imagecreatefromjpeg($tmp);
            if (!$image) {
                return false;
            }
            $width = imagesx($image);
            $height = imagesy($image);
            $scale = min(1, 640 / max(1, max($width, $height)));
            if ($scale < 1) {
                $small = imagescale($image, max(1, (int)round($width * $scale)), max(1, (int)round($height * $scale)));
                if ($small) {
                    imagedestroy($image);
                    $image = $small;
                }
            }
            $ok = imagejpeg($image, dirname(__DIR__) . cc_poster_path($videoPath), 80);
            imagedestroy($image);
            return (bool)$ok;
        } catch (Throwable $e) {
            error_log('[chat] copertina video: ' . $e->getMessage());
            return false;
        }
    }

    /** Cancella dal disco un allegato della chat (e la sua copertina), e solo quello. */
    function cc_delete_upload(string $path): void
    {
        if (!preg_match('#^/uploads/chat/\d{4}/\d{2}/[a-f0-9]{40}\.[a-z0-9]{2,5}$#', $path)) {
            return;
        }
        $full = dirname(__DIR__) . $path;
        if (is_file($full)) {
            @unlink($full);
        }
        $poster = dirname(__DIR__) . cc_poster_path($path);
        if (is_file($poster)) {
            @unlink($poster);
        }
    }

    /** Solo allegati veri della chat: mai un percorso arrivato dal client. */
    function cc_clean_attachments($list): array
    {
        $out = [];
        if (!is_array($list)) {
            return $out;
        }
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $path = (string)($item['file_path'] ?? '');
            if (!preg_match('#^/uploads/chat/\d{4}/\d{2}/[a-f0-9]{40}\.[a-z0-9]{2,5}$#', $path)) {
                continue;
            }
            $type = (string)($item['file_type'] ?? 'file');
            $clean = [
                'file_name' => (string)($item['file_name'] ?? 'allegato'),
                'file_path' => $path,
                'file_size' => (int)($item['file_size'] ?? 0),
                'file_mime' => (string)($item['file_mime'] ?? ''),
                'file_type' => in_array($type, ['image', 'video', 'audio', 'file', 'sticker'], true) ? $type : 'file',
            ];
            if ($clean['file_type'] === 'video') {
                // La copertina non sta nel database: se c'è, è il file accanto.
                $poster = cc_poster_path($path);
                $clean['poster'] = is_file(dirname(__DIR__) . $poster) ? $poster : null;
            }
            $out[] = $clean;
        }
        return $out;
    }

    // ── Conversazioni private ──────────────────────────────────────────────

    function cc_pm_member_columns(mysqli $mysqli): string
    {
        $columns = 'cp.user_id, cp.last_read_message_id, cp.is_muted, cp.is_archived';
        foreach (['nickname', 'is_request', 'cleared_before_id', 'muted_until'] as $optional) {
            if (rt_has_col($mysqli, 'private_conversation_participants', $optional)) {
                $columns .= ', cp.' . $optional;
            }
        }
        return $columns;
    }

    /**
     * Le due righe di una conversazione a due: ['me' => ..., 'other' => ...].
     * Null se chi chiede non ne fa parte: è questo il controllo d'accesso
     * di ogni operazione sulle chat private.
     */
    function cc_pm_pair(mysqli $mysqli, int $conversationId, int $userId): ?array
    {
        if ($conversationId <= 0) {
            return null;
        }
        $stmt = $mysqli->prepare('
            SELECT ' . cc_pm_member_columns($mysqli) . '
            FROM private_conversation_participants cp
            INNER JOIN private_conversations c ON c.id = cp.conversation_id AND c.is_group = 0
            WHERE cp.conversation_id = ?
            LIMIT 3
        ');
        $stmt->bind_param('i', $conversationId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $pair = ['me' => null, 'other' => null, 'id' => $conversationId];
        foreach ($rows as $row) {
            $row['user_id'] = (int)$row['user_id'];
            $row['last_read_message_id'] = (int)($row['last_read_message_id'] ?? 0);
            $row['is_request'] = (int)($row['is_request'] ?? 0) === 1;
            $row['cleared_before_id'] = (int)($row['cleared_before_id'] ?? 0);
            $row['muted'] = (int)$row['is_muted'] === 1
                || (!empty($row['muted_until']) && strtotime((string)$row['muted_until']) > time());
            if ($row['user_id'] === $userId) {
                $pair['me'] = $row;
            } elseif ($pair['other'] === null) {
                $pair['other'] = $row;
            }
        }

        return ($pair['me'] && $pair['other']) ? $pair : null;
    }

    function cc_pm_require(mysqli $mysqli, int $conversationId, int $userId): array
    {
        $pair = cc_pm_pair($mysqli, $conversationId, $userId);
        if (!$pair) {
            throw new ChatError(rt_t('Conversazione non trovata.', 'Conversation not found.'), 404);
        }
        return $pair;
    }

    function cc_pm_find(mysqli $mysqli, int $a, int $b): int
    {
        $stmt = $mysqli->prepare('
            SELECT p1.conversation_id
            FROM private_conversation_participants p1
            INNER JOIN private_conversation_participants p2 ON p2.conversation_id = p1.conversation_id AND p2.user_id = ?
            INNER JOIN private_conversations c ON c.id = p1.conversation_id AND c.is_group = 0
            WHERE p1.user_id = ?
            ORDER BY p1.conversation_id ASC
            LIMIT 1
        ');
        $stmt->bind_param('ii', $b, $a);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();
        return $row ? (int)$row[0] : 0;
    }

    /**
     * Trova la conversazione fra due utenti o la crea. Il lock sulla coppia
     * impedisce che due primi messaggi partiti insieme creino due
     * conversazioni gemelle.
     */
    function cc_pm_open(mysqli $mysqli, int $userId, int $recipientId): int
    {
        $existing = cc_pm_find($mysqli, $userId, $recipientId);
        if ($existing > 0) {
            return $existing;
        }

        $allowed = sc_can_message($mysqli, $userId, $recipientId);
        if (!$allowed['ok']) {
            throw new ChatError($allowed['message'], 403);
        }

        $lock = sc_lock_pair($mysqli, $userId, $recipientId);
        try {
            $existing = cc_pm_find($mysqli, $userId, $recipientId);
            if ($existing > 0) {
                return $existing;
            }

            $asRequest = $allowed['request'] && rt_has_col($mysqli, 'private_conversation_participants', 'is_request');

            $mysqli->begin_transaction();
            try {
                $mysqli->query('INSERT INTO private_conversations (is_group) VALUES (0)');
                $conversationId = (int)$mysqli->insert_id;

                $stmt = $mysqli->prepare('INSERT INTO private_conversation_participants (conversation_id, user_id) VALUES (?, ?)');
                $stmt->bind_param('ii', $conversationId, $userId);
                $stmt->execute();
                $stmt->close();

                if ($asRequest) {
                    $stmt = $mysqli->prepare('INSERT INTO private_conversation_participants (conversation_id, user_id, is_request) VALUES (?, ?, 1)');
                } else {
                    $stmt = $mysqli->prepare('INSERT INTO private_conversation_participants (conversation_id, user_id) VALUES (?, ?)');
                }
                $stmt->bind_param('ii', $conversationId, $recipientId);
                $stmt->execute();
                $stmt->close();

                $mysqli->commit();
            } catch (Throwable $e) {
                $mysqli->rollback();
                throw $e;
            }

            return $conversationId;
        } finally {
            sc_unlock($mysqli, $lock);
        }
    }

    /** Troppi messaggi in pochi secondi: si guarda il più vecchio degli ultimi N. */
    function cc_rate_check(mysqli $mysqli, string $table, int $userId, int $burst = CC_BURST, int $window = 10): void
    {
        $offset = max(0, $burst - 1);
        $stmt = $mysqli->prepare("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) FROM `$table` WHERE sender_id = ? ORDER BY id DESC LIMIT 1 OFFSET $offset");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();
        if ($row && $row[0] !== null && (int)$row[0] < $window) {
            throw new ChatError(rt_t('Stai scrivendo troppo in fretta. Aspetta un attimo.', 'You are sending messages too fast. Wait a moment.'), 429);
        }
    }

    /** Si vede a vicenda «letto» solo se entrambi tengono attive le conferme. */
    function cc_pm_receipts_allowed(mysqli $mysqli, int $a, int $b): bool
    {
        $settings = sc_settings_many($mysqli, [$a, $b]);
        return !empty($settings[$a]['read_receipts']) && !empty($settings[$b]['read_receipts']);
    }

    // ── Lettura dei messaggi privati ───────────────────────────────────────

    /**
     * Condizioni che rendono un messaggio visibile a chi chiede: è nella sua
     * conversazione, non è stato rimosso, non l'ha tolto per sé, e viene dopo
     * il punto in cui ha eventualmente svuotato la chat.
     */
    function cc_pm_visible_sql(array $pair, string $alias = 'm'): array
    {
        $sql = "$alias.conversation_id = ? AND $alias.deleted_at IS NULL"
            . " AND NOT EXISTS (SELECT 1 FROM private_message_deleted d WHERE d.message_id = $alias.id AND d.user_id = ?)";
        $types = 'ii';
        $params = [(int)$pair['id'], (int)$pair['me']['user_id']];
        if ($pair['me']['cleared_before_id'] > 0) {
            $sql .= " AND $alias.id > ?";
            $types .= 'i';
            $params[] = $pair['me']['cleared_before_id'];
        }
        return [$sql, $types, $params];
    }

    function cc_pm_select(): string
    {
        return '
            SELECT m.id, m.conversation_id, m.sender_id, m.message, m.message_type, m.media_url, m.media_title,
                   m.reply_to_id, m.forwarded_from_id, m.is_edited, m.deleted_for_all,
                   m.created_at, UNIX_TIMESTAMP(m.created_at) AS ts,
                   u.username AS sender_username, u.display_name AS sender_display_name,
                   u.ruolo AS sender_role, u.is_premium AS sender_premium,
                   rm.id AS reply_id, rm.sender_id AS reply_sender_id, rm.message AS reply_text,
                   rm.message_type AS reply_type, rm.deleted_for_all AS reply_deleted,
                   ru.username AS reply_username
            FROM private_messages m
            INNER JOIN utenti u ON u.id = m.sender_id
            LEFT JOIN private_messages rm ON rm.id = m.reply_to_id
                  AND rm.conversation_id = m.conversation_id AND rm.deleted_at IS NULL
            LEFT JOIN utenti ru ON ru.id = rm.sender_id
        ';
    }

    /** Dalle righe del database al formato che riceve il browser. */
    function cc_pm_hydrate(mysqli $mysqli, int $viewerId, array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $ids = array_map(static fn($r) => (int)$r['id'], $rows);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $attachments = [];
        $stmt = $mysqli->prepare("SELECT message_id, file_name, file_path, file_size, file_mime, file_type FROM private_message_attachments WHERE message_id IN ($marks) ORDER BY id ASC");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $file = [
                'file_name' => (string)$row['file_name'],
                'file_path' => (string)$row['file_path'],
                'file_size' => (int)$row['file_size'],
                'file_mime' => (string)$row['file_mime'],
                'file_type' => (string)$row['file_type'],
            ];
            if ($file['file_type'] === 'video') {
                $poster = cc_poster_path($file['file_path']);
                $file['poster'] = $poster !== $file['file_path'] && is_file(dirname(__DIR__) . $poster) ? $poster : null;
            }
            $attachments[(int)$row['message_id']][] = $file;
        }
        $stmt->close();

        $reactions = cc_reactions($mysqli, 'private_message_reactions', $viewerId, $ids);

        $pinned = [];
        if (rt_has_table($mysqli, 'private_pinned_messages')) {
            $stmt = $mysqli->prepare("SELECT message_id FROM private_pinned_messages WHERE message_id IN ($marks)");
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_row()) {
                $pinned[(int)$row[0]] = true;
            }
            $stmt->close();
        }

        $favorites = [];
        if (rt_has_table($mysqli, 'private_favorites')) {
            $stmt = $mysqli->prepare("SELECT message_id FROM private_favorites WHERE user_id = ? AND message_id IN ($marks)");
            $params = array_merge([$viewerId], $ids);
            $stmt->bind_param('i' . $types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_row()) {
                $favorites[(int)$row[0]] = true;
            }
            $stmt->close();
        }

        $out = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $deleted = (int)$row['deleted_for_all'] === 1;
            $body = $deleted ? null : ($row['message'] !== null ? (string)$row['message'] : null);

            $reply = null;
            if (!$deleted && !empty($row['reply_id'])) {
                $replyDeleted = (int)$row['reply_deleted'] === 1;
                $reply = [
                    'id' => (int)$row['reply_id'],
                    'sender_id' => (int)$row['reply_sender_id'],
                    'username' => (string)$row['reply_username'],
                    'text' => $replyDeleted ? null : cc_preview($row['reply_text'], 140),
                    'type' => (string)$row['reply_type'],
                    'deleted' => $replyDeleted,
                ];
            }

            $out[] = [
                'id' => $id,
                'kind' => 'private',
                'chat_id' => (int)$row['conversation_id'],
                'conversation_id' => (int)$row['conversation_id'],
                'sender_id' => (int)$row['sender_id'],
                'sender_username' => (string)$row['sender_username'],
                'sender_display_name' => (string)($row['sender_display_name'] ?: $row['sender_username']),
                'sender_role' => (string)$row['sender_role'],
                'sender_premium' => (int)$row['sender_premium'] === 1,
                'message_type' => $deleted ? 'text' : (string)$row['message_type'],
                'body' => $body,
                'message' => $body,
                'media_url' => $deleted ? null : $row['media_url'],
                'media_title' => $deleted ? null : $row['media_title'],
                'attachments' => $deleted ? [] : ($attachments[$id] ?? []),
                'reactions' => $deleted ? [] : ($reactions[$id] ?? []),
                'reply' => $reply,
                'reply_to_id' => $reply ? $reply['id'] : null,
                'reply_message_text' => $reply ? $reply['text'] : null,
                'reply_username' => $reply ? $reply['username'] : null,
                'forwarded' => !$deleted && !empty($row['forwarded_from_id']),
                'is_edited' => !$deleted && (int)$row['is_edited'] === 1,
                'is_deleted' => $deleted,
                'deleted_for_all' => $deleted ? 1 : 0,
                'is_pinned' => !$deleted && isset($pinned[$id]),
                'is_favorite' => !$deleted && isset($favorites[$id]),
                'created_at' => (string)$row['created_at'],
                'ts' => (int)$row['ts'],
            ];
        }
        return $out;
    }

    /**
     * Messaggi di una conversazione privata.
     *
     * Opzioni: before / after (id), ids (lista), around (id, per saltare a un
     * messaggio), limit. Restituisce anche `gone`: degli id richiesti, quelli
     * che non sono più visibili (il browser li toglie).
     */
    function cc_pm_fetch(mysqli $mysqli, array $pair, array $options = []): array
    {
        $viewerId = (int)$pair['me']['user_id'];
        $limit = max(1, min(80, (int)($options['limit'] ?? CC_PAGE)));
        [$visible, $types, $params] = cc_pm_visible_sql($pair);

        $run = static function (string $extra, string $extraTypes, array $extraParams, string $order, int $take) use ($mysqli, $visible, $types, $params): array {
            $stmt = $mysqli->prepare(cc_pm_select() . " WHERE $visible $extra ORDER BY m.id $order LIMIT ?");
            $all = array_merge($params, $extraParams, [$take]);
            $stmt->bind_param($types . $extraTypes . 'i', ...$all);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        };

        $hasMore = false;
        $hasNewer = false;
        $gone = [];

        if (!empty($options['ids'])) {
            $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array)$options['ids'])))), 0, 80);
            if (!$ids) {
                return ['messages' => [], 'has_more' => false, 'has_newer' => false, 'gone' => []];
            }
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $rows = $run("AND m.id IN ($marks)", str_repeat('i', count($ids)), $ids, 'ASC', count($ids));
            $found = array_map(static fn($r) => (int)$r['id'], $rows);
            $gone = array_values(array_diff($ids, $found));
        } elseif (!empty($options['around'])) {
            $around = (int)$options['around'];
            $half = (int)ceil($limit / 2);
            $older = array_reverse($run('AND m.id <= ?', 'i', [$around], 'DESC', $half + 1));
            $newer = $run('AND m.id > ?', 'i', [$around], 'ASC', $half + 1);
            $hasMore = count($older) > $half;
            $hasNewer = count($newer) > $half;
            if ($hasMore) {
                array_shift($older);
            }
            if ($hasNewer) {
                array_pop($newer);
            }
            $rows = array_merge($older, $newer);
        } elseif (!empty($options['after'])) {
            $rows = $run('AND m.id > ?', 'i', [(int)$options['after']], 'ASC', $limit + 1);
            $hasNewer = count($rows) > $limit;
            if ($hasNewer) {
                array_pop($rows);
            }
        } else {
            $extra = '';
            $extraTypes = '';
            $extraParams = [];
            if (!empty($options['before'])) {
                $extra = 'AND m.id < ?';
                $extraTypes = 'i';
                $extraParams = [(int)$options['before']];
            }
            $rows = $run($extra, $extraTypes, $extraParams, 'DESC', $limit + 1);
            $hasMore = count($rows) > $limit;
            if ($hasMore) {
                array_pop($rows);
            }
            $rows = array_reverse($rows);
        }

        return [
            'messages' => cc_pm_hydrate($mysqli, $viewerId, $rows),
            'has_more' => $hasMore,
            'has_newer' => $hasNewer,
            'gone' => $gone,
        ];
    }

    function cc_pm_one(mysqli $mysqli, array $pair, int $messageId): ?array
    {
        return cc_pm_fetch($mysqli, $pair, ['ids' => [$messageId]])['messages'][0] ?? null;
    }

    /**
     * Riga grezza di un messaggio più la conversazione di chi chiede.
     * Qualunque azione su un messaggio parte da qui.
     */
    function cc_pm_message_context(mysqli $mysqli, int $messageId, int $userId): array
    {
        $stmt = $mysqli->prepare('SELECT id, conversation_id, sender_id, message_type, deleted_for_all, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age FROM private_messages WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->bind_param('i', $messageId);
        $stmt->execute();
        $message = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $pair = $message ? cc_pm_pair($mysqli, (int)$message['conversation_id'], $userId) : null;
        if (!$message || !$pair) {
            // Stessa risposta per «non esiste» e «non è tuo»: gli id altrui non si sondano.
            throw new ChatError(rt_t('Messaggio non trovato.', 'Message not found.'), 404);
        }
        if ((int)$message['id'] <= $pair['me']['cleared_before_id']) {
            throw new ChatError(rt_t('Messaggio non trovato.', 'Message not found.'), 404);
        }

        $stmt = $mysqli->prepare('SELECT 1 FROM private_message_deleted WHERE message_id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $messageId, $userId);
        $stmt->execute();
        $hidden = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($hidden) {
            throw new ChatError(rt_t('Messaggio non trovato.', 'Message not found.'), 404);
        }

        return ['message' => $message, 'pair' => $pair];
    }

    /** Avvisa i due partecipanti che in una conversazione è cambiato qualcosa. */
    function cc_pm_signal(array $pair, array $event, bool $onlyMe = false): void
    {
        $event['c'] = (int)$pair['id'];
        rt_push_user((int)$pair['me']['user_id'], $event);
        if (!$onlyMe) {
            rt_push_user((int)$pair['other']['user_id'], $event);
        }
    }

    // ── Invio ──────────────────────────────────────────────────────────────

    /**
     * Invia un messaggio privato (testo, GIF o allegati già salvati).
     *
     * $input: conversation_id | recipient_id, message, message_type,
     *         media_url, media_title, reply_to_id, forwarded (bool).
     */
    function cc_pm_send(mysqli $mysqli, int $userId, array $input, array $attachments = []): array
    {
        $conversationId = (int)($input['conversation_id'] ?? 0);
        $recipientId = (int)($input['recipient_id'] ?? 0);
        $type = (string)($input['message_type'] ?? 'text');
        $type = $attachments ? 'media' : ($type === 'gif' ? 'gif' : 'text');

        $text = cc_validate_text((string)($input['message'] ?? ''), $type !== 'text');
        $mediaUrl = null;
        $mediaTitle = null;
        if ($type === 'gif') {
            $mediaUrl = trim((string)($input['media_url'] ?? ''));
            if (!function_exists('chat_is_allowed_gif_url') || !chat_is_allowed_gif_url($mediaUrl) || mb_strlen($mediaUrl, 'UTF-8') > 255) {
                throw new ChatError(rt_t('GIF non valida.', 'Invalid GIF.'), 422);
            }
            $mediaTitle = mb_substr(cc_clean_text((string)($input['media_title'] ?? 'GIF')), 0, 160, 'UTF-8') ?: 'GIF';
        }

        if ($conversationId <= 0) {
            if ($recipientId <= 0 || $recipientId === $userId) {
                throw new ChatError(rt_t('Destinatario non valido.', 'Invalid recipient.'), 422);
            }
            $conversationId = cc_pm_open($mysqli, $userId, $recipientId);
        }

        $pair = cc_pm_require($mysqli, $conversationId, $userId);
        $otherId = (int)$pair['other']['user_id'];

        $allowed = sc_can_message($mysqli, $userId, $otherId);
        if (!$allowed['ok']) {
            throw new ChatError($allowed['message'], 403);
        }

        cc_rate_check($mysqli, 'private_messages', $userId);

        // Finché l'altro non accetta la richiesta, pochi messaggi: una
        // richiesta non deve poter diventare un bombardamento.
        if ($pair['other']['is_request']) {
            $stmt = $mysqli->prepare('SELECT COUNT(*) FROM private_messages WHERE conversation_id = ? AND sender_id = ? AND deleted_at IS NULL');
            $stmt->bind_param('ii', $conversationId, $userId);
            $stmt->execute();
            $sent = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
            if ($sent >= CC_REQUEST_MAX_MESSAGES) {
                throw new ChatError(rt_t(
                    'Aspetta che l\'altra persona accetti la tua richiesta prima di scrivere ancora.',
                    'Wait for the other person to accept your request before writing again.'
                ), 429);
            }
        }

        // La risposta cita solo un messaggio di questa conversazione che chi
        // scrive può vedere. Un id qualsiasi viene semplicemente ignorato.
        $replyId = (int)($input['reply_to_id'] ?? 0);
        if ($replyId > 0) {
            [$visible, $types, $params] = cc_pm_visible_sql($pair);
            $stmt = $mysqli->prepare("SELECT m.id FROM private_messages m WHERE $visible AND m.id = ? AND m.deleted_for_all = 0 LIMIT 1");
            $all = array_merge($params, [$replyId]);
            $stmt->bind_param($types . 'i', ...$all);
            $stmt->execute();
            $replyId = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
        }
        $replyParam = $replyId > 0 ? $replyId : null;

        // forwarded_from_id resta un semplice segnale «inoltrato»: punta al
        // messaggio stesso, mai a quello d'origine, così non lega fra loro
        // conversazioni diverse.
        $forwarded = !empty($input['forwarded']);

        $mysqli->begin_transaction();
        try {
            $body = $text === '' ? null : $text;
            $stmt = $mysqli->prepare('
                INSERT INTO private_messages (conversation_id, sender_id, message, message_type, media_url, media_title, reply_to_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->bind_param('iissssi', $conversationId, $userId, $body, $type, $mediaUrl, $mediaTitle, $replyParam);
            $stmt->execute();
            $messageId = (int)$mysqli->insert_id;
            $stmt->close();

            if ($forwarded) {
                $stmt = $mysqli->prepare('UPDATE private_messages SET forwarded_from_id = id WHERE id = ?');
                $stmt->bind_param('i', $messageId);
                $stmt->execute();
                $stmt->close();
            }

            if ($attachments) {
                $stmt = $mysqli->prepare('INSERT INTO private_message_attachments (message_id, file_name, file_path, file_size, file_mime, file_type) VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($attachments as $file) {
                    $stmt->bind_param('ississ', $messageId, $file['file_name'], $file['file_path'], $file['file_size'], $file['file_mime'], $file['file_type']);
                    $stmt->execute();
                }
                $stmt->close();
            }

            $stmt = $mysqli->prepare('UPDATE private_conversations SET updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $conversationId);
            $stmt->execute();
            $stmt->close();

            // Chi scrive ha letto fin qui, e la conversazione torna fra le
            // attive per entrambi. Rispondere a una richiesta la accetta.
            $extra = $pair['me']['is_request'] ? ', is_request = 0' : '';
            $stmt = $mysqli->prepare("UPDATE private_conversation_participants SET is_archived = 0, last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), ?)$extra WHERE conversation_id = ? AND user_id = ?");
            $stmt->bind_param('iii', $messageId, $conversationId, $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare('UPDATE private_conversation_participants SET is_archived = 0 WHERE conversation_id = ? AND user_id = ? AND is_archived = 1');
            $stmt->bind_param('ii', $conversationId, $otherId);
            $stmt->execute();
            $stmt->close();

            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        try {
            if (function_exists('trackMissionProgress')) {
                trackMissionProgress($mysqli, $userId, 'send_message');
                trackMissionProgress($mysqli, $userId, 'send_private_message');
            }
            if (function_exists('stats_track')) {
                stats_track($mysqli, $userId, 'msg_private');
            }
        } catch (Throwable $e) {
            error_log('[chat] tracking privato: ' . $e->getMessage());
        }

        $event = ['t' => 'pm', 'm' => $messageId, 'f' => $userId];
        if ($pair['other']['is_request']) {
            $event['rq'] = 1;
        }
        if ($pair['other']['muted']) {
            $event['q'] = 1; // silenziata dal destinatario: niente avviso sonoro
        }
        cc_pm_signal($pair, $event);

        $message = cc_pm_one($mysqli, $pair, $messageId);
        return ['message' => $message, 'conversation_id' => $conversationId, 'recipient_id' => $otherId];
    }

    // ── Azioni sui messaggi ────────────────────────────────────────────────

    function cc_pm_edit(mysqli $mysqli, int $userId, int $messageId, string $text): array
    {
        $context = cc_pm_message_context($mysqli, $messageId, $userId);
        $message = $context['message'];
        if ((int)$message['sender_id'] !== $userId) {
            throw new ChatError(rt_t('Puoi modificare solo i tuoi messaggi.', 'You can only edit your own messages.'), 403);
        }
        if ((int)$message['deleted_for_all'] === 1 || $message['message_type'] === 'system') {
            throw new ChatError(rt_t('Questo messaggio non si può modificare.', 'This message cannot be edited.'), 422);
        }
        if ((int)$message['age'] > CC_EDIT_WINDOW) {
            throw new ChatError(rt_t('È passato troppo tempo per modificarlo.', 'Too much time has passed to edit it.'), 403);
        }

        $text = cc_validate_text($text, $message['message_type'] !== 'text');
        $body = $text === '' ? null : $text;
        $stmt = $mysqli->prepare('UPDATE private_messages SET message = ?, is_edited = 1 WHERE id = ?');
        $stmt->bind_param('si', $body, $messageId);
        $stmt->execute();
        $stmt->close();

        cc_pm_signal($context['pair'], ['t' => 'pu', 'm' => $messageId]);
        return ['message' => cc_pm_one($mysqli, $context['pair'], $messageId)];
    }

    function cc_pm_delete(mysqli $mysqli, int $userId, int $messageId, bool $forEveryone): array
    {
        $context = cc_pm_message_context($mysqli, $messageId, $userId);
        $message = $context['message'];

        if (!$forEveryone) {
            $stmt = $mysqli->prepare('INSERT IGNORE INTO private_message_deleted (message_id, user_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $messageId, $userId);
            $stmt->execute();
            $stmt->close();
            cc_pm_signal($context['pair'], ['t' => 'pu', 'm' => $messageId], true);
            return ['message_id' => $messageId, 'deleted_for_self' => true];
        }

        if ((int)$message['sender_id'] !== $userId) {
            throw new ChatError(rt_t('Puoi eliminare per tutti solo i tuoi messaggi.', 'You can only delete your own messages for everyone.'), 403);
        }

        $stmt = $mysqli->prepare('SELECT file_path FROM private_message_attachments WHERE message_id = ?');
        $stmt->bind_param('i', $messageId);
        $stmt->execute();
        $files = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'file_path');
        $stmt->close();

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare('UPDATE private_messages SET deleted_for_all = 1, message = NULL, media_url = NULL, media_title = NULL WHERE id = ?');
            $stmt->bind_param('i', $messageId);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare('DELETE FROM private_message_attachments WHERE message_id = ?');
            $stmt->bind_param('i', $messageId);
            $stmt->execute();
            $stmt->close();

            foreach (['private_pinned_messages', 'private_message_reactions'] as $table) {
                if (rt_has_table($mysqli, $table)) {
                    $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE message_id = ?");
                    $stmt->bind_param('i', $messageId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        foreach ($files as $path) {
            cc_delete_upload((string)$path);
        }

        cc_pm_signal($context['pair'], ['t' => 'pu', 'm' => $messageId]);
        return ['message_id' => $messageId, 'deleted_for_all' => true, 'message' => cc_pm_one($mysqli, $context['pair'], $messageId)];
    }

    function cc_pm_react(mysqli $mysqli, int $userId, int $messageId, string $reaction): array
    {
        $context = cc_pm_message_context($mysqli, $messageId, $userId);
        if ((int)$context['message']['deleted_for_all'] === 1) {
            throw new ChatError(rt_t('Messaggio non trovato.', 'Message not found.'), 404);
        }
        if (!cc_ensure_reaction_table($mysqli, 'private_message_reactions', 'private_messages')) {
            throw new ChatError(rt_t('Reazioni non disponibili.', 'Reactions are not available.'), 503);
        }
        $length = rt_col_len($mysqli, 'private_message_reactions', 'reaction') ?: 20;
        if (!cc_valid_reaction($mysqli, $reaction, $length)) {
            throw new ChatError(rt_t('Reazione non valida.', 'Invalid reaction.'), 422);
        }

        cc_toggle_reaction($mysqli, 'private_message_reactions', $messageId, $userId, $reaction);
        cc_pm_signal($context['pair'], ['t' => 'pu', 'm' => $messageId]);

        return [
            'message_id' => $messageId,
            'reactions' => cc_reactions($mysqli, 'private_message_reactions', $userId, [$messageId])[$messageId] ?? [],
        ];
    }

    function cc_pm_toggle_pin(mysqli $mysqli, int $userId, int $messageId): array
    {
        $context = cc_pm_message_context($mysqli, $messageId, $userId);
        if ((int)$context['message']['deleted_for_all'] === 1 || !rt_has_table($mysqli, 'private_pinned_messages')) {
            throw new ChatError(rt_t('Questo messaggio non si può fissare.', 'This message cannot be pinned.'), 422);
        }
        $conversationId = (int)$context['pair']['id'];

        $stmt = $mysqli->prepare('DELETE FROM private_pinned_messages WHERE conversation_id = ? AND message_id = ?');
        $stmt->bind_param('ii', $conversationId, $messageId);
        $stmt->execute();
        $wasPinned = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$wasPinned) {
            $stmt = $mysqli->prepare('SELECT COUNT(*) FROM private_pinned_messages WHERE conversation_id = ?');
            $stmt->bind_param('i', $conversationId);
            $stmt->execute();
            $count = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
            if ($count >= 30) {
                throw new ChatError(rt_t('Ci sono già troppi messaggi fissati in questa chat.', 'There are already too many pinned messages in this chat.'), 422);
            }

            if (rt_has_col($mysqli, 'private_pinned_messages', 'pinned_by')) {
                $stmt = $mysqli->prepare('INSERT IGNORE INTO private_pinned_messages (conversation_id, message_id, pinned_by) VALUES (?, ?, ?)');
                $stmt->bind_param('iii', $conversationId, $messageId, $userId);
            } else {
                $stmt = $mysqli->prepare('INSERT IGNORE INTO private_pinned_messages (conversation_id, message_id) VALUES (?, ?)');
                $stmt->bind_param('ii', $conversationId, $messageId);
            }
            $stmt->execute();
            $stmt->close();
        }

        cc_pm_signal($context['pair'], ['t' => 'pu', 'm' => $messageId, 'pin' => 1]);
        return ['message_id' => $messageId, 'pinned' => !$wasPinned];
    }

    function cc_pm_toggle_favorite(mysqli $mysqli, int $userId, int $messageId): array
    {
        $context = cc_pm_message_context($mysqli, $messageId, $userId);
        if (!rt_has_table($mysqli, 'private_favorites')) {
            throw new ChatError(rt_t('I messaggi salvati non sono ancora attivi.', 'Saved messages are not available yet.'), 503);
        }

        $stmt = $mysqli->prepare('DELETE FROM private_favorites WHERE user_id = ? AND message_id = ?');
        $stmt->bind_param('ii', $userId, $messageId);
        $stmt->execute();
        $was = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$was) {
            $stmt = $mysqli->prepare('INSERT IGNORE INTO private_favorites (user_id, message_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $userId, $messageId);
            $stmt->execute();
            $stmt->close();
        }

        cc_pm_signal($context['pair'], ['t' => 'pu', 'm' => $messageId], true);
        return ['message_id' => $messageId, 'favorited' => !$was];
    }

    /** Segna come letta la conversazione fino a un certo messaggio. */
    function cc_pm_mark_read(mysqli $mysqli, array $pair, int $upTo = 0): int
    {
        $conversationId = (int)$pair['id'];
        $userId = (int)$pair['me']['user_id'];

        $stmt = $mysqli->prepare('SELECT MAX(id) FROM private_messages WHERE conversation_id = ? AND deleted_at IS NULL');
        $stmt->bind_param('i', $conversationId);
        $stmt->execute();
        $max = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();

        $target = $upTo > 0 ? min($upTo, $max) : $max;
        if ($target <= $pair['me']['last_read_message_id']) {
            return $pair['me']['last_read_message_id'];
        }

        $stmt = $mysqli->prepare('UPDATE private_conversation_participants SET last_read_message_id = ? WHERE conversation_id = ? AND user_id = ?');
        $stmt->bind_param('iii', $target, $conversationId, $userId);
        $stmt->execute();
        $stmt->close();

        rt_push_user($userId, ['t' => 'rd', 'c' => $conversationId, 'f' => $userId, 'm' => $target]);
        // Chi non ha ancora accettato una richiesta non rivela di averla letta.
        if (!$pair['me']['is_request'] && cc_pm_receipts_allowed($mysqli, $userId, (int)$pair['other']['user_id'])) {
            rt_push_user((int)$pair['other']['user_id'], ['t' => 'rd', 'c' => $conversationId, 'f' => $userId, 'm' => $target]);
        }

        return $target;
    }

    // ── Elenco delle conversazioni ─────────────────────────────────────────

    function cc_pm_list(mysqli $mysqli, int $userId): array
    {
        $hasNick = rt_has_col($mysqli, 'private_conversation_participants', 'nickname');
        $hasRequest = rt_has_col($mysqli, 'private_conversation_participants', 'is_request');
        $hasCleared = rt_has_col($mysqli, 'private_conversation_participants', 'cleared_before_id');
        $hasUntil = rt_has_col($mysqli, 'private_conversation_participants', 'muted_until');
        $hasPins = rt_has_table($mysqli, 'private_conversation_pins');

        $cleared = $hasCleared ? 'COALESCE(cp.cleared_before_id, 0)' : '0';
        $notMine = 'NOT EXISTS (SELECT 1 FROM private_message_deleted d WHERE d.message_id = pm.id AND d.user_id = cp.user_id)';

        $sql = "
            SELECT c.id AS conversation_id, cp.is_muted, cp.is_archived, cp.last_read_message_id,
                   " . ($hasRequest ? 'cp.is_request' : '0') . " AS is_request,
                   " . ($hasRequest ? 'o.is_request' : '0') . " AS other_is_request,
                   " . ($hasUntil ? 'cp.muted_until' : 'NULL') . " AS muted_until,
                   " . ($hasPins ? 'EXISTS(SELECT 1 FROM private_conversation_pins pin WHERE pin.user_id = cp.user_id AND pin.conversation_id = c.id)' : '0') . " AS is_pinned,
                   o.user_id AS other_user_id, o.last_read_message_id AS other_last_read,
                   " . ($hasNick ? 'o.nickname' : 'NULL') . " AS other_nickname,
                   ou.username AS other_username, ou.display_name AS other_display_name,
                   ou.ruolo AS other_role, ou.is_premium AS other_premium,
                   TIMESTAMPDIFF(SECOND, ou.ultimo_accesso, NOW()) AS other_idle,
                   UNIX_TIMESTAMP(ou.ultimo_accesso) AS other_seen_ts,
                   UNIX_TIMESTAMP(c.created_at) AS created_ts,
                   (SELECT MAX(pm.id) FROM private_messages pm
                     WHERE pm.conversation_id = c.id AND pm.deleted_at IS NULL AND pm.id > $cleared AND $notMine) AS last_id,
                   (SELECT COUNT(*) FROM private_messages pm
                     WHERE pm.conversation_id = c.id AND pm.deleted_at IS NULL AND pm.id > $cleared
                       AND pm.id > COALESCE(cp.last_read_message_id, 0) AND pm.sender_id <> cp.user_id AND $notMine) AS unread
            FROM private_conversation_participants cp
            INNER JOIN private_conversations c ON c.id = cp.conversation_id AND c.is_group = 0
            INNER JOIN private_conversation_participants o ON o.conversation_id = c.id AND o.user_id <> cp.user_id
            INNER JOIN utenti ou ON ou.id = o.user_id
            WHERE cp.user_id = ?
            ORDER BY c.updated_at DESC
            LIMIT 300
        ";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $lastIds = array_values(array_filter(array_map(static fn($r) => (int)$r['last_id'], $rows)));
        $last = [];
        if ($lastIds) {
            $marks = implode(',', array_fill(0, count($lastIds), '?'));
            $stmt = $mysqli->prepare("
                SELECT m.id, m.sender_id, m.message, m.message_type, m.deleted_for_all, UNIX_TIMESTAMP(m.created_at) AS ts,
                       (SELECT a.file_type FROM private_message_attachments a WHERE a.message_id = m.id ORDER BY a.id ASC LIMIT 1) AS attachment_type
                FROM private_messages m WHERE m.id IN ($marks)
            ");
            $stmt->bind_param(str_repeat('i', count($lastIds)), ...$lastIds);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $last[(int)$row['id']] = $row;
            }
            $stmt->close();
        }

        $otherIds = array_map(static fn($r) => (int)$r['other_user_id'], $rows);
        $settings = sc_settings_many($mysqli, array_merge([$userId], $otherIds));
        $blocked = array_flip(sc_hidden_ids($mysqli, $userId));

        $list = [];
        foreach ($rows as $row) {
            $otherId = (int)$row['other_user_id'];
            $lastRow = $last[(int)$row['last_id']] ?? null;
            $idle = $row['other_idle'];
            $isBlocked = isset($blocked[$otherId]);
            $receipts = !empty($settings[$userId]['read_receipts']) && !empty($settings[$otherId]['read_receipts']);
            $muted = (int)$row['is_muted'] === 1 || (!empty($row['muted_until']) && strtotime((string)$row['muted_until']) > time());
            $deleted = $lastRow && (int)$lastRow['deleted_for_all'] === 1;

            $list[] = [
                'conversation_id' => (int)$row['conversation_id'],
                'other_user_id' => $otherId,
                'other_username' => (string)$row['other_username'],
                'other_display_name' => (string)($row['other_display_name'] ?: $row['other_username']),
                'other_nickname' => $row['other_nickname'] !== null && $row['other_nickname'] !== '' ? (string)$row['other_nickname'] : null,
                'other_role' => (string)$row['other_role'],
                'other_premium' => (int)$row['other_premium'] === 1,
                'is_online' => !$isBlocked && $idle !== null && (int)$idle < SC_ONLINE_WINDOW,
                'last_seen_ts' => (!$isBlocked && $row['other_seen_ts'] !== null) ? (int)$row['other_seen_ts'] : null,
                'is_blocked' => $isBlocked,
                'is_muted' => $muted,
                'muted_until_ts' => !empty($row['muted_until']) ? strtotime((string)$row['muted_until']) : null,
                'is_archived' => (int)$row['is_archived'] === 1,
                'is_pinned' => (int)$row['is_pinned'] === 1,
                'is_request' => (int)$row['is_request'] === 1,
                'awaiting_accept' => (int)$row['other_is_request'] === 1,
                'unread_count' => (int)$row['unread'],
                'last_read_id' => (int)$row['last_read_message_id'],
                'other_last_read_id' => ($receipts && (int)$row['other_is_request'] !== 1) ? (int)$row['other_last_read'] : null,
                // Vuota: appena creata, oppure svuotata da chi guarda. La
                // pagina la mostra solo se è quella aperta.
                'is_empty' => !$lastRow,
                'last_message_id' => $lastRow ? (int)$lastRow['id'] : null,
                'last_message_sender_id' => $lastRow ? (int)$lastRow['sender_id'] : null,
                'last_message_text' => ($lastRow && !$deleted) ? cc_preview($lastRow['message'], 110) : null,
                'last_message_type' => $lastRow ? ($deleted ? 'deleted' : (string)$lastRow['message_type']) : null,
                'last_message_attachment_type' => ($lastRow && !$deleted) ? $lastRow['attachment_type'] : null,
                'last_ts' => $lastRow ? (int)$lastRow['ts'] : (int)$row['created_ts'],
            ];
        }

        return $list;
    }
}
