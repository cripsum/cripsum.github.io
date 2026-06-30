<?php
require_once __DIR__ . '/bootstrap.php';

$targetId = isset($_GET['target_id']) ? (int)$_GET['target_id'] : 0;
$targetUsername = isset($_GET['username']) ? trim((string)$_GET['username']) : '';

if (!$targetId && $targetUsername === '') {
    send_api_error("Specificare l'ID o l'username dell'utente.", "INVALID_INPUT");
}

// 1. Troviamo l'utente nel database
if ($targetId > 0) {
    $stmt = $mysqli->prepare("
        SELECT id, username, display_name, ruolo, is_premium, bio, ultimo_accesso,
               TIMESTAMPDIFF(SECOND, ultimo_accesso, NOW()) AS seconds_since_active,
               accent_color, profile_secondary_color, profile_card_color, profile_text_color,
               profile_card_opacity, profile_card_blur, profile_font, profile_border_color, 
               profile_border_width, profile_border_opacity, avatar_ring_enabled, 
               avatar_ring_style, avatar_ring_color, profile_ui_shape
        FROM utenti WHERE id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $targetId);
} else {
    $stmt = $mysqli->prepare("
        SELECT id, username, display_name, ruolo, is_premium, bio, ultimo_accesso,
               TIMESTAMPDIFF(SECOND, ultimo_accesso, NOW()) AS seconds_since_active,
               accent_color, profile_secondary_color, profile_card_color, profile_text_color,
               profile_card_opacity, profile_card_blur, profile_font, profile_border_color, 
               profile_border_width, profile_border_opacity, avatar_ring_enabled, 
               avatar_ring_style, avatar_ring_color, profile_ui_shape
        FROM utenti WHERE username = ? LIMIT 1
    ");
    $stmt->bind_param("s", $targetUsername);
}

if (!$stmt) {
    send_api_error("Errore di database.", "DATABASE_ERROR", 500);
}

$stmt->execute();
$userProfile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userProfile) {
    send_api_error("Utente non trovato.", "USER_NOT_FOUND", 404);
}

$targetId = (int)$userProfile['id'];

// 2. Calcoliamo lo stato della relazione con il visualizzatore
$relationship = getRelationshipStatus($mysqli, $userId, $targetId);

// 3. Calcoliamo le statistiche social
// Followers
$stmtFollowers = $mysqli->prepare("SELECT COUNT(*) FROM user_follows WHERE followed_id = ?");
$stmtFollowers->bind_param("i", $targetId);
$stmtFollowers->execute();
$stmtFollowers->bind_result($followersCount);
$stmtFollowers->fetch();
$stmtFollowers->close();

// Following
$stmtFollowing = $mysqli->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ?");
$stmtFollowing->bind_param("i", $targetId);
$stmtFollowing->execute();
$stmtFollowing->bind_result($followingCount);
$stmtFollowing->fetch();
$stmtFollowing->close();

// Amici
$stmtFriends = $mysqli->prepare("SELECT COUNT(*) FROM friendships WHERE user_one_id = ? OR user_two_id = ?");
$stmtFriends->bind_param("ii", $targetId, $targetId);
$stmtFriends->execute();
$stmtFriends->bind_result($friendsCount);
$stmtFriends->fetch();
$stmtFriends->close();

// 4. Calcoliamo gli amici in comune
$mutualFriends = getMutualFriends($mysqli, $userId, $targetId);

// 5. Formattiamo l'online status
$secSince = $userProfile['seconds_since_active'];
$isOnline = ($secSince !== null && $secSince < 180);

$data = [
    'id' => $targetId,
    'username' => $userProfile['username'],
    'display_name' => $userProfile['display_name'] ?: $userProfile['username'],
    'ruolo' => $userProfile['ruolo'],
    'is_premium' => (bool)$userProfile['is_premium'],
    'bio' => $userProfile['bio'] ?: '',
    'is_online' => $isOnline,
    'last_seen' => $isOnline ? null : $userProfile['ultimo_accesso'],
    'stats' => [
        'followers_count' => $followersCount,
        'following_count' => $followingCount,
        'friends_count' => $friendsCount,
        'mutual_friends_count' => count($mutualFriends)
    ],
    'mutual_friends' => array_slice($mutualFriends, 0, 5), // Mostriamo al massimo i primi 5 amici in comune
    'relationship' => $relationship,
    'style' => [
        'accent_color' => $userProfile['accent_color'],
        'secondary_color' => $userProfile['profile_secondary_color'],
        'card_color' => $userProfile['profile_card_color'],
        'text_color' => $userProfile['profile_text_color'],
        'card_opacity' => $userProfile['profile_card_opacity'],
        'card_blur' => $userProfile['profile_card_blur'],
        'font' => $userProfile['profile_font'],
        'border_color' => $userProfile['profile_border_color'],
        'border_width' => $userProfile['profile_border_width'],
        'border_opacity' => $userProfile['profile_border_opacity'],
        'avatar_ring_enabled' => (bool)$userProfile['avatar_ring_enabled'],
        'avatar_ring_style' => $userProfile['avatar_ring_style'],
        'avatar_ring_color' => $userProfile['avatar_ring_color'],
        'ui_shape' => $userProfile['profile_ui_shape']
    ]
];

send_api_success($data, "Dati carta utente caricati.");
?>
