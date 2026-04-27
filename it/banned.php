<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_COOKIE['banned']) || $_COOKIE['banned'] == '0') {
    if (!isLoggedIn()) {
        header('Location: home');
        exit();
    }

    $user_id = (int)$_SESSION['user_id'];

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

    if ((int)$row['isBannato'] !== 1) {
        header('Location: home');
        exit();
    }

    setcookie('banned', '1', time() + (10 * 365 * 24 * 60 * 60), '/');
    setcookie('user_id', (string)$user_id, time() + (10 * 365 * 24 * 60 * 60), '/');
    session_destroy();
} else {
    $utente_id = isset($_COOKIE['user_id']) ? (int)$_COOKIE['user_id'] : 0;

    if ($utente_id <= 0) {
        setcookie('banned', '0', time() + (10 * 365 * 24 * 60 * 60), '/');
        header('Location: home');
        exit();
    }

    $stmt = $mysqli->prepare("SELECT isBannato FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $utente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $row = $result ? $result->fetch_assoc() : null;

    if (!$row || (int)$row['isBannato'] !== 1) {
        setcookie('banned', '0', time() + (10 * 365 * 24 * 60 * 60), '/');
        session_destroy();
        header('Location: home');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Account sospeso</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/static/static.css?v=1.0-static">
    <script src="/assets/static/static.js?v=1.0-static" defer></script>

</head>

<body class="static-page">

    <div class="static-bg" aria-hidden="true">
        <span class="static-orb static-orb--one"></span>
        <span class="static-orb static-orb--two"></span>
        <span class="static-grid-bg"></span>
    </div>


    <main class="static-shell static-shell--narrow">
        <section class="static-ban-card static-reveal">
            <div class="static-ban-icon">
                <i class="fas fa-ban"></i>
            </div>

            <span class="static-pill">Account</span>
            <h1>Account sospeso</h1>
            <p>Il tuo account è stato sospeso per violazione dei termini o delle regole del sito.</p>

            <div class="static-alert static-alert--danger" style="margin-top:1rem; text-align:left;">
                <i class="fas fa-circle-exclamation"></i>
                <p>Se pensi sia un errore, contatta il supporto e includi username, email dell’account e una descrizione chiara.</p>
            </div>

            <div class="static-actions" style="justify-content:center;">
                <a href="mailto:support@cripsum.com" class="static-btn static-btn--primary">
                    <i class="fas fa-envelope"></i>
                    <span>Contatta supporto</span>
                </a>
            </div>
        </section>
    </main>
</body>
</html>
