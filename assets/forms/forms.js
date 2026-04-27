(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const initReveal = () => {
        const items = $$('.form-reveal');

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
        }, { threshold: 0.1 });

        items.forEach((item) => observer.observe(item));
    };

    const initSubmitLoading = () => {
        $$('form[data-form-loading]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('[type="submit"]');
                if (!button) return;

                const text = button.getAttribute('data-loading-text') || 'Invio...';
                button.dataset.originalText = button.innerHTML;
                button.classList.add('is-loading');
                button.setAttribute('disabled', 'disabled');
                button.innerHTML = `<i class="fas fa-circle-notch fa-spin"></i><span>${text}</span>`;
            });
        });
    };

    const initPasswordToggles = () => {
        $$('[data-password-wrap]').forEach((wrap) => {
            const input = wrap.querySelector('input');
            const button = wrap.querySelector('[data-toggle-password]');

            if (!input || !button) return;

            button.addEventListener('click', () => {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                button.innerHTML = isPassword ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
                button.setAttribute('aria-label', isPassword ? 'Nascondi password' : 'Mostra password');
            });
        });
    };

    const initFileNames = () => {
        $$('input[type="file"]').forEach((input) => {
            const output = document.createElement('div');
            output.className = 'form-file-name';
            output.textContent = 'Nessun file selezionato';
            input.insertAdjacentElement('afterend', output);

            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                output.textContent = file ? file.name : 'Nessun file selezionato';
            });
        });
    };

    const initTextareaAutoResize = () => {
        $$('textarea').forEach((textarea) => {
            const resize = () => {
                textarea.style.height = 'auto';
                textarea.style.height = `${textarea.scrollHeight}px`;
            };

            textarea.addEventListener('input', resize);
            resize();
        });
    };

    const initFirstFocus = () => {
        const input = $('main input:not([type="hidden"]):not([disabled]), main textarea:not([disabled]), main select:not([disabled])');
        if (!input || window.innerWidth < 700) return;

        window.setTimeout(() => input.focus(), 250);
    };

    const initNavbarDropdownFallback = () => {
        const toggles = $$('[data-bs-toggle="dropdown"], .dropdown-toggle');

        toggles.forEach((toggle) => {
            if (toggle.dataset.formsDropdownBound === '1') return;
            toggle.dataset.formsDropdownBound = '1';

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
        initPasswordToggles();
        initFileNames();
        initTextareaAutoResize();
        initSubmitLoading();
        initFirstFocus();
    });
})();
