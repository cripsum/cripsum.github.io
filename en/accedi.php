<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (function_exists('isLoggedIn') && isLoggedIn()) {
    header('Location: home');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        $emailOrUsername = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = auth_start_password_login($mysqli, $emailOrUsername, $password);

        if (!empty($result['ok'])) {
            $redirect = $_SESSION['redirect_after_login'] ?? 'home';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit();
        }

        if (!empty($result['twofa_required'])) {
            header('Location: verifica-2fa');
            exit();
        }

        $error = $result['message'] ?? 'Login failed.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.2">
    <script src="/assets/auth/auth.js?v=1.2" defer></script>
</head>

<body class="auth-page">
    <?php include '../includes/navbar.php'; ?>


    <main class="auth-shell">
        <section class="auth-card auth-reveal">
            <div class="auth-card__side">
                <span class="auth-pill">Cripsum™</span>
                <h1>Login</h1>
                <p>Login to your Cripsum™ account</p>
            </div>

            <div class="auth-card__form">
                <?php if ($error): ?>
                    <div class="auth-alert auth-alert--error">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span><?php echo auth_h($error); ?></span>

                        <?php if (stripos($error, 'verificare') !== false): ?>
                            <a href="verifica-email">Resend email</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['login_message'])): ?>
                    <div class="auth-alert auth-alert--info">
                        <i class="fa-solid fa-circle-info"></i>
                        <span><?php echo auth_h($_SESSION['login_message']); ?></span>
                    </div>
                    <?php unset($_SESSION['login_message']); ?>
                <?php endif; ?>

                <form method="POST" class="auth-form" data-auth-form>
                    <?php echo csrf_field(); ?>

                    <label class="auth-field">
                        <span>Email or username</span>
                        <input type="text" name="email" autocomplete="username" required value="<?php echo auth_h($_POST['email'] ?? ''); ?>">
                    </label>

                    <label class="auth-field">
                        <span>Password</span>
                        <div class="auth-password">
                            <input type="password" name="password" autocomplete="current-password" required data-password-input>
                            <button type="button" data-toggle-password aria-label="Show password" style="margin-top: -18px;">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Accedi">
                        <span>Login</span>
                    </button>
                    <div style="text-align: center; margin: 15px 0; color: var(--auth-muted);">or</div>
                    <a href="google_login" class="auth-btn" style="background-color: white; color: black; text-decoration: none; text-align: center; display: flex; justify-content: center; align-items: center; gap: 10px;">
                        <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google" width="20" height="20">
                        <span>Login with Google</span>
                    </a>



                    <div class="auth-links">
                        <a href="password-dimenticata">Forgot password?</a>
                        <a href="registrati">Create account</a>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>