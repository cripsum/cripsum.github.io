<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

if(!isset($_COOKIE['banned']) || $_COOKIE['banned'] == '0') {
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

        setcookie('banned', '1', time() + (10 * 365 * 24 * 60 * 60), '/');
        session_destroy();
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
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
            box-sizing: border-box;
        }
        .ban-container {
            padding: 40px;
            border-radius: 10px;
            background-color: #333;
            color: white;
            box-shadow: 0 0 8px 4px rgba(255, 255, 255, 0);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .ban-icon {
            font-size: 64px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        h1 {
            color: #dc3545;
            margin-bottom: 20px;
            font-size: 2rem;
        }
        p {
            color:rgb(255, 255, 255);
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .contact-info {
            background-color:rgba(248, 249, 250, 0);
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            .ban-container {
                padding: 30px 20px;
            }
            .ban-icon {
                font-size: 48px;
            }
            h1 {
                font-size: 1.5rem;
            }
            .contact-info {
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            .ban-container {
                padding: 20px 15px;
            }
            .ban-icon {
                font-size: 40px;
            }
            h1 {
                font-size: 1.3rem;
            }
            p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="ban-container">
        <div class="ban-icon">🚫</div>
        <h1>Account Bannato</h1>
        <p>Il tuo account è stato sospeso per violazione dei nostri termini di servizio.</p>
        <p>Godo coglione</p>
        <img src="/img/toppng.com-laughing-pointing-emoji-1645x1070.png" alt="">
        <p>Se ritieni che questo sia un errore, puoi contattare il nostro supporto.</p>
        
        <div class="contact-info">
            <h3 class="testobianco" >Contatta il Supporto</h3>
            <p>Email: support@cripsum.com</p>
            <p>Includi il tuo username e una descrizione dettagliata del problema.</p>
        </div>
    </div>
</body>
</html>