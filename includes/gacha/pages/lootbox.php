<?php

/**
 * Pagina della lootbox, una sola per IT ed EN.
 *
 * Si include da it/lootbox.php ed en/lootbox.php con $gachaLang impostata.
 * I banner arrivano da gacha_lootbox_state() (motore in includes/gacha/):
 * qui si disegnano e basta.
 *
 * - Schermata iniziale: stili in css/lootbox-v3.css, comportamento in
 *   js/lootbox-ui.js (cambio banner, arte, conti alla rovescia, premium).
 * - Pop-up (impostazioni, classifica, cronologia, dettagli, valute): sono
 *   <dialog> gestiti da js/lootbox-modal.js, stili in css/lootbox-modal.css.
 * - La pull (overlay, carta, video, effetti, riepilogo) e' identica a prima
 *   e la guida js/gacha.js: gli id e i data- che legge restano quelli.
 *
 * Attenzione ai nomi: head-import.php e la navbar sovrascrivono $t, $lang,
 * $ruolo e $userId. Le variabili della pagina usano il prefisso $g.
 */

require_once __DIR__ . '/../../../config/session_init.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../mission_generator.php';
require_once __DIR__ . '/../public.php';

$gLang = ($gachaLang ?? 'it') === 'en' ? 'en' : 'it';
$gEn = $gLang === 'en';

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = $gEn ? 'You need to be logged in to access the Lootboxes' : 'Devi essere loggato per accedere alle Lootbox';
    header('Location: accedi');
    exit();
}
checkPermissions($mysqli, 'utente');

$gUserId = (int)$_SESSION['user_id'];
$gRole = $_SESSION['ruolo'] ?? 'utente';
$gIsAdmin = in_array($gRole, ['admin', 'owner'], true);

