<?php
if (!defined('CRIPSUM_SECURITY_HELPERS')) {
    define('CRIPSUM_SECURITY_HELPERS', true);
}

function auth_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function auth_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return substr($ip, 0, 45);
}

function auth_table_exists(mysqli $mysqli, string $table): bool
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return $cache[$table] = false;
    }

    try {
        $stmt = $mysqli->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND BINARY TABLE_NAME = ?
            LIMIT 1
        ");
        if (!$stmt) return $cache[$table] = false;

        $stmt->bind_param('s', $table);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $cache[$table] = $exists;
    } catch (Throwable $e) {
        return $cache[$table] = false;
    }
}

function auth_column_exists(mysqli $mysqli, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
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
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $cache[$key] = $exists;
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . auth_h(csrf_token()) . '">';
}

function csrf_validate(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function auth_is_valid_username(string $username): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) return false;
    if (preg_match('/^_/', $username)) return false;
    if (preg_match('/_$/', $username)) return false;
    if (strlen($username) < 3 || strlen($username) > 20) return false;
    if (preg_match('/\s/', $username)) return false;

    return true;
}

function auth_normalize_backup_code(string $code): string
{
    return strtoupper(preg_replace('/[^A-Z0-9]/', '', $code));
}

function auth_generate_backup_code(): string
{
    $raw = strtoupper(bin2hex(random_bytes(6)));
    return substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
}

function auth_generate_backup_codes(int $count = 8): array
{
    $codes = [];
    while (count($codes) < $count) {
        $code = auth_generate_backup_code();
        $codes[$code] = true;
    }
    return array_keys($codes);
}

function auth_store_backup_codes(mysqli $mysqli, int $userId, array $codes): bool
{
    if (!auth_table_exists($mysqli, 'utenti_2fa_backup_codes')) {
        return false;
    }

    $mysqli->begin_transaction();

    try {
        $delete = $mysqli->prepare("DELETE FROM utenti_2fa_backup_codes WHERE user_id = ?");
        if (!$delete) throw new RuntimeException('backup_delete_prepare_failed');
        $delete->bind_param('i', $userId);
        $delete->execute();
        $delete->close();

        $insert = $mysqli->prepare("INSERT INTO utenti_2fa_backup_codes (user_id, code_hash, created_at) VALUES (?, ?, NOW())");
        if (!$insert) throw new RuntimeException('backup_insert_prepare_failed');

        foreach ($codes as $code) {
            $normalized = auth_normalize_backup_code($code);
            $hash = password_hash($normalized, PASSWORD_DEFAULT);
            $insert->bind_param('is', $userId, $hash);
            $insert->execute();
        }

        $insert->close();
        $mysqli->commit();
        return true;
    } catch (Throwable $e) {
        $mysqli->rollback();
        return false;
    }
}

