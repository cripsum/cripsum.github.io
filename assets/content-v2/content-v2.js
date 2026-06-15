(() => {
    'use strict';

    const lang = location.pathname.split('/').find(s => s === 'it' || s === 'en') || 'it';

    const t = {
        it: {
            // date locale
            date_locale:         'it-IT',
            // card / modal
            open_post:           'Apri post',
            show_spoiler:        'Mostra spoiler',
            no_title:            'Senza titolo',
            user_fallback:       'utente',
            pending:             'In attesa',
            save:                'Salva',
            report:              'Segnala',
            edit:                'Modifica',
            delete:              'Elimina',
            hide:                'Nascondi',
            approve:             'Approva',
            // stats
            stat_votes:          'Voti',
            stat_likes:          'Like',
            stat_comments:       'Commenti',
            stat_views:          'Visite',
            // toasts
            must_login:          'Devi accedere.',
            post_saved:          'Post salvato.',
            post_unsaved:        'Post rimosso dai salvati.',
            link_copied:         'Link copiato.',
            copy_failed:         'Non sono riuscito a copiare.',
            report_sent:         'Segnalazione inviata.',
            post_deleted:        'Post eliminato.',
            post_approved:       'Post approvato.',
            post_hidden:         'Post nascosto.',
            post_submitted:      'Post inviato. Sarà visibile dopo approvazione.',
            post_updated:        'Post aggiornato.',
            comment_deleted:     'Commento eliminato.',
            upload_error:        'Errore upload',
            load_error:          'Errore caricamento',
            // comments
            no_comments:         'Nessun commento',
            comment_placeholder: 'Scrivi un commento',
            // confirm dialogs
            confirm_delete_post: 'Eliminare questo post?',
            confirm_delete_comment: 'Eliminare commento?',
            // report prompt
            report_prompt:       'Motivo segnalazione',
            // edit form
            field_title:         'Titolo',
            field_description:   'Descrizione',
            field_motivation:    'Motivazione',
            field_tag:           'Tag',
            field_spoiler:       'Spoiler',
            btn_cancel:          'Annulla',
            btn_save:            'Salva',
            // upload preview
            upload_hint:         'Carica immagine, GIF o video breve',
            upload_limits:       'Immagini/GIF max 8MB, video max 20MB.',
        },
        en: {
            date_locale:         'en-GB',
            open_post:           'Open post',
            show_spoiler:        'Show spoiler',
            no_title:            'Untitled',
            user_fallback:       'user',
            pending:             'Pending',
            save:                'Save',
            report:              'Report',
            edit:                'Edit',
            delete:              'Delete',
            hide:                'Hide',
            approve:             'Approve',
            stat_votes:          'Votes',
            stat_likes:          'Likes',
            stat_comments:       'Comments',
            stat_views:          'Views',
            must_login:          'You must be logged in.',
            post_saved:          'Post saved.',
            post_unsaved:        'Post removed from saved.',
            link_copied:         'Link copied.',
            copy_failed:         'Could not copy the link.',
            report_sent:         'Report submitted.',
            post_deleted:        'Post deleted.',
            post_approved:       'Post approved.',
            post_hidden:         'Post hidden.',
            post_submitted:      'Post submitted. It will be visible after approval.',
            post_updated:        'Post updated.',
            comment_deleted:     'Comment deleted.',
            upload_error:        'Upload error',
            load_error:          'Loading error',
            no_comments:         'No comments',
            comment_placeholder: 'Write a comment',
            confirm_delete_post: 'Delete this post?',
            confirm_delete_comment: 'Delete comment?',
            report_prompt:       'Reason for report',
            field_title:         'Title',
            field_description:   'Description',
            field_motivation:    'Motivation',
            field_tag:           'Tag',
            field_spoiler:       'Spoiler',
            btn_cancel:          'Cancel',
            btn_save:            'Save',
            upload_hint:         'Upload image, GIF or short video',
            upload_limits:       'Images/GIFs max 8MB, videos max 20MB.',
        },
    }[lang];

    const body = document.body;    const type = body.dataset.contentType || 'shitpost';
    const csrf = body.dataset.csrf || '';
    const isLogged = body.dataset.logged === '1';
    const isAdmin = body.dataset.admin === '1';
    const currentUserId = Number(body.dataset.userId || 0);
    const needsMotivation = body.dataset.needsMotivation === '1';
    const defaultSort = body.dataset.defaultSort || (type === 'rimasto' ? 'top' : 'recent');

    const state = {
        page: 1,
        pages: 1,
        loading: false,
        q: '',
        sort: defaultSort,
        status: isAdmin ? 'approved' : 'approved',
        savedOnly: false,
        posts: [],
        openedComments: new Set(),
        viewed: new Set(),
    };

    let toastTimer = null;

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const apiBase = '/api/content';

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[char]));

    const compactNumber = (num) => {
        num = Number(num || 0);
        if (num >= 1000000) return `${(num / 1000000).toFixed(1)}M`;
        if (num >= 1000) return `${(num / 1000).toFixed(1)}K`;
        return String(num);
    };

    const formatDate = (value) => {
        if (!value) return '';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleString(t.date_locale, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    };

    const showToast = (message, isError = false) => {
        const toast = $('#cwToast');
        if (!toast) return;
        toast.textContent = message;
        toast.classList.toggle('is-error', isError);
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2400);
    };

    const api = async (endpoint, options = {}) => {
        const headers = {
            'X-CSRF-Token': csrf,
            'X-Requested-With': 'fetch',
            ...(options.headers || {}),
        };

        const response = await fetch(endpoint.startsWith('http') ? endpoint : `${apiBase}/${endpoint}`, {
            ...options,
            headers,
            cache: 'no-store',
        });

        const contentType = response.headers.get('content-type') || '';
        const data = contentType.includes('application/json') ? await response.json() : { ok: false, message: await response.text() };

        if (!response.ok || data.ok === false) {
            throw new Error(data.message || `HTTP ${response.status}`);
        }

        return data;
    };

    const copyText = async (text) => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return true;
            }
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            const ok = document.execCommand('copy');
            textarea.remove();
            return ok;
        } catch {
            return false;
        }
    };

    const mediaHtml = (post) => {
        if (!post.media_url) {
            return `<div class="cw-media" style="display:grid;place-items:center;color:var(--cw-muted);"><i class="fa-solid fa-image"></i></div>`;
        }

        if (post.is_video) {
            return `<video class="cw-media" src="${escapeHtml(post.media_url)}" controls playsinline preload="metadata"></video>`;
        }

        return `<img class="cw-media godomedia" src="${escapeHtml(post.media_url)}" alt="" loading="lazy" onerror="this.closest('.cw-post__media-wrap').innerHTML='<div class=&quot;cw-media&quot; style=&quot;display:grid;place-items:center;color:var(--cw-muted);&quot;><i class=&quot;fa-solid fa-triangle-exclamation&quot;></i></div>'">`;
    };

    const postCard = (post) => {
        const isOwner = Number(post.id_utente) === currentUserId;
        const canManage = isOwner || isAdmin;
        const approved = Number(post.approvato) === 1;
        const url = `${location.origin}${post.public_url || location.pathname + '?post=' + post.id}`;
        const hasDesc = String(post.descrizione || '').trim() !== '';
        const hasExtra = String(post.extra_text || '').trim() !== '';
        const tag = String(post.tag || '').trim();
        const isSpoiler = Number(post.is_spoiler || 0) === 1;

        return `
            <article class="cw-post ${isSpoiler ? 'is-spoiler' : ''}" data-post-id="${Number(post.id)}" data-approved="${approved ? '1' : '0'}">
                <button type="button" class="cw-post__media-wrap cw-post__media-button" data-open-post="${Number(post.id)}" aria-label="${t.open_post}">
                    ${mediaHtml(post)}
                    ${isSpoiler ? `<button type="button" class="cw-spoiler-cover" data-reveal-spoiler="${Number(post.id)}"><span class="cw-btn cw-btn--ghost"><i class="fa-solid fa-eye"></i> ${t.show_spoiler}</span></button>` : ''}
                </button>

                <div class="cw-card-body">
                    <div class="cw-post__head">
                        <a class="cw-user" href="/u/${encodeURIComponent(post.username || '')}">
                            <img class="cw-avatar" src="/includes/get_pfp.php?id=${Number(post.id_utente)}" alt="">
                            <span>
                                <strong>${escapeHtml(post.username || t.user_fallback)}</strong>
                                <span>${formatDate(post.data_creazione)}</span>
                            </span>
                        </a>
                        ${post.ruolo && post.ruolo !== 'utente' ? `<span class="cw-badge">${escapeHtml(post.ruolo)}</span>` : ''}
                    </div>

                    <button type="button" class="cw-title cw-title-button" data-open-post="${Number(post.id)}">${escapeHtml(post.titolo || t.no_title)}</button>
                    ${hasDesc ? `<p class="cw-description">${escapeHtml(post.descrizione)}</p>` : ''}
                    ${hasExtra ? `<p class="cw-extra">${escapeHtml(post.extra_text)}</p>` : ''}

                    <div class="cw-meta-row">
                        ${tag ? `<span class="cw-meta-pill"><i class="fa-solid fa-tag"></i>${escapeHtml(tag)}</span>` : ''}
                        ${approved ? '' : `<span class="cw-meta-pill"><i class="fa-solid fa-clock"></i>${t.pending}</span>`}
                        <span class="cw-meta-pill"><i class="fa-solid fa-eye"></i>${compactNumber(post.views || 0)}</span>
                    </div>

                    <div class="cw-actions">
                        <button type="button" class="cw-action ${post.user_liked ? 'is-active' : ''}" data-action="react" data-id="${Number(post.id)}">
                            <i class="fa-solid fa-fire"></i> <span>${compactNumber(post.score || 0)}</span>
                        </button>
                        <button type="button" class="cw-action" data-action="comments" data-id="${Number(post.id)}">
                            <i class="fa-solid fa-comment"></i> <span>${compactNumber(post.comments_count || 0)}</span>
                        </button>
                        <button type="button" class="cw-action ${post.user_saved ? 'is-active' : ''}" data-action="save" data-id="${Number(post.id)}">
                            <i class="fa-solid fa-bookmark"></i> <span>${t.save}</span>
                        </button>
                        <button type="button" class="cw-action" data-action="share" data-url="${escapeHtml(url)}">
                            <i class="fa-solid fa-share-nodes"></i> <span>Share</span>
                        </button>
                    </div>

                    <div class="cw-admin-actions">
                        <button type="button" class="cw-btn cw-btn--ghost" data-action="report" data-id="${Number(post.id)}"><i class="fa-solid fa-flag"></i> ${t.report}</button>
                        ${canManage ? `<button type="button" class="cw-btn cw-btn--ghost" data-action="edit" data-id="${Number(post.id)}"><i class="fa-solid fa-pen"></i> ${t.edit}</button>` : ''}
                        ${canManage ? `<button type="button" class="cw-btn cw-btn--danger" data-action="delete" data-id="${Number(post.id)}"><i class="fa-solid fa-trash"></i> ${t.delete}</button>` : ''}
                        ${isAdmin ? `<button type="button" class="cw-btn cw-btn--ghost" data-action="approve" data-approved="${approved ? '0' : '1'}" data-id="${Number(post.id)}"><i class="fa-solid ${approved ? 'fa-xmark' : 'fa-check'}"></i> ${approved ? t.hide : t.approve}</button>` : ''}
                    </div>

                    <div class="cw-comments" id="comments-${Number(post.id)}" hidden></div>
                </div>
            </article>
        `;
    };

    const renderStats = (data) => {
        const box = $('#cwStats');
        if (!box) return;

        const stats = data.stats || {};
        box.innerHTML = [
            ['fa-solid fa-layer-group', stats.total || 0, 'Post'],
            ['fa-solid fa-fire', stats.reactions || 0, type === 'rimasto' ? t.stat_votes : t.stat_likes],
            ['fa-solid fa-comments', stats.comments || 0, t.stat_comments],
            ['fa-solid fa-eye', stats.views || 0, t.stat_views],
        ].map(([icon, value, label]) => `<span class="cw-meta-pill"><i class="${icon}"></i><b>${compactNumber(value)}</b>${label}</span>`).join('');
        box.hidden = false;
    };

    const render = (append = false) => {
        const feed = $('#cwFeed');
        if (!feed) return;

        if (!append) feed.innerHTML = '';

        feed.insertAdjacentHTML('beforeend', state.posts.map(postCard).join(''));

        $('#cwEmpty').hidden = state.posts.length > 0 || state.loading;
        $('#cwLoadMore').hidden = state.page >= state.pages || state.loading;

        bindPostActions(feed);
        observeViews();
    };

    const loadPosts = async (append = false) => {
        if (state.loading) return;
        state.loading = true;

        $('#cwLoader').style.display = 'flex';
        $('#cwEmpty').hidden = true;

        try {
            const params = new URLSearchParams({
                type,
                page: state.page,
                limit: 100,
                sort: state.sort,
                q: state.q,
                status: state.status,
                saved: state.savedOnly ? '1' : '0',
            });

            const data = await api(`get_posts.php?${params}`);
            state.pages = Number(data.pagination?.pages || 1);

            const posts = data.posts || [];
            if (append) state.posts = posts;
            else {
                state.posts = posts;
                $('#cwFeed').innerHTML = '';
            }

            renderStats(data);
            render(false);
        } catch (error) {
            showToast(error.message, true);
            $('#cwFeed').innerHTML = `<div class="cw-empty"><i class="fa-solid fa-triangle-exclamation"></i><strong>${t.load_error}</strong><span>${escapeHtml(error.message)}</span></div>`;
        } finally {
            state.loading = false;
            $('#cwLoader').style.display = 'none';
            $('#cwEmpty').hidden = state.posts.length > 0;
        }
    };

    const appendPosts = async () => {
        if (state.loading || state.page >= state.pages) return;
        state.loading = true;
        $('#cwLoadMore').hidden = true;
        $('#cwLoader').style.display = 'flex';

        try {
            state.page += 1;
            const params = new URLSearchParams({
                type,
                page: state.page,
                limit: 100,
                sort: state.sort,
                q: state.q,
                status: state.status,
                saved: state.savedOnly ? '1' : '0',
            });
            const data = await api(`get_posts.php?${params}`);
            state.pages = Number(data.pagination?.pages || 1);
            const newPosts = data.posts || [];
            state.posts.push(...newPosts);
            $('#cwFeed').insertAdjacentHTML('beforeend', newPosts.map(postCard).join(''));
            bindPostActions($('#cwFeed'));
            observeViews();
        } catch (error) {
            state.page -= 1;
            showToast(error.message, true);
        } finally {
            state.loading = false;
            $('#cwLoader').style.display = 'none';
            $('#cwLoadMore').hidden = state.page >= state.pages;
        }
    };

    const reload = () => {
        state.page = 1;
        state.viewed.clear();
        loadPosts(false);
    };

    const bindPostActions = (root) => {
        $$('[data-action]', root).forEach((btn) => {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';

            btn.addEventListener('click', async () => {
                const action = btn.dataset.action;
                const id = Number(btn.dataset.id || 0);

                if (action === 'react') return reactPost(id, btn);
                if (action === 'comments') return toggleComments(id);
                if (action === 'save') return savePost(id, btn);
                if (action === 'share') return sharePost(btn.dataset.url);
                if (action === 'report') return reportPost(id);
                if (action === 'edit') return openEditModal(id);
                if (action === 'delete') return deletePost(id);
                if (action === 'approve') return approvePost(id, Number(btn.dataset.approved || 0));
            });
        });

        $$('[data-open-post]', root).forEach((btn) => {
            if (btn.dataset.boundOpen === '1') return;
            btn.dataset.boundOpen = '1';
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                const id = Number(btn.dataset.openPost || 0);
                if (id) openPostModal(id);
            });
        });

        $$('[data-reveal-spoiler]', root).forEach((btn) => {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                btn.closest('.cw-post')?.classList.add('is-revealed');
            });
        });
    };


    const openPostModal = async (id) => {
        const post = state.posts.find((item) => Number(item.id) === Number(id));
        if (!post) return;

        const modal = $('#cwPostModal');
        const bodyBox = $('#cwPostModalBody');
        if (!modal || !bodyBox) return;

        const url = `${location.origin}${post.public_url || location.pathname + '?post=' + post.id}`;
        const hasDesc = String(post.descrizione || '').trim() !== '';
        const hasExtra = String(post.extra_text || '').trim() !== '';
        const tag = String(post.tag || '').trim();

        bodyBox.innerHTML = `
            <article class="cw-post-detail">
                <div class="cw-post-detail__media">
                    ${mediaHtml(post)}
                </div>
                <div class="cw-post-detail__body">
                    <div class="cw-post__head">
                        <a class="cw-user" href="/u/${encodeURIComponent(post.username || '')}">
                            <img class="cw-avatar" src="/includes/get_pfp.php?id=${Number(post.id_utente)}" alt="">
                            <span>
                                <strong>${escapeHtml(post.username || t.user_fallback)}</strong>
                                <span>${formatDate(post.data_creazione)}</span>
                            </span>
                        </a>
                        ${post.ruolo && post.ruolo !== 'utente' ? `<span class="cw-badge">${escapeHtml(post.ruolo)}</span>` : ''}
                    </div>

                    <h2 class="cw-title">${escapeHtml(post.titolo || t.no_title)}</h2>
                    ${hasDesc ? `<p class="cw-description">${escapeHtml(post.descrizione)}</p>` : ''}
                    ${hasExtra ? `<p class="cw-extra">${escapeHtml(post.extra_text)}</p>` : ''}

                    <div class="cw-meta-row">
                        ${tag ? `<span class="cw-meta-pill"><i class="fa-solid fa-tag"></i>${escapeHtml(tag)}</span>` : ''}
                        <span class="cw-meta-pill"><i class="fa-solid fa-fire"></i>${compactNumber(post.score || 0)}</span>
                        <span class="cw-meta-pill"><i class="fa-solid fa-comment"></i>${compactNumber(post.comments_count || 0)}</span>
                        <span class="cw-meta-pill"><i class="fa-solid fa-eye"></i>${compactNumber(post.views || 0)}</span>
                    </div>

                    <div class="cw-actions cw-actions--detail">
                        <button type="button" class="cw-action ${post.user_liked ? 'is-active' : ''}" data-action="react" data-id="${Number(post.id)}">
                            <i class="fa-solid fa-fire"></i> <span>${compactNumber(post.score || 0)}</span>
                        </button>
                        <button type="button" class="cw-action ${post.user_saved ? 'is-active' : ''}" data-action="save" data-id="${Number(post.id)}">
                            <i class="fa-solid fa-bookmark"></i> <span>${t.save}</span>
                        </button>
                        <button type="button" class="cw-action" data-action="share" data-url="${escapeHtml(url)}">
                            <i class="fa-solid fa-share-nodes"></i> <span>Share</span>
                        </button>
                    </div>

                    <div class="cw-comments" id="modal-comments-${Number(post.id)}"></div>
                </div>
            </article>
        `;

        modal.classList.add('is-open');
        window.__presencePost = {
            title: post.titolo || t.no_title,
            image: (!post.is_video && post.media_url) ? post.media_url : null,
        };
        modal.setAttribute('aria-hidden', 'false');
        bindPostActions(bodyBox);
        await loadComments(id, `#modal-comments-${Number(post.id)}`);
    };

    const closePostModal = () => {
        const modal = $('#cwPostModal');
        if (!modal) return;
        modal.classList.remove('is-open');
        window.__presencePost = null;
        modal.setAttribute('aria-hidden', 'true');
        const bodyBox = $('#cwPostModalBody');
        if (bodyBox) bodyBox.innerHTML = '';
    };


    const requireLogin = () => {
        if (isLogged) return true;
        showToast(t.must_login, true);
        return false;
    };

    const reactPost = async (id, btn) => {
        if (!requireLogin()) return;

        try {
            const data = await api('react_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, id }),
            });

            btn.classList.toggle('is-active', data.active);
            const span = btn.querySelector('span');
            if (span) span.textContent = compactNumber(data.score || 0);

            const post = state.posts.find((item) => Number(item.id) === id);
            if (post) {
                post.score = data.score || 0;
                post.user_liked = data.active ? 1 : 0;
            }
        } catch (error) {
            showToast(error.message, true);
        }
    };

    const savePost = async (id, btn) => {
        if (!requireLogin()) return;

        try {
            const data = await api('save_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, id }),
            });
            btn.classList.toggle('is-active', data.active);
            showToast(data.active ? t.post_saved : t.post_unsaved);
        } catch (error) {
            showToast(error.message, true);
        }
    };

    const sharePost = async (url) => {
        if (navigator.share) {
            try {
                await navigator.share({ title: document.title, url });
                return;
            } catch (error) {
                if (error.name === 'AbortError') return;
            }
        }

        const ok = await copyText(url);
        showToast(ok ? t.link_copied : t.copy_failed);
    };

    const reportPost = async (id) => {
        if (!requireLogin()) return;

        const reason = prompt(t.report_prompt);
        if (!reason) return;

        try {
            await api('report_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, id, reason }),
            });
            showToast(t.report_sent);
        } catch (error) {
            showToast(error.message, true);
        }
    };

    const openEditModal = (id) => {
        const post = state.posts.find((item) => Number(item.id) === id);
        if (!post) return;

        const modal = $('#cwTextModal');
        const form = $('#cwTextForm');

        form.innerHTML = `
            <input type="hidden" name="id" value="${Number(post.id)}">
            <div class="cw-field">
                <label>${t.field_title}</label>
                <input name="titolo" maxlength="120" required value="${escapeHtml(post.titolo || '')}">
            </div>
            <div class="cw-field">
                <label>${t.field_description}</label>
                <textarea name="descrizione" maxlength="2000" rows="4">${escapeHtml(post.descrizione || '')}</textarea>
            </div>
            ${needsMotivation ? `<div class="cw-field"><label>${t.field_motivation}</label><textarea name="motivazione" maxlength="2000" rows="3">${escapeHtml(post.extra_text || '')}</textarea></div>` : ''}
            <div class="cw-form-grid">
                <div class="cw-field">
                    <label>${t.field_tag}</label>
                    <input name="tag" maxlength="40" value="${escapeHtml(post.tag || '')}">
                </div>
                <label class="cw-check">
                    <input type="checkbox" name="is_spoiler" value="1" ${Number(post.is_spoiler || 0) === 1 ? 'checked' : ''}>
                    <span>${t.field_spoiler}</span>
                </label>
            </div>
            <div class="cw-modal__footer">
                <button type="button" class="cw-btn cw-btn--ghost js-close-text-modal">${t.btn_cancel}</button>
                <button type="submit" class="cw-btn cw-btn--primary">${t.btn_save}</button>
            </div>
        `;

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        $$('.js-close-text-modal', modal).forEach((b) => b.addEventListener('click', closeTextModal, { once: true }));
    };

    const closeTextModal = () => {
        const modal = $('#cwTextModal');
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    };

    const deletePost = async (id) => {
        if (!confirm(t.confirm_delete_post)) return;

        try {
            await api('delete_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, id }),
            });
            showToast(t.post_deleted);
            reload();
        } catch (error) {
            showToast(error.message, true);
        }
    };

    const approvePost = async (id, approved) => {
        try {
            await api('approve_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, id, approved }),
            });
            showToast(approved ? t.post_approved : t.post_hidden);
            reload();
        } catch (error) {
            showToast(error.message, true);
        }
    };

    const toggleComments = async (id) => {
        const box = $(`#comments-${id}`);
        if (!box) return;

        if (!box.hidden) {
            box.hidden = true;
            state.openedComments.delete(id);
            return;
        }

        box.hidden = false;
        state.openedComments.add(id);
        await loadComments(id);
    };

    const loadComments = async (id, targetSelector = null) => {
        const box = targetSelector ? $(targetSelector) : $(`#comments-${id}`);
        if (!box) return;
        box.innerHTML = '<div class="cw-loader"><span></span><span></span><span></span></div>';

        try {
            const data = await api(`get_comments.php?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
            const comments = data.comments || [];

            box.innerHTML = `
                <div>
                    ${comments.length ? comments.map((comment) => `
                        <div class="cw-comment">
                            <img class="cw-avatar" src="/includes/get_pfp.php?id=${Number(comment.id_utente)}" alt="">
                            <div class="cw-comment__body">
                                <strong>${escapeHtml(comment.username || t.user_fallback)}</strong>
                                <small>${formatDate(comment.created_at)}</small>
                                <span>${escapeHtml(comment.commento || '')}</span>
                            </div>
                            ${isAdmin || Number(comment.id_utente) === currentUserId ? `<button type="button" class="cw-icon-btn cw-icon-btn--danger" data-delete-comment="${Number(comment.id)}" data-post-id="${Number(id)}"><i class="fa-solid fa-trash"></i></button>` : ''}
                        </div>
                    `).join('') : `<div class="cw-meta-pill"><i class="fa-solid fa-comment"></i>${t.no_comments}</div>`}
                </div>
                ${isLogged ? `
                    <form class="cw-comment-form" data-comment-form="${Number(id)}">
                        <input name="commento" maxlength="500" placeholder="${t.comment_placeholder}">
                        <button class="cw-icon-btn" type="submit"><i class="fa-solid fa-paper-plane"></i></button>
                    </form>` : ''}
            `;

            $$('[data-delete-comment]', box).forEach((btn) => {
                btn.addEventListener('click', () => deleteComment(Number(btn.dataset.deleteComment), Number(btn.dataset.postId)));
            });

            const form = $(`[data-comment-form="${id}"]`, box);
            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                const commento = new FormData(form).get('commento');
                if (!String(commento || '').trim()) return;

                try {
                    await api('comment_post.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ type, id, commento }),
                    });
                    await loadComments(id);
                    const post = state.posts.find((item) => Number(item.id) === id);
                    if (post) post.comments_count = Number(post.comments_count || 0) + 1;
                } catch (error) {
                    showToast(error.message, true);
                }
            });
        } catch (error) {
            box.innerHTML = `<div class="cw-meta-pill"><i class="fa-solid fa-triangle-exclamation"></i>${escapeHtml(error.message)}</div>`;
        }
    };

    const deleteComment = async (commentId, postId) => {
        if (!confirm(t.confirm_delete_comment)) return;

        try {
            await api('delete_comment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, comment_id: commentId }),
            });
            await loadComments(postId);
            showToast(t.comment_deleted);
        } catch (error) {
            showToast(error.message, true);
        }
    };

    const observeViews = () => {
        if (!('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;

                const id = Number(entry.target.dataset.postId || 0);
                if (!id || state.viewed.has(id)) return;

                state.viewed.add(id);
                api('view_post.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type, id }),
                }).catch(() => {});

                observer.unobserve(entry.target);
            });
        }, { threshold: 0.55 });

        $$('.cw-post').forEach((post) => observer.observe(post));
    };

    const openCreateModal = () => {
        if (!requireLogin()) return;
        const modal = $('#cwCreateModal');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    };

    const closeCreateModal = () => {
        const modal = $('#cwCreateModal');
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        $('#cwCreateForm')?.reset();
        $('#cwPreview').innerHTML = `<i class="fa-solid fa-cloud-arrow-up"></i><strong>${t.upload_hint}</strong><span>${t.upload_limits}</span>`;
    };

    const initCreate = () => {
        $$('.js-open-create').forEach((btn) => btn.addEventListener('click', openCreateModal));
        $$('.js-close-modal').forEach((btn) => btn.addEventListener('click', closeCreateModal));

        $('#cwCreateModal')?.addEventListener('click', (event) => {
            if (event.target.id === 'cwCreateModal') closeCreateModal();
        });

        $('#cwTextModal')?.addEventListener('click', (event) => {
            if (event.target.id === 'cwTextModal') closeTextModal();
        });

        $('#cwPostModal')?.addEventListener('click', (event) => {
            if (event.target.id === 'cwPostModal') closePostModal();
        });

        $$('.js-close-post-modal').forEach((btn) => btn.addEventListener('click', closePostModal));

        $('#cwMediaInput')?.addEventListener('change', (event) => {
            const file = event.target.files?.[0];
            const preview = $('#cwPreview');
            if (!file || !preview) return;

            const url = URL.createObjectURL(file);
            if (file.type.startsWith('video/')) {
                preview.innerHTML = `<video src="${url}" controls playsinline></video>`;
            } else {
                preview.innerHTML = `<img src="${url}" alt="">`;
            }
        });

        $('#cwCreateForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();

            const form = event.currentTarget;
            const submit = form.querySelector('[type="submit"]');
            submit.disabled = true;

            const data = new FormData(form);
            data.append('type', type);

            try {
                await fetch(`${apiBase}/create_post.php`, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'fetch' },
                    body: data,
                }).then(async (response) => {
                    const payload = await response.json();
                    if (!response.ok || payload.ok === false) throw new Error(payload.message || t.upload_error);
                    return payload;
                });

                closeCreateModal();
                showToast(t.post_submitted);
                reload();
            } catch (error) {
                showToast(error.message, true);
            } finally {
                submit.disabled = false;
            }
        });

        $('#cwTextForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();

            const payload = Object.fromEntries(new FormData(event.currentTarget).entries());
            payload.type = type;
            payload.is_spoiler = event.currentTarget.querySelector('[name="is_spoiler"]')?.checked ? 1 : 0;

            try {
                await api('update_post.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                closeTextModal();
                showToast(t.post_updated);
                reload();
            } catch (error) {
                showToast(error.message, true);
            }
        });
    };

    const initControls = () => {
        const search = $('#cwSearchInput');
        const sortSelect = $('#cwSortSelect');
        if (sortSelect) sortSelect.value = state.sort;
        let searchTimer = null;

        search?.addEventListener('input', () => {
            search.closest('.cw-search')?.classList.toggle('has-value', search.value.trim() !== '');
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                state.q = search.value.trim();
                reload();
            }, 280);
        });

        $('#cwClearSearch')?.addEventListener('click', () => {
            if (!search) return;
            search.value = '';
            search.closest('.cw-search')?.classList.remove('has-value');
            state.q = '';
            reload();
        });

        $('#cwSortSelect')?.addEventListener('change', (event) => {
            state.sort = event.target.value;
            reload();
        });

        $('#cwStatusSelect')?.addEventListener('change', (event) => {
            state.status = event.target.value;
            reload();
        });

        $('#cwSavedFilter')?.addEventListener('click', (event) => {
            state.savedOnly = !state.savedOnly;
            event.currentTarget.classList.toggle('is-active', state.savedOnly);
            reload();
        });

        $('#cwLoadMore')?.addEventListener('click', appendPosts);

        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeCreateModal();
                closeTextModal();
                closePostModal();
            }
        });
    };


    const initNavbarDropdownFallback = () => {
        const toggles = $$('[data-bs-toggle="dropdown"], .dropdown-toggle');
        toggles.forEach((toggle) => {
            if (toggle.dataset.cv2DropdownBound === '1') return;
            toggle.dataset.cv2DropdownBound = '1';

            toggle.addEventListener('click', (event) => {
                const hasBootstrap = window.bootstrap && window.bootstrap.Dropdown;
                if (hasBootstrap) return;

                event.preventDefault();
                event.stopPropagation();

                const parent = toggle.closest('.dropdown') || toggle.parentElement;
                const menu = parent?.querySelector('.dropdown-menu');
                if (!menu) return;

                $$('.dropdown-menu.show').forEach((other) => {
                    if (other !== menu) other.classList.remove('show');
                });

                menu.classList.toggle('show');
                toggle.setAttribute('aria-expanded', menu.classList.contains('show') ? 'true' : 'false');
            });
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('.dropdown')) return;
            $$('.dropdown-menu.show').forEach((menu) => menu.classList.remove('show'));
        });
    };


    document.addEventListener('DOMContentLoaded', () => {
        initNavbarDropdownFallback();
        initControls();
        initCreate();
        loadPosts();
    });

    if (window.__contentV2CustomSelectLoaded) return;
    window.__contentV2CustomSelectLoaded = true;

    const initContentCustomSelect = () => {
        document.querySelectorAll('[data-cw-custom-select]').forEach((wrap) => {
            if (wrap.dataset.bound === '1') return;
            wrap.dataset.bound = '1';

            const select = wrap.querySelector('select');
            const trigger = wrap.querySelector('.cw-select-trigger');
            const current = wrap.querySelector('.cw-select-current');
            const options = Array.from(wrap.querySelectorAll('.cw-select-menu [data-value]'));

            if (!select || !trigger || !current || !options.length) return;

            const sync = (value, emit = false) => {
                const realOption =
                    Array.from(select.options).find((option) => option.value === value) ||
                    select.options[0];

                if (!realOption) return;

                select.value = realOption.value;
                current.textContent = realOption.textContent.trim();

                options.forEach((button) => {
                    const active = button.dataset.value === realOption.value;
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                if (emit) {
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
            };

            trigger.addEventListener('click', (event) => {
                event.stopPropagation();

                document.querySelectorAll('[data-cw-custom-select].is-open').forEach((other) => {
                    if (other === wrap) return;

                    other.classList.remove('is-open');
                    other.querySelector('.cw-select-trigger')?.setAttribute('aria-expanded', 'false');
                });

                const isOpen = wrap.classList.toggle('is-open');
                trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            options.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.stopPropagation();

                    sync(button.dataset.value, true);

                    wrap.classList.remove('is-open');
                    trigger.setAttribute('aria-expanded', 'false');
                });
            });

            select.addEventListener('change', () => {
                sync(select.value, false);
            });

            sync(select.value || options[0].dataset.value, false);
        });

        document.addEventListener('click', () => {
            document.querySelectorAll('[data-cw-custom-select].is-open').forEach((wrap) => {
                wrap.classList.remove('is-open');
                wrap.querySelector('.cw-select-trigger')?.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;

            document.querySelectorAll('[data-cw-custom-select].is-open').forEach((wrap) => {
                wrap.classList.remove('is-open');
                wrap.querySelector('.cw-select-trigger')?.setAttribute('aria-expanded', 'false');
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initContentCustomSelect, { once: true });
    } else {
        initContentCustomSelect();
    }
})();