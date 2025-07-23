<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function getCharacters($mysqli, $userId) {
    $stmt = $mysqli->prepare("SELECT id, nome, rarità, categoria, img_url, audio_url, data, quantità FROM personaggi, utenti_personaggi WHERE personaggi.id = utenti_personaggi.personaggio_id AND utenti_personaggi.utente_id = ?"); 
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $characters = [];
    while ($row = $result->fetch_assoc()) {
        $characters[] = $row;
    }
    
    $stmt->close();
    return $characters;
}

function addToInventory($mysqli, $userId, $characterId) {
    $stmt = $mysqli->prepare("INSERT INTO utenti_personaggi (utente_id, personaggio_id, data, quantità) VALUES (?, ?, NOW(), 1) ON DUPLICATE KEY UPDATE quantità = quantità + 1");
    $stmt->bind_param("ii", $userId, $characterId);
    
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        $stmt->close();
        return false;
    }
}

function resettaInventario($mysqli, $userId) {
    $stmt = $mysqli->prepare("DELETE FROM utenti_personaggi WHERE utente_id = ?");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        $stmt->close();
        return false;
    }
}

?>