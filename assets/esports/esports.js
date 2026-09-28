/*
 * Pagine del team esports: navigazione tra team e player, musica di
 * sottofondo, copia del codice mirino.
 *
 * Team (/it/ohpy) e player (/it/ohpy/{player}) sono pagine vere, fatte dal
 * PHP: si aprono anche da un link diretto. Qui i link con data-es-link si
 * caricano senza ricaricare tutto il sito: si scarica la pagina e se ne
 * sostituisce il <main>.
 *
 * Il motivo e' la musica, che deve partire da sola aprendo un player. I
 * browser fanno partire l'audio solo dentro un gesto dell'utente: il link
 * porta con se' il brano (data-es-music) e play() viene chiamato nello
 * stesso clic, prima ancora di scaricare la pagina. Con un ricaricamento
 * vero, Safari e Firefox la bloccherebbero. Con un link aperto da fuori
 * (Discord, WhatsApp) il browser puo' bloccarla lo stesso: allora parte al
 * primo tocco o tasto sulla pagina.
 *
 * Un solo elemento audio per tutte le pagine: una volta sbloccato da un
 * gesto resta sbloccato, e la musica continua senza buchi da un player
 * all'altro.
 */
(() => {
    'use strict';

    const body = document.body;
    const root = document.documentElement;
    const base = body.dataset.esBase || '';

    const store = {
        get(key) {
            try { return localStorage.getItem(key); } catch (_) { return null; }
        },
        set(key, value) {
            try { localStorage.setItem(key, value); } catch (_) { /* navigazione privata */ }
        },
    };

    const parseTrack = (json) => {
        if (!json) return null;
        try {
            const data = JSON.parse(json);
            if (!data || !data.src) return null;
            const volume = Number(data.volume);
            return {
                src: String(data.src),
                title: String(data.title || ''),
                artist: String(data.artist || ''),
                cover: String(data.cover || ''),
                nickname: String(data.nickname || ''),
                start: Math.max(0, Number(data.start) || 0),
                volume: Number.isFinite(volume) ? volume : 60,
            };
        } catch (_) {
            return null;
        }
    };

    /* ── Avviso a comparsa ──────────────────────────────────────────── */

    const toast = document.querySelector('[data-es-toast]');
    let toastTimer = null;

    const showToast = (text) => {
        if (!toast || !text) return;
        toast.textContent = text;
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2200);
    };

    /* ── Musica ─────────────────────────────────────────────────────── */

    const VOLUME_KEY = 'cripsum.ohpy.volume';

    // Fino al 28/09 la pausa si ricordava per tutti i player e poteva
    // restare accesa per sbaglio: chi ce l'ha salvata non deve restare muto.
    try { localStorage.removeItem('cripsum.ohpy.music-off'); } catch (_) { /* niente storage */ }

    const music = (() => {
        const audio = new Audio();
        // Il file si scarica appena il player si apre: se il browser blocca
        // l'avvio, al primo tocco la musica parte senza attese.
        audio.preload = 'auto';
        audio.loop = true;

        let box = null;          // riquadro .es-music della pagina aperta
        let track = null;        // brano del player aperto
        let loadedSrc = '';      // il src messo davvero nell'audio
        let pendingStart = null; // secondo da cui partire, finche' non ci si arriva
        let fadeTimer = null;
        let blocked = false;     // il browser ha rifiutato l'avvio automatico
        let pausedByUser = false; // pausa premuta su questo player (solo su questo)
        let resumeOnVisible = false;
        let playToken = 0;       // cambia a ogni avvio o arresto
        const broken = new Set();

        // Bottone ben visibile quando il browser blocca l'avvio: un tocco
        // qualsiasi fa partire la musica, questo dice dove toccare.
        const tap = document.createElement('button');
        tap.type = 'button';
        tap.className = 'es-tap';
        tap.innerHTML = '<i class="fa-solid fa-volume-high" aria-hidden="true"></i> <span></span>';
        tap.querySelector('span').textContent = body.dataset.esTap || '';
        tap.hidden = true;
        body.appendChild(tap);

        // Il volume scelto con il cursore vale per tutti i player; finche'
        // non lo si tocca, ogni player usa quello deciso nel pannello.
        const userVolume = () => {
            const raw = store.get(VOLUME_KEY);
            const value = Number(raw);
            return raw !== null && raw !== '' && Number.isFinite(value) ? Math.min(100, Math.max(0, value)) : null;
        };
        const targetVolume = () => (userVolume() ?? track?.volume ?? 60) / 100;

        const fadeTo = (to, ms, done) => {
            clearInterval(fadeTimer);
            const from = audio.volume;
            const steps = Math.max(1, Math.round(ms / 30));
            let step = 0;
            if (ms <= 0) {
                audio.volume = to;
                done?.();
                return;
            }
            fadeTimer = setInterval(() => {
                step += 1;
                audio.volume = Math.min(1, Math.max(0, from + (to - from) * (step / steps)));
                if (step >= steps) {
                    clearInterval(fadeTimer);
                    done?.();
                }
            }, 30);
        };

        const paint = () => {
            const waiting = Boolean(blocked && box && track);
            tap.hidden = !waiting;
            tap.classList.toggle('is-visible', waiting);

            if (!box) return;
            const playing = !audio.paused && !blocked && loadedSrc === track?.src;
            box.classList.toggle('is-playing', playing);

            const toggle = box.querySelector('[data-es-music-toggle]');
            if (toggle) {
                toggle.setAttribute('aria-label', playing ? toggle.dataset.labelPause : toggle.dataset.labelPlay);
                toggle.title = toggle.getAttribute('aria-label');
                const icon = toggle.querySelector('i');
                if (icon) icon.className = playing ? 'fa-solid fa-pause' : 'fa-solid fa-play';
            }

            const hint = box.querySelector('[data-es-music-hint]');
            if (hint) hint.hidden = !blocked;

            const broke = track && broken.has(track.src);
            box.classList.toggle('is-broken', Boolean(broke));
            const error = box.querySelector('[data-es-music-error]');
            if (error) error.hidden = !broke;

            const volume = Math.round(targetVolume() * 100);
            const range = box.querySelector('[data-es-volume]');
            if (range && document.activeElement !== range) range.value = String(volume);
            const volumeIcon = box.querySelector('[data-es-volume-icon]');
            if (volumeIcon) {
                volumeIcon.className = volume === 0 ? 'fa-solid fa-volume-xmark' : volume < 45 ? 'fa-solid fa-volume-low' : 'fa-solid fa-volume-high';
            }
        };

        /* Il browser ha bloccato l'avvio: si riprova al primo gesto vero.
           Tocchi, clic e tasti sono tutti in ascolto perche' per i browser
           solo alcuni contano come gesto (per il touch conta il rilascio). */
        const GESTURES = ['pointerdown', 'pointerup', 'touchend', 'click', 'keydown'];

        const onGesture = (event) => {
            if (event.type === 'keydown' && (event.key === 'Escape' || event.key === 'Tab')) return;
            if (event.target instanceof Element) {
                // Un link del team fa partire da se' il brano della pagina di
                // arrivo, e il tasto play/pausa fa da se': se partisse anche
                // qui, il clic che segue lo rimetterebbe subito in pausa.
                if (event.target.closest('a[data-es-link], [data-es-music-toggle]')) return;
            }
            disarm();
            if (blocked && track && !pausedByUser) play();
        };

        const arm = () => GESTURES.forEach((type) => document.addEventListener(type, onGesture, true));
        const disarm = () => GESTURES.forEach((type) => document.removeEventListener(type, onGesture, true));

        const play = () => {
            if (!track || broken.has(track.src)) return;
            // Una dissolvenza in uscita ancora in corso finirebbe con
            // pause() sul brano appena ripartito.
            clearInterval(fadeTimer);
            if (loadedSrc !== track.src) {
                audio.src = track.src;
                loadedSrc = track.src;
                pendingStart = track.start;
                // Prima che il file sia caricato questo e' il punto di
                // partenza; loadedmetadata lo ricontrolla dopo.
                try { audio.currentTime = track.start; } catch (_) { /* si rimette sotto */ }
            }
            if (audio.paused) audio.volume = 0;
            blocked = false;

            // Niente await prima di questa riga: deve restare dentro il gesto.
            const token = ++playToken;
            const attempt = audio.play();
            paint();

            if (attempt && typeof attempt.then === 'function') {
                attempt.then(() => {
                    // Il brano puo' finire di caricarsi quando si e' gia'
                    // andati altrove: quell'avvio non conta piu'.
                    if (token !== playToken) return;
                    blocked = false;
                    disarm();
                    fadeTo(targetVolume(), 700);
                    paint();
                }).catch((error) => {
                    if (token !== playToken) return;
                    if (error && error.name === 'NotAllowedError') {
                        blocked = true;
                        arm();
                    }
                    paint();
                });
            } else {
                fadeTo(targetVolume(), 700);
            }
        };

        const fadeOutAndPause = (ms = 350) => {
            playToken += 1;
            if (audio.paused) return;
            fadeTo(0, ms, () => audio.pause());
        };

        const updateMediaSession = () => {
            if (!('mediaSession' in navigator) || !track) return;
            try {
                navigator.mediaSession.metadata = new MediaMetadata({
                    title: track.title,
                    artist: track.artist || track.nickname,
                    album: track.nickname,
                    artwork: track.cover ? [{ src: new URL(track.cover, location.href).href, sizes: '512x512' }] : [],
                });
            } catch (_) { /* browser senza MediaMetadata */ }
        };

        audio.addEventListener('loadedmetadata', () => {
            if (pendingStart === null) return;
            const start = pendingStart;
            pendingStart = null;
            if (start > 0 && Number.isFinite(audio.duration) && start < audio.duration && Math.abs(audio.currentTime - start) > 1) {
                try { audio.currentTime = start; } catch (_) { /* resta dall'inizio */ }
            }
        });

        ['play', 'playing', 'pause'].forEach((type) => audio.addEventListener(type, paint));

        audio.addEventListener('error', () => {
            if (!loadedSrc) return;
            broken.add(loadedSrc);
            blocked = false;
            disarm();
            paint();
        });

        // In un'altra scheda del browser la musica si ferma, e riparte al
        // ritorno se stava suonando.
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (!audio.paused) {
                    resumeOnVisible = true;
                    audio.pause();
                }
            } else if (resumeOnVisible) {
                resumeOnVisible = false;
                if (track && !pausedByUser) audio.play().catch(() => {});
            }
        });

        const api = {
            /**
             * Il brano della pagina di arrivo, nello stesso clic che ci porta:
             * parte subito (o la musica sfuma, se il player non ne ha).
             */
            prepare(next) {
                // Ogni player che si apre riparte: la pausa premuta su un
                // altro player valeva solo per quello.
                pausedByUser = false;

                if (!next) {
                    track = null;
                    blocked = false;
                    disarm();
                    fadeOutAndPause(300);
                    paint();
                    return;
                }

                const same = track && track.src === next.src;
                track = next;
                updateMediaSession();

                // Un volume lasciato a zero su un player non deve rendere
                // muti anche i successivi: si torna a quello del pannello.
                if (userVolume() === 0) store.set(VOLUME_KEY, '');

                if (same && !audio.paused) {
                    paint();
                    return;
                }

                if (loadedSrc !== track.src) {
                    clearInterval(fadeTimer);
                    audio.pause();
                }
                play();
            },

            /**
             * Pagina appena mostrata: si collegano i controlli del suo
             * riquadro. Se si e' arrivati senza un clic (link diretto, tasto
             * Indietro) il brano parte da qui.
             */
            attach(scope) {
                if (box) box.classList.remove('is-playing');
                box = scope.querySelector('[data-es-music-box]');
                const pageTrack = box ? parseTrack(box.dataset.esMusic) : null;

                if (!pageTrack) {
                    if (track) api.prepare(null);
                    return;
                }
                if (!track || track.src !== pageTrack.src) {
                    api.prepare(pageTrack);
                }
                paint();
            },

            toggle() {
                if (!box || !track) return;
                if (!audio.paused && !blocked && loadedSrc === track.src) {
                    pausedByUser = true;
                    blocked = false;
                    disarm();
                    fadeOutAndPause(250);
                    paint();
                    return;
                }
                pausedByUser = false;
                play();
            },

            setVolume(value) {
                const volume = Math.min(100, Math.max(0, Number(value) || 0));
                store.set(VOLUME_KEY, String(volume));
                clearInterval(fadeTimer);
                audio.volume = volume / 100;
                paint();
            },

            isPlaying: () => !audio.paused,
        };

        return api;
    })();

    /* ── Navigazione tra le pagine del team ─────────────────────────── */

    const cache = new Map();
    const scrollByPath = new Map();
    let currentPath = location.pathname;
    let navToken = 0;

    const isOurs = (url) => Boolean(base)
        && url.origin === location.origin
        && (url.pathname === base || url.pathname.startsWith(base + '/'));

    const load = (href) => {
        if (!cache.has(href)) {
            const request = fetch(href, { credentials: 'same-origin' }).then((response) => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.text();
            });
            request.catch(() => cache.delete(href));
            cache.set(href, request);
            // Poche pagine, ma non all'infinito.
            if (cache.size > 24) cache.delete(cache.keys().next().value);
        }
        return cache.get(href);
    };

    const prefetch = (href) => {
        load(href).catch(() => { /* ci si riprova al clic */ });
    };

    const updateLangSwitch = (path) => {
        const lang = path.split('/')[1];
        const alt = lang === 'en' ? 'it' : 'en';
        document.querySelectorAll('a.cnav-lang').forEach((link) => {
            link.setAttribute('href', '/' + alt + path.slice(3));
        });
    };

    const scrollTo = (top) => {
        // Niente scorrimento morbido (style-dark.css lo mette su html): la
        // pagina nuova deve comparire gia' in cima. 'instant' vince sul CSS.
        try {
            window.scrollTo({ top, left: 0, behavior: 'instant' });
        } catch (_) {
            window.scrollTo(0, top);
        }
    };

    /* Le pagine vicine (precedente e successivo) si scaricano in anticipo:
       le frecce diventano istantanee. */
    const warmNeighbours = (scope) => {
        const run = () => scope.querySelectorAll('a[data-es-step]').forEach((link) => prefetch(new URL(link.href).pathname));
        if ('requestIdleCallback' in window) window.requestIdleCallback(run, { timeout: 2000 });
        else setTimeout(run, 600);
    };

    const initPage = (scope) => {
        music.attach(scope);
        warmNeighbours(scope);
    };

    const swap = (html, path) => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const next = doc.querySelector('main.es-shell');
        const current = document.querySelector('main.es-shell');
        if (!next || !current || !doc.body.classList.contains('es-page')) return false;

        current.replaceWith(document.importNode(next, true));

        document.title = doc.title;
        body.setAttribute('style', doc.body.getAttribute('style') || '');
        // richpresence.js rilegge questi meta a ogni cambio di indirizzo.
        ['cripsum:presence-title', 'cripsum:presence-state'].forEach((name) => {
            const fresh = doc.querySelector(`meta[name="${name}"]`);
            const live = document.querySelector(`meta[name="${name}"]`);
            if (fresh && live) live.content = fresh.content;
        });

        currentPath = path;
        updateLangSwitch(path);
        return true;
    };

    const navigate = async (href, { push = true } = {}) => {
        const url = new URL(href, location.href);
        const path = url.pathname + url.search;
        const token = ++navToken;

        // La posizione si ricorda solo andando avanti: tornando indietro con
        // il browser si ritrova la griglia dov'era quando si e' aperto il player.
        if (push) scrollByPath.set(currentPath, window.scrollY);
        window.history.scrollRestoration = 'manual';

        // Barra in alto solo se la pagina non era gia' pronta.
        const slow = setTimeout(() => root.classList.add('es-loading'), 120);
        let html;
        try {
            html = await load(path);
        } catch (_) {
            window.location.assign(path);
            return;
        } finally {
            clearTimeout(slow);
            root.classList.remove('es-loading');
        }
        if (token !== navToken) return; // nel frattempo si e' cliccato altro

        if (!swap(html, url.pathname)) {
            window.location.assign(path);
            return;
        }

        if (push) window.history.pushState({ esNav: true }, '', path);
        scrollTo(push ? 0 : (scrollByPath.get(url.pathname) || 0));

        const scope = document.querySelector('main.es-shell');
        initPage(scope);

        // Il fuoco va sul titolo della pagina nuova: chi usa uno screen
        // reader sente dove e' arrivato, come con un caricamento normale.
        scope.querySelector('h1[tabindex]')?.focus({ preventScroll: true });
    };

    /** Il gesto che porta a un'altra pagina del team: musica, poi pagina. */
    const follow = (link) => {
        const url = new URL(link.href, location.href);
        if (url.pathname === currentPath) {
            scrollTo(0);
            return;
        }
        music.prepare(parseTrack(link.dataset.esMusic));
        navigate(url.pathname + url.search);
    };

    window.addEventListener('popstate', () => {
        const url = new URL(location.href);
        if (!isOurs(url) || url.pathname === currentPath) return;
        navigate(url.pathname + url.search, { push: false });
    });

    // Media Session: i tasti avanti/indietro della tastiera o del telefono
    // passano al player successivo o precedente.
    if ('mediaSession' in navigator) {
        const stepLink = (dir) => document.querySelector(`main.es-shell a[data-es-step="${dir}"]`);
        const handlers = {
            play: () => { if (!music.isPlaying()) music.toggle(); },
            pause: () => { if (music.isPlaying()) music.toggle(); },
            nexttrack: () => { const link = stepLink('next'); if (link) follow(link); },
            previoustrack: () => { const link = stepLink('prev'); if (link) follow(link); },
        };
        Object.entries(handlers).forEach(([action, handler]) => {
            try { navigator.mediaSession.setActionHandler(action, handler); } catch (_) { /* non supportato */ }
        });
    }

    /* ── Eventi ─────────────────────────────────────────────────────── */

    const plainClick = (event) => event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        const link = target.closest('a[data-es-link]');
        if (link && plainClick(event) && !link.target && isOurs(new URL(link.href, location.href))) {
            event.preventDefault();
            follow(link);
            return;
        }

        if (target.closest('[data-es-music-toggle]')) {
            music.toggle();
            return;
        }

        const copyButton = target.closest('[data-es-copy]');
        if (copyButton) copyText(copyButton.dataset.esCopy);
    });

    // Passando sopra (o toccando) un link del team la sua pagina si
    // scarica gia': al clic e' pronta.
    const warm = (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[data-es-link]') : null;
        if (link) prefetch(new URL(link.href, location.href).pathname);
    };
    document.addEventListener('pointerover', warm, { passive: true });
    document.addEventListener('touchstart', warm, { passive: true });
    document.addEventListener('focusin', warm);

    document.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.matches('[data-es-volume]')) {
            music.setVolume(event.target.value);
        }
    });

    // Frecce della tastiera sulla pagina di un player: precedente/successivo.
    document.addEventListener('keydown', (event) => {
        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        if (event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable]')) return;
        const link = document.querySelector(`main.es-shell a[data-es-step="${event.key === 'ArrowRight' ? 'next' : 'prev'}"]`);
        if (!link) return;
        event.preventDefault();
        follow(link);
    });

    // Swipe orizzontale sulla foto, sul telefono, per cambiare player.
    let swipe = null;
    document.addEventListener('touchstart', (event) => {
        const area = event.target instanceof Element ? event.target.closest('[data-es-swipe]') : null;
        swipe = area && event.touches.length === 1 ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
    }, { passive: true });
    document.addEventListener('touchend', (event) => {
        if (!swipe) return;
        const touch = event.changedTouches[0];
        const dx = touch.clientX - swipe.x;
        const dy = touch.clientY - swipe.y;
        swipe = null;
        if (Math.abs(dx) < 70 || Math.abs(dy) > 60) return;
        const link = document.querySelector(`main.es-shell a[data-es-step="${dx < 0 ? 'next' : 'prev'}"]`);
        if (link) follow(link);
    }, { passive: true });

    /* ── Copia del codice mirino ────────────────────────────────────── */

    const copyText = async (text) => {
        if (!text) return;
        try {
            // Se il browser lascia la richiesta in sospeso (permesso mai
            // concesso) si passa al vecchio metodo invece di restare muti.
            await Promise.race([
                navigator.clipboard.writeText(text),
                new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 1200)),
            ]);
            showToast(body.dataset.esCopied);
        } catch (_) {
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
            body.appendChild(area);
            area.select();
            let ok = false;
            try { ok = document.execCommand('copy'); } catch (__) { ok = false; }
            area.remove();
            showToast(ok ? body.dataset.esCopied : body.dataset.esCopyFailed);
        }
    };

    /* ── Avvio ──────────────────────────────────────────────────────── */

    const progress = document.createElement('div');
    progress.className = 'es-progress';
    progress.setAttribute('aria-hidden', 'true');
    body.appendChild(progress);

    const firstScope = document.querySelector('main.es-shell');
    if (firstScope) initPage(firstScope);
})();
