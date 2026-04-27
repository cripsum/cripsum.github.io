<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

checkBan($mysqli);

$edits = [
    [
        'id' => 29,
        'order' => 0,
        'category' => 'influencer',
        'streamable' => 'https://streamable.com/e/934apl?',
        'badge' => 'Latest',
        'icon' => 'fas fa-star character-icon',
        'title_html' => 'Danil Showman',
        'title_text' => 'Danil Showman',
        'music' => 'Sto bene al mare - Marco Mengoni',
    ],
    [
        'id' => 28,
        'order' => 1,
        'category' => 'games',
        'streamable' => 'https://streamable.com/e/kez9r2?',
        'badge' => 'Collab',
        'icon' => 'fas fa-gamepad character-icon',
        'title_html' => '<p>The herta &amp; Sparkle - HSR <br/> (collab con <a class="linkbianco" href="https://www.tiktok.com/@nauz_aep">Nauz</a>)</p>',
        'title_text' => 'The herta & Sparkle - HSR (collab con Nauz )',
        'music' => 'TWICE - Strategy',
    ],
    [
        'id' => 27,
        'order' => 2,
        'category' => 'anime',
        'streamable' => 'https://streamable.com/e/io9mwe?',
        'badge' => '',
        'icon' => 'fas fa-user character-icon',
        'title_html' => 'Kōtarō Bokuto - Haikyuu',
        'title_text' => 'Kōtarō Bokuto - Haikyuu',
        'music' => 'QMIIR - Sempero',
    ],
    [
        'id' => 26,
        'order' => 3,
        'category' => 'games',
        'streamable' => 'https://streamable.com/e/ypekqr?',
        'badge' => '',
        'icon' => 'fas fa-gamepad character-icon',
        'title_html' => 'Iuno - Wuthering Waves',
        'title_text' => 'Iuno - Wuthering Waves',
        'music' => 'XYLØ - Afterlife (Ark Patrol Remix)',
    ],
    [
        'id' => 25,
        'order' => 4,
        'category' => 'anime',
        'streamable' => 'https://streamable.com/e/rh84rz?',
        'badge' => '',
        'icon' => 'fas fa-user character-icon',
        'title_html' => 'Perfect Cell - DragonBall',
        'title_text' => 'Perfect Cell - DragonBall',
        'music' => 'Jmilton, CHASHKAKEFIRA - Reinado',
    ],
    [
        'id' => 24,
        'order' => 5,
        'category' => 'anime',
        'streamable' => 'https://streamable.com/e/41cdia?',
        'badge' => '',
        'icon' => 'fas fa-user character-icon',
        'title_html' => 'Waguri Kaoruko',
        'title_text' => 'Waguri Kaoruko',
        'music' => 'Tate McRae - it\'s ok i\'m ok',
    ],
    [
        'id' => 23,
        'order' => 6,
        'category' => 'games',
        'streamable' => 'https://streamable.com/e/xzj4ag?',
        'badge' => '',
        'icon' => 'fas fa-gamepad character-icon',
        'title_html' => 'Evelyn - Zenless Zone Zero',
        'title_text' => 'Evelyn - Zenless Zone Zero',
        'music' => 'Charli XCX - Track 10',
    ],
    [
        'id' => 22,
        'order' => 7,
        'category' => 'games',
        'streamable' => 'https://streamable.com/e/tfs4nt?',
        'badge' => '',
        'icon' => 'fas fa-gamepad character-icon',
        'title_html' => 'Shorekeeper - Wuthering Waves',
        'title_text' => 'Shorekeeper - Wuthering Waves',
        'music' => 'Irokz - Toxic Potion (slowed)',
    ],
    [
        'id' => 21,
        'order' => 8,
        'category' => 'anime',
        'streamable' => 'https://streamable.com/e/lowaxh?',
        'badge' => '',
        'icon' => 'fas fa-user character-icon',
        'title_html' => 'Karane Inda',
        'title_text' => 'Karane Inda',
        'music' => 'Katy Perry - Harleys in Hawaii',
    ],
    [
        'id' => 20,
        'order' => 9,
        'category' => 'games',
        'streamable' => 'https://streamable.com/e/8iv09j?',
        'badge' => '',
        'icon' => 'fas fa-gamepad character-icon',
        'title_html' => 'Dante - Devil May Cry',
        'title_text' => 'Dante - Devil May Cry',
        'music' => 'ATLXS - PASSO BEM SOLTO (super slowed)',
    ],
    [
        'id' => 19,
        'order' => 10,
        'category' => 'anime',
        'streamable' => 'https://streamable.com/e/gyfwer?',
        'badge' => '',
        'icon' => 'fas fa-user character-icon',
        'title_html' => 'Sung Jin-Woo - Solo Levelling',
        'title_text' => 'Sung Jin-Woo - Solo Levelling',
        'music' => 'Peak - Re-Up',
    ],
    [
        'id' => 18,
        'order' => 11,
        'category' => 'anime',
        'streamable' => 'https://streamable.com/e/1n4azs?',
        'badge' => '',
        'icon' => 'fas fa-user character-icon',
        'title_html' => 'Nagi - Blue Lock',
        'title_text' => 'Nagi - Blue Lock',
        'music' => 'One of the girls X good for you',
    ],
    [
        'id' => 17,
        'order' => 12,
        'category' => 'games',
        'streamable' => 'https://streamable.com/e/79a35r?',
        'badge' => '',
        'icon' => 'fas fa-gamepad character-icon',
        'title_html' => 'Cool Mita / Cappie - MiSide',
        'title_text' => 'Cool Mita / Cappie - MiSide',
        'music' => 'Bruno Mars - Treasure',
    ],
    [
        'id' => 16,
        'order' => 13,
        'category' => 'games',
        'streamable' => 'https://streamable.com/e/1j8bd8?',
        'badge' => '',
        'icon' => 'fas fa-gamepad character-icon',
        'title_html' => 'Crazy Mita - MiSide',
        'title_text' => 'Crazy Mita - MiSide',
        'music' => 'Imogen Heap - Headlock',
    ],
    [
        'id' => 15,
        'order' => 14,
        'category' => 'anime',
        'streamable' => 'https://streamable.com/e/nkccr6?',
        'badge' => '',
        'icon' => 'fas fa-user character-icon',
        'title_html' => 'Yuki Suou - Roshidere',
        'title_text' => 'Yuki Suou - Roshidere',
        'music' => 'Rarin - Mamacita',
    ],
    [
        'id' => 14,
        'order' => 15,
        'category' => 'anime',
        'streamable' => 'https://streamable.com/e/wy68h4?',
        'badge' => '',
        'icon' => 'fas fa-user character-icon',
        'title_html' => 'Alya Kujou - Roshidere',
        'title_text' => 'Alya Kujou - Roshidere',
        'music' => 'Clean Bandit - Solo',
    ],
    [
        'id' => 13,
        'order' => 16,
        'category' => 'anime',
        'streamable' => 'https://streamable.com/e/gyfwui?',
        'badge' => '',
        'icon' => 'fas fa-user character-icon',
        'title_html' => 'Alya Kujou - Roshidere',
        'title_text' => 'Alya Kujou - Roshidere',
        'music' => 'Subway Surfers phonk trend',
    ],
    [
        'id' => 12,
        'order' => 17,
        'category' => 'influencer',
        'streamable' => 'https://streamable.com/e/pdcav0?',
        'badge' => '',
        'icon' => 'fas fa-star character-icon',
        'title_html' => 'Luca Arlia (meme)',
        'title_text' => 'Luca Arlia (meme)',
        'music' => 'Luca Carboni - Luca lo stesso',
    ],
    [
        'id' => 11,
        'order' => 18,
        'category' => 'anime',
        'streamable' => 'https://streamable.com/e/mx6h2n?',
        'badge' => '',
        'icon' => 'fas fa-user character-icon',
        'title_html' => 'Yuki Suou - Roshidere',
        'title_text' => 'Yuki Suou - Roshidere',
        'music' => 'PnB Rock - Unforgettable (Freestyle)',
    ],
    [
        'id' => 10,
        'order' => 19,
        'category' => 'anime',
        'streamable' => 'https://streamable.com/e/ml3dve?',
        'badge' => '',
        'icon' => 'fas fa-user character-icon',
        'title_html' => 'Alya Kujou - Roshidere',
        'title_text' => 'Alya Kujou - Roshidere',
        'music' => 'Rarin & Frozy - Kompa',
    ],
    [
        'id' => 9,
        'order' => 20,
        'category' => 'sports',
        'streamable' => 'https://streamable.com/e/bf8j16?',
        'badge' => '',
        'icon' => 'fas fa-futbol character-icon',
        'title_html' => 'Cristiano Ronaldo',
        'title_text' => 'Cristiano Ronaldo',
        'music' => 'G-Eazy - Tumblr Girls',
    ],
    [
        'id' => 8,
        'order' => 21,
        'category' => 'games',
        'streamable' => 'https://streamable.com/e/zjyoct?',
        'badge' => '',
        'icon' => 'fas fa-gamepad character-icon',
        'title_html' => 'Mandy - Brawl Stars',
        'title_text' => 'Mandy - Brawl Stars',
        'music' => 'NCTS - NEXT!',
    ],
    [
        'id' => 7,
        'order' => 22,
        'category' => 'anime',
        'streamable' => 'https://streamable.com/e/bllcn8?',
        'badge' => '',
        'icon' => 'fas fa-user character-icon',
        'title_html' => 'Choso - Jujutsu Kaisen',
        'title_text' => 'Choso - Jujutsu Kaisen',
        'music' => 'The Weeknd - Is There Someone Else?',
    ],
    [
        'id' => 6,
        'order' => 23,
        'category' => 'influencer',
        'streamable' => 'https://streamable.com/e/r2bppn?',
        'badge' => '',
        'icon' => 'fas fa-star character-icon',
        'title_html' => 'Nym',
        'title_text' => 'Nym',
        'music' => 'Chris Brown - Under the influence',
    ],
    [
        'id' => 5,
        'order' => 24,
        'category' => 'games',
        'streamable' => 'https://streamable.com/e/zd75uc?',
        'badge' => '',
        'icon' => 'fas fa-gamepad character-icon',
        'title_html' => 'Mortis - Brawl Stars',
        'title_text' => 'Mortis - Brawl Stars',
        'music' => 'DJ FNK - Slide da Treme Melódica v2',
    ],
    [
        'id' => 4,
        'order' => 25,
        'category' => 'influencer',
        'streamable' => 'https://streamable.com/e/r9ygoy?',
        'badge' => '',
        'icon' => 'fas fa-star character-icon',
        'title_html' => 'Nino balletto tattico',
        'title_text' => 'Nino balletto tattico',
        'music' => 'Zara Larsson - Lush Life',
    ],
    [
        'id' => 3,
        'order' => 26,
        'category' => 'influencer',
        'streamable' => 'https://streamable.com/e/vnqxdt?',
        'badge' => '',
        'icon' => 'fas fa-star character-icon',
        'title_html' => 'Mates - Crossbar challenge',
        'title_text' => 'Mates - Crossbar challenge',
        'music' => 'G-Eazy - Lady Killers II',
    ],
    [
        'id' => 2,
        'order' => 27,
        'category' => 'movies',
        'streamable' => 'https://streamable.com/e/htbn8k?',
        'badge' => '',
        'icon' => 'fas fa-film character-icon',
        'title_html' => 'Homelander - The Boys',
        'title_text' => 'Homelander - The Boys',
        'music' => 'MGMT - Little Dark Age',
    ],
    [
        'id' => 1,
        'order' => 28,
        'category' => 'movies',
        'streamable' => 'https://streamable.com/e/x40nn6?',
        'badge' => '',
        'icon' => 'fas fa-film character-icon',
        'title_html' => 'Heisenberg - Breaking Bad',
        'title_text' => 'Heisenberg - Breaking Bad',
        'music' => 'Travis Scott - MY EYES',
    ],
];

