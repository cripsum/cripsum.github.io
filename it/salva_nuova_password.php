<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

$token = $_POST['token'] ?? '';
$nuova_password = $_POST['nuova_password'] ?? '';
$messaggio = '';
$success = false;

$resetIdentifier = 'reset:' . substr(hash('sha256', (string)$token), 0, 32);
$passwordPolicyError = auth_password_policy_error((string)$nuova_password);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $messaggio = 'Metodo non consentito.';
} elseif (auth_rate_limited($mysqli, $resetIdentifier, 'password_reset_submit_failed', 5, 30)) {
    $messaggio = 'Troppi tentativi. Richiedi un nuovo link o riprova più tardi.';
} elseif (!preg_match('/^[a-f0-9]{64}$/', (string)$token)) {
    $messaggio = 'Token non valido o scaduto.';
    auth_record_rate_failure($mysqli, null, $resetIdentifier, 'password_reset_submit_failed');
} elseif ($passwordPolicyError !== null) {
    $messaggio = auth_password_policy_message($passwordPolicyError, 'it');
    auth_record_rate_failure($mysqli, null, $resetIdentifier, 'password_reset_submit_failed');
} else {
    $tokenHash = hash('sha256', (string)$token);
    $stmt = $mysqli->prepare("SELECT id, username, email FROM utenti WHERE reset_token = ? AND token_scadenza > NOW()");
    if (!$stmt) {
        $messaggio = 'Non è stato possibile verificare il link.';
    } else {
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
    }

    if (!empty($row)) {
        $id = (int)$row['id'];
        $contextPolicyError = auth_password_policy_error((string)$nuova_password, (string)($row['username'] ?? ''), (string)($row['email'] ?? ''));
        if ($contextPolicyError !== null) {
            $messaggio = auth_password_policy_message($contextPolicyError, 'it');
            auth_record_rate_failure($mysqli, $id, $resetIdentifier, 'password_reset_submit_failed');
        } else {
            $hash = auth_password_hash_secure((string)$nuova_password);
            $stmt = $mysqli->prepare("UPDATE utenti SET password = ?, reset_token = NULL, token_scadenza = NULL WHERE id = ? AND reset_token = ? AND token_scadenza > NOW()");

            if ($stmt) {
                $stmt->bind_param("sis", $hash, $id, $tokenHash);
                $updated = $stmt->execute() && $stmt->affected_rows === 1;
                $stmt->close();
            } else {
                $updated = false;
            }

            if ($updated) {
                auth_revoke_all_device_sessions($mysqli, $id);
                auth_record_login_attempt($mysqli, $id, $resetIdentifier, true, 'password_reset_submit_ok');
                $messaggio = "Password aggiornata con successo. Tutti i dispositivi sono stati scollegati.";
                $success = true;
            } else {
                $messaggio = "Non è stato possibile aggiornare la password.";
                auth_record_rate_failure($mysqli, $id, $resetIdentifier, 'password_reset_submit_failed');
            }
        }
    } elseif ($messaggio === '') {
        $messaggio = "Token non valido o scaduto.";
        auth_record_rate_failure($mysqli, null, $resetIdentifier, 'password_reset_submit_failed');
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Esito reset</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/forms/forms.css?v=1.0-unified">
    <script src="/assets/forms/forms.js?v=1.0-unified" defer></script>
</head>

<body class="form-page">
    <?php include '../includes/navbar-morta.php'; ?>


    <div class="form-bg" aria-hidden="true">
        <span class="form-orb form-orb--one"></span>
        <span class="form-orb form-orb--two"></span>
        <span class="form-grid-bg"></span>
    </div>


    <main class="form-shell form-shell--narrow">
        <section class="form-card form-reveal">
            <div class="confirm-icon">
                <i class="fa-solid <?php echo $success ? 'fa-check' : 'fa-triangle-exclamation'; ?>"></i>
            </div>

            <div class="form-card__header" style="text-align:center;">
                <h1>Reset password</h1>
                <p><?php echo htmlspecialchars($messaggio, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="form-actions form-actions--center">
                <a class="form-btn form-btn--primary" href="accedi">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Torna al login</span>
                </a>
            </div>
        </section>
    </main>
</body>
</html>
