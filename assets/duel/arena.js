/**
 * Duelli 2.0 — l'arena (tavole 3, 4 e 9 delle bozze approvate).
 *
 * Il server decide tutto (includes/duel/): qui si disegna, si anima e si
 * mandano le mosse. Niente librerie.
 *
 * - Tempo reale: ogni secondo (piu' piano se tocca a te o la scheda e'
 *   nascosta) api/duel/rt.php legge il timbro della partita senza database;
 *   quando la versione cambia si chiedono a state.php solo gli eventi nuovi.
 * - Animazioni: una coda guidata dagli eventi del motore (colpi, critici,
 *   stati, KO, ultimate...), mai dal testo del registro. Gli eventi portano
 *   la vita dopo ogni colpo; alla fine di ogni gruppo si applica lo stato
 *   vero del server, quindi quello che resta a schermo e' sempre giusto.
 * - Le carte sono elementi che si aggiornano, non si ridisegna la pagina.
 * - Tasti: 1-4 mosse, Tab o frecce cambiano bersaglio, Invio conferma, Esc
 *   annulla. «Riduci movimento» del sistema lascia solo dissolvenze;
 *   «Animazioni veloci» dimezza tutto.
 * - Replay (CFG.replay, /it/duelli/replay?m=): la partita finita rigiocata
 *   da api/duel/replay.php con le stesse animazioni, con la barra dei turni
 *   (KO e ultimate segnati, avanti e indietro, 2×) al posto delle mosse.
 */
