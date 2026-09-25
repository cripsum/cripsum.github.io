<?php

/**
 * Pagina della lootbox, una sola per IT ed EN.
 *
 * Si include da it/lootbox.php ed en/lootbox.php con $gachaLang impostata.
 * I banner arrivano da gacha_lootbox_state() (motore in includes/gacha/):
 * qui si disegnano e basta. La parte della pull (overlay, carta, video,
 * effetti) e' identica a prima e la guida js/gacha.js.
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
    'always' => '✦ Always Available',
    'type_evento' => '✦ LIMITED BANNER',
    'type_selezione' => '✦ SELECTION BANNER',
    'type_principiante' => '✦ BEGINNER BANNER',
    'soon_badge' => '✦ COMING SOON',
    'premium_only' => 'Premium only',
    'rateup' => 'Rate-Up ✦',
    'rateup_many' => 'Rate-Up characters',
    'guaranteed' => 'Guaranteed active — next rare is the rate-up',
    'pity_std' => 'Standard Pity',
    'pity_evt' => 'Limited Pity',
    'pity_own' => 'Banner Pity',
    'pity_shared' => 'Shared with the other limited banners',
    'pity_std_hard' => '★ Guaranteed: next pull is Special or Secret!',
    'pity_std_soft' => '✦ Soft pity — % Special or Secret increased',
    'pity_std_count' => 'Guaranteed Special or Secret in %d pulls',
    'pity_evt_soft' => '✦ Soft pity active — rates increasing',
    'pity_evt_count' => 'Secret guaranteed in %d pulls',
    'pity_gen_hard' => '★ Guaranteed: next pull is %s or higher!',
    'pity_gen_count' => '%s or higher guaranteed in %d pulls',
    'ends_in' => 'Remaining time',
    'starts_in' => 'Starts in',
    'days' => 'days', 'hours' => 'hours', 'mins' => 'mins',
    'godos_tip' => 'Free currency obtained by using the website.',
    'shards_tip' => 'Premium currency used to pull.',
    'buy_shards' => 'Buy Godo Shards',
    'free' => '• Free',
    'cost' => '• Cost: %s Godos or %d Shard',
    'open1' => 'Open 1×', 'open10' => 'Open 10×', 'multi10' => 'Open 10×',
    'free_today' => '%d free today',
    'limit_total' => '%d / %d pulls used',
    'limit_day' => '%d / %d pulls today',
    'multi_guarantee' => 'Every 10× has at least one Epic or higher',
    'details' => 'Details & rates',
    'destiny' => 'Choose your rate-up',
    'destiny_help' => 'If you win a rate-up that is not your target %d times, the next one is your target.',
    'destiny_none' => 'No target',
    'destiny_points' => 'Destiny %d/%d',
    'soon_note' => 'Available from %s',
    'standard_name' => 'Standard Banner',
    'sidebar' => 'Banners', 'sidebar_soon' => 'Coming soon',
    'tag_standard' => 'Standard', 'tag_evento' => 'Limited', 'tag_selezione' => 'Selection', 'tag_principiante' => 'Beginner', 'tag_soon' => 'Soon',
    'premium_claim' => 'Premium Claim', 'claimed' => 'Claimed today (Reset in <span class="claim-countdown">--:--:--</span>)', 'claim' => 'Claim 500 Points',
    'settings' => 'Settings', 'leaderboard' => 'Leaderboard', 'inventory' => 'Inventory', 'history' => 'Pull History',
    'win5050' => '<i class="fa-solid fa-trophy"></i> 50/50 Won!',
    'loss5050' => 'Guaranteed activated for the next pull',
    'again' => '<i class="fa-solid fa-rotate-right"></i> Open Again',
    'close' => '<i class="fa-solid fa-xmark"></i> Close',
    'see_inventory' => '<i class="fa-solid fa-layer-group"></i> View Inventory',
    'tap_audio' => 'Tap for audio',
] : [
    'always' => '✦ SEMPRE DISPONIBILE',
    'type_evento' => '✦ BANNER EVENTO',
    'type_selezione' => '✦ BANNER SELEZIONE',
    'type_principiante' => '✦ BANNER PRINCIPIANTE',
    'soon_badge' => '✦ PROSSIMAMENTE',
    'premium_only' => 'Solo Premium',
    'rateup' => 'Rate-Up ✦',
    'rateup_many' => 'Personaggi in rate-up',
    'guaranteed' => 'Garantito attivo — prossima rara è il rate-up',
    'pity_std' => 'Pity Standard',
    'pity_evt' => 'Pity Evento',
    'pity_own' => 'Pity del banner',
    'pity_shared' => 'Condiviso con gli altri banner evento',
    'pity_std_hard' => '★ Garantito: prossima pull è Speciale o Segreto!',
    'pity_std_soft' => '✦ Soft pity — % Speciale o Segreto aumentata',
    'pity_std_count' => 'Garantito Speciale o Segreto in %d pull',
    'pity_evt_soft' => '✦ Soft pity attivo — probabilità in aumento',
    'pity_evt_count' => 'Garantito segreto in %d pull',
    'pity_gen_hard' => '★ Garantito: prossima pull è %s o superiore!',
    'pity_gen_count' => 'Garantito %s o superiore in %d pull',
    'ends_in' => 'Scade tra',
    'starts_in' => 'Inizia tra',
    'days' => 'gg', 'hours' => 'ore', 'mins' => 'min',
    'godos_tip' => 'Valuta gratuita ottenibile usando il sito.',
    'shards_tip' => 'Valuta premium usata per pullare.',
    'buy_shards' => 'Acquista Godo Shards',
    'free' => '• Gratuito',
    'cost' => '• Costo: %s Godos o %d Shard',
    'open1' => 'Apri 1×', 'open10' => 'Apri 10×', 'multi10' => 'Multi 10×',
    'free_today' => '%d gratis oggi',
    'limit_total' => '%d / %d pull usate',
    'limit_day' => '%d / %d pull oggi',
    'multi_guarantee' => 'Ogni 10× ha almeno un Epico o superiore',
    'details' => 'Dettagli e probabilità',
    'destiny' => 'Scegli il tuo rate-up',
    'destiny_help' => 'Se vinci %d volte un rate-up diverso dal tuo bersaglio, il successivo è il bersaglio.',
    'destiny_none' => 'Nessun bersaglio',
    'destiny_points' => 'Destino %d/%d',
    'soon_note' => 'Disponibile dal %s',
    'standard_name' => 'Banner Standard',
    'sidebar' => 'Banner', 'sidebar_soon' => 'Prossimamente',
    'tag_standard' => 'Standard', 'tag_evento' => 'Evento', 'tag_selezione' => 'Selezione', 'tag_principiante' => 'Principiante', 'tag_soon' => 'In arrivo',
    'premium_claim' => 'Riscatto Premium', 'claimed' => 'Riscattato oggi (Ricarica tra <span class="claim-countdown">--:--:--</span>)', 'claim' => 'Riscatta 500 Punti',
    'settings' => 'Impostazioni', 'leaderboard' => 'Classifica', 'inventory' => 'Inventario', 'history' => 'Cronologia',
    'win5050' => '<i class="fa-solid fa-trophy"></i> Rate-Up Vinto!',
    'loss5050' => 'Garantito attivato per la prossima pull',
    'again' => '<i class="fa-solid fa-rotate-right"></i> Apri ancora',
    'close' => '<i class="fa-solid fa-xmark"></i> Chiudi',
    'see_inventory' => '<i class="fa-solid fa-layer-group"></i> Vedi inventario',
    'tap_audio' => 'Tap per audio',
];

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/** Il testo sotto la barra del pity, come lo scriveva la pagina di prima. */
function gacha_page_pity_note(array $b, array $G, string $lang): array
{
    $p = $b['pity'];
    // La pull garantita e' la numero `hard`: con pity hard-1 e' la prossima.
    $left = max(1, $p['hard'] - $p['contatore']);
    $soglia = gacha_rarity_label($p['soglia'], $lang);
    if ($b['pity_gruppo'] === 'standard') {
        if ($p['contatore'] + 1 >= $p['hard']) return [$G['pity_std_hard'], true];
        if ($p['contatore'] >= $p['soft']) return [$G['pity_std_soft'], true];
        return [sprintf($G['pity_std_count'], $left), false];
    }
    if ($p['soglia'] === 'segreto') {
        if ($p['contatore'] >= $p['soft']) return [$G['pity_evt_soft'], true];
        return [sprintf($G['pity_evt_count'], $left), false];
    }
    if ($p['contatore'] + 1 >= $p['hard']) return [sprintf($G['pity_gen_hard'], $soglia), true];
    return [sprintf($G['pity_gen_count'], $soglia, $left), false];
}

