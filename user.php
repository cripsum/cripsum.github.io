<?php

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
checkBan($mysqli);

$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

$user_id = $_SESSION['user_id'] ?? null;
$username_session = $_SESSION['username'] ?? null;

if ($isLoggedIn) {
    $username = $_SESSION['username'] ?? 'Utente';
    $userId = $_SESSION['user_id'];
    $profilePic = "/includes/get_pfp.php?id=$userId";
    $ruolo = $_SESSION['ruolo'] ?? '';
}

$identifier = $_GET['username'] ?? $_GET['id'] ?? null;
if (!$identifier) {
    http_response_code(400);
    exit("Identificativo utente non specificato.");
}

$is_id = is_numeric($identifier);

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
    <?php include 'includes/head-import.php'; ?>
    <title>Cripsum™ - Profilo di <?php echo htmlspecialchars($user['username']); ?></title>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/impostazioni.php'; ?>

    <div class="container my-5 paginainterachisiamo testobianco" style="padding-top: 7rem">

        <div class="row mb-4 fadeup">
            <div class="col-12">
                <div class="card shadow-lg border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px;">
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

        <h1 class="mb-4 fadeup">Profilo di <?php echo htmlspecialchars($user['username']); ?></h1>

        <div class="row mb-4">
            <div class="col-md-4 text-center fadeup">
                
                <div style="width: 150px; height: 150px; border-radius: 50%; overflow: hidden; margin: 0 auto;" class="mb-3">
                    <img src="../includes/get_pfp.php?id=<?php echo $user_cercato_id; ?>" alt="Foto Profilo"
                        style="width: 100%; height: 100%; object-fit: cover; object-position: center;">
                </div>

                <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                <p>Membro dal: <?php echo date('d/m/Y', strtotime($user['data_creazione'])); ?></p>
                
                <div class="mt-3">
                    <button class="btn btn-sm btn-outline-primary" onclick="copyProfileLink('username')">
                        Copia link profilo
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="copyProfileLink('id')">
                        Copia link ID
                    </button>
                </div>
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
            <a href="../it/home" class="linkbianco">← Torna alla home</a>
        </div>
    </div>

    <script>
        function copyProfileLink(type) {
            const username = <?php echo json_encode($user['username']); ?>;
            const userId = <?php echo $user['id']; ?>;
            const baseUrl = window.location.origin;
            
            let url;
            if (type === 'username') {
                url = `${baseUrl}/user/${encodeURIComponent(username)}`;
            } else {
                url = `${baseUrl}/user?id=${userId}`;
            }
            
            navigator.clipboard.writeText(url).then(function() {
                alert('Link copiato negli appunti!');
            }, function(err) {
                console.error('Errore nel copiare il link: ', err);
                const textArea = document.createElement("textarea");
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    alert('Link copiato negli appunti!');
                } catch (err) {
                    alert('Impossibile copiare il link automaticamente. URL: ' + url);
                }
                document.body.removeChild(textArea);
            });
        }
    </script>

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