(() => {
    'use strict';

    if (window.__cripsumFooterV2Loaded) return;
    window.__cripsumFooterV2Loaded = true;

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-cripsum-back-top]').forEach((button) => {
            button.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    });
})();
