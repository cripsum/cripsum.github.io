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

$whereSql = "f.followed_id = ?";
$params = [$targetId];
$types = "i";

if ($query !== '') {
    $whereSql .= " AND u.username LIKE ?";
    $params[] = '%' . $query . '%';
    $types .= "s";
}

// Aggiungiamo i parametri di paginazione
$userOne = min($userId, $targetId); // fittizio per il bind, usiamo variabili
$params[] = $userId; // per is_following
$params[] = $userId; // per is_followed_by
$params[] = $userId; // per is_friend (user_one)
$params[] = $userId; // per is_friend (user_two)
$params[] = $userId; // per request_sent
$params[] = $userId; // per request_received
$params[] = $limit;
$params[] = $offset;
$types .= "iiiiiiii";

$sql = "
    SELECT 
        u.id, u.username, u.ruolo, u.is_premium,
        EXISTS(SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = u.id) AS is_following,
        EXISTS(SELECT 1 FROM user_follows WHERE follower_id = u.id AND followed_id = ?) AS is_followed_by,
        EXISTS(SELECT 1 FROM friendships WHERE (user_one_id = LEAST(?, u.id) AND user_two_id = GREATEST(?, u.id))) AS is_friend,
        EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = ? AND receiver_id = u.id AND status = 'pending') AS request_sent,
        EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = u.id AND receiver_id = ? AND status = 'pending') AS request_received
    FROM user_follows f
    INNER JOIN utenti u ON u.id = f.follower_id
    WHERE $whereSql
    ORDER BY f.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    send_api_error("Errore interno del server durante il caricamento dei follower.", "DATABASE_ERROR", 500);
}

// Prepariamo i parametri dinamici per il bind_param
// Il primo parametro di bind_param deve essere la stringa dei tipi, seguita dai valori reali.
// Poiché $whereSql contiene ? in base alla query, inseriamo i parametri nell'ordine corretto:
// Ordine dei segnaposto:
// 1. is_following -> ? ($userId)
// 2. is_followed_by -> ? ($userId)
// 3. is_friend least -> ? ($userId)
// 4. is_friend greatest -> ? ($userId)
// 5. request_sent -> ? ($userId)
// 6. request_received -> ? ($userId)
// 7. followed_id -> ? ($targetId)
// [Opzionale] 8. username like -> ? (%query%)
// 9. limit -> ? ($limit)
// 10. offset -> ? ($offset)

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

$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$res = $stmt->get_result();
$followers = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Cast corretto dei booleani per il JSON
foreach ($followers as &$f) {
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
    'users' => $followers,
    'page' => $page,
    'has_more' => count($followers) >= $limit
]);
?>
