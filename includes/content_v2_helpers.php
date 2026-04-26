<?php
if (!defined('CONTENT_V2_LOADED')) {
    define('CONTENT_V2_LOADED', true);
}

function cv2_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cv2_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cv2_ok(array $payload = []): void
{
    cv2_json(array_merge(['ok' => true], $payload));
}

function cv2_fail(string $message, int $status = 400, array $extra = []): void
{
    cv2_json(array_merge(['ok' => false, 'message' => $message], $extra), $status);
}

function cv2_input(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function cv2_csrf_token(): string
{
    if (empty($_SESSION['content_v2_csrf'])) {
        $_SESSION['content_v2_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['content_v2_csrf'];
}

function cv2_check_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['content_v2_csrf'] ?? '', $token)) {
        cv2_fail('Sessione scaduta. Ricarica la pagina.', 419);
    }
}

function cv2_table_exists(mysqli $mysqli, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return $cache[$table] = false;

    try {
        $result = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($table) . "'");
        return $cache[$table] = ($result && $result->num_rows > 0);
    } catch (Throwable $e) {
        return $cache[$table] = false;
    }
}

function cv2_column_exists(mysqli $mysqli, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[\p{L}\p{N}_]+$/u', $column)) {
        return $cache[$key] = false;
    }

    try {
        $result = $mysqli->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        if (!$result) return $cache[$key] = false;

        while ($row = $result->fetch_assoc()) {
            if (($row['Field'] ?? '') === $column) {
                return $cache[$key] = true;
            }
        }
        return $cache[$key] = false;
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

function cv2_current_user(mysqli $mysqli): ?array
{
    if (!function_exists('isLoggedIn') || !isLoggedIn()) return null;

    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id <= 0) return null;

    $stmt = $mysqli->prepare("SELECT id, username, ruolo, isBannato FROM utenti WHERE id = ? LIMIT 1");
    if (!$stmt) return null;

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function cv2_is_admin(?array $user): bool
{
    return isset($user['ruolo']) && in_array($user['ruolo'], ['admin', 'owner'], true);
}

function cv2_require_login(mysqli $mysqli): array
{
    $user = cv2_current_user($mysqli);
    if (!$user) cv2_fail('Devi essere loggato.', 401);

    if ((int)($user['isBannato'] ?? 0) === 1) {
        cv2_fail('Account bannato.', 403);
    }

    return $user;
}

function cv2_normalize_type(string $type): string
{
    return $type === 'rimasto' || $type === 'toprimasti' || $type === 'rimasti' ? 'rimasto' : 'shitpost';
}

function cv2_meta(string $type): array
{
    $type = cv2_normalize_type($type);

    if ($type === 'rimasto') {
        return [
            'type' => 'rimasto',
            'table' => 'toprimasti',
            'id' => 'id',
            'user' => 'id_utente',
            'title' => 'titolo',
            'description' => 'descrizione',
            'extra' => 'motivazione',
            'blob' => 'foto_rimasto',
            'mime' => 'tipo_foto_rimasto',
            'created' => 'data_creazione',
            'approved' => 'approvato',
            'score' => 'reazioni',
            'media_endpoint' => '/api/content/media.php?type=rimasto&id=',
            'label' => 'Top Rimasti',
        ];
    }

    return [
        'type' => 'shitpost',
        'table' => 'shitposts',
        'id' => 'id',
        'user' => 'id_utente',
        'title' => 'titolo',
        'description' => 'descrizione',
        'extra' => null,
        'blob' => 'foto_shitpost',
        'mime' => 'tipo_foto_shitpost',
        'created' => 'data_creazione',
        'approved' => 'approvato',
        'score' => null,
        'media_endpoint' => '/api/content/media.php?type=shitpost&id=',
        'label' => 'Shitpost',
    ];
}

function cv2_qcol(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function cv2_rate_limit(string $key, int $seconds, string $message): void
{
    $now = time();
    $last = (int)($_SESSION[$key] ?? 0);
    if ($last > 0 && ($now - $last) < $seconds) {
        cv2_fail($message . ' Riprova tra ' . ($seconds - ($now - $last)) . 's.', 429);
    }
    $_SESSION[$key] = $now;
}

function cv2_validate_title(string $title): string
{
    $title = trim($title);
    if ($title === '' || mb_strlen($title, 'UTF-8') > 120) {
        cv2_fail('Titolo non valido. Max 120 caratteri.');
    }
    return $title;
}

function cv2_validate_text(string $text, int $max, string $field): string
{
    $text = trim($text);
    if (mb_strlen($text, 'UTF-8') > $max) {
        cv2_fail($field . ' troppo lungo. Max ' . $max . ' caratteri.');
    }
    return $text;
}

function cv2_validate_tag(?string $tag): ?string
{
    $tag = trim((string)$tag);
    if ($tag === '') return null;
    $tag = mb_substr($tag, 0, 40, 'UTF-8');
    return preg_replace('/[^a-zA-Z0-9_\-àèéìòù ]/u', '', $tag) ?: null;
}

function cv2_allowed_mime(string $mime): bool
{
    return in_array($mime, [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/webm',
    ], true);
}

function cv2_is_video(?string $mime): bool
{
    return is_string($mime) && str_starts_with($mime, 'video/');
}

function cv2_detect_mime(string $tmpPath): string
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmpPath) : '';
    if ($finfo) finfo_close($finfo);
    return (string)$mime;
}

function cv2_upload_file(string $field): array
{
    if (empty($_FILES[$field]) || !isset($_FILES[$field]['error']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        cv2_fail('File mancante o non valido.');
    }

    $tmp = $_FILES[$field]['tmp_name'];
    $size = (int)($_FILES[$field]['size'] ?? 0);
    $mime = cv2_detect_mime($tmp);

    if (!cv2_allowed_mime($mime)) {
        cv2_fail('Formato non supportato. Usa JPG, PNG, GIF, WEBP, MP4 o WEBM.');
    }

    $max = cv2_is_video($mime) ? 20 * 1024 * 1024 : 8 * 1024 * 1024;
    if ($size <= 0 || $size > $max) {
        cv2_fail(cv2_is_video($mime) ? 'Video troppo grande. Max 20MB.' : 'Immagine/GIF troppo grande. Max 8MB.');
    }

    $blob = file_get_contents($tmp);
    if ($blob === false || $blob === '') {
        cv2_fail('Non sono riuscito a leggere il file.');
    }

    return ['blob' => $blob, 'mime' => $mime, 'size' => $size];
}

function cv2_post_owner(mysqli $mysqli, array $meta, int $postId): ?int
{
    $table = cv2_qcol($meta['table']);
    $userCol = cv2_qcol($meta['user']);

    $stmt = $mysqli->prepare("SELECT $userCol AS user_id FROM $table WHERE id = ? LIMIT 1");
    if (!$stmt) return null;

    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int)$row['user_id'] : null;
}

function cv2_can_manage_post(mysqli $mysqli, array $user, array $meta, int $postId): bool
{
    if (cv2_is_admin($user)) return true;

    $owner = cv2_post_owner($mysqli, $meta, $postId);
    return $owner !== null && $owner === (int)$user['id'];
}

function cv2_bool_int($value): int
{
    return in_array((string)$value, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
}

function cv2_client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function cv2_post_url(string $type, int $id): string
{
    $base = $type === 'rimasto' ? '/it/rimasti' : '/it/shitpost';
    return $base . '?post=' . $id;
}
