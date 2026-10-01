<?php
/**
 * Ingresso comune degli endpoint social: login, ban, token e formato delle
 * risposte. Le regole (chi può fare cosa a chi) stanno in
 * includes/social_core.php.
 */
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/social_functions.php';
require_once __DIR__ . '/../../includes/stats_tracker.php';
require_once __DIR__ . '/../../includes/mission_tracker.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function get_json_input()
{
    static $input = null;
    if (is_array($input)) {
        return $input;
    }
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    return $input = is_array($decoded) ? $decoded : $_POST;
}

function send_api_error($message, $code = 'BAD_REQUEST', $httpStatus = 400)
{
    $httpStatus = (int)$httpStatus;
    http_response_code($httpStatus >= 400 && $httpStatus < 600 ? $httpStatus : 400);
    echo json_encode([
        'success' => false,
        'error' => ['code' => $code, 'message' => $message],
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function send_api_success($data = [], $message = '')
{
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/**
 * Esegue il corpo di un endpoint traducendo gli errori: quelli previsti
 * arrivano all'utente con il loro messaggio, tutto il resto finisce nel log
 * e fuori esce una frase generica (mai il testo di MySQL).
 */
function social_run(callable $body): void
{
    try {
        $body();
    } catch (SocialError $e) {
        send_api_error($e->getMessage(), $e->errorCode, $e->status);
    } catch (Throwable $e) {
        error_log('[api/social] ' . basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) . ': ' . $e->getMessage());
        send_api_error(rt_t('Qualcosa è andato storto. Riprova tra poco.', 'Something went wrong. Try again shortly.'), 'SERVER_ERROR', 500);
    }
}

function social_csrf_token(): string
{
    if (empty($_SESSION['social_csrf'])) {
        $_SESSION['social_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['social_csrf'];
}

if (!isLoggedIn()) {
    send_api_error(rt_t('Devi accedere per fare questa azione.', 'You need to sign in to do this.'), 'UNAUTHORIZED', 401);
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['ruolo'] ?? 'utente';

// Le pagine espongono due token diversi (quello del sito e quello storico
// «social»): valgono entrambi, così la card utente funziona ovunque.
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    $input = get_json_input();
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
    $okSocial = $token !== '' && !empty($_SESSION['social_csrf']) && hash_equals((string)$_SESSION['social_csrf'], $token);
    $okSite = $token !== '' && function_exists('csrf_validate') && csrf_validate($token);
    if (!$okSocial && !$okSite) {
        send_api_error(rt_t('Sessione scaduta. Ricarica la pagina.', 'Session expired. Reload the page.'), 'INVALID_CSRF', 419);
    }
}

// La sessione da qui in poi si legge soltanto: liberarla subito evita che le
// altre richieste della stessa scheda restino in coda dietro a questa.
cripsum_release_session();

// Un account bannato con una scheda ancora aperta non deve poter agire.
(static function () use ($mysqli, $userId): void {
    $stmt = $mysqli->prepare('SELECT isBannato, banned_until FROM utenti WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        send_api_error(rt_t('Sessione non valida.', 'Invalid session.'), 'UNAUTHORIZED', 401);
    }
    $expired = !empty($row['banned_until']) && strtotime((string)$row['banned_until']) <= time();
    if ((int)$row['isBannato'] === 1 && !$expired) {
        send_api_error(rt_t('Account sospeso.', 'Account suspended.'), 'BANNED', 403);
    }
})();
