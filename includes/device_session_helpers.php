<?php

const AUTH_DEVICE_SESSION_LIFETIME = 604800;

function auth_device_sessions_available(mysqli $mysqli): bool
{
    return auth_table_exists($mysqli, 'user_sessions');
}

function auth_device_token_hash(string $token): string
{
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

function auth_register_device_session(mysqli $mysqli, int $userId): bool
{
    if ($userId <= 0 || !auth_device_sessions_available($mysqli)) {
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = auth_device_token_hash($token);
    $info = auth_device_request_info();
    $expiresAt = date('Y-m-d H:i:s', time() + AUTH_DEVICE_SESSION_LIFETIME);

    $stmt = $mysqli->prepare("
        INSERT INTO user_sessions
            (user_id, token_hash, device_name, device_type, browser, os, ip_address, user_agent, created_at, last_seen_at, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
    ");

    if (!$stmt) {
        return false;
    }

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
        $update = $mysqli->prepare('UPDATE user_sessions SET last_seen_at = NOW() WHERE id = ? AND user_id = ?');
        if ($update) {
            $sessionId = (int)$row['id'];
            $update->bind_param('ii', $sessionId, $userId);
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

    $stmt = $mysqli->prepare("
        SELECT id, token_hash, device_name, device_type, browser, os, ip_address,
               created_at, last_seen_at, expires_at
        FROM user_sessions
        WHERE user_id = ?
          AND revoked_at IS NULL
          AND expires_at > NOW()
        ORDER BY (token_hash = ?) DESC, last_seen_at DESC
    ");

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('is', $userId, $currentHash);
    $stmt->execute();
    $result = $stmt->get_result();
    $sessions = [];

    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['is_current'] = $currentHash !== '' && hash_equals($currentHash, (string)$row['token_hash']);
        unset($row['token_hash']);
        $sessions[] = $row;
    }

    $stmt->close();
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
