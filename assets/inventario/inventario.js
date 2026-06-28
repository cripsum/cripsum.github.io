(() => {
    'use strict';

    const lang = location.pathname.split('/').find(s => s === 'it' || s === 'en') || 'it';

    const t = {
        it: {
            date_locale:        'it-IT',
            rarity: {
                comune:             'Comune',
                raro:               'Raro',
                epico:              'Epico',
                leggendario:        'Leggendario',
                speciale:           'Speciale',
                segreto:            'Segreto',
                segreto_limited:    'Segreto Limitato',
                theone:             'THE ONE'
            },
            unknown:            '???',
            not_found:          'Non trovato',
            found_on:           (date) => `Trovato il ${date}`,
            open_character:     (name) => `Apri ${name}`,
            no_description:     'Nessuna descrizione disponibile.',
            traits_title:       'Tratti distintivi',
            no_traits:          'Nessun tratto specificato.',
            view_animation:     'Visualizza animazione',
            results:            (n) => `${n} ${n === 1 ? 'risultato' : 'risultati'}`,
            tab_details:        'Dettagli',
            tab_upgrade:        'Potenziamento',
            abilities_title:    'Abilità Personaggio',
            ability_passive:    'Passiva',
            ability_special:    'Speciale',
            ability_ultimate:   'Ultimate',
            no_passive:         'Nessun effetto passivo speciale.',
            default_special_desc: 'Un potente attacco speciale.',
            default_ultimate_desc: 'Una mossa finale devastante.',
            cost_cost:          'Costo'
        },
        en: {
            date_locale:        'en-GB',
            rarity: {
                comune:             'Common',
                raro:               'Rare',
                epico:              'Epic',
                leggendario:        'Legendary',
                speciale:           'Special',
                segreto:            'Secret',
                segreto_limited:    'Limited Secret',
                theone:             'THE ONE'
            },
            unknown:            '???',
            not_found:          'Not found',
            found_on:           (date) => `Found on ${date}`,
            open_character:     (name) => `Open ${name}`,
            no_description:     'No description available.',
            traits_title:       'Distinctive traits',
            no_traits:          'No traits specified.',
            view_animation:     'View animation',
            results:            (n) => `${n} ${n === 1 ? 'result' : 'results'}`,
            tab_details:        'Details',
            tab_upgrade:        'Upgrade',
            abilities_title:    'Character Abilities',
            ability_passive:    'Passive',
            ability_special:    'Special',
            ability_ultimate:   'Ultimate',
            no_passive:         'No special passive effect.',
            default_special_desc: 'A powerful special attack.',
            default_ultimate_desc: 'A devastating finishing move.',
            cost_cost:          'Cost'
        }
    }[lang];

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const API = {
        inventory: 'https://cripsum.com/api/api_get_inventario',
        characterCount: 'https://cripsum.com/api/api_get_characters_num',
        allCharacters: 'https://cripsum.com/api/get_all_characters',
        openedBoxes: 'https://cripsum.com/api/get_casse_aperte'
    };

    const rarityOrder = ['comune', 'raro', 'epico', 'leggendario', 'speciale', 'segreto', 'segreto_limited', 'theone'];

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

    // Aggiunto il controllo per la lingua inglese nei campi nome, descrizione e caratteristiche
    const getName = (item) => String(
        (lang === 'en' && getField(item, ['nome_en', 'name_en'])) || 
        getField(item, ['nome', 'name'], '')
    );

    const getDescription = (item) => String(
        (lang === 'en' && getField(item, ['descrizione_en', 'description_en'])) || 
        getField(item, ['descrizione', 'description'], '')
    );

    const getTraits = (item) => String(
        (lang === 'en' && getField(item, ['caratteristiche_en', 'traits_en'])) || 
        getField(item, ['caratteristiche', 'traits'], '')
    );

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
            return date.toLocaleDateString(t.date_locale);
        }

        return `${date.toLocaleDateString(t.date_locale)} · ${date.toLocaleTimeString(t.date_locale, {
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
                level: owned ? toInt(owned.livello ?? owned.level ?? 1) : 1,
                stats: owned ? owned.stats : null,
                stats_next: owned ? owned.stats_next : null,
                required_next: owned ? toInt(owned.required_next) : 0,
                owned: Boolean(owned),
                raw: source
            };
        });
    };

    const isSecretLimited = (entry) => entry.rarity === 'segreto' && normalize(entry.category) === 'limited';

    const getDisplayRarity = (entry) => isSecretLimited(entry) ? 'segreto_limited' : entry.rarity;

    const visibleBaseEntries = () => {
        return state.entries.filter((entry) => {
            const displayRarity = getDisplayRarity(entry);
            const foundInGroup = state.entries.some((item) => getDisplayRarity(item) === displayRarity && item.owned);

            if ((displayRarity === 'segreto' || displayRarity === 'segreto_limited' || displayRarity === 'theone') && !foundInGroup) {
                return false;
            }

            return true;
        });
    };

    const filteredEntries = () => {
        const query = normalize(state.filters.search);

        let list = visibleBaseEntries();

        if (state.filters.rarity !== 'all') {
            if (state.filters.rarity === 'segreto_limited') {
                list = list.filter((entry) => isSecretLimited(entry));
            } else if (state.filters.rarity === 'segreto') {
                list = list.filter((entry) => entry.rarity === 'segreto' && !isSecretLimited(entry));
            } else {
                list = list.filter((entry) => entry.rarity === state.filters.rarity);
            }
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
            const key = getDisplayRarity(entry);
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(entry);
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
        const baseEntries = visibleBaseEntries().filter((entry) => getDisplayRarity(entry) === rarity);
        const found = baseEntries.filter((entry) => entry.owned).length;
        const total = baseEntries.length;

        return `
            <div class="rarity-title">
                <strong>${escapeHtml(t.rarity[rarity] || rarity)}</strong>
                <span>${found} / ${total}</span>
            </div>
        `;
    };

    const renderCard = (entry) => {
        const isDuplicate = entry.quantity > 1;
        const rarityLabel = t.rarity[entry.rarity] || entry.rarity;
        const image = resolveImage(entry.image, entry.owned);
        const date = entry.owned ? formatDate(entry.date) : '';
        const displayName = entry.owned ? entry.name : t.unknown;

        return `
            <article class="character-card rarity-${escapeHtml(entry.rarity)}${isSecretLimited(entry) ? ' rarity-segreto_limited' : ''} ${entry.owned ? 'is-owned' : 'is-missing'} ${entry.owned && entry.level === 6 ? 'is-max-level' : ''}"
                     data-character-id="${entry.id}"
                     ${entry.owned ? 'tabindex="0" role="button"' : ''}
                     aria-label="${entry.owned ? escapeHtml(t.open_character(entry.name)) : t.not_found}">
                <div class="character-image-wrap">
                    <img src="${escapeHtml(image)}"
                         class="character-image"
                         alt="${escapeHtml(displayName)}"
                         loading="lazy"
                         onerror="this.src='${fallbackImage}'">

                    <span class="character-count ${isDuplicate ? 'is-duplicate' : ''}">
                        ${entry.owned ? `x${entry.quantity}` : '?'}
                    </span>
                    ${entry.owned ? `<span class="character-level-badge">Lv. ${entry.level === 6 ? 'MAX' : entry.level}</span>` : ''}
                </div>

                <div class="character-info">
                    <h3 class="character-name">${escapeHtml(displayName)}</h3>

                    <div class="character-meta">
                        <span class="character-badge character-badge--rarity">${escapeHtml(rarityLabel)}</span>
                        ${entry.stats && entry.stats.role ? `<span class="character-badge character-badge--role" style="background: rgba(47, 107, 255, 0.12); border-color: rgba(47, 107, 255, 0.3); color: #82baff; font-weight: bold;">${escapeHtml(entry.stats.role)}</span>` : ''}
                        ${entry.category ? `<span class="character-badge">${escapeHtml(entry.category)}</span>` : ''}
                    </div>

                    <span class="character-date">
                        ${entry.owned && date ? escapeHtml(t.found_on(date)) : t.not_found}
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
            visibleCount.textContent = t.results(renderedCount);
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

    const confirmTexts = {
        it: {
            title: "Conferma Potenziamento",
            cancel: "Annulla",
            confirm: "Potenzia",
            confirmMsg: (copies, name, nextLvl) => `Sei sicuro di voler consumare <strong>${copies}</strong> di <strong>${name}</strong> per potenziarlo al livello <strong>${nextLvl}</strong>?`
        },
        en: {
            title: "Confirm Upgrade",
            cancel: "Cancel",
            confirm: "Upgrade",
            confirmMsg: (copies, name, nextLvl) => `Are you sure you want to consume <strong>${copies}</strong> of <strong>${name}</strong> to upgrade them to level <strong>${nextLvl}</strong>?`
        }
    }[lang] || {
        title: "Conferma Potenziamento",
        cancel: "Annulla",
        confirm: "Potenzia",
        confirmMsg: (copies, name, nextLvl) => `Sei sicuro di voler consumare <strong>${copies}</strong> di <strong>${name}</strong> per potenziarlo al livello <strong>${nextLvl}</strong>?`
    };

    const showCustomConfirm = (message, onConfirm) => {
        const modal = $('#confirmUpgradeModal');
        const text = $('#confirmUpgradeText');
        const btnCancel = $('#btnCancelUpgrade');
        const btnConfirm = $('#btnConfirmUpgrade');
        const backdrop = $('#closeConfirmUpgrade');

        if (!modal || !text || !btnConfirm || !btnCancel) return;

        text.innerHTML = message;
        modal.hidden = false;
        modal.classList.remove('is-leaving');
        // Forza il reflow del browser per avviare l'animazione di entrata
        void modal.offsetWidth;
        modal.classList.add('is-active');

        const cleanup = () => {
            modal.classList.remove('is-active');
            modal.classList.add('is-leaving');
            
            btnConfirm.removeEventListener('click', handleConfirm);
            btnCancel.removeEventListener('click', cleanup);
            if (backdrop) backdrop.removeEventListener('click', cleanup);

            setTimeout(() => {
                modal.hidden = true;
                modal.classList.remove('is-leaving');
            }, 250);
        };

        const handleConfirm = () => {
            onConfirm();
            cleanup();
        };

        btnConfirm.addEventListener('click', handleConfirm);
        btnCancel.addEventListener('click', cleanup);
        if (backdrop) backdrop.addEventListener('click', cleanup);
    };

    const triggerLevelUpEffects = (panel) => {
        if (!panel) return;
        const imgWrap = panel.querySelector('.modal-character__image');
        if (imgWrap) {
            // Onde d'urto radiali
            const shockwave = document.createElement('div');
            shockwave.className = 'level-up-shockwave';
            imgWrap.appendChild(shockwave);
            setTimeout(() => shockwave.remove(), 800);

            // Particelle magiche
            const particleCount = 24;
            for (let i = 0; i < particleCount; i++) {
                const p = document.createElement('div');
                p.className = 'level-up-particle';
                
                const angle = Math.random() * Math.PI * 2;
                const speed = 40 + Math.random() * 110;
                const tx = Math.cos(angle) * speed;
                const ty = Math.sin(angle) * speed;
                
                p.style.setProperty('--tx', `${tx}px`);
                p.style.setProperty('--ty', `${ty}px`);
                
                const size = 5 + Math.random() * 7;
                p.style.width = `${size}px`;
                p.style.height = `${size}px`;
                p.style.animationDelay = `${Math.random() * 0.12}s`;
                
                imgWrap.appendChild(p);
                setTimeout(() => p.remove(), 950);
            }
        }
    };

    const openCharacterModal = (entry, defaultTab = 'info') => {
        if (!entry || !entry.owned) return;

        const modal = $('#characterModal');
        const content = $('#characterModalContent');
        if (!modal || !content) return;

        const rarityLabel = t.rarity[entry.rarity] || entry.rarity;
        const image = resolveImage(entry.image, true);
        const traits = entry.traits
            ? entry.traits.split(';').map((trait) => trait.trim()).filter(Boolean)
            : [];

        const statsNow = entry.stats || {};
        const statsNext = entry.stats_next || {};

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
                            <i class="fa-solid fa-box"></i>
                            x${entry.quantity}
                        </span>

                        ${statsNow.role ? `
                            <span class="character-badge" style="background: rgba(47, 107, 255, 0.12); border-color: rgba(47, 107, 255, 0.3); color: #82baff; font-weight: bold;">
                                <i class="fa-solid fa-shield-halved"></i>
                                ${escapeHtml(statsNow.role)}
                            </span>
                        ` : ''}

                        ${entry.category ? `
                            <span class="character-badge">
                                <i class="fa-solid fa-tag"></i>
                                ${escapeHtml(entry.category)}
                            </span>
                        ` : ''}

                        ${entry.date ? `
                            <span class="character-badge">
                                <i class="fa-solid fa-calendar-check"></i>
                                ${escapeHtml(formatDate(entry.date, true))}
                            </span>
                        ` : ''}
                    </div>

                    <!-- Navigation Tabs -->
                    <div class="modal-tabs" style="display: flex; gap: 0.5rem; margin: 1.25rem 0 1rem; border-bottom: 1px solid var(--inv-border); padding-bottom: 0.5rem;">
                        <button type="button" class="modal-tab-btn ${defaultTab === 'info' ? 'active' : ''}" data-tab="info" style="flex: 1; padding: 0.6rem; border: none; background: none; color: var(--inv-muted); font-weight: 700; font-size: 0.88rem; cursor: pointer; transition: all 0.2s ease; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                            <i class="fa-solid fa-circle-info"></i>
                            ${t.tab_details}
                        </button>
                        <button type="button" class="modal-tab-btn ${defaultTab === 'upgrade' ? 'active' : ''}" data-tab="upgrade" style="flex: 1; padding: 0.6rem; border: none; background: none; color: var(--inv-muted); font-weight: 700; font-size: 0.88rem; cursor: pointer; transition: all 0.2s ease; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 0.4rem; position: relative;">
                            <i class="fa-solid fa-angles-up"></i>
                            ${t.tab_upgrade}
                            ${Math.max(0, entry.quantity - 1) >= entry.required_next && entry.level < 6 ? `
                                <span class="tab-ready-indicator" style="position: absolute; top: 6px; right: 12px; width: 8px; height: 8px; background: var(--inv-green); border-radius: 50%; box-shadow: 0 0 6px var(--inv-green);"></span>
                            ` : ''}
                        </button>
                    </div>

                    <!-- TAB 1: INFO & KIT -->
                    <div class="modal-tab-content ${defaultTab === 'info' ? 'active' : ''}" id="tab-info-content" style="${defaultTab === 'info' ? 'display: block;' : 'display: none;'}">
                        <p>${escapeHtml(entry.description || t.no_description)}</p>

                        <div class="modal-character__traits">
                            <strong>${t.traits_title}</strong><br>
                            ${traits.length ? traits.map((trait) => `- ${escapeHtml(trait)}`).join('<br>') : t.no_traits}
                        </div>

                        <!-- Sezione Kit Personaggio Duello -->
                        ${statsNow.role ? `
                        <div class="character-kit" style="margin-top: 1.25rem; padding: 1.1rem; background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 14px;">
                            <h4 style="margin: 0 0 0.85rem; font-size: 0.95rem; color: var(--inv-gold); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 0.5rem; font-weight: 800;">
                                <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--inv-gold);"></i>
                                ${t.abilities_title}
                            </h4>
                            
                            <div class="kit-ability" style="margin-bottom: 0.85rem;">
                                <div style="font-weight: 700; color: var(--inv-text); font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="background: rgba(139, 92, 246, 0.16); border: 1px solid rgba(139, 92, 246, 0.3); color: #c084fc; padding: 2px 6px; border-radius: 6px; font-size: 0.72rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">${t.ability_passive}</span>
                                    ${escapeHtml(statsNow.passive_name || (lang === 'en' ? 'None' : 'Nessuna'))}
                                </div>
                                <div style="font-size: 0.82rem; color: var(--inv-muted); margin-top: 4px; line-height: 1.45;">
                                    ${escapeHtml(statsNow.passive_desc || t.no_passive)}
                                </div>
                            </div>
                            
                            <div class="kit-ability">
                                <div style="font-weight: 700; color: var(--inv-text); font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="background: rgba(47, 107, 255, 0.16); border: 1px solid rgba(47, 107, 255, 0.3); color: #60a5fa; padding: 2px 6px; border-radius: 6px; font-size: 0.72rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">${t.ability_special}</span>
                                    ${escapeHtml(statsNow.special_name || (lang === 'en' ? 'Special Strike' : 'Colpo Speciale'))}
                                    <span style="font-size: 0.75rem; color: var(--inv-muted-2); font-weight: normal; margin-left: auto;">
                                        ${t.cost_cost}: <strong>${statsNow.special_cost || 0} E</strong> · CD: <strong>${statsNow.special_cooldown || 0}t</strong>
                                    </span>
                                </div>
                                <div style="font-size: 0.82rem; color: var(--inv-muted); margin-top: 4px; line-height: 1.45;">
                                    ${escapeHtml(statsNow.special_desc || t.default_special_desc)}
                                </div>
                            </div>
                            
                            ${statsNow.ultimate_name ? `
                            <div class="kit-ability" style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px dashed rgba(255, 255, 255, 0.08);">
                                <div style="font-weight: 700; color: var(--inv-text); font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="background: rgba(212, 175, 55, 0.16); border: 1px solid rgba(212, 175, 55, 0.3); color: #fbbf24; padding: 2px 6px; border-radius: 6px; font-size: 0.72rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">${t.ability_ultimate}</span>
                                    ${escapeHtml(statsNow.ultimate_name)}
                                </div>
                                <div style="font-size: 0.82rem; color: var(--inv-muted); margin-top: 4px; line-height: 1.45;">
                                    ${escapeHtml(statsNow.ultimate_desc || t.default_ultimate_desc)}
                                </div>
                            </div>
                            ` : ''}
                        </div>
                        ` : ''}

                        <div class="modal-character__actions" style="margin-top: 1.5rem;">
                            <a class="inv-btn inv-btn--primary" href="animazione_personaggio?id_personaggio=${encodeURIComponent(entry.id)}" style="width: 100%; justify-content: center;">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                <span>${t.view_animation}</span>
                            </a>
                        </div>
                    </div>

                    <!-- TAB 2: UPGRADE -->
                    <div class="modal-tab-content ${defaultTab === 'upgrade' ? 'active' : ''}" id="tab-upgrade-content" style="${defaultTab === 'upgrade' ? 'display: block;' : 'display: none;'}">
                        <div class="character-progression" style="margin-top: 0;">
                            <div class="progression-header">
                                <span class="progression-level">Livello: <strong class="progression-level-val">${entry.level === 6 ? 'MAX' : entry.level}</strong></span>
                                ${entry.level < 6 ? `
                                    <span class="progression-copies">Duplicati: <strong>${Math.max(0, entry.quantity - 1)} / ${entry.required_next}</strong></span>
                                ` : `
                                    <span class="progression-copies progression-copies--max">MAX</span>
                                `}
                            </div>
                            
                            ${entry.level < 6 ? `
                                <div class="progression-bar">
                                    <div class="progression-bar-fill" style="width: ${Math.min(100, (Math.max(0, entry.quantity - 1) / entry.required_next) * 100)}%"></div>
                                </div>
                            ` : ''}

                            <div class="progression-stats">
                                <div class="progression-stat-row">
                                    <span class="stat-name">HP</span>
                                    <span class="stat-value">${statsNow.hp || 0}</span>
                                    ${statsNext.hp ? `<span class="stat-arrow">→</span><span class="stat-value-next">${statsNext.hp}</span>` : ''}
                                </div>
                                <div class="progression-stat-row">
                                    <span class="stat-name">ATK</span>
                                    <span class="stat-value">${statsNow.attack || 0}</span>
                                    ${statsNext.attack ? `<span class="stat-arrow">→</span><span class="stat-value-next">${statsNext.attack}</span>` : ''}
                                </div>
                                <div class="progression-stat-row">
                                    <span class="stat-name">DEF</span>
                                    <span class="stat-value">${statsNow.defense || 0}</span>
                                    ${statsNext.defense ? `<span class="stat-arrow">→</span><span class="stat-value-next">${statsNext.defense}</span>` : ''}
                                </div>
                                <div class="progression-stat-row">
                                    <span class="stat-name">SPD</span>
                                    <span class="stat-value">${statsNow.speed || 0}</span>
                                    ${statsNext.speed ? `<span class="stat-arrow">→</span><span class="stat-value-next">${statsNext.speed}</span>` : ''}
                                </div>
                            </div>

                            <div class="progression-upgrade-action">
                                ${entry.level < 6 ? `
                                    <button type="button" class="inv-btn inv-btn--upgrade" id="btnUpgradeCharacter" ${Math.max(0, entry.quantity - 1) < entry.required_next ? 'disabled' : ''}>
                                        <i class="fa-solid fa-angles-up"></i>
                                        <span>Potenzia</span>
                                    </button>
                                    ${Math.max(0, entry.quantity - 1) < entry.required_next ? `
                                        <small class="progression-hint">Ti mancano ${entry.required_next - Math.max(0, entry.quantity - 1)} copie duplicate per sbloccare il prossimo livello.</small>
                                    ` : `
                                        <small class="progression-hint progression-hint--ready">Pronto per il potenziamento!</small>
                                    `}
                                ` : `
                                    <div class="progression-max-badge">
                                        <i class="fa-solid fa-crown"></i>
                                        <span>POTENZA MASSIMA RAGGIUNTA</span>
                                    </div>
                                `}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Gestione Tab cliccabili
        const tabBtns = $$('.modal-tab-btn', content);
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                tabBtns.forEach(b => {
                    b.classList.remove('active');
                    b.style.color = 'var(--inv-muted)';
                    b.style.background = 'none';
                });
                btn.classList.add('active');
                btn.style.color = 'var(--inv-text)';
                btn.style.background = 'rgba(255, 255, 255, 0.04)';

                $$('.modal-tab-content', content).forEach(c => {
                    c.style.display = 'none';
                    c.classList.remove('active');
                });
                
                const tabId = btn.dataset.tab;
                const tabContent = content.querySelector(`#tab-${tabId}-content`);
                if (tabContent) {
                    tabContent.style.display = 'block';
                    void tabContent.offsetWidth;
                    tabContent.classList.add('active');
                }
            });
        });

        // Configura il colore iniziale del tab attivo
        const activeBtn = content.querySelector('.modal-tab-btn.active');
        if (activeBtn) {
            activeBtn.style.color = 'var(--inv-text)';
            activeBtn.style.background = 'rgba(255, 255, 255, 0.04)';
        }

        const btnUpgrade = $('#btnUpgradeCharacter');
        if (btnUpgrade) {
            btnUpgrade.addEventListener('click', () => {
                const copiesText = entry.required_next === 1 ? (lang === 'en' ? '1 copy' : '1 copia') : `${entry.required_next} ${lang === 'en' ? 'copies' : 'copie'}`;
                const nextLvlText = entry.level + 1 === 6 ? 'MAX' : entry.level + 1;
                const message = confirmTexts.confirmMsg(copiesText, entry.name, nextLvlText);
                
                showCustomConfirm(message, async () => {
                    btnUpgrade.disabled = true;
                    try {
                        const res = await fetch('/api/game/upgrade_character.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ character_id: entry.id })
                        });
                        const data = await res.json();
                        if (data.ok) {
                            const panel = $('.inv-modal__panel');
                            if (panel) {
                                panel.classList.add('level-up-animating');
                                triggerLevelUpEffects(panel);
                                
                                const lvlUpTxt = document.createElement('div');
                                lvlUpTxt.className = 'level-up-text';
                                lvlUpTxt.textContent = 'LEVEL UP!';
                                panel.appendChild(lvlUpTxt);
                                
                                setTimeout(() => {
                                    panel.classList.remove('level-up-animating');
                                    lvlUpTxt.remove();
                                }, 1200);
                            }
                            
                            // Mostra i popup di sblocco degli achievement ricevuti
                            if (Array.isArray(data.unlocked_achievements)) {
                                data.unlocked_achievements.forEach((achId) => {
                                    if (typeof window.showAchievementPopup === 'function') {
                                        window.showAchievementPopup(achId);
                                    }
                                });
                            }

                            // Update local state entry
                            const localEntry = state.entries.find(item => String(item.id) === String(entry.id));
                            if (localEntry) {
                                localEntry.level = data.level;
                                localEntry.quantity = data.quantity;
                                localEntry.required_next = data.required_next;
                                localEntry.stats = data.stats;
                                localEntry.stats_next = data.stats_next;
                            }

                            // Update only the card DOM in the grid
                            const cardEl = document.querySelector(`.character-card[data-character-id="${entry.id}"]`);
                            if (cardEl && localEntry) {
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(renderCard(localEntry), 'text/html');
                                const newCardEl = doc.body.firstElementChild;
                                newCardEl.classList.add('is-visible');
                                cardEl.replaceWith(newCardEl);
                                
                                newCardEl.addEventListener('click', () => {
                                    openCharacterModal(localEntry);
                                });
                                newCardEl.addEventListener('keydown', (event) => {
                                    if (event.key === 'Enter' || event.key === ' ') {
                                        event.preventDefault();
                                        openCharacterModal(localEntry);
                                    }
                                });
                            }
                            
                            if (localEntry) {
                                setTimeout(() => openCharacterModal(localEntry, 'upgrade'), 500);
                            } else {
                                closeCharacterModal();
                            }
                        } else {
                            alert(data.message || 'Errore durante il potenziamento');
                            btnUpgrade.disabled = false;
                        }
                    } catch (e) {
                        alert('Errore durante il potenziamento: ' + e.message);
                        btnUpgrade.disabled = false;
                    }
                });
            });
        }

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
                status: 'owned',
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
        const confirmModalHtml = `
            <div class="inv-modal" id="confirmUpgradeModal" hidden>
                <div class="inv-modal__backdrop" id="closeConfirmUpgrade"></div>
                <article class="inv-modal__panel inv-modal__panel--confirm" role="dialog">
                    <div class="confirm-upgrade-content">
                        <div class="confirm-upgrade-icon"><i class="fa-solid fa-circle-question"></i></div>
                        <h3>${confirmTexts.title}</h3>
                        <p id="confirmUpgradeText"></p>
                        <div class="confirm-upgrade-actions">
                            <button type="button" class="inv-btn inv-btn--soft" id="btnCancelUpgrade">${confirmTexts.cancel}</button>
                            <button type="button" class="inv-btn inv-btn--primary" id="btnConfirmUpgrade">${confirmTexts.confirm}</button>
                        </div>
                    </div>
                </article>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', confirmModalHtml);

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