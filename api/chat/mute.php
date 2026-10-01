<?php
/**
 * Silenziare.
 *
 *   con `chat_id`  → le notifiche di un gruppo, per `duration` secondi
 *                    (-1 = finché non le riattivi);
 *   con `user_id`  → un utente nella chat globale (non vedrai i suoi
 *                    messaggi), `muted: false` per togliere il muto;
 *   GET            → elenco di chi hai mutato nella chat globale.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $list = [];
        if (rt_has_table($mysqli, 'chat_mutes')) {
            $stmt = $mysqli->prepare('
                SELECT u.id, u.username, u.display_name, u.is_premium
                FROM chat_mutes cm INNER JOIN utenti u ON u.id = cm.muted_id
                WHERE cm.muter_id = ? ORDER BY cm.created_at DESC LIMIT 200
            ');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $list[] = [
                    'id' => (int)$row['id'],
                    'username' => (string)$row['username'],
                    'display_name' => (string)($row['display_name'] ?: $row['username']),
                    'is_premium' => (int)$row['is_premium'] === 1,
                ];
            }
            $stmt->close();
        }
        send_success(['muted' => $list]);
    }

    $input = get_json_input();

    if (isset($input['chat_id'])) {
        $until = cg_mute($mysqli, $userId, (int)$input['chat_id'], (int)($input['duration'] ?? -1));
        send_success(['muted_until_ts' => $until, 'muted' => $until !== null]);
    }

    $targetId = (int)($input['user_id'] ?? 0);
    $muted = !empty($input['muted']);
    if ($targetId <= 0 || $targetId === $userId || !rt_has_table($mysqli, 'chat_mutes')) {
        throw new ChatError(rt_t('Utente non valido.', 'Invalid user.'), 422);
    }

    if ($muted) {
        $stmt = $mysqli->prepare('SELECT COUNT(*) FROM chat_mutes WHERE muter_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $count = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
        if ($count >= 200) {
            throw new ChatError(rt_t('Hai già mutato troppe persone.', 'You already muted too many people.'), 422);
        }
        $stmt = $mysqli->prepare('INSERT IGNORE INTO chat_mutes (muter_id, muted_id, created_at) VALUES (?, ?, NOW())');
    } else {
        $stmt = $mysqli->prepare('DELETE FROM chat_mutes WHERE muter_id = ? AND muted_id = ?');
    }
    $stmt->bind_param('ii', $userId, $targetId);
    $stmt->execute();
    $stmt->close();

    sc_refresh_hidden($mysqli, $userId);
    send_success(['muted' => $muted]);
});
