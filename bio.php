<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

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
        <?php include 'includes/head-import.php'; ?>
        <link rel="stylesheet" href="css/style-users.css?v=3" />
        <title>Profilo di cripsum</title>
        <script src="js/nomePagina.js"></script>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #16213e 100%);
                min-height: 100vh;
                color: white;
                overflow-x: hidden;
            }

            .bio-page {
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                position: relative;
                padding: 20px;
            }

            .background {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: -2;
            }

            .background video {     
                width: 100%;
                height: 100%;
                object-fit: cover;
                /* filter: blur(3px) brightness(0.3) contrast(1.2); */
            }

            .profile-container {
                background: rgba(0, 0, 0, 0.1);
                -webkit-backdrop-filter: blur(10px);
                backdrop-filter: blur(10px);
                border-radius: 10px;
                border: 2px solid rgb(15, 91, 255);
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), 
                           0 0 0 1px rgba(255, 255, 255, 0.05);
                width: 100%;
                max-width: 480px;
                padding: 40px;
                text-align: center;
                position: relative;
                overflow: hidden;
            }

            .user-username {
                font-size: 2rem;
                font-weight: 700;
                background: linear-gradient(135deg, #00d4ff, #7c3aed, #00d4ff);
                background-size: 200% 200%;
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                animation: gradientShift 3s ease-in-out infinite;
                margin-bottom: 8px;
                text-shadow: 0 0 30px rgba(0, 212, 255, 0.3);
            }

            @keyframes gradientShift {
                0%, 100% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
            }

            .profile-subtitle {
                color: #a0a0a0;
                font-size: 0.9rem;
                margin-bottom: 20px;
                font-weight: 400;
            }

            .profile-info {
                color: #e0e0e0;
                font-size: 0.95rem;
                line-height: 1.6;
                margin-bottom: 30px;
                font-weight: 400;
            }

            .profile-info span {
                color: #00d4ff;
                margin-right: 8px;
            }

            .discord-section {
                margin: 30px 0;
                background: rgba(88, 101, 242, 0.1);
                border: 1px solid rgba(88, 101, 242, 0.3);
                border-radius: 12px;
                padding: 20px;
                transition: all 0.3s ease;
            }

            .discord-section:hover {
                background: rgba(88, 101, 242, 0.15);
                transform: translateY(-2px);
                box-shadow: 0 10px 30px rgba(88, 101, 242, 0.2);
            }

            .social-links {
                display: flex;
                justify-content: center;
                gap: 15px;
                margin-top: 30px;
            }

            .social-link {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 45px;
                height: 45px;
                background: rgba(255, 255, 255, 0.05);
                border: 2px solid rgb(15, 91, 255);
                border-radius: 12px;
                color: white;
                text-decoration: none;
                font-size: 1.2rem;
                font-weight: 600;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }

            .social-link i {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 100%;
                height: 100%;
            }

            .social-link::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
                transition: left 0.5s ease;
            }

            .social-link:hover {
                transform: translateY(-3px);
                background: rgba(255, 255, 255, 0.1);
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            }

            .social-link:hover::before {
                left: 100%;
            }

            .social-link:nth-child(1):hover {
                background: linear-gradient(45deg, #ff0050, #ff4081);
                border-color: #ff4081;
            }

            .social-link:nth-child(2):hover {
                background: linear-gradient(45deg, #0088cc, #00a0e9);
                border-color: #00a0e9;
            }

            .social-link:nth-child(3):hover {
                background: linear-gradient(45deg, #5865f2, #7289da);
                border-color: #7289da;
            }

            .floating-elements {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                z-index: -1;
            }

            .floating-particle {
                position: absolute;
                width: 4px;
                height: 4px;
                background: rgba(0, 212, 255, 0.6);
                border-radius: 50%;
                animation: float 6s infinite linear;
            }

            .floating-particle:nth-child(2) {
                background: rgba(124, 58, 237, 0.6);
                animation-delay: -2s;
                animation-duration: 8s;
            }

            .floating-particle:nth-child(3) {
                background: rgba(0, 212, 255, 0.4);
                animation-delay: -4s;
                animation-duration: 10s;
            }

            @keyframes float {
                0% {
                    transform: translateY(100vh) rotate(0deg);
                    opacity: 0;
                }
                10% {
                    opacity: 1;
                }
                90% {
                    opacity: 1;
                }
                100% {
                    transform: translateY(-100px) rotate(360deg);
                    opacity: 0;
                }
            }
            .navbarutenti {
                background: rgba(255, 255, 255, 0.1);
                -webkit-backdrop-filter: blur(20px);
                backdrop-filter: blur(20px);
                border: 2px solid rgb(15, 91, 255);
            }

            .dropdownutenti .dropdown-menu {
                background: rgba(15, 91, 255, 0.5);
                border: 2px solid rgb(15, 91, 255);
            }

            @media (max-width: 2000px) {
                .bio-page {
                    margin-top: 7rem;
                }
                
            }

            @media (max-width: 768px) {
                .bio-page {
                    margin-top: 7rem;
                }


                .profile-container {
                    margin: 20px;
                    padding: 30px 25px;
                    max-width: calc(100vw - 40px);
                }

                .profile-username {
                    font-size: 1.8rem;
                }

                .social-links {
                    gap: 12px;
                }

                .social-link {
                    width: 42px;
                    height: 42px;
                    font-size: 1.1rem;
                }
            }

            @media (max-width: 480px) {
                .bio-page {
                    padding: 15px;
                    margin-top: 5rem;
                }

                .profile-container {
                    padding: 25px 20px;
                    margin: 15px;
                }

                .profile-username {
                    font-size: 1.6rem;
                }

            }

            .profile-container {
                animation: subtlePulse 4s ease-in-out infinite;
            }

            @keyframes subtlePulse {
                0%, 100% {
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), 
                               0 0 0 1px rgba(255, 255, 255, 0.05);
                }
                50% {
                    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.6), 
                               0 0 0 1px rgba(255, 255, 255, 0.08),
                               0 0 50px rgba(0, 212, 255, 0.1);
                }
            }

            .audio-controls {
                margin-top: 25px;
                padding: 20px;
                background: rgba(0, 0, 0, 0.1);
                -webkit-backdrop-filter: blur(10px);
                backdrop-filter: blur(10px);
                border-radius: 15px;
                border: 2px solid ;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                transition: all 0.3s ease;
                border-color: rgb(15, 91, 255);
            }

            .audio-controls:hover {
                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4), 0 0 30px rgba(15, 91, 255, 0.1);
            }

            .song-info {
                text-align: center;
                margin-bottom: 15px;
                color: white;
                font-weight: 700;
                font-size: 0.95rem;
                font-weight: bold;
            }

            .progress-container {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 15px;
            }

            .time-display {
                color: #a0a0a0;
                font-size: 0.8rem;
                font-weight: 500;
                min-width: 40px;
                text-align: center;
            }

            .progress-slider {
                flex: 1;
                height: 6px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 3px;
                outline: none;
                -webkit-appearance: none;
                appearance: none;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .progress-slider:hover {
                background: rgba(255, 255, 255, 0.15);
            }

            .progress-slider::-webkit-slider-thumb {
                -webkit-appearance: none;
                width: 18px;
                height: 18px;
                background: linear-gradient(135deg, #00d4ff, #7c3aed);
                border-radius: 50%;
                cursor: pointer;
                box-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
                transition: all 0.3s ease;
            }

            .progress-slider::-webkit-slider-thumb:hover {
                transform: scale(1.1);
                box-shadow: 0 0 15px rgba(0, 212, 255, 0.7);
            }

            .progress-slider::-moz-range-thumb {
                width: 18px;
                height: 18px;
                background: linear-gradient(135deg, #00d4ff, #7c3aed);
                border-radius: 50%;
                cursor: pointer;
                border: none;
                box-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
            }

            .bottom-controls {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
            }

            .volume-container {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .player-controls {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .audio-btn {
                background: rgba(0, 212, 255, 0.1);
                border: 2px solid rgba(15, 91, 255, 0.5);
                border-radius: 8px;
                width: 35px;
                height: 35px;
                color: white;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.9rem;
                position: relative;
                overflow: hidden;
            }

            .audio-btn:hover {
                background: rgba(0, 212, 255, 0.3);
                border-color: rgb(15, 91, 255);
                transform: scale(1.05);
                box-shadow: 0 0 15px rgba(15, 91, 255, 0.3);
            }

            .audio-btn:active {
                transform: scale(0.95);
            }

            .play-pause-btn {
                width: 40px;
                height: 40px;
                font-size: 1.1rem;
                background: rgba(0, 212, 255, 0.2);
                border-radius: 10px;
            }
            .volume-slider {
                width: 80px;
                height: 4px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 2px;
                outline: none;
                -webkit-appearance: none;
                appearance: none;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .volume-slider:hover {
                background: rgba(255, 255, 255, 0.15);
            }

            .volume-slider::-webkit-slider-thumb {
                -webkit-appearance: none;
                width: 14px;
                height: 14px;
                background: rgb(15, 91, 255);
                border-radius: 50%;
                cursor: pointer;
                box-shadow: 0 0 8px rgba(15, 91, 255, 0.5);
                transition: all 0.3s ease;
            }

            .volume-slider::-webkit-slider-thumb:hover {
                transform: scale(1.1);
                box-shadow: 0 0 12px rgba(15, 91, 255, 0.7);
            }

            .volume-slider::-moz-range-thumb {
                width: 14px;
                height: 14px;
                background: rgb(15, 91, 255);
                border-radius: 50%;
                cursor: pointer;
                border: none;
                box-shadow: 0 0 8px rgba(15, 91, 255, 0.5);
            }

            @media (max-width: 480px) {
                .audio-controls {
                    padding: 15px;
                }

                .bottom-controls {
                    flex-direction: column;
                    gap: 15px;
                }

                .player-controls {
                    gap: 10px;
                }

                .audio-btn {
                    width: 32px;
                    height: 32px;
                    font-size: 0.8rem;
                }

                .play-pause-btn {
                    width: 38px;
                    height: 38px;
                    font-size: 1rem;
                }

                .volume-slider {
                    width: 60px;
                }

                .time-display {
                    font-size: 0.75rem;
                    min-width: 35px;
                }
            }
        </style>
    </head>
    <body>
        <?php include 'includes/navbar-bio.php'; ?>
                    <div class="background">
                <video autoplay muted loop>
                    <source src="vid/Shorekeeper Wallpaper 4K Loop.mp4" type="video/mp4" />
                    Your browser does not support the video tag.
                </video>
            </div>

        <div class="bio-page">

            <div class="floating-elements">
                <div class="floating-particle" style="left: 10%; animation-delay: 0s;"></div>
                <div class="floating-particle" style="left: 20%; animation-delay: -1s;"></div>
                <div class="floating-particle" style="left: 30%; animation-delay: -2s;"></div>
                <div class="floating-particle" style="left: 40%; animation-delay: -3s;"></div>
                <div class="floating-particle" style="left: 50%; animation-delay: -4s;"></div>
                <div class="floating-particle" style="left: 60%; animation-delay: -5s;"></div>
                <div class="floating-particle" style="left: 70%; animation-delay: -1.5s;"></div>
                <div class="floating-particle" style="left: 80%; animation-delay: -2.5s;"></div>
                <div class="floating-particle" style="left: 90%; animation-delay: -3.5s;"></div>
            </div>

            <div class="profile-container">
                <div class="user-avatar">
                    <img src="includes/get_pfp.php?id=<?php echo $user_cercato_id; ?>" alt="Foto Profilo">
                </div>
                
                <h1 class="user-username text-center" style="text-align: center;">cripsum</h1>
                <p class="profile-subtitle">AKA - Leo, Sofficino alla pesca</p>
                
                <div class="profile-info">
                    <div><span>⟡</span>🇮🇹 | 20</div>
                    <div><span>⟡</span>Video Editor</div>
                    <div><span>⟡</span>Developer</div>
                </div>

                <div class="social-links">
                    <a href="https://tiktok.cripsum.com" target="_blank" class="social-link" title="TikTok">
                       <i class="fab fa-tiktok" style="display: flex; align-items: center; justify-content: center;"></i>
                    </a>
                    <a href="https://t.me/sburragrigliata" target="_blank" class="social-link" title="Telegram">
                       <i class="fab fa-telegram-plane" style="display: flex; align-items: center; justify-content: center;"></i>
                    </a>
                    <a href="https://discord.cripsum.com" target="_blank" class="social-link" title="Discord">
                           <i class="fab fa-discord" style="display: flex; align-items: center; justify-content: center;"></i>
                    </a>
                </div>

                    <div class="discord-box" id="discordBox">
                        <?php include 'includes/discord_status.php?discordId=963536045180350474'; ?>
                    </div>

                <div class="audio-controls">
                    <div class="song-info">
                        <i class="fas fa-music"></i> To the Shore's end
                    </div>
                    
                    <div class="progress-container">
                        <span id="currentTime" class="time-display">0:00</span>
                        <input type="range" id="progressSlider" min="0" max="100" value="0" class="progress-slider" title="Posizione Canzone">
                        <span id="totalTime" class="time-display">0:00</span>
                    </div>

                    <div class="bottom-controls">
                        <div class="volume-container">
                            <button id="volumeBtn" class="audio-btn" title="Muto/Volume">
                                <i class="fas fa-volume-up" id="volumeIcon"></i>
                            </button>
                            <input type="range" id="volumeSlider" min="0" max="1" step="0.01" value="0.1" class="volume-slider" title="Volume">
                        </div>

                        <div class="player-controls">
                            <button id="prevBtn" class="audio-btn" title="Canzone Precedente">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button id="playPauseBtn" class="audio-btn play-pause-btn" title="Play/Pausa">
                                <i class="fas fa-play" id="playPauseIcon"></i>
                            </button>
                            <button id="nextBtn" class="audio-btn" title="Canzone Successiva">
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
                <audio id="background-audio" preload="metadata" autoplay loop>
                        <source src="audio/godo.mp3" type="audio/mpeg">
                </audio>
        <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const audio = document.getElementById('background-audio');
                    const playPauseBtn = document.getElementById('playPauseBtn');
                    const playPauseIcon = document.getElementById('playPauseIcon');
                    const prevBtn = document.getElementById('prevBtn');
                    const nextBtn = document.getElementById('nextBtn');
                    const volumeBtn = document.getElementById('volumeBtn');
                    const volumeIcon = document.getElementById('volumeIcon');
                    const volumeSlider = document.getElementById('volumeSlider');
                    const progressSlider = document.getElementById('progressSlider');
                    const currentTimeDisplay = document.getElementById('currentTime');
                    const totalTimeDisplay = document.getElementById('totalTime');
                    
                    let isPlaying = false;
                    let isMuted = false;
                    let isDragging = false;
                    let hasUserInteracted = false;
                    playPauseIcon.className = 'fas fa-play';

                    function tryAutoplay() {
                        if (!hasUserInteracted) return;
                        
                        audio.play().then(() => {
                            playPauseIcon.className = 'fas fa-pause';
                            isPlaying = true;
                        }).catch(err => {
                            console.log('Autoplay prevented:', err);
                            playPauseIcon.className = 'fas fa-play';
                            isPlaying = false;
                        });
                    }

                    function enableAutoplay() {
                        hasUserInteracted = true;
                        tryAutoplay();
                        document.removeEventListener('click', enableAutoplay);
                        document.removeEventListener('keydown', enableAutoplay);
                        document.removeEventListener('touchstart', enableAutoplay);
                    }

                    document.addEventListener('click', enableAutoplay);
                    document.addEventListener('keydown', enableAutoplay);
                    document.addEventListener('touchstart', enableAutoplay);
                    playPauseIcon.className = 'fas fa-pause';
                    
                    function formatTime(seconds) {
                        const minutes = Math.floor(seconds / 60);
                        const remainingSeconds = Math.floor(seconds % 60);
                        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
                    }
                    
                    function updateProgress() {
                        if (!isDragging && audio.duration) {
                            const progress = (audio.currentTime / audio.duration) * 100;
                            progressSlider.value = progress;
                            currentTimeDisplay.textContent = formatTime(audio.currentTime);
                        }
                    }
                    
                    audio.addEventListener('loadedmetadata', function() {
                        totalTimeDisplay.textContent = formatTime(audio.duration);
                        audio.volume = volumeSlider.value;
                    });
                    
                    audio.addEventListener('timeupdate', updateProgress);

                    playPauseBtn.addEventListener('click', function() {
                        if (isPlaying) {
                            audio.pause();
                            playPauseIcon.className = 'fas fa-play';
                            isPlaying = false;
                        } else {
                            audio.play().then(() => {
                                playPauseIcon.className = 'fas fa-pause';
                                isPlaying = true;
                            }).catch(err => {
                                console.error('Error playing audio:', err);
                            });
                        }
                    });
                    
                    volumeBtn.addEventListener('click', function() {
                        if (isMuted) {
                            audio.muted = false;
                            volumeIcon.className = audio.volume === 0 ? 'fas fa-volume-mute' : 'fas fa-volume-up';
                            isMuted = false;
                        } else {
                            audio.muted = true;
                            volumeIcon.className = 'fas fa-volume-mute';
                            isMuted = true;
                        }
                    });
                    
                    volumeSlider.addEventListener('input', function() {
                        audio.volume = this.value;
                        if (this.value == 0) {
                            volumeIcon.className = 'fas fa-volume-mute';
                        } else if (this.value < 0.5) {
                            volumeIcon.className = 'fas fa-volume-down';
                        } else {
                            volumeIcon.className = 'fas fa-volume-up';
                        }
                        if (isMuted) {
                            audio.muted = false;
                            isMuted = false;
                        }
                    });

                    progressSlider.addEventListener('mousedown', function() {
                        isDragging = true;
                    });
                    
                    progressSlider.addEventListener('mouseup', function() {
                        isDragging = false;
                        if (audio.duration) {
                            const newTime = (this.value / 100) * audio.duration;
                            audio.currentTime = newTime;
                        }
                    });
                    
                    progressSlider.addEventListener('input', function() {
                        if (isDragging && audio.duration) {
                            const newTime = (this.value / 100) * audio.duration;
                            currentTimeDisplay.textContent = formatTime(newTime);
                        }
                    });
                    
                    prevBtn.addEventListener('click', function() {
                        console.log('Previous track');
                    });
                    
                    nextBtn.addEventListener('click', function() {
                        console.log('Next track');
                    });
                    
                    audio.addEventListener('ended', function() {
                        playPauseIcon.className = 'fas fa-play';
                        isPlaying = false;
                        progressSlider.value = 0;
                        currentTimeDisplay.textContent = '0:00';
                    });
                });

            fetch('includes/discord_status.php?discordId=963536045180350474')
                .then(r => r.text())
                .then(html => {
                    const discordBox = document.querySelector('.discord-box');
                    if (discordBox) {
                        discordBox.innerHTML = html;
                        initActivityCarousel();
                    }
                })
                .catch(err => console.error('Errore aggiornamento Discord status:', err));

            setInterval(() => {
                fetch('includes/discord_status.php?discordId=963536045180350474')
                    .then(r => r.text())
                    .then(html => {
                        const discordBox = document.querySelector('.discord-box');
                        if (discordBox) {
                            discordBox.innerHTML = html;
                            initActivityCarousel();
                        }
                    })
                    .catch(err => console.error('Errore aggiornamento Discord status:', err));
            }, 30000);

            function initActivityCarousel() {
                const slides = document.querySelectorAll(".activity-item");
                if (slides.length <= 1) return;

                let current = 0;

                slides.forEach((slide, index) => {
                    if (index !== 0) {
                        slide.style.display = "none";
                    }
                });

                setInterval(() => {
                    if (slides.length > 1) {
                        slides[current].style.display = "none";
                        current = (current + 1) % slides.length;
                        slides[current].style.display = "flex";
                    }
                }, 4000);
            }

            document.addEventListener("DOMContentLoaded", () => {
                initActivityCarousel();
            });

            function copyProfileLink(type) {
                const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '');
                let url;
                
                if (type === 'username') {
                    url = `${baseUrl}/cripsum`;
                } else {
                    url = `${baseUrl}/user/<?php echo $user_cercato_id; ?>`;
                }
                
                navigator.clipboard.writeText(url).then(() => {
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
                    const textArea = document.createElement('textarea');
                    textArea.value = url;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                });
            }

            document.addEventListener('mousemove', (e) => {
                const container = document.querySelector('.profile-container');
                const rect = container.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                
                const rotateX = (y / rect.height) * 1;
                const rotateY = (x / rect.width) * -1;
                
                container.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            });

            document.addEventListener('mouseleave', () => {
                const container = document.querySelector('.profile-container');
                container.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
            });

            document.addEventListener('DOMContentLoaded', function() {
                function updateTimestamps() {
                    const timestamps = document.querySelectorAll('.activity-timestamp');
                    
                    timestamps.forEach(function(element) {
                        const startTime = element.getAttribute('data-start');
                        const endTime = element.getAttribute('data-end');
                        
                        if (startTime || endTime) {
                            const now = Math.floor(Date.now() / 1000);
                            
                            if (startTime) {
                                const start = Math.floor(startTime / 1000);
                                const elapsed = now - start;
                                
                                const hours = Math.floor(elapsed / 3600);
                                const minutes = Math.floor((elapsed % 3600) / 60);
                                const seconds = elapsed % 60;
                                
                                if (hours > 0) {
                                    element.textContent = String(hours).padStart(2, '0') + ':' + 
                                                        String(minutes).padStart(2, '0') + ':' + 
                                                        String(seconds).padStart(2, '0') + ' elapsed';
                                } else {
                                    element.textContent = String(minutes).padStart(2, '0') + ':' + 
                                                        String(seconds).padStart(2, '0') + ' elapsed';
                                }
                            } else if (endTime) {
                                const end = Math.floor(endTime / 1000);
                                const remaining = end - now;
                                
                                if (remaining > 0) {
                                    const hours = Math.floor(remaining / 3600);
                                    const minutes = Math.floor((remaining % 3600) / 60);
                                    const seconds = remaining % 60;
                                    
                                    if (hours > 0) {
                                        element.textContent = String(hours).padStart(2, '0') + ':' + 
                                                            String(minutes).padStart(2, '0') + ':' + 
                                                            String(seconds).padStart(2, '0') + ' left';
                                    } else {
                                        element.textContent = String(minutes).padStart(2, '0') + ':' + 
                                                            String(seconds).padStart(2, '0') + ' left';
                                    }
                                }
                            }
                        }
                    });
                }
                
                updateTimestamps();
                setInterval(updateTimestamps, 1000);
            });
            
        </script>

        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="js/modeChanger.js"></script>
    </body>
</html>