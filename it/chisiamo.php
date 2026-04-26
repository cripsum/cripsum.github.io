<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

function chisiamo_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$teamMembers = [
    [
        'name' => 'cripsum',
        'image' => '../img/cripsumchisiamo.jpg',
        'alt' => 'Cripsum',
        'link' => '../user/cripsum',
        'role' => 'Founder',
        'description' => 'L\'imperatore del Congo. Editor fallito che continua comunque a sognare in grande.',
    ],
    [
        'name' => 'simonetussi.ph',
        'image' => '../img/simonetussi.jpg',
        'alt' => 'Simone Tussi',
        'link' => '../user/simonetussi',
        'role' => 'Fotografia',
        'description' => 'Scatti, visual e roba bella. Seguitelo su Instagram e TikTok.',
        'socials' => [
            [
                'label' => 'Instagram',
                'url' => 'https://instagram.com/simonetussi.ph',
                'icon' => 'fab fa-instagram',
            ],
            [
                'label' => 'TikTok',
                'url' => 'https://tiktok.com/@simonetussi.ph',
                'icon' => 'fab fa-tiktok',
            ],
        ],
    ],
    [
        'name' => 'danebidev',
        'image' => '../img/sahe.jpg',
        'alt' => 'Danebidev',
        'role' => 'Game dev',
        'description' => 'Game developer sempre in risparmio energetico. JavaScript >> Java, questa è la filosofia.',
    ],
    [
        'name' => 'Ray',
        'image' => '../img/ray.jpg',
        'alt' => 'Ray',
        'role' => 'Trader',
        'description' => 'Broke/Broken/Broker. Vive la vita al limite e fa scelte finanziarie discutibili.',
    ],
    [
        'name' => 'Barandeep',
        'image' => '../img/barandeep.jpg',
        'alt' => 'Barandeep',
        'role' => 'Project manager',
        'description' => 'Xenon il gigante indiano. Tiene tutto sotto controllo con presenza imponente.',
    ],
    [
        'name' => 'Scammarpreet',
        'image' => '../img/samarpreet.jpg',
        'alt' => 'Scammarpreet',
        'role' => 'Gambler',
        'description' => 'Money grabber, scammer, guru, doxer. Fa girare i soldi e pure i dubbi.',
    ],
    [
        'name' => 'Tsundere Nyan',
        'image' => '../img/houshou_marine.jpeg',
        'alt' => 'Tsundere Nyan',
        'link' => '../user/tsundere_nyan',
        'role' => 'Gacha enjoyer',
        'description' => 'Grande amante dei gacha e del gooning. Primo posto in GoonLand.',
        'socials' => [
            [
                'label' => 'GoonLand',
                'url' => 'goonland/goon-generator',
                'icon' => 'fas fa-wand-magic-sparkles',
            ],
        ],
    ],
    [
        'name' => 'Cossu',
        'image' => '../img/cossu.jpg',
        'alt' => 'Cossu',
        'role' => 'Lontra enjoyer',
        'description' => 'Ama le lontre, le frittate con la banana e gusti culinari particolari.',
    ],
    [
        'name' => 'Zakator',
        'image' => '../img/photo_2023-11-14_17-21-10.jpg',
        'alt' => 'Zakator',
        'link' => '../user/zakator',
        'role' => 'Music listener',
        'description' => 'Grande ascoltatore di musica anime e phonk. Hackerino fallito ma non si arrende.',
    ],
    [
        'name' => 'Xalx Andrea',
        'image' => '../img/salsina.jpg',
        'alt' => 'Xalx Andrea',
        'link' => '../user/salsina',
        'role' => 'Yokai Watch player',
        'description' => 'Il player più tossico di Yokai Watch. Sa giocare e questo gli basta.',
    ],
    [
        'name' => 'Mabbon',
        'image' => '../img/mabbon.jpg',
        'alt' => 'Mabbon',
        'role' => 'Supporto',
        'description' => 'Ragazzo sfruttato e sottopagato. Non chiedergli perché, ormai è lore.',
    ],
    [
        'name' => 'LolloLaPulce',
        'image' => '../img/lollolapulce.jpg',
        'alt' => 'LolloLaPulce',
        'role' => 'Minecraft',
        'description' => 'Addetto alla depressione e grande giocatore di Minecraft. Costruisce mentre piange.',
    ],
    [
        'name' => 'Zazzo',
        'image' => '../img/zazzo.png',
        'alt' => 'Zazzo',
        'role' => 'Roma fan',
        'description' => 'Scarso in tutti i videogiochi, ma tifa la MAGGICA ROMA.',
    ],
];

