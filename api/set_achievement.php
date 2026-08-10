<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

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

    $stmt = $mysqli->prepare('SELECT id FROM achievement WHERE id = ? LIMIT 1');
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

    $stmt = $mysqli->prepare(
        'INSERT INTO utenti_achievement (utente_id, achievement_id, data) VALUES (?, ?, NOW())'
    );
    if (!$stmt) throw new RuntimeException('Query sblocco non disponibile.');
    $stmt->bind_param('ii', $userId, $achievementId);
    if (!$stmt->execute()) throw new RuntimeException('Sblocco non riuscito.');
    $stmt->close();

    $mysqli->commit();
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
