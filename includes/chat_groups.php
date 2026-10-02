<?php
/**
 * Cripsum™ — gruppi di chat.
 *
 * Stesso formato dei messaggi delle chat private (vedi chat_core.php), stesse
 * regole di base: si legge e si scrive in un gruppo solo da membri attivi,
 * una risposta cita solo messaggi dello stesso gruppo, gli allegati mostrati
 * sono solo quelli che il server ha salvato (mai dati arrivati dal browser).
 *
 * Chi è soltanto invitato vede l'invito, non i messaggi.
 */

require_once __DIR__ . '/chat_core.php';

if (!defined('CG_MAX_MEMBERS')) {
    define('CG_MAX_MEMBERS', 50);
    define('CG_NAME_MAX', 60);
    define('CG_DESC_MAX', 300);
    define('CG_CREATE_PER_DAY', 10);
    define('CG_INVITES_PER_HOUR', 40);
}

if (!function_exists('cg_member')) {

    function cg_member(mysqli $mysqli, int $chatId, int $userId): ?array
    {
        if ($chatId <= 0) {
            return null;
        }
        $stmt = $mysqli->prepare('
            SELECT m.role, m.status, m.last_read_message_id, m.muted_until, m.notification_level, m.is_archived,
                   c.name, c.description, c.avatar_url, c.created_by, c.is_archived AS chat_closed
            FROM chat_members m
            INNER JOIN chats c ON c.id = m.chat_id
            WHERE m.chat_id = ? AND m.user_id = ?
            LIMIT 1
        ');
        $stmt->bind_param('ii', $chatId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $row['chat_id'] = $chatId;
        $row['user_id'] = $userId;
        $row['last_read_message_id'] = (int)($row['last_read_message_id'] ?? 0);
        $row['muted'] = $row['notification_level'] === 'muted'
            || (!empty($row['muted_until']) && strtotime((string)$row['muted_until']) > time());
        $row['is_staff'] = in_array($row['role'], ['owner', 'admin'], true);
        return $row;
    }

    /** Membro attivo, o errore. È il controllo d'accesso di ogni operazione sui gruppi. */
    function cg_require(mysqli $mysqli, int $chatId, int $userId): array
    {
        $member = cg_member($mysqli, $chatId, $userId);
        if (!$member || $member['status'] !== 'active') {
            throw new ChatError(rt_t('Gruppo non trovato.', 'Group not found.'), 404);
        }
        return $member;
    }

    function cg_settings(mysqli $mysqli, int $chatId): array
    {
        $stmt = $mysqli->prepare('SELECT invite_permission, edit_info_permission, message_permission FROM chat_settings WHERE chat_id = ? LIMIT 1');
        $stmt->bind_param('i', $chatId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return [
            'invite_permission' => ($row['invite_permission'] ?? 'everyone') === 'owner_admins' ? 'owner_admins' : 'everyone',
            'edit_info_permission' => ($row['edit_info_permission'] ?? 'owner_admins') === 'everyone' ? 'everyone' : 'owner_admins',
            'message_permission' => ($row['message_permission'] ?? 'members') === 'admins_only' ? 'admins_only' : 'members',
        ];
    }

    function cg_member_ids(mysqli $mysqli, int $chatId): array
    {
        $stmt = $mysqli->prepare("SELECT user_id FROM chat_members WHERE chat_id = ? AND status = 'active'");
        $stmt->bind_param('i', $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_row()) {
            $ids[] = (int)$row[0];
        }
        $stmt->close();
        return $ids;
    }

    /** Avvisa i membri attivi di un gruppo che è cambiato qualcosa. */
    function cg_signal(mysqli $mysqli, int $chatId, array $event, ?array $onlyIds = null): void
    {
        $event['g'] = $chatId;
        rt_push_users($onlyIds ?? cg_member_ids($mysqli, $chatId), $event);
    }

    /**
     * Messaggio di servizio («X è entrato», «Y ha rinominato il gruppo»).
     * Il testo salvato è in italiano per i client vecchi; i metadati portano
     * il tipo di evento e i nomi, così la pagina lo scrive nella lingua di
     * chi legge.
     */
    function cg_system(mysqli $mysqli, int $chatId, string $event, array $meta = []): int
    {
        $name = static fn(string $key, string $fallback) => (string)($meta[$key] ?? $fallback);
        $body = match ($event) {
            'create' => 'Il gruppo è stato creato.',
            'join' => '@' . $name('username', 'utente') . ' è entrato nel gruppo.',
            'leave' => '@' . $name('username', 'utente') . ' ha lasciato il gruppo.',
            'invite' => '@' . $name('inviter', 'utente') . ' ha invitato @' . $name('invitee', 'utente') . '.',
            'remove' => '@' . $name('actor', 'utente') . ' ha rimosso @' . $name('target', 'utente') . ' dal gruppo.',
            'rename' => '@' . $name('username', 'utente') . ' ha rinominato il gruppo in "' . $name('new_name', '') . '".',
            'avatar' => '@' . $name('username', 'utente') . ' ha cambiato l\'immagine del gruppo.',
            'promote' => '@' . $name('actor', 'utente') . ' ha promosso @' . $name('target', 'utente') . ' ad admin.',
            'demote' => '@' . $name('actor', 'utente') . ' ha tolto i privilegi di admin a @' . $name('target', 'utente') . '.',
            'owner' => '@' . $name('target', 'utente') . ' è il nuovo proprietario del gruppo.',
            default => '',
        };
        if ($body === '') {
            return 0;
        }

        $meta['event'] = $event;
        $json = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $sender = 0;
        $stmt = $mysqli->prepare("INSERT INTO chat_messages (chat_id, sender_id, body, message_type, metadata_json) VALUES (?, ?, ?, 'system', ?)");
        $stmt->bind_param('iiss', $chatId, $sender, $body, $json);
        $stmt->execute();
        $messageId = (int)$mysqli->insert_id;
        $stmt->close();

        $stmt = $mysqli->prepare('UPDATE chats SET last_message_id = ?, last_message_at = NOW() WHERE id = ?');
        $stmt->bind_param('ii', $messageId, $chatId);
        $stmt->execute();
        $stmt->close();

        return $messageId;
    }

    function cg_username(mysqli $mysqli, int $userId): string
    {
        return sc_user_brief($mysqli, $userId)['username'] ?? 'utente';
    }

    // ── Lettura ────────────────────────────────────────────────────────────

    function cg_select(): string
    {
        return '
            SELECT m.id, m.chat_id, m.sender_id, m.body, m.message_type, m.reply_to_message_id, m.metadata_json,
                   m.edited_at, m.created_at, UNIX_TIMESTAMP(m.created_at) AS ts,
                   u.username AS sender_username, u.display_name AS sender_display_name,
                   u.ruolo AS sender_role, u.is_premium AS sender_premium,
                   rm.id AS reply_id, rm.sender_id AS reply_sender_id, rm.body AS reply_text,
                   rm.message_type AS reply_type, ru.username AS reply_username
            FROM chat_messages m
            LEFT JOIN utenti u ON u.id = m.sender_id
            LEFT JOIN chat_messages rm ON rm.id = m.reply_to_message_id AND rm.chat_id = m.chat_id AND rm.deleted_at IS NULL
            LEFT JOIN utenti ru ON ru.id = rm.sender_id
        ';
    }

    function cg_hydrate(mysqli $mysqli, int $viewerId, array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $ids = array_map(static fn($r) => (int)$r['id'], $rows);
        $reactions = cc_reactions($mysqli, 'group_chat_reactions', $viewerId, $ids);

        $out = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $meta = $row['metadata_json'] ? json_decode((string)$row['metadata_json'], true) : null;
            $meta = is_array($meta) ? $meta : [];
            $type = (string)$row['message_type'];
            if (!in_array($type, ['text', 'gif', 'media', 'system'], true)) {
                $type = 'text';
            }

            // I metadati sono stati a lungo scrivibili dal browser: da qui
            // escono solo percorsi di allegati veri e GIF dei siti ammessi.
            $attachments = cc_clean_attachments($meta['attachments'] ?? []);
            $mediaUrl = null;
            $mediaTitle = null;
            if ($type === 'gif') {
                $candidate = (string)($meta['media_url'] ?? '');
                if (function_exists('chat_is_allowed_gif_url') && chat_is_allowed_gif_url($candidate)) {
                    $mediaUrl = $candidate;
                    $mediaTitle = mb_substr((string)($meta['media_title'] ?? 'GIF'), 0, 160, 'UTF-8');
                } else {
                    $type = 'text';
                }
            }

            $reply = null;
            if (!empty($row['reply_id'])) {
                $reply = [
                    'id' => (int)$row['reply_id'],
                    'sender_id' => (int)$row['reply_sender_id'],
                    'username' => (string)($row['reply_username'] ?? ''),
                    'text' => cc_preview($row['reply_text'], 140),
                    'type' => (string)$row['reply_type'],
                    'deleted' => false,
                ];
            }

            $system = null;
            if ($type === 'system') {
                $system = ['event' => (string)($meta['event'] ?? '')];
                foreach (['username', 'inviter', 'invitee', 'actor', 'target', 'new_name'] as $key) {
                    if (isset($meta[$key]) && is_string($meta[$key])) {
                        $system[$key] = $meta[$key];
                    }
                }
            }

            $body = $row['body'] !== null ? (string)$row['body'] : null;
            $out[] = [
                'id' => $id,
                'kind' => 'group',
                'chat_id' => (int)$row['chat_id'],
                'sender_id' => (int)$row['sender_id'],
                'sender_username' => (string)($row['sender_username'] ?? ''),
                'sender_display_name' => (string)(($row['sender_display_name'] ?? '') ?: ($row['sender_username'] ?? '')),
                'sender_role' => (string)($row['sender_role'] ?? 'utente'),
                'sender_premium' => (int)($row['sender_premium'] ?? 0) === 1,
                'message_type' => $type,
                'body' => $body,
                'message' => $body,
                'media_url' => $mediaUrl,
                'media_title' => $mediaTitle,
                'metadata' => $type === 'gif' ? ['media_url' => $mediaUrl, 'media_title' => $mediaTitle] : null,
                'attachments' => $attachments,
                'reactions' => $reactions[$id] ?? [],
                'reply' => $reply,
                'reply_to_message_id' => $reply ? $reply['id'] : null,
                'forwarded' => !empty($meta['forwarded']),
                'is_edited' => !empty($row['edited_at']),
                'edited_at' => $row['edited_at'],
                'is_deleted' => false,
                'is_pinned' => false,
                'is_favorite' => false,
                'system' => $system,
                'created_at' => (string)$row['created_at'],
                'ts' => (int)$row['ts'],
            ];
        }
        return $out;
    }

    function cg_fetch(mysqli $mysqli, int $viewerId, int $chatId, array $options = []): array
    {
        $limit = max(1, min(80, (int)($options['limit'] ?? CC_PAGE)));

        $run = static function (string $extra, string $types, array $params, string $order, int $take) use ($mysqli, $chatId): array {
            $stmt = $mysqli->prepare(cg_select() . " WHERE m.chat_id = ? AND m.deleted_at IS NULL $extra ORDER BY m.id $order LIMIT ?");
            $all = array_merge([$chatId], $params, [$take]);
            $stmt->bind_param('i' . $types . 'i', ...$all);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        };

        $hasMore = false;
        $hasNewer = false;
        $gone = [];

        if (!empty($options['ids'])) {
            $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array)$options['ids'])))), 0, 80);
            if (!$ids) {
                return ['messages' => [], 'has_more' => false, 'has_newer' => false, 'gone' => []];
            }
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $rows = $run("AND m.id IN ($marks)", str_repeat('i', count($ids)), $ids, 'ASC', count($ids));
            $gone = array_values(array_diff($ids, array_map(static fn($r) => (int)$r['id'], $rows)));
        } elseif (!empty($options['around'])) {
            $around = (int)$options['around'];
            $half = (int)ceil($limit / 2);
            $older = array_reverse($run('AND m.id <= ?', 'i', [$around], 'DESC', $half + 1));
            $newer = $run('AND m.id > ?', 'i', [$around], 'ASC', $half + 1);
            $hasMore = count($older) > $half;
            $hasNewer = count($newer) > $half;
            if ($hasMore) {
                array_shift($older);
            }
            if ($hasNewer) {
                array_pop($newer);
            }
            $rows = array_merge($older, $newer);
        } elseif (!empty($options['after'])) {
            $rows = $run('AND m.id > ?', 'i', [(int)$options['after']], 'ASC', $limit + 1);
            $hasNewer = count($rows) > $limit;
            if ($hasNewer) {
                array_pop($rows);
            }
        } else {
            $extra = '';
            $types = '';
            $params = [];
            if (!empty($options['before'])) {
                $extra = 'AND m.id < ?';
                $types = 'i';
                $params = [(int)$options['before']];
            }
            $rows = $run($extra, $types, $params, 'DESC', $limit + 1);
            $hasMore = count($rows) > $limit;
            if ($hasMore) {
                array_pop($rows);
            }
            $rows = array_reverse($rows);
        }

        return [
            'messages' => cg_hydrate($mysqli, $viewerId, $rows),
            'has_more' => $hasMore,
            'has_newer' => $hasNewer,
            'gone' => $gone,
        ];
    }

    function cg_one(mysqli $mysqli, int $viewerId, int $chatId, int $messageId): ?array
    {
        return cg_fetch($mysqli, $viewerId, $chatId, ['ids' => [$messageId]])['messages'][0] ?? null;
    }

    /** Riga di un messaggio di gruppo più l'appartenenza di chi chiede. */
    function cg_message_context(mysqli $mysqli, int $messageId, int $userId): array
    {
        $stmt = $mysqli->prepare('SELECT id, chat_id, sender_id, message_type, metadata_json, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age FROM chat_messages WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->bind_param('i', $messageId);
        $stmt->execute();
        $message = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $member = $message ? cg_member($mysqli, (int)$message['chat_id'], $userId) : null;
        if (!$message || !$member || $member['status'] !== 'active') {
            throw new ChatError(rt_t('Messaggio non trovato.', 'Message not found.'), 404);
        }
        return ['message' => $message, 'member' => $member];
    }

    function cg_mark_read(mysqli $mysqli, array $member, int $upTo = 0): int
    {
        $chatId = (int)$member['chat_id'];
        $userId = (int)$member['user_id'];

        $stmt = $mysqli->prepare('SELECT MAX(id) FROM chat_messages WHERE chat_id = ? AND deleted_at IS NULL');
        $stmt->bind_param('i', $chatId);
        $stmt->execute();
        $max = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();

        $target = $upTo > 0 ? min($upTo, $max) : $max;
        if ($target <= $member['last_read_message_id']) {
            return $member['last_read_message_id'];
        }

        $stmt = $mysqli->prepare('UPDATE chat_members SET last_read_message_id = ?, last_read_at = NOW() WHERE chat_id = ? AND user_id = ?');
        $stmt->bind_param('iii', $target, $chatId, $userId);
        $stmt->execute();
        $stmt->close();

        rt_push_user($userId, ['t' => 'rd', 'g' => $chatId, 'f' => $userId, 'm' => $target]);
        return $target;
    }

    // ── Invio e azioni sui messaggi ────────────────────────────────────────

    function cg_send(mysqli $mysqli, int $userId, int $chatId, array $input, array $attachments = []): array
    {
        $member = cg_require($mysqli, $chatId, $userId);
        if ((int)$member['chat_closed'] === 1) {
            throw new ChatError(rt_t('Questo gruppo è stato chiuso.', 'This group has been closed.'), 403);
        }
        if (cg_settings($mysqli, $chatId)['message_permission'] === 'admins_only' && !$member['is_staff']) {
            throw new ChatError(rt_t('In questo gruppo possono scrivere solo gli amministratori.', 'Only admins can write in this group.'), 403);
        }

        $type = (string)($input['message_type'] ?? 'text');
        $type = $attachments ? 'media' : ($type === 'gif' ? 'gif' : 'text');
        $text = cc_validate_text((string)($input['message'] ?? ''), $type !== 'text');

        $meta = [];
        if ($type === 'gif') {
            $mediaUrl = trim((string)($input['media_url'] ?? ''));
            if (!function_exists('chat_is_allowed_gif_url') || !chat_is_allowed_gif_url($mediaUrl)) {
                throw new ChatError(rt_t('GIF non valida.', 'Invalid GIF.'), 422);
            }
            $meta['media_url'] = $mediaUrl;
            $meta['media_title'] = mb_substr(cc_clean_text((string)($input['media_title'] ?? 'GIF')), 0, 160, 'UTF-8') ?: 'GIF';
        }
        if ($attachments) {
            $meta['attachments'] = $attachments;
        }
        if (!empty($input['forwarded'])) {
            $meta['forwarded'] = true;
        }

        cc_rate_check($mysqli, 'chat_messages', $userId);

        $replyId = (int)($input['reply_to_message_id'] ?? $input['reply_to_id'] ?? 0);
        if ($replyId > 0) {
            $stmt = $mysqli->prepare("SELECT id FROM chat_messages WHERE id = ? AND chat_id = ? AND deleted_at IS NULL AND message_type <> 'system' LIMIT 1");
            $stmt->bind_param('ii', $replyId, $chatId);
            $stmt->execute();
            $replyId = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
        }
        $replyParam = $replyId > 0 ? $replyId : null;
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $body = $text === '' ? null : $text;

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare('INSERT INTO chat_messages (chat_id, sender_id, body, message_type, reply_to_message_id, metadata_json) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iissis', $chatId, $userId, $body, $type, $replyParam, $metaJson);
            $stmt->execute();
            $messageId = (int)$mysqli->insert_id;
            $stmt->close();

            $stmt = $mysqli->prepare('UPDATE chats SET last_message_id = ?, last_message_at = NOW() WHERE id = ?');
            $stmt->bind_param('ii', $messageId, $chatId);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare('UPDATE chat_members SET last_read_message_id = ?, last_read_at = NOW(), is_archived = 0 WHERE chat_id = ? AND user_id = ?');
            $stmt->bind_param('iii', $messageId, $chatId, $userId);
            $stmt->execute();
            $stmt->close();

            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        try {
            if (function_exists('trackMissionProgress')) {
                trackMissionProgress($mysqli, $userId, 'send_message');
                trackMissionProgress($mysqli, $userId, 'send_group_message');
            }
            if (function_exists('stats_track')) {
                stats_track($mysqli, $userId, 'msg_group');
            }
        } catch (Throwable $e) {
            error_log('[chat] tracking gruppo: ' . $e->getMessage());
        }

        // Un evento nel timbro di ogni membro: niente più messaggio nella
        // posta per ogni riga scritta in un gruppo.
        cg_signal($mysqli, $chatId, ['t' => 'gm', 'm' => $messageId, 'f' => $userId]);

        return ['message' => cg_one($mysqli, $userId, $chatId, $messageId), 'chat_id' => $chatId];
    }

    function cg_edit(mysqli $mysqli, int $userId, int $messageId, string $text): array
    {
        $context = cg_message_context($mysqli, $messageId, $userId);
        $message = $context['message'];
        if ((int)$message['sender_id'] !== $userId || $message['message_type'] === 'system') {
            throw new ChatError(rt_t('Puoi modificare solo i tuoi messaggi.', 'You can only edit your own messages.'), 403);
        }
        if ((int)$message['age'] > CC_EDIT_WINDOW) {
            throw new ChatError(rt_t('È passato troppo tempo per modificarlo.', 'Too much time has passed to edit it.'), 403);
        }

        $text = cc_validate_text($text, $message['message_type'] !== 'text');
        $body = $text === '' ? null : $text;
        $stmt = $mysqli->prepare('UPDATE chat_messages SET body = ?, edited_at = NOW() WHERE id = ?');
        $stmt->bind_param('si', $body, $messageId);
        $stmt->execute();
        $stmt->close();

        $chatId = (int)$message['chat_id'];
        cg_signal($mysqli, $chatId, ['t' => 'gu', 'm' => $messageId]);
        return ['message' => cg_one($mysqli, $userId, $chatId, $messageId)];
    }

    function cg_delete(mysqli $mysqli, int $userId, int $messageId): array
    {
        $context = cg_message_context($mysqli, $messageId, $userId);
        $message = $context['message'];
        $mine = (int)$message['sender_id'] === $userId;
        if (!$mine && !$context['member']['is_staff']) {
            throw new ChatError(rt_t('Non puoi eliminare questo messaggio.', 'You cannot delete this message.'), 403);
        }
        if ($message['message_type'] === 'system') {
            throw new ChatError(rt_t('I messaggi di servizio non si eliminano.', 'Service messages cannot be deleted.'), 422);
        }

        $stmt = $mysqli->prepare('UPDATE chat_messages SET deleted_at = NOW(), deleted_by = ? WHERE id = ?');
        $stmt->bind_param('ii', $userId, $messageId);
        $stmt->execute();
        $stmt->close();

        $meta = $message['metadata_json'] ? json_decode((string)$message['metadata_json'], true) : null;
        foreach (cc_clean_attachments(is_array($meta) ? ($meta['attachments'] ?? []) : []) as $file) {
            cc_delete_upload($file['file_path']);
        }

        $chatId = (int)$message['chat_id'];
        cg_signal($mysqli, $chatId, ['t' => 'gu', 'm' => $messageId]);
        return ['message_id' => $messageId];
    }

    function cg_react(mysqli $mysqli, int $userId, int $messageId, string $reaction): array
    {
        $context = cg_message_context($mysqli, $messageId, $userId);
        if ($context['message']['message_type'] === 'system') {
            throw new ChatError(rt_t('Reazione non valida.', 'Invalid reaction.'), 422);
        }
        if (!cc_ensure_reaction_table($mysqli, 'group_chat_reactions', 'chat_messages')) {
            throw new ChatError(rt_t('Reazioni non disponibili.', 'Reactions are not available.'), 503);
        }
        $length = rt_col_len($mysqli, 'group_chat_reactions', 'reaction') ?: 20;
        if (!cc_valid_reaction($mysqli, $reaction, $length)) {
            throw new ChatError(rt_t('Reazione non valida.', 'Invalid reaction.'), 422);
        }

        cc_toggle_reaction($mysqli, 'group_chat_reactions', $messageId, $userId, $reaction);
        cg_signal($mysqli, (int)$context['message']['chat_id'], ['t' => 'gu', 'm' => $messageId]);

        return [
            'message_id' => $messageId,
            'reactions' => cc_reactions($mysqli, 'group_chat_reactions', $userId, [$messageId])[$messageId] ?? [],
        ];
    }

    // ── Elenchi ────────────────────────────────────────────────────────────

    function cg_list(mysqli $mysqli, int $userId): array
    {
        $stmt = $mysqli->prepare("
            SELECT c.id AS chat_id, c.name, c.description, c.avatar_url, c.created_by,
                   m.role, m.muted_until, m.notification_level, m.is_archived, m.last_read_message_id,
                   UNIX_TIMESTAMP(c.created_at) AS created_ts,
                   (SELECT COUNT(*) FROM chat_messages x
                     WHERE x.chat_id = c.id AND x.id > COALESCE(m.last_read_message_id, 0)
                       AND x.deleted_at IS NULL AND x.sender_id <> m.user_id AND x.message_type <> 'system') AS unread,
                   (SELECT COUNT(*) FROM chat_members mm WHERE mm.chat_id = c.id AND mm.status = 'active') AS members,
                   lm.id AS last_id, lm.body AS last_body, lm.message_type AS last_type, lm.sender_id AS last_sender_id,
                   lm.metadata_json AS last_meta, UNIX_TIMESTAMP(lm.created_at) AS last_ts, lu.username AS last_username
            FROM chat_members m
            INNER JOIN chats c ON c.id = m.chat_id
            LEFT JOIN chat_messages lm ON lm.id = (
                SELECT MAX(y.id) FROM chat_messages y WHERE y.chat_id = c.id AND y.deleted_at IS NULL
            )
            LEFT JOIN utenti lu ON lu.id = lm.sender_id
            WHERE m.user_id = ? AND m.status = 'active' AND c.is_archived = 0
            ORDER BY COALESCE(lm.created_at, c.created_at) DESC
            LIMIT 200
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $groups = [];
        foreach ($rows as $row) {
            $meta = $row['last_meta'] ? json_decode((string)$row['last_meta'], true) : null;
            $muted = $row['notification_level'] === 'muted'
                || (!empty($row['muted_until']) && strtotime((string)$row['muted_until']) > time());
            $system = null;
            if ($row['last_type'] === 'system' && is_array($meta)) {
                $system = ['event' => (string)($meta['event'] ?? '')];
                foreach (['username', 'inviter', 'invitee', 'actor', 'target', 'new_name'] as $key) {
                    if (isset($meta[$key]) && is_string($meta[$key])) {
                        $system[$key] = $meta[$key];
                    }
                }
            }
            $groups[] = [
                'chat_id' => (int)$row['chat_id'],
                'name' => (string)$row['name'],
                'description' => (string)($row['description'] ?? ''),
                'avatar_url' => cg_safe_avatar($row['avatar_url']),
                'role' => (string)$row['role'],
                'members_count' => (int)$row['members'],
                'is_muted' => $muted,
                'muted_until_ts' => !empty($row['muted_until']) ? strtotime((string)$row['muted_until']) : null,
                'is_archived' => (int)$row['is_archived'] === 1,
                'unread_count' => (int)$row['unread'],
                'last_read_id' => (int)$row['last_read_message_id'],
                'last_message_id' => $row['last_id'] !== null ? (int)$row['last_id'] : null,
                'last_message_sender_id' => $row['last_sender_id'] !== null ? (int)$row['last_sender_id'] : null,
                'last_message_sender_username' => $row['last_username'],
                'last_message_body' => cc_preview($row['last_body'], 110),
                'last_message_type' => $row['last_type'],
                'last_message_system' => $system,
                'last_ts' => $row['last_ts'] !== null ? (int)$row['last_ts'] : (int)$row['created_ts'],
            ];
        }

        $stmt = $mysqli->prepare("
            SELECT i.chat_id, c.name AS chat_name, c.avatar_url, i.inviter_id, u.username AS inviter_username,
                   UNIX_TIMESTAMP(i.created_at) AS invited_ts,
                   (SELECT COUNT(*) FROM chat_members mm WHERE mm.chat_id = c.id AND mm.status = 'active') AS members
            FROM chat_invites i
            INNER JOIN chats c ON c.id = i.chat_id AND c.is_archived = 0
            INNER JOIN utenti u ON u.id = i.inviter_id
            INNER JOIN chat_members m ON m.chat_id = i.chat_id AND m.user_id = i.invitee_id AND m.status = 'invited'
            WHERE i.invitee_id = ? AND i.status = 'pending'
              AND NOT EXISTS (
                  SELECT 1 FROM blocked_users b
                  WHERE (b.blocker_id = i.invitee_id AND b.blocked_id = i.inviter_id)
                     OR (b.blocker_id = i.inviter_id AND b.blocked_id = i.invitee_id)
              )
            ORDER BY i.id DESC
            LIMIT 100
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $inviteRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $invites = [];
        foreach ($inviteRows as $row) {
            // Dati vecchi possono avere più inviti in sospeso per lo stesso
            // gruppo: conta il più recente.
            if (isset($invites[(int)$row['chat_id']])) {
                continue;
            }
            $invites[(int)$row['chat_id']] = [
                'chat_id' => (int)$row['chat_id'],
                'chat_name' => (string)$row['chat_name'],
                'chat_avatar' => cg_safe_avatar($row['avatar_url']),
                'inviter_id' => (int)$row['inviter_id'],
                'inviter_username' => (string)$row['inviter_username'],
                'members_count' => (int)$row['members'],
                'invited_ts' => (int)$row['invited_ts'],
            ];
        }

        return ['groups' => $groups, 'invites' => array_values($invites)];
    }

    /** L'avatar di un gruppo è solo un file caricato dal sito. */
    function cg_safe_avatar($url): ?string
    {
        $url = (string)$url;
        return preg_match('#^/uploads/chat_avatars/group_\d+_[a-f0-9]{32}\.(?:jpg|png|webp|gif)$#', $url) ? $url : null;
    }

    function cg_members(mysqli $mysqli, int $chatId): array
    {
        $stmt = $mysqli->prepare("
            SELECT m.user_id, m.role, m.status, UNIX_TIMESTAMP(m.joined_at) AS joined_ts,
                   u.username, u.display_name, u.is_premium, u.ruolo,
                   TIMESTAMPDIFF(SECOND, u.ultimo_accesso, NOW()) AS idle
            FROM chat_members m
            INNER JOIN utenti u ON u.id = m.user_id
            WHERE m.chat_id = ? AND m.status IN ('active', 'invited')
            ORDER BY (m.status = 'active') DESC,
                     CASE m.role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END, u.username ASC
        ");
        $stmt->bind_param('i', $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = [
                'user_id' => (int)$row['user_id'],
                'role' => (string)$row['role'],
                'status' => (string)$row['status'],
                'joined_ts' => $row['joined_ts'] !== null ? (int)$row['joined_ts'] : null,
                'username' => (string)$row['username'],
                'display_name' => (string)($row['display_name'] ?: $row['username']),
                'is_premium' => (int)$row['is_premium'] === 1,
                'site_role' => (string)$row['ruolo'],
                'is_online' => $row['idle'] !== null && (int)$row['idle'] < SC_ONLINE_WINDOW,
            ];
        }
        $stmt->close();
        return $members;
    }

    // ── Gestione ───────────────────────────────────────────────────────────

    function cg_clean_name(string $name): string
    {
        $name = cc_clean_text(str_replace("\n", ' ', $name));
        if ($name === '') {
            throw new ChatError(rt_t('Dai un nome al gruppo.', 'Give the group a name.'), 422);
        }
        if (mb_strlen($name, 'UTF-8') > CG_NAME_MAX) {
            throw new ChatError(rt_t('Il nome del gruppo è troppo lungo.', 'The group name is too long.'), 422);
        }
        return $name;
    }

    function cg_clean_description(?string $text): ?string
    {
        $text = cc_clean_text((string)$text);
        if (mb_strlen($text, 'UTF-8') > CG_DESC_MAX) {
            throw new ChatError(rt_t('La descrizione è troppo lunga.', 'The description is too long.'), 422);
        }
        return $text === '' ? null : $text;
    }

    /**
     * Invita una persona. Si possono invitare solo gli amici: un gruppo non
     * deve diventare il modo per scrivere a chi non ti ha tra i contatti.
     */
    function cg_invite_one(mysqli $mysqli, int $chatId, int $inviterId, int $inviteeId): bool
    {
        if ($inviteeId <= 0 || $inviteeId === $inviterId) {
            return false;
        }
        if (sc_is_blocked($mysqli, $inviterId, $inviteeId) || !sc_are_friends($mysqli, $inviterId, $inviteeId)) {
            return false;
        }
        $invitee = sc_user_brief($mysqli, $inviteeId);
        if (!$invitee || !$invitee['active']) {
            return false;
        }

        $existing = cg_member($mysqli, $chatId, $inviteeId);
        if ($existing && in_array($existing['status'], ['active', 'invited'], true)) {
            return false;
        }

        $stmt = $mysqli->prepare("SELECT COUNT(*) FROM chat_members WHERE chat_id = ? AND status IN ('active', 'invited')");
        $stmt->bind_param('i', $chatId);
        $stmt->execute();
        $size = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
        if ($size >= CG_MAX_MEMBERS) {
            throw new ChatError(rt_t('Il gruppo è pieno.', 'The group is full.'), 422);
        }

        $stmt = $mysqli->prepare("
            INSERT INTO chat_members (chat_id, user_id, role, status) VALUES (?, ?, 'member', 'invited')
            ON DUPLICATE KEY UPDATE status = 'invited', role = 'member', joined_at = NULL, left_at = NULL
        ");
        $stmt->bind_param('ii', $chatId, $inviteeId);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("UPDATE chat_invites SET status = 'cancelled', responded_at = NOW() WHERE chat_id = ? AND invitee_id = ? AND status = 'pending'");
        $stmt->bind_param('ii', $chatId, $inviteeId);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("INSERT INTO chat_invites (chat_id, inviter_id, invitee_id, status) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param('iii', $chatId, $inviterId, $inviteeId);
        $stmt->execute();
        $stmt->close();

        // L'invito resta in sospeso nell'elenco delle chat e nel menu delle
        // notifiche finché non riceve risposta: non serve anche una lettera.
        sc_notify($mysqli, $inviteeId, ['t' => 'gi', 'g' => $chatId, 'f' => $inviterId]);
        return true;
    }

    function cg_create(mysqli $mysqli, int $userId, array $input): array
    {
        $name = cg_clean_name((string)($input['name'] ?? ''));
        $description = cg_clean_description($input['description'] ?? null);

        $inviteeIds = [];
        foreach ((array)($input['invited_users'] ?? []) as $id) {
            $id = (int)$id;
            if ($id > 0 && $id !== $userId) {
                $inviteeIds[$id] = $id;
            }
        }
        $inviteeIds = array_slice(array_values($inviteeIds), 0, CG_MAX_MEMBERS - 1);
        if (!$inviteeIds) {
            throw new ChatError(rt_t('Scegli almeno una persona da invitare.', 'Pick at least one person to invite.'), 422);
        }

        $stmt = $mysqli->prepare('SELECT COUNT(*) FROM chats WHERE created_by = ? AND created_at > NOW() - INTERVAL 1 DAY');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $today = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
        if ($today >= CG_CREATE_PER_DAY) {
            throw new ChatError(rt_t('Hai creato troppi gruppi oggi. Riprova domani.', 'You created too many groups today. Try again tomorrow.'), 429);
        }

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare("INSERT INTO chats (type, name, description, created_by) VALUES ('group_private', ?, ?, ?)");
            $stmt->bind_param('ssi', $name, $description, $userId);
            $stmt->execute();
            $chatId = (int)$mysqli->insert_id;
            $stmt->close();

            $stmt = $mysqli->prepare("INSERT INTO chat_members (chat_id, user_id, role, status, joined_at) VALUES (?, ?, 'owner', 'active', NOW())");
            $stmt->bind_param('ii', $chatId, $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare("INSERT INTO chat_settings (chat_id, invite_permission, edit_info_permission, message_permission, approval_required) VALUES (?, 'everyone', 'owner_admins', 'members', 0)");
            $stmt->bind_param('i', $chatId);
            $stmt->execute();
            $stmt->close();

            cg_system($mysqli, $chatId, 'create');

            $invited = 0;
            foreach ($inviteeIds as $inviteeId) {
                if (cg_invite_one($mysqli, $chatId, $userId, $inviteeId)) {
                    $invited++;
                }
            }
            if ($invited === 0) {
                throw new ChatError(rt_t('Puoi invitare solo i tuoi amici.', 'You can only invite your friends.'), 422);
            }

            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        rt_push_user($userId, ['t' => 'ls']);
        return ['chat_id' => $chatId, 'invited' => $invited];
    }

    function cg_invite(mysqli $mysqli, int $userId, int $chatId, int $inviteeId): void
    {
        $member = cg_require($mysqli, $chatId, $userId);
        if (cg_settings($mysqli, $chatId)['invite_permission'] === 'owner_admins' && !$member['is_staff']) {
            throw new ChatError(rt_t('In questo gruppo possono invitare solo gli amministratori.', 'Only admins can invite people to this group.'), 403);
        }

        $stmt = $mysqli->prepare('SELECT COUNT(*) FROM chat_invites WHERE inviter_id = ? AND created_at > NOW() - INTERVAL 1 HOUR');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $recent = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
        if ($recent >= CG_INVITES_PER_HOUR) {
            throw new ChatError(rt_t('Stai mandando troppi inviti. Riprova più tardi.', 'You are sending too many invites. Try again later.'), 429);
        }

        $inviter = cg_username($mysqli, $userId);
        if (!cg_invite_one($mysqli, $chatId, $userId, $inviteeId)) {
            throw new ChatError(rt_t('Non puoi invitare questa persona: puoi invitare solo amici che non sono già nel gruppo.', 'You cannot invite this person: only friends who are not already in the group can be invited.'), 403);
        }

        $messageId = cg_system($mysqli, $chatId, 'invite', ['inviter' => $inviter, 'invitee' => cg_username($mysqli, $inviteeId)]);
        cg_signal($mysqli, $chatId, ['t' => 'gm', 'm' => $messageId, 'f' => 0, 'q' => 1]);
        cg_signal($mysqli, $chatId, ['t' => 'gx']);
    }

    function cg_answer_invite(mysqli $mysqli, int $userId, int $chatId, bool $accept): void
    {
        $member = cg_member($mysqli, $chatId, $userId);
        if (!$member || $member['status'] !== 'invited') {
            throw new ChatError(rt_t('Questo invito non è più valido.', 'This invitation is no longer valid.'), 404);
        }

        $status = $accept ? 'accepted' : 'declined';
        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare("UPDATE chat_invites SET status = ?, responded_at = NOW() WHERE chat_id = ? AND invitee_id = ? AND status = 'pending'");
            $stmt->bind_param('sii', $status, $chatId, $userId);
            $stmt->execute();
            $stmt->close();

            if ($accept) {
                $stmt = $mysqli->prepare("
                    UPDATE chat_members
                    SET status = 'active', joined_at = NOW(), left_at = NULL,
                        last_read_message_id = (SELECT MAX(id) FROM chat_messages WHERE chat_id = ?)
                    WHERE chat_id = ? AND user_id = ? AND status = 'invited'
                ");
                $stmt->bind_param('iii', $chatId, $chatId, $userId);
            } else {
                $stmt = $mysqli->prepare("UPDATE chat_members SET status = 'left', left_at = NOW() WHERE chat_id = ? AND user_id = ? AND status = 'invited'");
                $stmt->bind_param('ii', $chatId, $userId);
            }
            $stmt->execute();
            $stmt->close();

            $messageId = $accept ? cg_system($mysqli, $chatId, 'join', ['username' => cg_username($mysqli, $userId)]) : 0;
            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        if ($accept) {
            cg_signal($mysqli, $chatId, ['t' => 'gm', 'm' => $messageId, 'f' => 0, 'q' => 1]);
        }
        // Chi ha il pannello del gruppo aperto vede cambiare l'elenco dei membri.
        cg_signal($mysqli, $chatId, ['t' => 'gx']);
        rt_push_user($userId, ['t' => 'ls']);
    }

    function cg_cancel_invite(mysqli $mysqli, int $userId, int $chatId, int $inviteeId): void
    {
        $member = cg_require($mysqli, $chatId, $userId);

        $stmt = $mysqli->prepare("SELECT inviter_id FROM chat_invites WHERE chat_id = ? AND invitee_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('ii', $chatId, $inviteeId);
        $stmt->execute();
        $invite = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$invite) {
            throw new ChatError(rt_t('Invito non trovato.', 'Invitation not found.'), 404);
        }
        if ((int)$invite['inviter_id'] !== $userId && !$member['is_staff']) {
            throw new ChatError(rt_t('Non puoi annullare questo invito.', 'You cannot cancel this invitation.'), 403);
        }

        $stmt = $mysqli->prepare("UPDATE chat_invites SET status = 'cancelled', responded_at = NOW() WHERE chat_id = ? AND invitee_id = ? AND status = 'pending'");
        $stmt->bind_param('ii', $chatId, $inviteeId);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("UPDATE chat_members SET status = 'left', left_at = NOW() WHERE chat_id = ? AND user_id = ? AND status = 'invited'");
        $stmt->bind_param('ii', $chatId, $inviteeId);
        $stmt->execute();
        $stmt->close();

        rt_push_user($inviteeId, ['t' => 'ls']);
        cg_signal($mysqli, $chatId, ['t' => 'gx']);
    }

    /**
     * Esce dal gruppo. Se esce il proprietario, il gruppo passa
     * all'amministratore (o al membro) che c'è da più tempo: prima l'unico
     * modo era «trasferire la proprietà», che però non esisteva.
     */
    function cg_leave(mysqli $mysqli, int $userId, int $chatId): void
    {
        $member = cg_require($mysqli, $chatId, $userId);
        $username = cg_username($mysqli, $userId);

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare("UPDATE chat_members SET status = 'left', left_at = NOW(), role = 'member' WHERE chat_id = ? AND user_id = ?");
            $stmt->bind_param('ii', $chatId, $userId);
            $stmt->execute();
            $stmt->close();

            $leaveId = cg_system($mysqli, $chatId, 'leave', ['username' => $username]);
            $ownerId = 0;

            $stmt = $mysqli->prepare("
                SELECT user_id FROM chat_members WHERE chat_id = ? AND status = 'active'
                ORDER BY (role = 'admin') DESC, joined_at ASC, id ASC LIMIT 1
            ");
            $stmt->bind_param('i', $chatId);
            $stmt->execute();
            $heir = $stmt->get_result()->fetch_row();
            $stmt->close();

            if (!$heir) {
                $stmt = $mysqli->prepare('UPDATE chats SET is_archived = 1 WHERE id = ?');
                $stmt->bind_param('i', $chatId);
                $stmt->execute();
                $stmt->close();
            } elseif ($member['role'] === 'owner') {
                $heirId = (int)$heir[0];
                $stmt = $mysqli->prepare("UPDATE chat_members SET role = 'owner' WHERE chat_id = ? AND user_id = ?");
                $stmt->bind_param('ii', $chatId, $heirId);
                $stmt->execute();
                $stmt->close();
                $ownerId = cg_system($mysqli, $chatId, 'owner', ['target' => cg_username($mysqli, $heirId)]);
            }

            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        cg_signal($mysqli, $chatId, ['t' => 'gm', 'm' => $ownerId ?: $leaveId, 'f' => 0, 'q' => 1]);
        cg_signal($mysqli, $chatId, ['t' => 'gx']);
        rt_push_user($userId, ['t' => 'ls']);
    }

    function cg_remove(mysqli $mysqli, int $userId, int $chatId, int $targetId): void
    {
        $member = cg_require($mysqli, $chatId, $userId);
        $target = cg_member($mysqli, $chatId, $targetId);
        if ($targetId === $userId || !$target || !in_array($target['status'], ['active', 'invited'], true)) {
            throw new ChatError(rt_t('Membro non trovato.', 'Member not found.'), 404);
        }
        $allowed = $member['role'] === 'owner' || ($member['role'] === 'admin' && $target['role'] === 'member');
        if (!$allowed) {
            throw new ChatError(rt_t('Non puoi rimuovere questa persona.', 'You cannot remove this person.'), 403);
        }

        $actor = cg_username($mysqli, $userId);
        $targetName = cg_username($mysqli, $targetId);

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare("UPDATE chat_members SET status = 'removed', left_at = NOW(), role = 'member' WHERE chat_id = ? AND user_id = ?");
            $stmt->bind_param('ii', $chatId, $targetId);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare("UPDATE chat_invites SET status = 'cancelled', responded_at = NOW() WHERE chat_id = ? AND invitee_id = ? AND status = 'pending'");
            $stmt->bind_param('ii', $chatId, $targetId);
            $stmt->execute();
            $stmt->close();

            $messageId = $target['status'] === 'active'
                ? cg_system($mysqli, $chatId, 'remove', ['actor' => $actor, 'target' => $targetName])
                : 0;
            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        if ($target['status'] === 'active') {
            $safeName = str_replace(['[', ']', '*'], '', (string)$member['name']);
            sc_notify($mysqli, $targetId, ['t' => 'ls'], [
                'title_it' => 'Rimosso da un gruppo',
                'title_en' => 'Removed from a group',
                'content_it' => "Sei stato rimosso dal gruppo \"$safeName\".",
                'content_en' => "You were removed from the group \"$safeName\".",
            ]);
            cg_signal($mysqli, $chatId, ['t' => 'gm', 'm' => $messageId, 'f' => 0, 'q' => 1]);
        } else {
            rt_push_user($targetId, ['t' => 'ls']);
        }
        cg_signal($mysqli, $chatId, ['t' => 'gx']);
    }

    /** Promuove o toglie il ruolo di admin, oppure passa la proprietà (solo il proprietario). */
    function cg_set_role(mysqli $mysqli, int $userId, int $chatId, int $targetId, string $role): void
    {
        $member = cg_require($mysqli, $chatId, $userId);
        if ($member['role'] !== 'owner') {
            throw new ChatError(rt_t('Solo il proprietario può cambiare i ruoli.', 'Only the owner can change roles.'), 403);
        }
        $target = cg_member($mysqli, $chatId, $targetId);
        if ($targetId === $userId || !$target || $target['status'] !== 'active') {
            throw new ChatError(rt_t('Membro non trovato.', 'Member not found.'), 404);
        }
        if (!in_array($role, ['admin', 'member', 'owner'], true) || $target['role'] === $role) {
            throw new ChatError(rt_t('Ruolo non valido.', 'Invalid role.'), 422);
        }

        $actor = cg_username($mysqli, $userId);
        $targetName = cg_username($mysqli, $targetId);

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare('UPDATE chat_members SET role = ? WHERE chat_id = ? AND user_id = ?');
            $stmt->bind_param('sii', $role, $chatId, $targetId);
            $stmt->execute();
            $stmt->close();

            if ($role === 'owner') {
                $stmt = $mysqli->prepare("UPDATE chat_members SET role = 'admin' WHERE chat_id = ? AND user_id = ?");
                $stmt->bind_param('ii', $chatId, $userId);
                $stmt->execute();
                $stmt->close();
                $messageId = cg_system($mysqli, $chatId, 'owner', ['target' => $targetName]);
            } else {
                $messageId = cg_system($mysqli, $chatId, $role === 'admin' ? 'promote' : 'demote', ['actor' => $actor, 'target' => $targetName]);
            }
            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        cg_signal($mysqli, $chatId, ['t' => 'gm', 'm' => $messageId, 'f' => 0, 'q' => 1]);
        cg_signal($mysqli, $chatId, ['t' => 'gx']);
    }

    function cg_can_edit_info(mysqli $mysqli, array $member): bool
    {
        return $member['is_staff'] || cg_settings($mysqli, (int)$member['chat_id'])['edit_info_permission'] === 'everyone';
    }

    function cg_update(mysqli $mysqli, int $userId, int $chatId, array $input): void
    {
        $member = cg_require($mysqli, $chatId, $userId);
        $messageId = 0;

        $touchesInfo = array_key_exists('name', $input) || array_key_exists('description', $input);
        if ($touchesInfo) {
            if (!cg_can_edit_info($mysqli, $member)) {
                throw new ChatError(rt_t('Non puoi modificare questo gruppo.', 'You cannot edit this group.'), 403);
            }
            $name = array_key_exists('name', $input) && trim((string)$input['name']) !== ''
                ? cg_clean_name((string)$input['name'])
                : (string)$member['name'];
            $description = array_key_exists('description', $input)
                ? cg_clean_description($input['description'])
                : ($member['description'] !== '' ? $member['description'] : null);

            $stmt = $mysqli->prepare('UPDATE chats SET name = ?, description = ? WHERE id = ?');
            $stmt->bind_param('ssi', $name, $description, $chatId);
            $stmt->execute();
            $stmt->close();

            if ($name !== (string)$member['name']) {
                $messageId = cg_system($mysqli, $chatId, 'rename', ['username' => cg_username($mysqli, $userId), 'new_name' => $name]);
            }
        }

        $settings = [];
        foreach ([
            'invite_permission' => ['everyone', 'owner_admins'],
            'edit_info_permission' => ['everyone', 'owner_admins'],
            'message_permission' => ['members', 'admins_only'],
        ] as $key => $valid) {
            if (isset($input[$key]) && in_array($input[$key], $valid, true)) {
                $settings[$key] = (string)$input[$key];
            }
        }
        if ($settings) {
            if ($member['role'] !== 'owner') {
                throw new ChatError(rt_t('Solo il proprietario può cambiare i permessi.', 'Only the owner can change permissions.'), 403);
            }
            $sets = implode(', ', array_map(static fn($k) => "$k = ?", array_keys($settings)));
            $stmt = $mysqli->prepare("UPDATE chat_settings SET $sets WHERE chat_id = ?");
            $params = array_merge(array_values($settings), [$chatId]);
            $stmt->bind_param(str_repeat('s', count($settings)) . 'i', ...$params);
            $stmt->execute();
            $stmt->close();
        }

        if ($messageId) {
            cg_signal($mysqli, $chatId, ['t' => 'gm', 'm' => $messageId, 'f' => 0, 'q' => 1]);
        }
        cg_signal($mysqli, $chatId, ['t' => 'gx']);
    }

    /** Silenzia per un certo tempo (secondi), per sempre (-1) o riattiva (0). */
    function cg_mute(mysqli $mysqli, int $userId, int $chatId, int $seconds): ?int
    {
        cg_require($mysqli, $chatId, $userId);
        $until = null;
        if ($seconds < 0) {
            $until = date('Y-m-d H:i:s', time() + 10 * 365 * 86400);
        } elseif ($seconds > 0) {
            $until = date('Y-m-d H:i:s', time() + min($seconds, 365 * 86400));
        }
        $stmt = $mysqli->prepare("UPDATE chat_members SET muted_until = ?, notification_level = 'all' WHERE chat_id = ? AND user_id = ?");
        $stmt->bind_param('sii', $until, $chatId, $userId);
        $stmt->execute();
        $stmt->close();
        rt_push_user($userId, ['t' => 'ls']);
        return $until ? strtotime($until) : null;
    }

    function cg_archive(mysqli $mysqli, int $userId, int $chatId, bool $archived): void
    {
        cg_require($mysqli, $chatId, $userId);
        $flag = $archived ? 1 : 0;
        $stmt = $mysqli->prepare('UPDATE chat_members SET is_archived = ? WHERE chat_id = ? AND user_id = ?');
        $stmt->bind_param('iii', $flag, $chatId, $userId);
        $stmt->execute();
        $stmt->close();
        rt_push_user($userId, ['t' => 'ls']);
    }

    /**
     * Quante chat hanno qualcosa di nuovo. È il numero del badge in navbar:
     * conta le conversazioni (private e di gruppo), non i singoli messaggi,
     * e ignora quelle silenziate, archiviate o ancora da accettare.
     */
    function cc_unread_summary(mysqli $mysqli, int $userId): array
    {
        $summary = ['chats' => 0, 'requests' => 0, 'invites' => 0];

        $hasRequest = rt_has_col($mysqli, 'private_conversation_participants', 'is_request');
        $hasCleared = rt_has_col($mysqli, 'private_conversation_participants', 'cleared_before_id');
        $hasUntil = rt_has_col($mysqli, 'private_conversation_participants', 'muted_until');

        $unreadExists = 'EXISTS (
            SELECT 1 FROM private_messages pm
            WHERE pm.conversation_id = cp.conversation_id
              AND pm.id > COALESCE(cp.last_read_message_id, 0)
              AND pm.sender_id <> cp.user_id AND pm.deleted_at IS NULL'
            . ($hasCleared ? ' AND pm.id > COALESCE(cp.cleared_before_id, 0)' : '') . '
              AND NOT EXISTS (SELECT 1 FROM private_message_deleted d WHERE d.message_id = pm.id AND d.user_id = cp.user_id)
        )';

        $stmt = $mysqli->prepare(
            'SELECT COUNT(*) FROM private_conversation_participants cp
             WHERE cp.user_id = ? AND cp.is_archived = 0 AND cp.is_muted = 0'
            . ($hasRequest ? ' AND cp.is_request = 0' : '')
            . ($hasUntil ? ' AND (cp.muted_until IS NULL OR cp.muted_until < NOW())' : '')
            . ' AND ' . $unreadExists
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $summary['chats'] = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();

        if ($hasRequest) {
            $stmt = $mysqli->prepare(
                'SELECT COUNT(*) FROM private_conversation_participants cp
                 WHERE cp.user_id = ? AND cp.is_request = 1 AND cp.is_archived = 0 AND ' . $unreadExists
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $summary['requests'] = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
        }

        if (rt_has_table($mysqli, 'chat_members')) {
            $stmt = $mysqli->prepare("
                SELECT
                    SUM(m.status = 'active' AND m.is_archived = 0 AND m.notification_level <> 'muted'
                        AND (m.muted_until IS NULL OR m.muted_until < NOW())
                        AND EXISTS (
                            SELECT 1 FROM chat_messages x
                            WHERE x.chat_id = m.chat_id AND x.id > COALESCE(m.last_read_message_id, 0)
                              AND x.deleted_at IS NULL AND x.sender_id <> m.user_id AND x.message_type <> 'system'
                        )) AS unread,
                    SUM(m.status = 'invited') AS invites
                FROM chat_members m
                INNER JOIN chats c ON c.id = m.chat_id AND c.is_archived = 0
                WHERE m.user_id = ? AND m.status IN ('active', 'invited')
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_row();
            $stmt->close();
            $summary['chats'] += (int)($row[0] ?? 0);
            $summary['invites'] = (int)($row[1] ?? 0);
        }

        return $summary;
    }

    /** Tutto quello che serve al pannello «Dettagli» di un gruppo, in una chiamata. */
    function cg_details(mysqli $mysqli, int $userId, int $chatId): array
    {
        $member = cg_require($mysqli, $chatId, $userId);
        return [
            'chat' => [
                'id' => $chatId,
                'name' => (string)$member['name'],
                'description' => (string)($member['description'] ?? ''),
                'avatar_url' => cg_safe_avatar($member['avatar_url']),
                'created_by' => (int)$member['created_by'],
            ],
            'settings' => cg_settings($mysqli, $chatId),
            'my_membership' => [
                'role' => (string)$member['role'],
                'status' => (string)$member['status'],
                'notification_level' => (string)$member['notification_level'],
                'is_muted' => $member['muted'],
                'muted_until_ts' => !empty($member['muted_until']) ? strtotime((string)$member['muted_until']) : null,
                'is_archived' => (int)$member['is_archived'] === 1,
                'can_edit_info' => cg_can_edit_info($mysqli, $member),
            ],
            'members' => cg_members($mysqli, $chatId),
        ];
    }
}
