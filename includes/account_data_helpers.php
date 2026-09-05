<?php
// Account data export ("download your data") and self-service account deletion
// with a grace period, the way mainstream social networks do it:
//
//   request  ->  account is deactivated and hidden, a 30 day timer starts
//   login    ->  the request is cancelled and the account comes back
//   timeout  ->  every trace of the account is permanently removed
//
// Both features walk the schema at runtime instead of hardcoding a table list,
// so a table added later is covered automatically.

require_once __DIR__ . '/account_zip.php';
require_once __DIR__ . '/security_helpers.php';

const ACCOUNT_DELETION_GRACE_DAYS = 30;
const ACCOUNT_EXPORT_TTL_DAYS = 7;
const ACCOUNT_EXPORT_COOLDOWN_HOURS = 24;
const ACCOUNT_EXPORT_MAX_MEDIA_BYTES = 268435456; // 256 MB of attachments, max

/** Directory holding generated exports. Never served directly by Apache. */
function account_export_dir(): string
{
    return __DIR__ . '/../uploads/data_exports';
}

/**
 * Verifies the schema this feature needs is present.
 *
 * The migration is applied by hand (migrations/2026_09_account_data_and_deletion.sql);
 * nothing here ever runs DDL. When something is missing the two settings
 * sections show as unavailable instead of producing broken queries.
 */
function account_ensure_schema(mysqli $mysqli): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    try {
        $columns = [];
        $res = $mysqli->query("SHOW COLUMNS FROM `utenti`");
        if (!$res) {
            return $ready = false;
        }
        while ($row = $res->fetch_assoc()) {
            $columns[strtolower($row['Field'])] = true;
        }
        $res->free();

        if (!isset($columns['deletion_requested_at']) || !isset($columns['deletion_scheduled_for'])) {
            error_log('[account_ensure_schema] utenti is missing the deletion columns; run migrations/2026_09_account_data_and_deletion.sql');
            return $ready = false;
        }

        $exports = $mysqli->query("SHOW TABLES LIKE 'user_data_exports'");
        if (!$exports || $exports->num_rows === 0) {
            error_log('[account_ensure_schema] user_data_exports is missing; run migrations/2026_09_account_data_and_deletion.sql');
            return $ready = false;
        }
        $exports->free();

        return $ready = true;
    } catch (Throwable $e) {
        error_log('[account_ensure_schema] ' . $e->getMessage());
        return $ready = false;
    }
}

/**
 * Column names that hold a `utenti.id`.
 *
 * Every entry was verified against the actual queries in this codebase. Names
 * that merely look like a user reference are deliberately absent:
 *   - `target_id`          -> a message id in chat_reports
 *   - `user_mission_id`    -> a user_missions row id
 *   - `*_discord_id`       -> a Discord snowflake, not our id
 *   - a bare `utente`      -> would risk matching a username column
 * Getting one of these wrong would delete unrelated rows, so the list stays
 * explicit rather than pattern-based.
 */
function account_owner_columns(): array
{
    return [
        // Generic ownership
        'utente_id', 'user_id', 'id_utente',
        // Friendships, follows and blocks (both sides)
        'user_one_id', 'user_two_id',
        'requester_id', 'addressee_id',
        'follower_id', 'followed_id',
        'blocker_id', 'blocked_id', 'blocked_user_id',
        // Messaging
        'sender_id', 'recipient_id', 'receiver_id', 'destinatario_id', 'mittente_id',
        'inviter_id', 'invitee_id',
        // Games
        'player_id', 'player1_id', 'player2_id', 'winner_id', 'loser_id',
        // Moderation and authorship
        'author_id', 'author_user_id', 'target_author_id', 'created_by',
        'reporter_id', 'reporter_user_id', 'reported_id', 'reported_user_id', 'reported_by',
        'target_user_id', 'from_user_id', 'to_user_id',
    ];
}

/** Tables that must never be touched by the generic sweep. */
function account_protected_tables(): array
{
    // `utenti` is handled explicitly and last; the rest are shared catalogues
    // whose rows do not belong to any single account.
    return ['utenti', 'achievement', 'personaggi', 'missions', 'godos_shop_items', 'banner_eventi'];
}

/**
 * Discovers every {table, column} pair in the current database that points at a
 * user. Returns ['table' => ['col1', 'col2'], ...].
 */
