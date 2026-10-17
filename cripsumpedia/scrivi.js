/* Cripsumpedia — Scrivi (fase 4).

   La bozza si salva da sola nel database mentre si scrive (si riprende anche
   da un altro dispositivo), la foto si carica appena scelta, scrivendo [[ si
   apre l'elenco delle voci da collegare, il titolo dice subito se la voce c'è
   già. «Invia allo staff» manda la proposta; lo staff vede «Pubblica», e in
   «Sistema e poi approva» il pulsante approva la proposta con i cambiamenti.
   Usa post(), toast() e la ricerca di cripsumpedia.js. */
(() => {
    'use strict';

    const C = window.CpWrite;
    const P = window.Cripsumpedia || {};
    if (!C || !P.post) return;

    const S = C.strings;
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const esc = P.escapeHtml;
    const fmt = (text, ...args) => {
        let i = 0;
        return String(text).replace(/%[sd]/g, () => String(args[i++] ?? ''));
    };
    const debounce = (fn, wait) => {
        let timer = 0;
        const run = (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), wait);
        };
        run.cancel = () => clearTimeout(timer);
        return run;
    };
    const slugify = (value) => String(value).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

    const form = $('[data-cp-write]');

    // Ritira una proposta in attesa (anche fuori dal modulo, in «Le tue bozze»).
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-cp-withdraw]');
        if (!button) return;
        button.disabled = true;
        try {
            const data = await P.post(P.saveEndpoint, { action: 'withdraw', revision_id: Number(button.dataset.cpWithdraw) });
            location.href = data.url;
        } catch (error) {
            P.toast(error.message, true);
            button.disabled = false;
        }
    });

    if (!form) return;

    const state = {
        draftId: C.draftId,
        entryId: C.entryId,
        saving: false,
        again: false,
        dirty: false,
        gone: false,
        sending: false,
        matches: [],
    };
    const autosave = C.mode !== 'fix';

    // ── Cosa c'è nel modulo ──────────────────────────────────────────────────

    const typeOf = () => ($('input[name="entry_type"]:checked', form) || $('input[name="entry_type"]', form))?.value || 'person';

    const pills = (name) => $$(`[data-cp-pills="${name}"] .cp-pill`, form).map((pill) => ({ it: pill.dataset.it, en: pill.dataset.en || '' }));

    function collect() {
        const value = (name) => form.elements[name] ? form.elements[name].value : '';
        const payload = {
            entry_type: typeOf(),
            title: value('title'),
            subtitle: value('subtitle'),
            real_date: value('real_date'),
            lore_date: value('lore_date'),
            image_url: value('image_url'),
            content_md: value('content_md'),
            title_en: value('title_en'),
            subtitle_en: value('subtitle_en'),
            content_md_en: value('content_md_en'),
            summary: value('summary'),
            aliases: pills('aliases'),
            tags: pills('tags'),
            quotes: $$('.cp-qrow', form).map((row) => ({
                text: $('[data-q="text"]', row).value,
                speaker: $('[data-q="speaker"]', row).value,
                text_en: row.dataset.textEn || '',
                speaker_en: row.dataset.speakerEn || '',
                featured: Number(row.dataset.featured || 0),
            })),
            relations: $$('.cp-rel', form).map((row) => ({
                target_id: Number(row.dataset.target),
                relation_type: $('select', row).value,
                label: row.dataset.label || '',
                label_en: row.dataset.labelEn || '',
                weight: Number(row.dataset.weight || 50),
                bidirectional: Number(row.dataset.both ?? 1),
            })),
        };
        if (C.staff) {
            payload.profile_username = value('profile_username');
            payload.status = ($('input[name="status"]:checked', form) || {}).value || 'published';
            payload.canonical_status = value('canonical_status');
            payload.featured = form.elements.featured?.checked ? 1 : 0;
        }
        return payload;
    }

    // ── Bozza che si salva da sola ───────────────────────────────────────────

    const savedBox = $('[data-cp-saved]');
    const discardRow = $('[data-cp-discard-row]', form);
    const setSaved = (text, error = false) => {
        if (!savedBox) return;
        $('span', savedBox).textContent = text;
        savedBox.classList.toggle('is-error', error);
    };
    const now = () => new Date().toLocaleTimeString(P.lang === 'en' ? 'en-GB' : 'it-IT', { hour: '2-digit', minute: '2-digit' });

    async function saveDraft() {
        if (!autosave || state.gone || state.sending) return;
        if (state.saving) {
            state.again = true;
            return;
        }
        state.saving = true;
        state.dirty = false;
        setSaved(S.saving);
        try {
            const data = await P.post(P.saveEndpoint, { action: 'draft', draft_id: state.draftId, entry_id: state.entryId, ...collect() });
            clearErrors(true);
            if (!state.draftId) {
                state.draftId = data.draft_id;
                // Ricaricando la pagina si riparte da questa bozza.
                const url = new URL(location.href);
                ['type', 'title', 'voce', 'id', 'riprendi'].forEach((key) => url.searchParams.delete(key));
                url.searchParams.set('bozza', data.draft_id);
                history.replaceState(null, '', url);
                if (discardRow) discardRow.hidden = false;
            }
            setSaved(fmt(S.saved, data.saved_at || now()));
        } catch (error) {
            state.dirty = true;
            const data = error.data || {};
            if (data.gone) state.gone = true;
            if (data.errors) showErrors(data.errors, false);
            setSaved(navigator.onLine === false ? S.offline : `${S.save_failed}: ${error.message}`, true);
        } finally {
            state.saving = false;
            if (state.again) {
                state.again = false;
                saveDraft();
            }
        }
    }

    const scheduleSave = debounce(saveDraft, 1500);
    const changed = () => {
        if (!autosave) return;
        state.dirty = true;
        scheduleSave();
    };
    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-cp-rel-search], [data-cp-pills] input, .cp-linker input')) return;
        changed();
    });
    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-cp-rel-search], [data-cp-pills] input, .cp-linker input, [data-cp-photo-input], [data-cp-inline-photo]')) return;
        changed();
    });
    window.addEventListener('online', () => { if (state.dirty) saveDraft(); });
    window.addEventListener('beforeunload', (event) => {
        if ((state.dirty || state.saving) && !state.sending) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden' && state.dirty) {
            scheduleSave.cancel();
            saveDraft();
        }
    });

    // ── Errori sui campi ─────────────────────────────────────────────────────

    function clearErrors(onlyDraft = false) {
        $$('.cp-field-error', form).forEach((el) => {
            if (!onlyDraft || el.dataset.draft === '1') el.remove();
        });
        $$('[aria-invalid="true"]', form).forEach((el) => el.removeAttribute('aria-invalid'));
    }

    function showErrors(errors, focus = true) {
        clearErrors();
        let first = null;
        Object.entries(errors).forEach(([field, message]) => {
            const input = $(`[data-cp-field="${field}"]`, form) || (field === 'quotes' ? $('[data-cp-quotes]', form) : null) || (field === 'entry_type' ? $('.cp-types', form) : null);
            const anchor = input ? (input.closest('.cp-count-wrap, .cp-at') || input) : $('.cp-sendbar', form);
            const note = document.createElement('p');
            note.className = 'cp-field-error';
            note.dataset.draft = focus ? '0' : '1';
            note.textContent = message;
            anchor.insertAdjacentElement('afterend', note);
            if (input) input.setAttribute('aria-invalid', 'true');
            if (!first && input) first = input;
        });
        if (focus && first) {
            first.closest('details')?.setAttribute('open', '');
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (first.focus) first.focus({ preventScroll: true });
        }
    }

    // ── Tipo di voce: cambiano i suggerimenti ────────────────────────────────

    form.addEventListener('change', (event) => {
        if (event.target.name !== 'entry_type') return;
        const type = typeOf();
        if (form.elements.title) form.elements.title.placeholder = S.title_ph[type] || '';
        if (form.elements.subtitle) form.elements.subtitle.placeholder = S.subtitle_ph[type] || '';
        const when = $('[data-cp-when]', form);
        if (when) when.textContent = S.when[type] || '';
        $$('[data-cp-only-person]', form).forEach((el) => { el.hidden = type !== 'person'; });
        checkTitle();
    });

    // ── Titolo: c'è già una voce così? ───────────────────────────────────────

    const titleInput = form.elements.title;
    const titleHelp = $('[data-cp-title-help]', form);
    const startTitle = titleInput ? titleInput.value.trim() : '';
    let titleToken = 0;

    async function checkTitle() {
        if (!titleInput || !titleHelp) return;
        const title = titleInput.value.trim();
        const token = ++titleToken;
        if (title.length < 2 || (C.mode !== 'new' && title === startTitle)) {
            titleHelp.innerHTML = '';
            state.matches = [];
            return;
        }
        try {
            const data = await P.post(P.saveEndpoint, { action: 'check_title', title, entry_id: state.entryId || 0 });
            if (token !== titleToken) return;
            state.matches = data.matches || [];
            if (state.matches.length) {
                const match = state.matches[0];
                titleHelp.className = 'cp-help is-warn';
                titleHelp.innerHTML = `<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>&nbsp; ${esc(fmt(S.title_taken, match.title))} <a href="${esc(match.url)}" target="_blank" rel="noopener">${esc(S.title_open)}</a> · <a href="${esc(match.edit_url)}">${esc(S.title_edit)}</a>`;
            } else if (C.mode === 'new') {
                const slug = slugify(title);
                titleHelp.className = 'cp-help';
                titleHelp.innerHTML = slug ? `<i class="fa-solid fa-check" aria-hidden="true"></i>&nbsp; ${esc(fmt(S.title_free, `${C.address}${C.typeSlugs[typeOf()]}/${slug}`))}` : '';
            } else {
                titleHelp.innerHTML = '';
            }
        } catch (_) {
            // Il controllo è un aiuto: se non risponde, si va avanti lo stesso.
        }
    }
    if (titleInput) {
        titleInput.addEventListener('input', debounce(checkTitle, 400));
        if (C.mode === 'new' && titleInput.value.trim()) checkTitle();
    }

    // ── Contatore di «In breve» ──────────────────────────────────────────────

    $$('[data-cp-count]', form).forEach((input) => {
        const box = input.parentElement.querySelector('.cp-count');
        const paint = () => {
            const n = input.value.length;
            box.textContent = n ? `${n} / ${input.maxLength}` : '';
        };
        input.addEventListener('input', paint);
        paint();
    });

    // ── Foto ─────────────────────────────────────────────────────────────────

    async function upload(file) {
        const body = new FormData();
        body.append('action', 'upload');
        body.append('file', file);
        const data = await P.post(P.saveEndpoint, body);
        return data.path;
    }

    const photo = $('[data-cp-photo]', form);
    if (photo) {
        const input = $('[data-cp-photo-input]', photo);
        const hidden = form.elements.image_url;
        const status = $('[data-cp-photo-status]', photo);
        const remove = $('[data-cp-photo-remove]', photo);
        const choose = $('[data-cp-photo-choose]', photo);
        const box = $('[data-cp-photo-img]', photo);

        const show = (path) => {
            hidden.value = path;
            box.innerHTML = path ? `<img src="${esc(path)}" alt="">` : '<i class="fa-regular fa-image" aria-hidden="true"></i>';
            photo.classList.toggle('has-photo', !!path);
            if (remove) remove.hidden = !path;
            if (choose) choose.textContent = path ? S.photo_change : S.photo_choose;
            changed();
        };

        const take = async (file) => {
            if (!file || !input) return;
            status.textContent = S.photo_uploading;
            photo.classList.add('is-busy');
            try {
                show(await upload(file));
                status.textContent = '';
            } catch (error) {
                status.textContent = error.message;
            } finally {
                photo.classList.remove('is-busy');
                input.value = '';
            }
        };

        if (input) {
            input.addEventListener('change', () => take(input.files[0]));
            ['dragenter', 'dragover'].forEach((name) => photo.addEventListener(name, (event) => {
                event.preventDefault();
                photo.classList.add('is-over');
            }));
            ['dragleave', 'drop'].forEach((name) => photo.addEventListener(name, (event) => {
                event.preventDefault();
                photo.classList.remove('is-over');
            }));
            photo.addEventListener('drop', (event) => take(event.dataTransfer?.files?.[0]));
        }
        remove?.addEventListener('click', () => show(''));
    }

    // ── Testo: barra, anteprima, [[ ]] ───────────────────────────────────────

    const text = form.elements.content_md;
    const preview = $('[data-cp-preview]', form);
    const suggest = $('[data-cp-suggest]', form);

    const replaceSelection = (before, after = '', placeholder = '') => {
        const start = text.selectionStart;
        const end = text.selectionEnd;
        const selected = text.value.slice(start, end) || placeholder;
        text.setRangeText(before + selected + after, start, end, 'end');
        // Resta selezionato il testo dentro i segni, così si scrive sopra.
        text.setSelectionRange(start + before.length, start + before.length + selected.length);
        text.focus();
        text.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const prefixLines = (prefix) => {
        const start = text.value.lastIndexOf('\n', text.selectionStart - 1) + 1;
        const endIndex = text.value.indexOf('\n', text.selectionEnd);
        const end = endIndex === -1 ? text.value.length : endIndex;
        const block = text.value.slice(start, end).split('\n').map((line) => (line.startsWith(prefix) ? line.slice(prefix.length) : prefix + line)).join('\n');
        text.setRangeText(block, start, end, 'end');
        text.focus();
        text.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const inlinePhoto = $('[data-cp-inline-photo]', form);
    inlinePhoto?.addEventListener('change', async () => {
        const file = inlinePhoto.files[0];
        if (!file) return;
        P.toast(S.photo_uploading);
        try {
            const path = await upload(file);
            const at = text.selectionStart;
            const before = text.value.slice(0, at);
            const gapBefore = before === '' || before.endsWith('\n\n') ? '' : (before.endsWith('\n') ? '\n' : '\n\n');
            text.setRangeText(`${gapBefore}![](${path})\n\n`, at, text.selectionEnd, 'end');
            text.dispatchEvent(new Event('input', { bubbles: true }));
        } catch (error) {
            P.toast(error.message, true);
        } finally {
            inlinePhoto.value = '';
        }
    });

    $$('[data-cp-tool]', form).forEach((button) => button.addEventListener('click', () => {
        if (!preview.hidden) setTab('write');
        const tool = button.dataset.cpTool;
        const en = P.lang === 'en';
        if (tool === 'bold') replaceSelection('**', '**', en ? 'bold' : 'grassetto');
        if (tool === 'italic') replaceSelection('*', '*', en ? 'italic' : 'corsivo');
        if (tool === 'heading') prefixLines('## ');
        if (tool === 'quote') prefixLines('> ');
        if (tool === 'spoiler') replaceSelection('[spoiler]\n', '\n[/spoiler]', '…');
        if (tool === 'link' && !openLinker()) {
            replaceSelection('[[', '');
            openSuggest();
        }
        if (tool === 'image') inlinePhoto?.click();
    }));

    // Scrivi / Anteprima.
    const tabs = $$('[data-cp-tab]', form);
    async function setTab(tab) {
        tabs.forEach((button) => {
            const on = button.dataset.cpTab === tab;
            button.classList.toggle('is-on', on);
            button.setAttribute('aria-selected', String(on));
        });
        const showPreview = tab === 'preview';
        text.hidden = showPreview;
        preview.hidden = !showPreview;
        closeSuggest();
        if (showPreview) closeLinker(false);
        if (!showPreview) {
            text.focus();
            return;
        }
        if (!text.value.trim()) {
            preview.innerHTML = `<p class="cp-muted">${esc(S.preview_empty)}</p>`;
            return;
        }
        preview.classList.add('is-loading');
        try {
            const data = await P.post(P.saveEndpoint, { action: 'preview', content_md: text.value, entry_id: state.entryId || 0 });
            preview.innerHTML = data.html || `<p class="cp-muted">${esc(S.preview_empty)}</p>`;
        } catch (error) {
            preview.innerHTML = `<p class="cp-muted">${esc(error.message)}</p>`;
        } finally {
            preview.classList.remove('is-loading');
        }
    }
    tabs.forEach((button) => button.addEventListener('click', () => setTab(button.dataset.cpTab)));
    $('[data-cp-preview-btn]', form)?.addEventListener('click', () => {
        setTab('preview');
        $('[data-cp-editor]', form).scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Un elenco di voci sotto un campo (per [[ e per i collegamenti).
    function suggestBox(box, onPick) {
        let items = [];
        let active = 0;
        const paint = () => $$('[data-i]', box).forEach((el) => {
            const on = Number(el.dataset.i) === active;
            el.classList.toggle('is-on', on);
            el.setAttribute('aria-selected', String(on));
        });
        box.addEventListener('mousedown', (event) => event.preventDefault());
        box.addEventListener('click', (event) => {
            const el = event.target.closest('[data-i]');
            if (el) onPick(items[Number(el.dataset.i)]);
        });
        return {
            get open() { return !box.hidden; },
            show(list, query, create) {
                items = list.slice(0, 6);
                if (create && query) items.push({ create: true, title: query });
                active = 0;
                if (!items.length) {
                    box.innerHTML = `<p class="cp-suggest__none">${esc(S.no_match)}</p>`;
                } else {
                    box.innerHTML = items.map((item, i) => {
                        if (item.create) return `<div class="cp-suggest__item" role="option" data-i="${i}"><span class="cp-suggest__img cp-suggest__img--plus">+</span><span>${esc(fmt(S.create, item.title))}</span><small>${esc(S.new_small)}</small></div>`;
                        const img = item.image ? `<img src="${esc(item.image)}" alt="">` : `<span class="cp-mono">${esc(item.mono || '')}</span>`;
                        return `<div class="cp-suggest__item" role="option" data-i="${i}"><span class="cp-suggest__img">${img}</span><span>${esc(item.title)}</span><small>${esc(item.typeLabel || '')}</small></div>`;
                    }).join('');
                }
                box.hidden = false;
                paint();
            },
            hide() {
                box.hidden = true;
                box.innerHTML = '';
                items = [];
            },
            key(event) {
                if (box.hidden || !items.length) return false;
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    active = (active + (event.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length;
                    paint();
                    return true;
                }
                if (event.key === 'Enter' || event.key === 'Tab') {
                    event.preventDefault();
                    onPick(items[active]);
                    return true;
                }
                return false;
            },
        };
    }

    const searchEntries = async (query) => {
        const data = await P.search({ action: 'palette', q: query });
        const list = [];
        ['person', 'event', 'meme'].forEach((type) => (data.groups?.[type] || []).forEach((hit) => {
            list.push({ ...hit, typeLabel: P.ui?.groups?.[type] || '' });
        }));
        return list;
    };

    // Il nome da mettere dentro [[ ]]: il titolo, o l'indirizzo se il titolo ha [ ] o | (romperebbero il link).
    const linkTarget = (item) => (item.create || !/[[\]|]/.test(item.title) ? item.title : String(item.url || '').split('/').pop());

    // [[ dentro il testo.
    let linkStart = -1;
    let linkToken = 0;
    const linkSuggest = suggestBox(suggest, (item) => {
        if (!item || linkStart < 0) return;
        const end = text.selectionStart;
        text.setRangeText(`[[${linkTarget(item)}]]`, linkStart, end, 'end');
        closeSuggest();
        text.focus();
        text.dispatchEvent(new Event('input', { bubbles: true }));
    });
    function closeSuggest() {
        linkStart = -1;
        linkSuggest.hide();
    }
    function openSuggest() {
        const caret = text.selectionStart;
        const before = text.value.slice(0, caret);
        const open = before.lastIndexOf('[[');
        if (open < 0 || before.slice(open).includes(']]') || before.slice(open).includes('\n')) {
            closeSuggest();
            return;
        }
        const query = before.slice(open + 2).split('|')[0];
        if (before.slice(open + 2).includes('|') || query.length > 60) {
            closeSuggest();
            return;
        }
        linkStart = open;
        placeSuggest();
        const token = ++linkToken;
        if (!query.trim()) {
            linkSuggest.show([], '', false);
            suggest.innerHTML = `<p class="cp-suggest__none">${esc(S.no_match)}</p>`;
            return;
        }
        searchEntries(query.trim()).then((list) => {
            if (token === linkToken && linkStart >= 0) linkSuggest.show(list, query.trim(), true);
        }).catch(() => {});
    }
    // L'elenco va sotto la riga dove si scrive: si misura con una copia del campo.
    const mirror = document.createElement('div');
    function placeAt(el, index, width) {
        const style = getComputedStyle(text);
        ['fontFamily', 'fontSize', 'lineHeight', 'paddingTop', 'paddingLeft', 'paddingRight', 'letterSpacing', 'borderLeftWidth', 'boxSizing'].forEach((key) => { mirror.style[key] = style[key]; });
        Object.assign(mirror.style, { position: 'absolute', visibility: 'hidden', whiteSpace: 'pre-wrap', overflowWrap: 'break-word', width: `${text.clientWidth}px`, top: '0', left: '0' });
        mirror.textContent = text.value.slice(0, index);
        const marker = document.createElement('span');
        marker.textContent = '​';
        mirror.appendChild(marker);
        text.parentElement.appendChild(mirror);
        const top = marker.offsetTop - text.scrollTop + parseFloat(style.lineHeight || '24');
        const left = Math.min(marker.offsetLeft, Math.max(0, text.clientWidth - width));
        mirror.remove();
        el.style.top = `${Math.max(0, top) + 8}px`;
        el.style.left = `${Math.max(12, left)}px`;
    }
    const placeSuggest = () => placeAt(suggest, text.selectionStart, 300);

    // Collegare parole scelte da chi scrive: si seleziona «il fratello di mychy», si preme [[ ]] e si
    // sceglie la voce. Nel testo si legge quello che si è scritto, il link porta alla voce:
    // [[Voce|il fratello di mychy]]. Su un collegamento già fatto si cambia la voce, il testo resta.
    const linker = document.createElement('div');
    linker.className = 'cp-linker';
    linker.hidden = true;
    linker.setAttribute('role', 'dialog');
    linker.setAttribute('aria-label', S.link_dialog);
    linker.innerHTML = `<p class="cp-linker__head"></p>
        <label class="cp-linker__find"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" autocomplete="off" spellcheck="false" placeholder="${esc(S.link_ph)}" aria-label="${esc(S.link_ph)}"></label>
        <div class="cp-linker__list" role="listbox" hidden></div>
        <p class="cp-linker__tip"></p>`;
    text.parentElement.appendChild(linker);
    const linkerInput = $('input', linker);
    let linkRange = null; // [inizio, fine, testo che si legge]
    let linkerToken = 0;

    function closeLinker(restore = true) {
        if (linker.hidden) return;
        linker.hidden = true;
        linkerList.hide();
        if (restore && linkRange) {
            text.focus();
            text.setSelectionRange(linkRange[0], linkRange[1]);
        }
        linkRange = null;
    }

    const linkerList = suggestBox($('.cp-linker__list', linker), (item) => {
        if (!item || !linkRange) return;
        const [start, end, label] = linkRange;
        const same = label.toLowerCase() === String(item.title).trim().toLowerCase();
        const link = same ? `[[${linkTarget(item)}]]` : `[[${linkTarget(item)}|${label}]]`;
        closeLinker(false);
        text.focus();
        text.setRangeText(link, start, end, 'end');
        text.dispatchEvent(new Event('input', { bubbles: true }));
    });

    const searchLinker = debounce(async () => {
        const query = linkerInput.value.trim();
        const token = ++linkerToken;
        if (!query) {
            linkerList.hide();
            return;
        }
        try {
            const list = (await searchEntries(query)).filter((item) => Number(item.id) !== Number(state.entryId));
            if (token === linkerToken && !linker.hidden) linkerList.show(list, query.slice(0, 120), true);
        } catch (_) {
            // Si riprova scrivendo.
        }
    }, 180);

    /** Apre la scelta della voce per le parole selezionate; false se non c'è niente di selezionato. */
    function openLinker() {
        let start = text.selectionStart;
        let end = text.selectionEnd;
        // Gli spazi intorno restano fuori dal collegamento.
        while (start < end && /\s/.test(text.value[start])) start++;
        while (end > start && /\s/.test(text.value[end - 1])) end--;
        const chosen = text.value.slice(start, end);
        if (!chosen || chosen.includes('\n') || chosen.length > 130) return false;
        const existing = chosen.match(/^\[\[([^[\]|\n]+)(?:\|([^[\]\n]+))?\]\]$/);
        const label = (existing ? (existing[2] || existing[1]) : chosen).trim();
        if (!label || label.length > 120 || /[[\]]/.test(label)) return false;

        linkRange = [start, end, label];
        $('.cp-linker__head', linker).innerHTML = esc(S.link_to).replace('%s', `<b>${esc(label)}</b>`);
        $('.cp-linker__tip', linker).textContent = fmt(S.link_tip, label);
        closeSuggest();
        placeAt(linker, start, 320);
        linker.hidden = false;
        linkerInput.value = existing ? existing[1] : label;
        linkerInput.focus();
        linkerInput.select();
        searchLinker();
        return true;
    }

    linkerInput.addEventListener('input', searchLinker);
    linkerInput.addEventListener('keydown', (event) => {
        if (linkerList.key(event)) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeLinker();
        } else if (event.key === 'Enter') {
            event.preventDefault();
        }
    });
    document.addEventListener('mousedown', (event) => {
        if (!linker.hidden && !linker.contains(event.target) && !event.target.closest('[data-cp-tool="link"]')) closeLinker(false);
    });
    text.addEventListener('input', () => {
        if (text.value.slice(0, text.selectionStart).lastIndexOf('[[') >= 0) openSuggest();
        else closeSuggest();
    });
    text.addEventListener('keydown', (event) => {
        if (linkSuggest.key(event)) return;
        if (event.key === 'Escape' && linkSuggest.open) {
            event.preventDefault();
            closeSuggest();
        }
    });
    text.addEventListener('blur', () => setTimeout(closeSuggest, 120));

    // ── Soprannomi e tag ─────────────────────────────────────────────────────

    $$('[data-cp-pills]', form).forEach((box) => {
        const input = $('input', box);
        const max = Number(box.dataset.max || 10);
        const add = () => {
            const value = input.value.replace(/,/g, ' ').replace(/\s+/g, ' ').trim();
            input.value = '';
            if (!value) return;
            const exists = $$('.cp-pill', box).some((pill) => pill.dataset.it.toLowerCase() === value.toLowerCase());
            if (exists || $$('.cp-pill', box).length >= max) return;
            const pill = document.createElement('span');
            pill.className = 'cp-pill';
            pill.dataset.it = value;
            pill.dataset.en = '';
            pill.innerHTML = `${esc(value)}<button type="button" aria-label="${esc(S.remove)}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>`;
            box.insertBefore(pill, input);
            changed();
        };
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                add();
            } else if (event.key === 'Backspace' && !input.value) {
                const last = $$('.cp-pill', box).pop();
                if (last) {
                    last.remove();
                    changed();
                }
            }
        });
        input.addEventListener('blur', add);
        box.addEventListener('click', (event) => {
            const x = event.target.closest('.cp-pill button');
            if (x) {
                x.closest('.cp-pill').remove();
                changed();
                input.focus();
            } else if (event.target === box) {
                input.focus();
            }
        });
    });

    // ── Collegamenti ─────────────────────────────────────────────────────────

    const rels = $('[data-cp-relations]', form);
    if (rels) {
        const find = $('[data-cp-rel-search]', rels);
        const box = $('[data-cp-rel-suggest]', rels);
        const max = Number(rels.dataset.max || 20);
        const options = Object.entries(S.rel).map(([value, label]) => `<option value="${esc(value)}">${esc(label)}</option>`).join('');
        let token = 0;

        const relSuggest = suggestBox(box, (item) => {
            if (!item || item.create) return;
            const taken = $$('.cp-rel', rels).some((row) => Number(row.dataset.target) === Number(item.id));
            if (!taken && Number(item.id) !== Number(state.entryId) && $$('.cp-rel', rels).length < max) {
                const row = document.createElement('div');
                row.className = 'cp-rel';
                row.dataset.target = item.id;
                row.dataset.both = '1';
                row.dataset.weight = '50';
                const img = item.image ? `<img src="${esc(item.image)}" alt="">` : `<span class="cp-mono" aria-hidden="true">${esc(item.mono || '')}</span>`;
                row.innerHTML = `<span class="cp-rel__img">${img}</span><select aria-label="${esc(S.rel.related)}">${options}</select><b>${esc(item.title)}</b><button type="button" class="cp-rel__x" aria-label="${esc(S.remove)}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>`;
                rels.insertBefore(row, $('.cp-rels__find', rels));
                changed();
            }
            find.value = '';
            relSuggest.hide();
            find.focus();
        });

        find.addEventListener('input', debounce(async () => {
            const query = find.value.trim();
            const mine = ++token;
            if (query.length < 1) {
                relSuggest.hide();
                return;
            }
            try {
                const list = (await searchEntries(query)).filter((item) => Number(item.id) !== Number(state.entryId));
                if (mine === token) relSuggest.show(list, query, false);
            } catch (_) {
                relSuggest.hide();
            }
        }, 220));
        find.addEventListener('keydown', (event) => {
            if (relSuggest.key(event)) return;
            if (event.key === 'Escape') relSuggest.hide();
            if (event.key === 'Enter') event.preventDefault();
        });
        find.addEventListener('blur', () => setTimeout(() => relSuggest.hide(), 120));

        rels.addEventListener('click', (event) => {
            const x = event.target.closest('.cp-rel__x');
            if (!x) return;
            x.closest('.cp-rel').remove();
            changed();
        });
        // Un tipo scelto dal menu prende il posto dell'etichetta scritta a mano.
        rels.addEventListener('change', (event) => {
            const row = event.target.closest('.cp-rel');
            if (!row || event.target.tagName !== 'SELECT') return;
            row.dataset.label = '';
            row.dataset.labelEn = '';
        });
    }

    // ── Citazioni ────────────────────────────────────────────────────────────

    const quotes = $('[data-cp-quotes]', form);
    $('[data-cp-quote-add]', form)?.addEventListener('click', () => {
        if ($$('.cp-qrow', quotes).length >= Number(quotes.dataset.max || 10)) return;
        const row = document.createElement('div');
        row.className = 'cp-qrow';
        row.innerHTML = `<input class="cp-input" maxlength="300" placeholder="${esc(S.quote_ph)}" aria-label="${esc(S.quote_ph)}" data-q="text">`
            + `<input class="cp-input" maxlength="80" placeholder="${esc(S.quote_who_ph)}" aria-label="${esc(S.quote_who_ph)}" data-q="speaker">`
            + `<button type="button" class="cp-icon" data-cp-quote-remove aria-label="${esc(S.remove)}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>`;
        quotes.appendChild(row);
        $('input', row).focus();
    });
    quotes?.addEventListener('click', (event) => {
        const x = event.target.closest('[data-cp-quote-remove]');
        if (!x) return;
        x.closest('.cp-qrow').remove();
        changed();
    });

    // ── Butta la bozza ───────────────────────────────────────────────────────

    $$('[data-cp-discard]', form).forEach((button) => button.addEventListener('click', async () => {
        if (!state.draftId || !window.confirm(S.confirm_discard)) return;
        state.sending = true;
        scheduleSave.cancel();
        try {
            await P.post(P.saveEndpoint, { action: 'discard', draft_id: state.draftId });
            const url = new URL(C.writeUrl, location.origin);
            if (button.hasAttribute('data-cp-restart') && state.entryId) url.searchParams.set('voce', state.entryId);
            location.href = url;
        } catch (error) {
            state.sending = false;
            P.toast(error.message, true);
        }
    }));

    // ── Invia, pubblica, approva ─────────────────────────────────────────────

    const submit = $('[data-cp-submit]', form);
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (state.sending) return;
        if (C.staff && C.mode === 'new' && state.matches.length && !window.confirm(fmt(S.confirm_duplicate, state.matches[0].title))) return;

        scheduleSave.cancel();
        state.sending = true;
        const label = submit.textContent;
        submit.disabled = true;
        submit.textContent = S.sending;
        clearErrors();
        try {
            const data = C.mode === 'fix'
                ? await P.post(C.reviewEndpoint, { action: 'approve', id: C.fixId, fields: collect() })
                : await P.post(P.saveEndpoint, { action: 'submit', draft_id: state.draftId, entry_id: state.entryId, ...collect() });
            location.href = data.url;
        } catch (error) {
            state.sending = false;
            submit.disabled = false;
            submit.textContent = label;
            const data = error.data || {};
            if (data.draft_id && !state.draftId) state.draftId = data.draft_id;
            if (data.errors) showErrors(data.errors);
            P.toast(error.message || S.error, true);
        }
    });
})();
