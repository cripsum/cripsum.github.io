<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mission_generator.php';
require_once __DIR__ . '/../includes/gacha_helpers.php';
checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = 'Devi essere loggato per accedere alle Lootbox';
    header('Location: accedi');
    exit();
}
checkPermissions($mysqli, 'utente');

$ruolo   = $_SESSION['ruolo'] ?? 'utente';
$isAdmin = in_array($ruolo, ['admin', 'owner'], true);

$stmtUser = $mysqli->prepare(
    'SELECT username, soldi, godoshards_balance, pity_standard, pity_evento, garantito_evento, is_premium, last_premium_claim
     FROM utenti WHERE id = ? LIMIT 1'
);
$stmtUser->bind_param('i', $_SESSION['user_id']);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$soldi        = (int)($userData['soldi']           ?? 0);
$godoshards   = (int)($userData['godoshards_balance'] ?? 0);
$pityStandard = (int)($userData['pity_standard']   ?? 0);
$pityEvento   = (int)($userData['pity_evento']     ?? 0);
$garantito    = (int)($userData['garantito_evento'] ?? 0);
$isPremium    = (int)($userData['is_premium']       ?? 0);
$lastPremiumClaim = $userData['last_premium_claim'] ?? null;

$nowDt = date('Y-m-d H:i:s');
$pCols = gacha_character_columns($mysqli);
$beCols = gacha_event_columns($mysqli);

$stmtBanners = $mysqli->prepare(
    "SELECT be.id, be.{$beCols['slug']} AS slug, be.{$beCols['name']} AS nome, be.{$beCols['description']} AS descrizione, be.{$beCols['rateup']} AS id_personaggio_rateup,
            be.{$beCols['image']} AS banner_img_url, be.{$beCols['cost']} AS costo_punti, be.{$beCols['ends']} AS data_fine,
            p.{$pCols['name']} AS rateup_nome, p.{$pCols['rarity']} AS rateup_rarità,
            p.{$pCols['image']} AS rateup_img_url, p.{$pCols['description']} AS rateup_desc,
            p.{$pCols['features']} AS rateup_chars
     FROM banner_eventi be
     INNER JOIN personaggi p ON p.id = be.{$beCols['rateup']}
     WHERE be.{$beCols['active']} = 1
       AND (be.{$beCols['starts']} IS NULL OR be.{$beCols['starts']} <= ?)
       AND (be.{$beCols['ends']}   IS NULL OR be.{$beCols['ends']}   >= ?)
     ORDER BY be.id ASC"
);
$stmtBanners->bind_param('ss', $nowDt, $nowDt);
$stmtBanners->execute();
$bannersEvento = [];
$resBanners = $stmtBanners->get_result();
while ($row = $resBanners->fetch_assoc()) {
    $bannersEvento[] = $row;
}
$stmtBanners->close();

defined('PITY_STANDARD_HARD') || define('PITY_STANDARD_HARD', 90);
defined('PITY_STANDARD_SOFT') || define('PITY_STANDARD_SOFT', 70);
defined('PITY_EVENTO_HARD') || define('PITY_EVENTO_HARD',   80);
defined('PITY_EVENTO_SOFT') || define('PITY_EVENTO_SOFT',   65);
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <link rel="stylesheet" href="/css/lootbox.css?v=8.3">
    <link rel="stylesheet" href="/css/gacha.css?v=24">
    <meta name="theme-color" content="#080810">
    <title>Cripsum™ — Lootbox</title>
</head>

