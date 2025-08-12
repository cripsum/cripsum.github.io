<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <title>Cripsum™ - chisiamo</title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @font-face {
            font-family: NotoColorEmojiLimited;
            unicode-range: U+1F1E6-1F1FF;
            src: url(https://raw.githack.com/googlefonts/noto-emoji/main/fonts/NotoColorEmoji.ttf);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0f0f0f 100%);
            min-height: 100vh;
            color: #ffffff;
            overflow-x: hidden;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .chisiamo-section {
            text-align: center;
            padding: 8rem 0 4rem;
            position: relative;
        }

        .chisiamo-title {
            font-size: clamp(2.5rem, 6vw, 4rem);
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #64c8ff 50%, #ff64c8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 3s ease-in-out infinite;
        }

        .chisiamo-subtitle {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 3rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 3rem;
            padding: 2rem 0;
        }

        .team-member {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            padding: 2rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(40px);
            animation: fadeInUp 0.8s ease forwards;
        }

        .team-member::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.03) 0%, rgba(255, 100, 200, 0.02) 50%, rgba(100, 255, 150, 0.03) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
            border-radius: 24px;
        }

        .team-member:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 40px rgba(100, 200, 255, 0.1);
            border-color: rgba(100, 200, 255, 0.3);
        }

        .team-member:hover::before {
            opacity: 1;
        }

        .member-content {
            display: flex;
            align-items: center;
            gap: 2rem;
            position: relative;
            z-index: 2;
        }

        .member-image {
            flex-shrink: 0;
            position: relative;
        }

        .member-image img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.2);
            transition: all 0.4s ease;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .team-member:hover .member-image img {
            border-color: rgba(100, 200, 255, 0.5);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4), 0 0 20px rgba(100, 200, 255, 0.3);
            transform: scale(1.05);
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .member-name a {
            color: inherit;
            text-decoration: none;
            position: relative;
            transition: all 0.3s ease;
        }

        .member-name a::after {
            content: "";
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(135deg, #64c8ff, #ff64c8);
            transform: scaleX(0);
            transition: transform 0.3s ease;
            border-radius: 1px;
        }

        .member-name a:hover {
            background: linear-gradient(135deg, #64c8ff 0%, #ff64c8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .member-name a:hover::after {
            transform: scaleX(1);
        }

        .member-description {
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.6;
            font-size: 1rem;
            font-weight: 400;
        }

        .member-description a {
            color: #64c8ff;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .member-description a:hover {
            color: #ffffff;
            text-shadow: 0 0 8px rgba(100, 200, 255, 0.6);
        }

        .join-team-section {
            text-align: center;
            padding: 4rem 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%);
            border-radius: 24px;
            margin: 3rem 0;
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .join-team-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.03), transparent);
            animation: shine 4s ease-in-out infinite;
        }

        .join-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #ffffff;
            position: relative;
            z-index: 2;
        }

        .join-description {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            max-width: 600px;
            margin: 0 auto 2rem;
            line-height: 1.6;
            position: relative;
            z-index: 2;
        }

        .join-email {
            display: inline-block;
            background: linear-gradient(135deg, #3a3a3d, #2a2a2d);
            color: #ffffff;
            padding: 1rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 2;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .join-email:hover {
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.2), rgba(255, 100, 200, 0.15));
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.4), 0 0 20px rgba(100, 200, 255, 0.3);
            color: #ffffff;
            border-color: rgba(100, 200, 255, 0.4);
        }

        .mystery-members {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .mystery-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            border-radius: 20px;
            border: 2px dashed rgba(255, 255, 255, 0.2);
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .mystery-card::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: radial-gradient(circle, rgba(100, 200, 255, 0.1) 0%, transparent 70%);
            transition: all 0.5s ease;
            transform: translate(-50%, -50%);
            border-radius: 50%;
        }

        .mystery-card:hover::before {
            width: 300px;
            height: 300px;
        }

        .mystery-card:hover {
            border-color: rgba(100, 200, 255, 0.4);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .mystery-icon {
            font-size: 4rem;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .mystery-card:hover .mystery-icon {
            color: rgba(100, 200, 255, 0.8);
            transform: scale(1.1) rotate(5deg);
        }

        .mystery-text {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
            position: relative;
            z-index: 2;
        }

        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }

        .floating-element {
            position: absolute;
            width: 6px;
            height: 6px;
            background: rgba(100, 200, 255, 0.3);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(1) { top: 20%; left: 10%; animation-delay: 0s; }
        .floating-element:nth-child(2) { top: 60%; left: 80%; animation-delay: 1s; }
        .floating-element:nth-child(3) { top: 30%; left: 70%; animation-delay: 2s; }
        .floating-element:nth-child(4) { top: 80%; left: 20%; animation-delay: 3s; }
        .floating-element:nth-child(5) { top: 40%; left: 50%; animation-delay: 4s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shimmer {
            0%, 100% { filter: hue-rotate(0deg); }
            50% { filter: hue-rotate(180deg); }
        }

        @keyframes shine {
            0% { left: -100%; }
            50% { left: 100%; }
            100% { left: 100%; }
        }

        @keyframes float {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg); 
                opacity: 0.3;
            }
            50% { 
                transform: translateY(-20px) rotate(180deg); 
                opacity: 0.8;
            }
        }

        .team-member:nth-child(1) { animation-delay: 0.1s; }
        .team-member:nth-child(2) { animation-delay: 0.2s; }
        .team-member:nth-child(3) { animation-delay: 0.3s; }
        .team-member:nth-child(4) { animation-delay: 0.4s; }
        .team-member:nth-child(5) { animation-delay: 0.5s; }
        .team-member:nth-child(6) { animation-delay: 0.6s; }
        .team-member:nth-child(7) { animation-delay: 0.7s; }
        .team-member:nth-child(8) { animation-delay: 0.8s; }
        .team-member:nth-child(9) { animation-delay: 0.9s; }
        .team-member:nth-child(10) { animation-delay: 1.0s; }

        @media (max-width: 768px) {
            .team-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .member-content {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }

            .member-image img {
                width: 100px;
                height: 100px;
            }

            .chisiamo-section {
                padding: 6rem 0 3rem;
            }

            .team-member {
                padding: 1.5rem;
            }

            .mystery-members {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .main-container {
                padding: 0 0.5rem;
            }

            .team-grid {
                grid-template-columns: minmax(280px, 1fr);
                gap: 1.5rem;
            }

            .team-member {
                padding: 1.25rem;
                border-radius: 20px;
            }

            .member-image img {
                width: 80px;
                height: 80px;
            }

            .member-name {
                font-size: 1.3rem;
            }

            .member-description {
                font-size: 0.9rem;
            }

            .chisiamo-title {
                margin-bottom: 1rem;
            }

            .chisiamo-subtitle {
                font-size: 1rem;
                margin-bottom: 2rem;
            }

            .join-team-section {
                padding: 3rem 1rem;
                margin: 2rem 0;
            }

            .join-title {
                font-size: 2rem;
            }

            .join-description {
                font-size: 1rem;
            }

            .join-email {
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
            }
        }

        .scroll-indicator {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .scroll-indicator:hover {
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.2), rgba(255, 100, 200, 0.15));
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            color: white;
        }
        </style>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

           <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <div class="main-container">
        <section class="chisiamo-section">
            <h1 class="chisiamo-title">Il Nostro Team di Sviluppo</h1>
            <p class="chisiamo-subtitle">
                Vuoi far parte del nostro team di sviluppo? Manda una e-mail allegando immagine, nome e descrizione, e se vuoi, un username o un link social per i crediti.
            </p>
        </section>

        <section class="team-section">
            <div class="team-grid">
                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/cripsumchisiamo.jpg" alt="Cripsum" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="../user/cripsum">cripsum</a>
                            </h3>
                            <p class="member-description">
                                L'imperatore del Congo, è un editor fallito che continua a sognare in grande.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/simonetussi.jpg" alt="Simone Tussi" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="../user/simonetussi">simonetussi.ph</a>
                            </h3>
                            <p class="member-description">
                                Seguite tutti simonetussi.ph su <a href="https://instagram.com/simonetussi.ph">Instagram</a> e 
                                <a href="https://tiktok.com/@simonetussi.ph">TikTok</a> per degli scatti fantastici.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/sahe.jpg" alt="Danebidev" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">danebidev</h3>
                            <p class="member-description">
                                Game developer sempre in risparmio energetico.<br>
                                <strong>JavaScript >> Java</strong> - questa è la sua filosofia di vita.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/ray.jpg" alt="Ray" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">Ray</h3>
                            <p class="member-description">
                                <strong>Broke/Broken/Broker</strong><br>
                                Un trader che mangia cani e beve birra fino a stare male. Vive la vita al limite.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/barandeep.jpg" alt="Barandeep" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">Barandeep</h3>
                            <p class="member-description">
                                Xenon il gigante indiano, è il project manager che tiene tutto sotto controllo con la sua presenza imponente.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/samarpreet.jpg" alt="Scammarpreet" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">Scammarpreet</h3>
                            <p class="member-description">
                                <strong>Money grabber • Scammer • Guru • Doxer</strong><br>
                                Gambler professionista che sa come far girare i soldi.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/tacos.jpg" alt="Tacos" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="../user/tacos">Tacos</a>
                            </h3>
                            <p class="member-description">
                                Anche conosciuto come <strong>1nstxnct</strong>, è un pro player italiano di Brawl Stars con skills leggendarie.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/cossu.jpg" alt="Cossu" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">Cossu</h3>
                            <p class="member-description">
                                <strong>Lontrone spermatozoico</strong> (ama le lontre)<br>
                                Ama le frittate con la banana e ha gusti culinari... particolari.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/photo_2023-11-14_17-21-10.jpg" alt="Zakator" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="../user/zakator">Zakator</a>
                            </h3>
                            <p class="member-description">
                                Grande ascoltatore di musica anime e phonk, è un hackerino fallito che non si arrende mai.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/salsina.jpg" alt="Xalx Andrea" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="../user/salsina">Xalx Andrea</a>
                            </h3>
                            <p class="member-description">
                                Il player + tossico di tutto <strong>Yokai Watch</strong><br>
                                Boh è anoressico ma almeno sa giocare.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/mabbon.jpg" alt="Mabbon" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">Mabbon</h3>
                            <p class="member-description">
                                Un ragazzo sfruttato e sottopagato. Forse perché è ne-... meglio non finire la frase.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/lollolapulce.jpg" alt="LolloLaPulce" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">LolloLaPulce</h3>
                            <p class="member-description">
                                Addetto alla depressione e grande giocatore di Minecraft. Costruisce mentre piange.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="join-team-section">
            <h2 class="join-title">Unisciti al Team! 🚀</h2>
            <p class="join-description">
                Hai quello che serve per far parte della squadra più king del web? 
                Mandaci la tua candidatura e diventa parte della squadra + da king del secolo!
            </p>
            <a href="mailto:cripsum@cripsum.com" class="join-email">
                <i class="fas fa-envelope me-2"></i>
                cripsum@cripsum.com
            </a>
            
            <div class="mystery-members">
                <div class="mystery-card">
                    <div class="mystery-icon">
                        <i class="fas fa-question"></i>
                    </div>
                    <p class="mystery-text">
                        Il prossimo potresti essere <strong>TU!</strong><br>
                        Scrivi per essere aggiunto alla lista dei king!
                    </p>
                </div>
                <div class="mystery-card">
                    <div class="mystery-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="mystery-text">
                        Cerchiamo talenti in ogni campo:<br>
                        <strong>Design • Sviluppo • Content • Marketing</strong>
                    </p>
                </div>
            </div>
        </section>
    </div>>

    <a href="#" class="scroll-indicator" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
        <i class="fas fa-arrow-up"></i>
    </a>

    <?php include '../includes/footer.php'; ?>
    <script>
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.team-member').forEach(member => {
            member.style.animationPlayState = 'paused';
            observer.observe(member);
        });

        function createFloatingElement() {
            const element = document.createElement('div');
            element.className = 'floating-element';
            element.style.left = Math.random() * 100 + '%';
            element.style.top = Math.random() * 100 + '%';
            element.style.animationDelay = Math.random() * 6 + 's';
            element.style.animationDuration = (Math.random() * 4 + 4) + 's';
            
            const colors = ['rgba(100, 200, 255, 0.3)', 'rgba(255, 100, 200, 0.3)', 'rgba(100, 255, 150, 0.3)'];
            element.style.background = colors[Math.floor(Math.random() * colors.length)];
            
            document.querySelector('.floating-elements').appendChild(element);
            
            setTimeout(() => {
                element.remove();
            }, 8000);
        }

        setInterval(createFloatingElement, 2000);

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallax = scrolled * 0.3;
            
            const chisiamoSection = document.querySelector('.chisiamo-section');
            if (chisiamoSection) {
                chisiamoSection.style.transform = `translateY(${parallax}px)`;
            }
        });

        document.querySelectorAll('.team-member').forEach(member => {
            member.addEventListener('mouseenter', function() {
                this.style.transition = 'all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            });
            
            member.addEventListener('mouseleave', function() {
                this.style.transition = 'all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            });
        });

        const scrollIndicator = document.querySelector('.scroll-indicator');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                scrollIndicator.style.opacity = '1';
                scrollIndicator.style.transform = 'translateY(0)';
            } else {
                scrollIndicator.style.opacity = '0';
                scrollIndicator.style.transform = 'translateY(20px)';
            }
        });

        scrollIndicator.style.opacity = '0';
        scrollIndicator.style.transform = 'translateY(20px)';
        scrollIndicator.style.transition = 'all 0.3s ease';
    </script>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
