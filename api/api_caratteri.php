<?php
// api_caratteri.php
require_once '../config/database.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get_all';

switch ($action) {
    case 'get_all':
        getAllCharacters();
        break;
    case 'get_by_rarity':
        getCharactersByRarity();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Azione non valida']);
}

function getAllCharacters() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT nome, rarità, categoria, img_url, audio_url FROM personaggi ORDER BY nome");
        $stmt->execute();
        $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Converti in formato compatibile con il codice esistente
        $formatted_characters = [];
        foreach ($characters as $char) {
            $formatted_characters[] = [
                'name' => $char['nome'],
                'rarity' => $char['rarità'],
                'category' => $char['categoria'],
                'img' => $char['img_url'],
                'audio' => $char['audio_url']
            ];
        }
        
        echo json_encode($formatted_characters);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore database: ' . $e->getMessage()]);
    }
}

function getCharactersByRarity() {
    global $pdo;
    
    $rarity = $_GET['rarity'] ?? '';
    
    if (empty($rarity)) {
        http_response_code(400);
        echo json_encode(['error' => 'Rarità non specificata']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT nome, rarità, categoria, img_url, audio_url FROM personaggi WHERE rarità = ? ORDER BY nome");
        $stmt->execute([$rarity]);
        $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Converti in formato compatibile con il codice esistente
        $formatted_characters = [];
        foreach ($characters as $char) {
            $formatted_characters[] = [
                'name' => $char['nome'],
                'rarity' => $char['rarità'],
                'category' => $char['categoria'],
                'img' => $char['img_url'],
                'audio' => $char['audio_url']
            ];
        }
        
        echo json_encode($formatted_characters);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore database: ' . $e->getMessage()]);
    }
}
?>