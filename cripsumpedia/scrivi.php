<?php

declare(strict_types=1);

/**
 * Cripsumpedia — Scrivi (fase 4, tavola 4 a destra).
 *
 * Una pagina sola per scrivere una voce nuova e per modificarne una:
 *   ?type=&title=   voce nuova (dalla ricerca: titolo già scritto)
 *   ?voce=ID        modifica (se c'è una bozza tua per quella voce, riparte da lì)
 *   ?bozza=ID       riprende una bozza
 *   ?riprendi=ID    riprende una proposta che lo staff non ha pubblicato
 *   ?sistema=ID     solo staff: «Sistema e poi approva» una proposta
 *   ?inviata=ID     «Inviata allo staff»
 * Tutto il resto lo fa scrivi.js (bozza che si salva da sola, foto, [[, invio).
 */

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$w = cp_write_strings($lang);
$userId = cp_current_user_id();
$staff = cp_is_admin_user();

$view = 'form';
$message = '';
$notice = '';
$mode = 'new';
$draft = null;
$entry = null;
$data = null;
$summary = '';
$fix = null;
$type = cp_normalize_type($_GET['type'] ?? null) ?? 'person';

if (!cp_schema_ready($mysqli)) {
    $view = 'install';
} elseif ($userId <= 0) {
    $view = 'login';
    $_SESSION['redirect_after_login'] = (string)($_SERVER['REQUEST_URI'] ?? cp_url('write', [], $lang));
} elseif (!cp_v2_ready($mysqli)) {
    $view = 'cannot';
    $message = cp_cannot_write_text('migration', $lang, $mysqli);
} elseif (isset($_GET['inviata'])) {
    $view = 'sent';
} else {
    [$canPropose, $why] = cp_can_propose($mysqli);
    $fixId = $staff ? (int)($_GET['sistema'] ?? 0) : 0;
    $draftId = (int)($_GET['bozza'] ?? 0);
    $entryId = (int)($_GET['voce'] ?? $_GET['id'] ?? 0);
    $rejectedId = (int)($_GET['riprendi'] ?? 0);

    if (!$canPropose) {
        $view = 'cannot';
        $message = cp_cannot_write_text($why, $lang, $mysqli);
    } elseif ($fixId > 0) {
        $fix = cp_revision_find($mysqli, $fixId);
        if (!$fix || $fix['status'] !== 'pending') {
            $view = 'gone';
            $message = $w['handled'];
        } else {
            $mode = 'fix';
            $data = $fix['data_array'];
            $summary = (string)$fix['summary'];
            $entry = $fix['entry_id'] ? cp_fetch_entry($mysqli, null, (string)$fix['entry_id'], true) : null;
        }
    } elseif ($draftId > 0) {
        $draft = cp_revision_find($mysqli, $draftId);
        if (!$draft || (int)$draft['user_id'] !== $userId || $draft['status'] !== 'draft') {
            $view = 'gone';
            $message = $w['gone_text'];
            $draft = null;
        } else {
            $entry = $draft['entry_id'] ? cp_fetch_entry($mysqli, null, (string)$draft['entry_id'], $staff) : null;
            if ($draft['entry_id'] && !$entry) {
                $view = 'gone';
                $message = $w['entry_gone'];
            }
            $data = $draft['data_array'];
            $summary = (string)$draft['summary'];
            $notice = cp_w('resumed', $lang, cp_relative_time((string)$draft['updated_at'], $lang));
        }
    } elseif ($entryId > 0) {
        $entry = cp_fetch_entry($mysqli, null, (string)$entryId, $staff);
        if (!$entry) {
            $view = 'gone';
            $message = $w['entry_gone'];
        } else {
            $draft = cp_user_draft_for($mysqli, $userId, (int)$entry['id']);
            if ($draft) {
                $data = $draft['data_array'];
                $summary = (string)$draft['summary'];
                $notice = cp_w('resumed', $lang, cp_relative_time((string)$draft['updated_at'], $lang));
            } else {
                $data = cp_write_base($mysqli, $entry, (string)$entry['entry_type']);
            }
        }
    } elseif ($rejectedId > 0) {
        $resume = cp_resume_rejected($mysqli, $userId, $rejectedId);
        if (!$resume) {
            $view = 'gone';
            $message = $w['gone_text'];
        } else {
            $entry = $resume['entry'];
            $data = $resume['data'];
            $summary = $resume['summary'];
            $notice = $w['resumed_rejected'];
        }
    }

    if ($view === 'form') {
        if ($data === null) {
            $data = cp_empty_data($type);
            $data['fields']['title'] = mb_substr(cp_clean_line($_GET['title'] ?? ''), 0, CP_LIMITS['title'], 'UTF-8');
        }
        if ($mode !== 'fix' && $entry) $mode = 'edit';
        $data = cp_data_for_editor($data);
        $type = cp_normalize_type($data['entry_type'] ?? null) ?? 'person';
    }
}

