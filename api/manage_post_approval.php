<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Accesso non autorizzato']);
    exit;
}

checkBan($mysqli);

$stmt = $mysqli->prepare("SELECT ruolo FROM utenti WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $isAdmin = in_array($row['ruolo'], ['admin', 'owner']);
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Utente non trovato']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['post_id']) || !isset($input['approved'])) {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

$post_id = (int)$input['post_id'];
$approved = (int)$input['approved'];

try {
    $stmt = $mysqli->prepare("SELECT id, approvato FROM toprimasti WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result->fetch_assoc()) {
        echo json_encode(['success' => false, 'error' => 'Post non trovato']);
        exit;
    }
    
    $stmt = $mysqli->prepare("UPDATE toprimasti SET approvato = ? WHERE id = ?");
    $stmt->bind_param("ii", $approved, $post_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => $approved == 1 ? 'Post approvato con successo' : 'Post disapprovato con successo'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Errore nell\'aggiornamento del database']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Errore del server: ' . $e->getMessage()]);
}

$mysqli->close();
?>