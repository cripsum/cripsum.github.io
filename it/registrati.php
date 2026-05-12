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

function auth_recaptcha_verify(): bool
{
    $secret = defined('RECAPTCHA_SECRET') ? RECAPTCHA_SECRET : (getenv('RECAPTCHA_SECRET') ?: '');
    $response = $_POST['g-recaptcha-response'] ?? '';

    if ($secret === '') {
        return false;
    }

    if ($response === '') {
        return false;
    }

    $verify = curl_init();
    curl_setopt($verify, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
    curl_setopt($verify, CURLOPT_POST, true);
    curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $secret,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]));
    curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($verify, CURLOPT_TIMEOUT, 8);

    $body = curl_exec($verify);
    curl_close($verify);

    if (!$body) {
        return false;
    }

    $captcha = json_decode($body, true);
    return is_array($captcha) && !empty($captcha['success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $repeatPassword = $_POST['repeatPassword'] ?? '';
    $acceptTerms = isset($_POST['acceptTerms']);

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Sessione scaduta. Riprova.';
    } elseif (auth_rate_limited($mysqli, $email ?: $username, 'register_failed', 5, 30)) {
        $error = 'Troppi tentativi. Riprova tra qualche minuto.';
    } elseif ($username === '' || $email === '' || $password === '' || $repeatPassword === '') {
        $error = 'Compila tutti i campi.';
    } elseif (!$acceptTerms) {
        $error = 'Devi accettare i termini.';
    } elseif (!auth_is_valid_username($username)) {
        $error = 'Username non valido. Usa 3-20 caratteri, lettere, numeri e underscore.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email non valida.';
    } elseif ($password !== $repeatPassword) {
        $error = 'Le password non coincidono.';
    } elseif (strlen($password) < 8) {
        $error = 'La password deve avere almeno 8 caratteri.';
    } elseif (!auth_recaptcha_verify()) {
        $error = 'Verifica reCAPTCHA non riuscita.';
    } else {
        $result = registerUser($mysqli, $username, $email, $password);

        if ($result === true) {
            $success = 'Account creato. Controlla la tua email per verificare l\'account.';
        } else {
            $error = is_string($result) ? $result : 'Registrazione non riuscita.';
            auth_record_login_attempt($mysqli, null, $email ?: $username, false, 'register_failed');
            auth_session_rate_fail($email ?: $username, 'register_failed');
        }
    }
}

$googleAuthUrl = google_auth_url();
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Registrati</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.1-google">
    <script src="/assets/auth/auth.js?v=1.1-google" defer></script>
</head>

<body class="auth-page">
    <?php include '../includes/navbar.php'; ?>

    <main class="auth-shell">
        <section class="auth-card auth-reveal">
            <div class="auth-card__side">
                <span class="auth-pill">Cripsum™</span>
                <h1>Crea account</h1>
                <p>Unisciti alla community di Cripsum™ e accedi a tantissimi contenuti.</p>
            </div>

            <div class="auth-card__form">
                <?php if ($error): ?>
                    <div class="auth-alert auth-alert--error">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span><?php echo auth_h($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="auth-alert auth-alert--success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo auth_h($success); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Pulsante Google -->
                <a href="<?php echo htmlspecialchars($googleAuthUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    class="auth-btn auth-btn--google">
                    <svg class="auth-google-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" />
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                    </svg>
                    <span>Registrati con Google</span>
                </a>

                <div class="auth-divider">
                    <span>oppure crea un account con email</span>
                </div>

                <form method="POST" class="auth-form" data-auth-form>
                    <?php echo csrf_field(); ?>

                    <label class="auth-field">
                        <span>Username</span>
                        <input type="text" name="username" autocomplete="username" required maxlength="20" value="<?php echo auth_h($_POST['username'] ?? ''); ?>">
                    </label>

                    <label class="auth-field">
                        <span>Email</span>
                        <input type="email" name="email" autocomplete="email" required value="<?php echo auth_h($_POST['email'] ?? ''); ?>">
                    </label>

                    <label class="auth-field">
                        <span>Password</span>
                        <div class="auth-password">
                            <input type="password" name="password" autocomplete="new-password" required minlength="8" data-password-input>
                            <button type="button" data-toggle-password aria-label="Mostra password" style="margin-top: -18px;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <label class="auth-field">
                        <span>Ripeti password</span>
                        <div class="auth-password">
                            <input type="password" name="repeatPassword" autocomplete="new-password" required minlength="8" data-password-input>
                            <button type="button" data-toggle-password aria-label="Mostra password" style="margin-top: -18px;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <label class="auth-check">
                        <input type="checkbox" name="acceptTerms" required <?php echo isset($_POST['acceptTerms']) ? 'checked' : ''; ?>>
                        <span>Accetto termini e privacy.</span>
                    </label>

                    <div class="auth-recaptcha">
                        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8'); ?>"></div>
                    </div>

                    <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Registrati">
                        <span>Registrati</span>
                    </button>

                    <div class="auth-links">
                        <a href="accedi">Hai già un account?</a>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>