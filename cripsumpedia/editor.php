<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

cp_require_admin(false);

$lang = cp_detect_lang();
$entryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$entry = $entryId > 0 ? cp_fetch_entry($mysqli, null, (string)$entryId, true) : null;

if ($entryId > 0 && cp_schema_ready($mysqli) && !$entry) {
    http_response_code(404);
}

$tags = $entry ? cp_fetch_tags($mysqli, (int)$entry['id']) : [];
$aliases = $entry ? cp_fetch_aliases($mysqli, (int)$entry['id']) : [];
$quotes = $entry ? cp_fetch_quotes($mysqli, (int)$entry['id'], 30) : [];
$relations = $entry ? cp_fetch_relations($mysqli, (int)$entry['id'], null, 120) : [];

// Deduplicate: bidirectional relations may appear in both directions
$seenRelIds = [];
$uniqueRelations = [];
foreach ($relations as $rel) {
    $tid = (int)$rel['id'];
    if (!isset($seenRelIds[$tid])) {
        $seenRelIds[$tid] = true;
        $uniqueRelations[] = $rel;
    }
}
$relations = $uniqueRelations;

$tagIt = implode(', ', array_map(static fn(array $row): string => cp_i18n($row, 'name', 'it'), $tags));
$tagEn = implode(', ', array_map(static fn(array $row): string => cp_i18n($row, 'name', 'en'), $tags));
$aliasIt = implode("\n", array_map(static fn(array $row): string => cp_i18n($row, 'alias', 'it'), $aliases));
$aliasEn = implode("\n", array_map(static fn(array $row): string => cp_i18n($row, 'alias', 'en'), $aliases));
$quoteIt = implode("\n", array_map(static fn(array $row): string => cp_i18n($row, 'quote_text', 'it'), $quotes));
$quoteEn = implode("\n", array_map(static fn(array $row): string => cp_i18n($row, 'quote_text', 'en'), $quotes));
$relationSeed = [];
foreach ($relations as $relation) {
    $item = cp_entry_public($relation, $lang, $mysqli, false);
    $relationSeed[] = [
        'target_id' => (int)$relation['id'],
        'title' => $item['title'],
        'type' => $item['type'],
        'relation_type' => $relation['relation_type'] ?? 'related',
        'relation_label' => cp_i18n($relation, 'relation_label', $lang),
        'weight' => (int)($relation['weight'] ?? 50),
    ];
}

$defaults = [
    'id' => 0,
    'entry_type' => 'person',
    'status' => 'draft',
    'title' => '',
    'title_en' => '',
    'slug' => '',
    'subtitle' => '',
    'subtitle_en' => '',
    'description' => '',
    'description_en' => '',
    'content_md' => '',
    'content_md_en' => '',
    'image_url' => '',
    'banner_url' => '',
    'accent_color' => '#42f5b0',
    'canonical_status' => 'canon',
    'rarity' => 'common',
    'lore_date' => '',
    'real_date' => '',
    'importance' => 50,
    'featured' => 0,
    'seo_title' => '',
    'seo_title_en' => '',
    'seo_description' => '',
    'seo_description_en' => '',
];
$form = array_merge($defaults, $entry ?: []);
$pageTitle = ($entry ? cp_i18n($entry, 'title', $lang) : cp_t('new_entry', $lang)) . ' - Cripsumpedia';
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>">

<head>
    <?php cp_render_head($pageTitle, cp_t('subtitle', $lang), $lang); ?>
</head>

