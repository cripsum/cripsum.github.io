<?php

/**
 * Duelli 2.0 — l'arena (tavole 3, 4 e 9), una pagina sola per IT ed EN.
 *
 * Si include da it/duelli/arena.php ed en/duelli/arena.php con $duelLang.
 * Indirizzo: /it/duelli/arena?m=<partita>; senza partita si torna alla
 * lobby (/it/duelli/).
 *
 * Il replay (/it/duelli/replay?m=<partita>, $duelReplay) e' questa stessa
 * pagina: l'arena rigioca la partita finita con le sue animazioni, con la
 * barra dei turni al posto delle mosse (tavola 6).
 *
 * A schermo pieno: niente navbar e footer del sito, solo la barra della
 * partita. Il disegno e le animazioni stanno in assets/duel/arena.css e
 * arena.js; i dati arrivano dagli endpoint di api/duel/ (includes/duel/api.php).
 *
 * Finche' i duelli nuovi non sono aperti a tutti (game_settings.public) la
 * pagina la vede solo lo staff; agli altri dice che arrivano presto.
 */

require_once __DIR__ . '/../../../config/session_init.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../theme.php';
require_once __DIR__ . '/../pages.php';

$dLang = ($duelLang ?? 'it') === 'en' ? 'en' : 'it';
$dEn = $dLang === 'en';
$dReplay = !empty($duelReplay);
$_SERVER['HTTP_X_CRIPSUM_LANG'] = $dLang;

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = $dEn ? 'You need to be logged in to play' : 'Per giocare devi essere loggato';
    header('Location: ../accedi');
    exit();
}

