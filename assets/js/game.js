(() => {
    'use strict';

    if (window.__cripsumDuelLoaded) return;
    window.__cripsumDuelLoaded = true;

    const state = {
        matchId: null,
        roomCode: null,
        inventory: [],
        selectedTeam: [],
        match: null,
        pollTimer: null,
        rankingTimer: null,
        lastActionId: 0,
    };

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    let toastTimer = null;
    let fxTimer = null;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    function showToast(message) {
        const toast = $('#gameToast');
        if (!toast) return;

        toast.querySelector('span').textContent = message;
        toast.hidden = false;
        requestAnimationFrame(() => toast.classList.add('is-visible'));

        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toast.classList.remove('is-visible');
            setTimeout(() => { toast.hidden = true; }, 180);
        }, 2200);
    }

    async function api(path, payload = {}, method = 'POST') {
        const options = {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
        };

        if (method !== 'GET') {
            options.body = JSON.stringify(payload);
        }

        const url = method === 'GET' ? `${path}?${new URLSearchParams(payload).toString()}` : path;
        const response = await fetch(url, options);
        const text = await response.text();

        let data;
        try {
            data = JSON.parse(text);
        } catch {
            throw new Error(text || 'Risposta non valida');
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Errore richiesta');
        }

        return data;
    }

    function imgSrc(src) {
        if (!src) return '/img/Susremaster.png';
        if (/^https?:\/\//i.test(src) || src.startsWith('/')) return src;
        return `/img/${src}`;
    }

    function cardImage(src, alt) {
        return `<img src="${escapeAttr(imgSrc(src))}" alt="${escapeAttr(alt)}" onerror="this.onerror=null;this.src='/img/Susremaster.png';">`;
    }

    function rarityClass(value) {
        return String(value || 'comune').toLowerCase().replace(/[^a-z0-9_-]/g, '');
    }

    function hidePanels() {
        $('#gameLobby').hidden = true;
        $('#teamPanel').hidden = true;
        $('#arenaPanel').hidden = true;
    }

    function showLobby() {
        hidePanels();
        $('#gameLobby').hidden = false;
    }

    function showTeam() {
        hidePanels();
        $('#teamPanel').hidden = false;
        $('#roomCodeLabel').textContent = state.roomCode || '---';
    }

    function showArena() {
        hidePanels();
        $('#arenaPanel').hidden = false;
        $('#arenaRoomCode').textContent = state.roomCode || '---';
    }

    function setCoach(message, icon = 'fa-lightbulb') {
        const coach = $('#turnCoach');
        if (!coach) return;
        coach.innerHTML = `<i class="fas ${icon}"></i><span>${escapeHtml(message)}</span>`;
    }

    function showFx(type, message) {
        const arena = $('#arenaPanel');
        const fx = $('#gameFx');
        if (!arena || !fx) return;

        arena.classList.remove('fx-basic_attack', 'fx-special_attack', 'fx-defend', 'fx-charge', 'fx-switch', 'is-fx');
        void arena.offsetWidth;
        arena.classList.add('is-fx', `fx-${type}`);
        fx.querySelector('span').textContent = message;

        clearTimeout(fxTimer);
        fxTimer = setTimeout(() => {
            arena.classList.remove('is-fx', `fx-${type}`);
        }, 850);
    }

    function pulse(selector, className) {
        const el = $(selector);
        if (!el) return;
        el.classList.remove(className);
        void el.offsetWidth;
        el.classList.add(className);
        setTimeout(() => el.classList.remove(className), 520);
    }

    function actionLabel(action) {
        return {
            basic_attack: 'Attacco base',
            special_attack: 'Mossa speciale',
            defend: 'Difesa attiva',
            charge: 'Energia caricata',
            switch: 'Cambio carta',
        }[action] || 'Azione';
    }

    async function loadInventory() {
        const data = await api('/api/game/get_inventory_cards.php', {}, 'GET');
        state.inventory = data.cards || [];
        renderInventory();
    }

    function renderInventory() {
        const grid = $('#inventoryGrid');
        const query = ($('#cardSearch')?.value || '').toLowerCase();
        if (!grid) return;

        grid.innerHTML = '';

        const filtered = state.inventory.filter((card) => `${card.nome} ${card.rarita} ${card.categoria}`.toLowerCase().includes(query));

        if (!filtered.length) {
            grid.innerHTML = '<p class="game-hint">Nessun personaggio trovato.</p>';
            return;
        }

        for (const card of filtered) {
            const selected = state.selectedTeam.includes(Number(card.id));
            const el = document.createElement('button');
            el.type = 'button';
            el.className = `game-card-option rarity-${rarityClass(card.rarita)} ${selected ? 'is-selected' : ''}`;
            el.dataset.id = card.id;
            el.innerHTML = `
                ${cardImage(card.img_url, card.nome)}
                <strong>${escapeHtml(card.nome)}</strong>
                <div class="game-card-stats">
                    <span>HP ${card.stats.hp}</span>
                    <span>ATK ${card.stats.attack}</span>
                    <span>DEF ${card.stats.defense}</span>
                    <span>EN ${card.stats.max_energy}</span>
                </div>
            `;
            el.addEventListener('click', () => toggleTeamCard(Number(card.id)));
            grid.appendChild(el);
        }

        renderSelectedTeam();
    }

    function toggleTeamCard(id) {
        const index = state.selectedTeam.indexOf(id);
        if (index >= 0) {
            state.selectedTeam.splice(index, 1);
        } else {
            if (state.selectedTeam.length >= 3) {
                showToast('Puoi scegliere solo 3 personaggi');
                return;
            }
            state.selectedTeam.push(id);
        }

        renderInventory();
    }

    function renderSelectedTeam() {
        const wrap = $('#selectedTeam');
        const counter = $('#teamCounter');
        if (!wrap) return;

        if (counter) counter.textContent = `${state.selectedTeam.length}/3`;

        if (!state.selectedTeam.length) {
            wrap.innerHTML = '<span class="game-selected-pill">Nessuna carta selezionata</span>';
            return;
        }

        wrap.innerHTML = state.selectedTeam.map((id, index) => {
            const card = state.inventory.find((item) => Number(item.id) === Number(id));
            return `<span class="game-selected-pill"><b>${index + 1}</b>${escapeHtml(card?.nome || 'Carta')}</span>`;
        }).join('');
    }

    async function findMatch(mode) {
        try {
            const data = await api('/api/game/find_match.php', { mode });
            state.matchId = Number(data.match_id);
            state.roomCode = data.room_code;
            state.selectedTeam = [];
            showToast(data.joined ? 'Match trovato' : 'Stanza creata. Attendi avversario');
            showTeam();
            await loadInventory();
            startPolling();
        } catch (error) {
            showToast(error.message);
        }
    }

    async function createPrivate() {
        try {
            const data = await api('/api/game/create_match.php', { mode: 'private', spectators: true });
            state.matchId = Number(data.match_id);
            state.roomCode = data.room_code;
            state.selectedTeam = [];
            showToast(`Stanza creata: ${data.room_code}`);
            showTeam();
            await loadInventory();
            startPolling();
        } catch (error) {
            showToast(error.message);
        }
    }

    async function joinCode() {
        const code = ($('#roomCodeInput')?.value || '').trim();
        if (!code) {
            showToast('Inserisci codice stanza');
            return;
        }

        try {
            const data = await api('/api/game/join_match.php', { room_code: code });
            state.matchId = Number(data.match_id);
            state.roomCode = data.room_code;
            state.selectedTeam = [];
            showToast('Sei entrato nella stanza');
            showTeam();
            await loadInventory();
            startPolling();
        } catch (error) {
            showToast(error.message);
        }
    }

    async function submitTeam() {
        if (state.selectedTeam.length !== 3) {
            showToast('Scegli 3 personaggi');
            return;
        }

        try {
            await api('/api/game/select_team.php', { match_id: state.matchId, team: state.selectedTeam });
            showToast('Team confermato');
            setCoach('Team salvato. Ora aspetta l’altro player.');
            startPolling();
        } catch (error) {
            showToast(error.message);
        }
    }

    function startPolling() {
        stopPolling();
        if (!state.matchId) return;

        pollState();
        state.pollTimer = setInterval(() => {
            if (document.hidden) return;
            pollState();
        }, 1500);
    }

    function stopPolling() {
        if (state.pollTimer) clearInterval(state.pollTimer);
        state.pollTimer = null;
    }

    async function pollState() {
        if (!state.matchId) return;

        try {
            const data = await api('/api/game/get_match_state.php', { match_id: state.matchId }, 'GET');
            const previousWinner = state.match?.winner_id || null;
            state.match = data.match;
            state.roomCode = data.match.room_code;
            renderMatch();

            if (!previousWinner && state.match.winner_id) {
                showFx('special_attack', Number(state.match.winner_id) === myId() ? 'Vittoria' : 'Sconfitta');
                stopPolling();
                loadRanking();
            }
        } catch (error) {
            console.warn('[Cripsum Duel] Poll error:', error);
        }
    }

    function myId() {
        return Number(state.match?.viewer_id || 0);
    }

    function enemyId() {
        const match = state.match;
        if (!match) return null;
        return Number(match.player1_id) === myId() ? Number(match.player2_id) : Number(match.player1_id);
    }

    function cardsOf(userId) {
        return (state.match?.cards || []).filter((card) => Number(card.user_id) === Number(userId));
    }

    function activeOf(userId) {
        return cardsOf(userId).find((card) => Number(card.is_active) === 1 && Number(card.is_ko) === 0)
            || cardsOf(userId).find((card) => Number(card.is_ko) === 0);
    }

    function percent(current, max) {
        if (!max) return 0;
        return Math.max(0, Math.min(100, Math.round((Number(current) / Number(max)) * 100)));
    }

    function renderMatch() {
        const match = state.match;
        if (!match) return;

        if (match.status === 'waiting' || match.status === 'team_select') {
            showTeam();
            $('#roomCodeLabel').textContent = match.room_code;
            setCoach(match.status === 'waiting' ? 'Stanza creata. Condividi il codice o aspetta il matchmaking.' : 'Scegli il team e aspetta la conferma dell’altro player.');
            return;
        }

        showArena();

        $('#matchStatus').textContent = match.status === 'finished' ? 'Conclusa' : `Turno ${match.turn_number}`;

        const isMyTurn = Number(match.current_turn_user_id) === myId();
        if (match.status === 'finished') {
            $('#turnLabel').textContent = Number(match.winner_id) === myId() ? 'Hai vinto' : 'Hai perso';
            setCoach('La partita è finita. Puoi tornare in lobby o iniziarne una nuova.', 'fa-flag-checkered');
        } else if (isMyTurn) {
            $('#turnLabel').textContent = 'È il tuo turno';
            setCoach('Scegli una mossa. Attacco e Carica sono sicuri, Speciale serve energia.', 'fa-hand-pointer');
        } else {
            $('#turnLabel').textContent = 'Turno avversario';
            setCoach('Aspetta la mossa avversaria. Lo stato si aggiorna da solo.', 'fa-clock');
        }

        renderActive('#playerActive', activeOf(myId()), true);
        renderActive('#opponentActive', activeOf(enemyId()), false);
        renderTeam('#playerTeam', cardsOf(myId()), true);
        renderTeam('#opponentTeam', cardsOf(enemyId()), false);
        renderLog(match.actions || []);

        $$('#actionBar .game-move').forEach((button) => {
            button.disabled = !isMyTurn || match.status !== 'active';
        });
    }

    function renderActive(selector, card) {
        const el = $(selector);
        if (!el) return;
        if (!card) {
            el.innerHTML = '<p class="game-hint">Nessuna carta attiva.</p>';
            return;
        }

        const ch = card.character || {};
        const hp = percent(card.current_hp, card.max_hp);
        const en = percent(card.energy, card.max_energy);

        el.innerHTML = `
            ${cardImage(ch.img_url, ch.nome)}
            <div class="game-active-name">
                <strong>${escapeHtml(ch.nome || 'Carta')}</strong>
                <span>${escapeHtml(ch.rarita || 'comune')}</span>
            </div>
            <div class="game-hpbar" title="HP"><span style="--value:${hp}%"></span></div>
            <small>HP ${card.current_hp}/${card.max_hp}</small>
            <div class="game-energybar" title="Energia"><span style="--value:${en}%"></span></div>
            <small>Energia ${card.energy}/${card.max_energy} · Speciale CD ${card.special_cooldown}</small>
            <div class="game-card-stats">
                <span>ATK ${card.attack}</span>
                <span>DEF ${card.defense}</span>
                <span>SPD ${card.speed}</span>
                <span>${Number(card.is_defending) ? 'Difesa attiva' : 'Pronto'}</span>
            </div>
        `;
    }

    function renderTeam(selector, cards, mine) {
        const el = $(selector);
        if (!el) return;

        el.innerHTML = cards.map((card) => {
            const ch = card.character || {};
            return `
                <button class="game-mini-card ${Number(card.is_active) ? 'is-active' : ''} ${Number(card.is_ko) ? 'is-ko' : ''}" type="button" data-card-id="${card.id}" ${mine && !Number(card.is_ko) ? '' : 'disabled'}>
                    ${cardImage(ch.img_url, ch.nome)}
                    <strong>${escapeHtml(ch.nome || 'Carta')}</strong>
                    <small>${card.current_hp}/${card.max_hp} HP</small>
                </button>
            `;
        }).join('');

        if (mine) {
            $$('.game-mini-card', el).forEach((button) => {
                button.addEventListener('click', () => {
                    const id = Number(button.dataset.cardId);
                    if (!id) return;
                    submitBattleAction('switch', id);
                });
            });
        }
    }

    function renderLog(actions) {
        const log = $('#battleLog');
        if (!log) return;

        if (!actions.length) {
            log.innerHTML = '<p class="game-hint">Il log apparirà qui.</p>';
            return;
        }

        const newest = Math.max(...actions.map((action) => Number(action.id || 0)));
        const hadPrevious = state.lastActionId > 0;

        log.innerHTML = actions.map((action) => {
            const isNew = hadPrevious && Number(action.id || 0) > state.lastActionId;
            return `
                <div class="game-log-row ${isNew ? 'is-new' : ''}">
                    <strong>T${action.turn_number}</strong>
                    ${escapeHtml(action.message)}
                    ${Number(action.damage) > 0 ? `· ${action.damage} danni` : ''}
                </div>
            `;
        }).join('');

        if (newest > state.lastActionId) {
            state.lastActionId = newest;
        }

        log.scrollTop = log.scrollHeight;
    }

    async function submitBattleAction(action, targetCardId = null) {
        if (!state.matchId) return;

        try {
            showFx(action, actionLabel(action));

            if (['basic_attack', 'special_attack'].includes(action)) {
                pulse('#playerActive', 'is-acting');
                setTimeout(() => pulse('#opponentActive', 'is-hit'), 220);
            }

            if (action === 'defend' || action === 'charge') {
                pulse('#playerActive', 'is-acting');
            }

            const payload = { match_id: state.matchId, action };
            if (targetCardId) payload.target_card_id = targetCardId;

            const data = await api('/api/game/submit_action.php', payload);
            showToast(data.message || 'Mossa inviata');
            await pollState();
        } catch (error) {
            showToast(error.message);
        }
    }

    async function forfeit() {
        if (!state.matchId) {
            showLobby();
            return;
        }

        if (!confirm('Vuoi abbandonare la partita?')) return;

        try {
            await api('/api/game/forfeit_match.php', { match_id: state.matchId });
            showToast('Partita abbandonata');
            stopPolling();
            state.matchId = null;
            state.match = null;
            showLobby();
            loadRanking();
        } catch (error) {
            showToast(error.message);
        }
    }

    async function activeMatch() {
        try {
            const data = await api('/api/game/active_match.php', {}, 'GET');
            if (!data.match) {
                showToast('Nessuna partita attiva');
                return;
            }

            state.matchId = Number(data.match.id);
            state.roomCode = data.match.room_code;
            startPolling();
            showToast('Partita ripresa');
        } catch (error) {
            showToast(error.message);
        }
    }

    async function loadRanking() {
        const wrap = $('#rankingList');
        if (!wrap) return;

        try {
            const data = await api('/api/game/get_ranking.php', {}, 'GET');
            const ranking = data.ranking || [];
            if (!ranking.length) {
                wrap.innerHTML = '<p class="game-hint">Classifica vuota. Gioca una ranked per iniziare.</p>';
                return;
            }

            wrap.innerHTML = ranking.map((row, index) => `
                <div class="game-rank-row">
                    <strong>#${index + 1}</strong>
                    <span>${escapeHtml(row.username)}</span>
                    <span>${row.rating} · ${row.wins}W/${row.losses}L</span>
                </div>
            `).join('');
        } catch (error) {
            wrap.innerHTML = '<p class="game-hint">Classifica non caricata.</p>';
            console.warn('[Cripsum Duel] Ranking error:', error);
        }
    }

    function initReveals() {
        const items = $$('.game-reveal');
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
        }, { threshold: .12 });

        items.forEach((item) => observer.observe(item));
    }

    document.addEventListener('DOMContentLoaded', () => {
        $$('[data-action="find-match"]').forEach((button) => {
            button.addEventListener('click', () => findMatch(button.dataset.mode || 'casual'));
        });

        $('[data-action="create-private"]')?.addEventListener('click', createPrivate);
        $('[data-action="join-code"]')?.addEventListener('click', joinCode);
        $('[data-action="submit-team"]')?.addEventListener('click', submitTeam);
        $$('[data-action="forfeit"]').forEach((button) => button.addEventListener('click', forfeit));
        $('[data-action="active-match"]')?.addEventListener('click', activeMatch);
        $$('[data-action="load-ranking"]').forEach((button) => button.addEventListener('click', loadRanking));
        $('#cardSearch')?.addEventListener('input', renderInventory);
        $$('[data-battle-action]').forEach((button) => {
            button.addEventListener('click', () => submitBattleAction(button.dataset.battleAction));
        });

        initReveals();
        loadRanking();
        state.rankingTimer = setInterval(() => {
            if (!document.hidden) loadRanking();
        }, 30000);
    });
})();
