<?php
require_once 'config/session_init.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
checkBan($mysqli);

$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

$user_id = $_SESSION['user_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <?php include 'includes/head-import.php'; ?>
    <title>Cripsum™ - aura</title>
        <style>
        .card:hover {
            transform: translateY(0px) scale(1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-color: rgba(255, 255, 255, 0.12);
        }

    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/impostazioni.php'; ?>

    <div class="container my-5 paginainterachisiamo testobianco" style="padding-top: 5rem; padding-bottom: 4rem;">
        <div class="text-center">
            <img src="img/absolutetopaz.jpg" alt="Goofy Animation" class="goofy-animation" style="max-width: 300px; height: auto;">
        </div>
    </div>

    <style>
        @keyframes goofyBounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            40% {
                transform: translateY(-30px) rotate(-5deg);
            }
            60% {
                transform: translateY(-15px) rotate(5deg);
            }
        }

        .goofy-animation {
            animation: goofyBounce 2s ease-in-out infinite;
        }
    </style>

            <div id="achievement-popup" class="popup">
            <img id="popup-image" src="" alt="Achievement" />
            <div>
                <h3 id="popup-title"></h3>
                <p id="popup-description"></p>
            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>

        <script src="../js/modeChanger.js"></script>
</body>
</html>