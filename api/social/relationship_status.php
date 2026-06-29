<?php
require_once __DIR__ . '/bootstrap.php';

$targetId = isset($_GET['target_id']) ? (int)$_GET['target_id'] : 0;
$userIdsStr = isset($_GET['user_ids']) ? trim((string)$_GET['user_ids']) : '';

$ids = [];
if ($targetId > 0) {
    $ids[] = $targetId;
} elseif ($userIdsStr !== '') {
    // Esplode e pulisce la lista di ID numerici
    $parts = explode(',', $userIdsStr);
    foreach ($parts as $p) {
        $val = (int)trim($p);
        if ($val > 0 && $val !== $userId) {
            $ids[] = $val;
        }
    }
}

if (empty($ids)) {
    send_api_success(['relations' => []], "Nessun ID fornito.");
}

// Rimuove duplicati per sicurezza
$ids = array_unique($ids);

// Limitiamo la query batch a un massimo di 50 utenti per evitare congestione
if (count($ids) > 50) {
    $ids = array_slice($ids, 0, 50);
}

// Creiamo i segnaposto per la clausola IN (?, ?, ?, ...)
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$sql = "
    SELECT 
        u.id,
        EXISTS(SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = u.id) AS is_following,
        EXISTS(SELECT 1 FROM user_follows WHERE follower_id = u.id AND followed_id = ?) AS is_followed_by,
        EXISTS(SELECT 1 FROM friendships WHERE (user_one_id = LEAST(?, u.id) AND user_two_id = GREATEST(?, u.id))) AS is_friend,
        EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = ? AND receiver_id = u.id AND status = 'pending') AS request_sent,
        EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = u.id AND receiver_id = ? AND status = 'pending') AS request_received,
        EXISTS(SELECT 1 FROM blocked_users WHERE blocker_id = ? AND blocked_id = u.id) AS blocked_by_viewer,
        EXISTS(SELECT 1 FROM blocked_users WHERE blocker_id = u.id AND blocked_id = ?) AS blocked_viewer,
        s.profile_visibility,
        s.follow_permission,
        s.friend_request_permission,
        s.message_permission
    FROM utenti u
    LEFT JOIN user_social_settings s ON s.user_id = u.id
    WHERE u.id IN ($placeholders)
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    send_api_error("Errore di database.", "DATABASE_ERROR", 500);
}

// Costruiamo i tipi di parametri per bind_param
// I primi 8 parametri sono $userId (per le sottoquery di relazione)
// Seguiti dagli ID del batch nella clausola IN
$types = "iiiiiiii" . str_repeat("i", count($ids));
$bindParams = [
    $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId
];
foreach ($ids as $id) {
    $bindParams[] = $id;
}

$stmt->bind_param($types, ...$bindParams);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$relations = [];
foreach ($rows as $row) {
    $target = (int)$row['id'];
    
    // Default privacy settings se mancanti
    $visibility = $row['profile_visibility'] ?: 'public';
    $flPerm = $row['follow_permission'] ?: 'everyone';
    $frPerm = $row['friend_request_permission'] ?: 'everyone';
    $msgPerm = $row['message_permission'] ?: 'everyone';
    
    $isFollowing = (bool)$row['is_following'];
    $isFollowedBy = (bool)$row['is_followed_by'];
    $isFriend = (bool)$row['is_friend'];
    $requestSent = (bool)$row['request_sent'];
    $requestReceived = (bool)$row['request_received'];
    $blockedByViewer = (bool)$row['blocked_by_viewer'];
    $blockedViewer = (bool)$row['blocked_viewer'];
    
    // Calcolo permessi dinamici
    $canViewProfile = true;
    $canFollow = false;
    $canSendFriendRequest = false;
    $canMessage = false;
    
    if (!$blockedByViewer && !$blockedViewer) {
        // 1. Profilo
        if ($visibility === 'private' && !$isFriend) {
            $canViewProfile = false;
        }
        // 2. Follow
        if ($flPerm === 'everyone' || $flPerm === 'registered') {
            $canFollow = true;
        }
        // 3. Richiesta Amicizia
        if (!$isFriend && !$requestSent && !$requestReceived) {
            if ($frPerm === 'everyone') {
                $canSendFriendRequest = true;
            } elseif ($frPerm === 'followers' && $isFollowing) {
                $canSendFriendRequest = true;
            } elseif ($frPerm === 'following' && $isFollowedBy) {
                $canSendFriendRequest = true;
            } elseif ($frPerm === 'mutual_followers' && ($isFollowing && $isFollowedBy)) {
                $canSendFriendRequest = true;
            }
        }
        // 4. Messaggi
        if ($msgPerm === 'everyone') {
            $canMessage = true;
        } elseif ($msgPerm === 'followers' && $isFollowing) {
            $canMessage = true;
        } elseif ($msgPerm === 'following' && $isFollowedBy) {
            $canMessage = true;
        } elseif ($msgPerm === 'friends' && $isFriend) {
            $canMessage = true;
        }
    } else {
        $canViewProfile = false;
    }
    
    $relations[$target] = [
        'is_self' => false,
        'is_following' => $isFollowing,
        'is_followed_by' => $isFollowedBy,
        'is_mutual_follow' => ($isFollowing && $isFollowedBy),
        'is_friend' => $isFriend,
        'friend_request_sent' => $requestSent,
        'friend_request_received' => $requestReceived,
        'is_blocked_by_viewer' => $blockedByViewer,
        'has_blocked_viewer' => $blockedViewer,
        'can_follow' => $canFollow,
        'can_send_friend_request' => $canSendFriendRequest,
        'can_message' => $canMessage,
        'can_view_profile' => $canViewProfile
    ];
}

send_api_success(['relations' => $relations]);
?>
