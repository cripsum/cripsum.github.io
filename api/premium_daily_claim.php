<?php
/**
 * Cripsum™ — API Riscatto Giornaliero Punti Premium
 * Consente agli utenti Premium di riscattare 500 punti (soldi) ogni giorno.
 *
 * Endpoint : POST /api/premium_daily_claim.php
 * Auth     : sessione PHP (isLoggedIn())
 * Response : JSON
 */

require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/mission_generator.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato', 'code' => 'UNAUTHENTICATED']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit();
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database non disponibile']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

// Recupera lo stato premium e l'ultimo riscatto
$stmt = $mysqli->prepare("SELECT is_premium, last_premium_claim, soldi FROM utenti WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore database']);
    exit();
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'Utente non trovato', 'code' => 'NOT_FOUND']);
    exit();
}

if ((int)($user['is_premium'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Richiede account Premium', 'code' => 'PREMIUM_REQUIRED']);
    exit();
}

$today = getMissionDailyPeriod();
if ($user['last_premium_claim'] === $today) {
    http_response_code(409);
    echo json_encode(['error' => 'Hai già riscattato i tuoi 500 punti giornalieri oggi!', 'code' => 'ALREADY_CLAIMED']);
    exit();
}

$mysqli->begin_transaction();
try {
    $stmtUpdate = $mysqli->prepare("UPDATE utenti SET soldi = soldi + 500, last_premium_claim = ? WHERE id = ? AND (last_premium_claim IS NULL OR last_premium_claim != ?)");
    if (!$stmtUpdate) {
        throw new Exception("Errore query update");
    }
    $stmtUpdate->bind_param('sis', $today, $userId, $today);
    $stmtUpdate->execute();
    
    if ($stmtUpdate->affected_rows !== 1) {
        $stmtUpdate->close();
        $mysqli->rollback();
        http_response_code(409);
        echo json_encode(['error' => 'Punti già riscattati (concorrenza)', 'code' => 'CONCURRENCY_ERROR']);
        exit();
    }
    $stmtUpdate->close();
    
    $mysqli->commit();
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno nel database: ' . $e->getMessage()]);
    exit();
}

$newSoldi = (int)$user['soldi'] + 500;
$secondsLeft = strtotime('tomorrow') - time();
echo json_encode([
    'success' => true,
    'message' => 'Hai riscattato 500 punti bonus Premium!',
    'new_soldi' => $newSoldi,
    'seconds_left' => $secondsLeft
]);
