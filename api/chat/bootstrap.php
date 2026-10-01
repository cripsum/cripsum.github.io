<?php
/**
 * Ingresso comune degli endpoint delle chat (private, gruppi e globale):
 * login, ban, token, formato delle risposte.
 *
 * Le regole stanno in includes/chat_core.php (chat private),
 * includes/chat_groups.php (gruppi) e includes/chat_global.php (globale).
 */
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/chat_config.php';
require_once __DIR__ . '/../../config/klipy_config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/chat_v2_helpers.php';
require_once __DIR__ . '/../../includes/social_functions.php';
require_once __DIR__ . '/../../includes/chat_groups.php';
require_once __DIR__ . '/../../includes/stats_tracker.php';
require_once __DIR__ . '/../../includes/mission_tracker.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

/** Corpo della richiesta: JSON oppure un normale form. */
function get_json_input()
{
    static $input = null;
    if (is_array($input)) {
        return $input;
    }
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    return $input = is_array($decoded) ? $decoded : $_POST;
}

function send_error($message, $code = 400)
{
    $code = (int)$code;
    http_response_code($code >= 400 && $code < 600 ? $code : 400);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit();
}

function send_success($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

/**
 * Esegue il corpo di un endpoint. Gli errori previsti arrivano all'utente
 * col loro messaggio; tutto il resto va nel log e fuori esce una frase
 * generica, mai il testo di MySQL.
 */
function chat_run(callable $body): void
{
    try {
        $body();
    } catch (ChatError $e) {
        send_error($e->getMessage(), $e->status);
    } catch (SocialError $e) {
        send_error($e->getMessage(), $e->status);
    } catch (Throwable $e) {
        error_log('[api/chat] ' . basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        send_error(rt_t('Qualcosa è andato storto. Riprova tra poco.', 'Something went wrong. Try again shortly.'), 500);
    }
}

/** Storico: vero se fra i due utenti c'è un blocco, in un verso o nell'altro. */
function is_blocked_with($mysqli, $userId, $otherUserId)
{
    return sc_is_blocked($mysqli, (int)$userId, (int)$otherUserId);
}

if (!isLoggedIn()) {
    send_error(rt_t('Devi accedere.', 'You need to sign in.'), 401);
}

$userId = (int)$_SESSION['user_id'];

// Ogni richiesta che cambia qualcosa porta il token (quello del sito o
// quello storico della chat): controllarlo qui copre tutti gli endpoint.
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    chat_verify_csrf(get_json_input());
}

// Da qui la sessione si legge soltanto: liberarla evita che invio, lettura
// e caricamento di un file dello stesso utente si mettano in coda.
cripsum_release_session();

// Una query sola per sapere chi è e se può stare qui: un account bannato con
// una scheda ancora aperta non deve poter scrivere né leggere.
$chatUser = (static function () use ($mysqli, $userId): array {
    $timeout = rt_has_col($mysqli, 'utenti', 'chat_timeout_until') ? ', chat_timeout_until' : '';
    $stmt = $mysqli->prepare("SELECT id, username, display_name, ruolo, is_premium, isBannato, banned_until, discord_id$timeout FROM utenti WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        send_error(rt_t('Sessione non valida.', 'Invalid session.'), 401);
    }
    $expired = !empty($row['banned_until']) && strtotime((string)$row['banned_until']) <= time();
    if ((int)$row['isBannato'] === 1 && !$expired) {
        send_error(rt_t('Account sospeso.', 'Account suspended.'), 403);
    }
    $row['id'] = (int)$row['id'];
    $row['is_premium'] = (int)$row['is_premium'] === 1;
    $row['is_mod'] = in_array($row['ruolo'], ['admin', 'owner'], true);
    return $row;
})();

$userRole = (string)$chatUser['ruolo'];
