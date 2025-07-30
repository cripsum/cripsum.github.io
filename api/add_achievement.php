<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo non consentito');
}

if (!isset($_POST['user_id'], $_POST['achievement_id'])) {
    http_response_code(400);
    exit('Dati mancanti');
}

$user_id = intval($_POST['user_id']);
$achievement_id = intval($_POST['achievement_id']);

$stmt = $mysqli->prepare("INSERT INTO utenti_achievement (utente_id, achievement_id, data) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE data = NOW()");
$stmt->bind_param("ii", $user_id, $achievement_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Errore aggiunta achievement']);
}

$stmt2 = $mysqli->prepare("SELECT punti FROM achievement WHERE id = ?");
$stmt2->bind_param("i", $achievement_id);

if ($stmt2->execute()) {
    $result2 = $stmt2->get_result();
    if ($row2 = $result2->fetch_assoc()) {
        $punti = (int)$row2['punti'];
        $stmt3 = $mysqli->prepare("UPDATE utenti SET soldi = soldi + ? WHERE id = ?");
        $stmt3->bind_param("ii", $punti, $user_id);
        $stmt3->execute();
        $stmt3->close();
    }
}
$stmt2->close();

$stmt->close();
$mysqli->close();