$stmt = $mysqli->prepare('SELECT username, soldi, godoshards_balance, is_premium, last_premium_claim FROM utenti WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $gUserId);
$stmt->execute();
$gUser = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$gSoldi = (int)($gUser['soldi'] ?? 0);
$gShards = (int)($gUser['godoshards_balance'] ?? 0);
$gPremium = (int)($gUser['is_premium'] ?? 0) === 1;
$gLastClaim = $gUser['last_premium_claim'] ?? null;

$gState = gacha_lootbox_state($mysqli, $gUserId, $gLang);
$gBanners = $gState['banners'];
$gGps = (int)$gState['godos_per_shard'];

// Banner da aprire: ?banner=<chiave> (link dall'inventario o dalla posta),
// altrimenti lo standard come sempre.
$gActive = 'standard';
$gWanted = (string)($_GET['banner'] ?? '');
foreach ($gBanners as $b) {
    if ($gWanted !== '' && $b['key'] === $gWanted) {
        $gActive = $b['key'];
    }
}
if (!in_array($gActive, array_column($gBanners, 'key'), true)) {
    $gActive = $gBanners[0]['key'] ?? 'standard';
}

$G = $gEn ? [
    'kind_standard' => 'Always available',
    'kind_evento' => 'Limited banner',
    'kind_selezione' => 'Selection banner',
    'kind_principiante' => 'Beginner banner',
    'kind_soon' => 'Coming soon',
    'ends_in' => 'Ends in',
    'starts_in' => 'Starts in',
    'no_end' => 'No end date',
    'premium_only' => 'Premium only',
    'rateup' => 'Rate-up',
    'pool' => 'Pool',
    'pool_all' => '%d characters',
    'pool_cat' => 'Category “%s”',
    'pity' => 'Pity',
    'pity_shared' => 'Shared with the other limited banners',
    'pity_next' => '%s guaranteed on the next pull',
    'pity_soft' => 'Soft pity active · odds rising',
    'pity_within' => '%s guaranteed within <b>%d</b>',
    'tier_std' => 'Special or Secret',
    'tier_up' => '%s or higher',
    'guaranteed' => 'Guaranteed: the next %s is a rate-up',
    'free_today' => '%d free pull today',
    'free_today_n' => '%d free pulls today',
    'limit_total' => '%d / %d pulls used',
    'limit_day' => '%d / %d pulls today',
    'destiny' => 'Destiny',
    'destiny_none' => 'none',
    'destiny_help' => 'If you win a rate-up that is not your target %d times, the next one is your target.',
    'destiny_points' => 'Destiny %d/%d',
    'open1' => 'Open 1×', 'open10' => 'Open 10×',
    'or' => 'or',
    'free' => 'Free',
    'free_badge' => 'FREE',
    'free_sub' => 'costs nothing today',
    'buy_shards' => 'Buy Godo Shards',
    'details' => 'Details & rates',
    'soon_note' => 'Available from %s',
    'standard_name' => 'Standard Banner',
    'sidebar' => 'Banners',
    'group_always' => 'Always available', 'group_events' => 'Events', 'group_soon' => 'Coming soon',
    'all' => 'All',
    'card_free' => '%d free',
    'card_left' => '%d left',
    'premium' => 'Premium', 'premium_sub' => '500 Godos a day', 'premium_again' => 'Again in', 'claim' => 'Claim',
    'settings' => 'Settings', 'leaderboard' => 'Leaderboard', 'inventory' => 'Inventory', 'history' => 'History',
    'close_x' => 'Close',
    // Overlay della pull: come prima.
    'win5050' => '<i class="fa-solid fa-trophy"></i> 50/50 Won!',
    'loss5050' => 'Guaranteed activated for the next pull',
    'again' => '<i class="fa-solid fa-rotate-right"></i> Open Again',
    'close' => '<i class="fa-solid fa-xmark"></i> Close',
    'see_inventory' => '<i class="fa-solid fa-layer-group"></i> View Inventory',
    'tap_audio' => 'Tap for audio',
] : [
    'kind_standard' => 'Sempre disponibile',
    'kind_evento' => 'Banner evento',
    'kind_selezione' => 'Banner selezione',
    'kind_principiante' => 'Banner principiante',
    'kind_soon' => 'In arrivo',
    'ends_in' => 'Finisce tra',
    'starts_in' => 'Inizia tra',
    'no_end' => 'Senza scadenza',
    'premium_only' => 'Solo Premium',
    'rateup' => 'Rate-up',
    'pool' => 'Pool',
    'pool_all' => '%d personaggi',
    'pool_cat' => 'Categoria «%s»',
    'pity' => 'Pity',
    'pity_shared' => 'Condiviso con gli altri banner evento',
    'pity_next' => '%s garantito alla prossima',
    'pity_soft' => 'Soft pity attivo · probabilità in salita',
    'pity_within' => '%s garantito entro <b>%d</b>',
    'tier_std' => 'Speciale o Segreto',
    'tier_up' => '%s o superiore',
    'guaranteed' => 'Garantito: il prossimo %s è un rate-up',
    'free_today' => '%d pull gratis oggi',
    'free_today_n' => '%d pull gratis oggi',
    'limit_total' => '%d / %d pull usate',
    'limit_day' => '%d / %d pull oggi',
    'destiny' => 'Destino',
    'destiny_none' => 'nessuno',
    'destiny_help' => 'Se vinci %d volte un rate-up diverso dal tuo bersaglio, il successivo è il bersaglio.',
    'destiny_points' => 'Destino %d/%d',
    'open1' => 'Apri 1×', 'open10' => 'Apri 10×',
    'or' => 'o',
    'free' => 'Gratis',
    'free_badge' => 'GRATIS',
    'free_sub' => 'oggi non costa nulla',
    'buy_shards' => 'Acquista Godo Shards',
    'details' => 'Dettagli e probabilità',
    'soon_note' => 'Disponibile dal %s',
    'standard_name' => 'Banner Standard',
    'sidebar' => 'Banner',
    'group_always' => 'Sempre disponibili', 'group_events' => 'Eventi', 'group_soon' => 'In arrivo',
    'all' => 'Tutti',
    'card_free' => '%d gratis',
    'card_left' => '%d rimaste',
    'premium' => 'Premium', 'premium_sub' => '500 Godos al giorno', 'premium_again' => 'Di nuovo tra', 'claim' => 'Riscatta',
    'settings' => 'Impostazioni', 'leaderboard' => 'Classifica', 'inventory' => 'Inventario', 'history' => 'Cronologia',
    'close_x' => 'Chiudi',
    'win5050' => '<i class="fa-solid fa-trophy"></i> Rate-Up Vinto!',
    'loss5050' => 'Garantito attivato per la prossima pull',
    'again' => '<i class="fa-solid fa-rotate-right"></i> Apri ancora',
    'close' => '<i class="fa-solid fa-xmark"></i> Chiudi',
    'see_inventory' => '<i class="fa-solid fa-layer-group"></i> Vedi inventario',
    'tap_audio' => 'Tap per audio',
];

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$gFmt = static fn($n) => number_format((int)$n, 0, $gEn ? '.' : ',', $gEn ? ',' : '.');
$gProfiles = gacha_pity_profiles();

/** Durata breve per i conti alla rovescia: "4g 2h", "2h 13m", "13m". */
function lb_duration(int $seconds, bool $en): string
{
    $seconds = max(0, $seconds);
    $d = intdiv($seconds, 86400);
    $hh = intdiv($seconds % 86400, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($d > 0) return $d . ($en ? 'd ' : 'g ') . $hh . 'h';
    if ($hh > 0) return $hh . 'h ' . $m . 'm';
    return max(1, $m) . 'm';
}

/** Proporzioni di un'immagine del sito (larghezza / altezza), o null. */
function lb_ratio(?string $url): ?float
{
    static $cache = [];
    $url = (string)$url;
    if ($url === '' || !str_starts_with($url, '/img/')) return null;
    if (array_key_exists($url, $cache)) return $cache[$url];
    $file = dirname(__DIR__, 3) . rawurldecode((string)parse_url($url, PHP_URL_PATH));
    $size = is_file($file) ? @getimagesize($file) : false;
    return $cache[$url] = ($size && $size[1] > 0) ? round($size[0] / $size[1], 4) : null;
}

/** Colore del banner: quello scelto nel pannello, o dalla rarita' del rate-up. */
function lb_accent(array $b): array
{
    $byRarity = [
        'comune' => '#64748b', 'raro' => '#38bdf8', 'epico' => '#c084fc', 'leggendario' => '#f59e0b',
        'speciale' => '#e879f9', 'segreto' => '#a855f7', 'theone' => '#3b82f6',
    ];
    if ($b['colore'] && preg_match('/^#[0-9a-f]{6}$/i', $b['colore'])) {
        $accent = $b['colore'];
    } elseif ($b['featured']) {
        $accent = $byRarity[$b['featured'][0]['rarita']] ?? '#a855f7';
    } else {
        $accent = '#38bdf8';
    }
    return [$accent, $b['tipo'] === 'standard' ? '#6366f1' : '#60a5fa'];
}

/** Cosa garantisce il pity, in parole: "Segreto", "Speciale o Segreto"... */
function lb_tier_label(string $soglia, array $G, string $lang): string
{
    if ($soglia === 'speciale') return $G['tier_std'];
    if ($soglia === 'segreto' || $soglia === 'theone') return gacha_rarity_label('segreto', $lang);
    return sprintf($G['tier_up'], gacha_rarity_label($soglia, $lang));
}

/** La frase accanto al pity (HTML, con il numero in grassetto) e se e' "accesa". */
function lb_pity_note(array $b, array $G, string $lang): array
{
    $p = $b['pity'];
    $tier = htmlspecialchars(lb_tier_label($p['soglia'], $G, $lang), ENT_QUOTES, 'UTF-8');
    // La pull garantita e' la numero `hard`: con pity hard-1 e' la prossima.
    if ($p['contatore'] + 1 >= $p['hard']) return [sprintf($G['pity_next'], $tier), true];
    if ($p['contatore'] >= $p['soft']) return [$G['pity_soft'], true];
    return [sprintf($G['pity_within'], $tier, max(1, $p['hard'] - $p['contatore'])), false];
}

$gRateRows = ['comune', 'raro', 'epico', 'leggendario', 'speciale', 'segreto'];
$gNow = time();

// Le carte dell'elenco banner, uguali nella colonna e nella striscia mobile.
$gCard = static function (array $b, bool $mini) use ($h, $G, $gActive, $gEn, $gNow): string {
    [$accent] = lb_accent($b);
    $isSoon = $b['stato'] === 'prossimamente';
    $thumb = $b['thumb'] ?: ($b['featured'][0]['img'] ?? '/img/cassa.png');
    $uso = $b['uso'];

    $st = '';
    if ($isSoon && $b['data_inizio']) {
        $st = '<span class="lb-card__st is-soon"><i class="fa-regular fa-calendar"></i> <span data-countdown="' . $h($b['data_inizio']) . '">'
            . $h(lb_duration(strtotime($b['data_inizio']) - $gNow, $gEn)) . '</span></span>';
    } elseif ($uso['gratis_rimaste'] > 0) {
        $st = '<span class="lb-card__st is-free" data-card-free><i class="fa-solid fa-gift"></i> ' . $h(sprintf($G['card_free'], $uso['gratis_rimaste'])) . '</span>';
    } elseif ($uso['limite']) {
        $st = '<span class="lb-card__st" data-card-left><i class="fa-solid fa-hourglass-half"></i> ' . $h(sprintf($G['card_left'], max(0, $uso['limite'] - $uso['totale']))) . '</span>';
    } elseif ($b['data_fine']) {
        $st = '<span class="lb-card__st"><i class="fa-regular fa-clock"></i> <span data-countdown="' . $h($b['data_fine']) . '">'
            . $h(lb_duration(strtotime($b['data_fine']) - $gNow, $gEn)) . '</span></span>';
    }

    if ($b['featured']) {
        $sub = implode(' · ', array_column($b['featured'], 'nome'));
    } elseif ($b['pool_modo'] === 'categoria' && $b['pool_categoria']) {
        $sub = sprintf($G['pool_cat'], $b['pool_categoria']);
    } else {
        $sub = sprintf($G['pool_all'], $b['pool_count']);
    }
    $on = $b['key'] === $gActive;

    return '<button type="button" class="lb-card' . ($mini ? ' lb-card--mini' : '') . ($on ? ' is-active' : '') . ($isSoon ? ' is-soon' : '') . '"'
        . ' data-banner-select="' . $h($b['key']) . '" data-banner-type="' . ($b['tipo'] === 'standard' ? 'standard' : 'evento') . '"'
        . ' aria-pressed="' . ($on ? 'true' : 'false') . '" aria-label="' . $h($b['nome']) . '"'
        . ' style="--card-img:url(\'' . $h($thumb) . '\');--accent:' . $h($accent) . '">'
        . '<span class="lb-card__bg" aria-hidden="true"></span>'
        . $st
        . '<span class="lb-card__body"><b>' . $h($b['nome']) . '</b>' . ($mini ? '' : '<small>' . $h($sub) . '</small>') . '</span>'
        . '</button>';
};

$gGroups = [
    'always' => array_values(array_filter($gBanners, static fn($b) => $b['stato'] !== 'prossimamente' && in_array($b['tipo'], ['standard', 'principiante'], true))),
    'events' => array_values(array_filter($gBanners, static fn($b) => $b['stato'] !== 'prossimamente' && !in_array($b['tipo'], ['standard', 'principiante'], true))),
    'soon' => array_values(array_filter($gBanners, static fn($b) => $b['stato'] === 'prossimamente')),
];
$gActiveBanner = null;
foreach ($gBanners as $b) {
    if ($b['key'] === $gActive) $gActiveBanner = $b;
}
[$gAccent, $gAccent2] = $gActiveBanner ? lb_accent($gActiveBanner) : ['#38bdf8', '#6366f1'];

$gClaimedToday = $gPremium && ($gLastClaim === getMissionDailyPeriod());
$gClaimLeft = strtotime('tomorrow') - time();
?>
<!DOCTYPE html>
<html lang="<?= $gLang ?>">

<head>
    <?php include __DIR__ . '/../../head-import.php'; ?>
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/css/gacha.css')) ?>">
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/css/gacha-v2.css')) ?>">
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/css/lootbox-v3.css')) ?>">
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/css/lootbox-modal.css')) ?>">
    <meta name="theme-color" content="#06070d">
    <title>Cripsum™ — Lootbox</title>
</head>

<body class="lootbox-page lb-page<?= $gPremium ? ' has-premium' : '' ?>" data-ruolo="<?= $h($gRole) ?>" style="--accent:<?= $h($gAccent) ?>;--accent-2:<?= $h($gAccent2) ?>">

    <?php include __DIR__ . '/../../navbar-lootbox.php'; ?>

    <div class="lb" id="gacha-layout">

        <main class="lb-stage" id="gacha-main">
            <div class="lb-bgs" aria-hidden="true">
                <?php foreach ($gBanners as $b):
                    $bg = $b['sfondo'] ?: null;
                    $fallback = $bg ? null : ($b['arte'] ?: '/img/cassa.png');
                    $on = $b['key'] === $gActive;
                ?>
                    <div class="lb-bg<?= $bg ? '' : ' is-blurred' ?><?= $on ? ' is-active' : '' ?>" data-bg="<?= $h($b['key']) ?>"
                        data-src="<?= $h($bg ?: $fallback) ?>"<?= $on ? ' style="background-image:url(\'' . $h($bg ?: $fallback) . '\')"' : '' ?>></div>
                <?php endforeach; ?>
            </div>

            <nav class="lb-strip" aria-label="<?= $h($G['sidebar']) ?>">
                <div class="lb-strip__track">
                    <?php foreach (array_merge($gGroups['always'], $gGroups['events'], $gGroups['soon']) as $b) echo $gCard($b, true); ?>
                </div>
                <button type="button" class="lb-strip__all" data-rail-open aria-controls="gacha-sidebar">
                    <i class="fa-solid fa-grip"></i><span><?= $h($G['all']) ?></span>
                </button>
            </nav>

            <?php foreach ($gBanners as $b):
                $key = $b['key'];
                $isStd = $b['tipo'] === 'standard';
                $isSoon = $b['stato'] === 'prossimamente';
                $featured = $b['featured'];
                $uso = $b['uso'];
                $p = $b['pity'];
                [$accent, $accent2] = lb_accent($b);
                [$note, $noteActive] = lb_pity_note($b, $G, $gLang);
                $kind = $isSoon ? $G['kind_soon'] : ($G['kind_' . $b['tipo']] ?? $G['kind_evento']);
                $kindIcon = $isSoon ? 'fa-calendar' : ($isStd ? 'fa-infinity' : ($b['tipo'] === 'principiante' ? 'fa-seedling' : 'fa-star'));
                $on = $key === $gActive;

                // Arte: quella scelta nel pannello, altrimenti i personaggi in
                // rate-up, altrimenti la cassa. Ogni immagine tiene la sua forma.
                $explicitArt = $b['arte'] && (!$featured || $b['arte'] !== ($featured[0]['img'] ?? null));
                if ($explicitArt || !$featured) {
                    $arts = [['img' => $b['arte'] ?: '/img/cassa.png', 'nome' => $b['nome']]];
                } else {
                    $arts = array_map(static fn($f) => ['img' => $f['img'] ?: '/img/cassa.png', 'nome' => $f['nome']], array_slice($featured, 0, 4));
                }
                $isChest = count($arts) === 1 && str_ends_with((string)$arts[0]['img'], '/cassa.png');
                $sameRarity = $featured && count(array_unique(array_column($featured, 'rarita'))) === 1;
            ?>
                <section
                    class="lb-view<?= $on ? ' is-active' : '' ?><?= $isSoon ? ' is-soon' : '' ?>"
                    id="banner-view-<?= $h($key) ?>"
                    data-banner-id="<?= $h($key) ?>"
                    data-banner-type="<?= $isStd ? 'standard' : 'evento' ?>"
                    data-pity-gruppo="<?= $h($b['pity_gruppo']) ?>"
                    data-pity-hard="<?= (int)$p['hard'] ?>"
                    data-pity-soft="<?= (int)$p['soft'] ?>"
                    data-pity-soglia="<?= $h($p['soglia']) ?>"
                    data-costo="<?= (int)$b['costo'] ?>"
                    data-stato="<?= $h($b['stato']) ?>"
                    data-gratis="<?= (int)$uso['gratis_rimaste'] ?>"
                    data-accent="<?= $h($accent) ?>" data-accent-2="<?= $h($accent2) ?>"
                    <?php if ($b['data_fine']): ?>data-data-fine="<?= $h($b['data_fine']) ?>"<?php endif; ?>
                    <?= $on ? '' : 'hidden' ?>
                    aria-label="<?= $h($b['nome']) ?>">

                    <div class="lb-info">
                        <p class="lb-kicker lb-in" style="--d:0">
                            <span class="lb-kicker__kind"><i class="fa-solid <?= $kindIcon ?>"></i> <?= $h($kind) ?></span>
                            <?php $timerTarget = $isSoon ? $b['data_inizio'] : $b['data_fine']; ?>
                            <?php if ($timerTarget || !$isStd): ?>
                                <span class="lb-kicker__sep" aria-hidden="true"></span>
                                <span class="lb-kicker__time"><i class="fa-regular fa-clock"></i>
                                    <?php if ($timerTarget): ?>
                                        <?= $h($isSoon ? $G['starts_in'] : $G['ends_in']) ?>
                                        <b data-countdown="<?= $h($timerTarget) ?>"<?= $isSoon ? ' data-reload-at-zero' : '' ?>><?= $h(lb_duration(strtotime($timerTarget) - $gNow, $gEn)) ?></b>
                                    <?php else: ?>
                                        <?= $h($G['no_end']) ?>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($b['solo_premium']): ?>
                                <span class="lb-kicker__premium"><i class="fa-solid fa-gem"></i> <?= $h($G['premium_only']) ?></span>
                            <?php endif; ?>
                        </p>

                        <h1 class="lb-title lb-in" style="--d:1"><?= $h($b['nome']) ?></h1>
                        <?php if ($b['descrizione']): ?>
                            <p class="lb-desc lb-in" style="--d:2" title="<?= $h($b['descrizione']) ?>"><?= $h($b['descrizione']) ?></p>
                        <?php endif; ?>

                        <div class="lb-feature lb-in" style="--d:3">
                            <?php if ($featured): ?>
                                <span class="lb-feature__imgs">
                                    <?php foreach (array_slice($featured, 0, 3) as $f): ?>
                                        <img src="<?= $h($f['img'] ?: '/img/cassa.png') ?>" alt="" style="--rc:<?= $h(gacha_rarity_defs()[$f['rarita']]['color'] ?? '#fff') ?>" onerror="this.src='/img/cassa.png'">
                                    <?php endforeach; ?>
                                </span>
                                <span class="lb-feature__text">
                                    <small><?= $h($G['rateup']) ?></small>
                                    <b><?= $h(count($featured) > 2 ? $featured[0]['nome'] . ', ' . $featured[1]['nome'] . ' +' . (count($featured) - 2) : implode(' & ', array_column($featured, 'nome'))) ?></b>
                                </span>
                                <?php if ($sameRarity): $rk = $featured[0]['rarita']; ?>
                                    <span class="lb-chip-r" style="--rc:<?= $h(gacha_rarity_defs()[$rk]['color'] ?? '#fff') ?>"><?= $h(gacha_rarity_label($rk, $gLang)) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="lb-feature__icon"><i class="fa-solid fa-layer-group"></i></span>
                                <span class="lb-feature__text">
                                    <small><?= $h($G['pool']) ?></small>
                                    <b><?= $h($b['pool_modo'] === 'categoria' && $b['pool_categoria'] ? sprintf($G['pool_cat'], $b['pool_categoria']) : sprintf($G['pool_all'], $b['pool_count'])) ?></b>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!$isSoon): ?>
                            <div class="lb-pity lb-in" style="--d:4" data-pity-box>
                                <div class="lb-pity__top">
                                    <span class="lb-pity__count"><?= $h($G['pity']) ?> <b data-pity-num><?= (int)$p['contatore'] ?></b> / <?= (int)$p['hard'] ?>
                                        <?php if ($p['condiviso']): ?><i class="fa-solid fa-link" title="<?= $h($G['pity_shared']) ?>" aria-label="<?= $h($G['pity_shared']) ?>"></i><?php endif; ?>
                                    </span>
                                    <span class="lb-pity__note<?= $noteActive ? ' is-active' : '' ?>" data-pity-note><?= $note ?></span>
                                </div>
                                <div class="lb-pity__bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int)$p['hard'] ?>" aria-valuenow="<?= (int)$p['contatore'] ?>">
                                    <span class="lb-pity__soft" style="left:<?= round($p['soft'] / max(1, $p['hard']) * 100, 2) ?>%"></span>
                                    <span class="lb-pity__fill" data-pity-fill style="width:<?= min(100, round($p['contatore'] / max(1, $p['hard']) * 100, 2)) ?>%"></span>
                                </div>
                            </div>

                            <div class="lb-tags lb-in" style="--d:5" data-limits>
                                <?php if ($featured): ?>
                                    <span class="lb-tag is-guaranteed" data-garantito-badge data-pity-gruppo="<?= $h($b['pity_gruppo']) ?>" id="garantito-badge-<?= $h($key) ?>"<?= $p['garantito'] ? '' : ' style="display:none"' ?>>
                                        <i class="fa-solid fa-shield-halved"></i> <?= $h(sprintf($G['guaranteed'], lb_tier_label($p['soglia'], $G, $gLang))) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($uso['gratis_max'] > 0): ?>
                                    <span class="lb-tag is-free" data-free-chip<?= $uso['gratis_rimaste'] > 0 ? '' : ' hidden' ?>><i class="fa-solid fa-gift"></i> <span data-free-text><?= $h(sprintf($uso['gratis_rimaste'] === 1 ? $G['free_today'] : $G['free_today_n'], $uso['gratis_rimaste'])) ?></span></span>
                                <?php endif; ?>
                                <?php if ($uso['limite']): ?>
                                    <span class="lb-tag" data-limit-total><i class="fa-solid fa-hourglass-half"></i> <span><?= $h(sprintf($G['limit_total'], $uso['totale'], $uso['limite'])) ?></span></span>
                                <?php endif; ?>
                                <?php if ($uso['limite_giorno']): ?>
                                    <span class="lb-tag" data-limit-day><i class="fa-solid fa-calendar-day"></i> <span><?= $h(sprintf($G['limit_day'], $uso['oggi'], $uso['limite_giorno'])) ?></span></span>
                                <?php endif; ?>
                                <?php if ($b['destino']): $d = $b['destino'];
                                    $target = null;
                                    foreach ($featured as $f) if ((int)$f['id'] === (int)$d['bersaglio']) $target = $f['nome'];
                                ?>
                                    <div class="lb-destiny" data-destiny data-banner="<?= $h($key) ?>" data-max="<?= (int)$d['max'] ?>">
                                        <button type="button" class="lb-tag lb-tag--btn" data-destiny-toggle aria-expanded="false">
                                            <i class="fa-solid fa-crosshairs"></i> <?= $h($G['destiny']) ?>: <b data-destiny-label><?= $h($target ?? $G['destiny_none']) ?></b>
                                            <i class="fa-solid fa-chevron-down lb-destiny__chev"></i>
                                        </button>
                                        <div class="lb-destiny__menu" role="menu">
                                            <div class="lb-destiny__head"><span><?= $h($G['destiny']) ?></span><small data-destiny-points><?= $h(sprintf($G['destiny_points'], (int)$d['punti'], (int)$d['max'])) ?></small></div>
                                            <button type="button" role="menuitemradio" class="lb-destiny__opt<?= $d['bersaglio'] ? '' : ' is-active' ?>" data-destiny-target="0" data-name="<?= $h($G['destiny_none']) ?>">
                                                <span class="lb-destiny__none"><i class="fa-solid fa-ban"></i></span><?= $h(ucfirst($G['destiny_none'])) ?><i class="fa-solid fa-check lb-destiny__check"></i>
                                            </button>
                                            <?php foreach ($featured as $f): if (!in_array($f['id'], $d['scelte'], true)) continue; ?>
                                                <button type="button" role="menuitemradio" class="lb-destiny__opt<?= (int)$d['bersaglio'] === (int)$f['id'] ? ' is-active' : '' ?>" data-destiny-target="<?= (int)$f['id'] ?>" data-name="<?= $h($f['nome']) ?>">
                                                    <img src="<?= $h($f['img'] ?: '/img/cassa.png') ?>" alt="" onerror="this.src='/img/cassa.png'"><?= $h($f['nome']) ?><i class="fa-solid fa-check lb-destiny__check"></i>
                                                </button>
                                            <?php endforeach; ?>
                                            <p class="lb-destiny__help"><?= $h(sprintf($G['destiny_help'], (int)$d['max'])) ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="lb-more lb-in" style="--d:6" data-banner-details="<?= $h($key) ?>">
                                <i class="fa-solid fa-circle-info"></i> <?= $h($G['details']) ?>
                            </button>

                            <div class="lb-actions lb-in" style="--d:6">
                                <div class="lb-pulls">
                                    <button type="button" class="lb-pull" id="pull-btn-<?= $h($key) ?>" data-pull-btn data-banner-id="<?= $h($key) ?>" aria-label="<?= $h($G['open1'] . ' ' . $b['nome']) ?>">
                                        <?php if ($uso['gratis_max'] > 0): ?><span class="lb-pull__free" data-free-badge<?= $uso['gratis_rimaste'] > 0 ? '' : ' hidden' ?>><?= $h($G['free_badge']) ?></span><?php endif; ?>
                                        <span class="lb-pull__label"><i class="fa-solid fa-star"></i> <?= $h($G['open1']) ?></span>
                                        <?php if ($b['costo'] > 0): ?>
                                            <small class="lb-pull__cost" data-cost-paid<?= $uso['gratis_rimaste'] > 0 ? ' hidden' : '' ?>><?= $gFmt($b['costo']) ?> <img src="/img/godos.png" alt="Godos"> <?= $h($G['or']) ?> <?= (int)$b['costo_shards'] ?> <img src="/img/godoshards.png" alt="Shards"></small>
                                            <?php if ($uso['gratis_max'] > 0): ?><small class="lb-pull__cost" data-cost-free<?= $uso['gratis_rimaste'] > 0 ? '' : ' hidden' ?>><?= $h($G['free_sub']) ?></small><?php endif; ?>
                                        <?php else: ?>
                                            <small class="lb-pull__cost"><?= $h($G['free']) ?></small>
                                        <?php endif; ?>
                                    </button>
                                    <button type="button" class="lb-pull lb-pull--main" data-pull-btn data-banner-id="<?= $h($key) ?>" data-pull-qty="10" aria-label="<?= $h($G['open10'] . ' ' . $b['nome']) ?>">
                                        <span class="lb-pull__label"><i class="fa-solid fa-boxes-stacked"></i> <?= $h($G['open10']) ?></span>
                                        <?php if ($b['costo'] > 0): ?>
                                            <small class="lb-pull__cost"><?= $gFmt($b['costo'] * 10) ?> <img src="/img/godos.png" alt="Godos"> <?= $h($G['or']) ?> <?= (int)ceil($b['costo'] * 10 / max(1, $gGps)) ?> <img src="/img/godoshards.png" alt="Shards"></small>
                                        <?php else: ?>
                                            <small class="lb-pull__cost"><?= $h($G['free']) ?></small>
                                        <?php endif; ?>
                                    </button>
                                </div>
                                <div class="lb-wallet">
                                    <span class="lb-coin" title="Godos"><img src="/img/godos.png" alt="Godos"><b class="user-points-val"><?= $gFmt($gSoldi) ?></b></span>
                                    <span class="lb-coin" title="Godo Shards"><img src="/img/godoshards.png" alt="Godo Shards"><b class="user-shards-val"><?= $gFmt($gShards) ?></b></span>
                                    <a href="shop" class="lb-coin-add" title="<?= $h($G['buy_shards']) ?>" aria-label="<?= $h($G['buy_shards']) ?>"><i class="fa-solid fa-plus"></i></a>
                                    <span class="lb-wallet__sp"></span>
                                    <span class="lb-mini-pity"><i class="fa-solid fa-chart-simple"></i> <?= $h($G['pity']) ?> <b data-pity-mini><?= (int)$p['contatore'] ?></b>/<?= (int)$p['hard'] ?></span>
                                    <button type="button" class="lb-link" data-banner-details="<?= $h($key) ?>"><i class="fa-solid fa-circle-info"></i> <?= $h($G['details']) ?></button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="lb-soon lb-in" style="--d:4">
                                <span class="lb-soon__icon"><i class="fa-regular fa-calendar"></i></span>
                                <div><b><?= $h(sprintf($G['soon_note'], date($gEn ? 'M j, H:i' : 'd/m, H:i', strtotime((string)$b['data_inizio'])))) ?></b>
                                    <small><?= $h($G['starts_in']) ?> <span data-countdown="<?= $h($b['data_inizio']) ?>" data-reload-at-zero><?= $h(lb_duration(strtotime((string)$b['data_inizio']) - $gNow, $gEn)) ?></span></small></div>
                            </div>
                            <button type="button" class="lb-link lb-in" style="--d:5" data-banner-details="<?= $h($key) ?>"><i class="fa-solid fa-circle-info"></i> <?= $h($G['details']) ?></button>
                        <?php endif; ?>
                    </div>

                    <div class="lb-art<?= $isChest ? ' lb-art--chest' : '' ?><?= count($arts) > 1 ? ' lb-art--multi' : '' ?>" data-art>
                        <div class="lb-art__stack">
                            <?php foreach ($arts as $i => $a): $ratio = lb_ratio($a['img']); ?>
                                <figure class="lb-art__card<?= $i === 0 ? ' is-front' : '' ?>" data-art-card="<?= $i ?>" style="--pos:<?= $i ?><?= $ratio ? ';--ar:' . $ratio : '' ?>">
                                    <img src="<?= $h($a['img']) ?>" alt="<?= $h($a['nome']) ?>" draggable="false" decoding="async"<?= $on ? '' : ' loading="lazy"' ?> onerror="this.src='/img/cassa.png'">
                                </figure>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($arts) > 1): ?>
                            <div class="lb-art__picks">
                                <?php foreach ($arts as $i => $a): ?>
                                    <button type="button" class="lb-art__pick<?= $i === 0 ? ' is-on' : '' ?>" data-art-pick="<?= $i ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
                                        <img src="<?= $h($a['img']) ?>" alt="" onerror="this.src='/img/cassa.png'"><?= $h($a['nome']) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <div class="lb-extras">
                <?php if ($gPremium): ?>
                    <div class="lb-premium" data-premium>
                        <span class="lb-premium__icon"><i class="fa-solid fa-gem"></i></span>
                        <span class="lb-premium__text"><b><?= $h($G['premium']) ?></b><small><?= $h($G['premium_sub']) ?></small></span>
                        <button type="button" class="lb-premium__btn<?= $gClaimedToday ? ' claimed' : '' ?>" data-premium-claim data-seconds-left="<?= (int)$gClaimLeft ?>"<?= $gClaimedToday ? ' disabled' : '' ?>>
                            <span class="btn-text"><?= $gClaimedToday ? '<i class="fa-regular fa-clock"></i> <span class="claim-countdown">--:--:--</span>' : $h($G['claim']) ?></span>
                        </button>
                    </div>
                <?php endif; ?>
                <div class="lb-tools">
                    <button type="button" data-open="settings"><i class="fa-solid fa-gear"></i><span><?= $h($G['settings']) ?></span></button>
                    <button type="button" data-open="leaderboard"><i class="fa-solid fa-trophy"></i><span><?= $h($G['leaderboard']) ?></span></button>
                    <a href="inventario"><i class="fa-solid fa-layer-group"></i><span><?= $h($G['inventory']) ?></span></a>
                    <button type="button" data-open="history"><i class="fa-solid fa-clock-rotate-left"></i><span><?= $h($G['history']) ?></span></button>
                </div>
            </div>
        </main>

        <aside class="lb-rail" id="gacha-sidebar" aria-label="<?= $h($G['sidebar']) ?>">
            <div class="lb-rail__sheet-head">
                <span class="lb-rail__grab" aria-hidden="true"></span>
                <h2><?= $h($G['sidebar']) ?></h2>
                <button type="button" class="lb-rail__close" data-rail-close aria-label="<?= $h($G['close_x']) ?>"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="lb-rail__list" id="gsb-banners">
                <?php $gi = 0;
                foreach (['always' => $G['group_always'], 'events' => $G['group_events'], 'soon' => $G['group_soon']] as $gk => $label):
                    if (!$gGroups[$gk]) continue; ?>
                    <p class="lb-rail__label"><?= $h($label) ?><?= $gk === 'events' ? ' · ' . count($gGroups[$gk]) : '' ?></p>
                    <?php foreach ($gGroups[$gk] as $b): ?>
                        <div class="lb-rail__item" style="--i:<?= $gi++ ?>"><?= $gCard($b, false) ?></div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            <div class="lb-rail__bottom">
                <?php if ($gPremium): ?>
                    <div class="lb-premium" data-premium>
                        <span class="lb-premium__icon"><i class="fa-solid fa-gem"></i></span>
                        <span class="lb-premium__text"><b><?= $h($G['premium']) ?></b><small><?= $h($G['premium_sub']) ?></small></span>
                        <button type="button" class="lb-premium__btn<?= $gClaimedToday ? ' claimed' : '' ?>" id="premium-claim-btn" data-premium-claim data-seconds-left="<?= (int)$gClaimLeft ?>"<?= $gClaimedToday ? ' disabled' : '' ?>>
                            <span class="btn-text"><?= $gClaimedToday ? '<i class="fa-regular fa-clock"></i> <span class="claim-countdown">--:--:--</span>' : $h($G['claim']) ?></span>
                        </button>
                    </div>
                <?php endif; ?>
                <div class="lb-tools">
                    <button type="button" data-open="settings" id="btn-settings"><i class="fa-solid fa-gear"></i><span><?= $h($G['settings']) ?></span></button>
                    <button type="button" data-open="leaderboard"><i class="fa-solid fa-trophy"></i><span><?= $h($G['leaderboard']) ?></span></button>
                    <a href="inventario"><i class="fa-solid fa-layer-group"></i><span><?= $h($G['inventory']) ?></span></a>
                    <button type="button" data-open="history"><i class="fa-solid fa-clock-rotate-left"></i><span><?= $h($G['history']) ?></span></button>
                </div>
            </div>
        </aside>
        <div class="lb-rail-scrim" data-rail-close aria-hidden="true"></div>

    </div>

    <div class="gacha-overlay" id="gacha-overlay" role="dialog"
        aria-modal="true" aria-label="<?= $gEn ? 'Pull result' : 'Risultato pull' ?>" aria-live="polite">

        <div class="gacha-overlay-bg"></div>
        <div class="gacha-glow-burst" id="gacha-glow-burst"></div>
        <div class="gacha-stars-layer" id="overlay-stars"></div>
        <div class="gacha-particles-layer" id="gacha-particles"></div>
        <div class="gacha-flash" id="gacha-flash"></div>

        <div class="gacha-phase gacha-phase--opening" id="phase-opening">
            <div class="gacha-orb-container">
                <div class="gacha-orb">
                    <div class="gacha-orb-ring gacha-orb-ring--3"></div>
                    <div class="gacha-orb-ring gacha-orb-ring--2"></div>
                    <div class="gacha-orb-ring gacha-orb-ring--1"></div>
                    <div class="gacha-orb-core" id="orb-core"></div>
                </div>
            </div>
        </div>

        <div class="gacha-phase gacha-phase--video" id="phase-video" style="display:none">
            <video id="gacha-video" autoplay muted playsinline preload="metadata"
                webkit-playsinline></video>
            <button class="gacha-video-unmute" id="video-unmute-btn" style="display:none">
                <i class="fa-solid fa-volume-xmark"></i> <?= $h($G['tap_audio']) ?>
            </button>
        </div>

        <div class="gacha-phase gacha-phase--card" id="phase-card" style="display:none">
            <div class="gacha-card" id="gacha-card" aria-live="polite">
                <div class="gacha-card-bg-glow" id="card-bg-glow"></div>

                <div class="gacha-card-frame" id="card-frame">
                    <div class="gacha-card-img-wrap" id="card-img-wrap">
                        <img id="card-img" class="card-img-godo" src="/img/cassa.png" alt="<?= $gEn ? 'Character' : 'Personaggio' ?>"
                            draggable="false" onerror="this.src='/img/cassa.png'">
                    </div>
                    <div class="gacha-card-img-shine"></div>
                    <span class="gacha-card-new-badge" id="card-new-badge" style="display:none">NEW!</span>
                    <span class="gacha-card-50-badge gacha-card-50-badge--win" id="card-50-win" style="display:none">
                        <?= $G['win5050'] ?>
                    </span>
                    <span class="gacha-card-50-badge gacha-card-50-badge--loss" id="card-50-loss" style="display:none">
                        <?= $h($G['loss5050']) ?>
                    </span>
                </div>

                <div class="gacha-card-details">
                    <div class="gacha-card-rarity-bar" id="card-rarity-bar"></div>
                    <p class="gacha-card-rarity-label" id="card-rarity-label">—</p>
                    <h2 class="gacha-card-name" id="card-name"></h2>
                </div>
            </div>

            <div class="gacha-overlay-actions" id="overlay-actions">
                <button class="gacha-btn gacha-btn--primary" id="btn-pull-again">
                    <?= $G['again'] ?>
                </button>
                <button class="gacha-btn gacha-btn--ghost" id="btn-close-overlay">
                    <?= $G['close'] ?>
                </button>
                <a href="inventario" class="gacha-btn gacha-btn--ghost" id="btn-go-inventory">
                    <?= $G['see_inventory'] ?>
                </a>
            </div>
        </div>

    </div>

    <div class="gacha-toast" id="gacha-toast" role="alert" aria-live="assertive"></div>

    <audio id="gacha-audio" preload="none"></audio>

    <div class="profile-floating-audio-btn-container position-bottom-left"
        data-floating-audio>
        <button class="profile-floating-audio-btn" type="button" aria-label="Mute/Unmute">
            <i class="fa-solid fa-volume-high"></i>
        </button>
        <div class="profile-floating-audio-slider-wrap">
            <input type="range" class="profile-floating-audio-slider" min="0" max="1" step="0.01" value="0.8" aria-label="Volume">
        </div>
    </div>

    <?php include __DIR__ . '/lootbox-modals.php'; ?>

    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement">
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>

    <script>
        window.GACHA_INIT = <?= json_encode([
            'userId' => $gUserId,
            'ruolo' => $gRole,
            'isAdmin' => $gIsAdmin,
            'lang' => $gLang,
            'soldi' => $gSoldi,
            'godoshards' => $gShards,
            'godosPerShard' => $gGps,
            'activeBannerId' => $gActive,
            'pity' => $gState['pity'],
            'banners' => array_map(static fn($b) => [
                'id' => $b['key'],
                'key' => $b['key'],
                'tipo' => $b['tipo'],
                'stato' => $b['stato'],
                'nome' => $b['nome'],
                'costo' => $b['costo'],
                'pity_gruppo' => $b['pity_gruppo'],
                'pity' => $b['pity'],
                'uso' => $b['uso'],
                'featured' => array_map(static fn($f) => ['id' => $f['id'], 'nome' => $f['nome'], 'rarita' => $f['rarita']], $b['featured']),
                'destino' => $b['destino'],
                'data_fine' => $b['data_fine'],
            ], $gBanners),
            'rarities' => array_map(static fn($k) => ['key' => $k, 'label' => gacha_rarity_label($k, $gLang), 'color' => gacha_rarity_defs()[$k]['color']], gacha_rarity_keys()),
            'tierLabels' => [
                'speciale' => lb_tier_label('speciale', $G, $gLang),
                'segreto' => lb_tier_label('segreto', $G, $gLang),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
    <script src="<?= $h(cripsum_asset('/js/unlockAchievement-' . $gLang . '.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/js/lootbox-modal.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/js/gacha-effects.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/js/gacha.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/js/lootbox-ui.js')) ?>"></script>

</body>

</html>
