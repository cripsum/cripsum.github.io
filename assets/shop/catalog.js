/*
 * Negozio, Merch e Download: filtri, ordinamento, scheda rapida, checkout
 * finto, download e conto alla rovescia dei drop.
 *
 * I dati arrivano dalla pagina in <script id="shop-data">: il PHP ha gia'
 * disegnato le card, qui si decide solo quali mostrare e in che ordine.
 * Lo stato dei filtri sta nell'indirizzo (?q=&cat=&sort=&p=), cosi' una
 * ricerca si puo' condividere e il tasto Indietro fa quello che ci si
 * aspetta.
 */
(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const readData = () => {
        try {
            return JSON.parse($('#shop-data')?.textContent || '{}');
        } catch {
            return {};
        }
    };

    const data = readData();
    const strings = data.strings || {};
    const lang = document.body?.dataset.shopLang === 'en' ? 'en' : 'it';
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[char]));

    // "Felpa" trova anche "félpa": si confrontano le lettere senza accenti.
    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();

    /* ── Toast e copia ────────────────────────────────────────────────── */

    let toastTimer = null;

    const toast = (message) => {
        const box = $('[data-shop-toast]');
        if (!box || !message) return;
        box.textContent = message;
        box.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => box.classList.remove('is-visible'), 2400);
    };

    const copyText = async (text) => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return true;
            }
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            const ok = document.execCommand('copy');
            area.remove();
            return ok;
        } catch {
            return false;
        }
    };

    /* ── Indirizzo della pagina ──────────────────────────────────────── */

    // replaceState e' intercettato dalla Rich Presence di Discord, che a ogni
    // chiamata manda un aggiornamento: mentre si scrive nella ricerca si
    // aspetta che la persona si fermi.
    let urlTimer = null;

    const writeParams = (params, immediate = false) => {
        clearTimeout(urlTimer);
        const apply = () => {
            const url = new URL(window.location.href);
            Object.entries(params).forEach(([key, value]) => {
                if (value === '' || value === null || value === undefined) url.searchParams.delete(key);
                else url.searchParams.set(key, value);
            });
            if (url.href !== window.location.href) {
                window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
            }
        };
        if (immediate) apply();
        else urlTimer = setTimeout(apply, 450);
    };

    /* ── Lista: filtri e ordinamento ─────────────────────────────────── */

    const initList = () => {
        const grid = $('[data-shop-grid]');
        if (!grid) return;

        const items = new Map((data.products || []).map((item) => [item.slug, item]));
        const cards = $$('[data-shop-item]', grid);
        const search = $('[data-shop-search]');
        const clear = $('[data-shop-search-clear]');
        const sort = $('[data-shop-sort]');
        const filters = $$('[data-category]');
        const results = $('[data-shop-results]');
        const empty = $('[data-shop-empty]');
        const toolbar = $('[data-shop-toolbar]');
        const defaultSort = sort?.options[0]?.value || 'featured';

        const params = new URL(window.location.href).searchParams;
        const state = {
            q: params.get('q') || '',
            cat: params.get('cat') || '',
            sort: params.get('sort') || defaultSort,
        };

        if (!filters.some((button) => button.dataset.category === state.cat)) state.cat = '';
        if (sort && !Array.from(sort.options).some((option) => option.value === state.sort)) state.sort = defaultSort;

        const comparators = {
            featured: (a, b) => (b.featured - a.featured) || (a.position - b.position),
            popular: (a, b) => (b.orders - a.orders) || (a.position - b.position),
            'price-asc': (a, b) => (a.price - b.price) || (a.position - b.position),
            'price-desc': (a, b) => (b.price - a.price) || (a.position - b.position),
            name: (a, b) => a.name.localeCompare(b.name, lang, { sensitivity: 'base' }),
            recent: (a, b) => (b.created - a.created) || (a.position - b.position),
        };

        const render = () => {
            const query = normalize(state.q);
            const compare = comparators[state.sort] || comparators.featured;

            // Si riordina sempre, anche tornando all'ordine di partenza: prima
            // "Ordine originale" lasciava le card dove le aveva messe
            // l'ultimo ordinamento.
            const sorted = cards.slice().sort((a, b) => {
                const itemA = items.get(a.dataset.shopItem);
                const itemB = items.get(b.dataset.shopItem);
                return itemA && itemB ? compare(itemA, itemB) : 0;
            });

            let visible = 0;
            sorted.forEach((card) => {
                const item = items.get(card.dataset.shopItem);
                const haystack = normalize(`${item?.name || ''} ${item?.variant || ''} ${item?.description || ''}`);
                const show = (!query || haystack.includes(query)) && (!state.cat || item?.category === state.cat);
                card.hidden = !show;
                if (show) visible++;
                grid.appendChild(card);
            });

            if (empty) empty.hidden = visible > 0;
            if (results) {
                const filtered = query !== '' || state.cat !== '';
                results.textContent = filtered
                    ? (visible === 1 ? strings.resultsOne : String(strings.resultsMany || '%d').replace('%d', visible))
                    : '';
            }
            if (clear) clear.hidden = state.q === '';

            filters.forEach((button) => {
                const active = button.dataset.category === state.cat;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        };

        const sync = (immediate = false) => {
            render();
            writeParams({
                q: state.q.trim(),
                cat: state.cat,
                sort: state.sort === defaultSort ? '' : state.sort,
            }, immediate);
        };

        if (search) {
            search.value = state.q;
            search.addEventListener('input', () => {
                state.q = search.value;
                sync();
            });
            search.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && search.value) {
                    event.stopPropagation();
                    state.q = '';
                    search.value = '';
                    sync(true);
                }
            });
        }

        clear?.addEventListener('click', () => {
            state.q = '';
            if (search) {
                search.value = '';
                search.focus();
            }
            sync(true);
        });

        if (sort) {
            sort.value = state.sort;
            sort.addEventListener('change', () => {
                state.sort = sort.value;
                sync(true);
            });
        }

        filters.forEach((button) => button.addEventListener('click', () => {
            state.cat = button.dataset.category || '';
            sync(true);
        }));

        $$('[data-shop-reset]').forEach((button) => button.addEventListener('click', () => {
            state.q = '';
            state.cat = '';
            if (search) search.value = '';
            sync(true);
            search?.focus();
        }));

        // La barra si "stacca" dallo sfondo solo quando resta attaccata in
        // alto, altrimenti sarebbe un riquadro dentro un riquadro.
        if (toolbar) {
            let ticking = false;
            const check = () => {
                ticking = false;
                const top = parseFloat(getComputedStyle(toolbar).top) || 0;
                const rect = toolbar.getBoundingClientRect();
                const panel = toolbar.parentElement?.getBoundingClientRect();
                toolbar.classList.toggle('is-stuck', rect.top <= top + 1 && (!panel || panel.top < top - 4));
            };
            window.addEventListener('scroll', () => {
                if (!ticking) {
                    ticking = true;
                    requestAnimationFrame(check);
                }
            }, { passive: true });
            check();
        }

        render();
    };

    /* ── Scheda rapida dei prodotti ──────────────────────────────────── */

    const initQuickView = () => {
        if (data.kind !== 'products') return;

        const modal = $('[data-shop-modal]');
        const content = $('[data-modal-content]');
        const panel = modal?.querySelector('.shop-modal__panel');
        if (!modal || !content || !panel) return;

        const items = new Map((data.products || []).map((item) => [item.slug, item]));
        let lastTrigger = null;

        const pageUrlFor = (slug) => {
            const url = new URL(window.location.href);
            url.search = '';
            url.hash = '';
            url.searchParams.set('p', slug);
            return url.toString();
        };

        const open = (slug, trigger = null) => {
            const item = items.get(slug);
            if (!item) return;

            lastTrigger = trigger || document.activeElement;

            const sizes = (item.sizes || []).map((size, index) => `
                <label class="shop-size">
                    <input type="radio" name="quick-size" value="${escapeHtml(size)}" ${index === 0 && item.sizes.length === 1 ? 'checked' : ''}>
                    <span>${escapeHtml(size)}</span>
                </label>`).join('');

            content.innerHTML = `
                <div class="shop-quick">
                    <div class="shop-quick__image">
                        ${item.image ? `<img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}">` : '<i class="fa-solid fa-box-open shop-card__placeholder"></i>'}
                    </div>
                    <div class="shop-quick__body">
                        ${item.badge ? `<span class="shop-kicker">${escapeHtml(item.badge)}</span>` : ''}
                        <div>
                            <h2 id="shop-modal-title">${escapeHtml(item.name)}</h2>
                            ${item.variant ? `<span class="shop-variant">${escapeHtml(item.variant)}</span>` : ''}
                        </div>
                        ${item.description ? `<p>${escapeHtml(item.description)}</p>` : ''}
                        <div class="shop-quick__price">
                            <strong>${escapeHtml(item.priceLabel)}</strong>
                            ${item.fullPriceLabel ? `<s>${escapeHtml(item.fullPriceLabel)}</s>` : ''}
                        </div>
                        ${sizes ? `<div><div class="shop-field"><span>${escapeHtml(strings.size)}</span></div><div class="shop-sizes" role="radiogroup">${sizes}</div></div>` : ''}
                        <div class="shop-quick__actions">
                            <a class="shop-btn shop-btn--primary" href="${escapeHtml(item.buyUrl)}" data-quick-buy>
                                <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> ${escapeHtml(strings.buy)}
                            </a>
                            <button type="button" class="shop-btn shop-btn--ghost" data-quick-copy>
                                <i class="fa-solid fa-link" aria-hidden="true"></i> ${escapeHtml(strings.copyLink)}
                            </button>
                        </div>
                    </div>
                </div>`;

            // La taglia scelta qui arriva gia' selezionata nel checkout.
            const buy = $('[data-quick-buy]', content);
            $$('input[name="quick-size"]', content).forEach((input) => input.addEventListener('change', () => {
                const url = new URL(item.buyUrl, window.location.origin);
                url.searchParams.set('taglia', input.value);
                buy.href = url.pathname + url.search;
            }));

            $('[data-quick-copy]', content)?.addEventListener('click', async () => {
                const ok = await copyText(pageUrlFor(item.slug));
                toast(ok ? strings.linkCopied : strings.copyFailed);
            });

            modal.hidden = false;
            document.documentElement.style.overflow = 'hidden';
            writeParams({ p: item.slug }, true);
            requestAnimationFrame(() => panel.focus({ preventScroll: true }));
        };

        const close = () => {
            if (modal.hidden) return;
            modal.hidden = true;
            content.innerHTML = '';
            document.documentElement.style.overflow = '';
            writeParams({ p: '' }, true);
            if (lastTrigger && typeof lastTrigger.focus === 'function') {
                lastTrigger.focus({ preventScroll: true });
            }
        };

        $$('[data-open-product]').forEach((button) => {
            button.addEventListener('click', () => open(button.dataset.openProduct, button));
        });

        $$('[data-close-modal]', modal).forEach((button) => button.addEventListener('click', close));

        document.addEventListener('keydown', (event) => {
            if (modal.hidden) return;
            if (event.key === 'Escape') {
                close();
                return;
            }
            // Il Tab resta dentro la scheda finche' e' aperta.
            if (event.key === 'Tab') {
                const focusable = $$('a[href], button:not([disabled]), input:not([disabled])', panel).filter((el) => el.offsetParent !== null);
                if (!focusable.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        // Un link condiviso (?p=felpa-big-logo) apre subito il prodotto.
        const requested = new URL(window.location.href).searchParams.get('p');
        if (requested && items.has(requested)) {
            const card = document.getElementById(`p-${requested}`);
            card?.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
            setTimeout(() => open(requested, $('[data-open-product]', card || document)), reduceMotion ? 0 : 250);
        }
    };

    /* ── Checkout finto ──────────────────────────────────────────────── */

    const initCheckout = () => {
        const form = $('[data-shop-checkout]');
        if (!form) return;

        const qty = $('[data-qty]', form);
        const subtotal = $('[data-subtotal]', form);
        const total = $('[data-total]', form);
        const error = $('[data-checkout-error]', form);
        const submit = $('[data-checkout-submit]', form);
        const unit = Number(qty?.dataset.unitPrice || 0);

        const money = (value) => value.toLocaleString(lang === 'en' ? 'en-IE' : 'it-IT', {
            style: 'currency',
            currency: 'EUR',
        });

        const clampQty = () => {
            const value = Math.min(99, Math.max(1, parseInt(qty.value, 10) || 1));
            qty.value = String(value);
            return value;
        };

        const updateTotals = () => {
            if (!qty) return;
            const amount = money(unit * clampQty());
            if (subtotal) subtotal.textContent = amount;
            if (total) total.textContent = amount;
        };

        $$('[data-qty-step]', form).forEach((button) => button.addEventListener('click', () => {
            qty.value = String((parseInt(qty.value, 10) || 1) + Number(button.dataset.qtyStep));
            updateTotals();
        }));
        qty?.addEventListener('change', updateTotals);
        qty?.addEventListener('input', () => {
            if (qty.value !== '') updateTotals();
        });

        const showError = (message) => {
            if (!error) return;
            error.textContent = message;
            error.hidden = !message;
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            showError('');
            form.classList.add('was-validated');

            const sizeInputs = $$('input[name="taglia"]', form);
            if (sizeInputs.length && !sizeInputs.some((input) => input.checked)) {
                showError(strings.chooseSize);
                sizeInputs[0].focus();
                return;
            }

            if (!form.checkValidity()) {
                const firstInvalid = $$('input', form).find((input) => !input.checkValidity());
                firstInvalid?.focus();
                firstInvalid?.reportValidity?.();
                return;
            }

            const label = submit?.querySelector('span');
            submit?.classList.add('is-loading');
            if (label) label.textContent = strings.processing;

            const csrf = $('input[name="csrf_token"]', form)?.value || '';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({
                        csrf_token: csrf,
                        p: $('input[name="p"]', form)?.value || '',
                        lang,
                        qty: clampQty(),
                        taglia: sizeInputs.find((input) => input.checked)?.value || '',
                    }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.ok) throw new Error(payload.message || strings.orderError);
                window.location.assign(payload.redirect || `/${lang}/confirm`);
            } catch (err) {
                showError(err.message || strings.orderError);
                submit?.classList.remove('is-loading');
                if (label) label.textContent = strings.placeOrder;
            }
        });

        updateTotals();
    };

    /* ── Download ────────────────────────────────────────────────────── */

    const initDownloads = () => {
        $$('[data-download-go]').forEach((link) => link.addEventListener('click', () => {
            if (link.target !== '_blank') toast(strings.downloadStarted);
        }));

        $$('[data-copy-page]').forEach((button) => button.addEventListener('click', async () => {
            const url = new URL(window.location.href);
            url.search = '';
            url.hash = '';
            const ok = await copyText(url.toString());
            toast(ok ? strings.linkCopied : strings.copyFailed);
        }));
    };

    /* ── Conto alla rovescia dei drop ────────────────────────────────── */

    const initCountdowns = () => {
        const blocks = $$('[data-countdown]');
        if (!blocks.length) return;

        const units = lang === 'en' ? ['d', 'h', 'm', 's'] : ['g', 'h', 'm', 's'];

        const format = (ms) => {
            const total = Math.max(0, Math.floor(ms / 1000));
            const parts = [Math.floor(total / 86400), Math.floor(total / 3600) % 24, Math.floor(total / 60) % 60, total % 60];
            const start = parts.findIndex((value) => value > 0);
            return parts
                .map((value, index) => `${index > 0 ? String(value).padStart(2, '0') : value}${units[index]}`)
                .slice(start < 0 ? 3 : Math.min(start, 2))
                .join(' ');
        };

        let reloaded = false;
        const tick = () => {
            blocks.forEach((block) => {
                const target = Date.parse(block.dataset.countdown || '');
                const output = block.matches('[data-countdown-output]') ? block : $('[data-countdown-output]', block);
                if (!output || Number.isNaN(target)) return;

                const left = target - Date.now();
                output.textContent = format(left);

                // Il drop e' uscito: la pagina ricaricata mostra i prodotti.
                if (left <= 0 && !reloaded && block.classList.contains('shop-soon')) {
                    reloaded = true;
                    setTimeout(() => window.location.reload(), 1200);
                }
            });
        };

        tick();
        setInterval(tick, 1000);
    };

    const start = () => {
        initList();
        initQuickView();
        initCheckout();
        initDownloads();
        initCountdowns();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
