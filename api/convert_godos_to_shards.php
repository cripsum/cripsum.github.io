<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Utente non autenticato.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Ricevi parametri POST
$input = json_decode(file_get_contents('php://input'), true);
$shardsToBuy = isset($input['shards']) ? (int)$input['shards'] : 0;

if ($shardsToBuy <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Quantità di shards non valida.']);
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

    echo json_encode([
        'status' => 'success',
        'message' => 'Conversione completata con successo!',
        'soldi_rimasti' => $nuoviSoldi,
        'shards_rimaste' => $nuoveShards,
        'shards_acquistate' => $shardsToBuy,
        'costo_punti' => $costoGodos
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