$gRateRows = [
    ['comune', 'rate-common'], ['raro', 'rate-rare'], ['epico', 'rate-epic'],
    ['leggendario', 'rate-legendary'], ['speciale', 'rate-special'], ['segreto', 'rate-secret'],
];
$gProfiles = gacha_pity_profiles();
$gFmt = static fn($n) => number_format((int)$n, 0, $gEn ? '.' : ',', $gEn ? ',' : '.');
?>
<!DOCTYPE html>
<html lang="<?= $gLang ?>">

<head>
    <?php include __DIR__ . '/../../head-import.php'; ?>
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/css/lootbox.css')) ?>">
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/css/gacha.css')) ?>">
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/css/gacha-v2.css')) ?>">
    <meta name="theme-color" content="#080810">
    <title>Cripsum™ — Lootbox</title>
</head>

<body class="lootbox-page<?= $gPremium ? ' has-premium' : '' ?>" data-ruolo="<?= $h($gRole) ?>">

    <?php include __DIR__ . '/../../navbar-lootbox.php'; ?>

    <div class="stars" id="stars"></div>

    <div class="gacha-layout" id="gacha-layout">

        <main class="gacha-main" id="gacha-main">
            <?php foreach ($gBanners as $b):
                $key = $b['key'];
                $isStd = $b['tipo'] === 'standard';
                $isSoon = $b['stato'] === 'prossimamente';
                $featured = $b['featured'];
                [$note, $noteActive] = gacha_page_pity_note($b, $G, $gLang);
                $pityLabel = $b['pity_gruppo'] === 'standard' ? $G['pity_std'] : ($b['pity_gruppo'] === 'evento' ? $G['pity_evt'] : $G['pity_own']);
                $badge = $isSoon ? $G['soon_badge'] : ($isStd ? $G['always'] : ($G['type_' . $b['tipo']] ?? $G['type_evento']));
                $uso = $b['uso'];
                $styleAccent = $b['colore'] ? '--banner-accent:' . $h($b['colore']) . ';--banner-accent-glow:' . $h($b['colore']) . '55;' : '';
            ?>
                <section
                    class="gacha-banner-view<?= $isSoon ? ' is-soon' : '' ?>"
                    id="banner-view-<?= $h($key) ?>"
                    data-banner-id="<?= $h($key) ?>"
                    data-banner-type="<?= $isStd ? 'standard' : 'evento' ?>"
                    data-pity-gruppo="<?= $h($b['pity_gruppo']) ?>"
                    data-pity-hard="<?= (int)$b['pity']['hard'] ?>"
                    data-pity-soft="<?= (int)$b['pity']['soft'] ?>"
                    data-pity-soglia="<?= $h($b['pity']['soglia']) ?>"
                    data-costo="<?= (int)$b['costo'] ?>"
                    data-stato="<?= $h($b['stato']) ?>"
                    data-gratis="<?= (int)$uso['gratis_rimaste'] ?>"
                    <?php if ($b['data_fine']): ?>data-data-fine="<?= $h($b['data_fine']) ?>"<?php endif; ?>
                    style="<?= $key === $gActive ? '' : 'display:none;' ?><?= $styleAccent ?>"
                    aria-label="<?= $h($b['nome']) ?>">
                    <div class="gacha-banner-bg<?= $b['sfondo'] ? ' has-img' : '' ?>" id="banner-bg-<?= $h($key) ?>"
                        <?php if ($b['sfondo']): ?>style="background-image:url('<?= $h($b['sfondo']) ?>')"<?php endif; ?>></div>

                    <div class="gacha-banner-art-wrap" aria-hidden="true">
                        <img src="<?= $h($b['arte'] ?: '/img/cassa.png') ?>" alt="" class="gacha-banner-char"
                            <?= $isStd ? 'id="banner-char-standard"' : '' ?> draggable="false" onerror="this.src='/img/cassa.png'">
                    </div>

                    <div class="gacha-banner-info">
                        <div>
                            <span class="gacha-banner-type-badge"><?= $h($badge) ?></span>
                            <?php if ($b['solo_premium']): ?>
                                <span class="gacha-v2-chip gacha-v2-chip--premium"><i class="fa-solid fa-gem"></i> <?= $h($G['premium_only']) ?></span>
                            <?php endif; ?>
                            <h1 class="gacha-banner-title"><?= $h($b['nome']) ?></h1>
                            <?php if ($b['descrizione']): ?>
                                <p class="gacha-banner-desc"><?= $h($b['descrizione']) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if (count($featured) === 1): $f = $featured[0]; ?>
                            <div class="gacha-rateup-info">
                                <span class="gacha-rateup-label"><?= $h($G['rateup']) ?></span>
                                <p class="gacha-rateup-name"><?= $h($f['nome']) ?></p>
                                <p class="gacha-rateup-rarity rarity-<?= $h($f['rarita']) ?>"><?= $h($f['rarita']) ?></p>
                                <?php if ($f['descrizione']): ?>
                                    <p class="gacha-rateup-char-desc"><?= $h($f['descrizione']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php elseif (count($featured) > 1): ?>
                            <div class="gacha-rateup-info gacha-v2-rateups">
                                <span class="gacha-rateup-label"><?= $h($G['rateup_many']) ?></span>
                                <div class="gacha-v2-rateup-list">
                                    <?php foreach ($featured as $f): ?>
                                        <div class="gacha-v2-rateup" data-featured-id="<?= (int)$f['id'] ?>">
                                            <img src="<?= $h($f['img'] ?: '/img/cassa.png') ?>" alt="" loading="lazy" onerror="this.src='/img/cassa.png'">
                                            <span><strong><?= $h($f['nome']) ?></strong><small class="gacha-rateup-rarity rarity-<?= $h($f['rarita']) ?>"><?= $h(gacha_rarity_label($f['rarita'], $gLang)) ?></small></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($b['destino'] && !$isSoon): $d = $b['destino']; ?>
                            <div class="gacha-v2-destiny" data-destiny data-banner="<?= $h($key) ?>" data-max="<?= (int)$d['max'] ?>">
                                <div class="gacha-v2-destiny-head">
                                    <span><i class="fa-solid fa-crosshairs"></i> <?= $h($G['destiny']) ?></span>
                                    <small data-destiny-points><?= $h(sprintf($G['destiny_points'], (int)$d['punti'], (int)$d['max'])) ?></small>
                                </div>
                                <div class="gacha-v2-destiny-options">
                                    <button type="button" class="gacha-v2-destiny-opt<?= $d['bersaglio'] ? '' : ' is-active' ?>" data-destiny-target="0"><?= $h($G['destiny_none']) ?></button>
                                    <?php foreach ($featured as $f): if (!in_array($f['id'], $d['scelte'], true)) continue; ?>
                                        <button type="button" class="gacha-v2-destiny-opt<?= (int)$d['bersaglio'] === (int)$f['id'] ? ' is-active' : '' ?>" data-destiny-target="<?= (int)$f['id'] ?>"><?= $h($f['nome']) ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <small class="gacha-v2-destiny-help"><?= $h(sprintf($G['destiny_help'], (int)$d['max'])) ?></small>
                            </div>
                        <?php endif; ?>

                        <?php if ($featured): ?>
                            <div class="gacha-garantito-badge" data-garantito-badge data-pity-gruppo="<?= $h($b['pity_gruppo']) ?>" id="garantito-badge-<?= $h($key) ?>"
                                <?= !$b['pity']['garantito'] ? 'style="display:none"' : '' ?>>
                                <i class="fa-solid fa-shield-halved"></i>
                                <?= $h($G['guaranteed']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$isSoon): ?>
                            <div class="gacha-pity-wrap" data-pity-box>
                                <div class="gacha-pity-header">
                                    <span title="<?= $b['pity']['condiviso'] ? $h($G['pity_shared']) : '' ?>"><?= $h($pityLabel) ?><?= $b['pity']['condiviso'] ? ' <i class="fa-solid fa-link gacha-v2-shared" aria-hidden="true"></i>' : '' ?></span>
                                    <span class="gacha-pity-num" data-pity-num><?= (int)$b['pity']['contatore'] ?> / <?= (int)$b['pity']['hard'] ?></span>
                                </div>
                                <div class="gacha-pity-track">
                                    <div class="gacha-pity-fill" data-pity-fill
                                        style="width:<?= min(100, round($b['pity']['contatore'] / max(1, $b['pity']['hard']) * 100)) ?>%"></div>
                                    <div class="gacha-pity-soft-marker"
                                        style="left:<?= round($b['pity']['soft'] / max(1, $b['pity']['hard']) * 100) ?>%"></div>
                                </div>
                                <p class="gacha-pity-note<?= $noteActive ? ' is-active' : '' ?>" data-pity-note><?= $h($note) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php $timerTarget = $isSoon ? $b['data_inizio'] : $b['data_fine']; ?>
                        <?php if ($timerTarget): ?>
                            <div class="gacha-timer-wrap">
                                <i class="fa-solid fa-clock"></i>
                                <span><?= $h($isSoon ? $G['starts_in'] : $G['ends_in']) ?></span>
                                <div class="gacha-timer-digits" data-ends="<?= $h($timerTarget) ?>"<?= $isSoon ? ' data-reload-at-zero' : '' ?>>
                                    <div class="gacha-timer-block"><span class="t-days">--</span><small><?= $h($G['days']) ?></small></div>
                                    <div class="gacha-timer-block"><span class="t-hours">--</span><small><?= $h($G['hours']) ?></small></div>
                                    <div class="gacha-timer-block"><span class="t-mins">--</span><small><?= $h($G['mins']) ?></small></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!$isSoon): ?>
                            <div class="gacha-v2-limits" data-limits>
                                <?php if ($uso['gratis_max'] > 0): ?>
                                    <span class="gacha-v2-chip gacha-v2-chip--free" data-free-chip<?= $uso['gratis_rimaste'] > 0 ? '' : ' hidden' ?>><i class="fa-solid fa-gift"></i> <span data-free-text><?= $h(sprintf($G['free_today'], $uso['gratis_rimaste'])) ?></span></span>
                                <?php endif; ?>
                                <?php if ($uso['limite']): ?>
                                    <span class="gacha-v2-chip" data-limit-total><i class="fa-solid fa-hourglass-half"></i> <?= $h(sprintf($G['limit_total'], $uso['totale'], $uso['limite'])) ?></span>
                                <?php endif; ?>
                                <?php if ($uso['limite_giorno']): ?>
                                    <span class="gacha-v2-chip" data-limit-day><i class="fa-solid fa-calendar-day"></i> <?= $h(sprintf($G['limit_day'], $uso['oggi'], $uso['limite_giorno'])) ?></span>
                                <?php endif; ?>
                                <?php if ($b['garanzia_multi']): ?>
                                    <span class="gacha-v2-chip gacha-v2-chip--soft" title="<?= $h($G['multi_guarantee']) ?>"><i class="fa-solid fa-shield"></i> 10× → <?= $h(gacha_rarity_label('epico', $gLang)) ?>+</span>
                                <?php endif; ?>
                            </div>

                            <div class="gacha-economy-row">
                                <div class="gacha-user-balance-panel">
                                    <div class="balance-item" title="<?= $h($G['godos_tip']) ?>" data-bs-toggle="tooltip">
                                        <span class="balance-icon"><img src="/img/godos.png" alt="Godos" class="currency-icon-img"></span>
                                        <span class="balance-label">Godos:</span>
                                        <span class="balance-value user-points-val"><?= $gFmt($gSoldi) ?></span>
                                    </div>
                                    <div class="balance-item" title="<?= $h($G['shards_tip']) ?>" data-bs-toggle="tooltip">
                                        <span class="balance-icon"><img src="/img/godoshards.png" alt="Godo Shards" class="currency-icon-img"></span>
                                        <span class="balance-label">Godo Shards:</span>
                                        <span class="balance-value user-shards-val"><?= $gFmt($gShards) ?></span>
                                    </div>
                                    <a href="shop" class="balance-shop-btn" title="<?= $h($G['buy_shards']) ?>" data-bs-toggle="tooltip">
                                        <i class="fa-solid fa-plus"></i>
                                    </a>
                                </div>
                                <span class="gacha-cost"><?= $b['costo'] > 0 ? $h(sprintf($G['cost'], $gFmt($b['costo']), $b['costo_shards'])) : $h($G['free']) ?></span>
                            </div>

                            <div class="gacha-pull-row">
                                <?php if ($b['costo'] > 0): ?>
                                    <button class="gacha-pull-btn" id="pull-btn-<?= $h($key) ?>" aria-label="<?= $h($G['open1'] . ' ' . $b['nome']) ?>" data-banner-id="<?= $h($key) ?>">
                                        <i class="fa-solid fa-star gacha-pull-btn-icon"></i>
                                        <div class="gacha-pull-btn-text">
                                            <span><?= $h($G['open1']) ?></span>
                                            <small class="gacha-pull-btn-cost" data-cost-single><?= $gFmt($b['costo']) ?> <img src="/img/godos.png" alt="Godos" class="cost-icon-img"> / <?= (int)$b['costo_shards'] ?> <img src="/img/godoshards.png" alt="Shards" class="cost-icon-img"></small>
                                        </div>
                                    </button>
                                    <button class="gacha-pull-btn gacha-pull-btn--multi" aria-label="<?= $h($G['multi10'] . ' ' . $b['nome']) ?>" data-banner-id="<?= $h($key) ?>" data-pull-qty="10">
                                        <i class="fa-solid fa-boxes-stacked gacha-pull-btn-icon"></i>
                                        <div class="gacha-pull-btn-text">
                                            <span><?= $h($G['multi10']) ?></span>
                                            <small class="gacha-pull-btn-cost"><?= $gFmt($b['costo'] * 10) ?> <img src="/img/godos.png" alt="Godos" class="cost-icon-img"> / <?= (int)ceil($b['costo'] * 10 / $gGps) ?> <img src="/img/godoshards.png" alt="Shards" class="cost-icon-img"></small>
                                        </div>
                                    </button>
                                <?php else: ?>
                                    <button class="gacha-pull-btn" id="pull-btn-<?= $h($key) ?>" aria-label="<?= $h($G['open1']) ?>" data-banner-id="<?= $h($key) ?>">
                                        <i class="fa-solid fa-box-open gacha-pull-btn-icon"></i>
                                        <span><?= $h($G['open1']) ?></span>
                                    </button>
                                    <button class="gacha-pull-btn gacha-pull-btn--multi" aria-label="<?= $h($G['open10']) ?>" data-banner-id="<?= $h($key) ?>" data-pull-qty="10">
                                        <i class="fa-solid fa-boxes-stacked gacha-pull-btn-icon"></i>
                                        <span><?= $h($G['open10']) ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="gacha-v2-soon-note"><i class="fa-solid fa-calendar"></i> <?= $h(sprintf($G['soon_note'], date($gEn ? 'M j, H:i' : 'd/m, H:i', strtotime($b['data_inizio'])))) ?></p>
                        <?php endif; ?>

                        <button type="button" class="gacha-v2-details-btn" data-banner-details="<?= $h($key) ?>">
                            <i class="fa-solid fa-circle-info"></i> <?= $h($G['details']) ?>
                        </button>
                    </div>
                </section>
            <?php endforeach; ?>
        </main>

        <aside class="gacha-sidebar<?= $gPremium ? ' has-premium' : '' ?>" id="gacha-sidebar" aria-label="<?= $h($G['sidebar']) ?>">

            <p class="gsb-section-label"><?= $h($G['sidebar']) ?></p>

            <div class="gsb-banners" id="gsb-banners">
                <?php
                $gSoonPrinted = false;
                $gOrdered = array_merge(
                    array_values(array_filter($gBanners, static fn($b) => $b['stato'] !== 'prossimamente')),
                    array_values(array_filter($gBanners, static fn($b) => $b['stato'] === 'prossimamente'))
                );
                foreach ($gOrdered as $b):
                    $key = $b['key'];
                    $isSoon = $b['stato'] === 'prossimamente';
                    if ($isSoon && !$gSoonPrinted):
                        $gSoonPrinted = true; ?>
                        <p class="gsb-section-label gacha-v2-soon-label"><?= $h($G['sidebar_soon']) ?></p>
                    <?php endif;
                    $tag = $isSoon ? $G['tag_soon'] : ($G['tag_' . $b['tipo']] ?? $G['tag_evento']);
                    $thumb = $b['thumb'] ?: ($b['featured'][0]['img'] ?? '/img/cassa.png');
                ?>
                    <button
                        class="gsb-card<?= $key === $gActive ? ' is-active' : '' ?><?= $isSoon ? ' gsb-card--soon' : '' ?>"
                        data-banner-id="<?= $h($key) ?>"
                        data-banner-type="<?= $b['tipo'] === 'standard' ? 'standard' : 'evento' ?>"
                        aria-pressed="<?= $key === $gActive ? 'true' : 'false' ?>"
                        aria-label="<?= $h($b['nome']) ?>">
                        <div class="gsb-card-bg" style="background-image:url('<?= $h($thumb) ?>')"></div>
                        <div class="gsb-card-overlay"></div>
                        <div class="gsb-card-body">
                            <span class="gsb-card-tag"><?= $h($tag) ?></span>
                            <span class="gsb-card-name"><?= $h($b['nome']) ?></span>
                            <?php if ($b['featured']): ?>
                                <span class="gsb-card-rateup"><?= $h(implode(' · ', array_column($b['featured'], 'nome'))) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="gsb-card-active-bar"></div>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php if ($gPremium): ?>
                <div class="gsb-premium-claim-box">
                    <div class="gsb-premium-claim-header">
                        <i class="fa-solid fa-gem premium-gem-icon"></i>
                        <span><?= $h($G['premium_claim']) ?></span>
                    </div>
                    <?php
                    $gToday = getMissionDailyPeriod();
                    $gClaimedToday = ($gLastClaim === $gToday);
                    $gSecondsLeft = strtotime('tomorrow') - time();
                    ?>
                    <button id="premium-claim-btn" class="gsb-premium-claim-btn <?= $gClaimedToday ? 'claimed' : '' ?>" <?= $gClaimedToday ? 'disabled' : '' ?> data-seconds-left="<?= $gSecondsLeft ?>">
                        <span class="btn-text"><?= $gClaimedToday ? $G['claimed'] : $h($G['claim']) ?></span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="gsb-actions">
                <button class="gsb-action-btn" id="btn-settings" aria-label="<?= $h($G['settings']) ?>">
                    <i class="fa-solid fa-gear"></i>
                    <span><?= $h($G['settings']) ?></span>
                </button>
                <button class="gsb-action-btn" onclick="toggleLeaderboard()" aria-label="<?= $h($G['leaderboard']) ?>">
                    <i class="fa-solid fa-trophy"></i>
                    <span><?= $h($G['leaderboard']) ?></span>
                </button>
                <a href="inventario" class="gsb-action-btn" aria-label="<?= $h($G['inventory']) ?>">
                    <i class="fa-solid fa-layer-group"></i>
                    <span><?= $h($G['inventory']) ?></span>
                </a>
                <button class="gsb-action-btn" onclick="openCurrentHistory()" aria-label="<?= $h($G['history']) ?>">
                    <i class="fa-solid fa-scroll"></i>
                    <span><?= $h($G['history']) ?></span>
                </button>
            </div>

        </aside>

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

    <div class="modal fade lootbox-settings-modal" id="impostazioniModal"
        tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable lootbox-settings-dialog">
            <div class="modal-content bgimpostazioni lootbox-settings-content">
                <div class="modal-header lootbox-settings-header">
                    <div>
                        <span class="lootbox-modal-kicker">Gacha</span>
                        <h5 class="modal-title"><?= $gEn ? 'Settings' : 'Impostazioni' ?></h5>
                        <p><?= $gEn ? 'Drop rates, controls, and quick functions.' : 'Probabilità, comandi e funzioni rapide.' ?></p>
                    </div>
                    <button type="button" class="lootbox-modal-close"
                        data-bs-dismiss="modal" aria-label="<?= $gEn ? 'Close settings' : 'Chiudi' ?>">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="modal-body lootbox-settings-body">

                    <section class="lootbox-settings-section">
                        <div class="lootbox-section-head">
                            <i class="fa-solid fa-keyboard"></i>
                            <div>
                                <h6><?= $gEn ? 'Controls' : 'Comandi' ?></h6>
                                <p><?= $gEn ? 'Quick shortcuts.' : 'Scorciatoie rapide.' ?></p>
                            </div>
                        </div>
                        <div class="lootbox-command-grid">
                            <div class="lootbox-command-item"><span>Space</span><strong><?= $gEn ? 'Normal Pull' : 'Pull normale' ?></strong></div>
                            <div class="lootbox-command-item"><span>Enter</span><strong><?= $gEn ? 'Open Again' : 'Apri ancora' ?></strong></div>
                            <div class="lootbox-command-item"><span>Esc</span><strong><?= $gEn ? 'Close Overlay' : 'Chiudi overlay' ?></strong></div>
                            <div class="lootbox-command-item"><span>S</span><strong><?= $gEn ? 'Skip Multi' : 'Salta multi' ?></strong></div>
                        </div>
                    </section>

                    <section class="lootbox-settings-section">
                        <div class="lootbox-section-head">
                            <i class="fa-solid fa-dice"></i>
                            <div>
                                <h6><?= $gEn ? 'Base rates' : 'Probabilità base' ?></h6>
                                <p><?= $gEn ? 'Each banner shows its own under “Details & rates”.' : 'Ogni banner ha le sue in «Dettagli e probabilità».' ?></p>
                            </div>
                        </div>
                        <div class="gacha-rates-grid">
                            <?php foreach ($gRateRows as [$rk, $cls]):
                                $w = gacha_rarity_defs()[$rk]['peso'];
                                $label = $rk === 'segreto' ? '???' : gacha_rarity_label($rk, $gLang);
                                $value = $w >= 5 ? round($w) . '%' : number_format($w, 2, $gEn ? '.' : ',', '') . '%';
                            ?>
                                <div class="gacha-rate-row <?= $cls ?>"><span><?= $h($label) ?></span><strong><?= $h($value) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="lootbox-settings-section">
                        <div class="lootbox-section-head">
                            <i class="fa-solid fa-chart-line"></i>
                            <div>
                                <h6><?= $gEn ? 'Pity System' : 'Sistema Pity' ?></h6>
                            </div>
                        </div>
                        <div class="lootbox-command-grid">
                            <div class="lootbox-command-item"><span><?= $gEn ? 'Standard Soft pity' : 'Soft pity standard' ?></span><strong>Pull <?= (int)$gProfiles['standard']['soft'] ?></strong></div>
                            <div class="lootbox-command-item"><span><?= $gEn ? 'Standard Hard pity' : 'Hard pity standard' ?></span><strong>Pull <?= (int)$gProfiles['standard']['hard'] ?></strong></div>
                            <div class="lootbox-command-item"><span><?= $gEn ? 'Limited Soft pity' : 'Soft pity evento' ?></span><strong>Pull <?= (int)$gProfiles['evento']['soft'] ?></strong></div>
                            <div class="lootbox-command-item"><span><?= $gEn ? 'Limited Hard pity' : 'Hard pity evento' ?></span><strong>Pull <?= (int)$gProfiles['evento']['hard'] ?></strong></div>
                        </div>
                    </section>

                    <?php if ($gIsAdmin): ?>
                        <section id="admin-cheats" class="lootbox-settings-section lootbox-admin-section">
                            <div class="lootbox-section-head">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                <div>
                                    <h6>Admin cheats</h6>
                                    <p><?= $gEn ? 'Force rarity (server-side).' : 'Force rarità (server-side).' ?></p>
                                </div>
                            </div>
                            <div class="lootbox-toggle-grid">
                                <?php foreach (
                                    [
                                        'forza-comune' => 'Solo Comuni',
                                        'forza-raro' => 'Solo Rari',
                                        'forza-epico' => 'Solo Epici',
                                        'forza-leggendario' => 'Solo Leggendari',
                                        'forza-speciale' => 'Solo Speciali',
                                        'forza-segreto' => 'Solo Segreti',
                                        'forza-theone' => 'Solo The One',
                                    ] as $id => $label
                                ): ?>
                                    <label class="lootbox-toggle-pill" for="<?= $id ?>">
                                        <input class="form-check-input admin-force-rarity" type="checkbox"
                                            id="<?= $id ?>" data-rarity="<?= str_replace('forza-', '', $id) ?>">
                                        <span><?= $label ?></span>
                                    </label>
                                <?php endforeach; ?>
                                <label class="lootbox-toggle-pill" for="forza-lobotomy">
                                    <input class="form-check-input admin-force-character" type="checkbox"
                                        id="forza-lobotomy" data-character-id="155">
                                    <span>Mod sono Lobotomy</span>
                                </label>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="lootbox-settings-section lootbox-code-section">
                        <div class="lootbox-section-head">
                            <i class="fa-solid fa-lock"></i>
                            <div>
                                <h6><?= $gEn ? 'Redeem Code' : 'Codice segreto' ?></h6>
                            </div>
                        </div>
                        <div class="lootbox-secret-row">
                            <label class="visually-hidden" for="codiceSegreto"><?= $gEn ? 'Redeem Code' : 'Codice Segreto' ?></label>
                            <input type="text" id="codiceSegreto" class="form-control"
                                placeholder="<?= $gEn ? 'Secret code' : 'Codice segreto' ?>" autocomplete="off"
                                onkeydown="if(event.key==='Enter')riscattaCodice()">
                            <button type="button" class="btn btn-secondary bottone lootbox-modal-btn"
                                id="btnRiscatta" onclick="riscattaCodice()">
                                <span id="btnRiscattaLabel"><?= $gEn ? 'Redeem' : 'Riscatta' ?></span>
                                <span id="btnRiscattaSpin" style="display:none"><i class="fa-solid fa-spinner fa-spin"></i></span>
                            </button>
                        </div>
                    </section>
                </div>
                <div class="modal-footer lootbox-settings-footer">
                    <button type="button"
                        class="btn btn-secondary bottone lootbox-modal-btn lootbox-modal-btn--ghost"
                        data-bs-dismiss="modal"><?= $gEn ? 'Close' : 'Chiudi' ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="leaderboard-wrapper" id="leaderboard-wrapper" style="display:none">
        <div class="leaderboard-box lootbox-leaderboard-box">
            <div class="leaderboard-head">
                <div>
                    <span class="leaderboard-kicker"><?= $gEn ? 'Leaderboard' : 'Classifica' ?></span>
                    <h3 class="testobianco">Top Gacha</h3>
                    <p><?= $gEn ? 'Top 10 players' : 'Le prime posizioni del momento.' ?></p>
                </div>
                <button class="leaderboard-close" type="button"
                    id="leaderboard-close-btn" aria-label="<?= $gEn ? 'Close' : 'Chiudi' ?>">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="leaderboard-buttons" role="group">
                <button class="btn btn-secondary bottone leaderboard-btn active"
                    id="btn-casse" onclick="switchLeaderboard('casse_aperte')">
                    <i class="fa-solid fa-box-open"></i> <span><?= $gEn ? 'Pull count' : 'Casse aperte' ?></span>
                </button>
                <button class="btn btn-secondary bottone leaderboard-btn"
                    id="btn-personaggi" onclick="switchLeaderboard('personaggi_sbloccati')">
                    <i class="fa-solid fa-layer-group"></i> <span><?= $gEn ? 'Characters' : 'Personaggi' ?></span>
                </button>
            </div>
            <div id="leaderboard-data" class="leaderboard-data">
                <div class="loading-text testobianco"><?= $gEn ? 'Loading...' : 'Caricamento...' ?></div>
            </div>
        </div>
    </div>

    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement">
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>

    <div class="modal fade shop-modal" id="gachaShopRedirectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: rgba(13, 10, 24, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; color: #fff;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                    <h5 class="modal-title"><?= $gEn ? 'Insufficient Balances' : 'Valute Insufficienti' ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div style="margin-bottom: 1rem;"><img src="/img/godoshards.png" alt="Godo Shards" style="width: 80px; height: 80px; object-fit: contain;"></div>
                    <p class="mb-4"><?= $gEn ? 'You do not have enough Godos or Godo Shards to complete this pull.' : 'Non hai abbastanza Godos o Godo Shards per completare questa pull.' ?></p>
                    <div class="d-grid gap-2 col-8 mx-auto">
                        <a href="/<?= $gLang ?>/shop.php" class="btn btn-primary" style="background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%); border: none; font-weight: 700; padding: 0.75rem;"><?= $gEn ? 'Visit Shop' : 'Visita lo Shop' ?></a>
                        <a href="/<?= $gLang ?>/shop.php#converti" class="btn btn-outline-light" style="font-weight: 700;"><?= $gEn ? 'Convert Godos to Shards' : 'Converti i Godos in Shards' ?></a>
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal"><?= $gEn ? 'Close' : 'Chiudi' ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gachaConversionModal" tabindex="-1" aria-labelledby="gachaConversionTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered gacha-conversion-dialog">
            <div class="modal-content gacha-conversion-card">
                <div class="gacha-conversion-glow" aria-hidden="true"></div>
                <div class="gacha-conversion-header">
                    <div>
                        <span class="gacha-conversion-kicker"><i class="fa-solid fa-wand-magic-sparkles"></i> <?= $gEn ? 'Confirm pull' : 'Conferma pull' ?></span>
                        <h5 class="gacha-conversion-title" id="gachaConversionTitle"><?= $gEn ? 'Complete your pull' : 'Completa la tua pull' ?></h5>
                    </div>
                    <button type="button" class="gacha-conversion-close" data-bs-dismiss="modal" aria-label="<?= $gEn ? 'Close' : 'Chiudi' ?>">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="gacha-conversion-body">
                    <p class="gacha-conversion-copy">
                        <?php if ($gEn): ?>
                            You need <strong><span class="conversion-shards-count">4</span> more Godo Shards</strong>.
                            Create them instantly using your Godos.
                        <?php else: ?>
                            Ti mancano <strong><span class="conversion-shards-count">4</span> Godo Shards</strong>.
                            Puoi crearli al volo usando i tuoi Godos.
                        <?php endif; ?>
                    </p>

                    <div class="gacha-conversion-flow" aria-label="<?= $gEn ? 'Conversion summary' : 'Riepilogo conversione' ?>">
                        <div class="gacha-conversion-currency gacha-conversion-currency--godos">
                            <span class="gacha-conversion-label"><?= $gEn ? 'Spend' : 'Spendi' ?></span>
                            <img src="/img/godos.png" alt="" class="gacha-conversion-icon">
                            <strong class="conversion-godos-cost">400</strong>
                            <small>Godos</small>
                        </div>

                        <div class="gacha-conversion-arrow" aria-hidden="true">
                            <i class="fa-solid fa-arrow-right"></i>
                        </div>

                        <div class="gacha-conversion-currency gacha-conversion-currency--shards">
                            <span class="gacha-conversion-label"><?= $gEn ? 'Receive' : 'Ricevi' ?></span>
                            <img src="/img/godoshards.png" alt="" class="gacha-conversion-icon">
                            <strong>+<span class="conversion-shards-count">4</span></strong>
                            <small>Godo Shards</small>
                        </div>
                    </div>

                    <div class="gacha-conversion-actions">
                        <button type="button" class="gacha-conversion-btn gacha-conversion-btn--ghost" data-bs-dismiss="modal"><?= $gEn ? 'Cancel' : 'Annulla' ?></button>
                        <button type="button" class="gacha-conversion-btn gacha-conversion-btn--primary btn-confirm-conversion">
                            <span><?= $gEn ? 'Convert & pull' : 'Converti e pulla' ?></span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
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
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

        const premiumBtn = document.getElementById('premium-claim-btn');
        if (premiumBtn) {
            const isEn = <?= $gEn ? 'true' : 'false' ?>;
            function startPremiumClaimCountdown(btn, seconds) {
                if (!btn) return;
                function formatTime(secs) {
                    if (secs <= 0) return "00:00:00";
                    const h = Math.floor(secs / 3600);
                    const m = Math.floor((secs % 3600) / 60);
                    const s = secs % 60;
                    return [h, m, s].map((v) => v.toString().padStart(2, '0')).join(':');
                }
                if (btn._countdownInterval) clearInterval(btn._countdownInterval);
                const update = () => {
                    if (seconds <= 0) {
                        clearInterval(btn._countdownInterval);
                        btn.classList.remove('claimed');
                        btn.disabled = false;
                        const btnText = btn.querySelector('.btn-text');
                        if (btnText) btnText.textContent = isEn ? 'Claim 500 Points' : 'Riscatta 500 Punti';
                        return;
                    }
                    const countdownSpan = btn.querySelector('.claim-countdown');
                    if (countdownSpan) countdownSpan.textContent = formatTime(seconds);
                    seconds--;
                };
                update();
                btn._countdownInterval = setInterval(update, 1000);
            }

            const secondsLeft = parseInt(premiumBtn.dataset.secondsLeft || 0, 10);
            if (premiumBtn.classList.contains('claimed')) startPremiumClaimCountdown(premiumBtn, secondsLeft);

            premiumBtn.addEventListener('click', async () => {
                try {
                    premiumBtn.disabled = true;
                    const res = await fetch('/api/premium_daily_claim.php', {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' }
                    });
                    const data = await res.json();
                    if (data.success) {
                        premiumBtn.classList.add('claimed');
                        const btnText = premiumBtn.querySelector('.btn-text');
                        if (btnText) {
                            btnText.innerHTML = isEn
                                ? 'Claimed today (Reset in <span class="claim-countdown">--:--:--</span>)'
                                : 'Riscattato oggi (Ricarica tra <span class="claim-countdown">--:--:--</span>)';
                        }
                        startPremiumClaimCountdown(premiumBtn, parseInt(data.seconds_left || 86400, 10));
                        window.GachaUI?.setSoldi?.(data.new_soldi);
                        window.GachaUI?.showToast?.(data.message, 'success');
                    } else {
                        premiumBtn.disabled = false;
                        window.GachaUI?.showToast?.(data.error || (isEn ? 'Error during claim' : 'Errore durante il riscatto'), 'error');
                    }
                } catch (e) {
                    premiumBtn.disabled = false;
                    window.GachaUI?.showToast?.(isEn ? 'Network or server error' : 'Errore di rete o del server', 'error');
                }
            });
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
    <script src="<?= $h(cripsum_asset('/js/unlockAchievement-' . $gLang . '.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/js/gacha-effects.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/js/gacha.js')) ?>"></script>

    <script>
        function openCurrentHistory() {
            const bid = window.GACHA_INIT?.activeBannerId ?? 'standard';
            const banner = window.GACHA_INIT?.banners?.find((b) => String(b.id) === String(bid));
            window.GachaHistory?.open(bid, banner?.nome ?? <?= json_encode($G['standard_name']) ?>);
        }
        let currentLeaderboardType = 'casse_aperte';
        let leaderboardVisible = false;
        const LB = <?= json_encode($gEn
            ? ['loading' => 'Loading...', 'empty' => 'No data available', 'error' => 'Connection error', 'boxes' => 'pulls', 'chars' => 'characters']
            : ['loading' => 'Caricamento...', 'empty' => 'Nessun dato disponibile', 'error' => 'Errore connessione', 'boxes' => 'casse', 'chars' => 'personaggi'], JSON_UNESCAPED_UNICODE) ?>;

        function toggleLeaderboard() {
            const wrapper = document.getElementById('leaderboard-wrapper');
            leaderboardVisible = !leaderboardVisible;
            wrapper.style.display = leaderboardVisible ? 'flex' : 'none';
            if (leaderboardVisible) loadLeaderboard(currentLeaderboardType);
        }

        async function loadLeaderboard(type) {
            const dataDiv = document.getElementById('leaderboard-data');
            dataDiv.innerHTML = `<div class="loading-text testobianco"><i class="fa-solid fa-circle-notch fa-spin"></i><span>${LB.loading}</span></div>`;
            try {
                const r = await fetch(`/api/get_leaderboard?type=${type}`);
                const d = await r.json();
                if (d.status === 'success' && d.data.length > 0) {
                    displayLeaderboard(d.data, type);
                } else {
                    dataDiv.innerHTML = `<div class="loading-text testobianco"><i class="fa-solid fa-ranking-star"></i><span>${LB.empty}</span></div>`;
                }
            } catch {
                dataDiv.innerHTML = `<div class="loading-text testobianco is-error"><i class="fa-solid fa-triangle-exclamation"></i><span>${LB.error}</span></div>`;
            }
        }

        function lbEscape(value) {
            return String(value ?? '').replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[c]));
        }

        function displayLeaderboard(data, type) {
            const lbl = type === 'casse_aperte' ? LB.boxes : LB.chars;
            document.getElementById('leaderboard-data').innerHTML = data.map(item => {
                const medal = { 1: '🥇 ', 2: '🥈 ', 3: '🥉 ' }[item.position] ?? '';
                const cls = { 1: 'gold', 2: 'silver', 3: 'bronze' }[item.position] ?? '';
                return `<div class="leaderboard-entry ${cls}">
            <span class="entry-position testobianco">${medal}${item.position}</span>
            <span class="entry-user-wrap"><span class="entry-username testobianco">${lbEscape(item.username)}${item.is_premium ? ' <span class="premium-badge-icon" title="Premium"><i class="fa-solid fa-gem"></i></span>' : ''}</span><small>${lbl}</small></span>
            <span class="entry-value">${lbEscape(item.value)}</span>
        </div>`;
            }).join('');
        }

        function switchLeaderboard(type) {
            currentLeaderboardType = type;
            document.querySelectorAll('.leaderboard-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(type === 'casse_aperte' ? 'btn-casse' : 'btn-personaggi').classList.add('active');
            loadLeaderboard(type);
        }

        document.getElementById('leaderboard-close-btn').addEventListener('click', toggleLeaderboard);
        document.addEventListener('click', e => {
            if (leaderboardVisible && e.target.id === 'leaderboard-wrapper') toggleLeaderboard();
        });

        async function riscattaCodice() {
            const input = document.getElementById('codiceSegreto');
            const codice = input.value.trim();
            if (!codice) return;
            const btn = document.getElementById('btnRiscatta');
            const label = document.getElementById('btnRiscattaLabel');
            const spin = document.getElementById('btnRiscattaSpin');
            btn.disabled = true;
            label.style.display = 'none';
            spin.style.display = 'inline';

            try {
                const resp = await fetch('/api/api_redeem_code', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        codice,
                        lang: <?= json_encode($gLang) ?>,
                        csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }),
                    credentials: 'same-origin',
                });
                const data = await resp.json();

                if (data.status !== 'success') {
                    window.GachaUI?.showToast(data.message ?? <?= json_encode($gEn ? 'Error occurred.' : 'Errore riscatto.') ?>, 'error');
                    return;
                }

                input.value = '';

                if (data.tipo === 'personaggio') {
                    bootstrap.Modal.getInstance(document.getElementById('impostazioniModal'))?.hide();
                    window.GachaUI?.openRevealWithData(data.personaggio);
                } else if (data.tipo === 'punti') {
                    if (data.soldi_rimasti != null) window.GachaUI?.setSoldi?.(data.soldi_rimasti);
                    const desc = data.descrizione ?? <?= $gEn ? '`+${data.punti} points!`' : '`+${data.punti} punti!`' ?>;
                    window.GachaUI?.showToast(`🎁 ${desc}`, 'success');
                }
            } catch {
                window.GachaUI?.showToast(<?= json_encode($gEn ? 'Error occurred. Please try again.' : 'Errore riscatto. Riprova.') ?>, 'error');
            } finally {
                btn.disabled = false;
                label.style.display = 'inline';
                spin.style.display = 'none';
            }
        }
    </script>

</body>

</html>
