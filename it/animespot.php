<?php

/**
 * Cripsum™ — Animespot
 *
 * Songspot con le sigle degli anime: parte un frammento di opening o ending e
 * si deve indovinare di quale serie è. Il frammento si allunga a ogni errore e
 * chi indovina presto prende più punti. Cinque difficoltà, dai temi che sanno
 * tutti a quelli che non ha sentito nessuno, e nessun limite: finita una sigla
 * ne parte un'altra.
 *
 * Stesso file su /it/animespot e /en/animespot, la lingua viene dall'indirizzo.
 */

require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/animespot_helpers.php';

checkBan($mysqli);

$lang = str_contains((string)($_SERVER['REQUEST_URI'] ?? ''), '/en/') ? 'en' : 'it';
$isEn = $lang === 'en';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = $isEn
        ? 'You need to be logged in to play Animespot.'
        : 'Per giocare ad Animespot devi essere loggato.';

    header('Location: accedi');
    exit();
}

function as_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Il catalogo può non esserci: le migration di questo progetto si applicano a
// mano e il catalogo lo riempie un import a parte. In quel caso la pagina resta
// in piedi e dice che cosa manca, invece di girare a vuoto.
$ready = animespot_catalog_ready($mysqli);

$copy = [
    'it' => [
        'attempts'   => 'Tentativi',
        'newTrack'   => 'Nuova sigla',
        'difficulty' => 'Difficoltà',
        'era'        => 'Epoca',
        'clips'      => 'Frammenti',
        'listen'     => 'Ascolto',
        'searchMode' => 'Ricerca',
        'volume'     => 'Volume',
        'help'       => 'Aiuto',
        'rules'      => 'Come si gioca',
        'stats'      => 'Statistiche',
        'board'      => 'Classifica',
        'loading'    => 'Sto cercando una sigla…',
        'retry'      => 'Riprova',
        'play'       => 'Ascolta il frammento',
        'clear'      => 'Cancella',
        'close'      => 'Chiudi',
        'search'     => 'Di che anime è?',
        'skip'       => 'Salta',
        'clipsHint'  => 'Spegni i frammenti che non vuoi. L\'ultimo resta sempre acceso.',
        'credit'     => 'Sigle e video da <a href="https://animethemes.moe" target="_blank" rel="noopener noreferrer">AnimeThemes</a>, notorietà e titoli da <a href="https://kitsu.io" target="_blank" rel="noopener noreferrer">Kitsu</a>.',
        'missing'    => 'Il catalogo delle sigle non è ancora stato importato: mancano le tabelle <code>animespot_*</code> o sono vuote.',
        'rulesList'  => [
            'Parte l\'inizio di una sigla — opening o ending — e devi capire di quale anime è.',
            'Hai cinque tentativi. Ogni errore o salto allunga il frammento: 0,1s → 0,5s → 2s → 8s → 15s.',
            'Scrivi nel campo di ricerca e scegli una serie dall\'elenco: cerca fra titolo giapponese, inglese, alternativi e anche fra i titoli delle canzoni.',
            'Una serie già provata sparisce dall\'elenco: non la puoi rigiocare.',
            'Prima indovini, più punti prendi: 1200, 975, 750, 525, 300.',
            'Si gioca a serie di cinque: una sigla facile, una media, una difficile, una da esperto e una impossibile, in quest\'ordine. Finita la quinta ne parte un\'altra serie.',
            'Le cinque difficoltà si possono girare a mano quando si vuole: tornando su una già chiusa si ritrova quella sigla lì, non una nuova.',
            'La difficoltà decide quanto è conosciuto l\'anime da cui esce la sigla, non le regole.',
            'L\'epoca restringe il mazzo a un decennio: i classici prima del Duemila, oppure gli anni 2000, 2010 o 2020.',
            'Ad anteprima il frammento non parte dall\'inizio ma da un punto in mezzo alla sigla: si perde l\'attacco, che è la parte che riconoscono tutti.',
            'Puoi spegnere i frammenti che non ti servono: partire da due secondi vale meno punti ma è più facile.',
            'Le sigle sono sempre a caso e non ci sono limiti: si gioca quanto si vuole.',
        ],
        'description' => 'Indovina l\'anime dalla sua sigla: cinque tentativi, un frammento più lungo a ogni errore, cinque difficoltà.',
    ],
    'en' => [
        'attempts'   => 'Guesses',
        'newTrack'   => 'New theme',
        'difficulty' => 'Difficulty',
        'era'        => 'Era',
        'clips'      => 'Clips',
        'listen'     => 'Playback',
        'searchMode' => 'Search',
        'volume'     => 'Volume',
        'help'       => 'Help',
        'rules'      => 'How to play',
        'stats'      => 'Statistics',
        'board'      => 'Leaderboard',
        'loading'    => 'Looking for a theme…',
        'retry'      => 'Try again',
        'play'       => 'Play the clip',
        'clear'      => 'Clear',
        'close'      => 'Close',
        'search'     => 'Name that anime',
        'skip'       => 'Skip',
        'clipsHint'  => 'Turn off the clips you do not want. The last one always stays on.',
        'credit'     => 'Themes and videos from <a href="https://animethemes.moe" target="_blank" rel="noopener noreferrer">AnimeThemes</a>, popularity and titles from <a href="https://kitsu.io" target="_blank" rel="noopener noreferrer">Kitsu</a>.',
        'missing'    => 'The theme catalogue has not been imported yet: the <code>animespot_*</code> tables are missing or empty.',
        'rulesList'  => [
            'You hear the beginning of a theme — an opening or an ending — and work out which anime it belongs to.',
            'You get five tries. Every wrong guess or skip makes the clip longer: 0.1s → 0.5s → 2s → 8s → 15s.',
            'Type in the search box and pick a series from the list: it searches Japanese, English and alternative titles, and song titles too.',
            'A series you already tried drops out of the list: you cannot spend a guess on it twice.',
            'The sooner you get it, the more you score: 1200, 975, 750, 525, 300.',
            'You play in sets of five: one easy theme, one medium, one hard, one expert and one impossible, in that order. After the fifth, another set begins.',
            'You can move between the five difficulties whenever you like: going back to one you already finished shows that theme again, not a new one.',
            'Difficulty changes how well known the anime behind the theme is, not the rules.',
            'The era narrows the pool to one decade: classics before 2000, or the 2000s, 2010s and 2020s.',
            'On preview playback the clip does not start at the beginning but somewhere in the middle of the theme: you lose the intro, which is the part everyone recognises.',
            'You can turn off the clips you do not need: starting at two seconds is worth fewer points but is easier.',
            'Themes are always random and there is no limit: play as much as you like.',
        ],
        'description' => 'Guess the anime from its opening or ending: five tries, a longer clip after every miss, five difficulties.',
    ],
][$lang];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">

