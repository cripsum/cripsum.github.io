<?php
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/social_functions.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Controllo Autenticazione
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'UNAUTHORIZED',
            'message' => 'Devi effettuare l\'accesso per eseguire questa azione.'
        ]
    ]);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['ruolo'] ?? 'utente';

// 2. Helper CSRF Token
function social_csrf_token(): string
{
    if (empty($_SESSION['social_csrf'])) {
        $_SESSION['social_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['social_csrf'];
}

function social_check_csrf(): void
{
    if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'], true)) {
        $input = get_json_input();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
        if (!is_string($token) || $token === '' || !hash_equals($_SESSION['social_csrf'] ?? '', $token)) {
            http_response_code(419);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CSRF',
                    'message' => 'Sessione non valida o scaduta. Per favore ricarica la pagina.'
                ]
            ]);
            exit();
        }
    }
}

// Eseguiamo il controllo CSRF su tutte le chiamate modificatrici
social_check_csrf();

// 3. Utility di Input/Output
function get_json_input() {
    return json_decode(file_get_contents('php://input'), true) ?? $_POST;
}

function send_api_error($message, $code = 'BAD_REQUEST', $httpStatus = 400) {
    http_response_code($httpStatus);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message
        ]
    ]);
    exit();
}

function send_api_success($data = [], $message = 'Operazione completata con successo.') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}
?>
