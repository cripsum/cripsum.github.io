<?php
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$uri = $_SERVER['REQUEST_URI'];
$lang = explode('/', trim($uri, '/'))[0];

if (!in_array($lang, ['it', 'en'])) {
    $lang = 'it';
}

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
            <img src="/img/amongus.jpg" height="40px" style="border-radius: 4px" class="d-inline-block align-middle" />
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

            <!-- Voci di navigazione sinistra -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/home">Home page</a></li>
                <li class="nav-item dropdown dropdownutenti">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Memes</a>
                    <ul class="dropdown-menu animate slideIn">
                        <li><a class="dropdown-item" href="/<?= $lang ?>/shitpost">Shitpost</a></li>
                        <li><a class="dropdown-item" href="/<?= $lang ?>/tiktokpedia">TikTokPedia</a></li>
                        <li><a class="dropdown-item" href="/<?= $lang ?>/rimasti">Top rimasti</a></li>
                        <li><a class="dropdown-item" href="/<?= $lang ?>/cripsumpedia/home">CripsumPedia</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown dropdownutenti">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Giochi</a>
                    <ul class="dropdown-menu animate slideIn">
                        <li><a class="dropdown-item" href="/<?= $lang ?>/gambling">Gambling</a></li>
                        <li><a class="dropdown-item" href="/<?= $lang ?>/lootbox">Lootbox</a></li>
                        <li><a class="dropdown-item" href="/<?= $lang ?>/game/">Duelli</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown dropdownutenti">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Shop</a>
                    <ul class="dropdown-menu animate slideIn">
                        <li><a class="dropdown-item" href="/<?= $lang ?>/negozio">Negozio</a></li>
                        <li><a class="dropdown-item" href="/<?= $lang ?>/merch">Merch</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/download">Downloads</a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/donazioni">Donazioni</a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/chisiamo">Chi siamo</a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/edits">Edits</a></li>
            </ul>

            <!--
                Voci destra: SEARCH come primo <li>, poi profilo/login.
                Stando dentro ms-auto la search è sempre a sinistra del profilo,
                sia su desktop che nel menu collassato su mobile.
            -->
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-xl-center">

                <!-- ══ SEARCH BAR ══════════════════════════════════ -->
                <li class="nav-item navbar-search-item" id="navbarSearchItem">
                    <div class="navbar-search-group">
                        <i class="fas fa-search navbar-search-icon"></i>
                        <input
                            type="text"
                            class="navbar-search-input"
                            id="navbarSearchInput"
                            placeholder="Cerca utente…"
                            autocomplete="off"
                            spellcheck="false"
                            maxlength="30"
                            aria-label="Cerca utente"
                            aria-autocomplete="list"
                            aria-controls="navbarSearchDropdown"
                            aria-expanded="false" />
                        <button class="navbar-search-clear" id="navbarSearchClear" tabindex="-1" aria-label="Cancella">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="navbar-search-dropdown" id="navbarSearchDropdown" role="listbox"></div>
                </li>
                <!-- ════════════════════════════════════════════════ -->

                <?php if (!$isLoggedIn): ?>
                    <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/accedi">Accedi</a></li>
                    <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/registrati">Registrati</a></li>
                <?php else: ?>
                    <li class="nav-item dropdown dropdownutenti">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <img src="<?php echo htmlspecialchars($profilePic); ?>&t=<?php echo time(); ?>" alt="Profilo"
                                class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;">
                            <span><?php echo htmlspecialchars($username); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end animate slideIn">
                            <li><a class="dropdown-item" href="/u/<?php echo htmlspecialchars($username); ?>"><i class="fas fa-user me-2"></i>Il mio profilo</a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/impostazioni"><i class="fas fa-cog me-2"></i>Impostazioni</a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/achievements"><i class="fas fa-trophy me-2"></i>Achievements</a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/inventario"><i class="fas fa-box me-2"></i>Inventario</a></li>
                            <!--<li><a class="dropdown-item" href="/<?= $lang ?>/ordini"><i class="fas fa-shopping-bag me-2"></i>I miei ordini</a></li>-->
                            <li><a class="dropdown-item" href="/<?= $lang ?>/global-chat"><i class="fas fa-envelope me-2"></i>Chat Globale</a></li>
                            <?php if ($nsfw === 1): ?>
                                <li><a class="dropdown-item" href="/<?= $lang ?>/goonland/home"><i class="fas fa-eye-slash me-2"></i>GoonLand</a></li>
                            <?php endif; ?>
                            <?php if ($ruolo === 'admin' || $ruolo === 'owner'): ?>
                                <li><a class="dropdown-item" href="/<?= $lang ?>/admin"><i class="fas fa-shield-alt me-2"></i>Pannello Admin</a></li>
                            <?php endif; ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="https://cripsum.com/logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

        </div>
    </div>
</nav>

<?php if ($richpresence === 1): ?>
    <script>
        window.addEventListener('load', function() {
            var script = document.createElement('script');
            script.src = '/js/richpresence.js';
            document.head.appendChild(script);
        });
    </script>
<?php endif; ?>
<?php if ($isLoggedIn): ?>
    <script>
        if (getCookie("achievement1Unlocked")) {} else {
            unlockAchievement(1);
            setCookie("achievement1Unlocked", true);
        }
    </script>
<?php endif; ?>