function account_owned_tables(mysqli $mysqli): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    $owners = account_owner_columns();
    $placeholders = implode(',', array_fill(0, count($owners), '?'));

    // DATA_TYPE is constrained to integers on purpose: comparing a user id to a
    // VARCHAR column would make MySQL cast the text to a number, so a username
    // like "5abc" would match user 5 and its row would be exported or deleted.
    $sql = "SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND LOWER(COLUMN_NAME) IN ($placeholders)
              AND DATA_TYPE IN ('int', 'bigint', 'mediumint', 'smallint', 'tinyint')
            ORDER BY TABLE_NAME, COLUMN_NAME";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return $cache;
    }
    $stmt->bind_param(str_repeat('s', count($owners)), ...$owners);
    if (!$stmt->execute()) {
        $stmt->close();
        return $cache;
    }

    $protected = account_protected_tables();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $table = (string)$row['TABLE_NAME'];
        $column = (string)$row['COLUMN_NAME'];
        if (in_array(strtolower($table), $protected, true)) {
            continue;
        }
        // Defensive: identifiers are interpolated into SQL below.
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            continue;
        }
        $cache[$table][] = $column;
    }
    $stmt->close();

    return $cache;
}

/** True when a column holds a credential that must never leave the server. */
function account_is_secret_column(string $column): bool
{
    return (bool)preg_match(
        '/(password|passwd|secret|token|salt|csrf|api_key|private_key|backup_code|recovery_code|_hash$|^hash$)/i',
        $column
    );
}

/** Columns holding raw media, exported as real files instead of JSON. */
function account_is_blob_column(string $column): bool
{
    return in_array(strtolower($column), ['profile_pic', 'profile_banner', 'profile_music_blob'], true);
}

/** Makes a database value safe and readable inside the JSON export. */
function account_export_value(string $column, $value)
{
    if ($value === null) {
        return null;
    }
    if (account_is_secret_column($column)) {
        return '[rimosso per sicurezza]';
    }
    if (!is_string($value)) {
        return $value;
    }
    if (account_is_blob_column($column)) {
        return str_starts_with($value, '/uploads/')
            ? $value
            : '[dati binari, ' . strlen($value) . ' byte - vedi la cartella media/]';
    }
    if (!mb_check_encoding($value, 'UTF-8')) {
        return '[dati binari, ' . strlen($value) . ' byte]';
    }

    return $value;
}

/** Encodes one export document. */
function account_json(array $data): string
{
    return (string)json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
}

/** Collects the files the user uploaded, for inclusion in the archive. */
function account_media_files(mysqli $mysqli, int $userId): array
{
    $files = [];
    $total = 0;

    $dir = __DIR__ . '/../uploads/profile_media/user_' . $userId;
    if (is_dir($dir)) {
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $dir . '/' . $name;
            if (!is_file($path)) {
                continue;
            }
            $size = filesize($path) ?: 0;
            if ($total + $size > ACCOUNT_EXPORT_MAX_MEDIA_BYTES) {
                break;
            }
            $total += $size;
            $files[] = ['name' => 'media/' . $name, 'file' => $path];
        }
    }

    // Avatar / background / song still stored as blobs in the database. Only the
    // columns that actually exist are selected, so an older or trimmed schema
    // degrades to "no blobs" instead of aborting the whole export.
    $available = [];
    try {
        $describe = $mysqli->query("SHOW COLUMNS FROM `utenti`");
        while ($describe && ($col = $describe->fetch_assoc())) {
            $available[strtolower($col['Field'])] = true;
        }
        if ($describe) {
            $describe->free();
        }
    } catch (Throwable $e) {
        return $files;
    }

    $wanted = array_values(array_filter(
        ['profile_pic', 'profile_pic_type', 'profile_banner', 'profile_banner_type', 'profile_music_blob', 'profile_music_mime'],
        static fn(string $c): bool => isset($available[$c])
    ));
    if (empty($wanted)) {
        return $files;
    }

    $row = null;
    try {
        $stmt = $mysqli->prepare('SELECT `' . implode('`, `', $wanted) . '` FROM `utenti` WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('[account_media_files] ' . $e->getMessage());
    }

    if ($row) {
        $blobs = [
            ['profile_pic', 'profile_pic_type', 'avatar'],
            ['profile_banner', 'profile_banner_type', 'sfondo-profilo'],
            ['profile_music_blob', 'profile_music_mime', 'musica-profilo'],
        ];
        $extensions = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
            'video/mp4' => 'mp4', 'video/webm' => 'webm', 'audio/mpeg' => 'mp3',
        ];

        foreach ($blobs as [$dataCol, $mimeCol, $label]) {
            $value = $row[$dataCol] ?? null;
            // A path means the bytes already came from the folder scanned above.
            if (!is_string($value) || $value === '' || str_starts_with($value, '/uploads/')) {
                continue;
            }
            if ($total + strlen($value) > ACCOUNT_EXPORT_MAX_MEDIA_BYTES) {
                continue;
            }
            $total += strlen($value);
            $ext = $extensions[strtolower(trim((string)($row[$mimeCol] ?? '')))] ?? 'bin';
            $files[] = ['name' => 'media/' . $label . '.' . $ext, 'data' => $value];
        }
    }

    return $files;
}

