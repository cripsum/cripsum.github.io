<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metodo non consentito']);
    exit;
}

$admin_id = (int)($_SESSION['user_id'] ?? 0);
if ($admin_id <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Devi essere loggato']);
    exit;
}

$roleStmt = $mysqli->prepare("SELECT ruolo FROM utenti WHERE id = ? LIMIT 1");
$roleStmt->bind_param("i", $admin_id);
$roleStmt->execute();
$role = $roleStmt->get_result()->fetch_assoc()['ruolo'] ?? 'utente';
$roleStmt->close();

if (!in_array($role, ['admin', 'owner'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Permessi insufficienti']);
    exit;
}

if (!isset($_POST['user_id'], $_POST['character_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dati mancanti']);
    exit;
}

$user_id = intval($_POST['user_id']);
$character_id = intval($_POST['character_id']);
date_default_timezone_set('Europe/Rome');

if ($user_id <= 0 || $character_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID non validi']);
    exit;
}

$stmt = $mysqli->prepare("INSERT INTO utenti_personaggi (utente_id, personaggio_id, data, quantità) VALUES (?, ?, NOW(), 1) ON DUPLICATE KEY UPDATE quantità = quantità + 1");
$stmt->bind_param("ii", $user_id, $character_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Errore aggiunta personaggio']);
}

$stmt->close();
$mysqli->close();
