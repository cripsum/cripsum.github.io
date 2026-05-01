(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const API = {
        inventory: 'https://cripsum.com/api/api_get_inventario',
        characterCount: 'https://cripsum.com/api/api_get_characters_num',
        allCharacters: 'https://cripsum.com/api/get_all_characters',
        openedBoxes: 'https://cripsum.com/api/get_casse_aperte'
    };

    const rarityOrder = ['comune', 'raro', 'epico', 'leggendario', 'speciale', 'segreto', 'theone'];

    const rarityLabels = {
        comune: 'Comune',
        raro: 'Raro',
        epico: 'Epico',
        leggendario: 'Leggendario',
        speciale: 'Speciale',
        segreto: 'Segreto',
        theone: 'THE ONE'
    };

    const state = {
        inventory: [],
        allCharacters: [],
        entries: [],
        filters: {
            search: localStorage.getItem('cripsum:inventory:search') || '',
            rarity: localStorage.getItem('cripsum:inventory:rarity') || 'all',
            status: localStorage.getItem('cripsum:inventory:status') || 'all',
            sort: localStorage.getItem('cripsum:inventory:sort') || 'default'
        }
    };

    const fallbackImage = '../img/boh.png';

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;'
    }[char]));

    const normalize = (value) => String(value ?? '').toLowerCase().trim();

    const toInt = (value, fallback = 0) => {
        const number = Number.parseInt(value, 10);
        return Number.isFinite(number) ? number : fallback;
    };

    const getField = (item, fields, fallback = '') => {
        for (const field of fields) {
            if (item && item[field] !== undefined && item[field] !== null && item[field] !== '') {
                return item[field];
            }
        }

        return fallback;
    };

    const getId = (item) => toInt(getField(item, ['id', 'personaggio_id', 'character_id'], 0));

    const getName = (item) => String(getField(item, ['nome', 'name'], ''));

    const normalizeRarityValue = (value) => {
        const rarity = normalize(value)
            .replaceAll(' ', '')
            .replaceAll('_', '')
            .replaceAll('-', '');

        const map = {
            comune: 'comune',
            raro: 'raro',
            epico: 'epico',
            leggendario: 'leggendario',
            speciale: 'speciale',
            segreto: 'segreto',
            theone: 'theone',
            one: 'theone'
        };

        return map[rarity] || rarity || 'comune';
    };

    const getRarity = (item) => normalizeRarityValue(getField(item, ['rarità', 'rarita', 'rarity'], 'comune'));

    const getQuantity = (item) => Math.max(0, toInt(getField(item, ['quantità', 'quantita', 'quantity'], 0)));

    const getImage = (item) => String(getField(item, ['img_url', 'img', 'image'], ''));

    const getCategory = (item) => String(getField(item, ['categoria', 'category'], ''));

    const getDescription = (item) => String(getField(item, ['descrizione', 'description'], ''));

    const getTraits = (item) => String(getField(item, ['caratteristiche', 'traits'], ''));

    const getDate = (item) => String(getField(item, ['data', 'created_at', 'ottenuto_il'], ''));

    const resolveImage = (image, owned) => {
        const raw = String(image || '').trim();

        if (!owned) return fallbackImage;
        if (!raw) return fallbackImage;
        if (/^(https?:)?\/\//i.test(raw)) return raw;
        if (raw.startsWith('/') || raw.startsWith('../') || raw.startsWith('./')) return raw;

        return `/img/${raw}`;
    };

    const formatDate = (value, withTime = false) => {
        if (!value) return '';

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';

        if (!withTime) {
            return date.toLocaleDateString('it-IT');
        }

        return `${date.toLocaleDateString('it-IT')} · ${date.toLocaleTimeString('it-IT', {
            hour: '2-digit',
            minute: '2-digit'
        })}`;
    };

    const safeJson = async (response, fallback) => {
        try {
            if (!response || !response.ok) return fallback;
            return await response.json();
        } catch {
            return fallback;
        }
    };

    const fetchJson = async (url, fallback) => {
        const response = await fetch(url);
        return safeJson(response, fallback);
    };

    const animateNumber = (element, targetNumber, suffix = '') => {
        if (!element) return;

        const target = Math.max(0, toInt(targetNumber));
        const steps = 36;
        const increment = target / steps;
        let current = 0;
        let tick = 0;

        const timer = setInterval(() => {
            tick += 1;
            current += increment;

            if (tick >= steps || current >= target) {
                current = target;
                clearInterval(timer);
            }

            element.textContent = `${Math.floor(current)}${suffix}`;
        }, 20);
    };

    const mergeInventory = (allCharacters, inventory) => {
        const byId = new Map();
        const byName = new Map();

        inventory.forEach((item) => {
            const id = getId(item);
            const name = normalize(getName(item));

            if (id) byId.set(id, item);
            if (name) byName.set(name, item);
        });

        return allCharacters.map((base, index) => {
            const id = getId(base);
            const name = getName(base);
            const owned = (id && byId.get(id)) || byName.get(normalize(name)) || null;
            const source = owned || base;
            const rarity = getRarity(source) || getRarity(base);

            return {
                index,
                id: id || getId(owned),
                name,
                rarity,
                category: getCategory(source) || getCategory(base),
                image: getImage(source) || getImage(base),
                description: getDescription(source) || getDescription(base),
                traits: getTraits(source) || getTraits(base),
                date: getDate(owned),
                quantity: owned ? getQuantity(owned) || 1 : 0,
                owned: Boolean(owned),
                raw: source
            };
        });
    };

    const visibleBaseEntries = () => {
        return state.entries.filter((entry) => {
            const foundInRarity = state.entries.some((item) => item.rarity === entry.rarity && item.owned);

            if ((entry.rarity === 'segreto' || entry.rarity === 'theone') && !foundInRarity) {
                return false;
            }

            return true;
        });
    };

    const filteredEntries = () => {
        const query = normalize(state.filters.search);

        let list = visibleBaseEntries();

        if (state.filters.rarity !== 'all') {
            list = list.filter((entry) => entry.rarity === state.filters.rarity);
        }

        if (state.filters.status === 'owned') {
            list = list.filter((entry) => entry.owned);
        }

        if (state.filters.status === 'missing') {
            list = list.filter((entry) => !entry.owned);
        }

        if (state.filters.status === 'duplicates') {
            list = list.filter((entry) => entry.owned && entry.quantity > 1);
        }

        if (query) {
            list = list.filter((entry) => {
                const haystack = normalize(`${entry.name} ${entry.rarity} ${entry.category} ${entry.description}`);
                return haystack.includes(query);
            });
        }

        const rarityRank = (rarity) => {
            const index = rarityOrder.indexOf(rarity);
            return index === -1 ? 999 : index;
        };

        list.sort((a, b) => {
            switch (state.filters.sort) {
                case 'name':
                    return a.name.localeCompare(b.name);

                case 'rarity':
                    return rarityRank(a.rarity) - rarityRank(b.rarity) || a.index - b.index;

                case 'quantity-desc':
                    return b.quantity - a.quantity || a.index - b.index;

                case 'quantity-asc':
                    return a.quantity - b.quantity || a.index - b.index;

                default:
                    return a.index - b.index;
            }
        });

        return list;
    };

    const groupByRarity = (entries) => {
        const groups = new Map();

        rarityOrder.forEach((rarity) => groups.set(rarity, []));
        entries.forEach((entry) => {
            if (!groups.has(entry.rarity)) groups.set(entry.rarity, []);
            groups.get(entry.rarity).push(entry);
        });

        return groups;
    };

    const renderStats = (openedBoxes, totalCharactersNum) => {
        const foundCharacters = state.inventory.length;
        const total = toInt(totalCharactersNum) || state.allCharacters.length;
        const completionRate = total > 0 ? Math.round((foundCharacters / total) * 100) : 0;

        animateNumber($('#casseAperteNumber'), openedBoxes);
        animateNumber($('#foundCharacters'), foundCharacters);
        animateNumber($('#totalCharactersNum'), total);
        animateNumber($('#completionRate'), completionRate, '%');
    };

    const renderRarityTitle = (rarity, entries) => {
        const baseEntries = visibleBaseEntries().filter((entry) => entry.rarity === rarity);
        const found = baseEntries.filter((entry) => entry.owned).length;
        const total = baseEntries.length;

        return `
            <div class="rarity-title">
                <strong>${escapeHtml(rarityLabels[rarity] || rarity)}</strong>
                <span>${found} / ${total}</span>
            </div>
        `;
    };

    const renderCard = (entry) => {
        const isDuplicate = entry.quantity > 1;
        const rarityLabel = rarityLabels[entry.rarity] || entry.rarity;
        const image = resolveImage(entry.image, entry.owned);
        const date = entry.owned ? formatDate(entry.date) : '';
        const displayName = entry.owned ? entry.name : '???';

        return `
            <article class="character-card rarity-${escapeHtml(entry.rarity)} ${entry.owned ? 'is-owned' : 'is-missing'}"
                     data-character-id="${entry.id}"
                     ${entry.owned ? 'tabindex="0" role="button"' : ''}
                     aria-label="${entry.owned ? `Apri ${escapeHtml(entry.name)}` : 'Personaggio non trovato'}">
                <div class="character-image-wrap">
                    <img src="${escapeHtml(image)}"
                         class="character-image"
                         alt="${escapeHtml(displayName)}"
                         loading="lazy"
                         onerror="this.src='${fallbackImage}'">

                    <span class="character-count ${isDuplicate ? 'is-duplicate' : ''}">
                        ${entry.owned ? `x${entry.quantity}` : '?'}
                    </span>
                </div>

                <div class="character-info">
                    <h3 class="character-name">${escapeHtml(displayName)}</h3>

                    <div class="character-meta">
                        <span class="character-badge character-badge--rarity">${escapeHtml(rarityLabel)}</span>
                        ${entry.category ? `<span class="character-badge">${escapeHtml(entry.category)}</span>` : ''}
                    </div>

                    <span class="character-date">
                        ${entry.owned && date ? `Trovato il ${escapeHtml(date)}` : 'Non trovato'}
                    </span>
                </div>
            </article>
        `;
    };

    const renderInventory = () => {
        const container = $('#inventario');
        const empty = $('#inventoryEmpty');
        const visibleCount = $('#visibleCount');

        if (!container) return;

        const list = filteredEntries();
        const groups = groupByRarity(list);
        let html = '';
        let renderedCount = 0;

        groups.forEach((items, rarity) => {
            if (!items.length) return;

            renderedCount += items.length;
            html += `
                <section class="rarity-section rarity-${escapeHtml(rarity)}">
                    ${renderRarityTitle(rarity, items)}
                    <div class="characters-grid">
                        ${items.map(renderCard).join('')}
                    </div>
                </section>
            `;
        });

        container.innerHTML = html;
        container.hidden = renderedCount === 0;
        empty.hidden = renderedCount !== 0;

        if (visibleCount) {
            visibleCount.textContent = `${renderedCount} ${renderedCount === 1 ? 'risultato' : 'risultati'}`;
        }

        $$('.character-card.is-owned', container).forEach((card, index) => {
            window.setTimeout(() => card.classList.add('is-visible'), index * 24);

            card.addEventListener('click', () => {
                const entry = state.entries.find((item) => String(item.id) === String(card.dataset.characterId));
                if (entry) openCharacterModal(entry);
            });

            card.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;

                event.preventDefault();
                const entry = state.entries.find((item) => String(item.id) === String(card.dataset.characterId));
                if (entry) openCharacterModal(entry);
            });
        });

        $$('.character-card.is-missing', container).forEach((card, index) => {
            window.setTimeout(() => card.classList.add('is-visible'), index * 18);
        });
    };

    const openCharacterModal = (entry) => {
        if (!entry || !entry.owned) return;

        const modal = $('#characterModal');
        const content = $('#characterModalContent');
        if (!modal || !content) return;

        const rarityLabel = rarityLabels[entry.rarity] || entry.rarity;
        const image = resolveImage(entry.image, true);
        const traits = entry.traits
            ? entry.traits.split(';').map((trait) => trait.trim()).filter(Boolean)
            : [];

        content.innerHTML = `
            <div class="modal-character rarity-${escapeHtml(entry.rarity)}">
                <div class="modal-character__image">
                    <img src="${escapeHtml(image)}"
                         alt="${escapeHtml(entry.name)}"
                         onerror="this.src='${fallbackImage}'">
                </div>

                <div class="modal-character__body">
                    <span class="character-badge character-badge--rarity">${escapeHtml(rarityLabel)}</span>
                    <h2 id="characterModalTitle">${escapeHtml(entry.name)}</h2>

                    <div class="character-meta">
                        <span class="character-badge">
                            <i class="fas fa-box"></i>
                            x${entry.quantity}
                        </span>

                        ${entry.category ? `
                            <span class="character-badge">
                                <i class="fas fa-tag"></i>
                                ${escapeHtml(entry.category)}
                            </span>
                        ` : ''}

                        ${entry.date ? `
                            <span class="character-badge">
                                <i class="fas fa-calendar-check"></i>
                                ${escapeHtml(formatDate(entry.date, true))}
                            </span>
                        ` : ''}
                    </div>

                    <p>${escapeHtml(entry.description || 'Nessuna descrizione disponibile.')}</p>

                    <div class="modal-character__traits">
                        <strong>Tratti distintivi</strong><br>
                        ${traits.length ? traits.map((trait) => `- ${escapeHtml(trait)}`).join('<br>') : 'Nessun tratto specificato.'}
                    </div>

                    <div class="modal-character__actions">
                        <a class="inv-btn inv-btn--primary" href="animazione_personaggio?id_personaggio=${encodeURIComponent(entry.id)}">
                            <i class="fas fa-wand-magic-sparkles"></i>
                            <span>Visualizza animazione</span>
                        </a>
                    </div>
                </div>
            </div>
        `;

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    const closeCharacterModal = () => {
        const modal = $('#characterModal');
        if (!modal) return;

        modal.hidden = true;
        document.body.style.overflow = '';
    };

    const saveFilters = () => {
        localStorage.setItem('cripsum:inventory:search', state.filters.search);
        localStorage.setItem('cripsum:inventory:rarity', state.filters.rarity);
        localStorage.setItem('cripsum:inventory:status', state.filters.status);
        localStorage.setItem('cripsum:inventory:sort', state.filters.sort);
    };

    const syncControls = () => {
        $('#inventorySearch').value = state.filters.search;
        $('#rarityFilter').value = state.filters.rarity;
        $('#statusFilter').value = state.filters.status;
        $('#inventorySort').value = state.filters.sort;
    };

    const initControls = () => {
        const search = $('#inventorySearch');
        const rarity = $('#rarityFilter');
        const status = $('#statusFilter');
        const sort = $('#inventorySort');
        const reset = $('#resetInventoryFilters');

        syncControls();

        search?.addEventListener('input', () => {
            state.filters.search = search.value;
            saveFilters();
            renderInventory();
        });

        rarity?.addEventListener('change', () => {
            state.filters.rarity = rarity.value;
            saveFilters();
            renderInventory();
        });

        status?.addEventListener('change', () => {
            state.filters.status = status.value;
            saveFilters();
            renderInventory();
        });

        sort?.addEventListener('change', () => {
            state.filters.sort = sort.value;
            saveFilters();
            renderInventory();
        });

        reset?.addEventListener('click', () => {
            state.filters = {
                search: '',
                rarity: 'all',
                status: 'all',
                sort: 'default'
            };

            saveFilters();
            syncControls();
            renderInventory();
        });
    };

    const initModal = () => {
        $$('[data-close-character-modal]').forEach((button) => {
            button.addEventListener('click', closeCharacterModal);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeCharacterModal();
        });
    };

    const initReveal = () => {
        const items = $$('.inv-reveal');

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
            if (toggle.dataset.invDropdownBound === '1') return;

            toggle.dataset.invDropdownBound = '1';

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

    const loadInventory = async () => {
        const loading = $('#inventoryLoading');
        const error = $('#inventoryError');

        try {
            const [inventory, allCharacters, openedBoxesData, characterCountData] = await Promise.all([
                fetchJson(API.inventory, []),
                fetchJson(API.allCharacters, []),
                fetchJson(API.openedBoxes, { total: 0 }),
                fetchJson(API.characterCount, 0)
            ]);

            state.inventory = Array.isArray(inventory) ? inventory : [];
            state.allCharacters = Array.isArray(allCharacters) ? allCharacters : [];

            localStorage.setItem('inventory', JSON.stringify(state.inventory));

            const openedBoxes = typeof openedBoxesData === 'number'
                ? openedBoxesData
                : toInt(openedBoxesData.total ?? openedBoxesData.count ?? 0);

            const characterCount = typeof characterCountData === 'number'
                ? characterCountData
                : toInt(characterCountData.total ?? characterCountData.count ?? state.allCharacters.length);

            state.entries = mergeInventory(state.allCharacters, state.inventory);

            renderStats(openedBoxes, characterCount);
            renderInventory();

            loading.hidden = true;
            error.hidden = true;
            $('#inventario').hidden = false;
        } catch (err) {
            console.error('Errore inventario:', err);
            loading.hidden = true;
            error.hidden = false;
            $('#inventario').hidden = true;
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.body.classList.add('rarity-animations-ready');
        initNavbarDropdownFallback();
        initReveal();
        initControls();
        initModal();
        loadInventory();
    });

    if (window.__invCustomSelectLoaded) return;
    window.__invCustomSelectLoaded = true;

    const refreshInvCustomSelects = () => {
        document.querySelectorAll('[data-inv-custom-select]').forEach((wrap) => {
            const select = wrap.querySelector('select');
            const current = wrap.querySelector('.inv-select-current');
            const options = Array.from(wrap.querySelectorAll('.inv-select-menu [data-value]'));

            if (!select || !current || !options.length) return;

            const realOption =
                Array.from(select.options).find((option) => option.value === select.value) ||
                select.options[0];

            if (!realOption) return;

            current.textContent = realOption.textContent.trim();

            options.forEach((button) => {
                const active = button.dataset.value === realOption.value;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        });
    };

    const initInvCustomSelect = () => {
        document.querySelectorAll('[data-inv-custom-select]').forEach((wrap) => {
            if (wrap.dataset.bound === '1') return;
            wrap.dataset.bound = '1';

            const select = wrap.querySelector('select');
            const trigger = wrap.querySelector('.inv-select-trigger');
            const current = wrap.querySelector('.inv-select-current');
            const options = Array.from(wrap.querySelectorAll('.inv-select-menu [data-value]'));

            if (!select || !trigger || !current || !options.length) return;

            const sync = (value, emit = false) => {
                const realOption =
                    Array.from(select.options).find((option) => option.value === value) ||
                    select.options[0];

                if (!realOption) return;

                select.value = realOption.value;
                current.textContent = realOption.textContent.trim();

                options.forEach((button) => {
                    const active = button.dataset.value === realOption.value;
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                if (emit) {
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
            };

            trigger.addEventListener('click', (event) => {
                event.stopPropagation();

                document.querySelectorAll('[data-inv-custom-select].is-open').forEach((other) => {
                    if (other === wrap) return;

                    other.classList.remove('is-open');
                    other.querySelector('.inv-select-trigger')?.setAttribute('aria-expanded', 'false');
                });

                const isOpen = wrap.classList.toggle('is-open');
                trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            options.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.stopPropagation();

                    sync(button.dataset.value, true);

                    wrap.classList.remove('is-open');
                    trigger.setAttribute('aria-expanded', 'false');
                });
            });

            select.addEventListener('change', () => {
                sync(select.value, false);
            });

            sync(select.value || options[0].dataset.value, false);
        });

        document.addEventListener('click', () => {
            document.querySelectorAll('[data-inv-custom-select].is-open').forEach((wrap) => {
                wrap.classList.remove('is-open');
                wrap.querySelector('.inv-select-trigger')?.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;

            document.querySelectorAll('[data-inv-custom-select].is-open').forEach((wrap) => {
                wrap.classList.remove('is-open');
                wrap.querySelector('.inv-select-trigger')?.setAttribute('aria-expanded', 'false');
            });
        });

        document.querySelector('#resetInventoryFilters')?.addEventListener('click', () => {
            window.setTimeout(refreshInvCustomSelects, 0);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInvCustomSelect, { once: true });
    } else {
        initInvCustomSelect();
    }
})();