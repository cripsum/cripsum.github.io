<?php
/**
 * Pagina degli achievement, una sola per le due lingue.
 *
 * it/achievements.php ed en/achievements.php impostano $achLang e includono
 * questo file. I dati arrivano da ach_overview() già dentro l'HTML: la pagina
 * si apre senza altre richieste, e assets/achievements/achievements.js li
 * richiede di nuovo solo dopo uno sblocco o un premio incassato.
 *
 * Attenzione ai nomi: head-import.php e la navbar usano $t e $lang, qui
 * tutto comincia per $ach.
 */
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../theme.php';
require_once __DIR__ . '/../achievements.php';

$achLang = ($achLang ?? 'it') === 'en' ? 'en' : 'it';
$achEn = $achLang === 'en';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = $achEn
        ? 'You must be logged in to view achievements.'
        : 'Per accedere agli achievement devi essere loggato';

    header('Location: accedi');
    exit();
}

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

checkBan($mysqli);

$achUserId = (int)$_SESSION['user_id'];
$achData = ach_overview($mysqli, $achUserId, $achLang);
$achSum = $achData['summary'];
$achRank = $achSum['rank'];
$achPercent = $achSum['total'] > 0 ? (int)round($achSum['unlocked'] / $achSum['total'] * 100) : 0;

$achH = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$achN = static fn(int $value): string => number_format($value, 0, $achEn ? '.' : ',', $achEn ? ',' : '.');

$achS = $achEn ? [
    'title'        => 'Achievements',
    'subtitle'     => 'What you unlocked, what is left, and what is still waiting to be claimed.',
    'rank'         => 'Rank',
    'rank_next'    => '%s points to %s',
    'rank_max'     => 'Highest rank reached',
    'completion'   => 'Completion',
    'of'           => '%s of %s',
    'unlocked'     => 'Unlocked',
    'points'       => 'Points',
    'to_claim'     => 'To claim',
    'claim_all'    => 'Claim all',
    'near'         => 'Almost there',
    'categories'   => 'Categories',
    'all'          => 'All',
    'search'       => 'Search achievements…',
    'status'       => 'Status',
    'st_all'       => 'All',
    'st_unlocked'  => 'Unlocked',
    'st_locked'    => 'Locked',
    'st_claim'     => 'To claim',
    'sort'         => 'Sort achievements',
    'so_default'   => 'Default order',
    'so_closest'   => 'Closest to unlock',
    'so_rarest'    => 'Rarest first',
    'so_recent'    => 'Most recent',
    'so_points'    => 'Most points',
    'so_name'      => 'Name (A–Z)',
    'view'         => 'View',
    'view_grid'    => 'Grid',
    'view_time'    => 'Timeline',
    'empty_title'  => 'No achievements found',
    'empty_text'   => 'Try another search or filter.',
    'tracking'     => 'You turned off statistics: achievements about time, days and streaks cannot move forward.',
    'tracking_cta' => 'Open settings',
    'noscript'     => 'This page needs JavaScript to show your achievements.',
    'close'        => 'Close',
    'og'           => 'Your achievements on Cripsum™.',
] : [
    'title'        => 'Achievement',
    'subtitle'     => 'Cosa hai sbloccato, cosa ti manca e cosa aspetta ancora di essere riscosso.',
    'rank'         => 'Grado',
    'rank_next'    => '%s punti a %s',
    'rank_max'     => 'Grado massimo raggiunto',
    'completion'   => 'Completamento',
    'of'           => '%s su %s',
    'unlocked'     => 'Sbloccati',
    'points'       => 'Punti',
    'to_claim'     => 'Da riscuotere',
    'claim_all'    => 'Riscuoti tutto',
    'near'         => 'Quasi fatti',
    'categories'   => 'Categorie',
    'all'          => 'Tutti',
    'search'       => 'Cerca achievement…',
    'status'       => 'Stato',
    'st_all'       => 'Tutti',
    'st_unlocked'  => 'Sbloccati',
    'st_locked'    => 'Da sbloccare',
    'st_claim'     => 'Da riscuotere',
    'sort'         => 'Ordina achievement',
    'so_default'   => 'Ordine predefinito',
    'so_closest'   => 'I più vicini',
    'so_rarest'    => 'I più rari',
    'so_recent'    => 'I più recenti',
    'so_points'    => 'Più punti',
    'so_name'      => 'Nome (A–Z)',
    'view'         => 'Vista',
    'view_grid'    => 'Griglia',
    'view_time'    => 'Cronologia',
    'empty_title'  => 'Nessun achievement trovato',
    'empty_text'   => 'Cambia ricerca o filtro.',
    'tracking'     => 'Hai disattivato le statistiche: gli achievement su tempo, giorni e serie di giorni non possono avanzare.',
    'tracking_cta' => 'Apri le impostazioni',
    'noscript'     => 'Questa pagina ha bisogno di JavaScript per mostrare i tuoi achievement.',
    'close'        => 'Chiudi',
    'og'           => 'I tuoi achievement su Cripsum™.',
];

