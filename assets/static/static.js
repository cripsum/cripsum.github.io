(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    let toastTimer = null;

    const showToast = (message) => {
        let toast = $('#staticToast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'staticToast';
            toast.className = 'static-toast';
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.classList.add('is-visible');

        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2200);
    };

    const initReveal = () => {
        const items = $$('.static-reveal');

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
        }, { threshold: .1 });

        items.forEach((item) => observer.observe(item));
    };

    const initFaqSearch = () => {
        $$('[data-static-faq-search]').forEach((input) => {
            const target = $(input.dataset.staticFaqSearch);
            if (!target) return;

            const empty = $('[data-static-faq-empty]', target);

            input.addEventListener('input', () => {
                const query = input.value.toLowerCase().trim();
                let visible = 0;

                $$('details', target).forEach((item) => {
                    const match = item.textContent.toLowerCase().includes(query);
                    item.hidden = !match;
                    if (match) visible += 1;
                });

                if (empty) empty.classList.toggle('is-visible', visible === 0);
            });
        });
    };

    const initCopyLinks = () => {
        $$('[data-copy-section]').forEach((button) => {
            button.addEventListener('click', async () => {
                const id = button.dataset.copySection;
                const url = `${location.origin}${location.pathname}#${encodeURIComponent(id)}`;

                try {
                    await navigator.clipboard.writeText(url);
                    showToast('Link copiato.');
                } catch {
                    showToast('Non sono riuscito a copiare.');
                }
            });
        });
    };

    const initBackToTop = () => {
        const button = $('#staticBackTop');
        if (!button) return;

        const update = () => {
            button.classList.toggle('is-visible', window.scrollY > 600);
        };

        window.addEventListener('scroll', update, { passive: true });
        update();

        button.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    };

    const initTocActive = () => {
        const links = $$('.static-toc a[href^="#"]');
        const sections = links
            .map((link) => document.getElementById(link.getAttribute('href').slice(1)))
            .filter(Boolean);

        if (!links.length || !sections.length || !('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;

                links.forEach((link) => {
                    link.classList.toggle('is-active', link.getAttribute('href') === `#${entry.target.id}`);
                });
            });
        }, {
            rootMargin: '-35% 0px -58% 0px',
            threshold: 0
        });

        sections.forEach((section) => observer.observe(section));
    };

    const initNavbarDropdownFallback = () => {
        const toggles = $$('[data-bs-toggle="dropdown"], .dropdown-toggle');

        toggles.forEach((toggle) => {
            if (toggle.dataset.staticDropdownBound === '1') return;
            toggle.dataset.staticDropdownBound = '1';

            toggle.addEventListener('click', (event) => {
                if (window.bootstrap && window.bootstrap.Dropdown) return;

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

    document.addEventListener('DOMContentLoaded', () => {
        initNavbarDropdownFallback();
        initReveal();
        initFaqSearch();
        initCopyLinks();
        initBackToTop();
        initTocActive();
    });
})();
