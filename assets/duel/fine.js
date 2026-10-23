/**
 * Duelli 2.0 — la fine partita (tavola 5).
 *
 * Esito; in Ranked i punti con la barra che si riempie e la soglia del rango
 * («Promosso a…» quando la passi); l'MVP della partita; i numeri di tutte e
 * sei le carte. Rivincita (all'avversario arriva la richiesta), replay,
 * ritorno alla lobby. Se l'avversario chiede la rivincita lo dice qui.
 *
 * Fase T2 (tavola 23): dopo un piano della Torre il titolo e' il piano
 * («Piano 47», «Boss battuto»), con i Godos, il piano dopo e, dopo un boss,
 * «Chi potenzi?» per il potenziamento gratuito. Se perdi: «Riprova», il
 * consiglio sulla potenza, «Cambia squadra».
 */
(function () {
    'use strict';
    const D = window.D2P;
    const { T, $, esc, fmt, ic, api, CFG } = D;
    const R = D.DATA;
    const U = CFG.urls;
    const TIERS = [[0, 'bronzo'], [1000, 'argento'], [1200, 'oro'], [1400, 'diamante'], [1600, 'campione'], [1900, 'leggenda']];
    const tierAt = (pts) => TIERS.filter((t) => pts >= t[0]).pop();

    const players = (side) => R.players[side] || R.players[String(side)] || {};
    const mine = R.side;
    const watcher = mine === null || mine === undefined;
    const other = watcher ? null : 1 - mine;

    const TW = R.tower;
    function title() {
        if (TW) {
            if (TW.cleared && TW.top && TW.first) return [T('In cima alla Torre', 'Top of the Tower'), ''];
            if (TW.cleared && TW.boss && TW.first) return [T('Boss battuto', 'Boss beaten'), ''];
            if (TW.cleared) return [T('Piano ', 'Floor ') + TW.n, ''];
            if (TW.won) return [T('Fuori tempo', 'Too slow'), 'd2p-lose'];
            return [T('Sconfitta', 'Defeat'), 'd2p-lose'];
        }
        if (R.winner === null || R.winner === undefined) return [T('Pareggio', 'Draw'), ''];
        if (watcher) return [T('Vince ', 'Winner: ') + (players(R.winner).username || '?'), ''];
        return R.winner === mine ? [T('Vittoria', 'Victory'), ''] : [T('Sconfitta', 'Defeat'), 'd2p-lose'];
    }
    function duration(s) {
        if (!s) return '';
        const m = Math.floor(s / 60);
        return m ? m + ' min ' + (s % 60) + ' s' : (s % 60) + ' s';
    }
    const REASON = {
        ko: T('Tutte le carte avversarie sono KO.', 'All enemy cards are KO.'),
        limit: T('Limite di turni: vince chi ha più vita in percentuale.', 'Turn limit: the team with more HP % wins.'),
        forfeit: T('Abbandono.', 'Forfeit.'),
        timeout: R.async ? T('Tempo scaduto due volte.', 'Timed out twice.') : T('Tempo scaduto tre volte.', 'Timed out three times.'),
    };

    function rating() {
        const r = R.rating;
        if (!r) return '';
        const up = r.delta >= 0;
        // La barra va dalla soglia piu' bassa tra prima e dopo alla prossima.
        const lo = Math.min(r.rank_before.min, r.rank.min);
        const top = r.rank.next || (r.rank.min + 300);
        const hi = Math.max(top, r.rank_before.next || top);
        const frac = (v) => Math.max(0, Math.min(1, (v - lo) / (hi - lo)));
        const a = up ? frac(r.before) : frac(r.after);
        const b = up ? frac(r.after) : frac(r.before);
        const crossed = r.rank.key !== r.rank_before.key;
        const mark = crossed ? frac(up ? r.rank.min : r.rank_before.min) : null;
        const nextKey = r.rank.next ? tierAt(r.rank.next)[1] : null;
        return '<div class="d2p-rate" style="--tc: var(--t-' + esc(r.rank.key) + ')">' + D.emblem(r.rank.key) + '<div>'
            + '<div class="d2p-rate-l1"><strong class="d2p-num">' + fmt(r.after) + '</strong><span class="d2p-d d2p-num' + (up ? '' : ' d2p-dn') + '">' + (r.delta > 0 ? '+' : r.delta < 0 ? '−' : '±') + fmt(Math.abs(r.delta)) + '</span>'
            + '<span>' + esc(T('da ', 'from ')) + fmt(r.before) + '</span></div>'
            + '<div class="d2p-track' + (up ? '' : ' d2p-loss') + '" id="d2p-track" data-a="' + a.toFixed(3) + '" data-b="' + b.toFixed(3) + '" role="img" aria-label="'
            + esc(fmt(r.before) + ' → ' + fmt(r.after)) + '"><b></b><i></i>' + (mark !== null ? '<em style="position:absolute;top:-6px;bottom:-6px;width:2px;background:var(--tx);left:' + (mark * 100).toFixed(1) + '%"></em>' : '') + '</div>'
            + '<div class="d2p-track-n"><span>' + esc(crossed ? r.rank_before.label + ' · ' + fmt(r.rank_before.min) : r.rank.label) + '</span>'
            + (mark !== null ? '<span class="d2p-track-m" style="left:' + (mark * 100).toFixed(1) + '%">' + esc((up ? r.rank : r.rank_before).label + ' · ' + fmt(up ? r.rank.min : r.rank_before.min)) + '</span>' : '')
            + '<span>' + (nextKey ? esc(D.TIER[nextKey] + ' · ' + fmt(r.rank.next)) : esc(T('Il rango più alto', 'The top rank'))) + '</span></div>'
            + '</div></div>'
            + (crossed ? '<span class="d2p-promo' + (up ? '' : ' d2p-demote') + '" style="--tc: var(--t-' + esc(r.rank.key) + ')">' + ic(up ? 'check' : 'freccia') + ' '
                + esc((up ? T('Promosso a ', 'Promoted to ') : T('Sceso a ', 'Down to ')) + r.rank.label) + '</span>' : '')
            + (r.delta === 0 ? '<p class="d2p-mut">' + esc(T('Oggi contro questo avversario le Ranked non contano più.', 'Ranked games against this opponent no longer count today.')) + '</p>' : '');
    }

    function facts() {
        const rows = [];
        // Fase 6: i Godos pagati con questa partita e la stagione.
        (R.rewards || []).forEach((r) => {
            if (r.kind === 'daily_win') rows.push(['coppa', T('Prima vittoria del giorno', 'First win of the day'), '+' + fmt(r.amount) + ' Godos']);
            if (r.kind === 'daily_ch') rows.push(['coppa', T('Obiettivo della sfida del giorno', 'Daily challenge goal'), '+' + fmt(r.amount) + ' Godos']);
            if (r.kind === 'tower' && TW) rows.push(['coppa', T('Prima volta al piano ' + TW.n, 'First time on floor ' + TW.n), '+' + fmt(r.amount) + ' Godos']);
        });
        if (TW) towerFacts(rows);
        // Fase 8: com'e' andata la sfida del giorno, e dove sei in classifica.
        if (R.daily) {
            const d = R.daily;
            const res = !d.won ? T('persa', 'lost') : d.completed ? T('centrata in ' + d.turns + ' turni', 'done in ' + d.turns + ' turns') : T('vinta in ' + d.turns + ' turni, obiettivo ' + d.goal, 'won in ' + d.turns + ' turns, goal ' + d.goal);
            rows.push([d.completed ? 'check' : 'orologio', T('Sfida del giorno', 'Daily challenge'), res + (d.me && d.me.pos ? ' · ' + T(d.me.pos + 'º in classifica', '#' + d.me.pos + ' today') : '')]);
        }
        if (R.season) {
            const placing = R.season.games < R.season.placement;
            rows.push(['calendario', T('Stagione ', 'Season ') + R.season.n + (placing ? T(' · piazzamento', ' · placement') : ''),
                placing ? R.season.games + ' / ' + R.season.placement : T('conta per la stagione', 'counts for the season')]);
        }
        if (!watcher && R.winner === mine && R.streak >= 2) rows.push(['fulmine', T('Serie di vittorie', 'Win streak'), fmt(R.streak)]);
        if (R.mode === 'bot' && !R.daily && !TW) rows.push(['robot', T('Contro il bot', 'Vs bot'), T('non conta per la classifica', 'does not count for the ranking')]);
        if (R.mode === 'casual') rows.push(['dadi', T('Veloce', 'Quick'), T('non conta per la classifica', 'does not count for the ranking')]);
        if (R.mode === 'private') rows.push(R.async ? ['orologio', T('In differita', 'Turn-based'), T('non conta per la classifica', 'does not count for the ranking')]
            : ['lucchetto', T('Stanza o sfida', 'Room or challenge'), T('non conta per la classifica', 'does not count for the ranking')]);
        if (!rows.length) return '';
        return '<div class="d2p-rew">' + rows.map((r) => '<div>' + ic(r[0]) + '<span>' + esc(r[1]) + '</span><b>' + esc(r[2]) + '</b></div>').join('') + '</div>';
    }

    // ── La Torre (fase T2, tavola 23) ────────────────────────────────────
    const upTo = (max) => (max === 'theone' ? T('di qualsiasi rarità', 'of any rarity') : T('fino a ', 'up to ') + D.rarName(max));
    const levelText = (f) => (f.levels[0] === f.levels[1] ? T('livello ', 'level ') + f.levels[0] : T('livelli ', 'levels ') + f.levels[0] + '–' + f.levels[1]);
    function towerFacts(rows) {
        if (TW.cleared && TW.first && TW.voucher) {
            rows.push(['piu', TW.top ? T('Due potenziamenti gratuiti', 'Two free level-ups') : T('Potenziamento gratuito', 'Free level-up'), upTo(TW.voucher)]);
        }
        // Fase T4: il badge del piano 100.
        if (TW.cleared && TW.first && TW.top && TW.badge) rows.push(['check', T('Badge «In cima alla Torre»', 'Badge «Top of the Tower»'), T('sul profilo', 'on your profile')]);
        if (TW.cleared && !TW.first) rows.push(['check', T('Già superato prima', 'Already beaten before'), T('senza premio', 'no rewards')]);
        if (TW.won && !TW.cleared) rows.push(['orologio', T('Vinta in ' + R.turns + ' turni', 'Won in ' + R.turns + ' turns'), T('il piano chiede entro ' + TW.turns_max, 'the floor asks for ' + TW.turns_max + ' at most')]);
        if (!TW.cleared) {
            rows.push(['dadi', T('Tentativi su questo piano', 'Attempts on this floor'), fmt(TW.attempts)]);
            if (!TW.replay) rows.push(['coppa', T('Il premio resta lì', 'The reward is still there'), '+' + fmt(TW.reward) + ' Godos']);
        }
        if (TW.cleared && TW.next) {
            rows.push(['freccia', T('Il prossimo: piano ', 'Next: floor ') + TW.next.n + ' · ' + (TW.next.title || TW.next.team.map((c) => D.niceName(c.name)).join(', ')), levelText(TW.next)]);
        }
    }
    /** Perche' non e' andata, e cosa provare. */
    function towerWhy() {
        // Dopo un abbandono o il tempo scaduto la potenza non c'entra.
        if (!TW || TW.cleared || (!TW.won && ['forfeit', 'timeout'].includes(R.reason))) return '';
        const p = TW.power;
        const text = TW.won
            ? T('Questo piano va vinto entro ' + TW.turns_max + ' turni: serve una squadra che chiuda prima.', 'This floor must be won within ' + TW.turns_max + ' turns: you need a team that finishes faster.')
            : p.you < p.rec
                ? T('La tua squadra ha potenza ' + fmt(p.you) + ', quella consigliata è ' + fmt(p.rec) + ': un livello in più a uno dei tre di solito basta.',
                    'Your team has ' + fmt(p.you) + ' power, the recommended is ' + fmt(p.rec) + ': one more level on one of the three usually does it.')
                : T('La tua squadra ha potenza ' + fmt(p.you) + ', non meno della consigliata (' + fmt(p.rec) + '): riprova, o prova un\'altra squadra.',
                    'Your team has ' + fmt(p.you) + ' power, no less than the recommended ' + fmt(p.rec) + ': try again, or try another team.');
        return '<p class="d2p-res-why">' + esc(text) + '</p>';
    }
    function towerActs() {
        const replay = '<a class="d2p-btn d2p-btn-big" href="' + esc(U.replay + '?m=' + R.id) + '">' + ic('replay') + ' ' + esc(T('Rivedi la partita', 'Watch the replay')) + '</a>';
        const back = (w) => '<a class="d2p-btn d2p-btn-big' + (w ? ' d2p-btn-w' : '') + '" href="' + esc(U.tower) + '">' + esc(T('Torna alla Torre', 'Back to the Tower')) + '</a>';
        if (TW.cleared) {
            return TW.next ? '<button class="d2p-btn d2p-btn-w d2p-btn-big" type="button" id="d2p-tw-next">' + esc(T('Piano ', 'Floor ') + TW.next.n) + '</button>' + replay + back(false)
                : back(true) + replay;
        }
        return '<button class="d2p-btn d2p-btn-w d2p-btn-big" type="button" id="d2p-rematch">' + esc(T('Riprova', 'Try again')) + '</button>'
            + '<a class="d2p-btn d2p-btn-big" href="' + esc(U.tower + '?piano=' + TW.n) + '">' + esc(T('Cambia squadra', 'Change team')) + '</a>' + back(false);
    }
    /** Il piano dopo con la stessa squadra; se la regola non la vuole, il foglio del piano. */
    async function nextFloor() {
        const b = $('d2p-tw-next');
        b.disabled = true;
        const team = R.units.filter((u) => u.side === mine).map((u) => u.pid);
        const r = await api('tower', null, { action: 'start', floor: TW.next.n, team });
        if (r.ok || (r.code === 'IN_MATCH' && r.match)) { location.href = U.arena + '?m=' + r.match; return; }
        if (r.code === 'TOWER_TEAM') { location.href = U.tower + '?piano=' + TW.next.n; return; }
        b.disabled = false;
        D.toast(r.error || T('Qualcosa è andato storto.', 'Something went wrong.'), true);
    }

    // «Chi potenzi?»: i buoni di questo boss, uno alla volta (il piano 100 ne da' due).
    const P = { pid: null, all: false, busy: false, done: [], on: !!(TW && TW.cleared && TW.vouchers && TW.vouchers.length) };
    function drawPick() {
        const box = $('d2p-tw-pick');
        if (!box) return;
        const v = TW.vouchers[0];
        const done = P.done.map((t) => '<p class="d2p-tw-done">' + ic('check') + '<span>' + esc(t) + '</span></p>').join('');
        if (!P.on || !v) {
            box.innerHTML = done;
            box.hidden = !done;
            D.hydrate(box);
            return;
        }
        const list = TW.pick.filter((c) => c.level < CFG.level_max);
        const shown = P.all ? list : list.slice(0, 10);
        const chosen = list.find((c) => c.id === P.pid) || null;
        box.innerHTML = '<h2 class="d2p-h2">' + esc(T('Chi potenzi?', 'Who gets the level-up?')) + '</h2>'
            + '<p>' + esc(T('Un tuo personaggio sale di un livello senza spendere copie, ', 'One of your characters goes up a level without spending copies, ') + upTo(v.max)
                + T(' e non uno già al livello massimo. Puoi anche sceglierlo più tardi dalla Torre.', ', not one already at max level. You can also pick later from the Tower.')
                + (TW.vouchers.length > 1 ? T(' Ne hai ' + TW.vouchers.length + '.', ' You have ' + TW.vouchers.length + '.') : '')) + '</p>'
            + done
            + (list.length ? '<div class="d2p-cards d2p-tw-pick-grid">' + shown.map((c) => D.card(c, { cost: false, tag: 'button', attrs: 'type="button" data-tw-pid="' + c.id + '" aria-pressed="' + (c.id === P.pid) + '"' })
                .replace(/<\/button>$/, '<span class="d2p-sub">Lv ' + c.level + ' → <b>' + (c.level + 1) + '</b></span></button>')).join('') + '</div>'
                + (list.length > shown.length ? '<p style="margin:-8px 0 18px"><button class="d2p-link" type="button" data-tw-all="1">' + esc(T('Mostra tutti (' + list.length + ')', 'Show all (' + list.length + ')')) + '</button></p>' : '')
                : '<p class="d2p-mut">' + esc(T('Nessun tuo personaggio si può potenziare con questo: lo trovi nella Torre quando ti serve.', 'None of your characters can use this now: it waits in the Tower.')) + '</p>')
            + '<div class="d2p-tw-pick-acts"><button class="d2p-btn d2p-btn-w d2p-btn-big" type="button" data-tw-use="1"' + (!chosen || P.busy ? ' disabled' : '') + '>'
            + esc(chosen ? T('Potenzia ', 'Level up ') + D.niceName(chosen.name) : T('Scegli un personaggio', 'Pick a character')) + '</button>'
            + '<button class="d2p-btn d2p-btn-big" type="button" data-tw-later="1">' + esc(T('Più tardi', 'Later')) + '</button></div>';
        box.hidden = false;
        D.hydrate(box);
    }
    function endPick() {
        P.on = false;
        drawPick();
        $('d2p-acts').hidden = false;
    }
    async function onPick(e) {
        const b = e.target.closest('button');
        if (!b || b.disabled || !P.on) return;
        if (b.dataset.twPid) { P.pid = Number(b.dataset.twPid); drawPick(); return; }
        if (b.dataset.twAll) { P.all = true; drawPick(); return; }
        if (b.dataset.twLater) { P.done.push(T('Il potenziamento ti aspetta nella Torre, in «Da usare».', 'The level-up waits for you in the Tower, under «To use».')); endPick(); return; }
        if (!b.dataset.twUse) return;
        P.busy = true;
        drawPick();
        const r = await api('tower', null, { action: 'voucher', voucher: TW.vouchers[0].id, pid: P.pid });
        P.busy = false;
        if (!r.ok) { D.toast(r.error || T('Qualcosa è andato storto.', 'Something went wrong.'), true); drawPick(); return; }
        const c = TW.pick.find((x) => x.id === r.pid);
        if (c) c.level = r.level;
        P.done.push((c ? D.niceName(c.name) : '') + T(' sale al livello ', ' goes up to level ') + r.level);
        P.pid = null;
        TW.vouchers = (r.vouchers || []).filter((x) => x.floor === TW.n);
        if (TW.vouchers.length) drawPick();
        else endPick();
    }

    function mvp() {
        const u = R.units.find((x) => x.id === R.mvp);
        if (!u) return '';
        const side = watcher ? (players(u.side).username || '') : u.side === mine ? T('la tua squadra', 'your team') : T('avversario', 'opponent');
        const list = [[T('Danni fatti', 'Damage dealt'), u.dealt], [T('KO', 'KOs'), u.kos], [T('Colpo più forte', 'Biggest hit'), u.best]];
        if (u.healed > 0) list.push([T('Cure', 'Healing'), u.healed]);
        return '<aside class="d2p-mvp"><span class="d2p-mvp-lab">' + ic('coppa') + ' MVP</span>'
            + D.card({ id: u.pid, name: u.name, img: u.img, rarity: u.rarity, role: u.role, cost: 0, level: 0 }, { lv: false, cost: false }).replace('<span class="d2p-dn">' + esc(D.niceName(u.name)) + '</span>', '')
            + '<div><div class="d2p-mvp-n">' + esc(D.niceName(u.name)) + '<small>' + esc(side) + '</small></div>'
            + '<ul>' + list.map((l) => '<li>' + esc(l[0]) + ' <b class="d2p-num">' + fmt(l[1]) + '</b></li>').join('') + '</ul></div></aside>';
    }

    function table() {
        const max = (k) => Math.max(1, ...R.units.map((u) => u[k]));
        const mx = { dealt: max('dealt'), taken: max('taken'), healed: max('healed') };
        const order = watcher ? [0, 1] : [mine, other];
        const row = (u) => '<tr class="' + (!watcher && u.side !== mine ? 'd2p-foe' : '') + '"><td><span class="d2p-who" style="--r:' + D.rarColor(u.rarity) + '"><span class="d2p-face">'
            + (u.img ? '<img src="' + esc(u.img) + '" alt="" loading="lazy" onerror="this.remove()">' : esc(D.niceName(u.name).charAt(0))) + '</span><span class="d2p-wn-full">' + esc(D.niceName(u.name)) + '</span>'
            + (u.ko ? ' <span class="d2p-ko">KO</span>' : '') + '</span></td>'
            + '<td><span class="d2p-v"><b class="d2p-num">' + fmt(u.dealt) + '</b><span style="--w:' + (u.dealt / mx.dealt).toFixed(3) + '"></span></span></td>'
            + '<td><span class="d2p-v d2p-f"><b class="d2p-num">' + fmt(u.taken) + '</b><span style="--w:' + (u.taken / mx.taken).toFixed(3) + '"></span></span></td>'
            + '<td><span class="d2p-v d2p-g"><b class="d2p-num">' + fmt(u.healed) + '</b><span style="--w:' + (u.healed / mx.healed).toFixed(3) + '"></span></span></td>'
            + '<td class="d2p-c4 d2p-num">' + fmt(u.kos) + '</td></tr>';
        const group = (side) => '<tr><td colspan="5" class="d2p-tbl-side">' + esc(!watcher && side === mine ? T('La tua squadra', 'Your team') : players(side).username || '?') + '</td></tr>'
            + R.units.filter((u) => u.side === side).map(row).join('');
        return '<section class="d2p-nums"><h3>' + esc(T('La partita in numeri', 'The match in numbers')) + '</h3><table class="d2p-tbl"><thead><tr><th>' + esc(T('Personaggio', 'Character')) + '</th><th>' + esc(T('Danni fatti', 'Damage'))
            + '</th><th>' + esc(T('Subiti', 'Taken')) + '</th><th>' + esc(T('Cure', 'Healing')) + '</th><th class="d2p-c4">KO</th></tr></thead><tbody>' + order.map(group).join('') + '</tbody></table></section>';
    }

    function render() {
        const [t, cls] = title();
        // Chi ha giocato, con le foto: prima tu (o il primo giocatore, per chi guarda), poi l'altro.
        const who = (p) => '<span class="d2p-res-p">' + D.pfp(p.id, p.username, '', 96) + '<b>' + esc(p.username || '?') + '</b></span>';
        const vs = '<div class="d2p-res-vs">' + who(players(watcher ? 0 : mine)) + '<span>' + esc(T('contro', 'vs')) + '</span>' + who(players(watcher ? 1 : other)) + '</div>';
        const BOT_LV = { facile: T('Facile', 'Easy'), normale: T('Normale', 'Normal'), difficile: T('Difficile', 'Hard'), incubo: T('Incubo', 'Nightmare') };
        const botLv = R.bot ? (players(R.side === null || R.side === undefined ? 1 : other).level || '') : '';
        // La Torre: il piano davanti (o lo stato, se il titolo e' gia' il piano).
        const lead = !TW ? [] : TW.cleared && !(TW.first && (TW.boss || TW.top))
            ? [TW.first ? T('Superato', 'Beaten') : T('Superato di nuovo, senza premio', 'Beaten again, no rewards')]
            : [T('Piano ', 'Floor ') + TW.n + (TW.title ? ' · ' + TW.title : '')];
        const sub = [...lead, R.bot ? T('Contro il bot ', 'Vs bot ') + (BOT_LV[botLv] || botLv) : R.async ? T('In differita', 'Turn-based') : D.MODE[R.mode] || R.mode, R.turns + (R.turns === 1 ? T(' turno', ' turn') : T(' turni', ' turns')), duration(R.seconds)].filter(Boolean).join(' · ');
        const again = R.daily ? (R.daily.today ? T('Riprova la sfida', 'Try the challenge again') : '') : T('Rivincita', 'Rematch');
        const acts = TW ? towerActs() : (R.rematch && again ? '<button class="d2p-btn d2p-btn-w d2p-btn-big" type="button" id="d2p-rematch">' + esc(again) + '</button>' : '')
            + '<a class="d2p-btn d2p-btn-big' + (R.rematch ? '' : ' d2p-btn-w') + '" href="' + esc(U.replay + '?m=' + R.id) + '">' + ic('replay') + ' ' + esc(T('Rivedi la partita', 'Watch the replay')) + '</a>'
            + '<a class="d2p-btn d2p-btn-big" href="' + esc(U.lobby) + '">' + esc(T('Torna ai duelli', 'Back to duels')) + '</a>';
        $('d2p-res').innerHTML = '<section>' + vs + '<h1 class="d2p-res-big ' + cls + '">' + esc(t) + '</h1><p class="d2p-res-sub">' + esc(sub) + '</p>'
            + (REASON[R.reason] && !(TW && TW.cleared) ? '<p class="d2p-res-why">' + esc(REASON[R.reason]) + '</p>' : '') + towerWhy()
            + rating() + facts() + (TW ? '<div class="d2p-tw-pick" id="d2p-tw-pick" hidden></div>' : '')
            + '<div class="d2p-res-acts" id="d2p-acts"' + (P.on ? ' hidden' : '') + '>' + acts + '</div><p class="d2p-offer" id="d2p-offer" aria-live="polite"></p></section>'
            + mvp() + table();
        if (TW) {
            drawPick();
            $('d2p-tw-pick').addEventListener('click', onPick);
            const nb = $('d2p-tw-next');
            if (nb) nb.addEventListener('click', nextFloor);
            const back = document.querySelector('.d2p-back');
            if (back) {
                back.href = U.tower;
                back.innerHTML = ic('indietro') + ' ' + esc(T('La Torre', 'The Tower'));
            }
        }
        D.hydrate($('d2p-main'));
        document.title = t + ' · ' + document.title;

        // La barra dei punti si riempie dopo un attimo.
        const track = $('d2p-track');
        if (track) {
            track.style.setProperty('--a', track.dataset.a);
            requestAnimationFrame(() => requestAnimationFrame(() => track.style.setProperty('--b', track.dataset.b)));
        }
        const rb = $('d2p-rematch');
        if (rb) rb.addEventListener('click', rematch);
    }

    async function rematch() {
        const b = $('d2p-rematch');
        b.disabled = true;
        const r = await api('rematch', null, { m: R.id });
        if (!r.ok) {
            b.disabled = false;
            if (r.code === 'IN_MATCH' && r.match) { location.href = U.arena + '?m=' + r.match; return; }
            D.toast(r.error || '', true);
            return;
        }
        location.href = U.arena + '?m=' + r.match;
    }

    // La richiesta di rivincita dell'avversario arriva nel timbro personale ('dr').
    async function watchOffers() {
        if (watcher || R.bot || R.mode === 'ranked') return;
        const read = (u) => fetch(CFG.rt + '?u=' + u, { credentials: 'same-origin', cache: 'no-store' }).then((x) => x.json()).catch(() => null);
        // Dal primo evento che il timbro ricorda: la richiesta puo' essere
        // arrivata prima che la pagina si aprisse (la stanza vale 10 minuti).
        let cursor = 0;
        const until = Date.now() + 10 * 60 * 1000;
        const loop = async () => {
            if (Date.now() > until) return;
            if (!document.hidden) {
                const r = await read(cursor);
                if (r && r.u) {
                    if (r.u.seq) cursor = r.u.seq;
                    (r.u.events || []).forEach((e) => {
                        if (e.t !== 'dr' || Number(e.from) !== R.id || (e.at && e.at < Date.now() / 1000 - 600)) return;
                        const box = $('d2p-offer');
                        box.innerHTML = esc(T('L\'avversario vuole la rivincita.', 'Your opponent wants a rematch.')) + ' <button class="d2p-btn d2p-btn-w d2p-btn-sm" type="button" id="d2p-accept">' + esc(T('Accetta', 'Accept')) + '</button>';
                        $('d2p-accept').addEventListener('click', async () => {
                            const team = R.units.filter((u) => u.side === mine).map((u) => u.pid);
                            const j = await api('room', null, { action: 'join', code: e.c, team });
                            if (!j.ok) { D.toast(j.error || '', true); return; }
                            location.href = U.arena + '?m=' + j.match;
                        });
                    });
                }
            }
            setTimeout(loop, 3000);
        };
        loop();
    }

    render();
    watchOffers();
})();
