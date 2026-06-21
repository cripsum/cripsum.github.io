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

$stmt = $mysqli->prepare("SELECT id, username, email, password, google_id, profile_pic, ruolo, nsfw, richpresence, twofa_enabled, twofa_secret, isBannato, is_premium, banned_until FROM utenti WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ((int)($row['isBannato'] ?? 0) === 1) {
        $banned_until = $row['banned_until'] ?? null;
        if ($banned_until !== null && strtotime($banned_until) <= time()) {
            $stmt_unban = $mysqli->prepare("UPDATE utenti SET isBannato = 0, banned_until = NULL, motivo_ban = NULL WHERE id = ?");
            $stmt_unban->bind_param("i", $row['id']);
            $stmt_unban->execute();
            $stmt_unban->close();
            $row['isBannato'] = 0;
            $row['banned_until'] = null;
        } else {
            auth_record_login_attempt($mysqli, (int)$row['id'], $email, false, 'google_login_banned');
            header('Location: accedi.php?error=Account+bannato.+Contatta+il+supporto.');
            exit();
        }
    }

    if (empty($row['google_id'])) {
        $update = $mysqli->prepare("UPDATE utenti SET google_id = ? WHERE id = ?");
        $update->bind_param("si", $google_id, $row['id']);
        $update->execute();
    }

    if ((int)($row['twofa_enabled'] ?? 0) === 1 && !empty($row['twofa_secret'])) {
        $_SESSION['pending_2fa_user_id'] = (int)$row['id'];
        $_SESSION['pending_2fa_started_at'] = time();
        $_SESSION['pending_2fa_identifier'] = $email;
        $_SESSION['pending_2fa_redirect'] = $_SESSION['redirect_after_login'] ?? 'home';

        auth_record_login_attempt($mysqli, (int)$row['id'], $email, true, 'google_ok_2fa_pending');

        header('Location: verifica-2fa');
        exit();
    }

    auth_complete_login($row, $mysqli);
    auth_record_login_attempt($mysqli, (int)$row['id'], $email, true, 'google_login_ok');
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

    $insert = $mysqli->prepare("INSERT INTO utenti (username, email, google_id, data_creazione, email_verificata, ruolo) VALUES (?, ?, ?, NOW(), 1, 'utente')");
    $insert->bind_param("sss", $username, $email, $google_id);
    $insert->execute();

    $new_user_id = $insert->insert_id;
    $user_data = [
        'id' => $new_user_id,
        'username' => $username,
        'email' => $email,
        'profile_pic' => '../img/abdul.jpg',
        'ruolo' => 'utente',
        'nsfw' => 0,
        'richpresence' => 0,
        'password' => ''
    ];
    auth_complete_login($user_data, $mysqli);
    auth_record_login_attempt($mysqli, $new_user_id, $email, true, 'google_register_ok');
}

$redirect = $_SESSION['redirect_after_login'] ?? 'home';
unset($_SESSION['redirect_after_login']);
header('Location: ' . $redirect);
exit();
