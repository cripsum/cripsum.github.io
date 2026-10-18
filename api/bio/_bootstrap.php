<?php
declare(strict_types=1);

/**
 * Ingresso comune delle API di bio.cripsum.com (api/bio/*.php): risposte JSON,
 * accesso, ban e token CSRF. Le chiamano assets/bio/bio.js e le pagine di
 * bio/; vanno anche su cripsum.com, ma lì nessuna pagina le usa.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/bio_pages.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$bioLang = bio_lang();

function bio_api_ok(array $data = []): never
{
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bio_api_fail(string $key, int $status = 400): never
{
    // bio_lang() e non la globale $bioLang: questa funzione va anche quando il
    // file e' incluso dentro una funzione, dove la globale non c'e'.
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => bio_t($key, bio_lang())], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Il corpo della richiesta: JSON o form. */
function bio_api_input(): array
{
    static $input = null;
    if ($input === null) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        $input = is_array($decoded) ? $decoded : $_POST;
    }

    return $input;
}

/** Chi e' dentro, non bannato. Restituisce l'id. */
function bio_api_require_user(mysqli $mysqli): int
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        bio_api_fail('err_login', 401);
    }

    $stmt = $mysqli->prepare('SELECT isBannato, banned_until FROM utenti WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $banEnded = !empty($row['banned_until']) && strtotime((string)$row['banned_until']) <= time();
    if (!$row || ((int)$row['isBannato'] === 1 && !$banEnded)) {
        bio_api_fail('err_login', 403);
    }

    return $userId;
}

/** POST col token del sito (header X-CSRF-Token o campo csrf_token). */
function bio_api_require_post_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        bio_api_fail('err_generic', 405);
    }
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? (bio_api_input()['csrf_token'] ?? ''));
    if (!csrf_validate($token)) {
        bio_api_fail('err_csrf', 419);
    }
}
