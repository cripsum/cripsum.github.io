<?php
// Keeps `utenti.discord_avatar` in sync with the user's real Discord avatar.
//
// The hash is only written when the user connects Discord, so it goes stale as
// soon as they change their picture: the CDN URL built from it returns 404 and
// the avatar shows up broken everywhere. This resolves the current hash from
// the presence API the front-end already uses, caches the answer off the web
// root, and writes it back so every other page picks it up too.

const DISCORD_AVATAR_TTL = 900;          // re-check a known hash every 15 min
const DISCORD_AVATAR_FAILURE_TTL = 600;  // back off 10 min after a failed lookup
const DISCORD_AVATAR_TIMEOUT = 2;        // seconds; this runs while serving an image

/** Path of the per-user cache file, outside the web root. */
function discord_avatar_cache_file(string $discordId): ?string
{
    if (!preg_match('/^\d{15,25}$/', $discordId)) {
        return null;
    }

    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cripsum_discord_avatars';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return null;
    }

    return $dir . DIRECTORY_SEPARATOR . $discordId . '.json';
}

/** Reads the cached lookup, or null when missing/expired/unreadable. */
function discord_avatar_cache_read(string $discordId): ?array
{
    $file = discord_avatar_cache_file($discordId);
    if ($file === null || !is_file($file)) {
        return null;
    }

    $raw = @file_get_contents($file);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['checked'])) {
        return null;
    }

    $age = time() - (int)$data['checked'];
    $ttl = empty($data['ok']) ? DISCORD_AVATAR_FAILURE_TTL : DISCORD_AVATAR_TTL;

    return $age < $ttl ? $data : null;
}

/** Stores a lookup result (success or failure) for the back-off window. */
function discord_avatar_cache_write(string $discordId, bool $ok, ?string $hash): void
{
    $file = discord_avatar_cache_file($discordId);
    if ($file === null) {
        return;
    }

    $payload = json_encode(['ok' => $ok, 'hash' => $hash, 'checked' => time()]);
    // Write to a temp file first so a concurrent reader never sees half a file.
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }
}

/**
 * Asks the presence API for the account's current avatar hash.
 *
 * The endpoints are Lanyard-compatible and public: no bot token is involved and
 * the URL is built from a validated snowflake, so nothing user-supplied reaches
 * the request.
 */
function discord_avatar_fetch_hash(string $discordId): ?string
{
    if (!function_exists('curl_init') || !preg_match('/^\d{15,25}$/', $discordId)) {
        return null;
    }

    $endpoints = ['https://api.cripsum.com/v1/users/', 'https://api.lanyard.rest/v1/users/'];

    foreach ($endpoints as $base) {
        $ch = curl_init($base . $discordId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => DISCORD_AVATAR_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => DISCORD_AVATAR_TIMEOUT,
            CURLOPT_USERAGENT => 'CripsumAvatarSync/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200 || !is_string($body) || $body === '') {
            continue;
        }

        $data = json_decode($body, true);
        $user = $data['data']['discord_user'] ?? null;
        if (!is_array($user) || (string)($user['id'] ?? '') !== $discordId) {
            continue;
        }

        $hash = trim((string)($user['avatar'] ?? ''));
        // An account on the default avatar reports null; that is a valid answer.
        if ($hash === '') {
            return '';
        }
        if (preg_match('/^(a_)?[a-f0-9]{16,64}$/i', $hash)) {
            return $hash;
        }
    }

    return null;
}

/**
 * Returns the freshest avatar hash known for the account, refreshing the stored
 * one when it changed. Falls back to $storedHash whenever the lookup is not
 * possible, so this can never make things worse than they already are.
 */
function discord_avatar_sync(mysqli $mysqli, int $userId, string $discordId, ?string $storedHash): ?string
{
    $storedHash = trim((string)$storedHash);
    if (!preg_match('/^\d{15,25}$/', $discordId)) {
        return $storedHash !== '' ? $storedHash : null;
    }

    // No writable cache means every single avatar request would hit the network.
    // Not worth it: fall back to whatever is stored.
    if (discord_avatar_cache_file($discordId) === null) {
        return $storedHash !== '' ? $storedHash : null;
    }

    $cached = discord_avatar_cache_read($discordId);
    if ($cached !== null) {
        if (empty($cached['ok'])) {
            return $storedHash !== '' ? $storedHash : null;
        }
        $hash = (string)($cached['hash'] ?? '');
        return $hash !== '' ? $hash : null;
    }

    $fresh = discord_avatar_fetch_hash($discordId);
    if ($fresh === null) {
        discord_avatar_cache_write($discordId, false, null);
        return $storedHash !== '' ? $storedHash : null;
    }

    discord_avatar_cache_write($discordId, true, $fresh);

    if ($fresh !== $storedHash) {
        $stmt = $mysqli->prepare("UPDATE utenti SET discord_avatar = ? WHERE id = ? LIMIT 1");
        if ($stmt) {
            $value = $fresh !== '' ? $fresh : null;
            $stmt->bind_param('si', $value, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    return $fresh !== '' ? $fresh : null;
}
