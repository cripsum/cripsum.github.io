/**
 * Editor del profilo — elementi ripetibili, sezioni, personaggi e badge.
 *
 * Ogni elemento (tag, social, link, embed, progetto, contenuto, blocco) e' una
 * card chiusa con un riassunto: si apre per modificarlo. I valori stanno in
 * campi `data-field` che collect() trasforma nel JSON che api/update_profile.php
 * gia' conosce (socials_json, links_json, ...).
 */
(function () {
    'use strict';

    const PE = window.PE;
    const { t, $, $$, escape, data } = PE;
    const catalog = data.catalog || {};
    const limits = (catalog.limits || {})[data.premium ? 'premium' : 'free'] || {};

    // ── Pezzi di markup per i campi delle righe ─────────────────────────────
    const field = (label, control, { help = '', premium = false, showIf = '', full = true } = {}) => `
        <div class="pe-field${full ? '' : ' pe-field--half'}${premium && !data.premium ? ' is-locked' : ''}"${premium && !data.premium ? ' data-premium-lock="1"' : ''}${showIf ? ` data-show-if="${escape(showIf)}"` : ''}>
            <div class="pe-field-label"><span class="pe-field-title">${escape(label)}</span>${premium ? '<span class="pe-chip-premium"><i class="fa-solid fa-crown" aria-hidden="true"></i><span>Premium</span></span>' : ''}</div>
            ${control}
            ${help ? `<p class="pe-help">${help}</p>` : ''}
        </div>`;

    const disabled = (premium) => (premium && !data.premium ? ' disabled' : '');

    const text = (name, value, { max = 255, placeholder = '', type = 'text', premium = false } = {}) =>
        `<input class="pe-input" type="${type}" data-field="${name}" value="${escape(value)}" maxlength="${max}" placeholder="${escape(placeholder)}" autocomplete="off"${disabled(premium)}>`;

    const area = (name, value, { max = 260, placeholder = '', rows = 3 } = {}) =>
        `<textarea class="pe-input pe-textarea" data-field="${name}" maxlength="${max}" rows="${rows}" placeholder="${escape(placeholder)}">${escape(value)}</textarea>`;

    const seg = (name, value, options, { local = false, premiumValues = [] } = {}) => {
        // Il name serve solo a raggruppare i radio: editor.js lo scarta dal form.
        const group = PE.uid('pe-local');
        return `<div class="pe-seg" role="radiogroup">${options.map((opt) => {
            const locked = premiumValues.includes(opt.value) && !data.premium;
            return `<label class="pe-choice${locked ? ' is-locked' : ''}"${locked ? ' data-premium-lock="1"' : ''}>
                <input type="radio" ${local ? `data-local="${name}"` : `data-field="${name}"`} name="${group}" value="${escape(opt.value)}"${String(value) === String(opt.value) ? ' checked' : ''}${locked ? ' disabled' : ''}>
                <span class="pe-choice-body">${opt.icon ? `<i class="${escape(opt.icon)}" aria-hidden="true"></i>` : ''}<span class="pe-choice-label">${escape(opt.label)}</span>${locked ? '<i class="fa-solid fa-crown pe-choice-lock" aria-hidden="true"></i>' : ''}</span>
            </label>`;
        }).join('')}</div>`;
    };

    const select = (name, value, options) =>
        `<select class="pe-select" data-field="${name}">${options.map((opt) => `<option value="${escape(opt.value)}"${String(value) === String(opt.value) ? ' selected' : ''}>${escape(opt.label)}</option>`).join('')}</select>`;

    const color = (name, value, { premium = false } = {}) =>
        `<div class="pe-color" data-color-field><input type="hidden" data-field="${name}" value="${escape(value || '')}"${disabled(premium)}></div>`;

    const icon = (name, value, placeholderIcon) =>
        `<div class="pe-icon-field" data-icon-field data-placeholder-icon="${escape(placeholderIcon)}"><input type="hidden" data-field="${name}" value="${escape(value || '')}"></div>`;

    const media = (name, value, { purpose = 'icon', accept = 'image/jpeg,image/png,image/webp,image/gif', kind = '' } = {}) =>
        `<div class="pe-media-url" data-media-url data-purpose="${purpose}" data-accept="${escape(accept)}"${kind ? ` data-media-kind="${kind}"` : ''}><input type="hidden" data-field="${name}" value="${escape(value || '')}"></div>`;

    const toggle = (name, checked, label, { description = '', premium = false } = {}) => `
        <label class="pe-toggle${premium && !data.premium ? ' is-locked' : ''}"${premium && !data.premium ? ' data-premium-lock="1"' : ''}>
            <span class="pe-toggle-text"><span class="pe-toggle-title">${escape(label)}${premium ? ' <span class="pe-chip-premium"><i class="fa-solid fa-crown" aria-hidden="true"></i><span>Premium</span></span>' : ''}</span>${description ? `<small>${escape(description)}</small>` : ''}</span>
            <input class="pe-switch-input" type="checkbox" role="switch" data-field="${name}"${checked ? ' checked' : ''}${disabled(premium)}>
            <span class="pe-switch" aria-hidden="true"></span>
        </label>`;

    const row2 = (...fields) => `<div class="pe-row-2">${fields.join('')}</div>`;

    const cardTag = (item) => `
        <details class="pe-subsettings${data.premium ? '' : ' is-locked'}"${data.premium ? '' : ' data-premium-lock="1"'}>
            <summary><i class="fa-solid fa-tag" aria-hidden="true"></i>${escape(t('Etichetta sulla card', 'Card label'))}<span class="pe-chip-premium"><i class="fa-solid fa-crown" aria-hidden="true"></i><span>Premium</span></span></summary>
            ${field(t('Testo', 'Text'), text('card_tag_text', item.card_tag_text, { max: 30, placeholder: t('Es. NUOVO', 'E.g. NEW'), premium: true }), { help: escape(t('Un piccolo bollino accanto al titolo.', 'A small badge next to the title.')) })}
            ${row2(field(t('Sfondo', 'Background'), color('card_tag_bg', item.card_tag_bg || '#ef4444', { premium: true }), { full: false }), field(t('Testo', 'Text'), color('card_tag_color', item.card_tag_color || '#ffffff', { premium: true }), { full: false }))}
        </details>`;

    const truthy = (value, fallback = true) => (value === undefined || value === null ? fallback : (value === true || value === 1 || value === '1'));

    const hostLabel = (url) => {
        try {
            const u = new URL(url);
            return u.hostname.replace(/^www\./, '') + (u.pathname !== '/' ? u.pathname : '');
        } catch (_) {
            return url || '';
        }
    };

    const platformFor = (url) => {
        const host = (() => { try { return new URL(url).hostname.replace(/^www\./, ''); } catch (_) { return ''; } })();
        const map = {
            'instagram.com': 'instagram', 'tiktok.com': 'tiktok', 'youtube.com': 'youtube', 'youtu.be': 'youtube', 'twitch.tv': 'twitch',
            'x.com': 'x', 'twitter.com': 'x', 'discord.gg': 'discord', 'discord.com': 'discord', 'github.com': 'github', 'open.spotify.com': 'spotify',
            'spotify.com': 'spotify', 'soundcloud.com': 'soundcloud', 'steamcommunity.com': 'steam', 'reddit.com': 'reddit', 't.me': 'telegram',
            'threads.net': 'threads', 'bsky.app': 'bluesky', 'kick.com': 'kick', 'snapchat.com': 'snapchat', 'facebook.com': 'facebook',
            'linkedin.com': 'linkedin', 'pinterest.com': 'pinterest', 'patreon.com': 'patreon', 'paypal.me': 'paypal', 'paypal.com': 'paypal',
            'behance.net': 'behance', 'dribbble.com': 'dribbble',
        };
        if (/^mailto:/i.test(url)) return 'email';
        return map[host] || Object.entries(map).find(([domain]) => host.endsWith('.' + domain))?.[1] || (host ? 'website' : '');
    };

    const platformIcon = (value) => (catalog.platforms || []).find((p) => p.value === value)?.icon || 'fa-solid fa-link';
    const platformLabel = (value) => (catalog.platforms || []).find((p) => p.value === value)?.label || value;

    // ── Tipi di elemento ────────────────────────────────────────────────────
    const TYPES = {
        tags: {
            label: t('tag', 'tag'),
            empty: t('Nessun tag. Aggiungine uno per descriverti in poche parole.', 'No tags yet. Add one to describe yourself in a few words.'),
            defaults: () => ({ text: '', icon: '', color: '', gradient: '' }),
            hasVisibility: false,
            body: (item) => {
                const style = item.gradient ? 'gradient' : (item.color ? 'color' : 'default');
                return `
                    ${field(t('Testo', 'Text'), text('text', item.text, { max: 40, placeholder: t('Es. Video editor', 'E.g. Video editor') }))}
                    ${field(t('Icona', 'Icon'), icon('icon', item.icon, 'fa-solid fa-tag'), { help: escape(t('Facoltativa.', 'Optional.')) })}
                    ${field(t('Stile', 'Style'), seg('tag_style', style, [
                        { value: 'default', label: t('Predefinito', 'Default') },
                        { value: 'color', label: t('Colore', 'Color') },
                        { value: 'gradient', label: t('Sfumatura', 'Gradient') },
                    ], { local: true }), { help: escape(t('Il colore del testo si adatta da solo per restare leggibile.', 'Text color adapts by itself to stay readable.')) })}
                    <div class="pe-row-2">
                        ${field(t('Colore', 'Color'), color('color', item.color || '#8b5cf6'), { full: false, showIf: 'tag_style!=default' })}
                        ${field(t('Secondo colore', 'Second color'), color('gradient', item.gradient || '#ec4899'), { full: false, showIf: 'tag_style=gradient' })}
                    </div>`;
            },
            summary: (row, item) => ({ pill: item }),
            collect: (item, row) => {
                const style = row.querySelector('[data-local="tag_style"]:checked')?.value || 'default';
                if (style === 'default') { item.color = ''; item.gradient = ''; }
                if (style === 'color') item.gradient = '';
                return item.text ? item : null;
            },
        },

        socials: {
            label: 'social',
            empty: t('Nessun social. Aggiungi Instagram, TikTok, YouTube…', 'No socials yet. Add Instagram, TikTok, YouTube…'),
            defaults: () => ({ platform: 'website', url: '', label: '', display_username: '', icon: '', is_visible: 1 }),
            body: (item) => `
                ${field(t('Link al profilo', 'Profile link'), text('url', item.url, { type: 'url', placeholder: 'https://instagram.com/…' }), { help: escape(t('La piattaforma si riconosce dal link.', 'The platform is detected from the link.')) })}
                ${field(t('Piattaforma', 'Platform'), select('platform', item.platform || 'website', catalog.platforms || []))}
                ${row2(
                    field(t('Nome', 'Name'), text('label', item.label, { max: 40, placeholder: 'Instagram' }), { full: false }),
                    field('Username', text('display_username', item.display_username, { max: 60, placeholder: '@username' }), { full: false }),
                )}
                ${field(t('Icona personalizzata', 'Custom icon'), icon('icon', item.icon, platformIcon(item.platform)), { premium: true, help: escape(t('Vuota = icona della piattaforma.', 'Empty = platform icon.')) })}`,
            summary: (row, item) => ({
                icon: item.icon && data.premium ? item.icon : platformIcon(item.platform),
                title: item.label || platformLabel(item.platform),
                subtitle: item.display_username || hostLabel(item.url) || t('Link mancante', 'Missing link'),
                warn: !item.url,
            }),
            onInput: (row, target) => {
                if (target.dataset.field === 'url') {
                    const platform = platformFor(target.value.trim());
                    const sel = row.querySelector('[data-field="platform"]');
                    if (platform && sel && sel.value !== platform && (sel.value === 'website' || sel.value === 'other' || !row.dataset.platformTouched)) {
                        sel.value = platform;
                        PE.syncSelect(sel);
                        row.querySelector('[data-field="icon"]')?.closest('[data-icon-field]')?.setAttribute('data-placeholder-icon', platformIcon(platform));
                    }
                }
                if (target.dataset.field === 'platform') row.dataset.platformTouched = '1';
            },
            collect: (item) => (item.url ? item : null),
        },

        links: {
            label: 'link',
            empty: t('Nessun link. I link sono pulsanti grandi verso i tuoi siti.', 'No links yet. Links are big buttons pointing to your sites.'),
            defaults: () => ({ title: '', url: '', description: '', icon: 'fa-solid fa-link', button_style: 'card', is_visible: 1 }),
            body: (item) => `
                ${field(t('Titolo', 'Title'), text('title', item.title, { max: 60, placeholder: t('Es. Il mio portfolio', 'E.g. My portfolio') }))}
                ${field('URL', text('url', item.url, { type: 'url', placeholder: 'https://…' }))}
                ${field(t('Descrizione', 'Description'), text('description', item.description, { max: 160, placeholder: t('Facoltativa', 'Optional') }))}
                ${field(t('Icona', 'Icon'), icon('icon', item.icon, 'fa-solid fa-link'))}
                ${field(t('Aspetto', 'Look'), seg('button_style', item.button_style || 'card', (catalog.link_button_styles || [])))}
                ${cardTag(item)}`,
            summary: (row, item) => ({ icon: item.icon || 'fa-solid fa-link', title: item.title || t('Link senza titolo', 'Untitled link'), subtitle: hostLabel(item.url) || t('URL mancante', 'Missing URL'), warn: !item.url || !item.title }),
            collect: (item) => (item.title || item.url ? item : null),
        },

        embeds: {
            label: 'embed',
            empty: t('Nessun embed. Incolla il link di una playlist Spotify o di un video YouTube.', 'No embeds yet. Paste a Spotify playlist or YouTube video link.'),
            defaults: () => ({ type: 'spotify', url: '', title: '', is_visible: 1 }),
            body: (item) => `
                ${field('URL', text('url', item.url, { type: 'url', placeholder: 'https://open.spotify.com/… o https://youtube.com/…' }), { help: escape(t('Va bene anche il link normale: lo convertiamo noi.', 'A normal link is fine: we convert it for you.')) })}
                ${field(t('Tipo', 'Type'), seg('type', item.type || 'spotify', (catalog.embed_types || []).map((o) => ({ ...o, icon: o.value === 'spotify' ? 'fa-brands fa-spotify' : (o.value === 'youtube' ? 'fa-brands fa-youtube' : 'fa-solid fa-code') }))))}
                ${field(t('Titolo', 'Title'), text('title', item.title, { max: 100, placeholder: t('Facoltativo', 'Optional') }))}`,
            summary: (row, item) => ({ icon: item.type === 'spotify' ? 'fa-brands fa-spotify' : (item.type === 'youtube' ? 'fa-brands fa-youtube' : 'fa-solid fa-code'), title: item.title || (item.type === 'spotify' ? 'Spotify' : (item.type === 'youtube' ? 'YouTube' : 'Embed')), subtitle: hostLabel(item.url) || t('URL mancante', 'Missing URL'), warn: !item.url }),
            onInput: (row, target) => {
                if (target.dataset.field !== 'url') return;
                const v = target.value;
                const type = /spotify\.com/i.test(v) ? 'spotify' : (/youtu\.?be/i.test(v) ? 'youtube' : null);
                if (type) {
                    const radio = row.querySelector(`[data-field="type"][value="${type}"]`);
                    if (radio && !radio.checked) radio.checked = true;
                }
            },
            collect: (item) => (item.url ? item : null),
        },

        projects: {
            label: t('progetto', 'project'),
            empty: t('Nessun progetto. Mostra quello a cui stai lavorando.', 'No projects yet. Show what you are working on.'),
            defaults: () => ({ title: '', description: '', url: '', image_url: '', icon: '', tech_stack: '', status: 'active', is_visible: 1 }),
            body: (item) => `
                ${field(t('Nome', 'Name'), text('title', item.title, { max: 70, placeholder: t('Nome del progetto', 'Project name') }))}
                ${field(t('Descrizione', 'Description'), area('description', item.description, { max: 260 }))}
                ${field('URL', text('url', item.url, { type: 'url', placeholder: 'https://…' }))}
                ${field(t('Immagine', 'Image'), media('image_url', item.image_url), { help: escape(t('Facoltativa. Senza immagine la card mostra un\'icona.', 'Optional. Without an image the card shows an icon.')) })}
                ${data.itemIcons ? field(t('Icona (senza immagine)', 'Icon (without image)'), icon('icon', item.icon, 'fa-solid fa-layer-group'), { showIf: 'image_url=' }) : ''}
                ${field(t('Tecnologie', 'Tech stack'), text('tech_stack', item.tech_stack, { max: 160, placeholder: 'PHP, JavaScript, MySQL' }))}
                ${field(t('Stato', 'Status'), seg('status', item.status || 'active', catalog.project_statuses || []))}
                ${cardTag(item)}`,
            summary: (row, item) => ({ image: item.image_url, icon: item.icon || 'fa-solid fa-layer-group', title: item.title || t('Progetto senza nome', 'Untitled project'), subtitle: item.tech_stack || (catalog.project_statuses || []).find((s) => s.value === item.status)?.label || '', warn: !item.title }),
            collect: (item) => (item.title ? item : null),
        },

        contents: {
            label: t('contenuto', 'content'),
            empty: t('Nessun contenuto. Metti in mostra edit, video o post.', 'No content yet. Show off edits, videos or posts.'),
            defaults: () => ({ content_type: 'edit', title: '', description: '', url: '', thumbnail_url: '', icon: '', is_visible: 1 }),
            body: (item) => `
                ${field(t('Tipo', 'Type'), seg('content_type', item.content_type || 'edit', catalog.content_types || []))}
                ${field(t('Titolo', 'Title'), text('title', item.title, { max: 70 }))}
                ${field(t('Descrizione', 'Description'), area('description', item.description, { max: 220 }))}
                ${field('URL', text('url', item.url, { type: 'url', placeholder: 'https://…' }))}
                ${field(t('Copertina', 'Thumbnail'), media('thumbnail_url', item.thumbnail_url))}
                ${data.itemIcons ? field(t('Icona (senza copertina)', 'Icon (without thumbnail)'), icon('icon', item.icon, 'fa-solid fa-play'), { showIf: 'thumbnail_url=' }) : ''}
                ${cardTag(item)}`,
            summary: (row, item) => ({ image: item.thumbnail_url, icon: item.icon || 'fa-solid fa-play', title: item.title || t('Contenuto senza titolo', 'Untitled content'), subtitle: (catalog.content_types || []).find((c) => c.value === item.content_type)?.label || '', warn: !item.title }),
            collect: (item) => (item.title ? item : null),
        },

        blocks: {
            label: t('blocco', 'block'),
            empty: t('Nessun blocco. Un blocco libero può contenere testo, un\'immagine o un video.', 'No blocks yet. A custom block can hold text, an image or a video.'),
            defaults: () => ({ title: '', block_type: 'text', body: '', media_url: '', media_type: 'text', media_position: 'top', media_fit: 'cover', text_align: 'left', media_align: 'center', no_card_style: 0, is_visible: 1 }),
            body: (item) => {
                let format = item.block_type || 'text';
                let mediaKind = 'none';
                if (['image', 'gif'].includes(format)) { format = 'text'; mediaKind = 'image'; }
                else if (format === 'video') { format = 'text'; mediaKind = 'video'; }
                if (item.media_url) mediaKind = item.media_type === 'video' ? 'video' : 'image';
                const maxBody = data.premium ? 5000 : 700;
                return `
                    ${field(t('Titolo', 'Title'), text('title', item.title, { max: 80, placeholder: t('Es. Chi sono', 'E.g. About me') }))}
                    ${field(t('Formato del testo', 'Text format'), seg('block_type', format, [
                        { value: 'text', label: t('Testo', 'Text') },
                        { value: 'markdown', label: 'Markdown' },
                        { value: 'html', label: 'HTML' },
                    ], { premiumValues: ['markdown', 'html'] }))}
                    <div class="pe-field">
                        <div class="pe-field-label"><span class="pe-field-title">${escape(t('Testo', 'Text'))}</span>
                            <button type="button" class="pe-link markdown-guide-trigger" data-open-markdown-guide data-show-if="block_type=markdown"><i class="fa-brands fa-markdown" aria-hidden="true"></i>${escape(t('Guida Markdown', 'Markdown guide'))}</button>
                        </div>
                        <textarea class="pe-input pe-textarea block-body-textarea" data-field="body" rows="5" maxlength="${maxBody}" data-counter>${escape(item.body || '')}</textarea>
                        <span class="pe-counter"></span>
                    </div>
                    ${field(t('Media', 'Media'), seg('media_kind', mediaKind, [
                        { value: 'none', label: t('Nessuno', 'None') },
                        { value: 'image', label: t('Immagine', 'Image'), icon: 'fa-solid fa-image' },
                        { value: 'video', label: 'Video', icon: 'fa-solid fa-film' },
                    ], { local: true }))}
                    <div data-show-if="media_kind!=none">
                        <div class="pe-field" data-block-media>
                            ${media('media_url', item.media_url, { purpose: 'block', accept: 'image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm', kind: mediaKind === 'video' ? 'video' : '' })}
                            <p class="pe-help">${escape(t('Immagini JPG, PNG, WEBP, GIF o video MP4, WEBM (fino a 50MB).', 'JPG, PNG, WEBP, GIF images or MP4, WEBM videos (up to 50MB).'))}</p>
                        </div>
                    </div>
                    <details class="pe-subsettings${data.premium ? '' : ' is-locked'}"${data.premium ? '' : ' data-premium-lock="1"'}>
                        <summary><i class="fa-solid fa-table-columns" aria-hidden="true"></i>${escape(t('Impaginazione', 'Layout'))}<span class="pe-chip-premium"><i class="fa-solid fa-crown" aria-hidden="true"></i><span>Premium</span></span></summary>
                        ${field(t('Posizione del media', 'Media position'), seg('media_position', item.media_position || 'top', [{ value: 'top', label: t('Sopra il testo', 'Above text') }, { value: 'bottom', label: t('Sotto il testo', 'Below text') }]))}
                        ${field(t('Adattamento', 'Fit'), seg('media_fit', item.media_fit || 'cover', [{ value: 'cover', label: t('Riempi', 'Fill') }, { value: 'contain', label: t('Intero', 'Whole') }, { value: 'original', label: t('Originale', 'Original') }]))}
                        ${field(t('Allineamento del testo', 'Text alignment'), seg('text_align', item.text_align || 'left', [{ value: 'left', label: '', icon: 'fa-solid fa-align-left' }, { value: 'center', label: '', icon: 'fa-solid fa-align-center' }, { value: 'right', label: '', icon: 'fa-solid fa-align-right' }]))}
                        ${field(t('Allineamento del media', 'Media alignment'), seg('media_align', item.media_align || 'center', [{ value: 'left', label: '', icon: 'fa-solid fa-align-left' }, { value: 'center', label: '', icon: 'fa-solid fa-align-center' }, { value: 'right', label: '', icon: 'fa-solid fa-align-right' }]))}
                        ${toggle('no_card_style', truthy(item.no_card_style, false), t('Senza sfondo e bordo', 'No background and border'), { premium: true })}
                    </details>
                    ${cardTag(item)}`;
            },
            summary: (row, item) => {
                const kind = row.querySelector('[data-local="media_kind"]:checked')?.value || 'none';
                return {
                    image: kind === 'image' ? item.media_url : '',
                    icon: kind === 'video' ? 'fa-solid fa-film' : (item.block_type === 'markdown' ? 'fa-brands fa-markdown' : (item.block_type === 'html' ? 'fa-solid fa-code' : 'fa-solid fa-align-left')),
                    title: item.title || (item.body ? item.body.slice(0, 40) : t('Blocco vuoto', 'Empty block')),
                    subtitle: [kind === 'image' ? t('Immagine', 'Image') : (kind === 'video' ? 'Video' : ''), item.block_type === 'text' ? t('Testo', 'Text') : item.block_type].filter(Boolean).join(' · '),
                };
            },
            onInput: (row, target) => {
                if (target.dataset.local === 'media_kind') {
                    const mediaWrap = row.querySelector('[data-block-media] [data-media-url]');
                    if (mediaWrap) {
                        mediaWrap.dataset.mediaKind = target.value === 'video' ? 'video' : '';
                        PE.syncMediaUrl(mediaWrap);
                    }
                }
            },
            onUploaded: (row, detail) => {
                // Il tipo segue il file caricato: un video scelto da "Immagine" diventa video.
                const kind = detail.media_type === 'video' ? 'video' : 'image';
                const radio = row.querySelector(`[data-local="media_kind"][value="${kind}"]`);
                if (radio && !radio.checked) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            },
            collect: (item, row) => {
                const kind = row.querySelector('[data-local="media_kind"]:checked')?.value || 'none';
                if (kind === 'none') { item.media_url = ''; item.media_type = 'text'; }
                else item.media_type = kind;
                return item.title || item.body || item.media_url ? item : null;
            },
        },
    };

    // ── Card di un elemento ─────────────────────────────────────────────────
    const readItem = (row) => {
        const item = {};
        $$('[data-field]', row).forEach((el) => {
            const key = el.dataset.field;
            if (el.type === 'radio') {
                if (el.checked) item[key] = el.value;
            } else if (el.type === 'checkbox') {
                item[key] = el.checked;
            } else {
                item[key] = el.value.trim();
            }
        });
        return item;
    };

    const renderSummary = (row) => {
        const type = TYPES[row.dataset.type];
        const info = type.summary(row, readItem(row));
        const head = row.querySelector('.pe-item-summary');
        if (info.pill) {
            const pill = info.pill;
            const style = row.querySelector('[data-local="tag_style"]:checked')?.value || 'default';
            const bg = style === 'gradient' ? `linear-gradient(135deg, ${pill.color}, ${pill.gradient})` : (style === 'color' ? pill.color : '');
            head.innerHTML = `<span class="pe-tag-preview${style !== 'default' ? ' is-colored' : ''}"${bg ? ` style="background:${escape(bg)}"` : ''}>${pill.icon ? PE.iconHtml(pill.icon) : ''}<span></span></span>`;
            head.querySelector('.pe-tag-preview > span').textContent = pill.text || t('Nuovo tag', 'New tag');
            if (style !== 'default') {
                const hex = PE.hex(style === 'gradient' ? pill.color : pill.color) || '#8b5cf6';
                const n = parseInt(hex.slice(1), 16);
                const lum = (0.2126 * ((n >> 16) & 255) + 0.7152 * ((n >> 8) & 255) + 0.0722 * (n & 255)) / 255;
                head.querySelector('.pe-tag-preview').style.color = lum > 0.62 ? '#111827' : '#ffffff';
            }
            return;
        }
        const thumb = info.image
            ? `<img src="${escape(info.image)}" alt="" loading="lazy">`
            : PE.iconHtml(info.icon);
        head.innerHTML = `<span class="pe-item-thumb">${thumb}</span><span class="pe-item-text"><strong></strong><small></small></span>${info.warn ? `<i class="fa-solid fa-circle-exclamation pe-item-warn" title="${escape(t('Mancano dei dati: così non viene salvato', 'Missing data: it will not be saved like this'))}" aria-hidden="true"></i>` : ''}`;
        head.querySelector('strong').textContent = info.title;
        head.querySelector('small').textContent = info.subtitle || '';
    };

    const makeRow = (typeKey, item = {}, { open = false } = {}) => {
        const type = TYPES[typeKey];
        const values = { ...type.defaults(), ...item };
        const row = document.createElement('div');
        row.className = 'pe-item' + (open ? ' is-open' : '');
        row.dataset.type = typeKey;
        const visible = truthy(values.is_visible, true);
        row.innerHTML = `
            <div class="pe-item-head">
                <span class="pe-drag" title="${escape(t('Trascina per spostare', 'Drag to move'))}"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span>
                <button type="button" class="pe-item-toggle" aria-expanded="${open}"><span class="pe-item-summary"></span></button>
                ${type.hasVisibility === false ? '' : `
                <label class="pe-eye" title="${escape(t('Mostra o nascondi sul profilo', 'Show or hide on your profile'))}">
                    <input type="checkbox" data-field="is_visible"${visible ? ' checked' : ''}>
                    <i class="fa-solid fa-eye pe-eye-on" aria-hidden="true"></i><i class="fa-solid fa-eye-slash pe-eye-off" aria-hidden="true"></i>
                </label>`}
                <button type="button" class="pe-icon-btn pe-icon-btn-sm pe-item-more" aria-label="${escape(t('Altre azioni', 'More actions'))}"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
            </div>
            <div class="pe-item-body"${open ? '' : ' hidden'}>${type.body(values)}</div>`;

        PE.initComponents(row);
        renderSummary(row);

        row.querySelector('.pe-item-toggle').addEventListener('click', () => toggleRow(row));
        row.querySelector('.pe-item-more').addEventListener('click', (event) => openRowMenu(event.currentTarget, row));
        row.addEventListener('input', (event) => {
            type.onInput?.(row, event.target);
            renderSummary(row);
            PE.updateShowIf(row);
        });
        row.addEventListener('change', (event) => {
            type.onInput?.(row, event.target);
            renderSummary(row);
            PE.updateShowIf(row);
        });
        row.addEventListener('pe:uploaded', (event) => type.onUploaded?.(row, event.detail));
        return row;
    };

    const toggleRow = (row, force) => {
        const open = force ?? !row.classList.contains('is-open');
        row.classList.toggle('is-open', open);
        row.querySelector('.pe-item-body').hidden = !open;
        row.querySelector('.pe-item-toggle').setAttribute('aria-expanded', String(open));
        if (open) PE.updateShowIf(row);
    };

    const openRowMenu = (anchor, row) => {
        const menu = document.createElement('div');
        menu.className = 'pe-menu';
        const actions = [
            ['duplicate', 'fa-regular fa-copy', t('Duplica', 'Duplicate')],
            ['up', 'fa-solid fa-arrow-up', t('Sposta su', 'Move up')],
            ['down', 'fa-solid fa-arrow-down', t('Sposta giù', 'Move down')],
            ['delete', 'fa-regular fa-trash-can', t('Elimina', 'Delete')],
        ];
        actions.forEach(([act, iconClass, label]) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'pe-menu-item' + (act === 'delete' ? ' is-danger' : '');
            b.innerHTML = `<i class="${iconClass}" aria-hidden="true"></i><span>${escape(label)}</span>`;
            b.addEventListener('click', () => {
                PE.closePopover();
                const list = row.parentElement;
                if (act === 'duplicate') {
                    if (!canAdd(row.dataset.type)) return;
                    const copy = makeRow(row.dataset.type, readItem(row));
                    row.after(copy);
                } else if (act === 'up' && row.previousElementSibling) {
                    list.insertBefore(row, row.previousElementSibling);
                } else if (act === 'down' && row.nextElementSibling) {
                    list.insertBefore(row.nextElementSibling, row);
                } else if (act === 'delete') {
                    const next = row.nextElementSibling;
                    const snapshot = readItem(row);
                    const typeKey = row.dataset.type;
                    row.remove();
                    refreshList(typeKey);
                    PE.changed?.({ structural: true });
                    PE.toast(t('Elemento eliminato.', 'Item deleted.'), {
                        action: {
                            label: t('Annulla', 'Undo'),
                            run: () => {
                                const restored = makeRow(typeKey, snapshot);
                                if (next && next.parentElement === list) list.insertBefore(restored, next);
                                else list.appendChild(restored);
                                refreshList(typeKey);
                                PE.changed?.({ structural: true });
                            },
                        },
                    });
                    return;
                }
                refreshList(row.dataset.type);
                PE.changed?.({ structural: true });
            });
            menu.appendChild(b);
        });
        PE.openPopover(anchor, menu, { className: 'pe-popover-menu' });
    };

    // ── Liste ───────────────────────────────────────────────────────────────
    const listFor = (typeKey) => $(`.pe-items[data-items="${typeKey}"]`);

    const countFor = (typeKey) => $$('.pe-item', listFor(typeKey) || document.createElement('div')).length;

    const canAdd = (typeKey) => {
        const max = limits[typeKey];
        if (!max || countFor(typeKey) < max) return true;
        if (!data.premium && (limits[typeKey] || 0) < ((catalog.limits || {}).premium || {})[typeKey]) {
            PE.upsell(t(`Più di ${max} ${TYPES[typeKey].label}`, `More than ${max} ${TYPES[typeKey].label}s`));
        } else {
            PE.toast(t(`Puoi averne al massimo ${max}.`, `You can have at most ${max}.`), { type: 'error' });
        }
        return false;
    };

    const refreshList = (typeKey) => {
        const list = listFor(typeKey);
        if (!list) return;
        const count = countFor(typeKey);
        let empty = list.querySelector('.pe-empty-inline');
        if (!count) {
            if (!empty) {
                empty = document.createElement('p');
                empty.className = 'pe-empty-inline';
                empty.textContent = TYPES[typeKey].empty;
                list.appendChild(empty);
            }
        } else {
            empty?.remove();
        }
        const max = limits[typeKey];
        $$(`[data-limit-for="${typeKey}"]`).forEach((el) => {
            // Il contatore serve solo dove il limite si sente: piano Base.
            el.textContent = max && !data.premium ? `${count}/${max}` : '';
        });
        $$(`[data-add-item="${typeKey}"]`).forEach((btn) => btn.classList.toggle('is-full', !!max && count >= max));
        PE.refreshSectionSummaries?.();
    };

    PE.items = {
        TYPES,

        load(typeKey, items) {
            const list = listFor(typeKey);
            if (!list) return;
            $$('.pe-item', list).forEach((row) => row.remove());
            (items || []).forEach((item) => list.appendChild(makeRow(typeKey, item)));
            refreshList(typeKey);
        },

        add(typeKey) {
            const list = listFor(typeKey);
            if (!list || !canAdd(typeKey)) return;
            $$('.pe-item.is-open', list).forEach((row) => toggleRow(row, false));
            const row = makeRow(typeKey, {}, { open: true });
            list.appendChild(row);
            refreshList(typeKey);
            row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            row.querySelector('.pe-item-body input:not([type="hidden"]), .pe-item-body textarea')?.focus({ preventScroll: true });
            PE.changed?.({ structural: true });
        },

        collect(typeKey) {
            const list = listFor(typeKey);
            if (!list) return [];
            const type = TYPES[typeKey];
            return $$('.pe-item', list).map((row) => {
                const item = readItem(row);
                return type.collect ? type.collect(item, row) : item;
            }).filter(Boolean);
        },

        count: countFor,
        refreshList,
    };

    document.addEventListener('click', (event) => {
        const add = event.target.closest('[data-add-item]');
        if (add) {
            event.preventDefault();
            PE.items.add(add.dataset.addItem);
        }
    });

    // ── Sezioni: ordine, apertura, riassunto, intestazione ──────────────────
    const sectionsEl = document.getElementById('peSections');

    PE.sections = {
        order() {
            return $$('.pe-section', sectionsEl).map((s) => s.dataset.section);
        },

        config() {
            const config = {};
            $$('[data-section-config]', sectionsEl).forEach((wrap) => {
                const key = wrap.dataset.sectionConfig;
                const title = wrap.querySelector('[data-config="title"]')?.value.trim() || '';
                const icon = wrap.querySelector('[data-config="icon"]')?.value.trim() || '';
                const hidden = wrap.querySelector('[data-config="hidden"]')?.checked ? 1 : 0;
                if (title || icon || hidden) config[key] = { hidden, title, icon };
            });
            return config;
        },

        apply(order, config) {
            if (!sectionsEl) return;
            (order || []).forEach((key) => {
                const el = sectionsEl.querySelector(`.pe-section[data-section="${key}"]`);
                if (el) sectionsEl.appendChild(el);
            });
            $$('[data-section-config]', sectionsEl).forEach((wrap) => {
                const c = (config || {})[wrap.dataset.sectionConfig] || {};
                const titleEl = wrap.querySelector('[data-config="title"]');
                const iconEl = wrap.querySelector('[data-config="icon"]');
                const hiddenEl = wrap.querySelector('[data-config="hidden"]');
                if (titleEl) titleEl.value = c.title || '';
                if (iconEl) { iconEl.value = c.icon || ''; PE.syncIcon(iconEl.closest('[data-icon-field]')); }
                if (hiddenEl) hiddenEl.checked = !!c.hidden;
            });
        },

        toggle(sectionEl, force) {
            const open = force ?? !sectionEl.classList.contains('is-open');
            sectionEl.classList.toggle('is-open', open);
            sectionEl.querySelector('.pe-section-body').hidden = !open;
            sectionEl.querySelector('.pe-section-toggle').setAttribute('aria-expanded', String(open));
            if (open) {
                PE.updateShowIf(sectionEl);
                PE.focusPreviewSection?.(sectionEl.dataset.section);
            }
        },
    };

    PE.refreshSectionSummaries = () => {
        if (!sectionsEl) return;
        $$('.pe-section', sectionsEl).forEach((section) => {
            const key = section.dataset.section;
            const summary = section.querySelector('[data-section-summary]');
            const eye = section.querySelector('.pe-eye input[type="checkbox"]');
            section.classList.toggle('is-hidden-on-profile', eye && !eye.checked);
            if (!summary) return;
            let count = null;
            if (TYPES[key]) count = countFor(key);
            if (key === 'characters') count = PE.characters?.selected().length ?? null;
            if (key === 'badges') count = PE.badges?.selected().length ?? null;
            if (key === 'stats') count = PE.stats?.selected().length ?? null;
            const parts = [];
            if (count !== null && key === 'stats') {
                parts.push(count === 0 ? t('Nessuna statistica', 'No stats') : (count === 1 ? t('1 statistica', '1 stat') : t(`${count} statistiche`, `${count} stats`)));
            } else if (count !== null) {
                parts.push(count === 0 ? t('Vuota', 'Empty') : (count === 1 ? t('1 elemento', '1 item') : t(`${count} elementi`, `${count} items`)));
            } else {
                parts.push(t('Automatica', 'Automatic'));
            }
            if (eye && !eye.checked) parts.push(t('nascosta', 'hidden'));
            summary.textContent = parts.join(' · ');
        });
    };

    if (sectionsEl) {
        sectionsEl.addEventListener('click', (event) => {
            const btn = event.target.closest('.pe-section-toggle');
            if (btn) PE.sections.toggle(btn.closest('.pe-section'));
        });
        sectionsEl.addEventListener('change', (event) => {
            if (event.target.closest('.pe-eye')) PE.refreshSectionSummaries();
        });
    }

    // ── Personaggi ──────────────────────────────────────────────────────────
    const charGrid = document.getElementById('peCharacterGrid');
    const charOrder = document.getElementById('peCharacterOrder');
    let selectedChars = (data.displayedCharacters || []).map(Number);
    const charById = new Map((data.characters || []).map((c) => [c.id, c]));

    const renderCharacters = () => {
        if (!charGrid || !charOrder) return;
        selectedChars = selectedChars.filter((id) => charById.has(id)).slice(0, 12);
        const query = (document.getElementById('peCharacterSearch')?.value || '').trim().toLowerCase();
        charGrid.innerHTML = '';
        (data.characters || []).forEach((c) => {
            if (query && !c.name.toLowerCase().includes(query)) return;
            const selected = selectedChars.includes(c.id);
            const card = document.createElement('button');
            card.type = 'button';
            card.className = `pe-character rarity-${c.rarity}${selected ? ' is-selected' : ''}`;
            card.setAttribute('aria-pressed', String(selected));
            card.innerHTML = `${c.img ? `<img src="${escape(c.img)}" alt="" loading="lazy">` : '<i class="fa-solid fa-user-astronaut" aria-hidden="true"></i>'}<span></span>${c.qty > 1 ? `<small>×${c.qty}</small>` : ''}<i class="fa-solid fa-check pe-character-check" aria-hidden="true"></i>`;
            card.querySelector('span').textContent = c.name;
            card.title = c.name;
            card.addEventListener('click', () => {
                if (selectedChars.includes(c.id)) selectedChars = selectedChars.filter((id) => id !== c.id);
                else if (selectedChars.length >= 12) { PE.toast(t('Puoi mostrare al massimo 12 personaggi.', 'You can show at most 12 characters.'), { type: 'error' }); return; }
                else selectedChars.push(c.id);
                renderCharacters();
                PE.changed?.({ structural: true });
            });
            charGrid.appendChild(card);
        });
        const count = document.getElementById('peCharacterCount');
        if (count) count.textContent = t(`${selectedChars.length} di 12 selezionati`, `${selectedChars.length} of 12 selected`);

        charOrder.innerHTML = selectedChars.length ? '' : `<p class="pe-empty-inline">${escape(t('Tocca un personaggio qui sopra per mostrarlo.', 'Tap a character above to show it.'))}</p>`;
        selectedChars.forEach((id) => {
            const c = charById.get(id);
            const chip = document.createElement('div');
            chip.className = `pe-order-chip rarity-${c.rarity}`;
            chip.dataset.id = String(id);
            chip.innerHTML = `<span class="pe-drag"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span>${c.img ? `<img src="${escape(c.img)}" alt="">` : '<i class="fa-solid fa-user-astronaut" aria-hidden="true"></i>'}<span></span><button type="button" class="pe-icon-btn pe-icon-btn-sm" aria-label="${escape(t('Togli', 'Remove'))}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>`;
            chip.querySelector('span:not(.pe-drag)').textContent = c.name;
            chip.querySelector('button').addEventListener('click', () => {
                selectedChars = selectedChars.filter((x) => x !== id);
                renderCharacters();
                PE.changed?.({ structural: true });
            });
            charOrder.appendChild(chip);
        });
        PE.refreshSectionSummaries();
    };

    PE.characters = {
        selected: () => selectedChars.slice(),
        set: (ids) => { selectedChars = (ids || []).map(Number); renderCharacters(); },
        render: renderCharacters,
    };
    document.getElementById('peCharacterSearch')?.addEventListener('input', renderCharacters);

    // ── Badge ───────────────────────────────────────────────────────────────
    const badgesEl = document.getElementById('peBadges');
    let badges = (data.badges || []).map((b) => ({ ...b, key: `${b.badge_source}_${b.id}`, selected: Number(b.selected) === 1 }));
    badges.sort((a, b) => (Number(b.selected) - Number(a.selected)) || (Number(a.sort_order) - Number(b.sort_order)));

    const renderBadges = () => {
        if (!badgesEl) return;
        if (!badges.length) {
            badgesEl.innerHTML = `<div class="pe-empty"><i class="fa-solid fa-medal" aria-hidden="true"></i><strong>${escape(t('Nessun badge ancora', 'No badges yet'))}</strong><p>${escape(t('Sbloccali completando gli achievement del sito.', 'Unlock them by completing site achievements.'))}</p></div>`;
            return;
        }
        const max = limits.badges || 1000;
        badgesEl.innerHTML = `<p class="pe-help">${escape(max < 1000 ? t(`Scegli fino a ${max} badge e trascinali per ordinarli.`, `Pick up to ${max} badges and drag to order them.`) : t('Scegli i badge e trascinali per ordinarli.', 'Pick badges and drag to order them.'))}</p><div class="pe-badge-list"></div>`;
        const list = badgesEl.querySelector('.pe-badge-list');
        badges.forEach((b) => {
            const row = document.createElement('label');
            row.className = 'pe-badge' + (b.selected ? ' is-selected' : '');
            row.dataset.key = b.key;
            const img = b.img_url ? (/^https?:/i.test(b.img_url) ? b.img_url : '/img/' + String(b.img_url).replace(/^\//, '')) : '';
            row.innerHTML = `<span class="pe-drag"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span>
                <input type="checkbox"${b.selected ? ' checked' : ''}>
                <span class="pe-badge-art">${img ? `<img src="${escape(img)}" alt="">` : `<i class="${escape(b.icon || 'fa-solid fa-medal')}" style="color:${escape(b.color || 'var(--pe-accent)')}" aria-hidden="true"></i>`}</span>
                <span class="pe-badge-name"></span>`;
            row.querySelector('.pe-badge-name').textContent = (data.lang === 'en' && b.nome_en) ? b.nome_en : b.nome;
            row.querySelector('input').addEventListener('change', (event) => {
                if (event.target.checked && badges.filter((x) => x.selected).length >= max) {
                    event.target.checked = false;
                    if (!data.premium) PE.upsell(t(`Più di ${max} badge`, `More than ${max} badges`));
                    return;
                }
                b.selected = event.target.checked;
                row.classList.toggle('is-selected', b.selected);
                PE.refreshSectionSummaries();
                PE.changed?.({ structural: true });
            });
            list.appendChild(row);
        });
        if (window.Sortable) {
            Sortable.create(list, {
                handle: '.pe-drag', animation: 180, ghostClass: 'is-ghost',
                onEnd: () => {
                    const order = $$('.pe-badge', list).map((el) => el.dataset.key);
                    badges.sort((a, b) => order.indexOf(a.key) - order.indexOf(b.key));
                    PE.changed?.({ structural: true });
                },
            });
        }
    };

    PE.badges = {
        selected: () => badges.filter((b) => b.selected).map((b) => b.key).slice(0, limits.badges || 1000),
        set: (keys) => {
            const set = new Set(keys || []);
            badges.forEach((b) => { b.selected = set.has(b.key); });
            badges.sort((a, b) => {
                const ia = (keys || []).indexOf(a.key);
                const ib = (keys || []).indexOf(b.key);
                return (ia === -1 ? 999 : ia) - (ib === -1 ? 999 : ib);
            });
            renderBadges();
        },
        render: renderBadges,
    };

    // ── Statistiche ─────────────────────────────────────────────────────────
    const statsData = data.stats || { catalog: [], groups: {}, selected: [], limit: 4, limitPremium: 8, explicit: false };
    const statsChosen = document.getElementById('peStatsChosen');
    const statsCatalogEl = document.getElementById('peStatsCatalog');
    const statByKey = new Map((statsData.catalog || []).map((s) => [s.key, s]));
    const statsDefault = (statsData.selected || []).slice();
    let selectedStats = statsDefault.slice();
    // Una scelta mai salvata resta "le quattro di sempre" finche' non la tocchi.
    let statsTouched = !!statsData.explicit;

    const statValueHtml = (s) => (s.value !== null && s.value !== undefined
        ? `<small class="pe-stat-value">${escape(s.value)}</small>`
        : `<small class="pe-stat-value is-empty">${escape(t('Nessun dato', 'No data'))}</small>`);

    const statsChanged = () => {
        statsTouched = true;
        renderStats();
        PE.changed?.({ structural: true });
    };

    const renderStats = () => {
        if (!statsChosen || !statsCatalogEl) return;
        selectedStats = selectedStats.filter((key) => statByKey.has(key)).slice(0, statsData.limit);
        const count = document.getElementById('peStatsCount');
        if (count) {
            const extra = !data.premium && statsData.limitPremium > statsData.limit
                ? t(` · con Premium fino a ${statsData.limitPremium}`, ` · up to ${statsData.limitPremium} with Premium`)
                : '';
            count.textContent = t(`${selectedStats.length} di ${statsData.limit} sul profilo`, `${selectedStats.length} of ${statsData.limit} on your profile`) + extra;
        }

        statsChosen.innerHTML = selectedStats.length
            ? ''
            : `<p class="pe-empty-inline">${escape(t('Nessuna statistica: il box non comparirà sul profilo.', 'No stats: the box will not appear on your profile.'))}</p>`;
        selectedStats.forEach((key) => {
            const s = statByKey.get(key);
            const chip = document.createElement('div');
            chip.className = 'pe-stat-chip';
            chip.dataset.key = key;
            chip.innerHTML = `<span class="pe-drag" title="${escape(t('Trascina per spostare', 'Drag to move'))}"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span>
                <span class="pe-stat-icon"><i class="${escape(s.icon)}" aria-hidden="true"></i></span>
                <span class="pe-stat-text"><span class="pe-stat-label"></span>${statValueHtml(s)}</span>
                <button type="button" class="pe-icon-btn pe-icon-btn-sm" aria-label="${escape(t('Togli', 'Remove'))}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>`;
            chip.querySelector('.pe-stat-label').textContent = s.label;
            chip.querySelector('button').addEventListener('click', () => {
                selectedStats = selectedStats.filter((x) => x !== key);
                statsChanged();
            });
            statsChosen.appendChild(chip);
        });

        statsCatalogEl.innerHTML = '';
        Object.entries(statsData.groups || {}).forEach(([groupKey, groupLabel]) => {
            const items = (statsData.catalog || []).filter((s) => s.group === groupKey);
            if (!items.length) return;
            const group = document.createElement('div');
            group.className = 'pe-stats-group';
            group.innerHTML = `<h5></h5><div class="pe-stats-options"></div>`;
            group.querySelector('h5').textContent = groupLabel;
            const options = group.querySelector('.pe-stats-options');
            items.forEach((s) => {
                const selected = selectedStats.includes(s.key);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pe-stat-option' + (selected ? ' is-selected' : '');
                btn.setAttribute('aria-pressed', String(selected));
                btn.innerHTML = `<span class="pe-stat-icon"><i class="${escape(s.icon)}" aria-hidden="true"></i></span>
                    <span class="pe-stat-text"><span class="pe-stat-label"></span>${statValueHtml(s)}</span>
                    <i class="fa-solid ${selected ? 'fa-check' : 'fa-plus'} pe-stat-toggle" aria-hidden="true"></i>`;
                btn.querySelector('.pe-stat-label').textContent = s.label;
                btn.addEventListener('click', () => {
                    if (selectedStats.includes(s.key)) {
                        selectedStats = selectedStats.filter((x) => x !== s.key);
                    } else if (selectedStats.length >= statsData.limit) {
                        if (!data.premium && statsData.limitPremium > statsData.limit) {
                            PE.upsell(t(`Più di ${statsData.limit} statistiche`, `More than ${statsData.limit} stats`));
                        } else {
                            PE.toast(t(`Puoi mostrare al massimo ${statsData.limit} statistiche: togline una prima.`, `You can show at most ${statsData.limit} stats: remove one first.`), { type: 'error' });
                        }
                        return;
                    } else {
                        selectedStats.push(s.key);
                    }
                    statsChanged();
                });
                options.appendChild(btn);
            });
            statsCatalogEl.appendChild(group);
        });
        PE.refreshSectionSummaries();
    };

    PE.stats = {
        selected: () => selectedStats.slice(),
        /** Valore del campo: vuoto finche' la scelta di sempre non viene toccata. */
        value: () => (statsTouched ? JSON.stringify(selectedStats) : ''),
        set: (value) => {
            if (value === '' || value === null || value === undefined) {
                selectedStats = statsDefault.slice();
                statsTouched = !!statsData.explicit;
            } else {
                selectedStats = Array.isArray(value) ? value.slice() : [];
                statsTouched = true;
            }
            renderStats();
        },
        render: renderStats,
    };

    // ── Avvio ───────────────────────────────────────────────────────────────
    PE.initItems = () => {
        Object.keys(TYPES).forEach((typeKey) => PE.items.load(typeKey, (data.items || {})[typeKey] || []));
        renderCharacters();
        renderBadges();
        renderStats();

        if (window.Sortable) {
            if (statsChosen) {
                Sortable.create(statsChosen, {
                    handle: '.pe-drag', animation: 180, ghostClass: 'is-ghost', draggable: '.pe-stat-chip',
                    onEnd: () => {
                        selectedStats = $$('.pe-stat-chip', statsChosen).map((el) => el.dataset.key);
                        statsTouched = true;
                        PE.changed?.({ structural: true });
                    },
                });
            }
            $$('.pe-items').forEach((list) => Sortable.create(list, {
                handle: '.pe-drag', animation: 180, ghostClass: 'is-ghost', draggable: '.pe-item',
                onEnd: () => PE.changed?.({ structural: true }),
            }));
            if (sectionsEl) {
                Sortable.create(sectionsEl, {
                    handle: '.pe-section-head > .pe-drag', animation: 180, ghostClass: 'is-ghost', draggable: '.pe-section',
                    onEnd: () => PE.changed?.({ structural: true }),
                });
            }
            if (charOrder) {
                Sortable.create(charOrder, {
                    handle: '.pe-drag', animation: 180, ghostClass: 'is-ghost', draggable: '.pe-order-chip',
                    onEnd: () => {
                        selectedChars = $$('.pe-order-chip', charOrder).map((el) => Number(el.dataset.id));
                        PE.changed?.({ structural: true });
                    },
                });
            }
        }
        PE.refreshSectionSummaries();
    };
})();
