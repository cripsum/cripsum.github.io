<?php
ob_start();

require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

ob_end_clean();

header('Content-Type: application/json');

if (!isset($_GET['shitpost_id'])) {
    echo json_encode(['success' => false, 'error' => 'ID shitpost mancante']);
    exit();
}

$shitpost_id = intval($_GET['shitpost_id']);

try {
    $stmt = $mysqli->prepare("
        SELECT 
            c.id,
            c.commento,
            c.data_commento,
            c.id_utente,
            u.username,
            u.profile_pic
        FROM commenti_shitpost c
        JOIN utenti u ON c.id_utente = u.id
        WHERE c.id_shitpost = ?
        ORDER BY c.data_commento DESC
    ");

    if (!$stmt) {
        throw new Exception("Errore nella preparazione della query: " . $mysqli->error);
    }

    $stmt->bind_param("i", $shitpost_id);

    if (!$stmt->execute()) {
        throw new Exception("Errore nell'esecuzione della query: " . $stmt->error);
    }

    $result = $stmt->get_result();

    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            'id' => $row['id'],
            'commento' => $row['commento'],
            'data_commento' => $row['data_commento'],
            'id_utente' => $row['id_utente'],
            'username' => $row['username'],
            'profile_pic' => $row['profile_pic']
        ];
    }

    $stmt->close();
    echo json_encode(['success' => true, 'comments' => $comments]);
} catch (Exception $e) {
    error_log("Errore nel recupero dei commenti: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$mysqli->close();
