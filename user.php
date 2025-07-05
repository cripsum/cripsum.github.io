<?php

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$username_session = $_SESSION['username'] ?? null;

// Username preso dalla URL riscritta
$username_url = $_GET['username'] ?? null;
if (!$username_url) {
    http_response_code(400);
    exit("Username non specificato.");
}

// Recupera utente dal database
$stmt = $mysqli->prepare("SELECT u.id, u.username, u.data_creazione, u.soldi, u.ruolo,
    COUNT(DISTINCT ua.achievement_id) AS num_achievement,
    COUNT(DISTINCT up.personaggio_id) AS num_personaggi
    FROM utenti u
    LEFT JOIN utenti_achievement ua ON ua.utente_id = u.id
    LEFT JOIN utenti_personaggi up ON up.utente_id = u.id
    WHERE u.username = ?
    GROUP BY u.id");
$stmt->bind_param("s", $username_url);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit("Utente non trovato.");
}

$user = $result->fetch_assoc();
$stmt->close();

$user_cercato_id = $user['id'];
$is_own_profile = $isLoggedIn && $user_cercato_id == $user_id;

// Modifica profilo se è il proprio e c'è invio form
if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $username = $mysqli->real_escape_string($_POST['username']);
    $pfp_blob = null;
    $pfp_mime = null;

    if (isset($_FILES['pfp']) && $_FILES['pfp']['error'] === 0) {
        $pfp_blob = file_get_contents($_FILES['pfp']['tmp_name']);
        $pfp_mime = mime_content_type($_FILES['pfp']['tmp_name']);
    }

    if ($pfp_blob && $pfp_mime) {
        $stmt = $mysqli->prepare("UPDATE utenti SET username = ?, profile_pic = ?, profile_pic_type = ? WHERE id = ?");
        $null = NULL;
        $stmt->bind_param("sbsi", $username, $null, $pfp_mime, $user_id);
        $stmt->send_long_data(1, $pfp_blob);
    } else {
        $stmt = $mysqli->prepare("UPDATE utenti SET username = ? WHERE id = ?");
        $stmt->bind_param("si", $username, $user_id);
    }

    $stmt->execute();
    $stmt->close();

    $_SESSION['username'] = $username;
    header("Location: /user/" . urlencode($username));
    exit;
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
        <meta charset="UTF-8" />
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
            crossorigin="anonymous"
        />
        <link rel="icon" href="../img/Susremaster.png" type="image/png" />
        <link rel="shortcut icon" href="../img/Susremaster.png" type="image/png" />
        <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
        <link rel="stylesheet" href="../css/style.css" />
        <link rel="stylesheet" href="../css/style-dark.css" />
        <link rel="stylesheet" href="../css/animations.css" />
        <link rel="stylesheet" href="../css/achievement-style.css" />
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script src="../js/animations.js"></script>

        <script src="../js/controlloLingua-it.js"></script>
        <script src="../js/controlloTema.js"></script>
        <script src="../js/unlockAchievement-it.js"></script>
        <script src="../js/achievements-globali.js"></script>
        <script src="../js/richpresence.js"></script>

        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cripsum™ - Profilo</title>
</head>
<body >

    <?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

if ($isLoggedIn) {
    $username = $_SESSION['username'] ?? 'Utente';
    $userId = $_SESSION['user_id'];
    $profilePic = "/includes/get_pfp.php?id=$userId";
    $ruolo = $_SESSION['ruolo'] ?? '';
}
?>

