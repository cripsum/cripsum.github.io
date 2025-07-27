<?php
require_once 'config/database.php';

$messaggio = "Se l'email è registrata, riceverai un link per reimpostare la password.";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

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

        $link = "https://cripsum.com/it/reset_password.php?token=$token";
        $subject = "Reimposta la tua password";
        $message = "Clicca il link per reimpostare la tua password:\n$link\n\nIl link scade tra 1 ora.";
        $headers = "From: no-reply@cripsum.com";

        mail($email, $subject, $message, $headers);
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <meta charset="UTF-8">
    <title>Cripsum™ - Link inviato</title>
    <style>
        body {
            background: #0e0e0e;
            color: white;
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .box {
            background: #1a1a1a;
            padding: 30px;
            border: 1px solid #ffffff22;
            border-radius: 12px;
            box-shadow: 0 0 10px #ffffff11;
            text-align: center;
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar-morta.php'; ?>
    <div class="alert alert-info fadeup" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i>
        <?php echo htmlspecialchars($messaggio); ?>
    </div>
</body>
</html>
