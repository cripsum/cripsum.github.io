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

if (!isset($input['post_id'])) {
    echo json_encode(['success' => false, 'error' => 'ID shitpost mancante']);
    exit;
}

$post_id = (int)$input['post_id'];

try {
    $mysqli->begin_transaction();
    
    $stmt = $mysqli->prepare("SELECT id_utente, titolo FROM shitposts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $postData = $result ? $result->fetch_assoc() : null;
    
    if (!$postData) {
        $mysqli->rollback();
        echo json_encode(['success' => false, 'error' => 'Shitpost non trovato']);
        exit;
    }

    $stmt = $mysqli->prepare("DELETE FROM shitposts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $mysqli->commit();
            
            $currentTime = date('d/m/Y H:i:s');
            $recipientId = (int)$postData['id_utente'];
            $postTitle = $postData['titolo'];
            
            $titleIt = "Contenuto rimosso: Violazione linee guida";
            $titleEn = "Content removed: Guidelines violation";
            
            $contentIt = "Il tuo shitpost intitolato \"" . $postTitle . "\" è stato rimosso dai moderatori in data " . $currentTime . " per violazione delle linee guida della community.\n\n" .
                         "Ti invitiamo a rispettare le regole per evitare ulteriori provvedimenti sul tuo account.";
                         
            $contentEn = "Your shitpost titled \"" . $postTitle . "\" has been removed by moderators on " . $currentTime . " for violation of the community guidelines.\n\n" .
                         "Please follow the rules to avoid further action on your account.";
                         
            sendSecurityInboxMessage($mysqli, $recipientId, $titleIt, $titleEn, $contentIt, $contentEn, 'system');
            
            echo json_encode([
                'success' => true, 
                'message' => 'Shitpost eliminato con successo'
            ]);
        } else {
            $mysqli->rollback();
            echo json_encode(['success' => false, 'error' => 'Shitpost non trovato o già eliminato']);
        }
    } else {
        $mysqli->rollback();
        echo json_encode(['success' => false, 'error' => 'Errore nell\'eliminazione del shitpost']);
    }
    
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['success' => false, 'error' => 'Errore del server durante l\'eliminazione']);
}

$mysqli->close();
?>