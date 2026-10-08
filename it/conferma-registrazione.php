<?php
require_once '../config/session_init.php';
require_once '../includes/theme.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

/*
 * Ultimo passaggio della registrazione con Google.
 *
 * google_callback lascia in sessione email, ID e nome Google e manda qui chi
 * non ha ancora un account: l'account nasce solo dopo la stessa spunta della
 * registrazione normale (almeno 14 anni, Termini e Informativa privacy).
 */

const GOOGLE_SIGNUP_TTL = 900;

$pending = $_SESSION['pending_google_signup'] ?? null;

if (function_exists('isLoggedIn') && isLoggedIn()) {
    unset($_SESSION['pending_google_signup']);
    header('Location: home');
    exit();
}

if (!is_array($pending)
    || empty($pending['email'])
    || empty($pending['google_id'])
    || (time() - (int)($pending['started_at'] ?? 0)) > GOOGLE_SIGNUP_TTL
) {
    unset($_SESSION['pending_google_signup']);
    $_SESSION['login_message'] = 'La registrazione con Google è scaduta. Riprova.';
    header('Location: accedi');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'cancel') {
        unset($_SESSION['pending_google_signup']);
        header('Location: accedi');
        exit();
    }

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Sessione scaduta. Riprova.';
    } elseif (!isset($_POST['acceptTerms'])) {
        $error = 'Devi avere almeno 14 anni e accettare i Termini di servizio.';
    } else {
        $email = (string)$pending['email'];

        // Nel frattempo l'email potrebbe essersi registrata in un altro modo.
        $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();

        if ($exists) {
            unset($_SESSION['pending_google_signup']);
            $_SESSION['login_message'] = 'Esiste già un account con questa email: accedi.';
            header('Location: accedi');
            exit();
        }

        $user = auth_google_create_account($mysqli, $email, (string)$pending['google_id'], (string)($pending['name'] ?? ''));

        if ($user === null) {
            $error = 'Registrazione non riuscita. Riprova.';
        } else {
            unset($_SESSION['pending_google_signup']);
            auth_complete_login($user, $mysqli);
            auth_record_login_attempt($mysqli, (int)$user['id'], $email, true, 'google_register_ok');

            if (function_exists('notifyDiscordSiteLogs')) {
                notifyDiscordSiteLogs('register', 'Nuova Registrazione Utente', "Un nuovo utente **{$user['username']}** si è registrato sul sito!", [
                    ['name' => 'Email', 'value' => $email, 'inline' => true],
                    ['name' => 'Metodo', 'value' => 'Google', 'inline' => true]
                ], (int)$user['id']);
            }

            $redirect = $_SESSION['redirect_after_login'] ?? 'home';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php include '../includes/head-import.php'; ?>
    <?php cripsum_theme_head('auth'); ?>
    <title>Cripsum™ - Conferma registrazione</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.7">
    <script src="/assets/auth/auth.js?v=1.2" defer></script>
</head>

<body class="auth-page">
    <?php include '../includes/navbar.php'; ?>

    <main class="auth-shell">
        <section class="auth-card auth-reveal">
            <div class="auth-card__side">
                <h1>Ultimo passaggio</h1>
                <p>Stai creando un account Cripsum™ con l'account Google <strong><?php echo auth_h($pending['email']); ?></strong>.</p>
            </div>

            <div class="auth-card__form">
                <?php if ($error): ?>
                    <div class="auth-alert auth-alert--error">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span><?php echo auth_h($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="auth-form" data-auth-form>
                    <?php echo csrf_field(); ?>

                    <label class="auth-check auth-check--legal">
                        <input type="checkbox" name="acceptTerms" required>
                        <span>Ho almeno 14 anni e accetto i <a href="tos" target="_blank" rel="noopener">Termini di servizio</a>. Ho letto l'<a href="privacy" target="_blank" rel="noopener">Informativa privacy</a>.</span>
                    </label>

                    <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Creazione account">
                        <span>Crea account</span>
                    </button>
                </form>

                <form method="POST" class="auth-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="cancel">
                    <button class="auth-btn" type="submit">
                        <span>Annulla</span>
                    </button>
                </form>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