<body class="lootbox-page<?= $isPremium ? ' has-premium' : '' ?>" data-ruolo="<?= htmlspecialchars($ruolo, ENT_QUOTES) ?>">

    <?php include '../includes/navbar-lootbox.php'; ?>

    <div class="stars" id="stars"></div>

    <div class="gacha-layout" id="gacha-layout">

        <main class="gacha-main" id="gacha-main">

            <section
                class="gacha-banner-view"
                id="banner-view-standard"
                data-banner-id="standard"
                data-banner-type="standard"
                data-pity-hard="<?= PITY_STANDARD_HARD ?>"
                data-pity-soft="<?= PITY_STANDARD_SOFT ?>"
                data-costo="0"
                aria-label="Banner Standard">
                <div class="gacha-banner-bg has-img" id="banner-bg-standard"
                    style="background-image:url('/img/banner_standard_bg.jpg')"></div>

                <div class="gacha-banner-art-wrap" aria-hidden="true">
                    <img src="/img/cassa.png" alt="Cassa" class="gacha-banner-char"
                        id="banner-char-standard" draggable="false">
                </div>

                <div class="gacha-banner-info">
                    <div>
                        <span class="gacha-banner-type-badge">✦ SEMPRE DISPONIBILE</span>
                        <h1 class="gacha-banner-title">Banner Standard</h1>
                        <p class="gacha-banner-desc">Il banner classico di Cripsum™ dove puoi trovare tutti i personaggi originali delle vecchie lootbox</p>
                    </div>

                    <div class="gacha-pity-wrap">
                        <div class="gacha-pity-header">
                            <span>Pity Standard</span>
                            <span id="pity-std-num"><?= $pityStandard ?> / <?= PITY_STANDARD_HARD ?></span>
                        </div>
                        <div class="gacha-pity-track">
                            <div class="gacha-pity-fill" id="pity-std-fill"
                                style="width:<?= min(100, round($pityStandard / PITY_STANDARD_HARD * 100)) ?>%"></div>
                            <div class="gacha-pity-soft-marker"
                                style="left:<?= round(PITY_STANDARD_SOFT / PITY_STANDARD_HARD * 100) ?>%"></div>
                        </div>
                        <p class="gacha-pity-note <?= $pityStandard >= PITY_STANDARD_SOFT ? 'is-active' : '' ?>" id="pity-std-note">
                            <?php if ($pityStandard >= PITY_STANDARD_HARD): ?>
                                ★ Garantito: prossima pull è Speciale o Segreto!
                            <?php elseif ($pityStandard >= PITY_STANDARD_SOFT): ?>
                                ✦ Soft pity — % Speciale o Segreto aumentata
                            <?php else: ?>
                                Garantito Speciale o Segreto in <?= PITY_STANDARD_HARD - $pityStandard ?> pull
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="gacha-economy-row">
                        <div class="gacha-user-balance-panel">
                            <div class="balance-item" title="Valuta gratuita ottenibile usando il sito." data-bs-toggle="tooltip">
                                <span class="balance-icon"><img src="/img/godos.png" alt="Godos" class="currency-icon-img"></span>
                                <span class="balance-label">Godos:</span>
                                <span class="balance-value user-points-val"><?= number_format($soldi) ?></span>
                            </div>
                            <div class="balance-item" title="Valuta premium usata per pullare." data-bs-toggle="tooltip">
                                <span class="balance-icon"><img src="/img/godoshards.png" alt="Godo Shards" class="currency-icon-img"></span>
                                <span class="balance-label">Godo Shards:</span>
                                <span class="balance-value user-shards-val"><?= number_format($godoshards) ?></span>
                            </div>
                            <a href="shop" class="balance-shop-btn" title="Acquista Godo Shards" data-bs-toggle="tooltip">
                                <i class="fa-solid fa-plus"></i>
                            </a>
                        </div>
                        <span class="gacha-cost">• Gratuito</span>
                    </div>

                    <div class="gacha-pull-row">
                        <button class="gacha-pull-btn" id="pull-btn-standard"
                            aria-label="Apri 1x banner standard" data-banner-id="standard">
                            <i class="fa-solid fa-box-open gacha-pull-btn-icon"></i>
                            <span>Apri 1×</span>
                        </button>
                        <button class="gacha-pull-btn gacha-pull-btn--multi"
                            aria-label="Apri 10x banner standard"
                            data-banner-id="standard" data-pull-qty="10">
                            <i class="fa-solid fa-boxes-stacked gacha-pull-btn-icon"></i>
                            <span>Apri 10×</span>
                        </button>
                    </div>
                </div>
            </section>

            <?php foreach ($bannersEvento as $b):
                $bid       = (int)$b['id'];
                $safeName  = htmlspecialchars($b['nome'],           ENT_QUOTES, 'UTF-8');
                $safeDesc  = htmlspecialchars($b['descrizione'] ?? '', ENT_QUOTES, 'UTF-8');
                $safeRateup = htmlspecialchars($b['rateup_nome'],    ENT_QUOTES, 'UTF-8');
                $safeRar   = strtolower(trim($b['rateup_rarità'] ?? ''));
                $safeRarIt = htmlspecialchars($b['rateup_rarità'] ?? '', ENT_QUOTES, 'UTF-8');
                $safeImg   = htmlspecialchars($b['rateup_img_url'] ?? '', ENT_QUOTES, 'UTF-8');
                $safeDesc2 = htmlspecialchars($b['rateup_desc'] ?? '', ENT_QUOTES, 'UTF-8');
                $bgImg     = htmlspecialchars($b['banner_img_url'] ?? '', ENT_QUOTES, 'UTF-8');
                $costo     = (int)$b['costo_punti'];
                $dataFine  = $b['data_fine'] ?? null;
            ?>
                <section
                    class="gacha-banner-view"
                    id="banner-view-<?= $bid ?>"
                    data-banner-id="<?= $bid ?>"
                    data-banner-type="evento"
                    data-pity-hard="<?= PITY_EVENTO_HARD ?>"
                    data-pity-soft="<?= PITY_EVENTO_SOFT ?>"
                    data-costo="<?= $costo ?>"
                    data-data-fine="<?= htmlspecialchars($dataFine ?? '', ENT_QUOTES) ?>"
                    style="display:none"
                    aria-label="Banner <?= $safeName ?>">
                    <div class="gacha-banner-bg <?= $bgImg ? 'has-img' : '' ?>" id="banner-bg-<?= $bid ?>"
                        <?php if ($bgImg): ?>style="background-image:url('/img/<?= $bgImg ?>')" <?php endif; ?>></div>

                    <div class="gacha-banner-art-wrap" aria-hidden="true">
                        <?php if ($safeImg): ?>
                            <img src="/img/<?= $safeImg ?>" alt="<?= $safeRateup ?>" class="gacha-banner-char"
                                draggable="false" onerror="this.src='/img/cassa.png'">
                        <?php else: ?>
                            <img src="/img/cassa.png" alt="Cassa" class="gacha-banner-char" draggable="false">
                        <?php endif; ?>
                    </div>

                    <div class="gacha-banner-info">
                        <div>
                            <span class="gacha-banner-type-badge">✦ BANNER EVENTO</span>
                            <h1 class="gacha-banner-title"><?= $safeName ?></h1>
                            <?php if ($safeDesc): ?>
                                <p class="gacha-banner-desc"><?= $safeDesc ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="gacha-rateup-info">
                            <span class="gacha-rateup-label">Rate-Up ✦</span>
                            <p class="gacha-rateup-name"><?= $safeRateup ?></p>
                            <p class="gacha-rateup-rarity rarity-<?= htmlspecialchars($safeRar) ?>"><?= $safeRarIt ?></p>
                            <?php if ($safeDesc2): ?>
                                <p class="gacha-rateup-char-desc"><?= $safeDesc2 ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="gacha-garantito-badge" id="garantito-badge-<?= $bid ?>"
                            <?= !$garantito ? 'style="display:none"' : '' ?>>
                            <i class="fa-solid fa-shield-halved"></i>
                            Garantito attivo — prossima rara è il rate-up
                        </div>

                        <div class="gacha-pity-wrap">
                            <div class="gacha-pity-header">
                                <span>Pity Evento</span>
                                <span class="pity-evt-num"><?= $pityEvento ?> / <?= PITY_EVENTO_HARD ?></span>
                            </div>
                            <div class="gacha-pity-track">
                                <div class="gacha-pity-fill pity-evt-fill"
                                    style="width:<?= min(100, round($pityEvento / PITY_EVENTO_HARD * 100)) ?>%"></div>
                                <div class="gacha-pity-soft-marker"
                                    style="left:<?= round(PITY_EVENTO_SOFT / PITY_EVENTO_HARD * 100) ?>%"></div>
                            </div>
                            <p class="gacha-pity-note pity-evt-note <?= $pityEvento >= PITY_EVENTO_SOFT ? 'is-active' : '' ?>">
                                <?php if ($pityEvento >= PITY_EVENTO_SOFT): ?>
                                    ✦ Soft pity attivo — probabilità in aumento
                                <?php else: ?>
                                    Garantito segreto in <?= PITY_EVENTO_HARD - $pityEvento ?> pull
                                <?php endif; ?>
                            </p>
                        </div>

                        <?php if ($dataFine): ?>
                            <div class="gacha-timer-wrap">
                                <i class="fa-solid fa-clock"></i>
                                <span>Scade tra</span>
                                <div class="gacha-timer-digits" data-ends="<?= htmlspecialchars($dataFine, ENT_QUOTES) ?>">
                                    <div class="gacha-timer-block"><span class="t-days">--</span><small>gg</small></div>
                                    <div class="gacha-timer-block"><span class="t-hours">--</span><small>ore</small></div>
                                    <div class="gacha-timer-block"><span class="t-mins">--</span><small>min</small></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="gacha-economy-row">
                            <div class="gacha-user-balance-panel">
                                <div class="balance-item" title="Valuta gratuita ottenibile usando il sito." data-bs-toggle="tooltip">
                                    <span class="balance-icon"><img src="/img/godos.png" alt="Godos" class="currency-icon-img"></span>
                                    <span class="balance-label">Godos:</span>
                                    <span class="balance-value user-points-val"><?= number_format($soldi) ?></span>
                                </div>
                                <div class="balance-item" title="Valuta premium usata per pullare." data-bs-toggle="tooltip">
                                    <span class="balance-icon"><img src="/img/godoshards.png" alt="Godo Shards" class="currency-icon-img"></span>
                                    <span class="balance-label">Godo Shards:</span>
                                    <span class="balance-value user-shards-val"><?= number_format($godoshards) ?></span>
                                </div>
                                <a href="shop" class="balance-shop-btn" title="Acquista Godo Shards" data-bs-toggle="tooltip">
                                    <i class="fa-solid fa-plus"></i>
                                </a>
                            </div>
                            <span class="gacha-cost">• Costo: <?= number_format($costo) ?> Godos o <?= (int)ceil($costo / 100) ?> Shard</span>
                        </div>

                        <div class="gacha-pull-row">
                            <button class="gacha-pull-btn" id="pull-btn-<?= $bid ?>"
                                aria-label="Apri 1x <?= $safeName ?>" data-banner-id="<?= $bid ?>">
                                <i class="fa-solid fa-star gacha-pull-btn-icon"></i>
                                <div class="gacha-pull-btn-text">
                                    <span>Apri 1×</span>
                                    <small class="gacha-pull-btn-cost"><?= number_format($costo) ?> <img src="/img/godos.png" alt="Godos" class="cost-icon-img"> / <?= (int)ceil($costo / 100) ?> <img src="/img/godoshards.png" alt="Shards" class="cost-icon-img"></small>
                                </div>
                            </button>
                            <button class="gacha-pull-btn gacha-pull-btn--multi"
                                aria-label="Apri 10x <?= $safeName ?>"
                                data-banner-id="<?= $bid ?>" data-pull-qty="10">
                                <i class="fa-solid fa-boxes-stacked gacha-pull-btn-icon"></i>
                                <div class="gacha-pull-btn-text">
                                    <span>Multi 10×</span>
                                    <small class="gacha-pull-btn-cost"><?= number_format($costo * 10) ?> <img src="/img/godos.png" alt="Godos" class="cost-icon-img"> / <?= (int)ceil($costo / 10) ?> <img src="/img/godoshards.png" alt="Shards" class="cost-icon-img"></small>
                                </div>
                            </button>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>

        </main>

        <aside class="gacha-sidebar<?= $isPremium ? ' has-premium' : '' ?>" id="gacha-sidebar" aria-label="Selezione banner">

            <p class="gsb-section-label">Banner</p>

            <div class="gsb-banners" id="gsb-banners">

                <button
                    class="gsb-card is-active"
                    data-banner-id="standard"
                    data-banner-type="standard"
                    aria-pressed="true"
                    aria-label="Banner Standard">
                    <div class="gsb-card-bg" style="background-image:url('/img/banner_standard_bg.jpg')"></div>
                    <div class="gsb-card-overlay"></div>
                    <div class="gsb-card-body">
                        <span class="gsb-card-tag">Standard</span>
                        <span class="gsb-card-name">Banner Standard</span>
                    </div>
                    <div class="gsb-card-active-bar"></div>
                </button>

                <?php foreach ($bannersEvento as $b):
                    $bid2    = (int)$b['id'];
                    $bgThumb = htmlspecialchars($b['banner_img_url'] ?? '', ENT_QUOTES, 'UTF-8');
                    $rImg    = htmlspecialchars($b['rateup_img_url'] ?? '', ENT_QUOTES, 'UTF-8');
                    $thumb   = $bgThumb ? "/img/{$bgThumb}" : ($rImg ? "/img/{$rImg}" : '/img/cassa.png');
                ?>
                    <button
                        class="gsb-card"
                        data-banner-id="<?= $bid2 ?>"
                        data-banner-type="evento"
                        aria-pressed="false"
                        aria-label="Banner <?= htmlspecialchars($b['nome'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="gsb-card-bg" style="background-image:url('<?= $thumb ?>')"></div>
                        <div class="gsb-card-overlay"></div>
                        <div class="gsb-card-body">
                            <span class="gsb-card-tag">Evento</span>
                            <span class="gsb-card-name"><?= htmlspecialchars($b['nome'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="gsb-card-rateup"><?= htmlspecialchars($b['rateup_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="gsb-card-active-bar"></div>
                    </button>
                <?php endforeach; ?>

            </div>

            <?php if ($isPremium): ?>
                <div class="gsb-premium-claim-box">
                    <div class="gsb-premium-claim-header">
                        <i class="fa-solid fa-gem premium-gem-icon"></i>
                        <span>Riscatto Premium</span>
                    </div>
                    <?php
                    $today = getMissionDailyPeriod();
                    $hasClaimedToday = ($lastPremiumClaim === $today);
                    $secondsLeft = strtotime('tomorrow') - time();
                    ?>
                    <button id="premium-claim-btn" class="gsb-premium-claim-btn <?= $hasClaimedToday ? 'claimed' : '' ?>" <?= $hasClaimedToday ? 'disabled' : '' ?> data-seconds-left="<?= $secondsLeft ?>">
                        <span class="btn-text">
                            <?php if ($hasClaimedToday): ?>
                                Riscattato oggi (Ricarica tra <span class="claim-countdown">--:--:--</span>)
                            <?php else: ?>
                                Riscatta 500 Punti
                            <?php endif; ?>
                        </span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="gsb-actions">
                <button class="gsb-action-btn" id="btn-settings" aria-label="Impostazioni">
                    <i class="fa-solid fa-gear"></i>
                    <span>Impostazioni</span>
                </button>
                <button class="gsb-action-btn" onclick="toggleLeaderboard()" aria-label="Classifica">
                    <i class="fa-solid fa-trophy"></i>
                    <span>Classifica</span>
                </button>
                <a href="inventario" class="gsb-action-btn" aria-label="Inventario">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Inventario</span>
                </a>
                <button class="gsb-action-btn" onclick="openCurrentHistory()" aria-label="Cronologia">
                    <i class="fa-solid fa-scroll"></i>
                    <span>Cronologia</span>
                </button>
            </div>

        </aside>

    </div>

    <div class="gacha-overlay" id="gacha-overlay" role="dialog"
        aria-modal="true" aria-label="Risultato pull" aria-live="polite">

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
                <i class="fa-solid fa-volume-xmark"></i> Tap per audio
            </button>
        </div>

        <div class="gacha-phase gacha-phase--card" id="phase-card" style="display:none">
            <div class="gacha-card" id="gacha-card" aria-live="polite">
                <div class="gacha-card-bg-glow" id="card-bg-glow"></div>

                <div class="gacha-card-frame" id="card-frame">
                    <div class="gacha-card-img-wrap" id="card-img-wrap">
                        <img id="card-img" class="card-img-godo" src="/img/cassa.png" alt="Personaggio"
                            draggable="false" onerror="this.src='/img/cassa.png'">
                    </div>
                    <div class="gacha-card-img-shine"></div>
                    <span class="gacha-card-new-badge" id="card-new-badge" style="display:none">NEW!</span>
                    <span class="gacha-card-50-badge gacha-card-50-badge--win" id="card-50-win" style="display:none">
                        <i class="fa-solid fa-trophy"></i> Rate-Up Vinto!
                    </span>
                    <span class="gacha-card-50-badge gacha-card-50-badge--loss" id="card-50-loss" style="display:none">
                        Garantito attivato per la prossima pull
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
                    <i class="fa-solid fa-rotate-right"></i> Apri ancora
                </button>
                <button class="gacha-btn gacha-btn--ghost" id="btn-close-overlay">
                    <i class="fa-solid fa-xmark"></i> Chiudi
                </button>
                <a href="inventario" class="gacha-btn gacha-btn--ghost" id="btn-go-inventory">
                    <i class="fa-solid fa-layer-group"></i> Vedi inventario
                </a>
            </div>
        </div>

    </div>

    <div class="gacha-toast" id="gacha-toast" role="alert" aria-live="assertive"></div>

    <audio id="gacha-audio" preload="none"></audio>

    <!-- Controlli Audio/Volume Floating -->
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
                        <h5 class="modal-title">Impostazioni</h5>
                        <p>Probabilità, comandi e funzioni rapide.</p>
                    </div>
                    <button type="button" class="lootbox-modal-close"
                        data-bs-dismiss="modal" aria-label="Chiudi">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="modal-body lootbox-settings-body">

                    <section class="lootbox-settings-section">
                        <div class="lootbox-section-head">
                            <i class="fa-solid fa-keyboard"></i>
                            <div>
                                <h6>Comandi</h6>
                                <p>Scorciatoie rapide.</p>
                            </div>
                        </div>
                        <div class="lootbox-command-grid">
                            <div class="lootbox-command-item"><span>Space</span><strong>Pull normale</strong></div>
                            <!-- <div class="lootbox-command-item"><span>F</span><strong>Apertura rapida</strong></div> -->
                            <div class="lootbox-command-item"><span>Enter</span><strong>Apri ancora</strong></div>
                            <div class="lootbox-command-item"><span>Esc</span><strong>Chiudi overlay</strong></div>
                            <div class="lootbox-command-item"><span>S</span><strong>Salta multi</strong></div>
                        </div>
                    </section>

                    <section class="lootbox-settings-section">
                        <div class="lootbox-section-head">
                            <i class="fa-solid fa-dice"></i>
                            <div>
                                <h6>Probabilità base</h6>
                            </div>
                        </div>
                        <div class="gacha-rates-grid">
                            <div class="gacha-rate-row rate-common"><span>Comune</span><strong>51%</strong></div>
                            <div class="gacha-rate-row rate-rare"><span>Raro</span><strong>28%</strong></div>
                            <div class="gacha-rate-row rate-epic"><span>Epico</span><strong>13%</strong></div>
                            <div class="gacha-rate-row rate-legendary"><span>Leggendario</span><strong>6%</strong></div>
                            <div class="gacha-rate-row rate-special"><span>Speciale</span><strong>1.80%</strong></div>
                            <div class="gacha-rate-row rate-secret"><span>???</span><strong>0.20%</strong></div>
                        </div>
                    </section>

                    <section class="lootbox-settings-section">
                        <div class="lootbox-section-head">
                            <i class="fa-solid fa-chart-line"></i>
                            <div>
                                <h6>Sistema Pity</h6>
                            </div>
                        </div>
                        <div class="lootbox-command-grid">
                            <div class="lootbox-command-item"><span>Soft pity standard</span><strong>Pull 70</strong></div>
                            <div class="lootbox-command-item"><span>Hard pity standard</span><strong>Pull 90</strong></div>
                            <div class="lootbox-command-item"><span>Soft pity evento</span><strong>Pull 65</strong></div>
                            <div class="lootbox-command-item"><span>Hard pity evento</span><strong>Pull 80</strong></div>
                        </div>
                    </section>

                    <?php if ($isAdmin): ?>
                        <section id="admin-cheats" class="lootbox-settings-section lootbox-admin-section">
                            <div class="lootbox-section-head">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                <div>
                                    <h6>Admin cheats</h6>
                                    <p>Force rarità (server-side).</p>
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
                                <h6>Codice segreto</h6>
                            </div>
                        </div>
                        <div class="lootbox-secret-row">
                            <label class="visually-hidden" for="codiceSegreto">Codice Segreto</label>
                            <input type="text" id="codiceSegreto" class="form-control"
                                placeholder="Codice segreto" autocomplete="off"
                                onkeydown="if(event.key==='Enter')riscattaCodice()">
                            <button type="button" class="btn btn-secondary bottone lootbox-modal-btn"
                                id="btnRiscatta" onclick="riscattaCodice()">
                                <span id="btnRiscattaLabel">Riscatta</span>
                                <span id="btnRiscattaSpin" style="display:none"><i class="fa-solid fa-spinner fa-spin"></i></span>
                            </button>
                        </div>
                    </section>
                </div>
                <div class="modal-footer lootbox-settings-footer">
                    <button type="button"
                        class="btn btn-secondary bottone lootbox-modal-btn lootbox-modal-btn--ghost"
                        data-bs-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>

    <div class="leaderboard-wrapper" id="leaderboard-wrapper" style="display:none">
        <div class="leaderboard-box lootbox-leaderboard-box">
            <div class="leaderboard-head">
                <div>
                    <span class="leaderboard-kicker">Classifica</span>
                    <h3 class="testobianco">Top Gacha</h3>
                    <p>Le prime posizioni del momento.</p>
                </div>
                <button class="leaderboard-close" type="button"
                    id="leaderboard-close-btn" aria-label="Chiudi">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="leaderboard-buttons" role="group">
                <button class="btn btn-secondary bottone leaderboard-btn active"
                    id="btn-casse" onclick="switchLeaderboard('casse_aperte')">
                    <i class="fa-solid fa-box-open"></i> <span>Casse aperte</span>
                </button>
                <button class="btn btn-secondary bottone leaderboard-btn"
                    id="btn-personaggi" onclick="switchLeaderboard('personaggi_sbloccati')">
                    <i class="fa-solid fa-layer-group"></i> <span>Personaggi</span>
                </button>
            </div>
            <div id="leaderboard-data" class="leaderboard-data">
                <div class="loading-text testobianco">Caricamento...</div>
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

    <!-- Modal Insufficient Godo Shards / Godos -->
    <div class="modal fade shop-modal" id="gachaShopRedirectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: rgba(13, 10, 24, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; color: #fff;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                    <h5 class="modal-title">Valute Insufficienti</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div style="margin-bottom: 1rem;"><img src="/img/godoshards.png" alt="Godo Shards" style="width: 80px; height: 80px; object-fit: contain;"></div>
                    <p class="mb-4">Non hai abbastanza Godos o Godo Shards per completare questa pull.</p>
                    <div class="d-grid gap-2 col-8 mx-auto">
                        <a href="/it/shop.php" class="btn btn-primary" style="background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%); border: none; font-weight: 700; padding: 0.75rem;">Visita lo Shop</a>
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Chiudi</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Conversione Valuta -->
    <div class="modal fade" id="gachaConversionModal" tabindex="-1" aria-labelledby="gachaConversionTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered gacha-conversion-dialog">
            <div class="modal-content gacha-conversion-card">
                <div class="gacha-conversion-glow" aria-hidden="true"></div>
                <div class="gacha-conversion-header">
                    <div>
                        <span class="gacha-conversion-kicker"><i class="fa-solid fa-wand-magic-sparkles"></i> Conferma pull</span>
                        <h5 class="gacha-conversion-title" id="gachaConversionTitle">Completa la tua pull</h5>
                    </div>
                    <button type="button" class="gacha-conversion-close" data-bs-dismiss="modal" aria-label="Chiudi">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="gacha-conversion-body">
                    <p class="gacha-conversion-copy">
                        Ti mancano <strong><span class="conversion-shards-count">4</span> Godo Shards</strong>.
                        Puoi crearli al volo usando i tuoi Godos.
                    </p>

                    <div class="gacha-conversion-flow" aria-label="Riepilogo conversione">
                        <div class="gacha-conversion-currency gacha-conversion-currency--godos">
                            <span class="gacha-conversion-label">Spendi</span>
                            <img src="/img/godos.png" alt="" class="gacha-conversion-icon">
                            <strong class="conversion-godos-cost">400</strong>
                            <small>Godos</small>
                        </div>

                        <div class="gacha-conversion-arrow" aria-hidden="true">
                            <i class="fa-solid fa-arrow-right"></i>
                        </div>

                        <div class="gacha-conversion-currency gacha-conversion-currency--shards">
                            <span class="gacha-conversion-label">Ricevi</span>
                            <img src="/img/godoshards.png" alt="" class="gacha-conversion-icon">
                            <strong>+<span class="conversion-shards-count">4</span></strong>
                            <small>Godo Shards</small>
                        </div>
                    </div>

                    <div class="gacha-conversion-actions">
                        <button type="button" class="gacha-conversion-btn gacha-conversion-btn--ghost" data-bs-dismiss="modal">Annulla</button>
                        <button type="button" class="gacha-conversion-btn gacha-conversion-btn--primary btn-confirm-conversion">
                            <span>Converti e pulla</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.GACHA_INIT = <?= json_encode([
                                'userId'      => (int)$_SESSION['user_id'],
                                'ruolo'       => $ruolo,
                                'isAdmin'     => $isAdmin,
                                'soldi'       => $soldi,
                                'godoshards'  => $godoshards,
                                'pityStandard' => $pityStandard,
                                'pityEvento'  => $pityEvento,
                                'garantito'   => (bool)$garantito,
                                'pityHardStd' => PITY_STANDARD_HARD,
                                'pitySoftStd' => PITY_STANDARD_SOFT,
                                'pityHardEvt' => PITY_EVENTO_HARD,
                                'pitySoftEvt' => PITY_EVENTO_SOFT,
                                'banners'     => array_map(function ($b) {
                                    return [
                                        'id'         => (int)$b['id'],
                                        'slug'       => $b['slug'],
                                        'nome'       => $b['nome'],
                                        'costo'      => (int)$b['costo_punti'],
                                        'data_fine'  => $b['data_fine'],
                                        'rateup_nome' => $b['rateup_nome'],
                                        'rateup_img' => $b['rateup_img_url'],
                                    ];
                                }, $bannersEvento),
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        window.GACHA_INIT.activeBannerId = 'standard';
        document.querySelectorAll('.gsb-card[data-banner-id]').forEach(card => {
            card.addEventListener('click', () => {
                window.GACHA_INIT.activeBannerId = card.dataset.bannerId;
            });
        });

        const premiumBtn = document.getElementById('premium-claim-btn');
        if (premiumBtn) {
            function startPremiumClaimCountdown(btn, seconds) {
                if (!btn) return;

                function formatTime(secs) {
                    if (secs <= 0) return "00:00:00";
                    const h = Math.floor(secs / 3600);
                    const m = Math.floor((secs % 3600) / 60);
                    const s = secs % 60;
                    return [
                        h.toString().padStart(2, '0'),
                        m.toString().padStart(2, '0'),
                        s.toString().padStart(2, '0')
                    ].join(':');
                }

                if (btn._countdownInterval) {
                    clearInterval(btn._countdownInterval);
                }

                const update = () => {
                    if (seconds <= 0) {
                        clearInterval(btn._countdownInterval);
                        btn.classList.remove('claimed');
                        btn.disabled = false;
                        const btnText = btn.querySelector('.btn-text');
                        if (btnText) {
                            const isEn = window.location.pathname.includes('/en/');
                            btnText.textContent = isEn ? 'Claim 500 Points' : 'Riscatta 500 Punti';
                        }
                        return;
                    }

                    const countdownSpan = btn.querySelector('.claim-countdown');
                    if (countdownSpan) {
                        countdownSpan.textContent = formatTime(seconds);
                    }
                    seconds--;
                };

                update();
                btn._countdownInterval = setInterval(update, 1000);
            }

            // Init countdown on load if already claimed
            const secondsLeft = parseInt(premiumBtn.dataset.secondsLeft || 0, 10);
            if (premiumBtn.classList.contains('claimed')) {
                startPremiumClaimCountdown(premiumBtn, secondsLeft);
            }

            premiumBtn.addEventListener('click', async () => {
                try {
                    premiumBtn.disabled = true;
                    const res = await fetch('/api/premium_daily_claim.php', {
                        method: 'POST'
                    });
                    const data = await res.json();
                    if (data.success) {
                        premiumBtn.classList.add('claimed');
                        const isEn = window.location.pathname.includes('/en/');
                        const btnText = premiumBtn.querySelector('.btn-text');
                        if (btnText) {
                            btnText.innerHTML = isEn ?
                                'Claimed today (Reset in <span class="claim-countdown">--:--:--</span>)' :
                                'Riscattato oggi (Ricarica tra <span class="claim-countdown">--:--:--</span>)';
                        }

                        const secs = parseInt(data.seconds_left || 86400, 10);
                        startPremiumClaimCountdown(premiumBtn, secs);

                        if (window.GachaUI && typeof window.GachaUI.setSoldi === 'function') {
                            window.GachaUI.setSoldi(data.new_soldi);
                        }
                        if (window.GachaUI && typeof window.GachaUI.showToast === 'function') {
                            window.GachaUI.showToast(data.message, 'success');
                        } else {
                            alert(data.message);
                        }
                    } else {
                        premiumBtn.disabled = false;
                        if (window.GachaUI && typeof window.GachaUI.showToast === 'function') {
                            window.GachaUI.showToast(data.error || 'Errore durante il riscatto', 'error');
                        } else {
                            alert(data.error || 'Errore durante il riscatto');
                        }
                    }
                } catch (e) {
                    premiumBtn.disabled = false;
                    console.error(e);
                    if (window.GachaUI && typeof window.GachaUI.showToast === 'function') {
                        window.GachaUI.showToast('Errore di rete o del server', 'error');
                    } else {
                        alert('Errore di rete o del server');
                    }
                }
            });
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
    <script src="/js/unlockAchievement-it.js"></script>
    <script src="/js/gacha-effects.js?v=5"></script>
    <script src="/js/gacha.js?v=42"></script>

    <script>
        function openCurrentHistory() {
            const bid = window.GACHA_INIT?.activeBannerId ?? 'standard';
            const label = bid === 'standard' ?
                'Banner Standard' :
                (window.GACHA_INIT?.banners?.find(b => b.id == bid)?.nome ?? 'Banner Evento');
            window.GachaHistory?.open(bid, label);
        }
        let currentLeaderboardType = 'casse_aperte';
        let leaderboardVisible = false;

        function toggleLeaderboard() {
            const wrapper = document.getElementById('leaderboard-wrapper');
            leaderboardVisible = !leaderboardVisible;
            wrapper.style.display = leaderboardVisible ? 'flex' : 'none';
            if (leaderboardVisible) loadLeaderboard(currentLeaderboardType);
        }

        async function loadLeaderboard(type) {
            const dataDiv = document.getElementById('leaderboard-data');
            dataDiv.innerHTML = '<div class="loading-text testobianco"><i class="fa-solid fa-circle-notch fa-spin"></i><span>Caricamento...</span></div>';
            try {
                const r = await fetch(`/api/get_leaderboard?type=${type}`);
                const d = await r.json();
                if (d.status === 'success' && d.data.length > 0) {
                    displayLeaderboard(d.data, type);
                } else {
                    dataDiv.innerHTML = '<div class="loading-text testobianco"><i class="fa-solid fa-ranking-star"></i><span>Nessun dato disponibile</span></div>';
                }
            } catch {
                dataDiv.innerHTML = '<div class="loading-text testobianco is-error"><i class="fa-solid fa-triangle-exclamation"></i><span>Errore connessione</span></div>';
            }
        }

        function displayLeaderboard(data, type) {
            const lbl = type === 'casse_aperte' ? 'casse' : 'personaggi';
            document.getElementById('leaderboard-data').innerHTML = data.map(item => {
                const medal = {
                    1: '🥇 ',
                    2: '🥈 ',
                    3: '🥉 '
                } [item.position] ?? '';
                const cls = {
                    1: 'gold',
                    2: 'silver',
                    3: 'bronze'
                } [item.position] ?? '';
                return `<div class="leaderboard-entry ${cls}">
            <span class="entry-position testobianco">${medal}${item.position}</span>
            <span class="entry-user-wrap"><span class="entry-username testobianco">${item.username}${item.is_premium ? ' <span class="premium-badge-icon" title="Premium"><i class="fa-solid fa-gem"></i></span>' : ''}</span><small>${lbl}</small></span>
            <span class="entry-value">${item.value}</span>
        </div>`;
            }).join('');
        }

        function switchLeaderboard(type) {
            currentLeaderboardType = type;
            document.querySelectorAll('.leaderboard-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(type === 'casse_aperte' ? 'btn-casse' : 'btn-personaggi')
                .classList.add('active');
            loadLeaderboard(type);
        }

        document.getElementById('leaderboard-close-btn')
            .addEventListener('click', toggleLeaderboard);
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
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        codice,
                        lang: location.pathname.split('/').find(s => s === 'it' || s === 'en') || 'it'
                    }),
                    credentials: 'same-origin',
                });
                const data = await resp.json();

                if (data.status !== 'success') {
                    window.GachaUI?.showToast(data.message ?? 'Errore riscatto.', 'error');
                    return;
                }

                input.value = '';

                if (data.tipo === 'personaggio') {
                    const modal = bootstrap.Modal.getInstance(
                        document.getElementById('settingsModal')
                    );
                    modal?.hide();
                    window.GachaUI?.openRevealWithData(data.personaggio);

                } else if (data.tipo === 'punti') {
                    if (data.soldi_rimasti != null) {
                        window.GachaUI?.setSoldi?.(data.soldi_rimasti);
                        document.querySelectorAll('#user-points-std, .user-points-evt')
                            .forEach(el => el.textContent = data.soldi_rimasti.toLocaleString('it'));
                    }
                    const desc = data.descrizione ?? `+${data.punti} punti!`;
                    window.GachaUI?.showToast(`🎁 ${desc}`, 'success');
                }

            } catch {
                window.GachaUI?.showToast('Errore riscatto. Riprova.', 'error');
            } finally {
                btn.disabled = false;
                label.style.display = 'inline';
                spin.style.display = 'none';
            }
        }
    </script>

</body>

</html>
