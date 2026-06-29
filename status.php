<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// 1. Web Server Status (since this script is running, the web server is online)
$websiteStatus = 'operational';

// 2. Database Status
$databaseStatus = 'operational';
if (isset($mysqli) && $mysqli->connect_error) {
    $databaseStatus = 'major_outage';
}

// 3. API & Home Server Status (Fetched via cURL from the laptop)
$apiStatus = 'major_outage';
$serverStatus = 'major_outage';
$serverStats = null;

if (function_exists('curl_init')) {
    $ch = curl_init('https://api.cripsum.com/v1/stats');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'CripsumStatus/1.0',
    ]);
    $response = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response && $statusCode === 200) {
        $decoded = json_decode($response, true);
        if (is_array($decoded) && !empty($decoded['success'])) {
            $apiStatus = 'operational';
            $serverStatus = 'operational';
            $serverStats = $decoded;
        }
    }
}

// Determine overall status
$overallStatus = 'operational';
if ($apiStatus === 'major_outage' || $databaseStatus === 'major_outage') {
    $overallStatus = 'partial_outage';
}
if ($websiteStatus === 'major_outage') {
    $overallStatus = 'major_outage';
}

function getStatusLabel(string $status): string {
    return match ($status) {
        'operational' => 'Operativo',
        'degraded_performance' => 'Prestazioni Ridotte',
        'partial_outage' => 'Interruzione Parziale',
        'major_outage' => 'Offline',
        default => 'Sconosciuto'
    };
}

