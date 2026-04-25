(function () {
    const form = document.getElementById('profileEditForm');
    if (!form) return;

    const $ = (selector, parent = document) => parent.querySelector(selector);
    const $$ = (selector, parent = document) => Array.from(parent.querySelectorAll(selector));

    function readJson(id) {
        const node = document.getElementById(id);
        if (!node) return [];
        try {
            return JSON.parse(node.textContent || '[]');
        } catch (_) {
            return [];
        }
    }

    function hexToRgbLocal(hex) {
        const clean = String(hex || '').replace('#', '').trim();
        if (!/^[0-9a-fA-F]{6}$/.test(clean)) return '15, 91, 255';
        const value = parseInt(clean, 16);
        return `${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}`;
    }

    function escapeAttr(value) {
        return String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function boolAttr(value) {
        return Number(value) === 1 || value === true ? 'checked' : '';
    }

    const repeaters = {
        socials: $('#socialsRepeater'),
        links: $('#linksRepeater'),
        projects: $('#projectsRepeater'),
        contents: $('#contentsRepeater'),
        blocks: $('#blocksRepeater'),
    };

    const platformOptions = ['tiktok', 'instagram', 'youtube', 'twitch', 'github', 'discord', 'telegram', 'x', 'website', 'steam', 'other'];
    const projectStatuses = [['active', 'Attivo'], ['paused', 'In pausa'], ['finished', 'Finito'], ['idea', 'Idea']];
    const contentTypes = [['edit', 'Edit'], ['video', 'Video'], ['game', 'Gioco'], ['post', 'Post'], ['other', 'Altro']];
    const blockTypes = [['text', 'Testo'], ['image', 'Immagine'], ['gif', 'GIF'], ['video', 'Video']];

    function options(list, selected) {
        return list.map((item) => {
            const value = Array.isArray(item) ? item[0] : item;
            const label = Array.isArray(item) ? item[1] : item;
            return `<option value="${escapeAttr(value)}" ${String(selected || '') === String(value) ? 'selected' : ''}>${escapeAttr(label)}</option>`;
        }).join('');
    }

    function makeRow(type, data = {}) {
        const row = document.createElement('div');
        row.className = 'profile-row-card';
        row.dataset.rowType = type;

        let body = '';
        if (type === 'socials') {
            body = `
                <div class="profile-row-grid">
                    <label>Platform<select data-field="platform">${options(platformOptions, data.platform || 'website')}</select></label>
                    <label>Label<input data-field="label" maxlength="40" value="${escapeAttr(data.label || '')}" placeholder="TikTok"></label>
                    <label class="profile-row-grid full">URL<input data-field="url" value="${escapeAttr(data.url || '')}" placeholder="https://..."></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> Visibile</label>
                </div>`;
        }

        if (type === 'links') {
            body = `
                <div class="profile-row-grid">
                    <label>Titolo<input data-field="title" maxlength="60" value="${escapeAttr(data.title || '')}" placeholder="Portfolio"></label>
                    <label>Icona FontAwesome<input data-field="icon" maxlength="40" value="${escapeAttr(data.icon || 'fas fa-link')}"></label>
                    <label class="profile-row-grid full">Descrizione<input data-field="description" maxlength="160" value="${escapeAttr(data.description || '')}" placeholder="Una frase breve"></label>
                    <label class="profile-row-grid full">URL<input data-field="url" value="${escapeAttr(data.url || '')}" placeholder="https://..."></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_featured" ${boolAttr(data.is_featured)}> In evidenza</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> Visibile</label>
                </div>`;
        }

        if (type === 'projects') {
            body = `
                <div class="profile-row-grid">
                    <label>Titolo<input data-field="title" maxlength="70" value="${escapeAttr(data.title || '')}" placeholder="Nome progetto"></label>
                    <label>Stato<select data-field="status">${options(projectStatuses, data.status || 'active')}</select></label>
                    <label class="profile-row-grid full">Descrizione<textarea data-field="description" maxlength="260" placeholder="Cosa fa questo progetto">${escapeAttr(data.description || '')}</textarea></label>
                    <label>URL<input data-field="url" value="${escapeAttr(data.url || '')}" placeholder="https://..."></label>
                    <label>Immagine URL<input data-field="image_url" value="${escapeAttr(data.image_url || '')}" placeholder="https://..."></label>
                    <label class="profile-row-grid full">Tech stack<input data-field="tech_stack" maxlength="160" value="${escapeAttr(data.tech_stack || '')}" placeholder="PHP, JS, MySQL"></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_featured" ${boolAttr(data.is_featured)}> In evidenza</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> Visibile</label>
                </div>`;
        }

        if (type === 'contents') {
            body = `
                <div class="profile-row-grid">
                    <label>Tipo<select data-field="content_type">${options(contentTypes, data.content_type || 'edit')}</select></label>
                    <label>Titolo<input data-field="title" maxlength="70" value="${escapeAttr(data.title || '')}" placeholder="Titolo contenuto"></label>
                    <label class="profile-row-grid full">Descrizione<textarea data-field="description" maxlength="220" placeholder="Descrizione breve">${escapeAttr(data.description || '')}</textarea></label>
                    <label>URL<input data-field="url" value="${escapeAttr(data.url || '')}" placeholder="https://..."></label>
                    <label>Thumbnail URL<input data-field="thumbnail_url" value="${escapeAttr(data.thumbnail_url || '')}" placeholder="https://..."></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_featured" ${boolAttr(data.is_featured)}> In evidenza</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> Visibile</label>
                </div>`;
        }

        if (type === 'blocks') {
            body = `
                <div class="profile-row-grid">
                    <label>Tipo<select data-field="block_type">${options(blockTypes, data.block_type || 'text')}</select></label>
                    <label>Titolo<input data-field="title" maxlength="80" value="${escapeAttr(data.title || '')}" placeholder="Titolo opzionale"></label>
                    <label class="profile-row-grid full">Testo<textarea data-field="body" maxlength="700" placeholder="Testo breve, nota, descrizione o quote">${escapeAttr(data.body || '')}</textarea></label>
                    <label>Media URL<input data-field="media_url" value="${escapeAttr(data.media_url || '')}" placeholder="https://... immagine/gif/video"></label>
                    <label>Media type<select data-field="media_type">${options(blockTypes, data.media_type || data.block_type || 'image')}</select></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_featured" ${boolAttr(data.is_featured)}> Pin</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> Visibile</label>
                </div>`;
        }

        row.innerHTML = `
            <div class="profile-row-head">
                <strong>${type === 'socials' ? 'Social' : type === 'links' ? 'Link' : type === 'projects' ? 'Progetto' : type === 'blocks' ? 'Blocco custom' : 'Contenuto'}</strong>
                <button type="button" class="profile-remove-row">Rimuovi</button>
            </div>
            ${body}`;

        $('.profile-remove-row', row).addEventListener('click', () => row.remove());
        return row;
    }

    function addRow(type, data = {}) {
        if (!repeaters[type]) return;
        repeaters[type].appendChild(makeRow(type, data));
    }

    readJson('initialSocialsData').forEach((item) => addRow('socials', item));
    readJson('initialLinksData').forEach((item) => addRow('links', item));
    readJson('initialProjectsData').forEach((item) => addRow('projects', item));
    readJson('initialContentsData').forEach((item) => addRow('contents', item));
    readJson('initialBlocksData').forEach((item) => addRow('blocks', item));

    Object.entries(repeaters).forEach(([type, node]) => {
        if (node && node.children.length === 0) addRow(type, {});
    });

    $$('[data-add-row]').forEach((button) => {
        button.addEventListener('click', () => addRow(button.dataset.addRow, {}));
    });

    $$('[data-edit-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.editTab;
            $$('[data-edit-tab]').forEach((item) => item.classList.toggle('is-active', item === tab));
            $$('[data-edit-section]').forEach((section) => section.classList.toggle('is-active', section.dataset.editSection === target));
        });
    });

    function collectRows(type) {
        return $$('.profile-row-card', repeaters[type]).map((row) => {
            const obj = {};
            $$('[data-field]', row).forEach((input) => {
                const key = input.dataset.field;
                obj[key] = input.type === 'checkbox' ? input.checked : input.value.trim();
            });
            return obj;
        }).filter((obj) => Object.values(obj).some((value) => value !== '' && value !== false));
    }

    function collectBadges() {
        return $$('#badgePicker input[type="checkbox"]:checked').slice(0, 8).map((input) => Number(input.value));
    }

    const displayNameInput = $('#displayNameInput');
    const usernameInput = $('#usernameInput');
    const bioInput = $('#bioInput');
    const statusInput = $('#statusInput');
    const bioCounter = $('#bioCounter');
    const accentInput = $('#accentInput');
    const themeInput = $('#themeInput');
    const avatarInput = $('#avatarInput');
    const bannerInput = $('#bannerInput');
    const musicFileInput = $('#musicFileInput');
    const profileEffectInput = $('#profileEffectInput');
    const ringEnabledInput = $('#ringEnabledInput');
    const ringStyleInput = $('#ringStyleInput');
    const ringColorInput = $('#ringColorInput');

    function updatePreview() {
        const name = displayNameInput.value.trim() || usernameInput.value.trim() || 'Utente';
        $('#previewName').textContent = name;
        $('#previewUsername').textContent = '@' + (usernameInput.value.trim() || 'username');
        $('#previewBio').textContent = bioInput.value.trim() || 'La tua bio apparirà qui.';
        const statusBadge = $('#previewStatusBadge');
        if (statusBadge) {
            const status = statusInput && statusInput.value.trim() ? statusInput.value.trim() : 'Stato';
            statusBadge.innerHTML = `<i class="fas fa-signal"></i>${escapeAttr(status)}`;
        }        bioCounter.textContent = bioInput.value.length;
        document.documentElement.style.setProperty('--accent', accentInput.value);
        document.documentElement.style.setProperty('--accent-rgb', hexToRgbLocal(accentInput.value));
        document.documentElement.style.setProperty('--profile-accent', accentInput.value);
        document.body.dataset.accent = accentInput.value;
        document.body.dataset.theme = themeInput.value === 'auto' ? 'dark' : themeInput.value;
        document.body.dataset.profileEffect = profileEffectInput ? profileEffectInput.value : 'none';
        const wrap = $('#previewAvatarWrap');
        if (wrap) {
            const style = ringStyleInput ? ringStyleInput.value : 'spin';
            const enabled = ringEnabledInput ? ringEnabledInput.checked : true;
            wrap.className = `bio-avatar-wrap profile-preview-avatar-ring ring-style-${style} ${(!enabled || style === 'none') ? 'ring-disabled' : ''}`;
            wrap.style.setProperty('--profile-ring', ringColorInput ? ringColorInput.value : accentInput.value);
        }
    }

    [displayNameInput, usernameInput, bioInput, statusInput, accentInput, themeInput, profileEffectInput, ringEnabledInput, ringStyleInput, ringColorInput].filter(Boolean).forEach((input) => {
        input.addEventListener('input', updatePreview);
        input.addEventListener('change', updatePreview);
    });
    updatePreview();

    function previewAvatarFile(input, target) {
        const file = input.files && input.files[0];
        if (!file || !file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = () => { target.src = reader.result; };
        reader.readAsDataURL(file);
    }

    function previewBackgroundFile(input) {
        const file = input.files && input.files[0];
        if (!file) return;

        const background = document.querySelector('.bio-background');
        if (!background) return;

        const url = URL.createObjectURL(file);
        background.querySelectorAll('.bio-background__media, video').forEach((node) => node.remove());

        let media;
        if (file.type.startsWith('video/')) {
            media = document.createElement('video');
            media.autoplay = true;
            media.muted = true;
            media.loop = true;
            media.playsInline = true;
            const source = document.createElement('source');
            source.src = url;
            source.type = file.type;
            media.appendChild(source);
        } else if (file.type.startsWith('image/')) {
            media = document.createElement('img');
            media.src = url;
            media.alt = '';
        } else {
            window.profileToast('Formato sfondo non supportato.');
            URL.revokeObjectURL(url);
            return;
        }

        media.className = 'bio-background__media';
        background.prepend(media);
        window.profileToast('Anteprima sfondo aggiornata.');
    }

    function previewMusicFile(input) {
        const file = input.files && input.files[0];
        if (!file) return;
        const isMp3 = file.type === 'audio/mpeg' || file.name.toLowerCase().endsWith('.mp3');
        if (!isMp3) {
            window.profileToast('Usa solo file MP3.');
            input.value = '';
            return;
        }
        if (file.size > 12 * 1024 * 1024) {
            window.profileToast('MP3 troppo pesante. Max 12MB.');
            input.value = '';
            return;
        }
        const title = $('#musicTitleInput');
        if (title && !title.value.trim()) {
            title.value = file.name.replace(/\.mp3$/i, '');
        }
        window.profileToast('MP3 selezionato. Salva per applicarlo.');
    }

    avatarInput.addEventListener('change', () => previewAvatarFile(avatarInput, $('#previewAvatar')));
    bannerInput.addEventListener('change', () => previewBackgroundFile(bannerInput));
    if (musicFileInput) musicFileInput.addEventListener('change', () => previewMusicFile(musicFileInput));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        $('#socialsJson').value = JSON.stringify(collectRows('socials'));
        $('#linksJson').value = JSON.stringify(collectRows('links'));
        $('#projectsJson').value = JSON.stringify(collectRows('projects'));
        $('#contentsJson').value = JSON.stringify(collectRows('contents'));
        $('#blocksJson').value = JSON.stringify(collectRows('blocks'));
        $('#badgesJson').value = JSON.stringify(collectBadges());

        const button = $('#saveProfileButton');
        button.disabled = true;
        button.textContent = 'Salvataggio...';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'Errore salvataggio.');
            window.profileToast(data.message || 'Profilo salvato.');
            setTimeout(() => {
                window.location.href = data.profile_url || '/profile.php';
            }, 650);
        } catch (error) {
            window.profileToast(error.message || 'Errore salvataggio.');
        } finally {
            button.disabled = false;
            button.textContent = 'Salva profilo';
        }
    });
})();
