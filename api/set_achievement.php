<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/stats_tracker.php';
require_once __DIR__ . '/../includes/mission_tracker.php';
require_once __DIR__ . '/../includes/gacha/config.php';

/**
 * Whether the browser may claim this achievement for this user.
 *
 * Most triggers live in page scripts (first visit, gambling, time spent) and
 * cannot be checked here, so they stay open. Two groups are not:
 *  - achievements the server already grants by itself, which no page ever
 *    requests from here: a request for one of them is someone typing ids;
 *  - achievements whose condition the server can read from its own data.
 * The checks are never stricter than the page script that fires them.
 */
function set_achievement_allowed(mysqli $mysqli, int $userId, int $achievementId, string $name): bool
{
    // Granted by the gacha engine, the profile editor (2: custom avatar) and
    // the character upgrades (looked up by name there, so by name here too).
    $serverOnlyIds = [2, GACHA_ACH_FIRST_PULL, GACHA_ACH_100_BOXES, GACHA_ACH_500_BOXES, GACHA_ACH_10_COMMONS];
    $serverOnlyNames = ['Massimo Splendore I', 'Massimo Splendore V', 'Massimo Splendore X', 'Esercito Dorato'];
    if (in_array($achievementId, $serverOnlyIds, true) || in_array($name, $serverOnlyNames, true)) {
        return false;
    }

    $count = static function (string $sql) use ($mysqli, $userId): int {
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) throw new RuntimeException('Query verifica achievement non disponibile.');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();
        return (int)($row[0] ?? 0);
    };

    switch ($achievementId) {
        case 7:
            // Merch: api/shop/fake_order.php leaves the flag, the confirm page
            // turns it into this one-shot permission.
            return (int)($_SESSION['shop_achievement_ok'] ?? 0) === 7;
        case GACHA_ACH_100_CHARACTERS:
            // Same count the gacha engine uses after a pull.
            return $count('SELECT COUNT(*) FROM utenti_personaggi WHERE utente_id = ?') >= 100;
        case 19:
            // GoonLand asks for it when the server counter reaches 100.
            return $count('SELECT clickgoon FROM utenti WHERE id = ?') >= 100;
        case 21:
            // achievements-globali.js asks for it at 20 unlocked.
            return $count('SELECT COUNT(*) FROM utenti_achievement WHERE utente_id = ? AND achievement_id <> 21') >= 20;
    }

    return true;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metodo non consentito.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Devi essere loggato.']);
    exit;
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
    http_response_code(419);
    echo json_encode(['status' => 'error', 'message' => 'Sessione scaduta. Ricarica la pagina.']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$achievementId = (int)($_POST['achievement_id'] ?? 0);
if ($userId <= 0 || $achievementId <= 0) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Achievement non valido.']);
    exit;
}

try {
    $mysqli->begin_transaction();

    // Serialize grants for the same account. Client-side achievement triggers
    // are cosmetic hints, never an authority for currency or other value.
    $stmt = $mysqli->prepare('SELECT id FROM utenti WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) throw new RuntimeException('Query utente non disponibile.');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $userExists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$userExists) {
        $mysqli->rollback();
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Utente non trovato.']);
        exit;
    }

    $stmt = $mysqli->prepare('SELECT id, nome FROM achievement WHERE id = ? LIMIT 1');
    if (!$stmt) throw new RuntimeException('Query achievement non disponibile.');
    $stmt->bind_param('i', $achievementId);
    $stmt->execute();
    $achievement = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$achievement) {
        $mysqli->rollback();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Achievement non trovato.']);
        exit;
    }

    $stmt = $mysqli->prepare(
        'SELECT 1 FROM utenti_achievement WHERE utente_id = ? AND achievement_id = ? LIMIT 1 FOR UPDATE'
    );
    if (!$stmt) throw new RuntimeException('Query verifica sblocco non disponibile.');
    $stmt->bind_param('ii', $userId, $achievementId);
    $stmt->execute();
    $alreadyUnlocked = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    if ($alreadyUnlocked) {
        $mysqli->commit();
        echo json_encode(['status' => 'already_unlocked', 'message' => 'Achievement già sbloccato.']);
        exit;
    }

    if (!set_achievement_allowed($mysqli, $userId, $achievementId, (string)($achievement['nome'] ?? ''))) {
        $mysqli->rollback();
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Questo achievement non si sblocca da qui.', 'code' => 'NOT_ELIGIBLE']);
        exit;
    }

    $stmt = $mysqli->prepare(
        'INSERT INTO utenti_achievement (utente_id, achievement_id, data) VALUES (?, ?, NOW())'
    );
    if (!$stmt) throw new RuntimeException('Query sblocco non disponibile.');
    $stmt->bind_param('ii', $userId, $achievementId);
    if (!$stmt->execute()) throw new RuntimeException('Sblocco non riuscito.');
    $stmt->close();

    $mysqli->commit();

    if ($achievementId === 7) {
        unset($_SESSION['shop_achievement_ok']);
    }

    // Statistiche Rewind, dopo il commit: lo sblocco resta valido anche se
    // il contatore non riesce a scrivere.
    try {
        stats_track($mysqli, $userId, 'achievements_unlocked');
        trackMissionProgress($mysqli, $userId, 'unlock_achievement');
    } catch (Throwable $trackErr) {
        error_log('[Stats set_achievement] ' . $trackErr->getMessage());
    }

    echo json_encode(['status' => 'success', 'message' => 'Achievement sbloccato.', 'points_added' => 0]);
} catch (Throwable $e) {
    try {
        $mysqli->rollback();
    } catch (Throwable $ignored) {
    }
    error_log('set_achievement failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore interno del server.']);
}
