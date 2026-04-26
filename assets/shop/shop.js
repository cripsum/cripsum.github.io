
(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const page = document.body?.dataset.shopPage || 'shop';
    const favoritesEnabled = document.body?.dataset.favorites === '1';
    const favoritesKey = `cripsum:${page}:favorites`;

    let currentCategory = 'all';
    let favoritesOnly = false;
    let toastTimer = null;

    const getCards = () => $$('[data-product-card]');
    const getGrid = () => $('[data-shop-grid]');

    const getFavorites = () => {
        try {
            return JSON.parse(localStorage.getItem(favoritesKey) || '[]');
        } catch {
            return [];
        }
    };

    const setFavorites = (items) => {
        localStorage.setItem(favoritesKey, JSON.stringify([...new Set(items)]));
    };

    const isFavorite = (id) => getFavorites().includes(id);

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;'
    }[char]));

    const formatPrice = (value) => {
        const number = Number(value || 0);
        if (!number) return '';
        return number.toLocaleString('it-IT', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + '€';
    };

    const showToast = (message) => {
        const toast = $('[data-shop-toast]');
        if (!toast) return;

        toast.textContent = message;
        toast.classList.add('is-visible');

        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2200);
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

    const normalizeTargetUrl = (url) => {
        const raw = String(url || '').trim();
        if (!raw || raw === '#') return '';

        if (/^(https?:)?\/\//i.test(raw)) {
            return raw;
        }

        if (raw.startsWith('/')) {
            return raw;
        }

        return raw;
    };

    const goToCardLink = (card) => {
        if (!card) return;

        const link = normalizeTargetUrl(card.dataset.link || '');
        if (!link) {
            showToast('Non disponibile.');
            return;
        }

        window.location.href = link;
    };

    const isActionClick = (target) => {
        return Boolean(target.closest(
            'button, a, input, select, textarea, label, [data-open-detail], [data-favorite-toggle], [data-copy-link], [data-close-modal], [data-copy-current]'
        ));
    };

    const applyFavoritesVisual = () => {
        if (!favoritesEnabled) return;

        getCards().forEach((card) => {
            const id = card.dataset.id;
            const button = $('[data-favorite-toggle]', card);
            if (!button) return;

            const saved = isFavorite(id);
            button.classList.toggle('is-saved', saved);
            button.innerHTML = saved ? '<i class="fas fa-heart"></i>' : '<i class="far fa-heart"></i>';
        });
    };

    const applyFilters = () => {
        const search = (($('[data-shop-search]')?.value || '').trim()).toLowerCase();
        const sort = $('[data-shop-sort]')?.value || 'default';
        const grid = getGrid();
        const empty = $('[data-shop-empty]');

        if (!grid) return;

        let cards = getCards();

        cards.forEach((card) => {
            const name = (card.dataset.name || '').toLowerCase();
            const description = (card.dataset.description || '').toLowerCase();
            const category = card.dataset.category || '';
            const id = card.dataset.id || '';

            const matchesSearch = !search || name.includes(search) || description.includes(search);
            const matchesCategory = currentCategory === 'all' || category === currentCategory;
            const matchesFavorite = !favoritesOnly || isFavorite(id);
            const visible = matchesSearch && matchesCategory && matchesFavorite;

            card.hidden = !visible;
        });

        cards = cards.filter((card) => !card.hidden);

        if (sort !== 'default') {
            cards.sort((a, b) => {
                if (sort === 'name-asc') {
                    return (a.dataset.name || '').localeCompare(b.dataset.name || '');
                }

                if (sort === 'price-asc') {
                    return Number(a.dataset.price || 0) - Number(b.dataset.price || 0);
                }

                if (sort === 'price-desc') {
                    return Number(b.dataset.price || 0) - Number(a.dataset.price || 0);
                }

                return 0;
            });

            cards.forEach((card) => grid.appendChild(card));
        }

        if (empty) {
            empty.hidden = cards.length > 0;
        }
    };

    const toggleFavorite = (card) => {
        if (!favoritesEnabled || !card) return;

        const id = card.dataset.id;
        const favorites = getFavorites();
        const saved = favorites.includes(id);
        const next = saved
            ? favorites.filter((item) => item !== id)
            : [...favorites, id];

        setFavorites(next);
        applyFavoritesVisual();
        applyFilters();
        showToast(saved ? 'Rimosso dai preferiti.' : 'Salvato nei preferiti.');
    };

    const productUrl = (card) => {
        const id = card.dataset.id || '';
        return `${location.origin}${location.pathname}#${encodeURIComponent(id)}`;
    };

    const openModal = (card) => {
        const modal = $('[data-shop-modal]');
        const content = $('[data-modal-content]');

        if (!modal || !content || !card) return;

        const name = card.dataset.name || 'Prodotto';
        const description = card.dataset.description || '';
        const image = card.dataset.image || '';
        const link = normalizeTargetUrl(card.dataset.link || '');
        const price = formatPrice(card.dataset.price);
        const badge = card.dataset.badge || '';
        const isDownload = page === 'download';
        const isAvailable = Boolean(link);

        content.innerHTML = `
            <div class="shop-modal-product">
                <div class="shop-modal-product__image">
                    <img src="${escapeHtml(image)}" alt="${escapeHtml(name)}">
                </div>

                <div class="shop-modal-product__body">
                    ${badge ? `<span class="shop-kicker">${escapeHtml(badge)}</span>` : ''}
                    <h2>${escapeHtml(name)}</h2>
                    <p>${escapeHtml(description)}</p>
                    ${price ? `<strong class="shop-modal-product__price">${escapeHtml(price)}</strong>` : ''}

                    <div class="shop-modal-actions">
                        ${isAvailable ? `
                            <a class="shop-btn shop-btn--primary" href="${escapeHtml(link)}">
                                ${isDownload ? 'Scarica' : 'Acquista'}
                            </a>
                        ` : `<span class="shop-btn is-disabled">Non disponibile</span>`}

                        <button type="button" class="shop-btn shop-btn--ghost" data-copy-current>
                            Copia link
                        </button>
                    </div>
                </div>
            </div>
        `;

        $('[data-copy-current]', content)?.addEventListener('click', async () => {
            const ok = await copyText(productUrl(card));
            showToast(ok ? 'Link copiato.' : 'Non sono riuscito a copiare.');
        });

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
        const modal = $('[data-shop-modal]');
        if (!modal) return;

        modal.hidden = true;
        document.body.style.overflow = '';
    };

    const initFilters = () => {
        $('[data-shop-search]')?.addEventListener('input', applyFilters);
        $('[data-shop-sort]')?.addEventListener('change', applyFilters);

        $$('[data-category]').forEach((button) => {
            button.addEventListener('click', () => {
                favoritesOnly = false;
                currentCategory = button.dataset.category || 'all';

                $$('[data-category]').forEach((item) => {
                    item.classList.toggle('is-active', item === button);
                });

                applyFilters();
            });
        });

        $('[data-show-favorites]')?.addEventListener('click', () => {
            favoritesOnly = !favoritesOnly;
            currentCategory = 'all';

            $$('[data-category]').forEach((item) => {
                item.classList.toggle('is-active', item.dataset.category === 'all');
            });

            applyFilters();
            showToast(favoritesOnly ? 'Mostro solo i preferiti.' : 'Mostro tutti i prodotti.');
        });
    };

    const initCards = () => {
        getCards().forEach((card) => {
            const link = normalizeTargetUrl(card.dataset.link || '');

            if (link) {
                card.setAttribute('tabindex', '0');
                card.setAttribute('role', 'link');
                card.setAttribute('aria-label', `Apri ${card.dataset.name || 'prodotto'}`);
            }

            card.addEventListener('click', (event) => {
                if (isActionClick(event.target)) return;
                goToCardLink(card);
            });

            card.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                if (isActionClick(event.target)) return;

                event.preventDefault();
                goToCardLink(card);
            });

            $('[data-favorite-toggle]', card)?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                toggleFavorite(card);
            });

            $('[data-open-detail]', card)?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openModal(card);
            });

            $('[data-copy-link]', card)?.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();

                const direct = normalizeTargetUrl(card.dataset.link || '');
                const absolute = direct
                    ? new URL(direct, location.href).toString()
                    : productUrl(card);

                const ok = await copyText(absolute);
                showToast(ok ? 'Link copiato.' : 'Non sono riuscito a copiare.');
            });

            $$('.shop-card__actions a[href]', card).forEach((anchor) => {
                anchor.addEventListener('click', (event) => {
                    event.stopPropagation();

                    const href = anchor.getAttribute('href') || '';
                    if (!href || href === '#') {
                        event.preventDefault();
                        showToast('Non disponibile.');
                    }
                });
            });
        });
    };

    const initModal = () => {
        $$('[data-close-modal]').forEach((button) => button.addEventListener('click', closeModal));

        $('[data-shop-modal]')?.addEventListener('click', (event) => {
            if (event.target.matches('[data-shop-modal]')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeModal();
        });
    };

    const initReveal = () => {
        const items = $$('.shop-reveal');

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

    const initNavbarDropdownFallback = () => {
        const toggles = $$('[data-bs-toggle="dropdown"], .dropdown-toggle');

        toggles.forEach((toggle) => {
            if (toggle.dataset.shopDropdownBound === '1') return;

            toggle.dataset.shopDropdownBound = '1';

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

    const initHashOpen = () => {
        const id = decodeURIComponent((location.hash || '').replace('#', ''));
        if (!id) return;

        const card = getCards().find((item) => item.dataset.id === id);
        if (card) {
            setTimeout(() => openModal(card), 250);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        initNavbarDropdownFallback();
        initFilters();
        initCards();
        initModal();
        initReveal();
        applyFavoritesVisual();
        applyFilters();
        initHashOpen();
    });
})();
