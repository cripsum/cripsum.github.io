/**
 * Duelli 2.0 — la pagina Squadra (tavola 2).
 *
 * Tre posti in campo; il contatore del costo colora i pezzi con la rarita'
 * di chi li occupa e dice cosa ci sta ancora; legami attivi e quasi attivi
 * (con chi li completerebbe); ruoli che mancano, senza obbligare. A destra
 * la scheda del personaggio coi numeri del livello massimo (come in
 * Ranked); su telefono sale dal basso.
 *
 * Le tre squadre si modificano in bozza: si salva una alla volta
 * (api/duel/team.php). Uscendo con modifiche non salvate il browser chiede.
 */
(function () {
    'use strict';
    const D = window.D2P;
    const { T, $, esc, fmt, ic, api, CFG } = D;
    const H = D.DATA;
    const budget = CFG.budget;
    const byId = {};
    H.roster.forEach((c) => { byId[c.id] = c; });
    const phoneMq = window.matchMedia ? window.matchMedia('(max-width: 700px)') : { matches: false };
    const ATTACK = ['DPS', 'Burst DPS', 'Sub DPS', 'Bruiser'];
    const GUARD = ['Tank', 'Support'];
    const HEAL = ['Healer'];

    // Bozze delle tre squadre.
    const drafts = {};
    for (let s = 1; s <= H.slots; s++) {
        const t = H.teams.find((x) => x.slot === s);
        const ids = t ? t.team.filter((id) => byId[id]) : [];
        drafts[s] = { name: t ? t.name : T('Squadra ', 'Team ') + s, ids, savedIds: t ? t.team.slice() : null, savedName: t ? t.name : null };
    }
    const S = { slot: H.slot, sel: null, q: '', role: '', rar: '', fit: false, saving: false };
    const draft = () => drafts[S.slot];
    const cards = () => draft().ids.map((id) => byId[id]).filter(Boolean);
    const cost = () => cards().reduce((a, c) => a + c.cost, 0);
    const dirty = (s) => {
        const d = drafts[s];
        if (!d.savedIds) return d.ids.length > 0;
        return d.ids.join(',') !== d.savedIds.join(',') || d.name !== d.savedName;
    };
    function say(text) {
        const el = $('d2p-say');
        el.textContent = '';
        setTimeout(() => { el.textContent = text; }, 30);
    }

    // ── Squadre salvate ──────────────────────────────────────────────────
    function renderSlots() {
        $('d2p-slots').innerHTML = Object.keys(drafts).map((s) => {
            s = Number(s);
            const d = drafts[s];
            return '<button type="button" role="tab" data-slot="' + s + '" aria-selected="' + (s === S.slot) + '"' + (s === S.slot ? ' class="d2p-on"' : '') + '>'
                + esc(d.savedIds ? d.savedName : T('Squadra ', 'Team ') + s) + (dirty(s) ? ' •' : '') + '</button>';
        }).join('');
    }

    // ── In campo ─────────────────────────────────────────────────────────
    function renderField() {
        const cs = cards();
        let html = cs.map((c) => D.card(c, { lv: false, cls: c.id === S.sel ? 'd2p-sel' : '', attrs: 'data-pick="' + c.id + '"' })
            .replace('<span class="d2p-art">', '<span class="d2p-art"><button type="button" class="d2p-x" data-remove="' + c.id + '" aria-label="' + esc(T('Togli ', 'Remove ') + D.niceName(c.name)) + '">' + ic('x') + '</button>')).join('');
        for (let i = cs.length; i < 3; i++) html += '<span class="d2p-slot-empty" aria-hidden="true">' + ic('piu') + '</span>';
        $('d2p-field').innerHTML = html;
    }

    function fitsText(left) {
        const names = Object.keys(CFG.rarities).filter((k) => k !== 'segreto_limitato' && CFG.rarities[k].cost <= left).map((k) => CFG.rarities[k].n);
        if (!names.length) return '';
        const art = (n) => (D.EN ? (/^[aeiou]/i.test(n) ? 'an ' : 'a ') : (/^(s[^aeiou]|z|gn|ps)/i.test(n) ? 'uno ' : 'un ')) + n;
        const list = names.map(art);
        return list.length === 1 ? list[0] : list.slice(0, -1).join(', ') + T(' o ', ' or ') + list[list.length - 1];
    }
    function renderMeter() {
        const c = cost();
        const left = budget - c;
        const n = cards().length;
        let note;
        if (c > budget) note = T('Costa ' + (c - budget) + ' più del massimo della Ranked: va bene per Veloce, stanze e bot.', 'Costs ' + (c - budget) + ' over the Ranked limit: fine for Quick, rooms and the bot.');
        else if (n < 3) note = left > 0 ? T('Ti restano ' + left + ': ', left + ' left: ') + fitsText(left) + '.' : T('Hai speso tutto: per la Ranked togli qualcuno.', 'Budget spent: remove someone for Ranked.');
        else note = left > 0 ? T('Avanzano ' + left + ' punti di costo.', left + ' cost points to spare.') : T('Costo pieno: giusto per la Ranked.', 'Full cost: right on the Ranked limit.');
        $('d2p-meter').innerHTML = '<div class="d2p-meter-top"><span>' + esc(T('Costo per la Ranked', 'Ranked cost')) + '</span><b class="d2p-num' + (c > budget ? ' d2p-bad' : '') + '">' + c + ' / ' + budget + '</b></div>'
            + D.pips(cards(), budget) + '<p>' + esc(note) + '</p>';
    }

    // ── Legami e ruoli ───────────────────────────────────────────────────
    function renderSyn() {
        const cs = cards();
        const count = {};
        cs.forEach((c) => (c.traits || []).forEach((t) => { count[t] = (count[t] || 0) + 1; }));
        const rows = Object.keys(count).filter((k) => H.traits[k]).map((k) => {
            const tr = H.traits[k];
            const needs = Object.keys(tr.tiers).map(Number).sort((a, b) => a - b);
            const n = count[k];
            const active = needs.filter((x) => n >= x).pop() || 0;
            const max = needs[needs.length - 1];
            let text;
            if (active) {
                text = T('Con ', 'With ') + active + ': ' + tr.tiers[active];
                const next = needs.find((x) => x > n);
                if (next) text += T(' · con ', ' · with ') + next + ': ' + tr.tiers[next];
            } else {
                const need = needs[0];
                const who = H.roster.filter((c) => (c.traits || []).includes(k) && !draft().ids.includes(c.id)).slice(0, 2).map((c) => D.niceName(c.name));
                text = (who.length ? T('Con ', 'With ') + who.join(T(' o ', ' or ')) + ': ' : T('Con ', 'With ') + need + ': ') + tr.tiers[need];
            }
            return { active, html: '<div class="d2p-syn-r' + (active ? '' : ' d2p-off') + '"><span class="d2p-n">' + n + '/' + max + '</span><div><b>' + esc(tr.n) + '</b><small>' + esc(text) + '</small></div></div>' };
        }).sort((a, b) => b.active - a.active);
        $('d2p-syn').innerHTML = '<span class="d2p-lab">' + esc(T('Legami', 'Bonds')) + '</span>'
            + (rows.length ? rows.map((r) => r.html).join('') : '<p class="d2p-mut">' + esc(T('Nessun legame in squadra: due personaggi dello stesso gruppo danno un piccolo bonus.', 'No bonds: two characters from the same group give a small bonus.')) + '</p>');

        const roles = cs.map((c) => c.role);
        const has = (list) => roles.some((r) => list.includes(r));
        const chips = [];
        if (has(ATTACK)) chips.push('<span>' + ic('attacco') + ' ' + esc(T('Attacco', 'Attack')) + '</span>');
        if (has(GUARD)) chips.push('<span>' + ic('guardia') + ' ' + esc(T('Difesa', 'Defense')) + '</span>');
        if (has(HEAL)) chips.push('<span>' + ic('healer') + ' ' + esc(T('Cura', 'Healing')) + '</span>');
        if (cs.length) {
            if (!has(ATTACK)) chips.push('<span class="d2p-miss">' + ic('attacco') + ' ' + esc(T('Manca chi attacca', 'No attacker')) + '</span>');
            if (!has(GUARD) && !has(HEAL)) chips.push('<span class="d2p-miss">' + ic('guardia') + ' ' + esc(T('Manca chi difende o cura', 'No defender or healer')) + '</span>');
        }
        $('d2p-roles').innerHTML = '<span class="d2p-lab">' + esc(T('Ruoli', 'Roles')) + '</span><div class="d2p-roles">' + (chips.join('') || '<span class="d2p-miss">' + esc(T('Squadra vuota', 'Empty team')) + '</span>') + '</div>';
    }

    function renderSave() {
        const d = draft();
        const n = d.ids.length;
        const name = $('d2p-name');
        if (document.activeElement !== name) name.value = d.name;
        $('d2p-save').disabled = S.saving || n !== 3 || !dirty(S.slot);
        $('d2p-try').disabled = n !== 3;
        let note = '';
        if (n < 3) note = T('Scegli ancora ' + (3 - n) + (3 - n === 1 ? ' personaggio.' : ' personaggi.'), 'Pick ' + (3 - n) + ' more.');
        else if (dirty(S.slot)) note = T('Modifiche non salvate.', 'Unsaved changes.');
        else note = T('Salvata: la trovi pronta nella lobby.', 'Saved: it is ready in the lobby.');
        $('d2p-note').textContent = note;
    }

    // ── Collezione ───────────────────────────────────────────────────────
    function renderFilters() {
        const roles = CFG.roles.filter((r) => H.roster.some((c) => c.role === r));
        $('d2p-f-role').innerHTML = '<button type="button" data-role=""' + (S.role === '' ? ' class="d2p-on"' : '') + ' aria-pressed="' + (S.role === '') + '">' + esc(T('Tutti i ruoli', 'All roles')) + '</button>'
            + roles.map((r) => '<button type="button" data-role="' + esc(r) + '"' + (S.role === r ? ' class="d2p-on"' : '') + ' aria-pressed="' + (S.role === r) + '">' + ic(D.ROLE_ICON[r]) + ' ' + esc(D.roleName(r)) + '</button>').join('');
        const rars = Object.keys(CFG.rarities).filter((k) => H.roster.some((c) => c.rarity === k));
        $('d2p-f-rar').innerHTML = '<button type="button" data-rar=""' + (S.rar === '' ? ' class="d2p-on"' : '') + ' aria-pressed="' + (S.rar === '') + '">' + esc(T('Tutte le rarità', 'All rarities')) + '</button>'
            + rars.map((k) => '<button type="button" data-rar="' + k + '"' + (S.rar === k ? ' class="d2p-on"' : '') + ' aria-pressed="' + (S.rar === k) + '" style="--r:' + D.rarColor(k) + '"><i class="d2p-sw"></i>' + esc(D.rarName(k)) + '</button>').join('');
    }
    function renderColl() {
        const left = budget - cost();
        const full = draft().ids.length >= 3;
        const q = S.q.trim().toLowerCase();
        const list = H.roster.filter((c) => (!S.role || c.role === S.role) && (!S.rar || c.rarity === S.rar)
            && (!q || c.name.toLowerCase().includes(q)) && (!S.fit || draft().ids.includes(c.id) || c.cost <= left));
        $('d2p-coll').innerHTML = list.length ? list.map((c) => {
            const picked = draft().ids.includes(c.id);
            const cls = (picked ? 'd2p-picked ' : '') + (c.id === S.sel ? 'd2p-sel ' : '') + (!picked && !full && c.cost > left ? 'd2p-over' : '');
            return D.card(c, { tag: 'button', cls, sub: D.roleName(c.role) + ' · ' + D.rarName(c.rarity),
                attrs: 'type="button" data-pick="' + c.id + '" aria-pressed="' + picked + '" aria-label="' + esc(D.niceName(c.name) + ', ' + D.roleName(c.role) + ', ' + D.rarName(c.rarity) + ', ' + T('costo ', 'cost ') + c.cost + (picked ? T(', in squadra', ', in team') : '')) + '"' });
        }).join('') : '<p class="d2p-coll-empty">' + esc(H.roster.length ? T('Nessun personaggio con questi filtri.', 'No characters match these filters.') : T('La collezione è vuota: i personaggi si trovano nella lootbox.', 'Your collection is empty: find characters in the lootbox.')) + '</p>';
    }

    // ── Scheda ───────────────────────────────────────────────────────────
    const STAT_MAX = {};
    ['hp', 'atk', 'def', 'spd', 'crit'].forEach((k) => { STAT_MAX[k] = Math.max(1, ...H.roster.map((c) => c.stats_max[k] || 0)); });
    const STAT_LABEL = { hp: 'HP', atk: 'ATK', def: 'DEF', spd: T('VEL', 'SPD'), crit: T('Critico', 'Crit') };
    const hi = (text) => esc(text).replace(/([+−-]?\d+(?:[.,]\d+)?%?(?: ATK| DEF)?)/g, '<em>$1</em>');
    const PLAIN_ATTACK = /^(Infligge|Deals) \d+% ATK (al bersaglio|to the target)\.$/;

    function renderDet() {
        const box = $('d2p-det');
        const c = S.sel ? byId[S.sel] : null;
        if (!c) {
            box.innerHTML = '<p class="d2p-det-empty">' + esc(T('Scegli un personaggio per vedere statistiche e kit.', 'Pick a character to see stats and kit.')) + '</p>';
            box.classList.remove('d2p-up');
            removeScrim();
            return;
        }
        const name = D.niceName(c.name);
        const picked = draft().ids.includes(c.id);
        const full = draft().ids.length >= 3;
        const lvMax = c.level >= CFG.level_max;
        const diff = lvMax ? 0 : Math.round((1 - c.stats.hp / c.stats_max.hp) * 100);
        const k = c.kit;
        const sk = (label, part, u) => part ? '<div class="d2p-sk"><div class="d2p-sk-t"><span class="d2p-sk-k' + (u ? ' d2p-u' : '') + '">' + esc(label) + '</span><strong>' + esc(part.name) + '</strong>'
            + (part.meta ? '<span class="d2p-sk-c">' + esc(part.meta) + '</span>' : '') + '</div><p>' + hi(part.text) + '</p></div>' : '';
        box.style.setProperty('--r', D.rarColor(c.rarity));
        box.innerHTML = '<span class="d2p-handle" aria-hidden="true"></span><div class="d2p-det-main"><div class="d2p-det-top"><div class="d2p-big">' + (c.img ? '<img src="' + esc(c.img) + '" alt="" onerror="this.remove()">' : '') + '<span class="d2p-ini">' + esc(name.charAt(0)) + '</span></div>'
            + '<div><h3>' + esc(name) + '</h3><div class="d2p-meta"><span class="d2p-pill d2p-rp">' + esc(D.rarName(c.rarity)) + ' · ' + esc(T('costo ', 'cost ')) + c.cost + '</span>'
            + '<span class="d2p-pill">' + ic(D.ROLE_ICON[c.role]) + ' ' + esc(D.roleName(c.role)) + '</span><span class="d2p-pill">' + (lvMax ? 'MAX' : 'Lv ' + c.level) + '</span></div></div></div>'
            + '<div class="d2p-stats">' + Object.keys(STAT_LABEL).map((s) => '<div class="d2p-st"><span>' + esc(STAT_LABEL[s]) + '</span><span class="d2p-b"><i style="width:' + Math.round((c.stats_max[s] / STAT_MAX[s]) * 100) + '%"></i></span><b class="d2p-num">' + fmt(c.stats_max[s]) + (s === 'crit' ? '%' : '') + '</b></div>').join('') + '</div>'
            + '<p class="d2p-lvl">' + esc(lvMax ? T('Valori al livello massimo, come in Ranked e in Veloce.', 'Max level values, as in Ranked and Quick.')
                : T('Valori al livello massimo, come in Ranked. In Veloce usa il tuo Lv ' + c.level + ': −' + diff + '%.', 'Max level values, as in Ranked. Quick uses your Lv ' + c.level + ': −' + diff + '%.')) + '</p>'
            + (k.traits && k.traits.length ? '<p class="d2p-lvl">' + esc(T('Legami: ', 'Bonds: ') + k.traits.join(', ')) + '</p>' : '')
            + '<div class="d2p-det-acts"><button class="d2p-btn ' + (picked ? '' : 'd2p-btn-w') + ' d2p-btn-sm" type="button" data-toggle="' + c.id + '"' + (!picked && full ? ' disabled' : '') + '>'
            + esc(picked ? T('Togli dalla squadra', 'Remove from team') : full ? T('Squadra piena', 'Team full') : T('Aggiungi alla squadra', 'Add to team')) + '</button>'
            + '<button class="d2p-btn d2p-btn-sm d2p-close-m" type="button" data-close="1">' + esc(T('Chiudi', 'Close')) + '</button></div></div>'
            + sk(T('Passiva', 'Passive'), k.passive)
            + (k.attack && !PLAIN_ATTACK.test(k.attack.text) ? sk(T('Attacco', 'Attack'), k.attack) : '')
            + sk(T('Abilità', 'Skill'), k.skill)
            + sk('Ultimate', k.ultimate, true);
        D.hydrate(box);
        const closeBtn = box.querySelector('.d2p-close-m');
        if (closeBtn && !phoneMq.matches) closeBtn.remove();
    }
    function openSheet() {
        if (!phoneMq.matches) return;
        const box = $('d2p-det');
        box.classList.add('d2p-up');
        if (!document.querySelector('.d2p-scrim')) {
            const s = document.createElement('div');
            s.className = 'd2p-scrim';
            s.addEventListener('click', closeSheet);
            document.body.appendChild(s);
        }
        const first = box.querySelector('[data-toggle]');
        if (first) first.focus();
    }
    function removeScrim() {
        const s = document.querySelector('.d2p-scrim');
        if (s) s.remove();
    }
    function closeSheet() {
        $('d2p-det').classList.remove('d2p-up');
        removeScrim();
        const back = S.sel && document.querySelector('.d2p-coll [data-pick="' + S.sel + '"]');
        if (back) back.focus();
    }

    // ── Azioni ───────────────────────────────────────────────────────────
    function toggle(id) {
        const d = draft();
        const c = byId[id];
        if (!c) return;
        const at = d.ids.indexOf(id);
        if (at >= 0) {
            d.ids.splice(at, 1);
            say(D.niceName(c.name) + T(' tolto dalla squadra.', ' removed from the team.'));
        } else if (d.ids.length < 3) {
            d.ids.push(id);
            say(D.niceName(c.name) + T(' in squadra.', ' added to the team.'));
        } else {
            D.toast(T('La squadra è piena: togli qualcuno prima.', 'The team is full: remove someone first.'), true);
            return;
        }
        render();
    }
    function select(id, open) {
        S.sel = id;
        renderField();
        renderColl();
        renderDet();
        if (open) openSheet();
    }
    async function save() {
        const d = draft();
        if (d.ids.length !== 3 || S.saving) return;
        S.saving = true;
        renderSave();
        const r = await api('team', null, { action: 'save', slot: S.slot, name: d.name, team: d.ids });
        S.saving = false;
        if (!r.ok) { D.toast(r.error || T('Non sono riuscito a salvare.', 'Could not save.'), true); renderSave(); return; }
        const t = (r.teams || []).find((x) => x.slot === S.slot);
        if (t) { d.savedIds = t.team.slice(); d.savedName = t.name; d.name = t.name; d.ids = t.team.slice(); }
        D.toast(T('Squadra salvata.', 'Team saved.'));
        render();
    }
    async function tryBot() {
        const d = draft();
        if (d.ids.length !== 3) return;
        const r = await api('bot', null, { team: d.ids, level: 'normale' });
        if (!r.ok) {
            if (r.code === 'IN_MATCH' && r.match) { location.href = CFG.urls.arena + '?m=' + r.match; return; }
            D.toast(r.error || '', true);
            return;
        }
        window.removeEventListener('beforeunload', guard);
        location.href = CFG.urls.arena + '?m=' + r.match;
    }

    function render() {
        renderSlots();
        renderField();
        renderMeter();
        renderSyn();
        renderSave();
        renderColl();
        renderDet();
        D.hydrate($('d2p-main'));
    }

    const main = $('d2p-main');
    main.addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b || b.disabled) return;
        if (b.dataset.remove) { e.stopPropagation(); toggle(Number(b.dataset.remove)); return; }
        if (b.dataset.slot) { S.slot = Number(b.dataset.slot); S.sel = null; render(); history.replaceState(null, '', '?slot=' + S.slot); return; }
        if (b.dataset.role !== undefined) { S.role = b.dataset.role; renderFilters(); renderColl(); D.hydrate($('d2p-f-role')); return; }
        if (b.dataset.rar !== undefined) { S.rar = b.dataset.rar; renderFilters(); renderColl(); D.hydrate($('d2p-f-role')); return; }
        if (b.dataset.toggle) { toggle(Number(b.dataset.toggle)); return; }
        if (b.dataset.close) { closeSheet(); return; }
        if (b.dataset.pick) { select(Number(b.dataset.pick), true); return; }
        if (b.id === 'd2p-fit') { S.fit = !S.fit; b.setAttribute('aria-pressed', String(S.fit)); renderColl(); return; }
        if (b.id === 'd2p-save') { save(); return; }
        if (b.id === 'd2p-try') { tryBot(); }
    });
    main.addEventListener('dblclick', (e) => {
        const b = e.target.closest('.d2p-coll [data-pick]');
        if (b && !phoneMq.matches) toggle(Number(b.dataset.pick));
    });
    $('d2p-field').addEventListener('click', (e) => {
        const c = e.target.closest('[data-pick]');
        if (c && !e.target.closest('[data-remove]')) select(Number(c.dataset.pick), true);
    });
    $('d2p-q').addEventListener('input', (e) => { S.q = e.target.value; renderColl(); });
    $('d2p-name').addEventListener('input', (e) => { draft().name = e.target.value.slice(0, 40); renderSlots(); renderSave(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && $('d2p-det').classList.contains('d2p-up')) closeSheet();
    });
    const guard = (e) => {
        if (Object.keys(drafts).some((s) => dirty(Number(s)) && drafts[s].ids.length === 3)) {
            e.preventDefault();
            e.returnValue = '';
        }
    };
    window.addEventListener('beforeunload', guard);

    // All'inizio: la squadra chiesta, il primo in campo nella scheda.
    if (!drafts[S.slot]) S.slot = 1;
    S.sel = draft().ids[0] || null;
    renderFilters();
    render();
})();
