<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$user_id = $_SESSION['user_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <meta charset="UTF-8">
    <title>Cripsum™ - Forgot password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/forms/forms.css?v=1.0-unified">
    <script src="/assets/forms/forms.js?v=1.0-unified" defer></script>
</head>

<body class="form-page">
    <?php include '../includes/navbar-morta.php'; ?>


    <div class="form-bg" aria-hidden="true">
        <span class="form-orb form-orb--one"></span>
        <span class="form-orb form-orb--two"></span>
        <span class="form-grid-bg"></span>
    </div>


    <main class="form-shell form-shell--narrow">
        <section class="form-card form-reveal">
            <div class="form-card__header">
                <span class="form-pill">Account</span>
                <h1>Forgot password</h1>
                <p>Enter your email. We'll send you a link to reset your password.</p>
            </div>

            <form method="POST" action="invia_link.php" data-form-loading>
                <label class="form-field">
                    <span>Email</span>
                    <input type="email" name="email" placeholder="email@example.com" required autocomplete="email">
                </label>

                <div class="form-actions">
                    <button class="form-btn form-btn--primary form-btn--wide" type="submit" data-loading-text="Sending link...">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Send link</span>
                    </button>
                </div>
            </form>

            <div class="form-links">
                <a href="accedi"><i class="fa-solid fa-arrow-left"></i> Back to login</a>
            </div>
        </section>
    </main>
</body>

</html>