<?php

/**
 * Sorgente unica del menu di navigazione.
 *
 * Prima di questo file il markup della navbar era duplicato in navbar.php,
 * navbar-bio.php, navbar-lootbox.php e navbar-goonland.php: aggiungere una
 * voce voleva dire ricordarsi di toccare quattro file. Qui il menu e' un
 * array, il rendering sta in nav_render.php e le quattro navbar restano come
 * wrapper che passano un `variant` diverso.
 */

if (!function_exists('nav_lang')) {

    /**
     * Lingua corrente dedotta dal primo segmento della URI.
     */
    function nav_lang(?string $uri = null): string
    {
        $uri  = $uri ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $lang = explode('/', trim($uri, '/'))[0] ?? '';

        return in_array($lang, ['it', 'en'], true) ? $lang : 'it';
    }

    /**
     * Stringhe della navbar. E' un superset delle chiavi che usavano le
     * vecchie navbar: le pagine che leggono $t dopo l'include continuano a
     * trovare quello che si aspettano.
     */
    function nav_translations(string $lang): array
    {
        $strings = [
            'it' => [
                'memes'          => 'Memes',
                'top_rimasti'    => 'Top rimasti',
                'games'          => 'Giochi',
                'duels'          => 'Duelli',
                'subway'         => 'Subway Surfers',
                'shop'           => 'Shop',
                'store'          => 'Negozio',
                'gacha_shop'     => 'Shop Gacha',
                'other'          => 'Altro',
                'donations'      => 'Donazioni',
                'about'          => 'Chi siamo',
                'login'          => 'Accedi',
                'register'       => 'Registrati',
                'my_profile'     => 'Il mio profilo',
                'settings'       => 'Impostazioni',
                'inventory'      => 'Inventario',
                'global_chat'    => 'Chat Globale',
                'admin_panel'    => 'Pannello Admin',
                'search_ph'      => 'Cerca utente...',
                'search_lbl'     => 'Cerca utente',
                'search_clear'   => 'Cancella ricerca',
                'no_results'     => 'Nessun utente trovato',
                'searching'      => 'Ricerca in corso...',
                'search_err'     => 'Errore nella ricerca, riprova',
                'user_badge'     => 'Utente',
                'my_profile_alt' => 'Profilo',
                'missions'       => 'Missioni',
                'private_chat'   => 'Chat Privata',
                'rewind'         => 'Il tuo Rewind',
                'friends'        => 'Amici',
                'achievements'   => 'Achievements',
                'sec_play'       => 'Gioco',
                'sec_social'     => 'Social',
                'sec_restricted' => 'Riservato',
                'account_menu'   => 'Menu account',
                'close'          => 'Chiudi',
                'missions_ready' => 'ricompense da riscuotere',
                'achv_new'       => 'Nuovi achievement sbloccati',
                'has_news'       => 'Ci sono novità nel menu account',
                'stat_money'     => 'Godos',
                'stat_money_tip' => 'Godos: la valuta del sito',
                'stat_streak'    => 'Giorni di fila',
                'stat_streak_tip' => 'Giorni consecutivi in cui sei passato',
                'stat_achv'      => 'Achievement',
                'stat_achv_tip'  => 'Achievement sbloccati',
                'inbox'          => 'Posta',
                'inbox_unread'   => 'messaggi da leggere',
                'inbox_empty'    => 'Nessun messaggio nuovo',
                'lang_switch'    => 'Passa a',
                'search_hint'    => 'Premi / per cercare',
                'logout'         => 'Logout',
                'back_cripsum'   => 'Torna su Cripsum',
                'home_page'      => 'Home page',
                'goon_gen'       => 'Goon Generator',
                'waifu_quiz'     => 'Waifu Quiz',
                'smash_pass'     => 'Smash or Pass',
                'coming_soon'    => 'Coming soon',
                'open_nav'       => 'Apri il menu',
            ],
            'en' => [
                'memes'          => 'Memes',
                'top_rimasti'    => 'Top braindeads',
                'games'          => 'Games',
                'duels'          => 'Duels',
                'subway'         => 'Subway Surfers',
                'shop'           => 'Shop',
                'store'          => 'Store',
                'gacha_shop'     => 'Gacha Shop',
                'other'          => 'More',
                'donations'      => 'Donate',
                'about'          => 'About us',
                'login'          => 'Log in',
                'register'       => 'Sign up',
                'my_profile'     => 'My profile',
                'settings'       => 'Settings',
                'inventory'      => 'Inventory',
                'global_chat'    => 'Global Chat',
                'admin_panel'    => 'Admin Panel',
                'search_ph'      => 'Search user...',
                'search_lbl'     => 'Search user',
                'search_clear'   => 'Clear search',
                'no_results'     => 'No users found',
                'searching'      => 'Searching...',
                'search_err'     => 'Search error, try again',
                'user_badge'     => 'User',
                'my_profile_alt' => 'Profile',
                'missions'       => 'Missions',
                'private_chat'   => 'Private Chat',
                'rewind'         => 'Your Rewind',
                'friends'        => 'Friends',
                'achievements'   => 'Achievements',
                'sec_play'       => 'Play',
                'sec_social'     => 'Social',
                'sec_restricted' => 'Restricted',
                'account_menu'   => 'Account menu',
                'close'          => 'Close',
                'missions_ready' => 'rewards to claim',
                'achv_new'       => 'New achievements unlocked',
                'has_news'       => 'You have news in the account menu',
                'stat_money'     => 'Godos',
                'stat_money_tip' => 'Godos: the site currency',
                'stat_streak'    => 'Day streak',
                'stat_streak_tip' => 'Days in a row you stopped by',
                'stat_achv'      => 'Achievements',
                'stat_achv_tip'  => 'Achievements unlocked',
                'inbox'          => 'Inbox',
                'inbox_unread'   => 'unread messages',
                'inbox_empty'    => 'No new messages',
                'lang_switch'    => 'Switch to',
                'search_hint'    => 'Press / to search',
                'logout'         => 'Log out',
                'back_cripsum'   => 'Back to Cripsum',
                'home_page'      => 'Home page',
                'goon_gen'       => 'Goon Generator',
                'waifu_quiz'     => 'Waifu Quiz',
                'smash_pass'     => 'Smash or Pass',
                'coming_soon'    => 'Coming soon',
                'open_nav'       => 'Open menu',
            ],
        ];

        return $strings[$lang] ?? $strings['it'];
    }

    /**
     * Vero se l'indirizzo corrente sta dentro questa voce di menu.
     *
     * Confronta per prefisso di percorso, cosi' /it/cripsumpedia/qualcosa
     * accende comunque la voce CripsumPedia. Il taglio sulla query evita che
     * ?page=2 spenga la voce.
     */
    function nav_is_current(string $href, string $uri): bool
    {
        if ($href === '' || $href === '#') {
            return false;
        }

        $path = rtrim(strtok($href, '?'), '/');
        $cur  = rtrim(strtok($uri, '?'), '/');

        if ($path === '') {
            return false;
        }

        return $cur === $path || strpos($cur . '/', $path . '/') === 0;
    }

    /**
     * URL della stessa pagina nell'altra lingua.
     */
    function nav_switch_url(string $uri, string $lang, string $altLang): string
    {
        return preg_replace('#^/' . $lang . '(/|$)#', '/' . $altLang . '$1', $uri) ?? $uri;
    }

    /**
     * Voci di sinistra. `variant` decide quale set: goonland ha una nav
     * completamente diversa, le altre condividono la stessa.
     */
    function nav_primary_menu(string $lang, array $t, string $variant = 'main'): array
    {
        // Le pagine di recupero password vogliono solo il marchio: chi sta
        // reimpostando la password non deve poter finire altrove per sbaglio.
        if ($variant === 'minimal') {
            return [];
        }

        if ($variant === 'goonland') {
            return [
                ['type' => 'link', 'id' => 'back',     'icon' => 'fa-arrow-left',      'label' => $t['back_cripsum'], 'href' => "/$lang/home"],
                ['type' => 'link', 'id' => 'gl-home',  'icon' => 'fa-house',           'label' => $t['home_page'],    'href' => "/$lang/goonland/home"],
                ['type' => 'link', 'id' => 'gl-gen',   'icon' => 'fa-gears',           'label' => $t['goon_gen'],     'href' => "/$lang/goonland/goon-generator"],
                ['type' => 'link', 'id' => 'gl-quiz',  'icon' => 'fa-circle-question', 'label' => $t['waifu_quiz'],   'href' => "/$lang/goonland/anime-girl-quiz"],
                ['type' => 'link', 'id' => 'gl-smash', 'icon' => 'fa-heart',           'label' => $t['smash_pass'],   'href' => "/$lang/goonland/smash-or-pass"],
                ['type' => 'link', 'id' => 'gl-soon',  'icon' => 'fa-clock',           'label' => $t['coming_soon'],  'href' => '#', 'disabled' => true],
            ];
        }

        return [
            ['type' => 'link', 'id' => 'home', 'icon' => 'fa-house', 'label' => $t['home_page'], 'href' => "/$lang/home"],
            [
                'type'  => 'menu',
                'id'    => 'memes',
                'icon'  => 'fa-image',
                'label' => $t['memes'],
                'items' => [
                    ['icon' => 'fa-fire',   'label' => 'Shitpost',        'href' => "/$lang/shitpost"],
                    ['icon' => 'fa-tiktok', 'label' => 'TikTokPedia',     'href' => "/$lang/tiktokpedia", 'brand' => true],
                    ['icon' => 'fa-star',   'label' => $t['top_rimasti'], 'href' => "/$lang/rimasti"],
                    ['icon' => 'fa-book',   'label' => 'CripsumPedia',    'href' => "/$lang/cripsumpedia/home"],
                ],
            ],
            [
                'type'  => 'menu',
                'id'    => 'games',
                'icon'  => 'fa-gamepad',
                'label' => $t['games'],
                'items' => [
                    ['icon' => 'fa-dice',              'label' => 'Gambling',   'href' => "/$lang/gambling"],
                    ['icon' => 'fa-box-open',          'label' => 'Lootbox',    'href' => "/$lang/lootbox"],
                    ['icon' => 'fa-gamepad',           'label' => $t['duels'],  'href' => "/$lang/game/"],
                    ['icon' => 'fa-train',             'label' => $t['subway'], 'href' => "/$lang/subway"],
                    ['icon' => 'fa-headphones-simple', 'label' => 'Pullspot',   'href' => "/$lang/pullspot"],
                    ['icon' => 'fa-compact-disc',      'label' => 'Animespot',  'href' => "/$lang/animespot"],
                ],
            ],
            [
                'type'  => 'menu',
                'id'    => 'shop',
                'icon'  => 'fa-cart-shopping',
                'label' => $t['shop'],
                'items' => [
                    ['icon' => 'fa-store', 'label' => $t['store'],      'href' => "/$lang/negozio"],
                    ['icon' => 'fa-shirt', 'label' => 'Merch',          'href' => "/$lang/merch"],
                    ['icon' => 'fa-gem',   'label' => $t['gacha_shop'], 'href' => "/$lang/shop"],
                ],
            ],
            [
                'type'  => 'menu',
                'id'    => 'other',
                'icon'  => 'fa-ellipsis',
                'label' => $t['other'],
                'items' => [
                    ['icon' => 'fa-download', 'label' => 'Downloads',     'href' => "/$lang/download"],
                    ['icon' => 'fa-heart',    'label' => $t['donations'], 'href' => "/$lang/donazioni"],
                    ['icon' => 'fa-users',    'label' => $t['about'],     'href' => "/$lang/chisiamo"],
                ],
            ],
            ['type' => 'link', 'id' => 'edits', 'icon' => 'fa-video', 'label' => 'Edits', 'href' => "/$lang/edits"],
        ];
    }

    /**
     * Contenuto del pannello account.
     *
     * $ctx: ruolo, nsfw, can_rewind, unread_chat.
     * Le sezioni con layout `tiles` diventano una griglia di riquadri, quelle
     * con `rows` righe piene: le tre voci di gioco sono destinazioni note e
     * riconoscibili dall'icona, quindi occupano una riga sola invece di tre.
     */
    function nav_account_panel(string $lang, array $t, array $ctx): array
    {
        $ruolo   = $ctx['ruolo'] ?? '';
        $isStaff = ($ruolo === 'admin' || $ruolo === 'owner');

        $sections = [];

        $sections[] = [
            'id'     => 'play',
            'label'  => $t['sec_play'],
            'layout' => 'tiles',
            'items'  => [
                [
                    'icon'  => 'fa-trophy',
                    'label' => $t['achievements'],
                    'href'  => "/$lang/achievements",
                    // Gli achievement non hanno uno stato "letto" a database:
                    // il pallino lo decide il browser confrontando questa
                    // data con l'ultima volta che la pagina e' stata aperta.
                    'new_since' => $ctx['achv_latest'] ?? null,
                ],
                [
                    'icon'  => 'fa-bullseye',
                    'label' => $t['missions'],
                    'href'  => "/$lang/missions",
                    'badge' => ((int)($ctx['missions'] ?? 0)) > 0 ? (int)$ctx['missions'] : null,
                    'tip'   => ((int)($ctx['missions'] ?? 0)) > 0
                        ? (int)$ctx['missions'] . ' ' . $t['missions_ready']
                        : null,
                ],
                ['icon' => 'fa-box', 'label' => $t['inventory'], 'href' => "/$lang/inventario"],
            ],
        ];

        $sections[] = [
            'id'     => 'social',
            'label'  => $t['sec_social'],
            'layout' => 'rows',
            'items'  => [
                [
                    'icon'  => 'fa-message',
                    'label' => $t['private_chat'],
                    'href'  => "/$lang/chat",
                    'badge' => ((int)($ctx['unread_chat'] ?? 0)) > 0 ? (int)$ctx['unread_chat'] : null,
                ],
                ['icon' => 'fa-envelope', 'label' => $t['global_chat'], 'href' => "/$lang/global-chat"],
                [
                    'icon'  => 'fa-user-group',
                    'label' => $t['friends'],
                    'href'  => "/$lang/amici",
                    'badge' => ((int)($ctx['friends'] ?? 0)) > 0 ? (int)$ctx['friends'] : null,
                ],
            ],
        ];

        $extra = [];
        if (!empty($ctx['can_rewind'])) {
            $extra[] = ['icon' => 'fa-clock-rotate-left', 'label' => $t['rewind'], 'href' => "/$lang/rewind"];
        }
        if ($extra) {
            $sections[] = ['id' => 'extra', 'label' => null, 'layout' => 'rows', 'items' => $extra];
        }

        $restricted = [];
        if ($isStaff) {
            $restricted[] = [
                'icon'  => 'fa-shield-halved',
                'label' => $t['admin_panel'],
                'href'  => "/$lang/admin",
                'tone'  => 'staff',
            ];
        }
        if ((int)($ctx['nsfw'] ?? 0) === 1) {
            $restricted[] = [
                'icon'  => 'fa-eye-slash',
                'label' => 'GoonLand',
                'href'  => "/$lang/goonland/home",
                'tone'  => 'nsfw',
            ];
        }

        return [
            'sections'   => $sections,
            'restricted' => $restricted,
            'settings'   => ['icon' => 'fa-gear', 'label' => $t['settings'], 'href' => "/$lang/impostazioni"],
            'logout'     => ['icon' => 'fa-right-from-bracket', 'label' => $t['logout'], 'href' => 'https://cripsum.com/logout'],
        ];
    }

    /**
     * Colonne di una tabella, con cache per richiesta.
     *
     * Lo schema di questo progetto viene migrato a mano, quindi una colonna
     * puo' non esserci ancora: chi legge deve degradare, non esplodere.
     */
    function nav_table_columns(mysqli $mysqli, string $table): array
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return $cache[$table] = [];
        }

        try {
            $res = $mysqli->query('SHOW COLUMNS FROM `' . $table . '`');
        } catch (Throwable $e) {
            return $cache[$table] = [];
        }
        if (!$res) {
            return $cache[$table] = [];
        }

        $cols = [];
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['Field'])) {
                $cols[] = $row['Field'];
            }
        }
        $res->free();

        return $cache[$table] = $cols;
    }

    /**
     * Prima colonna esistente fra quelle candidate, o null.
     */
    function nav_pick_column(mysqli $mysqli, string $table, array $candidates): ?string
    {
        $existing = nav_table_columns($mysqli, $table);
        foreach ($candidates as $wanted) {
            foreach ($existing as $real) {
                if (strcasecmp($real, $wanted) === 0) {
                    return $real;
                }
            }
        }

        return null;
    }

    /**
     * Numeri e pallini del pannello account, in una query sola.
     *
     * La navbar sta su ogni pagina, quindi tutto quello che serve al
     * pannello viene letto insieme: il saldo, le tre statistiche e i
     * conteggi che accendono gli indicatori rossi.
     *
     * Non c'e' cache: un pallino che resta acceso dopo che hai riscosso la
     * missione e' peggio di una query in piu', e le sottoquery girano tutte
     * su colonne indicizzate. La navbar ne fa gia' due per i messaggi.
     *
     * Ogni valore puo' essere null quando la colonna o la tabella non
     * esistono ancora: lo schema qui si migra a mano, quindi il renderer
     * salta la voce invece di mostrare uno zero inventato.
     */
    function nav_user_snapshot(mysqli $mysqli, int $userId): array
    {
        $empty = [
            'money'        => null,
            'streak'       => null,
            'achievements' => null,
            'achv_latest'  => null,
            'missions'     => 0,
            'friends'      => 0,
        ];

        if ($userId <= 0) {
            return $empty;
        }

        $snap = $empty;

        try {
            $select   = [];
            $types    = '';
            $params   = [];
            $joinStat = false;

            $moneyCol = nav_pick_column($mysqli, 'utenti', ['soldi', 'punti', 'points']);
            if ($moneyCol !== null) {
                $select[] = 'u.`' . $moneyCol . '` AS money';
            }

            $streakCol = nav_pick_column($mysqli, 'user_stat_totals', ['current_streak']);
            if ($streakCol !== null && nav_pick_column($mysqli, 'user_stat_totals', ['utente_id']) !== null) {
                $select[] = 's.`' . $streakCol . '` AS streak';
                $joinStat = true;
            }

            $achvUser = nav_pick_column($mysqli, 'utenti_achievement', ['utente_id', 'user_id', 'id_utente']);
            if ($achvUser !== null) {
                $select[] = '(SELECT COUNT(*) FROM `utenti_achievement` a WHERE a.`' . $achvUser . '` = u.id) AS achievements';

                // Serve a decidere se l'utente ha gia' visto gli ultimi
                // sbloccati: non c'e' una colonna "letto", quindi il
                // confronto lo fa il browser con quello che ha memorizzato.
                $achvDate = nav_pick_column($mysqli, 'utenti_achievement', ['data', 'unlocked_at', 'created_at']);
                if ($achvDate !== null) {
                    $select[] = '(SELECT UNIX_TIMESTAMP(MAX(a2.`' . $achvDate . '`)) FROM `utenti_achievement` a2 WHERE a2.`' . $achvUser . '` = u.id) AS achv_latest';
                }
            }

            // Missione finita ma ricompensa non ritirata.
            //
            // Il periodo va filtrato: user_missions conserva anche le
            // giornaliere e settimanali scadute, e quelle restano completate
            // e non riscosse per sempre. La pagina missioni non le mostra
            // (fetchUserMissionsForPeriod legge solo il periodo corrente),
            // quindi contarle accenderebbe un pallino su cui non si puo'
            // fare niente. I due periodi arrivano da mission_generator.php
            // invece che ricalcolati qui: sono la stessa definizione che usa
            // la pagina, e tenerne due allineate a mano finirebbe male.
            $mUser  = nav_pick_column($mysqli, 'user_missions', ['user_id', 'utente_id']);
            $mDone  = nav_pick_column($mysqli, 'user_missions', ['completata', 'completed']);
            $mTaken = nav_pick_column($mysqli, 'user_missions', ['riscattata', 'claimed']);
            $mType  = nav_pick_column($mysqli, 'user_missions', ['tipo']);
            $mPer   = nav_pick_column($mysqli, 'user_missions', ['periodo']);

            if (!function_exists('getMissionDailyPeriod') && file_exists(__DIR__ . '/mission_generator.php')) {
                require_once __DIR__ . '/mission_generator.php';
            }
            $canPeriod = function_exists('getMissionDailyPeriod') && function_exists('getMissionWeeklyPeriod');

            if ($mUser !== null && $mDone !== null && $mTaken !== null
                && $mType !== null && $mPer !== null && $canPeriod) {
                $select[] = '(SELECT COUNT(*) FROM `user_missions` m'
                    . ' WHERE m.`' . $mUser . '` = u.id'
                    . ' AND m.`' . $mDone . '` = 1'
                    . ' AND m.`' . $mTaken . '` = 0'
                    . ' AND ((m.`' . $mType . '` = \'daily\' AND m.`' . $mPer . '` = ?)'
                    . ' OR (m.`' . $mType . '` = \'weekly\' AND m.`' . $mPer . '` = ?))) AS missions';
                $types   .= 'ss';
                $params[] = getMissionDailyPeriod();
                $params[] = getMissionWeeklyPeriod();
            }

            // Richieste di amicizia ricevute e ancora in sospeso.
            $fRecv = nav_pick_column($mysqli, 'friendship_requests', ['receiver_id']);
            $fStat = nav_pick_column($mysqli, 'friendship_requests', ['status']);
            if ($fRecv !== null && $fStat !== null) {
                $select[] = '(SELECT COUNT(*) FROM `friendship_requests` r WHERE r.`' . $fRecv . '` = u.id'
                    . " AND r.`" . $fStat . "` = 'pending') AS friends";
            }

            if (!$select) {
                return $snap;
            }

            $sql = 'SELECT ' . implode(', ', $select) . ' FROM `utenti` u';
            if ($joinStat) {
                $sql .= ' LEFT JOIN `user_stat_totals` s ON s.utente_id = u.id';
            }
            $sql .= ' WHERE u.id = ? LIMIT 1';

            $stmt = $mysqli->prepare($sql);
            if (!$stmt) {
                return $snap;
            }

            // L'utente chiude la lista: il suo segnaposto e' l'ultimo della
            // query, dopo quelli che le sottoquery hanno aggiunto sopra.
            $types   .= 'i';
            $params[] = $userId;
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                foreach (['money', 'streak', 'achievements', 'achv_latest'] as $key) {
                    if (array_key_exists($key, $row) && $row[$key] !== null) {
                        $snap[$key] = (int)$row[$key];
                    }
                }
                foreach (['missions', 'friends'] as $key) {
                    if (array_key_exists($key, $row)) {
                        $snap[$key] = (int)$row[$key];
                    }
                }
            }
        } catch (Throwable $e) {
            return $snap;
        }

        return $snap;
    }
}
