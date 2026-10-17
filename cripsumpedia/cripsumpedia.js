/* Cripsumpedia — le pagine da leggere.
   Foto che entrano a fuoco, anteprima delle voci nominate nel testo, condividi,
   una voce a caso, l'indice che segue la lettura. Editor e pannello dello staff
   usano ancora staff.js fino alla fase 4. */
(() => {
    'use strict';

    const cfg = window.Cripsumpedia || {};
    const en = cfg.lang === 'en';
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

    function api(params) {
        const url = new URL(cfg.searchEndpoint || '/api/cripsumpedia_search.php', location.origin);
        Object.entries({ lang: cfg.lang || 'it', ...params }).forEach(([k, v]) => v !== undefined && v !== null && url.searchParams.set(k, v));
        return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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
        toastTimer = setTimeout(() => { el.hidden = true; }, 2600);
    }

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

    function init() {
        initFade();
        initPeek();
        initShare();
        initRandom();
        initToc();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
