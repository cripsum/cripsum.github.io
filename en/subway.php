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
    <link rel="stylesheet" href="/assets/css/subway.css?v=13.1">
    <script src="/assets/js/subway/subway-profile.js?v=1.0" defer></script>
    <script src="/assets/js/subway/subway.js?v=13.1" defer></script>
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
                    <h1>Subway Surfers</h1>
                    <div class="game-steps" aria-label="Challenge Steps">
                        <span><b>1</b> Select City</span>
                        <span><b>2</b> Configure Player</span>
                        <span><b>3</b> Avoid Coins</span>
                    </div>
                </div>
            </section>

            <!-- Custom Options & Key Binds Section -->
            <div class="subway-settings-panel" hidden aria-hidden="true">
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
                                <input type="checkbox" checked tabindex="-1">
                                <span class="subway-slider"></span>
                            </label>
                        </div>

                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong>Block SPACE</strong>
                                <span>Stops SPACE from reaching the game, preventing accidental hoverboard activation.</span>
                            </div>
                            <label class="subway-switch">
                                <input type="checkbox" data-setting="blockSpace">
                                <span class="subway-slider"></span>
                            </label>
                        </div>

                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong>Adaptive VSync</strong>
                                <span>Matches the game's frame rate to the monitor's actual refresh, including above 144 Hz.</span>
                            </div>
                            <label class="subway-switch">
                                <input type="checkbox" data-setting="vsync" checked>
                                <span class="subway-slider"></span>
                            </label>
                        </div>

                        <label class="subway-fps-limit-setting" data-fps-control>
                            <span><strong>FPS Limit</strong><small>Used while VSync is disabled.</small></span>
                            <input type="number" min="30" max="500" step="1" value="144" data-fps-limit>
                        </label>

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
                        <div class="subway-keybind-item">
                            <label>Start Red Boost</label>
                            <button class="subway-key-btn" data-keybind="boost">B</button>
                        </div>

                    </div>
                </section>

                <section class="game-panel game-reveal subway-overlay-customization">
                    <div class="game-panel-head">
                        <div>
                            <h2>Overlay Appearance</h2>
                            <p>Customize every panel with real-time live preview. Settings are saved automatically.</p>
                        </div>
                    </div>

                    <!-- Live Preview Stage -->
                    <div class="subway-preview-stage">
                        <!-- Timer Preview -->
                        <div class="subway-hud-widget subway-timer-widget subway-preview-widget" data-preview-widget="timer">
                            <div class="subway-timer-banner-bg"></div>
                            <div class="widget-handle">
                                <strong>TIMER</strong>
                                <i class="fa-solid fa-arrows-up-down-left-right"></i>
                            </div>
                            <div class="subway-timer-display">00:00<span class="subway-ms">.000</span></div>
                        </div>

                        <!-- FPS Preview -->
                        <div class="subway-hud-widget subway-fps-widget subway-preview-widget" data-preview-widget="fps">
                            <div class="widget-handle">
                                <strong>VSYNC / FPS</strong>
                                <i class="fa-solid fa-arrows-up-down-left-right"></i>
                            </div>
                            <div class="subway-fps-readout">
                                <strong>144</strong><span>FPS</span>
                            </div>
                            <div class="subway-vsync-state"><i class="fa-solid fa-circle"></i> VSync Active</div>
                        </div>

                        <!-- WASD Keys Preview -->
                        <div class="subway-hud-widget subway-keys-widget subway-preview-widget" data-preview-widget="keys">
                            <div class="widget-handle">
                                <strong>WASD</strong>
                                <i class="fa-solid fa-arrows-up-down-left-right"></i>
                            </div>
                            <div class="subway-hud-keys-grid">
                                <span></span>
                                <div class="subway-hud-key">W</div>
                                <span></span>

                                <div class="subway-hud-key">A</div>
                                <div class="subway-hud-key">S</div>
                                <div class="subway-hud-key">D</div>
                            </div>
                            <div class="subway-hud-boost-row">
                                <span>BOOST</span>
                                <div class="subway-hud-key">B</div>
                            </div>
                        </div>

                        <!-- Settings Button Preview -->
                        <button class="subway-hud-widget subway-settings-btn-widget subway-preview-widget" data-preview-widget="settings" type="button" aria-label="Settings">
                            <i class="fa-solid fa-gear"></i>
                        </button>
                    </div>

                    <!-- Overlay Tabs Navigation -->
                    <div class="subway-overlay-tabs">
                        <button type="button" class="subway-tab-btn active" data-overlay-tab="timer"><i class="fa-solid fa-stopwatch"></i> Timer</button>
                        <button type="button" class="subway-tab-btn" data-overlay-tab="fps"><i class="fa-solid fa-gauge-high"></i> FPS</button>
                        <button type="button" class="subway-tab-btn" data-overlay-tab="keys"><i class="fa-solid fa-keyboard"></i> WASD Controls</button>
                        <button type="button" class="subway-tab-btn" data-overlay-tab="settings"><i class="fa-solid fa-gear"></i> Settings Button</button>
                        <button type="button" class="subway-tab-btn" data-overlay-tab="layout"><i class="fa-solid fa-sliders"></i> General Layout</button>
                    </div>

                    <!-- Tab Pane: Timer -->
                    <div class="subway-tab-pane active" data-overlay-pane="timer">
                        <div class="subway-widget-config-card">
                            <div class="subway-widget-card-title"><i class="fa-solid fa-stopwatch"></i> Timer Overlay</div>
                            <div class="subway-theme-grid">
                                <label class="subway-color-setting"><span>Background</span><input type="color" value="#090d18" data-widget="timer" data-widget-prop="bg"></label>
                                <label class="subway-color-setting"><span>Text</span><input type="color" value="#ffffff" data-widget="timer" data-widget-prop="textColor"></label>
                                <label class="subway-color-setting"><span>Border</span><input type="color" value="#06b6d4" data-widget="timer" data-widget-prop="borderColor"></label>
                            </div>
                            <label class="subway-opacity-setting">
                                <span>Background opacity <output data-widget-output>88%</output></span>
                                <input type="range" min="0" max="100" step="1" value="88" data-widget="timer" data-widget-prop="bgOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Border opacity <output data-widget-output>68%</output></span>
                                <input type="range" min="0" max="100" step="1" value="68" data-widget="timer" data-widget-prop="borderOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Border radius <output data-widget-output>12px</output></span>
                                <input type="range" min="0" max="50" step="1" value="12" data-widget="timer" data-widget-prop="borderRadius">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Blur effect <output data-widget-output>16px</output></span>
                                <input type="range" min="0" max="30" step="1" value="16" data-widget="timer" data-widget-prop="blur">
                            </label>

                            <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Text Drop Shadow</h5>
                                <div class="subway-theme-grid">
                                    <label class="subway-color-setting"><span>Shadow Color</span><input type="color" value="#000000" data-widget="timer" data-widget-prop="shadowColor"></label>
                                </div>
                                <label class="subway-opacity-setting">
                                    <span>Shadow Opacity <output data-widget-output>50%</output></span>
                                    <input type="range" min="0" max="100" step="1" value="50" data-widget="timer" data-widget-prop="shadowOpacity">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Shadow Blur <output data-widget-output>8px</output></span>
                                    <input type="range" min="0" max="30" step="1" value="8" data-widget="timer" data-widget-prop="shadowBlur">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Offset X <output data-widget-output>0px</output></span>
                                    <input type="range" min="-20" max="20" step="1" value="0" data-widget="timer" data-widget-prop="shadowX">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Offset Y <output data-widget-output>2px</output></span>
                                    <input type="range" min="-20" max="20" step="1" value="2" data-widget="timer" data-widget-prop="shadowY">
                                </label>
                            </div>

                            <div class="subway-img-setting">
                                <h5><i class="fa-solid fa-image"></i> Timer Custom Background Banner Editor</h5>
                                <div class="subway-img-input-row">
                                    <input type="text" placeholder="Image URL (e.g. https://...)" data-widget="timer" data-widget-prop="bgImage">
                                    <label class="subway-img-btn">
                                        <i class="fa-solid fa-upload"></i> Upload...
                                        <input type="file" accept="image/*" data-timer-bg-file style="display:none;">
                                    </label>
                                    <button type="button" class="subway-img-btn subway-img-btn-remove" data-timer-bg-remove title="Remove image">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                                <div class="subway-img-preview-thumb" data-timer-bg-preview>No custom banner image set</div>
                                <label class="subway-opacity-setting" style="margin-top: 0.5rem;">
                                    <span>Banner Opacity <output data-widget-output>100%</output></span>
                                    <input type="range" min="0" max="100" step="1" value="100" data-widget="timer" data-widget-prop="bgImageOpacity">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Image Scale / Zoom <output data-widget-output>100%</output></span>
                                    <input type="range" min="50" max="300" step="1" value="100" data-widget="timer" data-widget-prop="bgImageScale">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Rotation <output data-widget-output>0°</output></span>
                                    <input type="range" min="0" max="360" step="1" value="0" data-widget="timer" data-widget-prop="bgImageRotate">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Offset X <output data-widget-output>0px</output></span>
                                    <input type="range" min="-100" max="100" step="1" value="0" data-widget="timer" data-widget-prop="bgImagePosX">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Offset Y <output data-widget-output>0px</output></span>
                                    <input type="range" min="-100" max="100" step="1" value="0" data-widget="timer" data-widget-prop="bgImagePosY">
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Pane: FPS -->
                    <div class="subway-tab-pane" data-overlay-pane="fps">
                        <div class="subway-widget-config-card">
                            <div class="subway-widget-card-title"><i class="fa-solid fa-gauge-high"></i> FPS / VSync Overlay</div>
                            <div class="subway-theme-grid">
                                <label class="subway-color-setting"><span>Background</span><input type="color" value="#090d18" data-widget="fps" data-widget-prop="bg"></label>
                                <label class="subway-color-setting"><span>Text</span><input type="color" value="#ffffff" data-widget="fps" data-widget-prop="textColor"></label>
                                <label class="subway-color-setting"><span>Border</span><input type="color" value="#06b6d4" data-widget="fps" data-widget-prop="borderColor"></label>
                            </div>
                            <label class="subway-opacity-setting">
                                <span>Background opacity <output data-widget-output>88%</output></span>
                                <input type="range" min="0" max="100" step="1" value="88" data-widget="fps" data-widget-prop="bgOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Border opacity <output data-widget-output>68%</output></span>
                                <input type="range" min="0" max="100" step="1" value="68" data-widget="fps" data-widget-prop="borderOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Border radius <output data-widget-output>12px</output></span>
                                <input type="range" min="0" max="50" step="1" value="12" data-widget="fps" data-widget-prop="borderRadius">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Blur effect <output data-widget-output>16px</output></span>
                                <input type="range" min="0" max="30" step="1" value="16" data-widget="fps" data-widget-prop="blur">
                            </label>

                            <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Text Drop Shadow</h5>
                                <div class="subway-theme-grid">
                                    <label class="subway-color-setting"><span>Shadow Color</span><input type="color" value="#000000" data-widget="fps" data-widget-prop="shadowColor"></label>
                                </div>
                                <label class="subway-opacity-setting">
                                    <span>Shadow Opacity <output data-widget-output>50%</output></span>
                                    <input type="range" min="0" max="100" step="1" value="50" data-widget="fps" data-widget-prop="shadowOpacity">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Shadow Blur <output data-widget-output>8px</output></span>
                                    <input type="range" min="0" max="30" step="1" value="8" data-widget="fps" data-widget-prop="shadowBlur">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Offset X <output data-widget-output>0px</output></span>
                                    <input type="range" min="-20" max="20" step="1" value="0" data-widget="fps" data-widget-prop="shadowX">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Offset Y <output data-widget-output>2px</output></span>
                                    <input type="range" min="-20" max="20" step="1" value="2" data-widget="fps" data-widget-prop="shadowY">
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Pane: WASD Keys -->
                    <div class="subway-tab-pane" data-overlay-pane="keys">
                        <div class="subway-widget-config-card">
                            <div class="subway-widget-card-title"><i class="fa-solid fa-keyboard"></i> WASD Controls Overlay</div>
                            <div class="subway-theme-grid">
                                <label class="subway-color-setting"><span>Box BG</span><input type="color" value="#090d18" data-widget="keys" data-widget-prop="bg"></label>
                                <label class="subway-color-setting"><span>Box Text</span><input type="color" value="#ffffff" data-widget="keys" data-widget-prop="textColor"></label>
                                <label class="subway-color-setting"><span>Box Border</span><input type="color" value="#06b6d4" data-widget="keys" data-widget-prop="borderColor"></label>
                            </div>
                            <label class="subway-opacity-setting">
                                <span>Box background opacity <output data-widget-output>88%</output></span>
                                <input type="range" min="0" max="100" step="1" value="88" data-widget="keys" data-widget-prop="bgOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Box border opacity <output data-widget-output>68%</output></span>
                                <input type="range" min="0" max="100" step="1" value="68" data-widget="keys" data-widget-prop="borderOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Box border radius <output data-widget-output>12px</output></span>
                                <input type="range" min="0" max="50" step="1" value="12" data-widget="keys" data-widget-prop="borderRadius">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Box blur effect <output data-widget-output>16px</output></span>
                                <input type="range" min="0" max="30" step="1" value="16" data-widget="keys" data-widget-prop="blur">
                            </label>

                            <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Text Drop Shadow</h5>
                                <div class="subway-theme-grid">
                                    <label class="subway-color-setting"><span>Shadow Color</span><input type="color" value="#000000" data-widget="keys" data-widget-prop="shadowColor"></label>
                                </div>
                                <label class="subway-opacity-setting">
                                    <span>Shadow Opacity <output data-widget-output>50%</output></span>
                                    <input type="range" min="0" max="100" step="1" value="50" data-widget="keys" data-widget-prop="shadowOpacity">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Shadow Blur <output data-widget-output>8px</output></span>
                                    <input type="range" min="0" max="30" step="1" value="8" data-widget="keys" data-widget-prop="shadowBlur">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Offset X <output data-widget-output>0px</output></span>
                                    <input type="range" min="-20" max="20" step="1" value="0" data-widget="keys" data-widget-prop="shadowX">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Offset Y <output data-widget-output>2px</output></span>
                                    <input type="range" min="-20" max="20" step="1" value="2" data-widget="keys" data-widget-prop="shadowY">
                                </label>
                            </div>

                            <div style="margin-top: 1.2rem; padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.08);">
                                <h5 style="margin: 0 0 0.8rem 0; color: #fff; font-size: 0.85rem;"><i class="fa-solid fa-square-full"></i> Inner WASD Keys Style</h5>
                                <div class="subway-theme-grid">
                                    <label class="subway-color-setting"><span>Keys BG</span><input type="color" value="#ffffff" data-widget="keys" data-widget-prop="keyBg"></label>
                                    <label class="subway-color-setting"><span>Hover / Active</span><input type="color" value="#06b6d4" data-widget="keys" data-widget-prop="keyHover"></label>
                                    <label class="subway-color-setting"><span>Keys Text</span><input type="color" value="#ffffff" data-widget="keys" data-widget-prop="keyText"></label>
                                    <label class="subway-color-setting"><span>Keys Border</span><input type="color" value="#06b6d4" data-widget="keys" data-widget-prop="keyBorderColor"></label>
                                </div>
                                <label class="subway-opacity-setting">
                                    <span>Keys background opacity <output data-widget-output>7%</output></span>
                                    <input type="range" min="0" max="100" step="1" value="7" data-widget="keys" data-widget-prop="keyBgOpacity">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Keys border opacity <output data-widget-output>40%</output></span>
                                    <input type="range" min="0" max="100" step="1" value="40" data-widget="keys" data-widget-prop="keyBorderOpacity">
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Pane: Settings Button -->
                    <div class="subway-tab-pane" data-overlay-pane="settings">
                        <div class="subway-widget-config-card">
                            <div class="subway-widget-card-title"><i class="fa-solid fa-gear"></i> Settings Button Overlay</div>
                            <div class="subway-theme-grid">
                                <label class="subway-color-setting"><span>Background</span><input type="color" value="#090d18" data-widget="settings" data-widget-prop="bg"></label>
                                <label class="subway-color-setting"><span>Icon</span><input type="color" value="#ffffff" data-widget="settings" data-widget-prop="textColor"></label>
                                <label class="subway-color-setting"><span>Border</span><input type="color" value="#06b6d4" data-widget="settings" data-widget-prop="borderColor"></label>
                            </div>
                            <label class="subway-opacity-setting">
                                <span>Background opacity <output data-widget-output>88%</output></span>
                                <input type="range" min="0" max="100" step="1" value="88" data-widget="settings" data-widget-prop="bgOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Border opacity <output data-widget-output>68%</output></span>
                                <input type="range" min="0" max="100" step="1" value="68" data-widget="settings" data-widget-prop="borderOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Border radius <output data-widget-output>12px</output></span>
                                <input type="range" min="0" max="50" step="1" value="12" data-widget="settings" data-widget-prop="borderRadius">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Blur effect <output data-widget-output>16px</output></span>
                                <input type="range" min="0" max="30" step="1" value="16" data-widget="settings" data-widget-prop="blur">
                            </label>

                            <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Icon Shadow</h5>
                                <div class="subway-theme-grid">
                                    <label class="subway-color-setting"><span>Shadow Color</span><input type="color" value="#000000" data-widget="settings" data-widget-prop="shadowColor"></label>
                                </div>
                                <label class="subway-opacity-setting">
                                    <span>Shadow Opacity <output data-widget-output>50%</output></span>
                                    <input type="range" min="0" max="100" step="1" value="50" data-widget="settings" data-widget-prop="shadowOpacity">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Shadow Blur <output data-widget-output>8px</output></span>
                                    <input type="range" min="0" max="30" step="1" value="8" data-widget="settings" data-widget-prop="shadowBlur">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Offset X <output data-widget-output>0px</output></span>
                                    <input type="range" min="-20" max="20" step="1" value="0" data-widget="settings" data-widget-prop="shadowX">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Offset Y <output data-widget-output>2px</output></span>
                                    <input type="range" min="-20" max="20" step="1" value="2" data-widget="settings" data-widget-prop="shadowY">
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Pane: Layout -->
                    <div class="subway-tab-pane" data-overlay-pane="layout">
                        <div class="subway-widget-config-card">
                            <div class="subway-widget-card-title"><i class="fa-solid fa-sliders"></i> General Layout</div>
                            <div class="subway-option-row">
                                <div class="subway-option-info">
                                    <strong>Show Boost Key (B)</strong>
                                    <span>Display the B boost key row below WASD.</span>
                                </div>
                                <label class="subway-switch">
                                    <input type="checkbox" data-overlay-toggle="showBoost">
                                    <span class="subway-slider"></span>
                                </label>
                            </div>
                            <div class="subway-option-row">
                                <div class="subway-option-info">
                                    <strong>Show Overlay Headers</strong>
                                    <span>Display drag handle bars and overlay titles.</span>
                                </div>
                                <label class="subway-switch">
                                    <input type="checkbox" data-overlay-toggle="showHeaders" checked>
                                    <span class="subway-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="subway-theme-reset" data-reset-overlay-theme style="margin-top: 1.2rem;">Reset default settings</button>
                </section>
            </div>

            <!-- Map Selection Grid -->
            <section class="game-panel game-reveal" style="margin-top: 2rem;">
                <div class="game-panel-head">
                    <div>
                        <h2>Select Map</h2>
                    </div>
                    <button type="button" class="game-btn game-btn-soft subway-map-settings-btn" id="openSubwaySettings">
                        <i class="fa-solid fa-sliders"></i>
                        <span>Settings</span>
                    </button>
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

                <!-- Floating Timer Widget (Draggable) -->
                <div class="subway-hud-widget subway-timer-widget" id="hudWidgetTimer">
                    <div class="subway-timer-banner-bg"></div>
                    <div class="subway-timer-display" id="subwayTimerDisplay">00:00.000</div>
                </div>

                <!-- Floating FPS / VSync Widget (Draggable) -->
                <div class="subway-hud-widget subway-fps-widget" id="hudWidgetFps">
                    <div class="subway-fps-readout">
                        <strong id="subwayFpsValue">--</strong><span>FPS</span>
                    </div>
                </div>

                <!-- Floating Keys HUD (Draggable) -->
                <div class="subway-hud-widget subway-keys-widget" id="hudWidgetKeys">
                    <div class="subway-hud-keys-grid">
                        <span></span>
                        <div class="subway-hud-key" id="hudKey-jump">W</div>
                        <span></span>

                        <div class="subway-hud-key" id="hudKey-left">A</div>
                        <div class="subway-hud-key" id="hudKey-duck">S</div>
                        <div class="subway-hud-key" id="hudKey-right">D</div>
                    </div>
                    <div class="subway-hud-boost-row">
                        <span>BOOST</span>
                        <div class="subway-hud-key" id="hudKey-boost">B</div>
                    </div>
                </div>

                <!-- Floating Setup button (Draggable button) -->
                <button class="subway-hud-widget subway-settings-btn-widget" id="hudWidgetSettingsBtn" type="button" aria-label="Open game settings">
                    <i class="fa-solid fa-gear"></i>
                </button>

                <!-- The WebGL Canvas host -->
                <div class="subway-game-container" id="subwayGameContainer">
                    <!-- Canvas will be dynamically injected here -->
                </div>

                <!-- Controls Config Modal inside game frame -->
                <div class="subway-settings-modal" id="subwaySettingsModal">
                    <div class="subway-modal-box">
                        <h3 style="margin-top: 0; color: #fff;"><i class="fa-solid fa-gear"></i> Game Settings</h3>
                        <p style="font-size: 0.8rem; color: var(--game-muted); margin-bottom: 1.2rem;">Keys, hoverboard protection and overlay appearance are saved automatically.</p>

                        <div class="subway-modal-section subway-modal-section-first">
                            <div class="subway-option-row">
                                <div class="subway-option-info">
                                    <strong>No-Coin Challenge</strong>
                                    <span>Starts the timer with the run and stops it on the first coin.</span>
                                </div>
                                <label class="subway-switch">
                                    <input type="checkbox" id="toggleNoCoinChallenge" checked>
                                    <span class="subway-slider"></span>
                                </label>
                            </div>
                        </div>

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
                            <div class="subway-keybind-item">
                                <label>Start Red Boost</label>
                                <button class="subway-key-btn" data-keybind="boost">B</button>
                            </div>
                        </div>

                        <div class="subway-modal-section">
                            <div class="subway-option-row">
                                <div class="subway-option-info">
                                    <strong>Block SPACE</strong>
                                    <span>Prevents accidental hoverboard activation.</span>
                                </div>
                                <label class="subway-switch">
                                    <input type="checkbox" data-setting="blockSpace">
                                    <span class="subway-slider"></span>
                                </label>
                            </div>
                            <div class="subway-option-row subway-modal-timing-row">
                                <div class="subway-option-info">
                                    <strong>Adaptive VSync</strong>
                                    <span>Automatically follows the monitor refresh rate.</span>
                                </div>
                                <label class="subway-switch">
                                    <input type="checkbox" data-setting="vsync" checked>
                                    <span class="subway-slider"></span>
                                </label>
                            </div>
                            <label class="subway-fps-limit-setting" data-fps-control>
                                <span><strong>FPS Limit</strong><small>Available while VSync is disabled.</small></span>
                                <input type="number" min="30" max="500" step="1" value="144" data-fps-limit>
                            </label>
                        </div>

                        <div class="subway-modal-section">
                            <h4>Overlay Appearance</h4>
                            
                            <!-- Modal Overlay Tabs Navigation -->
                            <div class="subway-overlay-tabs">
                                <button type="button" class="subway-tab-btn active" data-overlay-tab="timer"><i class="fa-solid fa-stopwatch"></i> Timer</button>
                                <button type="button" class="subway-tab-btn" data-overlay-tab="fps"><i class="fa-solid fa-gauge-high"></i> FPS</button>
                                <button type="button" class="subway-tab-btn" data-overlay-tab="keys"><i class="fa-solid fa-keyboard"></i> WASD Controls</button>
                                <button type="button" class="subway-tab-btn" data-overlay-tab="settings"><i class="fa-solid fa-gear"></i> Settings</button>
                                <button type="button" class="subway-tab-btn" data-overlay-tab="layout"><i class="fa-solid fa-sliders"></i> Layout</button>
                            </div>

                            <!-- Modal Tab Pane: Timer -->
                            <div class="subway-tab-pane active" data-overlay-pane="timer">
                                <div class="subway-widget-config-card">
                                    <div class="subway-widget-card-title"><i class="fa-solid fa-stopwatch"></i> Timer Overlay</div>
                                    <div class="subway-theme-grid">
                                        <label class="subway-color-setting"><span>Background</span><input type="color" value="#090d18" data-widget="timer" data-widget-prop="bg"></label>
                                        <label class="subway-color-setting"><span>Text</span><input type="color" value="#ffffff" data-widget="timer" data-widget-prop="textColor"></label>
                                        <label class="subway-color-setting"><span>Border</span><input type="color" value="#06b6d4" data-widget="timer" data-widget-prop="borderColor"></label>
                                    </div>
                                    <label class="subway-opacity-setting">
                                        <span>Background opacity <output data-widget-output>88%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="88" data-widget="timer" data-widget-prop="bgOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Border opacity <output data-widget-output>68%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="68" data-widget="timer" data-widget-prop="borderOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Border radius <output data-widget-output>12px</output></span>
                                        <input type="range" min="0" max="50" step="1" value="12" data-widget="timer" data-widget-prop="borderRadius">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Blur effect <output data-widget-output>16px</output></span>
                                        <input type="range" min="0" max="30" step="1" value="16" data-widget="timer" data-widget-prop="blur">
                                    </label>

                                    <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                        <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Text Drop Shadow</h5>
                                        <div class="subway-theme-grid">
                                            <label class="subway-color-setting"><span>Shadow Color</span><input type="color" value="#000000" data-widget="timer" data-widget-prop="shadowColor"></label>
                                        </div>
                                        <label class="subway-opacity-setting">
                                            <span>Shadow Opacity <output data-widget-output>50%</output></span>
                                            <input type="range" min="0" max="100" step="1" value="50" data-widget="timer" data-widget-prop="shadowOpacity">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Shadow Blur <output data-widget-output>8px</output></span>
                                            <input type="range" min="0" max="30" step="1" value="8" data-widget="timer" data-widget-prop="shadowBlur">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Offset X <output data-widget-output>0px</output></span>
                                            <input type="range" min="-20" max="20" step="1" value="0" data-widget="timer" data-widget-prop="shadowX">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Offset Y <output data-widget-output>2px</output></span>
                                            <input type="range" min="-20" max="20" step="1" value="2" data-widget="timer" data-widget-prop="shadowY">
                                        </label>
                                    </div>

                                    <div class="subway-img-setting">
                                        <h5><i class="fa-solid fa-image"></i> Timer Custom Background Banner Editor</h5>
                                        <div class="subway-img-input-row">
                                            <input type="text" placeholder="Image URL (e.g. https://...)" data-widget="timer" data-widget-prop="bgImage">
                                            <label class="subway-img-btn">
                                                <i class="fa-solid fa-upload"></i> Upload...
                                                <input type="file" accept="image/*" data-timer-bg-file style="display:none;">
                                            </label>
                                            <button type="button" class="subway-img-btn subway-img-btn-remove" data-timer-bg-remove title="Remove image">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                        <div class="subway-img-preview-thumb" data-timer-bg-preview>No custom banner image set</div>
                                        <label class="subway-opacity-setting" style="margin-top: 0.5rem;">
                                            <span>Banner Opacity <output data-widget-output>100%</output></span>
                                            <input type="range" min="0" max="100" step="1" value="100" data-widget="timer" data-widget-prop="bgImageOpacity">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Image Scale / Zoom <output data-widget-output>100%</output></span>
                                            <input type="range" min="50" max="300" step="1" value="100" data-widget="timer" data-widget-prop="bgImageScale">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Rotation <output data-widget-output>0°</output></span>
                                            <input type="range" min="0" max="360" step="1" value="0" data-widget="timer" data-widget-prop="bgImageRotate">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Offset X <output data-widget-output>0px</output></span>
                                            <input type="range" min="-100" max="100" step="1" value="0" data-widget="timer" data-widget-prop="bgImagePosX">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Offset Y <output data-widget-output>0px</output></span>
                                            <input type="range" min="-100" max="100" step="1" value="0" data-widget="timer" data-widget-prop="bgImagePosY">
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Tab Pane: FPS -->
                            <div class="subway-tab-pane" data-overlay-pane="fps">
                                <div class="subway-widget-config-card">
                                    <div class="subway-widget-card-title"><i class="fa-solid fa-gauge-high"></i> FPS / VSync Overlay</div>
                                    <div class="subway-theme-grid">
                                        <label class="subway-color-setting"><span>Background</span><input type="color" value="#090d18" data-widget="fps" data-widget-prop="bg"></label>
                                        <label class="subway-color-setting"><span>Text</span><input type="color" value="#ffffff" data-widget="fps" data-widget-prop="textColor"></label>
                                        <label class="subway-color-setting"><span>Border</span><input type="color" value="#06b6d4" data-widget="fps" data-widget-prop="borderColor"></label>
                                    </div>
                                    <label class="subway-opacity-setting">
                                        <span>Background opacity <output data-widget-output>88%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="88" data-widget="fps" data-widget-prop="bgOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Border opacity <output data-widget-output>68%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="68" data-widget="fps" data-widget-prop="borderOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Border radius <output data-widget-output>12px</output></span>
                                        <input type="range" min="0" max="50" step="1" value="12" data-widget="fps" data-widget-prop="borderRadius">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Blur effect <output data-widget-output>16px</output></span>
                                        <input type="range" min="0" max="30" step="1" value="16" data-widget="fps" data-widget-prop="blur">
                                    </label>

                                    <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                        <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Text Drop Shadow</h5>
                                        <div class="subway-theme-grid">
                                            <label class="subway-color-setting"><span>Shadow Color</span><input type="color" value="#000000" data-widget="fps" data-widget-prop="shadowColor"></label>
                                        </div>
                                        <label class="subway-opacity-setting">
                                            <span>Shadow Opacity <output data-widget-output>50%</output></span>
                                            <input type="range" min="0" max="100" step="1" value="50" data-widget="fps" data-widget-prop="shadowOpacity">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Shadow Blur <output data-widget-output>8px</output></span>
                                            <input type="range" min="0" max="30" step="1" value="8" data-widget="fps" data-widget-prop="shadowBlur">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Offset X <output data-widget-output>0px</output></span>
                                            <input type="range" min="-20" max="20" step="1" value="0" data-widget="fps" data-widget-prop="shadowX">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Offset Y <output data-widget-output>2px</output></span>
                                            <input type="range" min="-20" max="20" step="1" value="2" data-widget="fps" data-widget-prop="shadowY">
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Tab Pane: WASD Keys -->
                            <div class="subway-tab-pane" data-overlay-pane="keys">
                                <div class="subway-widget-config-card">
                                    <div class="subway-widget-card-title"><i class="fa-solid fa-keyboard"></i> WASD Controls Overlay</div>
                                    <div class="subway-theme-grid">
                                        <label class="subway-color-setting"><span>Box BG</span><input type="color" value="#090d18" data-widget="keys" data-widget-prop="bg"></label>
                                        <label class="subway-color-setting"><span>Box Text</span><input type="color" value="#ffffff" data-widget="keys" data-widget-prop="textColor"></label>
                                        <label class="subway-color-setting"><span>Box Border</span><input type="color" value="#06b6d4" data-widget="keys" data-widget-prop="borderColor"></label>
                                    </div>
                                    <label class="subway-opacity-setting">
                                        <span>Box background opacity <output data-widget-output>88%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="88" data-widget="keys" data-widget-prop="bgOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Box border opacity <output data-widget-output>68%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="68" data-widget="keys" data-widget-prop="borderOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Box border radius <output data-widget-output>12px</output></span>
                                        <input type="range" min="0" max="50" step="1" value="12" data-widget="keys" data-widget-prop="borderRadius">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Box blur effect <output data-widget-output>16px</output></span>
                                        <input type="range" min="0" max="30" step="1" value="16" data-widget="keys" data-widget-prop="blur">
                                    </label>

                                    <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                        <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Text Drop Shadow</h5>
                                        <div class="subway-theme-grid">
                                            <label class="subway-color-setting"><span>Shadow Color</span><input type="color" value="#000000" data-widget="keys" data-widget-prop="shadowColor"></label>
                                        </div>
                                        <label class="subway-opacity-setting">
                                            <span>Shadow Opacity <output data-widget-output>50%</output></span>
                                            <input type="range" min="0" max="100" step="1" value="50" data-widget="keys" data-widget-prop="shadowOpacity">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Shadow Blur <output data-widget-output>8px</output></span>
                                            <input type="range" min="0" max="30" step="1" value="8" data-widget="keys" data-widget-prop="shadowBlur">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Offset X <output data-widget-output>0px</output></span>
                                            <input type="range" min="-20" max="20" step="1" value="0" data-widget="keys" data-widget-prop="shadowX">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Offset Y <output data-widget-output>2px</output></span>
                                            <input type="range" min="-20" max="20" step="1" value="2" data-widget="keys" data-widget-prop="shadowY">
                                        </label>
                                    </div>

                                    <div style="margin-top: 1.2rem; padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.08);">
                                        <h5 style="margin: 0 0 0.8rem 0; color: #fff; font-size: 0.85rem;"><i class="fa-solid fa-square-full"></i> Inner WASD Keys Style</h5>
                                        <div class="subway-theme-grid">
                                            <label class="subway-color-setting"><span>Keys BG</span><input type="color" value="#ffffff" data-widget="keys" data-widget-prop="keyBg"></label>
                                            <label class="subway-color-setting"><span>Hover / Active</span><input type="color" value="#06b6d4" data-widget="keys" data-widget-prop="keyHover"></label>
                                            <label class="subway-color-setting"><span>Keys Text</span><input type="color" value="#ffffff" data-widget="keys" data-widget-prop="keyText"></label>
                                            <label class="subway-color-setting"><span>Keys Border</span><input type="color" value="#06b6d4" data-widget="keys" data-widget-prop="keyBorderColor"></label>
                                        </div>
                                        <label class="subway-opacity-setting">
                                            <span>Keys background opacity <output data-widget-output>7%</output></span>
                                            <input type="range" min="0" max="100" step="1" value="7" data-widget="keys" data-widget-prop="keyBgOpacity">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Keys border opacity <output data-widget-output>40%</output></span>
                                            <input type="range" min="0" max="100" step="1" value="40" data-widget="keys" data-widget-prop="keyBorderOpacity">
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Tab Pane: Settings Button -->
                            <div class="subway-tab-pane" data-overlay-pane="settings">
                                <div class="subway-widget-config-card">
                                    <div class="subway-widget-card-title"><i class="fa-solid fa-gear"></i> Settings Button Overlay</div>
                                    <div class="subway-theme-grid">
                                        <label class="subway-color-setting"><span>Background</span><input type="color" value="#090d18" data-widget="settings" data-widget-prop="bg"></label>
                                        <label class="subway-color-setting"><span>Icon</span><input type="color" value="#ffffff" data-widget="settings" data-widget-prop="textColor"></label>
                                        <label class="subway-color-setting"><span>Border</span><input type="color" value="#06b6d4" data-widget="settings" data-widget-prop="borderColor"></label>
                                    </div>
                                    <label class="subway-opacity-setting">
                                        <span>Background opacity <output data-widget-output>88%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="88" data-widget="settings" data-widget-prop="bgOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Border opacity <output data-widget-output>68%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="68" data-widget="settings" data-widget-prop="borderOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Border radius <output data-widget-output>12px</output></span>
                                        <input type="range" min="0" max="50" step="1" value="12" data-widget="settings" data-widget-prop="borderRadius">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Blur effect <output data-widget-output>16px</output></span>
                                        <input type="range" min="0" max="30" step="1" value="16" data-widget="settings" data-widget-prop="blur">
                                    </label>

                                    <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                        <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Icon Shadow</h5>
                                        <div class="subway-theme-grid">
                                            <label class="subway-color-setting"><span>Shadow Color</span><input type="color" value="#000000" data-widget="settings" data-widget-prop="shadowColor"></label>
                                        </div>
                                        <label class="subway-opacity-setting">
                                            <span>Shadow Opacity <output data-widget-output>50%</output></span>
                                            <input type="range" min="0" max="100" step="1" value="50" data-widget="settings" data-widget-prop="shadowOpacity">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Shadow Blur <output data-widget-output>8px</output></span>
                                            <input type="range" min="0" max="30" step="1" value="8" data-widget="settings" data-widget-prop="shadowBlur">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Offset X <output data-widget-output>0px</output></span>
                                            <input type="range" min="-20" max="20" step="1" value="0" data-widget="settings" data-widget-prop="shadowX">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Offset Y <output data-widget-output>2px</output></span>
                                            <input type="range" min="-20" max="20" step="1" value="2" data-widget="settings" data-widget-prop="shadowY">
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Tab Pane: Layout -->
                            <div class="subway-tab-pane" data-overlay-pane="layout">
                                <div class="subway-widget-config-card">
                                    <div class="subway-widget-card-title"><i class="fa-solid fa-sliders"></i> General Layout</div>
                                    <div class="subway-option-row">
                                        <div class="subway-option-info">
                                            <strong>Show Boost Key (B)</strong>
                                            <span>Display the B boost key row below WASD.</span>
                                        </div>
                                        <label class="subway-switch">
                                            <input type="checkbox" data-overlay-toggle="showBoost">
                                            <span class="subway-slider"></span>
                                        </label>
                                    </div>
                                    <div class="subway-option-row">
                                        <div class="subway-option-info">
                                            <strong>Show Overlay Headers</strong>
                                            <span>Display drag handle bars and overlay titles.</span>
                                        </div>
                                        <label class="subway-switch">
                                            <input type="checkbox" data-overlay-toggle="showHeaders" checked>
                                            <span class="subway-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="subway-theme-reset" data-reset-overlay-theme style="margin-top: 1.2rem;">Reset default settings</button>
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