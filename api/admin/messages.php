<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $query = "
        SELECT m.id, m.title_it, m.title_en, m.category, m.target_type, m.created_at,
               (SELECT COUNT(*) FROM site_message_recipients r WHERE r.message_id = m.id) AS recipient_count,
               (SELECT COUNT(*) FROM site_message_rewards mr WHERE mr.message_id = m.id) AS reward_count
        FROM site_messages m
        ORDER BY m.created_at DESC
        LIMIT 100
    ";
    
    $result = $mysqli->query($query);
    if (!$result) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Errore nel database.']);
        exit();
    }
    
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['ok' => true, 'messages' => $messages]);
    exit();
    
} elseif ($method === 'POST') {
    $input = admin_input();
    
    $title_it = trim($input['title_it'] ?? '');
    $title_en = trim($input['title_en'] ?? '');
    $content_it = trim($input['content_it'] ?? '');
    $content_en = trim($input['content_en'] ?? '');
    $category = trim($input['category'] ?? 'system');
    $target_type = trim($input['target_type'] ?? 'single');
    $target_user = trim($input['target_user'] ?? '');
    $rewards = $input['rewards'] ?? [];
    
    if ($title_it === '' || $title_en === '' || $content_it === '' || $content_en === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Tutti i campi testuali (IT e EN) sono obbligatori.']);
        exit();
    }
    
    $recipientIds = [];
    
    if ($target_type === 'single') {
        if ($target_user === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'È necessario specificare un utente destinatario.']);
            exit();
        }
        
        $stmtUser = $mysqli->prepare("SELECT id FROM utenti WHERE username = ? LIMIT 1");
        $stmtUser->bind_param("s", $target_user);
        $stmtUser->execute();
        $resUser = $stmtUser->get_result();
        $userRow = $resUser->fetch_assoc();
        $stmtUser->close();
        
        if (!$userRow) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => "L'utente '$target_user' non esiste."]);
            exit();
        }
        
        $recipientIds[] = (int)$userRow['id'];
        
    } elseif ($target_type === 'group') {
        if ($target_user === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Specificare almeno un utente per il gruppo.']);
            exit();
        }
        
        $usernames = array_map('trim', explode(',', $target_user));
        $usernames = array_filter($usernames);
        
        if (empty($usernames)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Nessun nome utente valido inserito.']);
            exit();
        }
        
        $inClause = implode(',', array_fill(0, count($usernames), '?'));
        $stmtUsers = $mysqli->prepare("SELECT id, username FROM utenti WHERE username IN ($inClause)");
        
        $types = str_repeat('s', count($usernames));
        $stmtUsers->bind_param($types, ...$usernames);
        $stmtUsers->execute();
        $resUsers = $stmtUsers->get_result();
        $foundUsers = $resUsers->fetch_all(MYSQLI_ASSOC);
        $stmtUsers->close();
        
        $foundUserMap = [];
        foreach ($foundUsers as $u) {
            $recipientIds[] = (int)$u['id'];
            $foundUserMap[strtolower($u['username'])] = true;
        }
        
        $missing = [];
        foreach ($usernames as $u) {
            if (!isset($foundUserMap[strtolower($u)])) {
                $missing[] = $u;
            }
        }
        
        if (!empty($missing)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "I seguenti utenti non esistono: " . implode(', ', $missing)]);
            exit();
        }
    }
    
    $mysqli->begin_transaction();
    
    try {
        $senderId = (int)$adminUser['id'];
        $stmtMsg = $mysqli->prepare("
            INSERT INTO site_messages (sender_id, title_it, title_en, content_it, content_en, category, target_type)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmtMsg->bind_param("issssss", $senderId, $title_it, $title_en, $content_it, $content_en, $category, $target_type);
        $stmtMsg->execute();
        $messageId = $mysqli->insert_id;
        $stmtMsg->close();
        
        if (!empty($rewards) && is_array($rewards)) {
            $stmtRew = $mysqli->prepare("
                INSERT INTO site_message_rewards (message_id, reward_type, reward_value, quantity)
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($rewards as $rew) {
                $rewType = trim($rew['type'] ?? '');
                $rewVal = trim($rew['value'] ?? '');
                $rewQty = (int)($rew['quantity'] ?? 1);
                
                if (!in_array($rewType, ['points', 'godoshards', 'character', 'badge', 'premium'], true) || $rewVal === '') {
                    throw new Exception("Tipo o valore premio non valido.");
                }
                
                $stmtRew->bind_param("issi", $messageId, $rewType, $rewVal, $rewQty);
                $stmtRew->execute();
            }
            $stmtRew->close();
        }
        
        if ($target_type === 'single' || $target_type === 'group') {
            $stmtRec = $mysqli->prepare("
                INSERT IGNORE INTO site_message_recipients (message_id, recipient_id)
                VALUES (?, ?)
            ");
            foreach ($recipientIds as $recId) {
                $stmtRec->bind_param("ii", $messageId, $recId);
                $stmtRec->execute();
            }
            $stmtRec->close();
        } elseif ($target_type === 'all') {
            $stmtRec = $mysqli->prepare("
                INSERT IGNORE INTO site_message_recipients (message_id, recipient_id)
                SELECT ?, id FROM utenti
            ");
            $stmtRec->bind_param("i", $messageId);
            $stmtRec->execute();
            $stmtRec->close();
        } elseif ($target_type === 'premium') {
            $stmtRec = $mysqli->prepare("
                INSERT IGNORE INTO site_message_recipients (message_id, recipient_id)
                SELECT ?, id FROM utenti WHERE is_premium = 1
            ");
            $stmtRec->bind_param("i", $messageId);
            $stmtRec->execute();
            $stmtRec->close();
        }
        
        $logTargetId = ($target_type === 'single' && !empty($recipientIds)) ? $recipientIds[0] : null;
        admin_log($mysqli, $senderId, 'create_message', $logTargetId, [
            'message_id' => $messageId,
            'title_it' => $title_it,
            'target_type' => $target_type
        ]);
        
        $mysqli->commit();
        echo json_encode(['ok' => true, 'message_id' => $messageId]);
        exit();
        
    } catch (Exception $e) {
        $mysqli->rollback();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Impossibile inviare il messaggio: ' . $e->getMessage()]);
        exit();
    }
} else {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo non consentito.']);
    exit();
}
