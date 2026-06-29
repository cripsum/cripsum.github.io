<?php
require_once __DIR__ . '/bootstrap.php';

$archived = isset($_GET['archived']) ? (int)$_GET['archived'] : 0;

// Query per recuperare le conversazioni dell'utente
$query = "
    SELECT 
        c.id AS conversation_id,
        c.is_group,
        c.title AS group_title,
        cp.is_muted,
        cp.is_archived,
        cp.nickname,
        cp.theme_color,
        cp.theme_bg,
        cp.favorite_emoji,
        (SELECT COUNT(*) FROM private_messages pm 
         WHERE pm.conversation_id = c.id 
           AND pm.id > COALESCE(cp.last_read_message_id, 0)
           AND pm.sender_id != ?
           AND pm.deleted_at IS NULL
           AND NOT EXISTS (SELECT 1 FROM private_message_deleted pmd WHERE pmd.message_id = pm.id AND pmd.user_id = ?)
        ) AS unread_count,
        EXISTS(SELECT 1 FROM private_conversation_pins pin WHERE pin.user_id = ? AND pin.conversation_id = c.id) AS is_pinned,
        
        -- Info sull'altro partecipante (1to1)
        other_u.id AS other_user_id,
        other_u.username AS other_username,
        other_u.ruolo AS other_role,
        other_cp.nickname AS other_nickname,
        
        -- Ultimo messaggio
        last_m.id AS last_message_id,
        last_m.sender_id AS last_message_sender_id,
        last_m.message AS last_message_text,
        last_m.created_at AS last_message_time,
        last_m.deleted_for_all AS last_message_deleted_for_all,
        (SELECT file_type FROM private_message_attachments pma WHERE pma.message_id = last_m.id LIMIT 1) AS last_message_attachment_type
    FROM private_conversation_participants cp
    INNER JOIN private_conversations c ON c.id = cp.conversation_id
    -- Trova l'altro partecipante (escludendo se stessi in chat 1to1)
    INNER JOIN private_conversation_participants other_cp ON other_cp.conversation_id = c.id AND other_cp.user_id != ?
    INNER JOIN utenti other_u ON other_u.id = other_cp.user_id
    -- Ultimo messaggio della conversazione (escludendo quelli eliminati localmente dall'utente)
    LEFT JOIN (
        SELECT pm1.*
        FROM private_messages pm1
        INNER JOIN (
            SELECT conversation_id, MAX(id) as max_id
            FROM private_messages
            WHERE deleted_at IS NULL
            GROUP BY conversation_id
        ) pm2 ON pm1.id = pm2.max_id
    ) last_m ON last_m.conversation_id = c.id
    WHERE cp.user_id = ? AND cp.is_archived = ?
    ORDER BY is_pinned DESC, COALESCE(last_m.created_at, c.created_at) DESC
";

$stmt = $mysqli->prepare($query);
if (!$stmt) {
    send_error("Errore nel caricamento delle conversazioni: " . $mysqli->error, 500);
}

$stmt->bind_param("iiiiii", $userId, $userId, $userId, $userId, $userId, $archived);
$stmt->execute();
$res = $stmt->get_result();
$conversations = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Arricchiamo le conversazioni con lo stato online in tempo reale (da implementare con caching o query di sessione)
// Nota: assumiamo che lo stato online sia derivato dall'ultima attività registrata (es: meno di 3 minuti fa)
foreach ($conversations as &$conv) {
    $otherId = (int)$conv['other_user_id'];
    
    // Controlliamo l'ultima attività dell'utente nella tabella sessioni o utenti
    // Assumiamo che la tabella utenti abbia una colonna 'last_activity' o simile.
    // Facciamo una query veloce per verificare.
    $conv['is_online'] = false;
    $conv['last_seen'] = null;
    
    $stmtAct = $mysqli->prepare("SELECT ultimo_accesso FROM utenti WHERE id = ? LIMIT 1");
    if ($stmtAct) {
        $stmtAct->bind_param("i", $otherId);
        $stmtAct->execute();
        $resAct = $stmtAct->get_result();
        if ($rowAct = $resAct->fetch_assoc()) {
            $lastAct = $rowAct['ultimo_accesso'] ? strtotime($rowAct['ultimo_accesso']) : 0;
            // Se l'ultima attività è inferiore a 3 minuti (180 secondi) fa, l'utente è online
            if ((time() - $lastAct) < 180) {
                $conv['is_online'] = true;
            } else {
                $conv['last_seen'] = $rowAct['ultimo_accesso'];
            }
        }
        $stmtAct->close();
    }
    
    // Determiniamo il testo dell'anteprima dell'ultimo messaggio
    if ($conv['last_message_id']) {
        if ($conv['last_message_deleted_for_all']) {
            $conv['preview_text'] = "🚫 Questo messaggio è stato eliminato.";
        } else {
            // Controlliamo se è stato eliminato localmente da questo utente specifico
            $stmtDel = $mysqli->prepare("SELECT id FROM private_message_deleted WHERE message_id = ? AND user_id = ? LIMIT 1");
            $isLocalDeleted = false;
            if ($stmtDel) {
                $stmtDel->bind_param("ii", $conv['last_message_id'], $userId);
                $stmtDel->execute();
                $isLocalDeleted = $stmtDel->get_result()->num_rows > 0;
                $stmtDel->close();
            }
            
            if ($isLocalDeleted) {
                $conv['preview_text'] = "Messaggio non disponibile.";
            } elseif ($conv['last_message_attachment_type']) {
                switch ($conv['last_message_attachment_type']) {
                    case 'image': $conv['preview_text'] = "📷 Foto"; break;
                    case 'video': $conv['preview_text'] = "🎥 Video"; break;
                    case 'audio': $conv['preview_text'] = "🎵 Audio"; break;
                    case 'sticker': $conv['preview_text'] = "🎨 Sticker"; break;
                    default: $conv['preview_text'] = "📁 File"; break;
                }
            } else {
                $conv['preview_text'] = $conv['last_message_text'];
            }
        }
    } else {
        $conv['preview_text'] = "Nessun messaggio presente.";
    }
}
unset($conv);

send_success(['conversations' => $conversations]);
?>
