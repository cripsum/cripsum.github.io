<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
$user_id = $_SESSION['user_id'] ?? null;

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
        $message = "Clicca il link per reimpostare la tua password:\n$link\n\nScade tra 1 ora.";
        $headers = "From: no-reply@cripsum.com";

        mail($email, $subject, $message, $headers);
    }

    echo "Se l'email è registrata, riceverai un link.";
}
?>
