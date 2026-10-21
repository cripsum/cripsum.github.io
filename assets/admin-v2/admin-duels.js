/*
 * Pannello admin: Duelli (gruppo «Duelli», fase 7 dei Duelli 2.0, tavola 7).
 *
 * Cinque sezioni:
 * - Kit personaggi: l'elenco (unico, ruolo, pannello), l'editor a frasi
 *   (ogni effetto e' una riga con i suoi numeri), il testo che legge il
 *   giocatore nelle due lingue, la prova con 2.000 partite tra bot con il
 *   prima e dopo, le versioni, i legami, colore e musica dell'ultimate;
 * - Stati e regole: i valori base degli stati del catalogo e le costanti, con la prova;
 * - Bilanciamento: partite vere degli ultimi giorni e prove tra bot;
 * - Stagioni: accese o spente, nome, fine, premi, archivio;
 * - Partite: in corso e finite, annulla, guarda, replay, abbandoni sospetti,
 *   e gli interruttori «aperti a tutti» e «avvisi su Discord».
 *
 * Tutto passa da /api/admin/duels.php.
 */
(() => {
    'use strict';

    const A = window.CripsumAdmin;
    if (!A) return;

    const { api, confirmBox, showToast, emptyState } = A;
    const e = A.escapeHtml;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const get = (params) => api(`duels.php?${new URLSearchParams(params)}`);
    const post = (action, body = {}) => api('duels.php', { method: 'POST', body: { action, ...body } });
    const clone = (v) => JSON.parse(JSON.stringify(v ?? null));
    const num = (v, d = 0) => Number(v).toLocaleString('it-IT', { minimumFractionDigits: d, maximumFractionDigits: d });
    const pct = (v) => (v === null || v === undefined ? '—' : `${num(v, 1)}%`);
    const isObj = (v) => v && typeof v === 'object' && !Array.isArray(v);
    const debounce = (fn, wait) => {
        let t = null;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
    };
    const dateTime = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleString('it-IT', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—');
    const day = (v) => (v ? new Date(typeof v === 'number' ? v * 1000 : String(v).replace(' ', 'T')).toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric' }) : '—');

    // ── Parole del gioco ─────────────────────────────────────────────────
    const TO = {
        target: 'il bersaglio', self: 'sé', allies: 'tutta la squadra', allies_other: 'gli alleati', enemies: 'tutti i nemici',
        other_enemies: 'gli altri nemici', random_enemy: 'un nemico a caso', lowest_ally: 'l\'alleato con meno vita',
        debuffed_ally: 'l\'alleato con più stati negativi', attacker: 'chi lo ha colpito', trigger: 'quell\'alleato',
    };
    const MOVE_TARGET = { enemy: 'un nemico', ally: 'un alleato', ally_other: 'un altro alleato', self: 'sé stesso' };
    const WHEN = {
        battle_start: 'A inizio partita', turn_start: 'A inizio turno', turn_end: 'A fine turno', after_action: 'Dopo ogni sua mossa',
        on_crit: 'Quando fa un critico', on_kill: 'Quando mette KO un nemico', on_damaged: 'Quando viene colpito', on_ally_ko: 'Quando cade un alleato',
        self_low: 'La prima volta che scende sotto…', ally_low: 'Quando un alleato scende sotto…',
    };
    const MODS = {
        atk_pct: 'Attacco %', def_pct: 'Difesa %', spd_pct: 'Velocità %', crit: 'Critico', cdmg: 'Danno critico %', prec: 'Precisione', ten: 'Tenacia',
        dmg_pct: 'Danni %', pen: 'Difesa ignorata %', dodge: 'Schivata %', reflect: 'Riflesso %', lifesteal: 'Rubavita %', red_pct: 'Danni subiti in meno %',
        red_skill_pct: 'Danni da abilità in meno %', heal_in_pct: 'Cure ricevute %', heal_out_pct: 'Cure fatte %',
    };
    const STATS = { hp: 'HP %', atk: 'ATK %', def: 'DEF %', spd: 'Velocità %', crit: 'Critico', cdmg: 'Danno critico', prec: 'Precisione', ten: 'Tenacia' };
    const STAT_LABEL = { hp: 'HP', atk: 'ATK', def: 'DEF', spd: 'Velocità', crit: 'Critico %', cdmg: 'Danno critico %', prec: 'Precisione', ten: 'Tenacia' };
    const COND = {
        '': 'sempre', chance: 'con probabilità', crit: 'se è critico', nocrit: 'se non è critico', killed: 'se mette KO',
        target_has: 'se il bersaglio ha', target_hp_below: 'se il bersaglio è sotto', self_hp_below: 'se è sotto', self_hp_above: 'se è sopra',
    };
    const SOURCE = { pannello: ['pannello', 'Scritto dal pannello'], unico: ['unico', 'Kit unico del codice'], ruolo: ['ruolo', 'Dal ruolo e dalla rarità'] };

    /*
     * Gli effetti come frasi: ogni parte e' testo o un campo.
     * ['num', chiave, {pre, unit, min, max}] · ['to'] · ['status'] · ['terrain'] · ['form']
     * ['select', chiave, {valore: parola}] · ['bool', chiave, parola] · ['statuses'] (piu' stati).
     */
    const FX = {
        damage: { label: 'Danno', make: { to: 'target', pct: 100 }, parts: [['num', 'pct', { unit: '% di ATK', max: 2000 }], 'a', ['to'],
            ['num', 'hits', { unit: 'colpi', min: 1, max: 10 }], ['num', 'pen', { pre: 'ignora', unit: '% DEF', max: 100 }],
            ['bool', 'true', 'danno vero'], ['bool', 'pierce_shield', 'passa sotto gli scudi'], ['num', 'cdmg', { pre: '+', unit: '% danno critico', max: 300 }]] },
        heal: { label: 'Cura', make: { to: 'self', pct: 10 }, parts: [['to'], ['num', 'pct', { pre: 'del', unit: '%', max: 300 }],
            ['select', 'of', { src_max: 'degli HP massimi di chi la usa', target_max: 'degli HP massimi di chi la riceve', damage: 'del danno fatto' }]] },
        shield: { label: 'Scudo', make: { to: 'self', pct: 15 }, parts: ['a', ['to'], ['num', 'pct', { pre: 'pari al', unit: '%', max: 300 }],
            ['select', 'of', { src_max: 'della vita massima di chi lo dà', target_max: 'della vita massima di chi lo riceve' }]] },
        apply: { label: 'Stato', make: { k: 'atk_giu', to: 'target', t: 2 }, parts: [['status'], 'a', ['to'], ['num', 'v', { pre: 'valore', min: -1000, max: 1000 }],
            ['num', 't', { pre: 'per', unit: 'turni', max: 99 }], ['num', 'st', { unit: 'cariche', min: 1, max: 10 }], ['num', 'chance', { unit: '% di probabilità', max: 100 }]] },
        remove: { label: 'Togli stato', make: { k: 'veleno', to: 'self' }, parts: [['status'], 'a', ['to']] },
        guardian: { label: 'Guardiano', make: { to: 'allies_other', v: 40, t: 2 }, parts: ['per', ['to'], ['num', 'v', { pre: 'prende il', unit: '% dei danni', max: 100 }],
            ['num', 't', { pre: 'per', unit: 'turni', max: 99 }]] },
        cleanse: { label: 'Purifica', make: { to: 'self' }, parts: [['to'], ['num', 'n', { pre: 'togli', unit: 'stati negativi (vuoto: tutti)', min: 1, max: 99 }]] },
        dispel: { label: 'Dissolvi', make: { to: 'target' }, parts: ['togli tutti i potenziamenti a', ['to']] },
        steal_buff: { label: 'Ruba potenziamento', make: { to: 'target' }, parts: ['il più forte di', ['to']] },
        spread: { label: 'Contagio', make: { to: 'target' }, parts: ['copia gli stati negativi del bersaglio sugli altri nemici'] },
        consume: { label: 'Detonazione', make: { to: 'target', k: ['veleno', 'bruciatura'] }, parts: ['su', ['to'], 'fa esplodere', ['statuses']] },
        ps: { label: 'Punti squadra', make: { n: 1, side: 'own' }, parts: [['num', 'n', { pre: '+', unit: 'PS', min: 1, max: 5 }],
            ['select', 'side', { own: 'alla squadra', steal: 'rubati alla squadra nemica', enemy: 'alla squadra nemica' }]] },
        charge: { label: 'Carica', make: { to: 'self', n: 20 }, parts: [['num', 'n', { unit: 'di carica (100 = piena)', min: -100, max: 100 }], 'a', ['to']] },
        advance: { label: 'Avanza', make: { to: 'self', pct: 20 }, parts: [['to'], ['num', 'pct', { pre: 'del', unit: '%', max: 100 }]] },
        delay: { label: 'Ritarda', make: { to: 'target', pct: 20 }, parts: [['to'], ['num', 'pct', { pre: 'del', unit: '%', max: 100 }], ['num', 'chance', { unit: '% di probabilità', max: 100 }]] },
        extra_turn: { label: 'Turno extra', make: { to: 'self' }, parts: ['a', ['to']] },
        terrain: { label: 'Terreno', make: { k: '' }, parts: [['terrain'], ['num', 'g', { pre: 'per', unit: 'giri', min: 1, max: 20 }]] },
        form: { label: 'Forma', make: { k: '', t: 2 }, parts: [['form'], ['num', 't', { pre: 'per', unit: 'turni', max: 99 }]] },
        stacks: { label: 'Cariche', make: { n: 1 }, parts: [['num', 'n', { pre: '+', unit: 'cariche (−99 le azzera)', min: -99, max: 10 }]] },
        revive: { label: 'Rianima', make: { pct: 25 }, parts: ['l\'ultimo alleato caduto con il', ['num', 'pct', { unit: '% della vita', max: 100 }]] },
    };
    const fxKeys = (type) => {
        const keys = new Set(['e', 'to', 'if']);
        (FX[type]?.parts || []).forEach((p) => {
            if (!Array.isArray(p)) return;
            if (['num', 'select', 'bool'].includes(p[0])) keys.add(p[1]);
            if (['status', 'terrain', 'form', 'statuses'].includes(p[0])) keys.add('k');
        });
        return keys;
    };

    // ════════════════════════════════════════════════════════════════════
    // Kit personaggi
    // ════════════════════════════════════════════════════════════════════
    const kitRoot = $('[data-duel-admin="kits"]');
    const K = {
        list: null, filter: 'all', q: '', id: 0, view: null, draft: null, tab: 'skill', dirty: false,
        preview: null, errors: [], sim: null, simRunning: false, catalog: null,
    };
    const TABS = [['passive', 'Passiva'], ['attack', 'Attacco'], ['skill', 'Abilità'], ['ultimate', 'Ultimate'], ['more', 'Statistiche']];

    const statusName = (k) => K.catalog?.statuses.find((s) => s.key === k)?.name || k;

    const listHtml = () => {
        const q = K.q.trim().toLowerCase();
        const rows = (K.list || []).filter((c) => (K.filter === 'all' || c.source === K.filter) && (!q || c.name.toLowerCase().includes(q) || c.role.toLowerCase().includes(q)));
        const counts = { all: K.list?.length || 0, unico: 0, ruolo: 0, pannello: 0 };
        (K.list || []).forEach((c) => { counts[c.source] += 1; });
        return `
            <div class="dz-list-head">
                <label class="dz-search"><i class="fa-solid fa-search"></i><input type="search" data-k-search placeholder="Cerca" value="${e(K.q)}" aria-label="Cerca un personaggio"></label>
                <div class="cmod-tabs dz-seg" role="tablist">${[['all', 'Tutti'], ['pannello', 'Pannello'], ['unico', 'Unici'], ['ruolo', 'Ruolo']].map(([k, l]) =>
                    `<button type="button" class="${K.filter === k ? 'is-active' : ''}" data-k-filter="${k}" title="${counts[k]} personaggi">${l}</button>`).join('')}</div>
            </div>
            <div class="dz-list" role="listbox" aria-label="Personaggi">
                ${rows.map((c) => `
                    <button type="button" class="dz-li${c.id === K.id ? ' is-active' : ''}" data-k-open="${c.id}" role="option" aria-selected="${c.id === K.id}">
                        ${c.img ? `<img src="${e(c.img)}" alt="" loading="lazy">` : `<span class="dz-li-ini">${e(c.name.charAt(0))}</span>`}
                        <span class="dz-li-text"><b>${e(c.name)}</b><small>${e(c.role)} · ${e(c.rarity.label)}</small></span>
                        <span class="dz-src dz-src--${c.source}" title="${e(SOURCE[c.source][1])}">${SOURCE[c.source][0]}</span>
                    </button>`).join('') || '<p class="admin-muted dz-pad">Nessun personaggio.</p>'}
            </div>`;
    };

    // ── L'editor: un campo per ogni pezzo della frase ────────────────────
    const field = (path, value, opts = {}) => {
        const min = opts.min ?? 0;
        const max = opts.max ?? 9999;
        return `${opts.pre ? `<span class="dz-word">${e(opts.pre)}</span>` : ''}<input type="number" class="dz-chip dz-num" data-path="${path}" value="${value ?? ''}" min="${min}" max="${max}" step="any" placeholder="—" aria-label="${e(opts.unit || opts.pre || 'valore')}">${opts.unit ? `<span class="dz-word">${e(opts.unit)}</span>` : ''}`;
    };
    const select = (path, value, options, cls = '') => `<select class="dz-chip ${cls}" data-path="${path}">${Object.entries(options).map(([k, l]) =>
        `<option value="${e(k)}"${String(value ?? '') === k ? ' selected' : ''}>${e(l)}</option>`).join('')}</select>`;
    const statusOptions = (value) => {
        const fams = {};
        (K.catalog?.statuses || []).forEach((s) => { (fams[s.fam_label] ||= []).push(s); });
        return Object.entries(fams).map(([fam, list]) => `<optgroup label="${e(fam)}">${list.map((s) =>
            `<option value="${e(s.key)}"${s.key === value ? ' selected' : ''}>${e(s.name)}</option>`).join('')}</optgroup>`).join('');
    };

    const condHtml = (path, cond) => {
        const keys = isObj(cond) ? Object.keys(cond) : [];
        if (keys.length > 1 || keys.includes('target_stacks')) {
            return `<span class="dz-word dz-if">condizione: ${e(JSON.stringify(cond))}</span>`;
        }
        const key = keys[0] || '';
        const kind = key === 'crit' && cond.crit === false ? 'nocrit' : key;
        const value = key ? cond[key] : '';
        let extra = '';
        if (kind === 'chance') extra = field(`${path}.if.chance`, value, { unit: '%', min: 1, max: 100 });
        if (['target_hp_below', 'self_hp_below', 'self_hp_above'].includes(kind)) extra = field(`${path}.if.${kind}`, value, { unit: '% della vita', min: 1, max: 99 });
        if (kind === 'target_has') {
            extra = `<select class="dz-chip" data-path="${path}.if.target_has"><option value="neg"${value === 'neg' ? ' selected' : ''}>uno stato negativo</option><option value="cc"${value === 'cc' ? ' selected' : ''}>un controllo</option>${statusOptions(value)}</select>`;
        }
        return `<span class="dz-if"><select class="dz-chip dz-chip--soft" data-cond="${path}" aria-label="Condizione">${Object.entries(COND).map(([k, l]) =>
            `<option value="${k}"${k === kind ? ' selected' : ''}>${e(l)}</option>`).join('')}</select>${extra}</span>`;
    };

    const effectHtml = (path, eff, i, total) => {
        const type = FX[eff.e] ? eff.e : null;
        const parts = (type ? FX[type].parts : []).map((p) => {
            if (typeof p === 'string') return `<span class="dz-word">${e(p)}</span>`;
            const [kind, key, opts] = p;
            if (kind === 'num') return field(`${path}.${key}`, eff[key], opts);
            if (kind === 'to') return select(`${path}.to`, eff.to || 'target', TO);
            if (kind === 'select') return select(`${path}.${key}`, eff[key] ?? Object.keys(key === 'of' ? opts : opts)[0], opts);
            if (kind === 'bool') return `<label class="dz-chip dz-bool"><input type="checkbox" data-path="${path}.${key}"${eff[key] ? ' checked' : ''}>${e(opts)}</label>`;
            if (kind === 'status') return `<select class="dz-chip" data-path="${path}.k">${statusOptions(eff.k)}</select>`;
            if (kind === 'terrain') return select(`${path}.k`, eff.k, Object.fromEntries([['', 'scegli…'], ...(K.catalog?.terrains || []).map((t) => [t.key, t.name])]));
            if (kind === 'form') {
                const forms = Object.keys(K.draft?.forms || {});
                return select(`${path}.k`, eff.k, Object.fromEntries([['', forms.length ? 'scegli…' : 'nessuna forma nel kit'], ...forms.map((f) => [f, K.draft.forms[f]?.it || f])]));
            }
            if (kind === 'statuses') {
                const chosen = Array.isArray(eff.k) ? eff.k : [eff.k].filter(Boolean);
                return `<select class="dz-chip" multiple size="3" data-path="${path}.k" data-multi aria-label="Stati che esplodono">${statusOptions(null).replace(/<option value="([^"]+)"/g,
                    (m, k) => `<option value="${k}"${chosen.includes(k) ? ' selected' : ''}`)}</select>`;
            }
            return '';
        }).join('');
        const extra = type ? Object.keys(eff).filter((k) => !fxKeys(type).has(k)) : [];
        return `
            <div class="dz-fx" data-fx="${path}">
                <span class="dz-fx-n">${i + 1}</span>
                <div class="dz-fx-body">
                    ${type ? select(`${path}.e`, eff.e, Object.fromEntries(Object.entries(FX).map(([k, v]) => [k, v.label])), 'dz-chip--strong') : `<span class="dz-chip dz-chip--strong">${e(eff.e || '?')}</span>`}
                    ${parts}
                    ${condHtml(path, eff.if)}
                    ${extra.length ? `<span class="dz-word dz-extra" title="${e(JSON.stringify(Object.fromEntries(extra.map((k) => [k, eff[k]]))))}">+ ${e(extra.join(', '))}</span>` : ''}
                </div>
                <div class="dz-fx-tools">
                    <button type="button" class="dz-tool" data-fx-move="-1" ${i === 0 ? 'disabled' : ''} title="Su" aria-label="Sposta su"><i class="fa-solid fa-arrow-up"></i></button>
                    <button type="button" class="dz-tool" data-fx-move="1" ${i === total - 1 ? 'disabled' : ''} title="Giù" aria-label="Sposta giù"><i class="fa-solid fa-arrow-down"></i></button>
                    <button type="button" class="dz-tool" data-fx-json title="Modifica come JSON" aria-label="Modifica come JSON"><i class="fa-solid fa-code"></i></button>
                    <button type="button" class="dz-tool" data-fx-del title="Togli" aria-label="Togli l'effetto"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>`;
    };

    const effectsHtml = (path, list) => `
        <div class="dz-fx-list" data-fx-list="${path}">
            ${(list || []).map((eff, i) => effectHtml(`${path}.${i}`, eff, i, list.length)).join('')}
            <button type="button" class="dz-add" data-fx-add="${path}"><i class="fa-solid fa-plus"></i> Aggiungi un effetto</button>
        </div>`;

    const modsHtml = (path, mods, labels, title) => {
        const rows = Object.entries(isObj(mods) ? mods : {});
        const free = Object.keys(labels).filter((k) => !rows.some(([r]) => r === k));
        return `
            <div class="dz-mods" data-mods="${path}">
                <span class="dz-label">${e(title)}</span>
                ${rows.map(([k, v]) => `<span class="dz-mod"><span class="dz-word">${e(labels[k] || k)}</span><input type="number" class="dz-chip dz-num" data-path="${path}.${k}" value="${v}" step="any" aria-label="${e(labels[k] || k)}"><button type="button" class="dz-tool" data-mod-del="${path}.${k}" aria-label="Togli"><i class="fa-solid fa-xmark"></i></button></span>`).join('')}
                ${free.length ? `<select class="dz-chip dz-chip--soft" data-mod-add="${path}" aria-label="Aggiungi"><option value="">+ aggiungi</option>${free.map((k) => `<option value="${k}">${e(labels[k])}</option>`).join('')}</select>` : ''}
            </div>`;
    };

    const moveHtml = (key) => {
        const mv = K.draft[key];
        if (!mv) {
            return `<p class="admin-muted">Questo kit non scrive questa mossa: la prende dal ruolo.</p>`;
        }
        return `
            <div class="dz-fields">
                <label class="dz-f dz-f--wide"><span>Nome</span><input class="admin-input" data-path="${key}.name" value="${e(mv.name || '')}" maxlength="60"></label>
                ${key === 'skill' ? `<label class="dz-f"><span>Costo</span><span class="dz-unit"><input class="admin-input" type="number" min="0" max="5" data-path="skill.cost" value="${mv.cost ?? 1}"><em>PS</em></span></label>
                    <label class="dz-f"><span>Ricarica</span><span class="dz-unit"><input class="admin-input" type="number" min="0" max="9" data-path="skill.cd" value="${mv.cd ?? ''}" placeholder="nessuna"><em>turni</em></span></label>` : ''}
                <label class="dz-f"><span>Si usa su</span>${select(`${key}.target`, mv.target || 'enemy', MOVE_TARGET, 'admin-input')}</label>
            </div>
            <p class="dz-label">Cosa fa, in ordine</p>
            ${effectsHtml(`${key}.effects`, mv.effects)}`;
    };

    const ADV_PASSIVE = ['scaling', 'vs', 'fatal', 'block_neg', 'counter', 'first_crit', 'cc_feed', 'overheal_shield'];
    const passiveHtml = () => {
        const p = K.draft.passive || {};
        const adv = Object.fromEntries(Object.entries(p).filter(([k]) => ADV_PASSIVE.includes(k) || !['name', 'mods', 'aura', 'triggers'].includes(k)));
        return `
            <div class="dz-fields"><label class="dz-f dz-f--wide"><span>Nome</span><input class="admin-input" data-path="passive.name" value="${e(p.name || '')}" maxlength="60"></label></div>
            ${modsHtml('passive.mods', p.mods, MODS, 'Sempre attivi')}
            ${modsHtml('passive.aura', p.aura, MODS, 'Aura: finché è in vita, a tutta la squadra')}
            <p class="dz-label">Quando scatta</p>
            <div class="dz-triggers">
                ${(p.triggers || []).map((tr, i) => `
                    <div class="dz-trigger">
                        <div class="dz-trigger-head">
                            ${select(`passive.triggers.${i}.when`, tr.when, WHEN, 'dz-chip--strong')}
                            ${['self_low', 'ally_low'].includes(tr.when) ? field(`passive.triggers.${i}.below`, tr.below ?? 30, { unit: '% della vita', min: 1, max: 99 }) : ''}
                            ${field(`passive.triggers.${i}.every`, tr.every, { pre: 'ogni', unit: 'turni', min: 1, max: 20 })}
                            <label class="dz-chip dz-bool"><input type="checkbox" data-path="passive.triggers.${i}.silent"${tr.silent ? ' checked' : ''}>senza annuncio</label>
                            <button type="button" class="dz-tool" data-trigger-del="${i}" title="Togli il momento" aria-label="Togli il momento"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        ${effectsHtml(`passive.triggers.${i}.effects`, tr.effects)}
                    </div>`).join('')}
                <button type="button" class="dz-add" data-trigger-add><i class="fa-solid fa-plus"></i> Aggiungi un momento</button>
            </div>
            <details class="dz-adv"${Object.keys(adv).length ? ' open' : ''}>
                <summary>Altre regole della passiva (avanzate)</summary>
                <p class="admin-muted">Crescita con la vita persa, danni in più contro certi bersagli, colpo fatale, contrattacco… Si scrivono in JSON: il testo a destra dice cosa fanno.</p>
                <textarea class="admin-input dz-json" data-json="passive" spellcheck="false" rows="6">${e(Object.keys(adv).length ? JSON.stringify(adv, null, 2) : '{}')}</textarea>
            </details>`;
    };

    const musicHtml = () => {
        const m = K.view.music;
        const theme = K.draft.theme || {};
        const chain = [
            theme.music ? ['Scelta qui', theme.music] : null,
            m.own ? ['Traccia sua', m.own] : null,
            m.pull ? ['Musica del pull', m.pull] : null,
            ['Default', m.default],
        ].filter(Boolean);
        return `
            <div class="dz-ult-look">
                <label class="dz-f"><span>Colore della scena</span><span class="dz-color"><input type="color" data-color value="${e(theme.color || K.view.character.rarity.color)}" aria-label="Colore"><input class="admin-input" data-path="theme.color" value="${e(theme.color || '')}" placeholder="${e(K.view.character.rarity.color)} (rarità)" maxlength="7"></span></label>
                <div class="dz-f dz-f--wide"><span>Musica</span>
                    <div class="dz-music">
                        <span class="admin-muted">Suona la prima che c'è:</span>
                        ${chain.map(([label, url], i) => `<span class="dz-music-row${i === 0 ? ' is-on' : ''}"><b>${e(label)}</b><audio controls preload="none" src="${e(url)}"></audio></span>`).join('')}
                        <span class="dz-music-actions">
                            <label class="admin-btn admin-btn--small"><i class="fa-solid fa-upload"></i> Carica una musica<input type="file" accept="audio/mpeg,audio/ogg,audio/mp4,audio/aac,audio/wav,.mp3,.ogg,.m4a,.aac,.wav" data-music-upload hidden></label>
                            ${theme.music ? '<button type="button" class="admin-btn admin-btn--small admin-btn--ghost" data-music-clear>Togli quella scelta qui</button>' : ''}
                        </span>
                    </div>
                </div>
            </div>`;
    };

    const moreHtml = () => {
        const v = K.view;
        const stats = K.preview?.stats || v.stats;
        const sk = K.draft.stacks;
        return `
            <div class="dz-stats">
                <table class="dz-mini"><thead><tr><th></th><th>Lv. 1</th><th>Lv. ${v.stats.level_max}</th></tr></thead><tbody>
                    ${Object.keys(STAT_LABEL).map((k) => `<tr><td>${STAT_LABEL[k]}</td><td>${num(stats.lv1[k] ?? 0)}</td><td>${num(stats.max[k] ?? 0)}</td></tr>`).join('')}
                </tbody></table>
                ${modsHtml('stats_mods', K.draft.stats_mods, STATS, 'Ritocco delle statistiche del ruolo')}
            </div>
            <p class="dz-label">Cariche del kit</p>
            ${sk ? `<div class="dz-fields">
                    <label class="dz-f"><span>Nome</span><input class="admin-input" data-path="stacks.name" value="${e(sk.name || '')}" maxlength="30"></label>
                    <label class="dz-f"><span>Massimo</span><input class="admin-input" type="number" min="1" max="20" data-path="stacks.max" value="${sk.max ?? 5}"></label>
                    <label class="dz-f dz-f--check"><input type="checkbox" data-path="stacks.reset_on_damaged"${sk.reset_on_damaged ? ' checked' : ''}><span>si azzerano quando viene colpito</span></label>
                </div>
                ${modsHtml('stacks.per', sk.per, MODS, 'Per ogni carica')}
                <button type="button" class="admin-btn admin-btn--small admin-btn--ghost" data-stacks-off>Togli le cariche</button>`
                : '<button type="button" class="dz-add" data-stacks-on><i class="fa-solid fa-plus"></i> Aggiungi delle cariche (come lo Stile di Dante)</button>'}
            <details class="dz-adv"${K.draft.forms ? ' open' : ''}>
                <summary>Forme (trasformazioni, avanzate)</summary>
                <textarea class="admin-input dz-json" data-json="forms" spellcheck="false" rows="5">${e(K.draft.forms ? JSON.stringify(K.draft.forms, null, 2) : '{}')}</textarea>
            </details>
            <p class="dz-label">Legami</p>
            <div class="dz-traits">
                ${v.traits.map((t) => `<label class="dz-trait${t.member ? ' is-on' : ''}" title="${e(Object.entries(t.bonus).map(([n, b]) => `${n}: ${b}`).join(' · '))}">
                    <input type="checkbox" data-trait="${e(t.key)}"${t.member ? ' checked' : ''}${t.by_category ? ' disabled' : ''}>
                    <b>${e(t.it)}</b>${t.by_category ? '<small>per categoria</small>' : ''}</label>`).join('')}
            </div>
            <p class="admin-muted dz-small">I legami si salvano subito, a parte dal kit. Quelli «per categoria» dipendono dalla categoria del personaggio (Personaggi).</p>`;
    };

    const textHtml = () => {
        const t = (K.preview || K.view).text;
        const key = K.tab === 'more' ? null : K.tab;
        const block = (lang) => {
            if (!key) {
                return ['passive', 'attack', 'skill', 'ultimate'].filter((k) => t[lang][k]).map((k) => `<p><b>${e(t[lang][k].name)}</b> ${e(t[lang][k].text)}</p>`).join('');
            }
            const m = t[lang][key];
            return m ? `<p><b>${e(m.name)}</b>${m.meta ? ` <small>${e(m.meta)}</small>` : ''}<br>${e(m.text) || '<span class="admin-muted">nessun effetto</span>'}</p>` : '<p class="admin-muted">—</p>';
        };
        return `
            <p class="dz-label">Come lo legge il giocatore</p>
            ${K.errors.length ? `<ul class="dz-errors">${K.errors.map((x) => `<li>${e(x)}</li>`).join('')}</ul>` : ''}
            <div class="dz-read">${block('it')}</div>
            <div class="dz-read dz-read--en">${block('en')}</div>`;
    };

    const simHtml = () => {
        const s = K.sim;
        const bar = (v) => `<span class="dz-bar"><i style="--l:${Math.max(0, Math.min(100, (v - 30) / 40 * 100))}%"></i></span>`;
        if (!s) {
            return `
                <p class="dz-label">Prova: 2.000 partite tra bot</p>
                <p class="admin-muted dz-small">Il personaggio in squadre a caso della Ranked, contro squadre a caso, a lati scambiati. ${K.dirty ? 'Si prova il kit modificato e quello di prima.' : ''}</p>
                <button type="button" class="admin-btn" data-sim-run><i class="fa-solid fa-flask"></i> Fai la prova</button>`;
        }
        const win = s.after.games ? 100 * s.after.wins / s.after.games : null;
        const before = s.before?.games ? 100 * s.before.wins / s.before.games : null;
        const cls = win === null ? '' : win >= 56 ? ' is-high' : win <= 44 ? ' is-low' : '';
        return `
            <p class="dz-label">Prova: ${num(s.after.played)} partite tra bot${s.running ? ` <small>· ${Math.round(100 * s.done / s.total)}%</small>` : ''}</p>
            <div class="dz-win${cls}"><strong>${win === null ? '—' : pct(win)}</strong><span>vittorie con ${e(K.view.character.name)}</span></div>
            <div class="dz-band">${win === null ? '' : bar(win)}<span class="dz-band-l"><small>30%</small><small>la parte chiara, tra 44% e 56%, va bene</small><small>70%</small></span></div>
            <dl class="dz-dl">
                <div><dt>Danno medio dell'abilità</dt><dd>${s.after.skill_uses ? num(s.after.skill_dmg / s.after.skill_uses) : '—'}</dd></div>
                <div><dt>Turni medi a partita</dt><dd>${s.after.played ? num(s.after.turns / s.after.played) : '—'}</dd></div>
                ${s.before ? `<div><dt>Prima della modifica</dt><dd>${before === null ? '—' : pct(before)}</dd></div>` : ''}
            </dl>
            ${s.running ? '' : '<button type="button" class="admin-btn admin-btn--small admin-btn--ghost" data-sim-run><i class="fa-solid fa-rotate"></i> Rifai la prova</button>'}`;
    };

    const versionsHtml = () => {
        const v = K.view;
        return `
            <details class="dz-versions">
                <summary>Versioni${v.versions.length ? ` <small>${v.versions.length}</small>` : ''}</summary>
                <ul>
                    ${v.versions.map((x) => `<li><span><b>v${x.version}</b> ${e(x.note || 'salvata')}<small>${e(x.by || '')} · ${dateTime(x.at)}</small></span>
                        ${x.version === v.version && v.source === 'pannello' ? '<em>in uso</em>' : `<button type="button" class="admin-btn admin-btn--small admin-btn--ghost" data-restore="${x.id}">Rimetti in uso</button>`}</li>`).join('')}
                    <li><span><b>Codice</b> il kit scritto in includes/duel/</span>${v.source === 'pannello' ? '<button type="button" class="admin-btn admin-btn--small admin-btn--ghost" data-kit-reset>Torna a questo</button>' : '<em>in uso</em>'}</li>
                </ul>
            </details>`;
    };

    const editorHtml = () => {
        const v = K.view;
        const c = v.character;
        const body = K.tab === 'passive' ? passiveHtml() : K.tab === 'more' ? moreHtml() : `${K.tab === 'ultimate' ? musicHtml() : ''}${moveHtml(K.tab)}`;
        return `
            <div class="dz-card dz-editor">
                <div class="dz-head">
                    ${c.img ? `<img src="${e(c.img)}" alt="" style="--r:${e(c.rarity.color)}">` : `<span class="dz-head-ini" style="--r:${e(c.rarity.color)}">${e(c.name.charAt(0))}</span>`}
                    <div>
                        <h3>${e(c.name)}</h3>
                        <div class="dz-tags"><span class="dz-tag" style="--r:${e(c.rarity.color)}">${e(c.rarity.label)}</span><span class="dz-tag">${e(c.role)}</span>
                            ${v.traits.filter((t) => t.member).map((t) => `<span class="dz-tag">Legame: ${e(t.it)}</span>`).join('')}
                            <span class="dz-src dz-src--${v.source}">${SOURCE[v.source][1]}${v.source === 'pannello' ? ` · v${v.version}` : ''}</span></div>
                    </div>
                </div>
                <div class="cmod-tabs dz-tabs" role="tablist">${TABS.map(([k, l]) => `<button type="button" role="tab" class="${K.tab === k ? 'is-active' : ''}" aria-selected="${K.tab === k}" data-k-tab="${k}">${l}</button>`).join('')}</div>
                <div class="dz-tab-body" data-k-body>${body}</div>
            </div>
            <div class="dz-card dz-side">
                <div data-k-text>${textHtml()}</div>
                <div class="dz-sep"></div>
                <div data-k-sim>${simHtml()}</div>
                <div class="dz-sep"></div>
                <label class="dz-f dz-f--wide"><span>Nota per le versioni (facoltativa)</span><input class="admin-input" data-k-note maxlength="160" placeholder="es. abilità più forte, meno cure"></label>
                <div class="dz-actions">
                    <button type="button" class="admin-btn admin-btn--primary" data-kit-save ${K.dirty && !K.errors.length ? '' : 'disabled'}><i class="fa-solid fa-check"></i> Salva e pubblica</button>
                    ${K.dirty ? '<button type="button" class="admin-btn admin-btn--ghost" data-kit-undo>Annulla le modifiche</button>' : ''}
                </div>
                ${versionsHtml()}
            </div>`;
    };

    const renderKits = () => {
        if (!kitRoot) return;
        kitRoot.innerHTML = `
            <div class="dz-kits">
                <div class="dz-card dz-col-list" data-k-list>${K.list ? listHtml() : '<div class="dz-pad admin-muted">Caricamento…</div>'}</div>
                ${K.view ? editorHtml() : `<div class="dz-card dz-empty-editor">${emptyState('fa-solid fa-wand-magic-sparkles', 'Scegli un personaggio', 'A sinistra tutti i personaggi: «unico» chi ha un kit suo, «ruolo» chi lo prende dal ruolo e dalla rarità, «pannello» chi è stato scritto qui.')}</div>`}
            </div>`;
    };
    const paintList = () => { const box = $('[data-k-list]', kitRoot); if (box) box.innerHTML = listHtml(); };
    const paintBody = () => {
        const box = $('[data-k-body]', kitRoot);
        if (!box) return renderKits();
        // L'editor si ridisegna senza perdere il punto in cui si scrive.
        const focus = document.activeElement?.dataset?.path;
        renderKits();
        if (focus) {
            const el = $(`[data-path="${CSS.escape(focus)}"]`, kitRoot);
            if (el) { el.focus(); if (el.select && el.type !== 'number') { /* resta il cursore */ } }
        }
    };
    const paintSide = () => {
        const t = $('[data-k-text]', kitRoot);
        if (t) t.innerHTML = textHtml();
        const s = $('[data-k-sim]', kitRoot);
        if (s) s.innerHTML = simHtml();
        const save = $('[data-kit-save]', kitRoot);
        if (save) save.disabled = !(K.dirty && !K.errors.length);
    };

    const loadKits = async () => {
        if (!kitRoot) return;
        try {
            const data = await get({ action: 'kits' });
            K.list = data.characters;
            if (!K.view) renderKits(); else paintList();
        } catch (error) {
            kitRoot.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Duelli non disponibili', error.message);
        }
    };

    const openKit = async (id) => {
        try {
            const data = await get({ action: 'kit', id });
            A.discardPendingUploads?.();
            K.id = id;
            K.view = data;
            K.catalog = data.catalog;
            K.draft = clone(data.kit);
            K.dirty = false;
            K.preview = null;
            K.errors = [];
            K.sim = null;
            renderKits();
        } catch (error) {
            showToast(error.message, true);
        }
    };
    const askLeave = (then) => {
        if (!K.dirty) return then();
        confirmBox('Lasciare le modifiche?', '<p class="admin-muted">Il kit ha modifiche non salvate: andando avanti si perdono.</p>', async () => then());
    };

    // ── Le modifiche: un percorso tipo "skill.effects.0.pct" ─────────────
    const walk = (path, create = false) => {
        const keys = path.split('.');
        let node = K.draft;
        for (let i = 0; i < keys.length - 1; i++) {
            const k = /^\d+$/.test(keys[i]) ? Number(keys[i]) : keys[i];
            if (node[k] === undefined || node[k] === null || (typeof node[k] !== 'object')) {
                if (!create) return [null, null];
                node[k] = /^\d+$/.test(keys[i + 1]) ? [] : {};
            }
            node = node[k];
        }
        const last = keys[keys.length - 1];
        return [node, /^\d+$/.test(last) ? Number(last) : last];
    };
    const setPath = (path, value) => {
        const [node, key] = walk(path, true);
        if (!node) return;
        if (value === undefined) {
            if (Array.isArray(node)) node.splice(key, 1); else delete node[key];
        } else {
            node[key] = value;
        }
    };
    const getPath = (path) => {
        const [node, key] = walk(path);
        return node ? node[key] : undefined;
    };

    const changed = () => {
        K.dirty = JSON.stringify(K.draft) !== JSON.stringify(K.view.kit);
        K.sim = null;
        schedulePreview();
    };
    const schedulePreview = debounce(async () => {
        if (!K.view) return;
        const id = K.id;
        try {
            const data = await post('kit_preview', { id, kit: K.draft });
            if (id !== K.id) return;
            K.preview = data;
            K.errors = [];
        } catch (error) {
            if (id !== K.id) return;
            K.errors = String(error.message).split('\n');
        }
        paintSide();
        if (K.tab === 'more') {
            const box = $('.dz-stats .dz-mini', kitRoot);
            if (box && K.preview) paintBody();
        }
    }, 350);

    const fieldValue = (el) => {
        if (el.type === 'checkbox') return el.checked ? true : undefined;
        if (el.multiple) return $$('option:checked', el).map((o) => o.value);
        if (el.type === 'number') return el.value === '' ? undefined : Number(el.value);
        return el.value;
    };

    // ── La prova da 2.000 partite, a blocchi da 250 ──────────────────────
    const runSim = async () => {
        if (K.simRunning || !K.view) return;
        const id = K.id;
        const draft = K.dirty ? clone(K.draft) : null;
        const chunks = 8;
        const add = (acc, r) => {
            ['games', 'draws', 'wins', 'turns', 'played', 'limit', 'skill_uses', 'skill_dmg'].forEach((k) => { acc[k] = (acc[k] || 0) + r[k]; });
            return acc;
        };
        K.simRunning = true;
        K.sim = { after: {}, before: draft ? {} : null, done: 0, total: chunks * (draft ? 2 : 1), running: true };
        paintSide();
        try {
            for (let i = 0; i < chunks; i++) {
                const seed = i + 1;
                const after = await post('kit_sim', draft ? { id, kit: draft, n: 250, seed } : { id, n: 250, seed });
                if (id !== K.id) return;
                add(K.sim.after, after);
                K.sim.done++;
                if (draft) {
                    const before = await post('kit_sim', { id, n: 250, seed });
                    if (id !== K.id) return;
                    add(K.sim.before, before);
                    K.sim.done++;
                }
                paintSide();
            }
            K.sim.running = false;
        } catch (error) {
            K.sim = null;
            showToast(error.message, true);
        } finally {
            K.simRunning = false;
            paintSide();
        }
    };

    const saveKit = async () => {
        const note = $('[data-k-note]', kitRoot)?.value || '';
        try {
            const data = await post('kit_save', { id: K.id, kit: K.draft, note });
            showToast(data.message);
            K.view = data;
            K.draft = clone(data.kit);
            K.dirty = false;
            K.preview = null;
            K.errors = [];
            renderKits();
            loadKits();
        } catch (error) {
            K.errors = String(error.message).split('\n');
            paintSide();
            showToast(K.errors[0], true);
        }
    };

    const jsonEdit = (title, value, onSave) => {
        A.openModal?.(title, 'Per i campi che l\'editor non mostra. Il controllo e la prova girano quando chiudi.',
            `<textarea class="admin-input dz-json dz-json--big" id="dzJson" spellcheck="false" rows="14">${e(JSON.stringify(value, null, 2))}</textarea>`,
            '<button type="button" class="admin-btn" data-bs-dismiss="modal">Annulla</button><button type="button" class="admin-btn admin-btn--primary" id="dzJsonOk">Usa questo</button>');
        $('#dzJsonOk')?.addEventListener('click', () => {
            try {
                onSave(JSON.parse($('#dzJson').value));
                A.closeModal?.();
            } catch (error) {
                showToast('JSON non valido: ' + error.message, true);
            }
        });
    };

    if (kitRoot) {
        kitRoot.addEventListener('input', (ev) => {
            const el = ev.target;
            if (el.matches('[data-k-search]')) {
                K.q = el.value;
                const pos = el.selectionStart;
                paintList();
                const s = $('[data-k-search]', kitRoot);
                s?.focus();
                s?.setSelectionRange(pos, pos);
                return;
            }
            if (el.matches('[data-color]')) {
                setPath('theme.color', el.value);
                const text = $('[data-path="theme.color"]', kitRoot);
                if (text) text.value = el.value;
                changed();
                return;
            }
            if (el.matches('[data-path]') && !el.matches('select') && el.type !== 'checkbox') {
                setPath(el.dataset.path, fieldValue(el));
                changed();
            }
        });
        kitRoot.addEventListener('change', (ev) => {
            const el = ev.target;
            if (el.matches('[data-music-upload]')) return uploadMusic(el);
            if (el.matches('[data-cond]')) {
                const path = el.dataset.cond;
                const v = el.value;
                const cond = v === '' ? undefined : v === 'crit' ? { crit: true } : v === 'nocrit' ? { crit: false } : v === 'killed' ? { killed: true }
                    : v === 'chance' ? { chance: 50 } : v === 'target_has' ? { target_has: 'neg' } : { [v]: 50 };
                setPath(`${path}.if`, cond);
                changed();
                paintBody();
                return;
            }
            if (el.matches('[data-mod-add]')) {
                if (el.value) { setPath(`${el.dataset.modAdd}.${el.value}`, 0); changed(); paintBody(); }
                return;
            }
            if (el.matches('[data-trait]')) return saveTraits();
            if (el.matches('[data-json]')) {
                try {
                    const v = JSON.parse(el.value || '{}');
                    if (!isObj(v)) throw new Error('serve un oggetto { }');
                    if (el.dataset.json === 'passive') {
                        const p = K.draft.passive || (K.draft.passive = { name: 'Passiva', triggers: [] });
                        Object.keys(p).filter((k) => !['name', 'mods', 'aura', 'triggers'].includes(k)).forEach((k) => delete p[k]);
                        Object.assign(p, v);
                    } else if (Object.keys(v).length) {
                        K.draft[el.dataset.json] = v;
                    } else {
                        delete K.draft[el.dataset.json];
                    }
                    el.classList.remove('is-bad');
                    changed();
                } catch (error) {
                    el.classList.add('is-bad');
                    showToast('JSON non valido: ' + error.message, true);
                }
                return;
            }
            if (el.matches('[data-path]') && (el.matches('select') || el.type === 'checkbox')) {
                const path = el.dataset.path;
                if (/\.e$/.test(path)) {
                    // Effetto cambiato: i numeri del tipo nuovo, tenendo bersaglio e condizione.
                    const base = path.replace(/\.e$/, '');
                    const old = getPath(base) || {};
                    const fresh = { e: el.value, ...clone(FX[el.value].make) };
                    if (old.to && 'to' in fresh) fresh.to = old.to;
                    if (old.if) fresh.if = old.if;
                    setPath(base, fresh);
                } else if (/\.when$/.test(path)) {
                    setPath(path, el.value);
                    if (['self_low', 'ally_low'].includes(el.value) && getPath(path.replace(/when$/, 'below')) === undefined) setPath(path.replace(/when$/, 'below'), 30);
                } else {
                    setPath(path, fieldValue(el));
                }
                changed();
                if (el.matches('select')) paintBody(); else paintSide();
            }
        });
        kitRoot.addEventListener('click', (ev) => {
            const t = ev.target.closest('button, [data-k-open]');
            if (!t) return;
            if (t.dataset.kOpen) return askLeave(() => openKit(Number(t.dataset.kOpen)));
            if (t.dataset.kFilter) { K.filter = t.dataset.kFilter; paintList(); return; }
            if (t.dataset.kTab) { K.tab = t.dataset.kTab; renderKits(); return; }
            if ('fxAdd' in t.dataset) {
                const list = getPath(t.dataset.fxAdd) || [];
                list.push({ e: 'damage', ...clone(FX.damage.make) });
                setPath(t.dataset.fxAdd, list);
                changed();
                paintBody();
                return;
            }
            const row = t.closest('[data-fx]');
            if (row && ('fxDel' in t.dataset || 'fxMove' in t.dataset || 'fxJson' in t.dataset)) {
                const path = row.dataset.fx;
                const listPath = path.replace(/\.\d+$/, '');
                const index = Number(path.split('.').pop());
                const list = getPath(listPath);
                if ('fxDel' in t.dataset) list.splice(index, 1);
                if ('fxMove' in t.dataset) {
                    const to = index + Number(t.dataset.fxMove);
                    [list[index], list[to]] = [list[to], list[index]];
                }
                if ('fxJson' in t.dataset) {
                    return jsonEdit('Effetto come JSON', list[index], (value) => {
                        if (!isObj(value) || !value.e) throw new Error('serve un effetto con "e"');
                        list[index] = value;
                        changed();
                        paintBody();
                    });
                }
                changed();
                paintBody();
                return;
            }
            if (t.dataset.modDel) { setPath(t.dataset.modDel, undefined); changed(); paintBody(); return; }
            if ('triggerAdd' in t.dataset) {
                const p = K.draft.passive || (K.draft.passive = { name: 'Passiva' });
                (p.triggers ||= []).push({ when: 'turn_start', effects: [{ e: 'heal', to: 'self', pct: 5 }] });
                changed();
                paintBody();
                return;
            }
            if (t.dataset.triggerDel !== undefined && t.dataset.triggerDel !== '') {
                K.draft.passive.triggers.splice(Number(t.dataset.triggerDel), 1);
                changed();
                paintBody();
                return;
            }
            if ('stacksOn' in t.dataset) { K.draft.stacks = { name: 'Carica', max: 5, per: { dmg_pct: 5 } }; changed(); paintBody(); return; }
            if ('stacksOff' in t.dataset) { delete K.draft.stacks; changed(); paintBody(); return; }
            if ('musicClear' in t.dataset) { delete K.draft.theme.music; if (!Object.keys(K.draft.theme).length) delete K.draft.theme; changed(); paintBody(); return; }
            if ('simRun' in t.dataset) return runSim();
            if ('kitSave' in t.dataset) return saveKit();
            if ('kitUndo' in t.dataset) { K.draft = clone(K.view.kit); K.dirty = false; K.preview = null; K.errors = []; K.sim = null; renderKits(); return; }
            if (t.dataset.restore) {
                return confirmBox('Rimettere in uso questa versione?', '<p class="admin-muted">Diventa la versione nuova del kit e vale dalla prossima partita. Le altre restano nell\'elenco.</p>', async () => {
                    const data = await post('kit_restore', { id: K.id, version_id: Number(t.dataset.restore) });
                    showToast(data.message);
                    K.view = data; K.draft = clone(data.kit); K.dirty = false; K.preview = null; K.errors = []; K.sim = null;
                    renderKits();
                    loadKits();
                });
            }
            if ('kitReset' in t.dataset) {
                return confirmBox('Tornare al kit del codice?', '<p class="admin-muted">Il kit scritto nel pannello smette di valere dalla prossima partita; resta tra le versioni e si può rimettere in uso.</p>', async () => {
                    const data = await post('kit_reset', { id: K.id });
                    showToast(data.message);
                    K.view = data; K.draft = clone(data.kit); K.dirty = false; K.preview = null; K.errors = []; K.sim = null;
                    renderKits();
                    loadKits();
                });
            }
        });
    }

    const saveTraits = async () => {
        const keys = $$('[data-trait]:checked:not(:disabled)', kitRoot).map((x) => x.dataset.trait);
        try {
            const data = await post('kit_traits', { id: K.id, traits: keys });
            showToast(data.message);
            K.view.traits = data.traits;
            K.view.text = data.text;
            if (!K.dirty) K.preview = null;
            renderKits();
        } catch (error) {
            showToast(error.message, true);
        }
    };

    const uploadMusic = async (input) => {
        const file = input.files?.[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('file', file);
        fd.append('type', 'audio');
        fd.append('folder', 'ultimates');
        try {
            showToast('Caricamento della musica…');
            const res = await api('upload_media.php', { method: 'POST', body: fd });
            A.trackUpload?.(res.url);
            K.draft.theme = { ...(K.draft.theme || {}), music: res.url };
            changed();
            paintBody();
            showToast('Musica caricata: vale quando salvi il kit.');
        } catch (error) {
            showToast(error.message, true);
        } finally {
            input.value = '';
        }
    };

    A.registerSection('duel-kits', () => { loadKits(); if (K.id && !K.dirty) openKit(K.id); });

    // ════════════════════════════════════════════════════════════════════
    // Stati e regole
    // ════════════════════════════════════════════════════════════════════
    const valRoot = $('[data-duel-admin="values"]');
    const V = { view: null, draft: { rules: {}, status: {} }, sim: null, simRunning: false, fam: 'all' };

    const valuesDirty = () => Object.keys(V.draft.rules).length + Object.keys(V.draft.status).length > 0;
    const ruleValue = (r) => (r.key in V.draft.rules ? V.draft.rules[r.key] : r.value);
    const statusValue = (s, f) => (V.draft.status[s.key] && f in V.draft.status[s.key] ? V.draft.status[s.key][f] : s.value[f]);

    const renderValues = () => {
        if (!valRoot) return;
        const v = V.view;
        if (!v) { valRoot.innerHTML = '<div class="dz-pad admin-muted">Caricamento…</div>'; return; }
        const fams = [...new Set(v.statuses.map((s) => s.fam_label))];
        const sim = V.sim;
        valRoot.innerHTML = `
            <div class="dz-bar-top">
                <p class="admin-muted">Valgono per tutti i kit che li usano, dalla prossima partita. Le partite già cominciate tengono i loro. Con ↺ si torna al valore del codice.</p>
                <div class="dz-actions">
                    <button type="button" class="admin-btn" data-v-sim ${V.simRunning ? 'disabled' : ''}><i class="fa-solid fa-flask"></i> Prova le modifiche</button>
                    <button type="button" class="admin-btn admin-btn--primary" data-v-save ${valuesDirty() ? '' : 'disabled'}><i class="fa-solid fa-check"></i> Salva</button>
                    ${valuesDirty() ? '<button type="button" class="admin-btn admin-btn--ghost" data-v-undo>Annulla</button>' : ''}
                </div>
            </div>
            ${sim ? `<div class="dz-card dz-vsim">
                <p class="dz-label">Prova: ${num(sim.after.played || 0)} partite tra bot ${sim.running ? `<small>· ${Math.round(100 * sim.done / sim.total)}%</small>` : ''}</p>
                <table class="dz-mini"><thead><tr><th></th><th>Adesso</th><th>Con le modifiche</th></tr></thead><tbody>
                    <tr><td>Turni medi a partita</td><td>${sim.before.played ? num(sim.before.turns / sim.before.played, 1) : '—'}</td><td>${sim.after.played ? num(sim.after.turns / sim.after.played, 1) : '—'}</td></tr>
                    <tr><td>Finite al limite di turni</td><td>${sim.before.played ? pct(100 * sim.before.limit / sim.before.played) : '—'}</td><td>${sim.after.played ? pct(100 * sim.after.limit / sim.after.played) : '—'}</td></tr>
                    ${Object.keys(sim.after.roles || {}).sort().map((r) => {
                        const w = (o) => (o.roles?.[r]?.g ? pct(100 * o.roles[r].w / o.roles[r].g) : '—');
                        return `<tr><td>Vittorie ${e(r)}</td><td>${w(sim.before)}</td><td>${w(sim.after)}</td></tr>`;
                    }).join('')}
                </tbody></table></div>` : ''}
            <div class="dz-values">
                <div class="dz-card">
                    <p class="dz-label">Regole</p>
                    ${v.groups.map((g) => `<div class="dz-vgroup"><b>${e(g.name)}</b>${g.rules.map((r) => {
                        const val = ruleValue(r);
                        const mod = (r.key in V.draft.rules) || r.changed;
                        return `<label class="dz-vrow${mod ? ' is-mod' : ''}"><span>${e(r.label)}${r.help ? `<small>${e(r.help)}</small>` : ''}</span>
                            <input type="number" class="admin-input dz-vin" step="any" min="0" data-rule="${e(r.key)}" value="${val ?? r.base}" aria-label="${e(r.label)}">
                            <small class="dz-base">codice ${num(r.base, 2).replace(/,00$/, '')}</small>
                            ${mod && Number(val ?? r.base) !== Number(r.base) ? `<button type="button" class="dz-tool" data-rule-reset="${e(r.key)}" title="Torna al valore del codice" aria-label="Torna al valore del codice">↺</button>` : '<span></span>'}</label>`;
                    }).join('')}</div>`).join('')}
                </div>
                <div class="dz-card">
                    <div class="dz-vhead"><p class="dz-label">Stati</p>
                        <select class="admin-input" data-v-fam aria-label="Famiglia">${['all', ...fams].map((f) => `<option value="${e(f)}"${V.fam === f ? ' selected' : ''}>${f === 'all' ? 'Tutte le famiglie' : e(f)}</option>`).join('')}</select></div>
                    <table class="dz-mini dz-vstat"><thead><tr><th>Stato</th><th>Valore</th><th>Turni</th><th>Massimo</th><th></th></tr></thead><tbody>
                        ${v.statuses.filter((s) => V.fam === 'all' || s.fam_label === V.fam).map((s) => {
                            const mod = !!V.draft.status[s.key] || s.changed;
                            const cell = (f) => `<td><input type="number" class="admin-input dz-vin" step="any" data-status="${e(s.key)}" data-f="${f}" value="${statusValue(s, f)}" aria-label="${e(s.name)} ${f}">${Number(statusValue(s, f)) !== Number(s.base[f]) ? `<small class="dz-base">${num(s.base[f], 1).replace(/,0$/, '')}</small>` : ''}</td>`;
                            return `<tr class="${mod ? 'is-mod' : ''}"><td><b>${e(s.name)}</b><small>${e(s.desc)}</small></td>${cell('v')}${cell('t')}${s.stack === 'stacks' ? cell('max') : '<td class="admin-muted">—</td>'}
                                <td>${mod ? `<button type="button" class="dz-tool" data-status-reset="${e(s.key)}" title="Torna ai valori del codice" aria-label="Torna ai valori del codice">↺</button>` : ''}</td></tr>`;
                        }).join('')}
                    </tbody></table>
                </div>
            </div>`;
    };

    const loadValues = async () => {
        if (!valRoot) return;
        try {
            V.view = await get({ action: 'values' });
            renderValues();
        } catch (error) {
            valRoot.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Valori non disponibili', error.message);
        }
    };

    const runValuesSim = async () => {
        if (V.simRunning) return;
        const values = clone(V.draft);
        const chunks = 4;
        const add = (acc, r) => {
            ['games', 'draws', 'wins', 'turns', 'played', 'limit'].forEach((k) => { acc[k] = (acc[k] || 0) + r[k]; });
            acc.roles ||= {};
            Object.entries(r.roles || {}).forEach(([role, s]) => {
                acc.roles[role] ||= { g: 0, w: 0 };
                acc.roles[role].g += s.g;
                acc.roles[role].w += s.w;
            });
        };
        V.simRunning = true;
        V.sim = { after: {}, before: {}, done: 0, total: chunks * 2, running: true };
        renderValues();
        try {
            for (let i = 0; i < chunks; i++) {
                add(V.sim.after, await post('values_sim', { values, n: 250, seed: 100 + i }));
                V.sim.done++;
                add(V.sim.before, await post('values_sim', { n: 250, seed: 100 + i }));
                V.sim.done++;
                renderValues();
            }
            V.sim.running = false;
        } catch (error) {
            V.sim = null;
            showToast(error.message, true);
        } finally {
            V.simRunning = false;
            renderValues();
        }
    };

    if (valRoot) {
        valRoot.addEventListener('change', (ev) => {
            const el = ev.target;
            if (el.matches('[data-v-fam]')) { V.fam = el.value; renderValues(); return; }
            if (el.dataset.rule) {
                const r = V.view.groups.flatMap((g) => g.rules).find((x) => x.key === el.dataset.rule);
                if (el.value === '' || Number(el.value) === Number(r.value)) delete V.draft.rules[r.key];
                else V.draft.rules[r.key] = Number(el.value);
                V.sim = null;
                renderValues();
            }
            if (el.dataset.status) {
                const s = V.view.statuses.find((x) => x.key === el.dataset.status);
                const f = el.dataset.f;
                const d = V.draft.status[s.key] || {};
                if (el.value === '' || Number(el.value) === Number(s.value[f])) delete d[f];
                else d[f] = Number(el.value);
                if (Object.keys(d).length) V.draft.status[s.key] = d; else delete V.draft.status[s.key];
                V.sim = null;
                renderValues();
            }
        });
        valRoot.addEventListener('click', async (ev) => {
            const t = ev.target.closest('button');
            if (!t) return;
            if (t.dataset.ruleReset) { V.draft.rules[t.dataset.ruleReset] = null; renderValues(); }
            if (t.dataset.statusReset) { V.draft.status[t.dataset.statusReset] = { v: null, t: null, max: null }; renderValues(); }
            if ('vUndo' in t.dataset) { V.draft = { rules: {}, status: {} }; V.sim = null; renderValues(); }
            if ('vSim' in t.dataset) runValuesSim();
            if ('vSave' in t.dataset) {
                try {
                    const data = await post('values_save', { values: V.draft });
                    showToast(data.message);
                    V.view = data;
                    V.draft = { rules: {}, status: {} };
                    V.sim = null;
                    renderValues();
                } catch (error) {
                    showToast(error.message, true);
                }
            }
        });
    }
    A.registerSection('duel-values', loadValues);

    // ════════════════════════════════════════════════════════════════════
    // Bilanciamento
    // ════════════════════════════════════════════════════════════════════
    const balRoot = $('[data-duel-admin="balance"]');
    const B = { view: null, days: 30, by: 'characters', filter: 'all', running: null };
    const STATUS_BADGE = {
        limare: ['admin-badge admin-badge--danger', 'da limare'], rinforzare: ['admin-badge dz-badge-low', 'da rinforzare'],
        ok: ['admin-badge', 'ok'], pochi: ['admin-badge dz-badge-few', 'pochi dati'],
    };
    const winBar = (v) => {
        if (v === null || v === undefined) return '<span class="admin-muted">—</span>';
        const cls = v >= B.view.high ? 'is-high' : v <= B.view.low ? 'is-low' : '';
        return `<span class="dz-wbar ${cls}"><i style="width:${Math.max(2, Math.min(100, v))}%"></i><u></u></span><b class="dz-wnum">${pct(v)}</b>`;
    };

    const renderBalance = () => {
        if (!balRoot) return;
        const v = B.view;
        if (!v) { balRoot.innerHTML = '<div class="dz-pad admin-muted">Caricamento…</div>'; return; }
        const sim = v.sim;
        const rows = B.by === 'characters' ? v.characters.filter((c) => B.filter === 'all' ? (c.real.games || c.sim.games) : c.status === B.filter)
            .sort((a, b) => (a.status === 'pochi') - (b.status === 'pochi') || ((b.real.games >= 20 ? b.real.win : b.sim.win) ?? -1) - ((a.real.games >= 20 ? a.real.win : a.sim.win) ?? -1))
            : (B.by === 'roles' ? v.roles : v.rarities);
        balRoot.innerHTML = `
            <div class="dz-bar-top">
                <div class="dz-actions">
                    <div class="cmod-tabs dz-seg">${[['characters', 'Personaggi'], ['roles', 'Ruoli'], ['rarities', 'Rarità']].map(([k, l]) => `<button type="button" class="${B.by === k ? 'is-active' : ''}" data-b-by="${k}">${l}</button>`).join('')}</div>
                    ${B.by === 'characters' ? `<select class="admin-input" data-b-filter aria-label="Filtra"><option value="all">Tutti con partite</option><option value="limare"${B.filter === 'limare' ? ' selected' : ''}>Da limare</option><option value="rinforzare"${B.filter === 'rinforzare' ? ' selected' : ''}>Da rinforzare</option><option value="ok"${B.filter === 'ok' ? ' selected' : ''}>Ok</option><option value="pochi"${B.filter === 'pochi' ? ' selected' : ''}>Pochi dati</option></select>` : ''}
                    <select class="admin-input" data-b-days aria-label="Periodo">${[7, 30, 90].map((d) => `<option value="${d}"${B.days === d ? ' selected' : ''}>Ultimi ${d} giorni</option>`).join('')}</select>
                </div>
                <div class="dz-actions">
                    <span class="admin-muted dz-small">${sim.games ? `Prove: ${num(sim.games)} partite tra bot · ${dateTime(sim.at)}` : 'Nessuna prova tra bot ancora.'}</span>
                    <button type="button" class="admin-btn" data-b-sim ${B.running || !sim.ready ? 'disabled' : ''}>${B.running ? `<i class="fa-solid fa-spinner fa-spin"></i> ${Math.round(100 * B.running.done / B.running.total)}%` : '<i class="fa-solid fa-rotate"></i> Rifai le prove (6.000 partite)'}</button>
                </div>
            </div>
            ${!sim.ready ? '<p class="dz-warn">Per le prove tra bot manca la migrazione <code>migrations/2026_10_21_duelli2_fase7.sql</code>: eseguila in phpMyAdmin.</p>' : ''}
            <p class="admin-muted">Partite vere degli ultimi ${v.days} giorni (Ranked e Veloce): <b>${num(v.matches)}</b>. Più le prove tra bot. Rosso: vince troppo (dal ${num(v.high)}%); azzurro: vince troppo poco (fino al ${num(v.low)}%). Il giudizio usa le partite vere da 20 in su, altrimenti le prove.</p>
            ${rows.length ? `<table class="admin-table dz-btable"><thead><tr><th>${B.by === 'characters' ? 'Personaggio' : B.by === 'roles' ? 'Ruolo' : 'Rarità'}</th>${B.by === 'characters' ? '<th>Scelto</th>' : ''}<th>Vittorie</th><th>Partite</th><th>Bot</th>${B.by === 'characters' ? '<th>Stato</th>' : ''}</tr></thead><tbody>
                ${rows.map((r) => `<tr>
                    <td data-label="${B.by === 'characters' ? 'Personaggio' : ''}">${B.by === 'characters' ? `<span class="dz-who">${r.img ? `<img src="${e(r.img)}" alt="" loading="lazy">` : ''}<span><b>${e(r.name)}</b><small>${e(r.role)} · ${e(r.rarity.label)}</small></span></span>` : `<b>${e(r.label)}</b>`}</td>
                    ${B.by === 'characters' ? `<td data-label="Scelto">${r.picked === null ? '—' : pct(r.picked)}</td>` : ''}
                    <td data-label="Vittorie">${winBar(r.real.win)}</td>
                    <td data-label="Partite">${num(r.real.games)}</td>
                    <td data-label="Bot">${r.sim.win === null ? '—' : `${pct(r.sim.win)} <small class="admin-muted">(${num(r.sim.games)})</small>`}</td>
                    ${B.by === 'characters' ? `<td data-label="Stato"><span class="${STATUS_BADGE[r.status][0]}">${STATUS_BADGE[r.status][1]}</span>${r.status !== 'pochi' ? ` <button type="button" class="admin-link-btn" data-b-kit="${r.id}">kit</button>` : ''}</td>` : ''}
                </tr>`).join('')}
            </tbody></table>` : emptyState('fa-solid fa-scale-balanced', 'Ancora niente da mostrare', 'Servono partite vere negli ultimi giorni o le prove tra bot.')}`;
    };

    const loadBalance = async () => {
        if (!balRoot) return;
        try {
            B.view = await get({ action: 'balance', days: B.days });
            renderBalance();
        } catch (error) {
            balRoot.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Bilanciamento non disponibile', error.message);
        }
    };

    const runBalanceSim = async () => {
        const chunks = 30;
        B.running = { done: 0, total: chunks };
        renderBalance();
        try {
            await post('balance_sim_start');
            for (let i = 0; i < chunks; i++) {
                await post('balance_sim_chunk', { seed: Date.now() % 100000 + i, n: 200 });
                B.running.done++;
                renderBalance();
            }
            showToast('Prove rifatte.');
        } catch (error) {
            showToast(error.message, true);
        } finally {
            B.running = null;
            loadBalance();
        }
    };

    if (balRoot) {
        balRoot.addEventListener('change', (ev) => {
            if (ev.target.matches('[data-b-days]')) { B.days = Number(ev.target.value); loadBalance(); }
            if (ev.target.matches('[data-b-filter]')) { B.filter = ev.target.value; renderBalance(); }
        });
        balRoot.addEventListener('click', (ev) => {
            const t = ev.target.closest('button');
            if (!t) return;
            if (t.dataset.bBy) { B.by = t.dataset.bBy; renderBalance(); }
            if ('bSim' in t.dataset) {
                confirmBox('Rifare le prove tra bot?', '<p class="admin-muted">6.000 partite tra bot a blocchi da 200: ci vuole qualche minuto e la pagina deve restare aperta. Le prove di prima si cancellano.</p>', async () => { runBalanceSim(); });
            }
            if (t.dataset.bKit) {
                $('[data-admin-nav] [data-section="duel-kits"]')?.click();
                askLeave(() => openKit(Number(t.dataset.bKit)));
            }
        });
    }
    A.registerSection('duel-balance', loadBalance);

    // ════════════════════════════════════════════════════════════════════
    // Stagioni
    // ════════════════════════════════════════════════════════════════════
    const seaRoot = $('[data-duel-admin="seasons"]');
    let S = null;

    const switchHtml = (key, on, title, text) => `
        <label class="dz-switch"><input type="checkbox" data-switch="${key}"${on ? ' checked' : ''}><span class="dz-switch-ui" aria-hidden="true"></span>
            <span><b>${e(title)}</b><small>${text}</small></span></label>`;

    const toLocalInput = (ts) => {
        const d = new Date(ts * 1000);
        const p = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
    };

    const renderSeasons = () => {
        if (!seaRoot) return;
        if (!S) { seaRoot.innerHTML = '<div class="dz-pad admin-muted">Caricamento…</div>'; return; }
        if (!S.ready) { seaRoot.innerHTML = emptyState('fa-solid fa-calendar', 'Stagioni non pronte', 'Manca la migrazione migrations/2026_10_21_duelli2_fase6.sql.'); return; }
        const c = S.current;
        seaRoot.innerHTML = `
            <div class="dz-cols">
                <div class="dz-card">
                    ${switchHtml('seasons', S.on, 'Stagioni accese', S.on ? 'Quando una stagione finisce parte subito la prossima.' : 'Finché sono spente la lobby dice «Stagione 0 · la classifica di sempre». Accendendole parte subito una stagione.')}
                    <div class="dz-sep"></div>
                    ${c ? `
                        <form class="dz-season" data-season-form>
                            <p class="dz-label">In corso</p>
                            <h3>Stagione ${c.number}${c.name ? ` · ${e(c.name)}` : ''}</h3>
                            <span class="dz-prog"><i style="width:${Math.round(100 * c.progress)}%"></i></span>
                            <p class="admin-muted">Dal ${day(c.starts)} al ${day(c.ends)} · ${c.days_left === 1 ? 'manca 1 giorno' : `mancano ${c.days_left} giorni`} · ${num(c.players)} giocatori, ${num(c.placed)} con le ${S.placement} di piazzamento</p>
                            <div class="dz-fields">
                                <label class="dz-f dz-f--wide"><span>Nome</span><input class="admin-input" name="name" value="${e(c.name)}" maxlength="60" placeholder="Stagione ${c.number}"></label>
                                <label class="dz-f"><span>Finisce il</span><input class="admin-input" type="datetime-local" name="ends_at" value="${toLocalInput(c.ends)}"></label>
                            </div>
                            <div class="dz-actions">
                                <button type="submit" class="admin-btn admin-btn--primary"><i class="fa-solid fa-check"></i> Salva</button>
                                <button type="button" class="admin-btn admin-btn--danger" data-season-close="${c.id}"><i class="fa-solid fa-flag-checkered"></i> Chiudi adesso</button>
                            </div>
                        </form>` : `<p class="admin-muted">Nessuna stagione in corso.${S.on ? ' La prossima parte alla prossima pulizia dei duelli.' : ''}</p>`}
                </div>
                <form class="dz-card" data-rules-form>
                    <p class="dz-label">Premi di fine stagione</p>
                    <p class="admin-muted dz-small">Godos per la fascia più alta raggiunta, a chi ha giocato almeno ${S.placement} Ranked.</p>
                    <div class="dz-rewards">${Object.entries(S.rewards).map(([tier, godos]) => `<label class="dz-f"><span>${e(S.tiers[tier])}</span><span class="dz-unit"><input class="admin-input" type="number" min="0" max="100000" name="reward_${tier}" value="${godos}"><em>Godos</em></span></label>`).join('')}</div>
                    <div class="dz-fields">
                        <label class="dz-f"><span>Prima vittoria del giorno</span><span class="dz-unit"><input class="admin-input" type="number" min="0" max="10000" name="daily_win" value="${S.daily_win}"><em>Godos</em></span></label>
                        <label class="dz-f"><span>Durata delle stagioni nuove</span><span class="dz-unit"><input class="admin-input" type="number" min="7" max="120" name="days" value="${S.days}"><em>giorni</em></span></label>
                    </div>
                    <div class="dz-actions"><button type="submit" class="admin-btn admin-btn--primary"><i class="fa-solid fa-check"></i> Salva i premi</button></div>
                </form>
            </div>
            <div class="dz-card">
                <p class="dz-label">Archivio</p>
                ${S.past.length ? `<table class="admin-table"><thead><tr><th>Stagione</th><th>Date</th><th>Giocatori</th><th>Godos pagati</th><th>Podio</th><th></th></tr></thead><tbody>
                    ${S.past.map((p) => `<tr>
                        <td data-label="Stagione"><b>${p.number}</b> <input class="admin-input dz-inline" data-past-name="${p.id}" value="${e(p.name)}" maxlength="60" placeholder="senza nome" aria-label="Nome della stagione ${p.number}"></td>
                        <td data-label="Date">${day(p.starts_at)} – ${day(p.ends_at)}</td>
                        <td data-label="Giocatori">${num(p.players)} <small class="admin-muted">(${num(p.placed)} piazzati)</small></td>
                        <td data-label="Godos">${num(p.godos)}</td>
                        <td data-label="Podio">${p.top.map((t, i) => `${i + 1}. ${e(t.username)} <small class="admin-muted">${e(t.tier)} · ${num(t.peak)}</small>`).join('<br>') || '—'}</td>
                        <td><button type="button" class="admin-btn admin-btn--small admin-btn--ghost" data-past-save="${p.id}">Salva il nome</button></td>
                    </tr>`).join('')}
                </tbody></table>` : '<p class="admin-muted">Nessuna stagione finita.</p>'}
            </div>`;
    };

    const loadSeasons = async () => {
        if (!seaRoot) return;
        try {
            S = await get({ action: 'seasons' });
            renderSeasons();
        } catch (error) {
            seaRoot.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Stagioni non disponibili', error.message);
        }
    };

    if (seaRoot) {
        seaRoot.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const f = ev.target;
            try {
                let data;
                if (f.matches('[data-season-form]')) {
                    data = await post('season_save', { id: S.current.id, name: f.elements.name.value, ends_at: f.elements.ends_at.value.replace('T', ' ') });
                } else {
                    const rewards = {};
                    Object.keys(S.rewards).forEach((tier) => { rewards[tier] = Number(f.elements[`reward_${tier}`].value); });
                    data = await post('season_rules', { rewards, daily_win: Number(f.elements.daily_win.value), days: Number(f.elements.days.value) });
                }
                showToast(data.message);
                S = data;
                renderSeasons();
            } catch (error) {
                showToast(error.message, true);
            }
        });
        seaRoot.addEventListener('click', async (ev) => {
            const t = ev.target.closest('button');
            if (!t) return;
            if (t.dataset.seasonClose) {
                confirmBox('Chiudere la stagione adesso?', `<p class="admin-muted">Si pagano subito i premi a chi ha giocato le ${S.placement} di piazzamento e i punti di tutti si avvicinano a 1.000 della metà. ${S.on ? 'Poi parte la stagione dopo.' : 'Le stagioni sono spente: non ne parte un\'altra.'} Non si torna indietro.</p>`, async () => {
                    const data = await post('season_close', { id: Number(t.dataset.seasonClose) });
                    showToast(data.message);
                    S = data;
                    renderSeasons();
                });
            }
            if (t.dataset.pastSave) {
                try {
                    const input = $(`[data-past-name="${t.dataset.pastSave}"]`, seaRoot);
                    const data = await post('season_save', { id: Number(t.dataset.pastSave), name: input.value });
                    showToast(data.message);
                    S = data;
                    renderSeasons();
                } catch (error) {
                    showToast(error.message, true);
                }
            }
        });
        seaRoot.addEventListener('change', (ev) => { if (ev.target.matches('[data-switch]')) toggleSwitch(ev.target, loadSeasons); });
    }
    A.registerSection('duel-seasons', loadSeasons);

    const toggleSwitch = async (input, after) => {
        const key = input.dataset.switch;
        const on = input.checked;
        const ask = { public: on ? ['Aprire i duelli nuovi a tutti?', 'Da adesso li vede e li usa ogni utente, non solo lo staff.'] : ['Richiudere i duelli allo staff?', 'Chi non è staff vede «I nuovi duelli arrivano presto». Le partite in corso restano.'],
            seasons: on ? ['Accendere le stagioni?', 'Parte subito una stagione di 6 settimane (o la durata scelta), con premi a fine stagione.'] : ['Spegnere le stagioni?', 'Quella in corso arriva alla sua fine e paga i premi; poi non ne parte un\'altra.'] }[key];
        const go = async () => {
            const data = await post('switch', { key, on });
            showToast(data.message);
            after();
        };
        if (!ask) {
            try { await go(); } catch (error) { input.checked = !on; showToast(error.message, true); }
            return;
        }
        input.checked = !on;
        confirmBox(ask[0], `<p class="admin-muted">${e(ask[1])}</p>`, async () => { await go(); });
    };

    // ════════════════════════════════════════════════════════════════════
    // Partite
    // ════════════════════════════════════════════════════════════════════
    const matRoot = $('[data-duel-admin="matches"]');
    const M = { view: null, filter: 'live', mode: '', page: 1 };
    const MODE = { ranked: 'Ranked', casual: 'Veloce', private: 'Stanza', bot: 'Bot' };
    const REASON = { ko: 'KO', limit: 'limite di turni', forfeit: 'abbandono', timeout: 'tempo scaduto', admin: 'dallo staff', closed: 'stanza chiusa', expired: 'scaduta', queue: 'coda', idle: 'ferma', declined: 'rifiutata', cancelled: 'ritirata' };
    const STATUS_TXT = { waiting: 'in attesa', team_select: 'squadre', active: 'in corso', finished: 'finita', cancelled: 'annullata' };

    const renderMatches = () => {
        if (!matRoot) return;
        const v = M.view;
        if (!v) { matRoot.innerHTML = '<div class="dz-pad admin-muted">Caricamento…</div>'; return; }
        const live = ['waiting', 'team_select', 'active'];
        matRoot.innerHTML = `
            <div class="dz-cols">
                <div class="dz-card">
                    ${switchHtml('public', v.switches.public, 'Duelli aperti a tutti', v.switches.public ? 'Tutti gli utenti vedono e usano i duelli nuovi.' : 'Per ora li vede solo lo staff (admin e owner).')}
                    ${switchHtml('discord', v.switches.discord, 'Avvisi su Discord', 'Quando qualcuno cerca una partita da 20 secondi il bot lo scrive nel canale dei duelli (solo con i duelli aperti a tutti).')}
                </div>
                <div class="dz-card">
                    <p class="dz-label">Abbandoni sospetti · ultimi 7 giorni</p>
                    ${v.suspects.length ? `<ul class="dz-suspects">${v.suspects.map((s) => `<li><button type="button" class="admin-link-btn" data-open-user="${s.id}">${e(s.username)}</button><span>${s.count} partite lasciate entro le prime mosse</span><small class="admin-muted">${dateTime(s.last)}</small></li>`).join('')}</ul>`
                        : '<p class="admin-muted">Nessuno: chi esce da Ranked o Veloce entro 4 mosse almeno 3 volte in una settimana finisce qui.</p>'}
                </div>
            </div>
            <div class="dz-bar-top">
                <div class="cmod-tabs dz-seg">${[['live', 'In corso', v.counts.live], ['finished', 'Finite', v.counts.finished], ['cancelled', 'Annullate', v.counts.cancelled], ['all', 'Tutte', null]].map(([k, l, n]) =>
                    `<button type="button" class="${M.filter === k ? 'is-active' : ''}" data-m-filter="${k}">${l}${n !== null ? ` <small>${num(n)}</small>` : ''}</button>`).join('')}</div>
                <select class="admin-input" data-m-mode aria-label="Modalità"><option value="">Tutte le modalità</option>${Object.entries(MODE).map(([k, l]) => `<option value="${k}"${M.mode === k ? ' selected' : ''}>${l}</option>`).join('')}</select>
            </div>
            ${v.list.length ? `<table class="admin-table"><thead><tr><th>#</th><th>Modalità</th><th>Giocatori</th><th>Turno</th><th>Stato</th><th>Iniziata</th><th></th></tr></thead><tbody>
                ${v.list.map((r) => {
                    const isLive = live.includes(r.status);
                    const winner = r.result === 'p1' ? r.p1.name : r.result === 'p2' ? r.p2.name : null;
                    return `<tr>
                        <td data-label="#">${r.id}</td>
                        <td data-label="Modalità">${MODE[r.mode] || e(r.mode)}${r.async ? ' <span class="admin-badge">differita</span>' : ''}</td>
                        <td data-label="Giocatori"><b>${e(r.p1.name)}</b> <span class="admin-muted">contro</span> <b>${e(r.p2.name || '—')}</b></td>
                        <td data-label="Turno">${r.turn || '—'}</td>
                        <td data-label="Stato">${isLive ? `<span class="admin-badge admin-badge--success">${STATUS_TXT[r.status]}</span>` : r.status === 'finished'
                            ? `${winner ? `vince <b>${e(winner)}</b>` : 'pari'} <small class="admin-muted">${e(REASON[r.reason] || r.reason)}</small>`
                            : `<span class="admin-badge">annullata</span> <small class="admin-muted">${e(REASON[r.reason] || r.reason)}</small>`}</td>
                        <td data-label="Iniziata">${dateTime(r.created_at)}</td>
                        <td class="dz-row-actions">${isLive ? `<a class="admin-btn admin-btn--small admin-btn--ghost" href="/it/duelli/arena?m=${r.id}" target="_blank" rel="noopener">Guarda</a>
                            <button type="button" class="admin-btn admin-btn--small admin-btn--danger" data-m-cancel="${r.id}">Annulla</button>`
                            : r.status === 'finished' ? `<a class="admin-btn admin-btn--small admin-btn--ghost" href="/it/duelli/replay?m=${r.id}" target="_blank" rel="noopener">Replay</a>` : ''}</td>
                    </tr>`;
                }).join('')}
            </tbody></table>` : emptyState('fa-solid fa-chess-board', 'Nessuna partita', M.filter === 'live' ? 'Adesso non gioca nessuno.' : '')}
            <div class="admin-pagination" data-m-pages></div>`;
        A.pagination('[data-m-pages]', v, (page) => { M.page = page; loadMatches(); });
    };

    const loadMatches = async () => {
        if (!matRoot) return;
        try {
            M.view = await get({ action: 'matches', filter: M.filter, mode: M.mode, page: M.page });
            renderMatches();
        } catch (error) {
            matRoot.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Partite non disponibili', error.message);
        }
    };

    if (matRoot) {
        matRoot.addEventListener('click', (ev) => {
            const t = ev.target.closest('button');
            if (!t) return;
            if (t.dataset.mFilter) { M.filter = t.dataset.mFilter; M.page = 1; loadMatches(); }
            if (t.dataset.mCancel) {
                confirmBox(`Annullare la partita #${t.dataset.mCancel}?`, '<p class="admin-muted">Finisce adesso senza vincitori: nessuno prende o perde punti. Chi gioca vede «Partita annullata dallo staff».</p>', async () => {
                    const data = await post('match_cancel', { id: Number(t.dataset.mCancel) });
                    showToast(data.message);
                    loadMatches();
                });
            }
        });
        matRoot.addEventListener('change', (ev) => {
            if (ev.target.matches('[data-m-mode]')) { M.mode = ev.target.value; M.page = 1; loadMatches(); }
            if (ev.target.matches('[data-switch]')) toggleSwitch(ev.target, loadMatches);
        });
    }
    A.registerSection('duel-matches', loadMatches);

    // Lasciare la pagina con un kit non salvato: il browser chiede conferma.
    window.addEventListener('beforeunload', (ev) => {
        if (K.dirty) { ev.preventDefault(); ev.returnValue = ''; }
    });
})();
