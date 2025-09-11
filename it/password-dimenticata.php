<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
$user_id = $_SESSION['user_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <meta charset="UTF-8">
    <title>Cripsum™ - Password dimenticata</title>
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
        <h2>Password dimenticata</h2>
        <form method="POST" action="invia_link.php">
            <input type="email" name="email" placeholder="Inserisci la tua email" required>
            <button class="btn btn-secondary bottone" type="submit">Invia link</button>
        </form>
        <a class="nav-link" href="accedi"><i class="fas fa-arrow-left"></i> Torna al login</a>
    </div>
</body>
</html>
