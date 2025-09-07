<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non sei autenticato']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['post_id']) || !isset($input['action'])) {
        echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
        exit();
    }
    
    $postId = (int)$input['post_id'];
    $action = $input['action'];
    
    $checkStmt = $mysqli->prepare("SELECT id FROM toprimasti WHERE id = ? AND approvato = 1");
    $checkStmt->bind_param("i", $postId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Post non trovato']);
        exit();
    }
    $checkStmt->close();
    
    $voteCheckStmt = $mysqli->prepare("SELECT id FROM voti_toprimasti WHERE id_utente = ? AND id_post = ?");
    $voteCheckStmt->bind_param("ii", $userId, $postId);
    $voteCheckStmt->execute();
    $voteCheckResult = $voteCheckStmt->get_result();
    $hasVoted = $voteCheckResult->num_rows > 0;
    $voteCheckStmt->close();
    
    $mysqli->begin_transaction();
    
    try {
        if ($action === 'add' && !$hasVoted) {
            $insertVoteStmt = $mysqli->prepare("INSERT INTO voti_toprimasti (id_utente, id_post, data_voto) VALUES (?, ?, NOW())");
            $insertVoteStmt->bind_param("ii", $userId, $postId);
            $insertVoteStmt->execute();
            $insertVoteStmt->close();
            
            $updateStmt = $mysqli->prepare("UPDATE toprimasti SET reazioni = reazioni + 1 WHERE id = ?");
            $updateStmt->bind_param("i", $postId);
            $updateStmt->execute();
            $updateStmt->close();
            
        } elseif ($action === 'remove' && $hasVoted) {
            $deleteVoteStmt = $mysqli->prepare("DELETE FROM voti_toprimasti WHERE id_utente = ? AND id_post = ?");
            $deleteVoteStmt->bind_param("ii", $userId, $postId);
            $deleteVoteStmt->execute();
            $deleteVoteStmt->close();
            
            $updateStmt = $mysqli->prepare("UPDATE toprimasti SET reazioni = GREATEST(0, reazioni - 1) WHERE id = ?");
            $updateStmt->bind_param("i", $postId);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        $countStmt = $mysqli->prepare("SELECT COALESCE(reazioni, 0) as reazioni FROM toprimasti WHERE id = ?");
        $countStmt->bind_param("i", $postId);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $newVoteCount = $countResult->fetch_assoc()['reazioni'];
        $countStmt->close();
        
        $mysqli->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Voto aggiornato con successo',
            'newVoteCount' => (int)$newVoteCount,
            'action' => $action
        ]);
        
    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?>