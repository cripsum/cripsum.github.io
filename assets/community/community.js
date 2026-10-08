/**
 * Cripsum™ — Shitpost e Top Rimasti.
 *
 * La pagina arriva con la prima manciata di post già dentro (#cmData); da lì
 * in avanti questo script carica scorrendo e tiene in pari le tre viste dello
 * stesso post: la card nel feed (o la riga in classifica), il post aperto e
 * l'elenco in memoria.
 *
 *   - Shitpost: colonne riempite una card alla volta, sempre nella più corta,
 *     così l'ordine resta leggibile e aggiungere post non sposta gli altri.
 *   - Top Rimasti: podio e righe; dopo un voto la classifica si riordina.
 *   - Post aperto: ?post=ID nell'indirizzo, quindi i link funzionano e
 *     «indietro» chiude. Due navigazioni separate: le frecce sul media (← →)
 *     scorrono i file del post, «Precedente» e «Successivo» (↑ ↓) cambiano
 *     post. Le immagini si vedono sempre intere; zoom con clic, rotella o
 *     pizzico. I video usano il player degli edit (edits-player.js).
 *   - Nuovo post: i file si caricano uno alla volta appena scelti, ognuno
 *     con il suo avanzamento; il post li aggancia quando si pubblica.
 *
 * PreMiD legge window.__presencePost (titolo e immagine del post aperto).
 */
