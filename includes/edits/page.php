<?php
/**
 * /it/edits e /it/edits/{id}: la griglia degli edit e, con un id, lo stesso
 * edit gia' aperto nel player grande. La regola in .htaccess passa l'id
 * come ?edit=.
 *
 * La griglia mostra solo copertine: il video (file o embed Streamable) si
 * carica quando si apre un edit, nel player che prende le proporzioni vere
 * del video. edits.js gestisce filtri, player, link e "visti".
 *
 * Variabili attese: $eLang ('it' | 'en'), $mysqli.
 */

require_once __DIR__ . '/edits.php';
require_once __DIR__ . '/strings.php';
require_once __DIR__ . '/../theme.php';

$S = edits_strings($eLang);
$eReady = edits_ready($mysqli);

$eEdits = [];
$eCategories = [];
if ($eReady) {
    try {
        $eEdits = array_map(static fn(array $row): array => edits_view($row, $eLang), edits_rows($mysqli));
        $eCategories = edits_categories($mysqli, $eLang);
    } catch (Throwable $e) {
        error_log('[edits] ' . $e->getMessage());
        $eReady = false;
        $eEdits = [];
    }
}

$eTexts = edits_page_texts($eReady ? $mysqli : null, $eLang, $S);
$eRanks = edits_popularity_ranks($eEdits);

// Nei filtri solo le categorie che hanno almeno un edit.
$eUsed = array_count_values(array_map(static fn(array $e): string => $e['category']['slug'], $eEdits));
$eCategories = array_values(array_filter($eCategories, static fn(array $c): bool => isset($eUsed[$c['slug']])));

$eLatest = $eEdits[0] ?? null;
$eFeatured = null;
foreach ($eEdits as $edit) {
    if ($edit['featured']) {
        $eFeatured = $edit;
        break;
    }
}
$eFeatured ??= $eLatest;

// /it/edits/28: quell'edit si apre da solo e il link condiviso ha la sua anteprima.
$eOpen = null;
$eNotFound = false;
$eRequested = (int)($_GET['edit'] ?? 0);
if ($eRequested > 0) {
    foreach ($eEdits as $edit) {
        if ($edit['id'] === $eRequested) {
            $eOpen = $edit;
            break;
        }
    }
    $eNotFound = $eOpen === null;
}

if ($eOpen) {
    $ogTitle = 'Cripsum™ - ' . $eOpen['full_title'];
    $ogDescription = $eOpen['music'] !== '' ? '🎵 ' . $eOpen['music'] : $S['meta_description'];
    if ($eOpen['cover'] !== '') {
        $ogImage = $eOpen['cover'];
    }
} else {
    $ogTitle = 'Cripsum™ - ' . $S['page_title'];
    $ogDescription = $eTexts['subtitle'] !== '' ? $eTexts['subtitle'] : $S['meta_description'];
}

// Il bottone "Guardalo su TikTok" (o YouTube...): testo gia' pronto per il player.
$ePost = static fn(?array $post): ?array => $post ? [
    'url' => $post['url'],
    'icon' => $post['icon'],
    'label' => $post['name'] !== '' ? sprintf($S['watch_on'], $post['name']) : $S['watch_post'],
] : null;

// Quello che serve al player: il resto lo legge dalle card.
$eData = array_map(static fn(array $e): array => [
    'id' => $e['id'],
    'title' => $e['title'],
    'serie' => $e['serie'],
    'full_title' => $e['full_title'],
    'description' => $e['description'],
    'music' => $e['music'],
    'category' => $e['category'],
    'source' => $e['source'],
    'video' => $e['video'],
    'embed' => $e['embed'],
    'missing' => $e['missing'],
    'ratio' => $e['ratio'],
    'cover' => $e['cover'],
    'label' => $e['label'],
    'is_new' => $e['is_new'],
    'collab' => $e['collab'],
    'collab_link' => $e['collab_link'],
    'post' => $ePost($e['post']),
    'url' => $e['url'],
], $eEdits);

$eJs = [
    'lang' => $eLang,
    'base' => '/' . $eLang . '/edits',
    'open' => $eOpen['id'] ?? 0,
    'edits' => $eData,
    'strings' => [
        'copied' => $S['copied'],
        'copy_failed' => $S['copy_failed'],
        'collab_with' => $S['collab_with'],
        'new' => $S['new'],
        'seen' => $S['seen'],
        'missing_title' => $S['missing_title'],
        'missing_text' => $S['missing_text'],
        'not_found' => $S['not_found'],
        'player' => $S['player'],
    ],
    'not_found' => $eNotFound,
];

