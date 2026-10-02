<?php
/**
 * Cripsum™ — media dei post della community: controllo e pulizia dei file
 * caricati, miniature, risposta al browser.
 *
 * Cosa succede a un file caricato:
 *   - il tipo si legge dal contenuto, non dal nome né da quello che dichiara
 *     il browser, e deve essere uno dei sei ammessi;
 *   - dalle foto si tolgono i dati della fotocamera (posizione compresa):
 *     senza ricomprimere quando basta scartare i blocchi dei metadati, con
 *     GD quando la foto va anche raddrizzata o rimpicciolita;
 *   - GIF e immagini animate restano come sono: GD ne terrebbe un fotogramma;
 *   - i video non si toccano. La copertina la manda il browser di chi carica
 *     (un fotogramma), e qui viene ricodificata prima di essere salvata.
 */

require_once __DIR__ . '/community.php';
require_once __DIR__ . '/../content_media_cache.php';

const CM_IMAGE_BYTES = 8 * 1024 * 1024;
const CM_VIDEO_BYTES = 20 * 1024 * 1024;
const CM_POSTER_BYTES = 2 * 1024 * 1024;
/** Peso massimo di tutti i media di un post messi insieme. */
const CM_POST_BYTES = 60 * 1024 * 1024;
/** Oltre questo lato la foto viene rimpicciolita. */
const CM_IMAGE_SIDE = 2560;
/** Oltre questi pixel la foto viene rifiutata: aprirla costerebbe troppa memoria. */
const CM_IMAGE_PIXELS = 40000000;
const CM_THUMB_WIDTH = 800;
const CM_THUMB_HEIGHT = 1600;
const CM_POSTER_SIDE = 1280;

// ── Strumenti ───────────────────────────────────────────────────────────

function cm_gd(): bool
{
    return function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor');
}

/** C'è abbastanza memoria per aprire con GD un'immagine di queste misure? */
function cm_memory_for(int $width, int $height): bool
{
    $needed = (int)($width * $height * 5 * 1.7) + 24 * 1024 * 1024;

    $limit = trim((string)ini_get('memory_limit'));
    if ($limit === '' || $limit === '-1') {
        return true;
    }
    $bytes = (int)$limit * (['k' => 1024, 'm' => 1048576, 'g' => 1073741824][strtolower(substr($limit, -1))] ?? 1);

    if ($bytes < $needed && $needed <= 512 * 1024 * 1024) {
        @ini_set('memory_limit', (string)(int)ceil(($needed + 32 * 1024 * 1024) / 1048576) . 'M');
        $limit = trim((string)ini_get('memory_limit'));
        $bytes = (int)$limit * (['k' => 1024, 'm' => 1048576, 'g' => 1073741824][strtolower(substr($limit, -1))] ?? 1);
    }

    return $bytes >= $needed;
}

function cm_is_animated(string $blob, string $mime): bool
{
    if ($mime === 'image/gif') {
        // Più di un blocco «controllo grafico» = più di un fotogramma.
        return preg_match_all('/\x21\xF9\x04.{4}\x00[\x2C\x21]/s', $blob) > 1;
    }
    if ($mime === 'image/webp') {
        return strpos(substr($blob, 0, 256), 'ANIM') !== false;
    }
    if ($mime === 'image/png') {
        $idat = strpos($blob, 'IDAT');
        $actl = strpos($blob, 'acTL');
        return $actl !== false && ($idat === false || $actl < $idat);
    }
    return false;
}

/**
 * Toglie da un JPEG i blocchi dei metadati (EXIF, XMP, IPTC, commenti)
 * senza toccare l'immagine. Null se il file non è fatto come ci si aspetta.
 */
