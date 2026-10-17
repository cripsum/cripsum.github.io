<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$type = cp_normalize_type($_GET['type'] ?? $_GET['category'] ?? null);
$slugOrId = trim((string)($_GET['id'] ?? $_GET['slug'] ?? ''));
$entry = $slugOrId !== '' ? cp_fetch_entry($mysqli, $type, $slugOrId, cp_is_admin_user()) : null;

// Una voce che ha cambiato titolo: l'indirizzo vecchio porta a quello nuovo.
if (!$entry && $slugOrId !== '' && !ctype_digit($slugOrId)) {
    $moved = cp_find_slug_redirect($mysqli, $type, $slugOrId);
    if ($moved) {
        header('Location: ' . cp_entry_url($moved, $lang), true, 301);
        exit;
    }
}

if (cp_schema_ready($mysqli) && !$entry) {
    http_response_code(404);
}

if ($entry) {
    cp_record_view($mysqli, (int)$entry['id']);
}

$entryType = (string)($entry['entry_type'] ?? '');
$titleText = $entry ? cp_i18n($entry, 'title', $lang) : 'Cripsumpedia';
$metaDescription = $entry ? cp_excerpt(cp_entry_line($entry, $lang, 300) ?: cp_i18n($entry, 'description', $lang), 160) : cp_t('subtitle', $lang);

