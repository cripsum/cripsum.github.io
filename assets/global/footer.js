(() => {
    'use strict';

    if (window.__cripsumFooterClassicLoaded) return;
    window.__cripsumFooterClassicLoaded = true;

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-footer-back-top]').forEach((button) => {
            button.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    });
})();