/** Human-readable index shipped at the root of the archive. */
function account_export_readme(array $account, array $tableCounts, int $mediaCount): string
{
    $lines = [];
    $lines[] = 'ESPORTAZIONE DATI - Cripsum(TM)';
    $lines[] = str_repeat('=', 46);
    $lines[] = '';
    $lines[] = 'Account : ' . ($account['username'] ?? '-') . ' (ID ' . ($account['id'] ?? '-') . ')';
    $lines[] = 'Generato: ' . date('d/m/Y H:i:s');
    $lines[] = '';
    $lines[] = 'CONTENUTO';
    $lines[] = '---------';
    $lines[] = 'account.json   Il tuo profilo e le impostazioni dell\'account.';
    $lines[] = 'data/*.json    Una tabella per file, con tutte le righe che ti riguardano.';
    $lines[] = 'media/         I file che hai caricato (avatar, sfondi, immagini, musica).';
    $lines[] = '';
    $lines[] = 'TABELLE INCLUSE';
    $lines[] = '---------------';
    if (empty($tableCounts)) {
        $lines[] = '(nessun dato aggiuntivo)';
    } else {
        ksort($tableCounts);
        foreach ($tableCounts as $table => $count) {
            $lines[] = sprintf('%-38s %6d righe', $table, $count);
        }
    }
    $lines[] = '';
    $lines[] = 'File multimediali inclusi: ' . $mediaCount;
    $lines[] = '';
    $lines[] = 'NOTE';
    $lines[] = '----';
    $lines[] = '- Password, codici di backup, segreti 2FA e token di sessione NON sono';
    $lines[] = '  inclusi: sono credenziali e non lasciano mai il server.';
    $lines[] = '- I messaggi privati includono anche quelli ricevuti, perche fanno parte';
    $lines[] = '  della tua casella. Trattali con la stessa riservatezza.';
    $lines[] = '- Questo archivio contiene dati personali: conservalo in un posto sicuro.';
    $lines[] = '';

    return implode("\r\n", $lines);
}

/**
 * Builds the export archive for a user.
 *
 * Returns ['ok' => bool, 'message' => string, 'file' => ?string, 'size' => int].
 */
function account_build_export(mysqli $mysqli, int $userId): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'message' => 'Utente non valido.'];
    }

    try {
        return account_build_export_unsafe($mysqli, $userId);
    } catch (Throwable $e) {
        error_log('[account_build_export] ' . $e->getMessage());
        return ['ok' => false, 'message' => "Errore durante la creazione dell'archivio. Riprova più tardi."];
    }
}

