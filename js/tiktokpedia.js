(() => {
    'use strict';

    if (window.__tiktokpediaV2Loaded) return;
    window.__tiktokpediaV2Loaded = true;

    const search = document.getElementById('tpSearch');
    const filters = Array.from(document.querySelectorAll('.tp-filter'));
    const cards = Array.from(document.querySelectorAll('.tp-card'));
    const count = document.getElementById('tpResultCount');
    const empty = document.getElementById('tpEmpty');
    const reset = document.getElementById('tpReset');
    const topBtn = document.getElementById('tpTop');
    const toast = document.getElementById('tpToast');

    let activeFilter = localStorage.getItem('tiktokpedia_filter') || 'all';
    let toastTimer = null;

    function normalize(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function showToast(message) {
        if (!toast) return;

        const text = toast.querySelector('span');
        if (text) text.textContent = message;

        toast.hidden = false;
        requestAnimationFrame(() => toast.classList.add('is-visible'));

        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toast.classList.remove('is-visible');
            setTimeout(() => { toast.hidden = true; }, 180);
        }, 1800);
    }

    async function copy(text) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }

        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.left = '-9999px';
        document.body.appendChild(area);
        area.select();

        try {
            document.execCommand('copy');
        } finally {
            area.remove();
        }
    }

    function applyFilters() {
        const query = normalize(search ? search.value : '');
        let visible = 0;

        cards.forEach((card) => {
            const haystack = normalize([
                card.dataset.name,
                card.dataset.description,
                card.dataset.tags,
            ].join(' '));

            const tags = normalize(card.dataset.tags || '').split(/\s+/);
            const matchFilter = activeFilter === 'all' || tags.includes(normalize(activeFilter));
            const matchSearch = !query || haystack.includes(query);
            const show = matchFilter && matchSearch;

            card.hidden = !show;
            if (show) visible += 1;
        });

        if (count) count.textContent = `${visible} ${visible === 1 ? 'voce' : 'voci'}`;
        if (empty) empty.hidden = visible !== 0;

        filters.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.filter === activeFilter);
        });
    }

    function initReveal() {
        const revealItems = Array.from(document.querySelectorAll('.tp-reveal, .tp-card'));

        if (!('IntersectionObserver' in window)) {
            revealItems.forEach((item) => item.classList.add('is-visible'));
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: .12 });

        revealItems.forEach((item) => observer.observe(item));
    }

    filters.forEach((button) => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.filter || 'all';
            localStorage.setItem('tiktokpedia_filter', activeFilter);
            applyFilters();
        });
    });

    if (search) search.addEventListener('input', applyFilters);

    if (reset) {
        reset.addEventListener('click', () => {
            activeFilter = 'all';
            localStorage.setItem('tiktokpedia_filter', activeFilter);
            if (search) search.value = '';
            applyFilters();
        });
    }

    document.querySelectorAll('[data-copy-link]').forEach((button) => {
        button.addEventListener('click', async () => {
            const card = button.closest('.tp-card');
            if (!card || !card.id) return;

            try {
                await copy(`${window.location.origin}${window.location.pathname}#${card.id}`);
                showToast('Link copiato');
            } catch {
                showToast('Copia non riuscita');
            }
        });
    });

    if (topBtn) {
        window.addEventListener('scroll', () => {
            topBtn.classList.toggle('is-visible', window.scrollY > 650);
        }, { passive: true });

        topBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!filters.some((button) => button.dataset.filter === activeFilter)) {
            activeFilter = 'all';
        }

        initReveal();
        applyFilters();
    });
})();
