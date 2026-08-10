<?php
// imposta_password.php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Controlla se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    header('Location: accedi.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Verifica se l'utente ha già una password
$stmt = $mysqli->prepare("SELECT password, username, email FROM utenti WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!empty($row['password'])) {
    // Ha già una password
    header('Location: impostazioni.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'] ?? '';
    $securityIdentifier = 'user:' . (int)$user_id;
    $passwordPolicyError = auth_password_policy_error($newPassword, (string)($row['username'] ?? ''), (string)($row['email'] ?? ''));

    if (auth_rate_limited($mysqli, $securityIdentifier, 'password_set_failed', 5, 30)) {
        $error = "Troppi tentativi. Riprova più tardi.";
    } elseif (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = "Sessione scaduta. Ricarica la pagina.";
    } elseif ($passwordPolicyError !== null) {
        $error = auth_password_policy_message($passwordPolicyError, 'it');
        auth_record_rate_failure($mysqli, (int)$user_id, $securityIdentifier, 'password_set_failed');
    } else {
        $hashed = auth_password_hash_secure($newPassword);

        $update = $mysqli->prepare("UPDATE utenti SET password = ? WHERE id = ?");
        $update->bind_param("si", $hashed, $user_id);

        if ($update->execute()) {
            auth_record_login_attempt($mysqli, (int)$user_id, $securityIdentifier, true, 'password_set_ok');
            auth_revoke_other_device_sessions($mysqli, (int)$user_id);
            unset($_SESSION['needs_password']);
            $success = "Password impostata con successo! Gli altri dispositivi sono stati scollegati. Ora puoi accedere sia tramite Google che con la tua email.";
        } else {
            $error = "Errore durante l'aggiornamento.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Imposta password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.2">
    <script src="/assets/auth/auth.js?v=1.2" defer></script>
</head>

<body class="auth-page">
    <?php include '../includes/navbar.php'; ?>
    <main class="auth-shell">
        <section class="auth-card">
            <div class="auth-card__form">
                <h1>Imposta Password</h1>
                <p>Hai effettuato l'accesso con Google. Imposta una password per poter accedere anche con la tua email.</p>

                <?php if ($error): ?>
                    <div style="color: var(--auth-danger);"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div style="color: var(--auth-success);"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <?php echo csrf_field(); ?>
                    <label class="auth-field">
                        <span>Nuova Password</span>
                        <div class="auth-password">
                            <input type="password" name="password" autocomplete="new-password" required minlength="8" maxlength="128" data-password-input>
                        </div>
                        <small><?php echo auth_h(auth_password_policy_hint('it')); ?></small>
                    </label>

                    <button class="auth-btn auth-btn--primary" type="submit">
                        <span>Salva Password</span>
                    </button>
                </form>
            </div>
        </section>
    </main>
</body>

</html>