/** Actual export work; always called through account_build_export(). */
function account_build_export_unsafe(mysqli $mysqli, int $userId): array
{

    $dir = account_export_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return ['ok' => false, 'message' => 'Impossibile creare la cartella delle esportazioni.'];
    }
    account_protect_export_dir($dir);

    // 1. The account row itself.
    $stmt = $mysqli->prepare("SELECT * FROM `utenti` WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Errore di lettura dei dati.'];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $accountRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$accountRow) {
        return ['ok' => false, 'message' => 'Account non trovato.'];
    }

    $account = [];
    foreach ($accountRow as $column => $value) {
        $account[$column] = account_export_value($column, $value);
    }

    $entries = [];
    $entries[] = ['name' => 'account.json', 'data' => account_json([
        'esportazione' => [
            'generata_il' => date('c'),
            'sito' => defined('SITE_URL') ? SITE_URL : 'https://cripsum.com',
            'formato' => 'JSON (UTF-8)',
        ],
        'account' => $account,
    ])];

    // 2. Every other table that references this user.
    $tableCounts = [];
    foreach (account_owned_tables($mysqli) as $table => $columns) {
        $where = [];
        foreach ($columns as $column) {
            $where[] = "`$column` = ?";
        }
        $sql = "SELECT * FROM `$table` WHERE " . implode(' OR ', $where);

        try {
            $rowStmt = $mysqli->prepare($sql);
            if (!$rowStmt) {
                continue;
            }
            $params = array_fill(0, count($columns), $userId);
            $rowStmt->bind_param(str_repeat('i', count($columns)), ...$params);
            if (!$rowStmt->execute()) {
                $rowStmt->close();
                continue;
            }
            $result = $rowStmt->get_result();
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $clean = [];
                foreach ($row as $column => $value) {
                    $clean[$column] = account_export_value($column, $value);
                }
                $rows[] = $clean;
            }
            $rowStmt->close();

            if (empty($rows)) {
                continue;
            }

            $tableCounts[$table] = count($rows);
            $entries[] = [
                'name' => 'data/' . $table . '.json',
                'data' => account_json(['tabella' => $table, 'righe' => count($rows), 'dati' => $rows]),
            ];
        } catch (Throwable $e) {
            error_log('[account_build_export] ' . $table . ': ' . $e->getMessage());
        }
    }

    // 3. Uploaded media.
    $media = account_media_files($mysqli, $userId);
    foreach ($media as $file) {
        $entries[] = $file;
    }

    array_unshift($entries, [
        'name' => 'README.txt',
        'data' => account_export_readme($accountRow, $tableCounts, count($media)),
    ]);

    $fileName = 'cripsum-dati-' . $userId . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(16)) . '.zip';
    $path = $dir . '/' . $fileName;

    if (!account_zip_write($path, $entries)) {
        return ['ok' => false, 'message' => 'Impossibile creare l\'archivio.'];
    }

    $size = filesize($path) ?: 0;
    $expires = date('Y-m-d H:i:s', time() + ACCOUNT_EXPORT_TTL_DAYS * 86400);
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    $insert = $mysqli->prepare("INSERT INTO `user_data_exports` (utente_id, file_name, file_size, requested_at, expires_at, request_ip) VALUES (?, ?, ?, NOW(), ?, ?)");
    if ($insert) {
        $insert->bind_param('isiss', $userId, $fileName, $size, $expires, $ip);
        $insert->execute();
        $insert->close();
    }

    account_cleanup_exports($mysqli, $userId);

    return ['ok' => true, 'message' => 'Esportazione pronta.', 'file' => $fileName, 'size' => $size];
}

/** Drops an .htaccess into the export folder so Apache never serves it. */
function account_protect_export_dir(string $dir): void
{
    $file = $dir . '/.htaccess';
    if (is_file($file)) {
        return;
    }
    @file_put_contents(
        $file,
        "# Exports contain personal data and are served only through\n" .
        "# api/download_data_export.php, which checks the session.\n" .
        "Require all denied\n" .
        "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
    );
}

