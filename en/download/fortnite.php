<?php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

checkBan($mysqli);

$download = [
    'title' => 'Fortnite Hacks',
    'kicker' => 'Undetectable',
    'description' => 'Download the sickest Fortnite hacks',
    'image' => '/img/fortnitehack.jpg',
    'image_alt' => 'Fortnite hacks image',
    'href' => '/random%20stuff/itfortnitehacks.txt',
    'download_name' => 'fortnite hacks method tutorial.txt',
    'button' => 'Download the file',
    'note' => 'Infinite V-bucks, aimbot, wallhack, and the TUNG TUNG TUNG SAHUR skin for free',
    'back_href' => '/en/download.php',
    'meta' => [
        ['label' => 'Type', 'value' => 'TXT File'],
        ['label' => 'Theme', 'value' => 'Hacks'],
        ['label' => 'Platform', 'value' => 'Browser'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../../includes/head-import.php'; ?>
    <title>Cripsum™ - <?php echo htmlspecialchars($download['title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/download-single.css?v=2.1-buttons-fix">
    <script src="/js/download-single.js?v=2.3" defer></script>
</head>

<body class="download-page">
    <?php include '../../includes/navbar.php'; ?>


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
                    data-download-image>

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


                <div class="download-note download-note--warning">
                    <i class="fa-solid fa-triangle-exclamation"></i>
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
                        data-download-title="<?php echo htmlspecialchars($download['title'], ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa-solid fa-download"></i>
                        <span><?php echo htmlspecialchars($download['button'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>

                    <button class="download-secondary-btn" type="button" data-copy-download>
                        <i class="fa-solid fa-link"></i>
                        <span>Copy link</span>
                    </button>

                    <a class="download-secondary-btn" href="<?php echo htmlspecialchars($download['back_href'], ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Back to downloads</span>
                    </a>
                </div>
            </div>
        </section>

        <section class="download-info-grid fadeup">
            <article class="download-info-card">
                <h2>Before downloading</h2>
                <ol>
                    <li>Click the download button.</li>
                    <li>Save the file wherever you like.</li>
                    <li>Remember: this is purely ironic, not an actual cheat.</li>
                </ol>
            </article>

            <article class="download-info-card">
                <h2>Note</h2>
                <p>The download will start automatically after clicking the download button.</p>
            </article>
        </section>
    </main>

    <div class="download-toast" data-download-toast hidden>
        <i class="fa-solid fa-check"></i>
        <span>Download started</span>
    </div>

    <?php include '../../includes/footer-en.php'; ?>

    <script>
        (() => {
            const _tracked = new Set();

            document.addEventListener('click', (e) => {
                // Intercetta solo il bottone principale di download di questa pagina
                const btn = e.target.closest('.download-main-btn[href]');
                if (!btn) return;

                const itemId = btn.dataset.downloadTitle || btn.href;
                if (_tracked.has(itemId)) return;
                _tracked.add(itemId);

                fetch('/api/missions/track_download.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        item_id: itemId
                    }),
                }).catch(() => {});
            }, {
                passive: true
            });
        })();
    </script>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>