if ($entry) {
    $entryId = (int)$entry['id'];
    $tags = cp_fetch_tags($mysqli, $entryId);
    $aliases = array_values(array_filter(array_map(static fn(array $row): string => cp_i18n($row, 'alias', $lang), cp_fetch_aliases($mysqli, $entryId))));
    $quotes = cp_fetch_quotes($mysqli, $entryId, 12);
    $relations = cp_fetch_relations($mysqli, $entryId);
    $mentionedIn = cp_mentioned_in($mysqli, $entryId);
    $authorship = cp_entry_authorship($mysqli, $entry);
    $profile = $entryType === 'person' ? cp_entry_profile($mysqli, $entry) : null;
    $people = $entryType === 'event' ? array_values(array_filter($relations, static fn(array $r): bool => ($r['entry_type'] ?? '') === 'person')) : [];
    $image = cp_entry_image($entry);

    // «In breve» sotto il titolo; la descrizione diventa il primo paragrafo
    // solo se dice qualcosa in più e il testo non comincia già così.
    $subtitle = cp_i18n($entry, 'subtitle', $lang);
    $descriptionText = cp_i18n($entry, 'description', $lang);
    $lead = $subtitle !== '' ? $subtitle : (mb_strlen($descriptionText, 'UTF-8') <= 160 ? $descriptionText : '');
    $contentHtml = cp_entry_content_html($mysqli, $entry, $lang);
    $flat = static fn(string $text): string => mb_strtolower(trim((string)preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 'UTF-8');
    $intro = $descriptionText !== '' && $descriptionText !== $lead && !str_starts_with($flat($contentHtml), $flat($descriptionText))
        ? $descriptionText : '';
    $toc = cp_toc($contentHtml);

    $note = match (true) {
        ($entry['status'] ?? '') === 'draft' => cp_ui('note_draft', $lang),
        ($entry['status'] ?? '') === 'archived' => cp_ui('note_archived', $lang),
        ($entry['canonical_status'] ?? '') === 'non_canon' => cp_ui('note_non_canon', $lang),
        ($entry['canonical_status'] ?? '') === 'disputed' => cp_ui('note_disputed', $lang),
        default => '',
    };

    // I fatti della colonna di destra cambiano col tipo di voce.
    $facts = [];
    if ($aliases) $facts[] = [cp_ui('known_as', $lang), implode(', ', $aliases), null];
    if ($profile) $facts[] = [cp_ui('profile', $lang), '@' . $profile['username'], ['url' => '/u/' . rawurlencode((string)$profile['username']), 'avatar' => cp_avatar_url((int)$profile['id'], 64)]];
    $realDate = cp_format_date($entry['real_date'] ?? null, $lang, 'long');
    if ($realDate !== '') {
        $facts[] = [cp_ui(['person' => 'since_person', 'event' => 'when', 'meme' => 'since_meme'][$entryType] ?? 'when', $lang), $realDate, null];
    }
    $loreDate = trim((string)($entry['lore_date'] ?? ''));
    if ($loreDate !== '') $facts[] = [cp_ui('lore_date', $lang), $loreDate, null];
    if ($people) $facts[] = [cp_ui('who_was_there', $lang), implode(', ', array_map(static fn(array $p): string => cp_i18n($p, 'title', $lang), $people)), null];
}
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php cp_render_head($titleText . ' - Cripsumpedia', $metaDescription, $lang, 'cp-body', $entry ? (($entry['image_url'] ?? '') ?: ($entry['banner_url'] ?? null)) : null); ?>
</head>

<body class="cp-body cp-page-entry">
    <?php cp_render_topbar($lang, $entryType ?: 'home'); ?>

    <main class="cp-main">
        <?php if (!cp_schema_ready($mysqli)): ?>
            <?php cp_render_install_notice($lang); ?>
        <?php elseif (!$entry): ?>
            <?php cp_render_bar($lang, (string)$type); ?>
            <section class="cp-notfound">
                <h1><?= cp_h(cp_ui('not_found_title', $lang)) ?></h1>
                <p><?= cp_h(cp_ui('not_found_text', $lang)) ?></p>
                <div class="cp-notfound__actions">
                    <a class="cp-btn cp-btn--primary" href="<?= cp_h(cp_url('home', [], $lang)) ?>"><?= cp_h(cp_ui('back_to_pedia', $lang)) ?></a>
                    <?php if (cp_can_write() && $slugOrId !== '' && !ctype_digit($slugOrId)): ?>
                        <?php $guess = ucfirst(str_replace('-', ' ', $slugOrId)); ?>
                        <a class="cp-btn" href="<?= cp_h(cp_write_url($lang, $type, $guess)) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i><?= cp_h(cp_ui('write_this', $lang, $guess)) ?></a>
                    <?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <?php cp_render_bar($lang, $entryType); ?>

            <div class="cp-entry">
                <article class="cp-article">
                    <a class="cp-crumb" href="<?= cp_h(cp_url('category', ['type' => $entryType], $lang)) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><?= cp_h(cp_type_plural($entryType, $lang)) ?></a>
                    <h1><?= cp_h($titleText) ?></h1>
                    <?php if ($lead !== ''): ?><p class="cp-lead"><?= cp_h($lead) ?></p><?php endif; ?>
                    <?php if ($note !== ''): ?><p class="cp-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><?= cp_h($note) ?></p><?php endif; ?>

                    <?php if ($image || $facts): ?>
                        <div class="cp-phone-card">
                            <?php if ($image): ?><span class="cp-side__img"><img src="<?= cp_h($image) ?>" alt="<?= cp_h($titleText) ?>" decoding="async" data-cp-fade></span><?php endif; ?>
                            <?php if ($facts): ?>
                                <dl class="cp-facts">
                                    <?php foreach (array_slice($facts, 0, 3) as [$label, $value, $link]): ?>
                                        <div><dt><?= cp_h($label) ?></dt><dd><?php if ($link): ?><a href="<?= cp_h($link['url']) ?>"><img src="<?= cp_h($link['avatar']) ?>" alt="" loading="lazy"><?= cp_h($value) ?></a><?php else: ?><?= cp_h($value) ?><?php endif; ?></dd></div>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="cp-meta">
                        <div class="cp-meta__by">
                            <?php if ($authorship['user_id'] > 0): ?><img src="<?= cp_h(cp_avatar_url($authorship['user_id'], 64)) ?>" alt="" loading="lazy"><?php endif; ?>
                            <span>
                                <?php if ($authorship['name'] !== ''): ?>
                                    <?= cp_h(cp_ui('modified', $lang, cp_relative_time($authorship['at'], $lang))) ?>
                                    <?= cp_h($lang === 'en' ? 'by' : 'da') ?> <a href="/u/<?= cp_h(rawurlencode($authorship['username'])) ?>"><b><?= cp_h($authorship['name']) ?></b></a>
                                <?php else: ?>
                                    <?= cp_h(cp_ui('modified', $lang, cp_relative_time($authorship['at'], $lang))) ?>
                                <?php endif; ?>
                                <span class="cp-meta__more">
                                    <?php if ($authorship['authors'] > 1): ?> · <?= cp_h(cp_ui_n('authors', $authorship['authors'], $lang)) ?><?php endif; ?>
                                    · <?= cp_h(cp_ui_n('reads', (int)($entry['views_count'] ?? 0), $lang)) ?>
                                </span>
                            </span>
                        </div>
                        <div class="cp-meta__actions">
                            <?php if (cp_can_write()): ?>
                                <a class="cp-btn" href="<?= cp_h(cp_url('editor', ['id' => $entryId], $lang)) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i><?= cp_h(cp_ui('edit', $lang)) ?></a>
                            <?php endif; ?>
                            <button class="cp-icon" type="button" data-cp-share title="<?= cp_h(cp_ui('share', $lang)) ?>" aria-label="<?= cp_h(cp_ui('share', $lang)) ?>"><i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i></button>
                        </div>
                    </div>

                    <div class="cp-prose" data-cp-prose>
                        <?php if ($intro !== ''): ?><p><?= nl2br(cp_h($intro)) ?></p><?php endif; ?>
                        <?= $contentHtml ?>
                    </div>

                    <?php if ($quotes): ?>
                        <section class="cp-block" id="cp-citazioni">
                            <h2><?= cp_h(cp_ui('quotes', $lang)) ?></h2>
                            <div class="cp-quotes">
                                <?php foreach ($quotes as $quote): ?>
                                    <figure class="cp-quote">
                                        <blockquote><p><?= cp_h(cp_i18n($quote, 'quote_text', $lang)) ?></p></blockquote>
                                        <?php if (cp_i18n($quote, 'speaker', $lang) !== ''): ?><figcaption><?= cp_h(cp_i18n($quote, 'speaker', $lang)) ?></figcaption><?php endif; ?>
                                    </figure>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($relations): ?>
                        <section class="cp-block" id="cp-collegamenti">
                            <h2><?= cp_h(cp_ui('links', $lang)) ?></h2>
                            <div class="cp-links">
                                <?php foreach ($relations as $relation): ?>
                                    <a class="cp-link" href="<?= cp_h(cp_entry_url($relation, $lang)) ?>">
                                        <?php cp_render_media($relation, 'cp-link__img', $lang); ?>
                                        <span><small><?= cp_h(cp_relation_label($relation, $lang)) ?></small><strong><?= cp_h(cp_i18n($relation, 'title', $lang)) ?></strong></span>
                                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($mentionedIn): ?>
                        <section class="cp-block cp-block--small" id="cp-citata">
                            <h2><?= cp_h(cp_ui('cited_in', $lang)) ?></h2>
                            <p class="cp-cited">
                                <?php foreach ($mentionedIn as $i => $other): ?><?= $i > 0 ? ', ' : '' ?><a href="<?= cp_h(cp_entry_url($other, $lang)) ?>"><?= cp_h(cp_i18n($other, 'title', $lang)) ?></a><?php endforeach; ?>
                            </p>
                        </section>
                    <?php endif; ?>

                    <footer class="cp-foot">
                        <span><?= cp_h(cp_ui('last_edit', $lang, cp_format_date($authorship['at'], $lang, 'long') . ', ' . date('H:i', strtotime($authorship['at']) ?: time()))) ?></span>
                        <?php if ($mentionedIn): ?><a href="#cp-citata"><?= cp_h(cp_ui_n('cited_in_n', count($mentionedIn), $lang)) ?></a><?php endif; ?>
                    </footer>
                </article>

                <aside class="cp-side">
                    <?php if ($image): ?>
                        <span class="cp-side__img"><img src="<?= cp_h($image) ?>" alt="<?= cp_h($titleText) ?>" decoding="async" data-cp-fade></span>
                    <?php endif; ?>
                    <?php if ($facts || $tags): ?>
                        <dl class="cp-facts">
                            <?php foreach ($facts as [$label, $value, $link]): ?>
                                <div><dt><?= cp_h($label) ?></dt><dd><?php if ($link): ?><a href="<?= cp_h($link['url']) ?>"><img src="<?= cp_h($link['avatar']) ?>" alt="" loading="lazy"><?= cp_h($value) ?></a><?php else: ?><?= cp_h($value) ?><?php endif; ?></dd></div>
                            <?php endforeach; ?>
                            <?php if ($tags): ?>
                                <div><dt><?= cp_h(cp_ui('tags', $lang)) ?></dt><dd><span class="cp-tags">
                                    <?php foreach ($tags as $tagRow): ?>
                                        <a class="cp-tag" href="<?= cp_h(cp_url('category', ['type' => $entryType, 'tag' => $tagRow['slug']], $lang)) ?>"><?= cp_h(cp_i18n($tagRow, 'name', $lang)) ?></a>
                                    <?php endforeach; ?>
                                </span></dd></div>
                            <?php endif; ?>
                        </dl>
                    <?php endif; ?>
                    <?php if ($toc): ?>
                        <nav class="cp-toc" aria-label="<?= cp_h(cp_ui('in_this_entry', $lang)) ?>">
                            <h3><?= cp_h(cp_ui('in_this_entry', $lang)) ?></h3>
                            <?php foreach ($toc as $item): ?>
                                <a class="cp-toc__l<?= $item['level'] ?>" href="#<?= cp_h($item['id']) ?>" data-cp-toc="<?= cp_h($item['id']) ?>"><?= cp_h($item['text']) ?></a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>
                </aside>
            </div>
        <?php endif; ?>
    </main>

    <?php cp_render_footer($lang); ?>
</body>

</html>