function cm_jpeg_strip(string $blob): ?string
{
    $length = strlen($blob);
    if ($length < 4 || substr($blob, 0, 2) !== "\xFF\xD8") {
        return null;
    }

    $out = "\xFF\xD8";
    $pos = 2;

    while ($pos + 4 <= $length) {
        if ($blob[$pos] !== "\xFF") {
            return null;
        }
        $marker = ord($blob[$pos + 1]);

        // Riempitivo fra due blocchi.
        if ($marker === 0xFF) {
            $pos++;
            continue;
        }
        // Da «inizio scansione» in poi c'è l'immagine: si copia com'è.
        if ($marker === 0xDA) {
            return $out . substr($blob, $pos);
        }

        $size = (ord($blob[$pos + 2]) << 8) | ord($blob[$pos + 3]);
        if ($size < 2 || $pos + 2 + $size > $length) {
            return null;
        }

        // APP1 (EXIF, XMP), APP13 (IPTC) e COM si scartano; APP0, APP2
        // (profilo colore) e APP14 servono a mostrare bene l'immagine.
        $drop = in_array($marker, [0xE1, 0xED, 0xFE], true) || ($marker >= 0xE3 && $marker <= 0xEC) || $marker === 0xEF;
        if (!$drop) {
            $out .= substr($blob, $pos, 2 + $size);
        }
        $pos += 2 + $size;
    }

    return null;
}

/** Toglie da un PNG i blocchi di testo, data e EXIF. Null se il file è irregolare. */
function cm_png_strip(string $blob): ?string
{
    $signature = "\x89PNG\r\n\x1a\n";
    if (strncmp($blob, $signature, 8) !== 0) {
        return null;
    }

    $out = $signature;
    $pos = 8;
    $length = strlen($blob);

    while ($pos + 12 <= $length) {
        $size = unpack('N', substr($blob, $pos, 4))[1];
        $name = substr($blob, $pos + 4, 4);
        if ($size > $length - $pos - 12) {
            return null;
        }
        if (!in_array($name, ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'], true)) {
            $out .= substr($blob, $pos, 12 + $size);
        }
        $pos += 12 + $size;
        if ($name === 'IEND') {
            return $out;
        }
    }

    return null;
}

/** Orientamento EXIF di un JPEG (1 = dritto). */
function cm_jpeg_orientation(string $path): int
{
    if (!function_exists('exif_read_data')) {
        return 1;
    }
    try {
        $exif = @exif_read_data($path);
        $value = (int)($exif['Orientation'] ?? 1);
        return $value >= 1 && $value <= 8 ? $value : 1;
    } catch (Throwable $e) {
        return 1;
    }
}

/** @param resource|GdImage $image */
function cm_gd_orient($image, int $orientation)
{
    $rotated = $image;
    switch ($orientation) {
        case 2: imageflip($image, IMG_FLIP_HORIZONTAL); break;
        case 3: $rotated = imagerotate($image, 180, 0); break;
        case 4: imageflip($image, IMG_FLIP_VERTICAL); break;
        case 5: $rotated = imagerotate($image, 270, 0); if ($rotated) imageflip($rotated, IMG_FLIP_HORIZONTAL); break;
        case 6: $rotated = imagerotate($image, 270, 0); break;
        case 7: $rotated = imagerotate($image, 90, 0); if ($rotated) imageflip($rotated, IMG_FLIP_HORIZONTAL); break;
        case 8: $rotated = imagerotate($image, 90, 0); break;
    }
    return $rotated ?: $image;
}

/** Rimpicciolisce dentro un riquadro, senza mai ingrandire. Tiene la trasparenza. */
function cm_gd_fit($image, int $maxWidth, int $maxHeight)
{
    $width = imagesx($image);
    $height = imagesy($image);
    $scale = min(1, $maxWidth / $width, $maxHeight / $height);
    if ($scale >= 1) {
        return $image;
    }

    $newWidth = max(1, (int)round($width * $scale));
    $newHeight = max(1, (int)round($height * $scale));
    $copy = imagecreatetruecolor($newWidth, $newHeight);
    if (!$copy) {
        return $image;
    }

    imagealphablending($copy, false);
    imagesavealpha($copy, true);
    imagefill($copy, 0, 0, imagecolorallocatealpha($copy, 0, 0, 0, 127));
    imagecopyresampled($copy, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    return $copy;
}

function cm_gd_encode($image, string $mime, int $quality): ?string
{
    ob_start();
    $ok = false;
    try {
        if ($mime === 'image/webp' && function_exists('imagewebp')) {
            if (function_exists('imagepalettetotruecolor')) {
                @imagepalettetotruecolor($image);
            }
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $ok = imagewebp($image, null, $quality);
        } elseif ($mime === 'image/png') {
            imagesavealpha($image, true);
            $ok = imagepng($image, null, 7);
        } else {
            // Il JPEG non ha trasparenza: sotto ci va il colore di fondo del sito.
            $width = imagesx($image);
            $height = imagesy($image);
            $flat = imagecreatetruecolor($width, $height);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 11, 15, 28));
            imagecopy($flat, $image, 0, 0, 0, 0, $width, $height);
            imageinterlace($flat, true);
            $ok = imagejpeg($flat, null, $quality);
        }
    } catch (Throwable $e) {
        $ok = false;
    }
    $data = ob_get_clean();

    return $ok && is_string($data) && $data !== '' ? $data : null;
}

