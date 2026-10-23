/**
 * Duelli 2.0 — la Torre (tavole 21 e 22).
 *
 * In alto il piano in cui sei: la squadra da battere coi potenziamenti, la
 * regola, il premio, la potenza tua e quella consigliata, «Combatti». Sotto
 * i dieci tratti: la barra sceglie il tratto, la colonna mostra i suoi dieci
 * piani dall'alto (il boss) in giu', fatti, il tuo e chiusi. Ogni piano apre
 * il suo foglio (squadra, regola, potenza, premi, le tue squadre con quelle
 * fuori regola spente). A lato il tuo record, i potenziamenti gratuiti da
 * usare e la classifica, amici o tutti.
 *
 * I dati arrivano nella pagina (duel_tower_page); il foglio degli altri
 * piani da api/duel/tower.php?floor=N. «Combatti» manda POST start; il
 * potenziamento POST voucher. ?piano=N apre subito il foglio del piano N.
 */
(function () {
    'use strict';
    const D = window.D2P;
    const { T, $, esc, fmt, ic, api, CFG } = D;
    const H = D.DATA;
    const U = CFG.urls;
    const FLOORS = H.floors.length;

    const store = {
        get(k, d) { try { const v = localStorage.getItem('d2.' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
        set(k, v) { try { localStorage.setItem('d2.' + k, JSON.stringify(v)); } catch (e) { /* niente */ } },
    };

    const sectorOf = (n) => Math.floor((Math.max(1, n) - 1) / 10) + 1;
    const floorAt = (n) => H.floors[n - 1] || null;
    const S = {
        sector: sectorOf(H.me.next),
        slot: store.get('tslot', null),
        board: H.board_friends.length > 1 ? 'friends' : 'all',
        sheets: {},
        sheet: null, // { n, data, slot, busy }
        pick: null, // { voucher, pid, all, busy }
        back: null,
    };
    if (H.sheet) S.sheets[H.sheet.n] = H.sheet;

    const BOT = { facile: T('Facile', 'Easy'), normale: T('Normale', 'Normal'), difficile: T('Difficile', 'Hard'), incubo: T('Incubo', 'Nightmare') };
    const go =(id) => { location.href = U.arena + '?m=' + id; };

    // ── Testi ────────────────────────────────────────────────────────────
    function names(list) {
        const n = list.map((c) => D.niceName(c.name || c));
        return n.length < 2 ? n.join('') : n.slice(0, -1).join(', ') + T(' e ', ' and ') + n[n.length - 1];
    }
    function levelText(team) {
        const lv = team.map((c) => c.level);
        const lo = Math.min(...lv);
        const hi = Math.max(...lv);
        return lo === hi ? T('livello ', 'level ') + lo : T('livelli ', 'levels ') + lo + '–' + hi;
    }
    /** «fino a Leggendario», o «di qualsiasi rarità» per il buono del piano 100. */
    function upTo(max) {
        return max === 'theone' ? T('di qualsiasi rarità', 'of any rarity') : T('fino a ', 'up to ') + D.rarName(max);
    }
    function ruleIcon(rule) {
        if (rule.terrain) return rule.terrain;
        if (rule.turns) return 'orologio';
        return 'lucchetto';
    }
    function say(text) {
        const el = $('d2p-say');
        el.textContent = '';
        setTimeout(() => { el.textContent = text; }, 30);
    }
    const face = (c) => '<span style="--r:' + D.rarColor(c.rarity) + '">' + (c.img ? '<img src="' + esc(c.img) + '" alt="" loading="lazy" onerror="this.remove()">' : '')
        + '<i>' + esc(D.niceName(c.name).charAt(0)) + '</i></span>';
    const faces = (team) => '<div class="d2p-faces">' + team.map(face).join('') + '</div>';
    const boonChip = (text) => (text ? '<span class="d2p-tw-boon">' + ic('fulmine') + esc(text) + '</span>' : '');
    /** Una carta nemica col potenziamento sotto (i nemici non hanno costo). */
    const enemyCard = (c, boon) => D.card(c, { cost: false }).replace(/<\/div>$/, boonChip(boon) + '</div>');

    // ── Le squadre ───────────────────────────────────────────────────────
    /** La squadra scelta per un foglio: quella salvata se va bene, sennò la prima che va bene. */
    function pickSlot(sheet, want) {
        const ok = (sheet.teams || []).filter((t) => !t.error);
        if (ok.some((t) => t.slot === want)) return want;
        return ok.length ? ok[0].slot : null;
    }
    const teamOf = (sheet, slot) => (sheet.teams || []).find((t) => t.slot === slot) || null;

    // ── In alto: il record ───────────────────────────────────────────────
    function renderProg() {
        const best = H.me.best;
        $('d2p-tw-prog').innerHTML = '<div class="d2p-tw-prog"><p><span>' + esc(T('Piano più alto', 'Highest floor')) + '</span><b>' + best + ' / ' + FLOORS + '</b></p>'
            + '<span class="d2p-bar" role="img" aria-label="' + esc(T(best + ' piani su ' + FLOORS, best + ' of ' + FLOORS + ' floors')) + '"><i style="width:' + (best / FLOORS * 100).toFixed(1) + '%"></i></span></div>';
    }

    // ── Il piano in cui sei ──────────────────────────────────────────────
    function renderNow() {
        const box = $('d2p-tw-now');
        if (H.me.done) {
            box.innerHTML = '<div class="d2p-tw-now-h"><h2>' + esc(T('In cima alla Torre', 'At the top of the Tower')) + '</h2></div>'
                + '<p class="d2p-tw-done">' + ic('check') + '<span>' + esc(T('Hai superato tutti i ' + FLOORS + ' piani. Puoi rigiocarli quando vuoi, senza premio.', 'You beat all ' + FLOORS + ' floors. You can replay them any time, without rewards.')) + '</span></p>'
                + '<div class="d2p-play" style="margin-top:22px"><button class="d2p-btn d2p-btn-w d2p-btn-big" type="button" data-floor="' + FLOORS + '">' + esc(T('Rigioca il piano ' + FLOORS, 'Replay floor ' + FLOORS)) + '</button></div>';
            D.hydrate(box);
            return;
        }
        const f = floorAt(H.me.next);
        const sh = S.sheets[f.n] || null;
        const head = '<div class="d2p-tw-now-h"><h2>' + esc(T('Piano ', 'Floor ')) + f.n + ' <span>· ' + esc(H.sectors[f.sector - 1]) + '</span></h2>'
            + '<button class="d2p-link" type="button" data-floor="' + f.n + '">' + esc(T('Il foglio del piano', 'Floor details')) + ' ' + ic('freccia') + '</button></div>';
        if (!sh) {
            box.innerHTML = head + '<div class="d2p-team">' + faces(f.team) + '<div class="d2p-team-info"><p class="d2p-tw-fact">' + ic('robot')
                + '<span>' + esc(T('Questo piano ha un problema: lo sistema lo staff.', 'This floor has a problem: the staff will fix it.')) + '</span></p></div></div>';
            D.hydrate(box);
            return;
        }
        S.slot = pickSlot(sh, S.slot);
        const t = teamOf(sh, S.slot);
        const facts = [];
        if (sh.boss && sh.title) facts.push(['bandiera', '<b>' + esc(T('Boss', 'Boss')) + '</b> · ' + esc(sh.title)]);
        facts.push(['robot', esc(T('Bot ', 'Bot ')) + '<b>' + esc(BOT[sh.bot] || sh.bot) + '</b>, ' + esc(levelText(sh.enemy)) + (sh.boon_text ? ', <b>' + esc(sh.boon_text) + '</b>' : '') + '.']);
        if (sh.rule_text) facts.push([ruleIcon(sh.rule), '<b>' + esc(sh.rule_text) + '.</b>' + (sh.terrain ? ' ' + esc(sh.terrain.d) : '')]);
        facts.push(['coppa', esc(T('La prima volta ', 'First time: ')) + '<b>+' + fmt(sh.reward) + ' Godos</b>'
            + (sh.voucher ? esc(T(' e ', ' and ')) + '<b>' + esc(sh.vouchers > 1 ? T(sh.vouchers + ' potenziamenti gratuiti', sh.vouchers + ' free level-ups') : T('un potenziamento gratuito', 'a free level-up')) + '</b> ' + esc(upTo(sh.voucher)) : '') + '.']);
        facts.push(['grafico', esc(T('Potenza: ', 'Power: ')) + (t ? esc(T('la tua squadra ', 'your team ')) + '<b>' + fmt(t.power) + '</b>, ' : '') + esc(T('consigliata ', 'recommended ')) + '<b>' + fmt(sh.power) + '</b>.']);
        const anyOk = S.slot !== null;
        const status = t ? esc(T('Squadra: ', 'Team: ')) + '<b>' + esc(t.name) + '</b> · ' + esc(names(t.names))
            : (sh.teams.length ? esc(T('Nessuna tua squadra va bene per questo piano.', 'None of your teams fits this floor.')) : esc(T('Non hai ancora una squadra.', 'You have no team yet.')))
                + ' <a class="d2p-link" href="' + esc(U.team) + '">' + esc(T('Prepara una squadra', 'Build a team')) + ' ' + ic('freccia') + '</a>';
        box.innerHTML = head
            + '<div class="d2p-team"><div class="d2p-cards">' + sh.enemy.map((c) => enemyCard(c, sh.boon_text)).join('') + '</div>'
            + '<div class="d2p-team-info"><div class="d2p-tw-facts">' + facts.map(([i, html]) => '<p class="d2p-tw-fact">' + ic(i) + '<span>' + html + '</span></p>').join('') + '</div></div></div>'
            + '<div class="d2p-play"><button class="d2p-btn d2p-btn-w d2p-btn-big" type="button" data-act="fight"' + (anyOk ? '' : ' disabled') + '>' + esc(T('Combatti', 'Fight')) + '</button>'
            + (sh.teams.length > 1 ? '<button class="d2p-btn d2p-btn-big" type="button" data-floor="' + f.n + '">' + esc(T('Cambia squadra', 'Change team')) + '</button>' : '')
            + '<span class="d2p-status">' + status + '</span></div>';
        D.hydrate(box);
    }

    // ── La salita: tratti e piani ────────────────────────────────────────
    function floorRow(f) {
        const cls = 'd2p-tw-f is-' + f.state + (f.boss ? ' is-boss' : '');
        const sub = [esc((f.boss ? T('Boss · ', 'Boss · ') : '') + levelText(f.team).replace(/^./, (x) => x.toUpperCase()) + ' · bot ' + (BOT[f.bot] || f.bot))];
        if (f.boon_text) sub.push('<span class="d2p-tw-tag">' + ic('fulmine') + esc(f.boon_text) + '</span>');
        if (f.rule_text) sub.push('<span class="d2p-tw-tag">' + ic(ruleIcon(f.rule)) + esc(f.rule_text) + '</span>');
        let right;
        if (f.state === 'done') {
            right = '<b>' + ic('check') + ' ' + esc(T('Fatto', 'Done')) + '</b>' + (f.turns ? esc(T(f.turns + ' turni', f.turns + ' turns')) : '');
        } else {
            right = '<b>+' + fmt(f.reward) + ' Godos</b>';
            if (f.voucher) right += esc((f.vouchers > 1 ? T(f.vouchers + ' potenziamenti ', f.vouchers + ' level-ups ') : T('e un potenziamento ', 'and a level-up ')) + upTo(f.voucher));
            else if (f.state === 'now') right += esc(T('la prima volta', 'first time'));
        }
        const who = f.title || names(f.team);
        const stateText = f.state === 'done' ? T('fatto', 'done') : f.state === 'now' ? T('il tuo piano', 'your floor') : T('chiuso', 'locked');
        return '<li class="' + cls + '"><button type="button" class="d2p-tw-hit" data-floor="' + f.n + '" aria-label="' + esc(T('Piano ', 'Floor ') + f.n + ': ' + who + ', ' + stateText) + '"></button>'
            + '<span class="d2p-tw-dot"></span><span class="d2p-tw-n">' + f.n + '</span>'
            + '<div class="d2p-tw-what">' + faces(f.team) + '<div><strong>' + esc(who) + '</strong><small>' + sub.join('') + '</small></div></div>'
            + '<div class="d2p-tw-r">' + right + '</div></li>';
    }
    function renderClimb() {
        const s = S.sector;
        const from = (s - 1) * 10 + 1;
        const to = Math.min(FLOORS, s * 10);
        const best = H.me.best;
        const rail = H.sectors.map((name, i) => {
            const n = i + 1;
            const lo = (n - 1) * 10;
            const cls = best >= n * 10 ? 'is-done' : best >= lo ? 'is-now' : '';
            const p = Math.max(0, Math.min(100, (best - lo) * 10));
            return '<button type="button" class="' + cls + '" style="--p:' + p + '%" data-sector="' + n + '" aria-pressed="' + (n === s) + '" aria-label="'
                + esc(name + ', ' + T('piani ', 'floors ') + (lo + 1) + '–' + n * 10) + '">' + (lo + 1) + '–' + n * 10 + '</button>';
        }).join('');
        const rows = [];
        for (let n = to; n >= from; n--) rows.push(floorRow(floorAt(n)));
        $('d2p-tw-climb').innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(H.sectors[s - 1]) + '</h2><span class="d2p-link">' + esc(T('Piani ', 'Floors ')) + from + '–' + to + '</span></div>'
            + '<div class="d2p-tw-sectors" role="group" aria-label="' + esc(T('Tratti della Torre', 'Tower sections')) + '">' + rail + '</div>'
            + '<ol class="d2p-tw-floors">' + rows.join('') + '</ol>';
        D.hydrate($('d2p-tw-climb'));
    }

    // ── A lato: record, potenziamenti, classifica ────────────────────────
    function renderMe() {
        const me = H.me;
        $('d2p-tw-me').innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(T('La tua salita', 'Your climb')) + '</h2></div>'
            + '<div class="d2p-tw-stats"><div><b>' + me.best + '</b><span>' + esc(T('piano più alto', 'highest floor')) + '</span></div>'
            + '<div><b>' + me.bosses + ' / ' + Math.floor(FLOORS / 10) + '</b><span>' + esc(T('boss battuti', 'bosses beaten')) + '</span></div>'
            + '<div><b>' + fmt(me.attempts) + '</b><span>' + esc(T('partite', 'matches')) + '</span></div></div>'
            + (me.pos ? '<p class="d2p-mut">' + esc(T(me.pos + 'º nella classifica della Torre.', '#' + me.pos + ' in the Tower ranking.')) + '</p>'
                : '<p class="d2p-mut">' + esc(T('Supera il primo piano per entrare in classifica.', 'Beat the first floor to enter the ranking.')) + '</p>');
    }
    function renderUse() {
        const rows = H.vouchers.map((v) => '<div class="d2p-tw-vou"><span class="d2p-av">' + ic('piu') + '</span><div><strong>' + esc(T('Potenziamento gratuito', 'Free level-up')) + '</strong>'
            + '<small>' + esc(T('Boss del piano ' + v.floor + ' · un personaggio ', 'Floor ' + v.floor + ' boss · one character ') + upTo(v.max)) + '</small></div>'
            + '<button class="d2p-btn d2p-btn-sm" type="button" data-voucher="' + v.id + '">' + esc(T('Usa', 'Use')) + '</button></div>').join('');
        $('d2p-tw-use').innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(T('Da usare', 'To use')) + '</h2></div>'
            + (rows || '<p class="d2p-mut" style="margin:0">' + esc(T('Ogni boss ti dà un potenziamento gratuito: un tuo personaggio sale di un livello senza spendere copie.', 'Every boss gives you a free level-up: one of your characters goes up a level without spending copies.')) + '</p>');
        D.hydrate($('d2p-tw-use'));
    }
    function renderBoard() {
        const list = S.board === 'friends' ? H.board_friends : H.board;
        const meIn = list.some((x) => x.id === CFG.me);
        let rows = list.map((x, i) => '<div class="d2p-lb' + (x.id === CFG.me ? ' d2p-me' : '') + '"><span class="d2p-p">' + (i + 1) + '</span>' + D.pfp(x.id, x.username)
            + '<b>' + esc(x.username) + '</b><span class="d2p-d d2p-num">' + esc(x.floor >= FLOORS ? T('in cima', 'top') : T('piano ', 'floor ') + x.floor) + '</span></div>').join('');
        if (S.board === 'all' && !meIn && H.me.pos) {
            rows += '<div class="d2p-lb d2p-me d2p-gap"><span class="d2p-p">' + H.me.pos + '</span>' + D.pfp(CFG.me, T('Tu', 'You')) + '<b>' + esc(T('Tu', 'You')) + '</b><span class="d2p-d d2p-num">'
                + esc(T('piano ', 'floor ') + H.me.best) + '</span></div>';
        }
        if (!list.length) rows = '<p class="d2p-mut">' + esc(T('Ancora nessuno: il primo piano superato ti mette in cima.', 'Nobody yet: beating the first floor puts you on top.')) + '</p>';
        else if (S.board === 'friends' && list.length <= 1) rows += '<p class="d2p-mut">' + esc(T('Quando i tuoi amici salgono compaiono qui.', 'Your friends show up here once they climb.')) + '</p>';
        $('d2p-tw-board').innerHTML = '<div class="d2p-sec-t"><h2 class="d2p-h2">' + esc(T('Classifica', 'Ranking')) + '</h2><span class="d2p-tabs2" role="group">'
            + ['friends', 'all'].map((k) => '<button type="button" data-board="' + k + '" aria-pressed="' + (k === S.board) + '"' + (k === S.board ? ' class="d2p-on"' : '') + '>'
                + esc(k === 'friends' ? T('Amici', 'Friends') : T('Tutti', 'All')) + '</button>').join('') + '</span></div><div class="d2p-tw-board">' + rows + '</div>';
    }

    // ── Il foglio (piano o potenziamento) ────────────────────────────────
    function sheetEl() {
        let el = $('d2p-sheet');
        if (!el) {
            el = document.createElement('div');
            el.id = 'd2p-sheet';
            el.className = 'd2p-sheet';
            el.hidden = true;
            el.innerHTML = '<div class="d2p-sheet-bg" data-close="1"></div><section class="d2p-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="d2p-sheet-t" id="d2p-sheet-p"></section>';
            document.body.appendChild(el);
            el.addEventListener('click', onSheetClick);
            el.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSheet(); });
        }
        return el;
    }
    function showSheet(focusSel) {
        if (sheetEl().hidden) S.back = document.activeElement;
        sheetEl().hidden = false;
        document.body.classList.add('d2p-lock');
        const first = $('d2p-sheet-p').querySelector(focusSel || 'button[data-close]');
        if (first) first.focus();
    }
    function closeSheet() {
        const el = $('d2p-sheet');
        if (el) el.hidden = true;
        document.body.classList.remove('d2p-lock');
        S.sheet = null;
        S.pick = null;
        if (S.back && S.back.focus && document.contains(S.back)) S.back.focus();
    }
    const sheetHead = (title) => '<div class="d2p-sheet-h"><button type="button" class="d2p-ibtn" data-close="1" aria-label="' + esc(T('Chiudi', 'Close')) + '">' + ic('x') + '</button>'
        + '<h3 id="d2p-sheet-t">' + esc(title) + '</h3></div>';

    async function openFloor(n) {
        if (!floorAt(n)) return;
        sheetEl();
        S.pick = null;
        S.sheet = { n, data: S.sheets[n] || null, slot: S.slot, busy: false };
        if (!S.sheet.data) {
            $('d2p-sheet-p').innerHTML = sheetHead(T('Piano ', 'Floor ') + n) + '<p class="d2p-mut">' + esc(T('Un attimo…', 'One moment…')) + '</p>';
            showSheet();
            const r = await api('tower', { floor: n });
            if (!S.sheet || S.sheet.n !== n) return;
            if (!r.ok) { closeSheet(); D.toast(r.error || T('Il piano non si è caricato.', 'The floor failed to load.'), true); return; }
            S.sheets[n] = r.sheet;
            S.sheet.data = r.sheet;
        }
        S.sheet.slot = pickSlot(S.sheet.data, S.slot);
        drawFloor();
        showSheet('button[data-play]:not([disabled]), button[data-close]');
    }
    function drawFloor() {
        const x = S.sheet.data;
        const n = x.n;
        const t = teamOf(x, S.sheet.slot);
        const panel = $('d2p-sheet-p');
        panel.className = 'd2p-sheet-panel d2p-tw-sheet' + (n === FLOORS ? ' d2p-tw-top' : '');
        const note = esc(T('Bot ', 'Bot ')) + '<b>' + esc(BOT[x.bot] || x.bot) + '</b>, ' + esc(levelText(x.enemy)) + (x.boon_text ? ', <b>' + esc(x.boon_text) + '</b>' : '') + '.'
            + (x.rule_text ? ' <b>' + esc(x.rule_text) + '.</b>' + (x.terrain ? ' ' + esc(T('Per ' + x.terrain.g + ' giri: ', 'For ' + x.terrain.g + ' rounds: ') + x.terrain.d) : '') : '');
        const scale = Math.max(x.power, t ? t.power : 0) * 1.15 || 1;
        const bar = (cls, label, v) => '<div class="d2p-tw-pw ' + cls + '"><span>' + esc(label) + '</span><span class="d2p-bar"><i style="width:' + (v / scale * 100).toFixed(1) + '%"></i></span><b>' + fmt(v) + '</b></div>';
        const prizes = [];
        if (x.cleared) {
            prizes.push(['check', esc(T('Già superato: si rigioca senza premio.', 'Already beaten: you replay it without rewards.'))]);
        } else {
            prizes.push(['coppa', '<b>+' + fmt(x.reward) + ' Godos</b> ' + esc(T('la prima volta', 'the first time'))]);
            if (x.voucher) prizes.push(['piu', '<b>' + esc(x.vouchers > 1 ? T(x.vouchers + ' potenziamenti gratuiti', x.vouchers + ' free level-ups') : T('Un potenziamento gratuito', 'A free level-up')) + '</b> ' + esc(upTo(x.voucher))]);
            if (n === FLOORS) prizes.push(['bandiera', esc(T('Il badge ', 'The ')) + '<b>' + esc(T('In cima alla Torre', 'Top of the Tower')) + '</b>' + esc(T(' sul profilo', ' badge on your profile'))]);
        }
        const teams = x.teams.map((tm) => '<button type="button" class="d2p-fr d2p-dteam" data-tslot="' + tm.slot + '" aria-pressed="' + (tm.slot === S.sheet.slot) + '"' + (tm.error ? ' disabled' : '') + '>'
            + '<span class="d2p-av">' + tm.slot + '</span><span><b>' + esc(tm.name) + '</b><small>' + esc(tm.error || names(tm.names)) + '</small></span>'
            + '<span class="d2p-num" title="' + esc(T('Potenza', 'Power')) + '">' + fmt(tm.power) + '</span></button>').join('');
        let play;
        if (!x.open) play = T('Prima batti il piano ' + (H.me.best + 1), 'Beat floor ' + (H.me.best + 1) + ' first');
        else if (x.cleared) play = T('Rigioca, senza premio', 'Replay, no rewards');
        else play = T('Combatti', 'Fight');
        panel.innerHTML = sheetHead(T('Piano ', 'Floor ') + n + ' · ' + x.sector)
            + '<p class="d2p-dtitle">' + esc(x.title || names(x.enemy)) + '</p>'
            + '<p class="d2p-sheet-note">' + ic(x.rule_text ? ruleIcon(x.rule) : 'robot') + '<span>' + note + '</span></p>'
            + '<span class="d2p-lab">' + esc(T('Contro', 'Against')) + '</span><div class="d2p-cards d2p-dcards">' + x.enemy.map((c) => enemyCard(c, x.boon_text)).join('') + '</div>'
            + '<span class="d2p-lab">' + esc(T('Potenza', 'Power')) + '</span><div class="d2p-tw-power">'
            + (t ? bar('is-you', T('La tua squadra', 'Your team'), t.power) : '') + bar('is-them', T('Consigliata', 'Recommended'), x.power) + '</div>'
            + '<span class="d2p-lab">' + esc(T('Premi', 'Rewards')) + '</span><div class="d2p-tw-prizes">' + prizes.map(([i, h]) => '<p class="d2p-tw-prize">' + ic(i) + '<span>' + h + '</span></p>').join('') + '</div>'
            + '<span class="d2p-lab">' + esc(T('La tua squadra', 'Your team')) + '</span>'
            + (teams ? '<div class="d2p-fl">' + teams + '</div>' : '')
            + (x.teams.some((tm) => !tm.error) ? '' : '<p class="d2p-mut">' + esc(x.teams.length ? T('Nessuna delle tue squadre va bene per questo piano. ', 'None of your teams fits this floor. ') : T('Non hai ancora una squadra salvata. ', 'You have no saved team yet. '))
                + '<a class="d2p-link" href="' + esc(U.team) + '">' + esc(T('Prepara una squadra', 'Build a team')) + ' ' + ic('freccia') + '</a></p>')
            + '<button class="d2p-btn d2p-btn-w d2p-btn-big" type="button" data-play="1"' + (!x.open || S.sheet.slot === null || S.sheet.busy ? ' disabled' : '') + '>' + esc(play) + '</button>';
        D.hydrate(panel);
    }

    async function fight(n, slot) {
        const r = await api('tower', null, { action: 'start', floor: n, slot });
        if (r.ok) { go(r.match); return true; }
        if (r.code === 'IN_MATCH' && r.match) { go(r.match); return true; }
        D.toast(r.error || T('Qualcosa è andato storto.', 'Something went wrong.'), true);
        return false;
    }

    // ── Il potenziamento gratuito ────────────────────────────────────────
    function openPick(id) {
        const v = H.vouchers.find((x) => x.id === id);
        if (!v) return;
        sheetEl();
        S.sheet = null;
        S.pick = { voucher: v, pid: null, all: false, busy: false };
        drawPick();
        showSheet('button[data-pid], button[data-close]');
    }
    function drawPick() {
        const v = S.pick.voucher;
        const max = H.tiers[v.max];
        const list = H.pick.filter((c) => c.tier <= max && c.level < CFG.level_max);
        const shown = S.pick.all ? list : list.slice(0, 24);
        const chosen = list.find((c) => c.id === S.pick.pid) || null;
        const panel = $('d2p-sheet-p');
        panel.className = 'd2p-sheet-panel';
        panel.innerHTML = sheetHead(T('Potenziamento gratuito', 'Free level-up'))
            + '<p class="d2p-sheet-note">' + ic('piu') + '<span>' + esc(T('Boss del piano ' + v.floor + '. Un tuo personaggio sale di un livello senza spendere copie: ', 'Floor ' + v.floor + ' boss. One of your characters goes up a level without spending copies: ')
                + upTo(v.max) + T(', non uno già al livello massimo.', ', not one already at max level.')) + '</span></p>'
            + (list.length ? '<div class="d2p-cards d2p-tw-pick-grid">' + shown.map((c) => D.card(c, { cost: false, tag: 'button', attrs: 'type="button" data-pid="' + c.id + '" aria-pressed="' + (c.id === S.pick.pid) + '"' })
                .replace(/<\/button>$/, '<span class="d2p-sub">Lv ' + c.level + ' → <b>' + (c.level + 1) + '</b></span></button>')).join('') + '</div>'
                + (list.length > shown.length ? '<button class="d2p-link" type="button" data-all="1" style="margin-top:12px">' + esc(T('Mostra tutti (' + list.length + ')', 'Show all (' + list.length + ')')) + '</button>' : '')
                : '<p class="d2p-mut">' + esc(T('Nessun tuo personaggio si può potenziare con questo: tienilo per dopo.', 'None of your characters can use this one: keep it for later.')) + '</p>')
            + '<button class="d2p-btn d2p-btn-w d2p-btn-big" type="button" data-use="1"' + (!chosen || S.pick.busy ? ' disabled' : '') + '>'
            + esc(chosen ? T('Potenzia ', 'Level up ') + D.niceName(chosen.name) : T('Scegli un personaggio', 'Pick a character')) + '</button>';
        D.hydrate(panel);
    }
    async function useVoucher() {
        const p = S.pick;
        p.busy = true;
        drawPick();
        const r = await api('tower', null, { action: 'voucher', voucher: p.voucher.id, pid: p.pid });
        p.busy = false;
        if (!r.ok) { D.toast(r.error || T('Qualcosa è andato storto.', 'Something went wrong.'), true); drawPick(); return; }
        const c = H.pick.find((x) => x.id === r.pid);
        if (c) c.level = r.level;
        H.vouchers = r.vouchers || H.vouchers.filter((x) => x.id !== p.voucher.id);
        const text = (c ? D.niceName(c.name) : '') + T(' sale al livello ', ' goes up to level ') + r.level;
        closeSheet();
        D.toast(text);
        say(text);
        renderUse();
        // La potenza delle squadre e' cambiata: si rilegge il foglio del piano in cui sei.
        S.sheets = {};
        if (!H.me.done) {
            const s = await api('tower', { floor: H.me.next });
            if (s.ok) { S.sheets[H.me.next] = s.sheet; renderNow(); }
        }
    }

    async function onSheetClick(e) {
        const b = e.target.closest('[data-close], button');
        if (!b || b.disabled) return;
        if (b.dataset.close) { closeSheet(); return; }
        if (b.dataset.tslot) {
            S.sheet.slot = Number(b.dataset.tslot);
            S.slot = S.sheet.slot;
            store.set('tslot', S.slot);
            drawFloor();
            renderNow();
            return;
        }
        if (b.dataset.play) {
            S.sheet.busy = true;
            drawFloor();
            const ok = await fight(S.sheet.n, S.sheet.slot);
            if (!ok && S.sheet) { S.sheet.busy = false; drawFloor(); }
            return;
        }
        if (b.dataset.pid) { S.pick.pid = Number(b.dataset.pid); drawPick(); return; }
        if (b.dataset.all) { S.pick.all = true; drawPick(); return; }
        if (b.dataset.use) useVoucher();
    }

    // ── Clic sulla pagina ────────────────────────────────────────────────
    $('d2p-main').addEventListener('click', async (e) => {
        const b = e.target.closest('button');
        if (!b || b.disabled) return;
        if (b.dataset.floor) { openFloor(Number(b.dataset.floor)); return; }
        if (b.dataset.sector) { S.sector = Number(b.dataset.sector); renderClimb(); return; }
        if (b.dataset.board) { S.board = b.dataset.board; renderBoard(); return; }
        if (b.dataset.voucher) { openPick(Number(b.dataset.voucher)); return; }
        if (b.dataset.act === 'fight') {
            b.disabled = true;
            if (!(await fight(H.me.next, S.slot))) b.disabled = false;
        }
    });

    renderProg();
    renderNow();
    renderClimb();
    renderMe();
    renderUse();
    renderBoard();
    D.hydrate($('d2p-main'));

    // ?piano=N: il foglio di quel piano, col suo tratto sotto.
    const want = Number(new URLSearchParams(location.search).get('piano') || new URLSearchParams(location.search).get('floor') || 0);
    if (want >= 1 && want <= FLOORS) {
        S.sector = sectorOf(want);
        renderClimb();
        openFloor(want);
    }
})();
