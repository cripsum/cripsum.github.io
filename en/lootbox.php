<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = 'Per accedere al sistema gacha devi essere loggato';
    header('Location: accedi');
    exit();
}
checkPermissions($mysqli, 'utente');

$ruolo = $_SESSION['ruolo'] ?? 'utente';
$isAdmin = in_array($ruolo, ['admin', 'owner'], true);

// Carica dati utente per PHP-side render iniziale
$stmtUser = $mysqli->prepare(
    'SELECT username, soldi, pity_standard, pity_evento, garantito_evento
     FROM utenti WHERE id = ? LIMIT 1'
);
$stmtUser->bind_param('i', $_SESSION['user_id']);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$username       = htmlspecialchars($userData['username'] ?? '', ENT_QUOTES, 'UTF-8');
$soldi          = (int)($userData['soldi'] ?? 0);
$pityStandard   = (int)($userData['pity_standard'] ?? 0);
$pityEvento     = (int)($userData['pity_evento'] ?? 0);
$garantito      = (int)($userData['garantito_evento'] ?? 0);

// Carica banner evento attivi per render server-side iniziale
$nowDt = date('Y-m-d H:i:s');
$stmtBanners = $mysqli->prepare(
    'SELECT be.id, be.slug, be.nome, be.descrizione, be.id_personaggio_rateup,
            be.banner_img_url, be.costo_punti, be.data_fine,
            p.nome AS rateup_nome, p.rarità AS rateup_rarità,
            p.img_url AS rateup_img_url, p.descrizione AS rateup_desc,
            p.caratteristiche AS rateup_chars
     FROM banner_eventi be
     INNER JOIN personaggi p ON p.id = be.id_personaggio_rateup
     WHERE be.attivo = 1
       AND (be.data_inizio IS NULL OR be.data_inizio <= ?)
       AND (be.data_fine   IS NULL OR be.data_fine   >= ?)
     ORDER BY be.id ASC'
);
$stmtBanners->bind_param('ss', $nowDt, $nowDt);
$stmtBanners->execute();
$bannersResult = $stmtBanners->get_result();
$bannersEvento = [];
while ($row = $bannersResult->fetch_assoc()) {
    $bannersEvento[] = $row;
}
$stmtBanners->close();

// Pity hard (deve coincidere con api_gacha_pull.php)
define('PITY_STANDARD_HARD', 90);
define('PITY_STANDARD_SOFT', 70);
define('PITY_EVENTO_HARD',   80);
define('PITY_EVENTO_SOFT',   65);
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <link rel="stylesheet" href="/css/lootbox.css?v=8.4" />
    <link rel="stylesheet" href="/css/gacha.css?v=1.3" />
    <meta name="theme-color" content="#080810">
    <title>Cripsum™ — Lootbox</title>
</head>

<body class="lootbox-page" style="padding-bottom:60px" data-ruolo="<?= htmlspecialchars($ruolo, ENT_QUOTES) ?>">

    <?php include '../includes/navbar-lootbox.php'; ?>

    <div class="stars" id="stars"></div>

    <!-- ══════════════════════════════════════════════════════════
     TABS BANNER
