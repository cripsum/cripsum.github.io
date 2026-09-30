/*
 * Pannello admin: la sezione Personaggi.
 *
 * Lista con filtri e ricerca, catalogo e banner standard modificabili dalla
 * riga; scheda in una finestra con l'anteprima della card (anche come la
 * vede chi non ce l'ha), i file della pull (immagine, audio, video) e il
 * kit dei duelli calcolato dal server con i valori del form. Le abilita'
 * vere stanno in includes/game_config.php: qui si vedono e basta.
 *
 * API: /api/admin/characters.php. Usa gli strumenti dei form dello shop
 * (A.forms, da admin-shop.js: va caricato dopo).
 */
(() => {
    'use strict';

    const A = window.CripsumAdmin;
    if (!A || !A.forms) return;

    const { api, confirmBox, showToast, thumb, setLoading, emptyState } = A;
    const { fields, sectionTitle, bindForm, formModal } = A.forms;
    const e = A.escapeHtml;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const EP = 'characters.php';
    const get = (params = {}) => api(`${EP}?${new URLSearchParams(params)}`);
    const post = (action, body = {}) => api(EP, { method: 'POST', body: { action, ...body } });
    const num = (value) => Number(value || 0).toLocaleString('it-IT');
    const debounce = (fn, wait = 300) => {
        let t = null;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
    };

    const PER_PAGE = 40;
    const st = { rarita: '', categoria: '', ruolo: '', catalogo: '', limitati: false, generici: false, sort: 'recenti', page: 1 };
    let ctx = null;

    const ROLE_ICONS = {
        Tank: 'fa-solid fa-shield-halved', Bruiser: 'fa-solid fa-hand-fist', DPS: 'fa-solid fa-khanda',
        'Burst DPS': 'fa-solid fa-burst', 'Sub DPS': 'fa-solid fa-bolt', Support: 'fa-solid fa-hands-holding-circle',
        Healer: 'fa-solid fa-heart-pulse', Controller: 'fa-solid fa-snowflake', Debuffer: 'fa-solid fa-skull-crossbones',
        Buffer: 'fa-solid fa-arrow-trend-up',
    };
    const CATALOG = [['visibile', 'Visibile'], ['segreto', '???'], ['nascosto', 'Nascosto']];
    const TOP = ['segreto', 'theone'];

    const traitsOf = (value) => String(value || '').split(/[;\n]/).map((t) => t.trim()).filter(Boolean);
    const needsKit = (c) => TOP.includes(c.rarita) && !c.kit.unique;

    /* Un file salvato relativo alla sua cartella (x.mp3, personaggi/x.jpg), pronto per src. */
    const mediaUrl = (value, base) => {
        const v = String(value || '').trim();
        if (!v) return '';
        if (/^https?:\/\//i.test(v)) return v;
        const path = v.startsWith('/') ? v : `${base}${v}`;
        return path.split('/').map((part) => {
            try { return encodeURIComponent(decodeURIComponent(part)); } catch (_) { return encodeURIComponent(part); }
        }).join('/');
    };

    const rarityDef = (key) => ctx?.rarities.find((r) => r.key === key) || { key, label: key || '—', color: '#9ca3af' };
    const rarityPill = (key) => {
        const r = rarityDef(key);
        return `<span class="admin-rarity" style="--c:${e(r.color)}">${e(r.label)}</span>`;
    };
    const categoryChip = (name) => {
        if (!name) return '';
        const c = ctx?.categories.find((x) => x.nome.toLowerCase() === String(name).toLowerCase());
        return `<span class="char-admin-cat" style="--cc:${e(c?.colore || '#94a3b8')}">${c?.icona ? `<i class="${e(c.icona)}"></i>` : ''}${e(c?.nome || name)}</span>`;
    };

    /* ════════════════════════════════════════════════════════════════
       LISTA
       ════════════════════════════════════════════════════════════════ */

    const filtered = () => {
        const q = String(A.getQuery() || '').toLowerCase().trim();
        const qid = q.replace(/^#/, '');
        let rows = ctx.characters.filter((c) => !q || String(c.id) === qid
            || [c.nome, c.categoria, c.ruolo, c.caratteristiche].some((t) => String(t || '').toLowerCase().includes(q)));
        if (st.rarita) rows = rows.filter((c) => c.rarita === st.rarita);
        if (st.categoria) rows = rows.filter((c) => c.categoria.toLowerCase() === st.categoria.toLowerCase());
        if (st.ruolo) rows = rows.filter((c) => (c.ruolo || 'DPS') === st.ruolo);
        if (st.catalogo) rows = rows.filter((c) => c.catalogo === st.catalogo);
        if (st.limitati) rows = rows.filter((c) => c.limitato === 1);
        if (st.generici) rows = rows.filter(needsKit);

        const rank = (key) => ctx.rarities.findIndex((r) => r.key === key);
        const byName = (a, b) => a.nome.localeCompare(b.nome, 'it');
        const sorts = {
            recenti: (a, b) => b.id - a.id,
            nome: byName,
            rarita: (a, b) => rank(b.rarita) - rank(a.rarita) || byName(a, b),
            utenti: (a, b) => b.utenti - a.utenti || byName(a, b),
        };
        return rows.sort(sorts[st.sort] || sorts.recenti);
    };

    const bannerNote = (c) => {
        if (!c.banner.length) return '';
        const live = c.banner.filter((b) => b.stato === 'attivo');
        const first = live[0] || c.banner[0];
        const more = c.banner.length > 1 ? ` +${c.banner.length - 1}` : '';
        const title = c.banner.map((b) => `${b.nome} (${b.stato})`).join('\n');
        return `<span class="char-admin-banner${live.length ? ' is-live' : ''}" title="${e(title)}"><i class="fa-solid fa-star"></i> ${e(first.nome)}${more}</span>`;
    };

    const roleCell = (c) => {
        const role = c.ruolo || 'DPS';
        const mismatch = c.kit.role && c.kit.role !== role;
        const kit = c.kit.unique
            ? `<small class="is-unique${mismatch ? ' is-warn' : ''}" title="${e(mismatch ? `Il kit è scritto per ${c.kit.role}: con ${role} cambiano statistiche e bonus del ruolo` : 'Abilità scritte apposta in includes/game_config.php')}"><i class="fa-solid ${mismatch ? 'fa-triangle-exclamation' : 'fa-wand-magic-sparkles'}"></i> Kit unico</small>`
            : `<small${needsKit(c) ? ' class="is-todo" title="Segreto con le abilità generiche del ruolo"' : ''}>Kit del ruolo</small>`;
        return `<div class="char-admin-role"><span><i class="${ROLE_ICONS[role] || 'fa-solid fa-user'}"></i> ${e(role)}</span>${kit}</div>`;
    };

    const mediaIcons = (c) => {
        const needsVideo = TOP.includes(c.rarita);
        const icon = (ok, cls, label, warn = false) => `<i class="${cls}${ok ? ' is-on' : (warn ? ' is-warn' : '')}" title="${e(label)}" aria-label="${e(label)}"></i>`;
        return `<span class="char-admin-media">
            ${icon(c.img_url, 'fa-solid fa-image', c.img_url ? 'Immagine' : 'Manca l\'immagine', true)}
            ${ctx.schema.audio ? icon(c.audio_url, 'fa-solid fa-music', c.audio_url ? 'Audio della pull' : 'Nessun audio alla pull') : ''}
            ${ctx.schema.video ? icon(c.video_url, 'fa-solid fa-film', c.video_url ? 'Video della pull' : (needsVideo ? 'Manca il video: Segreti e The One lo usano alla pull' : 'Nessun video (lo usano solo Segreti e The One)'), needsVideo) : ''}
        </span>`;
    };

    const switchHtml = (checked, attrs, label) => `
        <label class="shop-admin-switch" title="${e(label)}">
            <input type="checkbox" ${checked ? 'checked' : ''} ${attrs} aria-label="${e(label)}">
            <span></span>
        </label>`;

    const renderList = (root) => {
        const schema = ctx.schema;
        const rows = filtered();
        const pages = Math.max(1, Math.ceil(rows.length / PER_PAGE));
        st.page = Math.min(st.page, pages);
        const shown = rows.slice((st.page - 1) * PER_PAGE, st.page * PER_PAGE);
        const generic = ctx.characters.filter(needsKit);
        const filtering = Boolean(A.getQuery() || st.rarita || st.categoria || st.ruolo || st.catalogo || st.limitati || st.generici);

        const select = (key, first, options) => `
            <select class="admin-input" data-f="${key}" aria-label="${e(first)}">
                <option value="">${e(first)}</option>
                ${options.map(([v, l]) => `<option value="${e(v)}" ${String(st[key]) === String(v) ? 'selected' : ''}>${e(l)}</option>`).join('')}
            </select>`;

        root.innerHTML = `
            ${generic.length ? `
            <div class="char-admin-note">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
                <span><b>${generic.length === 1 ? '1 segreto usa' : `${generic.length} segreti usano`} il kit del ruolo:</b> nei duelli ha${generic.length === 1 ? '' : 'nno'} abilità generiche. Quelle uniche si scrivono in <code>includes/game_config.php</code> con l'id del personaggio.</span>
                <button type="button" class="admin-btn admin-btn--small" data-f-generic>${st.generici ? 'Mostra tutti' : 'Mostra solo questi'}</button>
            </div>` : ''}
            <div class="shop-admin-bar">
                <div class="pages-admin-filters char-admin-filters">
                    ${select('rarita', 'Tutte le rarità', ctx.rarities.map((r) => [r.key, r.label]))}
                    ${ctx.categories.length ? select('categoria', 'Tutte le categorie', ctx.categories.map((c) => [c.nome, c.nome])) : ''}
                    ${schema.ruolo ? select('ruolo', 'Tutti i ruoli', ctx.roles.map((r) => [r, r])) : ''}
                    ${schema.catalogo ? select('catalogo', 'Catalogo: tutti', [['visibile', 'Visibili'], ['segreto', 'Come ???'], ['nascosto', 'Nascosti']]) : ''}
                    <select class="admin-input" data-f="sort" aria-label="Ordina">
                        ${[['recenti', 'Più recenti'], ['nome', 'Nome A-Z'], ['rarita', 'Rarità più alta'], ['utenti', 'Più posseduti']].map(([v, l]) => `<option value="${v}" ${st.sort === v ? 'selected' : ''}>${l}</option>`).join('')}
                    </select>
                    ${schema.limitato ? `<button type="button" class="admin-btn char-admin-toggle" data-f-limited aria-pressed="${st.limitati}"><i class="fa-solid fa-hourglass-half"></i> Solo limitati</button>` : ''}
                </div>
                <button type="button" class="admin-btn admin-btn--primary" data-new-char><i class="fa-solid fa-plus"></i> Nuovo personaggio</button>
            </div>
            <p class="admin-muted char-admin-count">${filtering ? `${num(rows.length)} di ${num(ctx.characters.length)} personaggi` : `${num(ctx.characters.length)} personaggi`}${pages > 1 ? ` · pagina ${st.page} di ${pages}` : ''}</p>
            ${shown.length ? `
            <table class="admin-table char-admin-table">
                <thead><tr><th>Personaggio</th><th>Rarità</th><th>Duelli</th><th>File</th>${schema.catalogo ? '<th>Catalogo</th>' : ''}${schema.pool ? '<th>Standard</th>' : ''}<th>Azioni</th></tr></thead>
                <tbody>
                    ${shown.map((c) => `
                        <tr class="${c.catalogo === 'nascosto' ? 'is-off' : ''}">
                            <td data-label="Personaggio"><div class="admin-name-cell">${thumb(c.image_url || c.img_url, 'fa-solid fa-box-open')}<div class="char-admin-name">
                                <div class="admin-row-title">${e(c.nome)}</div>
                                <div class="admin-row-sub pages-admin-sub">
                                    <span>#${Number(c.id)}</span>
                                    ${categoryChip(c.categoria)}
                                    ${c.limitato ? '<span class="admin-pill admin-pill--limited">Limitato</span>' : ''}
                                    <span title="Utenti che lo hanno nell'inventario">${c.utenti ? `${num(c.utenti)} ${c.utenti === 1 ? 'utente' : 'utenti'}` : 'nessuno ce l\'ha'}</span>
                                    ${bannerNote(c)}
                                </div>
                            </div></div></td>
                            <td data-label="Rarità">${rarityPill(c.rarita)}</td>
                            <td data-label="Duelli">${roleCell(c)}</td>
                            <td data-label="File">${mediaIcons(c)}</td>
                            ${schema.catalogo ? `<td data-label="Catalogo">
                                <select class="admin-input shop-admin-state char-admin-catalog" data-catalog="${Number(c.id)}" aria-label="Catalogo di ${e(c.nome)}" title="Come lo vede chi non ce l'ha">
                                    ${CATALOG.map(([v, l]) => `<option value="${v}" ${c.catalogo === v ? 'selected' : ''}>${l}</option>`).join('')}
                                </select>
                            </td>` : ''}
                            ${schema.pool ? `<td data-label="Standard">${switchHtml(c.in_pool_standard === 1, `data-pool="${Number(c.id)}"`, `Nel banner standard: ${c.nome}`)}</td>` : ''}
                            <td data-label="Azioni"><div class="admin-row-actions">
                                <button class="admin-btn admin-btn--small" data-edit-char="${Number(c.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                                <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-char="${Number(c.id)}" title="Elimina" aria-label="Elimina ${e(c.nome)}"><i class="fa-solid fa-trash"></i></button>
                            </div></td>
                        </tr>`).join('')}
                </tbody>
            </table>` : emptyState('fa-solid fa-box-open', 'Nessun personaggio', ctx.characters.length ? 'Nessun risultato con questi filtri.' : 'Crea il primo con «Nuovo personaggio».')}`;

        A.pagination('#charactersPagination', { page: st.page, pages }, (page) => {
            st.page = page;
            renderList(root);
            root.scrollIntoView({ behavior: 'instant', block: 'start' });
        });

        const find = (id) => ctx.characters.find((c) => c.id === Number(id));
        const rerender = () => { st.page = 1; renderList(root); };

        $$('select[data-f]', root).forEach((el) => el.addEventListener('change', () => { st[el.dataset.f] = el.value; rerender(); }));
        $('[data-f-limited]', root)?.addEventListener('click', () => { st.limitati = !st.limitati; rerender(); });
        $('[data-f-generic]', root)?.addEventListener('click', () => { st.generici = !st.generici; rerender(); });
        $('[data-new-char]', root).addEventListener('click', () => characterForm(null));
        $$('[data-edit-char]', root).forEach((b) => b.addEventListener('click', () => characterForm(find(b.dataset.editChar))));

        $$('[data-catalog]', root).forEach((sel) => sel.addEventListener('change', async () => {
            const c = find(sel.dataset.catalog);
            try {
                const res = await post('set_flag', { id: c.id, field: 'catalogo', value: sel.value });
                c.catalogo = sel.value;
                sel.closest('tr').classList.toggle('is-off', c.catalogo === 'nascosto');
                showToast(res.message);
            } catch (error) {
                sel.value = c.catalogo;
                showToast(error.message, true);
            }
        }));

        $$('[data-pool]', root).forEach((input) => input.addEventListener('change', async () => {
            const c = find(input.dataset.pool);
            try {
                const res = await post('set_flag', { id: c.id, field: 'in_pool_standard', value: input.checked ? 1 : 0 });
                c.in_pool_standard = input.checked ? 1 : 0;
                showToast(res.message);
            } catch (error) {
                input.checked = !input.checked;
                showToast(error.message, true);
            }
        }));

        $$('[data-delete-char]', root).forEach((b) => b.addEventListener('click', () => {
            const c = find(b.dataset.deleteChar);
            const owners = c.utenti
                ? `<b>${c.utenti === 1 ? 'Ce l\'ha 1 utente' : `Ce l'hanno ${num(c.utenti)} utenti`}</b>: sparisce anche dal loro inventario, con copie e livelli.`
                : 'Non ce l\'ha ancora nessuno.';
            confirmBox('Eliminare il personaggio?', `
                <p class="admin-muted">«${e(c.nome)}» sparisce dal gacha, dai banner e dalle wishlist. ${owners} Non si può annullare.</p>
                ${c.utenti ? '<p class="admin-muted">Per toglierlo solo dalle pull: spegni «Standard» e toglilo dai banner.</p>' : ''}`, async () => {
                const res = await post('delete', { id: c.id });
                showToast(res.message);
                load();
            });
        }));
    };

    /* ════════════════════════════════════════════════════════════════
       SCHEDA
       ════════════════════════════════════════════════════════════════ */

    /* Caratteristiche come etichette: Invio o ";" ne aggiunge una, clic per correggerla. */
    const traitsField = (name, label, value, placeholder) => `
        <div class="admin-field admin-field--full" data-traits>
            <label for="char-${name}-new">${e(label)}</label>
            <div class="char-admin-traits">
                <ul data-traits-list></ul>
                <input id="char-${name}-new" type="text" maxlength="150" placeholder="${e(placeholder)}" autocomplete="off" data-traits-input>
            </div>
            <input type="hidden" name="${name}" value="${e(traitsOf(value).join('; '))}" data-traits-value>
            <small class="shop-admin-help">Invio o <code>;</code> per aggiungerne una, clic su una per correggerla. Nell'inventario diventano un elenco. <span class="char-admin-count-chars" data-traits-count></span></small>
        </div>`;

    const bindTraits = (form, refresh) => {
        $$('[data-traits]', form).forEach((wrap) => {
            const list = $('[data-traits-list]', wrap);
            const input = $('[data-traits-input]', wrap);
            const hidden = $('[data-traits-value]', wrap);
            const count = $('[data-traits-count]', wrap);
            const items = traitsOf(hidden.value);
            let editAt = null;

            const sync = () => {
                hidden.value = items.join('; ');
                list.innerHTML = items.map((t, i) => `
                    <li><button type="button" class="char-admin-trait" data-trait-edit="${i}" title="Correggi">${e(t)}</button><button type="button" class="char-admin-trait-x" data-trait-remove="${i}" aria-label="Togli «${e(t)}»"><i class="fa-solid fa-xmark"></i></button></li>`).join('');
                count.textContent = `${hidden.value.length}/300`;
                count.classList.toggle('is-over', hidden.value.length > 300);
                refresh();
            };

            const add = (text) => {
                const parts = traitsOf(text);
                if (!parts.length) return false;
                if (editAt !== null) items.splice(Math.min(editAt, items.length), 0, ...parts);
                else items.push(...parts);
                editAt = null;
                sync();
                return true;
            };

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ';') {
                    // Invio nel form lo salverebbe.
                    event.preventDefault();
                    if (add(input.value)) input.value = '';
                } else if (event.key === 'Backspace' && !input.value && items.length) {
                    event.preventDefault();
                    editAt = items.length - 1;
                    input.value = items.pop();
                    sync();
                }
            });
            // Un testo incollato con dei ";" diventa piu' etichette.
            input.addEventListener('input', () => {
                if (!input.value.includes(';')) return;
                const parts = input.value.split(';');
                const rest = parts.pop();
                add(parts.join(';'));
                input.value = rest.trimStart();
            });
            // Anche quello lasciato scritto conta: Salva toglie il fuoco prima del clic.
            input.addEventListener('blur', () => {
                if (add(input.value)) input.value = '';
            });

            list.addEventListener('click', (event) => {
                const remove = event.target.closest('[data-trait-remove]');
                const edit = event.target.closest('[data-trait-edit]');
                if (remove) {
                    items.splice(Number(remove.dataset.traitRemove), 1);
                    if (editAt !== null && editAt > items.length) editAt = items.length;
                    sync();
                    input.focus();
                } else if (edit) {
                    add(input.value);
                    const i = Number(edit.dataset.traitEdit);
                    editAt = i;
                    input.value = items.splice(i, 1)[0] || '';
                    sync();
                    input.focus();
                }
            });

            sync();
        });
    };

    /* Audio e video della pull: nome del file o link, caricamento e anteprima. */
    const fileField = ({ name, label, kind, value, help }) => `
        <div class="admin-field admin-field--full" data-file-field="${kind}">
            <label for="char-${name}">${e(label)}</label>
            <div class="admin-input-group">
                <input id="char-${name}" type="text" name="${name}" value="${e(value || '')}" placeholder="${kind === 'audio' ? 'nome.mp3 (cartella audio/) oppure https://...' : 'nome.mp4 (cartella vid/) oppure https://...'}" data-file-input>
                <label class="admin-btn shop-admin-upload"><i class="fa-solid fa-upload"></i> Carica<input type="file" accept="${kind === 'audio' ? 'audio/mpeg,audio/ogg,audio/mp4,audio/aac,audio/wav,.mp3,.ogg,.m4a,.aac,.wav' : 'video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov'}" data-file-upload hidden></label>
            </div>
            <span class="pages-admin-progress" data-file-progress hidden><i></i></span>
            ${kind === 'audio'
                ? '<audio class="esports-admin-audio" controls preload="none" data-file-preview hidden></audio>'
                : '<video class="char-admin-video" controls muted playsinline preload="metadata" data-file-preview hidden></video>'}
            ${help ? `<small class="shop-admin-help">${help}</small>` : ''}
        </div>`;

    const bindFiles = (form, refresh) => {
        $$('[data-file-field]', form).forEach((wrap) => {
            const kind = wrap.dataset.fileField;
            const input = $('[data-file-input]', wrap);
            const upload = $('[data-file-upload]', wrap);
            const preview = $('[data-file-preview]', wrap);
            const progress = $('[data-file-progress]', wrap);
            const base = kind === 'audio' ? '/audio/' : '/vid/';

            const setPreview = () => {
                const src = mediaUrl(input.value, base);
                preview.hidden = !src;
                if (!src) {
                    if (preview.getAttribute('src')) {
                        preview.pause();
                        preview.removeAttribute('src');
                        preview.load();
                    }
                    preview.dataset.src = '';
                    return;
                }
                if (preview.dataset.src === src) return;
                preview.dataset.src = src;
                preview.src = src;
            };

            preview.addEventListener('error', () => {
                if (preview.getAttribute('src')) showToast(kind === 'audio' ? 'Anteprima: l\'audio non si apre, controlla il nome del file.' : 'Anteprima: il video non si apre, controlla il nome del file.', true);
            });
            input.addEventListener('change', setPreview);
            input.addEventListener('input', debounce(setPreview, 450));

            upload.addEventListener('change', async () => {
                const file = upload.files?.[0];
                upload.value = '';
                if (!file) return;
                try {
                    if (kind === 'video') {
                        progress.hidden = false;
                        progress.firstElementChild.style.width = '0%';
                        showToast('Caricamento video...');
                        const url = await A.uploadVideo(file, '', (p) => { progress.firstElementChild.style.width = `${Math.round(p * 100)}%`; });
                        input.value = url.replace(/^\/vid\//, '');
                    } else {
                        showToast('Caricamento audio...');
                        const fd = new FormData();
                        fd.append('file', file);
                        fd.append('type', 'audio');
                        input.value = (await api('upload_media.php', { method: 'POST', body: fd })).filename;
                    }
                    setPreview();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    showToast(kind === 'video' ? 'Video caricato.' : 'Audio caricato.');
                } catch (error) {
                    showToast(error.message, true);
                } finally {
                    progress.hidden = true;
                }
            });

            setPreview();
        });

        // Chiusa la finestra, le anteprime non devono continuare a suonare.
        $('#adminModal')?.addEventListener('hidden.bs.modal', () => $$('[data-file-preview]', form).forEach((m) => m.pause()), { once: true });
    };

    const cardPreview = (form, kit) => {
        const box = $('[data-char-preview]', form);
        if (!box) return;
        const val = (name) => form.elements[name]?.value?.trim() || '';
        const r = rarityDef(val('rarita'));
        const img = mediaUrl(val('img_url'), '/img/');
        const name = val('nome') || 'Nome';
        const cat = val('categoria');
        const role = val('ruolo') || 'DPS';
        const catalog = val('catalogo') || 'visibile';
        const traits = traitsOf(val('caratteristiche'));
        const broken = 'onerror="this.parentNode.classList.add(\'is-broken\')"';

        box.innerHTML = `
            <article class="char-admin-card" style="--c:${e(r.color)}">
                <div class="char-admin-card__art">
                    ${img ? `<img class="char-admin-card__blur" src="${e(img)}" alt=""><img class="char-admin-card__img" src="${e(img)}" alt="" ${broken}>` : ''}
                    <span class="char-admin-card__empty"><i class="fa-solid fa-image"></i></span>
                    ${form.elements.limitato?.checked ? '<span class="char-admin-card__ribbon"><i class="fa-solid fa-hourglass-half"></i> Limitato</span>' : ''}
                </div>
                <div class="char-admin-card__body">
                    <strong>${e(name)}</strong>
                    <span class="char-admin-card__meta"><b style="color:${e(r.color)}">${e(r.label)}</b>${categoryChip(cat)}</span>
                    ${form.elements.ruolo ? `<small><i class="${ROLE_ICONS[role] || 'fa-solid fa-user'}"></i> ${e(role)}${kit ? ` · ${kit.unique ? 'kit unico' : 'kit del ruolo'}` : ''}</small>` : ''}
                </div>
            </article>
            ${traits.length ? `<ul class="char-admin-card__traits">${traits.map((t) => `<li>${e(t)}</li>`).join('')}</ul>` : ''}
            ${form.elements.catalogo ? `
            <div class="char-admin-ghost">
                <span class="shop-admin-help">Chi non ce l'ha lo vede così</span>
                ${catalog === 'nascosto'
                    ? '<p class="char-admin-ghost__none"><i class="fa-solid fa-eye-slash"></i> Non compare nel catalogo</p>'
                    : `<div class="char-admin-ghost__card">
                        <span class="char-admin-ghost__art">${catalog === 'segreto' ? '<b>?</b>' : (img ? `<img src="${e(img)}" alt="">` : '')}</span>
                        <span><strong>${catalog === 'segreto' ? '???' : e(name)}</strong><small style="color:${e(r.color)}">${e(r.label)}</small></span>
                    </div>`}
            </div>` : ''}`;
    };

    /* ── Kit dei duelli ─────────────────────────────────────────────── */

    const STAT_ROWS = [['hp', 'HP'], ['attack', 'Attacco'], ['defense', 'Difesa'], ['speed', 'Velocità'], ['max_energy', 'Energia max'], ['crit_rate', 'Crit Rate', '%'], ['crit_dmg', 'Danno critico', '%']];

    const skill = (cls, label, s, meta = '') => `
        <div class="char-admin-skill is-${cls}">
            <span class="char-admin-skill__tag">${label}</span>
            <strong>${e(s.name || '—')}</strong>
            ${meta ? `<small>${meta}</small>` : ''}
            <p>${e(s.desc || '')}</p>
        </div>`;

    const kitHtml = (kit, id) => {
        const where = '<code>includes/game_config.php</code>';
        const roleWarn = kit.kit_role && kit.kit_role !== kit.role;
        return `
            <div class="char-admin-kit__head${kit.unique ? ' is-unique' : ''}">
                <i class="fa-solid ${kit.unique ? 'fa-wand-magic-sparkles' : 'fa-shapes'}"></i>
                <div>
                    <strong>${kit.unique ? 'Kit unico' : 'Kit del ruolo'}</strong>
                    <small>${kit.unique
                        ? `Abilità scritte apposta per questo personaggio in ${where} (id ${Number(id)}). Si vedono così nell'inventario e in partita.`
                        : `Abilità generate da ruolo (${e(kit.role)}) e rarità: cambiano se cambi uno dei due. Per dargliene di sue vanno scritte in ${where}${id ? ` con l'id ${Number(id)}` : ', con l\'id che avrà dopo il salvataggio'}.`}</small>
                </div>
            </div>
            ${roleWarn ? `
            <p class="char-admin-kit__warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Il kit è scritto per <b>${e(kit.kit_role)}</b> ma il ruolo scelto è <b>${e(kit.role)}</b>: le abilità restano, ma statistiche e bonus del ruolo sono quelli di ${e(kit.role)}.</span>
                <button type="button" class="admin-btn admin-btn--small" data-use-role="${e(kit.kit_role)}">Usa ${e(kit.kit_role)}</button>
            </p>` : ''}
            <div class="char-admin-kit__grid">
                <table class="char-admin-stats">
                    <thead><tr><th scope="col"><span class="visually-hidden">Statistica</span></th><th scope="col">Lv. 1</th><th scope="col">MAX</th></tr></thead>
                    <tbody>${STAT_ROWS.map(([k, label, unit = '']) => `<tr><th scope="row">${label}</th><td>${num(kit.stats.base[k])}${unit}</td><td>${num(kit.stats.max[k])}${unit}</td></tr>`).join('')}</tbody>
                </table>
                <div class="char-admin-skills">
                    ${skill('passive', 'Passiva', kit.passive)}
                    ${skill('special', 'Speciale', kit.special, `${kit.special.cost} Energia · ricarica in ${kit.special.cooldown} ${kit.special.cooldown === 1 ? 'turno' : 'turni'}`)}
                    ${kit.ultimate
                        ? skill('ultimate', 'Ultimate', kit.ultimate, 'Energia piena, dal 6° turno, una volta a partita')
                        : '<div class="char-admin-skill is-none"><span class="char-admin-skill__tag">Ultimate</span><p>Nessuna: ce l\'hanno solo Segreti e The One.</p></div>'}
                </div>
            </div>
            <ul class="char-admin-kit__facts">
                <li><i class="fa-solid fa-angles-up"></i><span>Potenziamento: ${kit.upgrade.costs.map((c, i) => `Lv. ${i + 1}→${i + 2} <b>${c}</b>`).join(' · ')} ${kit.upgrade.total === 1 ? 'copia' : 'copie'} (${kit.upgrade.total} in tutto${kit.upgrade.limited ? ', prezzi da limitato' : ''}). Ogni livello +${kit.level_step}% alle statistiche.</span></li>
                ${kit.ultimate ? `<li><i class="fa-solid fa-volume-high"></i><span>Audio della cinematica: ${kit.ultimate_audio ? `<code>audio/ultimates/${Number(id)}.mp3</code>` : `quello di default${id ? `. Per dargliene uno suo: <code>audio/ultimates/${Number(id)}.mp3</code>` : ''}`}.</span></li>` : ''}
                ${kit.overrides ? '<li><i class="fa-solid fa-sliders"></i><span>Alcune statistiche arrivano dalla tabella <code>game_card_stats</code>.</span></li>' : ''}
            </ul>`;
    };

    const bindKit = (form, id, onKit) => {
        const box = $('[data-kit]', form);
        const val = (name) => form.elements[name]?.value?.trim() || '';
        let token = 0;
        let last = '';

        const load = async () => {
            const params = { action: 'kit', id, nome: val('nome'), rarita: val('rarita'), ruolo: val('ruolo') || 'DPS', limitato: form.elements.limitato?.checked ? 1 : 0 };
            const key = JSON.stringify(params);
            if (key === last) return;
            last = key;
            const mine = ++token;
            box.classList.add('is-loading');
            try {
                const { kit } = await get(params);
                if (mine !== token || !form.isConnected) return;
                box.innerHTML = kitHtml(kit, id);
                onKit(kit);
            } catch (error) {
                if (mine === token) box.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Kit non disponibile', error.message);
            } finally {
                if (mine === token) box.classList.remove('is-loading');
            }
        };

        const soon = debounce(load, 350);
        form.addEventListener('change', (event) => {
            if (['rarita', 'ruolo', 'limitato', 'nome'].includes(event.target.name)) load();
        });
        form.elements.nome?.addEventListener('input', soon);

        box.addEventListener('click', (event) => {
            const use = event.target.closest('[data-use-role]');
            if (!use || !form.elements.ruolo) return;
            form.elements.ruolo.value = use.dataset.useRole;
            form.elements.ruolo.dispatchEvent(new Event('change', { bubbles: true }));
            // Il pulsante sparisce col kit ridisegnato: il fuoco resta nella finestra (Esc compreso).
            box.focus({ preventScroll: true });
        });

        load();
    };

    const characterForm = (item) => {
        const schema = ctx.schema;
        const values = item
            ? { ...item, ruolo: item.ruolo || 'DPS', catalogo: item.catalogo || (TOP.includes(item.rarita) ? 'segreto' : 'visibile') }
            : { rarita: 'comune', ruolo: 'DPS', catalogo: 'visibile', in_pool_standard: 1, limitato: 0 };
        const names = ctx.categories.map((c) => c.nome);
        const orphan = values.categoria && !names.some((n) => n.toLowerCase() === values.categoria.toLowerCase());

        const html = `
            <div class="admin-field--full shop-admin-tabs char-admin-tabs" role="tablist">
                <button type="button" role="tab" class="shop-admin-tab is-active" data-ctab="scheda" aria-selected="true"><i class="fa-solid fa-id-card"></i> Scheda</button>
                <button type="button" role="tab" class="shop-admin-tab" data-ctab="duelli" aria-selected="false"><i class="fa-solid fa-khanda"></i> Duelli</button>
            </div>

            <div class="admin-field--full" data-cpanel="scheda" role="tabpanel">
                <div class="shop-admin-split char-admin-split">
                    <div class="admin-form-grid">
                        ${fields([
                            sectionTitle('Chi è'),
                            { name: 'nome', label: 'Nome', required: true, max: 80, placeholder: 'es. RIAS GREMORY' },
                            { name: 'rarita', label: 'Rarità', type: 'select', required: true, options: ctx.rarities.map((r) => [r.key, r.label]) },
                            schema.ruolo ? { name: 'ruolo', label: 'Ruolo nei duelli', type: 'select', options: ctx.roles.map((r) => [r, r]), help: 'Decide le statistiche e, senza un kit unico, anche le abilità.' } : '',
                            names.length
                                ? { name: 'categoria', label: 'Categoria', type: 'select', options: [['', '— nessuna —'], ...(orphan ? [[values.categoria, `${values.categoria} (fuori elenco)`]] : []), ...names.map((n) => [n, n])], help: 'Si gestiscono in Gacha › Categorie.' }
                                : { name: 'categoria', label: 'Categoria', max: 100, placeholder: 'anime, meme...' },
                            { name: 'img_url', label: 'Immagine', type: 'image', full: true, help: 'Va in <code>img/personaggi/</code>. Si vede intera, senza ritagli.' },
                            sectionTitle('Nel gacha'),
                            schema.catalogo ? { name: 'catalogo', label: 'Nel catalogo, a chi non ce l\'ha', type: 'select', full: true, options: [['visibile', 'Visibile: silhouette e nome'], ['segreto', '???: solo la rarità'], ['nascosto', 'Nascosto: non compare (non ancora uscito)']] } : '',
                            schema.pool ? { name: 'in_pool_standard', label: 'Esce nel banner standard', type: 'checkbox', help: 'E nel 50/50 perso dei banner evento. Spento: esce solo nei banner che lo scelgono.' } : '',
                            schema.limitato ? { name: 'limitato', label: 'Limitato', type: 'checkbox', checked: false, help: 'Badge «Limitato» nell\'inventario e potenziamento da limitato (meno copie).' } : '',
                        ], values)}
                    </div>
                    <div class="shop-admin-preview-wrap"><span class="shop-admin-help">Anteprima</span><div data-char-preview></div></div>
                </div>
                <div class="admin-form-grid char-admin-more">
                    ${sectionTitle('Testi')}
                    ${fields([
                        { name: 'descrizione', label: 'Descrizione (IT)', type: 'textarea', max: 500, rows: 3, full: !schema.en },
                        schema.en ? { name: 'descrizione_en', label: 'Descrizione (EN)', type: 'textarea', max: 500, rows: 3, placeholder: 'vuoto = usa l\'italiano' } : '',
                    ], values)}
                    ${traitsField('caratteristiche', 'Caratteristiche (IT)', values.caratteristiche, 'es. gioco preferito: WUWA')}
                    ${schema.en ? traitsField('caratteristiche_en', 'Caratteristiche (EN)', values.caratteristiche_en, 'e.g. favourite game: WUWA') : ''}
                    ${schema.audio || schema.video ? sectionTitle('Quando esce dalla cassa') : ''}
                    ${schema.audio ? fileField({ name: 'audio_url', label: 'Audio', kind: 'audio', value: values.audio_url, help: 'Suona quando esce dalla cassa e nella sua scheda dell\'inventario.' }) : ''}
                    ${schema.video ? fileField({ name: 'video_url', label: 'Video', kind: 'video', value: values.video_url, help: 'L\'animazione della pull: parte solo per Segreti e The One.' }) : ''}
                </div>
            </div>

            <div class="admin-field--full char-admin-kit" data-cpanel="duelli" role="tabpanel" hidden>
                <div data-kit tabindex="-1"><p class="admin-muted">Calcolo del kit...</p></div>
            </div>`;

        formModal({
            title: item ? item.nome : 'Nuovo personaggio',
            subtitle: item ? `#${item.id}${item.utenti ? ` · ${item.utenti === 1 ? '1 utente ce l\'ha' : `${num(item.utenti)} utenti ce l'hanno`}` : ''}` : 'Abilità e statistiche nei duelli nella scheda «Duelli»',
            html,
            endpoint: EP,
            action: 'save',
            extra: { id: item?.id || 0 },
            after: () => load(),
            onReady: (form) => {
                let kit = null;
                const refresh = () => cardPreview(form, kit);

                $$('[data-ctab]', form).forEach((tab) => tab.addEventListener('click', () => {
                    $$('[data-ctab]', form).forEach((t) => {
                        t.classList.toggle('is-active', t === tab);
                        t.setAttribute('aria-selected', String(t === tab));
                    });
                    $$('[data-cpanel]', form).forEach((panel) => { panel.hidden = panel.dataset.cpanel !== tab.dataset.ctab; });
                }));

                // Personaggio nuovo: catalogo e banner standard seguono rarità e
                // limitato finché non li tocchi tu.
                if (!item) {
                    const touched = new Set();
                    ['catalogo', 'in_pool_standard'].forEach((name) => form.elements[name]?.addEventListener('change', (event) => {
                        if (event.isTrusted) touched.add(name);
                    }));
                    form.elements.rarita.addEventListener('change', () => {
                        if (form.elements.catalogo && !touched.has('catalogo')) form.elements.catalogo.value = TOP.includes(form.elements.rarita.value) ? 'segreto' : 'visibile';
                    });
                    form.elements.limitato?.addEventListener('change', () => {
                        if (form.elements.in_pool_standard && !touched.has('in_pool_standard')) form.elements.in_pool_standard.checked = !form.elements.limitato.checked;
                    });
                }

                bindForm(form, refresh, 'personaggi');
                bindTraits(form, refresh);
                bindFiles(form, refresh);
                bindKit(form, item?.id || 0, (k) => { kit = k; refresh(); });
                refresh();
            },
        });
    };

    /* ════════════════════════════════════════════════════════════════ */

    let lastQuery = '';

    const load = async () => {
        const root = $('[data-characters-admin]');
        if (!root) return;
        if (!ctx) setLoading(root);
        // La ricerca in alto ricarica la sezione: con un testo nuovo si riparte dalla prima pagina.
        if (A.getQuery() !== lastQuery) {
            lastQuery = A.getQuery();
            st.page = 1;
        }
        try {
            ctx = await get({ action: 'list' });
        } catch (error) {
            root.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore personaggi', error.message);
            A.pagination('#charactersPagination', null, () => {});
            return;
        }
        renderList(root);
    };

    A.registerSection('characters', load);
})();
