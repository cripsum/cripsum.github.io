<?php
function loginUser($pdo, $email, $password) {
    $stmt = $pdo->prepare("SELECT id, username, email, password, profile_pic FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
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

function registerUser($pdo, $nome, $username, $email, $password) {
    try {
        // Controlla se username o email esistono già
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        
        if ($checkStmt->fetch()) {
            return 'Username o email già in uso';
        }
        
        // Hash della password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Inserisci nuovo utente
        $insertStmt = $pdo->prepare("
            INSERT INTO users (nome, username, email, password, created_at, is_active) 
            VALUES (?, ?, ?, ?, NOW(), 1)
        ");
        
        $insertStmt->execute([$nome, $username, $email, $passwordHash]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Errore registrazione: " . $e->getMessage());
        return 'Errore durante la registrazione';
    }
}

/**
 * Controlla e ripristina la sessione dal token "ricordami"
 */
function checkRememberToken($pdo) {
    if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.nome, u.username, u.email, u.profile_pic 
                FROM users u 
                JOIN remember_tokens rt ON u.id = rt.user_id 
                WHERE rt.token = ? AND rt.expires_at > NOW() AND u.is_active = 1
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Ripristina la sessione
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nome'] = $user['nome'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['profile_pic'] = $user['profile_pic'] ?? '../img/default-avatar.png';
                $_SESSION['login_time'] = time();
                
                // Aggiorna ultimo accesso
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                return true;
            } else {
                // Token non valido, rimuovi il cookie
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            }
        } catch (PDOException $e) {
            error_log("Errore controllo token: " . $e->getMessage());
        }
    }
    
    return false;
}

/**
 * Ottiene i dati dell'utente corrente
 */
function getCurrentUser($pdo) {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, username, email, profile_pic, created_at, last_login 
            FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Errore ottenimento utente: " . $e->getMessage());
        return null;
    }
}

// Funzione per ottenere il profilo dell'utente
function getUserProfile($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, username, email, profile_pic, created_at, last_login 
            FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Errore ottenimento profilo utente: " . $e->getMessage());
        return null;
    }
}

// Funzione per aggiornare il profilo dell'utente
function updateUserProfile($pdo, $userId, $nome, $username, $email, $profilePic) {
    try {
        // Prepara l'aggiornamento
        $stmt = $pdo->prepare("
            UPDATE users 
            SET nome = ?, username = ?, email = ?, profile_pic = ? 
            WHERE id = ? AND is_active = 1
        ");
        
        // Esegui l'aggiornamento
        $stmt->execute([$nome, $username, $email, $profilePic, $userId]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Errore aggiornamento profilo: " . $e->getMessage());
        return 'Errore durante l\'aggiornamento del profilo';
    }
}

// Funzione per eliminare l'account dell'utente
function deleteUserAccount($pdo, $userId) {
    try {
        // Prepara l'eliminazione
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_active = 1");
        
        // Esegui l'eliminazione
        $stmt->execute([$userId]);
        
        // Distruggi la sessione
        session_destroy();
        
        return true;
    } catch (PDOException $e) {
        error_log("Errore eliminazione account: " . $e->getMessage());
        return 'Errore durante l\'eliminazione dell\'account';
    }
}



?>