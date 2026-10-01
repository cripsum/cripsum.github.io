<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../config/session_init.php';
$user_id = $_SESSION['user_id'] ?? 0;

$stmt = $mysqli->prepare("UPDATE utenti SET lineeGuidaChat = 1 WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

$_SESSION['lineeGuidaChat'] = 1;

header('Location: ../it/global-chat');
exit();
?>