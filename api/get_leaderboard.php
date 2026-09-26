<?php

/**
 * Classifica della lootbox: casse aperte o personaggi trovati.
 * GET /api/get_leaderboard?type=casse_aperte|personaggi_sbloccati
 *
 * Restituisce la top 10 con la foto profilo e, se si e' loggati, la propria
 * riga (posizione compresa) anche quando si e' fuori dalla top 10.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/session_init.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$type = $_GET['type'] ?? 'casse_aperte';

try {
    if ($type === 'casse_aperte') {
        $valueSql = 'SUM(' . cripsum_boxes_sql($mysqli, 'up') . ')';
    } elseif ($type === 'personaggi_sbloccati') {
        $valueSql = 'COUNT(DISTINCT up.personaggio_id)';
    } else {
        throw new InvalidArgumentException('Invalid leaderboard type');
    }

    $stmt = $mysqli->prepare("
        SELECT u.id, u.username, COALESCE(u.is_premium, 0) AS is_premium, $valueSql AS valore
        FROM utenti u
        JOIN utenti_personaggi up ON u.id = up.utente_id
        GROUP BY u.id, u.username, u.is_premium
        ORDER BY valore DESC, u.id ASC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $row = static fn(array $r, int $position): array => [
        'position' => $position,
        'id' => (int)$r['id'],
        'username' => (string)$r['username'],
        'avatar' => '/includes/get_pfp.php?id=' . (int)$r['id'] . '&size=96',
        'is_premium' => (int)($r['is_premium'] ?? 0) === 1,
        'value' => (int)($r['valore'] ?? 0),
    ];

    $leaderboard = [];
    $position = 1;
    while ($r = $result->fetch_assoc()) {
        $leaderboard[] = $row($r, $position++);
    }
    $stmt->close();

    // La propria riga: posizione = quanti hanno un valore piu' alto + 1
    // (a pari valore vince chi si e' iscritto prima, come nella top 10).
    $me = null;
    $userId = isLoggedIn() ? (int)($_SESSION['user_id'] ?? 0) : 0;
    if ($userId > 0) {
        foreach ($leaderboard as $entry) {
            if ($entry['id'] === $userId) {
                $me = $entry;
            }
        }
        if ($me === null) {
            $stmt = $mysqli->prepare("
                SELECT u.id, u.username, COALESCE(u.is_premium, 0) AS is_premium, $valueSql AS valore
                FROM utenti u
                JOIN utenti_personaggi up ON u.id = up.utente_id
                WHERE u.id = ?
                GROUP BY u.id, u.username, u.is_premium
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $mine = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($mine) {
                $stmt = $mysqli->prepare("
                    SELECT COUNT(*) FROM (
                        SELECT u.id, $valueSql AS valore
                        FROM utenti u
                        JOIN utenti_personaggi up ON u.id = up.utente_id
                        GROUP BY u.id
                    ) t
                    WHERE t.valore > ? OR (t.valore = ? AND t.id < ?)
                ");
                $value = (int)$mine['valore'];
                $stmt->bind_param('iii', $value, $value, $userId);
                $stmt->execute();
                $ahead = (int)$stmt->get_result()->fetch_row()[0];
                $stmt->close();
                $me = $row($mine, $ahead + 1);
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'type' => $type,
        'data' => $leaderboard,
        'me' => $me,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode(['status' => 'error', 'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Errore classifica']);
}
