<?php
require_once '../config/session_init.php';
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

$username_chisiamo = $_POST['username'] ?? '';
$email_chisiamo = $_POST['email'] ?? '';
$descrizione_chisiamo = $_POST['descrizione'] ?? '';
$username_social = $_POST['social_username'] ?? '';
$link_social = $_POST['social_link'] ?? '';

$pfp_chisiamo = '';
$mime_type = '';
$pfp_uploaded = false;

if (isset($_FILES['pfp_chisiamo']) && $_FILES['pfp_chisiamo']['error'] === UPLOAD_ERR_OK && $_FILES['pfp_chisiamo']['size'] > 0) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($_FILES['pfp_chisiamo']['tmp_name']);
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    if (in_array($mime_type, $allowed_types)) {
        $pfp_chisiamo = file_get_contents($_FILES['pfp_chisiamo']['tmp_name']);
        $pfp_chisiamo = base64_encode($pfp_chisiamo);
        $pfp_uploaded = true;
    }
}

$to = 'dio.covid@gmail.com';
$subject = 'Nuova Candidatura - ' . $username_chisiamo;
$boundary = md5(time());

$message = "Nuova candidatura ricevuta:\n\n";
$message .= "Username: " . $username_chisiamo . "\n";
$message .= "Email: " . $email_chisiamo . "\n";
$message .= "Descrizione: " . $descrizione_chisiamo . "\n";

if ($pfp_uploaded) {
    $message .= "Foto profilo: Immagine allegata\n";
} else {
    $message .= "Foto profilo: Nessuna immagine caricata\n";
}

$message .= "Username social: " . $username_social . "\n";
$message .= "Link social: " . $link_social . "\n";
$message .= "\nInviata da utente ID: " . $user_id . " (" . $username . " - " . $email  . ")";

$headers = "From: noreply@cripsum.com\r\n";
$headers .= "Reply-To: " . $email_chisiamo . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

$email_message = "--{$boundary}\r\n";
$email_message .= "Content-Type: text/plain; charset=UTF-8\r\n";
$email_message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$email_message .= $message . "\r\n\r\n";

if ($pfp_uploaded) {
    $filename = 'profile_picture';
    switch ($mime_type) {
        case 'image/jpeg':
            $filename .= '.jpg';
            break;
        case 'image/png':
            $filename .= '.png';
            break;
        case 'image/webp':
            $filename .= '.webp';
            break;
        default:
            $filename .= '.jpg';
            break;
    }
    
    $email_message .= "--{$boundary}\r\n";
    $email_message .= "Content-Type: {$mime_type}; name=\"{$filename}\"\r\n";
    $email_message .= "Content-Transfer-Encoding: base64\r\n";
    $email_message .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
    $email_message .= chunk_split($pfp_chisiamo) . "\r\n";
}

$email_message .= "--{$boundary}--\r\n";

if(mail($to, $subject, $email_message, $headers)) {
    $_SESSION['result_candidatura'] = "Candidatura inviata con successo!";
} else {
    $_SESSION['result_candidatura'] = "Errore nell'invio della candidatura.";
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();
?>