<?php
require_once __DIR__ . '/bootstrap.php';

$targetId = isset($_GET['target_id']) ? (int)$_GET['target_id'] : $userId;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

if ($page < 1) $page = 1;
if ($limit < 1 || $limit > 50) $limit = 20;
$offset = ($page - 1) * $limit;

// Verifichiamo se possiamo vedere il profilo dell'utente target
$targetRel = getRelationshipStatus($mysqli, $userId, $targetId);
if (!$targetRel['can_view_profile'] && $targetId !== $userId) {
    send_api_error("Questo profilo è privato o hai un blocco attivo.", "PROFILE_PRIVATE", 403);
}

$whereSql = "f.follower_id = ?";
$bindTypes = "iiiiii" . ($query !== '' ? "is" : "i") . "ii";
$bindParams = [
    $userId, $userId, $userId, $userId, $userId, $userId,
    $targetId
];
if ($query !== '') {
    $bindParams[] = '%' . $query . '%';
}
$bindParams[] = $limit;
$bindParams[] = $offset;

if ($query !== '') {
    $whereSql .= " AND u.username LIKE ?";
}

$sql = "
    SELECT 
        u.id, u.username, u.ruolo, u.is_premium,
        EXISTS(SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = u.id) AS is_following,
        EXISTS(SELECT 1 FROM user_follows WHERE follower_id = u.id AND followed_id = ?) AS is_followed_by,
        EXISTS(SELECT 1 FROM friendships WHERE (user_one_id = LEAST(?, u.id) AND user_two_id = GREATEST(?, u.id))) AS is_friend,
        EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = ? AND receiver_id = u.id AND status = 'pending') AS request_sent,
        EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = u.id AND receiver_id = ? AND status = 'pending') AS request_received
    FROM user_follows f
    INNER JOIN utenti u ON u.id = f.followed_id
    WHERE $whereSql
    ORDER BY f.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    send_api_error("Errore interno del server durante il caricamento dei seguiti.", "DATABASE_ERROR", 500);
}

$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$res = $stmt->get_result();
$following = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Cast dei booleani
foreach ($following as &$f) {
    $f['id'] = (int)$f['id'];
    $f['is_following'] = (bool)$f['is_following'];
    $f['is_followed_by'] = (bool)$f['is_followed_by'];
    $f['is_mutual_follow'] = ($f['is_following'] && $f['is_followed_by']);
    $f['is_friend'] = (bool)$f['is_friend'];
    $f['friend_request_sent'] = (bool)$f['request_sent'];
    $f['friend_request_received'] = (bool)$f['request_received'];
    unset($f['request_sent'], $f['request_received']);
}
unset($f);

send_api_success([
    'users' => $following,
    'page' => $page,
    'has_more' => count($following) >= $limit
]);
?>
