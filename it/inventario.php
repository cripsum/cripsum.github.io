<?php
require_once '../config/session_init.php';
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
    <link rel="stylesheet" href="../css/inventario.css?v=3" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cripsum™ - inventario</title>
    <style>
        .character-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease-in-out;
        }

        .character-modal.closing {
            animation: fadeOut 0.3s ease-in-out forwards;
        }

        .modal-content {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            border-radius: 15px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            border: 2px solid #3d5a80;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            animation: slideIn 0.3s ease-out;
        }

        .modal-content.closing {
            animation: slideOut 0.3s ease-in forwards;
        }

        .modal-content.rarity-comune {
            background: linear-gradient(135deg, #2c2c2c, #1a1a1a);
            border: 2px solid #666;
        }

        .modal-content.rarity-raro {
            background: linear-gradient(135deg, #1e3a5f, #0f2849);
            border: 2px solid #4a90e2;
        }

        .modal-content.rarity-epico {
            background: linear-gradient(135deg, #4a1a5f, #2d0f49);
            border: 2px solid #9b59b6;
        }

        .modal-content.rarity-leggendario {
            background: linear-gradient(135deg, #8b4513, #654321);
            border: 2px solid #ffa500;
        }

        .modal-content.rarity-speciale {
            white-space: nowrap;
            background: linear-gradient(90deg, #8b0000, #b85500, #cccc00, #2eb800, #0099cc, #1a4dcc, #5500cc, #8b0000);
            background-size: 300% 300%;
            animation: backgroundAnimate 6s linear infinite;
            box-shadow: 0 10px 40px rgba(255, 0, 255, 0.6);
            border: 2px solid #fff;
        }

        .modal-content.rarity-segreto {
            background: linear-gradient(90deg, #9400d3, #4b0082, #1d002b, #4b0082, #9400d3);
            background-size: 300% 300%;
            animation: backgroundAnimate 5s linear infinite;
            box-shadow: 0 10px 40px rgba(147, 0, 211, 0.6);
            border: 2px solid #8b00ff;
        }

        .modal-content.rarity-theone {
            background: linear-gradient(90deg, #0000ff, #00bfff, rgb(52, 164, 255), #4169e1, #0000ff);
            background-size: 300% 300%;
            animation: backgroundAnimate 5s linear infinite;
            box-shadow: 0 10px 50px rgba(0, 11, 112, 0.8);
            border: 2px solid #ffffff;
        }

        @keyframes epicPulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.8;
                filter: brightness(1.2) saturate(1.3);
            }

            50% {
                transform: scale(1.1);
                opacity: 1;
                filter: brightness(1.5) saturate(1.6);
            }
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 30px;
            color: #fff;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: #ff6b6b;
        }

        .modal-character-info {
            text-align: center;
            color: #fff;
        }

        .modal-character-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ffffff;
            margin-bottom: 1rem;
            transition: transform 0.3s;
        }

        .modal-character-image:hover {
            transform: scale(1.05);
        }

        .modal-character-info h2 {
            margin: 1rem 0;
            color: #fff;
            font-size: 1.8rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .character-rarity {
            font-size: 1.1rem;
            font-weight: bold;
            margin: 0.5rem 0;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            display: inline-block;
            text-transform: uppercase;
        }

        .character-quantity {
            font-size: 1.2rem;
            color: #ffd700;
            font-weight: bold;
            margin: 0.5rem 0;
        }

        .character-description {
            margin: 1rem 0;
            line-height: 1.5;
            font-style: italic;
            color: #b0b0b0;
        }

        .character-traits {
            margin: 1rem 0;
            text-align: left;
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid #ffffff;
        }

        .modal-character-image.personaggio-comune {
            border-color: #666;
        }

        .modal-character-image.personaggio-raro {
            border-color: #4a90e2;
        }

        .modal-character-image.personaggio-epico {
            border-color: #9b59b6;
        }

        .modal-character-image.personaggio-leggendario {
            border-color: #ffa500;
        }

        .modal-character-image.personaggio-speciale {
            border-color: #fff;
        }

        .modal-character-image.personaggio-segreto {
            border-color: #8b00ff;
        }

        .modal-character-image.personaggio-theone {
            border-color: #ffffff;
        }

        .character-traits.personaggio-comune {
            border-left-color: #666;
        }

        .character-traits.personaggio-raro {
            border-left-color: #4a90e2;
        }

        .character-traits.personaggio-epico {
            border-left-color: #9b59b6;
        }

        .character-traits.personaggio-leggendario {
            border-left-color: #ffa500;
        }

        .character-traits.personaggio-speciale {
            border-left-color: #fff;
        }

        .character-traits.personaggio-segreto {
            border-left-color: #8b00ff;
        }

        .character-traits.personaggio-theone {
            border-left-color: #ffffff;
        }

        .character-date {
            margin: 1rem 0;
            color: #888;
            font-size: 0.9rem;
        }

        .animation-button {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 12px 24px;
            border-radius: 15px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin-top: 1rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .animation-button:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .animation-button:hover {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }

        .animation-button:hover:before {
            left: 100%;
        }

        .animation-button:active {
            transform: translateY(0);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }

            to {
                opacity: 0;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px) scale(0.95);
                opacity: 0;
            }

            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateY(0) scale(1);
                opacity: 1;
            }

            to {
                transform: translateY(-50px) scale(0.95);
                opacity: 0;
            }
        }

        @keyframes rainbowGradient {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        @keyframes secretGradient {
            0% {
                background-position: 0% 50%;
            }

            100% {
                background-position: 100% 50%;
            }
        }

        @keyframes newPulse {
            0% {
                transform: scale(0.8) rotate(15deg);
            }

            100% {
                transform: scale(1) rotate(15deg);
            }
        }

        @keyframes backgroundAnimate {
            0% {
                background-position: 0% 0%;
            }

            100% {
                background-position: 300% 0%;
            }
        }

        @keyframes rainbowFlow {
            0% {
                background-position: 0% 50%;
            }

            100% {
                background-position: 400% 50%;
            }
        }

        @media (max-width: 768px) {
            .modal-content {
                padding: 1.5rem;
                margin: 1rem;
            }

            .modal-character-image {
                width: 120px;
                height: 120px;
            }

            .modal-character-info h2 {
                font-size: 1.5rem;
            }
        }
    </style>
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
                <div class="stat-number" id="totalCharactersNum">0</div>
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
        crossorigin="anonymous"></script>
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
            const totalCharacters = await getAllCharacters() || [];
            const inventarioDiv = document.getElementById("inventario");
            const casseAperteResponse = await fetch('https://cripsum.com/api/get_casse_aperte');
            const casseAperteData = await casseAperteResponse.json();
            const casseAperte = await casseAperteData.total;

            const totalCharactersNum = await getCharactersNum();
            const foundCharacters = inventory.length;
            const completionRate = totalCharactersNum > 0 ? Math.round((foundCharacters / totalCharactersNum) * 100) : 0;

            animateNumber(document.getElementById("casseAperteNumber"), casseAperte);
            animateNumber(document.getElementById("foundCharacters"), foundCharacters);
            animateNumber(document.getElementById("totalCharactersNum"), totalCharactersNum);
            animateNumber(document.getElementById("completionRate"), completionRate, "%");

            const rarityOrder = ["comune", "raro", "epico", "leggendario", "speciale", "segreto", "theone"];

            rarityOrder.forEach((rarity, index) => {
                const foundInRarity = inventory.filter((p) => p.rarità === rarity).length;
                const totalInRarity = totalCharacters.filter((p) => p.rarità === rarity).length;

                if (rarity === "segreto" && foundInRarity === 0) {
                    return;
                }

                if (rarity === "theone" && foundInRarity === 0) {
                    return;
                }

                const section = document.createElement("div");
                section.classList.add("rarity-section", `rarity-${rarity}`);
                section.style.animationDelay = `${0.1 * index}s`;

                const titleDiv = document.createElement("div");
                titleDiv.classList.add("rarity-title");
                const displayRarity = rarity === "theone" ? "GOONING" : rarity.toUpperCase();
                titleDiv.textContent = `${displayRarity}: ${foundInRarity} / ${totalInRarity}`;
                section.appendChild(titleDiv);

                const charactersGrid = document.createElement("div");
                charactersGrid.classList.add("characters-grid");

                const filteredCharacters = totalCharacters.filter((p) => p.rarità === rarity);

                function showUnboxAnimation(idPersonaggio) {
                    window.location.href = "animazione_personaggio?id_personaggio=" + idPersonaggio;
                }

                function showCharacterModal(character) {
                    const modal = document.createElement("div");
                    modal.classList.add("character-modal");
                    modal.innerHTML = `
                <div class="modal-content rarity-${character.rarità}">
                <span class="close-modal">&times;</span>
                <div class="modal-character-info">
                    <img src="/img/${character.img_url}" class="modal-character-image personaggio-${character.rarità}" alt="${character.nome}">
                    <h2>${character.nome}</h2>
                    <p class="character-rarity">Rarità: ${character.rarità === "theone" ? "GOONING" : character.rarità}</p>
                    <p class="character-quantity">Quantità: x${character.quantità}</p>
                    <p class="character-description">${character.descrizione || 'Nessuna descrizione disponibile'}</p>
                    <p class="character-traits personaggio-${character.rarità}"><strong>Tratti distintivi:</strong><br>- ${character.caratteristiche ? character.caratteristiche.split(';').join('<br> -') : 'Nessun tratto specificato'}</p>
                    <p class="character-date">Trovato il: ${new Date(character.data).toLocaleDateString()} alle ${new Date(character.data).toLocaleTimeString('it-IT')}</p>
                    <button class="animation-button" onclick="window.location.href='animazione_personaggio?id_personaggio=${character.id}'">Visualizza Animazione Apertura</button>
                </div>
                </div>
            `;

                    document.body.appendChild(modal);

                    modal.addEventListener("click", (e) => {
                        if (e.target === modal || e.target.classList.contains("close-modal")) {
                            modal.classList.add("closing");
                            const modalContent = modal.querySelector(".modal-content");
                            modalContent.classList.add("closing");
                            setTimeout(() => {
                                document.body.removeChild(modal);
                            }, 300);
                        }
                    });
                }

                filteredCharacters.forEach((personaggio) => {
                    const character = inventory.find((p) => p.nome === personaggio.nome);
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

                    if (character) {
                        characterCard.style.cursor = "pointer";
                        characterCard.addEventListener("click", () => {
                            showCharacterModal(character);
                        });
                    }

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

        async function getCharactersNum() {
            const response = await fetch('https://cripsum.com/api/api_get_characters_num');
            const data = await response.json();
            return data;
        }

        async function getAllCharacters() {
            const response = await fetch('https://cripsum.com/api/get_all_characters');
            const data = await response.json();
            return data;
        }
    </script>

    <script src="../js/modeChanger.js"></script>
</body>

</html>