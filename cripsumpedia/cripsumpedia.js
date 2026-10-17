/* Cripsumpedia — le pagine da leggere.
   Foto che entrano a fuoco, anteprima delle voci nominate nel testo, condividi,
   una voce a caso, l'indice che segue la lettura, la ricerca sopra la pagina.
   Dalla fase 4 anche i pulsanti dello staff in «Da approvare» e in cronologia
   (approva, rifiuta, ripristina); la pagina Scrivi ha il suo scrivi.js, che
   usa post() e toast() da qui. */
(() => {
    'use strict';

    const cfg = window.Cripsumpedia || {};
    const en = cfg.lang === 'en';
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

    function api(params, signal) {
        const url = new URL(cfg.searchEndpoint || '/api/cripsumpedia_search.php', location.origin);
        Object.entries({ lang: cfg.lang || 'it', ...params }).forEach(([k, v]) => v !== undefined && v !== null && url.searchParams.set(k, v));
        return fetch(url, { credentials: 'same-origin', signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((res) => res.json().then((data) => {
                if (!res.ok || data.ok === false) throw new Error(data.message || `HTTP ${res.status}`);
                return data;
            }));
    }

    let toastTimer = 0;
    function toast(message, error = false) {
        const el = document.querySelector('[data-cp-toast]');
        if (!el) return;
        el.textContent = message;
        el.classList.toggle('is-error', error);
        el.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.hidden = true; }, error ? 4200 : 2600);
    }

    // Scrivere e approvare (fase 4): POST con il token del sito. Un errore
    // porta con sé la risposta (err.data), per i campi da segnare.
    function post(endpoint, body) {
        const isForm = body instanceof FormData;
        const url = new URL(endpoint, location.origin);
        url.searchParams.set('lang', cfg.lang || 'it');
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': cfg.csrf || '', 'X-Requested-With': 'XMLHttpRequest', ...(isForm ? {} : { 'Content-Type': 'application/json' }) },
            body: isForm ? body : JSON.stringify(body),
        }).then(async (res) => {
            let data;
            try {
                data = await res.json();
            } catch (_) {
                data = { ok: false, message: res.status === 413 ? (en ? 'File too large.' : 'File troppo grande.') : `HTTP ${res.status}` };
            }
            data.httpStatus = res.status;
            if (!res.ok || data.ok === false) {
                const error = new Error(data.message || `HTTP ${res.status}`);
                error.data = data;
                throw error;
            }
            return data;
        });
    }

    cfg.toast = toast;
    cfg.post = post;
    cfg.search = api;
    cfg.escapeHtml = escapeHtml;

    // Le foto entrano a fuoco quando sono pronte, non a pezzi mentre scaricano.
    function initFade() {
        $$('img[data-cp-fade]').forEach((img) => {
            const done = () => img.classList.add('is-loaded');
            if (img.complete) done();
            else {
                img.addEventListener('load', done, { once: true });
                img.addEventListener('error', done, { once: true });
            }
        });
    }

    // Anteprima di una voce nominata nel testo, al passaggio del mouse.
    function initPeek() {
        const card = document.querySelector('[data-cp-hover-card]');
        if (!card || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

        const cache = new Map();
        let current = null;
        let timer = 0;

        const place = (link) => {
            const r = link.getBoundingClientRect();
            const width = card.offsetWidth || 340;
            const left = Math.max(12, Math.min(r.left, window.innerWidth - width - 12));
            const below = r.bottom + 10;
            const top = below + card.offsetHeight > window.innerHeight - 12 ? r.top - card.offsetHeight - 10 : below;
            card.style.left = `${left}px`;
            card.style.top = `${top}px`;
        };

        const render = (data) => {
            const meta = [data.type, data.date].filter(Boolean).join(' · ');
            const media = data.image
                ? `<img src="${escapeHtml(data.image)}" alt="">`
                : `<span class="cp-mono">${escapeHtml((data.title || '?').slice(0, 1).toUpperCase())}</span>`;
            card.innerHTML = `<span class="cp-hover-card__img">${media}</span><span><small>${escapeHtml(meta)}</small><strong>${escapeHtml(data.title)}</strong>${data.line ? `<p>${escapeHtml(data.line)}</p>` : ''}</span>`;
        };

        const show = async (link) => {
            const id = link.dataset.loreId;
            current = link;
            try {
                if (!cache.has(id)) cache.set(id, api({ action: 'preview', id }).then((d) => d.html));
                const data = await cache.get(id);
                if (current !== link) return;
                render(data);
                card.hidden = false;
                place(link);
                requestAnimationFrame(() => card.classList.add('is-open'));
            } catch {
                cache.delete(id);
            }
        };

        const hide = () => {
            current = null;
            card.classList.remove('is-open');
            card.hidden = true;
        };

        document.addEventListener('mouseover', (event) => {
            const link = event.target.closest('a.cp-lore-link[data-lore-id]');
            if (!link || link === current) return;
            clearTimeout(timer);
            timer = setTimeout(() => show(link), 220);
        });
        document.addEventListener('mouseout', (event) => {
            const link = event.target.closest('a.cp-lore-link[data-lore-id]');
            if (!link || link.contains(event.relatedTarget)) return;
            clearTimeout(timer);
            hide();
        });
        window.addEventListener('scroll', hide, { passive: true });
    }

    function initShare() {
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-cp-share]');
            if (!button) return;
            const data = { title: document.title, url: location.href.split('#')[0] };
            if (navigator.share) {
                try {
                    await navigator.share(data);
                    return;
                } catch (err) {
                    if (err && err.name === 'AbortError') return; // chiusa senza condividere
                    // Condivisione non disponibile qui: si copia il link.
                }
            }
            try {
                await navigator.clipboard.writeText(data.url);
                toast(en ? 'Link copied' : 'Link copiato');
            } catch {
                toast(data.url);
            }
        });
    }

    function initRandom() {
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-cp-random]');
            if (!button) return;
            button.disabled = true;
            try {
                const data = await api({ action: 'random', type: button.dataset.cpRandomType || undefined });
                location.href = data.url;
            } catch (err) {
                toast(err.message, true);
                button.disabled = false;
            }
        });
    }

    // L'indice della voce segna il titoletto che si sta leggendo.
    function initToc() {
        const links = $$('[data-cp-toc]');
        if (!links.length || !('IntersectionObserver' in window)) return;
        const byId = new Map(links.map((link) => [link.dataset.cpToc, link]));
        const headings = links.map((link) => document.getElementById(link.dataset.cpToc)).filter(Boolean);
        const visible = new Set();

        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => (entry.isIntersecting ? visible.add(entry.target) : visible.delete(entry.target)));
            const first = headings.find((h) => visible.has(h))
                || [...headings].reverse().find((h) => h.getBoundingClientRect().top < 120);
            links.forEach((link) => link.classList.remove('is-on'));
            if (first) byId.get(first.id)?.classList.add('is-on');
        }, { rootMargin: '-100px 0px -60% 0px' });
        headings.forEach((h) => io.observe(h));
    }

    // ── Ricerca sopra la pagina (fase 3) ──
    // Si apre dal campo della barra, dall'icona sul telefono o dal campo grande
    // dell'ingresso. Senza JavaScript quei campi portano alla pagina dei risultati.
    function initPalette() {
        const ui = cfg.ui;
        const openers = $$('[data-cp-search-open]');
        if (!ui || !openers.length) return;

        const cache = new Map();
        let dim = null;
        let box = null;
        let input = null;
        let list = null;
        let closeButton = null;
        let opener = null;
        let items = [];
        let active = -1;
        let timer = 0;
        let controller = null;
        let shown = null;
        let refocusing = false;

        const highlight = (text, query) => {
            text = String(text ?? '');
            const q = String(query ?? '').trim().toLowerCase();
            if (!q) return escapeHtml(text);
            const lower = text.toLowerCase();
            let out = '';
            let from = 0;
            for (let at = lower.indexOf(q); at !== -1; at = lower.indexOf(q, from)) {
                out += escapeHtml(text.slice(from, at)) + '<mark>' + escapeHtml(text.slice(at, at + q.length)) + '</mark>';
                from = at + q.length;
            }
            return out + escapeHtml(text.slice(from));
        };
        const fill = (template, value) => escapeHtml(template).replace('%s', value);
        const clean = (value) => String(value || '').trim().replace(/\s+/g, ' ');

        function build() {
            dim = document.createElement('div');
            dim.className = 'cp-dim';
            dim.hidden = true;
            box = document.createElement('div');
            box.className = 'cp-palette';
            box.hidden = true;
            box.setAttribute('role', 'dialog');
            box.setAttribute('aria-modal', 'true');
            box.setAttribute('aria-label', ui.search_pedia);
            box.innerHTML = `
                <form class="cp-palette__in" role="search" action="${escapeHtml(cfg.searchUrl || '')}" method="get">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" name="q" autocomplete="off" spellcheck="false" placeholder="${escapeHtml(ui.search_hint)}" aria-label="${escapeHtml(ui.search_pedia)}" role="combobox" aria-expanded="true" aria-controls="cp-pal-list" aria-autocomplete="list">
                    <button class="cp-kbd" type="button" data-cp-pal-close aria-label="${escapeHtml(ui.close)}">Esc</button>
                </form>
                <div class="cp-palette__body" id="cp-pal-list" role="listbox" aria-label="${escapeHtml(ui.palette_results)}"></div>
                <div class="cp-palette__foot" aria-hidden="true">
                    <span><span class="cp-kbd">↑</span> <span class="cp-kbd">↓</span> ${escapeHtml(ui.hint_move)}</span>
                    <span><span class="cp-kbd">${escapeHtml(ui.key_enter)}</span> ${escapeHtml(ui.hint_open)}</span>
                    <span><span class="cp-kbd">Esc</span> ${escapeHtml(ui.hint_close)}</span>
                </div>`;
            document.body.append(dim, box);
            input = box.querySelector('input');
            list = box.querySelector('#cp-pal-list');
            closeButton = box.querySelector('[data-cp-pal-close]');

            dim.addEventListener('click', close);
            closeButton.addEventListener('click', close);
            input.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => search(input.value), 140);
            });
            box.addEventListener('keydown', onKey);
            list.addEventListener('mousemove', (event) => {
                const option = event.target.closest('[role="option"]');
                if (option && items.indexOf(option) !== active) setActive(items.indexOf(option), false);
            });
            box.querySelector('form').addEventListener('submit', (event) => {
                // Invio: la voce scelta con le frecce; se non ce n'è una, la pagina dei risultati.
                if (active >= 0 && items[active]) {
                    event.preventDefault();
                    location.href = items[active].href;
                } else if (!clean(input.value)) {
                    event.preventDefault();
                }
            });
        }

        function open(from) {
            if (!box) build();
            opener = from;
            input.value = 'value' in from ? from.value : (new URL(from.href, location.href).searchParams.get('q') || '');
            const scrollbar = window.innerWidth - document.documentElement.clientWidth;
            document.documentElement.style.setProperty('--cp-scrollbar', `${scrollbar}px`);
            document.documentElement.classList.add('cp-pal-open');
            dim.hidden = false;
            box.hidden = false;
            requestAnimationFrame(() => {
                dim.classList.add('is-open');
                box.classList.add('is-open');
            });
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
            search(input.value);
        }

        function close() {
            if (!box || box.hidden) return;
            clearTimeout(timer);
            if (controller) controller.abort();
            dim.classList.remove('is-open');
            box.classList.remove('is-open');
            dim.hidden = true;
            box.hidden = true;
            document.documentElement.classList.remove('cp-pal-open');
            if (opener) {
                if ('value' in opener) opener.value = input.value;
                refocusing = true;
                opener.focus({ preventScroll: true });
                refocusing = false;
            }
        }

        function setActive(index, scroll = true) {
            items.forEach((item) => {
                item.classList.remove('is-on');
                item.setAttribute('aria-selected', 'false');
            });
            active = items.length ? index : -1;
            if (active < 0) {
                input.removeAttribute('aria-activedescendant');
                return;
            }
            const item = items[active];
            item.classList.add('is-on');
            item.setAttribute('aria-selected', 'true');
            input.setAttribute('aria-activedescendant', item.id);
            if (scroll) item.scrollIntoView({ block: 'nearest' });
        }

        function onKey(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
            } else if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && items.length) {
                event.preventDefault();
                const step = event.key === 'ArrowDown' ? 1 : -1;
                setActive(active < 0 ? (step > 0 ? 0 : items.length - 1) : (active + step + items.length) % items.length);
            } else if (event.key === 'Tab') {
                // Il focus resta dentro la finestra: campo ↔ pulsante per chiudere.
                event.preventDefault();
                (document.activeElement === input ? closeButton : input).focus();
            }
        }

        async function search(raw) {
            const query = clean(raw);
            if (query === shown) return;
            if (cache.has(query)) return render(cache.get(query));
            if (controller) controller.abort();
            controller = new AbortController();
            list.setAttribute('aria-busy', 'true');
            if (!list.firstElementChild) list.innerHTML = `<p class="cp-pal-status">${escapeHtml(ui.searching)}</p>`;
            try {
                const data = await api({ action: 'palette', q: query }, controller.signal);
                cache.set(query, data);
                if (clean(input.value) === query) render(data);
            } catch (err) {
                if (err.name !== 'AbortError') list.innerHTML = `<p class="cp-pal-status">${escapeHtml(err.message)}</p>`;
            } finally {
                list.removeAttribute('aria-busy');
            }
        }

        function render(data) {
            const query = data.query || '';
            let n = 0;
            const hit = (entry) => {
                const media = entry.image
                    ? `<img src="${escapeHtml(entry.image)}" alt="" loading="lazy">`
                    : `<span class="cp-mono">${escapeHtml(entry.mono || '?')}</span>`;
                let line = entry.snippet ? highlight(entry.snippet, query) : escapeHtml(entry.line || '');
                if (entry.alias) line = (line ? `${line} · ` : '') + fill(ui.aka, highlight(entry.alias, query));
                return `<a class="cp-pal-hit" role="option" aria-selected="false" id="cp-pal-${n++}" href="${escapeHtml(entry.url)}">
                    <span class="cp-pal-hit__img">${media}</span>
                    <span><strong>${highlight(entry.title, query)}</strong>${line ? `<span class="cp-pal-hit__line">${line}</span>` : ''}</span>
                    <i class="fa-solid fa-arrow-turn-down fa-rotate-90" aria-hidden="true"></i>
                </a>`;
            };
            const group = (label, entries) => `<div class="cp-pal-group" role="group" aria-label="${escapeHtml(label)}"><h4 aria-hidden="true">${escapeHtml(label)}</h4>${entries.map(hit).join('')}</div>`;

            let html = '';
            if (!query) {
                if (data.popular && data.popular.length) html += group(ui.popular_now, data.popular);
            } else {
                ['person', 'event', 'meme'].forEach((type) => {
                    if (data.groups[type] && data.groups[type].length) html += group(ui.groups[type], data.groups[type]);
                });
                if (data.text && data.text.length) html += group(ui.group_text, data.text);
                if (!data.total) {
                    html += `<p class="cp-pal-status"><strong>${fill(ui.nothing_found, escapeHtml(query))}</strong> ${escapeHtml(ui.nothing_hint)}</p>`;
                }
                let tail = '';
                if (data.total && data.all_url) {
                    tail += `<a class="cp-pal-hit" role="option" aria-selected="false" id="cp-pal-${n++}" href="${escapeHtml(data.all_url)}">
                        <span class="cp-pal-hit__img"><i class="fa-solid fa-list" aria-hidden="true"></i></span>
                        <span><strong>${escapeHtml(ui.all_results)}</strong><span class="cp-pal-hit__line">${escapeHtml(data.total_label || data.total)}</span></span>
                        <i class="fa-solid fa-arrow-turn-down fa-rotate-90" aria-hidden="true"></i>
                    </a>`;
                }
                if (data.write_url) {
                    tail += `<a class="cp-pal-hit cp-pal-hit--create" role="option" aria-selected="false" id="cp-pal-${n++}" href="${escapeHtml(data.write_url)}">
                        <span class="cp-pal-hit__img"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                        <span>${escapeHtml(ui.write_hint)} <strong>${fill(ui.write_this, escapeHtml(query))}</strong></span>
                        <i class="fa-solid fa-arrow-turn-down fa-rotate-90" aria-hidden="true"></i>
                    </a>`;
                }
                if (tail) html += `<div class="cp-pal-tail">${tail}</div>`;
            }

            list.innerHTML = html;
            shown = query;
            items = $$('[role="option"]', list);
            setActive(query && items.length ? 0 : -1, false);
        }

        openers.forEach((el) => {
            if (el.tagName === 'INPUT') {
                // Il campo non prende il focus: si apre la finestra al suo posto (al tocco,
                // dentro lo stesso gesto, così sul telefono si apre anche la tastiera).
                el.addEventListener('pointerdown', (event) => event.preventDefault());
                el.addEventListener('click', () => open(el));
                el.addEventListener('focus', () => { if (!refocusing) open(el); });
            } else {
                el.addEventListener('click', (event) => {
                    event.preventDefault();
                    open(el);
                });
            }
        });
    }

    // «Da approvare» e cronologia (solo staff): approva, rifiuta, ripristina.
    function initReview() {
        const root = document.querySelector('[data-cp-review]');
        if (!root) return;
        const endpoint = cfg.reviewEndpoint || '/api/cripsumpedia_review.php';
        const reviewUrl = root.dataset.reviewUrl || '';
        const next = (data) => {
            if (data.next && reviewUrl) location.href = `${reviewUrl}?id=${encodeURIComponent(data.next)}`;
            else location.href = data.url || reviewUrl || location.href;
        };

        $$('[data-cp-review-tab]', root).forEach((tab) => tab.addEventListener('click', () => {
            $$('[data-cp-review-tab]', root).forEach((other) => {
                const on = other === tab;
                other.classList.toggle('is-on', on);
                other.setAttribute('aria-selected', String(on));
            });
            $$('[data-cp-review-pane]', root).forEach((pane) => { pane.hidden = pane.dataset.cpReviewPane !== tab.dataset.cpReviewTab; });
        }));

        root.addEventListener('click', async (event) => {
            const approve = event.target.closest('[data-cp-approve]');
            const restore = event.target.closest('[data-cp-restore]');
            const opener = event.target.closest('[data-cp-reject-open]');
            const cancel = event.target.closest('[data-cp-reject-cancel]');
            const form = root.querySelector('[data-cp-reject]');

            if (opener || cancel) {
                const open = !!opener && form.hidden;
                form.hidden = !open;
                root.querySelector('[data-cp-reject-open]')?.setAttribute('aria-expanded', String(open));
                if (open) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    form.querySelector('input')?.focus({ preventScroll: true });
                }
                return;
            }
            const button = approve || restore;
            if (!button || button.disabled) return;
            if (restore && !window.confirm(restore.dataset.confirm || '')) return;
            button.disabled = true;
            try {
                const data = approve
                    ? await post(endpoint, { action: 'approve', id: Number(approve.dataset.cpApprove) })
                    : await post(endpoint, { action: 'restore', revision_id: Number(restore.dataset.cpRestore) });
                if (approve) {
                    toast(en ? 'Published.' : 'Pubblicata.');
                    setTimeout(() => next(data), 500);
                } else {
                    location.href = data.url;
                }
            } catch (error) {
                button.disabled = false;
                toast(error.message, true);
            }
        });

        root.querySelector('[data-cp-reject]')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const submit = form.querySelector('[type="submit"]');
            const reason = form.querySelector('input[name="reason"]:checked')?.value || '';
            if (!reason) {
                toast(en ? 'Choose a reason.' : 'Scegli un motivo.', true);
                return;
            }
            submit.disabled = true;
            try {
                const data = await post(endpoint, { action: 'reject', id: Number(form.dataset.cpReject), reason, note: form.elements.note.value });
                toast(en ? 'Not published: the reason goes to their Inbox.' : 'Non pubblicata: il motivo arriva nella Posta.');
                setTimeout(() => next(data), 700);
            } catch (error) {
                submit.disabled = false;
                toast(error.message, true);
            }
        });
    }

    function init() {
        initFade();
        initPeek();
        initShare();
        initRandom();
        initToc();
        initPalette();
        initReview();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
