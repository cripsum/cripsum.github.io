/*
 * Pannello admin: Novita' (gruppo "Sito").
 *
 * Le notizie della finestra «News & Changelog» della homepage: si aggiungono,
 * si modificano, si nascondono e si eliminano da qui. Stanno nel database
 * (tabella cripsum_news) e tutto passa da /api/admin/news.php.
 *
 * Il testo si puo' scrivere senza tag: una riga vuota separa i paragrafi e le
 * righe che cominciano con «- » fanno un elenco. E' il server a trasformarlo
 * e a pulirlo, e l'anteprima del modulo mostra proprio quello che il server
 * salverebbe.
 */
(() => {
    'use strict';

    const A = window.CripsumAdmin;
    if (!A) return;

    const { api, openModal, closeModal, confirmBox, showToast, thumb, setLoading, emptyState } = A;
    const e = A.escapeHtml;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const root = $('[data-news-admin]');
    if (!root) return;

    // I tipi che la finestra conosce gia'; se ne puo' scrivere uno qualsiasi.
    const TAGS = ['update', 'feature', 'fix', 'hotfix', 'event', 'lootbox'];
    // Dove torna il fuoco quando il server rifiuta un campo: l'inizio del messaggio dice quale.
    const FIELD_BY_ERROR = [
        [/^titolo in inglese/i, 'titolo_en'], [/^titolo/i, 'titolo'], [/^versione/i, 'versione'],
        [/^tipo in inglese/i, 'tag_en'], [/^tipo/i, 'tag'], [/^testo in inglese/i, 'contenuto_en'],
        [/^testo/i, 'contenuto'], [/^immagine/i, 'immagine'], [/^data/i, 'data'],
    ];

    let view = null;   // l'ultima risposta del server

    const post = (action, body = {}) => api('news.php', { method: 'POST', body: { action, ...body } });
    const find = (id) => (view?.news || []).find((n) => Number(n.id) === Number(id));
    const today = () => {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    };
    /** La data come la scrive la finestra: 10 ott 2026. */
    const dateLabel = (value, lang = 'it') => {
        const date = new Date(`${value}T00:00:00`);
        if (!value || Number.isNaN(date.getTime())) return '';
        return date.toLocaleDateString(lang === 'en' ? 'en-GB' : 'it-IT', { day: '2-digit', month: 'short', year: 'numeric' });
    };

    /* ── Elenco ──────────────────────────────────────────────────────── */

    const render = () => {
        if (!view.ready) {
            root.innerHTML = emptyState('fa-solid fa-database', 'Tabella mancante', 'Applica la migrazione 2026_10_10_cripsum_news.sql, poi ricarica.');
            return;
        }
        if (!view.news.length) {
            root.innerHTML = emptyState('fa-solid fa-newspaper', 'Nessuna novità', 'Scrivi la prima con «Nuova».');
            return;
        }

        const visible = view.news.filter((n) => n.visibile).length;

        root.innerHTML = `
            <table class="admin-table">
                <thead><tr><th>Notizia</th><th>Stato</th><th>Azioni</th></tr></thead>
                <tbody>
                    ${view.news.map((n) => `
                        <tr>
                            <td data-label="Notizia">
                                <div class="admin-name-cell">
                                    ${thumb(n.immagine, 'fa-solid fa-newspaper')}
                                    <div>
                                        <div class="admin-row-title">${e(n.titolo)}</div>
                                        <div class="admin-row-sub">${[n.versione ? `v ${e(n.versione)}` : '', e(n.tag), e(dateLabel(n.data))].filter(Boolean).join(' · ')}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Stato">
                                ${n.visibile ? '<span class="admin-badge admin-badge--success">Visibile</span>' : '<span class="admin-badge">Nascosta</span>'}
                                ${n.pinned ? '<span class="admin-badge admin-badge--warning">In evidenza</span>' : ''}
                                ${n.contenuto_en ? '' : '<span class="admin-badge" title="Chi legge in inglese vede il testo in italiano">Solo italiano</span>'}
                            </td>
                            <td data-label="Azioni">
                                <div class="admin-row-actions">
                                    <button type="button" class="admin-btn admin-btn--small" data-news-visible="${Number(n.id)}" data-to="${n.visibile ? 0 : 1}"><i class="fa-solid ${n.visibile ? 'fa-eye-slash' : 'fa-eye'}"></i> ${n.visibile ? 'Nascondi' : 'Mostra'}</button>
                                    <button type="button" class="admin-btn admin-btn--small" data-news-edit="${Number(n.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                                    <button type="button" class="admin-btn admin-btn--small admin-btn--danger" data-news-delete="${Number(n.id)}"><i class="fa-solid fa-trash"></i> Elimina</button>
                                </div>
                            </td>
                        </tr>`).join('')}
                </tbody>
            </table>
            ${visible > view.public_limit ? `<p class="admin-news__note"><i class="fa-solid fa-circle-info"></i> La finestra del sito mostra le prime ${Number(view.public_limit)} notizie visibili: le più vecchie restano qui, ma sul sito non si vedono.</p>` : ''}`;
    };

    const load = async () => {
        setLoading(root);
        try {
            view = await api('news.php');
            render();
        } catch (error) {
            root.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Novità non caricate', error.message);
        }
    };

    /* ── Modulo ──────────────────────────────────────────────────────── */

    const tools = (name) => `
        <div class="admin-news__tools" data-news-tools="${name}">
            <button type="button" data-news-tool="strong" title="Grassetto" aria-label="Grassetto"><i class="fa-solid fa-bold"></i></button>
            <button type="button" data-news-tool="em" title="Corsivo" aria-label="Corsivo"><i class="fa-solid fa-italic"></i></button>
            <button type="button" data-news-tool="list" title="Elenco" aria-label="Elenco"><i class="fa-solid fa-list-ul"></i></button>
            <button type="button" data-news-tool="link" title="Link" aria-label="Link"><i class="fa-solid fa-link"></i></button>
        </div>`;

    const formHtml = (n) => `
        <form id="newsForm" class="admin-form-grid admin-news__form" novalidate>
            ${n.id ? `<input type="hidden" name="id" value="${Number(n.id)}">` : ''}
            <div class="admin-field">
                <label for="newsTitolo">Titolo</label>
                <input id="newsTitolo" name="titolo" value="${e(n.titolo || '')}" maxlength="200" required>
            </div>
            <div class="admin-field">
                <label for="newsTitoloEn">Titolo in inglese <em>facoltativo</em></label>
                <input id="newsTitoloEn" name="titolo_en" value="${e(n.titolo_en || '')}" maxlength="200" placeholder="Vuoto = quello in italiano">
            </div>
            <div class="admin-field">
                <label for="newsVersione">Versione <em>facoltativa</em></label>
                <input id="newsVersione" name="versione" value="${e(n.versione || '')}" maxlength="20" placeholder="7.0">
            </div>
            <div class="admin-field">
                <label for="newsData">Data</label>
                <input type="date" id="newsData" name="data" value="${e(n.data || today())}" required>
            </div>
            <div class="admin-field">
                <label for="newsTag">Tipo <em>facoltativo</em></label>
                <input id="newsTag" name="tag" value="${e(n.tag || '')}" maxlength="50" list="newsTagList" placeholder="update, feature, fix, event…" autocomplete="off">
                <datalist id="newsTagList">${TAGS.map((tag) => `<option value="${tag}">`).join('')}</datalist>
            </div>
            <div class="admin-field">
                <label for="newsTagEn">Tipo in inglese <em>facoltativo</em></label>
                <input id="newsTagEn" name="tag_en" value="${e(n.tag_en || '')}" maxlength="50" placeholder="Vuoto = quello in italiano" autocomplete="off">
            </div>
            <div class="admin-field admin-field--full">
                <label for="newsImmagine">Immagine <em>facoltativa</em></label>
                <div class="shop-admin-media">
                    <span class="shop-admin-media__preview" data-news-thumb>${thumb(n.immagine)}</span>
                    <div class="admin-input-group">
                        <input id="newsImmagine" name="immagine" value="${e(n.immagine || '')}" maxlength="300" placeholder="/img/... oppure https://...">
                        <label class="admin-btn shop-admin-upload" title="Carica un'immagine"><i class="fa-solid fa-upload"></i><input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-news-upload hidden></label>
                    </div>
                </div>
                <small class="shop-admin-help">Sta sopra il testo, larga quanto la finestra e alta al massimo 200 px: meglio una foto orizzontale.</small>
            </div>
            <div class="admin-field">
                <label for="newsContenuto">Testo</label>
                ${tools('contenuto')}
                <textarea id="newsContenuto" name="contenuto" required>${e(n.contenuto || '')}</textarea>
                <small class="shop-admin-help">Scrivi normalmente: una riga vuota comincia un nuovo paragrafo, le righe che iniziano con «- » fanno un elenco.</small>
            </div>
            <div class="admin-field">
                <label for="newsContenutoEn">Testo in inglese <em>facoltativo</em></label>
                ${tools('contenuto_en')}
                <textarea id="newsContenutoEn" name="contenuto_en" placeholder="Vuoto = chi legge in inglese vede quello in italiano">${e(n.contenuto_en || '')}</textarea>
            </div>
            <div class="admin-field shop-admin-check">
                <label><input type="checkbox" name="visibile" value="1" ${n.id && !n.visibile ? '' : 'checked'}> <span>Visibile sul sito</span></label>
                <small class="shop-admin-help">Senza spunta resta qui come bozza. Appena è visibile, la finestra si apre da sola a chi non l'ha ancora letta.</small>
            </div>
            <div class="admin-field shop-admin-check">
                <label><input type="checkbox" name="pinned" value="1" ${n.pinned ? 'checked' : ''}> <span>In evidenza</span></label>
                <small class="shop-admin-help">Resta in cima alla lista anche quando ne escono di più recenti.</small>
            </div>
            <div class="admin-field admin-field--full" data-dirty-ignore>
                <label>Anteprima</label>
                <div class="admin-news__preview">
                    <div class="admin-news__langs">
                        <button type="button" class="is-active" data-news-lang="it">Italiano</button>
                        <button type="button" data-news-lang="en">English</button>
                    </div>
                    <div class="admin-news__sheet" data-news-sheet aria-live="polite"></div>
                </div>
            </div>
        </form>`;

    const payload = (form) => ({
        id: Number(form.elements.id?.value || 0),
        titolo: form.elements.titolo.value,
        titolo_en: form.elements.titolo_en.value,
        versione: form.elements.versione.value,
        data: form.elements.data.value,
        tag: form.elements.tag.value,
        tag_en: form.elements.tag_en.value,
        immagine: form.elements.immagine.value,
        contenuto: form.elements.contenuto.value,
        contenuto_en: form.elements.contenuto_en.value,
        visibile: form.elements.visibile.checked ? 1 : 0,
        pinned: form.elements.pinned.checked ? 1 : 0,
    });

    /** Scrive attorno al testo selezionato, o al posto suo, e lascia la selezione su quello che ha scritto. */
    const replaceSelection = (area, build) => {
        const { selectionStart: start, selectionEnd: end, value } = area;
        const text = build(value.slice(start, end));
        if (text === null) return;
        area.focus();
        area.setRangeText(text, start, end, 'select');
        area.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const applyTool = (area, tool) => {
        if (tool === 'strong' || tool === 'em') {
            replaceSelection(area, (selected) => `<${tool}>${selected || 'testo'}</${tool}>`);
            return;
        }

        if (tool === 'list') {
            // Se nel testo ci sono gia' paragrafi o elenchi scritti coi tag, l'elenco si scrive allo stesso modo.
            const tagged = /<\/?(?:p|ul|ol|li|br)\b/i.test(area.value);
            replaceSelection(area, (selected) => {
                const lines = (selected || 'voce').split('\n').map((line) => line.replace(/^\s*[-*•]\s+/, '').trim()).filter(Boolean);
                return tagged
                    ? `<ul>\n${lines.map((line) => `<li>${line}</li>`).join('\n')}\n</ul>`
                    : lines.map((line) => `- ${line}`).join('\n');
            });
            return;
        }

        if (tool === 'link') {
            const url = (window.prompt('Indirizzo del link: una pagina del sito (/it/lootbox) o un indirizzo https completo.', 'https://') || '').trim();
            if (!url || url === 'https://') return;
            if (!/^(https?:\/\/|\/(?!\/)|mailto:)/i.test(url)) {
                showToast('Usa una pagina del sito (/it/...) o un indirizzo https completo.', true);
                return;
            }
            replaceSelection(area, (selected) => `<a href="${url.replace(/"/g, '%22')}">${selected || 'testo del link'}</a>`);
        }
    };

    const openForm = (item = null) => {
        const n = item || {};
        openModal(
            item ? 'Modifica novità' : 'Nuova novità',
            item ? `ID ${item.id}` : 'Compare nella finestra «News & Changelog» della homepage',
            formHtml(n),
            '<button class="admin-btn" data-admin-close="1">Annulla</button><button class="admin-btn admin-btn--primary" id="saveNewsBtn"><i class="fa-solid fa-floppy-disk"></i> Salva</button>'
        );

        const form = $('#newsForm');
        if (!form) return;

        const sheet = $('[data-news-sheet]', form);
        let lang = 'it';
        let cleaned = { contenuto: '', contenuto_en: '' };
        let timer = null;
        let seq = 0;

        // Titolo, etichette e immagine si aggiornano subito; il testo e' quello che ha pulito il server.
        const paint = () => {
            const data = payload(form);
            const english = lang === 'en';
            const title = (english && data.titolo_en.trim()) || data.titolo.trim();
            const tag = (english && data.tag_en.trim()) || data.tag.trim();
            const body = (english && cleaned.contenuto_en) || cleaned.contenuto;
            const image = A.assetUrl(data.immagine);

            sheet.innerHTML = `
                <div class="admin-news__meta">
                    ${data.versione.trim() ? `<span>${e(data.versione.trim())}</span>` : ''}
                    ${tag ? `<span class="admin-news__tag">${e(tag)}</span>` : ''}
                    <span class="admin-news__date">${e(dateLabel(data.data, lang))}</span>
                </div>
                <h3 class="admin-news__title">${e(title || 'Titolo')}</h3>
                ${image ? `<img class="admin-news__image" src="${e(image)}" alt="" onerror="this.remove()">` : ''}
                <div class="admin-news__body">${body || '<p class="admin-news__empty">Il testo compare qui mentre scrivi.</p>'}</div>`;
        };

        const refresh = async () => {
            const mine = ++seq;
            try {
                const data = await post('preview', { contenuto: form.elements.contenuto.value, contenuto_en: form.elements.contenuto_en.value });
                if (mine !== seq || !form.isConnected) return;
                cleaned = { contenuto: data.contenuto || '', contenuto_en: data.contenuto_en || '' };
                paint();
            } catch (error) {
                // L'anteprima non e' il salvataggio: se manca, il modulo funziona lo stesso.
            }
        };

        form.addEventListener('input', (event) => {
            if (event.target.matches('textarea')) {
                clearTimeout(timer);
                timer = setTimeout(refresh, 350);
                return;
            }
            if (event.target.name === 'immagine') {
                $('[data-news-thumb]', form).innerHTML = thumb(event.target.value);
            }
            paint();
        });
        form.addEventListener('change', paint);

        form.addEventListener('click', (event) => {
            const tool = event.target.closest('[data-news-tool]');
            if (tool) {
                applyTool(form.elements[tool.closest('[data-news-tools]').dataset.newsTools], tool.dataset.newsTool);
                return;
            }

            const tab = event.target.closest('[data-news-lang]');
            if (tab) {
                lang = tab.dataset.newsLang;
                $$('[data-news-lang]', form).forEach((button) => button.classList.toggle('is-active', button === tab));
                paint();
            }
        });

        $('[data-news-upload]', form).addEventListener('change', async (event) => {
            const input = event.target;
            const file = input.files?.[0];
            if (!file) return;
            try {
                showToast('Caricamento immagine...');
                const fd = new FormData();
                fd.append('file', file);
                fd.append('type', 'image');
                fd.append('folder', 'news');
                const res = await api('upload_media.php', { method: 'POST', body: fd });
                A.trackUpload?.(res.url);
                form.elements.immagine.value = res.url;
                form.elements.immagine.dispatchEvent(new Event('input', { bubbles: true }));
                showToast('Immagine caricata.');
            } catch (error) {
                showToast(error.message, true);
            } finally {
                input.value = '';
            }
        });

        // Invio in un campo corto non deve mandare il modulo per conto suo.
        form.addEventListener('submit', (event) => event.preventDefault());

        $('#saveNewsBtn')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            button.disabled = true;
            try {
                const data = await post('save', payload(form));
                view = { ...view, news: data.news };
                closeModal();
                showToast(data.created ? 'Novità pubblicata.' : 'Novità salvata.');
                render();
            } catch (error) {
                showToast(error.message, true);
                const name = (FIELD_BY_ERROR.find(([pattern]) => pattern.test(error.message)) || [])[1];
                if (name) form.elements[name]?.focus();
                button.disabled = false;
            }
        });

        paint();
        refresh();
    };

    /* ── Azioni dell'elenco ──────────────────────────────────────────── */

    root.addEventListener('click', async (event) => {
        const edit = event.target.closest('[data-news-edit]');
        if (edit) {
            const item = find(edit.dataset.newsEdit);
            if (item) openForm(item);
            return;
        }

        const toggle = event.target.closest('[data-news-visible]');
        if (toggle) {
            const show = toggle.dataset.to === '1';
            try {
                const data = await post('visible', { id: Number(toggle.dataset.newsVisible), visibile: show ? 1 : 0 });
                view = { ...view, news: data.news };
                render();
                showToast(show ? 'Novità visibile sul sito.' : 'Novità nascosta.');
            } catch (error) {
                showToast(error.message, true);
            }
            return;
        }

        const remove = event.target.closest('[data-news-delete]');
        if (remove) {
            const item = find(remove.dataset.newsDelete);
            if (!item) return;
            confirmBox(
                'Eliminare la novità?',
                `<p class="admin-muted">«${e(item.titolo)}» sparisce dalla finestra del sito e non si recupera. Se vuoi solo toglierla per un po', usa «Nascondi».</p>`,
                async () => {
                    const data = await post('delete', { id: Number(item.id) });
                    view = { ...view, news: data.news };
                    render();
                    showToast('Novità eliminata.');
                }
            );
        }
    });

    $('#createNewsBtn')?.addEventListener('click', () => {
        if (view && !view.ready) {
            showToast('Manca la tabella delle novità: applica la migrazione.', true);
            return;
        }
        openForm();
    });

    A.registerSection('news', load);
})();
