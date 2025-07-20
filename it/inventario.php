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
        <link rel="icon" href="img/Susremaster.png" type="image/png" />
        <link rel="shortcut icon" href="img/Susremaster.png" type="image/png" />
        <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
        <link rel="stylesheet" href="../css/style.css" />
        <link rel="stylesheet" href="../css/style-dark.css" />
        <link rel="stylesheet" href="../css/animations.css" />
        <link rel="stylesheet" href="../css/inventario.css" />
        <script src="../js/animations.js"></script>
        <script src="../js/richpresence.js"></script>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Cripsum™ - inventario</title>
        <style>
            img {
                border-radius: 100px;
            }
        </style>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div class="paginainterachisiamo testobianco" style="padding-top: 7rem; text-align: center">
            <div class="container">
                <h1 class="fadeup">Il tuo Inventario</h1>
                <p class="fadeup" id="casseAperte"></p>
                <div id="counter" class="fadeup"></div>
                <div id="inventario" class="fadeup"></div>
                <a href="lootbox" class="linkbianco fadeup">Torna alla lootbox</a>
            </div>
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
        <script src="../js/characters.js"></script>
        <script>
            function getCookie(name) {
                const cookies = document.cookie.split("; ");
                for (let cookie of cookies) {
                    let [key, value] = cookie.split("=");
                    if (key === name) return JSON.parse(value);
                }
                return null;
            }

            const inventory = JSON.parse(localStorage.getItem("inventory")) || [];
            const inventarioDiv = document.getElementById("inventario");
            const counterDiv = document.getElementById("counter");
            const casseAperte = getCookie("casseAperte") || 0;

            const totalCharacters = rarities.length;
            const foundCharacters = inventory.length;

            document.getElementById("casseAperte").innerText = `Casse aperte: ${casseAperte}`;

            counterDiv.innerText = `Personaggi trovati: ${foundCharacters} / ${totalCharacters}`;

            const rarityOrder = ["comune", "raro", "epico", "leggendario", "mitico", "speciale"];

            rarityOrder.forEach((rarity) => {
                const section = document.createElement("div");
                section.classList.add("rarity-section", `rarity-${rarity}`);

                const foundInRarity = inventory.filter((p) => p.rarity === rarity).length;
                const totalInRarity = rarities.filter((p) => p.rarity === rarity).length;
                section.innerHTML = `<div class="rarity-title">${rarity.toUpperCase()}: ${foundInRarity} / ${totalInRarity}</div>`;

                const filteredCharacters = rarities.filter((p) => p.rarity === rarity);

                filteredCharacters.forEach((personaggio) => {
                    const character = inventory.find((p) => p.name === personaggio.name);
                    const count = character ? character.count : 0;

                    section.innerHTML += `
                <div class="personaggio">
                    <img src="../${character ? personaggio.img : "../img/boh.png"}" class="${character ? "" : "hidden"}" alt="Personaggio">
                    <span>${character ? `${personaggio.name} (x${count})` : "???"}</span>
                </div>
            `;
                });

                inventarioDiv.appendChild(section);
            });
        </script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