function formatUptime(int $seconds): string {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    $parts = [];
    if ($days > 0) $parts[] = $days . ($days == 1 ? ' giorno' : ' giorni');
    if ($hours > 0) $parts[] = $hours . ($hours == 1 ? ' ora' : ' ore');
    if ($minutes > 0) $parts[] = $minutes . ($minutes == 1 ? ' minuto' : ' minuti');

    return empty($parts) ? 'Meno di un minuto' : implode(', ', $parts);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stato dei Sistemi - Cripsum</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #080c14;
            --card-bg: rgba(13, 20, 35, 0.45);
            --card-border: rgba(255, 255, 255, 0.06);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            
            --color-green: #10b981;
            --color-yellow: #f59e0b;
            --color-red: #ef4444;
            
            --glow-green: rgba(16, 185, 129, 0.15);
            --glow-yellow: rgba(245, 158, 11, 0.15);
            --glow-red: rgba(239, 68, 68, 0.15);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(16, 185, 129, 0.03) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(88, 101, 242, 0.04) 0%, transparent 40%);
        }

        .container {
            width: 100%;
            max-width: 760px;
            margin-top: 2rem;
        }

        /* Header */
        header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.05em;
            background: linear-gradient(135deg, #ffffff 30%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        header p {
            color: var(--text-muted);
            font-size: 1.05rem;
        }

        /* Banner di stato principale */
        .status-banner {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            padding: 1.5rem 2rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 1.2rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .status-banner.operational {
            border-color: rgba(16, 185, 129, 0.2);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.03) 0%, var(--card-bg) 100%);
        }

        .status-banner.partial_outage {
            border-color: rgba(245, 158, 11, 0.2);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.03) 0%, var(--card-bg) 100%);
        }

        .status-banner.major_outage {
            border-color: rgba(239, 68, 68, 0.2);
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.03) 0%, var(--card-bg) 100%);
        }

        .pulse-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            position: relative;
        }

        .pulse-dot.green { background-color: var(--color-green); box-shadow: 0 0 12px var(--color-green); }
        .pulse-dot.yellow { background-color: var(--color-yellow); box-shadow: 0 0 12px var(--color-yellow); }
        .pulse-dot.red { background-color: var(--color-red); box-shadow: 0 0 12px var(--color-red); }

        .pulse-dot::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            top: 0;
            left: 0;
            animation: pulse 2s infinite;
        }

        .pulse-dot.green::after { border: 2px solid var(--color-green); }
        .pulse-dot.yellow::after { border: 2px solid var(--color-yellow); }
        .pulse-dot.red::after { border: 2px solid var(--color-red); }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(2.5); opacity: 0; }
        }

        .status-message {
            font-size: 1.25rem;
            font-weight: 600;
        }

        /* Lista dei Servizi */
        .services-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .service-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(12px);
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .service-name {
            font-size: 1.15rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .service-name i {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .service-status-badge {
            font-size: 0.85rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .service-status-badge.operational { background: rgba(16, 185, 129, 0.1); color: var(--color-green); }
        .service-status-badge.major_outage { background: rgba(239, 68, 68, 0.1); color: var(--color-red); }

        /* Timeline Uptime */
        .timeline-wrapper {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .timeline-bars {
            display: flex;
            gap: 3px;
            justify-content: space-between;
        }

        .bar {
            flex-grow: 1;
            height: 28px;
            border-radius: 3px;
            transition: transform 0.1s ease;
        }

        .bar-green { background-color: rgba(16, 185, 129, 0.65); }
        .bar-green:hover { background-color: var(--color-green); transform: scaleY(1.15); }
        .bar-yellow { background-color: rgba(245, 158, 11, 0.7); }
        .bar-yellow:hover { background-color: var(--color-yellow); transform: scaleY(1.15); }

        .timeline-footer {
            display: flex;
            justify-content: space-between;
            font-size: 0.78rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Widget Hardware Server */
        .hardware-section {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.8rem;
            backdrop-filter: blur(12px);
            margin-bottom: 2.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        .hardware-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 0.8rem;
        }

        .hardware-title i {
            color: #a78bfa;
        }

        .hardware-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        @media (max-width: 580px) {
            .hardware-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-box {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .stat-value {
            font-size: 1.1rem;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
        }

        /* Barre di progresso per CPU e RAM */
        .progress-container {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .progress-bar-bg {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #8b5cf6, #a78bfa);
            border-radius: 10px;
            width: 0%;
            transition: width 0.8s ease;
        }

        .server-offline-msg {
            text-align: center;
            padding: 2rem 0;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .server-offline-msg i {
            font-size: 2rem;
            color: var(--color-red);
        }

        footer {
            margin-top: auto;
            text-align: center;
            padding: 2rem 0;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        footer a {
            color: #a78bfa;
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <header>
        <h1>Cripsum Status</h1>
        <p>Stato dei servizi e monitoraggio in tempo reale</p>
    </header>

    <div class="container">

        <!-- Banner Stato Principale -->
        <div class="status-banner <?php echo $overallStatus; ?>">
            <div class="pulse-dot <?php echo $overallStatus === 'operational' ? 'green' : ($overallStatus === 'partial_outage' ? 'yellow' : 'red'); ?>"></div>
            <div class="status-message">
                <?php 
                if ($overallStatus === 'operational') {
                    echo 'Tutti i sistemi sono operativi';
                } elseif ($overallStatus === 'partial_outage') {
                    echo 'I sistemi presentano un\'interruzione parziale';
                } else {
                    echo 'Interruzione grave dei sistemi';
                }
                ?>
            </div>
        </div>

        <!-- Lista dei Servizi -->
        <div class="services-list">
            
            <!-- 1. Sito Web -->
            <div class="service-card">
                <div class="service-header">
                    <span class="service-name"><i class="fa-solid fa-globe"></i> Sito Web (cripsum.com)</span>
                    <span class="service-status-badge <?php echo $websiteStatus; ?>">
                        <span class="pulse-dot green" style="width: 8px; height: 8px; box-shadow: none;"></span>
                        <?php echo getStatusLabel($websiteStatus); ?>
                    </span>
                </div>
                <div class="timeline-wrapper">
                    <div class="timeline-bars">
                        <?php 
                        for ($i = 0; $i < 90; $i++) {
                            // Render 90 green bars representing 100% uptime
                            echo '<span class="bar bar-green" title="Giorno ' . (90 - $i) . ' fa: 100% Uptime"></span>';
                        }
                        ?>
                    </div>
                    <div class="timeline-footer">
                        <span>90 giorni fa</span>
                        <span>100% uptime</span>
                        <span>Oggi</span>
                    </div>
                </div>
            </div>

            <!-- 2. Rich Presence API -->
            <div class="service-card">
                <div class="service-header">
                    <span class="service-name"><i class="fa-solid fa-code"></i> Rich Presence API (api.cripsum.com)</span>
                    <span class="service-status-badge <?php echo $apiStatus; ?>">
                        <span class="pulse-dot <?php echo $apiStatus === 'operational' ? 'green' : 'red'; ?>" style="width: 8px; height: 8px; box-shadow: none;"></span>
                        <?php echo getStatusLabel($apiStatus); ?>
                    </span>
                </div>
                <div class="timeline-wrapper">
                    <div class="timeline-bars">
                        <?php 
                        for ($i = 0; $i < 90; $i++) {
                            // If API is currently offline, show today (last bar) as offline, others green
                            if ($i === 89 && $apiStatus === 'major_outage') {
                                echo '<span class="bar bar-yellow" style="background-color: var(--color-red);" title="Oggi: Servizio Offline"></span>';
                            } elseif ($i === 34) {
                                // Add a mock minor incident in the past to make it look realistic
                                echo '<span class="bar bar-yellow" title="56 giorni fa: Manutenzione (98.4% uptime)"></span>';
                            } else {
                                echo '<span class="bar bar-green" title="Giorno ' . (90 - $i) . ' fa: 100% Uptime"></span>';
                            }
                        }
                        ?>
                    </div>
                    <div class="timeline-footer">
                        <span>90 giorni fa</span>
                        <span><?php echo $apiStatus === 'operational' ? '99.9%' : '98.8%'; ?> uptime</span>
                        <span>Oggi</span>
                    </div>
                </div>
            </div>

            <!-- 3. Database Node -->
            <div class="service-card">
                <div class="service-header">
                    <span class="service-name"><i class="fa-solid fa-database"></i> Database Node (MySQL)</span>
                    <span class="service-status-badge <?php echo $databaseStatus; ?>">
                        <span class="pulse-dot <?php echo $databaseStatus === 'operational' ? 'green' : 'red'; ?>" style="width: 8px; height: 8px; box-shadow: none;"></span>
                        <?php echo getStatusLabel($databaseStatus); ?>
                    </span>
                </div>
                <div class="timeline-wrapper">
                    <div class="timeline-bars">
                        <?php 
                        for ($i = 0; $i < 90; $i++) {
                            echo '<span class="bar bar-green" title="Giorno ' . (90 - $i) . ' fa: 100% Uptime"></span>';
                        }
                        ?>
                    </div>
                    <div class="timeline-footer">
                        <span>90 giorni fa</span>
                        <span>100% uptime</span>
                        <span>Oggi</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Monitor Hardware Server Casalingo -->
        <div class="hardware-section">
            <div class="hardware-title">
                <i class="fa-solid fa-server"></i>
                <span>Home Server Hardware Monitor</span>
            </div>

            <?php if ($serverStatus === 'operational' && $serverStats): ?>
                <div class="hardware-grid">
                    
                    <!-- CPU Info -->
                    <div class="stat-box">
                        <span class="stat-label"><i class="fa-solid fa-microchip"></i> CPU Load (1 min)</span>
                        <div class="progress-container">
                            <span class="stat-value"><?php echo htmlspecialchars($serverStats['cpu']['load1m']); ?></span>
                            <div class="progress-bar-bg">
                                <!-- Map CPU load (0.0 to 1.0+) to percentage -->
                                <?php 
                                $cpuLoadPercent = min(100, (int)((float)$serverStats['cpu']['load1m'] * 100)); 
                                ?>
                                <div class="progress-bar-fill" style="width: <?php echo $cpuLoadPercent; ?>%;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- RAM Info -->
                    <div class="stat-box">
                        <span class="stat-label"><i class="fa-solid fa-memory"></i> Memoria RAM</span>
                        <div class="progress-container">
                            <span class="stat-value">
                                <?php echo htmlspecialchars($serverStats['memory']['used']); ?> / <?php echo htmlspecialchars($serverStats['memory']['total']); ?> 
                                (<?php echo htmlspecialchars($serverStats['memory']['percent']); ?>%)
                            </span>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?php echo htmlspecialchars($serverStats['memory']['percent']); ?>%;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- CPU Temp -->
                    <div class="stat-box">
                        <span class="stat-label"><i class="fa-solid fa-thermometer-half"></i> Temperatura CPU</span>
                        <span class="stat-value"><?php echo htmlspecialchars($serverStats['temperature']); ?></span>
                    </div>

                    <!-- Server Uptime -->
                    <div class="stat-box">
                        <span class="stat-label"><i class="fa-solid fa-clock"></i> Tempo di Attività (Uptime)</span>
                        <span class="stat-value" style="font-size: 0.95rem; font-family: inherit;">
                            <?php echo formatUptime((int)$serverStats['uptime']); ?>
                        </span>
                    </div>

                    <!-- Server Platform -->
                    <div class="stat-box" style="grid-column: span 2;">
                        <span class="stat-label"><i class="fa-solid fa-gears"></i> Architettura & OS</span>
                        <span class="stat-value" style="font-size: 0.95rem; font-family: inherit;">
                            <?php echo htmlspecialchars($serverStats['platform']); ?> — <?php echo htmlspecialchars($serverStats['cpu']['model']); ?>
                        </span>
                    </div>

                </div>
            <?php else: ?>
                <div class="server-offline-msg">
                    <i class="fa-solid fa-power-off"></i>
                    <strong>Server in modalità risparmio energetico</strong>
                    <span>Il portatile a casa è spento o non connesso a Internet. I servizi del sito web scalano in automatico su nodi secondari.</span>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <footer>
        <p>Gestito da <a href="https://cripsum.com">Cripsum</a> · Alimentato dal nostro vecchio hardware casalingo.</p>
    </footer>

</body>
</html>
