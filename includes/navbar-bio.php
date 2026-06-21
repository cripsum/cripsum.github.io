<?php
session_start();
require_once __DIR__ . '/mission_tracker.php';
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
if (isLoggedIn()) {
    trackDailyLogin($mysqli, (int)$_SESSION['user_id']);
    trackMissionProgress($mysqli, (int)$_SESSION['user_id'], 'view_page');
}
$uri = $_SERVER['REQUEST_URI'];
$lang = explode('/', trim($uri, '/'))[0];

if (!in_array($lang, ['it', 'en'])) {
    $lang = 'en';
}

$t = [
    'it' => [
        'memes'        => 'Memes',
        'top_rimasti'  => 'Top rimasti',
        'games'        => 'Giochi',
        'duels'        => 'Duelli',
        'shop'         => 'Shop',
        'store'        => 'Negozio',
        'other'        => 'Altro',
        'donations'    => 'Donazioni',
        'about'        => 'Chi siamo',
        'login'        => 'Accedi',
        'register'     => 'Registrati',
        'my_profile'   => 'Il mio profilo',
        'settings'     => 'Impostazioni',
        'inventory'    => 'Inventario',
        'global_chat'  => 'Chat Globale',
        'admin_panel'  => 'Pannello Admin',
        'search_ph'    => 'Cerca utente…',
        'search_lbl'   => 'Cerca utente',
        'search_clear' => 'Cancella ricerca',
        'no_results'   => 'Nessun utente trovato',
        'searching'    => 'Ricerca in corso…',
        'search_err'   => 'Errore nella ricerca, riprova',
        'user_badge'   => 'Utente',
        'my_profile_alt' => 'Profilo',
        'missions'     => 'Missioni',
    ],
    'en' => [
        'memes'        => 'Memes',
        'top_rimasti'  => 'Top braindeads',
        'games'        => 'Games',
        'duels'        => 'Duels',
        'shop'         => 'Shop',
        'store'        => 'Store',
        'other'        => 'More',
        'donations'    => 'Donate',
        'about'        => 'About us',
        'login'        => 'Log in',
        'register'     => 'Sign up',
        'my_profile'   => 'My profile',
        'settings'     => 'Settings',
        'inventory'    => 'Inventory',
        'global_chat'  => 'Global Chat',
        'admin_panel'  => 'Admin Panel',
        'search_ph'    => 'Search user…',
        'search_lbl'   => 'Search user',
        'search_clear' => 'Clear search',
        'no_results'   => 'No users found',
        'searching'    => 'Searching…',
        'search_err'   => 'Search error, try again',
        'user_badge'   => 'User',
        'my_profile_alt' => 'Profile',
        'missions'     => 'Missions',
    ],
][$lang];

$altLang   = ($lang === 'it') ? 'en' : 'it';
$altLabel  = ($lang === 'it') ? 'EN' : 'IT';
$curLabel  = strtoupper($lang);
$switchUrl = preg_replace('#^/' . $lang . '(/|$)#', '/' . $altLang . '$1', $uri);

if ($isLoggedIn) {
    $username = $_SESSION['username'] ?? 'Utente';
    $userId = $_SESSION['user_id'];
    $profilePic = "/includes/get_pfp.php?id=$userId";
    $ruolo = $_SESSION['ruolo'] ?? '';
    $nsfw = $_SESSION['nsfw'] ?? 0;
    $richpresence = $_SESSION['richpresence'] ?? 0;
}
?>

