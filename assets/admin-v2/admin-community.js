/*
 * Pannello admin: Shitpost e Top Rimasti (gruppo "Community").
 *
 * Le due sezioni sono la stessa cosa su due tabelle: una coda a sinistra
 * (In attesa / Pubblicati / Segnalati) e a destra l'anteprima del post
 * scelto, con i media in grande, lo storico dell'autore, le segnalazioni, i
 * commenti e le azioni. Si lavora anche da tastiera: frecce per scorrere la
 * coda, A per approvare, R per rifiutare, spazio per selezionare.
 *
 * Le azioni distruttive si confermano con un secondo clic sullo stesso
 * bottone, senza finestre sovrapposte. Tutto passa da /api/admin/community.php.
 */
(() => {
    'use strict';

    const A = window.CripsumAdmin;
    if (!A) return;

    const { api, openModal, closeModal, showToast, emptyState } = A;
    const e = A.escapeHtml;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const SECTIONS = { shitposts: 'shitpost', toprimasti: 'rimasto' };
    const PAGES = { shitpost: '/it/shitpost', rimasto: '/it/rimasti' };
    const REASONS = [['', 'Nessuno'], ['duplicate', 'Doppione'], ['quality', 'Bassa qualità'], ['offtopic', 'Fuori tema'], ['rules', 'Contenuto vietato'], ['other', 'Altro…']];
    const TABS = [['pending', 'In attesa'], ['approved', 'Pubblicati'], ['reported', 'Segnalati']];
    const SORTS = {
        recent: ['fa-solid fa-arrow-down-wide-short', 'Più recenti'],
        oldest: ['fa-solid fa-arrow-up-wide-short', 'Più vecchi'],
        top: ['fa-solid fa-fire', 'Più votati'],
        comments: ['fa-solid fa-comments', 'Più commentati'],
    };

    const newState = () => ({ tab: 'pending', sort: '', page: 1, pages: 1, posts: [], selected: 0, checked: new Set(), detail: null, media: 0, reason: '', reasonText: '', loading: false, token: 0 });
    const states = { shitpost: newState(), rimasto: newState() };
    let counts = { shitpost: { pending: 0, reported: 0 }, rimasto: { pending: 0, reported: 0 } };
    let activeType = null;

    const rootOf = (type) => $(`[data-community-admin="${type}"]`);
    const sortOf = (state) => state.sort || (state.tab === 'pending' ? 'oldest' : 'recent');
    const get = (params) => api(`community.php?${new URLSearchParams(params)}`);
    const post = (action, type, body = {}) => api('community.php', { method: 'POST', body: { action, type, ...body } });

    const relative = new Intl.RelativeTimeFormat('it', { numeric: 'auto', style: 'short' });
    const ago = (ts) => {
        const seconds = Math.max(0, Date.now() / 1000 - Number(ts || 0));
        if (seconds < 60) return 'adesso';
        if (seconds < 3600) return relative.format(-Math.round(seconds / 60), 'minute');
        if (seconds < 86400) return relative.format(-Math.round(seconds / 3600), 'hour');
        if (seconds < 30 * 86400) return relative.format(-Math.round(seconds / 86400), 'day');
        return new Date(Number(ts) * 1000).toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric' });
    };

    const size = (bytes) => {
        bytes = Number(bytes) || 0;
        if (!bytes) return '';
        return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1).replace('.', ',')} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
    };

    const kindLabel = (mime) => String(mime || '').split('/')[1]?.toUpperCase().replace('JPEG', 'JPG') || '';
    const reasonValue = (state) => (state.reason === 'other' ? state.reasonText.trim() : state.reason);

    // ── Contatori nel menu laterale ─────────────────────────────────────────
    const paintCounts = () => {
        Object.entries(SECTIONS).forEach(([section, type]) => {
            const button = $(`[data-admin-nav] [data-section="${section}"]`);
            if (!button) return;
            let badge = $('.cmod-navcount', button);
            const pending = Number(counts[type]?.pending) || 0;
            if (!pending) {
                badge?.remove();
                return;
            }
            if (!badge) {
                badge = document.createElement('em');
                badge.className = 'cmod-navcount';
                button.appendChild(badge);
            }
            badge.textContent = pending > 99 ? '99+' : String(pending);
            badge.title = `${pending} in attesa`;
        });
    };

    const setCounts = (next) => {
        if (next) counts = next;
        paintCounts();
        Object.values(SECTIONS).forEach((type) => {
            const root = rootOf(type);
            if (root && $('.cmod-tabs', root)) paintTabs(type);
        });
    };

    // ── Disegno ─────────────────────────────────────────────────────────────
    const thumbOf = (item) => {
        const media = item.media && item.media[0];
        if (!media) return '<span class="cmod-thumb cmod-thumb--empty"><i class="fa-solid fa-image"></i></span>';
        if (media.thumb) return `<span class="cmod-thumb"><img src="${e(media.thumb)}" alt="" loading="lazy">${media.kind === 'video' ? '<i class="fa-solid fa-play"></i>' : ''}</span>`;
        return '<span class="cmod-thumb cmod-thumb--empty"><i class="fa-solid fa-video"></i></span>';
    };

    function paintTabs(type) {
        const state = states[type];
        const root = rootOf(type);
        const box = $('.cmod-tabs', root);
        if (!box) return;
        box.innerHTML = TABS.map(([tab, label]) => {
            const count = tab === 'pending' ? counts[type]?.pending : (tab === 'reported' ? counts[type]?.reported : 0);
            return `<button type="button" role="tab" class="${tab === state.tab ? 'is-active' : ''}" aria-selected="${tab === state.tab}" data-cmod-tab="${tab}">${label}${count ? `<em class="${tab === 'reported' ? 'is-red' : ''}">${Number(count)}</em>` : ''}</button>`;
        }).join('');
    }

    const queueHtml = (type) => {
        const state = states[type];
        if (state.loading && !state.posts.length) return '<p class="admin-muted cmod-note">Caricamento…</p>';
        if (!state.posts.length) {
            const empty = { pending: ['fa-solid fa-circle-check', 'Niente da approvare', 'La coda è vuota.'], approved: ['fa-solid fa-image', 'Nessun post pubblicato', ''], reported: ['fa-solid fa-flag', 'Nessuna segnalazione aperta', ''] }[state.tab];
            return emptyState(empty[0], empty[1], A.getQuery() ? 'Nessun risultato per questa ricerca.' : empty[2]);
        }

        return state.posts.map((item) => {
            const admin = item.admin || {};
            const flags = [];
            if (admin.reports > 0) flags.push(`<span class="cmod-flag is-red"><i class="fa-solid fa-flag"></i> ${Number(admin.reports)}</span>`);
            if (state.tab === 'pending' && admin.author_today >= 2) flags.push(`<span class="cmod-flag"><i class="fa-solid fa-triangle-exclamation"></i> ${Number(admin.author_today)}° invio oggi</span>`);
            if (!item.approved && state.tab !== 'pending') flags.push('<span class="cmod-flag"><i class="fa-solid fa-clock"></i> in attesa</span>');
            if (item.media && item.media.length > 1) flags.push(`<span class="cmod-flag is-plain"><i class="fa-solid fa-clone"></i> ${item.media.length}</span>`);
            if (item.spoiler) flags.push('<span class="cmod-flag is-plain">spoiler</span>');

            return `
                <div class="cmod-item${item.id === state.selected ? ' is-active' : ''}" data-cmod-item="${item.id}" role="button" tabindex="0">
                    <button type="button" class="cmod-check${state.checked.has(item.id) ? ' is-on' : ''}" role="checkbox" aria-checked="${state.checked.has(item.id)}" data-cmod-check="${item.id}" aria-label="Seleziona"><i class="fa-solid fa-check"></i></button>
                    ${thumbOf(item)}
                    <div class="cmod-item__text">
                        <b>${e(item.title || 'Senza titolo')}</b>
                        <small>${e(item.author.username || 'utente eliminato')} · ${e(ago(item.ts))}${admin.mime ? ` · ${e(kindLabel(admin.mime))} ${e(size(admin.bytes))}` : ''}</small>
                        ${flags.length ? `<div class="cmod-flags">${flags.join('')}</div>` : ''}
                    </div>
                </div>`;
        }).join('');
    };

    const bulkHtml = (type) => {
        const state = states[type];
        const n = state.checked.size;
        if (!n) return '';
        const reason = REASONS.find(([value]) => value === state.reason);
        return `
            <div class="cmod-bulk">
                <span>${n} selezionat${n === 1 ? 'o' : 'i'}</span>
                <small>${state.reason ? `Motivo: ${e(state.reason === 'other' ? (state.reasonText.trim() || 'da scrivere') : reason[1])}` : ''}</small>
                <span class="cmod-gap"></span>
                ${state.tab !== 'approved' ? '<button type="button" class="admin-btn admin-btn--small cmod-ok" data-cmod-bulk="approve"><i class="fa-solid fa-check"></i> Approva</button>' : ''}
                ${state.tab === 'approved' ? '<button type="button" class="admin-btn admin-btn--small" data-cmod-bulk="hide" data-cmod-arm><i class="fa-solid fa-eye-slash"></i> Nascondi</button>' : ''}
                ${state.tab === 'reported' ? '<button type="button" class="admin-btn admin-btn--small" data-cmod-bulk="dismiss"><i class="fa-solid fa-flag"></i> Ignora</button>' : ''}
                <button type="button" class="admin-btn admin-btn--small cmod-no" data-cmod-bulk="delete" data-cmod-arm><i class="fa-solid ${state.tab === 'pending' ? 'fa-xmark' : 'fa-trash'}"></i> ${state.tab === 'pending' ? 'Rifiuta' : 'Elimina'}</button>
            </div>`;
    };

    const pagesHtml = (state) => (state.pages > 1 ? `
        <div class="cmod-pages">
            <button type="button" class="admin-btn admin-btn--small" data-cmod-page="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''} aria-label="Pagina precedente"><i class="fa-solid fa-chevron-left"></i></button>
            <span>${state.page} / ${state.pages}</span>
            <button type="button" class="admin-btn admin-btn--small" data-cmod-page="${state.page + 1}" ${state.page >= state.pages ? 'disabled' : ''} aria-label="Pagina successiva"><i class="fa-solid fa-chevron-right"></i></button>
        </div>` : '');

    const mediaHtml = (state, item) => {
        const list = item.media || [];
        const media = list[state.media] || list[0];
        if (!media) return '<div class="cmod-stage"><span class="admin-muted"><i class="fa-solid fa-image"></i> Nessun media</span></div>';

        const view = media.kind === 'video'
            ? `<video src="${e(media.url)}" ${media.thumb ? `poster="${e(media.thumb)}"` : ''} controls playsinline preload="metadata"></video>`
            : `<img src="${e(media.url)}" alt="">`;

        const strip = list.length > 1 ? `<div class="cmod-strip">${list.map((entry, index) => `
            <button type="button" class="${index === state.media ? 'is-active' : ''}" data-cmod-media="${index}" aria-label="Media ${index + 1}">
                ${entry.thumb ? `<img src="${e(entry.thumb)}" alt="">` : '<i class="fa-solid fa-video"></i>'}
            </button>`).join('')}</div>` : '';

        return `<div class="cmod-stage">${view}</div>${strip}`;
    };

    const reasonsHtml = (state, label) => `
        <div class="cmod-why">
            <label>${label}</label>
            <div class="cmod-chips">
                ${REASONS.map(([value, text]) => `<button type="button" class="${state.reason === value ? 'is-active' : ''}" data-cmod-reason="${value}">${text}</button>`).join('')}
            </div>
            <input type="text" class="admin-input" data-cmod-reason-text maxlength="300" placeholder="Scrivi il motivo" value="${e(state.reasonText)}" ${state.reason === 'other' ? '' : 'hidden'}>
        </div>`;

    const previewHtml = (type) => {
        const state = states[type];
        const detail = state.detail;
        if (!state.selected) return `<div class="cmod-preview cmod-preview--empty">${emptyState('fa-solid fa-hand-pointer', 'Scegli un post', 'Qui compaiono media, autore, segnalazioni e azioni.')}</div>`;
        if (!detail || detail.post.id !== state.selected) return '<div class="cmod-preview cmod-preview--empty"><p class="admin-muted cmod-note">Caricamento…</p></div>';

        const item = detail.post;
        const author = detail.author || {};
        const open = (detail.reports || []).filter((report) => report.status === 'open');
        const isRimasto = type === 'rimasto';
        const scoreLabel = isRimasto ? 'Voti' : 'Reazioni';
        const reactions = !isRimasto && item.reactions ? Object.entries(item.reactions).map(([key, n]) => `${key} ${n}`).join(' · ') : '';

        return `
            <div class="cmod-preview">
                <div class="cmod-media">${mediaHtml(state, item)}</div>
                <div class="cmod-info">
                    <div class="cmod-author">
                        <img class="admin-avatar" src="/includes/get_pfp.php?id=${Number(item.author.id)}" alt="">
                        <div>
                            <b>${e(item.author.username || 'utente eliminato')}${author.banned ? ' <span class="cmod-flag is-red">bannato</span>' : ''}</b>
                            <small>${e(ago(item.ts))} · #${item.id}</small>
                        </div>
                    </div>

                    <h3>${e(item.title || 'Senza titolo')}</h3>
                    <p class="cmod-desc">${item.description ? e(item.description) : '<span class="admin-muted">Nessuna descrizione.</span>'}</p>
                    ${item.extra ? `<p class="cmod-quote">«${e(item.extra)}»</p>` : ''}
                    ${(item.tags || []).length || item.spoiler ? `<div class="cmod-tags">${(item.tags || []).map((tag) => `<span>#${e(tag)}</span>`).join('')}${item.spoiler ? '<span>spoiler</span>' : ''}</div>` : ''}

                    <dl class="cmod-kv">
                        ${item.approved ? `<dt>${scoreLabel}</dt><dd title="${e(reactions)}">${Number(item.score)}</dd><dt>Commenti</dt><dd>${Number(item.comments)}</dd><dt>Visite</dt><dd>${Number(item.views || 0)}</dd>` : ''}
                        <dt>Post approvati dell'autore</dt><dd>${Number(author.approved || 0)}</dd>
                        <dt>Suoi post in attesa</dt><dd>${Number(author.pending || 0)}</dd>
                        <dt>Segnalazioni ricevute</dt><dd>${Number(author.reports || 0)}</dd>
                        <dt>Iscritto dal</dt><dd>${author.since ? e(new Date(String(author.since).replace(' ', 'T')).toLocaleDateString('it-IT', { month: 'short', year: 'numeric' })) : '—'}</dd>
                    </dl>

                    ${detail.reports && detail.reports.length ? `
                        <div class="cmod-box">
                            <div class="cmod-box__head"><b>Segnalazioni</b>${open.length ? '<button type="button" class="admin-btn admin-btn--small" data-cmod-do="dismiss">Ignora</button>' : ''}</div>
                            ${detail.reports.map((report) => `<p class="${report.status === 'open' ? '' : 'is-done'}"><b>${e(report.username || 'utente eliminato')}</b> · ${e(report.reason)}</p>`).join('')}
                        </div>` : ''}

                    ${detail.comments && detail.comments.length ? `
                        <details class="cmod-box">
                            <summary><b>Commenti (${detail.comments.length})</b></summary>
                            ${detail.comments.map((comment) => `
                                <p class="cmod-comment${comment.parent ? ' is-reply' : ''}">
                                    <span><b>${e(comment.author.username || 'utente eliminato')}</b> ${e(comment.text)}</span>
                                    <button type="button" class="admin-btn admin-btn--small" data-cmod-comment="${comment.id}" data-cmod-arm aria-label="Elimina commento"><i class="fa-solid fa-trash"></i></button>
                                </p>`).join('')}
                        </details>` : ''}

                    <span class="cmod-gap"></span>
                    ${reasonsHtml(state, item.approved ? 'Motivo della rimozione (arriva all\'autore)' : 'Motivo del rifiuto (arriva all\'autore)')}
                    <div class="cmod-actions">
                        ${item.approved
                            ? '<button type="button" class="admin-btn" data-cmod-do="hide" data-cmod-arm><i class="fa-solid fa-eye-slash"></i> Nascondi</button>'
                            : '<button type="button" class="admin-btn cmod-ok" data-cmod-do="approve"><i class="fa-solid fa-check"></i> Approva <kbd>A</kbd></button>'}
                        <button type="button" class="admin-btn cmod-no" data-cmod-do="delete" data-cmod-arm><i class="fa-solid ${item.approved ? 'fa-trash' : 'fa-xmark'}"></i> ${item.approved ? 'Elimina' : 'Rifiuta'} <kbd>R</kbd></button>
                    </div>
                    <div class="cmod-actions cmod-actions--minor">
                        <button type="button" class="admin-btn admin-btn--small" data-cmod-do="edit"><i class="fa-solid fa-pen"></i> Modifica</button>
                        ${item.author.id ? `<button type="button" class="admin-btn admin-btn--small" data-open-user="${Number(item.author.id)}"><i class="fa-solid fa-user"></i> Scheda utente</button>` : ''}
                        ${isRimasto && item.approved ? '<button type="button" class="admin-btn admin-btn--small" data-cmod-do="reset" data-cmod-arm><i class="fa-solid fa-rotate-left"></i> Azzera voti</button>' : ''}
                        ${item.approved ? `<a class="admin-btn admin-btn--small" href="${e(item.url)}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Apri sul sito</a>` : ''}
                    </div>
                </div>
            </div>`;
    };

    const paintQueue = (type) => {
        const root = rootOf(type);
        const state = states[type];
        $('.cmod-queue__list', root).innerHTML = queueHtml(type);
        $('.cmod-queue__foot', root).innerHTML = pagesHtml(state) + bulkHtml(type);
    };

    const paintPreview = (type) => {
        const slot = $('.cmod-slot', rootOf(type));
        // Un video che sta andando non si ridisegna per un cambio di motivo.
        slot.innerHTML = previewHtml(type);
    };

    const paintSort = (type) => {
        const state = states[type];
        const [icon, label] = SORTS[sortOf(state)];
        $('[data-cmod-sort]', rootOf(type)).innerHTML = `<i class="${icon}"></i> ${label}`;
    };

    const shell = (type) => {
        const root = rootOf(type);
        if (!root || root.dataset.ready === '1') return;
        root.dataset.ready = '1';
        root.innerHTML = `
            <div class="cmod-head">
                <div class="cmod-tabs" role="tablist"></div>
                <span class="cmod-gap"></span>
                <button type="button" class="admin-btn admin-btn--small" data-cmod-sort></button>
                <a class="admin-btn admin-btn--small" href="${PAGES[type]}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Apri la pagina</a>
                <button type="button" class="admin-btn admin-btn--small" data-cmod-rules><i class="fa-solid fa-sliders"></i> Regole</button>
            </div>
            <div class="cmod-split">
                <div class="cmod-queue"><div class="cmod-queue__list"></div><div class="cmod-queue__foot"></div></div>
                <div class="cmod-slot"></div>
            </div>
            <p class="cmod-keys admin-muted"><kbd>↑</kbd><kbd>↓</kbd> scorri · <kbd>Spazio</kbd> seleziona · <kbd>A</kbd> approva · <kbd>R</kbd> rifiuta (due volte)</p>`;
        bind(type);
    };

    // ── Dati ────────────────────────────────────────────────────────────────
    const loadDetail = async (type) => {
        const state = states[type];
        const id = state.selected;
        if (!id) {
            state.detail = null;
            paintPreview(type);
            return;
        }
        if (!state.detail || state.detail.post.id !== id) paintPreview(type);

        try {
            const data = await get({ action: 'post', type, id });
            if (state.selected !== id) return;
            state.detail = data;
            state.media = Math.min(state.media, Math.max(0, (data.post.media || []).length - 1));
            paintPreview(type);
        } catch (error) {
            if (state.selected !== id) return;
            $('.cmod-slot', rootOf(type)).innerHTML = `<div class="cmod-preview cmod-preview--empty">${emptyState('fa-solid fa-triangle-exclamation', 'Post non disponibile', error.message)}</div>`;
        }
    };

    const select = (type, id, focus = false) => {
        const state = states[type];
        if (state.selected !== id) {
            state.selected = id;
            state.media = 0;
        }
        $$('.cmod-item', rootOf(type)).forEach((el) => el.classList.toggle('is-active', Number(el.dataset.cmodItem) === id));
        if (focus) $(`.cmod-item[data-cmod-item="${id}"]`, rootOf(type))?.scrollIntoView({ block: 'nearest' });
        loadDetail(type);
    };

    const load = async (type, keep = true) => {
        const state = states[type];
        const token = ++state.token;
        state.loading = true;
        shell(type);
        paintTabs(type);
        paintSort(type);
        if (!state.posts.length) paintQueue(type);

        try {
            const data = await get({ action: 'list', type, tab: state.tab, sort: sortOf(state), page: state.page, q: A.getQuery() || '' });
            if (token !== state.token) return;

            state.loading = false;
            state.posts = data.posts || [];
            state.pages = Number(data.pagination?.pages) || 1;
            state.page = Math.min(state.page, state.pages);
            const ids = new Set(state.posts.map((item) => item.id));
            state.checked = new Set([...state.checked].filter((id) => ids.has(id)));
            setCounts(data.counts);

            if (!keep || !ids.has(state.selected)) {
                state.selected = state.posts[0]?.id || 0;
                state.media = 0;
            }
            paintQueue(type);
            loadDetail(type);
        } catch (error) {
            if (token !== state.token) return;
            state.loading = false;
            $('.cmod-queue__list', rootOf(type)).innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
        }
    };

    // ── Azioni ──────────────────────────────────────────────────────────────
    /** Primo clic: il bottone chiede conferma. Secondo clic entro tre secondi: si procede. */
    const armed = (button) => {
        if (!button || !button.hasAttribute('data-cmod-arm')) return true;
        if (button.dataset.armed === '1') {
            delete button.dataset.armed;
            return true;
        }
        const html = button.innerHTML;
        button.dataset.armed = '1';
        button.classList.add('is-armed');
        button.innerHTML = '<i class="fa-solid fa-circle-question"></i> Sicuro?';
        setTimeout(() => {
            if (!button.isConnected || button.dataset.armed !== '1') return;
            delete button.dataset.armed;
            button.classList.remove('is-armed');
            button.innerHTML = html;
        }, 3000);
        return false;
    };

    /** Dopo un'azione la selezione passa al post che segue quello appena trattato. */
    const nextAfter = (state, ids) => {
        const gone = new Set(ids);
        const index = state.posts.findIndex((item) => item.id === state.selected);
        const following = state.posts.slice(index + 1).find((item) => !gone.has(item.id));
        const previous = state.posts.slice(0, Math.max(0, index)).reverse().find((item) => !gone.has(item.id));
        return (following || previous || {}).id || 0;
    };

    const run = async (type, action, ids, button) => {
        const state = states[type];
        if (!ids.length) return;
        if (action === 'delete' && state.reason === 'other' && !state.reasonText.trim()) {
            showToast('Scrivi il motivo, o scegline uno pronto.', true);
            $('[data-cmod-reason-text]', rootOf(type))?.focus();
            return;
        }
        if (!armed(button)) return;

        try {
            const leaves = action === 'delete' || (action === 'approve' && state.tab === 'pending') || (action === 'hide' && state.tab === 'approved') || (action === 'dismiss' && state.tab === 'reported');
            const next = leaves && ids.includes(state.selected) ? nextAfter(state, ids) : state.selected;

            let data;
            if (action === 'dismiss') {
                for (const id of ids) data = await post('dismiss_reports', type, { id });
            } else {
                data = await post(action, type, { ids, reason: action === 'delete' ? reasonValue(state) : '' });
            }

            showToast(data.message);
            ids.forEach((id) => state.checked.delete(id));
            if (leaves) {
                state.selected = next;
                state.media = 0;
            }
            state.detail = null;
            load(type);
        } catch (error) {
            showToast(error.message, true);
        }
    };

    const openEdit = (type) => {
        const state = states[type];
        const item = state.detail?.post;
        if (!item) return;

        openModal('Modifica post', `#${item.id} · ${item.author.username || ''}`, `
            <form id="cmodEditForm" class="admin-form-grid">
                <div class="admin-field admin-field--full"><label>Titolo</label><input name="titolo" value="${e(item.title)}" required maxlength="120"></div>
                <div class="admin-field admin-field--full"><label>Descrizione</label><textarea name="descrizione" maxlength="2000">${e(item.description)}</textarea></div>
                ${type === 'rimasto' ? `<div class="admin-field admin-field--full"><label>Motivazione</label><textarea name="motivazione" maxlength="2000">${e(item.extra)}</textarea></div>` : ''}
                <div class="admin-field"><label>Tag (fino a 3, separati da virgola)</label><input name="tags" value="${e((item.tags || []).join(', '))}" maxlength="80"></div>
                <label class="admin-check"><input type="checkbox" name="is_spoiler" value="1" ${item.spoiler ? 'checked' : ''}><span>Spoiler</span></label>
            </form>`, '<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" id="cmodEditSave">Salva</button>');

        $('#cmodEditSave')?.addEventListener('click', async () => {
            const form = $('#cmodEditForm');
            if (!form) return;
            const body = Object.fromEntries(new FormData(form).entries());
            body.is_spoiler = form.elements.is_spoiler.checked ? 1 : 0;
            body.id = item.id;
            try {
                const data = await post('update', type, body);
                closeModal();
                showToast(data.message);
                state.detail = null;
                load(type);
            } catch (error) {
                showToast(error.message, true);
            }
        });
    };

    const openRules = async () => {
        let data;
        try {
            data = await get({ action: 'settings' });
        } catch (error) {
            showToast(error.message, true);
            return;
        }

        const s = data.settings || {};
        const toggle = (name, label, hint) => `<label class="admin-check cmod-rule"><input type="checkbox" name="${name}" value="1" ${Number(s[name]) === 1 ? 'checked' : ''}><span><b>${label}</b><small>${hint}</small></span></label>`;
        const number = (name, label, min, max, hint) => `<div class="admin-field"><label>${label}</label><input type="number" name="${name}" min="${min}" max="${max}" value="${Number(s[name])}"><small class="admin-muted">${hint}</small></div>`;

        openModal('Regole di pubblicazione', 'Valgono per Shitpost e Top Rimasti. Lo staff non ha limiti.', `
            ${data.available ? '' : '<p class="cmod-warn"><i class="fa-solid fa-triangle-exclamation"></i> Le regole si possono cambiare dopo aver applicato la migration <b>2026_10_03_community_v3.sql</b>. Per ora valgono i valori qui sotto.</p>'}
            <form id="cmodRulesForm" class="admin-form-grid">
                <div class="admin-field--full cmod-rules">
                    ${toggle('shitpost_aperto', 'Shitpost aperti', 'Spento: gli utenti non possono pubblicare shitpost.')}
                    ${toggle('rimasto_aperto', 'Top Rimasti aperti', 'Spento: gli utenti non possono candidare rimasti.')}
                    ${toggle('auto_approva', 'Approvazione automatica', 'Chi ha abbastanza post approvati va online senza passare dalla coda.')}
                </div>
                ${number('auto_approva_soglia', 'Post approvati per la fiducia', 1, 500, 'Somma di shitpost e rimasti online.')}
                ${number('max_post_giorno', 'Post al giorno per utente', 1, 100, 'Sulle due sezioni insieme.')}
                ${number('max_in_attesa', 'Post in attesa per utente', 1, 50, 'Oltre, deve aspettare il controllo.')}
                ${number('max_media', 'Media per post', 1, 10, data.multi_media ? 'Immagini, GIF e video nello stesso post.' : 'Serve la migration: per ora uno solo.')}
            </form>`, `<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" id="cmodRulesSave" ${data.available ? '' : 'disabled'}>Salva</button>`);

        $('#cmodRulesSave')?.addEventListener('click', async () => {
            const form = $('#cmodRulesForm');
            if (!form) return;
            const body = {};
            $$('input', form).forEach((input) => { body[input.name] = input.type === 'checkbox' ? (input.checked ? 1 : 0) : Number(input.value); });
            try {
                const saved = await post('save_settings', 'shitpost', body);
                closeModal();
                showToast(saved.message);
            } catch (error) {
                showToast(error.message, true);
            }
        });
    };

    // ── Eventi ──────────────────────────────────────────────────────────────
    function bind(type) {
        const root = rootOf(type);
        const state = states[type];

        root.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) return;

            const tab = target.closest('[data-cmod-tab]');
            if (tab) {
                state.tab = tab.dataset.cmodTab;
                state.page = 1;
                state.sort = '';
                state.posts = [];
                state.checked.clear();
                state.selected = 0;
                state.detail = null;
                paintPreview(type);
                return void load(type, false);
            }

            if (target.closest('[data-cmod-sort]')) {
                const order = state.tab === 'pending' ? ['oldest', 'recent'] : ['recent', 'oldest', 'top', 'comments'];
                state.sort = order[(order.indexOf(sortOf(state)) + 1) % order.length];
                state.page = 1;
                return void load(type, false);
            }

            if (target.closest('[data-cmod-rules]')) return void openRules();

            const page = target.closest('[data-cmod-page]');
            if (page && !page.disabled) {
                state.page = Number(page.dataset.cmodPage);
                state.posts = [];
                return void load(type, false);
            }

            const check = target.closest('[data-cmod-check]');
            if (check) {
                const id = Number(check.dataset.cmodCheck);
                if (state.checked.has(id)) state.checked.delete(id);
                else state.checked.add(id);
                return void paintQueue(type);
            }

            const item = target.closest('[data-cmod-item]');
            if (item) return void select(type, Number(item.dataset.cmodItem));

            const bulk = target.closest('[data-cmod-bulk]');
            if (bulk) return void run(type, bulk.dataset.cmodBulk, [...state.checked], bulk);

            const media = target.closest('[data-cmod-media]');
            if (media) {
                state.media = Number(media.dataset.cmodMedia);
                return void paintPreview(type);
            }

            const reason = target.closest('[data-cmod-reason]');
            if (reason) {
                state.reason = reason.dataset.cmodReason;
                $$('[data-cmod-reason]', root).forEach((chip) => chip.classList.toggle('is-active', chip.dataset.cmodReason === state.reason));
                const text = $('[data-cmod-reason-text]', root);
                if (text) {
                    text.hidden = state.reason !== 'other';
                    if (!text.hidden) text.focus();
                }
                $('.cmod-queue__foot', root).innerHTML = pagesHtml(state) + bulkHtml(type);
                return;
            }

            const comment = target.closest('[data-cmod-comment]');
            if (comment) {
                if (!armed(comment)) return;
                post('delete_comment', type, { comment_id: Number(comment.dataset.cmodComment) })
                    .then((data) => {
                        showToast(data.message);
                        if (state.detail) {
                            state.detail.comments = data.comments || [];
                            state.detail.post.comments = state.detail.comments.length;
                            paintPreview(type);
                            const box = $('details.cmod-box', root);
                            if (box) box.open = true;
                        }
                    })
                    .catch((error) => showToast(error.message, true));
                return;
            }

            const act = target.closest('[data-cmod-do]');
            if (!act || !state.selected) return;
            const action = act.dataset.cmodDo;

            if (action === 'edit') return void openEdit(type);
            if (action === 'reset') {
                if (!armed(act)) return;
                post('reset_votes', 'rimasto', { id: state.selected })
                    .then((data) => { showToast(data.message); state.detail = null; load(type); })
                    .catch((error) => showToast(error.message, true));
                return;
            }
            run(type, action, [state.selected], act);
        });

        root.addEventListener('input', (event) => {
            if (event.target.matches('[data-cmod-reason-text]')) state.reasonText = event.target.value;
        });

        root.addEventListener('keydown', (event) => {
            const item = event.target instanceof Element ? event.target.closest('[data-cmod-item]') : null;
            if (item && event.target === item && event.key === 'Enter') select(type, Number(item.dataset.cmodItem));
        });
    }

    document.addEventListener('keydown', (event) => {
        if (!activeType || event.ctrlKey || event.metaKey || event.altKey) return;
        const root = rootOf(activeType);
        if (!root || !root.closest('.admin-section.is-active') || document.querySelector('.modal.show')) return;
        if (event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable], video')) return;

        const state = states[activeType];
        const key = event.key.toLowerCase();
        const index = state.posts.findIndex((item) => item.id === state.selected);

        if (key === 'arrowdown' || key === 'arrowup') {
            const next = state.posts[index + (key === 'arrowdown' ? 1 : -1)];
            if (next) {
                event.preventDefault();
                select(activeType, next.id, true);
            }
        } else if (key === ' ' && state.selected && !(event.target instanceof HTMLButtonElement)) {
            event.preventDefault();
            if (state.checked.has(state.selected)) state.checked.delete(state.selected);
            else state.checked.add(state.selected);
            paintQueue(activeType);
        } else if (key === 'a' && state.selected && state.detail && !state.detail.post.approved) {
            run(activeType, 'approve', [state.selected], null);
        } else if (key === 'r' && state.selected && state.detail) {
            run(activeType, 'delete', [state.selected], $('[data-cmod-do="delete"]', root));
        }
    });

    // ── Avvio ───────────────────────────────────────────────────────────────
    Object.entries(SECTIONS).forEach(([section, type]) => {
        A.registerSection(section, () => {
            activeType = type;
            states[type].page = 1;
            states[type].posts = [];
            load(type);
        });
    });

    document.addEventListener('click', (event) => {
        const nav = event.target instanceof Element ? event.target.closest('[data-admin-nav] button') : null;
        if (nav && !SECTIONS[nav.dataset.section]) activeType = null;
    });

    get({ action: 'counts' }).then((data) => setCounts(data.counts)).catch(() => {});
})();
