<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per accedere alla pagina del tuo profilo devi essere loggato";

    header('Location: accedi');
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $username = $mysqli->real_escape_string($_POST['username']);
    $pfp_blob = null;
    $pfp_mime = null;

    if (isset($_FILES['pfp']) && $_FILES['pfp']['error'] === 0) {
        $pfp_blob = file_get_contents($_FILES['pfp']['tmp_name']);
        $pfp_mime = mime_content_type($_FILES['pfp']['tmp_name']);
    }

    if ($pfp_blob !== null && $pfp_mime !== null) {
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
}

$stmt = $mysqli->prepare("
    SELECT 
        u.username,
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
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Profilo</title>
    <style>
        .card:hover {
            transform: translateY(0px) scale(0);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-color: rgba(255, 255, 255, 0.12);
        }

    </style>
</head>
<body >

    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="container my-5 paginainterachisiamo testobianco" style="padding-top: 5rem; padding-bottom: 4rem;">
        <div class="row mb-4 fadeup">
            <div class="col-12">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3 text-white d-flex align-items-center">
                            <i class="fas fa-users me-2"></i>
                            Cerca Utenti
                        </h5>
                        <div class="input-group mb-2">
                            <input 
                                type="text" 
                                class="form-control border-0 shadow-sm" 
                                id="userSearch" 
                                placeholder="Inserisci username..."
                                maxlength="50"
                                style="border-radius: 50px 0 0 50px; padding: 12px 20px; font-size: 16px;"
                            >
                            <button 
                                type="button" 
                                class="btn btn-light px-4 shadow-sm" 
                                onclick="searchUser()"
                                style="border-radius: 0 50px 50px 0; font-weight: 600; transition: all 0.3s ease;"
                                onmouseover="this.style.transform='translateY(-2px)'"
                                onmouseout="this.style.transform='translateY(0)'"
                            >
                                <i class="fas fa-search me-1"></i>
                                Cerca
                            </button>
                        </div>
                        <small class="text-white-50 d-flex align-items-center">
                            <i class="fas fa-info-circle me-1"></i>
                            Scopri altri profili della community
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <h1 class="mb-4 fadeup">Il Mio Profilo</h1>

        <div class="row mb-4">
            <div class="col-md-4 text-center fadeup">
                    <div style="width: 150px; height: 150px; border-radius: 50%; overflow: hidden; margin: 0 auto;" class="mb-3">
                        <img src="../includes/get_pfp.php?id=<?php echo $user_id; ?>" alt="Foto Profilo"
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

        <h4 class="mb-3 fadeup">Modifica Profilo</h4>
        <form method="POST" enctype="multipart/form-data" class="fadeup">
            <input type="hidden" name="action" value="update_profile">

            <div class="mb-3">
                <label for="username" class="form-label">Nome utente</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>

            <div class="mb-3">
                <label for="pfp" class="form-label">Immagine profilo</label>
                <input type="file" class="form-control" id="pfp" name="pfp" accept="image/*" onchange="unlockAchievement(2)">
            </div>
            <button class="btn btn-secondary bottone" type="submit">Salva modifiche</button>
        </form>

        <div class="mt-4 fadeup">
            <a href="home" class="linkbianco">← Torna alla home</a>
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
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>

        <script src="../js/modeChanger.js"></script>
</body>
</html>