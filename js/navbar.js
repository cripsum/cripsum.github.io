/**
 * Navbar: menu, pannello account, sheet mobile e ricerca utenti.
 *
 * I menu usano l'API popover del browser, che porta con se' top layer,
 * chiusura con Esc e chiusura al click fuori. Qui restano solo le tre cose
 * che l'API non fa: posizionare il menu sotto al suo trigger, spostare il
 * focus con le frecce, e il trascinamento verso il basso dello sheet.
 */
(function () {
    'use strict';

    var DESKTOP = '(min-width: 1200px)';
    var GAP = 10;
    var EDGE = 8;

    var supportsPopover =
        typeof HTMLElement !== 'undefined' &&
        typeof HTMLElement.prototype.togglePopover === 'function';

    var keyboardNav = false;
    var openerFor = Object.create(null);

    function isDesktop() {
        return window.matchMedia(DESKTOP).matches;
    }

    function pops() {
        return Array.prototype.slice.call(document.querySelectorAll('.cnav-pop'));
    }

    function triggers() {
        return Array.prototype.slice.call(document.querySelectorAll('[popovertarget]'));
    }

    function isOpen(pop) {
        if (supportsPopover) {
            try {
                return pop.matches(':popover-open');
            } catch (e) {
                return pop.classList.contains('is-open');
            }
        }
        return pop.classList.contains('is-open');
    }

    /* ── Posizionamento ─────────────────────────────────────
       Solo da desktop in su: sotto i 1200px il CSS ancora lo sheet al
       fondo della finestra con !important, e qualsiasi stile inline qui
       darebbe solo fastidio. */

    function place(pop, trigger) {
        if (!isDesktop() || !trigger) {
            pop.style.top = '';
            pop.style.left = '';
            pop.style.right = '';
            pop.style.maxHeight = '';
            return;
        }

        var r = trigger.getBoundingClientRect();
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;
        var w = parseFloat(getComputedStyle(pop).width) || 280;
        var anchor = trigger.getAttribute('data-cnav-anchor') || 'start';

        var left = anchor === 'end' ? r.right - w : r.left;
        left = Math.min(left, vw - w - EDGE);
        left = Math.max(EDGE, left);

        var top = r.bottom + GAP;

        pop.style.left = left + 'px';
        pop.style.right = 'auto';
        pop.style.top = top + 'px';
        pop.style.maxHeight = Math.max(160, vh - top - EDGE) + 'px';
        pop.style.setProperty('--cn-origin', anchor === 'end' ? 'top right' : 'top left');
    }

    /* ── Apertura e chiusura ────────────────────────────────── */

    function hide(pop) {
        if (supportsPopover && pop.hasAttribute('popover')) {
            try {
                pop.hidePopover();
                return;
            } catch (e) {
                /* non era aperto */
            }
        }
        pop.classList.remove('is-open');
        syncTriggers(pop, false);
    }

    function hideAll(except) {
        pops().forEach(function (pop) {
            if (pop !== except && isOpen(pop)) {
                hide(pop);
            }
        });
    }

    function syncTriggers(pop, open) {
        triggers().forEach(function (btn) {
            if (btn.getAttribute('popovertarget') === pop.id) {
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        });
    }

    function items(pop) {
        return Array.prototype.slice.call(
            pop.querySelectorAll('[role="menuitem"]:not([disabled])')
        );
    }

    // Lo sheet copre quasi tutto lo schermo: se sotto la pagina continua a
    // scorrere, il pollice che sbaglia bersaglio muove il contenuto dietro.
    // Su desktop il menu e' piccolo e bloccare farebbe sparire la barra di
    // scorrimento, spostando il layout.
    function setScrollLock(on) {
        document.documentElement.classList.toggle('cnav-locked', on && !isDesktop());
    }

    function onOpened(pop) {
        pop.style.removeProperty('--cn-drag');
        syncTriggers(pop, true);
        setScrollLock(true);

        if (keyboardNav) {
            var list = items(pop);
            if (list.length) {
                list[0].focus();
            }
        }
    }

    function onClosed(pop) {
        pop.style.removeProperty('--cn-drag');
        pop.classList.remove('cnav-pop--closing');
        syncTriggers(pop, false);

        if (!pops().some(isOpen)) {
            setScrollLock(false);
        }
    }

    /* ── Fallback per browser senza popover API ─────────────── */

    function wireFallback(pop) {
        pop.classList.add('cnav-pop--fallback');
        pop.removeAttribute('popover');
    }

    function toggleFallback(pop, trigger) {
        if (pop.classList.contains('is-open')) {
            pop.classList.remove('is-open');
            onClosed(pop);
            return;
        }
        hideAll(pop);
        place(pop, trigger);
        pop.classList.add('is-open');
        onOpened(pop);
    }

    /* ── Trascinamento dello sheet ──────────────────────────── */

    function wireDrag(pop) {
        var head = pop.querySelector('.cnav-sheet-head');
        if (!head) {
            return;
        }

        var startY = 0;
        var dy = 0;
        var dragging = false;

        head.addEventListener('pointerdown', function (e) {
            if (isDesktop() || e.button !== 0) {
                return;
            }
            if (e.target.closest('.cnav-sheet-close')) {
                return;
            }
            dragging = true;
            startY = e.clientY;
            dy = 0;
            try {
                head.setPointerCapture(e.pointerId);
            } catch (err) {
                // Senza cattura il trascinamento funziona lo stesso finche'
                // il dito resta sulla maniglia.
            }
            pop.style.transition = 'none';
        });

        head.addEventListener('pointermove', function (e) {
            if (!dragging) {
                return;
            }
            dy = Math.max(0, e.clientY - startY);
            pop.style.setProperty('--cn-drag', dy + 'px');
        });

        function end(e) {
            if (!dragging) {
                return;
            }
            dragging = false;
            pop.style.transition = '';
            try {
                head.releasePointerCapture(e.pointerId);
            } catch (err) {
                /* il puntatore era gia' andato */
            }

            if (dy > 90) {
                pop.classList.add('cnav-pop--closing');
                pop.style.removeProperty('--cn-drag');
                hide(pop);
            } else {
                pop.style.removeProperty('--cn-drag');
            }
        }

        head.addEventListener('pointerup', end);
        head.addEventListener('pointercancel', end);
    }

    /* ── Tastiera dentro un menu ────────────────────────────── */

    function wireKeys(pop) {
        pop.addEventListener('keydown', function (e) {
            var list = items(pop);
            if (!list.length) {
                return;
            }
            var i = list.indexOf(document.activeElement);

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                list[i < 0 ? 0 : Math.min(i + 1, list.length - 1)].focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                list[i <= 0 ? 0 : i - 1].focus();
            } else if (e.key === 'Home') {
                e.preventDefault();
                list[0].focus();
            } else if (e.key === 'End') {
                e.preventDefault();
                list[list.length - 1].focus();
            } else if (e.key === 'Tab') {
                hide(pop);
            }
        });

        // La navigazione avviene comunque, ma chiudere subito evita che il
        // menu resti aperto sopra la pagina nuova durante il caricamento.
        pop.addEventListener('click', function (e) {
            if (e.target.closest('a[href]')) {
                hide(pop);
            }
        });
    }

    /* ── Avvio ──────────────────────────────────────────────── */

    function init() {
        var list = pops();
        if (!list.length) {
            return;
        }

        list.forEach(function (pop) {
            if (!supportsPopover) {
                wireFallback(pop);
            }

            wireDrag(pop);
            wireKeys(pop);

            pop.addEventListener('beforetoggle', function (e) {
                if (e.newState === 'open') {
                    place(pop, openerFor[pop.id]);
                } else {
                    pop.classList.add('cnav-pop--closing');
                }
            });

            pop.addEventListener('toggle', function (e) {
                if (e.newState === 'open') {
                    pop.classList.remove('cnav-pop--closing');
                    onOpened(pop);
                } else {
                    onClosed(pop);
                }
            });
        });

        triggers().forEach(function (btn) {
            var id = btn.getAttribute('popovertarget');
            var pop = document.getElementById(id);
            if (!pop || !pop.classList.contains('cnav-pop')) {
                return;
            }

            btn.setAttribute('aria-controls', id);
            if (btn.getAttribute('popovertargetaction') !== 'hide') {
                btn.setAttribute('aria-expanded', 'false');
            }

            var closes = btn.getAttribute('popovertargetaction') === 'hide';

            btn.addEventListener('click', function () {
                if (closes) {
                    if (!supportsPopover) {
                        pop.classList.remove('is-open');
                        onClosed(pop);
                    }
                    return;
                }

                openerFor[id] = btn;

                if (!supportsPopover) {
                    toggleFallback(pop, btn);
                }
            });
        });

        document.addEventListener('keydown', function (e) {
            keyboardNav = e.key === 'Tab' || e.key === 'Enter' || e.key === ' ' || e.key.indexOf('Arrow') === 0;

            if (e.key === 'Escape' && !supportsPopover) {
                hideAll(null);
            }
        });

        document.addEventListener('pointerdown', function (e) {
            keyboardNav = false;

            if (supportsPopover) {
                return;
            }
            if (e.target.closest('.cnav-pop') || e.target.closest('[popovertarget]')) {
                return;
            }
            hideAll(null);
        });

        // Il collapse dell'hamburger e il menu sono due livelli diversi: se
        // il primo si chiude, il secondo non deve restare a mezz'aria.
        var collapse = document.getElementById('navbarSupportedContent');
        if (collapse) {
            collapse.addEventListener('hide.bs.collapse', function () {
                hideAll(null);
            });
        }

        // Passando fra sheet e menu ancorato cambia il modo di posizionare:
        // riaprirlo e' piu' onesto che spostarlo a meta' animazione.
        var mq = window.matchMedia(DESKTOP);
        var onChange = function () {
            hideAll(null);
        };
        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', onChange);
        } else if (typeof mq.addListener === 'function') {
            mq.addListener(onChange);
        }

        window.addEventListener('resize', function () {
            pops().forEach(function (pop) {
                if (isOpen(pop)) {
                    place(pop, openerFor[pop.id]);
                }
            });
        });

        window.addEventListener('scroll', function () {
            if (!isDesktop()) {
                return;
            }
            pops().forEach(function (pop) {
                if (isOpen(pop)) {
                    place(pop, openerFor[pop.id]);
                }
            });
        }, { passive: true });
    }

    /* ── Indicatori di notifica ─────────────────────────────────
       Missioni da riscuotere, richieste di amicizia e chat non lette le
       conta il server. Gli achievement no: a database non c'e' uno stato
       "gia' visto", quindi il confronto lo fa il browser fra la data
       dell'ultimo sbloccato e l'ultima volta che la pagina e' stata
       aperta. Il pallino sull'avatar riassume tutto, perche' il pannello
       resta chiuso quasi sempre. */

    var SEEN_PREFIX = 'cnav.achvSeen.';

    function seenKey() {
        var state = window.CNAV_STATE || {};
        return SEEN_PREFIX + (state.userId || 0);
    }

    function readSeen() {
        try {
            return parseInt(localStorage.getItem(seenKey()), 10) || 0;
        } catch (e) {
            // Navigazione privata o storage negato: senza memoria il pallino
            // resta acceso, che e' il male minore rispetto a nasconderlo.
            return 0;
        }
    }

    function writeSeen(value) {
        try {
            localStorage.setItem(seenKey(), String(value));
        } catch (e) {
            /* niente da fare */
        }
    }

    function refreshAccountDot() {
        // Niente ispezione del DOM: a pannello chiuso e' tutto display:none e
        // ogni misura darebbe zero. Le due sorgenti sono il conteggio del
        // server e il pallino degli achievement, che decide il browser.
        var state = window.CNAV_STATE || {};
        var achvDot = document.querySelector('[data-cnav-new-key="achv"] .cnav-dot');
        var lit = !!state.hasNews || !!(achvDot && !achvDot.hidden);

        Array.prototype.forEach.call(
            document.querySelectorAll('.cnav-trigger--account, .cnav-avatar-btn'),
            function (el) {
                el.classList.toggle('has-news', lit);
            }
        );
    }

    function initNotifications() {
        var tile = document.querySelector('[data-cnav-new-key="achv"]');
        if (!tile) {
            refreshAccountDot();
            return;
        }

        var latest = parseInt(tile.getAttribute('data-cnav-new-since'), 10) || 0;
        var dot = tile.querySelector('.cnav-dot');

        // "Sono sulla pagina achievements?" lo ha gia' deciso il server
        // marcando il riquadro come corrente: rifare il confronto sull'URL
        // qui vorrebbe dire tenere due regole allineate a mano.
        if (tile.classList.contains('is-current')) {
            writeSeen(latest || Math.floor(Date.now() / 1000));
            if (dot) {
                dot.hidden = true;
            }
        } else if (dot && latest > 0 && latest > readSeen()) {
            dot.hidden = false;
            tile.setAttribute('data-cnav-tip', (window.CNAV_I18N || {}).achvNew || '');
        }

        refreshAccountDot();
    }

    /* ── Tooltip ────────────────────────────────────────
       Quello del browser compare dopo circa un secondo, non si puo'
       impaginare e sotto ai menu (che stanno nel top layer) finirebbe
       dietro. Anche questo e' un popover, cosi' condivide quello strato.
       Su touch non si mostra: un tooltip appeso al dito copre solo il
       bersaglio, e le etichette che contano si vedono comunque. */

    function initTooltips() {
        var tip = document.getElementById('cnavTip');
        if (!tip || !window.matchMedia('(hover: hover)').matches) {
            return;
        }

        if (supportsPopover) {
            tip.setAttribute('popover', 'manual');
        }

        var target = null;
        var timer = null;

        function place() {
            if (!target) {
                return;
            }
            var r = target.getBoundingClientRect();
            var t = tip.getBoundingClientRect();
            var vw = document.documentElement.clientWidth;
            var gap = 8;

            var top = r.top - t.height - gap;
            if (top < 4) {
                top = r.bottom + gap;
            }

            var left = r.left + (r.width / 2) - (t.width / 2);
            left = Math.max(6, Math.min(left, vw - t.width - 6));

            tip.style.top = Math.round(top) + 'px';
            tip.style.left = Math.round(left) + 'px';
        }

        function open(el) {
            var text = el.getAttribute('data-cnav-tip');
            if (!text) {
                return;
            }
            target = el;
            tip.textContent = text;

            if (supportsPopover) {
                try {
                    tip.showPopover();
                } catch (e) {
                    tip.classList.add('is-open');
                }
            } else {
                tip.classList.add('is-open');
            }
            place();
        }

        function close() {
            clearTimeout(timer);
            target = null;
            if (supportsPopover) {
                try {
                    tip.hidePopover();
                } catch (e) {
                    /* non era aperto */
                }
            }
            tip.classList.remove('is-open');
        }

        function schedule(el) {
            clearTimeout(timer);
            timer = setTimeout(function () {
                open(el);
            }, 240);
        }

        document.addEventListener('pointerover', function (e) {
            var el = e.target.closest ? e.target.closest('[data-cnav-tip]') : null;
            if (!el) {
                if (target) {
                    close();
                }
                return;
            }
            if (el !== target) {
                schedule(el);
            }
        });

        document.addEventListener('focusin', function (e) {
            var el = e.target.closest ? e.target.closest('[data-cnav-tip]') : null;
            if (el) {
                open(el);
            }
        });

        document.addEventListener('focusout', close);
        document.addEventListener('pointerdown', close);
        window.addEventListener('scroll', close, { passive: true });
        window.addEventListener('resize', close);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                close();
            }
        });
    }

    /* ── Hamburger senza Bootstrap ──────────────────────────────
       Diciassette pagine hanno la navbar ma non caricano il bundle JS di
       Bootstrap (fra cui imposta_password). Prima ci pensava la navbar
       tirandosi giu' Bootstrap da un CDN al volo; per aprire un menu a
       fisarmonica bastano queste righe. Il controllo sta nel gestore e non
       all'avvio perche' su molte pagine Bootstrap arriva dopo di noi. */

    function initCollapse() {
        var toggler = document.querySelector('.navbarutenti .navbar-toggler[data-bs-target]');
        if (!toggler) {
            return;
        }
        var target = document.querySelector(toggler.getAttribute('data-bs-target'));
        if (!target) {
            return;
        }

        toggler.addEventListener('click', function (e) {
            if (window.bootstrap && window.bootstrap.Collapse) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();

            var open = target.classList.toggle('show');
            toggler.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (!open) {
                hideAll(null);
            }
        });
    }

    /* ── Ricerca utenti ─────────────────────────────────────── */

    function initSearch() {
        var ENDPOINT = '/includes/search_users.php';
        var DEBOUNCE_MS = 280;
        var MIN_CHARS = 2;
        var i18n = window.CNAV_I18N || {};

        var input = document.getElementById('navbarSearchInput');
        var dropdown = document.getElementById('navbarSearchDropdown');
        var clearBtn = document.getElementById('navbarSearchClear');
        var wrapper = document.getElementById('navbarSearch');

        if (!input || !dropdown) {
            return;
        }

        var debounceTimer = null;
        var currentQuery = '';
        var focusedIndex = -1;

        function showDropdown(html) {
            dropdown.innerHTML = html;
            dropdown.classList.add('visible');
            input.setAttribute('aria-expanded', 'true');
        }

        function hideDropdown() {
            dropdown.classList.remove('visible');
            input.setAttribute('aria-expanded', 'false');
            focusedIndex = -1;

            setTimeout(function () {
                if (!dropdown.classList.contains('visible')) {
                    dropdown.innerHTML = '';
                }
            }, 200);
        }

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function (c) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                }[c];
            });
        }

        function roleBadge(ruolo) {
            var map = { owner: 'Owner', admin: 'Admin', utente: i18n.badgeUser || 'Utente' };
            return map[ruolo] || ruolo;
        }

        function highlight(text, query) {
            if (!query) {
                return escapeHtml(text);
            }
            var escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return escapeHtml(text).replace(
                new RegExp('(' + escaped + ')', 'gi'),
                '<mark class="cnav-mark">$1</mark>'
            );
        }

        function renderResults(users, query) {
            if (!users.length) {
                showDropdown('<div class="search-status-msg">' + escapeHtml(i18n.noResults || '') + '</div>');
                return;
            }

            showDropdown(users.map(function (u, i) {
                var hasDisplay = u.display_name && u.display_name.trim();
                var name = hasDisplay ? highlight(u.display_name, query) : highlight(u.username, query);
                var gem = u.is_premium
                    ? '<i class="fa-solid fa-gem" style="color:#fbbf24;font-size:0.78rem;margin-left:4px" title="Premium"></i>'
                    : '';
                var handle = hasDisplay && u.display_name !== u.username
                    ? '<span class="search-result-handle">@' + highlight(u.username, query) + '</span>'
                    : '';

                return '<a href="/u/' + encodeURIComponent(u.username) + '"' +
                    ' class="search-result-item" role="option" data-index="' + i + '" tabindex="-1">' +
                    '<img src="' + escapeHtml(u.pfp) + '" alt="" class="search-result-avatar" loading="lazy"' +
                    ' onerror="this.src=\'/img/default_pfp.png\'">' +
                    '<div class="search-result-info">' +
                    '<span class="search-result-username">' + name + gem + '</span>' +
                    handle +
                    '<span class="search-result-role ' + escapeHtml(u.ruolo) + '">' + escapeHtml(roleBadge(u.ruolo)) + '</span>' +
                    '</div>' +
                    '<i class="fa-solid fa-arrow-up-right-from-square search-result-arrow"></i>' +
                    '</a>';
            }).join(''));
        }

        function fetchUsers(query) {
            showDropdown('<div class="search-spinner">' + escapeHtml(i18n.searching || '') + '</div>');

            fetch(ENDPOINT + '?q=' + encodeURIComponent(query), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: AbortSignal.timeout ? AbortSignal.timeout(5000) : undefined
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }
                    return res.json();
                })
                .then(function (data) {
                    if (input.value.trim() !== query) {
                        return;
                    }
                    if (data.error) {
                        showDropdown('<div class="search-status-msg">' + escapeHtml(data.error) + '</div>');
                        return;
                    }
                    renderResults(data, query);
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    showDropdown('<div class="search-status-msg">' + escapeHtml(i18n.error || '') + '</div>');
                });
        }

        function getItems() {
            return Array.prototype.slice.call(dropdown.querySelectorAll('.search-result-item'));
        }

        function setFocus(index) {
            var list = getItems();
            list.forEach(function (el) {
                el.classList.remove('focused');
            });
            if (index >= 0 && index < list.length) {
                list[index].classList.add('focused');
                list[index].scrollIntoView({ block: 'nearest' });
                focusedIndex = index;
            } else {
                focusedIndex = -1;
            }
        }

        input.addEventListener('input', function () {
            var q = input.value.trim();
            clearBtn.style.display = q.length ? 'block' : 'none';
            clearTimeout(debounceTimer);

            if (q.length < MIN_CHARS) {
                hideDropdown();
                currentQuery = '';
                return;
            }
            if (q === currentQuery) {
                return;
            }
            currentQuery = q;
            debounceTimer = setTimeout(function () {
                fetchUsers(q);
            }, DEBOUNCE_MS);
        });

        input.addEventListener('keydown', function (e) {
            var list = getItems();

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setFocus(Math.min(focusedIndex + 1, list.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setFocus(Math.max(focusedIndex - 1, -1));
                if (focusedIndex === -1) {
                    input.focus();
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                var target = focusedIndex >= 0 ? list[focusedIndex] : list[0];
                if (target) {
                    target.click();
                }
            } else if (e.key === 'Escape') {
                hideDropdown();
                input.blur();
            } else if (e.key === 'Tab') {
                hideDropdown();
            }
        });

        input.addEventListener('focus', function () {
            if (input.value.trim().length >= MIN_CHARS) {
                fetchUsers(input.value.trim());
            }
        });

        // "/" porta il cursore nella ricerca, come su GitHub o YouTube.
        // offsetParent nullo vuol dire che il campo sta nel menu chiuso.
        document.addEventListener('keydown', function (e) {
            if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) {
                return;
            }
            var el = document.activeElement;
            var tag = el ? el.tagName : '';
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (el && el.isContentEditable)) {
                return;
            }
            if (!input.offsetParent) {
                return;
            }
            e.preventDefault();
            input.focus();
            input.select();
        });

        clearBtn.addEventListener('click', function () {
            input.value = '';
            clearBtn.style.display = 'none';
            currentQuery = '';
            hideDropdown();
            input.focus();
        });

        document.addEventListener('click', function (e) {
            if (wrapper && !wrapper.contains(e.target)) {
                hideDropdown();
            }
        });

        dropdown.addEventListener('click', function () {
            setTimeout(hideDropdown, 120);
        });
    }

    /* ── Badge inbox: la barra mobile e quella desktop ─────── */

    function initBadges() {
        var desktop = document.getElementById('inbox-unread-count');
        var mobile = document.getElementById('inbox-unread-count-mobile');
        if (!desktop || !mobile) {
            return;
        }

        // inbox.php riscrive il badge con il numero grezzo, e oltre il
        // centinaio non ci sta nella pastiglia. Riscrivere '99+' non ricade
        // in questo ramo (parseInt('99+') fa 99), quindi non si cicla.
        function cap(el) {
            var raw = (el.textContent || '').trim();
            var n = parseInt(raw, 10);
            if (!isNaN(n) && n > 99) {
                el.textContent = '99+';
            }
        }

        function hasUnread(el) {
            return !el.classList.contains('d-none') && (el.textContent || '').trim() !== '0';
        }

        function sync() {
            cap(desktop);
            mobile.textContent = desktop.textContent;
            mobile.classList.toggle('d-none', desktop.classList.contains('d-none'));

            var lit = hasUnread(desktop);
            Array.prototype.forEach.call(document.querySelectorAll('.cnav-inbox'), function (btn) {
                btn.classList.toggle('has-unread', lit);
            });
        }

        sync();
        new MutationObserver(sync).observe(desktop, {
            attributes: true,
            childList: true,
            characterData: true
        });
    }

    function boot() {
        init();
        initCollapse();
        initTooltips();
        initSearch();
        initBadges();
        initNotifications();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
