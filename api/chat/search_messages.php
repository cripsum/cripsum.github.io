<?php
// api/chat/search_messages.php
// Searches messages within a specific group chat.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$chatId = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;
$queryText = isset($_GET['query']) ? trim((string)$_GET['query']) : '';

if (!$chatId) {
    send_error("ID chat mancante o non valido.");
}

if ($queryText === '') {
    send_success(['results' => []]);
}

if (!canViewChat($mysqli, $chatId, $userId)) {
    send_error("Accesso negato o non partecipi a questa chat.", 403);
}

try {
    $searchLike = "%" . $queryText . "%";
    
    $stmt = $mysqli->prepare("
        SELECT 
            m.id,
            m.chat_id,
            m.sender_id,
            u.username AS sender_username,
            u.display_name AS sender_display_name,
            m.body,
            m.message_type,
            m.created_at
        FROM chat_messages m
        LEFT JOIN utenti u ON u.id = m.sender_id
        WHERE m.chat_id = ? 
          AND m.body LIKE ? 
          AND m.message_type = 'text' 
          AND m.deleted_at IS NULL
        ORDER BY m.id DESC
        LIMIT 50
    ");
    
    if (!$stmt) {
        send_error("Errore interno del server.", 500);
    }
    
    $stmt->bind_param("is", $chatId, $searchLike);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($results as &$r) {
        $r['id'] = (int)$r['id'];
        $r['chat_id'] = (int)$r['chat_id'];
        $r['sender_id'] = (int)$r['sender_id'];
    }
    unset($r);
    
    send_success([
        'results' => $results
    ]);

} catch (Throwable $e) {
    send_error("Errore durante la ricerca: " . $e->getMessage(), 500);
}
?>
