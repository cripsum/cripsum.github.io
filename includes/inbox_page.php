<?php

/**
 * Centro messaggi — pagina condivisa fra /it/inbox e /en/inbox.
 *
 * I due file dentro it/ e en/ sono wrapper di tre righe che passano la
 * lingua. Prima erano due copie da 1356 righe l'una, tenute allineate a
 * mano: la stessa correzione andava fatta due volte e le due versioni
 * avevano gia' cominciato a divergere.
 *
 * Si aspetta $lang gia' impostata da chi include.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/inbox_strings.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

requireLogin();

$lang        = (isset($lang) && $lang === 'en') ? 'en' : 'it';
$userId      = (int)$_SESSION['user_id'];
$currentUser = getCurrentUser($mysqli);
$csrfToken   = csrf_token();

$T = inbox_strings($lang);

$isStaff = in_array($currentUser['ruolo'] ?? '', ['admin', 'owner'], true);

function inbox_e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// La versione la da' la data del file: cambiare il CSS o il JS senza
// ricordarsi di alzare ?v= vuol dire che nessuno vede la modifica.
$cssVer = @filemtime(__DIR__ . '/../css/inbox.css') ?: 1;
$jsVer  = @filemtime(__DIR__ . '/../assets/inbox/inbox.js') ?: 1;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <?php $ogDescription = $T['og_description']; ?>
    <?php include __DIR__ . '/head-import.php'; ?>
    <title><?= inbox_e($T['page_title']) ?> - Cripsum&trade;</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta property="og:url" content="https://cripsum.com/<?= $lang ?>/inbox">

    <link rel="stylesheet" href="/css/inbox.css?v=<?= $cssVer ?>">
    <link rel="stylesheet" href="/css/style-dark.css?v=5.0">
</head>

<body class="home-v5-body">
    <?php include __DIR__ . '/navbar.php'; ?>
    <?php include __DIR__ . '/impostazioni.php'; ?>

    <div class="home-bg" aria-hidden="true">
        <span class="home-noise"></span>
        <span class="home-orb home-orb--one"></span>
        <span class="home-orb home-orb--two"></span>
        <span class="home-grid"></span>
    </div>

    <main class="inbox-container">
        <div class="inbox-topbar">
            <!-- Livello superiore: i ticket sono conversazioni, non una
                 categoria di messaggi, quindi stanno fuori dall'elenco. -->
            <div class="inbox-sections" role="tablist" aria-label="<?= inbox_e($T['aside_label']) ?>">
                <button type="button" class="inbox-section-btn is-active" data-section="messages" role="tab" aria-selected="true">
                    <i class="fa-solid fa-inbox" aria-hidden="true"></i>
                    <span><?= inbox_e($T['sec_messages']) ?></span>
                    <span class="inbox-section-badge" id="badge-messages" hidden>0</span>
                </button>
                <button type="button" class="inbox-section-btn" data-section="tickets" role="tab" aria-selected="false">
                    <i class="fa-solid fa-headset" aria-hidden="true"></i>
                    <span><?= inbox_e($T['sec_tickets']) ?></span>
                    <span class="inbox-section-badge" id="badge-ticket" hidden>0</span>
                </button>
            </div>


            <!-- Le categorie erano una colonna intera per sei voci, e su
                 telefono una striscia che scorreva di lato: qui sono chip
                 che stanno sulla stessa riga. -->
            <div class="inbox-chips" id="inboxChips" aria-label="<?= inbox_e($T['filter_by']) ?>">
                <?php
                $chips = [
                    ''        => $T['cat_all'],
                    'system'  => $T['cat_system'],
                    'social'  => $T['cat_social'],
                    'rewards' => $T['cat_rewards_nav'],
                    'special' => $T['cat_special_nav'],
                ];
                foreach ($chips as $cat => $label): ?>
                    <button type="button" class="inbox-chip<?= $cat === '' ? ' is-active' : '' ?>" data-cat="<?= inbox_e($cat) ?>">
                        <?= inbox_e($label) ?>
                        <span class="inbox-chip-badge" data-badge="<?= inbox_e($cat === '' ? 'all' : $cat) ?>" hidden>0</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <section class="inbox-panel inbox-main-layout">

            <div class="inbox-list-pane">

                <div class="inbox-search-bar">
                    <div class="inbox-search-wrapper">
                        <i class="fa-solid fa-search inbox-search-icon" aria-hidden="true"></i>
                        <input type="text" class="inbox-search-input" id="inboxSearchInput"
                            placeholder="<?= inbox_e($T['search']) ?>" aria-label="<?= inbox_e($T['search']) ?>" autocomplete="off">
                    </div>
                </div>

                <div class="inbox-filters">
                    <button type="button" class="inbox-filter-tab is-active" data-status=""><?= inbox_e($T['tab_inbox']) ?></button>
                    <button type="button" class="inbox-filter-tab" data-status="unread"><?= inbox_e($T['tab_unread']) ?></button>
                    <button type="button" class="inbox-filter-tab" data-status="important"><?= inbox_e($T['tab_starred']) ?></button>
                    <button type="button" class="inbox-filter-tab" data-status="archived"><?= inbox_e($T['tab_archive']) ?></button>
                </div>

                <div class="inbox-cards-scroll" id="inboxCardsContainer"></div>
            </div>

            <div class="inbox-view-pane" id="inboxDetailContainer">
                <div class="inbox-view-empty">
                    <i class="fa-solid fa-envelope-open" aria-hidden="true"></i>
                    <h4><?= inbox_e($T['empty_title']) ?></h4>
                    <p class="inbox-view-empty-sub"><?= inbox_e($T['empty_body']) ?></p>
                </div>
            </div>

        </section>
    </main>

    <div class="inbox-reward-modal-backdrop" id="rewardModalBackdrop">
        <div class="inbox-reward-modal">
            <div class="inbox-reward-modal-icon">
                <i class="fa-solid fa-gift" aria-hidden="true"></i>
            </div>
            <div class="inbox-reward-modal-title"><?= inbox_e($T['modal_title']) ?></div>
            <div class="inbox-reward-modal-sub"><?= inbox_e($T['modal_sub']) ?></div>

            <div class="inbox-reward-modal-list" id="rewardModalList"></div>

            <button type="button" class="inbox-reward-modal-close" id="rewardModalCloseBtn"><?= inbox_e($T['modal_close']) ?></button>
        </div>
    </div>

    <?php
    // Il piede di pagina esiste in due file, non tradotto internamente: la
    // vecchia en/inbox.php includeva footer-en.php e unificando le pagine
    // quella riga si era persa.
    include __DIR__ . ($lang === 'en' ? '/footer-en.php' : '/footer.php');
    ?>

    <!-- Serve al modale delle impostazioni e al menu della navbar. -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

    <script>
        window.INBOX_BOOT = {
            lang: <?= json_encode($lang) ?>,
            userId: <?= $userId ?>,
            isAdmin: <?= $isStaff ? 'true' : 'false' ?>,
            csrfToken: <?= json_encode($csrfToken) ?>
        };
        window.INBOX_I18N = <?= json_encode(inbox_js_strings($T), JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="/assets/inbox/inbox.js?v=<?= $jsVer ?>" defer></script>
</body>

</html>
