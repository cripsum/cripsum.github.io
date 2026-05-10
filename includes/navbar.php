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
                        <li><a class="dropdown-item " href="/<?= $lang ?>/gambling">Gambling</a></li>
                        <li><a class="dropdown-item " href="/<?= $lang ?>/lootbox">Lootbox</a></li>
                        <li><a class="dropdown-item " href="/<?= $lang ?>/game/">Duelli</a></li>
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

            <!-- ══ SEARCH BAR ══════════════════════════════════════════ -->
            <div class="navbar-search-wrapper" id="navbarSearch">
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
                        aria-expanded="false"
                    />
                    <button class="navbar-search-clear" id="navbarSearchClear" tabindex="-1" aria-label="Cancella ricerca">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="navbar-search-dropdown" id="navbarSearchDropdown" role="listbox"></div>
            </div>
            <!-- ════════════════════════════════════════════════════════ -->

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
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

<!-- ══ SEARCH SCRIPT ═══════════════════════════════════════════════════ -->
<script>
(function () {
    'use strict';

    const SEARCH_ENDPOINT = '/includes/search_users.php';
    const DEBOUNCE_MS     = 280;
    const MIN_CHARS       = 2;

    const input    = document.getElementById('navbarSearchInput');
    const dropdown = document.getElementById('navbarSearchDropdown');
    const clearBtn = document.getElementById('navbarSearchClear');

    if (!input || !dropdown) return;

    let debounceTimer = null;
    let currentQuery  = '';
    let focusedIndex  = -1;
    let currentLang   = '<?= $lang ?>';

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
        const map = { owner: 'Owner', admin: 'Admin', utente: 'Utente' };
        return map[ruolo] ?? ruolo;
    }

    function escapeHtml(str) {
        return str.replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
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
            showDropdown('<div class="search-status-msg">Nessun utente trovato</div>');
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
                    <span class="search-result-username">${highlight(u.username, query)}</span>
                    <span class="search-result-role ${escapeHtml(u.ruolo)}">${getRoleBadge(u.ruolo)}</span>
                </div>
                <i class="fas fa-arrow-up-right-from-square search-result-arrow"></i>
            </a>
        `).join('');

        showDropdown(html);
    }

    /* ── fetch utenti ────────────────────────────────────── */
    async function fetchUsers(query) {
        showDropdown('<div class="search-spinner">Ricerca in corso…</div>');

        try {
            const res = await fetch(
                `${SEARCH_ENDPOINT}?q=${encodeURIComponent(query)}`,
                {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: AbortSignal.timeout(5000),
                }
            );

            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            // Se nel frattempo l'utente ha digitato altro, ignora
            if (input.value.trim() !== query) return;

            if (data.error) {
                showDropdown(`<div class="search-status-msg">${escapeHtml(data.error)}</div>`);
                return;
            }

            renderResults(data, query);
        } catch (err) {
            if (err.name === 'AbortError') return;
            showDropdown('<div class="search-status-msg">Errore nella ricerca, riprova</div>');
        }
    }

    /* ── navigazione tastiera ────────────────────────────── */
    function getItems() {
        return [...dropdown.querySelectorAll('.search-result-item')];
    }

    function setFocus(index) {
        const items = getItems();
        items.forEach(el => el.classList.remove('focused'));
        if (index >= 0 && index < items.length) {
            items[index].classList.add('focused');
            items[index].scrollIntoView({ block: 'nearest' });
            focusedIndex = index;
        } else {
            focusedIndex = -1;
        }
    }

    /* ── eventi input ────────────────────────────────────── */
    input.addEventListener('input', () => {
        const q = input.value.trim();

        // mostra/nascondi clear button
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
                } else if (currentQuery.length >= MIN_CHARS) {
                    // Fallback: vai alla pagina di ricerca se esiste
                    window.location.href = `/${currentLang}/cerca?q=${encodeURIComponent(currentQuery)}`;
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
<!-- ════════════════════════════════════════════════════════════════════ -->