function cm_upload_error(int $code): string
{
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return cm_t('Il file è troppo grande.', 'The file is too large.');
    }
    if ($code === UPLOAD_ERR_NO_FILE) {
        return cm_t('Manca il file.', 'The file is missing.');
    }
    return cm_t('Il caricamento non è andato a buon fine. Riprova.', 'The upload did not go through. Try again.');
}

// ── File caricati ───────────────────────────────────────────────────────

/**
 * Controlla e ripulisce il media di un post.
 *
 * @return array{blob: string, mime: string, width: ?int, height: ?int, bytes: int, kind: string}
 */
function cm_process_upload(string $field): array
{
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        cv2_fail(cm_upload_error(UPLOAD_ERR_NO_FILE));
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        cv2_fail(cm_upload_error((int)$file['error']), (int)$file['error'] === UPLOAD_ERR_NO_FILE ? 400 : 413);
    }

    $path = (string)$file['tmp_name'];
    if (!is_uploaded_file($path)) {
        cv2_fail(cm_upload_error(UPLOAD_ERR_NO_FILE));
    }

    $mime = cv2_detect_mime($path);
    if (!cv2_allowed_mime($mime)) {
        cv2_fail(cm_t('Formato non supportato. Usa JPG, PNG, GIF, WEBP, MP4 o WEBM.', 'Unsupported format. Use JPG, PNG, GIF, WEBP, MP4 or WEBM.'));
    }

    $bytes = (int)filesize($path);
    $isVideo = cv2_is_video($mime);
    if ($bytes <= 0 || $bytes > ($isVideo ? CM_VIDEO_BYTES : CM_IMAGE_BYTES)) {
        cv2_fail($isVideo
            ? cm_t('Video troppo grande: massimo 20 MB.', 'Video too large: 20 MB at most.')
            : cm_t('Immagine troppo grande: massimo 8 MB.', 'Image too large: 8 MB at most.'), 413);
    }

    $blob = file_get_contents($path);
    if ($blob === false || $blob === '') {
        cv2_fail(cm_upload_error(UPLOAD_ERR_NO_FILE));
    }

    if ($isVideo) {
        return ['blob' => $blob, 'mime' => $mime, 'width' => null, 'height' => null, 'bytes' => $bytes, 'kind' => 'video'];
    }

    $info = @getimagesize($path);
    if (!$info || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[1] < 1) {
        cv2_fail(cm_t('Il file non sembra un\'immagine valida.', 'The file does not look like a valid image.'));
    }

    $width = (int)$info[0];
    $height = (int)$info[1];
    if ($width * $height > CM_IMAGE_PIXELS) {
        cv2_fail(cm_t('Immagine troppo grande: riducila sotto i 40 megapixel.', 'Image too large: keep it under 40 megapixels.'), 413);
    }

    if (cm_is_animated($blob, $mime)) {
        return ['blob' => $blob, 'mime' => $mime, 'width' => $width, 'height' => $height, 'bytes' => $bytes, 'kind' => $mime === 'image/gif' ? 'gif' : 'image'];
    }

    $orientation = $mime === 'image/jpeg' ? cm_jpeg_orientation($path) : 1;
    $tooBig = max($width, $height) > CM_IMAGE_SIDE;
    $needsGd = $tooBig || $orientation !== 1 || ($mime === 'image/webp' && strpos($blob, 'EXIF') !== false);

    if ($needsGd && cm_gd() && cm_memory_for($width, $height)) {
        $image = @imagecreatefromstring($blob);
        if ($image) {
            $image = cm_gd_orient($image, $orientation);
            $image = cm_gd_fit($image, CM_IMAGE_SIDE, CM_IMAGE_SIDE);
            $encoded = cm_gd_encode($image, $mime === 'image/gif' ? 'image/png' : $mime, $mime === 'image/webp' ? 88 : 86);
            if ($encoded !== null) {
                $newWidth = imagesx($image);
                $newHeight = imagesy($image);
                imagedestroy($image);
                return [
                    'blob' => $encoded,
                    'mime' => $mime === 'image/gif' ? 'image/png' : $mime,
                    'width' => $newWidth,
                    'height' => $newHeight,
                    'bytes' => strlen($encoded),
                    'kind' => 'image',
                ];
            }
            imagedestroy($image);
        }
    }

    // Nessuna ricompressione: si scartano solo i blocchi dei metadati.
    $clean = null;
    if ($mime === 'image/jpeg') {
        $clean = cm_jpeg_strip($blob);
    } elseif ($mime === 'image/png') {
        $clean = cm_png_strip($blob);
    }
    if ($clean !== null && @getimagesizefromstring($clean)) {
        $blob = $clean;
    }

    return ['blob' => $blob, 'mime' => $mime, 'width' => $width, 'height' => $height, 'bytes' => strlen($blob), 'kind' => $mime === 'image/gif' ? 'gif' : 'image'];
}

