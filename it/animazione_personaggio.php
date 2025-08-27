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

$nomePersonaggio = $_GET['nome_personaggio'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <link rel="stylesheet" href="/css/lootbox.css?v=5" />
        <title>Cripsum™ - lootbox</title>
    </head>

    <body class="">
        <div class="stars" id="stars"></div>
        
        <!-- TO DO
           bloccare lo scorrimento della pagina quando il menu della navbar è aperto (aggiungere classe overflow-hidden al body) 
           bloccare lo scorrimento della pagina quando il pop up è aperto (aggiungere classe overflow-hidden al body)  
        -->

        <div style="max-width: 1520px; margin: auto; padding-top: 5rem" class="testobianco" id="paginaintera">

            <div class="container">

                <img src="../img/cassa.png" alt="Cassa" id="cassa" class="fadein" />

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
                        <a class="btn btn-secondary bottone mt-2" onclick="refresh()" style="cursor: pointer" href="?nome_personaggio=<?php echo urlencode($nomePersonaggio); ?>">Ripeti l'animazione</a>
                        <a class="btn btn-secondary bottone mt-2" href="inventario" style="cursor: pointer">Torna all'inventario</a>
                    </div>
                </div>

                <div id="particelle"></div>
            </div>

            <audio id="suonoCassa"></audio>
        </div>

        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/characters.js?v=4"></script>
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

            let isProcessing = false;

            function createStars() {
                const starsContainer = document.getElementById('stars');
                for (let i = 0; i < 100; i++) {
                    const star = document.createElement('div');
                    star.className = 'star';
                    star.style.left = Math.random() * 100 + '%';
                    star.style.top = Math.random() * 100 + '%';
                    star.style.animationDelay = Math.random() * 4 + 's';
                    starsContainer.appendChild(star);
                }
            }

            async function getInventory() {
                const response = await fetch('https://cripsum.com/api/api_get_inventario');
                const data = await response.json();

                localStorage.setItem("inventory", JSON.stringify(data));
                return data;
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

            async function riscattaPersonaggio(nomePersonaggio){

                if (isProcessing) {
                    return;
                }
                    
                isProcessing = true;

                try {
                    const pull = await fetch('https://cripsum.com/api/get_characters_from_name?name="' + encodeURIComponent(nomePersonaggio)+'"');
                    
                    document.getElementById("contenuto").innerHTML = `
                        <p style="top 10px; font-size: 20px; max-width: 600px; text-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);" id="nomePersonaggio">${pull.nome}</p>
                        <img src="/img/${pull.img_url}" alt="Premio" class="premio" />
                    `;

                    if (rarita === "comune") {
                        messaggioRarita.innerText = "bravo fra hai pullato un personaggio comune, skill issue xd";
                        bagliore.style.background = "radial-gradient(circle, rgba(150, 150, 150, 1) 0%, rgba(255, 255, 0, 0) 70%)";
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
                        messaggioRarita.innerText = "COSA? HAI PULLATO UN PERSONAGGIO SEGRETO? aura.";
                        bagliore.style.position = "fixed";
                        bagliore.style.width = "100vw";
                        bagliore.style.height = "100vh";
                        bagliore.style.zIndex = "-1";

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

            async function apriNormale() {
                cassa.onclick = null;

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
                    width: 100%;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    transform: scale(0.8);
                    transition: opacity 1s ease-out 0.3s, transform 1s ease-out 0.3s;
                `;

                const purpleCircle = document.createElement('div');
                purpleCircle.style.cssText = `
                    width: 300px;
                    height: 300px;
                    border-radius: 50%;
                    background: radial-gradient(circle, rgba(147, 0, 211, 1) 0%, rgba(75, 0, 130, 0.9) 30%, rgba(138, 43, 226, 0.7) 60%, transparent 100%);
                    animation: epicPulse 2s ease-in-out infinite;
                    box-shadow: 0 0 50px rgba(147, 0, 211, 0.8), 0 0 100px rgba(75, 0, 130, 0.6), inset 0 0 30px rgba(138, 43, 226, 0.4);
                    filter: brightness(1.2) saturate(1.3);
                    opacity: 0;
                    transform: scale(0.5);
                    animation-delay: 0.8s;
                    transition: opacity 0.8s ease-out 0.8s, transform 0.8s ease-out 0.8s;
                `;

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

                for (let i = 0; i < 8; i++) {
                    const lightning = document.createElement('div');
                    lightning.style.cssText = `
                        position: absolute;
                        width: 2px;
                        height: ${100 + Math.random() * 60}px;
                        background: linear-gradient(to bottom, 
                            rgba(255, 255, 255, 1) 0%,
                            rgba(147, 0, 211, 0.9) 20%,
                            rgba(138, 43, 226, 0.7) 60%,
                            transparent 100%
                        );
                        left: 50%;
                        top: 50%;
                        transform-origin: 50% 100%;
                        transform: translate(-50%, -50%) rotate(${i * 45}deg);
                        box-shadow: 0 0 8px rgba(255, 255, 255, 0.8), 0 0 16px rgba(147, 0, 211, 0.6);
                        border-radius: 1px;
                        opacity: 0;
                        animation: cleanLightning 1.5s ease-in-out ${1.2 + i * 0.1}s infinite;
                    `;
                    purpleContainer.appendChild(lightning);
                }

                for (let p = 0; p < 12; p++) {
                    const particle = document.createElement('div');
                    particle.style.cssText = `
                        position: absolute;
                        width: ${3 + Math.random() * 4}px;
                        height: ${3 + Math.random() * 4}px;
                        border-radius: 50%;
                        background: radial-gradient(circle, rgba(255, 255, 255, 0.9), rgba(147, 0, 211, 0.8));
                        left: ${35 + Math.random() * 30}%;
                        top: ${35 + Math.random() * 30}%;
                        box-shadow: 0 0 6px rgba(147, 0, 211, 0.8);
                        opacity: 0;
                        animation: floatingParticles 3s ease-in-out infinite ${Math.random() * 2 + 1.5}s;
                    `;
                    purpleContainer.appendChild(particle);
                }

                const enhancedStyle = document.createElement('style');
                enhancedStyle.textContent = `
                    @keyframes epicPulse {
                        0%, 100% { 
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
                    @keyframes expandingRing {
                        0% { 
                            transform: translate(-50%, -50%) scale(0.5); 
                            opacity: 0.6; 
                        }
                        100% { 
                            transform: translate(-50%, -50%) scale(2); 
                            opacity: 0; 
                        }
                    }
                    @keyframes cleanLightning {
                        0% { 
                            opacity: 0; 
                            transform: translate(-50%, -50%) rotate(var(--rotation, 0deg)) scaleY(0);
                        }
                        20% { 
                            opacity: 0.8; 
                            transform: translate(-50%, -50%) rotate(var(--rotation, 0deg)) scaleY(0.7);
                        }
                        40% { 
                            opacity: 1; 
                            transform: translate(-50%, -50%) rotate(var(--rotation, 0deg)) scaleY(1);
                        }
                        60% { 
                            opacity: 0.6; 
                            transform: translate(-50%, -50%) rotate(var(--rotation, 0deg)) scaleY(0.8);
                        }
                        100% { 
                            opacity: 0; 
                            transform: translate(-50%, -50%) rotate(var(--rotation, 0deg)) scaleY(0.3);
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
                    color:rgb(255, 255, 255);
                    font-size: 10rem;
                    font-weight: bold;
                    text-shadow: 0 0 20px #9932cc, 0 0 40px #4b0082;
                    opacity: 0;
                    transform: scale(0.3);
                    transition: opacity 1s ease-out 1s, transform 1s ease-out 2.5s;
                `;
                mysteriousText.textContent = 'オーラシグマゴド';

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

                setTimeout(() => {
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

                    setTimeout(() => {
                        mysteriousText.style.opacity = '1';
                        mysteriousText.style.transform = 'scale(1)';
                        createStars();
                    }, 1000);
                    
                    const particles = purpleContainer.querySelectorAll('div[style*="radial-gradient(circle, #ff00ff"]');
                    particles.forEach((particle, index) => {
                        setTimeout(() => {
                            particle.style.opacity = '1';
                        }, 1500 + Math.random() * 500);
                    });
                    

                        bagliore.style.background = "radial-gradient(circle, rgba(147, 0, 211, 1) 0%, rgba(75, 0, 130, 0.8) 30%, rgba(138, 43, 226, 0.6) 60%, rgba(148, 0, 211, 0) 100%)";
                        bagliore.style.animation = "secretGlowRotate 8s ease-in-out infinite";
                        bagliore.style.boxShadow = "0 0 100px rgba(147, 0, 211, 0.8), 0 0 200px rgba(75, 0, 130, 0.6), inset 0 0 50px rgba(138, 43, 226, 0.4)";
                        bagliore.style.borderRadius = "50%";
                        bagliore.style.width = "150vw";
                        bagliore.style.height = "150vw";

                        const secretStyleSheet = document.createElement('style');
                        secretStyleSheet.textContent = `
                            @keyframes secretGlowRotate {
                                0% { 
                                    transform: translate(-50%, -50%) scale(1) rotate(0deg);
                                    filter: brightness(1) saturate(1);
                                }
                                25% { 
                                    transform: translate(-50%, -50%) scale(1.2) rotate(90deg);
                                    filter: brightness(1.3) saturate(1.5);
                                }
                                50% { 
                                    transform: translate(-50%, -50%) scale(1) rotate(180deg);
                                    filter: brightness(1) saturate(1);
                                }
                                75% { 
                                    transform: translate(-50%, -50%) scale(1.2) rotate(270deg);
                                    filter: brightness(1.3) saturate(1.5);
                                }
                                100% { 
                                    transform: translate(-50%, -50%) scale(1) rotate(360deg);
                                    filter: brightness(1) saturate(1);
                                }
                            }
                        `;
                        document.head.appendChild(secretStyleSheet);
                    
                }, 100);

                setTimeout(() => {
                    introOverlay.style.animation = 'fadeOut 1.2s ease-out forwards';
                    setTimeout(() => {
                        document.body.removeChild(introOverlay);
                        document.head.removeChild(style);
                        document.head.removeChild(enhancedStyle);
                    }, 1200);
                }, 4000);
            }

            document.addEventListener('DOMContentLoaded', async function() {
                await riscattaPersonaggio(<?php echo $nomePersonaggio ?>);
                setTimeout(() => {
                testoNuovo();
                apriNormale();
                }, 1000);
            });

        </script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
