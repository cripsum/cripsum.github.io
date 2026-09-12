<?php
declare(strict_types=1);

/**
 * Token CSRF corrente dell'editor del profilo.
 *
 * Serve a una cosa sola: quando la pagina dell'editor resta aperta a lungo e
 * nel frattempo la sessione viene rigenerata — un nuovo accesso in un'altra
 * scheda, un logout e rientro — il token stampato nel form diventa vecchio e
 * il salvataggio torna 403. Prima si perdeva tutto il lavoro fatto nell'editor
 * e bisognava ricaricare a mano.
 *
 * Con questo la pagina chiede il token nuovo e riprova una volta sola.
 *
 * Non indebolisce la protezione: il token lo ottiene solo chi ha gia' la
 * sessione, ed e' lo stesso che la pagina riceve quando viene stampata. Un
 * sito esterno non puo' leggere questa risposta, perche' non ci sono
 * intestazioni CORS che glielo permettano.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Devi aver effettuato l\'accesso.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'csrf_token' => profile_csrf_token(),
]);
