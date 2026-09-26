/**
 * lootbox-ui.js — la schermata iniziale della lootbox e i suoi pop-up.
 *
 * gacha.js tiene lo stato (banner attivo, pity, saldi) e fa le pull; qui
 * c'e' tutto quello che si vede prima di pullare:
 *   - il passaggio tra i banner (sfondo, colore, testo, arte, carte);
 *   - l'arte che tiene la forma delle immagini, con inclinazione al mouse;
 *   - l'elenco dei banner come foglio dal basso sul telefono;
 *   - conti alla rovescia, destino, riscatto premium;
 *   - impostazioni, classifica, cronologia, dettagli, "completa la pull".
 *
 * Espone window.LootboxUI per gacha.js (showBanner, removeBanner, funds...).
 */

'use strict';

(function () {
    const INIT = window.GACHA_INIT || {};
    const EN = INIT.lang === 'en';
    const LOCALE = EN ? 'en-GB' : 'it-IT';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isPhone = () => window.matchMedia('(max-width: 899px)').matches;

    const T = EN ? {
        close: 'Close', retry: 'Try again', error: 'Something went wrong.',
        d: 'd', h: 'h', m: 'm',
        // classifica
        lb_boxes: 'boxes', lb_chars: 'characters', lb_you: 'you', lb_empty: 'Nobody here yet.', lb_error: 'Could not load the leaderboard.',
        // cronologia
        h_sub: (n) => n, h_total: 'total pulls', h_high: 'Legendary+', h_secret: 'secrets', h_pity: 'current pity',
        h_all: 'All', h_high_f: 'Legendary+', h_new_f: 'New', h_ru_f: 'Rate-up',
        h_today: 'Today', h_yesterday: 'Yesterday', h_more: 'Load more',
        h_empty: 'No pulls on this banner yet.', h_empty_sub: 'Your pulls will show up here.',
        h_empty_f: 'No pull matches this filter.',
        h_new: 'NEW', h_ru: 'RATE-UP', h_lost: 'LOST 50/50', h_free: 'FREE', h_pity_at: (n) => `pity ${n}`,
        h_luck: (w, l, p) => `50/50: ${w} won · ${l} lost${p ? ` · secrets at pity ${p} on average` : ''}`,
        h_error: 'Could not load the history.',
        // dettagli
        d_rateup: 'Rate-up', d_rates: 'Rates by rarity', d_perpull: 'per pull',
        d_quota: (q, r) => `${q}% when a ${r} drops`, d_owned: 'owned', d_how: 'How it works', d_all: 'All',
        d_soft: (s) => `From pull <b>${s + 1}</b> the high rarities go up every pull (soft pity).`,
        d_hard: (h, r) => `By pull <b>${h}</b> you surely get a <b>${r}</b> or higher.`,
        d_5050: (q, n) => `When the rate-up rarity drops you have a <b>${q}%</b> chance to get a rate-up; if you lose, the next one is guaranteed. At most <b>${n}</b> pulls.`,
        d_shared: 'Pity and guarantee are <b>shared</b> with the other limited banners.',
        d_multi: (r) => `Every 10× has at least one <b>${r}</b> or higher.`,
        d_pool_std: 'Every character of the standard pool, plus the rate-ups.',
        d_pool_list: 'Only the characters listed in “Characters”.',
        d_pool_cat: (c) => `Every character of the “${c}” category.`,
        d_error: 'Could not load the details.',
        // valute
        f_sub: (q, n) => `Open ${q}× · ${n}`, f_missing: (n) => `You need ${n} more Godo Shard${n === 1 ? '' : 's'}`,
        f_convert: 'We make them on the spot with your Godos, then the pull starts.',
        f_spend: 'Spend', f_get: 'Get', f_after: (g, s) => `After the pull: <b>${g} Godos</b> · <b>${s} Shards</b>`,
        f_cancel: 'Cancel', f_go: 'Convert & open', f_poor: 'Not enough currency',
        f_poor_text: (s, g) => `This pull needs ${s} Shards more, that is ${g} Godos: you don't have enough yet.`,
        f_have: 'You have', f_need: 'You need', f_shop: 'Go to the shop', f_convert_link: 'Convert Godos',
        // varie
        redeem_error: 'Redeem error.', redeem_points: (n) => `+${n} points!`, net_error: 'Network or server error',
        claim: 'Claim', claim_error: 'Error during claim',
    } : {
        close: 'Chiudi', retry: 'Riprova', error: 'Qualcosa è andato storto.',
        d: 'g', h: 'h', m: 'm',
        lb_boxes: 'casse', lb_chars: 'personaggi', lb_you: 'tu', lb_empty: 'Ancora nessuno qui.', lb_error: 'Non riesco a caricare la classifica.',
        h_sub: (n) => n, h_total: 'pull totali', h_high: 'leggendari+', h_secret: 'segreti', h_pity: 'pity attuale',
        h_all: 'Tutte', h_high_f: 'Leggendario+', h_new_f: 'Nuovi', h_ru_f: 'Rate-up',
        h_today: 'Oggi', h_yesterday: 'Ieri', h_more: 'Carica altre',
        h_empty: 'Nessuna pull su questo banner.', h_empty_sub: 'Le tue pull compariranno qui.',
        h_empty_f: 'Nessuna pull con questo filtro.',
        h_new: 'NUOVO', h_ru: 'RATE-UP', h_lost: '50/50 PERSO', h_free: 'GRATIS', h_pity_at: (n) => `pity ${n}`,
        h_luck: (w, l, p) => `50/50: ${w} vinti · ${l} persi${p ? ` · segreti in media al pity ${p}` : ''}`,
        h_error: 'Non riesco a caricare la cronologia.',
        d_rateup: 'Rate-up', d_rates: 'Probabilità per rarità', d_perpull: 'a pull',
        d_quota: (q, r) => `${q}% quando esce un ${r}`, d_owned: 'posseduto', d_how: 'Come funziona', d_all: 'Tutti',
        d_soft: (s) => `Dalla <b>${s + 1}ª pull</b> le rarità alte salgono a ogni pull (soft pity).`,
        d_hard: (h, r) => `Entro la <b>${h}ª pull</b> esce di sicuro un <b>${r}</b> o superiore.`,
        d_5050: (q, n) => `Quando esce la rarità del rate-up hai il <b>${q}%</b> di prendere un rate-up; se perdi, il prossimo è garantito. Al massimo <b>${n}</b> pull.`,
        d_shared: 'Pity e garantito sono <b>condivisi</b> con gli altri banner evento.',
        d_multi: (r) => `Ogni 10× contiene almeno un <b>${r}</b> o superiore.`,
        d_pool_std: 'Tutti i personaggi del pool standard, più i rate-up.',
        d_pool_list: 'Solo i personaggi elencati in «Personaggi».',
        d_pool_cat: (c) => `Tutti i personaggi della categoria «${c}».`,
        d_error: 'Non riesco a caricare i dettagli.',
        f_sub: (q, n) => `Apri ${q}× · ${n}`, f_missing: (n) => `Ti ${n === 1 ? 'manca' : 'mancano'} ${n} Godo Shard${n === 1 ? '' : 's'}`,
        f_convert: 'Li creiamo al volo con i tuoi Godos, poi parte la pull.',
        f_spend: 'Spendi', f_get: 'Ricevi', f_after: (g, s) => `Dopo la pull: <b>${g} Godos</b> · <b>${s} Shards</b>`,
        f_cancel: 'Annulla', f_go: 'Converti e apri', f_poor: 'Valute insufficienti',
        f_poor_text: (s, g) => `Per questa pull ti mancano ${s} Shards, cioè ${g} Godos: per ora non ne hai abbastanza.`,
        f_have: 'Hai', f_need: 'Servono', f_shop: 'Vai allo shop', f_convert_link: 'Converti Godos',
        redeem_error: 'Errore riscatto.', redeem_points: (n) => `+${n} punti!`, net_error: 'Errore di rete o del server',
        claim: 'Riscatta', claim_error: 'Errore durante il riscatto',
    };

    const RC = Object.fromEntries((INIT.rarities || []).map((r) => [r.key, r.color]));
    const RL = Object.fromEntries((INIT.rarities || []).map((r) => [r.key, r.label]));
    const RANK = ['comune', 'raro', 'epico', 'leggendario', 'speciale', 'segreto', 'theone'];
    const banners = new Map((INIT.banners || []).map((b) => [String(b.key), b]));

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
    const decode = (s) => { const t = document.createElement('textarea'); t.innerHTML = String(s ?? ''); return t.value; };
    const num = (n) => Number(n || 0).toLocaleString(LOCALE);
    const media = (p) => {
        const s = String(p || '');
        if (!s) return '/img/cassa.png';
        return /^(https?:)?\//i.test(s) ? s : `/img/${s.split('/').map(encodeURIComponent).join('/')}`;
    };
    const rarity = (r) => String(r ?? 'comune').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[\s_-]+/g, '');
    const toast = (msg, type) => window.GachaUI?.showToast?.(msg, type);
    const pct = (v) => {
        const n = Number(v || 0);
        const digits = n >= 1 ? 2 : n >= 0.01 ? 3 : 4;
        return `${n.toLocaleString(LOCALE, { maximumFractionDigits: digits })}%`;
    };

    /** "4g 2h", "2h 13m", "13m" */
    function shortDuration(ms) {
        const s = Math.max(0, Math.floor(ms / 1000));
        const d = Math.floor(s / 86400);
        const h = Math.floor((s % 86400) / 3600);
        const m = Math.floor((s % 3600) / 60);
        if (d > 0) return `${d}${T.d} ${h}${T.h}`;
        if (h > 0) return `${h}${T.h} ${m}${T.m}`;
        return `${Math.max(1, m)}${T.m}`;
    }
    const parseDate = (raw) => {
        const v = String(raw || '');
        return /T.*([+-]\d{2}:?\d{2}|Z)$/.test(v) ? new Date(v) : new Date(v.replace(' ', 'T'));
    };

    /** Numero che sale fino al valore (per statistiche e classifica). */
    function countUp(el, to, ms = 900) {
        if (!el) return;
        const target = Number(to) || 0;
        if (reduced() || target === 0) { el.textContent = num(target); return; }
        const t0 = performance.now();
        const step = (t) => {
            const k = Math.min(1, (t - t0) / ms);
            const eased = 1 - Math.pow(1 - k, 3);
            el.textContent = num(Math.round(target * eased));
            if (k < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    /* ══════════════════════════════════════════════════════════════════
       ALTEZZA DELLA NAVBAR
       La navbar e' fissa e fluttua sopra la pagina: il contenuto parte
       sotto il suo bordo.
    ══════════════════════════════════════════════════════════════════ */
    function measureNav() {
        const nav = document.querySelector('nav.navbar, header.navbar, #navbar, .navbar');
        const apply = () => {
            if (!nav) return;
            const r = nav.getBoundingClientRect();
            const navH = `${Math.round(r.bottom)}px`;
            if (r.height > 0 && document.body.style.getPropertyValue('--nav-h') !== navH) document.body.style.setProperty('--nav-h', navH);
            measureMbar();
        };
        apply();
        window.addEventListener('resize', apply, { passive: true });
        if (nav && window.ResizeObserver) new ResizeObserver(apply).observe(nav);
        setTimeout(apply, 400);
    }

    /* ══════════════════════════════════════════════════════════════════
       CAMBIO BANNER
    ══════════════════════════════════════════════════════════════════ */
    const view = (key) => document.getElementById(`banner-view-${key}`);
    const railOrder = () => $$('.lb-rail [data-banner-select]').map((c) => c.dataset.bannerSelect);
    const wait = (ms) => new Promise((r) => setTimeout(r, ms));
    const idle = (fn) => (window.requestIdleCallback ? requestIdleCallback(fn, { timeout: 2000 }) : setTimeout(fn, 300));
    let shownKey = null;
    let switchToken = 0;

    /* ── Immagini pronte prima dell'animazione ──────────────────────────
       Sfondo e arte di un banner si scaricano e decodificano quando ci si
       passa sopra (o lo si tocca), e al clic si aspettano al massimo un
       attimo: cosi' l'animazione non si ferma a meta' per decodificarle. */
    const primed = new Map();
    const decoded = new Set();
    function decodeUrl(url) {
        const im = new Image();
        im.decoding = 'async';
        im.src = url;
        decoded.add(im);
        return (im.decode ? im.decode() : new Promise((r) => { im.onload = im.onerror = r; })).catch(() => {});
    }
    function primeBanner(key) {
        key = String(key);
        if (primed.has(key)) return primed.get(key);
        const jobs = [];
        const bg = $(`.lb-bg[data-bg="${CSS.escape(key)}"]`);
        if (bg?.dataset.src) jobs.push(decodeUrl(bg.dataset.src));
        const v = view(key);
        if (v) {
            $$('.lb-art__card img', v).forEach((img) => {
                img.loading = 'eager';
                jobs.push(img.decode ? img.decode().catch(() => {}) : Promise.resolve());
            });
        }
        const p = Promise.all(jobs).then(() => { bg?.classList.add('is-ready'); });
        primed.set(key, p);
        return p;
    }

    /* Stili e impaginazione di una vista nascosta si calcolano prima del
       clic: resta trasparente sotto quella visibile, e inert la toglie a
       tastiera e lettori di schermo. Al cambio resta solo l'animazione. */
    let warmKey = null;
    function coolView(key) {
        const v = view(key);
        if (!v) return;
        v.classList.remove('is-warm');
        v.inert = false;
    }
    function warmView(key) {
        if (isPhone() || key === shownKey || key === warmKey) return;
        if (warmKey) coolView(warmKey);
        const v = view(key);
        if (!v) return;
        warmKey = key;
        v.inert = true;
        v.classList.add('is-warm');
    }

    function onIntent(e) {
        const card = e.target.closest?.('[data-banner-select]');
        if (!card) return;
        primeBanner(card.dataset.bannerSelect);
        if (e.type === 'pointerover') warmView(card.dataset.bannerSelect);
    }

    /** I due banner vicini nell'elenco, con calma: sono i prossimi probabili. */
    function primeNeighbours(key) {
        if (navigator.connection?.saveData) return;
        const order = railOrder();
        const i = order.indexOf(key);
        [order[i + 1], order[i - 1]].filter(Boolean).forEach((k) => idle(() => primeBanner(k)));
    }

    function setBackground(key) {
        $$('.lb-bg').forEach((bg) => {
            const img = bg.firstElementChild;
            if (bg.dataset.bg === key) {
                clearTimeout(bg._clear);
                if (img && !img.style.backgroundImage && bg.dataset.src) {
                    img.style.backgroundImage = `url("${bg.dataset.src.replace(/"/g, '%22')}")`;
                }
                // Se l'immagine non e' ancora pronta lo sfondo resta
                // trasparente e poi entra in dissolvenza, senza comparire di colpo.
                if (!bg.classList.contains('is-ready')) {
                    bg.classList.add('is-waiting');
                    primeBanner(key).then(() => bg.classList.remove('is-waiting'));
                }
                bg.classList.remove('is-leaving');
                bg.classList.add('is-active');
            } else if (bg.classList.contains('is-active')) {
                bg.classList.remove('is-active');
                bg.classList.add('is-leaving');
                // Le GIF degli sfondi spenti non devono restare vive: si
                // tolgono finita la dissolvenza.
                bg._clear = setTimeout(() => {
                    bg.classList.remove('is-leaving');
                    if (img) img.style.backgroundImage = '';
                }, 1000);
            }
        });
        $$('.lb-glow').forEach((g) => g.classList.toggle('is-active', g.dataset.glow === key));
    }

    function markCards(key) {
        // Prima si misura (impaginazione gia' pronta), poi si modifica: cosi'
        // il browser non deve ricalcolare tutto a meta' del clic.
        let railTop = null;
        const list = $('.lb-rail__list');
        const railCard = $(`.lb-rail [data-banner-select="${CSS.escape(key)}"]`);
        if (railCard && list && !isPhone()) {
            const r = railCard.getBoundingClientRect();
            const lr = list.getBoundingClientRect();
            if (r.top < lr.top + 10) railTop = list.scrollTop + r.top - lr.top - 10;
            else if (r.bottom > lr.bottom - 30) railTop = list.scrollTop + r.bottom - lr.bottom + 30;
        }
        let stripLeft = null;
        const track = $('.lb-strip__track');
        const stripCard = $(`.lb-strip [data-banner-select="${CSS.escape(key)}"]`);
        if (stripCard && track && isPhone()) {
            stripLeft = stripCard.offsetLeft - track.clientWidth / 2 + stripCard.offsetWidth / 2;
        }

        $$('[data-banner-select].is-active').forEach((c) => {
            if (c.dataset.bannerSelect === key) return;
            c.classList.remove('is-active');
            c.setAttribute('aria-pressed', 'false');
        });
        $$(`[data-banner-select="${CSS.escape(key)}"]`).forEach((c) => {
            c.classList.add('is-active');
            c.setAttribute('aria-pressed', 'true');
        });

        const behavior = reduced() ? 'auto' : 'smooth';
        if (railTop !== null) list.scrollTo({ top: railTop, behavior });
        if (stripLeft !== null) track.scrollTo({ left: stripLeft, behavior });
    }

    async function showBanner(from, to) {
        to = String(to);
        const next = view(to);
        if (!next) return;
        const token = ++switchToken;

        // Subito la risposta al clic, poi (al massimo 140 ms dopo) il cambio.
        markCards(to);
        closeRail();
        closeAllDestiny();
        await Promise.race([primeBanner(to), wait(140)]);
        if (token !== switchToken) return;

        const prev = view(shownKey);
        const order = railOrder();
        const dir = order.indexOf(to) < order.indexOf(shownKey) ? 'up' : 'down';
        shownKey = to;
        setBackground(to);

        if (prev && prev !== next) {
            prev.classList.remove('is-active');
            prev.dataset.dir = dir;
            if (isPhone() || reduced()) {
                prev.hidden = true;
            } else {
                prev.classList.add('is-leaving');
                setTimeout(() => {
                    prev.classList.remove('is-leaving');
                    if (!prev.classList.contains('is-active')) prev.hidden = true;
                }, 380);
            }
        }
        next.dataset.dir = dir;
        next.hidden = false;
        next.classList.remove('is-leaving', 'is-active');
        if (warmKey === to) {
            coolView(to);
            warmKey = null;
        }
        prepareArt(next);
        void next.offsetWidth;
        next.classList.add('is-active');

        if (isPhone() && window.scrollY > 40) window.scrollTo({ top: 0, behavior: reduced() ? 'auto' : 'smooth' });
        requestAnimationFrame(() => measureMbar());
        setTimeout(() => primeNeighbours(to), 1200);
    }

    let mbarH = 0;
    function measureMbar() {
        if (!isPhone()) return;
        const bar = $('.lb-view.is-active .lb-actions');
        const h = bar ? Math.round(bar.offsetHeight) : 0;
        // Solo se cambia: una variabile sul body ricalcola tutta la pagina.
        if (h && h !== mbarH) {
            mbarH = h;
            document.body.style.setProperty('--mbar-h', `${h}px`);
        }
    }

    /** Toglie un banner finito (limite di pull per utente raggiunto). */
    function removeBanner(key) {
        $$(`[data-banner-select="${CSS.escape(key)}"]`).forEach((card) => {
            const item = card.closest('.lb-rail__item') || card;
            item.classList.add('is-leaving');
            card.style.pointerEvents = 'none';
            if (card.classList.contains('lb-card--mini')) {
                card.style.transition = 'opacity .35s, transform .35s';
                card.style.opacity = '0';
                card.style.transform = 'scale(.9)';
            }
            setTimeout(() => item.remove(), 460);
        });
        setTimeout(() => {
            view(key)?.remove();
            $(`.lb-bg[data-bg="${CSS.escape(key)}"]`)?.remove();
            $(`.lb-glow[data-glow="${CSS.escape(key)}"]`)?.remove();
            // Un gruppo rimasto vuoto perde anche l'etichetta.
            $$('.lb-rail__label').forEach((label) => {
                const next = label.nextElementSibling;
                if (!next || next.classList.contains('lb-rail__label')) label.remove();
            });
        }, 480);
        banners.delete(key);
    }

    /* ══════════════════════════════════════════════════════════════════
       ELENCO DEI BANNER COME FOGLIO (telefono)
    ══════════════════════════════════════════════════════════════════ */
    let railReturn = null;
    function openRail() {
        railReturn = document.activeElement;
        document.body.classList.add('is-rail-open');
        const active = $('.lb-rail [data-banner-select].is-active');
        setTimeout(() => {
            active?.scrollIntoView({ block: 'center', behavior: 'auto' });
            active?.focus({ preventScroll: true });
        }, 60);
    }
    function closeRail() {
        if (!document.body.classList.contains('is-rail-open')) return;
        document.body.classList.remove('is-rail-open');
        if (railReturn && document.contains(railReturn)) railReturn.focus({ preventScroll: true });
    }
    function initRail() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-rail-open]')) openRail();
            if (e.target.closest('[data-rail-close]')) closeRail();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.body.classList.contains('is-rail-open')) closeRail();
        });
        // Trascina giu' la maniglia per chiudere.
        const head = $('.lb-rail__sheet-head');
        const rail = $('.lb-rail');
        if (!head || !rail) return;
        let y0 = 0; let dy = 0; let drag = false;
        head.addEventListener('pointerdown', (e) => {
            if (!isPhone() || e.target.closest('button')) return;
            drag = true; y0 = e.clientY; dy = 0;
            rail.style.transition = 'none';
            head.setPointerCapture(e.pointerId);
        });
        head.addEventListener('pointermove', (e) => {
            if (!drag) return;
            dy = Math.max(0, e.clientY - y0);
            rail.style.transform = `translateY(${dy}px)`;
        });
        const end = () => {
            if (!drag) return;
            drag = false;
            rail.style.transition = '';
            rail.style.transform = '';
            if (dy > 110) closeRail();
        };
        head.addEventListener('pointerup', end);
        head.addEventListener('pointercancel', end);

        // Frecce su e giu' tra le carte della colonna.
        $('.lb-rail__list')?.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
            const cards = $$('.lb-rail [data-banner-select]');
            const i = cards.indexOf(document.activeElement);
            if (i < 0) return;
            e.preventDefault();
            cards[Math.min(cards.length - 1, Math.max(0, i + (e.key === 'ArrowDown' ? 1 : -1)))]?.focus();
        });
    }

    /* ══════════════════════════════════════════════════════════════════
       ARTE: forma delle immagini, personaggi multipli, inclinazione
    ══════════════════════════════════════════════════════════════════ */
    function prepareArt(scope) {
        $$('.lb-art__card', scope).forEach((card) => {
            const img = card.querySelector('img');
            if (!img) return;
            img.loading = 'eager';
            const ready = () => {
                if (img.naturalWidth && img.naturalHeight) {
                    card.style.setProperty('--ar', String(Math.round((img.naturalWidth / img.naturalHeight) * 10000) / 10000));
                }
                card.classList.add('is-loaded');
            };
            if (img.complete && img.naturalWidth) ready();
            else {
                img.addEventListener('load', ready, { once: true });
                img.addEventListener('error', () => card.classList.add('is-loaded'), { once: true });
            }
        });
    }

    function pickArt(art, index) {
        const cards = $$('.lb-art__card', art);
        const n = cards.length;
        cards.forEach((card) => {
            const i = Number(card.dataset.artCard);
            const pos = (i - index + n) % n;
            card.style.setProperty('--pos', String(pos));
            card.classList.toggle('is-front', pos === 0);
            card.style.removeProperty('--rx');
            card.style.removeProperty('--ry');
        });
        $$('[data-art-pick]', art).forEach((b) => {
            const on = Number(b.dataset.artPick) === index;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function initArt() {
        $$('.lb-view').forEach((v) => { if (!v.hidden) prepareArt(v); });
        document.addEventListener('click', (e) => {
            const pick = e.target.closest('[data-art-pick]');
            const card = e.target.closest('.lb-art__card:not(.is-front)');
            const art = (pick || card)?.closest('[data-art]');
            if (!art) return;
            pickArt(art, Number(pick ? pick.dataset.artPick : card.dataset.artCard));
        });

        // Inclinazione leggera che segue il mouse (solo con mouse vero).
        if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
        let raf = 0;
        document.addEventListener('pointermove', (e) => {
            if (reduced()) return;
            const card = e.target.closest?.('.lb-view.is-active .lb-art__card.is-front');
            if (!card || card.closest('.lb-art--chest')) return;
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => {
                const r = card.getBoundingClientRect();
                const x = (e.clientX - r.left) / r.width - 0.5;
                const y = (e.clientY - r.top) / r.height - 0.5;
                card.classList.add('is-tilting');
                card.style.setProperty('--ry', `${(x * 12).toFixed(2)}deg`);
                card.style.setProperty('--rx', `${(-y * 10).toFixed(2)}deg`);
            });
        });
        document.addEventListener('pointerout', (e) => {
            const card = e.target.closest?.('.lb-art__card');
            if (!card || card.contains(e.relatedTarget)) return;
            cancelAnimationFrame(raf);
            card.classList.remove('is-tilting');
            card.style.removeProperty('--rx');
            card.style.removeProperty('--ry');
        });
    }

    /* ══════════════════════════════════════════════════════════════════
       CONTI ALLA ROVESCIA
    ══════════════════════════════════════════════════════════════════ */
    function tickCountdowns() {
        const now = Date.now();
        $$('[data-countdown]').forEach((el) => {
            const diff = parseDate(el.dataset.countdown).getTime() - now;
            if (diff <= 0) {
                if (el.hasAttribute('data-reload-at-zero') && !el.dataset.reloading) {
                    el.dataset.reloading = '1';
                    setTimeout(() => location.reload(), 1500);
                }
                el.textContent = `0${T.m}`;
                return;
            }
            const text = shortDuration(diff);
            if (el.textContent !== text) el.textContent = text;
        });
    }

    /* ══════════════════════════════════════════════════════════════════
       DESTINO (il salvataggio lo fa gacha.js)
    ══════════════════════════════════════════════════════════════════ */
    function closeDestiny(box) {
        if (!box) return;
        box.classList.remove('is-open');
        box.querySelector('[data-destiny-toggle]')?.setAttribute('aria-expanded', 'false');
    }
    const closeAllDestiny = () => $$('[data-destiny].is-open').forEach(closeDestiny);
    function initDestiny() {
        document.addEventListener('click', (e) => {
            const toggle = e.target.closest('[data-destiny-toggle]');
            if (toggle) {
                const box = toggle.closest('[data-destiny]');
                const open = !box.classList.contains('is-open');
                closeAllDestiny();
                if (open) {
                    box.classList.add('is-open');
                    toggle.setAttribute('aria-expanded', 'true');
                }
                return;
            }
            if (!e.target.closest('[data-destiny]')) closeAllDestiny();
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAllDestiny(); });
    }

    /* ══════════════════════════════════════════════════════════════════
       RISCATTO PREMIUM
    ══════════════════════════════════════════════════════════════════ */
    function initPremium() {
        const buttons = $$('[data-premium-claim]');
        if (!buttons.length) return;
        let timer = null;
        const fmt = (s) => [Math.floor(s / 3600), Math.floor((s % 3600) / 60), s % 60].map((v) => String(v).padStart(2, '0')).join(':');

        const setClaimed = (seconds) => {
            let left = seconds;
            buttons.forEach((b) => {
                b.classList.add('claimed');
                b.disabled = true;
                b.querySelector('.btn-text').innerHTML = `<i class="fa-regular fa-clock"></i> <span class="claim-countdown">${fmt(left)}</span>`;
            });
            clearInterval(timer);
            timer = setInterval(() => {
                left -= 1;
                if (left <= 0) {
                    clearInterval(timer);
                    buttons.forEach((b) => {
                        b.classList.remove('claimed');
                        b.disabled = false;
                        b.querySelector('.btn-text').textContent = T.claim;
                    });
                    return;
                }
                $$('.claim-countdown').forEach((c) => { c.textContent = fmt(left); });
            }, 1000);
        };

        const first = buttons[0];
        if (first.classList.contains('claimed')) setClaimed(parseInt(first.dataset.secondsLeft || '0', 10));

        buttons.forEach((btn) => btn.addEventListener('click', async () => {
            if (btn.disabled) return;
            buttons.forEach((b) => { b.disabled = true; });
            try {
                const res = await fetch('/api/premium_daily_claim.php', { method: 'POST', headers: { 'X-CSRF-Token': CSRF } });
                const data = await res.json();
                if (data.success) {
                    buttons.forEach((b) => { b.classList.remove('is-done'); void b.offsetWidth; b.classList.add('is-done'); });
                    setClaimed(parseInt(data.seconds_left || 86400, 10));
                    window.GachaUI?.setSoldi?.(data.new_soldi);
                    toast(data.message, 'success');
                } else {
                    buttons.forEach((b) => { b.disabled = false; });
                    toast(data.error || T.claim_error, 'error');
                }
            } catch (e) {
                buttons.forEach((b) => { b.disabled = false; });
                toast(T.net_error, 'error');
            }
        }));
    }

    /* ══════════════════════════════════════════════════════════════════
       APERTURA DEI POP-UP
    ══════════════════════════════════════════════════════════════════ */
    const M = () => window.LootboxModal;
    function initOpeners() {
        document.addEventListener('click', (e) => {
            const opener = e.target.closest('[data-open]');
            if (opener) {
                const what = opener.dataset.open;
                if (what === 'settings') openSettings();
                if (what === 'leaderboard') openLeaderboard();
                if (what === 'history') openHistory();
            }
            const details = e.target.closest('[data-banner-details]');
            if (details) openDetails(details.dataset.bannerDetails);
        });
        // L'ingranaggio della navbar apriva il modale Bootstrap: ora apre il
        // pop-up nuovo. Bootstrap ascolta i click in cattura sul document,
        // quindi si toglie l'attributo e si intercetta prima, sulla window.
        $$('[data-bs-target="#impostazioniModal"]').forEach((b) => b.removeAttribute('data-bs-toggle'));
        window.addEventListener('click', (e) => {
            const gear = e.target.closest?.('[data-bs-target="#impostazioniModal"]');
            if (!gear) return;
            e.preventDefault();
            e.stopPropagation();
            openSettings();
        }, true);
    }

    /* ── Impostazioni ─────────────────────────────────────────────────── */
    function syncAudioControls() {
        const audio = window.GachaUI?.getAudio?.();
        if (!audio) return;
        const range = $('[data-audio-volume]');
        const val = $('[data-audio-volume-val]');
        const toggle = $('[data-audio-toggle]');
        const icon = $('[data-audio-icon]');
        const v = Math.round(audio.volume * 100);
        if (range && document.activeElement !== range) range.value = String(v);
        if (range) range.style.setProperty('--v', `${range.value}%`);
        if (val) val.textContent = `${range ? range.value : v}%`;
        if (toggle) toggle.checked = !audio.muted;
        if (icon) {
            const quiet = audio.muted || audio.volume === 0;
            icon.className = `fa-solid ${quiet ? 'fa-volume-xmark' : audio.volume < 0.5 ? 'fa-volume-low' : 'fa-volume-high'} lm-row__icon`;
        }
    }

    function initSettings() {
        const range = $('[data-audio-volume]');
        range?.addEventListener('input', () => {
            window.GachaUI?.setVolume?.(Number(range.value) / 100);
            syncAudioControls();
        });
        $('[data-audio-toggle]')?.addEventListener('change', (e) => {
            window.GachaUI?.setMuted?.(!e.target.checked);
            syncAudioControls();
        });
        document.addEventListener('lootbox:audio', syncAudioControls);

        const form = $('[data-redeem-form]');
        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = $('#codiceSegreto');
            const codice = input.value.trim();
            if (!codice) { input.focus(); return; }
            const btn = $('#btnRiscatta');
            const label = $('#btnRiscattaLabel');
            const spin = $('#btnRiscattaSpin');
            btn.disabled = true; label.hidden = true; spin.hidden = false;
            form.classList.remove('is-error');
            try {
                const resp = await fetch('/api/api_redeem_code', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify({ codice, lang: INIT.lang, csrf_token: CSRF }),
                    credentials: 'same-origin',
                });
                const data = await resp.json();
                if (data.status !== 'success') {
                    void form.offsetWidth;
                    form.classList.add('is-error');
                    toast(data.message ?? T.redeem_error, 'error');
                    return;
                }
                input.value = '';
                if (data.tipo === 'personaggio') {
                    M().close('impostazioniModal');
                    setTimeout(() => window.GachaUI?.openRevealWithData(data.personaggio), 280);
                } else if (data.tipo === 'punti') {
                    if (data.soldi_rimasti != null) window.GachaUI?.setSoldi?.(data.soldi_rimasti);
                    toast(`🎁 ${data.descrizione ?? T.redeem_points(data.punti)}`, 'success');
                }
            } catch (err) {
                toast(T.redeem_error, 'error');
            } finally {
                btn.disabled = false; label.hidden = false; spin.hidden = true;
            }
        });
    }

    function openSettings() {
        syncAudioControls();
        M().open('impostazioniModal');
    }

    /* ── Classifica ───────────────────────────────────────────────────── */
    const lbCache = new Map();
    let lbType = 'casse_aperte';

    function avatar(entry, cls = '') {
        const name = decode(entry.username || '?');
        const initials = name.replace(/[^\p{L}\p{N}]/gu, '').slice(0, 2).toUpperCase() || '?';
        const hue = (Number(entry.id) * 47) % 360;
        return `<span class="lm-ava ${cls}" style="--a1:hsl(${hue} 70% 50%);--a2:hsl(${(hue + 60) % 360} 70% 45%)">${esc(initials)}`
            + (entry.avatar ? `<img src="${esc(entry.avatar)}" alt="" loading="lazy" onload="this.classList.add('is-loaded')" onerror="this.remove()">` : '')
            + '</span>';
    }

    function renderLeaderboard(body, data, type) {
        const unit = type === 'casse_aperte' ? T.lb_boxes : T.lb_chars;
        const rows = data.data || [];
        const me = data.me;
        if (!rows.length) {
            body.innerHTML = `<div class="lm-empty"><i class="fa-solid fa-ranking-star"></i><b>${esc(T.lb_empty)}</b></div>`;
            return;
        }
        const isMe = (r) => me && Number(r.id) === Number(me.id);
        const podiumOrder = [rows[1], rows[0], rows[2]].filter(Boolean);
        const podium = `<div class="lm-podium lm-podium--${podiumOrder.length}">${podiumOrder.map((r) => `
            <div class="lm-pod lm-pod--${r.position}${isMe(r) ? ' is-me' : ''}">
                ${r.position === 1 ? '<i class="fa-solid fa-crown lm-crown"></i>' : ''}
                ${avatar(r).replace('</span>', `<span class="lm-pos" style="color:${['', '#fbbf24', '#cbd5e1', '#d97706'][r.position]}">${r.position}</span></span>`)}
                <b>${esc(decode(r.username))}${r.is_premium ? '<img class="cr-premium-gem lm-gem" src="/img/premium.svg" alt="Premium">' : ''}</b>
                <strong data-count="${Number(r.value)}">0</strong><small>${esc(unit)}</small>
            </div>`).join('')}</div>`;

        const rest = rows.slice(3);
        const restRow = (r, i) => `
            <div class="lm-lrow${isMe(r) ? ' is-me' : ''}" style="--i:${i}">
                <span class="lm-n">${r.position}</span>${avatar(r)}
                <b>${esc(decode(r.username))}${r.is_premium ? '<img class="cr-premium-gem lm-gem" src="/img/premium.svg" alt="Premium">' : ''}${isMe(r) ? `<span class="lm-you">· ${esc(T.lb_you)}</span>` : ''}</b>
                <strong><span data-count="${Number(r.value)}">0</span><small>${esc(unit)}</small></strong>
            </div>`;
        const meOutside = me && me.position > rows.length;
        const list = rest.length || meOutside ? `<div class="lm-card lm-stagger">${rest.map(restRow).join('')}
            ${meOutside ? `${rest.length ? '<div class="lm-gap">···</div>' : ''}${restRow(me, rest.length + 1)}` : ''}</div>` : '';

        body.innerHTML = podium + list;
        $$('[data-count]', body).forEach((el, i) => setTimeout(() => countUp(el, el.dataset.count), 120 + i * 30));
    }

    async function loadLeaderboard(type) {
        const dlg = document.getElementById('lootboxLeaderboard');
        const body = $('[data-lb-body]', dlg);
        lbType = type;
        const cached = lbCache.get(type);
        if (cached && Date.now() - cached.t < 60000) {
            renderLeaderboard(body, cached.data, type);
            return;
        }
        body.innerHTML = `<div class="lm-podium">${[1, 2, 3].map((i) => `<div class="lm-skel" style="height:${i === 2 ? 168 : 146}px"></div>`).join('')}</div>
            <div class="lm-skel" style="height:220px"></div>`;
        try {
            const r = await fetch(`/api/get_leaderboard?type=${encodeURIComponent(type)}`, { credentials: 'same-origin' });
            const d = await r.json();
            if (d.status !== 'success') throw new Error();
            lbCache.set(type, { t: Date.now(), data: d });
            if (lbType === type) renderLeaderboard(body, d, type);
        } catch (e) {
            if (lbType !== type) return;
            body.innerHTML = `<div class="lm-empty"><i class="fa-solid fa-triangle-exclamation"></i><b>${esc(T.lb_error)}</b>
                <button type="button" class="lm-btn" data-lb-retry>${esc(T.retry)}</button></div>`;
        }
    }

    function openLeaderboard() {
        const dlg = document.getElementById('lootboxLeaderboard');
        if (!dlg) return;
        if (!dlg.dataset.ready) {
            dlg.dataset.ready = '1';
            dlg.addEventListener('lm:tab', (e) => loadLeaderboard(e.detail.tab));
            dlg.addEventListener('click', (e) => { if (e.target.closest('[data-lb-retry]')) { lbCache.delete(lbType); loadLeaderboard(lbType); } });
        }
        M().open(dlg);
        loadLeaderboard(lbType);
    }

    /* ── Cronologia ───────────────────────────────────────────────────── */
    const H = { key: null, filtro: '', offset: 0, items: [], loading: false, data: null };

    function dayLabel(date) {
        const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const today = new Date(); today.setHours(0, 0, 0, 0);
        const diff = Math.round((today - d) / 86400000);
        if (diff === 0) return T.h_today;
        if (diff === 1) return T.h_yesterday;
        return date.toLocaleDateString(LOCALE, { day: 'numeric', month: 'long', year: date.getFullYear() === today.getFullYear() ? undefined : 'numeric' });
    }

    function historyRow(p, i) {
        const r = rarity(p['rarità']);
        const c = RC[r] || '#fff';
        const when = parseDate(p.created_at);
        const time = when.toLocaleTimeString(LOCALE, { hour: '2-digit', minute: '2-digit' });
        const badges = [
            p.is_new ? `<span class="lm-badge lm-badge--new">${esc(T.h_new)}</span>` : '',
            p.esito_50_50 === 1 || (p.featured && p.esito_50_50 === null) ? `<span class="lm-badge lm-badge--ru"><i class="fa-solid fa-star"></i> ${esc(T.h_ru)}</span>` : '',
            p.esito_50_50 === 0 ? `<span class="lm-badge lm-badge--lost">${esc(T.h_lost)}</span>` : '',
            p.gratuita ? `<span class="lm-badge lm-badge--free">${esc(T.h_free)}</span>` : '',
        ].join('');
        return `<a class="lm-hrow" href="inventario?c=${Number(p.personaggio_id)}" style="--rc:${c};--i:${i}">
            <img src="${esc(media(p.img_url))}" alt="" loading="lazy" onerror="this.src='/img/cassa.png'">
            <div style="min-width:0"><b>${esc(decode(p.nome))}</b><div class="lm-meta"><span class="lm-dot" style="--rc:${c}">${esc(RL[r] || r)}</span>${badges}</div></div>
            <div class="lm-hrow__r"><b>${esc(T.h_pity_at(Number(p.pity_al_momento) + 1))}</b><br>${esc(time)}</div>
        </a>`;
    }

    function renderHistoryList() {
        const list = $('[data-history-list]');
        if (!list) return;
        if (!H.items.length) {
            list.innerHTML = `<div class="lm-empty"><i class="fa-solid fa-clock-rotate-left"></i><b>${esc(H.filtro ? T.h_empty_f : T.h_empty)}</b>${H.filtro ? '' : `<small>${esc(T.h_empty_sub)}</small>`}</div>`;
            return;
        }
        let html = '';
        let lastDay = '';
        let open = false;
        H.items.forEach((p, i) => {
            const day = dayLabel(parseDate(p.created_at));
            if (day !== lastDay) {
                if (open) html += '</div>';
                html += `<p class="lm-day">${esc(day)}</p><div class="lm-card lm-stagger">`;
                open = true;
                lastDay = day;
            }
            html += historyRow(p, i % 20);
        });
        if (open) html += '</div>';
        list.innerHTML = html;
    }

    async function loadHistory(append = false) {
        if (H.loading) return;
        H.loading = true;
        const foot = $('[data-history-foot]');
        const moreBtn = $('[data-history-more]');
        if (moreBtn) { moreBtn.disabled = true; moreBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>'; }
        if (!append) {
            const list = $('[data-history-list]');
            if (list) list.innerHTML = [1, 2, 3, 4].map(() => '<div class="lm-skel" style="height:62px;margin-bottom:8px"></div>').join('');
        }
        try {
            const q = new URLSearchParams({ banner_id: H.key, limit: '30', offset: String(H.offset) });
            if (H.filtro) q.set('filtro', H.filtro);
            const r = await fetch(`/api/api_gacha_history?${q}`, { credentials: 'same-origin' });
            const d = await r.json();
            if (d.status !== 'success') throw new Error(d.message);
            H.data = d;
            H.items = append ? H.items.concat(d.pulls || []) : (d.pulls || []);
            H.offset = H.items.length;

            if (!append) {
                const st = d.stats || {};
                const pity = window.GachaUI?.getPity?.(banners.get(H.key)?.pity_gruppo || 'standard')?.contatore ?? banners.get(H.key)?.pity?.contatore ?? 0;
                const stats = $('[data-history-stats]');
                stats.innerHTML = `
                    <div class="lm-stat"><strong data-count="${Number(d.total || 0)}">0</strong><small>${esc(T.h_total)}</small></div>
                    <div class="lm-stat is-accent" style="--rc:#fbbf24"><strong data-count="${Number(st.alte || 0)}">0</strong><small>${esc(T.h_high)}</small></div>
                    <div class="lm-stat is-accent" style="--rc:#a855f7"><strong data-count="${Number(st.segreti || 0)}">0</strong><small>${esc(T.h_secret)}</small></div>
                    <div class="lm-stat"><strong data-count="${Number(pity)}">0</strong><small>${esc(T.h_pity)}</small></div>`;
                $$('[data-count]', stats).forEach((el) => countUp(el, el.dataset.count));
                const luck = st.vinti_50_50 || st.persi_50_50 ? T.h_luck(st.vinti_50_50, st.persi_50_50, st.pity_medio_segreti) : '';
                foot.innerHTML = `<small>${esc(luck)}</small><span class="lm-sp"></span>${d.altre ? `<button type="button" class="lm-btn" data-history-more>${esc(T.h_more)}</button>` : ''}`;
                foot.hidden = !luck && !d.altre;
            } else {
                const btn = $('[data-history-more]');
                if (btn && !d.altre) btn.remove();
                foot.hidden = !foot.querySelector('small')?.textContent && !d.altre;
            }
            renderHistoryList();
        } catch (e) {
            const list = $('[data-history-list]');
            if (list) list.innerHTML = `<div class="lm-empty"><i class="fa-solid fa-triangle-exclamation"></i><b>${esc(T.h_error)}</b></div>`;
        } finally {
            H.loading = false;
            const btn = $('[data-history-more]');
            if (btn) { btn.disabled = false; btn.textContent = T.h_more; }
        }
    }

    function openHistory() {
        const dlg = document.getElementById('gachaHistoryModal');
        if (!dlg) return;
        const key = String(window.GACHA_INIT?.activeBannerId ?? 'standard');
        const banner = banners.get(key);
        H.key = key; H.filtro = ''; H.offset = 0; H.items = [];
        $('[data-lm-sub]', dlg).textContent = T.h_sub(banner?.nome ?? '');
        const body = $('[data-history-body]', dlg);
        const filters = [['', T.h_all], ['alte', T.h_high_f], ['nuovi', T.h_new_f]];
        if (banner?.featured?.length) filters.push(['rateup', T.h_ru_f]);
        body.innerHTML = `
            <div class="lm-stats" data-history-stats>${[1, 2, 3, 4].map(() => '<div class="lm-skel" style="height:66px"></div>').join('')}</div>
            <div class="lm-filters" role="group">${filters.map(([k, l]) => `<button type="button" data-history-filter="${k}" class="${k === '' ? 'is-on' : ''}">${esc(l)}</button>`).join('')}</div>
            <div data-history-list></div>`;
        $('[data-history-foot]', dlg).hidden = true;

        if (!dlg.dataset.ready) {
            dlg.dataset.ready = '1';
            dlg.addEventListener('click', (e) => {
                const f = e.target.closest('[data-history-filter]');
                if (f && !f.classList.contains('is-on')) {
                    $$('[data-history-filter]', dlg).forEach((b) => b.classList.toggle('is-on', b === f));
                    H.filtro = f.dataset.historyFilter; H.offset = 0; H.items = [];
                    loadHistoryFiltered();
                }
                if (e.target.closest('[data-history-more]')) loadHistory(true);
            });
        }
        M().open(dlg);
        loadHistory(false);
    }

    /** Cambio filtro: le statistiche restano, cambia solo l'elenco. */
    async function loadHistoryFiltered() {
        if (H.loading) return;
        H.loading = true;
        const list = $('[data-history-list]');
        list.innerHTML = [1, 2, 3].map(() => '<div class="lm-skel" style="height:62px;margin-bottom:8px"></div>').join('');
        try {
            const q = new URLSearchParams({ banner_id: H.key, limit: '30', offset: '0' });
            if (H.filtro) q.set('filtro', H.filtro);
            const r = await fetch(`/api/api_gacha_history?${q}`, { credentials: 'same-origin' });
            const d = await r.json();
            if (d.status !== 'success') throw new Error();
            H.items = d.pulls || [];
            H.offset = H.items.length;
            const foot = $('[data-history-foot]');
            const small = foot.querySelector('small')?.outerHTML || '<small></small>';
            foot.innerHTML = `${small}<span class="lm-sp"></span>${d.altre ? `<button type="button" class="lm-btn" data-history-more>${esc(T.h_more)}</button>` : ''}`;
            foot.hidden = !foot.querySelector('small')?.textContent && !d.altre;
            renderHistoryList();
        } catch (e) {
            list.innerHTML = `<div class="lm-empty"><i class="fa-solid fa-triangle-exclamation"></i><b>${esc(T.h_error)}</b></div>`;
        } finally {
            H.loading = false;
        }
    }

    /* ── Dettagli e probabilita' ─────────────────────────────────────── */
    let detailsKey = null;
    async function openDetails(key) {
        const dlg = document.getElementById('gachaDetailsModal');
        if (!dlg) return;
        detailsKey = String(key);
        const v = view(detailsKey);
        const panel = $('[data-details-panel]', dlg);
        panel.style.setProperty('--lm-c', v?.dataset.accent || '#a855f7');
        panel.style.setProperty('--lm-c2', v?.dataset.accentHi || '#60a5fa');
        $('[data-lm-sub]', dlg).textContent = banners.get(detailsKey)?.nome ?? '';
        $('[data-details-count]', dlg).textContent = '';
        const panes = { rates: $('[data-lm-pane="rates"]', dlg), chars: $('[data-lm-pane="chars"]', dlg), rules: $('[data-lm-pane="rules"]', dlg) };
        panes.rates.innerHTML = `<div class="lm-skel" style="height:84px"></div><div class="lm-skel" style="height:230px"></div>`;
        panes.chars.innerHTML = '';
        panes.rules.innerHTML = '';
        M().tab(dlg, 'rates');
        M().open(dlg);

        try {
            const resp = await fetch(`/api/gacha/dettagli.php?banner=${encodeURIComponent(detailsKey)}&lang=${INIT.lang}`, { credentials: 'same-origin' });
            const d = await resp.json();
            if (!d.ok) throw new Error(d.message || T.d_error);
            if (String(key) !== detailsKey) return;

            const pool = d.pool || [];
            $('[data-details-count]', dlg).textContent = `· ${pool.length}`;

            // Probabilita'.
            const featured = pool.filter((p) => p.featured);
            const pity = d.pity || {};
            const quotaRarity = featured[0] ? (RL[featured[0].rarita] || featured[0].rarita) : '';
            const maxProb = Math.max(...(d.rarita || []).map((r) => Number(r.prob) || 0), 1);
            panes.rates.innerHTML = `
                ${featured.length ? `<div class="lm-sec"><h3>${esc(T.d_rateup)}</h3><div class="lm-rucards lm-stagger">${featured.map((f, i) => `
                    <div class="lm-rucard" style="--rc:${RC[f.rarita] || '#fff'};--i:${i}">
                        <img src="${esc(f.img || '/img/cassa.png')}" alt="" onerror="this.src='/img/cassa.png'">
                        <div><b>${esc(decode(f.nome))}</b>
                            <small><span class="lm-dot" style="--rc:${RC[f.rarita] || '#fff'}">${esc(RL[f.rarita] || f.rarita)}</span> · ${esc(pct(f.prob))} ${esc(T.d_perpull)}${f.posseduto ? ` · <span class="lm-own"><i class="fa-solid fa-check"></i> ${esc(T.d_owned)}</span>` : ''}</small>
                            ${pity.quota ? `<small>${esc(T.d_quota(pity.quota, quotaRarity))}</small>` : ''}</div>
                    </div>`).join('')}</div></div>` : ''}
                <div class="lm-sec"><h3>${esc(T.d_rates)}</h3><div class="lm-card lm-card--pad"><div class="lm-rates is-in">
                    ${(d.rarita || []).slice().reverse().map((r, i) => `
                        <div class="lm-rate" style="--rc:${esc(r.colore)};--w:${Math.max(1.5, (Number(r.prob) / maxProb) * 100).toFixed(2)}%;--i:${i}">
                            <span class="lm-dot">${esc(r.label)}</span><span class="lm-rate__track"><i></i></span>
                            <strong>${esc(pct(r.prob))} <small>· ${Number(r.count)}</small></strong>
                        </div>`).join('')}
                </div></div></div>`;

            // Personaggi, dal piu' raro, con filtro per rarita'.
            const present = RANK.slice().reverse().filter((r) => pool.some((p) => p.rarita === r));
            const renderChars = (filter) => {
                const list = pool.filter((p) => !filter || p.rarita === filter);
                const groups = present.filter((r) => !filter || r === filter).map((r) => {
                    // Prima i rate-up, poi quelli trovati, poi i segreti "???".
                    const items = list.filter((p) => p.rarita === r)
                        .sort((a, b) => (b.featured - a.featured) || (b.posseduto - a.posseduto) || ((b.id ? 1 : 0) - (a.id ? 1 : 0)));
                    if (!items.length) return '';
                    return `<div class="lm-group-h"><span class="lm-dot" style="--rc:${RC[r]}">${esc(RL[r] || r)}</span><small>${items.length}</small></div>
                        <div class="lm-chars">${items.map((p, i) => `
                            <div class="lm-char${p.id ? '' : ' is-masked'}${p.posseduto ? ' is-owned' : ''}${p.featured ? ' is-featured' : ''}" style="--rc:${RC[p.rarita] || '#fff'};--i:${i}" title="${esc(decode(p.nome))} · ${esc(pct(p.prob))}">
                                <span class="lm-char__img">${p.img ? `<img src="${esc(p.img)}" alt="" loading="lazy" onerror="this.src='/img/cassa.png'">` : '<span class="lm-q">?</span>'}</span>
                                <span>${esc(decode(p.nome))}</span>
                            </div>`).join('')}</div>`;
                }).join('');
                panes.chars.querySelector('[data-chars-list]').innerHTML = groups;
            };
            panes.chars.innerHTML = `
                <div class="lm-filters" role="group">
                    <button type="button" class="is-on" data-chars-filter="">${esc(T.d_all)}</button>
                    ${present.map((r) => `<button type="button" data-chars-filter="${r}"><span class="lm-dot" style="--rc:${RC[r]}">${esc(RL[r] || r)}</span></button>`).join('')}
                </div>
                <p class="lm-note" style="margin:0">${esc(d.banner?.pool_modo === 'lista' ? T.d_pool_list : d.banner?.pool_modo === 'categoria' ? T.d_pool_cat(d.banner.pool_categoria || '') : T.d_pool_std)}</p>
                <div data-chars-list style="display:grid;gap:14px"></div>`;
            renderChars('');
            panes.chars.onclick = (e) => {
                const b = e.target.closest('[data-chars-filter]');
                if (!b) return;
                $$('[data-chars-filter]', panes.chars).forEach((x) => x.classList.toggle('is-on', x === b));
                renderChars(b.dataset.charsFilter);
            };

            // Regole.
            const tier = (s) => (s === 'speciale' ? INIT.tierLabels?.speciale : s === 'segreto' || s === 'theone' ? INIT.tierLabels?.segreto : RL[s]) || s;
            const rules = [
                ['fa-arrow-trend-up', T.d_soft(Number(pity.soft || 0))],
                ['fa-bullseye', T.d_hard(Number(pity.hard || 0), esc(tier(pity.soglia)))],
                pity.featured_entro ? ['fa-scale-balanced', T.d_5050(pity.quota, pity.featured_entro)] : null,
                pity.condiviso ? ['fa-link', T.d_shared] : null,
                d.garanzia_multi ? ['fa-shield', T.d_multi(esc(RL[d.garanzia_multi] || d.garanzia_multi))] : null,
            ].filter(Boolean);
            panes.rules.innerHTML = `<div class="lm-sec"><h3>${esc(T.d_how)}</h3><div class="lm-rules lm-stagger">${rules.map(([icon, html], i) => `<div class="lm-rule" style="--i:${i}"><i class="fa-solid ${icon}"></i><span>${html}</span></div>`).join('')}</div></div>`;
        } catch (err) {
            panes.rates.innerHTML = `<div class="lm-empty"><i class="fa-solid fa-triangle-exclamation"></i><b>${esc(err.message || T.d_error)}</b></div>`;
        }
    }

    /* ── Completa la pull (valute) ───────────────────────────────────── */
    function funds(o) {
        const dlg = document.getElementById('gachaConversionModal');
        if (!dlg) {
            if (o.canConvert) o.onConfirm?.();
            return;
        }
        const body = $('[data-funds-body]', dlg);
        const foot = $('[data-funds-foot]', dlg);
        $('[data-lm-sub]', dlg).textContent = T.f_sub(o.quantity, o.bannerName || '');
        const godos = '<img src="/img/godos.png" alt="Godos">';
        const shards = '<img src="/img/godoshards.png" alt="Shards">';
        let confirmed = false;

        if (o.canConvert) {
            const afterGodos = Math.max(0, o.soldi - o.pointsCost);
            const afterShards = Math.max(0, o.shards + o.missingShards - o.costShards);
            $('.lm__icon', dlg).innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i>';
            body.innerHTML = `
                <div class="lm-funds lm-stagger"><img class="lm-funds__art" src="/img/godoshards.png" alt="" style="--i:0">
                    <h3 style="--i:1">${esc(T.f_missing(o.missingShards))}</h3><p style="--i:2">${esc(T.f_convert)}</p></div>
                <div class="lm-swap lm-stagger">
                    <div class="lm-swap__side" style="--i:3"><small>${esc(T.f_spend)}</small><strong>${godos} ${num(o.pointsCost)}</strong><span>Godos</span></div>
                    <span class="lm-swap__arrow" style="--i:4"><i class="fa-solid fa-arrow-right"></i></span>
                    <div class="lm-swap__side lm-swap__side--get" style="--i:5"><small>${esc(T.f_get)}</small><strong>${shards} +${num(o.missingShards)}</strong><span>Godo Shards</span></div>
                </div>
                <p class="lm-after">${T.f_after(num(afterGodos), num(afterShards))}</p>`;
            foot.innerHTML = `<button type="button" class="lm-btn" data-lm-close>${esc(T.f_cancel)}</button><span class="lm-sp"></span>
                <button type="button" class="lm-btn lm-btn--main" data-funds-go>${esc(T.f_go)} <i class="fa-solid fa-arrow-right"></i></button>`;
        } else {
            $('.lm__icon', dlg).innerHTML = '<img src="/img/godos.png" alt="" style="width:28px;height:28px;object-fit:contain">';
            body.innerHTML = `
                <div class="lm-funds lm-stagger"><img class="lm-funds__art" src="/img/godos.png" alt="" style="--i:0">
                    <h3 style="--i:1">${esc(T.f_poor)}</h3><p style="--i:2">${esc(T.f_poor_text(num(o.missingShards), num(o.pointsCost)))}</p></div>
                <div class="lm-need lm-stagger">
                    <div class="lm-swap__side" style="--i:3"><small>${esc(T.f_have)}</small><strong>${godos} ${num(o.soldi)}</strong><span>${shards.replace('<img', '<img style="width:14px;height:14px"')} ${num(o.shards)} Shards</span></div>
                    <div class="lm-swap__side lm-swap__side--get" style="--i:4"><small>${esc(T.f_need)}</small><strong>${godos} ${num(o.pointsCost)}</strong><span>= ${num(o.missingShards)} Shards</span></div>
                </div>`;
            foot.innerHTML = `<a class="lm-btn" href="/${INIT.lang}/shop.php#converti">${esc(T.f_convert_link)}</a><span class="lm-sp"></span>
                <a class="lm-btn lm-btn--main" href="/${INIT.lang}/shop.php">${esc(T.f_shop)} <i class="fa-solid fa-arrow-right"></i></a>`;
        }

        foot.onclick = (e) => {
            if (!e.target.closest('[data-funds-go]')) return;
            confirmed = true;
            M().close(dlg, 'confirm');
        };
        M().open(dlg, {
            onClose: () => { if (confirmed) o.onConfirm?.(); },
        });
    }

    /* ══════════════════════════════════════════════════════════════════
       AVVIO
    ══════════════════════════════════════════════════════════════════ */
    function init() {
        measureNav();
        initRail();
        initArt();
        initDestiny();
        initPremium();
        initOpeners();
        initSettings();
        tickCountdowns();
        setInterval(tickCountdowns, 30000);
        // Lo sfondo attivo e' gia' nel markup; gli altri si preparano quando
        // ci si passa sopra, e con calma i due vicini.
        const active = $('.lb-view.is-active');
        shownKey = active?.dataset.bannerId ?? null;
        if (shownKey) {
            $(`.lb-bg[data-bg="${CSS.escape(shownKey)}"]`)?.classList.add('is-ready');
            primeBanner(shownKey);
            setTimeout(() => primeNeighbours(shownKey), 2000);
        }
        document.addEventListener('pointerover', onIntent, { passive: true });
        document.addEventListener('touchstart', onIntent, { passive: true });
        document.addEventListener('focusin', onIntent);
        requestAnimationFrame(() => document.body.classList.add('lb-ready'));
    }

    window.LootboxUI = { showBanner, removeBanner, closeRail, openRail, closeDestiny, funds, openDetails, openHistory, openLeaderboard, openSettings };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
