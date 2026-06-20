<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$motivoBan = '';
$bannedUntil = null;

if (!isset($_COOKIE['banned']) || $_COOKIE['banned'] == '0') {
    if (!isLoggedIn()) {
        header('Location: home');
        exit();
    }

    $user_id = (int)$_SESSION['user_id'];

    $stmt = $mysqli->prepare("SELECT isBannato, motivo_ban, banned_until FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        header('Location: home');
        exit();
    }

    $row = $result->fetch_assoc();
    $motivoBan = $row['motivo_ban'] ?? '';
    $bannedUntil = $row['banned_until'] ?? null;

    if ($bannedUntil !== null && strtotime($bannedUntil) <= time()) {
        $mysqli->query("UPDATE utenti SET isBannato = 0, banned_until = NULL, motivo_ban = NULL WHERE id = " . $user_id);
        setcookie('banned', '0', time() + (10 * 365 * 24 * 60 * 60), '/');
        header('Location: home');
        exit();
    }

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

    $stmt = $mysqli->prepare("SELECT isBannato, motivo_ban, banned_until FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $utente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $row = $result ? $result->fetch_assoc() : null;
    $motivoBan = $row ? ($row['motivo_ban'] ?? '') : '';
    $bannedUntil = $row ? ($row['banned_until'] ?? null) : null;

    if ($row && $bannedUntil !== null && strtotime($bannedUntil) <= time()) {
        $mysqli->query("UPDATE utenti SET isBannato = 0, banned_until = NULL, motivo_ban = NULL WHERE id = " . $utente_id);
        setcookie('banned', '0', time() + (10 * 365 * 24 * 60 * 60), '/');
        header('Location: home');
        exit();
    }

    if (!$row || (int)$row['isBannato'] !== 1) {
        setcookie('banned', '0', time() + (10 * 365 * 24 * 60 * 60), '/');
        session_destroy();
        header('Location: home');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Account banned</title>

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
            <div class="static-ban-icon-img" style="margin-bottom: 1rem;">
                <img src="/img/bannatogodo.png" alt="Banned" style="width: 15rem; height: 15rem; object-fit: contain;">
            </div>

            <h1>Account banned</h1>
            <p>Your account has been banned for violating the terms or rules of the site.</p>

            <div class="static-alert" style="margin-top: 1rem; text-align: left; border-color: rgba(255, 45, 85, 0.2);">
                <i class="fa-solid fa-gavel" style="color: var(--static-red);"></i>
                <div style="flex: 1;">
                    <strong style="color: #fff; font-size: 0.9rem; display: block;">Suspension Details</strong>
                    <?php if (!empty($motivoBan)): ?>
                        <p style="margin: 0.35rem 0 0; font-size: 0.85rem; color: var(--static-muted);">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($motivoBan); ?>
                        </p>
                    <?php endif; ?>
                    <p style="margin: 0.35rem 0 0; font-size: 0.85rem; color: var(--static-muted);">
                        <strong>Duration:</strong> 
                        <?php 
                        if (empty($bannedUntil)) {
                            echo "Permanent";
                        } else {
                            echo "Until " . date('d/m/Y \a\t H:i', strtotime($bannedUntil));
                        }
                        ?>
                    </p>
                </div>
            </div>

            <div class="static-alert static-alert--danger" style="margin-top:1rem; text-align:left;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <p>If you think this is an error, please contact support at <a href="mailto:dio.covid@mail.com" style="color: #ff9ab8; text-decoration: underline;">dio.covid@mail.com</a> or on Discord at <a href="https://discord.com/users/963536045180350474" target="_blank" rel="noopener" style="color: #ff9ab8; text-decoration: underline;">discord.com</a>.</p>
            </div>

            <div class="static-actions" style="justify-content:center; gap: 10px;">
                <a href="mailto:dio.covid@mail.com" class="static-btn static-btn--primary">
                    <i class="fa-solid fa-envelope"></i>
                    <span>E-mail</span>
                </a>
                <a href="https://discord.com/users/963536045180350474" target="_blank" rel="noopener" class="static-btn static-btn--primary" style="background: #5865F2; border-color: #5865F2;">
                    <i class="fa-brands fa-discord"></i>
                    <span>Discord</span>
                </a>
            </div>
        </section>
    </main>
</body>

</html>