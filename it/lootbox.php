<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);


if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per accedere alle lootbox devi essere loggato";

    header('Location: accedi');
    exit();
}
require_once '../api/api_personaggi.php';

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <link rel="stylesheet" href="/css/lootbox.css" />
        <title>Cripsum™ - lootbox</title>
    </head>

    <body class="">
        <?php include '../includes/navbar-lootbox.php'; ?>
        
        <!-- TO DO
           bloccare lo scorrimento della pagina quando il menu della navbar è aperto (aggiungere classe overflow-hidden al body) 
           bloccare lo scorrimento della pagina quando il pop up è aperto (aggiungere classe overflow-hidden al body)  
        -->

        <div style="max-width: 1520px; margin: auto; padding-top: 5rem" class="testobianco" id="paginaintera">
            <div
                id="popup-overlay"
                style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.85);
                    display: none;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    opacity: 0;
                    transition: opacity 0.5s ease;
                "
            >
                <div
                    id="collegamentoedits"
                    class="collegamentoedit ombra fadeup"
                    style="
                        backdrop-filter: blur(15px);
                        background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(64, 64, 64, 0.1));
                        box-shadow: 0 0 8px 4px rgba(255, 255, 255, 0.5);
                        padding: 20px;
                        border: 1px solid rgba(255, 255, 255, 0.5);
                        border-radius: 10px;
                        max-width: 80%;
                        text-align: center;
                        position: relative;
                        opacity: 0;
                        transform: translateY(-20px);
                        transition: opacity 0.5s ease, transform 0.5s ease;
                    "
                >
                    <button style="position: absolute; top: 0px; right: 5px; background-color: transparent; border: none; cursor: pointer" onclick="closePopup()">
                        <span class="close_div tastobianco" style="font-size: 20px; color: rgb(255, 255, 255)"
                            >&times;<span class="linkbianco" style="font-size: small; position: relative; top: -3px; left: 3px">chiudi</span></span
                        >
                    </button>
                    <div id="banner-content"></div>
                </div>
            </div>

            <script>
                function getRandomBanner() {
                    const banners = [
                        `<div class="bannerino">
            <h2 style="color: rgb(255, 255, 255); padding-top: 11px">Ti offriamo un cookie! 🍪</h2>
            <p style="color: rgb(255, 255, 255);">Questo sito utilizza i cookie per salvare i tuoi dati. Se li disattivi, alcune funzioni come le impostazioni e l'inventario potrebbero non funzionare correttamente.</p>
            <p style="color: rgb(255, 255, 255);">Buon divertimento!</p>
            <button type="button" class="btn btn-secondary bottone" data-bs-dismiss="modal" onclick="closePopup()">Prendi i miei dati 😆</button>

        </div>`,
                    ];
                    return banners[Math.floor(Math.random() * banners.length)];
                }

                function showPopup() {
                    if (getCookie("popupSeen")) return;

                    const overlay = document.getElementById("popup-overlay");
                    const popup = document.getElementById("collegamentoedits");
                    document.getElementById("banner-content").innerHTML = getRandomBanner();
                    overlay.style.display = "flex";
                    document.body.style.overflow = "hidden";
                    setTimeout(() => {
                        overlay.style.opacity = "1";
                        popup.style.opacity = "1";
                        popup.style.transform = "translateY(0)";
                    }, 10);
                }

                function closePopup() {
                    const overlay = document.getElementById("popup-overlay");
                    const popup = document.getElementById("collegamentoedits");
                    popup.style.opacity = "0";
                    popup.style.transform = "translateY(-20px)";
                    overlay.style.opacity = "0";
                    document.body.style.overflow = "auto";
                    setTimeout(() => {
                        overlay.style.display = "none";
                    }, 500);
                    setCookie("popupSeen", true);
                }

                window.onload = function () {
                    setTimeout(showPopup, 700);
                };
            </script>
            <div class="container">

                <img src="../img/cassa.png" alt="Cassa" id="cassa" class="fadein" ondblclick="apriVeloce()" onclick="pullaPersonaggio(); apriNormale()" />

                <div id="baglioreWrapper">
                    <div class="bagliore" id="bagliore"></div>
                </div>

                <div id="contenuto"></div>

                <div id="messaggio" class="nascosto">
                    <h1 style="margin-top: 100px; font-size: 25px" id="messaggioRarita" class="non-selezionabile"></h1>
                    <a onclick="refresh()" id="apriAncora" class="linkbianco"></a>
                </div>

                <div id="divApriAncora" class="nascosto">
                    <div class="button-container mt-4" style="text-align: center; max-width: 95%; margin: auto">
                        <a class="btn btn-secondary bottone mt-2" onclick="refresh()" style="cursor: pointer">Apri cassa</a>
                        <a class="btn btn-secondary bottone mt-2" href="inventario" style="cursor: pointer">Apri l'inventario</a>
                    </div>
                </div>

                <div class="modal fade" id="impostazioniModal" tabindex="-1" aria-labelledby="impostazioniModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content bgimpostazioni">
                            <div class="modal-header">
                                <h5 class="modal-title" id="disclaimerModalLabel">Impostazioni</h5>
                                <!--<button type="button" class="btn-close tastobianco" data-bs-dismiss="modal" aria-label="Close" onclick="close_disclaimer(1)" style="color: #ffffff"></button>-->
                            </div>
                            <div class="modal-body">
                                <div class="col-md-6 d-flex" style="text-align: center">
                                    <!-- Checkbox -->
                                    <div class="form-check mb-3 mb-md-0" style="text-align: center">
                                        <input class="form-check-input checco" type="checkbox" value="" id="RimuoviAnime" />
                                        <label class="form-check-label" for="loginCheck">Rimuovi Anime</label>
                                    </div>
                                </div>

                                <div class="col-md-6 d-flex" style="text-align: center">
                                    <div class="form-check mb-3 mb-md-0" style="text-align: center">
                                        <input class="form-check-input checco" type="checkbox" value="" id="SoloSpeciali" />
                                        <label class="form-check-label" for="loginCheck">Solo Speciali</label>
                                    </div>
                                </div>
                                <div class="col-md-6 d-flex" style="text-align: center">
                                    <div class="form-check mb-3 mb-md-0" style="text-align: center">
                                        <input class="form-check-input checco" type="checkbox" value="" id="SoloSegreti" />
                                        <label class="form-check-label" for="loginCheck">Solo Segreti</label>
                                    </div>
                                </div>
                                <div class="col-md-6 d-flex" style="text-align: center">
                                    <div class="form-check mb-3 mb-md-0" style="text-align: center">
                                        <input class="form-check-input checco" type="checkbox" value="" id="SoloPoppy" />
                                        <label class="form-check-label" for="loginCheck">Meow</label>
                                    </div>
                                </div>
                                <div class="col-md-6 d-flex" style="text-align: center">
                                    <div class="form-check mb-3 mb-md-0" style="text-align: center">
                                        <input class="form-check-input checco" type="checkbox" value="" id="SoloComuni" />
                                        <label class="form-check-label" for="loginCheck">Solo Comuni</label>
                                    </div>
                                </div>

                                <div data-mdb-input-init class="form-outline mb-4">
                                    <label class="form-label" for="registerName">Codice Segreto</label>
                                    <input type="text" id="codiceSegreto" class="form-control" />
                                    <br />
                                    <button type="button" class="btn btn-secondary bottone" data-bs-dismiss="modal" onclick="riscattaCodice()">Riscatta codice</button>
                                </div>
                                <button type="button" class="btn btn-secondary bottone" data-bs-dismiss="modal" onclick="resettaInventario()">resetta inventario</button>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary bottone" data-bs-dismiss="modal" onclick="salvaPreferenze()">Salva Preferenze</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="particelle"></div>
            </div>

            <audio id="suonoCassa"></audio>
        </div>
        <div id="achievement-popup" class="popup" style="max-height: 100px">
            <img id="popup-image" src="" alt="Achievement" />
            <div>
                <h3 id="popup-title"></h3>
                <p id="popup-description"></p>
            </div>
        </div>
        <!--<footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="it/privacy" class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="it/tos" class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="it/supporto" class="linkbianco">Supporto</a></li>
            </ul>
        </footer>-->
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/characters.js"></script>
        <script src="../js/unlockAchievement-it.js"></script>
        <script>
            const cassa = document.getElementById("cassa");
            const nomePersonaggio = document.getElementById("nomePersonaggio");
            const messaggioRarita = document.getElementById("messaggioRarita");
            const audio = document.getElementById("suonoCassa");
            const bagliore = document.getElementById("bagliore");
            const messaggio = document.getElementById("messaggio");
            const contenuto = document.getElementById("contenuto");
            const particelleContainer = document.getElementById("particelle");
            const paginaintera = document.getElementById("paginaintera");
            const apriAncora = document.getElementById("apriAncora");
            const apriInventario = document.getElementById("apriInventario");
            const divApriAncora = document.getElementById("divApriAncora");
            const wrapper = document.getElementById("bagliore-wrapper");

            const soloSpecialiCheckbox = document.getElementById("SoloSpeciali");
            const rimuoviAnimeCheckbox = document.getElementById("RimuoviAnime");
            const soloPoppyCheckbox = document.getElementById("SoloPoppy");

            const codiceSegreto = document.getElementById("codiceSegreto");

            var rarityProbabilities = aggiornaRarita();
            let isProcessing = false;

            var casseAperte;
            var comuniDiFila;
            //|| {
            //    comune: 45,
            //    raro: 25,
            //    epico: 15,
            //    leggendario: 10,
            //    mitico: 4,
            //    speciale: 1,
            //};

            //TODO aggiungere pagina con la lore dei vari personaggi

            function getRandomRarity() {
                const totalWeight = Object.values(rarityProbabilities).reduce((sum, weight) => sum + weight, 0);
                let randomNum = Math.random() * totalWeight;

                for (let [rarity, weight] of Object.entries(rarityProbabilities)) {
                    if (randomNum < weight) {
                        return rarity;
                    }
                    randomNum -= weight;
                }
            }

            function getCookie(name) {
                const cookies = document.cookie.split("; ");
                for (let cookie of cookies) {
                    let [key, value] = cookie.split("=");
                    if (key === name) return JSON.parse(value);
                }
                return null;
            }

            function setCookie(name, value) {
                document.cookie = `${name}=${JSON.stringify(value)}; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT`;
            }

            /**function addToInventory(character) {
                let inventory = JSON.parse(localStorage.getItem("inventory")) || [];

                let characterFound = inventory.find((p) => p.name === character.name);

                if (!characterFound) {
                    inventory.push({ ...character, count: 1 });
                    testoNuovo();
                } else {
                    characterFound.count++;
                }

                localStorage.setItem("inventory", JSON.stringify(inventory));
            }



            function getInventory() {
                return JSON.parse(localStorage.getItem("inventory")) || [];
            }*/

            async function addToInventory(character) {
                const response = await fetch(`https://cripsum.com/api/add_character_to_inventory?character_id=${character.id}`);
                const data = await response.json();

                if (data.status === 'success') {
                    let inventory = JSON.parse(localStorage.getItem("inventory")) || [];
                    let characterFound = inventory.find((p) => p.nome === character.nome);

                    if (!characterFound) {
                        inventory.push({ ...character, count: 1 });
                        testoNuovo();
                    } else {
                        characterFound.count++;
                    }

                    localStorage.setItem("inventory", JSON.stringify(inventory));
                } else {
                    alert(data.message);
                }
            }

            async function getInventory() {
                const response = await fetch('https://cripsum.com/api/api_get_inventario');
                const data = await response.json();

                localStorage.setItem("inventory", JSON.stringify(data));
                return data;
            }

            async function resettaInventario() {
                if (!confirm("Sei sicuro di voler resettare l'inventario? Tutti i personaggi saranno persi!")) {
                    return;
                }

                const response = await fetch('https://cripsum.com/api/delete_inventory', {
                    method: 'DELETE',
                });

                const data = await response.json();
                if (data.status === 'success') {
                    localStorage.setItem("inventory", JSON.stringify([]));
                    setCookie("casseAperte", 0);
                    setCookie("comuniDiFila", 0);
                    setCookie("preferences", {});
                    setLastCharacterFound("");
                    localStorage.clear();
                    alert("Inventario resettato con successo!");
                    location.reload();
                } else {
                    alert(data.message);
                }
            }

            async function getAllCharacters() {
                const response = await fetch('https://cripsum.com/api/get_all_characters');
                const data = await response.json();
                return data;
            }

            async function getCharacterNumber() {
                const response = await fetch('https://cripsum.com/api/api_get_characters_num');
                const data = await response.json();
                return data;
            }

            /*function resettaInventario() {
                if (!confirm("Sei sicuro di voler resettare l'inventario? Tutti i personaggi saranno persi!")) {
                    return;
                }
                localStorage.setItem("inventory", JSON.stringify([]));
                setCookie("casseAperte", 0);
                setCookie("comuniDiFila", 0);
                setCookie("preferences", {});
                setLastCharacterFound("");
                localStorage.clear();
                alert("Inventario resettato con successo!");
                location.reload();
            }*/

            async function getRandomPull() {
                const allCharacters = await getAllCharacters();
                const selectedRarity = getRandomRarity();
                //const filteredRarities = rarities.filter((item) => item.rarity === selectedRarity);
                const filteredRarities = allCharacters.filter((item) => item.rarità === selectedRarity);
                return filteredRarities[Math.floor(Math.random() * filteredRarities.length)];
            }

            function salvaPreferenze() {
                const preferences = {};
                const checkboxes = document.querySelectorAll(".checco");
                checkboxes.forEach((checkbox) => {
                    preferences[checkbox.id] = checkbox.checked;
                });
                document.cookie = `preferences=${JSON.stringify(preferences)}; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT`;

                rarityProbabilities = aggiornaRarita();
            }

            document.addEventListener("DOMContentLoaded", () => {
                const preferences = getCookie("preferences");

                if (preferences) {
                    const checkboxes = document.querySelectorAll(".checco");
                    checkboxes.forEach((checkbox) => {
                        checkbox.checked = preferences[checkbox.id];
                    });
                }
                rarityProbabilities = aggiornaRarita();
            });

            function aggiornaRarita() {
                const preferences = getCookie("preferences");

                if (preferences) {
                    if (preferences.SoloSpeciali === true) {
                        return (rarityProbabilities = {
                            comune: 0,
                            raro: 0,
                            epico: 0,
                            leggendario: 0,
                            mitico: 0,
                            speciale: 100,
                            segreto: 0,
                        });
                    } else if (preferences.SoloSegreti === true) {
                        return (rarityProbabilities = {
                            comune: 0,
                            raro: 0,
                            epico: 0,
                            leggendario: 0,
                            mitico: 0,
                            speciale: 0,
                            segreto: 100,
                        });
                    } else if (preferences.SoloComuni === true) {
                        return (rarityProbabilities = {
                            comune: 100,
                            raro: 0,
                            epico: 0,
                            leggendario: 0,
                            mitico: 0,
                            speciale: 0,
                            segreto: 0,
                        });
                    } else {
                        return (rarityProbabilities = {
                            comune: 45,
                            raro: 25,
                            epico: 15,
                            leggendario: 10,
                            mitico: 4,
                            speciale: 0.9,
                            segreto: 0.1,
                        });
                    }
                }

                return (rarityProbabilities = {
                    comune: 45,
                    raro: 25,
                    epico: 15,
                    leggendario: 10,
                    mitico: 4,
                    speciale: 0.9,
                    segreto: 0.1,
                });
            }

            function getAllPossiblePulls() {
                return rarities;
            }

            async function filtroPull() {
                const preferences = getCookie("preferences");

                if (preferences) {
                    if (preferences.SoloPoppy === true) {
                        while (true) {
                            const pull = await getRandomPull();
                            if (pull.categoria === "poppy") {
                                return pull;
                            }
                        }
                    }
                    if (preferences.RimuoviAnime === true) {
                        while (true) {
                            const pull = await getRandomPull();
                            if (pull.categoria !== "anime") {
                                return pull;
                            }
                        }
                    }
                }
                return await getRandomPull();
            }

            async function pullaPersonaggio(){

                if (isProcessing) {
                    return;
                }
                    
                isProcessing = true;

                try {
                    const pull = await filtroPull();
                    
                    document.getElementById("contenuto").innerHTML = `
                        <p style="top 10px; font-size: 20px; max-width: 600px;" id="nomePersonaggio">${pull.nome}</p>
                        <img src="/img/${pull.img_url}" alt="Premio" class="premio" />
                    `;
                    
                    await addToInventory(pull);
                    
                    if (typeof setLastCharacterFound === 'function') {
                        setLastCharacterFound(pull.nome);
                    }

                    const rarita = pull.rarità;
                    setComuniDiFila(rarita);

                    if (rarita === "comune") {
                        messaggioRarita.innerText = "bravo fra hai pullato un personaggio comune, skill issue xd";
                        bagliore.style.background = "radial-gradient(circle, rgba(150, 150, 150, 1) 0%, rgba(255, 255, 0, 0) 70%)";
                    } else if (rarita === "mitico") {
                        messaggioRarita.innerText = "PAZZESCO FRA, hai pullato un personaggio mitico";
                        bagliore.style.background = "radial-gradient(circle, rgba(245, 15, 15, 1) 0%, rgba(0, 255, 0, 0) 70%)";
                    } else if (rarita === "leggendario") {
                        messaggioRarita.innerText = "che fortuna, hai pullato un personaggio leggendario!";
                        bagliore.style.background = "radial-gradient(circle, rgba(255, 228, 23, 1) 0%, rgba(0, 0, 255, 0) 70%)";
                    } else if (rarita === "epico") {
                        messaggioRarita.innerText = "hai pullato un personaggio epico, tanta roba, ma poteva andare meglio";
                        bagliore.style.background = "radial-gradient(circle, rgba(195, 0, 235, 1) 0%, rgba(0, 0, 255, 0) 70%)";
                    } else if (rarita === "raro") {
                        messaggioRarita.innerText = "buono dai, hai pullato un personaggio raro!";
                        bagliore.style.background = "radial-gradient(circle, rgba(0, 74, 247, 1) 0%, rgba(0, 0, 255, 0) 70%)";
                    } else if (rarita === "speciale") {
                        messaggioRarita.innerText = "COM'É POSSIBILE? HAI PULLATO UN PERSONAGGIO SPECIALE!";

                        bagliore.style.position = "fixed";
                        bagliore.style.width = "100vw";
                        bagliore.style.height = "100vh";
                        bagliore.style.zIndex = "-1";

                        bagliore.style.background = "linear-gradient(90deg, #ff0000, #ff7300, #fffb00, #48ff00, #00f7ff, #2b65ff, #8000ff, #ff0000)";
                        bagliore.style.backgroundSize = "300% 100%";
                        bagliore.style.animation = "rainbowBackground 6s linear infinite";
                    } else if (rarita === "segreto") {

                        startIntroAnimation(pull.nome);
                        messaggioRarita.innerText = "COSA?.. HAI PULLATO UN PERSONAGGIO SEGRETO? aura.";
                        bagliore.style.position = "fixed";
                        bagliore.style.width = "100vw";
                        bagliore.style.height = "100vh";
                        bagliore.style.zIndex = "-1";
                        bagliore.style.background = "radial-gradient(circle, rgba(147, 0, 211, 1) 0%, rgba(75, 0, 130, 0.8) 30%, rgba(138, 43, 226, 0.6) 60%, rgba(148, 0, 211, 0) 100%)";
                        bagliore.style.animation = "secretGlow 2s ease-in-out infinite, rotate 8s linear infinite";
                        bagliore.style.boxShadow = "0 0 100px rgba(147, 0, 211, 0.8), 0 0 200px rgba(75, 0, 130, 0.6), inset 0 0 50px rgba(138, 43, 226, 0.4)";
                        
                        const secretStyleSheet = document.createElement('style');
                        secretStyleSheet.textContent = `
                            @keyframes secretGlow {
                                0%, 100% { 
                                    transform: translate(-50%, -50%) scale(1);
                                    filter: brightness(1) saturate(1);
                                }
                                50% { 
                                    transform: translate(-50%, -50%) scale(1.2);
                                    filter: brightness(1.3) saturate(1.5);
                                }
                            }
                            @keyframes rotate {
                                from { transform: translate(-50%, -50%) rotate(0deg); }
                                to { transform: translate(-50%, -50%) rotate(360deg); }
                            }
                        `;
                        document.head.appendChild(secretStyleSheet);
                    }
                    document.getElementById("suonoCassa").innerHTML = `
                        <source src="/audio/${pull.audio_url}" type="audio/mpeg" id="suono" />
                    `;
                    
                } catch (error) {
                    console.error('Errore nel pull del personaggio:', error);
                    messaggioRarita.innerText = "Errore durante l'apertura della cassa. Riprova.";
                } finally {
                    setTimeout(() => {
                        isProcessing = false;
                    }, 1000);
                }
            }

            document.addEventListener("DOMContentLoaded", function() {
            document.addEventListener("keydown", function (event) {
                if (event.code === "Space") {
                    event.preventDefault(); 

                    if (!cassa.classList.contains("aperta")) {
                        if (!contenuto.classList.contains("salto")) {
                            pullaPersonaggio().then(() => {
                                bagliore.style.opacity = 0.6;
                                bagliore.style.transform = "translate(-50%, -50%) scale(1.5)";

                                audio.currentTime = 0;
                                audio.play();

                                generaParticelle();
                                apriCassa();
                                apriVeloce();
                            });
                        }
                    } else {
                        apriVeloce();
                    }
                }
            });

            document.addEventListener("keydown", function (event) {
                if (event.code === "KeyR" || event.code === "Enter") {
                    event.preventDefault();
                    refresh();
                }
            });
        });


            async function riscattaCodice() {
                if (codiceSegreto.value === "godo") {
                    const inventory = await getInventory();
                    if (inventory.find((p) => p.nome === "CRIPSUM")) {
                        alert("il Codice è già riscattato o cripsum è già nel tuo inventario!");
                        return;
                    }
                    let pullRiscattata = getCharacter("CRIPSUM");
                    addToInventory(pullRiscattata);
                    alert("Codice riscattato con successo! cripsum è stato aggiunto al tuo inventario!");
                } else {
                    alert("Codice non valido, skill issue!");
                }
            }

            function getCharacter(name) {
                return rarities.find((p) => p.name === name);
            }

            async function apriCassa() {
                const casseAperteResponse = await fetch('https://cripsum.com/api/get_casse_aperte');
                const casseAperteData = await casseAperteResponse.json();
                const casseAperte = await casseAperteData.total;
                const inventory = await getInventory();
                comuniDiFila = getCookie("comuniDiFila") || 0;

                if (comuniDiFila === 10) {
                    unlockAchievement(9);
                }

                if (casseAperte === 100) {
                    unlockAchievement(8);
                }
                if (casseAperte === 500) {
                    unlockAchievement(16);
                }
                if (inventory.length === 1) {
                    unlockAchievement(5);
                }
                if (inventory.length === 46) {
                    unlockAchievement(18);
                }
            }

            async function apriNormale() {
                cassa.onclick = null;
                await apriCassa();

                generaParticelle();

                bagliore.style.opacity = 0.6;
                bagliore.style.transform = "translate(-50%, -50%) scale(1.5)";

                audio.currentTime = 0;
                audio.play();

                cassa.src = "../img/cassa_aperta.png";
                cassa.classList.add("aperta");

                setTimeout(() => {
                    contenuto.classList.add("salto");
                    messaggio.classList.add("salto");
                    cassa.classList.add("dissolvi");
                }, 3000);

                setTimeout(() => {
                    divApriAncora.classList.remove("nascosto");
                    divApriAncora.classList.add("salto");
                }, 4000);

                //audio.onended = () => {
                //    setTimeout(refresh, 500);
                //};
            }

            function testoNuovo() {
                let newLabel = document.createElement("span");
                newLabel.classList.add("new-label");
                newLabel.innerText = "NEW!";
                contenuto.appendChild(newLabel);
            }

            function apriVeloce() {
                contenuto.classList.add("salto");
                messaggio.classList.add("salto");
                cassa.classList.add("dissolvi");

                divApriAncora.classList.remove("nascosto");
                divApriAncora.classList.add("salto");

                cassa.onclick = null;

                //audio.onended = () => {
                //    setTimeout(refresh, 500);
                //};
            }

            function generaParticelle() {
                const container = document.getElementById("particelle");
                const cassa = document.getElementById("cassa");
                const rect = cassa.getBoundingClientRect();

                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;

                for (let i = 0; i < 100; i++) {
                    const particella = document.createElement("div");
                    particella.classList.add("particella");

                    particella.style.left = `${centerX}px`;
                    particella.style.top = `${centerY}px`;

                    const angle = Math.random() * 2 * Math.PI;
                    const distance = Math.random() * 200 + 50;
                    const x = Math.cos(angle) * distance;
                    const y = Math.sin(angle) * distance;

                    particella.style.setProperty("--x", `${x}px`);
                    particella.style.setProperty("--y", `${y}px`);

                    container.appendChild(particella);

                    setTimeout(() => particella.remove(), 2000);
                }
            }

            function refresh() {
                location.reload();
            }

            function setComuniDiFila(rarita) {
                comuniDiFila = getCookie("comuniDiFila") || 0;
                if (rarita === "comune") {
                    comuniDiFila++;
                    setCookie("comuniDiFila", comuniDiFila);
                } else {
                    setCookie("comuniDiFila", 0);
                }
            }

            function getComuniDiFila(rarita) {
                tempComuniDiFila = getCookie("comuniDiFila") || 0;
                if (rarita === "comune") {
                    tempComuniDiFila++;
                    setCookie("comuniDiFila", tempComuniDiFila);
                    return tempComuniDiFila;
                } else {
                    setCookie("comuniDiFila", 0);
                    return tempComuniDiFila;
                }
            }

            function startIntroAnimation(nome_personaggio) {

                const introOverlay = document.createElement('div');
                introOverlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100vw;
                    height: 100vh;
                    background: #000;
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                    opacity: 0;
                    transition: opacity 0.8s ease-in-out;
                `;

                const purpleContainer = document.createElement('div');
                purpleContainer.style.cssText = `
                    position: relative;
                    width: 100vw;
                    height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    transform: scale(0.8);
                    transition: opacity 1s ease-out 0.3s, transform 1s ease-out 0.3s;
                    background: radial-gradient(circle at center, 
                        rgba(147, 0, 211, 0.2) 0%, 
                        rgba(75, 0, 130, 0.1) 20%, 
                        rgba(138, 43, 226, 0.08) 40%, 
                        rgba(25, 25, 112, 0.05) 60%, 
                        transparent 80%);
                    animation: backgroundPulse 3s ease-in-out infinite;
                `;
                const purpleCircle = document.createElement('div');
                purpleCircle.style.cssText = `
                    width: 300px;
                    height: 300px;
                    border-radius: 50%;
                    background: radial-gradient(circle, 
                        rgba(147, 0, 211, 1) 0%, 
                        rgba(75, 0, 130, 0.9) 20%, 
                        rgba(138, 43, 226, 0.8) 40%, 
                        rgba(72, 61, 139, 0.6) 60%, 
                        rgba(25, 25, 112, 0.3) 80%, 
                        transparent 100%);
                    box-shadow: 
                        0 0 50px rgba(147, 0, 211, 0.9), 
                        0 0 100px rgba(75, 0, 130, 0.7), 
                        0 0 150px rgba(138, 43, 226, 0.5),
                        inset 0 0 50px rgba(138, 43, 226, 0.4),
                        inset 0 0 100px rgba(75, 0, 130, 0.2);
                    filter: brightness(1.3) saturate(1.5) blur(0.5px);
                    opacity: 0;
                    transform: scale(0.5);
                    animation: epicPulse 2.5s ease-in-out infinite, orbitRotation 8s linear infinite;
                    animation-delay: 0.8s;
                    transition: opacity 0.8s ease-out 0.8s, transform 0.8s ease-out 0.8s;
                    position: relative;
                    overflow: visible;
                `;

                for (let i = 0; i < 3; i++) {
                    const orbitalRing = document.createElement('div');
                    orbitalRing.style.cssText = `
                        position: absolute;
                        width: ${350 + i * 60}px;
                        height: ${350 + i * 60}px;
                        border-radius: 50%;
                        border: 2px solid rgba(147, 0, 211, ${0.4 - i * 0.1});
                        left: 50%;
                        top: 50%;
                        transform: translate(-50%, -50%);
                        animation: counterRotation ${4 + i * 2}s linear infinite reverse;
                        box-shadow: 0 0 20px rgba(147, 0, 211, 0.3);
                        opacity: 0;
                        transition: opacity 0.6s ease-out ${1.5 + i * 0.3}s;
                    `;
                    purpleCircle.appendChild(orbitalRing);
                }

                const enhancedAnimationStyle = document.createElement('style');
                enhancedAnimationStyle.textContent = `
                    @keyframes orbitRotation {
                        from { transform: scale(var(--scale, 1)) rotate(0deg); }
                        to { transform: scale(var(--scale, 1)) rotate(360deg); }
                    }
                    @keyframes counterRotation {
                        from { transform: translate(-50%, -50%) rotate(0deg); }
                        to { transform: translate(-50%, -50%) rotate(-360deg); }
                    }
                `;
                document.head.appendChild(enhancedAnimationStyle);

                for (let ring = 0; ring < 3; ring++) {
                    const energyRing = document.createElement('div');
                    energyRing.style.cssText = `
                        position: absolute;
                        width: ${250 + ring * 80}px;
                        height: ${250 + ring * 80}px;
                        border-radius: 50%;
                        border: 2px solid rgba(147, 0, 211, ${0.6 - ring * 0.2});
                        animation: expandingRing 3s ease-out infinite ${ring * 0.5}s;
                        left: 50%;
                        top: 50%;
                        transform: translate(-50%, -50%);
                        opacity: 0;
                        transition: opacity 0.6s ease-out ${1.2 + ring * 0.2}s;
                    `;
                    purpleContainer.appendChild(energyRing);
                }

                for (let i = 0; i < 12; i++) {
                    const lightning = document.createElement('div');
                    lightning.style.cssText = `
                        position: absolute;
                        width: 4px;
                        height: ${120 + Math.random() * 80}px;
                        background: linear-gradient(to bottom, #ff00ff, #9932cc, #4b0082, transparent);
                        transform-origin: bottom center;
                        animation: enhancedLightning 0.5s ease-in-out ${1.5 + i * 0.15}s infinite;
                        transform: rotate(${i * 30}deg);
                        left: 50%;
                        top: 50%;
                        transform-origin: 50% 100%;
                        box-shadow: 0 0 10px rgba(147, 0, 211, 0.8);
                        filter: brightness(1.5);
                        opacity: 0;
                        transition: opacity 0.4s ease-out ${1.5 + i * 0.05}s;
                    `;
                    purpleContainer.appendChild(lightning);
                }

                for (let p = 0; p < 15; p++) {
                    const particle = document.createElement('div');
                    particle.style.cssText = `
                        position: absolute;
                        width: ${4 + Math.random() * 6}px;
                        height: ${4 + Math.random() * 6}px;
                        border-radius: 50%;
                        background: radial-gradient(circle, #ff00ff, #9932cc);
                        animation: floatingParticles 4s ease-in-out infinite ${Math.random() * 2 + 1}s;
                        left: ${30 + Math.random() * 40}%;
                        top: ${30 + Math.random() * 40}%;
                        box-shadow: 0 0 8px rgba(147, 0, 211, 0.9);
                        opacity: 0;
                        transition: opacity 0.6s ease-out ${1.8 + Math.random() * 0.8}s;
                    `;
                    purpleContainer.appendChild(particle);
                }

                const enhancedStyle = document.createElement('style');
                enhancedStyle.textContent = `
                    @keyframes epicPulse {
                        0%, 100% { 
                            transform: scale(1) rotate(0deg); 
                            opacity: 0.9; 
                            filter: brightness(1.2) saturate(1.3) hue-rotate(0deg);
                        }
                        25% { 
                            transform: scale(1.2) rotate(90deg); 
                            opacity: 1; 
                            filter: brightness(1.4) saturate(1.5) hue-rotate(10deg);
                        }
                        50% { 
                            transform: scale(1.05) rotate(180deg); 
                            opacity: 0.85; 
                            filter: brightness(1.6) saturate(1.8) hue-rotate(20deg);
                        }
                        75% { 
                            transform: scale(1.25) rotate(270deg); 
                            opacity: 1; 
                            filter: brightness(1.3) saturate(1.4) hue-rotate(10deg);
                        }
                    }
                    @keyframes expandingRing {
                        0% { 
                            transform: translate(-50%, -50%) scale(0.6); 
                            opacity: 0.8; 
                        }
                        50% { 
                            transform: translate(-50%, -50%) scale(1.5); 
                            opacity: 0.4; 
                        }
                        100% { 
                            transform: translate(-50%, -50%) scale(2.2); 
                            opacity: 0; 
                        }
                    }
                    @keyframes enhancedLightning {
                        0% { 
                            opacity: 0; 
                            transform: rotate(var(--rotation, 0deg)) scaleY(0) translateX(-50%); 
                            filter: brightness(1);
                        }
                        30% { 
                            opacity: 0.7; 
                            transform: rotate(var(--rotation, 0deg)) scaleY(0.8) translateX(-50%); 
                            filter: brightness(1.5) saturate(1.5);
                        }
                        60% { 
                            opacity: 1; 
                            transform: rotate(var(--rotation, 0deg)) scaleY(1) translateX(-50%); 
                            filter: brightness(2) saturate(2);
                        }
                        100% { 
                            opacity: 0; 
                            transform: rotate(var(--rotation, 0deg)) scaleY(0.6) translateX(-50%); 
                            filter: brightness(0.8);
                        }
                    }
                    @keyframes floatingParticles {
                        0%, 100% { 
                            transform: translateY(0px) scale(1); 
                            opacity: 0.6; 
                        }
                        25% { 
                            transform: translateY(-15px) scale(1.1); 
                            opacity: 0.9; 
                        }
                        50% { 
                            transform: translateY(-25px) scale(1.2); 
                            opacity: 1; 
                        }
                        75% { 
                            transform: translateY(-10px) scale(0.9); 
                            opacity: 0.7; 
                        }
                    }
                `;
                document.head.appendChild(enhancedStyle);

                const mysteriousText = document.createElement('div');
                mysteriousText.style.cssText = `
                    position: absolute;
                    color: #9932cc;
                    font-size: 3rem;
                    font-weight: bold;
                    text-shadow: 0 0 20px #9932cc, 0 0 40px #4b0082;
                    opacity: 0;
                    transform: scale(0.3);
                    transition: opacity 1s ease-out 2s, transform 1s ease-out 2s;
                `;
                mysteriousText.textContent = '???';

                const style = document.createElement('style');
                style.textContent = `
                    @keyframes textReveal {
                        0% { opacity: 0; transform: scale(0.5); }
                        50% { opacity: 1; transform: scale(1.2); }
                        100% { opacity: 1; transform: scale(1); }
                    }
                    @keyframes fadeOut {
                        to { opacity: 0; transform: scale(0.9); }
                    }
                `;

                document.head.appendChild(style);
                purpleContainer.appendChild(purpleCircle);
                purpleContainer.appendChild(mysteriousText);
                introOverlay.appendChild(purpleContainer);
                document.body.appendChild(introOverlay);

                    introOverlay.style.opacity = '1';
                    purpleContainer.style.opacity = '1';
                    purpleContainer.style.transform = 'scale(1)';
                    
                    setTimeout(() => {
                        purpleCircle.style.opacity = '1';
                        purpleCircle.style.transform = 'scale(1)';
                    }, 300);

                    const rings = purpleContainer.querySelectorAll('div[style*="border:"]');
                    rings.forEach((ring, index) => {
                        setTimeout(() => {
                            ring.style.opacity = '1';
                        }, 800 + index * 200);
                    });
                    
                    const lightnings = purpleContainer.querySelectorAll('div[style*="linear-gradient(to bottom"]');
                    lightnings.forEach((lightning, index) => {
                        setTimeout(() => {
                            lightning.style.opacity = '1';
                        }, 1200 + index * 50);
                    });
                    
                    const particles = purpleContainer.querySelectorAll('div[style*="radial-gradient(circle, #ff00ff"]');
                    particles.forEach((particle, index) => {
                        setTimeout(() => {
                            particle.style.opacity = '1';
                        }, 1500 + Math.random() * 500);
                    });
                    
                    setTimeout(() => {
                        mysteriousText.style.opacity = '1';
                        mysteriousText.style.transform = 'scale(1)';
                    }, 2000);

                setTimeout(() => {
                    introOverlay.style.animation = 'fadeOut 1.2s ease-out forwards';
                    setTimeout(() => {
                        document.body.removeChild(introOverlay);
                        document.head.removeChild(style);
                        document.head.removeChild(enhancedStyle);
                    }, 1200);
                }, 4000);
            }

        </script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