/** Removes expired archives (and superseded ones) for a user. */
function account_cleanup_exports(mysqli $mysqli, ?int $userId = null): void
{
    $dir = account_export_dir();

    try {
        if ($userId !== null) {
            // Keep only the newest archive per user.
            $stmt = $mysqli->prepare("SELECT id, file_name FROM `user_data_exports` WHERE utente_id = ? ORDER BY requested_at DESC, id DESC");
            $stmt->bind_param('i', $userId);
        } else {
            $stmt = $mysqli->prepare("SELECT id, file_name FROM `user_data_exports` WHERE expires_at < NOW()");
        }
        if (!$stmt) {
            return;
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stale = $userId !== null ? array_slice($rows, 1) : $rows;
        foreach ($stale as $row) {
            $path = $dir . '/' . basename((string)$row['file_name']);
            if (is_file($path)) {
                @unlink($path);
            }
            $del = $mysqli->prepare("DELETE FROM `user_data_exports` WHERE id = ?");
            if ($del) {
                $del->bind_param('i', $row['id']);
                $del->execute();
                $del->close();
            }
        }
    } catch (Throwable $e) {
        error_log('[account_cleanup_exports] ' . $e->getMessage());
    }
}

/** The user's current, still valid export (or null). */
function account_latest_export(mysqli $mysqli, int $userId): ?array
{
    $stmt = $mysqli->prepare("SELECT id, file_name, file_size, requested_at, expires_at, download_count FROM `user_data_exports` WHERE utente_id = ? AND expires_at > NOW() ORDER BY requested_at DESC, id DESC LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }
    if (!is_file(account_export_dir() . '/' . basename((string)$row['file_name']))) {
        return null;
    }

    return $row;
}

/** Seconds left before the user may request another export (0 = allowed). */
function account_export_cooldown(mysqli $mysqli, int $userId): int
{
    $stmt = $mysqli->prepare("SELECT requested_at FROM `user_data_exports` WHERE utente_id = ? ORDER BY requested_at DESC, id DESC LIMIT 1");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['requested_at'])) {
        return 0;
    }

    $elapsed = time() - (int)strtotime((string)$row['requested_at']);
    $window = ACCOUNT_EXPORT_COOLDOWN_HOURS * 3600;

    return $elapsed >= $window ? 0 : ($window - $elapsed);
}

/**
 * True when the deletion columns exist, so callers can safely filter on them.
 *
 * Deliberately does NOT create them: read paths (profile pages, search) must
 * stay cheap and must never run DDL.
 */
function account_deletion_filter_available(mysqli $mysqli): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    return $available = function_exists('auth_column_exists')
        && auth_column_exists($mysqli, 'utenti', 'deletion_requested_at');
}

/** SQL fragment excluding accounts pending deletion, or '' when unsupported. */
function account_active_sql(mysqli $mysqli, string $alias = ''): string
{
    if (!account_deletion_filter_available($mysqli)) {
        return '';
    }
    $prefix = $alias !== '' ? '`' . $alias . '`.' : '';

    return $prefix . 'deletion_requested_at IS NULL';
}

// ── RE-AUTHENTICATION ───────────────────────────────────────────────────────

/**
 * Confirms the person at the keyboard really is the account owner.
 *
 * Downloading everything an account holds, and destroying it, are both
 * irreversible-grade actions: a valid session alone is not enough, because a
 * borrowed or hijacked one would be sufficient otherwise.
 *
 * Accepts the account password, or a 2FA / backup code, and falls back to
 * typing the exact username for Google-only accounts that have neither.
 */
function account_reauthenticate(mysqli $mysqli, int $userId, array $input): array
{
    $user = auth_get_user_by_id($mysqli, $userId);
    if (!$user) {
        return ['ok' => false, 'message' => 'Account non trovato.'];
    }

    $identifier = 'user:' . $userId;
    if (auth_rate_limited($mysqli, $identifier, 'account_reauth_failed', 5, 15)) {
        return ['ok' => false, 'message' => 'Troppi tentativi. Riprova tra qualche minuto.'];
    }

    $password = (string)($input['confirm_password'] ?? '');
    $code = preg_replace('/\s+/', '', (string)($input['confirm_code'] ?? ''));
    $typed = trim((string)($input['confirm_username'] ?? ''));

    $hasPassword = !empty($user['password']);
    $has2fa = (int)($user['twofa_enabled'] ?? 0) === 1 && !empty($user['twofa_secret']);

    if ($hasPassword) {
        if ($password === '') {
            return ['ok' => false, 'message' => 'Inserisci la tua password per continuare.'];
        }
        if (!auth_verify_user_password($mysqli, $userId, $password)) {
            auth_record_rate_failure($mysqli, $userId, $identifier, 'account_reauth_failed');
            return ['ok' => false, 'message' => 'Password non corretta.'];
        }
        // With 2FA on, the second factor is required here too.
        if ($has2fa) {
            if ($code === '') {
                return ['ok' => false, 'message' => 'Inserisci anche il codice 2FA.'];
            }
            if (!auth_verify_2fa_or_backup($mysqli, $userId, $code)) {
                auth_record_rate_failure($mysqli, $userId, $identifier, 'account_reauth_failed');
                return ['ok' => false, 'message' => 'Codice 2FA non valido.'];
            }
        }
        return ['ok' => true, 'message' => ''];
    }

    if ($has2fa) {
        if ($code === '') {
            return ['ok' => false, 'message' => 'Inserisci il codice 2FA per continuare.'];
        }
        if (!auth_verify_2fa_or_backup($mysqli, $userId, $code)) {
            auth_record_rate_failure($mysqli, $userId, $identifier, 'account_reauth_failed');
            return ['ok' => false, 'message' => 'Codice 2FA non valido.'];
        }
        return ['ok' => true, 'message' => ''];
    }

    // No password and no 2FA (account created through Google): the only proof
    // available is deliberate confirmation.
    $username = (string)($user['username'] ?? '');
    if ($typed === '' || strcasecmp($typed, $username) !== 0) {
        auth_record_rate_failure($mysqli, $userId, $identifier, 'account_reauth_failed');
        return ['ok' => false, 'message' => 'Scrivi esattamente il tuo username per confermare.'];
    }

    return ['ok' => true, 'message' => ''];
}

