<?php

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$username_session = $_SESSION['username'] ?? null;

// Identifica se è username o ID dalla URL
$identifier = "cripsum";

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

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit("Utente non trovato.");
}

$user = $result->fetch_assoc();
$stmt->close();
$user_cercato_id = $user['id'];
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <link rel="stylesheet" href="../css/style-users.css" />
        <title>Profilo di <?php echo htmlspecialchars($user['username']); ?></title>
        <style>
            .navbarutenti {
                background: linear-gradient(135deg, rgba(125, 246, 255, 0.1), rgba(4, 87, 87, 0.1)); /* Sfondo trasparente */
            }
            .dropdownutenti .dropdown-menu {
                background: linear-gradient(135deg, rgba(0, 46, 56, 0.5), rgb(0, 37, 39)); /* Sfondo trasparente */
            }

            .list-group-item {
                background: linear-gradient(135deg, rgba(125, 246, 255, 0), rgba(4, 87, 87, 0)); /* Sfondo trasparente */
            }

            .list-group{
                background: linear-gradient(135deg, rgba(125, 246, 255, 0), rgba(4, 87, 87, 0)); /* Sfondo trasparente */
            }
            .discord-box {
                margin: 2rem auto 0 auto;
                width: 100%;
                max-width: 400px;
                display: block;
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
                <div style="width: 150px; height: 150px; border-radius: 50%; overflow: hidden; margin: 0 auto;" class="mb-3">
                    <img src="../includes/get_pfp.php?id=<?php echo $user_cercato_id; ?>" alt="Foto Profilo" class="img-fluid rounded-circle mb-3">
                </div>
                <h1 class="testo-arcobaleno mt-2" style="font-weight: bolder; text-shadow: 0 0 25px rgba(255, 255, 255, 0.7), 0 0 15px rgba(255, 255, 255, 0.5)"><?php echo htmlspecialchars($user['username']); ?></h1>
                <p class="mb-2" style="color: rgb(171, 171, 171)">AKA - sofficino alla pesca</p>
                <p>Editor scaduto, ha speso tutti i suoi risparmi in brawl pass e ora non può permettersi la patente</p>    
                            <p>Membro dal: <?php echo date('d/m/Y', strtotime($user['data_creazione'])); ?></p>

                            <h4 style="margin-bottom: 10px;">Statistiche</h4>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span style="margin-left: 3%;">Obiettivi sbloccati</span>
                                    <strong style="margin-right: 3%;"><?php echo $user['num_achievement']; ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span style="margin-left: 3%;">Personaggi trovati</span>
                                    <strong style="margin-right: 3%;"><?php echo $user['num_personaggi']; ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span style="margin-left: 3%;">Soldi</span>
                                    <strong style="margin-right: 3%;"><?php echo $user['soldi']; ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span style="margin-left: 3%;">Ruolo</span>
                                    <strong style="margin-right: 3%;"><?php echo htmlspecialchars($user['ruolo']); ?></strong>
                                </li>
                            </ul>
                            <div class="discord-box fadeup" id="discordBox" style="margin: auto; text-align: center;">
                                <?php include '../includes/discord_status.php'; ?>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-sm btn-outline-primary" onclick="copyProfileLink('username')">
                                    Copia link profilo
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="copyProfileLink('id')">
                                    Copia link ID
                                </button>
                            </div>
                <div class="social-media" style="margin-top: 20px;">
                    <a href="https://tiktok.cripsum.com" target="_blank" class="linkbianco">TikTok</a>
                    <a href="https://t.me/sburragrigliata" target="_blank" class="linkbianco">Telegram</a>
                    <a href="https://discord.cripsum.com" target="_blank" class="linkbianco">Discord</a>
                </div>
            </div>
        </div>
        <script>
            setInterval(() => {
                fetch('../includes/discord_status.php')
                    .then(r => r.text())
                    .then(html => {
                        const discordBox = document.querySelector('.discord-box');
                        if (discordBox) {
                            discordBox.innerHTML = html;
                            // Reinizializza il carousel dopo l'aggiornamento
                            initActivityCarousel();
                        }
                    })
                    .catch(err => console.error('Errore aggiornamento Discord status:', err));
            }, 30000);

            // Funzione per inizializzare il carousel delle attività
            function initActivityCarousel() {
                const slides = document.querySelectorAll(".activity-item");
                if (slides.length <= 1) return;

                let current = 0;

                // Nascondere tutti tranne il primo
                slides.forEach((slide, index) => {
                    if (index !== 0) {
                        slide.style.display = "none";
                    }
                });

                // Carousel automatico
                setInterval(() => {
                    if (slides.length > 1) {
                        slides[current].style.display = "none";
                        current = (current + 1) % slides.length;
                        slides[current].style.display = "flex";
                    }
                }, 4000); // Cambia ogni 4 secondi
            }

            // Inizializza il carousel al caricamento della pagina
            document.addEventListener("DOMContentLoaded", () => {
                initActivityCarousel();
            });

            // Funzioni esistenti per copiare i link del profilo
            function copyProfileLink(type) {
                const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '');
                let url;
                
                if (type === 'username') {
                    url = `${baseUrl}/cripsum`;
                } else {
                    url = `${baseUrl}/user/<?php echo $user_cercato_id; ?>`;
                }
                
                navigator.clipboard.writeText(url).then(() => {
                    // Feedback visuale opzionale
                    const button = event.target;
                    const originalText = button.textContent;
                    button.textContent = 'Copiato!';
                    button.classList.add('btn-success');
                    
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.classList.remove('btn-success');
                    }, 2000);
                }).catch(err => {
                    console.error('Errore nella copia:', err);
                    // Fallback per browser più vecchi
                    const textArea = document.createElement('textarea');
                    textArea.value = url;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                });
            }
            </script>

        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
