(() => {
    'use strict';

    /**
     * Homepage, tema nuovo.
     *
     * Qui c'e' solo quello che la pagina nuova ha in piu': la galleria "Cosa
     * puoi fare". Il riscatto Premium, il contatore di Discord e la comparsa
     * allo scorrimento li fa ancora assets/home-v5/home.js, caricato insieme a
     * questo file.
     *
     * La galleria e' gia' tutta nell'HTML e si sfoglia da sola (scorrimento,
     * trascinamento, tastiera): questo script aggiunge le due frecce e tiene
     * aggiornata la barra di avanzamento.
     */
    const initGallery = () => {
        const gallery = document.getElementById('homeGallery');
        if (!gallery) return;

        const bar = document.getElementById('homeGalleryBar');
        const prev = document.getElementById('homeGalleryPrev');
        const next = document.getElementById('homeGalleryNext');
        const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)');

        const sync = () => {
            const max = gallery.scrollWidth - gallery.clientWidth;

            // La barra dice quanta parte della fila si e' gia' vista, non la
            // posizione: all'inizio e' gia' piena per le schede sullo schermo.
            bar?.style.setProperty('--p', String(Math.min(1, (gallery.scrollLeft + gallery.clientWidth) / gallery.scrollWidth)));

            if (prev) prev.disabled = gallery.scrollLeft <= 2;
            if (next) next.disabled = gallery.scrollLeft >= max - 2;
        };

        // Quasi una schermata alla volta: l'aggancio dello scorrimento ferma
        // poi sul bordo di una scheda.
        const page = (direction) => {
            gallery.scrollBy({
                left: direction * gallery.clientWidth * 0.8,
                behavior: reducedMotion?.matches ? 'auto' : 'smooth'
            });
        };

        prev?.addEventListener('click', () => page(-1));
        next?.addEventListener('click', () => page(1));
        gallery.addEventListener('scroll', sync, { passive: true });
        window.addEventListener('resize', sync);

        sync();
    };

    document.addEventListener('DOMContentLoaded', initGallery);
})();
