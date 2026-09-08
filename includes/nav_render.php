<?php

/**
 * Rendering della navbar.
 *
 * I dropdown non sono piu' quelli di Bootstrap: ogni menu e' un elemento con
 * l'attributo `popover`, quindi vive nel top layer (niente z-index da inseguire
 * e niente clipping dai contenitori) e riceve gratis chiusura con Esc e con il
 * click fuori. L'animazione di uscita, che con Bootstrap non esisteva perche'
 * il menu passava dritto a display:none, la fa il CSS in navbar.css.
 *
 * Sotto i 1200px lo stesso elemento diventa un bottom sheet: nessuna griglia
 * compressa con lo scroll interno dentro un collapse che a sua volta scorre.
 */

require_once __DIR__ . '/nav_config.php';

if (!function_exists('nav_bootstrap')) {

    /**
     * Raccoglie tutto quello che serve al rendering.
     *
     * Torna anche le variabili che le vecchie navbar lasciavano nello scope
     * dell'include ($lang, $t, $isLoggedIn...): diverse pagine le leggono dopo
     * l'include, quindi i wrapper le riespongono a partire da qui.
     */
    function nav_bootstrap(array $opts = []): array
    {
        $mysqli = $opts['mysqli'] ?? ($GLOBALS['mysqli'] ?? null);

        $uri     = $_SERVER['REQUEST_URI'] ?? '/';
        $lang    = nav_lang($uri);
        $t       = nav_translations($lang);
        $altLang = ($lang === 'it') ? 'en' : 'it';

        $isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
        $userId     = $isLoggedIn ? (int)$_SESSION['user_id'] : 0;

        $unreadCount = 0;
        $unreadChat  = 0;
        $stats       = ['money' => null, 'streak' => null, 'achievements' => null];

        if ($isLoggedIn && $mysqli instanceof mysqli) {
            if (function_exists('trackDailyLogin')) {
                trackDailyLogin($mysqli, $userId);
            }
            if (function_exists('trackMissionProgress')) {
                trackMissionProgress($mysqli, $userId, 'view_page');
            }
            if (function_exists('getUnreadMessagesCount')) {
                $unreadCount = (int)getUnreadMessagesCount($mysqli, $userId);
            }
            if (function_exists('getUnreadPrivateChatsCount')) {
                $unreadChat = (int)getUnreadPrivateChatsCount($mysqli, $userId);
            }
            $stats = nav_user_stats($mysqli, $userId);
        }

        $ctx = [
            'variant'        => $opts['variant'] ?? 'main',
            'fadein'         => $opts['fadein'] ?? true,
            'settings_modal' => $opts['settings_modal'] ?? false,
            'show_lang'      => $opts['show_lang'] ?? true,
            // navbar-bio mostra il cambio lingua nella barra solo su
            // edit-profile, ma lo tiene sempre nella riga mobile.
            'show_lang_desktop' => $opts['show_lang_desktop'] ?? ($opts['show_lang'] ?? true),
            'show_search'    => $opts['show_search'] ?? true,
            // Spegne il lato destro per intero: inbox, pannello account e
            // pulsanti accedi/registrati.
            'show_account'   => $opts['show_account'] ?? true,
            'brand_href'     => $opts['brand_href'] ?? "/$lang/home",

            'uri'         => $uri,
            'lang'        => $lang,
            'altLang'     => $altLang,
            'altLabel'    => strtoupper($altLang),
            'curLabel'    => strtoupper($lang),
            'switchUrl'   => nav_switch_url($uri, $lang, $altLang),
            't'           => $t,
            'isLoggedIn'  => $isLoggedIn,
            'unreadCount' => $unreadCount,
            'unreadChat'  => $unreadChat,
            'stats'       => $stats,

            'userId'       => $userId,
            'username'     => $isLoggedIn ? ($_SESSION['username'] ?? 'Utente') : '',
            'ruolo'        => $isLoggedIn ? ($_SESSION['ruolo'] ?? '') : '',
            'nsfw'         => $isLoggedIn ? (int)($_SESSION['nsfw'] ?? 0) : 0,
            'richpresence' => $isLoggedIn ? (int)($_SESSION['richpresence'] ?? 0) : 0,
            'isPremium'    => $isLoggedIn ? (int)($_SESSION['is_premium'] ?? 0) : 0,
        ];

        // L'avatar aveva ?t=time(): un URL nuovo a ogni pageload, quindi la
        // cache da 24h che get_pfp.php dichiara non veniva mai usata. Con un
        // bucket da 5 minuti la foto resta valida per una sessione di
        // navigazione e un cambio si vede comunque entro pochi minuti.
        $ctx['profilePic'] = $isLoggedIn
            ? '/includes/get_pfp.php?id=' . $userId . '&v=' . floor(time() / 300)
            : '';

        $ctx['can_rewind'] = $isLoggedIn && function_exists('rewind_user_can_view') && rewind_user_can_view();

        return $ctx;
    }

    /**
     * Icona Font Awesome. `brand` sceglie il set dei loghi.
     */
    function nav_icon(array $item, string $extraClass = ''): string
    {
        $family = !empty($item['brand']) ? 'fa-brands' : 'fa-solid';
        $icon   = $item['icon'] ?? 'fa-circle';

        return '<i class="' . $family . ' ' . htmlspecialchars($icon, ENT_QUOTES) . ' ' . $extraClass . '" aria-hidden="true"></i>';
    }

    function nav_e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Una riga cliccabile dentro un menu.
     */
    function nav_row(array $item, int $index): string
    {
        $badge = '';
        if (!empty($item['badge'])) {
            $badge = '<span class="cnav-row__badge">' . (int)$item['badge'] . '</span>';
        }

        return '<a class="cnav-row" role="menuitem" style="--i:' . $index . '" href="' . nav_e($item['href']) . '">'
            . '<span class="cnav-row__ico">' . nav_icon($item) . '</span>'
            . '<span class="cnav-row__label">' . nav_e($item['label']) . '</span>'
            . $badge
            . '</a>';
    }

    /**
     * Menu di primo livello (Memes, Giochi, Shop, Altro).
     */
    function nav_render_menu(array $entry, array $ctx): void
    {
        $id      = 'cnav-pop-' . nav_e($entry['id']);
        $labelId = $id . '-label';
        ?>
        <li class="cnav-item cnav-item--menu">
            <button type="button"
                class="cnav-trigger"
                id="<?= $id ?>-btn"
                popovertarget="<?= $id ?>"
                aria-haspopup="menu"
                data-cnav-anchor="start">
                <?= nav_icon($entry) ?>
                <span><?= nav_e($entry['label']) ?></span>
                <i class="fa-solid fa-chevron-down cnav-trigger__caret" aria-hidden="true"></i>
            </button>

            <div class="cnav-pop" id="<?= $id ?>" popover role="menu" aria-labelledby="<?= $labelId ?>">
                <div class="cnav-sheet-head">
                    <span class="cnav-sheet-grip" aria-hidden="true"></span>
                    <span class="cnav-sheet-title" id="<?= $labelId ?>"><?= nav_e($entry['label']) ?></span>
                    <button type="button" class="cnav-sheet-close" popovertarget="<?= $id ?>" popovertargetaction="hide" aria-label="<?= nav_e($ctx['t']['close']) ?>">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="cnav-pop__body">
                    <?php foreach (array_values($entry['items']) as $i => $item): ?>
                        <?= nav_row($item, $i) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </li>
        <?php
    }

    /**
     * Pannello account.
     *
     * Vive fuori dal <nav> perche' lo aprono due trigger diversi, quello
     * desktop nella barra e quello mobile accanto all'hamburger: un solo
     * elemento nel DOM, due `popovertarget` che lo puntano.
     */
    function nav_render_account_panel(array $ctx): void
    {
        $t     = $ctx['t'];
        $panel = nav_account_panel($ctx['lang'], $t, [
            'ruolo'       => $ctx['ruolo'],
            'nsfw'        => $ctx['nsfw'],
            'can_rewind'  => $ctx['can_rewind'],
            'unread_chat' => $ctx['unreadChat'],
        ]);

        $stats = $ctx['stats'];
        $rowIx = 0;
        ?>
        <div class="cnav-pop cnav-pop--account" id="cnav-account" popover role="menu" aria-labelledby="cnav-account-name">
            <div class="cnav-sheet-head">
                <span class="cnav-sheet-grip" aria-hidden="true"></span>
                <span class="cnav-sheet-title"><?= nav_e($t['account_menu']) ?></span>
                <button type="button" class="cnav-sheet-close" popovertarget="cnav-account" popovertargetaction="hide" aria-label="<?= nav_e($t['close']) ?>">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="cnav-pop__body">
                <a class="cnav-acct-head" role="menuitem" href="/u/<?= rawurlencode($ctx['username']) ?>">
                    <img class="cnav-acct-head__pfp" src="<?= nav_e($ctx['profilePic']) ?>" alt="" loading="lazy" width="44" height="44">
                    <span class="cnav-acct-head__text">
                        <span class="cnav-acct-head__name" id="cnav-account-name">
                            <?= nav_e($ctx['username']) ?>
                            <?php if ($ctx['isPremium']): ?>
                                <i class="fa-solid fa-gem cnav-acct-head__gem" aria-hidden="true" title="Premium"></i>
                            <?php endif; ?>
                        </span>
                        <span class="cnav-acct-head__meta">
                            @<?= nav_e($ctx['username']) ?><?php if ($ctx['ruolo']): ?> &middot; <?= nav_e($ctx['ruolo']) ?><?php endif; ?>
                        </span>
                    </span>
                    <i class="fa-solid fa-chevron-right cnav-acct-head__go" aria-hidden="true"></i>
                </a>

                <?php
                $statRow = [];
                if ($stats['money'] !== null) {
                    $statRow[] = ['icon' => 'fa-coins', 'value' => $stats['money'], 'label' => $t['stat_money']];
                }
                if (!empty($stats['streak'])) {
                    $statRow[] = ['icon' => 'fa-fire', 'value' => $stats['streak'], 'label' => $t['stat_streak']];
                }
                if ($stats['achievements'] !== null) {
                    $statRow[] = ['icon' => 'fa-trophy', 'value' => $stats['achievements'], 'label' => $t['stat_achv']];
                }
                ?>
                <?php if ($statRow): ?>
                    <div class="cnav-acct-stats">
                        <?php foreach ($statRow as $s): ?>
                            <span class="cnav-stat" title="<?= nav_e($s['label']) ?>">
                                <i class="fa-solid <?= nav_e($s['icon']) ?>" aria-hidden="true"></i>
                                <span class="cnav-stat__val"><?= number_format((int)$s['value'], 0, ',', '.') ?></span>
                                <span class="cnav-stat__lbl"><?= nav_e($s['label']) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($panel['sections'] as $section): ?>
                    <div class="cnav-sect">
                        <?php if (!empty($section['label'])): ?>
                            <p class="cnav-sect__label"><?= nav_e($section['label']) ?></p>
                        <?php endif; ?>

                        <?php if (($section['layout'] ?? 'rows') === 'tiles'): ?>
                            <div class="cnav-tiles">
                                <?php foreach ($section['items'] as $item): ?>
                                    <a class="cnav-tile" role="menuitem" style="--i:<?= $rowIx++ ?>" href="<?= nav_e($item['href']) ?>">
                                        <?= nav_icon($item, 'cnav-tile__ico') ?>
                                        <span class="cnav-tile__label"><?= nav_e($item['label']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($section['items'] as $item): ?>
                                <?= nav_row($item, $rowIx++) ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ($panel['restricted']): ?>
                    <div class="cnav-sect cnav-sect--restricted">
                        <p class="cnav-sect__label"><?= nav_e($t['sec_restricted']) ?></p>
                        <div class="cnav-keys">
                            <?php foreach ($panel['restricted'] as $item): ?>
                                <a class="cnav-key cnav-key--<?= nav_e($item['tone']) ?>" role="menuitem" style="--i:<?= $rowIx++ ?>" href="<?= nav_e($item['href']) ?>">
                                    <?= nav_icon($item, 'cnav-key__ico') ?>
                                    <span class="cnav-key__label"><?= nav_e($item['label']) ?></span>
                                    <i class="fa-solid fa-arrow-right cnav-key__go" aria-hidden="true"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="cnav-acct-foot">
                <a class="cnav-foot-btn cnav-foot-btn--danger" role="menuitem" href="<?= nav_e($panel['logout']['href']) ?>">
                    <?= nav_icon($panel['logout']) ?>
                    <span><?= nav_e($panel['logout']['label']) ?></span>
                </a>
                <a class="cnav-foot-icon" role="menuitem" href="<?= nav_e($panel['settings']['href']) ?>" aria-label="<?= nav_e($panel['settings']['label']) ?>" title="<?= nav_e($panel['settings']['label']) ?>">
                    <?= nav_icon($panel['settings']) ?>
                </a>
            </div>
        </div>
        <?php
    }

    function nav_render_lang_switch(array $ctx, string $extraClass = ''): void
    {
        ?>
        <a href="<?= nav_e($ctx['switchUrl']) ?>"
            class="lang-switch <?= nav_e($extraClass) ?>"
            aria-label="Switch language to <?= nav_e($ctx['altLabel']) ?>"
            title="Switch to <?= nav_e($ctx['altLabel']) ?>">
            <span class="lang-switch__cur"><?= nav_e($ctx['curLabel']) ?></span>
            <span class="lang-switch__sep">&middot;</span>
            <span class="lang-switch__alt"><?= nav_e($ctx['altLabel']) ?></span>
        </a>
        <?php
    }

    /**
     * Emette la navbar completa.
     */
    function nav_render(array $ctx): void
    {
        $t          = $ctx['t'];
        $lang       = $ctx['lang'];
        $isLoggedIn = $ctx['isLoggedIn'];
        $menu       = nav_primary_menu($lang, $t, $ctx['variant']);

        // Il foglio di stile viaggia con la navbar invece che dall'head: e' il
        // primo elemento del body, quindi blocca comunque il rendering di
        // quello che segue e non c'e' lampo di pagina senza stile. Il guard
        // serve alle pagine che includono due navbar (profile.php).
        static $assetsDone = false;
        $emitAssets = !$assetsDone;
        $assetsDone = true;
        ?>
        <?php if ($emitAssets): ?>
            <script>
                // Il tema arriva dallo stesso cookie che legge controlloTema.js.
                // Va scritto prima che la navbar venga disegnata, altrimenti il
                // menu lampeggia scuro su tema chiaro.
                (function () {
                    try {
                        var m = document.cookie.match(/(?:^|; )theme=([^;]*)/);
                        var v = m ? parseInt(decodeURIComponent(m[1]), 10) : 1;
                        document.documentElement.setAttribute('data-cnav-theme', v === 2 ? 'light' : 'dark');
                    } catch (e) {
                        document.documentElement.setAttribute('data-cnav-theme', 'dark');
                    }
                })();
            </script>
            <link rel="stylesheet" href="/css/navbar.css?v=1">
        <?php endif; ?>

        <nav class="navbarutenti navbar navbar-expand-xl<?= $ctx['fadein'] ? ' fadein' : '' ?>">
            <div class="container-fluid">
                <a class="navbar-brand" href="<?= nav_e($ctx['brand_href']) ?>">
                    <img src="/img/amongus-logo.jpg" height="40" width="40" style="border-radius: 4px" class="d-inline-block align-middle" alt="Cripsum" />
                    <span class="align-middle ms-3 fw-bold testobianco">Cripsum&trade;</span>
                </a>

                <div class="navbar-mobile-actions d-xl-none">
                    <?php if ($ctx['show_lang']) {
                        nav_render_lang_switch($ctx);
                    } ?>

                    <?php if ($isLoggedIn && $ctx['show_account']): ?>
                        <a href="/<?= $lang ?>/inbox" class="nav-inbox-link-mobile position-relative" aria-label="Inbox" title="Inbox">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            <span id="inbox-unread-count-mobile" class="badge bg-danger position-absolute translate-middle rounded-pill <?= ($ctx['unreadCount'] > 0) ? '' : 'd-none' ?>">
                                <?= (int)$ctx['unreadCount'] ?>
                            </span>
                        </a>

                        <button type="button"
                            class="cnav-avatar-btn"
                            popovertarget="cnav-account"
                            aria-haspopup="menu"
                            aria-label="<?= nav_e($t['account_menu']) ?>">
                            <img src="<?= nav_e($ctx['profilePic']) ?>" alt="" width="32" height="32" loading="lazy">
                        </button>
                    <?php endif; ?>
                </div>

                <button
                    class="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent"
                    aria-controls="navbarSupportedContent"
                    aria-expanded="false"
                    aria-label="<?= nav_e($t['open_nav']) ?>"
                    style="z-index: 1000">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav cnav-list me-auto mb-2 mb-lg-0">
                        <?php foreach ($menu as $entry): ?>
                            <?php if (($entry['type'] ?? 'link') === 'menu'): ?>
                                <?php nav_render_menu($entry, $ctx); ?>
                            <?php else: ?>
                                <li class="cnav-item nav-item">
                                    <a class="nav-link<?= !empty($entry['disabled']) ? ' cnav-link--off' : '' ?>" href="<?= nav_e($entry['href']) ?>">
                                        <?= nav_icon($entry, 'me-2') ?><?= nav_e($entry['label']) ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($ctx['show_search']): ?>
                            <li class="navbar-search-wrapper" id="navbarSearch">
                                <div class="navbar-search-group">
                                    <i class="fa-solid fa-search navbar-search-icon" aria-hidden="true"></i>
                                    <input
                                        type="text"
                                        class="navbar-search-input"
                                        id="navbarSearchInput"
                                        placeholder="<?= nav_e($t['search_ph']) ?>"
                                        autocomplete="off"
                                        spellcheck="false"
                                        maxlength="30"
                                        aria-label="<?= nav_e($t['search_lbl']) ?>"
                                        aria-autocomplete="list"
                                        aria-controls="navbarSearchDropdown"
                                        aria-expanded="false" />
                                    <button class="navbar-search-clear" id="navbarSearchClear" type="button" tabindex="-1" aria-label="<?= nav_e($t['search_clear']) ?>">
                                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="navbar-search-dropdown" id="navbarSearchDropdown" role="listbox"></div>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <?php if ($ctx['show_lang_desktop']): ?>
                            <li class="nav-item d-none d-xl-flex align-items-center me-2 lang-switch-item">
                                <?php nav_render_lang_switch($ctx); ?>
                            </li>
                        <?php endif; ?>

                        <?php if (!$ctx['show_account']): ?>
                            <?php /* barra ridotta: nessun accesso all'account */ ?>
                        <?php elseif (!$isLoggedIn): ?>
                            <li class="nav-item nav-auth-group">
                                <a class="nav-link" href="/<?= $lang ?>/accedi"><i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i><?= nav_e($t['login']) ?></a>
                                <a class="nav-link" href="/<?= $lang ?>/registrati"><i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i><?= nav_e($t['register']) ?></a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item d-none d-xl-flex align-items-center ms-2 me-1 inbox-item" style="position: relative;">
                                <a href="/<?= $lang ?>/inbox" class="nav-link nav-inbox-link d-flex align-items-center position-relative" aria-label="Inbox" title="Inbox">
                                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                    <span id="inbox-unread-count" class="badge bg-danger position-absolute translate-middle rounded-pill <?= ($ctx['unreadCount'] > 0) ? '' : 'd-none' ?>">
                                        <?= (int)$ctx['unreadCount'] ?>
                                    </span>
                                </a>
                            </li>

                            <li class="nav-item cnav-item cnav-item--account dropdownprofilo d-none d-xl-flex">
                                <button type="button"
                                    class="cnav-trigger cnav-trigger--account"
                                    popovertarget="cnav-account"
                                    aria-haspopup="menu"
                                    data-cnav-anchor="end">
                                    <img src="<?= nav_e($ctx['profilePic']) ?>" alt="" class="cnav-trigger__pfp" width="30" height="30" loading="lazy">
                                    <span><?= nav_e($ctx['username']) ?></span>
                                    <i class="fa-solid fa-chevron-down cnav-trigger__caret" aria-hidden="true"></i>
                                </button>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if ($ctx['settings_modal']): ?>
                    <div class="btn-group ms-auto me-3 linguanuova d-none d-xl-inline-flex">
                        <button type="button" class="btn impostazioni-toggler" data-bs-toggle="modal" data-bs-target="#impostazioniModal">
                            <img src="/img/settings-icon.svg" alt="<?= nav_e($t['settings']) ?>" style="width: 25px" class="imgbianca" />
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </nav>

        <?php if ($isLoggedIn && $ctx['show_account']) {
            nav_render_account_panel($ctx);
        } ?>

        <?php if ($emitAssets): ?>
            <script>
                window.CNAV_I18N = {
                    noResults: <?= json_encode($t['no_results'], JSON_UNESCAPED_UNICODE) ?>,
                    searching: <?= json_encode($t['searching'], JSON_UNESCAPED_UNICODE) ?>,
                    error: <?= json_encode($t['search_err'], JSON_UNESCAPED_UNICODE) ?>,
                    badgeUser: <?= json_encode($t['user_badge'], JSON_UNESCAPED_UNICODE) ?>
                };
            </script>
            <script src="/js/navbar.js?v=1" defer></script>
        <?php endif; ?>

        <?php if ($ctx['richpresence'] === 1): ?>
            <script src="/js/richpresence.js?v=4" defer></script>
        <?php endif; ?>

        <?php if ($isLoggedIn): ?>
            <script>
                if (typeof unlockAchievement === 'function' && typeof getCookie === 'function' && !getCookie('achievement1Unlocked')) {
                    unlockAchievement(1);
                    setCookie('achievement1Unlocked', true);
                }
            </script>
        <?php endif; ?>
        <?php
    }
}
