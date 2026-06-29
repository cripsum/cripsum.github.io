<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;

if (!$conversationId) {
    send_error("ID conversazione mancante o non valido.");
}

// Verifica che il mittente partecipi alla conversazione
$stmtCheck = $mysqli->prepare("SELECT id FROM private_conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1");
$stmtCheck->bind_param("ii", $conversationId, $userId);
$stmtCheck->execute();
$isPart = $stmtCheck->get_result()->num_rows > 0;
$stmtCheck->close();

if (!$isPart) {
    send_error("Non sei autorizzato a modificare le impostazioni di questa conversazione.", 403);
}

$mysqli->begin_transaction();

try {
    // 1. Aggiorna il nickname dell'altro partecipante (se fornito)
    if (isset($input['nickname'])) {
        $nickname = trim((string)$input['nickname']);
        if ($nickname === '') $nickname = null;
        
        // Trova l'altro partecipante (chat 1to1)
        $stmtOther = $mysqli->prepare("SELECT user_id FROM private_conversation_participants WHERE conversation_id = ? AND user_id != ? LIMIT 1");
        $stmtOther->bind_param("ii", $conversationId, $userId);
        $stmtOther->execute();
        $resOther = $stmtOther->get_result();
        $otherRow = $resOther->fetch_assoc();
        $stmtOther->close();
        
        if ($otherRow) {
            $otherUserId = (int)$otherRow['user_id'];
            $stmtNick = $mysqli->prepare("UPDATE private_conversation_participants SET nickname = ? WHERE conversation_id = ? AND user_id = ?");
            $stmtNick->bind_param("sii", $nickname, $conversationId, $otherUserId);
            $stmtNick->execute();
            $stmtNick->close();
        }
    }
    
    // 2. Aggiorna le preferenze grafiche personali dell'utente corrente (colore, sfondo, emoji preferita)
    $themeColor = isset($input['theme_color']) ? trim((string)$input['theme_color']) : null;
    $themeBg = isset($input['theme_bg']) ? trim((string)$input['theme_bg']) : null;
    $favoriteEmoji = isset($input['favorite_emoji']) ? trim((string)$input['favorite_emoji']) : null;
    
    if ($themeColor === '') $themeColor = null;
    if ($themeBg === '') $themeBg = null;
    if ($favoriteEmoji === '') $favoriteEmoji = null;
    
    // Verifichiamo che il colore del tema sia sicuro (es: formato esadecimale o RGB/HSL semplice)
    if ($themeColor && !preg_match('/^(#[a-fA-F0-9]{3,8}|rgba?\(.*\)|hsla?\(.*\)|[a-zA-Z\-]+)$/', $themeColor)) {
        throw new Exception("Formato colore del tema non valido.");
    }

    $stmtPrefs = $mysqli->prepare("
        UPDATE private_conversation_participants 
        SET theme_color = COALESCE(?, theme_color), 
            theme_bg = COALESCE(?, theme_bg), 
            favorite_emoji = COALESCE(?, favorite_emoji)
        WHERE conversation_id = ? AND user_id = ?
    ");
    
    // Se un parametro è esplicitamente passato come stringa vuota o nullo, lo resettiamo nel DB.
    // Per gestire il reset (impostare a NULL nel DB), usiamo query specifiche o passiamo i parametri.
    // Per semplicità, se le chiavi sono presenti nell'input, le aggiorniamo direttamente.
    $bindColor = isset($input['theme_color']) ? $themeColor : null;
    $bindBg = isset($input['theme_bg']) ? $themeBg : null;
    $bindEmoji = isset($input['favorite_emoji']) ? $favoriteEmoji : null;
    
    $stmtPrefs2 = $mysqli->prepare("
        UPDATE private_conversation_participants 
        SET theme_color = ?, 
            theme_bg = ?, 
            favorite_emoji = ?
        WHERE conversation_id = ? AND user_id = ?
    ");
    
    // Eseguiamo l'aggiornamento delle preferenze
    $stmtPrefs2->bind_param("sssii", $themeColor, $themeBg, $favoriteEmoji, $conversationId, $userId);
    $stmtPrefs2->execute();
    $stmtPrefs2->close();
    
    $mysqli->commit();
    send_success([
        'theme_color' => $themeColor,
        'theme_bg' => $themeBg,
        'favorite_emoji' => $favoriteEmoji
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    send_error($e->getMessage());
}
?>
