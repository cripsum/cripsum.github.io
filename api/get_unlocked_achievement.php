<?php
/**
 * Gli achievement sbloccati da chi è collegato.
 *
 * Porta anche il token CSRF nell'intestazione: il popup di sblocco lo prende
 * da qui prima di chiedere un achievement.
 *
 * Endpoint : GET /api/get_unlocked_achievement.php
 */
require_once __DIR__ . '/../config/session_init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$user_id = (int)($_SESSION['user_id'] ?? 0);

// Chi non è collegato non ha niente da leggere: inutile aprire il database.
if ($user_id <= 0) {
    echo '[]';
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('X-CSRF-Token: ' . csrf_token());
cripsum_release_session();

$stmt = $mysqli->prepare("SELECT id, nome, descrizione, punti, img_url, data FROM achievement, utenti_achievement WHERE achievement.id = utenti_achievement.achievement_id AND utenti_achievement.utente_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$achievements = [];
while ($row = $result->fetch_assoc()) {
    $achievements[] = $row;
}

$stmt->close();

echo json_encode($achievements);
