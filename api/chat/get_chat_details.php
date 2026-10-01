<?php
/**
 * Pannello «Dettagli» di una conversazione privata: l'altra persona, i
 * messaggi fissati, i media, i file e i link condivisi.
 *
 * Del soprannome esce solo quello che ho dato io all'altro: quello che
 * l'altro ha dato a me resta suo.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $conversationId = (int)($_GET['conversation_id'] ?? 0);
    $pair = cc_pm_require($mysqli, $conversationId, $userId);
    $otherId = (int)$pair['other']['user_id'];

    $rel = sc_relationship($mysqli, $userId, $otherId);
    $blocked = $rel['is_blocked_by_viewer'] || $rel['has_blocked_viewer'];

    $stmt = $mysqli->prepare('
        SELECT id, username, display_name, ruolo, is_premium,
               TIMESTAMPDIFF(SECOND, ultimo_accesso, NOW()) AS idle, UNIX_TIMESTAMP(ultimo_accesso) AS seen_ts
        FROM utenti WHERE id = ? LIMIT 1
    ');
    $stmt->bind_param('i', $otherId);
    $stmt->execute();
    $other = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $online = !$blocked && isset($other['idle']) && $other['idle'] !== null && (int)$other['idle'] < SC_ONLINE_WINDOW;
    $nickname = isset($pair['other']['nickname']) && $pair['other']['nickname'] !== '' ? (string)$pair['other']['nickname'] : null;

    [$visible, $types, $params] = cc_pm_visible_sql($pair, 'pm');

    // Messaggi fissati
    $pinned = [];
    if (rt_has_table($mysqli, 'private_pinned_messages')) {
        $stmt = $mysqli->prepare("
            SELECT pm.id AS message_id, pm.message, pm.message_type, pm.created_at, UNIX_TIMESTAMP(pm.created_at) AS ts, u.username AS sender_username
            FROM private_pinned_messages ppm
            INNER JOIN private_messages pm ON pm.id = ppm.message_id
            INNER JOIN utenti u ON u.id = pm.sender_id
            WHERE ppm.conversation_id = ? AND $visible AND pm.deleted_for_all = 0
            ORDER BY ppm.id DESC
            LIMIT 30
        ");
        $all = array_merge([$conversationId], $params);
        $stmt->bind_param('i' . $types, ...$all);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $pinned[] = [
                'message_id' => (int)$row['message_id'],
                'message' => cc_preview($row['message'], 160),
                'message_type' => (string)$row['message_type'],
                'sender_username' => (string)$row['sender_username'],
                'created_at' => (string)$row['created_at'],
                'ts' => (int)$row['ts'],
            ];
        }
        $stmt->close();
    }

    // Allegati condivisi
    $media = [];
    $files = [];
    $stmt = $mysqli->prepare("
        SELECT a.message_id, a.file_name, a.file_path, a.file_size, a.file_mime, a.file_type, UNIX_TIMESTAMP(pm.created_at) AS ts
        FROM private_message_attachments a
        INNER JOIN private_messages pm ON pm.id = a.message_id
        WHERE $visible AND pm.deleted_for_all = 0
        ORDER BY a.id DESC
        LIMIT 200
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $item = cc_clean_attachments([$row])[0] ?? null;
        if (!$item) {
            continue;
        }
        $item['message_id'] = (int)$row['message_id'];
        $item['ts'] = (int)$row['ts'];
        if (in_array($item['file_type'], ['image', 'video'], true)) {
            if (count($media) < 60) {
                $media[] = $item;
            }
        } elseif ($item['file_type'] !== 'sticker' && count($files) < 40) {
            $files[] = $item;
        }
    }
    $stmt->close();

    // Link condivisi
    $links = [];
    $stmt = $mysqli->prepare("
        SELECT pm.id, pm.message, UNIX_TIMESTAMP(pm.created_at) AS ts, u.username AS sender_username
        FROM private_messages pm
        INNER JOIN utenti u ON u.id = pm.sender_id
        WHERE $visible AND pm.deleted_for_all = 0 AND pm.message LIKE '%http%'
        ORDER BY pm.id DESC
        LIMIT 60
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        if (preg_match_all('#https?://[^\s<>"\']+#i', (string)$row['message'], $found)) {
            foreach ($found[0] as $url) {
                if (count($links) >= 40) {
                    break 2;
                }
                $links[] = [
                    'message_id' => (int)$row['id'],
                    'url' => rtrim($url, '.,;:!?)'),
                    'sender_username' => (string)$row['sender_username'],
                    'ts' => (int)$row['ts'],
                ];
            }
        }
    }
    $stmt->close();

    $isPinned = false;
    if (rt_has_table($mysqli, 'private_conversation_pins')) {
        $stmt = $mysqli->prepare('SELECT 1 FROM private_conversation_pins WHERE user_id = ? AND conversation_id = ? LIMIT 1');
        $stmt->bind_param('ii', $userId, $conversationId);
        $stmt->execute();
        $isPinned = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    }

    $otherOut = [
        'id' => $otherId,
        'username' => (string)($other['username'] ?? ''),
        'display_name' => (string)(($other['display_name'] ?? '') ?: ($other['username'] ?? '')),
        'nickname' => $nickname,
        'ruolo' => (string)($other['ruolo'] ?? 'utente'),
        'is_premium' => (int)($other['is_premium'] ?? 0) === 1,
        'is_online' => $online,
        'last_seen_ts' => (!$blocked && !$online && !empty($other['seen_ts'])) ? (int)$other['seen_ts'] : null,
        'is_friend' => $rel['is_friend'],
        'friend_request_sent' => $rel['friend_request_sent'],
        'friend_request_received' => $rel['friend_request_received'],
        'can_send_friend_request' => $rel['can_send_friend_request'],
        'is_blocked_by_me' => $rel['is_blocked_by_viewer'],
        'can_message' => $rel['can_message'],
    ];

    send_success([
        'conversation' => [
            'id' => $conversationId,
            'is_muted' => $pair['me']['muted'],
            'muted_until_ts' => !empty($pair['me']['muted_until']) ? strtotime((string)$pair['me']['muted_until']) : null,
            'is_archived' => (int)$pair['me']['is_archived'] === 1,
            'is_pinned' => $isPinned,
            'is_request' => $pair['me']['is_request'],
            'awaiting_accept' => $pair['other']['is_request'],
        ],
        'other' => $otherOut,
        // Chiavi storiche, per le schede aperte con la versione precedente.
        'settings' => ['is_muted' => $pair['me']['muted'] ? 1 : 0, 'is_archived' => (int)$pair['me']['is_archived']],
        'participants' => [
            ['id' => $otherId, 'username' => $otherOut['username'], 'nickname' => $nickname, 'ruolo' => $otherOut['ruolo'], 'is_premium' => $otherOut['is_premium']],
        ],
        'pinned_messages' => $pinned,
        'gallery' => ['media' => $media, 'files' => $files, 'links' => $links],
    ]);
});
