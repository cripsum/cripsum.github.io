<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../auth/google_config.php';
require_once '../includes/functions.php';

if (!isset($_GET['code'])) {
    header('Location: accedi.php?error=Accesso+annullato');
    exit();
}

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code',
    'code' => $_GET['code']
]));
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    header('Location: accedi.php?error=Errore+autenticazione+Google');
    exit();
}

$ch = curl_init("https://www.googleapis.com/oauth2/v2/userinfo?access_token=" . $tokenData['access_token']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$userInfoResponse = curl_exec($ch);
curl_close($ch);

$googleUser = json_decode($userInfoResponse, true);
if (!isset($googleUser['email'])) {
    header('Location: accedi.php?error=Impossibile+recuperare+dati+da+Google');
    exit();
}

$google_id = $googleUser['id'];
$email = strtolower(trim($googleUser['email']));
$name = $googleUser['name'] ?? explode('@', $email)[0];

$stmt = $mysqli->prepare("SELECT id, username, password, google_id, profile_pic, ruolo, nsfw, richpresence FROM utenti WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (empty($row['google_id'])) {
        $update = $mysqli->prepare("UPDATE utenti SET google_id = ? WHERE id = ?");
        $update->bind_param("si", $google_id, $row['id']);
        $update->execute();
    }

    $_SESSION['user_id'] = $row['id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['email'] = $email;
    $_SESSION['profile_pic'] = $row['profile_pic'] ?? '../img/abdul.jpg';
    $_SESSION['ruolo'] = $row['ruolo'] ?? 'utente';
    $_SESSION['nsfw'] = (int)($row['nsfw'] ?? 0);
    $_SESSION['richpresence'] = (int)($row['richpresence'] ?? 0);

    if (empty($row['password'])) {
        $_SESSION['needs_password'] = true;
    }
} else {
    $base_username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $name));
    $username = $base_username;

    $check_user = $mysqli->prepare("SELECT id FROM utenti WHERE username = ?");
    $i = 1;
    while (true) {
        $check_user->bind_param("s", $username);
        $check_user->execute();
        if ($check_user->get_result()->num_rows == 0) break;
        $username = $base_username . $i;
        $i++;
    }

    $insert = $mysqli->prepare("INSERT INTO utenti (username, email, google_id) VALUES (?, ?, ?)");
    $insert->bind_param("sss", $username, $email, $google_id);
    $insert->execute();

    $_SESSION['user_id'] = $insert->insert_id;
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    $_SESSION['needs_password'] = true;
}

$redirect = $_SESSION['redirect_after_login'] ?? 'home';
unset($_SESSION['redirect_after_login']);
header('Location: ' . $redirect);
exit();
