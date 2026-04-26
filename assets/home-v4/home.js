(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const showcaseSlides = [
        {
            media: '../img/jay-quadrato.png',
            title: 'Ciao! Sono Jay!',
            description: 'Vuoi imparare l’arte dello Spinjitzu?',
            buttonText: 'Acquista il videocorso',
            link: 'https://payhip.com/b/m0kaT'
        },
        {
            media: '../img/chinese-essay-2.jpg',
            title: 'Hey! Mi chiamo 優希!',
            description: 'Vuoi imparare l’arte dello Yoshukai?',
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
    ];

    let showcaseIndex = Math.floor(Math.random() * showcaseSlides.length);
    let showcaseTimer = null;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;'
    }[char]));

    const renderShowcase = () => {
        const main = $('#homeShowcaseMain');
        const thumbs = $('#homeShowcaseThumbs');

        if (!main || !thumbs) return;

        const slide = showcaseSlides[showcaseIndex];

        main.innerHTML = `
            <div class="home-showcase-media">
                <img src="${escapeHtml(slide.media)}" alt="${escapeHtml(slide.title)}" loading="lazy" onerror="this.closest('.home-showcase-media').classList.add('is-broken')">
            </div>

            <div class="home-showcase-content">
                <span class="home-showcase-label">Cripsum</span>
                <h3 class="home-showcase-title">${escapeHtml(slide.title)}</h3>
                <p class="home-showcase-description">${escapeHtml(slide.description)}</p>
                <a class="home-btn home-btn--primary home-showcase-button" href="${escapeHtml(slide.link)}">
                    <span>${escapeHtml(slide.buttonText)}</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        `;

        thumbs.innerHTML = showcaseSlides.map((item, index) => `
            <button type="button" class="home-showcase-thumb ${index === showcaseIndex ? 'is-active' : ''}" data-showcase-index="${index}" aria-label="Apri ${escapeHtml(item.title)}">
                <img src="${escapeHtml(item.media)}" alt="" loading="lazy">
                <span>${escapeHtml(item.title)}</span>
            </button>
        `).join('');

        $$('[data-showcase-index]', thumbs).forEach((button) => {
            button.addEventListener('click', () => {
                const index = Number(button.dataset.showcaseIndex);
                if (!Number.isFinite(index) || index === showcaseIndex) return;
                showcaseIndex = index;
                renderShowcase();
                restartShowcaseAuto();
            });
        });
    };

    const nextShowcase = () => {
        showcaseIndex = (showcaseIndex + 1) % showcaseSlides.length;
        renderShowcase();
    };

    const previousShowcase = () => {
        showcaseIndex = (showcaseIndex - 1 + showcaseSlides.length) % showcaseSlides.length;
        renderShowcase();
    };

    const startShowcaseAuto = () => {
        clearInterval(showcaseTimer);
        showcaseTimer = setInterval(nextShowcase, 6500);
    };

    const restartShowcaseAuto = () => {
        startShowcaseAuto();
    };

    const initShowcase = () => {
        if (!$('#homeShowcaseMain')) return;

        renderShowcase();
        startShowcaseAuto();

        $('#homeShowcaseNext')?.addEventListener('click', () => {
            nextShowcase();
            restartShowcaseAuto();
        });

        $('#homeShowcasePrev')?.addEventListener('click', () => {
            previousShowcase();
            restartShowcaseAuto();
        });

        $('#homeShowcase')?.addEventListener('mouseenter', () => clearInterval(showcaseTimer));
        $('#homeShowcase')?.addEventListener('mouseleave', startShowcaseAuto);
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
                // fallback già attivo
            }
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        initNavbarDropdownFallback();
        initBootstrapAfterLoad();
        initReveal();
        initShowcase();
        document.body.classList.add('home-is-ready');
    });
})();