<nav class="navbarutenti navbar navbar-expand-xl fadein">
    <div class="container-fluid">
        <a class="navbar-brand" href="/<?= $lang ?>/home">
            <img src="/img/amongus-logo.jpg" height="40px" style="border-radius: 4px" class="d-inline-block align-middle" />
            <span class="align-middle ms-3 fw-bold testobianco">Cripsum™</span>
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarSupportedContent"
            aria-controls="navbarSupportedContent"
            aria-expanded="false"
            aria-label="Toggle navigation"
            style="z-index: 1000">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/home"><i class="fa-solid fa-home me-2"></i>Home page</a></li>
                <li class="nav-item dropdown dropdownutenti">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="fa-solid fa-image me-2"></i><?= $t['memes'] ?></a>
                    <ul class="dropdown-menu animate slideIn">
                        <li><a class="dropdown-item" href="/<?= $lang ?>/shitpost"><i class="fa-solid fa-fire me-2"></i>Shitpost</a></li>
                        <li><a class="dropdown-item" href="/<?= $lang ?>/tiktokpedia"><i class="fa-brands fa-tiktok me-2"></i>TikTokPedia</a></li>
                        <li><a class="dropdown-item" href="/<?= $lang ?>/rimasti"><i class="fa-solid fa-star me-2"></i><?= $t['top_rimasti'] ?></a></li>
                        <li><a class="dropdown-item" href="/<?= $lang ?>/cripsumpedia/home"><i class="fa-solid fa-book me-2"></i>CripsumPedia</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown dropdownutenti">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="fa-solid fa-gamepad me-2"></i><?= $t['games'] ?></a>
                    <ul class="dropdown-menu animate slideIn">
                        <li><a class="dropdown-item " href="/<?= $lang ?>/gambling"><i class="fa-solid fa-dice me-2"></i>Gambling</a></li>
                        <li><a class="dropdown-item " href="/<?= $lang ?>/lootbox"><i class="fa-solid fa-box-open me-2"></i>Lootbox</a></li>
                        <li><a class="dropdown-item " href="/<?= $lang ?>/game/"><i class="fa-solid fa-gamepad me-2"></i><?= $t['duels'] ?></a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown dropdownutenti">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="fa-solid fa-cart-shopping me-2"></i><?= $t['shop'] ?></a>
                    <ul class="dropdown-menu animate slideIn">
                        <li><a class="dropdown-item" href="/<?= $lang ?>/negozio"><i class="fa-solid fa-store me-2"></i><?= $t['store'] ?></a></li>
                        <li><a class="dropdown-item" href="/<?= $lang ?>/merch"><i class="fa-solid fa-shirt me-2"></i>Merch</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown dropdownutenti">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="fa-solid fa-ellipsis-h me-2"></i><?= $t['other'] ?></a>
                    <ul class="dropdown-menu animate slideIn">
                        <li><a class="dropdown-item" href="/<?= $lang ?>/download"><i class="fa-solid fa-download me-2"></i>Downloads</a></li>
                        <li><a class="dropdown-item" href="/<?= $lang ?>/donazioni"><i class="fa-solid fa-heart me-2"></i><?= $t['donations'] ?></a></li>
                        <li><a class="dropdown-item" href="/<?= $lang ?>/chisiamo"><i class="fa-solid fa-users me-2"></i><?= $t['about'] ?></a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/edits"><i class="fa-solid fa-video me-2"></i>Edits</a></li>
                <div class="navbar-search-wrapper" id="navbarSearch">
                    <div class="navbar-search-group">
                        <i class="fa-solid fa-search navbar-search-icon"></i>
                        <input
                            type="text"
                            class="navbar-search-input"
                            id="navbarSearchInput"
                            placeholder="<?= $t['search_ph'] ?>"
                            autocomplete="off"
                            spellcheck="false"
                            maxlength="30"
                            aria-label="<?= $t['search_lbl'] ?>"
                            aria-autocomplete="list"
                            aria-controls="navbarSearchDropdown"
                            aria-expanded="false" />
                        <button class="navbar-search-clear" id="navbarSearchClear" tabindex="-1" aria-label="<?= $t['search_clear'] ?>">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="navbar-search-dropdown" id="navbarSearchDropdown" role="listbox"></div>
                </div>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item d-flex align-items-center me-2">
                    <?php if (preg_match('#^/(it|en)/edit-profile\b#', $uri)): ?>
                        <a href="<?= htmlspecialchars($switchUrl) ?>"
                            class="lang-switch"
                            aria-label="Switch language to <?= $altLabel ?>"
                            title="Switch to <?= $altLabel ?>">
                            <span class="lang-switch__cur"><?= $curLabel ?></span>
                            <span class="lang-switch__sep">·</span>
                            <span class="lang-switch__alt"><?= $altLabel ?></span>
                        </a>
                    <?php endif; ?>
                </li>
                <?php if (!$isLoggedIn): ?>
                    <li class="nav-item nav-auth-group">
                        <a class="nav-link" href="/<?= $lang ?>/accedi"><i class="fa-solid fa-right-to-bracket me-2"></i><?= $t['login'] ?></a>
                        <a class="nav-link" href="/<?= $lang ?>/registrati"><i class="fa-solid fa-user-plus me-2"></i><?= $t['register'] ?></a>
                    </li>
                <?php else: ?>
                    <li class="nav-item dropdown dropdownutenti dropdownprofilo">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <img src="<?php echo htmlspecialchars($profilePic); ?>&t=<?php echo time(); ?>" alt="<?= $t['my_profile_alt'] ?>"
                                class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;">
                            <span><?php echo htmlspecialchars($username); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end animate slideIn">
                            <li><a class="dropdown-item" href="/u/<?php echo htmlspecialchars($username); ?>"><i class="fa-solid fa-user me-2"></i><?= $t['my_profile'] ?></a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/impostazioni"><i class="fa-solid fa-gear me-2"></i><?= $t['settings'] ?></a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/achievements"><i class="fa-solid fa-trophy me-2"></i>Achievements</a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/missions"><i class="fa-solid fa-bullseye me-2"></i><?= $t['missions'] ?></a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/inventario"><i class="fa-solid fa-box me-2"></i><?= $t['inventory'] ?></a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/global-chat"><i class="fa-solid fa-envelope me-2"></i><?= $t['global_chat'] ?></a></li>
                            <?php if ($nsfw === 1): ?>
                                <li><a class="dropdown-item" href="/<?= $lang ?>/goonland/home"><i class="fa-solid fa-eye-slash me-2"></i>GoonLand</a></li>
                            <?php endif; ?>
                            <?php if ($ruolo === 'admin' || $ruolo === 'owner'): ?>
                                <li><a class="dropdown-item" href="/<?= $lang ?>/admin"><i class="fa-solid fa-shield-halved me-2"></i><?= $t['admin_panel'] ?></a></li>
                            <?php endif; ?>
                            <li class="grid-span-2">
                                <hr class="dropdown-divider">
                            </li>
                            <li class="grid-span-2"><a class="dropdown-item text-danger" href="https://cripsum.com/logout"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<style>
    .lang-switch {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 20px;
        text-decoration: none;
        font-size: .78rem;
        font-weight: 600;
        letter-spacing: .06em;
        color: rgba(255, 255, 255, 0.55);
        transition: border-color .2s, color .2s, background .2s;
        white-space: nowrap;
    }

    .lang-switch:hover {
        border-color: rgba(255, 255, 255, 0.45);
        color: #fff;
        background: rgba(255, 255, 255, 0.07);
    }

    .lang-switch__cur {
        color: #fff;
        font-weight: 700;
    }

    .lang-switch__sep {
        color: rgba(255, 255, 255, 0.25);
        font-weight: 400;
    }

    @media (max-width: 1199.98px) {
        .lang-switch {
            margin: 6px 0 2px 0;
        }
    }
