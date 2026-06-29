<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$followedId = isset($input['followed_id']) ? (int)$input['followed_id'] : 0;

if (!$followedId) {
    send_api_error("ID utente da seguire mancante o non valido.", "INVALID_INPUT");
}

if ($followedId === $userId) {
    send_api_error("Non puoi seguire te stesso.", "SELF_FOLLOW_FORBIDDEN");
}

// 1. Controlliamo lo stato di relazione e i permessi di privacy
$rel = getRelationshipStatus($mysqli, $userId, $followedId);

if ($rel['is_blocked_by_viewer'] || $rel['has_blocked_viewer']) {
    send_api_error("Impossibile seguire l'utente. C'è un blocco attivo.", "BLOCKED", 403);
}

if (!$rel['can_follow']) {
    send_api_error("Questo utente non consente il follow in base alle sue impostazioni di privacy.", "FOLLOW_RESTRICTED", 403);
}

if ($rel['is_following']) {
    send_api_success([], "Segui già questo utente.");
}

// 2. Inseriamo il follow nel database
$stmt = $mysqli->prepare("INSERT IGNORE INTO user_follows (follower_id, followed_id) VALUES (?, ?)");
if ($stmt) {
    $stmt->bind_param("ii", $userId, $followedId);
    $ok = $stmt->execute();
    $stmt->close();
    
    if ($ok) {
        // 3. Generiamo una notifica per l'utente seguito
        $myUsername = $_SESSION['username'] ?? 'Un utente';
        $titleIt = "@$myUsername ha iniziato a seguirti!";
        $titleEn = "@$myUsername started following you!";
        $contentIt = "Grande notizia! **@$myUsername** ha iniziato a seguirti. Clicca [qui](/u/$myUsername) per visualizzare il suo profilo!";
        $contentEn = "Great news! **@$myUsername** started following you. Click [here](/u/$myUsername) to view their profile!";
        
        sendSocialNotification($mysqli, $followedId, $titleIt, $titleEn, $contentIt, $contentEn);
        
        send_api_success(['is_following' => true], "Hai iniziato a seguire l'utente.");
    }
}

send_api_error("Errore di database durante il follow.", "DATABASE_ERROR", 500);
?>
