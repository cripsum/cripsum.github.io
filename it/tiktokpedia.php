<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['error_message'] = "mi dispiace, ma la pagina TikTokPedia™ è in manutenzione. riprova più tardi.";
    header('Location: home');
    exit();
}

if (!isOwner()) {
    $_SESSION['error_message'] = "mi dispiace, ma la pagina TikTokPedia™ è in manutenzione. riprova più tardi.";
    header('Location: home');
    exit();
}

$voices = [
    [
        'name' => 'Cripsum™',
        'slug' => 'cripsum',
        'description' => 'Editor, creator e voce principale della TikTokpedia. Autoironia inclusa, serietà poca.',
        'image' => '../img/pfp choso2 cc.png',
        'tiktok' => 'https://www.tiktok.com/@cripsum',
        'tags' => ['creator', 'editor', 'storico'],
    ],
    [
        'name' => 'napoloris222',
        'slug' => 'napoloris222',
        'description' => 'Nino e Napo, due presenze storiche della raccolta. Energia Brawl Stars Italia.',
        'image' => '../img/foto_nino.jpg',
        'tiktok' => 'https://www.tiktok.com/@napoloris222',
        'tags' => ['tiktok', 'brawl', 'storico'],
    ],
];

$tagSet = [];
foreach ($voices as $voice) {
    foreach ($voice['tags'] as $tag) {
        $tagSet[$tag] = true;
    }
}
$tags = array_keys($tagSet);
sort($tags);

function tp_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - TikTokpedia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/tiktokpedia.css?v=2.0">
    <script src="/js/tiktokpedia.js?v=2.0" defer></script>
</head>

<body class="tiktokpedia-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="tp-bg" aria-hidden="true">
        <span></span>
        <span></span>
    </div>

    <main class="tp-shell">
        <section class="tp-hero tp-reveal">
            <div class="tp-hero-text">

                <h1>TikTokpedia</h1>

                <p>
                    Una piccola wiki/raccolta con profili, riferimenti e robe legate al lato TikTok del sito.
                </p>
            </div>

            <div class="tp-hero-card">
                <span>Voci presenti</span>
                <strong><?php echo count($voices); ?></strong>
            </div>
        </section>

        <section class="tp-toolbar tp-reveal" aria-label="Filtri TikTokpedia">
            <div class="tp-search">
                <i class="fas fa-search"></i>
                <input type="search" id="tpSearch" placeholder="Cerca voce, descrizione o tag..." autocomplete="off">
            </div>

            <div class="tp-filters" role="group" aria-label="Filtra per tag">
                <button type="button" class="tp-filter is-active" data-filter="all">Tutti</button>
                <?php foreach ($tags as $tag): ?>
                    <button type="button" class="tp-filter" data-filter="<?php echo tp_h($tag); ?>">
                        <?php echo tp_h($tag); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="tp-toolbar-bottom">
                <span id="tpResultCount"><?php echo count($voices); ?> voci</span>
                <button type="button" class="tp-reset" id="tpReset">Reset</button>
            </div>
        </section>

        <section class="tp-grid" id="tpGrid">
            <?php foreach ($voices as $voice): ?>
                <?php $tagsText = implode(' ', $voice['tags']); ?>
                <article
                    class="tp-card tp-reveal"
                    data-name="<?php echo tp_h($voice['name']); ?>"
                    data-description="<?php echo tp_h($voice['description']); ?>"
                    data-tags="<?php echo tp_h($tagsText); ?>"
                    id="<?php echo tp_h($voice['slug']); ?>">
                    <div class="tp-card-image">
                        <img
                            src="<?php echo tp_h($voice['image']); ?>"
                            alt="<?php echo tp_h($voice['name']); ?>"
                            loading="lazy"
                            onerror="this.onerror=null; this.parentElement.classList.add('is-broken'); this.remove();">
                        <span class="tp-image-fallback">
                            <?php echo tp_h(mb_strtoupper(mb_substr($voice['name'], 0, 1, 'UTF-8'), 'UTF-8')); ?>
                        </span>
                    </div>

                    <div class="tp-card-body">
                        <h2><?php echo tp_h($voice['name']); ?></h2>
                        <p><?php echo tp_h($voice['description']); ?></p>

                        <div class="tp-tags">
                            <?php foreach ($voice['tags'] as $tag): ?>
                                <span><?php echo tp_h($tag); ?></span>
                            <?php endforeach; ?>
                        </div>

                        <div class="tp-card-actions">
                            <a class="tp-btn tp-btn-main" href="<?php echo tp_h($voice['tiktok']); ?>" target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-tiktok"></i>
                                TikTok
                            </a>

                            <button class="tp-btn tp-btn-ghost" type="button" data-copy-link>
                                <i class="fas fa-link"></i>
                                Copia link
                            </button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="tp-empty" id="tpEmpty" hidden>
            <i class="fas fa-magnifying-glass"></i>
            <strong>Nessuna voce trovata</strong>
            <p>Prova a cambiare ricerca o filtro.</p>
        </section>

        <section class="tp-note tp-reveal">
            <i class="fas fa-circle-info"></i>
            <p>Altre voci arriveranno quando ci saranno contenuti veri da aggiungere.</p>
        </section>
    </main>

    <button class="tp-top" type="button" id="tpTop" aria-label="Torna su">
        <i class="fas fa-arrow-up"></i>
    </button>

    <div class="tp-toast" id="tpToast" hidden>
        <i class="fas fa-check"></i>
        <span>Link copiato</span>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>