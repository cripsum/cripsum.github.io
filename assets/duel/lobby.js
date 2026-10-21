/**
 * Duelli 2.0 — la lobby «Duelli» (tavola 1).
 *
 * Una cosa sola da fare per prima, giocare: la modalita' a pillole, la
 * squadra salvata gia' pronta sotto, il pulsante. A lato rango, classifica,
 * partite in diretta e le tue ultime. I dati arrivano nella pagina; ogni 30
 * secondi (a pagina visibile) api/duel/hub.php aggiorna diretta, coda e
 * partite in corso.
 *
 * In coda il browser manda il segnale ogni 4 secondi (queue ping): il server
 * abbina e risponde «matched» con la partita.
 */
(function () {
    'use strict';
    const D = window.D2P;
    const { T, $, esc, fmt, ic, api, CFG } = D;
    const H = D.DATA;
    const U = CFG.urls;

    const store = {
        get(k, d) { try { const v = localStorage.getItem('d2.' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
        set(k, v) { try { localStorage.setItem('d2.' + k, JSON.stringify(v)); } catch (e) { /* niente */ } },
    };

    const S = {
        mode: store.get('mode', 'ranked'),
        slot: store.get('slot', 0),
        level: store.get('bot', 'normale'),
        room: Object.assign({ levels: 'max', cost: false }, store.get('room', {})),
        board: null,
        search: null, // { mode, started, timer }
        busy: false,
    };
    const MODES = ['casual', 'ranked', 'friend', 'bot'];
    if (!MODES.includes(S.mode)) S.mode = 'ranked';
    const team = () => H.teams.find((t) => t.slot === S.slot) || H.teams[0] || null;
    if (!H.teams.some((t) => t.slot === S.slot) && H.teams[0]) S.slot = H.teams[0].slot;

    const go = (id) => { location.href = U.arena + '?m=' + id; };
    function say(text) {
        const el = $('d2p-say');
        el.textContent = '';
        setTimeout(() => { el.textContent = text; }, 30);
    }

    // ── Modalita' ────────────────────────────────────────────────────────
    const MODE_LABEL = { casual: T('Veloce', 'Quick'), ranked: 'Ranked', friend: T('Sfida un amico', 'Challenge a friend'), bot: T('Contro il bot', 'Vs bot') };
    function modeLine() {
        const b = CFG.budget;
        return {
            casual: '<b>' + T('Veloce:', 'Quick:') + '</b> ' + esc(T('i tuoi livelli, squadra libera, 45 secondi a turno. Non conta per la classifica.', 'your own levels, any team, 45 seconds per turn. Does not count for the ranking.')),
            ranked: '<b>Ranked:</b> ' + esc(T('tutti i personaggi al livello massimo, squadra con costo fino a ' + b + ', 45 secondi a turno.', 'every character at max level, team cost up to ' + b + ', 45 seconds per turn.')),
            friend: '<b>' + esc(T('Sfida un amico:', 'Challenge a friend:')) + '</b> ' + esc(T('apri una stanza e manda il codice, oppure entra con il codice che ti hanno mandato. Il codice vale 10 minuti.', 'open a room and send the code, or join with the code you got. The code lasts 10 minutes.')),
            bot: '<b>' + esc(T('Contro il bot:', 'Vs bot:')) + '</b> ' + esc(T('i tuoi livelli, tre bot dal più facile al più bravo. Non conta per la classifica.', 'your own levels, three bots from easiest to hardest. Does not count for the ranking.')),
        }[S.mode];
    }
    function renderModes() {
        $('d2p-modes').innerHTML = MODES.map((m) => '<button type="button" role="tab" data-mode="' + m + '" aria-selected="' + (m === S.mode) + '"' + (m === S.mode ? ' class="d2p-on"' : '') + (S.search ? ' disabled' : '') + '>'
            + esc(MODE_LABEL[m]) + '</button>').join('');
        $('d2p-modeline').innerHTML = modeLine();
    }

    // ── Squadra ──────────────────────────────────────────────────────────
    function rolesChip(t) {
        if (t.attack && (t.guard || t.heal)) return '<span><i data-i="check"></i> ' + esc(T('Ruoli completi', 'Roles covered')) + '</span>';
        if (!t.attack) return '<span class="d2p-off"><i data-i="attacco"></i> ' + esc(T('Manca chi attacca', 'No attacker')) + '</span>';
        return '<span class="d2p-off"><i data-i="guardia"></i> ' + esc(T('Manca chi difende o cura', 'No defender or healer')) + '</span>';
    }
    function renderTeam() {
        const box = $('d2p-team');
        const t = team();
        if (!t) {
            box.innerHTML = '<div class="d2p-team d2p-team-empty"><div class="d2p-cards">'
                + [0, 1, 2].map(() => '<a class="d2p-slot-empty" href="' + esc(U.team) + '" aria-hidden="true" tabindex="-1">' + ic('piu') + '</a>').join('') + '</div>'
                + '<div class="d2p-team-info"><strong>' + esc(T('Ancora nessuna squadra', 'No team yet')) + '</strong>'
                + '<p>' + esc(H.collection >= 3 ? T('Scegli tre personaggi dalla collezione: la squadra resta salvata per le prossime partite.', 'Pick three characters from your collection: the team stays saved for your next matches.')
                    : T('Servono almeno tre personaggi: trovali nella lootbox.', 'You need at least three characters: find them in the lootbox.')) + '</p>'
                + '<a class="d2p-btn d2p-btn-w d2p-btn-sm" href="' + esc(H.collection >= 3 ? U.team : '/' + CFG.lang + '/lootbox') + '">'
                + esc(H.collection >= 3 ? T('Crea la squadra', 'Build a team') : T('Vai alla lootbox', 'Go to the lootbox')) + ' ' + ic('freccia') + '</a></div></div>';
            D.hydrate(box);
            return;
        }
        const b = CFG.budget;
        const over = t.cost > b;
        const traits = t.traits.filter((x) => x.active).map((x) => '<span><i data-i="check"></i> ' + esc(H.traits[x.key] ? H.traits[x.key].n : x.key) + ' ' + x.n + '/' + x.max + '</span>').join('');
        const slots = H.teams.length > 1 ? '<span class="d2p-slots" role="group" aria-label="' + esc(T('Squadre salvate', 'Saved teams')) + '">'
            + H.teams.map((x) => '<button type="button" data-slot="' + x.slot + '" aria-pressed="' + (x.slot === t.slot) + '"' + (x.slot === t.slot ? ' class="d2p-on"' : '') + ' title="' + esc(x.name) + '">' + x.slot + '</button>').join('') + '</span>' : '';
        box.innerHTML = '<div class="d2p-team"><div class="d2p-cards">' + t.cards.map((c) => D.card(c, { lv: true })).join('')
            + (t.missing ? Array(t.missing).fill('<a class="d2p-slot-empty" href="' + esc(U.team + '?slot=' + t.slot) + '" aria-hidden="true" tabindex="-1">' + ic('piu') + '</a>').join('') : '') + '</div>'
            + '<div class="d2p-team-info"><div class="d2p-team-name"><strong>' + esc(t.name) + '</strong>' + slots + '</div>'
            + '<div class="d2p-tcost' + (over && S.mode === 'ranked' ? ' d2p-bad' : '') + '">' + esc(T('Costo', 'Cost')) + ' <b class="d2p-num">' + t.cost + ' / ' + b + '</b> ' + D.pips(t.cards, b) + '</div>'
            + '<div class="d2p-chips">' + traits + rolesChip(t) + '</div>'
            + (t.missing ? '<p class="d2p-warn">' + esc(T('Un personaggio della squadra non è più nella tua collezione: sistemala prima di giocare.', 'A character in this team is no longer in your collection: fix the team before playing.')) + '</p>' : '')
            + '<a class="d2p-btn d2p-btn-sm" href="' + esc(U.team + '?slot=' + t.slot) + '">' + esc(T('Cambia squadra', 'Change team')) + ' ' + ic('freccia') + '</a></div></div>';
        D.hydrate(box);
    }

    // ── Opzioni della modalita' (bot, stanza) ────────────────────────────
    function renderExtra() {
        const box = $('d2p-extra');
        if (S.mode === 'bot') {
            const lv = { facile: T('Facile', 'Easy'), normale: T('Normale', 'Normal'), difficile: T('Difficile', 'Hard') };
            box.innerHTML = '<div class="d2p-opts"><span class="d2p-lab">' + esc(T('Bot', 'Bot')) + '</span><div class="d2p-tabs2" role="group">'
                + Object.keys(lv).map((k) => '<button type="button" data-level="' + k + '" aria-pressed="' + (k === S.level) + '"' + (k === S.level ? ' class="d2p-on"' : '') + '>' + esc(lv[k]) + '</button>').join('') + '</div></div>';
        } else if (S.mode === 'friend') {
            box.innerHTML = '<div class="d2p-opts"><span class="d2p-lab">' + esc(T('Livelli', 'Levels')) + '</span><div class="d2p-tabs2" role="group">'
                + '<button type="button" data-levels="max" aria-pressed="' + (S.room.levels === 'max') + '"' + (S.room.levels === 'max' ? ' class="d2p-on"' : '') + '>' + esc(T('Pareggiati', 'Equal')) + '</button>'
                + '<button type="button" data-levels="own" aria-pressed="' + (S.room.levels === 'own') + '"' + (S.room.levels === 'own' ? ' class="d2p-on"' : '') + '>' + esc(T('I vostri', 'Your own')) + '</button></div>'
                + '<span class="d2p-lab">' + esc(T('Costo', 'Cost')) + '</span><div class="d2p-tabs2" role="group">'
                + '<button type="button" data-cost="0" aria-pressed="' + !S.room.cost + '"' + (!S.room.cost ? ' class="d2p-on"' : '') + '>' + esc(T('Libero', 'Free')) + '</button>'
                + '<button type="button" data-cost="1" aria-pressed="' + S.room.cost + '"' + (S.room.cost ? ' class="d2p-on"' : '') + '>' + esc(T('Fino a ', 'Up to ') + CFG.budget) + '</button></div></div>';
        } else {
            box.innerHTML = '';
        }
    }

    // ── Gioca ────────────────────────────────────────────────────────────
    function renderPlay() {
        const box = $('d2p-play');
        const t = team();
        if (S.search) {
            const secs = Math.floor((Date.now() - S.search.started) / 1000);
            const long = secs >= 60;
            box.innerHTML = '<button class="d2p-btn d2p-btn-big" type="button" data-act="cancel">' + esc(T('Annulla la ricerca', 'Cancel search')) + '</button>'
                + '<small class="d2p-status"><span class="d2p-dot d2p-pulse"></span> <span id="d2p-search-t">' + esc(T('Cerco un avversario ', 'Looking for an opponent ') + MODE_LABEL[S.search.mode]) + ' · ' + clock(secs) + '</span></small>'
                + (long ? '<p class="d2p-hint">' + esc(T('Poca gente in coda adesso. Puoi aspettare ancora o giocare col bot (esci dalla coda).', 'Few people in queue right now. Keep waiting or play the bot (you leave the queue).'))
                    + ' <button class="d2p-btn d2p-btn-sm" type="button" data-act="bot-now">' + esc(T('Gioca col bot', 'Play the bot')) + '</button></p>' : '');
            return;
        }
        const label = { casual: T('Gioca Veloce', 'Play Quick'), ranked: T('Gioca Ranked', 'Play Ranked'), friend: T('Apri una stanza', 'Open a room'), bot: T('Gioca contro il bot', 'Play the bot') }[S.mode];
        let note = '';
        let disabled = !t || t.missing > 0 || t.cards.length < 3;
        if (t && S.mode === 'ranked' && t.cost > CFG.budget) {
            disabled = true;
            note = '<small class="d2p-status d2p-bad">' + esc(T('La squadra costa ' + t.cost + ': in Ranked il massimo è ' + CFG.budget + '.', 'The team costs ' + t.cost + ': Ranked allows ' + CFG.budget + ' at most.')) + '</small>';
        } else if (S.mode === 'casual' || S.mode === 'ranked') {
            const n = (H.queue_now || {})[S.mode] || 0;
            note = '<small class="d2p-status"><span class="d2p-dot' + (n ? '' : ' d2p-dot-off') + '"></span> '
                + esc(n ? (n === 1 ? T('1 in coda adesso', '1 in queue now') : T(n + ' in coda adesso', n + ' in queue now')) : T('Nessuno in coda adesso: chi entra aspetta il prossimo.', 'Nobody in queue right now: whoever joins waits for the next one.')) + '</small>';
        }
        box.innerHTML = '<button class="d2p-btn d2p-btn-w d2p-btn-big" type="button" data-act="play"' + (disabled ? ' disabled' : '') + '>' + esc(label) + '</button>' + note
            + (S.mode === 'friend' ? '<form class="d2p-join" id="d2p-join" autocomplete="off"><input id="d2p-code" maxlength="8" spellcheck="false" autocapitalize="characters" placeholder="' + esc(T('Codice', 'Code')) + '" aria-label="' + esc(T('Codice della stanza', 'Room code')) + '">'
                + '<button class="d2p-btn" type="submit"' + (!t || t.cards.length < 3 ? ' disabled' : '') + '>' + esc(T('Entra con il codice', 'Join with code')) + '</button></form>' : '');
    }
    const clock = (s) => Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');

    async function play() {
        const t = team();
        if (!t || S.busy) return;
        S.busy = true;
        let r;
        if (S.mode === 'bot') r = await api('bot', null, { slot: t.slot, level: S.level });
        else if (S.mode === 'friend') r = await api('room', null, { action: 'create', slot: t.slot, levels: S.room.levels, cost_limit: S.room.cost });
        else r = await api('queue', null, { action: 'join', mode: S.mode, slot: t.slot });
        S.busy = false;
        if (!r.ok) { fail(r); return; }
        if (r.match && (S.mode === 'bot' || S.mode === 'friend' || r.status === 'matched')) { go(r.match); return; }
        startSearch(S.mode, Date.now() - (r.waited || 0) * 1000);
    }
    function fail(r) {
        if (r.code === 'IN_MATCH' && r.match) { go(r.match); return; }
        D.toast(r.error || T('Qualcosa è andato storto.', 'Something went wrong.'), true);
    }
    async function join(code) {
        const t = team();
        if (!t || !code) return;
        const r = await api('room', null, { action: 'join', code: code.toUpperCase(), slot: t.slot });
        if (!r.ok) { fail(r); return; }
        go(r.match);
    }

    function startSearch(mode, started) {
        stopSearch();
        S.search = { mode, started, timer: 0 };
        renderModes();
        renderPlay();
        say(T('Cerco un avversario.', 'Looking for an opponent.'));
        const tick = async () => {
            if (!S.search) return;
            const p = await api('queue', null, { action: 'ping' });
            if (!S.search) return;
            if (p.ok && p.status === 'matched') { say(T('Avversario trovato.', 'Opponent found.')); go(p.match); return; }
            if (p.ok && p.status === 'idle') { stopSearch(); D.toast(T('Ricerca finita.', 'Search ended.')); render(); return; }
            renderPlay();
            S.search.timer = setTimeout(tick, 4000);
        };
        S.search.timer = setTimeout(tick, 4000);
        S.search.clock = setInterval(() => {
            const el = $('d2p-search-t');
            if (!el || !S.search) return;
            const secs = Math.floor((Date.now() - S.search.started) / 1000);
            el.textContent = T('Cerco un avversario ', 'Looking for an opponent ') + MODE_LABEL[S.search.mode] + ' · ' + clock(secs);
            if (secs === 60) renderPlay();
        }, 1000);
    }
    function stopSearch() {
        if (!S.search) return;
        clearTimeout(S.search.timer);
        clearInterval(S.search.clock);
        S.search = null;
    }

    // ── In corso ─────────────────────────────────────────────────────────
    function renderGoing() {
        const box = $('d2p-going');
        const a = H.active;
        if (!a) { box.hidden = true; return; }
        box.hidden = false;
        let row;
        if (a.status === 'waiting') {
            const left = Math.max(0, (a.expires || 0) - Date.now() / 1000);
            row = '<div class="d2p-chl"><span class="d2p-av">' + ic('lucchetto') + '</span><div><strong>' + esc(T('La tua stanza è aperta', 'Your room is open')) + '</strong>'
                + '<span>' + esc(T('Codice ', 'Code ')) + '<b class="d2p-code">' + esc(a.code || '') + '</b> · ' + esc(T('scade tra ', 'expires in ')) + clock(Math.floor(left)) + '</span></div>'
                + '<div class="d2p-acts"><a class="d2p-btn d2p-btn-w d2p-btn-sm" href="' + esc(U.arena + '?m=' + a.id) + '">' + esc(T('Apri', 'Open')) + '</a>'
                + '<button class="d2p-btn d2p-btn-sm" type="button" data-act="close-room">' + esc(T('Chiudi', 'Close')) + '</button></div></div>';
        } else {
            row = '<div class="d2p-chl"><span class="d2p-av">' + ic('attacco') + '</span><div><strong>' + (a.my_turn ? '<span class="d2p-badge">' + esc(T('Tocca a te', 'Your turn')) + '</span>' : '')
                + esc(a.opponent ? T('contro ', 'vs ') + a.opponent : T('Partita in corso', 'Match in progress')) + '</strong>'
                + '<span>' + esc(D.MODE[a.mode] || a.mode) + (a.turn ? ' · ' + T('turno ', 'turn ') + a.turn : '') + '</span></div>'
                + '<div class="d2p-acts"><a class="d2p-btn d2p-btn-w d2p-btn-sm" href="' + esc(U.arena + '?m=' + a.id) + '">' + esc(T('Torna in campo', 'Back to the match')) + '</a></div></div>';
        }
        box.innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(T('In corso', 'In progress')) + '</h2></div>' + row;
        D.hydrate(box);
    }

    // ── Rango, classifica, diretta, ultime ───────────────────────────────
    function renderRank() {
        const me = H.me;
        const r = me.rank;
        const next = r.next;
        const pct = next ? Math.max(4, Math.min(100, ((me.rating - r.min) / (next - r.min)) * 100)) : 100;
        const nextTier = next ? D.TIER[{ 1000: 'argento', 1200: 'oro', 1400: 'diamante', 1600: 'campione', 1900: 'leggenda' }[next]] || '' : '';
        $('d2p-rank').innerHTML = '<div class="d2p-rank">' + D.emblem(r.key, 'd2p-emb-l') + '<div><strong>' + esc(r.label) + '</strong><span class="d2p-num">' + fmt(me.rating) + ' ' + esc(T('punti', 'points')) + '</span></div></div>'
            + '<div class="d2p-bar d2p-rank-bar" style="--tc: var(--t-' + esc(r.key) + ')"><i style="width:' + pct.toFixed(1) + '%"></i></div>'
            + '<div class="d2p-rank-note">' + (next ? '<span>' + esc(nextTier + T(' a ', ' at ') + fmt(next)) + '</span><span class="d2p-num">' + esc(T('mancano ', '')) + fmt(next - me.rating) + esc(T('', ' to go')) + '</span>'
                : '<span>' + esc(T('Il rango più alto', 'The top rank')) + '</span>') + '</div>'
            + '<div class="d2p-rank-wl"><span><b class="d2p-num">' + fmt(me.wins) + '</b> ' + esc(T('vittorie', 'wins')) + '</span><span><b class="d2p-num">' + fmt(me.losses) + '</b> ' + esc(T('sconfitte', 'losses')) + '</span>'
            + '<span>' + esc(T('serie', 'streak')) + ' <b class="d2p-num">' + fmt(me.streak) + '</b></span></div>'
            + (me.games ? '' : '<p class="d2p-mut">' + esc(T('Gioca una Ranked per entrare in classifica. I punti sono quelli dei duelli di sempre.', 'Play a Ranked match to enter the ranking. Points carry over from the classic duels.')) + '</p>');
    }

    function renderBoard() {
        const b = H.board;
        if (S.board === null) S.board = b.friends.length > 1 ? 'friends' : 'all';
        const list = b[S.board];
        const meIn = list.some((x) => x.id === CFG.me);
        let rows = list.map((x, i) => '<div class="d2p-lb' + (x.id === CFG.me ? ' d2p-me' : '') + '"><span class="d2p-p">' + (i + 1) + '</span>' + D.emblem(x.rank) + '<b>' + esc(x.username) + '</b><span class="d2p-d d2p-num">' + fmt(x.rating) + '</span></div>').join('');
        if (S.board === 'all' && !meIn && b.pos) {
            rows += '<div class="d2p-lb d2p-me d2p-gap"><span class="d2p-p">' + b.pos + '</span>' + D.emblem(H.me.rank.key) + '<b>' + esc(T('Tu', 'You')) + '</b><span class="d2p-d d2p-num">' + fmt(H.me.rating) + '</span></div>';
        }
        if (!list.length) rows = '<p class="d2p-mut">' + esc(T('Ancora nessuno in classifica.', 'Nobody in the ranking yet.')) + '</p>';
        if (S.board === 'friends' && b.friends.length <= 1) rows += '<p class="d2p-mut">' + esc(T('Quando i tuoi amici giocano compaiono qui.', 'Your friends show up here once they play.')) + '</p>';
        $('d2p-board').innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(T('Classifica', 'Ranking')) + '</h2><span class="d2p-tabs2" role="group">'
            + ['friends', 'all'].map((k) => '<button type="button" data-board="' + k + '" aria-pressed="' + (k === S.board) + '"' + (k === S.board ? ' class="d2p-on"' : '') + '>' + esc(k === 'friends' ? T('Amici', 'Friends') : T('Tutti', 'All')) + '</button>').join('')
            + '</span></div>' + rows;
    }

    function renderLive() {
        const rows = H.live.map((l) => '<div class="d2p-live"><div><strong>' + esc((l.players[0] || '?') + T(' contro ', ' vs ') + (l.players[1] || '?')) + '</strong>'
            + '<span>' + esc(D.MODE[l.mode] || l.mode) + ' · ' + esc(T('turno ', 'turn ')) + l.turn + (l.watchers ? ' · ' + ic('occhio') + ' ' + l.watchers : '') + '</span></div>'
            + '<a class="d2p-btn d2p-btn-sm" href="' + esc(U.arena + '?m=' + l.id) + '">' + esc(T('Guarda', 'Watch')) + '</a></div>').join('');
        $('d2p-live').innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(T('In diretta', 'Live')) + '</h2></div>'
            + (rows || '<p class="d2p-mut">' + esc(T('Nessuna partita in diretta adesso.', 'No live matches right now.')) + '</p>');
        D.hydrate($('d2p-live'));
    }

    function renderRecent() {
        const rows = H.recent.map((r) => {
            const res = r.draw ? ['P', 'd2p-l', T('Pareggio', 'Draw')] : r.won ? ['V', '', T('Vittoria', 'Victory')] : ['S', 'd2p-l', T('Sconfitta', 'Defeat')];
            const d = r.rating_delta;
            return '<div class="d2p-hist"><a class="d2p-hist-main" href="' + esc(U.end + '?m=' + r.id) + '"><span class="d2p-r ' + res[1] + '" title="' + esc(res[2]) + '">' + (D.EN ? res[2].charAt(0) : res[0]) + '</span>'
                + '<span><span class="d2p-hist-who">' + esc(T('contro ', 'vs ') + r.opponent) + '</span><small>' + esc((D.MODE[r.mode] || r.mode) + ' · ' + D.ago(r.at)) + '</small></span>'
                + (d !== null && d !== undefined ? '<span class="d2p-dl d2p-num ' + (d > 0 ? 'd2p-up' : d < 0 ? 'd2p-dn' : '') + '">' + (d > 0 ? '+' : d < 0 ? '−' : '') + fmt(Math.abs(d)) + '</span>' : '<span></span>') + '</a>'
                + '<a class="d2p-ibtn" href="' + esc(U.replay + '?m=' + r.id) + '" aria-label="' + esc(T('Rivedi la partita', 'Watch the replay')) + '" title="' + esc(T('Rivedi la partita', 'Watch the replay')) + '">' + ic('replay') + '</a></div>';
        }).join('');
        $('d2p-recent').innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(T('Ultime partite', 'Recent matches')) + '</h2></div>'
            + (rows || '<p class="d2p-mut">' + esc(T('Le tue partite compariranno qui.', 'Your matches will show up here.')) + '</p>');
    }

    function render() {
        renderModes();
        renderTeam();
        renderExtra();
        renderPlay();
        renderGoing();
        renderRank();
        renderBoard();
        renderLive();
        renderRecent();
    }

    // ── Clic ─────────────────────────────────────────────────────────────
    document.getElementById('d2p-main').addEventListener('click', async (e) => {
        const b = e.target.closest('button');
        if (!b || b.disabled) return;
        if (b.dataset.mode) { S.mode = b.dataset.mode; store.set('mode', S.mode); renderModes(); renderTeam(); renderExtra(); renderPlay(); return; }
        if (b.dataset.slot) { S.slot = Number(b.dataset.slot); store.set('slot', S.slot); renderTeam(); renderPlay(); return; }
        if (b.dataset.level) { S.level = b.dataset.level; store.set('bot', S.level); renderExtra(); return; }
        if (b.dataset.levels) { S.room.levels = b.dataset.levels; store.set('room', S.room); renderExtra(); return; }
        if (b.dataset.cost) { S.room.cost = b.dataset.cost === '1'; store.set('room', S.room); renderExtra(); return; }
        if (b.dataset.board) { S.board = b.dataset.board; renderBoard(); return; }
        const act = b.dataset.act;
        if (act === 'play') { play(); return; }
        if (act === 'cancel') { stopSearch(); await api('queue', null, { action: 'leave' }); render(); return; }
        if (act === 'bot-now') {
            stopSearch();
            const t = team();
            const r = await api('bot', null, { slot: t.slot, level: S.level });
            if (!r.ok) { fail(r); render(); return; }
            go(r.match);
            return;
        }
        if (act === 'close-room') {
            const r = await api('forfeit', null, { m: H.active.id });
            if (!r.ok) { fail(r); return; }
            H.active = null;
            renderGoing();
        }
    });
    document.getElementById('d2p-main').addEventListener('submit', (e) => {
        if (e.target.id !== 'd2p-join') return;
        e.preventDefault();
        join(($('d2p-code').value || '').trim());
    });

    // ── Aggiornamento leggero ────────────────────────────────────────────
    async function refresh() {
        if (document.hidden || S.busy) return;
        const r = await api('hub');
        if (!r.ok) return;
        H.live = r.live || [];
        H.recent = r.recent || H.recent;
        H.queue_now = r.queue_now || H.queue_now;
        const wasActive = H.active && H.active.id;
        if (!r.active) H.active = null;
        else if (!wasActive || wasActive !== r.active.id || H.active.status !== r.active.status) location.reload();
        renderGoing();
        renderLive();
        renderRecent();
        if (!S.search) renderPlay();
    }
    setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });

    render();
    // Chi era gia' in coda (pagina ricaricata) riprende la ricerca.
    if (H.queue) {
        S.mode = H.queue.mode;
        startSearch(H.queue.mode, Date.now() - H.queue.waited * 1000);
    }
    D.hydrate(document.getElementById('d2p-main'));
})();
