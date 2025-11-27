<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

if (!isLoggedIn()) {
    header('Location: accedi');
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$profilePic = "/includes/get_pfp.php?id=$userId";
$ruolo = $_SESSION['ruolo'] ?? 'utente';
$nsfw = $_SESSION['nsfw'] ?? 0;
$richpresence = $_SESSION['richpresence'] ?? 0;
$oldEmail = $_SESSION['email'] ?? '';
$success = '';
$error = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $nsfw = isset($_POST['nsfw']) ? 1 : 0;
    $richpresence = isset($_POST['richpresence']) ? 1 : 0;

    if (empty($username) || empty($email)) {
        $error = 'Compila tutti i campi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email non valida';
    } elseif (!isValidUsername($username)) {
        $error = "L'username può contenere solo lettere, numeri e underscore, non può iniziare o finire con un carattere speciale, deve essere lungo tra 3 e 20 caratteri e non può contenere spazi.";
    } else {
        $result = updateUserSettings($mysqli, $userId, $username, $email, $password, $nsfw, $richpresence);
        if ($result === true) {
            if ($_SESSION['email'] !== $oldEmail) {
                session_destroy();
                $success = "Modifica dell'email completata! Controlla la tua email per verificarla prima di poter accedere di nuovo.";
            } else {
                header('Location: home');
            }
        } else {
            $error = is_string($result) ? $result : 'Errore durante l\'aggiornamento delle impostazioni';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™</title>

</head>

<body class="">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="testobianco paginaprincipale fadeup" style="max-width: 800px; padding-top: 7rem;">
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
        <div class="container">
            <h1 class="mb-4">Impostazioni</h1>
            <p>Qui puoi modificare le tue impostazioni personali.</p>

            <form method="post" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Nome utente</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Nuova password (opzionale)</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Lascia vuoto per mantenere la password attuale">
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input checco" id="nsfw" name="nsfw" value="1" <?= $nsfw ? 'checked' : '' ?>>
                    <label class="form-check-label" for="nsfw">
                        Abilita contenuti NSFW
                    </label>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input checco" id="richpresence" name="richpresence" value="1" <?= $richpresence ? 'checked' : '' ?>>
                    <label class="form-check-label" for="richpresence">
                        Abilita Discord Rich Presence
                    </label>
                </div>
                <button class="btn btn-secondary bottone" type="submit">Salva modifiche</button>
            </form>
            <button class="btn btn-secondary bottone mt-3" type="button">
                <a class="testobianco" href="https://github.com/cripsum/cripsum.com-rich-presence/releases/tag/v1.0.0">Scarica client rich presence</a>
            </button>
        </div>
    </div>

    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement" />
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>

    <script src="../js/modeChanger.js"></script>
</body>

</html>