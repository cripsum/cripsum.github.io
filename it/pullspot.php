<?php

/**
 * Cripsum™ — Pullspot
 *
 * Songspot con le musiche delle animazioni di pull: si sente un frammento che
 * si allunga a ogni errore e si deve indovinare di quale personaggio è. Si
 * gioca quanto si vuole: finita una traccia ne parte un'altra.
 *
 * Stesso file su /it/pullspot e /en/pullspot, la lingua viene dall'indirizzo.
 */

require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/pullspot_helpers.php';

checkBan($mysqli);

$lang = str_contains((string)($_SERVER['REQUEST_URI'] ?? ''), '/en/') ? 'en' : 'it';
$isEn = $lang === 'en';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = $isEn
        ? 'You need to be logged in to play Pullspot.'
        : 'Per giocare a Pullspot devi essere loggato.';

    header('Location: accedi');
    exit();
}

function ps_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$copy = [
    'it' => [
        'attempts'   => 'Tentativi',
        'newTrack'   => 'Nuova traccia',
        'unlocks'    => 'Sblocchi',
        'volume'     => 'Volume',
        'help'       => 'Aiuto',
        'rules'      => 'Come si gioca',
        'stats'      => 'Statistiche',
        'loading'    => 'Sto cercando una traccia…',
        'retry'      => 'Riprova',
        'play'       => 'Ascolta il frammento',
        'clear'      => 'Cancella',
        'close'      => 'Chiudi',
        'search'     => 'Che personaggio è?',
        'skip'       => 'Salta',
        'rulesList'  => [
            'Parte l\'inizio della musica di pull di un personaggio: devi capire di chi è.',
            'Hai sei tentativi. Ogni errore o salto allunga il frammento: 0,1s → 0,5s → 2s → 5s → 8s → 15s.',
            'Scrivi nel campo di ricerca e scegli un nome dall\'elenco: la scelta vale subito come tentativo.',
            'Un nome già provato sparisce dall\'elenco: non lo puoi rigiocare.',
            'Se due personaggi condividono la stessa musica valgono tutti e due.',
            'La traccia è sempre a caso e non ci sono limiti: finita una partita ne parte un\'altra.',
        ],
        'description' => 'Indovina il personaggio Cripsum dalla musica della sua animazione di pull: sei tentativi, un frammento più lungo a ogni errore.',
    ],
    'en' => [
        'attempts'   => 'Guesses',
        'newTrack'   => 'New track',
        'unlocks'    => 'Unlocks',
        'volume'     => 'Volume',
        'help'       => 'Help',
        'rules'      => 'How to play',
        'stats'      => 'Statistics',
        'loading'    => 'Looking for a track…',
        'retry'      => 'Try again',
        'play'       => 'Play the snippet',
        'clear'      => 'Clear',
        'close'      => 'Close',
        'search'     => 'Name that character',
        'skip'       => 'Skip',
        'rulesList'  => [
            'You hear the beginning of a character\'s pull track: work out whose it is.',
            'You get six tries. Every wrong guess or skip makes the snippet longer: 0.1s → 0.5s → 2s → 5s → 8s → 15s.',
            'Type in the search box and pick a name from the list: picking one counts as your guess straight away.',
            'A name you already tried drops out of the list: you cannot spend a guess on it twice.',
            'If two characters share the same track, both of them count as right.',
            'The track is always random and there is no limit: when a round ends, another one starts.',
        ],
        'description' => 'Guess the Cripsum character from their pull animation track: six tries, a longer snippet after every miss.',
    ],
][$lang];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">

<head>
    <?php $ogDescription = $copy['description']; ?>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ — Pullspot</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#050706">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Pullspot — Cripsum™">
    <meta property="og:description" content="<?php echo ps_h($copy['description']); ?>">

    <link rel="stylesheet" href="<?php echo pullspot_asset('/assets/pullspot/pullspot.css'); ?>">
    <script>
        window.PULLSPOT_LANG = '<?php echo $lang; ?>';
        window.PULLSPOT_CSRF = '<?php echo ps_h(function_exists('csrf_token') ? csrf_token() : ''); ?>';
    </script>
    <script src="<?php echo pullspot_asset('/assets/pullspot/pullspot.js'); ?>" defer></script>
</head>