$eCover = static function (array $edit, string $class): string {
    if ($edit['cover'] === '') {
        return '<span class="' . $class . '__empty" aria-hidden="true"><i class="' . shop_h($edit['category']['icon']) . '"></i></span>';
    }
    $html = '';
    // Gli orizzontali si vedono interi, con dietro la stessa immagine sfocata.
    if ($edit['shape'] === 'landscape') {
        $html .= '<img class="' . $class . '__blur" src="' . shop_h($edit['cover']) . '" alt="" aria-hidden="true" loading="lazy" decoding="async">';
    }
    return $html . '<img class="' . $class . '__img" src="' . shop_h($edit['cover']) . '" alt="" loading="lazy" decoding="async">';
};
$eLinkIcon = static fn(string $url): string => str_contains($url, 'tiktok.com') ? 'fa-brands fa-tiktok' : 'fa-solid fa-arrow-up-right-from-square';
?>
<!DOCTYPE html>
<html lang="<?php echo shop_h($eLang); ?>"<?php echo cripsum_theme_html_attr(); ?>>

<head>
    <?php include __DIR__ . '/../head-import.php'; ?>
    <title><?php echo shop_h($ogTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo shop_h(cripsum_asset('/assets/edits/edits.css')); ?>">
    <link rel="stylesheet" href="<?php echo shop_h(cripsum_asset('/assets/edits/edits-player.css')); ?>">
    <?php cripsum_theme_head(); ?>
    <script src="<?php echo shop_h(cripsum_asset('/assets/edits/edits-player.js')); ?>" defer></script>
    <script src="<?php echo shop_h(cripsum_asset('/assets/edits/edits.js')); ?>" defer></script>
</head>

<body class="edits-page">
    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="edits-bg" aria-hidden="true">
        <span class="edits-orb edits-orb--one"></span>
        <span class="edits-orb edits-orb--two"></span>
    </div>

    <main class="edits-shell">
        <section class="edits-hero">
            <div class="edits-hero__text">
                <h1 class="edits-title"><?php echo edits_title_html($eTexts['title']); ?></h1>
                <?php if ($eTexts['subtitle'] !== ''): ?>
                    <p class="edits-lead"><?php echo shop_h($eTexts['subtitle']); ?></p>
                <?php endif; ?>
                <div class="edits-actions">
                    <?php if ($eLatest): ?>
                        <button type="button" class="edits-btn edits-btn--primary" data-edit-open="<?php echo (int)$eLatest['id']; ?>"><i class="fa-solid fa-play" aria-hidden="true"></i> <?php echo shop_h($S['watch_latest']); ?></button>
                    <?php endif; ?>
                    <?php if ($eTexts['link_text'] !== '' && $eTexts['link_url'] !== ''): ?>
                        <a class="edits-btn" href="<?php echo shop_h($eTexts['link_url']); ?>"<?php echo str_starts_with($eTexts['link_url'], '/') ? '' : ' target="_blank" rel="noopener noreferrer"'; ?>><i class="<?php echo shop_h($eLinkIcon($eTexts['link_url'])); ?>" aria-hidden="true"></i> <?php echo shop_h($eTexts['link_text']); ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($eFeatured): ?>
                <button type="button" class="edits-featured edits-featured--<?php echo shop_h($eFeatured['shape']); ?>" data-edit-open="<?php echo (int)$eFeatured['id']; ?>" aria-label="<?php echo shop_h(sprintf($S['watch'], $eFeatured['full_title'])); ?>">
                    <?php echo $eCover($eFeatured, 'edits-featured'); ?>
                    <span class="edits-featured__shade" aria-hidden="true"></span>
                    <?php if ($eFeatured['label'] !== '' || $eFeatured['is_new']): ?>
                        <span class="edits-featured__tags"><span class="edit-label<?php echo $eFeatured['is_new'] ? ' edit-label--new' : ''; ?>"><?php echo shop_h($eFeatured['is_new'] ? $S['new'] : $eFeatured['label']); ?></span></span>
                    <?php endif; ?>
                    <span class="edits-featured__play" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                    <span class="edits-featured__info">
                        <strong><?php echo shop_h($eFeatured['title']); ?></strong>
                        <?php if ($eFeatured['serie'] !== ''): ?>
                            <em><?php echo shop_h($eFeatured['serie']); ?></em>
                        <?php endif; ?>
                        <?php if ($eFeatured['music'] !== ''): ?>
                            <span><i class="fa-solid fa-music" aria-hidden="true"></i> <?php echo shop_h($eFeatured['music']); ?></span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endif; ?>
        </section>

        <?php if (!$eReady || !$eEdits): ?>
            <section class="edits-state">
                <i class="fa-solid fa-clapperboard" aria-hidden="true"></i>
                <strong><?php echo shop_h($eReady ? $S['none_title'] : $S['soon_title']); ?></strong>
                <p><?php echo shop_h($eReady ? $S['none_text'] : $S['soon_text']); ?></p>
                <a class="edits-btn" href="/<?php echo shop_h($eLang); ?>/home"><?php echo shop_h($S['go_home']); ?></a>
            </section>
        <?php else: ?>
            <section class="edits-toolbar" aria-label="<?php echo shop_h($S['search_label']); ?>">
                <div class="edits-toolbar__row">
                    <label class="edits-search">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="editsSearch" placeholder="<?php echo shop_h($S['search']); ?>" aria-label="<?php echo shop_h($S['search_label']); ?>" autocomplete="off">
                        <kbd aria-hidden="true">/</kbd>
                    </label>
                    <?php
                    $eSorts = [
                        'recent' => ['fa-solid fa-clock-rotate-left', $S['sort_recent']],
                        'popular' => ['fa-solid fa-fire', $S['sort_popular']],
                        'name' => ['fa-solid fa-arrow-down-a-z', $S['sort_name']],
                    ];
                    ?>
                    <div class="edits-sort" data-edits-sort>
                        <span class="edits-sort__label" id="editsSortLabel"><?php echo shop_h($S['sort']); ?></span>
                        <button type="button" class="edits-sort__button" id="editsSortButton" aria-haspopup="listbox" aria-expanded="false" aria-controls="editsSortMenu" aria-labelledby="editsSortLabel editsSortButton">
                            <span data-sort-current><?php echo shop_h($S['sort_recent']); ?></span>
                            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <ul class="edits-sort__menu" id="editsSortMenu" role="listbox" tabindex="-1" aria-labelledby="editsSortLabel">
                            <?php $eSortIndex = 0; ?>
                            <?php foreach ($eSorts as $value => [$icon, $label]): ?>
                                <li class="edits-sort__option" id="editsSort-<?php echo $value; ?>" role="option" data-value="<?php echo $value; ?>" aria-selected="<?php echo $value === 'recent' ? 'true' : 'false'; ?>" style="--i: <?php echo $eSortIndex++; ?>">
                                    <i class="<?php echo $icon; ?>" aria-hidden="true"></i>
                                    <span><?php echo shop_h($label); ?></span>
                                    <i class="fa-solid fa-check edits-sort__check" aria-hidden="true"></i>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <label class="edits-toggle">
                        <input type="checkbox" id="editsHideSeen">
                        <span class="edits-toggle__switch" aria-hidden="true"></span>
                        <span><?php echo shop_h($S['hide_seen']); ?></span>
                    </label>
                </div>
                <?php if (count($eCategories) > 1): ?>
                    <div class="edits-chips" role="group" aria-label="<?php echo shop_h($S['categories']); ?>">
                        <button type="button" class="edits-chip is-active" data-filter="all" aria-pressed="true"><?php echo shop_h($S['all']); ?></button>
                        <?php foreach ($eCategories as $c): ?>
                            <button type="button" class="edits-chip" data-filter="<?php echo shop_h($c['slug']); ?>" aria-pressed="false"><i class="<?php echo shop_h($c['icon']); ?>" aria-hidden="true"></i> <?php echo shop_h($c['name']); ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="edits-grid" id="editsGrid">
                <?php foreach ($eEdits as $i => $edit): ?>
                    <article
                        class="edit-card edit-card--<?php echo shop_h($edit['shape']); ?>"
                        data-edit-id="<?php echo (int)$edit['id']; ?>"
                        data-category="<?php echo shop_h($edit['category']['slug']); ?>"
                        data-order="<?php echo (int)$i; ?>"
                        data-rank="<?php echo (int)($eRanks[$edit['id']] ?? $i + 1); ?>"
                        data-title="<?php echo shop_h($edit['title']); ?>"
                        data-search="<?php echo shop_h(mb_strtolower($edit['title'] . ' ' . $edit['serie'] . ' ' . $edit['music'] . ' ' . $edit['collab'] . ' ' . $edit['category']['name'])); ?>">
                        <div class="edit-cover">
                            <?php echo $eCover($edit, 'edit-cover'); ?>
                            <span class="edit-cover__shade" aria-hidden="true"></span>
                            <span class="edit-cover__tags">
                                <?php if ($edit['category']['name'] !== ''): ?>
                                    <span class="edit-cat"><i class="<?php echo shop_h($edit['category']['icon']); ?>" aria-hidden="true"></i> <?php echo shop_h($edit['category']['name']); ?></span>
                                <?php endif; ?>
                                <?php if ($edit['label'] !== '' || $edit['is_new']): ?>
                                    <span class="edit-label<?php echo $edit['is_new'] ? ' edit-label--new' : ''; ?>"><?php echo shop_h($edit['is_new'] ? $S['new'] : $edit['label']); ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="edit-seen"><i class="fa-solid fa-check" aria-hidden="true"></i> <?php echo shop_h($S['seen']); ?></span>
                            <span class="edit-cover__play" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                        </div>
                        <div class="edit-info">
                            <?php /* PreMiD legge questo span: dentro c'e' anche la serie, nascosta, cosi' su Discord resta "Iuno - Wuthering Waves". */ ?>
                            <h3 class="character-name"><span><?php echo shop_h($edit['title']); ?><?php if ($edit['serie'] !== ''): ?><span class="edits-sr"> - <?php echo shop_h($edit['serie']); ?></span><?php endif; ?></span></h3>
                            <?php if ($edit['serie'] !== ''): ?>
                                <p class="edit-serie" aria-hidden="true"><?php echo shop_h($edit['serie']); ?></p>
                            <?php endif; ?>
                            <?php if ($edit['music'] !== ''): ?>
                                <p class="music-info"><i class="fa-solid fa-music" aria-hidden="true"></i><span><?php echo shop_h($edit['music']); ?></span></p>
                            <?php endif; ?>
                            <?php if ($edit['collab'] !== ''): ?>
                                <p class="edit-collab"><i class="fa-solid fa-handshake" aria-hidden="true"></i>
                                    <?php if ($edit['collab_link'] !== ''): ?>
                                        <?php echo str_replace('%s', '<a href="' . shop_h($edit['collab_link']) . '" target="_blank" rel="noopener noreferrer">' . shop_h($edit['collab']) . '</a>', shop_h($S['collab_with'])); ?>
                                    <?php else: ?>
                                        <?php echo shop_h(sprintf($S['collab_with'], $edit['collab'])); ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="edit-card__hit" data-edit-open="<?php echo (int)$edit['id']; ?>" aria-label="<?php echo shop_h(sprintf($S['watch'], $edit['full_title'])); ?>"></button>
                        <img class="rpcimg" src="<?php echo shop_h($edit['presence_image']); ?>" alt="" hidden loading="lazy">
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="edits-state" id="editsEmpty" hidden>
                <i class="fa-solid fa-video-slash" aria-hidden="true"></i>
                <strong><?php echo shop_h($S['empty_title']); ?></strong>
                <p><?php echo shop_h($S['empty_text']); ?></p>
                <button type="button" class="edits-btn" data-edits-reset><?php echo shop_h($S['reset']); ?></button>
            </section>

            <dialog class="edit-theater" id="editTheater" aria-labelledby="editTheaterTitle">
                <div class="edit-theater__stage" data-theater-stage>
                    <button type="button" class="edit-theater__close" data-theater-close aria-label="<?php echo shop_h($S['close']); ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    <button type="button" class="edit-theater__arrow edit-theater__arrow--prev" data-theater-step="-1" aria-label="<?php echo shop_h($S['prev']); ?>"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                    <button type="button" class="edit-theater__arrow edit-theater__arrow--next" data-theater-step="1" aria-label="<?php echo shop_h($S['next']); ?>"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                    <div class="edit-theater__box">
                        <div class="edit-player" data-player></div>
                        <div class="edit-theater__side">
                            <div class="edit-theater__tags" data-theater-tags></div>
                            <div class="edit-theater__heading">
                                <h2 class="edit-theater__title" id="editTheaterTitle" data-theater-title tabindex="-1" autofocus></h2>
                                <p class="edit-theater__serie" data-theater-serie hidden></p>
                            </div>
                            <p class="edit-theater__meta" data-theater-music hidden><i class="fa-solid fa-music" aria-hidden="true"></i><span></span></p>
                            <p class="edit-theater__meta" data-theater-collab hidden><i class="fa-solid fa-handshake" aria-hidden="true"></i><span></span></p>
                            <div class="edit-theater__desc" data-theater-desc hidden></div>
                            <div class="edit-theater__actions">
                                <button type="button" class="edits-btn edits-btn--primary edits-btn--small" data-theater-copy><i class="fa-solid fa-link" aria-hidden="true"></i> <?php echo shop_h($S['copy_link']); ?></button>
                                <a class="edits-btn edits-btn--small" data-theater-post href="#" target="_blank" rel="noopener noreferrer" hidden><i class="fa-brands fa-tiktok" aria-hidden="true"></i> <span></span></a>
                            </div>
                            <div class="edit-theater__nav">
                                <button type="button" class="edits-btn edits-btn--small" data-theater-step="-1"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?php echo shop_h($S['prev']); ?></button>
                                <button type="button" class="edits-btn edits-btn--small" data-theater-step="1"><?php echo shop_h($S['next']); ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </dialog>

            <div class="edits-toast" data-edits-toast role="status" aria-live="polite"></div>
            <script type="application/json" id="editsData"><?php echo json_encode($eJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../scroll_indicator.php'; ?>
    <?php include __DIR__ . '/../' . ($eLang === 'en' ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
