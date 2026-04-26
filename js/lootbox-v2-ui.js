(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const rarityClasses = [
        'lootbox-rarity-comune',
        'lootbox-rarity-raro',
        'lootbox-rarity-epico',
        'lootbox-rarity-leggendario',
        'lootbox-rarity-speciale',
        'lootbox-rarity-segreto',
        'lootbox-rarity-theone'
    ];

    const removeRarityClasses = () => {
        document.body.classList.remove(...rarityClasses);
    };

    const getRarityFromText = (text) => {
        const value = String(text || '').toLowerCase();

        if (value.includes('più raro') || value.includes('the one')) return 'theone';
        if (value.includes('segreto')) return 'segreto';
        if (value.includes('speciale')) return 'speciale';
        if (value.includes('leggendario')) return 'leggendario';
        if (value.includes('epico')) return 'epico';
        if (value.includes('raro')) return 'raro';
        if (value.includes('comune')) return 'comune';

        return null;
    };

    const applyRaritySkin = () => {
        const message = $('#messaggioRarita');
        if (!message) return;

        const rarity = getRarityFromText(message.textContent);
        if (!rarity) return;

        removeRarityClasses();
        document.body.classList.add(`lootbox-rarity-${rarity}`);
    };

    const updateVisualState = () => {
        const cassa = $('#cassa');
        const contenuto = $('#contenuto');
        const message = $('#messaggioRarita');

        document.body.classList.toggle('lootbox-is-opening', !!cassa?.classList.contains('aperta'));
        document.body.classList.toggle('lootbox-has-result', !!contenuto?.querySelector('.premio'));
        document.body.classList.toggle('lootbox-has-message', !!String(message?.textContent || '').trim());

        applyRaritySkin();
    };

    const initChestFeedback = () => {
        const cassa = $('#cassa');
        if (!cassa) return;

        cassa.addEventListener('click', () => {
            cassa.classList.remove('ui-press');
            void cassa.offsetWidth;
            cassa.classList.add('ui-press');
            setTimeout(() => cassa.classList.remove('ui-press'), 460);
        });
    };

    const initObservers = () => {
        const targets = ['#cassa', '#contenuto', '#messaggioRarita', '#divApriAncora']
            .map((selector) => $(selector))
            .filter(Boolean);

        const observer = new MutationObserver(updateVisualState);
        targets.forEach((target) => {
            observer.observe(target, {
                attributes: true,
                childList: true,
                subtree: true,
                characterData: true,
                attributeFilter: ['class', 'style']
            });
        });

        updateVisualState();
    };

    const initNavbarDropdownFallback = () => {
        const toggles = $$('[data-bs-toggle="dropdown"], .dropdown-toggle');

        toggles.forEach((toggle) => {
            if (toggle.dataset.lootDropdownBound === '1') return;
            toggle.dataset.lootDropdownBound = '1';

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
        document.body.classList.add('lootbox-ui-ready');
        initChestFeedback();
        initObservers();
        initNavbarDropdownFallback();
        initBootstrapAfterLoad();
    });
})();
