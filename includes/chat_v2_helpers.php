<?php
require_once __DIR__ . '/../config/chat_config.php';

function chat_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function chat_current_user_id(): int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function chat_current_role(): string
{
    return (string)($_SESSION['ruolo'] ?? 'utente');
}

function chat_is_mod(?string $role = null): bool
{
    $role = $role ?? chat_current_role();
    return in_array($role, ['admin', 'owner'], true);
}

function chat_require_login_json(mysqli $mysqli): array
{
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        chat_json(['ok' => false, 'error' => 'Devi essere loggato.'], 401);
    }

    if (function_exists('checkBan')) {
        checkBan($mysqli);
    }

    $userId = chat_current_user_id();
    $stmt = $mysqli->prepare('SELECT id, username, ruolo, isBannato FROM utenti WHERE id = ? LIMIT 1');
    if (!$stmt) {
        chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        chat_json(['ok' => false, 'error' => 'Sessione non valida.'], 401);
    }

    if ((int)($user['isBannato'] ?? 0) === 1) {
        chat_json(['ok' => false, 'error' => 'Account bannato.'], 403);
    }

    $_SESSION['username'] = $user['username'];
    $_SESSION['ruolo'] = $user['ruolo'];
    return $user;
}

function chat_read_input(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function chat_csrf_token(): string
{
    if (empty($_SESSION['chat_csrf'])) {
        $_SESSION['chat_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['chat_csrf'];
}

function chat_verify_csrf(array $data): void
{
    $token = (string)($data['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (empty($_SESSION['chat_csrf']) || !hash_equals((string)$_SESSION['chat_csrf'], $token)) {
        chat_json(['ok' => false, 'error' => 'Token non valido. Ricarica la pagina.'], 419);
    }
}

function chat_clean_message(string $message): string
{
    $message = str_replace(["\r\n", "\r"], "\n", $message);
    $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message) ?? $message;
    $message = preg_replace("/\n{4,}/", "\n\n\n", $message) ?? $message;
    return trim($message);
}

function chat_message_error(string $message): ?string
{
    $message = chat_clean_message($message);
    if ($message === '') return 'Scrivi qualcosa.';
    if (mb_strlen($message, 'UTF-8') > MAX_MESSAGE_LENGTH) return 'Messaggio troppo lungo.';
    if (preg_match('/(.)\1{24,}/u', $message)) return 'Messaggio troppo ripetitivo.';
    return null;
}

function chat_table_exists(mysqli $mysqli, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];

    $stmt = $mysqli->prepare("SHOW TABLES LIKE ?");
    if (!$stmt) return $cache[$table] = false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $cache[$table] = $exists;
}

function chat_column_exists(mysqli $mysqli, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];

    $stmt = $mysqli->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    if (!$stmt) return $cache[$key] = false;
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $cache[$key] = $exists;
}

function chat_get_banned_words(mysqli $mysqli): array
{
    $words = json_decode(CHAT_BANNED_WORDS, true);
    $words = is_array($words) ? $words : [];

    if (chat_table_exists($mysqli, 'chat_word_filters')) {
        $result = $mysqli->query('SELECT word FROM chat_word_filters WHERE is_active = 1');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $words[] = (string)$row['word'];
            }
        }
    }

    return array_values(array_unique(array_filter(array_map('trim', $words))));
}

function chat_has_bad_word(mysqli $mysqli, string $message): bool
{
    foreach (chat_get_banned_words($mysqli) as $word) {
        if ($word !== '' && mb_stripos($message, $word, 0, 'UTF-8') !== false) {
            return true;
        }
    }
    return false;
}

function chat_rate_limit_ok(mysqli $mysqli, int $userId): array
{
    $timeout = (int)MESSAGE_TIMEOUT;
    $stmt = $mysqli->prepare('SELECT created_at FROM messages WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) return ['ok' => true, 'wait' => 0];

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row || empty($row['created_at'])) return ['ok' => true, 'wait' => 0];

    $last = strtotime((string)$row['created_at']);
    $wait = $timeout - (time() - $last);
    return ['ok' => $wait <= 0, 'wait' => max(0, $wait)];
}

