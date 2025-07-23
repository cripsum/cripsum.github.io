<?php
// api_personaggi.php
session_start();
require_once '../config/database.php';

// Verifica che l'utente sia loggato
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Utente non autenticato']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'add':
        addPersonaggio();
        break;
    case 'get_inventory':
        getInventory();
        break;
    case 'reset_inventory':
        resetInventory();
        break;
    case 'get_character_by_name':
        getCharacterByName();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Azione non valida']);
}

function addPersonaggio() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $character_name = $input['name'] ?? '';
    
    if (empty($character_name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nome personaggio mancante']);
        return;
    }
    
    try {
        // Trova l'ID del personaggio dalla tabella personaggi
        $stmt = $pdo->prepare("SELECT id FROM personaggi WHERE nome = ?");
        $stmt->execute([$character_name]);
        $personaggio = $stmt->fetch();
        
        if (!$personaggio) {
            http_response_code(404);
            echo json_encode(['error' => 'Personaggio non trovato']);
            return;
        }
        
        $personaggio_id = $personaggio['id'];
        
        // Controlla se l'utente ha già questo personaggio
        $stmt = $pdo->prepare("SELECT quantità FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ?");
        $stmt->execute([$user_id, $personaggio_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Incrementa la quantità
            $stmt = $pdo->prepare("UPDATE utenti_personaggi SET quantità = quantità + 1, data = CURRENT_TIMESTAMP WHERE utente_id = ? AND personaggio_id = ?");
            $stmt->execute([$user_id, $personaggio_id]);
            $new_quantity = $existing['quantità'] + 1;
            echo json_encode(['success' => true, 'new_character' => false, 'quantity' => $new_quantity]);
        } else {
            // Aggiungi nuovo personaggio
            $stmt = $pdo->prepare("INSERT INTO utenti_personaggi (utente_id, personaggio_id, quantità, data) VALUES (?, ?, 1, CURRENT_TIMESTAMP)");
            $stmt->execute([$user_id, $personaggio_id]);
            echo json_encode(['success' => true, 'new_character' => true, 'quantity' => 1]);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore database: ' . $e->getMessage()]);
    }
}

function getInventory() {
    global $pdo, $user_id;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.nome, p.rarità, p.categoria, p.img_url, p.audio_url, up.quantità 
            FROM utenti_personaggi up 
            JOIN personaggi p ON up.personaggio_id = p.id 
            WHERE up.utente_id = ?
            ORDER BY p.nome
        ");
        $stmt->execute([$user_id]);
        $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Converti in formato compatibile con il codice esistente
        $formatted_inventory = [];
        foreach ($inventory as $item) {
            $formatted_inventory[] = [
                'name' => $item['nome'],
                'rarity' => $item['rarità'],
                'category' => $item['categoria'],
                'img' => $item['img_url'],
                'audio' => $item['audio_url'],
                'count' => (int)$item['quantità']
            ];
        }
        
        echo json_encode($formatted_inventory);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore database: ' . $e->getMessage()]);
    }
}

function resetInventory() {
    global $pdo, $user_id;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM utenti_personaggi WHERE utente_id = ?");
        $stmt->execute([$user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Inventario resettato con successo']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore database: ' . $e->getMessage()]);
    }
}

function getCharacterByName() {
    global $pdo;
    
    $name = $_GET['name'] ?? '';
    
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nome personaggio mancante']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT nome, rarità, categoria, img_url, audio_url FROM personaggi WHERE nome = ?");
        $stmt->execute([$name]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($character) {
            $formatted_character = [
                'name' => $character['nome'],
                'rarity' => $character['rarità'],
                'category' => $character['categoria'],
                'img' => $character['img_url'],
                'audio' => $character['audio_url']
            ];
            echo json_encode($formatted_character);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Personaggio non trovato']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore database: ' . $e->getMessage()]);
    }
}
?>