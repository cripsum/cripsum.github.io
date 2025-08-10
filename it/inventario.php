<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per accedere all'inventario devi essere loggato";

    header('Location: accedi');
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <link rel="stylesheet" href="../css/inventario.css" />
        
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

        <div class="paginainterachisiamo testobianco" style="padding-top: 7rem; text-align: center; padding-bottom: 4rem;">
            <div class="container">
                <h1 class="fadeup">Il tuo Inventario</h1>
                <p class="fadeup" id="casseAperte"></p>
                <div id="counter" class="fadeup"></div>
                <div id="inventario" class="fadeup"></div>
                <a href="lootbox" class="linkbianco fadeup">Torna alla lootbox</a>
            </div>
        </div>

        <?php include '../includes/footer.php'; ?>
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

            async function initializeInventory() {
                const inventory = await getInventory() || [];
                const inventarioDiv = document.getElementById("inventario");
                const counterDiv = document.getElementById("counter");
                const casseAperteResponse = await fetch('https://cripsum.com/api/get_casse_aperte');
                const casseAperteData = await casseAperteResponse.json();
                const casseAperte = await casseAperteData.total;
                // const casseAperte = getCookie("casseAperte") || 0; // Old way, now using API


                const totalCharacters = await getCharactersNum(); // Assuming this function returns the total number of characters available
                const foundCharacters = inventory.length;

                document.getElementById("casseAperte").innerText = `Casse aperte: ${casseAperte}`;

                counterDiv.innerText = `Personaggi trovati: ${foundCharacters} / ${totalCharacters}`;

                const rarityOrder = ["comune", "raro", "epico", "leggendario", "mitico", "speciale"];

                rarityOrder.forEach((rarity) => {
                    const section = document.createElement("div");
                    section.classList.add("rarity-section", `rarity-${rarity}`);

                    const foundInRarity = inventory.filter((p) => p.rarità === rarity).length;
                    const totalInRarity = rarities.filter((p) => p.rarity === rarity).length;
                    section.innerHTML = `<div class="rarity-title">${rarity.toUpperCase()}: ${foundInRarity} / ${totalInRarity}</div>`;

                    const filteredCharacters = rarities.filter((p) => p.rarity === rarity);

                    filteredCharacters.forEach((personaggio) => {
                        const character = inventory.find((p) => p.nome === personaggio.name);
                        const count = character ? character.count : 0;

                    section.innerHTML += `
                    <div class="personaggio">
                        <img src="/img/${character ? character.img_url : "../img/boh.png"}" class="${character ? "" : "hidden"}" alt="Personaggio">
                        <span>${character ? `${character.nome} (x${character.quantità})` : "???"}</span>
                    </div>
                `;
                    });

                    inventarioDiv.appendChild(section);
                });
            }

            initializeInventory();

            async function getInventory() {
                const response = await fetch('https://cripsum.com/api/api_get_inventario');
                const data = await response.json();

                localStorage.setItem("inventory", JSON.stringify(data));
                return data;
            }

            async function getCharactersNum(){
                const response = await fetch('https://cripsum.com/api/api_get_characters_num');
                const data = await response.json();
                return data;
            }
        </script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
