<?php

function loginUser($mysqli, $email, $password) {
    $stmt = $mysqli->prepare("SELECT id, username, email, password, profile_pic FROM utenti WHERE email = ?");
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

    // Inserisci nuovo utente
    $insertStmt = $mysqli->prepare("
        INSERT INTO utenti (username, email, password, data_creazione) 
        VALUES (?, ?, ?, NOW())
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
?>
