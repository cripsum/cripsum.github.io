<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$message = '';
$messageType = '';

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);

    if (!empty($token)) {
        $user = verifyEmail($mysqli, $token);

        if ($user) {
            $message = "Email verificata con successo. Ora puoi accedere.";
            $messageType = 'success';

            sendWelcomeEmail($user['email'], $user['username']);
        } else {
            $message = "Token non valido o già usato.";
            $messageType = 'error';
        }
    } else {
        $message = "Token di verifica mancante.";
        $messageType = 'error';
    }
} else {
    $message = "Token di verifica mancante.";
    $messageType = 'error';
}

if ($_POST && isset($_POST['resend_email'])) {
    $email = trim($_POST['email'] ?? '');

    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (resendVerificationEmail($mysqli, $email)) {
            $message = "Email di verifica reinviata.";
            $messageType = 'success';
        } else {
            $message = "Email già verificata o invio non riuscito.";
            $messageType = 'error';
        }
    } else {
        $message = "Email non valida.";
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Verifica Email</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/forms/forms.css?v=1.0-unified">
    <script src="/assets/forms/forms.js?v=1.0-unified" defer></script>
</head>

<body class="form-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>


    <div class="form-bg" aria-hidden="true">
        <span class="form-orb form-orb--one"></span>
        <span class="form-orb form-orb--two"></span>
        <span class="form-grid-bg"></span>
    </div>


    <main class="form-shell form-shell--narrow">
        <section class="form-card form-reveal">
            <div class="confirm-icon">
                <i class="fas <?php echo $messageType === 'success' ? 'fa-check' : 'fa-triangle-exclamation'; ?>"></i>
            </div>

            <div class="form-card__header" style="text-align:center;">
                <span class="form-pill">Email</span>
                <h1>Verifica email</h1>
                <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="form-alert <?php echo $messageType === 'success' ? 'form-alert--success' : 'form-alert--error'; ?>">
                <i class="fas <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <?php if ($messageType === 'success'): ?>
                <div class="form-actions form-actions--center">
                    <a href="accedi" class="form-btn form-btn--primary">
                        <i class="fas fa-right-to-bracket"></i>
                        <span>Accedi ora</span>
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="" data-form-loading>
                    <label class="form-field">
                        <span>Reinvia verifica</span>
                        <input type="email" id="email" name="email" placeholder="email@esempio.com" required>
                        <small>Inserisci la tua email per ricevere un nuovo link.</small>
                    </label>

                    <div class="form-actions">
                        <button type="submit" name="resend_email" class="form-btn form-btn--primary form-btn--wide" data-loading-text="Invio...">
                            <i class="fas fa-paper-plane"></i>
                            <span>Reinvia email</span>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="form-links">
                <a href="registrati">Torna alla registrazione</a>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
