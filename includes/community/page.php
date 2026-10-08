<?php
/**
 * /it/shitpost e /it/rimasti (e le gemelle in inglese): la stessa pagina su
 * due sezioni. Shitpost è un feed a colonne con le reazioni; Top Rimasti è
 * una classifica con podio, voti e albo d'oro.
 *
 * La prima pagina di post sta già nell'HTML (#cmData): community.js la
 * disegna senza una richiesta in più e da lì in avanti carica scorrendo.
 * Con ?post=ID il post si apre da solo: è l'indirizzo che usano Discord, le
 * condivisioni e le segnalazioni.
 *
 * Variabili attese: $cmLang ('it' | 'en'), $cmType ('shitpost' | 'rimasto'),
 * $mysqli. Attenzione ai nomi: head-import.php e la navbar usano $t e $lang.
 */

// Aperto direttamente, senza passare da it/ o en/: non c'è niente da mostrare.
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/community.php';
require_once __DIR__ . '/strings.php';
require_once __DIR__ . '/../edits/strings.php';
require_once __DIR__ . '/../cripsum_og.php';
require_once __DIR__ . '/../theme.php';

@$mysqli->set_charset('utf8mb4');
if (function_exists('checkBan') && function_exists('isLoggedIn') && isLoggedIn()) {
    checkBan($mysqli);
}

$cmLang = cm_lang(($cmLang ?? 'it') === 'en' ? 'en' : 'it');
$cmType = cv2_normalize_type((string)($cmType ?? 'shitpost'));
$cmRimasto = $cmType === 'rimasto';

$cmUser = cv2_current_user($mysqli);
$cmAdmin = cv2_is_admin($cmUser);
$cmS = cm_strings($cmLang, $cmType);
$cmSchema = cm_schema($mysqli, $cmType);
$cmSettings = cm_settings($mysqli);
$cmCanPost = $cmAdmin || !empty($cmSettings[$cmType . '_aperto']);
$cmBase = '/' . $cmLang . '/' . ($cmRimasto ? 'rimasti' : 'shitpost');

// Anteprima per i link incollati altrove (titolo e immagine del post aperto).
$ogMeta = cripsum_og_content($mysqli, $cmType);

// ── Stato di partenza, dall'indirizzo ───────────────────────────────────────
$cmSorts = $cmRimasto ? ['top', 'recent'] : ['recent', 'trending', 'top', 'comments'];
$cmSort = (string)($_GET['sort'] ?? '');
if (!in_array($cmSort, $cmSorts, true)) {
    $cmSort = $cmSorts[0];
}
$cmPeriod = $cmRimasto && in_array((string)($_GET['period'] ?? ''), ['month', 'week'], true) ? (string)$_GET['period'] : 'all';
$cmTag = cm_tag_clean((string)($_GET['tag'] ?? ''));
$cmQuery = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80, 'UTF-8');
$cmRequested = (int)($_GET['post'] ?? 0);

