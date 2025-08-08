<?php

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$username_url = $_GET['username'] ?? null;
if (!$username_url) {
    http_response_code(400);
    exit('Username mancante.');
}

// Dati utente cercato
$stmt = $mysqli->prepare("
    SELECT 
        u.id,
        u.username,
        u.data_creazione,
        u.soldi,
        u.ruolo,
        COUNT(DISTINCT ua.achievement_id) AS num_achievement,
        COUNT(DISTINCT up.personaggio_id) AS num_personaggi
    FROM utenti u
    LEFT JOIN utenti_achievement ua ON ua.utente_id = u.id
    LEFT JOIN utenti_personaggi up ON up.utente_id = u.id
    WHERE u.username = ?
    GROUP BY u.id
");
$stmt->bind_param("s", $username_url);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit("Utente non trovato.");
}

$user = $result->fetch_assoc();
$user_cercato_id = $user['id'];
$is_own_profile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_cercato_id;
$stmt->close();

// Gestione modifica profilo
if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $nuovo_username = $mysqli->real_escape_string($_POST['username']);
    $pfp_blob = null;
    $pfp_type = null;

    if (isset($_FILES['pfp']) && $_FILES['pfp']['error'] === 0) {
        $pfp_blob = file_get_contents($_FILES['pfp']['tmp_name']);
        $pfp_type = mime_content_type($_FILES['pfp']['tmp_name']);
    }

    if ($pfp_blob && $pfp_type) {
        $stmt = $mysqli->prepare("UPDATE utenti SET username = ?, profile_pic = ?, profile_pic_type = ? WHERE id = ?");
        $null = null;
        $stmt->bind_param("sbsi", $nuovo_username, $null, $pfp_type, $user_cercato_id);
        $stmt->send_long_data(1, $pfp_blob);
    } else {
        $stmt = $mysqli->prepare("UPDATE utenti SET username = ? WHERE id = ?");
        $stmt->bind_param("si", $nuovo_username, $user_cercato_id);
    }

    $stmt->execute();
    $stmt->close();

    // Aggiorna sessione
    $_SESSION['username'] = $nuovo_username;

    // Redirect per aggiornare URL se username cambia
    header("Location: /user/" . urlencode($nuovo_username));
    exit();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <title>Profilo di <?php echo htmlspecialchars($user['username']); ?></title>
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
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container my-5 paginainterachisiamo testobianco" style="padding-top: 7rem">
        <div class="row mb-4 fadeup">
            <div class="col-12">
                <div class="card bg-dark border-secondary">
                    <div class="card-body">
                        <h5 class="card-title">Cerca un profilo</h5>
                        <input type="text" class="form-control" id="userSearch" placeholder="Inserisci username o ID utente" required>
                        <button type="submit" class="btn btn-primary" onclick="searchUser()">Cerca</button>
                    </div>
                </div>
            </div>
        </div>
    <h1 class="mb-4 fadeup">Cripsum™ - Profilo di <?php echo htmlspecialchars($user['username']); ?></h1>

    <div class="row mb-4">
        <div class="col-md-4 text-center fadeup">
                <div style="width: 150px; height: 150px; border-radius: 50%; overflow: hidden; margin: 0 auto;" class="mb-3">
                    <img src="../includes/get_pfp.php?id=<?php echo $user_cercato_id; ?>" alt="Foto Profilo"
                        style="width: 100%; height: 100%; object-fit: cover; object-position: center;">
                </div>
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

    <?php if ($is_own_profile): ?>
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
        <a href="/home" class="linkbianco">← Torna alla home</a>
    </div>
</div>
                <script>
                function searchUser() {
                    const username = document.getElementById('userSearch').value.trim();
                    if (username) {
                        window.location.href = `../user/${encodeURIComponent(username)}`;
                    } else {
                        alert('Inserisci un nome utente per continuare');
                    }
                }

                document.getElementById('userSearch').addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        searchUser();
                    }
                });
                </script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/modeChanger.js"></script>
</body>
</html>
