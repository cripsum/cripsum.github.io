<?php
/**
 * Cripsum™ — notifiche in tempo reale.
 *
 * Il timbro di un utente (includes/realtime.php) dice solo «è successo
 * qualcosa», con gli id. Questo file trasforma quegli eventi in avvisi che
 * si possono mostrare: chi ha scritto, un'anteprima, dove porta il clic.
 * Ogni dato passa dagli stessi controlli della pagina a cui si riferisce:
 * l'anteprima di un messaggio privato esce solo se chi chiede partecipa a
 * quella conversazione.
 *
 * Qui stanno anche i contatori della navbar (posta, chat, amici), così
 * pagina e avvisi mostrano sempre gli stessi numeri.
 */

require_once __DIR__ . '/chat_groups.php';

if (!function_exists('notify_counters')) {

    function notify_counters(mysqli $mysqli, int $userId, string $role = 'utente'): array
    {
        $out = ['inbox' => 0, 'chats' => 0, 'requests' => 0, 'invites' => 0, 'friends' => 0];

        try {
            if (function_exists('getUnreadMessagesCount')) {
                $out['inbox'] = (int)getUnreadMessagesCount($mysqli, $userId);
            }
        } catch (Throwable $e) {
            $out['inbox'] = 0;
        }

        try {
            $summary = cc_unread_summary($mysqli, $userId);
            $out['chats'] = $summary['chats'];
            $out['requests'] = $summary['requests'];
            $out['invites'] = $summary['invites'];
        } catch (Throwable $e) {
            error_log('[notify] chat: ' . $e->getMessage());
        }

        try {
            $stmt = $mysqli->prepare("SELECT COUNT(*) FROM friendship_requests WHERE receiver_id = ? AND status = 'pending'");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $out['friends'] = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
        } catch (Throwable $e) {
            $out['friends'] = 0;
        }

        return $out;
    }

    function notify_attachment_label(?string $type): string
    {
        return match ($type) {
            'image' => rt_t('📷 Foto', '📷 Photo'),
            'video' => rt_t('🎬 Video', '🎬 Video'),
            'audio' => rt_t('🎵 Audio', '🎵 Audio'),
            default => rt_t('📎 Allegato', '📎 Attachment'),
        };
    }

    /**
     * Dagli eventi del timbro agli avvisi da mostrare.
     * Restituisce al massimo dodici voci, la più recente per ogni chat.
     */
    function notify_items(mysqli $mysqli, int $userId, array $events): array
    {
        $lang = rt_lang();
        $privateIds = [];
        $groupIds = [];
        $userIds = [];
        $mentionIds = [];
        $wanted = [];

        foreach ($events as $event) {
            $type = (string)($event['t'] ?? '');
            $from = (int)($event['f'] ?? 0);
            if ($from === $userId) {
                continue;
            }
            switch ($type) {
                case 'pm':
                    $privateIds[(int)$event['m']] = true;
                    $wanted[] = $event;
                    break;
                case 'gm':
                    if (!empty($event['q']) || $from === 0) {
                        break;
                    }
                    $groupIds[(int)$event['m']] = true;
                    $wanted[] = $event;
                    break;
                case 'mn':
                    $mentionIds[(int)$event['m']] = true;
                    $userIds[$from] = true;
                    $wanted[] = $event;
                    break;
                case 'fr':
                case 'fa':
                case 'gi':
                    $userIds[$from] = true;
                    $wanted[] = $event;
                    break;
                case 'ib':
                    // «q» = solo contatore: l'avviso l'ha già dato un altro evento.
                    if (empty($event['q'])) {
                        $wanted[] = $event;
                    }
                    break;
            }
        }
        if (!$wanted) {
            return [];
        }

        // Messaggi privati: solo quelli di conversazioni di cui chi chiede fa parte.
        $private = [];
        if ($privateIds) {
            $ids = array_keys($privateIds);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $muteUntil = rt_has_col($mysqli, 'private_conversation_participants', 'muted_until') ? 'cp.muted_until' : 'NULL';
            $request = rt_has_col($mysqli, 'private_conversation_participants', 'is_request') ? 'cp.is_request' : '0';
            $nick = rt_has_col($mysqli, 'private_conversation_participants', 'nickname') ? 'o.nickname' : 'NULL';
            $stmt = $mysqli->prepare("
                SELECT m.id, m.conversation_id, m.sender_id, m.message, m.message_type, m.deleted_for_all,
                       u.username, u.display_name, cp.is_muted, $muteUntil AS muted_until, $request AS is_request, $nick AS nickname,
                       (SELECT a.file_type FROM private_message_attachments a WHERE a.message_id = m.id ORDER BY a.id ASC LIMIT 1) AS attachment_type
                FROM private_messages m
                INNER JOIN private_conversation_participants cp ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
                INNER JOIN private_conversation_participants o ON o.conversation_id = m.conversation_id AND o.user_id = m.sender_id
                INNER JOIN utenti u ON u.id = m.sender_id
                WHERE m.id IN ($marks) AND m.deleted_at IS NULL AND m.sender_id <> ?
            ");
            $params = array_merge([$userId], $ids, [$userId]);
            $stmt->bind_param(str_repeat('i', count($params)), ...$params);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $private[(int)$row['id']] = $row;
            }
            $stmt->close();
        }

        // Messaggi di gruppo: solo dei gruppi in cui chi chiede è attivo.
        $group = [];
        if ($groupIds) {
            $ids = array_keys($groupIds);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $mysqli->prepare("
                SELECT m.id, m.chat_id, m.sender_id, m.body, m.message_type, u.username, c.name AS chat_name, c.avatar_url,
                       mb.muted_until, mb.notification_level
                FROM chat_messages m
                INNER JOIN chat_members mb ON mb.chat_id = m.chat_id AND mb.user_id = ? AND mb.status = 'active'
                INNER JOIN chats c ON c.id = m.chat_id
                LEFT JOIN utenti u ON u.id = m.sender_id
                WHERE m.id IN ($marks) AND m.deleted_at IS NULL AND m.sender_id <> ?
            ");
            $params = array_merge([$userId], $ids, [$userId]);
            $stmt->bind_param(str_repeat('i', count($params)), ...$params);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $group[(int)$row['id']] = $row;
            }
            $stmt->close();
        }

        $mentions = [];
        if ($mentionIds && rt_has_table($mysqli, 'messages')) {
            $ids = array_keys($mentionIds);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $mysqli->prepare("SELECT id, user_id, message FROM messages WHERE id IN ($marks) AND deleted_at IS NULL");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $mentions[(int)$row['id']] = $row;
            }
            $stmt->close();
        }

        $users = [];
        if ($userIds) {
            $ids = array_keys($userIds);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $mysqli->prepare("SELECT id, username, display_name FROM utenti WHERE id IN ($marks)");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $users[(int)$row['id']] = $row;
            }
            $stmt->close();
        }
        $hidden = array_flip(sc_hidden_ids($mysqli, $userId));

        $items = [];
        foreach ($wanted as $event) {
            $type = (string)$event['t'];
            $from = (int)($event['f'] ?? 0);
            $seq = (int)($event['s'] ?? 0);
            if ($from > 0 && isset($hidden[$from])) {
                continue;
            }

            if ($type === 'pm') {
                $row = $private[(int)$event['m']] ?? null;
                if (!$row) {
                    continue;
                }
                $muted = (int)$row['is_muted'] === 1 || (!empty($row['muted_until']) && strtotime((string)$row['muted_until']) > time());
                $isRequest = (int)$row['is_request'] === 1;
                $text = (int)$row['deleted_for_all'] === 1
                    ? rt_t('Messaggio eliminato', 'Message deleted')
                    : ($row['message_type'] === 'gif' ? 'GIF'
                        : ($row['message_type'] === 'media' && trim((string)$row['message']) === ''
                            ? notify_attachment_label($row['attachment_type'])
                            : cc_preview($row['message'], 110)));
                $name = (string)(($row['nickname'] ?? '') ?: ($row['display_name'] ?: $row['username']));
                $items['p' . $row['conversation_id']] = [
                    'key' => 'p' . $row['conversation_id'],
                    'seq' => $seq,
                    'type' => $isRequest ? 'request' : 'dm',
                    'title' => $name,
                    'text' => $isRequest ? rt_t('Vuole scriverti: ', 'Wants to message you: ') . $text : $text,
                    'avatar' => '/includes/get_pfp.php?id=' . (int)$row['sender_id'],
                    'url' => "/$lang/chat?c=" . (int)$row['conversation_id'],
                    'chat' => ['kind' => 'private', 'id' => (int)$row['conversation_id']],
                    'silent' => $muted || $isRequest,
                ];
                continue;
            }

            if ($type === 'gm') {
                $row = $group[(int)$event['m']] ?? null;
                if (!$row) {
                    continue;
                }
                $muted = $row['notification_level'] === 'muted' || (!empty($row['muted_until']) && strtotime((string)$row['muted_until']) > time());
                $text = $row['message_type'] === 'gif' ? 'GIF'
                    : ($row['message_type'] === 'media' && trim((string)$row['body']) === '' ? notify_attachment_label(null) : cc_preview($row['body'], 100));
                $items['g' . $row['chat_id']] = [
                    'key' => 'g' . $row['chat_id'],
                    'seq' => $seq,
                    'type' => 'group',
                    'title' => (string)$row['chat_name'],
                    'text' => '@' . (string)($row['username'] ?? '') . ': ' . $text,
                    'avatar' => cg_safe_avatar($row['avatar_url']) ?: '/img/Susremaster.png',
                    'url' => "/$lang/chat?g=" . (int)$row['chat_id'],
                    'chat' => ['kind' => 'group', 'id' => (int)$row['chat_id']],
                    'silent' => $muted,
                ];
                continue;
            }

            if ($type === 'mn') {
                $row = $mentions[(int)$event['m']] ?? null;
                $user = $users[$from] ?? null;
                if (!$row || !$user) {
                    continue;
                }
                $items['m' . $row['id']] = [
                    'key' => 'm' . $row['id'],
                    'seq' => $seq,
                    'type' => 'mention',
                    'title' => rt_t('@' . $user['username'] . ' ti ha menzionato', '@' . $user['username'] . ' mentioned you'),
                    'text' => cc_preview($row['message'], 110),
                    'avatar' => '/includes/get_pfp.php?id=' . $from,
                    'url' => "/$lang/global-chat?message=" . (int)$row['id'],
                    'silent' => false,
                ];
                continue;
            }

            if ($type === 'ib') {
                $items['ib'] = [
                    'key' => 'ib',
                    'seq' => $seq,
                    'type' => 'inbox',
                    'title' => rt_t('Nuovo messaggio nella posta', 'New message in your inbox'),
                    'text' => (string)($lang === 'en' ? ($event['xe'] ?? '') : ($event['xi'] ?? '')),
                    'avatar' => null,
                    'url' => "/$lang/inbox",
                    'silent' => false,
                ];
                continue;
            }

            $user = $users[$from] ?? null;
            if (!$user) {
                continue;
            }
            $name = '@' . $user['username'];
            if ($type === 'fr') {
                $items['fr' . $from] = [
                    'key' => 'fr' . $from, 'seq' => $seq, 'type' => 'friend_request',
                    'title' => $name,
                    'text' => rt_t('Ti ha inviato una richiesta di amicizia', 'Sent you a friend request'),
                    'avatar' => '/includes/get_pfp.php?id=' . $from,
                    'url' => "/$lang/amici?tab=requests", 'silent' => false,
                ];
            } elseif ($type === 'fa') {
                $items['fa' . $from] = [
                    'key' => 'fa' . $from, 'seq' => $seq, 'type' => 'friend_accept',
                    'title' => $name,
                    'text' => rt_t('Ha accettato la tua richiesta di amicizia', 'Accepted your friend request'),
                    'avatar' => '/includes/get_pfp.php?id=' . $from,
                    'url' => "/$lang/amici", 'silent' => false,
                ];
            } elseif ($type === 'gi') {
                $items['gi' . (int)($event['g'] ?? 0)] = [
                    'key' => 'gi' . (int)($event['g'] ?? 0), 'seq' => $seq, 'type' => 'group_invite',
                    'title' => $name,
                    'text' => rt_t('Ti ha invitato in un gruppo', 'Invited you to a group'),
                    'avatar' => '/includes/get_pfp.php?id=' . $from,
                    'url' => "/$lang/chat", 'silent' => false,
                ];
            }
        }

        $items = array_values($items);
        usort($items, static fn($a, $b) => $a['seq'] <=> $b['seq']);
        return array_slice($items, -12);
    }
}