$ogTitle = $achS['title'] . ' - Cripsum™';
$ogDescription = $achS['og'];
$ogImage = '/img/achievement-default.png';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/' . $achLang . '/achievements'), '#');

$achCssVer = @filemtime(__DIR__ . '/../../assets/achievements/achievements.css') ?: 3;
$achJsVer = @filemtime(__DIR__ . '/../../assets/achievements/achievements.js') ?: 3;
?>
<!DOCTYPE html>
<html lang="<?= $achLang ?>"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php include __DIR__ . '/../head-import.php'; ?>
    <title>Cripsum™ - <?= $achH($achS['title']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <link rel="stylesheet" href="/assets/achievements/achievements.css?v=<?= $achCssVer ?>">
    <?php cripsum_theme_head(); ?>
    <script src="/assets/achievements/achievements.js?v=<?= $achJsVer ?>" defer></script>
</head>

<?php // Senza la migration v3 non ci sono categorie né livelli veri: la classe nasconde linguette ed etichette. ?>
<body class="ach-page<?= $achData['v3'] ? '' : ' ach-legacy' ?>">
    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="ach-bg" aria-hidden="true">
        <span class="ach-orb ach-orb--one"></span>
        <span class="ach-orb ach-orb--two"></span>
        <span class="ach-grid-bg"></span>
    </div>

    <main class="ach-shell" id="achApp">
        <header class="ach-hero">
            <div class="ach-hero__main">
                <h1><?= $achH($achS['title']) ?></h1>
                <p class="ach-hero__sub"><?= $achH($achS['subtitle']) ?></p>

                <div class="ach-rank" data-rank="<?= (int)$achRank['index'] ?>">
                    <span class="ach-rank__icon" aria-hidden="true"><i class="fa-solid fa-ranking-star"></i></span>
                    <div class="ach-rank__body">
                        <div class="ach-rank__line">
                            <span class="ach-rank__label"><?= $achH($achS['rank']) ?></span>
                            <strong class="ach-rank__name" id="achRankName"><?= $achH($achRank['name']) ?></strong>
                        </div>
                        <div class="ach-rank__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)round($achRank['progress'] * 100) ?>" aria-label="<?= $achH($achS['rank']) ?>">
                            <span id="achRankFill" style="--p: <?= $achH($achRank['progress']) ?>"></span>
                        </div>
                        <small class="ach-rank__next" id="achRankNext">
                            <?php if ($achRank['next']): ?>
                                <?= $achH(sprintf($achS['rank_next'], $achN((int)$achRank['next']['min'] - (int)$achSum['points']), $achRank['next']['name'])) ?>
                            <?php else: ?>
                                <?= $achH($achS['rank_max']) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>

            <div class="ach-ring" aria-label="<?= $achH($achS['completion']) ?>">
                <svg class="ach-ring__svg" viewBox="0 0 140 140" aria-hidden="true">
                    <defs>
                        <linearGradient id="achRingGradient" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#5b8cff" />
                            <stop offset="1" stop-color="#a78bfa" />
                        </linearGradient>
                    </defs>
                    <circle class="ach-ring__track" cx="70" cy="70" r="60"></circle>
                    <circle class="ach-ring__fill" id="achRingFill" cx="70" cy="70" r="60" pathLength="100" style="--p: <?= $achPercent ?>"></circle>
                </svg>
                <div class="ach-ring__text">
                    <strong><span id="achPercent" data-value="<?= $achPercent ?>"><?= $achPercent ?></span>%</strong>
                    <span id="achCount"><?= $achH(sprintf($achS['of'], $achN((int)$achSum['unlocked']), $achN((int)$achSum['total']))) ?></span>
                </div>
            </div>

            <dl class="ach-stats">
                <div class="ach-stat">
                    <dt><i class="fa-solid fa-unlock" aria-hidden="true"></i> <?= $achH($achS['unlocked']) ?></dt>
                    <?php // statUnlocked e statTotal sono i nomi della vecchia pagina: li legge chi mostra i progressi fuori dal sito (PreMiD). ?>
                    <dd><span id="achStatUnlocked" class="statUnlocked"><?= $achN((int)$achSum['unlocked']) ?></span><small>/ <span class="statTotal"><?= $achN((int)$achSum['total']) ?></span></small></dd>
                </div>
                <div class="ach-stat">
                    <dt><i class="fa-solid fa-star" aria-hidden="true"></i> <?= $achH($achS['points']) ?></dt>
                    <dd><span id="achStatPoints"><?= $achN((int)$achSum['points']) ?></span><small>/ <?= $achN((int)$achSum['points_total']) ?></small></dd>
                </div>
                <?php if ($achData['can_claim']): ?>
                    <div class="ach-stat ach-stat--claim<?= $achSum['claimable'] > 0 ? ' has-claim' : '' ?>" id="achClaimStat">
                        <dt><img src="/img/godos.png" alt="" width="16" height="16"> <?= $achH($achS['to_claim']) ?></dt>
                        <dd>
                            <span id="achStatGodos"><?= $achN((int)$achSum['claimable_godos']) ?></span><small>Godos</small>
                            <button type="button" class="ach-btn ach-btn--gold" id="achClaimAll" <?= $achSum['claimable'] > 0 ? '' : 'hidden' ?>>
                                <i class="fa-solid fa-coins" aria-hidden="true"></i> <?= $achH($achS['claim_all']) ?>
                            </button>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </header>

        <?php if (!$achData['tracking']): ?>
            <p class="ach-note">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span><?= $achH($achS['tracking']) ?></span>
                <a href="/<?= $achLang ?>/impostazioni"><?= $achH($achS['tracking_cta']) ?></a>
            </p>
        <?php endif; ?>

        <section class="ach-near" id="achNear" aria-labelledby="achNearTitle" hidden>
            <h2 id="achNearTitle"><i class="fa-solid fa-bolt" aria-hidden="true"></i> <?= $achH($achS['near']) ?></h2>
            <div class="ach-near__list" id="achNearList"></div>
        </section>

        <section class="ach-controls" aria-label="<?= $achH($achS['categories']) ?>">
            <div class="ach-tabs" id="achTabs" role="tablist" aria-label="<?= $achH($achS['categories']) ?>">
                <button type="button" class="ach-tab is-active" role="tab" aria-selected="true" data-category="all">
                    <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                    <span><?= $achH($achS['all']) ?></span>
                    <small><?= (int)$achSum['unlocked'] ?>/<?= (int)$achSum['total'] ?></small>
                </button>
                <?php foreach ($achData['categories'] as $achCategory): ?>
                    <button type="button" class="ach-tab" role="tab" aria-selected="false" data-category="<?= $achH($achCategory['key']) ?>">
                        <i class="fa-solid <?= $achH($achCategory['icon']) ?>" aria-hidden="true"></i>
                        <span><?= $achH($achCategory['name']) ?></span>
                        <small><?= (int)$achCategory['unlocked'] ?>/<?= (int)$achCategory['total'] ?></small>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="ach-toolbar">
                <strong class="ach-toolbar__title"><i class="fa-solid fa-timeline" aria-hidden="true"></i> <?= $achH($achS['view_time']) ?></strong>
                <label class="ach-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" class="ach-search__input" id="achSearch" placeholder="<?= $achH($achS['search']) ?>" aria-label="<?= $achH($achS['search']) ?>" autocomplete="off" spellcheck="false">
                </label>

                <div class="ach-chips" role="group" aria-label="<?= $achH($achS['status']) ?>">
                    <button type="button" class="ach-chip is-active" data-status="all" aria-pressed="true"><?= $achH($achS['st_all']) ?></button>
                    <button type="button" class="ach-chip" data-status="unlocked" aria-pressed="false"><?= $achH($achS['st_unlocked']) ?></button>
                    <button type="button" class="ach-chip" data-status="locked" aria-pressed="false"><?= $achH($achS['st_locked']) ?></button>
                    <?php if ($achData['can_claim']): ?>
                        <button type="button" class="ach-chip ach-chip--gold" data-status="claimable" aria-pressed="false"><?= $achH($achS['st_claim']) ?></button>
                    <?php endif; ?>
                </div>

                <?php
                // «Ordina» è un listbox fatto a mano, come nella pagina degli
                // edit: niente <select> del browser.
                $achSorts = [
                    'default' => ['fa-solid fa-list-ol', $achS['so_default']],
                    'closest' => ['fa-solid fa-bullseye', $achS['so_closest']],
                    'rarest'  => ['fa-solid fa-gem', $achS['so_rarest']],
                    'recent'  => ['fa-solid fa-clock-rotate-left', $achS['so_recent']],
                    'points'  => ['fa-solid fa-star', $achS['so_points']],
                    'name'    => ['fa-solid fa-arrow-down-a-z', $achS['so_name']],
                ];
                ?>
                <div class="ach-sort" data-ach-sort>
                    <span class="ach-sort__label" id="achSortLabel"><?= $achH($achS['sort']) ?></span>
                    <button type="button" class="ach-sort__button" id="achSortButton" aria-haspopup="listbox" aria-expanded="false" aria-controls="achSortMenu" aria-labelledby="achSortLabel achSortButton">
                        <i class="fa-solid fa-arrow-down-wide-short" aria-hidden="true"></i>
                        <span data-sort-current><?= $achH($achS['so_default']) ?></span>
                        <i class="fa-solid fa-chevron-down ach-sort__chevron" aria-hidden="true"></i>
                    </button>
                    <ul class="ach-sort__menu" id="achSortMenu" role="listbox" tabindex="-1" aria-labelledby="achSortLabel">
                        <?php $achSortIndex = 0; ?>
                        <?php foreach ($achSorts as $achSortValue => [$achSortIcon, $achSortName]): ?>
                            <li class="ach-sort__option" id="achSort-<?= $achSortValue ?>" role="option" data-value="<?= $achSortValue ?>" aria-selected="<?= $achSortValue === 'default' ? 'true' : 'false' ?>" style="--i: <?= $achSortIndex++ ?>">
                                <i class="<?= $achSortIcon ?>" aria-hidden="true"></i>
                                <span><?= $achH($achSortName) ?></span>
                                <i class="fa-solid fa-check ach-sort__check" aria-hidden="true"></i>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="ach-view" role="group" aria-label="<?= $achH($achS['view']) ?>">
                    <button type="button" class="ach-view__btn is-active" data-view="grid" aria-pressed="true" title="<?= $achH($achS['view_grid']) ?>" aria-label="<?= $achH($achS['view_grid']) ?>">
                        <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="ach-view__btn" data-view="timeline" aria-pressed="false" title="<?= $achH($achS['view_time']) ?>" aria-label="<?= $achH($achS['view_time']) ?>">
                        <i class="fa-solid fa-timeline" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </section>

        <noscript>
            <p class="ach-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> <span><?= $achH($achS['noscript']) ?></span></p>
        </noscript>

        <section class="ach-grid" id="achGrid" aria-live="polite"></section>
        <section class="ach-timeline" id="achTimeline" hidden></section>

        <section class="ach-empty" id="achEmpty" hidden>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <strong><?= $achH($achS['empty_title']) ?></strong>
            <span><?= $achH($achS['empty_text']) ?></span>
        </section>
    </main>

    <dialog class="ach-dialog" id="achDialog" aria-labelledby="achDialogTitle">
        <button type="button" class="ach-dialog__close" data-ach-close aria-label="<?= $achH($achS['close']) ?>">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="ach-dialog__body" id="achDialogBody"></div>
    </dialog>

    <div class="ach-toast" id="achToast" role="status" aria-live="polite"></div>

    <?php // JSON_HEX_TAG: i nomi degli achievement li scrive lo staff, ma un «</script>» qui dentro chiuderebbe il blocco. ?>
    <script type="application/json" id="achData"><?= json_encode($achData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <?php include __DIR__ . '/../scroll_indicator.php'; ?>
    <?php include __DIR__ . '/../' . ($achEn ? 'footer-en.php' : 'footer.php'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
