/**
 * Cripsum™ — Rewind
 *
 * Motore delle schermate in formato storia e disegno dei contenuti.
 *
 * Il payload arriva già calcolato da /api/rewind/get.php: qui non si fanno
 * conti, si decide solo come mostrarli. Ogni schermata ha una sua palette,
 * i numeri salgono da zero e gli elementi entrano scaglionati.
 *
 * Tutto ciò che proviene dal database passa per esc(): nomi di personaggi,
 * titoli di post e nomi utente sono scritti dalle persone e non devono mai
 * finire nel DOM come markup.
 */
(() => {
    'use strict';

    const lang = (window.CRIPSUM_LANG === 'en') ? 'en' : 'it';
    const isPublic = !!window.CRIPSUM_REWIND_PUBLIC;
    const preloaded = window.CRIPSUM_REWIND_DATA || null;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const SLIDE_MS = 7000;

    // ─────────────────────────────────────────────────────────
    //  TESTI
    // ─────────────────────────────────────────────────────────

    const T = {
        it: {
            loading: 'Sto ripercorrendo il tuo anno...',
            error: 'Non riesco a caricare il tuo Rewind.',
            retry: 'Riprova',
            unavailable: 'Il Rewind non è ancora attivo su questo account.',
            hint: 'Tocca per continuare',
            hintKeys: 'Usa le frecce o clicca per continuare',

            introKicker: 'Cripsum Rewind',
            introTitle: 'Ecco com\'è andato<br>il tuo anno',
            introLead: n => `Sei con noi da <strong>${n}</strong> giorni. Vediamo cosa hai combinato.`,
            introStart: 'Comincia',

            timeKicker: 'Tempo passato qui',
            timeUnitH: 'ore',
            timeUnitM: 'minuti',
            timeLead: (h, d) => `Hai passato <strong>${h}</strong> su Cripsum, distribuite su <strong>${d}</strong> giorni.`,
            timeEstimate: 'Una parte viene dal vecchio contatore del browser, quindi è una stima generosa.',
            timeStreak: 'Streak record',
            timeSessions: 'Sessioni',
            timeLongest: 'Più lunga',
            timeDays: 'Giorni attivi',

            hoursKicker: 'Il tuo orario',
            hoursTitle: h => `Le tue ore migliori sono<br>attorno alle ${h}`,
            chrono: {
                nottambulo: 'Sei un <strong>nottambulo</strong>.',
                mattiniero: 'Sei un <strong>mattiniero</strong>.',
                diurno: 'Sei un tipo <strong>diurno</strong>.',
                serale: 'Sei un tipo <strong>serale</strong>.',
                equilibrato: 'Il tuo orario è <strong>equilibrato</strong>.'
            },
            hoursShare: (p, label) => `Il <strong>${p}%</strong> del tuo tempo se ne va ${label}.`,
            bandNight: 'tra mezzanotte e le 6', bandMorning: 'tra le 6 e mezzogiorno',
            bandDay: 'tra mezzogiorno e le 18', bandEvening: 'tra le 18 e mezzanotte',

            calKicker: 'Il tuo calendario',
            calTitle: n => `${n} giorni<br>con noi`,
            calLead: 'Ogni quadratino è un giorno. Più è chiaro, più ci sei stato.',

            pagesKicker: 'Dove sei stato',
            pagesTitle: 'Le tue zone preferite',
            pagesLead: name => `Il tuo posto è <strong>${name}</strong>.`,
            pageNames: {
                home: 'Home', profilo: 'Profili', rewind: 'Rewind', chat: 'Chat',
                'global-chat': 'Chat globale', inbox: 'Inbox', amici: 'Amici',
                lootbox: 'Lootbox', gacha: 'Shop gacha', negozio: 'Negozio',
                inventario: 'Inventario', achievements: 'Achievement', missions: 'Missioni',
                subway: 'Subway', game: 'Duelli', gambling: 'Gambling', goonland: 'GoonLand',
                shitpost: 'Shitpost', rimasti: 'Top Rimasti', cripsumpedia: 'CripsumPedia',
                edits: 'Edits', download: 'Download', tiktokpedia: 'TikTokPedia',
                merch: 'Merch', donazioni: 'Donazioni', impostazioni: 'Impostazioni', altro: 'Altro'
            },

            gachaKicker: 'Lootbox e gacha',
            gachaTitle: 'pull',
            gachaLead: n => `Hai aperto <strong>${n}</strong> lootbox quest'anno.`,
            gachaNew: 'Nuovi', gachaPity: 'Pity max', gacha5050: '50/50 vinti', gachaSpent: 'Godos spesi',

            bestKicker: 'Il colpo dell\'anno',
            bestLead: (name, pity) => `<strong>${name}</strong> arrivato con solo <strong>${pity}</strong> di pity.`,
            bestLeadNoPity: name => `<strong>${name}</strong>, il pezzo più raro che hai tirato.`,

            collKicker: 'La tua collezione',
            collTitle: 'personaggi',
            collLead: (owned, total, pct) => `Ne hai <strong>${owned}</strong> su <strong>${total}</strong>. Sei al <strong>${pct}%</strong>.`,
            collRarest: 'Il più raro', collDupes: n => `Ne hai ${n} copie`,

            chrKicker: 'Chi hai visto di più',
            chrTitle: 'Il tuo compagno fisso',
            chrMost: 'Più trovato', chrLeast: 'Meno trovato',
            chrTimes: n => n === 1 ? '1 volta' : `${n} volte`,
            chrLead: (name, n) => `<strong>${name}</strong> è uscito <strong>${n}</strong> volte. Ormai siete parenti.`,

            postKicker: 'I tuoi primati',
            postTitle: 'Il meglio che hai pubblicato',
            postLikes: 'Più apprezzato', postViews: 'Più visto', postComments: 'Più commentato',
            postLikesN: n => `${n} like`, postViewsN: n => `${n} visualizzazioni`, postCommentsN: n => `${n} commenti`,
            postViewsTotal: n => `In tutto i tuoi post hanno raccolto <strong>${n}</strong> visualizzazioni.`,

            audioToggle: 'Attiva o disattiva la musica',
            audioVolume: 'Volume',

            achKicker: 'Achievement',
            achTitle: 'sbloccati',
            achLead: p => `E <strong>${p}</strong> punti guadagnati.`,
            achRarest: pct => `Il tuo più raro ce l'ha solo il <strong>${pct}%</strong> degli utenti.`,

            misKicker: 'Missioni',
            misTitle: 'completate',
            misLead: p => `Ne hai riscattate il <strong>${p}%</strong>.`,
            misForgot: 'Qualche ricompensa te la sei dimenticata.',

            socKicker: 'Social',
            socTitle: 'messaggi',
            socLead: 'Ecco quanto hai parlato.',
            socGlobal: 'Chat globale', socPrivate: 'Privati', socGroup: 'Gruppi', socFriends: 'Amici',
            socPartner: name => `Con <strong>${name}</strong> più che con chiunque altro.`,
            socBusiest: (d, n) => `Il ${d} ne hai mandati ${n} in un giorno solo.`,

            proKicker: 'Il tuo profilo',
            proTitle: 'visite',
            proLead: n => `E l'hai ritoccato <strong>${n}</strong> volte.`,
            proNeverHappy: 'Non sei mai contento del risultato, eh?',

            gamKicker: 'Giochi',
            gamTitle: 'duelli',
            gamWin: 'Vinti', gamLoss: 'Persi', gamRate: 'Winrate',
            gamSubway: (s, rank) => `Subway: <strong>${s}s</strong>, posizione <strong>#${rank}</strong>.`,

            conKicker: 'Contenuti',
            conTitle: 'like ricevuti',
            conLead: (p, c) => `Su <strong>${p}</strong> post e <strong>${c}</strong> commenti.`,
            conBest: (title, n) => `Il migliore: "${title}" con ${n} like.`,

            ecoKicker: 'Economia',
            ecoTitle: 'Godos spesi',
            ecoEarned: 'Guadagnati', ecoSpent: 'Spesi', ecoBalance: 'Saldo',

            busyKicker: 'Il giorno più intenso',
            busyLead: (d, m, a) => `Il <strong>${d}</strong>: ${m} minuti e ${a} azioni.`,

            perKicker: 'E quindi tu sei...',
            rankTop: (p, n) => `Sei nel <strong>top ${p}%</strong> degli utenti più attivi, su ${n}.`,

            sumKicker: 'Il tuo anno in breve',
            sumTitle: 'Ecco tutto',
            share: 'Condividi',
            shareOn: 'Link attivo',
            replay: 'Rivedi',
            copied: 'Link copiato!',
            shareOff: 'Link disattivato',
            shareErr: 'Non è riuscito, riprova.',
            close: 'Chiudi',
            back: 'Torna al sito'
        },
        en: {
            loading: 'Replaying your year...',
            error: 'I could not load your Rewind.',
            retry: 'Try again',
            unavailable: 'Rewind is not active on this account yet.',
            hint: 'Tap to continue',
            hintKeys: 'Use the arrows or click to continue',

            introKicker: 'Cripsum Rewind',
            introTitle: 'Here is how<br>your year went',
            introLead: n => `You have been with us for <strong>${n}</strong> days. Let us see what you did.`,
            introStart: 'Start',

            timeKicker: 'Time spent here',
            timeUnitH: 'hours',
            timeUnitM: 'minutes',
            timeLead: (h, d) => `You spent <strong>${h}</strong> on Cripsum, across <strong>${d}</strong> days.`,
            timeEstimate: 'Part of this comes from the old browser counter, so it is a generous estimate.',
            timeStreak: 'Best streak',
            timeSessions: 'Sessions',
            timeLongest: 'Longest',
            timeDays: 'Active days',

            hoursKicker: 'Your schedule',
            hoursTitle: h => `Your best hours are<br>around ${h}`,
            chrono: {
                nottambulo: 'You are a <strong>night owl</strong>.',
                mattiniero: 'You are an <strong>early bird</strong>.',
                diurno: 'You are a <strong>daytime</strong> type.',
                serale: 'You are an <strong>evening</strong> type.',
                equilibrato: 'Your schedule is <strong>balanced</strong>.'
            },
            hoursShare: (p, label) => `<strong>${p}%</strong> of your time goes ${label}.`,
            bandNight: 'between midnight and 6am', bandMorning: 'between 6am and noon',
            bandDay: 'between noon and 6pm', bandEvening: 'between 6pm and midnight',

            calKicker: 'Your calendar',
            calTitle: n => `${n} days<br>with us`,
            calLead: 'Every square is a day. The brighter it is, the more you were around.',

            pagesKicker: 'Where you went',
            pagesTitle: 'Your favourite corners',
            pagesLead: name => `Your spot is <strong>${name}</strong>.`,
            pageNames: {
                home: 'Home', profilo: 'Profiles', rewind: 'Rewind', chat: 'Chat',
                'global-chat': 'Global chat', inbox: 'Inbox', amici: 'Friends',
                lootbox: 'Lootbox', gacha: 'Gacha shop', negozio: 'Store',
                inventario: 'Inventory', achievements: 'Achievements', missions: 'Missions',
                subway: 'Subway', game: 'Duels', gambling: 'Gambling', goonland: 'GoonLand',
                shitpost: 'Shitpost', rimasti: 'Top Rimasti', cripsumpedia: 'CripsumPedia',
                edits: 'Edits', download: 'Downloads', tiktokpedia: 'TikTokPedia',
                merch: 'Merch', donazioni: 'Donations', impostazioni: 'Settings', altro: 'Other'
            },

            gachaKicker: 'Lootboxes and gacha',
            gachaTitle: 'pulls',
            gachaLead: n => `You opened <strong>${n}</strong> lootboxes this year.`,
            gachaNew: 'New', gachaPity: 'Max pity', gacha5050: '50/50 won', gachaSpent: 'Godos spent',

            bestKicker: 'Pull of the year',
            bestLead: (name, pity) => `<strong>${name}</strong> landed at only <strong>${pity}</strong> pity.`,
            bestLeadNoPity: name => `<strong>${name}</strong>, the rarest thing you pulled.`,

            collKicker: 'Your collection',
            collTitle: 'characters',
            collLead: (owned, total, pct) => `You own <strong>${owned}</strong> of <strong>${total}</strong>. That is <strong>${pct}%</strong>.`,
            collRarest: 'The rarest', collDupes: n => `You have ${n} copies`,

            chrKicker: 'Who you saw the most',
            chrTitle: 'Your constant companion',
            chrMost: 'Most pulled', chrLeast: 'Least pulled',
            chrTimes: n => n === 1 ? 'once' : `${n} times`,
            chrLead: (name, n) => `<strong>${name}</strong> showed up <strong>${n}</strong> times. You are practically related.`,

            postKicker: 'Your records',
            postTitle: 'The best you posted',
            postLikes: 'Most liked', postViews: 'Most viewed', postComments: 'Most commented',
            postLikesN: n => `${n} likes`, postViewsN: n => `${n} views`, postCommentsN: n => `${n} comments`,
            postViewsTotal: n => `Your posts collected <strong>${n}</strong> views in total.`,

            audioToggle: 'Turn the music on or off',
            audioVolume: 'Volume',

            achKicker: 'Achievements',
            achTitle: 'unlocked',
            achLead: p => `And <strong>${p}</strong> points earned.`,
            achRarest: pct => `Only <strong>${pct}%</strong> of users have your rarest one.`,

            misKicker: 'Missions',
            misTitle: 'completed',
            misLead: p => `You claimed <strong>${p}%</strong> of them.`,
            misForgot: 'You forgot a few rewards along the way.',

            socKicker: 'Social',
            socTitle: 'messages',
            socLead: 'Here is how much you talked.',
            socGlobal: 'Global chat', socPrivate: 'Private', socGroup: 'Groups', socFriends: 'Friends',
            socPartner: name => `With <strong>${name}</strong> more than anyone else.`,
            socBusiest: (d, n) => `On ${d} you sent ${n} in a single day.`,

            proKicker: 'Your profile',
            proTitle: 'views',
            proLead: n => `And you tweaked it <strong>${n}</strong> times.`,
            proNeverHappy: 'Never quite happy with it, are you?',

            gamKicker: 'Games',
            gamTitle: 'duels',
            gamWin: 'Won', gamLoss: 'Lost', gamRate: 'Winrate',
            gamSubway: (s, rank) => `Subway: <strong>${s}s</strong>, rank <strong>#${rank}</strong>.`,

            conKicker: 'Content',
            conTitle: 'likes received',
            conLead: (p, c) => `Across <strong>${p}</strong> posts and <strong>${c}</strong> comments.`,
            conBest: (title, n) => `The best one: "${title}" with ${n} likes.`,

            ecoKicker: 'Economy',
            ecoTitle: 'Godos spent',
            ecoEarned: 'Earned', ecoSpent: 'Spent', ecoBalance: 'Balance',

            busyKicker: 'Your busiest day',
            busyLead: (d, m, a) => `On <strong>${d}</strong>: ${m} minutes and ${a} actions.`,

            perKicker: 'And so you are...',
            rankTop: (p, n) => `You are in the <strong>top ${p}%</strong> of the most active users, out of ${n}.`,

            sumKicker: 'Your year at a glance',
            sumTitle: 'That is all',
            share: 'Share',
            shareOn: 'Link is live',
            replay: 'Replay',
            copied: 'Link copied!',
            shareOff: 'Link disabled',
            shareErr: 'That did not work, try again.',
            close: 'Close',
            back: 'Back to the site'
        }
    }[lang];

    // Palette per schermata: [colore principale, colore secondario].
    const THEMES = {
        intro:       ['#2f6bff', '#0b2a6b'],
        time:        ['#7c5cff', '#2b1a5e'],
        hours:       ['#4c1d95', '#0b1020'],
        calendar:    ['#0ea5e9', '#083344'],
        pages:       ['#06b6d4', '#083344'],
        gacha:       ['#fbbf24', '#5c3a00'],
        best_pull:   ['#f59e0b', '#4a1d00'],
        collection:  ['#a78bfa', '#3b1e75'],
        characters:  ['#c084fc', '#4c1d95'],
        achievements:['#f472b6', '#5c1140'],
        missions:    ['#34d399', '#064e3b'],
        social:      ['#22d3ee', '#083344'],
        profile:     ['#38bdf8', '#0c4a6e'],
        games:       ['#f87171', '#5c1414'],
        content:     ['#fb923c', '#5c2a00'],
        top_post:    ['#f97316', '#431407'],
        economy:     ['#facc15', '#4a3a00'],
        busiest_day: ['#8b5cf6', '#2e1065'],
        persona:     ['#2f6bff', '#0b2a6b'],
        summary:     ['#1e293b', '#05070d']
    };

    // ─────────────────────────────────────────────────────────
    //  UTILITÀ
    // ─────────────────────────────────────────────────────────

    /** Neutralizza il markup nei dati scritti dagli utenti. */
    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[ch]));
    }

    function num(value) {
        return Number(value || 0).toLocaleString(lang === 'en' ? 'en-US' : 'it-IT');
    }

    function formatDate(iso) {
        if (!iso) return '';
        const date = new Date(String(iso).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return String(iso).slice(0, 10);
        return date.toLocaleDateString(lang === 'en' ? 'en-GB' : 'it-IT', {
            day: 'numeric', month: 'long'
        });
    }

    function humanDuration(seconds) {
        const hours = Math.round(Number(seconds || 0) / 3600);
        if (hours >= 1) return `${num(hours)} ${T.timeUnitH}`;
        return `${num(Math.round(Number(seconds || 0) / 60))} ${T.timeUnitM}`;
    }

    function el(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        return wrap.firstElementChild;
    }

    /** Marca gli elementi in ordine, così l'entrata è scaglionata. */
    function stagger(root) {
        root.querySelectorAll('.rw-in').forEach((node, index) => {
            node.style.setProperty('--rw-i', String(index));
        });
    }

    // ─────────────────────────────────────────────────────────
    //  MUSICA
    //
    //  Dieci tracce in /audio/rewind, mescolate a ogni apertura e
    //  suonate in sequenza. I browser bloccano la riproduzione finché
    //  non c'è un gesto dell'utente, quindi si tenta subito e, se viene
    //  rifiutata, si riprova al primo tocco o tasto premuto.
    // ─────────────────────────────────────────────────────────

    const AUDIO_TRACKS = 10;
    const AUDIO_DIR = '/audio/rewind/';
    const AUDIO_STORE = 'cripsum.rewind.volume';
    const AUDIO_DEFAULT_VOLUME = 0.45;

    class Soundtrack {
        constructor() {
            this.order = this.shuffle();
            this.position = 0;
            this.muted = false;
            this.started = false;
            this.failed = false;
            this.volume = this.readVolume();

            this.audio = new Audio();
            this.audio.preload = 'auto';
            this.audio.volume = 0;

            // A fine traccia si passa alla successiva; esaurita la lista si
            // rimescola, così due ascolti di fila non sono mai identici.
            this.audio.addEventListener('ended', () => {
                this.position += 1;
                if (this.position >= this.order.length) {
                    this.order = this.shuffle();
                    this.position = 0;
                }
                this.load();
                this.play();
            });

            // Un file mancante non deve interrompere la musica: si salta.
            this.audio.addEventListener('error', () => {
                if (this.position < this.order.length - 1) {
                    this.position += 1;
                    this.load();
                    this.play();
                } else {
                    this.failed = true;
                }
            });

            this.load();
        }

        shuffle() {
            const list = Array.from({ length: AUDIO_TRACKS }, (_, i) => i + 1);
            for (let i = list.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [list[i], list[j]] = [list[j], list[i]];
            }
            return list;
        }

        readVolume() {
            try {
                const stored = parseFloat(localStorage.getItem(AUDIO_STORE));
                if (Number.isFinite(stored) && stored >= 0 && stored <= 1) return stored;
            } catch (_) { /* private browsing */ }
            return AUDIO_DEFAULT_VOLUME;
        }

        saveVolume() {
            try { localStorage.setItem(AUDIO_STORE, String(this.volume)); } catch (_) {}
        }

        load() {
            this.audio.src = `${AUDIO_DIR}${this.order[this.position]}.mp3`;
        }

        /** Sale gradualmente al volume scelto: entrare a piena potenza stona. */
        fadeTo(target, ms = 900) {
            const from = this.audio.volume;
            const started = performance.now();
            const step = now => {
                const p = Math.min(1, (now - started) / ms);
                this.audio.volume = from + (target - from) * p;
                if (p < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        }

        play() {
            const promise = this.audio.play();
            if (promise && typeof promise.catch === 'function') {
                promise
                    .then(() => {
                        this.started = true;
                        this.fadeTo(this.muted ? 0 : this.volume);
                        this.onState?.();
                    })
                    .catch(() => { this.started = false; this.onState?.(); });
            }
        }

        toggle() {
            if (!this.started) { this.play(); return; }
            this.muted = !this.muted;
            this.fadeTo(this.muted ? 0 : this.volume, 260);
            this.onState?.();
        }

        setVolume(value) {
            this.volume = Math.max(0, Math.min(1, value));
            this.muted = this.volume === 0;
            this.audio.volume = this.volume;
            this.saveVolume();
            if (!this.started) this.play();
            this.onState?.();
        }

        get isPlaying() {
            return this.started && !this.muted && !this.audio.paused;
        }
    }

    /** Costruisce il controllo audio e lo collega alla colonna sonora. */
    function mountAudioControl(container, track) {
        const node = el(`
            <div class="rw-audio" data-rw-audio>
                <button type="button" class="rw-audio__toggle" data-rw-audio-toggle
                        aria-label="${esc(T.audioToggle)}">
                    <i class="fa-solid fa-music"></i>
                </button>
                <span class="rw-audio__viz" aria-hidden="true"><span></span><span></span><span></span></span>
                <input type="range" class="rw-audio__slider" data-rw-audio-volume
                       min="0" max="1" step="0.01" value="${track.volume}"
                       aria-label="${esc(T.audioVolume)}">
            </div>`);

        container.prepend(node);

        const toggle = node.querySelector('[data-rw-audio-toggle]');
        const slider = node.querySelector('[data-rw-audio-volume]');
        const icon = toggle.querySelector('i');

        const paint = () => {
            const pct = Math.round(track.volume * 100);
            slider.style.setProperty('--rw-vol', pct + '%');
            node.classList.toggle('is-playing', track.isPlaying);
            icon.className = track.isPlaying
                ? 'fa-solid fa-music'
                : (track.started ? 'fa-solid fa-volume-xmark' : 'fa-solid fa-play');
        };

        track.onState = paint;
        paint();

        toggle.addEventListener('click', event => {
            event.stopPropagation();
            track.toggle();
        });

        slider.addEventListener('input', event => {
            event.stopPropagation();
            track.setVolume(parseFloat(event.target.value));
        });

        // Il pannello si apre al passaggio del mouse; su touch lo apre il
        // primo tocco sull'icona, senza rubare spazio al racconto.
        node.addEventListener('pointerenter', () => node.classList.add('is-open'));
        node.addEventListener('pointerleave', () => node.classList.remove('is-open'));
        toggle.addEventListener('focus', () => node.classList.add('is-open'));
        node.addEventListener('focusout', () => {
            if (!node.contains(document.activeElement)) node.classList.remove('is-open');
        });

        // I controlli non devono far avanzare la storia né metterla in pausa.
        ['pointerdown', 'pointerup', 'click'].forEach(evt => {
            node.addEventListener(evt, e => e.stopPropagation());
        });

        return paint;
    }

    // ─────────────────────────────────────────────────────────
    //  DISEGNO DELLE SCHERMATE
    // ─────────────────────────────────────────────────────────

    const RENDER = {
        intro(d) {
            const days = d.user?.days_on_site || 0;
            return `
                <p class="rw-kicker rw-in">${esc(T.introKicker)}</p>
                <h1 class="rw-title rw-in">${T.introTitle}</h1>
                <p class="rw-lead rw-in">${T.introLead(num(days))}</p>
                <div class="rw-actions rw-in">
                    <button type="button" class="rw-btn" data-rw-next>
                        ${esc(T.introStart)} <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>`;
        },

        time(d) {
            const t = d.time || {};
            const value = t.hours >= 1 ? t.hours : Math.round((t.seconds || 0) / 60);
            const unit = t.hours >= 1 ? T.timeUnitH : T.timeUnitM;

            return `
                <p class="rw-kicker rw-in">${esc(T.timeKicker)}</p>
                <p class="rw-big rw-in" data-count="${value}">0<small>${esc(unit)}</small></p>
                <p class="rw-lead rw-in">${T.timeLead(humanDuration(t.seconds), num(t.days_active))}</p>
                <div class="rw-stats rw-in">
                    ${stat(t.longest_streak, T.timeStreak)}
                    ${stat(t.days_active, T.timeDays)}
                    ${stat(t.sessions, T.timeSessions)}
                    ${stat(Math.round((t.longest_session || 0) / 60) + ' min', T.timeLongest)}
                </div>
                ${t.is_estimated ? `<p class="rw-note rw-in">${esc(T.timeEstimate)}</p>` : ''}`;
        },

        hours(d) {
            const h = d.hours || {};
            const buckets = h.buckets || [];
            const max = Math.max(1, ...buckets);
            const peak = String(h.peak_hour ?? 0).padStart(2, '0') + ':00';

            const bands = [
                [h.share_night, T.bandNight],
                [h.share_morning, T.bandMorning],
                [h.share_day, T.bandDay],
                [h.share_evening, T.bandEvening]
            ].sort((a, b) => b[0] - a[0])[0];

            const bars = buckets.map((value, hour) => {
                const pct = Math.max(3, Math.round(value / max * 100));
                const isPeak = hour === h.peak_hour ? ' is-peak' : '';
                return `<span class="rw-clock__bar${isPeak}" data-h="${pct}"></span>`;
            }).join('');

            return `
                <p class="rw-kicker rw-in">${esc(T.hoursKicker)}</p>
                <h2 class="rw-title rw-in">${T.hoursTitle(peak)}</h2>
                <div class="rw-clock rw-in">${bars}</div>
                <div class="rw-clock__axis rw-in"><span>00</span><span>06</span><span>12</span><span>18</span><span>23</span></div>
                <p class="rw-lead rw-in" style="margin-top:18px">
                    ${T.chrono[h.chronotype] || T.chrono.equilibrato}
                    ${bands && bands[0] > 0 ? ' ' + T.hoursShare(bands[0], bands[1]) : ''}
                </p>`;
        },

        calendar(d) {
            const cal = d.calendar || {};
            const days = cal.days || {};
            const entries = Object.entries(days);
            const maxSeconds = Math.max(1, ...entries.map(([, v]) => v.seconds || 0));

            // La griglia copre l'intero periodo, non solo i giorni presenti:
            // i buchi sono l'informazione più leggibile di una heatmap.
            const start = new Date(d.period.start + 'T00:00:00');
            const end = new Date(d.period.end + 'T00:00:00');
            const cells = [];

            for (let cursor = new Date(start); cursor <= end; cursor.setDate(cursor.getDate() + 1)) {
                const key = cursor.toISOString().slice(0, 10);
                const entry = days[key];
                let level = 0;
                if (entry) {
                    const ratio = (entry.seconds || 0) / maxSeconds;
                    level = entry.seconds === 0 ? 1 : Math.min(4, Math.max(1, Math.ceil(ratio * 4)));
                }
                cells.push(`<span class="rw-heat__cell" data-level="${level}" title="${key}"></span>`);
            }

            return `
                <p class="rw-kicker rw-in">${esc(T.calKicker)}</p>
                <h2 class="rw-title rw-in">${T.calTitle(num(cal.total_days))}</h2>
                <div class="rw-heat rw-in">${cells.join('')}</div>
                <p class="rw-note rw-in">${esc(T.calLead)}</p>`;
        },

        pages(d) {
            const top = (d.pages?.top || []).slice(0, 5);
            if (!top.length) return '';
            const max = Math.max(1, ...top.map(p => p.seconds));

            const bars = top.map(page => {
                const name = T.pageNames[page.key] || page.key;
                return `
                    <div class="rw-in">
                        <div class="rw-bar__head">
                            <span class="rw-bar__name">${esc(name)}</span>
                            <span class="rw-bar__value">${esc(humanDuration(page.seconds))}</span>
                        </div>
                        <span class="rw-bar__track">
                            <span class="rw-bar__fill" data-w="${Math.round(page.seconds / max * 100)}"></span>
                        </span>
                    </div>`;
            }).join('');

            return `
                <p class="rw-kicker rw-in">${esc(T.pagesKicker)}</p>
                <h2 class="rw-title rw-in">${esc(T.pagesTitle)}</h2>
                <p class="rw-lead rw-in">${T.pagesLead(esc(T.pageNames[top[0].key] || top[0].key))}</p>
                <div class="rw-bars">${bars}</div>`;
        },

        gacha(d) {
            const g = d.gacha || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.gachaKicker)}</p>
                <p class="rw-big rw-in" data-count="${g.pulls || 0}">0<small>${esc(T.gachaTitle)}</small></p>
                <p class="rw-lead rw-in">${T.gachaLead(num(g.pulls))}</p>
                <div class="rw-stats rw-in">
                    ${stat(g.new_chars, T.gachaNew)}
                    ${stat(g.max_pity, T.gachaPity)}
                    ${g.rate_5050 !== null && g.rate_5050 !== undefined ? stat(g.rate_5050 + '%', T.gacha5050) : ''}
                    ${g.godos_spent ? stat(num(g.godos_spent), T.gachaSpent) : ''}
                </div>`;
        },

        best_pull(d) {
            const p = d.gacha?.best_pull;
            if (!p) return '';
            const img = p.img_url ? `<img class="rw-hero__img" src="${esc(p.img_url)}" alt="${esc(p.nome)}" loading="eager">` : '';
            const pity = Number(p.pity_al_momento || 0);

            return `
                <p class="rw-kicker rw-in">${esc(T.bestKicker)}</p>
                <div class="rw-hero rw-in">
                    ${img}
                    <span class="rw-chip rw-chip--gold">${esc(p.rarita || '')}</span>
                </div>
                <h2 class="rw-title rw-in" style="font-size:clamp(1.4rem,5.4vw,2rem)">${esc(p.nome)}</h2>
                <p class="rw-lead rw-in">${pity > 0 ? T.bestLead(esc(p.nome), pity) : T.bestLeadNoPity(esc(p.nome))}</p>
                <p class="rw-note rw-in">${esc(formatDate(p.created_at))}</p>`;
        },

        collection(d) {
            const c = d.collection || {};
            const rarest = c.rarest;
            return `
                <p class="rw-kicker rw-in">${esc(T.collKicker)}</p>
                <p class="rw-big rw-in" data-count="${c.owned || 0}">0<small>${esc(T.collTitle)}</small></p>
                <p class="rw-lead rw-in">${T.collLead(num(c.owned), num(c.catalogue), c.completion)}</p>
                ${rarest ? `
                    <div class="rw-card rw-in" style="margin-top:18px">
                        <p class="rw-kicker" style="margin-bottom:8px">${esc(T.collRarest)}</p>
                        <strong style="font-size:1.1rem">${esc(rarest.nome)}</strong>
                        <span class="rw-chip rw-chip--purple" style="margin-left:8px">${esc(rarest.rarita || '')}</span>
                    </div>` : ''}
                ${c.most_duplicated ? `<p class="rw-note rw-in">${esc(c.most_duplicated.nome)} — ${esc(T.collDupes(c.most_duplicated.quantita))}</p>` : ''}`;
        },

        characters(d) {
            const c = d.collection || {};
            const most = c.most_pulled;
            const least = c.least_pulled;
            if (!most || !least) return '';

            const side = (entry, tag) => `
                <div class="rw-duo__side">
                    <span class="rw-duo__tag">${esc(tag)}</span>
                    ${entry.img_url ? `<img class="rw-duo__img" src="${esc(entry.img_url)}" alt="${esc(entry.nome)}" loading="lazy">` : ''}
                    <span class="rw-duo__name">${esc(entry.nome)}</span>
                    <span class="rw-duo__count">${esc(T.chrTimes(Number(entry.pulls || 0)))}</span>
                </div>`;

            return `
                <p class="rw-kicker rw-in">${esc(T.chrKicker)}</p>
                <h2 class="rw-title rw-in">${esc(T.chrTitle)}</h2>
                <div class="rw-duo rw-in">
                    ${side(most, T.chrMost)}
                    ${side(least, T.chrLeast)}
                </div>
                <p class="rw-lead rw-in" style="margin-top:18px">${T.chrLead(esc(most.nome), num(most.pulls))}</p>`;
        },

        top_post(d) {
            const c = d.content || {};
            const rows = [];

            if (c.best_post) {
                rows.push(['fa-solid fa-heart', T.postLikes, c.best_post.titolo, T.postLikesN(num(c.best_post.likes))]);
            }
            if (c.most_viewed) {
                rows.push(['fa-solid fa-eye', T.postViews, c.most_viewed.titolo, T.postViewsN(num(c.most_viewed.views))]);
            }
            if (c.most_commented) {
                rows.push(['fa-solid fa-comment', T.postComments, c.most_commented.titolo, T.postCommentsN(num(c.most_commented.comments))]);
            }
            if (!rows.length) return '';

            const list = rows.map(([icon, label, title, meta]) => `
                <div class="rw-record rw-in">
                    <span class="rw-record__icon"><i class="${esc(icon)}"></i></span>
                    <span class="rw-record__body">
                        <span class="rw-record__label">${esc(label)}</span>
                        <span class="rw-record__title">${esc(title || '—')}</span>
                        <span class="rw-record__meta">${esc(meta)}</span>
                    </span>
                </div>`).join('');

            return `
                <p class="rw-kicker rw-in">${esc(T.postKicker)}</p>
                <h2 class="rw-title rw-in">${esc(T.postTitle)}</h2>
                <div class="rw-records">${list}</div>
                ${c.views_received ? `<p class="rw-note rw-in">${T.postViewsTotal(num(c.views_received))}</p>` : ''}`;
        },

        achievements(d) {
            const a = d.achievements || {};
            const rarest = a.rarest;
            return `
                <p class="rw-kicker rw-in">${esc(T.achKicker)}</p>
                <p class="rw-big rw-in" data-count="${a.unlocked_in_period || 0}">0<small>${esc(T.achTitle)}</small></p>
                <p class="rw-lead rw-in">${T.achLead(num(a.points_in_period))}</p>
                ${rarest ? `
                    <div class="rw-card rw-in" style="margin-top:18px">
                        <strong style="font-size:1.05rem">${esc(lang === 'en' ? (rarest.nome_en || rarest.nome) : rarest.nome)}</strong>
                        ${rarest.owners_pct !== undefined ? `<p class="rw-note" style="margin-top:8px">${T.achRarest(rarest.owners_pct)}</p>` : ''}
                    </div>` : ''}`;
        },

        missions(d) {
            const m = d.missions || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.misKicker)}</p>
                <p class="rw-big rw-in" data-count="${m.completed || 0}">0<small>${esc(T.misTitle)}</small></p>
                <p class="rw-lead rw-in">${T.misLead(m.claim_rate)}</p>
                ${m.claim_rate < 90 ? `<p class="rw-note rw-in">${esc(T.misForgot)}</p>` : ''}`;
        },

        social(d) {
            const s = d.social || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.socKicker)}</p>
                <p class="rw-big rw-in" data-count="${s.msg_total || 0}">0<small>${esc(T.socTitle)}</small></p>
                <div class="rw-stats rw-in">
                    ${stat(num(s.msg_global), T.socGlobal)}
                    ${stat(num(s.msg_private), T.socPrivate)}
                    ${stat(num(s.msg_group), T.socGroup)}
                    ${stat(num(s.friends_total), T.socFriends)}
                </div>
                ${s.top_partner ? `<p class="rw-lead rw-in" style="margin-top:18px">${T.socPartner(esc(s.top_partner.display_name))}</p>` : ''}
                ${s.busiest_day ? `<p class="rw-note rw-in">${esc(T.socBusiest(formatDate(s.busiest_day.giorno), s.busiest_day.n))}</p>` : ''}`;
        },

        profile(d) {
            const p = d.profile || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.proKicker)}</p>
                <p class="rw-big rw-in" data-count="${p.views_lifetime || 0}">0<small>${esc(T.proTitle)}</small></p>
                <p class="rw-lead rw-in">${T.proLead(num(p.changes))}</p>
                ${p.changes > 20 ? `<p class="rw-note rw-in">${esc(T.proNeverHappy)}</p>` : ''}`;
        },

        games(d) {
            const g = d.games || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.gamKicker)}</p>
                <p class="rw-big rw-in" data-count="${g.duels_played || 0}">0<small>${esc(T.gamTitle)}</small></p>
                <div class="rw-stats rw-in">
                    ${stat(num(g.duels_won), T.gamWin)}
                    ${stat(num(g.duels_lost), T.gamLoss)}
                    ${g.winrate !== null && g.winrate !== undefined ? stat(g.winrate + '%', T.gamRate) : ''}
                </div>
                ${g.subway_best_ms ? `<p class="rw-lead rw-in" style="margin-top:18px">${T.gamSubway((g.subway_best_ms / 1000).toFixed(1), g.subway_rank ?? '?')}</p>` : ''}`;
        },

        content(d) {
            const c = d.content || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.conKicker)}</p>
                <p class="rw-big rw-in" data-count="${c.likes_received || 0}">0<small>${esc(T.conTitle)}</small></p>
                <p class="rw-lead rw-in">${T.conLead(num(c.shitposts), num(c.comments))}</p>
                ${c.best_post ? `<p class="rw-note rw-in">${esc(T.conBest(c.best_post.titolo || '', c.best_post.likes))}</p>` : ''}`;
        },

        economy(d) {
            const e = d.economy || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.ecoKicker)}</p>
                <p class="rw-big rw-in" data-count="${e.spent || 0}">0<small>${esc(T.ecoTitle)}</small></p>
                <div class="rw-stats rw-in">
                    ${stat(num(e.earned), T.ecoEarned)}
                    ${stat(num(e.spent), T.ecoSpent)}
                    ${e.balance !== undefined ? stat(num(e.balance), T.ecoBalance) : ''}
                </div>`;
        },

        busiest_day(d) {
            const b = d.calendar?.busiest_day;
            if (!b) return '';
            return `
                <p class="rw-kicker rw-in">${esc(T.busyKicker)}</p>
                <h2 class="rw-title rw-in">${esc(formatDate(b.date))}</h2>
                <p class="rw-lead rw-in">${T.busyLead(esc(formatDate(b.date)), Math.round(b.seconds / 60), b.actions)}</p>`;
        },

        persona(d) {
            const p = d.persona || {};
            const rank = d.ranking || {};
            const name = lang === 'en' ? p.name_en : p.name_it;
            const desc = lang === 'en' ? p.desc_en : p.desc_it;

            return `
                <p class="rw-kicker rw-in">${esc(T.perKicker)}</p>
                <div class="rw-persona__icon rw-in"><i class="${esc(p.icon || 'fa-solid fa-star')}"></i></div>
                <h2 class="rw-persona__name rw-in">${esc(name || '')}</h2>
                <p class="rw-lead rw-in">${esc(desc || '')}</p>
                ${rank.has_data ? `<p class="rw-note rw-in">${T.rankTop(rank.top_percent, num(rank.total_users))}</p>` : ''}`;
        },

        summary(d) {
            const t = d.time || {};
            const g = d.gacha || {};
            const s = d.social || {};
            const a = d.achievements || {};
            const p = d.persona || {};

            const shareBtn = isPublic ? '' : `
                <button type="button" class="rw-btn" data-rw-share>
                    <i class="fa-solid fa-link"></i> <span data-rw-share-label>${esc(T.share)}</span>
                </button>`;

            return `
                <p class="rw-kicker rw-in">${esc(T.sumKicker)}</p>
                <h2 class="rw-title rw-in">${esc(p[lang === 'en' ? 'name_en' : 'name_it'] || T.sumTitle)}</h2>
                <div class="rw-summary rw-in">
                    <div class="rw-summary__item">
                        <div class="rw-summary__value">${esc(humanDuration(t.seconds))}</div>
                        <div class="rw-summary__label">${esc(T.timeKicker)}</div>
                    </div>
                    <div class="rw-summary__item">
                        <div class="rw-summary__value">${num(t.days_active)}</div>
                        <div class="rw-summary__label">${esc(T.timeDays)}</div>
                    </div>
                    <div class="rw-summary__item">
                        <div class="rw-summary__value">${num(g.pulls)}</div>
                        <div class="rw-summary__label">${esc(T.gachaTitle)}</div>
                    </div>
                    <div class="rw-summary__item">
                        <div class="rw-summary__value">${num(s.msg_total)}</div>
                        <div class="rw-summary__label">${esc(T.socTitle)}</div>
                    </div>
                    <div class="rw-summary__item">
                        <div class="rw-summary__value">${num(a.unlocked_in_period)}</div>
                        <div class="rw-summary__label">${esc(T.achKicker)}</div>
                    </div>
                    <div class="rw-summary__item">
                        <div class="rw-summary__value">${num(t.longest_streak)}</div>
                        <div class="rw-summary__label">${esc(T.timeStreak)}</div>
                    </div>
                </div>
                <div class="rw-actions rw-in">
                    ${shareBtn}
                    <button type="button" class="rw-btn rw-btn--ghost" data-rw-replay>
                        <i class="fa-solid fa-rotate-left"></i> ${esc(T.replay)}
                    </button>
                    <a class="rw-btn rw-btn--ghost" href="/${lang}/home">
                        <i class="fa-solid fa-house"></i> ${esc(T.back)}
                    </a>
                </div>`;
        }
    };

    function stat(value, label) {
        return `<div class="rw-stat">
            <span class="rw-stat__value">${esc(value ?? 0)}</span>
            <span class="rw-stat__label">${esc(label)}</span>
        </div>`;
    }

    // ─────────────────────────────────────────────────────────
    //  MOTORE
    // ─────────────────────────────────────────────────────────

    class Story {
        constructor(data, root) {
            this.data = data;
            this.root = root;
            this.index = 0;
            this.paused = false;
            this.timer = null;
            this.startedAt = 0;
            this.remaining = SLIDE_MS;

            // Le schermate senza contenuto vengono scartate qui: il piano
            // arriva dal server, ma un renderer può comunque restituire
            // stringa vuota se un campo atteso manca.
            this.slides = (data.slides || ['intro', 'summary'])
                .map(name => ({ name, html: (RENDER[name] || (() => ''))(data) }))
                .filter(slide => slide.html !== '');

            this.build();
            this.mountAudio();
            this.bind();
            this.go(0);
        }

        /**
         * Avvia la colonna sonora.
         *
         * Il primo tentativo parte subito e quasi sempre viene rifiutato dal
         * browser: senza un gesto dell'utente la riproduzione automatica è
         * bloccata. Per questo restiamo in ascolto del primo tocco o della
         * prima pressione di un tasto e riproviamo allora, una volta sola.
         */
        mountAudio() {
            const slot = this.root.querySelector('[data-rw-audio-slot]');
            if (!slot) return;

            this.track = new Soundtrack();
            this.repaintAudio = mountAudioControl(slot, this.track);
            this.track.play();

            const kickstart = () => {
                if (!this.track.started && !this.track.failed) this.track.play();
                document.removeEventListener('pointerdown', kickstart);
                document.removeEventListener('keydown', kickstart);
            };
            document.addEventListener('pointerdown', kickstart, { once: false });
            document.addEventListener('keydown', kickstart, { once: false });

            // Uscendo dalla scheda la musica si ferma; tornando riprende.
            document.addEventListener('visibilitychange', () => {
                if (!this.track.started) return;
                if (document.visibilityState === 'visible') {
                    if (!this.track.muted) this.track.audio.play().catch(() => {});
                } else {
                    this.track.audio.pause();
                }
                this.repaintAudio?.();
            });

            window.addEventListener('pagehide', () => this.track.audio.pause());
        }

        build() {
            this.progress = this.root.querySelector('[data-rw-progress]');
            this.container = this.root.querySelector('[data-rw-slides]');
            this.bg = this.root.querySelector('[data-rw-bg]');
            this.hint = this.root.querySelector('[data-rw-hint]');

            this.progress.innerHTML = this.slides
                .map(() => '<span class="rw-progress__seg"><span class="rw-progress__fill"></span></span>')
                .join('');

            this.container.innerHTML = this.slides
                .map(slide => `<section class="rw-slide" data-slide="${esc(slide.name)}">${slide.html}</section>`)
                .join('');

            this.nodes = Array.from(this.container.querySelectorAll('.rw-slide'));
            this.segments = Array.from(this.progress.querySelectorAll('.rw-progress__seg'));
            this.nodes.forEach(stagger);
        }

        bind() {
            this.root.querySelector('[data-rw-prev]')?.addEventListener('click', () => this.go(this.index - 1));
            this.root.querySelector('[data-rw-next]')?.addEventListener('click', () => this.go(this.index + 1));

            this.container.addEventListener('click', event => {
                if (event.target.closest('[data-rw-next]')) this.go(this.index + 1);
                if (event.target.closest('[data-rw-replay]')) this.go(0);
                if (event.target.closest('[data-rw-share]')) this.share(event.target.closest('[data-rw-share]'));
            });

            document.addEventListener('keydown', event => {
                if (event.key === 'ArrowRight' || event.key === ' ') { event.preventDefault(); this.go(this.index + 1); }
                if (event.key === 'ArrowLeft') { event.preventDefault(); this.go(this.index - 1); }
                if (event.key === 'Escape') window.location.href = `/${lang}/home`;
            });

            // Tenere premuto mette in pausa, come nelle storie.
            const hold = () => this.pause();
            const release = () => this.resume();
            this.root.addEventListener('pointerdown', hold);
            this.root.addEventListener('pointerup', release);
            this.root.addEventListener('pointercancel', release);

            // Swipe orizzontale.
            let startX = 0;
            this.root.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
            this.root.addEventListener('touchend', e => {
                const delta = e.changedTouches[0].clientX - startX;
                if (Math.abs(delta) > 60) this.go(this.index + (delta < 0 ? 1 : -1));
            }, { passive: true });

            document.addEventListener('visibilitychange', () => {
                document.visibilityState === 'visible' ? this.resume() : this.pause();
            });
        }

        go(index) {
            if (index < 0) index = 0;
            if (index >= this.slides.length) index = this.slides.length - 1;

            this.index = index;
            const slide = this.slides[index];

            this.nodes.forEach((node, i) => node.classList.toggle('is-active', i === index));

            this.segments.forEach((seg, i) => {
                const fill = seg.querySelector('.rw-progress__fill');
                seg.classList.toggle('is-done', i < index);
                if (i !== index) {
                    fill.style.transition = 'none';
                    fill.style.width = i < index ? '100%' : '0';
                }
            });

            const theme = THEMES[slide.name] || THEMES.intro;
            this.bg.style.setProperty('--rw-slide-1', theme[0]);
            this.bg.style.setProperty('--rw-slide-2', theme[1]);

            this.animate(this.nodes[index]);
            this.hint?.classList.toggle('is-hidden', index > 0);

            // L'ultima schermata resta ferma: è quella con i pulsanti.
            const isLast = index === this.slides.length - 1;
            if (isLast || reduceMotion) {
                this.stopTimer();
            } else {
                this.startTimer();
            }

            if (navigator.vibrate && !reduceMotion) navigator.vibrate(8);
        }

        /** Fa partire i numeri, le barre e l'istogramma della schermata. */
        animate(node) {
            node.querySelectorAll('[data-count]').forEach(target => {
                const final = Number(target.dataset.count || 0);
                const small = target.querySelector('small');
                const suffix = small ? small.outerHTML : '';

                if (reduceMotion || final <= 0) {
                    target.innerHTML = num(final) + suffix;
                    return;
                }

                const duration = 1400;
                const started = performance.now();
                const step = now => {
                    const progress = Math.min(1, (now - started) / duration);
                    // Decelerazione: i numeri grossi sembrano più "pesanti".
                    const eased = 1 - Math.pow(1 - progress, 3);
                    target.innerHTML = num(Math.round(final * eased)) + suffix;
                    if (progress < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            });

            node.querySelectorAll('.rw-bar__fill').forEach(bar => {
                bar.style.width = '0';
                requestAnimationFrame(() => { bar.style.width = bar.dataset.w + '%'; });
            });

            node.querySelectorAll('.rw-clock__bar').forEach(bar => {
                bar.style.height = '0';
                requestAnimationFrame(() => { bar.style.height = bar.dataset.h + '%'; });
            });
        }

        startTimer() {
            this.stopTimer();
            this.remaining = SLIDE_MS;
            this.startedAt = performance.now();

            const fill = this.segments[this.index]?.querySelector('.rw-progress__fill');
            if (fill) {
                fill.style.transition = 'none';
                fill.style.width = '0';
                requestAnimationFrame(() => {
                    fill.style.transition = `width ${SLIDE_MS}ms linear`;
                    fill.style.width = '100%';
                });
            }

            this.timer = window.setTimeout(() => this.go(this.index + 1), SLIDE_MS);
        }

        stopTimer() {
            if (this.timer) { window.clearTimeout(this.timer); this.timer = null; }
        }

        pause() {
            if (this.paused || !this.timer) return;
            this.paused = true;
            this.remaining -= performance.now() - this.startedAt;
            this.stopTimer();

            const fill = this.segments[this.index]?.querySelector('.rw-progress__fill');
            if (fill) {
                const width = getComputedStyle(fill).width;
                fill.style.transition = 'none';
                fill.style.width = width;
            }
        }

        resume() {
            if (!this.paused) return;
            this.paused = false;
            if (this.index === this.slides.length - 1 || reduceMotion) return;

            this.startedAt = performance.now();
            const left = Math.max(400, this.remaining);

            const fill = this.segments[this.index]?.querySelector('.rw-progress__fill');
            if (fill) {
                requestAnimationFrame(() => {
                    fill.style.transition = `width ${left}ms linear`;
                    fill.style.width = '100%';
                });
            }

            this.timer = window.setTimeout(() => this.go(this.index + 1), left);
        }

        async share(button) {
            const label = button.querySelector('[data-rw-share-label]');
            const token = window.CRIPSUM_CSRF || '';

            try {
                const response = await fetch('/api/rewind/share.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
                    body: JSON.stringify({ action: 'enable', period: this.data.period.key, lang })
                });

                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.error || 'fallito');

                await navigator.clipboard?.writeText(result.url).catch(() => {});
                if (label) label.textContent = T.shareOn;
                toast(T.copied);
            } catch (err) {
                toast(T.shareErr);
            }
        }
    }

    function toast(message) {
        let node = document.querySelector('.rw-toast');
        if (!node) {
            node = el('<div class="rw-toast" role="status" aria-live="polite"></div>');
            document.body.appendChild(node);
        }
        node.textContent = message;
        node.classList.add('is-visible');
        window.clearTimeout(toast.timer);
        toast.timer = window.setTimeout(() => node.classList.remove('is-visible'), 2600);
    }

    // ─────────────────────────────────────────────────────────
    //  AVVIO
    // ─────────────────────────────────────────────────────────

    async function boot() {
        const root = document.querySelector('[data-rw-root]');
        const bootScreen = document.querySelector('[data-rw-boot]');
        if (!root) return;

        let data = preloaded;

        if (!data) {
            try {
                const response = await fetch('/api/rewind/get.php?lang=' + lang, { credentials: 'include' });
                const result = await response.json();

                if (response.status === 503) throw new Error(T.unavailable);
                if (!response.ok || !result.ok) throw new Error(result.error || T.error);

                data = result.rewind;
            } catch (err) {
                if (bootScreen) {
                    bootScreen.innerHTML = `
                        <div>
                            <p class="rw-title" style="font-size:1.4rem">${esc(err.message || T.error)}</p>
                            <div class="rw-actions">
                                <button type="button" class="rw-btn" onclick="location.reload()">${esc(T.retry)}</button>
                                <a class="rw-btn rw-btn--ghost" href="/${lang}/home">${esc(T.back)}</a>
                            </div>
                        </div>`;
                }
                return;
            }
        }

        // Le immagini della schermata "colpo dell'anno" arrivano prima che
        // serva, così non compare un riquadro vuoto a metà racconto.
        const preloadUrl = data?.gacha?.best_pull?.img_url;
        if (preloadUrl) { const img = new Image(); img.src = preloadUrl; }

        new Story(data, root);
        bootScreen?.classList.add('is-gone');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
