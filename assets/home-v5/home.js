(() => {
    'use strict';

    const lang = location.pathname.split('/').find(s => s === 'it' || s === 'en') || 'it';

    const t = {
        it: {
            open_slide: (title) => `Apri ${title}`,
            open: 'Apri',
            by: 'di',
            pause: 'Metti in pausa',
            play: 'Riprendi',
            claim: 'Riscatta 500 Godos',
            claimed: 'Riscattati',
            claim_again: 'tra',
            claim_error: 'Non riuscito, riprova',
        },
        en: {
            open_slide: (title) => `Open ${title}`,
            open: 'Open',
            by: 'by',
            pause: 'Pause',
            play: 'Resume',
            claim: 'Claim 500 Godos',
            claimed: 'Claimed',
            claim_again: 'again in',
            claim_error: 'Didn’t work, try again',
        },
    }[lang];

    const slideData = {
        it: [
            {
                media: '../img/profili.png',
                title: 'Profili custom!',
                description: 'Personalizza il tuo profilo creando una bio o un portfolio clean.',
                buttonText: 'Modifica il tuo profilo',
                link: '../profile'
            },
            {
                media: '../img/jay-quadrato.png',
                title: 'Ciao! Sono Jay!',
                description: 'Vuoi imparare l\u2019arte dello Spinjitzu?',
                buttonText: 'Scarica il videocorso',
                link: 'download'
            },
            {
                media: '../img/chinese-essay-2.jpg',
                title: 'Hey! Mi chiamo \u512a\u5e0c!',
                description: 'Vuoi imparare l\u2019arte dello Yoshukai?',
                buttonText: 'Scarica la guida',
                link: 'download/yoshukai'
            },
            {
                media: '../img/segone4.png',
                title: 'Achievements',
                description: 'Sblocca gli achievement del sito e guarda i tuoi progressi.',
                buttonText: 'Vedi achievement',
                link: 'achievements'
            },
            {
                media: '../img/waguri.jpeg',
                title: 'Lootbox',
                description: 'Apri lootbox e aggiungi personaggi alla tua collezione.',
                buttonText: 'Apri lootbox',
                link: 'lootbox'
            },
            {
                media: '../img/pfp choso2 cc.png',
                title: 'I miei Edit',
                description: 'Guarda gli ultimi edit e video caricati sul sito.',
                buttonText: 'Guarda gli edit',
                link: 'edits'
            },
            {
                media: '../img/mentone.jpg',
                title: 'GoonLand',
                description: 'La parte più interna e strana del sito.',
                buttonText: 'Entra',
                link: 'goonland/home'
            },
            {
                media: '../img/abdul.jpg',
                title: 'Chat Globale',
                description: 'Chatta con gli altri utenti del sito.',
                buttonText: 'Apri chat',
                link: 'global-chat'
            },
            {
                media: '../img/dukedennis.jpg',
                title: 'Downloads',
                description: 'Scarica contenuti, file e robe del sito.',
                buttonText: 'Vai ai download',
                link: 'download'
            }
        ],
        en: [
            {
                media: '../img/profili.png',
                title: 'Custom profiles!',
                description: 'Customise your profile by creating a clean bio or portfolio.',
                buttonText: 'Edit your profile',
                link: '../profile'
            },
            {
                media: '../img/jay-quadrato.png',
                title: 'Hi! I\'m Jay!',
                description: 'Want to learn the art of Spinjitzu?',
                buttonText: 'Download the video course',
                link: 'download'
            },
            {
                media: '../img/chinese-essay-2.jpg',
                title: 'Hey! My name is \u512a\u5e0c!',
                description: 'Want to learn the art of Yoshukai?',
                buttonText: 'Download the guide',
                link: 'download/yoshukai'
            },
            {
                media: '../img/segone4.png',
                title: 'Achievements',
                description: 'Unlock site achievements and track your progress.',
                buttonText: 'View achievements',
                link: 'achievements'
            },
            {
                media: '../img/waguri.jpeg',
                title: 'Lootbox',
                description: 'Open lootboxes and add characters to your collection.',
                buttonText: 'Open lootbox',
                link: 'lootbox'
            },
            {
                media: '../img/pfp choso2 cc.png',
                title: 'My Edits',
                description: 'Watch the latest edits and videos uploaded to the site.',
                buttonText: 'Watch edits',
                link: 'edits'
            },
            {
                media: '../img/mentone.jpg',
                title: 'GoonLand',
                description: 'The most internal and "special" part of the site.',
                buttonText: 'Enter',
                link: 'goonland/home'
            },
            {
                media: '../img/abdul.jpg',
                title: 'Global Chat',
                description: 'Chat with other users on the site.',
                buttonText: 'Open chat',
                link: 'global-chat'
            },
            {
                media: '../img/dukedennis.jpg',
                title: 'Downloads',
                description: 'Download content, files and stuff from the site.',
                buttonText: 'Go to downloads',
                link: 'download'
            }
        ],
    };


    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;'
    }[char]));

    const cleanTitle = (title) => String(title || '').replace(/[🏆📦🎬🌟💬⬇️]/g, '').trim();

    /**
     * Le slide scritte a mano restano come rete di sicurezza.
     *
     * Sono quello che si vedeva prima: un elenco di pagine con degli screenshot.
     * Servono ancora quando il feed non risponde, quando non c'e' niente di
     * recente da mostrare, o mentre la richiesta e' in volo — la sezione non
     * deve mai restare vuota.
     */
    const normalize = (slide) => ({
        media: String(slide.media || ''),
        title: String(slide.title || ''),
        description: String(slide.description || ''),
        link: String(slide.link || ''),
        buttonText: String(slide.buttonText || '') || t.open
    });

    const staticSlides = slideData[lang].map(normalize);

    /**
     * Le slide vere arrivano dal database, stampate dentro la pagina da
     * home_slides.php e gestite dal pannello admin. Quelle qui sopra restano
     * come rete di sicurezza: se la tabella non c'e' ancora, o e' vuota, o il
     * JSON e' illeggibile, la sezione resta quella di sempre invece di
     * sparire.
     */
    const slidesFromPage = () => {
        const tag = document.getElementById('homeSlidesData');
        if (!tag) return null;

        try {
            const parsed = JSON.parse(tag.textContent || '[]');
            if (!Array.isArray(parsed) || !parsed.length) return null;

            return parsed.map(normalize).filter((slide) => slide.title && slide.media);
        } catch {
            return null;
        }
    };

    let slides = staticSlides;
    let index = 0;
    let autoTimer = null;
    let progressTimer = null;
    let dragStartX = null;
    const duration = 6500;

    /**
     * Motivi per cui la riproduzione automatica sta ferma.
     *
     * Sono indipendenti fra loro — il mouse sopra, la scheda in secondo piano,
     * la sezione fuori schermo, la pausa chiesta a mano — e finche' ne resta
     * anche uno solo non si riparte. Tenendoli in un insieme non c'e' modo che
     * due cause che finiscono in ordine diverso facciano ripartire lo slider
     * quando non dovrebbe.
     */
    const holds = new Set();

    // Chi ha chiesto meno animazioni non si merita una giostra che parte da
    // sola: le frecce e i pallini continuano a funzionare.
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)');

    const canAutoplay = () => holds.size === 0 && !reducedMotion?.matches && slides.length > 1;

    const hold = (reason) => {
        holds.add(reason);
        stopAuto();
    };

    const release = (reason) => {
        holds.delete(reason);
        if (canAutoplay()) startAuto();
    };

    /**
     * Precarica l'immagine prima di mostrarla.
     *
     * Senza questo passaggio la slide compariva vuota e l'immagine ci cadeva
     * dentro un istante dopo, con uno scatto a ogni cambio. Un errore di
     * caricamento non blocca niente: si va avanti e al massimo si vede il
     * riquadro senza figura.
     */
    const preload = (slide) => new Promise((resolve) => {
        if (!slide.media) {
            resolve();
            return;
        }

        const image = new Image();
        image.onload = () => resolve();
        image.onerror = () => resolve();
        image.src = slide.media;

        // Se la rete e' lenta non si resta bloccati: dopo un secondo si mostra
        // comunque e l'immagine arrivera' quando arriva.
        setTimeout(resolve, 1000);
    });

    const renderTabs = () => {
        const tabs = $('#homeSliderTabs');
        if (!tabs) return;

        tabs.innerHTML = slides.map((slide, slideIndex) => `
            <button type="button"
                    role="tab"
                    class="home-tab ${slideIndex === index ? 'is-active' : ''}"
                    data-slide="${slideIndex}"
                    aria-selected="${slideIndex === index ? 'true' : 'false'}"
                    aria-label="${escapeHtml(t.open_slide(cleanTitle(slide.title)))}">
                <img src="${escapeHtml(slide.media)}" alt="" loading="lazy">
                <span>${escapeHtml(cleanTitle(slide.title))}</span>
            </button>
        `).join('');

        $$('[data-slide]', tabs).forEach((button) => {
            button.addEventListener('click', () => {
                const nextIndex = Number(button.dataset.slide);
                if (!Number.isFinite(nextIndex) || nextIndex === index) return;
                go(nextIndex);
            });
        });

        // La linguetta attiva deve restare visibile anche quando le slide
        // scorrono da sole e la striscia e' piu' larga dello schermo.
        tabs.querySelector('.is-active')?.scrollIntoView({
            behavior: reducedMotion?.matches ? 'auto' : 'smooth',
            block: 'nearest',
            inline: 'nearest'
        });
    };

    const paintSlide = () => {
        const stage = $('#homeSliderStage');
        const backdrop = $('#homeSliderBackdrop');
        if (!stage) return;

        const slide = slides[index];

        if (backdrop) {
            backdrop.style.backgroundImage = `url("${slide.media}")`;
        }

        stage.innerHTML = `
            <article class="home-slide is-entering">
                <div class="home-slide__copy">
                    <h3 class="home-slide__title">${escapeHtml(slide.title)}</h3>
                    <p class="home-slide__description">${escapeHtml(slide.description)}</p>
                    <a class="home-btn home-btn--primary home-slide__button" href="${escapeHtml(slide.link)}">
                        <span>${escapeHtml(slide.buttonText)}</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="home-slide__media">
                    <img src="${escapeHtml(slide.media)}" alt="${escapeHtml(slide.title)}" loading="lazy">
                </div>
            </article>
        `;

        requestAnimationFrame(() => {
            stage.querySelector('.home-slide')?.classList.remove('is-entering');
        });

        renderTabs();
        resetProgress();
    };

    /**
     * Cambia slide precaricando il media, e ignora i cambi che arrivano mentre
     * uno e' gia' in corso: con la riproduzione automatica addosso a un clic
     * capitava di vedere due transizioni sovrapposte.
     */
    let painting = false;
    const go = async (nextIndex, { restart = true } = {}) => {
        if (painting || !slides.length) return;

        const target = ((nextIndex % slides.length) + slides.length) % slides.length;
        if (target === index && slides.length > 1) return;

        painting = true;
        index = target;

        await preload(slides[index]);
        paintSlide();
        painting = false;

        // La slide dopo si scarica mentre questa si guarda: al cambio e' gia'
        // in cache e la transizione non ha buchi.
        if (slides.length > 1) preload(slides[(index + 1) % slides.length]);

        if (restart && canAutoplay()) startAuto();
    };

    const next = () => go(index + 1);
    const prev = () => go(index - 1);

    const resetProgress = () => {
        const progress = $('#homeSliderProgress');
        if (!progress) return;

        clearTimeout(progressTimer);
        progress.style.transition = 'none';
        progress.style.width = '0%';

        if (!canAutoplay()) return;

        progressTimer = setTimeout(() => {
            progress.style.transition = `width ${duration}ms linear`;
            progress.style.width = '100%';
        }, 40);
    };

    const stopAuto = () => {
        clearInterval(autoTimer);
        autoTimer = null;
        clearTimeout(progressTimer);

        const progress = $('#homeSliderProgress');
        if (progress) {
            // Si congela dov'e' invece di tornare a zero: cosi' si vede che e'
            // in pausa e non che e' appena ripartito.
            const width = getComputedStyle(progress).width;
            progress.style.transition = 'none';
            progress.style.width = width;
        }

        $('#homeSlider')?.classList.add('is-paused');
    };

    const startAuto = () => {
        clearInterval(autoTimer);
        if (!canAutoplay()) return;

        autoTimer = setInterval(() => go(index + 1, { restart: false }), duration);
        $('#homeSlider')?.classList.remove('is-paused');
        resetProgress();
    };

    const initSlider = () => {
        const slider = $('#homeSlider');
        if (!$('#homeSliderStage')) return;

        // Le slide del pannello admin sono gia' nella pagina: si usano subito,
        // senza aspettare una richiesta. Se non ci sono restano quelle scritte
        // qui dentro.
        slides = slidesFromPage() || staticSlides;

        // Si parte sempre dalla prima. Prima si partiva da una a caso, e con
        // l'ordine deciso dal pannello quello era il motivo per cui spostare
        // una slide sembrava non cambiare niente: l'ordine c'era, ma ogni
        // visita cominciava da un punto diverso.
        index = 0;

        paintSlide();
        startAuto();

        $('#homeSliderNext')?.addEventListener('click', next);
        $('#homeSliderPrev')?.addEventListener('click', prev);

        const pauseButton = $('#homeSliderPause');
        pauseButton?.addEventListener('click', () => {
            const paused = holds.has('manuale');

            if (paused) release('manuale');
            else hold('manuale');

            pauseButton.setAttribute('aria-pressed', paused ? 'false' : 'true');
            pauseButton.setAttribute('aria-label', paused ? t.pause : t.play);
            pauseButton.innerHTML = paused
                ? '<i class="fa-solid fa-pause"></i>'
                : '<i class="fa-solid fa-play"></i>';
        });

        slider?.addEventListener('mouseenter', () => hold('mouse'));
        slider?.addEventListener('mouseleave', () => release('mouse'));

        // Chi naviga da tastiera resta dentro la slide finche' non ha finito:
        // senza questo, il fuoco si sposterebbe su un bottone che nel frattempo
        // e' stato sostituito.
        slider?.addEventListener('focusin', () => hold('fuoco'));
        slider?.addEventListener('focusout', (event) => {
            if (!slider.contains(event.relatedTarget)) release('fuoco');
        });

        slider?.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowRight') { event.preventDefault(); next(); }
            if (event.key === 'ArrowLeft') { event.preventDefault(); prev(); }
        });

        // A scheda nascosta il timer continuerebbe a girare a vuoto, e al
        // ritorno si troverebbero cinque slide saltate in un colpo.
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) hold('scheda');
            else release('scheda');
        });

        // Fuori schermo non serve far girare niente.
        if ('IntersectionObserver' in window && slider) {
            hold('fuori-schermo');

            new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) release('fuori-schermo');
                    else hold('fuori-schermo');
                });
            }, { threshold: 0.25 }).observe(slider);
        }

        reducedMotion?.addEventListener?.('change', () => {
            if (reducedMotion.matches) stopAuto();
            else startAuto();
        });

        slider?.addEventListener('pointerdown', (event) => {
            dragStartX = event.clientX;
        });

        slider?.addEventListener('pointerup', (event) => {
            if (dragStartX === null) return;

            const diff = event.clientX - dragStartX;
            dragStartX = null;

            if (Math.abs(diff) < 45) return;

            if (diff < 0) next();
            else prev();
        });

        const tabs = $('#homeSliderTabs');
        tabs?.addEventListener('wheel', (event) => {
            const shouldScrollHorizontal = Math.abs(event.deltaY) > Math.abs(event.deltaX);
            if (!shouldScrollHorizontal) return;

            event.preventDefault();
            tabs.scrollLeft += event.deltaY;
        }, { passive: false });
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
        const badge = $('[data-discord-count]');
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

    /**
     * Riscatto giornaliero Premium, dal pulsante dell'hero.
     *
     * Usa la stessa API della Lootbox. Il pulsante arriva dal server gia' nello
     * stato giusto (da riscattare oppure riscattato): qui si gestiscono il
     * clic e il conto alla rovescia fino a mezzanotte, quando torna premibile
     * senza dover ricaricare la pagina.
     */
    const initPremiumClaim = () => {
        const button = $('[data-premium-claim]');
        if (!button) return;

        const csrf = $('meta[name="csrf-token"]')?.content || '';
        const loadedAt = Date.now();
        const secondsAtLoad = Number(button.dataset.secondsLeft) || 0;
        let ticker = null;

        const clock = (seconds) => [Math.floor(seconds / 3600), Math.floor((seconds % 3600) / 60), seconds % 60]
            .map((part) => String(part).padStart(2, '0'))
            .join(':');

        // Quanto manca a mezzanotte adesso, partendo dal numero che il server
        // ha stampato quando ha costruito la pagina.
        const secondsToMidnight = () => Math.max(0, secondsAtLoad - Math.floor((Date.now() - loadedAt) / 1000));

        const showReady = () => {
            clearInterval(ticker);
            button.disabled = false;
            button.classList.remove('is-claimed', 'is-busy');
            button.classList.add('home-btn--premium');
            button.innerHTML = `<img class="home-btn__coin" src="/img/godos-icon.png" alt="" width="20" height="20"><span>${escapeHtml(t.claim)}</span>`;
        };

        const showClaimed = (seconds) => {
            // Si conta verso un orario fisso e non sottraendo un secondo alla
            // volta: a scheda in secondo piano i timer rallentano, e al
            // ritorno il conto sarebbe rimasto indietro.
            const endsAt = Date.now() + seconds * 1000;
            const left = () => Math.max(0, Math.round((endsAt - Date.now()) / 1000));

            clearInterval(ticker);
            button.disabled = true;
            button.classList.remove('home-btn--premium', 'is-busy');
            button.classList.add('is-claimed');
            button.innerHTML = `<i class="fa-solid fa-check"></i><span><strong>${escapeHtml(t.claimed)}</strong> · ${escapeHtml(t.claim_again)} <span data-claim-countdown>${clock(left())}</span></span>`;

            const countdown = $('[data-claim-countdown]', button);
            ticker = setInterval(() => {
                if (left() <= 0) {
                    showReady();
                    return;
                }
                countdown.textContent = clock(left());
            }, 1000);
        };

        const showError = () => {
            button.classList.remove('is-busy');
            button.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i><span>${escapeHtml(t.claim_error)}</span>`;
            setTimeout(showReady, 2600);
        };

        if (button.classList.contains('is-claimed')) showClaimed(secondsToMidnight());

        button.addEventListener('click', async () => {
            if (button.disabled || button.classList.contains('is-busy')) return;

            button.disabled = true;
            button.classList.add('is-busy');

            try {
                const response = await fetch('/api/premium_daily_claim.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf }
                });
                const data = await response.json();

                if (data.success) {
                    showClaimed(Number(data.seconds_left) || secondsToMidnight());
                } else if (data.code === 'ALREADY_CLAIMED') {
                    // Riscattato da un'altra scheda o dalla Lootbox: non e' un errore.
                    showClaimed(secondsToMidnight());
                } else {
                    showError();
                }
            } catch {
                showError();
            }
        });
    };

    const initReveal = () => {
        const items = $$('.home-reveal');

        if (!('IntersectionObserver' in window)) {
            items.forEach((item) => item.classList.add('is-visible'));
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12 });

        items.forEach((item) => observer.observe(item));
    };

    const initNavbarDropdownFallback = () => {
        const toggles = $$('[data-bs-toggle="dropdown"], .dropdown-toggle');

        toggles.forEach((toggle) => {
            if (toggle.dataset.homeDropdownBound === '1') return;
            toggle.dataset.homeDropdownBound = '1';

            toggle.addEventListener('click', (event) => {
                const hasBootstrap = window.bootstrap && window.bootstrap.Dropdown;
                if (hasBootstrap) return;

                event.preventDefault();
                event.stopPropagation();

                const parent = toggle.closest('.dropdown') || toggle.parentElement;
                const menu = parent?.querySelector('.dropdown-menu');

                if (!menu) return;

                $$('.dropdown-menu.show').forEach((other) => {
                    if (other !== menu) other.classList.remove('show');
                });

                menu.classList.toggle('show');
                toggle.setAttribute('aria-expanded', menu.classList.contains('show') ? 'true' : 'false');
            });
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('.dropdown')) return;
            $$('.dropdown-menu.show').forEach((menu) => menu.classList.remove('show'));
        });
    };

    const initBootstrapAfterLoad = () => {
        if (!window.bootstrap || !window.bootstrap.Dropdown) return;

        $$('.dropdown-toggle').forEach((toggle) => {
            try {
                window.bootstrap.Dropdown.getOrCreateInstance(toggle);
            } catch {
                // fallback already active
            }
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        initNavbarDropdownFallback();
        initBootstrapAfterLoad();
        initReveal();
        initSlider();
        initPremiumClaim();
        initDiscordCount();
        document.body.classList.add('home-is-ready');
    });
})();