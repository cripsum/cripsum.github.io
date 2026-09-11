<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/redeem_codes.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metodo non consentito.']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Non autenticato.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

$body   = json_decode(file_get_contents('php://input'), true);
$body   = is_array($body) ? $body : [];
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['csrf_token'] ?? null);
if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
    http_response_code(419);
    echo json_encode(['status' => 'error', 'message' => 'Sessione scaduta. Ricarica la pagina.']);
    exit;
}
$codice = strtolower(trim($body['codice'] ?? ''));
$lang   = ($body['lang'] ?? 'it') === 'en' ? 'en' : 'it';

if ($codice === '') {
    echo json_encode(['status' => 'error', 'message' => $lang === 'en' ? 'Missing code.' : 'Codice mancante.']);
    exit;
}

// La logica di riscatto vive in includes/redeem_codes.php ed e' condivisa con
// l'endpoint del bot Discord (api/bot/redeem_code.php).
echo json_encode(cripsum_redeem_code_apply($mysqli, $userId, $codice, $lang));
exit;
