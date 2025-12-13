<?php
ob_start();

require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

ob_end_clean();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Devi essere loggato per commentare']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['shitpost_id']) || !isset($input['commento'])) {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit();
}

$shitpost_id = intval($input['shitpost_id']);
$commento = trim($input['commento']);
$user_id = $_SESSION['user_id'];

if (empty($commento)) {
    echo json_encode(['success' => false, 'error' => 'Il commento non può essere vuoto']);
    exit();
}

if (strlen($commento) > 500) {
    echo json_encode(['success' => false, 'error' => 'Il commento è troppo lungo (max 500 caratteri)']);
    exit();
}

try {
    $checkStmt = $mysqli->prepare("SELECT id FROM shitposts WHERE id = ?");
    if (!$checkStmt) {
        throw new Exception("Errore nella preparazione della query: " . $mysqli->error);
    }

    $checkStmt->bind_param("i", $shitpost_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Shitpost non trovato']);
        exit();
    }
    $checkStmt->close();

    $stmt = $mysqli->prepare("
        INSERT INTO commenti_shitpost (id_shitpost, id_utente, commento, data_commento)
        VALUES (?, ?, ?, NOW())
    ");

    if (!$stmt) {
        throw new Exception("Errore nella preparazione della query di inserimento: " . $mysqli->error);
    }

    $stmt->bind_param("iis", $shitpost_id, $user_id, $commento);

    if ($stmt->execute()) {
        $comment_id = $stmt->insert_id;

        $getStmt = $mysqli->prepare("
            SELECT 
                c.id,
                c.commento,
                c.data_commento,
                c.id_utente,
                u.username,
                u.profile_pic
            FROM commenti_shitpost c
            JOIN utenti u ON c.id_utente = u.id
            WHERE c.id = ?
        ");

        if (!$getStmt) {
            throw new Exception("Errore nella preparazione della query di recupero: " . $mysqli->error);
        }

        $getStmt->bind_param("i", $comment_id);
        $getStmt->execute();
        $result = $getStmt->get_result();
        $comment = $result->fetch_assoc();
        $getStmt->close();

        echo json_encode([
            'success' => true,
            'comment' => $comment,
            'message' => 'Commento aggiunto con successo'
        ]);
    } else {
        throw new Exception('Errore durante l\'inserimento del commento: ' . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Errore nell'aggiunta del commento: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$mysqli->close();
