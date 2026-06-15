<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/content_v2_helpers.php';
require_once __DIR__ . '/../includes/cripsum_og.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (function_exists('checkBan') && function_exists('isLoggedIn') && isLoggedIn()) {
    checkBan($mysqli);
}

$currentUser = cv2_current_user($mysqli);
$isLogged = (bool)$currentUser;
$isAdmin = cv2_is_admin($currentUser);
$csrfToken = cv2_csrf_token();

$contentType = 'shitpost';
$pageTitle = 'Shitpost';
$pageSubtitle = 'Meme, GIF and community posts.';
$uploadTitle = 'New shitpost';
$needsMotivation = false;
$ogMeta = cripsum_og_content($mysqli, $contentType);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include __DIR__ . '/../includes/head-import.php'; ?>
    <?php cripsum_og_print($ogMeta); ?>
    <title>Cripsum™ - <?php echo cv2_h($pageTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/content-v2/content-v2.css?v=2.0.4">
    <script src="/assets/content-v2/content-v2.js?v=2.0.7" defer></script>
</head>

<body class="content-v2-body"
    data-content-type="<?php echo cv2_h($contentType); ?>"
    data-csrf="<?php echo cv2_h($csrfToken); ?>"
    data-logged="<?php echo $isLogged ? '1' : '0'; ?>"
    data-admin="<?php echo $isAdmin ? '1' : '0'; ?>"
    data-user-id="<?php echo (int)($currentUser['id'] ?? 0); ?>"
    data-needs-motivation="<?php echo $needsMotivation ? '1' : '0'; ?>"
    data-default-sort="<?php echo $contentType === 'rimasto' ? 'top' : 'recent'; ?>">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/impostazioni.php'; ?>

    <div class="cw-bg" aria-hidden="true">
        <span class="cw-orb cw-orb--one"></span>
        <span class="cw-orb cw-orb--two"></span>
        <span class="cw-grid"></span>
    </div>

    <main class="cw-shell">
        <header class="cw-hero">
            <div class="cw-hero__text">
                <h1><?php echo cv2_h($pageTitle); ?></h1>
                <p><?php echo cv2_h($pageSubtitle); ?></p>
            </div>
            <div class="cw-hero__actions">
                <?php if ($isLogged): ?>
                    <button type="button" class="cw-btn cw-btn--primary js-open-create">
                        <i class="fa-solid fa-plus"></i>
                        <span><?php echo cv2_h($uploadTitle); ?></span>
                    </button>
                <?php else: ?>
                    <a class="cw-btn cw-btn--primary" href="/it/accedi">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <span>Log in to post</span>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <section class="cw-toolbar">
            <label class="cw-search">
                <i class="fa-solid fa-search"></i>
                <input type="search" id="cwSearchInput" placeholder="Search the feed">
                <button type="button" class="cw-search-clear" id="cwClearSearch" aria-label="Clear search">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </label>

            <div class="cw-filters">
                <div class="cw-custom-select" data-cw-custom-select>
                    <select id="cwSortSelect" class="cw-select cw-native-select" tabindex="-1" aria-hidden="true">
                        <option value="recent" <?php echo $contentType !== 'rimasto' ? 'selected' : ''; ?>>Recent</option>
                        <option value="top" <?php echo $contentType === 'rimasto' ? 'selected' : ''; ?>>Most voted</option>
                        <option value="comments">Most commented</option>
                        <option value="views">Most viewed</option>
                    </select>

                    <button type="button" class="cw-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                        <span class="cw-select-current"><?php echo $contentType === 'rimasto' ? 'Most voted' : 'Recent'; ?></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>

                    <div class="cw-select-menu" role="listbox" aria-label="Ordina post">
                        <button type="button" data-value="recent">
                            <strong>Recent</strong>
                        </button>
                        <button type="button" data-value="top">
                            <strong>Most voted</strong>

                        </button>
                        <button type="button" data-value="comments">
                            <strong>Most commented</strong>

                        </button>
                        <button type="button" data-value="views">
                            <strong>Most viewed</strong>

                        </button>
                    </div>
                </div>

                <?php if ($isLogged): ?>
                    <button type="button" class="cw-filter-btn" id="cwSavedFilter">
                        <i class="fa-solid fa-bookmark"></i>
                        <span>Saved</span>
                    </button>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                    <div class="cw-custom-select" data-cw-custom-select>
                        <select id="cwStatusSelect" class="cw-select cw-native-select" tabindex="-1" aria-hidden="true">
                            <option value="approved" selected>Approved</option>
                            <option value="pending">Pending</option>
                            <option value="all">All</option>
                        </select>

                        <button type="button" class="cw-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                            <span class="cw-select-current">Approved</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>

                        <div class="cw-select-menu" role="listbox" aria-label="Post status">
                            <button type="button" data-value="approved">
                                <strong>Approved</strong>
                                <span>OK</span>
                            </button>
                            <button type="button" data-value="pending">
                                <strong>Pending</strong>
                                <span>Wait</span>
                            </button>
                            <button type="button" data-value="all">
                                <strong>All</strong>
                                <span>All</span>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="cwStats" class="cw-stats" hidden></section>

        <section id="cwFeed" class="cw-feed" aria-live="polite"></section>

        <div id="cwEmpty" class="cw-empty" hidden>
            <i class="fa-solid fa-face-grin-squint"></i>
            <strong>No posts to display</strong>
            <span>When there is something approved, it will appear here.</span>
            <?php if ($isLogged): ?>
                <button type="button" class="cw-btn cw-btn--primary js-open-create">Post now</button>
            <?php endif; ?>
        </div>

        <div id="cwLoader" class="cw-loader">
            <span></span><span></span><span></span>
        </div>

        <button type="button" id="cwLoadMore" class="cw-btn cw-btn--ghost" hidden>
            <i class="fa-solid fa-chevron-down"></i>
            <span>Load more</span>
        </button>
    </main>

    <div id="cwCreateModal" class="cw-modal" aria-hidden="true">
        <div class="cw-modal__panel" role="dialog" aria-modal="true" aria-labelledby="cwCreateTitle">
            <div class="cw-modal__head">
                <div>
                    <strong id="cwCreateTitle"><?php echo cv2_h($uploadTitle); ?></strong>
                    <span>The post will be reviewed before appearing.</span>
                </div>
                <button type="button" class="cw-icon-btn js-close-modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form id="cwCreateForm" class="cw-form">
                <div class="cw-field">
                    <label>Title</label>
                    <input name="titolo" maxlength="120" required placeholder="Short title">
                </div>

                <div class="cw-field">
                    <label>Description</label>
                    <textarea name="descrizione" maxlength="2000" rows="4" placeholder="Brief context"></textarea>
                </div>

                <?php if ($needsMotivation): ?>
                    <div class="cw-field">
                        <label>Reason</label>
                        <textarea name="motivazione" maxlength="2000" rows="3" placeholder="Why does it deserve to be here?"></textarea>
                    </div>
                <?php endif; ?>

                <div class="cw-form-grid">
                    <div class="cw-field">
                        <label>Tag</label>
                        <input name="tag" maxlength="40" placeholder="e.g. meme, gaming">
                    </div>
                    <label class="cw-check">
                        <input type="checkbox" name="is_spoiler" value="1">
                        <span>Mark as spoiler</span>
                    </label>
                </div>

                <div class="cw-dropzone" id="cwDropzone">
                    <input type="file" name="media" id="cwMediaInput" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm" required>
                    <div id="cwPreview" class="cw-preview">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <strong>Upload image, GIF or short video</strong>
                        <span>Images/GIF max 8MB, video max 20MB.</span>
                    </div>
                </div>

                <div class="cw-modal__footer">
                    <button type="button" class="cw-btn cw-btn--ghost js-close-modal">Cancel</button>
                    <button type="submit" class="cw-btn cw-btn--primary">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Send</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="cwTextModal" class="cw-modal" aria-hidden="true">
        <div class="cw-modal__panel" role="dialog" aria-modal="true">
            <div class="cw-modal__head">
                <div>
                    <strong id="cwTextTitle">Edit</strong>
                    <span>You can only edit text and tags.</span>
                </div>
                <button type="button" class="cw-icon-btn js-close-text-modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="cwTextForm" class="cw-form"></form>
        </div>
    </div>


    <div id="cwPostModal" class="cw-modal cw-post-modal" aria-hidden="true">
        <div class="cw-modal__panel cw-post-modal__panel" role="dialog" aria-modal="true">
            <button type="button" class="cw-icon-btn cw-post-modal__close js-close-post-modal" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div id="cwPostModalBody"></div>
        </div>
    </div>

    <div id="cwToast" class="cw-toast"></div>

    <?php include '../includes/footer-en.php'; ?>

    <script>
        window.__CRIPSUM_BOOTSTRAP_FALLBACK__ = true;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>