<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$token = $_POST['token'] ?? '';
$nuova_password = $_POST['nuova_password'] ?? '';
$messaggio = '';

if ($token && $nuova_password) {
    $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE reset_token = ? AND token_scadenza > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $hash = password_hash($nuova_password, PASSWORD_DEFAULT);

        $stmt = $mysqli->prepare("UPDATE utenti SET password = ?, reset_token = NULL, token_scadenza = NULL WHERE id = ?");
        $stmt->bind_param("si", $hash, $id);
        $stmt->execute();

        $messaggio = "Password aggiornata con successo.";
    } else {
        $messaggio = "Token non valido o scaduto.";
    }
} else {
    $messaggio = "Richiesta non valida.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Esito reset</title>
    <style>
        body {
            background: #0e0e0e;
            color: white;
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .box {
            background: #1a1a1a;
            padding: 30px;
            border: 1px solid #ffffff22;
            border-radius: 12px;
            box-shadow: 0 0 10px #ffffff11;
            text-align: center;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="box">
        <h2><?= htmlspecialchars($messaggio) ?></h2>
    </div>
</body>
</html>
