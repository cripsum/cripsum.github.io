<?php
declare(strict_types=1);

/**
 * Logica condivisa dei ticket, usata dal sito (it/en supporto, api/tickets.php)
 * e dagli endpoint del bot Discord (api/bot/tickets/*).
 *
 * Il ponte con Discord si regge su due colonne di `site_tickets`:
 * `discord_thread_id` (il thread collegato) e `source` ("site" o "discord").
 * Se non esistono ancora, tutto continua a funzionare senza il ponte.
 */

require_once __DIR__ . '/security_helpers.php';

/**
 * Argomenti ammessi, gli stessi del modulo di supporto sul sito.
 *
 * @return string[]
 */
function cripsum_ticket_topics(): array
{
    return ['Segnalazione Bug', 'Problema Account', 'Segnalazione Utente', 'Altro'];
}

function cripsum_ticket_normalize_topic(string $topic): string
{
    $topics = cripsum_ticket_topics();

    foreach ($topics as $allowed) {
        if (mb_strtolower($allowed) === mb_strtolower(trim($topic))) {
            return $allowed;
        }
    }

    return 'Altro';
}

/**
 * Quali colonne opzionali del ponte esistono davvero.
 *
 * @return array{thread: bool, source: bool}
 */
function cripsum_ticket_schema(mysqli $mysqli): array
{
    static $schema = null;

    if ($schema === null) {
        $schema = [
            'thread' => auth_column_exists($mysqli, 'site_tickets', 'discord_thread_id'),
            'source' => auth_column_exists($mysqli, 'site_tickets', 'source'),
        ];
    }

    return $schema;
}

/**
 * Identificativo nuovo, nello stesso formato del sito (TK-XXXXXX), garantito
 * libero.
 */
function cripsum_ticket_generate_id(mysqli $mysqli): string
{
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = 'TK-' . strtoupper(bin2hex(random_bytes(3)));

        $stmt = $mysqli->prepare('SELECT 1 FROM site_tickets WHERE ticket_id = ? LIMIT 1');
        if (!$stmt) {
            return $candidate;
        }

        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $stmt->store_result();
        $taken = $stmt->num_rows > 0;
        $stmt->close();

        if (!$taken) {
            return $candidate;
        }
    }

    throw new RuntimeException('Impossibile generare un identificativo ticket libero.');
}

/**
 * Ticket per identificativo del sito o per thread Discord.
 *
 * @return array<string, mixed>|null
 */
function cripsum_ticket_find(mysqli $mysqli, string $ticketId = '', string $threadId = ''): ?array
{
    $schema = cripsum_ticket_schema($mysqli);
    $select = 't.id, t.ticket_id, t.user_id, t.title, t.topic, t.status, t.created_at, u.username, u.discord_id';
    $select .= $schema['thread'] ? ', t.discord_thread_id' : ', NULL AS discord_thread_id';
    $select .= $schema['source'] ? ', t.source' : ", 'site' AS source";

    if ($threadId !== '' && $schema['thread']) {
        $sql = "SELECT $select FROM site_tickets t LEFT JOIN utenti u ON u.id = t.user_id WHERE t.discord_thread_id = ? LIMIT 1";
        $param = $threadId;
    } elseif ($ticketId !== '') {
        $sql = "SELECT $select FROM site_tickets t LEFT JOIN utenti u ON u.id = t.user_id WHERE t.ticket_id = ? LIMIT 1";
        $param = $ticketId;
    } else {
        return null;
    }

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $param);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $ticket ?: null;
}

/**
 * Collega un thread Discord a un ticket gia' esistente.
 */