(() => {
    'use strict';

    const dataEl = document.getElementById('cmData');
    if (!dataEl) return;

    let D;
    try {
        D = JSON.parse(dataEl.textContent || '{}');
    } catch (error) {
        return;
    }

    const S = D.strings || {};
    const lang = D.lang === 'en' ? 'en' : 'it';
    const type = D.type === 'rimasto' ? 'rimasto' : 'shitpost';
    const isRimasto = type === 'rimasto';
    const me = D.user || null;
    const isAdmin = !!(me && me.admin);
    const REACTIONS = D.reactions || { fire: '🔥' };
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const hasPopover = typeof HTMLElement !== 'undefined' && 'showPopover' in HTMLElement.prototype;

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    }[char]));

    /** «%d nuovi post» → «3 nuovi post». */
    const fmt = (text, ...values) => {
        let index = 0;
        return String(text || '').replace(/%%|%[ds]/g, (token) => (token === '%%' ? '%' : String(values[index++] ?? '')));
    };

    const compact = new Intl.NumberFormat(lang, { notation: 'compact', maximumFractionDigits: 1 });
    const num = (value) => compact.format(Number(value) || 0);
    const relative = new Intl.RelativeTimeFormat(lang, { numeric: 'auto', style: 'short' });
    const relativeDays = new Intl.RelativeTimeFormat(lang, { numeric: 'auto', style: 'long' });

    // L'orologio del visitatore può essere sbagliato: i «3 minuti fa» si
    // contano da quello del server.
    const skew = (Number(D.now) || Date.now() / 1000) - Date.now() / 1000;
    const serverNow = () => Date.now() / 1000 + skew;

    const ago = (ts) => {
        const seconds = Math.max(0, serverNow() - Number(ts || 0));
        if (seconds < 45) return S.now;
        if (seconds < 3600) return relative.format(-Math.max(1, Math.round(seconds / 60)), 'minute');
        if (seconds < 86400) return relative.format(-Math.round(seconds / 3600), 'hour');
        if (seconds < 30 * 86400) return relativeDays.format(-Math.round(seconds / 86400), 'day');
        // Mese per esteso: «1 ago» in italiano sembra inglese.
        const date = new Date(Number(ts) * 1000);
        const sameYear = date.getFullYear() === new Date().getFullYear();
        return date.toLocaleDateString(lang, sameYear ? { day: 'numeric', month: 'long' } : { day: 'numeric', month: 'short', year: 'numeric' });
    };

    const clock = (seconds) => {
        seconds = Math.max(0, Math.round(Number(seconds) || 0));
        return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
    };

    const bytes = (value) => {
        value = Number(value) || 0;
        return value >= 1048576 ? `${(value / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(value / 1024))} KB`;
    };

    // ── Elementi ────────────────────────────────────────────────────────────
    const app = $('#cmApp');
    const feedEl = $('[data-cm-feed]');
    const podiumEl = $('[data-cm-podium]');
    const rankEl = $('[data-cm-rank]');
    const emptyEl = $('[data-cm-empty]');
    const tagsEl = $('[data-cm-tags]');
    const hallEl = $('[data-cm-hall]');
    const sentinel = $('[data-cm-sentinel]');
    const newPill = $('[data-cm-newpill]');
    const searchInput = $('[data-cm-search]');
    const viewerEl = $('[data-cm-viewer]');
    const editorEl = $('[data-cm-editor]');
    const reportEl = $('[data-cm-report]');
    const confirmEl = $('[data-cm-confirm]');
    const menuEl = $('[data-cm-menu]');
    const pickerEl = $('[data-cm-picker]');
    const toastEl = $('[data-cm-toast]');

    if (!app || !feedEl) return;

    const state = {
        sort: D.sort || (isRimasto ? 'top' : 'recent'),
        period: D.period || 'all',
        tag: D.tag || '',
        q: D.q || '',
        filter: '',
        page: 1,
        pages: Number(D.feed?.pages) || 1,
        posts: [],
        byId: new Map(),
        loading: false,
        token: 0,
        failed: false,
        tags: Array.isArray(D.tags) ? D.tags : [],
        hall: Array.isArray(D.hall) ? D.hall : [],
        revealed: new Set(),
        viewed: new Set(),
        seenAt: Number(D.now) || 0,
        pendingMine: Number(D.pendingMine) || 0,
    };

    const defaultSort = isRimasto ? 'top' : 'recent';
    const isRanked = () => isRimasto && state.sort === 'top' && !state.filter && !state.q && !state.tag;

    // ── Avvisi ──────────────────────────────────────────────────────────────
    const openDialogs = [];
    const topDialog = () => openDialogs[openDialogs.length - 1] || null;
    /** Dentro una finestra modale tutto il resto della pagina è inerte: menu e simili vanno messi lì. */
    const host = () => topDialog() || document.body;

    const pop = {
        open(el) {
            if (!el) return;
            if (hasPopover) {
                try { if (el.matches(':popover-open')) el.hidePopover(); } catch (error) { /* non era aperto */ }
                try { el.showPopover(); } catch (error) { /* già aperto */ }
            } else {
                el.removeAttribute('popover');
                el.hidden = false;
                el.style.display = el === pickerEl ? 'flex' : 'block';
                el.style.zIndex = '2147483000';
            }
        },
        close(el) {
            if (!el) return;
            if (hasPopover) {
                try { el.hidePopover(); } catch (error) { /* già chiuso */ }
            } else {
                el.style.display = 'none';
            }
        },
        isOpen(el) {
            if (!el) return false;
            if (hasPopover) {
                try { return el.matches(':popover-open'); } catch (error) { return false; }
            }
            return el.style.display !== 'none' && el.style.display !== '';
        },
    };

    let toastTimer = 0;
    const toast = (message, isError = false) => {
        if (!toastEl || !message) return;
        clearTimeout(toastTimer);
        toastEl.textContent = message;
        toastEl.classList.toggle('is-error', isError);
        host().appendChild(toastEl);
        pop.open(toastEl);
        toastTimer = setTimeout(() => pop.close(toastEl), isError ? 4200 : 2600);
    };

    // ── Rete ────────────────────────────────────────────────────────────────
    const api = async (endpoint, { method = 'GET', body = null, form = null } = {}) => {
        const headers = { 'X-CSRF-Token': D.csrf || '', 'X-Requested-With': 'fetch', 'X-Cripsum-Lang': lang };
        let payload = form;
        if (!form && body) {
            headers['Content-Type'] = 'application/json';
            payload = JSON.stringify(body);
        }

        let response;
        try {
            response = await fetch(`/api/content/${endpoint}`, { method, headers, body: payload, cache: 'no-store', credentials: 'same-origin' });
        } catch (error) {
            throw new Error(S.network_error);
        }

        let data = null;
        try {
            data = await response.json();
        } catch (error) {
            data = null;
        }

        if (!response.ok || !data || data.ok === false) {
            const failure = new Error((data && data.message) || S.network_error);
            failure.status = response.status;
            throw failure;
        }
        return data;
    };

    const requireLogin = () => {
        if (me) return true;
        toast(S.must_login, true);
        setTimeout(() => { window.location.href = D.login; }, 900);
        return false;
    };

    const copyText = async (text) => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return true;
            }
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.cssText = 'position:fixed;left:-9999px';
            host().appendChild(area);
            area.select();
            const ok = document.execCommand('copy');
            area.remove();
            return ok;
        } catch (error) {
            return false;
        }
    };

    // ── Pezzi di HTML ───────────────────────────────────────────────────────
    const GEM = '<img class="cr-premium-gem" src="/img/premium.svg" alt="Premium" title="Premium">';
    const NO_IMAGE = '<span class="cm-noimg"><i class="fa-solid fa-image" aria-hidden="true"></i></span>';

    const titleOf = (post) => post.title || S.untitled;
    const nameOf = (author) => (author && author.username) || S.deleted_user;
    const profileOf = (author) => (author && author.username ? `/u/${encodeURIComponent(author.username)}` : '#');
    const absolute = (post) => `${location.origin}${post.url}`;
    const roleBadge = (author) => (author && author.role && author.role !== 'utente' ? `<span class="cm-role">${esc(author.role)}</span>` : '');

    const ratioOf = (media) => {
        const ratio = media && media.w && media.h ? media.w / media.h : 1;
        return Math.min(2.2, Math.max(0.56, ratio)).toFixed(4);
    };

    /** Il primo media di un post, come sta in una card. */
    const thumbHtml = (post) => {
        const media = post.media && post.media[0];
        if (!media) return NO_IMAGE;

        if (media.kind === 'video') {
            const picture = media.thumb
                ? `<img src="${esc(media.thumb)}" alt="" loading="lazy" decoding="async">`
                : `<video src="${esc(media.url)}#t=0.1" preload="metadata" muted playsinline tabindex="-1"></video>`;
            return `${picture}<span class="cm-badge"><i class="fa-solid fa-video" aria-hidden="true"></i>${media.duration ? ` ${clock(media.duration)}` : ''}</span><span class="cm-play"><i class="fa-solid fa-play" aria-hidden="true"></i></span>`;
        }

        const gif = media.kind === 'gif';
        return `<img class="godomedia" src="${esc(media.thumb || media.url)}" alt="" loading="lazy" decoding="async"${gif ? ` data-gif="${esc(media.url)}" data-still="${esc(media.thumb || media.url)}"` : ''}${media.w ? '' : ' data-measure="1"'}>${gif ? '<span class="cm-badge">GIF</span>' : ''}`;
    };

    const byHtml = (post, withTag = false) => {
        const tag = withTag && post.tags && post.tags[0];
        return `
            <div class="cm-by">
                <a class="cm-by__user" href="${esc(profileOf(post.author))}">
                    <img class="cm-avatar" src="/includes/get_pfp.php?id=${Number(post.author.id)}" alt="" loading="lazy">
                    <span>${esc(nameOf(post.author))}</span>${post.author.premium ? GEM : ''}
                </a>
                ${roleBadge(post.author)}
                <span class="cm-by__time">· ${esc(ago(post.ts))}</span>
                ${tag ? `<button type="button" class="cm-tag cm-tag--small" data-act="tag" data-tag="${esc(tag)}">#${esc(tag)}</button>` : ''}
            </div>`;
    };

    /** Le emoji più usate sul post, con la propria per prima. */
    const reactionStack = (post) => {
        const entries = Object.entries(post.reactions || {}).filter(([, count]) => count > 0).sort((a, b) => b[1] - a[1]);
        let keys = entries.map(([key]) => key);
        if (post.my_reaction) keys = [post.my_reaction, ...keys.filter((key) => key !== post.my_reaction)];
        if (!keys.length) keys = ['fire'];
        return keys.slice(0, 3).map((key) => `<span class="cm-emo">${REACTIONS[key] || REACTIONS.fire}</span>`).join('');
    };

    const actsHtml = (post) => {
        if (!post.approved) {
            return `
                <div class="cm-acts">
                    ${post.can_manage ? `<button type="button" class="cm-act" data-act="edit"><i class="fa-solid fa-pen" aria-hidden="true"></i> ${esc(S.edit)}</button>` : ''}
                    ${isAdmin ? `<button type="button" class="cm-act" data-act="approve"><i class="fa-solid fa-check" aria-hidden="true"></i> ${esc(S.approve)}</button>` : ''}
                    <span class="cm-acts__gap"></span>
                    ${post.can_manage ? `<button type="button" class="cm-act cm-act--danger" data-act="delete"><i class="fa-solid fa-trash" aria-hidden="true"></i> ${esc(S.delete)}</button>` : ''}
                </div>`;
        }

        const main = isRimasto
            ? `<button type="button" class="cm-act${post.voted ? ' is-on' : ''}" data-act="vote" aria-pressed="${post.voted ? 'true' : 'false'}" aria-label="${esc(post.voted ? S.voted : S.vote)}"><i class="fa-solid ${post.voted ? 'fa-check' : 'fa-chevron-up'}" aria-hidden="true"></i><span class="cm-num">${num(post.score)}</span></button>`
            : `<button type="button" class="cm-act${post.my_reaction ? ' is-on' : ''}" data-act="react" aria-pressed="${post.my_reaction ? 'true' : 'false'}" aria-label="${esc(S.react)}">${reactionStack(post)}<span class="cm-num">${num(post.score)}</span></button>`;

        return `
            <div class="cm-acts">
                ${main}
                <button type="button" class="cm-act" data-act="comments" aria-label="${esc(S.comments)}"><i class="fa-regular fa-comment" aria-hidden="true"></i><span class="cm-num">${num(post.comments)}</span></button>
                <span class="cm-acts__gap"></span>
                <button type="button" class="cm-act${post.saved ? ' is-saved' : ''}" data-act="save" aria-pressed="${post.saved ? 'true' : 'false'}" aria-label="${esc(post.saved ? S.unsave : S.save)}"><i class="fa-${post.saved ? 'solid' : 'regular'} fa-bookmark" aria-hidden="true"></i></button>
                <button type="button" class="cm-act" data-act="share" aria-label="${esc(S.share)}"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i></button>
                <button type="button" class="cm-act" data-act="menu" aria-label="${esc(S.more)}" aria-haspopup="menu"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
            </div>`;
    };

    const cardHtml = (post, index = 0) => {
        const classes = ['cm-post'];
        if (post.spoiler) classes.push('is-spoiler');
        if (state.revealed.has(post.id)) classes.push('is-revealed');
        if (!post.approved) classes.push('is-pending');
        const many = post.media && post.media.length > 1;

        return `
            <article class="${classes.join(' ')}" data-post="${post.id}" style="--cm-i:${Math.min(index, 8)}">
                ${post.approved ? '' : `<div class="cm-post__ribbon"><i class="fa-solid fa-clock" aria-hidden="true"></i> ${esc(post.mine ? S.pending_own : S.pending_staff)}</div>`}
                <button type="button" class="cm-post__media" data-act="open" style="aspect-ratio:${ratioOf(post.media && post.media[0])}" aria-label="${esc(S.open_post)}: ${esc(titleOf(post))}">
                    ${thumbHtml(post)}
                    ${many ? `<span class="cm-badge cm-badge--right" title="${esc(fmt(S.media_count, post.media.length))}"><i class="fa-solid fa-clone" aria-hidden="true"></i> ${post.media.length}</span>` : ''}
                    ${post.spoiler ? `<span class="cm-veil"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i><b>${esc(S.spoiler)}</b><span>${esc(S.spoiler_hint)}</span></span>` : ''}
                </button>
                <div class="cm-post__body">
                    <button type="button" class="cm-post__title" data-act="open">${esc(titleOf(post))}</button>
                    ${byHtml(post, true)}
                    ${post.extra ? `<p class="cm-quote">«${esc(post.extra)}»</p>` : ''}
                    ${actsHtml(post)}
                </div>
            </article>`;
    };

    const voteButton = (post) => `
        <button type="button" class="cm-votebtn${post.voted ? ' is-on' : ''}" data-act="vote" aria-pressed="${post.voted ? 'true' : 'false'}">
            <i class="fa-solid ${post.voted ? 'fa-check' : 'fa-chevron-up'}" aria-hidden="true"></i> <span>${esc(post.voted ? S.voted : S.vote)}</span>
        </button>`;

    const transitionName = (post) => (reducedMotion ? '' : ` style="view-transition-name:cm-r-${post.id}"`);

    const podHtml = (post, place) => `
        <article class="cm-pod cm-pod--${place}" data-post="${post.id}"${transitionName(post)}>
            <span class="cm-medal">${place === 1 ? '<i class="fa-solid fa-crown" aria-hidden="true"></i>' : place}</span>
            <button type="button" class="cm-pod__media" data-act="open" aria-label="${esc(S.open_post)}: ${esc(titleOf(post))}">${thumbHtml(post)}</button>
            <div class="cm-pod__info">
                <button type="button" class="cm-pod__title" data-act="open">${esc(titleOf(post))}</button>
                ${byHtml(post)}
                ${post.extra ? `<p class="cm-quote">«${esc(post.extra)}»</p>` : ''}
                <div class="cm-vote">
                    <span class="cm-vote__n"><span class="cm-num" data-score>${num(post.score)}</span><small>${esc(post.score === 1 ? S.vote_one : S.votes)}</small></span>
                    <span class="cm-vote__gap"></span>
                    ${voteButton(post)}
                </div>
            </div>
        </article>`;

    const rowHtml = (post, index, leader) => `
        <article class="cm-row" data-post="${post.id}"${reducedMotion ? ` style="--cm-i:${Math.min(index, 8)}"` : ` style="--cm-i:${Math.min(index, 8)};view-transition-name:cm-r-${post.id}"`}>
            <span class="cm-row__pos">${post.rank || ''}</span>
            <button type="button" class="cm-row__thumb" data-act="open" aria-label="${esc(S.open_post)}: ${esc(titleOf(post))}">${thumbHtml(post)}</button>
            <div class="cm-row__main">
                <button type="button" class="cm-row__title" data-act="open">${esc(titleOf(post))}</button>
                ${post.extra ? `<p class="cm-row__quote">«${esc(post.extra)}»</p>` : ''}
                ${byHtml(post)}
            </div>
            <div class="cm-meter">
                <span class="cm-meter__bar"><b style="width:${leader > 0 ? Math.max(2, Math.round((post.score / leader) * 100)) : 0}%"></b></span>
                <span class="cm-meter__n cm-num" data-score>${num(post.score)}</span>
            </div>
            ${voteButton(post)}
        </article>`;

    const skeletonHtml = '<div class="cm-skel" aria-hidden="true"><i></i><i></i><i></i></div>';

    // ── Colonne ─────────────────────────────────────────────────────────────
    let columns = [];
    let columnCount = 0;

    const wantedColumns = () => {
        const width = feedEl.clientWidth || app.clientWidth;
        if (width >= 960) return 3;
        if (width >= 580) return 2;
        return 1;
    };

    const buildColumns = () => {
        columnCount = wantedColumns();
        feedEl.innerHTML = '';
        columns = Array.from({ length: columnCount }, () => {
            const column = document.createElement('div');
            column.className = 'cm-col';
            feedEl.appendChild(column);
            return column;
        });
    };

    const shortestColumn = () => columns.reduce((best, column) => (column.offsetHeight < best.offsetHeight ? column : best), columns[0]);

    const appendCards = (posts, animate = true) => {
        posts.forEach((post, index) => {
            shortestColumn().insertAdjacentHTML('beforeend', cardHtml(post, animate ? index : 0));
        });
        observeNew();
    };

    const showSkeletons = () => {
        if (isRanked() || !columns.length) return;
        columns.forEach((column) => column.insertAdjacentHTML('beforeend', skeletonHtml));
    };

    const clearSkeletons = () => $$('.cm-skel', feedEl).forEach((el) => el.remove());

    // ── Classifica ──────────────────────────────────────────────────────────
    const renderRanked = () => {
        if (!podiumEl || !rankEl) return;
        const top = state.posts.slice(0, 3);
        const rest = state.posts.slice(3);
        const leader = state.posts.length ? Math.max(...state.posts.map((post) => post.score)) : 0;

        podiumEl.dataset.count = String(top.length);
        podiumEl.innerHTML = top.map((post, index) => podHtml(post, index + 1)).join('');
        rankEl.innerHTML = rest.map((post, index) => rowHtml(post, index, leader)).join('');
        podiumEl.hidden = top.length === 0;
        rankEl.hidden = rest.length === 0;
        observeNew();
    };

    /** Dopo un voto: stessi post, nuovo ordine, con lo scambio di posto animato. */
    const resortRanked = () => {
        if (!isRanked()) return;
        const before = state.posts.map((post) => post.id).join(',');
        const order = new Map(state.posts.map((post, index) => [post.id, index]));
        state.posts.sort((a, b) => b.score - a.score || order.get(a.id) - order.get(b.id));
        state.posts.forEach((post, index) => { post.rank = index + 1; });
        if (state.posts.map((post) => post.id).join(',') === before) return;

        const draw = () => {
            app.classList.add('cm-noanim');
            renderRanked();
        };

        if (document.startViewTransition && !reducedMotion && !topDialog()) {
            document.startViewTransition(draw).finished.finally(() => app.classList.remove('cm-noanim'));
        } else {
            draw();
            requestAnimationFrame(() => app.classList.remove('cm-noanim'));
        }
    };

    // ── Viste ───────────────────────────────────────────────────────────────
    const emptyState = () => {
        if (state.failed) return ['fa-solid fa-triangle-exclamation', S.load_error, '', 'retry'];
        if (state.filter === 'saved') return ['fa-solid fa-bookmark', S.empty_saved_title, S.empty_saved_text, 'reset'];
        if (state.filter === 'mine') return ['fa-solid fa-user', S.empty_mine_title, S.empty_mine_text, 'reset'];
        if (state.filter === 'pending') return ['fa-solid fa-circle-check', S.empty_pending_title, S.empty_pending_text, 'reset'];
        if (state.q || state.tag) return ['fa-solid fa-magnifying-glass', S.empty_search_title, S.empty_search_text, 'reset'];
        if (isRimasto && state.sort === 'top' && state.period !== 'all') return ['fa-solid fa-ranking-star', S.empty_period_title, S.empty_period_text, ''];
        return [isRimasto ? 'fa-solid fa-ranking-star' : 'fa-solid fa-face-grin-squint', S.empty_title, S.empty_text, ''];
    };

    const renderEmpty = () => {
        const show = !state.loading && state.posts.length === 0;
        emptyEl.hidden = !show;
        if (!show) return;

        const [icon, title, text, action] = emptyState();
        const button = action === 'retry'
            ? `<button type="button" class="cm-btn cm-btn--small" data-act="retry">${esc(S.retry)}</button>`
            : (action === 'reset' ? `<button type="button" class="cm-btn cm-btn--small" data-act="reset">${esc(S.reset_filters)}</button>` : '');
        emptyEl.innerHTML = `<i class="${icon}" aria-hidden="true"></i><strong>${esc(title)}</strong>${text ? `<span>${esc(text)}</span>` : ''}${button}`;
    };

    const renderTags = () => {
        if (!tagsEl) return;
        const tags = state.tags.slice();
        if (state.tag && !tags.includes(state.tag)) tags.unshift(state.tag);
        tagsEl.hidden = tags.length === 0;
        tagsEl.innerHTML = tags.length
            ? `<button type="button" class="cm-tag${state.tag ? '' : ' is-active'}" data-act="tag" data-tag="">${esc(S.all_tags)}</button>`
                + tags.map((tag) => `<button type="button" class="cm-tag${tag === state.tag ? ' is-active' : ''}" data-act="tag" data-tag="${esc(tag)}">#${esc(tag)}</button>`).join('')
            : '';
    };

    const renderHall = () => {
        if (!hallEl) return;
        const show = state.hall.length > 0 && !state.filter && !state.q && !state.tag;
        hallEl.hidden = !show;
        if (!show) return;

        $('[data-cm-hall-grid]', hallEl).innerHTML = state.hall.map((entry) => {
            const [year, month] = String(entry.month).split('-').map(Number);
            const label = new Date(year, (month || 1) - 1, 1).toLocaleDateString(lang, { month: 'long', year: 'numeric' });
            return `
                <button type="button" class="cm-fame" data-act="open-id" data-id="${Number(entry.id)}">
                    ${entry.thumb ? `<img src="${esc(entry.thumb)}" alt="" loading="lazy">` : NO_IMAGE}
                    <div>
                        <small>${esc(label)}</small>
                        <b>${esc(entry.title || S.untitled)}</b>
                        <span>${esc(entry.username || S.deleted_user)} · ${num(entry.votes)} ${esc(entry.votes === 1 ? S.vote_one : S.votes)}</span>
                    </div>
                </button>`;
        }).join('');
    };

    const renderAll = () => {
        const ranked = isRanked();
        if (podiumEl) podiumEl.hidden = !ranked || state.posts.length === 0;
        if (rankEl) rankEl.hidden = !ranked || state.posts.length <= 3;
        feedEl.hidden = ranked;

        if (ranked) {
            feedEl.innerHTML = '';
            columns = [];
            renderRanked();
        } else {
            buildColumns();
            appendCards(state.posts);
        }

        renderEmpty();
        renderTags();
        renderHall();
    };

    const setPosts = (posts) => {
        state.posts = posts;
        state.byId = new Map(posts.map((post) => [post.id, post]));
    };

    const syncControls = () => {
        $$('[data-cm-sorts] .cm-seg__tab').forEach((tab) => {
            const active = tab.dataset.sort === state.sort && (tab.dataset.period || 'all') === state.period;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        $$('[data-cm-filter]').forEach((button) => button.setAttribute('aria-pressed', button.dataset.cmFilter === state.filter ? 'true' : 'false'));

        const label = searchInput && searchInput.closest('.cm-search');
        if (label) label.classList.toggle('has-value', state.q !== '');
        const clear = $('[data-cm-search-clear]');
        if (clear) clear.hidden = state.q === '';

        const dot = $('[data-cm-mine-dot]');
        if (dot) {
            dot.hidden = state.pendingMine <= 0;
            dot.textContent = String(state.pendingMine);
        }
    };

    const pageUrl = (postId = 0) => {
        const params = new URLSearchParams();
        if (state.sort !== defaultSort) params.set('sort', state.sort);
        if (state.period !== 'all') params.set('period', state.period);
        if (state.tag) params.set('tag', state.tag);
        if (state.q) params.set('q', state.q);
        if (postId) params.set('post', String(postId));
        const query = params.toString();
        return D.base + (query ? `?${query}` : '');
    };

    const syncUrl = () => {
        if (viewer.post) return;
        history.replaceState(history.state, '', pageUrl());
    };

    // ── Caricamento ─────────────────────────────────────────────────────────
    const feedParams = (page) => {
        const params = new URLSearchParams({ type, sort: state.sort, period: state.period, page: String(page), limit: String(D.pageSize || 18) });
        if (state.q) params.set('q', state.q);
        if (state.tag) params.set('tag', state.tag);
        if (state.filter === 'saved') params.set('saved', '1');
        if (state.filter === 'mine') params.set('mine', '1');
        if (state.filter === 'pending') params.set('status', 'pending');
        if (isRimasto && page === 1) params.set('hall', '1');
        return params;
    };

    const load = async (reset) => {
        if (!reset && (state.loading || state.page >= state.pages)) return;

        const page = reset ? 1 : state.page + 1;
        const token = ++state.token;
        state.loading = true;
        state.failed = false;

        if (reset) {
            setPosts([]);
            renderAll();
            emptyEl.hidden = true;
        }
        showSkeletons();

        try {
            const data = await api(`get_posts.php?${feedParams(page)}`);
            if (token !== state.token) return;

            state.page = page;
            state.pages = Number(data.pagination?.pages) || 1;
            const fresh = (data.posts || []).filter((post) => !state.byId.has(post.id));

            if (reset) {
                if (Array.isArray(data.tags)) state.tags = data.tags;
                if (Array.isArray(data.hall)) state.hall = data.hall;
                state.seenAt = Number(data.now) || state.seenAt;
                hideNewPill();
            }

            state.loading = false;
            clearSkeletons();
            fresh.forEach((post) => {
                state.posts.push(post);
                state.byId.set(post.id, post);
            });

            if (reset || isRanked()) {
                renderAll();
            } else {
                appendCards(fresh);
                renderEmpty();
            }
            if (viewer.post) updateNav();
        } catch (error) {
            if (token !== state.token) return;
            state.loading = false;
            clearSkeletons();
            if (reset) {
                state.failed = true;
                renderEmpty();
            }
            toast(error.message, true);
        }
    };

    const reload = () => {
        syncControls();
        syncUrl();
        return load(true);
    };

    // ── Osservatori: visite, GIF, altra pagina ──────────────────────────────
    let pendingViews = [];
    let viewTimer = 0;

    // Le visualizzazioni partono a gruppi: una richiesta (e una connessione
    // al database) per tutto quello che è passato sullo schermo.
    const flushViews = () => {
        clearTimeout(viewTimer);
        viewTimer = 0;
        if (!pendingViews.length) return;
        const ids = pendingViews;
        pendingViews = [];
        fetch('/api/content/view_post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': D.csrf || '' },
            body: JSON.stringify({ type, ids }),
            keepalive: true,
            credentials: 'same-origin',
        }).catch(() => {});
    };

    const queueView = (id) => {
        const post = state.byId.get(id) || (viewer.post && viewer.post.id === id ? viewer.post : null);
        if (!id || state.viewed.has(id) || (post && !post.approved)) return;
        state.viewed.add(id);
        pendingViews.push(id);
        if (pendingViews.length >= 25) flushViews();
        else if (!viewTimer) viewTimer = setTimeout(flushViews, 1500);
    };

    window.addEventListener('pagehide', flushViews);

    const viewObserver = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            queueView(Number(entry.target.dataset.post));
            viewObserver.unobserve(entry.target);
        });
    }, { threshold: 0.55 }) : null;

    // Le GIF si muovono solo mentre sono sullo schermo: fuori resta il fotogramma.
    const gifObserver = 'IntersectionObserver' in window && !reducedMotion ? new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            const img = entry.target;
            const wanted = entry.isIntersecting && entry.intersectionRatio >= 0.6 ? img.dataset.gif : img.dataset.still;
            if (wanted && img.getAttribute('src') !== wanted) img.setAttribute('src', wanted);
        });
    }, { threshold: [0, 0.6] }) : null;

    const observeNew = () => {
        $$('[data-post]:not([data-seen])', app).forEach((el) => {
            el.dataset.seen = '1';
            if (viewObserver) viewObserver.observe(el);
        });
        if (gifObserver) {
            $$('img[data-gif]:not([data-watched])', app).forEach((img) => {
                img.dataset.watched = '1';
                gifObserver.observe(img);
            });
        }
    };

    if (sentinel && 'IntersectionObserver' in window) {
        new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) load(false);
        }, { rootMargin: '700px 0px' }).observe(sentinel);
    }

    // Un'immagine senza misure note prende le sue appena arriva; una rotta lascia un segnaposto.
    app.addEventListener('load', (event) => {
        const img = event.target;
        if (!(img instanceof HTMLImageElement) || !img.dataset.measure || !img.naturalWidth) return;
        const box = img.closest('.cm-post__media');
        if (box) box.style.aspectRatio = ratioOf({ w: img.naturalWidth, h: img.naturalHeight });
    }, true);

    app.addEventListener('error', (event) => {
        const el = event.target;
        if (!(el instanceof HTMLImageElement) || el.classList.contains('cm-avatar') || el.classList.contains('cr-premium-gem')) return;
        // La miniatura non c'è (GD assente, formato strano): si prova l'originale una volta.
        if (el.dataset.gif && el.getAttribute('src') !== el.dataset.gif) {
            el.setAttribute('src', el.dataset.gif);
            return;
        }
        el.insertAdjacentHTML('beforebegin', NO_IMAGE);
        el.remove();
    }, true);

    // ── Aggiornare un post ovunque sia disegnato ────────────────────────────
    const patch = (post) => {
        $$(`.cm-post[data-post="${post.id}"]`, app).forEach((card) => {
            const acts = $('.cm-acts', card);
            if (acts) acts.outerHTML = actsHtml(post);
        });

        $$(`.cm-pod[data-post="${post.id}"], .cm-row[data-post="${post.id}"]`, app).forEach((el) => {
            const button = $('.cm-votebtn', el);
            if (button) button.outerHTML = voteButton(post);
            const score = $('[data-score]', el);
            if (score && score.textContent !== num(post.score)) {
                score.textContent = num(post.score);
                score.classList.remove('is-tick');
                void score.offsetWidth;
                score.classList.add('is-tick');
            }
            const label = $('.cm-vote__n small', el);
            if (label) label.textContent = post.score === 1 ? S.vote_one : S.votes;
        });

        if (isRanked()) {
            const leader = state.posts.length ? Math.max(...state.posts.map((item) => item.score)) : 0;
            state.posts.forEach((item) => {
                const bar = $(`.cm-row[data-post="${item.id}"] .cm-meter__bar b`, app);
                if (bar) bar.style.width = `${leader > 0 ? Math.max(2, Math.round((item.score / leader) * 100)) : 0}%`;
            });
        }

        if (viewer.post && viewer.post.id === post.id) renderSide();
    };

    const removePost = (id) => {
        state.posts = state.posts.filter((post) => post.id !== id);
        state.byId.delete(id);
        if (isRanked()) {
            state.posts.forEach((post, index) => { post.rank = index + 1; });
            renderAll();
        } else {
            $$(`.cm-post[data-post="${id}"]`, app).forEach((card) => card.remove());
            renderEmpty();
        }
    };

    const burst = (anchor, emoji) => {
        if (reducedMotion || !anchor) return;
        const box = anchor.getBoundingClientRect();
        const el = document.createElement('span');
        el.className = 'cm-burst cm-emo';
        el.textContent = emoji;
        el.style.left = `${box.left + box.width / 2}px`;
        el.style.top = `${box.top}px`;
        host().appendChild(el);
        el.addEventListener('animationend', () => el.remove(), { once: true });
        setTimeout(() => el.remove(), 1200);
    };

    const popButton = (postId, selector) => {
        $$(`[data-post="${postId}"] ${selector}`).forEach((button) => {
            button.classList.remove('is-pop');
            void button.offsetWidth;
            button.classList.add('is-pop');
        });
    };

    // ── Azioni ──────────────────────────────────────────────────────────────
    /** `key` nullo = clic veloce: toglie la propria reazione, o mette il fuoco. */
    const react = async (post, key, anchor) => {
        if (!requireLogin() || !post.approved) return;

        const previous = { my: post.my_reaction, reactions: { ...(post.reactions || {}) }, score: post.score };
        const wanted = key || (post.my_reaction ? post.my_reaction : 'fire');
        const removing = post.my_reaction === wanted;
        const next = { ...previous.reactions };

        if (post.my_reaction) next[post.my_reaction] = Math.max(0, (next[post.my_reaction] || 0) - 1);
        if (!removing) next[wanted] = (next[wanted] || 0) + 1;
        Object.keys(next).forEach((name) => { if (!next[name]) delete next[name]; });

        post.reactions = next;
        post.my_reaction = removing ? null : wanted;
        post.score = Object.values(next).reduce((sum, count) => sum + count, 0);
        patch(post);

        if (!removing) {
            burst(anchor, REACTIONS[wanted]);
            popButton(post.id, '[data-act="react"], .cm-react.is-on');
        }

        try {
            const data = await api('react_post.php', { method: 'POST', body: { type, id: post.id, reaction: wanted, set: !removing } });
            post.reactions = data.reactions || {};
            post.my_reaction = data.reaction || null;
            post.score = Number(data.score) || 0;
            patch(post);
        } catch (error) {
            post.reactions = previous.reactions;
            post.my_reaction = previous.my;
            post.score = previous.score;
            patch(post);
            toast(error.message, true);
        }
    };

    let resortTimer = 0;
    const vote = async (post) => {
        if (!requireLogin() || !post.approved) return;

        const was = !!post.voted;
        post.voted = !was;
        post.score = Math.max(0, post.score + (was ? -1 : 1));
        patch(post);
        if (!was) popButton(post.id, '.cm-votebtn, [data-act="vote"]');

        try {
            const data = await api('react_post.php', { method: 'POST', body: { type, id: post.id, set: !was } });
            // In una classifica di periodo il numero mostrato non è il totale:
            // si corregge con la differenza, non con il valore del server.
            const expected = was ? -1 : 1;
            if (Number(data.delta) !== expected) post.score = Math.max(0, post.score - expected + Number(data.delta || 0));
            post.voted = !!data.active;
            patch(post);
            clearTimeout(resortTimer);
            resortTimer = setTimeout(resortRanked, 650);
        } catch (error) {
            post.voted = was;
            post.score = Math.max(0, post.score + (was ? 1 : -1));
            patch(post);
            toast(error.message, true);
        }
    };

    const save = async (post) => {
        if (!requireLogin() || !post.approved) return;
        const was = !!post.saved;
        post.saved = !was;
        patch(post);

        try {
            const data = await api('save_post.php', { method: 'POST', body: { type, id: post.id } });
            post.saved = !!data.active;
            patch(post);
            toast(post.saved ? S.saved_done : S.unsaved_done);
            if (!post.saved && state.filter === 'saved') removePost(post.id);
        } catch (error) {
            post.saved = was;
            patch(post);
            toast(error.message, true);
        }
    };

    const copyLink = async (post) => toast((await copyText(absolute(post))) ? S.link_copied : S.copy_failed);

    const share = async (post) => {
        const touch = window.matchMedia('(pointer: coarse)').matches;
        if (navigator.share && touch) {
            try {
                await navigator.share({ title: titleOf(post), url: absolute(post) });
                return;
            } catch (error) {
                if (error && error.name === 'AbortError') return;
            }
        }
        copyLink(post);
    };

    const setApproval = async (post, approved) => {
        try {
            const data = await api('approve_post.php', { method: 'POST', body: { type, id: post.id, approved: approved ? 1 : 0 } });
            toast(data.message);
            post.approved = approved;
            const stays = approved ? state.filter !== 'pending' : (state.filter === 'pending' || state.filter === 'mine');
            if (viewer.post && viewer.post.id === post.id) renderViewer();
            if (!stays) removePost(post.id);
            else if (!isRanked()) {
                $$(`.cm-post[data-post="${post.id}"]`, app).forEach((card) => { card.outerHTML = cardHtml(post); });
                observeNew();
            } else renderAll();
        } catch (error) {
            toast(error.message, true);
        }
    };

    // ── Finestre ────────────────────────────────────────────────────────────
    const showDialog = (dialog) => {
        if (!dialog || dialog.open) return;
        dialog.classList.remove('is-closing');
        pop.close(menuEl);
        pop.close(pickerEl);
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
        openDialogs.push(dialog);
        document.documentElement.classList.add('cm-lock');
    };

    const hideDialog = (dialog) => new Promise((resolve) => {
        if (!dialog || !dialog.open) {
            resolve();
            return;
        }

        const finish = () => {
            dialog.classList.remove('is-closing');
            // Avvisi e menu vivono dentro la finestra finché è aperta.
            [toastEl, menuEl, pickerEl].forEach((el) => { if (el && dialog.contains(el)) document.body.appendChild(el); });
            if (typeof dialog.close === 'function') dialog.close();
            else dialog.removeAttribute('open');
            const index = openDialogs.indexOf(dialog);
            if (index >= 0) openDialogs.splice(index, 1);
            if (!openDialogs.length) document.documentElement.classList.remove('cm-lock');
            resolve();
        };

        if (reducedMotion) {
            finish();
            return;
        }
        dialog.classList.add('is-closing');
        setTimeout(finish, 170);
    });

    let confirmResolve = null;
    /**
     * Una domanda con due bottoni; `extra` aggiunge campi (il motivo di una
     * rimozione). Risolve con i dati del form, o null se si annulla.
     */
    const ask = ({ title, text, yes, danger = true, extra = '' }) => new Promise((resolve) => {
        if (!confirmEl) {
            resolve(window.confirm(title) ? new FormData() : null);
            return;
        }
        $('[data-cm-confirm-title]', confirmEl).textContent = title;
        $('[data-cm-confirm-text]', confirmEl).textContent = text || '';
        const extraBox = $('[data-cm-confirm-extra]', confirmEl);
        extraBox.innerHTML = extra;
        extraBox.hidden = !extra;
        const yesButton = $('[data-cm-confirm-yes]', confirmEl);
        yesButton.textContent = yes;
        yesButton.className = `cm-btn ${danger ? 'cm-btn--danger' : 'cm-btn--primary'}`;
        confirmResolve = resolve;
        showDialog(confirmEl);
        yesButton.focus();
    });

    const settleConfirm = async (value) => {
        const resolve = confirmResolve;
        confirmResolve = null;
        await hideDialog(confirmEl);
        if (resolve) resolve(value);
    };

    if (confirmEl) {
        $('[data-cm-confirm-form]', confirmEl).addEventListener('submit', (event) => {
            event.preventDefault();
            settleConfirm(new FormData(event.currentTarget));
        });
        $('[data-cm-confirm-no]', confirmEl).addEventListener('click', () => settleConfirm(null));
        confirmEl.addEventListener('cancel', (event) => { event.preventDefault(); settleConfirm(null); });
        confirmEl.addEventListener('click', (event) => { if (event.target === confirmEl) settleConfirm(null); });
        confirmEl.addEventListener('change', (event) => {
            if (event.target.name !== 'reason') return;
            const other = $('[data-cm-reason-other]', confirmEl);
            if (other) {
                other.hidden = event.target.value !== 'other';
                if (!other.hidden) other.focus();
            }
        });
    }

    const deletePost = async (post) => {
        const moderating = isAdmin && !post.mine;
        const reasons = moderating ? `
            <div class="cm-field">
                <label>${esc(S.delete_reason)}</label>
                <div class="cm-choices cm-choices--chips">
                    ${[['', S.delete_reason_none], ['duplicate', S.reason_duplicate], ['quality', S.reason_quality], ['offtopic', S.reason_offtopic], ['rules', S.reason_rules], ['other', S.delete_reason_other]]
                        .map(([value, label], index) => `<label class="cm-choice"><input type="radio" name="reason" value="${esc(value)}"${index === 0 ? ' checked' : ''}><i aria-hidden="true"></i><span>${esc(label)}</span></label>`).join('')}
                </div>
            </div>
            <input class="cm-input" type="text" name="reason_text" maxlength="300" placeholder="${esc(S.delete_reason_ph)}" data-cm-reason-other hidden>` : '';

        const answer = await ask({ title: S.delete_title, text: S.delete_text, yes: S.delete, extra: reasons });
        if (!answer) return;

        let reason = String(answer.get('reason') || '');
        if (reason === 'other') reason = String(answer.get('reason_text') || '').trim();

        try {
            const data = await api('delete_post.php', { method: 'POST', body: { type, id: post.id, reason } });
            toast(data.message);
            if (post.mine && !post.approved) state.pendingMine = Math.max(0, state.pendingMine - 1);
            if (viewer.post && viewer.post.id === post.id) closeViewer();
            removePost(post.id);
            syncControls();
        } catch (error) {
            toast(error.message, true);
        }
    };

    // ── Menu «⋯» e scelta della reazione ────────────────────────────────────
    const place = (el, anchor, above = false) => {
        const box = anchor.getBoundingClientRect();
        host().appendChild(el);
        pop.open(el);
        const width = el.offsetWidth;
        const height = el.offsetHeight;
        let left = above ? box.left + box.width / 2 - width / 2 : box.right - width;
        let top = above ? box.top - height - 8 : box.bottom + 6;
        if (!above && top + height > window.innerHeight - 8) top = box.top - height - 6;
        if (above && top < 8) top = box.bottom + 8;
        left = Math.max(8, Math.min(left, window.innerWidth - width - 8));
        el.style.left = `${left}px`;
        el.style.top = `${Math.max(8, top)}px`;
    };

    let menuPost = null;
    const openMenu = (post, anchor) => {
        if (pop.isOpen(menuEl) && menuPost === post) {
            pop.close(menuEl);
            return;
        }
        menuPost = post;
        const item = (act, icon, label, danger = false) => `<button type="button" role="menuitem" data-menu="${act}"${danger ? ' class="is-danger"' : ''}><i class="${icon}" aria-hidden="true"></i> ${esc(label)}</button>`;
        const items = [item('copy', 'fa-solid fa-link', S.copy_link)];
        if (!post.mine) items.push(item('report', 'fa-solid fa-flag', S.report));
        if (post.can_manage || isAdmin) items.push('<hr>');
        if (post.can_manage) items.push(item('edit', 'fa-solid fa-pen', S.edit));
        if (isAdmin) items.push(post.approved ? item('hide', 'fa-solid fa-eye-slash', S.hide) : item('approve', 'fa-solid fa-check', S.approve));
        if (post.can_manage) items.push(item('delete', 'fa-solid fa-trash', S.delete, true));
        menuEl.innerHTML = items.join('');
        place(menuEl, anchor);
        const first = $('button', menuEl);
        if (first) first.focus({ preventScroll: true });
    };

    if (menuEl) {
        menuEl.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-menu]');
            if (!button || !menuPost) return;
            const post = menuPost;
            pop.close(menuEl);
            const action = button.dataset.menu;

            if (action === 'copy') copyLink(post);
            if (action === 'report') openReport(post);
            if (action === 'edit') openEditor(post);
            if (action === 'delete') deletePost(post);
            if (action === 'approve') setApproval(post, true);
            if (action === 'hide') {
                const answer = await ask({ title: S.hide_title, text: S.hide_text, yes: S.hide });
                if (answer) setApproval(post, false);
            }
        });

        menuEl.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
            event.preventDefault();
            const buttons = $$('button', menuEl);
            const index = buttons.indexOf(document.activeElement);
            const next = buttons[(index + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length];
            if (next) next.focus();
        });
    }

    let pickerPost = null;
    let pickerTimer = 0;
    const openPicker = (post, anchor) => {
        if (!me || !post.approved || !pickerEl) return;
        pickerPost = post;
        pickerEl.innerHTML = Object.entries(REACTIONS).map(([key, emoji]) => `<button type="button" role="menuitem" class="cm-emo${post.my_reaction === key ? ' is-on' : ''}" data-reaction="${esc(key)}" aria-label="${esc(key)}">${emoji}</button>`).join('');
        place(pickerEl, anchor, true);
    };

    if (pickerEl) {
        pickerEl.addEventListener('click', (event) => {
            const button = event.target.closest('[data-reaction]');
            if (!button || !pickerPost) return;
            const post = pickerPost;
            pop.close(pickerEl);
            react(post, button.dataset.reaction, button);
        });
        pickerEl.addEventListener('pointerenter', () => clearTimeout(pickerTimer));
        pickerEl.addEventListener('pointerleave', () => { pickerTimer = setTimeout(() => pop.close(pickerEl), 350); });
    }

    // Sul bottone della reazione: clic = fuoco; fermarsi sopra col mouse (o
    // tenere premuto col dito) apre tutte le reazioni.
    let pressTimer = 0;
    let pressOpened = false;

    const reactButtonFrom = (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-act="react"]') : null;
        return button && app.contains(button) ? button : null;
    };

    app.addEventListener('pointerover', (event) => {
        if (event.pointerType !== 'mouse') return;
        const button = reactButtonFrom(event);
        if (!button || button.contains(event.relatedTarget)) return;
        clearTimeout(pickerTimer);
        const post = state.byId.get(Number(button.closest('[data-post]')?.dataset.post));
        if (post) pickerTimer = setTimeout(() => openPicker(post, button), 380);
    });

    app.addEventListener('pointerout', (event) => {
        if (event.pointerType !== 'mouse') return;
        const button = reactButtonFrom(event);
        if (!button || button.contains(event.relatedTarget)) return;
        clearTimeout(pickerTimer);
        pickerTimer = setTimeout(() => pop.close(pickerEl), 350);
    });

    app.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse') return;
        const button = reactButtonFrom(event);
        if (!button) return;
        pressOpened = false;
        clearTimeout(pressTimer);
        const post = state.byId.get(Number(button.closest('[data-post]')?.dataset.post));
        pressTimer = setTimeout(() => {
            if (!post) return;
            pressOpened = true;
            openPicker(post, button);
        }, 450);
    });

    ['pointerup', 'pointercancel', 'pointerleave'].forEach((name) => app.addEventListener(name, () => clearTimeout(pressTimer), true));
    app.addEventListener('contextmenu', (event) => { if (reactButtonFrom(event) && pressOpened) event.preventDefault(); });

    // ── Segnala ─────────────────────────────────────────────────────────────
    let reportPost = null;
    const openReport = (post) => {
        if (!requireLogin() || !reportEl) return;
        reportPost = post;
        const form = $('[data-cm-report-form]', reportEl);
        form.reset();
        showDialog(reportEl);
    };

    if (reportEl) {
        const form = $('[data-cm-report-form]', reportEl);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!reportPost) return;
            const submit = $('[type="submit"]', form);
            const fields = new FormData(form);
            const detail = String(fields.get('detail') || '').trim();
            const reason = String(fields.get('reason') || '') + (detail ? ` - ${detail}` : '');

            submit.disabled = true;
            try {
                const data = await api('report_post.php', { method: 'POST', body: { type, id: reportPost.id, reason } });
                await hideDialog(reportEl);
                toast(data.message);
            } catch (error) {
                toast(error.message, true);
            } finally {
                submit.disabled = false;
            }
        });
        $$('[data-cm-dialog-close]', reportEl).forEach((button) => button.addEventListener('click', () => hideDialog(reportEl)));
        reportEl.addEventListener('cancel', (event) => { event.preventDefault(); hideDialog(reportEl); });
        reportEl.addEventListener('click', (event) => { if (event.target === reportEl) hideDialog(reportEl); });
    }

    // ── Post aperto ─────────────────────────────────────────────────────────
    const viewer = { post: null, index: 0, pushed: false, comments: [], replyTo: null, token: 0, player: null, stepping: false };

    const stage = viewerEl ? $('[data-cm-stage]', viewerEl) : null;
    const stageMedia = viewerEl ? $('[data-cm-stage-media]', viewerEl) : null;
    const sideTop = viewerEl ? $('[data-cm-side-top]', viewerEl) : null;
    const commentsBox = viewerEl ? $('[data-cm-comments]', viewerEl) : null;
    const commentForm = viewerEl ? $('[data-cm-comment-form]', viewerEl) : null;
    const commentInput = viewerEl ? $('[data-cm-comment-input]', viewerEl) : null;

    const listIndex = () => (viewer.post ? state.posts.findIndex((post) => post.id === viewer.post.id) : -1);
    const currentMedia = () => (viewer.post ? viewer.post.media[viewer.index] || null : null);

    /** Le frecce sul media si fermano al primo e all'ultimo file; i bottoni di fianco cambiano post. */
    const updateNav = () => {
        if (!viewer.post || !stage) return;
        const count = viewer.post.media.length;
        const position = listIndex();

        $$('[data-cm-media-step]', stage).forEach((button) => {
            button.hidden = count < 2;
            button.disabled = Number(button.dataset.cmMediaStep) < 0 ? viewer.index <= 0 : viewer.index >= count - 1;
        });
        $('[data-cm-post-step="-1"]', viewerEl).disabled = position <= 0;
        $('[data-cm-post-step="1"]', viewerEl).disabled = position < 0 || (position >= state.posts.length - 1 && state.page >= state.pages);
    };

    // ── Zoom ────────────────────────────────────────────────────────────────
    // A zoom 1 l'immagine sta tutta nello stage. Lo zoom è una trasformazione:
    // rotella e pizzico ingrandiscono sul punto che si sta guardando,
    // trascinando ci si sposta senza mai uscire dai bordi dell'immagine.
    const MAX_UPSCALE = 2;
    const zoom = { scale: 1, x: 0, y: 0 };
    const pointers = new Map();
    let drag = null;
    let pinch = null;
    let lastTap = null;

    const stageImage = () => (stageMedia ? $('.cm-stage__img', stageMedia) : null);
    const isZoomed = () => zoom.scale > 1.001;

    const stagePoint = (event) => {
        const box = stageMedia.getBoundingClientRect();
        return { x: event.clientX - box.left, y: event.clientY - box.top };
    };

    /** Le misure dell'immagine a zoom 1, e lo zoom che la porta alle sue misure vere. */
    const fitOf = (img) => {
        const media = currentMedia();
        const boxW = stageMedia.clientWidth || 1;
        const boxH = stageMedia.clientHeight || 1;
        const width = img.naturalWidth || (media && media.w) || boxW;
        const height = img.naturalHeight || (media && media.h) || boxH;
        const k = Math.min(boxW / width, boxH / height, MAX_UPSCALE);
        return { w: width * k, h: height * k, boxW, boxH, natural: 1 / k };
    };

    const clampZoom = (fit) => {
        if (!isZoomed()) {
            zoom.scale = 1;
            zoom.x = 0;
            zoom.y = 0;
            return;
        }
        const maxX = Math.max(0, (fit.w * zoom.scale - fit.boxW) / 2);
        const maxY = Math.max(0, (fit.h * zoom.scale - fit.boxH) / 2);
        zoom.x = Math.min(maxX, Math.max(-maxX, zoom.x));
        zoom.y = Math.min(maxY, Math.max(-maxY, zoom.y));
    };

    const applyZoom = (animate = false) => {
        const img = stageImage();
        const zoomed = isZoomed();
        if (img) {
            img.style.transition = animate && !reducedMotion ? '' : 'none';
            img.style.transform = zoomed ? `translate3d(${zoom.x.toFixed(1)}px, ${zoom.y.toFixed(1)}px, 0) scale(${zoom.scale.toFixed(4)})` : '';
        }
        if (stage.classList.contains('is-zoomed') === zoomed) return;

        stage.classList.toggle('is-zoomed', zoomed);
        const button = $('[data-cm-zoom]', stage);
        button.setAttribute('aria-pressed', zoomed ? 'true' : 'false');
        button.setAttribute('aria-label', zoomed ? S.zoom_out : S.zoom);
        button.title = zoomed ? S.zoom_out : S.zoom;
        $('i', button).className = `fa-solid fa-magnifying-glass-${zoomed ? 'minus' : 'plus'}`;
    };

    /** Porta lo zoom a `scale` tenendo fermo sotto il cursore il punto (x, y) dello stage. */
    const zoomTo = (scale, x, y, animate = false) => {
        const img = stageImage();
        if (!img) return;
        const fit = fitOf(img);
        const next = Math.min(Math.min(8, Math.max(3, fit.natural * 1.5)), Math.max(1, scale));
        const cx = (x ?? fit.boxW / 2) - fit.boxW / 2;
        const cy = (y ?? fit.boxH / 2) - fit.boxH / 2;
        const ratio = next / zoom.scale;
        zoom.x = cx - (cx - zoom.x) * ratio;
        zoom.y = cy - (cy - zoom.y) * ratio;
        zoom.scale = next;
        clampZoom(fit);
        applyZoom(animate);
    };

    const resetZoom = (animate = false) => {
        zoom.scale = 1;
        zoom.x = 0;
        zoom.y = 0;
        applyZoom(animate);
    };

    /**
     * Clic, doppio tocco e bottone: dentro fino a vedere i pixel veri
     * dell'immagine (fra il doppio e il quadruplo), oppure di nuovo intera.
     */
    const toggleZoom = (x, y) => {
        const img = stageImage();
        if (!img) return;
        if (isZoomed()) resetZoom(true);
        else zoomTo(Math.min(4, Math.max(2, fitOf(img).natural / (window.devicePixelRatio || 1))), x, y, true);
    };

    // ── Schermo intero ──────────────────────────────────────────────────────
    // Va a schermo intero lo stage, con frecce e pallini: vale per immagini e
    // video (il player usa lo stesso). Dove il browser non lo permette (iPhone)
    // lo stage copre tutta la finestra del post.
    const fullscreenElement = () => document.fullscreenElement || document.webkitFullscreenElement || null;
    const isFull = () => !!stage && (fullscreenElement() === stage || viewerEl.classList.contains('is-immersive'));

    const syncFull = () => {
        if (!stage) return;
        const on = isFull();
        stage.classList.toggle('is-full', on);
        const button = $('[data-cm-fullscreen]', stage);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        button.setAttribute('aria-label', on ? S.fullscreen_exit : S.fullscreen);
        button.title = `${on ? S.fullscreen_exit : S.fullscreen} (F)`;
        $('i', button).className = `fa-solid ${on ? 'fa-compress' : 'fa-expand'}`;
        if (viewer.player) viewer.player.syncFullscreen();
        resetZoom();
    };

    let fullTimer = 0;

    /** Il ripiego: lo stage copre la finestra del post. */
    const fillViewer = () => {
        clearTimeout(fullTimer);
        if (!viewer.post || fullscreenElement() === stage) return;
        viewerEl.classList.add('is-immersive');
        syncFull();
    };

    const setFull = (on) => {
        if (!stage || on === isFull()) return;
        clearTimeout(fullTimer);

        if (!on) {
            viewerEl.classList.remove('is-immersive');
            if (fullscreenElement()) {
                const leaving = (document.exitFullscreen || document.webkitExitFullscreen).call(document);
                if (leaving && leaving.catch) leaving.catch(() => {});
            }
            syncFull();
            return;
        }

        const request = stage.requestFullscreen || stage.webkitRequestFullscreen;
        if (!request) {
            fillViewer();
            return;
        }
        try {
            const entering = request.call(stage);
            if (entering && entering.catch) entering.catch(fillViewer);
        } catch (error) {
            fillViewer();
            return;
        }
        // I browser dentro certe app non rispondono né sì né no: dopo un attimo si passa al ripiego.
        fullTimer = setTimeout(fillViewer, 800);
    };

    const onFullscreenChange = () => {
        clearTimeout(fullTimer);
        // Entrati o usciti dallo schermo intero vero, il ripiego non serve più.
        if (viewerEl) viewerEl.classList.remove('is-immersive');
        syncFull();
    };

    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.addEventListener('webkitfullscreenchange', onFullscreenChange);

    /** Il tasto del player. Su iPhone lo schermo intero vero c'è solo per i video, con i controlli di iOS. */
    const toggleVideoFull = () => {
        const video = viewer.player ? viewer.player.video : null;
        const native = !stage.requestFullscreen && !stage.webkitRequestFullscreen && video && video.webkitEnterFullscreen;
        if (native && !isFull()) video.webkitEnterFullscreen();
        else setFull(!isFull());
    };

    // ── Stage ───────────────────────────────────────────────────────────────
    const coverOf = (post) => {
        const media = post.media && post.media[0];
        if (!media) return '';
        return media.thumb || (media.kind === 'video' ? '' : media.url);
    };

    /** Su telefono lo stage prende le proporzioni del media più alto del post: scorrendoli non cambia altezza. */
    const setStageRatio = (post) => {
        const ratios = post.media.map((media) => (media.w && media.h ? media.w / media.h : 0)).filter(Boolean);
        viewerEl.style.setProperty('--cm-ratio', (ratios.length ? Math.min(...ratios) : 1).toFixed(4));
    };

    const clearStage = () => {
        if (!stageMedia) return;
        if (viewer.player) {
            viewer.player.destroy();
            viewer.player = null;
        }
        $$('video', stageMedia).forEach((video) => {
            try {
                video.pause();
                video.removeAttribute('src');
                video.load();
            } catch (error) {
                // niente
            }
        });
        stageMedia.innerHTML = '';
        pointers.clear();
        drag = null;
        pinch = null;
        lastTap = null;
        stage.classList.remove('is-loading', 'is-panning');
        resetZoom();
    };

    const mountVideo = (post, media) => {
        if (!window.CripsumPlayer) {
            // Senza il player del sito restano i controlli del browser.
            stageMedia.innerHTML = `<video src="${esc(media.url)}" ${media.thumb ? `poster="${esc(media.thumb)}"` : ''} controls playsinline preload="metadata"></video>`;
            $('video', stageMedia).play().catch(() => {});
            return;
        }

        // A fine video resta fermo su «Rivedi»; se era l'ultimo file del post propone il post dopo.
        const position = listIndex();
        const next = position >= 0 && viewer.index >= post.media.length - 1 ? state.posts[position + 1] : null;
        viewer.player = window.CripsumPlayer.mount(stageMedia, {
            src: media.url,
            poster: media.thumb || '',
            title: titleOf(post),
            autoplay: true,
            fullscreen: { isOn: isFull, toggle: toggleVideoFull },
            next: next ? { title: titleOf(next), cover: coverOf(next) } : null,
            onNext: next ? () => stepPost(1) : null,
            strings: D.player || {},
        });
    };

    /** `direction` dice da che parte entra il media nuovo (0: nessun movimento). */
    const renderStage = (direction = 0) => {
        const post = viewer.post;
        if (!post || !stage) return;

        clearStage();
        const count = post.media.length;
        const media = currentMedia();
        const isVideo = !!media && media.kind === 'video';
        const isImage = !!media && !isVideo;
        const glow = $('[data-cm-stage-glow]', stage);

        stage.classList.toggle('is-video', isVideo);
        stageMedia.dataset.dir = direction > 0 ? 'next' : (direction < 0 ? 'prev' : '');
        setStageRatio(post);

        if (!media) {
            stageMedia.innerHTML = NO_IMAGE;
            glow.style.backgroundImage = '';
        } else if (isVideo) {
            glow.style.backgroundImage = media.thumb ? `url("${media.thumb}")` : '';
            mountVideo(post, media);
        } else {
            // Un'immagine piccola cresce al massimo del doppio: oltre si sgrana.
            const cap = media.w && media.h ? ` style="max-width:${media.w * MAX_UPSCALE}px;max-height:${media.h * MAX_UPSCALE}px"` : '';
            stageMedia.innerHTML = `<img class="cm-stage__img godomedia" src="${esc(media.url)}" alt="${esc(titleOf(post))}" decoding="async" draggable="false"${cap}>`;
            glow.style.backgroundImage = `url("${media.thumb || media.url}")`;
            stage.classList.toggle('is-loading', !stageImage().complete);
        }

        $('[data-cm-zoom]', stage).hidden = !isImage;
        // Sui video lo schermo intero è il tasto del player; senza media resta solo per uscirne.
        $('[data-cm-fullscreen]', stage).hidden = isVideo || (!isImage && !isFull());

        const counter = $('[data-cm-stage-count]', stage);
        counter.hidden = count < 2;
        counter.textContent = count < 2 ? '' : `${viewer.index + 1} / ${count}`;

        const dots = $('[data-cm-stage-dots]', stage);
        dots.hidden = count < 2;
        dots.innerHTML = count < 2 ? '' : post.media.map((item, index) => `<button type="button" data-cm-dot="${index}"${index === viewer.index ? ' class="is-active" aria-current="true"' : ''} aria-label="${esc(fmt(S.media_position, index + 1, count))}"></button>`).join('');

        // Il prossimo media si prepara in anticipo.
        const upcoming = post.media[viewer.index + 1];
        if (upcoming && upcoming.kind !== 'video') new Image().src = upcoming.url;

        updateNav();
    };

    const linkify = (text) => esc(text).replace(/(^|[^\w@])@([A-Za-z0-9_]{3,20})/g, '$1<a href="/u/$2">@$2</a>');

    const renderSide = () => {
        const post = viewer.post;
        if (!post || !sideTop) return;

        const meta = [ago(post.ts)];
        if (typeof post.views === 'number') meta.push(`${num(post.views)} ${S.views}`);

        const actions = post.approved
            ? `<button type="button" class="cm-icon${post.saved ? ' is-on' : ''}" data-act="save" aria-pressed="${post.saved ? 'true' : 'false'}" aria-label="${esc(post.saved ? S.unsave : S.save)}"><i class="fa-${post.saved ? 'solid' : 'regular'} fa-bookmark" aria-hidden="true"></i></button>
               <button type="button" class="cm-icon" data-act="share" aria-label="${esc(S.share)}"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i></button>`
            : '';

        let engage = '';
        if (post.approved && isRimasto) {
            engage = `
                <div class="cm-vote">
                    <span class="cm-vote__n"><span class="cm-num">${num(post.score)}</span><small>${esc(post.score === 1 ? S.vote_one : S.votes)}</small></span>
                    <span class="cm-vote__gap"></span>
                    ${voteButton(post)}
                </div>`;
        } else if (post.approved) {
            engage = `<div class="cm-reacts" role="group" aria-label="${esc(S.reactions)}">${Object.entries(REACTIONS).map(([key, emoji]) => {
                const count = Number((post.reactions || {})[key] || 0);
                return `<button type="button" class="cm-react${post.my_reaction === key ? ' is-on' : ''}" data-act="react-key" data-key="${esc(key)}" aria-pressed="${post.my_reaction === key ? 'true' : 'false'}"><span class="cm-emo">${emoji}</span>${count ? `<span class="cm-num">${num(count)}</span>` : ''}</button>`;
            }).join('')}</div>`;
        }

        sideTop.innerHTML = `
            <div class="cm-who">
                <a class="cm-who__user" href="${esc(profileOf(post.author))}">
                    <img class="cm-avatar" src="/includes/get_pfp.php?id=${Number(post.author.id)}" alt="">
                    <span>
                        <span class="cm-who__name"><b>${esc(nameOf(post.author))}</b>${post.author.premium ? GEM : ''}${roleBadge(post.author)}</span>
                        <small>${esc(meta.join(' · '))}</small>
                    </span>
                </a>
                ${actions}
                <button type="button" class="cm-icon" data-act="menu" aria-label="${esc(S.more)}" aria-haspopup="menu"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
            </div>
            <h2 class="cm-side__title" id="cmViewerTitle" tabindex="-1">${esc(titleOf(post))}</h2>
            ${post.description ? `<p class="cm-side__desc">${esc(post.description)}</p>` : ''}
            ${post.extra ? `<p class="cm-quote" title="${esc(S.motivation)}">«${esc(post.extra)}»</p>` : ''}
            ${post.tags && post.tags.length ? `<div class="cm-side__tags">${post.tags.map((tag) => `<button type="button" class="cm-tag cm-tag--small" data-act="tag" data-tag="${esc(tag)}">#${esc(tag)}</button>`).join('')}</div>` : ''}
            ${post.approved ? '' : `<div class="cm-side__state"><i class="fa-solid fa-clock" aria-hidden="true"></i> ${esc(post.mine ? S.pending_own : S.pending_staff)}</div>`}
            ${engage}`;
    };

    const renderComments = () => {
        if (!commentsBox || !viewer.post) return;
        const all = viewer.comments;
        const roots = all.filter((comment) => !comment.parent || !all.some((other) => other.id === comment.parent));
        const replies = (id) => all.filter((comment) => comment.parent === id);

        const one = (comment, reply = false) => `
            <div class="cm-cm${reply ? ' cm-cm--reply' : ''}" data-comment="${comment.id}">
                <img class="cm-avatar" src="/includes/get_pfp.php?id=${Number(comment.author.id)}" alt="" loading="lazy">
                <div class="cm-cm__body">
                    <div class="cm-cm__head">
                        <a href="${esc(profileOf(comment.author))}">${esc(nameOf(comment.author))}</a>${comment.author.premium ? GEM : ''}${roleBadge(comment.author)}
                        <small>${esc(ago(comment.ts))}</small>
                    </div>
                    <p class="cm-cm__text">${linkify(comment.text)}</p>
                    <div class="cm-cm__acts">
                        ${me && viewer.post.approved && D.replies ? `<button type="button" data-comment-reply="${reply ? comment.parent : comment.id}" data-name="${esc(comment.author.username || '')}">${esc(S.reply)}</button>` : ''}
                        ${comment.can_delete ? `<button type="button" data-comment-delete="${comment.id}">${esc(S.delete)}</button>` : ''}
                    </div>
                </div>
            </div>`;

        const count = all.length;
        commentsBox.innerHTML = `
            <h3 class="cm-comments__head">${esc(count === 1 ? S.comments_count_one : fmt(S.comments_count_many, count))}</h3>
            ${count ? roots.map((root) => one(root) + replies(root.id).map((reply) => one(reply, true)).join('')).join('') : `<p class="cm-comments__none">${esc(S.comments_none)}</p>`}`;
    };

    const setReply = (target) => {
        viewer.replyTo = target;
        const banner = $('[data-cm-reply-banner]', viewerEl);
        if (!banner) return;
        banner.hidden = !target;
        if (target) {
            $('[data-cm-reply-label]', banner).textContent = fmt(S.replying_to, target.name || S.deleted_user);
            if (target.name && !commentInput.value.includes(`@${target.name}`)) commentInput.value = `@${target.name} ${commentInput.value}`;
            commentInput.focus();
            updateCommentCount();
        }
    };

    const updateCommentCount = () => {
        const counter = $('[data-cm-comment-count]', viewerEl);
        if (counter && commentInput) counter.textContent = `${commentInput.value.length}/${D.limits.comment}`;
    };

    const loadComments = async () => {
        const post = viewer.post;
        if (!post) return;
        const token = ++viewer.token;
        viewer.comments = [];
        commentsBox.innerHTML = `<h3 class="cm-comments__head">${esc(S.comments)}</h3>`;

        try {
            const data = await api(`get_comments.php?type=${type}&id=${post.id}`);
            if (token !== viewer.token) return;
            viewer.comments = data.comments || [];
            renderComments();
        } catch (error) {
            if (token !== viewer.token) return;
            commentsBox.innerHTML = `<p class="cm-comments__none">${esc(error.message)}</p>`;
        }
    };

    const applyComments = (comments) => {
        viewer.comments = comments || [];
        const post = viewer.post;
        if (post) {
            post.comments = viewer.comments.length;
            patch(post);
        }
        renderComments();
    };

    const renderViewer = () => {
        const post = viewer.post;
        if (!post) return;
        renderStage();
        renderSide();

        const canComment = !!me && post.approved;
        commentForm.hidden = !canComment;
        $('[data-cm-comment-login]', viewerEl).hidden = !!me || !post.approved;
        $('[data-cm-comment-note]', viewerEl).hidden = post.approved;

        window.__presencePost = {
            title: titleOf(post),
            image: post.media[0] && post.media[0].kind !== 'video' ? post.media[0].url : (post.media[0] && post.media[0].thumb) || null,
        };
    };

    const openPost = (post, { push = true, focusComments = false } = {}) => {
        if (!viewerEl || !post) return;
        const wasOpen = !!viewer.post;
        viewer.post = post;
        viewer.index = 0;
        setReply(null);
        if (commentInput) commentInput.value = '';
        updateCommentCount();

        showDialog(viewerEl);
        renderViewer();
        loadComments();
        queueView(post.id);
        $('[data-cm-side-scroll]', viewerEl).scrollTop = 0;

        if (push) {
            if (wasOpen || viewer.pushed) history.replaceState({ cmPost: post.id }, '', pageUrl(post.id));
            else {
                history.pushState({ cmPost: post.id }, '', pageUrl(post.id));
                viewer.pushed = true;
            }
        }

        if (focusComments && commentInput && !commentForm.hidden) commentInput.focus();
        else $('#cmViewerTitle', viewerEl)?.focus({ preventScroll: true });
    };

    const openById = async (id) => {
        const known = state.byId.get(id);
        if (known) {
            openPost(known);
            return;
        }
        try {
            const data = await api(`get_posts.php?type=${type}&post=${id}`);
            const post = (data.posts || [])[0];
            if (post) openPost(post);
            else toast(S.post_gone, true);
        } catch (error) {
            toast(error.message, true);
        }
    };

    const closeViewer = ({ fromHistory = false } = {}) => {
        if (!viewer.post) return;
        // L'audio si ferma subito; il media resta a vista finché la finestra si chiude.
        $$('video', stageMedia).forEach((video) => video.pause());
        setFull(false);

        viewer.post = null;
        viewer.token++;
        window.__presencePost = null;
        hideDialog(viewerEl).then(() => { if (!viewer.post) clearStage(); });

        if (fromHistory) {
            viewer.pushed = false;
        } else if (viewer.pushed) {
            viewer.pushed = false;
            history.back();
        } else {
            history.replaceState(null, '', pageUrl());
        }
    };

    /** Dentro al post: il file prima o dopo. Arrivati in fondo ci si ferma, non si cambia post. */
    const stepMedia = (direction) => {
        const post = viewer.post;
        if (!post || post.media.length < 2) return;

        const target = viewer.index + direction;
        if (target < 0 || target >= post.media.length) {
            // Un colpetto dice che i file sono finiti.
            if (reducedMotion) return;
            stageMedia.classList.remove('is-bump-next', 'is-bump-prev');
            void stageMedia.offsetWidth;
            stageMedia.classList.add(direction > 0 ? 'is-bump-next' : 'is-bump-prev');
            return;
        }
        viewer.index = target;
        renderStage(direction);
    };

    /** Il post prima o dopo, nell'ordine in cui sono in pagina; in fondo alla lista si carica la pagina dopo. */
    const stepPost = async (direction) => {
        if (!viewer.post || viewer.stepping) return;
        let position = listIndex();
        if (position < 0) return;

        if (direction > 0 && position === state.posts.length - 1 && state.page < state.pages) {
            viewer.stepping = true;
            try {
                await load(false);
            } finally {
                viewer.stepping = false;
            }
            if (!viewer.post) return;
            position = listIndex();
        }

        const next = state.posts[position + direction];
        if (next) openPost(next);
    };

    if (viewerEl) {
        // Esc: prima toglie lo zoom o lo schermo intero, poi chiude.
        viewerEl.addEventListener('cancel', (event) => {
            if (event.cancelable && (isZoomed() || isFull())) {
                event.preventDefault();
                if (isZoomed()) resetZoom(true);
                else setFull(false);
                return;
            }
            event.preventDefault();
            closeViewer();
        });
        viewerEl.addEventListener('click', (event) => {
            if (event.target === viewerEl) {
                closeViewer();
                return;
            }
            const target = event.target instanceof Element ? event.target : null;
            if (!target) return;

            if (target.closest('[data-cm-viewer-close]')) return closeViewer();
            const mediaStep = target.closest('[data-cm-media-step]');
            if (mediaStep) return stepMedia(Number(mediaStep.dataset.cmMediaStep));
            const postStep = target.closest('[data-cm-post-step]');
            if (postStep) return stepPost(Number(postStep.dataset.cmPostStep));
            const dot = target.closest('[data-cm-dot]');
            if (dot) {
                const index = Number(dot.dataset.cmDot);
                if (index === viewer.index) return undefined;
                const direction = index > viewer.index ? 1 : -1;
                viewer.index = index;
                return renderStage(direction);
            }
            if (target.closest('[data-cm-zoom]')) return toggleZoom();
            if (target.closest('[data-cm-fullscreen]')) return setFull(!isFull());
            if (target.closest('[data-cm-reply-cancel]')) return setReply(null);

            const replyButton = target.closest('[data-comment-reply]');
            if (replyButton) return setReply({ id: Number(replyButton.dataset.commentReply), name: replyButton.dataset.name || '' });

            const deleteButton = target.closest('[data-comment-delete]');
            if (deleteButton) {
                // Due clic: il primo chiede conferma sul bottone stesso.
                if (!deleteButton.classList.contains('is-armed')) {
                    deleteButton.classList.add('is-armed');
                    deleteButton.textContent = S.confirm_short;
                    setTimeout(() => {
                        if (deleteButton.isConnected) {
                            deleteButton.classList.remove('is-armed');
                            deleteButton.textContent = S.delete;
                        }
                    }, 3000);
                    return undefined;
                }
                api('delete_comment.php', { method: 'POST', body: { type, comment_id: Number(deleteButton.dataset.commentDelete) } })
                    .then((data) => { applyComments(data.comments); toast(S.comment_deleted); })
                    .catch((error) => toast(error.message, true));
                return undefined;
            }

            const actor = target.closest('[data-act]');
            if (actor && viewer.post) handleAct(actor, viewer.post, event);
            return undefined;
        });

        commentInput?.addEventListener('input', updateCommentCount);

        commentForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const post = viewer.post;
            const text = commentInput.value.trim();
            if (!post || !text) return;

            const submit = $('[type="submit"]', commentForm);
            submit.disabled = true;
            try {
                const data = await api('comment_post.php', { method: 'POST', body: { type, id: post.id, commento: text, parent: viewer.replyTo ? viewer.replyTo.id : 0 } });
                commentInput.value = '';
                updateCommentCount();
                setReply(null);
                applyComments(data.comments);
                const added = $(`[data-comment="${Number(data.comment_id)}"]`, commentsBox);
                if (added) added.scrollIntoView({ block: 'nearest', behavior: reducedMotion ? 'auto' : 'smooth' });
            } catch (error) {
                toast(error.message, true);
            } finally {
                submit.disabled = false;
            }
        });

        // Gesti sul media. Mouse: clic ingrandisce, trascinando ci si sposta.
        // Dito: doppio tocco e pizzico ingrandiscono; a immagine intera,
        // scorrere di lato passa al file prima o dopo.
        const pinchNow = () => {
            const [a, b] = Array.from(pointers.values());
            return { distance: Math.hypot(a.x - b.x, a.y - b.y) || 1, x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
        };

        stage.addEventListener('pointerdown', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const touch = event.pointerType !== 'mouse';
            if (!target || (!touch && event.button !== 0)) return;
            // I bottoni e la barra del player hanno i loro gesti.
            if (target.closest('button, .ep__bar, .ep__end')) return;

            const img = stageImage();
            const onImage = !!img && target === img;
            if (!onImage && !touch) return;

            const point = stagePoint(event);
            if (onImage) {
                pointers.set(event.pointerId, point);
                try {
                    img.setPointerCapture(event.pointerId);
                } catch (error) {
                    // il puntatore non c'è già più
                }
                if (pointers.size === 2) {
                    pinch = pinchNow();
                    drag = null;
                    return;
                }
                if (pointers.size > 2) return;
            }
            drag = { id: event.pointerId, x: point.x, y: point.y, zoomX: zoom.x, zoomY: zoom.y, moved: false, onImage, touch };
        });

        stage.addEventListener('pointermove', (event) => {
            if (pointers.has(event.pointerId)) pointers.set(event.pointerId, stagePoint(event));

            if (pinch && pointers.size >= 2) {
                const now = pinchNow();
                zoom.x += now.x - pinch.x;
                zoom.y += now.y - pinch.y;
                zoomTo(zoom.scale * (now.distance / pinch.distance), now.x, now.y);
                pinch = now;
                return;
            }
            if (!drag || drag.id !== event.pointerId) return;

            const point = stagePoint(event);
            const dx = point.x - drag.x;
            const dy = point.y - drag.y;
            if (!drag.moved && Math.hypot(dx, dy) < (drag.touch ? 10 : 4)) return;
            drag.moved = true;
            if (!drag.onImage || !isZoomed()) return;

            zoom.x = drag.zoomX + dx;
            zoom.y = drag.zoomY + dy;
            clampZoom(fitOf(stageImage()));
            stage.classList.add('is-panning');
            applyZoom();
        });

        const endPointer = (event) => {
            pointers.delete(event.pointerId);

            if (pinch) {
                if (pointers.size >= 2) return;
                pinch = null;
                if (zoom.scale < 1.06) resetZoom(true);
                // Il dito rimasto continua a spostare da dov'è.
                const [id, point] = pointers.entries().next().value || [];
                drag = point ? { id, x: point.x, y: point.y, zoomX: zoom.x, zoomY: zoom.y, moved: true, onImage: true, touch: true } : null;
                return;
            }
            if (!drag || drag.id !== event.pointerId) return;

            const done = drag;
            drag = null;
            stage.classList.remove('is-panning');
            if (event.type === 'pointercancel') return;

            const point = stagePoint(event);
            if (!done.moved) {
                if (!done.onImage) return;
                const now = performance.now();
                if (!done.touch) {
                    // Il secondo clic di un doppio clic non rifà il contrario del primo.
                    if (lastTap && now - lastTap.time < 350) return;
                    lastTap = { time: now, x: point.x, y: point.y };
                    toggleZoom(point.x, point.y);
                    return;
                }
                if (lastTap && now - lastTap.time < 320 && Math.hypot(point.x - lastTap.x, point.y - lastTap.y) < 36) {
                    lastTap = null;
                    toggleZoom(point.x, point.y);
                } else {
                    lastTap = { time: now, x: point.x, y: point.y };
                }
                return;
            }

            if (done.touch && !isZoomed()) {
                const dx = point.x - done.x;
                const dy = point.y - done.y;
                if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) stepMedia(dx < 0 ? 1 : -1);
            }
        };
        stage.addEventListener('pointerup', endPointer);
        stage.addEventListener('pointercancel', endPointer);

        stage.addEventListener('wheel', (event) => {
            if (!stageImage() || (event.target instanceof Element && event.target.closest('button'))) return;
            event.preventDefault();
            const unit = event.deltaMode === 1 ? 33 : (event.deltaMode === 2 ? 400 : 1);
            // Il pizzico sul trackpad arriva come rotella con Ctrl, a passi piccoli.
            const factor = Math.exp(-event.deltaY * unit * (event.ctrlKey ? 0.01 : 0.0022));
            const point = stagePoint(event);
            zoomTo(zoom.scale * Math.min(2, Math.max(0.5, factor)), point.x, point.y);
        }, { passive: false });

        // «load» ed «error» non salgono: si prendono in cattura.
        stageMedia.addEventListener('load', (event) => {
            const img = event.target;
            if (!(img instanceof HTMLImageElement) || !img.classList.contains('cm-stage__img')) return;
            stage.classList.remove('is-loading');
            img.style.maxWidth = `${img.naturalWidth * MAX_UPSCALE}px`;
            img.style.maxHeight = `${img.naturalHeight * MAX_UPSCALE}px`;

            const media = currentMedia();
            if (media && !(media.w && media.h) && img.naturalWidth) {
                media.w = img.naturalWidth;
                media.h = img.naturalHeight;
                setStageRatio(viewer.post);
            }
        }, true);

        stageMedia.addEventListener('error', (event) => {
            const img = event.target;
            if (!(img instanceof HTMLImageElement) || !img.classList.contains('cm-stage__img')) return;
            stage.classList.remove('is-loading');
            resetZoom();
            stageMedia.innerHTML = NO_IMAGE;
            $('[data-cm-zoom]', stage).hidden = true;
            $('[data-cm-fullscreen]', stage).hidden = !isFull();
        }, true);

        stageMedia.addEventListener('loadedmetadata', (event) => {
            const video = event.target;
            const media = currentMedia();
            if (!(video instanceof HTMLVideoElement) || !media || media.kind !== 'video' || (media.w && media.h) || !video.videoWidth) return;
            media.w = video.videoWidth;
            media.h = video.videoHeight;
            setStageRatio(viewer.post);
        }, true);

        stageMedia.addEventListener('animationend', (event) => {
            if (event.target === stageMedia) stageMedia.classList.remove('is-bump-next', 'is-bump-prev');
        });

        // Lo stage cambia misura (finestra, schermo intero): lo zoom resta dentro i bordi.
        if ('ResizeObserver' in window) {
            new ResizeObserver(() => {
                const img = stageImage();
                if (!img || !isZoomed()) return;
                clampZoom(fitOf(img));
                applyZoom();
            }).observe(stageMedia);
        }
    }

    window.addEventListener('popstate', () => {
        const id = Number(new URLSearchParams(location.search).get('post')) || 0;
        if (id && state.byId.has(id)) {
            viewer.pushed = true;
            openPost(state.byId.get(id), { push: false });
        } else if (viewer.post) {
            closeViewer({ fromHistory: true });
        }
    });

    // ── Nuovo post / modifica ───────────────────────────────────────────────
    const editor = { mode: 'new', post: null, files: [], tags: [], busy: false, seq: 0 };
    const ALLOWED = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm'];

    const editorForm = editorEl ? $('[data-cm-editor-form]', editorEl) : null;
    const tilesEl = editorEl ? $('[data-cm-tiles]', editorEl) : null;
    const dropEl = editorEl ? $('[data-cm-drop]', editorEl) : null;
    const fileInput = editorEl ? $('[data-cm-file]', editorEl) : null;
    const tagBox = editorEl ? $('[data-cm-tagbox]', editorEl) : null;
    const tagInput = editorEl ? $('[data-cm-tag-input]', editorEl) : null;
    const submitButton = editorEl ? $('[data-cm-editor-submit]', editorEl) : null;

    const cleanTag = (value) => {
        let tag = String(value || '').toLowerCase().trim().replace(/^#+/, '').replace(/\s+/g, '-');
        try {
            tag = tag.replace(/[^\p{L}\p{N}_-]/gu, '');
        } catch (error) {
            tag = tag.replace(/[^a-z0-9_\-àèéìòù]/g, '');
        }
        return tag.slice(0, D.limits.tag).replace(/^[-_]+|[-_]+$/g, '');
    };

    const renderTagBox = () => {
        if (!tagBox) return;
        $$('.cm-tag', tagBox).forEach((chip) => chip.remove());
        tagInput.insertAdjacentHTML('beforebegin', editor.tags.map((tag) => `<span class="cm-tag is-active">#${esc(tag)} <button type="button" data-tag-remove="${esc(tag)}" aria-label="${esc(S.delete)} #${esc(tag)}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></span>`).join(''));
        tagInput.hidden = editor.tags.length >= D.limits.tags;

        const hints = $('[data-cm-tag-hints]', editorEl);
        const free = state.tags.filter((tag) => !editor.tags.includes(tag)).slice(0, 6);
        hints.hidden = free.length === 0 || editor.tags.length >= D.limits.tags;
        hints.innerHTML = free.map((tag) => `<button type="button" class="cm-tag cm-tag--small" data-tag-add="${esc(tag)}">#${esc(tag)}</button>`).join('');
    };

    const addTag = (value) => {
        const tag = cleanTag(value);
        if (tag && !editor.tags.includes(tag) && editor.tags.length < D.limits.tags) editor.tags.push(tag);
        tagInput.value = '';
        renderTagBox();
    };

    const updateCounters = () => {
        $$('[data-cm-count]', editorEl).forEach((counter) => {
            const field = editorForm.elements[counter.dataset.cmCount];
            if (!field) return;
            const max = Number(field.getAttribute('maxlength')) || 0;
            counter.textContent = `${field.value.length}/${max}`;
        });
    };

    const overallProgress = () => {
        const bar = $('[data-cm-progress] b', editorEl);
        const active = editor.files.filter((item) => item.status === 'uploading' || item.status === 'queued');
        if (!bar) return;
        if (!active.length && !editor.busy) {
            bar.style.width = '0';
            return;
        }
        const total = editor.files.reduce((sum, item) => sum + (item.status === 'done' ? 1 : item.progress || 0), 0);
        bar.style.width = `${Math.round((total / Math.max(1, editor.files.length)) * 100)}%`;
    };

    const renderTiles = () => {
        if (!tilesEl) return;
        const count = editor.files.length;
        dropEl.classList.toggle('has-files', count > 0);
        tilesEl.hidden = count === 0;
        const canAdd = count > 0 && count < D.maxMedia;
        tilesEl.dataset.count = String(count + (canAdd ? 1 : 0));
        tilesEl.classList.toggle('is-dense', count + (canAdd ? 1 : 0) > 4);

        tilesEl.innerHTML = editor.files.map((item, index) => `
            <div class="cm-tile${item.status === 'done' ? ' is-done' : ''}${item.status === 'error' ? ' is-error' : ''}" data-tile="${item.key}">
                ${item.kind === 'video' ? `<video src="${esc(item.url)}#t=0.1" muted playsinline preload="metadata"></video>` : `<img src="${esc(item.url)}" alt="">`}
                ${index === 0 && count > 1 ? `<span class="cm-tile__label">${esc(S.cover)}</span>` : ''}
                <div class="cm-tile__tools">
                    ${count > 1 && index > 0 ? `<button type="button" data-tile-move="-1" aria-label="${esc(S.move_left)}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>` : ''}
                    ${count > 1 && index < count - 1 ? `<button type="button" data-tile-move="1" aria-label="${esc(S.move_right)}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>` : ''}
                    <button type="button" data-tile-remove aria-label="${esc(S.remove_file)}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <span class="cm-tile__meta">${esc(item.file.name)} · ${bytes(item.file.size)}</span>
                ${item.status === 'error' ? `<button type="button" class="cm-tile__retry" data-tile-retry><i class="fa-solid fa-rotate-right" aria-hidden="true"></i>${esc(item.error || S.err_upload_failed)}<span>${esc(S.upload_retry)}</span></button>` : ''}
                <span class="cm-tile__bar"><b style="width:${Math.round((item.progress || 0) * 100)}%"></b></span>
            </div>`).join('') + (canAdd ? `<button type="button" class="cm-tile cm-tile--add" data-cm-pick><i class="fa-solid fa-plus" aria-hidden="true"></i>${esc(S.add_more)}</button>` : '');

        overallProgress();
    };

    const tileProgress = (item) => {
        const bar = $(`[data-tile="${item.key}"] .cm-tile__bar b`, tilesEl);
        if (bar) bar.style.width = `${Math.round((item.progress || 0) * 100)}%`;
        overallProgress();
    };

    /** Di un video: misure, durata e un fotogramma da usare come copertina. */
    const inspectVideo = (file) => new Promise((resolve) => {
        const video = document.createElement('video');
        const url = URL.createObjectURL(file);
        let settled = false;

        const finish = (result) => {
            if (settled) return;
            settled = true;
            clearTimeout(timer);
            video.removeAttribute('src');
            video.load();
            URL.revokeObjectURL(url);
            resolve(result);
        };
        const timer = setTimeout(() => finish({}), 6000);
        const info = () => ({ width: video.videoWidth || 0, height: video.videoHeight || 0, duration: Number.isFinite(video.duration) ? Math.round(video.duration) : 0 });

        video.muted = true;
        video.playsInline = true;
        video.preload = 'auto';
        video.addEventListener('loadedmetadata', () => {
            try {
                // Un quarto del video: l'inizio è spesso nero o un titolo.
                video.currentTime = Math.min(3, Math.max(0.1, (video.duration || 1) * 0.25));
            } catch (error) {
                finish(info());
            }
        });
        video.addEventListener('seeked', () => {
            try {
                const scale = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight, 1));
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
                canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                canvas.toBlob((blob) => finish({ ...info(), poster: blob }), 'image/jpeg', 0.82);
            } catch (error) {
                finish(info());
            }
        });
        video.addEventListener('error', () => finish({}));
        video.src = url;
    });

    const send = (endpoint, formData, onProgress) => {
        const xhr = new XMLHttpRequest();
        const promise = new Promise((resolve, reject) => {
            xhr.open('POST', `/api/content/${endpoint}`);
            xhr.setRequestHeader('X-CSRF-Token', D.csrf || '');
            xhr.setRequestHeader('X-Requested-With', 'fetch');
            xhr.setRequestHeader('X-Cripsum-Lang', lang);
            xhr.upload.addEventListener('progress', (event) => { if (event.lengthComputable && onProgress) onProgress(event.loaded / event.total); });
            xhr.addEventListener('load', () => {
                let data = null;
                try { data = JSON.parse(xhr.responseText); } catch (error) { data = null; }
                if (xhr.status >= 200 && xhr.status < 300 && data && data.ok !== false) resolve(data);
                else reject(new Error((data && data.message) || (xhr.status === 413 ? S.err_video_size : S.err_upload_failed)));
            });
            xhr.addEventListener('error', () => reject(new Error(S.network_error)));
            xhr.addEventListener('abort', () => reject(new Error('abort')));
            xhr.send(formData);
        });
        return { xhr, promise };
    };

    const uploadFile = async (item) => {
        item.status = 'uploading';
        item.progress = 0;
        item.error = '';
        renderTiles();

        if (item.kind === 'video' && !item.inspected) {
            item.meta = await inspectVideo(item.file);
            item.inspected = true;
            if (!editor.files.includes(item)) return;
        }

        const body = new FormData();
        body.append('type', type);
        body.append('media', item.file);
        if (item.meta) {
            if (item.meta.poster) body.append('poster', item.meta.poster, 'poster.jpg');
            body.append('larghezza', String(item.meta.width || 0));
            body.append('altezza', String(item.meta.height || 0));
            body.append('durata', String(item.meta.duration || 0));
        }

        const { xhr, promise } = send('upload_media.php', body, (progress) => {
            item.progress = progress * 0.98;
            tileProgress(item);
        });
        item.xhr = xhr;

        try {
            const data = await promise;
            item.id = Number(data.id);
            item.status = 'done';
            item.progress = 1;
        } catch (error) {
            if (error.message === 'abort') return;
            item.status = 'error';
            item.error = error.message;
        }
        item.xhr = null;
        if (editor.files.includes(item)) renderTiles();
    };

    const addFiles = (list) => {
        const files = Array.from(list || []);
        if (!files.length || editor.mode !== 'new') return;

        for (const file of files) {
            if (!ALLOWED.includes(file.type)) {
                toast(S.err_type, true);
                continue;
            }
            const isVideo = file.type.startsWith('video/');
            if (file.size > (isVideo ? D.limits.video : D.limits.image)) {
                toast(isVideo ? S.err_video_size : S.err_image_size, true);
                continue;
            }
            if (D.maxMedia === 1 && editor.files.length) removeFile(editor.files[0]);
            if (editor.files.length >= D.maxMedia) {
                toast(fmt(S.err_too_many, D.maxMedia), true);
                break;
            }

            const item = { key: ++editor.seq, file, kind: isVideo ? 'video' : (file.type === 'image/gif' ? 'gif' : 'image'), url: URL.createObjectURL(file), status: D.drafts ? 'queued' : 'done', progress: D.drafts ? 0 : 1, id: 0, xhr: null, meta: null, inspected: false, error: '' };
            editor.files.push(item);
            if (D.drafts) uploadFile(item);
        }
        renderTiles();
    };

    const removeFile = (item) => {
        if (item.xhr) item.xhr.abort();
        if (item.id) api('discard_media.php', { method: 'POST', body: { ids: [item.id] } }).catch(() => {});
        URL.revokeObjectURL(item.url);
        editor.files = editor.files.filter((other) => other !== item);
        renderTiles();
    };

    const editorDirty = () => {
        if (editor.mode === 'new') return editor.files.length > 0 || editorForm.elements.titolo.value.trim() !== '' || editorForm.elements.descrizione.value.trim() !== '';
        const post = editor.post;
        return !!post && (editorForm.elements.titolo.value !== post.title || editorForm.elements.descrizione.value !== post.description
            || (isRimasto && editorForm.elements.motivazione.value !== post.extra)
            || editor.tags.join(',') !== (post.tags || []).join(',') || editorForm.elements.is_spoiler.checked !== !!post.spoiler);
    };

    const resetEditor = (discard) => {
        const drafts = editor.files.filter((item) => item.id).map((item) => item.id);
        editor.files.forEach((item) => {
            if (item.xhr) item.xhr.abort();
            URL.revokeObjectURL(item.url);
        });
        if (discard && drafts.length) api('discard_media.php', { method: 'POST', body: { ids: drafts } }).catch(() => {});
        editor.files = [];
        editor.tags = [];
        editor.post = null;
        editor.busy = false;
    };

    const openEditor = (post = null) => {
        if (!requireLogin() || !editorEl) return;
        resetEditor(true);
        editorForm.reset();
        editor.mode = post ? 'edit' : 'new';
        editor.post = post;
        editorEl.classList.toggle('is-edit', !!post);

        $('[data-cm-editor-title]', editorEl).textContent = post ? S.composer_edit_title : S.new;
        $('[data-cm-editor-hint]', editorEl).textContent = post ? S.composer_edit_hint : S.composer_hint;
        $('span', submitButton).textContent = post ? S.save_changes : S.publish;
        submitButton.disabled = false;

        let note = D.autoApprove ? S.review_note_auto : S.review_note;
        if (post) note = !isAdmin && !D.autoApprove && post.approved ? S.composer_review_note : '';
        const noteEl = $('[data-cm-editor-note]', editorEl);
        $('span', noteEl).textContent = note;
        noteEl.style.visibility = note ? 'visible' : 'hidden';

        if (post) {
            editorForm.elements.titolo.value = post.title || '';
            editorForm.elements.descrizione.value = post.description || '';
            if (isRimasto) editorForm.elements.motivazione.value = post.extra || '';
            editorForm.elements.is_spoiler.checked = !!post.spoiler;
            editor.tags = (post.tags || []).slice(0, D.limits.tags);
        }

        $$('.is-invalid', editorEl).forEach((el) => el.classList.remove('is-invalid'));
        renderTiles();
        renderTagBox();
        updateCounters();
        showDialog(editorEl);
        (post ? editorForm.elements.titolo : $('[data-cm-pick]', editorEl))?.focus();
    };

    const closeEditor = async (force = false) => {
        if (!editorEl || !editorEl.open) return;
        if (!force && editorDirty()) {
            const answer = await ask({ title: S.discard_title, text: S.discard_text, yes: S.discard_confirm });
            if (!answer) return;
        }
        resetEditor(true);
        hideDialog(editorEl);
    };

    const refreshPost = async (id) => {
        try {
            const data = await api(`get_posts.php?type=${type}&post=${id}`);
            return (data.posts || [])[0] || null;
        } catch (error) {
            return null;
        }
    };

    const submitEditor = async () => {
        if (editor.busy) return;

        const fields = editorForm.elements;
        const title = fields.titolo.value.trim();
        const invalid = (field, message) => {
            field.classList.add('is-invalid');
            field.focus();
            toast(message, true);
        };

        $$('.is-invalid', editorEl).forEach((el) => el.classList.remove('is-invalid'));
        if (tagInput.value.trim()) addTag(tagInput.value);

        if (editor.mode === 'new') {
            if (!editor.files.length) return toast(S.err_no_media, true);
            if (editor.files.some((item) => item.status === 'uploading' || item.status === 'queued')) return toast(S.err_upload_wait, true);
            if (editor.files.some((item) => item.status === 'error')) return toast(S.err_upload_failed, true);
        }
        if (!title) return invalid(fields.titolo, S.err_title);
        if (isRimasto && !fields.motivazione.value.trim()) return invalid(fields.motivazione, S.err_motivation);

        const payload = {
            type,
            titolo: title,
            descrizione: fields.descrizione.value.trim(),
            motivazione: isRimasto ? fields.motivazione.value.trim() : '',
            tags: editor.tags,
            is_spoiler: fields.is_spoiler.checked ? 1 : 0,
        };

        editor.busy = true;
        submitButton.disabled = true;
        const label = $('span', submitButton);
        const idle = label.textContent;
        label.textContent = S.sending;

        try {
            if (editor.mode === 'edit') {
                const post = editor.post;
                const data = await api('update_post.php', { method: 'POST', body: { ...payload, id: post.id } });
                resetEditor(false);
                await hideDialog(editorEl);
                toast(data.message);

                const fresh = await refreshPost(post.id);
                if (!fresh) return removePost(post.id);
                if (!post.approved === fresh.approved) state.pendingMine = Math.max(0, state.pendingMine + (fresh.approved ? -1 : 1));
                Object.assign(post, fresh);
                syncControls();

                const visible = fresh.approved || state.filter === 'mine' || state.filter === 'pending';
                if (viewer.post && viewer.post.id === post.id) {
                    if (visible || fresh.mine) renderViewer();
                    else closeViewer();
                }
                if (!visible) removePost(post.id);
                else renderAll();
                return undefined;
            }

            let data;
            if (D.drafts) {
                data = await api('create_post.php', { method: 'POST', body: { ...payload, media_ids: editor.files.map((item) => item.id) } });
            } else {
                // Senza la tabella dei media aggiuntivi: un file solo, nella stessa richiesta.
                const item = editor.files[0];
                if (item.kind === 'video' && !item.inspected) {
                    item.meta = await inspectVideo(item.file);
                    item.inspected = true;
                }
                const body = new FormData();
                Object.entries(payload).forEach(([key, value]) => body.append(key, Array.isArray(value) ? value.join(',') : String(value)));
                body.append('media', item.file);
                if (item.meta) {
                    if (item.meta.poster) body.append('poster', item.meta.poster, 'poster.jpg');
                    body.append('larghezza', String(item.meta.width || 0));
                    body.append('altezza', String(item.meta.height || 0));
                    body.append('durata', String(item.meta.duration || 0));
                }
                data = await send('create_post.php', body, (progress) => {
                    item.progress = progress;
                    label.textContent = fmt(S.uploading, Math.round(progress * 100));
                    tileProgress(item);
                }).promise;
            }

            resetEditor(false);
            await hideDialog(editorEl);
            toast(data.approved ? S.created_live : S.created_pending);

            state.q = '';
            state.tag = '';
            if (searchInput) searchInput.value = '';
            if (data.approved) {
                state.filter = '';
                state.sort = 'recent';
                state.period = 'all';
            } else {
                state.filter = 'mine';
                state.pendingMine += 1;
            }
            window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
            reload();
        } catch (error) {
            toast(error.message, true);
        } finally {
            editor.busy = false;
            if (editorEl.open) {
                submitButton.disabled = false;
                label.textContent = idle;
            }
        }
        return undefined;
    };

    if (editorEl) {
        editorForm.addEventListener('submit', (event) => { event.preventDefault(); submitEditor(); });
        editorForm.addEventListener('input', updateCounters);
        editorEl.addEventListener('cancel', (event) => { event.preventDefault(); closeEditor(); });

        editorEl.addEventListener('click', (event) => {
            if (event.target === editorEl) return closeEditor();
            const target = event.target instanceof Element ? event.target : null;
            if (!target) return undefined;

            if (target.closest('[data-cm-editor-close]')) return closeEditor();
            if (target.closest('[data-cm-pick]')) return fileInput.click();

            const tile = target.closest('[data-tile]');
            const item = tile ? editor.files.find((file) => file.key === Number(tile.dataset.tile)) : null;
            if (item && target.closest('[data-tile-remove]')) return removeFile(item);
            if (item && target.closest('[data-tile-retry]')) return uploadFile(item);
            const move = item ? target.closest('[data-tile-move]') : null;
            if (move) {
                const from = editor.files.indexOf(item);
                const to = from + Number(move.dataset.tileMove);
                if (to >= 0 && to < editor.files.length) {
                    editor.files.splice(from, 1);
                    editor.files.splice(to, 0, item);
                    renderTiles();
                }
                return undefined;
            }

            const remove = target.closest('[data-tag-remove]');
            if (remove) {
                editor.tags = editor.tags.filter((tag) => tag !== remove.dataset.tagRemove);
                renderTagBox();
                tagInput.focus();
                return undefined;
            }
            const add = target.closest('[data-tag-add]');
            if (add) return addTag(add.dataset.tagAdd);
            if (target === tagBox) tagInput.focus();
            return undefined;
        });

        fileInput.addEventListener('change', () => {
            addFiles(fileInput.files);
            fileInput.value = '';
        });

        ['dragenter', 'dragover'].forEach((name) => editorEl.addEventListener(name, (event) => {
            if (editor.mode !== 'new' || !event.dataTransfer || !Array.from(event.dataTransfer.types || []).includes('Files')) return;
            event.preventDefault();
            dropEl.classList.add('is-over');
        }));
        ['dragleave', 'drop'].forEach((name) => editorEl.addEventListener(name, (event) => {
            if (name === 'dragleave' && editorEl.contains(event.relatedTarget)) return;
            dropEl.classList.remove('is-over');
        }));
        editorEl.addEventListener('drop', (event) => {
            if (editor.mode !== 'new' || !event.dataTransfer || !event.dataTransfer.files.length) return;
            event.preventDefault();
            addFiles(event.dataTransfer.files);
        });

        tagInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ',' || event.key === ' ') {
                event.preventDefault();
                addTag(tagInput.value);
            } else if (event.key === 'Backspace' && tagInput.value === '' && editor.tags.length) {
                editor.tags.pop();
                renderTagBox();
            }
        });
        tagInput.addEventListener('blur', () => { if (tagInput.value.trim()) addTag(tagInput.value); });

        // Incollare un'immagine dagli appunti mentre la finestra è aperta.
        document.addEventListener('paste', (event) => {
            if (!editorEl.open || editor.mode !== 'new' || !event.clipboardData) return;
            const files = Array.from(event.clipboardData.files || []).filter((file) => ALLOWED.includes(file.type));
            if (!files.length) return;
            event.preventDefault();
            addFiles(files);
        });
    }

    // ── Clic sulla pagina ───────────────────────────────────────────────────
    function handleAct(actor, post, event) {
        const action = actor.dataset.act;

        if (action === 'open') {
            const card = actor.closest('.cm-post');
            if (card && card.classList.contains('is-spoiler') && !card.classList.contains('is-revealed') && actor.classList.contains('cm-post__media')) {
                state.revealed.add(post.id);
                card.classList.add('is-revealed');
                return;
            }
            openPost(post);
            return;
        }
        if (action === 'comments') return void openPost(post, { focusComments: true });
        if (action === 'react') {
            if (pressOpened) {
                pressOpened = false;
                return;
            }
            pop.close(pickerEl);
            react(post, null, actor);
            return;
        }
        if (action === 'react-key') return void react(post, actor.dataset.key, actor);
        if (action === 'vote') return void vote(post);
        if (action === 'save') return void save(post);
        if (action === 'share') return void share(post);
        if (action === 'menu') return void openMenu(post, actor);
        if (action === 'edit') return void openEditor(post);
        if (action === 'delete') return void deletePost(post);
        if (action === 'approve') return void setApproval(post, true);
        if (action === 'tag') {
            event.preventDefault();
            const tag = actor.dataset.tag || '';
            state.tag = state.tag === tag ? '' : tag;
            if (viewer.post) closeViewer();
            reload();
        }
    }

    app.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        if (target.closest('[data-cm-new]')) return void openEditor();

        const tab = target.closest('[data-cm-sorts] .cm-seg__tab');
        if (tab) {
            state.sort = tab.dataset.sort;
            state.period = tab.dataset.period || 'all';
            if (state.filter === 'mine') state.filter = '';
            return void reload();
        }

        const filter = target.closest('[data-cm-filter]');
        if (filter) {
            state.filter = state.filter === filter.dataset.cmFilter ? '' : filter.dataset.cmFilter;
            return void reload();
        }

        if (target.closest('[data-cm-search-clear]')) {
            searchInput.value = '';
            state.q = '';
            searchInput.focus();
            return void reload();
        }

        if (target.closest('[data-cm-newpill]')) {
            state.filter = '';
            state.q = '';
            state.tag = '';
            state.sort = 'recent';
            state.period = 'all';
            if (searchInput) searchInput.value = '';
            window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
            return void reload();
        }

        const actor = target.closest('[data-act]');
        if (!actor) return;
        const action = actor.dataset.act;

        if (action === 'retry') return void reload();
        if (action === 'reset') {
            state.filter = '';
            state.q = '';
            state.tag = '';
            if (searchInput) searchInput.value = '';
            return void reload();
        }
        if (action === 'open-id') return void openById(Number(actor.dataset.id));
        if (action === 'tag' && !actor.closest('[data-post]')) {
            const tag = actor.dataset.tag || '';
            state.tag = state.tag === tag ? '' : tag;
            return void reload();
        }

        const post = state.byId.get(Number(actor.closest('[data-post]')?.dataset.post));
        if (post) handleAct(actor, post, event);
    });

    // ── Ricerca e tastiera ──────────────────────────────────────────────────
    let searchTimer = 0;
    searchInput?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchInput.closest('.cm-search')?.classList.toggle('has-value', searchInput.value !== '');
        searchTimer = setTimeout(() => {
            const value = searchInput.value.trim();
            if (value === state.q) return;
            state.q = value;
            reload();
        }, 320);
    });

    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && searchInput.value) {
            searchInput.value = '';
            state.q = '';
            reload();
        }
    });

    document.addEventListener('keydown', (event) => {
        const typing = event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable]');

        // Post aperto: ← → i file del post, ↑ ↓ il post prima o dopo, F schermo
        // intero. Sui video spazio, M, F, J/L e < > sono del player.
        if (viewer.post && topDialog() === viewerEl && !typing) {
            const target = event.target instanceof Element ? event.target : null;
            // Menu aperto, barre del player: le frecce lì fanno già altro.
            if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) return;
            if (target && target.closest('.cm-menu, .cm-picker, [role="slider"], [role="listbox"]')) return;
            if (viewer.player && viewer.player.handleKey(event)) return;

            const steps = { ArrowRight: () => stepMedia(1), ArrowLeft: () => stepMedia(-1), ArrowDown: () => stepPost(1), ArrowUp: () => stepPost(-1) };
            if (steps[event.key]) {
                event.preventDefault();
                steps[event.key]();
            } else if ((event.key === 'f' || event.key === 'F') && stageImage()) {
                event.preventDefault();
                setFull(!isFull());
            }
            return;
        }
        if (event.key === '/' && !typing && !topDialog() && searchInput && !event.ctrlKey && !event.metaKey && !event.altKey) {
            event.preventDefault();
            searchInput.focus();
        }
    });

    // ── Colonne che seguono la larghezza ────────────────────────────────────
    if ('ResizeObserver' in window) {
        let resizeTimer = 0;
        new ResizeObserver(() => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (isRanked() || !columns.length || wantedColumns() === columnCount) return;
                buildColumns();
                appendCards(state.posts, false);
            }, 120);
        }).observe(app);
    }

    // ── «Nuovi post» ────────────────────────────────────────────────────────
    function hideNewPill() {
        if (newPill) newPill.hidden = true;
    }

    const checkPulse = async () => {
        if (document.hidden || !newPill || topDialog()) return;
        try {
            const response = await fetch(`/api/content/pulse.php?type=${type}`, { cache: 'no-store' });
            const data = await response.json();
            const fresh = (data.posts || []).filter((post) => post.at > state.seenAt && (!me || post.u !== me.id) && !state.byId.has(post.id));
            if (!fresh.length) return;
            newPill.innerHTML = `<i class="fa-solid fa-arrow-up" aria-hidden="true"></i> ${esc(fresh.length === 1 ? S.new_posts_one : fmt(S.new_posts_many, fresh.length))}`;
            newPill.hidden = false;
        } catch (error) {
            // Si riprova al prossimo giro.
        }
    };

    setInterval(checkPulse, 60000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) checkPulse(); });

    // ── Avvio ───────────────────────────────────────────────────────────────
    state.failed = !!D.failed;
    setPosts(Array.isArray(D.feed?.posts) ? D.feed.posts : []);
    syncControls();
    renderAll();

    if (D.post) {
        const known = state.byId.get(D.post.id);
        openPost(known || D.post, { push: false });
    } else if (D.postMissing) {
        toast(S.post_gone, true);
        history.replaceState(null, '', pageUrl());
    }
})();
