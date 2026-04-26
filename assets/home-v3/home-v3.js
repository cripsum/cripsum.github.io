(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    let toastTimer = null;

    const showToast = (message) => {
        const toast = $('#homeToast');
        if (!toast) return;

        toast.textContent = message;
        toast.classList.add('is-visible');

        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toast.classList.remove('is-visible');
        }, 2200);
    };

    const copyText = async (text) => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return true;
            }

            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            const ok = document.execCommand('copy');
            textarea.remove();
            return ok;
        } catch {
            return false;
        }
    };

    const initCopy = () => {
        $$('.js-copy-home').forEach((button) => {
            button.addEventListener('click', async () => {
                const url = button.dataset.url || location.href;
                const ok = await copyText(url);
                showToast(ok ? 'Link copiato.' : 'Non sono riuscito a copiare.');
            });
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

    const initQuickSearch = () => {
        const input = $('#homeQuickSearch');
        const results = $('#homeQuickResults');
        if (!input || !results) return;

        const items = $$('[data-search-item]', document);
        const quickItems = $$('a[data-search-item]', results);

        input.addEventListener('input', () => {
            const query = input.value.trim().toLowerCase();

            if (!query) {
                quickItems.forEach((item, index) => {
                    item.hidden = index >= 5;
                });
                return;
            }

            let shown = 0;
            quickItems.forEach((item) => {
                const match = (item.dataset.title || '').includes(query);
                item.hidden = !match || shown >= 5;
                if (match) shown += 1;
            });

            if (shown === 0) {
                results.dataset.empty = '1';
            } else {
                delete results.dataset.empty;
            }
        });

        // Track last clicked section.
        items.forEach((item) => {
            item.addEventListener('click', () => {
                const title = item.querySelector('strong, span')?.textContent?.trim() || item.dataset.title || 'Continua';
                localStorage.setItem('cripsum:lastSection', JSON.stringify({
                    title,
                    url: item.getAttribute('href') || '/',
                    time: Date.now(),
                }));
            });
        });
    };

    const initContinue = () => {
        const link = $('#homeContinue');
        if (!link) return;

        try {
            const raw = localStorage.getItem('cripsum:lastSection');
            if (!raw) return;

            const data = JSON.parse(raw);
            if (!data?.url || !data?.title) return;

            link.href = data.url;
            link.querySelector('span').textContent = `Continua: ${data.title}`;
            link.hidden = false;
        } catch {
            localStorage.removeItem('cripsum:lastSection');
        }
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
        initCopy();
        initReveal();
        initQuickSearch();
        initContinue();
    });
})();
