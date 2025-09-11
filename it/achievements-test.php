<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per accedere agli achievement devi essere loggato";

    header('Location: accedi');
    exit();
}

$userId = $_SESSION['user_id'];

checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <title>Cripsum™ - Achievement</title>
        <style>
            img {
                border-radius: 10px;
            }

            .achievements-section {
                max-width: 85%;
                margin: 0 auto;
                padding: 2rem 0;
            }

            .stats-overview {
                background: linear-gradient(135deg, rgba(30, 30, 30, 0.95), rgba(20, 20, 20, 0.98));
                border-radius: 20px;
                border: 1px solid rgba(255, 255, 255, 0.2);
                backdrop-filter: blur(20px);
                padding: 2rem;
                margin-bottom: 3rem;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1.5rem;
                margin-top: 1.5rem;
            }

            .stat-card {
                background: rgba(255, 255, 255, 0.05);
                border-radius: 15px;
                padding: 1.5rem;
                text-align: center;
                border: 1px solid rgba(255, 255, 255, 0.1);
                transition: all 0.3s ease;
            }

            .stat-card:hover {
                transform: translateY(-5px);
                border-color: rgba(100, 200, 255, 0.3);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            }

            .stat-value {
                font-size: 2rem;
                font-weight: bold;
                color: #4fc3f7;
                margin-bottom: 0.5rem;
            }

            .stat-label {
                color: rgba(255, 255, 255, 0.8);
                font-size: 0.9rem;
            }

            .achievements-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 2rem;
                margin-top: 2rem;
            }

            .achievement-card {
                background: linear-gradient(135deg, rgba(30, 30, 30, 0.95), rgba(20, 20, 20, 0.98));
                border-radius: 20px;
                border: 1px solid rgba(255, 255, 255, 0.2);
                backdrop-filter: blur(20px);
                overflow: hidden;
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                position: relative;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                opacity: 0;
                transform: translateY(20px);
            }

            .achievement-card:hover {
                transform: translateY(-8px) scale(1.02);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 40px rgba(100, 200, 255, 0.1);
                border-color: rgba(100, 200, 255, 0.3);
            }

            .achievement-card.unlocked {
                border-color: rgba(76, 175, 80, 0.5);
            }

            .achievement-card.unlocked:hover {
                border-color: rgba(76, 175, 80, 0.8);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 40px rgba(76, 175, 80, 0.2);
            }

            .achievement-card.locked {
                opacity: 0.6;
                filter: grayscale(70%);
            }

            .achievement-header {
                position: relative;
                padding: 1.5rem;
                display: flex;
                align-items: center;
                gap: 1.5rem;
            }

            .achievement-icon {
                width: 80px;
                height: 80px;
                border-radius: 15px;
                transition: all 0.3s ease;
                border: 2px solid rgba(255, 255, 255, 0.2);
                object-fit: cover;
            }

            .achievement-card.unlocked .achievement-icon {
                border-color: rgba(76, 175, 80, 0.5);
                box-shadow: 0 0 20px rgba(76, 175, 80, 0.3);
            }

            .achievement-card.locked .achievement-icon {
                filter: grayscale(100%) blur(2px);
            }

            .achievement-info {
                flex: 1;
            }

            .achievement-title {
                font-size: 1.4rem;
                font-weight: 600;
                margin-bottom: 0.5rem;
                color: white;
            }

            .achievement-card.locked .achievement-title {
                color: rgba(255, 255, 255, 0.5);
            }

            .achievement-description {
                color: rgba(255, 255, 255, 0.8);
                line-height: 1.5;
                margin-bottom: 1rem;
            }

            .achievement-card.locked .achievement-description {
                color: rgba(255, 255, 255, 0.4);
            }

            .achievement-points {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                background: rgba(255, 193, 7, 0.2);
                color: #ffc107;
                padding: 0.3rem 0.8rem;
                border-radius: 20px;
                font-size: 0.9rem;
                font-weight: 500;
            }

            .achievement-status {
                position: absolute;
                top: 1rem;
                right: 1rem;
                padding: 0.5rem 1rem;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .achievement-status.unlocked {
                background: linear-gradient(135deg, #4caf50, #45a049);
                color: white;
            }

            .achievement-status.locked {
                background: rgba(158, 158, 158, 0.3);
                color: rgba(255, 255, 255, 0.6);
            }

            .achievement-progress {
                padding: 0 1.5rem 1.5rem;
            }

            .progress-info {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 0.5rem;
            }

            .progress-label {
                color: rgba(255, 255, 255, 0.8);
                font-size: 0.9rem;
            }

            .progress-value {
                color: #4fc3f7;
                font-weight: 600;
                font-size: 0.9rem;
            }

            .progress-bar {
                width: 100%;
                height: 8px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 10px;
                overflow: hidden;
                position: relative;
            }

            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #4fc3f7, #29b6f6);
                border-radius: 10px;
                transition: width 0.8s ease;
                position: relative;
            }

            .progress-fill::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
                animation: shimmer 2s infinite;
            }

            @keyframes shimmer {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }

            .achievement-unlock-date {
                padding: 1rem 1.5rem;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                background: rgba(76, 175, 80, 0.1);
                color: rgba(76, 175, 80, 0.9);
                font-size: 0.85rem;
                text-align: center;
            }

            .completion-summary {
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
                border-radius: 20px;
                border: 1px solid rgba(255, 255, 255, 0.12);
                backdrop-filter: blur(15px);
                padding: 2rem;
                margin-bottom: 2rem;
                text-align: center;
            }

            .completion-circle {
                width: 120px;
                height: 120px;
                margin: 0 auto 1rem;
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .circle-progress {
                transform: rotate(-90deg);
            }

            .circle-bg {
                fill: none;
                stroke: rgba(255, 255, 255, 0.1);
                stroke-width: 8;
            }

            .circle-fill {
                fill: none;
                stroke: #4fc3f7;
                stroke-width: 8;
                stroke-linecap: round;
                stroke-dasharray: 314;
                stroke-dashoffset: 314;
                transition: stroke-dashoffset 1s ease;
            }

            .circle-text {
                position: absolute;
                font-size: 1.5rem;
                font-weight: bold;
                color: #4fc3f7;
            }

            .category-filter {
                display: flex;
                justify-content: center;
                gap: 1rem;
                margin-bottom: 2rem;
                flex-wrap: wrap;
            }

            .filter-btn {
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.2);
                color: white;
                padding: 0.8rem 1.5rem;
                border-radius: 25px;
                cursor: pointer;
                transition: all 0.3s ease;
                font-size: 0.9rem;
            }

            .filter-btn:hover,
            .filter-btn.active {
                background: linear-gradient(135deg, #4fc3f7, #29b6f6);icon {
                    width: 60px;
                    height: 60px;
                }

                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                }

                .completion-circle {
                    width: 100px;
                    height: 100px;
                }
            }

            @media (max-width: 480px) {
                .achievements-grid {
                    grid-template-columns: 1fr;
                }

                .stats-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div class="paginainterachisiamo testobianco" style="padding-top: 7rem; padding-bottom: 4rem;">
            <div class="achievements-section">
                <!-- Header Section -->
                <div class="chisiamo-section fadeup"></div>
                    <h1 class="chisiamo-title">🏆 Achievement Sbloccati</h1>
                    <p class="chisiamo-subtitle">
                        Traccia i tuoi progressi e sblocca nuovi traguardi esplorando tutte le funzionalità di Cripsum™
                    </p>
                </div>

                <!-- Statistiche Utente -->
                <div class="stats-overview fadeup">
                    <h2 style="color: white; text-align: center; margin-bottom: 1rem;">🏆 Le tue Statistiche</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value" id="totalTimeDisplay">00:00</div>
                            <div class="stat-label">Tempo Totale</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" id="unlockedCountDisplay">0</div>
                            <div class="stat-
                .achievements-grid {
                    grid-template-columns: 1fr;
                }

                .stats-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div class="paginainterachisiamo testobianco" style="padding-top: 7rem; padding-bottom: 4rem;">
            <div class="achievements-section">
                <!-- Header Section -->
                <div class="chisiamo-section fadeup">
                    <h1 class="chisiamo-title">🏆 Achievement Sbloccati</h1>
                    <p class="chisiamo-subtitle">
                        Traccia i tuoi progressi e sblocca nuovi traguardi esplorando tutte le funzionalità di Cripsum™
                    </p>
                </div>

                <!-- Statistiche Utente -->
                <div class="stats-overview fadeup">
                    <h2 style="color: white; text-align: center; margin-bottom: 1rem;">🏆 Le tue Statistiche</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value" id="totalTimeDisplay">00:00</div>
                            <div class="stat-label">Tempo Totale</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" id="unlockedCountDisplay">0</div>
                            <div class="stat-label">Achievement Sbloccati</div>
                        </div>
                    </div>
                </div>

                <!-- Completamento Generale -->
                <div class="completion-summary fadeup">
                    <div class="completion-circle">
                        <svg class="circle-progress" width="120" height="120">
                            <circle class="circle-bg" cx="60" cy="60" r="50"></circle>
                            <circle class="circle-fill" cx="60" cy="60" r="50" id="completionCircle"></circle>
                        </svg>
                        <div class="circle-text" id="completionPercentage">0%</div>
                    </div>
                    <h3 style="color: white; margin-bottom: 0.5rem;">Completamento Achievement</h3>
                    <p style="color: rgba(255, 255, 255, 0.7);" id="completionText">Caricamento...</p>
                </div>

                <!-- Loading State -->
                <div id="loadingState" class="loading-container">
                    <div class="loading_white">
                        <div class="loading__dot_white"></div>
                        <div class="loading__dot_white"></div>
                        <div class="loading__dot_white"></div>
                    </div>
                    <p class="testobianco" style="text-align: center; margin-top: 1rem;">Caricamento achievement...</p>
                </div>

                <!-- Lista Achievement -->
                <div id="achievementsContainer" class="achievements-grid" style="display: none;">
                    <!-- Gli achievement verranno caricati qui dinamicamente -->
                </div>
            </div>
        </div>

        <?php include '../includes/footer.php'; ?>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../js/modeChanger.js"></script>

        <script>
        let allAchievements = [];
        let unlockedAchievements = [];
        let currentFilter = 'all';

        // Funzioni per gestire i cookie (coerenti con il tuo sistema esistente)
        function getCookie(name) {
            const cookies = document.cookie.split("; ");
            for (let cookie of cookies) {
                let [key, value] = cookie.split("=");
                if (key === name) return JSON.parse(value);
            }
            return null;
        }

        function getVideo(name) {
            return getCookie(name);
        }

        // Funzioni per ottenere statistiche dai cookie
        function getTimeSpent() {
            return parseInt(getCookie("timeSpent")) || 0;
        }

        function getDaysVisited() {
            return (getCookie("daysVisited") || []).length;
        }

        function getWatchedVideos() {
            return (getCookie("watchedVideos") || []).length;
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadAchievements();
            updateStatsFromCookies();
            startRealTimeTracking();
            setupFilters();
        });

        function updateStatsFromCookies() {
            // Aggiorna le statistiche basandoti sui cookie
            const timeSpent = getTimeSpent();
            const daysVisited = getDaysVisited();
            const videosWatched = getWatchedVideos();
            
            document.getElementById('totalTimeDisplay').textContent = formatTime(timeSpent);
            document.getElementById('daysVisitedDisplay').textContent = daysVisited;
            document.getElementById('videosWatchedDisplay').textContent = videosWatched;
        }

        async function loadAchievements() {
            try {
                const [unlockedRes, allRes] = await Promise.all([
                    fetch("../api/get_unlocked_achievement.php"),
                    fetch("../api/get_all_achievement.php")
                ]);

                unlockedAchievements = await unlockedRes.json();
                allAchievements = await allRes.json();

                document.getElementById('unlockedCountDisplay').textContent = unlockedAchievements.length;

                displayAchievements();
                updateCompletionStats();
                
                document.getElementById('loadingState').style.display = 'none';
                document.getElementById('achievementsContainer').style.display = 'grid';
                
                // Animazione fade-in
                const cards = document.querySelectorAll('.achievement-card');
                cards.forEach((card, index) => {
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 100);
                });
                
            } catch (error) {
                console.error('Errore nel caricamento degli achievement:', error);
                document.getElementById('loadingState').innerHTML = 
                    '<p class="testobianco">Errore nel caricamento degli achievement</p>';
            }
        }

        function displayAchievements() {
            const container = document.getElementById('achievementsContainer');
            
            const filteredAchievements = currentFilter === 'all' ? 
                allAchievements : 
                allAchievements.filter(ach => ach.categoria === currentFilter);

            container.innerHTML = filteredAchievements.map(achievement => {
                const unlocked = unlockedAchievements.find(a => a.id === achievement.id);
                const isUnlocked = !!unlocked;
                const progress = calculateProgress(achievement);
                
                return `
                    <div class="achievement-card ${isUnlocked ? 'unlocked' : 'locked'}" data-category="${achievement.categoria || 'generale'}">
                        <div class="achievement-status ${isUnlocked ? 'unlocked' : 'locked'}">
                            ${isUnlocked ? '✓ Sbloccato' : '🔒 Bloccato'}
                        </div>
                        
                        <div class="achievement-header">
                            <img src="../img/${achievement.img_url || 'default-achievement.png'}" 
                                 alt="${achievement.nome}" 
                                 class="achievement-icon">
                            <div class="achievement-info">
                                <h3 class="achievement-title">
                                    ${isUnlocked ? achievement.nome : '???'}
                                </h3>
                                <p class="achievement-description">
                                    ${achievement.descrizione}
                                </p>
                                <div class="achievement-points">
                                    ⭐ ${achievement.punti} punti
                                </div>
                            </div>
                        </div>

                        ${!isUnlocked && progress ? `
                        <div class="achievement-progress">
                            <div class="progress-info">
                                <span class="progress-label">Progresso</span>
                                <span class="progress-value">${progress.display || `${progress.current}/${progress.target}`}</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${Math.min((progress.current/progress.target * 100), 100).toFixed(1)}%"></div>
                            </div>
                        </div>
                        ` : ''}

                        ${isUnlocked && unlocked.data ? `
                        <div class="achievement-unlock-date">
                            🎉 Sbloccato il ${new Date(unlocked.data).toLocaleDateString('it-IT')} 
                            alle ${new Date(unlocked.data).toLocaleTimeString('it-IT')}
                        </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
        }

        function calculateProgress(achievement) {
            // Calcola il progresso basandosi sui cookie e sull'ID dell'achievement
            switch(achievement.id) {
                case 14: // 2 ore sul sito (7200 secondi)
                    const timeSpent = getTimeSpent();
                    return {
                        current: timeSpent,
                        target: 7200,
                        display: `${formatTime(timeSpent)} / ${formatTime(7200)}`
                    };
                    
                case 13: // 30 giorni visitati
                    const daysVisited = getCookie("daysVisited") || [];
                    return {
                        current: daysVisited.length,
                        target: 30
                    };
                    
                case 17: // Tutti i video guardati
                    const watchedVideos = getCookie("watchedVideos") || [];
                    const totalVideos = 10; // fallback statico
                    return {
                        current: watchedVideos.length,
                        target: totalVideos
                    };

                case 21: // 20 achievement sbloccati
                    return {
                        current: unlockedAchievements.length,
                        target: 20
                    };
                    
                case 12: // Visitare alle 3 del mattino
                    const now = new Date();
                    return now.getHours() === 3 ? {
                        current: 1,
                        target: 1,
                        display: "Visita ora!"
                    } : {
                        current: 0,
                        target: 1,
                        display: "Visita alle 3:00"
                    };
                    
                default:
                    return null;
            }
        }

        function updateCompletionStats() {
            const totalAchievements = allAchievements.length;
            const unlockedCount = unlockedAchievements.length;
            const percentage = totalAchievements > 0 ? (unlockedCount / totalAchievements * 100).toFixed(0) : 0;
            
            // Aggiorna il cerchio di completamento
            const circle = document.getElementById('completionCircle');
            const circumference = 2 * Math.PI * 50;
            const offset = circumference - (percentage / 100) * circumference;
            circle.style.strokeDashoffset = offset;
            
            // Aggiorna il testo
            document.getElementById('completionPercentage').textContent = percentage + '%';
            document.getElementById('completionText').textContent = 
                `${unlockedCount} su ${totalAchievements} achievement completati`;
        }

        function setupFilters() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            
            filterButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    filterButtons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    
                    currentFilter = btn.getAttribute('data-category');
                    displayAchievements();
                    
                    // Re-anima le card
                    const cards = document.querySelectorAll('.achievement-card');
                    cards.forEach((card, index) => {
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(20px)';
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }, index * 50);
                    });
                });
            });
        }

        function startRealTimeTracking() {
            // Aggiorna le statistiche dai cookie ogni secondo
            setInterval(() => {
                updateStatsFromCookies();
                
                // Ricarica il progresso degli achievement se ci sono cambiamenti
                const cards = document.querySelectorAll('.achievement-card:not(.unlocked)');
                if (cards.length > 0) {
                    displayAchievements();
                }
            }, 1000);
            
            // Verifica nuovi achievement ogni 10 secondi
            setInterval(checkForNewAchievements, 10000);
        }

        async function checkForNewAchievements() {
            try {
                const response = await fetch("../api/get_unlocked_achievement.php");
                const newUnlocked = await response.json();
                
                if (newUnlocked.length > unlockedAchievements.length) {
                    // Nuovo achievement sbloccato!
                    const newAchievement = newUnlocked.find(a => 
                        !unlockedAchievements.some(u => u.id === a.id)
                    );
                    
                    if (newAchievement) {
                        showAchievementNotification(newAchievement);
                    }
                    
                    unlockedAchievements = newUnlocked;
                    const unlockedCountEl = document.getElementById('unlockedCountDisplay');
                    if (unlockedCountEl) {
                        unlockedCountEl.textContent = unlockedAchievements.length;
                    }
                    displayAchievements();
                    updateCompletionStats();
                }
            } catch (error) {
                console.error('Errore nel controllo dei nuovi achievement:', error);
            }
        }

        function showAchievementNotification(achievement) {
            // Crea una notifica per il nuovo achievement sbloccato
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #4caf50, #45a049);
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                z-index: 10000;
                animation: slideInRight 0.5s ease;
                max-width: 300px;
            `;
            
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="font-size: 2rem;">🎉</div>
                    <div>
                        <div style="font-weight: bold; margin-bottom: 0.5rem;">Achievement Sbloccato!</div>
                        <div style="font-size: 0.9rem; opacity: 0.9;">${achievement.nome}</div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.5s ease';
                setTimeout(() => notification.remove(), 500);
            }, 4000);
        }

        function formatTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
        }

        // Aggiungi stili per le animazioni
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        </script>
    </body>
</html>