══════════════════════════════════════════════════════════ -->
    <nav class="gacha-tabs" id="gacha-tabs" role="tablist" aria-label="Banner gacha">

        <!-- Tab Standard -->
        <button
            class="gacha-tab is-active"
            role="tab"
            aria-selected="true"
            data-banner-id="standard"
            data-banner-type="standard"
            data-tab-accent="#9ca3af"
            style="--tab-accent:#9ca3af">
            <span class="gacha-tab-dot" style="background:#9ca3af;box-shadow:0 0 6px #9ca3af"></span>
            Banner Standard
        </button>

        <!-- Tab banner evento (generati da PHP) -->
        <?php foreach ($bannersEvento as $b):
            $safeName  = htmlspecialchars($b['nome'], ENT_QUOTES, 'UTF-8');
            $safeSlug  = htmlspecialchars($b['slug'], ENT_QUOTES, 'UTF-8');
            $accentCol = '#38bdf8'; // default; il JS sovrascriverà
        ?>
            <button
                class="gacha-tab"
                role="tab"
                aria-selected="false"
                data-banner-id="<?= (int)$b['id'] ?>"
                data-banner-type="evento"
                data-banner-slug="<?= $safeSlug ?>"
                data-tab-accent="<?= $accentCol ?>"
                style="--tab-accent:<?= $accentCol ?>">
                <span class="gacha-tab-dot"></span>
                <?= $safeName ?>
            </button>
        <?php endforeach; ?>

        <!-- Tasto impostazioni -->
        <button
            class="gacha-tab gacha-tab--settings"
            aria-label="Impostazioni"
            id="btn-settings"
            title="Impostazioni">
            <i class="fas fa-gear"></i>
        </button>
    </nav>

    <!-- ══════════════════════════════════════════════════════════
     BANNER VIEWS (una per ogni banner, swap via JS)