/** La copertina di un video mandata dal browser, come JPEG. Null se manca o non è valida. */
function cm_process_poster(string $field): ?string
{
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
        return null;
    }

    $path = (string)$file['tmp_name'];
    $bytes = (int)filesize($path);
    $mime = cv2_detect_mime($path);
    if ($bytes <= 0 || $bytes > CM_POSTER_BYTES || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return null;
    }

    $info = @getimagesize($path);
    if (!$info || $info[0] < 16 || $info[1] < 16 || $info[0] * $info[1] > 12000000) {
        return null;
    }

    $blob = (string)file_get_contents($path);

    if (cm_gd() && cm_memory_for((int)$info[0], (int)$info[1])) {
        $image = @imagecreatefromstring($blob);
        if ($image) {
            $image = cm_gd_fit($image, CM_POSTER_SIDE, CM_POSTER_SIDE);
            $encoded = cm_gd_encode($image, 'image/jpeg', 80);
            imagedestroy($image);
            return $encoded;
        }
        return null;
    }

    // Senza GD si tiene solo un JPEG piccolo, ripulito dai metadati.
    if ($mime !== 'image/jpeg' || $bytes > 600 * 1024) {
        return null;
    }
    return cm_jpeg_strip($blob);
}

/**
 * Miniatura per il feed. Null quando conviene mandare l'originale:
 * immagine già piccola, GD assente, formato che GD non apre.
 *
 * @return array{blob: string, mime: string}|null
 */
