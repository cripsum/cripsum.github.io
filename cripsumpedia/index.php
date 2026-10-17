<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$ready = cp_schema_ready($mysqli);
$canWrite = cp_can_write();
// Con le proposte aperte l'invito spiega i tre passi; chi non ha un account va ad accedere.
$proposalsOpen = $ready && cp_v2_ready($mysqli) && cp_settings($mysqli)['proposals_open'];
$showInvite = $canWrite || ($proposalsOpen && cp_current_user_id() <= 0);

$stats = cp_fetch_stats($mysqli);
$featured = $ready ? cp_featured_entry($mysqli) : null;
$featuredId = (int)($featured['id'] ?? 0);

// Ogni voce compare una volta sola: niente liste che ripetono le stesse cose.
$people = cp_fetch_entries($mysqli, ['type' => 'person', 'order' => 'trending', 'limit' => 6]);
$events = cp_fetch_entries($mysqli, ['type' => 'event', 'order' => 'date', 'limit' => 4]);
$memes = cp_fetch_entries($mysqli, ['type' => 'meme', 'order' => 'latest', 'limit' => 4]);
$onThisDay = $ready ? cp_events_on_this_day($mysqli) : [];
$changes = $ready ? cp_recent_changes($mysqli, 5) : [];

// «Prova con»: tre voci vere, le più lette, senza quella in evidenza.
$tryWith = array_slice(array_values(array_filter(
    cp_fetch_entries($mysqli, ['order' => 'trending', 'limit' => 4]),
    static fn(array $entry): bool => (int)$entry['id'] !== $featuredId
)), 0, 3);

$total = (int)array_sum([$stats['person'], $stats['event'], $stats['meme']]);
$title = 'Cripsumpedia - Cripsum';
$description = cp_t('subtitle', $lang);
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php cp_render_head($title, $description, $lang); ?>
</head>

