(() => {
    'use strict';

    /**
     * Homepage: la vetrina "Cosa puoi fare", la comparsa allo scorrimento e
     * il contatore di Discord.
     *
     * La vetrina mostra una voce in grande e, sotto, tutte le voci in
     * miniatura. Nell'HTML c'e' gia' la prima voce e ogni miniatura e' un link
     * alla sua pagina, con testi e immagine negli attributi data-*: questo
     * script fa diventare le miniature il selettore della voce in grande.
     *
     * - Col mouse basta passare sopra una miniatura per vederla in grande, e
     *   il clic porta alla pagina. Col dito il primo tocco la mostra, il
     *   secondo (o il pulsante della voce) porta alla pagina.
     * - Da sola passa alla voce dopo quando la linea sopra la miniatura si e'
     *   riempita: l'orologio e' quell'animazione CSS, quindi metterla in
     *   pausa ferma anche il giro. Si ferma col mouse sopra, col focus dentro,
     *   fuori dallo schermo, a scheda nascosta, e per sempre appena si sceglie
     *   una voce a mano (riparte dal pulsante).
     * - Chi ha chiesto meno movimento non ha lo scorrimento automatico.
     */
    const initShowcase = () => {
        const showcase = document.getElementById('homeShowcase');
        const rail = document.getElementById('homeRail');
        const stage = document.getElementById('homeStage');
        const copy = document.getElementById('homeStageCopy');
        const media = document.getElementById('homeStageMedia');
        const title = document.getElementById('homeStageTitle');
        const text = document.getElementById('homeStageText');
        const link = document.getElementById('homeStageLink');
        const cta = document.getElementById('homeStageCta');
        const counter = document.getElementById('homeStageIndex');

        if (!showcase || !rail || !stage || !copy || !media || !title || !text || !link || !cta) return;

        const thumbs = Array.from(rail.querySelectorAll('.home-thumb'));
        if (thumbs.length < 2) return;

        const controls = document.getElementById('homeShowcaseControls');
        const prevButton = document.getElementById('homeShowcasePrev');
        const nextButton = document.getElementById('homeShowcaseNext');
        const pauseButton = document.getElementById('homeShowcasePause');
        const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;

        let current = Math.max(0, thumbs.findIndex((thumb) => thumb.classList.contains('is-active')));

        /* ── Fermo / in movimento ─────────────────────────────── */

        // Ogni motivo per stare fermi ha il suo nome: la vetrina riparte solo
        // quando non ne resta nessuno.
        const holds = new Set();
        const hold = (reason, on) => {
            if (on) holds.add(reason);
            else holds.delete(reason);
            showcase.classList.toggle('is-held', holds.size > 0);
        };

        const setPaused = (paused) => {
            hold('user', paused);
            showcase.classList.toggle('is-paused', paused);
            if (!pauseButton) return;
            pauseButton.setAttribute('aria-label', (paused ? pauseButton.dataset.labelPlay : pauseButton.dataset.labelPause) || '');
            const icon = pauseButton.querySelector('i');
            if (icon) icon.className = paused ? 'fa-solid fa-play' : 'fa-solid fa-pause';
        };

        /* ── Cambio di voce ───────────────────────────────────── */

        // La foto nuova entra sopra quella di prima e solo quando e' pronta;
        // se nel frattempo ne e' stata chiesta un'altra, questa si scarta.
        let mediaTicket = 0;
        const showMedia = (src) => {
            const ticket = ++mediaTicket;
            const image = new Image();
            image.alt = '';
            image.decoding = 'async';

            const place = () => {
                if (ticket !== mediaTicket) return;

                const settle = () => {
                    image.classList.remove('is-entering');
                    while (media.firstElementChild && media.firstElementChild !== image) {
                        media.firstElementChild.remove();
                    }
                };

                media.append(image);
                if (reducedMotion) {
                    settle();
                } else {
                    image.classList.add('is-entering');
                    image.addEventListener('animationend', settle, { once: true });
                }
            };

            image.addEventListener('load', place, { once: true });
            image.addEventListener('error', place, { once: true });
            image.src = src;
        };

        const centerThumb = (thumb) => {
            if (rail.scrollWidth <= rail.clientWidth + 1) return;
            rail.scrollTo({
                left: thumb.offsetLeft - (rail.clientWidth - thumb.offsetWidth) / 2,
                behavior: reducedMotion ? 'auto' : 'smooth'
            });
        };

        const select = (index, { center = true } = {}) => {
            const next = (index + thumbs.length) % thumbs.length;
            if (next === current) return;

            thumbs[current].classList.remove('is-active');
            thumbs[current].removeAttribute('aria-current');

            current = next;
            const thumb = thumbs[current];
            const href = thumb.getAttribute('href') || '#';

            thumb.classList.add('is-active');
            thumb.setAttribute('aria-current', 'true');

            title.textContent = thumb.dataset.title || '';
            text.textContent = thumb.dataset.text || '';
            cta.textContent = thumb.dataset.cta || '';
            link.setAttribute('href', href);
            media.setAttribute('href', href);
            if (counter) counter.textContent = String(current + 1).padStart(2, '0');
            if (thumb.dataset.media) showMedia(thumb.dataset.media);

            // Togliere e rimettere la classe fa ripartire l'animazione del testo.
            copy.classList.remove('is-swapping');
            void copy.offsetWidth;
            copy.classList.add('is-swapping');

            // Non mentre ci si passa sopra col mouse: la fila si sposterebbe
            // sotto il puntatore.
            if (center) centerThumb(thumb);
        };

        // Una scelta fatta a mano ferma il giro: la voce scelta resta li'.
        const choose = (index) => {
            select(index);
            setPaused(true);
        };

        /* ── Miniature ────────────────────────────────────────── */

        let hoverTimer = 0;
        let lastPointer = 'mouse';

        thumbs.forEach((thumb, index) => {
            thumb.addEventListener('pointerdown', (event) => {
                lastPointer = event.pointerType || 'mouse';
            });

            // Un attimo di attesa: attraversare la fila col mouse non deve
            // far sfarfallare tutte le voci.
            thumb.addEventListener('pointerenter', (event) => {
                if (event.pointerType !== 'mouse') return;
                window.clearTimeout(hoverTimer);
                hoverTimer = window.setTimeout(() => select(index, { center: false }), 90);
            });

            thumb.addEventListener('pointerleave', () => window.clearTimeout(hoverTimer));

            // Solo il focus da tastiera: quello che arriva con un clic o un
            // tocco lo gestisce il clic qui sotto.
            thumb.addEventListener('focus', () => {
                if (thumb.matches(':focus-visible')) select(index);
            });

            thumb.addEventListener('click', (event) => {
                const plainTap = lastPointer !== 'mouse' && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
                lastPointer = 'mouse';
                if (!plainTap || index === current) return;

                event.preventDefault();
                choose(index);
            });
        });

        rail.addEventListener('keydown', (event) => {
            const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
            if (step === 0) return;

            event.preventDefault();
            select(current + step);
            thumbs[current].focus({ preventScroll: true });
        });

        /* ── Frecce, pausa, dito ──────────────────────────────── */

        prevButton?.addEventListener('click', () => choose(current - 1));
        nextButton?.addEventListener('click', () => choose(current + 1));
        pauseButton?.addEventListener('click', () => setPaused(!holds.has('user')));

        let touchX = 0;
        let touchY = 0;

        stage.addEventListener('touchstart', (event) => {
            touchX = event.changedTouches[0].clientX;
            touchY = event.changedTouches[0].clientY;
        }, { passive: true });

        stage.addEventListener('touchend', (event) => {
            const dx = event.changedTouches[0].clientX - touchX;
            const dy = event.changedTouches[0].clientY - touchY;
            if (Math.abs(dx) < 48 || Math.abs(dx) < Math.abs(dy) * 1.5) return;

            choose(current + (dx < 0 ? 1 : -1));
        }, { passive: true });

        if (controls) controls.hidden = false;

        /* ── Scorrimento automatico ───────────────────────────── */

        if (reducedMotion) {
            if (pauseButton) pauseButton.hidden = true;
            return;
        }

        showcase.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse') hold('pointer', true);
        });
        showcase.addEventListener('pointerleave', () => hold('pointer', false));

        showcase.addEventListener('focusin', () => hold('focus', true));
        showcase.addEventListener('focusout', (event) => hold('focus', showcase.contains(event.relatedTarget)));

        document.addEventListener('visibilitychange', () => hold('hidden', document.hidden));

        if ('IntersectionObserver' in window) {
            hold('offscreen', true);
            new IntersectionObserver((entries) => {
                entries.forEach((entry) => hold('offscreen', !entry.isIntersecting));
            }, { threshold: 0.4 }).observe(stage);
        }

        rail.addEventListener('animationend', (event) => {
            if (event.animationName === 'homeFill') select(current + 1);
        });

        showcase.classList.add('is-live');
    };

    /**
     * Comparsa allo scorrimento: ogni .home-reveal prende .is-visible quando
     * entra nello schermo, e home.css lo fa comparire.
     */
    const initReveal = () => {
        const items = Array.from(document.querySelectorAll('.home-reveal'));
        const show = (item) => item.classList.add('is-visible');

        // Un riquadro con una foto, e la scritta che gli sta sotto, compaiono
        // quando la foto e' pronta da disegnare: altrimenti si vede prima il
        // riquadro vuoto e la foto arriva di colpo a meta' della comparsa. Se
        // la foto tarda o manca, dopo poco compaiono lo stesso.
        const photoOf = (item) => {
            const tile = item.classList.contains('home-tile') ? item : item.parentElement?.querySelector(':scope > .home-tile');
            return tile ? tile.querySelector('img') : null;
        };

        const reveal = (item) => {
            const photo = photoOf(item);
            if (!photo || typeof photo.decode !== 'function') { show(item); return; }

            const timer = setTimeout(() => show(item), 1200);
            photo.decode().catch(() => { /* foto mancante: compare il riquadro vuoto */ }).then(() => {
                clearTimeout(timer);
                show(item);
            });
        };

        if (!('IntersectionObserver' in window)) {
            items.forEach(show);
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                observer.unobserve(entry.target);
                reveal(entry.target);
            });
        }, { threshold: 0.12 });

        items.forEach((item) => observer.observe(item));
    };

    /**
     * Quante persone ci sono nel Discord, sul bottone del riquadro finale.
     *
     * "Entra nel Discord · 45 online" convince molto piu' di un'icona muta.
     * Si prova prima il widget ufficiale, che pero' va acceso nelle impostazioni
     * del server; se e' spento si ripiega sull'invito pubblico, che risponde
     * sempre. Se non risponde nessuno dei due il numero resta nascosto e il
     * bottone funziona lo stesso.
     */
    const initDiscordCount = () => {
        const badge = document.querySelector('[data-discord-count]');
        const link = badge?.closest('a');
        if (!badge || !link) return;

        const guildId = link.dataset.discordGuild || '';
        const invite = (link.getAttribute('href') || '').split('/').filter(Boolean).pop();
        const CACHE_KEY = 'cripsum_discord_online';
        const CACHE_MS = 5 * 60 * 1000;

        const show = (count) => {
            if (!Number.isFinite(count) || count <= 0) return;
            badge.textContent = `${count} online`;
            badge.hidden = false;
        };

        // Discord non ama essere interrogato a ogni visita, e il numero cambia
        // lentamente: cinque minuti di cache per scheda bastano.
        try {
            const cached = JSON.parse(sessionStorage.getItem(CACHE_KEY) || 'null');
            if (cached && Date.now() - cached.at < CACHE_MS) {
                show(cached.count);
                return;
            }
        } catch { /* sessionStorage non disponibile: si chiede e basta */ }

        const remember = (count) => {
            try { sessionStorage.setItem(CACHE_KEY, JSON.stringify({ count, at: Date.now() })); }
            catch { /* niente cache, pazienza */ }
        };

        const fetchJson = async (url) => {
            const response = await fetch(url, { mode: 'cors' });
            if (!response.ok) throw new Error(String(response.status));
            return response.json();
        };

        (async () => {
            if (guildId) {
                try {
                    const data = await fetchJson(`https://discord.com/api/guilds/${guildId}/widget.json`);
                    const count = Number(data?.presence_count);
                    if (count > 0) { show(count); remember(count); return; }
                } catch { /* widget spento: si prova l'invito */ }
            }

            if (!invite) return;

            try {
                const data = await fetchJson(`https://discord.com/api/v10/invites/${invite}?with_counts=true`);
                const count = Number(data?.approximate_presence_count);
                if (count > 0) { show(count); remember(count); }
            } catch { /* si lascia il bottone senza numero */ }
        })();
    };

    document.addEventListener('DOMContentLoaded', () => {
        initReveal();
        initDiscordCount();
        initShowcase();
    });
})();
