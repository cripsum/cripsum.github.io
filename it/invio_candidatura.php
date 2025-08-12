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
$email = $_SESSION['email'] ?? '';
$username = $_SESSION['username'];
$userRole = $_SESSION['ruolo'] ?? 'utente';
$profilePic = "/includes/get_pfp.php?id=$user_id";

$username_chisiamo = $_POST['username'] ?? '';
$email_chisiamo = $_POST['email'] ?? '';
$descrizione_chisiamo = $_POST['descrizione'] ?? '';
$pfp_chisiamo = '';
$pfp_uploaded = false;
$username_social = $_POST['social_username'] ?? '';
$link_social = $_POST['social_link'] ?? '';


$to = 'dio.covid@gmail.com';
$subject = 'Nuova Candidatura - ' . $username_chisiamo;

if (isset($_FILES['pfp_chisiamo']) && $_FILES['pfp_chisiamo']['error'] === UPLOAD_ERR_OK && $_FILES['pfp_chisiamo']['size'] > 0) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($_FILES['pfp_chisiamo']['tmp_name']);
    
    if (in_array($mime_type, $allowed_types)) {
        $pfp_chisiamo = file_get_contents($_FILES['pfp_chisiamo']['tmp_name']);
        $pfp_chisiamo = base64_encode($pfp_chisiamo);
        $pfp_uploaded = true;
    }
}

$message = "Nuova candidatura ricevuta:\n\n";
$message .= "Username: " . $username_chisiamo . "\n";
$message .= "Email: " . $email_chisiamo . "\n";
$message .= "Descrizione: " . $descrizione_chisiamo . "\n";

if ($pfp_uploaded) {
    $message .= "Profilo foto: Immagine allegata\n";
} else {
    $message .= "Profilo foto: Nessuna immagine caricata\n";
}

$message .= "Username social: " . $username_social . "\n";
$message .= "Link social: " . $link_social . "\n";
$message .= "\nInviata da utente ID: " . $user_id . " (" . $username . " - " . $email  . ")";

if(mail($to, $subject, $message, $headers)) {
    $_SESSION['result_candidatura'] = "Candidatura inviata con successo!";
} else {
    $_SESSION['result_candidatura'] = "Errore nell'invio della candidatura.";
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();

?>