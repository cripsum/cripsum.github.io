<?php
declare(strict_types=1);

/**
 * bio.cripsum.com/esplora: le pagine della community (tavola 3).
 *
 *   ?vista=vetrina|nuove|viste|amici   (amici solo per chi e' dentro)
 *   ?q=...                             cerca per nome, username o alias
 *   ?pagina=N
 *
 * Lo staff vede la stellina sulle schede e da li' mette o toglie la vetrina.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bio_pages.php';

cripsum_bio_only('esplora');
checkBan($mysqli);
cripsum_bio_auto_login($mysqli);

$lang = bio_lang();
$t = static fn(string $key): string => bio_t($key, $lang);
$userId = (int)($_SESSION['user_id'] ?? 0);
$ready = bio_explore_ready($mysqli);
$staff = $userId > 0 && $ready && profile_is_staff();

$views = $userId > 0 ? BIO_EXPLORE_VIEWS : array_values(array_diff(BIO_EXPLORE_VIEWS, ['amici']));
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 40);
$page = max(1, (int)($_GET['pagina'] ?? 1));
$view = (string)($_GET['vista'] ?? '');
if (!in_array($view, $views, true)) {
    // Di base la vetrina; finche' e' vuota, le pagine nuove.
    [$firstFeatured] = bio_explore_cards($mysqli, 'vetrina', '', 0, 1, 1);
    $view = $firstFeatured ? 'vetrina' : 'nuove';
}

[$cards, $more] = bio_explore_cards($mysqli, $view, $q, $userId, $page);

$link = static function (array $changes) use ($view, $q): string {
    $query = array_filter(array_merge(['vista' => $view, 'q' => $q], $changes), static fn($v): bool => $v !== '' && $v !== null);

    return '/esplora' . ($query ? '?' . http_build_query($query) : '');
};

bio_page_open($t('explore') . ' — ' . parse_url(cripsum_bio_url(), PHP_URL_HOST), $t('explore_lead'), $lang, 'bh-page-esplora');
?>
<header class="bh-ex-head">
    <div>
        <h1><?= bio_h($t('explore')) ?></h1>
        <p><?= bio_h($t('explore_lead')) ?></p>
    </div>
    <form class="bh-search" action="/esplora" method="get" role="search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="hidden" name="vista" value="<?= bio_h($view) ?>">
        <input type="search" name="q" value="<?= bio_h($q) ?>" placeholder="<?= bio_h($t('search_ph')) ?>" aria-label="<?= bio_h($t('search_label')) ?>" autocomplete="off" spellcheck="false">
    </form>
</header>

<?php if ($userId > 0 && !$ready && profile_is_staff()): ?>
    <p class="nx-note bh-install"><i class="fa-solid fa-database" aria-hidden="true"></i><?= bio_h($t('install')) ?></p>
<?php endif; ?>

<nav class="bh-ex-bar" aria-label="<?= bio_h($t('explore')) ?>">
    <div class="nx-tabs">
        <?php foreach ($views as $option): ?>
            <a class="nx-tab<?= $option === $view ? ' is-active' : '' ?>" href="<?= bio_h($link(['vista' => $option, 'pagina' => null])) ?>"<?= $option === $view ? ' aria-current="page"' : '' ?>><?= bio_h($t('tab_' . $option)) ?></a>
        <?php endforeach; ?>
    </div>
</nav>

<?php if ($cards): ?>
    <ul class="bh-cards bh-ex-grid">
        <?php foreach ($cards as $row) { bio_render_card($row, $staff, $lang); } ?>
    </ul>
    <?php if ($more): ?>
        <div class="bh-more-wrap"><a class="nx-btn nx-btn--outline" href="<?= bio_h($link(['pagina' => $page + 1])) ?>"><?= bio_h($t('more')) ?></a></div>
    <?php endif; ?>
<?php else: ?>
    <p class="bh-empty">
        <?php if ($q !== ''): ?>
            <?= bio_h($t('empty_search')) ?>
        <?php elseif ($view === 'vetrina'): ?>
            <?= bio_h($t('empty_vetrina')) ?>
            <?php if ($staff): ?><small><?= bio_h($t('empty_vetrina_staff')) ?></small><?php endif; ?>
        <?php elseif ($view === 'amici'): ?>
            <?= bio_h($t('empty_amici')) ?>
        <?php else: ?>
            <?= bio_h($t('empty_all')) ?>
        <?php endif; ?>
    </p>
<?php endif; ?>

<?php if ($userId > 0 && $ready): ?>
    <p class="bh-small bh-ex-note"><?= sprintf(bio_h($t('hide_note')), '<a href="/">' . bio_h($t('your_page')) . '</a>') ?></p>
<?php endif; ?>
<?php
bio_page_close($lang, ['csrf' => csrf_token(), 't' => ['failed' => $t('save_failed')]]);