function chat_touch_user(mysqli $mysqli, int $userId): void
{
    if ($userId <= 0 || !chat_column_exists($mysqli, 'utenti', 'ultimo_accesso')) return;
    $stmt = $mysqli->prepare('UPDATE utenti SET ultimo_accesso = NOW() WHERE id = ?');
    if (!$stmt) return;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function chat_avatar_url(int $userId): string
{
    return '/includes/get_pfp.php?id=' . $userId;
}

function chat_role_label(string $role): ?array
{
    return match ($role) {
        'owner' => ['label' => 'Owner', 'class' => 'owner'],
        'admin' => ['label' => 'Admin', 'class' => 'admin'],
        default => null,
    };
}

function chat_compact_text(?string $text, int $limit = 90): string
{
    $text = trim((string)$text);
    if (mb_strlen($text, 'UTF-8') <= $limit) return $text;
    return mb_substr($text, 0, $limit - 1, 'UTF-8') . '…';
}

function chat_get_visible_badges(mysqli $mysqli, array $userIds): array
{
    $userIds = array_values(array_unique(array_map('intval', $userIds)));
    if (!$userIds || !chat_table_exists($mysqli, 'utenti_profile_badges') || !chat_table_exists($mysqli, 'achievement')) return [];

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $types = str_repeat('i', count($userIds));
    $sql = "
        SELECT upb.utente_id, a.nome, a.img_url
        FROM utenti_profile_badges upb
        INNER JOIN achievement a ON a.id = upb.achievement_id
        WHERE upb.is_visible = 1 AND upb.utente_id IN ($placeholders)
        ORDER BY upb.sort_order ASC, upb.id ASC
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$userIds);
    $stmt->execute();
    $result = $stmt->get_result();
    $badges = [];
    while ($row = $result->fetch_assoc()) {
        $uid = (int)$row['utente_id'];
        if (!isset($badges[$uid])) {
            $badges[$uid] = [
                'name' => (string)$row['nome'],
                'image' => !empty($row['img_url']) ? '/img/' . ltrim((string)$row['img_url'], '/') : null,
            ];
        }
    }
    $stmt->close();
    return $badges;
}

function chat_format_message_row(array $row, int $currentUserId, array $badgeMap = []): array
{
    $userId = (int)$row['user_id'];
    $isDeleted = !empty($row['deleted_at']);
    $role = (string)($row['ruolo'] ?? 'utente');
    $canMod = chat_is_mod();
    $isMine = $userId === $currentUserId;
    $createdAt = (string)$row['created_at'];
    $createdTs = strtotime($createdAt) ?: time();
    $canEdit = !$isDeleted && $isMine && (time() - $createdTs) <= (int)CHAT_EDIT_WINDOW_SECONDS;

    return [
        'id' => (int)$row['id'],
        'user_id' => $userId,
        'username' => (string)$row['username'],
        'profile_url' => '/u/' . rawurlencode((string)$row['username']),
        'avatar_url' => chat_avatar_url($userId),
        'role' => $role,
        'role_badge' => chat_role_label($role),
        'badge' => $badgeMap[$userId] ?? null,
        'message' => $isDeleted ? 'Messaggio eliminato' : (string)$row['message'],
        'created_at' => $createdAt,
        'created_label' => date('H:i', $createdTs),
        'edited_at' => $row['edited_at'] ?? null,
        'deleted_at' => $row['deleted_at'] ?? null,
        'is_deleted' => $isDeleted,
        'is_mine' => $isMine,
        'can_edit' => $canEdit,
        'can_delete' => !$isDeleted && ($isMine || $canMod),
        'can_report' => !$isDeleted && !$isMine,
        'reply' => !empty($row['reply_id']) ? [
            'id' => (int)$row['reply_id'],
            'username' => (string)($row['reply_username'] ?? 'utente'),
            'message' => chat_compact_text((string)($row['reply_message'] ?? ''), 110),
        ] : null,
    ];
}

