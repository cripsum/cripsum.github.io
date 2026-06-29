<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Recupera lo stato completo della relazione tra un visualizzatore (viewer) e un utente target.
 * Calcola anche i permessi dinamici basati su blocchi e preferenze di privacy.
 */
function getRelationshipStatus($mysqli, $viewerId, $targetId)
{
    $viewerId = (int)$viewerId;
    $targetId = (int)$targetId;

    $defaultStatus = [
        'is_self' => false,
        'is_following' => false,
        'is_followed_by' => false,
        'is_mutual_follow' => false,
        'is_friend' => false,
        'friend_request_sent' => false,
        'friend_request_received' => false,
        'is_blocked_by_viewer' => false,
        'has_blocked_viewer' => false,
        'can_follow' => false,
        'can_send_friend_request' => false,
        'can_message' => false,
        'can_view_profile' => true
    ];

    if ($viewerId === $targetId) {
        $defaultStatus['is_self'] = true;
        return $defaultStatus;
    }

    // Se l'utente non è loggato, i permessi sono limitati ma può comunque visualizzare se pubblico
    if ($viewerId <= 0) {
        $stmt = $mysqli->prepare("SELECT profile_visibility FROM user_social_settings WHERE user_id = ? LIMIT 1");
        $visibility = 'public';
        if ($stmt) {
            $stmt->bind_param("i", $targetId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $visibility = $row['profile_visibility'];
            }
            $stmt->close();
        }
        $defaultStatus['can_view_profile'] = ($visibility === 'public');
        return $defaultStatus;
    }

    // Ordine degli ID per la tabella friendships
    $userOne = min($viewerId, $targetId);
    $userTwo = max($viewerId, $targetId);

    $query = "
        SELECT 
            EXISTS(SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = ?) AS is_following,
            EXISTS(SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = ?) AS is_followed_by,
            EXISTS(SELECT 1 FROM friendships WHERE user_one_id = ? AND user_two_id = ?) AS is_friend,
            EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending') AS request_sent,
            EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending') AS request_received,
            EXISTS(SELECT 1 FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?) AS blocked_by_viewer,
            EXISTS(SELECT 1 FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?) AS blocked_viewer,
            s.profile_visibility,
            s.follow_permission,
            s.friend_request_permission,
            s.message_permission
        FROM user_social_settings s
        WHERE s.user_id = ?
    ";

    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        return $defaultStatus;
    }

    $stmt->bind_param(
        "iiiiiiiiiiiiiii",
        $viewerId, $targetId,     // is_following
        $targetId, $viewerId,     // is_followed_by
        $userOne, $userTwo,       // is_friend
        $viewerId, $targetId,     // request_sent
        $targetId, $viewerId,     // request_received
        $viewerId, $targetId,     // blocked_by_viewer
        $targetId, $viewerId,     // blocked_viewer
        $targetId                 // user_social_settings user_id
    );

    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();

    // Se l'utente di destinazione non ha ancora una riga di impostazioni (caso limite), usiamo i valori di default
    if (!$data) {
        $data = [
            'is_following' => 0,
            'is_followed_by' => 0,
            'is_friend' => 0,
            'request_sent' => 0,
            'request_received' => 0,
            'blocked_by_viewer' => 0,
            'blocked_viewer' => 0,
            'profile_visibility' => 'public',
            'follow_permission' => 'everyone',
            'friend_request_permission' => 'everyone',
            'message_permission' => 'everyone'
        ];
    }

    $status = [
        'is_self' => false,
        'is_following' => (bool)$data['is_following'],
        'is_followed_by' => (bool)$data['is_followed_by'],
        'is_mutual_follow' => ($data['is_following'] && $data['is_followed_by']),
        'is_friend' => (bool)$data['is_friend'],
        'friend_request_sent' => (bool)$data['request_sent'],
        'friend_request_received' => (bool)$data['request_received'],
        'is_blocked_by_viewer' => (bool)$data['blocked_by_viewer'],
        'has_blocked_viewer' => (bool)$data['blocked_viewer'],
        'can_follow' => false,
        'can_send_friend_request' => false,
        'can_message' => false,
        'can_view_profile' => true
    ];

    // Calcolo permessi dinamici
    if ($status['is_blocked_by_viewer'] || $status['has_blocked_viewer']) {
        $status['can_view_profile'] = false;
        $status['can_follow'] = false;
        $status['can_send_friend_request'] = false;
        $status['can_message'] = false;
        return $status;
    }

    // 1. Permesso Visualizzazione Profilo
    if ($data['profile_visibility'] === 'private' && !$status['is_friend']) {
        $status['can_view_profile'] = false;
    }

    // 2. Permesso Follow
    if ($data['follow_permission'] === 'everyone') {
        $status['can_follow'] = true;
    } elseif ($data['follow_permission'] === 'registered') {
        $status['can_follow'] = true; // ViewerId > 0 già verificato sopra
    }

    // 3. Permesso Inviare Richiesta di Amicizia
    if (!$status['is_friend'] && !$status['friend_request_sent'] && !$status['friend_request_received']) {
        $frPerm = $data['friend_request_permission'];
        if ($frPerm === 'everyone') {
            $status['can_send_friend_request'] = true;
        } elseif ($frPerm === 'followers' && $status['is_following']) {
            // Chi può inviarmi richieste: I miei follower (quindi il viewer deve seguire il target)
            $status['can_send_friend_request'] = true;
        } elseif ($frPerm === 'following' && $status['is_followed_by']) {
            // Chi può inviarmi richieste: Chi seguo (quindi il target deve seguire il viewer)
            $status['can_send_friend_request'] = true;
        } elseif ($frPerm === 'mutual_followers' && $status['is_mutual_follow']) {
            $status['can_send_friend_request'] = true;
        }
    }

    // 4. Permesso Messaggistica
    $msgPerm = $data['message_permission'];
    if ($msgPerm === 'everyone') {
        $status['can_message'] = true;
    } elseif ($msgPerm === 'followers' && $status['is_following']) {
        $status['can_message'] = true;
    } elseif ($msgPerm === 'following' && $status['is_followed_by']) {
        $status['can_message'] = true;
    } elseif ($msgPerm === 'friends' && $status['is_friend']) {
        $status['can_message'] = true;
    }

    return $status;
}

