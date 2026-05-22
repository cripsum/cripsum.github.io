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

    const platformOptions = ['tiktok', 'instagram', 'youtube', 'twitch', 'github', 'discord', 'telegram', 'x', 'spotify', 'soundcloud', 'steam', 'reddit', 'pinterest', 'snapchat', 'facebook', 'linkedin', 'paypal', 'patreon', 'kick', 'bluesky', 'threads', 'behance', 'dribbble', 'website', 'email', 'other'];
    const projectStatuses = [['active', 'Attivo'], ['paused', 'In pausa'], ['finished', 'Finito'], ['idea', 'Idea']];
    const contentTypes = [['edit', 'Edit'], ['video', 'Video'], ['game', 'Gioco'], ['post', 'Post'], ['other', 'Altro']];
    const blockTypes = [['text', 'Testo'], ['image', 'Immagine'], ['gif', 'GIF'], ['video', 'Video']];
    const linkButtonStyles = [['card', 'Card'], ['compact', 'Compatto'], ['icon', 'Solo icona']];

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
                    <label>Thumbnail URL<input data-field="thumbnail_url" value="${escapeAttr(data.thumbnail_url || '')}" placeholder="https://.../cover.webp"></label>
                    <label>Icona custom URL<input data-field="custom_icon_url" value="${escapeAttr(data.custom_icon_url || '')}" placeholder="https://.../icon.svg"></label>
                    <label>Short slug<input data-field="short_slug" maxlength="48" value="${escapeAttr(data.short_slug || '')}" placeholder="my-link"></label>
                    <label>Start<input type="datetime-local" data-field="schedule_starts_at" value="${escapeAttr((data.schedule_starts_at || '').replace(' ', 'T').slice(0, 16))}"></label>
                    <label>End<input type="datetime-local" data-field="schedule_ends_at" value="${escapeAttr((data.schedule_ends_at || '').replace(' ', 'T').slice(0, 16))}"></label>
                    <label>Separator title<input data-field="separator_title" maxlength="70" value="${escapeAttr(data.separator_title || '')}" placeholder="Featured"></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_featured" ${boolAttr(data.is_featured)}> In evidenza</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> Visibile</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_hidden" ${boolAttr(data.is_hidden)}> Hidden</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_separator" ${boolAttr(data.is_separator)}> Separator</label>
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
                <strong>${type === 'socials' ? 'Social' : type === 'links' ? 'Link' : type === 'projects' ? 'Progetto' : type === 'blocks' ? 'Blocco' : 'Contenuto'}</strong>
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

    [displayNameInput, usernameInput, bioInput, statusInput, accentInput, secondaryColorInput, cardColorInput, textColorInput, linkStyleInput, buttonShapeInput, themeInput, profileEffectInput, ringEnabledInput, ringStyleInput, ringColorInput, discordUseNameInput, discordUseAvatarInput].filter(Boolean).forEach((input) => {
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
})();

