<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$type = cp_normalize_type($_GET['type'] ?? $_GET['category'] ?? null);
$slugOrId = trim((string)($_GET['id'] ?? $_GET['slug'] ?? ''));
$entry = $slugOrId !== '' ? cp_fetch_entry($mysqli, $type, $slugOrId, cp_is_admin_user()) : null;

if (cp_schema_ready($mysqli) && !$entry) {
    http_response_code(404);
}

if ($entry) {
    cp_record_view($mysqli, (int)$entry['id']);
}

$titleText = $entry ? cp_i18n($entry, 'title', $lang) : 'Cripsumpedia';
$description = $entry ? cp_excerpt(cp_i18n($entry, 'description', $lang), 160) : cp_t('subtitle', $lang);
$content = $entry ? cp_i18n($entry, 'content_md', $lang) : '';
$tags = $entry ? cp_fetch_tags($mysqli, (int)$entry['id']) : [];
$aliases = $entry ? cp_fetch_aliases($mysqli, (int)$entry['id']) : [];
$quotes = $entry ? cp_fetch_quotes($mysqli, (int)$entry['id'], 12) : [];
$rawRels = $entry ? cp_fetch_relations($mysqli, (int)$entry['id']) : [];
$_seenRelIds = [];
$relations = [];
foreach ($rawRels as $_r) {
    $_tid = (int)$_r['id'];
    if (!isset($_seenRelIds[$_tid])) {
        $_seenRelIds[$_tid] = true;
        $relations[] = $_r;
    }
}
unset($_seenRelIds, $_r, $_tid, $rawRels);
$groupedRelations = cp_group_relations_by_type($relations);
$public = $entry ? cp_entry_public($entry, $lang, $mysqli, false) : null;

$creatorName = '';
if ($entry && !empty($entry['created_by'])) {
    $stmt = $mysqli->prepare("SELECT display_name, username FROM utenti WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $entry['created_by']);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $creatorName = $userRow ? ($userRow['display_name'] ?: $userRow['username']) : '';
        $stmt->close();
    }
}

// Fetch automatic related pages
$relatedEntries = $entry ? cp_fetch_related_entries($mysqli, (int)$entry['id'], (string)$entry['entry_type'], 4) : [];

// Fetch adjacent entries (prev/next)
$adjacent = $entry ? cp_fetch_adjacent_entries($mysqli, (int)$entry['id'], (string)$entry['entry_type']) : ['prev' => null, 'next' => null];
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>">

<head>
    <?php cp_render_head($titleText . ' - Cripsumpedia', $description, $lang, 'cp-body', $entry['banner_url'] ?? null); ?>
</head>

