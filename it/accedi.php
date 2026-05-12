<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/google_auth.php';   // ← aggiunto

if (function_exists('isLoggedIn') && isLoggedIn()) {
    header('Location: home');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Sessione scaduta. Riprova.';
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

        $error = $result['message'] ?? 'Accesso non riuscito.';
    }
}

// Messaggio di errore proveniente dal callback Google
if (isset($_SESSION['login_message_error'])) {
    $error = $_SESSION['login_message_error'];
    unset($_SESSION['login_message_error']);
}

$googleAuthUrl = google_auth_url();
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Accedi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.1-google">
    <script src="/assets/auth/auth.js?v=1.1-google" defer></script>
</head>

<body class="auth-page">
    <?php include '../includes/navbar.php'; ?>

    <main class="auth-shell">
        <section class="auth-card auth-reveal">
            <div class="auth-card__side">
                <span class="auth-pill">Cripsum™</span>
                <h1>Accedi</h1>
                <p>Entra nel tuo account e torna alle tue robe.</p>
            </div>

            <div class="auth-card__form">
                <?php if ($error): ?>
                    <div class="auth-alert auth-alert--error">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span><?php echo auth_h($error); ?></span>

                        <?php if (stripos($error, 'verificare') !== false): ?>
                            <a href="verifica-email">Reinvia email</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['login_message'])): ?>
                    <div class="auth-alert auth-alert--info">
                        <i class="fas fa-circle-info"></i>
                        <span><?php echo auth_h($_SESSION['login_message']); ?></span>
                    </div>
                    <?php unset($_SESSION['login_message']); ?>
                <?php endif; ?>

                <!-- Pulsante Google -->
                <a href="<?php echo htmlspecialchars($googleAuthUrl, ENT_QUOTES, 'UTF-8'); ?>"
                   class="auth-btn auth-btn--google">
                    <svg class="auth-google-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    <span>Accedi con Google</span>
                </a>

                <div class="auth-divider">
                    <span>oppure</span>
                </div>

                <form method="POST" class="auth-form" data-auth-form>
                    <?php echo csrf_field(); ?>

                    <label class="auth-field">
                        <span>Email o username</span>
                        <input type="text" name="email" autocomplete="username" required value="<?php echo auth_h($_POST['email'] ?? ''); ?>">
                    </label>

                    <label class="auth-field">
                        <span>Password</span>
                        <div class="auth-password">
                            <input type="password" name="password" autocomplete="current-password" required data-password-input>
                            <button type="button" data-toggle-password aria-label="Mostra password" style="margin-top: -18px;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Accedi">
                        <span>Accedi</span>
                    </button>

                    <div class="auth-links">
                        <a href="password-dimenticata">Password dimenticata?</a>
                        <a href="registrati">Crea account</a>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
