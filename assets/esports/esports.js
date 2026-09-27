/*
 * Pagina del team esports: schede dei player e musica di sottofondo.
 *
 * Le schede sono <dialog> gia' nella pagina (le disegna il PHP): cliccando
 * una card si apre la sua con showModal() e l'indirizzo diventa
 * /it/ohpy/{player}, cosi' il link si condivide e il tasto Indietro chiude.
 *
 * La musica parte da sola all'apertura. I browser fanno partire l'audio
 * solo dentro un gesto dell'utente, e il clic sulla card lo e': per questo
 * play() viene chiamato subito, nello stesso giro del clic, senza attese in
 * mezzo. Con un link aperto da fuori (Discord, WhatsApp) il browser puo'
 * rifiutare: allora la musica parte al primo tocco o tasto sulla pagina.
 * Si usa un solo elemento audio per tutti i player: una volta sbloccato da
 * un gesto resta sbloccato anche cambiando brano con le frecce.
 */
(() => {
    'use strict';

    const body = document.body;
    const base = body.dataset.esBase || '';
    const teamTitle = body.dataset.esTitle || document.title;
    const teamPresence = body.dataset.esPresence || '';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const sheets = Array.from(document.querySelectorAll('[data-es-sheet]'));
    const bySlug = new Map(sheets.map((sheet) => [sheet.dataset.esSheet, sheet]));
    const order = sheets.map((sheet) => sheet.dataset.esSheet);

    const store = {
        get(key) {
            try { return localStorage.getItem(key); } catch (_) { return null; }
        },
        set(key, value) {
            try { localStorage.setItem(key, value); } catch (_) { /* navigazione privata */ }
        },
    };

    const isModal = (dialog) => {
        try { return dialog.matches(':modal'); } catch (_) { return true; }
    };

    /* ── Avviso a comparsa ──────────────────────────────────────────── */

    const toast = document.querySelector('[data-es-toast]');
    let toastTimer = null;

    const showToast = (text) => {
        if (!toast || !text) return;
        // Una scheda aperta sta nello strato in cima alla pagina: l'avviso
        // deve starci dentro, altrimenti resterebbe sotto.
        (current || body).appendChild(toast);
        toast.textContent = text;
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2200);
    };

    /* ── Musica ─────────────────────────────────────────────────────── */

    const OFF_KEY = 'cripsum.ohpy.music-off';
    const VOLUME_KEY = 'cripsum.ohpy.volume';

    const music = (() => {
        const audio = new Audio();
        audio.preload = 'none';
        audio.loop = true;

        let box = null;          // .es-music della scheda aperta
        let track = null;        // brano del player aperto
        let loadedSrc = '';      // il src messo davvero nell'audio
        let pendingStart = null; // secondo da cui partire, finche' non ci si arriva
        let fadeTimer = null;
        let blocked = false;     // il browser ha rifiutato l'avvio automatico
        let resumeOnVisible = false;

        const isOff = () => store.get(OFF_KEY) === '1';

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
            if (!box) return;
            const playing = !audio.paused && !blocked;
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
            // Chi sta chiudendo la scheda non vuole far partire la musica.
            if (event.target instanceof Element && event.target.closest('[data-es-close]')) return;
            disarm();
            if (blocked && box && track && !isOff()) play();
        };

        const arm = () => GESTURES.forEach((type) => document.addEventListener(type, onGesture, true));
        const disarm = () => GESTURES.forEach((type) => document.removeEventListener(type, onGesture, true));

        const play = () => {
            if (!track) return;
            // Una dissolvenza in uscita ancora in corso (si arriva da un
            // player senza musica) finirebbe con pause() sul brano appena
            // ripartito.
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
            const attempt = audio.play();
            paint();

            if (attempt && typeof attempt.then === 'function') {
                attempt.then(() => {
                    blocked = false;
                    disarm();
                    fadeTo(targetVolume(), 700);
                    paint();
                }).catch((error) => {
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
            if (audio.paused) return;
            fadeTo(0, ms, () => audio.pause());
        };

        const clearBox = () => {
            if (!box) return;
            box.classList.remove('is-playing');
            const hint = box.querySelector('[data-es-music-hint]');
            if (hint) hint.hidden = true;
        };

        const updateMediaSession = (nickname) => {
            if (!('mediaSession' in navigator) || !track) return;
            try {
                navigator.mediaSession.metadata = new MediaMetadata({
                    title: track.title,
                    artist: track.artist || nickname || '',
                    album: nickname || '',
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
            if (!box || !audio.getAttribute('src')) return;
            box.classList.add('is-broken');
            const error = box.querySelector('[data-es-music-error]');
            if (error) error.hidden = false;
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
                if (box && track && !isOff()) audio.play().catch(() => {});
            }
        });

        return {
            /** Scheda aperta (o cambiata): parte il suo brano. */
            load(sheet) {
                const next = sheet ? sheet.querySelector('[data-es-music]') : null;
                if (box !== next) clearBox();
                box = next;

                if (!box) {
                    track = null;
                    blocked = false;
                    disarm();
                    fadeOutAndPause(250);
                    return;
                }

                const data = box.dataset;
                const volume = Number(data.volume);
                track = {
                    src: data.src || '',
                    title: data.title || '',
                    artist: data.artist || '',
                    cover: data.cover || '',
                    start: Math.max(0, Number(data.start) || 0),
                    volume: Number.isFinite(volume) ? volume : 60,
                };

                const nickname = sheet.querySelector('.es-sheet__nick')?.textContent.trim() || '';
                updateMediaSession(nickname);

                if (box.classList.contains('is-broken') || !track.src) {
                    fadeOutAndPause(250);
                    paint();
                    return;
                }

                if (isOff()) {
                    clearInterval(fadeTimer);
                    audio.pause();
                    paint();
                    return;
                }

                if (loadedSrc !== track.src) {
                    clearInterval(fadeTimer);
                    audio.pause();
                }
                play();
            },

            close() {
                clearBox();
                box = null;
                blocked = false;
                disarm();
                fadeOutAndPause(350);
            },

            toggle() {
                if (!box || !track) return;
                if (!audio.paused && !blocked) {
                    store.set(OFF_KEY, '1');
                    fadeTo(0, 250, () => audio.pause());
                    blocked = false;
                    paint();
                    return;
                }
                store.set(OFF_KEY, '0');
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
    })();

    /* ── Schede ─────────────────────────────────────────────────────── */

    let current = null;        // scheda aperta
    let pushed = false;        // aprendola si e' aggiunta una voce alla cronologia
    let ignorePop = false;     // il popstate provocato da noi con history.back()

    const setMeta = (title, presence) => {
        if (title) document.title = title;
        // richpresence.js rilegge questi meta a ogni cambio di indirizzo.
        const meta = document.querySelector('meta[name="cripsum:presence-state"]');
        if (meta && presence) meta.content = presence;
    };

    const lockPage = (locked) => document.documentElement.classList.toggle('es-lock', locked);

    const resetScroll = (sheet) => {
        const frame = sheet.querySelector('.es-sheet__frame');
        const content = sheet.querySelector('[data-es-scroll]');
        if (frame) frame.scrollTop = 0;
        if (content) content.scrollTop = 0;
    };

    const clearAnimationClasses = (sheet) => {
        clearTimeout(sheet.esTimer);
        sheet.classList.remove('is-closing', 'is-from-next', 'is-from-prev', 'is-swapping');
    };

    const show = (sheet, direction) => {
        clearAnimationClasses(sheet);

        // Una scheda aperta dal server e' "open" ma non modale: la si
        // richiude e riapre come modale, che blocca il resto della pagina.
        if (sheet.open && !isModal(sheet)) sheet.close();

        if (direction) {
            sheet.classList.add(direction > 0 ? 'is-from-next' : 'is-from-prev', 'is-swapping');
            sheet.esTimer = setTimeout(() => clearAnimationClasses(sheet), 420);
        }

        resetScroll(sheet);
        if (!sheet.open) sheet.showModal();
        lockPage(true);
    };

    /**
     * Apre la scheda di un player. `history`: 'push' (dalla griglia),
     * 'replace' (frecce, swipe) o 'none' (indirizzo gia' giusto).
     */
    const open = (slug, { history: mode = 'push', direction = 0 } = {}) => {
        const sheet = bySlug.get(slug);
        if (!sheet) return false;

        const previous = current;
        if (previous && previous !== sheet) {
            clearAnimationClasses(previous);
            previous.close();
        }

        current = sheet;
        show(sheet, previous && previous !== sheet ? direction : 0);

        // Prima il titolo e i meta, poi la cronologia: la Rich Presence
        // legge i meta quando cambia l'indirizzo.
        setMeta(sheet.dataset.esDocTitle, sheet.dataset.esPresence);

        const url = sheet.dataset.esUrl;
        const fromTeam = mode === 'push' || Boolean(window.history.state && window.history.state.esFromTeam);
        if (mode === 'push' && url) {
            window.history.pushState({ esPlayer: slug, esFromTeam: true }, '', url);
            pushed = true;
        } else if (mode === 'replace' && url) {
            window.history.replaceState({ esPlayer: slug, esFromTeam: fromTeam }, '', url);
        }

        // Nello stesso giro del clic: e' questo che permette l'avvio.
        music.load(sheet);
        return true;
    };

    const focusCard = (slug) => {
        const card = document.querySelector(`[data-es-player="${CSS.escape(slug)}"]`);
        if (card) card.focus({ preventScroll: true });
    };

    const close = ({ fromHistory = false } = {}) => {
        const sheet = current;
        if (!sheet) return;
        current = null;
        music.close();

        // Il fuoco torna sulla card dell'ultimo player visto, anche se ci si
        // e' arrivati con le frecce. Solo a scheda chiusa: finche' il dialog
        // modale e' aperto il resto della pagina non puo' ricevere il fuoco.
        const finish = () => {
            if (current === sheet) return; // riaperta nel frattempo
            clearAnimationClasses(sheet);
            if (sheet.open) sheet.close();
            if (!current) {
                lockPage(false);
                focusCard(sheet.dataset.esSheet);
            }
        };

        clearAnimationClasses(sheet);
        if (reduceMotion.matches) {
            finish();
        } else {
            sheet.classList.add('is-closing');
            sheet.esTimer = setTimeout(finish, 220);
        }

        setMeta(teamTitle, teamPresence);

        if (fromHistory) {
            pushed = false;
            return;
        }

        if (pushed) {
            // Si torna alla voce della griglia invece di aggiungerne una:
            // cosi' il tasto Indietro dopo non riapre la scheda.
            pushed = false;
            ignorePop = true;
            window.history.back();
        } else if (base) {
            window.history.replaceState({}, '', base);
        }
    };

    const step = (direction) => {
        if (!current || order.length < 2) return;
        const index = order.indexOf(current.dataset.esSheet);
        const slug = order[(index + direction + order.length) % order.length];
        open(slug, { history: 'replace', direction });
    };

    const slugFromPath = (path) => {
        if (!base || !path.startsWith(base + '/')) return '';
        try {
            return decodeURIComponent(path.slice(base.length + 1).replace(/\/+$/, ''));
        } catch (_) {
            return '';
        }
    };

    window.addEventListener('popstate', () => {
        if (ignorePop) {
            ignorePop = false;
            return;
        }
        const slug = slugFromPath(window.location.pathname);
        if (slug && bySlug.has(slug)) {
            pushed = Boolean(window.history.state && window.history.state.esFromTeam);
            open(slug, { history: 'none' });
        } else if (current) {
            close({ fromHistory: true });
        }
    });

    // Media Session: i tasti avanti/indietro della tastiera o del telefono
    // cambiano player.
    if ('mediaSession' in navigator) {
        const handlers = {
            play: () => { if (!music.isPlaying()) music.toggle(); },
            pause: () => { if (music.isPlaying()) music.toggle(); },
            nexttrack: () => step(1),
            previoustrack: () => step(-1),
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

        const card = target.closest('[data-es-player]');
        if (card && plainClick(event)) {
            if (open(card.dataset.esPlayer, { history: current ? 'replace' : 'push' })) event.preventDefault();
            return;
        }

        if (!current || !current.contains(target)) return;

        if (target === current) {
            // Clic fuori dal riquadro, sullo sfondo scuro.
            close();
            return;
        }

        const closer = target.closest('[data-es-close]');
        if (closer && plainClick(event)) {
            event.preventDefault();
            close();
            return;
        }

        const stepLink = target.closest('[data-es-step]');
        if (stepLink && plainClick(event)) {
            event.preventDefault();
            const index = order.indexOf(current.dataset.esSheet);
            const targetIndex = order.indexOf(stepLink.dataset.esStep);
            const forward = targetIndex === (index + 1) % order.length;
            open(stepLink.dataset.esStep, { history: 'replace', direction: forward ? 1 : -1 });
            return;
        }

        if (target.closest('[data-es-music-toggle]')) {
            music.toggle();
            return;
        }

        const copyButton = target.closest('[data-es-copy]');
        if (copyButton) copyText(copyButton.dataset.esCopy);
    });

    document.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.matches('[data-es-volume]')) {
            music.setVolume(event.target.value);
        }
    });

    sheets.forEach((sheet) => {
        // Esc: la chiusura passa da qui, con animazione e cronologia.
        sheet.addEventListener('cancel', (event) => {
            event.preventDefault();
            if (current === sheet) close();
        });

        // Swipe orizzontale sul telefono per cambiare player.
        let startX = 0;
        let startY = 0;
        let tracking = false;

        sheet.addEventListener('touchstart', (event) => {
            const touch = event.touches[0];
            tracking = event.touches.length === 1 && !(event.target instanceof Element && event.target.closest('input, code'));
            startX = touch.clientX;
            startY = touch.clientY;
        }, { passive: true });

        sheet.addEventListener('touchend', (event) => {
            if (!tracking || current !== sheet) return;
            tracking = false;
            const touch = event.changedTouches[0];
            const dx = touch.clientX - startX;
            const dy = touch.clientY - startY;
            if (Math.abs(dx) > 70 && Math.abs(dy) < 60) step(dx < 0 ? 1 : -1);
        }, { passive: true });
    });

    document.addEventListener('keydown', (event) => {
        if (!current || event.altKey || event.ctrlKey || event.metaKey) return;
        if (event.target instanceof Element && event.target.closest('input, textarea, select')) return;
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            step(1);
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            step(-1);
        }
    });

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
            // Vecchio metodo: il campo va messo nella scheda aperta, perche'
            // fuori da un dialog modale non si puo' selezionare.
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
            (current || body).appendChild(area);
            area.select();
            let ok = false;
            try { ok = document.execCommand('copy'); } catch (__) { ok = false; }
            area.remove();
            showToast(ok ? body.dataset.esCopied : body.dataset.esCopyFailed);
        }
    };

    /* ── Scheda gia' aperta dal server (/it/ohpy/{player}) ──────────── */

    const initial = body.dataset.esOpen;
    if (initial && bySlug.has(initial)) {
        open(initial, { history: 'none' });
    }
})();
