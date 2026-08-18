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
    $_SESSION['login_message'] = 'Per giocare devi essere loggato';
    header('Location: accedi');
    exit();
}

$ogDescription = 'Gioca a Subway Surfers direttamente su Cripsum con mappe World Tour, timer No-Coin e controlli configurabili.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/subway'), '#');
?>
<!DOCTYPE html>
<html lang="it">

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
    <link rel="stylesheet" href="/assets/css/subway.css?v=19.0">
    <script src="/assets/js/subway/subway-profile.js?v=2.0" defer></script>
    <script src="/assets/js/subway/subway.js?v=19.0" defer></script>
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
                    <div class="game-steps" aria-label="Passaggi della sfida">
                        <span><b>1</b> Scegli la città</span>
                        <span><b>2</b> Configura il player</span>
                        <span><b>3</b> Schiva le monete</span>
                    </div>
                </div>
                <!-- Personal Best Hero Card -->
                <div class="subway-personal-best-card" id="subwayPersonalBestCard" hidden>
                    <div class="subway-pb-badge"><i class="fa-solid fa-trophy"></i> Tuo Record No-Coin</div>
                    <div class="subway-pb-time" id="subwayPersonalBestTime">--:--.---</div>
                    <div class="subway-pb-meta">
                        <span>Posizione: <strong id="subwayPersonalBestRank">-</strong></span>
                        <span>Mappa: <strong id="subwayPersonalBestMap">-</strong></span>
                    </div>
                </div>
            </section>

            <!-- Custom Options & Key Binds Section -->
            <div class="subway-settings-panel" hidden aria-hidden="true">
                <section class="game-panel game-reveal">
                    <div class="game-panel-head">
                        <div>
                            <h2>Configurazione Sfida</h2>
                        </div>
                    </div>
                    <div class="game-action-list" style="margin-top: 1rem;">

                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong>No-Coin Challenge</strong>
                                <span>Avvia il timer sui tasti di movimento e lo ferma al primo audio/log di moneta raccolto.</span>
                            </div>
                            <label class="subway-switch">
                                <input type="checkbox" checked tabindex="-1">
                                <span class="subway-slider"></span>
                            </label>
                        </div>

                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong>Blocca SPAZIO</strong>
                                <span>Impedisce a SPAZIO di raggiungere il gioco, evitando di attivare l'hoverboard per errore.</span>
                            </div>
                            <label class="subway-switch">
                                <input type="checkbox" data-setting="blockSpace">
                                <span class="subway-slider"></span>
                            </label>
                        </div>

                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong>Auto Boost (3x)</strong>
                                <span>Attiva automaticamente 3 volte il boost rosso (Headstart) all'inizio di ogni corsa.</span>
                            </div>
                            <label class="subway-switch">
                                <input type="checkbox" data-setting="autoBoost">
                                <span class="subway-slider"></span>
                            </label>
                        </div>

                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong>VSync adattivo</strong>
                                <span>Sincronizza il gioco con gli Hz reali del monitor, anche oltre 144 Hz.</span>
                            </div>
                            <label class="subway-switch">
                                <input type="checkbox" data-setting="vsync" checked>
                                <span class="subway-slider"></span>
                            </label>
                        </div>

                        <label class="subway-fps-limit-setting" data-fps-control>
                            <span><strong>Limite FPS</strong><small>Usato quando il VSync è disattivato.</small></span>
                            <input type="number" min="30" max="500" step="1" value="144" data-fps-limit>
                        </label>

                    </div>
                </section>

                <section class="game-panel game-reveal">
                    <div class="game-panel-head">
                        <div>
                            <h2>Tasti di Gioco</h2>
                        </div>
                    </div>
                    <div class="subway-keybind-list" style="margin-top: 1.2rem;">

                        <div class="subway-keybind-item">
                            <label>Salta / Jump</label>
                            <button class="subway-key-btn" data-keybind="jump">W</button>
                        </div>

                        <div class="subway-keybind-item">
                            <label>Scivola / Roll</label>
                            <button class="subway-key-btn" data-keybind="duck">S</button>
                        </div>

                        <div class="subway-keybind-item">
                            <label>Sposta Sinistra</label>
                            <button class="subway-key-btn" data-keybind="left">A</button>
                        </div>

                        <div class="subway-keybind-item">
                            <label>Sposta Destra</label>
                            <button class="subway-key-btn" data-keybind="right">D</button>
                        </div>
                        <div class="subway-keybind-item">
                            <label>Boost rosso iniziale</label>
                            <button class="subway-key-btn" data-keybind="boost">B</button>
                        </div>

                    </div>
                </section>

                <section class="game-panel game-reveal subway-overlay-customization">
                    <div class="game-panel-head">
                        <div>
                            <h2>Aspetto Overlay</h2>
                            <p>Personalizza ogni pannello con anteprima in tempo reale. Le modifiche si salvano automaticamente.</p>
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
                            <div class="subway-vsync-state"><i class="fa-solid fa-circle"></i> VSync Attivo</div>
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

                        <!-- Audio Button Preview -->
                        <div class="subway-hud-widget subway-audio-widget subway-preview-widget" data-preview-widget="audio">
                            <button class="subway-audio-btn" type="button" aria-label="Muto / Attiva audio">
                                <i class="fa-solid fa-volume-high"></i>
                            </button>
                            <div class="subway-audio-slider-wrap">
                                <input type="range" class="subway-audio-slider" min="0" max="1" step="0.01" value="0.8" aria-label="Volume">
                            </div>
                        </div>

                        <!-- Settings Button Preview -->
                        <button class="subway-hud-widget subway-settings-btn-widget subway-preview-widget" data-preview-widget="settings" type="button" aria-label="Impostazioni">
                            <i class="fa-solid fa-gear"></i>
                        </button>
                    </div>

                    <!-- Overlay Tabs Navigation -->
                    <div class="subway-overlay-tabs">
                        <button type="button" class="subway-tab-btn active" data-overlay-tab="timer"><i class="fa-solid fa-stopwatch"></i> Timer</button>
                        <button type="button" class="subway-tab-btn" data-overlay-tab="fps"><i class="fa-solid fa-gauge-high"></i> FPS</button>
                        <button type="button" class="subway-tab-btn" data-overlay-tab="keys"><i class="fa-solid fa-keyboard"></i> Controlli WASD</button>
                        <button type="button" class="subway-tab-btn" data-overlay-tab="audio"><i class="fa-solid fa-volume-high"></i> Audio</button>
                        <button type="button" class="subway-tab-btn" data-overlay-tab="settings"><i class="fa-solid fa-gear"></i> Pulsante Impostazioni</button>
                        <button type="button" class="subway-tab-btn" data-overlay-tab="layout"><i class="fa-solid fa-sliders"></i> Layout Generali</button>
                    </div>

                    <!-- Tab Pane: Timer -->
                    <div class="subway-tab-pane active" data-overlay-pane="timer">
                        <div class="subway-widget-config-card">
                            <div class="subway-widget-card-title"><i class="fa-solid fa-stopwatch"></i> Overlay Timer</div>

                            <details class="subway-settings-group" open>
                                <summary><i class="fa-solid fa-palette"></i> Colori & Sfondo</summary>
                                <div class="subway-settings-group-body">
                                    <div class="subway-theme-grid">
                                        <label class="subway-color-setting"><span>Sfondo</span><input type="color" value="#090d18" data-widget="timer" data-widget-prop="bg"></label>
                                        <label class="subway-color-setting"><span>Testo</span><input type="color" value="#ffffff" data-widget="timer" data-widget-prop="textColor"></label>
                                        <label class="subway-color-setting"><span>Accento</span><input type="color" value="#06b6d4" data-widget="timer" data-widget-prop="accentColor"></label>
                                        <label class="subway-color-setting"><span>Bordo</span><input type="color" value="#06b6d4" data-widget="timer" data-widget-prop="borderColor"></label>
                                    </div>
                                    <label class="subway-opacity-setting">
                                        <span>Opacità sfondo <output data-widget-output>88%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="88" data-widget="timer" data-widget-prop="bgOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Opacità bordo <output data-widget-output>68%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="68" data-widget="timer" data-widget-prop="borderOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Raggio bordi <output data-widget-output>12px</output></span>
                                        <input type="range" min="0" max="100" step="1" value="12" data-widget="timer" data-widget-prop="borderRadius">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Effetto Blur <output data-widget-output>16px</output></span>
                                        <input type="range" min="0" max="60" step="1" value="16" data-widget="timer" data-widget-prop="blur">
                                    </label>
                                </div>
                            </details>

                            <details class="subway-settings-group">
                                <summary><i class="fa-solid fa-text-height"></i> Testo & Dimensioni Timer</summary>
                                <div class="subway-settings-group-body">
                                    <label class="subway-opacity-setting">
                                        <span>Scala / Dimensione Testo <output data-widget-output>33px</output></span>
                                        <input type="range" min="10" max="120" step="1" value="33" data-widget="timer" data-widget-prop="timerFontSize">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Larghezza Testo <output data-widget-output>100%</output></span>
                                        <input type="range" min="10" max="400" step="1" value="100" data-widget="timer" data-widget-prop="timerTextScaleX">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Altezza Testo <output data-widget-output>100%</output></span>
                                        <input type="range" min="10" max="400" step="1" value="100" data-widget="timer" data-widget-prop="timerTextScaleY">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Posizione Testo X <output data-widget-output>0px</output></span>
                                        <input type="range" min="-200" max="200" step="1" value="0" data-widget="timer" data-widget-prop="timerTextPosX">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Posizione Testo Y <output data-widget-output>0px</output></span>
                                        <input type="range" min="-200" max="200" step="1" value="0" data-widget="timer" data-widget-prop="timerTextPosY">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Larghezza Timer <output data-widget-output>250px</output></span>
                                        <input type="range" min="50" max="800" step="1" value="250" data-widget="timer" data-widget-prop="timerWidth">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Altezza Timer <output data-widget-output>90px</output></span>
                                        <input type="range" min="30" max="400" step="1" value="90" data-widget="timer" data-widget-prop="timerHeight">
                                    </label>
                                </div>
                            </details>

                            <details class="subway-settings-group">
                                <summary><i class="fa-solid fa-moon"></i> Ombra Testo</summary>
                                <div class="subway-settings-group-body">
                                    <div class="subway-theme-grid">
                                        <label class="subway-color-setting"><span>Colore Ombra</span><input type="color" value="#000000" data-widget="timer" data-widget-prop="shadowColor"></label>
                                    </div>
                                    <label class="subway-opacity-setting">
                                        <span>Opacità Ombra <output data-widget-output>50%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="50" data-widget="timer" data-widget-prop="shadowOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Sfumatura Blur <output data-widget-output>8px</output></span>
                                        <input type="range" min="0" max="100" step="1" value="8" data-widget="timer" data-widget-prop="shadowBlur">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Spostamento X <output data-widget-output>0px</output></span>
                                        <input type="range" min="-100" max="100" step="1" value="0" data-widget="timer" data-widget-prop="shadowX">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Spostamento Y <output data-widget-output>2px</output></span>
                                        <input type="range" min="-100" max="100" step="1" value="2" data-widget="timer" data-widget-prop="shadowY">
                                    </label>
                                </div>
                            </details>

                            <details class="subway-settings-group">
                                <summary><i class="fa-solid fa-image"></i> Immagine Banner</summary>
                                <div class="subway-settings-group-body">
                                    <div class="subway-img-input-row">
                                        <input type="text" placeholder="URL immagine (es. https://...)" data-widget="timer" data-widget-prop="bgImage">
                                        <label class="subway-img-btn">
                                            <i class="fa-solid fa-upload"></i> Carica...
                                            <input type="file" accept="image/*" data-timer-bg-file style="display:none;">
                                        </label>
                                        <button type="button" class="subway-img-btn subway-img-btn-remove" data-timer-bg-remove title="Rimuovi immagine">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                    <div class="subway-img-preview-thumb" data-timer-bg-preview>Nessuna immagine impostata</div>
                                    <div style="display: flex; gap: 0.5rem; margin-top: 0.6rem; align-items: center;">
                                        <span style="font-size: 0.78rem; color: #fff; font-weight: 700;">Modalità Taglio:</span>
                                        <select data-widget="timer" data-widget-prop="bgFit" style="flex: 1; height: 34px; border-radius: 8px; background: rgba(0,0,0,0.35); color: #fff; border: 1px solid rgba(255,255,255,0.15); padding: 0 0.5rem; font-size: 0.78rem; outline: none;">
                                            <option value="cover">Copri (Riempie tutto)</option>
                                            <option value="contain">Contieni (Foto intera)</option>
                                            <option value="custom">Personalizzato</option>
                                        </select>
                                    </div>
                                    <label class="subway-opacity-setting" style="margin-top: 0.5rem;">
                                        <span>Opacità Banner <output data-widget-output>100%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="100" data-widget="timer" data-widget-prop="bgImageOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Scala / Zoom <output data-widget-output>100%</output></span>
                                        <input type="range" min="1" max="1000" step="1" value="100" data-widget="timer" data-widget-prop="bgImageScale">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Spostamento X <output data-widget-output>0px</output></span>
                                        <input type="range" min="-2000" max="2000" step="1" value="0" data-widget="timer" data-widget-prop="bgImagePosX">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Spostamento Y <output data-widget-output>0px</output></span>
                                        <input type="range" min="-2000" max="2000" step="1" value="0" data-widget="timer" data-widget-prop="bgImagePosY">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Rotazione <output data-widget-output>0°</output></span>
                                        <input type="range" min="0" max="360" step="1" value="0" data-widget="timer" data-widget-prop="bgImageRotate">
                                    </label>
                                </div>
                            </details>
                        </div>
                    </div>

                    <!-- Tab Pane: FPS -->
                    <div class="subway-tab-pane" data-overlay-pane="fps">
                        <div class="subway-widget-config-card">
                            <div class="subway-widget-card-title"><i class="fa-solid fa-gauge-high"></i> Overlay FPS / VSync</div>
                            <div class="subway-theme-grid">
                                <label class="subway-color-setting"><span>Sfondo</span><input type="color" value="#090d18" data-widget="fps" data-widget-prop="bg"></label>
                                <label class="subway-color-setting"><span>Testo</span><input type="color" value="#ffffff" data-widget="fps" data-widget-prop="textColor"></label>
                                <label class="subway-color-setting"><span>Bordo</span><input type="color" value="#06b6d4" data-widget="fps" data-widget-prop="borderColor"></label>
                            </div>
                            <label class="subway-opacity-setting">
                                <span>Opacità sfondo <output data-widget-output>88%</output></span>
                                <input type="range" min="0" max="100" step="1" value="88" data-widget="fps" data-widget-prop="bgOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Opacità bordo <output data-widget-output>68%</output></span>
                                <input type="range" min="0" max="100" step="1" value="68" data-widget="fps" data-widget-prop="borderOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Raggio bordi <output data-widget-output>12px</output></span>
                                <input type="range" min="0" max="100" step="1" value="12" data-widget="fps" data-widget-prop="borderRadius">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Effetto Blur <output data-widget-output>16px</output></span>
                                <input type="range" min="0" max="60" step="1" value="16" data-widget="fps" data-widget-prop="blur">
                            </label>

                            <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Ombra Testo</h5>
                                <div class="subway-theme-grid">
                                    <label class="subway-color-setting"><span>Colore Ombra</span><input type="color" value="#000000" data-widget="fps" data-widget-prop="shadowColor"></label>
                                </div>
                                <label class="subway-opacity-setting">
                                    <span>Opacità Ombra <output data-widget-output>50%</output></span>
                                    <input type="range" min="0" max="100" step="1" value="50" data-widget="fps" data-widget-prop="shadowOpacity">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Sfumatura Blur <output data-widget-output>8px</output></span>
                                    <input type="range" min="0" max="100" step="1" value="8" data-widget="fps" data-widget-prop="shadowBlur">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Spostamento Orizzontale (X) <output data-widget-output>0px</output></span>
                                    <input type="range" min="-100" max="100" step="1" value="0" data-widget="fps" data-widget-prop="shadowX">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Spostamento Verticale (Y) <output data-widget-output>2px</output></span>
                                    <input type="range" min="-100" max="100" step="1" value="2" data-widget="fps" data-widget-prop="shadowY">
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Pane: WASD Keys -->
                    <div class="subway-tab-pane" data-overlay-pane="keys">
                        <div class="subway-widget-config-card">
                            <div class="subway-widget-card-title"><i class="fa-solid fa-keyboard"></i> Overlay Controlli (WASD)</div>
                            <div class="subway-theme-grid">
                                <label class="subway-color-setting"><span>Sfondo Box</span><input type="color" value="#090d18" data-widget="keys" data-widget-prop="bg"></label>
                                <label class="subway-color-setting"><span>Testo Box</span><input type="color" value="#ffffff" data-widget="keys" data-widget-prop="textColor"></label>
                                <label class="subway-color-setting"><span>Bordo Box</span><input type="color" value="#06b6d4" data-widget="keys" data-widget-prop="borderColor"></label>
                            </div>
                            <label class="subway-opacity-setting">
                                <span>Opacità sfondo box <output data-widget-output>88%</output></span>
                                <input type="range" min="0" max="100" step="1" value="88" data-widget="keys" data-widget-prop="bgOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Opacità bordo box <output data-widget-output>68%</output></span>
                                <input type="range" min="0" max="100" step="1" value="68" data-widget="keys" data-widget-prop="borderOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Raggio bordi box <output data-widget-output>12px</output></span>
                                <input type="range" min="0" max="100" step="1" value="12" data-widget="keys" data-widget-prop="borderRadius">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Effetto Blur box <output data-widget-output>16px</output></span>
                                <input type="range" min="0" max="60" step="1" value="16" data-widget="keys" data-widget-prop="blur">
                            </label>

                            <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Ombra Testo</h5>
                                <div class="subway-theme-grid">
                                    <label class="subway-color-setting"><span>Colore Ombra</span><input type="color" value="#000000" data-widget="keys" data-widget-prop="shadowColor"></label>
                                </div>
                                <label class="subway-opacity-setting">
                                    <span>Opacità Ombra <output data-widget-output>50%</output></span>
                                    <input type="range" min="0" max="100" step="1" value="50" data-widget="keys" data-widget-prop="shadowOpacity">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Sfumatura Blur <output data-widget-output>8px</output></span>
                                    <input type="range" min="0" max="100" step="1" value="8" data-widget="keys" data-widget-prop="shadowBlur">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Spostamento Orizzontale (X) <output data-widget-output>0px</output></span>
                                    <input type="range" min="-100" max="100" step="1" value="0" data-widget="keys" data-widget-prop="shadowX">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Spostamento Verticale (Y) <output data-widget-output>2px</output></span>
                                    <input type="range" min="-100" max="100" step="1" value="2" data-widget="keys" data-widget-prop="shadowY">
                                </label>
                            </div>

                            <div style="margin-top: 1.2rem; padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.08);">
                                <h5 style="margin: 0 0 0.8rem 0; color: #fff; font-size: 0.85rem;"><i class="fa-solid fa-square-full"></i> Stile Tasti WASD Interni</h5>
                                <div class="subway-theme-grid">
                                    <label class="subway-color-setting"><span>Sfondo Tasti</span><input type="color" value="#ffffff" data-widget="keys" data-widget-prop="keyBg"></label>
                                    <label class="subway-color-setting"><span>Hover / Attivo</span><input type="color" value="#06b6d4" data-widget="keys" data-widget-prop="keyHover"></label>
                                    <label class="subway-color-setting"><span>Testo Tasti</span><input type="color" value="#ffffff" data-widget="keys" data-widget-prop="keyText"></label>
                                    <label class="subway-color-setting"><span>Bordo Tasti</span><input type="color" value="#06b6d4" data-widget="keys" data-widget-prop="keyBorderColor"></label>
                                </div>
                                <label class="subway-opacity-setting">
                                    <span>Opacità sfondo tasti <output data-widget-output>7%</output></span>
                                    <input type="range" min="0" max="100" step="1" value="7" data-widget="keys" data-widget-prop="keyBgOpacity">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Opacità bordo tasti <output data-widget-output>40%</output></span>
                                    <input type="range" min="0" max="100" step="1" value="40" data-widget="keys" data-widget-prop="keyBorderOpacity">
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Pane: Audio / Volume -->
                    <div class="subway-tab-pane" data-overlay-pane="audio">
                        <div class="subway-widget-config-card">
                            <div class="subway-widget-card-title"><i class="fa-solid fa-volume-high"></i> Overlay Audio / Volume</div>

                            <details class="subway-settings-group" open>
                                <summary><i class="fa-solid fa-palette"></i> Colori & Sfondo</summary>
                                <div class="subway-settings-group-body">
                                    <div class="subway-theme-grid">
                                        <label class="subway-color-setting"><span>Sfondo</span><input type="color" value="#090d18" data-widget="audio" data-widget-prop="bg"></label>
                                        <label class="subway-color-setting"><span>Icona</span><input type="color" value="#ffffff" data-widget="audio" data-widget-prop="textColor"></label>
                                        <label class="subway-color-setting"><span>Accento</span><input type="color" value="#06b6d4" data-widget="audio" data-widget-prop="accentColor"></label>
                                        <label class="subway-color-setting"><span>Bordo</span><input type="color" value="#06b6d4" data-widget="audio" data-widget-prop="borderColor"></label>
                                    </div>
                                    <label class="subway-opacity-setting">
                                        <span>Opacità sfondo <output data-widget-output>88%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="88" data-widget="audio" data-widget-prop="bgOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Opacità bordo <output data-widget-output>68%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="68" data-widget="audio" data-widget-prop="borderOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Raggio bordi <output data-widget-output>12px</output></span>
                                        <input type="range" min="0" max="100" step="1" value="12" data-widget="audio" data-widget-prop="borderRadius">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Effetto Blur <output data-widget-output>16px</output></span>
                                        <input type="range" min="0" max="60" step="1" value="16" data-widget="audio" data-widget-prop="blur">
                                    </label>
                                </div>
                            </details>

                            <details class="subway-settings-group">
                                <summary><i class="fa-solid fa-moon"></i> Ombra</summary>
                                <div class="subway-settings-group-body">
                                    <div class="subway-theme-grid">
                                        <label class="subway-color-setting"><span>Colore Ombra</span><input type="color" value="#000000" data-widget="audio" data-widget-prop="shadowColor"></label>
                                    </div>
                                    <label class="subway-opacity-setting">
                                        <span>Opacità Ombra <output data-widget-output>50%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="50" data-widget="audio" data-widget-prop="shadowOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Sfumatura Blur <output data-widget-output>8px</output></span>
                                        <input type="range" min="0" max="100" step="1" value="8" data-widget="audio" data-widget-prop="shadowBlur">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Spostamento Orizzontale (X) <output data-widget-output>0px</output></span>
                                        <input type="range" min="-100" max="100" step="1" value="0" data-widget="audio" data-widget-prop="shadowX">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Spostamento Verticale (Y) <output data-widget-output>2px</output></span>
                                        <input type="range" min="-100" max="100" step="1" value="2" data-widget="audio" data-widget-prop="shadowY">
                                    </label>
                                </div>
                            </details>
                        </div>
                    </div>

                    <!-- Tab Pane: Settings Button -->
                    <div class="subway-tab-pane" data-overlay-pane="settings">
                        <div class="subway-widget-config-card">
                            <div class="subway-widget-card-title"><i class="fa-solid fa-gear"></i> Pulsante Impostazioni Overlay</div>
                            <div class="subway-theme-grid">
                                <label class="subway-color-setting"><span>Sfondo</span><input type="color" value="#090d18" data-widget="settings" data-widget-prop="bg"></label>
                                <label class="subway-color-setting"><span>Icona</span><input type="color" value="#ffffff" data-widget="settings" data-widget-prop="textColor"></label>
                                <label class="subway-color-setting"><span>Bordo</span><input type="color" value="#06b6d4" data-widget="settings" data-widget-prop="borderColor"></label>
                            </div>
                            <label class="subway-opacity-setting">
                                <span>Opacità sfondo <output data-widget-output>88%</output></span>
                                <input type="range" min="0" max="100" step="1" value="88" data-widget="settings" data-widget-prop="bgOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Opacità bordo <output data-widget-output>68%</output></span>
                                <input type="range" min="0" max="100" step="1" value="68" data-widget="settings" data-widget-prop="borderOpacity">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Raggio bordi <output data-widget-output>12px</output></span>
                                <input type="range" min="0" max="100" step="1" value="12" data-widget="settings" data-widget-prop="borderRadius">
                            </label>
                            <label class="subway-opacity-setting">
                                <span>Effetto Blur <output data-widget-output>16px</output></span>
                                <input type="range" min="0" max="60" step="1" value="16" data-widget="settings" data-widget-prop="blur">
                            </label>

                            <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Ombra Icona</h5>
                                <div class="subway-theme-grid">
                                    <label class="subway-color-setting"><span>Colore Ombra</span><input type="color" value="#000000" data-widget="settings" data-widget-prop="shadowColor"></label>
                                </div>
                                <label class="subway-opacity-setting">
                                    <span>Opacità Ombra <output data-widget-output>50%</output></span>
                                    <input type="range" min="0" max="100" step="1" value="50" data-widget="settings" data-widget-prop="shadowOpacity">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Sfumatura Blur <output data-widget-output>8px</output></span>
                                    <input type="range" min="0" max="100" step="1" value="8" data-widget="settings" data-widget-prop="shadowBlur">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Spostamento Orizzontale (X) <output data-widget-output>0px</output></span>
                                    <input type="range" min="-100" max="100" step="1" value="0" data-widget="settings" data-widget-prop="shadowX">
                                </label>
                                <label class="subway-opacity-setting">
                                    <span>Spostamento Verticale (Y) <output data-widget-output>2px</output></span>
                                    <input type="range" min="-100" max="100" step="1" value="2" data-widget="settings" data-widget-prop="shadowY">
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Pane: Layout -->
                    <div class="subway-tab-pane" data-overlay-pane="layout">
                        <div class="subway-widget-config-card">
                            <div class="subway-widget-card-title"><i class="fa-solid fa-sliders"></i> Layout Generale</div>
                            <div class="subway-option-row">
                                <div class="subway-option-info">
                                    <strong>Mostra riga Boost (B)</strong>
                                    <span>Mostra il tasto B per il boost sotto il WASD.</span>
                                </div>
                                <label class="subway-switch">
                                    <input type="checkbox" data-overlay-toggle="showBoost">
                                    <span class="subway-slider"></span>
                                </label>
                            </div>
                            <div class="subway-option-row">
                                <div class="subway-option-info">
                                    <strong>Mostra intestazioni overlay</strong>
                                    <span>Mostra la barra di trascinamento e i titoli degli overlay.</span>
                                </div>
                                <label class="subway-switch">
                                    <input type="checkbox" data-overlay-toggle="showHeaders" checked>
                                    <span class="subway-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="subway-theme-reset" data-reset-overlay-theme style="margin-top: 1.2rem;">Ripristina impostazioni predefinite</button>
                </section>
            </div>

            <!-- Map Selection Grid -->
            <section class="game-panel game-reveal" style="margin-top: 2rem;">
                <div class="game-panel-head">
                    <div>
                        <h2>Seleziona la Mappa</h2>
                    </div>
                    <button type="button" class="game-btn game-btn-soft subway-map-settings-btn" id="openSubwaySettings">
                        <i class="fa-solid fa-sliders"></i>
                        <span>Impostazioni</span>
                    </button>
                </div>

                <div class="subway-grid" hidden>

                    <!-- builds-1 maps -->
                    <div class="subway-map-card" data-map="zurich">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag">Europa</span>
                            <h3>Zurich</h3>
                            <p>Corri tra gli splendidi scenari alpini e i binari svizzeri.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="beijing">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag">Asia</span>
                            <h3>Beijing</h3>
                            <p>Sfreccia tra i templi storici e le lanterne cinesi.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="cairo">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag">Africa</span>
                            <h3>Cairo</h3>
                            <p>Esplora i misteri dell'Antico Egitto tra le sabbie del deserto.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="paris">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag">Europa</span>
                            <h3>Paris</h3>
                            <p>La città delle luci con i suoi incantevoli boulevard.</p>
                        </div>
                    </div>

                    <!-- builds-2 maps -->
                    <div class="subway-map-card" data-map="tokyo">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-2">Asia</span>
                            <h3>Tokyo</h3>
                            <p>Fuggi sotto le luci dei neon di Shibuya e i ciliegi in fiore.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="london">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-2">Europa</span>
                            <h3>London</h3>
                            <p>Corri accanto all'iconico Big Ben e la metropolitana londinese.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="mexico">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-2">America</span>
                            <h3>Mexico</h3>
                            <p>Colori caldi e festival vivaci lungo i binari messicani.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="newyork">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-2">America</span>
                            <h3>New York</h3>
                            <p>Evita i treni sfrecciando nei tunnel sotterranei della Grande Mela.</p>
                        </div>
                    </div>

                    <!-- builds-3 maps -->
                    <div class="subway-map-card" data-map="bangkok">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-3">Asia</span>
                            <h3>Bangkok</h3>
                            <p>Corri tra canali d'acqua densi e antiche statue dorate.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="berlin">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-3">Europa</span>
                            <h3>Berlin</h3>
                            <p>Muri storici e la maestosità della Porta di Brandeburgo.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="buenosaires">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-3">America</span>
                            <h3>Buenos Aires</h3>
                            <p>Immergiti nell'architettura maestosa e colorata dell'Argentina.</p>
                        </div>
                    </div>

                    <div class="subway-map-card" data-map="moscow">
                        <div class="subway-map-bg" style="background-image: url('https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png');"></div>
                        <div class="subway-map-content">
                            <span class="subway-map-tag tier-3">Europa</span>
                            <h3>Moscow</h3>
                            <p>Sfreccia tra le cupole dorate del Cremlino e le stazioni della metro.</p>
                        </div>
                    </div>

                </div>
            </section>

            <!-- No-Coin Challenge Leaderboard -->
            <section class="game-panel game-reveal subway-leaderboard-panel" style="margin-top: 2rem;">
                <div class="game-panel-head">
                    <div class="subway-lb-header-info">
                        <h2><i class="fa-solid fa-trophy" style="color: #f59e0b; margin-right: 0.5rem;"></i> Classifica No-Coin Challenge</h2>
                        <p>I record mondiali di sopravvivenza nella No-Coin Challenge. Schiva le monete e scala la vetta!</p>
                    </div>
                    <button type="button" class="game-btn game-btn-soft" id="subwayLeaderboardRefresh" aria-label="Aggiorna Classifica">
                        <i class="fa-solid fa-rotate"></i>
                        <span>Aggiorna</span>
                    </button>
                </div>

                <div class="subway-leaderboard-wrapper">
                    <div class="subway-leaderboard-empty" id="subwayLeaderboardEmpty" hidden>
                        <i class="fa-solid fa-stopwatch" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.8rem;"></i>
                        <p>Nessun record ancora registrato. Gioca una partita per posizionarti primo in classifica!</p>
                    </div>
                    <div class="subway-leaderboard-table-responsive" id="subwayLeaderboardTable">
                        <table class="subway-lb-table">
                            <thead>
                                <tr>
                                    <th class="th-pos">#</th>
                                    <th class="th-user">Giocatore</th>
                                    <th class="th-time">Miglior Tempo</th>
                                    <th class="th-map">Mappa</th>
                                </tr>
                            </thead>
                            <tbody id="subwayLeaderboardBody">
                                <!-- Caricato dinamicamente via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <!-- STATE 2: The Game Screen Arena with draggable overlays -->
        <div id="subwayGameArea" style="display: none;">
            <div class="subway-arena-wrapper">

                <!-- Game Exit button -->
                <button class="game-btn game-btn-special" id="exitGameBtn" style="position: absolute; top: 15px; left: 15px; z-index: 9999; box-shadow: 0 8px 24px rgba(0,0,0,0.5);">
                    <i class="fa-solid fa-arrow-left"></i> Torna alla selezione
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

                <!-- Floating Audio / Volume Widget (Draggable) -->
                <div class="subway-hud-widget subway-audio-widget" id="hudWidgetAudio">
                    <button class="subway-audio-btn" type="button" aria-label="Muto / Attiva audio">
                        <i class="fa-solid fa-volume-high"></i>
                    </button>
                    <div class="subway-audio-slider-wrap">
                        <input type="range" class="subway-audio-slider" min="0" max="1" step="0.01" value="0.8" aria-label="Volume">
                    </div>
                </div>

                <!-- Floating Setup button (Draggable button) -->
                <button class="subway-hud-widget subway-settings-btn-widget" id="hudWidgetSettingsBtn" type="button" aria-label="Apri le impostazioni di gioco">
                    <i class="fa-solid fa-gear"></i>
                </button>

                <!-- The WebGL Canvas host -->
                <div class="subway-game-container" id="subwayGameContainer">
                    <!-- Canvas will be dynamically injected here -->
                </div>

                <!-- Controls Config Modal inside game frame -->
                <div class="subway-settings-modal" id="subwaySettingsModal">
                    <div class="subway-modal-box">
                        <h3 style="margin-top: 0; color: #fff;"><i class="fa-solid fa-gear"></i> Impostazioni di gioco</h3>
                        <p style="font-size: 0.8rem; color: var(--game-muted); margin-bottom: 1.2rem;">Tasti, protezione hoverboard e aspetto degli overlay vengono salvati automaticamente.</p>

                        <div class="subway-modal-section subway-modal-section-first">
                            <div class="subway-option-row">
                                <div class="subway-option-info">
                                    <strong>No-Coin Challenge</strong>
                                    <span>Avvia il timer con la run e lo ferma alla prima moneta.</span>
                                </div>
                                <label class="subway-switch">
                                    <input type="checkbox" id="toggleNoCoinChallenge" checked>
                                    <span class="subway-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="subway-keybind-list">
                            <div class="subway-keybind-item">
                                <label>Salta (Jump)</label>
                                <button class="subway-key-btn" data-keybind="jump">W</button>
                            </div>
                            <div class="subway-keybind-item">
                                <label>Scivola (Roll)</label>
                                <button class="subway-key-btn" data-keybind="duck">S</button>
                            </div>
                            <div class="subway-keybind-item">
                                <label>Sposta Sinistra</label>
                                <button class="subway-key-btn" data-keybind="left">A</button>
                            </div>
                            <div class="subway-keybind-item">
                                <label>Sposta Destra</label>
                                <button class="subway-key-btn" data-keybind="right">D</button>
                            </div>
                            <div class="subway-keybind-item">
                                <label>Boost rosso iniziale</label>
                                <button class="subway-key-btn" data-keybind="boost">B</button>
                            </div>
                        </div>

                        <div class="subway-modal-section">
                            <div class="subway-option-row">
                                <div class="subway-option-info">
                                    <strong>Blocca SPAZIO</strong>
                                    <span>Evita l'attivazione involontaria dell'hoverboard.</span>
                                </div>
                                <label class="subway-switch">
                                    <input type="checkbox" data-setting="blockSpace">
                                    <span class="subway-slider"></span>
                                </label>
                            </div>
                            <div class="subway-option-row">
                                <div class="subway-option-info">
                                    <strong>Auto Boost (3x)</strong>
                                    <span>Attiva automaticamente 3 volte il boost rosso all'avvio.</span>
                                </div>
                                <label class="subway-switch">
                                    <input type="checkbox" data-setting="autoBoost">
                                    <span class="subway-slider"></span>
                                </label>
                            </div>
                            <div class="subway-option-row subway-modal-timing-row">
                                <div class="subway-option-info">
                                    <strong>VSync adattivo</strong>
                                    <span>Segue automaticamente gli Hz del monitor.</span>
                                </div>
                                <label class="subway-switch">
                                    <input type="checkbox" data-setting="vsync" checked>
                                    <span class="subway-slider"></span>
                                </label>
                            </div>
                            <label class="subway-fps-limit-setting" data-fps-control>
                                <span><strong>Limite FPS</strong><small>Disponibile con VSync disattivato.</small></span>
                                <input type="number" min="30" max="500" step="1" value="144" data-fps-limit>
                            </label>
                        </div>

                        <div class="subway-modal-section">
                            <h4>Aspetto Overlay</h4>
                            
                            <!-- Modal Overlay Tabs Navigation -->
                            <div class="subway-overlay-tabs">
                                <button type="button" class="subway-tab-btn active" data-overlay-tab="timer"><i class="fa-solid fa-stopwatch"></i> Timer</button>
                                <button type="button" class="subway-tab-btn" data-overlay-tab="fps"><i class="fa-solid fa-gauge-high"></i> FPS</button>
                                <button type="button" class="subway-tab-btn" data-overlay-tab="keys"><i class="fa-solid fa-keyboard"></i> Controlli WASD</button>
                                <button type="button" class="subway-tab-btn" data-overlay-tab="audio"><i class="fa-solid fa-volume-high"></i> Audio</button>
                                <button type="button" class="subway-tab-btn" data-overlay-tab="settings"><i class="fa-solid fa-gear"></i> Impostazioni</button>
                                <button type="button" class="subway-tab-btn" data-overlay-tab="layout"><i class="fa-solid fa-sliders"></i> Layout</button>
                            </div>

                            <!-- Modal Tab Pane: Timer -->
                            <div class="subway-tab-pane active" data-overlay-pane="timer">
                                <div class="subway-widget-config-card">
                                    <div class="subway-widget-card-title"><i class="fa-solid fa-stopwatch"></i> Overlay Timer</div>

                                    <details class="subway-settings-group" open>
                                        <summary><i class="fa-solid fa-palette"></i> Colori & Sfondo</summary>
                                        <div class="subway-settings-group-body">
                                            <div class="subway-theme-grid">
                                                <label class="subway-color-setting"><span>Sfondo</span><input type="color" value="#090d18" data-widget="timer" data-widget-prop="bg"></label>
                                                <label class="subway-color-setting"><span>Testo</span><input type="color" value="#ffffff" data-widget="timer" data-widget-prop="textColor"></label>
                                                <label class="subway-color-setting"><span>Accento</span><input type="color" value="#06b6d4" data-widget="timer" data-widget-prop="accentColor"></label>
                                                <label class="subway-color-setting"><span>Bordo</span><input type="color" value="#06b6d4" data-widget="timer" data-widget-prop="borderColor"></label>
                                            </div>
                                            <label class="subway-opacity-setting">
                                                <span>Opacità sfondo <output data-widget-output>88%</output></span>
                                                <input type="range" min="0" max="100" step="1" value="88" data-widget="timer" data-widget-prop="bgOpacity">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Opacità bordo <output data-widget-output>68%</output></span>
                                                <input type="range" min="0" max="100" step="1" value="68" data-widget="timer" data-widget-prop="borderOpacity">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Raggio bordi <output data-widget-output>12px</output></span>
                                                <input type="range" min="0" max="100" step="1" value="12" data-widget="timer" data-widget-prop="borderRadius">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Effetto Blur <output data-widget-output>16px</output></span>
                                                <input type="range" min="0" max="60" step="1" value="16" data-widget="timer" data-widget-prop="blur">
                                            </label>
                                        </div>
                                    </details>

                                    <details class="subway-settings-group">
                                        <summary><i class="fa-solid fa-text-height"></i> Testo & Dimensioni Timer</summary>
                                        <div class="subway-settings-group-body">
                                            <label class="subway-opacity-setting">
                                                <span>Scala / Dimensione Testo <output data-widget-output>33px</output></span>
                                                <input type="range" min="10" max="120" step="1" value="33" data-widget="timer" data-widget-prop="timerFontSize">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Larghezza Testo <output data-widget-output>100%</output></span>
                                                <input type="range" min="10" max="400" step="1" value="100" data-widget="timer" data-widget-prop="timerTextScaleX">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Altezza Testo <output data-widget-output>100%</output></span>
                                                <input type="range" min="10" max="400" step="1" value="100" data-widget="timer" data-widget-prop="timerTextScaleY">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Posizione Testo X <output data-widget-output>0px</output></span>
                                                <input type="range" min="-200" max="200" step="1" value="0" data-widget="timer" data-widget-prop="timerTextPosX">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Posizione Testo Y <output data-widget-output>0px</output></span>
                                                <input type="range" min="-200" max="200" step="1" value="0" data-widget="timer" data-widget-prop="timerTextPosY">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Larghezza Timer <output data-widget-output>250px</output></span>
                                                <input type="range" min="50" max="800" step="1" value="250" data-widget="timer" data-widget-prop="timerWidth">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Altezza Timer <output data-widget-output>90px</output></span>
                                                <input type="range" min="30" max="400" step="1" value="90" data-widget="timer" data-widget-prop="timerHeight">
                                            </label>
                                        </div>
                                    </details>

                                    <details class="subway-settings-group">
                                        <summary><i class="fa-solid fa-moon"></i> Ombra Testo</summary>
                                        <div class="subway-settings-group-body">
                                            <div class="subway-theme-grid">
                                                <label class="subway-color-setting"><span>Colore Ombra</span><input type="color" value="#000000" data-widget="timer" data-widget-prop="shadowColor"></label>
                                            </div>
                                            <label class="subway-opacity-setting">
                                                <span>Opacità Ombra <output data-widget-output>50%</output></span>
                                                <input type="range" min="0" max="100" step="1" value="50" data-widget="timer" data-widget-prop="shadowOpacity">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Sfumatura Blur <output data-widget-output>8px</output></span>
                                                <input type="range" min="0" max="100" step="1" value="8" data-widget="timer" data-widget-prop="shadowBlur">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Spostamento X <output data-widget-output>0px</output></span>
                                                <input type="range" min="-100" max="100" step="1" value="0" data-widget="timer" data-widget-prop="shadowX">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Spostamento Y <output data-widget-output>2px</output></span>
                                                <input type="range" min="-100" max="100" step="1" value="2" data-widget="timer" data-widget-prop="shadowY">
                                            </label>
                                        </div>
                                    </details>

                                    <details class="subway-settings-group">
                                        <summary><i class="fa-solid fa-image"></i> Immagine Banner</summary>
                                        <div class="subway-settings-group-body">
                                            <div class="subway-img-input-row">
                                                <input type="text" placeholder="URL immagine (es. https://...)" data-widget="timer" data-widget-prop="bgImage">
                                                <label class="subway-img-btn">
                                                    <i class="fa-solid fa-upload"></i> Carica...
                                                    <input type="file" accept="image/*" data-timer-bg-file style="display:none;">
                                                </label>
                                                <button type="button" class="subway-img-btn subway-img-btn-remove" data-timer-bg-remove title="Rimuovi immagine">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                            <div class="subway-img-preview-thumb" data-timer-bg-preview>Nessuna immagine impostata</div>
                                            <div style="display: flex; gap: 0.5rem; margin-top: 0.6rem; align-items: center;">
                                                <span style="font-size: 0.78rem; color: #fff; font-weight: 700;">Modalità Taglio:</span>
                                                <select data-widget="timer" data-widget-prop="bgFit" style="flex: 1; height: 34px; border-radius: 8px; background: rgba(0,0,0,0.35); color: #fff; border: 1px solid rgba(255,255,255,0.15); padding: 0 0.5rem; font-size: 0.78rem; outline: none;">
                                                    <option value="cover">Copri (Riempie tutto)</option>
                                                    <option value="contain">Contieni (Foto intera)</option>
                                                    <option value="custom">Personalizzato</option>
                                                </select>
                                            </div>
                                            <label class="subway-opacity-setting" style="margin-top: 0.5rem;">
                                                <span>Opacità Banner <output data-widget-output>100%</output></span>
                                                <input type="range" min="0" max="100" step="1" value="100" data-widget="timer" data-widget-prop="bgImageOpacity">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Scala / Zoom <output data-widget-output>100%</output></span>
                                                <input type="range" min="1" max="1000" step="1" value="100" data-widget="timer" data-widget-prop="bgImageScale">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Spostamento X <output data-widget-output>0px</output></span>
                                                <input type="range" min="-2000" max="2000" step="1" value="0" data-widget="timer" data-widget-prop="bgImagePosX">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Spostamento Y <output data-widget-output>0px</output></span>
                                                <input type="range" min="-2000" max="2000" step="1" value="0" data-widget="timer" data-widget-prop="bgImagePosY">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Rotazione <output data-widget-output>0°</output></span>
                                                <input type="range" min="0" max="360" step="1" value="0" data-widget="timer" data-widget-prop="bgImageRotate">
                                            </label>
                                        </div>
                                    </details>
                                </div>
                            </div>

                            <!-- Modal Tab Pane: FPS -->
                            <div class="subway-tab-pane" data-overlay-pane="fps">
                                <div class="subway-widget-config-card">
                                    <div class="subway-widget-card-title"><i class="fa-solid fa-gauge-high"></i> Overlay FPS / VSync</div>
                                    <div class="subway-theme-grid">
                                        <label class="subway-color-setting"><span>Sfondo</span><input type="color" value="#090d18" data-widget="fps" data-widget-prop="bg"></label>
                                        <label class="subway-color-setting"><span>Testo</span><input type="color" value="#ffffff" data-widget="fps" data-widget-prop="textColor"></label>
                                        <label class="subway-color-setting"><span>Bordo</span><input type="color" value="#06b6d4" data-widget="fps" data-widget-prop="borderColor"></label>
                                    </div>
                                    <label class="subway-opacity-setting">
                                        <span>Opacità sfondo <output data-widget-output>88%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="88" data-widget="fps" data-widget-prop="bgOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Opacità bordo <output data-widget-output>68%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="68" data-widget="fps" data-widget-prop="borderOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Raggio bordi <output data-widget-output>12px</output></span>
                                        <input type="range" min="0" max="100" step="1" value="12" data-widget="fps" data-widget-prop="borderRadius">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Effetto Blur <output data-widget-output>16px</output></span>
                                        <input type="range" min="0" max="60" step="1" value="16" data-widget="fps" data-widget-prop="blur">
                                    </label>

                                    <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                        <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Ombra Testo</h5>
                                        <div class="subway-theme-grid">
                                            <label class="subway-color-setting"><span>Colore Ombra</span><input type="color" value="#000000" data-widget="fps" data-widget-prop="shadowColor"></label>
                                        </div>
                                        <label class="subway-opacity-setting">
                                            <span>Opacità Ombra <output data-widget-output>50%</output></span>
                                            <input type="range" min="0" max="100" step="1" value="50" data-widget="fps" data-widget-prop="shadowOpacity">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Sfumatura Blur <output data-widget-output>8px</output></span>
                                            <input type="range" min="0" max="100" step="1" value="8" data-widget="fps" data-widget-prop="shadowBlur">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Spostamento Orizzontale (X) <output data-widget-output>0px</output></span>
                                            <input type="range" min="-100" max="100" step="1" value="0" data-widget="fps" data-widget-prop="shadowX">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Spostamento Verticale (Y) <output data-widget-output>2px</output></span>
                                            <input type="range" min="-100" max="100" step="1" value="2" data-widget="fps" data-widget-prop="shadowY">
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Tab Pane: WASD Keys -->
                            <div class="subway-tab-pane" data-overlay-pane="keys">
                                <div class="subway-widget-config-card">
                                    <div class="subway-widget-card-title"><i class="fa-solid fa-keyboard"></i> Overlay Controlli (WASD)</div>
                                    <div class="subway-theme-grid">
                                        <label class="subway-color-setting"><span>Sfondo Box</span><input type="color" value="#090d18" data-widget="keys" data-widget-prop="bg"></label>
                                        <label class="subway-color-setting"><span>Testo Box</span><input type="color" value="#ffffff" data-widget="keys" data-widget-prop="textColor"></label>
                                        <label class="subway-color-setting"><span>Bordo Box</span><input type="color" value="#06b6d4" data-widget="keys" data-widget-prop="borderColor"></label>
                                    </div>
                                    <label class="subway-opacity-setting">
                                        <span>Opacità sfondo box <output data-widget-output>88%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="88" data-widget="keys" data-widget-prop="bgOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Opacità bordo box <output data-widget-output>68%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="68" data-widget="keys" data-widget-prop="borderOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Raggio bordi box <output data-widget-output>12px</output></span>
                                        <input type="range" min="0" max="100" step="1" value="12" data-widget="keys" data-widget-prop="borderRadius">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Effetto Blur box <output data-widget-output>16px</output></span>
                                        <input type="range" min="0" max="60" step="1" value="16" data-widget="keys" data-widget-prop="blur">
                                    </label>

                                    <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                        <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Ombra Testo</h5>
                                        <div class="subway-theme-grid">
                                            <label class="subway-color-setting"><span>Colore Ombra</span><input type="color" value="#000000" data-widget="keys" data-widget-prop="shadowColor"></label>
                                        </div>
                                        <label class="subway-opacity-setting">
                                            <span>Opacità Ombra <output data-widget-output>50%</output></span>
                                            <input type="range" min="0" max="100" step="1" value="50" data-widget="keys" data-widget-prop="shadowOpacity">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Sfumatura Blur <output data-widget-output>8px</output></span>
                                            <input type="range" min="0" max="100" step="1" value="8" data-widget="keys" data-widget-prop="shadowBlur">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Spostamento Orizzontale (X) <output data-widget-output>0px</output></span>
                                            <input type="range" min="-100" max="100" step="1" value="0" data-widget="keys" data-widget-prop="shadowX">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Spostamento Verticale (Y) <output data-widget-output>2px</output></span>
                                            <input type="range" min="-100" max="100" step="1" value="2" data-widget="keys" data-widget-prop="shadowY">
                                        </label>
                                    </div>

                                    <div style="margin-top: 1.2rem; padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.08);">
                                        <h5 style="margin: 0 0 0.8rem 0; color: #fff; font-size: 0.85rem;"><i class="fa-solid fa-square-full"></i> Stile Tasti WASD Interni</h5>
                                        <div class="subway-theme-grid">
                                            <label class="subway-color-setting"><span>Sfondo Tasti</span><input type="color" value="#ffffff" data-widget="keys" data-widget-prop="keyBg"></label>
                                            <label class="subway-color-setting"><span>Hover / Attivo</span><input type="color" value="#06b6d4" data-widget="keys" data-widget-prop="keyHover"></label>
                                            <label class="subway-color-setting"><span>Testo Tasti</span><input type="color" value="#ffffff" data-widget="keys" data-widget-prop="keyText"></label>
                                            <label class="subway-color-setting"><span>Bordo Tasti</span><input type="color" value="#06b6d4" data-widget="keys" data-widget-prop="keyBorderColor"></label>
                                        </div>
                                        <label class="subway-opacity-setting">
                                            <span>Opacità sfondo tasti <output data-widget-output>7%</output></span>
                                            <input type="range" min="0" max="100" step="1" value="7" data-widget="keys" data-widget-prop="keyBgOpacity">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Opacità bordo tasti <output data-widget-output>40%</output></span>
                                            <input type="range" min="0" max="100" step="1" value="40" data-widget="keys" data-widget-prop="keyBorderOpacity">
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Tab Pane: Audio / Volume -->
                            <div class="subway-tab-pane" data-overlay-pane="audio">
                                <div class="subway-widget-config-card">
                                    <div class="subway-widget-card-title"><i class="fa-solid fa-volume-high"></i> Overlay Audio / Volume</div>

                                    <details class="subway-settings-group" open>
                                        <summary><i class="fa-solid fa-palette"></i> Colori & Sfondo</summary>
                                        <div class="subway-settings-group-body">
                                            <div class="subway-theme-grid">
                                                <label class="subway-color-setting"><span>Sfondo</span><input type="color" value="#090d18" data-widget="audio" data-widget-prop="bg"></label>
                                                <label class="subway-color-setting"><span>Icona</span><input type="color" value="#ffffff" data-widget="audio" data-widget-prop="textColor"></label>
                                                <label class="subway-color-setting"><span>Accento</span><input type="color" value="#06b6d4" data-widget="audio" data-widget-prop="accentColor"></label>
                                                <label class="subway-color-setting"><span>Bordo</span><input type="color" value="#06b6d4" data-widget="audio" data-widget-prop="borderColor"></label>
                                            </div>
                                            <label class="subway-opacity-setting">
                                                <span>Opacità sfondo <output data-widget-output>88%</output></span>
                                                <input type="range" min="0" max="100" step="1" value="88" data-widget="audio" data-widget-prop="bgOpacity">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Opacità bordo <output data-widget-output>68%</output></span>
                                                <input type="range" min="0" max="100" step="1" value="68" data-widget="audio" data-widget-prop="borderOpacity">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Raggio bordi <output data-widget-output>12px</output></span>
                                                <input type="range" min="0" max="100" step="1" value="12" data-widget="audio" data-widget-prop="borderRadius">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Effetto Blur <output data-widget-output>16px</output></span>
                                                <input type="range" min="0" max="60" step="1" value="16" data-widget="audio" data-widget-prop="blur">
                                            </label>
                                        </div>
                                    </details>

                                    <details class="subway-settings-group">
                                        <summary><i class="fa-solid fa-moon"></i> Ombra</summary>
                                        <div class="subway-settings-group-body">
                                            <div class="subway-theme-grid">
                                                <label class="subway-color-setting"><span>Colore Ombra</span><input type="color" value="#000000" data-widget="audio" data-widget-prop="shadowColor"></label>
                                            </div>
                                            <label class="subway-opacity-setting">
                                                <span>Opacità Ombra <output data-widget-output>50%</output></span>
                                                <input type="range" min="0" max="100" step="1" value="50" data-widget="audio" data-widget-prop="shadowOpacity">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Sfumatura Blur <output data-widget-output>8px</output></span>
                                                <input type="range" min="0" max="100" step="1" value="8" data-widget="audio" data-widget-prop="shadowBlur">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Spostamento Orizzontale (X) <output data-widget-output>0px</output></span>
                                                <input type="range" min="-100" max="100" step="1" value="0" data-widget="audio" data-widget-prop="shadowX">
                                            </label>
                                            <label class="subway-opacity-setting">
                                                <span>Spostamento Verticale (Y) <output data-widget-output>2px</output></span>
                                                <input type="range" min="-100" max="100" step="1" value="2" data-widget="audio" data-widget-prop="shadowY">
                                            </label>
                                        </div>
                                    </details>
                                </div>
                            </div>

                            <!-- Modal Tab Pane: Settings Button -->
                            <div class="subway-tab-pane" data-overlay-pane="settings">
                                <div class="subway-widget-config-card">
                                    <div class="subway-widget-card-title"><i class="fa-solid fa-gear"></i> Pulsante Impostazioni Overlay</div>
                                    <div class="subway-theme-grid">
                                        <label class="subway-color-setting"><span>Sfondo</span><input type="color" value="#090d18" data-widget="settings" data-widget-prop="bg"></label>
                                        <label class="subway-color-setting"><span>Icona</span><input type="color" value="#ffffff" data-widget="settings" data-widget-prop="textColor"></label>
                                        <label class="subway-color-setting"><span>Bordo</span><input type="color" value="#06b6d4" data-widget="settings" data-widget-prop="borderColor"></label>
                                    </div>
                                    <label class="subway-opacity-setting">
                                        <span>Opacità sfondo <output data-widget-output>88%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="88" data-widget="settings" data-widget-prop="bgOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Opacità bordo <output data-widget-output>68%</output></span>
                                        <input type="range" min="0" max="100" step="1" value="68" data-widget="settings" data-widget-prop="borderOpacity">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Raggio bordi <output data-widget-output>12px</output></span>
                                        <input type="range" min="0" max="100" step="1" value="12" data-widget="settings" data-widget-prop="borderRadius">
                                    </label>
                                    <label class="subway-opacity-setting">
                                        <span>Effetto Blur <output data-widget-output>16px</output></span>
                                        <input type="range" min="0" max="60" step="1" value="16" data-widget="settings" data-widget-prop="blur">
                                    </label>

                                    <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--game-border, rgba(255, 255, 255, 0.08));">
                                        <h5 style="margin: 0 0 0.5rem 0; color: #fff; font-size: 0.82rem;"><i class="fa-solid fa-moon"></i> Ombra Icona</h5>
                                        <div class="subway-theme-grid">
                                            <label class="subway-color-setting"><span>Colore Ombra</span><input type="color" value="#000000" data-widget="settings" data-widget-prop="shadowColor"></label>
                                        </div>
                                        <label class="subway-opacity-setting">
                                            <span>Opacità Ombra <output data-widget-output>50%</output></span>
                                            <input type="range" min="0" max="100" step="1" value="50" data-widget="settings" data-widget-prop="shadowOpacity">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Sfumatura Blur <output data-widget-output>8px</output></span>
                                            <input type="range" min="0" max="100" step="1" value="8" data-widget="settings" data-widget-prop="shadowBlur">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Spostamento Orizzontale (X) <output data-widget-output>0px</output></span>
                                            <input type="range" min="-100" max="100" step="1" value="0" data-widget="settings" data-widget-prop="shadowX">
                                        </label>
                                        <label class="subway-opacity-setting">
                                            <span>Spostamento Verticale (Y) <output data-widget-output>2px</output></span>
                                            <input type="range" min="-100" max="100" step="1" value="2" data-widget="settings" data-widget-prop="shadowY">
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Tab Pane: Layout -->
                            <div class="subway-tab-pane" data-overlay-pane="layout">
                                <div class="subway-widget-config-card">
                                    <div class="subway-widget-card-title"><i class="fa-solid fa-sliders"></i> Layout Generale</div>
                                    <div class="subway-option-row">
                                        <div class="subway-option-info">
                                            <strong>Mostra riga Boost (B)</strong>
                                            <span>Mostra il tasto B per il boost sotto il WASD.</span>
                                        </div>
                                        <label class="subway-switch">
                                            <input type="checkbox" data-overlay-toggle="showBoost">
                                            <span class="subway-slider"></span>
                                        </label>
                                    </div>
                                    <div class="subway-option-row">
                                        <div class="subway-option-info">
                                            <strong>Mostra intestazioni overlay</strong>
                                            <span>Mostra la barra di trascinamento e i titoli degli overlay.</span>
                                        </div>
                                        <label class="subway-switch">
                                            <input type="checkbox" data-overlay-toggle="showHeaders" checked>
                                            <span class="subway-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="subway-theme-reset" data-reset-overlay-theme style="margin-top: 1.2rem;">Ripristina impostazioni predefinite</button>
                        </div>

                        <button class="game-btn game-btn-main" id="closeSettingsModal" style="width: 100%; margin-top: 1.5rem; min-height: 2.5rem;">
                            Salva e Chiudi
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
                <button class="subway-boot-cancel" id="cancelSubwayLoad" type="button">Annulla</button>
            </header>

            <div class="subway-boot-content">
                <section class="subway-boot-narrative">
                    <h1 id="subwayBootStage">Preparazione Portale</h1>
                    <p id="subwayBootStatus">Avvio dei moduli di aggancio audio e log...</p>
                </section>

                <div class="subway-boot-progress-section">
                    <span class="subway-boot-stage">NUCLEO IN CORSO</span>
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