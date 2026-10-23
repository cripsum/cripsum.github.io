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
 * abbina e risponde «matched» con la partita. Dopo un minuto propone il bot
 * restando in coda.
 *
 * Fase 6: stagione in alto, «Le tue sfide» (sfide ricevute e mandate,
 * partite in differita), il foglio «Sfida un amico» (tavola 6), il bot
 * Incubo. Le sfide arrivano anche dal timbro personale (evento 'dc').
 *
 * Fase 8: la sfida del giorno, sotto «Le tue sfide», con il suo foglio.
 *
 * Fase T2: il riquadro della Torre sotto la sfida del giorno.
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
            friend: '<b>' + esc(T('Sfida un amico:', 'Challenge a friend:')) + '</b> ' + esc(T('adesso o in differita (24 ore a turno), le arriva nella campanella. Oppure una stanza con un codice. Non conta per la classifica.', 'now or turn-based (24 hours per turn); it shows up in their bell. Or a room with a code. Does not count for the ranking.')),
            bot: '<b>' + esc(T('Contro il bot:', 'Vs bot:')) + '</b> ' + esc(T('i tuoi livelli, quattro bot dal Facile all\'Incubo. Non conta per la classifica.', 'your own levels, four bots from Easy to Nightmare. Does not count for the ranking.')),
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
            const lv = { facile: T('Facile', 'Easy'), normale: T('Normale', 'Normal'), difficile: T('Difficile', 'Hard'), incubo: T('Incubo', 'Nightmare') };
            box.innerHTML = '<div class="d2p-opts"><span class="d2p-lab">' + esc(T('Bot', 'Bot')) + '</span><div class="d2p-tabs2" role="group">'
                + Object.keys(lv).map((k) => '<button type="button" data-level="' + k + '" aria-pressed="' + (k === S.level) + '"' + (k === S.level ? ' class="d2p-on"' : '') + '>' + esc(lv[k]) + '</button>').join('') + '</div></div>';
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
                + (long ? '<p class="d2p-hint">' + esc(T('Poca gente in coda adesso. Gioca col bot mentre aspetti: resti in coda e quando arriva qualcuno ti porto da lui.', 'Few people in queue right now. Play the bot while you wait: you stay in the queue and I take you to your opponent when one shows up.'))
                    + ' <button class="d2p-btn d2p-btn-sm" type="button" data-act="bot-now">' + esc(T('Gioca col bot mentre aspetti', 'Play the bot while waiting')) + '</button></p>' : '');
            return;
        }
        const label = { casual: T('Gioca Veloce', 'Play Quick'), ranked: T('Gioca Ranked', 'Play Ranked'), friend: T('Scegli un amico', 'Pick a friend'), bot: T('Gioca contro il bot', 'Play the bot') }[S.mode];
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
            + (S.mode === 'friend' ? codeOptions() + '<form class="d2p-join" id="d2p-join" autocomplete="off"><button class="d2p-btn" type="button" data-act="room"' + (disabled ? ' disabled' : '') + '>' + esc(T('Apri una stanza con codice', 'Open a code room')) + '</button>'
                + '<input id="d2p-code" maxlength="8" spellcheck="false" autocapitalize="characters" placeholder="' + esc(T('Codice', 'Code')) + '" aria-label="' + esc(T('Codice della stanza', 'Room code')) + '">'
                + '<button class="d2p-btn" type="submit"' + (!t || t.cards.length < 3 ? ' disabled' : '') + '>' + esc(T('Entra', 'Join')) + '</button></form>' : '');
    }
    const clock = (s) => Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    /** La stanza con codice, sotto il pulsante degli amici: livelli e limite di costo. */
    function codeOptions() {
        const tab = (key, val, on, label) => '<button type="button" data-' + key + '="' + val + '" aria-pressed="' + on + '"' + (on ? ' class="d2p-on"' : '') + '>' + esc(label) + '</button>';
        return '<div class="d2p-opts d2p-code-opts"><span class="d2p-lab">' + esc(T('Con un codice', 'With a code')) + '</span>'
            + '<span class="d2p-tabs2" role="group" aria-label="' + esc(T('Livelli', 'Levels')) + '">' + tab('levels', 'max', S.room.levels === 'max', T('Livelli pareggiati', 'Equal levels')) + tab('levels', 'own', S.room.levels === 'own', T('I vostri', 'Your own')) + '</span>'
            + '<span class="d2p-tabs2" role="group" aria-label="' + esc(T('Costo', 'Cost')) + '">' + tab('cost', '0', !S.room.cost, T('Costo libero', 'Any cost')) + tab('cost', '1', S.room.cost, T('Fino a ', 'Up to ') + CFG.budget) + '</span></div>';
    }

    async function play() {
        const t = team();
        if (!t || S.busy) return;
        S.busy = true;
        let r;
        if (S.mode === 'friend') { S.busy = false; openFriends(); return; }
        if (S.mode === 'bot') r = await api('bot', null, { slot: t.slot, level: S.level });
        else r = await api('queue', null, { action: 'join', mode: S.mode, slot: t.slot });
        S.busy = false;
        if (!r.ok) { fail(r); return; }
        if (r.match && (S.mode === 'bot' || r.status === 'matched')) { go(r.match); return; }
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
        let failures = 0;
        const tick = async () => {
            if (!S.search) return;
            const p = await api('queue', null, { action: 'ping' });
            if (!S.search) return;
            if (p.ok && p.status === 'matched') { say(T('Avversario trovato.', 'Opponent found.')); go(p.match); return; }
            if (p.ok && p.status === 'idle') { stopSearch(); D.toast(T('Ricerca finita.', 'Search ended.')); render(); return; }
            // Errori dal server: la ricerca continua, ma lo si dice (prima restava in silenzio).
            failures = p.ok ? 0 : failures + 1;
            if (failures === 3) D.toast((p.error || T('La ricerca non risponde.', 'The search is not responding.')) + ' ' + T('Riprovo…', 'Retrying…'), true);
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

    // ── Le tue sfide: partita in corso, stanza aperta, sfide, differita ──
    const avatar = (id, name, cls) => D.pfp(id, name, 'd2p-av' + (cls ? ' ' + cls : ''), 96);
    function leftText(s) {
        if (s === null || s === undefined) return '';
        if (s >= 3600) {
            const h = Math.floor(s / 3600);
            return h + T(h === 1 ? ' ora' : ' ore', ' h');
        }
        if (s >= 60) return Math.floor(s / 60) + ' min';
        return T('meno di un minuto', 'under a minute');
    }
    const chl = (av, mid, acts) => '<div class="d2p-chl">' + av + '<div>' + mid + '</div><div class="d2p-acts">' + acts + '</div></div>';
    function renderGoing() {
        const box = $('d2p-going');
        const rows = [];
        const a = H.active;
        if (a && a.status === 'waiting') {
            const left = Math.max(0, (a.expires || 0) - Date.now() / 1000);
            rows.push(chl('<span class="d2p-av">' + ic('lucchetto') + '</span>', '<strong>' + esc(T('La tua stanza è aperta', 'Your room is open')) + '</strong>'
                + '<span>' + esc(T('Codice ', 'Code ')) + '<b class="d2p-code">' + esc(a.code || '') + '</b> · ' + esc(T('scade tra ', 'expires in ')) + clock(Math.floor(left)) + '</span>',
                '<a class="d2p-btn d2p-btn-w d2p-btn-sm" href="' + esc(U.arena + '?m=' + a.id) + '">' + esc(T('Apri', 'Open')) + '</a>'
                + '<button class="d2p-btn d2p-btn-sm" type="button" data-act="close-room">' + esc(T('Chiudi', 'Close')) + '</button>'));
        } else if (a) {
            rows.push(chl(a.opponent_id !== null && a.opponent_id !== undefined ? avatar(a.opponent_id, a.opponent) : '<span class="d2p-av">' + ic('attacco') + '</span>', '<strong>' + (a.my_turn ? '<span class="d2p-badge">' + esc(T('Tocca a te', 'Your turn')) + '</span>' : '')
                + esc(a.opponent ? T('contro ', 'vs ') + a.opponent : T('Partita in corso', 'Match in progress')) + '</strong>'
                + '<span>' + esc(D.MODE[a.mode] || a.mode) + (a.turn ? ' · ' + T('turno ', 'turn ') + a.turn : '') + '</span>',
                '<a class="d2p-btn d2p-btn-w d2p-btn-sm" href="' + esc(U.arena + '?m=' + a.id) + '">' + esc(T('Torna in campo', 'Back to the match')) + '</a>'));
        }
        (H.async || []).forEach((g) => {
            rows.push(chl(avatar(g.opponent_id, g.opponent), '<strong>' + (g.my_turn ? '<span class="d2p-badge">' + esc(T('Tocca a te', 'Your turn')) + '</span>' + esc(T('contro ', 'vs ') + g.opponent) : esc(T('In attesa di ', 'Waiting for ') + g.opponent)) + '</strong>'
                + '<span>' + esc(T('In differita · turno ', 'Turn-based · turn ') + g.turn + (g.left !== null ? ' · ' + T('scade tra ', 'expires in ') + leftText(g.left) : '')) + '</span>',
                '<a class="d2p-btn ' + (g.my_turn ? 'd2p-btn-w ' : '') + 'd2p-btn-sm" href="' + esc(U.arena + '?m=' + g.id) + '">' + esc(g.my_turn ? T('Gioca', 'Play') : T('Guarda', 'View')) + '</a>'));
        });
        (H.challenges || []).forEach((c) => {
            const kind = (c.kind === 'async' ? T('In differita', 'Turn-based') : T('Live', 'Live')) + (c.levels === 'max' ? T(' · livelli pareggiati', ' · equal levels') : '');
            if (c.sent) {
                rows.push(chl(avatar(c.other_id, c.other), '<strong>' + esc(T('In attesa di ', 'Waiting for ') + c.other) + '</strong><span>' + esc(kind + ' · ' + T('scade tra ', 'expires in ') + leftText(c.left)) + '</span>',
                    (c.kind === 'live' && c.match ? '<a class="d2p-btn d2p-btn-sm" href="' + esc(U.arena + '?m=' + c.match) + '">' + esc(T('Apri', 'Open')) + '</a>' : '')
                    + '<button class="d2p-btn d2p-btn-sm" type="button" data-act="withdraw" data-id="' + c.id + '">' + esc(T('Ritira', 'Withdraw')) + '</button>'));
            } else {
                rows.push(chl(avatar(c.other_id, c.other), '<strong>' + esc(c.other + T(' ti sfida', ' challenges you')) + '</strong><span>' + esc(kind + ' · ' + T('scade tra ', 'expires in ') + leftText(c.left)) + '</span>',
                    '<button class="d2p-btn d2p-btn-w d2p-btn-sm" type="button" data-act="accept" data-id="' + c.id + '">' + esc(T('Accetta', 'Accept')) + '</button>'
                    + '<button class="d2p-btn d2p-btn-sm" type="button" data-act="decline" data-id="' + c.id + '">' + esc(T('No', 'No')) + '</button>'));
            }
        });
        box.hidden = false;
        box.innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(T('Le tue sfide', 'Your challenges')) + '</h2>'
            + '<button class="d2p-link" type="button" data-act="friends">' + esc(T('Sfida un amico', 'Challenge a friend')) + '</button></div>'
            + (rows.join('') || '<p class="d2p-mut">' + esc(T('Nessuna sfida in corso. Sfida un amico adesso, o in differita: un turno al giorno, quando potete.', 'No challenges going. Challenge a friend now, or turn-based: one turn a day, whenever you can.')) + '</p>');
        D.hydrate(box);
    }

    async function challengeAct(act, id) {
        const t = team();
        if (act === 'accept') {
            if (!t || t.cards.length < 3 || t.missing) { D.toast(T('Prima prepara la squadra.', 'Set up your team first.'), true); return; }
            const r = await api('challenge', null, { action: 'accept', id, slot: t.slot });
            if (!r.ok) { fail(r); refresh(); return; }
            go(r.match);
            return;
        }
        const r = await api('challenge', null, { action: act === 'withdraw' ? 'cancel' : 'decline', id });
        if (!r.ok) { fail(r); return; }
        H.challenges = r.challenges || [];
        renderGoing();
        say(act === 'withdraw' ? T('Sfida ritirata.', 'Challenge withdrawn.') : T('Sfida rifiutata.', 'Challenge declined.'));
    }

    /** Il timbro personale: una sfida nuova o rifiutata ('dc'), una partita partita ('dm'). */
    function watchPersonal(onChange) {
        let cursor = -1;
        const loop = async () => {
            if (!document.hidden) {
                const r = await fetch(CFG.rt + '?u=' + cursor, { credentials: 'same-origin', cache: 'no-store' }).then((x) => x.json()).catch(() => null);
                if (r && r.u) {
                    const fresh = cursor >= 0 && (r.u.events || []).some((e) => e.t === 'dc' || e.t === 'dm');
                    if (r.u.seq) cursor = r.u.seq;
                    if (fresh) onChange();
                }
            }
            setTimeout(loop, 5000);
        };
        loop();
    }

    // ── Rango, classifica, diretta, ultime ───────────────────────────────
    function renderRank() {
        const me = H.me;
        const r = me.rank;
        const next = r.next;
        const pct = next ? Math.max(4, Math.min(100, ((me.rating - r.min) / (next - r.min)) * 100)) : 100;
        const nextTier = next ? D.TIER[{ 1000: 'argento', 1200: 'oro', 1400: 'diamante', 1600: 'campione', 1900: 'leggenda' }[next]] || '' : '';
        const sm = H.season_me;
        const placing = H.season && sm && sm.placement > 0;
        $('d2p-rank').innerHTML = '<div class="d2p-rank">' + D.emblem(r.key, 'd2p-emb-l') + '<div><strong>' + esc(placing ? T('Piazzamento', 'Placement') : r.label) + '</strong><span class="d2p-num">'
            + (placing ? esc(T('ancora ' + sm.placement + ' Ranked per il rango', sm.placement + ' more Ranked for your rank')) : fmt(me.rating) + ' ' + esc(T('punti', 'points'))) + '</span></div></div>'
            + '<div class="d2p-bar d2p-rank-bar" style="--tc: var(--t-' + esc(r.key) + ')"><i style="width:' + pct.toFixed(1) + '%"></i></div>'
            + '<div class="d2p-rank-note">' + (next ? '<span>' + esc(nextTier + T(' a ', ' at ') + fmt(next)) + '</span><span class="d2p-num">' + esc(T('mancano ', '')) + fmt(next - me.rating) + esc(T('', ' to go')) + '</span>'
                : '<span>' + esc(T('Il rango più alto', 'The top rank')) + '</span>') + '</div>'
            + '<div class="d2p-rank-wl"><span><b class="d2p-num">' + fmt(me.wins) + '</b> ' + esc(T('vittorie', 'wins')) + '</span><span><b class="d2p-num">' + fmt(me.losses) + '</b> ' + esc(T('sconfitte', 'losses')) + '</span>'
            + '<span>' + esc(T('serie', 'streak')) + ' <b class="d2p-num">' + fmt(me.streak) + '</b></span></div>'
            + (me.games ? '' : '<p class="d2p-mut">' + esc(T('Gioca una Ranked per entrare in classifica. I punti sono quelli dei duelli di sempre.', 'Play a Ranked match to enter the ranking. Points carry over from the classic duels.')) + '</p>')
            + '<a class="d2p-link d2p-rank-more" href="' + esc(U.season) + '">' + esc(H.season ? T('Stagione e premi', 'Season and rewards') : T('Premi delle stagioni', 'Season rewards')) + ' ' + ic('freccia') + '</a>';
        D.hydrate($('d2p-rank'));
    }

    function renderBoard() {
        const b = H.board;
        if (S.board === null) S.board = b.friends.length > 1 ? 'friends' : 'all';
        const list = b[S.board];
        const meIn = list.some((x) => x.id === CFG.me);
        let rows = list.map((x, i) => '<div class="d2p-lb' + (x.id === CFG.me ? ' d2p-me' : '') + '"><span class="d2p-p">' + (i + 1) + '</span>' + D.pfp(x.id, x.username) + '<b>' + esc(x.username) + '</b><span class="d2p-d d2p-num">' + D.emblem(x.rank) + fmt(x.rating) + '</span></div>').join('');
        if (S.board === 'all' && !meIn && b.pos) {
            rows += '<div class="d2p-lb d2p-me d2p-gap"><span class="d2p-p">' + b.pos + '</span>' + D.pfp(CFG.me, T('Tu', 'You')) + '<b>' + esc(T('Tu', 'You')) + '</b><span class="d2p-d d2p-num">' + D.emblem(H.me.rank.key) + fmt(H.me.rating) + '</span></div>';
        }
        if (!list.length) rows = '<p class="d2p-mut">' + esc(T('Ancora nessuno in classifica.', 'Nobody in the ranking yet.')) + '</p>';
        if (S.board === 'friends' && b.friends.length <= 1) rows += '<p class="d2p-mut">' + esc(T('Quando i tuoi amici giocano compaiono qui.', 'Your friends show up here once they play.')) + '</p>';
        $('d2p-board').innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(T('Classifica', 'Ranking')) + '</h2><span class="d2p-tabs2" role="group">'
            + ['friends', 'all'].map((k) => '<button type="button" data-board="' + k + '" aria-pressed="' + (k === S.board) + '"' + (k === S.board ? ' class="d2p-on"' : '') + '>' + esc(k === 'friends' ? T('Amici', 'Friends') : T('Tutti', 'All')) + '</button>').join('')
            + '</span></div>' + rows;
    }

    function renderLive() {
        const rows = H.live.map((l) => '<div class="d2p-live"><span class="d2p-pair">' + D.pfp((l.ids || [])[0], l.players[0]) + D.pfp((l.ids || [])[1], l.players[1]) + '</span><div><strong>' + esc((l.players[0] || '?') + T(' contro ', ' vs ') + (l.players[1] || '?')) + '</strong>'
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
                + '<span><span class="d2p-hist-who">' + D.pfp(r.opponent_id, r.opponent) + '<span>' + esc(T('contro ', 'vs ') + r.opponent) + '</span></span><small>' + esc((r.daily ? T('Sfida del giorno', 'Daily challenge')
                    : r.tower ? T('Torre · piano ', 'Tower · floor ') + r.tower : D.MODE[r.mode] || r.mode) + ' · ' + D.ago(r.at)) + '</small></span>'
                + (d !== null && d !== undefined ? '<span class="d2p-dl d2p-num ' + (d > 0 ? 'd2p-up' : d < 0 ? 'd2p-dn' : '') + '">' + (d > 0 ? '+' : d < 0 ? '−' : '') + fmt(Math.abs(d)) + '</span>' : '<span></span>') + '</a>'
                + '<a class="d2p-ibtn" href="' + esc(U.replay + '?m=' + r.id) + '" aria-label="' + esc(T('Rivedi la partita', 'Watch the replay')) + '" title="' + esc(T('Rivedi la partita', 'Watch the replay')) + '">' + ic('replay') + '</a></div>';
        }).join('');
        $('d2p-recent').innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(T('Ultime partite', 'Recent matches')) + '</h2></div>'
            + (rows || '<p class="d2p-mut">' + esc(T('Le tue partite compariranno qui.', 'Your matches will show up here.')) + '</p>');
    }

    // ── Stagione, in alto ────────────────────────────────────────────────
    function renderSeason() {
        const box = $('d2p-season');
        if (!box) return;
        const s = H.season;
        if (!s) {
            box.innerHTML = '<a class="d2p-season" href="' + esc(U.season) + '"><p><span><b>' + esc(T('Stagione 0', 'Season 0')) + '</b> · ' + esc(T('la classifica di sempre', 'the all-time ranking')) + '</span>' + ic('freccia') + '</p></a>';
        } else {
            const left = s.days_left <= 1 ? T('ultimo giorno', 'last day') : T('ancora ' + s.days_left + ' giorni', s.days_left + ' days left');
            box.innerHTML = '<a class="d2p-season" href="' + esc(U.season) + '"><p><span><b>' + esc(T('Stagione ', 'Season ') + s.number) + '</b>' + (s.name ? ' · ' + esc(s.name) : '') + '</span><span>' + esc(left) + '</span></p>'
                + '<span class="d2p-bar"><i style="width:' + (s.progress * 100).toFixed(1) + '%"></i></span></a>';
        }
        D.hydrate(box);
    }

    function render() {
        renderSeason();
        renderModes();
        renderTeam();
        renderExtra();
        renderPlay();
        renderGoing();
        renderDaily();
        renderTower();
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
        if (b.dataset.levels) { S.room.levels = b.dataset.levels; store.set('room', S.room); renderPlay(); return; }
        if (b.dataset.cost) { S.room.cost = b.dataset.cost === '1'; store.set('room', S.room); renderPlay(); return; }
        if (b.dataset.board) { S.board = b.dataset.board; renderBoard(); return; }
        const act = b.dataset.act;
        if (act === 'play') { play(); return; }
        if (act === 'cancel') { stopSearch(); await api('queue', null, { action: 'leave' }); render(); return; }
        if (act === 'bot-now') {
            // Si resta in coda: l'arena continua a mandare il segnale e porta dall'avversario.
            const t = team();
            const r = await api('bot', null, { slot: t.slot, level: S.level, keep_queue: true });
            if (!r.ok) { fail(r); return; }
            stopSearch();
            go(r.match);
            return;
        }
        if (act === 'friends') { openFriends(); return; }
        if (act === 'daily') { openDaily(); return; }
        if (act === 'room') {
            const t = team();
            const r = await api('room', null, { action: 'create', slot: t.slot, levels: S.room.levels, cost_limit: S.room.cost });
            if (!r.ok) { fail(r); return; }
            go(r.match);
            return;
        }
        if (act === 'accept' || act === 'decline' || act === 'withdraw') { challengeAct(act, Number(b.dataset.id)); return; }
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
        H.challenges = r.challenges || [];
        H.async = r.async || [];
        H.season = r.season || null;
        H.season_me = r.season_me || null;
        const wasActive = H.active && H.active.id;
        if (!r.active) H.active = null;
        else if (!wasActive || wasActive !== r.active.id || H.active.status !== r.active.status) location.reload();
        renderGoing();
        renderLive();
        renderRecent();
        if (!S.search) renderPlay();
    }
    setInterval(refresh, 30000);
    watchPersonal(refresh);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });


    // ── Sfida del giorno (fase 8, tavola 1) ──────────────────────────────
    // Il riquadro con la squadra nemica, l'obiettivo e il tuo record; «Prova»
    // apre il foglio con le regole, le tue squadre (solo quelle che vanno
    // bene oggi) e la classifica del giorno.
    const DY = { slot: null, busy: false };
    const dayLabel = (d) => new Date(d + 'T12:00:00').toLocaleDateString(D.EN ? 'en-GB' : 'it-IT', { day: 'numeric', month: 'long' });
    function renderDaily() {
        const box = $('d2p-daily');
        const d = H.daily;
        if (!box) return;
        if (!d) { box.hidden = true; return; }
        box.hidden = false;
        const me = d.me;
        const bits = [];
        if (me && me.best !== null) bits.push(T('il tuo record: ' + me.best + ' turni', 'your best: ' + me.best + ' turns'));
        else if (me) bits.push(T('tentativi: ' + me.attempts, 'attempts: ' + me.attempts));
        bits.push(d.players ? T('ci hanno provato in ' + d.players, d.players + ' tried it') : T('nessuno ci ha ancora provato', 'nobody tried it yet'));
        box.innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(T('Sfida del giorno', 'Daily challenge')) + '</h2>'
            + '<button class="d2p-link" type="button" data-act="daily">' + esc(dayLabel(d.day)) + '</button></div>'
            + '<div class="d2p-daily"><div class="d2p-faces">' + d.enemy.map((c) => '<span style="--r:' + D.rarColor(c.rarity) + '">'
                + (c.img ? '<img src="' + esc(c.img) + '" alt="" loading="lazy" onerror="this.remove()">' : '') + '<i>' + esc(D.niceName(c.name).charAt(0)) + '</i></span>').join('') + '</div>'
            + '<div><strong>' + (me && me.completed ? ic('check') + ' ' : '') + esc(d.title) + '</strong>'
            + '<p>' + esc(d.goal ? d.goal_text + ' · ' + d.rules_text : d.rules_text) + '. ' + esc(bits.join(' · ').replace(/^./, (x) => x.toUpperCase())) + '.</p></div>'
            + '<button class="d2p-btn' + (me && me.completed ? '' : ' d2p-btn-w') + '" type="button" data-act="daily">' + esc(me ? T('Riprova', 'Try again') : T('Prova', 'Try it')) + '</button></div>';
        D.hydrate(box);
    }
    /** Perche' una squadra salvata non va bene oggi (o null). */
    function dailyError(t) {
        const r = H.daily.rules;
        if (!t || t.cards.length < 3 || t.missing) return T('Squadra incompleta', 'Incomplete team');
        if (r.rarities && r.rarities.length && t.cards.some((c) => !r.rarities.includes(c.rarity))) return T('Rarità non ammesse oggi', 'Rarities not allowed today');
        if (r.cost_max > 0 && t.cost > r.cost_max) return T('Costa ' + t.cost + ', oggi al massimo ' + r.cost_max, 'Costs ' + t.cost + ', today ' + r.cost_max + ' at most');
        return null;
    }
    function openDaily() {
        if (!H.daily) return;
        const ok = H.teams.filter((t) => !dailyError(t));
        if (!ok.some((t) => t.slot === DY.slot)) DY.slot = ok.some((t) => t.slot === S.slot) ? S.slot : (ok[0] || {}).slot ?? null;
        F.back = document.activeElement;
        sheet().hidden = false;
        document.body.classList.add('d2p-lock');
        drawDaily();
        const first = $('d2p-sheet-p').querySelector('button[data-dslot]:not([disabled]), button[data-close]');
        if (first) first.focus();
    }
    function drawDaily() {
        const d = H.daily;
        const me = d.me;
        const board = d.board.map((x, i) => '<div class="d2p-lb' + (x.id === CFG.me ? ' d2p-me' : '') + '"><span class="d2p-p">' + (i + 1) + '</span>'
            + D.pfp(x.id, x.username) + '<b>' + esc(x.username) + '</b><span class="d2p-d d2p-num">' + (x.completed ? ic('check') : '')
            + esc(x.best !== null ? T(x.best + ' turni', x.best + ' turns') : T('non ancora', 'not yet')) + '</span></div>').join('');
        const mine = me && me.pos && !d.board.some((x) => x.id === CFG.me)
            ? '<div class="d2p-lb d2p-me d2p-gap"><span class="d2p-p">' + me.pos + '</span>' + D.pfp(CFG.me, T('Tu', 'You')) + '<b>' + esc(T('Tu', 'You')) + '</b><span class="d2p-d d2p-num">' + (me.completed ? ic('check') : '') + esc(T(me.best + ' turni', me.best + ' turns')) + '</span></div>' : '';
        const teams = H.teams.map((t) => {
            const err = dailyError(t);
            return '<button type="button" class="d2p-fr d2p-dteam" data-dslot="' + t.slot + '" aria-pressed="' + (t.slot === DY.slot) + '"' + (err ? ' disabled' : '') + '>'
                + '<span class="d2p-av">' + t.slot + '</span><span><b>' + esc(t.name) + '</b><small>' + esc(err || t.cards.map((c) => D.niceName(c.name)).join(', ')) + '</small></span>'
                + '<span class="d2p-num">' + t.cost + '</span></button>';
        }).join('');
        const anyOk = H.teams.some((t) => !dailyError(t));
        $('d2p-sheet-p').innerHTML = '<div class="d2p-sheet-h"><button type="button" class="d2p-ibtn" data-close="1" aria-label="' + esc(T('Chiudi', 'Close')) + '">' + ic('x') + '</button>'
            + '<h3 id="d2p-sheet-t">' + esc(T('Sfida del giorno', 'Daily challenge')) + ' · ' + esc(dayLabel(d.day)) + '</h3></div>'
            + '<p class="d2p-dtitle">' + esc(d.title) + '</p>'
            + '<p class="d2p-sheet-note">' + ic('coppa') + '<span>' + esc(d.goal_text + '. ' + d.rules_text + '.')
                + (d.reward > 0 ? ' ' + esc(T('La prima volta che centri l\'obiettivo: +' + d.reward + ' Godos.', 'The first time you hit the goal: +' + d.reward + ' Godos.')) : '') + '</span></p>'
            + '<span class="d2p-lab">' + esc(T('Contro', 'Against')) + '</span><div class="d2p-cards d2p-dcards">' + d.enemy.map((c) => D.card(c, { lv: true })).join('') + '</div>'
            + '<span class="d2p-lab">' + esc(T('La tua squadra', 'Your team')) + '</span><div class="d2p-fl">' + (teams || '') + '</div>'
            + (anyOk ? '' : '<p class="d2p-mut">' + esc(T('Nessuna delle tue squadre va bene oggi. ', 'None of your teams fits today. ')) + '<a class="d2p-link" href="' + esc(U.team) + '">' + esc(T('Prepara una squadra', 'Build a team')) + ' ' + ic('freccia') + '</a></p>')
            + '<span class="d2p-lab">' + esc(T('Classifica di oggi', 'Today\'s ranking')) + '</span>'
            + (board + mine || '<p class="d2p-mut">' + esc(T('Ancora nessuno: la prima vittoria è in cima.', 'Nobody yet: the first win goes to the top.')) + '</p>')
            + '<button class="d2p-btn d2p-btn-w d2p-btn-big" type="button" data-dplay="1"' + (DY.slot === null || DY.busy ? ' disabled' : '') + '>' + esc(T('Gioca la sfida', 'Play the challenge')) + '</button>';
        D.hydrate($('d2p-sheet-p'));
    }

    // ── La Torre (fase T2, tavola 21) ────────────────────────────────────
    // Il piano in cui sei con le facce della squadra da battere e la barra
    // dei cento piani; «Sali» apre la pagina della Torre col foglio del piano.
    function renderTower() {
        const box = $('d2p-tower');
        const tw = H.tower;
        if (!box) return;
        if (!tw) { box.hidden = true; return; }
        box.hidden = false;
        const f = tw.floor;
        const BOT = { facile: T('Facile', 'Easy'), normale: T('Normale', 'Normal'), difficile: T('Difficile', 'Hard'), incubo: T('Incubo', 'Nightmare') };
        const lv = f.levels[0] === f.levels[1] ? T('livello ', 'level ') + f.levels[0] : T('livelli ', 'levels ') + f.levels[0] + '–' + f.levels[1];
        const who = f.title || f.team.map((c) => D.niceName(c.name)).join(', ');
        const line = tw.done ? T('Hai superato tutti i ' + tw.floors + ' piani: si rigiocano senza premio.', 'You beat all ' + tw.floors + ' floors: replays give no rewards.')
            : who + ', ' + lv + ' · bot ' + (BOT[f.bot] || f.bot) + '. ' + T('La prima volta: +' + fmt(f.reward) + ' Godos.', 'First time: +' + fmt(f.reward) + ' Godos.');
        const extra = tw.vouchers ? ' · ' + (tw.vouchers === 1 ? T('1 potenziamento da usare', '1 level-up to use') : T(tw.vouchers + ' potenziamenti da usare', tw.vouchers + ' level-ups to use')) : '';
        box.innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(T('La Torre', 'The Tower')) + '</h2>'
            + '<a class="d2p-link" href="' + esc(U.tower) + '">' + esc(T('Tutti i piani', 'All floors')) + ' ' + ic('freccia') + '</a></div>'
            + '<div class="d2p-daily"><div class="d2p-faces">' + f.team.map((c) => '<span style="--r:' + D.rarColor(c.rarity) + '">'
                + (c.img ? '<img src="' + esc(c.img) + '" alt="" loading="lazy" onerror="this.remove()">' : '') + '<i>' + esc(D.niceName(c.name).charAt(0)) + '</i></span>').join('') + '</div>'
            + '<div><strong>' + (tw.done ? ic('check') + ' ' : '') + esc((tw.done ? T('In cima', 'At the top') : T('Piano ', 'Floor ') + f.n) + ' · ' + f.sector) + '</strong>'
            + '<p>' + esc(line) + '</p>'
            + '<div class="d2p-tw-mini"><span class="d2p-bar"><i style="width:' + (tw.best / tw.floors * 100).toFixed(1) + '%"></i></span><span>'
                + esc(T(tw.best + ' piani su ' + tw.floors, tw.best + ' of ' + tw.floors + ' floors') + extra) + '</span></div></div>'
            + '<a class="d2p-btn' + (tw.done ? '' : ' d2p-btn-w') + '" href="' + esc(U.tower + (tw.done ? '' : '?piano=' + f.n)) + '">' + esc(tw.done ? T('Apri', 'Open') : T('Sali', 'Climb')) + '</a></div>';
        D.hydrate(box);
    }

    // ── Foglio «Sfida un amico» (tavola 6) ───────────────────────────────
    const F = { list: null, teams: [], pick: null, kind: 'live', levels: 'own', q: '', slot: null, sending: false, back: null };
    function sheet() {
        let el = $('d2p-sheet');
        if (!el) {
            el = document.createElement('div');
            el.id = 'd2p-sheet';
            el.className = 'd2p-sheet';
            el.hidden = true;
            el.innerHTML = '<div class="d2p-sheet-bg" data-close="1"></div><section class="d2p-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="d2p-sheet-t" id="d2p-sheet-p"></section>';
            document.body.appendChild(el);
            el.addEventListener('click', onSheetClick);
            el.addEventListener('input', (e) => { if (e.target.id === 'd2p-fq') { F.q = e.target.value; drawFriends(true); } });
            el.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeFriends(); });
        }
        return el;
    }
    async function openFriends() {
        const t = team();
        if (!t || t.cards.length < 3 || t.missing) { D.toast(T('Prima prepara la squadra.', 'Set up your team first.'), true); return; }
        F.slot = t.slot;
        F.back = document.activeElement;
        const el = sheet();
        el.hidden = false;
        document.body.classList.add('d2p-lock');
        $('d2p-sheet-p').innerHTML = '<p class="d2p-mut">' + esc(T('Carico i tuoi amici…', 'Loading your friends…')) + '</p>';
        const r = await api('friends');
        if (!r.ok) { fail(r); closeFriends(); return; }
        F.list = r.friends || [];
        F.teams = (r.teams || []).filter((x) => x.team && x.team.length === 3);
        if (!F.teams.some((x) => x.slot === F.slot) && F.teams[0]) F.slot = F.teams[0].slot;
        if (!F.list.some((f) => f.id === F.pick && !f.open)) F.pick = (F.list.find((f) => !f.open) || {}).id || null;
        drawFriends();
        const q = $('d2p-fq');
        if (q) q.focus();
    }
    function closeFriends() {
        const el = $('d2p-sheet');
        if (el) el.hidden = true;
        document.body.classList.remove('d2p-lock');
        if (F.back && F.back.focus) F.back.focus();
    }
    function seenText(f) {
        if (f.online) return T('Online adesso', 'Online now');
        if (f.idle === null) return '';
        const s = f.idle;
        if (s < 3600) return T('Visto ' + Math.max(1, Math.floor(s / 60)) + ' min fa', 'Seen ' + Math.max(1, Math.floor(s / 60)) + ' min ago');
        if (s < 86400) { const h = Math.floor(s / 3600); return T('Visto ' + (h === 1 ? 'un\'ora fa' : h + ' ore fa'), 'Seen ' + h + ' h ago'); }
        const d = Math.floor(s / 86400);
        return T('Visto ' + (d === 1 ? 'ieri' : d + ' giorni fa'), 'Seen ' + (d === 1 ? 'yesterday' : d + ' days ago'));
    }
    function drawFriends(listOnly) {
        const panel = $('d2p-sheet-p');
        const q = F.q.trim().toLowerCase();
        const shown = F.list.filter((f) => !q || f.username.toLowerCase().includes(q));
        const rows = shown.map((f) => '<button type="button" class="d2p-fr" data-friend="' + f.id + '" aria-pressed="' + (f.id === F.pick) + '"' + (f.open ? ' disabled' : '') + '>'
            + avatar(f.id, f.username, f.online ? 'd2p-online' : '')
            + '<span><b>' + esc(f.username) + '</b><small>' + esc([f.open ? T('Sfida già aperta', 'Challenge already open') : seenText(f), f.games ? D.TIER[f.rank] : ''].filter(Boolean).join(' · ')) + '</small></span>'
            + (f.games ? D.emblem(f.rank) : '<span></span>') + '</button>').join('');
        const listHtml = rows || '<p class="d2p-mut">' + esc(F.list.length ? T('Nessun amico con questo nome.', 'No friend with this name.') : T('Non hai ancora amici sul sito: aggiungili dalla pagina Amici.', 'You have no friends on the site yet: add them from the Friends page.'))
            + (F.list.length ? '' : ' <a class="d2p-link" href="/' + CFG.lang + '/amici">' + esc(T('Amici', 'Friends')) + ' ' + ic('freccia') + '</a>') + '</p>';
        if (listOnly && $('d2p-fl')) {
            $('d2p-fl').innerHTML = listHtml;
            D.hydrate($('d2p-fl'));
            return;
        }
        const pick = F.list.find((f) => f.id === F.pick) || null;
        const tabs = (key, opts) => '<span class="d2p-tabs2" role="group">' + opts.map(([v, l]) => '<button type="button" data-' + key + '="' + v + '" aria-pressed="' + (F[key] === v) + '"' + (F[key] === v ? ' class="d2p-on"' : '') + '>' + esc(l) + '</button>').join('') + '</span>';
        panel.innerHTML = '<div class="d2p-sheet-h"><button type="button" class="d2p-ibtn" data-close="1" aria-label="' + esc(T('Chiudi', 'Close')) + '">' + ic('x') + '</button><h3 id="d2p-sheet-t">' + esc(T('Sfida un amico', 'Challenge a friend')) + '</h3></div>'
            + '<label class="d2p-search">' + ic('cerca') + '<input id="d2p-fq" type="search" autocomplete="off" value="' + esc(F.q) + '" placeholder="' + esc(T('Cerca tra gli amici', 'Search your friends')) + '" aria-label="' + esc(T('Cerca tra gli amici', 'Search your friends')) + '"></label>'
            + '<span class="d2p-lab">' + esc(T('Amici', 'Friends')) + '</span><div class="d2p-fl" id="d2p-fl">' + listHtml + '</div>'
            + '<span class="d2p-lab">' + esc(T('Come', 'How')) + '</span>'
            + '<div class="d2p-seg d2p-seg-s">' + [['live', T('Adesso, live', 'Now, live')], ['async', T('In differita', 'Turn-based')]].map(([v, l]) => '<button type="button" data-kind="' + v + '" aria-pressed="' + (F.kind === v) + '"' + (F.kind === v ? ' class="d2p-on"' : '') + '>' + esc(l) + '</button>').join('') + '</div>'
            + '<div class="d2p-srow"><span>' + esc(T('Tempo per turno', 'Time per turn')) + '</span><span class="d2p-mut0">' + esc(F.kind === 'live' ? T('45 secondi', '45 seconds') : T('24 ore', '24 hours')) + '</span></div>'
            + '<div class="d2p-srow"><span>' + esc(T('Livelli', 'Levels')) + '</span>' + tabs('levels', [['own', T('I vostri', 'Your own')], ['max', T('Pareggiati', 'Equal')]]) + '</div>'
            + '<div class="d2p-srow"><span>' + esc(T('Squadra', 'Team')) + '</span>' + (F.teams.length ? tabs('slot', F.teams.map((x) => [x.slot, x.name])) : '<span class="d2p-mut0">—</span>') + '</div>'
            + '<p class="d2p-sheet-note">' + ic('campana') + '<span>' + esc(pick ? T(pick.username + ' la vede nella campanella e nei Duelli. ', pick.username + ' sees it in the bell and in Duels. ') : '')
            + esc(F.kind === 'live' ? T('Aspetti nell\'arena finché accetta (10 minuti). ', 'You wait in the arena until they accept (10 minutes). ') : T('Vale un giorno; poi ognuno ha 24 ore per mossa. ', 'It lasts a day; then each move has 24 hours. '))
            + esc(T('Non conta per la classifica.', 'Does not count for the ranking.')) + '</span></p>'
            + '<button class="d2p-btn d2p-btn-w d2p-btn-big" type="button" data-send="1"' + (!pick || pick.open || F.sending ? ' disabled' : '') + '>'
            + esc(pick ? T('Sfida ', 'Challenge ') + pick.username : T('Scegli un amico', 'Pick a friend')) + '</button>';
        D.hydrate(panel);
    }
    async function onSheetClick(e) {
        const b = e.target.closest('[data-close], button');
        if (!b || b.disabled) return;
        if (b.dataset.close) { closeFriends(); return; }
        if (b.dataset.dslot) { DY.slot = Number(b.dataset.dslot); drawDaily(); return; }
        if (b.dataset.dplay) {
            DY.busy = true;
            drawDaily();
            const r = await api('daily', null, { action: 'start', slot: DY.slot });
            DY.busy = false;
            if (!r.ok) { fail(r); drawDaily(); return; }
            go(r.match);
            return;
        }
        if (b.dataset.friend) { F.pick = Number(b.dataset.friend); drawFriends(); return; }
        if (b.dataset.kind) { F.kind = b.dataset.kind; drawFriends(); return; }
        if (b.dataset.levels) { F.levels = b.dataset.levels; drawFriends(); return; }
        if (b.dataset.slot) { F.slot = Number(b.dataset.slot); drawFriends(); return; }
        if (b.dataset.send) {
            F.sending = true;
            drawFriends();
            const r = await api('challenge', null, { action: 'send', to: F.pick, kind: F.kind, levels: F.levels, slot: F.slot });
            F.sending = false;
            if (!r.ok) { fail(r); drawFriends(); return; }
            if (r.kind === 'live' && r.match) { go(r.match); return; }
            const who = (F.list.find((f) => f.id === F.pick) || {}).username || '';
            closeFriends();
            D.toast(T('Sfida mandata a ', 'Challenge sent to ') + who);
            refresh();
        }
    }

    render();
    // Chi era gia' in coda (pagina ricaricata) riprende la ricerca.
    if (H.queue) {
        S.mode = H.queue.mode;
        startSearch(H.queue.mode, Date.now() - H.queue.waited * 1000);
    }
    D.hydrate(document.getElementById('d2p-main'));
})();
