/*
 * Pannello admin: banner del gacha e categorie dei personaggi.
 *
 * Usa gli strumenti di admin.js (window.CripsumAdmin) e si registra come due
 * sezioni della sidebar. API in /api/admin/gacha_banners.php e
 * /api/admin/gacha_categories.php.
 *
 * L'editor del banner ha un'anteprima delle probabilita' che chiede i numeri
 * al server a ogni modifica: li calcola la stessa funzione del motore delle
 * pull, quindi cio' che si vede qui e' cio' che succede nella lootbox.
 */
(() => {
    'use strict';

    const A = window.CripsumAdmin;
    if (!A) return;

    const { api, openModal, closeModal, confirmBox, showToast, thumb, setLoading, emptyState, enableRowDrag } = A;
    const e = A.escapeHtml;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const EP = { banner: 'gacha_banners.php', categorie: 'gacha_categories.php' };
    const get = (endpoint, params = {}) => api(`${endpoint}?${new URLSearchParams(params)}`);
    const post = (endpoint, action, body = {}) => api(endpoint, { method: 'POST', body: { action, ...body } });
    const num = (value) => Number(value || 0).toLocaleString('it-IT');
    const pct = (value, digits = 3) => `${Number(value || 0).toLocaleString('it-IT', { maximumFractionDigits: digits })}%`;

    const RARITY_COLORS = {
        comune: '#9ca3af', raro: '#38bdf8', epico: '#c084fc', leggendario: '#fbbf24',
        speciale: '#ffffff', segreto: '#a855f7', theone: '#60a5fa',
    };
    const RARITY_LABELS = {
        comune: 'Comune', raro: 'Raro', epico: 'Epico', leggendario: 'Leggendario',
        speciale: 'Speciale', segreto: 'Segreto', theone: 'The One',
    };
    const rarityDot = (key) => `<span class="gacha-admin-dot" style="--c:${RARITY_COLORS[key] || '#fff'}"></span>`;

    const STATUS = {
        attivo: '<span class="admin-badge admin-badge--success">Attivo</span>',
        prossimamente: '<span class="admin-badge admin-badge--info">Prossimamente</span>',
        programmato: '<span class="admin-badge admin-badge--warning">Programmato</span>',
        scaduto: '<span class="admin-badge admin-badge--danger">Scaduto</span>',
        bozza: '<span class="admin-badge">Spento</span>',
    };
    const TIPI = { standard: 'Standard', evento: 'Evento', selezione: 'Selezione', principiante: 'Principiante' };
    const POOL = { standard: 'Pool standard', lista: 'Solo selezionati', categoria: 'Per categoria' };

    const switchHtml = (checked, attrs, label) => `
        <label class="shop-admin-switch" title="${e(label)}">
            <input type="checkbox" ${checked ? 'checked' : ''} ${attrs} aria-label="${e(label)}"><span></span>
        </label>`;

    // Dati per l'editor: personaggi, categorie, rarita'. Si chiedono una volta.
    let meta = null;
    const loadMeta = async (force = false) => {
        if (meta && !force) return meta;
        meta = await get(EP.banner, { action: 'characters' });
        meta.byId = new Map((meta.characters || []).map((c) => [Number(c.id), c]));
        return meta;
    };

    const uploadImage = async (file) => {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('type', 'image');
        fd.append('folder', 'gacha');
        const res = await api('upload_media.php', { method: 'POST', body: fd });
        A.trackUpload?.(res.url);
        return res.filename || res.url;
    };

    /* ══ Banner: lista ═══════════════════════════════════════════════════ */

    const loadBanners = async () => {
        const box = $('[data-gacha-admin="banner"]');
        if (!box) return;
        setLoading(box);
        let data;
        try {
            data = await get(EP.banner, { action: 'list' });
        } catch (error) {
            box.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore banner', error.message);
            return;
        }
        if (data.ready === false) {
            box.innerHTML = `<p class="shop-admin-note"><i class="fa-solid fa-circle-info"></i> ${e(data.message)}</p>`;
            $('#createGachaBannerBtn')?.setAttribute('disabled', 'disabled');
            return;
        }

        const q = String(A.getQuery() || '').toLowerCase();
        const rows = (data.banners || []).filter((b) => !q || `${b.nome} ${b.slug} ${(b.featured || []).map((f) => f.nome).join(' ')}`.toLowerCase().includes(q));

        box.innerHTML = rows.length ? `
            <p class="shop-admin-hint"><i class="fa-solid fa-grip-vertical"></i> Trascina le righe per cambiare l'ordine nella lootbox.</p>
            <table class="admin-table">
                <thead><tr><th></th><th>Banner</th><th>Stato</th><th>Rate-up</th><th>Pity</th><th>Statistiche</th><th>Azioni</th></tr></thead>
                <tbody>
                ${rows.map((b) => {
                    const s = b.stats;
                    const played = s ? Number(s.vinti) + Number(s.persi) : 0;
                    const statsHtml = s
                        ? `<b>${num(s.pull)}</b> pull · ${num(s.utenti)} utenti<div class="admin-row-sub">${num(s.spesa)} Godos${played ? ` · 50/50 vinti ${Math.round(Number(s.vinti) / played * 100)}%` : ''}</div>`
                        : '<span class="admin-muted">Nessuna pull</span>';
                    const dates = [b.data_inizio ? `dal ${A.formatDate(b.data_inizio)}` : '', b.data_fine ? `al ${A.formatDate(b.data_fine)}` : ''].filter(Boolean).join(' ');
                    return `
                    <tr draggable="true" data-shop-row="${Number(b.id)}" class="${b.attivo ? '' : 'is-off'}">
                        <td data-label="Attivo">${switchHtml(b.attivo, `data-toggle-banner="${Number(b.id)}"`, 'Acceso nella lootbox')}</td>
                        <td data-label="Banner"><div class="admin-name-cell">${thumb(b.thumb, 'fa-solid fa-star')}<div>
                            <div class="admin-row-title">${e(b.nome)}</div>
                            <div class="admin-row-sub">${e(TIPI[b.tipo] || b.tipo)} · ${e(POOL[b.pool_modo] || b.pool_modo)} · ${b.costo > 0 ? `${num(b.costo)} Godos` : 'Gratis'}</div>
                        </div></div></td>
                        <td data-label="Stato">${STATUS[b.stato] || e(b.stato)}${dates ? `<div class="admin-row-sub">${e(dates)}</div>` : ''}</td>
                        <td data-label="Rate-up">${(b.featured || []).length ? b.featured.map((f) => e(f.nome)).join('<br>') : '<span class="admin-muted">—</span>'}</td>
                        <td data-label="Pity">${b.pity_gruppo === 'evento' ? 'Evento (condiviso)' : b.pity_gruppo === 'standard' ? 'Standard' : 'Dedicato'}</td>
                        <td data-label="Statistiche">${statsHtml}</td>
                        <td data-label="Azioni"><div class="admin-row-actions">
                            <button class="admin-btn admin-btn--small" data-edit-banner="${Number(b.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                            ${b.tipo !== 'standard' ? `<button class="admin-btn admin-btn--small" data-copy-banner="${Number(b.id)}" title="Duplica per una riproposta"><i class="fa-solid fa-clone"></i></button>
                            <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-banner="${Number(b.id)}" title="Elimina"><i class="fa-solid fa-trash"></i></button>` : ''}
                        </div></td>
                    </tr>`;
                }).join('')}
                </tbody>
            </table>` : emptyState('fa-solid fa-star', 'Nessun banner');

        $$('[data-toggle-banner]', box).forEach((input) => input.addEventListener('change', async () => {
            try {
                const res = await post(EP.banner, 'toggle', { id: Number(input.dataset.toggleBanner), attivo: input.checked ? 1 : 0 });
                showToast(res.message);
                loadBanners();
            } catch (error) {
                input.checked = !input.checked;
                showToast(error.message, true);
            }
        }));
        $$('[data-edit-banner]', box).forEach((b) => b.addEventListener('click', () => openBannerEditor(Number(b.dataset.editBanner))));
        $$('[data-copy-banner]', box).forEach((b) => b.addEventListener('click', () => openBannerEditor(Number(b.dataset.copyBanner), true)));
        $$('[data-delete-banner]', box).forEach((b) => b.addEventListener('click', () => {
            const banner = rows.find((r) => Number(r.id) === Number(b.dataset.deleteBanner));
            confirmBox('Eliminare il banner?', `<p class="admin-muted">«${e(banner?.nome || '')}» sparisce dalla lootbox. Lo storico delle pull resta. Se vuoi solo toglierlo per un po', spegnilo con l'interruttore.</p>`, async () => {
                await post(EP.banner, 'delete', { id: Number(b.dataset.deleteBanner) });
                showToast('Banner eliminato.');
                loadBanners();
            });
        }));

        enableRowDrag(box, 'data-shop-row', async (order) => {
            try {
                await post(EP.banner, 'reorder', { order });
                showToast('Ordine aggiornato.');
            } catch (error) {
                showToast(error.message, true);
            }
            loadBanners();
        });
    };

    /* ══ Banner: editor ══════════════════════════════════════════════════ */

    const PRESETS = {
        evento: {
            label: 'Evento 50/50', icon: 'fa-solid fa-star',
            help: 'Un rate-up segreto, pool standard, pity evento condiviso: come i banner di sempre.',
            values: { tipo: 'evento', pool_modo: 'standard', pity_modo: 'evento', costo_punti: 100, garanzia_multi: 1, destino_max: 1 },
        },
        multiplo: {
            label: 'Rate-up multiplo', icon: 'fa-solid fa-users',
            help: 'Più featured nello stesso banner: chi gioca sceglie il suo bersaglio (destino).',
            values: { tipo: 'evento', pool_modo: 'standard', pity_modo: 'evento', costo_punti: 100, garanzia_multi: 1, destino_max: 1 },
        },
        lista: {
            label: 'Solo selezionati', icon: 'fa-solid fa-list-check',
            help: 'Escono solo i personaggi che aggiungi al pool. Pity tutto suo.',
            values: { tipo: 'selezione', pool_modo: 'lista', pity_modo: 'dedicato', pity_soft: 50, pity_hard: 65, pity_soglia: 'segreto', costo_punti: 100, garanzia_multi: 1 },
        },
        categoria: {
            label: 'Per categoria', icon: 'fa-solid fa-tags',
            help: 'Tutti i personaggi di una categoria; quelli aggiunti dopo entrano da soli.',
            values: { tipo: 'selezione', pool_modo: 'categoria', pity_modo: 'dedicato', pity_soft: 50, pity_hard: 65, pity_soglia: 'segreto', costo_punti: 100, garanzia_multi: 1 },
        },
        principiante: {
            label: 'Principiante', icon: 'fa-solid fa-seedling',
            help: 'Venti pull a testa con un Segreto garantito entro la ventesima.',
            values: { tipo: 'principiante', pool_modo: 'standard', pity_modo: 'dedicato', pity_soft: 15, pity_hard: 20, pity_soglia: 'segreto', limite_pull_utente: 20, costo_punti: 100, garanzia_multi: 1 },
        },
    };

    const toLocalInput = (value) => {
        if (!value) return '';
        return String(value).replace(' ', 'T').slice(0, 16);
    };

    const field = (name, label, value, attrs = '', help = '', full = false, wrap = '') => `
        <div class="admin-field${full ? ' admin-field--full' : ''}" ${wrap}>
            <label for="gb-${name}">${label}</label>
            <input id="gb-${name}" name="${name}" value="${e(value ?? '')}" ${attrs}>
            ${help ? `<small class="shop-admin-help">${help}</small>` : ''}
        </div>`;

    const imageField = (name, label, value, help = '') => `
        <div class="admin-field">
            <label for="gb-${name}">${label}</label>
            <div class="shop-admin-media">
                <span class="shop-admin-media__preview" data-image-preview>${thumb(value)}</span>
                <div class="admin-input-group">
                    <input id="gb-${name}" name="${name}" value="${e(value || '')}" placeholder="nome.jpg, /img/... o https://" data-image-input>
                    <label class="admin-btn shop-admin-upload"><i class="fa-solid fa-upload"></i><input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-upload-image hidden></label>
                </div>
            </div>
            ${help ? `<small class="shop-admin-help">${help}</small>` : ''}
        </div>`;

    const check = (name, label, checked, help = '') => `
        <div class="admin-field shop-admin-check"><label><input type="checkbox" name="${name}" value="1" ${checked ? 'checked' : ''}> <span>${label}</span></label>${help ? `<small class="shop-admin-help">${help}</small>` : ''}</div>`;

    const select = (name, label, value, options, help = '', attrs = '', wrap = '') => `
        <div class="admin-field" ${wrap}>
            <label for="gb-${name}">${label}</label>
            <select id="gb-${name}" name="${name}" ${attrs}>${options.map(([v, l]) => `<option value="${e(v)}" ${String(v) === String(value ?? '') ? 'selected' : ''}>${e(l)}</option>`).join('')}</select>
            ${help ? `<small class="shop-admin-help">${help}</small>` : ''}
        </div>`;

    const section = (title, hint = '') => `<div class="shop-admin-subtitle admin-field--full"><strong>${title}</strong>${hint ? `<small>${hint}</small>` : ''}</div>`;

    const editorHtml = (b, m, isNew) => {
        const isStandard = b.tipo === 'standard';
        const profiles = m.profiles || {};
        const rarityRows = (m.rarities || []).map((r) => {
            const custom = (b.rarita || {})[r.key] || {};
            return `<tr>
                <td>${rarityDot(r.key)} ${e(r.label)}</td>
                <td><input type="number" step="0.001" min="0" max="1000" data-rarity-peso="${r.key}" value="${custom.peso ?? ''}" placeholder="${r.peso}" class="gacha-admin-num" aria-label="Peso ${e(r.label)}"></td>
                <td><input type="number" step="1" min="0" max="100" data-rarity-quota="${r.key}" value="${custom.quota ?? ''}" placeholder="50" class="gacha-admin-num" aria-label="Quota rate-up ${e(r.label)}"></td>
            </tr>`;
        }).join('');

        return `
        <div class="gacha-admin-editor">
            <form class="admin-form-grid shop-admin-form" data-banner-form novalidate>
                ${isNew ? `<div class="admin-field admin-field--full">
                    <label>Parti da un modello</label>
                    <div class="shop-admin-presets">${Object.entries(PRESETS).map(([k, p]) => `<button type="button" class="shop-admin-preset" data-preset="${k}" title="${e(p.help)}"><i class="${p.icon}"></i> ${e(p.label)}</button>`).join('')}</div>
                    <small class="shop-admin-help" data-preset-help>Scegli un modello per precompilare pool, pity e costo; poi aggiungi i personaggi.</small>
                </div>` : ''}

                ${section('Testi')}
                ${field('nome', 'Nome *', b.nome, 'maxlength="120" required')}
                ${field('nome_en', 'Nome (EN)', b.nome_en, 'maxlength="120"')}
                ${isStandard ? '' : field('slug', 'Slug', b.slug, 'maxlength="80"', 'Vuoto = dal nome.')}
                ${isStandard ? '' : select('tipo', 'Tipo', b.tipo || 'evento', [['evento', 'Evento'], ['selezione', 'Selezione'], ['principiante', 'Principiante']], 'Cambia solo l\'etichetta nella lootbox.')}
                <div class="admin-field admin-field--full"><label for="gb-descrizione">Descrizione</label><textarea id="gb-descrizione" name="descrizione" rows="2" maxlength="2000">${e(b.descrizione || '')}</textarea></div>
                <div class="admin-field admin-field--full"><label for="gb-descrizione_en">Descrizione (EN)</label><textarea id="gb-descrizione_en" name="descrizione_en" rows="2" maxlength="2000">${e(b.descrizione_en || '')}</textarea></div>

                ${section('Immagini', 'Si caricano in img/gacha. Senza arte si usa l\'immagine del primo rate-up.')}
                ${imageField('banner_img_url', 'Sfondo', b.banner_img_url)}
                ${imageField('arte_url', 'Arte (in primo piano)', b.arte_url)}
                ${imageField('thumb_url', 'Miniatura (barra laterale)', b.thumb_url, 'Vuota = lo sfondo.')}
                <div class="admin-field"><label>Colore</label><div class="shop-admin-color"><input type="color" name="colore_picker" value="${e(b.colore || '#a855f7')}" data-color-picker><input type="text" name="colore" value="${e(b.colore || '')}" data-color-text maxlength="7" placeholder="#a855f7"></div><small class="shop-admin-help">Vuoto = colore del tema.</small></div>

                ${section('Quando', isStandard ? 'Lo standard è sempre disponibile finché è acceso.' : 'Senza date resta aperto finché è acceso.')}
                ${check('attivo', 'Acceso', Number(b.attivo ?? 1) === 1)}
                ${isStandard ? '' : field('data_inizio', 'Inizio', toLocalInput(b.data_inizio), 'type="datetime-local"')}
                ${isStandard ? '' : field('data_fine', 'Fine', toLocalInput(b.data_fine), 'type="datetime-local"')}
                ${isStandard ? '' : check('anteprima', 'Mostra in «Prossimamente» prima dell\'inizio', Number(b.anteprima) === 1, 'Con il conto alla rovescia e i rate-up visibili.')}
                ${check('solo_premium', 'Solo utenti Premium', Number(b.solo_premium) === 1)}
                ${field('ordine', 'Ordine', b.ordine ?? 0, 'type="number" min="-1000" max="1000"', 'Più basso = più in alto.')}

                ${section('Costo e limiti')}
                ${field('costo_punti', 'Costo per pull (Godos)', b.costo_punti ?? 100, 'type="number" min="0" max="1000000"', `In Shards: <span data-cost-shards></span> (cambio attuale ${num(m.godos_per_shard)} Godos = 1 Shard).`)}
                ${field('pull_gratis_giorno', 'Pull gratis al giorno', b.pull_gratis_giorno ?? 0, 'type="number" min="0" max="10"', 'Si azzerano a mezzanotte.')}
                ${field('limite_pull_utente', 'Limite pull per utente', b.limite_pull_utente ?? '', 'type="number" min="1"', 'Vuoto = nessun limite.')}
                ${field('limite_pull_giorno', 'Limite pull al giorno', b.limite_pull_giorno ?? '', 'type="number" min="1"', 'Vuoto = nessun limite.')}

                ${isStandard ? section('Pool', 'Lo standard pesca da tutti i personaggi con «Pool Standard». Qui puoi solo escluderne o cambiarne il peso.') : section('Pool')}
                ${isStandard ? '' : select('pool_modo', 'Da dove escono i personaggi', b.pool_modo || 'standard', [['standard', 'Pool standard + rate-up'], ['lista', 'Solo quelli aggiunti qui sotto'], ['categoria', 'Tutta una categoria']])}
                ${isStandard ? '' : select('pool_categoria', 'Categoria', b.pool_categoria || '', [['', '— scegli —'], ...(m.categories || []).map((c) => [c, c])], '', '', 'data-show-pool="categoria"')}
                <div class="admin-field admin-field--full gacha-admin-pool">
                    <label for="gb-pool-search">Personaggi del banner</label>
                    <div class="gacha-admin-picker">
                        <input id="gb-pool-search" type="search" placeholder="Cerca un personaggio da aggiungere..." autocomplete="off" data-pool-search>
                        <div class="gacha-admin-results" data-pool-results hidden></div>
                    </div>
                    <small class="shop-admin-help"><b>Rate-up</b>: il 50/50 della sua fascia. <b>Pool</b>: entra nel pool (serve con «Solo selezionati») o cambia peso. <b>Escluso</b>: non esce mai qui. Il <b>peso</b> moltiplica le probabilità dentro la sua rarità (2 = doppie).</small>
                    <div data-pool-list></div>
                </div>

                ${section('Probabilità', 'Vuoto = valore base. Le rarità senza personaggi nel pool valgono zero e le altre si ridistribuiscono.')}
                <div class="admin-field admin-field--full">
                    <table class="admin-table gacha-admin-rarity"><thead><tr><th>Rarità</th><th>Peso</th><th>Quota rate-up %</th></tr></thead><tbody>${rarityRows}</tbody></table>
                    <small class="shop-admin-help">La quota è la probabilità che, uscita quella rarità, arrivi un rate-up (50 = il classico 50/50). Vale solo se il banner ha rate-up di quella fascia.</small>
                </div>

                ${section('Pity e garanzie')}
                ${isStandard ? `<p class="shop-admin-note admin-field--full"><i class="fa-solid fa-lock"></i> Pity standard fisso: soft ${profiles.standard?.soft}, hard ${profiles.standard?.hard}, garantito Speciale o superiore.</p>` : select('pity_modo', 'Pity', b.pity_modo || 'evento', [['evento', `Evento, condiviso con gli altri (soft ${profiles.evento?.soft}, hard ${profiles.evento?.hard})`], ['dedicato', 'Dedicato a questo banner']], 'Condiviso: pity e garantito passano da un banner evento all\'altro, come sempre.')}
                ${field('pity_soft', 'Soft pity', b.pity_soft ?? 65, 'type="number" min="1" max="1000"', 'Da qui le probabilità salgono a ogni pull.', false, 'data-show-pity="dedicato"')}
                ${field('pity_hard', 'Hard pity', b.pity_hard ?? 80, 'type="number" min="1" max="1000"', 'La pull numero N dà di sicuro la rarità garantita (N = questo valore).', false, 'data-show-pity="dedicato"')}
                ${select('pity_soglia', 'Rarità garantita', b.pity_soglia || 'segreto', (m.rarities || []).map((r) => [r.key, `${r.label} o superiore`]), '', '', 'data-show-pity="dedicato"')}
                ${isStandard ? '' : field('quota_featured', 'Quota del rate-up principale %', b.quota_featured ?? '', 'type="number" min="0" max="100" placeholder="50"', 'Fascia del pity (segreto+theone per l\'evento). Vuoto = 50, 100 = sempre il rate-up.')}
                ${isStandard ? '' : field('destino_max', 'Destino: rate-up non scelti prima del bersaglio', b.destino_max ?? 1, 'type="number" min="0" max="10"', 'Con più rate-up nella stessa fascia chi gioca sceglie il bersaglio. 0 = niente scelta.')}
                ${check('garanzia_multi', 'Garanzia multi: almeno un Epico o superiore ogni 10x', Number(b.garanzia_multi ?? 1) === 1)}
            </form>

            <aside class="gacha-admin-preview" data-preview aria-live="polite">
                <strong>Anteprima probabilità</strong>
                <div data-preview-body><p class="admin-muted">Calcolo...</p></div>
            </aside>
        </div>`;
    };

    const poolListHtml = (links, m) => {
        if (!links.length) return '<p class="admin-muted gacha-admin-empty">Nessun personaggio aggiunto.</p>';
        const order = { featured: 0, pool: 1, escluso: 2 };
        return `<table class="admin-table gacha-admin-links"><tbody>
            ${links.slice().sort((a, b) => order[a.ruolo] - order[b.ruolo]).map((l) => {
                const c = m.byId.get(Number(l.id)) || { nome: `#${l.id}`, rarita: 'comune' };
                return `<tr data-link="${Number(l.id)}">
                    <td>${thumb(c.img, 'fa-solid fa-user')}</td>
                    <td><div class="admin-row-title">${e(c.nome)}</div><div class="admin-row-sub">${rarityDot(c.rarita)} ${e(RARITY_LABELS[c.rarita] || c.rarita)}${c.categoria ? ` · ${e(c.categoria)}` : ''}${c.limitato ? ' · Limitato' : ''}${c.standard ? '' : ' · fuori dallo standard'}</div></td>
                    <td><select data-link-role aria-label="Ruolo">${[['featured', 'Rate-up'], ['pool', 'Pool'], ['escluso', 'Escluso']].map(([v, t]) => `<option value="${v}" ${l.ruolo === v ? 'selected' : ''}>${t}</option>`).join('')}</select></td>
                    <td><input type="number" step="0.1" min="0.01" max="100" value="${Number(l.peso || 1)}" data-link-peso class="gacha-admin-num" aria-label="Peso" ${l.ruolo === 'escluso' ? 'disabled' : ''}></td>
                    <td><button type="button" class="admin-btn admin-btn--small admin-btn--danger" data-link-remove title="Togli"><i class="fa-solid fa-xmark"></i></button></td>
                </tr>`;
            }).join('')}
        </tbody></table>`;
    };

    const previewHtml = (p) => {
        const rarities = Object.entries(p.rarita || {}).filter(([, v]) => v > 0).reverse();
        const max = Math.max(...rarities.map(([, v]) => v), 1);
        return `
            ${p.avvisi?.length ? `<ul class="gacha-admin-warnings">${p.avvisi.map((w) => `<li><i class="fa-solid fa-triangle-exclamation"></i> ${e(w)}</li>`).join('')}</ul>` : ''}
            <div class="gacha-admin-bars">
                ${rarities.map(([k, v]) => `<div class="gacha-admin-bar"><span>${rarityDot(k)} ${e(RARITY_LABELS[k] || k)} <small>(${num(p.pool?.[k])})</small></span><b>${pct(v, 4)}</b><i style="--w:${Math.max(1, v / max * 100)}%;--c:${RARITY_COLORS[k]}"></i></div>`).join('')}
            </div>
            <p class="admin-row-sub">${num(p.pool_totale)} personaggi nel pool.</p>
            ${(p.featured || []).length ? `<div class="gacha-admin-featured">
                <strong>Rate-up</strong>
                ${p.featured.map((f) => `<div><span>${rarityDot(f.rarita)} ${e(f.nome)}</span><b>${pct(f.prob, 4)}</b><small>a pull · ${pct(f.entro_hard, 1)} entro ${Number(p.pity?.hard)} pull senza pity</small></div>`).join('')}
            </div>` : ''}
            <div class="gacha-admin-pityinfo">
                <span>Soft ${p.pity?.soft} · Hard ${p.pity?.hard} · ${e(RARITY_LABELS[p.pity?.soglia] || p.pity?.soglia)}+ garantito</span>
                ${p.pity?.featured_entro ? `<span>Rate-up garantito entro <b>${p.pity.featured_entro}</b> pull${p.pity.quota < 100 ? ' (hard pity perso al 50/50, poi garantito)' : ''}</span>` : ''}
            </div>`;
    };

    const openBannerEditor = async (id = null, copy = false) => {
        let m;
        let banner = { tipo: 'evento', pool_modo: 'standard', pity_modo: 'evento', costo_punti: 100, attivo: 1, garanzia_multi: 1, destino_max: 1, personaggi: [], rarita: {} };
        try {
            m = await loadMeta();
            if (id) banner = (await get(EP.banner, { action: 'get', id })).banner;
        } catch (error) {
            showToast(error.message, true);
            return;
        }
        if (copy) {
            banner = { ...banner, id: null, slug: '', nome: `${banner.nome} (rerun)`, data_inizio: null, data_fine: null, attivo: 0 };
        }

        const isNew = !banner.id;
        let links = (banner.personaggi || []).map((l) => ({ id: Number(l.id), ruolo: l.ruolo, peso: Number(l.peso || 1) }));

        openModal(
            isNew ? (copy ? 'Duplica banner' : 'Nuovo banner') : 'Modifica banner',
            isNew ? '' : `#${banner.id} · ${banner.slug}`,
            editorHtml(banner, m, isNew && !copy),
            '<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" data-banner-save><i class="fa-solid fa-floppy-disk"></i> Salva</button>'
        );

        const body = $('#adminModalBody');
        const form = $('[data-banner-form]', body);
        const poolList = $('[data-pool-list]', body);
        const previewBody = $('[data-preview-body]', body);

        const readPayload = () => {
            const data = Object.fromEntries(new FormData(form).entries());
            $$('input[type="checkbox"][name]', form).forEach((input) => { data[input.name] = input.checked ? 1 : 0; });
            delete data.colore_picker;
            data.id = banner.id || 0;
            data.personaggi = links;
            data.rarita = {};
            $$('[data-rarity-peso]', form).forEach((input) => {
                const key = input.dataset.rarityPeso;
                const quota = $(`[data-rarity-quota="${key}"]`, form)?.value ?? '';
                if (input.value !== '' || quota !== '') data.rarita[key] = { peso: input.value, quota };
            });
            if (banner.tipo === 'standard') data.pity_modo = 'standard';
            return data;
        };

        let previewTimer = null;
        let previewSeq = 0;
        const refreshPreview = () => {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(async () => {
                const seq = ++previewSeq;
                try {
                    const res = await post(EP.banner, 'preview', readPayload());
                    if (seq === previewSeq) previewBody.innerHTML = previewHtml(res.preview);
                } catch (error) {
                    if (seq === previewSeq) previewBody.innerHTML = `<p class="gacha-admin-error"><i class="fa-solid fa-triangle-exclamation"></i> ${e(error.message)}</p>`;
                }
            }, 350);
        };

        const renderLinks = () => {
            poolList.innerHTML = poolListHtml(links, m);
            $$('[data-link]', poolList).forEach((row) => {
                const idLink = Number(row.dataset.link);
                const link = links.find((l) => l.id === idLink);
                $('[data-link-role]', row).addEventListener('change', (ev) => { link.ruolo = ev.target.value; renderLinks(); refreshPreview(); });
                $('[data-link-peso]', row).addEventListener('input', (ev) => { link.peso = Number(ev.target.value || 1); refreshPreview(); });
                $('[data-link-remove]', row).addEventListener('click', () => { links = links.filter((l) => l.id !== idLink); renderLinks(); refreshPreview(); });
            });
        };

        // Ricerca personaggi da aggiungere.
        const search = $('[data-pool-search]', body);
        const results = $('[data-pool-results]', body);
        const showResults = () => {
            const q = search.value.trim().toLowerCase();
            if (!q) { results.hidden = true; return; }
            const found = (m.characters || []).filter((c) => !links.some((l) => l.id === Number(c.id)) && `${c.nome} ${c.categoria || ''} ${c.rarita}`.toLowerCase().includes(q)).slice(0, 12);
            results.innerHTML = found.length ? found.map((c) => `
                <div class="gacha-admin-result">
                    ${thumb(c.img, 'fa-solid fa-user')}
                    <span><b>${e(c.nome)}</b><small>${rarityDot(c.rarita)} ${e(RARITY_LABELS[c.rarita] || c.rarita)}${c.categoria ? ` · ${e(c.categoria)}` : ''}${c.limitato ? ' · Limitato' : ''}</small></span>
                    <button type="button" class="admin-btn admin-btn--small" data-add="${Number(c.id)}" data-role="featured">Rate-up</button>
                    <button type="button" class="admin-btn admin-btn--small" data-add="${Number(c.id)}" data-role="pool">Pool</button>
                    <button type="button" class="admin-btn admin-btn--small" data-add="${Number(c.id)}" data-role="escluso">Escludi</button>
                </div>`).join('') : '<p class="admin-muted" style="padding:.6rem">Nessun risultato.</p>';
            results.hidden = false;
            $$('[data-add]', results).forEach((btn) => btn.addEventListener('click', () => {
                links.push({ id: Number(btn.dataset.add), ruolo: btn.dataset.role, peso: 1 });
                search.value = '';
                results.hidden = true;
                renderLinks();
                refreshPreview();
                search.focus();
            }));
        };
        search.addEventListener('input', showResults);
        search.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') ev.preventDefault(); });

        // Immagini: caricamento e anteprima.
        $$('[data-upload-image]', form).forEach((input) => input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) return;
            const text = $('[data-image-input]', input.closest('.admin-field'));
            try {
                showToast('Caricamento immagine...');
                text.value = await uploadImage(file);
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
        const picker = $('[data-color-picker]', form);
        const colorText = $('[data-color-text]', form);
        picker?.addEventListener('input', () => { colorText.value = picker.value; });

        // Campi che dipendono da altri.
        const costShards = $('[data-cost-shards]', form);
        const syncVisibility = () => {
            const poolMode = form.elements.pool_modo?.value || 'standard';
            $$('[data-show-pool]', form).forEach((el) => { el.hidden = el.dataset.showPool !== poolMode; });
            const pityMode = form.elements.pity_modo?.value || 'standard';
            $$('[data-show-pity]', form).forEach((el) => { el.hidden = el.dataset.showPity !== pityMode; });
            const cost = Number(form.elements.costo_punti?.value || 0);
            if (costShards) costShards.textContent = cost > 0 ? `${Math.ceil(cost / (m.godos_per_shard || 100))} Shard` : 'gratis';
        };

        // Modelli di partenza (solo per un banner nuovo).
        $$('[data-preset]', form).forEach((btn) => btn.addEventListener('click', () => {
            const preset = PRESETS[btn.dataset.preset];
            Object.entries(preset.values).forEach(([name, value]) => {
                const el = form.elements[name];
                if (!el) return;
                if (el.type === 'checkbox') el.checked = Number(value) === 1;
                else el.value = value;
            });
            $$('[data-preset]', form).forEach((b) => b.classList.toggle('is-active', b === btn));
            const help = $('[data-preset-help]', form);
            if (help) help.textContent = preset.help;
            syncVisibility();
            refreshPreview();
        }));

        form.addEventListener('input', () => { syncVisibility(); refreshPreview(); });
        form.addEventListener('change', () => { syncVisibility(); refreshPreview(); });

        $('[data-banner-save]')?.addEventListener('click', async (ev) => {
            const btn = ev.currentTarget;
            btn.disabled = true;
            try {
                const res = await post(EP.banner, 'save', readPayload());
                closeModal();
                showToast(res.avvisi?.length ? `Banner salvato. Attenzione: ${res.avvisi[0]}` : 'Banner salvato.', Boolean(res.avvisi?.length));
                loadBanners();
            } catch (error) {
                showToast(error.message, true);
            } finally {
                btn.disabled = false;
            }
        });

        renderLinks();
        syncVisibility();
        refreshPreview();
    };

    /* ══ Categorie ═══════════════════════════════════════════════════════ */

    let categoriesCache = { categories: [], badges: [] };

    const loadCategories = async () => {
        const box = $('[data-gacha-admin="categorie"]');
        if (!box) return;
        setLoading(box);
        let data;
        try {
            data = await get(EP.categorie, { action: 'list' });
        } catch (error) {
            box.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore categorie', error.message);
            return;
        }
        if (data.ready === false) {
            box.innerHTML = `<p class="shop-admin-note"><i class="fa-solid fa-circle-info"></i> ${e(data.message)}</p>`;
            $('#createGachaCategoryBtn')?.setAttribute('disabled', 'disabled');
            return;
        }
        categoriesCache = data;
        const rows = data.categories || [];

        box.innerHTML = `
            ${rows.length ? `<p class="shop-admin-hint"><i class="fa-solid fa-grip-vertical"></i> Trascina le righe per cambiare l'ordine dei filtri nell'inventario.</p>
            <table class="admin-table">
                <thead><tr><th>Categoria</th><th>Personaggi</th><th>Premio collezione</th><th>Azioni</th></tr></thead>
                <tbody>
                ${rows.map((c) => `
                    <tr draggable="true" data-shop-row="${Number(c.id)}">
                        <td data-label="Categoria"><div class="admin-name-cell">
                            <span class="gacha-admin-cat" style="--c:${e(c.colore || '#94a3b8')}"><i class="${e(c.icona || 'fa-solid fa-tag')}"></i></span>
                            <div><div class="admin-row-title">${e(c.nome)}</div><div class="admin-row-sub">${c.nome_en ? `EN: ${e(c.nome_en)} · ` : ''}${e(c.slug)}</div></div>
                        </div></td>
                        <td data-label="Personaggi">${num(c.personaggi)}${Number(c.limitati) ? `<div class="admin-row-sub">${num(c.limitati)} limitati</div>` : ''}</td>
                        <td data-label="Premio">${Number(c.premio_godos) > 0 ? `${num(c.premio_godos)} Godos` : ''}${c.premio_badge_id ? `${Number(c.premio_godos) > 0 ? ' + ' : ''}badge` : ''}${!Number(c.premio_godos) && !c.premio_badge_id ? '<span class="admin-muted">Nessuno</span>' : ''}</td>
                        <td data-label="Azioni"><div class="admin-row-actions">
                            <button class="admin-btn admin-btn--small" data-edit-category="${Number(c.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                            <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-category="${Number(c.id)}" title="Elimina"><i class="fa-solid fa-trash"></i></button>
                        </div></td>
                    </tr>`).join('')}
                </tbody>
            </table>` : emptyState('fa-solid fa-tags', 'Nessuna categoria')}
            ${(data.orphans || []).length ? `<div class="shop-admin-subtitle"><strong>Categorie senza scheda</strong><small>Scritte nei personaggi ma senza colore, icona o premio. Creale per gestirle da qui.</small></div>
                <div class="gacha-admin-orphans">${data.orphans.map((o) => `<button type="button" class="shop-admin-preset" data-create-orphan="${e(o.nome)}"><i class="fa-solid fa-plus"></i> ${e(o.nome)} <small>(${num(o.personaggi)})</small></button>`).join('')}</div>` : ''}`;

        $$('[data-edit-category]', box).forEach((b) => b.addEventListener('click', () => openCategoryForm(rows.find((c) => Number(c.id) === Number(b.dataset.editCategory)))));
        $$('[data-create-orphan]', box).forEach((b) => b.addEventListener('click', () => openCategoryForm({ nome: b.dataset.createOrphan })));
        $$('[data-delete-category]', box).forEach((b) => b.addEventListener('click', () => {
            const c = rows.find((r) => Number(r.id) === Number(b.dataset.deleteCategory));
            confirmBox('Eliminare la categoria?', `<p class="admin-muted">«${e(c?.nome || '')}» si può eliminare solo se nessun personaggio la usa.</p>`, async () => {
                await post(EP.categorie, 'delete', { id: Number(c.id) });
                showToast('Categoria eliminata.');
                A.refreshCharacterCategories?.();
                meta = null;
                loadCategories();
            });
        }));

        enableRowDrag(box, 'data-shop-row', async (order) => {
            try {
                await post(EP.categorie, 'reorder', { order });
                showToast('Ordine aggiornato.');
            } catch (error) {
                showToast(error.message, true);
            }
            loadCategories();
        });
    };

    const ICONS = ['fa-solid fa-tag', 'fa-solid fa-star', 'fa-solid fa-crown', 'fa-solid fa-user-group', 'fa-solid fa-face-grin-squint-tears', 'fa-solid fa-music', 'fa-solid fa-cat', 'fa-solid fa-hourglass-half', 'fa-solid fa-gamepad', 'fa-solid fa-fire', 'fa-solid fa-dragon', 'fa-solid fa-film', 'fa-solid fa-bolt', 'fa-solid fa-heart'];

    const openCategoryForm = (item = {}) => {
        const badges = categoriesCache.badges || [];
        openModal(item.id ? 'Modifica categoria' : 'Nuova categoria', item.id ? `#${item.id}` : '', `
            <form class="admin-form-grid shop-admin-form" data-category-form novalidate>
                ${field('nome', 'Nome *', item.nome, 'maxlength="100" required', item.id ? 'Rinominarla aggiorna anche i personaggi e i banner che la usano.' : '')}
                ${field('nome_en', 'Nome (EN)', item.nome_en, 'maxlength="100"')}
                <div class="admin-field"><label>Colore</label><div class="shop-admin-color"><input type="color" value="${e(item.colore || '#a855f7')}" data-color-picker><input type="text" name="colore" value="${e(item.colore || '')}" data-color-text maxlength="7" placeholder="#a855f7"></div></div>
                <div class="admin-field"><label for="gb-icona">Icona</label>
                    <div class="shop-admin-presets" data-icons>${ICONS.map((ic) => `<button type="button" class="shop-admin-preset ${item.icona === ic ? 'is-active' : ''}" data-icon="${ic}" title="${ic}"><i class="${ic}"></i></button>`).join('')}</div>
                    <input id="gb-icona" name="icona" value="${e(item.icona || '')}" placeholder="fa-solid fa-tag" style="margin-top:.4rem">
                </div>
                ${section('Premio della collezione', 'Chi trova tutti i personaggi della categoria (tranne i «nascosti») lo riscuote dall\'inventario e lo riceve in posta.')}
                ${field('premio_godos', 'Godos', item.premio_godos ?? 0, 'type="number" min="0" max="1000000"')}
                <div class="admin-field"><label for="gb-badge">Badge</label>
                    <select id="gb-badge" name="premio_badge_id"><option value="">— nessuno —</option>${badges.map((b) => `<option value="${Number(b.id)}" ${Number(item.premio_badge_id) === Number(b.id) ? 'selected' : ''}>${e(b.name)}</option>`).join('')}</select>
                </div>
            </form>`,
            '<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" data-category-save><i class="fa-solid fa-floppy-disk"></i> Salva</button>');

        const form = $('#adminModalBody [data-category-form]');
        const picker = $('[data-color-picker]', form);
        const text = $('[data-color-text]', form);
        picker.addEventListener('input', () => { text.value = picker.value; });
        $$('[data-icon]', form).forEach((btn) => btn.addEventListener('click', () => {
            form.elements.icona.value = btn.dataset.icon;
            $$('[data-icon]', form).forEach((b) => b.classList.toggle('is-active', b === btn));
        }));

        $('[data-category-save]')?.addEventListener('click', async () => {
            const payload = Object.fromEntries(new FormData(form).entries());
            try {
                await post(EP.categorie, 'save', { ...payload, id: item.id || 0 });
                closeModal();
                showToast('Categoria salvata.');
                A.refreshCharacterCategories?.();
                meta = null;
                loadCategories();
            } catch (error) {
                showToast(error.message, true);
            }
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        $('#createGachaBannerBtn')?.addEventListener('click', () => openBannerEditor());
        $('#createGachaCategoryBtn')?.addEventListener('click', () => openCategoryForm());
    });

    A.registerSection('gacha-banner', loadBanners);
    A.registerSection('gacha-categorie', loadCategories);
})();