</style>

<?php if ($richpresence === 1): ?>
    <script>
        window.addEventListener('load', function() {
            var script = document.createElement('script');
            script.src = '/js/richpresence.js?v=3';
            document.head.appendChild(script);
        });
    </script>
<?php endif; ?>
<?php if ($isLoggedIn): ?>
    <script>
        window.addEventListener('load', function() {
            var script = document.createElement('script');
            script.src = '/js/unlockAchievement-<?= $lang ?>.js?v=2';
            document.head.appendChild(script);
        });
    </script>
    <script>
        window.addEventListener('load', function() {
            var script = document.createElement('script');
            script.src = '/js/achievements-globali.js?v=4';
            document.head.appendChild(script);
        });
    </script>

<?php endif; ?>
<script>
    (function() {
        'use strict';

        const SEARCH_ENDPOINT = '/includes/search_users.php';
        const DEBOUNCE_MS = 280;
        const MIN_CHARS = 2;
        const MSG_NO_RESULTS = '<?= addslashes($t['no_results']) ?>';
        const MSG_SEARCHING = '<?= addslashes($t['searching']) ?>';
        const MSG_ERROR = '<?= addslashes($t['search_err']) ?>';
        const BADGE_USER = '<?= addslashes($t['user_badge']) ?>';

        const input = document.getElementById('navbarSearchInput');
        const dropdown = document.getElementById('navbarSearchDropdown');
        const clearBtn = document.getElementById('navbarSearchClear');

        if (!input || !dropdown) return;

        let debounceTimer = null;
        let currentQuery = '';
        let focusedIndex = -1;
        let currentLang = '<?= $lang ?>';

        /* ── helpers ─────────────────────────────────────────── */
        function showDropdown(html) {
            dropdown.innerHTML = html;
            dropdown.classList.add('visible');
            input.setAttribute('aria-expanded', 'true');
        }

        function hideDropdown() {
            dropdown.classList.remove('visible');
            input.setAttribute('aria-expanded', 'false');
            focusedIndex = -1;
            // Piccolo delay prima di svuotare così l'animazione out è visibile
            setTimeout(() => {
                if (!dropdown.classList.contains('visible')) dropdown.innerHTML = '';
            }, 200);
        }

        function getRoleBadge(ruolo) {
            const map = {
                owner: 'Owner',
                admin: 'Admin',
                utente: BADGE_USER
            };
            return map[ruolo] ?? ruolo;
        }

        function escapeHtml(str) {
            return str.replace(/[&<>"']/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[c]);
        }

        /* ── highlight del testo cercato ─────────────────────── */
        function highlight(text, query) {
            if (!query) return escapeHtml(text);
            const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const re = new RegExp('(' + escaped + ')', 'gi');
            return escapeHtml(text).replace(re, '<mark style="background:rgba(255,255,255,0.25);color:#fff;border-radius:3px;padding:0 2px">$1</mark>');
        }

        /* ── render risultati ────────────────────────────────── */
        function renderResults(users, query) {
            if (!users.length) {
                showDropdown(`<div class="search-status-msg">${MSG_NO_RESULTS}</div>`);
                return;
            }

            const html = users.map((u, i) => `
            <a href="/u/${encodeURIComponent(u.username)}"
               class="search-result-item"
               role="option"
               data-index="${i}"
               tabindex="-1">
                <img src="${escapeHtml(u.pfp)}"
                     alt="${escapeHtml(u.username)}"
                     class="search-result-avatar"
                     loading="lazy"
                     onerror="this.src='/img/default_pfp.png'">
                <div class="search-result-info">
                    <span class="search-result-username">
                        ${u.display_name && u.display_name.trim() ? highlight(u.display_name, query) : highlight(u.username, query)}
                        ${u.is_premium ? `<i class="fa-solid fa-gem" style="color: #fbbf24; font-size: 0.78rem; margin-left: 4px;" title="Premium"></i>` : ''}
                    </span>
                    ${u.display_name && u.display_name.trim() && u.display_name !== u.username ? `<span class="search-result-handle" style="font-size: 0.75rem; opacity: 0.6; color: var(--ns-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px;">@${highlight(u.username, query)}</span>` : ''}
                    <span class="search-result-role ${escapeHtml(u.ruolo)}">${getRoleBadge(u.ruolo)}</span>
                </div>
                <i class="fa-solid fa-arrow-up-right-from-square search-result-arrow"></i>
            </a>
        `).join('');

            showDropdown(html);
        }

        async function fetchUsers(query) {
            showDropdown(`<div class="search-spinner">${MSG_SEARCHING}</div>`);

            try {
                const res = await fetch(
                    `${SEARCH_ENDPOINT}?q=${encodeURIComponent(query)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        signal: AbortSignal.timeout(5000),
                    }
                );

                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();

                if (input.value.trim() !== query) return;

                if (data.error) {
                    showDropdown(`<div class="search-status-msg">${escapeHtml(data.error)}</div>`);
                    return;
                }

                renderResults(data, query);
            } catch (err) {
                if (err.name === 'AbortError') return;
                showDropdown(`<div class="search-status-msg">${MSG_ERROR}</div>`);
            }
        }

        function getItems() {
            return [...dropdown.querySelectorAll('.search-result-item')];
        }

        function setFocus(index) {
            const items = getItems();
            items.forEach(el => el.classList.remove('focused'));
            if (index >= 0 && index < items.length) {
                items[index].classList.add('focused');
                items[index].scrollIntoView({
                    block: 'nearest'
                });
                focusedIndex = index;
            } else {
                focusedIndex = -1;
            }
        }

        input.addEventListener('input', () => {
            const q = input.value.trim();

            clearBtn.style.display = q.length ? 'block' : 'none';

            clearTimeout(debounceTimer);

            if (q.length < MIN_CHARS) {
                hideDropdown();
                currentQuery = '';
                return;
            }

            if (q === currentQuery) return;
            currentQuery = q;

            debounceTimer = setTimeout(() => fetchUsers(q), DEBOUNCE_MS);
        });

        input.addEventListener('keydown', (e) => {
            const items = getItems();

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    setFocus(Math.min(focusedIndex + 1, items.length - 1));
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    setFocus(Math.max(focusedIndex - 1, -1));
                    if (focusedIndex === -1) input.focus();
                    break;

                case 'Enter':
                    e.preventDefault();
                    if (focusedIndex >= 0 && items[focusedIndex]) {
                        items[focusedIndex].click();
                    } else {
                        const firstItem = items[0];
                        if (firstItem) {
                            firstItem.click();
                        }
                    }
                    break;

                case 'Escape':
                    hideDropdown();
                    input.blur();
                    break;

                case 'Tab':
                    hideDropdown();
                    break;
            }
        });

        input.addEventListener('focus', () => {
            if (input.value.trim().length >= MIN_CHARS) {
                fetchUsers(input.value.trim());
            }
        });

        /* ── clear button ────────────────────────────────────── */
        clearBtn.addEventListener('click', () => {
            input.value = '';
            clearBtn.style.display = 'none';
            currentQuery = '';
            hideDropdown();
            input.focus();
        });

        /* ── click fuori → chiudi ────────────────────────────── */
        document.addEventListener('click', (e) => {
            if (!document.getElementById('navbarSearch').contains(e.target)) {
                hideDropdown();
            }
        });

        /* ── click su risultato → rimuove focus dal input ────── */
        dropdown.addEventListener('click', () => {
            setTimeout(hideDropdown, 120);
        });

    })();
</script>

<?php
$isPublicProfilePage = isset($isPublicProfilePage) && $isPublicProfilePage === true;
if ($isPublicProfilePage):
?>
<!-- ─────────────────────────────────────────────────────────────────────────
     NAVIGATION REDESIGN OVERLAYS & MODALS MARKUP
     ───────────────────────────────────────────────────────────────────────── -->

<!-- 1. Navigation Overlay -->
<div id="profileNavOverlay" class="profile-nav-overlay" style="position: fixed; inset: 0; z-index: 19000; visibility: hidden; pointer-events: none; display: flex; justify-content: center; align-items: center;" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php echo ($lang === 'it') ? 'Menu di navigazione' : 'Navigation menu'; ?>">
    <div class="profile-nav-overlay-backdrop"></div>
    <div class="profile-nav-overlay-container">
        <div class="profile-nav-overlay-header">
            <div class="profile-nav-overlay-logo">
                <img src="/img/amongus-logo.jpg" alt="Logo">
                <span>Cripsum™</span>
            </div>
            <button class="profile-nav-overlay-close-btn js-close-navigation" aria-label="<?php echo ($lang === 'it') ? 'Chiudi navigazione' : 'Close navigation'; ?>">&times;</button>
        </div>
        <div class="profile-nav-overlay-content">
            <div class="profile-nav-grid">
                <!-- Section Memes -->
                <div class="profile-nav-section">
                    <h3><i class="fa-solid fa-image"></i> <?php echo htmlspecialchars($t['memes']); ?></h3>
                    <ul class="profile-nav-links">
                        <li><a href="/<?php echo $lang; ?>/shitpost"><i class="fa-solid fa-fire"></i> Shitpost</a></li>
                        <li><a href="/<?php echo $lang; ?>/tiktokpedia"><i class="fa-brands fa-tiktok"></i> TikTokPedia</a></li>
                        <li><a href="/<?php echo $lang; ?>/rimasti"><i class="fa-solid fa-star"></i> <?php echo htmlspecialchars($t['top_rimasti']); ?></a></li>
                        <li><a href="/<?php echo $lang; ?>/cripsumpedia/home"><i class="fa-solid fa-book"></i> CripsumPedia</a></li>
                    </ul>
                </div>
                <!-- Section Games -->
                <div class="profile-nav-section">
                    <h3><i class="fa-solid fa-gamepad"></i> <?php echo htmlspecialchars($t['games']); ?></h3>
                    <ul class="profile-nav-links">
                        <li><a href="/<?php echo $lang; ?>/gambling"><i class="fa-solid fa-dice"></i> Gambling</a></li>
                        <li><a href="/<?php echo $lang; ?>/lootbox"><i class="fa-solid fa-box-open"></i> Lootbox</a></li>
                        <li><a href="/<?php echo $lang; ?>/game/"><i class="fa-solid fa-gamepad"></i> <?php echo htmlspecialchars($t['duels']); ?></a></li>
                    </ul>
                </div>
                <!-- Section Shop -->
                <div class="profile-nav-section">
                    <h3><i class="fa-solid fa-cart-shopping"></i> <?php echo htmlspecialchars($t['shop']); ?></h3>
                    <ul class="profile-nav-links">
                        <li><a href="/<?php echo $lang; ?>/negozio"><i class="fa-solid fa-store"></i> <?php echo htmlspecialchars($t['store']); ?></a></li>
                        <li><a href="/<?php echo $lang; ?>/merch"><i class="fa-solid fa-shirt"></i> Merch</a></li>
                    </ul>
                </div>
                <!-- Section Other -->
                <div class="profile-nav-section">
                    <h3><i class="fa-solid fa-ellipsis-h"></i> <?php echo htmlspecialchars($t['other']); ?></h3>
                    <ul class="profile-nav-links">
                        <li><a href="/<?php echo $lang; ?>/download"><i class="fa-solid fa-download"></i> Downloads</a></li>
                        <li><a href="/<?php echo $lang; ?>/donazioni"><i class="fa-solid fa-heart"></i> <?php echo htmlspecialchars($t['donations']); ?></a></li>
                        <li><a href="/<?php echo $lang; ?>/chisiamo"><i class="fa-solid fa-users"></i> <?php echo htmlspecialchars($t['about']); ?></a></li>
                        <li><a href="/<?php echo $lang; ?>/edits"><i class="fa-solid fa-video"></i> Edits</a></li>
                    </ul>
                </div>
            </div>

            <!-- User Profile Section inside Overlay -->
            <div class="profile-nav-user-section">
                <?php if (!$isLoggedIn): ?>
                    <div class="profile-nav-auth-buttons">
                        <a href="/<?php echo $lang; ?>/accedi" class="profile-nav-auth-btn primary"><i class="fa-solid fa-right-to-bracket"></i> <?php echo htmlspecialchars($t['login']); ?></a>
                        <a href="/<?php echo $lang; ?>/registrati" class="profile-nav-auth-btn secondary"><i class="fa-solid fa-user-plus"></i> <?php echo htmlspecialchars($t['register']); ?></a>
                    </div>
                <?php else: ?>
                    <div class="profile-nav-user-card">
                        <div class="profile-nav-user-info">
                            <img src="<?php echo htmlspecialchars($profilePic); ?>&t=<?php echo time(); ?>" alt="Avatar">
                            <div>
                                <h4><?php echo htmlspecialchars($username); ?></h4>
                                <span><?php echo htmlspecialchars($t['user_badge']); ?></span>
                            </div>
                        </div>
                        <div class="profile-nav-user-grid">
                            <a href="/u/<?php echo htmlspecialchars($username); ?>"><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($t['my_profile']); ?></a>
                            <a href="/<?php echo $lang; ?>/impostazioni"><i class="fa-solid fa-gear"></i> <?php echo htmlspecialchars($t['settings']); ?></a>
                            <a href="/<?php echo $lang; ?>/achievements"><i class="fa-solid fa-trophy"></i> Achievements</a>
                            <a href="/<?php echo $lang; ?>/missions"><i class="fa-solid fa-bullseye"></i> <?php echo htmlspecialchars($t['missions']); ?></a>
                            <a href="/<?php echo $lang; ?>/inventario"><i class="fa-solid fa-box"></i> <?php echo htmlspecialchars($t['inventory']); ?></a>
                            <a href="/<?php echo $lang; ?>/global-chat"><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($t['global_chat']); ?></a>
                            <?php if (isset($nsfw) && $nsfw === 1): ?>
                                <a href="/<?php echo $lang; ?>/goonland/home"><i class="fa-solid fa-eye-slash"></i> GoonLand</a>
                            <?php endif; ?>
                            <?php if (isset($ruolo) && ($ruolo === 'admin' || $ruolo === 'owner')): ?>
                                <a href="/<?php echo $lang; ?>/admin"><i class="fa-solid fa-shield-halved"></i> <?php echo htmlspecialchars($t['admin_panel']); ?></a>
                            <?php endif; ?>
                            <a href="https://cripsum.com/logout" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 2. User Search Overlay -->
<div id="profileSearchOverlay" class="profile-nav-overlay profile-search-overlay" style="position: fixed; inset: 0; z-index: 19000; visibility: hidden; pointer-events: none; display: flex; justify-content: center; align-items: center;" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php echo ($lang === 'it') ? 'Cerca utenti' : 'Search users'; ?>">
    <div class="profile-nav-overlay-backdrop"></div>
    <div class="profile-nav-overlay-container">
        <div class="profile-nav-overlay-header">
            <div class="profile-nav-overlay-logo">
                <i class="fa-solid fa-search" style="color: var(--accent); font-size: 1.15rem;"></i>
                <span><?php echo ($lang === 'it') ? 'Cerca Utenti' : 'Search Users'; ?></span>
            </div>
            <button class="profile-nav-overlay-close-btn js-close-search" aria-label="<?php echo ($lang === 'it') ? 'Chiudi ricerca' : 'Close search'; ?>">&times;</button>
        </div>
        <div class="profile-search-input-wrap">
            <i class="fa-solid fa-search search-icon"></i>
            <input type="text" id="profileSearchInput" placeholder="<?php echo htmlspecialchars($t['search_ph']); ?>" autocomplete="off" spellcheck="false" maxlength="30">
            <button id="profileSearchClear" style="display: none;" aria-label="<?php echo htmlspecialchars($t['search_clear']); ?>">&times;</button>
        </div>
        <div id="profileSearchResults" class="profile-search-results">
            <div class="profile-search-status"><?php echo ($lang === 'it') ? 'Digita almeno 2 caratteri per iniziare...' : 'Type at least 2 characters to start...'; ?></div>
        </div>
    </div>
</div>

<!-- 3. Report Profile Modal -->
<div id="profileReportModal" class="profile-report-modal" style="position: fixed; inset: 0; z-index: 20000; display: grid; place-items: center; visibility: hidden; pointer-events: none;" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php echo ($lang === 'it') ? 'Segnala profilo' : 'Report profile'; ?>">
    <div class="profile-report-backdrop"></div>
    <div class="profile-report-card">
        <button class="js-close-report" type="button" aria-label="<?php echo ($lang === 'it') ? 'Chiudi' : 'Close'; ?>">&times;</button>
        <div class="profile-report-header">
            <i class="fa-solid fa-flag text-danger" style="font-size: 1.8rem; margin-bottom: 0.5rem; display: inline-block;"></i>
            <h3><?php echo ($lang === 'it') ? 'Segnala Profilo' : 'Report Profile'; ?></h3>
            <p><?php echo ($lang === 'it') ? 'Seleziona il motivo per cui desideri segnalare questo profilo.' : 'Select the reason why you want to report this profile.'; ?></p>
        </div>
        <?php
        $viewed_user_id = 0;
        if (isset($profile['id'])) {
            $viewed_user_id = (int)$profile['id'];
        } elseif (isset($user_cercato_id)) {
            $viewed_user_id = (int)$user_cercato_id;
        }
        ?>
        <form id="profileReportForm" class="profile-report-form">
            <input type="hidden" name="reported_user_id" value="<?php echo $viewed_user_id; ?>">
            <div class="profile-report-options">
                <label class="profile-report-option">
                    <input type="radio" name="report_reason" value="spam" checked>
                    <span><?php echo ($lang === 'it') ? 'Spam / Pubblicità' : 'Spam / Advertising'; ?></span>
                </label>
                <label class="profile-report-option">
                    <input type="radio" name="report_reason" value="inappropriate">
                    <span><?php echo ($lang === 'it') ? 'Inappropriato / NSFW' : 'Inappropriate / NSFW'; ?></span>
                </label>
                <label class="profile-report-option">
                    <input type="radio" name="report_reason" value="harassment">
                    <span><?php echo ($lang === 'it') ? 'Molestie / Bullismo' : 'Harassment / Bullying'; ?></span>
                </label>
                <label class="profile-report-option">
                    <input type="radio" name="report_reason" value="other">
                    <span><?php echo ($lang === 'it') ? 'Altro motivo' : 'Other reason'; ?></span>
                </label>
            </div>
            <div class="profile-report-detail-group">
                <textarea id="profileReportDetail" placeholder="<?php echo ($lang === 'it') ? 'Fornisci ulteriori dettagli (opzionale)...' : 'Provide further details (optional)...'; ?>" maxlength="200"></textarea>
            </div>
            <button type="submit" class="bio-button bio-button--primary" style="width: 100%; margin-top: 0.8rem; border: none; color: #fff;">
                <?php echo ($lang === 'it') ? 'Invia Segnalazione' : 'Submit Report'; ?>
            </button>
        </form>
    </div>
</div>
<?php endif; ?>