$dUserId = (int)$_SESSION['user_id'];
$dReady = duel_db_ready($mysqli);
$dOpen = $dReady && duel_open_for($mysqli);
$dMatch = max(0, (int)($_GET['m'] ?? 0));
$dUrls = duel_urls($dLang);
if ($dOpen && !$dMatch) {
    header('Location: ' . $dUrls['lobby']);
    exit();
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$dT = static fn(string $it, string $en) => $dEn ? $en : $it;

// Per il browser: nomi e descrizioni degli stati e dei terreni (con i valori
// del pannello), colori delle rarita' della lootbox, regole che servono.
duel_use_current_values($mysqli);
$dStatuses = [];
foreach (duel_statuses() as $key => $s) {
    $dStatuses[$key] = ['n' => $s[$dLang], 'd' => $s['d_' . $dLang], 'fam' => $s['fam'], 'neg' => (bool)$s['neg'],
        'cc' => (bool)$s['cc'], 'v' => $s['v'], 't' => $s['t'], 'max' => $s['max']];
}
$dTerrains = [];
foreach (duel_terrains() as $key => $tr) {
    $dTerrains[$key] = ['n' => $tr[$dLang], 'd' => $tr['d_' . $dLang]];
}
$dRarities = [];
$gachaColors = function_exists('gacha_rarity_defs') ? gacha_rarity_defs() : [];
foreach (array_keys(duel_rarities()) as $key) {
    $base = $key === 'segreto_limitato' ? 'segreto' : $key;
    $dRarities[$key] = [
        'n' => $key === 'segreto_limitato' ? $dT('Segreto limitato', 'Limited secret') : ($gachaColors[$base][$dLang] ?? ucfirst($base)),
        'c' => $gachaColors[$base]['color'] ?? '#9ca3af',
    ];
}
$dConfig = [
    'lang' => $dLang,
    'me' => $dUserId,
    'match' => $dMatch,
    'csrf' => csrf_token(),
    'api' => '/api/duel/',
    'rt' => '/api/rt/poll.php',
    'self' => $dUrls['arena'],
    'lobby' => $dUrls['lobby'],
    'urls' => $dUrls,
    'replay' => $dReplay,
    'music' => '/audio/ultimates/',
    // Le musiche delle ultimate che esistono (audio/ultimates/<id>.mp3): le altre suonano default.mp3.
    'tracks' => array_values(array_filter(array_map(static fn($f) => (int)basename($f, '.mp3'),
        glob(__DIR__ . '/../../../audio/ultimates/*.mp3') ?: []))),
    'staff' => duel_is_staff(),
    'statuses' => $dStatuses,
    'terrains' => $dTerrains,
    'rarities' => $dRarities,
    'roles' => DUEL_ROLES,
    'rules' => ['ps_max' => duel_rules()['ps_max'], 'charge_max' => duel_rules()['charge_max'], 'budget' => duel_rules()['ranked_budget']],
];
?>
<!DOCTYPE html>
<html lang="<?= $dLang ?>"<?= cripsum_theme_html_attr() ?>>
<head>
    <?php include __DIR__ . '/../../head-import.php'; ?>
    <title><?= $h($dReplay ? $dT('Replay · Cripsum™', 'Replay · Cripsum™') : $dT('Duello · Cripsum™', 'Duel · Cripsum™')) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0a0a0b">
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/assets/duel/arena.css')) ?>">
    <?php cripsum_theme_head(); ?>
</head>
<body class="d2<?= $dReplay ? ' d2-replay' : '' ?>" data-lang="<?= $dLang ?>">
<?php if (!$dOpen): ?>
    <main class="d2-closed">
        <h1><?= $h($dT('I nuovi duelli arrivano presto', 'The new duels are coming soon')) ?></h1>
        <p><?= $h($dReady
            ? $dT('Per ora li sta provando lo staff. Intanto si gioca con i duelli di sempre.', 'The staff is testing them. Meanwhile, play the usual duels.')
            : $dT('Non sono ancora installati su questo sito.', 'They are not installed on this site yet.')) ?></p>
        <a class="d2-btn d2-btn-w" href="<?= $h($dUrls['classic']) ?>"><?= $h($dT('Vai ai duelli', 'Go to duels')) ?></a>
    </main>
<?php else: ?>
    <a class="d2-skip" href="#d2-moves"><?= $h($dT('Vai alle mosse', 'Skip to moves')) ?></a>

    <div class="d2-app" id="d2-app" hidden>
        <header class="d2-top">
            <a class="d2-ibtn" id="d2-back" href="<?= $h($dConfig['lobby']) ?>" aria-label="<?= $h($dT('Esci dall\'arena', 'Leave the arena')) ?>"><i data-i="indietro"></i></a>
            <div class="d2-mode"><b id="d2-mode"></b> <small id="d2-turn"></small></div>
            <span class="d2-sp"></span>
            <div class="d2-timer" id="d2-timer" hidden role="timer" aria-live="off"><span class="d2-ring"></span><span class="num" id="d2-timer-n">0:45</span></div>
            <span class="d2-sp"></span>
            <span class="d2-pill d2-hide-m" id="d2-spect" hidden><i data-i="occhio"></i> <span></span></span>
            <button class="d2-ibtn d2-hide-m" id="d2-sound" type="button" aria-pressed="true" aria-label="<?= $h($dT('Suoni', 'Sounds')) ?>"><i data-i="suono"></i></button>
            <button class="d2-ibtn" id="d2-menu-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="<?= $h($dT('Menu della partita', 'Match menu')) ?>"><i data-i="impostazioni"></i></button>
            <button class="d2-ibtn d2-m-only" id="d2-drawer-btn" type="button" aria-expanded="false" aria-controls="d2-drawer" aria-label="<?= $h($dT('Registro, chat e kit', 'Log, chat and kit')) ?>"><i data-i="log"></i><em class="d2-badge" hidden></em></button>
        </header>

        <div class="d2-main">
            <aside class="d2-tl" aria-label="<?= $h($dT('Ordine dei turni', 'Turn order')) ?>">
                <h4><?= $h($dT('Ordine', 'Order')) ?></h4>
                <ol id="d2-tl"></ol>
            </aside>

            <section class="d2-stage" id="d2-stage" aria-label="<?= $h($dT('Campo', 'Field')) ?>">
                <div class="d2-terrain" id="d2-terrain" hidden></div>
                <div class="d2-half d2-half-foe">
                    <div class="d2-side-h d2-foe" id="d2-head-foe"></div>
                    <div class="d2-row d2-row-foe" id="d2-row-foe"></div>
                </div>
                <div class="d2-cap" id="d2-cap"></div>
                <div class="d2-half d2-half-you">
                    <div class="d2-row d2-row-you" id="d2-row-you"></div>
                    <div class="d2-side-h" id="d2-head-you"></div>
                </div>
                <div class="d2-reacts" id="d2-reacts" aria-hidden="true"></div>
            </section>

            <aside class="d2-drawer" id="d2-drawer" aria-label="<?= $h($dT('Registro, chat e kit', 'Log, chat and kit')) ?>">
                <div class="d2-tabs" role="tablist">
                    <button type="button" role="tab" id="d2-tab-log" aria-selected="true" aria-controls="d2-pane-log"><i data-i="log"></i> <?= $h($dT('Partita', 'Match')) ?></button>
                    <button type="button" role="tab" id="d2-tab-chat" aria-selected="false" aria-controls="d2-pane-chat"><i data-i="chat"></i> <span id="d2-tab-chat-l"><?= $h($dT('Chat', 'Chat')) ?></span> <em class="d2-badge" hidden></em></button>
                    <button type="button" role="tab" id="d2-tab-kit" aria-selected="false" aria-controls="d2-pane-kit"><i data-i="abilita"></i> Kit</button>
                    <button type="button" class="d2-drawer-x d2-m-only" id="d2-drawer-x" aria-label="<?= $h($dT('Chiudi', 'Close')) ?>"><i data-i="x"></i></button>
                </div>
                <div class="d2-pane" id="d2-pane-log" role="tabpanel" aria-labelledby="d2-tab-log"><ol class="d2-log" id="d2-log" aria-live="polite"></ol></div>
                <div class="d2-pane" id="d2-pane-chat" role="tabpanel" aria-labelledby="d2-tab-chat" hidden>
                    <ol class="d2-chat" id="d2-chat"></ol>
                    <form class="d2-chat-f" id="d2-chat-f" autocomplete="off">
                        <input id="d2-chat-in" maxlength="220" placeholder="<?= $h($dT('Scrivi all\'avversario', 'Message your opponent')) ?>" aria-label="<?= $h($dT('Messaggio', 'Message')) ?>">
                        <button class="d2-btn d2-btn-sm" type="submit"><?= $h($dT('Invia', 'Send')) ?></button>
                    </form>
                    <div class="d2-react-bar" id="d2-react-bar" hidden></div>
                </div>
                <div class="d2-pane" id="d2-pane-kit" role="tabpanel" aria-labelledby="d2-tab-kit" hidden><div id="d2-kit"></div></div>
            </aside>
        </div>

        <footer class="d2-dock" id="d2-dock"<?= $dReplay ? ' hidden' : '' ?>>
            <div class="d2-psbox">
                <small><?= $h($dT('Punti squadra', 'Team points')) ?></small>
                <span class="d2-ps" id="d2-ps" role="img"></span>
                <p><?= $h($dT('Attacco e Guardia ne danno 1, le abilità li spendono.', 'Attack and Guard give 1, skills spend them.')) ?></p>
            </div>
            <div class="d2-moves" id="d2-moves" role="group" aria-label="<?= $h($dT('Mosse', 'Moves')) ?>" tabindex="-1"></div>
            <div class="d2-prev" id="d2-prev" aria-live="polite"></div>
        </footer>
        <footer class="d2-rbar" id="d2-rbar" hidden aria-label="Replay"></footer>
    </div>

    <div class="d2-wait" id="d2-wait" hidden></div>
    <div class="d2-layer" id="d2-layer" aria-hidden="true"></div>
    <div class="d2-end" id="d2-end" hidden role="dialog" aria-modal="true" aria-labelledby="d2-end-t"></div>
    <div class="d2-menu" id="d2-menu" hidden role="menu"></div>
    <div class="d2-pop" id="d2-pop" hidden role="tooltip"></div>
    <div class="d2-sr" id="d2-say" aria-live="assertive"></div>
    <div class="d2-loading" id="d2-loading"><span></span><?= $h($dReplay ? $dT('Carico il replay…', 'Loading the replay…') : $dT('Carico la partita…', 'Loading the match…')) ?></div>

    <script id="d2-config" type="application/json"><?= json_encode($dConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/icons.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/arena.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
