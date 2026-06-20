<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Devi essere loggato.']);
    exit;
}

$username = trim($_GET['username'] ?? '');

if (empty($username)) {
    echo json_encode(['ok' => false, 'message' => 'Inserisci un nome utente.']);
    exit;
}

// Non puoi regalare il premium a te stesso
if (strtolower($username) === strtolower($_SESSION['username'] ?? '')) {
    echo json_encode(['ok' => true, 'exists' => true, 'is_self' => true, 'message' => 'Non puoi regalare il premium a te stesso (usa l\'acquisto diretto).']);
    exit;
}

$stmt = $mysqli->prepare("SELECT id, username, is_premium, isBannato FROM utenti WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['ok' => true, 'exists' => false, 'message' => 'Utente non trovato. Controlla lo spelling.']);
    exit;
}

if ((int)$user['isBannato'] === 1) {
    echo json_encode(['ok' => true, 'exists' => false, 'message' => 'L\'utente selezionato è attualmente sospeso.']);
    exit;
}

$isPremium = (int)($user['is_premium'] ?? 0) === 1;

echo json_encode([
    'ok' => true,
    'exists' => true,
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'is_premium' => $isPremium,
    'message' => $isPremium ? 'L\'utente ha già un account Premium!' : 'Pronto a ricevere il regalo!'
]);
exit;
