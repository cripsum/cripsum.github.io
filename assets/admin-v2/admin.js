(() => {
    'use strict';

    const body = document.body;
    const csrf = body.dataset.csrf || '';
    const adminRole = body.dataset.adminRole || 'utente';
    const apiBase = '/api/admin';

    const state = {
        section: 'dashboard',
        q: '',
        users: { page: 1, status: 'all', role: 'all', sort: 'data_creazione', dir: 'DESC' },
        characters: { page: 1 },
        achievements: { page: 1 },
        shitposts: { page: 1, status: 'all' },
        toprimasti: { page: 1, status: 'all' },
        reports: { page: 1, source: 'all', status: 'open' },
        cache: { users: [], characters: [], achievements: [], shitposts: [], toprimasti: [], reports: [] }
    };

    let toastTimer = null;
    let modal = null;
    let confirmModal = null;
    let confirmAction = null;

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

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
        if (!value) return '—';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
    };

    const formatDateTime = (value) => {
        if (!value) return '—';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleString('it-IT', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    };

    const showToast = (message, isError = false) => {
        const toast = $('#adminToast');
        if (!toast) return;
        toast.textContent = message;
        toast.classList.toggle('is-error', isError);
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2600);
    };

    const api = async (endpoint, options = {}) => {
        const url = endpoint.startsWith('http') ? endpoint : `${apiBase}/${endpoint}`;
        const headers = { 'X-CSRF-Token': csrf, 'X-Requested-With': 'fetch', ...(options.headers || {}) };
        let bodyPayload = options.body;
        if (bodyPayload && !(bodyPayload instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            bodyPayload = JSON.stringify(bodyPayload);
        }
        let response;
        try {
            response = await fetch(url, { ...options, headers, body: bodyPayload, cache: 'no-store' });
        } catch (error) {
            throw new Error('API non raggiungibile. Controlla che /api/admin sia stato caricato.');
        }

        const contentType = response.headers.get('content-type') || '';
        let data;

        if (contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            const clean = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
            data = { ok: response.ok, message: clean || `HTTP ${response.status}` };
        }

        if (!response.ok || data.ok === false) throw new Error(data.message || `HTTP ${response.status}`);
        return data;
    };

    const setLoading = (container, rows = 4) => {
        if (!container) return;
        container.innerHTML = `<div class="admin-stack">${Array.from({ length: rows }).map(() => '<div class="admin-row-card"><div class="admin-row-main"><span class="admin-avatar"></span><div><div class="admin-row-title">Caricamento...</div><div class="admin-row-sub">Attendi</div></div></div></div>').join('')}</div>`;
    };

    const emptyState = (icon, title, text = '') => `
        <div class="admin-empty">
            <i class="${icon}"></i>
            <strong>${escapeHtml(title)}</strong>
            ${text ? `<span>${escapeHtml(text)}</span>` : ''}
        </div>
    `;

    const roleBadge = (role) => {
        const cls = role === 'owner' ? 'admin-badge--warning' : role === 'admin' ? 'admin-badge--info' : '';
        return `<span class="admin-badge ${cls}">${escapeHtml(role || 'utente')}</span>`;
    };

    const statusBadge = (isBanned) => isBanned == 1
        ? '<span class="admin-badge admin-badge--danger"><i class="fas fa-ban"></i>Bannato</span>'
        : '<span class="admin-badge admin-badge--success"><i class="fas fa-check"></i>Attivo</span>';


    const assetUrl = (value) => {
        value = String(value || '').trim();
        if (!value) return '';
        if (/^https?:\/\//i.test(value)) return value;
        if (value.startsWith('/')) return value;
        if (value.startsWith('img/')) return `/${value}`;
        return `/img/${value.replace(/^\/+/, '')}`;
    };

    const thumb = (url, icon = 'fas fa-image') => {
        const src = assetUrl(url);
        return src
            ? `<span class="admin-thumb"><img src="${escapeHtml(src)}" alt="" loading="lazy" onerror="this.closest('.admin-thumb').classList.add('is-broken'); this.remove();"></span>`
            : `<span class="admin-thumb admin-thumb--fallback"><i class="${icon}"></i></span>`;
    };

    const closeButtons = (root = document) => {
        $$('[data-admin-close], [data-admin-close="1"]', root).forEach((button) => {
            if (button.dataset.adminCloseBound === '1') return;
            button.dataset.adminCloseBound = '1';
            button.addEventListener('click', () => {
                const modalEl = button.closest('.admin-modal');
                if (!modalEl) return;
                if (modalEl.id === 'confirmModal') confirmModal?.hide();
                else modal?.hide();
            });
        });
    };

    const createFallbackModal = (element) => ({
        show() {
            if (!element) return;
            element.classList.add('is-open');
            element.style.display = 'block';
            element.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        },
        hide() {
            if (!element) return;
            element.classList.remove('is-open');
            element.style.display = 'none';
            element.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }
    });

    const initModalInstances = () => {
        const adminModalElement = $('#adminModal');
        const confirmModalElement = $('#confirmModal');

        if (window.bootstrap && window.bootstrap.Modal) {
            modal = adminModalElement ? new window.bootstrap.Modal(adminModalElement) : createFallbackModal(adminModalElement);
            confirmModal = confirmModalElement ? new window.bootstrap.Modal(confirmModalElement) : createFallbackModal(confirmModalElement);
            return;
        }

        modal = createFallbackModal(adminModalElement);
        confirmModal = createFallbackModal(confirmModalElement);

        $$('[data-admin-close="1"]').forEach((button) => {
            button.addEventListener('click', () => {
                button.closest('.modal')?.classList.contains('admin-modal') && createFallbackModal(button.closest('.modal')).hide();
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            modal?.hide();
            confirmModal?.hide();
        });
    };

    const openModal = (title, subtitle, bodyHtml, footerHtml = '') => {
        const titleEl = $('#adminModalTitle');
        const subtitleEl = $('#adminModalSubtitle');
        const bodyEl = $('#adminModalBody');
        const footerEl = $('#adminModalFooter');

        if (!titleEl || !bodyEl || !footerEl || !modal) {
            showToast('Modal admin non inizializzato. Ricarica la pagina.', true);
            return;
        }

        titleEl.textContent = title;
        if (subtitleEl) subtitleEl.textContent = subtitle || '';
        bodyEl.innerHTML = bodyHtml;
        footerEl.innerHTML = footerHtml;
        closeButtons(footerEl);
        closeButtons(bodyEl);
        modal.show();
    };

    const closeModal = () => modal?.hide();

    const confirmBox = (title, bodyHtml, action) => {
        const titleEl = $('#confirmTitle');
        const bodyEl = $('#confirmBody');

        if (!titleEl || !bodyEl || !confirmModal) {
            showToast('Conferma non disponibile. Ricarica la pagina.', true);
            return;
        }

        titleEl.textContent = title;
        bodyEl.innerHTML = bodyHtml;
        closeButtons(bodyEl);
        confirmAction = action;
        confirmModal.show();
    };

    const pagination = (target, data, onPage) => {
        const box = $(target);
        if (!box || !data || data.pages <= 1) {
            if (box) box.innerHTML = '';
            return;
        }
        const page = Number(data.page || 1);
        const pages = Number(data.pages || 1);
        const items = [];
        const start = Math.max(1, page - 2);
        const end = Math.min(pages, page + 2);
        items.push(`<button ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}"><i class="fas fa-chevron-left"></i></button>`);
        for (let i = start; i <= end; i++) items.push(`<button class="${i === page ? 'is-active' : ''}" data-page="${i}">${i}</button>`);
        items.push(`<button ${page >= pages ? 'disabled' : ''} data-page="${page + 1}"><i class="fas fa-chevron-right"></i></button>`);
        box.innerHTML = items.join('');
        $$('button[data-page]', box).forEach((btn) => btn.addEventListener('click', () => onPage(Number(btn.dataset.page))));
    };

    const loadDashboard = async () => {
        try {
            const data = await api('get_stats.php');
            const stats = data.stats || {};
            $('#adminStatsGrid').innerHTML = [
                ['fas fa-users', stats.users, 'Utenti totali'],
                ['fas fa-ban', stats.banned, 'Bannati'],
                ['fas fa-box-open', stats.characters, 'Personaggi'],
                ['fas fa-trophy', stats.achievements, 'Achievement'],
                ['fas fa-user-shield', stats.admins, 'Admin / owner'],
                ['fas fa-layer-group', stats.inventory_rows, 'Inventario'],
                ['fas fa-medal', stats.unlocked_achievements, 'Sblocchi'],
                ['fas fa-image', stats.shitposts, 'Shitpost'],
                ['fas fa-ranking-star', stats.toprimasti, 'Top Rimasti'],
                ['fas fa-flag', stats.content_reports_open, 'Report contenuti aperti'],
                ['fas fa-message-exclamation', stats.chat_reports_open, 'Report chat aperti'],
            ].map(([icon, value, label]) => `
                <article class="admin-stat-card"><i class="${icon}"></i><strong>${compactNumber(value)}</strong><span>${label}</span></article>
            `).join('');

            $('#latestUsersBox').innerHTML = (data.latest_users || []).length ? data.latest_users.map((user) => `
                <div class="admin-row-card">
                    <div class="admin-row-main">
                        <img class="admin-avatar" src="/includes/get_pfp.php?id=${Number(user.id)}" alt="">
                        <div class="admin-cell-text">
                            <div class="admin-row-title">${escapeHtml(user.username)}</div>
                            <div class="admin-row-sub">#${Number(user.id)} · ${formatDate(user.data_creazione)}</div>
                        </div>
                    </div>
                    <div class="admin-row-actions">${roleBadge(user.ruolo)}${statusBadge(user.isBannato)}</div>
                </div>
            `).join('') : emptyState('fas fa-user', 'Nessun utente');

            await loadLogs(true);
        } catch (error) {
            const grid = $('#adminStatsGrid');
            if (grid) {
                grid.innerHTML = `<article class="admin-stat-card admin-stat-card--error"><i class="fas fa-triangle-exclamation"></i><strong>Errore</strong><span>${escapeHtml(error.message)}</span></article>`;
            }
            const latest = $('#latestUsersBox');
            const logs = $('#dashboardLogsBox');
            if (latest) latest.innerHTML = emptyState('fas fa-triangle-exclamation', 'Utenti non caricati', error.message);
            if (logs) logs.innerHTML = emptyState('fas fa-triangle-exclamation', 'Log non caricati');
            showToast(error.message, true);
        }
    };

    const userRow = (user) => `
        <tr>
            <td data-label="Utente">
                <div class="admin-cell-user">
                    <img class="admin-avatar" src="${escapeHtml(user.avatar_url)}" alt="">
                    <div class="admin-cell-text">
                        <div class="admin-row-title">${escapeHtml(user.username)}</div>
                        <div class="admin-row-sub">#${Number(user.id)} · ${escapeHtml(user.email)}</div>
                    </div>
                </div>
            </td>
            <td data-label="Ruolo">${roleBadge(user.ruolo)}</td>
            <td data-label="Stato">${statusBadge(user.isBannato)}</td>
            <td data-label="Stats"><span class="admin-muted">Pull</span> <b>${compactNumber(user.pull_count)}</b><br><span class="admin-muted">Badge</span> <b>${compactNumber(user.achievement_count)}</b></td>
            <td data-label="Data" class="admin-nowrap">${formatDate(user.data_creazione)}</td>
            <td data-label="Azioni"><div class="admin-row-actions">
                <button class="admin-btn admin-btn--small" data-action="details" data-id="${Number(user.id)}"><i class="fas fa-eye"></i> Dettagli</button>
                <button class="admin-btn admin-btn--small" data-action="edit" data-id="${Number(user.id)}"><i class="fas fa-pen"></i> Modifica</button>
                ${user.isBannato == 1
                    ? `<button class="admin-btn admin-btn--small" data-action="unban" data-id="${Number(user.id)}"><i class="fas fa-check"></i> Sbanna</button>`
                    : `<button class="admin-btn admin-btn--small admin-btn--danger" data-action="ban" data-id="${Number(user.id)}"><i class="fas fa-ban"></i> Banna</button>`}
            </div></td>
        </tr>
    `;

    const loadUsers = async () => {
        const box = $('#usersTable');
        setLoading(box);
        try {
            const params = new URLSearchParams({
                q: state.q,
                status: state.users.status,
                role: state.users.role,
                page: state.users.page,
                sort: state.users.sort,
                dir: state.users.dir,
                limit: 20
            });
            const data = await api(`get_users.php?${params}`);
            state.cache.users = data.users || [];
            box.innerHTML = state.cache.users.length ? `
                <table class="admin-table">
                    <thead><tr><th>Utente</th><th>Ruolo</th><th>Stato</th><th>Stats</th><th>Data</th><th>Azioni</th></tr></thead>
                    <tbody>${state.cache.users.map(userRow).join('')}</tbody>
                </table>
            ` : emptyState('fas fa-users', 'Nessun utente trovato', 'Prova a cambiare ricerca o filtri.');
            bindUserActions(box);
            pagination('#usersPagination', data.pagination, (page) => { state.users.page = page; loadUsers(); });
        } catch (error) {
            box.innerHTML = emptyState('fas fa-triangle-exclamation', 'Errore utenti', error.message);
        }
    };

    const bindUserActions = (root) => {
        $$('[data-action]', root).forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = Number(btn.dataset.id);
                const action = btn.dataset.action;
                if (action === 'details') openUserDetails(id);
                if (action === 'edit') openUserEdit(id);
                if (action === 'ban') openBan(id);
                if (action === 'unban') runUnban(id);
            });
        });
    };

    const openUserDetails = async (id) => {
        try {
            const data = await api(`get_user_details.php?id=${id}`);
            const user = data.user;
            const inv = data.inventory || [];
            const ach = data.achievements || [];
            openModal(`@${user.username}`, `ID ${user.id}`, `
                <div class="admin-detail-grid">
                    <aside class="admin-detail-side">
                        <img class="admin-detail-avatar" src="${escapeHtml(user.avatar_url)}" alt="">
                        <h3>${escapeHtml(user.username)}</h3>
                        <p>${escapeHtml(user.email)}</p>
                        <div class="admin-row-actions" style="justify-content:center">${roleBadge(user.ruolo)}${statusBadge(user.isBannato)}</div>
                        ${user.motivo_ban ? `<p class="admin-muted">Motivo ban: ${escapeHtml(user.motivo_ban)}</p>` : ''}
                    </aside>
                    <div class="admin-detail-tabs">
                        <div class="admin-toolbar"><div><strong>Inventario</strong><small>${inv.length} personaggi mostrati</small></div><button class="admin-btn admin-btn--primary" id="quickAddCharacter"><i class="fas fa-plus"></i> Personaggio</button></div>
                        <div class="admin-mini-grid">${inv.length ? inv.map((item) => `<div class="admin-mini-card"><strong>${escapeHtml(item.nome)}</strong><span>${escapeHtml(item.rarita || '—')} · x${Number(item.quantita || 1)}</span><button class="admin-btn admin-btn--small admin-btn--danger" data-remove-character="${Number(item.id)}"><i class="fas fa-trash"></i> Rimuovi</button></div>`).join('') : emptyState('fas fa-box-open', 'Inventario vuoto')}</div>
                        <div class="admin-toolbar"><div><strong>Achievement</strong><small>${ach.length} achievement mostrati</small></div><button class="admin-btn admin-btn--primary" id="quickAddAchievement"><i class="fas fa-plus"></i> Achievement</button></div>
                        <div class="admin-mini-grid">${ach.length ? ach.map((item) => `<div class="admin-mini-card"><strong>${escapeHtml(item.nome)}</strong><span>${Number(item.punti || 0)} punti</span><button class="admin-btn admin-btn--small admin-btn--danger" data-remove-achievement="${Number(item.id)}"><i class="fas fa-trash"></i> Rimuovi</button></div>`).join('') : emptyState('fas fa-trophy', 'Nessun achievement')}</div>
                    </div>
                </div>
            `);
            $('#quickAddCharacter')?.addEventListener('click', () => openAssignCharacter(user.id));
            $('#quickAddAchievement')?.addEventListener('click', () => openAssignAchievement(user.id));
            $$('[data-remove-character]').forEach((btn) => btn.addEventListener('click', () => removeCharacter(user.id, Number(btn.dataset.removeCharacter))));
            $$('[data-remove-achievement]').forEach((btn) => btn.addEventListener('click', () => removeAchievement(user.id, Number(btn.dataset.removeAchievement))));
        } catch (error) { showToast(error.message, true); }
    };

    const openUserEdit = async (id) => {
        const user = state.cache.users.find((u) => Number(u.id) === id) || (await api(`get_user_details.php?id=${id}`)).user;
        openModal('Modifica utente', `ID ${user.id}`, `
            <form id="userEditForm" class="admin-form-grid">
                <input type="hidden" name="id" value="${Number(user.id)}">
                <div class="admin-field"><label>Username</label><input name="username" value="${escapeHtml(user.username)}" required maxlength="20"></div>
                <div class="admin-field"><label>Email</label><input type="email" name="email" value="${escapeHtml(user.email)}" required></div>
                <div class="admin-field"><label>Ruolo</label><select name="ruolo">
                    <option value="utente" ${user.ruolo === 'utente' ? 'selected' : ''}>utente</option>
                    <option value="admin" ${user.ruolo === 'admin' ? 'selected' : ''} ${adminRole !== 'owner' ? 'disabled' : ''}>admin</option>
                    <option value="owner" ${user.ruolo === 'owner' ? 'selected' : ''} ${adminRole !== 'owner' ? 'disabled' : ''}>owner</option>
                </select></div>
            </form>
        `, `<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" id="saveUserBtn">Salva</button>`);
        $('#saveUserBtn').addEventListener('click', async () => {
            const form = $('#userEditForm');
            const payload = Object.fromEntries(new FormData(form).entries());
            try { await api('update_user.php', { method: 'POST', body: payload }); closeModal(); showToast('Utente aggiornato.'); loadUsers(); loadDashboard(); }
            catch (error) { showToast(error.message, true); }
        });
    };

    const openBan = (id) => {
        confirmBox('Bannare utente?', `
            <p class="admin-muted">Inserisci un motivo breve. Sarà salvato nei log.</p>
            <div class="admin-field"><label>Motivo</label><textarea id="banReason" maxlength="255" placeholder="Spam, comportamento scorretto..."></textarea></div>
        `, async () => {
            await api('ban_user.php', { method: 'POST', body: { id, reason: $('#banReason')?.value || '' } });
            showToast('Utente bannato.'); loadUsers(); loadDashboard();
        });
    };

    const runUnban = (id) => confirmBox('Sbannare utente?', '<p class="admin-muted">L’utente potrà accedere di nuovo.</p>', async () => {
        await api('unban_user.php', { method: 'POST', body: { id } });
        showToast('Utente sbannato.'); loadUsers(); loadDashboard();
    });

    const loadCharacters = async () => {
        const box = $('#charactersTable'); setLoading(box);
        try {
            const params = new URLSearchParams({ q: state.q, page: state.characters.page, limit: 30 });
            const data = await api(`get_characters.php?${params}`);
            state.cache.characters = data.characters || [];
            box.innerHTML = state.cache.characters.length ? `
                <table class="admin-table"><thead><tr><th>Nome</th><th>Rarità</th><th>Categoria</th><th>Azioni</th></tr></thead><tbody>
                    ${state.cache.characters.map((c) => `<tr><td data-label="Nome"><div class="admin-name-cell">${thumb(c.image_url || c.img_url, 'fas fa-box-open')}<div><div class="admin-row-title">${escapeHtml(c.nome)}</div><div class="admin-row-sub">#${Number(c.id)}</div></div></div></td><td data-label="Rarità">${escapeHtml(c.rarita || '—')}</td><td data-label="Categoria">${escapeHtml(c.categoria || '—')}</td><td data-label="Azioni"><div class="admin-row-actions"><button class="admin-btn admin-btn--small" data-edit-character="${Number(c.id)}"><i class="fas fa-pen"></i> Modifica</button><button class="admin-btn admin-btn--small admin-btn--danger" data-delete-character="${Number(c.id)}"><i class="fas fa-trash"></i> Elimina</button></div></td></tr>`).join('')}
                </tbody></table>` : emptyState('fas fa-box-open', 'Nessun personaggio');
            $$('[data-edit-character]', box).forEach((b) => b.addEventListener('click', () => openCharacterForm(state.cache.characters.find((c) => Number(c.id) === Number(b.dataset.editCharacter)))));
            $$('[data-delete-character]', box).forEach((b) => b.addEventListener('click', () => deleteCharacter(Number(b.dataset.deleteCharacter))));
            pagination('#charactersPagination', data.pagination, (page) => { state.characters.page = page; loadCharacters(); });
        } catch (error) { box.innerHTML = emptyState('fas fa-triangle-exclamation', 'Errore personaggi', error.message); }
    };

    const characterFormHtml = (item = {}) => `
        <form id="characterForm" class="admin-form-grid">
            ${item.id ? `<input type="hidden" name="id" value="${Number(item.id)}">` : ''}
            <div class="admin-field"><label>Nome</label><input name="nome" value="${escapeHtml(item.nome || '')}" required maxlength="80"></div>
            <div class="admin-field"><label>Rarità</label><input name="rarita" value="${escapeHtml(item.rarita || '')}" placeholder="comune, raro, epico..."></div>
            <div class="admin-field"><label>Categoria</label><input name="categoria" value="${escapeHtml(item.categoria || '')}" placeholder="anime, poppy..."></div>
            <div class="admin-field"><label>Immagine URL</label><input name="img_url" value="${escapeHtml(item.img_url || '')}" placeholder="https://..."></div>
            <div class="admin-field admin-field--full"><label>Audio URL</label><input name="audio_url" value="${escapeHtml(item.audio_url || '')}" placeholder="https://..."></div>
        </form>`;

    const openCharacterForm = (item = null) => {
        openModal(item ? 'Modifica personaggio' : 'Nuovo personaggio', item ? `ID ${item.id}` : '', characterFormHtml(item || {}), `<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" id="saveCharacterBtn">Salva</button>`);
        $('#saveCharacterBtn')?.addEventListener('click', async () => {
            const form = $('#characterForm');
            if (!form) return;
            const payload = Object.fromEntries(new FormData(form).entries());
            try { await api(item ? 'update_character.php' : 'create_character.php', { method: 'POST', body: payload }); closeModal(); showToast('Personaggio salvato.'); loadCharacters(); loadDashboard(); }
            catch (error) { showToast(error.message, true); }
        });
    };

    const deleteCharacter = (id) => confirmBox('Eliminare personaggio?', '<p class="admin-muted">Verrà rimosso anche dagli inventari utenti.</p>', async () => {
        await api('delete_character.php', { method: 'POST', body: { id } });
        showToast('Personaggio eliminato.'); loadCharacters(); loadDashboard();
    });

    const loadAchievements = async () => {
        const box = $('#achievementsTable'); setLoading(box);
        try {
            const params = new URLSearchParams({ q: state.q, page: state.achievements.page, limit: 30 });
            const data = await api(`get_achievements.php?${params}`);
            state.cache.achievements = data.achievements || [];
            box.innerHTML = state.cache.achievements.length ? `
                <table class="admin-table"><thead><tr><th>Nome</th><th>Descrizione</th><th>Punti</th><th>Azioni</th></tr></thead><tbody>
                    ${state.cache.achievements.map((a) => `<tr><td data-label="Nome"><div class="admin-name-cell">${thumb(a.image_url || a.img_url, 'fas fa-trophy')}<div><div class="admin-row-title">${escapeHtml(a.nome)}</div><div class="admin-row-sub">#${Number(a.id)}</div></div></div></td><td data-label="Descrizione">${escapeHtml(a.descrizione || '—')}</td><td data-label="Punti">${Number(a.punti || 0)}</td><td data-label="Azioni"><div class="admin-row-actions"><button class="admin-btn admin-btn--small" data-edit-achievement="${Number(a.id)}"><i class="fas fa-pen"></i> Modifica</button><button class="admin-btn admin-btn--small admin-btn--danger" data-delete-achievement="${Number(a.id)}"><i class="fas fa-trash"></i> Elimina</button></div></td></tr>`).join('')}
                </tbody></table>` : emptyState('fas fa-trophy', 'Nessun achievement');
            $$('[data-edit-achievement]', box).forEach((b) => b.addEventListener('click', () => openAchievementForm(state.cache.achievements.find((a) => Number(a.id) === Number(b.dataset.editAchievement)))));
            $$('[data-delete-achievement]', box).forEach((b) => b.addEventListener('click', () => deleteAchievement(Number(b.dataset.deleteAchievement))));
            pagination('#achievementsPagination', data.pagination, (page) => { state.achievements.page = page; loadAchievements(); });
        } catch (error) { box.innerHTML = emptyState('fas fa-triangle-exclamation', 'Errore achievement', error.message); }
    };

    const achievementFormHtml = (item = {}) => `
        <form id="achievementForm" class="admin-form-grid">
            ${item.id ? `<input type="hidden" name="id" value="${Number(item.id)}">` : ''}
            <div class="admin-field"><label>Nome</label><input name="nome" value="${escapeHtml(item.nome || '')}" required maxlength="90"></div>
            <div class="admin-field"><label>Punti</label><input type="number" min="0" name="punti" value="${Number(item.punti || 0)}"></div>
            <div class="admin-field admin-field--full"><label>Immagine / icona</label><input name="img_url" value="${escapeHtml(item.img_url || '')}" placeholder="badge.png o https://..."></div>
            <div class="admin-field admin-field--full"><label>Descrizione</label><textarea name="descrizione" maxlength="255">${escapeHtml(item.descrizione || '')}</textarea></div>
        </form>`;

    const openAchievementForm = (item = null) => {
        openModal(item ? 'Modifica achievement' : 'Nuovo achievement', item ? `ID ${item.id}` : '', achievementFormHtml(item || {}), `<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" id="saveAchievementBtn">Salva</button>`);
        $('#saveAchievementBtn')?.addEventListener('click', async () => {
            const form = $('#achievementForm');
            if (!form) return;
            const payload = Object.fromEntries(new FormData(form).entries());
            try { await api(item ? 'update_achievement.php' : 'create_achievement.php', { method: 'POST', body: payload }); closeModal(); showToast('Achievement salvato.'); loadAchievements(); loadDashboard(); }
            catch (error) { showToast(error.message, true); }
        });
    };

    const deleteAchievement = (id) => confirmBox('Eliminare achievement?', '<p class="admin-muted">Verrà rimosso anche dagli utenti che lo hanno sbloccato.</p>', async () => {
        await api('delete_achievement.php', { method: 'POST', body: { id } });
        showToast('Achievement eliminato.'); loadAchievements(); loadDashboard();
    });

    const openAssignCharacter = async (userId) => {
        if (!state.cache.characters.length) await loadCharacters();
        openModal('Aggiungi personaggio', `Utente #${userId}`, `
            <form id="assignCharacterForm" class="admin-form-grid">
                <input type="hidden" name="user_id" value="${Number(userId)}">
                <div class="admin-field admin-field--full"><label>Personaggio</label><select name="character_id" required>${state.cache.characters.map((c) => `<option value="${Number(c.id)}">${escapeHtml(c.nome)}</option>`).join('')}</select></div>
                <div class="admin-field"><label>Quantità</label><input type="number" name="quantity" value="1" min="1" max="9999"></div>
            </form>
        `, `<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" id="assignCharacterBtn">Aggiungi</button>`);
        $('#assignCharacterBtn').addEventListener('click', async () => {
            const payload = Object.fromEntries(new FormData($('#assignCharacterForm')).entries());
            try { await api('add_character_to_user.php', { method: 'POST', body: payload }); closeModal(); showToast('Personaggio aggiunto.'); openUserDetails(userId); }
            catch (error) { showToast(error.message, true); }
        });
    };

    const openAssignAchievement = async (userId) => {
        if (!state.cache.achievements.length) await loadAchievements();
        openModal('Assegna achievement', `Utente #${userId}`, `
            <form id="assignAchievementForm" class="admin-form-grid">
                <input type="hidden" name="user_id" value="${Number(userId)}">
                <div class="admin-field admin-field--full"><label>Achievement</label><select name="achievement_id" required>${state.cache.achievements.map((a) => `<option value="${Number(a.id)}">${escapeHtml(a.nome)}</option>`).join('')}</select></div>
            </form>
        `, `<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" id="assignAchievementBtn">Assegna</button>`);
        $('#assignAchievementBtn').addEventListener('click', async () => {
            const payload = Object.fromEntries(new FormData($('#assignAchievementForm')).entries());
            try { await api('add_achievement_to_user.php', { method: 'POST', body: payload }); closeModal(); showToast('Achievement assegnato.'); openUserDetails(userId); }
            catch (error) { showToast(error.message, true); }
        });
    };

    const removeCharacter = (userId, characterId) => confirmBox('Rimuovere personaggio?', '', async () => {
        await api('remove_character_from_user.php', { method: 'POST', body: { user_id: userId, character_id: characterId } });
        showToast('Personaggio rimosso.'); openUserDetails(userId);
    });

    const removeAchievement = (userId, achievementId) => confirmBox('Rimuovere achievement?', '', async () => {
        await api('remove_achievement_from_user.php', { method: 'POST', body: { user_id: userId, achievement_id: achievementId } });
        showToast('Achievement rimosso.'); openUserDetails(userId);
    });


    const approvalBadge = (approved) => Number(approved) === 1
        ? '<span class="admin-badge admin-badge--success"><i class="fas fa-check"></i>Approvato</span>'
        : '<span class="admin-badge admin-badge--warning"><i class="fas fa-clock"></i>In attesa</span>';

    const reportStatusBadge = (status) => {
        if (status === 'reviewed') return '<span class="admin-badge admin-badge--info"><i class="fas fa-check-double"></i>Revisionata</span>';
        if (status === 'dismissed') return '<span class="admin-badge"><i class="fas fa-eye-slash"></i>Ignorata</span>';
        return '<span class="admin-badge admin-badge--danger"><i class="fas fa-flag"></i>Aperta</span>';
    };

    const postMedia = (url, fallbackIcon = 'fas fa-image') => url
        ? `<img class="admin-content-thumb" src="${escapeHtml(url)}" alt="" loading="lazy">`
        : `<span class="admin-content-thumb admin-content-thumb--empty"><i class="${fallbackIcon}"></i></span>`;

    const contentMetrics = (post, mode = 'shitpost') => {
        const scoreLabel = mode === 'toprimasti' ? 'Voti' : 'Like';
        const score = mode === 'toprimasti' ? (post.votes_count || post.reazioni || 0) : (post.likes_count || 0);
        return `
            <div class="admin-metric-line">
                <span><i class="fas fa-fire"></i>${scoreLabel}: <b>${compactNumber(score)}</b></span>
                <span><i class="fas fa-comment"></i>Commenti: <b>${compactNumber(post.comments_count || 0)}</b></span>
                <span><i class="fas fa-eye"></i>Visite: <b>${compactNumber(post.views || 0)}</b></span>
                <span><i class="fas fa-bookmark"></i>Salvati: <b>${compactNumber(post.saves_count || 0)}</b></span>
                <span class="${Number(post.reports_count || 0) > 0 ? 'admin-danger-text' : ''}"><i class="fas fa-flag"></i>Report: <b>${compactNumber(post.reports_count || 0)}</b></span>
            </div>
        `;
    };

    const postTextCell = (post, extra = '', mode = 'shitpost') => `
        <div class="admin-name-cell">
            ${postMedia(post.media_url)}
            <div class="admin-cell-text">
                <div class="admin-row-title">${escapeHtml(post.titolo || 'Senza titolo')}</div>
                <div class="admin-row-sub">#${Number(post.id)} · ${escapeHtml(post.username || 'utente eliminato')} · ${formatDateTime(post.data_creazione)}</div>
                ${post.descrizione ? `<span>${escapeHtml(post.descrizione)}</span>` : ''}
                ${extra ? `<small>${escapeHtml(extra)}</small>` : ''}
                <div class="admin-content-flags">
                    ${post.tag ? `<em>#${escapeHtml(post.tag)}</em>` : ''}
                    ${Number(post.is_spoiler || 0) === 1 ? '<em>Spoiler</em>' : ''}
                    ${post.updated_at ? `<em>Agg. ${formatDateTime(post.updated_at)}</em>` : ''}
                </div>
                ${contentMetrics(post, mode)}
            </div>
        </div>
    `;

    const loadShitposts = async () => {
        const box = $('#shitpostsTable'); setLoading(box);
        try {
            const params = new URLSearchParams({ q: state.q, status: state.shitposts.status, page: state.shitposts.page, limit: 30 });
            const data = await api(`get_shitposts.php?${params}`);
            state.cache.shitposts = data.posts || [];

            box.innerHTML = state.cache.shitposts.length ? `
                <table class="admin-table"><thead><tr><th>Post</th><th>Stato</th><th>Dati</th><th>Azioni</th></tr></thead><tbody>
                    ${state.cache.shitposts.map((post) => `<tr>
                        <td data-label="Post">${postTextCell(post, '', 'shitpost')}</td>
                        <td data-label="Stato">${approvalBadge(post.approvato)}</td>
                        <td data-label="Dati"><b>${compactNumber(post.likes_count || 0)}</b> like<br><span class="admin-muted">${compactNumber(post.views || 0)} visite</span></td>
                        <td data-label="Azioni"><div class="admin-row-actions">
                            <button class="admin-btn admin-btn--small" data-edit-shitpost="${Number(post.id)}"><i class="fas fa-pen"></i> Modifica</button>
                            <button class="admin-btn admin-btn--small" data-toggle-shitpost="${Number(post.id)}" data-approved="${Number(post.approvato) === 1 ? 0 : 1}"><i class="fas ${Number(post.approvato) === 1 ? 'fa-xmark' : 'fa-check'}"></i> ${Number(post.approvato) === 1 ? 'Nascondi' : 'Approva'}</button>
                            <button class="admin-btn admin-btn--small" data-comments-shitpost="${Number(post.id)}"><i class="fas fa-comments"></i> Commenti</button>
                            <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-shitpost="${Number(post.id)}"><i class="fas fa-trash"></i> Elimina</button>
                        </div></td>
                    </tr>`).join('')}
                </tbody></table>` : emptyState('fas fa-image', 'Nessuno shitpost');

            $$('[data-edit-shitpost]', box).forEach((b) => b.addEventListener('click', () => openShitpostForm(state.cache.shitposts.find((p) => Number(p.id) === Number(b.dataset.editShitpost)))));
            $$('[data-toggle-shitpost]', box).forEach((b) => b.addEventListener('click', () => toggleShitpost(Number(b.dataset.toggleShitpost), Number(b.dataset.approved))));
            $$('[data-comments-shitpost]', box).forEach((b) => b.addEventListener('click', () => openShitpostComments(Number(b.dataset.commentsShitpost))));
            $$('[data-delete-shitpost]', box).forEach((b) => b.addEventListener('click', () => deleteShitpost(Number(b.dataset.deleteShitpost))));

            pagination('#shitpostsPagination', data.pagination, (page) => { state.shitposts.page = page; loadShitposts(); });
        } catch (error) {
            box.innerHTML = emptyState('fas fa-triangle-exclamation', 'Errore shitpost', error.message);
        }
    };

    const shitpostFormHtml = (item = {}) => `
        <form id="shitpostForm" class="admin-form-grid">
            <input type="hidden" name="id" value="${Number(item.id || 0)}">
            <div class="admin-field admin-field--full"><label>Titolo</label><input name="titolo" value="${escapeHtml(item.titolo || '')}" required maxlength="120"></div>
            <div class="admin-field admin-field--full"><label>Descrizione</label><textarea name="descrizione" maxlength="2000">${escapeHtml(item.descrizione || '')}</textarea></div>
            <div class="admin-field"><label>Tag</label><input name="tag" value="${escapeHtml(item.tag || '')}" maxlength="40" placeholder="meme, gaming..."></div>
            <label class="admin-check"><input type="checkbox" name="is_spoiler" value="1" ${Number(item.is_spoiler || 0) === 1 ? 'checked' : ''}><span>Spoiler</span></label>
        </form>`;

    const openShitpostForm = (item = null) => {
        openModal('Modifica shitpost', item ? `ID ${item.id}` : '', shitpostFormHtml(item || {}), `<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" id="saveShitpostBtn">Salva</button>`);
        $('#saveShitpostBtn')?.addEventListener('click', async () => {
            const form = $('#shitpostForm');
            if (!form) return;
            const payload = Object.fromEntries(new FormData(form).entries());
            payload.is_spoiler = form.querySelector('[name="is_spoiler"]')?.checked ? 1 : 0;
            try { await api('update_shitpost.php', { method: 'POST', body: payload }); closeModal(); showToast('Shitpost salvato.'); loadShitposts(); loadDashboard(); }
            catch (error) { showToast(error.message, true); }
        });
    };

    const toggleShitpost = (id, approved) => {
        confirmBox(approved ? 'Approvare shitpost?' : 'Nascondere shitpost?', `<p class="admin-muted">Post #${Number(id)}</p>`, async () => {
            await api('approve_shitpost.php', { method: 'POST', body: { id, approved } });
            showToast(approved ? 'Shitpost approvato.' : 'Shitpost nascosto.');
            loadShitposts(); loadReports(); loadDashboard();
        });
    };

    const deleteShitpost = (id) => {
        confirmBox('Eliminare shitpost?', '<p class="admin-muted">Verranno rimossi anche commenti, like, salvati e report collegati.</p>', async () => {
            await api('delete_shitpost_admin.php', { method: 'POST', body: { id } });
            showToast('Shitpost eliminato.');
            loadShitposts(); loadReports(); loadDashboard();
        });
    };

    const openShitpostComments = async (id) => {
        try {
            const data = await api(`get_shitpost_comments_admin.php?id=${encodeURIComponent(id)}`);
            const comments = data.comments || [];
            openModal('Commenti shitpost', `Post #${id}`, `
                <div class="admin-stack">
                    ${comments.length ? comments.map((comment) => `
                        <div class="admin-row-card">
                            <div class="admin-row-main">
                                <img class="admin-avatar" src="/includes/get_pfp.php?id=${Number(comment.id_utente)}" alt="">
                                <div class="admin-cell-text">
                                    <div class="admin-row-title">${escapeHtml(comment.username || 'utente eliminato')}</div>
                                    <div class="admin-row-sub">${formatDateTime(comment.data_commento)}</div>
                                    <span>${escapeHtml(comment.commento || '')}</span>
                                </div>
                            </div>
                            <div class="admin-row-actions">
                                <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-comment="${Number(comment.id)}"><i class="fas fa-trash"></i> Elimina</button>
                            </div>
                        </div>
                    `).join('') : emptyState('fas fa-comments', 'Nessun commento')}
                </div>
            `, `<button class="admin-btn" data-admin-close="1">Chiudi</button>`);
            $$('[data-delete-comment]', $('#adminModalBody')).forEach((b) => b.addEventListener('click', async () => {
                try {
                    await api('delete_shitpost_comment_admin.php', { method: 'POST', body: { id: Number(b.dataset.deleteComment) } });
                    showToast('Commento eliminato.');
                    openShitpostComments(id);
                    loadShitposts();
                } catch (error) { showToast(error.message, true); }
            }));
        } catch (error) {
            showToast(error.message, true);
        }
    };

    const loadToprimasti = async () => {
        const box = $('#toprimastiTable'); setLoading(box);
        try {
            const params = new URLSearchParams({ q: state.q, status: state.toprimasti.status, page: state.toprimasti.page, limit: 30 });
            const data = await api(`get_toprimasti.php?${params}`);
            state.cache.toprimasti = data.posts || [];

            box.innerHTML = state.cache.toprimasti.length ? `
                <table class="admin-table"><thead><tr><th>Post</th><th>Stato</th><th>Dati</th><th>Azioni</th></tr></thead><tbody>
                    ${state.cache.toprimasti.map((post) => `<tr>
                        <td data-label="Post">${postTextCell(post, post.motivazione ? `Motivazione: ${post.motivazione}` : '', 'toprimasti')}</td>
                        <td data-label="Stato">${approvalBadge(post.approvato)}</td>
                        <td data-label="Dati"><b>${compactNumber(post.votes_count || post.reazioni || 0)}</b> voti<br><span class="admin-muted">${compactNumber(post.views || 0)} visite</span></td>
                        <td data-label="Azioni"><div class="admin-row-actions">
                            <button class="admin-btn admin-btn--small" data-edit-toprimasti="${Number(post.id)}"><i class="fas fa-pen"></i> Modifica</button>
                            <button class="admin-btn admin-btn--small" data-toggle-toprimasti="${Number(post.id)}" data-approved="${Number(post.approvato) === 1 ? 0 : 1}"><i class="fas ${Number(post.approvato) === 1 ? 'fa-xmark' : 'fa-check'}"></i> ${Number(post.approvato) === 1 ? 'Nascondi' : 'Approva'}</button>
                            <button class="admin-btn admin-btn--small" data-reset-votes="${Number(post.id)}"><i class="fas fa-rotate-left"></i> Reset voti</button>
                            <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-toprimasti="${Number(post.id)}"><i class="fas fa-trash"></i> Elimina</button>
                        </div></td>
                    </tr>`).join('')}
                </tbody></table>` : emptyState('fas fa-ranking-star', 'Nessun post');

            $$('[data-edit-toprimasti]', box).forEach((b) => b.addEventListener('click', () => openToprimastiForm(state.cache.toprimasti.find((p) => Number(p.id) === Number(b.dataset.editToprimasti)))));
            $$('[data-toggle-toprimasti]', box).forEach((b) => b.addEventListener('click', () => toggleToprimasti(Number(b.dataset.toggleToprimasti), Number(b.dataset.approved))));
            $$('[data-reset-votes]', box).forEach((b) => b.addEventListener('click', () => resetToprimastiVotes(Number(b.dataset.resetVotes))));
            $$('[data-delete-toprimasti]', box).forEach((b) => b.addEventListener('click', () => deleteToprimasti(Number(b.dataset.deleteToprimasti))));

            pagination('#toprimastiPagination', data.pagination, (page) => { state.toprimasti.page = page; loadToprimasti(); });
        } catch (error) {
            box.innerHTML = emptyState('fas fa-triangle-exclamation', 'Errore Top Rimasti', error.message);
        }
    };

    const toprimastiFormHtml = (item = {}) => `
        <form id="toprimastiForm" class="admin-form-grid">
            <input type="hidden" name="id" value="${Number(item.id || 0)}">
            <div class="admin-field admin-field--full"><label>Titolo</label><input name="titolo" value="${escapeHtml(item.titolo || '')}" required maxlength="120"></div>
            <div class="admin-field admin-field--full"><label>Descrizione</label><textarea name="descrizione" maxlength="2000">${escapeHtml(item.descrizione || '')}</textarea></div>
            <div class="admin-field admin-field--full"><label>Motivazione</label><textarea name="motivazione" maxlength="2000">${escapeHtml(item.motivazione || '')}</textarea></div>
            <div class="admin-field"><label>Tag</label><input name="tag" value="${escapeHtml(item.tag || '')}" maxlength="40" placeholder="meme, lore..."></div>
            <label class="admin-check"><input type="checkbox" name="is_spoiler" value="1" ${Number(item.is_spoiler || 0) === 1 ? 'checked' : ''}><span>Spoiler</span></label>
        </form>`;

    const openToprimastiForm = (item = null) => {
        openModal('Modifica Top Rimasti', item ? `ID ${item.id}` : '', toprimastiFormHtml(item || {}), `<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" id="saveToprimastiBtn">Salva</button>`);
        $('#saveToprimastiBtn')?.addEventListener('click', async () => {
            const form = $('#toprimastiForm');
            if (!form) return;
            const payload = Object.fromEntries(new FormData(form).entries());
            payload.is_spoiler = form.querySelector('[name="is_spoiler"]')?.checked ? 1 : 0;
            try { await api('update_toprimasti.php', { method: 'POST', body: payload }); closeModal(); showToast('Top Rimasti salvato.'); loadToprimasti(); loadDashboard(); }
            catch (error) { showToast(error.message, true); }
        });
    };

    const toggleToprimasti = (id, approved) => {
        confirmBox(approved ? 'Approvare post?' : 'Nascondere post?', `<p class="admin-muted">Post #${Number(id)}</p>`, async () => {
            await api('approve_toprimasti.php', { method: 'POST', body: { id, approved } });
            showToast(approved ? 'Post approvato.' : 'Post nascosto.');
            loadToprimasti(); loadReports(); loadDashboard();
        });
    };

    const deleteToprimasti = (id) => {
        confirmBox('Eliminare Top Rimasti?', '<p class="admin-muted">Verranno rimossi anche voti, salvati e report collegati.</p>', async () => {
            await api('delete_toprimasti_admin.php', { method: 'POST', body: { id } });
            showToast('Post eliminato.');
            loadToprimasti(); loadReports(); loadDashboard();
        });
    };

    const resetToprimastiVotes = (id) => {
        confirmBox('Resettare voti?', '<p class="admin-muted">I voti del post torneranno a 0.</p>', async () => {
            await api('reset_toprimasti_votes.php', { method: 'POST', body: { id } });
            showToast('Voti resettati.');
            loadToprimasti(); loadDashboard();
        });
    };

    const loadReports = async () => {
        const box = $('#reportsTable');
        if (!box) return;
        setLoading(box);
        try {
            const params = new URLSearchParams({ q: state.q, source: state.reports.source, status: state.reports.status, page: state.reports.page, limit: 30 });
            const data = await api(`get_reports.php?${params}`);
            state.cache.reports = data.reports || [];

            box.innerHTML = state.cache.reports.length ? `
                <table class="admin-table"><thead><tr><th>Segnalazione</th><th>Target</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>
                    ${state.cache.reports.map((report) => `<tr>
                        <td data-label="Segnalazione">
                            <div class="admin-cell-text">
                                <div class="admin-row-title">${escapeHtml(report.report_source_label || report.report_source)}</div>
                                <div class="admin-row-sub">Da ${escapeHtml(report.reporter_username || 'utente eliminato')} · ${formatDateTime(report.created_at)}</div>
                                <span>${escapeHtml(report.reason || 'Nessun motivo')}</span>
                            </div>
                        </td>
                        <td data-label="Target">
                            <div class="admin-cell-text">
                                <div class="admin-row-title">${escapeHtml(report.target_title || 'Contenuto non trovato')}</div>
                                <div class="admin-row-sub">${escapeHtml(report.target_username || 'autore sconosciuto')} · #${Number(report.post_id || 0)}</div>
                                ${report.target_url ? `<small><a href="${escapeHtml(report.target_url)}" target="_blank" rel="noopener">Apri contenuto</a></small>` : ''}
                            </div>
                        </td>
                        <td data-label="Stato">${reportStatusBadge(report.status)}</td>
                        <td data-label="Azioni"><div class="admin-row-actions">
                            <button class="admin-btn admin-btn--small" data-report-status="${Number(report.id)}" data-source="${escapeHtml(report.report_source)}" data-status="reviewed"><i class="fas fa-check-double"></i> Revisionata</button>
                            <button class="admin-btn admin-btn--small" data-report-status="${Number(report.id)}" data-source="${escapeHtml(report.report_source)}" data-status="dismissed"><i class="fas fa-eye-slash"></i> Ignora</button>
                            <button class="admin-btn admin-btn--small" data-report-status="${Number(report.id)}" data-source="${escapeHtml(report.report_source)}" data-status="open"><i class="fas fa-flag"></i> Riapri</button>
                        </div></td>
                    </tr>`).join('')}
                </tbody></table>` : emptyState('fas fa-flag', 'Nessuna segnalazione');

            $$('[data-report-status]', box).forEach((btn) => btn.addEventListener('click', () => updateReportStatus(Number(btn.dataset.reportStatus), btn.dataset.source, btn.dataset.status)));
            pagination('#reportsPagination', data.pagination, (page) => { state.reports.page = page; loadReports(); });
        } catch (error) {
            box.innerHTML = emptyState('fas fa-triangle-exclamation', 'Errore segnalazioni', error.message);
        }
    };

    const updateReportStatus = async (id, source, status) => {
        try {
            await api('update_report_status.php', { method: 'POST', body: { id, source, status } });
            showToast('Segnalazione aggiornata.');
            loadReports(); loadDashboard();
        } catch (error) {
            showToast(error.message, true);
        }
    };


    const loadLogs = async (dashboardOnly = false) => {
        try {
            const data = await api('get_logs.php');
            const logs = data.logs || [];
            const small = logs.slice(0, 8).map((log) => `
                <div class="admin-row-card">
                    <div class="admin-row-main"><span class="admin-brand-mark" style="width:2.2rem;height:2.2rem"><i class="fas fa-bolt"></i></span><div><div class="admin-row-title">${escapeHtml(log.action)}</div><div class="admin-row-sub">${escapeHtml(log.admin_username || 'Admin')} · ${formatDateTime(log.created_at)}</div></div></div>
                </div>`).join('') || emptyState('fas fa-clock', 'Nessun log');
            $('#dashboardLogsBox').innerHTML = small;
            if (!dashboardOnly) {
                $('#logsTable').innerHTML = logs.length ? `
                    <table class="admin-table"><thead><tr><th>Azione</th><th>Admin</th><th>Target</th><th>Data</th><th>IP</th></tr></thead><tbody>
                    ${logs.map((log) => `<tr><td data-label="Azione"><b>${escapeHtml(log.action)}</b><div class="admin-row-sub">${escapeHtml(log.details || '')}</div></td><td data-label="Admin">${escapeHtml(log.admin_username || log.admin_id)}</td><td data-label="Target">${escapeHtml(log.target_username || log.target_user_id || '—')}</td><td data-label="Data">${formatDateTime(log.created_at)}</td><td data-label="IP">${escapeHtml(log.ip_address || '—')}</td></tr>`).join('')}
                    </tbody></table>` : emptyState('fas fa-clock-rotate-left', 'Nessun log');
            }
        } catch (error) {
            if (!dashboardOnly) $('#logsTable').innerHTML = emptyState('fas fa-triangle-exclamation', 'Errore log', error.message);
        }
    };

    const switchSection = (section) => {
        state.section = section;
        $$('[data-admin-nav] button').forEach((b) => b.classList.toggle('is-active', b.dataset.section === section));
        $$('[data-section-panel]').forEach((panel) => panel.classList.toggle('is-active', panel.dataset.sectionPanel === section));
        if (section === 'dashboard') loadDashboard();
        if (section === 'users') loadUsers();
        if (section === 'characters') loadCharacters();
        if (section === 'achievements') loadAchievements();
        if (section === 'shitposts') loadShitposts();
        if (section === 'toprimasti') loadToprimasti();
        if (section === 'reports') loadReports();
        if (section === 'logs') loadLogs();
    };

    const debounce = (fn, wait = 260) => {
        let t = null;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
    };

    const reloadCurrent = () => switchSection(state.section);

    document.addEventListener('DOMContentLoaded', () => {
        try {
            initModalInstances();
        closeButtons(document);

            $('#confirmActionBtn')?.addEventListener('click', async () => {
                if (!confirmAction) return;
                const btn = $('#confirmActionBtn');
                btn.disabled = true;
                try { await confirmAction(); confirmModal?.hide(); }
                catch (error) { showToast(error.message, true); }
                finally { btn.disabled = false; confirmAction = null; }
            });

            $$('[data-admin-nav] button').forEach((btn) => {
                btn.addEventListener('click', () => switchSection(btn.dataset.section));
            });

            $('#adminRefreshBtn')?.addEventListener('click', reloadCurrent);
            $('#createCharacterBtn')?.addEventListener('click', () => openCharacterForm());
            $('#createAchievementBtn')?.addEventListener('click', () => openAchievementForm());
            $('#usersStatusFilter')?.addEventListener('change', (e) => { state.users.status = e.target.value; state.users.page = 1; loadUsers(); });
            $('#usersRoleFilter')?.addEventListener('change', (e) => { state.users.role = e.target.value; state.users.page = 1; loadUsers(); });
            $('#shitpostsStatusFilter')?.addEventListener('change', (e) => { state.shitposts.status = e.target.value; state.shitposts.page = 1; loadShitposts(); });
            $('#toprimastiStatusFilter')?.addEventListener('change', (e) => { state.toprimasti.status = e.target.value; state.toprimasti.page = 1; loadToprimasti(); });
            $('#reportsSourceFilter')?.addEventListener('change', (e) => { state.reports.source = e.target.value; state.reports.page = 1; loadReports(); });
            $('#reportsStatusFilter')?.addEventListener('change', (e) => { state.reports.status = e.target.value; state.reports.page = 1; loadReports(); });

            $('#adminGlobalSearch')?.addEventListener('input', debounce((e) => {
                state.q = e.target.value.trim();
                state.users.page = state.characters.page = state.achievements.page = state.shitposts.page = state.toprimasti.page = state.reports.page = 1;
                if (state.section === 'dashboard') switchSection('users');
                else reloadCurrent();
            }, 300));

            loadDashboard();
        } catch (error) {
            console.error('Admin init error:', error);
            const grid = $('#adminStatsGrid');
            if (grid) {
                grid.innerHTML = `<article class="admin-stat-card admin-stat-card--error"><i class="fas fa-triangle-exclamation"></i><strong>Errore JS</strong><span>${escapeHtml(error.message)}</span></article>`;
            }
            showToast('Errore inizializzazione admin: ' + error.message, true);
        }
    });
})();
