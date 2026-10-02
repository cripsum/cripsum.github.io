/**
 * Cripsum™ — Achievement: richiesta di sblocco e popup, su ogni pagina.
 *
 * Un file solo per le due lingue (prima erano due copie identiche più
 * achievements-globali.js). Espone le funzioni di sempre:
 *
 *   unlockAchievement(id)       chiede al server uno sblocco e, se va, lo mostra
 *   showAchievementPopup(x)     mostra il popup di un id o di un achievement già pronto
 *   getCookie / setCookie       i cookie JSON che usano gambling, edit e GoonLand
 *
 * Cosa NON fa più: contare da solo. Tempo sul sito, giorni di visita, casse e
 * personaggi li conta il server (includes/achievements.php), e quando assegna
 * qualcosa lo dice qui attraverso il giro del tempo reale (assets/rt/rt.js).
 * Prima ogni pagina aperta faceva fino a tre richieste al database solo per
 * rifare quei conti, anche a chi non era collegato.
 *
 * Il browser può chiedere solo gli achievement che il server non vede
 * (gambling, le 3 di notte): gli altri, se li chiede, vengono ricontrollati.
 */
(() => {
    'use strict';

    if (window.__cripsumAchievementPopupV3) {
        return;
    }

    const lang = location.pathname.split('/').includes('en') ? 'en' : 'it';
    const T = {
        it: {
            kicker: 'Achievement sbloccato',
            points: (n) => `+${n} punti`,
            reward: (n) => `${n} Godos da riscuotere`,
            more: (n) => `Altri ${n} achievement sbloccati`,
            moreHint: 'Aprili tutti nella pagina degli achievement.',
            close: 'Chiudi',
            fallbackName: 'Achievement sbloccato',
            fallbackText: 'Hai ottenuto un nuovo achievement.'
        },
        en: {
            kicker: 'Achievement unlocked',
            points: (n) => `+${n} points`,
            reward: (n) => `${n} Godos to claim`,
            more: (n) => `${n} more achievements unlocked`,
            moreHint: 'See them all on the achievements page.',
            close: 'Close',
            fallbackName: 'Achievement unlocked',
            fallbackText: 'You earned a new achievement.'
        }
    }[lang];

    const FALLBACK_IMAGE = '/img/achievements/_default.svg';
    const DISPLAY_TIME = 5200;
    const DAY = 86400000;

    // ── Cookie JSON (formato storico, lo leggono anche altri script) ───────

    function getCookie(name) {
        try {
            const cookies = document.cookie ? document.cookie.split('; ') : [];
            for (const cookie of cookies) {
                const index = cookie.indexOf('=');
                if (index === -1 || cookie.slice(0, index) !== name) continue;
                const value = decodeURIComponent(cookie.slice(index + 1));
                try {
                    return JSON.parse(value);
                } catch (_) {
                    return value;
                }
            }
        } catch (_) {
            return null;
        }
        return null;
    }

    function setCookie(name, value) {
        try {
            document.cookie = `${name}=${encodeURIComponent(JSON.stringify(value))}; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;
        } catch (_) {
            /* cookie spenti: niente da ricordare */
        }
    }

    // ── Chi sta guardando ──────────────────────────────────────────────────

    const domReady = new Promise((resolve) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', resolve, { once: true });
        } else {
            resolve();
        }
    });

    /** Id dell'utente collegato, 0 se non lo è, null se la pagina non lo dice (niente navbar). */
    function currentUser() {
        const state = window.CNAV_STATE;
        if (!state) return null;
        return Number(state.userId) || 0;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    // ── Memoria del browser: cosa è già stato chiesto e cosa già mostrato ──

    function readMap(key) {
        try {
            const map = JSON.parse(localStorage.getItem(key) || 'null');
            return map && typeof map === 'object' ? map : {};
        } catch (_) {
            return null; // memoria negata
        }
    }

    function writeMap(key, map, maxAge) {
        const now = Date.now();
        Object.keys(map).forEach((id) => {
            if (now - Number(map[id]) > maxAge) delete map[id];
        });
        try {
            localStorage.setItem(key, JSON.stringify(map));
        } catch (_) {
            /* piena o negata: vale per questa pagina */
        }
    }

    const memory = { shown: new Set(), known: new Set(), denied: new Set() };

    /** Vero la prima volta che questo browser mostra il popup di un achievement. */
    function claimPopup(id, late) {
        if (!id || memory.shown.has(id)) return false;

        const key = 'cripsum.ach.shown.' + (currentUser() || 0);
        const map = readMap(key);
        if (map === null) {
            // Senza memoria condivisa un avviso «in ritardo» tornerebbe a ogni
            // pagina aperta: meglio perderlo.
            if (late) return false;
            memory.shown.add(id);
            return true;
        }
        if (map[id] && Date.now() - Number(map[id]) < DAY) {
            memory.shown.add(id);
            return false;
        }

        map[id] = Date.now();
        writeMap(key, map, DAY);
        memory.shown.add(id);
        return true;
    }

    /** Achievement che questo browser sa già sbloccati: inutile richiederli a ogni pagina. */
    function isKnown(id) {
        if (memory.known.has(id)) return true;
        const map = readMap('cripsum.ach.known.' + (currentUser() || 0));
        return !!(map && map[id] && Date.now() - Number(map[id]) < 7 * DAY);
    }

    function markKnown(id) {
        memory.known.add(id);
        const key = 'cripsum.ach.known.' + (currentUser() || 0);
        const map = readMap(key);
        if (map === null) return;
        map[id] = Date.now();
        writeMap(key, map, 7 * DAY);
    }

    // ── Popup ──────────────────────────────────────────────────────────────

    const state = { queue: [], showing: false, timer: null, remaining: 0, startedAt: 0 };
    const TIERS = ['bronzo', 'argento', 'oro', 'platino', 'diamante'];

    function resolveImage(raw) {
        const value = String(raw || '').trim();
        if (!value) return FALLBACK_IMAGE;
        if (/^(https?:)?\/\//i.test(value) || value.startsWith('/')) return value;
        return '/img/' + value.replace(/^(\.\.\/)+img\//, '');
    }

    function normalize(data, id) {
        const source = Array.isArray(data) ? data[0] : data;
        const pick = (field) => (lang === 'en' && source?.[field + '_en']) || source?.[field] || '';
        const tier = String(source?.livello || source?.tier || '');

        return {
            id,
            nome: String(pick('nome') || source?.name || T.fallbackName),
            descrizione: String(pick('descrizione') || source?.description || T.fallbackText),
            img_url: resolveImage(source?.img_url ?? source?.image),
            punti: Number(source?.punti ?? source?.points) || 0,
            ricompensa: Number(source?.ricompensa ?? source?.reward) || 0,
            livello: TIERS.includes(tier) ? tier : 'bronzo'
        };
    }

    function ensurePopup() {
        // Molte pagine hanno ancora il vecchio <div id="achievement-popup">:
        // si riusa quello, così restano validi gli z-index che gli danno.
        let popup = document.getElementById('achievement-popup');
        if (!popup) {
            popup = document.createElement('div');
            popup.id = 'achievement-popup';
            document.body.appendChild(popup);
        }
        if (popup.dataset.achV3 === '1') return popup;

        popup.dataset.achV3 = '1';
        popup.className = 'popup achievement-popup';
        popup.setAttribute('role', 'status');
        popup.setAttribute('aria-live', 'polite');
        popup.setAttribute('aria-atomic', 'true');
        // Scritta anche in linea: il foglio del profilo rimette nel flusso i
        // figli diretti di body che non la dichiarano qui.
        popup.style.position = 'fixed';
        popup.innerHTML = `
            <a class="achievement-popup__link" data-ach-link>
                <span class="achievement-popup__medal">
                    <img class="achievement-popup__image" data-ach-image src="${FALLBACK_IMAGE}" alt="" width="64" height="64">
                </span>
                <span class="achievement-popup__content">
                    <span class="achievement-popup__kicker" data-ach-kicker></span>
                    <strong class="achievement-popup__title" data-ach-title></strong>
                    <span class="achievement-popup__description" data-ach-text></span>
                    <span class="achievement-popup__meta">
                        <span data-ach-points hidden></span>
                        <span class="achievement-popup__reward" data-ach-reward hidden></span>
                    </span>
                </span>
            </a>
            <button type="button" class="achievement-popup__close" data-ach-close aria-label="${T.close}">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <span class="achievement-popup__bar" aria-hidden="true"><span></span></span>
        `;

        popup.querySelector('[data-ach-close]').addEventListener('click', () => hide());
        // Il conto alla rovescia si ferma finché il puntatore è sopra.
        popup.addEventListener('mouseenter', pause);
        popup.addEventListener('mouseleave', resume);
        popup.addEventListener('focusin', pause);
        popup.addEventListener('focusout', resume);

        return popup;
    }

    function setBadge(element, text) {
        element.hidden = !text;
        element.textContent = text || '';
    }

    function render(item) {
        const popup = ensurePopup();
        const link = popup.querySelector('[data-ach-link]');
        const image = popup.querySelector('[data-ach-image]');
        const summary = !!item.summary;

        TIERS.forEach((tier) => popup.classList.remove('tier-' + tier));
        popup.classList.add('tier-' + (item.livello || 'bronzo'));
        popup.classList.toggle('is-summary', summary);

        popup.querySelector('[data-ach-kicker]').textContent = T.kicker;
        popup.querySelector('[data-ach-title]').textContent = summary ? T.more(item.count) : item.nome;
        popup.querySelector('[data-ach-text]').textContent = summary ? T.moreHint : item.descrizione;
        setBadge(popup.querySelector('[data-ach-points]'), !summary && item.punti > 0 ? T.points(item.punti) : '');
        setBadge(popup.querySelector('[data-ach-reward]'), !summary && item.ricompensa > 0 ? T.reward(item.ricompensa) : '');

        image.onerror = () => {
            image.onerror = null;
            image.src = FALLBACK_IMAGE;
        };
        image.src = summary ? FALLBACK_IMAGE : item.img_url;

        link.href = `/${lang}/achievements` + (!summary && item.id ? `#achievement-${item.id}` : '');

        // Fa ripartire le animazioni anche se il popup era già a schermo.
        popup.classList.remove('is-showing', 'is-hiding', 'show');
        void popup.offsetWidth;
        popup.style.setProperty('--ach-popup-duration', DISPLAY_TIME + 'ms');
        popup.classList.add('is-showing');
        popup.classList.remove('is-paused');
    }

    function arm(ms) {
        clearTimeout(state.timer);
        state.remaining = ms;
        state.startedAt = Date.now();
        state.timer = setTimeout(() => hide(), ms);
    }

    function pause() {
        if (!state.showing) return;
        clearTimeout(state.timer);
        state.remaining = Math.max(900, state.remaining - (Date.now() - state.startedAt));
        document.getElementById('achievement-popup')?.classList.add('is-paused');
    }

    function resume() {
        if (!state.showing) return;
        document.getElementById('achievement-popup')?.classList.remove('is-paused');
        arm(state.remaining);
    }

    function hide() {
        const popup = document.getElementById('achievement-popup');
        clearTimeout(state.timer);

        if (!popup || !state.showing) {
            state.showing = false;
            next();
            return;
        }

        popup.classList.remove('is-showing');
        popup.classList.add('is-hiding');
        setTimeout(() => {
            popup.classList.remove('is-hiding');
            state.showing = false;
            next();
        }, 300);
    }

    function next() {
        if (state.showing || state.queue.length === 0) return;

        state.showing = true;
        let item = state.queue.shift();

        // Tanti sblocchi insieme (il primo ricalcolo dopo un aggiornamento):
        // il primo si mostra, gli altri diventano un riepilogo solo.
        if (!item.summary && state.queue.length >= 3) {
            const rest = state.queue.filter((queued) => !queued.summary).length;
            state.queue = [{ summary: true, count: rest, livello: 'oro' }];
        }

        render(item);
        arm(DISPLAY_TIME);

        try {
            const rt = window.CripsumRT;
            if (rt && rt.prefs && rt.prefs.sound && document.visibilityState === 'visible') rt.playSound();
        } catch (_) {
            /* il suono è un di più */
        }
    }

    function enqueue(item) {
        state.queue.push(item);
        domReady.then(next);
    }

    /**
     * Mostra il popup di un achievement: un id (i dati li chiede al server)
     * o un oggetto già pronto. Lo stesso achievement non compare due volte
     * sullo stesso browser, da qualunque parte arrivi la notizia.
     */
    async function showAchievementPopup(idOrAchievement, options) {
        const isObject = typeof idOrAchievement === 'object' && idOrAchievement !== null;
        const id = Number.parseInt(isObject ? idOrAchievement.id : idOrAchievement, 10) || 0;

        await domReady;
        if (id && !claimPopup(id, !!(options && options.late))) return;

        if (isObject) {
            enqueue(normalize(idOrAchievement, id));
        } else if (id) {
            let data = null;
            try {
                const response = await fetch('/api/get_achievement?achievement_id=' + encodeURIComponent(id), { credentials: 'same-origin' });
                if (response.ok) data = await response.json();
            } catch (_) {
                data = null;
            }
            if (Array.isArray(data) && data.length === 0) return;
            enqueue(normalize(data, id));
        } else {
            enqueue(normalize(null, 0));
        }

        if (id) {
            markKnown(id);
            lightNavbarDot();
            document.dispatchEvent(new CustomEvent('cripsum:achievement', { detail: { id } }));
        }
    }

    /**
     * Accende subito il pallino degli achievement nella navbar (sulla voce
     * del menu e sull'avatar). Al caricamento di una pagina lo decide
     * navbar.js dalla data dell'ultimo sblocco; ma uno sblocco che arriva a
     * pagina già aperta lì non si vedeva fino alla pagina dopo.
     */
    function lightNavbarDot() {
        let lit = false;
        document.querySelectorAll('[data-cnav-new-key="achv"]').forEach((tile) => {
            // Sulla pagina degli achievement lo si sta già guardando.
            if (tile.classList.contains('is-current')) return;
            const dot = tile.querySelector('.cnav-dot');
            if (!dot) return;
            dot.hidden = false;
            const tip = (window.CNAV_I18N || {}).achvNew;
            if (tip) tile.setAttribute('data-cnav-tip', tip);
            lit = true;
        });
        if (lit) {
            document.querySelectorAll('.cnav-trigger--account, .cnav-avatar-btn').forEach((element) => {
                element.classList.add('has-news');
            });
        }
    }

    /**
     * Chiede al server di sbloccare un achievement. Risolve `true` solo se
     * è stato sbloccato adesso.
     */
    async function unlockAchievement(rawId, retried) {
        const id = Number.parseInt(rawId, 10) || 0;
        if (!id) return false;

        await domReady;

        // Chi non è collegato non ha achievement: nessuna richiesta.
        if (currentUser() === 0) return false;
        if (memory.denied.has(id) || isKnown(id)) return false;

        try {
            const token = csrfToken();
            const response = await fetch('/api/set_achievement', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-Token': token },
                body: new URLSearchParams({ achievement_id: String(id), csrf_token: token, lang })
            });

            if (response.status === 403 && retried !== true) {
                // Il server vuole sapere che si è passati da questa pagina, e
                // lo impara dal primo battito di presenza: una richiesta
                // partita appena la pagina si apre può arrivare prima. Si
                // riprova una volta, qualche secondo dopo.
                await new Promise((resolve) => setTimeout(resolve, 5000));
                return unlockAchievement(id, true);
            }

            if (!response.ok) {
                // Rifiutato (non è il momento, o non si sblocca da qui): non
                // si richiede di nuovo finché la pagina resta aperta.
                memory.denied.add(id);
                return false;
            }

            const data = await response.json().catch(() => null);
            if (data && data.status === 'already_unlocked') {
                markKnown(id);
                return false;
            }
            if (!data || data.status !== 'success') return false;

            await showAchievementPopup(data.achievement || id);
            return true;
        } catch (error) {
            console.warn('[Achievement] richiesta non riuscita:', error);
            return false;
        }
    }

    const api = { unlockAchievement, showAchievementPopup, getCookie, setCookie };
    window.__cripsumAchievementPopupV3 = api;
    // Nome storico: alcuni script controllano questo per sapere se c'è.
    window.__cripsumAchievementPopupV2 = api;
    window.unlockAchievement = unlockAchievement;
    window.showAchievementPopup = showAchievementPopup;
    window.getCookie = window.getCookie || getCookie;
    window.setCookie = window.setCookie || setCookie;

    domReady.then(() => {
        // Gli achievement assegnati dal server arrivano dal giro del tempo
        // reale, su qualunque pagina ci si trovi.
        const rt = window.CripsumRT;
        if (rt && typeof rt.onUser === 'function') {
            rt.onUser((event) => {
                if (event && event.t === 'ach' && event.a) {
                    showAchievementPopup(Number(event.a), { late: !!event.late });
                }
            });
        }

        // «Tocca l'erba, ti prego»: le 3 di notte sono quelle di chi guarda,
        // e l'orologio del server non le conosce.
        if (new Date().getHours() === 3) {
            unlockAchievement(12);
        }
    });
})();
