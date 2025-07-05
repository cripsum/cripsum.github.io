<?php

// Aggiungi all'inizio del file functions.php
require_once __DIR__ . '/../config/email_config.php';

/**
 * Invia email di verifica con funzione mail() nativa
 */
function sendVerificationEmail($email, $username, $token) {
    $subject = 'Verifica la tua email - ' . SITE_NAME;
    $verificationLink = SITE_URL . 'it/verifica-email?token=' . $token;
    
    // Contenuto HTML
    $htmlBody = getVerificationEmailTemplate($username, $verificationLink);
    
    // Contenuto testo semplice
    $textBody = "Ciao $username,\n\nGrazie per esserti registrato su " . SITE_NAME . ".\n\nPer completare la registrazione, visita questo link: $verificationLink\n\nSe non ti sei registrato, ignora questa email.\n\nCordiali saluti,\nIl team di " . SITE_NAME;
    
    // Headers
    $headers = array();
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . FROM_EMAIL;
    $headers[] = 'Return-Path: ' . FROM_EMAIL;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    
    // Invia email
    if (mail($email, $subject, $htmlBody, implode("\r\n", $headers))) {
        return true;
    } else {
        error_log("Errore invio email di verifica a: $email");
        return false;
    }
}

/**
 * Template HTML per email di verifica
 */
function getVerificationEmailTemplate($username, $verificationLink) {
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

/**
 * Verifica email tramite token (con scadenza)
 */
function verifyEmail($mysqli, $token) {
    // Verifica token valido e non scaduto (24 ore)
    $stmt = $mysqli->prepare("
        SELECT id, username, email 
        FROM utenti 
        WHERE email_token = ? 
        AND email_verificata = 0 
        AND data_creazione > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        // Aggiorna utente come verificato
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

/**
 * Invia email di benvenuto dopo la verifica
 */
function sendWelcomeEmail($email, $username) {
    $subject = 'Benvenuto su ' . SITE_NAME . '!';
    $htmlBody = getWelcomeEmailTemplate($username);
    $textBody = "Benvenuto su " . SITE_NAME . ", $username!\n\nIl tuo account è stato verificato con successo.\n\nPuoi ora accedere e iniziare a utilizzare tutti i nostri servizi.\n\nGrazie per esserti unito a noi!\n\nCordiali saluti,\nIl team di " . SITE_NAME;
    
    // Headers
    $headers = array();
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . FROM_EMAIL;
    $headers[] = 'Return-Path: ' . FROM_EMAIL;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    
    // Invia email
    if (mail($email, $subject, $htmlBody, implode("\r\n", $headers))) {
        return true;
    } else {
        error_log("Errore invio email di benvenuto a: $email");
        return false;
    }
}

/**
 * Template HTML per email di benvenuto
 */
function getWelcomeEmailTemplate($username) {
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
                <a href='" . SITE_URL . "it/accedi' class='button'>Accedi ora</a>
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

function loginUser($mysqli, $email, $password) {
    $stmt = $mysqli->prepare("SELECT id, username, email, password, profile_pic, ruolo, email_verificata FROM utenti WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // Controlla se l'email è verificata
        if ($user['email_verificata'] == 0) {
            return 'Devi verificare la tua email prima di accedere. Controlla la tua casella di posta.';
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['profile_pic'] = $user['profile_pic'] ?? '../img/abdul.jpg';
        $_SESSION['ruolo'] = $user['ruolo'];
        return true;
    }
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: accedi');
        exit();
    }
}

function logoutUser() {
    session_destroy();
    header('Location: https://cripsum.com');
    exit();
}

function registerUser($mysqli, $username, $email, $password) {
    // Controlla se username o email esistono già
    $checkStmt = $mysqli->prepare("SELECT id FROM utenti WHERE username = ? OR email = ?");
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->fetch_assoc()) {
        return 'Username o email già in uso';
    }
    $checkStmt->close();

    // Hash della password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Genera token di verifica
    $emailToken = bin2hex(random_bytes(32));

    // Inserisci nuovo utente con email non verificata
    $insertStmt = $mysqli->prepare("
        INSERT INTO utenti (username, email, password, data_creazione, ruolo, email_verificata, email_token) 
        VALUES (?, ?, ?, NOW(), 'utente', 0, ?)
    ");
    $insertStmt->bind_param("ssss", $username, $email, $passwordHash, $emailToken);

    if ($insertStmt->execute()) {
        $insertStmt->close();
        
        // Invia email di verifica
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

/**
 * Reinvia email di verifica
 */
function resendVerificationEmail($mysqli, $email) {
    $stmt = $mysqli->prepare("SELECT id, username, email_token FROM utenti WHERE email = ? AND email_verificata = 0");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        // Genera nuovo token se necessario
        $newToken = bin2hex(random_bytes(32));
        
        $updateStmt = $mysqli->prepare("UPDATE utenti SET email_token = ? WHERE id = ?");
        $updateStmt->bind_param("si", $newToken, $user['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Invia nuova email
        if (sendVerificationEmail($email, $user['username'], $newToken)) {
            $stmt->close();
            return true;
        }
    }
    
    $stmt->close();
    return false;
}

/**
 * Ottiene i dati dell'utente corrente
 */
function getCurrentUser($mysqli) {
    if (!isLoggedIn()) {
        return null;
    }

    $stmt = $mysqli->prepare("
        SELECT id, username, email, profile_pic, data_creazione
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

// Funzione per ottenere il profilo dell'utente
function getUserProfile($mysqli, $userId) {
    $stmt = $mysqli->prepare("
        SELECT id, username, email, profile_pic, data_creazione
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

// Funzione per aggiornare il profilo dell'utente
function updateUserProfile($mysqli, $userId, $username, $email, $profilePic) {
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

// Funzione per eliminare l'account dell'utente
function deleteUserAccount($mysqli, $userId) {
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

/**
 * Pulisce i token scaduti dal database (da eseguire periodicamente)
 */
function cleanExpiredTokens($mysqli) {
    $stmt = $mysqli->prepare("UPDATE utenti SET email_token = NULL WHERE email_token IS NOT NULL AND data_creazione < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $stmt->close();
}

?>