<body class="cp-body cp-page-home">
    <?php cp_render_topbar($lang, 'home'); ?>

    <main class="cp-main">
        <?php if (!$ready): ?>
            <?php cp_render_install_notice($lang); ?>
        <?php else: ?>
            <?php cp_render_bar($lang, '', false); ?>

            <section class="cp-hero">
                <div class="cp-hero__text">
                    <h1><?= cp_h(cp_ui('hero_1', $lang)) ?><br><span><?= cp_h(cp_ui('hero_2', $lang)) ?></span></h1>
                    <p class="cp-hero__lead"><?= cp_h(cp_ui('hero_lead', $lang)) ?></p>
                    <form class="cp-search" action="<?= cp_h(cp_url('search', [], $lang)) ?>" method="get" role="search">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" name="q" placeholder="<?= cp_h(cp_ui('search_hint', $lang)) ?>" aria-label="<?= cp_h(cp_ui('search_pedia', $lang)) ?>" autocomplete="off" spellcheck="false" data-cp-search-open>
                        <button class="cp-btn cp-btn--primary" type="submit"><span><?= cp_h(cp_t('search', $lang)) ?></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                    </form>
                    <?php if ($tryWith): ?>
                        <p class="cp-try">
                            <?= cp_h(cp_ui('try_with', $lang)) ?>
                            <?php foreach ($tryWith as $entry): ?>
                                <a href="<?= cp_h(cp_entry_url($entry, $lang)) ?>"><?= cp_h(cp_i18n($entry, 'title', $lang)) ?></a>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($featured): ?>
                    <a class="cp-feature" href="<?= cp_h(cp_entry_url($featured, $lang)) ?>">
                        <span class="cp-feature__img">
                            <?php cp_render_media($featured, 'cp-feature__media', $lang, false); ?>
                            <span class="cp-feature__label"><?= cp_h(cp_ui('featured', $lang)) ?></span>
                        </span>
                        <span class="cp-feature__cap">
                            <span>
                                <strong><?= cp_h(cp_i18n($featured, 'title', $lang)) ?></strong>
                                <?php $line = cp_entry_line($featured, $lang, 80); ?>
                                <?php if ($line !== ''): ?><span><?= cp_h($line) ?></span><?php endif; ?>
                            </span>
                            <em><?= cp_h(cp_ui('read', $lang)) ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></em>
                        </span>
                    </a>
                <?php endif; ?>
            </section>

            <?php if ($total === 0 && !$canWrite): ?>
                <p class="cp-empty"><?= cp_h(cp_ui('nobody_here', $lang)) ?></p>
            <?php endif; ?>

            <?php if ($onThisDay): ?>
                <section class="cp-today" aria-label="<?= cp_h(cp_ui('on_this_day', $lang)) ?>">
                    <span class="cp-today__label"><i class="fa-regular fa-calendar" aria-hidden="true"></i><?= cp_h(cp_ui('on_this_day', $lang)) ?></span>
                    <?php foreach ($onThisDay as $entry): ?>
                        <?php $years = (int)date('Y') - (int)substr((string)$entry['real_date'], 0, 4); ?>
                        <a href="<?= cp_h(cp_entry_url($entry, $lang)) ?>"><strong><?= cp_h(cp_i18n($entry, 'title', $lang)) ?></strong><span><?= cp_h(cp_ui_n('years_ago', $years, $lang)) ?></span></a>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <?php if ($people || $canWrite): ?>
                <section class="cp-sec">
                    <?php cp_render_section_head(cp_t('people', $lang), $stats['person'], cp_ui('all_people', $lang), cp_url('category', ['type' => 'person'], $lang)); ?>
                    <div class="cp-people">
                        <?php foreach ($people as $entry): ?>
                            <?php cp_render_person_tile($entry, $lang); ?>
                        <?php endforeach; ?>
                        <?php if ($canWrite && count($people) < 6): ?>
                            <?php cp_render_ghost_tile(cp_ui('add_person', $lang), cp_write_url($lang, 'person')); ?>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($events || $memes || $canWrite): ?>
                <div class="cp-split cp-sec">
                    <?php if ($events || $canWrite): ?>
                        <section>
                            <?php cp_render_section_head(cp_t('events', $lang), $stats['event'], cp_ui('timeline_link', $lang), cp_url('category', ['type' => 'event'], $lang)); ?>
                            <ol class="cp-events">
                                <?php foreach ($events as $entry): ?>
                                    <?php cp_render_event_row($entry, $lang); ?>
                                <?php endforeach; ?>
                                <?php if ($canWrite && count($events) < 4): ?>
                                    <li>
                                        <a class="cp-ev cp-ev--ghost" href="<?= cp_h(cp_write_url($lang, 'event')) ?>">
                                            <time><i class="fa-solid fa-plus" aria-hidden="true"></i></time>
                                            <span class="cp-ev__text"><strong><?= cp_h(cp_ui('add_event', $lang)) ?></strong><span><?= cp_h(cp_ui('add_event_text', $lang)) ?></span></span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ol>
                        </section>
                    <?php endif; ?>

                    <?php if ($memes || $canWrite): ?>
                        <section>
                            <?php cp_render_section_head(cp_t('memes', $lang), $stats['meme'], cp_ui('all_memes', $lang), cp_url('category', ['type' => 'meme'], $lang)); ?>
                            <div class="cp-memes">
                                <?php foreach (array_slice($memes, 0, $canWrite ? 3 : 4) as $entry): ?>
                                    <?php cp_render_meme_tile($entry, $lang); ?>
                                <?php endforeach; ?>
                                <?php if ($canWrite): ?>
                                    <?php cp_render_ghost_tile(cp_ui('add_meme', $lang), cp_write_url($lang, 'meme'), 'cp-meme'); ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($changes || $showInvite): ?>
                <div class="cp-split cp-sec">
                    <?php if ($changes): ?>
                        <section>
                            <?php cp_render_section_head(cp_ui('recent_changes', $lang), null); ?>
                            <ul class="cp-feed">
                                <?php foreach ($changes as $change): ?>
                                    <?php cp_render_change_row($change, $lang); ?>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>

                    <?php if ($showInvite): ?>
                        <aside class="cp-invite">
                            <h3><?= cp_h(cp_ui('invite_title', $lang)) ?></h3>
                            <?php if ($proposalsOpen): ?>
                                <ol class="cp-steps">
                                    <li><?= cp_h(cp_ui('invite_step_1', $lang)) ?></li>
                                    <li><?= cp_h(cp_ui('invite_step_2', $lang)) ?></li>
                                    <li><?= cp_h(cp_ui('invite_step_3', $lang)) ?></li>
                                </ol>
                            <?php else: ?>
                                <p><?= cp_h(cp_ui('invite_text', $lang)) ?></p>
                            <?php endif; ?>
                            <?php if ($canWrite): ?>
                                <a class="cp-btn" href="<?= cp_h(cp_write_url($lang)) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i><?= cp_h(cp_ui('write', $lang)) ?></a>
                            <?php else: ?>
                                <a class="cp-btn" href="<?= cp_h(cp_write_url($lang)) ?>"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><?= cp_h(cp_ui('invite_login', $lang)) ?></a>
                            <?php endif; ?>
                        </aside>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php cp_render_footer($lang); ?>
</body>

</html>
