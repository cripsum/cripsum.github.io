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
    const avatarBorderInput = $('#avatarBorderInput');
    const ringStyleInput = $('#ringStyleInput');
    const ringColorInput = $('#ringColorInput');
    const discordUseNameInput = $('#discordUseNameInput');
    const discordUseAvatarInput = $('#discordUseAvatarInput');
    const socialsStyleInput = $('#socialsStyleInput');
    const layoutInput = $('#layoutInput');
    const clickToEnterInput = $('#clickToEnterInput');
    const enterTextInput = $('#enterTextInput');

    const uiShapeInput = $('#uiShapeInput');
    const avatarShapeInput = $('#avatarShapeInput');
    const socialSizeInput = $('#socialSizeInput');
    const iconSpacingInput = $('#iconSpacingInput');
    const badgeSizeInput = $('#badgeSizeInput');
    const buttonSizeInput = $('#buttonSizeInput');

    const socialSizeVal = $('#socialSizeVal');
    const iconSpacingVal = $('#iconSpacingVal');
    const badgeSizeVal = $('#badgeSizeVal');
    const buttonSizeVal = $('#buttonSizeVal');

    const fontInput = $('#fontInput');
    const borderRadiusInput = $('#borderRadiusInput');
    const cardOpacityInput = $('#cardOpacityInput');
    const cardBlurInput = $('#cardBlurInput');
    const borderOpacityInput = $('#borderOpacityInput');
    const borderColorInput = $('#borderColorInput');
    const borderWidthInput = $('#borderWidthInput');

    const borderRadiusVal = $('#borderRadiusVal');
    const cardOpacityVal = $('#cardOpacityVal');
    const cardBlurVal = $('#cardBlurVal');
    const borderOpacityVal = $('#borderOpacityVal');
    const borderWidthVal = $('#borderWidthVal');

    const nameColorTypeInput = $('#nameColorTypeInput');
    const nameSolidColorInput = $('#nameSolidColorInput');
    const nameGradColor1Input = $('#nameGradColor1Input');
    const nameGradColor2Input = $('#nameGradColor2Input');
    const nameGradAngleInput = $('#nameGradAngleInput');
    const nameAnimationInput = $('#nameAnimationInput');
    const nameGlowColorInput = $('#nameGlowColorInput');

    const loadedFonts = new Set();
    function loadGoogleFontPreview(fontName) {
        if (!fontName || fontName === 'Poppins' || fontName === 'Minecraft' || fontName === 'Gang of Three' || loadedFonts.has(fontName)) return;
        loadedFonts.add(fontName);
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(fontName)}:wght@300;400;500;600;700;800&display=swap`;
        document.head.appendChild(link);
    }

    if (borderRadiusInput && borderRadiusVal) {
        borderRadiusInput.addEventListener('input', () => {
            borderRadiusVal.textContent = borderRadiusInput.value + 'px';
        });
    }
    if (cardOpacityInput && cardOpacityVal) {
        cardOpacityInput.addEventListener('input', () => {
            cardOpacityVal.textContent = cardOpacityInput.value + '%';
        });
    }
    if (cardBlurInput && cardBlurVal) {
        cardBlurInput.addEventListener('input', () => {
            cardBlurVal.textContent = cardBlurInput.value + 'px';
        });
    }
    if (borderOpacityInput && borderOpacityVal) {
        borderOpacityInput.addEventListener('input', () => {
            borderOpacityVal.textContent = borderOpacityInput.value + '%';
        });
    }
    if (borderWidthInput && borderWidthVal) {
        borderWidthInput.addEventListener('input', () => {
            borderWidthVal.textContent = borderWidthInput.value + 'px';
        });
    }

    function toggleNameFields() {
        if (!nameColorTypeInput) return;
        const type = nameColorTypeInput.value;
        const anim = nameAnimationInput ? nameAnimationInput.value : 'none';

        // Toggle Solid Color fields
        $$('.field-name-solid').forEach(el => {
            el.style.display = type === 'solid' ? 'block' : 'none';
        });

        // Toggle Gradient fields
        $$('.field-name-gradient').forEach(el => {
            el.style.display = type === 'gradient' ? 'block' : 'none';
        });

        // Toggle Glow fields
        $$('.field-name-glow').forEach(el => {
            el.style.display = (anim === 'glow' || anim === 'neon') ? 'block' : 'none';
        });
    }

    if (nameColorTypeInput) {
        nameColorTypeInput.addEventListener('change', toggleNameFields);
    }
    if (nameAnimationInput) {
        nameAnimationInput.addEventListener('change', toggleNameFields);
    }
    // Run initial toggle
    toggleNameFields();

    function updatePreview() {
        const name = displayNameInput.value.trim() || usernameInput.value.trim() || 'Utente';
        const previewNameEl = $('#previewName');
        if (previewNameEl) {
            const escapeHtml = (str) => str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            
            const colorType = nameColorTypeInput ? nameColorTypeInput.value : 'default';
            const solidColor = nameSolidColorInput ? nameSolidColorInput.value : '#ffffff';
            const gradColor1 = nameGradColor1Input ? nameGradColor1Input.value : '#ffffff';
            const gradColor2 = nameGradColor2Input ? nameGradColor2Input.value : '#8b5cf6';
            const gradAngle = nameGradAngleInput ? nameGradAngleInput.value : '90';
            const anim = nameAnimationInput ? nameAnimationInput.value : 'none';
            const glowColor = nameGlowColorInput ? nameGlowColorInput.value : '#8b5cf6';

            previewNameEl.dataset.nameType = colorType;
            previewNameEl.dataset.nameAnim = anim;
            previewNameEl.dataset.text = name;

            previewNameEl.style.setProperty('--name-color1', solidColor);
            previewNameEl.style.setProperty('--name-color2', gradColor1);
            previewNameEl.style.setProperty('--name-color3', gradColor2);
            previewNameEl.style.setProperty('--name-angle', `${gradAngle}deg`);
            previewNameEl.style.setProperty('--name-glow-color', glowColor);

            if (anim === 'bounce') {
                let formatted = '';
                const chars = [...name];
                chars.forEach((char, i) => {
                    if (char === ' ') {
                        formatted += `<span class="name-char space-char" style="--char-index: ${i};">&nbsp;</span>`;
                    } else {
                        formatted += `<span class="name-char" style="--char-index: ${i};">${escapeHtml(char)}</span>`;
                    }
                });
                previewNameEl.innerHTML = formatted;
            } else {
                previewNameEl.textContent = name;
            }

            if (typeof window.initNameSparkles === 'function') {
                window.initNameSparkles();
            }
        }
        $('#previewUsername').textContent = '@' + (usernameInput.value.trim() || 'username');
        $('#previewBio').textContent = bioInput.value.trim() || 'La tua bio apparirà qui.';
        const statusBadge = $('#previewStatusBadge');
        if (statusBadge) {
            const status = statusInput && statusInput.value.trim() ? statusInput.value.trim() : 'Stato';
            statusBadge.innerHTML = `<i class="fas fa-signal"></i>${escapeAttr(status)}`;
        }        bioCounter.textContent = bioInput.value.length;
        document.body.style.setProperty('--accent', accentInput.value);
        document.body.style.setProperty('--accent-rgb', hexToRgbLocal(accentInput.value));
        document.body.style.setProperty('--profile-accent', accentInput.value);
        document.body.style.setProperty('--accent-2', secondaryColorInput ? secondaryColorInput.value : accentInput.value);
        
        // Custom card opacity, color mix and border variables preview
        const cardCol = cardColorInput ? cardColorInput.value : '#080c18';
        const opacityVal = cardOpacityInput ? parseInt(cardOpacityInput.value, 10) : 68;
        const blurVal = cardBlurInput ? parseInt(cardBlurInput.value, 10) : 20;
        const borderOpacityVal = borderOpacityInput ? parseInt(borderOpacityInput.value, 10) : 100;
        const radiusVal = borderRadiusInput ? parseInt(borderRadiusInput.value, 10) : 30;
        const borderWVal = borderWidthInput ? parseInt(borderWidthInput.value, 10) : 1;
        
        document.body.style.setProperty('--radius-lg', `${radiusVal}px`);
        document.body.style.setProperty('--radius-md', `${Math.round(radiusVal * 0.73)}px`);
        document.body.style.setProperty('--radius-sm', `${Math.round(radiusVal * 0.47)}px`);
        
        document.body.style.setProperty('--profile-card-opacity', opacityVal / 100);
        document.body.style.setProperty('--profile-card-blur', `${blurVal}px`);
        document.body.style.setProperty('--profile-border-opacity', borderOpacityVal / 100);
        document.body.style.setProperty('--card', `color-mix(in srgb, ${cardCol} ${opacityVal}%, transparent)`);
        document.body.style.setProperty('--profile-card-color', `color-mix(in srgb, ${cardCol} ${opacityVal}%, transparent)`);
        document.body.style.setProperty('--card-strong', `color-mix(in srgb, ${cardCol} ${Math.min(100, opacityVal + 20)}%, transparent)`);
        
        document.body.style.setProperty('--profile-border-width', `${borderWVal}px`);
        if (borderColorInput && borderColorInput.value) {
            document.body.style.setProperty('--border', borderColorInput.value);
            document.body.style.setProperty('--profile-border-color', borderColorInput.value);
        }
        
        if (fontInput && fontInput.value) {
            loadGoogleFontPreview(fontInput.value);
            document.body.style.setProperty('--profile-font', `'${fontInput.value}', sans-serif`);
            document.body.style.fontFamily = `var(--profile-font)`;
        }
        
        if (uiShapeInput) {
            let shapeIco = '50%', shapeBtn = '999px', shapeCard = '24px';
            switch (uiShapeInput.value) {
                case 'circle': shapeIco = '50%'; shapeBtn = '999px'; shapeCard = '24px'; break;
                case 'rounded': shapeIco = '24px'; shapeBtn = '24px'; shapeCard = '24px'; break;
                case 'soft': shapeIco = '16px'; shapeBtn = '16px'; shapeCard = '16px'; break;
                case 'square-rounded': shapeIco = '8px'; shapeBtn = '8px'; shapeCard = '8px'; break;
                case 'square': shapeIco = '0px'; shapeBtn = '0px'; shapeCard = '0px'; break;
                case 'pill': shapeIco = '999px'; shapeBtn = '999px'; shapeCard = '999px'; break;
            }
            document.body.style.setProperty('--ui-shape-icon', shapeIco);
            document.body.style.setProperty('--ui-shape-button', shapeBtn);
            document.body.style.setProperty('--ui-shape-card', shapeCard);
        }
        if (socialSizeInput) {
            document.body.style.setProperty('--social-icon-size', `${socialSizeInput.value}px`);
            if (socialSizeVal) socialSizeVal.textContent = `${socialSizeInput.value}px`;
        }
        if (iconSpacingInput) {
            document.body.style.setProperty('--social-icon-spacing', `${iconSpacingInput.value}px`);
            if (iconSpacingVal) iconSpacingVal.textContent = `${iconSpacingInput.value}px`;
        }
        if (badgeSizeInput) {
            document.body.style.setProperty('--badge-size', `${badgeSizeInput.value}px`);
            if (badgeSizeVal) badgeSizeVal.textContent = `${badgeSizeInput.value}px`;
        }
        if (buttonSizeInput) {
            document.body.style.setProperty('--button-height', `${buttonSizeInput.value}px`);
            if (buttonSizeVal) buttonSizeVal.textContent = `${buttonSizeInput.value}px`;
        }
        if (avatarShapeInput) document.body.dataset.avatarShape = avatarShapeInput.value;
        if (avatarBorderInput) document.body.dataset.avatarBorder = avatarBorderInput.checked ? '1' : '0';

        document.body.style.setProperty('--profile-ring', ringColorInput ? ringColorInput.value : accentInput.value);
        document.body.dataset.accent = accentInput.value;
        document.body.dataset.profileLinkStyle = linkStyleInput ? linkStyleInput.value : 'glass';
        document.body.dataset.profileButtonShape = buttonShapeInput ? buttonShapeInput.value : 'pill';
        document.body.dataset.theme = themeInput.value === 'auto' ? 'dark' : themeInput.value;
        document.body.dataset.profileEffect = profileEffectInput ? profileEffectInput.value : 'none';
        const glassRainWarning = $('#glassRainWarning');
        if (glassRainWarning) {
            glassRainWarning.style.display = (profileEffectInput && profileEffectInput.value === 'glass_rain') ? 'flex' : 'none';
        }
        if (typeof window.initProfileEffects === 'function') {
            window.initProfileEffects();
        }
        if (layoutInput) {
            document.body.dataset.profileLayout = layoutInput.value;
        }
        if (socialsStyleInput) {
            document.body.dataset.profileSocialsStyle = socialsStyleInput.value;
        }
        const previewCard = document.querySelector('.profile-preview-card');
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

    [displayNameInput, usernameInput, bioInput, statusInput, accentInput, secondaryColorInput, cardColorInput, textColorInput, linkStyleInput, buttonShapeInput, themeInput, profileEffectInput, ringEnabledInput, avatarBorderInput, ringStyleInput, ringColorInput, discordUseNameInput, discordUseAvatarInput, socialsStyleInput, layoutInput, clickToEnterInput, enterTextInput, fontInput, borderRadiusInput, cardOpacityInput, cardBlurInput, borderOpacityInput, borderColorInput, borderWidthInput, nameColorTypeInput, nameSolidColorInput, nameGradColor1Input, nameGradColor2Input, nameGradAngleInput, nameAnimationInput, nameGlowColorInput, uiShapeInput, avatarShapeInput, socialSizeInput, iconSpacingInput, badgeSizeInput, buttonSizeInput].filter(Boolean).forEach((input) => {
        input.addEventListener('input', updatePreview);
        input.addEventListener('change', updatePreview);
    });


    $$('.ui-preset-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const preset = btn.dataset.preset;
            let p_ui = 'circle', p_av = 'circle', p_br = 30, p_bo = 100, p_bw = 1, p_link = 'glass', p_btn = 'pill';
            switch (preset) {
                case 'modern': p_ui='soft'; p_av='squircle'; p_br=24; p_bo=20; p_bw=1; p_link='outline'; p_btn='rounded'; break;
                case 'glass': p_ui='circle'; p_av='circle'; p_br=30; p_bo=30; p_bw=1; p_link='glass'; p_btn='pill'; break;
                case 'bubble': p_ui='circle'; p_av='circle'; p_br=40; p_bo=0; p_bw=0; p_link='solid'; p_btn='pill'; break;
                case 'sharp': p_ui='square'; p_av='square'; p_br=0; p_bo=100; p_bw=2; p_link='outline'; p_btn='sharp'; break;
                case 'cyber': p_ui='square-rounded'; p_av='hexagon'; p_br=8; p_bo=100; p_bw=1; p_link='neon'; p_btn='sharp'; break;
                case 'minimal': p_ui='rounded'; p_av='circle'; p_br=16; p_bo=10; p_bw=1; p_link='solid'; p_btn='rounded'; break;
            }
            
            if (uiShapeInput) uiShapeInput.value = p_ui;
            if (avatarShapeInput) avatarShapeInput.value = p_av;
            if (borderRadiusInput) borderRadiusInput.value = p_br;
            if (borderOpacityInput) borderOpacityInput.value = p_bo;
            if (borderWidthInput) borderWidthInput.value = p_bw;
            if (linkStyleInput) linkStyleInput.value = p_link;
            if (buttonShapeInput) buttonShapeInput.value = p_btn;
            
            [uiShapeInput, avatarShapeInput, borderRadiusInput, borderOpacityInput, borderWidthInput, linkStyleInput, buttonShapeInput].filter(Boolean).forEach(inp => {
                inp.dispatchEvent(new Event('input', { bubbles: true }));
                inp.dispatchEvent(new Event('change', { bubbles: true }));
            });
            updatePreview();
            if (typeof window.profileToast === 'function') {
                window.profileToast(window.location.pathname.includes('/en/') ? 'UI Style preset applied.' : 'UI Style preset applicato.');
            }
        });
    });

    $$('.profile-preset-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (accentInput && btn.dataset.accent) {
                accentInput.value = btn.dataset.accent;
                accentInput.dispatchEvent(new Event('input', { bubbles: true }));
                accentInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (secondaryColorInput && btn.dataset.secondary) {
                secondaryColorInput.value = btn.dataset.secondary;
                secondaryColorInput.dispatchEvent(new Event('input', { bubbles: true }));
                secondaryColorInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (cardColorInput && btn.dataset.card) {
                cardColorInput.value = btn.dataset.card;
                cardColorInput.dispatchEvent(new Event('input', { bubbles: true }));
                cardColorInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (textColorInput && btn.dataset.text) {
                textColorInput.value = btn.dataset.text;
                textColorInput.dispatchEvent(new Event('input', { bubbles: true }));
                textColorInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            updatePreview();
            if (typeof window.profileToast === 'function') {
                window.profileToast('Palette preset applicata.');
            }
        });
    });

    const resetDesignBtn = $('#resetDesignBtn');
    if (resetDesignBtn) {
        resetDesignBtn.addEventListener('click', () => {
            const isIt = document.documentElement.lang === 'it';
            const msg = isIt 
                ? 'Sei sicuro di voler ripristinare tutte le impostazioni del design ai valori di default?' 
                : 'Are you sure you want to reset all design settings to default values?';
            
            if (!confirm(msg)) return;

            const defaults = {
                accentInput: '#0f5bff',
                secondaryColorInput: '#8b5cf6',
                themeInput: 'dark',
                layoutInput: 'standard',
                cardColorInput: '#080c18',
                textColorInput: '#f7f8ff',
                linkStyleInput: 'glass',
                buttonShapeInput: 'pill',
                socialsStyleInput: 'cards',
                fontInput: 'Poppins',
                cardOpacityInput: '68',
                cardBlurInput: '20',
                borderOpacityInput: '100',
                borderRadiusInput: '30',
                borderWidthInput: '1',
                borderColorInput: '#ffffff'
            };

            const inputsMap = {
                accentInput,
                secondaryColorInput,
                themeInput,
                layoutInput,
                cardColorInput,
                textColorInput,
                linkStyleInput,
                buttonShapeInput,
                socialsStyleInput,
                fontInput,
                cardOpacityInput,
                cardBlurInput,
                borderOpacityInput,
                borderRadiusInput,
                borderWidthInput,
                borderColorInput
            };

            Object.entries(defaults).forEach(([key, val]) => {
                const input = inputsMap[key];
                if (input) {
                    input.value = val;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            updatePreview();

            if (typeof window.profileToast === 'function') {
                window.profileToast('Impostazioni di design ripristinate.');
            }
        });
    }

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
        if (select.name === 'profile_font') {
            current.style.fontFamily = `'${selected.value}', sans-serif`;
        }

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
            if (select.name === 'profile_font') {
                button.style.fontFamily = `'${option.value}', sans-serif`;
            }

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

    const closeAllProfileColorPickers = (except = null) => {
        document.querySelectorAll('[data-profile-color-picker].is-open').forEach((wrap) => {
            if (except && wrap === except) return;
            wrap.classList.remove('is-open');
        });
    };

    function hexToHsvLocal(hex) {
        let clean = String(hex || '').replace('#', '').trim();
        if (clean.length === 3) {
            clean = clean.split('').map(c => c + c).join('');
        }
        if (!/^[0-9a-fA-F]{6}$/.test(clean)) return { h: 0, s: 0, v: 0 };
        const r = parseInt(clean.substring(0, 2), 16) / 255;
        const g = parseInt(clean.substring(2, 4), 16) / 255;
        const b = parseInt(clean.substring(4, 6), 16) / 255;
        
        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        const d = max - min;
        let h;
        if (d === 0) {
            h = 0;
        } else {
            switch (max) {
                case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                case g: h = (b - r) / d + 2; break;
                case b: h = (r - g) / d + 4; break;
            }
            h /= 6;
        }
        return {
            h: Math.round(h * 360),
            s: max === 0 ? 0 : d / max,
            v: max
        };
    }

    function hsvToHexLocal(h, s, v) {
        let r, g, b;
        const i = Math.floor(h / 60);
        const f = h / 60 - i;
        const p = v * (1 - s);
        const q = v * (1 - f * s);
        const t = v * (1 - (1 - f) * s);
        switch (i % 6) {
            case 0: r = v, g = t, b = p; break;
            case 1: r = q, g = v, b = p; break;
            case 2: r = p, g = v, b = t; break;
            case 3: r = p, g = q, b = v; break;
            case 4: r = t, g = p, b = v; break;
            case 5: r = v, g = p, b = q; break;
        }
        const toHex = x => {
            const hex = Math.round(x * 255).toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        };
        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    }

    const syncProfileColorPicker = (wrap) => {
        const input = wrap.querySelector('input[type="color"]');
        const preview = wrap.querySelector('.profile-color-preview');
        const value = wrap.querySelector('.profile-color-value');
        const textInput = wrap.querySelector('.profile-color-hex-text');
        const swatches = Array.from(wrap.querySelectorAll('.profile-color-swatch'));

        const svSquare = wrap.querySelector('.profile-color-sv-square');
        const svHandle = wrap.querySelector('.profile-color-sv-handle');
        const hueSlider = wrap.querySelector('.profile-color-hue-slider');
        const hueHandle = wrap.querySelector('.profile-color-hue-handle');

        if (!input || !preview || !value) return;

        const val = input.value;
        preview.style.backgroundColor = val;
        value.textContent = val;
        if (textInput && textInput.value.toLowerCase() !== val.toLowerCase()) {
            textInput.value = val;
        }

        swatches.forEach((swatch) => {
            const active = swatch.dataset.color.toLowerCase() === val.toLowerCase();
            swatch.classList.toggle('is-active', active);
        });

        if (svSquare && svHandle && hueSlider && hueHandle) {
            const hsv = hexToHsvLocal(val);
            svSquare.style.backgroundColor = `hsl(${hsv.h}, 100%, 50%)`;
            svHandle.style.left = `${hsv.s * 100}%`;
            svHandle.style.top = `${(1 - hsv.v) * 100}%`;
            hueHandle.style.left = `${(hsv.h / 360) * 100}%`;
        }
    };

    const buildProfileColorPicker = (input) => {
        if (!input || input.dataset.profileColorBuilt === '1') return;
        input.dataset.profileColorBuilt = '1';

        // Convert parent <label> to <div> to prevent default label activation of the color input
        let container = input.parentNode;
        if (container && container.tagName === 'LABEL') {
            const div = document.createElement('div');
            Array.from(container.attributes).forEach(attr => {
                div.setAttribute(attr.name, attr.value);
            });
            while (container.firstChild) {
                div.appendChild(container.firstChild);
            }
            container.parentNode.replaceChild(div, container);
            container = div;
        }

        input.classList.add('profile-native-select');

        const wrap = document.createElement('div');
        wrap.className = 'profile-custom-color-picker';
        wrap.dataset.profileColorPicker = '1';

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'profile-color-trigger';
        trigger.innerHTML = `
            <span class="profile-color-preview"></span>
            <span class="profile-color-value"></span>
            <i class="fas fa-palette"></i>
        `;

        const dropdown = document.createElement('div');
        dropdown.className = 'profile-color-dropdown';
        
        dropdown.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        const presetColors = [
            '#f43f5e', '#ec4899', '#d946ef', '#a855f7', '#6366f1', '#3b82f6',
            '#06b6d4', '#0ea5e9', '#14b8a6', '#10b981', '#22c55e', '#eab308',
            '#f97316', '#ef4444', '#ffffff', '#9ca3af', '#4b5563', '#080c18'
        ];

        const swatchesGrid = document.createElement('div');
        swatchesGrid.className = 'profile-color-swatches';
        presetColors.forEach((color) => {
            const swatch = document.createElement('div');
            swatch.className = 'profile-color-swatch';
            swatch.style.backgroundColor = color;
            swatch.dataset.color = color;
            swatch.title = color;

            swatch.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                input.value = color;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                syncProfileColorPicker(wrap);
            });

            swatchesGrid.appendChild(swatch);
        });
        dropdown.appendChild(swatchesGrid);

        const advancedContainer = document.createElement('div');
        advancedContainer.className = 'profile-color-picker-advanced';

        const svSquare = document.createElement('div');
        svSquare.className = 'profile-color-sv-square';
        svSquare.innerHTML = `
            <div class="profile-color-sv-white"></div>
            <div class="profile-color-sv-black"></div>
            <div class="profile-color-sv-handle"></div>
        `;

        const hueSlider = document.createElement('div');
        hueSlider.className = 'profile-color-hue-slider';
        hueSlider.innerHTML = `
            <div class="profile-color-hue-handle"></div>
        `;

        advancedContainer.appendChild(svSquare);
        advancedContainer.appendChild(hueSlider);
        dropdown.appendChild(advancedContainer);

        const customRow = document.createElement('div');
        customRow.className = 'profile-color-custom-row';

        const textInput = document.createElement('input');
        textInput.type = 'text';
        textInput.className = 'profile-color-hex-text';
        textInput.placeholder = '#000000';
        textInput.maxLength = 7;

        textInput.addEventListener('input', () => {
            let val = textInput.value.trim();
            if (!val.startsWith('#')) {
                val = '#' + val;
            }
            const hexRegex = /^#([A-Fa-f0-9]{3}){1,2}$/;
            if (hexRegex.test(val)) {
                input.value = val;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                syncProfileColorPicker(wrap);
            }
        });

        customRow.appendChild(textInput);
        dropdown.appendChild(customRow);

        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        wrap.appendChild(trigger);
        wrap.appendChild(dropdown);

        const handleDrag = (element, callback) => {
            const onDrag = (e) => {
                const rect = element.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                let x = (clientX - rect.left) / rect.width;
                let y = (clientY - rect.top) / rect.height;
                x = Math.max(0, Math.min(1, x));
                y = Math.max(0, Math.min(1, y));
                callback(x, y);
            };

            const onMouseDown = (e) => {
                e.preventDefault();
                onDrag(e);

                const onMouseMove = (moveEvent) => {
                    onDrag(moveEvent);
                };
                const onMouseUp = () => {
                    window.removeEventListener('mousemove', onMouseMove);
                    window.removeEventListener('mouseup', onMouseUp);
                    window.removeEventListener('touchmove', onMouseMove);
                    window.removeEventListener('touchend', onMouseUp);
                };
                window.addEventListener('mousemove', onMouseMove);
                window.addEventListener('mouseup', onMouseUp);
                window.addEventListener('touchmove', onMouseMove);
                window.addEventListener('touchend', onMouseUp);
            };

            element.addEventListener('mousedown', onMouseDown);
            element.addEventListener('touchstart', onMouseDown, { passive: false });
        };

        handleDrag(svSquare, (x, y) => {
            const hsv = hexToHsvLocal(input.value);
            const newHex = hsvToHexLocal(hsv.h, x, 1 - y);
            input.value = newHex;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            syncProfileColorPicker(wrap);
        });

        handleDrag(hueSlider, (x) => {
            const hsv = hexToHsvLocal(input.value);
            const newHex = hsvToHexLocal(Math.round(x * 360), hsv.s, hsv.v);
            input.value = newHex;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            syncProfileColorPicker(wrap);
        });

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            closeAllProfileColorPickers(wrap);
            closeAllProfileSelects();

            const isOpen = wrap.classList.toggle('is-open');
        });

        input.addEventListener('input', () => syncProfileColorPicker(wrap));
        input.addEventListener('change', () => syncProfileColorPicker(wrap));

        if (container) {
            container.addEventListener('click', (event) => {
                if (event.target === container || event.target.tagName === 'SPAN') {
                    event.preventDefault();
                    event.stopPropagation();
                    trigger.click();
                }
            });
        }

        syncProfileColorPicker(wrap);
    };

    const initProfileCustomColorPickers = (root = document) => {
        root.querySelectorAll('.profile-field input[type="color"]').forEach(buildProfileColorPicker);
    };

    const initProfileCustomSelects = (root = document) => {
        root.querySelectorAll('.profile-field select, .profile-row-grid select').forEach(buildProfileSelect);
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
                    initProfileCustomColorPickers(node);
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
        closeAllProfileColorPickers();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllProfileSelects();
            closeAllProfileColorPickers();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        initProfileCustomSelects();
        initProfileCustomColorPickers();
        startProfileSelectObserver();

        setTimeout(refreshProfileCustomSelects, 0);
        setTimeout(refreshProfileCustomSelects, 100);
    });

    if (document.readyState !== 'loading') {
        initProfileCustomSelects();
        initProfileCustomColorPickers();
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