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
$pageSubtitle = 'Meme, GIF e post della community.';
$uploadTitle = 'Nuovo shitpost';
$needsMotivation = false;
$ogMeta = cripsum_og_content($mysqli, $contentType);
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include __DIR__ . '/../includes/head-import.php'; ?>
    <?php cripsum_og_print($ogMeta); ?>
    <title>Cripsum™ - <?php echo cv2_h($pageTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/content-v2/content-v2.css?v=2.0.2-og-previews">
    <script src="/assets/content-v2/content-v2.js?v=2.0.2-og-previews" defer></script>
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
                        <i class="fas fa-plus"></i>
                        <span><?php echo cv2_h($uploadTitle); ?></span>
                    </button>
                <?php else: ?>
                    <a class="cw-btn cw-btn--primary" href="/it/accedi">
                        <i class="fas fa-right-to-bracket"></i>
                        <span>Accedi per pubblicare</span>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <section class="cw-toolbar">
            <label class="cw-search">
                <i class="fas fa-search"></i>
                <input type="search" id="cwSearchInput" placeholder="Cerca nel feed">
                <button type="button" class="cw-search-clear" id="cwClearSearch" aria-label="Pulisci ricerca">
                    <i class="fas fa-xmark"></i>
                </button>
            </label>

            <div class="cw-filters">
                <select id="cwSortSelect" class="cw-select">
                    <option value="recent">Recenti</option>
                    <option value="top">Più votati</option>
                    <option value="comments">Più commentati</option>
                    <option value="views">Più visti</option>
                </select>

                <?php if ($isLogged): ?>
                    <button type="button" class="cw-filter-btn" id="cwSavedFilter">
                        <i class="fas fa-bookmark"></i>
                        <span>Salvati</span>
                    </button>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                    <select id="cwStatusSelect" class="cw-select">
                        <option value="approved">Approvati</option>
                        <option value="pending">In attesa</option>
                        <option value="all">Tutti</option>
                    </select>
                <?php endif; ?>
            </div>
        </section>

        <section id="cwStats" class="cw-stats" hidden></section>

        <section id="cwFeed" class="cw-feed" aria-live="polite"></section>

        <div id="cwEmpty" class="cw-empty" hidden>
            <i class="fas fa-face-grin-squint"></i>
            <strong>Nessun post da mostrare</strong>
            <span>Quando ci sarà qualcosa di approvato, apparirà qui.</span>
            <?php if ($isLogged): ?>
                <button type="button" class="cw-btn cw-btn--primary js-open-create">Pubblica ora</button>
            <?php endif; ?>
        </div>

        <div id="cwLoader" class="cw-loader">
            <span></span><span></span><span></span>
        </div>

        <button type="button" id="cwLoadMore" class="cw-btn cw-btn--ghost" hidden>
            <i class="fas fa-chevron-down"></i>
            <span>Carica altro</span>
        </button>
    </main>

    <div id="cwCreateModal" class="cw-modal" aria-hidden="true">
        <div class="cw-modal__panel" role="dialog" aria-modal="true" aria-labelledby="cwCreateTitle">
            <div class="cw-modal__head">
                <div>
                    <strong id="cwCreateTitle"><?php echo cv2_h($uploadTitle); ?></strong>
                    <span>Se serve, verrà controllato prima di apparire.</span>
                </div>
                <button type="button" class="cw-icon-btn js-close-modal" aria-label="Chiudi"><i class="fas fa-xmark"></i></button>
            </div>

            <form id="cwCreateForm" class="cw-form">
                <div class="cw-field">
                    <label>Titolo</label>
                    <input name="titolo" maxlength="120" required placeholder="Titolo breve">
                </div>

                <div class="cw-field">
                    <label>Descrizione</label>
                    <textarea name="descrizione" maxlength="2000" rows="4" placeholder="Contesto breve"></textarea>
                </div>

                <?php if ($needsMotivation): ?>
                    <div class="cw-field">
                        <label>Motivazione</label>
                        <textarea name="motivazione" maxlength="2000" rows="3" placeholder="Perché merita di stare qui?"></textarea>
                    </div>
                <?php endif; ?>

                <div class="cw-form-grid">
                    <div class="cw-field">
                        <label>Tag</label>
                        <input name="tag" maxlength="40" placeholder="es. meme, gaming">
                    </div>
                    <label class="cw-check">
                        <input type="checkbox" name="is_spoiler" value="1">
                        <span>Segna come spoiler</span>
                    </label>
                </div>

                <div class="cw-dropzone" id="cwDropzone">
                    <input type="file" name="media" id="cwMediaInput" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm" required>
                    <div id="cwPreview" class="cw-preview">
                        <i class="fas fa-cloud-arrow-up"></i>
                        <strong>Carica immagine, GIF o video breve</strong>
                        <span>Immagini/GIF max 8MB, video max 20MB.</span>
                    </div>
                </div>

                <div class="cw-modal__footer">
                    <button type="button" class="cw-btn cw-btn--ghost js-close-modal">Annulla</button>
                    <button type="submit" class="cw-btn cw-btn--primary">
                        <i class="fas fa-paper-plane"></i>
                        <span>Invia</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="cwTextModal" class="cw-modal" aria-hidden="true">
        <div class="cw-modal__panel" role="dialog" aria-modal="true">
            <div class="cw-modal__head">
                <div>
                    <strong id="cwTextTitle">Modifica</strong>
                    <span>Puoi modificare solo testo e tag.</span>
                </div>
                <button type="button" class="cw-icon-btn js-close-text-modal" aria-label="Chiudi"><i class="fas fa-xmark"></i></button>
            </div>
            <form id="cwTextForm" class="cw-form"></form>
        </div>
    </div>


    <div id="cwPostModal" class="cw-modal cw-post-modal" aria-hidden="true">
        <div class="cw-modal__panel cw-post-modal__panel" role="dialog" aria-modal="true">
            <button type="button" class="cw-icon-btn cw-post-modal__close js-close-post-modal" aria-label="Chiudi">
                <i class="fas fa-xmark"></i>
            </button>
            <div id="cwPostModalBody"></div>
        </div>
    </div>

    <div id="cwToast" class="cw-toast"></div>

    <?php include '../includes/footer.php'; ?>

    <script>
        window.__CRIPSUM_BOOTSTRAP_FALLBACK__ = true;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>