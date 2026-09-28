/*
 * Pannello admin: le pagine Chi siamo ed Edits (gruppo "Pagine").
 *
 * Chi siamo: i membri (lista riordinabile, finestra con l'anteprima della
 * card), le candidature arrivate dal form del sito e i testi della pagina.
 * Edits: gli edit (file caricato o embed Streamable), le categorie dei
 * filtri e i testi della pagina.
 *
 * Usa gli strumenti dei form dello shop (A.forms, da admin-shop.js: va
 * caricato dopo) e le API /api/admin/chisiamo.php ed edits.php. I testi
 * delle testate passano da shop_content.php, come Download e Merch.
 */
(() => {
    'use strict';

    const A = window.CripsumAdmin;
    if (!A || !A.forms) return;

    const { api, confirmBox, showToast, thumb, setLoading, emptyState } = A;
    const { fields, sectionTitle, readForm, bindForm, formModal, moveButtons, grip, bindReorder, tabs, uploadImage } = A.forms;
    const e = A.escapeHtml;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const get = (endpoint, params = {}) => api(`${endpoint}?${new URLSearchParams(params)}`);
    const post = (endpoint, action, body = {}) => api(endpoint, { method: 'POST', body: { action, ...body } });
    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    const matches = (...texts) => {
        const q = String(A.getQuery() || '').toLowerCase().trim();
        return !q || texts.some((t) => String(t || '').toLowerCase().includes(q));
    };

    const toggleSwitch = (checked, attrs, label) => `
        <label class="shop-admin-switch" title="${e(label)}">
            <input type="checkbox" ${checked ? 'checked' : ''} ${attrs} aria-label="${e(label)}">
            <span></span>
        </label>`;

    /* Come shop_rich_text() in PHP: **grassetto**, [testo](link) e a capo. */
    const richText = (text) => e(String(text || '').trim())
        .replace(/\[([^\]\n]{1,120})\]\(([^)\s]{1,300})\)/g, (all, label, url) => (/^(https?:\/\/|\/)/.test(url) && !url.startsWith('//') ? `<a href="${url}" target="_blank" rel="noopener">${label}</a>` : all))
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');

    /* Testi della testata (shop_pagine), in un form sul posto. */
    const pageTextsForm = async (box, pagina, list) => {
        box.innerHTML = '<p class="admin-muted">Caricamento testi...</p>';
        let page = {};
        try {
            page = (await get('shop_content.php', { action: 'page', pagina })).page || {};
        } catch (error) {
            box.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
            return;
        }

        box.innerHTML = `
            <form class="admin-form-grid shop-admin-inline" data-page-form>
                ${fields(list, page)}
                <div class="admin-field--full shop-admin-inline__actions">
                    <a class="admin-btn" href="/it/${e(pagina)}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Apri la pagina</a>
                    <button type="submit" class="admin-btn admin-btn--primary"><i class="fa-solid fa-floppy-disk"></i> Salva testi</button>
                </div>
            </form>`;

        const form = $('[data-page-form]', box);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const res = await post('shop_content.php', 'save_page', { ...readForm(form), pagina });
                showToast(res.message || 'Testi salvati.');
            } catch (error) {
                showToast(error.message, true);
            }
        });
    };

    /* ════════════════════════════════════════════════════════════════
       CHI SIAMO
       ════════════════════════════════════════════════════════════════ */

    const CS = 'chisiamo.php';
    const cs = { tab: 'membri' };

    const memberPreview = (form, ctx) => {
        const box = $('[data-member-preview]', form);
        if (!box) return;
        const val = (name) => form.elements[name]?.value?.trim() || '';
        const name = val('nome') || 'Nome';
        const photo = A.assetUrl(val('foto'));
        const socials = ctx.socials.filter((s) => val(`social_${s.key}`));
        const hasProfile = Boolean(val('profilo'));

        box.innerHTML = `
            <article class="pages-admin-member">
                ${photo ? `<img src="${e(photo)}" alt="">` : `<span class="pages-admin-member__initial">${e(name.charAt(0).toUpperCase())}</span>`}
                <div>
                    <strong>${e(name)}</strong>
                    ${val('tag') ? `<span class="pages-admin-member__tag">${e(val('tag'))}</span>` : ''}
                    <p>${richText(val('descrizione')) || '<span class="admin-muted">La descrizione...</span>'}</p>
                    ${socials.length || hasProfile ? `<div class="pages-admin-member__links">
                        ${socials.map((s) => `<span title="${e(s.label)}"><i class="${e(s.icon)}"></i></span>`).join('')}
                        ${hasProfile ? '<em><i class="fa-solid fa-user"></i> Profilo</em>' : ''}
                    </div>` : ''}
                </div>
            </article>
            ${!photo && hasProfile ? '<small class="shop-admin-help">Senza foto usa quella del profilo Cripsum.</small>' : ''}`;
    };

    const memberForm = (item, ctx, reload, overrides = {}) => {
        const values = { ...(item || { visibile: 1 }), ...overrides };

        const html = `
            <div class="admin-field--full shop-admin-split">
                <div class="admin-form-grid">
                    ${fields([
                        sectionTitle('Chi è'),
                        { name: 'nome', label: 'Nome sulla card', required: true, max: 60, placeholder: 'es. cripsum' },
                        { name: 'profilo', label: 'Profilo Cripsum (username)', max: 21, placeholder: 'facoltativo', help: 'Nome cliccabile, bottone «Profilo» e gemma se è Premium.' },
                        { name: 'link', label: 'Oppure un link esterno sul nome', max: 255, full: true, placeholder: 'https://...', help: 'TikTok, sito, bio: se c\'è vince su quello del profilo.' },
                        { name: 'foto', label: 'Foto', type: 'image', full: true, help: 'Quadrata. Vuota = la foto del profilo Cripsum, se c\'è.' },
                        { name: 'tag', label: 'Tag (IT)', max: 60, placeholder: 'es. Fotografo', help: 'La scritta colorata sotto il nome. Vuoto = niente.' },
                        { name: 'tag_en', label: 'Tag (EN)', max: 60, placeholder: 'vuoto = usa l\'italiano' },
                        { name: 'descrizione', label: 'Descrizione (IT)', type: 'textarea', required: true, max: 1500, rows: 4, full: true, help: '<code>**grassetto**</code>, <code>[testo](https://link)</code> e l\'a capo, come nelle card. Niente HTML.' },
                        { name: 'descrizione_en', label: 'Descrizione (EN)', type: 'textarea', max: 1500, rows: 3, full: true, placeholder: 'vuoto = usa l\'italiano' },
                    ], values)}
                </div>
                <div class="shop-admin-preview-wrap"><span class="shop-admin-help">Anteprima della card</span><div data-member-preview></div></div>
            </div>
            ${fields([
                sectionTitle('Social', 'Quelli vuoti non compaiono.'),
                ...ctx.socials.map((s) => ({ name: `social_${s.key}`, label: s.label, max: 255, placeholder: 'https://...' })),
                sectionTitle('Pagina'),
                { name: 'visibile', label: 'Visibile nella pagina', type: 'checkbox' },
            ], values)}`;

        formModal({
            title: item ? `Membro ${item.nome}` : 'Nuovo membro',
            subtitle: item ? (Number(values.visibile) === 1 ? 'Visibile nella pagina' : 'Nascosto') : 'Va in fondo alla lista: trascinalo dove vuoi',
            html,
            endpoint: CS,
            action: 'save_member',
            extra: { id: item?.id || 0 },
            after: reload,
            onReady: (form) => bindForm(form, () => memberPreview(form, ctx), 'team'),
        });
    };

    const membersTable = (box, ctx, reload) => {
        const rows = ctx.members.filter((m) => matches(m.nome, m.tag, m.profilo, m.descrizione));
        const canSort = !A.getQuery();
        const last = rows.length - 1;

        box.innerHTML = `
            <div class="shop-admin-bar">
                <p class="admin-muted">Trascina le righe o usa le frecce: l'ordine è quello della pagina. Il collage in alto prende 5 foto a caso del team, diverse a ogni visita.</p>
                <button type="button" class="admin-btn admin-btn--primary" data-new-member><i class="fa-solid fa-plus"></i> Nuovo membro</button>
            </div>
            ${rows.length ? `
            <table class="admin-table">
                <thead><tr><th>Membro</th><th>Tag e social</th><th>Visibile</th><th>Azioni</th></tr></thead>
                <tbody>
                    ${rows.map((m, i) => {
                        const socials = ctx.socials.filter((s) => m[`social_${s.key}`]);
                        const link = m.link ? m.link.replace(/^https?:\/\/(www\.)?/, '') : '';
                        return `
                        <tr ${canSort ? `draggable="true" data-shop-row="${Number(m.id)}"` : ''} class="${Number(m.visibile) === 1 ? '' : 'is-off'}">
                            <td data-label="Membro"><div class="admin-name-cell">${grip(canSort)}${thumb(m.foto_url || (m.profilo_utente_id ? `/includes/get_pfp.php?id=${Number(m.profilo_utente_id)}` : ''), 'fa-solid fa-user')}<div>
                                <div class="admin-row-title">${e(m.nome)}${Number(m.profilo_premium) === 1 ? ' <img class="cr-premium-gem" src="/img/premium.svg" alt="Premium" title="Premium">' : ''}</div>
                                <div class="admin-row-sub">${m.profilo ? `profilo <a href="/u/${e(m.profilo)}" target="_blank" rel="noopener">@${e(m.profilo)}</a>` : (link ? e(link.slice(0, 40)) : 'nessun link')}</div>
                            </div></div></td>
                            <td data-label="Tag e social"><div class="pages-admin-tagcell">
                                ${m.tag ? `<span class="pages-admin-member__tag">${e(m.tag)}</span>` : ''}
                                ${socials.map((s) => `<i class="${e(s.icon)}" title="${e(s.label)}"></i>`).join('')}
                                ${!m.tag && !socials.length ? '<span class="admin-muted">—</span>' : ''}
                            </div></td>
                            <td data-label="Visibile">${toggleSwitch(Number(m.visibile) === 1, `data-visible-member="${Number(m.id)}"`, `Visibile: ${m.nome}`)}</td>
                            <td data-label="Azioni"><div class="admin-row-actions admin-row-actions--slides">
                                ${canSort ? moveButtons(Number(m.id), i, last, false) : ''}
                                <button class="admin-btn admin-btn--small" data-edit-member="${Number(m.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                                <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-member="${Number(m.id)}" title="Elimina" aria-label="Elimina ${e(m.nome)}"><i class="fa-solid fa-trash"></i></button>
                            </div></td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>` : emptyState('fa-solid fa-users', 'Nessun membro', ctx.members.length ? 'Nessun risultato con questa ricerca.' : 'Aggiungi il primo con «Nuovo membro».')}`;

        const find = (id) => ctx.members.find((m) => Number(m.id) === Number(id));

        $('[data-new-member]', box).addEventListener('click', () => memberForm(null, ctx, reload));
        $$('[data-edit-member]', box).forEach((b) => b.addEventListener('click', () => memberForm(find(b.dataset.editMember), ctx, reload)));

        $$('[data-visible-member]', box).forEach((input) => input.addEventListener('change', async () => {
            try {
                const res = await post(CS, 'set_visible', { id: input.dataset.visibleMember, visibile: input.checked ? 1 : 0 });
                showToast(res.message);
                reload();
            } catch (error) {
                input.checked = !input.checked;
                showToast(error.message, true);
            }
        }));

        $$('[data-delete-member]', box).forEach((b) => b.addEventListener('click', () => {
            const m = find(b.dataset.deleteMember);
            confirmBox('Eliminare il membro?', `<p class="admin-muted">«${e(m.nome)}» sparisce dalla pagina Chi siamo. La foto caricata si cancella, se nessun altro la usa. Se vuoi solo toglierlo per un po', spegni «Visibile».</p>`, async () => {
                await post(CS, 'delete_member', { id: m.id });
                showToast('Membro eliminato.');
                reload();
            });
        }));

        if (canSort && rows.length) bindReorder(box, rows.map((m) => Number(m.id)), CS, 'reorder', reload);
    };

    const STATI_CANDIDATURA = {
        nuova: ['admin-badge--info', 'Nuova'],
        approvata: ['admin-badge--success', 'Approvata'],
        rifiutata: ['admin-badge--danger', 'Rifiutata'],
    };

    const candidatureList = async (box, ctx, reload) => {
        setLoading(box);
        let list = [];
        try {
            list = (await get(CS, { action: 'candidature' })).candidature || [];
        } catch (error) {
            box.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
            return;
        }

        const rows = list.filter((c) => matches(c.nome, c.username, c.descrizione, c.social_nome));

        box.innerHTML = `
            <p class="admin-muted pages-admin-note">Arrivano dal form <a href="/it/candidatura-chisiamo" target="_blank" rel="noopener">/it/candidatura-chisiamo</a>. «Approva» crea il membro (nascosto) già compilato e ti apre la sua scheda; in entrambi i casi chi si è candidato riceve un messaggio nella posta del sito. Si cancellano da sole dopo 12 mesi.</p>
            ${rows.length ? `<div class="pages-admin-cands">${rows.map((c) => {
                const [badge, label] = STATI_CANDIDATURA[c.stato] || ['', c.stato];
                const pending = c.stato === 'nuova';
                const foto = Number(c.ha_foto) === 1 ? `/api/admin/chisiamo.php?action=candidatura_foto&id=${Number(c.id)}` : '';
                return `
                <article class="pages-admin-cand${pending ? '' : ' is-done'}">
                    ${foto ? `<a href="${e(foto)}" target="_blank" rel="noopener" class="pages-admin-cand__photo"><img src="${e(foto)}" alt="Foto di ${e(c.nome)}" loading="lazy"></a>` : '<span class="pages-admin-cand__photo pages-admin-cand__photo--none"><i class="fa-solid fa-user"></i></span>'}
                    <div class="pages-admin-cand__body">
                        <div class="pages-admin-cand__head">
                            <strong>${e(c.nome)}</strong>
                            <span class="admin-badge ${badge}">${e(label)}</span>
                        </div>
                        <small>${c.username ? `<button type="button" class="admin-link-btn" data-open-user="${Number(c.utente_id)}">@${e(c.username)}</button>` : 'account eliminato'} · ${e(A.formatDate(c.created_at))}${c.social_link ? ` · <a href="${e(c.social_link)}" target="_blank" rel="noopener noreferrer">${e(c.social_nome || c.social_link.replace(/^https?:\/\/(www\.)?/, '').slice(0, 40))}</a>` : (c.social_nome ? ` · ${e(c.social_nome)}` : '')}</small>
                        <p>${e(c.descrizione)}</p>
                        <div class="pages-admin-cand__actions">
                            ${pending ? `
                                <button type="button" class="admin-btn admin-btn--small admin-btn--primary" data-approve="${Number(c.id)}"><i class="fa-solid fa-check"></i> Approva</button>
                                <button type="button" class="admin-btn admin-btn--small" data-reject="${Number(c.id)}"><i class="fa-solid fa-xmark"></i> Rifiuta</button>` : ''}
                            ${c.stato === 'approvata' && c.membro_id ? `<button type="button" class="admin-btn admin-btn--small" data-open-member="${Number(c.membro_id)}"><i class="fa-solid fa-user-pen"></i> Apri il membro</button>` : ''}
                            <button type="button" class="admin-btn admin-btn--small admin-btn--danger" data-delete-cand="${Number(c.id)}" title="Elimina" aria-label="Elimina la candidatura di ${e(c.nome)}"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                </article>`;
            }).join('')}</div>` : emptyState('fa-solid fa-inbox', 'Nessuna candidatura', list.length ? 'Nessun risultato con questa ricerca.' : 'Quando qualcuno manda il form, la trovi qui.')}`;

        const find = (id) => list.find((c) => Number(c.id) === Number(id));

        $$('[data-open-user]', box).forEach((b) => b.addEventListener('click', () => A.openUser?.(Number(b.dataset.openUser))));

        const openMember = async (memberId, overrides = {}) => {
            const fresh = await get(CS, { action: 'list' });
            const member = fresh.members.find((m) => Number(m.id) === Number(memberId));
            if (member) memberForm(member, fresh, reload, overrides);
        };

        $$('[data-open-member]', box).forEach((b) => b.addEventListener('click', () => openMember(b.dataset.openMember)));

        $$('[data-approve]', box).forEach((b) => b.addEventListener('click', async () => {
            b.disabled = true;
            try {
                const res = await post(CS, 'approve_candidatura', { id: b.dataset.approve });
                showToast(res.message);
                // La scheda si apre gia' con "Visibile" acceso: salvando va in pagina.
                await openMember(res.member_id, { visibile: 1 });
                reload();
            } catch (error) {
                b.disabled = false;
                showToast(error.message, true);
            }
        }));

        $$('[data-reject]', box).forEach((b) => b.addEventListener('click', () => {
            const c = find(b.dataset.reject);
            confirmBox('Rifiutare la candidatura?', `
                <p class="admin-muted">${e(c.nome)} riceve un messaggio nella posta del sito. La foto si cancella subito.</p>
                <label class="admin-field"><span>Motivo (facoltativo, lo legge anche lui)</span><textarea class="admin-input" rows="3" maxlength="500" data-reject-reason></textarea></label>`, async () => {
                const reason = $('#confirmBody [data-reject-reason]')?.value || '';
                const res = await post(CS, 'reject_candidatura', { id: c.id, motivo: reason });
                showToast(res.message);
                reload();
            });
        }));

        $$('[data-delete-cand]', box).forEach((b) => b.addEventListener('click', () => {
            const c = find(b.dataset.deleteCand);
            confirmBox('Eliminare la candidatura?', `<p class="admin-muted">La candidatura di «${e(c.nome)}» sparisce senza avvisarlo. Per rispondergli usa «Rifiuta».</p>`, async () => {
                await post(CS, 'delete_candidatura', { id: c.id });
                showToast('Candidatura eliminata.');
                reload();
            });
        }));
    };

    const loadChisiamo = async () => {
        const root = $('[data-chisiamo-admin]');
        if (!root) return;

        setLoading(root);
        let ctx;
        try {
            ctx = await get(CS, { action: 'list' });
        } catch (error) {
            root.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
            return;
        }
        if (ctx.ready === false) {
            root.innerHTML = emptyState('fa-solid fa-database', 'Migrazione da applicare', ctx.message);
            return;
        }

        const body = tabs(root, [
            ['membri', 'Membri', 'fa-solid fa-users'],
            ['candidature', ctx.pending ? `Candidature (${ctx.pending})` : 'Candidature', 'fa-solid fa-inbox'],
            ['testata', 'Testi della pagina', 'fa-solid fa-pen-ruler'],
        ], cs.tab, (tab) => {
            cs.tab = tab;
            loadChisiamo();
        });

        if (cs.tab === 'candidature') {
            candidatureList(body, ctx, loadChisiamo);
        } else if (cs.tab === 'testata') {
            pageTextsForm(body, 'chisiamo', [
                sectionTitle('Testata', 'Titolo e testo in alto. L\'ultima parola del titolo prende il colore.'),
                { name: 'titolo', label: 'Titolo (IT)', max: 120, required: true },
                { name: 'titolo_en', label: 'Titolo (EN)', max: 120, placeholder: 'vuoto = usa l\'italiano' },
                { name: 'sottotitolo', label: 'Testo (IT)', type: 'textarea', max: 400, rows: 2 },
                { name: 'sottotitolo_en', label: 'Testo (EN)', type: 'textarea', max: 400, rows: 2 },
                sectionTitle('Riquadro «Unisciti al Team!»', 'In fondo alla pagina, porta al form della candidatura.'),
                { name: 'nota_titolo', label: 'Titolo (IT)', max: 120 },
                { name: 'nota_titolo_en', label: 'Titolo (EN)', max: 120 },
                { name: 'nota', label: 'Testo (IT)', type: 'textarea', max: 600, rows: 2 },
                { name: 'nota_en', label: 'Testo (EN)', type: 'textarea', max: 600, rows: 2 },
                { name: 'link_testo', label: 'Testo del bottone (IT)', max: 60 },
                { name: 'link_testo_en', label: 'Testo del bottone (EN)', max: 60 },
            ]);
        } else {
            membersTable(body, ctx, loadChisiamo);
        }
    };

    /* ════════════════════════════════════════════════════════════════
       EDITS
       ════════════════════════════════════════════════════════════════ */

    const ED = 'edits.php';
    const ed = { tab: 'edit', categoria: '', stato: '', dead: false, checking: false };

    const ICONS = [
        ['fa-solid fa-user', 'Persona'], ['fa-solid fa-gamepad', 'Giochi'], ['fa-solid fa-futbol', 'Calcio'], ['fa-solid fa-film', 'Film'],
        ['fa-solid fa-star', 'Stella'], ['fa-solid fa-tv', 'Serie'], ['fa-solid fa-music', 'Musica'], ['fa-solid fa-fire', 'Fuoco'],
        ['fa-solid fa-heart', 'Cuore'], ['fa-solid fa-dragon', 'Drago'], ['fa-solid fa-ghost', 'Fantasma'], ['fa-solid fa-crown', 'Corona'],
        ['fa-solid fa-bolt', 'Fulmine'], ['fa-solid fa-car', 'Auto'], ['fa-solid fa-basketball', 'Basket'], ['fa-solid fa-masks-theater', 'Teatro'],
        ['fa-solid fa-face-laugh-squint', 'Meme'], ['fa-solid fa-clapperboard', 'Ciak'], ['fa-brands fa-tiktok', 'TikTok'], ['fa-brands fa-youtube', 'YouTube'],
    ];

    const shapeOf = (w, h) => {
        const r = Number(w) > 0 && Number(h) > 0 ? Number(w) / Number(h) : 0.8;
        return r > 1.08 ? 'orizzontale' : (r < 0.93 ? 'verticale' : 'quadrato');
    };

    const fmtSize = (bytes) => (bytes > 1048576 ? `${(bytes / 1048576).toFixed(1).replace('.', ',')} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
    const fmtTime = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

    const sourceBadge = (x) => {
        if (x.video) return '<span class="pages-admin-src pages-admin-src--file"><i class="fa-solid fa-server"></i> File sul sito</span>';
        if (Number(x.video_morto) === 1) return '<span class="pages-admin-src pages-admin-src--dead"><i class="fa-solid fa-link-slash"></i> Link morto</span>';
        if (x.streamable_ok === null || x.streamable_ok === undefined) return '<span class="pages-admin-src"><i class="fa-solid fa-circle-question"></i> Da controllare</span>';
        return '<span class="pages-admin-src pages-admin-src--stream"><i class="fa-solid fa-link"></i> Streamable</span>';
    };

    /* Carica il video con la barra di avanzamento (in admin.js, lo usano anche le clip del team). */
    const uploadVideo = (file, onProgress) => A.uploadVideo(file, 'edits', onProgress);

    const editPreview = (form, ctx) => {
        const box = $('[data-edit-preview]', form);
        if (!box) return;
        const val = (name) => form.elements[name]?.value?.trim() || '';
        const cover = A.assetUrl(val('copertina') || val('gif_presence'));
        const cat = ctx.categories.find((c) => String(c.id) === val('categoria_id'));
        const shape = shapeOf(val('larghezza'), val('altezza'));
        const label = val('etichetta');

        box.innerHTML = `
            <article class="pages-admin-edit${shape === 'orizzontale' ? ' is-wide' : ''}">
                <div class="pages-admin-edit__cover">
                    ${cover ? `${shape === 'orizzontale' ? `<img class="pages-admin-edit__blur" src="${e(cover)}" alt="">` : ''}<img class="pages-admin-edit__img" src="${e(cover)}" alt="">` : '<span class="pages-admin-edit__empty"><i class="fa-solid fa-film"></i></span>'}
                    <span class="pages-admin-edit__tags">
                        ${cat ? `<span><i class="${e(cat.icona)}"></i> ${e(cat.nome)}</span>` : ''}
                        ${label ? `<em>${e(label)}</em>` : ''}
                    </span>
                </div>
                <div class="pages-admin-edit__info">
                    <strong>${e(val('titolo') || 'Titolo')}</strong>
                    ${val('serie') ? `<small class="is-serie">${e(val('serie'))}</small>` : ''}
                    ${val('musica') ? `<small><i class="fa-solid fa-music"></i> ${e(val('musica'))}</small>` : ''}
                    ${val('collab_nome') ? `<small class="is-collab"><i class="fa-solid fa-handshake"></i> collab con ${e(val('collab_nome'))}</small>` : ''}
                </div>
            </article>
            <small class="shop-admin-help">${val('larghezza') ? `Video ${e(shapeOf(val('larghezza'), val('altezza')))}, ${e(val('larghezza'))}×${e(val('altezza'))}.` : 'Le proporzioni arrivano dal video.'}</small>`;
    };

    /*
     * La parte "video" del form: file caricato (con la scelta del fotogramma
     * per la copertina) oppure link Streamable (proporzioni e copertina le
     * legge il server).
     */
    const videoFields = (values) => {
        const source = values.video ? 'file' : (values.streamable ? 'streamable' : 'file');
        return `
            <div class="admin-field admin-field--full">
                <span class="pages-admin-label">Da dove arriva il video</span>
                <div class="pages-admin-seg" role="radiogroup" aria-label="Da dove arriva il video">
                    <label><input type="radio" name="sorgente" value="file" ${source === 'file' ? 'checked' : ''}><span><i class="fa-solid fa-upload"></i> Carica il file</span></label>
                    <label><input type="radio" name="sorgente" value="streamable" ${source === 'streamable' ? 'checked' : ''}><span><i class="fa-solid fa-link"></i> Link Streamable</span></label>
                </div>
                <small class="shop-admin-help">Funzionano uguali. Col file il video resta sul sito: da Streamable 26 edit sono spariti.</small>
            </div>
            <input type="hidden" name="video" value="${e(values.video || '')}">
            <input type="hidden" name="larghezza" value="${e(values.larghezza || '')}">
            <input type="hidden" name="altezza" value="${e(values.altezza || '')}">
            <div class="admin-field admin-field--full" data-show-when="sorgente=file">
                <div class="pages-admin-vfile" data-vfile>
                    <i class="fa-solid fa-file-video"></i>
                    <div>
                        <strong data-vfile-name>${values.video ? e(String(values.video).split('/').pop()) : 'Nessun file'}</strong>
                        <small data-vfile-info>${values.video ? `Già sul sito${values.larghezza ? ` · ${e(values.larghezza)}×${e(values.altezza)}` : ''}` : 'MP4 o WebM. Il limite lo decide il server.'}</small>
                        <span class="pages-admin-progress" data-vfile-progress hidden><i></i></span>
                    </div>
                    <label class="admin-btn admin-btn--small"><i class="fa-solid fa-upload"></i> ${values.video ? 'Sostituisci' : 'Scegli il video'}<input type="file" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov,.m4v" data-vfile-input hidden></label>
                </div>
                <div class="pages-admin-frame" data-frame ${values.video ? '' : 'hidden'}>
                    <canvas data-frame-canvas width="160" height="200" aria-hidden="true"></canvas>
                    <div>
                        <small class="shop-admin-help" data-frame-time>Scegli il fotogramma per la copertina</small>
                        <input type="range" class="esports-admin-range" min="0" max="1" step="0.05" value="0" data-frame-range aria-label="Fotogramma della copertina">
                        <button type="button" class="admin-btn admin-btn--small" data-frame-use><i class="fa-solid fa-image"></i> Usa questo fotogramma</button>
                    </div>
                </div>
            </div>
            <div class="admin-field admin-field--full" data-show-when="sorgente=streamable">
                <label for="pages-streamable">Link Streamable</label>
                <input id="pages-streamable" type="text" name="streamable" value="${e(values.streamable ? `https://streamable.com/${values.streamable}` : '')}" placeholder="https://streamable.com/abc123" maxlength="120" data-streamable-input>
                <small class="shop-admin-help" data-streamable-status>Incolla il link: proporzioni e copertina arrivano da sole.</small>
            </div>`;
    };

    const bindVideo = (form, refresh) => {
        const radios = $$('input[name="sorgente"]', form);
        const videoInput = form.elements.video;
        const streamInput = form.elements.streamable;
        const width = form.elements.larghezza;
        const height = form.elements.altezza;
        const cover = form.elements.copertina;
        const stash = { video: videoInput.value, streamable: streamInput.value };

        const setCover = (url, onlyIfEmpty = false) => {
            if (!url || (onlyIfEmpty && cover.value.trim())) return;
            cover.value = url;
            cover.dispatchEvent(new Event('input', { bubbles: true }));
        };

        // Si salva solo la sorgente scelta: l'altra si mette da parte e torna se si cambia idea.
        radios.forEach((radio) => radio.addEventListener('change', () => {
            if (radio.value === 'file') {
                stash.streamable = streamInput.value;
                streamInput.value = '';
                videoInput.value = stash.video;
            } else {
                stash.video = videoInput.value;
                videoInput.value = '';
                streamInput.value = stash.streamable;
            }
            refresh();
        }));
        const active = radios.find((r) => r.checked)?.value;
        if (active === 'file') streamInput.value = '';
        else videoInput.value = '';

        /* ── Fotogramma per la copertina ── */
        const frame = $('[data-frame]', form);
        const canvas = $('[data-frame-canvas]', form);
        const range = $('[data-frame-range]', form);
        const timeLabel = $('[data-frame-time]', form);
        let probe = null;
        let autoCover = false;

        const draw = () => {
            if (!probe || !probe.videoWidth) return;
            canvas.width = probe.videoWidth;
            canvas.height = probe.videoHeight;
            canvas.getContext('2d').drawImage(probe, 0, 0, canvas.width, canvas.height);
            timeLabel.textContent = `Fotogramma a ${fmtTime(probe.currentTime)}`;
        };

        const useFrame = async (silent = false) => {
            if (!probe || !probe.videoWidth) return;
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.86));
            if (!blob) return;
            try {
                if (!silent) showToast('Caricamento copertina...');
                const url = await uploadImage(new File([blob], 'copertina.jpg', { type: 'image/jpeg' }), 'edits');
                setCover(url);
                if (!silent) showToast('Copertina aggiornata.');
            } catch (error) {
                showToast(error.message, true);
            }
        };

        const loadProbe = (src, { fresh = false } = {}) => {
            probe?.removeAttribute('src');
            probe = document.createElement('video');
            probe.muted = true;
            probe.preload = 'auto';
            probe.playsInline = true;
            probe.crossOrigin = 'anonymous';
            probe.src = src;
            autoCover = fresh && !cover.value.trim();
            probe.addEventListener('loadedmetadata', () => {
                width.value = probe.videoWidth || '';
                height.value = probe.videoHeight || '';
                range.max = String(Math.max(0.1, probe.duration || 0));
                range.value = String(Math.min(1, (probe.duration || 0) / 3));
                probe.currentTime = Number(range.value);
                frame.hidden = false;
                refresh();
            });
            probe.addEventListener('seeked', () => {
                draw();
                if (autoCover) {
                    autoCover = false;
                    useFrame(true);
                }
            });
        };

        range.addEventListener('input', () => {
            if (probe) probe.currentTime = Number(range.value);
        });
        $('[data-frame-use]', form).addEventListener('click', () => useFrame(false));
        if (videoInput.value) loadProbe(videoInput.value);

        /* ── File ── */
        const fileInput = $('[data-vfile-input]', form);
        const name = $('[data-vfile-name]', form);
        const info = $('[data-vfile-info]', form);
        const progress = $('[data-vfile-progress]', form);
        fileInput.addEventListener('change', async () => {
            const file = fileInput.files?.[0];
            fileInput.value = '';
            if (!file) return;
            name.textContent = file.name;
            info.textContent = `${fmtSize(file.size)} · caricamento...`;
            progress.hidden = false;
            progress.firstElementChild.style.width = '0%';
            // Le proporzioni e il fotogramma si leggono dal file locale, senza aspettare il caricamento.
            const local = URL.createObjectURL(file);
            loadProbe(local, { fresh: true });
            try {
                const url = await uploadVideo(file, (p) => { progress.firstElementChild.style.width = `${Math.round(p * 100)}%`; });
                videoInput.value = url;
                stash.video = url;
                info.textContent = `${fmtSize(file.size)} · caricato in vid/edits/${probe?.duration ? ` · ${fmtTime(probe.duration)}` : ''}${width.value ? ` · ${width.value}×${height.value} (${shapeOf(width.value, height.value)})` : ''}`;
                refresh();
            } catch (error) {
                info.textContent = error.message;
                showToast(error.message, true);
            } finally {
                progress.hidden = true;
            }
        });

        /* ── Streamable ── */
        const status = $('[data-streamable-status]', form);
        let last = '';
        const check = async () => {
            const value = streamInput.value.trim();
            if (!value || value === last) return;
            last = value;
            status.textContent = 'Chiedo a Streamable...';
            status.classList.remove('is-error', 'is-ok');
            try {
                const res = await get(ED, { action: 'streamable', code: value });
                if (res.width) {
                    width.value = res.width;
                    height.value = res.height;
                }
                setCover(res.cover, true);
                status.textContent = `Trovato: ${res.width ? `${res.width}×${res.height}, ${shapeOf(res.width, res.height)}` : 'proporzioni sconosciute'}${res.cover ? ', copertina salvata in img/edits/' : ''}.`;
                status.classList.add('is-ok');
                refresh();
            } catch (error) {
                status.textContent = error.message;
                status.classList.add('is-error');
            }
        };
        streamInput.addEventListener('change', check);
        streamInput.addEventListener('blur', check);
    };

    const editForm = (item, ctx, reload) => {
        const values = item ? { ...item } : { stato: 'pubblicato', categoria_id: ctx.categories[0]?.id || '' };

        const html = `
            <div class="admin-field--full shop-admin-split">
                <div class="admin-form-grid">
                    ${sectionTitle('Video')}
                    ${videoFields(values)}
                    ${fields([
                        sectionTitle('Testi'),
                        { name: 'titolo', label: 'Titolo (IT)', required: true, max: 120, placeholder: 'es. Iuno', help: 'Il personaggio o il soggetto: la riga grande.' },
                        { name: 'titolo_en', label: 'Titolo (EN)', max: 120, placeholder: 'vuoto = usa l\'italiano' },
                        { name: 'serie', label: 'Gioco, anime o serie', max: 120, placeholder: 'es. Wuthering Waves', help: 'La riga sotto il titolo. Vuota = niente.' },
                        { name: 'musica', label: 'Musica', max: 160, placeholder: 'es. TWICE - Strategy' },
                        { name: 'categoria_id', label: 'Categoria', type: 'select', required: true, options: ctx.categories.map((c) => [c.id, c.nome]) },
                        { name: 'descrizione', label: 'Descrizione (IT)', type: 'textarea', max: 2000, rows: 3, full: true, placeholder: 'facoltativa', help: 'Si legge nell\'edit aperto. <code>**grassetto**</code>, <code>[testo](https://link)</code> e l\'a capo.' },
                        { name: 'descrizione_en', label: 'Descrizione (EN)', type: 'textarea', max: 2000, rows: 3, full: true, placeholder: 'vuoto = usa l\'italiano' },
                    ], values)}
                </div>
                <div class="shop-admin-preview-wrap"><span class="shop-admin-help">Anteprima della card</span><div data-edit-preview></div></div>
            </div>
            ${fields([
                sectionTitle('Immagini'),
                { name: 'copertina', label: 'Copertina', type: 'image', full: true, help: 'Il fotogramma scelto sopra, quella di Streamable o una tua.' },
                { name: 'gif_presence', label: 'GIF per la rich presence (PreMiD)', type: 'image', full: true, help: 'Non si vede sulla pagina: è l\'immagine invisibile che PreMiD manda a Discord quando qualcuno apre l\'edit. Di solito una GIF di Tenor. Vuota = la copertina, o l\'anteprima del sito se manca anche quella.' },
                sectionTitle('Extra', 'Tutto facoltativo.'),
                { name: 'collab_nome', label: 'Collab con', max: 60, placeholder: 'es. Nauz' },
                { name: 'collab_link', label: 'Link della collab', max: 255, placeholder: 'https://...' },
                { name: 'link_post', label: 'Dove l\'hai pubblicato (TikTok o YouTube)', max: 255, full: true, placeholder: 'https://www.tiktok.com/@cripsum/video/...', help: 'Aggiunge il bottone «Guardalo su TikTok» o «Guardalo su YouTube» (vale anche Instagram).' },
                { name: 'etichetta', label: 'Etichetta (IT)', max: 30, placeholder: 'es. Collab', help: 'Vuota: «Nuovo» si mette da solo per 14 giorni.' },
                { name: 'etichetta_en', label: 'Etichetta (EN)', max: 30, placeholder: 'vuoto = usa l\'italiano' },
                sectionTitle('Pagina'),
                { name: 'stato', label: 'Stato', type: 'select', options: [['pubblicato', 'Pubblicato'], ['nascosto', 'Nascosto']] },
                { name: 'in_evidenza', label: 'In evidenza nella testata (toglie gli altri)', type: 'checkbox', checked: false },
            ], values)}`;

        formModal({
            title: item ? `Edit #${item.id}` : 'Nuovo edit',
            subtitle: item ? item.titolo : 'Va in cima alla lista: è il più recente',
            html,
            endpoint: ED,
            action: 'save_edit',
            extra: { id: item?.id || 0 },
            after: reload,
            onReady: (form) => {
                const refresh = () => editPreview(form, ctx);
                bindForm(form, refresh, 'edits');
                bindVideo(form, refresh);
                refresh();
            },
        });
    };

    const recheckAll = async (ctx, reload) => {
        if (ed.checking) return;
        const list = ctx.edits.filter((x) => x.streamable && !x.video);
        if (!list.length) {
            showToast('Nessun edit su Streamable da controllare.');
            return;
        }
        ed.checking = true;
        const tally = { ok: 0, dead: 0, error: 0 };
        try {
            for (let i = 0; i < list.length; i += 1) {
                showToast(`Controllo ${i + 1} di ${list.length}...`);
                try {
                    const res = await post(ED, 'recheck', { id: list[i].id });
                    tally[res.state] = (tally[res.state] || 0) + 1;
                } catch (_) {
                    tally.error += 1;
                }
                // Streamable non ama le raffiche.
                await sleep(700);
            }
            showToast(`Fatto: ${tally.ok} ok, ${tally.dead} spariti${tally.error ? `, ${tally.error} senza risposta (riprova)` : ''}.`, tally.error > 0);
        } finally {
            ed.checking = false;
            reload();
        }
    };

    const editsTable = (box, ctx, reload) => {
        let rows = ctx.edits.filter((x) => matches(x.titolo, x.serie, x.musica, x.collab_nome, x.categoria_nome, x.id));
        if (ed.categoria) rows = rows.filter((x) => String(x.categoria_id) === ed.categoria);
        if (ed.stato) rows = rows.filter((x) => x.stato === ed.stato);
        if (ed.dead) rows = rows.filter((x) => Number(x.video_morto) === 1);
        const canSort = !ed.categoria && !ed.stato && !ed.dead && !A.getQuery();
        const last = rows.length - 1;

        box.innerHTML = `
            ${ctx.dead ? `
            <div class="pages-admin-warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><b>${ctx.dead} edit hanno il link Streamable non più valido:</b> sulla pagina mostrano «Video non disponibile». Quando puoi, caricane il file.</span>
                <button type="button" class="admin-btn admin-btn--small" data-show-dead>${ed.dead ? 'Mostra tutti' : 'Mostra solo questi'}</button>
                ${ctx.dead_visible ? `<button type="button" class="admin-btn admin-btn--small" data-hide-dead>Nascondili per ora (${ctx.dead_visible})</button>` : ''}
            </div>` : ''}
            <div class="shop-admin-bar">
                <div class="pages-admin-filters">
                    <select class="admin-input" data-filter-cat aria-label="Filtra per categoria">
                        <option value="">Tutte le categorie</option>
                        ${ctx.categories.map((c) => `<option value="${Number(c.id)}" ${String(c.id) === ed.categoria ? 'selected' : ''}>${e(c.nome)}</option>`).join('')}
                    </select>
                    <select class="admin-input" data-filter-stato aria-label="Filtra per stato">
                        <option value="">Tutti gli stati</option>
                        <option value="pubblicato" ${ed.stato === 'pubblicato' ? 'selected' : ''}>Pubblicati</option>
                        <option value="nascosto" ${ed.stato === 'nascosto' ? 'selected' : ''}>Nascosti</option>
                    </select>
                </div>
                <div class="admin-toolbar-actions">
                    <button type="button" class="admin-btn" data-recheck title="Controlla uno per uno i video su Streamable: proporzioni, copertine e link morti"><i class="fa-solid fa-rotate"></i> Ricontrolla i link Streamable</button>
                    <button type="button" class="admin-btn admin-btn--primary" data-new-edit><i class="fa-solid fa-plus"></i> Nuovo edit</button>
                </div>
            </div>
            ${rows.length ? `
            <table class="admin-table">
                <thead><tr><th>Edit</th><th>Video</th><th>Visite</th><th>Stato</th><th>Azioni</th></tr></thead>
                <tbody>
                    ${rows.map((x, i) => `
                        <tr ${canSort ? `draggable="true" data-shop-row="${Number(x.id)}"` : ''} class="${x.stato === 'pubblicato' ? '' : 'is-off'}">
                            <td data-label="Edit"><div class="admin-name-cell">${grip(canSort)}${thumb(x.copertina_url || x.gif_url, 'fa-solid fa-film')}<div>
                                <div class="admin-row-title">#${Number(x.id)} ${e(x.titolo)}${x.serie ? ` <span class="pages-admin-serie">· ${e(x.serie)}</span>` : ''}${Number(x.in_evidenza) === 1 ? ' <i class="fa-solid fa-star pages-admin-star" title="In evidenza"></i>' : ''}</div>
                                <div class="admin-row-sub pages-admin-sub">${x.categoria_nome ? `<span class="pages-admin-cat"><i class="${e(x.categoria_icona || 'fa-solid fa-film')}"></i> ${e(x.categoria_nome)}</span>` : ''}<a href="/it/edits/${Number(x.id)}" target="_blank" rel="noopener">/it/edits/${Number(x.id)} <i class="fa-solid fa-arrow-up-right-from-square"></i></a>${x.musica ? `<span>${e(x.musica)}</span>` : ''}</div>
                            </div></div></td>
                            <td data-label="Video">${sourceBadge(x)}</td>
                            <td data-label="Visite">${Number(x.visualizzazioni || 0).toLocaleString('it-IT')}</td>
                            <td data-label="Stato">
                                <select class="admin-input shop-admin-state" data-state-edit="${Number(x.id)}" aria-label="Stato di ${e(x.titolo)}">
                                    <option value="pubblicato" ${x.stato === 'pubblicato' ? 'selected' : ''}>Pubblicato</option>
                                    <option value="nascosto" ${x.stato === 'nascosto' ? 'selected' : ''}>Nascosto</option>
                                </select>
                            </td>
                            <td data-label="Azioni"><div class="admin-row-actions admin-row-actions--slides">
                                ${canSort ? moveButtons(Number(x.id), i, last, false) : ''}
                                <button class="admin-btn admin-btn--small" data-edit-edit="${Number(x.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                                <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-edit="${Number(x.id)}" title="Elimina" aria-label="Elimina ${e(x.titolo)}"><i class="fa-solid fa-trash"></i></button>
                            </div></td>
                        </tr>`).join('')}
                </tbody>
            </table>` : emptyState('fa-solid fa-film', 'Nessun edit', ctx.edits.length ? 'Nessun risultato con questi filtri.' : 'Aggiungi il primo con «Nuovo edit».')}`;

        const find = (id) => ctx.edits.find((x) => Number(x.id) === Number(id));

        $('[data-filter-cat]', box).addEventListener('change', (event) => { ed.categoria = event.target.value; editsTable(box, ctx, reload); });
        $('[data-filter-stato]', box).addEventListener('change', (event) => { ed.stato = event.target.value; editsTable(box, ctx, reload); });
        $('[data-show-dead]', box)?.addEventListener('click', () => { ed.dead = !ed.dead; editsTable(box, ctx, reload); });
        $('[data-hide-dead]', box)?.addEventListener('click', () => {
            confirmBox('Nascondere gli edit senza video?', `<p class="admin-muted">I ${ctx.dead_visible} edit con il link Streamable morto spariscono dalla pagina (restano qui, «Nascosti»). Quando ne carichi il file li rimetti «Pubblicati».</p>`, async () => {
                const res = await post(ED, 'hide_dead');
                showToast(res.message);
                reload();
            });
        });
        $('[data-recheck]', box).addEventListener('click', () => recheckAll(ctx, reload));
        $('[data-new-edit]', box).addEventListener('click', () => editForm(null, ctx, reload));
        $$('[data-edit-edit]', box).forEach((b) => b.addEventListener('click', () => editForm(find(b.dataset.editEdit), ctx, reload)));

        $$('[data-state-edit]', box).forEach((select) => {
            const previous = select.value;
            select.addEventListener('change', async () => {
                try {
                    const res = await post(ED, 'set_state', { id: select.dataset.stateEdit, stato: select.value });
                    showToast(res.message);
                    reload();
                } catch (error) {
                    select.value = previous;
                    showToast(error.message, true);
                }
            });
        });

        $$('[data-delete-edit]', box).forEach((b) => b.addEventListener('click', () => {
            const x = find(b.dataset.deleteEdit);
            confirmBox('Eliminare l\'edit?', `<p class="admin-muted">«${e(x.titolo)}» sparisce dalla pagina e il suo link /it/edits/${Number(x.id)} smette di funzionare. Video e copertina caricati si cancellano, se nessun altro li usa. Se vuoi solo toglierlo per un po', mettilo «Nascosto».</p>`, async () => {
                await post(ED, 'delete_edit', { id: x.id });
                showToast('Edit eliminato.');
                reload();
            });
        }));

        if (canSort && rows.length) bindReorder(box, rows.map((x) => Number(x.id)), ED, 'reorder', reload);
    };

    const categoryForm = (item, reload) => {
        const values = item || { icona: 'fa-solid fa-film' };
        const current = values.icona || 'fa-solid fa-film';
        const icons = ICONS.some(([icon]) => icon === current) ? ICONS : [[current, 'Attuale'], ...ICONS];

        formModal({
            title: item ? `Categoria ${item.nome}` : 'Nuova categoria',
            subtitle: 'Diventa un filtro della pagina Edits',
            html: `
                ${fields([
                    { name: 'nome', label: 'Nome (IT)', required: true, max: 40, placeholder: 'es. Giochi' },
                    { name: 'nome_en', label: 'Nome (EN)', max: 40, placeholder: 'vuoto = usa l\'italiano' },
                    { name: 'slug', label: 'Codice', max: 40, placeholder: 'vuoto = dal nome', help: 'Serve ai filtri salvati di chi visita la pagina: meglio non cambiarlo.' },
                ], values)}
                <div class="admin-field admin-field--full">
                    <span class="pages-admin-label">Icona</span>
                    <div class="pages-admin-icons" role="radiogroup" aria-label="Icona">
                        ${icons.map(([icon, label]) => `<label title="${e(label)}"><input type="radio" name="icona" value="${e(icon)}" ${icon === current ? 'checked' : ''}><span><i class="${e(icon)}"></i></span></label>`).join('')}
                    </div>
                </div>`,
            endpoint: ED,
            action: 'save_category',
            extra: { id: item?.id || 0 },
            after: reload,
        });
    };

    const categoriesTable = (box, ctx, reload) => {
        const rows = ctx.categories;
        const last = rows.length - 1;

        box.innerHTML = `
            <div class="shop-admin-bar">
                <p class="admin-muted">Sono i filtri sopra la griglia, in quest'ordine. Nella pagina compaiono solo quelle con almeno un edit.</p>
                <button type="button" class="admin-btn admin-btn--primary" data-new-category><i class="fa-solid fa-plus"></i> Nuova categoria</button>
            </div>
            ${rows.length ? `
            <table class="admin-table">
                <thead><tr><th>Categoria</th><th>Nome EN</th><th>Edit</th><th>Azioni</th></tr></thead>
                <tbody>
                    ${rows.map((c, i) => `
                        <tr draggable="true" data-shop-row="${Number(c.id)}">
                            <td data-label="Categoria"><div class="admin-name-cell">${grip(true)}<span class="pages-admin-caticon"><i class="${e(c.icona)}"></i></span><div>
                                <div class="admin-row-title">${e(c.nome)}</div><div class="admin-row-sub">${e(c.slug)}</div>
                            </div></div></td>
                            <td data-label="Nome EN">${e(c.nome_en || '—')}</td>
                            <td data-label="Edit">${Number(c.edit_count)}</td>
                            <td data-label="Azioni"><div class="admin-row-actions admin-row-actions--slides">
                                ${moveButtons(Number(c.id), i, last, false)}
                                <button class="admin-btn admin-btn--small" data-edit-category="${Number(c.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                                <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-category="${Number(c.id)}" title="Elimina" aria-label="Elimina ${e(c.nome)}"><i class="fa-solid fa-trash"></i></button>
                            </div></td>
                        </tr>`).join('')}
                </tbody>
            </table>` : emptyState('fa-solid fa-tags', 'Nessuna categoria', 'Senza categorie non si possono aggiungere edit: creane una.')}`;

        const find = (id) => rows.find((c) => Number(c.id) === Number(id));
        $('[data-new-category]', box).addEventListener('click', () => categoryForm(null, reload));
        $$('[data-edit-category]', box).forEach((b) => b.addEventListener('click', () => categoryForm(find(b.dataset.editCategory), reload)));
        $$('[data-delete-category]', box).forEach((b) => b.addEventListener('click', () => {
            const c = find(b.dataset.deleteCategory);
            if (Number(c.edit_count) > 0) {
                showToast(`«${c.nome}» ha ancora ${c.edit_count} edit: spostali in un'altra categoria prima di eliminarla.`, true);
                return;
            }
            confirmBox('Eliminare la categoria?', `<p class="admin-muted">Il filtro «${e(c.nome)}» sparisce dalla pagina.</p>`, async () => {
                await post(ED, 'delete_category', { id: c.id });
                showToast('Categoria eliminata.');
                reload();
            });
        }));

        if (rows.length) bindReorder(box, rows.map((c) => Number(c.id)), ED, 'reorder_categories', reload);
    };

    const loadEdits = async () => {
        const root = $('[data-edits-admin]');
        if (!root) return;

        const body = tabs(root, [
            ['edit', 'Edit', 'fa-solid fa-film'],
            ['categorie', 'Categorie', 'fa-solid fa-tags'],
            ['testata', 'Testi della pagina', 'fa-solid fa-pen-ruler'],
        ], ed.tab, (tab) => {
            ed.tab = tab;
            loadEdits();
        });

        if (ed.tab === 'testata') {
            pageTextsForm(body, 'edits', [
                sectionTitle('Testata', 'L\'ultima parola del titolo prende il colore.'),
                { name: 'titolo', label: 'Titolo (IT)', max: 120, required: true },
                { name: 'titolo_en', label: 'Titolo (EN)', max: 120, placeholder: 'vuoto = usa l\'italiano' },
                { name: 'sottotitolo', label: 'Testo (IT)', type: 'textarea', max: 400, rows: 2 },
                { name: 'sottotitolo_en', label: 'Testo (EN)', type: 'textarea', max: 400, rows: 2 },
                sectionTitle('Secondo bottone', 'Accanto a «Guarda l\'ultimo». Vuoto = nascosto.'),
                { name: 'link_testo', label: 'Testo (IT)', max: 60, placeholder: 'es. TikTok' },
                { name: 'link_testo_en', label: 'Testo (EN)', max: 60 },
                { name: 'link_url', label: 'Link', max: 255, full: true, placeholder: 'https://www.tiktok.com/@cripsum' },
            ]);
            return;
        }

        setLoading(body);
        let ctx;
        try {
            ctx = await get(ED, { action: 'list' });
        } catch (error) {
            body.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
            return;
        }
        if (ctx.ready === false) {
            body.innerHTML = emptyState('fa-solid fa-database', 'Migrazione da applicare', ctx.message);
            return;
        }

        if (ed.tab === 'categorie') categoriesTable(body, ctx, loadEdits);
        else editsTable(body, ctx, loadEdits);
    };

    A.registerSection('chisiamo', loadChisiamo);
    A.registerSection('edits', loadEdits);
})();
