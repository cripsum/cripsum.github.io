(() => {
    'use strict';

    if (window.__cripsumGamblingV2Loaded) return;
    window.__cripsumGamblingV2Loaded = true;

    const ROOT = document.querySelector('.gambling-shell');
    if (!ROOT) return;

    const USER_ID = ROOT.dataset.userId || 'guest';
    const STORAGE_KEY = `cripsum_gambling_v2_${USER_ID}`;

    const INITIAL_BALANCE = 100;
    const MAX_BALANCE = 999999;
    const MAX_RECHARGE = 5000;
    const NORMAL_DURATION = 1550;
    const TURBO_DURATION = 650;

    const SYMBOLS = Array.from({ length: 9 }, (_, index) => ({
        id: index + 1,
        src: `/img/slott${index + 1}.jpg`,
        label: `Simbolo ${index + 1}`
    }));

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const elements = {
        balance: $('[data-balance]'),
        currentBet: $('[data-current-bet]'),
        rechargeInput: $('[data-recharge-input]'),
        rechargeButton: $('[data-recharge-button]'),
        rechargeError: $('[data-recharge-error]'),
        betButtons: $$('[data-bet]'),
        spinButton: $('[data-spin-button]'),
        autoSpinButton: $('[data-auto-spin-button]'),
        turboToggle: $('[data-turbo-toggle]'),
        soundToggle: $('[data-sound-toggle]'),
        slotMachine: $('[data-slot-machine]'),
        slotImages: $$('[data-slot-image]'),
        resultBox: $('[data-result-box]'),
        resultTitle: $('[data-result-title]'),
        resultText: $('[data-result-text]'),
        resultKicker: $('.result-kicker'),
        historyList: $('[data-history-list]'),
        clearHistory: $('[data-clear-history]'),
        resetSession: $('[data-reset-session]'),
        openRules: $('[data-open-rules]'),
        closeRules: $('[data-close-rules]'),
        rulesModal: $('[data-rules-modal]')
    };

    const defaultStats = () => ({
        spins: 0,
        won: 0,
        lost: 0,
        best: 0,
        jackpots: 0,
        profit: 0
    });

    const state = {
        balance: INITIAL_BALANCE,
        bet: 10,
        spinning: false,
        autoSpinning: false,
        turbo: false,
        sound: false,
        stats: defaultStats(),
        history: []
    };

    function clampNumber(value, min, max) {
        const number = Number.parseInt(value, 10);
        if (!Number.isFinite(number)) return min;
        return Math.min(Math.max(number, min), max);
    }

    function formatCredits(value) {
        return `${Number.parseInt(value, 10) || 0}`;
    }

    function safeUnlock(id) {
        if (typeof window.unlockAchievement !== 'function') return;

        try {
            window.unlockAchievement(id);
        } catch (err) {
            console.warn('[Gambling] Achievement fallito:', err);
        }
    }

    function loadState() {
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY));

            if (!saved || typeof saved !== 'object') return;

            state.balance = clampNumber(saved.balance, 0, MAX_BALANCE);
            state.bet = [10, 25, 50, 100].includes(Number(saved.bet)) ? Number(saved.bet) : 10;
            state.turbo = Boolean(saved.turbo);
            state.sound = Boolean(saved.sound);
            state.stats = { ...defaultStats(), ...(saved.stats || {}) };
            state.history = Array.isArray(saved.history) ? saved.history.slice(0, 10) : [];
        } catch {
            localStorage.removeItem(STORAGE_KEY);
        }
    }

    function saveState() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                balance: state.balance,
                bet: state.bet,
                turbo: state.turbo,
                sound: state.sound,
                stats: state.stats,
                history: state.history.slice(0, 10)
            }));
        } catch (err) {
            console.warn('[Gambling] localStorage non disponibile:', err);
        }
    }

    function updateBalance() {
        if (!elements.balance) return;

        elements.balance.textContent = formatCredits(state.balance);
        elements.balance.classList.remove('is-low');

        if (state.balance < state.bet) {
            elements.balance.classList.add('is-low');
        }
    }

    function updateBetUI() {
        if (elements.currentBet) {
            elements.currentBet.textContent = formatCredits(state.bet);
        }

        elements.betButtons.forEach((button) => {
            const value = Number(button.dataset.bet);
            button.classList.toggle('is-active', value === state.bet);
            button.disabled = state.spinning || state.autoSpinning;
        });
    }

    function updateStats() {
        Object.entries(state.stats).forEach(([key, value]) => {
            const element = document.querySelector(`[data-stat="${key}"]`);
            if (!element) return;

            element.textContent = formatCredits(value);
            element.classList.toggle('is-negative', Number(value) < 0);
        });
    }

    function updateToggles() {
        if (elements.turboToggle) elements.turboToggle.checked = state.turbo;
        if (elements.soundToggle) elements.soundToggle.checked = state.sound;
    }

    function renderHistory() {
        if (!elements.historyList) return;

        if (!state.history.length) {
            elements.historyList.innerHTML = '<p class="history-empty">Nessuna giocata per ora.</p>';
            return;
        }

        elements.historyList.innerHTML = state.history.map((item) => `
            <div class="history-item ${item.type}">
                <div>
                    <strong>${escapeHtml(item.title)}</strong>
                    <span>${escapeHtml(item.symbols)} · puntata ${item.bet}</span>
                </div>
                <em>${item.win > 0 ? `+${item.win}` : `-${item.bet}`}</em>
            </div>
        `).join('');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function renderAll() {
        updateBalance();
        updateBetUI();
        updateStats();
        updateToggles();
        renderHistory();
        updateControls();
    }

    function updateControls() {
        const disabled = state.spinning || state.autoSpinning;
        const balanceTooLow = state.balance < state.bet;

        if (elements.spinButton) {
            elements.spinButton.disabled = disabled || balanceTooLow;
            elements.spinButton.querySelector('span').textContent = state.spinning ? 'SPINNING...' : 'SPIN';
        }

        if (elements.autoSpinButton) {
            elements.autoSpinButton.disabled = disabled || balanceTooLow;
            elements.autoSpinButton.querySelector('span').textContent = state.autoSpinning ? 'Stop auto' : 'Auto x10';
        }

        if (elements.rechargeButton) {
            elements.rechargeButton.disabled = disabled;
        }

        if (elements.rechargeInput) {
            elements.rechargeInput.disabled = disabled;
        }
    }

    function setResult(type, title, text, kicker = 'Risultato') {
        if (!elements.resultBox) return;

        elements.resultBox.classList.remove('is-win', 'is-loss', 'is-jackpot');
        if (type) elements.resultBox.classList.add(`is-${type}`);

        if (elements.resultKicker) elements.resultKicker.textContent = kicker;
        if (elements.resultTitle) elements.resultTitle.textContent = title;
        if (elements.resultText) elements.resultText.textContent = text;
    }

    function getRandomSymbol() {
        return SYMBOLS[Math.floor(Math.random() * SYMBOLS.length)];
    }

    function getFinalResult() {
        return [getRandomSymbol(), getRandomSymbol(), getRandomSymbol()];
    }

    function setSlotImages(symbols) {
        elements.slotImages.forEach((image, index) => {
            const symbol = symbols[index] || SYMBOLS[0];
            image.src = symbol.src;
            image.alt = symbol.label;
            image.onerror = () => {
                image.onerror = null;
                image.src = '/img/Susremaster.png';
            };
        });
    }

    function evaluateResult(symbols, bet) {
        const ids = symbols.map((symbol) => symbol.id);
        const counts = ids.reduce((acc, id) => {
            acc[id] = (acc[id] || 0) + 1;
            return acc;
        }, {});

        const maxCount = Math.max(...Object.values(counts));
        const allSame = maxCount === 3;
        const twoSame = maxCount === 2;
        let multiplier = 0;
        let title = 'Niente win';
        let type = 'loss';

        if (allSame && ids[0] === 9) {
            multiplier = 50;
            title = 'MEGA JACKPOT';
            type = 'jackpot';
        } else if (allSame && ids[0] === 7) {
            multiplier = 35;
            title = 'JACKPOT 7';
            type = 'jackpot';
        } else if (allSame) {
            multiplier = 20;
            title = 'JACKPOT';
            type = 'jackpot';
        } else if (twoSame) {
            multiplier = 2;
            title = 'Mini win';
            type = 'win';
        }

        const win = bet * multiplier;

        return {
            type,
            title,
            win,
            multiplier,
            symbolsText: ids.join(' · ')
        };
    }

    function playSound(type) {
        if (!state.sound) return;

        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            const ctx = new AudioContext();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            const frequency = type === 'jackpot' ? 740 : type === 'win' ? 520 : 180;

            osc.frequency.value = frequency;
            osc.type = 'sine';
            gain.gain.value = 0.035;

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start();
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.18);
            osc.stop(ctx.currentTime + 0.2);
        } catch {
            // Audio opzionale. Se fallisce non deve rompere il gioco.
        }
    }

    function spawnConfetti(amount = 28) {
        const colors = ['#fbbf24', '#34d399', '#2f6bff', '#8b5cf6', '#fb7185', '#ffffff'];

        for (let index = 0; index < amount; index += 1) {
            const piece = document.createElement('span');
            piece.className = 'gambling-confetti';
            piece.style.left = `${Math.random() * 100}vw`;
            piece.style.background = colors[index % colors.length];
            piece.style.setProperty('--x', `${(Math.random() * 180) - 90}px`);
            piece.style.animationDelay = `${Math.random() * 180}ms`;
            document.body.appendChild(piece);

            piece.addEventListener('animationend', () => piece.remove(), { once: true });
        }
    }

    function addHistory(entry) {
        state.history.unshift(entry);
        state.history = state.history.slice(0, 10);
    }

    function updateAfterSpin(result, bet, symbols) {
        state.stats.spins += 1;
        state.stats.lost += bet;
        state.stats.profit -= bet;

        if (result.win > 0) {
            state.balance = Math.min(state.balance + result.win, MAX_BALANCE);
            state.stats.won += result.win;
            state.stats.profit += result.win;
            state.stats.best = Math.max(state.stats.best, result.win);
        }

        if (result.type === 'jackpot') {
            state.stats.jackpots += 1;
            safeUnlock(3);
            spawnConfetti(34);
        }

        if (state.balance < 10) {
            safeUnlock(11);
        }

        addHistory({
            type: `is-${result.type}`,
            title: result.title,
            bet,
            win: result.win,
            symbols: symbols.map((symbol) => symbol.id).join(' · ')
        });

        saveState();
        renderAll();
    }

    async function spinOnce() {
        if (state.spinning) return false;

        const bet = state.bet;

        if (state.balance < bet) {
            setResult('loss', 'Saldo insufficiente', `Ti servono almeno ${bet} crediti per questa puntata.`, 'Bloccato');
            playSound('loss');
            return false;
        }

        state.spinning = true;
        state.balance -= bet;

        updateBalance();
        updateControls();
        setResult('', 'Slot in movimento', 'Aspetta il risultato...', 'Spin');

        elements.slotMachine?.classList.remove('is-win', 'is-loss', 'is-jackpot');
        elements.slotMachine?.classList.add('is-spinning');
        elements.slotImages.forEach((image) => image.closest('.slot-reel')?.classList.add('is-spinning'));

        const duration = state.turbo ? TURBO_DURATION : NORMAL_DURATION;
        const intervalDelay = state.turbo ? 58 : 92;
        const start = Date.now();

        await new Promise((resolve) => {
            const interval = window.setInterval(() => {
                setSlotImages(getFinalResult());

                if (Date.now() - start >= duration) {
                    window.clearInterval(interval);
                    resolve();
                }
            }, intervalDelay);
        });

        const finalSymbols = getFinalResult();
        const result = evaluateResult(finalSymbols, bet);

        setSlotImages(finalSymbols);

        elements.slotMachine?.classList.remove('is-spinning');
        elements.slotImages.forEach((image) => image.closest('.slot-reel')?.classList.remove('is-spinning'));

        elements.slotMachine?.classList.add(`is-${result.type}`);

        if (result.win > 0) {
            const text = result.type === 'jackpot'
                ? `Hai preso x${result.multiplier}. Vincita: ${result.win} crediti.`
                : `Hai preso x${result.multiplier}. Vincita: ${result.win} crediti.`;

            setResult(result.type, result.title, text, result.type === 'jackpot' ? 'Jackpot' : 'Win');
            playSound(result.type);
        } else {
            setResult('loss', 'Ritenta', `Hai perso ${bet} crediti.`, 'Loss');
            playSound('loss');
        }

        updateAfterSpin(result, bet, finalSymbols);

        window.setTimeout(() => {
            elements.slotMachine?.classList.remove('is-win', 'is-loss', 'is-jackpot');
        }, 1200);

        state.spinning = false;
        updateControls();

        return true;
    }

    async function autoSpin() {
        if (state.autoSpinning) {
            state.autoSpinning = false;
            updateControls();
            return;
        }

        state.autoSpinning = true;
        updateControls();

        for (let count = 0; count < 10; count += 1) {
            if (!state.autoSpinning || state.balance < state.bet) break;
            await spinOnce();
            await wait(state.turbo ? 190 : 420);
        }

        state.autoSpinning = false;
        updateControls();
    }

    function wait(ms) {
        return new Promise((resolve) => window.setTimeout(resolve, ms));
    }

    function recharge() {
        if (!elements.rechargeInput) return;

        const value = clampNumber(elements.rechargeInput.value, 0, MAX_RECHARGE);

        if (!value || value <= 0) {
            if (elements.rechargeError) elements.rechargeError.textContent = 'Inserisci un importo valido.';
            return;
        }

        if (value > MAX_RECHARGE) {
            if (elements.rechargeError) elements.rechargeError.textContent = `Massimo ${MAX_RECHARGE} crediti per ricarica.`;
            return;
        }

        state.balance = Math.min(state.balance + value, MAX_BALANCE);
        elements.rechargeInput.value = '';
        if (elements.rechargeError) elements.rechargeError.textContent = '';

        setResult('', 'Saldo aggiornato', `Hai aggiunto ${value} crediti finti.`, 'Ricarica');
        saveState();
        renderAll();
    }

    function resetSession() {
        const confirmed = window.confirm('Vuoi resettare saldo, statistiche e storico della sessione?');
        if (!confirmed) return;

        state.balance = INITIAL_BALANCE;
        state.bet = 10;
        state.stats = defaultStats();
        state.history = [];
        state.autoSpinning = false;
        state.spinning = false;

        saveState();
        renderAll();
        setResult('', 'Sessione resettata', 'Saldo riportato a 100 crediti.', 'Reset');
        setSlotImages([SYMBOLS[0], SYMBOLS[1], SYMBOLS[2]]);
    }

    function clearHistory() {
        state.history = [];
        saveState();
        renderHistory();
    }

    function setBet(value) {
        if (state.spinning || state.autoSpinning) return;
        if (![10, 25, 50, 100].includes(value)) return;

        state.bet = value;
        saveState();
        renderAll();
    }

    function openRules() {
        if (elements.rulesModal) {
            elements.rulesModal.hidden = false;
        }
    }

    function closeRules() {
        if (elements.rulesModal) {
            elements.rulesModal.hidden = true;
        }
    }

    function initReveal() {
        const items = $$('.gambling-reveal');

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
    }

    function bindEvents() {
        elements.betButtons.forEach((button) => {
            button.addEventListener('click', () => setBet(Number(button.dataset.bet)));
        });

        elements.spinButton?.addEventListener('click', () => spinOnce());
        elements.autoSpinButton?.addEventListener('click', () => autoSpin());
        elements.rechargeButton?.addEventListener('click', () => recharge());

        elements.rechargeInput?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                recharge();
            }
        });

        elements.turboToggle?.addEventListener('change', () => {
            state.turbo = Boolean(elements.turboToggle.checked);
            saveState();
        });

        elements.soundToggle?.addEventListener('change', () => {
            state.sound = Boolean(elements.soundToggle.checked);
            saveState();
        });

        elements.resetSession?.addEventListener('click', resetSession);
        elements.clearHistory?.addEventListener('click', clearHistory);
        elements.openRules?.addEventListener('click', openRules);
        elements.closeRules?.addEventListener('click', closeRules);

        elements.rulesModal?.addEventListener('click', (event) => {
            if (event.target === elements.rulesModal) {
                closeRules();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeRules();
            if (event.code === 'Space' && !state.spinning && !state.autoSpinning && !elements.rulesModal?.hidden) return;

            const activeTag = document.activeElement?.tagName?.toLowerCase();
            const isTyping = ['input', 'textarea', 'select', 'button'].includes(activeTag);

            if (event.code === 'Space' && !isTyping) {
                event.preventDefault();
                spinOnce();
            }
        });
    }

    function init() {
        loadState();
        bindEvents();
        renderAll();
        initReveal();
        setSlotImages([SYMBOLS[0], SYMBOLS[1], SYMBOLS[2]]);

        if (state.balance < 10) {
            setResult('loss', 'Saldo basso', 'Puoi ricaricare crediti finti quando vuoi.', 'Avviso');
        }
    }

    document.addEventListener('DOMContentLoaded', init);
})();