function cm_thumb(string $blob, string $mime): ?array
{
    if (!cm_gd() || strncmp($mime, 'image/', 6) !== 0) {
        return null;
    }

    $info = @getimagesizefromstring($blob);
    if (!$info || $info[0] < 1 || $info[1] < 1) {
        return null;
    }

    $animated = cm_is_animated($blob, $mime);
    if (!$animated && $info[0] <= CM_THUMB_WIDTH && strlen($blob) <= 160 * 1024) {
        return null;
    }
    if ($animated && $mime === 'image/webp') {
        return null;
    }
    if (!cm_memory_for((int)$info[0], (int)$info[1])) {
        return null;
    }

    $image = @imagecreatefromstring($blob);
    if (!$image) {
        return null;
    }

    $image = cm_gd_fit($image, CM_THUMB_WIDTH, CM_THUMB_HEIGHT);
    $format = function_exists('imagewebp') ? 'image/webp' : 'image/jpeg';
    $encoded = cm_gd_encode($image, $format, 78);
    imagedestroy($image);

    return $encoded !== null ? ['blob' => $encoded, 'mime' => $format] : null;
}

// ── Bozze: file caricati e non ancora pubblicati ────────────────────────

/** Salva un file appena caricato come bozza dell'utente e ne restituisce l'id. */
function cm_draft_create(mysqli $mysqli, int $userId, array $media, ?string $poster, ?int $duration): int
{
    // Le bozze dimenticate (finestra chiusa a metà) spariscono dopo due ore.
    $mysqli->query('DELETE FROM content_media WHERE content_type IS NULL AND post_id IS NULL AND created_at < NOW() - INTERVAL 2 HOUR');

    // Tetto ai file in sospeso di un utente: per numero e per peso.
    $pending = cm_count($mysqli, 'SELECT COUNT(*) FROM content_media WHERE user_id = ? AND post_id IS NULL', 'i', [$userId]);
    $weight = cm_count($mysqli, 'SELECT COALESCE(SUM(bytes), 0) FROM content_media WHERE user_id = ? AND post_id IS NULL', 'i', [$userId]);
    if ($pending >= 14 || $weight + (int)$media['bytes'] > CM_POST_BYTES * 2) {
        cv2_fail(cm_t('Hai troppi file in sospeso. Pubblica o chiudi la finestra e riprova.', 'You have too many pending files. Post or close the window and try again.'), 429);
    }

    $stmt = $mysqli->prepare('INSERT INTO content_media (user_id, mime, larghezza, altezza, durata, bytes, dati, anteprima, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    if (!$stmt) {
        throw new RuntimeException('bozza media: ' . $mysqli->error);
    }

    $none = null;
    $stmt->bind_param('isiiiibb', $userId, $media['mime'], $media['width'], $media['height'], $duration, $media['bytes'], $none, $none);
    cm_send_blob($stmt, 6, $media['blob']);
    if ($poster !== null) {
        cm_send_blob($stmt, 7, $poster);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('bozza media: ' . $error);
    }
    $id = (int)$stmt->insert_id;
    $stmt->close();

    return $id;
}

/** Un blob va mandato a pezzi: tutto insieme supererebbe il pacchetto massimo di MySQL. */
function cm_send_blob(mysqli_stmt $stmt, int $index, string $blob): void
{
    $chunk = 512 * 1024;
    for ($offset = 0, $length = strlen($blob); $offset < $length; $offset += $chunk) {
        $stmt->send_long_data($index, substr($blob, $offset, $chunk));
    }
}

function cm_draft_delete(mysqli $mysqli, int $userId, int $draftId): bool
{
    $stmt = $mysqli->prepare('DELETE FROM content_media WHERE id = ? AND user_id = ? AND post_id IS NULL');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $draftId, $userId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();
    return $deleted;
}

/**
 * Le bozze scelte per un post, nell'ordine dato. Falliscono se non sono
 * dell'utente o sono già state usate.
 *
 * @return array<int, array{id: int, mime: string}>
 */
