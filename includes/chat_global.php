<?php
/**
 * Chat globale — la parte che usa il database.
 *
 * Ogni scrittura (invio, modifica, eliminazione, reazione) finisce nel
 * database e, in forma neutra, nel timbro della chat globale: da lì la
 * leggono tutti i browser collegati senza altre query. La forma «per
 * utente» la costruisce gc_view() in includes/chat_global_view.php.
 */

require_once __DIR__ . '/chat_global_view.php';
require_once __DIR__ . '/social_core.php';
require_once __DIR__ . '/chat_core.php';

if (!defined('GC_NEW_ACCOUNT_SECONDS')) {
    define('GC_NEW_ACCOUNT_SECONDS', 600);   // account più giovani di così: limiti più stretti
    define('GC_NEW_ACCOUNT_WAIT', 15);
    define('GC_MAX_LINKS', 3);
    define('GC_SLOW_MAX', 120);
}

if (!function_exists('gc_fetch')) {

    function gc_select(): string
    {
        return '
            SELECT m.id, m.user_id, m.message, m.message_type, m.media_url, m.media_preview_url, m.media_title,
                   m.reply_to, m.created_at, UNIX_TIMESTAMP(m.created_at) AS ts, m.edited_at, m.deleted_at,
                   u.username, u.display_name, u.ruolo, u.is_premium,
                   rm.id AS reply_id, rm.user_id AS reply_user_id, rm.message AS reply_message,
                   rm.message_type AS reply_type, rm.deleted_at AS reply_deleted, ru.username AS reply_username
            FROM messages m
            INNER JOIN utenti u ON u.id = m.user_id
            LEFT JOIN messages rm ON rm.id = m.reply_to
            LEFT JOIN utenti ru ON ru.id = rm.user_id
        ';
    }

    /** Badge del profilo mostrato accanto al nome, per più utenti insieme. */
    function gc_badges(mysqli $mysqli, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds || !rt_has_table($mysqli, 'utenti_profile_badges') || !rt_has_table($mysqli, 'achievement')) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $mysqli->prepare("
            SELECT b.utente_id, a.nome, a.img_url
            FROM utenti_profile_badges b
            INNER JOIN achievement a ON a.id = b.achievement_id
            WHERE b.is_visible = 1 AND b.utente_id IN ($marks)
            ORDER BY b.sort_order ASC, b.id ASC
        ");
        $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
        $stmt->execute();
        $result = $stmt->get_result();
        $badges = [];
        while ($row = $result->fetch_assoc()) {
            $id = (int)$row['utente_id'];
            if (!isset($badges[$id])) {
                $badges[$id] = [
                    'name' => (string)$row['nome'],
                    'image' => !empty($row['img_url']) ? '/img/' . ltrim((string)$row['img_url'], '/') : null,
                ];
            }
        }
        $stmt->close();
        return $badges;
    }

    function gc_reactions(mysqli $mysqli, array $messageIds): array
    {
        $messageIds = array_values(array_unique(array_filter(array_map('intval', $messageIds))));
        if (!$messageIds || !rt_has_table($mysqli, 'chat_reactions')) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $mysqli->prepare("SELECT message_id, emoji, user_id FROM chat_reactions WHERE message_id IN ($marks) ORDER BY id ASC");
        $stmt->bind_param(str_repeat('i', count($messageIds)), ...$messageIds);
        $stmt->execute();
        $result = $stmt->get_result();
        $map = [];
        while ($row = $result->fetch_assoc()) {
            $map[(int)$row['message_id']][(string)$row['emoji']][] = (int)$row['user_id'];
        }
        $stmt->close();

        $out = [];
        foreach ($map as $messageId => $byEmoji) {
            foreach ($byEmoji as $emoji => $users) {
                $out[$messageId][] = ['emoji' => (string)$emoji, 'count' => count($users), 'users' => array_slice($users, 0, 400)];
            }
        }
        return $out;
    }

    /** Dalle righe del database alla forma neutra, uguale per chiunque la legga. */
    function gc_neutral(mysqli $mysqli, array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $badges = gc_badges($mysqli, array_map(static fn($r) => (int)$r['user_id'], $rows));
        $reactions = gc_reactions($mysqli, array_map(static fn($r) => (int)$r['id'], $rows));

        $out = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $userId = (int)$row['user_id'];
            $deleted = !empty($row['deleted_at']);
            $role = (string)($row['ruolo'] ?? 'utente');
            $type = (string)($row['message_type'] ?? 'text');
            $type = in_array($type, ['text', 'gif'], true) ? $type : 'text';
            $text = $deleted ? '' : (string)$row['message'];

            $reply = null;
            if (!$deleted && !empty($row['reply_id'])) {
                $replyDeleted = !empty($row['reply_deleted']);
                $reply = [
                    'id' => (int)$row['reply_id'],
                    'user_id' => (int)$row['reply_user_id'],
                    'username' => (string)($row['reply_username'] ?? 'utente'),
                    'message' => $replyDeleted ? '' : cc_preview($row['reply_message'], 110),
                    'message_type' => $replyDeleted ? 'deleted' : (string)($row['reply_type'] ?? 'text'),
                ];
            }

            $out[] = [
                'id' => $id,
                'user_id' => $userId,
                'username' => (string)$row['username'],
                'display_name' => (string)(($row['display_name'] ?? '') ?: $row['username']),
                'is_premium' => (int)($row['is_premium'] ?? 0) === 1,
                'profile_url' => '/u/' . rawurlencode((string)$row['username']),
                'avatar_url' => '/includes/get_pfp.php?id=' . $userId,
                'role' => $role,
                'role_badge' => $role === 'owner' ? ['label' => 'Owner', 'class' => 'owner'] : ($role === 'admin' ? ['label' => 'Admin', 'class' => 'admin'] : null),
                'badge' => $badges[$userId] ?? null,
                'message_type' => $deleted ? 'text' : $type,
                'message' => $text,
                'media_url' => $deleted ? null : ($row['media_url'] ?? null),
                'media_preview_url' => $deleted ? null : ($row['media_preview_url'] ?? null),
                'media_title' => $deleted ? null : ($row['media_title'] ?? null),
                'created_at' => (string)$row['created_at'],
                'ts' => (int)$row['ts'],
                'edited_at' => $deleted ? null : ($row['edited_at'] ?? null),
                'is_deleted' => $deleted,
                'reactions' => $deleted ? [] : ($reactions[$id] ?? []),
                'reply' => $reply,
                'mentions' => $deleted ? [] : gc_mentions($text),
            ];
        }
        return $out;
    }

    /**
     * Messaggi della chat globale in forma neutra.
     * Opzioni: after, before, around, ids, search, limit.
     */
    function gc_fetch(mysqli $mysqli, array $options = []): array
    {
        $limit = max(1, min(80, (int)($options['limit'] ?? MESSAGES_PER_PAGE)));

        $run = static function (string $where, string $types, array $params, string $order, int $take) use ($mysqli): array {
            $stmt = $mysqli->prepare(gc_select() . ($where !== '' ? " WHERE $where" : '') . " ORDER BY m.id $order LIMIT ?");
            $all = array_merge($params, [$take]);
            $stmt->bind_param($types . 'i', ...$all);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        };

        if (!empty($options['ids'])) {
            $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array)$options['ids'])))), 0, 80);
            if (!$ids) {
                return [];
            }
            $marks = implode(',', array_fill(0, count($ids), '?'));
            return gc_neutral($mysqli, $run("m.id IN ($marks)", str_repeat('i', count($ids)), $ids, 'ASC', count($ids)));
        }

        if (!empty($options['around'])) {
            $around = (int)$options['around'];
            $half = (int)ceil($limit / 2);
            $older = array_reverse($run('m.id <= ?', 'i', [$around], 'DESC', $half));
            $newer = $run('m.id > ?', 'i', [$around], 'ASC', $half);
            return gc_neutral($mysqli, array_merge($older, $newer));
        }

        $where = [];
        $types = '';
        $params = [];
        $search = trim((string)($options['search'] ?? ''));
        if ($search !== '') {
            $search = mb_substr($search, 0, (int)CHAT_MAX_SEARCH_LENGTH, 'UTF-8');
            $like = '%' . addcslashes($search, '\\%_') . '%';
            $where[] = 'm.deleted_at IS NULL AND (m.message LIKE ? OR m.media_title LIKE ? OR u.username LIKE ?)';
            $types .= 'sss';
            array_push($params, $like, $like, $like);
        }

        if (!empty($options['after'])) {
            $where[] = 'm.id > ?';
            $types .= 'i';
            $params[] = (int)$options['after'];
            return gc_neutral($mysqli, $run(implode(' AND ', $where), $types, $params, 'ASC', $limit));
        }

        if (!empty($options['before'])) {
            $where[] = 'm.id < ?';
            $types .= 'i';
            $params[] = (int)$options['before'];
        }
        return gc_neutral($mysqli, array_reverse($run(implode(' AND ', $where), $types, $params, 'DESC', $limit)));
    }

    function gc_one(mysqli $mysqli, int $messageId): ?array
    {
        return gc_fetch($mysqli, ['ids' => [$messageId]])[0] ?? null;
    }

    /** Messaggi neutri → visti da un utente, tolti quelli di chi ha nascosto. */
    function gc_views(array $messages, array $user, array $hidden): array
    {
        $hiddenMap = array_flip($hidden);
        $out = [];
        foreach ($messages as $message) {
            if (isset($hiddenMap[(int)$message['user_id']])) {
                continue;
            }
            if (!empty($message['reply']) && isset($hiddenMap[(int)$message['reply']['user_id']])) {
                $message['reply']['message'] = '';
                $message['reply']['message_type'] = 'deleted';
            }
            $out[] = gc_view($message, (int)$user['id'], (string)$user['ruolo'], (string)$user['username']);
        }
        return $out;
    }

    /** Utenti nascosti a chi guarda (bloccati e mutati), dal timbro se è recente. */
    function gc_hidden(mysqli $mysqli, int $userId): array
    {
        $stamp = rt_read('u:' . $userId);
        if (isset($stamp['hidden']) && (time() - (int)($stamp['hidden_at'] ?? 0)) < 600) {
            return array_map('intval', $stamp['hidden']);
        }
        return sc_refresh_hidden($mysqli, $userId);
    }

    // ── Filtro parole ──────────────────────────────────────────────────────

    function gc_words(mysqli $mysqli): array
    {
        $cached = rt_read('words');
        if (!empty($cached['at']) && (time() - (int)$cached['at']) < 600 && isset($cached['list'])) {
            return $cached['list'];
        }

        $words = json_decode((string)CHAT_BANNED_WORDS, true);
        $words = is_array($words) ? $words : [];
        if (rt_has_table($mysqli, 'chat_word_filters')) {
            $result = $mysqli->query('SELECT word FROM chat_word_filters WHERE is_active = 1');
            while ($result && ($row = $result->fetch_row())) {
                $words[] = (string)$row[0];
            }
        }
        $words = array_values(array_unique(array_filter(array_map(static fn($w) => mb_strtolower(trim((string)$w), 'UTF-8'), $words))));
        rt_update('words', static fn() => ['at' => time(), 'list' => $words]);
        return $words;
    }

    function gc_has_bad_word(mysqli $mysqli, string $text): bool
    {
        if ($text === '') {
            return false;
        }
        $haystack = mb_strtolower($text, 'UTF-8');
        foreach (gc_words($mysqli) as $word) {
            if ($word !== '' && mb_strpos($haystack, $word, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }

    // ── Invio ──────────────────────────────────────────────────────────────

    function gc_settings(): array
    {
        $settings = rt_read('g')['settings'] ?? [];
        return [
            'slow' => max(0, min(GC_SLOW_MAX, (int)($settings['slow'] ?? (int)MESSAGE_TIMEOUT))),
            'pinned' => $settings['pinned'] ?? null,
        ];
    }

    /** Controlli che valgono per ogni testo che entra nella chat globale. */
    function gc_check_text(mysqli $mysqli, string $text, bool $allowEmpty): string
    {
        $text = cc_validate_text($text, $allowEmpty, (int)MAX_MESSAGE_LENGTH);
        if ($text !== '' && preg_match('/(.)\1{24,}/u', $text)) {
            throw new ChatError(rt_t('Messaggio troppo ripetitivo.', 'Message too repetitive.'), 422);
        }
        if (preg_match_all('#https?://#i', $text) > GC_MAX_LINKS) {
            throw new ChatError(rt_t('Troppi link in un solo messaggio.', 'Too many links in one message.'), 422);
        }
        if (gc_has_bad_word($mysqli, $text)) {
            throw new ChatError(rt_t('Messaggio bloccato dal filtro.', 'Message blocked by the filter.'), 422);
        }
        return $text;
    }

    function gc_send(mysqli $mysqli, array $user, array $input): array
    {
        $userId = (int)$user['id'];
        $isMod = !empty($user['is_mod']);
        $type = ($input['type'] ?? $input['message_type'] ?? 'text') === 'gif' ? 'gif' : 'text';
        $text = gc_check_text($mysqli, (string)($input['message'] ?? ''), $type === 'gif');

        // Il browser manda un codice unico per ogni invio. Se arriva due
        // volte (rete caduta, nuovo tentativo) il messaggio c'è già: lo si
        // restituisce così com'è, senza farlo passare dai limiti di ritmo.
        $sentNonce = trim((string)($input['client_nonce'] ?? ''));
        if (preg_match('/^[a-zA-Z0-9._:-]{8,90}$/', $sentNonce)) {
            $stmt = $mysqli->prepare('SELECT id FROM messages WHERE client_nonce = ? AND user_id = ? LIMIT 1');
            $stmt->bind_param('si', $sentNonce, $userId);
            $stmt->execute();
            $existingId = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
            if ($existingId > 0) {
                return gc_one($mysqli, $existingId) ?? [];
            }
        }

        if (!empty($user['chat_timeout_until']) && strtotime((string)$user['chat_timeout_until']) > time()) {
            $minutes = (int)ceil((strtotime((string)$user['chat_timeout_until']) - time()) / 60);
            throw new ChatError(rt_t(
                "Sei stato sospeso dalla chat globale. Potrai scrivere di nuovo tra $minutes min.",
                "You have been suspended from the global chat. You can write again in $minutes min."
            ), 403);
        }

        $mediaUrl = null;
        $mediaPreview = null;
        $mediaTitle = null;
        if ($type === 'gif') {
            $mediaUrl = trim((string)($input['media_url'] ?? ''));
            $mediaPreview = trim((string)($input['media_preview_url'] ?? $mediaUrl));
            if (!chat_is_allowed_gif_url($mediaUrl) || !chat_is_allowed_gif_url($mediaPreview)) {
                throw new ChatError(rt_t('GIF non valida.', 'Invalid GIF.'), 422);
            }
            $mediaTitle = mb_substr(cc_clean_text((string)($input['media_title'] ?? 'GIF')), 0, 160, 'UTF-8') ?: 'GIF';
        }

        // Ritmo: modalità lenta per tutti, più stretta per gli account appena nati.
        $stmt = $mysqli->prepare('SELECT message, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age FROM messages WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $last = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$isMod) {
            $wait = gc_settings()['slow'];
            $isNew = false;
            if (rt_has_col($mysqli, 'utenti', 'data_creazione')) {
                $stmt = $mysqli->prepare('SELECT TIMESTAMPDIFF(SECOND, data_creazione, NOW()) FROM utenti WHERE id = ?');
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $accountAge = $stmt->get_result()->fetch_row()[0] ?? null;
                $stmt->close();
                $isNew = $accountAge !== null && (int)$accountAge < GC_NEW_ACCOUNT_SECONDS;
            }
            if ($isNew) {
                $wait = max($wait, GC_NEW_ACCOUNT_WAIT);
                if (preg_match('#https?://#i', $text)) {
                    throw new ChatError(rt_t('Gli account appena creati non possono ancora mandare link.', 'Brand-new accounts cannot send links yet.'), 403);
                }
            }
            if ($last && $last['age'] !== null && (int)$last['age'] < $wait) {
                $left = $wait - (int)$last['age'];
                throw new ChatError(rt_t("Aspetta ancora {$left}s.", "Wait {$left}s more."), 429);
            }
            if ($last && $text !== '' && (int)$last['age'] < 60
                && mb_strtolower((string)$last['message'], 'UTF-8') === mb_strtolower($text, 'UTF-8')) {
                throw new ChatError(rt_t('Hai appena scritto la stessa cosa.', 'You just wrote the same thing.'), 429);
            }
        }

        $replyTo = (int)($input['reply_to'] ?? 0);
        if ($replyTo > 0) {
            $stmt = $mysqli->prepare('SELECT id FROM messages WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->bind_param('i', $replyTo);
            $stmt->execute();
            $replyTo = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
        }
        $replyParam = $replyTo > 0 ? $replyTo : null;

        $nonce = trim((string)($input['client_nonce'] ?? ''));
        $nonce = preg_match('/^[a-zA-Z0-9._:-]{8,90}$/', $nonce) ? $nonce : bin2hex(random_bytes(16));

        $messageId = 0;
        try {
            $stmt = $mysqli->prepare('INSERT INTO messages (user_id, message, message_type, media_url, media_preview_url, media_title, reply_to, created_at, client_nonce) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)');
            $stmt->bind_param('isssssis', $userId, $text, $type, $mediaUrl, $mediaPreview, $mediaTitle, $replyParam, $nonce);
            $stmt->execute();
            $messageId = (int)$mysqli->insert_id;
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // Stesso nonce mandato due volte (la rete è caduta e il browser
            // ha riprovato): si restituisce il messaggio già salvato.
            if ((int)$e->getCode() !== 1062) {
                throw $e;
            }
            $stmt = $mysqli->prepare('SELECT id FROM messages WHERE client_nonce = ? AND user_id = ? LIMIT 1');
            $stmt->bind_param('si', $nonce, $userId);
            $stmt->execute();
            $messageId = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
            if ($messageId > 0) {
                return gc_one($mysqli, $messageId) ?? [];
            }
            throw $e;
        }

        try {
            if (function_exists('trackMissionProgress')) {
                trackMissionProgress($mysqli, $userId, 'send_message');
                trackMissionProgress($mysqli, $userId, 'use_global_chat');
            }
        } catch (Throwable $e) {
            error_log('[chat globale] tracking: ' . $e->getMessage());
        }

        $message = gc_one($mysqli, $messageId);
        if (!$message) {
            throw new ChatError(rt_t('Invio non riuscito.', 'Could not send the message.'), 500);
        }

        rt_push_global(['t' => 'msg', 'm' => $message], static function (array $data) use ($userId): array {
            unset($data['typing'][(string)$userId]);
            return $data;
        });

        gc_notify_mentions($mysqli, $userId, $message);
        return $message;
    }

    /** Id degli utenti (non bannati) con uno di questi nomi: al massimo cinque per messaggio. */
    function gc_mention_targets(mysqli $mysqli, array $names): array
    {
        $names = array_slice(array_values($names), 0, 5);
        if (!$names) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($names), '?'));
        $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE username IN ($marks) AND isBannato = 0");
        $stmt->bind_param(str_repeat('s', count($names)), ...$names);
        $stmt->execute();
        $result = $stmt->get_result();
        $targets = [];
        while ($row = $result->fetch_row()) {
            $targets[] = (int)$row[0];
        }
        $stmt->close();
        return $targets;
    }

    /**
     * Avvisa le persone menzionate, se esistono e non c'è un blocco di mezzo.
     * La menzione resta «da vedere» (rt_mentions) finché non aprono la chat.
     */
    function gc_notify_mentions(mysqli $mysqli, int $senderId, array $message): void
    {
        try {
            $targets = gc_mention_targets($mysqli, $message['mentions'] ?? []);
            if (!$targets) {
                return;
            }
            $hidden = array_flip(sc_hidden_ids($mysqli, $senderId));
            foreach ($targets as $targetId) {
                if ($targetId === $senderId || isset($hidden[$targetId])) {
                    continue;
                }
                rt_mention_add($targetId, (int)$message['id'], $senderId);
            }
        } catch (Throwable $e) {
            error_log('[chat globale] menzioni: ' . $e->getMessage());
        }
    }

    /** Un messaggio eliminato non è più una menzione da vedere per nessuno. */
    function gc_forget_mentions(mysqli $mysqli, int $messageId, string $text): void
    {
        try {
            foreach (gc_mention_targets($mysqli, gc_mentions($text)) as $targetId) {
                rt_mentions_clear($targetId, [$messageId]);
            }
        } catch (Throwable $e) {
            error_log('[chat globale] menzioni eliminate: ' . $e->getMessage());
        }
    }

    function gc_row(mysqli $mysqli, int $messageId): array
    {
        $stmt = $mysqli->prepare('SELECT id, user_id, message, message_type, created_at, deleted_at, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age FROM messages WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $messageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            throw new ChatError(rt_t('Messaggio non trovato.', 'Message not found.'), 404);
        }
        return $row;
    }

    function gc_broadcast_update(mysqli $mysqli, int $messageId): ?array
    {
        $message = gc_one($mysqli, $messageId);
        if ($message) {
            rt_push_global(['t' => 'upd', 'm' => $message]);
        }
        return $message;
    }

    function gc_edit(mysqli $mysqli, array $user, int $messageId, string $text): array
    {
        $row = gc_row($mysqli, $messageId);
        if (!empty($row['deleted_at'])) {
            throw new ChatError(rt_t('Messaggio già eliminato.', 'Message already deleted.'), 422);
        }
        if ((int)$row['user_id'] !== (int)$user['id']) {
            throw new ChatError(rt_t('Puoi modificare solo i tuoi messaggi.', 'You can only edit your own messages.'), 403);
        }
        if ($row['message_type'] !== 'text') {
            throw new ChatError(rt_t('Questo messaggio non si può modificare.', 'This message cannot be edited.'), 422);
        }
        if (empty($user['is_mod']) && (int)$row['age'] > (int)CHAT_EDIT_WINDOW_SECONDS) {
            throw new ChatError(rt_t('Tempo per la modifica scaduto.', 'The time to edit has expired.'), 403);
        }

        $text = gc_check_text($mysqli, $text, false);
        $stmt = $mysqli->prepare('UPDATE messages SET message = ?, edited_at = NOW() WHERE id = ? AND user_id = ?');
        $userId = (int)$user['id'];
        $stmt->bind_param('sii', $text, $messageId, $userId);
        $stmt->execute();
        $stmt->close();

        return gc_broadcast_update($mysqli, $messageId) ?? [];
    }

    function gc_log(mysqli $mysqli, int $adminId, string $action, ?int $targetId, array $details): void
    {
        if (!rt_has_table($mysqli, 'admin_logs')) {
            return;
        }
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $json = $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $stmt = $mysqli->prepare('INSERT INTO admin_logs (admin_id, target_user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('iisss', $adminId, $targetId, $action, $json, $ip);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            error_log('[chat globale] log staff: ' . $e->getMessage());
        }
    }

    function gc_delete(mysqli $mysqli, array $user, int $messageId, string $reason = ''): array
    {
        $row = gc_row($mysqli, $messageId);
        if (!empty($row['deleted_at'])) {
            return gc_one($mysqli, $messageId) ?? [];
        }

        $userId = (int)$user['id'];
        $mine = (int)$row['user_id'] === $userId;
        if (!$mine && empty($user['is_mod'])) {
            throw new ChatError(rt_t('Non puoi eliminare questo messaggio.', 'You cannot delete this message.'), 403);
        }

        $stmt = $mysqli->prepare('UPDATE messages SET message = "", deleted_at = NOW(), deleted_by = ? WHERE id = ?');
        $stmt->bind_param('ii', $userId, $messageId);
        $stmt->execute();
        $stmt->close();

        if (rt_has_table($mysqli, 'chat_reactions')) {
            $stmt = $mysqli->prepare('DELETE FROM chat_reactions WHERE message_id = ?');
            $stmt->bind_param('i', $messageId);
            $stmt->execute();
            $stmt->close();
        }

        gc_forget_mentions($mysqli, $messageId, (string)$row['message']);

        if (!$mine) {
            $reason = mb_substr(cc_clean_text($reason), 0, 200, 'UTF-8');
            gc_log($mysqli, $userId, 'chat_delete_message', (int)$row['user_id'], [
                'message_id' => $messageId,
                'content' => mb_substr((string)$row['message'], 0, 500, 'UTF-8'),
                'reason' => $reason,
            ]);

            if (function_exists('sendSecurityInboxMessage')) {
                $sent = date('d/m/Y H:i', strtotime((string)$row['created_at']));
                $quoted = str_replace(['*', '[', ']', '`'], '', mb_substr((string)$row['message'], 0, 300, 'UTF-8'));
                $whyIt = $reason !== '' ? "\n- **Motivo:** " . str_replace(['*', '[', ']'], '', $reason) : '';
                $whyEn = $reason !== '' ? "\n- **Reason:** " . str_replace(['*', '[', ']'], '', $reason) : '';
                try {
                    sendSecurityInboxMessage(
                        $mysqli,
                        (int)$row['user_id'],
                        'Messaggio rimosso dalla Chat Globale',
                        'Message removed from Global Chat',
                        "Un moderatore ha rimosso un tuo messaggio nella Chat Globale per violazione delle linee guida.\n\n- **Inviato il:** $sent\n- **Contenuto:** \"$quoted\"$whyIt\n\nTi invitiamo a rispettare le [linee guida](/it/chat-policy).",
                        "A moderator removed one of your messages in the Global Chat for breaking the guidelines.\n\n- **Sent on:** $sent\n- **Content:** \"$quoted\"$whyEn\n\nPlease follow the [guidelines](/en/chat-policy).",
                        'system'
                    );
                } catch (Throwable $e) {
                    error_log('[chat globale] avviso rimozione: ' . $e->getMessage());
                }
            }
        }

        // Se era il messaggio fissato in alto, l'avviso decade.
        $pinned = gc_settings()['pinned'];
        if (is_array($pinned) && (int)($pinned['id'] ?? 0) === $messageId) {
            gc_set_pinned($mysqli, null);
        }

        return gc_broadcast_update($mysqli, $messageId) ?? [];
    }

    function gc_react(mysqli $mysqli, array $user, int $messageId, string $emoji): array
    {
        $row = gc_row($mysqli, $messageId);
        if (!empty($row['deleted_at'])) {
            throw new ChatError(rt_t('Messaggio non trovato.', 'Message not found.'), 404);
        }
        if (!rt_has_table($mysqli, 'chat_reactions')) {
            throw new ChatError(rt_t('Reazioni non disponibili.', 'Reactions are not available.'), 503);
        }
        $length = rt_col_len($mysqli, 'chat_reactions', 'emoji') ?: 16;
        if (!cc_valid_reaction($mysqli, $emoji, $length)) {
            throw new ChatError(rt_t('Reazione non valida.', 'Invalid reaction.'), 422);
        }

        $userId = (int)$user['id'];
        $stmt = $mysqli->prepare('DELETE FROM chat_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?');
        $stmt->bind_param('iis', $messageId, $userId, $emoji);
        $stmt->execute();
        $removed = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$removed) {
            $stmt = $mysqli->prepare('SELECT COUNT(*) FROM chat_reactions WHERE message_id = ? AND user_id = ?');
            $stmt->bind_param('ii', $messageId, $userId);
            $stmt->execute();
            $mine = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
            if ($mine >= 6) {
                throw new ChatError(rt_t('Hai già messo troppe reazioni a questo messaggio.', 'You already added too many reactions to this message.'), 422);
            }
            $stmt = $mysqli->prepare('INSERT INTO chat_reactions (message_id, user_id, emoji, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->bind_param('iis', $messageId, $userId, $emoji);
            $stmt->execute();
            $stmt->close();
        }

        return gc_broadcast_update($mysqli, $messageId) ?? [];
    }

    // ── Presenza e conteggio online ────────────────────────────────────────

    function gc_presence_entry(array $user): array
    {
        return [
            'u' => (string)$user['username'],
            'd' => (string)(($user['display_name'] ?? '') ?: $user['username']),
            'r' => (string)$user['ruolo'],
            'p' => !empty($user['is_premium']) ? 1 : 0,
        ];
    }

    /** Riconta gli utenti online sul sito, al massimo una volta ogni mezzo minuto in tutto. */
    function gc_online_refresh(mysqli $mysqli, bool $force = false): void
    {
        if (!$force && !gc_online_stale(rt_read('g'))) {
            return;
        }
        $window = max(60, (int)CHAT_ONLINE_WINDOW);
        $stmt = $mysqli->prepare('SELECT COUNT(*) FROM utenti WHERE ultimo_accesso >= NOW() - INTERVAL ? SECOND AND isBannato = 0');
        $stmt->bind_param('i', $window);
        $stmt->execute();
        $count = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();

        rt_push_global(null, static function (array $data) use ($count): array {
            $data['online'] = ['at' => time(), 'count' => $count];
            return $data;
        });
    }

    // ── Strumenti dello staff ──────────────────────────────────────────────

    function gc_require_mod(array $user): void
    {
        if (empty($user['is_mod'])) {
            throw new ChatError(rt_t('Azione riservata allo staff.', 'Staff only.'), 403);
        }
    }

    /** Fissa in alto un messaggio (o toglie l'avviso con null). */
    function gc_set_pinned(mysqli $mysqli, ?array $message): void
    {
        $hasColumn = rt_has_col($mysqli, 'messages', 'pinned_at');
        if ($hasColumn) {
            $mysqli->query('UPDATE messages SET pinned_at = NULL WHERE pinned_at IS NOT NULL');
            if ($message) {
                $stmt = $mysqli->prepare('UPDATE messages SET pinned_at = NOW() WHERE id = ?');
                $id = (int)$message['id'];
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
        }

        $pinned = $message ? [
            'id' => (int)$message['id'],
            'user_id' => (int)$message['user_id'],
            'username' => (string)$message['username'],
            'message' => $message['message_type'] === 'gif' ? '[GIF] ' . (string)$message['message'] : cc_preview($message['message'], 220),
            'ts' => (int)$message['ts'],
        ] : null;

        rt_push_global(null, static function (array $data) use ($pinned): array {
            $data['settings']['pinned'] = $pinned;
            return $data;
        });
    }

    /**
     * Rimette nel timbro l'avviso fissato se il timbro è stato ricreato:
     * con la colonna `pinned_at` l'avviso sopravvive anche a quello.
     */
    function gc_restore_pinned(mysqli $mysqli): void
    {
        $stamp = rt_read('g');
        if (isset($stamp['settings']) && array_key_exists('pinned', $stamp['settings'])) {
            return;
        }
        $message = null;
        if (rt_has_col($mysqli, 'messages', 'pinned_at')) {
            $result = $mysqli->query('SELECT id FROM messages WHERE pinned_at IS NOT NULL AND deleted_at IS NULL ORDER BY pinned_at DESC LIMIT 1');
            $row = $result ? $result->fetch_row() : null;
            if ($row) {
                $message = gc_one($mysqli, (int)$row[0]);
            }
        }
        $pinned = $message ? [
            'id' => (int)$message['id'],
            'user_id' => (int)$message['user_id'],
            'username' => (string)$message['username'],
            'message' => cc_preview($message['message'], 220),
            'ts' => (int)$message['ts'],
        ] : null;
        rt_push_global(null, static function (array $data) use ($pinned): array {
            $data['settings']['pinned'] = $pinned;
            return $data;
        });
    }

    function gc_set_slow(int $seconds): int
    {
        $seconds = max(0, min(GC_SLOW_MAX, $seconds));
        rt_push_global(null, static function (array $data) use ($seconds): array {
            $data['settings']['slow'] = $seconds;
            return $data;
        });
        return $seconds;
    }

    /** Sospende un utente dalla chat globale per un po' (0 = toglie la sospensione). */
    function gc_timeout(mysqli $mysqli, array $admin, int $targetId, int $minutes, string $reason = ''): ?int
    {
        if (!rt_has_col($mysqli, 'utenti', 'chat_timeout_until')) {
            throw new ChatError(rt_t(
                'La sospensione dalla chat non è ancora attiva su questo server (manca la migration).',
                'Chat suspension is not available on this server yet (migration missing).'
            ), 503);
        }
        $target = sc_user_brief($mysqli, $targetId);
        if (!$target || $targetId === (int)$admin['id']) {
            throw new ChatError(rt_t('Utente non valido.', 'Invalid user.'), 422);
        }
        if (in_array($target['ruolo'], ['admin', 'owner'], true) && $admin['ruolo'] !== 'owner') {
            throw new ChatError(rt_t('Non puoi sospendere un membro dello staff.', 'You cannot suspend a staff member.'), 403);
        }

        $minutes = max(0, min(7 * 24 * 60, $minutes));
        $until = $minutes > 0 ? date('Y-m-d H:i:s', time() + $minutes * 60) : null;
        $stmt = $mysqli->prepare('UPDATE utenti SET chat_timeout_until = ? WHERE id = ?');
        $stmt->bind_param('si', $until, $targetId);
        $stmt->execute();
        $stmt->close();

        gc_log($mysqli, (int)$admin['id'], $minutes > 0 ? 'chat_timeout' : 'chat_timeout_remove', $targetId, [
            'minutes' => $minutes,
            'reason' => mb_substr(cc_clean_text($reason), 0, 200, 'UTF-8'),
        ]);

        if ($minutes > 0 && function_exists('sendSecurityInboxMessage')) {
            $label = $minutes >= 1440 ? round($minutes / 1440, 1) . ' g' : ($minutes >= 60 ? round($minutes / 60, 1) . ' h' : $minutes . ' min');
            try {
                sendSecurityInboxMessage(
                    $mysqli,
                    $targetId,
                    'Sospensione dalla Chat Globale',
                    'Global Chat suspension',
                    "Lo staff ti ha sospeso dalla Chat Globale per **$label**. Durante la sospensione puoi leggere ma non scrivere.\n\nRileggi le [linee guida](/it/chat-policy).",
                    "The staff suspended you from the Global Chat for **$label**. While suspended you can read but not write.\n\nPlease read the [guidelines](/en/chat-policy) again.",
                    'system'
                );
            } catch (Throwable $e) {
                error_log('[chat globale] avviso sospensione: ' . $e->getMessage());
            }
        }

        return $until ? strtotime($until) : null;
    }

    function gc_words_list(mysqli $mysqli): array
    {
        if (!rt_has_table($mysqli, 'chat_word_filters')) {
            return [];
        }
        $result = $mysqli->query('SELECT id, word, is_active FROM chat_word_filters ORDER BY word ASC');
        $list = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $list[] = ['id' => (int)$row['id'], 'word' => (string)$row['word'], 'is_active' => (int)$row['is_active'] === 1];
        }
        return $list;
    }

    function gc_words_change(mysqli $mysqli, array $admin, string $action, string $word, int $wordId = 0): void
    {
        if (!rt_has_table($mysqli, 'chat_word_filters')) {
            throw new ChatError(rt_t('Il filtro parole non è disponibile.', 'The word filter is not available.'), 503);
        }

        if ($action === 'add') {
            $word = mb_strtolower(cc_clean_text($word), 'UTF-8');
            if (mb_strlen($word, 'UTF-8') < 2 || mb_strlen($word, 'UTF-8') > 80) {
                throw new ChatError(rt_t('Parola non valida (da 2 a 80 caratteri).', 'Invalid word (2 to 80 characters).'), 422);
            }
            $stmt = $mysqli->prepare('SELECT id FROM chat_word_filters WHERE word = ? LIMIT 1');
            $stmt->bind_param('s', $word);
            $stmt->execute();
            $existing = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
            if ($existing > 0) {
                $stmt = $mysqli->prepare('UPDATE chat_word_filters SET is_active = 1 WHERE id = ?');
                $stmt->bind_param('i', $existing);
            } else {
                $stmt = $mysqli->prepare('INSERT INTO chat_word_filters (word, is_active) VALUES (?, 1)');
                $stmt->bind_param('s', $word);
            }
            $stmt->execute();
            $stmt->close();
            gc_log($mysqli, (int)$admin['id'], 'chat_word_add', null, ['word' => $word]);
        } else {
            $stmt = $mysqli->prepare('DELETE FROM chat_word_filters WHERE id = ?');
            $stmt->bind_param('i', $wordId);
            $stmt->execute();
            $stmt->close();
            gc_log($mysqli, (int)$admin['id'], 'chat_word_remove', null, ['word_id' => $wordId]);
        }

        $path = rt_path('words');
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}
