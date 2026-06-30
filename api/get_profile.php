<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

$identifier = profile_get_identifier();
if (!$identifier) {
    profile_json_response(['ok' => false, 'message' => 'Identificativo mancante.'], 400);
}

$profile = profile_get_public_profile($mysqli, $identifier);
if (!$profile) {
    profile_json_response(['ok' => false, 'message' => 'Profilo non trovato.'], 404);
}

$profileId = (int)$profile['id'];
$canEdit = profile_can_edit($profileId);
$isLoggedIn = isLoggedIn();

if ($profile['profile_visibility'] === 'private' && !$canEdit) {
    profile_json_response(['ok' => false, 'message' => 'Profilo privato.'], 403);
}

if ($profile['profile_visibility'] === 'friends' && !$canEdit) {
    $isFriend = false;
    if ($isLoggedIn) {
        $currentUserId = (int)$_SESSION['user_id'];
        $userOne = min($currentUserId, $profileId);
        $userTwo = max($currentUserId, $profileId);
        $stmtF = $mysqli->prepare("SELECT 1 FROM friendships WHERE user_one_id = ? AND user_two_id = ? LIMIT 1");
        if ($stmtF) {
            $stmtF->bind_param("ii", $userOne, $userTwo);
            $stmtF->execute();
            $resF = $stmtF->get_result();
            if ($resF->num_rows > 0) {
                $isFriend = true;
            }
            $stmtF->close();
        }
    }
    if (!$isFriend) {
        profile_json_response(['ok' => false, 'message' => 'Profilo visibile solo agli amici.'], 403);
    }
}

if ($profile['profile_visibility'] === 'logged_in' && !$isLoggedIn) {
    profile_json_response(['ok' => false, 'message' => 'Devi accedere per vedere questo profilo.'], 401);
}

$stamp = !empty($profile['profile_updated_at']) ? (int)strtotime((string)$profile['profile_updated_at']) : time();

profile_json_response([
    'ok' => true,
    'profile' => [
        'id' => $profileId,
        'username' => $profile['username'],
        'display_name' => $profile['display_name'] ?: $profile['username'],
        'bio' => $profile['bio'],
        'avatar_url' => '/includes/get_pfp.php?id=' . $profileId . '&t=' . $stamp,
        'banner_url' => !empty($profile['profile_banner_type']) ? '/includes/get_profile_banner.php?id=' . $profileId . '&t=' . $stamp : null,
        'accent_color' => profile_normalize_hex_color($profile['accent_color'] ?? '#0f5bff'),
        'theme' => $profile['profile_theme'],
        'layout' => $profile['profile_layout'],
        'visibility' => $profile['profile_visibility'],
        'discord_id' => $canEdit ? ($profile['discord_id'] ?? null) : (!empty($profile['discord_id']) ? 'connected' : null),
        'views' => (int)$profile['profile_views'],
        'stats' => [
            'achievements' => (int)$profile['num_achievement'],
            'characters' => (int)$profile['num_personaggi'],
            'coins' => (int)$profile['soldi'],
            'role' => $profile['ruolo'],
        ],
        'socials' => profile_list_socials($mysqli, $profileId, true),
        'links' => profile_list_links($mysqli, $profileId, true),
        'projects' => profile_list_projects($mysqli, $profileId, true),
        'contents' => profile_list_contents($mysqli, $profileId, true),
        'badges' => profile_list_visible_badges($mysqli, $profileId),
        'activity' => profile_recent_activity($mysqli, $profileId),
    ],
]);
