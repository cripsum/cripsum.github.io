<?php
require_once __DIR__ . '/bootstrap.php';

/*
 * Reazione a uno shitpost, o voto a un Top Rimasti.
 *
 * POST type, id
 *      reaction   (shitpost) una chiave di CM_REACTIONS; la stessa di prima la toglie
 *      set        facoltativo: true/false per dire lo stato voluto invece di invertirlo
 *
 * Ogni scrittura decide in base alle righe davvero toccate: due clic nello
 * stesso istante non producono né un errore né un conteggio sbagliato.
 */
try {
    cv2_check_csrf();

    $user = cv2_require_login($mysqli);
    cm_throttle('react', 40, 60);

    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $userId = (int)$user['id'];
    $wanted = array_key_exists('set', $input) ? cv2_bool_int($input['set']) === 1 || $input['set'] === true : null;

    $post = cm_require_post($mysqli, $type, (int)($input['id'] ?? 0), $user, true);
    $id = $post['id'];
    $schema = cm_schema($mysqli, $type);

    if ($type === 'rimasto') {
        if (!$schema['votes']) {
            cv2_fail(cm_t('I voti non sono disponibili.', 'Votes are not available.'), 503);
        }

        $delta = 0;
        $remove = static function () use ($mysqli, $userId, $id): bool {
            $stmt = $mysqli->prepare('DELETE FROM voti_toprimasti WHERE id_utente = ? AND id_post = ?');
            $stmt->bind_param('ii', $userId, $id);
            $stmt->execute();
            $done = $stmt->affected_rows > 0;
            $stmt->close();
            return $done;
        };
        $add = static function () use ($mysqli, $userId, $id): bool {
            $stmt = $mysqli->prepare('INSERT IGNORE INTO voti_toprimasti (id_utente, id_post, data_voto) VALUES (?, ?, NOW())');
            $stmt->bind_param('ii', $userId, $id);
            $stmt->execute();
            $done = $stmt->affected_rows > 0;
            $stmt->close();
            return $done;
        };

        if ($wanted === true) {
            $delta = $add() ? 1 : 0;
            $active = true;
        } elseif ($wanted === false) {
            $delta = $remove() ? -1 : 0;
            $active = false;
        } elseif ($remove()) {
            $delta = -1;
            $active = false;
        } else {
            $delta = $add() ? 1 : 0;
            $active = true;
        }

        if ($delta !== 0) {
            $stmt = $mysqli->prepare('UPDATE toprimasti SET reazioni = GREATEST(0, COALESCE(reazioni, 0) + ?) WHERE id = ?');
            $stmt->bind_param('ii', $delta, $id);
            $stmt->execute();
            $stmt->close();
        }

        if ($delta === 1 && cm_action_once($mysqli, $userId, $type, $id, 'like')) {
            cm_track($mysqli, $userId, ['add_like' => true, 'vote_rimasti' => true]);
        }

        cv2_ok([
            'active' => $active,
            'delta' => $delta,
            'score' => cm_count($mysqli, 'SELECT COALESCE(reazioni, 0) FROM toprimasti WHERE id = ?', 'i', [$id]),
        ]);
    }

    if (!$schema['likes']) {
        cv2_fail(cm_t('Le reazioni non sono disponibili.', 'Reactions are not available.'), 503);
    }

    $reaction = (string)($input['reaction'] ?? 'fire');
    if (!isset(CM_REACTIONS[$reaction]) || !$schema['reactions']) {
        $reaction = 'fire';
    }

    $column = $schema['reactions'] ? 'reazione' : "'fire'";
    $stmt = $mysqli->prepare("SELECT $column FROM shitpost_likes WHERE id_utente = ? AND id_shitpost = ? LIMIT 1");
    $stmt->bind_param('ii', $userId, $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_row()[0] ?? null;
    $stmt->close();

    $off = $wanted === false || ($wanted === null && $current !== null && $current === $reaction);
    $added = false;

    if ($off) {
        $stmt = $mysqli->prepare('DELETE FROM shitpost_likes WHERE id_utente = ? AND id_shitpost = ?');
        $stmt->bind_param('ii', $userId, $id);
        $stmt->execute();
        $stmt->close();
        $mine = null;
    } else {
        if ($schema['reactions']) {
            $stmt = $mysqli->prepare('INSERT INTO shitpost_likes (id_utente, id_shitpost, created_at, reazione) VALUES (?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE reazione = VALUES(reazione)');
            $stmt->bind_param('iis', $userId, $id, $reaction);
        } else {
            $stmt = $mysqli->prepare('INSERT IGNORE INTO shitpost_likes (id_utente, id_shitpost, created_at) VALUES (?, ?, NOW())');
            $stmt->bind_param('ii', $userId, $id);
        }
        $stmt->execute();
        // 1 = riga nuova; 2 = c'era già e ha cambiato reazione; 0 = identica.
        $added = $stmt->affected_rows === 1 && $current === null;
        $stmt->close();
        $mine = $reaction;
    }

    if ($added && cm_action_once($mysqli, $userId, $type, $id, 'like')) {
        cm_track($mysqli, $userId, ['add_like' => true, 'like_shitpost' => true]);
    }

    $reactions = [];
    $result = $mysqli->query("SELECT $column AS reazione, COUNT(*) AS n FROM shitpost_likes WHERE id_shitpost = $id GROUP BY $column");
    while ($result && ($row = $result->fetch_assoc())) {
        $key = isset(CM_REACTIONS[$row['reazione']]) ? $row['reazione'] : 'fire';
        $reactions[$key] = ($reactions[$key] ?? 0) + (int)$row['n'];
    }

    cv2_ok([
        'active' => $mine !== null,
        'reaction' => $mine,
        'score' => array_sum($reactions),
        'reactions' => (object)$reactions,
    ]);
} catch (Throwable $e) {
    cm_crash('reazione', $e);
}
