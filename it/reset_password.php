<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
$user_id = $_SESSION['user_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Reset password</title>
    <style>
        body {
            background: #0e0e0e;
            color: white;
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .box {
            background: #1a1a1a;
            padding: 30px;
            border: 1px solid #ffffff22;
            border-radius: 12px;
            box-shadow: 0 0 10px #ffffff11;
            text-align: center;
            width: 100%;
            max-width: 400px;
        }
        input {
            padding: 12px;
            width: 100%;
            margin-bottom: 15px;
            border: 1px solid #333;
            border-radius: 6px;
            background: #111;
            color: white;
        }
        button {
            background: #ffffff;
            color: black;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.2s;
        }
        button:hover {
            background: #ddd;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar-morta.php'; ?>
    <div class="box">
        <h2>Nuova password</h2>
        <form method="POST" action="salva_nuova_password.php">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="password" name="nuova_password" placeholder="Nuova password" required>
            <button class="btn btn-secondary bottone" type="submit">Salva</button>
        </form>
    </div>
</body>
</html>
