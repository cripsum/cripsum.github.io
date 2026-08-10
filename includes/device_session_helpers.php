<?php

const AUTH_DEVICE_SESSION_LIFETIME = 604800;
const AUTH_DEVICE_GEO_SUCCESS_TTL = 2592000; // 30 days
const AUTH_DEVICE_GEO_FAILURE_TTL = 21600;   // retry failures after 6 hours
const AUTH_DEVICE_GEO_LOOKUPS_PER_REQUEST = 3;

function auth_device_sessions_available(mysqli $mysqli): bool
{
    return auth_table_exists($mysqli, 'user_sessions');
}

function auth_device_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function auth_device_key_supported(mysqli $mysqli): bool
{
    return auth_column_exists($mysqli, 'user_sessions', 'device_key_hash');
}

function auth_device_persistent_key(): string
{
    $params = session_get_cookie_params();
    $secure = !empty($params['secure']);
    $cookieName = $secure ? '__Host-cripsum_device' : 'cripsum_device_dev';
    $token = (string)($_COOKIE[$cookieName] ?? '');

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $token = bin2hex(random_bytes(32));
        setcookie($cookieName, $token, [
            'expires' => time() + 31536000,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$cookieName] = $token;
    }

    // This is only a stable browser label. It is not accepted as proof of
    // authentication, and only its SHA-256 hash is stored in the database.
    return hash('sha256', $token);
}

function auth_device_request_info(): array
{
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Dispositivo sconosciuto'), 0, 500);

    $browser = 'Browser sconosciuto';
    if (preg_match('/Edg(?:A|iOS)?\/([\d.]+)/i', $userAgent, $match)) {
        $browser = 'Microsoft Edge ' . $match[1];
    } elseif (preg_match('/OPR\/([\d.]+)/i', $userAgent, $match)) {
        $browser = 'Opera ' . $match[1];
    } elseif (preg_match('/SamsungBrowser\/([\d.]+)/i', $userAgent, $match)) {
        $browser = 'Samsung Internet ' . $match[1];
    } elseif (preg_match('/(?:Chrome|CriOS)\/([\d.]+)/i', $userAgent, $match)) {
        $browser = 'Chrome ' . $match[1];
    } elseif (preg_match('/(?:Firefox|FxiOS)\/([\d.]+)/i', $userAgent, $match)) {
        $browser = 'Firefox ' . $match[1];
    } elseif (preg_match('/Version\/([\d.]+).*Safari/i', $userAgent, $match)) {
        $browser = 'Safari ' . $match[1];
    }

    $os = 'Sistema sconosciuto';
    if (preg_match('/Windows NT 10\.0/i', $userAgent)) {
        $os = 'Windows 10/11';
    } elseif (preg_match('/Windows NT 6\.3/i', $userAgent)) {
        $os = 'Windows 8.1';
    } elseif (preg_match('/Windows NT 6\.1/i', $userAgent)) {
        $os = 'Windows 7';
    } elseif (preg_match('/iPad.*OS ([\d_]+)/i', $userAgent, $match)) {
        $os = 'iPadOS ' . str_replace('_', '.', $match[1]);
    } elseif (preg_match('/iPhone OS ([\d_]+)/i', $userAgent, $match)) {
        $os = 'iOS ' . str_replace('_', '.', $match[1]);
    } elseif (preg_match('/Android\s+([\d.]+)/i', $userAgent, $match)) {
        $os = 'Android ' . $match[1];
    } elseif (preg_match('/Mac OS X\s+([\d_]+)/i', $userAgent, $match)) {
        $os = 'macOS ' . str_replace('_', '.', $match[1]);
    } elseif (preg_match('/CrOS/i', $userAgent)) {
        $os = 'ChromeOS';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $os = 'Linux';
    }

    $deviceType = 'desktop';
    if (preg_match('/iPad|Tablet|Nexus 7|Nexus 10/i', $userAgent)) {
        $deviceType = 'tablet';
    } elseif (preg_match('/Mobile|Android|iPhone|iPod/i', $userAgent)) {
        $deviceType = 'mobile';
    }

    $browserName = trim(preg_replace('/\s+[\d.]+$/', '', $browser));
    $deviceName = $browserName . ' · ' . $os;

    return [
        'device_name' => substr($deviceName, 0, 120),
        'device_type' => $deviceType,
        'browser' => substr($browser, 0, 100),
        'os' => substr($os, 0, 100),
        'ip_address' => auth_client_ip(),
        'user_agent' => $userAgent,
    ];
}