<body class="cp-body cp-page-entry">
    <?php cp_render_background(); ?>
    <?php cp_render_topbar($lang, $entry['entry_type'] ?? 'home'); ?>

    <main class="cp-shell">
        <?php if (!cp_schema_ready($mysqli)): ?>
            <?php cp_render_install_notice($lang); ?>
        <?php elseif (!$entry || !$public): ?>
            <section class="cp-install">
                <i class="fa-solid fa-circle-question"></i>
                <h1>404</h1>
                <p><?= cp_h(cp_t('no_results', $lang)) ?></p>
                <a class="cp-btn cp-btn--primary" href="<?= cp_h(cp_url('home', [], $lang)) ?>"><?= cp_h(cp_t('back_home', $lang)) ?></a>
            </section>
        <?php else: ?>
            <?php cp_render_breadcrumbs($lang, [
                ['label' => cp_type_plural((string)$entry['entry_type'], $lang), 'url' => cp_url('category', ['type' => $entry['entry_type']], $lang)],
                ['label' => $titleText, 'url' => null],
            ]); ?>

            <section class="cp-entry-hero cp-reveal" style="--entry-accent: <?= cp_h($public['accent']) ?>">
                <div class="cp-entry-hero__media">
                    <img src="<?= cp_h(cp_asset_url($entry['banner_url'] ?: $entry['image_url'])) ?>" alt="<?= cp_h($titleText) ?>" loading="eager" class="img-cripsumpedias" onerror="this.parentElement.classList.add('is-broken'); this.remove();">
                </div>
                <div class="cp-entry-hero__content">
                    <span class="cp-kicker"><i class="fa-solid <?= cp_h(cp_type_icon((string)$entry['entry_type'])) ?>"></i> <?= cp_h(cp_type_label((string)$entry['entry_type'], $lang)) ?></span>
                    <h1><?= cp_h($titleText) ?></h1>
                    <?php if (cp_i18n($entry, 'subtitle', $lang) !== ''): ?>
                        <p><?= cp_h(cp_i18n($entry, 'subtitle', $lang)) ?></p>
                    <?php else: ?>
                        <p><?= cp_h(cp_i18n($entry, 'description', $lang)) ?></p>
                    <?php endif; ?>
                    <div class="cp-hero-badges">
                        <span class="cp-badge cp-badge--status"><?= cp_h(cp_status_label($entry['canonical_status'] ?? 'canon', $lang)) ?></span>
                        <span class="cp-badge cp-badge--rarity cp-badge--<?= cp_h($entry['rarity'] ?? 'common') ?>"><?= cp_h($entry['rarity'] ?? 'common') ?></span>
                        <span class="cp-badge cp-badge--views"><i class="fa-solid fa-eye"></i> <?= (int)($entry['views_count'] ?? 0) ?></span>
                    </div>
                    <div class="cp-entry-actions">
                        <button class="cp-btn cp-btn--primary" type="button" data-cp-share>
                            <i class="fa-solid fa-share-nodes"></i>
                            <span><?= cp_h(cp_t('share', $lang)) ?></span>
                        </button>
                        <button class="cp-btn" type="button" data-cp-favorite data-entry-id="<?= (int)$entry['id'] ?>">
                            <i class="fa-solid fa-star"></i>
                            <span><?= cp_h(cp_t('favorite', $lang)) ?></span>
                        </button>
                        <button class="cp-btn" type="button" data-cp-reaction data-entry-id="<?= (int)$entry['id'] ?>" data-reaction="hype">
                            <i class="fa-solid fa-bolt"></i>
                            <span><?= cp_h(cp_t('reaction', $lang)) ?></span>
                        </button>
                        <button class="cp-icon-btn" type="button" data-cp-focus title="<?= cp_h(cp_t('focus', $lang)) ?>" aria-label="<?= cp_h(cp_t('focus', $lang)) ?>">
                            <i class="fa-solid fa-book-open-reader"></i>
                        </button>
                        <?php if (cp_is_admin_user()): ?>
                            <a class="cp-icon-btn" href="<?= cp_h(cp_url('editor', ['id' => (int)$entry['id']], $lang)) ?>" title="<?= cp_h(cp_t('editor', $lang)) ?>">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <div class="cp-entry-layout">
                <div class="cp-entry-main">
                    <article class="cp-entry-content cp-reveal" data-cp-readable>
                        <?php if (cp_i18n($entry, 'description', $lang) !== ''): ?>
                            <p class="cp-entry-lead"><?= cp_h(cp_i18n($entry, 'description', $lang)) ?></p>
                        <?php endif; ?>

                        <div class="cp-markdown">
                            <?= cp_markdown_to_html($content, $mysqli, $lang, (int)$entry['id']) ?>
                        </div>

                        <?php if ($quotes): ?>
                            <section class="cp-content-block">
                                <h2><?= cp_h(cp_t('quotes', $lang)) ?></h2>
                                <div class="cp-quote-grid">
                                    <?php foreach ($quotes as $quote): ?>
                                        <blockquote>
                                            <?= cp_h(cp_i18n($quote, 'quote_text', $lang)) ?>
                                            <?php if (cp_i18n($quote, 'speaker', $lang) !== ''): ?>
                                                <cite><?= cp_h(cp_i18n($quote, 'speaker', $lang)) ?></cite>
                                            <?php endif; ?>
                                        </blockquote>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Dedicated Connections (Collegamenti) Section -->
                        <?php
                        $hasRels = !empty($groupedRelations['person']) || !empty($groupedRelations['event']) || !empty($groupedRelations['meme']);
                        if ($hasRels):
                        ?>
                            <section class="cp-content-block cp-connections-section">
                                <h2><i class="fa-solid fa-circle-nodes"></i> <?= cp_h($lang === 'en' ? 'Links' : 'Collegamenti') ?></h2>
                                <div class="cp-connections-container">
                                    <?php foreach (['person' => 'related_people', 'event' => 'related_events', 'meme' => 'related_memes'] as $gType => $tKey): ?>
                                        <?php if (!empty($groupedRelations[$gType])): ?>
                                            <div class="cp-connections-group">
                                                <h3><?= cp_h(cp_t($tKey, $lang)) ?></h3>
                                                <div class="cp-relation-grid">
                                                    <?php foreach ($groupedRelations[$gType] as $relation): ?>
                                                        <?php $rel = cp_entry_public($relation, $lang, $mysqli, false); ?>
                                                        <a class="cp-relation-card" href="<?= cp_h($rel['url']) ?>" style="--entry-accent: <?= cp_h($rel['accent']) ?>">
                                                            <span class="cp-relation-card__media">
                                                                <img src="<?= cp_h($rel['image']) ?>" alt="<?= cp_h($rel['title']) ?>" loading="lazy" onerror="this.parentElement.classList.add('is-broken'); this.remove();">
                                                                <i class="fa-solid <?= cp_h(cp_type_icon($rel['type'])) ?>"></i>
                                                            </span>
                                                            <div class="cp-relation-card__info">
                                                                <strong><?= cp_h($rel['title']) ?></strong>
                                                                <small><?= cp_h(cp_i18n($relation, 'relation_label', $lang) ?: $relation['relation_type']) ?></small>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Automatic Related Pages Section -->
                        <?php if ($relatedEntries): ?>
                            <section class="cp-content-block cp-related-section">
                                <h2><i class="fa-solid fa-wand-magic-sparkles"></i> <?= cp_h($lang === 'en' ? 'Related' : 'Correlate') ?></h2>
                                <div class="cp-card-grid cp-card-grid--compact">
                                    <?php foreach ($relatedEntries as $rEntry): ?>
                                        <?php cp_render_entry_card($mysqli, $rEntry, $lang); ?>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                    </article>

                    <!-- Previous / Next Navigation Footer -->
                    <nav class="cp-entry-navigation cp-reveal" aria-label="<?= cp_h($lang === 'en' ? 'Page navigation' : 'Navigazione') ?>">
                        <div class="cp-entry-navigation__prev">
                            <?php if ($adjacent['prev']): ?>
                                <?php $prevItem = cp_entry_public($adjacent['prev'], $lang, $mysqli, false); ?>
                                <a href="<?= cp_h($prevItem['url']) ?>">
                                    <small><i class="fa-solid fa-arrow-left"></i> <?= cp_h($lang === 'en' ? 'Previous' : 'Precedente') ?></small>
                                    <strong><?= cp_h($prevItem['title']) ?></strong>
                                </a>
                            <?php endif; ?>
                        </div>
                        <button class="cp-btn cp-btn--ghost" type="button" data-cp-random title="<?= cp_h(cp_t('random', $lang)) ?>">
                            <i class="fa-solid fa-shuffle"></i>
                            <span><?= cp_h(cp_t('random', $lang)) ?></span>
                        </button>
                        <div class="cp-entry-navigation__next">
                            <?php if ($adjacent['next']): ?>
                                <?php $nextItem = cp_entry_public($adjacent['next'], $lang, $mysqli, false); ?>
                                <a href="<?= cp_h($nextItem['url']) ?>">
                                    <small><?= cp_h($lang === 'en' ? 'Next' : 'Successivo') ?> <i class="fa-solid fa-arrow-right"></i></small>
                                    <strong><?= cp_h($nextItem['title']) ?></strong>
                                </a>
                            <?php endif; ?>
                        </div>
                    </nav>
                </div>

                <aside class="cp-entry-sidebar cp-reveal">
                    <!-- Premium Wiki Infobox -->
                    <section class="cp-infobox" style="--entry-accent: <?= cp_h($public['accent']) ?>">
                        <div class="cp-infobox__header">
                            <h2><?= cp_h($titleText) ?></h2>
                            <?php if (cp_i18n($entry, 'subtitle', $lang) !== ''): ?>
                                <small><?= cp_h(cp_i18n($entry, 'subtitle', $lang)) ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="cp-infobox__media">
                            <img src="<?= cp_h($public['image']) ?>" alt="<?= cp_h($titleText) ?>" onerror="this.parentElement.classList.add('is-broken'); this.remove();">
                            <span class="cp-infobox__fallback"><i class="fa-solid <?= cp_h(cp_type_icon((string)$entry['entry_type'])) ?>"></i></span>
                        </div>

                        <div class="cp-infobox__badges">
                            <span class="cp-badge cp-badge--rarity cp-badge--<?= cp_h($entry['rarity'] ?? 'common') ?>"><?= cp_h($entry['rarity'] ?? 'common') ?></span>
                            <span class="cp-badge cp-badge--status cp-badge--<?= cp_h($entry['canonical_status'] ?? 'canon') ?>"><?= cp_h(cp_status_label($entry['canonical_status'] ?? 'canon', $lang)) ?></span>
                        </div>

                        <table class="cp-infobox__table">
                            <tbody>
                                <tr>
                                    <th><?= cp_h($lang === 'en' ? 'Category' : 'Categoria') ?></th>
                                    <td>
                                        <span class="cp-infobox-cat-badge">
                                            <i class="fa-solid <?= cp_h(cp_type_icon((string)$entry['entry_type'])) ?>"></i>
                                            <?= cp_h(cp_type_label((string)$entry['entry_type'], $lang)) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php if (!empty($entry['lore_date'])): ?>
                                    <tr>
                                        <th><?= cp_h(cp_t('lore_date', $lang)) ?></th>
                                        <td><?= cp_h($entry['lore_date']) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($entry['real_date'])): ?>
                                    <tr>
                                        <th><?= cp_h(cp_t('real_date', $lang)) ?></th>
                                        <td><?= cp_h(cp_safe_date($entry['real_date'])) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($creatorName !== ''): ?>
                                    <tr>
                                        <th><?= cp_h($lang === 'en' ? 'Author' : 'Autore') ?></th>
                                        <td><?= cp_h($creatorName) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <th><?= cp_h($lang === 'en' ? 'Last edit' : 'Ultima modifica') ?></th>
                                    <td><?= cp_h(cp_safe_date($entry['updated_at'] ?? '')) ?></td>
                                </tr>
                                <tr>
                                    <th><?= cp_h($lang === 'en' ? 'Views' : 'Visite') ?></th>
                                    <td><?= (int)($entry['views_count'] ?? 0) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <?php if ($tags): ?>
                        <section class="cp-sidebar-card">
                            <h2><?= cp_h(cp_t('tag', $lang)) ?></h2>
                            <div class="cp-tag-row cp-tag-row--sidebar">
                                <?php foreach ($tags as $tagRow): ?>
                                    <a href="<?= cp_h(cp_url('category', ['type' => $entry['entry_type'], 'tag' => $tagRow['slug']], $lang)) ?>" style="--tag-color: <?= cp_h(cp_valid_color($tagRow['color'] ?? null, '#7dd3fc')) ?>">
                                        <?= cp_h(cp_i18n($tagRow, 'name', $lang)) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($aliases): ?>
                        <section class="cp-sidebar-card">
                            <h2><?= cp_h(cp_t('aliases', $lang)) ?></h2>
                            <div class="cp-chip-list">
                                <?php foreach ($aliases as $alias): ?>
                                    <?php $label = cp_i18n($alias, 'alias', $lang); ?>
                                    <?php if ($label !== ''): ?><span><?= cp_h($label) ?></span><?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </aside>
            </div>
        <?php endif; ?>
    </main>

    <?php cp_render_footer($lang); ?>
</body>

</html>
