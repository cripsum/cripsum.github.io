(() => {
    'use strict';

    if (window.__cripsumEditsV2Loaded) return;
    window.__cripsumEditsV2Loaded = true;

    const STORAGE_FILTER = 'cripsum_edits_filter';
    const STORAGE_SORT = 'cripsum_edits_sort';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const state = {
        filter: localStorage.getItem(STORAGE_FILTER) || 'all',
        search: '',
        sort: localStorage.getItem(STORAGE_SORT) || 'recent',
        loadedIframes: new Set()
    };

    function safeUnlockAchievement(id) {
        if (typeof window.unlockAchievement !== 'function') return;

        try {
            window.unlockAchievement(id);
        } catch (err) {
            console.warn('[Edits] unlockAchievement non disponibile:', err);
        }
    }

    function readCookieJson(name, fallback = null) {
        try {
            const cookies = document.cookie ? document.cookie.split('; ') : [];

            for (const cookie of cookies) {
                const [key, ...rest] = cookie.split('=');

                if (key !== name) continue;

                const raw = rest.join('=');
                const decoded = decodeURIComponent(raw || '');

                try {
                    return JSON.parse(decoded);
                } catch {
                    return JSON.parse(raw);
                }
            }
        } catch (err) {
            console.warn('[Edits] cookie non leggibile:', err);
        }

        return fallback;
    }

    function writeCookieJson(name, value) {
        const encoded = encodeURIComponent(JSON.stringify(value));
        document.cookie = `${name}=${encoded}; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;
    }

    function getWatchedVideos() {
        const value = readCookieJson('watchedVideos', []);
        return Array.isArray(value)
            ? value.map((item) => Number.parseInt(item, 10)).filter(Number.isFinite)
            : [];
    }

    function setWatchedVideos(value) {
        const unique = Array.from(new Set(value.map((item) => Number.parseInt(item, 10)).filter(Number.isFinite)));
        writeCookieJson('watchedVideos', unique);
    }

    function getCards() {
        return $$('.edit-card');
    }

    function getIframe(card) {
        return card?.querySelector('.video-iframe') || null;
    }

    function getBaseSrc(iframe) {
        return iframe?.dataset.src || '';
    }

    function buildAutoplaySrc(src) {
        if (!src) return 'about:blank';

        const separator = src.includes('?') && !src.endsWith('?') ? '&' : '';
        return `${src}${separator}autoplay=1`;
    }

    function ensureIframeLoaded(card) {
        const iframe = getIframe(card);
        if (!iframe) return;

        const src = getBaseSrc(iframe);
        if (!src) return;

        if (!iframe.src || iframe.src === 'about:blank' || iframe.getAttribute('src') === 'about:blank') {
            iframe.src = src;
        }

        state.loadedIframes.add(String(card.dataset.editId || ''));
    }

    function stopOtherVideos(activeCard = null) {
        getCards().forEach((card) => {
            if (card === activeCard) return;

            card.classList.remove('playing');

            const iframe = getIframe(card);
            if (!iframe) return;

            const baseSrc = getBaseSrc(iframe);
            const currentSrc = iframe.getAttribute('src') || '';

            if (baseSrc && currentSrc.includes('autoplay=1')) {
                iframe.src = baseSrc;
            }
        });
    }

    function markWatched(card, id) {
        const watched = getWatchedVideos();

        if (!watched.includes(id)) {
            watched.push(id);
            setWatchedVideos(watched);
        }

        card.classList.add('is-watched');

        const totalIds = getCards().map((item) => Number.parseInt(item.dataset.editId, 10)).filter(Number.isFinite);
        const watchedSet = new Set(getWatchedVideos());

        if (totalIds.length > 0 && totalIds.every((videoId) => watchedSet.has(videoId))) {
            safeUnlockAchievement(17);
        }
    }

    function updateWatchedBadges() {
        const watchedSet = new Set(getWatchedVideos());

        getCards().forEach((card) => {
            const id = Number.parseInt(card.dataset.editId, 10);
            card.classList.toggle('is-watched', watchedSet.has(id));
        });
    }

    function applyFilters() {
        const cards = getCards();
        const query = state.search.trim().toLowerCase();
        let visibleCount = 0;

        cards.forEach((card) => {
            const categories = (card.dataset.category || '').split(/[\s,]+/).map((item) => item.trim());
            const title = (card.dataset.title || '').toLowerCase();
            const music = (card.dataset.music || '').toLowerCase();

            const matchFilter = state.filter === 'all' || categories.includes(state.filter);
            const matchSearch = !query || title.includes(query) || music.includes(query);
            const visible = matchFilter && matchSearch;

            card.hidden = !visible;

            if (visible) visibleCount += 1;
        });

        const count = $('#editResultCount');
        const empty = $('#editsEmpty');

        if (count) {
            count.textContent = `${visibleCount} edit ${visibleCount === 1 ? 'mostrato' : 'mostrati'}`;
        }

        if (empty) {
            empty.hidden = visibleCount !== 0;
        }

        $$('.filter-btn').forEach((button) => {
            button.classList.toggle('active', button.dataset.filter === state.filter);
        });
    }

    function applySort() {
        const grid = $('#editsGrid');
        if (!grid) return;

        const cards = getCards();

        const sorted = cards.sort((a, b) => {
            if (state.sort === 'name') {
                return (a.dataset.title || '').localeCompare(b.dataset.title || '', 'it', { sensitivity: 'base' });
            }

            if (state.sort === 'category') {
                return (a.dataset.category || '').localeCompare(b.dataset.category || '', 'it', { sensitivity: 'base' });
            }

            return Number(a.dataset.order || 0) - Number(b.dataset.order || 0);
        });

        sorted.forEach((card) => grid.appendChild(card));
    }

    function savePreferences() {
        localStorage.setItem(STORAGE_FILTER, state.filter);
        localStorage.setItem(STORAGE_SORT, state.sort);
    }

    function resetFilters() {
        state.filter = 'all';
        state.search = '';
        state.sort = 'recent';

        const search = $('#editSearch');
        const sort = $('#editSort');

        if (search) search.value = '';
        if (sort) sort.value = 'recent';

        savePreferences();
        applySort();
        applyFilters();
    }

    function initLazyIframes() {
        const cards = getCards();

        const loadCard = (card) => {
            ensureIframeLoaded(card);
            const iframe = getIframe(card);

            iframe?.addEventListener('load', () => {
                card.classList.add('is-video-loaded');
            }, { once: true });
        };

        if (!('IntersectionObserver' in window)) {
            cards.forEach(loadCard);
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;

                loadCard(entry.target);
                observer.unobserve(entry.target);
            });
        }, {
            rootMargin: '520px 0px'
        });

        cards.forEach((card) => observer.observe(card));
    }

    function initControls() {
        $$('.filter-btn').forEach((button) => {
            button.addEventListener('click', () => {
                state.filter = button.dataset.filter || 'all';
                savePreferences();
                applyFilters();
            });
        });

        const search = $('#editSearch');
        const sort = $('#editSort');
        const reset = $('#editReset');
        const backTop = $('#editsBackTop');

        if (search) {
            search.addEventListener('input', () => {
                state.search = search.value || '';
                applyFilters();
            });
        }

        if (sort) {
            sort.value = state.sort;
            sort.addEventListener('change', () => {
                state.sort = sort.value || 'recent';
                savePreferences();
                applySort();
                applyFilters();
            });
        }

        if (reset) {
            reset.addEventListener('click', resetFilters);
        }

        if (backTop) {
            window.addEventListener('scroll', () => {
                backTop.classList.toggle('is-visible', window.scrollY > 700);
            }, { passive: true });

            backTop.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        document.addEventListener('click', (event) => {
            if (event.target.closest('.edit-card')) return;

            stopOtherVideos(null);

            if (typeof window.clearCurrentEdit === 'function') {
                try {
                    window.clearCurrentEdit();
                } catch (err) {
                    console.warn('[Edits] clearCurrentEdit fallito:', err);
                }
            }
        });

        window.addEventListener('beforeunload', () => {
            if (typeof window.clearCurrentEdit === 'function') {
                try {
                    window.clearCurrentEdit();
                } catch {
                    // no-op
                }
            }
        });
    }

    window.playVideo = function playVideo(card, id) {
        if (!card) return;

        const videoId = Number.parseInt(id, 10);
        const iframe = getIframe(card);

        stopOtherVideos(card);
        card.classList.add('playing');

        if (iframe) {
            const baseSrc = getBaseSrc(iframe);

            iframe.src = 'about:blank';

            window.setTimeout(() => {
                iframe.src = buildAutoplaySrc(baseSrc);
            }, 60);
        }

        if (typeof window.setCurrentEdit === 'function') {
            try {
                window.setCurrentEdit(videoId);
            } catch (err) {
                console.warn('[Edits] setCurrentEdit fallito:', err);
            }
        }

        if (Number.isFinite(videoId)) {
            markWatched(card, videoId);
        }
    };

    function silenceStreamableNoise() {
        const originalError = console.error;
        const originalLog = console.log;

        console.error = function patchedConsoleError(...args) {
            if (args.some((item) => typeof item === 'string' && item.includes('socket.streamable.com'))) {
                return;
            }

            originalError.apply(console, args);
        };

        console.log = function patchedConsoleLog(...args) {
            if (args.some((item) => typeof item === 'string' && item.includes('Websocket error'))) {
                return;
            }

            originalLog.apply(console, args);
        };
    }

    document.addEventListener('DOMContentLoaded', () => {
        safeUnlockAchievement(6);
        silenceStreamableNoise();
        initControls();
        initLazyIframes();
        updateWatchedBadges();
        applySort();
        applyFilters();

        const savedFilterButton = document.querySelector(`.filter-btn[data-filter="${CSS.escape(state.filter)}"]`);
        if (!savedFilterButton) {
            state.filter = 'all';
        }
    });
})();