<body class="cp-body cp-page-editor">
    <?php cp_render_background(); ?>
    <?php cp_render_topbar($lang, 'admin'); ?>

    <main class="cp-editor-shell">
        <?php if (!cp_schema_ready($mysqli)): ?>
            <?php cp_render_install_notice($lang); ?>
        <?php elseif ($entryId > 0 && !$entry): ?>
            <section class="cp-install">
                <i class="fa-solid fa-circle-question"></i>
                <h1>404</h1>
                <p><?= cp_h(cp_t('no_results', $lang)) ?></p>
            </section>
        <?php else: ?>
            <?php cp_render_breadcrumbs($lang, [
                ['label' => cp_t('admin', $lang), 'url' => cp_url('admin', [], $lang)],
                ['label' => $entry ? cp_i18n($entry, 'title', $lang) : cp_t('new_entry', $lang), 'url' => null],
            ]); ?>

            <form class="cp-editor" data-cp-editor-form>
                <input type="hidden" name="action" value="save_entry">
                <input type="hidden" name="csrf_token" value="<?= cp_h(cp_csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
                <input type="hidden" name="relations_json" data-cp-relations-json value="<?= cp_h(json_encode($relationSeed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">

                <header class="cp-editor-head cp-reveal">
                    <div>
                        <span class="cp-kicker"><i class="fa-solid fa-pen-nib"></i> <?= cp_h(cp_t('editor', $lang)) ?></span>
                        <h1><?= cp_h($entry ? cp_i18n($entry, 'title', $lang) : cp_t('new_entry', $lang)) ?></h1>
                        <p>Markdown, preview live, upload immagini, link lore automatici e relazioni bidirezionali.</p>
                    </div>
                    <div class="cp-editor-actions">
                        <?php if ($entry): ?>
                            <a class="cp-btn" href="<?= cp_h(cp_entry_url($entry, $lang)) ?>"><i class="fa-solid fa-eye"></i><span>View</span></a>
                        <?php endif; ?>
                        <button class="cp-btn cp-btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i><span><?= cp_h(cp_t('save', $lang)) ?></span></button>
                    </div>
                </header>

                <div class="cp-editor-grid">
                    <section class="cp-editor-panel cp-reveal">
                        <div class="cp-editor-tabs" role="tablist">
                            <button type="button" class="is-active" data-cp-editor-tab="it"><?= cp_h(cp_t('language_it', $lang)) ?></button>
                            <button type="button" data-cp-editor-tab="en"><?= cp_h(cp_t('language_en', $lang)) ?></button>
                            <button type="button" data-cp-editor-tab="meta"><?= cp_h(cp_t('metadata', $lang)) ?></button>
                            <button type="button" data-cp-editor-tab="relations"><?= cp_h(cp_t('relations', $lang)) ?></button>
                        </div>

                        <div class="cp-editor-tab is-active" data-cp-editor-pane="it">
                            <label class="cp-field"><span>Titolo IT</span><input name="title" value="<?= cp_h($form['title']) ?>" required data-cp-title-source></label>
                            <label class="cp-field"><span>Slug</span><input name="slug" value="<?= cp_h($form['slug']) ?>" placeholder="slug-voce"></label>
                            <label class="cp-field"><span>Sottotitolo IT</span><input name="subtitle" value="<?= cp_h($form['subtitle']) ?>"></label>
                            <label class="cp-field"><span>Descrizione IT</span><textarea name="description" rows="4"><?= cp_h($form['description']) ?></textarea></label>
                            <label class="cp-field cp-field--wide"><span>Contenuto markdown IT</span><textarea name="content_md" rows="18" data-cp-markdown-source><?= cp_h($form['content_md']) ?></textarea></label>
                        </div>

                        <div class="cp-editor-tab" data-cp-editor-pane="en">
                            <label class="cp-field"><span>Title EN</span><input name="title_en" value="<?= cp_h($form['title_en']) ?>"></label>
                            <label class="cp-field"><span>Subtitle EN</span><input name="subtitle_en" value="<?= cp_h($form['subtitle_en']) ?>"></label>
                            <label class="cp-field"><span>Description EN</span><textarea name="description_en" rows="4"><?= cp_h($form['description_en']) ?></textarea></label>
                            <label class="cp-field cp-field--wide"><span>Markdown content EN</span><textarea name="content_md_en" rows="18"><?= cp_h($form['content_md_en']) ?></textarea></label>
                        </div>

                        <div class="cp-editor-tab" data-cp-editor-pane="meta">
                            <div class="cp-field-grid">
                                <label class="cp-field"><span>Tipo</span>
                                    <select name="entry_type">
                                        <option value="person" <?= $form['entry_type'] === 'person' ? 'selected' : '' ?>><?= cp_h(cp_t('people', $lang)) ?></option>
                                        <option value="event" <?= $form['entry_type'] === 'event' ? 'selected' : '' ?>><?= cp_h(cp_t('events', $lang)) ?></option>
                                        <option value="meme" <?= $form['entry_type'] === 'meme' ? 'selected' : '' ?>><?= cp_h(cp_t('memes', $lang)) ?></option>
                                    </select>
                                </label>
                                <label class="cp-field"><span>Status</span>
                                    <select name="status">
                                        <option value="draft" <?= $form['status'] === 'draft' ? 'selected' : '' ?>><?= cp_h(cp_t('draft', $lang)) ?></option>
                                        <option value="published" <?= $form['status'] === 'published' ? 'selected' : '' ?>><?= cp_h(cp_t('published', $lang)) ?></option>
                                        <option value="archived" <?= $form['status'] === 'archived' ? 'selected' : '' ?>><?= cp_h(cp_t('archived', $lang)) ?></option>
                                    </select>
                                </label>
                                <label class="cp-field"><span>Canon</span>
                                    <select name="canonical_status">
                                        <option value="canon" <?= $form['canonical_status'] === 'canon' ? 'selected' : '' ?>><?= cp_h(cp_t('canon', $lang)) ?></option>
                                        <option value="non_canon" <?= $form['canonical_status'] === 'non_canon' ? 'selected' : '' ?>><?= cp_h(cp_t('non_canon', $lang)) ?></option>
                                        <option value="disputed" <?= $form['canonical_status'] === 'disputed' ? 'selected' : '' ?>><?= cp_h(cp_t('disputed', $lang)) ?></option>
                                    </select>
                                </label>
                                <label class="cp-field"><span>Rarity</span>
                                    <select name="rarity">
                                        <?php foreach (['common', 'rare', 'epic', 'legendary', 'mythic', 'cursed'] as $rarity): ?>
                                            <option value="<?= cp_h($rarity) ?>" <?= $form['rarity'] === $rarity ? 'selected' : '' ?>><?= cp_h($rarity) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="cp-field"><span>Accent</span><input type="color" name="accent_color" value="<?= cp_h(cp_valid_color($form['accent_color'])) ?>"></label>
                                <label class="cp-field"><span>Importanza</span><input type="number" min="0" max="100" name="importance" value="<?= (int)$form['importance'] ?>"></label>
                                <label class="cp-field"><span>Data lore</span><input name="lore_date" value="<?= cp_h($form['lore_date']) ?>"></label>
                                <label class="cp-field"><span>Data reale</span><input type="date" name="real_date" value="<?= cp_h($form['real_date']) ?>"></label>
                                <label class="cp-field cp-field--wide"><span>Immagine</span><input name="image_url" value="<?= cp_h($form['image_url']) ?>" placeholder="/img/cripsumpedia/name.jpg"></label>
                                <label class="cp-field cp-field--wide"><span>Banner</span><input name="banner_url" value="<?= cp_h($form['banner_url']) ?>" placeholder="/img/cripsumpedia/banner.jpg"></label>
                                <label class="cp-field cp-check"><input type="checkbox" name="featured" value="1" <?= (int)$form['featured'] === 1 ? 'checked' : '' ?>><span>Featured/trending candidate</span></label>
                            </div>

                            <div class="cp-upload-zone" data-cp-upload-zone>
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <strong>Drag and drop immagini</strong>
                                <span>JPG, PNG, GIF, WebP fino a 6MB. L'URL viene copiato negli appunti.</span>
                                <input type="file" accept="image/*" multiple data-cp-upload-input>
                            </div>

                            <div class="cp-field-grid">
                                <label class="cp-field cp-field--wide"><span>Tag IT</span><input name="tags" value="<?= cp_h($tagIt) ?>" placeholder="lore principale, guerre, cursed lore"></label>
                                <label class="cp-field cp-field--wide"><span>Tags EN</span><input name="tags_en" value="<?= cp_h($tagEn) ?>" placeholder="main lore, wars, cursed lore"></label>
                                <label class="cp-field"><span>SEO title IT</span><input name="seo_title" value="<?= cp_h($form['seo_title']) ?>"></label>
                                <label class="cp-field"><span>SEO title EN</span><input name="seo_title_en" value="<?= cp_h($form['seo_title_en']) ?>"></label>
                                <label class="cp-field"><span>SEO description IT</span><textarea name="seo_description" rows="3"><?= cp_h($form['seo_description']) ?></textarea></label>
                                <label class="cp-field"><span>SEO description EN</span><textarea name="seo_description_en" rows="3"><?= cp_h($form['seo_description_en']) ?></textarea></label>
                                <label class="cp-field"><span>Alias IT</span><textarea name="aliases" rows="6" placeholder="Uno per riga"><?= cp_h($aliasIt) ?></textarea></label>
                                <label class="cp-field"><span>Aliases EN</span><textarea name="aliases_en" rows="6" placeholder="One per line"><?= cp_h($aliasEn) ?></textarea></label>
                                <label class="cp-field"><span>Citazioni IT</span><textarea name="quotes" rows="6" placeholder="Una per riga"><?= cp_h($quoteIt) ?></textarea></label>
                                <label class="cp-field"><span>Quotes EN</span><textarea name="quotes_en" rows="6" placeholder="One per line"><?= cp_h($quoteEn) ?></textarea></label>
                            </div>
                        </div>

                        <div class="cp-editor-tab" data-cp-editor-pane="relations">
                            <div class="cp-relation-builder" data-cp-relation-builder>
                                <label class="cp-field">
                                    <span>Cerca voce da collegare</span>
                                    <input type="search" data-cp-relation-search placeholder="<?= cp_h(cp_t('search_placeholder', $lang)) ?>">
                                </label>
                                <div class="cp-relation-results" data-cp-relation-results hidden></div>
                                <div class="cp-relation-selected" data-cp-relation-selected></div>
                            </div>
                        </div>
                    </section>

                    <aside class="cp-editor-preview cp-reveal">
                        <div class="cp-preview-toolbar">
                            <strong><?= cp_h(cp_t('preview', $lang)) ?></strong>
                            <button class="cp-icon-btn" type="button" data-cp-refresh-preview title="<?= cp_h(cp_t('preview', $lang)) ?>"><i class="fa-solid fa-rotate"></i></button>
                        </div>
                        <div class="cp-preview-card" data-cp-editor-preview>
                            <h2><?= cp_h($form['title'] ?: cp_t('new_entry', $lang)) ?></h2>
                            <p><?= cp_h($form['description'] ?: 'Preview markdown live.') ?></p>
                        </div>
                        <div class="cp-markdown cp-preview-markdown" data-cp-markdown-preview></div>
                        <div class="cp-editor-help">
                            <strong>Markdown support</strong>
                            <span># headings, **bold**, *italic*, quote, liste, immagini, YouTube, [spoiler:title]testo[/spoiler], [timeline]date|title|text[/timeline]</span>
                        </div>
                    </aside>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <?php cp_render_footer($lang); ?>
    <script>
        window.CripsumpediaEditorRelations = <?= json_encode($relationSeed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
</body>

</html>