function cm_drafts_for_post(mysqli $mysqli, int $userId, array $ids, int $max): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
    if (!$ids) {
        cv2_fail(cm_t('Aggiungi almeno un\'immagine o un video.', 'Add at least one image or video.'));
    }
    if (count($ids) > $max) {
        cv2_fail(cm_t('Puoi caricare al massimo ' . $max . ' file per post.', 'You can upload up to ' . $max . ' files per post.'));
    }

    $list = implode(',', $ids);
    $found = [];
    $total = 0;
    $result = $mysqli->query("SELECT id, mime, bytes FROM content_media WHERE id IN ($list) AND user_id = $userId AND post_id IS NULL");
    while ($result && ($row = $result->fetch_assoc())) {
        $found[(int)$row['id']] = ['id' => (int)$row['id'], 'mime' => (string)$row['mime']];
        $total += (int)$row['bytes'];
    }

    if ($total > CM_POST_BYTES) {
        cv2_fail(cm_t('Il post è troppo pesante: al massimo 60 MB in tutto.', 'The post is too heavy: 60 MB in total at most.'), 413);
    }

    $ordered = [];
    foreach ($ids as $id) {
        if (!isset($found[$id])) {
            cv2_fail(cm_t('Uno dei file caricati è scaduto: ricaricalo.', 'One of the uploaded files expired: upload it again.'), 409);
        }
        $ordered[] = $found[$id];
    }

    return $ordered;
}

