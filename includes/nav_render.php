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
        $snap        = [
            'money'        => null,
            'streak'       => null,
            'achievements' => null,
            'achv_latest'  => null,
            'missions'     => 0,
            'friends'      => 0,
        ];

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
            $snap = nav_user_snapshot($mysqli, $userId);
        }

        $ctx = [
            'variant'           => $opts['variant'] ?? 'main',
            'fadein'            => $opts['fadein'] ?? true,
            'settings_modal'    => $opts['settings_modal'] ?? false,
            'show_lang'         => $opts['show_lang'] ?? true,
            // navbar-bio mostra il cambio lingua nella barra solo su
            // edit-profile, ma lo tiene sempre nella riga mobile.
            'show_lang_desktop' => $opts['show_lang_desktop'] ?? ($opts['show_lang'] ?? true),
            'show_search'       => $opts['show_search'] ?? true,
            // Spegne il lato destro per intero: inbox, pannello account e
            // pulsanti accedi/registrati.
            'show_account'      => $opts['show_account'] ?? true,
            'brand_href'        => $opts['brand_href'] ?? "/$lang/home",

            'uri'         => $uri,
            'lang'        => $lang,
            'altLang'     => $altLang,
            'altLabel'    => strtoupper($altLang),
            'curLabel'    => strtoupper($lang),
            'altName'     => ($altLang === 'en') ? 'English' : 'Italiano',
            'switchUrl'   => nav_switch_url($uri, $lang, $altLang),
            't'           => $t,
            'isLoggedIn'  => $isLoggedIn,
            'unreadCount' => $unreadCount,
            'unreadChat'  => $unreadChat,
            'stats'       => $snap,
            'missions'    => $snap['missions'],
            'friends'     => $snap['friends'],
            'achv_latest' => $snap['achv_latest'],

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

    function nav_e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Icona Font Awesome. `brand` sceglie il set dei loghi.
     */
    function nav_icon(array $item, string $extraClass = ''): string
    {
        $family = !empty($item['brand']) ? 'fa-brands' : 'fa-solid';
        $icon   = $item['icon'] ?? 'fa-circle';

        return '<i class="' . $family . ' ' . nav_e($icon) . ' ' . nav_e($extraClass) . '" aria-hidden="true"></i>';
    }

    /**
     * Icona in una casella a larghezza fissa.
     *
     * Senza la casella le glifi larghe diverse (una casa contro tre puntini)
     * fanno partire le etichette da ascisse diverse, e nel menu mobile la
     * colonna di testo risultava a zigzag.
     */
    function nav_icon_slot(array $item): string
    {
        return '<span class="cnav-ico">' . nav_icon($item) . '</span>';
    }

    /**
     * Una riga cliccabile dentro un menu.
     */
    function nav_row(array $item, int $index, string $uri = ''): string
    {
        $badge = '';
        if (!empty($item['badge'])) {
            $badge = '<span class="cnav-row__badge">' . (int)$item['badge'] . '</span>';
        }

        $current = $uri !== '' && nav_is_current((string)$item['href'], $uri);

        return '<a class="cnav-row' . ($current ? ' is-current' : '') . '"'
            . ' role="menuitem" style="--i:' . $index . '"'
            . ($current ? ' aria-current="page"' : '')
            . ' href="' . nav_e($item['href']) . '">'
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

        // Il trigger si accende quando la pagina aperta e' una delle sue voci.
        $active = false;
        foreach ($entry['items'] as $item) {
            if (nav_is_current((string)$item['href'], $ctx['uri'])) {
                $active = true;
                break;
            }
        }
        ?>
        <li class="cnav-item cnav-item--menu">
            <button type="button"
                class="cnav-trigger<?= $active ? ' is-current' : '' ?>"
                id="<?= $id ?>-btn"
                popovertarget="<?= $id ?>"
                aria-haspopup="menu"
                data-cnav-anchor="start">
                <?= nav_icon_slot($entry) ?>
                <span class="cnav-trigger__label"><?= nav_e($entry['label']) ?></span>
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
                        <?= nav_row($item, $i, $ctx['uri']) ?>
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
        $uri   = $ctx['uri'];
        $panel = nav_account_panel($ctx['lang'], $t, [
            'ruolo'       => $ctx['ruolo'],
            'nsfw'        => $ctx['nsfw'],
            'can_rewind'  => $ctx['can_rewind'],
            'unread_chat' => $ctx['unreadChat'],
            'missions'    => $ctx['missions'],
            'friends'     => $ctx['friends'],
            'achv_latest' => $ctx['achv_latest'],
        ]);

        $stats = $ctx['stats'];
        $rowIx = 0;

        // I Godos hanno un'icona propria, le altre due voci no.
        $statRow = [];
        if ($stats['money'] !== null) {
            $statRow[] = [
                'img'   => '/img/godos.png',
                'value' => $stats['money'],
                'label' => $t['stat_money'],
                'tip'   => $t['stat_money_tip'],
            ];
        }
        if (!empty($stats['streak'])) {
            $statRow[] = [
                'icon'  => 'fa-fire',
                'value' => $stats['streak'],
                'label' => $t['stat_streak'],
                'tip'   => $t['stat_streak_tip'],
            ];
        }
        if ($stats['achievements'] !== null) {
            $statRow[] = [
                'icon'  => 'fa-trophy',
                'value' => $stats['achievements'],
                'label' => $t['stat_achv'],
                'tip'   => $t['stat_achv_tip'],
            ];
        }
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
                                <i class="fa-solid fa-gem cnav-acct-head__gem" aria-hidden="true" data-cnav-tip="Premium"></i>
                            <?php endif; ?>
                        </span>
                        <span class="cnav-acct-head__meta">
                            @<?= nav_e($ctx['username']) ?><?php if ($ctx['ruolo']): ?> &middot; <?= nav_e($ctx['ruolo']) ?><?php endif; ?>
                        </span>
                    </span>
                    <i class="fa-solid fa-chevron-right cnav-acct-head__go" aria-hidden="true"></i>
                </a>

                <?php if ($statRow): ?>
                    <div class="cnav-acct-stats">
                        <?php foreach ($statRow as $s): ?>
                            <span class="cnav-stat" data-cnav-tip="<?= nav_e($s['tip']) ?>" tabindex="0">
                                <span class="cnav-stat__ico">
                                    <?php if (!empty($s['img'])): ?>
                                        <img src="<?= nav_e($s['img']) ?>" alt="" width="18" height="18" loading="lazy">
                                    <?php else: ?>
                                        <i class="fa-solid <?= nav_e($s['icon']) ?>" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </span>
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
                                    <?php $cur = nav_is_current((string)$item['href'], $uri); ?>
                                    <a class="cnav-tile<?= $cur ? ' is-current' : '' ?>" role="menuitem" style="--i:<?= $rowIx++ ?>"<?= $cur ? ' aria-current="page"' : '' ?>
                                        href="<?= nav_e($item['href']) ?>"
                                        <?php if (!empty($item['tip'])): ?>data-cnav-tip="<?= nav_e($item['tip']) ?>"<?php endif; ?>
                                        <?php if (!empty($item['new_since'])): ?>data-cnav-new-since="<?= (int)$item['new_since'] ?>" data-cnav-new-key="achv"<?php endif; ?>>
                                        <?= nav_icon($item, 'cnav-tile__ico') ?>
                                        <span class="cnav-tile__label"><?= nav_e($item['label']) ?></span>
                                        <?php if (!empty($item['badge'])): ?>
                                            <span class="cnav-dot cnav-dot--count" aria-hidden="true"><?= (int)$item['badge'] > 9 ? '9+' : (int)$item['badge'] ?></span>
                                        <?php elseif (!empty($item['new_since'])): ?>
                                            <span class="cnav-dot" aria-hidden="true" hidden></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($section['items'] as $item): ?>
                                <?= nav_row($item, $rowIx++, $uri) ?>
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
                <a class="cnav-foot-icon" role="menuitem" href="<?= nav_e($panel['settings']['href']) ?>" data-cnav-tip="<?= nav_e($panel['settings']['label']) ?>" aria-label="<?= nav_e($panel['settings']['label']) ?>">
                    <?= nav_icon($panel['settings']) ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Cambio lingua.
     *
     * Prima era "IT · EN" e l'intera pillola portava all'altra lingua: si
     * leggeva come un interruttore a due stati, ma qualsiasi punto si
     * premesse il risultato era lo stesso. Ora la lingua attiva e' un
     * segmento pieno e l'altra e' il bersaglio, quindi si vede dove si va.
     */
    /**
     * Pallino riassuntivo sul trigger dell'account.
     *
     * Il pannello e' chiuso quasi sempre: senza questo, una missione da
     * riscuotere o una richiesta di amicizia resterebbero invisibili finche'
     * non lo si apre. Parte da quello che sa il server; per gli achievement
     * lo accende navbar.js, che e' l'unico a sapere se sono gia' stati visti.
     */
    function nav_account_has_news(array $ctx): bool
    {
        return ((int)$ctx['missions'] > 0)
            || ((int)$ctx['friends'] > 0)
            || ((int)$ctx['unreadChat'] > 0);
    }

    function nav_render_lang_switch(array $ctx): void
    {
        $label = $ctx['t']['lang_switch'] . ' ' . $ctx['altName'];
        ?>
        <a href="<?= nav_e($ctx['switchUrl']) ?>"
            class="cnav-lang"
            data-cnav-tip="<?= nav_e($label) ?>"
            aria-label="<?= nav_e($label) ?>">
            <span class="cnav-lang__seg cnav-lang__seg--on" aria-hidden="true"><?= nav_e($ctx['curLabel']) ?></span>
            <span class="cnav-lang__seg" aria-hidden="true"><?= nav_e($ctx['altLabel']) ?></span>
        </a>
        <?php
    }

    /**
     * Pulsante della posta.
     *
     * `$idSuffix` distingue la copia mobile da quella desktop: inbox.php
     * aggiorna il badge per id, quindi `inbox-unread-count` deve restare
     * quello che era.
     */
    function nav_render_inbox(array $ctx, string $badgeId): void
    {
        $t     = $ctx['t'];
        $count = (int)$ctx['unreadCount'];
        $tip   = $count > 0
            ? $count . ' ' . $t['inbox_unread']
            : $t['inbox_empty'];
        ?>
        <a href="/<?= $ctx['lang'] ?>/inbox"
            class="cnav-icon-btn cnav-inbox<?= $count > 0 ? ' has-unread' : '' ?>"
            data-cnav-tip="<?= nav_e($tip) ?>"
            aria-label="<?= nav_e($t['inbox']) ?>">
            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
            <span id="<?= nav_e($badgeId) ?>" class="cnav-icon-btn__badge <?= $count > 0 ? '' : 'd-none' ?>">
                <?= $count > 99 ? '99+' : $count ?>
            </span>
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

        // La versione la da' la data del file, non un numero da incrementare
        // a mano: cambiare il CSS senza ricordarsi di alzare ?v= vuol dire
        // che nessuno vede la modifica finche' non svuota la cache.
        $cssVer = @filemtime(__DIR__ . '/../css/navbar.css') ?: 1;
        $jsVer  = @filemtime(__DIR__ . '/../js/navbar.js') ?: 1;
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
            <link rel="stylesheet" href="/css/navbar.css?v=<?= $cssVer ?>">
        <?php endif; ?>

        <nav class="navbarutenti navbar navbar-expand-xl<?= $ctx['fadein'] ? ' fadein' : '' ?>">
            <div class="container-fluid">
                <a class="navbar-brand" href="<?= nav_e($ctx['brand_href']) ?>">
                    <img src="/img/amongus-logo.jpg" width="750" height="504" class="cnav-logo d-inline-block align-middle" alt="Cripsum" />
                    <span class="align-middle ms-3 fw-bold testobianco">Cripsum&trade;</span>
                </a>

                <div class="navbar-mobile-actions d-xl-none">
                    <?php if ($ctx['show_lang']) {
                        nav_render_lang_switch($ctx);
                    } ?>

                    <?php if ($isLoggedIn && $ctx['show_account']): ?>
                        <?php nav_render_inbox($ctx, 'inbox-unread-count-mobile'); ?>

                        <button type="button"
                            class="cnav-avatar-btn<?= nav_account_has_news($ctx) ? ' has-news' : '' ?>"
                            popovertarget="cnav-account"
                            aria-haspopup="menu"
                            aria-label="<?= nav_e($t['account_menu']) ?>">
                            <img src="<?= nav_e($ctx['profilePic']) ?>" alt="" width="32" height="32" loading="lazy">
                            <span class="cnav-dot cnav-dot--corner" aria-hidden="true"></span>
                        </button>
                    <?php endif; ?>
                </div>

                <button
                    class="navbar-toggler cnav-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent"
                    aria-controls="navbarSupportedContent"
                    aria-expanded="false"
                    aria-label="<?= nav_e($t['open_nav']) ?>">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav cnav-list me-auto mb-2 mb-lg-0">
                        <?php if ($ctx['show_search']): ?>
                            <li class="navbar-search-wrapper" id="navbarSearch">
                                <div class="navbar-search-group">
                                    <i class="fa-solid fa-magnifying-glass navbar-search-icon" aria-hidden="true"></i>
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
                                    <kbd class="cnav-search-kbd" aria-hidden="true">/</kbd>
                                    <button class="navbar-search-clear" id="navbarSearchClear" type="button" tabindex="-1" aria-label="<?= nav_e($t['search_clear']) ?>">
                                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="navbar-search-dropdown" id="navbarSearchDropdown" role="listbox"></div>
                            </li>
                        <?php endif; ?>

                        <?php foreach ($menu as $entry): ?>
                            <?php if (($entry['type'] ?? 'link') === 'menu'): ?>
                                <?php nav_render_menu($entry, $ctx); ?>
                            <?php else: ?>
                                <?php $cur = nav_is_current((string)$entry['href'], $ctx['uri']); ?>
                                <li class="cnav-item nav-item">
                                    <a class="nav-link cnav-navlink<?= $cur ? ' is-current' : '' ?><?= !empty($entry['disabled']) ? ' cnav-link--off' : '' ?>"
                                        href="<?= nav_e($entry['href']) ?>"<?= $cur ? ' aria-current="page"' : '' ?>>
                                        <?= nav_icon_slot($entry) ?>
                                        <span class="cnav-navlink__label"><?= nav_e($entry['label']) ?></span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>

                    <ul class="navbar-nav cnav-right ms-auto mb-2 mb-lg-0">
                        <?php if ($ctx['show_lang_desktop']): ?>
                            <li class="nav-item d-none d-xl-flex align-items-center lang-switch-item">
                                <?php nav_render_lang_switch($ctx); ?>
                            </li>
                        <?php endif; ?>

                        <?php if (!$ctx['show_account']): ?>
                            <?php /* barra ridotta: nessun accesso all'account */ ?>
                        <?php elseif (!$isLoggedIn): ?>
                            <li class="nav-item nav-auth-group">
                                <a class="nav-link" href="/<?= $lang ?>/accedi"><i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i><?= nav_e($t['login']) ?></a>
                                <a class="nav-link cnav-cta" href="/<?= $lang ?>/registrati"><i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i><?= nav_e($t['register']) ?></a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item d-none d-xl-flex align-items-center inbox-item">
                                <?php nav_render_inbox($ctx, 'inbox-unread-count'); ?>
                            </li>

                            <li class="nav-item cnav-item cnav-item--account dropdownprofilo d-none d-xl-flex">
                                <button type="button"
                                    class="cnav-trigger cnav-trigger--account<?= nav_account_has_news($ctx) ? ' has-news' : '' ?>"
                                    popovertarget="cnav-account"
                                    aria-haspopup="menu"
                                    data-cnav-anchor="end">
                                    <span class="cnav-trigger__pfp-wrap">
                                        <img src="<?= nav_e($ctx['profilePic']) ?>" alt="" class="cnav-trigger__pfp" width="30" height="30" loading="lazy">
                                        <span class="cnav-dot cnav-dot--corner" aria-hidden="true"></span>
                                    </span>
                                    <span class="cnav-trigger__label"><?= nav_e($ctx['username']) ?></span>
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
            <div class="cnav-tip" id="cnavTip" role="tooltip" aria-hidden="true"></div>

            <script>
                window.CNAV_STATE = {
                    userId: <?= (int)$ctx['userId'] ?>,
                    achvLatest: <?= (int)($ctx['achv_latest'] ?? 0) ?>,
                    hasNews: <?= nav_account_has_news($ctx) ? 'true' : 'false' ?>
                };
                window.CNAV_I18N = {
                    noResults: <?= json_encode($t['no_results'], JSON_UNESCAPED_UNICODE) ?>,
                    searching: <?= json_encode($t['searching'], JSON_UNESCAPED_UNICODE) ?>,
                    error: <?= json_encode($t['search_err'], JSON_UNESCAPED_UNICODE) ?>,
                    badgeUser: <?= json_encode($t['user_badge'], JSON_UNESCAPED_UNICODE) ?>,
                    searchHint: <?= json_encode($t['search_hint'], JSON_UNESCAPED_UNICODE) ?>,
                    achvNew: <?= json_encode($t['achv_new'], JSON_UNESCAPED_UNICODE) ?>,
                    hasNews: <?= json_encode($t['has_news'], JSON_UNESCAPED_UNICODE) ?>
                };
            </script>
            <script src="/js/navbar.js?v=<?= $jsVer ?>" defer></script>
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
