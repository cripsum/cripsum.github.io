<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$message = '';
$messageType = '';

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);

    if (!empty($token)) {
        $user = verifyEmail($mysqli, $token);

        if ($user) {
            $message = "Thank you for verifying your email! Your account is now active. You can log in and start using Cripsum™.";
            $messageType = 'success';

            sendWelcomeEmail($user['email'], $user['username']);
        } else {
            $message = "Invalid token or already used.";
            $messageType = 'error';
        }
    } else {
        $message = "Missing verification token.";
        $messageType = 'error';
    }
} else {
    $message = "Missing verification token.";
    $messageType = 'error';
}

if ($_POST && isset($_POST['resend_email'])) {
    $email = trim($_POST['email'] ?? '');

    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (resendVerificationEmail($mysqli, $email)) {
            $message = "Verification email resent.";
            $messageType = 'success';
        } else {
            $message = "Email already verified or resend failed.";
            $messageType = 'error';
        }
    } else {
        $message = "Invalid email.";
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Email Verification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/forms/forms.css?v=1.0-unified">
    <script src="/assets/forms/forms.js?v=1.0-unified" defer></script>
</head>

<body class="form-page">
    <?php include '../includes/navbar.php'; ?>
    


    <div class="form-bg" aria-hidden="true">
        <span class="form-orb form-orb--one"></span>
        <span class="form-orb form-orb--two"></span>
        <span class="form-grid-bg"></span>
    </div>


    <main class="form-shell form-shell--narrow">
        <section class="form-card form-reveal">
            <div class="confirm-icon">
                <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-check' : 'fa-triangle-exclamation'; ?>"></i>
            </div>

            <div class="form-card__header" style="text-align:center;">
                <span class="form-pill">Email</span>
                <h1>Email Verification</h1>
                <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="form-alert <?php echo $messageType === 'success' ? 'form-alert--success' : 'form-alert--error'; ?>">
                <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <?php if ($messageType === 'success'): ?>
                <div class="form-actions form-actions--center">
                    <a href="accedi" class="form-btn form-btn--primary">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <span>Log in now</span>
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="" data-form-loading>
                    <label class="form-field">
                        <span>Resend Verification</span>
                        <input type="email" id="email" name="email" placeholder="email@example.com" required>
                        <small>Enter your email to receive a new link.</small>
                    </label>

                    <div class="form-actions">
                        <button type="submit" name="resend_email" class="form-btn form-btn--primary form-btn--wide" data-loading-text="Sending...">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>Resend Email</span>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="form-links">
                <a href="registrati">Back to registration</a>
            </div>
        </section>
    </main>

    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
