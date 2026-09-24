/*
 * Pannello admin: Negozio, Merch (a collezioni), Shop Gacha e Download.
 *
 * Usa gli strumenti che admin.js espone in window.CripsumAdmin (api,
 * modali, toast, riordino trascinando) e si registra come quattro sezioni
 * della sidebar. Le API stanno in /api/admin/shop_*.php.
 *
 * Ogni lista funziona allo stesso modo: interruttore acceso/spento nella
 * riga, riordino trascinando (o con le frecce, che sul telefono il
 * trascinamento non c'e'), Modifica in una finestra, Elimina con conferma.
 */
(() => {
    'use strict';

    const A = window.CripsumAdmin;
    if (!A) return;

    const { api, openModal, closeModal, confirmBox, showToast, thumb, setLoading, emptyState, enableRowDrag } = A;
    const e = A.escapeHtml;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const isOwner = A.role === 'owner';

    const EP = {
        catalog: 'shop_catalog.php',
        content: 'shop_content.php',
        downloads: 'shop_downloads.php',
        gacha: 'shop_gacha.php',
    };

    const get = (endpoint, params = {}) => api(`${endpoint}?${new URLSearchParams(params)}`);
    const post = (endpoint, action, body = {}) => api(endpoint, { method: 'POST', body: { action, ...body } });

    const money = (value) => Number(value || 0).toLocaleString('it-IT', { style: 'currency', currency: 'EUR' });
    const num = (value) => Number(value || 0).toLocaleString('it-IT');

    // La ricerca in alto nel pannello filtra anche le liste dello shop.
    const matches = (...texts) => {
        const q = String(A.getQuery() || '').toLowerCase().trim();
        return !q || texts.some((t) => String(t || '').toLowerCase().includes(q));
    };

    /* ── Campi dei form ──────────────────────────────────────────────── */

    const field = (f, values = {}) => {
        const raw = values[f.name] ?? f.value ?? '';
        const value = raw === null ? '' : raw;
        const id = `shopf-${f.name}-${Math.random().toString(36).slice(2, 7)}`;
        const cls = `admin-field${f.full ? ' admin-field--full' : ''}`;
        const help = f.help ? `<small class="shop-admin-help">${f.help}</small>` : '';
        const attrs = [
            f.required ? 'required' : '',
            f.max ? `maxlength="${f.max}"` : '',
            f.placeholder ? `placeholder="${e(f.placeholder)}"` : '',
            f.readonly ? 'readonly' : '',
            f.min !== undefined ? `min="${f.min}"` : '',
            f.step ? `step="${f.step}"` : '',
            f.inputmode ? `inputmode="${f.inputmode}"` : '',
        ].filter(Boolean).join(' ');
        const show = f.showWhen ? ` data-show-when="${e(f.showWhen)}"` : '';
        const label = `<label for="${id}">${e(f.label)}${f.required ? ' <span class="shop-admin-req">*</span>' : ''}</label>`;

        switch (f.type) {
            case 'textarea':
                return `<div class="${cls}"${show}>${label}<textarea id="${id}" name="${f.name}" rows="${f.rows || 3}" ${attrs}>${e(value)}</textarea>${help}</div>`;
            case 'select':
                return `<div class="${cls}"${show}>${label}<select id="${id}" name="${f.name}" ${attrs}>${(f.options || []).map(([v, l]) =>
                    `<option value="${e(v)}" ${String(v) === String(value) ? 'selected' : ''}>${e(l)}</option>`).join('')}</select>${help}</div>`;
            case 'checkbox': {
                const checked = values[f.name] === undefined ? f.checked !== false : Number(values[f.name]) === 1;
                return `<div class="${cls} shop-admin-check"${show}><label><input type="checkbox" name="${f.name}" value="1" ${checked ? 'checked' : ''}> <span>${e(f.label)}</span></label>${help}</div>`;
            }
            case 'color':
                return `<div class="${cls}"${show}>${label}<div class="shop-admin-color"><input type="color" name="${f.name}" value="${e(value || f.value)}" data-color-picker><input type="text" value="${e(value || f.value)}" data-color-text maxlength="7" aria-label="${e(f.label)} esadecimale"></div>${help}</div>`;
            case 'image':
                return `<div class="${cls}"${show}>${label}
                    <div class="shop-admin-media">
                        <span class="shop-admin-media__preview" data-image-preview>${thumb(value)}</span>
                        <div class="admin-input-group">
                            <input id="${id}" type="text" name="${f.name}" value="${e(value)}" placeholder="/img/foto.jpg oppure https://..." data-image-input>
                            <label class="admin-btn shop-admin-upload"><i class="fa-solid fa-upload"></i> Carica<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-upload-image hidden></label>
                        </div>
                    </div>${help}</div>`;
            default:
                return `<div class="${cls}"${show}>${label}<input id="${id}" type="${f.type || 'text'}" name="${f.name}" value="${e(value)}" ${attrs}>${help}</div>`;
        }
    };

    const fields = (list, values) => list.map((f) => (typeof f === 'string' ? f : field(f, values))).join('');

    const sectionTitle = (text, hint = '') => `<div class="shop-admin-subtitle admin-field--full"><strong>${e(text)}</strong>${hint ? `<small>${hint}</small>` : ''}</div>`;

    const readForm = (form) => {
        const payload = Object.fromEntries(new FormData(form).entries());
        // Una casella non spuntata non compare nel FormData.
        $$('input[type="checkbox"][name]', form).forEach((input) => {
            payload[input.name] = input.checked ? 1 : 0;
        });
        $$('input[type="file"]', form).forEach((input) => delete payload[input.name]);
        return payload;
    };

    const slugify = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

    /*
     * Le immagini finiscono in una cartella di img/ secondo il form da cui
     * si caricano: img/negozio, img/merch/{collezione}, img/download,
     * img/gacha. `folder` puo' essere una funzione: si legge al momento del
     * caricamento, cosi' vale la collezione scelta in quel momento.
     */
    const uploadImage = async (file, folder = null) => {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('type', 'image');
        const target = typeof folder === 'function' ? folder() : folder;
        if (target) fd.append('folder', target);
        const res = await api('upload_media.php', { method: 'POST', body: fd });
        const url = res.url || `/img/${res.filename}`;
        A.trackUpload?.(url);
        return url;
    };

    const folderSlug = (value) => slugify(value).slice(0, 60).replace(/-+$/, '');

    const productFolder = (ctx, form) => {
        const id = form.elements.vetrina_id?.value || ctx.defaultVetrina;
        const vetrina = ctx.vetrine.find((v) => String(v.id) === String(id));
        if (vetrina?.tipo !== 'merch') return 'negozio';
        const slug = folderSlug(vetrina.slug);
        return slug ? `merch/${slug}` : 'merch';
    };

    /**
     * Comportamenti comuni dei form: caricamento immagini con anteprima,
     * colori (selettore e testo sincronizzati), campi che compaiono solo con
     * un certo valore di un altro campo (data-show-when="stato=in_arrivo").
     */
    const bindForm = (form, onChange = null, folder = null) => {
        $$('[data-upload-image]', form).forEach((input) => input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) return;
            const wrap = input.closest('.admin-field');
            const text = $('[data-image-input]', wrap);
            try {
                showToast('Caricamento immagine...');
                text.value = await uploadImage(file, folder);
                text.dispatchEvent(new Event('input', { bubbles: true }));
                showToast('Immagine caricata.');
            } catch (error) {
                showToast(error.message, true);
            } finally {
                input.value = '';
            }
        }));

        $$('[data-image-input]', form).forEach((input) => input.addEventListener('input', () => {
            const preview = $('[data-image-preview]', input.closest('.admin-field'));
            if (preview) preview.innerHTML = thumb(input.value);
        }));

        $$('[data-color-picker]', form).forEach((picker) => {
            const text = $('[data-color-text]', picker.parentElement);
            picker.addEventListener('input', () => { text.value = picker.value; });
            text.addEventListener('input', () => {
                if (/^#[0-9a-f]{6}$/i.test(text.value)) {
                    picker.value = text.value;
                    picker.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        });

        const conditional = $$('[data-show-when]', form);
        const refresh = () => {
            conditional.forEach((el) => {
                const [name, wanted] = el.dataset.showWhen.split('=');
                const control = form.elements[name];
                const current = control instanceof RadioNodeList ? control.value : control?.value;
                el.hidden = !wanted.split('|').includes(current);
            });
            onChange?.();
        };
        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        refresh();
    };

    /**
     * Finestra con un form: salva con l'azione indicata e, se va bene,
     * chiude e ricarica la lista.
     */
    const formModal = ({ title, subtitle = '', html, endpoint, action, extra = {}, after, onReady }) => {
        openModal(
            title,
            subtitle,
            `<form class="admin-form-grid shop-admin-form" data-shop-form novalidate>${html}</form>`,
            '<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" data-shop-save><i class="fa-solid fa-floppy-disk"></i> Salva</button>'
        );

        const form = $('#adminModalBody [data-shop-form]');
        const save = $('#adminModalFooter [data-shop-save]');
        if (!form || !save) return;

        onReady?.(form);

        const submit = async () => {
            save.disabled = true;
            try {
                const res = await post(endpoint, action, { ...readForm(form), ...extra });
                closeModal();
                showToast(res.message || 'Salvato.');
                after?.(res);
            } catch (error) {
                showToast(error.message, true);
            } finally {
                save.disabled = false;
            }
        };

        save.addEventListener('click', submit);
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submit();
        });
    };

    /* ── Liste ───────────────────────────────────────────────────────── */

    const toggleSwitch = (checked, attrs, label = 'Attivo') => `
        <label class="shop-admin-switch" title="${e(label)}">
            <input type="checkbox" ${checked ? 'checked' : ''} ${attrs} aria-label="${e(label)}">
            <span></span>
        </label>`;

    const moveButtons = (id, index, last, disabled) => `
        <button class="admin-btn admin-btn--small" data-move="${id}" data-dir="-1" ${disabled || index === 0 ? 'disabled' : ''} title="Sposta su" aria-label="Sposta su"><i class="fa-solid fa-arrow-up"></i></button>
        <button class="admin-btn admin-btn--small" data-move="${id}" data-dir="1" ${disabled || index === last ? 'disabled' : ''} title="Sposta giù" aria-label="Sposta giù"><i class="fa-solid fa-arrow-down"></i></button>`;

    const grip = (enabled) => enabled
        ? '<span class="admin-slide-grip" title="Trascina per riordinare" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>'
        : '';

    /**
     * Riordino di una tabella: trascinando le righe o con le frecce. Si
     * manda sempre l'elenco completo, il server rinumera da capo.
     */
    const bindReorder = (box, ids, endpoint, action, reload) => {
        const save = async (order) => {
            try {
                await post(endpoint, action, { order });
                showToast('Ordine aggiornato.');
            } catch (error) {
                showToast(error.message, true);
            }
            reload();
        };

        enableRowDrag(box, 'data-shop-row', save);

        $$('[data-move]', box).forEach((button) => button.addEventListener('click', () => {
            const order = ids.slice();
            const from = order.indexOf(Number(button.dataset.move));
            const to = from + Number(button.dataset.dir);
            if (from < 0 || to < 0 || to >= order.length) return;
            order.splice(to, 0, order.splice(from, 1)[0]);
            save(order);
        }));
    };

    const tabs = (root, list, active, onChange) => {
        root.innerHTML = `
            <div class="shop-admin-tabs" role="tablist">
                ${list.map(([key, label, icon]) => `<button type="button" role="tab" class="shop-admin-tab ${key === active ? 'is-active' : ''}" data-tab="${key}" aria-selected="${key === active}"><i class="${icon}"></i> ${e(label)}</button>`).join('')}
            </div>
            <div data-tab-body></div>`;
        $$('[data-tab]', root).forEach((button) => button.addEventListener('click', () => onChange(button.dataset.tab)));
        return $('[data-tab-body]', root);
    };

    const missing = (box, message) => {
        box.innerHTML = emptyState('fa-solid fa-database', 'Migrazione da applicare', message || 'Applica migrations/2026_09_23_shop_catalog.sql e ricarica.');
    };

    /* ── FAQ (inline, funziona anche dentro una finestra) ────────────── */

    const faqManager = async (box, scope) => {
        const params = scope.vetrina_id ? { action: 'faq', vetrina_id: scope.vetrina_id } : { action: 'faq', pagina: scope.pagina };
        box.innerHTML = '<p class="admin-muted">Caricamento FAQ...</p>';

        let rows = [];
        try {
            rows = (await get(EP.content, params)).faq || [];
        } catch (error) {
            box.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
            return;
        }

        const reload = () => faqManager(box, scope);

        const card = (row = {}) => `
            <form class="shop-admin-faq__item" data-faq="${Number(row.id || 0)}">
                <div class="admin-form-grid">
                    ${field({ name: 'domanda', label: 'Domanda (IT)', required: true, max: 255 }, row)}
                    ${field({ name: 'domanda_en', label: 'Domanda (EN)', max: 255, placeholder: 'vuoto = usa l\'italiano' }, row)}
                    ${field({ name: 'risposta', label: 'Risposta (IT)', type: 'textarea', required: true, max: 2000, rows: 3 }, row)}
                    ${field({ name: 'risposta_en', label: 'Risposta (EN)', type: 'textarea', max: 2000, rows: 3, placeholder: 'vuoto = usa l\'italiano' }, row)}
                </div>
                <div class="shop-admin-faq__actions">
                    ${field({ name: 'attiva', label: 'Visibile', type: 'checkbox' }, row.id ? row : {})}
                    <span class="shop-admin-spacer"></span>
                    ${row.id ? `<button type="button" class="admin-btn admin-btn--small" data-faq-move="-1" title="Su"><i class="fa-solid fa-arrow-up"></i></button><button type="button" class="admin-btn admin-btn--small" data-faq-move="1" title="Giù"><i class="fa-solid fa-arrow-down"></i></button>` : ''}
                    ${row.id ? '<button type="button" class="admin-btn admin-btn--small admin-btn--danger" data-faq-delete><i class="fa-solid fa-trash"></i> Elimina</button>' : '<button type="button" class="admin-btn admin-btn--small" data-faq-cancel>Annulla</button>'}
                    <button type="submit" class="admin-btn admin-btn--small admin-btn--primary"><i class="fa-solid fa-floppy-disk"></i> ${row.id ? 'Salva' : 'Aggiungi'}</button>
                </div>
            </form>`;

        box.innerHTML = `
            <div class="shop-admin-faq">
                ${rows.length ? rows.map((row) => card(row)).join('') : '<p class="admin-muted">Nessuna domanda: la sezione FAQ non compare sulla pagina.</p>'}
                <div data-faq-new></div>
                <button type="button" class="admin-btn" data-faq-add><i class="fa-solid fa-plus"></i> Aggiungi domanda</button>
            </div>`;

        const ids = rows.map((r) => Number(r.id));

        const bindCard = (form) => {
            const id = Number(form.dataset.faq);
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                try {
                    const res = await post(EP.content, 'save_faq', { ...readForm(form), id, ...scope });
                    showToast(res.message);
                    reload();
                } catch (error) {
                    showToast(error.message, true);
                }
            });

            // Dentro una finestra non si puo' aprire la conferma del
            // pannello: si chiede conferma sullo stesso bottone.
            const del = $('[data-faq-delete]', form);
            del?.addEventListener('click', async () => {
                if (del.dataset.armed !== '1') {
                    del.dataset.armed = '1';
                    del.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Sicuro?';
                    setTimeout(() => {
                        del.dataset.armed = '';
                        del.innerHTML = '<i class="fa-solid fa-trash"></i> Elimina';
                    }, 3000);
                    return;
                }
                try {
                    await post(EP.content, 'delete_faq', { id });
                    showToast('Domanda eliminata.');
                    reload();
                } catch (error) {
                    showToast(error.message, true);
                }
            });

            $$('[data-faq-move]', form).forEach((button) => button.addEventListener('click', async () => {
                const order = ids.slice();
                const from = order.indexOf(id);
                const to = from + Number(button.dataset.faqMove);
                if (from < 0 || to < 0 || to >= order.length) return;
                order.splice(to, 0, order.splice(from, 1)[0]);
                try {
                    await post(EP.content, 'reorder_faq', { order });
                    reload();
                } catch (error) {
                    showToast(error.message, true);
                }
            }));

            $('[data-faq-cancel]', form)?.addEventListener('click', () => form.remove());
        };

        $$('[data-faq]', box).forEach(bindCard);

        $('[data-faq-add]', box).addEventListener('click', () => {
            const slot = $('[data-faq-new]', box);
            if ($('form', slot)) return;
            slot.innerHTML = card();
            bindCard($('form', slot));
            $('textarea, input', slot)?.focus();
        });
    };

    /* ── Testata di una pagina (Download, elenco Merch) ──────────────── */

    const pageTextsForm = async (box, pagina, { withNote = false, withLink = false, hint = '' } = {}) => {
        box.innerHTML = '<p class="admin-muted">Caricamento testi...</p>';
        let page = {};
        try {
            page = (await get(EP.content, { action: 'page', pagina })).page || {};
        } catch (error) {
            box.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
            return;
        }

        box.innerHTML = `
            <form class="admin-form-grid shop-admin-inline" data-page-form>
                ${hint ? `<p class="admin-muted admin-field--full">${hint}</p>` : ''}
                ${fields([
                    { name: 'titolo', label: 'Titolo (IT)', max: 120, required: true },
                    { name: 'titolo_en', label: 'Titolo (EN)', max: 120, placeholder: 'vuoto = usa l\'italiano' },
                    { name: 'sottotitolo', label: 'Sottotitolo (IT)', type: 'textarea', max: 400, rows: 2 },
                    { name: 'sottotitolo_en', label: 'Sottotitolo (EN)', type: 'textarea', max: 400, rows: 2 },
                    ...(withNote ? [
                        sectionTitle('Riquadro informativo', 'Compare sotto la testata. Vuoto = nascosto.'),
                        { name: 'nota_titolo', label: 'Titolo avviso (IT)', max: 120 },
                        { name: 'nota_titolo_en', label: 'Titolo avviso (EN)', max: 120 },
                        { name: 'nota', label: 'Avviso (IT)', type: 'textarea', max: 600, rows: 2 },
                        { name: 'nota_en', label: 'Avviso (EN)', type: 'textarea', max: 600, rows: 2 },
                    ] : []),
                    ...(withLink ? [
                        sectionTitle('Bottone nella testata', 'Facoltativo, per esempio il link al Discord.'),
                        { name: 'link_testo', label: 'Testo bottone (IT)', max: 60 },
                        { name: 'link_testo_en', label: 'Testo bottone (EN)', max: 60 },
                        { name: 'link_url', label: 'Link del bottone', max: 255, full: true, placeholder: 'https://discord.gg/... oppure /it/...' },
                    ] : []),
                ], page)}
                <div class="admin-field--full shop-admin-inline__actions">
                    <button type="submit" class="admin-btn admin-btn--primary"><i class="fa-solid fa-floppy-disk"></i> Salva testi</button>
                </div>
            </form>`;

        const form = $('[data-page-form]', box);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const res = await post(EP.content, 'save_page', { ...readForm(form), pagina });
                showToast(res.message);
            } catch (error) {
                showToast(error.message, true);
            }
        });
    };

    /* ── Vetrine: testata e tema del Negozio e delle collezioni ─────── */

    const PRESETS = [
        ['Viola', '#6d5dfc', '#05070d', '#0b1020'],
        ['Oro', '#ffd23f', '#2e2203', '#9d7606'],
        ['Rosa', '#ff4d9d', '#1a0512', '#3b0a2a'],
        ['Menta', '#24d3a2', '#04100d', '#071c24'],
        ['Blu', '#2f6bff', '#040914', '#0b1a3a'],
        ['Rosso', '#ff4d4d', '#160404', '#3a0b0b'],
        ['Arancio', '#ff9f1c', '#1a0e02', '#4a2a04'],
        ['Ghiaccio', '#8fd3ff', '#06111a', '#12324a'],
    ];

    const vetrinaFields = (v, isMerch) => [
        sectionTitle('Nome e indirizzo'),
        { name: 'nome', label: isMerch ? 'Nome della collezione' : 'Nome', required: true, max: 80, placeholder: isMerch ? 'es. Poppy' : '' },
        isMerch
            ? { name: 'slug', label: 'Indirizzo', max: 60, placeholder: 'vuoto = dal nome', help: 'La pagina sarà <code>/it/merch/<b data-slug-preview>' + e(v.slug || '...') + '</b></code>' }
            : { name: 'nome_en', label: 'Nome (EN)', max: 80, placeholder: 'vuoto = usa l\'italiano' },
        ...(isMerch ? [{ name: 'nome_en', label: 'Nome (EN)', max: 80, placeholder: 'vuoto = usa l\'italiano' }, {
            name: 'stato', label: 'Visibilità', type: 'select', options: [
                ['attiva', 'Aperta: visibile a tutti'],
                ['in_arrivo', 'In arrivo: teaser con conto alla rovescia'],
                ['nascosta', 'Nascosta: la vede solo lo staff'],
            ],
        }] : []),
        ...(isMerch ? [{
            name: 'lancio_at', label: 'Data del drop', type: 'datetime-local', showWhen: 'stato=in_arrivo',
            help: 'Quando arriva, la collezione si apre da sola. Vuota = "in arrivo" senza data.',
        }] : []),
        sectionTitle('Testata della pagina'),
        { name: 'titolo', label: 'Titolo (IT)', max: 120, placeholder: 'vuoto = usa il nome' },
        { name: 'titolo_en', label: 'Titolo (EN)', max: 120, placeholder: 'vuoto = usa l\'italiano' },
        { name: 'sottotitolo', label: 'Sottotitolo (IT)', type: 'textarea', max: 400, rows: 2 },
        { name: 'sottotitolo_en', label: 'Sottotitolo (EN)', type: 'textarea', max: 400, rows: 2 },
        sectionTitle('Immagini', 'Il logo sostituisce l\'emoji nella testata. La copertina appare nell\'elenco delle collezioni: se manca, si usano le foto dei primi prodotti.'),
        { name: 'emoji', label: 'Emoji', max: 32, placeholder: 'es. 🤑🐦📸' },
        { name: 'logo', label: 'Logo', type: 'image' },
        ...(isMerch ? [{ name: 'copertina', label: 'Copertina', type: 'image', full: true }] : []),
        sectionTitle('Colori', 'Testo e bordi si adattano da soli. Parti da un preset e ritocca.'),
        `<div class="admin-field--full shop-admin-presets">${PRESETS.map(([name, a, b1, b2]) =>
            `<button type="button" class="shop-admin-preset" data-preset="${a},${b1},${b2}" style="--a:${a};--b1:${b1};--b2:${b2}"><span></span>${e(name)}</button>`).join('')}</div>`,
        { name: 'colore_accento', label: 'Colore principale', type: 'color', value: '#6d5dfc' },
        { name: 'colore_sfondo', label: 'Sfondo 1', type: 'color', value: '#05070d' },
        { name: 'colore_sfondo_2', label: 'Sfondo 2', type: 'color', value: '#0b1020' },
        '<div class="admin-field admin-field--full"><label>Anteprima</label><div class="shop-admin-theme" data-theme-preview></div></div>',
    ];

    const bindVetrinaForm = (form) => {
        const preview = $('[data-theme-preview]', form);
        const slugPreview = $('[data-slug-preview]', form);

        const draw = () => {
            const val = (name) => form.elements[name]?.value || '';
            const accent = val('colore_accento') || '#6d5dfc';
            if (preview) {
                preview.style.setProperty('--a', accent);
                preview.style.setProperty('--b1', val('colore_sfondo') || '#05070d');
                preview.style.setProperty('--b2', val('colore_sfondo_2') || '#0b1020');
                const light = (() => {
                    const hex = accent.replace('#', '');
                    const [r, g, b] = [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16));
                    return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.62;
                })();
                preview.style.setProperty('--on', light ? '#161000' : '#ffffff');
                preview.innerHTML = `
                    <strong>${e(val('titolo') || val('nome') || 'Titolo')}</strong>
                    <small>${e(val('sottotitolo') || 'Sottotitolo della vetrina')}</small>
                    <span class="shop-admin-theme__btn">Acquista</span>`;
            }
            if (slugPreview) {
                slugPreview.textContent = slugify(val('slug') || val('nome')) || '...';
            }
        };

        $$('[data-preset]', form).forEach((button) => button.addEventListener('click', () => {
            const [a, b1, b2] = button.dataset.preset.split(',');
            [['colore_accento', a], ['colore_sfondo', b1], ['colore_sfondo_2', b2]].forEach(([name, value]) => {
                const picker = form.elements[name];
                picker.value = value;
                const text = $('[data-color-text]', picker.parentElement);
                if (text) text.value = value;
            });
            draw();
        }));

        // Il Negozio non ha il campo indirizzo: e' l'unica vetrina senza slug.
        const folder = () => {
            if (!form.elements.slug) return 'negozio';
            const slug = folderSlug(form.elements.slug.value || form.elements.nome?.value);
            return slug ? `merch/${slug}` : 'merch';
        };

        bindForm(form, draw, folder);
    };

    const toLocalInput = (value) => (value ? String(value).replace(' ', 'T').slice(0, 16) : '');

    /* ── Prodotti (Negozio e Merch) ──────────────────────────────────── */

    const productPreview = (form) => {
        const box = $('[data-product-preview]', form);
        if (!box) return;
        const val = (name) => form.elements[name]?.value?.trim() || '';
        const price = val('prezzo');
        const full = val('prezzo_pieno');
        const image = A.assetUrl(val('immagine'));
        box.innerHTML = `
            <div class="shop-admin-card">
                <div class="shop-admin-card__media">
                    ${image ? `<img src="${e(image)}" alt="">` : '<i class="fa-solid fa-box-open"></i>'}
                    ${val('badge') ? `<span>${e(val('badge'))}</span>` : ''}
                    ${form.elements.in_evidenza?.checked ? '<em><i class="fa-solid fa-star"></i></em>' : ''}
                </div>
                <div class="shop-admin-card__body">
                    <strong>${e(val('nome') || 'Nome del prodotto')}</strong>
                    ${val('variante') ? `<small>${e(val('variante'))}</small>` : ''}
                    <p>${e(val('descrizione') || 'Descrizione breve')}</p>
                    <div>${full ? `<s>${e(full)} €</s>` : ''}<b>${e(price || '0,00')} €</b></div>
                </div>
            </div>`;
    };

    /* Indirizzo della pagina di un prodotto, per i link del pannello. */
    const productPath = (ctx, item) => {
        const vetrina = ctx.vetrine.find((v) => Number(v.id) === Number(item.vetrina_id)) || ctx.vetrine[0];
        const slug = item.slug || 'nome-del-prodotto';
        return vetrina && vetrina.tipo === 'merch'
            ? `/it/merch/${vetrina.slug}/${slug}`
            : `/it/negozio/${slug}`;
    };

    /*
     * Altre foto: miniature da riordinare o togliere, piu' "Aggiungi foto"
     * (anche piu' file insieme) o un percorso/link incollato. Il valore vero
     * sta nel textarea nascosto "galleria", una foto per riga, come lo legge
     * il server. Una foto tolta si cancella dal disco quando si salva, se
     * nessun altro prodotto la usa.
     */
    const GALLERY_MAX = 12;

    const galleryField = (values) => `
        <div class="admin-field admin-field--full" data-gallery-field>
            <label>Altre foto <span class="shop-admin-gallery__count" data-gallery-count></span></label>
            <ul class="shop-admin-gallery" data-gallery-list></ul>
            <div class="shop-admin-gallery__tools">
                <label class="admin-btn shop-admin-upload" data-gallery-upload><i class="fa-solid fa-images"></i> Aggiungi foto<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple data-upload-gallery hidden></label>
                <div class="admin-input-group shop-admin-gallery__link">
                    <input type="text" placeholder="oppure incolla un percorso /img/... o un link https" aria-label="Percorso o link di una foto" data-gallery-link>
                    <button type="button" class="admin-btn" data-gallery-add><i class="fa-solid fa-plus"></i> Aggiungi</button>
                </div>
            </div>
            <textarea name="galleria" hidden>${e(values.galleria_testo || '')}</textarea>
            <small class="shop-admin-help">Nella pagina del prodotto sono le miniature sotto la foto principale, in quest'ordine. Le foto tolte si cancellano dal sito quando salvi.</small>
        </div>`;

    const bindGalleryField = (form, folder = null) => {
        const wrap = $('[data-gallery-field]', form);
        if (!wrap) return;
        const area = $('textarea[name="galleria"]', wrap);
        const list = $('[data-gallery-list]', wrap);
        const count = $('[data-gallery-count]', wrap);
        const upload = $('[data-upload-gallery]', wrap);
        const uploadLabel = $('[data-gallery-upload]', wrap);
        const link = $('[data-gallery-link]', wrap);
        const addButton = $('[data-gallery-add]', wrap);

        const photos = area.value.split('\n').map((line) => line.trim()).filter(Boolean);

        const item = (url, index) => `
            <li class="shop-admin-gallery__item">
                ${thumb(url)}
                <span class="shop-admin-gallery__index">${index + 1}</span>
                <div class="shop-admin-gallery__actions">
                    <button type="button" data-index="${index}" data-gallery-move="-1" title="Sposta prima" aria-label="Sposta prima la foto ${index + 1}" ${index === 0 ? 'disabled' : ''}><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="is-danger" data-index="${index}" data-gallery-remove title="Togli" aria-label="Togli la foto ${index + 1}"><i class="fa-solid fa-xmark"></i></button>
                    <button type="button" data-index="${index}" data-gallery-move="1" title="Sposta dopo" aria-label="Sposta dopo la foto ${index + 1}" ${index === photos.length - 1 ? 'disabled' : ''}><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </li>`;

        const sync = () => {
            area.value = photos.join('\n');
            const full = photos.length >= GALLERY_MAX;
            count.textContent = photos.length ? `${photos.length}/${GALLERY_MAX}` : '';
            list.innerHTML = photos.map(item).join('');
            list.hidden = photos.length === 0;
            upload.disabled = full;
            uploadLabel.classList.toggle('is-disabled', full);
            link.disabled = full;
            addButton.disabled = full;
        };

        const add = (url) => {
            if (!url || photos.includes(url) || photos.length >= GALLERY_MAX) return false;
            photos.push(url);
            return true;
        };

        list.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-index]');
            if (!button || button.disabled) return;
            const index = Number(button.dataset.index);
            let focusIndex;
            let focusSelector;

            if (button.hasAttribute('data-gallery-remove')) {
                photos.splice(index, 1);
                focusIndex = Math.min(index, photos.length - 1);
                focusSelector = '[data-gallery-remove]';
            } else {
                const direction = Number(button.dataset.galleryMove);
                const to = index + direction;
                if (to < 0 || to >= photos.length) return;
                [photos[index], photos[to]] = [photos[to], photos[index]];
                focusIndex = to;
                focusSelector = `[data-gallery-move="${direction}"]`;
            }

            sync();
            // Il fuoco resta sulla foto appena spostata (o su quella che ha
            // preso il posto di quella tolta): con la tastiera si continua.
            const buttons = focusIndex >= 0 ? $$(`[data-index="${focusIndex}"]`, list) : [];
            const target = buttons.find((b) => b.matches(focusSelector) && !b.disabled)
                || buttons.find((b) => b.hasAttribute('data-gallery-remove'));
            (target || link).focus();
        });

        upload.addEventListener('change', async (event) => {
            const files = Array.from(event.target.files || []);
            event.target.value = '';
            let added = 0;
            for (const file of files) {
                if (photos.length >= GALLERY_MAX) {
                    showToast(`Al massimo ${GALLERY_MAX} foto: le altre non sono state caricate.`, true);
                    break;
                }
                try {
                    showToast(`Caricamento ${file.name}...`);
                    if (add(await uploadImage(file, folder))) added++;
                    sync();
                } catch (error) {
                    showToast(error.message, true);
                }
            }
            if (added) showToast(added === 1 ? 'Foto aggiunta.' : `${added} foto aggiunte.`);
        });

        const addLink = () => {
            const value = link.value.trim();
            if (!value) return;
            if (!/^(\/|https?:\/\/)/i.test(value)) {
                showToast('Usa un percorso del sito (/img/...) o un indirizzo https.', true);
                return;
            }
            if (!add(value)) {
                showToast(photos.includes(value) ? 'Questa foto c\'è già.' : `Al massimo ${GALLERY_MAX} foto.`, true);
                return;
            }
            link.value = '';
            sync();
        };

        addButton.addEventListener('click', addLink);
        link.addEventListener('keydown', (event) => {
            // Invio aggiunge la foto invece di salvare il form.
            if (event.key === 'Enter') {
                event.preventDefault();
                addLink();
            }
        });

        sync();
    };

    const productForm = (tipo, ctx, item = null, reload) => {
        const isMerch = tipo === 'merch';
        const values = item ? { ...item } : { attivo: 1, vetrina_id: ctx.defaultVetrina };
        if (item) {
            values.prezzo = Number(item.prezzo).toFixed(2).replace('.', ',');
            values.prezzo_pieno = item.prezzo_pieno ? Number(item.prezzo_pieno).toFixed(2).replace('.', ',') : '';
        }

        const html = `
            <div class="admin-field--full shop-admin-split">
                <div class="admin-form-grid">
                    ${fields([
                        ...(isMerch ? [{ name: 'vetrina_id', label: 'Collezione', type: 'select', required: true, options: ctx.vetrine.map((v) => [v.id, v.nome]) }] : []),
                        { name: 'categoria_id', label: 'Categoria', type: 'select', options: [['', '— nessuna —'], ...ctx.categorie.map((c) => [c.id, c.nome])] },
                        { name: 'nome', label: 'Nome (IT)', required: true, max: 120 },
                        { name: 'nome_en', label: 'Nome (EN)', max: 120, placeholder: 'vuoto = usa l\'italiano' },
                        { name: 'variante', label: 'Variante (IT)', max: 80, placeholder: 'es. Big logo' },
                        { name: 'variante_en', label: 'Variante (EN)', max: 80 },
                        { name: 'prezzo', label: 'Prezzo (€)', required: true, placeholder: '19,99', inputmode: 'decimal' },
                        { name: 'prezzo_pieno', label: 'Prezzo pieno (€)', placeholder: 'vuoto = niente sconto', inputmode: 'decimal', help: 'Se è più alto del prezzo, appare barrato.' },
                    ], values)}
                </div>
                <div class="shop-admin-preview-wrap"><span class="shop-admin-help">Anteprima della card</span><div data-product-preview></div></div>
            </div>
            ${fields([
                { name: 'descrizione', label: 'Descrizione (IT)', type: 'textarea', max: 600, rows: 3 },
                { name: 'descrizione_en', label: 'Descrizione (EN)', type: 'textarea', max: 600, rows: 3, placeholder: 'vuoto = usa l\'italiano' },
                { name: 'badge', label: 'Badge (IT)', max: 40, placeholder: 'es. Drop, -40%, Limited' },
                { name: 'badge_en', label: 'Badge (EN)', max: 40 },
                { name: 'immagine', label: 'Immagine principale', type: 'image', full: true },
                ...(ctx.details ? [galleryField(values)] : []),
                { name: 'taglie', label: 'Taglie', max: 120, placeholder: isMerch ? 'es. S, M, L, XL' : 'vuoto = niente taglie', help: 'Separate da virgola. Si scelgono nella pagina del prodotto e nel checkout.' },
                ...(ctx.details ? [
                    sectionTitle('Pagina del prodotto', 'Si apre cliccando la card. La descrizione breve qui sopra resta sulla card e in cima alla pagina.'),
                    { name: 'descrizione_lunga', label: 'Descrizione completa (IT)', type: 'textarea', max: 5000, rows: 5 },
                    { name: 'descrizione_lunga_en', label: 'Descrizione completa (EN)', type: 'textarea', max: 5000, rows: 5, placeholder: 'vuoto = usa l\'italiano' },
                    { name: 'specifiche_it', label: 'Specifiche (IT)', type: 'textarea', rows: 4, placeholder: 'Materiale: 100% poliestere\nPeso: 180 g', help: 'Una riga per voce, «Etichetta: valore». Categoria, variante e taglie si aggiungono da sole.' },
                    { name: 'specifiche_en', label: 'Specifiche (EN)', type: 'textarea', rows: 4, placeholder: 'Material: 100% polyester\nWeight: 180 g' },
                ] : ['<p class="shop-admin-note admin-field--full"><i class="fa-solid fa-circle-info"></i> Applica migrations/2026_09_23b_shop_prodotti_dettagli.sql per aggiungere descrizione completa, specifiche e altre foto alla pagina del prodotto.</p>']),
                { name: 'slug', label: 'Indirizzo della pagina', max: 80, placeholder: 'vuoto = dal nome', help: 'La pagina sarà <code>' + e(productPath(ctx, values)) + '</code>' },
                { name: 'attivo', label: 'In vendita', type: 'checkbox' },
                { name: 'in_evidenza', label: 'In evidenza (stella, in cima alla lista)', type: 'checkbox', checked: false },
            ], values)}`;

        formModal({
            title: item ? 'Modifica prodotto' : 'Nuovo prodotto',
            subtitle: item ? `#${item.id} · ${item.ordini_finti || 0} ordini finti` : 'Finisce in fondo alla lista, poi lo sposti dove vuoi',
            html,
            endpoint: EP.catalog,
            action: 'save_product',
            extra: { id: item?.id || 0, ...(isMerch ? {} : { vetrina_id: ctx.vetrine[0]?.id }) },
            after: reload,
            onReady: (form) => {
                const folder = () => productFolder(ctx, form);
                bindGalleryField(form, folder);
                bindForm(form, () => productPreview(form), folder);
            },
        });
    };

    const categoryForm = (tipo, item, reload) => formModal({
        title: item ? 'Modifica categoria' : 'Nuova categoria',
        subtitle: 'Le categorie diventano i filtri della pagina',
        html: fields([
            { name: 'nome', label: 'Nome (IT)', required: true, max: 60 },
            { name: 'nome_en', label: 'Nome (EN)', max: 60, placeholder: 'vuoto = usa l\'italiano' },
            { name: 'slug', label: 'Codice', max: 60, placeholder: 'vuoto = dal nome', help: 'Compare nell\'indirizzo quando si filtra: <code>?cat=codice</code>.' },
        ], item || {}),
        endpoint: EP.catalog,
        action: 'save_category',
        extra: { id: item?.id || 0, tipo },
        after: reload,
    });

    const productsTable = (box, tipo, ctx, reload, filters) => {
        const byId = new Map(ctx.vetrine.map((v) => [Number(v.id), v]));
        const catById = new Map(ctx.categorie.map((c) => [Number(c.id), c]));

        let rows = ctx.prodotti.filter((p) => matches(p.nome, p.nome_en, p.variante, p.slug));
        if (filters.vetrina) rows = rows.filter((p) => Number(p.vetrina_id) === Number(filters.vetrina));
        if (filters.stato === 'on') rows = rows.filter((p) => Number(p.attivo) === 1);
        if (filters.stato === 'off') rows = rows.filter((p) => Number(p.attivo) !== 1);
        if (filters.cat) rows = rows.filter((p) => String(p.categoria_id || '') === String(filters.cat));

        // Si riordina solo guardando una vetrina intera, senza altri filtri:
        // spostare un prodotto dentro una lista filtrata non avrebbe senso.
        const canSort = (tipo === 'negozio' || filters.vetrina) && !filters.stato && !filters.cat && !A.getQuery();
        const ids = rows.map((p) => Number(p.id));
        const last = rows.length - 1;

        if (!rows.length) {
            box.innerHTML = emptyState('fa-solid fa-box-open', 'Nessun prodotto', ctx.prodotti.length ? 'Nessun risultato con questi filtri.' : 'Crea il primo con «Nuovo prodotto».');
            return;
        }

        box.innerHTML = `
            ${canSort ? '' : '<p class="shop-admin-hint"><i class="fa-solid fa-circle-info"></i> Per riordinare, togli i filtri' + (tipo === 'merch' ? ' e scegli una collezione' : '') + '.</p>'}
            <table class="admin-table">
                <thead><tr><th>Prodotto</th>${tipo === 'merch' && !filters.vetrina ? '<th>Collezione</th>' : ''}<th>Categoria</th><th>Prezzo</th><th>Ordini</th><th>In vendita</th><th>Azioni</th></tr></thead>
                <tbody>
                    ${rows.map((p, i) => `
                        <tr ${canSort ? `draggable="true" data-shop-row="${Number(p.id)}"` : ''} class="${Number(p.attivo) === 1 ? '' : 'is-off'}">
                            <td data-label="Prodotto">
                                <div class="admin-name-cell">
                                    ${grip(canSort)}
                                    ${thumb(p.immagine, 'fa-solid fa-box-open')}
                                    <div>
                                        <div class="admin-row-title">${Number(p.in_evidenza) === 1 ? '<i class="fa-solid fa-star shop-admin-star" title="In evidenza"></i> ' : ''}${e(p.nome)}</div>
                                        <div class="admin-row-sub">${e([p.variante, p.badge ? `badge «${p.badge}»` : ''].filter(Boolean).join(' · ') || p.slug)}</div>
                                        <div class="admin-row-sub"><a href="${e(productPath(ctx, p))}" target="_blank" rel="noopener" title="Apri la pagina del prodotto">${e(productPath(ctx, p))} <i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>
                                    </div>
                                </div>
                            </td>
                            ${tipo === 'merch' && !filters.vetrina ? `<td data-label="Collezione">${e(byId.get(Number(p.vetrina_id))?.nome || '—')}</td>` : ''}
                            <td data-label="Categoria">${e(catById.get(Number(p.categoria_id))?.nome || '—')}</td>
                            <td data-label="Prezzo" class="admin-nowrap">${p.prezzo_pieno ? `<s class="admin-muted">${money(p.prezzo_pieno)}</s><br>` : ''}<b>${money(p.prezzo)}</b></td>
                            <td data-label="Ordini">${num(p.ordini_finti)}</td>
                            <td data-label="In vendita">${toggleSwitch(Number(p.attivo) === 1, `data-toggle-product="${Number(p.id)}"`, 'In vendita')}</td>
                            <td data-label="Azioni">
                                <div class="admin-row-actions admin-row-actions--slides">
                                    ${canSort ? moveButtons(Number(p.id), i, last, false) : ''}
                                    <button class="admin-btn admin-btn--small" data-edit-product="${Number(p.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                                    <button class="admin-btn admin-btn--small" data-duplicate-product="${Number(p.id)}" title="Duplica"><i class="fa-solid fa-clone"></i></button>
                                    <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-product="${Number(p.id)}" title="Elimina"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>`).join('')}
                </tbody>
            </table>`;

        const find = (id) => ctx.prodotti.find((p) => Number(p.id) === Number(id));

        $$('[data-toggle-product]', box).forEach((input) => input.addEventListener('change', async () => {
            try {
                await post(EP.catalog, 'toggle_product', { id: input.dataset.toggleProduct, field: 'attivo', value: input.checked ? 1 : 0 });
                showToast(input.checked ? 'Prodotto in vendita.' : 'Prodotto nascosto.');
                reload();
            } catch (error) {
                input.checked = !input.checked;
                showToast(error.message, true);
            }
        }));

        $$('[data-edit-product]', box).forEach((b) => b.addEventListener('click', () => productForm(tipo, ctx, find(b.dataset.editProduct), reload)));

        $$('[data-duplicate-product]', box).forEach((b) => b.addEventListener('click', async () => {
            try {
                const res = await post(EP.catalog, 'duplicate_product', { id: b.dataset.duplicateProduct });
                showToast(res.message);
                reload();
            } catch (error) {
                showToast(error.message, true);
            }
        }));

        $$('[data-delete-product]', box).forEach((b) => b.addEventListener('click', () => {
            const p = find(b.dataset.deleteProduct);
            confirmBox('Eliminare il prodotto?', `<p class="admin-muted">«${e(p?.nome || '')}» sparisce dalla pagina. Se vuoi solo toglierlo per un po', spegni l'interruttore «In vendita».</p>`, async () => {
                await post(EP.catalog, 'delete_product', { id: p.id });
                showToast('Prodotto eliminato.');
                reload();
            });
        }));

        if (canSort) bindReorder(box, ids, EP.catalog, 'reorder_products', reload);
    };

    const categoriesTable = (box, tipo, ctx, reload) => {
        const rows = ctx.categorie;
        const last = rows.length - 1;

        box.innerHTML = `
            <div class="shop-admin-bar">
                <p class="admin-muted">Le categorie sono i filtri della pagina${tipo === 'merch' ? ', condivise da tutte le collezioni: ogni collezione mostra solo quelle in cui ha prodotti' : ''}.</p>
                <button type="button" class="admin-btn admin-btn--primary" data-new-category><i class="fa-solid fa-plus"></i> Nuova categoria</button>
            </div>
            ${rows.length ? `
            <table class="admin-table">
                <thead><tr><th>Categoria</th><th>Codice</th><th>Prodotti</th><th>Azioni</th></tr></thead>
                <tbody>
                    ${rows.map((c, i) => `
                        <tr draggable="true" data-shop-row="${Number(c.id)}">
                            <td data-label="Categoria"><div class="admin-name-cell">${grip(true)}<div><div class="admin-row-title">${e(c.nome)}</div><div class="admin-row-sub">${e(c.nome_en || '—')}</div></div></div></td>
                            <td data-label="Codice"><code>${e(c.slug)}</code></td>
                            <td data-label="Prodotti">${num(c.prodotti)}</td>
                            <td data-label="Azioni"><div class="admin-row-actions admin-row-actions--slides">
                                ${moveButtons(Number(c.id), i, last, false)}
                                <button class="admin-btn admin-btn--small" data-edit-category="${Number(c.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                                <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-category="${Number(c.id)}" title="Elimina"><i class="fa-solid fa-trash"></i></button>
                            </div></td>
                        </tr>`).join('')}
                </tbody>
            </table>` : emptyState('fa-solid fa-tags', 'Nessuna categoria', 'Senza categorie la pagina non mostra i filtri.')}`;

        const find = (id) => rows.find((c) => Number(c.id) === Number(id));
        $('[data-new-category]', box).addEventListener('click', () => categoryForm(tipo, null, reload));
        $$('[data-edit-category]', box).forEach((b) => b.addEventListener('click', () => categoryForm(tipo, find(b.dataset.editCategory), reload)));
        $$('[data-delete-category]', box).forEach((b) => b.addEventListener('click', () => {
            const c = find(b.dataset.deleteCategory);
            confirmBox('Eliminare la categoria?', `<p class="admin-muted">«${e(c.nome)}» sparisce dai filtri. I suoi ${num(c.prodotti)} prodotti restano, senza categoria.</p>`, async () => {
                await post(EP.catalog, 'delete_category', { id: c.id });
                showToast('Categoria eliminata.');
                reload();
            });
        }));
        if (rows.length) bindReorder(box, rows.map((c) => Number(c.id)), EP.catalog, 'reorder_categories', reload);
    };

    const productsToolbar = (tipo, ctx, filters, onChange, onNew) => `
        <div class="shop-admin-bar">
            <div class="admin-toolbar-actions">
                ${tipo === 'merch' ? `<select class="admin-input" data-filter="vetrina" aria-label="Collezione">
                    <option value="">Tutte le collezioni</option>
                    ${ctx.vetrine.map((v) => `<option value="${Number(v.id)}" ${String(filters.vetrina) === String(v.id) ? 'selected' : ''}>${e(v.nome)} (${num(v.prodotti)})</option>`).join('')}
                </select>` : ''}
                <select class="admin-input" data-filter="cat" aria-label="Categoria">
                    <option value="">Tutte le categorie</option>
                    ${ctx.categorie.map((c) => `<option value="${Number(c.id)}" ${String(filters.cat) === String(c.id) ? 'selected' : ''}>${e(c.nome)}</option>`).join('')}
                </select>
                <select class="admin-input" data-filter="stato" aria-label="Stato">
                    <option value="">In vendita e spenti</option>
                    <option value="on" ${filters.stato === 'on' ? 'selected' : ''}>Solo in vendita</option>
                    <option value="off" ${filters.stato === 'off' ? 'selected' : ''}>Solo spenti</option>
                </select>
            </div>
            <button type="button" class="admin-btn admin-btn--primary" data-new-product ${tipo === 'merch' && !ctx.vetrine.length ? 'disabled title="Crea prima una collezione"' : ''}><i class="fa-solid fa-plus"></i> Nuovo prodotto</button>
        </div>
        <div data-products-table></div>`;

    /* ── Sezione Negozio ─────────────────────────────────────────────── */

    const negozio = { tab: 'prodotti', filters: { cat: '', stato: '' } };

    const loadNegozio = async () => {
        const root = $('[data-shop-admin="negozio"]');
        if (!root) return;

        const body = tabs(root, [
            ['prodotti', 'Prodotti', 'fa-solid fa-box-open'],
            ['categorie', 'Categorie', 'fa-solid fa-tags'],
            ['testata', 'Testata, colori e FAQ', 'fa-solid fa-pen-ruler'],
        ], negozio.tab, (tab) => { negozio.tab = tab; loadNegozio(); });

        setLoading(body);
        let ctx;
        try {
            ctx = await get(EP.catalog, { action: 'list', tipo: 'negozio' });
        } catch (error) {
            body.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
            return;
        }
        if (ctx.ready === false) return missing(body, ctx.message);

        const vetrina = ctx.vetrine[0];
        ctx.defaultVetrina = vetrina?.id;

        if (negozio.tab === 'prodotti') {
            body.innerHTML = productsToolbar('negozio', ctx, negozio.filters);
            $$('[data-filter]', body).forEach((select) => select.addEventListener('change', () => {
                negozio.filters[select.dataset.filter] = select.value;
                productsTable($('[data-products-table]', body), 'negozio', ctx, loadNegozio, negozio.filters);
            }));
            $('[data-new-product]', body).addEventListener('click', () => productForm('negozio', ctx, null, loadNegozio));
            productsTable($('[data-products-table]', body), 'negozio', ctx, loadNegozio, negozio.filters);
            return;
        }

        if (negozio.tab === 'categorie') {
            categoriesTable(body, 'negozio', ctx, loadNegozio);
            return;
        }

        body.innerHTML = `
            <form class="admin-form-grid shop-admin-inline" data-vetrina-form>
                ${fields(vetrinaFields(vetrina || {}, false), vetrina || {})}
                <div class="admin-field--full shop-admin-inline__actions">
                    <a class="admin-btn" href="/it/negozio" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Apri la pagina</a>
                    <button type="submit" class="admin-btn admin-btn--primary"><i class="fa-solid fa-floppy-disk"></i> Salva</button>
                </div>
            </form>
            <div class="shop-admin-subtitle"><strong>Domande frequenti</strong><small>In fondo alla pagina del Negozio.</small></div>
            <div data-faq-box></div>`;

        const form = $('[data-vetrina-form]', body);
        bindVetrinaForm(form);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const res = await post(EP.catalog, 'save_vetrina', { ...readForm(form), id: vetrina.id });
                showToast(res.message);
                A.discardPendingUploads?.();
            } catch (error) {
                showToast(error.message, true);
            }
        });
        faqManager($('[data-faq-box]', body), { vetrina_id: vetrina.id });
    };

    /* ── Sezione Merch ───────────────────────────────────────────────── */

    const merch = { tab: 'collezioni', filters: { vetrina: '', cat: '', stato: '' } };

    const stateBadge = (v) => ({
        attiva: '<span class="admin-badge admin-badge--success">Aperta</span>',
        in_arrivo: `<span class="admin-badge admin-badge--warning">In arrivo${v.lancio_at ? ' · ' + e(A.formatDate(v.lancio_at)) : ''}</span>`,
        nascosta: '<span class="admin-badge">Nascosta</span>',
    }[v.stato] || '');

    const collectionForm = (item, reload) => {
        const values = item ? { ...item, lancio_at: toLocalInput(item.lancio_at) } : { stato: 'nascosta', colore_accento: '#ff4d9d', colore_sfondo: '#1a0512', colore_sfondo_2: '#3b0a2a' };
        formModal({
            title: item ? `Collezione ${item.nome}` : 'Nuova collezione',
            subtitle: item ? `/it/merch/${item.slug}` : 'Nasce nascosta: la prepari, la guardi in anteprima e poi la apri',
            html: fields(vetrinaFields(values, true), values),
            endpoint: EP.catalog,
            action: 'save_vetrina',
            extra: { id: item?.id || 0, tipo: 'merch' },
            after: (res) => {
                if (!item && res.id) {
                    merch.filters.vetrina = String(res.id);
                }
                reload();
            },
            onReady: bindVetrinaForm,
        });
    };

    const collectionFaq = (item) => {
        openModal(`FAQ di ${item.nome}`, 'In fondo alla pagina della collezione', '<div data-faq-box></div>', '<button class="admin-btn" data-admin-close="1">Chiudi</button>');
        faqManager($('#adminModalBody [data-faq-box]'), { vetrina_id: item.id });
    };

    const deleteCollection = (item, ctx, reload) => {
        const others = ctx.vetrine.filter((v) => Number(v.id) !== Number(item.id));
        const count = Number(item.prodotti || 0);
        const choice = count > 0 ? `
            <p class="admin-muted">Ha <b>${num(count)}</b> prodotti. Che cosa ne faccio?</p>
            <div class="shop-admin-choice">
                ${others.length ? `<label><input type="radio" name="delete-mode" value="move" checked> Spostali in
                    <select class="admin-input" data-move-target>${others.map((v) => `<option value="${Number(v.id)}">${e(v.nome)}</option>`).join('')}</select></label>` : ''}
                <label><input type="radio" name="delete-mode" value="delete" ${others.length ? '' : 'checked'}> Eliminali insieme alla collezione</label>
            </div>` : '<p class="admin-muted">Non ha prodotti.</p>';

        confirmBox(`Eliminare «${item.nome}»?`, `${choice}<p class="admin-muted">Le sue FAQ vengono eliminate. Se vuoi solo toglierla, rendila «Nascosta».</p>`, async () => {
            const modalBody = $('#confirmBody');
            const mode = $('input[name="delete-mode"]:checked', modalBody)?.value || 'delete';
            const target = $('[data-move-target]', modalBody)?.value || 0;
            await post(EP.catalog, 'delete_vetrina', { id: item.id, mode, target_id: target });
            showToast('Collezione eliminata.');
            if (String(merch.filters.vetrina) === String(item.id)) merch.filters.vetrina = '';
            reload();
        });
    };

    const collectionsTable = (box, ctx, reload) => {
        const rows = ctx.vetrine.filter((v) => matches(v.nome, v.slug, v.nome_en));
        const canSort = !A.getQuery();
        const last = rows.length - 1;

        box.innerHTML = `
            <div class="shop-admin-bar">
                <p class="admin-muted">Ogni collezione ha la sua pagina, i suoi colori, i suoi prodotti e le sue FAQ. Con una sola collezione aperta, <code>/it/merch</code> mostra direttamente quella.</p>
                <button type="button" class="admin-btn admin-btn--primary" data-new-collection><i class="fa-solid fa-plus"></i> Nuova collezione</button>
            </div>
            ${rows.length ? `
            <table class="admin-table">
                <thead><tr><th>Collezione</th><th>Stato</th><th>Prodotti</th><th>Azioni</th></tr></thead>
                <tbody>
                    ${rows.map((v, i) => `
                        <tr ${canSort ? `draggable="true" data-shop-row="${Number(v.id)}"` : ''}>
                            <td data-label="Collezione">
                                <div class="admin-name-cell">
                                    ${grip(canSort)}
                                    <span class="shop-admin-swatch" style="--a:${e(v.colore_accento)};--b1:${e(v.colore_sfondo)};--b2:${e(v.colore_sfondo_2)}">${v.logo ? `<img src="${e(A.assetUrl(v.logo))}" alt="">` : e((v.emoji || '🛍️').slice(0, 2))}</span>
                                    <div>
                                        <div class="admin-row-title">${e(v.nome)}</div>
                                        <div class="admin-row-sub"><a href="/it/merch/${e(v.slug)}" target="_blank" rel="noopener">/it/merch/${e(v.slug)} <i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Stato">${stateBadge(v)}</td>
                            <td data-label="Prodotti"><button class="admin-btn admin-btn--small" data-show-products="${Number(v.id)}">${num(v.prodotti)} <i class="fa-solid fa-arrow-right"></i></button></td>
                            <td data-label="Azioni"><div class="admin-row-actions admin-row-actions--slides">
                                ${canSort ? moveButtons(Number(v.id), i, last, false) : ''}
                                <button class="admin-btn admin-btn--small" data-edit-collection="${Number(v.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                                <button class="admin-btn admin-btn--small" data-faq-collection="${Number(v.id)}"><i class="fa-solid fa-circle-question"></i> FAQ (${num(v.faq)})</button>
                                <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-collection="${Number(v.id)}" title="Elimina"><i class="fa-solid fa-trash"></i></button>
                            </div></td>
                        </tr>`).join('')}
                </tbody>
            </table>` : emptyState('fa-solid fa-shirt', 'Nessuna collezione', 'Crea la prima con «Nuova collezione».')}`;

        const find = (id) => ctx.vetrine.find((v) => Number(v.id) === Number(id));
        $('[data-new-collection]', box).addEventListener('click', () => collectionForm(null, reload));
        $$('[data-edit-collection]', box).forEach((b) => b.addEventListener('click', () => collectionForm(find(b.dataset.editCollection), reload)));
        $$('[data-faq-collection]', box).forEach((b) => b.addEventListener('click', () => collectionFaq(find(b.dataset.faqCollection))));
        $$('[data-delete-collection]', box).forEach((b) => b.addEventListener('click', () => deleteCollection(find(b.dataset.deleteCollection), ctx, reload)));
        $$('[data-show-products]', box).forEach((b) => b.addEventListener('click', () => {
            merch.filters.vetrina = b.dataset.showProducts;
            merch.tab = 'prodotti';
            loadMerch();
        }));
        if (canSort && rows.length) bindReorder(box, rows.map((v) => Number(v.id)), EP.catalog, 'reorder_vetrine', reload);
    };

    const loadMerch = async () => {
        const root = $('[data-shop-admin="merch"]');
        if (!root) return;

        const body = tabs(root, [
            ['collezioni', 'Collezioni', 'fa-solid fa-layer-group'],
            ['prodotti', 'Prodotti', 'fa-solid fa-shirt'],
            ['categorie', 'Categorie', 'fa-solid fa-tags'],
            ['testata', 'Pagina «Tutto il merch»', 'fa-solid fa-pen-ruler'],
        ], merch.tab, (tab) => { merch.tab = tab; loadMerch(); });

        setLoading(body);
        let ctx;
        try {
            ctx = await get(EP.catalog, { action: 'list', tipo: 'merch' });
        } catch (error) {
            body.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
            return;
        }
        if (ctx.ready === false) return missing(body, ctx.message);

        if (merch.filters.vetrina && !ctx.vetrine.some((v) => String(v.id) === String(merch.filters.vetrina))) {
            merch.filters.vetrina = '';
        }
        ctx.defaultVetrina = merch.filters.vetrina || ctx.vetrine[0]?.id;

        if (merch.tab === 'collezioni') {
            collectionsTable(body, ctx, loadMerch);
            return;
        }

        if (merch.tab === 'prodotti') {
            body.innerHTML = productsToolbar('merch', ctx, merch.filters);
            $$('[data-filter]', body).forEach((select) => select.addEventListener('change', () => {
                merch.filters[select.dataset.filter] = select.value;
                ctx.defaultVetrina = merch.filters.vetrina || ctx.vetrine[0]?.id;
                productsTable($('[data-products-table]', body), 'merch', ctx, loadMerch, merch.filters);
            }));
            $('[data-new-product]', body).addEventListener('click', () => productForm('merch', ctx, null, loadMerch));
            productsTable($('[data-products-table]', body), 'merch', ctx, loadMerch, merch.filters);
            return;
        }

        if (merch.tab === 'categorie') {
            categoriesTable(body, 'merch', ctx, loadMerch);
            return;
        }

        body.innerHTML = `
            <div data-page-box></div>
            <div class="shop-admin-subtitle"><strong>Domande frequenti dell'elenco</strong><small>Ogni collezione ha poi le sue, dal bottone «FAQ» nella scheda Collezioni.</small></div>
            <div data-faq-box></div>`;
        pageTextsForm($('[data-page-box]', body), 'merch', {
            hint: 'La pagina <code>/it/merch</code> con tutte le collezioni. Compare solo quando le collezioni visibili sono almeno due (o una aperta e una in arrivo).',
        });
        faqManager($('[data-faq-box]', body), { pagina: 'merch' });
    };

    /* ── Sezione Shop Gacha ──────────────────────────────────────────── */

    const gacha = { tab: 'pacchetti', orders: { stato: '', provider: '', page: 1 } };

    const packageForm = (item, reload) => {
        const values = item
            ? { ...item, prezzo: (Number(item.prezzo_cent) / 100).toFixed(2).replace('.', ',') }
            : { attivo: 0, evidenza: 'nessuna' };

        formModal({
            title: item ? `Pacchetto ${item.nome}` : 'Nuovo pacchetto',
            subtitle: item ? `Codice ${item.slug} · non si può cambiare` : 'Nasce spento: controllalo e poi mettilo in vendita',
            html: fields([
                { name: 'nome', label: 'Nome', required: true, max: 80, placeholder: 'es. 150 Godo Shards' },
                item
                    ? { name: 'slug_ro', label: 'Codice', readonly: true, value: item.slug, help: 'Legato al bonus x2 e ai pagamenti: resta questo.' }
                    : { name: 'slug', label: 'Codice', max: 50, placeholder: 'vuoto = shards_<quantità>', help: 'Solo minuscole, numeri e _. Non si potrà cambiare.' },
                { name: 'shards', label: 'Godo Shards', type: 'number', required: true, min: 1, step: 1 },
                { name: 'prezzo', label: 'Prezzo (€)', required: true, placeholder: '4,99', inputmode: 'decimal', help: 'Minimo 0,50 €.' },
                { name: 'evidenza', label: 'Evidenza', type: 'select', options: [['nessuna', 'Nessuna'], ['pity', '⭐ Pity completo'], ['best', 'Miglior offerta']] },
                { name: 'attivo', label: 'In vendita', type: 'checkbox' },
                '<p class="shop-admin-help admin-field--full" data-package-value></p>',
                '<p class="shop-admin-note admin-field--full"><i class="fa-solid fa-shield-halved"></i> Chi sta già pagando riceve il prezzo e le Shards che ha visto: le modifiche valgono per i pagamenti nuovi. Il primo acquisto di ogni pacchetto vale doppio.</p>',
            ], values),
            endpoint: EP.gacha,
            action: 'save_package',
            extra: { id: item?.id || 0 },
            after: reload,
            onReady: (form) => bindForm(form, () => {
                const shards = Number(form.elements.shards.value || 0);
                const cents = Math.round(Number(String(form.elements.prezzo.value || '0').replace(/\./g, '').replace(',', '.')) * 100);
                const out = $('[data-package-value]', form);
                if (!out) return;
                if (shards > 0 && cents > 0) {
                    const rate = shards / (cents / 100);
                    const bonus = Math.round((rate / (10 / 0.99) - 1) * 100);
                    out.textContent = `${rate.toFixed(1).replace('.', ',')} Shards per euro${bonus > 0 ? ` · la pagina mostra «+${bonus}% valore» rispetto a 10 Shards a 0,99 €` : ''} · primo acquisto: ${shards * 2} Shards.`;
                } else {
                    out.textContent = '';
                }
            }),
        });
    };

    const itemForm = (item, ctx, reload) => {
        const badges = ctx.badges;
        const values = item
            ? { ...item, badge_id: item.item_value, disponibile_dal: toLocalInput(item.disponibile_dal), disponibile_fino: toLocalInput(item.disponibile_fino) }
            : { active: 0 };
        const badgeOptions = badges.map((b) => [b.id, `${b.name} (#${b.id})`]);

        formModal({
            title: item ? 'Modifica oggetto' : 'Nuovo oggetto Godos',
            subtitle: item ? `#${item.id} · ${num(item.acquisti)} acquisti` : 'Nasce spento: controllalo e poi mettilo in vendita',
            html: fields([
                { name: 'badge_id', label: 'Badge che si ottiene', type: 'select', required: true, full: true, options: [['', '— scegli un badge —'], ...badgeOptions] },
                '<div class="admin-field--full shop-admin-badge" data-badge-preview></div>',
                { name: 'name_it', label: 'Nome (IT)', max: 100, placeholder: 'vuoto = nome del badge' },
                { name: 'name_en', label: 'Nome (EN)', max: 100, placeholder: 'vuoto = nome inglese del badge' },
                { name: 'description_it', label: 'Descrizione (IT)', type: 'textarea', max: 500, rows: 2 },
                { name: 'description_en', label: 'Descrizione (EN)', type: 'textarea', max: 500, rows: 2 },
                { name: 'price_godos', label: 'Prezzo in Godos', type: 'number', required: true, min: 1, step: 1 },
                { name: 'availability', label: 'Pezzi disponibili', type: 'number', min: 0, step: 1, placeholder: 'vuoto = illimitati', help: 'Scende di uno a ogni acquisto.' },
                { name: 'image_url', label: 'Immagine', type: 'image', full: true, help: 'Vuota = l\'immagine del badge.' },
                ...(ctx.items_window ? [
                    sectionTitle('Vendita a tempo', 'Facoltativa. Prima dell\'inizio l\'oggetto si vede come «in arrivo», dopo la fine sparisce da solo. Sulla card compare il conto alla rovescia.'),
                    { name: 'disponibile_dal', label: 'In vendita dal', type: 'datetime-local' },
                    { name: 'disponibile_fino', label: 'In vendita fino al', type: 'datetime-local' },
                ] : []),
                { name: 'active', label: 'In vendita', type: 'checkbox' },
            ], values),
            endpoint: EP.gacha,
            action: 'save_item',
            extra: { id: item?.id || 0 },
            after: reload,
            onReady: (form) => bindForm(form, () => {
                const badge = badges.find((b) => String(b.id) === String(form.elements.badge_id.value));
                const box = $('[data-badge-preview]', form);
                if (!box) return;
                box.innerHTML = badge
                    ? `${thumb(badge.image_url, 'fa-solid fa-certificate')}<div><strong style="color:${e(badge.color || '#fff')}">${e(badge.name)}</strong><small>${e(badge.descrizione || '')}</small></div>`
                    : '';
                form.elements.name_it.placeholder = badge ? badge.name : 'vuoto = nome del badge';
                form.elements.name_en.placeholder = badge ? (badge.name_en || badge.name) : 'vuoto = nome inglese del badge';
            }, 'gacha'),
        });
    };

    const packagesTable = (box, ctx, reload) => {
        const all = ctx.packages.filter((p) => matches(p.nome, p.slug));
        const live = all.filter((p) => !p.archiviato_at);
        const archived = all.filter((p) => p.archiviato_at);
        const canEdit = ctx.can_edit_packages;
        const canSort = canEdit && !A.getQuery();
        const last = live.length - 1;

        const row = (p, i, isArchived) => {
            const rate = Number(p.shards) / (Number(p.prezzo_cent) / 100);
            return `
                <tr ${canSort && !isArchived ? `draggable="true" data-shop-row="${Number(p.id)}"` : ''} class="${Number(p.attivo) === 1 && !isArchived ? '' : 'is-off'}">
                    <td data-label="Pacchetto"><div class="admin-name-cell">${grip(canSort && !isArchived)}<span class="admin-thumb"><img src="/img/godoshards.png" alt=""></span><div>
                        <div class="admin-row-title">${e(p.nome)}</div>
                        <div class="admin-row-sub"><code>${e(p.slug)}</code>${p.evidenza === 'pity' ? ' · ⭐ Pity' : p.evidenza === 'best' ? ' · Miglior offerta' : ''}</div>
                    </div></div></td>
                    <td data-label="Shards">${num(p.shards)}</td>
                    <td data-label="Prezzo" class="admin-nowrap"><b>${money(Number(p.prezzo_cent) / 100)}</b><div class="admin-row-sub">${rate.toFixed(1).replace('.', ',')} / €</div></td>
                    <td data-label="Acquirenti">${num(p.acquirenti)}</td>
                    <td data-label="In vendita">${isArchived ? '<span class="admin-badge">Archiviato</span>' : toggleSwitch(Number(p.attivo) === 1, `data-toggle-package="${Number(p.id)}" ${canEdit ? '' : 'disabled'}`, 'In vendita')}</td>
                    <td data-label="Azioni"><div class="admin-row-actions admin-row-actions--slides">
                        ${canEdit && !isArchived ? moveButtons(Number(p.id), i, last, !canSort) : ''}
                        ${canEdit ? (isArchived
                            ? `<button class="admin-btn admin-btn--small" data-restore-package="${Number(p.id)}"><i class="fa-solid fa-rotate-left"></i> Ripristina</button>`
                            : `<button class="admin-btn admin-btn--small" data-edit-package="${Number(p.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                               <button class="admin-btn admin-btn--small admin-btn--danger" data-archive-package="${Number(p.id)}" title="Archivia"><i class="fa-solid fa-box-archive"></i></button>`)
                        : '<span class="admin-muted">Solo owner</span>'}
                    </div></td>
                </tr>`;
        };

        box.innerHTML = `
            <div class="shop-admin-bar">
                <p class="admin-muted">${canEdit
                    ? 'Pacchetti venduti con soldi veri (Stripe e PayPal). Un pacchetto non si cancella: si archivia, così i pagamenti già partiti e il bonus x2 restano corretti.'
                    : '<i class="fa-solid fa-lock"></i> Pacchetti a pagamento: li può modificare solo l\'owner. Qui li vedi in sola lettura.'}</p>
                ${canEdit ? '<button type="button" class="admin-btn admin-btn--primary" data-new-package><i class="fa-solid fa-plus"></i> Nuovo pacchetto</button>' : ''}
            </div>
            ${all.length ? `
            <table class="admin-table">
                <thead><tr><th>Pacchetto</th><th>Shards</th><th>Prezzo</th><th>Acquirenti</th><th>In vendita</th><th>Azioni</th></tr></thead>
                <tbody>
                    ${live.map((p, i) => row(p, i, false)).join('')}
                    ${archived.length ? `<tr class="shop-admin-divider"><td colspan="6">Archiviati</td></tr>${archived.map((p) => row(p, 0, true)).join('')}` : ''}
                </tbody>
            </table>` : emptyState('fa-solid fa-gem', 'Nessun pacchetto', 'La pagina dello shop non mostrerà pacchetti da comprare.')}`;

        const find = (id) => ctx.packages.find((p) => Number(p.id) === Number(id));
        $('[data-new-package]', box)?.addEventListener('click', () => packageForm(null, reload));
        $$('[data-edit-package]', box).forEach((b) => b.addEventListener('click', () => packageForm(find(b.dataset.editPackage), reload)));

        $$('[data-toggle-package]', box).forEach((input) => input.addEventListener('change', async () => {
            try {
                const res = await post(EP.gacha, 'toggle_package', { id: input.dataset.togglePackage, attivo: input.checked ? 1 : 0 });
                showToast(res.message);
                reload();
            } catch (error) {
                input.checked = !input.checked;
                showToast(error.message, true);
            }
        }));

        $$('[data-archive-package]', box).forEach((b) => b.addEventListener('click', () => {
            const p = find(b.dataset.archivePackage);
            confirmBox('Archiviare il pacchetto?', `<p class="admin-muted">«${e(p.nome)}» esce dalla vendita e dalla lista. I pagamenti già partiti vengono comunque accreditati. Puoi ripristinarlo quando vuoi.</p>`, async () => {
                await post(EP.gacha, 'archive_package', { id: p.id });
                showToast('Pacchetto archiviato.');
                reload();
            });
        }));

        $$('[data-restore-package]', box).forEach((b) => b.addEventListener('click', async () => {
            try {
                const res = await post(EP.gacha, 'restore_package', { id: b.dataset.restorePackage });
                showToast(res.message);
                reload();
            } catch (error) {
                showToast(error.message, true);
            }
        }));

        if (canSort && live.length) bindReorder(box, live.map((p) => Number(p.id)), EP.gacha, 'reorder_packages', reload);
    };

    const parseStamp = (value) => (value ? new Date(String(value).replace(' ', 'T')).getTime() : NaN);

    const windowBadge = (it) => {
        const from = parseStamp(it.disponibile_dal);
        const until = parseStamp(it.disponibile_fino);
        const now = Date.now();
        if (!Number.isNaN(from) && from > now) return ` · <span class="admin-badge admin-badge--warning">Dal ${e(A.formatDate(it.disponibile_dal))}</span>`;
        if (!Number.isNaN(until) && until <= now) return ' · <span class="admin-badge admin-badge--danger">Scaduto</span>';
        if (!Number.isNaN(until)) return ` · <span class="admin-badge admin-badge--info">Fino al ${e(A.formatDate(it.disponibile_fino))}</span>`;
        return '';
    };

    const itemsTable = (box, ctx, reload) => {
        const all = ctx.items.filter((i) => matches(i.name_it, i.name_en, i.badge_name));
        const live = all.filter((i) => !i.archiviato_at);
        const archived = all.filter((i) => i.archiviato_at);
        const canSort = ctx.items_position && !A.getQuery();
        const last = live.length - 1;

        const row = (it, i, isArchived) => `
            <tr ${canSort && !isArchived ? `draggable="true" data-shop-row="${Number(it.id)}"` : ''} class="${Number(it.active) === 1 && !isArchived ? '' : 'is-off'}">
                <td data-label="Oggetto"><div class="admin-name-cell">${grip(canSort && !isArchived)}${thumb(it.image_url || it.badge_image, 'fa-solid fa-certificate')}<div>
                    <div class="admin-row-title">${e(it.name_it)}</div>
                    <div class="admin-row-sub">Badge ${it.badge_name ? `«${e(it.badge_name)}»` : `#${e(it.item_value)} <span class="admin-danger-text">(non trovato)</span>`}${windowBadge(it)}</div>
                </div></div></td>
                <td data-label="Prezzo" class="admin-nowrap"><b>${num(it.price_godos)}</b> Godos</td>
                <td data-label="Pezzi">${it.availability === null ? '∞' : num(it.availability)}</td>
                <td data-label="Acquisti">${num(it.acquisti)}</td>
                <td data-label="In vendita">${isArchived ? '<span class="admin-badge">Archiviato</span>' : toggleSwitch(Number(it.active) === 1, `data-toggle-item="${Number(it.id)}"`, 'In vendita')}</td>
                <td data-label="Azioni"><div class="admin-row-actions admin-row-actions--slides">
                    ${isArchived
                        ? `<button class="admin-btn admin-btn--small" data-restore-item="${Number(it.id)}"><i class="fa-solid fa-rotate-left"></i> Ripristina</button>`
                        : `${canSort ? moveButtons(Number(it.id), i, last, false) : ''}
                           <button class="admin-btn admin-btn--small" data-edit-item="${Number(it.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                           <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-item="${Number(it.id)}" title="Elimina"><i class="fa-solid fa-trash"></i></button>`}
                </div></td>
            </tr>`;

        box.innerHTML = `
            <div class="shop-admin-bar">
                <p class="admin-muted">Badge che si comprano con i Godos nella scheda «Negozio Godos» dello shop. Chi l'ha già comprato lo tiene anche se l'oggetto viene tolto.</p>
                <button type="button" class="admin-btn admin-btn--primary" data-new-item ${ctx.badges.length ? '' : 'disabled title="Non ci sono badge"'}><i class="fa-solid fa-plus"></i> Nuovo oggetto</button>
            </div>
            ${ctx.items_position ? '' : '<p class="shop-admin-hint"><i class="fa-solid fa-circle-info"></i> Applica la migrazione dello shop per riordinare e archiviare gli oggetti.</p>'}
            ${all.length ? `
            <table class="admin-table">
                <thead><tr><th>Oggetto</th><th>Prezzo</th><th>Pezzi</th><th>Acquisti</th><th>In vendita</th><th>Azioni</th></tr></thead>
                <tbody>
                    ${live.map((it, i) => row(it, i, false)).join('')}
                    ${archived.length ? `<tr class="shop-admin-divider"><td colspan="6">Archiviati</td></tr>${archived.map((it) => row(it, 0, true)).join('')}` : ''}
                </tbody>
            </table>` : emptyState('fa-solid fa-certificate', 'Nessun oggetto', 'Crea il primo con «Nuovo oggetto».')}`;

        const find = (id) => ctx.items.find((it) => Number(it.id) === Number(id));
        $('[data-new-item]', box).addEventListener('click', () => itemForm(null, ctx, reload));
        $$('[data-edit-item]', box).forEach((b) => b.addEventListener('click', () => itemForm(find(b.dataset.editItem), ctx, reload)));

        $$('[data-toggle-item]', box).forEach((input) => input.addEventListener('change', async () => {
            try {
                const res = await post(EP.gacha, 'toggle_item', { id: input.dataset.toggleItem, active: input.checked ? 1 : 0 });
                showToast(res.message);
                reload();
            } catch (error) {
                input.checked = !input.checked;
                showToast(error.message, true);
            }
        }));

        $$('[data-delete-item]', box).forEach((b) => b.addEventListener('click', () => {
            const it = find(b.dataset.deleteItem);
            const bought = Number(it.acquisti || 0);
            confirmBox(bought ? 'Archiviare l\'oggetto?' : 'Eliminare l\'oggetto?', bought
                ? `<p class="admin-muted">«${e(it.name_it)}» è stato comprato ${num(bought)} volte: viene archiviato invece che cancellato, così lo storico degli acquisti resta. Chi ha il badge lo tiene.</p>`
                : `<p class="admin-muted">«${e(it.name_it)}» non è mai stato comprato: viene eliminato.</p>`, async () => {
                const res = await post(EP.gacha, 'delete_item', { id: it.id });
                showToast(res.message);
                reload();
            });
        }));

        $$('[data-restore-item]', box).forEach((b) => b.addEventListener('click', async () => {
            try {
                const res = await post(EP.gacha, 'restore_item', { id: b.dataset.restoreItem });
                showToast(res.message);
                reload();
            } catch (error) {
                showToast(error.message, true);
            }
        }));

        if (canSort && live.length) bindReorder(box, live.map((it) => Number(it.id)), EP.gacha, 'reorder_items', reload);
    };

    const ORDER_STATES = {
        pagato: '<span class="admin-badge admin-badge--success">Pagato</span>',
        errore: '<span class="admin-badge admin-badge--danger">Errore</span>',
        in_attesa: '<span class="admin-badge admin-badge--warning">In attesa</span>',
        abbandonato: '<span class="admin-badge">Non completato</span>',
    };

    const ordersTable = async (box) => {
        setLoading(box);
        let data;
        try {
            data = await get(EP.gacha, { action: 'orders', stato: gacha.orders.stato, provider: gacha.orders.provider, q: A.getQuery() || '', page: gacha.orders.page });
        } catch (error) {
            box.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
            return;
        }
        if (data.ready === false) {
            missing(box, 'Lo storico degli ordini arriva con la migrazione dello shop: applica migrations/2026_09_23_shop_catalog.sql. Da quel momento ogni acquisto di Shards finisce qui.');
            return;
        }

        const rows = data.orders || [];
        const state = (o) => (o.stato === 'in_attesa' && Number(o.eta_secondi) > 86400 ? 'abbandonato' : o.stato);

        box.innerHTML = `
            <div class="shop-admin-bar">
                <p class="admin-muted">Ogni pagamento di Shards, in sola lettura. «In attesa» = checkout aperto e non ancora pagato (dopo un giorno diventa «Non completato»). «Errore» = qualcosa non è andato: la nota dice cosa. Cerca per username con la ricerca in alto. Ultimi 30 giorni: <b>${num(data.last30?.count || 0)}</b> pagamenti, <b>${money((data.last30?.cents || 0) / 100)}</b>.</p>
                <div class="admin-toolbar-actions">
                    <select class="admin-input" data-orders-filter="stato" aria-label="Stato">
                        <option value="">Tutti gli stati</option>
                        <option value="pagato" ${gacha.orders.stato === 'pagato' ? 'selected' : ''}>Pagati</option>
                        <option value="in_attesa" ${gacha.orders.stato === 'in_attesa' ? 'selected' : ''}>In attesa</option>
                        <option value="errore" ${gacha.orders.stato === 'errore' ? 'selected' : ''}>Con errore</option>
                    </select>
                    <select class="admin-input" data-orders-filter="provider" aria-label="Metodo">
                        <option value="">Stripe e PayPal</option>
                        <option value="stripe" ${gacha.orders.provider === 'stripe' ? 'selected' : ''}>Stripe</option>
                        <option value="paypal" ${gacha.orders.provider === 'paypal' ? 'selected' : ''}>PayPal</option>
                    </select>
                </div>
            </div>
            ${rows.length ? `
            <table class="admin-table">
                <thead><tr><th>Data</th><th>Utente</th><th>Pacchetto</th><th>Importo</th><th>Shards</th><th>Metodo</th><th>Stato</th></tr></thead>
                <tbody>
                    ${rows.map((o) => `
                        <tr>
                            <td data-label="Data" class="admin-nowrap">${e(A.formatDate(o.created_at))}<div class="admin-row-sub">${e(String(o.created_at || '').slice(11, 16))}</div></td>
                            <td data-label="Utente">${o.username ? `<b>${e(o.username)}</b>` : '<span class="admin-muted">account eliminato</span>'}<div class="admin-row-sub">#${Number(o.user_id)}</div></td>
                            <td data-label="Pacchetto"><code>${e(o.pacchetto)}</code></td>
                            <td data-label="Importo" class="admin-nowrap"><b>${money(Number(o.importo_cent) / 100)}</b></td>
                            <td data-label="Shards">${o.shards_accreditate !== null ? `<b>${num(o.shards_accreditate)}</b>${Number(o.bonus) === 1 ? ' <span class="admin-badge admin-badge--info">x2</span>' : ''}` : `<span class="admin-muted">${num(o.shards_base)}</span>`}</td>
                            <td data-label="Metodo">${o.provider === 'stripe' ? 'Stripe' : 'PayPal'}<div class="admin-row-sub shop-admin-source" title="${e(o.provider_ref)}">${e(String(o.provider_ref).slice(0, 22))}${String(o.provider_ref).length > 22 ? '…' : ''}</div></td>
                            <td data-label="Stato">${ORDER_STATES[state(o)] || e(o.stato)}${o.nota ? `<div class="admin-row-sub admin-danger-text">${e(o.nota)}</div>` : ''}</td>
                        </tr>`).join('')}
                </tbody>
            </table>
            ${data.pages > 1 ? `<div class="admin-pagination">
                <button ${data.page <= 1 ? 'disabled' : ''} data-orders-page="${data.page - 1}"><i class="fa-solid fa-chevron-left"></i></button>
                <button class="is-active">${data.page} / ${data.pages}</button>
                <button ${data.page >= data.pages ? 'disabled' : ''} data-orders-page="${data.page + 1}"><i class="fa-solid fa-chevron-right"></i></button>
            </div>` : ''}` : emptyState('fa-solid fa-receipt', 'Nessun ordine', 'Qui compaiono gli acquisti di Shards fatti dopo l\'aggiornamento.')}`;

        $$('[data-orders-filter]', box).forEach((select) => select.addEventListener('change', () => {
            gacha.orders[select.dataset.ordersFilter] = select.value;
            gacha.orders.page = 1;
            ordersTable(box);
        }));
        $$('[data-orders-page]', box).forEach((button) => button.addEventListener('click', () => {
            gacha.orders.page = Number(button.dataset.ordersPage);
            ordersTable(box);
        }));
    };

    const settingsForm = (box, ctx) => {
        if (!ctx.settings_ready) {
            missing(box, 'Le impostazioni arrivano con la migrazione dello shop. Finché non la applichi il cambio resta 100 Godos = 1 Shard.');
            return;
        }

        const canEdit = ctx.can_edit_packages;
        box.innerHTML = `
            <form class="admin-form-grid shop-admin-inline" data-settings-form>
                ${sectionTitle('Conversione Godos → Shards', canEdit
                    ? 'Quanti Godos servono per una Shard nel convertitore dello shop. Vale subito per tutti.'
                    : '<i class="fa-solid fa-lock"></i> Lo cambia solo l\'owner: decide quanto valgono le Shards comprate rispetto a quelle ottenute giocando.')}
                ${field({ name: 'godos_per_shard', label: 'Godos per 1 Shard', type: 'number', min: 1, step: 1, required: true, readonly: !canEdit }, { godos_per_shard: ctx.godos_per_shard })}
                <p class="shop-admin-help admin-field--full" data-rate-preview></p>
                ${canEdit ? '<div class="admin-field--full shop-admin-inline__actions"><button type="submit" class="admin-btn admin-btn--primary"><i class="fa-solid fa-floppy-disk"></i> Salva</button></div>' : ''}
            </form>`;

        const form = $('[data-settings-form]', box);
        const preview = () => {
            const rate = Number(form.elements.godos_per_shard.value || 0);
            $('[data-rate-preview]', form).textContent = rate > 0
                ? `Con ${num(rate * 10)} Godos si comprano 10 Shards, cioè un multi pull. Il lootbox continua a mostrare il suo costo delle pull, che è un'altra cosa.`
                : '';
        };
        form.addEventListener('input', preview);
        preview();

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!canEdit) return;
            try {
                const res = await post(EP.gacha, 'save_settings', readForm(form));
                showToast(res.message);
            } catch (error) {
                showToast(error.message, true);
            }
        });
    };

    const loadGacha = async () => {
        const root = $('[data-shop-admin="gacha"]');
        if (!root) return;

        const body = tabs(root, [
            ['pacchetti', 'Pacchetti Godo Shards', 'fa-solid fa-gem'],
            ['oggetti', 'Oggetti Godos', 'fa-solid fa-certificate'],
            ['ordini', 'Ordini', 'fa-solid fa-receipt'],
            ['impostazioni', 'Impostazioni', 'fa-solid fa-sliders'],
        ], gacha.tab, (tab) => { gacha.tab = tab; loadGacha(); });

        if (gacha.tab === 'ordini') {
            ordersTable(body);
            return;
        }

        setLoading(body);
        let ctx;
        try {
            ctx = await get(EP.gacha, { action: 'list' });
        } catch (error) {
            body.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
            return;
        }

        if (gacha.tab === 'pacchetti') {
            if (!ctx.packages_ready) {
                missing(body, 'Finché la migrazione non è applicata lo shop usa i pacchetti di sempre, scritti nel codice. Applica migrations/2026_09_23_shop_catalog.sql per gestirli da qui.');
                return;
            }
            packagesTable(body, ctx, loadGacha);
        } else if (gacha.tab === 'impostazioni') {
            settingsForm(body, ctx);
        } else {
            itemsTable(body, ctx, loadGacha);
        }
    };

    /* ── Sezione Download ────────────────────────────────────────────── */

    const downloads = { tab: 'elementi', stato: '' };

    const downloadForm = (item, ctx, reload) => {
        const values = item ? { ...item } : { stato: 'nascosto', tipo: 'file', nota_tono: 'info' };

        const html = fields([
            sectionTitle('Cosa si scarica'),
            { name: 'nome', label: 'Nome (IT)', required: true, max: 120 },
            { name: 'nome_en', label: 'Nome (EN)', max: 120, placeholder: 'vuoto = usa l\'italiano' },
            { name: 'stato', label: 'Stato', type: 'select', options: [['disponibile', 'Disponibile'], ['presto', 'In arrivo (visibile, non scaricabile)'], ['nascosto', 'Nascosto (lo vede solo lo staff)']] },
            { name: 'tipo', label: 'Tipo', type: 'select', options: [['file', 'File caricato sul sito'], ['link', 'Link a un sito esterno']] },
            `<div class="admin-field admin-field--full" data-show-when="tipo=file">
                <label>File</label>
                <div class="admin-input-group">
                    <input type="text" name="sorgente_file" value="${e(values.tipo === 'file' ? values.sorgente || '' : '')}" placeholder="/uploads/downloads/file.pdf" data-file-input>
                    <label class="admin-btn shop-admin-upload"><i class="fa-solid fa-upload"></i> Carica file<input type="file" data-upload-file hidden></label>
                </div>
                <small class="shop-admin-help" data-file-info>${values.tipo === 'file' && item ? (item.file_ok ? `Sul disco: ${e(item.size_label)}` : '<span class="admin-danger-text">File non trovato sul disco.</span>') : `Ammessi: ${e((ctx.extensions || []).join(', '))}. Niente eseguibili: per i programmi usa un link.`}</small>
            </div>`,
            `<div class="admin-field admin-field--full" data-show-when="tipo=file">
                <label>File inglese <small>(facoltativo)</small></label>
                <div class="admin-input-group">
                    <input type="text" name="sorgente_en_file" value="${e(values.tipo === 'file' ? values.sorgente_en || '' : '')}" placeholder="vuoto = lo stesso file" data-file-input>
                    <label class="admin-btn shop-admin-upload"><i class="fa-solid fa-upload"></i> Carica<input type="file" data-upload-file hidden></label>
                </div>
            </div>`,
            { name: 'nome_file', label: 'Nome del file scaricato', max: 160, full: true, showWhen: 'tipo=file', placeholder: 'es. corso-yoshukai.pdf', help: 'Come si chiamerà sul computer di chi scarica. Vuoto = il nome del file sul sito.' },
            { name: 'sorgente_link', label: 'Link', max: 500, full: true, showWhen: 'tipo=link', placeholder: 'https://...', value: values.tipo === 'link' ? values.sorgente || '' : '' },
            { name: 'sorgente_en_link', label: 'Link inglese (facoltativo)', max: 500, full: true, showWhen: 'tipo=link', placeholder: 'vuoto = lo stesso link', value: values.tipo === 'link' ? values.sorgente_en || '' : '' },
            sectionTitle('Come appare'),
            { name: 'immagine', label: 'Immagine', type: 'image', full: true },
            { name: 'descrizione_breve', label: 'Descrizione breve (IT)', type: 'textarea', max: 300, rows: 2, help: 'Nella card della lista.' },
            { name: 'descrizione_breve_en', label: 'Descrizione breve (EN)', type: 'textarea', max: 300, rows: 2 },
            { name: 'descrizione', label: 'Descrizione completa (IT)', type: 'textarea', max: 4000, rows: 4, help: 'Nella pagina del download. Vuota = quella breve.' },
            { name: 'descrizione_en', label: 'Descrizione completa (EN)', type: 'textarea', max: 4000, rows: 4 },
            { name: 'badge', label: 'Badge sulla card (IT)', max: 40, placeholder: 'es. Gratis' },
            { name: 'badge_en', label: 'Badge sulla card (EN)', max: 40 },
            { name: 'testo_bottone', label: 'Testo del bottone (IT)', max: 60, placeholder: 'vuoto = Scarica / Apri' },
            { name: 'testo_bottone_en', label: 'Testo del bottone (EN)', max: 60 },
            sectionTitle('Pagina del download'),
            { name: 'nota', label: 'Avviso (IT)', type: 'textarea', max: 400, rows: 2 },
            { name: 'nota_en', label: 'Avviso (EN)', type: 'textarea', max: 400, rows: 2 },
            { name: 'nota_tono', label: 'Tipo di avviso', type: 'select', options: [['info', 'Informazione'], ['avviso', 'Attenzione (giallo)']] },
            { name: 'slug', label: 'Indirizzo', max: 80, placeholder: 'vuoto = dal nome', help: 'La pagina sarà <code>/it/download/indirizzo</code>.' },
            { name: 'meta_it', label: 'Dettagli (IT)', type: 'textarea', rows: 3, placeholder: 'Piattaforma: Windows\nFonte: GitHub ufficiale', help: 'Una riga per voce, «Etichetta: valore». Tipo e dimensione del file si aggiungono da soli.' },
            { name: 'meta_en', label: 'Dettagli (EN)', type: 'textarea', rows: 3, placeholder: 'Platform: Windows\nSource: Official GitHub' },
            { name: 'passi_it', label: 'Prima di scaricare (IT)', type: 'textarea', rows: 3, placeholder: 'Clicca il pulsante download.\nApri il file.', help: 'Un passaggio per riga.' },
            { name: 'passi_en', label: 'Prima di scaricare (EN)', type: 'textarea', rows: 3 },
            { name: 'in_evidenza', label: 'In evidenza (stella, in cima alla lista)', type: 'checkbox', checked: false },
        ], values);

        openModal(
            item ? `Download ${item.nome}` : 'Nuovo download',
            item ? `/it/download/${item.slug} · ${num(item.contatore)} download` : 'Nasce nascosto: provalo dalla sua pagina e poi rendilo disponibile',
            `<form class="admin-form-grid shop-admin-form" data-shop-form novalidate>${html}</form>`,
            '<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" data-shop-save><i class="fa-solid fa-floppy-disk"></i> Salva</button>'
        );

        const form = $('#adminModalBody [data-shop-form]');
        const saveBtn = $('#adminModalFooter [data-shop-save]');
        bindForm(form, null, 'download');

        $$('[data-upload-file]', form).forEach((input) => input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) return;
            const wrap = input.closest('.admin-field');
            const text = $('[data-file-input]', wrap);
            const info = $('[data-file-info]', wrap);
            const fd = new FormData();
            fd.append('action', 'upload');
            fd.append('file', file);
            try {
                showToast('Caricamento file...');
                const res = await api(EP.downloads, { method: 'POST', body: fd });
                text.value = res.path;
                if (info) info.textContent = `Caricato: ${res.size_label}`;
                if (form.elements.nome_file && !form.elements.nome_file.value && text.name === 'sorgente_file') {
                    form.elements.nome_file.value = res.suggested_name;
                }
                showToast('File caricato.');
            } catch (error) {
                showToast(error.message, true);
            } finally {
                input.value = '';
            }
        }));

        const save = async () => {
            const payload = readForm(form);
            const isFile = payload.tipo === 'file';
            payload.sorgente = isFile ? payload.sorgente_file : payload.sorgente_link;
            payload.sorgente_en = isFile ? payload.sorgente_en_file : payload.sorgente_en_link;
            ['sorgente_file', 'sorgente_en_file', 'sorgente_link', 'sorgente_en_link'].forEach((k) => delete payload[k]);

            saveBtn.disabled = true;
            try {
                const res = await post(EP.downloads, 'save', { ...payload, id: item?.id || 0 });
                closeModal();
                showToast(res.message);
                reload();
            } catch (error) {
                showToast(error.message, true);
            } finally {
                saveBtn.disabled = false;
            }
        };

        saveBtn.addEventListener('click', save);
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            save();
        });
    };

    const downloadsTable = (box, ctx, reload) => {
        let rows = ctx.items.filter((d) => matches(d.nome, d.nome_en, d.slug, d.sorgente));
        if (downloads.stato) rows = rows.filter((d) => d.stato === downloads.stato);
        const canSort = !downloads.stato && !A.getQuery();
        const last = rows.length - 1;

        const source = (d) => d.tipo === 'link'
            ? `<i class="fa-solid fa-arrow-up-right-from-square"></i> ${e(d.host || d.sorgente || '—')}`
            : (d.sorgente
                ? (d.file_ok ? `<i class="fa-solid fa-file"></i> ${e(String(d.sorgente).split('/').pop())} · ${e(d.size_label)}` : `<span class="admin-danger-text"><i class="fa-solid fa-triangle-exclamation"></i> File mancante</span>`)
                : '<span class="admin-muted">Nessun file</span>');

        box.innerHTML = `
            <div class="shop-admin-bar">
                <div class="admin-toolbar-actions">
                    <select class="admin-input" data-filter-stato aria-label="Stato">
                        <option value="">Tutti gli stati</option>
                        <option value="disponibile" ${downloads.stato === 'disponibile' ? 'selected' : ''}>Disponibili</option>
                        <option value="presto" ${downloads.stato === 'presto' ? 'selected' : ''}>In arrivo</option>
                        <option value="nascosto" ${downloads.stato === 'nascosto' ? 'selected' : ''}>Nascosti</option>
                    </select>
                </div>
                <button type="button" class="admin-btn admin-btn--primary" data-new-download><i class="fa-solid fa-plus"></i> Nuovo download</button>
            </div>
            ${rows.length ? `
            <table class="admin-table">
                <thead><tr><th>Download</th><th>Sorgente</th><th>Download</th><th>Stato</th><th>Azioni</th></tr></thead>
                <tbody>
                    ${rows.map((d, i) => `
                        <tr ${canSort ? `draggable="true" data-shop-row="${Number(d.id)}"` : ''} class="${d.stato === 'disponibile' ? '' : 'is-off'}">
                            <td data-label="Download"><div class="admin-name-cell">${grip(canSort)}${thumb(d.immagine, 'fa-solid fa-download')}<div>
                                <div class="admin-row-title">${Number(d.in_evidenza) === 1 ? '<i class="fa-solid fa-star shop-admin-star"></i> ' : ''}${e(d.nome)}</div>
                                <div class="admin-row-sub"><a href="/it/download/${e(d.slug)}" target="_blank" rel="noopener">/it/download/${e(d.slug)} <i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>
                            </div></div></td>
                            <td data-label="Sorgente" class="shop-admin-source">${source(d)}</td>
                            <td data-label="Download">${num(d.contatore)}</td>
                            <td data-label="Stato">
                                <select class="admin-input shop-admin-state" data-state-download="${Number(d.id)}" aria-label="Stato">
                                    <option value="disponibile" ${d.stato === 'disponibile' ? 'selected' : ''}>Disponibile</option>
                                    <option value="presto" ${d.stato === 'presto' ? 'selected' : ''}>In arrivo</option>
                                    <option value="nascosto" ${d.stato === 'nascosto' ? 'selected' : ''}>Nascosto</option>
                                </select>
                            </td>
                            <td data-label="Azioni"><div class="admin-row-actions admin-row-actions--slides">
                                ${canSort ? moveButtons(Number(d.id), i, last, false) : ''}
                                <button class="admin-btn admin-btn--small" data-edit-download="${Number(d.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                                <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-download="${Number(d.id)}" title="Elimina"><i class="fa-solid fa-trash"></i></button>
                            </div></td>
                        </tr>`).join('')}
                </tbody>
            </table>` : emptyState('fa-solid fa-download', 'Nessun download', ctx.items.length ? 'Nessun risultato con questo filtro.' : 'Crea il primo con «Nuovo download».')}`;

        const find = (id) => ctx.items.find((d) => Number(d.id) === Number(id));

        $('[data-filter-stato]', box).addEventListener('change', (event) => {
            downloads.stato = event.target.value;
            downloadsTable(box, ctx, reload);
        });
        $('[data-new-download]', box).addEventListener('click', () => downloadForm(null, ctx, reload));
        $$('[data-edit-download]', box).forEach((b) => b.addEventListener('click', () => downloadForm(find(b.dataset.editDownload), ctx, reload)));

        $$('[data-state-download]', box).forEach((select) => {
            const previous = select.value;
            select.addEventListener('change', async () => {
                try {
                    const res = await post(EP.downloads, 'set_state', { id: select.dataset.stateDownload, stato: select.value });
                    showToast(res.message);
                    reload();
                } catch (error) {
                    select.value = previous;
                    showToast(error.message, true);
                }
            });
        });

        $$('[data-delete-download]', box).forEach((b) => b.addEventListener('click', () => {
            const d = find(b.dataset.deleteDownload);
            confirmBox('Eliminare il download?', `<p class="admin-muted">«${e(d.nome)}» sparisce dalla lista e la sua pagina smette di funzionare. Il file caricato resta sul server. Se vuoi solo toglierlo, mettilo «Nascosto».</p>`, async () => {
                await post(EP.downloads, 'delete', { id: d.id });
                showToast('Download eliminato.');
                reload();
            });
        }));

        if (canSort && rows.length) bindReorder(box, rows.map((d) => Number(d.id)), EP.downloads, 'reorder', reload);
    };

    const loadDownloads = async () => {
        const root = $('[data-shop-admin="download"]');
        if (!root) return;

        const body = tabs(root, [
            ['elementi', 'Download', 'fa-solid fa-download'],
            ['testata', 'Testata e FAQ', 'fa-solid fa-pen-ruler'],
        ], downloads.tab, (tab) => { downloads.tab = tab; loadDownloads(); });

        if (downloads.tab === 'testata') {
            body.innerHTML = `
                <div data-page-box></div>
                <div class="shop-admin-subtitle"><strong>Domande frequenti</strong><small>In fondo alla lista dei download.</small></div>
                <div data-faq-box></div>`;
            pageTextsForm($('[data-page-box]', body), 'download', { withNote: true, withLink: true });
            faqManager($('[data-faq-box]', body), { pagina: 'download' });
            return;
        }

        setLoading(body);
        let ctx;
        try {
            ctx = await get(EP.downloads, { action: 'list' });
        } catch (error) {
            body.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
            return;
        }
        if (ctx.ready === false) return missing(body, ctx.message);
        downloadsTable(body, ctx, loadDownloads);
    };

    A.registerSection('shop-negozio', loadNegozio);
    A.registerSection('shop-merch', loadMerch);
    A.registerSection('shop-gacha', loadGacha);
    A.registerSection('shop-download', loadDownloads);
})();
