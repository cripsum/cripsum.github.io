/*
 * Inventario.
 *
 * I dati arrivano gia' nella pagina (#inv-data, da gacha_collection_payload):
 * niente richieste al caricamento. Le carte si creano una volta sola; i
 * filtri le spostano e le nascondono senza ricostruire l'HTML.
 *
 * I filtri stanno nell'indirizzo (?r=segreto&s=owned...), cosi' si possono
 * condividere e il tasto indietro funziona; ?c=<id> apre un personaggio.
 *
 * Animazioni: solo transform e opacity, e niente del tutto con "riduci
 * movimento" attivo nel sistema.
 */
(() => {
    'use strict';

    const root = document.getElementById('inventory');
    const dataEl = document.getElementById('inv-data');
    if (!root || !dataEl) return;

    let data;
    try {
        data = JSON.parse(dataEl.textContent || 'null');
    } catch (e) {
        data = null;
    }
    if (!data) {
        root.classList.add('is-ready');
        return;
    }

    const lang = root.dataset.lang === 'en' ? 'en' : 'it';
    const locale = lang === 'en' ? 'en-GB' : 'it-IT';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const $ = (sel, el = document) => el.querySelector(sel);
    const $$ = (sel, el = document) => Array.from(el.querySelectorAll(sel));
    const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[c]));
    const num = (v) => Number(v || 0).toLocaleString(locale);
    const motionQuery = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    const reducedMotion = () => Boolean(motionQuery?.matches);
    const FRAG_IMG = '/img/frammento.svg';
    const fragIcon = (cls = '') => `<img src="${FRAG_IMG}" alt="" class="inv-frag-ico ${cls}" aria-hidden="true">`;

    const T = {
        it: {
            results: (n) => `${n} ${n === 1 ? 'personaggio' : 'personaggi'}`,
            progress: (a, b) => `${a} su ${b} personaggi`,
            standard: (a, b) => `Standard ${a}/${b}`,
            boxes: 'Casse aperte', dupes: 'Con doppioni', upgradable: 'Potenziabili', news: 'Nuovi', fragments: 'Frammenti',
            status: { owned: 'Posseduti', missing: 'Mancanti', duplicates: 'Doppioni', upgradable: 'Potenziabili', limited: 'Limitati', new: 'Nuovi', favorites: 'Preferiti', wishlist: 'Wishlist' },
            unknown: '???', notOwned: 'Non trovato', lv: 'Lv.', max: 'MAX', limited: 'Limitato', limitedSuffix: 'limitato', newBadge: 'NEW',
            noCategory: 'Senza categoria',
            sort: { label: 'Ordina per', rarity: 'Rarità (dalla comune)', rarity_desc: 'Rarità (dalla più alta)', recent: 'Più recenti', name: 'Nome A-Z', quantity: 'Più copie', level: 'Livello' },
            group: { label: 'Raggruppa', rarity: 'Per rarità', category: 'Per categoria', none: 'Nessun gruppo' },
            tabs: { info: 'Panoramica', upgrade: 'Potenziamento', kit: 'Abilità' },
            copies: 'Copie', found: 'Trovato il', last: 'Ultima copia', category: 'Categoria',
            noDesc: 'Nessuna descrizione.', traits: 'Tratti',
            whereTitle: 'Dove trovarlo', whereMissing: 'Dove trovarlo', notInPools: 'Non si trova in nessun banner attivo: tienilo d\'occhio in «Prossimamente».',
            bannerKinds: { standard: 'Sempre disponibile', evento: 'Banner evento', selezione: 'Banner selezione', principiante: 'Banner principiante' },
            rateup: 'Rate-up', inPool: 'Nel pool', endsIn: (t) => `Scade tra ${t}`, startsIn: (t) => `Inizia tra ${t}`, free: 'Gratis', cost: (c) => `${c} Godos a pull`,
            animation: 'Guarda animazione', play: 'Ascolta', stop: 'Ferma',
            favorite: 'Preferito', addFavorite: 'Aggiungi ai preferiti',
            wishlistAdd: 'Aggiungi alla wishlist', wishlistOn: 'Nella wishlist',
            wishlistHelp: 'Ti avvisiamo nella posta quando arriva in un banner.',
            missingTitle: 'Non ce l\'hai ancora', maskedTitle: 'Personaggio segreto', maskedText: 'Lo scoprirai quando lo trovi.',
            level: 'Livello', duplicatesOf: (d, r) => `Doppioni ${d} / ${r}`, upgrade: 'Potenzia', maxReached: 'Potenza massima raggiunta',
            missingCopies: (n) => `Ti mancano ${n} ${n === 1 ? 'copia' : 'copie'} per il prossimo livello.`,
            ready: 'Pronto per il potenziamento!',
            confirmUpgrade: (c, n, l) => `Consumare <strong>${c}</strong> di <strong>${esc(n)}</strong> per portarlo al livello <strong>${l}</strong>?`,
            confirmUpgradeTitle: 'Potenziare?',
            upgradeAllTitle: 'Potenziare tutto?',
            upgradeAllBody: (n) => `Questi <strong>${n}</strong> personaggi salgono di livello finché le copie bastano:`,
            upgradeAllDone: (c, l) => `Potenziati ${c} ${c === 1 ? 'personaggio' : 'personaggi'} per ${l} ${l === 1 ? 'livello' : 'livelli'}.`,
            levelUp: 'LEVEL UP!',
            excess: (n, f) => `${num(n)} ${n === 1 ? 'copia' : 'copie'} in eccesso → ${num(f)} frammenti`,
            convert: 'Converti',
            confirmConvertTitle: 'Convertire in frammenti?',
            confirmConvert: (n, f) => `<strong>${num(n)}</strong> ${n === 1 ? 'copia' : 'copie'} in eccesso diventano <strong>${num(f)}</strong> frammenti. Le copie che servono ai potenziamenti restano, e le casse aperte non cambiano.`,
            converted: (f) => `+${num(f)} frammenti`,
            stats: { hp: 'HP', attack: 'ATK', defense: 'DEF', speed: 'SPD' },
            passive: 'Passiva', special: 'Speciale', ultimate: 'Ultimate', costE: 'Costo', none: 'Nessuna',
            collections: { reward: 'Premio', claim: 'Riscuoti', claimed: 'Riscosso', noReward: 'Nessun premio', complete: 'Completa', claimedToast: 'Premio inviato nella posta!', view: 'Vedi i personaggi' },
            fr: {
                what: 'Cosa sono i frammenti',
                steps: [
                    ['fa-layer-group', 'Doppioni', 'Ogni personaggio sale fino al livello MAX consumando copie. Quelle oltre ciò che serve per arrivarci sono <b>in eccesso</b>.'],
                    ['fa-arrows-rotate', 'Frammenti', 'Converti le copie in eccesso: diventano frammenti. Più il personaggio è raro, più frammenti vale. Le casse aperte non si perdono.'],
                    ['fa-store', 'Personaggi', 'Con i frammenti compri personaggi nel <b>negozio della settimana</b>, scelto per te partendo da quelli che ti mancano.'],
                ],
                balance: 'I tuoi frammenti',
                convertTitle: 'Converti le copie in eccesso',
                convertHelp: 'Scegli le rarità da convertire. Le copie che ti servono per arrivare al MAX non si toccano mai.',
                convertBtn: (f) => `Converti in ${num(f)} frammenti`,
                nothing: 'Nessuna copia in eccesso da convertire.',
                chipCopies: (n) => `${num(n)} ${n === 1 ? 'copia' : 'copie'}`,
                andMore: (n) => `e altri ${n}`,
                shop: 'Negozio della settimana', shopHelp: 'Scelto per te: prima i personaggi che ti mancano. Ognuno si compra una volta a settimana.',
                renew: 'Nuovo negozio tra', buy: 'Compra', bought: 'Comprato', missingBadge: 'Ti manca',
                owned: (n) => n ? `ne hai ${num(n)}` : 'non ce l\'hai',
                values: 'Quanto vale una copia in eccesso',
                unavailable: 'I frammenti arrivano con il prossimo aggiornamento del database.',
                confirmBuyTitle: 'Comprare?', confirmBuy: (n, p) => `<strong>${esc(n)}</strong> per <strong>${num(p)}</strong> frammenti?`,
                boughtToast: (n) => `${n} è nel tuo inventario!`, notEnough: (n) => `Ti mancano ${num(n)} frammenti`,
            },
            seenDone: 'Fatto: niente più badge NEW.',
            error: 'Qualcosa è andato storto. Riprova.',
            d: 'g', h: 'h', m: 'min',
        },
        en: {
            results: (n) => `${n} ${n === 1 ? 'character' : 'characters'}`,
            progress: (a, b) => `${a} of ${b} characters`,
            standard: (a, b) => `Standard ${a}/${b}`,
            boxes: 'Pulls', dupes: 'With duplicates', upgradable: 'Upgradeable', news: 'New', fragments: 'Fragments',
            status: { owned: 'Owned', missing: 'Missing', duplicates: 'Duplicates', upgradable: 'Upgradeable', limited: 'Limited', new: 'New', favorites: 'Favorites', wishlist: 'Wishlist' },
            unknown: '???', notOwned: 'Not found', lv: 'Lv.', max: 'MAX', limited: 'Limited', limitedSuffix: 'limited', newBadge: 'NEW',
            noCategory: 'No category',
            sort: { label: 'Sort by', rarity: 'Rarity (common first)', rarity_desc: 'Rarity (highest first)', recent: 'Most recent', name: 'Name A-Z', quantity: 'Most copies', level: 'Level' },
            group: { label: 'Group by', rarity: 'By rarity', category: 'By category', none: 'No groups' },
            tabs: { info: 'Overview', upgrade: 'Upgrade', kit: 'Abilities' },
            copies: 'Copies', found: 'Found on', last: 'Last copy', category: 'Category',
            noDesc: 'No description.', traits: 'Traits',
            whereTitle: 'Where to find it', whereMissing: 'Where to find it', notInPools: 'Not in any active banner: keep an eye on “Coming soon”.',
            bannerKinds: { standard: 'Always available', evento: 'Limited banner', selezione: 'Selection banner', principiante: 'Beginner banner' },
            rateup: 'Rate-up', inPool: 'In the pool', endsIn: (t) => `Ends in ${t}`, startsIn: (t) => `Starts in ${t}`, free: 'Free', cost: (c) => `${c} Godos per pull`,
            animation: 'View animation', play: 'Listen', stop: 'Stop',
            favorite: 'Favorite', addFavorite: 'Add to favorites',
            wishlistAdd: 'Add to wishlist', wishlistOn: 'In wishlist',
            wishlistHelp: 'We\'ll let you know in your inbox when it joins a banner.',
            missingTitle: 'Not yours yet', maskedTitle: 'Secret character', maskedText: 'You\'ll find out when you get it.',
            level: 'Level', duplicatesOf: (d, r) => `Duplicates ${d} / ${r}`, upgrade: 'Upgrade', maxReached: 'Maximum power reached',
            missingCopies: (n) => `You need ${n} more ${n === 1 ? 'copy' : 'copies'} for the next level.`,
            ready: 'Ready to upgrade!',
            confirmUpgrade: (c, n, l) => `Use <strong>${c}</strong> of <strong>${esc(n)}</strong> to reach level <strong>${l}</strong>?`,
            confirmUpgradeTitle: 'Upgrade?',
            upgradeAllTitle: 'Upgrade everything?',
            upgradeAllBody: (n) => `These <strong>${n}</strong> characters level up as long as copies last:`,
            upgradeAllDone: (c, l) => `Upgraded ${c} ${c === 1 ? 'character' : 'characters'} for ${l} ${l === 1 ? 'level' : 'levels'}.`,
            levelUp: 'LEVEL UP!',
            excess: (n, f) => `${num(n)} extra ${n === 1 ? 'copy' : 'copies'} → ${num(f)} fragments`,
            convert: 'Convert',
            confirmConvertTitle: 'Convert to fragments?',
            confirmConvert: (n, f) => `<strong>${num(n)}</strong> extra ${n === 1 ? 'copy becomes' : 'copies become'} <strong>${num(f)}</strong> fragments. Copies needed for upgrades are kept, and your pull count doesn't change.`,
            converted: (f) => `+${num(f)} fragments`,
            stats: { hp: 'HP', attack: 'ATK', defense: 'DEF', speed: 'SPD' },
            passive: 'Passive', special: 'Special', ultimate: 'Ultimate', costE: 'Cost', none: 'None',
            collections: { reward: 'Reward', claim: 'Claim', claimed: 'Claimed', noReward: 'No reward', complete: 'Complete', claimedToast: 'Reward sent to your inbox!', view: 'See characters' },
            fr: {
                what: 'What fragments are',
                steps: [
                    ['fa-layer-group', 'Duplicates', 'Each character levels up to MAX by using copies. Copies beyond what it needs are <b>extra</b>.'],
                    ['fa-arrows-rotate', 'Fragments', 'Convert extra copies into fragments. Rarer characters are worth more. Your pull count is kept.'],
                    ['fa-store', 'Characters', 'Spend fragments on characters in the <b>weekly shop</b>, picked for you starting from the ones you are missing.'],
                ],
                balance: 'Your fragments',
                convertTitle: 'Convert extra copies',
                convertHelp: 'Choose which rarities to convert. Copies you need to reach MAX are never touched.',
                convertBtn: (f) => `Convert into ${num(f)} fragments`,
                nothing: 'No extra copies to convert.',
                chipCopies: (n) => `${num(n)} ${n === 1 ? 'copy' : 'copies'}`,
                andMore: (n) => `and ${n} more`,
                shop: 'Weekly shop', shopHelp: 'Picked for you: missing characters first. Each one can be bought once a week.',
                renew: 'New shop in', buy: 'Buy', bought: 'Bought', missingBadge: 'Missing',
                owned: (n) => n ? `you have ${num(n)}` : 'not owned',
                values: 'What an extra copy is worth',
                unavailable: 'Fragments arrive with the next database update.',
                confirmBuyTitle: 'Buy?', confirmBuy: (n, p) => `<strong>${esc(n)}</strong> for <strong>${num(p)}</strong> fragments?`,
                boughtToast: (n) => `${n} is in your inventory!`, notEnough: (n) => `${num(n)} fragments missing`,
            },
            seenDone: 'Done: no more NEW badges.',
            error: 'Something went wrong. Try again.',
            d: 'd', h: 'h', m: 'min',
        },
    }[lang];

    const RARITIES = data.rarita || [];
    const RARITY = Object.fromEntries(RARITIES.map((r) => [r.key, r]));
    const RANK = Object.fromEntries(RARITIES.map((r, i) => [r.key, i]));
    const LIMITED_COLOR = '#f0abfc';
    let CATEGORIES = data.categorie || [];
    let CATEGORY = new Map(CATEGORIES.map((c) => [String(c.nome).toLowerCase(), c]));
    let BANNERS = new Map((data.banner || []).map((b) => [String(b.key), b]));
    const features = data.funzioni || {};

    /*
     * Fascia di visualizzazione: i limitati hanno una sezione tutta loro,
     * subito dopo la loro rarita' (Segreto → Segreto limitato → The One).
     */
    const shelf = (e) => (e.limitato ? `${e.rarita}_limited` : e.rarita);
    const shelfRank = (key) => {
        const [base, lim] = String(key).split('_');
        return (RANK[base] ?? 0) * 2 + (lim ? 1 : 0);
    };
    const shelfLabel = (key) => {
        const [base, lim] = String(key).split('_');
        const label = RARITY[base]?.label || base;
        return lim ? `${label} ${T.limitedSuffix}` : label;
    };
    const shelfColor = (key) => (String(key).endsWith('_limited') ? LIMITED_COLOR : (RARITY[key]?.colore || '#fff'));
    const rarityLabel = (k) => RARITY[k]?.label || k;
    const rarityColor = (k) => RARITY[k]?.colore || '#fff';

    /* ── Stato e URL ─────────────────────────────────────────────────── */

    const SORTS = ['rarity', 'rarity_desc', 'recent', 'name', 'quantity', 'level'];
    const GROUPS = ['rarity', 'category', 'none'];
    const DEFAULTS = { q: '', r: [], s: [], cat: '', o: 'rarity', g: 'rarity', d: 'comfortable' };
    const state = { ...DEFAULTS, tab: 'collezione', open: null, filtersOpen: false };

    const readUrl = () => {
        const p = new URLSearchParams(location.search);
        state.q = p.get('q') || '';
        state.r = (p.get('r') || '').split(',').filter(Boolean);
        state.s = (p.get('s') || '').split(',').filter((k) => T.status[k]);
        state.cat = p.get('cat') || '';
        state.o = SORTS.includes(p.get('o')) ? p.get('o') : DEFAULTS.o;
        state.g = GROUPS.includes(p.get('g')) ? p.get('g') : DEFAULTS.g;
        let density = null;
        try { density = localStorage.getItem('cripsum:inv:density'); } catch (e) { density = null; }
        state.d = density === 'compact' ? 'compact' : 'comfortable';
        const tab = p.get('tab');
        state.tab = ['collezione', 'collezioni', 'frammenti'].includes(tab) ? tab : 'collezione';
        state.open = p.get('c');
    };

    const writeUrl = (push = false) => {
        const p = new URLSearchParams();
        if (state.q) p.set('q', state.q);
        if (state.r.length) p.set('r', state.r.join(','));
        if (state.s.length) p.set('s', state.s.join(','));
        if (state.cat) p.set('cat', state.cat);
        if (state.o !== DEFAULTS.o) p.set('o', state.o);
        if (state.g !== DEFAULTS.g) p.set('g', state.g);
        if (state.tab !== 'collezione') p.set('tab', state.tab);
        if (state.open) p.set('c', state.open);
        const url = `${location.pathname}${p.toString() ? `?${p}` : ''}`;
        try {
            if (push) history.pushState(null, '', url); else history.replaceState(null, '', url);
        } catch (e) { /* sandbox */ }
    };

    /* ── Rete ────────────────────────────────────────────────────────── */

    const postJson = async (url, body) => {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ ...body, csrf_token: CSRF }),
        });
        let json = {};
        try { json = await res.json(); } catch (e) { json = {}; }
        if (!res.ok || json.ok === false || json.success === false) throw new Error(json.message || T.error);
        return json;
    };
    const action = (name, body = {}) => postJson('/api/gacha/azioni.php', { action: name, ...body });

    /** Rilegge tutto dopo un'azione che cambia copie o livelli. */
    const reload = async () => {
        const res = await fetch(`/api/gacha/collezione.php?lang=${lang}`, { credentials: 'same-origin', cache: 'no-store' });
        const json = await res.json();
        if (!json.ok) throw new Error(json.message || T.error);
        data = json;
        CATEGORIES = data.categorie || [];
        CATEGORY = new Map(CATEGORIES.map((c) => [String(c.nome).toLowerCase(), c]));
        BANNERS = new Map((data.banner || []).map((b) => [String(b.key), b]));
        indexEntries();
        buildCards();
        renderAll({ animate: false });
    };

    /* ── Piccoli effetti ─────────────────────────────────────────────── */

    const easeOut = (t) => 1 - Math.pow(1 - t, 3);

    /** Numero che sale fino al valore (o cambia da quello mostrato). */
    const countTo = (el, target, { duration = 700, format = num } = {}) => {
        if (!el) return;
        const from = Number(el.dataset.value || 0);
        el.dataset.value = String(target);
        if (reducedMotion() || from === target) {
            el.textContent = format(target);
            return;
        }
        const start = performance.now();
        const tick = (now) => {
            const t = Math.min(1, (now - start) / duration);
            el.textContent = format(Math.round(from + (target - from) * easeOut(t)));
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };

    /** Rifa' partire un'animazione CSS togliendo e rimettendo la classe. */
    const replay = (el, cls) => {
        if (!el || reducedMotion()) return;
        el.classList.remove(cls);
        void el.offsetWidth;
        el.classList.add(cls);
    };

    const countdown = (iso) => {
        const diff = new Date(iso).getTime() - Date.now();
        if (!(diff > 0)) return '';
        const d = Math.floor(diff / 86400000);
        const h = Math.floor((diff % 86400000) / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        if (d > 0) return `${d}${T.d} ${h}${T.h}`;
        if (h > 0) return `${h}${T.h} ${m}${T.m}`;
        return `${m}${T.m}`;
    };

    /* ── Toast e conferme ────────────────────────────────────────────── */

    let toastTimer = 0;
    const toast = (message, isError = false) => {
        const el = $('[data-toast]');
        if (!el) return;
        el.textContent = message;
        el.classList.toggle('is-error', isError);
        el.hidden = false;
        requestAnimationFrame(() => el.classList.add('is-visible'));
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            el.classList.remove('is-visible');
            setTimeout(() => { el.hidden = true; }, 300);
        }, isError ? 3600 : 2600);
    };

    const confirmBox = (title, html, okLabel = null) => new Promise((resolve) => {
        const box = $('[data-confirm]');
        $('[data-confirm-title]', box).textContent = title;
        $('[data-confirm-body]', box).innerHTML = html;
        const ok = $('[data-confirm-ok]', box);
        ok.dataset.label = ok.dataset.label || ok.textContent;
        ok.textContent = okLabel || ok.dataset.label;
        const previous = document.activeElement;
        box.hidden = false;
        requestAnimationFrame(() => box.classList.add('is-open'));
        ok.focus();
        const done = (value) => {
            box.classList.remove('is-open');
            setTimeout(() => { box.hidden = true; }, reducedMotion() ? 0 : 200);
            ok.removeEventListener('click', onOk);
            $$('[data-confirm-cancel]', box).forEach((b) => b.removeEventListener('click', onCancel));
            document.removeEventListener('keydown', onKey);
            previous?.focus?.();
            resolve(value);
        };
        const onOk = () => done(true);
        const onCancel = () => done(false);
        const onKey = (e) => { if (e.key === 'Escape') done(false); };
        ok.addEventListener('click', onOk);
        $$('[data-confirm-cancel]', box).forEach((b) => b.addEventListener('click', onCancel));
        document.addEventListener('keydown', onKey);
    });

    /* ── Menu a tendina ──────────────────────────────────────────────── */

    /**
     * Tendina fatta a mano, al posto della <select> del browser: bottone +
     * lista animata, tastiera (frecce, Invio, Esc, Home/End) e chiusura con
     * un clic fuori.
     */
    const dropdowns = [];
    const makeDropdown = (host, { label, options, value, onChange }) => {
        const id = `dd-${Math.random().toString(36).slice(2, 8)}`;
        host.innerHTML = `
            <span class="inv-dd__label" id="${id}-l">${esc(label)}</span>
            <button type="button" class="inv-dd__btn" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="${id}-l ${id}-v">
                <i class="inv-dd__icon" aria-hidden="true"></i>
                <span class="inv-dd__value" id="${id}-v"></span>
                <i class="fa-solid fa-chevron-down inv-dd__chev" aria-hidden="true"></i>
            </button>
            <ul class="inv-dd__menu" role="listbox" tabindex="-1" aria-labelledby="${id}-l">
                ${options.map(([v, text, icon], i) => `<li role="option" class="inv-dd__opt" data-value="${esc(v)}" style="--i:${i}" tabindex="-1"><i class="fa-solid ${esc(icon)}" aria-hidden="true"></i><span>${esc(text)}</span><i class="fa-solid fa-check inv-dd__check" aria-hidden="true"></i></li>`).join('')}
            </ul>`;
        const btn = $('.inv-dd__btn', host);
        const menu = $('.inv-dd__menu', host);
        const opts = $$('.inv-dd__opt', host);
        let current = value;

        const set = (v, emit = false) => {
            current = v;
            const opt = options.find((o) => o[0] === v) || options[0];
            $('.inv-dd__value', host).textContent = opt[1];
            $('.inv-dd__icon', host).className = `inv-dd__icon fa-solid ${opt[2]}`;
            opts.forEach((o) => o.setAttribute('aria-selected', String(o.dataset.value === v)));
            if (emit) onChange(v);
        };
        const open = () => {
            dropdowns.forEach((d) => d !== api && d.close());
            host.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
            (opts.find((o) => o.dataset.value === current) || opts[0]).focus({ preventScroll: true });
        };
        const close = (focusBtn = false) => {
            if (!host.classList.contains('is-open')) return;
            host.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            if (focusBtn) btn.focus({ preventScroll: true });
        };
        const api = { host, set, open, close };

        btn.addEventListener('click', () => (host.classList.contains('is-open') ? close() : open()));
        btn.addEventListener('keydown', (e) => {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) { e.preventDefault(); open(); }
        });
        menu.addEventListener('click', (e) => {
            const opt = e.target.closest('.inv-dd__opt');
            if (!opt) return;
            set(opt.dataset.value, true);
            close(true);
        });
        menu.addEventListener('keydown', (e) => {
            const idx = opts.indexOf(document.activeElement);
            if (e.key === 'ArrowDown') { e.preventDefault(); opts[Math.min(opts.length - 1, idx + 1)].focus(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); opts[Math.max(0, idx - 1)].focus(); }
            else if (e.key === 'Home') { e.preventDefault(); opts[0].focus(); }
            else if (e.key === 'End') { e.preventDefault(); opts[opts.length - 1].focus(); }
            else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (idx >= 0) { set(opts[idx].dataset.value, true); close(true); } }
            else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(true); }
            else if (e.key === 'Tab') close();
        });
        set(value);
        dropdowns.push(api);
        return api;
    };
    document.addEventListener('click', (e) => {
        dropdowns.forEach((d) => { if (!d.host.contains(e.target)) d.close(); });
    });

    /* ── Dati ────────────────────────────────────────────────────────── */

    let entries = [];
    let byKey = new Map();

    const indexEntries = () => {
        entries = (data.personaggi || []).map((e, index) => ({ ...e, index, shelf: shelf(e) }));
        byKey = new Map(entries.map((e) => [e.key, e]));
    };

    const categoryOf = (e) => (e.categoria ? CATEGORY.get(String(e.categoria).toLowerCase()) : null);

    const matchesStatus = (e, s) => {
        switch (s) {
            case 'owned': return e.posseduto;
            case 'missing': return !e.posseduto;
            case 'duplicates': return e.posseduto && e.quantita > 1;
            case 'upgradable': return e.posseduto && e.potenziabile;
            case 'limited': return e.limitato;
            case 'new': return e.posseduto && e.nuovo;
            case 'favorites': return e.posseduto && e.preferito;
            case 'wishlist': return !e.posseduto && e.wishlist;
            default: return true;
        }
    };

    const filtered = () => {
        const q = state.q.trim().toLowerCase();
        const list = entries.filter((e) => {
            if (state.r.length && !state.r.includes(e.shelf)) return false;
            if (state.cat && String(e.categoria || '').toLowerCase() !== state.cat.toLowerCase()) return false;
            if (state.s.length) {
                const own = state.s.filter((s) => s === 'owned' || s === 'missing');
                const rest = state.s.filter((s) => s !== 'owned' && s !== 'missing');
                if (own.length && !own.some((s) => matchesStatus(e, s))) return false;
                if (!rest.every((s) => matchesStatus(e, s))) return false;
            }
            if (q) {
                const hay = `${e.mascherato ? '' : e.nome || ''} ${shelfLabel(e.shelf)} ${e.categoria || ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });

        const up = (a, b) => shelfRank(a.shelf) - shelfRank(b.shelf);
        const byOwned = (a, b) => Number(b.posseduto) - Number(a.posseduto) || a.index - b.index;
        const sorters = {
            rarity: (a, b) => up(a, b) || byOwned(a, b),
            rarity_desc: (a, b) => up(b, a) || byOwned(a, b),
            recent: (a, b) => String(b.ultima || '').localeCompare(String(a.ultima || '')) || up(a, b),
            name: (a, b) => String(a.nome || '~').localeCompare(String(b.nome || '~'), lang),
            quantity: (a, b) => (b.quantita || 0) - (a.quantita || 0) || up(a, b),
            level: (a, b) => (b.livello || 0) - (a.livello || 0) || up(a, b),
        };
        return list.sort(sorters[state.o] || sorters.rarity);
    };

    /* ── Carte ───────────────────────────────────────────────────────── */

    const cards = new Map();

    const levelPips = (lvl) => {
        const max = lvl >= 6;
        return `<span class="inv-card__lv" aria-label="${T.lv} ${max ? T.max : lvl}">${max ? `<b>${T.max}</b>` : Array.from({ length: 6 }, (_, i) => `<i class="${i < lvl ? 'on' : ''}"></i>`).join('')}</span>`;
    };

    const cardHtml = (e) => {
        const cat = categoryOf(e);
        const art = e.mascherato
            ? '<span class="inv-card__q" aria-hidden="true">?</span>'
            : `<img src="${esc(e.img || '/img/boh.png')}" alt="" loading="lazy" decoding="async" onload="this.classList.add('is-loaded')" onerror="this.onerror=null;this.src='/img/boh.png';this.classList.add('is-loaded')">`;
        const name = e.mascherato ? T.unknown : e.nome;
        return `
            <span class="inv-card__art">${art}
                <span class="inv-card__shine" aria-hidden="true"></span>
                ${e.posseduto && e.nuovo ? `<span class="inv-card__new">${T.newBadge}</span>` : ''}
                ${e.limitato && !e.mascherato ? `<span class="inv-card__ribbon"><i class="fa-solid fa-hourglass-half"></i> ${T.limited}</span>` : ''}
                ${e.posseduto ? `<span class="inv-card__qty${e.quantita > 1 ? ' is-dupe' : ''}">×${num(e.quantita)}</span>` : ''}
                ${e.posseduto && e.preferito ? '<span class="inv-card__fav" aria-hidden="true"><i class="fa-solid fa-star"></i></span>' : ''}
                ${!e.posseduto && e.wishlist ? '<span class="inv-card__fav is-wish" aria-hidden="true"><i class="fa-solid fa-heart"></i></span>' : ''}
                ${e.posseduto && e.potenziabile ? '<span class="inv-card__up" aria-hidden="true"><i class="fa-solid fa-angles-up"></i></span>' : ''}
            </span>
            <span class="inv-card__body">
                <span class="inv-card__name">${esc(name)}</span>
                <span class="inv-card__meta">
                    <span class="inv-card__rarity" style="color:${esc(rarityColor(e.rarita))}">${esc(rarityLabel(e.rarita))}</span>
                    ${cat && !e.mascherato ? `<span class="inv-card__cat" style="--cc:${esc(cat.colore || '#94a3b8')}">${esc(cat.label || cat.nome)}</span>` : ''}
                </span>
                ${e.posseduto ? levelPips(e.livello) : ''}
            </span>`;
    };

    const buildCards = () => {
        cards.clear();
        entries.forEach((e) => {
            const el = document.createElement(e.mascherato ? 'div' : 'button');
            if (e.mascherato) {
                el.setAttribute('aria-label', `${T.unknown} — ${shelfLabel(e.shelf)}`);
            } else {
                el.type = 'button';
                el.setAttribute('aria-label', e.nome + (e.posseduto ? '' : ` — ${T.notOwned}`));
            }
            el.className = `inv-card r-${e.rarita}${e.limitato ? ' is-limited' : ''}${e.posseduto ? ' is-owned' : ' is-missing'}${e.mascherato ? ' is-masked' : ''}${e.livello >= 6 && e.posseduto ? ' is-max' : ''}`;
            el.style.setProperty('--rc', e.limitato ? LIMITED_COLOR : rarityColor(e.rarita));
            el.dataset.key = e.key;
            el.innerHTML = cardHtml(e);
            cards.set(e.key, el);
        });
    };

    const refreshCard = (e) => {
        const card = cards.get(e.key);
        if (card) card.innerHTML = cardHtml(e);
    };

    /* ── Rendering ───────────────────────────────────────────────────── */

    const renderHead = () => {
        const tot = data.totali || {};
        const visible = Math.max(1, tot.visibili || 0);
        const pct = Math.round((tot.posseduti || 0) / visible * 100);
        const ring = $('[data-ring]');
        if (ring) requestAnimationFrame(() => { ring.style.strokeDasharray = `${pct} 100`; });
        countTo($('[data-progress-pct]'), pct, { format: (v) => `${v}%`, duration: 900 });
        $('[data-progress-text]').textContent = `${T.progress(num(tot.posseduti), num(tot.visibili))} · ${T.standard(num(tot.standard_posseduti), num(tot.standard))}`;

        const statsEl = $('[data-stats]');
        const stats = [
            ['boxes', T.boxes, tot.casse, '<i class="fa-solid fa-box-open" aria-hidden="true"></i>'],
            ['dupes', T.dupes, tot.duplicati, '<i class="fa-solid fa-clone" aria-hidden="true"></i>'],
            ['up', T.upgradable, tot.potenziabili, '<i class="fa-solid fa-angles-up" aria-hidden="true"></i>'],
            ['new', T.news, tot.nuovi, '<i class="fa-solid fa-star" aria-hidden="true"></i>'],
        ];
        if (features.frammenti && data.frammenti) stats.push(['frag', T.fragments, data.frammenti.saldo, fragIcon()]);
        if (statsEl.dataset.built !== stats.map((s) => s[0]).join()) {
            statsEl.innerHTML = stats.map(([k, label, , icon], i) => `<div style="--i:${i}"><dt>${icon} ${esc(label)}</dt><dd data-stat="${k}">0</dd></div>`).join('');
            statsEl.dataset.built = stats.map((s) => s[0]).join();
        }
        stats.forEach(([k, , value]) => countTo($(`[data-stat="${k}"]`, statsEl), Number(value || 0)));

        const up = $('[data-upgrade-all]');
        if (up) {
            up.disabled = !(tot.potenziabili > 0);
            const label = $('span', up);
            if (label) label.textContent = `${lang === 'en' ? 'Upgrade all' : 'Potenzia tutto'}${tot.potenziabili > 0 ? ` (${tot.potenziabili})` : ''}`;
        }
        const seen = $('[data-mark-seen]');
        if (seen) seen.hidden = !(features.nuovi && tot.nuovi > 0);

        const claimable = CATEGORIES.some((c) => c.id && !c.riscosso && (c.premio_godos > 0 || c.premio_badge) && c.posseduti >= c.totale);
        const dot = $('[data-claim-dot]');
        if (dot) dot.hidden = !claimable;
    };

    const chip = (value, label, active, count, color, attr) =>
        `<button type="button" class="inv-chip${active ? ' is-active' : ''}" ${attr}="${esc(value)}" aria-pressed="${active}"${color ? ` style="--cc:${esc(color)}"` : ''}>${color ? '<i class="inv-chip__dot" aria-hidden="true"></i>' : ''}${esc(label)}${count !== null ? `<small>${count}</small>` : ''}</button>`;

    let sortDd = null;
    let groupDd = null;

    const renderToolbar = () => {
        const shelves = {};
        entries.forEach((e) => {
            shelves[e.shelf] = shelves[e.shelf] || { tot: 0, own: 0 };
            shelves[e.shelf].tot++;
            if (e.posseduto) shelves[e.shelf].own++;
        });
        $('[data-rarity-chips]').innerHTML = Object.keys(shelves).sort((a, b) => shelfRank(a) - shelfRank(b)).map((k) =>
            chip(k, shelfLabel(k), state.r.includes(k), `${shelves[k].own}/${shelves[k].tot}`, shelfColor(k), 'data-rarity')
        ).join('');

        const statusKeys = ['owned', 'missing', 'duplicates', 'upgradable', 'limited'];
        if (features.nuovi) statusKeys.push('new');
        if (features.preferiti) statusKeys.push('favorites');
        if (features.wishlist) statusKeys.push('wishlist');
        $('[data-status-chips]').innerHTML = statusKeys.map((k) => chip(k, T.status[k], state.s.includes(k), entries.filter((e) => matchesStatus(e, k)).length, null, 'data-status')).join('');

        const catGroup = $('[data-category-group]');
        if (catGroup) catGroup.hidden = CATEGORIES.length === 0;
        $('[data-category-chips]').innerHTML = CATEGORIES.map((c) => chip(c.nome, c.label || c.nome, state.cat.toLowerCase() === String(c.nome).toLowerCase(), c.totale, c.colore || '#94a3b8', 'data-category')).join('');

        const search = $('[data-search]');
        if (document.activeElement !== search) search.value = state.q;
        sortDd?.set(state.o);
        groupDd?.set(state.g);
        $$('[data-density]').forEach((b) => b.classList.toggle('is-active', b.dataset.density === state.d));
        root.classList.toggle('is-compact', state.d === 'compact');

        const active = state.s.length + (state.cat ? 1 : 0) + (state.o !== DEFAULTS.o ? 1 : 0) + (state.g !== DEFAULTS.g ? 1 : 0);
        const badge = $('[data-filters-count]');
        badge.hidden = active === 0;
        if (badge.textContent !== String(active)) {
            badge.textContent = active;
            replay(badge, 'is-bumped');
        }
    };

    const groupKey = (e) => {
        if (state.g === 'rarity') return e.shelf;
        if (state.g === 'category') return e.categoria ? String(e.categoria).toLowerCase() : '';
        return 'all';
    };

    const groupTitle = (key, list) => {
        const own = list.filter((e) => e.posseduto).length;
        if (state.g === 'rarity') {
            return { label: shelfLabel(key), color: shelfColor(key), own, tot: list.length, icon: String(key).endsWith('_limited') ? 'fa-solid fa-hourglass-half' : null, limited: String(key).endsWith('_limited') };
        }
        if (state.g === 'category') {
            const cat = CATEGORY.get(key);
            return { label: cat ? (cat.label || cat.nome) : (key || T.noCategory), color: cat?.colore || '#94a3b8', icon: cat?.icona, own, tot: list.length };
        }
        return null;
    };

    const collapsed = new Set();

    /**
     * Ridisegna la griglia. Con `animate` le carte entrano a cascata (le
     * prime trenta, le altre subito) ogni volta che cambia un filtro.
     */
    const renderGrid = ({ animate = true } = {}) => {
        const list = filtered();
        const groupsEl = $('[data-groups]');
        const groups = new Map();
        list.forEach((e) => {
            const k = groupKey(e);
            if (!groups.has(k)) groups.set(k, []);
            groups.get(k).push(e);
        });

        const frag = document.createDocumentFragment();
        let order = 0;
        groups.forEach((items, key) => {
            const section = document.createElement('section');
            section.className = 'inv-group';
            const title = groupTitle(key, items);
            const id = `g-${state.g}-${key || 'none'}`;
            if (title) {
                if (title.limited) section.classList.add('is-limited');
                const pct = title.tot ? Math.round(title.own / title.tot * 100) : 0;
                const isCollapsed = collapsed.has(id);
                section.innerHTML = `
                    <button type="button" class="inv-group__head" data-collapse="${esc(id)}" aria-expanded="${!isCollapsed}" style="--gc:${esc(title.color)}">
                        ${title.icon ? `<i class="${esc(title.icon)} inv-group__icon" aria-hidden="true"></i>` : '<i class="inv-group__dot" aria-hidden="true"></i>'}
                        <strong>${esc(title.label)}</strong>
                        <span class="inv-group__count">${title.own}/${title.tot}</span>
                        <span class="inv-group__bar" aria-hidden="true"><i style="--w:${pct}%"></i></span>
                        <i class="fa-solid fa-chevron-down inv-group__chev" aria-hidden="true"></i>
                    </button>`;
                if (isCollapsed) section.classList.add('is-collapsed');
            }
            const body = document.createElement('div');
            body.className = 'inv-group__body';
            const inner = document.createElement('div');
            inner.className = 'inv-group__inner';
            const grid = document.createElement('div');
            grid.className = 'inv-grid';
            items.forEach((e) => {
                const card = cards.get(e.key);
                card.style.setProperty('--i', String(Math.min(order, 30)));
                order++;
                grid.appendChild(card);
            });
            inner.appendChild(grid);
            body.appendChild(inner);
            section.appendChild(body);
            if (animate && !reducedMotion()) section.classList.add('is-entering');
            frag.appendChild(section);
        });

        groupsEl.replaceChildren(frag);

        if (animate && !reducedMotion()) {
            list.forEach((e) => {
                const card = cards.get(e.key);
                card.classList.remove('is-entering');
            });
            void groupsEl.offsetWidth;
            list.forEach((e) => cards.get(e.key).classList.add('is-entering'));
        }

        const empty = $('[data-empty]');
        if (empty.hidden !== (list.length > 0)) {
            empty.hidden = list.length > 0;
            if (!empty.hidden) replay(empty, 'is-entering');
        }
        $('[data-results]').textContent = T.results(list.length);
    };

    const renderCollections = () => {
        const box = $('[data-collections]');
        if (!box) return;
        box.innerHTML = CATEGORIES.map((c, i) => {
            const pct = c.totale ? Math.round(c.posseduti / c.totale * 100) : 0;
            const complete = c.posseduti >= c.totale;
            const hasReward = c.premio_godos > 0 || c.premio_badge;
            const reward = hasReward
                ? `${c.premio_godos > 0 ? `${num(c.premio_godos)} Godos` : ''}${c.premio_godos > 0 && c.premio_badge ? ' + ' : ''}${c.premio_badge ? 'badge' : ''}`
                : T.collections.noReward;
            let actionHtml = '';
            if (hasReward && c.id && features.collezioni) {
                actionHtml = c.riscosso
                    ? `<span class="inv-coll__done"><i class="fa-solid fa-check"></i> ${T.collections.claimed}</span>`
                    : `<button type="button" class="inv-btn inv-btn--primary inv-btn--small" data-claim="${Number(c.id)}" ${complete ? '' : 'disabled'}>${T.collections.claim}</button>`;
            }
            return `
                <article class="inv-coll${complete ? ' is-complete' : ''}" data-coll="${Number(c.id) || 0}" style="--cc:${esc(c.colore || '#94a3b8')};--i:${i}">
                    <span class="inv-coll__icon"><i class="${esc(c.icona || 'fa-solid fa-tag')}" aria-hidden="true"></i></span>
                    <div class="inv-coll__main">
                        <strong>${esc(c.label || c.nome)}</strong>
                        <span class="inv-coll__bar" aria-hidden="true"><i style="--w:${pct}%"></i></span>
                        <small>${c.posseduti}/${c.totale}${complete ? ` · ${T.collections.complete}` : ''} · ${T.collections.reward}: ${esc(reward)}</small>
                    </div>
                    <div class="inv-coll__actions">
                        ${actionHtml}
                        <button type="button" class="inv-link" data-view-category="${esc(c.nome)}">${T.collections.view} <i class="fa-solid fa-arrow-right"></i></button>
                    </div>
                </article>`;
        }).join('');
    };

    /* ── Frammenti ───────────────────────────────────────────────────── */

    const convertChoice = new Set();
    let convertChoiceReady = false;

    const excessByRarity = () => {
        const out = {};
        entries.forEach((e) => {
            if (!e.posseduto || !(e.eccesso > 0)) return;
            const r = (out[e.rarita] = out[e.rarita] || { copies: 0, value: 0, list: [] });
            r.copies += e.eccesso;
            r.value += e.eccesso * (e.valore_frammenti || 0);
            r.list.push(e);
        });
        return out;
    };

    const renderFragments = () => {
        const box = $('[data-fragments]');
        if (!box) return;
        const f = data.frammenti;
        const F = T.fr;
        if (!f || !features.frammenti) {
            box.innerHTML = `<p class="inv-panel__intro"><i class="fa-solid fa-circle-info"></i> ${F.unavailable}</p>`;
            return;
        }

        const excess = excessByRarity();
        const rarities = RARITIES.map((r) => r.key).filter((k) => excess[k]);
        if (!convertChoiceReady) {
            // Di partenza si propongono le rarita' fino allo Speciale: i
            // segreti in eccesso li converte solo chi li sceglie.
            rarities.forEach((k) => { if (RANK[k] <= RANK.speciale) convertChoice.add(k); });
            convertChoiceReady = true;
        }
        [...convertChoice].forEach((k) => { if (!excess[k]) convertChoice.delete(k); });
        const chosen = rarities.filter((k) => convertChoice.has(k));
        const total = chosen.reduce((a, k) => ({ copies: a.copies + excess[k].copies, value: a.value + excess[k].value }), { copies: 0, value: 0 });
        const preview = chosen.flatMap((k) => excess[k].list).sort((a, b) => b.eccesso * b.valore_frammenti - a.eccesso * a.valore_frammenti);

        box.innerHTML = `
            <section class="inv-frag-intro" aria-labelledby="fragWhat">
                <img src="${FRAG_IMG}" alt="" class="inv-frag-intro__img">
                <div>
                    <h2 id="fragWhat">${F.what}</h2>
                    <ol class="inv-frag-steps">
                        ${F.steps.map(([icon, title, text], i) => `<li style="--i:${i}"><span class="inv-frag-steps__n"><i class="fa-solid ${icon}"></i></span><div><strong>${title}</strong><p>${text}</p></div></li>`).join('')}
                    </ol>
                </div>
            </section>

            <div class="inv-frag-top">
                <div class="inv-frag-balance">
                    <span>${F.balance}</span>
                    <strong>${fragIcon('is-big')}<b data-frag-balance data-value="${Number(f.saldo) || 0}">${num(f.saldo)}</b></strong>
                </div>
                <div class="inv-frag-convert">
                    <h3>${F.convertTitle}</h3>
                    <p class="inv-muted">${F.convertHelp}</p>
                    ${rarities.length ? `
                        <div class="inv-frag-pick">
                            ${rarities.map((k) => `
                                <button type="button" class="inv-frag-pick__opt${convertChoice.has(k) ? ' is-active' : ''}" data-frag-rarity="${k}" aria-pressed="${convertChoice.has(k)}" style="--rc:${esc(rarityColor(k))}">
                                    <span class="inv-frag-pick__name"><i class="inv-chip__dot"></i>${esc(rarityLabel(k))}</span>
                                    <span class="inv-frag-pick__val">${F.chipCopies(excess[k].copies)} → ${fragIcon()} ${num(excess[k].value)}</span>
                                </button>`).join('')}
                        </div>
                        ${preview.length ? `<p class="inv-frag-preview">${preview.slice(0, 6).map((e) => `<span>${esc(e.nome)} <b>×${num(e.eccesso)}</b></span>`).join('')}${preview.length > 6 ? `<span>${F.andMore(preview.length - 6)}</span>` : ''}</p>` : ''}
                        <button type="button" class="inv-btn inv-btn--primary" data-convert-chosen ${total.copies > 0 ? '' : 'disabled'}>
                            <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> ${F.convertBtn(total.value)}
                        </button>` : `<p class="inv-frag-empty">${F.nothing}</p>`}
                </div>
            </div>

            <div class="inv-frag-head">
                <div>
                    <h2>${F.shop}</h2>
                    <p class="inv-muted">${F.shopHelp}</p>
                </div>
                <span class="inv-frag-timer"><i class="fa-regular fa-clock"></i> ${F.renew} <b data-frag-renew>${esc(countdown(f.rinnovo))}</b></span>
            </div>
            <div class="inv-frag-shop">
                ${(f.negozio || []).map((it, i) => {
                    const missing = Math.max(0, it.prezzo - f.saldo);
                    return `
                    <article class="inv-frag-item r-${esc(it.rarita)}${it.comprato ? ' is-bought' : ''}" style="--rc:${esc(rarityColor(it.rarita))};--i:${i}">
                        <span class="inv-frag-item__art">
                            <img src="${esc(it.img || '/img/boh.png')}" alt="" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='/img/boh.png'">
                            ${it.nuovo && !it.comprato ? `<span class="inv-frag-item__badge">${F.missingBadge}</span>` : ''}
                        </span>
                        <div class="inv-frag-item__body">
                            <small style="color:${esc(rarityColor(it.rarita))}">${esc(rarityLabel(it.rarita))}</small>
                            <strong>${esc(it.nome)}</strong>
                            <span class="inv-muted">${esc(F.owned(it.posseduti))}</span>
                        </div>
                        <button type="button" class="inv-btn ${it.comprato ? 'inv-btn--ghost' : 'inv-btn--primary'} inv-btn--small inv-frag-item__buy" data-buy="${Number(it.id)}" ${it.comprato || missing > 0 ? 'disabled' : ''} title="${!it.comprato && missing > 0 ? esc(F.notEnough(missing)) : ''}">
                            ${it.comprato ? `<i class="fa-solid fa-check"></i> ${F.bought}` : `${fragIcon()} ${num(it.prezzo)}`}
                        </button>
                    </article>`;
                }).join('')}
            </div>

            <details class="inv-frag-values">
                <summary><i class="fa-solid fa-chevron-right"></i> ${F.values}</summary>
                <div>${RARITIES.map((r) => `<span style="--rc:${esc(r.colore)}"><i></i>${esc(r.label)} <b>${fragIcon()} ${num(f.valori?.[r.key] || 0)}</b></span>`).join('')}</div>
            </details>`;
    };

    /* ── Schede ──────────────────────────────────────────────────────── */

    const moveInk = () => {
        const ink = $('[data-tab-ink]');
        const active = $('.inv-tab.is-active');
        if (!ink || !active) return;
        ink.style.width = `${active.offsetWidth}px`;
        ink.style.transform = `translateX(${active.offsetLeft}px)`;
    };

    const renderPanels = (animate = false) => {
        $$('.inv-tab').forEach((b) => {
            const on = b.dataset.tab === state.tab;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        $$('[data-panel]').forEach((p) => {
            const on = p.dataset.panel === state.tab;
            p.hidden = !on;
            if (on && animate) replay(p, 'is-entering');
        });
        moveInk();
    };

    const renderAll = ({ animate = true } = {}) => {
        renderHead();
        renderToolbar();
        renderGrid({ animate });
        renderCollections();
        renderFragments();
        renderPanels(false);
    };

    const setFilters = (open) => {
        state.filtersOpen = open;
        const panel = $('[data-filters]');
        panel.classList.toggle('is-open', open);
        panel.classList.remove('is-settled');
        $('[data-filters-toggle]').setAttribute('aria-expanded', String(open));
        if (!open) dropdowns.forEach((d) => d.close());
        if (open) {
            const settle = () => panel.classList.add('is-settled');
            if (reducedMotion()) settle(); else setTimeout(settle, 320);
        }
    };

    /* ── Dettaglio ───────────────────────────────────────────────────── */

    let drawerKey = null;
    let drawerTab = 'info';
    let lastFocus = null;
    const audio = new Audio();
    audio.preload = 'none';

    const stopAudio = () => {
        audio.pause();
        try { audio.currentTime = 0; } catch (e) { /* niente */ }
        $$('[data-play]').forEach((b) => {
            b.classList.remove('is-playing');
            const icon = $('i', b);
            if (icon) icon.className = 'fa-solid fa-play';
        });
    };

    const statRows = (now, next) => ['hp', 'attack', 'defense', 'speed'].map((k, i) => `
        <div class="inv-stat" style="--i:${i}">
            <span>${T.stats[k]}</span>
            <b>${num(now?.[k])}</b>
            ${next && next[k] !== undefined ? `<i class="fa-solid fa-arrow-right" aria-hidden="true"></i><b class="is-next">${num(next[k])}</b>` : ''}
        </div>`).join('');

    /** Le carte dei banner in cui si trova un personaggio, con la loro immagine. */
    const bannerCards = (e) => {
        const list = [];
        (e.banner || []).forEach((key) => {
            const b = BANNERS.get(String(key));
            if (b) list.push({ b, featured: true });
        });
        if (e.standard) {
            const std = BANNERS.get('standard');
            if (std) list.push({ b: std, featured: false });
        }
        if (!list.length) return `<p class="inv-muted">${T.notInPools}</p>`;

        return `<div class="inv-banners">${list.map(({ b, featured }, i) => {
            const soon = b.stato === 'prossimamente';
            const when = soon ? T.startsIn(countdown(b.data_inizio)) : (b.data_fine ? T.endsIn(countdown(b.data_fine)) : '');
            const kind = T.bannerKinds[b.tipo] || T.bannerKinds.evento;
            return `
                <a class="inv-bcard${soon ? ' is-soon' : ''}" href="lootbox${b.key === 'standard' ? '' : `?banner=${encodeURIComponent(b.key)}`}" style="--i:${i}">
                    <span class="inv-bcard__bg" style="background-image:url('${esc(b.img || '/img/banner_standard_bg.jpg')}')"></span>
                    ${b.arte ? `<img class="inv-bcard__art" src="${esc(b.arte)}" alt="" loading="lazy" onerror="this.remove()">` : ''}
                    <span class="inv-bcard__body">
                        <small>${esc(kind)}${featured ? ` · <b>${T.rateup}</b>` : ` · ${T.inPool}`}</small>
                        <strong>${esc(b.nome)}</strong>
                        <em>${when ? `<i class="fa-regular fa-clock"></i> ${esc(when)}` : ''}${b.costo > 0 ? `${when ? ' · ' : ''}${esc(T.cost(num(b.costo)))}` : `${when ? ' · ' : ''}${T.free}`}</em>
                    </span>
                    <i class="fa-solid fa-chevron-right inv-bcard__go" aria-hidden="true"></i>
                </a>`;
        }).join('')}</div>`;
    };

    const formatDate = (value, withTime = false) => {
        if (!value) return '';
        const d = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return '';
        return withTime ? `${d.toLocaleDateString(locale)} ${d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })}` : d.toLocaleDateString(locale);
    };

    const drawerHtml = (e) => {
        const cat = categoryOf(e);
        const color = e.limitato ? LIMITED_COLOR : rarityColor(e.rarita);
        const head = `
            <div class="inv-hero r-${e.rarita}${e.posseduto ? '' : ' is-missing'}${e.limitato ? ' is-limited' : ''}" style="--rc:${esc(color)}">
                <div class="inv-hero__art" ${e.mascherato ? '' : `style="--img:url('${esc(e.img || '/img/boh.png')}')"`}>
                    ${e.mascherato
                        ? '<span class="inv-hero__q">?</span>'
                        : `<img src="${esc(e.img || '/img/boh.png')}" alt="" onload="this.classList.add('is-loaded')" onerror="this.onerror=null;this.src='/img/boh.png'">`}
                </div>
                <div class="inv-hero__info">
                    <span class="inv-hero__rarity">${esc(shelfLabel(e.shelf))}</span>
                    <h2 id="invDrawerTitle">${esc(e.mascherato ? T.unknown : e.nome)}</h2>
                    <div class="inv-hero__chips">
                        ${cat && !e.mascherato ? `<span class="inv-pill" style="--cc:${esc(cat.colore || '#94a3b8')}"><i class="${esc(cat.icona || 'fa-solid fa-tag')}"></i> ${esc(cat.label || cat.nome)}</span>` : ''}
                        ${e.limitato ? `<span class="inv-pill inv-pill--limited"><i class="fa-solid fa-hourglass-half"></i> ${T.limited}</span>` : ''}
                        ${e.posseduto ? `<span class="inv-pill"><i class="fa-solid fa-layer-group"></i> ${T.copies} ×${num(e.quantita)}</span>` : ''}
                        ${e.posseduto ? `<span class="inv-pill">${T.lv} ${e.livello >= 6 ? T.max : e.livello}</span>` : ''}
                    </div>
                </div>
            </div>`;

        if (e.mascherato) {
            return `${head}<div class="inv-drawer__section"><h3>${T.maskedTitle}</h3><p class="inv-muted">${T.maskedText}</p></div>`;
        }

        if (!e.posseduto) {
            return `${head}
                <div class="inv-drawer__section">
                    <h3>${T.missingTitle}</h3>
                    ${features.wishlist ? `
                        <button type="button" class="inv-btn ${e.wishlist ? 'inv-btn--active is-wish' : ''}" data-wishlist="${Number(e.id)}" aria-pressed="${e.wishlist ? 'true' : 'false'}">
                            <i class="fa-${e.wishlist ? 'solid' : 'regular'} fa-heart"></i> ${e.wishlist ? T.wishlistOn : T.wishlistAdd}
                        </button>
                        <small class="inv-muted">${T.wishlistHelp}</small>` : ''}
                </div>
                <div class="inv-drawer__section">
                    <h3>${T.whereMissing}</h3>
                    ${bannerCards(e)}
                </div>`;
        }

        const traits = String(e.caratteristiche || '').split(';').map((x) => x.trim()).filter(Boolean);
        const s = e.stats || {};
        const tabs = `
            <div class="inv-dtabs" role="tablist">
                ${['info', 'upgrade', 'kit'].map((k) => `<button type="button" role="tab" class="inv-dtab${drawerTab === k ? ' is-active' : ''}" data-dtab="${k}" aria-selected="${drawerTab === k}">${T.tabs[k]}${k === 'upgrade' && e.potenziabile ? ' <i class="inv-dot" aria-hidden="true"></i>' : ''}</button>`).join('')}
                <span class="inv-dtabs__ink" data-dtab-ink aria-hidden="true"></span>
            </div>`;

        const actions = `
            <div class="inv-actions">
                ${features.preferiti ? `<button type="button" class="inv-btn ${e.preferito ? 'inv-btn--active' : ''}" data-favorite="${Number(e.id)}" aria-pressed="${e.preferito ? 'true' : 'false'}"><i class="fa-${e.preferito ? 'solid' : 'regular'} fa-star"></i> ${e.preferito ? T.favorite : T.addFavorite}</button>` : ''}
                ${e.audio ? `<button type="button" class="inv-btn" data-play="${esc(e.audio)}"><i class="fa-solid fa-play"></i> ${T.play}</button>` : ''}
                <a class="inv-btn" href="animazione_personaggio?id_personaggio=${encodeURIComponent(e.id)}"><i class="fa-solid fa-wand-magic-sparkles"></i> ${T.animation}</a>
            </div>`;

        const info = `
            <div class="inv-dpanel" data-dpanel="info" ${drawerTab === 'info' ? '' : 'hidden'}>
                <p>${esc(e.descrizione || T.noDesc)}</p>
                ${traits.length ? `<div class="inv-traits"><h4>${T.traits}</h4><ul>${traits.map((x) => `<li>${esc(x)}</li>`).join('')}</ul></div>` : ''}
                <dl class="inv-facts">
                    ${e.data ? `<div><dt>${T.found}</dt><dd>${esc(formatDate(e.data, true))}</dd></div>` : ''}
                    ${e.ultima && e.quantita > 1 ? `<div><dt>${T.last}</dt><dd>${esc(formatDate(e.ultima, true))}</dd></div>` : ''}
                </dl>
                <h4 class="inv-subhead">${T.whereTitle}</h4>
                ${bannerCards(e)}
            </div>`;

        const dupes = Math.max(0, (e.quantita || 1) - 1);
        const upgrade = `
            <div class="inv-dpanel" data-dpanel="upgrade" ${drawerTab === 'upgrade' ? '' : 'hidden'}>
                <div class="inv-level">
                    <span>${T.level} <b>${e.livello >= 6 ? T.max : e.livello}</b></span>
                    ${e.livello < 6 ? `<span>${T.duplicatesOf(dupes, e.richieste)}</span>` : ''}
                </div>
                ${e.livello < 6 ? `<div class="inv-level__bar" aria-hidden="true"><i style="--w:${Math.min(100, e.richieste ? dupes / e.richieste * 100 : 0)}%"></i></div>` : ''}
                <div class="inv-stats">${statRows(e.stats, e.livello < 6 ? e.stats_next : null)}</div>
                ${e.livello < 6
                    ? `<button type="button" class="inv-btn inv-btn--primary inv-btn--wide" data-upgrade="${Number(e.id)}" ${e.potenziabile ? '' : 'disabled'}><i class="fa-solid fa-angles-up"></i> ${T.upgrade}</button>
                       <small class="inv-muted">${e.potenziabile ? T.ready : T.missingCopies(Math.max(0, e.richieste - dupes))}</small>`
                    : `<p class="inv-max"><i class="fa-solid fa-crown"></i> ${T.maxReached}</p>`}
                ${features.frammenti && e.eccesso > 0 ? `
                    <div class="inv-excess">
                        <span>${fragIcon()} ${esc(T.excess(e.eccesso, e.eccesso * (e.valore_frammenti || 0)))}</span>
                        <button type="button" class="inv-btn inv-btn--small" data-convert="${Number(e.id)}">${T.convert}</button>
                    </div>` : ''}
            </div>`;

        const kit = `
            <div class="inv-dpanel" data-dpanel="kit" ${drawerTab === 'kit' ? '' : 'hidden'}>
                ${s.role ? `<p class="inv-role"><i class="fa-solid fa-shield-halved"></i> ${esc(s.role)}</p>` : ''}
                <div class="inv-ability"><span class="inv-ability__tag is-passive">${T.passive}</span><strong>${esc(s.passive_name || T.none)}</strong><p>${esc(s.passive_desc || '')}</p></div>
                <div class="inv-ability"><span class="inv-ability__tag is-special">${T.special}</span><strong>${esc(s.special_name || '')}</strong><small>${T.costE}: ${num(s.special_cost)} E · CD ${num(s.special_cooldown)}</small><p>${esc(s.special_desc || '')}</p></div>
                ${s.ultimate_name ? `<div class="inv-ability"><span class="inv-ability__tag is-ultimate">${T.ultimate}</span><strong>${esc(s.ultimate_name)}</strong><p>${esc(s.ultimate_desc || '')}</p></div>` : ''}
            </div>`;

        return `${head}${actions}${tabs}${info}${upgrade}${kit}`;
    };

    const moveDrawerInk = () => {
        const ink = $('[data-dtab-ink]');
        const active = $('.inv-dtab.is-active');
        if (!ink || !active) return;
        ink.style.width = `${active.offsetWidth}px`;
        ink.style.transform = `translateX(${active.offsetLeft}px)`;
    };

    const visibleKeys = () => $$('.inv-card', $('[data-groups]')).map((c) => c.dataset.key);

    const openDrawer = (key, push = true, direction = 0) => {
        const e = byKey.get(key);
        if (!e) return;
        const drawer = $('[data-drawer]');
        const wasOpen = !drawer.hidden;
        if (!wasOpen) lastFocus = document.activeElement;
        drawerKey = key;
        if (!e.posseduto) drawerTab = 'info';
        stopAudio();

        const body = $('[data-drawer-body]');
        body.innerHTML = drawerHtml(e);
        body.scrollTop = 0;
        body.dataset.dir = direction > 0 ? 'next' : direction < 0 ? 'prev' : '';
        replay(body, 'is-swapping');

        drawer.hidden = false;
        document.body.classList.add('inv-lock');
        requestAnimationFrame(() => {
            drawer.classList.add('is-open');
            moveDrawerInk();
        });
        if (!wasOpen) $('.inv-drawer__panel', drawer).focus({ preventScroll: true });

        const keys = visibleKeys();
        const idx = keys.indexOf(key);
        $('[data-drawer-prev]').disabled = idx <= 0;
        $('[data-drawer-next]').disabled = idx < 0 || idx >= keys.length - 1;

        if (e.id) {
            state.open = String(e.id);
            writeUrl(push);
        }

        // Aperto = visto: il badge NEW se ne va.
        if (e.posseduto && e.nuovo && features.nuovi) {
            e.nuovo = false;
            data.totali.nuovi = Math.max(0, (data.totali.nuovi || 0) - 1);
            const badge = cards.get(key)?.querySelector('.inv-card__new');
            if (badge) {
                badge.classList.add('is-leaving');
                setTimeout(() => badge.remove(), reducedMotion() ? 0 : 300);
            }
            renderHead();
            action('visto', { ids: [e.id] }).catch(() => {});
        }
    };

    const closeDrawer = (push = true) => {
        const drawer = $('[data-drawer]');
        if (drawer.hidden) return;
        stopAudio();
        drawer.classList.remove('is-open');
        document.body.classList.remove('inv-lock');
        setTimeout(() => { if (!drawer.classList.contains('is-open')) drawer.hidden = true; }, reducedMotion() ? 0 : 320);
        drawerKey = null;
        state.open = null;
        writeUrl(push);
        lastFocus?.focus?.({ preventScroll: true });
    };

    const stepDrawer = (dir) => {
        const keys = visibleKeys();
        const next = keys[keys.indexOf(drawerKey) + dir];
        if (next) openDrawer(next, false, dir);
    };

    /* ── Azioni ──────────────────────────────────────────────────────── */

    const levelUpFx = () => {
        if (reducedMotion()) return;
        const panel = $('.inv-drawer__panel');
        const fx = document.createElement('div');
        fx.className = 'inv-levelup';
        fx.innerHTML = `<span>${T.levelUp}</span>`;
        panel.appendChild(fx);
        setTimeout(() => fx.remove(), 1400);
    };

    /** "+N" che sale dal saldo dei frammenti. */
    const fragmentFx = (amount) => {
        const target = $('[data-frag-balance]');
        if (!target || reducedMotion()) return;
        const fx = document.createElement('span');
        fx.className = 'inv-frag-float';
        fx.innerHTML = `${fragIcon()} +${num(amount)}`;
        target.closest('.inv-frag-balance').appendChild(fx);
        setTimeout(() => fx.remove(), 1300);
    };

    const showAchievements = (ids) => {
        (Array.isArray(ids) ? ids : []).forEach((id) => window.showAchievementPopup?.(id));
    };

    const doUpgrade = async (id) => {
        const e = entries.find((x) => x.id === id);
        if (!e) return;
        const copies = `${e.richieste} ${lang === 'en' ? (e.richieste === 1 ? 'copy' : 'copies') : (e.richieste === 1 ? 'copia' : 'copie')}`;
        const next = e.livello + 1 >= 6 ? T.max : e.livello + 1;
        if (!await confirmBox(T.confirmUpgradeTitle, `<p>${T.confirmUpgrade(copies, e.nome, next)}</p>`, T.upgrade)) return;
        try {
            const res = await postJson('/api/game/upgrade_character.php', { character_id: id });
            showAchievements(res.unlocked_achievements);
            drawerTab = 'upgrade';
            await reload();
            openDrawer(`c${id}`, false);
            levelUpFx();
            replay(cards.get(`c${id}`), 'is-pulsing');
        } catch (err) {
            toast(err.message, true);
        }
    };

    const doUpgradeAll = async () => {
        const ready = entries.filter((e) => e.posseduto && e.potenziabile);
        if (!ready.length) return;
        const list = `<ul class="inv-confirm__list">${ready.slice(0, 30).map((e) => `<li><span style="color:${esc(rarityColor(e.rarita))}">●</span> ${esc(e.nome)} <small>${T.lv} ${e.livello}</small></li>`).join('')}${ready.length > 30 ? `<li>… +${ready.length - 30}</li>` : ''}</ul>`;
        if (!await confirmBox(T.upgradeAllTitle, `<p>${T.upgradeAllBody(ready.length)}</p>${list}`, lang === 'en' ? 'Upgrade all' : 'Potenzia tutto')) return;
        const btn = $('[data-upgrade-all]');
        btn.disabled = true;
        try {
            const res = await postJson('/api/game/upgrade_all_characters.php', {});
            showAchievements(res.unlocked_achievements);
            await reload();
            ready.forEach((e) => replay(cards.get(e.key), 'is-pulsing'));
            toast(T.upgradeAllDone(Number(res.upgraded_count || 0), Number(res.levels_gained || 0)));
        } catch (err) {
            toast(err.message, true);
            btn.disabled = false;
        }
    };

    /** Conversione: di un personaggio, oppure delle rarita' scelte. */
    const doConvert = async ({ id = 0, rarities = [] } = {}) => {
        const list = id
            ? entries.filter((e) => e.id === id)
            : entries.filter((e) => e.posseduto && e.eccesso > 0 && rarities.includes(e.rarita));
        const copies = list.reduce((a, e) => a + (e.eccesso || 0), 0);
        const value = list.reduce((a, e) => a + (e.eccesso || 0) * (e.valore_frammenti || 0), 0);
        if (!copies) return;
        if (!await confirmBox(T.confirmConvertTitle, `<p>${T.confirmConvert(copies, value)}</p>`, T.convert)) return;
        try {
            const res = await action('converti', id ? { id } : { rarita: rarities });
            toast(T.converted(res.frammenti || 0));
            const reopen = drawerKey;
            const before = Number(data.frammenti?.saldo || 0);
            await reload();
            if (reopen) openDrawer(reopen, false);
            const bal = $('[data-frag-balance]');
            if (bal) {
                bal.dataset.value = String(before);
                bal.textContent = num(before);
                countTo(bal, Number(data.frammenti?.saldo || 0), { duration: 900 });
                fragmentFx(res.frammenti || 0);
            }
        } catch (err) {
            toast(err.message, true);
        }
    };

    const doBuy = async (id) => {
        const item = (data.frammenti?.negozio || []).find((x) => x.id === id);
        if (!item) return;
        if (!await confirmBox(T.fr.confirmBuyTitle, `<p>${T.fr.confirmBuy(item.nome, item.prezzo)}</p>`, T.fr.buy)) return;
        try {
            const before = Number(data.frammenti?.saldo || 0);
            await action('compra', { id });
            toast(T.fr.boughtToast(item.nome));
            await reload();
            const bal = $('[data-frag-balance]');
            if (bal) {
                bal.dataset.value = String(before);
                countTo(bal, Number(data.frammenti?.saldo || 0), { duration: 700 });
            }
            replay($(`[data-buy="${id}"]`)?.closest('.inv-frag-item'), 'is-bought-now');
        } catch (err) {
            toast(err.message, true);
        }
    };

    const doClaim = async (categoryId, button) => {
        button.disabled = true;
        try {
            await action('collezione', { categoria_id: categoryId });
            toast(T.collections.claimedToast);
            const c = CATEGORIES.find((x) => x.id === categoryId);
            if (c) c.riscosso = true;
            renderCollections();
            renderHead();
            replay($(`[data-coll="${categoryId}"]`), 'is-claimed');
        } catch (err) {
            toast(err.message, true);
            button.disabled = false;
        }
    };

    const toggleFlag = async (kind, id, button) => {
        const e = entries.find((x) => x.id === id);
        if (!e) return;
        const field = kind === 'preferito' ? 'preferito' : 'wishlist';
        const on = !e[field];
        button.disabled = true;
        try {
            await action(kind, { id, on });
            e[field] = on;
            refreshCard(e);
            if (drawerKey === e.key) openDrawer(e.key, false);
            renderToolbar();
            if (on) replay($(`[data-${kind === 'preferito' ? 'favorite' : 'wishlist'}="${id}"]`), 'is-popped');
        } catch (err) {
            toast(err.message, true);
        } finally {
            button.disabled = false;
        }
    };

    /* ── Eventi ──────────────────────────────────────────────────────── */

    let searchTimer = 0;
    const applyFilters = (animate = true) => {
        writeUrl();
        renderToolbar();
        renderGrid({ animate });
    };

    const toggleCollapse = (btn) => {
        const section = btn.closest('.inv-group');
        const id = btn.dataset.collapse;
        const willCollapse = !collapsed.has(id);
        if (willCollapse) collapsed.add(id); else collapsed.delete(id);
        btn.setAttribute('aria-expanded', String(!willCollapse));
        section.classList.add('is-animating');
        section.classList.toggle('is-collapsed', willCollapse);
        const body = $('.inv-group__body', section);
        const done = () => section.classList.remove('is-animating');
        if (reducedMotion()) done(); else { body.addEventListener('transitionend', done, { once: true }); setTimeout(done, 450); }
    };

    const bind = () => {
        $('[data-search]').addEventListener('input', (ev) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                state.q = ev.target.value;
                writeUrl();
                renderGrid({ animate: false });
            }, 160);
        });

        root.addEventListener('click', (ev) => {
            const t = ev.target;
            const rarity = t.closest('[data-rarity]');
            if (rarity) {
                const k = rarity.dataset.rarity;
                state.r = state.r.includes(k) ? state.r.filter((x) => x !== k) : [...state.r, k];
                return applyFilters();
            }
            const status = t.closest('[data-status]');
            if (status) {
                const k = status.dataset.status;
                state.s = state.s.includes(k) ? state.s.filter((x) => x !== k) : [...state.s, k];
                if (k === 'owned' && state.s.includes('missing')) state.s = state.s.filter((x) => x !== 'missing');
                if (k === 'missing' && state.s.includes('owned')) state.s = state.s.filter((x) => x !== 'owned');
                return applyFilters();
            }
            const category = t.closest('[data-category]');
            if (category) {
                const k = category.dataset.category;
                state.cat = state.cat.toLowerCase() === k.toLowerCase() ? '' : k;
                return applyFilters();
            }
            const density = t.closest('[data-density]');
            if (density) {
                state.d = density.dataset.density;
                try { localStorage.setItem('cripsum:inv:density', state.d); } catch (e) { /* niente */ }
                renderToolbar();
                return renderGrid();
            }
            if (t.closest('[data-reset]')) {
                Object.assign(state, { ...DEFAULTS, d: state.d });
                return applyFilters();
            }
            if (t.closest('[data-filters-toggle]')) return setFilters(!state.filtersOpen);
            const collapse = t.closest('[data-collapse]');
            if (collapse) return toggleCollapse(collapse);
            const tab = t.closest('.inv-tab[data-tab]');
            if (tab) {
                if (state.tab === tab.dataset.tab) return;
                state.tab = tab.dataset.tab;
                writeUrl();
                return renderPanels(true);
            }
            const viewCat = t.closest('[data-view-category]');
            if (viewCat) {
                state.cat = viewCat.dataset.viewCategory;
                state.tab = 'collezione';
                applyFilters();
                renderPanels(true);
                return window.scrollTo({ top: 0, behavior: reducedMotion() ? 'auto' : 'smooth' });
            }
            const claim = t.closest('[data-claim]');
            if (claim) return doClaim(Number(claim.dataset.claim), claim);
            const buy = t.closest('[data-buy]');
            if (buy) return doBuy(Number(buy.dataset.buy));
            const fragRarity = t.closest('[data-frag-rarity]');
            if (fragRarity) {
                const k = fragRarity.dataset.fragRarity;
                if (convertChoice.has(k)) convertChoice.delete(k); else convertChoice.add(k);
                return renderFragments();
            }
            if (t.closest('[data-convert-chosen]')) return doConvert({ rarities: [...convertChoice] });
            if (t.closest('[data-upgrade-all]')) return doUpgradeAll();
            if (t.closest('[data-mark-seen]')) {
                return action('visto', { ids: [] }).then(() => {
                    entries.forEach((e) => {
                        if (!e.nuovo) return;
                        e.nuovo = false;
                        const badge = cards.get(e.key)?.querySelector('.inv-card__new');
                        if (badge) { badge.classList.add('is-leaving'); setTimeout(() => badge.remove(), 300); }
                    });
                    data.totali.nuovi = 0;
                    renderHead();
                    renderToolbar();
                    if (state.s.includes('new')) renderGrid();
                    toast(T.seenDone);
                }).catch((err) => toast(err.message, true));
            }
            const card = t.closest('.inv-card');
            if (card) return openDrawer(card.dataset.key);
        });

        const drawer = $('[data-drawer]');
        drawer.addEventListener('click', (ev) => {
            const t = ev.target;
            if (t.closest('[data-drawer-close]')) return closeDrawer();
            if (t.closest('[data-drawer-prev]')) return stepDrawer(-1);
            if (t.closest('[data-drawer-next]')) return stepDrawer(1);
            const dtab = t.closest('[data-dtab]');
            if (dtab) {
                drawerTab = dtab.dataset.dtab;
                $$('[data-dtab]', drawer).forEach((b) => { const on = b === dtab; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', String(on)); });
                $$('[data-dpanel]', drawer).forEach((p) => {
                    const on = p.dataset.dpanel === drawerTab;
                    p.hidden = !on;
                    if (on) replay(p, 'is-entering');
                });
                return moveDrawerInk();
            }
            const up = t.closest('[data-upgrade]');
            if (up) return doUpgrade(Number(up.dataset.upgrade));
            const conv = t.closest('[data-convert]');
            if (conv) return doConvert({ id: Number(conv.dataset.convert) });
            const fav = t.closest('[data-favorite]');
            if (fav) return toggleFlag('preferito', Number(fav.dataset.favorite), fav);
            const wish = t.closest('[data-wishlist]');
            if (wish) return toggleFlag('wishlist', Number(wish.dataset.wishlist), wish);
            const play = t.closest('[data-play]');
            if (play) {
                if (!audio.paused && play.classList.contains('is-playing')) return stopAudio();
                stopAudio();
                audio.src = play.dataset.play;
                let volume = 0.8;
                try {
                    const v = localStorage.getItem('cripsum.lootbox.volume');
                    if (v !== null && !Number.isNaN(Number(v))) volume = Math.min(1, Math.max(0, Number(v)));
                    if (localStorage.getItem('cripsum.lootbox.muted') === 'true') volume = 0;
                } catch (e) { /* niente */ }
                audio.volume = volume;
                audio.play().then(() => {
                    play.classList.add('is-playing');
                    const icon = $('i', play);
                    if (icon) icon.className = 'fa-solid fa-pause';
                }).catch(() => {});
                audio.onended = () => stopAudio();
            }
        });

        document.addEventListener('keydown', (ev) => {
            if ($('[data-drawer]').hidden || !$('[data-confirm]').hidden) return;
            if (ev.key === 'Escape') closeDrawer();
            if (ev.key === 'ArrowLeft') stepDrawer(-1);
            if (ev.key === 'ArrowRight') stepDrawer(1);
            if (ev.key === 'Tab') {
                const focusables = $$('.inv-drawer__panel button:not([disabled]), .inv-drawer__panel a[href]');
                if (!focusables.length) return;
                const first = focusables[0];
                const last = focusables[focusables.length - 1];
                if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
                else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
            }
        });

        window.addEventListener('popstate', () => {
            readUrl();
            renderToolbar();
            renderGrid();
            renderPanels(true);
            if (state.open) openDrawer(`c${state.open}`, false); else closeDrawer(false);
        });

        window.addEventListener('resize', () => { moveInk(); moveDrawerInk(); }, { passive: true });

        // Il conto alla rovescia del negozio si aggiorna da solo.
        setInterval(() => {
            const el = $('[data-frag-renew]');
            if (el && data.frammenti?.rinnovo) el.textContent = countdown(data.frammenti.rinnovo);
        }, 30000);
    };

    /* ── Avvio ───────────────────────────────────────────────────────── */

    readUrl();
    indexEntries();
    buildCards();

    sortDd = makeDropdown($('[data-dd="sort"]'), {
        label: T.sort.label,
        value: state.o,
        options: [
            ['rarity', T.sort.rarity, 'fa-arrow-up-short-wide'],
            ['rarity_desc', T.sort.rarity_desc, 'fa-arrow-down-wide-short'],
            ['recent', T.sort.recent, 'fa-clock'],
            ['name', T.sort.name, 'fa-arrow-down-a-z'],
            ['quantity', T.sort.quantity, 'fa-layer-group'],
            ['level', T.sort.level, 'fa-angles-up'],
        ],
        onChange: (v) => { state.o = v; applyFilters(); },
    });
    groupDd = makeDropdown($('[data-dd="group"]'), {
        label: T.group.label,
        value: state.g,
        options: [
            ['rarity', T.group.rarity, 'fa-gem'],
            ['category', T.group.category, 'fa-tags'],
            ['none', T.group.none, 'fa-grip'],
        ],
        onChange: (v) => { state.g = v; applyFilters(); },
    });

    renderAll({ animate: true });
    renderPanels(false);
    bind();
    requestAnimationFrame(() => {
        root.classList.add('is-ready');
        moveInk();
    });
    if (state.open && byKey.has(`c${state.open}`)) openDrawer(`c${state.open}`, false);
})();
