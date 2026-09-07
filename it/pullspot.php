<?php

/**
 * Cripsum™ — Pullspot
 *
 * Songspot con le musiche delle animazioni di pull: si sente un frammento che
 * si allunga a ogni errore e si deve indovinare di quale personaggio è.
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
        'tagline'    => 'Indovina il personaggio dalla musica del suo pull.',
        'daily'      => 'Giornaliero',
        'practice'   => 'Allenamento',
        'rules'      => 'Come si gioca',
        'stats'      => 'Statistiche',
        'loading'    => 'Sto cercando la traccia del giorno…',
        'retry'      => 'Riprova',
        'play'       => 'Ascolta il frammento',
        'clear'      => 'Cancella',
        'close'      => 'Chiudi',
        'search'     => 'Cerca un personaggio…',
        'rulesList'  => [
            'Parte l\'inizio della musica di pull di un personaggio: devi capire di chi è.',
            'Hai sei tentativi. Ogni errore o salto allunga il frammento: 0,1s → 0,5s → 2s → 5s → 8s → 15s.',
            'Puoi indovinare solo i nomi che compaiono nella ricerca: sono gli stessi fra cui viene scelta la risposta.',
            'Se due personaggi condividono la stessa musica valgono tutti e due.',
            'Un personaggio nuovo ogni giorno a mezzanotte, uguale per tutti. In allenamento invece è a caso e non conta.',
        ],
        'description' => 'Indovina il personaggio Cripsum dalla musica della sua animazione di pull: sei tentativi, un frammento più lungo a ogni errore.',
    ],
    'en' => [
        'tagline'    => 'Guess the character from their pull track.',
        'daily'      => 'Daily',
        'practice'   => 'Practice',
        'rules'      => 'How to play',
        'stats'      => 'Statistics',
        'loading'    => 'Looking for today\'s track…',
        'retry'      => 'Try again',
        'play'       => 'Play the snippet',
        'clear'      => 'Clear',
        'close'      => 'Close',
        'search'     => 'Search a character…',
        'rulesList'  => [
            'You hear the beginning of a character\'s pull track: work out whose it is.',
            'You get six tries. Every wrong guess or skip makes the snippet longer: 0.1s → 0.5s → 2s → 5s → 8s → 15s.',
            'You can only guess names from the search list: the answer is picked from that same list.',
            'If two characters share the same track, both of them count as right.',
            'A new character every day at midnight, the same for everyone. Practice rounds are random and do not count.',
        ],
        'description' => 'Guess the Cripsum character from their pull animation track: six tries, a longer snippet after every miss.',
    ],
][$lang];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ — Pullspot</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo ps_h($copy['description']); ?>">
    <meta name="theme-color" content="#05070d">
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

    <div class="ps-bg" aria-hidden="true"><span></span><span></span></div>

    <main class="ps-shell" data-ps-root>

        <header class="ps-head">
            <div>
                <h1 class="ps-head__title"><i class="fa-solid fa-headphones-simple"></i>Pullspot</h1>
                <p class="ps-head__sub" data-ps-subtitle><?php echo ps_h($copy['tagline']); ?></p>
            </div>
            <div class="ps-head__actions">
                <button type="button" class="ps-iconbtn" data-ps-open-rules
                        aria-label="<?php echo ps_h($copy['rules']); ?>" title="<?php echo ps_h($copy['rules']); ?>">
                    <i class="fa-solid fa-circle-question"></i>
                </button>
                <button type="button" class="ps-iconbtn" data-ps-open-stats
                        aria-label="<?php echo ps_h($copy['stats']); ?>" title="<?php echo ps_h($copy['stats']); ?>">
                    <i class="fa-solid fa-chart-simple"></i>
                </button>
            </div>
        </header>

        <div class="ps-tabs" role="tablist">
            <button type="button" class="ps-tab" role="tab" aria-selected="true" data-ps-tab="daily">
                <i class="fa-solid fa-calendar-day"></i> <?php echo ps_h($copy['daily']); ?>
            </button>
            <button type="button" class="ps-tab" role="tab" aria-selected="false" data-ps-tab="practice">
                <i class="fa-solid fa-dumbbell"></i> <?php echo ps_h($copy['practice']); ?>
            </button>
        </div>

        <div class="ps-boot" data-ps-boot>
            <div class="ps-spinner" aria-hidden="true"></div>
            <p><?php echo ps_h($copy['loading']); ?></p>
        </div>

        <div class="ps-error" data-ps-error hidden>
            <p data-ps-error-text></p>
            <button type="button" class="ps-btn" onclick="window.location.reload()"><?php echo ps_h($copy['retry']); ?></button>
        </div>

        <div data-ps-game hidden>

            <ol class="ps-rows" data-ps-rows></ol>

            <div class="ps-player">
                <div class="ps-track">
                    <div class="ps-track__unlocked" data-ps-unlocked></div>
                    <div class="ps-track__played" data-ps-played></div>
                </div>
                <div class="ps-marks" data-ps-marks></div>

                <div class="ps-transport">
                    <button type="button" class="ps-play" data-ps-play aria-pressed="false"
                            aria-label="<?php echo ps_h($copy['play']); ?>">
                        <i class="fa-solid fa-play"></i>
                    </button>
                    <span class="ps-time" data-ps-time>0<?php echo $isEn ? '.' : ','; ?>0s / 0<?php echo $isEn ? '.' : ','; ?>1s</span>
                </div>
            </div>

            <div data-ps-controls hidden>
                <div class="ps-search">
                    <ul class="ps-list" role="listbox" data-ps-list hidden></ul>
                    <input type="text" class="ps-input" data-ps-input autocomplete="off" spellcheck="false"
                           role="combobox" aria-expanded="false" aria-autocomplete="list"
                           placeholder="<?php echo ps_h($copy['search']); ?>">
                    <button type="button" class="ps-clear" data-ps-clear hidden
                            aria-label="<?php echo ps_h($copy['clear']); ?>">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="ps-actions" style="margin-top:.5rem">
                    <button type="button" class="ps-btn" data-ps-skip></button>
                    <button type="button" class="ps-btn ps-btn--primary" data-ps-submit disabled></button>
                </div>
            </div>

            <div class="ps-result" data-ps-result hidden></div>
        </div>

        <div class="ps-toast" data-ps-toast role="status" aria-live="polite"></div>
    </main>

    <div class="ps-modal" data-ps-stats-modal hidden role="dialog" aria-modal="true" aria-label="<?php echo ps_h($copy['stats']); ?>">
        <div class="ps-modal__box">
            <div class="ps-modal__head">
                <h2><?php echo ps_h($copy['stats']); ?></h2>
                <button type="button" class="ps-iconbtn" data-ps-close aria-label="<?php echo ps_h($copy['close']); ?>">
                    <i class="fa-solid fa-xmark"></i>
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
                    <i class="fa-solid fa-xmark"></i>
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
