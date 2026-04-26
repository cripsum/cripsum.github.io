<?php
if (!defined('ADMIN_V2_LOADED')) {
    define('ADMIN_V2_LOADED', true);
}

function admin_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_fail(string $message, int $status = 400, array $extra = []): void
{
    admin_json(array_merge(['ok' => false, 'message' => $message], $extra), $status);
}

function admin_ok(array $data = []): void
{
    admin_json(array_merge(['ok' => true], $data));
}

function admin_read_input(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '{}', true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function admin_validate_csrf(?string $token = null): bool
{
    $token = $token ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    return is_string($token) && $token !== '' && hash_equals($_SESSION['admin_csrf_token'] ?? '', $token);
}

function admin_table_exists(mysqli $mysqli, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return $cache[$table] = false;

    try {
        $stmt = $mysqli->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = ? LIMIT 1");
        if (!$stmt) return $cache[$table] = false;
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();
        return $cache[$table] = $exists;
    } catch (Throwable $e) {
        return $cache[$table] = false;
    }
}

function admin_column_exists(mysqli $mysqli, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];

    // Alcune colonne del progetto usano accenti, es. `quantità` e `rarità`.
    // La vecchia regex le scartava e information_schema, con collation accent-insensitive,
    // poteva far risultare esistente `quantita` anche se la colonna reale era `quantità`.
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[\p{L}\p{N}_]+$/u', $column)) {
        return $cache[$key] = false;
    }

    try {
        $stmt = $mysqli->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND BINARY COLUMN_NAME = ?
            LIMIT 1
        ");
        if (!$stmt) return $cache[$key] = false;

        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();

        return $cache[$key] = $exists;
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

function admin_first_existing_column(mysqli $mysqli, string $table, array $columns): ?string
{
    foreach ($columns as $column) {
        if (admin_column_exists($mysqli, $table, $column)) return $column;
    }
    return null;
}

function admin_qcol(string $column): string
{
    return '`' . str_replace('`', '``', $column) . '`';
}

function admin_current_user(mysqli $mysqli): ?array
{
    if (!function_exists('isLoggedIn') || !isLoggedIn()) return null;
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) return null;

    $stmt = $mysqli->prepare("SELECT id, username, email, ruolo, isBannato FROM utenti WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $_SESSION['ruolo'] = $user['ruolo'] ?? ($_SESSION['ruolo'] ?? 'utente');
        $_SESSION['username'] = $user['username'] ?? ($_SESSION['username'] ?? '');
    }
    return $user ?: null;
}

function admin_is_owner_role(?string $role): bool
{
    return $role === 'owner';
}

function admin_is_admin_role(?string $role): bool
{
    return $role === 'admin' || $role === 'owner';
}

function admin_role_level(?string $role): int
{
    return match ($role) {
        'owner' => 3,
        'admin' => 2,
        default => 1,
    };
}

function admin_require_access(mysqli $mysqli, bool $json = false): array
{
    $user = admin_current_user($mysqli);

    if (!$user) {
        if ($json) admin_fail('Devi effettuare il login.', 401);
        header('Location: /it/accedi');
        exit;
    }

    if ((int)($user['isBannato'] ?? 0) === 1) {
        if ($json) admin_fail('Account bannato.', 403);
        header('Location: /it/banned');
        exit;
    }

    if (!admin_is_admin_role($user['ruolo'] ?? 'utente')) {
        if ($json) admin_fail('Non hai i permessi admin.', 403);
        http_response_code(403);
        echo 'Non autorizzato.';
        exit;
    }

    return $user;
}

