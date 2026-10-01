<?php
/**
 * Lista amici. Senza parametri restituisce i propri, con i contatori che la
 * pagina Amici mostra in testata; con `target_id` quelli di un altro utente,
 * se il suo profilo è visibile a chi chiede.
 */
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    $targetId = isset($_GET['target_id']) ? (int)$_GET['target_id'] : $userId;
    if ($targetId <= 0) {
        $targetId = $userId;
    }

    if ($targetId !== $userId) {
        $rel = sc_relationship($mysqli, $userId, $targetId);
        if (!$rel['target_exists'] || !$rel['can_view_profile']) {
            throw new SocialError(rt_t('Questo profilo non è visibile.', 'This profile is not visible.'), 'PROFILE_PRIVATE', 403);
        }

        // Degli amici di un altro si vede chi sono, non quando sono online.
        $friends = array_map(static function (array $friend): array {
            $friend['is_online'] = false;
            $friend['last_seen_ts'] = null;
            unset($friend['since_ts']);
            return $friend;
        }, sc_friends($mysqli, $targetId));

        $hidden = array_flip(sc_hidden_ids($mysqli, $userId));
        $friends = array_values(array_filter($friends, static fn($f) => !isset($hidden[$f['id']]) && $f['id'] !== $userId));
        $friends = sc_attach_relations($mysqli, $userId, $friends);

        send_api_success(['online' => [], 'offline' => $friends, 'all' => $friends]);
    }

    $friends = sc_friends($mysqli, $userId);
    $online = array_values(array_filter($friends, static fn($f) => $f['is_online']));
    $offline = array_values(array_filter($friends, static fn($f) => !$f['is_online']));

    $stmt = $mysqli->prepare("
        SELECT
            (SELECT COUNT(*) FROM friendship_requests WHERE receiver_id = ? AND status = 'pending') AS received,
            (SELECT COUNT(*) FROM friendship_requests WHERE sender_id = ? AND status = 'pending') AS sent,
            (SELECT COUNT(*) FROM blocked_users WHERE blocker_id = ?) AS blocked
    ");
    $stmt->bind_param('iii', $userId, $userId, $userId);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    send_api_success([
        'online' => $online,
        'offline' => $offline,
        'all' => $friends,
        'counts' => [
            'friends' => count($friends),
            'online' => count($online),
            'requests_received' => (int)($counts['received'] ?? 0),
            'requests_sent' => (int)($counts['sent'] ?? 0),
            'blocked' => (int)($counts['blocked'] ?? 0),
        ],
    ]);
});
