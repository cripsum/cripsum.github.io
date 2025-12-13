<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Devi essere loggato']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['comment_id'])) {
    echo json_encode(['success' => false, 'error' => 'ID commento mancante']);
    exit();
}

$comment_id = intval($input['comment_id']);
$user_id = $_SESSION['user_id'];

try {
    $stmt = $mysqli->prepare("
        SELECT c.id_utente, u.ruolo
        FROM commenti_shitpost c
        JOIN utenti u ON u.id = ?
        WHERE c.id = ?
    ");

    $stmt->bind_param("ii", $user_id, $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Commento non trovato']);
        exit();
    }

    $data = $result->fetch_assoc();
    $stmt->close();

    $checkStmt = $mysqli->prepare("SELECT id_utente FROM commenti_shitpost WHERE id = ?");
    $checkStmt->bind_param("i", $comment_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $comment = $checkResult->fetch_assoc();
    $checkStmt->close();

    $isOwner = ($comment['id_utente'] == $user_id);
    $isAdminOrOwner = in_array($data['ruolo'], ['admin', 'owner']);

    if (!$isOwner && !$isAdminOrOwner) {
        echo json_encode(['success' => false, 'error' => 'Non hai i permessi per eliminare questo commento']);
        exit();
    }

    $deleteStmt = $mysqli->prepare("DELETE FROM commenti_shitpost WHERE id = ?");
    $deleteStmt->bind_param("i", $comment_id);

    if ($deleteStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Commento eliminato con successo']);
    } else {
        throw new Exception('Errore durante l\'eliminazione del commento');
    }

    $deleteStmt->close();
} catch (Exception $e) {
    error_log("Errore nell'eliminazione del commento: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Errore nell\'eliminazione del commento']);
}

$mysqli->close();
