<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$message = '';
$messageType = '';

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    if (!empty($token)) {
        $user = verifyEmail($mysqli, $token);
        
        if ($user) {
            $message = "Email verificata con successo! Ora puoi accedere al tuo account.";
            $messageType = 'success';
            
            sendWelcomeEmail($user['email'], $user['username']);
        } else {
            $message = "Token di verifica non valido o già utilizzato.";
            $messageType = 'error';
        }
    } else {
        $message = "Token di verifica mancante.";
        $messageType = 'error';
    }
} else {
    $message = "Token di verifica mancante.";
    $messageType = 'error';
}

if ($_POST && isset($_POST['resend_email'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (resendVerificationEmail($mysqli, $email)) {
            $message = "Email di verifica reinviata con successo!";
            $messageType = 'success';
        } else {
            $message = "Errore nell'invio dell'email o email già verificata.";
            $messageType = 'error';
        }
    } else {
        $message = "Email non valida.";
        $messageType = 'error';
    }
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Verifica Email</title>
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div style="max-width: 1920px; margin: auto; padding-top: 7rem" class="testobianco">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="text-center mt-5">
                        <div class="fadeup">
                            <h1 class="fs-1 mb-4" style="font-weight: bold">Verifica Email</h1>
                            
                            <?php if ($messageType === 'success'): ?>
                                <div class="alert alert-success" role="alert">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <?php echo htmlspecialchars($message); ?>
                                </div>
                                <div class="mt-4">
                                    <a href="accedi" class="btn btn-secondary bottone">
                                        <span class="testobianco">Accedi ora</span>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <?php echo htmlspecialchars($message); ?>
                                </div>
                                
                                <div class="mt-4">
                                    <h4>Non hai ricevuto l'email di verifica?</h4>
                                    <p class="text-muted">Inserisci la tua email per ricevere un nuovo link di verifica:</p>
                                    
                                    <form method="POST" action="" class="mt-3">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" id="email" name="email" class="form-control" required>
                                        </div>
                                        <button type="submit" name="resend_email" class="btn btn-secondary bottone">
                                            <span class="testobianco">Reinvia Email</span>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <a href="registrati" class="linkbianco">Torna alla registrazione</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="privacy" class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="tos" class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="supporto" class="linkbianco">Supporto</a></li>
            </ul>
        </footer>
    </div>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"
    ></script>
    <script src="../js/modeChanger.js"></script>
</body>
</html>