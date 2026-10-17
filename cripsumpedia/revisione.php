<?php

declare(strict_types=1);

/**
 * Cripsumpedia — Da approvare (fase 4, tavola 5). Solo owner e admin.
 *
 * ?id=ID una proposta; senza id la più vecchia in coda. A sinistra chi l'ha
 * proposta, le differenze campo per campo (o «Come verrebbe») e le tre
 * scelte; a destra la coda e la cronologia della voce.
 */

require_once __DIR__ . '/_bootstrap.php';

cp_require_admin();

$lang = cp_detect_lang();
$w = cp_write_strings($lang);
$ready = cp_v2_ready($mysqli);
// Le voci scritte prima della cronologia, o col testo salvato da un
// trasformatore vecchio: si mettono in pari qui e dal Pannello admin.
if ($ready) cp_v2_backfill($mysqli);
$queue = $ready ? cp_pending_list($mysqli, 50) : [];
$id = (int)($_GET['id'] ?? 0);
$revision = null;
$handled = false;

if ($ready) {
    if ($id > 0) {
        $revision = cp_revision_find($mysqli, $id);
        if (!$revision || !in_array($revision['status'], ['pending', 'reviewing'], true)) {
            $revision = null;
            $handled = true;
        }
    }
    if (!$revision && !$handled && $queue) {
        $revision = cp_revision_find($mysqli, (int)$queue[0]['id']);
    }
}

$entry = null;
$diff = [];
$conflicts = [];
$result = null;
$author = null;
$approvedBefore = 0;
$history = [];

