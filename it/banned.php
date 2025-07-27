<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: home');
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $mysqli->prepare("SELECT isBannato FROM utenti WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    header('Location: home');
    exit();
}

$row = $result->fetch_assoc();

if ($row['isBannato'] != 1) {
    header('Location: home');
    exit();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Account Bannato</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .ban-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(255, 255, 255, 0.3);
            text-align: center;
            max-width: 500px;
        }
        .ban-icon {
            font-size: 64px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        h1 {
            color: #dc3545;
            margin-bottom: 20px;
        }
        p {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .contact-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="ban-container">
        <div class="ban-icon">🚫</div>
        <h1>Account Bannato</h1>
        <p>Il tuo account è stato sospeso per violazione dei nostri termini di servizio.</p>
        <p>Se ritieni che questo sia un errore, puoi contattare il nostro supporto.</p>
        <p>Godo coglione</p>
        <img src="/img/Laughing_emoji_no_background.webp" alt="">
        
        <div class="contact-info">
            <h3>Contatta il Supporto</h3>
            <p>Email: support@cripsum.com</p>
            <p>Includi il tuo username e una descrizione dettagliata del problema.</p>
        </div>
    </div>
</body>
</html>