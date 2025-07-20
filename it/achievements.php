<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
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
        <script src="../js/animations.js"></script>
        <script src="../js/richpresence.js"></script>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
                    const response = await fetch("../data/achievements-it.json");
                    const achievements = await response.json();
                    const unlockedAchievements = getCookie("achievements");
                    const container = document.getElementById("achievements-list");

                    achievements.forEach((ach) => {
                        const isUnlocked = unlockedAchievements.includes(ach.id);
                        const div = document.createElement("div");
                        div.className = "achievement";
                        div.innerHTML = `
                    <img src="${ach.image}" class="${isUnlocked ? "" : "locked"}">
                    <div>
                        <h3>${isUnlocked ? ach.title : "???"}</h3>
                        <p>${isUnlocked ? ach.description : "???"}</p>
                        <span>${ach.points} punti</span>
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