$cmFeed = ['posts' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
$cmTags = [];
$cmHall = [];
$cmOpen = null;
$cmPendingMine = 0;
$cmFailed = false;

try {
    $cmFeed = cm_feed($mysqli, $cmType, ['sort' => $cmSort, 'period' => $cmPeriod, 'q' => $cmQuery, 'tag' => $cmTag], $cmUser);
    $cmTags = cm_popular_tags($mysqli, $cmType);
    if ($cmRimasto) {
        $cmHall = cm_hall_of_fame($mysqli);
    }
    if ($cmRequested > 0) {
        $cmOpen = cm_feed($mysqli, $cmType, ['post' => $cmRequested], $cmUser)['posts'][0] ?? null;
    }
    if ($cmUser && $cmSchema['ready']) {
        $cmPendingMine = cm_count($mysqli, 'SELECT COUNT(*) FROM `' . cv2_meta($cmType)['table'] . '` WHERE id_utente = ? AND approvato = 0', 'i', [(int)$cmUser['id']]);
    }
} catch (Throwable $e) {
    error_log('[community] pagina: ' . $e->getMessage());
    $cmFailed = true;
}

$cmData = [
    'lang' => $cmLang,
    'type' => $cmType,
    'base' => $cmBase,
    'login' => '/' . $cmLang . '/accedi',
    'csrf' => cv2_csrf_token(),
    'user' => $cmUser ? ['id' => (int)$cmUser['id'], 'username' => (string)$cmUser['username'], 'admin' => $cmAdmin] : null,
    'sort' => $cmSort,
    'period' => $cmPeriod,
    'tag' => $cmTag,
    'q' => $cmQuery,
    'pageSize' => CM_PAGE_SIZE,
    'reactions' => CM_REACTIONS,
    'canPost' => $cmCanPost,
    'autoApprove' => $cmUser ? cm_auto_approve($mysqli, $cmUser) : false,
    'drafts' => $cmSchema['extra'],
    'replies' => $cmSchema['replies'],
    'maxMedia' => cm_max_media($mysqli, $cmType),
    'limits' => ['image' => 8 * 1024 * 1024, 'video' => 20 * 1024 * 1024, 'title' => 120, 'text' => 2000, 'comment' => CM_COMMENT_LENGTH, 'tags' => CM_MAX_TAGS, 'tag' => CM_TAG_LENGTH],
    'pendingMine' => $cmPendingMine,
    'feed' => ['posts' => $cmFeed['posts'], 'pages' => $cmFeed['pages'], 'total' => $cmFeed['total']],
    'tags' => $cmTags,
    'hall' => $cmHall,
    'post' => $cmOpen,
    'postMissing' => $cmRequested > 0 && $cmOpen === null,
    'failed' => $cmFailed,
    'now' => time(),
    'strings' => $cmS,
    // I video usano il player degli edit (edits-player.js), con i suoi testi.
    'player' => edits_strings($cmLang)['player'] ?? [],
];

$cmTabs = $cmRimasto
    ? [['top', 'all', 'rank_all'], ['top', 'month', 'rank_month'], ['top', 'week', 'rank_week'], ['recent', 'all', 'rank_new']]
    : [['recent', 'all', 'sort_recent'], ['trending', 'all', 'sort_trending'], ['top', 'all', 'sort_top'], ['comments', 'all', 'sort_comments']];
?>
<!DOCTYPE html>
<html lang="<?php echo cv2_h($cmLang); ?>"<?php echo cripsum_theme_html_attr(); ?>>

<head>
    <?php include __DIR__ . '/../head-import.php'; ?>
    <title>Cripsum™ - <?php echo cv2_h($cmS['title']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo cv2_h(cripsum_asset('/assets/edits/edits-player.css')); ?>">
    <link rel="stylesheet" href="<?php echo cv2_h(cripsum_asset('/assets/community/community.css')); ?>">
    <?php cripsum_theme_head('community', 'edits-player'); ?>
    <script src="<?php echo cv2_h(cripsum_asset('/assets/edits/edits-player.js')); ?>" defer></script>
    <script src="<?php echo cv2_h(cripsum_asset('/assets/community/community.js')); ?>" defer></script>
</head>

<body class="content-v2-body cm-page cm-page--<?php echo cv2_h($cmType); ?>"
    data-content-type="<?php echo cv2_h($cmType); ?>"
    data-csrf="<?php echo cv2_h($cmData['csrf']); ?>">
    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="cm-bg" aria-hidden="true">
        <span class="cm-orb cm-orb--one"></span>
        <span class="cm-orb cm-orb--two"></span>
    </div>

    <main class="cm-shell" id="cmApp">
        <header class="cm-hero">
            <div class="cm-hero__text">
                <h1><?php echo cv2_h($cmS['title']); ?></h1>
                <p><?php echo cv2_h($cmS['subtitle']); ?></p>
            </div>
            <?php if (!$cmUser): ?>
                <a class="cm-btn cm-btn--primary" href="/<?php echo cv2_h($cmLang); ?>/accedi"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> <?php echo cv2_h($cmS['login_to_post']); ?></a>
            <?php elseif ($cmCanPost): ?>
                <button type="button" class="cm-btn cm-btn--primary" data-cm-new><i class="fa-solid fa-plus" aria-hidden="true"></i> <?php echo cv2_h($cmS['new']); ?></button>
            <?php else: ?>
                <span class="cm-btn cm-btn--off"><i class="fa-solid fa-lock" aria-hidden="true"></i> <?php echo cv2_h($cmS['closed']); ?></span>
            <?php endif; ?>
        </header>

        <div class="cm-bar" data-cm-bar>
            <div class="cm-seg" role="tablist" aria-label="<?php echo cv2_h($cmS['sort_label']); ?>" data-cm-sorts>
                <?php foreach ($cmTabs as [$tabSort, $tabPeriod, $tabLabel]): ?>
                    <?php $tabActive = $tabSort === $cmSort && $tabPeriod === $cmPeriod; ?>
                    <button type="button" role="tab" class="cm-seg__tab<?php echo $tabActive ? ' is-active' : ''; ?>" aria-selected="<?php echo $tabActive ? 'true' : 'false'; ?>" data-sort="<?php echo cv2_h($tabSort); ?>" data-period="<?php echo cv2_h($tabPeriod); ?>"><?php echo cv2_h($cmS[$tabLabel]); ?></button>
                <?php endforeach; ?>
            </div>

            <label class="cm-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" data-cm-search value="<?php echo cv2_h($cmQuery); ?>" placeholder="<?php echo cv2_h($cmS['search']); ?>" aria-label="<?php echo cv2_h($cmS['search']); ?>" maxlength="80" autocomplete="off" enterkeyhint="search">
                <kbd aria-hidden="true">/</kbd>
                <button type="button" class="cm-search__clear" data-cm-search-clear aria-label="<?php echo cv2_h($cmS['search_clear']); ?>" hidden><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </label>

            <?php if ($cmUser): ?>
                <div class="cm-bar__filters">
                    <button type="button" class="cm-pill" data-cm-filter="saved" aria-pressed="false"><i class="fa-solid fa-bookmark" aria-hidden="true"></i> <span><?php echo cv2_h($cmS['saved']); ?></span></button>
                    <button type="button" class="cm-pill" data-cm-filter="mine" aria-pressed="false"><i class="fa-solid fa-user" aria-hidden="true"></i> <span><?php echo cv2_h($cmS['mine']); ?></span><b class="cm-pill__dot" data-cm-mine-dot <?php echo $cmPendingMine > 0 ? '' : 'hidden'; ?>><?php echo (int)$cmPendingMine; ?></b></button>
                    <?php if ($cmAdmin): ?>
                        <button type="button" class="cm-pill" data-cm-filter="pending" aria-pressed="false"><i class="fa-solid fa-clock" aria-hidden="true"></i> <span><?php echo cv2_h($cmS['pending_filter']); ?></span></button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="cm-tags" data-cm-tags hidden></div>

        <div class="cm-stream">
            <button type="button" class="cm-newpill" data-cm-newpill hidden></button>

            <?php if ($cmRimasto): ?>
                <section class="cm-podium" data-cm-podium hidden></section>
                <section class="cm-rank" data-cm-rank hidden></section>
            <?php endif; ?>

            <section class="cm-feed" data-cm-feed aria-live="polite"></section>
            <div class="cm-empty" data-cm-empty hidden></div>
            <div class="cm-sentinel" data-cm-sentinel aria-hidden="true"></div>
        </div>

        <?php if ($cmRimasto): ?>
            <section class="cm-hall" data-cm-hall hidden>
                <div class="cm-sect">
                    <h2><?php echo cv2_h($cmS['hall_title']); ?></h2>
                    <span><?php echo cv2_h($cmS['hall_text']); ?></span>
                </div>
                <div class="cm-hall__grid" data-cm-hall-grid></div>
            </section>
        <?php endif; ?>
    </main>

    <!-- Post aperto -->
    <dialog class="cm-viewer" data-cm-viewer aria-labelledby="cmViewerTitle">
        <div class="cm-viewer__panel" data-cm-viewer-panel>
            <div class="cm-stage" data-cm-stage>
                <div class="cm-stage__glow" data-cm-stage-glow aria-hidden="true"></div>
                <div class="cm-stage__media" data-cm-stage-media></div>
                <?php /* Le frecce sul media scorrono solo i file di questo post; da un post all'altro si va con la barra di fianco. */ ?>
                <button type="button" class="cm-stage__arrow cm-stage__arrow--prev" data-cm-media-step="-1" aria-label="<?php echo cv2_h($cmS['media_prev']); ?>" title="<?php echo cv2_h($cmS['media_prev']); ?> (←)" hidden><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                <button type="button" class="cm-stage__arrow cm-stage__arrow--next" data-cm-media-step="1" aria-label="<?php echo cv2_h($cmS['media_next']); ?>" title="<?php echo cv2_h($cmS['media_next']); ?> (→)" hidden><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                <span class="cm-stage__count" data-cm-stage-count hidden></span>
                <div class="cm-stage__dots" data-cm-stage-dots hidden></div>
                <div class="cm-stage__tools">
                    <button type="button" class="cm-icon cm-icon--dark" data-cm-zoom aria-label="<?php echo cv2_h($cmS['zoom']); ?>" title="<?php echo cv2_h($cmS['zoom']); ?>" aria-pressed="false"><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i></button>
                    <button type="button" class="cm-icon cm-icon--dark" data-cm-fullscreen aria-label="<?php echo cv2_h($cmS['fullscreen']); ?>" title="<?php echo cv2_h($cmS['fullscreen']); ?> (F)" aria-pressed="false"><i class="fa-solid fa-expand" aria-hidden="true"></i></button>
                </div>
                <button type="button" class="cm-icon cm-icon--dark cm-stage__close" data-cm-viewer-close aria-label="<?php echo cv2_h($cmS['close']); ?>"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
            </div>

            <div class="cm-side">
                <div class="cm-side__bar">
                    <button type="button" class="cm-navbtn" data-cm-post-step="-1" aria-label="<?php echo cv2_h($cmS['post_prev']); ?>" title="<?php echo cv2_h($cmS['post_prev']); ?> (↑)"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i> <span><?php echo cv2_h($cmS['prev']); ?></span></button>
                    <button type="button" class="cm-navbtn" data-cm-post-step="1" aria-label="<?php echo cv2_h($cmS['post_next']); ?>" title="<?php echo cv2_h($cmS['post_next']); ?> (↓)"><span><?php echo cv2_h($cmS['next']); ?></span> <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                    <span class="cm-side__bar-gap"></span>
                    <button type="button" class="cm-icon" data-cm-viewer-close aria-label="<?php echo cv2_h($cmS['close']); ?>" title="<?php echo cv2_h($cmS['close']); ?> (Esc)"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <div class="cm-side__scroll" data-cm-side-scroll>
                    <div class="cm-side__top" data-cm-side-top></div>
                    <div class="cm-comments" data-cm-comments></div>
                </div>
                <form class="cm-composer" data-cm-comment-form hidden>
                    <div class="cm-composer__reply" data-cm-reply-banner hidden>
                        <span data-cm-reply-label></span>
                        <button type="button" data-cm-reply-cancel aria-label="<?php echo cv2_h($cmS['cancel_reply']); ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                    <div class="cm-composer__row">
                        <?php if ($cmUser): ?>
                            <img class="cm-avatar" src="/includes/get_pfp.php?id=<?php echo (int)$cmUser['id']; ?>" alt="">
                        <?php endif; ?>
                        <label class="cm-composer__field">
                            <input type="text" data-cm-comment-input maxlength="<?php echo CM_COMMENT_LENGTH; ?>" placeholder="<?php echo cv2_h($cmS['comment_placeholder']); ?>" aria-label="<?php echo cv2_h($cmS['comment_placeholder']); ?>" autocomplete="off" enterkeyhint="send">
                            <small data-cm-comment-count>0/<?php echo CM_COMMENT_LENGTH; ?></small>
                        </label>
                        <button type="submit" class="cm-icon cm-icon--primary" aria-label="<?php echo cv2_h($cmS['comment_send']); ?>"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
                    </div>
                </form>
                <a class="cm-composer cm-composer--login" data-cm-comment-login href="/<?php echo cv2_h($cmLang); ?>/accedi" hidden><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> <?php echo cv2_h($cmS['comment_login']); ?></a>
                <p class="cm-composer cm-composer--note" data-cm-comment-note hidden><?php echo cv2_h($cmS['comment_pending']); ?></p>
            </div>
        </div>
    </dialog>

    <?php if ($cmUser): ?>
        <!-- Nuovo post / modifica -->
        <dialog class="cm-sheet" data-cm-editor aria-labelledby="cmEditorTitle">
            <form class="cm-sheet__panel" data-cm-editor-form novalidate>
                <div class="cm-sheet__head">
                    <div>
                        <strong id="cmEditorTitle" data-cm-editor-title><?php echo cv2_h($cmS['new']); ?></strong>
                        <small data-cm-editor-hint><?php echo cv2_h($cmS['composer_hint']); ?></small>
                    </div>
                    <button type="button" class="cm-icon" data-cm-editor-close aria-label="<?php echo cv2_h($cmS['close']); ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <div class="cm-progress" data-cm-progress aria-hidden="true"><b></b></div>

                <div class="cm-sheet__body">
                    <div class="cm-drop" data-cm-drop>
                        <input type="file" data-cm-file accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm" <?php echo $cmData['maxMedia'] > 1 ? 'multiple' : ''; ?> hidden>
                        <button type="button" class="cm-drop__zone" data-cm-pick>
                            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                            <strong><?php echo cv2_h($cmS['drop_title']); ?></strong>
                            <span><?php echo cv2_h($cmData['maxMedia'] > 1 ? sprintf($cmS['drop_text'], $cmData['maxMedia']) : $cmS['drop_text_one']); ?></span>
                        </button>
                        <div class="cm-drop__tiles" data-cm-tiles hidden></div>
                        <p class="cm-drop__note"><?php echo cv2_h($cmS['drop_limits']); ?><br><?php echo cv2_h($cmS['drop_privacy']); ?></p>
                    </div>

                    <div class="cm-fields">
                        <div class="cm-field">
                            <label for="cmFieldTitle"><?php echo cv2_h($cmS['field_title']); ?> <small data-cm-count="titolo">0/120</small></label>
                            <input class="cm-input" type="text" id="cmFieldTitle" name="titolo" maxlength="120" placeholder="<?php echo cv2_h($cmS['field_title_ph']); ?>" autocomplete="off">
                        </div>
                        <div class="cm-field">
                            <label for="cmFieldDesc"><?php echo cv2_h($cmS['field_description']); ?> <small><?php echo cv2_h($cmS['optional']); ?> · <span data-cm-count="descrizione">0/2000</span></small></label>
                            <textarea class="cm-input" id="cmFieldDesc" name="descrizione" maxlength="2000" rows="3" placeholder="<?php echo cv2_h($cmS['field_description_ph']); ?>"></textarea>
                        </div>
                        <?php if ($cmRimasto): ?>
                            <div class="cm-field">
                                <label for="cmFieldMotivation"><?php echo cv2_h($cmS['field_motivation']); ?> <small data-cm-count="motivazione">0/2000</small></label>
                                <textarea class="cm-input" id="cmFieldMotivation" name="motivazione" maxlength="2000" rows="3" placeholder="<?php echo cv2_h($cmS['field_motivation_ph']); ?>"></textarea>
                            </div>
                        <?php endif; ?>
                        <div class="cm-field">
                            <label for="cmFieldTags"><?php echo cv2_h($cmS['field_tags']); ?> <small><?php echo cv2_h($cmS['field_tags_hint']); ?></small></label>
                            <div class="cm-tagbox" data-cm-tagbox>
                                <input type="text" id="cmFieldTags" data-cm-tag-input maxlength="<?php echo CM_TAG_LENGTH; ?>" placeholder="<?php echo cv2_h($cmS['field_tags_ph']); ?>" autocomplete="off" enterkeyhint="done">
                            </div>
                            <div class="cm-tagbox__hints" data-cm-tag-hints hidden></div>
                        </div>
                        <label class="cm-switch">
                            <span>
                                <strong><?php echo cv2_h($cmS['field_spoiler']); ?></strong>
                                <small><?php echo cv2_h($cmS['field_spoiler_hint']); ?></small>
                            </span>
                            <input type="checkbox" name="is_spoiler" value="1">
                            <i aria-hidden="true"></i>
                        </label>
                    </div>
                </div>

                <div class="cm-sheet__foot">
                    <p data-cm-editor-note><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> <span></span></p>
                    <button type="button" class="cm-btn cm-btn--ghost" data-cm-editor-close><?php echo cv2_h($cmS['cancel']); ?></button>
                    <button type="submit" class="cm-btn cm-btn--primary" data-cm-editor-submit><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <span><?php echo cv2_h($cmS['publish']); ?></span></button>
                </div>
            </form>
        </dialog>

        <!-- Segnala -->
        <dialog class="cm-sheet cm-sheet--small" data-cm-report aria-labelledby="cmReportTitle">
            <form class="cm-sheet__panel" data-cm-report-form>
                <div class="cm-sheet__head">
                    <div>
                        <strong id="cmReportTitle"><?php echo cv2_h($cmS['report_title']); ?></strong>
                        <small><?php echo cv2_h($cmS['report_hint']); ?></small>
                    </div>
                    <button type="button" class="cm-icon" data-cm-dialog-close aria-label="<?php echo cv2_h($cmS['close']); ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <div class="cm-sheet__body cm-sheet__body--stack">
                    <div class="cm-choices" role="radiogroup" aria-labelledby="cmReportTitle">
                        <?php foreach (['nsfw', 'spam', 'hate', 'rules', 'other'] as $cmIndex => $cmReason): ?>
                            <label class="cm-choice">
                                <input type="radio" name="reason" value="<?php echo cv2_h($cmS['report_reason_' . $cmReason]); ?>" <?php echo $cmIndex === 0 ? 'checked' : ''; ?>>
                                <i aria-hidden="true"></i>
                                <span><?php echo cv2_h($cmS['report_reason_' . $cmReason]); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="cm-field">
                        <label for="cmReportDetail"><?php echo cv2_h($cmS['report_detail']); ?></label>
                        <textarea class="cm-input" id="cmReportDetail" name="detail" maxlength="300" rows="2" placeholder="<?php echo cv2_h($cmS['report_detail_ph']); ?>"></textarea>
                    </div>
                </div>
                <div class="cm-sheet__foot">
                    <span class="cm-sheet__spacer"></span>
                    <button type="button" class="cm-btn cm-btn--ghost" data-cm-dialog-close><?php echo cv2_h($cmS['cancel']); ?></button>
                    <button type="submit" class="cm-btn cm-btn--primary"><i class="fa-solid fa-flag" aria-hidden="true"></i> <?php echo cv2_h($cmS['report_send']); ?></button>
                </div>
            </form>
        </dialog>

        <!-- Conferme (elimina, nascondi, esci senza pubblicare) -->
        <dialog class="cm-sheet cm-sheet--small" data-cm-confirm aria-labelledby="cmConfirmTitle">
            <form class="cm-sheet__panel" data-cm-confirm-form>
                <div class="cm-sheet__head">
                    <div>
                        <strong id="cmConfirmTitle" data-cm-confirm-title></strong>
                        <small data-cm-confirm-text></small>
                    </div>
                </div>
                <div class="cm-sheet__body cm-sheet__body--stack" data-cm-confirm-extra hidden></div>
                <div class="cm-sheet__foot">
                    <span class="cm-sheet__spacer"></span>
                    <button type="button" class="cm-btn cm-btn--ghost" data-cm-confirm-no><?php echo cv2_h($cmS['cancel']); ?></button>
                    <button type="submit" class="cm-btn cm-btn--danger" data-cm-confirm-yes></button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>

    <div class="cm-menu" data-cm-menu popover="auto" role="menu"></div>
    <div class="cm-picker" data-cm-picker popover="auto" role="menu" aria-label="<?php echo cv2_h($cmS['reactions']); ?>"></div>
    <div class="cm-toast" data-cm-toast popover="manual" role="status" aria-live="polite"></div>

    <script type="application/json" id="cmData"><?php echo json_encode($cmData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?></script>

    <?php include __DIR__ . '/../scroll_indicator.php'; ?>
    <?php include __DIR__ . '/../' . ($cmLang === 'en' ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
