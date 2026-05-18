/**
 * Cripsum™ — News Popup  v1.0
 * Zero dipendenze. Non tocca nulla del sito esistente.
 */
(() => {
    'use strict';

    // ─── Config ───────────────────────────────────────────────
    const LS_KEY     = 'cripsum_news_seen'; // localStorage key
    const API_BASE   = '/api/get_news.php';
    const OPEN_DELAY = 900; // ms prima dell'apertura automatica

    // ─── Stato modulo ─────────────────────────────────────────
    let newsCache  = null; // array news, cachato dopo la prima fetch
    let activeId   = null; // id news selezionata nella sidebar
    let overlayEl  = null;

    // ─── Rilevamento lingua (identico a home.js) ───────────────
    const lang = location.pathname.split('/').find(s => s === 'it' || s === 'en') || 'it';

    // ─── i18n stringhe UI ─────────────────────────────────────
    const ui = {
        it: {
            label:        'News & Changelog',
            badge:        'NEW',
            empty_title:  'Nessuna novità',
            empty_sub:    'Torna presto per aggiornamenti.',
            close:        'Chiudi',
            pinned:       '📌',
        },
        en: {
            label:        'News & Changelog',
            badge:        'NEW',
            empty_title:  'Nothing here yet',
            empty_sub:    'Check back soon for updates.',
            close:        'Close',
            pinned:       '📌',
        },
    }[lang];

    // ─── Helpers ──────────────────────────────────────────────
    const esc = (v) => String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const formatDate = (dateStr) => {
        if (!dateStr) return '';
        try {
            return new Date(dateStr).toLocaleDateString(
                lang === 'en' ? 'en-GB' : 'it-IT',
                { day: '2-digit', month: 'short', year: 'numeric' }
            );
        } catch {
            return dateStr;
        }
    };

    const lsGet = () => {
        try { return parseInt(localStorage.getItem(LS_KEY), 10) || 0; }
        catch { return 0; }
    };

    const lsSet = (id) => {
        try { localStorage.setItem(LS_KEY, String(id)); }
        catch { /* Safari private: niente */ }
    };

    // ─── Fetch news (con cache in-memory) ─────────────────────
    const fetchNews = async () => {
        if (newsCache) return newsCache;

        const res  = await fetch(`${API_BASE}?lang=${lang}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();
        newsCache  = data;
        return data;
    };

    // ─── Build HTML sidebar item ───────────────────────────────
    const buildItem = (n, isNew) => {
        const tag     = n.tag ? `<span class="np-item__tag" data-tag="${esc(n.tag.toLowerCase())}">${esc(n.tag)}</span>` : '';
        const newBadge = isNew ? `<span class="np-badge-new">${esc(ui.badge)}</span>` : '';
        const pinIcon  = n.pinned ? `<span class="np-item__version">${ui.pinned}</span>` : '';
        const version  = n.versione ? `<span class="np-item__version">${esc(n.versione)}</span>` : '';

        return `
            <button type="button" class="np-item" data-id="${n.id}" aria-label="${esc(n.titolo)}">
                <div class="np-item__top">
                    ${pinIcon}
                    ${version}
                    ${tag}
                    ${newBadge}
                </div>
                <span class="np-item__title">${esc(n.titolo)}</span>
                <span class="np-item__date">${esc(formatDate(n.data))}</span>
            </button>
        `;
    };

    // ─── Build HTML contenuto news ────────────────────────────
    const buildContent = (n) => {
        const version = n.versione ? `<span class="np-content__version">${esc(n.versione)}</span>` : '';
        const tag     = n.tag     ? `<span class="np-content__tag">${esc(n.tag)}</span>` : '';
        const date    = n.data    ? `<span class="np-content__date">${esc(formatDate(n.data))}</span>` : '';
        const img     = n.immagine
            ? `<img class="np-content__image" src="${esc(n.immagine)}" alt="${esc(n.titolo)}" loading="lazy">`
            : '';

        /*
         * Il contenuto viene dal DB e può contenere HTML sanificato lato server.
         * Qui lo inseriamo direttamente. NON inserire innerHTML di contenuto
         * non sanificato dal backend.
         */
        return `
            <div class="np-content__meta">
                ${version}
                ${tag}
                ${date}
            </div>
            <h2 class="np-content__title">${esc(n.titolo)}</h2>
            ${img}
            <div class="np-content__body" id="npBodyText"></div>
        `;
    };

    // ─── Render sidebar + contenuto ───────────────────────────
    const renderSidebar = (news, latestId) => {
        const seenId  = lsGet();
        const listEl  = overlayEl.querySelector('.np-sidebar__list');
        listEl.innerHTML = news.map(n =>
            buildItem(n, n.id > seenId)
        ).join('');

        listEl.querySelectorAll('.np-item').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                selectNews(id);
            });
        });
    };

    const selectNews = (id) => {
        activeId = id;
        const news   = newsCache?.news ?? [];
        const n      = news.find(x => x.id === id);
        if (!n) return;

        // Evidenzia sidebar item
        overlayEl.querySelectorAll('.np-item').forEach(btn => {
            btn.classList.toggle('np-is-active', parseInt(btn.dataset.id, 10) === id);
        });

        // Aggiorna contenuto
        const contentEl = overlayEl.querySelector('.np-content__scroll');
        contentEl.innerHTML = buildContent(n);

        // Inserisci contenuto HTML in modo sicuro (il contenuto è già sanificato lato server)
        const bodyEl = contentEl.querySelector('#npBodyText');
        if (bodyEl) bodyEl.innerHTML = n.contenuto || '';

        contentEl.scrollTop = 0;
    };

    // ─── Costruzione DOM popup (una sola volta) ───────────────
    const buildOverlay = () => {
        if (overlayEl) return;

        overlayEl = document.createElement('div');
        overlayEl.className = 'np-overlay';
        overlayEl.setAttribute('role', 'dialog');
        overlayEl.setAttribute('aria-modal', 'true');
        overlayEl.setAttribute('aria-label', ui.label);
        overlayEl.innerHTML = `
            <div class="np-popup">
                <button type="button" class="np-close" aria-label="${esc(ui.close)}">
                    <i class="fas fa-xmark"></i>
                </button>

                <aside class="np-sidebar" aria-label="Lista news">
                    <div class="np-sidebar__head">
                        <span class="np-sidebar__label">
                            <i class="fas fa-newspaper"></i>
                            ${esc(ui.label)}
                        </span>
                    </div>
                    <div class="np-sidebar__list"></div>
                </aside>

                <section class="np-content">
                    <div class="np-content__scroll">
                        <div class="np-empty">
                            <i class="fas fa-newspaper"></i>
                            <strong>${esc(ui.empty_title)}</strong>
                            <span>${esc(ui.empty_sub)}</span>
                        </div>
                    </div>
                </section>
            </div>
        `;

        // Chiusura: bottone X
        overlayEl.querySelector('.np-close').addEventListener('click', closePopup);

        // Chiusura: click fuori dal popup
        overlayEl.addEventListener('click', (e) => {
            if (e.target === overlayEl) closePopup();
        });

        // Chiusura: ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlayEl.classList.contains('np-is-open')) {
                closePopup();
            }
        });

        document.body.appendChild(overlayEl);
    };

    // ─── Open / Close ─────────────────────────────────────────
    const openPopup = async () => {
        buildOverlay();
        overlayEl.classList.add('np-is-open');
        document.body.style.overflow = 'hidden';

        // focus trap base
        overlayEl.querySelector('.np-close')?.focus();

        // Fetch (usa cache se disponibile)
        try {
            const data  = await fetchNews();
            const news  = data.news || [];

            if (!news.length) return; // rimane lo stato vuoto

            renderSidebar(news, data.latest_id);

            // Seleziona la news più recente se non già selezionata
            const toSelect = activeId && news.find(n => n.id === activeId)
                ? activeId
                : news[0].id;

            selectNews(toSelect);

        } catch (err) {
            console.warn('[news-popup] fetch error:', err);
        }
    };

    const closePopup = () => {
        if (!overlayEl) return;
        overlayEl.classList.remove('np-is-open');
        document.body.style.overflow = '';

        // Salva in localStorage: l'utente ha visto fino a questo id
        if (newsCache?.latest_id) {
            lsSet(newsCache.latest_id);

            // Rimuovi i badge NEW dalla sidebar ora che l'utente ha aperto il popup
            overlayEl.querySelectorAll('.np-badge-new').forEach(b => b.remove());
        }
    };

    // ─── Apertura automatica ──────────────────────────────────
    const maybeAutoOpen = async () => {
        try {
            const data    = await fetchNews();
            const latest  = data.latest_id || 0;
            const seen    = lsGet();

            if (latest > seen) {
                setTimeout(openPopup, OPEN_DELAY);
            }
        } catch {
            // silenzioso: non bloccare la homepage
        }
    };

    // ─── API pubblica ─────────────────────────────────────────
    // window.newsPopup.open() → richiamato dal tasto "News" in home.php
    window.newsPopup = { open: openPopup, close: closePopup };

    // ─── Init ─────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        maybeAutoOpen();
    });

})();