function auth_verify_backup_code(mysqli $mysqli, int $userId, string $code, bool $markUsed = true): bool
{
    if (!auth_table_exists($mysqli, 'utenti_2fa_backup_codes')) {
        return false;
    }

    $normalized = auth_normalize_backup_code($code);
    if (strlen($normalized) < 8) {
        return false;
    }

    $stmt = $mysqli->prepare("
        SELECT id, code_hash
        FROM utenti_2fa_backup_codes
        WHERE user_id = ?
          AND used_at IS NULL
        ORDER BY id ASC
    ");
    if (!$stmt) return false;

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $matchedId = null;

    while ($row = $result->fetch_assoc()) {
        if (password_verify($normalized, $row['code_hash'])) {
            $matchedId = (int)$row['id'];
            break;
        }
    }

    $stmt->close();

    if (!$matchedId) {
        return false;
    }

    if ($markUsed) {
        $update = $mysqli->prepare("UPDATE utenti_2fa_backup_codes SET used_at = NOW() WHERE id = ? AND user_id = ?");
        if ($update) {
            $update->bind_param('ii', $matchedId, $userId);
            $update->execute();
            $update->close();
        }
    }

    return true;
}

function auth_record_login_attempt(mysqli $mysqli, ?int $userId, string $identifier, bool $success, string $reason = ''): void
{
    if (!auth_table_exists($mysqli, 'login_attempts')) {
        return;
    }

    $ip = auth_client_ip();
    $identifier = mb_substr($identifier, 0, 190);
    $reason = mb_substr($reason, 0, 80);
    $successInt = $success ? 1 : 0;

    $stmt = $mysqli->prepare("
        INSERT INTO login_attempts (user_id, ip_address, email_or_username, success, reason, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('issis', $userId, $ip, $identifier, $successInt, $reason);
    $stmt->execute();
    $stmt->close();
}

function auth_rate_limited(mysqli $mysqli, string $identifier, string $reason, int $max = 8, int $minutes = 15): bool
{
    $ip = auth_client_ip();
    $identifier = mb_substr($identifier, 0, 190);

    if (!auth_table_exists($mysqli, 'login_attempts')) {
        $key = 'rate_' . sha1($ip . '|' . $identifier . '|' . $reason);
        $now = time();

        $_SESSION[$key] = array_values(array_filter($_SESSION[$key] ?? [], fn($t) => ($now - (int)$t) < ($minutes * 60)));

        return count($_SESSION[$key]) >= $max;
    }

    $stmt = $mysqli->prepare("
        SELECT COUNT(*) AS total
        FROM login_attempts
        WHERE success = 0
          AND reason = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
          AND (ip_address = ? OR email_or_username = ?)
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('siss', $reason, $minutes, $ip, $identifier);
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    return $total >= $max;
}

function auth_session_rate_fail(string $identifier, string $reason): void
{
    $key = 'rate_' . sha1(auth_client_ip() . '|' . $identifier . '|' . $reason);
    $_SESSION[$key][] = time();
}

function auth_select_user_columns(mysqli $mysqli): string
{
    $columns = [
        'id',
        'username',
        'email',
        'password',
        'profile_pic',
        'ruolo',
        'email_verificata',
        'isBannato',
    ];

    $columns[] = auth_column_exists($mysqli, 'utenti', 'nsfw') ? 'nsfw' : '0 AS nsfw';
    $columns[] = auth_column_exists($mysqli, 'utenti', 'richpresence') ? 'richpresence' : '0 AS richpresence';
    $columns[] = auth_column_exists($mysqli, 'utenti', 'twofa_enabled') ? 'twofa_enabled' : '0 AS twofa_enabled';
    $columns[] = auth_column_exists($mysqli, 'utenti', 'twofa_secret') ? 'twofa_secret' : 'NULL AS twofa_secret';
    $columns[] = auth_column_exists($mysqli, 'utenti', 'twofa_enabled_at') ? 'twofa_enabled_at' : 'NULL AS twofa_enabled_at';

    return implode(', ', $columns);
}

function auth_get_user_by_identifier(mysqli $mysqli, string $identifier): ?array
{
    $identifier = trim($identifier);
    $columns = auth_select_user_columns($mysqli);
    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);

    $sql = $isEmail
        ? "SELECT {$columns} FROM utenti WHERE email = ? LIMIT 1"
        : "SELECT {$columns} FROM utenti WHERE username = ? LIMIT 1";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $user;
}

function auth_get_user_by_id(mysqli $mysqli, int $userId): ?array
{
    $columns = auth_select_user_columns($mysqli);
    $stmt = $mysqli->prepare("SELECT {$columns} FROM utenti WHERE id = ? LIMIT 1");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $user;
}

function auth_complete_login(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['profile_pic'] = $user['profile_pic'] ?? '../img/abdul.jpg';
    $_SESSION['ruolo'] = $user['ruolo'] ?? 'utente';
    $_SESSION['nsfw'] = (int)($user['nsfw'] ?? 0);
    $_SESSION['richpresence'] = (int)($user['richpresence'] ?? 0);
}

function auth_start_password_login(mysqli $mysqli, string $identifier, string $password): array
{
    $identifier = trim($identifier);

    if ($identifier === '' || $password === '') {
        return ['ok' => false, 'message' => 'Inserisci email/username e password.'];
    }

    if (auth_rate_limited($mysqli, $identifier, 'login_failed', 8, 15)) {
        return ['ok' => false, 'message' => 'Troppi tentativi. Riprova tra qualche minuto.'];
    }

    $user = auth_get_user_by_identifier($mysqli, $identifier);

    if (!$user || !password_verify($password, $user['password'])) {
        auth_record_login_attempt($mysqli, $user['id'] ?? null, $identifier, false, 'login_failed');
        auth_session_rate_fail($identifier, 'login_failed');
        return ['ok' => false, 'message' => 'Credenziali non valide.'];
    }

    if ((int)($user['email_verificata'] ?? 1) === 0) {
        auth_record_login_attempt($mysqli, (int)$user['id'], $identifier, false, 'email_not_verified');
        return ['ok' => false, 'message' => 'Devi verificare la tua email prima di accedere.'];
    }

    if ((int)($user['isBannato'] ?? 0) === 1) {
        auth_record_login_attempt($mysqli, (int)$user['id'], $identifier, false, 'banned');
        return ['ok' => false, 'message' => 'Account bannato. Contatta il supporto.'];
    }

    if ((int)($user['twofa_enabled'] ?? 0) === 1 && !empty($user['twofa_secret'])) {
        $_SESSION['pending_2fa_user_id'] = (int)$user['id'];
        $_SESSION['pending_2fa_started_at'] = time();
        $_SESSION['pending_2fa_identifier'] = $identifier;
        $_SESSION['pending_2fa_redirect'] = $_SESSION['redirect_after_login'] ?? 'home';

        auth_record_login_attempt($mysqli, (int)$user['id'], $identifier, true, 'password_ok_2fa_pending');

        return ['ok' => false, 'twofa_required' => true, 'redirect' => 'verifica-2fa'];
    }

    auth_complete_login($user);
    auth_record_login_attempt($mysqli, (int)$user['id'], $identifier, true, 'login_ok');

    return ['ok' => true];
}

function auth_verify_2fa_login(mysqli $mysqli, string $code): array
{
    if (empty($_SESSION['pending_2fa_user_id'])) {
        return ['ok' => false, 'message' => 'Sessione 2FA scaduta. Accedi di nuovo.'];
    }

    $userId = (int)$_SESSION['pending_2fa_user_id'];
    $identifier = (string)($_SESSION['pending_2fa_identifier'] ?? ('user:' . $userId));

    if (!empty($_SESSION['pending_2fa_started_at']) && time() - (int)$_SESSION['pending_2fa_started_at'] > 600) {
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_started_at'], $_SESSION['pending_2fa_identifier'], $_SESSION['pending_2fa_redirect']);
        return ['ok' => false, 'message' => 'Codice scaduto. Accedi di nuovo.'];
    }

    if (auth_rate_limited($mysqli, $identifier, '2fa_failed', 6, 10)) {
        return ['ok' => false, 'message' => 'Troppi tentativi 2FA. Riprova tra qualche minuto.'];
    }

    $user = auth_get_user_by_id($mysqli, $userId);

    if (!$user || (int)($user['twofa_enabled'] ?? 0) !== 1 || empty($user['twofa_secret'])) {
        return ['ok' => false, 'message' => '2FA non disponibile. Accedi di nuovo.'];
    }

    $valid = false;
    $usedBackup = false;

    if (function_exists('totp_verify') && totp_verify((string)$user['twofa_secret'], $code)) {
        $valid = true;
    } elseif (auth_verify_backup_code($mysqli, $userId, $code, true)) {
        $valid = true;
        $usedBackup = true;
    }

    if (!$valid) {
        auth_record_login_attempt($mysqli, $userId, $identifier, false, '2fa_failed');
        auth_session_rate_fail($identifier, '2fa_failed');
        return ['ok' => false, 'message' => 'Codice non valido.'];
    }

    auth_complete_login($user);

    $redirect = $_SESSION['pending_2fa_redirect'] ?? 'home';
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_started_at'], $_SESSION['pending_2fa_identifier'], $_SESSION['pending_2fa_redirect'], $_SESSION['redirect_after_login']);

    auth_record_login_attempt($mysqli, $userId, $identifier, true, $usedBackup ? '2fa_backup_ok' : '2fa_ok');

    return ['ok' => true, 'redirect' => $redirect, 'used_backup' => $usedBackup];
}

function auth_verify_user_password(mysqli $mysqli, int $userId, string $password): bool
{
    $stmt = $mysqli->prepare("SELECT password FROM utenti WHERE id = ? LIMIT 1");
    if (!$stmt) return false;

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
    $stmt->close();

    return is_string($hash) && $hash !== '' && password_verify($password, $hash);
}

function auth_twofa_status(mysqli $mysqli, int $userId): array
{
    $user = auth_get_user_by_id($mysqli, $userId);

    return [
        'enabled' => (int)($user['twofa_enabled'] ?? 0) === 1,
        'enabled_at' => $user['twofa_enabled_at'] ?? null,
        'has_columns' => auth_column_exists($mysqli, 'utenti', 'twofa_enabled') && auth_column_exists($mysqli, 'utenti', 'twofa_secret'),
    ];
}

function auth_verify_2fa_or_backup(mysqli $mysqli, int $userId, string $code): bool
{
    $user = auth_get_user_by_id($mysqli, $userId);

    if (!$user || empty($user['twofa_secret'])) {
        return false;
    }

    if (function_exists('totp_verify') && totp_verify((string)$user['twofa_secret'], $code)) {
        return true;
    }

    return auth_verify_backup_code($mysqli, $userId, $code, true);
}

function auth_enable_2fa(mysqli $mysqli, int $userId, string $secret): array
{
    if (!auth_column_exists($mysqli, 'utenti', 'twofa_enabled') || !auth_column_exists($mysqli, 'utenti', 'twofa_secret')) {
        return ['ok' => false, 'message' => 'Campi 2FA mancanti nel database.'];
    }

    $stmt = $mysqli->prepare("UPDATE utenti SET twofa_enabled = 1, twofa_secret = ?, twofa_enabled_at = NOW() WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Errore database.'];
    }

    $stmt->bind_param('si', $secret, $userId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'message' => 'Non sono riuscito ad attivare la 2FA.'];
    }

    $codes = auth_generate_backup_codes(8);
    auth_store_backup_codes($mysqli, $userId, $codes);

    return ['ok' => true, 'backup_codes' => $codes];
}

function auth_disable_2fa(mysqli $mysqli, int $userId): bool
{
    if (!auth_column_exists($mysqli, 'utenti', 'twofa_enabled') || !auth_column_exists($mysqli, 'utenti', 'twofa_secret')) {
        return false;
    }

    $stmt = $mysqli->prepare("UPDATE utenti SET twofa_enabled = 0, twofa_secret = NULL, twofa_enabled_at = NULL WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $userId);
    $ok = $stmt->execute();
    $stmt->close();

    if (auth_table_exists($mysqli, 'utenti_2fa_backup_codes')) {
        $delete = $mysqli->prepare("DELETE FROM utenti_2fa_backup_codes WHERE user_id = ?");
        if ($delete) {
            $delete->bind_param('i', $userId);
            $delete->execute();
            $delete->close();
        }
    }

    return $ok;
}
