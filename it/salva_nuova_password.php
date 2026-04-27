<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

$token = $_POST['token'] ?? '';
$nuova_password = $_POST['nuova_password'] ?? '';
$messaggio = '';
$success = false;

if ($token && $nuova_password) {
    $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE reset_token = ? AND token_scadenza > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $hash = password_hash($nuova_password, PASSWORD_DEFAULT);

        $stmt = $mysqli->prepare("UPDATE utenti SET password = ?, reset_token = NULL, token_scadenza = NULL WHERE id = ?");
        $stmt->bind_param("si", $hash, $id);
        $stmt->execute();

        $messaggio = "Password aggiornata con successo.";
        $success = true;
    } else {
        $messaggio = "Token non valido o scaduto.";
    }
} else {
    $messaggio = "Richiesta non valida.";
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
                <i class="fas <?php echo $success ? 'fa-check' : 'fa-triangle-exclamation'; ?>"></i>
            </div>

            <div class="form-card__header" style="text-align:center;">
                <span class="form-pill"><?php echo $success ? 'Fatto' : 'Attenzione'; ?></span>
                <h1>Reset password</h1>
                <p><?php echo htmlspecialchars($messaggio, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="form-actions form-actions--center">
                <a class="form-btn form-btn--primary" href="accedi">
                    <i class="fas fa-arrow-left"></i>
                    <span>Torna al login</span>
                </a>
            </div>
        </section>
    </main>
</body>
</html>
