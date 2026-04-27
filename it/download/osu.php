<?php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

checkBan($mysqli);

$download = [
    'title' => 'osu!',
    'kicker' => 'Download',
    'description' => 'Scarica osu! dal repository ufficiale. Un rhythm game veloce, preciso e molto punitivo.',
    'image' => '/img/osu.jpg',
    'image_alt' => 'Immagine osu!',
    'href' => 'https://github.com/ppy/osu/releases/latest/download/install.exe',
    'download_name' => '',
    'button' => 'Scarica osu!',
    'note' => 'Link esterno verso il download ufficiale da GitHub.',
    'back_href' => '/it/download.php',
    'meta' => [
        ['label' => 'Tipo', 'value' => 'Installer EXE'],
        ['label' => 'Piattaforma', 'value' => 'Windows'],
        ['label' => 'Fonte', 'value' => 'GitHub ufficiale'],
    ],
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../../includes/head-import.php'; ?>
    <title>Cripsum™ - <?php echo htmlspecialchars($download['title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/download-single.css?v=2.1-buttons-fix">
    <script src="/js/download-single.js?v=2.1-buttons-fix" defer></script>
</head>

<body class="download-page">
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <div class="download-bg" aria-hidden="true">
        <span class="download-orb download-orb--one"></span>
        <span class="download-orb download-orb--two"></span>
        <span class="download-grid-bg"></span>
    </div>

    <main class="download-shell">
        <section class="download-card fadeup">
            <div class="download-cover-wrap">
                <img
                    class="download-cover"
                    src="<?php echo htmlspecialchars($download['image'], ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars($download['image_alt'], ENT_QUOTES, 'UTF-8'); ?>"
                    loading="lazy"
                    data-download-image
                >

                <div class="download-cover-glow" aria-hidden="true"></div>
            </div>

            <div class="download-content">
                <span class="download-kicker"><?php echo htmlspecialchars($download['kicker'], ENT_QUOTES, 'UTF-8'); ?></span>

                <h1 class="download-title">
                    <?php echo htmlspecialchars($download['title'], ENT_QUOTES, 'UTF-8'); ?>
                </h1>

                <p class="download-description">
                    <?php echo htmlspecialchars($download['description'], ENT_QUOTES, 'UTF-8'); ?>
                </p>


                <div class="download-note download-note--info">
                    <i class="fab fa-github"></i>
                    <p><?php echo htmlspecialchars($download['note'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>


                <div class="download-meta" aria-label="Informazioni file">
                    <?php foreach ($download['meta'] as $item): ?>
                        <?php if (!empty($item['value'])): ?>
                            <div class="download-meta-item">
                                <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <strong><?php echo htmlspecialchars($item['value'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="download-actions">
                    <a
                        class="download-main-btn"
                        href="<?php echo htmlspecialchars($download['href'], ENT_QUOTES, 'UTF-8'); ?>"
                        <?php if (!empty($download['download_name'])): ?>
                            download="<?php echo htmlspecialchars($download['download_name'], ENT_QUOTES, 'UTF-8'); ?>"
                        <?php endif; ?>
                        data-download-link
                        data-download-title="<?php echo htmlspecialchars($download['title'], ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <i class="fas fa-download"></i>
                        <span><?php echo htmlspecialchars($download['button'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>

                    <button class="download-secondary-btn" type="button" data-copy-download>
                        <i class="fas fa-link"></i>
                        <span>Copia link</span>
                    </button>

                    <a class="download-secondary-btn" href="<?php echo htmlspecialchars($download['back_href'], ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-arrow-left"></i>
                        <span>Torna ai download</span>
                    </a>
                </div>
            </div>
        </section>

        <section class="download-info-grid fadeup">
            <article class="download-info-card">
                <h2>Prima di scaricare</h2>
                <ol>
                    <li>Clicca il pulsante download.</li>
                    <li>Apri l’installer scaricato.</li>
                    <li>Segui la procedura di installazione.</li>
                </ol>
            </article>

            <article class="download-info-card">
                <h2>Nota</h2>
                <p>Il download resta statico. Nessun account extra, nessun pagamento, nessun sistema nascosto.</p>
            </article>
        </section>
    </main>

    <div class="download-toast" data-download-toast hidden>
        <i class="fas fa-check"></i>
        <span>Download avviato</span>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
