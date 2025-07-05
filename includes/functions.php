<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function loginUser($mysqli, $email, $password) {
    $stmt = $mysqli->prepare("SELECT id, username, email, password, profile_pic FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['profile_pic'] = $user['profile_pic'] ?? '../img/abdul.png';
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
    header('Location: home');
    exit();
}

function registerUser($mysqli, $username, $email, $password) {
    // Controlla se username o email esistono già
    $checkStmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->fetch_assoc()) {
        return 'Username o email già in uso';
    }
    $checkStmt->close();

    // Hash della password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Inserisci nuovo utente
    $insertStmt = $mysqli->prepare("
        INSERT INTO users (username, email, password, created_at, is_active) 
        VALUES (?, ?, ?, NOW(), 1)
    ");
    $insertStmt->bind_param("sss", $username, $email, $passwordHash);

    if ($insertStmt->execute()) {
        $insertStmt->close();
        return true;
    } else {
        $insertStmt->close();
        return 'Errore durante la registrazione';
    }
}

/**
 * Controlla e ripristina la sessione dal token "ricordami"
 */
function checkRememberToken($mysqli) {
    if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];

        $stmt = $mysqli->prepare("
            SELECT u.id, u.username, u.email, u.profile_pic 
            FROM users u 
            JOIN remember_tokens rt ON u.id = rt.user_id 
            WHERE rt.token = ? AND rt.expires_at > NOW() AND u.is_active = 1
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['profile_pic'] = $user['profile_pic'] ?? '../img/default-avatar.png';
            $_SESSION['login_time'] = time();

            // Aggiorna ultimo accesso
            $updateStmt = $mysqli->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();

            $stmt->close();
            return true;
        } else {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        $stmt->close();
    }
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
        SELECT id, username, email, profile_pic, created_at, last_login 
        FROM users 
        WHERE id = ? AND is_active = 1
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
        SELECT id, username, email, profile_pic, created_at, last_login 
        FROM users 
        WHERE id = ? AND is_active = 1
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
        UPDATE users 
        SET username = ?, email = ?, profile_pic = ? 
        WHERE id = ? AND is_active = 1
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
    $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ? AND is_active = 1");
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
?>
