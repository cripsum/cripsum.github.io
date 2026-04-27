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
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Accedi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.0-2fa">
    <script src="/assets/auth/auth.js?v=1.0-2fa" defer></script>
</head>

<body class="auth-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

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
                            <button type="button" data-toggle-password aria-label="Mostra password">
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
