<?php
/**
 * Cripsum™ — API Missioni: CLAIM
 * Riscatta la ricompensa di una missione completata.
 * Aggiunge i punti in utenti.soldi.
 * Protezione anti-doppio-claim server-side.
 *
 * Endpoint : POST /api/missions/claim.php
 * Auth     : sessione PHP (isLoggedIn())
 * Body     : JSON { "user_mission_id": int }
 * Response : JSON
 */

require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Auth ──────────────────────────────────────────────────────
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato', 'code' => 'UNAUTHENTICATED']);
    exit();
}

// ── Solo POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit();
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database non disponibile']);
    exit();
}

$mysqli->set_charset('utf8mb4');
$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

// ── Leggi body JSON ──────────────────────────────────────────
$body = file_get_contents('php://input');
$payload = json_decode($body, true);

$userMissionId = isset($payload['user_mission_id']) ? (int)$payload['user_mission_id'] : 0;

if ($userMissionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametro mancante o non valido: user_mission_id']);
    exit();
}

// ── Transazione atomica: verifica + claim + punti ────────────
$mysqli->begin_transaction();

try {
    // 1. Leggi la missione con lock esclusivo (anti-race-condition)
    $stmt = $mysqli->prepare("
        SELECT
            um.id,
            um.user_id,
            um.completata,
            um.riscattata,
            m.punti_reward,
            m.titolo,
            m.slug
        FROM user_missions um
        JOIN missions m ON m.id = um.mission_id
        WHERE um.id = ?
        FOR UPDATE
    ");
    $stmt->bind_param('i', $userMissionId);
    $stmt->execute();
    $result  = $stmt->get_result();
    $mission = $result->fetch_assoc();
    $stmt->close();

    // ── Validazioni ───────────────────────────────────────────

    // La missione esiste?
    if (!$mission) {
        $mysqli->rollback();
        http_response_code(404);
        echo json_encode(['error' => 'Missione non trovata', 'code' => 'NOT_FOUND']);
        exit();
    }

    // Appartiene all'utente loggato?
    if ((int)$mission['user_id'] !== $userId) {
        $mysqli->rollback();
        http_response_code(403);
        echo json_encode(['error' => 'Accesso negato', 'code' => 'FORBIDDEN']);
        exit();
    }

    // È completata?
    if ((int)$mission['completata'] !== 1) {
        $mysqli->rollback();
        http_response_code(409);
        echo json_encode(['error' => 'Missione non ancora completata', 'code' => 'NOT_COMPLETED']);
        exit();
    }

    // Non è già stata riscattata?
    if ((int)$mission['riscattata'] === 1) {
        $mysqli->rollback();
        http_response_code(409);
        echo json_encode(['error' => 'Ricompensa già riscattata', 'code' => 'ALREADY_CLAIMED']);
        exit();
    }

    $punti = (int)$mission['punti_reward'];

    // 2. Segna come riscattata
    $updateMission = $mysqli->prepare("
        UPDATE user_missions
        SET riscattata = 1, claimed_at = NOW()
        WHERE id = ? AND riscattata = 0
    ");
    $updateMission->bind_param('i', $userMissionId);
    $updateMission->execute();

    // Controlla che l'UPDATE abbia effettivamente modificato 1 riga
    // (ulteriore protezione contro race condition doppio-click)
    if ($updateMission->affected_rows !== 1) {
        $updateMission->close();
        $mysqli->rollback();
        http_response_code(409);
        echo json_encode(['error' => 'Ricompensa già riscattata (concorrenza)', 'code' => 'ALREADY_CLAIMED']);
        exit();
    }
    $updateMission->close();

    // 3. Aggiungi i punti all'utente
    $updatePoints = $mysqli->prepare("
        UPDATE utenti SET soldi = soldi + ? WHERE id = ?
    ");
    $updatePoints->bind_param('ii', $punti, $userId);
    $updatePoints->execute();
    $updatePoints->close();

    // 4. Leggi il nuovo totale punti
    $stmtPunti = $mysqli->prepare("SELECT soldi FROM utenti WHERE id = ?");
    $stmtPunti->bind_param('i', $userId);
    $stmtPunti->execute();
    $resultPunti  = $stmtPunti->get_result();
    $userRow      = $resultPunti->fetch_assoc();
    $stmtPunti->close();
    $nuovoTotale  = (int)($userRow['soldi'] ?? 0);

    $mysqli->commit();

    // ── Risposta successo ─────────────────────────────────────
    echo json_encode([
        'success'      => true,
        'punti_earned' => $punti,
        'nuovo_totale' => $nuovoTotale,
        'missione'     => $mission['titolo'],
        'slug'         => $mission['slug'],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $mysqli->rollback();
    error_log('[API missions/claim] User ' . $userId . ' — ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno', 'code' => 'INTERNAL_ERROR']);
}
