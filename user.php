<?php
require_once 'config/session_init.php';
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
    header("Location: /user/" . urlencode(strtolower($username)));
    exit;
}

if (isUserOnline($mysqli, $user_cercato_id)) {
    $is_online = true;
} else {
    $is_online = false;
    $ultimo_accesso = getUltimoAccesso($mysqli, $user_cercato_id);
}
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <?php include 'includes/head-import.php'; ?>
    <title>Cripsum™ - Profilo di <?php echo htmlspecialchars($user['username']); ?></title>
    <style>
        .card:hover {
            transform: translateY(0px) scale(1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-color: rgba(255, 255, 255, 0.12);
        }

        .card:hover::before {
            opacity: 0;
        }
    </style>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/impostazioni.php'; ?>

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
                                style="border-radius: 50px 0 0 50px; padding: 12px 20px; font-size: 16px;">
                            <button
                                type="button"
                                class="btn btn-light px-4 shadow-sm"
                                onclick="searchUser()"
                                style="border-radius: 0 50px 50px 0; font-weight: 600; transition: all 0.3s ease;">
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

                <div style="width: 150px; height: 150px; border-radius: 50%; overflow: hidden; margin: 0 auto; position: relative;" class="mb-3">
                    <img src="../includes/get_pfp.php?id=<?php echo $user_cercato_id; ?>&t=<?php echo time(); ?>" alt="Foto Profilo"
                        style="width: 100%; height: 100%; object-fit: cover; object-position: center;">
                </div>

                <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                <p>Membro dal: <?php echo date('d/m/Y', strtotime($user['data_creazione'])); ?></p>
                <?php if ($is_online): ?>
                    <div style="background-color: #28a745; color: white; padding: 2px 2px; border-radius: 5px; font-size: 15px; font-weight: bold;">
                        Online
                    </div>
                <?php else: ?>
                    <div style="background-color: #6c757d; color: white; padding: 2px 2px; border-radius: 5px; font-size: 15px;">
                        Ultimo accesso: <?php
                                        if ($ultimo_accesso) {
                                            $date = new DateTime($ultimo_accesso);
                                            $date->setTimezone(new DateTimeZone('Europe/Rome'));
                                            echo $date->format('d/m/Y H:i');
                                        } else {
                                            echo 'Sconosciuto';
                                        }
                                        ?>
                    </div>
                <?php endif; ?>

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
            <a class="btn btn-secondary bottone mt-2 fadeup" href="/it/impostazioni" style="cursor: pointer">Modifica Profilo</a>
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
    <?php include 'includes/footer.php'; ?>
    <script>
        function searchUser() {
            const username = document.getElementById('userSearch').value.trim();
            if (username) {
                window.location.href = `../user/${encodeURIComponent(username.toLowerCase())}`;
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
        crossorigin="anonymous"></script>

    <script src="../js/modeChanger.js"></script>
</body>

</html>