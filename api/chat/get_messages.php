<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : (isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0);
$beforeMessageId = isset($input['before_message_id']) ? (int)$input['before_message_id'] : (isset($_GET['before_message_id']) ? (int)$_GET['before_message_id'] : 0);
$limit = 30; // Numero di messaggi per pagina

if (!$conversationId) {
    send_error("ID conversazione mancante o non valido.");
}

// 1. Verifica che l'utente loggato sia parte della conversazione
$stmtCheck = $mysqli->prepare("SELECT id, last_read_message_id FROM private_conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1");
if (!$stmtCheck) {
    send_error("Errore interno del server.", 500);
}
$stmtCheck->bind_param("ii", $conversationId, $userId);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();
$participantRow = $resCheck->fetch_assoc();
$stmtCheck->close();

if (!$participantRow) {
    send_error("Accesso negato. Non sei un partecipante di questa conversazione.", 403);
}

// 2. Prepara la clausola WHERE per la paginazione
$whereSql = "m.conversation_id = ? AND m.deleted_at IS NULL";
$params = [$conversationId];
$types = "i";

if ($beforeMessageId > 0) {
    $whereSql .= " AND m.id < ?";
    $params[] = $beforeMessageId;
    $types .= "i";
}

// Escludiamo i messaggi che l'utente ha eliminato localmente per se stesso
$whereSql .= " AND NOT EXISTS (SELECT 1 FROM private_message_deleted pmd WHERE pmd.message_id = m.id AND pmd.user_id = ?)";
$params[] = $userId;
$types .= "i";

// 3. Esegui la query per caricare i messaggi
$query = "
    SELECT 
        m.id,
        m.conversation_id,
        m.sender_id,
        u.username AS sender_username,
        u.ruolo AS sender_role,
        m.message,
        m.reply_to_id,
        m.forwarded_from_id,
        m.is_edited,
        m.ephemeral_timer,
        m.ephemeral_expires_at,
        m.created_at,
        m.deleted_for_all,
        
        -- Dati del messaggio a cui risponde (se presente)
        reply_m.message AS reply_message_text,
        reply_u.username AS reply_username,
        
        -- Se il messaggio è fissato
        EXISTS(SELECT 1 FROM private_pinned_messages ppm WHERE ppm.message_id = m.id) AS is_pinned
    FROM private_messages m
    INNER JOIN utenti u ON u.id = m.sender_id
    LEFT JOIN private_messages reply_m ON reply_m.id = m.reply_to_id
    LEFT JOIN utenti reply_u ON reply_u.id = reply_m.sender_id
    WHERE $whereSql
    ORDER BY m.id DESC
    LIMIT ?
";

$params[] = $limit;
$types .= "i";

$stmt = $mysqli->prepare($query);
if (!$stmt) {
    send_error("Errore nel database durante il recupero dei messaggi: " . $mysqli->error, 500);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$messages = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ordiniamo i messaggi in senso cronologico per il client
$messages = array_reverse($messages);

// 4. Arricchiamo i messaggi con allegati e reazioni
$maxMessageId = 0;
foreach ($messages as &$msg) {
    $msgId = (int)$msg['id'];
    if ($msgId > $maxMessageId) {
        $maxMessageId = $msgId;
    }
    
    $msg['attachments'] = [];
    $msg['reactions'] = [];
    
    // Se il messaggio è eliminato per tutti, oscuriamo il testo
    if ($msg['deleted_for_all']) {
        $msg['message'] = null;
        continue;
    }

    // Carica gli allegati
    $stmtAtt = $mysqli->prepare("SELECT id, file_name, file_path, file_size, file_mime, file_type FROM private_message_attachments WHERE message_id = ?");
    if ($stmtAtt) {
        $stmtAtt->bind_param("i", $msgId);
        $stmtAtt->execute();
        $msg['attachments'] = $stmtAtt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtAtt->close();
    }
    
    // Carica le reazioni raggruppate
    $stmtReact = $mysqli->prepare("
        SELECT r.reaction, GROUP_CONCAT(u.username SEPARATOR ', ') as usernames, COUNT(*) as count,
               MAX(CASE WHEN r.user_id = ? THEN 1 ELSE 0 END) as user_reacted
        FROM private_message_reactions r
        INNER JOIN utenti u ON u.id = r.user_id
        WHERE r.message_id = ?
        GROUP BY r.reaction
    ");
    if ($stmtReact) {
        $stmtReact->bind_param("ii", $userId, $msgId);
        $stmtReact->execute();
        $msg['reactions'] = $stmtReact->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtReact->close();
    }
}
unset($msg);

// 5. Aggiorna lo stato "letto" (last_read_message_id) se ci sono nuovi messaggi
if ($maxMessageId > (int)$participantRow['last_read_message_id']) {
    $stmtUpdateRead = $mysqli->prepare("UPDATE private_conversation_participants SET last_read_message_id = ? WHERE conversation_id = ? AND user_id = ?");
    if ($stmtUpdateRead) {
        $stmtUpdateRead->bind_param("iii", $maxMessageId, $conversationId, $userId);
        $stmtUpdateRead->execute();
        $stmtUpdateRead->close();
    }
}

send_success([
    'messages' => $messages,
    'has_more' => count($messages) >= $limit
]);
?>
