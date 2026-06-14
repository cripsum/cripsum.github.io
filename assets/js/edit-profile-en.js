(function () {
    const form = document.getElementById('profileEditForm');
    if (!form) return;
    const targetUserId = form.querySelector('input[name="target_user_id"]')?.value || '';
    const isEnglish = document.documentElement.lang === 'en' || window.location.pathname.includes('/en/');

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
        tags: $('#tagsRepeater'),
    };

    const platformOptions = ['tiktok', 'instagram', 'youtube', 'twitch', 'github', 'discord', 'telegram', 'x', 'spotify', 'soundcloud', 'steam', 'reddit', 'pinterest', 'snapchat', 'facebook', 'linkedin', 'paypal', 'patreon', 'kick', 'bluesky', 'threads', 'behance', 'dribbble', 'website', 'email', 'other'];
    const projectStatuses = isEnglish 
        ? [['active', 'Active'], ['paused', 'On Hold'], ['finished', 'Finished'], ['idea', 'Idea']]
        : [['active', 'Attivo'], ['paused', 'In pausa'], ['finished', 'Finito'], ['idea', 'Idea']];
    const contentTypes = isEnglish
        ? [['edit', 'Edit'], ['video', 'Video'], ['game', 'Game'], ['post', 'Post'], ['other', 'Other']]
        : [['edit', 'Edit'], ['video', 'Video'], ['game', 'Gioco'], ['post', 'Post'], ['other', 'Altro']];
    const blockTypes = isEnglish
        ? [['text', 'Text'], ['image', 'Image'], ['gif', 'GIF'], ['video', 'Video']]
        : [['text', 'Testo'], ['image', 'Immagine'], ['gif', 'GIF'], ['video', 'Video']];
    const linkButtonStyles = isEnglish
        ? [['card', 'Card'], ['compact', 'Compact'], ['icon', 'Icon only']]
        : [['card', 'Card'], ['compact', 'Compatto'], ['icon', 'Solo icona']];
    const embedTypes = isEnglish
        ? [['spotify', 'Spotify'], ['youtube', 'YouTube Playlist/Video'], ['custom', 'Custom Iframe/Safe Widget']]
        : [['spotify', 'Spotify'], ['youtube', 'YouTube Playlist/Video'], ['custom', 'Iframe custom/Safe Widget']];

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
                    <label>${isEnglish ? 'Platform' : 'Platform'}<select data-field="platform">${options(platformOptions, data.platform || 'website')}</select></label>
                    <label>${isEnglish ? 'Label' : 'Label'}<input data-field="label" maxlength="40" value="${escapeAttr(data.label || '')}" placeholder="TikTok"></label>
                    <label>${isEnglish ? 'Display username' : 'Username da mostrare'}<input data-field="display_username" maxlength="60" value="${escapeAttr(data.display_username || '')}" placeholder="@username / nome"></label>
                    <label class="profile-row-grid full">URL<input data-field="url" value="${escapeAttr(data.url || '')}" placeholder="https://..."></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> ${isEnglish ? 'Visible' : 'Visibile'}</label>
                </div>`;
        }

        if (type === 'links') {
            body = `
                <div class="profile-row-grid">
                    <label>${isEnglish ? 'Title' : 'Titolo'}<input data-field="title" maxlength="60" value="${escapeAttr(data.title || '')}" placeholder="Portfolio"></label>
                    <label>${isEnglish ? 'FontAwesome Icon' : 'Icona FontAwesome'}<input data-field="icon" maxlength="40" value="${escapeAttr(data.icon || 'fas fa-link')}" placeholder="fab fa-spotify"></label>
                    <label>${isEnglish ? 'Button type' : 'Tipo tasto'}<select data-field="button_style">${options(linkButtonStyles, data.button_style || 'card')}</select></label>
                    <label class="profile-row-grid full">${isEnglish ? 'Description' : 'Descrizione'}<input data-field="description" maxlength="160" value="${escapeAttr(data.description || '')}" placeholder="Una frase breve"></label>
                    <label class="profile-row-grid full">URL<input data-field="url" value="${escapeAttr(data.url || '')}" placeholder="https://..."></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_featured" ${boolAttr(data.is_featured)}> ${isEnglish ? 'Featured' : 'In evidenza'}</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> ${isEnglish ? 'Visible' : 'Visibile'}</label>
                </div>`;
        }

        if (type === 'embeds') {
            body = `
                <div class="profile-row-grid">
                    <label>${isEnglish ? 'Embed Type' : 'Tipo Embed'}<select data-field="type">${options(embedTypes, data.type || 'spotify')}</select></label>
                    <label>${isEnglish ? 'Title (optional)' : 'Titolo (opzionale)'}<input data-field="title" maxlength="100" value="${escapeAttr(data.title || '')}" placeholder="Es. Spotify Playlist"></label>
                    <label class="profile-row-grid full">${isEnglish ? 'Embed URL' : "URL dell'embed (o URL classico Spotify/YouTube)"}<input data-field="url" value="${escapeAttr(data.url || '')}" placeholder="https://open.spotify.com/... o https://www.youtube.com/..."></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> ${isEnglish ? 'Visible' : 'Visibile'}</label>
                </div>`;
        }

        if (type === 'projects') {
            body = `
                <div class="profile-row-grid">
                    <label>${isEnglish ? 'Title' : 'Titolo'}<input data-field="title" maxlength="70" value="${escapeAttr(data.title || '')}" placeholder="Nome progetto"></label>
                    <label>${isEnglish ? 'Status' : 'Stato'}<select data-field="status">${options(projectStatuses, data.status || 'active')}</select></label>
                    <label class="profile-row-grid full">${isEnglish ? 'Description' : 'Descrizione'}<textarea data-field="description" maxlength="260" placeholder="Cosa fa questo progetto">${escapeAttr(data.description || '')}</textarea></label>
                    <label>URL<input data-field="url" value="${escapeAttr(data.url || '')}" placeholder="https://..."></label>
                    <label>${isEnglish ? 'Image URL' : 'Immagine URL'}<input data-field="image_url" value="${escapeAttr(data.image_url || '')}" placeholder="https://..."></label>
                    <label class="profile-row-grid full">Tech stack<input data-field="tech_stack" maxlength="160" value="${escapeAttr(data.tech_stack || '')}" placeholder="PHP, JS, MySQL"></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_featured" ${boolAttr(data.is_featured)}> ${isEnglish ? 'Featured' : 'In evidenza'}</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> ${isEnglish ? 'Visible' : 'Visibile'}</label>
                </div>`;
        }

        if (type === 'contents') {
            body = `
                <div class="profile-row-grid">
                    <label>${isEnglish ? 'Type' : 'Tipo'}<select data-field="content_type">${options(contentTypes, data.content_type || 'edit')}</select></label>
                    <label>${isEnglish ? 'Title' : 'Titolo'}<input data-field="title" maxlength="70" value="${escapeAttr(data.title || '')}" placeholder="Titolo contenuto"></label>
                    <label class="profile-row-grid full">${isEnglish ? 'Description' : 'Descrizione'}<textarea data-field="description" maxlength="220" placeholder="Descrizione breve">${escapeAttr(data.description || '')}</textarea></label>
                    <label>URL<input data-field="url" value="${escapeAttr(data.url || '')}" placeholder="https://..."></label>
                    <label>Thumbnail URL<input data-field="thumbnail_url" value="${escapeAttr(data.thumbnail_url || '')}" placeholder="https://..."></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_featured" ${boolAttr(data.is_featured)}> ${isEnglish ? 'Featured' : 'In evidenza'}</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> ${isEnglish ? 'Visible' : 'Visibile'}</label>
                </div>`;
        }

        if (type === 'blocks') {
            body = `
                <div class="profile-row-grid">
                    <label>${isEnglish ? 'Type' : 'Tipo'}<select data-field="block_type">${options(blockTypes, data.block_type || 'text')}</select></label>
                    <label>${isEnglish ? 'Title' : 'Titolo'}<input data-field="title" maxlength="80" value="${escapeAttr(data.title || '')}" placeholder="Titolo del post"></label>
                    <label class="profile-row-grid full">${isEnglish ? 'Text' : 'Testo'}<textarea data-field="body" maxlength="700" placeholder="Testo breve, nota, descrizione o quote">${escapeAttr(data.body || '')}</textarea></label>
                    <label>${isEnglish ? 'Media URL' : 'Media URL'}<input data-field="media_url" value="${escapeAttr(data.media_url || '')}" placeholder="https://... immagine/gif/video"></label>
                    <label>${isEnglish ? 'Media type' : 'Media type'}<select data-field="media_type">${options(blockTypes, data.media_type || data.block_type || 'image')}</select></label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_featured" ${boolAttr(data.is_featured)}> Pin</label>
                    <label class="profile-check-line"><input type="checkbox" data-field="is_visible" ${boolAttr(data.is_visible ?? 1)}> ${isEnglish ? 'Visible' : 'Visibile'}</label>
                </div>`;
        }

        if (type === 'tags') {
            body = `
                <div class="profile-row-grid">
                    <label>${isEnglish ? 'Pill Text' : 'Testo Pillola'}<input data-field="text" maxlength="40" value="${escapeAttr(data.text || '')}" placeholder="Es. JavaScript"></label>
                    <label>${isEnglish ? 'FontAwesome Icon (opt)' : 'Icona FontAwesome (opzionale)'}<input data-field="icon" maxlength="40" value="${escapeAttr(data.icon || '')}" placeholder="fab fa-js"></label>
                    <div class="profile-row-grid two full" style="margin-top: 10px;">
                        <label class="profile-check-line"><input type="checkbox" data-field="use_color" ${boolAttr(data.use_color ?? (data.color ? 1 : 0))}> ${isEnglish ? 'Use Custom Color' : 'Usa Colore Personalizzato'}</label>
                        <label class="profile-check-line"><input type="checkbox" data-field="use_gradient" ${boolAttr(data.use_gradient ?? (data.gradient ? 1 : 0))}> ${isEnglish ? 'Use Gradient' : 'Usa Gradiente'}</label>
                    </div>
                    <div class="profile-row-grid two full tag-color-inputs" style="display: ${data.use_color || data.color ? 'grid' : 'none'};">
                        <label>${isEnglish ? 'Background Color' : 'Colore Sfondo'}<input type="color" data-field="color" value="${escapeAttr(data.color || '#8b5cf6')}"></label>
                        <label class="tag-gradient-input" style="display: ${data.use_gradient || data.gradient ? 'block' : 'none'};">${isEnglish ? 'Background Gradient' : 'Gradiente Sfondo'}<input type="color" data-field="gradient" value="${escapeAttr(data.gradient || '#ec4899')}"></label>
                    </div>
                </div>`;
        }

        const labelText = type === 'socials' ? (isEnglish ? 'Social' : 'Social')
            : type === 'links' ? (isEnglish ? 'Link' : 'Link')
            : type === 'embeds' ? (isEnglish ? 'Embed' : 'Embed')
            : type === 'projects' ? (isEnglish ? 'Project' : 'Progetto')
            : type === 'blocks' ? (isEnglish ? 'Block' : 'Blocco')
            : type === 'tags' ? (isEnglish ? 'Tag' : 'Tag')
            : (isEnglish ? 'Content' : 'Contenuto');

        row.innerHTML = `
            <div class="profile-row-head">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div class="profile-row-handle" style="cursor: grab;"><i class="fas fa-grip-vertical"></i></div>
                    <strong>${labelText}</strong>
                </div>
                <div class="profile-row-actions">
                    <button type="button" class="profile-move-up" title="${isEnglish ? 'Move up' : 'Sposta su'}"><i class="fas fa-arrow-up"></i></button>
                    <button type="button" class="profile-move-down" title="${isEnglish ? 'Move down' : 'Sposta giù'}"><i class="fas fa-arrow-down"></i></button>
                    <button type="button" class="profile-remove-row">${isEnglish ? 'Remove' : 'Rimuovi'}</button>
                </div>
            </div>
            ${body}`;

        $('.profile-remove-row', row).addEventListener('click', () => {
            row.remove();
            updatePreview();
            triggerAutosave(true);
            pushHistoryState();
        });

        const btnUp = $('.profile-move-up', row);
        const btnDown = $('.profile-move-down', row);
        
        btnUp.addEventListener('click', () => {
            const prev = row.previousElementSibling;
            if (prev && prev.classList.contains('profile-row-card')) {
                row.parentNode.insertBefore(row, prev);
                updatePreview();
                triggerAutosave(true);
                pushHistoryState();
            }
        });
        
        btnDown.addEventListener('click', () => {
            const next = row.nextElementSibling;
            if (next && next.classList.contains('profile-row-card')) {
                row.parentNode.insertBefore(next, row);
                updatePreview();
                triggerAutosave(true);
                pushHistoryState();
            }
        });

        if (type === 'tags') {
            setTimeout(() => {
                const useColorChk = row.querySelector('[data-field="use_color"]');
                const useGradientChk = row.querySelector('[data-field="use_gradient"]');
                const colorContainer = row.querySelector('.tag-color-inputs');
                const gradientContainer = row.querySelector('.tag-gradient-input');
                
                if (useColorChk && colorContainer) {
                    useColorChk.addEventListener('change', () => {
                        colorContainer.style.display = useColorChk.checked ? 'grid' : 'none';
                        updatePreview();
                        triggerAutosave(true);
                    });
                }
                if (useGradientChk && gradientContainer) {
                    useGradientChk.addEventListener('change', () => {
                        gradientContainer.style.display = useGradientChk.checked ? 'block' : 'none';
                        updatePreview();
                        triggerAutosave(true);
                    });
                }
            }, 0);
        }

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
    readJson('initialTagsData').forEach((item) => addRow('tags', item));

    Object.entries(repeaters).forEach(([type, node]) => {
        if (node && node.children.length === 0 && type !== 'tags') addRow(type, {});
    });

    $$('[data-add-row]').forEach((button) => {
        button.addEventListener('click', () => {
            addRow(button.dataset.addRow, {});
            updatePreview();
            triggerAutosave(true);
            pushHistoryState();
        });
    });

    function collectRows(type) {
        if (!repeaters[type]) return [];
        return $$('.profile-row-card', repeaters[type]).map((row) => {
            const obj = {};
            $$('[data-field]', row).forEach((input) => {
                const key = input.dataset.field;
                obj[key] = input.type === 'checkbox' ? input.checked : input.value.trim();
            });
            if (type === 'tags') {
                if (!obj.use_color) {
                    obj.color = '';
                    obj.gradient = '';
                } else if (!obj.use_gradient) {
                    obj.gradient = '';
                }
            }
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

    const loadedFonts = new Set();
    function loadGoogleFontPreview(fontName) {
        if (!fontName || fontName === 'Poppins' || fontName === 'Minecraft' || fontName === 'Gang of Three' || loadedFonts.has(fontName)) return;
        loadedFonts.add(fontName);
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(fontName)}:wght@300;400;500;600;700;800&display=swap`;
        document.head.appendChild(link);
    }

    const tiltPresetInput = $('#tiltPresetInput');
    const tiltEnabledInput = $('#tiltEnabledInput');
    const tiltMaxInput = $('#tiltMaxInput');
    const tiltGlareInput = $('#tiltGlareInput');
    const tiltZoomInput = $('#tiltZoomInput');
    const tiltSpeedInput = $('#tiltSpeedInput');
    const tiltCustomControls = $('#tiltCustomControls');

    const tiltMaxVal = $('#tiltMaxVal');
    const tiltGlareVal = $('#tiltGlareVal');
    const tiltZoomVal = $('#tiltZoomVal');
    const tiltSpeedVal = $('#tiltSpeedVal');

    const profileTabTitleInput = $('#profileTabTitleInput');
    const profileTabAnimationInput = $('#profileTabAnimationInput');
    const profileTabAnimationTextInput = $('#profileTabAnimationTextInput');
    const profileTabAnimationSpeedInput = $('#profileTabAnimationSpeedInput');
    const profileTabSpeedVal = $('#profileTabSpeedVal');

    const cornerStyleInput = $('#cornerStyleInput');
    const cornerStyleCustomInput = $('#cornerStyleCustomInput');
    const cornerStyleCustomContainer = $('#cornerStyleCustomContainer');
    const cornerStyleCustomVal = $('#cornerStyleCustomVal');

    const borderStyleInput = $('#borderStyleInput');

    const nameColorTypeInput = $('#nameColorTypeInput');
    const nameSolidColorInput = $('#nameSolidColorInput');
    const nameGradColor1Input = $('#nameGradColor1Input');
    const nameGradColor2Input = $('#nameGradColor2Input');
    const nameGradAngleInput = $('#nameGradAngleInput');
    const nameAnimationInput = $('#nameAnimationInput');
    const nameGlowColorInput = $('#nameGlowColorInput');

    function toggleNameFields() {
        if (!nameColorTypeInput) return;
        const type = nameColorTypeInput.value;
        const anim = nameAnimationInput ? nameAnimationInput.value : 'none';

        $$('.field-name-solid').forEach(el => {
            el.style.display = type === 'solid' ? 'block' : 'none';
        });
        $$('.field-name-gradient').forEach(el => {
            el.style.display = type === 'gradient' ? 'block' : 'none';
        });
        $$('.field-name-glow').forEach(el => {
            el.style.display = (anim === 'glow' || anim === 'neon') ? 'block' : 'none';
        });
    }

    if (nameColorTypeInput) nameColorTypeInput.addEventListener('change', toggleNameFields);
    if (nameAnimationInput) nameAnimationInput.addEventListener('change', toggleNameFields);
    toggleNameFields();

    // ── LIVE PREVIEW POSTMESSAGE PIPELINE ───────────────────────────────────
    function updatePreview() {
        const accentVal = accentInput?.value || '#0f5bff';
        const secondaryVal = secondaryColorInput?.value || accentVal;

        if (accentInput) {
            document.body.style.setProperty('--accent', accentVal);
            document.body.style.setProperty('--accent-rgb', hexToRgbLocal(accentVal));
        }

        const cardCol = cardColorInput?.value || '#080c18';
        const opacityVal = cardOpacityInput ? parseInt(cardOpacityInput.value, 10) : 68;
        const blurVal = cardBlurInput ? parseInt(cardBlurInput.value, 10) : 20;
        const borderOpacityVal = borderOpacityInput ? parseInt(borderOpacityInput.value, 10) : 100;
        const radiusVal = borderRadiusInput ? parseInt(borderRadiusInput.value, 10) : 30;
        const borderWVal = borderWidthInput ? parseInt(borderWidthInput.value, 10) : 1;
        const fontVal = fontInput?.value || 'Poppins';
        
        const variables = {
            '--accent': accentVal,
            '--accent-rgb': hexToRgbLocal(accentVal),
            '--profile-accent': accentVal,
            '--accent-2': secondaryVal,
            '--radius-lg': `${radiusVal}px`,
            '--radius-md': `${Math.round(radiusVal * 0.73)}px`,
            '--radius-sm': `${Math.round(radiusVal * 0.47)}px`,
            '--profile-card-opacity': opacityVal / 100,
            '--profile-card-blur': `${blurVal}px`,
            '--profile-border-opacity': borderOpacityVal / 100,
            '--card': `color-mix(in srgb, ${cardCol} ${opacityVal}%, transparent)`,
            '--profile-card-color': `color-mix(in srgb, ${cardCol} ${opacityVal}%, transparent)`,
            '--card-strong': `color-mix(in srgb, ${cardCol} ${Math.min(100, opacityVal + 20)}%, transparent)`,
            '--profile-border-width': `${borderWVal}px`
        };
        
        if (borderColorInput && borderColorInput.value) {
            variables['--border'] = borderColorInput.value;
            variables['--profile-border-color'] = borderColorInput.value;
        }
        if (fontVal) {
            variables['--profile-font'] = `'${fontVal}', sans-serif`;
            loadGoogleFontPreview(fontVal);
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
            variables['--ui-shape-icon'] = shapeIco;
            variables['--ui-shape-button'] = shapeBtn;
            variables['--ui-shape-card'] = shapeCard;
        }
        
        if (cornerStyleInput) {
            let radius = '100px';
            switch (cornerStyleInput.value) {
                case 'circle': radius = '100px'; break;
                case 'rounded': radius = '12px'; break;
                case 'soft': radius = '6px'; break;
                case 'square': radius = '0px'; break;
                case 'custom': radius = `${cornerStyleCustomInput ? cornerStyleCustomInput.value : 8}px`; break;
            }
            variables['--profile-corner-radius'] = radius;
        }
        
        if (socialSizeInput) {
            variables['--social-icon-size'] = `${socialSizeInput.value}px`;
            if (socialSizeVal) socialSizeVal.textContent = `${socialSizeInput.value}px`;
        }
        if (iconSpacingInput) {
            variables['--social-icon-spacing'] = `${iconSpacingInput.value}px`;
            if (iconSpacingVal) iconSpacingVal.textContent = `${iconSpacingInput.value}px`;
        }
        if (badgeSizeInput) {
            variables['--badge-size'] = `${badgeSizeInput.value}px`;
            if (badgeSizeVal) badgeSizeVal.textContent = `${badgeSizeInput.value}px`;
        }
        if (buttonSizeInput) {
            variables['--button-height'] = `${buttonSizeInput.value}px`;
            if (buttonSizeVal) buttonSizeVal.textContent = `${buttonSizeInput.value}px`;
        }
        
        const iframe = document.getElementById('profilePreviewIframe');
        if (iframe && iframe.contentWindow) {
            iframe.contentWindow.postMessage({
                type: 'update-css-variables',
                variables: variables
            }, '*');
        }
        
        const dName = displayNameInput?.value.trim() || usernameInput?.value.trim() || 'Utente';
        const uName = '@' + (usernameInput?.value.trim() || 'username');
        const bioText = bioInput?.value.trim() || (isEnglish ? 'Your bio will appear here.' : 'La tua bio apparirà qui.');
        
        if (iframe && iframe.contentWindow) {
            iframe.contentWindow.postMessage({
                type: 'update-text',
                texts: {
                    '.profile-display-name': dName,
                    '.bio-username': uName,
                    '.bio-tagline': bioText
                }
            }, '*');
        }
        
        const attributes = {};
        if (themeInput) attributes['data-theme'] = themeInput.value === 'auto' ? 'dark' : themeInput.value;
        if (avatarShapeInput) attributes['data-avatar-shape'] = avatarShapeInput.value;
        if (avatarBorderInput) attributes['data-avatar-border'] = avatarBorderInput.checked ? '1' : '0';
        if (linkStyleInput) attributes['data-profile-link-style'] = linkStyleInput.value;
        if (buttonShapeInput) attributes['data-profile-button-shape'] = buttonShapeInput.value;
        if (profileEffectInput) attributes['data-profile-effect'] = profileEffectInput.value;
        if (layoutInput) attributes['data-profile-layout'] = layoutInput.value;
        if (socialsStyleInput) attributes['data-profile-socials-style'] = socialsStyleInput.value;
        
        if (iframe && iframe.contentWindow) {
            iframe.contentWindow.postMessage({
                type: 'update-attributes',
                attributes: attributes
            }, '*');
        }
        
        if (borderRadiusVal && borderRadiusInput) borderRadiusVal.textContent = borderRadiusInput.value + 'px';
        if (cardOpacityVal && cardOpacityInput) cardOpacityVal.textContent = cardOpacityInput.value + '%';
        if (cardBlurVal && cardBlurInput) cardBlurVal.textContent = cardBlurInput.value + 'px';
        if (borderOpacityVal && borderOpacityInput) borderOpacityVal.textContent = borderOpacityInput.value + '%';
        if (borderWidthVal && borderWidthInput) borderWidthVal.textContent = borderWidthInput.value + 'px';
        if (tiltMaxVal && tiltMaxInput) tiltMaxVal.textContent = tiltMaxInput.value;
        if (tiltGlareVal && tiltGlareInput) tiltGlareVal.textContent = tiltGlareInput.value;
        if (tiltZoomVal && tiltZoomInput) tiltZoomVal.textContent = tiltZoomInput.value;
        if (tiltSpeedVal && tiltSpeedInput) tiltSpeedVal.textContent = tiltSpeedInput.value;
        if (cornerStyleCustomVal && cornerStyleCustomInput) cornerStyleCustomVal.textContent = cornerStyleCustomInput.value;
        if (bioCounter && bioInput) bioCounter.textContent = bioInput.value.length;
    }

    function triggerPreviewStructureReload() {
        const iframe = document.getElementById('profilePreviewIframe');
        if (iframe) {
            iframe.contentWindow.postMessage({ type: 'reload' }, '*');
        }
    }

    // ── DEBOUNCED AUTOSAVE DRAFT SYSTEM ────────────────────────────────────
    let autosaveTimeout = null;
    function triggerAutosave(immediate = false) {
        const statusSpan = document.getElementById('autosaveStatus');
        if (statusSpan) {
            statusSpan.innerHTML = `<i class="fas fa-spinner fa-spin" style="color: var(--accent);"></i> ${isEnglish ? 'Saving draft...' : 'Salvataggio bozza...'}`;
            statusSpan.style.color = 'rgba(255,255,255,0.6)';
        }

        clearTimeout(autosaveTimeout);

        const performSave = async () => {
            const socialsJson = $('#socialsJson');
            const linksJson = $('#linksJson');
            const embedsJson = $('#embedsJson');
            const projectsJson = $('#projectsJson');
            const contentsJson = $('#contentsJson');
            const blocksJson = $('#blocksJson');
            const badgesJson = $('#badgesJson');
            const charactersJson = $('#charactersJson');
            const tagsJson = $('#profileTagsJson');

            if (socialsJson) socialsJson.value = JSON.stringify(collectRows('socials'));
            if (linksJson) linksJson.value = JSON.stringify(collectRows('links'));
            if (embedsJson) embedsJson.value = JSON.stringify(collectRows('embeds'));
            if (projectsJson) projectsJson.value = JSON.stringify(collectRows('projects'));
            if (contentsJson) contentsJson.value = JSON.stringify(collectRows('contents'));
            if (blocksJson) blocksJson.value = JSON.stringify(collectRows('blocks'));
            if (badgesJson) badgesJson.value = JSON.stringify(collectBadges());
            if (charactersJson) charactersJson.value = JSON.stringify(collectCharacters());
            if (tagsJson) tagsJson.value = JSON.stringify(collectRows('tags'));

            const formData = new FormData(form);

            try {
                const res = await fetch('/api/update_profile_draft.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.ok) {
                    if (statusSpan) {
                        statusSpan.innerHTML = `<i class="fas fa-check-circle" style="color: #10b981;"></i> ${isEnglish ? 'Draft saved' : 'Bozza salvata'}`;
                        statusSpan.style.color = 'rgba(255,255,255,0.4)';
                    }
                    if (immediate) {
                        triggerPreviewStructureReload();
                    }
                } else {
                    if (statusSpan) {
                        statusSpan.innerHTML = `<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> ${isEnglish ? 'Save failed' : 'Errore bozza'}`;
                    }
                }
            } catch (e) {
                if (statusSpan) {
                    statusSpan.innerHTML = `<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> ${isEnglish ? 'Save failed' : 'Errore bozza'}`;
                }
            }
        };

        if (immediate) {
            performSave();
        } else {
            autosaveTimeout = setTimeout(performSave, 1000);
        }
    }

    // Register simple inputs listeners for live updates and autosave
    const simpleInputs = [displayNameInput, usernameInput, bioInput, statusInput, accentInput, secondaryColorInput, cardColorInput, textColorInput, linkStyleInput, buttonShapeInput, themeInput, profileEffectInput, ringEnabledInput, avatarBorderInput, ringStyleInput, ringColorInput, discordUseNameInput, discordUseAvatarInput, socialsStyleInput, layoutInput, clickToEnterInput, enterTextInput, fontInput, borderRadiusInput, cardOpacityInput, cardBlurInput, borderOpacityInput, borderColorInput, borderWidthInput, nameColorTypeInput, nameSolidColorInput, nameGradColor1Input, nameGradColor2Input, nameGradAngleInput, nameAnimationInput, nameGlowColorInput, uiShapeInput, avatarShapeInput, socialSizeInput, iconSpacingInput, badgeSizeInput, buttonSizeInput].filter(Boolean);

    simpleInputs.forEach((input) => {
        input.addEventListener('input', () => {
            updatePreview();
            triggerAutosave(false);
            pushHistoryState();
        });
        input.addEventListener('change', () => {
            updatePreview();
            const isStructural = input.tagName === 'SELECT' || input.type === 'checkbox' || input.type === 'radio';
            triggerAutosave(isStructural);
            pushHistoryState();
        });
    });

    // ── UNDO / REDO SYSTEM ──────────────────────────────────────────────────
    let historyStack = [];
    let historyIndex = -1;
    let isUndoingRedoing = false;

    function serializeFormState() {
        const state = {
            inputs: {},
            socials: collectRows('socials'),
            links: collectRows('links'),
            embeds: collectRows('embeds'),
            projects: collectRows('projects'),
            contents: collectRows('contents'),
            blocks: collectRows('blocks'),
            tags: collectRows('tags'),
            badges: collectBadges(),
            characters: collectCharacters(),
            sectionsOrder: $('#sectionsOrderJson')?.value || ''
        };
        
        $$('input[name], select[name], textarea[name]', form).forEach(input => {
            if (input.type === 'file' || input.name === 'csrf_token' || input.name === 'target_user_id' || input.name.endsWith('Json')) return;
            if (input.type === 'checkbox') {
                state.inputs[input.name] = input.checked;
            } else if (input.type === 'radio') {
                if (input.checked) {
                    state.inputs[input.name] = input.value;
                }
            } else {
                state.inputs[input.name] = input.value;
            }
        });
        return JSON.stringify(state);
    }

    function deserializeFormState(stateStr) {
        if (!stateStr) return;
        try {
            isUndoingRedoing = true;
            const state = JSON.parse(stateStr);
            
            // Restore inputs
            Object.entries(state.inputs).forEach(([name, value]) => {
                $$(`[name="${name}"]`, form).forEach(input => {
                    if (input.type === 'checkbox') {
                        input.checked = !!value;
                    } else if (input.type === 'radio') {
                        input.checked = (input.value === value);
                    } else {
                        input.value = value;
                    }
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
            
            // Restore repeaters
            const repeatKeys = ['socials', 'links', 'embeds', 'projects', 'contents', 'blocks', 'tags'];
            repeatKeys.forEach(key => {
                if (repeaters[key] && Array.isArray(state[key])) {
                    repeaters[key].innerHTML = '';
                    state[key].forEach(item => addRow(key, item));
                }
            });
            
            // Restore badges
            if (Array.isArray(state.badges)) {
                $$('#badgeSortList .badge-select-chk').forEach(chk => {
                    const compoundId = chk.dataset.id;
                    chk.checked = state.badges.includes(compoundId);
                    chk.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }
            
            // Restore characters
            if (Array.isArray(state.characters)) {
                selectedCharIds = state.characters;
                $$('#characterPicker input[type="checkbox"]').forEach(chk => {
                    chk.checked = selectedCharIds.includes(Number(chk.value));
                });
                renderCharacterSortList();
                updateCharacterCounter();
            }
            
            // Restore sections order
            if (state.sectionsOrder) {
                const input = document.getElementById('sectionsOrderJson');
                if (input) {
                    input.value = state.sectionsOrder;
                    initSectionsSorting();
                }
            }
            
            updatePreview();
            triggerAutosave(true);
        } catch (e) {
            console.error('Error deserializing state:', e);
        } finally {
            isUndoingRedoing = false;
        }
    }

    function pushHistoryState() {
        if (isUndoingRedoing) return;
        const currentState = serializeFormState();
        if (historyStack[historyIndex] === currentState) return;
        
        historyStack = historyStack.slice(0, historyIndex + 1);
        historyStack.push(currentState);
        if (historyStack.length > 50) {
            historyStack.shift();
        }
        historyIndex = historyStack.length - 1;
        updateUndoRedoButtons();
    }

    function updateUndoRedoButtons() {
        const undoBtn = document.getElementById('undoBtn');
        const redoBtn = document.getElementById('redoBtn');
        if (undoBtn) undoBtn.disabled = (historyIndex <= 0);
        if (redoBtn) redoBtn.disabled = (historyIndex >= historyStack.length - 1);
    }

    const undoBtn = document.getElementById('undoBtn');
    const redoBtn = document.getElementById('redoBtn');
    if (undoBtn) {
        undoBtn.addEventListener('click', () => {
            if (historyIndex > 0) {
                historyIndex--;
                deserializeFormState(historyStack[historyIndex]);
                updateUndoRedoButtons();
            }
        });
    }
    if (redoBtn) {
        redoBtn.addEventListener('click', () => {
            if (historyIndex < historyStack.length - 1) {
                historyIndex++;
                deserializeFormState(historyStack[historyIndex]);
                updateUndoRedoButtons();
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            undoBtn?.click();
        } else if (e.ctrlKey && e.key.toLowerCase() === 'y') {
            e.preventDefault();
            redoBtn?.click();
        }
    });

    // ── ACCORDION EXPAND/COLLAPSE ──────────────────────────────────────────
    function initAccordion() {
        $$('.editor-card-header').forEach(header => {
            header.addEventListener('click', (e) => {
                if (e.target.closest('button, input, select, .profile-custom-select, .profile-custom-color-picker')) return;
                const card = header.closest('.editor-card');
                const isExpanded = card.classList.contains('is-expanded');
                card.classList.toggle('is-expanded', !isExpanded);
            });
        });
        const firstCard = $('.editor-sidebar-scroll .editor-card');
        if (firstCard) firstCard.classList.add('is-expanded');
    }

    // ── GLOBAL SETTINGS SEARCH ──────────────────────────────────────────────
    function initGlobalSearch() {
        const searchInput = document.getElementById('editorSearch');
        const searchClear = document.getElementById('editorSearchClear');
        if (!searchInput) return;

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            if (searchClear) searchClear.style.display = query !== '' ? 'block' : 'none';

            $$('.editor-sidebar-scroll .editor-card').forEach(card => {
                removeSearchHighlights(card);
                
                if (query === '') {
                    card.style.display = '';
                    card.classList.remove('is-expanded');
                    if (card === $('.editor-sidebar-scroll .editor-card')) {
                        card.classList.add('is-expanded');
                    }
                    return;
                }

                const headerH3 = card.querySelector('.editor-card-text h3')?.textContent || '';
                const headerP = card.querySelector('.editor-card-text p')?.textContent || '';
                const labelsText = $$('label', card).map(l => l.textContent).join(' ');
                
                const combined = `${headerH3} ${headerP} ${labelsText}`.toLowerCase();
                const isMatch = combined.includes(query);

                if (isMatch) {
                    card.style.display = '';
                    card.classList.add('is-expanded');
                    applySearchHighlights(card, query);
                } else {
                    card.style.display = 'none';
                    card.classList.remove('is-expanded');
                }
            });
        });

        if (searchClear) {
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
            });
        }
    }

    function applySearchHighlights(container, query) {
        const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, null, false);
        const nodesToReplace = [];
        while (walker.nextNode()) {
            const node = walker.currentNode;
            const parent = node.parentNode;
            if (parent && parent.tagName !== 'SCRIPT' && parent.tagName !== 'STYLE' && parent.tagName !== 'INPUT' && parent.tagName !== 'TEXTAREA' && !parent.closest('select')) {
                const val = node.nodeValue;
                if (val.toLowerCase().includes(query)) {
                    nodesToReplace.push(node);
                }
            }
        }

        nodesToReplace.forEach(node => {
            const parent = node.parentNode;
            if (!parent) return;
            const val = node.nodeValue;
            const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
            const highlighted = val.replace(regex, '<mark class="search-highlight">$1</mark>');
            
            const span = document.createElement('span');
            span.className = 'search-highlight-wrapper';
            span.innerHTML = highlighted;
            parent.replaceChild(span, node);
        });
    }

    function removeSearchHighlights(container) {
        $$('.search-highlight-wrapper', container).forEach(wrapper => {
            const parent = wrapper.parentNode;
            if (parent) {
                const textNode = document.createTextNode(wrapper.textContent);
                parent.replaceChild(textNode, wrapper);
            }
        });
    }

    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // ── PREMIUM THEMES & PRESET SYSTEM ──────────────────────────────────────
    const themes = {
        cyberpunk: {
            accent: '#ff007f', secondary: '#7f00ff', card: '#0a0512', text: '#ffebf5',
            theme: 'dark', layout: 'standard', link_style: 'neon', button_shape: 'sharp',
            socials_style: 'cards', font: 'Poppins', opacity: '85', blur: '15',
            border_opacity: '100', radius: '8', border_width: '2', border_color: '#ff007f',
            ui_shape: 'square-rounded', avatar_shape: 'hexagon', effect: 'none'
        },
        rgb: {
            accent: '#ff0000', secondary: '#00ff00', card: '#080808', text: '#f7f8ff',
            theme: 'dark', layout: 'standard', link_style: 'outline', button_shape: 'rounded',
            socials_style: 'cards', font: 'Minecraft', opacity: '90', blur: '10',
            border_opacity: '100', radius: '12', border_width: '2', border_color: '#00ff00',
            ui_shape: 'rounded', avatar_shape: 'squircle', effect: 'none'
        },
        glass: {
            accent: '#ffffff', secondary: '#ffffff', card: '#ffffff', text: '#ffffff',
            theme: 'dark', layout: 'standard', link_style: 'glass', button_shape: 'pill',
            socials_style: 'glass', font: 'Poppins', opacity: '15', blur: '30',
            border_opacity: '40', radius: '30', border_width: '1', border_color: '#ffffff',
            ui_shape: 'circle', avatar_shape: 'circle', effect: 'none'
        },
        sakura: {
            accent: '#ff758c', secondary: '#ff7eb3', card: '#1f1015', text: '#fff0f5',
            theme: 'dark', layout: 'standard', link_style: 'glass', button_shape: 'pill',
            socials_style: 'cards', font: 'Poppins', opacity: '70', blur: '20',
            border_opacity: '80', radius: '25', border_width: '1', border_color: '#ff758c',
            ui_shape: 'circle', avatar_shape: 'circle', effect: 'sakura'
        },
        anime: {
            accent: '#ff6b6b', secondary: '#feca57', card: '#1a0f0f', text: '#fff5f5',
            theme: 'dark', layout: 'standard', link_style: 'solid', button_shape: 'rounded',
            socials_style: 'cards', font: 'Poppins', opacity: '75', blur: '15',
            border_opacity: '80', radius: '16', border_width: '2', border_color: '#ff6b6b',
            ui_shape: 'soft', avatar_shape: 'circle', effect: 'none'
        },
        neon: {
            accent: '#00f0ff', secondary: '#ff007f', card: '#03030d', text: '#f0f9ff',
            theme: 'dark', layout: 'standard', link_style: 'neon', button_shape: 'pill',
            socials_style: 'cards', font: 'Poppins', opacity: '80', blur: '25',
            border_opacity: '100', radius: '30', border_width: '1.5', border_color: '#00f0ff',
            ui_shape: 'circle', avatar_shape: 'circle', effect: 'neon_lines'
        },
        discord: {
            accent: '#5865f2', secondary: '#57f287', card: '#2f3136', text: '#ffffff',
            theme: 'dark', layout: 'standard', link_style: 'solid', button_shape: 'rounded',
            socials_style: 'cards', font: 'Poppins', opacity: '95', blur: '0',
            border_opacity: '0', radius: '8', border_width: '0', border_color: '#5865f2',
            ui_shape: 'soft', avatar_shape: 'circle', effect: 'none'
        },
        minimal: {
            accent: '#000000', secondary: '#888888', card: '#ffffff', text: '#000000',
            theme: 'light', layout: 'standard', link_style: 'outline', button_shape: 'sharp',
            socials_style: 'cards', font: 'Poppins', opacity: '90', blur: '0',
            border_opacity: '100', radius: '0', border_width: '1', border_color: '#000000',
            ui_shape: 'square', avatar_shape: 'square', effect: 'none'
        },
        dark_premium: {
            accent: '#0f5bff', secondary: '#c9d9ff', card: '#030509', text: '#ffffff',
            theme: 'dark', layout: 'standard', link_style: 'glass', button_shape: 'pill',
            socials_style: 'cards', font: 'Poppins', opacity: '80', blur: '20',
            border_opacity: '40', radius: '24', border_width: '1', border_color: '#0f5bff',
            ui_shape: 'circle', avatar_shape: 'circle', effect: 'none'
        }
    };

    $$('.theme-preset-card').forEach(card => {
        card.addEventListener('click', () => {
            const themeKey = card.dataset.themePreset;
            const themeObj = themes[themeKey];
            if (!themeObj) return;

            $$('.theme-preset-card').forEach(c => c.classList.remove('is-active'));
            card.classList.add('is-active');

            if (accentInput) accentInput.value = themeObj.accent;
            if (secondaryColorInput) secondaryColorInput.value = themeObj.secondary;
            if (cardColorInput) cardColorInput.value = themeObj.card;
            if (textColorInput) textColorInput.value = themeObj.text;
            if (themeInput) themeInput.value = themeObj.theme;
            if (layoutInput) layoutInput.value = themeObj.layout;
            if (linkStyleInput) linkStyleInput.value = themeObj.link_style;
            if (buttonShapeInput) buttonShapeInput.value = themeObj.button_shape;
            if (socialsStyleInput) socialsStyleInput.value = themeObj.socials_style;
            if (fontInput) fontInput.value = themeObj.font;
            if (cardOpacityInput) cardOpacityInput.value = themeObj.opacity;
            if (cardBlurInput) cardBlurInput.value = themeObj.blur;
            if (borderOpacityInput) borderOpacityInput.value = themeObj.border_opacity;
            if (borderRadiusInput) borderRadiusInput.value = themeObj.radius;
            if (borderWidthInput) borderWidthInput.value = themeObj.border_width;
            if (borderColorInput) borderColorInput.value = themeObj.border_color;
            if (uiShapeInput) uiShapeInput.value = themeObj.ui_shape;
            if (avatarShapeInput) avatarShapeInput.value = themeObj.avatar_shape;
            if (profileEffectInput) profileEffectInput.value = themeObj.effect;

            [accentInput, secondaryColorInput, cardColorInput, textColorInput, themeInput, layoutInput, linkStyleInput, buttonShapeInput, socialsStyleInput, fontInput, cardOpacityInput, cardBlurInput, borderOpacityInput, borderRadiusInput, borderWidthInput, borderColorInput, uiShapeInput, avatarShapeInput, profileEffectInput].filter(Boolean).forEach(input => {
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            updatePreview();
            triggerAutosave(true);
            pushHistoryState();

            if (typeof window.profileToast === 'function') {
                const name = card.querySelector('.theme-preset-name')?.textContent || '';
                window.profileToast(isEnglish ? `Theme '${name}' applied!` : `Tema '${name}' applicato!`);
            }
        });
    });

    // ── VIEWPORT AND MOBILE SWITCHERS ───────────────────────────────────────
    function initViewportSwitcher() {
        $$('.btn-viewport').forEach(btn => {
            btn.addEventListener('click', () => {
                const viewport = btn.dataset.viewport;
                $$('.btn-viewport').forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');

                const frame = document.getElementById('previewDeviceFrame');
                if (frame) {
                    frame.className = `device-frame ${viewport}`;
                }
            });
        });

        const floatingBtn = document.getElementById('floatingPreviewBtn');
        const sidebar = $('.editor-sidebar');
        if (floatingBtn && sidebar) {
            floatingBtn.addEventListener('click', () => {
                const isOpen = sidebar.classList.toggle('preview-open');
                floatingBtn.innerHTML = isOpen 
                    ? '<i class="fas fa-edit"></i>' 
                    : '<i class="fas fa-eye"></i>';
            });
        }
    }

    // ── UI STYLE & PALETTE PRESETS ──────────────────────────────────────────
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
            triggerAutosave(true);
            pushHistoryState();
            if (typeof window.profileToast === 'function') {
                window.profileToast(isEnglish ? 'UI Style preset applied.' : 'UI Style preset applicato.');
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
            triggerAutosave(true);
            pushHistoryState();
            if (typeof window.profileToast === 'function') {
                window.profileToast(isEnglish ? 'Palette preset applied.' : 'Palette preset applicata.');
            }
        });
    });

    const resetDesignBtn = $('#resetDesignBtn');
    if (resetDesignBtn) {
        resetDesignBtn.addEventListener('click', () => {
            const msg = isEnglish 
                ? 'Are you sure you want to reset all design settings to default values?'
                : 'Sei sicuro di voler ripristinare tutte le impostazioni del design ai valori di default?';
            
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
                accentInput, secondaryColorInput, themeInput, layoutInput, cardColorInput,
                textColorInput, linkStyleInput, buttonShapeInput, socialsStyleInput, fontInput,
                cardOpacityInput, cardBlurInput, borderOpacityInput, borderRadiusInput,
                borderWidthInput, borderColorInput
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
            triggerAutosave(true);
            pushHistoryState();

            if (typeof window.profileToast === 'function') {
                window.profileToast(isEnglish ? 'Design settings reset.' : 'Impostazioni di design ripristinate.');
            }
        });
    }

    if (tiltPresetInput) {
        tiltPresetInput.addEventListener('change', () => {
            const val = tiltPresetInput.value;
            if (val === 'off') {
                if (tiltEnabledInput) tiltEnabledInput.checked = false;
                if (tiltCustomControls) tiltCustomControls.style.display = 'none';
            } else {
                if (tiltEnabledInput) tiltEnabledInput.checked = true;
                if (val === 'custom') {
                    if (tiltCustomControls) tiltCustomControls.style.display = 'grid';
                } else {
                    if (tiltCustomControls) tiltCustomControls.style.display = 'none';
                    if (val === 'super_soft') {
                        tiltMaxInput.value = 3;
                        tiltGlareInput.value = 0.05;
                        tiltZoomInput.value = 1.005;
                        tiltSpeedInput.value = 1200;
                    } else if (val === 'soft') {
                        tiltMaxInput.value = 8;
                        tiltGlareInput.value = 0.12;
                        tiltZoomInput.value = 1.015;
                        tiltSpeedInput.value = 900;
                    } else if (val === 'medium') {
                        tiltMaxInput.value = 15;
                        tiltGlareInput.value = 0.25;
                        tiltZoomInput.value = 1.05;
                        tiltSpeedInput.value = 600;
                    } else if (val === 'strong') {
                        tiltMaxInput.value = 25;
                        tiltGlareInput.value = 0.40;
                        tiltZoomInput.value = 1.08;
                        tiltSpeedInput.value = 400;
                    } else if (val === 'extreme') {
                        tiltMaxInput.value = 35;
                        tiltGlareInput.value = 0.60;
                        tiltZoomInput.value = 1.12;
                        tiltSpeedInput.value = 200;
                    }
                    if (tiltMaxVal) tiltMaxVal.textContent = tiltMaxInput.value;
                    if (tiltGlareVal) tiltGlareVal.textContent = tiltGlareInput.value;
                    if (tiltZoomVal) tiltZoomVal.textContent = tiltZoomInput.value;
                    if (tiltSpeedVal) tiltSpeedVal.textContent = tiltSpeedInput.value;
                }
            }
            updatePreview();
            triggerAutosave(true);
        });
    }

    if (tiltEnabledInput) {
        tiltEnabledInput.addEventListener('change', () => {
            if (!tiltEnabledInput.checked) {
                if (tiltPresetInput) tiltPresetInput.value = 'off';
                if (tiltCustomControls) tiltCustomControls.style.display = 'none';
            } else {
                if (tiltPresetInput) {
                    tiltPresetInput.value = 'medium';
                    tiltPresetInput.dispatchEvent(new Event('change'));
                }
            }
            updatePreview();
            triggerAutosave(true);
        });
    }

    if (cornerStyleInput) {
        cornerStyleInput.addEventListener('change', () => {
            if (cornerStyleCustomContainer) {
                cornerStyleCustomContainer.style.display = cornerStyleInput.value === 'custom' ? 'block' : 'none';
            }
            updatePreview();
            triggerAutosave(true);
        });
    }

    if (borderStyleInput) {
        borderStyleInput.addEventListener('change', () => {
            updatePreview();
            triggerAutosave(true);
        });
    }

    // ── MEDIA UPLOAD PREVIEWS ───────────────────────────────────────────────
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
            window.profileToast(isEnglish ? 'Unsupported background format.' : 'Formato sfondo non supportato.');
            URL.revokeObjectURL(url);
            return;
        }

        media.className = 'bio-background__media';
        background.prepend(media);
        window.profileToast(isEnglish ? 'Background preview updated.' : 'Anteprima sfondo aggiornata.');
    }

    function previewMusicFile(input) {
        const file = input.files && input.files[0];
        if (!file) return;
        const isMp3 = file.type === 'audio/mpeg' || file.name.toLowerCase().endsWith('.mp3');
        if (!isMp3) {
            window.profileToast(isEnglish ? 'Use only MP3 files.' : 'Usa solo file MP3.');
            input.value = '';
            return;
        }
        if (file.size > 12 * 1024 * 1024) {
            window.profileToast(isEnglish ? 'MP3 too heavy. Max 12MB.' : 'MP3 troppo pesante. Max 12MB.');
            input.value = '';
            return;
        }
        const title = $('#musicTitleInput');
        if (title && !title.value.trim()) {
            title.value = file.name.replace(/\.mp3$/i, '');
        }
        window.profileToast(isEnglish ? 'MP3 selected. Save to apply.' : 'MP3 selezionato. Salva per applicarlo.');
    }

    avatarInput.addEventListener('change', () => previewAvatarFile(avatarInput, $('#previewAvatar')));
    bannerInput.addEventListener('change', () => previewBackgroundFile(bannerInput));
    if (musicFileInput) musicFileInput.addEventListener('change', () => previewMusicFile(musicFileInput));

    // ── LIVE ALIAS URL CHECKER ──────────────────────────────────────────────
    const aliasInput = document.getElementById('customAliasInput');
    const aliasIcon = document.getElementById('aliasValidationIcon');
    const aliasMsg = document.getElementById('aliasValidationMessage');

    if (aliasInput && aliasIcon && aliasMsg) {
        let debounceTimer;

        const checkAlias = async () => {
            const val = aliasInput.value.trim();
            if (val === '') {
                aliasIcon.innerHTML = '';
                aliasMsg.textContent = isEnglish 
                    ? 'Leave empty to disable. Allows accessing your profile via cripsum.com/youralias'
                    : 'Lascia vuoto per disattivare. Permette di accedere al tuo profilo tramite cripsum.com/tuoalias';
                aliasMsg.style.color = '';
                return;
            }

            if (!/^[a-zA-Z0-9_-]{3,30}$/.test(val)) {
                aliasIcon.innerHTML = '<i class="fas fa-times-circle" style="color: #ef4444;"></i>';
                aliasMsg.textContent = isEnglish
                    ? 'Alias must contain between 3 and 30 characters (letters, numbers, dashes, underscores).'
                    : "L'alias deve contenere da 3 a 30 caratteri (lettere, numeri, trattini, underscore).";
                aliasMsg.style.color = '#ef4444';
                return;
            }

            try {
                const res = await fetch(`/api/check_alias.php?alias=${encodeURIComponent(val)}&target_user_id=${targetUserId}`);
                const data = await res.json();
                if (data.available) {
                    aliasIcon.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i>';
                    aliasMsg.textContent = isEnglish ? 'Alias available!' : 'Alias disponibile!';
                    aliasMsg.style.color = '#10b981';
                } else {
                    aliasIcon.innerHTML = '<i class="fas fa-times-circle" style="color: #ef4444;"></i>';
                    aliasMsg.textContent = data.message || (isEnglish ? 'Alias not available.' : 'Alias non disponibile.');
                    aliasMsg.style.color = '#ef4444';
                }
            } catch (err) {
                aliasIcon.innerHTML = '';
                aliasMsg.textContent = isEnglish ? 'Error checking availability.' : 'Errore nel controllo disponibilità.';
                aliasMsg.style.color = '#ef4444';
            }
        };

        aliasInput.addEventListener('input', () => {
            aliasIcon.innerHTML = '<i class="fas fa-spinner fa-spin" style="color: var(--accent);"></i>';
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(checkAlias, 400);
        });
    }

    // ── MAIN SUBMIT HANDLER ────────────────────────────────────────────────
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        $('#socialsJson').value = JSON.stringify(collectRows('socials'));
        $('#linksJson').value = JSON.stringify(collectRows('links'));
        $('#embedsJson').value = JSON.stringify(collectRows('embeds'));
        $('#projectsJson').value = JSON.stringify(collectRows('projects'));
        $('#contentsJson').value = JSON.stringify(collectRows('contents'));
        $('#blocksJson').value = JSON.stringify(collectRows('blocks'));
        $('#badgesJson').value = JSON.stringify(collectBadges());
        $('#profileTagsJson').value = JSON.stringify(collectRows('tags'));

        const button = $('#saveBtn') || $('#saveProfileButton');
        const overlay = document.getElementById('editorLoadingOverlay');
        const overlayText = document.getElementById('editorLoadingText');

        // Check if files are selected for upload
        const hasFiles = [
            document.getElementById('avatarInput'),
            document.getElementById('bannerInput'),
            document.getElementById('musicFileInput')
        ].some(input => input && input.files && input.files.length > 0);

        if (button) {
            button.disabled = true;
            button.innerHTML = hasFiles
                ? `<i class="fas fa-spinner fa-spin"></i> ${isEnglish ? 'Uploading...' : 'Caricamento...'}`
                : `<i class="fas fa-spinner fa-spin"></i> ${isEnglish ? 'Saving...' : 'Salvataggio...'}`;
        }

        if (overlay) {
            if (overlayText) {
                overlayText.textContent = hasFiles
                    ? (isEnglish ? 'Uploading media files...' : 'Caricamento file multimediali...')
                    : (isEnglish ? 'Saving profile...' : 'Salvataggio profilo...');
            }
            overlay.classList.add('is-active');
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || (isEnglish ? 'Error saving.' : 'Errore salvataggio.'));
            window.profileToast(data.message || (isEnglish ? 'Profile saved.' : 'Profilo salvato.'));
            
            setTimeout(() => {
                window.location.href = data.profile_url || '/profile.php';
            }, 650);
        } catch (error) {
            window.profileToast(error.message || (isEnglish ? 'Error saving.' : 'Errore salvataggio.'));
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = `<i class="fas fa-save"></i> ${isEnglish ? 'Save' : 'Salva'}`;
            }
            if (overlay) {
                overlay.classList.remove('is-active');
            }
        }
    });

    // ── CHARACTER INVENTORY SORTING ──────────────────────────────────────────
    let selectedCharIds = [];
    const initialDataEl = document.getElementById('initialCharactersData');
    if (initialDataEl) {
        try {
            selectedCharIds = JSON.parse(initialDataEl.textContent || '[]');
        } catch (e) {
            console.error('Error parsing initialCharactersData:', e);
        }
    }

    const initialChecked = Array.from(document.querySelectorAll('#characterPicker input[type="checkbox"]:checked'))
        .map(input => Number(input.value));
    initialChecked.forEach(id => {
        if (!selectedCharIds.includes(id)) {
            selectedCharIds.push(id);
        }
    });
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
                    ${isEnglish ? 'No characters selected. Check boxes above to add.' : 'Nessun personaggio selezionato. Spunta le caselle sopra per aggiungerne.'}
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
                    <button type="button" class="profile-move-up-char" title="${isEnglish ? 'Move up' : 'Sposta su'}" ${index === 0 ? 'disabled' : ''}><i class="fas fa-arrow-up"></i></button>
                    <button type="button" class="profile-move-down-char" title="${isEnglish ? 'Move down' : 'Sposta giù'}" ${index === selectedCharIds.length - 1 ? 'disabled' : ''}><i class="fas fa-arrow-down"></i></button>
                </div>
            `;

            card.querySelector('.profile-move-up-char').addEventListener('click', (e) => {
                e.preventDefault();
                if (index > 0) {
                    const temp = selectedCharIds[index];
                    selectedCharIds[index] = selectedCharIds[index - 1];
                    selectedCharIds[index - 1] = temp;
                    renderCharacterSortList();
                    updatePreview();
                    triggerAutosave(true);
                }
            });

            card.querySelector('.profile-move-down-char').addEventListener('click', (e) => {
                e.preventDefault();
                if (index < selectedCharIds.length - 1) {
                    const temp = selectedCharIds[index];
                    selectedCharIds[index] = selectedCharIds[index + 1];
                    selectedCharIds[index + 1] = temp;
                    renderCharacterSortList();
                    updatePreview();
                    triggerAutosave(true);
                }
            });

            sortListEl.appendChild(card);
        });

        if (typeof Sortable !== 'undefined') {
            Sortable.create(sortListEl, {
                animation: 200,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function() {
                    const newOrder = Array.from(sortListEl.children).map(el => Number(el.dataset.charId)).filter(Boolean);
                    selectedCharIds = newOrder;
                    updateCharacterCounter();
                    updatePreview();
                    triggerAutosave(true);
                    pushHistoryState();
                }
            });
        }
    }

    function updateCharacterCounter() {
        const hint = document.querySelector('.profile-character-hint');
        if (hint) {
            const n = selectedCharIds.length;
            hint.innerHTML = `<i class="fas fa-circle-info"></i> ${n}/12 ${isEnglish ? 'selected' : 'selezionati'}.`;
        }
    }

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
                            window.profileToast(isEnglish ? 'You can select up to 12 characters.' : 'Puoi selezionare massimo 12 personaggi.');
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
                updatePreview();
                triggerAutosave(true);
            }
        });
    }

    renderCharacterSortList();
    updateCharacterCounter();

    // ── PROFILE SECTIONS ORDERING ─────────────────────────────────────────
    const sectionInfo = isEnglish ? {
        links: { name: 'Links', icon: 'fas fa-link' },
        embeds: { name: 'Embeds (Spotify/YouTube)', icon: 'fas fa-share-square' },
        stats: { name: 'Statistics', icon: 'fas fa-chart-simple' },
        projects: { name: 'Projects', icon: 'fas fa-cubes' },
        blocks: { name: 'Custom Blocks', icon: 'fas fa-wand-magic-sparkles' },
        contents: { name: 'Edits & Content', icon: 'fas fa-play' },
        characters: { name: 'Characters', icon: 'fas fa-user-astronaut' },
        badges: { name: 'Badges', icon: 'fas fa-trophy' },
        activity: { name: 'Recent Activity', icon: 'fas fa-clock' }
    } : {
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
                        <button type="button" class="profile-move-up-sec" title="${isEnglish ? 'Move up' : 'Sposta su'}" ${index === 0 ? 'disabled' : ''}><i class="fas fa-arrow-up"></i></button>
                        <button type="button" class="profile-move-down-sec" title="${isEnglish ? 'Move down' : 'Sposta giù'}" ${index === currentOrder.length - 1 ? 'disabled' : ''}><i class="fas fa-arrow-down"></i></button>
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
                        updatePreview();
                        triggerAutosave(true);
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
                        updatePreview();
                        triggerAutosave(true);
                    }
                });

                sectionsSortList.appendChild(item);
            });
        }

        function saveSectionsOrder() {
            sectionsOrderInput.value = currentOrder.join(',');
        }

        renderSectionsList();

        if (typeof Sortable !== 'undefined') {
            Sortable.create(sectionsSortList, {
                animation: 200,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function() {
                    const newOrder = Array.from(sectionsSortList.children).map(el => el.dataset.secKey).filter(Boolean);
                    currentOrder = newOrder;
                    saveSectionsOrder();
                    updatePreview();
                    triggerAutosave(true);
                    pushHistoryState();
                }
            });
        }
    }

    initSectionsSorting();

    // ── BADGES SORTING ─────────────────────────────────────────────────────
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
                        <strong>${isEnglish ? 'No badges unlocked or assigned' : 'Nessun badge sbloccato o assegnato'}</strong>
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
                        <button type="button" class="profile-move-up profile-move-up-badge" title="${isEnglish ? 'Move up' : 'Sposta su'}" ${index === 0 ? 'disabled' : ''}><i class="fas fa-arrow-up"></i></button>
                        <button type="button" class="profile-move-down profile-move-down-badge" title="${isEnglish ? 'Move down' : 'Sposta giù'}" ${index === badges.length - 1 ? 'disabled' : ''}><i class="fas fa-arrow-down"></i></button>
                    </div>
                `;

                item.querySelector('.badge-select-chk').addEventListener('change', (e) => {
                    badge.selected = e.target.checked ? 1 : 0;
                    saveBadgesState();
                    renderBadgesList();
                    updatePreview();
                    triggerAutosave(true);
                });

                item.querySelector('.profile-move-up-badge').addEventListener('click', (e) => {
                    e.preventDefault();
                    if (index > 0) {
                        const temp = badges[index];
                        badges[index] = badges[index - 1];
                        badges[index - 1] = temp;
                        saveBadgesState();
                        renderBadgesList();
                        updatePreview();
                        triggerAutosave(true);
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
                        updatePreview();
                        triggerAutosave(true);
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

        badges.sort((a, b) => {
            const selA = Number(a.selected) === 1 ? 1 : 0;
            const selB = Number(b.selected) === 1 ? 1 : 0;
            if (selA !== selB) return selB - selA;
            return Number(a.sort_order) - Number(b.sort_order);
        });

        renderBadgesList();
        saveBadgesState();

        if (typeof Sortable !== 'undefined') {
            Sortable.create(badgeSortList, {
                animation: 200,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function() {
                    const newOrder = Array.from(badgeSortList.children).map(el => el.querySelector('.badge-select-chk')?.dataset.id).filter(Boolean);
                    badges.sort((a, b) => {
                        const idA = a.badge_source + '_' + a.id;
                        const idB = b.badge_source + '_' + b.id;
                        return newOrder.indexOf(idA) - newOrder.indexOf(idB);
                    });
                    saveBadgesState();
                    updatePreview();
                    triggerAutosave(true);
                    pushHistoryState();
                }
            });
        }
    }

    initBadgesSorting();

    if (typeof Sortable !== 'undefined') {
        Object.entries(repeaters).forEach(([type, container]) => {
            if (container) {
                Sortable.create(container, {
                    animation: 200,
                    handle: '.profile-row-handle',
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    onEnd: function() {
                        updatePreview();
                        triggerAutosave(true);
                        pushHistoryState();
                    }
                });
            }
        });
    }

    // ── CUSTOM PRESETS GALLERY (LOAD / DUPLICATE / DELETE / RENAME) ──────────
    const presetsListContainer = $('#presetsListContainer');
    const saveNewPresetBtn = $('#saveNewPresetBtn');

    async function loadPresets() {
        if (!presetsListContainer) return;
        presetsListContainer.innerHTML = `<div class="bio-empty-state"><i class="fas fa-spinner fa-spin"></i><strong>${isEnglish ? 'Loading presets...' : 'Caricamento preset...'}</strong></div>`;
        try {
            const res = await fetch(`/api/manage_presets.php?action=list&target_user_id=${targetUserId}`);
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.message || (isEnglish ? 'Error loading presets.' : 'Errore caricamento preset.'));
            
            if (data.presets.length === 0) {
                presetsListContainer.innerHTML = `
                    <div class="bio-empty-state">
                        <i class="fas fa-magic"></i>
                        <strong>${isEnglish ? 'No presets saved' : 'Nessun preset salvato'}</strong>
                        <p>${isEnglish ? 'Save your current design setup as a preset to restore it later.' : 'Puoi salvare la tua configurazione corrente come preset per poterla ripristinare in futuro.'}</p>
                    </div>`;
                return;
            }

            presetsListContainer.innerHTML = '';
            const grid = document.createElement('div');
            grid.className = 'profile-presets-grid-container';
            grid.style.display = 'grid';
            grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(280px, 1fr))';
            grid.style.gap = '1.25rem';
            grid.style.marginTop = '1rem';

            data.presets.forEach(preset => {
                const card = document.createElement('div');
                card.className = 'bio-card preset-card';
                card.style.padding = '1.25rem';
                card.style.display = 'flex';
                card.style.flexDirection = 'column';
                card.style.gap = '0.75rem';
                card.style.position = 'relative';
                card.style.border = '1px solid rgba(255, 255, 255, 0.08)';
                card.style.borderRadius = '16px';
                card.style.background = 'rgba(255, 255, 255, 0.02)';
                card.style.transition = 'all 0.2s';

                let accentColor = '#0f5bff';
                let secondaryColor = '#8b5cf6';
                try {
                    const parsed = JSON.parse(preset.preset_data);
                    if (parsed.accent_color) accentColor = parsed.accent_color;
                    if (parsed.profile_secondary_color) secondaryColor = parsed.profile_secondary_color;
                } catch(e){}

                card.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <strong style="font-size: 1.1rem; color: var(--text);">${escapeAttr(preset.nome)}</strong>
                        <div style="display: flex; gap: 6px;">
                            <span style="width: 14px; height: 14px; border-radius: 50%; background: ${accentColor}; border: 1px solid rgba(255, 255, 255, 0.2);"></span>
                            <span style="width: 14px; height: 14px; border-radius: 50%; background: ${secondaryColor}; border: 1px solid rgba(255, 255, 255, 0.2);"></span>
                        </div>
                    </div>
                    <small style="color: var(--muted); font-size: 0.8rem;">${isEnglish ? 'Created on:' : 'Creato il:'} ${escapeAttr(preset.created_at)}</small>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 0.5rem;">
                        <button type="button" class="bio-button load-preset-btn" data-id="${preset.id}" style="padding: 0.4rem; font-size: 0.85rem;"><i class="fas fa-upload"></i> ${isEnglish ? 'Load' : 'Carica'}</button>
                        <button type="button" class="bio-button duplicate-preset-btn" data-id="${preset.id}" style="padding: 0.4rem; font-size: 0.85rem; background: rgba(255,255,255,0.05); color: #fff;"><i class="fas fa-copy"></i> ${isEnglish ? 'Copy' : 'Duplica'}</button>
                        <button type="button" class="bio-button rename-preset-btn" data-id="${preset.id}" data-name="${escapeAttr(preset.nome)}" style="padding: 0.4rem; font-size: 0.85rem; background: rgba(255,255,255,0.05); color: #fff;"><i class="fas fa-edit"></i> ${isEnglish ? 'Rename' : 'Rinomina'}</button>
                        <button type="button" class="bio-button delete-preset-btn" data-id="${preset.id}" style="padding: 0.4rem; font-size: 0.85rem; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.25);"><i class="fas fa-trash"></i> ${isEnglish ? 'Delete' : 'Elimina'}</button>
                    </div>`;
                grid.appendChild(card);
            });
            presetsListContainer.innerHTML = '';
            presetsListContainer.appendChild(grid);

            $$('.load-preset-btn', presetsListContainer).forEach(btn => {
                btn.addEventListener('click', () => handleLoadPreset(btn.dataset.id));
            });
            $$('.duplicate-preset-btn', presetsListContainer).forEach(btn => {
                btn.addEventListener('click', () => handleDuplicatePreset(btn.dataset.id));
            });
            $$('.rename-preset-btn', presetsListContainer).forEach(btn => {
                btn.addEventListener('click', () => handleRenamePreset(btn.dataset.id, btn.dataset.name));
            });
            $$('.delete-preset-btn', presetsListContainer).forEach(btn => {
                btn.addEventListener('click', () => handleDeletePreset(btn.dataset.id));
            });

        } catch (error) {
            presetsListContainer.innerHTML = `<div class="bio-card is-error" style="padding: 1rem;"><i class="fas fa-triangle-exclamation"></i><span>${escapeAttr(error.message)}</span></div>`;
        }
    }

    if (saveNewPresetBtn) {
        saveNewPresetBtn.addEventListener('click', async () => {
            const promptMsg = isEnglish ? 'Enter a name for this preset:' : 'Inserisci un nome per questo preset:';
            const name = prompt(promptMsg);
            if (name === null) return;
            const trimmedName = name.trim();
            if (trimmedName === '') {
                window.profileToast(isEnglish ? 'Preset name cannot be empty.' : 'Il nome del preset non può essere vuoto.');
                return;
            }

            $('#socialsJson').value = JSON.stringify(collectRows('socials'));
            $('#linksJson').value = JSON.stringify(collectRows('links'));
            $('#embedsJson').value = JSON.stringify(collectRows('embeds'));
            $('#projectsJson').value = JSON.stringify(collectRows('projects'));
            $('#contentsJson').value = JSON.stringify(collectRows('contents'));
            $('#blocksJson').value = JSON.stringify(collectRows('blocks'));
            $('#badgesJson').value = JSON.stringify(collectBadges());
            $('#charactersJson').value = JSON.stringify(collectCharacters());
            $('#profileTagsJson').value = JSON.stringify(collectRows('tags'));

            const formData = new FormData(form);
            formData.append('preset_name', trimmedName);
            formData.append('target_user_id', targetUserId);

            try {
                const res = await fetch('/api/manage_presets.php?action=save', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error(data.message || (isEnglish ? 'Error saving preset.' : 'Errore salvataggio preset.'));
                window.profileToast(data.message || (isEnglish ? 'Preset saved successfully!' : 'Preset salvato con successo!'));
                loadPresets();
            } catch(err) {
                window.profileToast(err.message || (isEnglish ? 'Error saving preset.' : 'Errore salvataggio preset.'));
            }
        });
    }

    async function handleLoadPreset(id) {
        const confirmMsg = isEnglish 
            ? 'Are you sure you want to load this preset? Your unsaved setup will be overwritten.'
            : 'Sei sicuro di voler caricare questo preset? La configurazione corrente non salvata andrà persa.';
        if (!confirm(confirmMsg)) return;
        try {
            const formData = new FormData();
            formData.append('preset_id', id);
            formData.append('target_user_id', targetUserId);
            const res = await fetch('/api/manage_presets.php?action=load', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.message || (isEnglish ? 'Error loading preset.' : 'Errore caricamento preset.'));
            window.profileToast(data.message || (isEnglish ? 'Preset loaded! Reloading...' : 'Preset caricato! Ricaricamento pagina...'));
            setTimeout(() => window.location.reload(), 1000);
        } catch(err) {
            window.profileToast(err.message || (isEnglish ? 'Error loading preset.' : 'Errore caricamento preset.'));
        }
    }

    async function handleDuplicatePreset(id) {
        try {
            const formData = new FormData();
            formData.append('preset_id', id);
            formData.append('target_user_id', targetUserId);
            const res = await fetch('/api/manage_presets.php?action=duplicate', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.message || (isEnglish ? 'Error duplicating preset.' : 'Errore duplicazione preset.'));
            window.profileToast(data.message || (isEnglish ? 'Preset duplicated!' : 'Preset duplicato!'));
            loadPresets();
        } catch(err) {
            window.profileToast(err.message || (isEnglish ? 'Error duplicating preset.' : 'Errore duplicazione preset.'));
        }
    }

    async function handleRenamePreset(id, currentName) {
        const promptMsg = isEnglish ? 'Enter a new name for the preset:' : 'Inserisci il nuovo nome per il preset:';
        const newName = prompt(promptMsg, currentName);
        if (newName === null) return;
        const trimmed = newName.trim();
        if (trimmed === '') {
            window.profileToast(isEnglish ? 'Preset name cannot be empty.' : 'Il nome del preset non può essere vuoto.');
            return;
        }
        try {
            const formData = new FormData();
            formData.append('preset_id', id);
            formData.append('preset_name', trimmed);
            formData.append('target_user_id', targetUserId);
            const res = await fetch('/api/manage_presets.php?action=rename', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.message || (isEnglish ? 'Error renaming preset.' : 'Errore rinomina preset.'));
            window.profileToast(data.message || (isEnglish ? 'Preset renamed!' : 'Preset rinominato!'));
            loadPresets();
        } catch(err) {
            window.profileToast(err.message || (isEnglish ? 'Error renaming preset.' : 'Errore rinomina preset.'));
        }
    }

    async function handleDeletePreset(id) {
        const confirmMsg = isEnglish 
            ? 'Are you sure you want to delete this preset? This action cannot be undone.'
            : 'Sei sicuro di voler eliminare questo preset? Questa operazione non può essere annullata.';
        if (!confirm(confirmMsg)) return;
        try {
            const formData = new FormData();
            formData.append('preset_id', id);
            formData.append('target_user_id', targetUserId);
            const res = await fetch('/api/manage_presets.php?action=delete', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.message || (isEnglish ? 'Error deleting preset.' : 'Errore eliminazione preset.'));
            window.profileToast(data.message || (isEnglish ? 'Preset deleted!' : 'Preset eliminato!'));
            loadPresets();
        } catch(err) {
            window.profileToast(err.message || (isEnglish ? 'Error deleting preset.' : 'Errore eliminazione preset.'));
        }
    }

    loadPresets();

    // ── ONBOARDING WALKTHROUGH TOUR ──────────────────────────────────────────
    function launchOnboardingTour() {
        if (localStorage.getItem('cripsum_visual_builder_tour_seen')) return;

        const steps = isEnglish ? [
            {
                title: "Welcome to the Visual Builder!",
                desc: "We completely redesigned the editor to provide a premium, Figma-like design experience. Let's see how it works!"
            },
            {
                title: "Global settings search",
                desc: "Type 'avatar', 'color', or any setting name in the search bar to locate specific fields instantly without scrolling."
            },
            {
                title: "Interactive Live Preview",
                desc: "The preview frame on the right displays exactly what your profile looks like in real time! You can switch between desktop and mobile viewport sizes."
            },
            {
                title: "Autosave Drafts",
                desc: "The editor saves a session draft automatically while you work. When you're ready to publish, click 'Save' in the top right header."
            }
        ] : [
            {
                title: "Benvenuto nel Visual Builder!",
                desc: "Abbiamo completamente riprogettato l'editor per offrirti un'esperienza di design fluida e premium, simile a Figma. Vediamo come funziona!"
            },
            {
                title: "Ricerca globale impostazioni",
                desc: "Scrivi 'avatar', 'colori' o qualsiasi impostazione nella barra di ricerca per trovare all'istante i campi che desideri modificare."
            },
            {
                title: "Anteprima Live Interattiva",
                desc: "Il pannello destro mostra in tempo reale l'anteprima esatta del tuo profilo. Puoi alternare tra la visualizzazione Desktop e Mobile."
            },
            {
                title: "Salvataggio automatico bozza",
                desc: "Mentre modifichi, il builder salva una bozza temporanea. Quando sei soddisfatto delle modifiche, clicca 'Salva' in alto a destra per pubblicarle."
            }
        ];

        let currentStep = 0;

        const overlay = document.createElement('div');
        overlay.className = 'onboarding-overlay';

        const card = document.createElement('div');
        card.className = 'onboarding-card';

        function renderStep() {
            card.innerHTML = `
                <h3>${steps[currentStep].title}</h3>
                <p>${steps[currentStep].desc}</p>
                <div class="onboarding-nav">
                    <button class="editor-btn" id="skipTourBtn">${isEnglish ? 'Skip' : 'Salta'}</button>
                    <span class="onboarding-steps">${currentStep + 1} / ${steps.length}</span>
                    <button class="editor-btn editor-btn-primary" id="nextTourBtn">
                        ${currentStep === steps.length - 1 ? (isEnglish ? 'Done' : 'Fine') : (isEnglish ? 'Next' : 'Avanti')}
                    </button>
                </div>
            `;

            card.querySelector('#skipTourBtn').addEventListener('click', () => {
                localStorage.setItem('cripsum_visual_builder_tour_seen', 'true');
                overlay.remove();
            });

            card.querySelector('#nextTourBtn').addEventListener('click', () => {
                if (currentStep < steps.length - 1) {
                    currentStep++;
                    renderStep();
                } else {
                    localStorage.setItem('cripsum_visual_builder_tour_seen', 'true');
                    overlay.remove();
                }
            });
        }

        renderStep();
        overlay.appendChild(card);
        document.body.appendChild(overlay);
    }

    // ── INITIALIZATION ONLOAD ──────────────────────────────────────────────
    const brandSpan = $('.editor-brand-title span');
    if (brandSpan) {
        const statusSpan = document.createElement('span');
        statusSpan.id = 'autosaveStatus';
        statusSpan.style.marginLeft = '8px';
        statusSpan.style.textTransform = 'none';
        statusSpan.style.letterSpacing = 'normal';
        statusSpan.style.fontSize = '0.7rem';
        statusSpan.style.color = 'rgba(255, 255, 255, 0.4)';
        statusSpan.style.display = 'inline-flex';
        statusSpan.style.alignItems = 'center';
        statusSpan.style.gap = '4px';
        statusSpan.innerHTML = `<i class="fas fa-check-circle" style="color: #10b981;"></i> ${isEnglish ? 'Draft saved' : 'Bozza salvata'}`;
        brandSpan.parentNode.appendChild(statusSpan);
    }

    initAccordion();
    initGlobalSearch();
    initViewportSwitcher();
    updatePreview();
    pushHistoryState();
    launchOnboardingTour();

    if (window.__profileCustomSelectLoaded) return;
    window.__profileCustomSelectLoaded = true;

    const shortLabel = (text) => {
        const clean = String(text || '').trim();
        const map = {
            'Nessuno': 'No', 'None': 'No',
            'Scuro': 'Dark', 'Dark': 'Dark',
            'Chiaro': 'Light', 'Light': 'Light',
            'Auto': 'Auto',
            'Standard': 'Std',
            'Compatto': 'Mini', 'Compact': 'Mini',
            'Showcase': 'Show',
            'Glass': 'Glass',
            'Pieno': 'Full', 'Solid': 'Solid',
            'Outline': 'Line',
            'Neon': 'Neon',
            'Pill': 'Pill',
            'Rounded': 'Round',
            'Squadrato': 'Sharp', 'Sharp': 'Sharp',
            'Pubblico': 'Pub', 'Public': 'Pub',
            'Solo utenti loggati': 'Login', 'Logged users only': 'Login',
            'Privato': 'Priv', 'Private': 'Priv',
            'Rotazione': 'Spin', 'Spin': 'Spin',
            'Arcobaleno': 'RGB', 'Rainbow': 'RGB',
            'Glitch leggero': 'Glitch', 'Light Glitch': 'Glitch',
            'Mouse glow': 'Glow',
            'Particelle soft': 'Soft', 'Soft particles': 'Soft',
            'Scanlines soft': 'Scan', 'Soft scanlines': 'Scan',
            'Ambient glow': 'Glow',
            'Onde gradient': 'Wave', 'Gradient waves': 'Wave',
            'Stelle leggere': 'Stars', 'Light stars': 'Stars',
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

            wrap.classList.toggle('is-open');
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
})();