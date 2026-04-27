(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const state = {
        all: [],
        unlocked: [],
        serverData: {
            casseAperte: 0,
            clickGoon: 0,
            inventory: [],
            personaggiCount: 0
        },
        statusFilter: localStorage.getItem('cripsum:achievements:status') || 'all',
        sort: localStorage.getItem('cripsum:achievements:sort') || 'default',
        search: ''
    };

    let toastTimer = null;

    const fallbackIcon = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
        <svg xmlns="http://www.w3.org/2000/svg" width="300" height="300" viewBox="0 0 300 300">
            <defs>
                <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
                    <stop offset="0%" stop-color="#2f6bff"/>
                    <stop offset="100%" stop-color="#8b5cf6"/>
                </linearGradient>
            </defs>
            <rect width="300" height="300" rx="42" fill="#0a0e1a"/>
            <circle cx="150" cy="126" r="52" fill="url(#g)" opacity=".95"/>
            <path d="M92 224l16-70h84l16 70-58-34-58 34z" fill="#f7f8ff" opacity=".92"/>
        </svg>
    `);

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

    const safeJson = async (response, fallback) => {
        try {
            if (!response || !response.ok) return fallback;
            return await response.json();
        } catch {
            return fallback;
        }
    };

    const safeParseCookie = (value) => {
        if (!value) return null;

        try {
            return JSON.parse(decodeURIComponent(value));
        } catch {
            try {
                return JSON.parse(value);
            } catch {
                return value;
            }
        }
    };

    const getCookie = (name) => {
        const cookies = document.cookie ? document.cookie.split('; ') : [];

        for (const cookie of cookies) {
            const [key, ...parts] = cookie.split('=');
            if (key === name) {
                return safeParseCookie(parts.join('='));
            }
        }

        return null;
    };

    const formatDate = (value) => {
        if (!value) return '';

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';

        return `${date.toLocaleDateString('it-IT')} · ${date.toLocaleTimeString('it-IT', {
            hour: '2-digit',
            minute: '2-digit'
        })}`;
    };

    const resolveImage = (image) => {
        const raw = String(image || '').trim();

        if (!raw) return fallbackIcon;
        if (/^(https?:)?\/\//i.test(raw)) return raw;
        if (raw.startsWith('/') || raw.startsWith('../') || raw.startsWith('./')) return raw;

        return `../img/${raw}`;
    };

    const normalizeAchievement = (achievement) => ({
        id: toInt(achievement.id),
        nome: String(achievement.nome || achievement.name || `Achievement #${achievement.id || '?'}`),
        descrizione: String(achievement.descrizione || achievement.description || ''),
        punti: toInt(achievement.punti || achievement.points || 0),
        img_url: String(achievement.img_url || achievement.img || ''),
        categoria: String(achievement.categoria || achievement.category || 'generale')
    });

    const normalizeUnlocked = (item) => {
        if (typeof item === 'number' || typeof item === 'string') {
            return {
                id: toInt(item),
                data: null
            };
        }

        return {
            id: toInt(item.id || item.achievement_id),
            data: item.data || item.created_at || item.unlocked_at || null,
            ...item
        };
    };

    const findUnlocked = (achievementId) => {
        const id = toInt(achievementId);
        return state.unlocked.find((item) => toInt(item.id) === id) || null;
    };

    const showToast = (message) => {
        const toast = $('#achievementToast');
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

    const getServerData = async () => {
        try {
            const [casseRes, clickRes, inventoryRes] = await Promise.all([
                fetch('../api/get_casse_aperte.php').catch(() => null),
                fetch('../api/get_clickgoon.php').catch(() => null),
                fetch('../api/api_get_inventario.php').catch(() => null)
            ]);

            const casseData = await safeJson(casseRes, 0);
            const clickData = await safeJson(clickRes, 0);
            const inventoryData = await safeJson(inventoryRes, []);

            const casseAperte = typeof casseData === 'number'
                ? casseData
                : toInt(casseData.total ?? casseData.casse_aperte ?? casseData.count ?? 0);

            const clickGoon = typeof clickData === 'number'
                ? clickData
                : toInt(clickData.total ?? clickData.clickgoon ?? clickData.count ?? 0);

            const inventory = Array.isArray(inventoryData)
                ? inventoryData
                : Array.isArray(inventoryData.inventory)
                    ? inventoryData.inventory
                    : [];

            return {
                casseAperte,
                clickGoon,
                inventory,
                personaggiCount: inventory.length
            };
        } catch {
            return {
                casseAperte: 0,
                clickGoon: 0,
                inventory: [],
                personaggiCount: 0
            };
        }
    };

    const getTimeSpent = () => toInt(getCookie('timeSpent'), 0);
    const getArrayCookieLength = (name) => {
        const value = getCookie(name);
        return Array.isArray(value) ? value.length : 0;
    };

    const formatTime = (seconds) => {
        const safeSeconds = Math.max(0, toInt(seconds));
        const hours = Math.floor(safeSeconds / 3600);
        const minutes = Math.floor((safeSeconds % 3600) / 60);

        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
    };

    const completedProgress = (id, label) => {
        const unlocked = Boolean(findUnlocked(id));

        return {
            current: unlocked ? 1 : 0,
            target: 1,
            display: unlocked ? 'Completato' : label
        };
    };

    const calculateProgress = (achievement) => {
        switch (toInt(achievement.id)) {
            case 14: {
                const timeSpent = getTimeSpent();
                return {
                    current: timeSpent,
                    target: 7200,
                    display: `${formatTime(timeSpent)} / ${formatTime(7200)}`
                };
            }

            case 13:
                return {
                    current: getArrayCookieLength('daysVisited'),
                    target: 30
                };

            case 17:
                return {
                    current: getArrayCookieLength('watchedVideos'),
                    target: 29
                };

            case 21:
                return {
                    current: state.unlocked.length,
                    target: 20
                };

            case 12: {
                const now = new Date();
                const isRightTime = now.getHours() === 3;

                return isRightTime
                    ? { current: 1, target: 1, display: 'Visita ora' }
                    : { current: 0, target: 1, display: 'Visita alle 3:00' };
            }

            case 2:
                return completedProgress(2, 'Cambia pfp');

            case 4:
                return completedProgress(4, 'Fai una donazione');

            case 6:
                return completedProgress(6, 'Guarda gli edit');

            case 7:
                return completedProgress(7, 'Compra qualcosa nello shop di Tussi');

            case 3:
                return completedProgress(3, 'Vinci una partita');

            case 10:
                return completedProgress(10, 'Riscatta V-Bucks gratis');

            case 11:
                return completedProgress(11, 'Rimani senza soldi');

            case 5:
                return completedProgress(5, 'Apri la tua prima lootbox');

            case 8: {
                const casseAperte = state.serverData.casseAperte || 0;
                return {
                    current: Math.min(casseAperte, 100),
                    target: 100,
                    display: `${casseAperte}/100 casse`
                };
            }

            case 15:
                return completedProgress(15, 'Esplora GoonLand');

            case 16: {
                const casseAperte = state.serverData.casseAperte || 0;
                return {
                    current: Math.min(casseAperte, 500),
                    target: 500,
                    display: `${casseAperte}/500 casse`
                };
            }

            case 9: {
                const comuniDiFila = toInt(getCookie('comuniDiFila'), 0);
                const unlocked = Boolean(findUnlocked(9));

                return {
                    current: unlocked ? 10 : Math.min(comuniDiFila, 10),
                    target: 10,
                    display: unlocked ? 'Completato' : `${comuniDiFila}/10 comuni consecutivi`
                };
            }

            case 18: {
                const personaggiCount = state.serverData.personaggiCount || 0;
                const totalCharacters = 134;

                return {
                    current: Math.min(personaggiCount, totalCharacters),
                    target: totalCharacters,
                    display: `${personaggiCount}/${totalCharacters} personaggi`
                };
            }

            case 19: {
                const clickGoon = state.serverData.clickGoon || 0;
                const unlocked = Boolean(findUnlocked(19));

                return {
                    current: unlocked ? 100 : Math.min(clickGoon, 100),
                    target: 100,
                    display: unlocked ? 'Completato' : `${clickGoon}/100 click`
                };
            }

            case 20: {
                const daysVisitedGoon = getArrayCookieLength('daysVisitedGoon');

                return {
                    current: daysVisitedGoon,
                    target: 10,
                    display: `${daysVisitedGoon}/10 giorni`
                };
            }

            default:
                return null;
        }
    };

    const progressPercent = (progress) => {
        if (!progress || !progress.target) return 0;
        return Math.min((progress.current / progress.target) * 100, 100);
    };

    const getFilteredAchievements = () => {
        const query = normalize(state.search);

        let list = state.all.map((achievement, originalIndex) => {
            const unlocked = findUnlocked(achievement.id);
            return {
                ...achievement,
                originalIndex,
                unlocked,
                isUnlocked: Boolean(unlocked),
                progress: calculateProgress(achievement)
            };
        });

        if (state.statusFilter === 'unlocked') {
            list = list.filter((achievement) => achievement.isUnlocked);
        }

        if (state.statusFilter === 'locked') {
            list = list.filter((achievement) => !achievement.isUnlocked);
        }

        if (query) {
            list = list.filter((achievement) => {
                const haystack = normalize(`${achievement.nome} ${achievement.descrizione} ${achievement.categoria}`);
                return haystack.includes(query);
            });
        }

        list.sort((a, b) => {
            switch (state.sort) {
                case 'name':
                    return a.nome.localeCompare(b.nome);

                case 'points-desc':
                    return b.punti - a.punti;

                case 'points-asc':
                    return a.punti - b.punti;

                case 'unlocked-first':
                    return Number(b.isUnlocked) - Number(a.isUnlocked);

                case 'locked-first':
                    return Number(a.isUnlocked) - Number(b.isUnlocked);

                default:
                    return a.originalIndex - b.originalIndex;
            }
        });

        return list;
    };

    const renderStats = () => {
        const total = state.all.length;
        const unlocked = state.unlocked.length;
        const locked = Math.max(total - unlocked, 0);
        const percentage = total > 0 ? Math.round((unlocked / total) * 100) : 0;

        const unlockedIds = new Set(state.unlocked.map((item) => toInt(item.id)));
        const points = state.all.reduce((sum, achievement) => (
            unlockedIds.has(toInt(achievement.id)) ? sum + toInt(achievement.punti) : sum
        ), 0);

        $('#statTotal').textContent = String(total);
        $('#statUnlocked').textContent = String(unlocked);
        $('#statLocked').textContent = String(locked);
        $('#statPoints').textContent = String(points);

        $('#completionPercentage').textContent = `${percentage}%`;
        $('#completionText').textContent = `${unlocked} su ${total} achievement completati`;

        const circle = $('#completionCircle');
        if (circle) {
            const circumference = 2 * Math.PI * 54;
            const offset = circumference - (percentage / 100) * circumference;
            circle.style.strokeDasharray = String(circumference);
            circle.style.strokeDashoffset = String(offset);
        }
    };

    const renderAchievementCard = (achievement) => {
        const isUnlocked = achievement.isUnlocked;
        const statusText = isUnlocked ? 'Sbloccato' : 'Bloccato';
        const date = isUnlocked ? formatDate(achievement.unlocked?.data) : '';
        const progress = achievement.progress;
        const percent = progressPercent(progress);
        const title = isUnlocked ? achievement.nome : '???';
        const description = achievement.descrizione || 'Nessuna descrizione.';
        const image = resolveImage(achievement.img_url);

        return `
            <article class="ach-card ${isUnlocked ? 'is-unlocked' : 'is-locked'}"
                     data-achievement-id="${achievement.id}"
                     tabindex="0"
                     role="button"
                     aria-label="Apri ${escapeHtml(achievement.nome)}">
                <div class="ach-card__top">
                    <div class="ach-icon">
                        <img src="${escapeHtml(image)}"
                             alt="${escapeHtml(achievement.nome)}"
                             loading="lazy"
                             onerror="this.src='${fallbackIcon}'">
                    </div>

                    <div class="ach-card__content">
                        <span class="ach-status ${isUnlocked ? 'ach-status--unlocked' : 'ach-status--locked'}">
                            ${isUnlocked ? '<i class="fas fa-check"></i>' : '<i class="fas fa-lock"></i>'}
                            ${statusText}
                        </span>

                        <h3>${escapeHtml(title)}</h3>
                        <p>${escapeHtml(description)}</p>
                    </div>
                </div>

                <div class="ach-card__meta">
                    <span class="ach-badge ach-badge--points">
                        <i class="fas fa-star"></i>
                        ${achievement.punti} punti
                    </span>

                    ${date ? `
                        <span class="ach-badge">
                            <i class="fas fa-calendar-check"></i>
                            ${escapeHtml(date)}
                        </span>
                    ` : ''}
                </div>

                ${!isUnlocked && progress ? `
                    <div class="ach-card__progress">
                        <div class="ach-progress-head">
                            <span>Progresso</span>
                            <strong>${escapeHtml(progress.display || `${progress.current}/${progress.target}`)}</strong>
                        </div>
                        <div class="ach-progress-bar">
                            <span style="width: ${percent.toFixed(1)}%"></span>
                        </div>
                    </div>
                ` : ''}
            </article>
        `;
    };

    const renderAchievements = () => {
        const container = $('#achievementsContainer');
        const empty = $('#achievementEmpty');

        if (!container || !empty) return;

        const list = getFilteredAchievements();
        container.innerHTML = list.map(renderAchievementCard).join('');

        container.hidden = list.length === 0;
        empty.hidden = list.length !== 0;

        $$('.ach-card', container).forEach((card, index) => {
            window.setTimeout(() => card.classList.add('is-visible'), index * 35);

            card.addEventListener('click', () => {
                openAchievementModal(toInt(card.dataset.achievementId));
            });

            card.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                openAchievementModal(toInt(card.dataset.achievementId));
            });
        });
    };

    const openAchievementModal = (achievementId) => {
        const achievement = state.all.find((item) => toInt(item.id) === achievementId);
        if (!achievement) return;

        const unlocked = findUnlocked(achievement.id);
        const isUnlocked = Boolean(unlocked);
        const progress = calculateProgress(achievement);
        const percent = progressPercent(progress);
        const image = resolveImage(achievement.img_url);
        const date = isUnlocked ? formatDate(unlocked.data) : '';

        const content = $('#achievementModalContent');
        const modal = $('#achievementModal');

        if (!content || !modal) return;

        content.innerHTML = `
            <div class="ach-modal-product">
                <div class="ach-modal-product__image">
                    <img src="${escapeHtml(image)}"
                         alt="${escapeHtml(achievement.nome)}"
                         onerror="this.src='${fallbackIcon}'">
                </div>

                <div>
                    <span class="ach-status ${isUnlocked ? 'ach-status--unlocked' : 'ach-status--locked'}">
                        ${isUnlocked ? '<i class="fas fa-check"></i>' : '<i class="fas fa-lock"></i>'}
                        ${isUnlocked ? 'Sbloccato' : 'Bloccato'}
                    </span>

                    <h2 id="achievementModalTitle">${escapeHtml(isUnlocked ? achievement.nome : '???')}</h2>
                    <p>${escapeHtml(achievement.descrizione || 'Nessuna descrizione.')}</p>

                    <div class="ach-card__meta" style="padding: 1rem 0 0;">
                        <span class="ach-badge ach-badge--points">
                            <i class="fas fa-star"></i>
                            ${achievement.punti} punti
                        </span>

                        ${date ? `
                            <span class="ach-badge">
                                <i class="fas fa-calendar-check"></i>
                                ${escapeHtml(date)}
                            </span>
                        ` : ''}
                    </div>

                    ${!isUnlocked && progress ? `
                        <div class="ach-card__progress" style="margin-top: 1rem; border-radius: 18px; border: 1px solid rgba(255,255,255,.08);">
                            <div class="ach-progress-head">
                                <span>Progresso</span>
                                <strong>${escapeHtml(progress.display || `${progress.current}/${progress.target}`)}</strong>
                            </div>
                            <div class="ach-progress-bar">
                                <span style="width: ${percent.toFixed(1)}%"></span>
                            </div>
                        </div>
                    ` : ''}

                    <div class="ach-modal-actions">
                        <button type="button" class="ach-btn" id="copyAchievementLink">
                            <i class="fas fa-link"></i>
                            Copia link
                        </button>
                    </div>
                </div>
            </div>
        `;

        $('#copyAchievementLink')?.addEventListener('click', async () => {
            const url = `${location.origin}${location.pathname}#achievement-${achievement.id}`;
            const ok = await copyText(url);
            showToast(ok ? 'Link copiato.' : 'Non sono riuscito a copiare.');
        });

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    const closeAchievementModal = () => {
        const modal = $('#achievementModal');
        if (!modal) return;

        modal.hidden = true;
        document.body.style.overflow = '';
    };

    const loadAchievements = async () => {
        const loading = $('#loadingState');
        const error = $('#achievementError');
        const container = $('#achievementsContainer');

        try {
            const [unlockedRes, allRes, serverData] = await Promise.all([
                fetch('../api/get_unlocked_achievement.php'),
                fetch('../api/get_all_achievement.php'),
                getServerData()
            ]);

            const unlockedPayload = await safeJson(unlockedRes, []);
            const allPayload = await safeJson(allRes, []);

            if (!Array.isArray(allPayload)) {
                throw new Error('Formato achievement non valido');
            }

            state.unlocked = Array.isArray(unlockedPayload)
                ? unlockedPayload.map(normalizeUnlocked).filter((item) => item.id)
                : [];

            state.all = allPayload.map(normalizeAchievement).filter((item) => item.id);
            state.serverData = serverData;

            loading.hidden = true;
            error.hidden = true;

            renderStats();
            renderAchievements();

            const hashId = (location.hash || '').replace('#achievement-', '');
            if (hashId) {
                window.setTimeout(() => openAchievementModal(toInt(hashId)), 250);
            }
        } catch (err) {
            console.error('Errore nel caricamento degli achievement:', err);

            loading.hidden = true;
            error.hidden = false;
            if (container) container.hidden = true;
        }
    };

    const initFilters = () => {
        const search = $('#achievementSearch');
        const sort = $('#achievementSort');

        if (search) {
            search.value = state.search;
            search.addEventListener('input', () => {
                state.search = search.value;
                renderAchievements();
            });
        }

        if (sort) {
            sort.value = state.sort;
            sort.addEventListener('change', () => {
                state.sort = sort.value;
                localStorage.setItem('cripsum:achievements:sort', state.sort);
                renderAchievements();
            });
        }

        $$('[data-status-filter]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.statusFilter === state.statusFilter);

            button.addEventListener('click', () => {
                state.statusFilter = button.dataset.statusFilter || 'all';
                localStorage.setItem('cripsum:achievements:status', state.statusFilter);

                $$('[data-status-filter]').forEach((item) => {
                    item.classList.toggle('is-active', item === button);
                });

                renderAchievements();
            });
        });
    };

    const initModal = () => {
        $$('[data-close-ach-modal]').forEach((button) => {
            button.addEventListener('click', closeAchievementModal);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeAchievementModal();
        });
    };

    const initReveal = () => {
        const items = $$('.ach-reveal');

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
            if (toggle.dataset.achDropdownBound === '1') return;

            toggle.dataset.achDropdownBound = '1';

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

    document.addEventListener('DOMContentLoaded', () => {
        initNavbarDropdownFallback();
        initReveal();
        initFilters();
        initModal();
        loadAchievements();
    });
})();
