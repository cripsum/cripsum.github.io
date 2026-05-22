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
$relations = $entry ? cp_fetch_relations($mysqli, (int)$entry['id']) : [];
$groupedRelations = cp_group_relations_by_type($relations);
$public = $entry ? cp_entry_public($entry, $lang, $mysqli, false) : null;
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
                <img src="<?= cp_h(cp_asset_url($entry['banner_url'] ?: $entry['image_url'])) ?>" alt="<?= cp_h($titleText) ?>" loading="eager" onerror="this.parentElement.classList.add('is-broken'); this.remove();">
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
                    <span><?= cp_h(cp_status_label($entry['canonical_status'] ?? 'canon', $lang)) ?></span>
                    <span><?= cp_h($entry['rarity'] ?? 'common') ?></span>
                    <span><?= (int)($entry['views_count'] ?? 0) ?> <?= cp_h(cp_t('views', $lang)) ?></span>
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

                <?php if ($relations): ?>
                    <section class="cp-content-block">
                        <h2><?= cp_h(cp_t('relations', $lang)) ?></h2>
                        <div class="cp-relation-grid">
                            <?php foreach ($relations as $relation): ?>
                                <?php $rel = cp_entry_public($relation, $lang, $mysqli, false); ?>
                                <a class="cp-relation-card" href="<?= cp_h($rel['url']) ?>" style="--entry-accent: <?= cp_h($rel['accent']) ?>">
                                    <i class="fa-solid <?= cp_h(cp_type_icon($rel['type'])) ?>"></i>
                                    <span><?= cp_h($rel['title']) ?></span>
                                    <small><?= cp_h(cp_i18n($relation, 'relation_label', $lang) ?: $relation['relation_type']) ?></small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </article>

            <aside class="cp-entry-sidebar cp-reveal">
                <section class="cp-sidebar-card">
                    <h2><?= cp_h(cp_t('quick_info', $lang)) ?></h2>
                    <dl>
                        <div><dt><?= cp_h(cp_t('status', $lang)) ?></dt><dd><?= cp_h(cp_status_label($entry['canonical_status'] ?? 'canon', $lang)) ?></dd></div>
                        <div><dt><?= cp_h(cp_t('importance', $lang)) ?></dt><dd><?= (int)($entry['importance'] ?? 0) ?>/100</dd></div>
                        <?php if (!empty($entry['lore_date'])): ?>
                            <div><dt><?= cp_h(cp_t('lore_date', $lang)) ?></dt><dd><?= cp_h($entry['lore_date']) ?></dd></div>
                        <?php endif; ?>
                        <?php if (!empty($entry['real_date'])): ?>
                            <div><dt><?= cp_h(cp_t('real_date', $lang)) ?></dt><dd><?= cp_h(cp_safe_date($entry['real_date'])) ?></dd></div>
                        <?php endif; ?>
                        <div><dt><?= cp_h(cp_t('created', $lang)) ?></dt><dd><?= cp_h(cp_safe_date($entry['created_at'] ?? '')) ?></dd></div>
                        <div><dt><?= cp_h(cp_t('updated', $lang)) ?></dt><dd><?= cp_h(cp_safe_date($entry['updated_at'] ?? '')) ?></dd></div>
                    </dl>
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

                <?php
                $sidebarGroups = [
                    'person' => cp_t('related_people', $lang),
                    'event' => cp_t('related_events', $lang),
                    'meme' => cp_t('related_memes', $lang),
                ];
                foreach ($sidebarGroups as $groupType => $label):
                    if (empty($groupedRelations[$groupType])) continue;
                ?>
                    <section class="cp-sidebar-card">
                        <h2><?= cp_h($label) ?></h2>
                        <div class="cp-side-relations">
                            <?php foreach (array_slice($groupedRelations[$groupType], 0, 8) as $relation): ?>
                                <?php $rel = cp_entry_public($relation, $lang, $mysqli, false); ?>
                                <a href="<?= cp_h($rel['url']) ?>" style="--entry-accent: <?= cp_h($rel['accent']) ?>">
                                    <i class="fa-solid <?= cp_h(cp_type_icon($groupType)) ?>"></i>
                                    <span><?= cp_h($rel['title']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </aside>
        </div>
    <?php endif; ?>
</main>

<?php cp_render_footer($lang); ?>
</body>
</html>
