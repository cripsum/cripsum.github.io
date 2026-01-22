<?php

require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isLoggedIn()) {
    header('Location: home');
    exit();
}

$error = '';
$success = '';

function isValidUsername($username)
{

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return false;
    }

    if (preg_match('/[_]$/', $username)) {
        return false;
    }

    if (preg_match('/^[_]/', $username)) {
        return false;
    }

    if (strlen($username) < 3) {
        return false;
    }

    if (strlen($username) > 20) {
        return false;
    }

    if (preg_match('/\s/', $username)) {
        return false;
    }

    return true;
}

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $repeatPassword = $_POST['repeatPassword'] ?? '';
    $acceptTerms = isset($_POST['acceptTerms']);
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

    $recaptchaSecret = "6Lcy-7srAAAAAMPFQJ_RnHSeJ0ineOsy89mtYQVc";
    $verify = curl_init();
    curl_setopt($verify, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
    curl_setopt($verify, CURLOPT_POST, true);
    curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $recaptchaSecret,
        'response' => $recaptchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ]));
    curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($verify);
    curl_close($verify);
    $captchaResult = json_decode($response, true);

    if (empty($username) || empty($email) || empty($password) || empty($repeatPassword)) {
        $error = 'Compila tutti i campi obbligatori';
    } elseif (!$acceptTerms) {
        $error = 'Devi accettare i termini e condizioni';
    } elseif ($password !== $repeatPassword) {
        $error = 'Le password non corrispondono';
    } elseif (!isValidUsername($username)) {
        $error = "L'username può contenere solo lettere, numeri e underscore, non può iniziare o finire con un carattere speciale, deve essere lungo tra 3 e 20 caratteri e non può contenere spazi.";
    } elseif (strlen($password) < 6) {
        $error = 'La password deve essere di almeno 6 caratteri';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email non valida';
    } elseif (!$captchaResult['success']) {
        $error = "Verifica captcha fallita. Riprova.";
    } else {
        $result = registerUser($mysqli, strtolower($username), $email, $password);
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
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - registrati</title>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div style="max-width: 1920px; margin: auto; padding-top: 7rem; padding-bottom: 4rem;" class="testobianco">
        <div class="loginpagege text-center mt-5">
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


                            <div class="form-check d-flex justify-content-center mb-4 fadeup">
                                <input class="form-check-input me-2 checco" type="checkbox" value="1" id="acceptTerms" name="acceptTerms" required
                                    <?php echo (isset($_POST['acceptTerms']) ? 'checked' : ''); ?> />
                                <label class="form-check-label text-center">
                                    Ho letto e accetto i <a href="tos" style="text-decoration: none; font-weight: bold" class="linkbianco">termini e condizioni</a>
                                </label>
                            </div>
                            <div class="g-recaptcha text-center" style="margin: auto;" data-sitekey="6Lcy-7srAAAAABHpyz_WFjiMIpLlfKi55pgsEHv4" data-theme="dark"></div>

                            <div class="button-container mb-3 fadeup" style="text-align: center; margin-top: 3%">
                                <button class="btn btn-secondary bottone" type="submit">
                                    <span class="testobianco">Registrati</span>
                                </button>
                            </div>

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


    </div>
    <?php include '../includes/footer.php'; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    
    <script src="../js/login.js"></script>

    <script>
        document.getElementById("repeatPassword").addEventListener("input", function() {
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