/** Describes which confirmation the account needs, for the UI to render. */
function account_reauth_method(array $user): string
{
    $hasPassword = !empty($user['password']);
    $has2fa = (int)($user['twofa_enabled'] ?? 0) === 1 && !empty($user['twofa_secret']);

    if ($hasPassword) {
        return $has2fa ? 'password_2fa' : 'password';
    }

    return $has2fa ? '2fa' : 'username';
}

// ── DELETION ────────────────────────────────────────────────────────────────

/** Returns ['requested_at' => ..., 'scheduled_for' => ..., 'days_left' => int] or null. */
function account_deletion_state(mysqli $mysqli, int $userId): ?array
{
    if (!account_ensure_schema($mysqli)) {
        return null;
    }

    $stmt = $mysqli->prepare("SELECT deletion_requested_at, deletion_scheduled_for FROM `utenti` WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['deletion_requested_at'])) {
        return null;
    }

    $scheduled = !empty($row['deletion_scheduled_for'])
        ? (int)strtotime((string)$row['deletion_scheduled_for'])
        : (int)strtotime((string)$row['deletion_requested_at']) + ACCOUNT_DELETION_GRACE_DAYS * 86400;

    return [
        'requested_at' => $row['deletion_requested_at'],
        'scheduled_for' => date('Y-m-d H:i:s', $scheduled),
        'days_left' => max(0, (int)ceil(($scheduled - time()) / 86400)),
        'seconds_left' => max(0, $scheduled - time()),
    ];
}

/** "3 ore e 20 minuti" / "3 hours and 20 minutes" for a countdown. */
function account_format_duration(int $seconds, string $lang = 'it'): string
{
    $seconds = max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    $isIt = $lang !== 'en';
    $parts = [];

    if ($hours > 0) {
        $parts[] = $hours . ' ' . ($isIt ? ($hours === 1 ? 'ora' : 'ore') : ($hours === 1 ? 'hour' : 'hours'));
    }
    if ($minutes > 0 || $hours === 0) {
        $parts[] = $minutes . ' ' . ($isIt ? ($minutes === 1 ? 'minuto' : 'minuti') : ($minutes === 1 ? 'minute' : 'minutes'));
    }

    return implode($isIt ? ' e ' : ' and ', $parts);
}

/**
 * Tells the user their account is scheduled for deletion, by email and in the
 * on-site inbox, so an unauthorised request cannot go unnoticed.
 */