/**
 * Ottiene la lista degli amici in comune tra due utenti
 */
function getMutualFriends($mysqli, $userOneId, $userTwoId)
{
    $userOneId = (int)$userOneId;
    $userTwoId = (int)$userTwoId;

    $query = "
        SELECT u.id, u.username, u.ruolo, u.is_premium
        FROM utenti u
        WHERE u.id IN (
            SELECT IF(f1.user_one_id = ?, f1.user_two_id, f1.user_one_id) 
            FROM friendships f1 
            WHERE f1.user_one_id = ? OR f1.user_two_id = ?
        )
        AND u.id IN (
            SELECT IF(f2.user_one_id = ?, f2.user_two_id, f2.user_one_id) 
            FROM friendships f2 
            WHERE f2.user_one_id = ? OR f2.user_two_id = ?
        )
        AND u.id NOT IN (?, ?)
    ";

    $stmt = $mysqli->prepare($query);
    if (!$stmt) return [];

    $stmt->bind_param(
        "iiiiiiii",
        $userOneId, $userOneId, $userOneId,
        $userTwoId, $userTwoId, $userTwoId,
        $userOneId, $userTwoId
    );
    $stmt->execute();
    $res = $stmt->get_result();
    $friends = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $friends;
}

/**
 * Invia una notifica interna (inbox) all'utente usando il sistema di messaggi esistente
 */
function sendSocialNotification($mysqli, $recipientId, $titleIt, $titleEn, $contentIt, $contentEn)
{
    // Riusiamo la funzione interna di Cripsum definita in includes/functions.php
    if (function_exists('sendSecurityInboxMessage')) {
        return sendSecurityInboxMessage($mysqli, $recipientId, $titleIt, $titleEn, $contentIt, $contentEn, 'social');
    }
    return false;
}
?>
