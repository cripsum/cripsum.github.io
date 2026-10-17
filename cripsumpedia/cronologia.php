<?php

declare(strict_types=1);

/**
 * Cripsumpedia — la cronologia di una voce (fase 4).
 *
 * ?voce=ID tutte le versioni approvate, dalla più recente; &versione=ID cosa
 * è cambiato in quella versione rispetto alla precedente. La vede chiunque
 * può leggere la voce; «Ripristina» solo lo staff.
 */

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$w = cp_write_strings($lang);
$staff = cp_is_admin_user();
$entryId = (int)($_GET['voce'] ?? $_GET['id'] ?? 0);
$entry = $entryId > 0 && cp_schema_ready($mysqli) ? cp_fetch_entry($mysqli, null, (string)$entryId, $staff, $staff) : null;
$ready = cp_v2_ready($mysqli);
$history = $entry && $ready ? cp_entry_history($mysqli, (int)$entry['id'], 200) : [];
$selected = null;
$diff = [];
$previous = null;

$versionId = (int)($_GET['versione'] ?? 0);
if ($entry && $versionId > 0) {
    $selected = cp_revision_find($mysqli, $versionId);
    if (!$selected || (int)$selected['entry_id'] !== (int)$entry['id'] || $selected['status'] !== 'approved') {
        $selected = null;
    } else {
        $previous = cp_previous_revision($mysqli, $selected);
        $diff = cp_diff($mysqli, $previous ? cp_data_for_editor($previous['data_array']) : [], cp_data_for_editor($selected['data_array']), $lang);
    }
}
$selectedRow = null;
foreach ($history as $row) {
    if ($selected && (int)$row['id'] === (int)$selected['id']) $selectedRow = $row;
}

if (!$entry) http_response_code(404);
$title = $entry ? cp_w('history_of', $lang, cp_i18n($entry, 'title', $lang)) : $w['history'];
$currentId = $entry ? (int)($entry['revision_id'] ?? 0) : 0;
$who = static fn(array $row): string => (string)(($row['display_name'] ?? '') ?: ($row['username'] ?? '')) ?: $w['deleted_account'];
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php cp_render_head($title . ' - Cripsumpedia', $w['history_lead'], $lang); ?>
    <meta name="robots" content="noindex">
</head>

<body class="cp-body cp-page-history">
    <?php cp_render_topbar($lang, 'history'); ?>

    <main class="cp-main">
        <?php if (!cp_schema_ready($mysqli)): ?>
            <?php cp_render_install_notice($lang); ?>
        <?php else: ?>
            <?php cp_render_bar($lang, (string)($entry['entry_type'] ?? '')); ?>

            <?php if (!$entry): ?>
                <section class="cp-notfound">
                    <h1><?= cp_h(cp_ui('not_found_title', $lang)) ?></h1>
                    <p><?= cp_h(cp_ui('not_found_text', $lang)) ?></p>
                    <div class="cp-notfound__actions"><a class="cp-btn cp-btn--primary" href="<?= cp_h(cp_url('home', [], $lang)) ?>"><?= cp_h(cp_ui('back_to_pedia', $lang)) ?></a></div>
                </section>
            <?php else: ?>
                <div class="cp-history" data-cp-review>
                    <a class="cp-crumb" href="<?= cp_h(cp_entry_url($entry, $lang)) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><?= cp_h(cp_i18n($entry, 'title', $lang)) ?></a>
                    <h1><?= cp_h($title) ?></h1>
                    <p class="cp-lead"><?= cp_h($w['history_lead']) ?></p>

                    <?php if ($selected && $selectedRow): ?>
                        <section class="cp-history__diff">
                            <h2><?= cp_h(cp_w('changes_of', $lang, cp_format_date((string)$selected['created_at'], $lang, 'long') . ', ' . date('H:i', strtotime((string)$selected['created_at']) ?: time()))) ?></h2>
                            <p class="cp-history__by">
                                <b><?= cp_h($who($selectedRow)) ?></b> <?= cp_h(cp_history_action($selected, $lang)) ?>
                                <?php if (cp_history_summary($selected) !== ''): ?> · <q><?= cp_h(cp_history_summary($selected)) ?></q><?php endif; ?>
                            </p>
                            <div class="cp-diff">
                                <?php if (!$previous): ?><p class="cp-muted"><?= cp_h($w['first_version']) ?></p><?php endif; ?>
                                <?php if ($diff): ?>
                                    <?php cp_render_diff($diff, $mysqli, $lang); ?>
                                <?php elseif ($previous): ?>
                                    <p class="cp-muted"><?= cp_h($w['no_diff']) ?></p>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($history): ?>
                        <ol class="cp-versions">
                            <?php foreach ($history as $row):
                                $isCurrent = (int)$row['id'] === $currentId;
                                $isSelected = $selected && (int)$row['id'] === (int)$selected['id']; ?>
                                <li class="<?= $isSelected ? 'is-on' : '' ?>">
                                    <img src="<?= cp_h(cp_avatar_url((int)($row['user_id'] ?? 0), 64)) ?>" alt="" loading="lazy">
                                    <span>
                                        <b><?= cp_h($who($row)) ?></b> <?= cp_h(cp_history_action($row, $lang)) ?><?php if (cp_history_summary($row) !== ''): ?> · <q><?= cp_h(cp_history_summary($row)) ?></q><?php endif; ?>
                                        <small><?= cp_h(cp_format_date((string)$row['created_at'], $lang, 'long') . ', ' . date('H:i', strtotime((string)$row['created_at']) ?: time())) ?><?php if ($isCurrent): ?> · <?= cp_h($w['current']) ?><?php endif; ?></small>
                                    </span>
                                    <span class="cp-versions__actions">
                                        <a class="cp-btn" href="<?= cp_h(cp_url('history', ['voce' => (int)$entry['id'], 'versione' => (int)$row['id']], $lang)) ?>"><?= cp_h($w['compare']) ?></a>
                                        <?php if ($staff && !$isCurrent && $row['kind'] !== 'hide' && empty($entry['deleted_at'])): ?>
                                            <button class="cp-btn" type="button" data-cp-restore="<?= (int)$row['id'] ?>" data-confirm="<?= cp_h(cp_w('restore_confirm', $lang, cp_format_date((string)$row['created_at'], $lang, 'long'))) ?>"><?= cp_h($w['restore']) ?></button>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p class="cp-muted cp-history__none"><?= cp_h($ready ? cp_ui('nobody_here', $lang) : cp_cannot_write_text('migration', $lang, $mysqli)) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php cp_render_footer($lang); ?>
</body>

</html>
