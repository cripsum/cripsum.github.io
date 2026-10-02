<?php
/**
 * Il browser chiede un achievement.
 *
 * Chi decide è includes/achievements.php (ach_client_claim): quelli che conta
 * il server si ricontrollano sul momento, quelli che solo il browser può
 * vedere (gambling, le 3 di notte) passano se la richiesta è plausibile e con
 * un limite al minuto, tutti gli altri si rifiutano. Da qui non esce mai
 * valuta: il premio si riscuote a parte, da api/achievements/claim.php.
 *
 * Endpoint : POST /api/set_achievement.php
 * Auth     : sessione PHP + token CSRF (header X-CSRF-Token o campo csrf_token)
 * Body     : achievement_id, lang (facoltativo: it | en)
 */
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/achievements.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metodo non consentito.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Devi essere loggato.']);
    exit;
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
    http_response_code(419);
    echo json_encode(['status' => 'error', 'message' => 'Sessione scaduta. Ricarica la pagina.']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$achievementId = (int)($_POST['achievement_id'] ?? 0);
if ($userId <= 0 || $achievementId <= 0) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Achievement non valido.']);
    exit;
}

try {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        @$mysqli->set_charset('utf8mb4');
    }

    $status = ach_client_claim($mysqli, $userId, $achievementId);

    switch ($status) {
        case 'success':
            $lang = ($_POST['lang'] ?? '') === 'en' ? 'en' : 'it';
            $entry = ach_catalog($mysqli)[$achievementId];
            echo json_encode([
                'status'       => 'success',
                'message'      => 'Achievement sbloccato.',
                'points_added' => 0,
                // Il popup si disegna con questi, senza un'altra richiesta.
                'achievement'  => [
                    'id'          => $achievementId,
                    'nome'        => ach_text($entry, 'nome', $lang),
                    'descrizione' => ach_text($entry, 'descrizione', $lang),
                    'img_url'     => ach_image_url($entry['img_url']),
                    'punti'       => $entry['punti'],
                    'livello'     => $entry['livello'],
                    'ricompensa'  => $entry['ricompensa'],
                ],
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'already_unlocked':
            echo json_encode(['status' => 'already_unlocked', 'message' => 'Achievement già sbloccato.']);
            break;

        case 'not_found':
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Achievement non trovato.']);
            break;

        case 'throttled':
            http_response_code(429);
            echo json_encode(['status' => 'error', 'message' => 'Troppe richieste. Riprova fra poco.', 'code' => 'THROTTLED']);
            break;

        default:
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Questo achievement non si sblocca da qui.', 'code' => 'NOT_ELIGIBLE']);
    }
} catch (Throwable $e) {
    error_log('set_achievement failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore interno del server.']);
}
