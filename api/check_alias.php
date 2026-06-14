<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'available' => false, 'message' => 'Devi essere loggato.']);
    exit;
}

$alias = isset($_GET['alias']) ? trim((string)$_GET['alias']) : '';
$currentUserId = (int)$_SESSION['user_id'];
$targetUserId = isset($_GET['target_user_id']) && profile_is_staff() ? (int)$_GET['target_user_id'] : $currentUserId;

header('Content-Type: application/json');

if ($alias === '') {
    echo json_encode(['ok' => true, 'available' => true, 'message' => '']); // empty is allowed (disables alias)
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $alias)) {
    echo json_encode(['ok' => true, 'available' => false, 'message' => 'L\'alias deve contenere da 3 a 30 caratteri (lettere, numeri, trattini, underscore).']);
    exit;
}

// Blacklist di parole riservate
$blacklist = [
    'api', 'assets', 'audio', 'auth', 'config', 'css', 'data', 'en', 'img', 'includes', 'it', 'js', 'mc', 'user', 'vid', 
    'u', 'admin', 'logout', 'profile', 'bio', 'gaming', 'game', 'negozio', 'shop', 'privacy', 'tos', 'terms', 'about', 
    'chisiamo', 'merch', 'checkout', 'lootbox', 'shitpost', 'missions', 'index', '404', 'aura', 'discord', 'register', 
    'registrati', 'login', 'accedi', 'settings', 'impostazioni', 'dashboard', 'help', 'support', 'uwu', 'db', 'search'
];

if (in_array(strtolower($alias), $blacklist, true)) {
    echo json_encode(['ok' => true, 'available' => false, 'message' => 'Questo alias è riservato e non può essere utilizzato.']);
    exit;
}

// Controllo collisione con gli username di ALTRI utenti (il proprio username è permesso come alias)
$stmt = $mysqli->prepare("SELECT id FROM utenti WHERE LOWER(username) = LOWER(?) AND id != ? LIMIT 1");
$stmt->bind_param('si', $alias, $targetUserId);
$stmt->execute();
$userCollision = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($userCollision) {
    echo json_encode(['ok' => true, 'available' => false, 'message' => 'Questo alias coincide con lo username di un altro utente.']);
    exit;
}

// Controllo collisione con alias di altri utenti
$stmt = $mysqli->prepare("SELECT id FROM utenti WHERE LOWER(custom_alias) = LOWER(?) AND id != ? LIMIT 1");
$stmt->bind_param('si', $alias, $targetUserId);
$stmt->execute();
$aliasCollision = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($aliasCollision) {
    echo json_encode(['ok' => true, 'available' => false, 'message' => 'Alias già in uso da un altro utente.']);
    exit;
}

echo json_encode(['ok' => true, 'available' => true, 'message' => 'Alias disponibile!']);
exit;
