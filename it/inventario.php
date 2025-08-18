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
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div class="inventory-container">
            <div class="inventory-header">
                <h1 class="inventory-title">Il tuo Inventario</h1>
            </div>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number" id="casseAperteNumber">0</div>
                    <div class="stat-label">Casse Aperte</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="foundCharacters">0</div>
                    <div class="stat-label">Personaggi Trovati</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="totalCharacters">0</div>
                    <div class="stat-label">Personaggi Totali</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="completionRate">0%</div>
                    <div class="stat-label">Completamento</div>
                </div>
            </div>

            <div class="inventory-grid" id="inventario">
               
            </div>

            <div style="text-align: center; margin-bottom: 3rem;">
                <a href="lootbox" class="back-button">
                    <span class="back-arrow">←</span>
                    Torna alla lootbox
                </a>
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
                const casseAperteResponse = await fetch('https://cripsum.com/api/get_casse_aperte');
                const casseAperteData = await casseAperteResponse.json();
                const casseAperte = await casseAperteData.total;

                const totalCharacters = await getCharactersNum();
                const foundCharacters = inventory.length;
                const completionRate = totalCharacters > 0 ? Math.round((foundCharacters / totalCharacters) * 100) : 0;

                animateNumber(document.getElementById("casseAperteNumber"), casseAperte);
                animateNumber(document.getElementById("foundCharacters"), foundCharacters);
                animateNumber(document.getElementById("totalCharacters"), totalCharacters);
                animateNumber(document.getElementById("completionRate"), completionRate, "%");

                const rarityOrder = ["comune", "raro", "epico", "leggendario", "mitico", "speciale"];

                rarityOrder.forEach((rarity, index) => {
                    const section = document.createElement("div");
                    section.classList.add("rarity-section", `rarity-${rarity}`);
                    section.style.animationDelay = `${0.1 * index}s`;

                    const foundInRarity = inventory.filter((p) => p.rarità === rarity).length;
                    const totalInRarity = rarities.filter((p) => p.rarity === rarity).length;
                    
                    const titleDiv = document.createElement("div");
                    titleDiv.classList.add("rarity-title");
                    titleDiv.textContent = `${rarity.toUpperCase()}: ${foundInRarity} / ${totalInRarity}`;
                    section.appendChild(titleDiv);

                    const charactersGrid = document.createElement("div");
                    charactersGrid.classList.add("characters-grid");

                    const filteredCharacters = rarities.filter((p) => p.rarity === rarity);

                    filteredCharacters.forEach((personaggio) => {
                        const character = inventory.find((p) => p.nome === personaggio.name);
                        const characterCard = document.createElement("div");
                        characterCard.classList.add("character-card");
                        
                        if (!character) {
                            characterCard.classList.add("hidden-character");
                        }

                        characterCard.innerHTML = `
                            <img src="${character ? `/img/${character.img_url}` : "../img/boh.png"}" 
                                 class="character-image" 
                                 alt="Personaggio">
                            <div class="character-name">${character ? character.nome : "???"}</div>
                            <div class="character-count">${character ? `x${character.quantità}` : "Non trovato"}</div>
                            <div class="character-unlock-date">${character ? `Trovato il: ${new Date(character.data).toLocaleDateString()}` : "Non trovato"}</div>
                            <div class="character-unlock-date">${character ? `Alle ${new Date(character.data).toLocaleTimeString('it-IT')}` : ""}</div>
                        `;

                        charactersGrid.appendChild(characterCard);
                    });

                    section.appendChild(charactersGrid);
                    inventarioDiv.appendChild(section);
                });
            }

            function animateNumber(element, targetNumber, suffix = "") {
                let currentNumber = 0;
                const increment = targetNumber / 50;
                const timer = setInterval(() => {
                    currentNumber += increment;
                    if (currentNumber >= targetNumber) {
                        currentNumber = targetNumber;
                        clearInterval(timer);
                    }
                    element.textContent = Math.floor(currentNumber) + suffix;
                }, 30);
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