function account_notify_deletion_scheduled(mysqli $mysqli, int $userId, ?string $scheduledFor, string $lang = 'it'): void
{
    $user = auth_get_user_by_id($mysqli, $userId);
    if (!$user) {
        return;
    }

    $when = $scheduledFor ? date('d/m/Y', (int)strtotime($scheduledFor)) : '-';
    $username = (string)($user['username'] ?? '');
    $site = defined('SITE_NAME') ? SITE_NAME : 'Cripsum';
    $loginUrl = (defined('SITE_URL') ? SITE_URL : 'https://cripsum.com') . '/' . ($lang === 'en' ? 'en' : 'it') . '/accedi';

    $titleIt = 'Cancellazione account programmata';
    $titleEn = 'Account deletion scheduled';
    $bodyIt = "Ciao {$username},\n\n"
        . "Abbiamo ricevuto la richiesta di cancellazione del tuo account.\n"
        . "Verrà eliminato definitivamente il {$when}.\n\n"
        . "Hai cambiato idea? Ti basta accedere di nuovo prima di quella data: "
        . "il login annulla automaticamente la cancellazione.\n\n"
        . "Se non sei stato tu, accedi subito e cambia la password.";
    $bodyEn = "Hi {$username},\n\n"
        . "We received a request to delete your account.\n"
        . "It will be permanently removed on {$when}.\n\n"
        . "Changed your mind? Just sign in again before that date: "
        . "logging in cancels the deletion automatically.\n\n"
        . "If this wasn't you, sign in now and change your password.";

    if (function_exists('sendSecurityInboxMessage')) {
        try {
            sendSecurityInboxMessage($mysqli, $userId, $titleIt, $titleEn, $bodyIt, $bodyEn, 'security');
        } catch (Throwable $e) {
            error_log('[account_notify_deletion_scheduled] inbox: ' . $e->getMessage());
        }
    }

    $email = trim((string)($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !defined('FROM_EMAIL')) {
        return;
    }

    $isIt = $lang !== 'en';
    $subject = ($isIt ? 'Cancellazione account programmata - ' : 'Account deletion scheduled - ') . $site;
    $body = nl2br(htmlspecialchars($isIt ? $bodyIt : $bodyEn, ENT_QUOTES, 'UTF-8'));
    $cta = $isIt ? 'Accedi per annullare' : 'Sign in to cancel';

    $html = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:24px;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:10px;padding:28px;">'
        . '<h2 style="margin-top:0;">' . htmlspecialchars($isIt ? $titleIt : $titleEn, ENT_QUOTES, 'UTF-8') . '</h2>'
        . '<p style="line-height:1.6;color:#333;">' . $body . '</p>'
        . '<p style="margin:26px 0;"><a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '" '
        . 'style="background:#0f5bff;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;">'
        . htmlspecialchars($cta, ENT_QUOTES, 'UTF-8') . '</a></p>'
        . '<p style="font-size:12px;color:#888;">' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</div></body></html>';

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . (defined('FROM_NAME') ? FROM_NAME : $site) . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
    ];

    @mail($email, $subject, $html, implode("\r\n", $headers));
}

/** Schedules the deletion and deactivates the account. */
function account_request_deletion(mysqli $mysqli, int $userId): array
{
    if (!account_ensure_schema($mysqli)) {
        return ['ok' => false, 'message' => 'La cancellazione account non è ancora disponibile. Riprova più tardi.'];
    }

    $scheduled = date('Y-m-d H:i:s', time() + ACCOUNT_DELETION_GRACE_DAYS * 86400);
    $stmt = $mysqli->prepare("UPDATE `utenti` SET deletion_requested_at = NOW(), deletion_scheduled_for = ? WHERE id = ? AND deletion_requested_at IS NULL LIMIT 1");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Errore durante la richiesta.'];
    }
    $stmt->bind_param('si', $scheduled, $userId);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'message' => 'Errore durante la richiesta.'];
    }
    if ($affected === 0) {
        // Already pending: report the existing schedule rather than restarting it.
        return ['ok' => true, 'message' => 'La cancellazione era già programmata.', 'scheduled_for' => $scheduled];
    }

    return ['ok' => true, 'message' => 'Cancellazione programmata.', 'scheduled_for' => $scheduled];
}

/** Cancels a pending deletion. Returns true when one was actually cancelled. */
function account_cancel_deletion(mysqli $mysqli, int $userId): bool
{
    if ($userId <= 0 || !account_ensure_schema($mysqli)) {
        return false;
    }

    $stmt = $mysqli->prepare("UPDATE `utenti` SET deletion_requested_at = NULL, deletion_scheduled_for = NULL WHERE id = ? AND deletion_requested_at IS NOT NULL LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $restored = $stmt->affected_rows > 0;
    $stmt->close();

    return $restored;
}

/** Recursively removes a directory. */
function account_delete_directory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . '/' . $name;
        is_dir($path) ? account_delete_directory($path) : @unlink($path);
    }
    @rmdir($dir);
}

