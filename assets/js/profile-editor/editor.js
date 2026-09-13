/**
 * Editor del profilo — regia.
 *
 * Il form e' l'unica fonte dello stato: ogni controllo scrive in un campo, e
 * da li' partono tre cose.
 *   1. Anteprima dal vivo: i valori viaggiano verso l'iframe con postMessage e
 *      profile.php li applica subito (profile-style.js per forme e colori).
 *   2. Bozza: dopo una breve pausa il form va a api/update_profile_draft.php;
 *      per i cambi che l'anteprima non sa disegnare da sola (liste, sezioni,
 *      layout) l'iframe si ricarica dalla bozza.
 *   3. Cronologia e stato: annulla/ripeti e "modifiche non pubblicate" si
 *      basano su un'istantanea del form.
 * "Pubblica" invia il form ad api/update_profile.php e resta nell'editor.
 */
(function () {
    'use strict';

    const PE = window.PE;
    if (!PE) return;
    const { t, $, $$, data } = PE;
    const form = document.getElementById('profileEditForm');
    const iframe = document.getElementById('profilePreviewIframe');
    if (!form) return;

    const storageKey = (name) => `cripsum.editor.${name}.${data.userKey || 'me'}`;
    const store = {
        get(name) { try { return localStorage.getItem(storageKey(name)); } catch (_) { return null; } },
        set(name, value) { try { localStorage.setItem(storageKey(name), value); } catch (_) { /* niente */ } },
    };

    // Campi che l'anteprima applica da sola, senza ricaricare la pagina.
    const LIVE_FIELDS = new Set([
        'accent_color', 'profile_secondary_color', 'profile_text_color', 'profile_theme', 'profile_card_color', 'profile_card_opacity', 'profile_card_blur',
        'profile_border_radius', 'profile_ui_shape', 'profile_avatar_shape', 'profile_border_width', 'profile_border_color', 'profile_border_opacity', 'profile_border_style',
        'profile_link_style', 'profile_button_size', 'profile_social_size', 'profile_icon_spacing', 'profile_badge_size', 'profile_font',
        'profile_bg_overlay_opacity', 'profile_bg_blur', 'profile_bg_orbs_opacity',
        'display_name', 'username', 'bio',
        'profile_name_color', 'profile_name_effect', 'profile_name_grad_color1', 'profile_name_grad_color2', 'profile_name_grad_angle', 'profile_name_glow_color',
        'avatar_ring_style', 'avatar_ring_color', 'profile_avatar_border', 'profile_effect',
        'profile_cursor_effect', 'profile_cursor_custom_url', 'profile_cursor_custom_center', 'profile_cursor_custom_hover_url', 'profile_cursor_custom_hover_center',
        'tilt_preset', 'tilt_enabled', 'tilt_max', 'tilt_glare', 'tilt_zoom', 'tilt_speed',
        'profile_music_theme', 'profile_show_audio_btn', 'profile_audio_btn_position', 'profile_audio_default_volume', 'profile_show_audio_player',
        'profile_music_title', 'profile_music_artist',
        // Solo interni all'editor, non cambiano la pagina.
        'music_source', 'profile_tab_title', 'profile_tab_animation', 'profile_tab_animation_text', 'profile_tab_animation_speed',
        'custom_alias', 'profile_visibility',
    ]);

    // ── Valori del form ─────────────────────────────────────────────────────
    const syncJsonFields = () => {
        const set = (name, value) => {
            const input = form.querySelector(`[data-json-field="${name}"]`);
            if (input) input.value = typeof value === 'string' ? value : JSON.stringify(value);
        };
        set('socials_json', PE.items.collect('socials'));
        set('links_json', PE.items.collect('links'));
        set('projects_json', PE.items.collect('projects'));
        set('contents_json', PE.items.collect('contents'));
        set('blocks_json', PE.items.collect('blocks'));
        set('embeds_json', PE.items.collect('embeds'));
        set('profile_tags_json', PE.items.collect('tags').slice(0, 10));
        set('badges_json', PE.badges.selected());
        set('characters_json', PE.characters.selected());
        set('profile_sections_order', PE.sections.order().join(','));
        set('profile_sections_config', PE.sections.config());
    };

    /** Il form cosi' com'e', con i JSON aggiornati. */
    const rawFormData = ({ withFiles = false } = {}) => {
        syncJsonFields();
        const fd = new FormData(form);
        if (!withFiles) ['avatar', 'banner', 'profile_music_file'].forEach((name) => fd.delete(name));
        // I radio delle righe hanno nomi casuali: il loro valore e' gia' nei JSON.
        Array.from(new Set(fd.keys())).filter((key) => key.startsWith('pe-local-')).forEach((key) => fd.delete(key));
        return fd;
    };

    /** FormData pronto da inviare: niente campi che servono solo all'editor. */
    const buildFormData = ({ withFiles = false } = {}) => {
        const fd = rawFormData({ withFiles });
        // Con "Carica un MP3" il link non deve sopravvivere.
        if (fd.get('music_source') === 'file') fd.set('profile_music_url', '');
        fd.delete('music_source');
        fd.delete('tilt_preset');
        return fd;
    };

    /** Tutti i valori come oggetto semplice (per anteprima e confronti). */
    const settings = () => {
        const out = {};
        for (const [key, value] of buildFormData().entries()) {
            if (typeof value === 'string') out[key] = value;
        }
        return out;
    };

    const snapshot = () => {
        const fd = rawFormData();
        const parts = [];
        for (const [key, value] of fd.entries()) {
            if (key === 'csrf_token' || typeof value !== 'string') continue;
            parts.push([key, value]);
        }
        return JSON.stringify(parts);
    };

    /** Riporta il form a un'istantanea (annulla, ripeti). */
    const restore = (snap) => {
        const entries = JSON.parse(snap);
        const values = new Map();
        entries.forEach(([key, value]) => {
            if (!values.has(key)) values.set(key, []);
            values.get(key).push(value);
        });
        const handled = new Set();
        $$('[name]', form).forEach((el) => {
            const name = el.name;
            if (['csrf_token', 'target_user_id'].includes(name) || el.type === 'file' || el.dataset.jsonField) return;
            const list = values.get(name) || [];
            if (el.type === 'checkbox') {
                el.checked = list.includes(el.value);
            } else if (el.type === 'radio') {
                el.checked = list.includes(el.value);
            } else if (el.type === 'hidden' && form.querySelector(`input[type="checkbox"][name="${CSS.escape(name)}"]`)) {
                // Il nascosto "0" della coppia hidden/checkbox resta com'e'.
            } else if (!handled.has(name)) {
                el.value = list.length ? list[list.length - 1] : '';
                handled.add(name);
            }
        });
        const json = (name, fallback) => {
            try { return JSON.parse((values.get(name) || [])[0] ?? fallback); } catch (_) { return JSON.parse(fallback); }
        };
        PE.items.load('socials', json('socials_json', '[]'));
        PE.items.load('links', json('links_json', '[]'));
        PE.items.load('projects', json('projects_json', '[]'));
        PE.items.load('contents', json('contents_json', '[]'));
        PE.items.load('blocks', json('blocks_json', '[]'));
        PE.items.load('embeds', json('embeds_json', '[]'));
        PE.items.load('tags', json('profile_tags_json', '[]'));
        PE.badges.set(json('badges_json', '[]'));
        PE.characters.set(json('characters_json', '[]'));
        PE.sections.apply(((values.get('profile_sections_order') || [''])[0] || '').split(',').filter(Boolean), json('profile_sections_config', '{}'));
        PE.syncAll();
        syncDerived();
        PE.refreshSectionSummaries();
    };

    // ── Stato: bozza, pubblicato ────────────────────────────────────────────
    const statusEl = document.getElementById('peStatus');
    let publishedSnapshot = '';
    let draftState = 'idle';

    const renderStatus = () => {
        if (!statusEl) return;
        const dirty = snapshot() !== publishedSnapshot;
        let state = dirty ? 'dirty' : 'saved';
        let label = dirty ? t('Modifiche non pubblicate', 'Unpublished changes') : t('Tutto pubblicato', 'Everything published');
        if (draftState === 'saving') { state = 'saving'; label = t('Salvo la bozza…', 'Saving draft…'); }
        if (draftState === 'error') { state = 'error'; label = t('Bozza non salvata', 'Draft not saved'); }
        statusEl.dataset.state = state;
        statusEl.querySelector('.pe-status-text').textContent = label;
        document.getElementById('pePublish')?.classList.toggle('is-attention', dirty);
    };

    let draftTimer = null;
    let draftQueue = Promise.resolve();
    let draftSeq = 0;
    let reloadPending = false;

    const saveDraft = () => {
        const seq = ++draftSeq;
        draftState = 'saving';
        renderStatus();
        const body = buildFormData();
        draftQueue = draftQueue.then(async () => {
            if (seq < draftSeq) return; // un'istantanea piu' nuova e' gia' in coda
            try {
                const res = await fetch('/api/update_profile_draft.php', { method: 'POST', body, credentials: 'same-origin' });
                const json = await res.json().catch(() => ({}));
                if (!json.ok) throw new Error(json.message || '');
                draftState = 'idle';
                if (reloadPending && seq === draftSeq) {
                    reloadPending = false;
                    reloadPreview();
                }
            } catch (error) {
                draftState = 'error';
                if (error.message) PE.toast(error.message, { type: 'error' });
            }
            renderStatus();
        });
    };

    const scheduleDraft = (delay = 700) => {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, delay);
    };

    // ── Cronologia ──────────────────────────────────────────────────────────
    const timeline = { stack: [], index: -1, timer: null, restoring: false };
    const undoBtn = document.getElementById('peUndo');
    const redoBtn = document.getElementById('peRedo');

    const updateHistoryButtons = () => {
        if (undoBtn) undoBtn.disabled = timeline.index <= 0;
        if (redoBtn) redoBtn.disabled = timeline.index >= timeline.stack.length - 1;
    };

    const pushHistory = () => {
        if (timeline.restoring) return;
        const snap = snapshot();
        if (timeline.stack[timeline.index] === snap) return;
        timeline.stack = timeline.stack.slice(0, timeline.index + 1);
        timeline.stack.push(snap);
        if (timeline.stack.length > 80) timeline.stack.shift();
        timeline.index = timeline.stack.length - 1;
        updateHistoryButtons();
    };

    // I cambi ravvicinati (una parola, un trascinamento) diventano un passo solo.
    const scheduleHistory = () => {
        clearTimeout(timeline.timer);
        timeline.timer = setTimeout(pushHistory, 450);
    };

    const goHistory = (step) => {
        clearTimeout(timeline.timer);
        pushHistory();
        const target = timeline.index + step;
        if (target < 0 || target >= timeline.stack.length) return;
        timeline.index = target;
        timeline.restoring = true;
        restore(timeline.stack[target]);
        timeline.restoring = false;
        updateHistoryButtons();
        sendLive();
        reloadPending = true;
        scheduleDraft(150);
        renderStatus();
    };

    undoBtn?.addEventListener('click', () => goHistory(-1));
    redoBtn?.addEventListener('click', () => goHistory(1));

    /** Da chiamare dopo ogni modifica. `structural`: l'anteprima va ricaricata. */
    PE.changed = ({ structural = false, live = true } = {}) => {
        if (!ready) return;
        if (live) sendLive();
        if (structural) reloadPending = true;
        scheduleDraft(structural ? 350 : 700);
        scheduleHistory();
        renderStatus();
        updateChecklist();
    };

    let ready = false;

    const onFieldEvent = (event) => {
        const el = event.target;
        if (!(el instanceof HTMLElement) || el.type === 'file' || el.closest('.pe-popover')) return;
        if (el.id === 'peCharacterSearch' || el.id === 'pePaletteInput') return;
        const insideItems = el.closest('.pe-item, .pe-badges, #peCharacters, [data-section-config]');
        const name = el.name || '';
        if (!name && !insideItems && !el.dataset.field && !el.dataset.local) return;

        syncDerived(el);
        PE.updateShowIf();
        const structural = insideItems ? event.type === 'change' || !el.matches('input[type="text"], input[type="url"], textarea') : (name && !LIVE_FIELDS.has(name));
        // Il testo dentro le liste ricarica l'anteprima solo a fine battitura.
        if (insideItems && event.type === 'input' && el.matches('input[type="text"], input[type="url"], textarea')) {
            reloadPending = true;
        }
        PE.changed({ structural: !!structural });
    };
    form.addEventListener('input', onFieldEvent);
    form.addEventListener('change', onFieldEvent);

    // ── Campi collegati ─────────────────────────────────────────────────────
    const byName = (name) => form.querySelector(`[name="${CSS.escape(name)}"]:not([type="hidden"])`) || form.querySelector(`[name="${CSS.escape(name)}"]`);
    const radioValue = (name) => form.querySelector(`input[name="${CSS.escape(name)}"]:checked`)?.value ?? '';

    const syncDerived = (changed = null) => {
        // Anello: "Nessuno" spegne l'anello.
        const ring = byName('avatar_ring_style');
        const ringEnabled = document.getElementById('peRingEnabled');
        if (ring && ringEnabled) ringEnabled.value = ring.value === 'none' ? '0' : '1';

        // Inclinazione: il livello scrive i quattro valori.
        const tiltPreset = radioValue('tilt_preset');
        const tiltEnabled = document.getElementById('peTiltEnabled');
        if (tiltEnabled) tiltEnabled.value = tiltPreset === 'off' ? '0' : '1';
        if (changed?.name === 'tilt_preset' && data.catalog.tilt_presets[tiltPreset]) {
            const p = data.catalog.tilt_presets[tiltPreset];
            [['tilt_max', p.max], ['tilt_glare', p.glare], ['tilt_zoom', p.zoom], ['tilt_speed', p.speed]].forEach(([n, v]) => {
                const input = byName(n);
                if (input) { input.value = v; PE.syncSlider(input); }
            });
        }

        // Anteprima del nome dentro l'editor.
        const sample = document.querySelector('#peNamePreview .pe-name-sample');
        if (sample) {
            const displayName = byName('display_name')?.value.trim() || byName('username')?.value.trim() || '';
            sample.textContent = displayName;
            sample.dataset.effect = radioValue('profile_name_effect') || 'none';
            sample.style.setProperty('--name-color', byName('profile_name_color')?.value || '#ffffff');
            sample.style.setProperty('--name-grad-1', byName('profile_name_grad_color1')?.value || '#ffffff');
            sample.style.setProperty('--name-grad-2', byName('profile_name_grad_color2')?.value || '#8b5cf6');
            sample.style.setProperty('--name-angle', `${byName('profile_name_grad_angle')?.value || 90}deg`);
            sample.style.setProperty('--name-glow-color', byName('profile_name_glow_color')?.value || '#8b5cf6');
        }

        // Forma della foto nell'editor.
        const avatarEditor = document.querySelector('.pe-avatar-editor');
        if (avatarEditor) avatarEditor.dataset.avatarShape = radioValue('profile_avatar_shape') || 'circle';

        // L'accento dell'editor segue quello del profilo.
        const accent = PE.hex(byName('accent_color')?.value);
        if (accent) {
            // --editor-accent serve al ritaglio foto, --accent alla guida Markdown.
            const rgb = [1, 3, 5].map((i) => parseInt(accent.slice(i, i + 2), 16)).join(', ');
            document.body.style.setProperty('--pe-accent', accent);
            document.body.style.setProperty('--editor-accent', accent);
            document.body.style.setProperty('--editor-accent-rgb', rgb);
            document.body.style.setProperty('--accent', accent);
            document.body.style.setProperty('--accent-rgb', rgb);
        }
    };

    // ── Anteprima ───────────────────────────────────────────────────────────
    const frame = document.getElementById('peFrame');
    const post = (message) => {
        if (iframe?.contentWindow) iframe.contentWindow.postMessage(message, window.location.origin);
    };

    let liveTimer = null;
    const sendLive = () => {
        cancelAnimationFrame(liveTimer);
        liveTimer = requestAnimationFrame(() => post({ type: 'cripsum:settings', settings: settings(), premium: !!data.premium }));
    };

    const reloadPreview = () => {
        frame?.classList.add('is-loading');
        post({ type: 'cripsum:reload' });
    };

    const media = { avatar: null, background: null, music: null };

    iframe?.addEventListener('load', () => {
        frame?.classList.remove('is-loading');
        sendLive();
        if (media.avatar) post({ type: 'cripsum:media', kind: 'avatar', url: media.avatar });
        if (media.background) post({ type: 'cripsum:media', kind: 'background', url: media.background.url, fileType: media.background.type });
        if (media.music) post({ type: 'cripsum:media', kind: 'music', url: media.music });
    });

    PE.focusPreviewSection = (section) => post({ type: 'cripsum:focus', section });

    // Dispositivo
    $$('[data-device]').forEach((btn) => btn.addEventListener('click', () => {
        $$('[data-device]').forEach((b) => b.classList.toggle('is-active', b === btn));
        frame?.classList.toggle('is-mobile', btn.dataset.device === 'mobile');
        frame?.classList.toggle('is-desktop', btn.dataset.device !== 'mobile');
        store.set('device', btn.dataset.device);
    }));
    if (store.get('device') === 'mobile') document.querySelector('[data-device="mobile"]')?.click();

    // Anteprima a tutto schermo sui telefoni
    $$('[data-toggle-preview]').forEach((btn) => btn.addEventListener('click', () => {
        document.body.classList.toggle('pe-show-preview');
    }));

    // ── File: foto, sfondo, musica ──────────────────────────────────────────
    const avatarInput = document.getElementById('peAvatarInput');
    const bannerInput = document.getElementById('peBannerInput');
    const musicInput = document.getElementById('peMusicInput');

    const rejectFile = (input, message) => {
        input.value = '';
        PE.toast(message, { type: 'error' });
    };

    const loadCropper = () => new Promise((resolve) => {
        if (window.CripsumPhotoCropper) { resolve(); return; }
        const script = document.createElement('script');
        script.src = '/assets/js/photo-cropper.js?v=1.3';
        script.onload = resolve;
        script.onerror = resolve;
        document.head.appendChild(script);
    });

    avatarInput?.addEventListener('change', async () => {
        let file = avatarInput.files?.[0];
        if (!file) return;
        if (!file.type.startsWith('image/')) { rejectFile(avatarInput, t('Scegli un\'immagine.', 'Choose an image.')); return; }
        await loadCropper();
        if (window.CripsumPhotoCropper) {
            let cropped = null;
            try {
                cropped = await window.CripsumPhotoCropper.open(file, { shape: radioValue('profile_avatar_shape') || 'circle' });
            } catch (_) {
                cropped = file;
            }
            if (!cropped) { avatarInput.value = ''; return; }
            if (cropped !== file) {
                const dt = new DataTransfer();
                dt.items.add(cropped);
                avatarInput.files = dt.files;
                file = cropped;
            }
        }
        const limit = data.uploadLimits.avatar;
        if (file.size > limit) { rejectFile(avatarInput, t(`Foto troppo pesante: il massimo è ${PE.formatBytes(limit)}.`, `Photo too large: the maximum is ${PE.formatBytes(limit)}.`)); return; }
        const reader = new FileReader();
        reader.onload = () => {
            media.avatar = reader.result;
            const preview = document.getElementById('peAvatarPreview');
            if (preview) preview.src = reader.result;
            post({ type: 'cripsum:media', kind: 'avatar', url: reader.result });
        };
        reader.readAsDataURL(file);
        const useDiscord = form.querySelector('input[type="checkbox"][name="discord_use_avatar"]');
        if (useDiscord?.checked) { useDiscord.checked = false; }
        PE.toast(t('Foto pronta: premi Pubblica per usarla.', 'Photo ready: press Publish to use it.'), { type: 'success' });
        PE.changed({ live: false });
        updateChecklist();
    });

    bannerInput?.addEventListener('change', () => {
        const file = bannerInput.files?.[0];
        if (!file) return;
        if (!/^(image|video)\//.test(file.type)) { rejectFile(bannerInput, t('Usa un\'immagine o un video.', 'Use an image or a video.')); return; }
        const limit = data.uploadLimits.background;
        if (file.size > limit) { rejectFile(bannerInput, t(`Sfondo troppo pesante: il massimo è ${PE.formatBytes(limit)}.`, `Background too large: the maximum is ${PE.formatBytes(limit)}.`)); return; }
        const url = URL.createObjectURL(file);
        media.background = { url, type: file.type };
        const preview = document.getElementById('peBgPreview');
        if (preview) {
            preview.innerHTML = file.type.startsWith('video/') ? `<video src="${url}" muted loop playsinline autoplay></video>` : `<img src="${url}" alt="">`;
        }
        post({ type: 'cripsum:media', kind: 'background', url, fileType: file.type });
        PE.toast(t('Sfondo pronto: premi Pubblica per usarlo.', 'Background ready: press Publish to use it.'), { type: 'success' });
        PE.changed({ live: false });
        updateChecklist();
    });

    musicInput?.addEventListener('change', () => {
        const file = musicInput.files?.[0];
        if (!file) return;
        if (!(file.type === 'audio/mpeg' || /\.mp3$/i.test(file.name))) { rejectFile(musicInput, t('Usa un file MP3.', 'Use an MP3 file.')); return; }
        const limit = data.uploadLimits.music;
        if (file.size > limit) { rejectFile(musicInput, t(`MP3 troppo pesante: il massimo è ${PE.formatBytes(limit)}.`, `MP3 too large: the maximum is ${PE.formatBytes(limit)}.`)); return; }
        media.music = URL.createObjectURL(file);
        const name = document.getElementById('peMusicName');
        if (name) name.textContent = file.name;
        const title = byName('profile_music_title');
        if (title && !title.value.trim()) title.value = file.name.replace(/\.mp3$/i, '');
        const remove = document.getElementById('peRemoveMusic');
        if (remove) remove.checked = false;
        post({ type: 'cripsum:media', kind: 'music', url: media.music, title: title?.value || '' });
        PE.changed();
    });

    // ── Aree e navigazione ──────────────────────────────────────────────────
    const showView = (view, { focus = false } = {}) => {
        if (!document.getElementById(`view-${view}`)) return;
        $$('.pe-rail-btn[data-view]').forEach((btn) => {
            const active = btn.dataset.view === view;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-current', active ? 'page' : 'false');
        });
        $$('[data-view-panel]').forEach((panel) => {
            const active = panel.dataset.viewPanel === view;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });
        document.body.classList.remove('pe-show-preview');
        const panel = document.getElementById('pePanel');
        if (panel && !focus) panel.scrollTop = 0;
        try { sessionStorage.setItem(storageKey('view'), view); } catch (_) { /* niente */ }
        if (location.hash !== `#${view}`) window.history.replaceState(null, '', `#${view}`);
        PE.updateShowIf();
    };

    $$('.pe-rail-btn[data-view]').forEach((btn) => btn.addEventListener('click', () => showView(btn.dataset.view)));

    /** Porta a un'impostazione: apre l'area, la sezione o i dettagli, evidenzia. */
    const reveal = (el) => {
        if (!el) return;
        const view = el.closest('[data-view-panel]')?.dataset.viewPanel;
        if (view) showView(view, { focus: true });
        const section = el.closest('.pe-section');
        if (section) PE.sections.toggle(section, true);
        const details = el.closest('details');
        if (details) details.open = true;
        const item = el.closest('.pe-item');
        if (item && !item.classList.contains('is-open')) item.querySelector('.pe-item-toggle')?.click();
        requestAnimationFrame(() => {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.remove('pe-flash');
            void el.offsetWidth;
            el.classList.add('pe-flash');
            el.querySelector('input:not([type="hidden"]), select, textarea, button')?.focus({ preventScroll: true });
        });
    };

    document.addEventListener('click', (event) => {
        const go = event.target.closest('[data-goto]');
        if (go) reveal(document.getElementById(go.dataset.goto));
    });

    // ── Ricerca delle impostazioni ──────────────────────────────────────────
    const palette = document.getElementById('pePalette');
    const paletteInput = document.getElementById('pePaletteInput');
    const paletteResults = document.getElementById('pePaletteResults');
    const normalize = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    let paletteIndex = [];
    let paletteActive = 0;

    const buildIndex = () => {
        const viewNames = Object.fromEntries($$('.pe-rail-btn[data-view]').map((b) => [b.dataset.view, (b.querySelector('.pe-rail-label') || b).textContent.trim()]));
        paletteIndex = $$('[data-search]', form).filter((el) => !el.closest('.pe-item')).map((el) => {
            const label = el.querySelector('.pe-field-title, .pe-field-label label, .pe-toggle-title, .pe-group-head h3, .pe-section-text strong, h4')?.textContent?.trim()
                || el.dataset.search.split(' ').slice(0, 4).join(' ');
            const group = el.closest('.pe-group')?.querySelector('.pe-group-head h3')?.textContent?.trim();
            const view = el.closest('[data-view-panel]')?.dataset.viewPanel;
            return { el, label, path: [viewNames[view], group !== label ? group : null].filter(Boolean).join(' › '), haystack: normalize(`${label} ${el.dataset.search} ${group || ''}`) };
        });
    };

    const renderResults = () => {
        const q = normalize(paletteInput.value).trim();
        const tokens = q.split(/\s+/).filter(Boolean);
        const results = (tokens.length
            ? paletteIndex.filter((r) => tokens.every((tok) => r.haystack.includes(tok)))
                .sort((a, b) => (normalize(a.label).startsWith(tokens[0]) ? -1 : 0) - (normalize(b.label).startsWith(tokens[0]) ? -1 : 0))
            : paletteIndex.filter((r) => r.el.classList.contains('pe-group') || r.el.classList.contains('pe-section'))
        ).slice(0, 12);
        paletteActive = 0;
        paletteResults.innerHTML = results.length ? '' : `<li class="pe-palette-empty">${PE.escape(t('Nessuna impostazione trovata.', 'No settings found.'))}</li>`;
        results.forEach((r, i) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.className = 'pe-palette-result' + (i === 0 ? ' is-active' : '');
            li.innerHTML = '<strong></strong><small></small>';
            li.querySelector('strong').textContent = r.label;
            li.querySelector('small').textContent = r.path;
            li.addEventListener('click', () => { palette.close(); reveal(r.el); });
            li.addEventListener('mousemove', () => {
                paletteActive = i;
                $$('.pe-palette-result', paletteResults).forEach((x, j) => x.classList.toggle('is-active', j === i));
            });
            li.peTarget = r.el;
            paletteResults.appendChild(li);
        });
    };

    const openPalette = () => {
        if (!palette) return;
        buildIndex();
        paletteInput.value = '';
        renderResults();
        palette.showModal();
        paletteInput.focus();
    };

    document.getElementById('peSearchOpen')?.addEventListener('click', openPalette);
    paletteInput?.addEventListener('input', renderResults);
    paletteInput?.addEventListener('keydown', (event) => {
        const items = $$('.pe-palette-result', paletteResults);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            paletteActive = (paletteActive + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % Math.max(items.length, 1);
            items.forEach((x, j) => x.classList.toggle('is-active', j === paletteActive));
            items[paletteActive]?.scrollIntoView({ block: 'nearest' });
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            const target = items[paletteActive]?.peTarget;
            if (target) { palette.close(); reveal(target); }
        }
    });

    // ── Scorciatoie ─────────────────────────────────────────────────────────
    document.addEventListener('keydown', (event) => {
        const mod = event.ctrlKey || event.metaKey;
        if (!mod) return;
        const key = event.key.toLowerCase();
        const typing = event.target.matches?.('input:not([type="range"]):not([type="checkbox"]):not([type="radio"]), textarea');
        if (key === 'k') { event.preventDefault(); openPalette(); }
        if (key === 's') { event.preventDefault(); form.requestSubmit(); }
        if (!typing && key === 'z' && !event.shiftKey) { event.preventDefault(); goHistory(-1); }
        if (!typing && (key === 'y' || (key === 'z' && event.shiftKey))) { event.preventDefault(); goHistory(1); }
    });

    // ── Temi, palette, ripristino ───────────────────────────────────────────
    const setField = (name, value) => {
        const els = $$(`[name="${CSS.escape(name)}"]`, form);
        if (!els.length) return;
        const radios = els.filter((el) => el.type === 'radio');
        if (radios.length) { radios.forEach((r) => { r.checked = String(r.value) === String(value); }); return; }
        const box = els.find((el) => el.type === 'checkbox');
        if (box) { box.checked = value === true || value === 1 || value === '1' || value === 'glow'; return; }
        els[els.length - 1].value = value;
    };

    const applySettings = (values, message) => {
        pushHistory();
        Object.entries(values).forEach(([name, value]) => {
            if (name === 'profile_font' && !data.premium) {
                const option = form.querySelector(`select[name="profile_font"] option[value="${CSS.escape(value)}"]`);
                if (option?.dataset.locked) return;
            }
            setField(name, value);
        });
        PE.syncAll();
        syncDerived();
        PE.changed({ structural: true });
        pushHistory();
        if (message) PE.toast(message, { type: 'success', action: { label: t('Annulla', 'Undo'), run: () => goHistory(-1) } });
    };

    $$('[data-theme-preset]').forEach((btn) => btn.addEventListener('click', () => {
        if (!data.premium) return;
        const theme = data.catalog.themes.find((th) => th.value === btn.dataset.themePreset);
        if (!theme) return;
        const values = { ...theme.settings };
        values.profile_border_style = values.profile_border_style === 'glow' ? '1' : '0';
        applySettings(values, t(`Tema «${theme.label}» applicato.`, `“${theme.label}” theme applied.`));
    }));

    $$('[data-palette]').forEach((btn) => btn.addEventListener('click', () => {
        const p = data.catalog.palettes[Number(btn.dataset.palette)];
        if (p) applySettings({ accent_color: p.accent_color, profile_secondary_color: p.profile_secondary_color });
    }));

    document.getElementById('peResetAppearance')?.addEventListener('click', () => {
        if (!window.confirm(t('Riportare colori, forme, bordi e sfondo ai valori predefiniti? Potrai annullare.', 'Reset colors, shapes, borders and background to defaults? You can undo it.'))) return;
        applySettings({
            profile_theme: 'dark', accent_color: '#0f5bff', profile_secondary_color: '#8b5cf6', profile_text_color: '', profile_layout_choice: 'standard',
            profile_border_radius: 30, profile_ui_shape: 'pill', profile_avatar_shape: 'circle', profile_card_color: '', profile_card_opacity: 68, profile_card_blur: 20,
            profile_border_width: 1, profile_border_color: '', profile_border_opacity: 100, profile_border_style: '0', profile_link_style: 'glass', profile_button_size: 48,
            profile_socials_style: 'cards', profile_social_size: 42, profile_icon_spacing: 8, profile_font: 'Poppins',
            profile_bg_overlay_opacity: 1, profile_bg_blur: 0, profile_bg_orbs_opacity: 0.45,
        }, t('Aspetto ripristinato.', 'Style reset.'));
    });

    // ── Preset salvati ──────────────────────────────────────────────────────
    const presetsEl = document.getElementById('pePresets');
    const presetRequest = async (action, extra = {}, { withForm = false } = {}) => {
        const body = withForm ? buildFormData() : new FormData();
        body.set('csrf_token', form.querySelector('input[name="csrf_token"]').value);
        body.set('target_user_id', String(data.targetUserId));
        Object.entries(extra).forEach(([k, v]) => body.set(k, v));
        const res = await fetch(`/api/manage_presets.php?action=${action}`, { method: 'POST', body, credentials: 'same-origin' });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.ok) throw new Error(json.message || t('Operazione non riuscita.', 'Something went wrong.'));
        return json;
    };

    const inlineName = (anchor, initial, onSave) => {
        const row = document.createElement('div');
        row.className = 'pe-inline-form';
        row.innerHTML = `<input type="text" class="pe-input" maxlength="60" placeholder="${PE.escape(t('Nome del preset', 'Preset name'))}"><button type="button" class="pe-btn pe-btn-primary pe-btn-sm">${PE.escape(t('Salva', 'Save'))}</button><button type="button" class="pe-btn pe-btn-ghost pe-btn-sm">${PE.escape(t('Annulla', 'Cancel'))}</button>`;
        const input = row.querySelector('input');
        input.value = initial;
        const [save, cancel] = row.querySelectorAll('button');
        const done = () => row.remove();
        const submit = async () => {
            const name = input.value.trim();
            if (!name) { input.focus(); return; }
            save.disabled = true;
            try { await onSave(name); done(); } catch (error) { PE.toast(error.message, { type: 'error' }); save.disabled = false; }
        };
        save.addEventListener('click', submit);
        cancel.addEventListener('click', done);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); submit(); }
            if (e.key === 'Escape') done();
        });
        anchor.appendChild(row);
        input.focus();
        input.select();
    };

    const loadPresets = async () => {
        if (!presetsEl || !data.premium) {
            if (presetsEl) presetsEl.innerHTML = `<p class="pe-empty-inline">${PE.escape(t('Con Premium puoi salvare fino a 5 look e passare dall\'uno all\'altro.', 'With Premium you can save up to 5 looks and switch between them.'))}</p>`;
            return;
        }
        presetsEl.innerHTML = `<p class="pe-empty-inline"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> ${PE.escape(t('Carico i preset…', 'Loading presets…'))}</p>`;
        try {
            const res = await fetch(`/api/manage_presets.php?action=list&target_user_id=${encodeURIComponent(data.targetUserId)}`, { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.ok) throw new Error(json.message);
            presetsEl.innerHTML = json.presets.length ? '' : `<p class="pe-empty-inline">${PE.escape(t('Nessun preset salvato.', 'No saved presets.'))}</p>`;
            json.presets.forEach((preset) => {
                let swatches = ['#0f5bff', '#8b5cf6'];
                try {
                    const parsed = JSON.parse(preset.preset_data);
                    swatches = [parsed.accent_color || swatches[0], parsed.profile_secondary_color || swatches[1]];
                } catch (_) { /* colori predefiniti */ }
                const row = document.createElement('div');
                row.className = 'pe-preset';
                row.innerHTML = `
                    <span class="pe-preset-swatch" style="--a:${PE.escape(swatches[0])};--b:${PE.escape(swatches[1])}"></span>
                    <span class="pe-preset-text"><strong></strong><small></small></span>
                    <button type="button" class="pe-btn pe-btn-secondary pe-btn-sm" data-act="load">${PE.escape(t('Usa', 'Apply'))}</button>
                    <button type="button" class="pe-icon-btn pe-icon-btn-sm" data-act="more" aria-label="${PE.escape(t('Altre azioni', 'More actions'))}"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>`;
                row.querySelector('strong').textContent = preset.nome;
                const created = new Date(String(preset.created_at || '').replace(' ', 'T'));
                row.querySelector('small').textContent = Number.isNaN(created.getTime())
                    ? (preset.created_at || '')
                    : created.toLocaleString(data.lang === 'en' ? 'en-GB' : 'it-IT', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                row.querySelector('[data-act="load"]').addEventListener('click', async () => {
                    if (!window.confirm(t('Caricare questo preset? Sostituisce il profilo pubblicato e la pagina si ricarica.', 'Load this preset? It replaces your published profile and the page reloads.'))) return;
                    try {
                        await presetRequest('load', { preset_id: preset.id });
                        publishedSnapshot = snapshot();
                        leaving = true;
                        window.location.reload();
                    } catch (error) { PE.toast(error.message, { type: 'error' }); }
                });
                row.querySelector('[data-act="more"]').addEventListener('click', (event) => {
                    const menu = document.createElement('div');
                    menu.className = 'pe-menu';
                    [
                        ['update', 'fa-solid fa-floppy-disk', t('Sovrascrivi con l\'attuale', 'Overwrite with current')],
                        ['rename', 'fa-solid fa-pen', t('Rinomina', 'Rename')],
                        ['duplicate', 'fa-regular fa-copy', t('Duplica', 'Duplicate')],
                        ['delete', 'fa-regular fa-trash-can', t('Elimina', 'Delete')],
                    ].forEach(([act, icon, label]) => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'pe-menu-item' + (act === 'delete' ? ' is-danger' : '');
                        b.innerHTML = `<i class="${icon}" aria-hidden="true"></i><span>${PE.escape(label)}</span>`;
                        b.addEventListener('click', async () => {
                            PE.closePopover();
                            try {
                                if (act === 'rename') {
                                    inlineName(row, preset.nome, async (name) => { await presetRequest('rename', { preset_id: preset.id, preset_name: name }); loadPresets(); });
                                    return;
                                }
                                if (act === 'delete' && !window.confirm(t('Eliminare il preset? Non si può annullare.', 'Delete this preset? This cannot be undone.'))) return;
                                if (act === 'update' && !window.confirm(t('Sovrascrivere il preset con il profilo attuale?', 'Overwrite the preset with your current profile?'))) return;
                                const json = await presetRequest(act, { preset_id: preset.id, preset_name: preset.nome }, { withForm: act === 'update' });
                                PE.toast(json.message || t('Fatto.', 'Done.'), { type: 'success' });
                                loadPresets();
                            } catch (error) { PE.toast(error.message, { type: 'error' }); }
                        });
                        menu.appendChild(b);
                    });
                    PE.openPopover(event.currentTarget, menu, { className: 'pe-popover-menu' });
                });
                presetsEl.appendChild(row);
            });
        } catch (error) {
            presetsEl.innerHTML = `<p class="pe-empty-inline is-error">${PE.escape(error.message || t('Preset non disponibili.', 'Presets unavailable.'))}</p>`;
        }
    };

    document.getElementById('peSavePreset')?.addEventListener('click', () => {
        if (!data.premium || !presetsEl) return;
        inlineName(presetsEl.parentElement.querySelector('.pe-presets-head'), '', async (name) => {
            const json = await presetRequest('save', { preset_name: name }, { withForm: true });
            PE.toast(json.message || t('Preset salvato.', 'Preset saved.'), { type: 'success' });
            loadPresets();
        });
    });

    // ── Link breve ──────────────────────────────────────────────────────────
    const aliasInput = document.getElementById('peAlias');
    const aliasStatus = document.getElementById('peAliasStatus');
    const aliasHelp = document.getElementById('peAliasHelp');
    if (aliasInput && aliasStatus && aliasHelp) {
        const defaultHelp = aliasHelp.textContent;
        let aliasTimer = null;
        aliasInput.addEventListener('input', () => {
            clearTimeout(aliasTimer);
            const value = aliasInput.value.trim();
            aliasHelp.classList.remove('is-ok', 'is-error');
            if (!value) { aliasStatus.innerHTML = ''; aliasHelp.textContent = defaultHelp; return; }
            if (!/^[a-zA-Z0-9_-]{3,30}$/.test(value)) {
                aliasStatus.innerHTML = '<i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>';
                aliasHelp.textContent = t('Da 3 a 30 caratteri: lettere, numeri, - e _.', '3 to 30 characters: letters, numbers, - and _.');
                aliasHelp.classList.add('is-error');
                return;
            }
            aliasStatus.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>';
            aliasTimer = setTimeout(async () => {
                try {
                    const res = await fetch(`/api/check_alias.php?alias=${encodeURIComponent(value)}&target_user_id=${encodeURIComponent(data.targetUserId)}`, { credentials: 'same-origin' });
                    const json = await res.json();
                    aliasStatus.innerHTML = json.available ? '<i class="fa-solid fa-circle-check" aria-hidden="true"></i>' : '<i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>';
                    aliasHelp.textContent = json.available ? t('Disponibile!', 'Available!') : (json.message || t('Non disponibile.', 'Not available.'));
                    aliasHelp.classList.add(json.available ? 'is-ok' : 'is-error');
                } catch (_) {
                    aliasStatus.innerHTML = '';
                    aliasHelp.textContent = defaultHelp;
                }
            }, 400);
        });
    }

    // ── Copia ───────────────────────────────────────────────────────────────
    document.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-copy]');
        if (!btn) return;
        const source = document.querySelector(btn.dataset.copy);
        const textValue = `https://${source?.textContent?.trim() || ''}`;
        try { await navigator.clipboard.writeText(textValue); PE.toast(t('Link copiato.', 'Link copied.'), { type: 'success' }); } catch (_) { /* niente */ }
    });

    // ── Primo accesso: cosa manca ───────────────────────────────────────────
    const checklistEl = document.getElementById('peChecklist');
    const updateChecklist = () => {
        if (!checklistEl || store.get('checklistDone') === '1') return;
        const steps = [
            { done: data.hasCustomAvatar || !!avatarInput?.files?.length, label: t('Metti una foto profilo', 'Add a profile photo'), go: 'grp-avatar' },
            { done: !!byName('bio')?.value.trim(), label: t('Scrivi una bio', 'Write a bio'), go: 'grp-identity' },
            { done: PE.items.count('socials') + PE.items.count('links') > 0, label: t('Aggiungi un social o un link', 'Add a social or a link'), go: 'grp-socials' },
            { done: data.hasCustomBackground || !!bannerInput?.files?.length, label: t('Scegli uno sfondo', 'Pick a background'), go: 'grp-background' },
        ];
        const done = steps.filter((s) => s.done).length;
        if (done === steps.length) {
            checklistEl.hidden = true;
            return;
        }
        checklistEl.hidden = false;
        checklistEl.innerHTML = `
            <div class="pe-checklist-head">
                <div><strong>${PE.escape(t('Completa il tuo profilo', 'Complete your profile'))}</strong><small>${done}/${steps.length}</small></div>
                <button type="button" class="pe-icon-btn pe-icon-btn-sm" aria-label="${PE.escape(t('Nascondi', 'Hide'))}" data-act="hide"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="pe-checklist-bar"><span style="width:${(done / steps.length) * 100}%"></span></div>
            <ul>${steps.map((s) => `<li class="${s.done ? 'is-done' : ''}"><button type="button" data-goto="${s.go}"><i class="fa-solid ${s.done ? 'fa-circle-check' : 'fa-circle'}" aria-hidden="true"></i>${PE.escape(s.label)}</button></li>`).join('')}</ul>
            <p class="pe-help">${PE.escape(t('Le modifiche si vedono subito nell\'anteprima. Quando ti piace, premi Pubblica.', 'Changes show up right away in the preview. When you like it, press Publish.'))}</p>`;
        checklistEl.querySelector('[data-act="hide"]').addEventListener('click', () => {
            store.set('checklistDone', '1');
            checklistEl.hidden = true;
        });
    };

    // ── Banner del piano ────────────────────────────────────────────────────
    const planBanner = document.getElementById('pePlanBanner');
    if (planBanner && store.get('planBannerHidden') !== '1') planBanner.hidden = false;
    planBanner?.querySelector('[data-dismiss-banner]')?.addEventListener('click', () => {
        planBanner.hidden = true;
        store.set('planBannerHidden', '1');
    });

    // ── Pubblica ────────────────────────────────────────────────────────────
    const publishBtn = document.getElementById('pePublish');
    let publishing = false;
    let leaving = false;

    const sendForm = (fd, onProgress) => new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.withCredentials = true;
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.addEventListener('progress', (e) => { if (e.lengthComputable) onProgress(e.loaded / e.total); });
        xhr.addEventListener('load', () => {
            let json = null;
            try { json = JSON.parse(xhr.responseText || '{}'); } catch (_) {
                reject(new Error(t('Risposta del server non valida.', 'Unexpected server response.')));
                return;
            }
            if (xhr.status < 200 || xhr.status >= 300 || !json.ok) {
                const error = new Error(json.message || t('Pubblicazione non riuscita.', 'Publishing failed.'));
                error.code = json.code || null;
                reject(error);
                return;
            }
            resolve(json);
        });
        xhr.addEventListener('error', () => reject(new Error(t('Errore di rete.', 'Network error.'))));
        xhr.send(fd);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (publishing) return;

        const username = byName('username');
        if (username && !/^(?!_)(?!.*_$)[a-zA-Z0-9_]{3,20}$/.test(username.value.trim())) {
            PE.toast(t('Username non valido: da 3 a 20 caratteri, lettere, numeri e trattino basso.', 'Invalid username: 3 to 20 characters, letters, numbers and underscore.'), { type: 'error' });
            reveal(document.getElementById('grp-identity'));
            return;
        }

        publishing = true;
        publishBtn.disabled = true;
        publishBtn.classList.add('is-busy');
        const label = publishBtn.querySelector('.pe-publish-label');
        const progress = publishBtn.querySelector('.pe-publish-progress');
        const setProgress = (ratio) => { progress.style.width = `${Math.round(ratio * 100)}%`; };

        try {
            if (PE.uploads.hasPending()) {
                label.textContent = t('Attendo i caricamenti…', 'Waiting for uploads…');
                await PE.uploads.waitForAll();
            }
            label.textContent = t('Pubblico…', 'Publishing…');

            let json;
            try {
                json = await sendForm(buildFormData({ withFiles: true }), setProgress);
            } catch (error) {
                if (error.code !== 'csrf') throw error;
                // Sessione rigenerata altrove: un nuovo token e un solo nuovo tentativo.
                const res = await fetch('/api/profile_csrf.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const fresh = await res.json().catch(() => null);
                if (!fresh?.csrf_token) throw error;
                $$('input[name="csrf_token"]').forEach((input) => { input.value = fresh.csrf_token; });
                json = await sendForm(buildFormData({ withFiles: true }), setProgress);
            }

            [avatarInput, bannerInput, musicInput].forEach((input) => { if (input) input.value = ''; });
            if (media.music) data.hasServerMusic = true;
            if (media.avatar) data.hasCustomAvatar = true;
            if (media.background) data.hasCustomBackground = true;
            media.avatar = media.background = media.music = null;
            publishedSnapshot = snapshot();
            if (json.profile_url) {
                data.profileUrl = json.profile_url;
                $$('a[href^="/u/"]').forEach((a) => { a.href = json.profile_url; });
                const link = document.getElementById('peProfileLink');
                if (link) link.textContent = `cripsum.com${json.profile_url}`;
            }
            renderStatus();
            reloadPreview();
            PE.toast(t('Profilo pubblicato.', 'Profile published.'), {
                type: 'success',
                timeout: 6000,
                action: { label: t('Vedi profilo', 'View profile'), run: () => window.open(data.profileUrl, '_blank', 'noopener') },
            });
        } catch (error) {
            PE.toast(error.message, { type: 'error', timeout: 7000 });
        } finally {
            publishing = false;
            publishBtn.disabled = false;
            publishBtn.classList.remove('is-busy');
            label.textContent = t('Pubblica', 'Publish');
            setProgress(0);
        }
    });

    // ── Uscita con modifiche non pubblicate ─────────────────────────────────
    window.addEventListener('beforeunload', (event) => {
        if (leaving || publishing || !ready) return;
        if (snapshot() !== publishedSnapshot) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-leave-editor]');
        if (!link) return;
        if (snapshot() === publishedSnapshot) { leaving = true; return; }
        if (window.confirm(t('Hai modifiche non pubblicate. La bozza resta, ma il profilo pubblico non cambia. Uscire comunque?', 'You have unpublished changes. The draft is kept, but your public profile won\'t change. Leave anyway?'))) {
            leaving = true;
        } else {
            event.preventDefault();
        }
    });

    // ── Avvio ───────────────────────────────────────────────────────────────
    const start = () => {
        PE.initComponents();
        PE.initItems();
        syncDerived();
        PE.updateShowIf();

        publishedSnapshot = snapshot();
        pushHistory();
        renderStatus();
        updateChecklist();
        loadPresets();

        const initialView = (location.hash || '').replace('#', '') || (() => { try { return sessionStorage.getItem(storageKey('view')); } catch (_) { return null; } })();
        if (initialView) showView(initialView);

        if (data.flash?.success) PE.toast(data.flash.success, { type: 'success' });
        if (data.flash?.error) PE.toast(data.flash.error, { type: 'error' });

        ready = true;
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
