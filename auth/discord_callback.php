<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../config/discord_oauth.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    header('Location: /en/accedi');
    exit;
}

$code = trim((string)($_GET['code'] ?? ''));
$state = trim((string)($_GET['state'] ?? ''));
$savedState = $_SESSION['discord_oauth_state'] ?? '';
$targetUserId = (int)($_SESSION['discord_oauth_target_user_id'] ?? $_SESSION['user_id']);

unset($_SESSION['discord_oauth_state'], $_SESSION['discord_oauth_target_user_id']);

if ($code === '' || $state === '' || !hash_equals((string)$savedState, $state)) {
    $_SESSION['profile_flash_error'] = 'Invalid Discord OAuth state.';
    header('Location: /en/edit-profile.php');
    exit;
}

if (!profile_can_edit($targetUserId)) {
    http_response_code(403);
    exit('Access denied.');
}

function cripsum_discord_request(string $url, array $options = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
    ] + $options);

    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException($error ?: 'Discord request failed.');
    }

    $json = json_decode((string)$body, true);
    if (!is_array($json)) throw new RuntimeException('Invalid Discord response.');
    return $json;
}

try {
    $token = cripsum_discord_request('https://discord.com/api/v10/oauth2/token', [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id' => CRIPSUM_DISCORD_CLIENT_ID,
            'client_secret' => CRIPSUM_DISCORD_CLIENT_SECRET,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => CRIPSUM_DISCORD_REDIRECT_URI,
        ]),
    ]);

    $accessToken = (string)($token['access_token'] ?? '');
    if ($accessToken === '') throw new RuntimeException('Missing Discord token.');

    $user = cripsum_discord_request('https://discord.com/api/v10/users/@me', [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
    ]);

    $discordId = trim((string)($user['id'] ?? ''));
    if (!profile_is_valid_discord_id($discordId) || $discordId === '') {
        throw new RuntimeException('Invalid Discord ID.');
    }

    $discordUsername = profile_clean_text((string)($user['username'] ?? ''), 64);
    $discordGlobalName = profile_clean_text((string)($user['global_name'] ?? ''), 80);
    $discordAvatar = profile_clean_text((string)($user['avatar'] ?? ''), 128);

    $stmt = $mysqli->prepare("\n        UPDATE utenti\n        SET discord_id = ?,\n            discord_username = ?,\n            discord_global_name = ?,\n            discord_avatar = ?,\n            discord_connected_at = NOW(),\n            profile_updated_at = NOW()\n        WHERE id = ?\n    ");
    $stmt->bind_param('ssssi', $discordId, $discordUsername, $discordGlobalName, $discordAvatar, $targetUserId);
    if (!$stmt->execute()) throw new RuntimeException('Failed to save Discord information.');
    $stmt->close();

    profile_record_activity($mysqli, $targetUserId, 'discord', 'Connected Discord');
    $_SESSION['profile_flash_success'] = 'Discord connected.';
} catch (Throwable $e) {
    $_SESSION['profile_flash_error'] = $e->getMessage();
}

$redirect = '/en/edit-profile.php' . (profile_is_staff() && $targetUserId !== (int)$_SESSION['user_id'] ? '?user_id=' . $targetUserId : '');
header('Location: ' . $redirect);
exit;
