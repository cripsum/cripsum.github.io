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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Whitney', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            overflow-x: hidden;
            color: white;
        }

        /* Animated Background */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            background: radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 40% 40%, rgba(120, 200, 255, 0.2) 0%, transparent 50%);
        }

        .stars {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: transparent;
        }

        .star {
            position: absolute;
            background: white;
            clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
            animation: twinkle 3s infinite;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 20px;
            left: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 12px 24px;
            z-index: 1000;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        /* Main Profile Container */
        .profile-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 120px 20px 40px;
        }

        .profile-card {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
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
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
        }

        /* Profile Picture */
        .profile-picture {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 24px;
        }

        .profile-picture img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.2);
            object-fit: cover;
            object-position: center;
        }

        .status-indicator {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 20px;
            height: 20px;
            background: #43b581;
            border: 3px solid rgba(0, 0, 0, 0.8);
            border-radius: 50%;
        }

        /* Profile Info */
        .profile-info {
            text-align: center;
            margin-bottom: 32px;
        }

        .username {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(45deg, #7289da, #ffffff, #43b581);
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gradientShift 3s ease-in-out infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .nickname {
            color: #b9bbbe;
            font-size: 16px;
            margin-bottom: 16px;
            font-style: italic;
        }

        .user-details {
            color: #dcddde;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        /* Discord Status */
        .discord-status {
            background: rgba(114, 137, 218, 0.1);
            border: 1px solid rgba(114, 137, 218, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin: 24px 0;
            position: relative;
        }

        .discord-status::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(114, 137, 218, 0.8), transparent);
        }

        /* Activity Card */
        .activity-card {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 16px;
            margin: 16px 0;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
        }

        .activity-card:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .activity-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            background: linear-gradient(45deg, #7289da, #43b581);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .activity-info h4 {
            color: white;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .activity-info p {
            color: #b9bbbe;
            font-size: 14px;
        }

        /* Social Links */
        .social-links {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-top: 32px;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            text-decoration: none;
            font-size: 20px;
            transition: all 0.3s ease;
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
            transition: left 0.5s ease;
        }

        .social-link:hover::before {
            left: 100%;
        }

        .social-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin: 24px 0;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px 16px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #43b581;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: #b9bbbe;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .profile-card {
                padding: 24px;
                margin: 0 16px;
            }

            .username {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .social-links {
                gap: 12px;
            }
        }

        /* Hover Effects */
        .profile-card:hover {
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.6);
            transform: translateY(-5px);
            transition: all 0.4s ease;
        }

        /* Loading Animation */
        .fade-in {
            animation: fadeIn 1s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
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
    <div class="background"></div>
    <div class="stars" id="stars"></div>

    <div class="profile-container">
        <div class="profile-card fade-in">
            <!-- Profile Picture -->
            <div class="profile-picture">
                <img src="includes/get_pfp.php?id=<?php echo $user_cercato_id; ?>" alt="Foto Profilo">
                <div class="status-indicator"></div>
            </div>

            <!-- Profile Info -->
            <div class="profile-info">
                <h1 class="username"><?php echo htmlspecialchars($user['username']); ?></h1>
                <p class="nickname">AKA - Leo, Sofficino alla pesca</p>
                <div class="user-details">
                    🇮🇹 Italy | 19 years old<br>
                    💼 Video Editor & Developer<br>
                    ⚡ Creative Professional
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user['num_achievement']; ?></div>
                    <div class="stat-label">Achievements</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user['num_personaggi']; ?></div>
                    <div class="stat-label">Characters</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user['soldi']; ?></div>
                    <div class="stat-label">Credits</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo date('Y', strtotime($user['data_creazione'])); ?></div>
                    <div class="stat-label">Member Since</div>
                </div>
            </div>

            <!-- Discord Status -->
            <div class="discord-status" id="discordStatus">
                <?php include 'includes/discord_status.php?discordId=963536045180350474'; ?>
            </div>

            <!-- Activity Cards -->
            <div class="activity-card">
                <div class="activity-icon">🎬</div>
                <div class="activity-info">
                    <h4>Video Editing</h4>
                    <p>Creating amazing content</p>
                </div>
            </div>

            <div class="activity-card">
                <div class="activity-icon">💻</div>
                <div class="activity-info">
                    <h4>Development</h4>
                    <p>Building cool projects</p>
                </div>
            </div>

            <!-- Social Links -->
            <div class="social-links">
                <a href="https://tiktok.cripsum.com" target="_blank" class="social-link" title="TikTok">
                    🎵
                </a>
                <a href="https://t.me/sburragrigliata" target="_blank" class="social-link" title="Telegram">
                    📱
                </a>
                <a href="https://discord.cripsum.com" target="_blank" class="social-link" title="Discord">
                    💬
                </a>
                <a href="#" class="social-link" title="GitHub">
                    🐙
                </a>
            </div>
        </div>
    </div>

    <script>
        // Create animated stars
        function createStars() {
            const starsContainer = document.getElementById('stars');
            const numberOfStars = 50;

            for (let i = 0; i < numberOfStars; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.width = Math.random() * 3 + 1 + 'px';
                star.style.height = star.style.width;
                star.style.animationDelay = Math.random() * 3 + 's';
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

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            createStars();
            updateDiscordStatus();
            
            // Update Discord status every 30 seconds
            setInterval(updateDiscordStatus, 30000);
        });

        // Copy profile link functionality
        function copyProfileLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                // Show success feedback
                const notification = document.createElement('div');
                notification.textContent = 'Profile link copied!';
                notification.style.cssText = `
                    position: fixed;
                    top: 100px;
                    right: 20px;
                    background: rgba(67, 181, 129, 0.9);
                    color: white;
                    padding: 12px 20px;
                    border-radius: 8px;
                    z-index: 10000;
                    animation: slideIn 0.3s ease;
                `;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            });
        }
    </script>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"
    ></script>
    <script src="js/modeChanger.js"></script>
</body>
</html>