<nav class="navbarutenti navbar navbar-expand-xl fadein">
    <div class="container-fluid">
        <a class="navbar-brand" href="home">
            <img src="../img/amongus.jpg" height="40px" style="border-radius: 4px" class="d-inline-block align-middle" />
            <span class="align-middle ms-3 fw-bold testobianco">Cripsum™</span>
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarSupportedContent"
            aria-controls="navbarSupportedContent"
            aria-expanded="false"
            aria-label="Toggle navigation"
            style="z-index: 1000"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="../it/home">Home page</a></li>
                <li class="nav-item dropdown dropdownutenti">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Memes</a>
                    <ul class="dropdown-menu animate slideIn">
                        <li><a class="dropdown-item" href="../it/shitpost">Shitpost</a></li>
                        <li><a class="dropdown-item" href="../it/tiktokpedia">TikTokPedia</a></li>
                        <li><a class="dropdown-item" href="../it/rimasti">Top rimasti</a></li>
                        <li><a class="dropdown-item" href="../it/quandel57" style="color: red; font-weight: bold">Quandel57</a></li>
                        <li><a class="dropdown-item arcobalenos" href="../it/gambling" style="font-weight: bold">Gambling!!</a></li>
                        <li><a class="dropdown-item testo-arcobaleno" href="../it/lootbox" style="font-weight: bold">Lootbox</a></li>
                        <li><a class="dropdown-item" href="../it/achievements">Achievements</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link" href="../it/download">Downloads</a></li>
                <li class="nav-item dropdown dropdownutenti">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Shop</a>
                    <ul class="dropdown-menu animate slideIn">
                        <li><a class="dropdown-item" href="../it/negozio">Negozio</a></li>
                        <li><a class="dropdown-item" href="../it/merch">Merch</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link" href="../it/donazioni">Donazioni</a></li>
                <li class="nav-item"><a class="nav-link" href="../it/chisiamo">Chi siamo</a></li>
                <li class="nav-item"><a class="nav-link" href="../it/edits">Edits</a></li>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if (!$isLoggedIn): ?>
                    <li class="nav-item"><a class="nav-link" href="../it/accedi">Accedi</a></li>
                    <li class="nav-item"><a class="nav-link" href="../it/registrati">Registrati</a></li>
                <?php else: ?>
                    <li class="nav-item dropdown dropdownutenti">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="../it/Profilo" 
                                 class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;">
                            <span><?php echo htmlspecialchars($username); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end animate slideIn">
                            <li><a class="dropdown-item" href="../it/profilo"><i class="fas fa-user me-2"></i>Il mio profilo</a></li>
                            <li><a class="dropdown-item" href="../it/impostazioni"><i class="fas fa-cog me-2"></i>Impostazioni</a></li>
                            <li><a class="dropdown-item" href="../it/ordini"><i class="fas fa-shopping-bag me-2"></i>I miei ordini</a></li>
                            <?php if ($ruolo === 'admin'): ?>
                                <li><a class="dropdown-item" href="../it/admin"><i class="fas fa-shield-alt me-2"></i>Pannello Admin</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="https://cripsum.com/logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="btn-group ms-auto me-3 linguanuova">
            <button type="button" class="btn impostazioni-toggler" data-bs-toggle="modal" data-bs-target="#impostazioniModal">
                <img src="../img/settings-icon.svg" alt="Impostazioni" style="width: 25px" class="imgbianca" />
            </button>
        </div>
    </div>
</nav>


    <div class="container my-5 paginainterachisiamo testobianco" style="padding-top: 7rem">
        <h1 class="mb-4 fadeup">Profilo di <?php echo htmlspecialchars($user['username']); ?></h1>

        <div class="row mb-4">
            <div class="col-md-4 text-center fadeup">
                <img src="../includes/get_pfp.php?id=<?php echo $user_cercato_id; ?>" alt="Foto Profilo" class="img-fluid rounded-circle mb-3" style="max-width: 150px;">

                <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                <p>Membro dal: <?php echo date('d/m/Y', strtotime($user['data_creazione'])); ?></p>
            </div>

            <div class="col-md-8 fadeup">
                <h4>Statistiche</h4>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Obiettivi sbloccati</span>
                        <strong><?php echo $user['num_achievement']; ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Personaggi trovati</span>
                        <strong><?php echo $user['num_personaggi']; ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Soldi</span>
                        <strong><?php echo $user['soldi']; ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Ruolo</span>
                        <strong><?php echo htmlspecialchars($user['ruolo']); ?></strong>
                    </li>
                </ul>
            </div>
        </div>



        <?php if ($user_cercato_id == $_SESSION['user_id']): ?>
        <h4 class="mb-3 fadeup">Modifica Profilo</h4>
        <form method="POST" enctype="multipart/form-data" class="fadeup">
            <input type="hidden" name="action" value="update_profile">

            <div class="mb-3">
            <label for="username" class="form-label">Nome utente</label>
            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>

            <div class="mb-3">
            <label for="pfp" class="form-label">Immagine profilo</label>
            <input type="file" class="form-control" id="pfp" name="pfp" accept="image/*">
            </div>
            <button class="btn btn-secondary bottone" type="submit">Salva modifiche</button>
        </form>
        <?php endif; ?>

        <div class="mt-4 fadeup">
            <a href="../it/home.php" class="linkbianco">← Torna alla home</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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
                <li class="list-inline-item"><a href="../it/privacy" class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="../it/tos" class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="../it/supporto" class="linkbianco">Supporto</a></li>
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