<?php
require_once __DIR__ . '/bootstrap.php';

$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if ($limit < 1 || $limit > 50) $limit = 20;
if ($offset < 0) $offset = 0;

if ($query === '') {
    send_api_success(['users' => []], "Inserisci un termine di ricerca.");
}

$likeQuery = '%' . $query . '%';

$sql = "
    SELECT 
        u.id, u.username, u.display_name, u.ruolo, u.is_premium,
        EXISTS(SELECT 1 FROM friendships WHERE (user_one_id = LEAST(?, u.id) AND user_two_id = GREATEST(?, u.id))) AS is_friend,
        EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = ? AND receiver_id = u.id AND status = 'pending') AS request_sent,
        EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = u.id AND receiver_id = ? AND status = 'pending') AS request_received
    FROM utenti u
    WHERE (u.username LIKE ? OR u.display_name LIKE ?) AND u.id != ?
      AND NOT EXISTS (
          SELECT 1 FROM blocked_users b 
          WHERE (b.blocker_id = ? AND b.blocked_id = u.id)
             OR (b.blocker_id = u.id AND b.blocked_id = ?)
      )
    ORDER BY u.username ASC
    LIMIT ? OFFSET ?
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    send_api_error("Errore di database durante la ricerca.", "DATABASE_ERROR", 500);
}

$stmt->bind_param(
    "iiiissiiiii",
    $userId, $userId, $userId, $userId,
    $likeQuery, $likeQuery, $userId,
    $userId, $userId,
    $limit, $offset
);

$stmt->execute();
$res = $stmt->get_result();
$users = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($users as &$u) {
    $u['id'] = (int)$u['id'];
    $u['display_name'] = $u['display_name'] ?: $u['username'];
    $u['is_following'] = false;
    $u['is_followed_by'] = false;
    $u['is_mutual_follow'] = false;
    $u['is_friend'] = (bool)$u['is_friend'];
    $u['friend_request_sent'] = (bool)$u['request_sent'];
    $u['friend_request_received'] = (bool)$u['request_received'];
    unset($u['request_sent'], $u['request_received']);
}
unset($u);

send_api_success(['users' => $users]);
?>