$categories = [
    'all' => 'All Edits',
    'anime' => 'Anime',
    'games' => 'Games',
    'sports' => 'Sports',
    'movies' => 'Movies & TV',
    'influencer' => 'Influencer',
];

$totalEdits = count($edits);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Edits</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/edits.css?v=3-edits-v2">
    <script src="/js/edits.js?v=3-edits-v3" defer></script>
</head>

<body class="edits-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="edits-bg" aria-hidden="true">
        <span class="edits-orb edits-orb--one"></span>
        <span class="edits-orb edits-orb--two"></span>
        <span class="edits-grid-bg"></span>
    </div>

    <main class="edits-shell">
        <section class="edits-hero fadeup">
            <div class="edits-hero__text">
                <span class="edits-pill">Edit gallery</span>
                <h1>My Latest Edits</h1>
                <p>Una raccolta dei miei edit. Filtra, cerca e apri quello che vuoi vedere.</p>

                <div class="edits-hero__actions">
                    <a href="https://tiktok.com/@cripsum" class="tiktok-link" target="_blank" rel="noopener">
                        <i class="fab fa-tiktok"></i>
                        <span>TikTok</span>
                    </a>

                    <span class="edits-count-chip">
                        <strong><?php echo (int)$totalEdits; ?></strong>
                        edit
                    </span>
                </div>
            </div>
        </section>

        <section class="edits-toolbar fadeup" aria-label="Filtri edits">
            <div class="edits-search">
                <i class="fas fa-search"></i>
                <input type="search" id="editSearch" placeholder="Cerca per nome o musica..." autocomplete="off">
            </div>

            <div class="filter-container" role="group" aria-label="Categorie">
                <?php foreach ($categories as $key => $label): ?>
                    <button type="button" class="filter-btn <?php echo $key === 'all' ? 'active' : ''; ?>" data-filter="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="edits-toolbar__bottom">
                <label class="edits-sort">
                    <span>Ordina</span>
                    <select id="editSort">
                        <option value="recent">Recenti</option>
                        <option value="name">Nome</option>
                        <option value="category">Categoria</option>
                    </select>
                </label>

                <div class="edits-result-count" id="editResultCount">
                    <?php echo (int)$totalEdits; ?> edit mostrati
                </div>

                <button type="button" class="edits-reset" id="editReset">
                    Reset
                </button>
            </div>
        </section>

        <section class="edits-container">
            <div class="edits-grid" id="editsGrid">
                <?php foreach ($edits as $edit): ?>
                    <?php
                    $editId = (int)$edit['id'];
                    $category = htmlspecialchars($edit['category'], ENT_QUOTES, 'UTF-8');
                    $streamable = htmlspecialchars($edit['streamable'], ENT_QUOTES, 'UTF-8');
                    $titleText = htmlspecialchars($edit['title_text'], ENT_QUOTES, 'UTF-8');
                    $music = htmlspecialchars($edit['music'], ENT_QUOTES, 'UTF-8');
                    $badge = trim($edit['badge']);
                    $badgeClass = $badge !== '' ? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $badge)) : '';
                    $icon = htmlspecialchars($edit['icon'], ENT_QUOTES, 'UTF-8');
                    ?>
                    <article
                        class="edit-card"
                        data-edit-id="<?php echo $editId; ?>"
                        data-order="<?php echo (int)$edit['order']; ?>"
                        data-category="<?php echo $category; ?>"
                        data-title="<?php echo $titleText; ?>"
                        data-music="<?php echo $music; ?>"
                        onclick="playVideo(this, <?php echo $editId; ?>)"
                    >
                        <?php if ($badge !== ''): ?>
                            <div class="edit-badge edit-badge--<?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="watched-badge">
                            <i class="fas fa-check"></i>
                            <span>Visto</span>
                        </div>

                        <div class="video-container">
                            <iframe
                                src="about:blank"
                                data-src="<?php echo $streamable; ?>"
                                class="video-iframe"
                                loading="lazy"
                                allow="fullscreen; autoplay"
                                allowfullscreen
                                id="video-<?php echo $editId; ?>"
                                title="<?php echo $titleText; ?>">
                            </iframe>

                            <div class="video-skeleton" aria-hidden="true"></div>

                            <div class="video-overlay">
                                <div class="play-button">
                                    <i class="fas fa-play"></i>
                                </div>
                            </div>
                        </div>

                        <div class="edit-info">
                            <div class="character-name">
                                <i class="<?php echo $icon; ?>"></i>
                                <span><?php echo $edit['title_html']; ?></span>
                            </div>

                            <div class="music-info">
                                <i class="fas fa-music music-icon"></i>
                                <span><?php echo $music; ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="edits-empty" id="editsEmpty" hidden>
                <i class="fas fa-video-slash"></i>
                <strong>Nessun edit trovato</strong>
                <p>Prova a cambiare filtro o ricerca.</p>
            </div>
        </section>
    </main>

    <?php include '../includes/scroll_indicator.php'; ?>

    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement">
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
