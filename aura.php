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

    <div class="container-fluid my-5 paginainterachisiamo testobianco" style="padding-top: 5rem; padding-bottom: 4rem; overflow: hidden;"></div>
        <div class="text-center">
            <img src="img/absolutetopaz.jpg" alt="Goofy Animation 1" class="goofy-animation-1" style="max-width: 200px; height: auto; position: absolute; top: 10%; left: 10%;">
            <img src="img/absolutetopaz.jpg" alt="Goofy Animation 2" class="goofy-animation-2" style="max-width: 150px; height: auto; position: absolute; top: 20%; right: 15%;">
            <img src="img/absolutetopaz.jpg" alt="Goofy Animation 3" class="goofy-animation-3" style="max-width: 180px; height: auto; position: absolute; top: 50%; left: 5%;">
            <img src="img/absolutetopaz.jpg" alt="Goofy Animation 4" class="goofy-animation-4" style="max-width: 120px; height: auto; position: absolute; bottom: 20%; right: 20%;">
            <img src="img/absolutetopaz.jpg" alt="Goofy Animation 5" class="goofy-animation-5" style="max-width: 250px; height: auto; position: absolute; top: 70%; left: 30%;">
            <img src="img/absolutetopaz.jpg" alt="Goofy Animation 6" class="goofy-animation-6" style="max-width: 100px; height: auto; position: absolute; top: 30%; left: 50%;">
            <img src="img/absolutetopaz.jpg" alt="Goofy Animation 7" class="goofy-animation-7" style="max-width: 160px; height: auto; position: absolute; bottom: 40%; left: 70%;">
            <img src="img/absolutetopaz.jpg" alt="Goofy Animation 8" class="goofy-animation-8" style="max-width: 220px; height: auto; position: absolute; top: 60%; right: 40%;">
            <img src="img/absolutetopaz.jpg" alt="Goofy Animation 9" class="goofy-animation-9" style="max-width: 140px; height: auto; position: absolute; bottom: 10%; left: 45%;">
            <img src="img/absolutetopaz.jpg" alt="Goofy Animation 10" class="goofy-animation-10" style="max-width: 190px; height: auto; position: absolute; top: 40%; right: 5%;">
            
            <!-- Nuove animazioni fluide -->
            <img src="img/absolutetopaz.jpg" alt="Random Mover 1" class="random-mover-1" style="max-width: 130px; height: auto; position: fixed; z-index: 1000; top: 0; left: 0;">
            <img src="img/absolutetopaz.jpg" alt="Random Mover 2" class="random-mover-2" style="max-width: 170px; height: auto; position: fixed; z-index: 1000; top: 0; left: 0;">
            <img src="img/absolutetopaz.jpg" alt="DVD Bouncer" class="dvd-bouncer" style="max-width: 110px; height: auto; position: fixed; z-index: 1000; top: 0; left: 0;">
        </div>
    </div>

    <style>
        @keyframes goofyBounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0) rotate(0deg) scale(1);
            }
            40% {
                transform: translateY(-30px) rotate(-15deg) scale(1.2);
            }
            60% {
                transform: translateY(-15px) rotate(15deg) scale(0.8);
            }
        }

        @keyframes crazyRotate {
            0% { transform: rotate(0deg) scale(1); }
            25% { transform: rotate(90deg) scale(1.5); }
            50% { transform: rotate(180deg) scale(0.5); }
            75% { transform: rotate(270deg) scale(1.8); }
            100% { transform: rotate(360deg) scale(1); }
        }

        @keyframes zigzag {
            0% { transform: translateX(0) translateY(0) rotate(0deg); }
            25% { transform: translateX(50px) translateY(-30px) rotate(45deg); }
            50% { transform: translateX(-30px) translateY(-60px) rotate(-45deg); }
            75% { transform: translateX(80px) translateY(-20px) rotate(90deg); }
            100% { transform: translateX(0) translateY(0) rotate(0deg); }
        }

        @keyframes wobble {
            0% { transform: translateX(0) skew(0deg, 0deg); }
            15% { transform: translateX(-25px) skew(-15deg, 5deg); }
            30% { transform: translateX(20px) skew(10deg, -5deg); }
            45% { transform: translateX(-15px) skew(-10deg, 3deg); }
            60% { transform: translateX(10px) skew(5deg, -3deg); }
            75% { transform: translateX(-5px) skew(-5deg, 2deg); }
            100% { transform: translateX(0) skew(0deg, 0deg); }
        }

        @keyframes pulse {
            0% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(2) rotate(180deg); }
            100% { transform: scale(1) rotate(360deg); }
        }

        @keyframes fly {
            0% { transform: translate(0, 0) rotate(0deg); }
            20% { transform: translate(100px, -50px) rotate(72deg); }
            40% { transform: translate(-80px, -100px) rotate(144deg); }
            60% { transform: translate(120px, 30px) rotate(216deg); }
            80% { transform: translate(-60px, 80px) rotate(288deg); }
            100% { transform: translate(0, 0) rotate(360deg); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10% { transform: translateX(-10px) rotate(-5deg); }
            20% { transform: translateX(10px) rotate(5deg); }
            30% { transform: translateX(-10px) rotate(-3deg); }
            40% { transform: translateX(10px) rotate(3deg); }
            50% { transform: translateX(-10px) rotate(-2deg); }
            60% { transform: translateX(10px) rotate(2deg); }
            70% { transform: translateX(-10px) rotate(-1deg); }
            80% { transform: translateX(10px) rotate(1deg); }
            90% { transform: translateX(-10px) rotate(0deg); }
        }

        @keyframes spiral {
            0% { transform: translate(0, 0) rotate(0deg) scale(1); }
            25% { transform: translate(50px, 50px) rotate(90deg) scale(0.5); }
            50% { transform: translate(0, 100px) rotate(180deg) scale(1.5); }
            75% { transform: translate(-50px, 50px) rotate(270deg) scale(0.8); }
            100% { transform: translate(0, 0) rotate(360deg) scale(1); }
        }

        @keyframes elastic {
            0% { transform: scale(1, 1); }
            20% { transform: scale(1.5, 0.5); }
            40% { transform: scale(0.5, 1.5); }
            60% { transform: scale(1.2, 0.8); }
            80% { transform: scale(0.9, 1.1); }
            100% { transform: scale(1, 1); }
        }

        @keyframes flip {
            0% { transform: perspective(400px) rotateY(0); }
            40% { transform: perspective(400px) translateZ(150px) rotateY(170deg); }
            50% { transform: perspective(400px) translateZ(150px) rotateY(190deg) scale(1.5); }
            80% { transform: perspective(400px) rotateY(360deg) scale(0.8); }
            100% { transform: perspective(400px) rotateY(360deg) scale(1); }
        }

        /* Nuove animazioni fluide */
        @keyframes randomPath1 {
            0% { transform: translate(10vw, 10vh) rotate(0deg); }
            15% { transform: translate(calc(80vw - 130px), 20vh) rotate(54deg); }
            30% { transform: translate(calc(70vw - 130px), calc(70vh - 130px)) rotate(108deg); }
            45% { transform: translate(20vw, calc(80vh - 130px)) rotate(162deg); }
            60% { transform: translate(5vw, 50vh) rotate(216deg); }
            75% { transform: translate(calc(60vw - 130px), 30vh) rotate(270deg); }
            90% { transform: translate(calc(40vw - 130px), calc(60vh - 130px)) rotate(324deg); }
            100% { transform: translate(10vw, 10vh) rotate(360deg); }
        }

        @keyframes randomPath2 {
            0% { transform: translate(calc(90vw - 170px), calc(80vh - 170px)) rotate(0deg) scale(1); }
            20% { transform: translate(30vw, 10vh) rotate(72deg) scale(1.3); }
            40% { transform: translate(10vw, calc(60vh - 170px)) rotate(144deg) scale(0.7); }
            60% { transform: translate(calc(75vw - 170px), calc(40vh - 170px)) rotate(216deg) scale(1.1); }
            80% { transform: translate(calc(50vw - 170px), calc(85vh - 170px)) rotate(288deg) scale(0.9); }
            100% { transform: translate(calc(90vw - 170px), calc(80vh - 170px)) rotate(360deg) scale(1); }
        }

        @keyframes dvdBounce {
            0% { transform: translate(0px, 0px); }
            25% { transform: translate(calc(100vw - 110px), calc(25vh - 55px)); }
            50% { transform: translate(calc(100vw - 110px), calc(100vh - 110px)); }
            75% { transform: translate(0px, calc(100vh - 110px)); }
            100% { transform: translate(0px, 0px); }
        }

        .goofy-animation-1 {
            animation: goofyBounce 1.5s ease-in-out infinite;
        }

        .goofy-animation-2 {
            animation: crazyRotate 3s linear infinite;
        }

        .goofy-animation-3 {
            animation: zigzag 2s ease-in-out infinite;
        }

        .goofy-animation-4 {
            animation: wobble 1s ease-in-out infinite;
        }

        .goofy-animation-5 {
            animation: pulse 2.5s ease-in-out infinite;
        }

        .goofy-animation-6 {
            animation: fly 4s ease-in-out infinite;
        }

        .goofy-animation-7 {
            animation: shake 0.5s ease-in-out infinite;
        }

        .goofy-animation-8 {
            animation: spiral 3.5s ease-in-out infinite;
        }

        .goofy-animation-9 {
            animation: elastic 1.8s ease-in-out infinite;
        }

        .goofy-animation-10 {
            animation: flip 2.2s ease-in-out infinite;
        }

        /* Nuove classi per movimenti fluidi */
        .random-mover-1 {
            animation: randomPath1 12s ease-in-out infinite;
        }

        .random-mover-2 {
            animation: randomPath2 15s ease-in-out infinite;
        }

        .dvd-bouncer {
            animation: dvdBounce 8s linear infinite;
        }
    </style>

            <!-- <div id="achievement-popup" class="popup">
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

        <script src="../js/modeChanger.js"></script> -->
</body>
</html>