function admin_fetch_user(mysqli $mysqli, int $userId): ?array
{
    $select = "id, username, email, ruolo, isBannato, data_creazione";
    foreach (['motivo_ban', 'banned_at', 'banned_by', 'updated_at', 'email_verificata'] as $column) {
        if (admin_column_exists($mysqli, 'utenti', $column)) $select .= ", $column";
    }

    $stmt = $mysqli->prepare("SELECT $select FROM utenti WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function admin_can_manage_user(array $actor, array $target, bool $allowSelf = false): bool
{
    $actorId = (int)($actor['id'] ?? 0);
    $targetId = (int)($target['id'] ?? 0);
    $actorRole = $actor['ruolo'] ?? 'utente';
    $targetRole = $target['ruolo'] ?? 'utente';

    if (!$allowSelf && $actorId === $targetId) return false;
    if ($actorRole === 'owner') return true;
    if ($actorRole === 'admin' && $targetRole === 'utente') return true;
    return false;
}

function admin_can_set_role(array $actor, array $target, string $newRole): bool
{
    $actorRole = $actor['ruolo'] ?? 'utente';
    $targetRole = $target['ruolo'] ?? 'utente';
    $actorId = (int)($actor['id'] ?? 0);
    $targetId = (int)($target['id'] ?? 0);

    if (!in_array($newRole, ['utente', 'admin', 'owner'], true)) return false;
    if ($actorId === $targetId && $newRole !== $targetRole) return false;
    if ($actorRole === 'owner') return true;
    if ($actorRole === 'admin') return $targetRole === 'utente' && $newRole === 'utente';
    return false;
}

function admin_log(mysqli $mysqli, int $adminId, string $action, ?int $targetUserId = null, array $details = []): void
{
    if (!admin_table_exists($mysqli, 'admin_logs')) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $json = $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

    $stmt = $mysqli->prepare("INSERT INTO admin_logs (admin_id, target_user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if (!$stmt) return;
    $stmt->bind_param('iisss', $adminId, $targetUserId, $action, $json, $ip);
    $stmt->execute();
    $stmt->close();
}

function admin_validate_username(string $username): bool
{
    return (bool)preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function admin_validate_url(?string $url): ?string
{
    $url = trim((string)$url);
    if ($url === '') return null;

    if (filter_var($url, FILTER_VALIDATE_URL)) return $url;

    // Permette path locali tipo badge.png, /img/badge.png, img/badge.png.
    // Blocca path pericolosi e pseudo-protocolli tipo javascript:.
    if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $url)) return null;
    if (str_contains($url, '..') || str_contains($url, "\0")) return null;
    if (preg_match('/^[a-zA-Z0-9_\-\/.% ]+$/', $url)) return $url;

    return null;
}

function admin_asset_url(?string $path, string $defaultBase = '/img/'): ?string
{
    $path = trim((string)$path);
    if ($path === '') return null;
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    if (str_starts_with($path, '/')) return $path;
    if (str_starts_with($path, 'img/')) return '/' . $path;
    return rtrim($defaultBase, '/') . '/' . ltrim($path, '/');
}

function admin_prepare_error(mysqli $mysqli, string $fallback): string
{
    $err = trim((string)$mysqli->error);
    return $err !== '' ? $fallback . ' Dettaglio: ' . $err : $fallback;
}

function admin_normalize_role(string $role): string
{
    return in_array($role, ['utente', 'admin', 'owner'], true) ? $role : 'utente';
}

function admin_bind_params(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '') return true;
    return $stmt->bind_param($types, ...$params);
}

function admin_safe_count(mysqli $mysqli, string $sql, string $types = '', array $params = []): int
{
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return 0;
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

function admin_compact_number(int $number): string
{
    if ($number >= 1000000) return round($number / 1000000, 1) . 'M';
    if ($number >= 1000) return round($number / 1000, 1) . 'K';
    return (string)$number;
}

function admin_avatar_url(int $userId): string
{
    return '/includes/get_pfp.php?id=' . $userId;
}

function admin_now_mysql(): string
{
    return date('Y-m-d H:i:s');
}

function admin_update_user_timestamp_sql(mysqli $mysqli): string
{
    return admin_column_exists($mysqli, 'utenti', 'updated_at') ? ', updated_at = NOW()' : '';
}

function admin_inventory_quantity_column(mysqli $mysqli): ?string
{
    return admin_first_existing_column($mysqli, 'utenti_personaggi', ['quantità', 'quantita', 'quantity']);
}

function admin_character_columns(mysqli $mysqli): array
{
    return [
        'id' => 'id',
        'name' => admin_first_existing_column($mysqli, 'personaggi', ['nome', 'name']),
        'image' => admin_first_existing_column($mysqli, 'personaggi', ['img_url', 'immagine', 'image_url', 'img']),
        'rarity' => admin_first_existing_column($mysqli, 'personaggi', ['rarità', 'rarita', 'rarity']),
        'audio' => admin_first_existing_column($mysqli, 'personaggi', ['audio_url', 'audio']),
        'category' => admin_first_existing_column($mysqli, 'personaggi', ['categoria', 'category']),
    ];
}

function admin_achievement_columns(mysqli $mysqli): array
{
    return [
        'id' => 'id',
        'name' => admin_first_existing_column($mysqli, 'achievement', ['nome', 'name']),
        'description' => admin_first_existing_column($mysqli, 'achievement', ['descrizione', 'description']),
        'image' => admin_first_existing_column($mysqli, 'achievement', ['img_url', 'icona', 'icon_url', 'image_url']),
        'points' => admin_first_existing_column($mysqli, 'achievement', ['punti', 'points']),
    ];
}
