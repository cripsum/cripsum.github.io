/**
 * Duelli 2.0 — la fine partita (tavola 5).
 *
 * Esito; in Ranked i punti con la barra che si riempie e la soglia del rango
 * («Promosso a…» quando la passi); l'MVP della partita; i numeri di tutte e
 * sei le carte. Rivincita (all'avversario arriva la richiesta), replay,
 * ritorno alla lobby. Se l'avversario chiede la rivincita lo dice qui.
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

    function title() {
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
        });
        if (R.season) {
            const placing = R.season.games < R.season.placement;
            rows.push(['calendario', T('Stagione ', 'Season ') + R.season.n + (placing ? T(' · piazzamento', ' · placement') : ''),
                placing ? R.season.games + ' / ' + R.season.placement : T('conta per la stagione', 'counts for the season')]);
        }
        if (!watcher && R.winner === mine && R.streak >= 2) rows.push(['fulmine', T('Serie di vittorie', 'Win streak'), fmt(R.streak)]);
        if (R.mode === 'bot') rows.push(['robot', T('Contro il bot', 'Vs bot'), T('non conta per la classifica', 'does not count for the ranking')]);
        if (R.mode === 'casual') rows.push(['dadi', T('Veloce', 'Quick'), T('non conta per la classifica', 'does not count for the ranking')]);
        if (R.mode === 'private') rows.push(R.async ? ['orologio', T('In differita', 'Turn-based'), T('non conta per la classifica', 'does not count for the ranking')]
            : ['lucchetto', T('Stanza o sfida', 'Room or challenge'), T('non conta per la classifica', 'does not count for the ranking')]);
        if (!rows.length) return '';
        return '<div class="d2p-rew">' + rows.map((r) => '<div>' + ic(r[0]) + '<span>' + esc(r[1]) + '</span><b>' + esc(r[2]) + '</b></div>').join('') + '</div>';
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
        const opp = watcher ? (players(0).username || '?') + T(' contro ', ' vs ') + (players(1).username || '?') : T('contro ', 'vs ') + (players(other).username || '?');
        const BOT_LV = { facile: T('Facile', 'Easy'), normale: T('Normale', 'Normal'), difficile: T('Difficile', 'Hard'), incubo: T('Incubo', 'Nightmare') };
        const botLv = R.bot ? (players(R.side === null || R.side === undefined ? 1 : other).level || '') : '';
        const sub = [R.bot ? T('Contro il bot ', 'Vs bot ') + (BOT_LV[botLv] || botLv) : R.async ? T('In differita', 'Turn-based') : D.MODE[R.mode] || R.mode, R.bot ? '' : opp, R.turns + (R.turns === 1 ? T(' turno', ' turn') : T(' turni', ' turns')), duration(R.seconds)].filter(Boolean).join(' · ');
        const acts = (R.rematch ? '<button class="d2p-btn d2p-btn-w d2p-btn-big" type="button" id="d2p-rematch">' + esc(T('Rivincita', 'Rematch')) + '</button>' : '')
            + '<a class="d2p-btn d2p-btn-big' + (R.rematch ? '' : ' d2p-btn-w') + '" href="' + esc(U.replay + '?m=' + R.id) + '">' + ic('replay') + ' ' + esc(T('Rivedi la partita', 'Watch the replay')) + '</a>'
            + '<a class="d2p-btn d2p-btn-big" href="' + esc(U.lobby) + '">' + esc(T('Torna ai duelli', 'Back to duels')) + '</a>';
        $('d2p-res').innerHTML = '<section><h1 class="d2p-res-big ' + cls + '">' + esc(t) + '</h1><p class="d2p-res-sub">' + esc(sub) + '</p>'
            + (REASON[R.reason] ? '<p class="d2p-res-why">' + esc(REASON[R.reason]) + '</p>' : '')
            + rating() + facts() + '<div class="d2p-res-acts">' + acts + '</div><p class="d2p-offer" id="d2p-offer" aria-live="polite"></p></section>'
            + mvp() + table();
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
