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
    $_SESSION['login_message'] = 'Sessione 2FA scaduta. Accedi di nuovo.';
    header('Location: accedi');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Sessione scaduta. Riprova.';
    } else {
        $code = trim($_POST['twofa_code'] ?? '');
        $result = auth_verify_2fa_login($mysqli, $code);

        if (!empty($result['ok'])) {
            if (!empty($result['used_backup'])) {
                $_SESSION['login_message'] = 'Accesso completato con codice backup. Ricorda che quel codice ora non vale più.';
            }

            header('Location: ' . ($result['redirect'] ?? 'home'));
            exit();
        }

        $error = $result['message'] ?? 'Codice non valido.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Verifica 2FA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.0-2fa">
    <script src="/assets/auth/auth.js?v=1.0-2fa" defer></script>
</head>

<body class="auth-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <main class="auth-shell auth-shell--narrow">
        <section class="auth-card auth-card--single auth-reveal">
            <div class="auth-card__form">
                <span class="auth-pill">2FA</span>
                <h1>Verifica accesso</h1>
                <p class="auth-muted">Inserisci il codice dell’app autenticatore o un backup code.</p>

                <?php if ($error): ?>
                    <div class="auth-alert auth-alert--error">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span><?php echo auth_h($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="auth-form" data-auth-form>
                    <?php echo csrf_field(); ?>

                    <label class="auth-field">
                        <span>Codice 2FA</span>
                        <input class="auth-code-input" type="text" name="twofa_code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required autofocus>
                    </label>

                    <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Verifica">
                        <span>Verifica</span>
                    </button>

                    <div class="auth-links">
                        <a href="accedi">Torna al login</a>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
