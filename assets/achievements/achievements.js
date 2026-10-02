/**
 * Cripsum™ — pagina degli achievement.
 *
 * I dati arrivano già dentro la pagina (#achData, scritto da
 * includes/achievements/page.php con ach_overview): niente richieste
 * all'apertura. Il server si richiama solo per incassare un premio
 * (api/achievements/claim.php) e per riallinearsi dopo uno sblocco
 * (api/achievements/overview.php).
 *
 * Qui non si decide niente: progressi, sblocchi e premi sono del server.
 * Fanno eccezione i due achievement che conta il browser dai suoi cookie
 * (edit guardati, giorni di Goon Generator), di cui si mostra solo la barra.
 */
(() => {
    'use strict';

    const dataEl = document.getElementById('achData');
    if (!dataEl) return;

    let data;
    try {
        data = JSON.parse(dataEl.textContent || '{}');
    } catch (_) {
        return;
    }
    if (!data || !Array.isArray(data.items)) return;

    const lang = data.lang === 'en' ? 'en' : 'it';
    const locale = lang === 'en' ? 'en-GB' : 'it-IT';
    const userId = Number(window.CNAV_STATE && window.CNAV_STATE.userId) || 0;

    const T = {
        it: {
            secretName: 'Achievement segreto',
            secretText: 'Nessuno sa come si sblocca. Continua a esplorare.',
            secret: 'Segreto',
            isNew: 'Nuovo',
            open: (name) => `Apri i dettagli di ${name}`,
            step: (n, total) => `Gradino ${n} di ${total}`,
            points: 'Punti',
            reward: 'Premio',
            rarity: 'Rarità',
            rarityValue: (pct) => `Ce l'ha il ${pct}% dei giocatori`,
            rarityShort: (pct) => `${pct}%`,
            rarityTip: 'Giocatori che ce l\'hanno',
            unlockedOn: 'Sbloccato il',
            category: 'Categoria',
            tier: 'Livello',
            claim: (n) => `Riscuoti ${n}`,
            claimed: 'Riscosso',
            claiming: 'Un attimo…',
            claimedToast: (n) => `+${n} Godos riscossi`,
            claimError: 'Non sono riuscito a riscuotere il premio. Riprova.',
            claimNothing: 'Questo premio risulta già riscosso.',
            copy: 'Copia link',
            copied: 'Link copiato.',
            copyFailed: 'Non sono riuscito a copiare il link.',
            showcase: 'Mettilo in vetrina',
            series: 'Tutti i gradini',
            progress: 'Progresso',
            locked: 'Da sbloccare',
            unlocked: 'Sbloccato',
            noReward: 'Solo punti',
            freshToast: (n) => n === 1 ? 'Hai sbloccato un achievement nuovo!' : `Hai sbloccato ${n} achievement nuovi!`,
            timelineEmpty: 'Qui compariranno gli achievement che sblocchi, dal più recente.',
            hours: 'h',
            minutes: 'min',
            rankNext: (n, name) => `${n} punti a ${name}`,
            rankMax: 'Grado massimo raggiunto',
            konamiAgain: 'Codice riconosciuto. L\'achievement ce l\'hai già, ma che stile.',
            of: (a, b) => `${a} su ${b}`
        },
        en: {
            secretName: 'Secret achievement',
            secretText: 'Nobody knows how to unlock it. Keep exploring.',
            secret: 'Secret',
            isNew: 'New',
            open: (name) => `Open details for ${name}`,
            step: (n, total) => `Step ${n} of ${total}`,
            points: 'Points',
            reward: 'Reward',
            rarity: 'Rarity',
            rarityValue: (pct) => `${pct}% of players have it`,
            rarityShort: (pct) => `${pct}%`,
            rarityTip: 'Players who have it',
            unlockedOn: 'Unlocked on',
            category: 'Category',
            tier: 'Tier',
            claim: (n) => `Claim ${n}`,
            claimed: 'Claimed',
            claiming: 'One moment…',
            claimedToast: (n) => `+${n} Godos claimed`,
            claimError: 'Could not claim the reward. Try again.',
            claimNothing: 'This reward was already claimed.',
            copy: 'Copy link',
            copied: 'Link copied.',
            copyFailed: 'Could not copy the link.',
            showcase: 'Show it on your profile',
            series: 'All steps',
            progress: 'Progress',
            locked: 'Locked',
            unlocked: 'Unlocked',
            noReward: 'Points only',
            freshToast: (n) => n === 1 ? 'You unlocked a new achievement!' : `You unlocked ${n} new achievements!`,
            timelineEmpty: 'The achievements you unlock will show up here, newest first.',
            hours: 'h',
            minutes: 'min',
            rankNext: (n, name) => `${n} points to ${name}`,
            rankMax: 'Highest rank reached',
            konamiAgain: 'Code accepted. You already have the achievement, but nice moves.',
            of: (a, b) => `${a} of ${b}`
        }
    }[lang];

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const SECRET_IMAGE = '/img/achievements/_secret.svg';
    const DEFAULT_IMAGE = '/img/achievements/_default.svg';
    const TIERS = ['bronzo', 'argento', 'oro', 'platino', 'diamante'];
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // L'ultima volta che questa pagina è stata aperta, prima che navbar.js
    // (che gira dopo) la aggiorni: serve a sapere cosa è «nuovo».
    let previousSeen = 0;
    try {
        previousSeen = Number.parseInt(localStorage.getItem('cnav.achvSeen.' + userId), 10) || 0;
    } catch (_) {
        previousSeen = 0;
    }

    const store = {
        get(key, fallback) {
            try {
                return localStorage.getItem('cripsum:achievements:' + key) || fallback;
            } catch (_) {
                return fallback;
            }
        },
        set(key, value) {
            try {
                localStorage.setItem('cripsum:achievements:' + key, value);
            } catch (_) {
                /* navigazione privata: la scelta vale per questa visita */
            }
        }
    };

    const state = {
        category: 'all',
        status: store.get('status', 'all'),
        sort: store.get('sort', 'default'),
        view: store.get('view', 'grid'),
        search: '',
        firstRender: true,
        openId: 0
    };

    // ── Utilità ────────────────────────────────────────────────────────────

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[char]));

    const fold = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    // Le migliaia si separano sempre, come fa il PHP nella testata: per
    // l'italiano toLocaleString lascia unite le cifre sotto 10.000.
    const number = (value) => {
        const [whole, fraction] = String(Math.round(Number(value || 0) * 10) / 10).split('.');
        const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, lang === 'en' ? ',' : '.');
        return fraction ? grouped + (lang === 'en' ? '.' : ',') + fraction : grouped;
    };
    const tierOf = (item) => (TIERS.includes(item.tier) ? item.tier : 'bronzo');
    const tierName = (item) => (data.tiers && data.tiers[tierOf(item)]) || tierOf(item);

    function cookieJson(name) {
        try {
            const part = document.cookie.split('; ').find((cookie) => cookie.startsWith(name + '='));
            if (!part) return null;
            return JSON.parse(decodeURIComponent(part.slice(name.length + 1)));
        } catch (_) {
            return null;
        }
    }

    function formatDate(item, withTime) {
        if (!item.unlocked_ts) return '';
        const date = new Date(item.unlocked_ts * 1000);
        const day = date.toLocaleDateString(locale, { day: '2-digit', month: '2-digit', year: 'numeric' });
        return withTime ? `${day} · ${date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })}` : day;
    }

    function duration(seconds) {
        const total = Math.max(0, Math.floor(seconds));
        const hours = Math.floor(total / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        if (hours >= 10 || (hours > 0 && minutes === 0)) return `${number(hours)} ${T.hours}`;
        if (hours > 0) return `${hours} ${T.hours} ${minutes} ${T.minutes}`;
        return `${minutes} ${T.minutes}`;
    }

    function progressLabel(progress) {
        if (progress.format === 'duration') return `${duration(progress.current)} / ${duration(progress.target)}`;
        if (progress.format === 'percent') return `${progress.current}% / ${progress.target}%`;
        return `${number(progress.current)} / ${number(progress.target)}`;
    }

    const ratio = (progress) => (progress && progress.target > 0 ? Math.min(1, progress.current / progress.target) : 0);

    // ── Modello ────────────────────────────────────────────────────────────

    let items = [];
    let byId = new Map();
    let tiles = [];

    /** Barre dei due achievement che conta il browser: il server dà solo il traguardo. */
    function clientProgress(item) {
        if (item.unlocked || item.progress || !item.client) return item.progress;

        if (item.key === 'fanatico-edit' || (!item.key && item.id === 17)) {
            const total = Number(data.extras && data.extras.edits_total) || 0;
            const watched = cookieJson('watchedVideos');
            if (total > 0) {
                return { current: Math.min(Array.isArray(watched) ? watched.length : 0, total), target: total, format: 'number' };
            }
        }
        if (item.key === 'gooning-streak' || (!item.key && item.id === 20)) {
            const days = cookieJson('daysVisitedGoon');
            return { current: Math.min(Array.isArray(days) ? days.length : 0, 10), target: 10, format: 'number' };
        }
        return null;
    }

    function buildModel() {
        items = data.items.map((raw) => {
            const item = Object.assign({}, raw);
            item.hidden = item.secret && !item.unlocked;
            item.displayName = item.hidden ? T.secretName : item.name;
            item.displayText = item.hidden ? T.secretText : item.description;
            item.displayImage = item.hidden ? SECRET_IMAGE : (item.image || DEFAULT_IMAGE);
            item.progress = clientProgress(item);
            item.isNew = !!item.unlocked && (!!item.fresh || (previousSeen > 0 && Number(item.unlocked_ts) > previousSeen));
            return item;
        });
        byId = new Map(items.map((item) => [item.id, item]));

        // Una serie è una card sola: mostra il primo gradino ancora da
        // sbloccare (o l'ultimo, se sono fatti tutti).
        const series = new Map();
        tiles = [];
        items.forEach((item) => {
            if (!item.series || item.hidden) {
                tiles.push({ steps: [item], current: item });
                return;
            }
            if (!series.has(item.series)) {
                const tile = { steps: [], current: item };
                series.set(item.series, tile);
                tiles.push(tile);
            }
            series.get(item.series).steps.push(item);
        });
        tiles.forEach((tile) => {
            tile.current = tile.steps.find((step) => !step.unlocked) || tile.steps[tile.steps.length - 1];
            tile.order = tile.steps[0].order;
            tile.latest = Math.max(0, ...tile.steps.map((step) => Number(step.unlocked_ts) || 0));
        });
    }

    function visibleTiles() {
        const query = fold(state.search);

        const list = tiles.filter((tile) => {
            if (state.category !== 'all' && tile.current.category !== state.category) return false;
            if (state.status === 'unlocked' && !tile.steps.some((step) => step.unlocked)) return false;
            if (state.status === 'locked' && !tile.steps.some((step) => !step.unlocked)) return false;
            if (state.status === 'claimable' && !tile.steps.some((step) => step.claimable)) return false;
            if (query) {
                return tile.steps.some((step) => !step.hidden && fold(step.name + ' ' + step.description).includes(query));
            }
            return true;
        });

        const rarity = (tile) => (tile.current.owners_pct === null || tile.current.owners_pct === undefined ? 999 : tile.current.owners_pct);
        const closeness = (tile) => (tile.current.unlocked ? -1 : ratio(tile.current.progress));

        list.sort((a, b) => {
            switch (state.sort) {
                case 'closest':
                    return closeness(b) - closeness(a) || a.order - b.order;
                case 'rarest':
                    return rarity(a) - rarity(b) || b.current.points - a.current.points;
                case 'recent':
                    return b.latest - a.latest || a.order - b.order;
                case 'points':
                    return b.current.points - a.current.points || a.order - b.order;
                case 'name':
                    return a.current.displayName.localeCompare(b.current.displayName, locale);
                default:
                    return a.order - b.order;
            }
        });

        return list;
    }

    // ── Pezzi di HTML ──────────────────────────────────────────────────────

    function progressHtml(progress, extraClass) {
        if (!progress) return '';
        const value = ratio(progress);
        return `
            <div class="ach-progress ${extraClass || ''}">
                <div class="ach-progress__bar" role="progressbar" aria-label="${T.progress}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${Math.round(value * 100)}">
                    <span style="--p: ${value.toFixed(4)}"></span>
                </div>
                <span class="ach-progress__label">${escapeHtml(progressLabel(progress))}</span>
            </div>`;
    }

    function claimButton(item, extraClass) {
        return `
            <button type="button" class="ach-claim ${extraClass || ''}" data-claim="${item.id}">
                <img src="/img/godos.png" alt="" width="16" height="16">
                <span>${escapeHtml(T.claim(number(item.reward)))}</span>
            </button>`;
    }

    function cardHtml(tile, index) {
        const item = tile.current;
        const tier = tierOf(item);
        const isSeries = tile.steps.length > 1;
        const claimable = tile.steps.find((step) => step.claimable);
        const anyNew = tile.steps.some((step) => step.isNew);
        const stepIndex = tile.steps.indexOf(item) + 1;

        const classes = [
            'ach-card', 'tier-' + tier,
            item.unlocked ? 'is-unlocked' : 'is-locked',
            item.hidden ? 'is-secret' : '',
            anyNew ? 'is-new' : '',
            claimable ? 'is-claimable' : '',
            state.firstRender ? 'is-enter' : ''
        ].filter(Boolean).join(' ');

        const steps = isSeries ? `
            <ol class="ach-steps" aria-label="${escapeHtml(T.step(stepIndex, tile.steps.length))}">
                ${tile.steps.map((step) => `<li class="tier-${tierOf(step)}${step.unlocked ? ' is-done' : ''}${step === item ? ' is-current' : ''}"></li>`).join('')}
                <li class="ach-steps__label">${escapeHtml(T.step(stepIndex, tile.steps.length))}</li>
            </ol>` : '';

        const meta = [];
        meta.push(`<span class="ach-meta ach-meta--points" title="${T.points}"><i class="fa-solid fa-star" aria-hidden="true"></i>${number(item.points)}</span>`);
        if (!item.hidden && item.reward > 0 && !(claimable && claimable === item)) {
            meta.push(`<span class="ach-meta ach-meta--reward${item.claimed ? ' is-claimed' : ''}" title="${T.reward}"><img src="/img/godos.png" alt="" width="14" height="14">${number(item.reward)}${item.claimed ? '<i class="fa-solid fa-check" aria-hidden="true"></i>' : ''}</span>`);
        }
        if (item.owners_pct !== null && item.owners_pct !== undefined) {
            meta.push(`<span class="ach-meta" title="${T.rarityTip}"><i class="fa-solid fa-users" aria-hidden="true"></i>${escapeHtml(T.rarityShort(number(item.owners_pct)))}</span>`);
        }
        if (item.unlocked) {
            meta.push(`<time class="ach-meta" datetime="${escapeHtml(item.unlocked_at || '')}"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i>${escapeHtml(formatDate(item))}</time>`);
        }

        return `
            <article class="${classes}" data-id="${item.id}" style="--i: ${Math.min(index, 18)}; view-transition-name: ach-${tile.steps[0].id}">
                <div class="ach-card__medal">
                    <img src="${escapeHtml(item.displayImage)}" alt="" width="76" height="76" loading="lazy" decoding="async">
                </div>
                <div class="ach-card__body">
                    <div class="ach-card__head">
                        <span class="ach-tier"><i aria-hidden="true"></i>${escapeHtml(tierName(item))}</span>
                        ${item.secret ? `<span class="ach-flag ach-flag--secret"><i class="fa-solid fa-user-secret" aria-hidden="true"></i>${T.secret}</span>` : ''}
                        ${anyNew ? `<span class="ach-flag ach-flag--new">${T.isNew}</span>` : ''}
                    </div>
                    <h3 class="ach-card__title">
                        <button type="button" class="ach-card__open" data-open="${item.id}" aria-label="${escapeHtml(T.open(item.displayName))}">${escapeHtml(item.displayName)}</button>
                    </h3>
                    <p class="ach-card__text">${escapeHtml(item.displayText)}</p>
                    ${steps}
                    ${!item.unlocked ? progressHtml(item.progress) : ''}
                    <div class="ach-card__foot">
                        ${meta.join('')}
                        ${claimable ? claimButton(claimable) : ''}
                    </div>
                </div>
            </article>`;
    }

    function timelineHtml() {
        const unlocked = items.filter((item) => item.unlocked).sort((a, b) => (b.unlocked_ts || 0) - (a.unlocked_ts || 0));
        if (unlocked.length === 0) {
            return `<p class="ach-timeline__empty">${T.timelineEmpty}</p>`;
        }

        const groups = [];
        unlocked.forEach((item) => {
            const date = new Date((item.unlocked_ts || 0) * 1000);
            const label = date.toLocaleDateString(locale, { month: 'long', year: 'numeric' });
            let group = groups[groups.length - 1];
            if (!group || group.label !== label) {
                group = { label, rows: [], points: 0 };
                groups.push(group);
            }
            group.rows.push(item);
            group.points += item.points;
        });

        return groups.map((group) => `
            <section class="ach-month">
                <header class="ach-month__head">
                    <h3>${escapeHtml(group.label)}</h3>
                    <span>${number(group.rows.length)} · <i class="fa-solid fa-star" aria-hidden="true"></i> ${number(group.points)}</span>
                </header>
                <ol class="ach-month__list">
                    ${group.rows.map((item) => `
                        <li class="ach-row tier-${tierOf(item)}${item.isNew ? ' is-new' : ''}">
                            <img src="${escapeHtml(item.displayImage)}" alt="" width="44" height="44" loading="lazy">
                            <div class="ach-row__text">
                                <button type="button" class="ach-row__open" data-open="${item.id}">${escapeHtml(item.displayName)}</button>
                                <span>${escapeHtml(item.displayText)}</span>
                            </div>
                            <div class="ach-row__side">
                                <time datetime="${escapeHtml(item.unlocked_at || '')}">${escapeHtml(formatDate(item, true))}</time>
                                <span><i class="fa-solid fa-star" aria-hidden="true"></i> ${number(item.points)}</span>
                            </div>
                        </li>`).join('')}
                </ol>
            </section>`).join('');
    }

    function nearHtml() {
        const near = tiles
            .map((tile) => tile.current)
            .filter((item) => !item.unlocked && !item.hidden && item.progress && ratio(item.progress) > 0 && ratio(item.progress) < 1)
            .sort((a, b) => ratio(b.progress) - ratio(a.progress))
            .slice(0, 3);

        return near.map((item) => `
            <button type="button" class="ach-near__item tier-${tierOf(item)}" data-open="${item.id}">
                <img src="${escapeHtml(item.displayImage)}" alt="" width="46" height="46" loading="lazy">
                <span class="ach-near__text">
                    <strong>${escapeHtml(item.displayName)}</strong>
                    ${progressHtml(item.progress, 'ach-progress--slim')}
                </span>
                <span class="ach-near__pct">${Math.floor(ratio(item.progress) * 100)}%</span>
            </button>`).join('');
    }

    function detailHtml(item) {
        const tier = tierOf(item);
        const tile = tiles.find((candidate) => candidate.steps.includes(item)) || { steps: [item] };
        const category = (data.categories || []).find((entry) => entry.key === item.category);

        const facts = [];
        facts.push([T.points, `<i class="fa-solid fa-star" aria-hidden="true"></i> ${number(item.points)}`]);
        if (!item.hidden) {
            facts.push([T.reward, item.reward > 0
                ? `<img src="/img/godos.png" alt="" width="16" height="16"> ${number(item.reward)} Godos${item.claimed ? ` <em>· ${T.claimed}</em>` : ''}`
                : T.noReward]);
        }
        if (item.owners_pct !== null && item.owners_pct !== undefined) {
            facts.push([T.rarity, escapeHtml(T.rarityValue(number(item.owners_pct)))]);
        }
        if (category) {
            facts.push([T.category, escapeHtml(category.name)]);
        }
        if (item.unlocked) {
            facts.push([T.unlockedOn, escapeHtml(formatDate(item, true))]);
        }

        const ladder = tile.steps.length > 1 ? `
            <div class="ach-ladder">
                <h3>${T.series}</h3>
                <ol>
                    ${tile.steps.map((step) => `
                        <li class="tier-${tierOf(step)}${step.unlocked ? ' is-done' : ''}${step === item ? ' is-current' : ''}">
                            <img src="${escapeHtml(step.displayImage)}" alt="" width="38" height="38" loading="lazy">
                            <button type="button" class="ach-ladder__name" data-open="${step.id}">${escapeHtml(step.displayName)}</button>
                            <span class="ach-ladder__state">${step.unlocked
                                ? `<i class="fa-solid fa-check" aria-hidden="true"></i> ${T.unlocked}`
                                : (step.progress ? escapeHtml(progressLabel(step.progress)) : `<i class="fa-solid fa-lock" aria-hidden="true"></i> ${T.locked}`)}</span>
                            ${step.claimable && step !== item ? claimButton(step, 'ach-claim--small') : ''}
                        </li>`).join('')}
                </ol>
            </div>` : '';

        return `
            <div class="ach-detail tier-${tier} ${item.unlocked ? 'is-unlocked' : 'is-locked'}">
                <div class="ach-detail__medal">
                    <img src="${escapeHtml(item.displayImage)}" alt="" width="132" height="132">
                </div>
                <div class="ach-card__head ach-detail__head">
                    <span class="ach-tier"><i aria-hidden="true"></i>${escapeHtml(tierName(item))}</span>
                    ${item.secret ? `<span class="ach-flag ach-flag--secret"><i class="fa-solid fa-user-secret" aria-hidden="true"></i>${T.secret}</span>` : ''}
                    <span class="ach-flag ${item.unlocked ? 'ach-flag--done' : 'ach-flag--locked'}">
                        <i class="fa-solid ${item.unlocked ? 'fa-check' : 'fa-lock'}" aria-hidden="true"></i>${item.unlocked ? T.unlocked : T.locked}
                    </span>
                </div>
                <h2 id="achDialogTitle">${escapeHtml(item.displayName)}</h2>
                <p class="ach-detail__text">${escapeHtml(item.displayText)}</p>
                ${!item.unlocked ? progressHtml(item.progress, 'ach-progress--big') : ''}
                <dl class="ach-facts">
                    ${facts.map(([label, value]) => `<div><dt>${label}</dt><dd>${value}</dd></div>`).join('')}
                </dl>
                ${ladder}
                <div class="ach-detail__actions">
                    ${item.claimable ? claimButton(item) : ''}
                    ${item.unlocked ? `<a class="ach-btn" href="/${lang}/edit-profile"><i class="fa-solid fa-medal" aria-hidden="true"></i> ${T.showcase}</a>` : ''}
                    ${!item.hidden ? `<button type="button" class="ach-btn" data-copy="${item.id}"><i class="fa-solid fa-link" aria-hidden="true"></i> ${T.copy}</button>` : ''}
                </div>
            </div>`;
    }

    // ── Disegno ────────────────────────────────────────────────────────────

    const grid = $('#achGrid');
    const timeline = $('#achTimeline');
    const empty = $('#achEmpty');
    const dialog = $('#achDialog');
    const dialogBody = $('#achDialogBody');

    function paintList() {
        const list = visibleTiles();
        const showGrid = state.view === 'grid';

        grid.hidden = !showGrid || list.length === 0;
        timeline.hidden = showGrid;
        empty.hidden = !showGrid || list.length > 0;

        if (showGrid) {
            grid.innerHTML = list.map(cardHtml).join('');
        } else {
            timeline.innerHTML = timelineHtml();
        }
    }

    /** Ridisegna la lista; dopo il primo disegno le card si spostano invece di sparire e ricomparire. */
    function render(animate) {
        const canTransition = animate && !state.firstRender && !reduceMotion
            && document.visibilityState === 'visible'
            && typeof document.startViewTransition === 'function';
        if (!canTransition) {
            paintList();
        } else {
            // Un secondo filtro a ruota interrompe lo spostamento in corso:
            // la lista è già quella giusta, l'errore non interessa a nessuno.
            const transition = document.startViewTransition(paintList);
            [transition.ready, transition.finished, transition.updateCallbackDone].forEach((promise) => {
                if (promise && typeof promise.catch === 'function') promise.catch(() => {});
            });
        }
        state.firstRender = false;
    }

    function paintNear() {
        const section = $('#achNear');
        const html = nearHtml();
        section.hidden = html === '';
        $('#achNearList').innerHTML = html;
    }

    function countUp(element, target) {
        if (!element) return;
        // A scheda nascosta requestAnimationFrame non gira: il numero
        // resterebbe quello vecchio finché non si torna a guardarla.
        element.dataset.target = String(target);
        if (reduceMotion || document.visibilityState !== 'visible') {
            element.textContent = number(target);
            return;
        }
        const from = Number(String(element.textContent).replace(/[^\d]/g, '')) || 0;
        if (from === target) {
            element.textContent = number(target);
            return;
        }
        // Un conto più recente sullo stesso numero ferma quello vecchio.
        const current = () => element.dataset.target === String(target);
        // Qualunque cosa succeda all'animazione, il valore giusto arriva.
        setTimeout(() => {
            if (current()) element.textContent = number(target);
        }, 900);
        const start = performance.now();
        const tick = (now) => {
            if (!current()) return;
            // L'orario del fotogramma può precedere di un soffio quello di partenza.
            const t = Math.max(0, Math.min(1, (now - start) / 700));
            const eased = 1 - Math.pow(1 - t, 3);
            element.textContent = number(Math.round(from + (target - from) * eased));
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }

    function paintSummary() {
        const summary = data.summary;
        const percent = summary.total > 0 ? Math.round(summary.unlocked / summary.total * 100) : 0;

        countUp($('#achPercent'), percent);
        $('#achRingFill')?.style.setProperty('--p', String(percent));
        $('#achCount').textContent = T.of(number(summary.unlocked), number(summary.total));
        countUp($('#achStatUnlocked'), summary.unlocked);
        countUp($('#achStatPoints'), summary.points);
        countUp($('#achStatGodos'), summary.claimable_godos);

        const claimStat = $('#achClaimStat');
        if (claimStat) {
            claimStat.classList.toggle('has-claim', summary.claimable > 0);
            $('#achClaimAll').hidden = summary.claimable <= 0;
        }

        const rank = summary.rank;
        $('#achRankName').textContent = rank.name;
        $('#achRankFill')?.style.setProperty('--p', String(rank.progress));
        $('#achRankNext').textContent = rank.next ? T.rankNext(number(rank.next.min - summary.points), rank.next.name) : T.rankMax;

        // Conteggi sulle linguette.
        $$('#achTabs [data-category]').forEach((tab) => {
            const small = $('small', tab);
            if (!small) return;
            if (tab.dataset.category === 'all') {
                small.textContent = `${summary.unlocked}/${summary.total}`;
            } else {
                const category = (data.categories || []).find((entry) => entry.key === tab.dataset.category);
                if (category) small.textContent = `${category.unlocked}/${category.total}`;
            }
        });
    }

    function paintControls() {
        $$('#achTabs [data-category]').forEach((tab) => {
            const active = tab.dataset.category === state.category;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        $$('[data-status]').forEach((chip) => {
            const active = chip.dataset.status === state.status;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        $$('[data-view]').forEach((button) => {
            const active = button.dataset.view === state.view;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        $$('#achSortMenu [role="option"]').forEach((option) => {
            const selected = option.dataset.value === state.sort;
            option.setAttribute('aria-selected', selected ? 'true' : 'false');
            if (selected) $('[data-sort-current]').textContent = $('span', option).textContent;
        });
        $('.ach-controls')?.classList.toggle('is-timeline', state.view !== 'grid');
    }

    /**
     * Menu «Ordina»: un listbox fatto a mano, niente <select> del browser.
     * Frecce, Home/Fine, Invio e Esc; il focus resta sul menu e la voce
     * evidenziata si dichiara con aria-activedescendant.
     */
    function bindSort() {
        const box = $('[data-ach-sort]');
        if (!box) return;

        const button = $('.ach-sort__button', box);
        const menu = $('.ach-sort__menu', box);
        const options = $$('[role="option"]', menu);
        let active = 0;

        const highlight = (index) => {
            active = (index + options.length) % options.length;
            options.forEach((option, i) => option.classList.toggle('is-active', i === active));
            menu.setAttribute('aria-activedescendant', options[active].id);
        };

        const isOpen = () => box.classList.contains('is-open');

        const open = () => {
            if (isOpen()) return;
            box.classList.add('is-open');
            button.setAttribute('aria-expanded', 'true');
            highlight(Math.max(0, options.findIndex((option) => option.dataset.value === state.sort)));
            menu.focus({ preventScroll: true });
        };

        const close = (focusButton = true) => {
            if (!isOpen()) return;
            box.classList.remove('is-open');
            button.setAttribute('aria-expanded', 'false');
            menu.removeAttribute('aria-activedescendant');
            if (focusButton) button.focus({ preventScroll: true });
        };

        const choose = (index) => {
            const value = options[index] && options[index].dataset.value;
            if (value && value !== state.sort) {
                state.sort = value;
                store.set('sort', value);
                paintControls();
                render(true);
            }
            close();
        };

        button.addEventListener('click', () => (isOpen() ? close() : open()));
        button.addEventListener('keydown', (event) => {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
                open();
            }
        });

        menu.addEventListener('keydown', (event) => {
            switch (event.key) {
                case 'ArrowDown': event.preventDefault(); highlight(active + 1); break;
                case 'ArrowUp': event.preventDefault(); highlight(active - 1); break;
                case 'Home': event.preventDefault(); highlight(0); break;
                case 'End': event.preventDefault(); highlight(options.length - 1); break;
                case 'Enter':
                case ' ': event.preventDefault(); choose(active); break;
                case 'Escape': event.preventDefault(); event.stopPropagation(); close(); break;
                case 'Tab': close(false); break;
                default: break;
            }
        });

        options.forEach((option, index) => {
            option.addEventListener('mousemove', () => {
                if (active !== index) highlight(index);
            });
            option.addEventListener('click', () => choose(index));
        });

        // Un clic fuori, o il focus che se ne va, chiude il menu.
        document.addEventListener('pointerdown', (event) => {
            if (isOpen() && !box.contains(event.target)) close(false);
        });
        menu.addEventListener('focusout', (event) => {
            if (isOpen() && !box.contains(event.relatedTarget)) close(false);
        });
    }

    // ── Avvisi ─────────────────────────────────────────────────────────────

    let toastTimer = null;
    function toast(message, kind) {
        const element = $('#achToast');
        if (!element) return;
        element.textContent = message;
        element.className = 'ach-toast is-visible' + (kind ? ' ach-toast--' + kind : '');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => element.classList.remove('is-visible'), 2800);
    }

    /** Qualche coriandolo dalla medaglia dell'anello: una volta, e solo se c'è qualcosa di nuovo. */
    function celebrate() {
        if (reduceMotion) return;
        const origin = $('.ach-ring');
        if (!origin) return;

        const burst = document.createElement('div');
        burst.className = 'ach-burst';
        burst.setAttribute('aria-hidden', 'true');
        const colors = ['#5b8cff', '#a78bfa', '#f4c343', '#34d399', '#ff7bd5', '#86d6ea'];
        for (let i = 0; i < 26; i += 1) {
            const piece = document.createElement('i');
            const angle = (Math.PI * 2 * i) / 26 + Math.random() * 0.4;
            const distance = 70 + Math.random() * 90;
            piece.style.setProperty('--x', Math.cos(angle) * distance + 'px');
            piece.style.setProperty('--y', Math.sin(angle) * distance - 30 + 'px');
            piece.style.setProperty('--r', Math.round(Math.random() * 540 - 270) + 'deg');
            piece.style.setProperty('--d', Math.round(Math.random() * 160) + 'ms');
            piece.style.background = colors[i % colors.length];
            burst.appendChild(piece);
        }
        origin.appendChild(burst);
        setTimeout(() => burst.remove(), 1700);
    }

    function floatGain(anchor, text) {
        if (reduceMotion || !anchor) return;
        const rect = anchor.getBoundingClientRect();
        const bubble = document.createElement('span');
        bubble.className = 'ach-float';
        bubble.textContent = text;
        bubble.style.position = 'fixed';
        bubble.style.left = rect.left + rect.width / 2 + 'px';
        bubble.style.top = rect.top + 'px';
        document.body.appendChild(bubble);
        setTimeout(() => bubble.remove(), 1100);
    }

    // ── Dettaglio ──────────────────────────────────────────────────────────

    let lastFocus = null;

    function openDetail(id, fromHash) {
        const item = byId.get(Number(id));
        if (!item || !dialog) return;

        state.openId = item.id;
        dialogBody.innerHTML = detailHtml(item);

        if (!dialog.open) {
            lastFocus = document.activeElement;
            dialog.classList.remove('is-closing');
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
            }
        }

        if (!fromHash && !item.hidden) {
            history.replaceState(null, '', '#achievement-' + item.id);
        }
    }

    function closeDetail() {
        if (!dialog || !dialog.open) return;

        const finish = () => {
            dialog.classList.remove('is-closing');
            if (typeof dialog.close === 'function') dialog.close();
            else dialog.removeAttribute('open');
        };

        state.openId = 0;
        if (location.hash.startsWith('#achievement-')) {
            history.replaceState(null, '', location.pathname + location.search);
        }
        if (reduceMotion) {
            finish();
        } else {
            dialog.classList.add('is-closing');
            setTimeout(finish, 190);
        }
        if (lastFocus && typeof lastFocus.focus === 'function' && document.contains(lastFocus)) {
            lastFocus.focus({ preventScroll: true });
        }
    }

    function openFromHash() {
        const match = /^#achievement-(\d+)$/.exec(location.hash || '');
        if (match) openDetail(Number(match[1]), true);
    }

    async function copyLink(id) {
        const url = `${location.origin}${location.pathname}#achievement-${id}`;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(url);
            } else {
                const area = document.createElement('textarea');
                area.value = url;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.left = '-9999px';
                (dialog.open ? dialog : document.body).appendChild(area);
                area.select();
                const ok = document.execCommand('copy');
                area.remove();
                if (!ok) throw new Error('copy');
            }
            toast(T.copied);
        } catch (_) {
            toast(T.copyFailed, 'error');
        }
    }

    // ── Server ─────────────────────────────────────────────────────────────

    function applyData(fresh) {
        if (!fresh || !Array.isArray(fresh.items)) return;
        // Quello che era «nuovo» in questa visita lo resta fino alla prossima.
        const wasNew = new Set(items.filter((item) => item.isNew).map((item) => item.id));
        const had = new Set(items.filter((item) => item.unlocked).map((item) => item.id));

        data = fresh;
        buildModel();
        items.forEach((item) => {
            if (wasNew.has(item.id) || (item.unlocked && !had.has(item.id))) item.isNew = true;
        });

        paintSummary();
        paintNear();
        render(true);
        if (state.openId && dialog.open) openDetail(state.openId, true);

        // Quello che si sblocca mentre questa pagina è aperta lo si è già
        // visto: senza questo il pallino della navbar si accenderebbe alla
        // pagina dopo (la chiave è quella di navbar.js).
        const latest = Math.max(0, ...items.map((item) => Number(item.unlocked_ts) || 0));
        if (latest > 0) {
            try {
                localStorage.setItem('cnav.achvSeen.' + userId, String(latest));
            } catch (_) {
                /* senza memoria il pallino resta alla navbar */
            }
        }
    }

    let refreshTimer = null;
    function refresh() {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(async () => {
            try {
                const response = await fetch('/api/achievements/overview?lang=' + lang, { credentials: 'same-origin', cache: 'no-store' });
                if (!response.ok) return;
                applyData(await response.json());
            } catch (_) {
                /* la pagina resta com'è: si riallinea alla prossima apertura */
            }
        }, 500);
    }

    let claiming = false;
    async function claim(id, button) {
        if (claiming) return;
        claiming = true;

        const all = id === 'all';
        const label = button ? $('span', button) : null;
        const original = label ? label.textContent : '';
        if (button) {
            button.disabled = true;
            button.classList.add('is-busy');
            if (label) label.textContent = T.claiming;
        }

        try {
            const response = await fetch('/api/achievements/claim', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify(all ? { all: true } : { achievement_id: Number(id) })
            });
            const result = await response.json().catch(() => null);

            if (!result || !result.ok) {
                toast(result && result.code === 'NOTHING_TO_CLAIM' ? T.claimNothing : T.claimError, 'error');
                if (result && result.code === 'NOTHING_TO_CLAIM') refresh();
                return;
            }

            floatGain(button, '+' + number(result.godos));
            toast(T.claimedToast(number(result.godos)), 'gold');

            // Lo stato si aggiorna subito con quello che ha detto il server;
            // il riallineamento completo arriva un attimo dopo.
            const claimed = new Set(result.claimed || []);
            data.items.forEach((item) => {
                if (claimed.has(item.id)) {
                    item.claimable = false;
                    item.claimed = true;
                }
            });
            data.summary.claimable = Math.max(0, data.summary.claimable - claimed.size);
            data.summary.claimable_godos = Math.max(0, data.summary.claimable_godos - (Number(result.godos) || 0));
            applyData(data);
        } catch (_) {
            toast(T.claimError, 'error');
        } finally {
            claiming = false;
            if (button && document.contains(button)) {
                button.disabled = false;
                button.classList.remove('is-busy');
                if (label) label.textContent = original;
            }
        }
    }

    // ── Eventi ─────────────────────────────────────────────────────────────

    function bind() {
        document.addEventListener('click', (event) => {
            const claimTarget = event.target.closest('[data-claim]');
            if (claimTarget) {
                event.preventDefault();
                claim(claimTarget.dataset.claim, claimTarget);
                return;
            }

            const copyTarget = event.target.closest('[data-copy]');
            if (copyTarget) {
                copyLink(copyTarget.dataset.copy);
                return;
            }

            const openTarget = event.target.closest('[data-open]');
            if (openTarget) {
                openDetail(openTarget.dataset.open);
                return;
            }

            if (event.target.closest('[data-ach-close]')) {
                closeDetail();
                return;
            }

            const card = event.target.closest('.ach-card');
            if (card && grid.contains(card)) {
                openDetail(card.dataset.id);
            }
        });

        $('#achClaimAll')?.addEventListener('click', (event) => claim('all', event.currentTarget));

        // Clic sullo sfondo e tasto Esc chiudono il dettaglio.
        dialog?.addEventListener('click', (event) => {
            if (event.target === dialog) closeDetail();
        });
        dialog?.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeDetail();
        });

        $('#achTabs')?.addEventListener('click', (event) => {
            const tab = event.target.closest('[data-category]');
            if (!tab || tab.dataset.category === state.category) return;
            state.category = tab.dataset.category;
            paintControls();
            render(true);
        });

        // Frecce fra le linguette, come vuole il ruolo «tablist».
        $('#achTabs')?.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
            const tabs = $$('#achTabs [data-category]');
            const index = tabs.indexOf(document.activeElement);
            if (index === -1) return;
            event.preventDefault();
            const next = tabs[(index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length];
            next.focus();
            next.click();
        });

        $$('[data-status]').forEach((chip) => chip.addEventListener('click', () => {
            state.status = chip.dataset.status;
            store.set('status', state.status);
            paintControls();
            render(true);
        }));

        $$('[data-view]').forEach((button) => button.addEventListener('click', () => {
            state.view = button.dataset.view;
            store.set('view', state.view);
            paintControls();
            render(false);
        }));

        bindSort();

        let searchTimer = null;
        $('#achSearch')?.addEventListener('input', (event) => {
            state.search = event.target.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => render(true), 140);
        });

        window.addEventListener('hashchange', openFromHash);

        // Uno sblocco arrivato mentre la pagina è aperta (dal tempo reale o
        // da una richiesta di questa pagina): si rileggono i dati.
        document.addEventListener('cripsum:achievement', refresh);

        bindKonami();
    }

    // ── Codice segreto ─────────────────────────────────────────────────────
    //
    // ↑ ↑ ↓ ↓ ← → ← → B A. Da tastiera, oppure col dito sull'anello della
    // testata (quattro direzioni e due tocchi). Dal secondo tasto giusto
    // compare in basso una fila di tasti che si accendono uno alla volta:
    // chi lo sta facendo vede che la pagina se n'è accorta, senza che la
    // fila sveli quelli che mancano.

    const KONAMI = ['U', 'U', 'D', 'D', 'L', 'R', 'L', 'R', 'B', 'A'];
    const KONAMI_KEYS = { ArrowUp: 'U', ArrowDown: 'D', ArrowLeft: 'L', ArrowRight: 'R', b: 'B', a: 'A' };
    const KONAMI_GLYPH = {
        U: '<i class="fa-solid fa-arrow-up" aria-hidden="true"></i>',
        D: '<i class="fa-solid fa-arrow-down" aria-hidden="true"></i>',
        L: '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i>',
        R: '<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>',
        B: 'B',
        A: 'A'
    };
    const KONAMI_NOTES = [392, 440, 494, 523, 587, 659, 698, 784, 880, 988];

    const konami = { index: 0, hud: null, timer: null, busy: false, audio: null };

    /** Una nota breve: sale a ogni tasto giusto. Rispetta l'interruttore «Suono» degli avvisi. */
    function konamiTone(frequency, duration, delay) {
        try {
            const rt = window.CripsumRT;
            if (rt && rt.prefs && rt.prefs.sound === false) return;
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            konami.audio = konami.audio || new AudioContext();
            const context = konami.audio;
            const start = context.currentTime + (delay || 0);
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'triangle';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.07, start + 0.015);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
            oscillator.connect(gain).connect(context.destination);
            oscillator.start(start);
            oscillator.stop(start + duration + 0.02);
        } catch (_) {
            /* niente audio: il resto funziona lo stesso */
        }
    }

    function konamiHud() {
        if (konami.hud && document.contains(konami.hud)) return konami.hud;
        const hud = document.createElement('div');
        hud.className = 'ach-konami';
        hud.setAttribute('aria-hidden', 'true');
        hud.style.position = 'fixed';
        hud.innerHTML = KONAMI.map(() => '<span class="ach-konami__key"></span>').join('');
        document.body.appendChild(hud);
        konami.hud = hud;
        return hud;
    }

    function konamiPaint() {
        const hud = konamiHud();
        $$('.ach-konami__key', hud).forEach((key, i) => {
            const on = i < konami.index;
            if (on && !key.classList.contains('is-on')) {
                key.innerHTML = KONAMI_GLYPH[KONAMI[i]];
                key.classList.add('is-on');
            } else if (!on) {
                key.classList.remove('is-on');
                key.innerHTML = '';
            }
        });
        hud.classList.remove('is-wrong', 'is-done');
        hud.classList.add('is-visible');
    }

    function konamiHide(delay) {
        clearTimeout(konami.timer);
        konami.timer = setTimeout(() => {
            konami.index = 0;
            if (konami.hud) konami.hud.classList.remove('is-visible', 'is-wrong', 'is-done');
        }, delay);
    }

    /** Il gran finale: lampo, coriandoli dall'alto, le card che fanno l'onda. */
    function konamiParty() {
        [523, 659, 784, 1047].forEach((note, i) => konamiTone(note, 0.22, i * 0.09));
        if (reduceMotion) return;

        const layer = document.createElement('div');
        layer.className = 'ach-konami-fx';
        layer.setAttribute('aria-hidden', 'true');
        layer.style.position = 'fixed';
        const colors = ['#5b8cff', '#a78bfa', '#f4c343', '#34d399', '#ff7bd5', '#86d6ea'];
        let pieces = '<span class="ach-konami-fx__flash"></span>';
        for (let i = 0; i < 70; i += 1) {
            pieces += `<i style="left:${(Math.random() * 100).toFixed(2)}%;background:${colors[i % colors.length]};`
                + `--d:${Math.round(Math.random() * 700)}ms;--t:${1500 + Math.round(Math.random() * 1300)}ms;`
                + `--x:${Math.round(Math.random() * 160 - 80)}px;--r:${Math.round(Math.random() * 900 - 450)}deg"></i>`;
        }
        layer.innerHTML = pieces;
        document.body.appendChild(layer);
        setTimeout(() => layer.remove(), 3400);

        // L'onda parte dalle card che si vedono, una dopo l'altra.
        $$('.ach-card', grid).filter((card) => {
            const rect = card.getBoundingClientRect();
            return rect.bottom > 0 && rect.top < window.innerHeight;
        }).forEach((card, i) => {
            card.style.setProperty('--k', String(i));
            card.classList.remove('is-enter');
            card.classList.add('is-wave');
            setTimeout(() => card.classList.remove('is-wave'), 1500 + i * 45);
        });

        const ring = $('.ach-ring');
        if (ring) {
            ring.classList.add('is-spin');
            setTimeout(() => ring.classList.remove('is-spin'), 1300);
        }
    }

    function konamiFeed(symbol) {
        if (konami.busy) return false;
        const expected = KONAMI[konami.index];

        if (symbol !== expected) {
            const wasVisible = konami.index >= 2;
            // ↑ ↑ ↑ resta a due: gli ultimi due tasti sono ancora quelli giusti.
            konami.index = symbol === 'U' ? Math.min(konami.index === 2 ? 2 : 1, 2) : 0;
            if (wasVisible && konami.index < 2) {
                konami.hud.classList.add('is-wrong');
                konamiTone(140, 0.18);
                konamiHide(520);
            } else if (konami.index >= 2) {
                konamiHide(4000);
            }
            return false;
        }

        konami.index += 1;
        if (konami.index >= 2) {
            konamiPaint();
            konamiTone(KONAMI_NOTES[konami.index - 1], 0.11);
            konamiHide(4000);
        }

        if (konami.index === KONAMI.length) {
            konami.busy = true;
            clearTimeout(konami.timer);
            konami.hud.classList.add('is-done');
            konamiParty();

            const id = Number(data.extras && data.extras.konami_id) || 0;
            if (id && typeof window.unlockAchievement === 'function') {
                window.unlockAchievement(id);
            } else {
                toast(T.konamiAgain, 'gold');
            }

            setTimeout(() => {
                konami.busy = false;
                konamiHide(0);
            }, 2200);
        }

        return true;
    }

    function bindKonami() {
        // In cattura: quando il codice è in corso frecce e lettere non devono
        // far scorrere la pagina né cambiare linguetta.
        document.addEventListener('keydown', (event) => {
            if (event.repeat || event.ctrlKey || event.metaKey || event.altKey) return;
            const target = event.target;
            if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) return;
            if ((dialog && dialog.open) || (target instanceof Element && target.closest('[data-ach-sort]'))) return;

            const symbol = KONAMI_KEYS[event.key.length === 1 ? event.key.toLowerCase() : event.key];
            if (!symbol) {
                if (konami.index >= 2 && !['Shift', 'Control', 'Alt', 'Meta', 'Tab'].includes(event.key)) konamiFeed('?');
                return;
            }

            const inProgress = konami.index >= 2;
            const matched = konamiFeed(symbol);
            if (matched && inProgress) {
                event.preventDefault();
                event.stopPropagation();
            }
        }, true);

        // Col dito: sull'anello della testata, dove uno scorrimento non
        // trascina la pagina. Quattro direzioni, poi due tocchi per B e A.
        const pad = $('.ach-ring');
        if (!pad) return;
        let start = null;

        pad.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse') return;
            start = { x: event.clientX, y: event.clientY };
        });
        pad.addEventListener('pointerup', (event) => {
            if (!start || event.pointerType === 'mouse') return;
            const dx = event.clientX - start.x;
            const dy = event.clientY - start.y;
            start = null;

            if (Math.max(Math.abs(dx), Math.abs(dy)) < 24) {
                // Un tocco vale il tasto che manca, se è B o A.
                const expected = KONAMI[konami.index];
                konamiFeed(expected === 'B' || expected === 'A' ? expected : '?');
                return;
            }
            konamiFeed(Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'R' : 'L') : (dy > 0 ? 'D' : 'U'));
        });
        pad.addEventListener('pointercancel', () => {
            start = null;
        });
    }

    function init() {
        // Un filtro salvato che non esiste più (o «da riscuotere» senza premi attivi).
        if (!$(`[data-status="${state.status}"]`)) state.status = 'all';
        if (!['default', 'closest', 'rarest', 'recent', 'points', 'name'].includes(state.sort)) state.sort = 'default';
        if (!['grid', 'timeline'].includes(state.view)) state.view = 'grid';

        // Durante gli spostamenti delle card il resto della pagina resta fermo.
        document.documentElement.style.viewTransitionName = 'none';

        buildModel();
        paintControls();
        paintNear();
        render(false);
        bind();
        openFromHash();

        // La percentuale sale insieme all'anello.
        const percent = $('#achPercent');
        if (percent && !reduceMotion && document.visibilityState === 'visible') {
            const target = Number(percent.dataset.value) || 0;
            percent.textContent = '0';
            countUp(percent, target);
        }

        const fresh = items.filter((item) => item.isNew).length;
        if (fresh > 0) {
            setTimeout(() => {
                toast(T.freshToast(fresh), 'gold');
                celebrate();
            }, 650);
        }

        document.body.classList.add('ach-ready');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
