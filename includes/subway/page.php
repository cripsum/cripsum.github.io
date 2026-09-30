<?php
/*
 * Pagina di Subway Surfers, unica per IT ed EN. it/subway.php ed
 * en/subway.php fanno solo login/ban e poi includono questo file con
 * $subwayLang impostato. I controlli delle impostazioni usano gli attributi
 * data-* letti da assets/js/subway/subway.js (data-setting, data-widget,
 * data-widget-prop, data-keybind...): cambiarli qui vuol dire cambiarli la'.
 */

require_once __DIR__ . '/catalog.php';

$subwayLang = ($subwayLang ?? 'it') === 'en' ? 'en' : 'it';
$swT = static fn(string $it, string $en): string => $subwayLang === 'it' ? $it : $en;
$swE = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$assetVersion = ['css' => '24.0', 'js' => '24.0', 'profile' => '2.0'];

// Il catalogo passa a subway.js cosi' com'e', piu' la base delle build.
$catalogJson = json_encode([
    'base' => SUBWAY_BUILDS_BASE,
    'trainingModes' => array_keys(SUBWAY_TRAINING_MODES),
    'maps' => subway_catalog(),
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

$regions = [
    'all' => $swT('Tutte', 'All'),
    'europe' => $swT('Europa', 'Europe'),
    'america' => $swT('America', 'America'),
    'asia' => $swT('Asia', 'Asia'),
    'africa' => $swT('Africa', 'Africa'),
    'event' => $swT('Eventi', 'Events'),
];

$trainingModes = [
    'training' => [$swT('Standard', 'Standard'), $swT('Percorsi di allenamento', 'Training patterns')],
    'training3rows' => [$swT('3 file', '3 rows'), $swT('Solo schemi a tre file', 'Three-row patterns only')],
    'trainingobstacles' => [$swT('Ostacoli', 'Obstacles'), $swT('Ostacoli su una sola fila', 'Single-row obstacles')],
];

/*
 * Controlli degli overlay. Ogni widget ha dei gruppi; ogni controllo e'
 * [tipo, etichetta, proprieta', min, max, unita'] per gli slider o
 * [tipo, etichetta, proprieta'] per i colori.
 */
$colorsGroup = static fn(bool $accent, string $textLabel) => [
    ['color', $swT('Sfondo', 'Background'), 'bg'],
    ['color', $textLabel, 'textColor'],
    ...($accent ? [['color', $swT('Accento', 'Accent'), 'accentColor']] : []),
    ['color', $swT('Bordo', 'Border'), 'borderColor'],
    ['range', $swT('Opacità sfondo', 'Background opacity'), 'bgOpacity', 0, 100, '%'],
    ['range', $swT('Opacità bordo', 'Border opacity'), 'borderOpacity', 0, 100, '%'],
    ['range', $swT('Arrotondamento', 'Corner radius'), 'borderRadius', 0, 100, 'px'],
    ['range', $swT('Sfocatura sfondo', 'Background blur'), 'blur', 0, 30, 'px'],
];
$shadowGroup = [
    ['color', $swT('Colore', 'Color'), 'shadowColor'],
    ['range', $swT('Opacità', 'Opacity'), 'shadowOpacity', 0, 100, '%'],
    ['range', $swT('Sfumatura', 'Blur'), 'shadowBlur', 0, 100, 'px'],
    ['range', $swT('Spostamento X', 'Offset X'), 'shadowX', -100, 100, 'px'],
    ['range', $swT('Spostamento Y', 'Offset Y'), 'shadowY', -100, 100, 'px'],
];
$overlayWidgets = [
    'timer' => [
        'icon' => 'fa-stopwatch', 'label' => 'Timer',
        'groups' => [
            [$swT('Colori e sfondo', 'Colors & background'), 'fa-palette', $colorsGroup(true, $swT('Testo', 'Text'))],
            [$swT('Testo e dimensioni', 'Text & size'), 'fa-text-height', [
                ['range', $swT('Dimensione testo', 'Text size'), 'timerFontSize', 10, 120, 'px'],
                ['range', $swT('Larghezza testo', 'Text width'), 'timerTextScaleX', 10, 400, '%'],
                ['range', $swT('Altezza testo', 'Text height'), 'timerTextScaleY', 10, 400, '%'],
                ['range', $swT('Posizione testo X', 'Text position X'), 'timerTextPosX', -200, 200, 'px'],
                ['range', $swT('Posizione testo Y', 'Text position Y'), 'timerTextPosY', -200, 200, 'px'],
                ['range', $swT('Larghezza riquadro', 'Box width'), 'timerWidth', 50, 800, 'px'],
                ['range', $swT('Altezza riquadro', 'Box height'), 'timerHeight', 30, 400, 'px'],
            ]],
            [$swT('Ombra', 'Shadow'), 'fa-moon', $shadowGroup],
            [$swT('Immagine di sfondo', 'Background image'), 'fa-image', 'banner'],
        ],
    ],
    'fps' => [
        'icon' => 'fa-gauge-high', 'label' => 'FPS',
        'groups' => [
            [$swT('Colori e sfondo', 'Colors & background'), 'fa-palette', $colorsGroup(false, $swT('Testo', 'Text'))],
            [$swT('Ombra', 'Shadow'), 'fa-moon', $shadowGroup],
        ],
    ],
    'keys' => [
        'icon' => 'fa-keyboard', 'label' => $swT('Tasti', 'Keys'),
        'groups' => [
            [$swT('Riquadro', 'Box'), 'fa-palette', $colorsGroup(false, $swT('Testo', 'Text'))],
            [$swT('Tasti', 'Keycaps'), 'fa-square-full', [
                ['color', $swT('Sfondo tasti', 'Key background'), 'keyBg'],
                ['color', $swT('Premuto', 'Pressed'), 'keyHover'],
                ['color', $swT('Testo tasti', 'Key text'), 'keyText'],
                ['color', $swT('Bordo tasti', 'Key border'), 'keyBorderColor'],
                ['range', $swT('Opacità sfondo tasti', 'Key background opacity'), 'keyBgOpacity', 0, 100, '%'],
                ['range', $swT('Opacità bordo tasti', 'Key border opacity'), 'keyBorderOpacity', 0, 100, '%'],
            ]],
            [$swT('Ombra', 'Shadow'), 'fa-moon', $shadowGroup],
        ],
    ],
    'audio' => [
        'icon' => 'fa-volume-high', 'label' => 'Audio',
        'groups' => [
            [$swT('Colori e sfondo', 'Colors & background'), 'fa-palette', $colorsGroup(true, $swT('Icona', 'Icon'))],
            [$swT('Ombra', 'Shadow'), 'fa-moon', $shadowGroup],
        ],
    ],
    'settings' => [
        'icon' => 'fa-gear', 'label' => $swT('Pulsante', 'Button'),
        'groups' => [
            [$swT('Colori e sfondo', 'Colors & background'), 'fa-palette', $colorsGroup(false, $swT('Icona', 'Icon'))],
            [$swT('Ombra', 'Shadow'), 'fa-moon', $shadowGroup],
        ],
    ],
];

$renderControl = static function (string $widget, array $control) use ($swE): string {
    if ($control[0] === 'color') {
        return '<label class="sw-color"><input type="color" value="#000000" data-widget="' . $swE($widget)
            . '" data-widget-prop="' . $swE($control[2]) . '"><span>' . $swE($control[1]) . '</span></label>';
    }
    [, $label, $prop, $min, $max, $unit] = $control;
    return '<label class="subway-opacity-setting sw-range"><span>' . $swE($label) . ' <output data-widget-output>0' . $swE($unit)
        . '</output></span><input type="range" min="' . (int)$min . '" max="' . (int)$max . '" step="1" value="0" data-widget="'
        . $swE($widget) . '" data-widget-prop="' . $swE($prop) . '"></label>';
};

$switch = static fn(string $attrs, bool $checked = false): string =>
    '<label class="subway-switch"><input type="checkbox" ' . $attrs . ($checked ? ' checked' : '') . '><span class="subway-slider"></span></label>';

$keybinds = [
    'jump' => [$swT('Salta', 'Jump'), 'W'],
    'duck' => [$swT('Scivola', 'Roll'), 'S'],
    'left' => [$swT('Sinistra', 'Left'), 'A'],
    'right' => [$swT('Destra', 'Right'), 'D'],
    'boost' => [$swT('Boost rosso iniziale', 'Starting red boost'), 'B'],
];
?>
<!DOCTYPE html>
<html lang="<?= $swE($subwayLang) ?>">

<head>
    <?php $ogTitle = 'Cripsum™ Subway Surfers'; $ogImage = '/img/og-default.jpg'; ?>
    <?php include __DIR__ . '/../head-import.php'; ?>
    <title>Cripsum™ Subway Surfers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <!-- Loader Unity e modulo di compatibilita' vengono ancora da jsDelivr; le build dal nostro server. -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>

    <link class="subway-css" rel="stylesheet" href="/assets/css/game.css?v=6.0">
    <link rel="stylesheet" href="/assets/css/subway.css?v=<?= $assetVersion['css'] ?>">
    <script src="/assets/js/subway/subway-profile.js?v=<?= $assetVersion['profile'] ?>" defer></script>
    <script src="/assets/js/subway/subway.js?v=<?= $assetVersion['js'] ?>" defer></script>
</head>

<body class="game-page">
    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="game-bg" aria-hidden="true"><span></span><span></span></div>

    <script type="application/json" id="subwayCatalog"><?= $catalogJson ?></script>

    <main class="subway-dashboard" id="subwayPortal">

        <div id="subwayLobby">
            <section class="game-hero sw-hero game-reveal">
                <div class="game-hero-copy">
                    <span class="game-kicker"><i class="fa-solid fa-coins"></i> No-Coin Challenge</span>
                    <h1>Subway Surfers</h1>
                    <p><?= $swT(
                        'Corri il più a lungo possibile senza prendere nemmeno una moneta. Il timer parte da solo con la run e si ferma alla prima moneta: il tuo tempo migliore va in classifica.',
                        'Run as long as you can without picking up a single coin. The timer starts with the run and stops at the first coin: your best time goes on the leaderboard.'
                    ) ?></p>
                    <div class="game-steps" aria-label="<?= $swT('Come funziona', 'How it works') ?>">
                        <span><b>1</b> <?= $swT('Scegli la città', 'Pick a city') ?></span>
                        <span><b>2</b> <?= $swT('Premi SPAZIO nel gioco', 'Press SPACE in game') ?></span>
                        <span><b>3</b> <?= $swT('Schiva le monete', 'Dodge the coins') ?></span>
                    </div>
                    <div class="sw-hero-actions">
                        <button type="button" class="game-btn game-btn-main" id="subwayQuickPlay" hidden>
                            <i class="fa-solid fa-play"></i> <span></span>
                        </button>
                        <button type="button" class="game-btn" id="openSubwaySettings">
                            <i class="fa-solid fa-sliders"></i> <?= $swT('Impostazioni', 'Settings') ?>
                        </button>
                    </div>
                </div>

                <div class="subway-personal-best-card" id="subwayPersonalBestCard">
                    <div class="subway-pb-badge"><i class="fa-solid fa-trophy"></i> <?= $swT('Il tuo record', 'Your best') ?></div>
                    <div class="subway-pb-time" id="subwayPersonalBestTime">--:--.---</div>
                    <div class="subway-pb-meta">
                        <span><?= $swT('Posizione', 'Rank') ?> <strong id="subwayPersonalBestRank">-</strong></span>
                        <span><?= $swT('Mappa', 'Map') ?> <strong id="subwayPersonalBestMap">-</strong></span>
                    </div>
                </div>
            </section>

            <div class="sw-notice" id="subwayTouchNotice" hidden>
                <i class="fa-solid fa-keyboard"></i>
                <p><?= $swT(
                    'Serve una tastiera: su telefono e tablet il gioco parte, ma i controlli e il timer della sfida funzionano solo con i tasti.',
                    'A keyboard is required: on phones and tablets the game loads, but the controls and the challenge timer only work with keys.'
                ) ?></p>
            </div>

            <section class="game-panel sw-maps game-reveal" aria-labelledby="subwayMapsTitle">
                <div class="sw-panel-head">
                    <div>
                        <h2 id="subwayMapsTitle"><?= $swT('Scegli la mappa', 'Choose a map') ?></h2>
                        <p class="game-hint" id="subwayMapsHint"><?= $swT(
                            'Circa 20 MB a mappa: dalla seconda volta si apre molto prima.',
                            'About 20 MB per map: from the second time on it opens much faster.'
                        ) ?></p>
                    </div>
                    <div class="sw-mode" role="radiogroup" aria-label="<?= $swT('Modalità', 'Mode') ?>">
                        <button type="button" role="radio" aria-checked="true" class="is-active" data-mode="original">
                            <i class="fa-solid fa-trophy"></i> <?= $swT('Classifica', 'Ranked') ?>
                        </button>
                        <button type="button" role="radio" aria-checked="false" data-mode="training">
                            <i class="fa-solid fa-dumbbell"></i> <?= $swT('Allenamento', 'Training') ?>
                        </button>
                    </div>
                </div>

                <div class="sw-training-bar" id="subwayTrainingBar" hidden>
                    <div class="sw-chips" role="radiogroup" aria-label="<?= $swT('Tipo di allenamento', 'Training type') ?>">
                        <?php foreach ($trainingModes as $key => [$label, $desc]): ?>
                            <button type="button" role="radio" class="sw-chip<?= $key === 'training' ? ' is-active' : '' ?>" aria-checked="<?= $key === 'training' ? 'true' : 'false' ?>" data-training="<?= $swE($key) ?>" title="<?= $swE($desc) ?>"><?= $swE($label) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <p><i class="fa-solid fa-circle-info"></i> <?= $swT(
                        'Le run di allenamento non entrano in classifica. Disponibile sulle mappe con il badge.',
                        'Training runs do not count for the leaderboard. Available on maps with the badge.'
                    ) ?></p>
                </div>

                <div class="sw-toolbar">
                    <label class="sw-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="subwayMapSearch" placeholder="<?= $swT('Cerca una città', 'Search a city') ?>" autocomplete="off">
                    </label>
                    <div class="sw-chips" role="radiogroup" aria-label="<?= $swT('Regione', 'Region') ?>">
                        <?php foreach ($regions as $key => $label): ?>
                            <button type="button" role="radio" class="sw-chip<?= $key === 'all' ? ' is-active' : '' ?>" aria-checked="<?= $key === 'all' ? 'true' : 'false' ?>" data-region="<?= $swE($key) ?>"><?= $swE($label) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="subway-grid sw-grid" id="subwayMapGrid"></div>
                <p class="sw-empty" id="subwayMapEmpty" hidden><?= $swT('Nessuna mappa trovata.', 'No maps found.') ?></p>
            </section>

            <section class="game-panel game-reveal subway-leaderboard-panel">
                <div class="sw-panel-head">
                    <div class="subway-lb-header-info">
                        <h2><i class="fa-solid fa-trophy"></i> <?= $swT('Classifica', 'Leaderboard') ?></h2>
                        <p><?= $swT('Il tempo migliore di ogni giocatore, su qualsiasi mappa.', 'Every player\'s best time, on any map.') ?></p>
                    </div>
                    <button class="game-btn" id="subwayLeaderboardRefresh" type="button" aria-label="<?= $swT('Aggiorna la classifica', 'Refresh the leaderboard') ?>">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                </div>
                <div class="subway-leaderboard-wrapper">
                    <div class="subway-leaderboard-empty" id="subwayLeaderboardEmpty" hidden>
                        <p><?= $swT('Nessun tempo ancora: il primo posto è libero.', 'No times yet: first place is up for grabs.') ?></p>
                    </div>
                    <div class="subway-leaderboard-table-responsive">
                        <table class="subway-lb-table" id="subwayLeaderboardTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?= $swT('Giocatore', 'Player') ?></th>
                                    <th><?= $swT('Tempo', 'Time') ?></th>
                                    <th><?= $swT('Mappa', 'Map') ?></th>
                                </tr>
                            </thead>
                            <tbody id="subwayLeaderboardBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div id="subwayGameArea" style="display: none;">
            <div class="subway-arena-wrapper">
                <button class="game-btn game-btn-special sw-exit" id="exitGameBtn" type="button">
                    <i class="fa-solid fa-arrow-left"></i> <?= $swT('Torna alle mappe', 'Back to maps') ?>
                </button>

                <div class="sw-mode-badge" id="subwayModeBadge" hidden></div>

                <div class="subway-start-hint" id="subwayStartHint">
                    <kbd><?= $swT('SPAZIO', 'SPACE') ?></kbd>
                    <span><?= $swT('Premi SPAZIO o clicca nel gioco per partire: il timer si avvia da solo con la run.', 'Press SPACE or click in the game to start: the timer starts on its own with the run.') ?></span>
                </div>

                <div class="subway-hud-widget subway-timer-widget" id="hudWidgetTimer">
                    <div class="subway-timer-banner-bg"></div>
                    <div class="subway-timer-display" id="subwayTimerDisplay">00:00.000</div>
                </div>

                <div class="subway-hud-widget subway-fps-widget" id="hudWidgetFps">
                    <div class="subway-fps-readout">
                        <strong id="subwayFpsValue">--</strong><span>FPS</span>
                    </div>
                </div>

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

                <div class="subway-hud-widget subway-audio-widget" id="hudWidgetAudio">
                    <button class="subway-audio-btn" type="button" aria-label="<?= $swT('Muto / attiva audio', 'Mute / unmute') ?>">
                        <i class="fa-solid fa-volume-high"></i>
                    </button>
                    <div class="subway-audio-slider-wrap">
                        <input type="range" class="subway-audio-slider" min="0" max="1" step="0.01" value="0.8" aria-label="Volume">
                    </div>
                </div>

                <button class="subway-hud-widget subway-settings-btn-widget" id="hudWidgetSettingsBtn" type="button" aria-label="<?= $swT('Apri le impostazioni', 'Open settings') ?>">
                    <i class="fa-solid fa-gear"></i>
                </button>

                <div class="sw-run-card" id="subwayRunCard" role="status" aria-live="polite" hidden>
                    <span class="sw-run-card-label" data-run-label></span>
                    <strong class="sw-run-card-time" data-run-time>00:00.000</strong>
                    <span class="sw-run-card-save" data-run-save></span>
                    <small><?= $swT('La prossima run parte da sola. R per azzerare.', 'The next run starts on its own. R to reset.') ?></small>
                </div>

                <div class="subway-game-container" id="subwayGameContainer"></div>
            </div>
        </div>

    </main>

    <div class="subway-settings-modal sw-modal" id="subwaySettingsModal" role="dialog" aria-modal="true" aria-labelledby="subwaySettingsTitle" hidden>
        <div class="subway-modal-box sw-modal-box">
            <header class="sw-modal-head">
                <h2 id="subwaySettingsTitle"><i class="fa-solid fa-sliders"></i> <?= $swT('Impostazioni', 'Settings') ?></h2>
                <span class="sw-saved" id="subwaySavedBadge" aria-live="polite"></span>
                <button type="button" class="sw-icon-btn" id="closeSettingsModal" aria-label="<?= $swT('Chiudi', 'Close') ?>"><i class="fa-solid fa-xmark"></i></button>
            </header>

            <div class="sw-modal-body">
                <nav class="sw-modal-nav" role="tablist" aria-label="<?= $swT('Sezioni', 'Sections') ?>">
                    <?php foreach ([
                        'challenge' => ['fa-coins', $swT('Sfida', 'Challenge')],
                        'controls' => ['fa-keyboard', $swT('Controlli', 'Controls')],
                        'graphics' => ['fa-display', $swT('Grafica', 'Graphics')],
                        'overlay' => ['fa-layer-group', $swT('Overlay', 'Overlays')],
                    ] as $key => [$icon, $label]): ?>
                        <button type="button" role="tab" id="swTab-<?= $key ?>" aria-controls="swPane-<?= $key ?>" aria-selected="<?= $key === 'challenge' ? 'true' : 'false' ?>" class="<?= $key === 'challenge' ? 'is-active' : '' ?>" data-settings-tab="<?= $key ?>">
                            <i class="fa-solid <?= $icon ?>"></i><span><?= $swE($label) ?></span>
                        </button>
                    <?php endforeach; ?>
                </nav>

                <div class="sw-modal-panes">
                    <section class="sw-pane is-active" id="swPane-challenge" role="tabpanel" aria-labelledby="swTab-challenge" data-settings-pane="challenge">
                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong>No-Coin Challenge</strong>
                                <span><?= $swT('Il timer parte con la run e si ferma alla prima moneta. Spenta, il gioco resta libero e non salva tempi.', 'The timer starts with the run and stops at the first coin. When off, the game is free and no times are saved.') ?></span>
                            </div>
                            <?= $switch('id="toggleNoCoinChallenge"', true) ?>
                        </div>
                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong><?= $swT('Blocca SPAZIO', 'Block SPACE') ?></strong>
                                <span><?= $swT('Evita di attivare l\'hoverboard per sbaglio (farebbe fallire la sfida).', 'Avoids activating the hoverboard by mistake (it would fail the challenge).') ?></span>
                            </div>
                            <?= $switch('data-setting="blockSpace"') ?>
                        </div>
                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong><?= $swT('Auto boost (3×)', 'Auto boost (3×)') ?></strong>
                                <span><?= $swT('Usa da solo il boost rosso tre volte appena parte la run.', 'Uses the red boost three times as soon as the run starts.') ?></span>
                            </div>
                            <?= $switch('data-setting="autoBoost"') ?>
                        </div>
                    </section>

                    <section class="sw-pane" id="swPane-controls" role="tabpanel" aria-labelledby="swTab-controls" data-settings-pane="controls" hidden>
                        <p class="sw-pane-hint"><?= $swT('Clicca un tasto e premi quello nuovo. Le frecce funzionano sempre.', 'Click a key and press the new one. Arrow keys always work.') ?></p>
                        <div class="subway-keybind-list">
                            <?php foreach ($keybinds as $action => [$label, $default]): ?>
                                <div class="subway-keybind-item">
                                    <label><?= $swE($label) ?></label>
                                    <button type="button" class="subway-key-btn" data-keybind="<?= $swE($action) ?>"><?= $swE($default) ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="sw-shortcuts">
                            <span><kbd>R</kbd> <?= $swT('chiude e salva la run, poi azzera il timer', 'ends and saves the run, then resets the timer') ?></span>
                            <span><kbd>Esc</kbd> <?= $swT('chiude questa finestra', 'closes this window') ?></span>
                        </div>
                    </section>

                    <section class="sw-pane" id="swPane-graphics" role="tabpanel" aria-labelledby="swTab-graphics" data-settings-pane="graphics" hidden>
                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong><?= $swT('VSync adattivo', 'Adaptive VSync') ?></strong>
                                <span><?= $swT('Segue gli Hz del monitor. Spento, puoi fissare un limite di FPS.', 'Follows your monitor refresh rate. When off, you can set an FPS cap.') ?></span>
                            </div>
                            <?= $switch('data-setting="vsync"', true) ?>
                        </div>
                        <label class="subway-fps-limit-setting" data-fps-control>
                            <span><strong><?= $swT('Limite FPS', 'FPS cap') ?></strong><small><?= $swT('Arrotondato a un divisore del refresh.', 'Rounded to a divisor of the refresh rate.') ?></small></span>
                            <input type="number" min="30" max="500" step="1" value="144" data-fps-limit>
                        </label>
                        <label class="subway-fps-limit-setting">
                            <span><strong><?= $swT('Scala di rendering', 'Render scale') ?></strong><small><?= $swT('Meno pixel, più FPS. Effetto immediato.', 'Fewer pixels, more FPS. Applies instantly.') ?></small></span>
                            <select data-render-scale>
                                <option value="native"><?= $swT('Nativa', 'Native') ?></option>
                                <?php foreach ([100, 85, 75, 65, 50] as $scale): ?>
                                    <option value="<?= $scale ?>"><?= $scale ?>%</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="subway-option-row">
                            <div class="subway-option-info">
                                <strong><?= $swT('Modalità prestazioni', 'Performance mode') ?></strong>
                                <span><?= $swT('Toglie sfocature e ombre degli overlay. L\'anti-aliasing cambia alla partita successiva.', 'Removes overlay blur and shadows. Anti-aliasing changes on the next game.') ?></span>
                            </div>
                            <?= $switch('data-setting="perfMode"') ?>
                        </div>
                    </section>

                    <section class="sw-pane sw-pane-overlay" id="swPane-overlay" role="tabpanel" aria-labelledby="swTab-overlay" data-settings-pane="overlay" hidden>
                        <div class="subway-overlay-tabs sw-widget-tabs">
                            <?php $first = true; foreach ($overlayWidgets as $key => $widget): ?>
                                <button type="button" class="subway-tab-btn<?= $first ? ' active' : '' ?>" data-overlay-tab="<?= $key ?>"><i class="fa-solid <?= $widget['icon'] ?>"></i> <?= $swE($widget['label']) ?></button>
                            <?php $first = false; endforeach; ?>
                            <button type="button" class="subway-tab-btn" data-overlay-tab="layout"><i class="fa-solid fa-table-cells-large"></i> Layout</button>
                        </div>

                        <div class="sw-preview" id="subwayOverlayPreview" data-active="timer" aria-hidden="true">
                            <div class="subway-hud-widget subway-timer-widget" data-preview-widget="timer">
                                <div class="subway-timer-banner-bg"></div>
                                <div class="subway-timer-display">01:23<span class="subway-ms">.456</span></div>
                            </div>
                            <div class="subway-hud-widget subway-fps-widget" data-preview-widget="fps">
                                <div class="subway-fps-readout"><strong>144</strong><span>FPS</span></div>
                            </div>
                            <div class="subway-hud-widget subway-keys-widget" data-preview-widget="keys">
                                <div class="subway-hud-keys-grid">
                                    <span></span><div class="subway-hud-key pressed">W</div><span></span>
                                    <div class="subway-hud-key">A</div><div class="subway-hud-key">S</div><div class="subway-hud-key">D</div>
                                </div>
                                <div class="subway-hud-boost-row"><span>BOOST</span><div class="subway-hud-key">B</div></div>
                            </div>
                            <div class="subway-hud-widget subway-audio-widget" data-preview-widget="audio">
                                <span class="subway-audio-btn"><i class="fa-solid fa-volume-high"></i></span>
                            </div>
                            <div class="subway-hud-widget subway-settings-btn-widget" data-preview-widget="settings">
                                <i class="fa-solid fa-gear"></i>
                            </div>
                        </div>

                        <?php $first = true; foreach ($overlayWidgets as $key => $widget): ?>
                            <div class="subway-tab-pane<?= $first ? ' active' : '' ?>" data-overlay-pane="<?= $key ?>">
                                <?php foreach ($widget['groups'] as $index => [$groupLabel, $groupIcon, $controls]): ?>
                                    <details class="subway-settings-group"<?= $index === 0 ? ' open' : '' ?>>
                                        <summary><i class="fa-solid <?= $groupIcon ?>"></i> <?= $swE($groupLabel) ?></summary>
                                        <div class="subway-settings-group-body">
                                            <?php if ($controls === 'banner'): ?>
                                                <div class="subway-img-input-row">
                                                    <input type="text" placeholder="<?= $swT('URL immagine (https://...)', 'Image URL (https://...)') ?>" data-widget="timer" data-widget-prop="bgImage">
                                                    <label class="subway-img-btn">
                                                        <i class="fa-solid fa-upload"></i> <?= $swT('Carica', 'Upload') ?>
                                                        <input type="file" accept="image/*" data-timer-bg-file hidden>
                                                    </label>
                                                    <button type="button" class="subway-img-btn subway-img-btn-remove" data-timer-bg-remove aria-label="<?= $swT('Rimuovi immagine', 'Remove image') ?>"><i class="fa-solid fa-trash"></i></button>
                                                </div>
                                                <p class="sw-pane-hint" data-timer-bg-note><?= $swT('Le immagini caricate vengono ridotte a 800 px per stare nella memoria del browser.', 'Uploaded images are resized to 800 px to fit in browser storage.') ?></p>
                                                <label class="subway-fps-limit-setting">
                                                    <span><strong><?= $swT('Adattamento', 'Fit') ?></strong></span>
                                                    <select data-widget="timer" data-widget-prop="bgFit">
                                                        <option value="cover"><?= $swT('Riempi', 'Cover') ?></option>
                                                        <option value="contain"><?= $swT('Intera', 'Contain') ?></option>
                                                        <option value="custom"><?= $swT('Personalizzato', 'Custom') ?></option>
                                                    </select>
                                                </label>
                                                <?php foreach ([
                                                    ['range', $swT('Opacità immagine', 'Image opacity'), 'bgImageOpacity', 0, 100, '%'],
                                                    ['range', 'Zoom', 'bgImageScale', 1, 1000, '%'],
                                                    ['range', $swT('Spostamento X', 'Offset X'), 'bgImagePosX', -2000, 2000, 'px'],
                                                    ['range', $swT('Spostamento Y', 'Offset Y'), 'bgImagePosY', -2000, 2000, 'px'],
                                                    ['range', $swT('Rotazione', 'Rotation'), 'bgImageRotate', 0, 360, '°'],
                                                ] as $control): ?>
                                                    <?= $renderControl('timer', $control) ?>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?php $colors = array_filter($controls, fn($c) => $c[0] === 'color'); ?>
                                                <?php if ($colors): ?>
                                                    <div class="sw-color-grid">
                                                        <?php foreach ($colors as $control): ?><?= $renderControl($key, $control) ?><?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php foreach ($controls as $control): if ($control[0] !== 'range') continue; ?>
                                                    <?= $renderControl($key, $control) ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        <?php $first = false; endforeach; ?>

                        <div class="subway-tab-pane" data-overlay-pane="layout">
                            <div class="subway-option-row">
                                <div class="subway-option-info">
                                    <strong><?= $swT('Riga del boost', 'Boost row') ?></strong>
                                    <span><?= $swT('Mostra il tasto B sotto WASD.', 'Shows the B key under WASD.') ?></span>
                                </div>
                                <?= $switch('data-overlay-toggle="showBoost"') ?>
                            </div>
                            <div class="subway-option-row">
                                <div class="subway-option-info">
                                    <strong><?= $swT('Intestazioni degli overlay', 'Overlay headers') ?></strong>
                                    <span><?= $swT('Barra di trascinamento e titoli sopra gli overlay.', 'Drag bar and titles above the overlays.') ?></span>
                                </div>
                                <?= $switch('data-overlay-toggle="showHeaders"', true) ?>
                            </div>
                            <p class="sw-pane-hint"><?= $swT('In partita puoi trascinare ogni overlay dove vuoi: la posizione viene ricordata.', 'In game you can drag every overlay anywhere: its position is remembered.') ?></p>
                        </div>
                    </section>
                </div>
            </div>

            <footer class="sw-modal-foot">
                <button type="button" class="game-btn sw-reset" data-reset-overlay-theme><i class="fa-solid fa-rotate-left"></i> <?= $swT('Overlay predefiniti', 'Default overlays') ?></button>
                <button type="button" class="game-btn game-btn-main" data-close-settings><?= $swT('Fatto', 'Done') ?></button>
            </footer>
        </div>
    </div>

    <div class="subway-boot-splash hidden" id="subwayBootSplash" role="status" aria-live="polite">
        <main class="subway-boot-panel">
            <header class="subway-boot-header">
                <div class="subway-boot-identity">
                    <span class="subway-boot-logo"><i class="fa-solid fa-train-subway"></i></span>
                    <div class="subway-boot-title">
                        <strong id="subwayBootMap">Subway Surfers</strong>
                        <small id="subwayBootMode"><?= $swT('Classifica', 'Ranked') ?></small>
                    </div>
                </div>
                <button class="subway-boot-cancel" id="cancelSubwayLoad" type="button"><?= $swT('Annulla', 'Cancel') ?></button>
            </header>

            <div class="subway-boot-content">
                <section class="subway-boot-narrative">
                    <h1 id="subwayBootStage"><?= $swT('Preparazione', 'Getting ready') ?></h1>
                    <p id="subwayBootStatus"><?= $swT('Avvio del player...', 'Starting the player...') ?></p>
                </section>

                <div class="subway-boot-progress-section">
                    <span class="subway-boot-stage" id="subwayBootBytes"></span>
                    <span class="subway-boot-percent"><strong id="subwayBootPercent">0</strong>%</span>
                </div>

                <div class="subway-boot-track-line">
                    <div class="subway-boot-track-value" id="subwayBootTrackValue"></div>
                </div>

                <details class="sw-boot-details">
                    <summary><?= $swT('Dettagli tecnici', 'Technical details') ?></summary>
                    <div class="subway-boot-console" id="subwayBootConsole"></div>
                </details>
            </div>
        </main>
    </div>

    <?php include __DIR__ . '/../footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
