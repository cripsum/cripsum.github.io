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
        $error = 'Session expired. Please try again.';
    } elseif (auth_rate_limited($mysqli, $email ?: $username, 'register_failed', 5, 30)) {
        $error = 'Too many attempts. Please try again in a few minutes.';
    } elseif ($username === '' || $email === '' || $password === '' || $repeatPassword === '') {
        $error = 'Please fill in all fields.';
    } elseif (!$acceptTerms) {
        $error = 'You must accept the terms.';
    } elseif (!auth_is_valid_username($username)) {
        $error = 'Invalid username. Use 3-20 characters, letters, numbers, and underscores.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($password !== $repeatPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!auth_recaptcha_verify()) {
        $error = 'reCAPTCHA verification failed.';
    } else {
        $result = registerUser($mysqli, $username, $email, $password);

        if ($result === true) {
            $success = 'Account created. Check your email to verify your account.';
        } else {
            $error = is_string($result) ? $result : 'Registration failed.';
            auth_record_login_attempt($mysqli, null, $email ?: $username, false, 'register_failed');
            auth_session_rate_fail($email ?: $username, 'register_failed');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Sign up</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.2">
    <script src="/assets/auth/auth.js?v=1.2" defer></script>
</head>

<body class="auth-page">
    <?php include '../includes/navbar.php'; ?>


    <main class="auth-shell">
        <section class="auth-card auth-reveal">
            <div class="auth-card__side">
                <span class="auth-pill">Cripsum™</span>
                <h1>Create account</h1>
                <p>Join the Cripsum™ community and get access to tons of content.</p>
            </div>

            <div class="auth-card__form">
                <?php if ($error): ?>
                    <div class="auth-alert auth-alert--error">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span><?php echo auth_h($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="auth-alert auth-alert--success">
                        <i class="fa-solid fa-circle-check"></i>
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
                            <button type="button" data-toggle-password aria-label="Show password" style="margin-top: -18px;">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <label class="auth-field">
                        <span>Repeat password</span>
                        <div class="auth-password">
                            <input type="password" name="repeatPassword" autocomplete="new-password" required minlength="8" data-password-input>
                            <button type="button" data-toggle-password aria-label="Show password" style="margin-top: -18px;">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <label class="auth-check">
                        <input type="checkbox" name="acceptTerms" required <?php echo isset($_POST['acceptTerms']) ? 'checked' : ''; ?>>
                        <span>I accept the terms and privacy policy.</span>
                    </label>

                    <div class="auth-recaptcha">
                        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8'); ?>"></div>
                    </div>

                    <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Registrati">
                        <span>Sign up</span>
                    </button>
                    <div style="text-align: center; margin: 15px 0; color: var(--auth-muted);">or</div>
                    <a href="google_login" class="auth-btn" style="background-color: white; color: black; text-decoration: none; text-align: center; display: flex; justify-content: center; align-items: center; gap: 10px;">
                        <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google" width="20" height="20">
                        <span>Sign in with Google</span>
                    </a>

                    <div class="auth-links">
                        <a href="accedi">Already have an account?</a>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>