function cripsum_ticket_attach_thread(mysqli $mysqli, string $ticketId, string $threadId): bool
{
    if (!cripsum_ticket_schema($mysqli)['thread']) {
        return false;
    }

    if (!preg_match('/^\d{15,25}$/', $threadId)) {
        return false;
    }

    $stmt = $mysqli->prepare('UPDATE site_tickets SET discord_thread_id = ? WHERE ticket_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $threadId, $ticketId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Aggiunge un messaggio alla conversazione di un ticket.
 *
 * `$unreadFor` dice a chi va segnalato il messaggio come non letto: "user" se
 * a scrivere e' stato lo staff, "admin" se e' stato l'utente.
 */
function cripsum_ticket_add_message(
    mysqli $mysqli,
    string $ticketId,
    int $senderId,
    string $message,
    ?string $attachmentUrl = null,
    string $unreadFor = 'user'
): bool {
    $stmt = $mysqli->prepare(
        'INSERT INTO site_ticket_messages (ticket_id, sender_id, message, attachment_url)
         VALUES (?, ?, ?, ?)'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('siss', $ticketId, $senderId, $message, $attachmentUrl);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return false;
    }

    // updated_at si aggiorna da solo (ON UPDATE CURRENT_TIMESTAMP), ma serve
    // toccare la riga perche' il ticket risalga in cima alla lista.
    $readColumn = $unreadFor === 'admin' ? 'admin_read' : 'user_read';
    if (auth_column_exists($mysqli, 'site_tickets', $readColumn)) {
        $update = $mysqli->prepare(
            "UPDATE site_tickets SET $readColumn = 0, updated_at = NOW() WHERE ticket_id = ? LIMIT 1"
        );
        if ($update) {
            $update->bind_param('s', $ticketId);
            $update->execute();
            $update->close();
        }
    } else {
        $update = $mysqli->prepare('UPDATE site_tickets SET updated_at = NOW() WHERE ticket_id = ? LIMIT 1');
        if ($update) {
            $update->bind_param('s', $ticketId);
            $update->execute();
            $update->close();
        }
    }

    return true;
}

/**
 * Cronologia dei messaggi di un ticket.
 *
 * @return array<int, array<string, mixed>>
 */
function cripsum_ticket_messages(mysqli $mysqli, string $ticketId, int $limit = 200): array
{
    $limit = max(1, min(500, $limit));
    $stmt = $mysqli->prepare(
        "SELECT tm.id, tm.sender_id, tm.message, tm.attachment_url, tm.created_at,
                u.username, u.ruolo, u.discord_id
         FROM site_ticket_messages tm
         LEFT JOIN utenti u ON u.id = tm.sender_id
         WHERE tm.ticket_id = ?
         ORDER BY tm.created_at ASC, tm.id ASC
         LIMIT $limit"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('s', $ticketId);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $messages;
}

/**
 * Apre o chiude un ticket.
 */
function cripsum_ticket_set_status(mysqli $mysqli, string $ticketId, string $status): bool
{
    $status = $status === 'closed' ? 'closed' : 'open';

    $stmt = $mysqli->prepare('UPDATE site_tickets SET status = ?, updated_at = NOW() WHERE ticket_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $status, $ticketId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Crea un ticket e il suo primo messaggio, in transazione.
 *
 * @return array{ticket_id: string}|null
 */
function cripsum_ticket_create(
    mysqli $mysqli,
    int $userId,
    string $title,
    string $topic,
    string $message,
    string $source = 'site',
    ?string $threadId = null
): ?array {
    $schema = cripsum_ticket_schema($mysqli);
    $ticketId = cripsum_ticket_generate_id($mysqli);
    $topic = cripsum_ticket_normalize_topic($topic);
    $source = $source === 'discord' ? 'discord' : 'site';

    $columns = ['ticket_id', 'user_id', 'title', 'topic'];
    $placeholders = ['?', '?', '?', '?'];
    $types = 'siss';
    $params = [$ticketId, $userId, $title, $topic];

    if ($schema['source']) {
        $columns[] = 'source';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $source;
    }

    if ($schema['thread'] && $threadId !== null && preg_match('/^\d{15,25}$/', $threadId)) {
        $columns[] = 'discord_thread_id';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $threadId;
    }

    $mysqli->begin_transaction();

    try {
        $sql = 'INSERT INTO site_tickets (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Query di creazione ticket non valida.');
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Impossibile creare il ticket.');
        }
        $stmt->close();

        $stmtMsg = $mysqli->prepare(
            'INSERT INTO site_ticket_messages (ticket_id, sender_id, message) VALUES (?, ?, ?)'
        );
        if (!$stmtMsg) {
            throw new RuntimeException('Query del primo messaggio non valida.');
        }

        $stmtMsg->bind_param('sis', $ticketId, $userId, $message);
        if (!$stmtMsg->execute()) {
            $stmtMsg->close();
            throw new RuntimeException('Impossibile salvare il primo messaggio.');
        }
        $stmtMsg->close();

        $mysqli->commit();

        return ['ticket_id' => $ticketId];
    } catch (Throwable $e) {
        $mysqli->rollback();
        error_log('[Tickets] ' . $e->getMessage());
        return null;
    }
}

/**
 * Salva un allegato immagine arrivato in base64 (dal bot Discord).
 *
 * I link del CDN di Discord scadono, quindi l'immagine va ricopiata sul sito
 * invece di salvarne l'URL. Stessi limiti e stessa cartella del modulo di
 * supporto: 5 MB, solo immagini vere, nome casuale.
 *
 * @return string|null URL pubblico dell'allegato, o null se non valido.
 */
function cripsum_ticket_store_attachment(string $base64, int $maxBytes = 5242880): ?string
{
    $binary = base64_decode($base64, true);
    if ($binary === false || $binary === '') {
        return null;
    }

    if (strlen($binary) > $maxBytes) {
        return null;
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'cripsum_ticket_');
    if ($tempFile === false) {
        return null;
    }

    try {
        if (file_put_contents($tempFile, $binary) === false) {
            return null;
        }

        $check = @getimagesize($tempFile);
        if ($check === false) {
            return null;
        }

        $allowedImageMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        $detectedMime = (string)($check['mime'] ?? '');
        if (!isset($allowedImageMimes[$detectedMime])) {
            return null;
        }

        $uploadDir = __DIR__ . '/../uploads/tickets/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return null;
        }

        $fileName = 'img_' . bin2hex(random_bytes(16)) . '.' . $allowedImageMimes[$detectedMime];
        if (!rename($tempFile, $uploadDir . $fileName)) {
            if (!copy($tempFile, $uploadDir . $fileName)) {
                return null;
            }
        }

        @chmod($uploadDir . $fileName, 0644);

        return '/uploads/tickets/' . $fileName;
    } finally {
        if (is_file($tempFile)) {
            @unlink($tempFile);
        }
    }
}
