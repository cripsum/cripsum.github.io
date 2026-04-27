(() => {
    'use strict';

    if (window.__cripsumAchievementsGlobaliLoaded) {
        return;
    }

    window.__cripsumAchievementsGlobaliLoaded = true;

    const COOKIE_MAX_AGE = 'Fri, 31 Dec 9999 23:59:59 GMT';

    function getCookie(name) {
        try {
            const cookies = document.cookie ? document.cookie.split('; ') : [];

            for (const cookie of cookies) {
                const [key, rawValue] = cookie.split('=');

                if (key !== name) continue;

                const value = decodeURIComponent(rawValue || '');

                try {
                    return JSON.parse(value);
                } catch {
                    return value;
                }
            }
        } catch (err) {
            console.warn('[Achievements globali] Errore lettura cookie:', err);
        }

        return null;
    }

    function setCookie(name, value) {
        try {
            document.cookie = `${name}=${encodeURIComponent(JSON.stringify(value))}; path=/; expires=${COOKIE_MAX_AGE}; SameSite=Lax`;
        } catch (err) {
            console.warn('[Achievements globali] Errore salvataggio cookie:', err);
        }
    }

    function safeUnlockAchievement(id) {
        if (typeof window.unlockAchievement !== 'function') {
            return;
        }

        try {
            window.unlockAchievement(id);
        } catch (err) {
            console.warn('[Achievements globali] unlockAchievement fallito:', err);
        }
    }

    async function getUnlockedAchievementsNumber() {
        try {
            const response = await fetch('https://cripsum.com/api/get_unlocked_achievement_number', {
                credentials: 'include'
            });

            if (!response.ok) {
                return 0;
            }

            const data = await response.json();
            return Number.parseInt(data?.count, 10) || 0;
        } catch (err) {
            console.error('Errore in getUnlockedAchievementsNumber:', err);
            return 0;
        }
    }

    async function checkAllAchievementsUnlocked() {
        const unlockedAchievementsCount = await getUnlockedAchievementsNumber();

        if (unlockedAchievementsCount === 20) {
            safeUnlockAchievement(21);
        }
    }

    function checkNightAchievement() {
        const currentDate = new Date();

        if (currentDate.getHours() === 3) {
            safeUnlockAchievement(12);
        }
    }

    function checkTimeSpent() {
        const currentTimeSpent = Number.parseInt(getCookie('timeSpent'), 10) || 0;
        const nextTimeSpent = currentTimeSpent + 1;

        setCookie('timeSpent', nextTimeSpent);

        if (nextTimeSpent >= 7200 && !getCookie('achievement14Unlocked')) {
            safeUnlockAchievement(14);
            setCookie('achievement14Unlocked', true);
        }
    }

    function checkDaysVisited() {
        let daysVisited = getCookie('daysVisited');

        if (!Array.isArray(daysVisited)) {
            daysVisited = [];
        }

        const today = new Date().toISOString().slice(0, 10);

        if (!daysVisited.includes(today)) {
            daysVisited.push(today);
            setCookie('daysVisited', daysVisited);
        }

        if (daysVisited.length >= 30) {
            safeUnlockAchievement(13);
        }
    }

    function initGlobalAchievements() {
        checkAllAchievementsUnlocked();
        checkNightAchievement();
        checkDaysVisited();

        window.setInterval(checkTimeSpent, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGlobalAchievements, { once: true });
    } else {
        initGlobalAchievements();
    }
})();
