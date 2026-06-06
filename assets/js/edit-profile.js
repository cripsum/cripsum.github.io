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
        embeds: $('#embedsRepeater'),
        projects: $('#projectsRepeater'),
        contents: $('#contentsRepeater'),
        blocks: $('#blocksRepeater'),
    };

    const platformOptions = ['tiktok', 'instagram', 'youtube', 'twitch', 'github', 'discord', 'telegram', 'x', 'spotify', 'soundcloud', 'steam', 'reddit', 'pinterest', 'snapchat', 'facebook', 'linkedin', 'paypal', 'patreon', 'kick', 'bluesky', 'threads', 'behance', 'dribbble', 'website', 'email', 'other'];
    const projectStatuses = [['active', 'Attivo'], ['paused', 'In pausa'], ['finished', 'Finito'], ['idea', 'Idea']];
    const contentTypes = [['edit', 'Edit'], ['video', 'Video'], ['game', 'Gioco'], ['post', 'Post'], ['other', 'Altro']];
    const blockTypes = [['text', 'Testo'], ['image', 'Immagine'], ['gif', 'GIF'], ['video', 'Video']];
    const linkButtonStyles = [['card', 'Card'], ['compact', 'Compatto'], ['icon', 'Solo icona']];
    const embedTypes = [['spotify', 'Spotify'], ['youtube', 'YouTube Playlist/Video'], ['custom', 'Iframe custom/Safe Widget']];

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
                    <label>Username da mostrare<input data-field="display_username" maxlength="60" value="${escapeAttr(data.display_username || '')}" placeholder="@username / nome"></label>
                    <label class="profile-row-grid full">URL<input data-field="url" value="${escapeAttr(data.url || '')}" placeholder="https://..."></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> Visibile</label>
                </div>`;
        }

        if (type === 'links') {
            body = `
                <div class="profile-row-grid">
                    <label>Titolo<input data-field="title" maxlength="60" value="${escapeAttr(data.title || '')}" placeholder="Portfolio"></label>
                    <label>Icona FontAwesome<input data-field="icon" maxlength="40" value="${escapeAttr(data.icon || 'fas fa-link')}" placeholder="fab fa-spotify"></label>
                    <label>Tipo tasto<select data-field="button_style">${options(linkButtonStyles, data.button_style || 'card')}</select></label>
                    <label class="profile-row-grid full">Descrizione<input data-field="description" maxlength="160" value="${escapeAttr(data.description || '')}" placeholder="Una frase breve"></label>
                    <label class="profile-row-grid full">URL<input data-field="url" value="${escapeAttr(data.url || '')}" placeholder="https://..."></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_featured" ${boolAttr(data.is_featured)}> In evidenza</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> Visibile</label>
                </div>`;
        }

        if (type === 'embeds') {
            body = `
                <div class="profile-row-grid">
                    <label>Tipo Embed<select data-field="type">${options(embedTypes, data.type || 'spotify')}</select></label>
                    <label>Titolo (opzionale)<input data-field="title" maxlength="100" value="${escapeAttr(data.title || '')}" placeholder="Es. Spotify Playlist"></label>
                    <label class="profile-row-grid full">URL dell'embed (o URL classico Spotify/YouTube)<input data-field="url" value="${escapeAttr(data.url || '')}" placeholder="https://open.spotify.com/... o https://www.youtube.com/..."></label>
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
                    <label>Titolo<input data-field="title" maxlength="80" value="${escapeAttr(data.title || '')}" placeholder="Titolo del post"></label>
                    <label class="profile-row-grid full">Testo<textarea data-field="body" maxlength="700" placeholder="Testo breve, nota, descrizione o quote">${escapeAttr(data.body || '')}</textarea></label>
                    <label>Media URL<input data-field="media_url" value="${escapeAttr(data.media_url || '')}" placeholder="https://... immagine/gif/video"></label>
                    <label>Media type<select data-field="media_type">${options(blockTypes, data.media_type || data.block_type || 'image')}</select></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_featured" ${boolAttr(data.is_featured)}> Pin</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> Visibile</label>
                </div>`;
        }

        row.innerHTML = `
            <div class="profile-row-head">
                <strong>${type === 'socials' ? 'Social' : type === 'links' ? 'Link' : type === 'embeds' ? 'Embed' : type === 'projects' ? 'Progetto' : type === 'blocks' ? 'Blocco' : 'Contenuto'}</strong>
                <div class="profile-row-actions">
                    <button type="button" class="profile-move-up" title="Sposta su"><i class="fas fa-arrow-up"></i></button>
                    <button type="button" class="profile-move-down" title="Sposta giù"><i class="fas fa-arrow-down"></i></button>
                    <button type="button" class="profile-remove-row">Rimuovi</button>
                </div>
            </div>
            ${body}`;

        $('.profile-remove-row', row).addEventListener('click', () => {
            row.remove();
            updatePreview();
        });

        const btnUp = $('.profile-move-up', row);
        const btnDown = $('.profile-move-down', row);
        
        btnUp.addEventListener('click', () => {
            const prev = row.previousElementSibling;
            if (prev && prev.classList.contains('profile-row-card')) {
                row.parentNode.insertBefore(row, prev);
                updatePreview();
            }
        });
        
        btnDown.addEventListener('click', () => {
            const next = row.nextElementSibling;
            if (next && next.classList.contains('profile-row-card')) {
                row.parentNode.insertBefore(next, row);
                updatePreview();
            }
        });

        return row;
    }

    function addRow(type, data = {}) {
        if (!repeaters[type]) return;
        repeaters[type].appendChild(makeRow(type, data));
    }

    readJson('initialSocialsData').forEach((item) => addRow('socials', item));
    readJson('initialLinksData').forEach((item) => addRow('links', item));
    readJson('initialEmbedsData').forEach((item) => addRow('embeds', item));
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
        return $$('#badgeSortList .badge-select-chk:checked').map(chk => chk.dataset.id).slice(0, 8);
    }

    const displayNameInput = $('#displayNameInput');
    const usernameInput = $('#usernameInput');
    const bioInput = $('#bioInput');
    const statusInput = $('#statusInput');
    const bioCounter = $('#bioCounter');
    const accentInput = $('#accentInput');
    const secondaryColorInput = $('#secondaryColorInput');
    const cardColorInput = $('#cardColorInput');
    const textColorInput = $('#textColorInput');
    const linkStyleInput = $('#linkStyleInput');
    const buttonShapeInput = $('#buttonShapeInput');
    const themeInput = $('#themeInput');
    const avatarInput = $('#avatarInput');
    const bannerInput = $('#bannerInput');
    const musicFileInput = $('#musicFileInput');
    const profileEffectInput = $('#profileEffectInput');
    const ringEnabledInput = $('#ringEnabledInput');
    const ringStyleInput = $('#ringStyleInput');
    const ringColorInput = $('#ringColorInput');
    const discordUseNameInput = $('#discordUseNameInput');
    const discordUseAvatarInput = $('#discordUseAvatarInput');
    const socialsStyleInput = $('#socialsStyleInput');
    const layoutInput = $('#layoutInput');
    const clickToEnterInput = $('#clickToEnterInput');
    const enterTextInput = $('#enterTextInput');

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
        document.documentElement.style.setProperty('--accent-2', secondaryColorInput ? secondaryColorInput.value : accentInput.value);
        document.documentElement.style.setProperty('--profile-card-color', cardColorInput ? cardColorInput.value : 'var(--card)');
        document.documentElement.style.setProperty('--profile-text-color', textColorInput ? textColorInput.value : 'var(--text)');
        document.documentElement.style.setProperty('--profile-ring', ringColorInput ? ringColorInput.value : accentInput.value);
        document.body.dataset.accent = accentInput.value;
        document.body.dataset.profileLinkStyle = linkStyleInput ? linkStyleInput.value : 'glass';
        document.body.dataset.profileButtonShape = buttonShapeInput ? buttonShapeInput.value : 'pill';
        document.body.dataset.theme = themeInput.value === 'auto' ? 'dark' : themeInput.value;
        document.body.dataset.profileEffect = profileEffectInput ? profileEffectInput.value : 'none';
        if (layoutInput) {
            document.body.dataset.profileLayout = layoutInput.value;
        }
        if (socialsStyleInput) {
            document.body.dataset.profileSocialsStyle = socialsStyleInput.value;
        }
        const previewCard = document.querySelector('.profile-edit-preview');
        if (previewCard && profileEffectInput) {
            previewCard.dataset.previewEffect = profileEffectInput.value;
        }
        const wrap = $('#previewAvatarWrap');
        if (wrap) {
            const style = ringStyleInput ? ringStyleInput.value : 'spin';
            const enabled = ringEnabledInput ? ringEnabledInput.checked : true;
            wrap.className = `bio-avatar-wrap profile-preview-avatar-ring ring-style-${style} ${(!enabled || style === 'none') ? 'ring-disabled' : ''}`;
            wrap.style.setProperty('--profile-ring', ringColorInput ? ringColorInput.value : accentInput.value);
        }
    }

    [displayNameInput, usernameInput, bioInput, statusInput, accentInput, secondaryColorInput, cardColorInput, textColorInput, linkStyleInput, buttonShapeInput, themeInput, profileEffectInput, ringEnabledInput, ringStyleInput, ringColorInput, discordUseNameInput, discordUseAvatarInput, socialsStyleInput, layoutInput, clickToEnterInput, enterTextInput].filter(Boolean).forEach((input) => {
        input.addEventListener('input', updatePreview);
        input.addEventListener('change', updatePreview);
    });

    $$('.profile-preset-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (accentInput && btn.dataset.accent) accentInput.value = btn.dataset.accent;
            if (secondaryColorInput && btn.dataset.secondary) secondaryColorInput.value = btn.dataset.secondary;
            if (cardColorInput && btn.dataset.card) cardColorInput.value = btn.dataset.card;
            if (textColorInput && btn.dataset.text) textColorInput.value = btn.dataset.text;
            updatePreview();
            if (typeof window.profileToast === 'function') {
                window.profileToast('Palette preset applicata.');
            }
        });
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
        $('#embedsJson').value = JSON.stringify(collectRows('embeds'));
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

    if (window.__profileCustomSelectLoaded) return;
    window.__profileCustomSelectLoaded = true;

    const shortLabel = (text) => {
        const clean = String(text || '').trim();

        const map = {
            'Nessuno': 'No',
            'Scuro': 'Dark',
            'Chiaro': 'Light',
            'Auto': 'Auto',
            'Standard': 'Std',
            'Compatto': 'Mini',
            'Showcase': 'Show',
            'Glass': 'Glass',
            'Pieno': 'Full',
            'Outline': 'Line',
            'Neon': 'Neon',
            'Pill': 'Pill',
            'Rounded': 'Round',
            'Squadrato': 'Sharp',
            'Pubblico': 'Pub',
            'Solo utenti loggati': 'Login',
            'Privato': 'Priv',
            'Rotazione': 'Spin',
            'Arcobaleno': 'RGB',
            'Glitch leggero': 'Glitch',
            'Mouse glow': 'Glow',
            'Particelle soft': 'Soft',
            'Scanlines soft': 'Scan',
            'Ambient glow': 'Glow',
            'Onde gradient': 'Wave',
            'Stelle leggere': 'Stars',
            'Spotlight mouse': 'Spot',
            'Digital noise': 'Noise',
            'Glass rain': 'Rain'
        };

        if (map[clean]) return map[clean];
        if (clean.length <= 6) return clean;

        return clean.slice(0, 5);
    };

    const closeAllProfileSelects = (except = null) => {
        document.querySelectorAll('[data-profile-custom-select].is-open').forEach((wrap) => {
            if (except && wrap === except) return;

            wrap.classList.remove('is-open');
            wrap.querySelector('.profile-select-trigger')?.setAttribute('aria-expanded', 'false');
        });
    };

    const syncProfileSelect = (wrap, emit = false) => {
        const select = wrap.querySelector('select');
        const current = wrap.querySelector('.profile-select-current');
        const buttons = Array.from(wrap.querySelectorAll('.profile-select-menu [data-value]'));

        if (!select || !current) return;

        const selected = select.options[select.selectedIndex] || select.options[0];
        if (!selected) return;

        current.textContent = selected.textContent.trim();

        buttons.forEach((button) => {
            const active = button.dataset.value === selected.value;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        if (emit) {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    const buildProfileSelect = (select) => {
        if (!select || select.dataset.profileCustomBuilt === '1') return;
        if (select.closest('[data-profile-custom-select]')) return;

        select.dataset.profileCustomBuilt = '1';
        select.classList.add('profile-native-select');

        const wrap = document.createElement('div');
        wrap.className = 'profile-custom-select';
        wrap.dataset.profileCustomSelect = '1';

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'profile-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.innerHTML = `
            <span class="profile-select-current"></span>
            <i class="fas fa-chevron-down"></i>
        `;

        const menu = document.createElement('div');
        menu.className = 'profile-select-menu';
        menu.setAttribute('role', 'listbox');

        Array.from(select.options).forEach((option) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.value = option.value;
            button.setAttribute('role', 'option');
            button.innerHTML = `
                <strong>${option.textContent.trim()}</strong>
                <span>${shortLabel(option.textContent)}</span>
            `;

            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                select.value = option.value;
                syncProfileSelect(wrap, true);

                wrap.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            });

            menu.appendChild(button);
        });

        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        wrap.appendChild(trigger);
        wrap.appendChild(menu);

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            closeAllProfileSelects(wrap);

            const isOpen = wrap.classList.toggle('is-open');
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        select.addEventListener('change', () => syncProfileSelect(wrap, false));
        select.addEventListener('input', () => syncProfileSelect(wrap, false));

        syncProfileSelect(wrap, false);
    };

    const initProfileCustomSelects = (root = document) => {
        root.querySelectorAll('.profile-editor-shell .profile-field select, .profile-editor-shell .profile-row-grid select').forEach(buildProfileSelect);
    };

    const refreshProfileCustomSelects = () => {
        document.querySelectorAll('[data-profile-custom-select]').forEach((wrap) => {
            syncProfileSelect(wrap, false);
        });
    };

    const startProfileSelectObserver = () => {
        const form = document.getElementById('profileEditForm');
        if (!form || !('MutationObserver' in window)) return;

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof HTMLElement)) return;
                    initProfileCustomSelects(node);
                });
            });
        });

        observer.observe(form, {
            childList: true,
            subtree: true
        });
    };

    document.addEventListener('click', () => {
        closeAllProfileSelects();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllProfileSelects();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        initProfileCustomSelects();
        startProfileSelectObserver();

        setTimeout(refreshProfileCustomSelects, 0);
        setTimeout(refreshProfileCustomSelects, 100);
    });

    if (document.readyState !== 'loading') {
        initProfileCustomSelects();
        startProfileSelectObserver();

        setTimeout(refreshProfileCustomSelects, 0);
        setTimeout(refreshProfileCustomSelects, 100);
    }

    // Gestione ordinamento personaggi
    let selectedCharIds = [];
    const initialDataEl = document.getElementById('initialCharactersData');
    if (initialDataEl) {
        try {
            selectedCharIds = JSON.parse(initialDataEl.textContent || '[]');
        } catch (e) {
            console.error('Errore parsing initialCharactersData:', e);
        }
    }

    // Carica personaggi inizialmente selezionati (se non presenti in initialCharactersData)
    const initialChecked = Array.from(document.querySelectorAll('#characterPicker input[type="checkbox"]:checked'))
        .map(input => Number(input.value));
    initialChecked.forEach(id => {
        if (!selectedCharIds.includes(id)) {
            selectedCharIds.push(id);
        }
    });
    // Pulisci eventuali deselezionati
    selectedCharIds = selectedCharIds.filter(id => initialChecked.includes(id)).slice(0, 12);

    function collectCharacters() {
        return selectedCharIds;
    }

    function getCharacterDetails(charId) {
        const label = document.querySelector(`#characterPicker input[value="${charId}"]`)?.closest('.profile-character-choice');
        if (!label) return { id: charId, name: 'Personaggio', img: '', rarityClass: 'rarity-common' };
        const name = label.getAttribute('title') || label.querySelector('strong')?.textContent || 'Personaggio';
        const img = label.querySelector('img')?.getAttribute('src') || '';
        const rarityClass = Array.from(label.classList).find(c => c.startsWith('rarity-')) || 'rarity-common';
        return { id: charId, name, img, rarityClass };
    }

    function renderCharacterSortList() {
        const sortListEl = document.getElementById('characterSortList');
        if (!sortListEl) return;
        sortListEl.innerHTML = '';

        if (selectedCharIds.length === 0) {
            sortListEl.innerHTML = `
                <div style="font-size: 0.82rem; color: var(--muted-2); font-style: italic; padding: 0.5rem 0;">
                    Nessun personaggio selezionato. Spunta le caselle sopra per aggiungerne.
                </div>
            `;
            return;
        }

        selectedCharIds.forEach((charId, index) => {
            const details = getCharacterDetails(charId);
            if (!details) return;

            const card = document.createElement('div');
            card.className = `profile-character-sort-card ${details.rarityClass}`;
            card.dataset.charId = charId;
            card.innerHTML = `
                <div class="profile-character-sort-info">
                    ${details.img ? `<img src="${details.img}" alt="" class="profile-character-sort-img">` : `<span class="profile-character-sort-fallback"><i class="fas fa-user-astronaut"></i></span>`}
                    <strong class="profile-character-sort-name">${details.name}</strong>
                </div>
                <div class="profile-row-actions">
                    <button type="button" class="profile-move-up-char" title="Sposta su" ${index === 0 ? 'disabled' : ''}><i class="fas fa-arrow-up"></i></button>
                    <button type="button" class="profile-move-down-char" title="Sposta giù" ${index === selectedCharIds.length - 1 ? 'disabled' : ''}><i class="fas fa-arrow-down"></i></button>
                </div>
            `;

            card.querySelector('.profile-move-up-char').addEventListener('click', (e) => {
                e.preventDefault();
                if (index > 0) {
                    const temp = selectedCharIds[index];
                    selectedCharIds[index] = selectedCharIds[index - 1];
                    selectedCharIds[index - 1] = temp;
                    renderCharacterSortList();
                }
            });

            card.querySelector('.profile-move-down-char').addEventListener('click', (e) => {
                e.preventDefault();
                if (index < selectedCharIds.length - 1) {
                    const temp = selectedCharIds[index];
                    selectedCharIds[index] = selectedCharIds[index + 1];
                    selectedCharIds[index + 1] = temp;
                    renderCharacterSortList();
                }
            });

            sortListEl.appendChild(card);
        });
    }

    function updateCharacterCounter() {
        const hint = document.querySelector('.profile-character-hint');
        if (hint) {
            const n = selectedCharIds.length;
            hint.innerHTML = `<i class="fas fa-circle-info"></i> ${n}/12 selezionati.`;
        }
    }

    // Filtro ricerca personaggi
    const characterSearchInput = document.getElementById('characterSearchInput');
    if (characterSearchInput) {
        characterSearchInput.addEventListener('input', () => {
            const q = characterSearchInput.value.trim().toLowerCase();
            document.querySelectorAll('.profile-character-choice').forEach((card) => {
                const name = (card.dataset.charName || '').toLowerCase();
                card.style.display = q === '' || name.includes(q) ? '' : 'none';
            });
        });
    }
    
    // Listener checkbox picker personaggi
    const characterPickerEl = document.getElementById('characterPicker');
    if (characterPickerEl) {
        characterPickerEl.addEventListener('change', (e) => {
            if (e.target && e.target.type === 'checkbox') {
                const charId = Number(e.target.value);
                if (e.target.checked) {
                    const checkedCount = characterPickerEl.querySelectorAll('input[type="checkbox"]:checked').length;
                    if (checkedCount > 12) {
                        e.target.checked = false;
                        if (typeof window.profileToast === 'function') {
                            window.profileToast('Puoi selezionare massimo 12 personaggi.');
                        }
                        return;
                    }
                    if (!selectedCharIds.includes(charId)) {
                        selectedCharIds.push(charId);
                    }
                } else {
                    selectedCharIds = selectedCharIds.filter(id => id !== charId);
                }
                renderCharacterSortList();
                updateCharacterCounter();
            }
        });
    }

    // Inizializza lista ordinamento personaggi
    renderCharacterSortList();
    updateCharacterCounter();

    // ── ORDINAMENTO SEZIONI PROFILO ─────────────────────────────────────────
    const sectionInfo = {
        links: { name: 'Link', icon: 'fas fa-link' },
        embeds: { name: 'Embed (Spotify/YouTube)', icon: 'fas fa-share-square' },
        stats: { name: 'Statistiche', icon: 'fas fa-chart-simple' },
        projects: { name: 'Progetti', icon: 'fas fa-cubes' },
        blocks: { name: 'Custom Blocks', icon: 'fas fa-wand-magic-sparkles' },
        contents: { name: 'Edit e Contenuti', icon: 'fas fa-play' },
        characters: { name: 'Personaggi', icon: 'fas fa-user-astronaut' },
        badges: { name: 'Badge', icon: 'fas fa-trophy' },
        activity: { name: 'Attività Recente', icon: 'fas fa-clock' }
    };

    function initSectionsSorting() {
        const sectionsOrderInput = document.getElementById('sectionsOrderJson');
        const sectionsSortList = document.getElementById('sectionsSortList');
        if (!sectionsOrderInput || !sectionsSortList) return;

        const allowedList = ['links', 'embeds', 'stats', 'projects', 'blocks', 'contents', 'characters', 'badges', 'activity'];
        let currentOrder = sectionsOrderInput.value.split(',').map(s => s.trim()).filter(s => allowedList.includes(s));

        // Inserisci eventuali sezioni mancanti alla fine
        allowedList.forEach(s => {
            if (!currentOrder.includes(s)) currentOrder.push(s);
        });

        function renderSectionsList() {
            sectionsSortList.innerHTML = '';
            currentOrder.forEach((secKey, index) => {
                const info = sectionInfo[secKey] || { name: secKey, icon: 'fas fa-folder' };
                const item = document.createElement('div');
                item.className = 'profile-sort-item';
                item.dataset.secKey = secKey;
                item.innerHTML = `
                    <div class="profile-sort-item-info">
                        <i class="${info.icon}"></i>
                        <span>${info.name}</span>
                    </div>
                    <div class="profile-row-actions">
                        <button type="button" class="profile-move-up-sec" title="Sposta su" ${index === 0 ? 'disabled' : ''}><i class="fas fa-arrow-up"></i></button>
                        <button type="button" class="profile-move-down-sec" title="Sposta giù" ${index === currentOrder.length - 1 ? 'disabled' : ''}><i class="fas fa-arrow-down"></i></button>
                    </div>
                `;

                item.querySelector('.profile-move-up-sec').addEventListener('click', (e) => {
                    e.preventDefault();
                    if (index > 0) {
                        const temp = currentOrder[index];
                        currentOrder[index] = currentOrder[index - 1];
                        currentOrder[index - 1] = temp;
                        saveSectionsOrder();
                        renderSectionsList();
                    }
                });

                item.querySelector('.profile-move-down-sec').addEventListener('click', (e) => {
                    e.preventDefault();
                    if (index < currentOrder.length - 1) {
                        const temp = currentOrder[index];
                        currentOrder[index] = currentOrder[index + 1];
                        currentOrder[index + 1] = temp;
                        saveSectionsOrder();
                        renderSectionsList();
                    }
                });

                sectionsSortList.appendChild(item);
            });
        }

        function saveSectionsOrder() {
            sectionsOrderInput.value = currentOrder.join(',');
        }

        renderSectionsList();
    }

    initSectionsSorting();

    function initBadgesSorting() {
        const badgeSortList = document.getElementById('badgeSortList');
        const badgesJsonInput = document.getElementById('badgesJson');
        if (!badgeSortList || !badgesJsonInput) return;

        let badges = [];
        try {
            badges = JSON.parse(badgeSortList.dataset.badges || '[]');
        } catch (_) {
            badges = [];
        }

        function renderBadgesList() {
            badgeSortList.innerHTML = '';
            if (badges.length === 0) {
                badgeSortList.innerHTML = `
                    <div class="bio-empty-state">
                        <i class="fas fa-medal"></i>
                        <strong>Nessun badge sbloccato o assegnato</strong>
                    </div>
                `;
                return;
            }

            badges.forEach((badge, index) => {
                const isSelected = Number(badge.selected) === 1;
                const compoundId = badge.badge_source + '_' + badge.id;
                const badgeName = badge.nome;
                const badgeImg = badge.img_url ? (badge.img_url.startsWith('http') ? badge.img_url : '/img/' + badge.img_url.replace(/^\//, '')) : null;
                const iconClass = badge.icon || 'fas fa-medal';

                const item = document.createElement('div');
                item.className = 'profile-sort-item badge-sort-item' + (isSelected ? ' is-selected' : '');

                let previewHtml = '';
                if (badgeImg) {
                    previewHtml = `<img src="${escapeAttr(badgeImg)}" alt="" style="width: 24px; height: 24px; object-fit: contain; border-radius: 4px;">`;
                } else {
                    previewHtml = `<i class="${escapeAttr(iconClass)}" style="font-size: 1.1rem; color: ${escapeAttr(badge.color || 'var(--accent)')}"></i>`;
                }

                item.innerHTML = `
                    <div class="profile-sort-item-info">
                        <input type="checkbox" class="badge-select-chk" data-id="${escapeAttr(compoundId)}" ${isSelected ? 'checked' : ''}>
                        <div class="badge-sort-preview" style="margin: 0 10px; display: flex; align-items: center;">${previewHtml}</div>
                        <span style="font-weight: 550;">${escapeAttr(badgeName)}</span>
                    </div>
                    <div class="profile-row-actions">
                        <button type="button" class="profile-move-up profile-move-up-badge" title="Sposta su" ${index === 0 ? 'disabled' : ''}><i class="fas fa-arrow-up"></i></button>
                        <button type="button" class="profile-move-down profile-move-down-badge" title="Sposta giù" ${index === badges.length - 1 ? 'disabled' : ''}><i class="fas fa-arrow-down"></i></button>
                    </div>
                `;

                item.querySelector('.badge-select-chk').addEventListener('change', (e) => {
                    badge.selected = e.target.checked ? 1 : 0;
                    saveBadgesState();
                    renderBadgesList();
                });

                item.querySelector('.profile-move-up-badge').addEventListener('click', (e) => {
                    e.preventDefault();
                    if (index > 0) {
                        const temp = badges[index];
                        badges[index] = badges[index - 1];
                        badges[index - 1] = temp;
                        saveBadgesState();
                        renderBadgesList();
                    }
                });

                item.querySelector('.profile-move-down-badge').addEventListener('click', (e) => {
                    e.preventDefault();
                    if (index < badges.length - 1) {
                        const temp = badges[index];
                        badges[index] = badges[index + 1];
                        badges[index + 1] = temp;
                        saveBadgesState();
                        renderBadgesList();
                    }
                });

                badgeSortList.appendChild(item);
            });
        }

        function saveBadgesState() {
            const selectedBadges = badges
                .filter(b => Number(b.selected) === 1)
                .map(b => b.badge_source + '_' + b.id)
                .slice(0, 8);
            badgesJsonInput.value = JSON.stringify(selectedBadges);
        }

        // Initial sort: selected first, then sort_order, then ID
        badges.sort((a, b) => {
            const selA = Number(a.selected) === 1 ? 1 : 0;
            const selB = Number(b.selected) === 1 ? 1 : 0;
            if (selA !== selB) return selB - selA;
            return Number(a.sort_order) - Number(b.sort_order);
        });

        renderBadgesList();
        saveBadgesState();
    }

    initBadgesSorting();
    
    // ── Hook nel submit: serializza i personaggi scelti ──────
    (function patchSubmitForCharacters() {
        const form = document.getElementById('profileEditForm');
        if (!form || form.dataset.charactersPatchApplied) return;
        form.dataset.charactersPatchApplied = '1';
    
        form.addEventListener(
            'submit',
            () => {
                const hiddenInput = document.getElementById('charactersJson');
                if (hiddenInput) {
                    hiddenInput.value = JSON.stringify(collectCharacters());
                }
            },
            true
        );
    })();
    
})();