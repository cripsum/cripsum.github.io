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
        @import url('https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
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
            z-index: -2;
            background: 
                radial-gradient(circle at 15% 30%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 85% 20%, rgba(139, 92, 246, 0.12) 0%, transparent 50%),
                radial-gradient(circle at 50% 80%, rgba(16, 185, 129, 0.08) 0%, transparent 50%);
        }

        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .shape {
            position: absolute;
            opacity: 0.03;
            animation: float 20s infinite ease-in-out;
        }

        .shape:nth-child(1) { top: 20%; left: 10%; animation-delay: 0s; }
        .shape:nth-child(2) { top: 60%; left: 80%; animation-delay: -5s; }
        .shape:nth-child(3) { top: 80%; left: 20%; animation-delay: -10s; }
        .shape:nth-child(4) { top: 40%; left: 70%; animation-delay: -15s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(120deg); }
            66% { transform: translateY(20px) rotate(240deg); }
        }

        /* Main Container */
        .main-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
        }

        /* Profile Card */
        .profile-card {
            background: rgba(15, 15, 35, 0.7);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 32px;
            padding: 3rem;
            max-width: 520px;
            width: 100%;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(139, 92, 246, 0.8), transparent);
        }

        /* Profile Header */
        .profile-header {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 1.5rem;
        }

        .avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 3px solid rgba(139, 92, 246, 0.4);
            object-fit: cover;
            box-shadow: 0 8px 32px rgba(139, 92, 246, 0.3);
            transition: transform 0.3s ease;
        }

        .avatar:hover {
            transform: scale(1.05);
        }

        .level-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 10px;
            border-radius: 16px;
            border: 3px solid rgba(15, 15, 35, 0.9);
            box-shadow: 0 4px 16px rgba(139, 92, 246, 0.4);
        }

        .status-dot {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 20px;
            height: 20px;
            background: #10b981;
            border: 4px solid rgba(15, 15, 35, 0.9);
            border-radius: 50%;
            box-shadow: 0 0 16px rgba(16, 185, 129, 0.5);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .username {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #8b5cf6, #3b82f6, #10b981);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gradientShift 4s ease-in-out infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .user-info {
            color: #a1a1aa;
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
        }

        .user-tags {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .tag {
            background: rgba(139, 92, 246, 0.15);
            border: 1px solid rgba(139, 92, 246, 0.3);
            color: #c4b5fd;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Social Links */
        .social-links {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            margin: 1.5rem 0;
            flex-wrap: wrap;
        }

        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            color: #ffffff;
            text-decoration: none;
            font-size: 1.25rem;
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
            transition: left 0.5s ease;
        }

        .social-link:hover::before {
            left: 100%;
        }

        .social-link:hover {
            background: rgba(139, 92, 246, 0.2);
            border-color: rgba(139, 92, 246, 0.4);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.3);
        }

        /* Activity Cards */
        .activity-section {
            margin: 2rem 0;
        }

        .activity-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .activity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(139, 92, 246, 0.4), transparent);
        }

        .activity-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(139, 92, 246, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
        }

        .activity-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .activity-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3);
            flex-shrink: 0;
        }

        .activity-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .activity-info p {
            color: #a1a1aa;
            font-size: 0.9rem;
        }

        .activity-details {
            color: #d1d5db;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* Custom Link */
        .custom-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            color: #ffffff;
            text-decoration: none;
            margin-top: 1rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .custom-link:hover {
            background: rgba(139, 92, 246, 0.15);
            border-color: rgba(139, 92, 246, 0.3);
            transform: translateX(4px);
            color: #ffffff;
        }

        .link-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Music Player */
        .music-player {
            margin-top: 1rem;
        }

        .music-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 0.75rem;
        }

        .progress-container {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            background: linear-gradient(90deg, #10b981, #059669);
            transition: width 0.3s ease;
            border-radius: 2px;
        }

        .time-display {
            font-size: 0.8rem;
            color: #a1a1aa;
            font-weight: 500;
            min-width: 35px;
        }

        /* Discord Status Styling - Override discord_status.php styles */
        .discord-status {
            margin-bottom: 1rem;
        }

        /* Override the discord-card styling from discord_status.php */
        .discord-status .discord-card {
            background: rgba(0, 0, 0, 0.3) !important;
            border: 1px solid rgba(88, 101, 242, 0.2) !important;
            border-radius: 20px !important;
            padding: 1.5rem !important;
            margin: 0 !important;
            max-width: 100% !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative !important;
            overflow: hidden !important;
            backdrop-filter: blur(10px) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1) !important;
        }

        .discord-status .discord-card::before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            height: 1px !important;
            background: linear-gradient(90deg, transparent, rgba(88, 101, 242, 0.6), transparent) !important;
        }

        .discord-status .discord-card:hover {
            background: rgba(88, 101, 242, 0.08) !important;
            border-color: rgba(88, 101, 242, 0.4) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2) !important;
        }

        /* Style the Discord profile section like an activity header */
        .discord-status .discord-profile {
            display: flex !important;
            align-items: center !important;
            gap: 1rem !important;
            margin-bottom: 1rem !important;
        }

        .discord-status .profile-avatar {
            position: relative !important;
            flex-shrink: 0 !important;
        }

        .discord-status .avatar-img {
            width: 48px !important;
            height: 48px !important;
            border-radius: 12px !important;
            border: 2px solid rgba(88, 101, 242, 0.3) !important;
            object-fit: cover !important;
            box-shadow: 0 4px 16px rgba(88, 101, 242, 0.2) !important;
        }

        .discord-status .status-indicator {
            position: absolute !important;
            bottom: -2px !important;
            right: -2px !important;
            width: 16px !important;
            height: 16px !important;
            border-radius: 50% !important;
            border: 3px solid rgba(15, 15, 35, 0.9) !important;
        }

        .discord-status .status-online { background-color: #10b981 !important; }
        .discord-status .status-idle { background-color: #f59e0b !important; }
        .discord-status .status-dnd { background-color: #ef4444 !important; }
        .discord-status .status-offline { background-color: #6b7280 !important; }

        .discord-status .username-content {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            font-weight: 600 !important;
            font-size: 1.1rem !important;
            color: #ffffff !important;
        }

        .discord-status .discord-logo {
            color: #5865f2 !important;
            flex-shrink: 0 !important;
        }

        .discord-status .discord-profile-link {
            color: #a1a1aa !important;
            text-decoration: none !important;
            padding: 4px !important;
            border-radius: 8px !important;
            transition: all 0.2s ease !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .discord-status .discord-profile-link:hover {
            color: #5865f2 !important;
            background-color: rgba(88, 101, 242, 0.15) !important;
            transform: scale(1.1) !important;
        }

        /* Style the activity section */
        .discord-status .activity-section {
            border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
            padding-top: 1rem !important;
            margin-top: 0.5rem !important;
        }

        .discord-status .activity-item {
            display: flex !important;
            align-items: center !important;
            gap: 1rem !important;
            padding: 0 !important;
        }

        .discord-status .activity-icon {
            width: 48px !important;
            height: 48px !important;
            border-radius: 12px !important;
            object-fit: cover !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2) !important;
        }

        .discord-status .activity-icon-fallback {
            background: linear-gradient(135deg, #5865f2, #7c3aed) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: white !important;
        }

        .discord-status .activity-content {
            flex-grow: 1 !important;
            min-width: 0 !important;
        }

        .discord-status .activity-name {
            font-weight: 600 !important;
            font-size: 1.1rem !important;
            color: #ffffff !important;
            margin-bottom: 0.25rem !important;
            line-height: 1.2 !important;
        }

        .discord-status .activity-details {
            font-size: 0.9rem !important;
            color: #a1a1aa !important;
            margin-bottom: 0.1rem !important;
            line-height: 1.3 !important;
        }

        .discord-status .activity-state {
            font-size: 0.9rem !important;
            color: #71717a !important;
            line-height: 1.3 !important;
        }

        /* Error state styling */
        .discord-status .discord-error {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            color: #ef4444 !important;
            font-size: 0.9rem !important;
            padding: 1rem !important;
            background: rgba(239, 68, 68, 0.1) !important;
            border-radius: 12px !important;
            border: 1px solid rgba(239, 68, 68, 0.2) !important;
        }

        /* Server specific */
        .server-card {
            background: linear-gradient(135deg, rgba(251, 146, 60, 0.1), rgba(251, 146, 60, 0.05));
            border-color: rgba(251, 146, 60, 0.2);
        }

        .server-card .activity-icon {
            background: linear-gradient(135deg, #fb923c, #f97316);
        }

        /* Music specific */
        .music-card {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05));
            border-color: rgba(34, 197, 94, 0.2);
        }

        .music-card .activity-icon {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .profile-card {
                padding: 2rem 1.5rem;
            }

            .username {
                font-size: 1.75rem;
            }

            .social-links {
                gap: 0.5rem;
            }

            .social-link {
                width: 44px;
                height: 44px;
                font-size: 1.1rem;
            }

            .activity-header {
                gap: 0.75rem;
            }

            .activity-icon {
                width: 44px;
                height: 44px;
                font-size: 1.25rem;
            }
        }

        @media (max-width: 480px) {
            .profile-card {
                padding: 1.5rem 1rem;
                margin: 1rem;
            }

            .avatar-container {
                width: 100px;
                height: 100px;
            }

            .username {
                font-size: 1.5rem;
            }

            .user-tags {
                gap: 0.5rem;
            }

            .tag {
                padding: 0.3rem 0.6rem;
                font-size: 0.75rem;
            }
        }

        /* Animation */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Loading animation for dynamic content */
        .loading {
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.1) 25%, rgba(255, 255, 255, 0.2) 50%, rgba(255, 255, 255, 0.1) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Animated Background -->
    <div class="background-container"></div>
    <div class="floating-shapes">
        <div class="shape" style="width: 100px; height: 100px; background: linear-gradient(45deg, rgba(139, 92, 246, 0.1), rgba(59, 130, 246, 0.1)); border-radius: 50%;"></div>
        <div class="shape" style="width: 150px; height: 150px; background: linear-gradient(45deg, rgba(16, 185, 129, 0.1), rgba(139, 92, 246, 0.1)); border-radius: 30%;"></div>
        <div class="shape" style="width: 80px; height: 80px; background: linear-gradient(45deg, rgba(59, 130, 246, 0.1), rgba(16, 185, 129, 0.1)); border-radius: 20%;"></div>
        <div class="shape" style="width: 120px; height: 120px; background: linear-gradient(45deg, rgba(251, 146, 60, 0.1), rgba(139, 92, 246, 0.1)); border-radius: 40%;"></div>
    </div>

    <div class="main-container">
        <div class="profile-card">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="avatar-container">
                    <img src="includes/get_pfp.php?id=<?php echo $user_cercato_id; ?>" alt="Profile Picture" class="avatar">
                    <div class="level-badge">170</div>
                    <div class="status-dot"></div>
                </div>
                <h1 class="username"><?php echo htmlspecialchars($user['username']); ?></h1>
                <div class="user-info">🇮🇹 19</div>
                <div class="user-tags">
                    <span class="tag">♦ video editor</span>
                    <span class="tag">♦ developer</span>
                </div>

                <!-- Social Links -->
                <div class="social-links">
                    <a href="https://cripsum.com" target="_blank" class="social-link" title="Website">🌐</a>
                    <a href="https://tiktok.cripsum.com" target="_blank" class="social-link" title="TikTok">📱</a>
                    <a href="#" class="social-link" title="YouTube">📺</a>
                    <a href="https://discord.cripsum.com" target="_blank" class="social-link" title="Discord">💬</a>
                    <a href="#" class="social-link" title="Instagram">📷</a>
                    <a href="#" class="social-link" title="Spotify">🎶</a>
                    <a href="#" class="social-link" title="GitHub">⚡</a>
                    <a href="https://t.me/sburragrigliata" target="_blank" class="social-link" title="Telegram">📲</a>
                </div>
            </div>

            <!-- Activity Section - Dynamic Content -->
            <div class="activity-section">
                <!-- Discord Integration (dynamic content) -->
                <div id="discordStatus" class="discord-status">
                    <?php include 'includes/discord_status.php?discordId=963536045180350474'; ?>
                </div>

                <!-- Custom Link Card -->
                <div class="activity-card">
                    <div class="activity-header">
                        <div class="activity-icon">🔗</div>
                        <div class="activity-info">
                            <h3>My website</h3>
                            <p>https://cripsum.com</p>
                        </div>
                    </div>
                    <a href="https://cripsum.com" target="_blank" class="custom-link">
                        <div class="link-content">
                            <span>🌐</span>
                            <span>Visit Website</span>
                        </div>
                        <span>→</span>
                    </a>
                </div>

                <!-- Server Status -->
                <div class="activity-card server-card">
                    <div class="activity-header">
                        <div class="activity-icon">🏠</div>
                        <div class="activity-info">
                            <h3>cripsum's tavern</h3>
                            <p>🟢 Online • 77 Members</p>
                        </div>
                    </div>
                    <a href="https://discord.cripsum.com" target="_blank" class="custom-link">
                        <div class="link-content">
                            <span>🚪</span>
                            <span>Join Server</span>
                        </div>
                        <span>→</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize dynamic content and Discord integration
        document.addEventListener('DOMContentLoaded', function() {
            initializeAnimations();
            updateDiscordStatus();
            
            // Update Discord status every 30 seconds
            setInterval(updateDiscordStatus, 30000);
            
            // Add interactive effects
            addInteractiveEffects();
        });

        // Discord status update function (your existing integration)
        function updateDiscordStatus() {
            fetch('includes/discord_status.php?discordId=963536045180350474')
                .then(r => r.text())
                .then(html => {
                    const discordStatus = document.getElementById('discordStatus');
                    if (discordStatus) {
                        discordStatus.innerHTML = html;
                        
                        // Apply styling to dynamically loaded content
                        styleDiscordContent(discordStatus);
                    }
                })
                .catch(err => console.error('Error updating Discord status:', err));
        }

        // Apply modern styling to Discord status content
        function styleDiscordContent(container) {
            // The content is already styled with CSS !important rules
            // Just add any additional interactive effects
            
            const discordCard = container.querySelector('.discord-card');
            if (discordCard && !discordCard.classList.contains('styled')) {
                discordCard.classList.add('styled');
                
                // Add entrance animation
                discordCard.style.opacity = '0';
                discordCard.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    discordCard.style.transition = 'all 0.6s cubic-bezier(0.16, 1, 0.3, 1)';
                    discordCard.style.opacity = '1';
                    discordCard.style.transform = 'translateY(0)';
                }, 100);
            }

            // Add interactive effects to profile link
            const profileLink = container.querySelector('.discord-profile-link');
            if (profileLink) {
                profileLink.addEventListener('click', function(e) {
                    // Add a small scale animation on click
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1.1)';
                    }, 100);
                });
            }

            // Add subtle animation to activity icons
            const activityIcons = container.querySelectorAll('.activity-icon');
            activityIcons.forEach(icon => {
                icon.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05) rotate(2deg)';
                    this.style.transition = 'transform 0.2s ease';
                });
                
                icon.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) rotate(0deg)';
                });
            });
        }

        function addInteractiveEffects() {
            // Add hover effects to cards
            const cards = document.querySelectorAll('.activity-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Add click effects to social links
            const socialLinks = document.querySelectorAll('.social-link');
            socialLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Add ripple effect
                    const ripple = document.createElement('div');
                    ripple.style.position = 'absolute';
                    ripple.style.borderRadius = '50%';
                    ripple.style.background = 'rgba(255, 255, 255, 0.3)';
                    ripple.style.transform = 'scale(0)';
                    ripple.style.animation = 'ripple 0.6s linear';
                    ripple.style.width = ripple.style.height = '20px';
                    ripple.style.left = ripple.style.top = '50%';
                    ripple.style.marginLeft = ripple.style.marginTop = '-10px';
                    this.style.position = 'relative';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        }

        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"
    ></script>
    <script src="js/modeChanger.js"></script>
</body>
</html>