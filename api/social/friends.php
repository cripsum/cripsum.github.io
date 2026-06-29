<?php
require_once __DIR__ . '/bootstrap.php';

$targetId = isset($_GET['target_id']) ? (int)$_GET['target_id'] : $userId;

// Verifichiamo se possiamo vedere il profilo dell'utente target
$targetRel = getRelationshipStatus($mysqli, $userId, $targetId);
if (!$targetRel['can_view_profile'] && $targetId !== $userId) {
    send_api_error("Questo profilo è privato o hai un blocco attivo.", "PROFILE_PRIVATE", 403);
}

$sql = "
    SELECT 
        u.id, u.username, u.ruolo, u.is_premium, u.ultimo_accesso,
        EXISTS(SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = u.id) AS is_following,
        EXISTS(SELECT 1 FROM user_follows WHERE follower_id = u.id AND followed_id = ?) AS is_followed_by,
        EXISTS(SELECT 1 FROM friendships WHERE (user_one_id = LEAST(?, u.id) AND user_two_id = GREATEST(?, u.id))) AS is_friend,
        EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = ? AND receiver_id = u.id AND status = 'pending') AS request_sent,
        EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = u.id AND receiver_id = ? AND status = 'pending') AS request_received
    FROM friendships f
    INNER JOIN utenti u ON u.id = IF(f.user_one_id = ?, f.user_two_id, f.user_one_id)
    WHERE f.user_one_id = ? OR f.user_two_id = ?
    ORDER BY u.username ASC
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    send_api_error("Errore di database durante il caricamento degli amici.", "DATABASE_ERROR", 500);
}

$stmt->bind_param(
    "iiiiiiiii",
    $userId, $userId, $userId, $userId, $userId, $userId,
    $targetId, $targetId, $targetId
);

$stmt->execute();
$res = $stmt->get_result();
$allFriends = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$onlineFriends = [];
$offlineFriends = [];

foreach ($allFriends as &$f) {
    $f['id'] = (int)$f['id'];
    $f['is_following'] = (bool)$f['is_following'];
    $f['is_followed_by'] = (bool)$f['is_followed_by'];
    $f['is_mutual_follow'] = ($f['is_following'] && $f['is_followed_by']);
    $f['is_friend'] = (bool)$f['is_friend'];
    $f['friend_request_sent'] = (bool)$f['request_sent'];
    $f['friend_request_received'] = (bool)$f['request_received'];
    
    // Calcolo stato online
    $lastAct = $f['ultimo_accesso'] ? strtotime($f['ultimo_accesso']) : 0;
    $isOnline = (time() - $lastAct) < 180; // Attivo negli ultimi 3 minuti
    $f['is_online'] = $isOnline;
    
    unset($f['request_sent'], $f['request_received'], $f['ultimo_accesso']);
    
    if ($isOnline) {
        $onlineFriends[] = $f;
    } else {
        $offlineFriends[] = $f;
    }
}
unset($f);

send_api_success([
    'online' => $onlineFriends,
    'offline' => $offlineFriends,
    'all' => $allFriends
]);
?>
