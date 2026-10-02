<?php
if (!defined('CONTENT_V2_LOADED')) {
    define('CONTENT_V2_LOADED', true);
}

/*
 * Mattoni di base degli endpoint di Shitpost e Top Rimasti: risposte JSON,
 * token anti-CSRF, utente collegato, nomi delle colonne delle due tabelle.
 * Le regole vere (chi vede cosa, feed, limiti, avvisi) stanno in
 * includes/community/community.php, che include questo file.
 */

function cv2_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cv2_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
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

/** Una frase nella lingua della pagina che chiama (cm_t), o in italiano se non si sa. */
function cv2_say(string $it, string $en): string
{
    return function_exists('cm_t') ? cm_t($it, $en) : $it;
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
        cv2_fail(cv2_say('Sessione scaduta. Ricarica la pagina.', 'Session expired. Reload the page.'), 419);
    }
}

function cv2_current_user(mysqli $mysqli): ?array
{
    if (!function_exists('isLoggedIn') || !isLoggedIn()) return null;

    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id <= 0) return null;

    $stmt = $mysqli->prepare("SELECT id, username, ruolo, isBannato, discord_id FROM utenti WHERE id = ? LIMIT 1");
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
    if (!$user) cv2_fail(cv2_say('Devi accedere.', 'You need to log in.'), 401);

    if ((int)($user['isBannato'] ?? 0) === 1) {
        cv2_fail(cv2_say('Account bannato.', 'Account banned.'), 403);
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

function cv2_bool_int($value): int
{
    return in_array((string)$value, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
}

function cv2_client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}
