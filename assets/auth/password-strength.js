(() => {
    'use strict';

    const translations = {
        it: ['Poco sicura', 'Discreta', 'Sicura', 'Molto sicura'],
        en: ['Weak', 'Fair', 'Strong', 'Very strong'],
    };

    const commonPattern = /^(?:password|passw0rd|qwerty|admin|welcome|benvenuto|cripsum|godos|12345678)\d*$/i;
    const sequencePattern = /(?:12345678|87654321|qwerty|asdfgh|abcdefgh)/i;
    const repeatedPattern = /(.)\1{4,}/;

    const strengthLevel = (password) => {
        if (commonPattern.test(password) || sequencePattern.test(password) || repeatedPattern.test(password)) {
            return 0;
        }

        let score = password.length >= 16 ? 2 : password.length >= 12 ? 1 : 0;
        const characterGroups = [
            /[a-z]/.test(password),
            /[A-Z]/.test(password),
            /\d/.test(password),
            /[^A-Za-z0-9]/.test(password),
        ].filter(Boolean).length;

        if (characterGroups >= 3) score += 1;
        if (characterGroups === 4 && password.length >= 12) score += 1;
        return Math.min(3, score);
    };

    const init = () => {
        const lang = document.documentElement.lang?.toLowerCase().startsWith('en') ? 'en' : 'it';
        const labels = translations[lang];
        const inputs = Array.from(document.querySelectorAll('input[type="password"]')).filter((input) => {
            if (input.dataset.noStrength !== undefined) return false;
            const autocomplete = (input.getAttribute('autocomplete') || '').toLowerCase();
            if (autocomplete === 'current-password') return false;

            const name = (input.name || '').toLowerCase();
            return name === 'password' || name === 'nuova_password';
        });

        inputs.forEach((input, index) => {
            if (input.dataset.strengthBound === '1') return;
            input.dataset.strengthBound = '1';

            const indicator = document.createElement('div');
            const indicatorId = `password-strength-${index + 1}`;
            indicator.id = indicatorId;
            indicator.className = 'password-strength';
            indicator.setAttribute('role', 'status');
            indicator.setAttribute('aria-live', 'polite');
            indicator.hidden = true;

            const meter = document.createElement('span');
            meter.className = 'password-strength__meter';
            meter.setAttribute('aria-hidden', 'true');

            const bar = document.createElement('span');
            bar.className = 'password-strength__bar';
            meter.appendChild(bar);

            const text = document.createElement('span');
            text.className = 'password-strength__text';
            indicator.append(meter, text);

            const wrapper = input.closest('.auth-password, .password-wrap');
            (wrapper || input).insertAdjacentElement('afterend', indicator);
            const describedBy = [input.getAttribute('aria-describedby'), indicatorId].filter(Boolean).join(' ');
            input.setAttribute('aria-describedby', describedBy);

            const update = () => {
                if (input.value === '') {
                    indicator.hidden = true;
                    return;
                }

                const level = strengthLevel(input.value);
                indicator.hidden = false;
                indicator.dataset.level = String(level);
                text.textContent = labels[level];
            };

            input.addEventListener('input', update);
            update();
        });
    };

    document.addEventListener('DOMContentLoaded', init);
})();
