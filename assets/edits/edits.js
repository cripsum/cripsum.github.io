/*
 * Edits: filtri, "visti", e il player grande con il suo indirizzo
 * (/it/edits/28), frecce, tastiera e swipe.
 *
 * PreMiD (il presence di Cripsum.com) ricorda l'ultima .edit-card cliccata
 * e ne legge titolo, musica e .rpcimg. Quando un edit si apre senza un
 * click sulla sua card (frecce, tastiera, swipe, testata, link diretto) si
 * simula quel click, cosi' la presence su Discord segue l'edit giusto.
 */
(() => {
    'use strict';

    const COOKIE_WATCHED = 'watchedVideos';
    const STORE = { filter: 'cripsum.edits.filter', sort: 'cripsum.edits.sort', hideSeen: 'cripsum.edits.hideSeen' };
    const ACHIEVEMENT_VISIT = 6;
    const ACHIEVEMENT_ALL_WATCHED = 17;

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const unlock = (id) => {
        try {
            window.unlockAchievement?.(id);
        } catch (error) {
            console.warn('[Edits] achievement non disponibile:', error);
        }
    };

    const store = {
        get(key, fallback) {
            try {
                const value = localStorage.getItem(key);
                return value === null ? fallback : value;
            } catch (_) {
                return fallback;
            }
        },
        set(key, value) {
            try {
                localStorage.setItem(key, value);
            } catch (_) {
                // Navigazione privata o memoria piena: pazienza.
            }
        },
    };

    /* ── Edit visti (cookie di sempre: l'achievement 17 si basa su questi id) ── */

    const readWatched = () => {
        try {
            const raw = document.cookie.split('; ').find((c) => c.startsWith(`${COOKIE_WATCHED}=`));
            if (!raw) return [];
            const value = raw.slice(COOKIE_WATCHED.length + 1);
            let parsed;
            try {
                parsed = JSON.parse(decodeURIComponent(value));
            } catch (_) {
                parsed = JSON.parse(value);
            }
            return Array.isArray(parsed) ? parsed.map((n) => Number.parseInt(n, 10)).filter(Number.isFinite) : [];
        } catch (_) {
            return [];
        }
    };

    const writeWatched = (ids) => {
        const unique = Array.from(new Set(ids));
        document.cookie = `${COOKIE_WATCHED}=${encodeURIComponent(JSON.stringify(unique))}; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;
    };

    const normalize = (value) => String(value || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

    const init = () => {
        unlock(ACHIEVEMENT_VISIT);

        const dataEl = $('#editsData');
        const grid = $('#editsGrid');
        if (!dataEl || !grid) return;

        let cfg;
        try {
            cfg = JSON.parse(dataEl.textContent);
        } catch (error) {
            console.error('[Edits] dati non leggibili:', error);
            return;
        }

        const S = cfg.strings || {};
        const edits = new Map((cfg.edits || []).map((edit) => [Number(edit.id), edit]));
        const cards = $$('.edit-card', grid);
        const cardById = new Map(cards.map((card) => [Number(card.dataset.editId), card]));
        const search = $('#editsSearch');
        const sort = $('#editsSort');
        const hideSeen = $('#editsHideSeen');
        const chips = $$('.edits-chip');
        const empty = $('#editsEmpty');
        const dialog = $('#editTheater');
        const toastEl = $('[data-edits-toast]');
        const pageTitle = document.title;

        const state = {
            filter: store.get(STORE.filter, 'all'),
            sort: store.get(STORE.sort, 'recent'),
            hideSeen: store.get(STORE.hideSeen, '0') === '1',
            query: '',
            current: null,
            pushed: false,
            tracked: new Set(),
            poking: false,
        };

        if (!chips.some((chip) => chip.dataset.filter === state.filter)) state.filter = 'all';
        if (!['recent', 'popular', 'name'].includes(state.sort)) state.sort = 'recent';
        if (sort) sort.value = state.sort;
        if (hideSeen) hideSeen.checked = state.hideSeen;

        /* ── Avvisi ─────────────────────────────────────────────────── */

        let toastTimer = null;
        const toast = (text, isError = false) => {
            if (!toastEl) return;
            // Col player aperto il <dialog> sta sopra a tutto: il toast va dentro.
            (dialog?.open ? dialog : document.body).appendChild(toastEl);
            toastEl.textContent = text;
            toastEl.classList.toggle('is-error', isError);
            toastEl.classList.add('is-visible');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => toastEl.classList.remove('is-visible'), 2600);
        };

        /* ── Visti ──────────────────────────────────────────────────── */

        const paintWatched = () => {
            const watched = new Set(readWatched());
            cards.forEach((card) => card.classList.toggle('is-watched', watched.has(Number(card.dataset.editId))));
        };

        const markWatched = (id) => {
            const watched = readWatched();
            if (!watched.includes(id)) {
                watched.push(id);
                writeWatched(watched);
            }
            cardById.get(id)?.classList.add('is-watched');

            const all = new Set(watched);
            if (cards.length && cards.every((card) => all.has(Number(card.dataset.editId)))) {
                unlock(ACHIEVEMENT_ALL_WATCHED);
            }
        };

        // Una visualizzazione per edit e per visita: conta anche per la missione "guarda un edit".
        const trackView = (id) => {
            if (state.tracked.has(id)) return;
            state.tracked.add(id);
            fetch('/api/missions/track_edit_view.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ edit_id: id }),
            }).catch(() => {});
        };

        /* ── Filtri e ordine ────────────────────────────────────────── */

        const compare = {
            recent: (a, b) => Number(a.dataset.order) - Number(b.dataset.order),
            popular: (a, b) => Number(a.dataset.rank) - Number(b.dataset.rank),
            name: (a, b) => String(a.dataset.title || '').localeCompare(String(b.dataset.title || ''), document.documentElement.lang || 'it', { sensitivity: 'base' }),
        };

        const applyFilters = () => {
            const query = normalize(state.query);
            let visible = 0;

            cards.slice().sort(compare[state.sort] || compare.recent).forEach((card) => grid.appendChild(card));

            cards.forEach((card) => {
                const show = (state.filter === 'all' || card.dataset.category === state.filter)
                    && (!query || normalize(card.dataset.search).includes(query))
                    && !(state.hideSeen && card.classList.contains('is-watched'));
                card.hidden = !show;
                if (show) visible += 1;
            });

            chips.forEach((chip) => {
                const active = chip.dataset.filter === state.filter;
                chip.classList.toggle('is-active', active);
                chip.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            if (empty) empty.hidden = visible !== 0;
        };

        const resetFilters = () => {
            state.filter = 'all';
            state.query = '';
            state.hideSeen = false;
            if (search) search.value = '';
            if (hideSeen) hideSeen.checked = false;
            store.set(STORE.filter, 'all');
            store.set(STORE.hideSeen, '0');
            applyFilters();
        };

        chips.forEach((chip) => chip.addEventListener('click', () => {
            state.filter = chip.dataset.filter || 'all';
            store.set(STORE.filter, state.filter);
            applyFilters();
        }));

        search?.addEventListener('input', () => {
            state.query = search.value;
            applyFilters();
        });

        sort?.addEventListener('change', () => {
            state.sort = sort.value;
            store.set(STORE.sort, state.sort);
            applyFilters();
        });

        hideSeen?.addEventListener('change', () => {
            state.hideSeen = hideSeen.checked;
            store.set(STORE.hideSeen, state.hideSeen ? '1' : '0');
            applyFilters();
        });

        $('[data-edits-reset]')?.addEventListener('click', resetFilters);

        // "/" porta alla ricerca, come nella navbar.
        document.addEventListener('keydown', (event) => {
            if (event.key !== '/' || dialog?.open || event.ctrlKey || event.metaKey || event.altKey) return;
            const target = event.target;
            if (target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))) return;
            if (!search) return;
            event.preventDefault();
            search.focus();
        });

        /* ── Player grande ──────────────────────────────────────────── */

        const player = dialog ? $('[data-player]', dialog) : null;
        const stage = dialog ? $('[data-theater-stage]', dialog) : null;

        const el = (tag, attrs = {}, text = '') => {
            const node = document.createElement(tag);
            Object.entries(attrs).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== false) node.setAttribute(key, value === true ? '' : String(value));
            });
            if (text) node.textContent = text;
            return node;
        };

        const clearPlayer = () => {
            if (!player) return;
            $$('video', player).forEach((video) => {
                try {
                    video.pause();
                    video.removeAttribute('src');
                    video.load();
                } catch (_) {
                    // niente
                }
            });
            player.replaceChildren();
        };

        const renderPlayer = (edit) => {
            clearPlayer();
            player.style.setProperty('--r', String(edit.ratio || 0.8));

            if (edit.missing) {
                const box = el('div', { class: 'edit-player__missing' });
                if (edit.cover) box.style.backgroundImage = `url("${edit.cover.replace(/"/g, '%22')}")`;
                box.append(el('i', { class: 'fa-solid fa-video-slash', 'aria-hidden': 'true' }), el('strong', {}, S.missing_title || ''), el('p', {}, S.missing_text || ''));
                if (edit.tiktok) {
                    const link = el('a', { class: 'edits-btn edits-btn--small', href: edit.tiktok, target: '_blank', rel: 'noopener noreferrer' });
                    link.append(el('i', { class: 'fa-brands fa-tiktok', 'aria-hidden': 'true' }), document.createTextNode(` ${S.tiktok || 'TikTok'}`));
                    box.append(link);
                }
                player.append(box);
                return;
            }

            if (edit.source === 'file' && edit.video) {
                const video = el('video', { src: edit.video, controls: true, playsinline: true, autoplay: true, preload: 'auto', poster: edit.cover || null });
                player.append(video);
                // Aperto con un click: il browser lascia partire l'audio. Da un
                // link diretto puo' rifiutare, e restano i controlli.
                video.play()?.catch(() => {});
                return;
            }

            if (edit.embed) {
                player.append(el('iframe', {
                    src: edit.embed,
                    title: edit.title,
                    allow: 'autoplay; fullscreen; picture-in-picture',
                    allowfullscreen: true,
                }));
            }
        };

        const renderSide = (edit) => {
            const tags = $('[data-theater-tags]', dialog);
            tags.replaceChildren();
            if (edit.category?.name) {
                const cat = el('span', { class: 'edit-cat' });
                cat.append(el('i', { class: edit.category.icon || 'fa-solid fa-film', 'aria-hidden': 'true' }), document.createTextNode(` ${edit.category.name}`));
                tags.append(cat);
            }
            if (edit.label || edit.is_new) {
                tags.append(el('span', { class: `edit-label${edit.is_new ? ' edit-label--new' : ''}` }, edit.is_new ? S.new : edit.label));
            }

            $('[data-theater-title]', dialog).textContent = edit.title;

            const music = $('[data-theater-music]', dialog);
            music.hidden = !edit.music;
            $('span', music).textContent = edit.music || '';

            const collab = $('[data-theater-collab]', dialog);
            const collabText = $('span', collab);
            collab.hidden = !edit.collab;
            collabText.replaceChildren();
            if (edit.collab) {
                const [before, after = ''] = String(S.collab_with || '%s').split('%s');
                collabText.append(document.createTextNode(before));
                collabText.append(edit.collab_link
                    ? el('a', { href: edit.collab_link, target: '_blank', rel: 'noopener noreferrer' }, edit.collab)
                    : document.createTextNode(edit.collab));
                collabText.append(document.createTextNode(after));
            }

            const tiktok = $('[data-theater-tiktok]', dialog);
            tiktok.hidden = !edit.tiktok;
            tiktok.href = edit.tiktok || '#';
        };

        // Il click sulla card lo vede anche PreMiD (ascolta sul document).
        const pokePresence = (id) => {
            const hit = cardById.get(id)?.querySelector('.edit-card__hit');
            if (!hit) return;
            state.poking = true;
            try {
                hit.click();
            } finally {
                state.poking = false;
            }
        };

        const openEdit = (id, { fromCard = false, history: mode = 'push' } = {}) => {
            const edit = edits.get(id);
            if (!edit || !dialog || !player) return;

            const wasOpen = dialog.open;
            state.current = id;
            renderSide(edit);
            renderPlayer(edit);

            if (!wasOpen) {
                if (typeof dialog.showModal === 'function') dialog.showModal();
                else dialog.setAttribute('open', '');
            }

            const url = edit.url;
            if (mode === 'push') {
                if (wasOpen) {
                    history.replaceState({ edit: id }, '', url);
                } else {
                    history.pushState({ edit: id }, '', url);
                    state.pushed = true;
                }
            } else if (mode === 'replace') {
                history.replaceState({ edit: id }, '', url);
            }

            document.title = `${edit.title} · ${pageTitle}`;
            markWatched(id);
            trackView(id);
            if (!fromCard) pokePresence(id);
        };

        const closeTheater = ({ fromHistory = false } = {}) => {
            if (!dialog) return;
            clearPlayer();
            if (dialog.open) {
                if (typeof dialog.close === 'function') dialog.close();
                else dialog.removeAttribute('open');
            }
            document.title = pageTitle;
            state.current = null;

            if (!fromHistory) {
                if (state.pushed) {
                    state.pushed = false;
                    history.back();
                } else {
                    history.replaceState(null, '', cfg.base);
                }
            } else {
                state.pushed = false;
            }

            // Con "Nascondi gia' visti" l'edit appena guardato sparisce ora, non mentre lo si guarda.
            applyFilters();
        };

        // Si scorre nell'ordine in cui la griglia li mostra adesso (filtri compresi).
        const step = (dir) => {
            if (state.current === null) return;
            const inGrid = Array.from(grid.children).filter((card) => card.classList.contains('edit-card'));
            let list = inGrid.filter((card) => !card.hidden).map((card) => Number(card.dataset.editId));
            if (!list.includes(state.current)) list = inGrid.map((card) => Number(card.dataset.editId));
            if (list.length < 2) return;
            const index = list.indexOf(state.current);
            const next = list[(index + dir + list.length) % list.length];
            openEdit(next, { history: 'replace' });
        };

        const copyLink = async () => {
            const edit = edits.get(state.current);
            if (!edit) return;
            const url = `${location.origin}${edit.url}`;
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(url);
                } else {
                    const input = el('input', { value: url, readonly: true });
                    dialog.appendChild(input);
                    input.select();
                    const ok = document.execCommand('copy');
                    input.remove();
                    if (!ok) throw new Error('copy');
                }
                toast(S.copied || 'OK');
            } catch (_) {
                toast(S.copy_failed || 'Error', true);
            }
        };

        document.addEventListener('click', (event) => {
            const opener = event.target.closest('[data-edit-open]');
            if (!opener) return;
            const fromCard = Boolean(opener.closest('.edit-card'));
            // Il click simulato per PreMiD non deve riaprire niente.
            if (fromCard && state.poking) return;
            event.preventDefault();
            openEdit(Number(opener.dataset.editOpen), { fromCard, history: dialog?.open ? 'replace' : 'push' });
        });

        if (dialog) {
            $$('[data-theater-step]', dialog).forEach((button) => button.addEventListener('click', () => step(Number(button.dataset.theaterStep))));
            $('[data-theater-close]', dialog)?.addEventListener('click', () => closeTheater());
            $('[data-theater-copy]', dialog)?.addEventListener('click', copyLink);

            // Esc: chiude passando dalla cronologia, come la X.
            dialog.addEventListener('cancel', (event) => {
                event.preventDefault();
                closeTheater();
            });

            // Un click sul fondo scuro chiude.
            stage?.addEventListener('click', (event) => {
                if (event.target === stage) closeTheater();
            });

            dialog.addEventListener('keydown', (event) => {
                if (event.target instanceof HTMLVideoElement || event.target instanceof HTMLInputElement) return;
                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    step(1);
                } else if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    step(-1);
                }
            });

            // Swipe orizzontale sul telefono. Dentro l'iframe di Streamable i
            // tocchi non arrivano: si scorre dalla parte delle informazioni.
            let touch = null;
            dialog.addEventListener('touchstart', (event) => {
                if (event.touches.length !== 1) return;
                touch = { x: event.touches[0].clientX, y: event.touches[0].clientY };
            }, { passive: true });
            dialog.addEventListener('touchend', (event) => {
                if (!touch) return;
                const dx = event.changedTouches[0].clientX - touch.x;
                const dy = event.changedTouches[0].clientY - touch.y;
                touch = null;
                if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) step(dx < 0 ? 1 : -1);
            }, { passive: true });
        }

        window.addEventListener('popstate', (event) => {
            const id = Number(event.state?.edit || 0);
            if (id && edits.has(id)) {
                openEdit(id, { history: 'none' });
                state.pushed = false;
            } else if (dialog?.open) {
                closeTheater({ fromHistory: true });
            }
        });

        paintWatched();
        applyFilters();

        // /it/edits/28: si apre da solo. La pagina stessa e' la voce della
        // cronologia, quindi chiudere torna a /it/edits senza uscire.
        if (cfg.open && edits.has(Number(cfg.open))) {
            openEdit(Number(cfg.open), { history: 'replace' });
            state.pushed = false;
        } else if (cfg.not_found) {
            history.replaceState(null, '', cfg.base);
            toast(S.not_found || '', true);
        }
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
