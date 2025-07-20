<?php

ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isLoggedIn()) {
    header('Location: home');
    exit();
}

$error = '';
$success = '';

if ($_POST) {

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $repeatPassword = $_POST['repeatPassword'] ?? '';
    $acceptTerms = isset($_POST['acceptTerms']);
    
    if (empty($username) || empty($email) || empty($password) || empty($repeatPassword)) {
        $error = 'Compila tutti i campi obbligatori';
    } elseif (!$acceptTerms) {
        $error = 'Devi accettare i termini e condizioni';
    } elseif ($password !== $repeatPassword) {
        $error = 'Le password non corrispondono';
    } elseif (strlen($password) < 6) {
        $error = 'La password deve essere di almeno 6 caratteri';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email non valida';
    } elseif (strlen($username) < 3) {
        $error = 'Lo username deve essere di almeno 3 caratteri';
    } else {
        $result = registerUser($mysqli, $username, $email, $password);
        if ($result === true) {
            $success = 'Registrazione completata! Controlla la tua email per verificare il tuo account prima di poter accedere.';
        } else {
            $error = $result; 
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
    <head>
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-T0CTM2SBJJ"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() {
                dataLayer.push(arguments);
            }
            gtag("js", new Date());

            gtag("config", "G-T0CTM2SBJJ");
        </script>
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
            crossorigin="anonymous"
        />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" />
        <link rel="icon" href="../img/Susremaster.png" type="image/png" />
        <link rel="shortcut icon" href="../img/Susremaster.png" type="image/png" />
        <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
        <link rel="stylesheet" href="../css/style.css" />
        <link rel="stylesheet" href="../css/style-dark.css" />
        <link rel="stylesheet" href="../css/animations.css" />
        <script src="../js/animations.js"></script>
        <script src="../js/richpresence.js"></script>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Cripsum™ - registrati</title>
    </head>

    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div style="max-width: 1920px; margin: auto; padding-top: 7rem" class="testobianco">
            <div class="loginpagege text-center mt-5">
                <!-- Pills content -->
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pills-login" role="tabpanel" aria-labelledby="tab-login">
                        <?php if ($error): ?>
                        <div class="alert alert-danger fadeup" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                        <div class="alert alert-success fadeup" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                        <div class="alert alert-info fadeup" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Non hai ricevuto l'email? Controlla la cartella spam o <a href="verifica-email" class="alert-link">clicca qui per reinviare</a>.
                        </div>
                        <?php endif; ?>

                        <?php if (!$success): ?>
                        <form method="POST" action="">
                            <p class="fs-1 text mb-5 fadeup" style="font-weight: bold">Registrati</p>

                            <div data-mdb-input-init class="form-outline mb-4 fadeup">
                                <label class="form-label" for="username">Username *</label>
                                <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required />
                                <small class="form-text text-muted testobianco">Minimo 3 caratteri</small>
                            </div>

                            <div data-mdb-input-init class="form-outline mb-4 fadeup">
                                <label class="form-label" for="email">Email *</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required />
                                <small class="form-text text-muted testobianco">Riceverai un'email di verifica</small>
                            </div>

                            <div data-mdb-input-init class="form-outline mb-4 fadeup">
                                <label class="form-label" for="password">Password *</label>
                                <input type="password" id="password" name="password" class="form-control" required />
                                <small class="form-text text-muted testobianco">Minimo 6 caratteri</small>
                            </div>

                            <div data-mdb-input-init class="form-outline mb-4 fadeup">
                                <label class="form-label" for="repeatPassword">Ripeti password *</label>
                                <input type="password" id="repeatPassword" name="repeatPassword" class="form-control" required />
                            </div>

                            <p class="text-center fadeup">Oppure:</p>

                            <div class="text-center mb-3 fadeup">
                                <button data-mdb-ripple-init type="button" class="btn btn-floating mx-1">
                                    <i class="bi bi-facebook" style="color: #ffffff"></i>
                                </button>

                                <button data-mdb-ripple-init type="button" class="btn btn-floating mx-1">
                                    <i class="bi bi-google" style="color: #ffffff"></i>
                                </button>

                                <button data-mdb-ripple-init type="button" class="btn btn-floating mx-1">
                                    <i class="bi bi-twitter" style="color: #ffffff"></i>
                                </button>

                                <button data-mdb-ripple-init type="button" class="btn btn-floating mx-1">
                                    <i class="bi bi-github" style="color: #ffffff"></i>
                                </button>
                            </div>

                            <!-- Checkbox -->
                            <div class="form-check d-flex justify-content-center mb-4 fadeup">
                                <input class="form-check-input me-2 checco" type="checkbox" value="1" id="acceptTerms" name="acceptTerms" required
                                <?php echo (isset($_POST['acceptTerms']) ? 'checked' : ''); ?>
                                />
                                <label class="form-check-label text-center">
                                    Ho letto e accetto i <a href="tos" style="text-decoration: none; font-weight: bold" class="linkbianco">termini e condizioni</a>
                                </label>
                            </div>

                            <!-- Submit button -->
                            <div class="button-container mb-3 fadeup" style="text-align: center; margin-top: 3%">
                                <button class="btn btn-secondary bottone" type="submit">
                                    <span class="testobianco">Registrati</span>
                                </button>
                            </div>

                            <!-- Login link -->
                            <div class="text-center fadeup">
                                <p>Hai già un account? <a href="accedi" style="font-weight: bold" class="linkbianco">Accedi</a></p>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="text-center fadeup mt-4">
                            <p>Hai già un account? <a href="accedi" style="font-weight: bold" class="linkbianco">Accedi</a></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pills content -->
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
        <script src="../js/login.js"></script>

        <script>
            // Validazione password in tempo reale
            document.getElementById("repeatPassword").addEventListener("input", function () {
                const password = document.getElementById("password").value;
                const repeatPassword = this.value;

                if (password !== repeatPassword && repeatPassword.length > 0) {
                    this.setCustomValidity("Le password non corrispondono");
                } else {
                    this.setCustomValidity("");
                }
            });
        </script>
    </body>
</html>