══════════════════════════════════════════════════════════ -->

    <!-- ── Banner Standard ────────────────────────────────────── -->
    <section
        class="gacha-banner-view"
        id="banner-view-standard"
        data-banner-id="standard"
        data-banner-type="standard"
        data-pity-hard="<?= PITY_STANDARD_HARD ?>"
        data-pity-soft="<?= PITY_STANDARD_SOFT ?>"
        data-costo="0"
        aria-label="Banner Standard">
        <div class="gacha-banner-bg" id="banner-bg-standard"></div>

        <!-- Art placeholder standard (nessun rateup) -->
        <div class="gacha-banner-art-wrap" aria-hidden="true">
            <img
                src="/img/cassa.png"
                alt="Cassa standard"
                class="gacha-banner-char"
                id="banner-char-standard"
                draggable="false">
        </div>

        <div class="gacha-banner-info">
            <div>
                <span class="gacha-banner-type-badge">✦ SEMPRE DISPONIBILE</span>
                <h1 class="gacha-banner-title">Banner Standard</h1>
                <p class="gacha-banner-desc">Il banner classico di GoonLand. Tira per ottenere personaggi di ogni rarità. Gratuito, senza timer.</p>
            </div>

            <!-- Pity standard -->
            <div class="gacha-pity-wrap">
                <div class="gacha-pity-header">
                    <span>Pity Standard</span>
                    <span id="pity-std-num"><?= $pityStandard ?> / <?= PITY_STANDARD_HARD ?></span>
                </div>
                <div class="gacha-pity-track">
                    <div
                        class="gacha-pity-fill"
                        id="pity-std-fill"
                        style="width:<?= min(100, round($pityStandard / PITY_STANDARD_HARD * 100)) ?>%"></div>
                    <div
                        class="gacha-pity-soft-marker"
                        style="left:<?= round(PITY_STANDARD_SOFT / PITY_STANDARD_HARD * 100) ?>%"></div>
                </div>
                <p class="gacha-pity-note <?= $pityStandard >= PITY_STANDARD_SOFT ? 'is-active' : '' ?>" id="pity-std-note">
                    <?php if ($pityStandard >= PITY_STANDARD_HARD): ?>
                        ★ Garantito: prossima pull è Speciale o Segreto!
                    <?php elseif ($pityStandard >= PITY_STANDARD_SOFT): ?>
                        ✦ Soft pity attivo — % Speciale/Segreto aumentata
                    <?php else: ?>
                        Garantito Speciale/Segreto in <?= PITY_STANDARD_HARD - $pityStandard ?> pull
                    <?php endif; ?>
                </p>
            </div>

            <!-- Economy -->
            <div class="gacha-economy-row">
                <span class="gacha-points">
                    <i class="fas fa-coins"></i>
                    <span id="user-points-std"><?= number_format($soldi) ?></span>
                </span>
                <span class="gacha-cost">• Gratuito</span>
            </div>

            <!-- Pull button -->
            <button
                class="gacha-pull-btn"
                id="pull-btn-standard"
                aria-label="Apri 1x banner standard"
                data-banner-id="standard">
                <i class="fas fa-box-open gacha-pull-btn-icon"></i>
                <span>Apri 1×</span>
            </button>
        </div>
    </section>

    <!-- ── Banner Evento (generati da PHP) ────────────────────── -->
    <?php foreach ($bannersEvento as $b):
        $bid          = (int)$b['id'];
        $safeName     = htmlspecialchars($b['nome'], ENT_QUOTES, 'UTF-8');
        $safeDesc     = htmlspecialchars($b['descrizione'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeRateup   = htmlspecialchars($b['rateup_nome'], ENT_QUOTES, 'UTF-8');
        $safeRarity   = strtolower(trim($b['rateup_rarità'] ?? ''));
        $safeRarityIt = htmlspecialchars($b['rateup_rarità'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeImg      = htmlspecialchars($b['rateup_img_url'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeDesc2    = htmlspecialchars($b['rateup_desc'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeChars    = htmlspecialchars($b['rateup_chars'] ?? '', ENT_QUOTES, 'UTF-8');
        $bgImg        = htmlspecialchars($b['banner_img_url'] ?? '', ENT_QUOTES, 'UTF-8');
        $costo        = (int)$b['costo_punti'];
        $dataFine     = $b['data_fine'] ?? null;
        $canAfford    = $soldi >= $costo;
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
            <div
                class="gacha-banner-bg <?= $bgImg ? 'has-img' : '' ?>"
                id="banner-bg-<?= $bid ?>"
                <?php if ($bgImg): ?>style="background-image:url('/img/<?= $bgImg ?>')" <?php endif; ?>></div>

            <div class="gacha-banner-art-wrap" aria-hidden="true">
                <?php if ($safeImg): ?>
                    <img
                        src="/img/<?= $safeImg ?>"
                        alt="<?= $safeRateup ?>"
                        class="gacha-banner-char"
                        draggable="false"
                        onerror="this.src='/img/cassa.png'">
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

                <!-- Rate-up info -->
                <div class="gacha-rateup-info">
                    <span class="gacha-rateup-label">Rate-Up ✦</span>
                    <p class="gacha-rateup-name"><?= $safeRateup ?></p>
                    <p class="gacha-rateup-rarity rarity-<?= htmlspecialchars($safeRarity) ?>"><?= $safeRarityIt ?></p>
                    <?php if ($safeDesc2): ?>
                        <p class="gacha-rateup-char-desc"><?= $safeDesc2 ?></p>
                    <?php endif; ?>
                </div>

                <!-- Garantito badge (visibile solo se attivo) -->
                <?php if ($garantito): ?>
                    <div class="gacha-garantito-badge" id="garantito-badge-<?= $bid ?>">
                        <i class="fas fa-shield-halved"></i>
                        Garantito attivo — prossima rara è il rate-up
                    </div>
                <?php else: ?>
                    <div class="gacha-garantito-badge" id="garantito-badge-<?= $bid ?>" style="display:none">
                        <i class="fas fa-shield-halved"></i>
                        Garantito attivo — prossima rara è il rate-up
                    </div>
                <?php endif; ?>

                <!-- Pity evento (condiviso) -->
                <div class="gacha-pity-wrap">
                    <div class="gacha-pity-header">
                        <span>Pity Evento</span>
                        <span class="pity-evt-num"><?= $pityEvento ?> / <?= PITY_EVENTO_HARD ?></span>
                    </div>
                    <div class="gacha-pity-track">
                        <div
                            class="gacha-pity-fill pity-evt-fill"
                            style="width:<?= min(100, round($pityEvento / PITY_EVENTO_HARD * 100)) ?>%"></div>
                        <div
                            class="gacha-pity-soft-marker"
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

                <!-- Timer -->
                <?php if ($dataFine): ?>
                    <div class="gacha-timer-wrap">
                        <i class="fas fa-clock"></i>
                        <span>Scade tra</span>
                        <div class="gacha-timer-digits" data-ends="<?= htmlspecialchars($dataFine, ENT_QUOTES) ?>">
                            <div class="gacha-timer-block"><span class="t-days">--</span><small>gg</small></div>
                            <div class="gacha-timer-block"><span class="t-hours">--</span><small>ore</small></div>
                            <div class="gacha-timer-block"><span class="t-mins">--</span><small>min</small></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Economy -->
                <div class="gacha-economy-row">
                    <span class="gacha-points">
                        <i class="fas fa-coins"></i>
                        <span class="user-points-evt"><?= number_format($soldi) ?></span>
                    </span>
                    <span class="gacha-cost">• <?= number_format($costo) ?> punti / pull</span>
                </div>

                <!-- Pull button -->
                <button
                    class="gacha-pull-btn"
                    id="pull-btn-<?= $bid ?>"
                    aria-label="Apri 1x <?= $safeName ?>"
                    data-banner-id="<?= $bid ?>"
                    <?= !$canAfford ? 'data-no-points="true"' : '' ?>>
                    <i class="fas fa-star gacha-pull-btn-icon"></i>
                    <span>Apri 1×</span>
                    <span class="gacha-pull-cost-badge"><?= number_format($costo) ?></span>
                </button>
            </div>
        </section>
    <?php endforeach; ?>

    <!-- ══════════════════════════════════════════════════════════
     OVERLAY FULLSCREEN PULL
     (un'unica istanza, riutilizzata per ogni pull)
══════════════════════════════════════════════════════════ -->
    <div
        class="gacha-overlay"
        id="gacha-overlay"
        role="dialog"
        aria-modal="true"
        aria-label="Risultato pull"
        aria-live="polite">
        <!-- BG -->
        <div class="gacha-overlay-bg"></div>

        <!-- Glow burst -->
        <div class="gacha-glow-burst" id="gacha-glow-burst"></div>

        <!-- Stars decorativi dentro overlay -->
        <div class="gacha-stars-layer" id="overlay-stars"></div>

        <!-- Particles -->
        <div class="gacha-particles-layer" id="gacha-particles"></div>

        <!-- Flash bianco per cinematic reveal -->
        <div class="gacha-flash" id="gacha-flash"></div>

        <!-- FASE 1: ORB animazione apertura -->
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

        <!-- FASE 2: Video fullscreen -->
        <div class="gacha-phase gacha-phase--video" id="phase-video" style="display:none">
            <video
                id="gacha-video"
                autoplay
                muted
                playsinline
                preload="metadata"
                webkit-playsinline></video>
            <button class="gacha-video-unmute" id="video-unmute-btn" style="display:none">
                <i class="fas fa-volume-xmark"></i> Tap per audio
            </button>
        </div>

        <!-- FASE 3: Card reveal -->
        <div class="gacha-phase gacha-phase--card" id="phase-card" style="display:none">

            <div class="gacha-card" id="gacha-card" aria-live="polite">
                <div class="gacha-card-bg-glow" id="card-bg-glow"></div>

                <div class="gacha-card-frame" id="card-frame">
                    <div class="gacha-card-img-wrap" id="card-img-wrap">
                        <img
                            id="card-img"
                            src="/img/cassa.png"
                            alt="Personaggio"
                            draggable="false"
                            onerror="this.src='/img/cassa.png'">
                    </div>
                    <div class="gacha-card-img-shine"></div>
                    <span class="gacha-card-new-badge" id="card-new-badge" style="display:none">NEW!</span>
                    <span class="gacha-card-50-badge gacha-card-50-badge--win" id="card-50-win" style="display:none">
                        <i class="fas fa-trophy"></i> Rate-Up Vinto!
                    </span>
                    <span class="gacha-card-50-badge gacha-card-50-badge--loss" id="card-50-loss" style="display:none">
                        Garantito attivato per la prossima pull
                    </span>
                </div>

                <div class="gacha-card-details">
                    <div class="gacha-card-rarity-bar" id="card-rarity-bar"></div>
                    <p class="gacha-card-rarity-label" id="card-rarity-label">—</p>
                    <h2 class="gacha-card-name" id="card-name">—</h2>
                    <!-- FIX 6: caratteristiche rimosse, solo nome + rarità -->
                </div>
            </div>

            <!-- Azioni -->
            <div class="gacha-overlay-actions" id="overlay-actions">
                <button class="gacha-btn gacha-btn--primary" id="btn-pull-again">
                    <i class="fas fa-rotate-right"></i> Apri ancora
                </button>
                <button class="gacha-btn gacha-btn--ghost" id="btn-close-overlay">
                    <i class="fas fa-xmark"></i> Chiudi
                </button>
                <a href="inventario" class="gacha-btn gacha-btn--ghost" id="btn-go-inventory">
                    <i class="fas fa-layer-group"></i> Vedi inventario
                </a>
            </div>
        </div>
    </div>

    <!-- FIX 10 — Skip button -->
    <button class="gacha-skip-btn" id="gacha-skip-btn" aria-label="Salta animazione">
        <i class="fas fa-forward-step"></i> Salta [S]
    </button>

    <!-- Toast notifiche -->
    <div class="gacha-toast" id="gacha-toast" role="alert" aria-live="assertive"></div>

    <!-- Audio -->
    <audio id="gacha-audio" preload="none"></audio>

    <!-- ══════════════════════════════════════════════════════════
     MODAL IMPOSTAZIONI
══════════════════════════════════════════════════════════ -->
    <div class="modal fade lootbox-settings-modal" id="impostazioniModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable lootbox-settings-dialog">
            <div class="modal-content bgimpostazioni lootbox-settings-content">
                <div class="modal-header lootbox-settings-header">
                    <div>
                        <span class="lootbox-modal-kicker">Gacha</span>
                        <h5 class="modal-title">Impostazioni</h5>
                        <p>Probabilità, comandi e funzioni rapide.</p>
                    </div>
                    <button type="button" class="lootbox-modal-close" data-bs-dismiss="modal" aria-label="Chiudi">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>

                <div class="modal-body lootbox-settings-body">

                    <!-- Comandi -->
                    <section class="lootbox-settings-section">
                        <div class="lootbox-section-head">
                            <i class="fas fa-keyboard"></i>
                            <div>
                                <h6>Comandi</h6>
                                <p>Scorciatoie rapide durante la pull.</p>
                            </div>
                        </div>
                        <div class="lootbox-command-grid">
                            <div class="lootbox-command-item"><span>Click / Tap</span><strong>Apri pull</strong></div>
                            <div class="lootbox-command-item"><span>Space</span><strong>Apri pull</strong></div>
                            <div class="lootbox-command-item"><span>Enter</span><strong>Apri ancora</strong></div>
                            <div class="lootbox-command-item"><span>Esc</span><strong>Chiudi overlay</strong></div>
                        </div>
                    </section>

                    <!-- Probabilità -->
                    <section class="lootbox-settings-section">
                        <div class="lootbox-section-head">
                            <i class="fas fa-dice"></i>
                            <div>
                                <h6>Probabilità base</h6>
                                <p>Calcolate server-side. Il pity aumenta la % col tempo.</p>
                            </div>
                        </div>
                        <div class="gacha-rates-grid">
                            <div class="gacha-rate-row rate-common"><span>Comune</span><strong>51%</strong></div>
                            <div class="gacha-rate-row rate-rare"><span>Raro</span><strong>28%</strong></div>
                            <div class="gacha-rate-row rate-epic"><span>Epico</span><strong>13%</strong></div>
                            <div class="gacha-rate-row rate-legendary"><span>Leggendario</span><strong>5.99%</strong></div>
                            <div class="gacha-rate-row rate-special"><span>Speciale</span><strong>1.80%</strong></div>
                            <div class="gacha-rate-row rate-secret"><span>Segreto</span><strong>0.20%</strong></div>
                            <div class="gacha-rate-row rate-secret"><span>The One</span><strong>0.01%</strong></div>
                        </div>
                    </section>

                    <!-- Sistema pity -->
                    <section class="lootbox-settings-section">
                        <div class="lootbox-section-head">
                            <i class="fas fa-chart-line"></i>
                            <div>
                                <h6>Sistema Pity</h6>
                                <p>Il pity evento è condiviso tra tutti i banner evento.</p>
                            </div>
                        </div>
                        <div class="lootbox-command-grid">
                            <div class="lootbox-command-item"><span>Soft pity std</span><strong>Pull 70</strong></div>
                            <div class="lootbox-command-item"><span>Hard pity std</span><strong>Pull 90</strong></div>
                            <div class="lootbox-command-item"><span>Soft pity evt</span><strong>Pull 65</strong></div>
                            <div class="lootbox-command-item"><span>Hard pity evt</span><strong>Pull 80</strong></div>
                        </div>
                    </section>

                    <!-- Admin cheats -->
                    <?php if ($isAdmin): ?>
                        <section id="admin-cheats" class="lootbox-settings-section lootbox-admin-section">
                            <div class="lootbox-section-head">
                                <i class="fas fa-wand-magic-sparkles"></i>
                                <div>
                                    <h6>Admin cheats</h6>
                                    <p>Force rarità (server-side).</p>
                                </div>
                            </div>
                            <div class="lootbox-toggle-grid">
                                <?php
                                $adminRarities = [
                                    'forza-comune'      => 'Solo Comuni',
                                    'forza-raro'        => 'Solo Rari',
                                    'forza-epico'       => 'Solo Epici',
                                    'forza-leggendario' => 'Solo Leggendari',
                                    'forza-speciale'    => 'Solo Speciali',
                                    'forza-segreto'     => 'Solo Segreti',
                                    'forza-theone'      => 'Solo The One',
                                ];
                                foreach ($adminRarities as $id => $label): ?>
                                    <label class="lootbox-toggle-pill" for="<?= $id ?>">
                                        <input class="form-check-input admin-force-rarity" type="checkbox" id="<?= $id ?>" data-rarity="<?= str_replace('forza-', '', $id) ?>" />
                                        <span><?= $label ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <!-- Codice segreto -->
                    <section class="lootbox-settings-section lootbox-code-section">
                        <div class="lootbox-section-head">
                            <i class="fas fa-lock"></i>
                            <div>
                                <h6>Codice segreto</h6>
                                <p>Inserisci un codice valido, se ne hai uno.</p>
                            </div>
                        </div>
                        <div class="lootbox-secret-row">
                            <label class="visually-hidden" for="codiceSegreto">Codice Segreto</label>
                            <input type="text" id="codiceSegreto" class="form-control" placeholder="Codice segreto" autocomplete="off" />
                            <button type="button" class="btn btn-secondary bottone lootbox-modal-btn" data-bs-dismiss="modal" onclick="riscattaCodice()">
                                Riscatta
                            </button>
                        </div>
                    </section>
                </div>

                <div class="modal-footer lootbox-settings-footer">
                    <button type="button" class="btn btn-secondary bottone lootbox-modal-btn lootbox-modal-btn--ghost" data-bs-dismiss="modal">
                        Chiudi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaderboard (identica all'originale, markup preservato) -->
    <div class="leaderboard-wrapper" id="leaderboard-wrapper" style="display:none">
        <div class="leaderboard-box lootbox-leaderboard-box">
            <div class="leaderboard-head">
                <div>
                    <span class="leaderboard-kicker">Classifica</span>
                    <h3 class="testobianco">Top Gacha</h3>
                    <p>Le prime posizioni del momento.</p>
                </div>
                <button class="leaderboard-close" type="button" id="leaderboard-close-btn" aria-label="Chiudi classifica">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <div class="leaderboard-buttons" role="group">
                <button class="btn btn-secondary bottone leaderboard-btn active" id="btn-casse" onclick="switchLeaderboard('casse_aperte')">
                    <i class="fas fa-box-open"></i> <span>Casse aperte</span>
                </button>
                <button class="btn btn-secondary bottone leaderboard-btn" id="btn-personaggi" onclick="switchLeaderboard('personaggi_sbloccati')">
                    <i class="fas fa-layer-group"></i> <span>Personaggi</span>
                </button>
            </div>
            <div id="leaderboard-data" class="leaderboard-data">
                <div class="loading-text testobianco">Caricamento...</div>
            </div>
        </div>
    </div>

    <!-- Achievement popup (compatibile con sistema esistente) -->
    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement" />
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════
     DATI INIZIALI PHP → JS
     Passati via JSON inline, sicuri, nessun RNG qui
════════════════════════════════════════════════════════ -->
    <!-- FIX 12 — Bottom bar (leaderboard + link) -->
    <div class="gacha-bottom-bar" id="gacha-bottom-bar">
        <button type="button" class="gacha-bottom-btn gacha-leaderboard-btn" onclick="alert('CLICK RICEVUTO!'); toggleLeaderboard();">
            <i class="fas fa-trophy"></i> Classifica
        </button>
        <a href="inventario" class="gacha-bottom-btn">
            <i class="fas fa-layer-group"></i> Inventario
        </a>
        <button class="gacha-bottom-btn" id="btn-open-history-cur" onclick="openCurrentHistory()">
            <i class="fas fa-scroll"></i> Cronologia
        </button>
    </div>

    <script>
        window.GACHA_INIT = <?= json_encode([
                                'userId'        => (int)$_SESSION['user_id'],
                                'ruolo'         => $ruolo,
                                'isAdmin'       => $isAdmin,
                                'soldi'         => $soldi,
                                'pityStandard'  => $pityStandard,
                                'pityEvento'    => $pityEvento,
                                'garantito'     => (bool)$garantito,
                                'pityHardStd'   => PITY_STANDARD_HARD,
                                'pitySoftStd'   => PITY_STANDARD_SOFT,
                                'pityHardEvt'   => PITY_EVENTO_HARD,
                                'pitySoftEvt'   => PITY_EVENTO_SOFT,
                                'banners'       => array_map(fn($b) => [
                                    'id'         => (int)$b['id'],
                                    'slug'       => $b['slug'],
                                    'nome'       => $b['nome'],
                                    'costo'      => (int)$b['costo_punti'],
                                    'data_fine'  => $b['data_fine'],
                                    'rateup_nome' => $b['rateup_nome'],
                                    'rateup_img' => $b['rateup_img_url'],
                                ], $bannersEvento),
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                            ]) ?>;
        // Tracking banner attivo per cronologia
        window.GACHA_INIT.activeBannerId = 'standard';
        document.querySelectorAll('.gacha-tab[data-banner-id]').forEach(tab => {
            tab.addEventListener('click', () => {
                window.GACHA_INIT.activeBannerId = tab.dataset.bannerId;
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="../js/unlockAchievement-it.js"></script>
    <script src="../js/gacha-effects.js?v=1.2"></script>
    <script src="../js/gacha.js?v=1.2.1"></script>

    <script>
        function openCurrentHistory() {
            const bannerId = window.GACHA_INIT?.activeBannerId ?? 'standard';
            const label = bannerId === 'standard' ? 'Banner Standard' :
                (window.GACHA_INIT?.banners?.find(b => b.id == bannerId)?.nome ?? 'Banner Evento');
            if (window.GachaHistory) {
                GachaHistory.open(bannerId, label);
            }
        }

        let currentLeaderboardType = 'casse_aperte';
        let leaderboardVisible = false;

        document.addEventListener('click', function(e) {
            // Controlla se il click è avvenuto sul bottone (o sull'icona della coppa al suo interno)
            const btn = e.target.closest('#tasto-apri-classifica') || e.target.closest('.gacha-leaderboard-btn');

            if (btn) {
                e.preventDefault();
                toggleLeaderboard();
            }
        });

        function toggleLeaderboard() {
            const wrapper = document.getElementById('leaderboard-wrapper');
            leaderboardVisible = !leaderboardVisible;
            wrapper.style.display = leaderboardVisible ? 'flex' : 'none';
            if (leaderboardVisible) loadLeaderboard(currentLeaderboardType);
        }

        async function loadLeaderboard(type) {
            const dataDiv = document.getElementById('leaderboard-data');
            dataDiv.innerHTML = '<div class="loading-text testobianco"><i class="fas fa-circle-notch fa-spin"></i><span>Caricamento...</span></div>';
            try {
                const r = await fetch(`/api/get_leaderboard?type=${type}`);
                const d = await r.json();
                if (d.status === 'success' && d.data.length > 0) {
                    displayLeaderboard(d.data, type);
                } else {
                    dataDiv.innerHTML = '<div class="loading-text testobianco"><i class="fas fa-ranking-star"></i><span>Nessun dato disponibile</span></div>';
                }
            } catch {
                dataDiv.innerHTML = '<div class="loading-text testobianco is-error"><i class="fas fa-triangle-exclamation"></i><span>Errore di connessione</span></div>';
            }
        }

        function displayLeaderboard(data, type) {
            const dataDiv = document.getElementById('leaderboard-data');
            const valueLabel = type === 'casse_aperte' ? 'casse' : 'personaggi';
            dataDiv.innerHTML = data.map(item => {
                const rankClass = item.position === 1 ? 'gold' : item.position === 2 ? 'silver' : item.position === 3 ? 'bronze' : '';
                const medal = item.position === 1 ? '🥇 ' : item.position === 2 ? '🥈 ' : item.position === 3 ? '🥉 ' : '';
                return `<div class="leaderboard-entry ${rankClass}">
            <span class="entry-position testobianco">${medal}${item.position}</span>
            <span class="entry-user-wrap"><span class="entry-username testobianco">${item.username}</span><small>${valueLabel}</small></span>
            <span class="entry-value">${item.value}</span>
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

        // ── Codice segreto (compatibile originale) ────────────────────────
        async function riscattaCodice() {
            const input = document.getElementById('codiceSegreto');
            const codice = input.value.trim().toLowerCase();
            const map = {
                'signortoki': 'TOKI',
                'cripsum': 'CRIPSUM',
                'peak': 'MAOMAO',
                'triplat': 'TRIPLA T',
            };
            if (!map[codice]) {
                window.GachaUI?.showToast('Codice non valido, skill issue!', 'error');
                return;
            }
            try {
                const r = await fetch(`/api/get_character_from_nome?nomePersonaggio=${encodeURIComponent(map[codice])}`);
                const pull = await r.json();
                if (!pull || !pull.id) {
                    window.GachaUI?.showToast('Codice già riscattato o personaggio non trovato!', 'error');
                    return;
                }
                const inv = await fetch('/api/api_get_inventario').then(r2 => r2.json());
                if (inv.find && inv.find(p => p.nome === pull.nome)) {
                    window.GachaUI?.showToast('Codice già riscattato!', 'error');
                    return;
                }
                await fetch(`/api/add_character_to_inventory?character_id=${pull.id}`);
                window.GachaUI?.openRevealWithData(pull);
            } catch (e) {
                window.GachaUI?.showToast('Errore riscatto. Riprova.', 'error');
            }
        }
    </script>

</body>

</html>