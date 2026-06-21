<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non autenticato.']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $category = $_GET['category'] ?? '';
    $status = $_GET['status'] ?? ''; // 'unread', 'read', 'important', 'archived'
    $q = trim($_GET['q'] ?? '');
    
    $where = ["r.recipient_id = ?"];
    $params = [$userId];
    $types = "i";
    
    if ($category !== '') {
        $where[] = "m.category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    if ($status === 'unread') {
        $where[] = "r.is_read = 0 AND r.is_archived = 0";
    } elseif ($status === 'read') {
        $where[] = "r.is_read = 1 AND r.is_archived = 0";
    } elseif ($status === 'important') {
        $where[] = "r.is_important = 1 AND r.is_archived = 0";
    } elseif ($status === 'archived') {
        $where[] = "r.is_archived = 1";
    } else {
        $where[] = "r.is_archived = 0";
    }
    
    if ($q !== '') {
        $where[] = "(m.title_it LIKE ? OR m.title_en LIKE ? OR m.content_it LIKE ? OR m.content_en LIKE ?)";
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
        $types .= "ssss";
    }
    
    $whereSql = implode(" AND ", $where);
    
    $query = "
        SELECT r.id AS recipient_row_id, m.id AS message_id, m.sender_id, 
               m.title_it, m.title_en, m.content_it, m.content_en, m.category,
               r.is_read, r.is_archived, r.is_important, r.claimed_at, 
               r.read_at, m.created_at,
               (SELECT COUNT(*) FROM site_message_rewards mr WHERE mr.message_id = m.id) AS has_rewards
        FROM site_message_recipients r
        INNER JOIN site_messages m ON m.id = r.message_id
        WHERE $whereSql
        ORDER BY m.created_at DESC
    ";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        echo json_encode(['ok' => false, 'error' => 'Errore nella query.']);
        exit();
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($messages as &$msg) {
        $msg['rewards'] = [];
        if ((int)$msg['has_rewards'] > 0) {
            $stmtRewards = $mysqli->prepare("
                SELECT reward_type, reward_value, quantity 
                FROM site_message_rewards 
                WHERE message_id = ?
            ");
            if ($stmtRewards) {
                $stmtRewards->bind_param("i", $msg['message_id']);
                $stmtRewards->execute();
                $resRewards = $stmtRewards->get_result();
                $msg['rewards'] = $resRewards->fetch_all(MYSQLI_ASSOC);
                $stmtRewards->close();
            }
        }
    }
    unset($msg);
    
    $unreadCount = getUnreadMessagesCount($mysqli, $userId);
    echo json_encode(['ok' => true, 'messages' => $messages, 'unread_count' => $unreadCount]);
    exit();
    
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';
    $messageId = isset($input['message_id']) ? (int)$input['message_id'] : 0;
    
    if (!$messageId) {
        echo json_encode(['ok' => false, 'error' => 'ID messaggio mancante o non valido.']);
        exit();
    }
    
    if ($action === 'read') {
        $stmt = $mysqli->prepare("
            UPDATE site_message_recipients 
            SET is_read = 1, read_at = COALESCE(read_at, NOW()) 
            WHERE recipient_id = ? AND message_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $userId, $messageId);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Impossibile aggiornare lo stato.']);
        }
        exit();
        
    } elseif ($action === 'toggle_important') {
        $stmt = $mysqli->prepare("
            UPDATE site_message_recipients 
            SET is_important = 1 - is_important 
            WHERE recipient_id = ? AND message_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $userId, $messageId);
            $stmt->execute();
            $stmt->close();
            
            $stmtNew = $mysqli->prepare("SELECT is_important FROM site_message_recipients WHERE recipient_id = ? AND message_id = ?");
            $stmtNew->bind_param("ii", $userId, $messageId);
            $stmtNew->execute();
            $stmtNew->bind_result($isImportant);
            $stmtNew->fetch();
            $stmtNew->close();
            
            echo json_encode(['ok' => true, 'is_important' => (int)$isImportant]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Impossibile aggiornare lo stato importante.']);
        }
        exit();
        
    } elseif ($action === 'toggle_archive') {
        $stmt = $mysqli->prepare("
            UPDATE site_message_recipients 
            SET is_archived = 1 - is_archived 
            WHERE recipient_id = ? AND message_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $userId, $messageId);
            $stmt->execute();
            $stmt->close();
            
            $stmtNew = $mysqli->prepare("SELECT is_archived FROM site_message_recipients WHERE recipient_id = ? AND message_id = ?");
            $stmtNew->bind_param("ii", $userId, $messageId);
            $stmtNew->execute();
            $stmtNew->bind_result($isArchived);
            $stmtNew->fetch();
            $stmtNew->close();
            
            echo json_encode(['ok' => true, 'is_archived' => (int)$isArchived]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Impossibile archiviare/ripristinare il messaggio.']);
        }
        exit();
        
    } elseif ($action === 'claim_rewards') {
        $res = claimMessageRewards($mysqli, $userId, $messageId);
        echo json_encode($res);
        exit();
        
    } elseif ($action === 'delete') {
        $stmt = $mysqli->prepare("
            DELETE FROM site_message_recipients 
            WHERE recipient_id = ? AND message_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $userId, $messageId);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Impossibile eliminare il messaggio.']);
        }
        exit();
    } else {
        echo json_encode(['ok' => false, 'error' => 'Azione non supportata.']);
        exit();
    }
} else {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo non consentito.']);
    exit();
}
