<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = 'You must be logged in to play';
    header('Location: accedi');
    exit();
}

$ogDescription = 'Play Subway Surfers directly on Cripsum with World Tour maps, a No-Coin timer and configurable controls.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/en/subway'), '#');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ Subway Surfers Mod & Challenge Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?= htmlspecialchars($ogDescription) ?>">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Cripsum™ Subway Surfers">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <meta property="og:image" content="https://cripsum.com/img/Susremaster.png">
    <meta property="og:url" content="<?= htmlspecialchars($ogUrl) ?>">
    
    <!-- Custom styling & game engine logic -->
    <link class="subway-css" rel="stylesheet" href="/assets/css/game.css?v=6.0">
    <link rel="stylesheet" href="/assets/css/subway.css?v=7.0">
    <script src="/assets/js/subway/subway.js?v=7.1" defer></script>
</head>

<body class="game-page">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="game-bg" aria-hidden="true"><span></span><span></span></div>

    <!-- Main Subway Surfers Container -->
    <main class="subway-dashboard" id="subwayPortal">
        
        <!-- STATE 1: Map Selector Lobby & Settings Dashboard -->
        <div id="subwayLobby">
            <section class="game-hero game-reveal" style="margin-bottom: 2rem;">
                <div class="game-hero-copy">
                    <span class="game-kicker"><i class="fa-solid fa-train"></i> Subway Surfers Native Challenge</span>
                    <h1>Subway Portal</h1>
                    <p>Choose a World Tour: Unity loads directly in the Cripsum player with the No-Coin timer and configurable controls.</p>
                    <div class="game-steps" aria-label="Challenge Steps">
                        <span><b>1</b> Select City</span>
                        <span><b>2</b> Configure Player</span>
                        <span><b>3</b> Avoid Coins</span>
                    </div>
                </div>
            </section>

            <!-- Custom Options & Key Binds Section -->
            <div class="subway-settings-panel">
                <section class="game-panel game-reveal">
                    <div class="game-panel-head">
                        <div>
                            <h2>Challenge Configuration</h2>
                        </div>
                    </div>
                    <div class="game-action-list" style="margin-top: 1rem;">
                        
                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong>No-Coin Challenge</strong>
                                <span>Starts the timer on movement keypress and automatically stops on first collected coin sound or signal.</span>
                            </div>
                            <label class="subway-switch">
                                <input type="checkbox" id="toggleNoCoinChallenge" checked>
                                <span class="subway-slider"></span>
                            </label>
                        </div>
                        
                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong>Auto-Pause on Coin</strong>
                                <span>Sends a pause request (ESC) to the game when you pick up a coin, freezing your run.</span>
                            </div>
                            <label class="subway-switch">
                                <input type="checkbox" id="toggleAutoPause" checked>
                                <span class="subway-slider"></span>
                            </label>
                        </div>
                        
                    </div>
                </section>

                <section class="game-panel game-reveal">
                    <div class="game-panel-head">
                        <div>
                            <h2>Play Controls</h2>
                        </div>
                    </div>
                    <div class="subway-keybind-list" style="margin-top: 1.2rem;">
                        
                        <div class="subway-keybind-item">
                            <label>Jump</label>
                            <button class="subway-key-btn" data-keybind="jump">W</button>
                        </div>
                        
                        <div class="subway-keybind-item">
                            <label>Roll / Duck</label>
                            <button class="subway-key-btn" data-keybind="duck">S</button>
                        </div>
                        
                        <div class="subway-keybind-item">
                            <label>Move Left</label>
                            <button class="subway-key-btn" data-keybind="left">A</button>
                        </div>
                        
                        <div class="subway-keybind-item">
                            <label>Move Right</label>
                            <button class="subway-key-btn" data-keybind="right">D</button>
                        </div>
                        
                    </div>
                </section>
            </div>

            <!-- Map Selection Grid -->
            <section class="game-panel game-reveal" style="margin-top: 2rem;">
                <div class="game-panel-head">
                    <div>
                        <h2>Select Map</h2>
                        <p>Choose one of the verified builds and stay on Cripsum for the entire game.</p>
                    </div>
                </div>
                
                <div class="subway-grid" hidden>
                    
                    <!-- builds-1 maps -->
                    <div class="subway-map-card" data-map="zurich">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag">Europe</span>
                            <h3>Zurich</h3>
                            <p>Run through gorgeous Swiss alpine cities and subway tracks.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="beijing">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag">Asia</span>
                            <h3>Beijing</h3>
                            <p>Dash through historic oriental temples and Chinese lanterns.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="cairo">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag">Africa</span>
                            <h3>Cairo</h3>
                            <p>Explore the mysteries of Ancient Egypt among desert sands.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="paris">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag">Europe</span>
                            <h3>Paris</h3>
                            <p>The city of lights with its gorgeous avenues and subways.</p>
                        </div>
                    </div>

                    <!-- builds-2 maps -->
                    <div class="subway-map-card" data-map="tokyo">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-2">Asia</span>
                            <h3>Tokyo</h3>
                            <p>Flee beneath neon lights of Shibuya and blooming cherry trees.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="london">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-2">Europe</span>
                            <h3>London</h3>
                            <p>Dash alongside the iconic Big Ben and historic brick subway tunnels.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="mexico">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-2">America</span>
                            <h3>Mexico</h3>
                            <p>Warm colors and vibrant festivals along Mexican tracks.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="newyork">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-2">America</span>
                            <h3>New York</h3>
                            <p>Avoid incoming trains inside the Big Apple's subway layout.</p>
                        </div>
                    </div>

                    <!-- builds-3 maps -->
                    <div class="subway-map-card" data-map="bangkok">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-3">Asia</span>
                            <h3>Bangkok</h3>
                            <p>Dash among floating markets and golden temples.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="berlin">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-3">Europe</span>
                            <h3>Berlin</h3>
                            <p>Historic walls and the majesty of Brandenburg Gate.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="buenosaires">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-3">America</span>
                            <h3>Buenos Aires</h3>
                            <p>Immerse yourself in Argentina's beautiful colored architecture.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="moscow">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-3">Europe</span>
                            <h3>Moscow</h3>
                            <p>Dash among golden Kremlin domes and ornate metro stations.</p>
                        </div>
                    </div>
                    
                </div>
            </section>
        </div>

        <!-- STATE 2: The Game Screen Arena with draggable overlays -->
        <div id="subwayGameArea" style="display: none;">
            <div class="subway-arena-wrapper">
                
                <!-- Game Exit button -->
                <button class="game-btn game-btn-special" id="exitGameBtn" style="position: absolute; top: 15px; left: 15px; z-index: 9999; box-shadow: 0 8px 24px rgba(0,0,0,0.5);">
                    <i class="fa-solid fa-arrow-left"></i> Back to Selection
                </button>

                <div class="subway-start-hint" id="subwayStartHint" aria-live="polite">
                    <kbd>SPACE</kbd>
                    <span>Start the run in game. The timer begins only when gameplay actually starts.</span>
                </div>

                <!-- Floating Timer Widget (Draggable) -->
                <div class="subway-hud-widget subway-timer-widget" id="hudWidgetTimer">
                    <div class="widget-handle">
                        <strong><i class="fa-solid fa-stopwatch"></i> Run Timer</strong>
                        <i class="fa-solid fa-arrows-up-down-left-right"></i>
                    </div>
                    <div class="subway-timer-display" id="subwayTimerDisplay">00:00.000</div>
                </div>

                <!-- Floating Challenge Status Widget (Draggable) -->
                <div class="subway-hud-widget subway-status-widget" id="hudWidgetStatus">
                    <div class="widget-handle">
                        <strong><i class="fa-solid fa-shield-halved"></i> Challenge HUD</strong>
                        <i class="fa-solid fa-arrows-up-down-left-right"></i>
                    </div>
                    <div class="subway-status-row">
                        <span>Mode:</span>
                        <strong>No-Coin</strong>
                    </div>
                    <div class="subway-status-row">
                        <span>Status:</span>
                        <span class="subway-status-badge active" id="subwayStatusBadge">Active</span>
                    </div>
                </div>

                <!-- Floating Keys HUD (Draggable) -->
                <div class="subway-hud-widget subway-keys-widget" id="hudWidgetKeys">
                    <div class="widget-handle">
                        <strong><i class="fa-solid fa-keyboard"></i> Controls</strong>
                        <i class="fa-solid fa-arrows-up-down-left-right"></i>
                    </div>
                    <div class="subway-hud-keys-grid">
                        <span></span>
                        <div class="subway-hud-key" id="hudKey-jump">W</div>
                        <span></span>
                        
                        <div class="subway-hud-key" id="hudKey-left">A</div>
                        <div class="subway-hud-key" id="hudKey-duck">S</div>
                        <div class="subway-hud-key" id="hudKey-right">D</div>
                    </div>
                </div>

                <!-- Floating Setup button (Draggable button) -->
                <div class="subway-hud-widget subway-settings-btn-widget" id="hudWidgetSettingsBtn">
                    <i class="fa-solid fa-gear"></i>
                </div>

                <!-- The WebGL Canvas host -->
                <div class="subway-game-container" id="subwayGameContainer">
                    <!-- Canvas will be dynamically injected here -->
                </div>

                <!-- Controls Config Modal inside game frame -->
                <div class="subway-settings-modal" id="subwaySettingsModal">
                    <div class="subway-modal-box">
                        <h3 style="margin-top: 0; color: #fff;"><i class="fa-solid fa-gear"></i> Remap Keys</h3>
                        <p style="font-size: 0.8rem; color: var(--game-muted); margin-bottom: 1.2rem;">Click a bind button, then press any key on your keyboard to map it.</p>
                        
                        <div class="subway-keybind-list">
                            <div class="subway-keybind-item">
                                <label>Jump</label>
                                <button class="subway-key-btn" data-keybind="jump">W</button>
                            </div>
                            <div class="subway-keybind-item">
                                <label>Duck / Slide</label>
                                <button class="subway-key-btn" data-keybind="duck">S</button>
                            </div>
                            <div class="subway-keybind-item">
                                <label>Move Left</label>
                                <button class="subway-key-btn" data-keybind="left">A</button>
                            </div>
                            <div class="subway-keybind-item">
                                <label>Move Right</label>
                                <button class="subway-key-btn" data-keybind="right">D</button>
                            </div>
                        </div>

                        <button class="game-btn game-btn-main" id="closeSettingsModal" style="width: 100%; margin-top: 1.5rem; min-height: 2.5rem;">
                            Save & Close
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- Unity Loading Splash (Full Screen Bootstrap overlay) -->
    <div class="subway-boot-splash hidden" id="subwayBootSplash" role="status" aria-live="polite">
        <main class="subway-boot-panel">
            <header class="subway-boot-header">
                <div class="subway-boot-identity">
                    <span class="subway-boot-logo"><i class="fa-solid fa-train"></i></span>
                    <div class="subway-boot-title">
                        <strong>Subway Portal</strong>
                        <small>Cripsum™ WebGL Launcher</small>
                    </div>
                </div>
                <div class="subway-boot-session">
                    <i class="subway-boot-pulse-dot"></i>
                    <span>Booting</span>
                </div>
                <button class="subway-boot-cancel" id="cancelSubwayLoad" type="button">Cancel</button>
            </header>

            <div class="subway-boot-content">
                <section class="subway-boot-narrative">
                    <h1 id="subwayBootStage">Preparing Portal</h1>
                    <p id="subwayBootStatus">Starting audio and logging interceptors...</p>
                </section>
                
                <div class="subway-boot-progress-section">
                    <span class="subway-boot-stage">CORE BOOT RUNNING</span>
                    <span class="subway-boot-percent"><strong id="subwayBootPercent">0</strong>%</span>
                </div>
                
                <div class="subway-boot-track-line">
                    <div class="subway-boot-track-value" id="subwayBootTrackValue"></div>
                </div>

                <!-- Boot Console (Telemetry & Injection status log) -->
                <div class="subway-boot-console" id="subwayBootConsole">
                    <!-- Logs dynamically written here -->
                </div>
            </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
