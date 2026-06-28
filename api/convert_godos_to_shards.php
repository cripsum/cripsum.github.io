<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
$isJsonRequest = strpos($contentType, 'application/json') !== false || strpos($accept, 'application/json') !== false;

$defaultReturn = (isset($_SESSION['lang']) && $_SESSION['lang'] === 'en') ? '/en/shop.php' : '/it/shop.php';
$requestedReturn = (string)($_POST['return_to'] ?? $defaultReturn);
$allowedReturns = ['/it/shop.php', '/en/shop.php'];
$returnTo = in_array($requestedReturn, $allowedReturns, true) ? $requestedReturn : $defaultReturn;
$lang = strpos($returnTo, '/en/') === 0 ? 'en' : 'it';

if ($isJsonRequest) {
    header('Content-Type: application/json; charset=utf-8');
}

if (!isset($_SESSION['user_id'])) {
    if ($isJsonRequest) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => $lang === 'en' ? 'Authentication required.' : 'Utente non autenticato.']);
    } else {
        header('Location: /' . $lang . '/accedi');
    }
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Supporta sia il controller JavaScript (JSON) sia il form HTML di fallback.
$input = $isJsonRequest ? json_decode(file_get_contents('php://input'), true) : $_POST;
$input = is_array($input) ? $input : [];
$shardsToBuy = isset($input['shards']) ? (int)$input['shards'] : 0;

if ($shardsToBuy <= 0) {
    if ($isJsonRequest) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $lang === 'en' ? 'Invalid Shard quantity.' : 'Quantità di shards non valida.']);
    } else {
        header('Location: ' . $returnTo . '?conversion=error');
    }
    exit;
}

$costoGodos = $shardsToBuy * 100;

try {
    $mysqli->begin_transaction();

    // Lock riga utente per consistenza concorrenza
    $stmtUser = $mysqli->prepare('SELECT soldi, godoshards_balance FROM utenti WHERE id = ? FOR UPDATE');
    if (!$stmtUser) throw new Exception('Prepare select user fallito.');
    $stmtUser->bind_param('i', $userId);
    $stmtUser->execute();
    $user = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    if (!$user) {
        throw new Exception('Utente non trovato.');
    }

    $currentSoldi = (int)$user['soldi'];
    $currentShards = (int)$user['godoshards_balance'];

    if ($currentSoldi < $costoGodos) {
        throw new Exception('Godos (punti) insufficienti per effettuare questa conversione.');
    }

    // Esegui conversione
    $nuoviSoldi = $currentSoldi - $costoGodos;
    $nuoveShards = $currentShards + $shardsToBuy;

    $stmtUpdate = $mysqli->prepare('UPDATE utenti SET soldi = ?, godoshards_balance = ? WHERE id = ?');
    if (!$stmtUpdate) throw new Exception('Prepare update balance fallito.');
    $stmtUpdate->bind_param('iii', $nuoviSoldi, $nuoveShards, $userId);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    $mysqli->commit();

    $payload = [
        'status' => 'success',
        'message' => $lang === 'en' ? 'Conversion completed successfully!' : 'Conversione completata con successo!',
        'soldi_rimasti' => $nuoviSoldi,
        'shards_rimaste' => $nuoveShards,
        'shards_acquistate' => $shardsToBuy,
        'costo_punti' => $costoGodos
    ];

    if ($isJsonRequest) {
        echo json_encode($payload);
    } else {
        header('Location: ' . $returnTo . '?conversion=success&shards=' . $shardsToBuy);
    }

} catch (Exception $e) {
    $mysqli->rollback();
    if ($isJsonRequest) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } else {
        header('Location: ' . $returnTo . '?conversion=error');
    }
}