$yours = $userId > 0 && cp_v2_ready($mysqli) ? array_values(array_filter(
    cp_user_open_revisions($mysqli, $userId),
    static fn(array $row): bool => !$draft || (int)$row['id'] !== (int)$draft['id']
)) : [];

$f = $data['fields'] ?? [];
$canUpload = $staff || cp_table_exists($mysqli, 'cripsumpedia_uploads');
$showType = $view === 'form' && ($mode === 'new' || $staff);
$author = '';
if ($fix) {
    $stmt = $mysqli->prepare('SELECT username FROM utenti WHERE id = ? LIMIT 1');
    $authorId = (int)$fix['user_id'];
    $stmt->bind_param('i', $authorId);
    $stmt->execute();
    $author = (string)($stmt->get_result()->fetch_row()[0] ?? $w['deleted_account']);
    $stmt->close();
}

// Le voci collegate, con foto e titolo.
$targets = [];
$relationIds = array_values(array_filter(array_map(static fn(array $r): int => (int)$r['target_id'], $data['relations'] ?? [])));
if ($relationIds) {
    $marks = implode(',', array_fill(0, count($relationIds), '?'));
    $stmt = $mysqli->prepare("SELECT id, entry_type, slug, title, title_en, image_url, banner_url FROM cripsumpedia_entries WHERE id IN ($marks)");
    $stmt->bind_param(str_repeat('i', count($relationIds)), ...$relationIds);
    $stmt->execute();
    foreach (cp_stmt_all($stmt) as $row) $targets[(int)$row['id']] = $row;
    $stmt->close();
}
$profileUsername = '';
if (!empty($f['profile_user_id'])) {
    $stmt = $mysqli->prepare('SELECT username FROM utenti WHERE id = ? LIMIT 1');
    $profileId = (int)$f['profile_user_id'];
    $stmt->bind_param('i', $profileId);
    $stmt->execute();
    $profileUsername = (string)($stmt->get_result()->fetch_row()[0] ?? '');
    $stmt->close();
}

$pageTitle = match (true) {
    $mode === 'fix' => cp_w('fix_title', $lang, $author),
    $mode === 'edit' => cp_w('edit_title', $lang, cp_i18n($entry ?? [], 'title', $lang)),
    default => $w['new_title'],
};
$lead = match (true) {
    $mode === 'fix' => $w['lead_fix'],
    $staff => $w['lead_staff'],
    $mode === 'edit' => $w['lead_edit'],
    default => $w['lead_new'],
};
$image = trim((string)($f['image_url'] ?? ''));
$typeSlugs = ['person' => cp_category_slug('person', $lang), 'event' => cp_category_slug('event', $lang), 'meme' => cp_category_slug('meme', $lang)];
$config = [
    'mode' => $mode,
    'staff' => $staff,
    'draftId' => $draft ? (int)$draft['id'] : null,
    'entryId' => $entry ? (int)$entry['id'] : null,
    'fixId' => $fix ? (int)$fix['id'] : null,
    'canUpload' => $canUpload,
    'address' => 'cripsum.com' . cp_base_url($lang) . '/',
    'typeSlugs' => $typeSlugs,
    'writeUrl' => cp_url('write', [], $lang),
    'reviewEndpoint' => '/api/cripsumpedia_review.php',
    'strings' => cp_write_js_strings($lang),
];