<!-- ══ NAVBAR SEARCH SCRIPT ════════════════════════════════════════════ -->
<script>
    (function() {
        'use strict';

        const ENDPOINT = '/includes/search_users.php';
        const DEBOUNCE_MS = 280;
        const MIN_CHARS = 2;
        const LANG = '<?= $lang ?>';

        const input = document.getElementById('navbarSearchInput');
        const dropdown = document.getElementById('navbarSearchDropdown');
        const clearBtn = document.getElementById('navbarSearchClear');
        const wrapper = document.getElementById('navbarSearchItem');

        if (!input || !dropdown) return;

        let debounceTimer = null;
        let currentQuery = '';
        let focusedIndex = -1;
        let isMobile = () => window.innerWidth < 1200;

        /* ── helpers ─────────────────────────────────────────── */

        function showDropdown(html) {
            dropdown.innerHTML = html;
            dropdown.classList.add('visible');
            input.setAttribute('aria-expanded', 'true');
        }

        function hideDropdown() {
            // su mobile: rimuovi subito (display:none)
            // su desktop: anima via CSS, poi svuota
            dropdown.classList.remove('visible');
            input.setAttribute('aria-expanded', 'false');
            focusedIndex = -1;
            if (isMobile()) {
                dropdown.innerHTML = '';
            } else {
                setTimeout(() => {
                    if (!dropdown.classList.contains('visible')) dropdown.innerHTML = '';
                }, 200);
            }
        }

        function escHtml(s) {
            return s.replace(/[&<>"']/g, c =>
                ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[c]
            );
        }

        function highlight(text, q) {
            if (!q) return escHtml(text);
            const re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            return escHtml(text).replace(re,
                '<mark style="background:rgba(255,255,255,.22);color:#fff;border-radius:3px;padding:0 2px">$1</mark>'
            );
        }

        function roleLabel(r) {
            return {
                owner: 'Owner',
                admin: 'Admin',
                utente: 'Utente'
            } [r] ?? r;
        }

        /* ── render ──────────────────────────────────────────── */

        function renderResults(users, query) {
            if (!users.length) {
                showDropdown(
                    '<div class="search-status-msg">' +
                    '<i class="fas fa-user-slash"></i> Nessun utente trovato</div>'
                );
                return;
            }

            const items = users.map((u, i) =>
                `<a href="/u/${encodeURIComponent(u.username)}"
                class="search-result-item"
                role="option"
                data-index="${i}"
                tabindex="-1">
                <img src="${escHtml(u.pfp)}"
                     alt="${escHtml(u.username)}"
                     class="search-result-avatar"
                     loading="lazy"
                     onerror="this.src='/img/default_pfp.png'">
                <div class="search-result-info">
                    <span class="search-result-username">${highlight(u.username, query)}</span>
                    <span class="search-result-role ${escHtml(u.ruolo)}">${roleLabel(u.ruolo)}</span>
                </div>
                <i class="fas fa-arrow-up-right-from-square search-result-arrow"></i>
            </a>`
            ).join('');

            showDropdown(
                '<div class="search-dropdown-header">Utenti</div>' + items
            );
        }

        /* ── fetch ───────────────────────────────────────────── */

        async function fetchUsers(query) {
            showDropdown('<div class="search-spinner">Ricerca in corso…</div>');
            try {
                const res = await fetch(
                    `${ENDPOINT}?q=${encodeURIComponent(query)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        signal: AbortSignal.timeout(5000)
                    }
                );
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                if (input.value.trim() !== query) return; // risposta scaduta
                if (data.error) {
                    showDropdown(`<div class="search-status-msg"><i class="fas fa-exclamation-circle"></i> ${escHtml(data.error)}</div>`);
                    return;
                }
                renderResults(data, query);
            } catch (err) {
                if (err.name === 'AbortError' || err.name === 'TimeoutError') return;
                showDropdown('<div class="search-status-msg"><i class="fas fa-wifi"></i> Errore di connessione</div>');
            }
        }

        /* ── navigazione tastiera ────────────────────────────── */

        function getItems() {
            return [...dropdown.querySelectorAll('.search-result-item')];
        }

        function setFocus(idx) {
            const items = getItems();
            items.forEach(el => el.classList.remove('focused'));
            if (idx >= 0 && idx < items.length) {
                items[idx].classList.add('focused');
                items[idx].scrollIntoView({
                    block: 'nearest'
                });
                focusedIndex = idx;
            } else {
                focusedIndex = -1;
            }
        }

        /* ── eventi ──────────────────────────────────────────── */

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

        input.addEventListener('keydown', e => {
            const items = getItems();
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setFocus(Math.min(focusedIndex + 1, items.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const next = focusedIndex - 1;
                if (next < 0) {
                    setFocus(-1);
                    input.focus();
                } else setFocus(next);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (focusedIndex >= 0 && items[focusedIndex]) {
                    items[focusedIndex].click();
                } else if (currentQuery.length >= MIN_CHARS) {
                    window.location.href = `/${LANG}/cerca?q=${encodeURIComponent(currentQuery)}`;
                }
            } else if (e.key === 'Escape') {
                hideDropdown();
                input.blur();
            } else if (e.key === 'Tab') {
                hideDropdown();
            }
        });

        input.addEventListener('focus', () => {
            if (input.value.trim().length >= MIN_CHARS) fetchUsers(input.value.trim());
        });

        clearBtn.addEventListener('click', () => {
            input.value = '';
            clearBtn.style.display = 'none';
            currentQuery = '';
            hideDropdown();
            input.focus();
        });

        dropdown.addEventListener('click', () => {
            setTimeout(hideDropdown, 100);
        });

        /* click fuori → chiudi (solo desktop; su mobile il collapse gestisce) */
        document.addEventListener('click', e => {
            if (!wrapper.contains(e.target)) hideDropdown();
        });

        /* chiudi quando Bootstrap chiude il collapse mobile */
        const navCollapse = document.getElementById('navbarSupportedContent');
        if (navCollapse) {
            navCollapse.addEventListener('hide.bs.collapse', () => hideDropdown());
        }

    })();
</script>
<!-- ═══════════════════════════════════════════════════════════════════ -->