(function () {
    'use strict';

    const CFG = JSON.parse(document.getElementById('d2-config').textContent);
    const EN = CFG.lang === 'en';
    const T = (it, en) => (EN ? en : it);
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // Migliaia sempre raggruppate come nelle tavole (1.716): in italiano il browser scrive 1716.
    const fmt = (n) => {
        const x = Number(n) || 0;
        const r = Math.round(x * 10) / 10;
        const int = String(Math.trunc(Math.abs(r))).replace(/\B(?=(\d{3})+(?!\d))/g, EN ? ',' : '.');
        const dec = Math.abs(r) % 1 ? (EN ? '.' : ',') + String(Math.round((Math.abs(r) % 1) * 10)) : '';
        return (r < 0 ? '−' : '') + int + dec;
    };
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    const ic = (name, cls) => (window.D2Icons ? window.D2Icons.svg(name, cls) : '');
    const reducedMq = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
    const phoneMq = window.matchMedia ? window.matchMedia('(max-width: 700px)') : { matches: false };

    const ROLE_ICON = { Tank: 'tank', Bruiser: 'bruiser', DPS: 'dps', 'Burst DPS': 'burst', 'Sub DPS': 'subdps', Support: 'support', Healer: 'healer', Controller: 'controller', Debuffer: 'debuffer', Buffer: 'buffer' };
    const MOVE_KIT = { attacco: 'attack', abilita: 'skill', ultimate: 'ultimate' };
    const MOVE_ICON = { attacco: 'attacco', abilita: 'abilita', ultimate: 'ultimate', guardia: 'guardia', passa: 'passa' };
    const MODE = { casual: T('Veloce', 'Quick'), ranked: 'Ranked', private: T('Stanza', 'Room'), bot: T('Contro il bot', 'Vs bot') };
    const VISUAL = ['congelamento', 'sonno', 'furtivita', 'barriera', 'bruciatura', 'provocazione', 'elettrizzato', 'paura', 'stordimento'];

    // ── Impostazioni (solo comodita' di chi guarda: in questo browser) ─────
    const SET = Object.assign({ fast: false, volume: 70, music: true, sfx: true, vibrate: true }, readStore('d2.settings') || {});
    function readStore(key) {
        try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { return null; }
    }
    function saveSettings() {
        try { localStorage.setItem('d2.settings', JSON.stringify(SET)); } catch (e) { /* niente */ }
        document.body.classList.toggle('d2-fast', SET.fast);
    }
    document.body.classList.toggle('d2-fast', SET.fast);
    let replayFast = false; // il 2× del replay, solo per questa pagina
    const spd = () => (reducedMq.matches ? 0.25 : (SET.fast || replayFast) ? 0.5 : 1);
    const wait = (ms) => sleep(ms * spd());

    // ── Rete ─────────────────────────────────────────────────────────────
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
            return { ok: false, code: 'NET', error: T('Connessione assente. Riprovo…', 'No connection. Retrying…') };
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
        el.className = 'd2-toast' + (err ? ' d2-err' : '');
        el.textContent = text;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3300);
    }
    function say(text) {
        const el = $('d2-say');
        el.textContent = '';
        setTimeout(() => { el.textContent = text; }, 30);
    }

    // ── Suoni: brevi, sintetizzati (niente file da scaricare) ─────────────
    const SND = {
        ctx: null,
        unlocked: false,
        unlock() {
            if (this.unlocked) return;
            try {
                const C = window.AudioContext || window.webkitAudioContext;
                this.ctx = C ? new C() : null;
                this.unlocked = true;
            } catch (e) { this.ctx = null; }
        },
        tone(freq, dur, type, gain, slide, delay) {
            const ctx = this.ctx;
            if (!ctx || !SET.sfx || SET.volume <= 0) return;
            const t0 = ctx.currentTime + (delay || 0);
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = type || 'sine';
            o.frequency.setValueAtTime(freq, t0);
            if (slide) o.frequency.exponentialRampToValueAtTime(Math.max(20, slide), t0 + dur);
            g.gain.setValueAtTime(0.0001, t0);
            g.gain.exponentialRampToValueAtTime(Math.max(0.0002, (gain || 0.2) * SET.volume / 100), t0 + 0.01);
            g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
            o.connect(g).connect(ctx.destination);
            o.start(t0);
            o.stop(t0 + dur + 0.02);
        },
        noise(dur, gain, freq) {
            const ctx = this.ctx;
            if (!ctx || !SET.sfx || SET.volume <= 0) return;
            const len = Math.floor(ctx.sampleRate * dur);
            const buf = ctx.createBuffer(1, len, ctx.sampleRate);
            const d = buf.getChannelData(0);
            for (let i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * (1 - i / len);
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            f.type = 'lowpass';
            f.frequency.value = freq || 1800;
            g.gain.value = (gain || 0.25) * SET.volume / 100;
            src.buffer = buf;
            src.connect(f).connect(g).connect(ctx.destination);
            src.start();
        },
        play(name) {
            if (!this.ctx) return;
            switch (name) {
                case 'hit': this.noise(0.12, 0.35, 1400); this.tone(110, 0.12, 'sine', 0.3, 60); break;
                case 'crit': this.noise(0.18, 0.5, 4000); this.tone(1400, 0.05, 'square', 0.12); this.tone(90, 0.25, 'sine', 0.45, 45); break;
                case 'heal': this.tone(520, 0.18, 'sine', 0.16, 880); this.tone(780, 0.2, 'sine', 0.1, 1170, 0.06); break;
                case 'shield': this.tone(980, 0.16, 'triangle', 0.12, 760); break;
                case 'break': this.noise(0.25, 0.35, 5000); this.tone(1200, 0.2, 'square', 0.08, 300); break;
                case 'ko': this.tone(220, 0.5, 'sawtooth', 0.18, 55); this.noise(0.35, 0.3, 700); break;
                case 'status': this.tone(660, 0.07, 'triangle', 0.1); break;
                case 'turn': this.tone(880, 0.12, 'sine', 0.16); this.tone(1320, 0.18, 'sine', 0.14, null, 0.1); break;
                case 'ps': this.tone(1500, 0.04, 'square', 0.05); break;
                case 'win': [523, 659, 784, 1046].forEach((f, i) => this.tone(f, 0.25, 'triangle', 0.18, null, i * 0.11)); break;
                case 'lose': [440, 370, 311, 247].forEach((f, i) => this.tone(f, 0.3, 'sine', 0.16, null, i * 0.13)); break;
                case 'ult': this.noise(0.4, 0.25, 900); this.tone(160, 0.6, 'sawtooth', 0.12, 320); break;
                default: break;
            }
        },
    };
    let music = null;
    /**
     * La musica dell'ultimate: quella sua se c'e' (audio/ultimates/<id>.mp3,
     * la pagina passa l'elenco), se no la musica del suo pull nella lootbox,
     * se no quella generica. Se un file non si apre si passa al successivo.
     */
    function playMusic(pid, pullAudio, themeMusic) {
        if (!SET.music || SET.volume <= 0) return;
        stopMusic(0);
        const chain = [];
        if (themeMusic) chain.push(themeMusic);
        if ((CFG.tracks || []).includes(Number(pid))) chain.push(CFG.music + pid + '.mp3');
        if (pullAudio) chain.push(pullAudio);
        chain.push(CFG.music + 'default.mp3');
        const a = new Audio(chain.shift());
        a.volume = Math.min(1, 0.85 * SET.volume / 100);
        a.onerror = () => {
            if (!chain.length) return;
            a.src = chain.shift();
            a.play().catch(() => {});
        };
        a.play().catch(() => {});
        music = a;
    }
    function stopMusic(ms) {
        const a = music;
        music = null;
        if (!a) return;
        if (!ms) { a.pause(); return; }
        const v0 = a.volume;
        const t0 = performance.now();
        const step = () => {
            const k = Math.min(1, (performance.now() - t0) / ms);
            a.volume = Math.max(0, v0 * (1 - k));
            if (k < 1) requestAnimationFrame(step); else a.pause();
        };
        requestAnimationFrame(step);
    }
    function buzz(ms) {
        // Il browser la permette solo dopo un tocco della persona.
        const active = !navigator.userActivation || navigator.userActivation.hasBeenActive;
        if (SET.vibrate && active && navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) { /* niente */ } }
    }
    ['pointerdown', 'keydown'].forEach((t) => window.addEventListener(t, () => SND.unlock(), { once: true, passive: true }));

    // ── Stato ────────────────────────────────────────────────────────────
    const S = {
        match: CFG.match,
        st: null,          // parte fissa (carte, kit, giocatori, regole)
        dyn: null,         // quello che c'e' a schermo
        latest: null,      // l'ultimo stato arrivato dal server
        v: -1,
        side: null,        // 0/1 se giochi, null se guardi
        status: '',
        chat: -1,
        react: -1,
        unread: 0,
        sel: { move: null, target: 0 },
        busy: false,
        queue: [],
        animating: false,
        tlKeys: [],
        offset: 0,
        ended: false,
        kitUnit: 0,
        pollTimer: 0,
        lastTurnSaid: -1,
        live: {},          // vita, scudo, carica durante le animazioni
    };
    const now = () => Date.now() / 1000 + S.offset;
    const mySide = () => (S.side === null ? 0 : S.side);
    const unitSt = (id) => S.st.units[id] || S.st.units[String(id)];
    const unitDyn = (id, dyn) => (dyn || S.dyn).units[id] || (dyn || S.dyn).units[String(id)];
    const unitIds = () => Object.keys(S.st.units).map(Number).sort((a, b) => a - b);
    const myTurn = () => S.latest && S.side !== null && !S.latest.over && S.status === 'active' && S.latest.actor_side === S.side && S.latest.legal;

    /** «Tocca a» o «Tocca ad» (d eufonica davanti alla a: «Tocca ad Anna»). */
    function toA(name) {
        return /^a/i.test(String(name || '')) ? 'Tocca ad ' : 'Tocca a ';
    }
    function niceName(name) {
        return String(name || '').toLowerCase().replace(/(^|[\s(\-./])(\p{L})/gu, (m, a, b) => a + b.toUpperCase());
    }
    function uName(id) {
        const u = unitSt(id);
        return u ? niceName(u.name) : '?';
    }
    function rarColor(r) {
        return (CFG.rarities[r] && CFG.rarities[r].c) || '#9ca3af';
    }
    function rarName(r) {
        return (CFG.rarities[r] && CFG.rarities[r].n) || r;
    }
    function avatar(id, cls) {
        const u = unitSt(id);
        if (u && u.img) return '<span class="d2-av' + (cls ? ' ' + cls : '') + '"><img src="' + esc(u.img) + '" alt="" loading="lazy" onerror="this.remove()"></span>';
        return '<span class="d2-av' + (cls ? ' ' + cls : '') + '">' + esc(uName(id).charAt(0)) + '</span>';
    }
    function moveName(id, move) {
        if (move === 'guardia') return T('Guardia', 'Guard');
        if (move === 'passa') return T('Passa', 'Pass');
        const u = unitSt(id);
        const k = u && u.kit[MOVE_KIT[move]];
        return k && k.name ? k.name : move;
    }

    // ── Avvio ────────────────────────────────────────────────────────────
    function boot() {
        if (window.D2Icons) window.D2Icons.hydrate(document);
        if (!S.match) {
            location.replace(CFG.lobby);
            return;
        }
        bindUi();
        if (CFG.replay) replayBoot(); else load(true);
        if (CFG.queued && !CFG.replay) queueWhileBot(CFG.queued);
    }

    /**
     * Col bot mentre si aspetta in coda (fase 6): la pillola in alto dice da
     * quanto si aspetta; il segnale continua ogni 4 secondi e quando arriva
     * un avversario si va da lui (la partita col bot si annulla, non conta).
     */
    function queueWhileBot(q) {
        const started = Date.now() - (q.waited || 0) * 1000;
        const pill = document.createElement('button');
        pill.type = 'button';
        pill.className = 'd2-pill d2-queue-pill';
        pill.title = T('Esci dalla coda', 'Leave the queue');
        $('d2-spect').after(pill);
        let stopped = false;
        const draw = () => {
            const s = Math.floor((Date.now() - started) / 1000);
            pill.innerHTML = '<span class="d2-qdot"></span>' + esc(T('In coda ', 'In queue ') + (MODE[q.mode] || q.mode)) + ' · ' + Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') + ' ' + ic('x');
        };
        draw();
        const clockT = setInterval(draw, 1000);
        const stop = () => { stopped = true; clearInterval(clockT); pill.remove(); };
        pill.addEventListener('click', async () => { stop(); await api('queue', null, { action: 'leave' }); toast(T('Sei uscito dalla coda.', 'You left the queue.')); });
        const ping = async () => {
            if (stopped) return;
            const r = await api('queue', null, { action: 'ping' });
            if (stopped) return;
            if (r.ok && r.status === 'matched' && r.match && r.match !== S.match) {
                stop();
                toast(T('Avversario trovato!', 'Opponent found!'));
                setTimeout(() => { location.href = CFG.self + '?m=' + r.match; }, 700);
                return;
            }
            if (r.ok && r.status === 'idle') { stop(); return; }
            setTimeout(ping, 4000);
        };
        setTimeout(ping, 1500);
    }

    async function load(first) {
        const r = await api('state', { m: S.match, since: -1, c: -1, r: -1 });
        $('d2-loading').hidden = true;
        if (!r.ok) {
            if (r.code === 'NET' || r.code === 'SERVER') {
                setTimeout(() => load(first), 3000);
                return;
            }
            fatal(r.error || T('Partita non disponibile.', 'Match not available.'));
            return;
        }
        handle(r, first);
        schedule(300);
    }

    function fatal(text) {
        const el = $('d2-end');
        el.className = 'd2-end';
        el.innerHTML = '<h2 id="d2-end-t" style="font-size:32px">' + esc(text) + '</h2><div class="d2-acts"><a class="d2-btn d2-btn-w" href="' + esc(CFG.lobby) + '">'
            + esc(T('Torna ai duelli', 'Back to duels')) + '</a></div>';
        el.hidden = false;
    }

    /** Una risposta di state.php (o di act.php, che risponde allo stesso modo). */
    function handle(r, first) {
        S.offset = (r.now || Date.now() / 1000) - Date.now() / 1000;
        S.status = r.status;
        S.side = r.side === undefined ? S.side : r.side;
        if (r.status === 'waiting') {
            showWaiting(r);
            return;
        }
        $('d2-wait').hidden = true;
        if (r.status === 'cancelled') {
            stopPolling();
            if (r.reason === 'queue' && r.next) {
                toast(T('Avversario trovato!', 'Opponent found!'));
                location.href = CFG.self + '?m=' + r.next;
                return;
            }
            fatal({ expired: T('La stanza è scaduta.', 'The room expired.'), declined: T('L\'amico ha rifiutato la sfida.', 'Your friend declined the challenge.'),
                cancelled: T('Sfida ritirata.', 'Challenge withdrawn.'), admin: T('Partita annullata dallo staff.', 'Match cancelled by the staff.') }[r.reason] || T('Partita annullata.', 'Match cancelled.'));
            return;
        }
        if (r.chat) addChat(r.chat);
        if (r.reactions) addReactions(r.reactions, first);
        if (r.spectators !== undefined) spectators(r.spectators);

        if (r.full || !S.st) {
            S.st = r.static;
            S.queue = [];
            build();
            S.latest = r.dyn;
            S.v = r.dyn.v;
            applyDyn(r.dyn);
            $('d2-app').hidden = false;
            // La presentazione una volta per partita, all'inizio (col bot che apre
            // il turno puo' gia' aver giocato qualche mossa).
            if (first && r.dyn.turn <= 6 && !r.dyn.over && !introSeen()) {
                intro();
            } else {
                settle();
            }
            return;
        }
        if (r.dyn) {
            if (r.dyn.v < S.v) return; // vecchia
            if (r.events && r.events.length) {
                S.queue.push({ events: r.events, dyn: r.dyn });
            } else if (r.dyn.v !== (S.dyn && S.dyn.v) || !S.animating) {
                S.queue.push({ events: [], dyn: r.dyn });
            }
            S.latest = r.dyn;
            S.v = r.dyn.v;
            renderDock();
            runQueue();
        }
    }

    function spectators(n) {
        const pill = $('d2-spect');
        pill.hidden = !(n > 0);
        pill.querySelector('span').textContent = n + ' ' + (n === 1 ? T('spettatore', 'spectator') : T('spettatori', 'spectators'));
    }

    // ── Tempo reale ──────────────────────────────────────────────────────
    function schedule(ms) {
        clearTimeout(S.pollTimer);
        S.pollTimer = setTimeout(poll, ms);
    }
    function stopPolling() {
        clearTimeout(S.pollTimer);
        S.pollTimer = 0;
        S.stopped = true;
    }
    async function poll() {
        if (S.stopped) return;
        let need = false;
        let ms = document.hidden ? 5000 : myTurn() ? 2500 : 1000;
        if (S.status === 'waiting') ms = 2000;
        const res = await fetch(CFG.api + 'rt.php?m=' + S.match, { credentials: 'same-origin', cache: 'no-store' }).then((x) => x.json()).catch(() => null);
        if (!res || !res.ok || res.off || res.v < 0) {
            need = true;
            ms = Math.max(ms, 3000);
        } else {
            S.offset = res.now - Date.now() / 1000;
            if (res.v !== S.v || (res.st && res.st !== S.status && S.status !== 'waiting')) need = true;
            if (res.dl && res.now > res.dl + 2 && S.status === 'active') need = true;
            if (res.c > S.chat && S.side !== null) need = true;
            if (res.r > S.react) need = true;
            if (S.status === 'waiting' && res.st && res.st !== 'waiting') need = true;
            if (res.s !== undefined && S.st && S.st.mode !== 'bot') spectators(res.s);
        }
        if (need) await refresh();
        if (S.status === 'finished' || S.status === 'cancelled') {
            if (!S.queue.length && !S.animating) return; // la fine la mostra settle()
        }
        schedule(ms);
    }
    async function refresh() {
        const r = await api('state', { m: S.match, since: S.st ? S.v : -1, c: S.chat, r: S.react });
        if (r.ok) handle(r, false);
    }

    // ── Costruzione della pagina ─────────────────────────────────────────
    function build() {
        const st = S.st;
        $('d2-mode').textContent = modeLabel();
        const top = 1 - mySide();
        const bottom = mySide();
        $('d2-row-foe').innerHTML = '';
        $('d2-row-you').innerHTML = '';
        $('d2-log').innerHTML = '<li class="d2-le d2-sys">' + esc(T('Qui compaiono le mosse, le più recenti in alto.', 'Moves show up here, newest first.')) + '</li>';
        unitIds().forEach((id) => {
            const u = unitSt(id);
            const el = cardEl(id);
            (u.side === top ? $('d2-row-foe') : $('d2-row-you')).appendChild(el);
        });
        $('d2-head-foe').innerHTML = sideHead(top, true);
        $('d2-head-you').innerHTML = sideHead(bottom, false);
        if (window.D2Icons) window.D2Icons.hydrate($('d2-app'));
        // Chat solo tra i giocatori; chi guarda ha le reazioni.
        if (S.side === null) {
            $('d2-chat-f').hidden = true;
            $('d2-tab-chat-l').textContent = T('Reazioni', 'Reactions');
            const bar = $('d2-react-bar');
            bar.hidden = false;
            bar.innerHTML = ['🔥', '💀', '👏', '😳', '⚡', '👀'].map((e) => '<button type="button" data-r="' + e + '" aria-label="' + e + '">' + e + '</button>').join('');
        } else if (st.mode === 'bot') {
            $('d2-tab-chat').hidden = true;
        }
        S.kitUnit = 0;
    }

    /** Il modo della partita a parole: in differita, una sfida a un amico o una stanza col codice. */
    function modeLabel() {
        const st = S.st || {};
        if (st.rules && st.rules.async) return T('In differita', 'Turn-based');
        return MODE[st.mode] || st.mode || '';
    }
    function sideHead(side, isTop) {
        const p = S.st.players[side] || S.st.players[String(side)] || {};
        const rank = p.rank ? '<span class="d2-pill">' + esc(p.rank.label) + ' · ' + fmt(p.rating) + '</span>' : (p.bot ? '<span class="d2-pill">' + esc(botLevel(p.level)) + '</span>' : '');
        const ps = isTop ? '<span class="d2-ps" data-ps="' + side + '"></span>' : '';
        // Fase 6: l'emblema dell'ultima stagione finita (si vede solo nei duelli).
        const badge = p.badge && window.D2Icons ? '<span class="d2-badge-s" style="--tc: var(--t-' + esc(p.badge.tier) + ')" title="' + esc(T('Stagione ', 'Season ') + p.badge.n) + '">'
            + window.D2Icons.emblem(p.badge.tier) + '<small>S' + p.badge.n + '</small></span>' : '';
        return '<span class="d2-sw"></span>' + esc(p.username || '?') + ' ' + badge + rank + ' ' + ps;
    }
    function botLevel(l) {
        return { facile: T('Facile', 'Easy'), normale: T('Normale', 'Normal'), difficile: T('Difficile', 'Hard'), incubo: T('Incubo', 'Nightmare') }[l] || l || '';
    }

    function cardEl(id) {
        const u = unitSt(id);
        const color = rarColor(u.rarity);
        const theme = (u.theme && u.theme.color) || color;
        const el = document.createElement('div');
        el.className = 'd2-card';
        el.dataset.u = id;
        el.style.setProperty('--r', color);
        el.style.setProperty('--theme', theme);
        el.innerHTML = '<div class="d2-art">'
            + (u.img ? '<img src="' + esc(u.img) + '" alt="" onerror="this.remove()">' : '')
            + '<span class="d2-ini">' + esc(uName(id).charAt(0)) + '</span>'
            + '<span class="d2-lv">' + (u.level >= 6 ? 'MAX' : 'Lv ' + u.level) + '</span>'
            + '<span class="d2-role" title="' + esc(u.role) + '">' + ic(ROLE_ICON[u.role] || 'dps') + '</span>'
            + '<div class="d2-vis"></div></div>'
            + '<div class="d2-name"><span>' + esc(uName(id)) + '</span><small>' + esc(rarName(u.rarity)) + '</small></div>'
            + '<div class="d2-hp"><u></u><i></i><b></b><s></s></div>'
            + '<div class="d2-hpn num"><span><b>0</b> / ' + fmt(u.max.hp) + '</span><span class="d2-pv"></span></div>'
            + '<div class="d2-ug"><i></i></div>'
            + '<div class="d2-fx"></div>';
        if (u.img) el.querySelector('.d2-ini').style.display = 'none';
        el.addEventListener('click', () => onCard(id));
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onCard(id); }
        });
        return el;
    }
    const cardOf = (id) => document.querySelector('.d2-card[data-u="' + id + '"]');

    // ── Disegno dallo stato ──────────────────────────────────────────────
    function applyDyn(dyn) {
        S.dyn = dyn;
        S.live = {};
        unitIds().forEach((id) => {
            const d = unitDyn(id);
            S.live[id] = { hp: d.hp, sh: d.shield, ch: d.charge, ko: d.ko };
            renderCard(id);
        });
        renderTimeline();
        renderHeads();
        renderTerrain();
        renderDock();
        renderKit();
        $('d2-turn').textContent = T('Turno ', 'Turn ') + dyn.turn;
    }

    function hpClass(pct) {
        return pct <= 25 ? ' d2-low' : pct <= 50 ? ' d2-mid' : '';
    }

    function renderCard(id) {
        const el = cardOf(id);
        if (!el) return;
        const u = unitSt(id);
        const d = unitDyn(id);
        const live = S.live[id] || { hp: d.hp, sh: d.shield, ch: d.charge, ko: d.ko };
        setBars(id, live.hp, live.sh, false);
        el.querySelector('.d2-ug i').style.setProperty('--v', Math.min(100, live.ch / CFG.rules.charge_max * 100) + '%');
        el.classList.toggle('d2-ready', live.ch >= CFG.rules.charge_max && !d.ko);
        el.classList.toggle('d2-guard', !!d.guard && !d.ko);
        setKo(id, d.ko, false);
        // Stati: etichette e quello che si vede sulla carta.
        const fx = el.querySelector('.d2-fx');
        fx.innerHTML = chips(id, d);
        VISUAL.forEach((k) => el.classList.toggle('st-' + k, !d.ko && d.fx.some((f) => f.k === k)));
        el.classList.toggle('st-forma', !!d.form && !d.ko);
        el.querySelector('.d2-vis').innerHTML = visuals(id, d);
        // Turno e bersagli.
        const actor = S.dyn && S.dyn.actor;
        el.classList.toggle('d2-turn', actor === id && !S.dyn.over);
        tagCard(el, id);
        el.setAttribute('aria-label', uName(id) + ', ' + fmt(live.hp) + ' / ' + fmt(u.max.hp) + ' HP' + (d.ko ? ', KO' : ''));
    }

    function chips(id, d) {
        let html = '';
        if (d.shield > 0) html += '<span data-k="scudo" title="' + esc(T('Scudo', 'Shield')) + '">' + ic('scudo') + '<span class="d2-lbl">' + fmt(d.shield) + '</span></span>';
        const st = unitSt(id);
        if (st.stacks && d.stk > 0) html += '<span data-k="cariche">' + ic('cariche') + '<span class="d2-lbl">' + esc(st.stacks.name) + '</span> ' + d.stk + '</span>';
        d.fx.forEach((f, i) => {
            const def = CFG.statuses[f.k] || { n: f.k, neg: false };
            const lbl = chipLabel(f);
            const count = f.st > 1 ? f.st : f.t > 0 ? f.t : '';
            html += '<span data-k="' + esc(f.k) + '" data-i="' + i + '" class="' + (def.neg ? 'd2-neg' : '') + '" tabindex="0">' + ic(f.k)
                + (lbl ? '<span class="d2-lbl">' + esc(lbl) + '</span>' : '') + (count !== '' ? ' ' + count : '') + '</span>';
        });
        return html;
    }
    function chipLabel(f) {
        const v = Number(f.v) || 0;
        if (!v) return '';
        if (/_su$/.test(f.k)) return '+' + fmt(v) + (f.k === 'crit_su' ? '' : '%');
        if (/_giu$/.test(f.k)) return '−' + fmt(v) + (f.k === 'crit_giu' ? '' : '%');
        if (['veleno', 'rigenerazione', 'rubavita', 'riflesso', 'schivata', 'maledizione', 'bruciatura', 'corrosione'].includes(f.k)) return fmt(v) + '%';
        return '';
    }

    function visuals(id, d) {
        if (d.ko) return '';
        let html = '';
        const has = (k) => d.fx.some((f) => f.k === k);
        if (has('congelamento')) html += '<span class="d2-vis-ice"></span>';
        if (has('sonno')) html += '<span class="d2-vis-zzz"><i>Z</i><i>z</i></span>';
        if (has('barriera')) html += '<svg class="d2-vis-hex" viewBox="0 0 100 112" preserveAspectRatio="none"><polygon points="50,2 98,28 98,84 50,110 2,84 2,28"/></svg>';
        if (has('bruciatura')) html += '<span class="d2-vis-fire"></span>';
        if (has('elettrizzato')) html += '<span class="d2-vis-bolt l">' + ic('elettro') + '</span><span class="d2-vis-bolt r">' + ic('elettro') + '</span>';
        if (has('stordimento')) html += '<span class="d2-vis-stun">' + ic('stordimento') + '</span>';
        if (d.form) {
            const f = (unitSt(id).forms || {})[d.form.k] || {};
            html += '<span class="d2-vis-form">' + esc(T('Forma', 'Form')) + ' · ' + esc(f[CFG.lang] || d.form.k) + ' · ' + Math.max(0, d.form.t - 1) + '</span>';
        }
        const guard = d.fx.find((f) => f.k === 'guardiano');
        if (guard && guard.src) html += '<span class="d2-vis-link">' + ic('guardiano') + ' ' + esc(uName(guard.src)) + '</span>';
        return html;
    }

    function setBars(id, hp, sh, animate) {
        const el = cardOf(id);
        if (!el) return;
        const max = unitSt(id).max.hp;
        const pct = Math.max(0, Math.min(100, hp / max * 100));
        const bar = el.querySelector('.d2-hp');
        const i = bar.querySelector('i');
        const old = parseFloat(i.style.getPropertyValue('--v')) || pct;
        if (animate && pct < old) {
            // Il pezzo di vita perso resta bianco un attimo, poi scende.
            const g = bar.querySelector('u');
            g.style.transition = 'none';
            g.style.setProperty('--g', old + '%');
            void g.offsetWidth;
            g.style.transition = '';
            requestAnimationFrame(() => g.style.setProperty('--g', pct + '%'));
        } else {
            bar.querySelector('u').style.setProperty('--g', pct + '%');
        }
        i.style.setProperty('--v', pct + '%');
        i.className = hpClass(pct).trim();
        const b = bar.querySelector('b');
        b.style.setProperty('--at', pct + '%');
        b.style.setProperty('--sv', Math.min(100 - pct, sh / max * 100) + '%');
        el.querySelector('.d2-hpn b').textContent = fmt(hp);
        el.querySelector('.d2-hpn .d2-pv').textContent = sh > 0 ? '+' + fmt(sh) : '';
    }

    function setKo(id, ko, animate) {
        const el = cardOf(id);
        if (!el) return;
        const had = el.classList.contains('d2-ko');
        el.classList.toggle('d2-ko', ko);
        let stamp = el.querySelector('.d2-ko-stamp');
        if (ko && !stamp) {
            stamp = document.createElement('span');
            stamp.className = 'd2-ko-stamp';
            stamp.textContent = 'KO';
            el.querySelector('.d2-art').appendChild(stamp);
        } else if (!ko && stamp) {
            stamp.remove();
        }
        if (ko && animate && !had) {
            el.classList.add('d2-ko-anim');
            setTimeout(() => el.classList.remove('d2-ko-anim'), 1000 * spd());
        }
    }

    function renderHeads() {
        document.querySelectorAll('[data-ps]').forEach((el) => {
            el.innerHTML = psDiamonds(S.dyn.ps[Number(el.dataset.ps)]);
        });
        $('d2-ps').innerHTML = psDiamonds(S.dyn.ps[mySide()]);
        $('d2-ps').setAttribute('aria-label', T('Punti squadra: ', 'Team points: ') + S.dyn.ps[mySide()] + ' / ' + CFG.rules.ps_max);
    }
    function psDiamonds(n) {
        let html = '';
        for (let i = 0; i < CFG.rules.ps_max; i++) html += '<i class="' + (i < n ? 'f' : '') + '"></i>';
        return html;
    }

    function renderTerrain() {
        const el = $('d2-terrain');
        const t = S.dyn.terrain;
        if (!t) { el.hidden = true; return; }
        const def = CFG.terrains[t.k] || { n: t.k };
        el.dataset.k = t.k;
        el.innerHTML = ic(t.k) + ' ' + esc(def.n) + (t.left ? ' · ' + Math.ceil(t.left / Math.max(1, t.round)) + ' ' + T('giri', 'rounds') : '');
        el.title = def.d || '';
        el.hidden = false;
    }

    // Ordine dei turni: le icone scorrono al nuovo posto (FLIP).
    function renderTimeline() {
        const ol = $('d2-tl');
        const before = {};
        ol.querySelectorAll('li').forEach((li) => { before[li.dataset.key] = li.getBoundingClientRect(); });
        const seen = {};
        const prevIndex = {};
        S.tlKeys.forEach((k, i) => { prevIndex[k] = i; });
        const keys = [];
        ol.innerHTML = (S.dyn.timeline || []).map((id, i) => {
            seen[id] = (seen[id] || 0) + 1;
            const key = id + ':' + seen[id];
            keys.push(key);
            const u = unitSt(id);
            const foe = u.side !== mySide();
            const moved = prevIndex[key] !== undefined && i < prevIndex[key] - 1 ? ' d2-up' : '';
            return '<li data-key="' + key + '" class="' + (i === 0 ? 'd2-now' : '') + (foe ? ' d2-foe' : '') + moved + '">' + avatar(id)
                + '<small>' + (i === 0 ? esc(T('Ora', 'Now')) : esc(uName(id))) + '</small></li>';
        }).join('');
        S.tlKeys = keys;
        if (spd() >= 0.5) {
            ol.querySelectorAll('li').forEach((li) => {
                const was = before[li.dataset.key];
                if (!was) return;
                const now2 = li.getBoundingClientRect();
                const dx = was.left - now2.left;
                const dy = was.top - now2.top;
                if (!dx && !dy) return;
                li.animate([{ transform: 'translate(' + dx + 'px,' + dy + 'px)' }, { transform: 'none' }], { duration: 400 * spd(), easing: 'cubic-bezier(0.16,1,0.3,1)' });
            });
        }
    }

    // ── Mosse, bersagli, anteprima ───────────────────────────────────────
    function renderDock() {
        const box = $('d2-moves');
        const dyn = S.latest || S.dyn;
        if (!dyn || !S.st) return;
        if (S.side === null) {
            box.innerHTML = '<p class="d2-watch">' + ic('occhio') + ' ' + esc(T('Stai guardando la partita.', 'You are watching this match.')) + '</p>';
            $('d2-prev').innerHTML = '';
            return;
        }
        const legal = myTurn() ? dyn.legal : null;
        const actor = dyn.actor;
        const myUnits = unitIds().filter((id) => unitSt(id).side === S.side);
        const who = actor && unitSt(actor).side === S.side ? actor : (S.kitUnit && unitSt(S.kitUnit).side === S.side ? S.kitUnit : myUnits[0]);
        const st = unitSt(who);
        const d = unitDyn(who, dyn);
        const four = legal && legal.passa ? 'passa' : 'guardia';
        const list = ['attacco', 'abilita', 'ultimate', four];
        if (S.sel.move && (!legal || !legal[S.sel.move])) clearSel(true);
        box.innerHTML = list.map((m, i) => {
            const can = !!(legal && legal[m]) && !S.busy;
            const on = S.sel.move === m;
            let sub = '';
            let cost = '';
            let extra = '';
            if (m === 'attacco') { sub = st.kit.attack.name + ' · ' + (st.kit.attack.meta || ''); cost = '+1 PS'; }
            if (m === 'abilita') {
                sub = st.kit.skill.name;
                const c = legal && dyn.skill_cost !== undefined ? dyn.skill_cost : st.moves.abilita.cost;
                cost = c > 0 ? '−' + c + ' PS' : T('gratis', 'free');
                if (d.cd > 0) cost = T('ricarica ', 'cooldown ') + d.cd;
            }
            if (m === 'ultimate') {
                sub = st.kit.ultimate.name;
                const ch = d.charge;
                const ready = ch >= CFG.rules.charge_max;
                cost = ready ? T('Pronta', 'Ready') : fmt(ch) + ' / ' + CFG.rules.charge_max;
                extra = '<span class="d2-fill" style="--v:' + Math.min(100, ch) + '%"></span>' + (ready ? '<span class="d2-stamp">' + esc(T('PRONTA', 'READY')) + '</span>' : '');
            }
            if (m === 'guardia') { sub = T('−35% danni fino al tuo turno', '−35% damage until your turn'); cost = '+1 PS'; }
            if (m === 'passa') { sub = T('Non puoi fare altro', 'Nothing else to do'); cost = ''; }
            const ultCls = m === 'ultimate' && d.charge >= CFG.rules.charge_max ? ' d2-ult-ready' : '';
            return '<button type="button" class="d2-mv' + (on ? ' d2-on' : '') + ultCls + '" data-m="' + m + '" style="--rc:' + rarColor(st.rarity) + '"' + (can ? '' : ' disabled')
                + ' aria-pressed="' + on + '" aria-keyshortcuts="' + (i + 1) + '">' + extra + '<span class="d2-k">' + (i + 1) + '</span><span class="d2-h">' + ic(MOVE_ICON[m]) + ' '
                + esc(m === 'attacco' ? T('Attacco', 'Attack') : m === 'abilita' ? T('Abilità', 'Skill') : m === 'ultimate' ? 'Ultimate' : moveName(who, m)) + '</span><span class="d2-s">' + esc(sub) + '</span><span class="d2-c">' + esc(cost) + '</span></button>';
        }).join('');
        renderPreview();
        renderTargets();
    }

    function targetsFor(move) {
        const dyn = S.latest;
        if (!dyn || !dyn.legal || !dyn.legal[move]) return [];
        return dyn.legal[move].map(Number).filter((t) => t > 0);
    }

    function selectMove(move) {
        if (!myTurn() || S.busy) return;
        const legal = S.latest.legal;
        if (!legal[move]) return;
        if (S.sel.move === move) { confirm(); return; }
        S.sel.move = move;
        const ts = targetsFor(move);
        if (!ts.includes(S.sel.target)) {
            // Il nemico piu' ferito tra quelli possibili, o il primo.
            let best = ts[0] || 0;
            ts.forEach((t) => {
                const d = unitDyn(t, S.latest);
                const b = unitDyn(best, S.latest);
                if (d && b && unitSt(t).side !== S.side && d.hp / unitSt(t).max.hp < b.hp / unitSt(best).max.hp) best = t;
            });
            S.sel.target = best;
        }
        renderDock();
        caption();
        if (!ts.length || ts.length === 1) say(moveName(S.latest.actor, move) + '. ' + T('Invio per confermare.', 'Enter to confirm.'));
    }
    function clearSel(silent) {
        S.sel = { move: null, target: 0 };
        if (!silent) { renderDock(); caption(); }
    }
    function cycleTarget(dir) {
        const ts = targetsFor(S.sel.move);
        if (ts.length < 2) return;
        const i = ts.indexOf(S.sel.target);
        S.sel.target = ts[(i + dir + ts.length) % ts.length];
        renderDock();
        caption();
        say(uName(S.sel.target));
    }

    function renderTargets() {
        const ts = S.sel.move ? targetsFor(S.sel.move) : [];
        unitIds().forEach((id) => {
            const el = cardOf(id);
            if (!el) return;
            tagCard(el, id);
            const can = ts.includes(id);
            el.classList.toggle('d2-can', can);
            // Si spengono gli altri, non chi sta muovendo.
            el.classList.toggle('d2-dim', !!S.sel.move && ts.length > 0 && !can && !unitDyn(id).ko && id !== (S.latest && S.latest.actor));
            el.tabIndex = can ? 0 : -1;
            el.setAttribute('role', can ? 'button' : 'group');
            // Anteprima del danno sulla barra del bersaglio.
            const bar = el.querySelector('.d2-hp s');
            const p = S.sel.move && S.sel.target === id && S.latest.preview && S.latest.preview[S.sel.move] && S.latest.preview[S.sel.move][id];
            if (p && p.max > 0) {
                const max = unitSt(id).max.hp;
                const hp = unitDyn(id, S.latest).hp;
                const lose = Math.min(hp, p.max);
                bar.style.setProperty('--pa', Math.max(0, (hp - lose) / max * 100) + '%');
                bar.style.setProperty('--pv', lose / max * 100 + '%');
                el.querySelector('.d2-hpn .d2-pv').textContent = '−' + fmt(p.min) + '/' + fmt(p.max);
            } else {
                bar.style.setProperty('--pv', '0%');
            }
        });
    }

    function tagCard(el, id) {
        let tag = el.querySelector('.d2-tag');
        let text = '';
        let cls = '';
        if (S.sel.move && S.sel.target === id) {
            const ally = unitSt(id).side === S.side;
            text = ic('mirino') + ' ' + esc(ally ? T('Su di lui', 'Target') : T('Bersaglio', 'Target'));
            cls = ally ? 'd2-ally-target' : 'd2-target';
        } else if (S.dyn && S.dyn.actor === id && !S.dyn.over) {
            text = unitSt(id).side === S.side ? esc(T('Tocca a te', 'Your turn')) : esc(T('Di turno', 'Acting'));
        }
        el.classList.toggle('d2-target', cls === 'd2-target');
        el.classList.toggle('d2-ally-target', cls === 'd2-ally-target');
        if (!text) { if (tag) tag.remove(); return; }
        if (!tag) {
            tag = document.createElement('span');
            tag.className = 'd2-tag';
            el.prepend(tag);
        }
        tag.innerHTML = text;
    }

    function caption(text, sub, last) {
        const cap = $('d2-cap');
        if (text !== undefined) {
            cap.innerHTML = '<strong>' + text + '</strong>' + (sub ? '<span>' + sub + '</span>' : '') + (last ? '<br><span class="d2-last">' + last + '</span>' : '');
            cap.classList.remove('d2-flash');
            void cap.offsetWidth;
            cap.classList.add('d2-flash');
            return;
        }
        const dyn = S.latest || S.dyn;
        if (!dyn) return;
        if (dyn.over) { cap.innerHTML = ''; return; }
        if (S.sel.move) {
            const ts = targetsFor(S.sel.move);
            const name = moveName(dyn.actor, S.sel.move);
            const tg = S.sel.target ? ' → ' + esc(uName(S.sel.target)) : '';
            const hint = ts.length > 1
                ? T('Tocca un altro bersaglio per cambiarlo · tocca di nuovo la mossa o premi Invio per confermare', 'Tap another target to change it · tap the move again or press Enter to confirm')
                : T('Tocca di nuovo la mossa o premi Invio per confermare', 'Tap the move again or press Enter to confirm');
            cap.innerHTML = '<strong>' + esc(name) + tg + '</strong><span>' + esc(hint) + '</span>';
            return;
        }
        if (myTurn()) {
            cap.innerHTML = '<strong>' + esc(T(toA(uName(dyn.actor)), 'Your turn: ')) + esc(uName(dyn.actor)) + '</strong><span>' + esc(T('Scegli una mossa (1–4)', 'Pick a move (1–4)')) + '</span>' + (S.lastLine ? '<br><span class="d2-last">' + S.lastLine + '</span>' : '');
        } else if (dyn.actor && S.st.rules && S.st.rules.async && S.side !== null) {
            const who = S.st.players[unitSt(dyn.actor).side] || {};
            cap.innerHTML = '<strong>' + esc(T(toA(who.username || ''), 'Turn: ')) + esc(who.username || '') + '</strong><span>' + esc(T('In differita: puoi chiudere, quando tocca a te arriva nella campanella.', 'Turn-based: you can close this, the bell tells you when it\'s your turn.')) + '</span>'
                + (S.lastLine ? '<br><span class="d2-last">' + S.lastLine + '</span>' : '');
        } else if (dyn.actor) {
            const who = S.st.players[unitSt(dyn.actor).side] || {};
            cap.innerHTML = '<strong>' + esc(T('Turno di ', 'Turn: ')) + esc(uName(dyn.actor)) + '</strong><span>' + esc(S.side === null ? (who.username || '') : T('Aspetta la mossa di ', 'Waiting for ') + (who.username || '')) + '</span>' + (S.lastLine ? '<br><span class="d2-last">' + S.lastLine + '</span>' : '');
        }
    }

    function renderPreview() {
        const box = $('d2-prev');
        const dyn = S.latest;
        if (S.side === null || !dyn) { box.innerHTML = ''; return; }
        const m = S.sel.move;
        const t = S.sel.target;
        if (!m) {
            box.innerHTML = myTurn()
                ? '<small>' + esc(T('Tocca a te', 'Your turn')) + '</small><strong>' + esc(uName(dyn.actor)) + '</strong><p>' + esc(T('Scegli una mossa, poi il bersaglio.', 'Pick a move, then the target.')) + '</p>'
                : '<small>' + esc(T('Turno avversario', 'Opponent\'s turn')) + '</small><p>' + esc(T('Le mosse si accendono quando tocca a te.', 'Moves light up when it\'s your turn.')) + '</p>';
            box.innerHTML += '<span class="d2-m-only">' + (myTurn() ? esc(T('Tocca a te', 'Your turn')) : esc(T('Turno avversario', 'Opponent\'s turn'))) + '</span>';
            return;
        }
        const p = t && dyn.preview && dyn.preview[m] && dyn.preview[m][t];
        if (p && p.max > 0) {
            const hp = unitDyn(t, dyn).hp + unitDyn(t, dyn).shield;
            const left = Math.max(0, hp - Math.round((p.min + p.max) / 2));
            let line = T('Resta con circa ', 'About ') + fmt(left) + T(' HP.', ' HP left.');
            if (p.crit_chance > 0) line += ' ' + T('Se è critico', 'If it crits') + ' (' + p.crit_chance + '%): ' + fmt(p.crit_min) + ' – ' + fmt(p.crit_max) + '.';
            const ko = p.ko_chance >= 100 ? '<span class="d2-ok">' + esc(T('KO sicuro', 'Sure KO')) + '</span>' : p.ko_chance > 0 ? '<span class="d2-ok">' + esc(T('KO se è critico', 'KO on a crit')) + '</span>' : '';
            box.innerHTML = '<small>' + esc(T('Se colpisci ', 'If you hit ')) + esc(uName(t)) + '</small><strong class="num">' + fmt(p.min) + ' – ' + fmt(p.max) + ' ' + esc(T('danni', 'damage')) + '</strong><p>' + esc(line) + ' ' + ko + '</p>'
                + '<span class="d2-m-only num">' + fmt(p.min) + '–' + fmt(p.max) + ' ' + esc(T('su ', 'on ')) + esc(uName(t)) + (p.crit_chance ? ' <em>· ' + esc(T('critico ', 'crit ')) + p.crit_chance + '%</em>' : '') + '</span>';
        } else {
            const u = unitSt(dyn.actor);
            const k = u && u.kit[MOVE_KIT[m]];
            box.innerHTML = '<small>' + esc(moveName(dyn.actor, m)) + (t ? ' → ' + esc(uName(t)) : '') + '</small><p>' + esc(k ? k.text : m === 'guardia' ? T('Prendi il 35% di danni in meno fino al tuo prossimo turno e guadagni 1 PS.', 'Take 35% less damage until your next turn and gain 1 TP.') : '') + '</p>'
                + '<span class="d2-m-only">' + esc(moveName(dyn.actor, m)) + (t ? ' → ' + esc(uName(t)) : '') + '</span>';
        }
    }

    function onCard(id) {
        if (S.sel.move) {
            const ts = targetsFor(S.sel.move);
            if (ts.includes(id)) {
                if (S.sel.target === id) { confirm(); return; }
                S.sel.target = id;
                renderDock();
                caption();
                return;
            }
        }
        showKit(id);
    }

    async function confirm() {
        if (!myTurn() || S.busy || !S.sel.move) return;
        const move = S.sel.move;
        const ts = targetsFor(move);
        const target = ts.length ? S.sel.target : 0;
        S.busy = true;
        clearSel(true);
        renderDock();
        caption(esc(moveName(S.latest.actor, move)) + (target ? ' → ' + esc(uName(target)) : ''), esc(T('Invio…', 'Sending…')));
        const cid = Math.random().toString(36).slice(2, 12);
        let r = await api('act', null, { m: S.match, v: S.latest.v, move, target, cid });
        if (!r.ok && r.code === 'NET') {
            await sleep(1200);
            r = await api('act', null, { m: S.match, v: S.latest.v, move, target, cid });
        }
        S.busy = false;
        if (r.ok) {
            handle(r, false);
        } else {
            if (['STALE', 'TIMED_OUT', 'NOT_YOUR_TURN', 'MATCH_OVER'].includes(r.code)) {
                if (r.code === 'TIMED_OUT') toast(r.error, true);
                await refresh();
            } else {
                toast(r.error || T('Mossa non riuscita.', 'Move failed.'), true);
            }
            renderDock();
            caption();
        }
    }

    // ── La coda delle animazioni ─────────────────────────────────────────
    async function runQueue() {
        if (S.animating) return;
        S.animating = true;
        try {
            while (S.queue.length) {
                const batch = S.queue.shift();
                for (const step of batch.events) {
                    await playStep(step);
                }
                applyDyn(batch.dyn);
            }
        } catch (e) {
            if (window.console) console.error(e);
            if (S.latest) applyDyn(S.latest);
        }
        S.animating = false;
        settle();
    }

    /** Dopo le animazioni: chi tocca, il tempo, la fine. */
    function settle() {
        if (CFG.replay) return;
        renderDock();
        caption();
        if (S.latest && S.latest.over) {
            showEnd();
            return;
        }
        if (myTurn() && S.lastTurnSaid !== S.latest.v) {
            S.lastTurnSaid = S.latest.v;
            SND.play('turn');
            buzz(25);
            say(T('Tocca a te: ', 'Your turn: ') + uName(S.latest.actor));
            if (document.activeElement === document.body) $('d2-moves').focus({ preventScroll: true });
        }
        tickTimer();
    }

    function centerOf(id) {
        const el = cardOf(id);
        if (!el) return { x: innerWidth / 2, y: innerHeight / 2 };
        const r = el.querySelector('.d2-art').getBoundingClientRect();
        return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
    }
    function floatNum(id, text, cls, dx, dy, band) {
        const c = centerOf(id);
        const el = document.createElement('div');
        el.className = 'd2-num ' + (cls || '');
        el.textContent = text;
        if (band) el.dataset.band = band;
        el.style.left = c.x + (dx || 0) + 'px';
        el.style.top = c.y + (dy || 0) + 'px';
        $('d2-layer').appendChild(el);
        setTimeout(() => el.remove(), 1500 * spd() + 200);
    }
    function flashCard(id, cls, ms) {
        const el = cardOf(id);
        if (!el) return;
        el.classList.remove(cls);
        void el.offsetWidth;
        el.classList.add(cls);
        setTimeout(() => el.classList.remove(cls), (ms || 400) * spd());
    }
    function banner(text, warn) {
        const el = document.createElement('div');
        el.className = 'd2-banner' + (warn ? ' d2-warn' : '');
        el.textContent = text;
        $('d2-stage').appendChild(el);
        setTimeout(() => el.remove(), 1600 * spd());
    }
    function rays(id) {
        if (spd() < 0.5) return;
        const c = centerOf(id);
        const b = document.createElement('div');
        b.className = 'd2-burst';
        b.style.left = c.x + 'px';
        b.style.top = c.y + 'px';
        for (let i = 0; i < 8; i++) {
            const r = document.createElement('i');
            r.style.transform = 'rotate(' + (i * 45) + 'deg) translateY(56px)';
            b.appendChild(r);
        }
        $('d2-layer').appendChild(b);
        setTimeout(() => b.remove(), 500 * spd());
    }

    function liveSet(id, hp, sh, animate) {
        const l = S.live[id] || (S.live[id] = {});
        if (hp !== undefined) l.hp = hp;
        if (sh !== undefined) l.sh = sh;
        setBars(id, l.hp, l.sh || 0, animate);
    }

    /** I KO arrivano prima del colpo che li ha causati: si mettono dopo. */
    function reorder(ev) {
        const out = ev.slice();
        for (let i = 0; i < out.length; i++) {
            if (out[i].t !== 'ko') continue;
            for (let j = i + 1; j < Math.min(out.length, i + 4); j++) {
                const e = out[j];
                if ((e.dst === out[i].u || e.u === out[i].u) && ['hit', 'dot', 'reflect', 'chain', 'detonate', 'curse'].includes(e.t)) {
                    const ko = out.splice(i, 1)[0];
                    out.splice(j, 0, ko);
                    break;
                }
            }
        }
        return out;
    }

    async function playStep(step) {
        const ev = reorder(step.ev || []);
        const log = { step, actor: null, move: step.move, hits: {}, crit: {}, heals: {}, fx: [], resist: [], ko: [], extra: [] };
        let segment = log;
        const segments = [log];
        let hitsOnTarget = {};
        if (step.kind === 'timeout') {
            caption(esc(T('Tempo scaduto', 'Time is up')), esc(step.auto === 'forfeit' ? T('Terzo turno scaduto: partita persa', 'Third timeout: match lost') : T('Guardia automatica', 'Automatic Guard')));
            await wait(500);
        }
        if (step.kind === 'forfeit') {
            banner(T('Abbandono', 'Forfeit'), true);
            await wait(700);
        }
        for (const e of ev) {
            switch (e.t) {
                case 'turn': {
                    if (segment.actor !== null || segment.hitsCount) {
                        segment = { step: { kind: 'turn', n: e.n }, actor: e.u, move: null, hits: {}, crit: {}, heals: {}, fx: [], resist: [], ko: [], extra: [], turnStart: true };
                        segments.push(segment);
                    }
                    S.dyn.actor = e.u;
                    unitIds().forEach((id) => { const el = cardOf(id); if (el) { el.classList.toggle('d2-turn', id === e.u); tagCard(el, id); } });
                    await wait(90);
                    break;
                }
                case 'move': {
                    segment.actor = e.u;
                    segment.move = e.m;
                    hitsOnTarget = {};
                    const name = moveName(e.u, e.m);
                    caption(esc(uName(e.u)) + ' · ' + esc(name), e.tg ? esc('→ ' + uName(e.tg)) : '');
                    if (e.m === 'ultimate') {
                        await cutIn(e.u, name);
                    } else if (e.m === 'guardia') {
                        flashCard(e.u, 'd2-flashw', 300);
                        SND.play('shield');
                        await wait(350);
                    } else if (e.m !== 'passa') {
                        const el = cardOf(e.u);
                        if (el) el.classList.add('d2-lunge');
                        await wait(e.m === 'abilita' ? 380 : 240);
                        if (el) el.classList.remove('d2-lunge');
                    }
                    break;
                }
                case 'hit': {
                    if (e.miss) { floatNum(e.dst, T('Schivato', 'Dodged'), 'd2-txt'); segment.extra.push(T('schivato', 'dodged')); await wait(380); break; }
                    if (e.blocked) {
                        const el = cardOf(e.dst);
                        if (el) { el.classList.add('d2-break'); setTimeout(() => el.classList.remove('d2-break'), 500); }
                        floatNum(e.dst, T('Barriera', 'Barrier'), 'd2-txt');
                        SND.play('break');
                        segment.extra.push(T('Barriera spezzata', 'Barrier broken'));
                        await wait(420);
                        break;
                    }
                    hitsOnTarget[e.dst] = (hitsOnTarget[e.dst] || 0) + 1;
                    const n = hitsOnTarget[e.dst];
                    segment.hits[e.dst] = (segment.hits[e.dst] || 0) + e.amount;
                    segment.hitsCount = (segment.hitsCount || 0) + 1;
                    if (e.crit) segment.crit[e.dst] = true;
                    const dx = n > 1 ? ((n % 2 ? -1 : 1) * 26 * Math.min(2, n - 1)) : 0;
                    const dy = n > 1 ? -18 * (n - 1) : 0;
                    if (e.crit) {
                        await wait(60); // pausa d'impatto
                        flashCard(e.dst, 'd2-flashw', 280);
                        rays(e.dst);
                        floatNum(e.dst, '−' + fmt(e.amount), 'd2-crit', dx, dy, T('CRITICO', 'CRITICAL'));
                        SND.play('crit');
                        if (S.side !== null && unitSt(e.dst).side === S.side) buzz(40);
                    } else {
                        floatNum(e.dst, '−' + fmt(e.amount), '', dx, dy);
                        SND.play('hit');
                    }
                    flashCard(e.dst, 'd2-shake', 340);
                    if (e.hp !== undefined) liveSet(e.dst, e.hp, e.sh, true);
                    await wait(e.crit ? 520 : 280);
                    break;
                }
                case 'dot': case 'curse': case 'detonate': case 'reflect': case 'chain': case 'guarded': {
                    const who = e.dst || e.u;
                    floatNum(who, '−' + fmt(e.amount), 'd2-dot', 0, -10);
                    if (e.t === 'dot') segment.fx.push({ k: e.k, dot: e.amount, u: who });
                    else segment.extra.push((e.t === 'reflect' ? T('Riflesso', 'Reflect') : e.t === 'chain' ? T('Catena', 'Chain') : e.t === 'guarded' ? T('Guardiano', 'Guardian')
                        : e.t === 'detonate' ? T('Detonazione', 'Detonation') : T('Maledizione', 'Curse')) + ' ' + uName(who) + ' −' + fmt(e.amount));
                    if (e.hp !== undefined) liveSet(who, e.hp, e.sh, true);
                    flashCard(who, 'd2-shake', 260);
                    SND.play('hit');
                    await wait(300);
                    break;
                }
                case 'heal': {
                    floatNum(e.u, '+' + fmt(e.amount), 'd2-h');
                    segment.heals[e.u] = (segment.heals[e.u] || 0) + e.amount;
                    if (e.hp !== undefined) liveSet(e.u, e.hp, e.sh, false);
                    flashCard(e.u, 'd2-healed', 500);
                    SND.play('heal');
                    await wait(260);
                    break;
                }
                case 'shield': {
                    if (e.broken) {
                        floatNum(e.u, T('Scudo rotto', 'Shield broken'), 'd2-txt');
                        SND.play('break');
                    } else {
                        floatNum(e.u, '+' + fmt(e.amount), 'd2-s', 0, 14);
                        SND.play('shield');
                        segment.extra.push(T('Scudo ', 'Shield ') + uName(e.u) + ' +' + fmt(e.amount));
                    }
                    if (e.hp !== undefined) liveSet(e.u, e.hp, e.sh, false);
                    await wait(200);
                    break;
                }
                case 'status': {
                    if (e.op === 'add' || e.op === 'refresh') {
                        addChip(e);
                        segment.fx.push({ k: e.k, v: e.v, turns: e.turns, st: e.st, u: e.u });
                        SND.play('status');
                        await wait(140);
                    } else if (e.op === 'resist') {
                        floatNum(e.u, T('Resiste', 'Resists'), 'd2-txt', 0, 30);
                        segment.resist.push({ k: e.k, u: e.u, why: e.why });
                        await wait(220);
                    } else {
                        removeChip(e.u, e.k);
                    }
                    break;
                }
                case 'ko': {
                    S.live[e.u] = Object.assign(S.live[e.u] || {}, { hp: 0, ko: true });
                    setBars(e.u, 0, 0, true);
                    setKo(e.u, true, true);
                    SND.play('ko');
                    segment.ko.push(e.u);
                    say(uName(e.u) + ' KO');
                    await wait(900);
                    break;
                }
                case 'revive': {
                    setKo(e.u, false, false);
                    liveSet(e.u, e.hp, e.sh, false);
                    floatNum(e.u, T('Rinascita', 'Revived'), 'd2-txt');
                    SND.play('heal');
                    segment.extra.push(T('Rinascita: ', 'Revived: ') + uName(e.u));
                    await wait(600);
                    break;
                }
                case 'undying': floatNum(e.u, T('Indistruttibile', 'Undying'), 'd2-txt'); await wait(400); break;
                case 'ps': {
                    const host = document.querySelector('[data-ps="' + e.side + '"]');
                    [host, e.side === mySide() ? $('d2-ps') : null].forEach((box) => {
                        if (!box) return;
                        box.innerHTML = psDiamonds(e.now);
                        const d = box.querySelectorAll('i')[Math.max(0, e.now - 1)];
                        if (d && e.delta > 0) d.classList.add('d2-ps-new'); // non d2-pop: e' la classe del popup
                    });
                    SND.play('ps');
                    break;
                }
                case 'charge': {
                    const el = cardOf(e.u);
                    if (el) {
                        el.querySelector('.d2-ug i').style.setProperty('--v', Math.min(100, e.now / CFG.rules.charge_max * 100) + '%');
                        el.classList.toggle('d2-ready', e.now >= CFG.rules.charge_max);
                    }
                    break;
                }
                case 'stacks': break;
                case 'form': {
                    if (e.op === 'start') {
                        const f = (unitSt(e.u).forms || {})[e.k] || {};
                        banner(T('Forma: ', 'Form: ') + (f[CFG.lang] || e.k));
                        segment.extra.push(T('Forma ', 'Form ') + (f[CFG.lang] || e.k));
                        await wait(500);
                    }
                    break;
                }
                case 'terrain': {
                    if (e.op === 'start') {
                        const def = CFG.terrains[e.k] || { n: e.k };
                        const el = $('d2-terrain');
                        el.dataset.k = e.k;
                        el.innerHTML = ic(e.k) + ' ' + esc(def.n) + ' · ' + e.giri + ' ' + T('giri', 'rounds');
                        el.hidden = false;
                        el.classList.remove('d2-in');
                        void el.offsetWidth;
                        el.classList.add('d2-in');
                        segment.extra.push(T('Terreno: ', 'Terrain: ') + def.n);
                        await wait(600);
                    } else {
                        $('d2-terrain').hidden = true;
                    }
                    break;
                }
                case 'order': segment.extra.push(uName(e.u) + (e.pct > 0 ? T(' avanza del ', ' moves up by ') : T(' arretra del ', ' moves back by ')) + Math.abs(e.pct) + '%'); break;
                case 'extra': case 'extra_given': banner(T('Turno extra: ', 'Extra turn: ') + uName(e.u)); segment.extra.push(T('Turno extra', 'Extra turn')); await wait(600); break;
                case 'skip': {
                    const def = CFG.statuses[e.k] || { n: e.k };
                    floatNum(e.u, T('Salta: ', 'Skips: ') + def.n, 'd2-txt');
                    segment.extra.push(uName(e.u) + T(' salta il turno (', ' skips the turn (') + def.n + ')');
                    await wait(560);
                    break;
                }
                case 'fatigue': banner(T('Sfinimento: cure −', 'Fatigue: healing −') + (e.heal || 0) + '%, ' + T('danni +', 'damage +') + (e.dmg || 0) + '%', true); await wait(900); break;
                case 'passive': floatNum(e.u, e.name || T('Passiva', 'Passive'), 'd2-txt', 0, -40); segment.extra.push(T('Passiva: ', 'Passive: ') + (e.name || '')); await wait(450); break;
                case 'counter': floatNum(e.u, T('Contrattacco', 'Counter'), 'd2-txt', 0, -40); await wait(300); break;
                case 'steal': floatNum(e.dst, T('Rubato: ', 'Stolen: ') + ((CFG.statuses[e.k] || {}).n || e.k), 'd2-txt'); await wait(400); break;
                case 'confused': floatNum(e.u, T('Confuso', 'Confused'), 'd2-txt'); await wait(400); break;
                case 'trait': if ((e.units || []).length) banner(T('Legame attivo', 'Bond active') + ' · ' + traitName(e)); await wait(500); break;
                default: break;
            }
        }
        // Colpi multipli: il totale sotto.
        Object.keys(hitsOnTarget).forEach((id) => {
            if (hitsOnTarget[id] < 2) return;
            const c = centerOf(Number(id));
            const el = document.createElement('div');
            el.className = 'd2-total';
            el.textContent = '−' + fmt(segment.hits[id] || 0) + ' · ' + hitsOnTarget[id] + T(' colpi', ' hits');
            el.style.left = c.x + 'px';
            el.style.top = c.y + 60 + 'px';
            $('d2-layer').appendChild(el);
            setTimeout(() => el.remove(), 1300 * spd());
        });
        segments.forEach((sg) => writeLog(sg));
    }

    function traitName(e) {
        const id = (e.units || [])[0];
        const u = id && unitSt(id);
        const names = u && u.kit && u.kit.traits;
        return names && names.length ? names.join(', ') : e.k;
    }

    function addChip(e) {
        const el = cardOf(e.u);
        if (!el) return;
        const box = el.querySelector('.d2-fx');
        const old = box.querySelector('[data-k="' + e.k + '"]');
        const def = CFG.statuses[e.k] || { n: e.k, neg: false };
        const lbl = chipLabel({ k: e.k, v: e.v });
        const count = e.st > 1 ? e.st : e.turns > 0 ? e.turns : '';
        const html = ic(e.k) + (lbl ? '<span class="d2-lbl">' + esc(lbl) + '</span>' : '') + (count !== '' ? ' ' + count : '');
        if (old) { old.innerHTML = html; return; }
        const span = document.createElement('span');
        span.dataset.k = e.k;
        span.className = (def.neg ? 'd2-neg ' : '') + 'd2-in';
        span.innerHTML = html;
        box.appendChild(span);
        if (VISUAL.includes(e.k)) {
            el.classList.add('st-' + e.k);
            if (e.k === 'congelamento') el.classList.add('d2-in-ice');
        }
    }
    function removeChip(id, k) {
        const el = cardOf(id);
        if (!el) return;
        const c = el.querySelector('.d2-fx [data-k="' + k + '"]');
        if (c) c.remove();
        if (VISUAL.includes(k)) el.classList.remove('st-' + k);
    }

    /** Un colore chiaro (grigio dei Comuni, azzurro, giallo, bianco) vuole il testo nero. */
    function isLight(color) {
        let h = String(color || '').trim().replace('#', '');
        if (h.length === 3) h = h.split('').map((c) => c + c).join('');
        if (!/^[0-9a-f]{6}$/i.test(h)) return false;
        const n = parseInt(h, 16);
        const lin = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
        const L = 0.2126 * lin(n >> 16) + 0.7152 * lin((n >> 8) & 255) + 0.0722 * lin(n & 255);
        return (L + 0.05) / 0.05 > 1.05 / (L + 0.05);
    }
    /** Rimpicciolisce il testo di box finche' ogni riga (sel) entra nella sua colonna. */
    function fitText(box, sel) {
        if (!box) return;
        const rows = [...box.querySelectorAll(sel)];
        let size = parseFloat(getComputedStyle(box).fontSize) || 40;
        while (size > 24 && rows.some((b) => b.offsetWidth > b.parentElement.clientWidth)) {
            size = Math.floor(size * 0.94);
            box.style.fontSize = size + 'px';
        }
    }

    // Il taglio dell'ultimate (tavola 18): 1,45 s di scena e 0,38 s di uscita, per spd().
    async function cutIn(id, name) {
        const u = unitSt(id);
        const color = (u.theme && u.theme.color) || rarColor(u.rarity);
        SND.play('ult');
        playMusic(u.pid, u.audio, u.theme && u.theme.music);
        const el = document.createElement('div');
        el.className = 'd2-cut' + (isLight(color) ? ' d2-cut-light' : '');
        el.style.setProperty('--c', color);
        // Il nome in righe di parole (al massimo 12 lettere l'una), per salire una alla volta.
        const rows = [];
        String(name).split(' ').forEach((w) => {
            if (!rows.length || (rows[rows.length - 1] + ' ' + w).length > 12) rows.push(w);
            else rows[rows.length - 1] += ' ' + w;
        });
        let lines = '';
        [[18, 340, 0], [31, 220, 40], [44, 420, 70], [57, 260, 20], [70, 360, 90], [83, 200, 50]].forEach(([top, w, d]) => {
            lines += '<i style="top:' + top + '%;width:' + w + 'px;animation-delay:calc(' + (150 + d) + 'ms * var(--spd))"></i>';
        });
        el.innerHTML = '<div class="d2-cut-band"></div><div class="d2-cut-lines">' + lines + '</div>'
            + '<div class="d2-cut-pic">' + (u.img ? '<img src="' + esc(u.img) + '" alt="">' : '<span class="d2-cut-ini">' + esc(uName(id).charAt(0)) + '</span>') + '</div>'
            + '<div class="d2-cut-seam" aria-hidden="true"><span>' + 'ULTIMATE ★ '.repeat(16) + '</span></div>'
            + '<div class="d2-cut-text"><h2 class="d2-cut-name">' + rows.map((r) => '<span><b>' + esc(r) + '</b></span>').join('') + '</h2>'
            + '<div class="d2-cut-meta"><span class="d2-cut-chip">' + ic('ultimate') + 'Ultimate</span><span class="d2-cut-who">' + esc(uName(id)) + ' · ' + esc(rarName(u.rarity)) + '</span></div></div>'
            + '<div class="d2-cut-flash"></div>';
        $('d2-layer').appendChild(el);
        fitText(el.querySelector('.d2-cut-name'), 'b');
        // Riduci movimento: la scena ferma, solo in dissolvenza, il tempo di leggerla.
        const reduced = reducedMq.matches;
        await (reduced ? sleep(1200) : wait(1450));
        el.classList.add('d2-out');
        await (reduced ? sleep(200) : wait(380));
        el.remove();
        stopMusic(1800);
    }

    // ── Registro ─────────────────────────────────────────────────────────
    function writeLog(sg) {
        const hitIds = Object.keys(sg.hits);
        const healIds = Object.keys(sg.heals);
        const hasContent = sg.move || hitIds.length || healIds.length || sg.fx.length || sg.ko.length || sg.extra.length || sg.resist.length;
        if (!hasContent) return;
        const actor = sg.actor;
        const foe = actor && unitSt(actor).side !== mySide();
        const turnN = sg.step.n || (S.dyn && S.dyn.turn) || '';
        const head = (turnN ? T('Turno ', 'Turn ') + turnN + ' · ' : '') + (actor ? uName(actor) : '') + (sg.move ? ' · ' + moveName(actor, sg.move) : sg.turnStart ? ' · ' + T('inizio turno', 'turn start') : '');
        const parts = [];
        hitIds.forEach((id) => parts.push(T('Colpisce ', 'Hits ') + '<b>' + esc(uName(Number(id))) + '</b>' + (sg.crit[id] ? '<span class="d2-crit">' + esc(T('CRITICO', 'CRIT')) + '</span>' : '')));
        healIds.forEach((id) => parts.push(T('Cura ', 'Heals ') + '<b>' + esc(uName(Number(id))) + '</b> +' + fmt(sg.heals[id])));
        if (sg.move === 'guardia') parts.push(T('Si mette in Guardia', 'Guards'));
        if (sg.step.kind === 'timeout') parts.unshift(T('Tempo scaduto', 'Timed out'));
        sg.ko.forEach((id) => parts.push('<b>' + esc(uName(id)) + '</b> KO'));
        sg.extra.forEach((x) => parts.push(esc(x)));
        const chipsHtml = sg.fx.map((f) => {
            const def = CFG.statuses[f.k] || { n: f.k, neg: false };
            return '<span class="d2-chip' + (def.neg ? ' d2-neg' : '') + '">' + ic(f.k) + ' ' + esc(def.n) + (f.dot ? ' −' + fmt(f.dot) : '') + (f.u ? ' · ' + esc(uName(f.u)) : '') + '</span>';
        }).join('') + sg.resist.map((f) => '<span class="d2-chip">' + ic(f.k) + ' ' + esc(((CFG.statuses[f.k] || {}).n || f.k)) + ' ' + esc(T('non riuscito', 'resisted')) + '</span>').join('');
        const dmg = hitIds.reduce((a, id) => a + sg.hits[id], 0);
        const heal = healIds.reduce((a, id) => a + sg.heals[id], 0);
        const num = dmg ? '<span class="d2-n d2-d num">−' + fmt(dmg) + '</span>' : heal ? '<span class="d2-n d2-h num">+' + fmt(heal) + '</span>' : '<span></span>';
        const li = document.createElement('li');
        li.className = 'd2-le' + (foe ? ' d2-foe' : '');
        li.innerHTML = (actor ? avatar(actor) : '<span></span>') + '<div><span class="d2-t">' + esc(head) + '</span><p>' + parts.join(', ') + '</p>' + chipsHtml + '</div>' + num;
        const log = $('d2-log');
        log.prepend(li);
        while (log.children.length > 80) log.lastChild.remove();
        if (dmg || heal) {
            S.lastLine = ic(heal && !dmg ? 'rigenerazione' : 'attacco') + ' ' + esc(actor ? uName(actor) : '') + ' ' + (dmg ? '<b class="d2-d">−' + fmt(dmg) + '</b>' : '<b class="d2-h">+' + fmt(heal) + '</b>');
            say((actor ? uName(actor) + ': ' : '') + (dmg ? fmt(dmg) + T(' danni', ' damage') : '+' + fmt(heal)));
        }
    }

    // ── Chat e reazioni ──────────────────────────────────────────────────
    function addChat(list) {
        const ol = $('d2-chat');
        list.forEach((m) => {
            if (m.id <= S.chat) return;
            S.chat = m.id;
            const li = document.createElement('li');
            if (m.user === CFG.me) li.className = 'd2-mine';
            const b = document.createElement('b');
            b.textContent = m.name;
            li.appendChild(b);
            li.appendChild(document.createTextNode(m.text));
            ol.appendChild(li);
            if (m.user !== CFG.me && $('d2-tab-chat').getAttribute('aria-selected') !== 'true') S.unread++;
        });
        ol.scrollTop = ol.scrollHeight;
        badge();
    }
    function badge() {
        document.querySelectorAll('#d2-tab-chat .d2-badge, #d2-drawer-btn .d2-badge').forEach((b) => {
            b.hidden = S.unread <= 0;
            b.textContent = S.unread;
        });
    }
    function addReactions(list, quiet) {
        list.forEach((r) => {
            if (r.id <= S.react) return;
            S.react = r.id;
            if (quiet) return;
            const el = document.createElement('span');
            el.className = 'd2-react-fly';
            el.textContent = r.r.length <= 4 ? r.r : '✨';
            el.style.left = 10 + Math.random() * 80 + '%';
            $('d2-reacts').appendChild(el);
            setTimeout(() => el.remove(), 2300);
        });
    }

    // ── Kit ──────────────────────────────────────────────────────────────
    function showKit(id) {
        S.kitUnit = id;
        renderKit();
        tab('kit');
        if (phoneMq.matches) openDrawer(true);
    }
    function renderKit() {
        if (!S.st) return;
        const dyn = S.dyn;
        const id = S.kitUnit || (dyn && dyn.actor) || unitIds().find((u) => unitSt(u).side === mySide());
        const u = unitSt(id);
        const d = unitDyn(id);
        if (!u || !d) return;
        const k = u.kit;
        const part = (label, p) => p && p.name ? '<div class="d2-kit-p"><h5><em>' + label + '</em>' + esc(p.name) + (p.meta ? '<small>' + esc(p.meta) + '</small>' : '') + '</h5><p>' + esc(p.text) + '</p></div>' : '';
        const e = d.eff || {};
        $('d2-kit').innerHTML = '<div class="d2-kit-h">' + avatar(id) + '<div><b>' + esc(uName(id)) + '</b><small>' + esc(u.role) + ' · ' + esc(rarName(u.rarity)) + ' · ' + (u.level >= 6 ? 'MAX' : 'Lv ' + u.level)
            + (k.traits && k.traits.length ? ' · ' + esc(k.traits.join(', ')) : '') + '</small></div></div>'
            + part(T('PASSIVA', 'PASSIVE'), k.passive) + part(T('ATTACCO', 'ATTACK'), k.attack) + part(T('ABILITÀ', 'SKILL'), k.skill) + part('ULTIMATE', k.ultimate)
            + '<div class="d2-kit-stats">' + [['ATK', e.atk], ['DEF', e.def], [T('VEL', 'SPD'), e.spd], [T('CRIT', 'CRIT'), (e.crit !== undefined ? e.crit + '%' : '')],
                [T('D. CRIT', 'C. DMG'), e.cdmg !== undefined ? e.cdmg + '%' : ''], [T('PREC', 'ACC'), e.prec], [T('TEN', 'TEN'), e.ten], ['HP', fmt(d.hp)]]
                .map((s) => '<span>' + esc(s[0]) + '<b class="num">' + esc(typeof s[1] === 'number' ? fmt(Math.round(s[1])) : (s[1] ?? '')) + '</b></span>').join('') + '</div>';
    }

    // ── Dettaglio di uno stato (passando sopra o tenendo premuto) ─────────
    function showPop(chip) {
        const card = chip.closest('.d2-card');
        if (!card) return;
        const id = Number(card.dataset.u);
        const k = chip.dataset.k;
        const pop = $('d2-pop');
        const d = unitDyn(id);
        let title = '';
        let body = '';
        let meta = '';
        if (k === 'scudo') {
            title = T('Scudo', 'Shield');
            body = T('Assorbe ', 'Absorbs ') + fmt(d.shield) + T(' danni prima della vita. Cala del 30% a ogni suo turno.', ' damage before HP. Shrinks by 30% each of its turns.');
        } else if (k === 'cariche') {
            const st = unitSt(id).stacks;
            title = st ? st.name : '';
            body = T('Cariche: ', 'Stacks: ') + d.stk + (st ? ' / ' + st.max : '') + '. ' + (unitSt(id).kit.passive.text || '');
        } else {
            const f = d.fx[Number(chip.dataset.i)] || d.fx.find((x) => x.k === k) || {};
            const def = CFG.statuses[k] || { n: k, d: '' };
            title = def.n + (f.st > 1 ? ' × ' + f.st : '');
            body = String(def.d || '').replace('{v}', fmt(f.v ?? def.v)).replace('{t}', f.t ?? def.t).replace('{max}', def.max);
            const parts = [];
            if (f.src) parts.push(T('Messo da ', 'Applied by ') + uName(f.src));
            if (f.pool) parts.push(T('Danni conservati: ', 'Stored damage: ') + fmt(f.pool));
            if (def.neg) parts.push(T('Lo toglie: Purificazione', 'Removed by: Cleanse'));
            meta = parts.join(' · ');
            if (f.t > 0) title += '<small>' + f.t + ' ' + esc(f.t === 1 ? T('turno', 'turn') : T('turni', 'turns')) + '</small>';
        }
        pop.innerHTML = '<b>' + ic(k) + ' ' + title + '</b><p>' + esc(body) + '</p>' + (meta ? '<div class="d2-pop-m">' + esc(meta) + '</div>' : '');
        const r = chip.getBoundingClientRect();
        pop.style.left = Math.max(8, Math.min(innerWidth - 290, r.left - 20)) + 'px';
        pop.style.top = (r.top > innerHeight / 2 ? r.top - 10 - 120 : r.bottom + 10) + 'px';
        pop.hidden = false;
    }
    function hidePop() { $('d2-pop').hidden = true; }

    // ── Tempo del turno ──────────────────────────────────────────────────
    let timerRaf = 0;
    function tickTimer() {
        cancelAnimationFrame(timerRaf);
        const el = $('d2-timer');
        const dyn = S.latest;
        const total = (S.st && S.st.rules.turn_seconds) || 0;
        if (!dyn || !dyn.deadline || dyn.over || !total) { el.hidden = true; return; }
        el.hidden = false;
        const loop = () => {
            const left = Math.max(0, dyn.deadline - now());
            const mine = myTurn();
            el.style.setProperty('--p', Math.max(0, Math.min(1, left / total)));
            $('d2-timer-n').textContent = left >= 3600 ? Math.floor(left / 3600) + ' h ' + String(Math.floor((left % 3600) / 60)).padStart(2, '0') + ' min'
                : Math.floor(left / 60) + ':' + String(Math.floor(left % 60)).padStart(2, '0');
            el.classList.toggle('d2-late', mine && left <= (total >= 3600 ? 4 * 3600 : 10));
            el.classList.toggle('d2-wait-opp', !mine);
            el.setAttribute('aria-label', (mine ? T('Tempo per la tua mossa: ', 'Time for your move: ') : T('Tempo dell\'avversario: ', 'Opponent\'s time: ')) + Math.ceil(left) + 's');
            if (left <= 0) { schedule(800); return; }
            timerRaf = requestAnimationFrame(() => setTimeout(loop, 250));
        };
        loop();
    }

    // ── Presentazione VS ─────────────────────────────────────────────────
    function introSeen() {
        try {
            const key = 'd2.vs.' + S.match;
            if (sessionStorage.getItem(key)) return true;
            sessionStorage.setItem(key, '1');
        } catch (e) { /* niente */ }
        return false;
    }
    // Tavola 17: 2,1 s di scena e 0,36 s di uscita, per spd(); con «Riduci
    // movimento» la stessa scena ferma, in dissolvenza.
    function intro() {
        const reduced = reducedMq.matches;
        const p = S.st.players;
        const half = (side, cls) => {
            const pl = p[side] || p[String(side)] || {};
            const cards = unitIds().filter((id) => unitSt(id).side === side).map((id) => {
                const u = unitSt(id);
                return '<span style="--r:' + rarColor(u.rarity) + '">' + (u.img ? '<img src="' + esc(u.img) + '" alt="">' : esc(uName(id).charAt(0))) + '</span>';
            }).join('');
            const meta = pl.rank ? '<span style="color: var(--t-' + esc(pl.rank.key) + ')">' + (window.D2Icons ? window.D2Icons.emblem(pl.rank.key) : '') + '</span>' + esc(pl.rank.label) + ' · ' + fmt(pl.rating)
                : pl.bot ? 'Bot · ' + esc(botLevel(pl.level)) : '';
            return '<div class="d2-vs-half ' + cls + '"><div class="d2-vs-in"><div class="d2-vs-cards">' + cards + '</div>'
                + '<div class="d2-vs-name"><b>' + esc(pl.username || '?') + '</b></div>' + (meta ? '<span class="d2-vs-meta">' + meta + '</span>' : '') + '</div></div>';
        };
        const el = document.createElement('div');
        el.className = 'd2-vs';
        const a = mySide();
        el.innerHTML = '<div class="d2-vs-shake">' + half(a, 'd2-vs-you') + half(1 - a, 'd2-vs-foe') + '<div class="d2-vs-seam"></div><span class="d2-vs-mid">VS</span></div>'
            + '<span class="d2-vs-tag">' + esc(modeLabel()) + '</span><span class="d2-vs-skip">' + esc(T('Tocca per saltare', 'Tap to skip')) + '</span>';
        document.body.appendChild(el);
        el.querySelectorAll('.d2-vs-name').forEach((n) => fitText(n, 'b'));
        let done = false;
        const close = () => {
            if (done) return;
            done = true;
            el.classList.add('d2-out');
            setTimeout(() => { el.remove(); settle(); }, reduced ? 200 : 360 * spd());
        };
        el.addEventListener('click', close);
        window.addEventListener('keydown', close, { once: true });
        setTimeout(close, reduced ? 1800 : 2100 * spd());
    }

    // ── Fine partita ─────────────────────────────────────────────────────
    function showEnd() {
        if (S.ended) return;
        S.ended = true;
        stopPolling();
        $('d2-timer').hidden = true;
        const dyn = S.latest;
        const res = dyn.result || {};
        const w = dyn.winner;
        let cls = '';
        let title;
        if (w === null || w === undefined) title = T('Pareggio', 'Draw');
        else if (S.side === null) { const pl = S.st.players[w] || S.st.players[String(w)] || {}; title = T('Vince ', 'Winner: ') + (pl.username || '?'); }
        else if (w === S.side) { title = T('Vittoria', 'Victory'); cls = 'd2-win'; }
        else { title = T('Sconfitta', 'Defeat'); cls = 'd2-lose'; }
        const reason = { ko: T('Tutte le carte avversarie sono KO.', 'All enemy cards are KO.'), limit: T('Limite di turni: vince chi ha più vita in percentuale.', 'Turn limit: the team with more HP % wins.'),
            forfeit: T('Abbandono.', 'Forfeit.'), timeout: T('Tempo scaduto tre volte.', 'Timed out three times.') }[res.reason || dyn.reason] || '';
        let elo = '';
        if (S.st.mode === 'ranked' && S.side !== null && res.rating && res.rating.winner_delta !== null) {
            const meWon = w === S.side;
            const delta = w === null ? res.rating.winner_delta : meWon ? res.rating.winner_delta : res.rating.loser_delta;
            const after = meWon ? res.rating.winner_after : res.rating.loser_after;
            elo = '<div class="d2-elo ' + (delta > 0 ? 'd2-up' : delta < 0 ? 'd2-down' : '') + ' num">' + (delta > 0 ? '+' : '') + fmt(delta) + ' · ' + fmt(after) + '</div>'
                + (delta === 0 ? '<p>' + esc(T('Oggi contro questo avversario le Ranked non contano più.', 'Ranked games against this opponent no longer count today.')) + '</p>' : '');
        }
        // I numeri della partita: chi ha fatto piu' danni e piu' cure.
        let mvp = '';
        let dmgBest = 0; let dmgId = 0; let healBest = 0; let healId = 0;
        unitIds().forEach((id) => {
            const s = unitDyn(id, dyn).stats || {};
            if ((s.dealt || 0) > dmgBest) { dmgBest = s.dealt; dmgId = id; }
            if ((s.healed || 0) > healBest) { healBest = s.healed; healId = id; }
        });
        if (dmgId) mvp += '<div><small>' + esc(T('Più danni', 'Most damage')) + '</small><b>' + esc(uName(dmgId)) + '</b><span class="num">' + fmt(dmgBest) + '</span></div>';
        if (healId) mvp += '<div><small>' + esc(T('Più cure', 'Most healing')) + '</small><b>' + esc(uName(healId)) + '</b><span class="num">' + fmt(healBest) + '</span></div>';
        mvp += '<div><small>' + esc(T('Turni', 'Turns')) + '</small><b class="num">' + fmt(dyn.turn) + '</b></div>';
        const el = $('d2-end');
        el.className = 'd2-end ' + cls;
        const canRematch = S.side !== null && S.st.mode !== 'ranked';
        el.innerHTML = '<h2 id="d2-end-t">' + esc(title) + '</h2><p>' + esc(reason) + '</p>' + elo + '<div class="d2-mvp">' + mvp + '</div>'
            + '<div class="d2-acts"><a class="d2-btn d2-btn-w" href="' + esc(CFG.urls.end + '?m=' + S.match) + '">' + esc(T('Riepilogo', 'Summary')) + '</a>'
            + (canRematch ? '<button class="d2-btn" type="button" id="d2-rematch">' + esc(T('Rivincita', 'Rematch')) + '</button>' : '')
            + '<a class="d2-btn" href="' + esc(CFG.lobby) + '">' + esc(T('Torna ai duelli', 'Back to duels')) + '</a>'
            + '<button class="d2-btn" type="button" id="d2-see">' + esc(T('Guarda il campo', 'See the field')) + '</button></div><p id="d2-offer"></p>';
        el.hidden = false;
        SND.play(cls === 'd2-win' ? 'win' : cls === 'd2-lose' ? 'lose' : 'turn');
        say(title + '. ' + reason);
        const rb = $('d2-rematch');
        if (rb) rb.addEventListener('click', rematch);
        $('d2-see').addEventListener('click', () => { el.hidden = true; });
        if (S.side !== null && S.st.mode !== 'bot') watchOffers();
        el.querySelector('button, a').focus();
    }

    async function rematch() {
        const r = await api('rematch', null, { m: S.match });
        if (!r.ok) { toast(r.error || '', true); return; }
        location.href = CFG.self + '?m=' + r.match;
    }

    // L'avversario propone la rivincita: arriva nel timbro personale ('dr').
    async function watchOffers() {
        // Dal primo evento che il timbro ricorda: l'avversario puo' aver chiesto
        // la rivincita mentre qui finivano le animazioni (la stanza vale 10 minuti).
        let cursor = 0;
        const loop = async () => {
            if ($('d2-end').hidden && !document.body.contains($('d2-offer'))) return;
            const r = await fetch(CFG.rt + '?u=' + cursor, { credentials: 'same-origin', cache: 'no-store' }).then((x) => x.json()).catch(() => null);
            if (r && r.u) {
                if (r.u.seq) cursor = r.u.seq;
                (r.u.events || []).forEach((e) => {
                    if (e.t === 'dr' && Number(e.from) === S.match && !(e.at && e.at < Date.now() / 1000 - 600)) {
                        const box = $('d2-offer');
                        if (box) box.innerHTML = esc(T('L\'avversario vuole la rivincita. ', 'Your opponent wants a rematch. ')) + '<button class="d2-btn d2-btn-sm d2-btn-w" type="button" id="d2-accept">' + esc(T('Accetta', 'Accept')) + '</button>';
                        const b = $('d2-accept');
                        if (b) b.addEventListener('click', () => acceptOffer(e.c));
                    }
                });
            }
            setTimeout(loop, 3000);
        };
        setTimeout(loop, 2000);
    }
    async function acceptOffer(code) {
        const r = await api('room', null, { action: 'join', code, team: (S.st && unitIds().filter((id) => unitSt(id).side === S.side).map((id) => unitSt(id).pid)) || [] });
        if (!r.ok) { toast(r.error || '', true); return; }
        location.href = CFG.self + '?m=' + r.match;
    }

    // ── Stanza in attesa ─────────────────────────────────────────────────
    function showWaiting(r) {
        $('d2-app').hidden = true;
        const el = $('d2-wait');
        const mine = !!r.code;
        const invite = r.invite || '';
        if (!el.dataset.ready) {
            el.dataset.ready = '1';
            el.innerHTML = '<small class="d2-pill">' + esc(invite ? T('Sfida a un amico', 'Friend challenge') : T('Stanza con codice', 'Room code')) + '</small>'
                + (invite ? '<div class="d2-code d2-code-name">' + esc(invite) + '</div><p>' + esc(T('Aspetto che accetti: la sfida arriva nella sua campanella e nei Duelli. Vale 10 minuti.', 'Waiting for them to accept: the challenge shows up in their bell and in Duels. It lasts 10 minutes.')) + '</p>'
                    : mine ? '<div class="d2-code" id="d2-code">' + esc(r.code) + '</div><p>' + esc(T('Manda il codice a chi vuoi sfidare: entra da «Entra con codice». La stanza aspetta 10 minuti.', 'Send the code to your opponent: they join with "Join with code". The room waits 10 minutes.')) + '</p>'
                    : '<p>' + esc(T('Aspetto che la stanza parta…', 'Waiting for the room to start…')) + '</p>')
                + '<p class="num" id="d2-wait-left"></p><p>' + esc((r.rules && r.rules.levels === 'max') ? T('Livelli pareggiati', 'Equal levels') : T('Livelli di ognuno', 'Own levels'))
                + ((r.rules && r.rules.cost_limit) ? ' · ' + T('costo fino a ', 'cost up to ') + r.rules.cost_limit : '') + '</p>'
                + '<div class="d2-acts">' + (mine && !invite ? '<button class="d2-btn d2-btn-w" type="button" id="d2-copy">' + esc(T('Copia il codice', 'Copy code')) + '</button>' : '')
                + '<button class="d2-btn" type="button" id="d2-close-room">' + esc(invite ? T('Ritira la sfida', 'Withdraw the challenge') : T('Chiudi la stanza', 'Close the room')) + '</button></div>';
            const copy = $('d2-copy');
            if (copy) copy.addEventListener('click', () => {
                const done = () => toast(T('Codice copiato', 'Code copied'));
                if (navigator.clipboard) navigator.clipboard.writeText(r.code).then(done).catch(() => {}); else done();
            });
            $('d2-close-room').addEventListener('click', async () => {
                await api('forfeit', null, { m: S.match });
                location.href = CFG.lobby;
            });
        }
        el.hidden = false;
        const left = () => {
            const s = Math.max(0, (r.expires || 0) - now());
            const box = $('d2-wait-left');
            if (box) box.textContent = T('Scade tra ', 'Expires in ') + Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0');
        };
        left();
        clearInterval(S.waitTimer);
        S.waitTimer = setInterval(left, 1000);
    }

    // ── Menu, cassetto, tasti ────────────────────────────────────────────
    function tab(name) {
        ['log', 'chat', 'kit'].forEach((n) => {
            $('d2-tab-' + n).setAttribute('aria-selected', String(n === name));
            $('d2-pane-' + n).hidden = n !== name;
        });
        if (name === 'chat') { S.unread = 0; badge(); const ol = $('d2-chat'); ol.scrollTop = ol.scrollHeight; }
    }
    function openDrawer(open) {
        $('d2-drawer').classList.toggle('d2-open', open);
        $('d2-drawer-btn').setAttribute('aria-expanded', String(open));
    }

    function menu() {
        const el = $('d2-menu');
        const open = el.hidden;
        if (!open) { el.hidden = true; $('d2-menu-btn').setAttribute('aria-expanded', 'false'); return; }
        const playing = S.side !== null && S.status === 'active' && S.latest && !S.latest.over;
        el.innerHTML = '<label><input type="checkbox" data-s="fast"' + (SET.fast ? ' checked' : '') + '> ' + esc(T('Animazioni veloci', 'Fast animations')) + '</label>'
            + '<label><input type="checkbox" data-s="sfx"' + (SET.sfx ? ' checked' : '') + '> ' + esc(T('Suoni', 'Sounds')) + '</label>'
            + '<label><input type="checkbox" data-s="music"' + (SET.music ? ' checked' : '') + '> ' + esc(T('Musiche delle ultimate', 'Ultimate music')) + '</label>'
            + '<label><input type="checkbox" data-s="vibrate"' + (SET.vibrate ? ' checked' : '') + '> ' + esc(T('Vibrazione', 'Vibration')) + '</label>'
            + '<label>' + ic('suono') + ' <input type="range" min="0" max="100" step="5" value="' + SET.volume + '" data-s="volume" aria-label="' + esc(T('Volume', 'Volume')) + '"></label>'
            + '<hr><button type="button" data-a="kit">' + ic('abilita') + ' ' + esc(T('Kit delle carte', 'Card kits')) + '</button>'
            + (playing ? '<button type="button" class="d2-danger" data-a="forfeit">' + ic('bandiera') + ' ' + esc(T('Abbandona la partita', 'Forfeit the match')) + '</button>' : '')
            + '<a class="d2-btn d2-btn-sm" style="width:100%;margin-top:6px" href="' + esc(CFG.lobby) + '">' + esc(T('Esci dall\'arena', 'Leave the arena')) + '</a>';
        el.hidden = false;
        $('d2-menu-btn').setAttribute('aria-expanded', 'true');
        el.querySelectorAll('[data-s]').forEach((inp) => inp.addEventListener('input', () => {
            const k = inp.dataset.s;
            SET[k] = inp.type === 'checkbox' ? inp.checked : Number(inp.value);
            saveSettings();
            $('d2-sound').setAttribute('aria-pressed', String(SET.sfx));
        }));
        el.querySelectorAll('[data-a]').forEach((b) => b.addEventListener('click', async () => {
            el.hidden = true;
            if (b.dataset.a === 'kit') { tab('kit'); openDrawer(true); }
            if (b.dataset.a === 'forfeit' && window.confirm(T('Abbandoni la partita? Conta come sconfitta.', 'Forfeit the match? It counts as a loss.'))) {
                const r = await api('forfeit', null, { m: S.match });
                if (r.ok) refresh(); else toast(r.error || '', true);
            }
        }));
    }

    function bindUi() {
        $('d2-moves').addEventListener('click', (e) => {
            const b = e.target.closest('.d2-mv');
            if (b && !b.disabled) { SND.unlock(); selectMove(b.dataset.m); }
        });
        $('d2-prev').addEventListener('click', (e) => { if (e.target.closest('[data-go]')) confirm(); });
        $('d2-tab-log').addEventListener('click', () => tab('log'));
        $('d2-tab-chat').addEventListener('click', () => tab('chat'));
        $('d2-tab-kit').addEventListener('click', () => tab('kit'));
        $('d2-drawer-btn').addEventListener('click', () => openDrawer(!$('d2-drawer').classList.contains('d2-open')));
        $('d2-drawer-x').addEventListener('click', () => openDrawer(false));
        $('d2-menu-btn').addEventListener('click', menu);
        $('d2-sound').setAttribute('aria-pressed', String(SET.sfx));
        $('d2-sound').addEventListener('click', () => {
            SET.sfx = !SET.sfx;
            SET.music = SET.sfx;
            saveSettings();
            $('d2-sound').setAttribute('aria-pressed', String(SET.sfx));
            $('d2-sound').innerHTML = ic(SET.sfx ? 'suono' : 'muto');
        });
        $('d2-chat-f').addEventListener('submit', async (e) => {
            e.preventDefault();
            const inp = $('d2-chat-in');
            const text = inp.value.trim();
            if (!text) return;
            inp.value = '';
            const r = await api('chat', null, { m: S.match, text });
            if (!r.ok) { toast(r.error || '', true); inp.value = text; return; }
            refresh();
        });
        $('d2-react-bar').addEventListener('click', async (e) => {
            const b = e.target.closest('[data-r]');
            if (!b) return;
            addReactions([{ id: S.react + 0.5, r: b.dataset.r }], false);
            const r = await api('react', null, { m: S.match, r: b.dataset.r });
            if (!r.ok && r.code !== 'RATE_LIMIT') toast(r.error || '', true);
        });
        // Dettaglio degli stati: passando sopra (PC) o tenendo premuto (telefono).
        let press = 0;
        document.addEventListener('pointerover', (e) => {
            const chip = e.target.closest && e.target.closest('.d2-fx > span');
            if (chip && e.pointerType === 'mouse') showPop(chip);
        });
        document.addEventListener('pointerout', (e) => { if (e.target.closest && e.target.closest('.d2-fx > span')) hidePop(); });
        document.addEventListener('pointerdown', (e) => {
            const chip = e.target.closest && e.target.closest('.d2-fx > span');
            clearTimeout(press);
            if (chip && e.pointerType !== 'mouse') press = setTimeout(() => showPop(chip), 380);
            if (!e.target.closest('#d2-menu, #d2-menu-btn')) { $('d2-menu').hidden = true; $('d2-menu-btn').setAttribute('aria-expanded', 'false'); }
        });
        document.addEventListener('pointerup', () => { clearTimeout(press); setTimeout(hidePop, 1800); });
        document.addEventListener('focusin', (e) => { const chip = e.target.closest && e.target.closest('.d2-fx > span'); if (chip) showPop(chip); });
        document.addEventListener('focusout', (e) => { if (e.target.closest && e.target.closest('.d2-fx > span')) hidePop(); });
        document.addEventListener('keydown', (e) => {
            if (CFG.replay) { replayKey(e); return; }
            if (e.target.closest && e.target.closest('input, textarea')) return;
            if (e.key === 'Escape') {
                if (!$('d2-menu').hidden) { $('d2-menu').hidden = true; return; }
                if ($('d2-drawer').classList.contains('d2-open')) { openDrawer(false); return; }
                if (S.sel.move) { clearSel(); return; }
            }
            const moves = ['attacco', 'abilita', 'ultimate', (S.latest && S.latest.legal && S.latest.legal.passa) ? 'passa' : 'guardia'];
            if (/^[1-4]$/.test(e.key)) { e.preventDefault(); selectMove(moves[Number(e.key) - 1]); return; }
            if (!S.sel.move) return;
            if (e.key === 'Enter') { e.preventDefault(); confirm(); return; }
            if (e.key === 'Tab' || e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                if (targetsFor(S.sel.move).length > 1) {
                    e.preventDefault();
                    cycleTarget(e.key === 'ArrowLeft' || (e.key === 'Tab' && e.shiftKey) ? -1 : 1);
                }
            }
        });
        document.addEventListener('visibilitychange', () => { if (!document.hidden && !S.stopped) schedule(100); });
        window.addEventListener('resize', () => { if (S.dyn) renderTargets(); });
    }

    // ── Replay (tavola 6) ────────────────────────────────────────────────
    // La partita finita rigiocata con le stesse animazioni: api/duel/replay.php
    // da' i passi (eventi + stato dopo ogni passo), qui si suonano uno dopo
    // l'altro. Saltare a un punto applica lo stato di quel passo senza
    // animazioni; se un'animazione e' in corso si salta appena finisce.
    const PLAY_SVG = '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5l11 6.5-11 6.5z" fill="currentColor"/></svg>';
    const PAUSE_SVG = '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z" fill="currentColor"/></svg>';

    async function replayBoot() {
        S.stopped = true; // niente tempo reale: la partita e' finita
        const r = await api('replay', { m: S.match });
        $('d2-loading').hidden = true;
        if (!r.ok) {
            fatal(r.error || T('Replay non disponibile.', 'Replay not available.'));
            return;
        }
        const R = r.replay;
        S.st = R.static;
        S.side = null;
        S.status = 'finished';
        // legacy: partita di un motore di un'altra versione, solo eventi salvati: si guarda di fila.
        S.rp = { steps: R.steps, i: 0, last: R.steps.length - 1, playing: false, pending: null, run: 0, legacy: !!R.legacy };
        build();
        $('d2-tab-chat').hidden = true;
        S.latest = R.steps[0].dyn;
        applyDyn(R.steps[0].dyn);
        $('d2-mode').textContent = 'Replay · ' + modeLabel();
        $('d2-app').hidden = false;
        replayBar();
        caption(esc(playersLine()), esc(T('Premi play o usa la barra dei turni.', 'Press play or use the turn bar.')));
        setTimeout(() => { if (!S.rp.playing && S.rp.i === 0) rpPlay(); }, 1200);
    }
    function playersLine() {
        const p = (s) => (S.st.players[s] || S.st.players[String(s)] || {}).username || '?';
        return p(0) + T(' contro ', ' vs ') + p(1);
    }
    function stepMove(step) {
        return (step.ev || []).find((e) => e.t === 'move') || null;
    }
    function replayBar() {
        const P = S.rp;
        const final = P.steps[P.last].dyn.turn;
        const marks = P.steps.map((s, i) => {
            if (!i) return '';
            const left = (i / Math.max(1, P.last) * 100).toFixed(2) + '%';
            const mv = stepMove(s);
            const ult = mv && mv.m === 'ultimate';
            const ko = (s.ev || []).some((e) => e.t === 'ko');
            return (ult ? '<i class="d2-rm d2-rm-ult" style="left:' + left + ';--c:' + rarColor(unitSt(mv.u).rarity) + '"></i>' : '')
                + (ko ? '<i class="d2-rm d2-rm-ko" style="left:' + left + '"></i>' : '');
        }).join('');
        const bar = $('d2-rbar');
        bar.innerHTML = '<div class="d2-rbtns">'
            + '<button class="d2-ibtn" type="button" data-r="prev" aria-label="' + esc(T('Mossa precedente', 'Previous move')) + '">' + ic('indietro') + '</button>'
            + '<button class="d2-rplay" type="button" data-r="play" id="d2-rplay"></button>'
            + '<button class="d2-ibtn d2-rnext" type="button" data-r="next" aria-label="' + esc(T('Mossa successiva', 'Next move')) + '">' + ic('indietro') + '</button></div>'
            + '<div class="d2-rmid"><div class="d2-rtrack"><div class="d2-rmarks" aria-hidden="true">' + marks + '</div>'
            + '<input type="range" id="d2-rrange" min="0" max="' + P.last + '" step="1" value="0" aria-label="' + esc(T('Punto della partita', 'Match position')) + '"></div>'
            + '<div class="d2-rinfo"><span>' + esc(T('Turno 1', 'Turn 1')) + '</span><span id="d2-rpos" class="num"></span><span>' + esc(T('Turno ', 'Turn ')) + final + '</span></div>'
            + '<div class="d2-rleg"><span><i class="d2-rm-ko"></i> KO</span><span><i class="d2-rm-ult"></i> Ultimate</span></div></div>'
            + '<div class="d2-rbtns"><button class="d2-ibtn d2-r2x" type="button" data-r="fast" aria-pressed="false" aria-label="' + esc(T('Velocità doppia', 'Double speed')) + '">2×</button>'
            + '<button class="d2-ibtn" type="button" data-r="share" aria-label="' + esc(T('Copia il link del replay', 'Copy the replay link')) + '">' + ic('posta') + '</button>'
            + '<a class="d2-ibtn" href="' + esc(CFG.urls.end + '?m=' + S.match) + '" aria-label="' + esc(T('Riepilogo della partita', 'Match summary')) + '">' + ic('coppa') + '</a></div>';
        bar.hidden = false;
        bar.addEventListener('click', (e) => {
            const b = e.target.closest('[data-r]');
            if (!b) return;
            SND.unlock();
            const a = b.dataset.r;
            if (a === 'play') { if (S.rp.playing) rpPause(); else rpPlay(); }
            if (a === 'prev') rpGoto(S.rp.i - 1);
            if (a === 'next') rpGoto(S.rp.i + 1);
            if (a === 'fast') {
                replayFast = !replayFast;
                document.body.classList.toggle('d2-fast', replayFast || SET.fast);
                b.setAttribute('aria-pressed', String(replayFast));
            }
            if (a === 'share') {
                const url = location.origin + CFG.urls.replay + '?m=' + S.match;
                const done = () => toast(T('Link del replay copiato', 'Replay link copied'));
                if (navigator.clipboard) navigator.clipboard.writeText(url).then(done).catch(() => toast(url)); else toast(url);
            }
        });
        $('d2-rrange').addEventListener('input', (e) => rpGoto(Number(e.target.value)));
        if (P.legacy) bar.querySelectorAll('[data-r="prev"], [data-r="next"], #d2-rrange').forEach((el) => { el.disabled = true; });
        rpUpdate();
    }
    function rpUpdate() {
        const P = S.rp;
        const dyn = P.steps[P.i].dyn || S.latest;
        $('d2-rrange').value = String(P.pending !== null ? P.pending : P.i);
        $('d2-rpos').textContent = T('Turno ', 'Turn ') + dyn.turn + ' · ' + T('mossa ', 'move ') + P.i + ' / ' + P.last;
        const play = $('d2-rplay');
        const atEnd = P.i >= P.last && !P.playing;
        play.innerHTML = P.playing ? PAUSE_SVG : PLAY_SVG;
        play.setAttribute('aria-label', P.playing ? T('Pausa', 'Pause') : atEnd ? T('Rivedi dall\'inizio', 'Play from the start') : T('Riproduci', 'Play'));
    }
    async function rpPlay() {
        const P = S.rp;
        if (!P || P.playing) return;
        if (P.i >= P.last) rpGoto(0);
        const run = ++P.run;
        P.playing = true;
        rpUpdate();
        while (P.playing && run === P.run && P.i < P.last) {
            const step = P.steps[P.i + 1];
            S.animating = true;
            try {
                await playStep(step);
            } catch (e) {
                if (window.console) console.error(e);
            }
            S.animating = false;
            if (P.pending !== null) {
                const to = P.pending;
                P.pending = null;
                rpGoto(to);
                return;
            }
            P.i++;
            if (step.dyn) {
                S.latest = step.dyn;
                applyDyn(step.dyn);
            }
            rpUpdate();
            if (run !== P.run) break;
            await wait(380);
        }
        if (run !== P.run) return;
        P.playing = false;
        if (P.i >= P.last) rpEnd();
        rpUpdate();
    }
    function rpPause() {
        S.rp.playing = false;
        S.rp.run++;
        rpUpdate();
    }
    function rpGoto(i) {
        const P = S.rp;
        i = Math.max(0, Math.min(P.last, i));
        if (P.legacy && i !== 0) return; // senza stati intermedi si riparte solo dall'inizio
        P.playing = false;
        P.run++;
        if (S.animating) { P.pending = i; rpUpdate(); return; }
        P.i = i;
        $('d2-layer').innerHTML = '';
        stopMusic(120);
        S.latest = P.steps[i].dyn;
        applyDyn(P.steps[i].dyn);
        const mv = stepMove(P.steps[i]);
        if (mv) caption(esc(uName(mv.u) + T(' usa ', ' uses ') + moveName(mv.u, mv.m)), esc(T('Turno ', 'Turn ') + P.steps[i].dyn.turn));
        else caption(esc(playersLine()), esc(T('Inizio della partita', 'Start of the match')));
        if (i >= P.last) rpEnd();
        rpUpdate();
    }
    function rpEnd() {
        const dyn = S.rp.steps[S.rp.last].dyn;
        const w = dyn.winner;
        const pl = w === null || w === undefined ? null : (S.st.players[w] || S.st.players[String(w)] || {});
        const text = pl ? T('Vince ', 'Winner: ') + (pl.username || '?') : T('Pareggio', 'Draw');
        caption(esc(text), esc(T('Fine della partita · turno ', 'End of the match · turn ') + dyn.turn));
        say(text);
    }
    function replayKey(e) {
        if (!S.rp) return;
        const inField = e.target.closest && e.target.closest('input, textarea');
        if (inField && !(e.target.id === 'd2-rrange' && (e.key === ' ' || e.key === 'k'))) return; // la barra gestisce da sola frecce, Home e Fine
        if (e.key === ' ' || e.key === 'k') { e.preventDefault(); if (S.rp.playing) rpPause(); else rpPlay(); }
        else if (e.key === 'ArrowLeft') { e.preventDefault(); rpGoto(S.rp.i - 1); }
        else if (e.key === 'ArrowRight') { e.preventDefault(); rpGoto(S.rp.i + 1); }
        else if (e.key === 'Home') { e.preventDefault(); rpGoto(0); }
        else if (e.key === 'End') { e.preventDefault(); rpGoto(S.rp.last); }
        else if (e.key === 'Escape') {
            if (!$('d2-menu').hidden) $('d2-menu').hidden = true;
            else if ($('d2-drawer').classList.contains('d2-open')) openDrawer(false);
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