$renderYours = static function (array $yours) use ($lang, $w): void {
    if (!$yours) return; ?>
    <section class="cp-yours" aria-labelledby="cp-yours-title">
        <h2 id="cp-yours-title"><?= cp_h($w['yours']) ?></h2>
        <ul>
            <?php foreach ($yours as $row): ?>
                <li>
                    <span>
                        <b><?= cp_h((string)$row['title'] !== '' ? (string)$row['title'] : $w['untitled']) ?></b>
                        <?= cp_h($row['kind'] === 'edit' ? $w['kind_edit'] : $w['kind_new']) ?> · <?= cp_h($row['status'] === 'pending' ? $w['st_pending'] : $w['st_draft']) ?> · <?= cp_h(cp_relative_time((string)$row['updated_at'], $lang)) ?>
                    </span>
                    <?php if ($row['status'] === 'draft'): ?>
                        <a class="cp-btn" href="<?= cp_h(cp_url('write', ['bozza' => (int)$row['id']], $lang)) ?>"><?= cp_h($w['resume']) ?></a>
                    <?php else: ?>
                        <button class="cp-btn" type="button" data-cp-withdraw="<?= (int)$row['id'] ?>" title="<?= cp_h($w['withdraw_title']) ?>"><?= cp_h($w['withdraw']) ?></button>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php };
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php cp_render_head($pageTitle . ' - Cripsumpedia', $lead, $lang); ?>
    <meta name="robots" content="noindex">
    <?php if ($view === 'form' || $view === 'sent' || $yours): ?>
        <script>window.CpWrite = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
        <script src="<?= cripsum_theme_asset('/cripsumpedia/scrivi.js') ?>" defer></script>
    <?php endif; ?>
</head>

