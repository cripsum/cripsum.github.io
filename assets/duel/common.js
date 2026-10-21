/**
 * Duelli 2.0 — i pezzi comuni delle pagine della fase 5 (lobby, Squadra,
 * fine partita): rete, testi, carte, emblemi. Niente librerie.
 *
 *   const D = window.D2P;   D.api('hub'), D.card(c), D.T('ciao', 'hi')...
 *
 * La configurazione arriva nella pagina (<script id="d2p-config">), i dati
 * della pagina in <script id="d2p-data">.
 */
(function () {
    'use strict';

    const CFG = JSON.parse(document.getElementById('d2p-config').textContent);
    const dataEl = document.getElementById('d2p-data');
    const DATA = dataEl ? JSON.parse(dataEl.textContent) : null;
    const EN = CFG.lang === 'en';
    const T = (it, en) => (EN ? en : it);
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // Migliaia sempre raggruppate come nelle tavole (1.506): in italiano il browser scrive 1506.
    const fmt = (n) => {
        const x = Number(n) || 0;
        const r = Math.round(x * 10) / 10;
        const int = String(Math.trunc(Math.abs(r))).replace(/\B(?=(\d{3})+(?!\d))/g, EN ? ',' : '.');
        const dec = Math.abs(r) % 1 ? (EN ? '.' : ',') + String(Math.round((Math.abs(r) % 1) * 10)) : '';
        return (r < 0 ? '−' : '') + int + dec;
    };
    const ic = (name, cls) => (window.D2Icons ? window.D2Icons.svg(name, cls) : '');
    const ROLE_ICON = { Tank: 'tank', Bruiser: 'bruiser', DPS: 'dps', 'Burst DPS': 'burst', 'Sub DPS': 'subdps', Support: 'support', Healer: 'healer', Controller: 'controller', Debuffer: 'debuffer', Buffer: 'buffer' };
    const ROLE_NAME = EN ? {} : { Tank: 'Tank', Bruiser: 'Bruiser', DPS: 'DPS', 'Burst DPS': 'Burst', 'Sub DPS': 'Sub DPS', Support: 'Support', Healer: 'Healer', Controller: 'Controller', Debuffer: 'Debuffer', Buffer: 'Buffer' };
    const MODE = { casual: T('Veloce', 'Quick'), ranked: 'Ranked', private: T('Stanza', 'Room'), bot: T('Contro il bot', 'Vs bot') };
    const TIER = { bronzo: T('Bronzo', 'Bronze'), argento: T('Argento', 'Silver'), oro: T('Oro', 'Gold'), diamante: T('Diamante', 'Diamond'), campione: T('Campione', 'Champion'), leggenda: T('Leggenda', 'Legend') };

    async function api(name, params, body) {
        const qs = params ? '?' + new URLSearchParams(params).toString() : '';
        const opts = { headers: { 'X-Cripsum-Lang': CFG.lang, Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' };
        if (body) {
            opts.method = 'POST';
            opts.headers['Content-Type'] = 'application/json';
            opts.headers['X-CSRF-Token'] = CFG.csrf;
            opts.body = JSON.stringify(body);
        }
        let res;
        try {
            res = await fetch(CFG.api + name + '.php' + qs, opts);
        } catch (e) {
            return { ok: false, code: 'NET', error: T('Connessione assente. Riprova.', 'No connection. Try again.') };
        }
        try {
            const data = await res.json();
            data.http = res.status;
            return data;
        } catch (e) {
            return { ok: false, code: 'SERVER', http: res.status, error: T('Risposta non valida dal server.', 'Invalid server response.') };
        }
    }

    function toast(text, err) {
        const el = document.createElement('div');
        el.className = 'd2p-toast' + (err ? ' d2p-err' : '');
        el.setAttribute('role', err ? 'alert' : 'status');
        el.textContent = text;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3600);
    }

    /** "DANTE (DEVIL TRIGGER)" -> "Dante (Devil Trigger)". */
    function niceName(name) {
        return String(name || '').toLowerCase().replace(/(^|[\s(\-./])(\p{L})/gu, (m, a, b) => a + b.toUpperCase());
    }
    const rarColor = (r) => (CFG.rarities[r] && CFG.rarities[r].c) || '#9ca3af';
    const rarName = (r) => (CFG.rarities[r] && CFG.rarities[r].n) || r;
    const roleName = (r) => ROLE_NAME[r] || r;

    function emblem(key, cls) {
        return '<span class="d2p-emb' + (cls ? ' ' + cls : '') + '" style="--tc: var(--t-' + esc(key) + ')" aria-hidden="true">' + (window.D2Icons ? window.D2Icons.emblem(key) : '') + '</span>';
    }

    /**
     * Una carta della collezione o della squadra.
     * opts: { lv: true (livello in alto), cost: true, sub: 'testo sotto', cls: 'classe', tag: 'button'|'div', attrs: '' }
     */
    function card(c, opts) {
        const o = Object.assign({ lv: true, cost: true, sub: '', cls: '', tag: 'div', attrs: '' }, opts || {});
        const name = niceName(c.name);
        const lv = c.level >= CFG.level_max ? 'MAX' : 'Lv ' + c.level;
        return '<' + o.tag + ' class="d2p-dc ' + o.cls + '" style="--r:' + rarColor(c.rarity) + '" data-id="' + c.id + '" ' + o.attrs + '>'
            + '<span class="d2p-art">' + (c.img ? '<img src="' + esc(c.img) + '" alt="" loading="lazy" onerror="this.remove()">' : '')
            + '<span class="d2p-ini" aria-hidden="true">' + esc(name.charAt(0)) + '</span>'
            + (o.lv ? '<span class="d2p-lv">' + lv + '</span>' : '')
            + '<span class="d2p-role" title="' + esc(roleName(c.role)) + '">' + ic(ROLE_ICON[c.role] || 'dps') + '</span>'
            + (o.cost ? '<span class="d2p-cost" title="' + esc(T('Costo', 'Cost')) + '">' + c.cost + '</span>' : '')
            + '</span><span class="d2p-dn">' + esc(name) + '</span>' + (o.sub ? '<span class="d2p-sub">' + esc(o.sub) + '</span>' : '')
            + '</' + o.tag + '>';
    }

    /** Il contatore del costo: pezzi colorati con la rarita' di chi li occupa. */
    function pips(cards, budget) {
        let out = '';
        let used = 0;
        cards.forEach((c) => {
            for (let i = 0; i < c.cost; i++) {
                out += '<i class="' + (used >= budget ? 'd2p-over' : 'd2p-on') + '" style="--r:' + rarColor(c.rarity) + '"></i>';
                used++;
            }
        });
        for (; used < budget; used++) out += '<i></i>';
        return '<span class="d2p-pips" style="--n:' + Math.max(budget, cards.reduce((a, c) => a + c.cost, 0)) + '" aria-hidden="true">' + out + '</span>';
    }

    function ago(iso) {
        const t = Date.parse(String(iso).replace(' ', 'T'));
        if (!t) return '';
        const s = Math.max(0, (Date.now() - t) / 1000);
        if (s < 60) return T('adesso', 'just now');
        if (s < 3600) return Math.floor(s / 60) + T(' min fa', ' min ago');
        const h = Math.floor(s / 3600);
        if (s < 86400) return h === 1 ? T('un\'ora fa', '1 h ago') : h + T(' ore fa', ' h ago');
        const d = Math.floor(s / 86400);
        return d === 1 ? T('ieri', 'yesterday') : d + T(' giorni fa', ' days ago');
    }

    function hydrate(root) {
        if (window.D2Icons) window.D2Icons.hydrate(root || document);
    }

    window.D2P = { CFG, DATA, EN, T, $, esc, fmt, ic, api, toast, niceName, rarColor, rarName, roleName, emblem, card, pips, ago, hydrate, ROLE_ICON, MODE, TIER };
})();
