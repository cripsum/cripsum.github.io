<?php

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$username_session = $_SESSION['username'] ?? null;

// Identifica se è username o ID dalla URL
$identifier = $_GET['username'] ?? $_GET['id'] ?? null;
if (!$identifier) {
    http_response_code(400);
    exit("Identificativo utente non specificato.");
}

// Determina se l'identificativo è numerico (ID) o alfanumerico (username)
$is_id = is_numeric($identifier);

// Prepara la query in base al tipo di identificativo
if ($is_id) {
    $query = "SELECT u.id, u.username, u.data_creazione, u.soldi, u.ruolo,
        COUNT(DISTINCT ua.achievement_id) AS num_achievement,
        COUNT(DISTINCT up.personaggio_id) AS num_personaggi
        FROM utenti u
        LEFT JOIN utenti_achievement ua ON ua.utente_id = u.id
        LEFT JOIN utenti_personaggi up ON up.utente_id = u.id
        WHERE u.id = ?
        GROUP BY u.id";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $identifier);
} else {
    $query = "SELECT u.id, u.username, u.data_creazione, u.soldi, u.ruolo,
        COUNT(DISTINCT ua.achievement_id) AS num_achievement,
        COUNT(DISTINCT up.personaggio_id) AS num_personaggi
        FROM utenti u
        LEFT JOIN utenti_achievement ua ON ua.utente_id = u.id
        LEFT JOIN utenti_personaggi up ON up.utente_id = u.id
        WHERE u.username = ?
        GROUP BY u.id";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $identifier);
}

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
    // Reindirizza sempre all'username dopo la modifica
    header("Location: /user/" . urlencode($username));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <title>Profilo di <?php echo htmlspecialchars($user['username']); ?></title>
        <style>
            .navbarutenti {
                background: linear-gradient(135deg, rgba(125, 246, 255, 0.1), rgba(4, 87, 87, 0.1)); /* Sfondo trasparente */
            }
            .dropdownutenti .dropdown-menu {
                background: linear-gradient(135deg, rgba(0, 46, 56, 0.5), rgb(0, 37, 39)); /* Sfondo trasparente */
            }
        </style>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>

        <div class="bio-page">
            <div class="background">
                <!-- Use a video or an image as background -->
                <!-- Video example -->
                <video autoplay muted loop>
                    <source src="../vid/overlaymatta.mp4" type="video/mp4" />
                    Your browser does not support the video tag.
                </video>
                <!-- Image example -->
                <!-- <img src="your-image.jpg" alt="Background Image"> -->
            </div>

            <div class="bio-container fadeup" style="background: linear-gradient(135deg, rgba(125, 246, 255, 0.1), rgba(4, 87, 87, 0.1))">
                <img src="../img/pfp choso2 cc.png" alt="" class="immaginechisiamo ombra bio-pfp" style="filter: none" />
                <h1 class="arcobalenos mt-2" style="font-weight: bolder; text-shadow: 0 0 25px rgba(255, 255, 255, 0.7), 0 0 15px rgba(255, 255, 255, 0.5)"><?php echo htmlspecialchars($user['username']); ?></h1>
                <p class="mb-2" style="color: rgb(171, 171, 171)">AKA - sofficino alla pesca</p>
                <p>Editor scaduto, ha speso tutti i suoi risparmi in brawl pass e ora non può permettersi la patente</p>
                    <div class="row mb-4">
                        <div class="col-md-4 text-center fadeup">
                            <div style="width: 150px; height: 150px; border-radius: 50%; overflow: hidden; margin: 0 auto;" class="mb-3">
                                <img src="../includes/get_pfp.php?id=<?php echo $user_id; ?>" alt="Foto Profilo" style="width: 100%; height: 100%; object-fit: cover;">
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
                <div class="social-media">
                    <a href="https://tiktok.cripsum.com" target="_blank" class="linkbianco">TikTok</a>
                    <a href="https://t.me/sburragrigliata" target="_blank" class="linkbianco">Telegram</a>
                    <a href="https://discord.cripsum.com" target="_blank" class="linkbianco">Discord</a>
                </div>
            </div>
        </div>
</div>


        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
