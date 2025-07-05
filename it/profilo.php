<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: accedi.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Aggiorna profilo se inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_profile') {
    $username = $mysqli->real_escape_string($_POST['username']);
    $update_pfp = "";

    if (isset($_FILES['pfp']) && $_FILES['pfp']['error'] === 0) {
        $upload_dir = '../uploads/profiles/';
        $file_extension = pathinfo($_FILES['pfp']['name'], PATHINFO_EXTENSION);
        $pfp_filename = "{$user_id}_" . time() . ".{$file_extension}";
        $pfp_path = $upload_dir . $pfp_filename;

        if (move_uploaded_file($_FILES['pfp']['tmp_name'], $pfp_path)) {
            $update_pfp = ", profile_pic = '{$pfp_path}'";
        }
    }

    $query = "UPDATE utenti SET username = '{$username}' {$update_pfp} WHERE id = {$user_id}";
    $mysqli->query($query);
}

// Recupera dati utente + statistiche
$stmt = $mysqli->prepare("
    SELECT 
        u.username,
        u.profile_pic,
        u.data_creazione,
        u.soldi,
        u.ruolo,
        COUNT(DISTINCT ua.achievement_id) AS num_achievement,
        COUNT(DISTINCT up.personaggio_id) AS num_personaggi
    FROM utenti u
    LEFT JOIN utenti_achievement ua ON ua.utente_id = u.id
    LEFT JOIN utenti_personaggi up ON up.utente_id = u.id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
        <!-- Google tag (gtag.js) -->
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

    <?php include '../includes/navbar.php'; ?>

    <div class="container my-5 paginainterachisiamo testobianco" style="padding-top: 7rem">
        <h1 class="mb-4">Il Mio Profilo</h1>

        <div class="row mb-4">
            <div class="col-md-4 text-center">
                <img src="<?php echo $user['profile_pic'] ?: '../images/default-avatar.png'; ?>" alt="Foto Profilo" class="img-fluid rounded-circle mb-3" style="max-width: 150px;">
                <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                <p>Membro dal: <?php echo date('d/m/Y', strtotime($user['data_creazione'])); ?></p>
            </div>

            <div class="col-md-8">
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

        <h4 class="mb-3">Modifica Profilo</h4>
        <form method="POST" enctype="multipart/form-data">
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

        <div class="mt-4">
            <a href="home.php" class="linkbianco">← Torna alla home</a>
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