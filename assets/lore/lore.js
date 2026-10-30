/* L'indice arricchisce gli anchor HTML: il racconto resta leggibile senza JS. */
(() => {
    'use strict';

    const sections = Array.from(document.querySelectorAll('[data-lore-section]'));
    const links = Array.from(document.querySelectorAll('[data-lore-chapter]'));
    if (!sections.length || !links.length) return;

    let scheduled = false;
    let activeId = '';

    const update = () => {
        scheduled = false;
        // La navbar resta fissa: il capitolo attivo e' quello sotto la barra.
        const offset = 144;
        let current = sections[0];
        for (const section of sections) {
            if (section.getBoundingClientRect().top > offset) break;
            current = section;
        }
        if (current.id === activeId) return;
        activeId = current.id;
        for (const link of links) {
            if (link.dataset.loreChapter === activeId) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        }
    };

    const scheduleUpdate = () => {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(update);
    };

    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate);
    window.addEventListener('hashchange', scheduleUpdate);
    window.addEventListener('load', scheduleUpdate, { once: true });
    update();
})();
