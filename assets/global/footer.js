(() => {
    'use strict';

    if (window.__cripsumFooterClassicLoaded) return;
    window.__cripsumFooterClassicLoaded = true;

    /*
     * Interruttori di Google Analytics ([data-analytics-toggle]): quello nel
     * footer e quello della pagina Cookie. Lo stato lo tiene
     * window.cripsumAnalytics (head-import.php); le pagine che non lo
     * caricano lasciano l'interruttore nascosto.
     */
    const initAnalyticsToggles = () => {
        const analytics = window.cripsumAnalytics;
        const toggles = document.querySelectorAll('[data-analytics-toggle]');
        if (!analytics || !toggles.length) return;

        const render = () => {
            const on = analytics.enabled();
            toggles.forEach((toggle) => {
                const label = toggle.querySelector('[data-analytics-label]') || toggle;
                label.textContent = on ? toggle.dataset.labelOn : toggle.dataset.labelOff;
                toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
                toggle.title = (on ? toggle.dataset.titleOn : toggle.dataset.titleOff) || '';
            });
        };

        toggles.forEach((toggle) => {
            toggle.closest('[data-analytics-item]')?.removeAttribute('hidden');
            toggle.removeAttribute('hidden');
            toggle.addEventListener('click', () => analytics.set(!analytics.enabled()));
        });

        document.addEventListener('cripsum:analytics', render);
        render();
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-footer-back-top]').forEach((button) => {
            button.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        initAnalyticsToggles();
    });
})();
