<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: accedi.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle profile update
if ($_POST['action'] === 'update_profile') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    
    // Handle profile picture upload
    $pfp_path = null;
    if (isset($_FILES['pfp']) && $_FILES['pfp']['error'] === 0) {
        $upload_dir = '../uploads/profiles/';
        $file_extension = pathinfo($_FILES['pfp']['name'], PATHINFO_EXTENSION);
        $pfp_filename = $user_id . '_' . time() . '.' . $file_extension;
        $pfp_path = $upload_dir . $pfp_filename;
        
        if (move_uploaded_file($_FILES['pfp']['tmp_name'], $pfp_path)) {
            $update_pfp = ", profile_pic = '$pfp_path'";
        }
    }
    
    $query = "UPDATE users SET username = '$username' $update_pfp WHERE id = $user_id";
    mysqli_query($conn, $query);
}

// Get user data and stats
$user_query = "SELECT username, profile_pic, data_creazione, soldi, ruolo, COUNT(achievement_id) as num_achievement, COUNT(personaggio_id) as num_personaggi FROM utenti, utenti_achievement, utenti_personaggi WHERE utenti.id = $user_id AND utenti_achievement.utente_id = utenti.id AND utenti_personaggi.utente_id = utenti.id AND utenti_achievement.achievement_id = achievement.id AND utenti_personaggi.personaggio_id = personaggi.id GROUP BY utenti.id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);
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
    <title>Il Mio Profilo - Cripsum</title>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>


    <div class="container">
        <h1>Il Mio Profilo</h1>
        
        <div class="profile-section">
            <div class="profile-info">
                <div class="profile-picture">
                    <img src="<?php echo $user['profile_picture'] ?: '../images/default-avatar.png'; ?>" alt="Profile Picture">
                </div>
                <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                <p>Membro dal: <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
            </div>
            
            <div class="stats">
                <h3>Statistiche</h3>
                <div class="stat-item">
                    <span>Obiettivi Sbloccati:</span>
                    <span><?php echo $achievements['total_achievements']; ?></span>
                </div>
                <div class="stat-item">
                    <span>Personaggi Sbloccati:</span>
                    <span><?php echo $characters['total_characters']; ?></span>
                </div>
            </div>
        </div>
        
        <div class="edit-profile">
            <h3>Modifica Profilo</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label for="username">Nome Utente:</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="pfp">Immagine Profilo:</label>
                    <input type="file" id="pfp" name="pfp" accept="image/*">
                </div>
                
                <button type="submit">Aggiorna Profilo</button>
            </form>
        </div>
        
        <a href="home.php" class="link-bianco">Torna alla home</a>
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