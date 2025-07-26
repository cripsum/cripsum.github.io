<?php
$discord_id = $_GET['discordId']; // ← Inserisci qui il tuo ID Discord
function getDiscordPresence($discord_id) {
    $ch = curl_init("https://api.lanyard.rest/v1/users/$discord_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$data = getDiscordPresence($discord_id);

?>
<?php if ($data && isset($data['data'])): 
    $user = $data['data']['discord_user'];
    $status = $data['data']['discord_status'];
    $activities = $data['data']['activities'];
    
    // Funzione per ottenere l'avatar Discord
    $avatar_url = "https://cdn.discordapp.com/avatars/{$user['id']}/{$user['avatar']}.png?size=64";
?>
    <div class="discord-card">
        <!-- Header con profilo Discord -->
        <div class="discord-profile">
            <div class="profile-avatar">
                <img src="<?php echo $avatar_url; ?>" alt="Avatar Discord" class="avatar-img">
                <div class="status-indicator status-<?php echo $status; ?>"></div>
            </div>
            <div class="profile-info">
                <div class="profile-username">
                    <div class="username-content">
                        <svg class="discord-logo" viewBox="0 0 24 24" width="20" height="20">
                            <path fill="currentColor" d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.195.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                        </svg>
                        @<?php echo htmlspecialchars($user['username']); ?>
                    </div>
                    <a href="https://discord.com/users/<?php echo $user['id']; ?>" target="_blank" class="discord-profile-link" title="Visualizza profilo Discord">
                        <svg viewBox="0 0 24 24" width="16" height="16">
                            <path fill="currentColor" d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.11 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Attività -->
        <?php if (!empty($activities)): ?>
            <div class="activity-section">
                <?php foreach ($activities as $index => $activity): ?>
                    <div class="activity-item" style="<?php echo $index !== 0 ? 'display:none;' : ''; ?>">
                        <div class="activity-icon-container">
                            <?php
                                $icon = null;
                                if (isset($activity['assets']['large_image'])) {
                                    $key = $activity['assets']['large_image'];
                                    if (str_starts_with($key, 'mp:external/')) {
                                        $icon = str_replace('mp:', 'https://media.discordapp.net/', $key);
                                    } else {
                                        $icon = "https://cdn.discordapp.com/app-assets/{$activity['application_id']}/$key.png";
                                    }
                                }
                            ?>
                            <?php if ($icon): ?>
                                <img src="<?php echo $icon; ?>" alt="App Icon" class="activity-icon">
                            <?php else: ?>
                                <div class="activity-icon activity-icon-fallback">
                                    <svg viewBox="0 0 24 24" width="24" height="24">
                                        <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-name"><?php echo htmlspecialchars($activity['name']); ?></div>
                            <?php if (!empty($activity['details'])): ?>
                                <div class="activity-details"><?php echo htmlspecialchars($activity['details']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($activity['state'])): ?>
                                <div class="activity-state"><?php echo htmlspecialchars($activity['state']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .discord-card {
            background: linear-gradient(135deg, rgba(125, 246, 255, 0), rgba(4, 87, 87, 0));
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 20px;
            max-width: 400px;
            margin: 1.5rem auto 0 auto;
            color: white;
            box-shadow: 0 0 8px 4px rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .discord-card:hover {
            color: white;
            box-shadow: 0 0 8px 4px rgba(255, 255, 255, 0.35);
            transform: translateY(-2px);
        }

        .discord-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .profile-avatar {
            position: relative;
            flex-shrink: 0;
        }

        .avatar-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.2);
            object-fit: cover;
        }

        .status-indicator {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid rgba(0, 0, 0, 0.8);
        }

        .status-online { background-color: #23a55a; }
        .status-idle { background-color: #f0b232; }
        .status-dnd { background-color: #f23f43; }
        .status-offline { background-color: #80848e; }

        .profile-info {
            flex-grow: 1;
            min-width: 0;
        }

        .profile-username {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 2px;
        }

        .username-content {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 16px;
            color: #ffffff;
        }

        .discord-profile-link {
            color: #b5bac1;
            text-decoration: none;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .discord-profile-link:hover {
            color: #5865f2;
            background-color: rgba(88, 101, 242, 0.1);
            transform: scale(1.1);
        }

        .discord-logo {
            color: #5865f2;
            flex-shrink: 0;
        }

        .profile-status {
            font-size: 13px;
            color: #b5bac1;
        }

        .status-text.status-online { color: #23a55a; }
        .status-text.status-idle { color: #f0b232; }
        .status-text.status-dnd { color: #f23f43; }
        .status-text.status-offline { color: #80848e; }

        .activity-section {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 16px;
            margin-top: 8px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            animation: fadeInActivity 0.4s ease;
        }

        .activity-icon-container {
            flex-shrink: 0;
        }

        .activity-icon {
            width: 64px;
            height: 64px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .activity-icon-fallback {
            background: linear-gradient(135deg, #5865f2, #7289da);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .activity-content {
            flex-grow: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .activity-name {
            font-weight: 600;
            font-size: 14px;
            color: #ffffff;
            margin-bottom: 2px;
            line-height: 1.2;
        }

        .activity-details {
            font-size: 13px;
            color: #b5bac1;
            margin-bottom: 1px;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .activity-state {
            font-size: 13px;
            color: #949ba4;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @keyframes fadeInActivity {
            from { 
                opacity: 0; 
                transform: translateY(8px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .discord-card {
                padding: 16px;
                max-width: 100%;
            }
            
            .avatar-img {
                width: 44px;
                height: 44px;
            }
            
            .activity-icon {
                width: 44px;
                height: 44px;
            }
        }
    </style>

<?php else: ?>
    <div class="discord-card">
        <div class="discord-error">
            <svg viewBox="0 0 24 24" width="20" height="20">
                <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            <span>Errore nel recupero dello stato Discord</span>
        </div>
    </div>
    
    <style>
        .discord-error {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #f23f43;
            font-size: 14px;
            justify-content: center;
            padding: 10px;
        }
    </style>
<?php endif; ?>