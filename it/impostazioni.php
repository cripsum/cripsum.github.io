<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
error_reporting(1);

ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: accedi');
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$profilePic = "/includes/get_pfp.php?id=$userId";
$ruolo = $_SESSION['ruolo'] ?? 'utente';
$nsfw = $_SESSION['nsfw'] ?? 0; // Imposta nsfw a 0 se non è definito

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $nsfw = isset($_POST['nsfw']) ? 1 : 0;

    if (empty($username) || empty($email)) {
        $error = 'Compila tutti i campi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email non valida';
    } else {
        $result = updateUserSettings($mysqli, $userId, $username, $email, $password, $nsfw);
        if ($result === true) {
            header('Location: home');
            exit();
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
        <script src="/js/nomePagina.js"></script>
    </head>

    <body class="">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div class="testobianco paginaprincipale fadeup" style="max-width: 800px; padding-top: 7rem;">
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
                    <button class="btn btn-secondary bottone" type="submit">Salva modifiche</button>
                </form>
            </div>
        </div>

        <div id="achievement-popup" class="popup">
            <img id="popup-image" src="" alt="Achievement" />
            <div>
                <h3 id="popup-title"></h3>
                <p id="popup-description"></p>
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
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>

        <script src="../js/modeChanger.js"></script>
    </body>
</html>
