<?php
/**
 * Ingresso «leggero» per gli endpoint del tempo reale: sessione sì,
 * database no.
 *
 * config/database.php apre la connessione MySQL nel momento stesso in cui
 * viene incluso, e l'account ne regge venti in tutto. Gli endpoint che
 * rispondono migliaia di volte al minuto (c'è qualcosa di nuovo? sto
 * scrivendo, sono ancora qui) includono questo file al suo posto e leggono
 * solo i timbri di includes/realtime.php.
 *
 * Chi ha bisogno del database lo include dopo, solo nel ramo in cui serve.
 */
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../includes/realtime.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function rt_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

/** Chi sta chiamando, da quello che la sessione già sa. */
function rt_light_user(): array
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        rt_json(['ok' => false, 'error' => 'auth'], 401);
    }
    return [
        'id' => $userId,
        'username' => (string)($_SESSION['username'] ?? ''),
        'ruolo' => (string)($_SESSION['ruolo'] ?? 'utente'),
    ];
}

/** Corpo JSON della richiesta (o il form). */
function rt_light_input(): array
{
    static $input = null;
    if ($input === null) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        $input = is_array($decoded) ? $decoded : $_POST;
    }
    return $input;
}

/**
 * Token anti-CSRF senza passare da security_helpers: vale uno qualsiasi dei
 * tre che le pagine del sito espongono (sito, chat, social).
 */
function rt_light_csrf(): void
{
    $input = rt_light_input();
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ($input['csrf'] ?? '')));
    if ($token !== '') {
        foreach (['csrf_token', 'chat_csrf', 'social_csrf'] as $key) {
            if (!empty($_SESSION[$key]) && hash_equals((string)$_SESSION[$key], $token)) {
                return;
            }
        }
    }
    rt_json(['ok' => false, 'error' => rt_t('Sessione scaduta. Ricarica la pagina.', 'Session expired. Reload the page.')], 419);
}
