/*
 * Inventario.
 *
 * I dati arrivano gia' nella pagina (#inv-data, da gacha_collection_payload):
 * niente richieste al caricamento. Le carte si creano una volta sola; i
 * filtri le spostano e le nascondono senza ricostruire l'HTML, quindi
 * scrivere nella ricerca non ridisegna duecento carte a ogni tasto.
 *
 * I filtri stanno nell'indirizzo (?r=segreto&s=owned...), cosi' si possono
 * condividere e il tasto indietro funziona; ?c=<id> apre un personaggio.
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
    if (!data) return;

    const lang = root.dataset.lang === 'en' ? 'en' : 'it';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const $ = (sel, el = document) => el.querySelector(sel);
    const $$ = (sel, el = document) => Array.from(el.querySelectorAll(sel));
    const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[c]));
    const num = (v) => Number(v || 0).toLocaleString(lang === 'en' ? 'en-GB' : 'it-IT');
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    const T = {
        it: {
            results: (n) => `${n} ${n === 1 ? 'personaggio' : 'personaggi'}`,
            progress: (a, b) => `${a} su ${b} personaggi`,
            standard: (a, b) => `Standard ${a}/${b}`,
            boxes: 'Casse aperte', dupes: 'Con doppioni', upgradable: 'Potenziabili', news: 'Nuovi',
            status: { owned: 'Posseduti', missing: 'Mancanti', duplicates: 'Doppioni', upgradable: 'Potenziabili', limited: 'Limitati', new: 'Nuovi', favorites: 'Preferiti', wishlist: 'Wishlist' },
            unknown: '???', notOwned: 'Non trovato', lv: 'Lv.', max: 'MAX', limited: 'Limitato', newBadge: 'NEW',
            noCategory: 'Senza categoria',
            tabs: { info: 'Panoramica', upgrade: 'Potenziamento', kit: 'Abilità' },
            copies: 'Copie', found: 'Trovato il', last: 'Ultima copia', category: 'Categoria',
            noDesc: 'Nessuna descrizione.', traits: 'Tratti',
            available: 'Disponibile ora in', availableSoon: 'In arrivo in', inStandard: 'Nel banner standard',
            notInPools: 'Non si trova in nessun banner attivo.',
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
            excess: (n, f) => `${n} ${n === 1 ? 'copia' : 'copie'} in eccesso → ${num(f)} frammenti`,
            excessHelp: 'Le copie che non servono più ai potenziamenti fino al MAX.',
            convert: 'Converti', convertAll: 'Converti tutte le copie in eccesso',
            confirmConvertTitle: 'Convertire in frammenti?',
            confirmConvert: (n, f) => `<strong>${n}</strong> ${n === 1 ? 'copia' : 'copie'} in eccesso diventano <strong>${num(f)}</strong> frammenti. Le copie che servono ai potenziamenti restano.`,
            converted: (f) => `+${num(f)} frammenti`,
            stats: { hp: 'HP', attack: 'ATK', defense: 'DEF', speed: 'SPD' },
            passive: 'Passiva', special: 'Speciale', ultimate: 'Ultimate', cost: 'Costo', none: 'Nessuna',
            collections: { reward: 'Premio', claim: 'Riscuoti', claimed: 'Riscosso', noReward: 'Nessun premio', complete: 'Completa', claimedToast: 'Premio inviato nella posta!', view: 'Vedi i personaggi' },
            fragments: {
                title: 'Frammenti', balance: 'I tuoi frammenti', shop: 'Negozio della settimana', renew: 'Si rinnova tra',
                buy: 'Compra', bought: 'Comprato', owned: (n) => n ? `ne hai ${n}` : 'non ce l\'hai', values: 'Quanto vale una copia in eccesso',
                none: 'Nessuna copia in eccesso da convertire.', unavailable: 'I frammenti arrivano con il prossimo aggiornamento del database.',
                confirmBuyTitle: 'Comprare?', confirmBuy: (n, p) => `<strong>${esc(n)}</strong> per <strong>${num(p)}</strong> frammenti?`,
                boughtToast: (n) => `${n} è nel tuo inventario!`, notEnough: 'Frammenti insufficienti',
            },
            seenDone: 'Fatto: niente più badge NEW.',
            error: 'Qualcosa è andato storto. Riprova.',
            days: 'g', hours: 'h',
        },
        en: {
            results: (n) => `${n} ${n === 1 ? 'character' : 'characters'}`,
            progress: (a, b) => `${a} of ${b} characters`,
            standard: (a, b) => `Standard ${a}/${b}`,
            boxes: 'Pulls', dupes: 'With duplicates', upgradable: 'Upgradeable', news: 'New',
            status: { owned: 'Owned', missing: 'Missing', duplicates: 'Duplicates', upgradable: 'Upgradeable', limited: 'Limited', new: 'New', favorites: 'Favorites', wishlist: 'Wishlist' },
            unknown: '???', notOwned: 'Not found', lv: 'Lv.', max: 'MAX', limited: 'Limited', newBadge: 'NEW',
            noCategory: 'No category',
            tabs: { info: 'Overview', upgrade: 'Upgrade', kit: 'Abilities' },
            copies: 'Copies', found: 'Found on', last: 'Last copy', category: 'Category',
            noDesc: 'No description.', traits: 'Traits',
            available: 'Available now in', availableSoon: 'Coming in', inStandard: 'In the standard banner',
            notInPools: 'Not available in any active banner.',
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
            excess: (n, f) => `${n} extra ${n === 1 ? 'copy' : 'copies'} → ${num(f)} fragments`,
            excessHelp: 'Copies no longer needed to reach MAX level.',
            convert: 'Convert', convertAll: 'Convert all extra copies',
            confirmConvertTitle: 'Convert to fragments?',
            confirmConvert: (n, f) => `<strong>${n}</strong> extra ${n === 1 ? 'copy becomes' : 'copies become'} <strong>${num(f)}</strong> fragments. Copies needed for upgrades are kept.`,
            converted: (f) => `+${num(f)} fragments`,
            stats: { hp: 'HP', attack: 'ATK', defense: 'DEF', speed: 'SPD' },
            passive: 'Passive', special: 'Special', ultimate: 'Ultimate', cost: 'Cost', none: 'None',
            collections: { reward: 'Reward', claim: 'Claim', claimed: 'Claimed', noReward: 'No reward', complete: 'Complete', claimedToast: 'Reward sent to your inbox!', view: 'See characters' },
            fragments: {
                title: 'Fragments', balance: 'Your fragments', shop: 'This week\'s shop', renew: 'Renews in',
                buy: 'Buy', bought: 'Bought', owned: (n) => n ? `you have ${n}` : 'not owned', values: 'What an extra copy is worth',
                none: 'No extra copies to convert.', unavailable: 'Fragments arrive with the next database update.',
                confirmBuyTitle: 'Buy?', confirmBuy: (n, p) => `<strong>${esc(n)}</strong> for <strong>${num(p)}</strong> fragments?`,
                boughtToast: (n) => `${n} is in your inventory!`, notEnough: 'Not enough fragments',
            },
            seenDone: 'Done: no more NEW badges.',
            error: 'Something went wrong. Try again.',
            days: 'd', hours: 'h',
        },
    }[lang];

    const RARITIES = data.rarita || [];
    const RARITY_RANK = Object.fromEntries(RARITIES.map((r, i) => [r.key, i]));
    const RARITY = Object.fromEntries(RARITIES.map((r) => [r.key, r]));
    const CATEGORIES = data.categorie || [];
    const CATEGORY = new Map(CATEGORIES.map((c) => [String(c.nome).toLowerCase(), c]));
    const BANNERS = new Map((data.banner || []).map((b) => [String(b.key), b]));
    const features = data.funzioni || {};

    /* ── Stato e URL ─────────────────────────────────────────────────── */

    const DEFAULTS = { q: '', r: [], s: [], cat: '', o: 'rarity', g: 'rarity', d: 'comfortable' };
    const state = { ...DEFAULTS, tab: 'collezione', open: null };

    const readUrl = () => {
        const p = new URLSearchParams(location.search);
        state.q = p.get('q') || '';
        state.r = (p.get('r') || '').split(',').filter((k) => RARITY[k]);
        state.s = (p.get('s') || '').split(',').filter((k) => T.status[k]);
        state.cat = p.get('cat') || '';
        state.o = ['rarity', 'recent', 'name', 'quantity', 'level'].includes(p.get('o')) ? p.get('o') : DEFAULTS.o;
        state.g = ['rarity', 'category', 'none'].includes(p.get('g')) ? p.get('g') : DEFAULTS.g;
        let density = p.get('d');
        if (!density) {
            try { density = localStorage.getItem('cripsum:inv:density'); } catch (e) { density = null; }
        }
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
        } catch (e) { /* file:// o sandbox */ }
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
        if (!res.ok || json.ok === false || json.success === false) {
            throw new Error(json.message || T.error);
        }
        return json;
    };
    const action = (name, body = {}) => postJson('/api/gacha/azioni.php', { action: name, ...body });

    /** Rilegge tutto dopo un'azione che cambia copie o livelli. */
    const reload = async () => {
        const res = await fetch(`/api/gacha/collezione.php?lang=${lang}`, { credentials: 'same-origin', cache: 'no-store' });
        const json = await res.json();
        if (!json.ok) throw new Error(json.message || T.error);
        data = json;
        indexEntries();
        buildCards();
        renderAll();
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
            setTimeout(() => { el.hidden = true; }, 250);
        }, isError ? 3600 : 2600);
    };

    const confirmBox = (title, html, okLabel = null) => new Promise((resolve) => {
        const box = $('[data-confirm]');
        $('[data-confirm-title]', box).textContent = title;
        $('[data-confirm-body]', box).innerHTML = html;
        const ok = $('[data-confirm-ok]', box);
        const originalLabel = ok.dataset.label || ok.textContent;
        ok.dataset.label = originalLabel;
        ok.textContent = okLabel || originalLabel;
        const previous = document.activeElement;
        box.hidden = false;
        ok.focus();
        const done = (value) => {
            box.hidden = true;
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

    /* ── Dati ────────────────────────────────────────────────────────── */

    let entries = [];
    let byKey = new Map();

    const indexEntries = () => {
        entries = (data.personaggi || []).map((e, index) => ({ ...e, index }));
        byKey = new Map(entries.map((e) => [e.key, e]));
    };

    const categoryOf = (e) => (e.categoria ? CATEGORY.get(String(e.categoria).toLowerCase()) : null);
    const rarityLabel = (k) => RARITY[k]?.label || k;
    const rarityColor = (k) => RARITY[k]?.colore || '#fff';

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
            if (state.r.length && !state.r.includes(e.rarita)) return false;
            if (state.cat && String(e.categoria || '').toLowerCase() !== state.cat.toLowerCase()) return false;
            if (state.s.length) {
                // Stati della stessa "famiglia" in OR, il resto in AND.
                const own = state.s.filter((s) => ['owned', 'missing'].includes(s));
                const rest = state.s.filter((s) => !['owned', 'missing'].includes(s));
                if (own.length && !own.some((s) => matchesStatus(e, s))) return false;
                if (!rest.every((s) => matchesStatus(e, s))) return false;
            }
            if (q) {
                const hay = `${e.mascherato ? '' : e.nome || ''} ${rarityLabel(e.rarita)} ${e.categoria || ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });

        const rank = (e) => RARITY_RANK[e.rarita] ?? 0;
        const sorters = {
            rarity: (a, b) => rank(b) - rank(a) || Number(b.posseduto) - Number(a.posseduto) || a.index - b.index,
            recent: (a, b) => String(b.ultima || '').localeCompare(String(a.ultima || '')) || rank(b) - rank(a),
            name: (a, b) => String(a.nome || '~').localeCompare(String(b.nome || '~'), lang),
            quantity: (a, b) => (b.quantita || 0) - (a.quantita || 0) || rank(b) - rank(a),
            level: (a, b) => (b.livello || 0) - (a.livello || 0) || rank(b) - rank(a),
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
        const color = rarityColor(e.rarita);
        const art = e.mascherato
            ? '<span class="inv-card__q" aria-hidden="true">?</span>'
            : `<img src="${esc(e.img || '/img/boh.png')}" alt="" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='/img/boh.png'">`;
        const name = e.mascherato ? T.unknown : e.nome;
        return `
            <span class="inv-card__art">${art}
                ${e.posseduto && e.nuovo ? `<span class="inv-card__new">${T.newBadge}</span>` : ''}
                ${e.limitato && !e.mascherato ? `<span class="inv-card__ribbon">${T.limited}</span>` : ''}
                ${e.posseduto ? `<span class="inv-card__qty${e.quantita > 1 ? ' is-dupe' : ''}">×${num(e.quantita)}</span>` : ''}
                ${e.posseduto && e.preferito ? '<span class="inv-card__fav" aria-hidden="true"><i class="fa-solid fa-star"></i></span>' : ''}
                ${!e.posseduto && e.wishlist ? '<span class="inv-card__fav is-wish" aria-hidden="true"><i class="fa-solid fa-heart"></i></span>' : ''}
                ${e.posseduto && e.potenziabile ? '<span class="inv-card__up" aria-hidden="true"><i class="fa-solid fa-angles-up"></i></span>' : ''}
            </span>
            <span class="inv-card__body">
                <span class="inv-card__name">${esc(name)}</span>
                <span class="inv-card__meta">
                    <span class="inv-card__rarity" style="color:${esc(color)}">${esc(rarityLabel(e.rarita))}</span>
                    ${cat && !e.mascherato ? `<span class="inv-card__cat" style="--cc:${esc(cat.colore || '#94a3b8')}">${esc(cat.label || cat.nome)}</span>` : ''}
                </span>
                ${e.posseduto ? levelPips(e.livello) : ''}
            </span>`;
    };

    const buildCards = () => {
        cards.clear();
        entries.forEach((e) => {
            const el = document.createElement(e.mascherato ? 'div' : 'button');
            if (!e.mascherato) {
                el.type = 'button';
                el.setAttribute('aria-label', e.nome + (e.posseduto ? '' : ` — ${T.notOwned}`));
            } else {
                el.setAttribute('aria-label', `${T.unknown} — ${rarityLabel(e.rarita)}`);
            }
            el.className = `inv-card r-${e.rarita}${e.posseduto ? ' is-owned' : ' is-missing'}${e.mascherato ? ' is-masked' : ''}${e.livello >= 6 ? ' is-max' : ''}`;
            el.style.setProperty('--rc', rarityColor(e.rarita));
            el.dataset.key = e.key;
            el.innerHTML = cardHtml(e);
            cards.set(e.key, el);
        });
    };

    /* ── Rendering ───────────────────────────────────────────────────── */

    const renderHead = () => {
        const tot = data.totali || {};
        const visible = Math.max(1, tot.visibili || 0);
        const pct = Math.round((tot.posseduti || 0) / visible * 100);
        const ring = $('[data-ring]');
        if (ring) ring.style.strokeDasharray = `${pct} 100`;
        $('[data-progress-pct]').textContent = `${pct}%`;
        $('[data-progress-text]').textContent = `${T.progress(num(tot.posseduti), num(tot.visibili))} · ${T.standard(num(tot.standard_posseduti), num(tot.standard))}`;
        $('[data-stats]').innerHTML = [
            [T.boxes, tot.casse, 'fa-box-open'],
            [T.dupes, tot.duplicati, 'fa-clone'],
            [T.upgradable, tot.potenziabili, 'fa-angles-up'],
            [T.news, tot.nuovi, 'fa-star'],
        ].map(([label, value, icon]) => `<div><dt><i class="fa-solid ${icon}" aria-hidden="true"></i> ${esc(label)}</dt><dd>${num(value)}</dd></div>`).join('');

        const up = $('[data-upgrade-all]');
        if (up) {
            up.disabled = !(tot.potenziabili > 0);
            const label = $('span', up);
            if (label) label.textContent = `${lang === 'en' ? 'Upgrade all' : 'Potenzia tutto'}${tot.potenziabili > 0 ? ` (${tot.potenziabili})` : ''}`;
        }
        const seen = $('[data-mark-seen]');
        if (seen) seen.hidden = !(features.nuovi && tot.nuovi > 0);

        const claimable = (data.categorie || []).some((c) => c.id && !c.riscosso && (c.premio_godos > 0 || c.premio_badge) && c.posseduti >= c.totale);
        const dot = $('[data-claim-dot]');
        if (dot) dot.hidden = !claimable;
    };

    const chip = (value, label, active, count = null, color = null, attr = 'data-chip') =>
        `<button type="button" class="inv-chip${active ? ' is-active' : ''}" ${attr}="${esc(value)}" aria-pressed="${active}"${color ? ` style="--cc:${esc(color)}"` : ''}>${color ? '<i class="inv-chip__dot" aria-hidden="true"></i>' : ''}${esc(label)}${count !== null ? `<small>${num(count)}</small>` : ''}</button>`;

    const renderToolbar = () => {
        const counts = {};
        entries.forEach((e) => { counts[e.rarita] = counts[e.rarita] || { tot: 0, own: 0 }; counts[e.rarita].tot++; if (e.posseduto) counts[e.rarita].own++; });
        $('[data-rarity-chips]').innerHTML = RARITIES.slice().reverse().filter((r) => counts[r.key]).map((r) =>
            chip(r.key, r.label, state.r.includes(r.key), null, r.colore, 'data-rarity').replace('</button>', `<small>${counts[r.key].own}/${counts[r.key].tot}</small></button>`)
        ).join('');

        const statusKeys = ['owned', 'missing', 'duplicates', 'upgradable', 'limited'];
        if (features.nuovi) statusKeys.push('new');
        if (features.preferiti) statusKeys.push('favorites');
        if (features.wishlist) statusKeys.push('wishlist');
        $('[data-status-chips]').innerHTML = statusKeys.map((k) => chip(k, T.status[k], state.s.includes(k), entries.filter((e) => matchesStatus(e, k)).length, null, 'data-status')).join('');

        const catBox = $('[data-category-chips]');
        const catGroup = $('[data-category-group]');
        if (catGroup) catGroup.hidden = CATEGORIES.length === 0;
        if (catBox) catBox.innerHTML = CATEGORIES.map((c) => chip(c.nome, c.label || c.nome, state.cat.toLowerCase() === String(c.nome).toLowerCase(), c.totale, c.colore || '#94a3b8', 'data-category')).join('');

        $('[data-search]').value = state.q;
        $('[data-sort]').value = state.o;
        $('[data-group]').value = state.g;
        $$('[data-density]').forEach((b) => b.classList.toggle('is-active', b.dataset.density === state.d));
        root.classList.toggle('is-compact', state.d === 'compact');

        const active = state.s.length + (state.cat ? 1 : 0) + (state.o !== DEFAULTS.o ? 1 : 0) + (state.g !== DEFAULTS.g ? 1 : 0);
        const badge = $('[data-filters-count]');
        badge.hidden = active === 0;
        badge.textContent = active;
    };

    const groupKey = (e) => {
        if (state.g === 'rarity') return e.rarita;
        if (state.g === 'category') return e.categoria ? String(e.categoria).toLowerCase() : '';
        return 'all';
    };

    const groupTitle = (key, list) => {
        const own = list.filter((e) => e.posseduto).length;
        if (state.g === 'rarity') {
            return { label: rarityLabel(key), color: rarityColor(key), own, tot: list.length };
        }
        if (state.g === 'category') {
            const cat = CATEGORY.get(key);
            return { label: cat ? (cat.label || cat.nome) : (key || T.noCategory), color: cat?.colore || '#94a3b8', icon: cat?.icona, own, tot: list.length };
        }
        return null;
    };

    const collapsed = new Set();

    const renderGrid = () => {
        const list = filtered();
        const groupsEl = $('[data-groups]');
        const groups = new Map();
        list.forEach((e) => {
            const k = groupKey(e);
            if (!groups.has(k)) groups.set(k, []);
            groups.get(k).push(e);
        });

        const frag = document.createDocumentFragment();
        groups.forEach((items, key) => {
            const section = document.createElement('section');
            section.className = 'inv-group';
            const title = groupTitle(key, items);
            const id = `g-${state.g}-${key || 'none'}`;
            if (title) {
                const pct = title.tot ? Math.round(title.own / title.tot * 100) : 0;
                const isCollapsed = collapsed.has(id);
                section.innerHTML = `
                    <button type="button" class="inv-group__head" data-collapse="${esc(id)}" aria-expanded="${!isCollapsed}" style="--gc:${esc(title.color)}">
                        ${title.icon ? `<i class="${esc(title.icon)}" aria-hidden="true"></i>` : '<i class="inv-group__dot" aria-hidden="true"></i>'}
                        <strong>${esc(title.label)}</strong>
                        <span class="inv-group__count">${title.own}/${title.tot}</span>
                        <span class="inv-group__bar" aria-hidden="true"><i style="width:${pct}%"></i></span>
                        <i class="fa-solid fa-chevron-down inv-group__chev" aria-hidden="true"></i>
                    </button>`;
                if (isCollapsed) section.classList.add('is-collapsed');
            }
            const grid = document.createElement('div');
            grid.className = 'inv-grid';
            items.forEach((e) => grid.appendChild(cards.get(e.key)));
            section.appendChild(grid);
            frag.appendChild(section);
        });

        groupsEl.replaceChildren(frag);
        $('[data-empty]').hidden = list.length > 0;
        $('[data-results]').textContent = T.results(list.length);
    };

    const renderCollections = () => {
        const box = $('[data-collections]');
        if (!box) return;
        const cats = data.categorie || [];
        box.innerHTML = cats.map((c) => {
            const pct = c.totale ? Math.round(c.posseduti / c.totale * 100) : 0;
            const complete = c.posseduti >= c.totale;
            const hasReward = c.premio_godos > 0 || c.premio_badge;
            const reward = hasReward
                ? `${c.premio_godos > 0 ? `${num(c.premio_godos)} Godos` : ''}${c.premio_godos > 0 && c.premio_badge ? ' + ' : ''}${c.premio_badge ? 'badge' : ''}`
                : T.collections.noReward;
            let actionHtml = '';
            if (hasReward && c.id && features.collezioni) {
                if (c.riscosso) actionHtml = `<span class="inv-coll__done"><i class="fa-solid fa-check"></i> ${T.collections.claimed}</span>`;
                else actionHtml = `<button type="button" class="inv-btn inv-btn--primary inv-btn--small" data-claim="${Number(c.id)}" ${complete ? '' : 'disabled'}>${T.collections.claim}</button>`;
            }
            return `
                <article class="inv-coll${complete ? ' is-complete' : ''}" style="--cc:${esc(c.colore || '#94a3b8')}">
                    <span class="inv-coll__icon"><i class="${esc(c.icona || 'fa-solid fa-tag')}" aria-hidden="true"></i></span>
                    <div class="inv-coll__main">
                        <strong>${esc(c.label || c.nome)}</strong>
                        <span class="inv-coll__bar" aria-hidden="true"><i style="width:${pct}%"></i></span>
                        <small>${c.posseduti}/${c.totale}${complete ? ` · ${T.collections.complete}` : ''} · ${T.collections.reward}: ${esc(reward)}</small>
                    </div>
                    <div class="inv-coll__actions">
                        ${actionHtml}
                        <button type="button" class="inv-link" data-view-category="${esc(c.nome)}">${T.collections.view}</button>
                    </div>
                </article>`;
        }).join('');
    };

    const countdown = (iso) => {
        const diff = new Date(iso).getTime() - Date.now();
        if (!(diff > 0)) return '';
        const d = Math.floor(diff / 86400000);
        const h = Math.floor(diff % 86400000 / 3600000);
        return d > 0 ? `${d}${T.days} ${h}${T.hours}` : `${h}${T.hours}`;
    };

    const renderFragments = () => {
        const box = $('[data-fragments]');
        if (!box) return;
        const f = data.frammenti;
        const F = T.fragments;
        if (!f || !features.frammenti) {
            box.innerHTML = `<p class="inv-panel__intro"><i class="fa-solid fa-circle-info"></i> ${F.unavailable}</p>`;
            return;
        }
        const excess = entries.filter((e) => e.posseduto && e.eccesso > 0);
        const excessCopies = excess.reduce((a, e) => a + e.eccesso, 0);
        const excessValue = excess.reduce((a, e) => a + e.eccesso * (e.valore_frammenti || 0), 0);

        box.innerHTML = `
            <div class="inv-frag-top">
                <div class="inv-frag-balance">
                    <span>${F.balance}</span>
                    <strong><i class="fa-solid fa-gem" aria-hidden="true"></i> ${num(f.saldo)}</strong>
                </div>
                <div class="inv-frag-convert">
                    <p>${excessCopies > 0 ? esc(T.excess(excessCopies, excessValue)) : F.none}</p>
                    <small>${T.excessHelp}</small>
                    <button type="button" class="inv-btn inv-btn--primary" data-convert-all ${excessCopies > 0 ? '' : 'disabled'}>
                        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> ${T.convertAll}
                    </button>
                </div>
            </div>

            <div class="inv-frag-head">
                <h2>${F.shop}</h2>
                <span>${F.renew} <b>${esc(countdown(f.rinnovo))}</b></span>
            </div>
            <div class="inv-frag-shop">
                ${(f.negozio || []).map((it) => `
                    <article class="inv-frag-item${it.comprato ? ' is-bought' : ''}" style="--rc:${esc(rarityColor(it.rarita))}">
                        <img src="${esc(it.img || '/img/boh.png')}" alt="" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='/img/boh.png'">
                        <div>
                            <strong>${esc(it.nome)}</strong>
                            <small><span style="color:${esc(rarityColor(it.rarita))}">${esc(rarityLabel(it.rarita))}</span> · ${esc(F.owned(it.posseduti))}</small>
                        </div>
                        <button type="button" class="inv-btn ${it.comprato ? 'inv-btn--ghost' : 'inv-btn--primary'} inv-btn--small" data-buy="${Number(it.id)}" ${it.comprato || f.saldo < it.prezzo ? 'disabled' : ''} title="${!it.comprato && f.saldo < it.prezzo ? esc(F.notEnough) : ''}">
                            ${it.comprato ? `<i class="fa-solid fa-check"></i> ${F.bought}` : `<i class="fa-solid fa-gem"></i> ${num(it.prezzo)}`}
                        </button>
                    </article>`).join('')}
            </div>

            <details class="inv-frag-values">
                <summary>${F.values}</summary>
                <div>${RARITIES.map((r) => `<span style="--rc:${esc(r.colore)}"><i></i>${esc(r.label)} <b>${num(f.valori?.[r.key] || 0)}</b></span>`).join('')}</div>
            </details>`;
    };

    const renderPanels = () => {
        $$('.inv-tab').forEach((b) => {
            const on = b.dataset.tab === state.tab;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        $$('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== state.tab; });
    };

    const renderAll = () => {
        renderHead();
        renderToolbar();
        renderGrid();
        renderCollections();
        renderFragments();
        renderPanels();
    };

    /* ── Pannello di dettaglio ───────────────────────────────────────── */

    let drawerKey = null;
    let drawerTab = 'info';
    let lastFocus = null;
    const audio = new Audio();
    audio.preload = 'none';

    const stopAudio = () => {
        audio.pause();
        audio.currentTime = 0;
        $$('[data-play]').forEach((b) => b.classList.remove('is-playing'));
    };

    const statRows = (now, next) => ['hp', 'attack', 'defense', 'speed'].map((k) => `
        <div class="inv-stat">
            <span>${T.stats[k]}</span>
            <b>${num(now?.[k])}</b>
            ${next && next[k] !== undefined ? `<i class="fa-solid fa-arrow-right" aria-hidden="true"></i><b class="is-next">${num(next[k])}</b>` : ''}
        </div>`).join('');

    const bannerLinks = (e) => {
        const parts = [];
        (e.banner || []).forEach((key) => {
            const b = BANNERS.get(String(key));
            if (!b) return;
            parts.push(`<a class="inv-pill" href="lootbox?banner=${encodeURIComponent(b.key)}"><i class="fa-solid fa-star"></i> ${esc(b.nome)}${b.stato === 'prossimamente' ? ' · ⏳' : ''}</a>`);
        });
        if (e.standard) parts.push(`<a class="inv-pill" href="lootbox"><i class="fa-solid fa-box-open"></i> ${T.inStandard}</a>`);
        return parts.length ? `<div class="inv-avail"><span>${T.available}</span>${parts.join('')}</div>` : `<p class="inv-muted">${T.notInPools}</p>`;
    };

    const formatDate = (value, withTime = false) => {
        if (!value) return '';
        const d = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return '';
        const locale = lang === 'en' ? 'en-GB' : 'it-IT';
        return withTime ? `${d.toLocaleDateString(locale)} ${d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })}` : d.toLocaleDateString(locale);
    };

    const drawerHtml = (e) => {
        const cat = categoryOf(e);
        const color = rarityColor(e.rarita);
        const head = `
            <div class="inv-hero r-${e.rarita}${e.posseduto ? '' : ' is-missing'}" style="--rc:${esc(color)}">
                <div class="inv-hero__art">
                    ${e.mascherato ? '<span class="inv-card__q">?</span>' : `<img src="${esc(e.img || '/img/boh.png')}" alt="" onerror="this.onerror=null;this.src='/img/boh.png'">`}
                </div>
                <div class="inv-hero__info">
                    <span class="inv-hero__rarity">${esc(rarityLabel(e.rarita))}</span>
                    <h2 id="invDrawerTitle">${esc(e.mascherato ? T.unknown : e.nome)}</h2>
                    <div class="inv-hero__chips">
                        ${cat ? `<span class="inv-pill" style="--cc:${esc(cat.colore || '#94a3b8')}"><i class="${esc(cat.icona || 'fa-solid fa-tag')}"></i> ${esc(cat.label || cat.nome)}</span>` : ''}
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
                    ${bannerLinks(e)}
                    ${features.wishlist ? `
                        <button type="button" class="inv-btn ${e.wishlist ? 'inv-btn--active' : ''}" data-wishlist="${Number(e.id)}" aria-pressed="${e.wishlist ? 'true' : 'false'}">
                            <i class="fa-${e.wishlist ? 'solid' : 'regular'} fa-heart"></i> ${e.wishlist ? T.wishlistOn : T.wishlistAdd}
                        </button>
                        <small class="inv-muted">${T.wishlistHelp}</small>` : ''}
                </div>`;
        }

        const traits = String(e.caratteristiche || '').split(';').map((x) => x.trim()).filter(Boolean);
        const s = e.stats || {};
        const tabs = `
            <div class="inv-dtabs" role="tablist">
                ${['info', 'upgrade', 'kit'].map((k) => `<button type="button" role="tab" class="inv-dtab${drawerTab === k ? ' is-active' : ''}" data-dtab="${k}" aria-selected="${drawerTab === k}">${T.tabs[k]}${k === 'upgrade' && e.potenziabile ? ' <i class="inv-dot" aria-hidden="true"></i>' : ''}</button>`).join('')}
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
                ${bannerLinks(e)}
            </div>`;

        const dupes = Math.max(0, (e.quantita || 1) - 1);
        const upgrade = `
            <div class="inv-dpanel" data-dpanel="upgrade" ${drawerTab === 'upgrade' ? '' : 'hidden'}>
                <div class="inv-level">
                    <span>${T.level} <b>${e.livello >= 6 ? T.max : e.livello}</b></span>
                    ${e.livello < 6 ? `<span>${T.duplicatesOf(dupes, e.richieste)}</span>` : ''}
                </div>
                ${e.livello < 6 ? `<div class="inv-level__bar" aria-hidden="true"><i style="width:${Math.min(100, e.richieste ? dupes / e.richieste * 100 : 0)}%"></i></div>` : ''}
                <div class="inv-stats">${statRows(e.stats, e.livello < 6 ? e.stats_next : null)}</div>
                ${e.livello < 6
                    ? `<button type="button" class="inv-btn inv-btn--primary inv-btn--wide" data-upgrade="${Number(e.id)}" ${e.potenziabile ? '' : 'disabled'}><i class="fa-solid fa-angles-up"></i> ${T.upgrade}</button>
                       <small class="inv-muted">${e.potenziabile ? T.ready : T.missingCopies(Math.max(0, e.richieste - dupes))}</small>`
                    : `<p class="inv-max"><i class="fa-solid fa-crown"></i> ${T.maxReached}</p>`}
                ${features.frammenti && e.eccesso > 0 ? `
                    <div class="inv-excess">
                        <span>${esc(T.excess(e.eccesso, e.eccesso * (e.valore_frammenti || 0)))}</span>
                        <button type="button" class="inv-btn inv-btn--small" data-convert="${Number(e.id)}"><i class="fa-solid fa-gem"></i> ${T.convert}</button>
                    </div>` : ''}
            </div>`;

        const kit = `
            <div class="inv-dpanel" data-dpanel="kit" ${drawerTab === 'kit' ? '' : 'hidden'}>
                ${s.role ? `<p class="inv-role"><i class="fa-solid fa-shield-halved"></i> ${esc(s.role)}</p>` : ''}
                <div class="inv-ability"><span class="inv-ability__tag is-passive">${T.passive}</span><strong>${esc(s.passive_name || T.none)}</strong><p>${esc(s.passive_desc || '')}</p></div>
                <div class="inv-ability"><span class="inv-ability__tag is-special">${T.special}</span><strong>${esc(s.special_name || '')}</strong><small>${T.cost}: ${num(s.special_cost)} E · CD ${num(s.special_cooldown)}</small><p>${esc(s.special_desc || '')}</p></div>
                ${s.ultimate_name ? `<div class="inv-ability"><span class="inv-ability__tag is-ultimate">${T.ultimate}</span><strong>${esc(s.ultimate_name)}</strong><p>${esc(s.ultimate_desc || '')}</p></div>` : ''}
            </div>`;

        return `${head}${actions}${tabs}${info}${upgrade}${kit}`;
    };

    const visibleKeys = () => $$('.inv-card', $('[data-groups]')).map((c) => c.dataset.key);

    const openDrawer = (key, push = true) => {
        const e = byKey.get(key);
        if (!e) return;
        const drawer = $('[data-drawer]');
        if (drawer.hidden) lastFocus = document.activeElement;
        drawerKey = key;
        if (!e.posseduto) drawerTab = 'info';
        stopAudio();
        $('[data-drawer-body]').innerHTML = drawerHtml(e);
        drawer.hidden = false;
        document.body.classList.add('inv-lock');
        requestAnimationFrame(() => drawer.classList.add('is-open'));
        $('.inv-drawer__panel', drawer).focus({ preventScroll: true });

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
            const card = cards.get(key);
            card?.querySelector('.inv-card__new')?.remove();
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
        setTimeout(() => { drawer.hidden = true; }, reducedMotion ? 0 : 200);
        drawerKey = null;
        state.open = null;
        writeUrl(push);
        lastFocus?.focus?.({ preventScroll: true });
    };

    const stepDrawer = (dir) => {
        const keys = visibleKeys();
        const idx = keys.indexOf(drawerKey);
        const next = keys[idx + dir];
        if (next) openDrawer(next, false);
    };

    /* ── Azioni ──────────────────────────────────────────────────────── */

    const refreshCard = (e) => {
        const card = cards.get(e.key);
        if (card) card.innerHTML = cardHtml(e);
    };

    const levelUpFx = () => {
        if (reducedMotion) return;
        const panel = $('.inv-drawer__panel');
        const fx = document.createElement('div');
        fx.className = 'inv-levelup';
        fx.textContent = T.levelUp;
        panel.appendChild(fx);
        setTimeout(() => fx.remove(), 1200);
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
            levelUpFx();
            drawerTab = 'upgrade';
            await reload();
            openDrawer(`c${id}`, false);
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
            toast(T.upgradeAllDone(Number(res.upgraded_count || 0), Number(res.levels_gained || 0)));
        } catch (err) {
            toast(err.message, true);
            btn.disabled = false;
        }
    };

    const doConvert = async (id = 0) => {
        const list = id ? entries.filter((e) => e.id === id) : entries.filter((e) => e.posseduto && e.eccesso > 0);
        const copies = list.reduce((a, e) => a + (e.eccesso || 0), 0);
        const value = list.reduce((a, e) => a + (e.eccesso || 0) * (e.valore_frammenti || 0), 0);
        if (!copies) return;
        if (!await confirmBox(T.confirmConvertTitle, `<p>${T.confirmConvert(copies, value)}</p>`, T.convert)) return;
        try {
            const res = await action('converti', id ? { id } : {});
            toast(T.converted(res.frammenti || 0));
            const reopen = drawerKey;
            await reload();
            if (reopen) openDrawer(reopen, false);
        } catch (err) {
            toast(err.message, true);
        }
    };

    const doBuy = async (id) => {
        const item = (data.frammenti?.negozio || []).find((x) => x.id === id);
        if (!item) return;
        if (!await confirmBox(T.fragments.confirmBuyTitle, `<p>${T.fragments.confirmBuy(item.nome, item.prezzo)}</p>`, T.fragments.buy)) return;
        try {
            await action('compra', { id });
            toast(T.fragments.boughtToast(item.nome));
            await reload();
        } catch (err) {
            toast(err.message, true);
        }
    };

    const doClaim = async (categoryId, button) => {
        button.disabled = true;
        try {
            await action('collezione', { categoria_id: categoryId });
            toast(T.collections.claimedToast);
            const c = (data.categorie || []).find((x) => x.id === categoryId);
            if (c) c.riscosso = true;
            renderCollections();
            renderHead();
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
        } catch (err) {
            toast(err.message, true);
        } finally {
            button.disabled = false;
        }
    };

    /* ── Eventi ──────────────────────────────────────────────────────── */

    let searchTimer = 0;
    const applyFilters = () => {
        writeUrl();
        renderToolbar();
        renderGrid();
    };

    const bind = () => {
        $('[data-search]').addEventListener('input', (ev) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                state.q = ev.target.value;
                writeUrl();
                renderGrid();
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
                // Posseduti e mancanti insieme non filtrano nulla: l'ultimo vince.
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
                return renderToolbar();
            }
            if (t.closest('[data-reset]')) {
                Object.assign(state, { ...DEFAULTS, d: state.d });
                return applyFilters();
            }
            if (t.closest('[data-filters-toggle]')) {
                const panel = $('[data-filters]');
                panel.hidden = !panel.hidden;
                t.closest('[data-filters-toggle]').setAttribute('aria-expanded', String(!panel.hidden));
                return;
            }
            const collapse = t.closest('[data-collapse]');
            if (collapse) {
                const id = collapse.dataset.collapse;
                if (collapsed.has(id)) collapsed.delete(id); else collapsed.add(id);
                collapse.closest('.inv-group').classList.toggle('is-collapsed', collapsed.has(id));
                collapse.setAttribute('aria-expanded', String(!collapsed.has(id)));
                return;
            }
            const tab = t.closest('[data-tab]');
            if (tab && tab.classList.contains('inv-tab')) {
                state.tab = tab.dataset.tab;
                writeUrl();
                return renderPanels();
            }
            const viewCat = t.closest('[data-view-category]');
            if (viewCat) {
                state.cat = viewCat.dataset.viewCategory;
                state.tab = 'collezione';
                applyFilters();
                renderPanels();
                return window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
            }
            const claim = t.closest('[data-claim]');
            if (claim) return doClaim(Number(claim.dataset.claim), claim);
            const buy = t.closest('[data-buy]');
            if (buy) return doBuy(Number(buy.dataset.buy));
            if (t.closest('[data-convert-all]')) return doConvert(0);
            if (t.closest('[data-upgrade-all]')) return doUpgradeAll();
            if (t.closest('[data-mark-seen]')) {
                return action('visto', { ids: [] }).then(() => {
                    entries.forEach((e) => { if (e.nuovo) { e.nuovo = false; refreshCard(e); } });
                    data.totali.nuovi = 0;
                    renderHead();
                    renderToolbar();
                    if (state.s.includes('new')) renderGrid();
                    toast(T.seenDone);
                }).catch((err) => toast(err.message, true));
            }
            const card = t.closest('.inv-card');
            if (card && !card.classList.contains('is-masked')) return openDrawer(card.dataset.key);
            if (card) return openDrawer(card.dataset.key);
        });

        $('[data-sort]').addEventListener('change', (ev) => { state.o = ev.target.value; applyFilters(); });
        $('[data-group]').addEventListener('change', (ev) => { state.g = ev.target.value; applyFilters(); });

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
                $$('[data-dpanel]', drawer).forEach((p) => { p.hidden = p.dataset.dpanel !== drawerTab; });
                return;
            }
            const up = t.closest('[data-upgrade]');
            if (up) return doUpgrade(Number(up.dataset.upgrade));
            const conv = t.closest('[data-convert]');
            if (conv) return doConvert(Number(conv.dataset.convert));
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
                audio.play().then(() => play.classList.add('is-playing')).catch(() => {});
                audio.onended = () => play.classList.remove('is-playing');
            }
        });

        document.addEventListener('keydown', (ev) => {
            if ($('[data-drawer]').hidden || !$('[data-confirm]').hidden) return;
            if (ev.key === 'Escape') closeDrawer();
            if (ev.key === 'ArrowLeft') stepDrawer(-1);
            if (ev.key === 'ArrowRight') stepDrawer(1);
            if (ev.key === 'Tab') {
                // Il focus resta nel pannello finche' e' aperto.
                const focusables = $$('.inv-drawer__panel button:not([disabled]), .inv-drawer__panel a[href], .inv-drawer__panel [tabindex="0"]');
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
            renderPanels();
            if (state.open) openDrawer(`c${state.open}`, false); else closeDrawer(false);
        });
    };

    /* ── Avvio ───────────────────────────────────────────────────────── */

    readUrl();
    indexEntries();
    buildCards();
    renderAll();
    bind();
    if (state.open && byKey.has(`c${state.open}`)) openDrawer(`c${state.open}`, false);
})();
