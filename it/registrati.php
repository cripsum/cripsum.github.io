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
            $success = 'Account creato. Controlla la tua email per verificare l’account.';
        } else {
            $error = is_string($result) ? $result : 'Registrazione non riuscita.';
            auth_record_login_attempt($mysqli, null, $email ?: $username, false, 'register_failed');
            auth_session_rate_fail($email ?: $username, 'register_failed');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Registrati</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
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
                <h1>Crea account</h1>
                <p>Ti serve per profilo, chat, lootbox e achievement.</p>
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
                            <button type="button" data-toggle-password aria-label="Mostra password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <label class="auth-field">
                        <span>Ripeti password</span>
                        <div class="auth-password">
                            <input type="password" name="repeatPassword" autocomplete="new-password" required minlength="8" data-password-input>
                            <button type="button" data-toggle-password aria-label="Mostra password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <label class="auth-check">
                        <input type="checkbox" name="acceptTerms" required <?php echo isset($_POST['acceptTerms']) ? 'checked' : ''; ?>>
                        <span>Accetto termini e privacy.</span>
                    </label>

                    <div class="auth-recaptcha">
                        <div class="g-recaptcha" data-sitekey="6Lcy-7srAAAAAIcwYWnXRPzFZ5oVIfcfNW1H_zLs"></div>
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