function auth_device_location_cache_available(mysqli $mysqli): bool
{
    return auth_table_exists($mysqli, 'ip_geolocation_cache');
}

function auth_device_location_row(array $row): ?array
{
    if (empty($row['lookup_success'])) {
        return null;
    }

    return [
        'city' => (string)($row['city'] ?? ''),
        'region' => (string)($row['region_name'] ?? ''),
        'country' => (string)($row['country_name'] ?? ''),
        'country_code' => strtoupper((string)($row['country_code'] ?? '')),
        'timezone' => (string)($row['timezone_name'] ?? ''),
        'network' => (string)($row['network_name'] ?? ''),
        'is_local' => false,
    ];
}

function auth_device_store_location_cache(mysqli $mysqli, string $ip, ?array $location): void
{
    $city = mb_substr(trim((string)($location['city'] ?? '')), 0, 100);
    $region = mb_substr(trim((string)($location['region'] ?? '')), 0, 120);
    $country = mb_substr(trim((string)($location['country'] ?? '')), 0, 120);
    $countryCode = mb_substr(strtoupper(trim((string)($location['country_code'] ?? ''))), 0, 2);
    $timezone = mb_substr(trim((string)($location['timezone'] ?? '')), 0, 80);
    $network = mb_substr(trim((string)($location['network'] ?? '')), 0, 160);
    $success = $location === null ? 0 : 1;

    $stmt = $mysqli->prepare("
        INSERT INTO ip_geolocation_cache
            (ip_address, city, region_name, country_name, country_code, timezone_name, network_name, lookup_success, fetched_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            city = VALUES(city),
            region_name = VALUES(region_name),
            country_name = VALUES(country_name),
            country_code = VALUES(country_code),
            timezone_name = VALUES(timezone_name),
            network_name = VALUES(network_name),
            lookup_success = VALUES(lookup_success),
            fetched_at = NOW()
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('sssssssi', $ip, $city, $region, $country, $countryCode, $timezone, $network, $success);
    $stmt->execute();
    $stmt->close();

    $mysqli->query("DELETE FROM ip_geolocation_cache WHERE fetched_at < DATE_SUB(NOW(), INTERVAL 90 DAY) LIMIT 100");
}

function auth_device_location_for_ip(mysqli $mysqli, string $ip): ?array
{
    static $memo = [];
    static $remoteLookups = 0;

    $ip = trim($ip);
    if (array_key_exists($ip, $memo)) {
        return $memo[$ip];
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return $memo[$ip] = null;
    }

    $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    if (!$isPublic) {
        return $memo[$ip] = [
            'city' => '',
            'region' => '',
            'country' => '',
            'country_code' => '',
            'timezone' => '',
            'network' => '',
            'is_local' => true,
        ];
    }

    if (!auth_device_location_cache_available($mysqli)) {
        return $memo[$ip] = null;
    }

    $cached = null;
    $stmt = $mysqli->prepare("
        SELECT city, region_name, country_name, country_code, timezone_name,
               network_name, lookup_success, fetched_at
        FROM ip_geolocation_cache
        WHERE ip_address = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $cached = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }

    if ($cached) {
        $age = time() - (int)strtotime((string)$cached['fetched_at']);
        $ttl = !empty($cached['lookup_success']) ? AUTH_DEVICE_GEO_SUCCESS_TTL : AUTH_DEVICE_GEO_FAILURE_TTL;
        if ($age >= 0 && $age < $ttl) {
            return $memo[$ip] = auth_device_location_row($cached);
        }
    }

    if ($remoteLookups >= AUTH_DEVICE_GEO_LOOKUPS_PER_REQUEST || !function_exists('curl_init')) {
        return $memo[$ip] = auth_device_location_row($cached ?? []);
    }
    $remoteLookups++;

    $curl = curl_init('https://ipwho.is/' . rawurlencode($ip));
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'Cripsum-Device-Security/1.0',
    ]);
    $body = curl_exec($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $payload = is_string($body) && strlen($body) <= 131072 ? json_decode($body, true) : null;
    if ($httpCode !== 200 || !is_array($payload) || empty($payload['success'])) {
        auth_device_store_location_cache($mysqli, $ip, null);
        return $memo[$ip] = null;
    }

    $connection = is_array($payload['connection'] ?? null) ? $payload['connection'] : [];
    $timezoneData = is_array($payload['timezone'] ?? null) ? $payload['timezone'] : [];
    $location = [
        'city' => (string)($payload['city'] ?? ''),
        'region' => (string)($payload['region'] ?? ''),
        'country' => (string)($payload['country'] ?? ''),
        'country_code' => (string)($payload['country_code'] ?? ''),
        'timezone' => (string)($timezoneData['id'] ?? ''),
        'network' => (string)($connection['isp'] ?? ($connection['org'] ?? '')),
        'is_local' => false,
    ];

    auth_device_store_location_cache($mysqli, $ip, $location);
    return $memo[$ip] = $location;
}

function auth_register_device_session(mysqli $mysqli, int $userId): bool
{
    if ($userId <= 0 || !auth_device_sessions_available($mysqli)) {
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = auth_device_token_hash($token);
    $info = auth_device_request_info();
    $expiresAt = date('Y-m-d H:i:s', time() + AUTH_DEVICE_SESSION_LIFETIME);

    if (auth_device_key_supported($mysqli)) {
        $deviceKeyHash = auth_device_persistent_key();
        $legacyCleanup = $mysqli->prepare("
            UPDATE user_sessions
            SET revoked_at = NOW()
            WHERE user_id = ?
              AND device_key_hash IS NULL
              AND device_name = ?
              AND os = ?
              AND ip_address = ?
              AND revoked_at IS NULL
        ");
        if ($legacyCleanup) {
            $legacyCleanup->bind_param(
                'isss',
                $userId,
                $info['device_name'],
                $info['os'],
                $info['ip_address']
            );
            $legacyCleanup->execute();
            $legacyCleanup->close();
        }

        $stmt = $mysqli->prepare("
            INSERT INTO user_sessions
                (user_id, token_hash, device_key_hash, device_name, device_type, browser, os, ip_address, user_agent, created_at, last_seen_at, expires_at, revoked_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, NULL)
            ON DUPLICATE KEY UPDATE
                token_hash = VALUES(token_hash),
                device_name = VALUES(device_name),
                device_type = VALUES(device_type),
                browser = VALUES(browser),
                os = VALUES(os),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                created_at = NOW(),
                last_seen_at = NOW(),
                expires_at = VALUES(expires_at),
                revoked_at = NULL
        ");
    } else {
        // Compatibility fallback before the migration is applied: retire old
        // rows matching the same device, operating system and IP before creating the new one.
        $cleanup = $mysqli->prepare("
            UPDATE user_sessions
            SET revoked_at = NOW()
            WHERE user_id = ?
              AND device_name = ?
              AND os = ?
              AND ip_address = ?
              AND revoked_at IS NULL
        ");
        if ($cleanup) {
            $cleanup->bind_param(
                'isss',
                $userId,
                $info['device_name'],
                $info['os'],
                $info['ip_address']
            );
            $cleanup->execute();
            $cleanup->close();
        }

        $stmt = $mysqli->prepare("
            INSERT INTO user_sessions
                (user_id, token_hash, device_name, device_type, browser, os, ip_address, user_agent, created_at, last_seen_at, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
        ");
    }

    if (!$stmt) {
        return false;
    }

    if (isset($deviceKeyHash)) {
        $stmt->bind_param(
            'isssssssss',
            $userId,
            $tokenHash,
            $deviceKeyHash,
            $info['device_name'],
            $info['device_type'],
            $info['browser'],
            $info['os'],
            $info['ip_address'],
            $info['user_agent'],
            $expiresAt
        );
    } else {
        $stmt->bind_param(
            'issssssss',
            $userId,
            $tokenHash,
            $info['device_name'],
            $info['device_type'],
            $info['browser'],
            $info['os'],
            $info['ip_address'],
            $info['user_agent'],
            $expiresAt
        );
    }

    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $_SESSION['auth_device_token'] = $token;
        $_SESSION['auth_device_last_sync'] = time();
    }

    return $ok;
}

function auth_destroy_local_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function auth_sync_current_device_session(mysqli $mysqli): bool
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0 || !auth_device_sessions_available($mysqli)) {
        return true;
    }

    $token = (string)($_SESSION['auth_device_token'] ?? '');

    // Adotta in modo trasparente le sessioni aperte prima dell'introduzione del registro dispositivi.
    if ($token === '') {
        return auth_register_device_session($mysqli, $userId);
    }

    $tokenHash = auth_device_token_hash($token);
    $stmt = $mysqli->prepare("
        SELECT id
        FROM user_sessions
        WHERE user_id = ?
          AND token_hash = ?
          AND revoked_at IS NULL
          AND expires_at > NOW()
        LIMIT 1
    ");

    if (!$stmt) {
        return true;
    }

    $stmt->bind_param('is', $userId, $tokenHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        auth_destroy_local_session();
        return false;
    }

    if (time() - (int)($_SESSION['auth_device_last_sync'] ?? 0) >= 60) {
        $info = auth_device_request_info();
        $update = $mysqli->prepare("
            UPDATE user_sessions
            SET last_seen_at = NOW(),
                device_name = ?,
                device_type = ?,
                browser = ?,
                os = ?,
                ip_address = ?,
                user_agent = ?
            WHERE id = ? AND user_id = ?
        ");
        if ($update) {
            $sessionId = (int)$row['id'];
            $update->bind_param(
                'ssssssii',
                $info['device_name'],
                $info['device_type'],
                $info['browser'],
                $info['os'],
                $info['ip_address'],
                $info['user_agent'],
                $sessionId,
                $userId
            );
            $update->execute();
            $update->close();
        }
        $_SESSION['auth_device_last_sync'] = time();
    }

    return true;
}

function auth_get_device_sessions(mysqli $mysqli, int $userId): array
{
    if ($userId <= 0 || !auth_device_sessions_available($mysqli)) {
        return [];
    }

    $currentHash = !empty($_SESSION['auth_device_token'])
        ? auth_device_token_hash((string)$_SESSION['auth_device_token'])
        : '';
    $deviceKeySelect = auth_device_key_supported($mysqli)
        ? 'device_key_hash'
        : 'NULL AS device_key_hash';

    $stmt = $mysqli->prepare("
        SELECT id, token_hash, {$deviceKeySelect}, device_name, device_type, browser, os, ip_address,
               created_at, last_seen_at, expires_at
        FROM user_sessions
        WHERE user_id = ?
          AND revoked_at IS NULL
          AND expires_at > NOW()
        ORDER BY (token_hash = ?) DESC, (device_key_hash IS NOT NULL) DESC, last_seen_at DESC
    ");

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('is', $userId, $currentHash);
    $stmt->execute();
    $result = $stmt->get_result();
    $sessions = [];
    $seenDevices = [];
    $seenLegacyFingerprints = [];
    $duplicateIds = [];

    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['is_current'] = $currentHash !== '' && hash_equals($currentHash, (string)$row['token_hash']);

        $deviceKeyHash = trim((string)($row['device_key_hash'] ?? ''));
        $legacyFingerprint = hash('sha256', implode('|', [
            (string)($row['device_name'] ?? ''),
            (string)($row['os'] ?? ''),
            (string)($row['ip_address'] ?? ''),
        ]));
        $dedupeKey = $deviceKeyHash !== ''
            ? 'device:' . $deviceKeyHash
            : 'legacy:' . $legacyFingerprint;

        if (isset($seenDevices[$dedupeKey]) || ($deviceKeyHash === '' && isset($seenLegacyFingerprints[$legacyFingerprint]))) {
            $duplicateIds[] = $row['id'];
            continue;
        }
        $seenDevices[$dedupeKey] = true;
        $seenLegacyFingerprints[$legacyFingerprint] = true;

        unset($row['token_hash'], $row['device_key_hash']);
        $sessions[] = $row;
    }

    $stmt->close();

    if ($duplicateIds) {
        $duplicateIdList = implode(',', array_map('intval', $duplicateIds));
        $cleanup = $mysqli->prepare("
            UPDATE user_sessions
            SET revoked_at = NOW()
            WHERE user_id = ?
              AND revoked_at IS NULL
              AND id IN ({$duplicateIdList})
        ");
        if ($cleanup) {
            $cleanup->bind_param('i', $userId);
            $cleanup->execute();
            $cleanup->close();
        }
    }

    foreach ($sessions as &$session) {
        $session['location'] = auth_device_location_for_ip($mysqli, (string)($session['ip_address'] ?? ''));
    }
    unset($session);

    return $sessions;
}

function auth_revoke_device_session(mysqli $mysqli, int $userId, int $sessionId): bool
{
    if ($userId <= 0 || $sessionId <= 0 || !auth_device_sessions_available($mysqli)) {
        return false;
    }

    $currentHash = !empty($_SESSION['auth_device_token'])
        ? auth_device_token_hash((string)$_SESSION['auth_device_token'])
        : '';

    $stmt = $mysqli->prepare("
        UPDATE user_sessions
        SET revoked_at = NOW()
        WHERE id = ?
          AND user_id = ?
          AND revoked_at IS NULL
          AND token_hash <> ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('iis', $sessionId, $userId, $currentHash);
    $stmt->execute();
    $revoked = $stmt->affected_rows === 1;
    $stmt->close();

    return $revoked;
}

function auth_revoke_other_device_sessions(mysqli $mysqli, int $userId): int
{
    if ($userId <= 0 || !auth_device_sessions_available($mysqli)) {
        return 0;
    }

    $currentHash = !empty($_SESSION['auth_device_token'])
        ? auth_device_token_hash((string)$_SESSION['auth_device_token'])
        : '';

    $stmt = $mysqli->prepare("
        UPDATE user_sessions
        SET revoked_at = NOW()
        WHERE user_id = ?
          AND revoked_at IS NULL
          AND token_hash <> ?
    ");

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('is', $userId, $currentHash);
    $stmt->execute();
    $count = max(0, $stmt->affected_rows);
    $stmt->close();

    return $count;
}

function auth_revoke_all_device_sessions(mysqli $mysqli, int $userId): int
{
    if ($userId <= 0 || !auth_device_sessions_available($mysqli)) {
        return 0;
    }

    $stmt = $mysqli->prepare("
        UPDATE user_sessions
        SET revoked_at = NOW()
        WHERE user_id = ?
          AND revoked_at IS NULL
    ");

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $count = max(0, $stmt->affected_rows);
    $stmt->close();

    return $count;
}

function auth_revoke_current_device_session(mysqli $mysqli): void
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $token = (string)($_SESSION['auth_device_token'] ?? '');

    if ($userId <= 0 || $token === '' || !auth_device_sessions_available($mysqli)) {
        return;
    }

    $tokenHash = auth_device_token_hash($token);
    $stmt = $mysqli->prepare("
        UPDATE user_sessions
        SET revoked_at = NOW()
        WHERE user_id = ? AND token_hash = ? AND revoked_at IS NULL
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param('is', $userId, $tokenHash);
        $stmt->execute();
        $stmt->close();
    }
}