/**
 * Permanently erases an account and everything attached to it.
 *
 * Returns ['ok' => bool, 'deleted' => ['table' => rows], 'message' => string].
 */
function account_purge_user(mysqli $mysqli, int $userId): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'deleted' => [], 'message' => 'ID non valido.'];
    }

    $deleted = [];

    // Uploaded files first: losing the row would lose the path to them.
    account_delete_directory(__DIR__ . '/../uploads/profile_media/user_' . $userId);

    try {
        $stmt = $mysqli->prepare("SELECT file_name FROM `user_data_exports` WHERE utente_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $path = account_export_dir() . '/' . basename((string)$row['file_name']);
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('[account_purge_user] exports: ' . $e->getMessage());
    }

    // Child rows across every discovered table, then the account itself.
    foreach (account_owned_tables($mysqli) as $table => $columns) {
        foreach ($columns as $column) {
            try {
                $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE `$column` = ?");
                if (!$stmt) {
                    continue;
                }
                $stmt->bind_param('i', $userId);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $deleted[$table] = ($deleted[$table] ?? 0) + $stmt->affected_rows;
                }
                $stmt->close();
            } catch (Throwable $e) {
                error_log('[account_purge_user] ' . $table . '.' . $column . ': ' . $e->getMessage());
            }
        }
    }

    $stmt = $mysqli->prepare("DELETE FROM `utenti` WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return ['ok' => false, 'deleted' => $deleted, 'message' => 'Impossibile eliminare l\'account.'];
    }
    $stmt->bind_param('i', $userId);
    $ok = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();

    if ($ok) {
        $deleted['utenti'] = 1;
        error_log('[account_purge_user] account ' . $userId . ' erased: ' . json_encode($deleted));
    }

    return ['ok' => $ok, 'deleted' => $deleted, 'message' => $ok ? 'Account eliminato.' : 'Account non trovato.'];
}

/**
 * Runs the purge at most once an hour, from ordinary web traffic.
 *
 * The project has no scheduler, so without this nothing would ever actually be
 * erased. A lock file keeps it to one run per hour across all requests and the
 * batch is tiny, so no visitor pays a noticeable cost. Setting up a real cron
 * that calls api/admin/purge_pending_deletions.php makes this a no-op.
 */
function account_maybe_run_scheduled_purge(mysqli $mysqli, int $batch = 3): void
{
    $lock = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cripsum_purge_last_run';

    $last = is_file($lock) ? (int)@file_get_contents($lock) : 0;
    if ($last > 0 && (time() - $last) < 3600) {
        return;
    }
    // Claim the hour before doing any work, so parallel requests do not pile up.
    if (@file_put_contents($lock, (string)time(), LOCK_EX) === false) {
        return;
    }

    try {
        account_purge_due($mysqli, $batch);
    } catch (Throwable $e) {
        error_log('[account_maybe_run_scheduled_purge] ' . $e->getMessage());
    }
}

/**
 * Erases the accounts whose grace period has run out.
 *
 * $limit caps how many are handled per run so a manual trigger can never turn
 * into a long request.
 */
function account_purge_due(mysqli $mysqli, int $limit = 20): array
{
    if (!account_ensure_schema($mysqli)) {
        return ['ok' => false, 'purged' => [], 'message' => 'Schema non pronto.'];
    }

    $limit = max(1, min(200, $limit));
    $result = $mysqli->query(
        "SELECT id FROM `utenti`
         WHERE deletion_requested_at IS NOT NULL
           AND deletion_scheduled_for IS NOT NULL
           AND deletion_scheduled_for <= NOW()
         ORDER BY deletion_scheduled_for ASC
         LIMIT $limit"
    );
    if (!$result) {
        return ['ok' => false, 'purged' => [], 'message' => 'Query fallita.'];
    }

    $purged = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        $outcome = account_purge_user($mysqli, $id);
        if ($outcome['ok']) {
            $purged[] = $id;
        }
    }
    $result->free();

    account_cleanup_exports($mysqli);

    return ['ok' => true, 'purged' => $purged, 'message' => count($purged) . ' account eliminati.'];
}
