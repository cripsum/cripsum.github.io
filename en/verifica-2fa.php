<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (function_exists('isLoggedIn') && isLoggedIn()) {
    header('Location: home');
    exit();
}

if (empty($_SESSION['pending_2fa_user_id'])) {
    header('Location: accedi');
    exit();
}

$error = '';
$info = '';

if (!empty($_SESSION['pending_2fa_started_at']) && time() - (int)$_SESSION['pending_2fa_started_at'] > 600) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_started_at'], $_SESSION['pending_2fa_identifier'], $_SESSION['pending_2fa_redirect']);
    $_SESSION['login_message'] = '2FA session expired. Please log in again.';
    header('Location: accedi');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        $code = trim($_POST['twofa_code'] ?? '');
        $result = auth_verify_2fa_login($mysqli, $code);

        if (!empty($result['ok'])) {
            if (!empty($result['used_backup'])) {
                $_SESSION['login_message'] = 'Login completed with backup code. That code is no longer valid.';
            }

            header('Location: ' . ($result['redirect'] ?? 'home'));
            exit();
        }

        $error = $result['message'] ?? 'Invalid code.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - 2FA Verification</title>
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
            <div class="form-card__header">
                <span class="form-pill">2FA</span>
                <h1>2FA Verification</h1>
                <p>Enter the authentication app code or a backup code.</p>
            </div>

            <?php if ($error): ?>
                <div class="form-alert form-alert--error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo auth_h($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" data-form-loading>
                <?php echo csrf_field(); ?>

                <label class="form-field">
                    <span>2FA Code</span>
                    <input type="text" name="twofa_code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required autofocus>
                </label>

                <div class="form-actions">
                    <button class="form-btn form-btn--primary form-btn--wide" type="submit" data-loading-text="Verifying...">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Verify</span>
                    </button>
                </div>

                <div class="form-links">
                    <a href="accedi">Back to login</a>
                </div>
            </form>
        </section>
    </main>

    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>