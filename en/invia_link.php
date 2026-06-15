<?php
require_once '../config/database.php';

$messaggio = "If the email is registered, you will receive a link to reset your password.";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $token = bin2hex(random_bytes(32));
            $scadenza = date("Y-m-d H:i:s", strtotime('+1 hour'));

            $stmt = $mysqli->prepare("UPDATE utenti SET reset_token = ?, token_scadenza = ? WHERE email = ?");
            $stmt->bind_param("sss", $token, $scadenza, $email);
            $stmt->execute();

            $link = "https://cripsum.com/en/reset_password.php?token=$token";
            $subject = "Password Reset Request";
            $message = "Please click the link below to reset your password:\n$link\n\nThis link will expire in 1 hour.";
            $headers = "From: no-reply@cripsum.com";

            mail($email, $subject, $message, $headers);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <meta charset="UTF-8">
    <title>Cripsum™ - Reset your password</title>
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
                <i class="fa-solid fa-envelope"></i>
            </div>

            <div class="form-card__header" style="text-align:center;">
                <span class="form-pill">Reset</span>
                <h1>Check your email</h1>
                <p><?php echo htmlspecialchars($messaggio, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="form-alert form-alert--info">
                <i class="fa-solid fa-circle-info"></i>
                <span><?php echo htmlspecialchars($messaggio, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <div class="form-actions form-actions--center">
                <a class="form-btn form-btn--primary" href="accedi">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Back to login</span>
                </a>
            </div>
        </section>
    </main>
</body>

</html>