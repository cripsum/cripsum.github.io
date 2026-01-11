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

if ($_POST) {
    $email_or_username = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email_or_username) || empty($password)) {
        $error = 'Compila tutti i campi!';
    } else {
        $result = loginUser($mysqli, $email_or_username, $password);
        if ($result === true) {
            $redirect = $_SESSION['redirect_after_login'] ?? 'home';
            unset($_SESSION['redirect_after_login']);
            header("Location: $redirect");
            exit();
        } else {
            $error = is_string($result) ? $result : 'Email/Username o password non corretti';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Accedi</title>
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

                            <?php if (strpos($error, 'verificare') !== false): ?>
                                <div class="mt-2">
                                    <a href="verifica-email" class="alert-link">Clicca qui per reinviare l'email di verifica</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success fadeup" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['login_message'])): ?>
                        <div class="alert alert-info fadeup" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <?php echo htmlspecialchars($_SESSION['login_message']); ?>
                        </div>
                        <?php unset($_SESSION['login_message']); ?>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <p class="fs-1 text mb-5 fadeup" style="font-weight: bold">Accedi</p>

                        <div data-mdb-input-init class="form-outline mb-4 fadeup">
                            <label class="form-label" for="email">Email o Username</label>
                            <input type="text" id="email" name="email" class="form-control"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required />
                        </div>

                        <div data-mdb-input-init class="form-outline mb-4 fadeup">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required />
                        </div>

                        <div class="button-container mb-3 fadeup" style="text-align: center; margin-top: 3%">
                            <button class="btn btn-secondary bottone" type="submit">
                                <span class="testobianco">Accedi</span>
                            </button>
                        </div>

                        <div class="text-center fadeup">
                            <p>
                                <a href="password-dimenticata" style="font-weight: bold" class="linkbianco">Password dimenticata?</a>
                            </p>
                            <p>
                                Non hai ancora un account?
                                <a href="registrati" style="font-weight: bold" class="linkbianco">Registrati</a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>


    </div>
    <?php include '../includes/footer.php'; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    <script src="../js/modeChanger.js"></script>
    <script src="../js/login.js"></script>
</body>

</html>