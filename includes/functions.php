<?php

require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/totp_helpers.php';

function sendVerificationEmail($email, $username, $token)
{
    $subject = 'Verifica la tua email - ' . SITE_NAME;
    $verificationLink = SITE_URL . '/it/verifica-email?token=' . $token;

    $htmlBody = getVerificationEmailTemplate($username, $verificationLink);

    $textBody = "Ciao $username,\n\nGrazie per esserti registrato su " . SITE_NAME . ".\n\nPer completare la registrazione, visita questo link: $verificationLink\n\nSe non ti sei registrato, ignora questa email.\n\nCordiali saluti,\nIl team di " . SITE_NAME;

    $headers = array();
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . FROM_EMAIL;
    $headers[] = 'Return-Path: ' . FROM_EMAIL;
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    if (mail($email, $subject, $htmlBody, implode("\r\n", $headers))) {
        return true;
    } else {
        error_log("Errore invio email di verifica a: $email");
        return false;
    }
}

function getVerificationEmailTemplate($username, $verificationLink)
{
    return "
    <!DOCTYPE html>
    <html lang='it'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Verifica Email - " . SITE_NAME . "</title>
        <style>
            body {
                font-family: 'Poppins', Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f4f4f4;
            }
            .container {
                background-color: #ffffff;
                border-radius: 10px;
                padding: 30px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .logo {
                font-size: 24px;
                font-weight: bold;
                color: #007bff;
            }
            .button {
                display: inline-block;
                padding: 15px 30px;
                background-color: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin: 20px 0;
            }
            .button:hover {
                background-color: #0056b3;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                font-size: 12px;
                color: #666;
                text-align: center;
            }
            .warning {
                background-color: #fff3cd;
                border: 1px solid #ffeaa7;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>" . SITE_NAME . "</div>
            </div>

            <h2>Ciao " . htmlspecialchars($username) . "!</h2>

            <p>Grazie per esserti registrato su " . SITE_NAME . ". Siamo felici di averti nella nostra community!</p>

            <p>Per completare la registrazione e iniziare a utilizzare il tuo account, devi verificare il tuo indirizzo email cliccando sul pulsante sottostante:</p>

            <div style='text-align: center;'>
                <a href='" . $verificationLink . "' class='button'>Verifica la tua Email</a>
            </div>

            <p>Se non riesci a cliccare sul pulsante, copia e incolla questo link nel tuo browser:</p>
            <p style='word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 5px;'>" . $verificationLink . "</p>

            <div class='warning'>
                <strong>Importante:</strong> Questo link scadrà tra 24 ore per motivi di sicurezza.
            </div>

            <p>Se non ti sei registrato su " . SITE_NAME . ", puoi tranquillamente ignorare questa email.</p>

            <div class='footer'>
                <p>Cordiali saluti,<br>Il team di " . SITE_NAME . "</p>
                <p>Questa è una email automatica, non rispondere a questo messaggio.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function sendVerificationEmailChanged($email, $username, $token)
{
    $subject = 'Verifica la tua email - ' . SITE_NAME;
    $verificationLink = SITE_URL . '/it/verifica-email?token=' . $token;

    $htmlBody = getVerificationEmailChangedTemplate($username, $verificationLink);

    $textBody = "Ciao $username,\n\nGrazie per esserti registrato su " . SITE_NAME . ".\n\nPer completare la registrazione, visita questo link: $verificationLink\n\nSe non ti sei registrato, ignora questa email.\n\nCordiali saluti,\nIl team di " . SITE_NAME;

    $headers = array();
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . FROM_EMAIL;
    $headers[] = 'Return-Path: ' . FROM_EMAIL;
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    if (mail($email, $subject, $htmlBody, implode("\r\n", $headers))) {
        return true;
    } else {
        error_log("Errore invio email di verifica a: $email");
        return false;
    }
}

function getVerificationEmailChangedTemplate($username, $verificationLink)
{
    return "
    <!DOCTYPE html>
    <html lang='it'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Verifica Email - " . SITE_NAME . "</title>
        <style>
            body {
                font-family: 'Poppins', Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f4f4f4;
            }
            .container {
                background-color: #ffffff;
                border-radius: 10px;
                padding: 30px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .logo {
                font-size: 24px;
                font-weight: bold;
                color: #007bff;
            }
            .button {
                display: inline-block;
                padding: 15px 30px;
                background-color: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin: 20px 0;
            }
            .button:hover {
                background-color: #0056b3;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                font-size: 12px;
                color: #666;
                text-align: center;
            }
            .warning {
                background-color: #fff3cd;
                border: 1px solid #ffeaa7;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>" . SITE_NAME . "</div>
            </div>

            <h2>Ciao " . htmlspecialchars($username) . "!</h2>

            <p>La tua email su " . SITE_NAME . "é stata modificata!</p>

            <p>Per completare la modifica della email devi verificare il tuo indirizzo email cliccando sul pulsante sottostante:</p>

            <div style='text-align: center;'>
                <a href='" . $verificationLink . "' class='button'>Verifica la tua Email</a>
            </div>

            <p>Se non riesci a cliccare sul pulsante, copia e incolla questo link nel tuo browser:</p>
            <p style='word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 5px;'>" . $verificationLink . "</p>

            <div class='warning'>
                <strong>Importante:</strong> Questo link scadrà tra 24 ore per motivi di sicurezza.
            </div>

            <p>Se non hai cambiato tu la tua email su " . SITE_NAME . ", contattaci subito a cripsum@cripsum.com per assistenza</p>

            <div class='footer'>
                <p>Cordiali saluti,<br>Il team di " . SITE_NAME . "</p>
                <p>Questa è una email automatica, non rispondere a questo messaggio.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function verifyEmail($mysqli, $token)
{
    $stmt = $mysqli->prepare("
        SELECT id, username, email 
        FROM utenti 
        WHERE email_token = ? 
        AND email_verificata = 0
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $updateStmt = $mysqli->prepare("UPDATE utenti SET email_verificata = 1, email_token = NULL WHERE id = ?");
        $updateStmt->bind_param("i", $user['id']);

        if ($updateStmt->execute()) {
            $updateStmt->close();
            $stmt->close();
            return $user;
        }
        $updateStmt->close();
    }

    $stmt->close();
    return false;
}

function sendWelcomeEmail($email, $username)
{
    $subject = 'Benvenuto su ' . SITE_NAME . '!';
    $htmlBody = getWelcomeEmailTemplate($username);
    $textBody = "Benvenuto su " . SITE_NAME . ", $username!\n\nIl tuo account è stato verificato con successo.\n\nPuoi ora accedere e iniziare a utilizzare tutti i nostri servizi.\n\nGrazie per esserti unito a noi!\n\nCordiali saluti,\nIl team di " . SITE_NAME;

    $headers = array();
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . FROM_EMAIL;
    $headers[] = 'Return-Path: ' . FROM_EMAIL;
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    if (mail($email, $subject, $htmlBody, implode("\r\n", $headers))) {
        return true;
    } else {
        error_log("Errore invio email di benvenuto a: $email");
        return false;
    }
}

function getWelcomeEmailTemplate($username)
{
    return "
    <!DOCTYPE html>
    <html lang='it'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Benvenuto - " . SITE_NAME . "</title>
        <style>
            body {
                font-family: 'Poppins', Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f4f4f4;
            }
            .container {
                background-color: #ffffff;
                border-radius: 10px;
                padding: 30px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .logo {
                font-size: 24px;
                font-weight: bold;
                color: #28a745;
            }
            .success-icon {
                font-size: 48px;
                color: #28a745;
                margin: 20px 0;
            }
            .button {
                display: inline-block;
                padding: 15px 30px;
                background-color: #28a745;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin: 20px 0;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                font-size: 12px;
                color: #666;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>" . SITE_NAME . "</div>
                <div class='success-icon'>🎉</div>
            </div>

            <h2>Benvenuto " . htmlspecialchars($username) . "!</h2>

            <p>Il tuo account è stato verificato con successo. Ora puoi accedere e iniziare a utilizzare tutti i nostri servizi.</p>

            <div style='text-align: center;'>
                <a href='" . SITE_URL . "/it/accedi' class='button'>Accedi ora</a>
            </div>

            <p>Se hai domande o hai bisogno di assistenza, non esitare a contattarci.</p>

            <div class='footer'>
                <p>Cordiali saluti,<br>Il team di " . SITE_NAME . "</p>
                <p>Questa è una email automatica, non rispondere a questo messaggio.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function sendPremiumGiftEmail($email, $recipientUsername, $senderUsername)
{
    $subject = $senderUsername . ' ti ha regalato il Premium su ' . SITE_NAME . '!';
    $htmlBody = getPremiumGiftEmailTemplate($recipientUsername, $senderUsername);

    $headers = array();
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . FROM_EMAIL;
    $headers[] = 'Return-Path: ' . FROM_EMAIL;
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    if (mail($email, $subject, $htmlBody, implode("\r\n", $headers))) {
        return true;
    } else {
        error_log("Errore invio email regalo a: $email");
        return false;
    }
}

function getPremiumGiftEmailTemplate($recipientUsername, $senderUsername)
{
    $siteName = SITE_NAME;
    $siteUrl = SITE_URL;
    $escRecipient = htmlspecialchars($recipientUsername);
    $escSender = htmlspecialchars($senderUsername);

    return <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Un regalo per te! - {$siteName}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0b0f19; font-family: 'Poppins', 'Segoe UI', Arial, sans-serif; -webkit-font-smoothing: antialiased; color: #f8fafc;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0b0f19; padding: 40px 10px;">
        <tr>
            <td align="center">
                <!-- Card Container -->
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 550px; background-color: #121829; border-radius: 16px; border: 1px solid #1e294b; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                    <!-- Header Image / Accent -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #7c3aed 0%, #d946ef 100%); padding: 35px 20px; text-align: center;">
                            <div style="font-size: 32px; font-weight: 800; letter-spacing: 2px; color: #ffffff; margin-bottom: 10px; text-transform: uppercase;">
                                {$siteName}
                            </div>
                            <div style="font-size: 64px; line-height: 1; margin: 15px 0;">🎁 💎</div>
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">Un regalo speciale per te!</h1>
                        </td>
                    </tr>
                    <!-- Main Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #ffffff; font-size: 20px; margin-top: 0; margin-bottom: 20px; font-weight: 600;">Ciao {$escRecipient}!</h2>
                            
                            <p style="color: #94a3b8; font-size: 15px; line-height: 1.6; margin-bottom: 25px;">
                                Abbiamo una splendida notizia! L'utente <strong style="color: #eab308; text-shadow: 0 0 10px rgba(234,179,8,0.2);">{$escSender}</strong> ti ha appena fatto un regalo speciale: ti ha donato l'abbonamento <strong style="color: #d946ef;">Cripsum™ Premium</strong>!
                            </p>

                            <!-- Perks Card -->
                            <div style="background-color: #1a2238; border-radius: 12px; padding: 25px; border: 1px solid #2e3b5e; margin-bottom: 30px;">
                                <h3 style="color: #ffffff; font-size: 16px; margin-top: 0; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; color: #eab308;">Cosa hai sbloccato:</h3>
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="vertical-align: top; width: 30px; font-size: 18px; padding-bottom: 15px;">💎</td>
                                        <td style="color: #f8fafc; font-size: 14px; padding-bottom: 15px; line-height: 1.5;">
                                            <strong style="color: #ffffff;">Cripsum™ Premium Status</strong><br>
                                            <span style="color: #94a3b8;">Personalizzazione totale del profilo con sfondi video, musiche, effetti cursori, layout personalizzati e altro!</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="vertical-align: top; width: 30px; font-size: 18px; padding-bottom: 15px;">🪙</td>
                                        <td style="color: #f8fafc; font-size: 14px; padding-bottom: 15px; line-height: 1.5;">
                                            <strong style="color: #ffffff;">Bonus di 200.000 Soldi</strong><br>
                                            <span style="color: #94a3b8;">Aggiunti istantaneamente al tuo bilancio per pullare nel Gacha dei personaggi!</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="vertical-align: top; width: 30px; font-size: 18px;">🏅</td>
                                        <td style="color: #f8fafc; font-size: 14px; line-height: 1.5;">
                                            <strong style="color: #ffffff;">Badge Premium</strong><br>
                                            <span style="color: #94a3b8;">Un luccicante badge Premium a forma di diamante visualizzato con orgoglio sul tuo profilo.</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- CTA Button -->
                            <div style="text-align: center; margin-bottom: 30px;">
                                <a href="{$siteUrl}/it/edit-profile" style="display: inline-block; background: linear-gradient(135deg, #7c3aed 0%, #d946ef 100%); color: #ffffff; font-weight: 700; font-size: 15px; text-decoration: none; padding: 16px 36px; border-radius: 30px; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4); text-transform: uppercase; letter-spacing: 1px;">
                                    Personalizza Profilo
                                </a>
                            </div>

                            <p style="color: #94a3b8; font-size: 14px; line-height: 1.6; text-align: center; margin-bottom: 0;">
                                Divertiti con le tue nuove feature Premium! ✨
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0d1220; padding: 25px 30px; text-align: center; border-top: 1px solid #1e294b;">
                            <p style="color: #64748b; font-size: 12px; line-height: 1.5; margin: 0;">
                                Cordiali saluti,<br>
                                <strong style="color: #94a3b8;">Il team di {$siteName}</strong>
                            </p>
                            <p style="color: #475569; font-size: 11px; margin-top: 12px; margin-bottom: 0;">
                                Questa è una email automatica, si prega di non rispondere direttamente a questo messaggio.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

function loginUser($mysqli, $email_or_username, $password)
{
    $result = auth_start_password_login($mysqli, $email_or_username, $password);

    if (is_array($result) && !empty($result['ok'])) {
        return true;
    }

    if (is_array($result) && !empty($result['twofa_required'])) {
        return '2FA_REQUIRED';
    }

    if (is_array($result) && !empty($result['message'])) {
        return $result['message'];
    }

    return false;
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin()
{
    return isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'admin';
}

function isOwner()
{
    return isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'owner';
}

function isPremium()
{
    return isset($_SESSION['is_premium']) && (int)$_SESSION['is_premium'] === 1;
}


function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: accedi');
        exit();
    }
}

function validateMessage($message)
{
    return strlen($message) <= 30;
}

function getUserRole()
{
    return $_SESSION['ruolo'] ?? 'utente';
}

function canDeleteMessage($messageUserId)
{
    $userRole = getUserRole();
    return $userRole === 'admin' || $userRole === 'owner' || $_SESSION['user_id'] == $messageUserId;
}

function setMessageTimeout()
{
    if (!isset($_SESSION['last_message_time'])) {
        $_SESSION['last_message_time'] = time();
        return true;
    }

    $currentTime = time();
    if (($currentTime - $_SESSION['last_message_time']) >= 5) {
        $_SESSION['last_message_time'] = $currentTime;
        return true;
    }

    return false;
}

function checkBan($mysqli)
{
    if (isset($_COOKIE['banned']) && $_COOKIE['banned'] == '1') {
        header('Location: https://cripsum.com/it/banned');
        exit();
    }

    if (!isLoggedIn()) return;

    $user_id = $_SESSION['user_id'];
    $stmt = $mysqli->prepare("SELECT isBannato, banned_until FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['isBannato'] == 1) {
            $banned_until = $row['banned_until'] ?? null;
            if ($banned_until !== null && strtotime($banned_until) <= time()) {
                $stmt2 = $mysqli->prepare("UPDATE utenti SET isBannato = 0, banned_until = NULL, motivo_ban = NULL WHERE id = ?");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $stmt2->close();
                return;
            }
            header('Location: https://cripsum.com/it/banned');
            exit();
        }
    }
}

function checkPermissions($mysqli, $requiredRole)
{
    $rolesHierarchy = ['utente' => 1, 'admin' => 2, 'owner' => 3];

    if (isLoggedIn()) {
        $stmt = $mysqli->prepare("SELECT ruolo FROM utenti WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            $currentUserRole = $user['ruolo'];
            $_SESSION['ruolo'] = $currentUserRole;
        } else {
            $currentUserRole = 'utente';
        }
    } else {
        $currentUserRole = 'utente';
    }

    if ($rolesHierarchy[$currentUserRole] < $rolesHierarchy[$requiredRole]) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Accesso negato. Non hai i permessi necessari per accedere a questa pagina. (skill issue)';
        exit();
    }
}

function logoutUser()
{
    session_destroy();
    header('Location: https://cripsum.com');
    exit();
}

function registerUser($mysqli, $username, $email, $password)
{
    $checkStmt = $mysqli->prepare("SELECT id FROM utenti WHERE username = ? OR email = ?");
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->fetch_assoc()) {
        return 'Username o email già in uso';
    }
    $checkStmt->close();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $emailToken = bin2hex(random_bytes(32));

    $insertStmt = $mysqli->prepare("
        INSERT INTO utenti (username, email, password, data_creazione, ruolo, email_verificata, email_token) VALUES (?, ?, ?, NOW(), 'utente', 0, ?)");
    $insertStmt->bind_param("ssss", $username, $email, $passwordHash, $emailToken);

    if ($insertStmt->execute()) {
        $insertStmt->close();

        if (sendVerificationEmail($email, $username, $emailToken)) {
            return true;
        } else {
            return 'Registrazione completata ma errore nell\'invio dell\'email di verifica';
        }
    } else {
        $insertStmt->close();
        return 'Errore durante la registrazione';
    }
}

function resendVerificationEmail($mysqli, $email)
{
    $stmt = $mysqli->prepare("SELECT id, username, email_token FROM utenti WHERE email = ? AND email_verificata = 0");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $newToken = bin2hex(random_bytes(32));

        $updateStmt = $mysqli->prepare("UPDATE utenti SET email_token = ? WHERE id = ?");
        $updateStmt->bind_param("si", $newToken, $user['id']);
        $updateStmt->execute();
        $updateStmt->close();

        if (sendVerificationEmail($email, $user['username'], $newToken)) {
            $stmt->close();
            return true;
        }
    }

    $stmt->close();
    return false;
}


function getCurrentUser($mysqli)
{
    if (!isLoggedIn()) {
        return null;
    }

    $stmt = $mysqli->prepare("
        SELECT id, username, email, profile_pic, data_creazione, ruolo, soldi, isBannato, nswf
        FROM utenti 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function getUserProfile($mysqli, $userId)
{
    $stmt = $mysqli->prepare("
        SELECT id, username, email, profile_pic, data_creazione, ruolo, soldi, isBannato, nswf
        FROM utenti 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function updateUserProfile($mysqli, $userId, $username, $email, $profilePic)
{
    $stmt = $mysqli->prepare("
        UPDATE utenti 
        SET username = ?, email = ?, profile_pic = ? 
        WHERE id = ? 
    ");
    $stmt->bind_param("sssi", $username, $email, $profilePic, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        $stmt->close();
        return 'Errore durante l\'aggiornamento del profilo';
    }
}

function deleteUserAccount($mysqli, $userId)
{
    $stmt = $mysqli->prepare("DELETE FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $userId);
    if ($stmt->execute()) {
        session_destroy();
        $stmt->close();
        return true;
    } else {
        $stmt->close();
        return 'Errore durante l\'eliminazione dell\'account';
    }
}

function cleanExpiredTokens($mysqli)
{
    $stmt = $mysqli->prepare("UPDATE utenti SET email_token = NULL WHERE email_token IS NOT NULL AND data_creazione < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $stmt->close();
}

function updateUserSettings($mysqli, $userId, $username, $email, $password, $nsfw, $richpresence)
{
    $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return "Il nome utente è già in uso";
    }

    $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return "L'email è già in uso";
    }

    $currentEmailStmt = $mysqli->prepare("SELECT email FROM utenti WHERE id = ?");
    $currentEmailStmt->bind_param("i", $userId);
    $currentEmailStmt->execute();
    $currentEmailResult = $currentEmailStmt->get_result();
    $currentUser = $currentEmailResult->fetch_assoc();
    $currentEmailStmt->close();

    $emailChanged = ($currentUser['email'] !== $email);


    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        if ($emailChanged) {
            $emailToken = bin2hex(random_bytes(32));
            $stmt = $mysqli->prepare("UPDATE utenti SET username = ?, password = ?, nsfw = ?, richpresence = ?, email_verificata = 0, email_token = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssiissi", $username, $hashedPassword, $nsfw, $richpresence, $emailToken, $email, $userId);
        } else {
            $stmt = $mysqli->prepare("UPDATE utenti SET username = ?, password = ?, nsfw = ?, richpresence = ? WHERE id = ?");
            $stmt->bind_param("ssiii", $username, $hashedPassword, $nsfw, $richpresence, $userId);
        }
    } else {
        if ($emailChanged) {
            $emailToken = bin2hex(random_bytes(32));
            $stmt = $mysqli->prepare("UPDATE utenti SET username = ?, nsfw = ?, richpresence = ?, email_verificata = 0, email_token = ?, email = ? WHERE id = ?");
            $stmt->bind_param("siissi", $username, $nsfw, $richpresence, $emailToken, $email, $userId);
        } else {
            $stmt = $mysqli->prepare("UPDATE utenti SET username = ?, nsfw = ?, richpresence = ? WHERE id = ?");
            $stmt->bind_param("siii", $username, $nsfw, $richpresence, $userId);
        }
    }

    if ($stmt->execute()) {
        if ($emailChanged) {
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['nsfw'] = $nsfw;
            $_SESSION['richpresence'] = $richpresence;
            return true;
        } else {
            $_SESSION['username'] = $username;
            $_SESSION['nsfw'] = $nsfw;
            $_SESSION['richpresence'] = $richpresence;
            return true;
        }
    } else {
        return "Errore durante l'aggiornamento";
    }
}

function isUserOnline($mysqli, $user_id)
{
    $ultimo_accesso = null;

    $time_limit = date('Y-m-d H:i:s', strtotime('-30 seconds'));
    $stmt = $mysqli->prepare("SELECT ultimo_accesso FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($ultimo_accesso);
    $stmt->fetch();
    $stmt->close();

    if (!$ultimo_accesso) return false;
    return ($ultimo_accesso > $time_limit);
}

function getUltimoAccesso($mysqli, $user_id)
{
    $ultimo_accesso = null;

    $stmt = $mysqli->prepare("SELECT ultimo_accesso FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($ultimo_accesso);
    $stmt->fetch();
    $stmt->close();

    return $ultimo_accesso;
}