<head>
    <?php $ogDescription = $copy['description']; ?>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ — Animespot</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#050706">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Animespot — Cripsum™">
    <meta property="og:description" content="<?php echo as_h($copy['description']); ?>">

    <link rel="stylesheet" href="<?php echo animespot_asset('/assets/animespot/animespot.css'); ?>">
    <script>
        window.ANIMESPOT_LANG = '<?php echo $lang; ?>';
        window.ANIMESPOT_CSRF = '<?php echo as_h(function_exists('csrf_token') ? csrf_token() : ''); ?>';
    </script>
    <script src="<?php echo animespot_asset('/assets/animespot/animespot.js'); ?>" defer></script>
</head>

<body class="as-page">
    <?php include '../includes/navbar.php'; ?>

    <div class="as-beam" aria-hidden="true"><span></span></div>
    <div class="as-glow" aria-hidden="true"></div>
    <canvas class="as-confetti" data-as-confetti aria-hidden="true"></canvas>

    <main class="as-stage" data-as-root>

        <div class="as-brand" aria-hidden="true">animespot</div>

        <?php if (!$ready): ?>
            <div class="as-error as-full">
                <p><?php echo $copy['missing']; ?></p>
            </div>
        <?php else: ?>

        <div class="as-boot as-full" data-as-boot>
            <div class="as-spinner" aria-hidden="true"></div>
            <p><?php echo as_h($copy['loading']); ?></p>
        </div>

        <div class="as-error as-full" data-as-error hidden>
            <p data-as-error-text></p>
            <button type="button" class="as-btn" onclick="window.location.reload()"><?php echo as_h($copy['retry']); ?></button>
        </div>

        <div class="as-game" data-as-game hidden>

            <!-- ══ Colonna sinistra: la storia della partita ══ -->
            <aside class="as-rail">
                <div>
                    <h2 class="as-group__title"><?php echo as_h($copy['attempts']); ?></h2>
                    <ol class="as-rows" data-as-rows></ol>
                    <button type="button" class="as-link" data-as-new>
                        <i class="fa-solid fa-rotate" aria-hidden="true"></i> <?php echo as_h($copy['newTrack']); ?>
                    </button>
                </div>

                <div>
                    <h2 class="as-group__title"><?php echo as_h($copy['difficulty']); ?></h2>
                    <div class="as-levels" data-as-levels></div>
                </div>

                <div>
                    <h2 class="as-group__title"><?php echo as_h($copy['era']); ?></h2>
                    <div class="as-eras" data-as-eras></div>
                </div>
            </aside>

            <!-- ══ Colonna centrale: il lettore, poi la rivelazione ══ -->
            <section class="as-center">

                <div class="as-meta">
                    <span data-as-meta-left></span>
                    <span class="as-meta__now" data-as-meta-right></span>
                </div>

                <div class="as-player" data-as-player>
                    <div class="as-bar">
                        <div class="as-segments" data-as-segments></div>
                        <div class="as-marks"><span class="as-marker" data-as-marker></span></div>
                    </div>

                    <div class="as-transport">
                        <button type="button" class="as-play" data-as-play aria-pressed="false"
                                aria-label="<?php echo as_h($copy['play']); ?>">
                            <i class="fa-solid fa-play" aria-hidden="true"></i>
                        </button>
                        <div class="as-clock">
                            <span class="as-clock__value" data-as-clock>—</span>
                            <span class="as-clock__label" data-as-clock-label></span>
                        </div>
                    </div>

                    <div class="as-guessrow" data-as-controls hidden>
                        <div class="as-search">
                            <i class="fa-solid fa-magnifying-glass as-search__icon" aria-hidden="true"></i>
                            <input type="text" class="as-input" data-as-input autocomplete="off" spellcheck="false"
                                   role="combobox" aria-expanded="false" aria-autocomplete="list"
                                   placeholder="<?php echo as_h($copy['search']); ?>">
                            <button type="button" class="as-clear" data-as-clear hidden
                                    aria-label="<?php echo as_h($copy['clear']); ?>">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                            <ul class="as-list" role="listbox" data-as-list hidden></ul>
                        </div>
                        <button type="button" class="as-skip" data-as-skip>
                            <i class="fa-solid fa-forward-step" aria-hidden="true"></i>
                            <span><?php echo as_h($copy['skip']); ?></span>
                            <small data-as-skip-bonus></small>
                        </button>
                    </div>
                </div>

                <div class="as-reveal" data-as-reveal hidden></div>
            </section>

            <!-- ══ Colonna destra: le opzioni ══ -->
            <aside class="as-rail">
                <div>
                    <h2 class="as-group__title"><?php echo as_h($copy['clips']); ?></h2>
                    <div class="as-chips" data-as-chips></div>
                    <p class="as-hint"><?php echo as_h($copy['clipsHint']); ?></p>
                </div>

                <div>
                    <h2 class="as-group__title"><?php echo as_h($copy['listen']); ?></h2>
                    <div class="as-switch" data-as-playback></div>
                </div>

                <div>
                    <h2 class="as-group__title"><?php echo as_h($copy['searchMode']); ?></h2>
                    <div class="as-switch" data-as-search></div>
                </div>

                <div>
                    <h2 class="as-group__title"><?php echo as_h($copy['volume']); ?></h2>
                    <input type="range" class="as-volume" data-as-volume min="0" max="100" step="1" value="80"
                           aria-label="<?php echo as_h($copy['volume']); ?>">
                </div>

                <div>
                    <h2 class="as-group__title"><?php echo as_h($copy['help']); ?></h2>
                    <div class="as-stack">
                        <button type="button" class="as-opt" data-as-open-rules>
                            <i class="fa-solid fa-circle-question" aria-hidden="true"></i> <?php echo as_h($copy['rules']); ?>
                        </button>
                        <button type="button" class="as-opt" data-as-open-stats>
                            <i class="fa-solid fa-chart-simple" aria-hidden="true"></i> <?php echo as_h($copy['stats']); ?>
                        </button>
                        <button type="button" class="as-opt" data-as-open-board>
                            <i class="fa-solid fa-ranking-star" aria-hidden="true"></i> <?php echo as_h($copy['board']); ?>
                        </button>
                    </div>
                </div>
            </aside>

            <p class="as-credit"><?php echo $copy['credit']; ?></p>
        </div>

        <?php endif; ?>

        <div class="as-toast" data-as-toast role="status" aria-live="polite"></div>
    </main>

    <div class="as-modal" data-as-stats-modal hidden role="dialog" aria-modal="true" aria-label="<?php echo as_h($copy['stats']); ?>">
        <div class="as-modal__box">
            <div class="as-modal__head">
                <h2><?php echo as_h($copy['stats']); ?></h2>
                <button type="button" class="as-iconbtn" data-as-close aria-label="<?php echo as_h($copy['close']); ?>">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div data-as-stats-body></div>
        </div>
    </div>

    <div class="as-modal" data-as-board-modal hidden role="dialog" aria-modal="true" aria-label="<?php echo as_h($copy['board']); ?>">
        <div class="as-modal__box as-modal__box--wide">
            <div class="as-modal__head">
                <h2><?php echo as_h($copy['board']); ?></h2>
                <button type="button" class="as-iconbtn" data-as-close aria-label="<?php echo as_h($copy['close']); ?>">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="as-switch as-switch--three" data-as-board-periods></div>
            <div data-as-board-body></div>
        </div>
    </div>

    <div class="as-modal" data-as-rules-modal hidden role="dialog" aria-modal="true" aria-label="<?php echo as_h($copy['rules']); ?>">
        <div class="as-modal__box">
            <div class="as-modal__head">
                <h2><?php echo as_h($copy['rules']); ?></h2>
                <button type="button" class="as-iconbtn" data-as-close aria-label="<?php echo as_h($copy['close']); ?>">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <ul class="as-rules">
                <?php foreach ($copy['rulesList'] as $rule): ?>
                    <li><?php echo as_h($rule); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php include $isEn ? '../includes/footer-en.php' : '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
