<?php
date_default_timezone_set('Europe/Rome');


require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/../secure/config.php';


/*
 * L'hosting concede all'utente del database una ventina di connessioni
 * contemporanee. Una pagina con tanti avatar e immagini ne chiede di piu' in
 * un colpo solo, e quelle in eccesso morivano qui con un 500 a pagina vuota
 * (new mysqli lancia un'eccezione che nessuno prendeva). Le richieste durano
 * un decimo di secondo: basta aspettare un attimo e riprovare perche' un posto
 * si liberi. Solo se dopo i tentativi il database non risponde ancora si
 * risponde 503, con un messaggio invece della pagina bianca.
 */
$mysqli = null;
$cripsumDbAttempt = 0;
while (true) {
    try {
        $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
        if (!$mysqli->connect_errno) {
            break;
        }
        $cripsumDbError = $mysqli->connect_error;
        $cripsumDbCode = (int)$mysqli->connect_errno;
    } catch (mysqli_sql_exception $e) {
        $cripsumDbError = $e->getMessage();
        $cripsumDbCode = (int)$e->getCode();
    }

    $cripsumDbAttempt++;
    // 1040 troppe connessioni, 1203/1226 limite dell'utente, 2002/2006/2013
    // server irraggiungibile o connessione caduta: tutti passeggeri.
    $cripsumDbRetry = in_array($cripsumDbCode, [1040, 1203, 1226, 2002, 2006, 2013], true);
    if (!$cripsumDbRetry || $cripsumDbAttempt >= 6) {
        error_log('[database] connessione non riuscita dopo ' . $cripsumDbAttempt . ' tentativi (' . $cripsumDbCode . '): ' . $cripsumDbError);
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 2');
            header('Cache-Control: no-store');
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'status' => 'error', 'code' => 'DB_BUSY', 'message' => 'Server occupato, riprova tra un attimo.']);
        exit;
    }

    // 0,1 s, 0,2 s, 0,3 s... con un po' di scarto, cosi' le richieste
    // respinte insieme non tornano tutte nello stesso istante.
    usleep($cripsumDbAttempt * 100000 + random_int(0, 60000));
}
unset($cripsumDbAttempt, $cripsumDbError, $cripsumDbCode, $cripsumDbRetry);

$mysqli->set_charset('utf8mb4');
$mysqli->query("SET time_zone = '" . date('P') . "'");

// Valida il token del dispositivo su ogni richiesta autenticata che usa il database.
require_once __DIR__ . '/../includes/security_helpers.php';
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
    auth_sync_current_device_session($mysqli);
}

/*
 * Manutenzione. Da qui passano tutte le pagine e tutte le API che usano il
 * database, con la sessione gia' aperta: si sa chi sta chiedendo. Se il sito
 * e' chiuso (pannello admin, Sito > Manutenzione) entra solo chi e'
 * nell'elenco; gli altri ricevono la pagina di manutenzione, o un 503 in JSON
 * se e' un'API. Le eccezioni stanno in includes/maintenance.php.
 */
require_once __DIR__ . '/../includes/maintenance.php';
cripsum_maintenance_guard($mysqli);