/** Aggancia le bozze al post: la prima diventa il media di sempre, le altre restano nella loro tabella. */
function cm_drafts_attach(mysqli $mysqli, string $type, int $postId, int $userId, array $drafts): void
{
    $meta = cv2_meta($type);
    $schema = cm_schema($mysqli, $type);
    $first = array_shift($drafts);

    $sets = ["p.`{$meta['blob']}` = m.dati", "p.`{$meta['mime']}` = m.mime"];
    if ($schema['dims']) {
        $sets[] = 'p.media_w = m.larghezza';
        $sets[] = 'p.media_h = m.altezza';
        $sets[] = 'p.media_durata = m.durata';
    }
    if ($schema['poster']) {
        $sets[] = 'p.anteprima = m.anteprima';
    }
    $sets[] = 'p.media_extra = ' . count($drafts);

    $stmt = $mysqli->prepare("
        UPDATE `{$meta['table']}` p
        INNER JOIN content_media m ON m.id = ? AND m.user_id = ? AND m.post_id IS NULL
        SET " . implode(', ', $sets) . "
        WHERE p.id = ?
    ");
    if (!$stmt) {
        throw new RuntimeException('aggancio media: ' . $mysqli->error);
    }
    $stmt->bind_param('iii', $first['id'], $userId, $postId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('aggancio media: ' . $error);
    }
    $stmt->close();

    $stmt = $mysqli->prepare('DELETE FROM content_media WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $first['id'], $userId);
    $stmt->execute();
    $stmt->close();

    $position = 1;
    $stmt = $mysqli->prepare('UPDATE content_media SET content_type = ?, post_id = ?, posizione = ? WHERE id = ? AND user_id = ? AND post_id IS NULL');
    foreach ($drafts as $draft) {
        $stmt->bind_param('siiii', $type, $postId, $position, $draft['id'], $userId);
        $stmt->execute();
        $position++;
    }
    $stmt->close();
}

// ── Risposta al browser ─────────────────────────────────────────────────

/**
 * Manda un media di un post, o chiude con 404 / 403.
 *
 * `$n` = 0 è il primo media, da 1 in su gli altri. `$variant`: '' per il
 * file intero, 'thumb' per la miniatura del feed, 'poster' per la
 * copertina di un video.
 */
function cm_media_respond(mysqli $mysqli, string $type, int $postId, int $n, string $variant, ?array $user): void
{
    $meta = cv2_meta($type);
    $schema = cm_schema($mysqli, $type);
    $n = max(0, $n);
    $variant = in_array($variant, ['thumb', 'poster'], true) ? $variant : '';

    if ($postId <= 0 || !$schema['ready'] || ($n > 0 && !$schema['extra'])) {
        http_response_code(404);
        exit;
    }

    $table = '`' . $meta['table'] . '`';

    // Prima il poco: stato, autore, tipo. Il file si legge solo se serve.
    if ($n === 0) {
        $stmt = $mysqli->prepare("
            SELECT p.approvato, p.id_utente, p.`{$meta['mime']}` AS mime,
                   " . ($schema['poster'] ? '(p.anteprima IS NOT NULL)' : '0') . " AS has_poster,
                   " . ($schema['dims'] ? 'p.media_w' : 'NULL') . " AS width
            FROM $table p WHERE p.id = ? AND p.`{$meta['blob']}` IS NOT NULL LIMIT 1
        ");
        $stmt->bind_param('i', $postId);
    } else {
        $stmt = $mysqli->prepare("
            SELECT p.approvato, p.id_utente, m.mime, (m.anteprima IS NOT NULL) AS has_poster, m.larghezza AS width, m.id AS media_id
            FROM content_media m
            INNER JOIN $table p ON p.id = m.post_id
            WHERE m.content_type = ? AND m.post_id = ? AND m.posizione = ? LIMIT 1
        ");
        $stmt->bind_param('sii', $type, $postId, $n);
    }

    $stmt->execute();
    $head = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$head) {
        // Il post non c'è più: via anche le sue copie su file. Una posizione
        // che non esiste, invece, non deve svuotare la copia degli altri media.
        if ($n === 0) {
            content_media_cache_forget($type, $postId);
        }
        http_response_code(404);
        exit;
    }

    $approved = (int)$head['approvato'] === 1;
    if (!$approved && !($user && (cv2_is_admin($user) || (int)$head['id_utente'] === (int)$user['id']))) {
        http_response_code(403);
        exit;
    }

    $mime = (string)$head['mime'];
    if (!isset(CONTENT_MEDIA_CACHE_EXT[$mime])) {
        http_response_code(404);
        exit;
    }

    $isVideo = strncmp($mime, 'video/', 6) === 0;
    // La «miniatura» di un video è la sua copertina.
    $wantPoster = $variant === 'poster' || ($variant === 'thumb' && $isVideo);

    if ($wantPoster) {
        if ((int)$head['has_poster'] !== 1) {
            http_response_code(404);
            exit;
        }
        $sql = $n === 0
            ? "SELECT anteprima FROM $table WHERE id = $postId"
            : 'SELECT anteprima FROM content_media WHERE id = ' . (int)$head['media_id'];
        $blob = (string)($mysqli->query($sql)->fetch_row()[0] ?? '');
        $outMime = 'image/jpeg';
    } else {
        $sql = $n === 0
            ? "SELECT `{$meta['blob']}` FROM $table WHERE id = $postId"
            : 'SELECT dati FROM content_media WHERE id = ' . (int)$head['media_id'];
        $blob = (string)($mysqli->query($sql)->fetch_row()[0] ?? '');
        $outMime = $mime;

        // I post di prima dell'aggiornamento non hanno le misure: si
        // segnano la prima volta che l'immagine passa di qui.
        if ($n === 0 && !$isVideo && $schema['dims'] && $head['width'] === null && $blob !== '') {
            $info = @getimagesizefromstring($blob);
            if ($info && $info[0] > 0 && $info[1] > 0) {
                $mysqli->query("UPDATE $table SET media_w = " . min(65535, (int)$info[0]) . ', media_h = ' . min(65535, (int)$info[1]) . " WHERE id = $postId");
            }
        }

        if ($variant === 'thumb') {
            $thumb = cm_thumb($blob, $mime);
            if ($thumb !== null) {
                $blob = $thumb['blob'];
                $outMime = $thumb['mime'];
            }
        }
    }

    if ($blob === '') {
        http_response_code(404);
        exit;
    }

    // La copia su file serve solo i post online: quella strada non sa chi chiede.
    if ($approved) {
        content_media_cache_store($type, $postId, $outMime, $blob, $n, $variant);
    }

    content_media_send($outMime, null, $blob);
}