$totalMembers = count($teamMembers);
$ogDescription = 'Il team e la lore dietro Cripsum™ / GoonLand.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/chisiamo'), '#');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Chi siamo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo chisiamo_h($ogDescription); ?>">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Chi siamo - Cripsum™">
    <meta property="og:description" content="<?php echo chisiamo_h($ogDescription); ?>">
    <meta property="og:image" content="https://cripsum.com/img/cripsumchisiamo.jpg">
    <meta property="og:url" content="<?php echo chisiamo_h($ogUrl); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="stylesheet" href="/assets/chisiamo/chisiamo.css?v=2.0-clean">
    <script src="/assets/chisiamo/chisiamo.js?v=2.0-clean" defer></script>
</head>

<body class="about-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="about-bg" aria-hidden="true">
        <span class="about-orb about-orb--one"></span>
        <span class="about-orb about-orb--two"></span>
        <span class="about-grid"></span>
    </div>

    <main class="about-shell">
        <section class="about-hero about-reveal">
            <div class="about-hero__copy">
                <span class="about-pill">Cripsum™ Team</span>
                <h1>Chi siamo</h1>
                <p>
                    Il cast dietro Cripsum™ e GoonLand. Una pagina più lore che curriculum.
                </p>

                <div class="about-hero__actions">
                    <a href="#team" class="about-btn about-btn--primary">
                        <i class="fas fa-users"></i>
                        <span>Vedi il team</span>
                    </a>
                    <a href="candidatura-chisiamo" class="about-btn about-btn--soft">
                        <i class="fas fa-envelope"></i>
                        <span>Candidati</span>
                    </a>
                </div>
            </div>

            <div class="about-hero__card" aria-label="Info team">
                <strong><?php echo (int)$totalMembers; ?></strong>
                <span>membri nella lore</span>
            </div>
        </section>

        <section id="team" class="team-section about-reveal">
            <div class="about-section-head">
                <div>
                    <span class="about-kicker">Team</span>
                    <h2>La squadra</h2>
                </div>
                <p>
                    Vuoi esserci anche tu? Manda immagine, nome, descrizione e un eventuale link per i crediti.
                </p>
            </div>

            <div class="team-toolbar" aria-label="Ricerca team">
                <label class="team-search">
                    <i class="fas fa-search"></i>
                    <input type="search" id="teamSearch" placeholder="Cerca un nome..." autocomplete="off">
                </label>
                <button type="button" class="about-btn about-btn--soft" id="clearTeamSearch">
                    <i class="fas fa-xmark"></i>
                    <span>Pulisci</span>
                </button>
            </div>

            <div class="team-grid" id="teamGrid">
                <?php foreach ($teamMembers as $member): ?>
                    <article class="team-member about-reveal" data-member-name="<?php echo chisiamo_h(mb_strtolower($member['name'] . ' ' . ($member['role'] ?? ''), 'UTF-8')); ?>">
                        <div class="member-image">
                            <img src="<?php echo chisiamo_h($member['image']); ?>" alt="<?php echo chisiamo_h($member['alt'] ?? $member['name']); ?>" loading="lazy">
                        </div>

                        <div class="member-info">
                            <?php if (!empty($member['role'])): ?>
                                <span class="member-role"><?php echo chisiamo_h($member['role']); ?></span>
                            <?php endif; ?>

                            <h3 class="member-name">
                                <?php if (!empty($member['link'])): ?>
                                    <a href="<?php echo chisiamo_h($member['link']); ?>"><?php echo chisiamo_h($member['name']); ?></a>
                                <?php else: ?>
                                    <?php echo chisiamo_h($member['name']); ?>
                                <?php endif; ?>
                            </h3>

                            <p class="member-description"><?php echo chisiamo_h($member['description']); ?></p>

                            <?php if (!empty($member['socials']) && is_array($member['socials'])): ?>
                                <div class="member-links">
                                    <?php foreach ($member['socials'] as $social): ?>
                                        <a href="<?php echo chisiamo_h($social['url']); ?>" target="_blank" rel="noopener">
                                            <i class="<?php echo chisiamo_h($social['icon']); ?>"></i>
                                            <span><?php echo chisiamo_h($social['label']); ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="team-empty" id="teamEmpty" hidden>
                <i class="fas fa-face-sad-tear"></i>
                <strong>Nessuno trovato</strong>
                <span>Prova con un altro nome.</span>
            </div>
        </section>

        <section class="join-team-section about-reveal">
            <div>
                <span class="about-kicker">Join</span>
                <h2>Vuoi entrare nel team?</h2>
                <p>
                    Manda candidatura, immagine, nome e descrizione. Se vuoi, aggiungi username o link social.
                </p>
            </div>

            <a href="candidatura-chisiamo" class="about-btn about-btn--primary">
                <i class="fas fa-envelope"></i>
                <span>Invia candidatura</span>
            </a>
        </section>
    </main>

    <?php include '../includes/scroll_indicator.php'; ?>
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
