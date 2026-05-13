<?php
require_once '../config/session_init.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: accedi.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$stmt = $mysqli->prepare("SELECT password FROM utenti WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!empty($row['password'])) {

    header('Location: impostazioni.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = "The password must be at least 8 characters long.";
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        $update = $mysqli->prepare("UPDATE utenti SET password = ? WHERE id = ?");
        $update->bind_param("si", $hashed, $user_id);

        if ($update->execute()) {
            unset($_SESSION['needs_password']);
            $success = "Password successfully set! You can now log in with both Google and your email.";
        } else {
            $error = "Error updating password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Set Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.0-2fa">
    <script src="/assets/auth/auth.js?v=1.0-2fa" defer></script>
</head>

<body class="auth-page">
    <?php include '../includes/navbar.php'; ?>
    <main class="auth-shell">
        <section class="auth-card">
            <div class="auth-card__form">
                <h1>Set Password</h1>
                <p>You logged in with Google. Set a password to also log in with your email.</p>

                <?php if ($error): ?>
                    <div style="color: var(--auth-danger);"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div style="color: var(--auth-success);"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <label class="auth-field">
                        <span>New Password</span>
                        <div class="auth-password">
                            <input type="password" name="password" required minlength="8" data-password-input>
                        </div>
                    </label>

                    <button class="auth-btn auth-btn--primary" type="submit">
                        <span>Save Password</span>
                    </button>
                </form>
            </div>
        </section>
    </main>
</body>

</html>