function chat_fetch_messages(mysqli $mysqli, int $currentUserId, array $options = []): array
{
    $afterId = max(0, (int)($options['after_id'] ?? 0));
    $beforeId = max(0, (int)($options['before_id'] ?? 0));
    $limit = min(max(1, (int)($options['limit'] ?? MESSAGES_PER_PAGE)), 80);
    $search = trim((string)($options['search'] ?? ''));
    $search = mb_substr($search, 0, CHAT_MAX_SEARCH_LENGTH, 'UTF-8');

    $where = ['cm.muter_id IS NULL'];
    $types = 'i';
    $params = [$currentUserId];

    if ($afterId > 0) {
        $where[] = 'm.id > ?';
        $types .= 'i';
        $params[] = $afterId;
    }

    if ($beforeId > 0) {
        $where[] = 'm.id < ?';
        $types .= 'i';
        $params[] = $beforeId;
    }

    if ($search !== '') {
        $where[] = 'm.deleted_at IS NULL AND m.message LIKE ?';
        $types .= 's';
        $params[] = '%' . $search . '%';
    }

    $order = $beforeId > 0 ? 'DESC' : 'ASC';
    $whereSql = implode(' AND ', $where);
    $sql = "
        SELECT
            m.id,
            m.user_id,
            m.message,
            m.reply_to,
            m.created_at,
            m.edited_at,
            m.deleted_at,
            u.username,
            u.ruolo,
            rm.id AS reply_id,
            rm.message AS reply_message,
            ru.username AS reply_username
        FROM messages m
        INNER JOIN utenti u ON u.id = m.user_id
        LEFT JOIN chat_mutes cm ON cm.muted_id = m.user_id AND cm.muter_id = ?
        LEFT JOIN messages rm ON rm.id = m.reply_to
        LEFT JOIN utenti ru ON ru.id = rm.user_id
        WHERE $whereSql
        ORDER BY m.id $order
        LIMIT ?
    ";

    $types .= 'i';
    $params[] = $limit;

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    $userIds = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        $userIds[] = (int)$row['user_id'];
    }
    $stmt->close();

    if ($beforeId > 0) {
        $rows = array_reverse($rows);
    }

    $badgeMap = chat_get_visible_badges($mysqli, $userIds);
    return array_map(fn($row) => chat_format_message_row($row, $currentUserId, $badgeMap), $rows);
}

function chat_get_online_count(mysqli $mysqli): int
{
    if (!chat_column_exists($mysqli, 'utenti', 'ultimo_accesso')) return 0;
    $window = (int)CHAT_ONLINE_WINDOW;
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM utenti WHERE ultimo_accesso >= DATE_SUB(NOW(), INTERVAL ? SECOND)");
    if (!$stmt) return 0;
    $stmt->bind_param('i', $window);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

function chat_get_typing_users(mysqli $mysqli, int $currentUserId): array
{
    if (!chat_table_exists($mysqli, 'chat_typing')) return [];

    $ttl = (int)CHAT_TYPING_TTL;
    $stmt = $mysqli->prepare("DELETE FROM chat_typing WHERE updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
    if ($stmt) {
        $stmt->bind_param('i', $ttl);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $mysqli->prepare("SELECT u.id, u.username FROM chat_typing ct INNER JOIN utenti u ON u.id = ct.user_id WHERE ct.user_id != ? AND ct.updated_at >= DATE_SUB(NOW(), INTERVAL ? SECOND) ORDER BY ct.updated_at DESC LIMIT 4");
    if (!$stmt) return [];
    $stmt->bind_param('ii', $currentUserId, $ttl);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = ['id' => (int)$row['id'], 'username' => (string)$row['username']];
    }
    $stmt->close();
    return $users;
}

function chat_upsert_typing(mysqli $mysqli, int $userId, bool $isTyping): void
{
    if (!chat_table_exists($mysqli, 'chat_typing')) return;

    if ($isTyping) {
        $stmt = $mysqli->prepare('INSERT INTO chat_typing (user_id, updated_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE updated_at = NOW()');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    $stmt = $mysqli->prepare('DELETE FROM chat_typing WHERE user_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}
?>