if ($revision) {
    $proposal = cp_data_for_editor($revision['data_array']);
    if ($revision['entry_id']) {
        $entry = cp_fetch_entry($mysqli, null, (string)$revision['entry_id'], true, true);
    }
    if ($entry) {
        $current = cp_entry_snapshot($mysqli, $entry);
        $base = $revision['base_revision_id'] ? (cp_revision_find($mysqli, (int)$revision['base_revision_id'])['data_array'] ?? $current) : $current;
        $patch = cp_patch_data($current, $base, $revision['data_array']);
        $result = cp_data_for_editor($patch['data']);
        $conflicts = $patch['conflicts'];
        $diff = cp_diff($mysqli, cp_data_for_editor($current), $result, $lang);
        $history = cp_entry_history($mysqli, (int)$entry['id'], 8);
    } else {
        $result = $proposal;
        $diff = cp_diff($mysqli, [], $result, $lang);
    }

    $authorId = (int)($revision['user_id'] ?? 0);
    if ($authorId > 0) {
        $stmt = $mysqli->prepare('SELECT id, username, display_name FROM utenti WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $authorId);
        $stmt->execute();
        $author = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        $stmt = $mysqli->prepare("SELECT COUNT(*) FROM cripsumpedia_revisions WHERE user_id = ? AND status = 'approved' AND kind IN ('new', 'edit')");
        $stmt->bind_param('i', $authorId);
        $stmt->execute();
        $approvedBefore = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
    }
}

$labels = $w['fields'];
$conflictNames = array_map(static fn(string $field): string => mb_strtolower($labels[$field] ?? $field, 'UTF-8'), $conflicts);
$resultTitle = $result ? (string)($result['fields']['title'] ?? '') : '';
$resultHtml = $result ? cp_autolink_html(cp_resolve_wiki_links(cp_markdown_render((string)($result['fields']['content_md'] ?? '')), $mysqli, $lang), $mysqli, $lang, $entry ? (int)$entry['id'] : null) : '';
$resultImage = $result && trim((string)($result['fields']['image_url'] ?? '')) !== '' ? cp_asset_url((string)$result['fields']['image_url']) : null;
$kindLabel = static fn(array $row): string => $row['kind'] === 'new'
    ? cp_type_label((string)($row['entry_type'] ?: 'person'), $lang) . ' · ' . $w['kind_new']
    : $w['kind_edit'];
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php cp_render_head($w['queue'] . ' - Cripsumpedia', $w['queue'], $lang); ?>
    <meta name="robots" content="noindex">
</head>

<body class="cp-body cp-page-review">
    <?php cp_render_topbar($lang, 'review'); ?>

    <main class="cp-main">
        <?php if (!cp_schema_ready($mysqli)): ?>
            <?php cp_render_install_notice($lang); ?>
        <?php else: ?>
            <?php cp_render_bar($lang); ?>

            <div class="cp-review" data-cp-review data-review-url="<?= cp_h(cp_url('review', [], $lang)) ?>">
                <div class="cp-review__main">
                    <a class="cp-crumb" href="<?= cp_h(cp_url('admin', [], $lang)) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><?= cp_h($lang === 'en' ? 'Admin panel' : 'Pannello admin') ?></a>

                    <?php if (!$ready): ?>
                        <h1><?= cp_h($w['queue']) ?></h1>
                        <p class="cp-review__empty"><?= cp_h(cp_cannot_write_text('migration', $lang, $mysqli)) ?></p>
                    <?php elseif (!$revision): ?>
                        <h1><?= cp_h($w['queue']) ?></h1>
                        <p class="cp-review__empty"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><?= cp_h($handled ? $w['handled'] : $w['queue_empty']) ?></p>
                    <?php else: ?>
                        <h1>
                            <?= cp_h($revision['kind'] === 'new' ? $w['review_new'] : $w['review_edit']) ?>
                            <span><?= cp_h($entry ? cp_i18n($entry, 'title', $lang) : ($resultTitle !== '' ? $resultTitle : $w['untitled'])) ?></span>
                        </h1>

                        <div class="cp-who">
                            <img src="<?= cp_h(cp_avatar_url((int)($author['id'] ?? 0), 96)) ?>" alt="" loading="lazy">
                            <div>
                                <?php if ($author): ?>
                                    <a href="/u/<?= cp_h(rawurlencode((string)$author['username'])) ?>"><b><?= cp_h((string)$author['username']) ?></b></a>
                                <?php else: ?>
                                    <b><?= cp_h($w['deleted_account']) ?></b>
                                <?php endif; ?>
                                <?= cp_h(cp_relative_time((string)$revision['created_at'], $lang)) ?> · <?= cp_h($approvedBefore > 0 ? cp_w('nth_time', $lang, $approvedBefore + 1) : $w['first_time']) ?>
                                <?php if (trim((string)$revision['summary']) !== ''): ?> · <q><?= cp_h((string)$revision['summary']) ?></q><?php endif; ?>
                            </div>
                        </div>

                        <?php if ($conflictNames): ?>
                            <p class="cp-note"><i class="fa-solid fa-code-merge" aria-hidden="true"></i><?= cp_h(cp_w('conflicts', $lang, implode(', ', $conflictNames))) ?></p>
                        <?php endif; ?>

                        <div class="cp-chips cp-review__tabs" role="tablist">
                            <button type="button" class="cp-chip is-on" role="tab" aria-selected="true" data-cp-review-tab="diff"><?= cp_h($w['tab_diff']) ?></button>
                            <button type="button" class="cp-chip" role="tab" aria-selected="false" data-cp-review-tab="result"><?= cp_h($w['tab_result']) ?></button>
                        </div>

                        <div class="cp-diff" data-cp-review-pane="diff">
                            <?php if ($diff): ?>
                                <?php cp_render_diff($diff, $mysqli, $lang); ?>
                            <?php else: ?>
                                <p class="cp-review__empty"><?= cp_h($w['no_diff']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="cp-result" data-cp-review-pane="result" hidden>
                            <?php if ($resultImage): ?><span class="cp-result__img"><img src="<?= cp_h($resultImage) ?>" alt=""></span><?php endif; ?>
                            <h2><?= cp_h($resultTitle) ?></h2>
                            <?php if (trim((string)($result['fields']['subtitle'] ?? '')) !== ''): ?><p class="cp-lead"><?= cp_h((string)$result['fields']['subtitle']) ?></p><?php endif; ?>
                            <div class="cp-prose"><?= $resultHtml ?></div>
                        </div>

                        <div class="cp-decide">
                            <button class="cp-btn cp-btn--primary cp-btn--lg" type="button" data-cp-approve="<?= (int)$revision['id'] ?>"><i class="fa-solid fa-check" aria-hidden="true"></i><?= cp_h($w['approve']) ?></button>
                            <button class="cp-btn cp-btn--lg" type="button" data-cp-reject-open aria-expanded="false" aria-controls="cp-reject"><?= cp_h($w['reject']) ?></button>
                            <a class="cp-linkish cp-decide__fix" href="<?= cp_h(cp_url('write', ['sistema' => (int)$revision['id']], $lang)) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i>&nbsp; <?= cp_h($w['fix']) ?></a>
                        </div>

                        <form class="cp-reject" id="cp-reject" data-cp-reject="<?= (int)$revision['id'] ?>" hidden>
                            <h2><?= cp_h($w['reject_title']) ?></h2>
                            <div class="cp-reasons" role="radiogroup" aria-label="<?= cp_h($w['reasons_label']) ?>">
                                <?php foreach (cp_rejection_reasons() as $value => $text): ?>
                                    <label class="cp-reason"><input type="radio" name="reason" value="<?= cp_h($value) ?>" required><span><?= cp_h($text[$lang] ?? $text['it']) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                            <label class="cp-label" for="cp-reject-note"><?= cp_h($w['reject_note']) ?> <small><?= cp_h($w['reject_note_small']) ?></small></label>
                            <textarea class="cp-input cp-input--area" id="cp-reject-note" name="note" maxlength="500" rows="3"></textarea>
                            <div class="cp-reject__actions">
                                <button class="cp-btn" type="button" data-cp-reject-cancel><?= cp_h($w['cancel']) ?></button>
                                <button class="cp-btn cp-btn--danger" type="submit"><?= cp_h($w['reject_btn']) ?></button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <aside class="cp-review__side">
                    <section class="cp-queue">
                        <h2><?= cp_h($w['queue']) ?><?php if ($queue): ?><small><?= count($queue) ?></small><?php endif; ?></h2>
                        <?php if ($queue): ?>
                            <ul>
                                <?php foreach ($queue as $row): ?>
                                    <li<?= $revision && (int)$row['id'] === (int)$revision['id'] ? ' class="is-on" aria-current="true"' : '' ?>>
                                        <a href="<?= cp_h(cp_url('review', ['id' => (int)$row['id']], $lang)) ?>">
                                            <img src="<?= cp_h(cp_avatar_url((int)($row['user_id'] ?? 0), 64)) ?>" alt="" loading="lazy">
                                            <span><b><?= cp_h((string)$row['title'] !== '' ? (string)$row['title'] : $w['untitled']) ?></b><?= cp_h(($row['username'] ?? '') ?: $w['deleted_account']) ?> · <?= cp_h($kindLabel($row)) ?> · <?= cp_h(cp_relative_time((string)$row['created_at'], $lang)) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="cp-muted"><?= cp_h($w['queue_empty']) ?></p>
                        <?php endif; ?>
                    </section>

                    <?php if ($entry && $history): ?>
                        <section class="cp-queue cp-hist">
                            <h2><?= cp_h(cp_w('history_of', $lang, cp_i18n($entry, 'title', $lang))) ?></h2>
                            <ul>
                                <?php foreach ($history as $i => $row): ?>
                                    <li>
                                        <img src="<?= cp_h(cp_avatar_url((int)($row['user_id'] ?? 0), 64)) ?>" alt="" loading="lazy">
                                        <span><b><?= cp_h(($row['username'] ?? '') ?: $w['deleted_account']) ?></b><?= cp_h(cp_history_action($row, $lang)) ?> · <?= cp_h(cp_relative_time((string)$row['created_at'], $lang)) ?></span>
                                        <a href="<?= cp_h(cp_url('history', ['voce' => (int)$entry['id'], 'versione' => (int)$row['id']], $lang)) ?>"><?= cp_h($w['compare']) ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <a class="cp-hist__all" href="<?= cp_h(cp_url('history', ['voce' => (int)$entry['id']], $lang)) ?>"><?= cp_h($w['history']) ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </section>
                    <?php endif; ?>
                </aside>
            </div>
        <?php endif; ?>
    </main>

    <?php cp_render_footer($lang); ?>
</body>

</html>