<body class="ps-page">
    <?php include '../includes/navbar.php'; ?>

    <div class="ps-beam" aria-hidden="true"><span></span></div>
    <div class="ps-glow" aria-hidden="true"></div>
    <canvas class="ps-confetti" data-ps-confetti aria-hidden="true"></canvas>

    <main class="ps-stage" data-ps-root>

        <div class="ps-brand" aria-hidden="true">pullspot</div>

        <div class="ps-boot ps-full" data-ps-boot>
            <div class="ps-spinner" aria-hidden="true"></div>
            <p><?php echo ps_h($copy['loading']); ?></p>
        </div>

        <div class="ps-error ps-full" data-ps-error hidden>
            <p data-ps-error-text></p>
            <button type="button" class="ps-btn" onclick="window.location.reload()"><?php echo ps_h($copy['retry']); ?></button>
        </div>

        <div class="ps-game" data-ps-game hidden>

            <!-- ══ Colonna sinistra: la storia della partita ══ -->
            <aside class="ps-rail">
                <div>
                    <h2 class="ps-group__title"><?php echo ps_h($copy['attempts']); ?></h2>
                    <ol class="ps-rows" data-ps-rows></ol>
                    <button type="button" class="ps-link" data-ps-new>
                        <i class="fa-solid fa-rotate" aria-hidden="true"></i> <?php echo ps_h($copy['newTrack']); ?>
                    </button>
                </div>
            </aside>

            <!-- ══ Colonna centrale: il lettore, poi la rivelazione ══ -->
            <section class="ps-center">

                <div class="ps-meta">
                    <span data-ps-meta-left></span>
                    <span class="ps-meta__now" data-ps-meta-right></span>
                </div>

                <div class="ps-player" data-ps-player>
                    <div class="ps-bar">
                        <div class="ps-segments" data-ps-segments></div>
                        <div class="ps-marks"><span class="ps-marker" data-ps-marker></span></div>
                    </div>

                    <div class="ps-transport">
                        <button type="button" class="ps-play" data-ps-play aria-pressed="false"
                                aria-label="<?php echo ps_h($copy['play']); ?>">
                            <i class="fa-solid fa-play" aria-hidden="true"></i>
                        </button>
                        <div class="ps-clock">
                            <span class="ps-clock__value" data-ps-clock>—</span>
                            <span class="ps-clock__label" data-ps-clock-label></span>
                        </div>
                    </div>

                    <div class="ps-guessrow" data-ps-controls hidden>
                        <div class="ps-search">
                            <i class="fa-solid fa-magnifying-glass ps-search__icon" aria-hidden="true"></i>
                            <input type="text" class="ps-input" data-ps-input autocomplete="off" spellcheck="false"
                                   role="combobox" aria-expanded="false" aria-autocomplete="list"
                                   placeholder="<?php echo ps_h($copy['search']); ?>">
                            <button type="button" class="ps-clear" data-ps-clear hidden
                                    aria-label="<?php echo ps_h($copy['clear']); ?>">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                            <ul class="ps-list" role="listbox" data-ps-list hidden></ul>
                        </div>
                        <button type="button" class="ps-skip" data-ps-skip>
                            <i class="fa-solid fa-forward-step" aria-hidden="true"></i>
                            <span><?php echo ps_h($copy['skip']); ?></span>
                            <small data-ps-skip-bonus></small>
                        </button>
                    </div>
                </div>

                <div class="ps-reveal" data-ps-reveal hidden></div>
            </section>

            <!-- ══ Colonna destra: le opzioni ══ -->
            <aside class="ps-rail">
                <div>
                    <h2 class="ps-group__title"><?php echo ps_h($copy['unlocks']); ?></h2>
                    <div class="ps-chips" data-ps-chips></div>
                </div>

                <div>
                    <h2 class="ps-group__title"><?php echo ps_h($copy['volume']); ?></h2>
                    <input type="range" class="ps-volume" data-ps-volume min="0" max="100" step="1" value="80"
                           aria-label="<?php echo ps_h($copy['volume']); ?>">
                </div>

                <div>
                    <h2 class="ps-group__title"><?php echo ps_h($copy['help']); ?></h2>
                    <div class="ps-stack">
                        <button type="button" class="ps-opt" data-ps-open-rules>
                            <i class="fa-solid fa-circle-question" aria-hidden="true"></i> <?php echo ps_h($copy['rules']); ?>
                        </button>
                        <button type="button" class="ps-opt" data-ps-open-stats>
                            <i class="fa-solid fa-chart-simple" aria-hidden="true"></i> <?php echo ps_h($copy['stats']); ?>
                        </button>
                    </div>
                </div>
            </aside>
        </div>

        <div class="ps-toast" data-ps-toast role="status" aria-live="polite"></div>
    </main>

    <div class="ps-modal" data-ps-stats-modal hidden role="dialog" aria-modal="true" aria-label="<?php echo ps_h($copy['stats']); ?>">
        <div class="ps-modal__box">
            <div class="ps-modal__head">
                <h2><?php echo ps_h($copy['stats']); ?></h2>
                <button type="button" class="ps-iconbtn" data-ps-close aria-label="<?php echo ps_h($copy['close']); ?>">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div data-ps-stats-body></div>
        </div>
    </div>

    <div class="ps-modal" data-ps-rules-modal hidden role="dialog" aria-modal="true" aria-label="<?php echo ps_h($copy['rules']); ?>">
        <div class="ps-modal__box">
            <div class="ps-modal__head">
                <h2><?php echo ps_h($copy['rules']); ?></h2>
                <button type="button" class="ps-iconbtn" data-ps-close aria-label="<?php echo ps_h($copy['close']); ?>">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <ul class="ps-rules">
                <?php foreach ($copy['rulesList'] as $rule): ?>
                    <li><?php echo ps_h($rule); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php include $isEn ? '../includes/footer-en.php' : '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
