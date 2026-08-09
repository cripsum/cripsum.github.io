<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (function_exists('isLoggedIn') && isLoggedIn()) {
    header('Location: home');
    exit();
}

if (empty($_SESSION['pending_google_confirm'])) {
    header('Location: accedi');
    exit();
}

$pending = $_SESSION['pending_google_confirm'];
$userId = (int)($pending['user_id'] ?? 0);
$email = (string)($pending['email'] ?? '');

if (empty($userId) || (!empty($pending['started_at']) && time() - (int)$pending['started_at'] > 600)) {
    unset($_SESSION['pending_google_confirm']);
    $_SESSION['login_message'] = 'Authentication session expired. Please sign in again.';
    header('Location: accedi');
    exit();
}

$user = auth_get_user_by_id($mysqli, $userId);

if (!$user) {
    unset($_SESSION['pending_google_confirm']);
    header('Location: accedi');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';

        if (auth_rate_limited($mysqli, $email, 'google_password_confirm_failed', 6, 10)) {
            $error = 'Too many failed attempts. Please try again in a few minutes.';
        } elseif (!auth_verify_user_password($mysqli, $userId, $password)) {
            auth_record_login_attempt($mysqli, $userId, $email, false, 'google_password_confirm_failed');
            $error = 'Incorrect password. Please try again.';
        } else {
            $googleId = (string)($pending['google_id'] ?? '');
            if ($googleId !== '' && empty($user['google_id'])) {
                $update = $mysqli->prepare("UPDATE utenti SET google_id = ? WHERE id = ?");
                if ($update) {
                    $update->bind_param("si", $googleId, $userId);
                    $update->execute();
                    $update->close();
                }
            }

            $redirectTarget = $pending['redirect'] ?? 'home';

            if ((int)($user['twofa_enabled'] ?? 0) === 1 && !empty($user['twofa_secret'])) {
                $_SESSION['pending_2fa_user_id'] = $userId;
                $_SESSION['pending_2fa_started_at'] = time();
                $_SESSION['pending_2fa_identifier'] = $email;
                $_SESSION['pending_2fa_redirect'] = $redirectTarget;

                unset($_SESSION['pending_google_confirm']);
                auth_record_login_attempt($mysqli, $userId, $email, true, 'google_password_ok_2fa_pending');

                header('Location: verifica-2fa');
                exit();
            }

            auth_complete_login($user, $mysqli);
            auth_record_login_attempt($mysqli, $userId, $email, true, 'google_password_confirmed_ok');
            unset($_SESSION['pending_google_confirm']);

            header('Location: ' . $redirectTarget);
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Confirm Google Password</title>
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
                <h1>Confirm Sign In</h1>
                <p>This account has a password configured. Please enter your password to authorize Google Sign-In.</p>
            </div>

            <?php if ($error): ?>
                <div class="form-alert form-alert--error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo auth_h($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" data-form-loading>
                <?php echo csrf_field(); ?>

                <div class="auth-field" style="margin-bottom: 1.25rem;">
                    <span style="display: block; font-size: 0.85rem; opacity: 0.8; margin-bottom: 0.25rem;">Account</span>
                    <strong style="font-size: 1rem; color: var(--accent, #38bdf8);"><?php echo auth_h($user['username'] ?? $email); ?></strong>
                    <div style="font-size: 0.85rem; opacity: 0.7;"><?php echo auth_h($email); ?></div>
                </div>

                <label class="form-field">
                    <span>Account Password</span>
                    <div class="auth-password" style="position: relative;">
                        <input type="password" name="password" autocomplete="current-password" required autofocus data-password-input placeholder="Enter your password">
                    </div>
                </label>

                <div class="form-actions" style="margin-top: 1.5rem;">
                    <button class="form-btn form-btn--primary form-btn--wide" type="submit" data-loading-text="Verifying...">
                        <i class="fa-solid fa-lock"></i>
                        <span>Confirm & Sign In</span>
                    </button>
                </div>

                <div class="form-links" style="margin-top: 1rem; text-align: center;">
                    <a href="accedi">Cancel and back to login</a>
                </div>
            </form>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