/* Cripsum Profile Editor 3.0 */
(() => {
    'use strict';

    const form = document.getElementById('profileEditForm');
    if (!form) return;

    const $ = (selector, parent = document) => parent.querySelector(selector);
    const $$ = (selector, parent = document) => Array.from(parent.querySelectorAll(selector));
    const toast = (message) => typeof window.profileToast === 'function' && window.profileToast(message);

    const readJson = (id, fallback = {}) => {
        const node = document.getElementById(id);
        if (!node) return fallback;
        try { return JSON.parse(node.textContent || ''); } catch (_) { return fallback; }
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    const cssEscape = (value) => window.CSS && CSS.escape ? CSS.escape(value) : String(value).replace(/["\\\]]/g, '\\$&');

    const v3 = readJson('initialProfileV3Data', {});
    let builder = readJson('initialBuilderData', { version: 3, blocks: [] });
    if (!Array.isArray(builder.blocks)) builder.blocks = [];

    const debounce = (fn, wait = 300) => {
        let timer = null;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), wait);
        };
    };

    const presetMap = {
        cyber: ['#00f5ff', '#8b5cf6', 'space-grotesk'],
        rose: ['#ff4fa3', '#ffd1e8', 'playfair'],
        onyx: ['#d7dde8', '#6b7280', 'inter'],
        toxic: ['#a3ff12', '#00ffaa', 'orbitron'],
        vaporwave: ['#ff71ce', '#01cdfe', 'space-grotesk'],
        crimson: ['#ff304f', '#ffb000', 'bebas'],
        midnight: ['#60a5fa', '#c084fc', 'jetbrains'],
        sakura: ['#ff9ac7', '#fdf2f8', 'poppins'],
    };

    const fontStacks = {
        inter: 'Inter, system-ui, sans-serif',
        'space-grotesk': '"Space Grotesk", Inter, sans-serif',
        jetbrains: '"JetBrains Mono", monospace',
        vcr: '"VCR OSD Mono", "JetBrains Mono", monospace',
        poppins: 'Poppins, Inter, sans-serif',
        playfair: '"Playfair Display", Georgia, serif',
        orbitron: 'Orbitron, Inter, sans-serif',
        bebas: '"Bebas Neue", Impact, sans-serif',
    };

    const getCanvasConfig = () => {
        const config = {};
        $$('[data-canvas-field]').forEach((input) => {
            config[input.dataset.canvasField] = input.type === 'checkbox' ? input.checked : input.value;
        });
        config.speed = Number(config.speed || 1);
        config.density = Number(config.density || 55);
        config.opacity = Number(config.opacity || 0.55);
        config.fps = Number(config.fps || 40);
        return config;
    };

    const getBackgroundConfig = () => {
        const config = { colors: [] };
        $$('[data-bg-color]').forEach((input) => {
            config.colors[Number(input.dataset.bgColor || 0)] = input.value;
        });
        $$('[data-bg-field]').forEach((input) => {
            const key = input.dataset.bgField;
            config[key] = input.type === 'checkbox' ? input.checked : input.value;
        });
        config.blur = Number(config.blur || 0);
        return config;
    };

    const syncHiddenV3 = () => {
        const builderInput = $('#profileBuilderJson');
        const canvasInput = $('#profileCanvasConfigJson');
        const bgInput = $('#profileBackgroundConfigJson');
        const pluginsInput = $('#profilePluginsJson');
        if (builderInput) builderInput.value = JSON.stringify(builder);
        if (canvasInput) canvasInput.value = JSON.stringify(getCanvasConfig());
        if (bgInput) bgInput.value = JSON.stringify(getBackgroundConfig());
        if (pluginsInput) pluginsInput.value = JSON.stringify(v3.plugins || []);
    };

    const makeBlock = (type) => ({
        id: `block-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`,
        type,
        title: type.replace('_', ' '),
        title_en: '',
        hidden: 0,
        collapsed: 0,
        style: {},
        data: {},
    });

    const dataToText = (block) => {
        const data = block.data || {};
        if (block.type === 'custom_html') return data.html || '';
        if (block.type === 'gallery') return Array.isArray(data.items) ? data.items.map((item) => item.url || '').join('\n') : '';
        if (['social', 'link', 'projects'].includes(block.type)) return Array.isArray(data.items) ? data.items.map((item) => `${item.label || item.title || ''} | ${item.url || ''}`).join('\n') : (data.text || '');
        if (block.type === 'table') return Array.isArray(data.rows) ? data.rows.map((row) => `${row.label || ''} | ${row.value || ''}`).join('\n') : '';
        if (block.type === 'quote') return data.quote || '';
        return data.text || data.description || '';
    };

    const blockUrl = (block) => (block.data || {}).url || '';

    const renderBuilder = () => {
        const root = $('#profileBuilderRepeater');
        if (!root) return;
        root.innerHTML = builder.blocks.map((block, index) => `
            <article class="profile-builder-editor-card" data-builder-index="${index}">
                <div class="profile-builder-editor-head">
                    <strong><span class="profile-builder-drag" title="Drag"><i class="fas fa-grip-vertical"></i></span>${escapeHtml(block.type)}</strong>
                    <div class="profile-builder-card-actions">
                        <button type="button" class="profile-duplicate-builder" data-builder-duplicate="${index}"><i class="fas fa-copy"></i></button>
                        <button type="button" class="profile-remove-builder" data-builder-remove="${index}"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="profile-builder-editor-body">
                    <div class="profile-builder-mini-grid">
                        <label>Title<input data-builder-field="title" value="${escapeHtml(block.title || '')}" maxlength="90"></label>
                        <label>Title EN<input data-builder-field="title_en" value="${escapeHtml(block.title_en || '')}" maxlength="90"></label>
                    </div>
                    <div class="profile-builder-mini-grid">
                        <label>URL / Embed<input data-builder-field="url" value="${escapeHtml(blockUrl(block))}" placeholder="https://..."></label>
                        <label>Meta<input data-builder-field="meta" value="${escapeHtml((block.data || {}).meta || (block.data || {}).author || '')}" placeholder="author, label, username"></label>
                    </div>
                    <label>${block.type === 'custom_html' ? 'HTML sanitizzato' : block.type === 'gallery' ? 'Gallery URLs, una per riga' : block.type === 'table' ? 'Righe: label | value' : 'Text / body'}<textarea data-builder-field="body">${escapeHtml(dataToText(block))}</textarea></label>
                    <div class="profile-builder-mini-grid">
                        <label class="profile-check-line"><input type="checkbox" data-builder-field="hidden" ${block.hidden ? 'checked' : ''}> Hidden</label>
                        <label class="profile-check-line"><input type="checkbox" data-builder-field="collapsed" ${block.collapsed ? 'checked' : ''}> Collapsed</label>
                    </div>
                </div>
            </article>
        `).join('');

        $$('[data-builder-field]', root).forEach((input) => {
            input.addEventListener('input', () => {
                const card = input.closest('[data-builder-index]');
                updateBlockFromCard(Number(card.dataset.builderIndex), card);
                markDirty();
            });
            input.addEventListener('change', () => {
                const card = input.closest('[data-builder-index]');
                updateBlockFromCard(Number(card.dataset.builderIndex), card);
                markDirty();
            });
        });

        $$('[data-builder-remove]', root).forEach((button) => {
            button.addEventListener('click', () => {
                builder.blocks.splice(Number(button.dataset.builderRemove), 1);
                renderBuilder();
                markDirty();
            });
        });

        $$('[data-builder-duplicate]', root).forEach((button) => {
            button.addEventListener('click', () => {
                const source = builder.blocks[Number(button.dataset.builderDuplicate)];
                const copy = JSON.parse(JSON.stringify(source));
                copy.id = makeBlock(source.type).id;
                builder.blocks.splice(Number(button.dataset.builderDuplicate) + 1, 0, copy);
                renderBuilder();
                markDirty();
            });
        });

        if (window.Sortable && !root.dataset.sortableReady) {
            root.dataset.sortableReady = '1';
            window.Sortable.create(root, {
                handle: '.profile-builder-drag',
                animation: 160,
                ghostClass: 'profile-sortable-ghost',
                onEnd: () => {
                    const next = [];
                    $$('[data-builder-index]', root).forEach((card) => next.push(builder.blocks[Number(card.dataset.builderIndex)]));
                    builder.blocks = next.filter(Boolean);
                    renderBuilder();
                    markDirty();
                },
            });
        }
    };

    const updateBlockFromCard = (index, card) => {
        const block = builder.blocks[index];
        if (!block) return;
        const data = { ...(block.data || {}) };
        $$('[data-builder-field]', card).forEach((input) => {
            const key = input.dataset.builderField;
            const value = input.type === 'checkbox' ? input.checked : input.value.trim();
            if (key === 'title' || key === 'title_en' || key === 'hidden' || key === 'collapsed') {
                block[key] = value;
            } else if (key === 'url') {
                data.url = value;
                if (block.type === 'github') data.username = value.replace(/^https?:\/\/github\.com\//i, '').replace(/[^a-zA-Z0-9_-]/g, '');
                if (block.type === 'countdown') data.datetime = value;
            } else if (key === 'meta') {
                data.meta = value;
                data.author = value;
                data.label = value;
            } else if (key === 'body') {
                if (block.type === 'custom_html') data.html = value;
                else if (['social', 'link', 'projects'].includes(block.type)) {
                    data.items = value.split('\n').map((line) => {
                        const parts = line.split('|');
                        if (parts.length > 1) return { label: parts[0].trim(), url: parts.slice(1).join('|').trim() };
                        return { label: parts[0].trim(), url: data.url || '' };
                    }).filter((item) => item.label && item.url);
                }
                else if (block.type === 'gallery') data.items = value.split('\n').map((url) => ({ url: url.trim() })).filter((item) => item.url);
                else if (block.type === 'table') data.rows = value.split('\n').map((line) => {
                    const [label, ...rest] = line.split('|');
                    return { label: (label || '').trim(), value: rest.join('|').trim() };
                }).filter((row) => row.label || row.value);
                else if (block.type === 'quote') data.quote = value;
                else data.text = value;
            }
        });
        block.data = data;
        syncHiddenV3();
    };

    $('#addBuilderBlock')?.addEventListener('click', () => {
        const type = $('#builderTypeSelect')?.value || 'bio';
        builder.blocks.push(makeBlock(type));
        renderBuilder();
        markDirty();
    });

    $('#exportBuilderPreset')?.addEventListener('click', async () => {
        syncHiddenV3();
        const text = JSON.stringify(builder, null, 2);
        try {
            await navigator.clipboard.writeText(text);
            toast('Preset copied.');
        } catch (_) {
            toast('Preset ready in builder JSON.');
        }
    });

    const applyPreset = (preset) => {
        const data = presetMap[preset];
        if (!data) return;
        const [accent, secondary, font] = data;
        const accentInput = $('#accentInput');
        const secondaryInput = $('#secondaryColorInput');
        const fontInput = $('#fontFamilyInput');
        if (accentInput) accentInput.value = accent;
        if (secondaryInput) secondaryInput.value = secondary;
        if (fontInput && !fontInput.dataset.userTouched) fontInput.value = font;
        document.body.dataset.themePreset = preset;
        document.documentElement.style.setProperty('--accent', accent);
        document.documentElement.style.setProperty('--accent-2', secondary);
        document.documentElement.style.setProperty('--profile-font-family', fontStacks[font] || fontStacks.inter);
    };

    $('#themePresetInput')?.addEventListener('change', (event) => {
        applyPreset(event.target.value);
        markDirty();
    });

    $('#fontFamilyInput')?.addEventListener('change', (event) => {
        event.target.dataset.userTouched = '1';
        document.documentElement.style.setProperty('--profile-font-family', fontStacks[event.target.value] || fontStacks.inter);
        markDirty();
    });

    const checkUsername = debounce(async () => {
        const input = $('#usernameInput');
        if (!input || input.value.length < 3) return;
        try {
            const target = form.querySelector('[name="target_user_id"]')?.value || '';
            const res = await fetch(`/api/check_username.php?username=${encodeURIComponent(input.value)}&target_user_id=${encodeURIComponent(target)}`, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            input.dataset.available = data.available ? '1' : '0';
            input.setCustomValidity(data.available ? '' : (data.message || 'Username unavailable.'));
        } catch (_) {}
    }, 360);

    $('#usernameInput')?.addEventListener('input', checkUsername);

    const initSortableRepeaters = () => {
        if (!window.Sortable) return;
        ['socialsRepeater', 'linksRepeater', 'projectsRepeater', 'contentsRepeater', 'blocksRepeater'].forEach((id) => {
            const node = document.getElementById(id);
            if (!node || node.dataset.sortableReady) return;
            node.dataset.sortableReady = '1';
            window.Sortable.create(node, { animation: 150, ghostClass: 'profile-sortable-ghost' });
        });
    };

    const dirtyDot = document.createElement('div');
    dirtyDot.className = 'profile-unsaved-dot';
    dirtyDot.innerHTML = '<i class="fas fa-circle"></i><span>Unsaved changes</span>';
    document.body.appendChild(dirtyDot);

    let dirty = false;
    const markDirty = () => {
        dirty = true;
        dirtyDot.classList.add('is-visible');
        saveDraftDebounced();
    };

    const draftKey = `cripsum.profile.draft.${form.querySelector('[name="target_user_id"]')?.value || 'me'}`;
    const collectDraft = () => {
        syncHiddenV3();
        const data = {};
        new FormData(form).forEach((value, key) => {
            if (value instanceof File) return;
            if (data[key] !== undefined) {
                if (!Array.isArray(data[key])) data[key] = [data[key]];
                data[key].push(value);
            } else {
                data[key] = value;
            }
        });
        return { saved_at: Date.now(), data, builder };
    };

    const saveDraftDebounced = debounce(() => {
        try { localStorage.setItem(draftKey, JSON.stringify(collectDraft())); } catch (_) {}
    }, 650);

    const restoreDraft = (draft) => {
        Object.entries(draft.data || {}).forEach(([key, value]) => {
            const fields = $$(`[name="${cssEscape(key)}"]`, form);
            fields.forEach((field) => {
                if (field.type === 'checkbox') field.checked = Array.isArray(value) ? value.includes(field.value) : String(value) === field.value;
                else if (field.type !== 'file') field.value = Array.isArray(value) ? value[0] : value;
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
        if (draft.builder && Array.isArray(draft.builder.blocks)) {
            builder = draft.builder;
            renderBuilder();
        }
        syncHiddenV3();
        markDirty();
    };

    try {
        const draft = JSON.parse(localStorage.getItem(draftKey) || 'null');
        if (draft?.saved_at && Date.now() - draft.saved_at < 1000 * 60 * 60 * 24) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'profile-unsaved-dot is-visible';
            button.innerHTML = '<i class="fas fa-rotate-left"></i><span>Recover draft</span>';
            button.addEventListener('click', () => {
                restoreDraft(draft);
                button.remove();
                toast('Draft recovered.');
            });
            document.body.appendChild(button);
        }
    } catch (_) {}

    const history = [];
    const redo = [];
    const snapshot = debounce(() => {
        history.push(JSON.stringify(collectDraft()));
        if (history.length > 30) history.shift();
        redo.length = 0;
    }, 500);

    form.addEventListener('input', () => { markDirty(); snapshot(); }, true);
    form.addEventListener('change', () => { markDirty(); snapshot(); }, true);
    form.addEventListener('submit', () => syncHiddenV3(), true);

    document.addEventListener('keydown', (event) => {
        if (!(event.ctrlKey || event.metaKey)) return;
        if (event.key.toLowerCase() === 'z' && history.length > 1) {
            event.preventDefault();
            const current = history.pop();
            redo.push(current);
            restoreDraft(JSON.parse(history[history.length - 1]));
        }
        if (event.key.toLowerCase() === 'y' && redo.length) {
            event.preventDefault();
            const next = redo.pop();
            history.push(next);
            restoreDraft(JSON.parse(next));
        }
    });

    $('[data-preview-toggle]')?.addEventListener('click', () => {
        $('.profile-edit-grid')?.classList.toggle('preview-open');
    });

    const renderAnalytics = async () => {
        const canvas = $('#profileAnalyticsCanvas');
        if (!canvas || !v3.profile_id) return;
        try {
            const response = await fetch(`/api/profile_analytics.php?profile_id=${encodeURIComponent(v3.profile_id)}`, { headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            if (!data.ok) return;
            const ctx = canvas.getContext('2d');
            const w = canvas.width;
            const h = canvas.height;
            ctx.clearRect(0, 0, w, h);
            const values = (data.days || []).map((d) => Number(d.total || 0));
            const max = Math.max(1, ...values);
            ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--accent') || '#00f5ff';
            ctx.fillStyle = 'rgba(255,255,255,.55)';
            ctx.lineWidth = 3;
            ctx.beginPath();
            values.forEach((value, index) => {
                const x = 28 + (index / Math.max(1, values.length - 1)) * (w - 56);
                const y = h - 28 - (value / max) * (h - 58);
                if (index === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            });
            ctx.stroke();
            ctx.font = '14px system-ui';
            ctx.fillText(`${max} max visits/day`, 28, 24);

            const fillList = (id, rows, labelKey = 'label') => {
                const node = $(id);
                if (!node) return;
                node.innerHTML = (rows || []).slice(0, 6).map((row) => `<li><span>${escapeHtml(row[labelKey] || row.device_type || 'direct')}</span><strong>${Number(row.total || 0)}</strong></li>`).join('');
            };
            fillList('#profileTopReferrers', data.referrers || []);
            fillList('#profileTopDevices', data.devices || [], 'device_type');
        } catch (_) {}
    };

    const autoDetectSocial = (url) => {
        const map = {
            'tiktok.com': 'tiktok',
            'instagram.com': 'instagram',
            'youtube.com': 'youtube',
            'youtu.be': 'youtube',
            'twitch.tv': 'twitch',
            'github.com': 'github',
            'discord.': 'discord',
            'spotify.com': 'spotify',
            'soundcloud.com': 'soundcloud',
            'steamcommunity.com': 'steam',
            'x.com': 'x',
            'twitter.com': 'x',
        };
        return Object.entries(map).find(([host]) => url.includes(host))?.[1] || '';
    };

    document.addEventListener('input', (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || input.dataset.field !== 'url') return;
        const row = input.closest('[data-row-type="socials"]');
        if (!row) return;
        const platform = autoDetectSocial(input.value.toLowerCase());
        const select = row.querySelector('[data-field="platform"]');
        if (platform && select) {
            select.value = platform;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    renderBuilder();
    syncHiddenV3();
    initSortableRepeaters();
    renderAnalytics();
    snapshot();
})();
