<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if(!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per poter mandare la tua candidatura devi avere un account Cripsum™";

    header('Location: accedi');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$userRole = $_SESSION['ruolo'] ?? 'utente';
$profilePic = "/includes/get_pfp.php?id=$userId";

$username_chisiamo = $_POST['username'] ?? '';
$email_chisiamo = $_POST['email'] ?? '';
$descrizione_chisiamo = $_POST['descrizione'] ?? '';
$pfp_chisiamo = $_POST['pfp_chisiamo'] ?? '';
$username_social = $_POST['social_username'] ?? '';
$link_social = $_POST['social_link'] ?? '';


$to = 'cripsum@cripsum.com';
$subject = 'Nuova Candidatura - ' . $username_chisiamo;
$message = "Nuova candidatura ricevuta:\n\n";
$message .= "Username: " . $username_chisiamo . "\n";
$message .= "Email: " . $email_chisiamo . "\n";
$message .= "Descrizione: " . $descrizione_chisiamo . "\n";
$message .= "Profilo foto: " . $pfp_chisiamo . "\n";
$message .= "Username social: " . $username_social . "\n";
$message .= "Link social: " . $link_social . "\n";
$message .= "\nInviata da utente ID: " . $user_id . " (" . $username . ")";

$headers = "From: noreply@cripsum.com\r\n";
$headers .= "Reply-To: " . $email_chisiamo . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

if(mail($to, $subject, $message, $headers)) {
    $_SESSION['result_candidatura'] = "Candidatura inviata con successo!";
} else {
    $_SESSION['result_candidatura'] = "Errore nell'invio della candidatura.";
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();

?>