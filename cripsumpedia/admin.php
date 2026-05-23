<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

cp_require_admin(false);

$lang = cp_detect_lang();
$type = cp_normalize_type($_GET['type'] ?? null);
$query = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));
if (!in_array($status, ['all', 'draft', 'published', 'archived'], true)) $status = 'all';

$entries = cp_fetch_entries($mysqli, [
    'type' => $type,
    'q' => $query,
    'status' => $status,
    'order' => 'latest',
    'limit' => 80,
]);
$stats = cp_fetch_stats($mysqli);
$drafts = cp_count_entries($mysqli, ['status' => 'draft']);
$archived = cp_count_entries($mysqli, ['status' => 'archived']);
$title = cp_t('admin', $lang) . ' - Cripsumpedia';
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>">

<head>
    <?php cp_render_head($title, cp_t('subtitle', $lang), $lang); ?>
</head>

<body class="cp-body cp-page-admin">
    <?php cp_render_background(); ?>
    <?php cp_render_topbar($lang, 'admin'); ?>

    <main class="cp-admin-shell">
        <?php if (!cp_schema_ready($mysqli)): ?>
            <?php cp_render_install_notice($lang); ?>
        <?php else: ?>
            <aside class="cp-admin-side cp-reveal">
                <a class="cp-admin-brand" href="<?= cp_h(cp_url('admin', [], $lang)) ?>">
                    <i class="fa-solid fa-database"></i>
                    <span><strong>Cripsumpedia</strong><small>pannello admin</small></span>
                </a>
                <nav>
                    <a class="<?= $type === null ? 'is-active' : '' ?>" href="<?= cp_h(cp_url('admin', [], $lang)) ?>"><i class="fa-solid fa-layer-group"></i><span><?= cp_h(cp_t('all', $lang)) ?></span></a>
                    <a class="<?= $type === 'person' ? 'is-active' : '' ?>" href="<?= cp_h(cp_url('admin', ['type' => 'person'], $lang)) ?>"><i class="fa-solid fa-user-astronaut"></i><span><?= cp_h(cp_t('people', $lang)) ?></span></a>
                    <a class="<?= $type === 'event' ? 'is-active' : '' ?>" href="<?= cp_h(cp_url('admin', ['type' => 'event'], $lang)) ?>"><i class="fa-solid fa-timeline"></i><span><?= cp_h(cp_t('events', $lang)) ?></span></a>
                    <a class="<?= $type === 'meme' ? 'is-active' : '' ?>" href="<?= cp_h(cp_url('admin', ['type' => 'meme'], $lang)) ?>"><i class="fa-solid fa-face-grin-squint-tears"></i><span><?= cp_h(cp_t('memes', $lang)) ?></span></a>
                </nav>
                <a class="cp-btn cp-btn--primary" href="<?= cp_h(cp_url('editor', [], $lang)) ?>">
                    <i class="fa-solid fa-plus"></i>
                    <span><?= cp_h(cp_t('new_entry', $lang)) ?></span>
                </a>
            </aside>

            <section class="cp-admin-main">
                <header class="cp-admin-header cp-reveal">
                    <div>
                        <span class="cp-kicker"><i class="fa-solid fa-shield-halved"></i> Admin</span>
                        <h1><?= cp_h(cp_t('admin', $lang)) ?></h1>
                        <p><?= cp_h($lang === 'en' ? 'Manage entries, relations, tags, aliases and quotes.' : 'Gestione voci, relazioni, tag, alias e citazioni.') ?></p>
                    </div>
                    <a class="cp-btn cp-btn--primary" href="<?= cp_h(cp_url('editor', [], $lang)) ?>">
                        <i class="fa-solid fa-pen-nib"></i>
                        <span><?= cp_h(cp_t('new_entry', $lang)) ?></span>
                    </a>
                </header>

                <section class="cp-admin-stats cp-reveal">
                    <article><i class="fa-solid fa-user-astronaut"></i><strong><?= (int)$stats['person'] ?></strong><span><?= cp_h(cp_t('people', $lang)) ?></span></article>
                    <article><i class="fa-solid fa-timeline"></i><strong><?= (int)$stats['event'] ?></strong><span><?= cp_h(cp_t('events', $lang)) ?></span></article>
                    <article><i class="fa-solid fa-face-grin-squint-tears"></i><strong><?= (int)$stats['meme'] ?></strong><span><?= cp_h(cp_t('memes', $lang)) ?></span></article>
                    <article><i class="fa-solid fa-file-pen"></i><strong><?= (int)$drafts ?></strong><span><?= cp_h(cp_t('draft', $lang)) ?></span></article>
                    <article><i class="fa-solid fa-box-archive"></i><strong><?= (int)$archived ?></strong><span><?= cp_h(cp_t('archived', $lang)) ?></span></article>
                </section>

                <section class="cp-admin-panel cp-reveal">
                    <form class="cp-admin-toolbar" method="get" action="<?= cp_h(cp_url('admin', [], $lang)) ?>">
                        <label class="cp-admin-search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" name="q" value="<?= cp_h($query) ?>" placeholder="<?= cp_h(cp_t('search_placeholder', $lang)) ?>">
                        </label>
                        <select name="type">
                            <option value=""><?= cp_h(cp_t('all', $lang)) ?></option>
                            <option value="person" <?= $type === 'person' ? 'selected' : '' ?>><?= cp_h(cp_t('people', $lang)) ?></option>
                            <option value="event" <?= $type === 'event' ? 'selected' : '' ?>><?= cp_h(cp_t('events', $lang)) ?></option>
                            <option value="meme" <?= $type === 'meme' ? 'selected' : '' ?>><?= cp_h(cp_t('memes', $lang)) ?></option>
                        </select>
                        <select name="status">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>><?= cp_h(cp_t('all', $lang)) ?></option>
                            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>><?= cp_h(cp_t('published', $lang)) ?></option>
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>><?= cp_h(cp_t('draft', $lang)) ?></option>
                            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>><?= cp_h(cp_t('archived', $lang)) ?></option>
                        </select>
                        <button class="cp-btn cp-btn--small" type="submit"><i class="fa-solid fa-filter"></i><span><?= cp_h(cp_t('filters', $lang)) ?></span></button>
                    </form>

                    <?php if (!$entries): ?>
                        <div class="cp-empty"><?= cp_h(cp_t('empty', $lang)) ?></div>
                    <?php else: ?>
                        <div class="cp-admin-table-wrap">
                            <table class="cp-admin-table">
                                <thead>
                                    <tr>
                                        <th>Entry</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Score</th>
                                        <th>Updated</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($entries as $entry): ?>
                                        <?php $item = cp_entry_public($entry, $lang, $mysqli, false); ?>
                                        <tr data-entry-id="<?= (int)$entry['id'] ?>" style="--entry-accent: <?= cp_h($item['accent']) ?>">
                                            <td data-label="Entry">
                                                <a class="cp-admin-entry-cell" href="<?= cp_h($item['url']) ?>">
                                                    <img src="<?= cp_h($item['image']) ?>" alt="" loading="lazy" onerror="this.remove();">
                                                    <span>
                                                        <strong><?= cp_h($item['title']) ?></strong>
                                                        <small><?= cp_h($entry['slug']) ?></small>
                                                    </span>
                                                </a>
                                            </td>
                                            <td data-label="Type"><?= cp_h($item['type_label']) ?></td>
                                            <td data-label="Status"><span class="cp-status-pill cp-status-pill--<?= cp_h($entry['status']) ?>"><?= cp_h(cp_t($entry['status'], $lang)) ?></span></td>
                                            <td data-label="Score"><?= (int)$entry['importance'] ?>/100</td>
                                            <td data-label="Updated"><?= cp_h(cp_safe_date($entry['updated_at'] ?? '')) ?></td>
                                            <td data-label="Actions">
                                                <div class="cp-admin-actions">
                                                    <a class="cp-icon-btn" href="<?= cp_h(cp_url('editor', ['id' => (int)$entry['id']], $lang)) ?>" title="<?= cp_h(cp_t('editor', $lang)) ?>"><i class="fa-solid fa-pen"></i></a>
                                                    <button class="cp-icon-btn" type="button" data-cp-delete-entry="<?= (int)$entry['id'] ?>" title="<?= cp_h(cp_t('delete', $lang)) ?>"><i class="fa-solid fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </section>
        <?php endif; ?>
    </main>

    <?php cp_render_footer($lang); ?>
</body>

</html>