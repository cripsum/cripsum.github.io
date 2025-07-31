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
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per accedere algi achievement devi essere loggato";

    header('Location: accedi');
    exit();
}

$userId = $_SESSION['user_id'];

checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <title>Cripsum™ - Achievements</title>
        <style>
            img {
                border-radius: 10px;
            }

            .achievement {
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 10px;
                padding: 10px;
                border: 1px solid #ccc;
                background: rgba(65, 65, 65, 0.153);
                border-radius: 10px;
            }
            .achievement img {
                width: 75px;
                height: 75px;
                margin-right: 10px;
            }
            .locked {
                filter: grayscale(100%) blur(3px);
                opacity: 0.5;
            }
        </style>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div class="paginainterachisiamo testobianco" style="padding-top: 7rem; text-align: center">
            <h1 class="fadeup">Achievements Sbloccati</h1>
            <p class="fadeup">N.B. gli achievement sono ancora in fase di creazione, pertanto sono ancora senza un icona, e molti non possono ancora essere sbloccati.</p>
            <div id="achievements-list" class="fadeup"></div>

            <script>
                function getCookie(name) {
                    let match = document.cookie.match(new RegExp("(^| )" + name + "=([^;]+)"));
                    return match ? JSON.parse(decodeURIComponent(match[2])) : [];
                }

                async function displayAchievements() {
                    // Fai il parsing dei dati
                    const unlockedRes = await fetch("https://cripsum.com/api/get_unlocked_achievement");
                    const allRes = await fetch("https://cripsum.com/api/get_all_achievement");

                    const unlockedAchievements = await unlockedRes.json();
                    const allAchievements = await allRes.json();

                    const container = document.getElementById("achievements-list");

                    allAchievements.forEach((ach) => {
                        const unlocked = unlockedAchievements.find(a => a.id === ach.id);
                        const isUnlocked = !!unlocked;

                        const div = document.createElement("div");
                        div.className = "achievement";
                        div.innerHTML = `
                            <img src="../img/${isUnlocked ? ach.img_url : ach.img_url}" class="${isUnlocked ? "" : "locked"}">
                            <div>
                                <h3>${isUnlocked ? ach.nome : "???"}</h3>
                                <p>${isUnlocked ? ach.descrizione : ach.descrizione}</p>
                                <span>${ach.punti} punti</span>
                                <p><small>${isUnlocked ? "Sbloccato il: " + new Date(unlocked.data).toLocaleDateString('it-IT') : ''}</small></p>
                            </div>
                        `;
                        container.appendChild(div);
                    });
                }

                displayAchievements();
            </script>
        </div>

        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item">
                    <a href="privacy" class="linkbianco">Privacy</a>
                </li>
                <li class="list-inline-item">
                    <a href="tos" class="linkbianco">Termini</a>
                </li>
                <li class="list-inline-item">
                    <a href="supporto" class="linkbianco">Supporto</a>
                </li>
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
