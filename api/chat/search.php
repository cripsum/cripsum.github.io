<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$type = isset($input['type']) ? trim((string)$input['type']) : 'users'; // 'users' o 'messages'
$query = isset($input['q']) ? trim((string)$input['q']) : (isset($_GET['q']) ? trim((string)$GET['q']) : '');

if ($query === '') {
    send_success(['results' => []]);
}

$likeQuery = '%' . $query . '%';

switch ($type) {
    case 'users':
        // Cerca utenti per iniziare una nuova conversazione
        // Esclude se stessi e gli utenti che abbiamo bloccato o che ci hanno bloccato
        $sql = "
            SELECT u.id, u.username, u.ruolo, u.is_premium
            FROM utenti u
            WHERE u.username LIKE ? AND u.id != ?
              AND NOT EXISTS (
                  SELECT 1 FROM blocked_users b 
                  WHERE (b.blocker_id = ? AND b.blocked_id = u.id)
                     OR (b.blocker_id = u.id AND b.blocked_id = ?)
              )
            LIMIT 15
        ";
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            send_error("Errore interno del server durante la ricerca.", 500);
        }
        
        $stmt->bind_param("siii", $likeQuery, $userId, $userId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $users = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        send_success(['results' => $users]);
        break;
        
    case 'messages':
        // Cerca messaggi all'interno di una specifica conversazione
        $conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : (isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0);
        if (!$conversationId) {
            send_error("ID conversazione mancante o non valido.");
        }
        
        // Verifica partecipazione
        $stmtCheck = $mysqli->prepare("SELECT id FROM private_conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1");
        $stmtCheck->bind_param("ii", $conversationId, $userId);
        $stmtCheck->execute();
        $isPart = $stmtCheck->get_result()->num_rows > 0;
        $stmtCheck->close();
        
        if (!$isPart) {
            send_error("Non sei autorizzato a cercare in questa conversazione.", 403);
        }
        
        $sql = "
            SELECT m.id, m.sender_id, u.username as sender_username, m.message, m.created_at
            FROM private_messages m
            INNER JOIN utenti u ON u.id = m.sender_id
            WHERE m.conversation_id = ? AND m.message LIKE ? AND m.deleted_at IS NULL AND m.deleted_for_all = 0
              AND NOT EXISTS (SELECT 1 FROM private_message_deleted pmd WHERE pmd.message_id = m.id AND pmd.user_id = ?)
            ORDER BY m.id DESC
            LIMIT 50
        ";
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            send_error("Errore interno del server durante la ricerca messaggi.", 500);
        }
        
        $stmt->bind_param("isi", $conversationId, $likeQuery, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $messages = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        send_success(['results' => $messages]);
        break;
        
    default:
        send_error("Tipo di ricerca non supportato.");
        break;
}
?>
