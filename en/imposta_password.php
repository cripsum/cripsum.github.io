<?php
// imposta_password.php
require_once '../config/session_init.php';
require_once '../config/database.php';

// Controlla se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    header('Location: accedi.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Verifica se l'utente ha già una password
$stmt = $mysqli->prepare("SELECT password FROM utenti WHERE id = ?");
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

    if (strlen($newPassword) < 8) {
        $error = "La password deve avere almeno 8 caratteri.";
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        $update = $mysqli->prepare("UPDATE utenti SET password = ? WHERE id = ?");
        $update->bind_param("si", $hashed, $user_id);

        if ($update->execute()) {
            unset($_SESSION['needs_password']);
            $success = "Password impostata con successo! Ora puoi accedere sia tramite Google che con la tua email.";
        } else {
            $error = "Errore durante l'aggiornamento.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Imposta password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.0-2fa">
    <script src="/assets/auth/auth.js?v=1.0-2fa" defer></script>
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
                    <label class="auth-field">
                        <span>Nuova Password</span>
                        <div class="auth-password">
                            <input type="password" name="password" required minlength="8" data-password-input>
                        </div>
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