(() => {
    'use strict';

    const lang = location.pathname.split('/').find(s => s === 'it' || s === 'en') || 'it';

    const t = {
        it: { open_slide: (title) => `Apri ${title}` },
        en: { open_slide: (title) => `Open ${title}` },
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
                buttonText: 'Acquista il videocorso',
                link: 'https://payhip.com/b/m0kaT'
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
                buttonText: 'Buy the video course',
                link: 'https://payhip.com/b/m0kaT'
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

    const slides = slideData[lang];
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    let index = Math.floor(Math.random() * slides.length);
    let autoTimer = null;
    let progressTimer = null;
    let dragStartX = null;
    const duration = 6500;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;'
    }[char]));

    const cleanTitle = (title) => String(title || '').replace(/[🏆📦🎬🌟💬⬇️]/g, '').trim();

    const renderTabs = () => {
        const tabs = $('#homeSliderTabs');
        if (!tabs) return;

        tabs.innerHTML = slides.map((slide, slideIndex) => `
            <button type="button" class="home-tab ${slideIndex === index ? 'is-active' : ''}" data-slide="${slideIndex}" aria-label="${escapeHtml(t.open_slide(cleanTitle(slide.title)))}">
                <img src="${escapeHtml(slide.media)}" alt="" loading="lazy">
                <span>${escapeHtml(cleanTitle(slide.title))}</span>
            </button>
        `).join('');

        $$('[data-slide]', tabs).forEach((button) => {
            button.addEventListener('click', () => {
                const nextIndex = Number(button.dataset.slide);
                if (!Number.isFinite(nextIndex) || nextIndex === index) return;
                index = nextIndex;
                renderSlider();
                restartAuto();
            });
        });
    };

    const renderSlider = () => {
        const stage = $('#homeSliderStage');
        const backdrop = $('#homeSliderBackdrop');
        if (!stage) return;

        const slide = slides[index];

        if (backdrop) {
            backdrop.style.backgroundImage = `url("${slide.media}")`;
        }

        stage.innerHTML = `
            <article class="home-slide">
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

        renderTabs();
        resetProgress();
    };

    const next = () => {
        index = (index + 1) % slides.length;
        renderSlider();
    };

    const prev = () => {
        index = (index - 1 + slides.length) % slides.length;
        renderSlider();
    };

    const resetProgress = () => {
        const progress = $('#homeSliderProgress');
        if (!progress) return;

        progress.style.transition = 'none';
        progress.style.width = '0%';

        clearTimeout(progressTimer);
        progressTimer = setTimeout(() => {
            progress.style.transition = `width ${duration}ms linear`;
            progress.style.width = '100%';
        }, 40);
    };

    const startAuto = () => {
        clearInterval(autoTimer);
        autoTimer = setInterval(next, duration);
        resetProgress();
    };

    const restartAuto = () => {
        startAuto();
    };

    const initSlider = () => {
        if (!$('#homeSliderStage')) return;

        renderSlider();
        startAuto();

        $('#homeSliderNext')?.addEventListener('click', () => {
            next();
            restartAuto();
        });

        $('#homeSliderPrev')?.addEventListener('click', () => {
            prev();
            restartAuto();
        });

        const slider = $('#homeSlider');

        slider?.addEventListener('mouseenter', () => {
            clearInterval(autoTimer);
            const progress = $('#homeSliderProgress');
            if (progress) {
                progress.style.transition = 'none';
            }
        });

        slider?.addEventListener('mouseleave', startAuto);

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

            restartAuto();
        });

        const tabs = $('#homeSliderTabs');
        tabs?.addEventListener('wheel', (event) => {
            const shouldScrollHorizontal = Math.abs(event.deltaY) > Math.abs(event.deltaX);
            if (!shouldScrollHorizontal) return;

            event.preventDefault();
            tabs.scrollLeft += event.deltaY;
        }, { passive: false });
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
        document.body.classList.add('home-is-ready');
    });
})();