<body class="cp-body cp-page-write">
    <?php cp_render_topbar($lang, 'write'); ?>

    <main class="cp-main">
        <?php if ($view === 'install'): ?>
            <?php cp_render_install_notice($lang); ?>
        <?php else: ?>
            <?php cp_render_bar($lang, '', true); ?>

            <?php if ($view !== 'form'): ?>
                <section class="cp-notfound">
                    <?php if ($view === 'login'): ?>
                        <h1><?= cp_h($w['login_title']) ?></h1>
                        <p><?= cp_h(cp_cannot_write_text('login', $lang, $mysqli)) ?></p>
                        <div class="cp-notfound__actions">
                            <a class="cp-btn cp-btn--primary" href="/<?= cp_h($lang) ?>/accedi"><?= cp_h($w['login_btn']) ?></a>
                            <a class="cp-btn" href="<?= cp_h(cp_url('home', [], $lang)) ?>"><?= cp_h($w['back']) ?></a>
                        </div>
                    <?php elseif ($view === 'sent'): ?>
                        <h1><?= cp_h($w['sent_title']) ?></h1>
                        <p><?= cp_h($w['sent_text']) ?></p>
                        <div class="cp-notfound__actions">
                            <a class="cp-btn cp-btn--primary" href="<?= cp_h(cp_url('home', [], $lang)) ?>"><?= cp_h($w['back']) ?></a>
                            <a class="cp-btn" href="<?= cp_h(cp_url('write', [], $lang)) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i><?= cp_h($w['write_another']) ?></a>
                        </div>
                    <?php else: ?>
                        <h1><?= cp_h($view === 'gone' ? $w['gone_title'] : $w['cannot_title']) ?></h1>
                        <p><?= cp_h($message) ?></p>
                        <div class="cp-notfound__actions">
                            <a class="cp-btn cp-btn--primary" href="<?= cp_h(cp_url('home', [], $lang)) ?>"><?= cp_h($w['back']) ?></a>
                        </div>
                    <?php endif; ?>
                </section>
                <?php if ($view !== 'login') $renderYours($yours); ?>
            <?php else: ?>

            <form class="cp-write" data-cp-write novalidate autocomplete="off">
                <header class="cp-write__head">
                    <?php if ($mode === 'fix'): ?>
                        <a class="cp-crumb" href="<?= cp_h(cp_url('review', ['id' => (int)$fix['id']], $lang)) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><?= cp_h($w['queue']) ?></a>
                    <?php elseif ($entry): ?>
                        <a class="cp-crumb" href="<?= cp_h(cp_entry_url($entry, $lang)) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><?= cp_h(cp_i18n($entry, 'title', $lang)) ?></a>
                    <?php endif; ?>
                    <h1><?= cp_h($pageTitle) ?></h1>
                    <p><?= cp_h($lead) ?></p>
                    <?php if ($notice !== ''): ?>
                        <p class="cp-write__notice"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span><?= cp_h($notice) ?></span><?php if ($draft): ?> <button type="button" class="cp-linkish" data-cp-discard data-cp-restart><?= cp_h($w['start_over']) ?></button><?php endif; ?></p>
                    <?php endif; ?>
                    <details class="cp-rules">
                        <summary><?= cp_h($w['rules_link']) ?></summary>
                        <ul><?php foreach ($w['rules'] as $rule): ?><li><?= cp_h($rule) ?></li><?php endforeach; ?></ul>
                    </details>
                </header>

                <?php if ($showType): ?>
                    <fieldset class="cp-step">
                        <legend class="cp-label"><?= cp_h($w['step_type']) ?></legend>
                        <div class="cp-types">
                            <?php foreach ($w['types'] as $value => [$label, $hint]): ?>
                                <label class="cp-type">
                                    <input type="radio" name="entry_type" value="<?= cp_h($value) ?>"<?= $type === $value ? ' checked' : '' ?>>
                                    <i class="<?= cp_h(['person' => 'fa-solid fa-user', 'event' => 'fa-regular fa-calendar', 'meme' => 'fa-regular fa-face-laugh-squint'][$value]) ?>" aria-hidden="true"></i>
                                    <strong><?= cp_h($label) ?></strong>
                                    <span><?= cp_h($hint) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php else: ?>
                    <input type="hidden" name="entry_type" value="<?= cp_h($type) ?>">
                <?php endif; ?>

                <div class="cp-step">
                    <label class="cp-label" for="cp-title"><?= cp_h($w['title']) ?></label>
                    <input class="cp-input cp-input--big" id="cp-title" name="title" value="<?= cp_h($f['title'] ?? '') ?>" maxlength="<?= CP_LIMITS['title'] ?>" placeholder="<?= cp_h($w['title_ph'][$type]) ?>" required data-cp-field="title" aria-describedby="cp-title-help">
                    <p class="cp-help" id="cp-title-help" data-cp-title-help aria-live="polite"></p>
                </div>

                <div class="cp-step">
                    <label class="cp-label" for="cp-subtitle"><?= cp_h($w['subtitle']) ?> <small><?= cp_h($w['subtitle_small']) ?></small></label>
                    <div class="cp-count-wrap">
                        <input class="cp-input" id="cp-subtitle" name="subtitle" value="<?= cp_h($f['subtitle'] ?? '') ?>" maxlength="<?= CP_LIMITS['subtitle'] ?>" placeholder="<?= cp_h($w['subtitle_ph'][$type]) ?>" data-cp-field="subtitle" data-cp-count>
                        <span class="cp-count" aria-hidden="true"></span>
                    </div>
                </div>

                <div class="cp-step cp-step--two">
                    <div>
                        <label class="cp-label" for="cp-date"><span data-cp-when><?= cp_h($w['when'][$type]) ?></span> <small><?= cp_h($w['when_small']) ?></small></label>
                        <input class="cp-input" type="date" id="cp-date" name="real_date" value="<?= cp_h((string)($f['real_date'] ?? '')) ?>" max="<?= date('Y-m-d') ?>" data-cp-field="real_date">
                    </div>
                    <div>
                        <label class="cp-label" for="cp-lore"><?= cp_h($w['lore_date']) ?> <small><?= cp_h($w['when_small']) ?></small></label>
                        <input class="cp-input" id="cp-lore" name="lore_date" value="<?= cp_h($f['lore_date'] ?? '') ?>" maxlength="<?= CP_LIMITS['lore_date'] ?>" placeholder="<?= cp_h($w['lore_date_ph']) ?>" data-cp-field="lore_date">
                    </div>
                </div>

                <div class="cp-step">
                    <span class="cp-label" id="cp-photo-label"><?= cp_h($w['photo']) ?> <small><?= cp_h($w['photo_small']) ?></small></span>
                    <input type="hidden" name="image_url" value="<?= cp_h($image) ?>" data-cp-field="image_url">
                    <div class="cp-photo<?= $image !== '' ? ' has-photo' : '' ?>" data-cp-photo>
                        <div class="cp-photo__img" data-cp-photo-img>
                            <?php if ($image !== ''): ?><img src="<?= cp_h(cp_asset_url($image)) ?>" alt=""><?php else: ?><i class="fa-regular fa-image" aria-hidden="true"></i><?php endif; ?>
                        </div>
                        <div class="cp-photo__drop">
                            <?php if ($canUpload): ?>
                                <p><?= cp_h($w['photo_help']) ?></p>
                                <div class="cp-photo__actions">
                                    <label class="cp-btn">
                                        <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-cp-photo-input hidden>
                                        <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i><span data-cp-photo-choose><?= cp_h($image !== '' ? $w['photo_change'] : $w['photo_choose']) ?></span>
                                    </label>
                                    <button class="cp-btn" type="button" data-cp-photo-remove<?= $image === '' ? ' hidden' : '' ?>><?= cp_h($w['photo_remove']) ?></button>
                                </div>
                            <?php else: ?>
                                <p><?= cp_h($w['photo_staff_only']) ?></p>
                            <?php endif; ?>
                            <p class="cp-help" data-cp-photo-status aria-live="polite"></p>
                        </div>
                    </div>
                </div>

                <div class="cp-step">
                    <label class="cp-label" for="cp-text"><?= cp_h($w['text']) ?></label>
                    <div class="cp-editor" data-cp-editor>
                        <div class="cp-editor__bar" role="toolbar" aria-label="<?= cp_h($w['text']) ?>">
                            <button type="button" data-cp-tool="bold" title="<?= cp_h($w['tb_bold']) ?>" aria-label="<?= cp_h($w['tb_bold']) ?>"><b>B</b></button>
                            <button type="button" data-cp-tool="italic" title="<?= cp_h($w['tb_italic']) ?>" aria-label="<?= cp_h($w['tb_italic']) ?>"><i>I</i></button>
                            <button type="button" data-cp-tool="heading" title="<?= cp_h($w['tb_heading']) ?>" aria-label="<?= cp_h($w['tb_heading']) ?>">H</button>
                            <span class="sep" aria-hidden="true"></span>
                            <button type="button" data-cp-tool="link" title="<?= cp_h($w['tb_link']) ?>" aria-label="<?= cp_h($w['tb_link']) ?>">[[ ]]</button>
                            <button type="button" data-cp-tool="quote" class="cp-hide-phone" title="<?= cp_h($w['tb_quote']) ?>" aria-label="<?= cp_h($w['tb_quote']) ?>"><i class="fa-solid fa-quote-left" aria-hidden="true"></i></button>
                            <button type="button" data-cp-tool="spoiler" class="cp-hide-phone" title="<?= cp_h($w['tb_spoiler']) ?>" aria-label="<?= cp_h($w['tb_spoiler']) ?>"><i class="fa-regular fa-eye-slash" aria-hidden="true"></i></button>
                            <?php if ($canUpload): ?>
                                <button type="button" data-cp-tool="image" title="<?= cp_h($w['tb_image']) ?>" aria-label="<?= cp_h($w['tb_image']) ?>"><i class="fa-regular fa-image" aria-hidden="true"></i></button>
                                <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-cp-inline-photo hidden>
                            <?php endif; ?>
                            <span class="cp-editor__tabs" role="tablist">
                                <button type="button" role="tab" class="is-on" aria-selected="true" data-cp-tab="write"><?= cp_h($w['tab_write']) ?></button>
                                <button type="button" role="tab" aria-selected="false" data-cp-tab="preview"><?= cp_h($w['tab_preview']) ?></button>
                            </span>
                        </div>
                        <div class="cp-editor__body">
                            <textarea class="cp-editor__text" id="cp-text" name="content_md" maxlength="<?= CP_LIMITS['content'] ?>" placeholder="<?= cp_h($w['text_ph']) ?>" data-cp-field="content_md" spellcheck="true"><?= cp_h($f['content_md'] ?? '') ?></textarea>
                            <div class="cp-suggest" data-cp-suggest role="listbox" hidden></div>
                            <div class="cp-editor__preview cp-prose" data-cp-preview hidden></div>
                        </div>
                    </div>
                    <p class="cp-help"><?= cp_h($w['text_help']) ?></p>
                </div>

                <div class="cp-folds">
                    <details class="cp-fold"<?= ($data['aliases'] || $data['tags'] || $data['relations'] || $profileUsername !== '') ? ' open' : '' ?>>
                        <summary><span><strong><?= cp_h($w['fold_links']) ?></strong><span><?= cp_h($w['fold_links_text']) ?></span></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="cp-fold__body">
                            <div>
                                <label class="cp-label" for="cp-aliases"><?= cp_h($w['aliases']) ?></label>
                                <div class="cp-pills" data-cp-pills="aliases" data-max="<?= CP_LIMITS['aliases'] ?>">
                                    <?php foreach ($data['aliases'] as $alias): ?>
                                        <span class="cp-pill" data-it="<?= cp_h($alias['it']) ?>" data-en="<?= cp_h($alias['en'] ?? '') ?>"><?= cp_h($alias['it']) ?><button type="button" aria-label="<?= cp_h($w['remove']) ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></span>
                                    <?php endforeach; ?>
                                    <input id="cp-aliases" maxlength="<?= CP_LIMITS['alias'] ?>" placeholder="<?= cp_h($w['add_ph']) ?>">
                                </div>
                            </div>
                            <?php if ($staff): ?>
                                <div data-cp-only-person<?= $type !== 'person' ? ' hidden' : '' ?>>
                                    <label class="cp-label" for="cp-profile"><?= cp_h($w['profile']) ?></label>
                                    <div class="cp-at"><span aria-hidden="true">@</span><input class="cp-input" id="cp-profile" name="profile_username" value="<?= cp_h($profileUsername) ?>" maxlength="60" placeholder="<?= cp_h($w['profile_ph']) ?>" data-cp-field="profile_username"></div>
                                    <p class="cp-help"><?= cp_h($w['profile_help']) ?></p>
                                </div>
                            <?php endif; ?>
                            <div class="wide">
                                <label class="cp-label" for="cp-rel-search"><?= cp_h($w['relations']) ?></label>
                                <div class="cp-rels" data-cp-relations data-max="<?= CP_LIMITS['relations'] ?>">
                                    <?php foreach ($data['relations'] as $relation):
                                        $target = $targets[(int)$relation['target_id']] ?? null;
                                        if (!$target) continue; ?>
                                        <div class="cp-rel" data-target="<?= (int)$relation['target_id'] ?>" data-label="<?= cp_h($relation['label'] ?? '') ?>" data-label-en="<?= cp_h($relation['label_en'] ?? '') ?>" data-weight="<?= (int)($relation['weight'] ?? 50) ?>" data-both="<?= (int)($relation['bidirectional'] ?? 1) ?>">
                                            <?php cp_render_media($target, 'cp-rel__img', $lang); ?>
                                            <select aria-label="<?= cp_h($w['relations']) ?>">
                                                <?php foreach (cp_ui_strings($lang)['rel'] as $value => $label): ?>
                                                    <option value="<?= cp_h($value) ?>"<?= ($relation['relation_type'] ?? 'related') === $value ? ' selected' : '' ?>><?= cp_h(($relation['label'] ?? '') !== '' && ($relation['relation_type'] ?? '') === $value ? $relation['label'] : $label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <b><?= cp_h(cp_i18n($target, 'title', $lang)) ?></b>
                                            <button type="button" class="cp-rel__x" aria-label="<?= cp_h($w['remove']) ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="cp-rels__find">
                                        <i class="fa-solid fa-link" aria-hidden="true"></i>
                                        <input id="cp-rel-search" placeholder="<?= cp_h($w['relations_ph']) ?>" data-cp-rel-search>
                                        <div class="cp-suggest cp-suggest--below" data-cp-rel-suggest role="listbox" hidden></div>
                                    </div>
                                </div>
                            </div>
                            <div class="wide">
                                <label class="cp-label" for="cp-tags"><?= cp_h($w['tags']) ?></label>
                                <div class="cp-pills" data-cp-pills="tags" data-max="<?= CP_LIMITS['tags'] ?>">
                                    <?php foreach ($data['tags'] as $tag): ?>
                                        <span class="cp-pill" data-it="<?= cp_h($tag['it']) ?>" data-en="<?= cp_h($tag['en'] ?? '') ?>"><?= cp_h($tag['it']) ?><button type="button" aria-label="<?= cp_h($w['remove']) ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></span>
                                    <?php endforeach; ?>
                                    <input id="cp-tags" maxlength="<?= CP_LIMITS['tag'] ?>" placeholder="<?= cp_h($w['tags_ph']) ?>">
                                </div>
                            </div>
                        </div>
                    </details>

                    <details class="cp-fold"<?= $data['quotes'] ? ' open' : '' ?>>
                        <summary><span><strong><?= cp_h($w['fold_quotes']) ?></strong><span><?= cp_h($w['fold_quotes_text']) ?></span></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="cp-fold__body cp-fold__body--one">
                            <div class="cp-quotes-edit" data-cp-quotes data-max="<?= CP_LIMITS['quotes'] ?>">
                                <?php foreach ($data['quotes'] as $quote): ?>
                                    <div class="cp-qrow" data-text-en="<?= cp_h($quote['text_en'] ?? '') ?>" data-speaker-en="<?= cp_h($quote['speaker_en'] ?? '') ?>" data-featured="<?= (int)($quote['featured'] ?? 0) ?>">
                                        <input class="cp-input" value="<?= cp_h($quote['text']) ?>" maxlength="<?= CP_LIMITS['quote'] ?>" placeholder="<?= cp_h($w['quote_ph']) ?>" aria-label="<?= cp_h($w['quote_ph']) ?>" data-q="text">
                                        <input class="cp-input" value="<?= cp_h($quote['speaker'] ?? '') ?>" maxlength="<?= CP_LIMITS['speaker'] ?>" placeholder="<?= cp_h($w['quote_who_ph']) ?>" aria-label="<?= cp_h($w['quote_who_ph']) ?>" data-q="speaker">
                                        <button type="button" class="cp-icon" data-cp-quote-remove aria-label="<?= cp_h($w['remove']) ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="cp-btn" data-cp-quote-add><i class="fa-solid fa-plus" aria-hidden="true"></i><?= cp_h($w['add_quote']) ?></button>
                        </div>
                    </details>

                    <details class="cp-fold"<?= trim(($f['title_en'] ?? '') . ($f['subtitle_en'] ?? '') . ($f['content_md_en'] ?? '')) !== '' ? ' open' : '' ?>>
                        <summary><span><strong><?= cp_h($w['fold_en']) ?></strong><span><?= cp_h($w['fold_en_text']) ?></span></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="cp-fold__body cp-fold__body--one">
                            <div>
                                <label class="cp-label" for="cp-title-en"><?= cp_h($w['title_en']) ?></label>
                                <input class="cp-input" id="cp-title-en" name="title_en" lang="en" value="<?= cp_h($f['title_en'] ?? '') ?>" maxlength="<?= CP_LIMITS['title'] ?>" data-cp-field="title_en">
                            </div>
                            <div>
                                <label class="cp-label" for="cp-subtitle-en"><?= cp_h($w['subtitle_en']) ?></label>
                                <input class="cp-input" id="cp-subtitle-en" name="subtitle_en" lang="en" value="<?= cp_h($f['subtitle_en'] ?? '') ?>" maxlength="<?= CP_LIMITS['subtitle'] ?>" data-cp-field="subtitle_en">
                            </div>
                            <div>
                                <label class="cp-label" for="cp-text-en"><?= cp_h($w['text_en']) ?></label>
                                <textarea class="cp-input cp-input--area" id="cp-text-en" name="content_md_en" lang="en" maxlength="<?= CP_LIMITS['content'] ?>" data-cp-field="content_md_en"><?= cp_h($f['content_md_en'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </details>

                    <?php if ($staff): ?>
                        <details class="cp-fold">
                            <summary><span><strong><?= cp_h($w['fold_staff']) ?></strong><span><?= cp_h($w['fold_staff_text']) ?></span></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                            <div class="cp-fold__body">
                                <fieldset>
                                    <legend class="cp-label"><?= cp_h($w['status']) ?></legend>
                                    <label class="cp-check"><input type="radio" name="status" value="published"<?= ($f['status'] ?? 'published') !== 'draft' ? ' checked' : '' ?>><?= cp_h($w['status_published']) ?></label>
                                    <label class="cp-check"><input type="radio" name="status" value="draft"<?= ($f['status'] ?? '') === 'draft' ? ' checked' : '' ?>><?= cp_h($w['status_draft']) ?></label>
                                </fieldset>
                                <div>
                                    <label class="cp-label" for="cp-canon"><?= cp_h($w['canon']) ?></label>
                                    <select class="cp-input" id="cp-canon" name="canonical_status">
                                        <?php foreach ($w['canon_values'] as $value => $label): ?>
                                            <option value="<?= cp_h($value) ?>"<?= ($f['canonical_status'] ?? 'canon') === $value ? ' selected' : '' ?>><?= cp_h($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="cp-check"><input type="checkbox" name="featured" value="1"<?= !empty($f['featured']) ? ' checked' : '' ?>><?= cp_h($w['featured']) ?></label>
                                </div>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>

                <?php if ($mode !== 'new'): ?>
                    <div class="cp-step cp-step--last">
                        <label class="cp-label" for="cp-summary"><?= cp_h($w['summary']) ?> <small><?= cp_h($w['summary_small']) ?></small></label>
                        <input class="cp-input" id="cp-summary" name="summary" value="<?= cp_h($summary) ?>" maxlength="<?= CP_LIMITS['summary'] ?>" placeholder="<?= cp_h($w['summary_ph']) ?>" data-cp-field="summary">
                    </div>
                <?php endif; ?>

                <div class="cp-sendbar">
                    <?php if ($mode !== 'fix'): ?>
                        <span class="cp-saved" data-cp-saved aria-live="polite"><i class="fa-solid fa-cloud" aria-hidden="true"></i><span><?= cp_h($draft ? cp_w('saved', $lang, date('H:i', strtotime((string)$draft['updated_at']) ?: time())) : $w['autosave']) ?></span></span>
                    <?php endif; ?>
                    <p>
                        <?php if ($mode === 'fix'): ?><?= cp_h($w['fix_note']) ?>
                        <?php elseif ($staff): ?><?= cp_h($w['staff_note']) ?>
                        <?php else: ?><?= cp_h($w['send_note']) ?> <b><?= cp_h($w['send_note_b']) ?></b><?php endif; ?>
                    </p>
                    <button class="cp-btn" type="button" data-cp-preview-btn><?= cp_h($w['btn_preview']) ?></button>
                    <button class="cp-btn cp-btn--primary" type="submit" data-cp-submit><?= cp_h($mode === 'fix' ? $w['btn_approve'] : ($staff ? $w['btn_publish'] : $w['btn_send'])) ?></button>
                </div>
                <?php if ($mode !== 'fix'): ?>
                    <div class="cp-write__foot" data-cp-discard-row<?= $draft ? '' : ' hidden' ?>>
                        <button class="cp-linkish cp-sendbar__discard" type="button" data-cp-discard><?= cp_h($w['btn_discard']) ?></button>
                    </div>
                <?php endif; ?>
            </form>

            <?php $renderYours($yours); ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php cp_render_footer($lang); ?>
</body>

</html>
