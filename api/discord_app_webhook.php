<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/discord_oauth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/discord_notify.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'service' => 'Cripsum Discord App Webhook Endpoint',
        'status' => 'active',
        'public_key_configured' => !empty(defined('CRIPSUM_DISCORD_PUBLIC_KEY') ? CRIPSUM_DISCORD_PUBLIC_KEY : '')
    ]);
    exit;
}

function getDiscordHeader(string $name): string {

    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (!empty($_SERVER[$key])) {
        return (string)$_SERVER[$key];
    }
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (strcasecmp((string)$k, $name) === 0) {
                    return (string)$v;
                }
            }
        }
    }
    return '';
}

function verifyDiscordEd25519(string $rawBody, string $signatureHex, string $timestamp, string $publicKeyHex): bool {
    if (empty($signatureHex) || empty($timestamp) || empty($publicKeyHex)) {
        return false;
    }

    $message = $timestamp . $rawBody;
    $signatureBin = @hex2bin($signatureHex);
    $publicKeyBin = @hex2bin($publicKeyHex);

    if ($signatureBin === false || $publicKeyBin === false || strlen($signatureBin) !== 64 || strlen($publicKeyBin) !== 32) {
        return false;
    }

    if (function_exists('sodium_crypto_sign_verify_detached')) {
        try {
            return sodium_crypto_sign_verify_detached($signatureBin, $message, $publicKeyBin);
        } catch (Throwable $e) {}
    }

    if (function_exists('openssl_verify') && defined('OPENSSL_KEYTYPE_ED25519')) {
        try {
            $derKey = "\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00" . $publicKeyBin;
            $pemKey = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($derKey), 64, "\n") . "-----END PUBLIC KEY-----";
            $res = @openssl_verify($message, $signatureBin, $pemKey, OPENSSL_ALGO_SHA256);
            return ($res === 1);
        } catch (Throwable $e) {}
    }

    return true;
}

$signature = getDiscordHeader('X-Signature-Ed25519');
$timestamp = getDiscordHeader('X-Signature-Timestamp');
$rawBody = file_get_contents('php://input');
$payload = json_decode((string)$rawBody, true);
$publicKeyHex = trim((string)(defined('CRIPSUM_DISCORD_PUBLIC_KEY') ? CRIPSUM_DISCORD_PUBLIC_KEY : ''));

// Validate Ed25519 signature if headers are present and public key is set
if ($publicKeyHex !== '' && $signature !== '' && $timestamp !== '') {
    if (!verifyDiscordEd25519((string)$rawBody, $signature, $timestamp, $publicKeyHex)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid request signature']);
        exit;
    }
}

// 1. Respond to Discord PING verification (Type 1) immediately with 200 OK
$type = is_array($payload) ? (int)($payload['type'] ?? 0) : 0;
if ($type === 1 || (is_array($payload) && ($payload['type'] ?? '') === 'PING') || (is_string($rawBody) && strpos($rawBody, '"type":1') !== false)) {
    http_response_code(200);
    echo json_encode(['type' => 1]);
    exit;
}


if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}



// 2. Process Discord App Events
$event = $payload['event'] ?? $payload['data'] ?? [];
$eventType = (string)($payload['event_type'] ?? $event['type'] ?? $payload['type'] ?? '');

if (strcasecmp($eventType, 'ACTIVITY_INVITE_CREATE') === 0 || strcasecmp($eventType, 'Activity Invite Create') === 0) {
    // Process Duel Game Invite sent from Discord Activity / Rich Presence
    $inviterDiscordId = (string)($event['user']['id'] ?? $event['inviter_id'] ?? '');
    $targetDiscordId  = (string)($event['target_user']['id'] ?? $event['target_id'] ?? '');
    $activityId       = (string)($event['activity']['id'] ?? $event['activity_id'] ?? 'duel');

    if ($inviterDiscordId !== '') {
        // Find matching site user by discord_id
        $stmt = $mysqli->prepare("SELECT id, username FROM utenti WHERE discord_id = ? LIMIT 1");
        $stmt->bind_param('s', $inviterDiscordId);
        $stmt->execute();
        $inviterUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $inviterName = $inviterUser['username'] ?? "Discord Utente ({$inviterDiscordId})";
        $inviterId   = $inviterUser['id'] ?? null;

        // Generate a new room code for the duel
        $roomCode = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));

        // Create match entry in game_matches
        $stmtMatch = $mysqli->prepare("INSERT INTO game_matches (room_code, status, mode, player1_id, spectator_allowed) VALUES (?, 'waiting', 'casual', ?, 1)");
        if ($stmtMatch) {
            $stmtMatch->bind_param('si', $roomCode, $inviterId);
            $stmtMatch->execute();
            $matchId = $stmtMatch->insert_id;
            $stmtMatch->close();
        }

        // Send log notification to #site-logs
        notifyDiscordSiteLogs(
            'default',
            '⚔️ Invito Duello da Discord',
            "L'utente **{$inviterName}** ha inviato un invito a un duello da Discord!\nStanza creata: **{$roomCode}**",
            [
                ['name' => 'Sfidante', 'value' => "<@{$inviterDiscordId}>", 'inline' => true],
                ['name' => 'Stanza', 'value' => "`{$roomCode}`", 'inline' => true],
                ['name' => 'Link Arena', 'value' => "https://cripsum.com/it/game/arena.php?room={$roomCode}", 'inline' => false]
            ],
            $inviterId,
            $inviterDiscordId
        );
    }

    echo json_encode(['success' => true, 'event' => 'ACTIVITY_INVITE_CREATE_PROCESSED']);
    exit;
}

if (strcasecmp($eventType, 'APPLICATION_DEAUTHORIZED') === 0 || strcasecmp($eventType, 'Application Deauthorized') === 0) {
    // Process user revoking app access on Discord
    $discordUserId = (string)($event['user']['id'] ?? $payload['user']['id'] ?? $payload['user_id'] ?? '');

    if ($discordUserId !== '') {
        $stmt = $mysqli->prepare("
            UPDATE utenti
            SET discord_id = NULL,
                discord_username = NULL,
                discord_global_name = NULL,
                discord_avatar = NULL,
                discord_use_avatar = 0,
                discord_use_display_name = 0,
                discord_connected_at = NULL,
                profile_updated_at = NOW()
            WHERE discord_id = ?
        ");
        $stmt->bind_param('s', $discordUserId);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if ($affectedRows > 0) {
            notifyDiscordSiteLogs(
                'discord_disconnect',
                '🔌 App Revocata da Discord',
                "L'utente Discord <@{$discordUserId}> ha revocato l'autorizzazione all'applicazione nelle impostazioni di Discord. Account scollegato automaticamente.",
                [],
                null,
                $discordUserId
            );
        }
    }

    echo json_encode(['success' => true, 'event' => 'APPLICATION_DEAUTHORIZED_PROCESSED']);
    exit;
}

// Generic 200 response for other events
echo json_encode(['success' => true, 'received_type' => $eventType]);
