<?php
/** Template condiviso. Il wrapper prepara sessione, funzioni e controllo ban. */
require_once __DIR__ . '/../theme.php';

$loreLang = ($loreLang ?? 'it') === 'en' ? 'en' : 'it';
$loreStrings = [
    'it' => [
        'title' => 'La lore di cripsum.com',
        'description' => 'Le origini di cripsum.com, la sua evoluzione e la community.',
        'index' => 'Capitoli',
        'skip' => 'Vai alla storia',
        'related' => 'Approfondimenti',
    ],
    'en' => [
        'title' => 'The cripsum.com lore',
        'description' => 'The origins of cripsum.com, its evolution and community.',
        'index' => 'Chapters',
        'skip' => 'Skip to the story',
        'related' => 'Related pages',
    ],
][$loreLang];
$loreContent = require __DIR__ . '/content.php';
$loreChapters = array_values(array_filter($loreContent[$loreLang], static fn(array $chapter): bool => !empty($chapter['events'])));

if (!function_exists('lore_h')) {
    function lore_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function lore_local_url(string $value): string
    {
        return preg_match('~^/(?!/)[^\\\\\\\\\\x00-\\x20]*$~D', $value) === 1 ? $value : '';
    }
}

$ogTitle = $loreStrings['title'];
$ogDescription = $loreStrings['description'];
$ogUrl = 'https://cripsum.com/' . $loreLang . '/lore';
?>
<!DOCTYPE html>
<html lang="<?= lore_h($loreLang) ?>"<?= cripsum_theme_html_attr() ?>>
<head>
    <?php include __DIR__ . '/../head-import.php'; ?>
    <title><?= lore_h($loreStrings['title']) ?></title>
    <meta name="description" content="<?= lore_h($loreStrings['description']) ?>">
    <link rel="canonical" href="<?= lore_h($ogUrl) ?>">
    <link rel="alternate" hreflang="it" href="https://cripsum.com/it/lore">
    <link rel="alternate" hreflang="en" href="https://cripsum.com/en/lore">
    <link rel="stylesheet" href="<?= lore_h(cripsum_theme_asset('/assets/lore/lore.css')) ?>">
    <?php cripsum_theme_head(); ?>
    <script src="<?= lore_h(cripsum_theme_asset('/assets/lore/lore.js')) ?>" defer></script>
</head>
<body class="lore-page">
    <a class="lore-skip" href="#story"><?= lore_h($loreStrings['skip']) ?></a>
    <?php include __DIR__ . '/../navbar.php'; ?>

    <main class="lore-shell">
        <h1 class="lore-title"><?= lore_h($loreStrings['title']) ?></h1>

        <?php if (count($loreChapters) > 1): ?>
            <nav class="lore-index" aria-label="<?= lore_h($loreStrings['index']) ?>">
                <?php foreach ($loreChapters as $loreChapter): ?>
                    <a class="lore-index__link" href="#<?= lore_h($loreChapter['id']) ?>" data-lore-chapter="<?= lore_h($loreChapter['id']) ?>"><?= lore_h($loreChapter['title']) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <div class="lore-story" id="story">
            <?php foreach ($loreChapters as $loreChapter): ?>
                <section class="lore-chapter" id="<?= lore_h($loreChapter['id']) ?>" aria-labelledby="<?= lore_h($loreChapter['id']) ?>-title" data-lore-section>
                    <h2 id="<?= lore_h($loreChapter['id']) ?>-title"><?= lore_h($loreChapter['title']) ?></h2>
                    <?php foreach ($loreChapter['events'] as $loreEvent): ?>
                        <article class="lore-event" id="<?= lore_h($loreEvent['id']) ?>" aria-label="<?= lore_h($loreEvent['title'] ?? $loreChapter['title']) ?>">
                            <?php if (!empty($loreEvent['date'])): ?>
                                <time class="lore-date" datetime="<?= lore_h($loreEvent['date']) ?>"><?= lore_h($loreEvent['period'] ?? $loreEvent['date']) ?></time>
                            <?php endif; ?>
                            <?php if (!empty($loreEvent['title'])): ?>
                                <h3><a href="#<?= lore_h($loreEvent['id']) ?>"><?= lore_h($loreEvent['title']) ?></a></h3>
                            <?php endif; ?>
                            <?php foreach ($loreEvent['paragraphs'] as $loreParagraph): ?>
                                <p><?= lore_h($loreParagraph) ?></p>
                            <?php endforeach; ?>
                            <?php foreach ($loreEvent['images'] ?? [] as $loreImage): ?>
                                <?php $loreImageUrl = lore_local_url($loreImage['src']); if ($loreImageUrl === '') { continue; } ?>
                                <figure class="lore-image">
                                    <img src="<?= lore_h($loreImageUrl) ?>" alt="<?= lore_h($loreImage['alt']) ?>" width="<?= max(1, (int)$loreImage['width']) ?>" height="<?= max(1, (int)$loreImage['height']) ?>" loading="lazy" decoding="async">
                                    <?php if (!empty($loreImage['caption'])): ?><figcaption><?= lore_h($loreImage['caption']) ?></figcaption><?php endif; ?>
                                </figure>
                            <?php endforeach; ?>
                            <?php if (!empty($loreEvent['links'])): ?>
                                <ul class="lore-links" aria-label="<?= lore_h($loreStrings['related']) ?>">
                                    <?php foreach ($loreEvent['links'] as $loreLink): ?>
                                        <?php $loreLinkUrl = lore_local_url($loreLink['href']); if ($loreLinkUrl === '') { continue; } ?>
                                        <li><a href="<?= lore_h($loreLinkUrl) ?>"><?= lore_h($loreLink['label']) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </main>

    <?php include __DIR__ . '/../' . ($loreLang === 'en' ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
