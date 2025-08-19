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
    <title>Profilo di <?php echo htmlspecialchars($user['username']); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0a0a0a;
            min-height: 100vh;
            overflow-x: hidden;
            color: #ffffff;
            position: relative;
        }

        /* Animated Background */
        .background-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: radial-gradient(circle at 20% 50%, rgba(120, 119, 255, 0.15) 0%, transparent 70%),
                        radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.1) 0%, transparent 70%),
                        radial-gradient(circle at 40% 80%, rgba(119, 255, 255, 0.12) 0%, transparent 70%);
        }

        .stars {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
        }

        .star {
            position: absolute;
            width: 2px;
            height: 2px;
            background: #ffffff;
            border-radius: 50%;
            animation: twinkle 4s ease-in-out infinite;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.2; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.5); }
        }

        /* Main Container */
        .main-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* Profile Card */
        .profile-card {
            background: rgba(15, 15, 35, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 48px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 32px 64px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(120, 119, 255, 0.6), transparent);
        }

        /* Profile Avatar Section */
        .avatar-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 32px;
        }

        .avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
        }

        .avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 3px solid rgba(120, 119, 255, 0.3);
            object-fit: cover;
            box-shadow: 0 8px 32px rgba(120, 119, 255, 0.2);
        }

        .status-dot {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 24px;
            height: 24px;
            background: #3ba55c;
            border: 4px solid rgba(15, 15, 35, 0.9);
            border-radius: 50%;
            box-shadow: 0 0 12px rgba(59, 165, 92, 0.4);
        }

        .level-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, #7c63ff, #5b47d6);
            color: white;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 12px;
            border: 2px solid rgba(15, 15, 35, 0.9);
            box-shadow: 0 4px 12px rgba(124, 99, 255, 0.3);
        }

        /* Username and Info */
        .username {
            font-size: 32px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #7c63ff, #ff6b9d, #4ecdc4);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gradientFlow 6s ease-in-out infinite;
        }

        @keyframes gradientFlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .user-info {
            text-align: center;
            margin-bottom: 24px;
        }

        .user-role {
            color: #a0a0a0;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .user-details {
            color: #c8c8c8;
            font-size: 15px;
            line-height: 1.6;
        }

        /* Status Cards */
        .status-section {
            margin: 32px 0;
        }

        .status-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .status-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(120, 119, 255, 0.3);
            transform: translateY(-2px);
        }

        .status-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .status-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: linear-gradient(135deg, #7c63ff, #5b47d6);
            box-shadow: 0 4px 16px rgba(124, 99, 255, 0.2);
        }

        .status-title {
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
        }

        .status-subtitle {
            font-size: 13px;
            color: #a0a0a0;
            margin-top: 2px;
        }

        .status-description {
            color: #b8b8b8;
            font-size: 14px;
            line-height: 1.4;
        }

        /* Discord Activity Card */
        .discord-card {
            background: linear-gradient(135deg, rgba(88, 101, 242, 0.15), rgba(88, 101, 242, 0.05));
            border: 1px solid rgba(88, 101, 242, 0.2);
        }

        .discord-icon {
            background: linear-gradient(135deg, #5865f2, #4f46e5);
        }

        /* Social Links */
        .social-section {
            margin-top: 32px;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            color: #ffffff;
            text-decoration: none;
            font-size: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .social-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .social-link:hover::before {
            left: 100%;
        }

        .social-link:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            border-color: rgba(120, 119, 255, 0.4);
        }

        /* Links Section */
        .links-section {
            margin: 24px 0;
        }

        .custom-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            color: #ffffff;
            text-decoration: none;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }

        .custom-link:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(120, 119, 255, 0.3);
            transform: translateX(4px);
            color: #ffffff;
        }

        .link-icon {
            font-size: 16px;
            opacity: 0.8;
        }

        .link-text {
            font-size: 14px;
            font-weight: 500;
        }

        /* Server Info Card */
        .server-card {
            background: linear-gradient(135deg, rgba(255, 165, 0, 0.1), rgba(255, 140, 0, 0.05));
            border: 1px solid rgba(255, 165, 0, 0.2);
        }

        .server-icon {
            background: linear-gradient(135deg, #ffa500, #ff8c00);
        }

        /* Music Player Card */
        .music-card {
            background: linear-gradient(135deg, rgba(29, 185, 84, 0.15), rgba(29, 185, 84, 0.05));
            border: 1px solid rgba(29, 185, 84, 0.2);
        }

        .music-icon {
            background: linear-gradient(135deg, #1db954, #1ed760);
        }

        .music-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
        }

        .progress-bar {
            flex: 1;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-fill {
            width: 35%;
            height: 100%;
            background: linear-gradient(90deg, #1db954, #1ed760);
            transition: width 0.3s ease;
        }

        .time-display {
            font-size: 12px;
            color: #a0a0a0;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .profile-card {
                padding: 32px 24px;
                margin: 16px;
            }

            .username {
                font-size: 28px;
            }

            .social-links {
                gap: 8px;
            }

            .social-link {
                width: 48px;
                height: 48px;
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            .profile-card {
                padding: 24px 16px;
            }

            .avatar-container {
                width: 100px;
                height: 100px;
            }

            .username {
                font-size: 24px;
            }
        }

        /* Smooth Entrance Animation */
        .profile-card {
            animation: slideUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Animated Background -->
    <div class="background-container"></div>
    <div class="stars" id="stars"></div>

    <div class="main-container">
        <div class="profile-card">
            <!-- Avatar Section -->
            <div class="avatar-section">
                <div class="avatar-container">
                    <img src="includes/get_pfp.php?id=<?php echo $user_cercato_id; ?>" alt="Profile Picture" class="avatar">
                    <div class="status-dot"></div>
                    <div class="level-badge">170</div>
                </div>
                <h1 class="username"><?php echo htmlspecialchars($user['username']); ?></h1>
                <div class="user-info">
                    <div class="user-role">🇮🇹 19</div>
                    <div class="user-details">
                        ♦ video editor<br>
                        ♦ developer
                    </div>
                </div>
            </div>

            <!-- Status Section -->
            <div class="status-section">
                <!-- Discord Status -->
                <div class="status-card discord-card">
                    <div class="status-header">
                        <div class="status-icon discord-icon">💬</div>
                        <div>
                            <div class="status-title">@cripsum</div>
                            <div class="status-subtitle">playing a ♥</div>
                        </div>
                    </div>
                    <div class="custom-link">
                        <span class="link-icon">🔗</span>
                        <span class="link-text">My website</span>
                    </div>
                </div>

                <!-- Current Activity -->
                <div class="status-card">
                    <div class="status-header">
                        <div class="status-icon">🎨</div>
                        <div>
                            <div class="status-title">After Effects</div>
                            <div class="status-subtitle">Untitled Project.aep</div>
                        </div>
                    </div>
                    <div class="status-description">Composizione 1 (0)</div>
                </div>

                <!-- Server Status -->
                <div class="status-card server-card">
                    <div class="status-header">
                        <div class="status-icon server-icon">🏠</div>
                        <div>
                            <div class="status-title">cripsum's tavern</div>
                            <div class="status-subtitle">🟢 Online • 77 Members</div>
                        </div>
                    </div>
                    <a href="#" class="custom-link">
                        <span class="link-icon">🚪</span>
                        <span class="link-text">Join Server</span>
                    </a>
                </div>

                <!-- Music Player -->
                <div class="status-card music-card">
                    <div class="status-header">
                        <div class="status-icon music-icon">🎵</div>
                        <div>
                            <div class="status-title">godo</div>
                            <div class="status-subtitle">Now Playing</div>
                        </div>
                    </div>
                    <div class="music-controls">
                        <span class="time-display">01:18</span>
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <span class="time-display">04:18</span>
                    </div>
                </div>
            </div>

            <!-- Discord Integration -->
            <div class="discord-status" id="discordStatus">
                <?php include 'includes/discord_status.php?discordId=963536045180350474'; ?>
            </div>

            <!-- Social Links -->
            <div class="social-section">
                <div class="social-links">
                    <a href="#" class="social-link" title="Website">🌐</a>
                    <a href="https://tiktok.cripsum.com" target="_blank" class="social-link" title="TikTok">🎵</a>
                    <a href="#" class="social-link" title="YouTube">📺</a>
                    <a href="https://discord.cripsum.com" target="_blank" class="social-link" title="Discord">💬</a>
                    <a href="#" class="social-link" title="Instagram">📷</a>
                    <a href="#" class="social-link" title="Spotify">🎶</a>
                    <a href="#" class="social-link" title="GitHub">⚡</a>
                    <a href="https://t.me/sburragrigliata" target="_blank" class="social-link" title="Telegram">📱</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Create animated stars
        function createStars() {
            const starsContainer = document.getElementById('stars');
            const numberOfStars = 80;

            for (let i = 0; i < numberOfStars; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.animationDelay = Math.random() * 4 + 's';
                star.style.animationDuration = (Math.random() * 3 + 2) + 's';
                starsContainer.appendChild(star);
            }
        }

        // Discord status update
        function updateDiscordStatus() {
            fetch('includes/discord_status.php?discordId=963536045180350474')
                .then(r => r.text())
                .then(html => {
                    const discordStatus = document.getElementById('discordStatus');
                    if (discordStatus) {
                        discordStatus.innerHTML = html;
                    }
                })
                .catch(err => console.error('Error updating Discord status:', err));
        }

        // Animate progress bar
        function animateProgressBar() {
            const progressFill = document.querySelector('.progress-fill');
            if (progressFill) {
                let width = 35;
                const interval = setInterval(() => {
                    width = (width + 0.5) % 100;
                    progressFill.style.width = width + '%';
                }, 500);
            }
        }

        // Initialize everything
        document.addEventListener('DOMContentLoaded', function() {
            createStars();
            updateDiscordStatus();
            animateProgressBar();
            
            // Update Discord status every 30 seconds
            setInterval(updateDiscordStatus, 30000);
            
            // Add hover effects to cards
            const cards = document.querySelectorAll('.status-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-2px)';
                });
            });
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