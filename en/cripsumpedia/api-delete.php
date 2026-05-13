<?php
// api-delete.php
require_once '../../config/session_init.php';
require_once '../../config/database.php';

// IMPORTANTE: Aggiungi controllo amministratore
// checkAdmin($mysqli);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$tipo = $input['tipo'] ?? '';
$id = $input['id'] ?? 0;

if (!$id || !$tipo) {
    echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
    exit;
}

try {
    switch ($tipo) {
        case 'persona':
            $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_persone WHERE id = ?");
            break;
        case 'evento':
            $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_eventi WHERE id = ?");
            break;
        case 'meme':
            $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_meme WHERE id = ?");
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Tipo non valido']);
            exit;
    }

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Eliminato con successo']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'eliminazione']);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
