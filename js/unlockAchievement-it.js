(() => {
    'use strict';

    if (window.__cripsumAchievementPopupV2) {
        window.unlockAchievement = window.__cripsumAchievementPopupV2.unlockAchievement;
        window.showAchievementPopup = window.__cripsumAchievementPopupV2.showAchievementPopup;
        window.getCookie = window.getCookie || window.__cripsumAchievementPopupV2.getCookie;
        window.setCookie = window.setCookie || window.__cripsumAchievementPopupV2.setCookie;
        return;
    }

    const API_BASE = 'https://cripsum.com/api';
    const FALLBACK_IMAGE = '/img/achievement-default.png';
    const DISPLAY_TIME = 4200;

    const state = {
        queue: [],
        showing: false,
        hideTimer: null
    };

    function getCookie(name) {
        try {
            const cookies = document.cookie ? document.cookie.split('; ') : [];

            for (const cookie of cookies) {
                const [key, value] = cookie.split('=');

                if (key === name) {
                    return JSON.parse(decodeURIComponent(value));
                }
            }
        } catch {
            return null;
        }

        return null;
    }

    function setCookie(name, value) {
        document.cookie = `${name}=${encodeURIComponent(JSON.stringify(value))}; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;
    }

    function safeText(value, fallback = '') {
        const text = String(value ?? '').trim();
        return text || fallback;
    }

    function normalizeAchievementId(id) {
        const number = Number.parseInt(id, 10);
        return Number.isFinite(number) ? number : 0;
    }

    function isUnlocked(unlocked, id) {
        if (!Array.isArray(unlocked)) return false;

        return unlocked.some((item) => {
            if (typeof item === 'number' || typeof item === 'string') {
                return normalizeAchievementId(item) === id;
            }

            return normalizeAchievementId(item?.id ?? item?.achievement_id) === id;
        });
    }

    function resolveImage(imgUrl) {
        const raw = safeText(imgUrl);

        if (!raw) return FALLBACK_IMAGE;
        if (/^(https?:)?\/\//i.test(raw)) return raw;
        if (raw.startsWith('/')) return raw;

        return `/img/${raw}`;
    }

    async function fetchJson(url, fallback = null) {
        try {
            const response = await fetch(url, { credentials: 'include' });

            if (!response.ok) {
                return fallback;
            }

            return await response.json();
        } catch (err) {
            console.warn('[Achievement] fetch fallita:', err);
            return fallback;
        }
    }

    function normalizeAchievement(data, id) {
        const source = Array.isArray(data) ? data[0] : data;

        return {
            id,
            nome: safeText(source?.nome ?? source?.name, 'Achievement sbloccato'),
            descrizione: safeText(source?.descrizione ?? source?.description, 'Hai ottenuto un nuovo achievement.'),
            img_url: resolveImage(source?.img_url ?? source?.image ?? source?.img),
            punti: source?.punti ?? source?.points ?? null,
            rarita: safeText(source?.rarita ?? source?.rarità ?? source?.rarity, '')
        };
    }

    function ensurePopup() {
        let popup = document.getElementById('achievement-popup');

        if (!popup) {
            popup = document.createElement('div');
            popup.id = 'achievement-popup';
            popup.className = 'popup achievement-popup';
            document.body.appendChild(popup);
        }

        popup.classList.add('achievement-popup');

        popup.setAttribute('role', 'status');
        popup.setAttribute('aria-live', 'polite');
        popup.setAttribute('aria-atomic', 'true');

        if (popup.dataset.achievementV2Ready !== '1') {
            popup.innerHTML = `
                <img id="popup-image" class="achievement-popup__image" src="${FALLBACK_IMAGE}" alt="Achievement" loading="lazy">
                <div class="achievement-popup__content" data-achievement-popup-content>
                    <span class="achievement-popup__kicker"><i class="fas fa-trophy"></i> Achievement</span>
                    <h3 id="popup-title" class="achievement-popup__title"></h3>
                    <p id="popup-description" class="achievement-popup__description"></p>
                    <div class="achievement-popup__meta">
                        <span id="popup-points" hidden></span>
                        <span id="popup-rarity" hidden></span>
                    </div>
                </div>
                <button type="button" class="achievement-popup__close" data-achievement-close aria-label="Chiudi achievement">
                    <i class="fas fa-xmark"></i>
                </button>
                <div class="achievement-popup__bar" aria-hidden="true"><span></span></div>
            `;

            popup.dataset.achievementV2Ready = '1';

            popup.querySelector('[data-achievement-close]')?.addEventListener('click', () => {
                hideCurrentPopup(true);
            });
        }

        return popup;
    }

    function setText(id, value) {
        const element = document.getElementById(id);

        if (element) {
            element.textContent = value;
        }
    }

    function setOptionalBadge(id, value) {
        const element = document.getElementById(id);
        const text = safeText(value);

        if (!element) return;

        if (!text) {
            element.hidden = true;
            element.textContent = '';
            return;
        }

        element.hidden = false;
        element.textContent = text;
    }

    function renderAchievement(achievement) {
        const popup = ensurePopup();
        const image = document.getElementById('popup-image');

        setText('popup-title', achievement.nome);
        setText('popup-description', achievement.descrizione);
        setOptionalBadge('popup-points', achievement.punti !== null && achievement.punti !== undefined ? `${achievement.punti} punti` : '');
        setOptionalBadge('popup-rarity', achievement.rarita);

        if (image) {
            image.src = achievement.img_url;
            image.alt = achievement.nome;
            image.onerror = () => {
                image.onerror = null;
                image.src = FALLBACK_IMAGE;
            };
        }

        const bar = popup.querySelector('.achievement-popup__bar span');
        if (bar) {
            bar.style.animation = 'none';
            bar.offsetHeight;
            bar.style.animation = '';
        }

        popup.style.setProperty('--ach-popup-duration', `${DISPLAY_TIME}ms`);
        popup.classList.remove('is-hiding');
        popup.classList.add('show', 'is-showing');
    }

    function hideCurrentPopup(skipNext = false) {
        const popup = document.getElementById('achievement-popup');

        clearTimeout(state.hideTimer);

        if (!popup) {
            state.showing = false;
            if (!skipNext) showNext();
            return;
        }

        popup.classList.remove('show', 'is-showing');
        popup.classList.add('is-hiding');

        window.setTimeout(() => {
            popup.classList.remove('is-hiding');
            state.showing = false;

            if (!skipNext) {
                showNext();
            }
        }, 280);
    }

    function showNext() {
        if (state.showing || state.queue.length === 0) return;

        state.showing = true;
        const achievement = state.queue.shift();

        renderAchievement(achievement);

        state.hideTimer = window.setTimeout(() => {
            hideCurrentPopup(false);
        }, DISPLAY_TIME);
    }

    function queueAchievement(achievement) {
        state.queue.push(achievement);
        showNext();
    }

    async function showAchievementPopup(idOrAchievement) {
        const id = normalizeAchievementId(typeof idOrAchievement === 'object' ? idOrAchievement?.id : idOrAchievement);

        if (typeof idOrAchievement === 'object' && idOrAchievement !== null) {
            queueAchievement(normalizeAchievement(idOrAchievement, id));
            return;
        }

        if (!id) {
            queueAchievement(normalizeAchievement(null, 0));
            return;
        }

        const data = await fetchJson(`${API_BASE}/get_achievement?achievement_id=${encodeURIComponent(id)}`, null);
        queueAchievement(normalizeAchievement(data, id));
    }

    async function unlockAchievement(id) {
        const achievementId = normalizeAchievementId(id);

        if (!achievementId) return false;

        try {
            const unlocked = await fetchJson(`${API_BASE}/get_unlocked_achievement`, []);

            if (isUnlocked(unlocked, achievementId)) {
                return false;
            }

            const result = await fetch(`${API_BASE}/set_achievement?achievement_id=${encodeURIComponent(achievementId)}`, {
                credentials: 'include'
            });

            if (!result.ok) {
                console.warn('[Achievement] unlock non riuscito:', achievementId);
                return false;
            }

            await showAchievementPopup(achievementId);
            return true;
        } catch (err) {
            console.error('Errore in unlockAchievement:', err);
            return false;
        }
    }

    const api = {
        unlockAchievement,
        showAchievementPopup,
        getCookie,
        setCookie
    };

    window.__cripsumAchievementPopupV2 = api;
    window.unlockAchievement = unlockAchievement;
    window.showAchievementPopup = showAchievementPopup;
    window.getCookie = window.getCookie || getCookie;
    window.setCookie = window.setCookie || setCookie;

    document.addEventListener('DOMContentLoaded', ensurePopup);
})();
