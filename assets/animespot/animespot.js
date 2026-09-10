/*
 * Cripsum™ — Animespot
 *
 * Il client non sa mai di quale anime sia la sigla finché la partita non è
 * finita: i tentativi li valida il server e l'audio arriva già tagliato ai
 * secondi sbloccati. Qui dentro ci sono il lettore, la ricerca e la scheda
 * finale.
 *
 * Due cose lo separano dal Pullspot, da cui pure discende:
 *
 * 1. le risposte possibili sono quasi cinquemila serie con i loro titoli
 *    alternativi, troppe da mandare tutte al browser: la ricerca chiede al
 *    server mentre si scrive;
 * 2. il colore della pagina lo decide la difficoltà scelta, non un'immagine.
 *    Tutto quello che è colorato legge --as-accent, quindi cambia insieme.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-as-root]');
    if (!root) return;

    const LANG = window.ANIMESPOT_LANG === 'en' ? 'en' : 'it';
    const CSRF = window.ANIMESPOT_CSRF || '';
    const DECIMAL = LANG === 'en' ? '.' : ',';
    const VOLUME_KEY = 'cripsum.animespot.volume';

    const STRINGS = {
        it: {
            skipped: 'Saltato',
            giveUp: 'Arrenditi',
            guess: 'Indovina',
            noResults: 'Nessun anime',
            searching: 'Cerco…',
            typeMore: 'Scrivi il nome di un anime',
            attempt: (n, max) => 'Tentativo ' + n + '/' + max,
            streak: (n) => 'Serie ' + n,
            unlocked: 'sbloccati',
            fullTrack: 'sigla intera',
            wonIn: (s) => 'Indovinata in ' + s,
            lostPill: 'Non presa',
            triesUsed: (n, max) => n + ' tentativi su ' + max,
            oneTry: 'al primo tentativo',
            share: 'Condividi',
            copied: 'Risultato copiato',
            copyFailed: 'Non sono riuscito a copiare',
            newTrack: 'Prossima',
            stats: 'Statistiche',
            played: 'Giocate',
            winRate: '% vinte',
            streakLabel: 'Serie',
            best: 'Record',
            distribution: "Frammento in cui l'hai presa",
            byLevel: 'Per difficoltà',
            points: 'punti',
            totalPoints: 'Punti totali',
            noStats: 'Le statistiche non sono ancora attive su questo sito: quello che giochi adesso non viene salvato.',
            loadError: 'Non riesco a caricare il gioco. Riprova tra poco.',
            audioError: 'La sigla non parte. Riprova.',
            noCatalog: 'Il catalogo delle sigle non è ancora stato importato.',
            levelHint: 'Vale dalla prossima sigla.',
            fromStart: 'Dall’inizio',
            fromStartHint: 'Il frammento parte dal primo secondo della sigla.',
            fromPreview: 'Anteprima',
            fromPreviewHint: 'Il frammento parte da un punto in mezzo alla sigla: niente attacco.',
            searchEasy: 'Facile',
            searchEasyHint: 'Cerca ovunque nel titolo e anche fra i titoli delle canzoni.',
            searchStrict: 'Stretta',
            searchStrictHint: 'Solo titoli di serie, e solo da inizio parola.',
            levelNow: 'Difficoltà cambiata.',
            stepsHint: "Spegni i frammenti che non vuoi. L'ultimo resta sempre acceso.",
            week: 'Settimana', month: 'Mese', ever: 'Sempre',
            boardWho: 'Giocatore', boardGuessed: 'Sigle', boardRest: '% · punti',
            boardEmpty: 'Ancora nessuno ha indovinato una sigla in questo periodo. Puoi essere il primo.',
            watch: 'Guarda la sigla',
            onThemes: 'Su AnimeThemes',
            onMal: 'Su MyAnimeList',
            openings: 'Opening',
            endings: 'Ending',
            shareWon: (s, n, max, level) => 'Animespot (' + level + '): indovinata in ' + s + ', al tentativo ' + n + ' su ' + max + '.',
            shareLost: (max, level) => 'Animespot (' + level + "): non l'ho presa, " + max + ' tentativi buttati.',
        },
        en: {
            skipped: 'Skipped',
            giveUp: 'Give up',
            guess: 'Guess',
            noResults: 'No anime',
            searching: 'Searching…',
            typeMore: 'Type an anime title',
            attempt: (n, max) => 'Guess ' + n + '/' + max,
            streak: (n) => 'Streak ' + n,
            unlocked: 'unlocked',
            fullTrack: 'full theme',
            wonIn: (s) => 'Guessed in ' + s,
            lostPill: 'Not guessed',
            triesUsed: (n, max) => n + ' guesses out of ' + max,
            oneTry: 'on the first try',
            share: 'Share',
            copied: 'Result copied',
            copyFailed: 'Could not copy',
            newTrack: 'Next',
            stats: 'Statistics',
            played: 'Played',
            winRate: 'Win %',
            streakLabel: 'Streak',
            best: 'Best',
            distribution: 'Clip you got it on',
            byLevel: 'By difficulty',
            points: 'points',
            totalPoints: 'Total points',
            noStats: 'Statistics are not enabled on this site yet: what you play now is not being saved.',
            loadError: 'Could not load the game. Try again shortly.',
            audioError: 'The theme will not start. Try again.',
            noCatalog: 'The theme catalogue has not been imported yet.',
            levelHint: 'Applies from the next theme.',
            fromStart: 'From the start',
            fromStartHint: 'The clip starts at the first second of the theme.',
            fromPreview: 'Preview',
            fromPreviewHint: 'The clip starts somewhere in the middle: no intro.',
            searchEasy: 'Easy',
            searchEasyHint: 'Matches anywhere in the title, and song titles too.',
            searchStrict: 'Strict',
            searchStrictHint: 'Series titles only, and only from the start of a word.',
            levelNow: 'Difficulty changed.',
            stepsHint: 'Turn off the clips you do not want. The last one always stays on.',
            week: 'Week', month: 'Month', ever: 'All time',
            boardWho: 'Player', boardGuessed: 'Themes', boardRest: '% · points',
            boardEmpty: 'Nobody has guessed a theme in this period yet. You could be the first.',
            watch: 'Watch the opening',
            onThemes: 'On AnimeThemes',
            onMal: 'On MyAnimeList',
            openings: 'Opening',
            endings: 'Ending',
            shareWon: (s, n, max, level) => 'Animespot (' + level + '): guessed in ' + s + ', on guess ' + n + ' of ' + max + '.',
            shareLost: (max, level) => 'Animespot (' + level + '): missed it, all ' + max + ' guesses gone.',
        },
    }[LANG];

    /* I nomi delle difficoltà stanno anche qui, non solo sul server: servono
       nel testo da condividere e nelle statistiche, dove non c'è nessuna
       risposta del server da cui leggerli. */
    const LEVELS = {
        it: ['Facile', 'Media', 'Difficile', 'Esperto', 'Impossibile'],
        en: ['Easy', 'Medium', 'Hard', 'Expert', 'Impossible'],
    }[LANG];

    /* Un colore per difficoltà: verde chi la sa tutta, viola chi non la sa
       nessuno. Cambiare livello ricolora la pagina intera, ed è il modo in cui
       la scelta si vede prima ancora di sentire la traccia. */
    /* Le epoche, nell'ordine in cui compaiono: l'indice è il codice che il
       server si aspetta, e zero vuol dire "tutte". */
    const ERAS = {
        it: ['Qualsiasi', 'Classici', 'Anni 2000', 'Anni 2010', 'Anni 2020'],
        en: ['Any era', 'Classics', '2000s', '2010s', '2020s'],
    }[LANG];

    /* Tutti i frammenti previsti, compresi quelli spenti: state.steps porta
       solo quelli in gioco, e il pannello deve poter riaccendere gli altri. */
    const ALL_STEPS = [0.1, 0.5, 2, 8, 15];

    const LEVEL_TINTS = [
        { h: 152, s: 72, l: 52 },
        { h: 190, s: 78, l: 55 },
        { h: 44,  s: 92, l: 58 },
        { h: 18,  s: 88, l: 60 },
        { h: 286, s: 78, l: 66 },
    ];

    const el = {
        boot: root.querySelector('[data-as-boot]'),
        game: root.querySelector('[data-as-game]'),
        error: root.querySelector('[data-as-error]'),
        metaLeft: root.querySelector('[data-as-meta-left]'),
        metaRight: root.querySelector('[data-as-meta-right]'),
        rows: root.querySelector('[data-as-rows]'),
        player: root.querySelector('[data-as-player]'),
        segments: root.querySelector('[data-as-segments]'),
        marker: root.querySelector('[data-as-marker]'),
        play: root.querySelector('[data-as-play]'),
        clock: root.querySelector('[data-as-clock]'),
        clockLabel: root.querySelector('[data-as-clock-label]'),
        controls: root.querySelector('[data-as-controls]'),
        input: root.querySelector('[data-as-input]'),
        clear: root.querySelector('[data-as-clear]'),
        list: root.querySelector('[data-as-list]'),
        skip: root.querySelector('[data-as-skip]'),
        skipBonus: root.querySelector('[data-as-skip-bonus]'),
        chips: root.querySelector('[data-as-chips]'),
        levels: root.querySelector('[data-as-levels]'),
        eras: root.querySelector('[data-as-eras]'),
        playback: root.querySelector('[data-as-playback]'),
        search: root.querySelector('[data-as-search]'),
        volume: root.querySelector('[data-as-volume]'),
        reveal: root.querySelector('[data-as-reveal]'),
        toast: root.querySelector('[data-as-toast]'),
        confetti: document.querySelector('[data-as-confetti]'),
        statsModal: document.querySelector('[data-as-stats-modal]'),
        statsBody: document.querySelector('[data-as-stats-body]'),
        rulesModal: document.querySelector('[data-as-rules-modal]'),
        boardModal: document.querySelector('[data-as-board-modal]'),
        boardBody: document.querySelector('[data-as-board-body]'),
        boardPeriods: document.querySelector('[data-as-board-periods]'),
    };

    const skipWord = el.skip ? el.skip.querySelector('span') : null;
    const skipIcon = el.skip ? el.skip.querySelector('i') : null;
    const skipWordText = skipWord ? skipWord.textContent : '';

    let state = null;
    let excluded = new Set();
    let highlighted = -1;
    let filtered = [];

    /* La ricerca vive sul server: qui restano solo il ritardo che evita una
       richiesta per tasto premuto, il modo per annullare quella in volo e la
       stringa a cui l'ultima risposta si riferiva. */
    let searchTimer = 0;
    let searchAbort = null;
    let searchedFor = null;
    let busy = false;
    let segments = [];
    let revealIcon = null;

    let clipUrl = null;
    let clipKey = '';
    let clipLoading = null;
    let clipDuration = 0;
    let clipSeconds = 0;
    let rafId = 0;
    let starting = false;
    let autoplayReveal = false;
    let celebrate = false;
    let suspenseTimer = 0;
    let handoffToken = 0;
    let handoffsInFlight = 0;
    let selected = null;

    const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ── Utilità ───────────────────────────────────────────────────────── */

    function formatSeconds(value) {
        if (!isFinite(value) || value < 0) value = 0;
        if (value >= 60) {
            const minutes = Math.floor(value / 60);
            const seconds = Math.floor(value % 60);
            return minutes + ':' + String(seconds).padStart(2, '0');
        }
        return value.toFixed(1).replace('.', DECIMAL) + 's';
    }

    function formatStep(value) {
        return String(value).replace('.', DECIMAL) + 's';
    }

    // I nomi arrivano dal database: in pagina ci vanno come testo, mai come HTML.
    function text(node, value) {
        if (node) node.textContent = value == null ? '' : String(value);
        return node;
    }

    function ratio(value) {
        return Math.max(0, Math.min(1, value)) * 100 + '%';
    }

    function icon(name) {
        const node = document.createElement('i');
        node.className = 'fa-solid fa-' + name;
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    let toastTimer = 0;
    function toast(message) {
        if (!el.toast) return;
        text(el.toast, message);
        el.toast.classList.add('as-toast--on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.toast.classList.remove('as-toast--on'), 2400);
    }

    /* ── Colore della difficoltà ───────────────────────────────────────── */

    function setAccent(level) {
        const a = LEVEL_TINTS[Math.max(1, Math.min(5, level || 1)) - 1];
        const tone = (alpha) => 'hsl(' + a.h + ' ' + a.s + '% ' + a.l + '%' + (alpha == null ? '' : ' / ' + alpha) + ')';
        const style = document.body.style;

        style.setProperty('--as-accent', tone());
        style.setProperty('--as-accent-soft', tone(.13));
        style.setProperty('--as-accent-line', tone(.34));
        style.setProperty('--as-accent-glow', tone(.34));
        style.setProperty('--as-accent-ink', 'hsl(' + a.h + ' 55% 7%)');
    }

    /** La tinta di un livello da sola, per i pallini della scala. */
    function levelTint(level) {
        const a = LEVEL_TINTS[Math.max(1, Math.min(5, level)) - 1];

        return 'hsl(' + a.h + ' ' + a.s + '% ' + a.l + '%)';
    }

    /* ── Rete ──────────────────────────────────────────────────────────── */

    async function request(url, options) {
        const response = await fetch(url, Object.assign({ credentials: 'same-origin' }, options));
        const payload = await response.json().catch(() => ({}));

        if (response.status === 401) {
            window.location.href = '/' + LANG + '/accedi';
            throw new Error('unauthenticated');
        }
        if (!response.ok) {
            throw new Error(payload.error || 'HTTP ' + response.status);
        }

        return payload;
    }

    function loadState(query) {
        return request('/api/animespot/state.php?lang=' + LANG + (query ? '&' + query : ''));
    }

    function post(url, body) {
        return request(url + '?lang=' + LANG, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(Object.assign({ csrf_token: CSRF }, body)),
        });
    }

    function sendGuess(body) {
        return post('/api/animespot/guess.php', body);
    }

    function sendOptions(body) {
        return post('/api/animespot/options.php', body);
    }

    /**
     * Cerca un anime sul server.
     *
     * La richiesta precedente si annulla: scrivendo in fretta partono cinque
     * ricerche e non c'è nessuna garanzia che tornino in ordine, quindi senza
     * annullarle l'elenco finirebbe per mostrare i risultati di due lettere fa.
     */
    function searchAnime(query) {
        if (searchAbort) searchAbort.abort();
        searchAbort = new AbortController();

        return fetch('/api/animespot/search.php?lang=' + LANG + '&q=' + encodeURIComponent(query), {
            credentials: 'same-origin',
            signal: searchAbort.signal,
        }).then((response) => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        }).then((payload) => payload.results || []);
    }

    /* ── Lettore ───────────────────────────────────────────────────────── */

    /*
     * Ci sono due lettori, non uno.
     *
     * Quando si salta mentre la traccia suona, il frammento nuovo è lo stesso
     * pezzo di musica, solo più lungo: sostituire la sorgente del lettore che
     * sta suonando lo fa tacere per un istante, ed è lo scatto che si sentiva.
     * Invece il pezzo nuovo si prepara nel lettore di riserva, lo si porta già
     * sul secondo esatto in cui il vecchio finirà, e al confine si passa il
     * testimone: uno parte, l'altro tace, e in mezzo non c'è silenzio.
     */
    function makePlayer() {
        const el = new Audio();
        el.preload = 'auto';

        el.addEventListener('ended', () => {
            // Durante il passaggio di testimone la fine del pezzo vecchio è
            // prevista: fermare tutto proprio lì rimetterebbe il buco che si
            // sta cercando di togliere.
            if (el === audio && handoffsInFlight === 0) stopPlayback();
        });

        el.addEventListener('loadedmetadata', () => {
            if (el !== audio) return;
            clipDuration = isFinite(el.duration) && el.duration > 0 ? el.duration : 0;
            if (el.paused) paint(0);
        });

        return el;
    }

    let audio = makePlayer();
    let spare = makePlayer();

    function swapPlayers() {
        const previous = audio;
        audio = spare;
        spare = previous;
        clipDuration = isFinite(audio.duration) && audio.duration > 0 ? audio.duration : 0;
    }

    function trackDuration() {
        return clipDuration;
    }

    function limitSeconds() {
        if (!state) return 0;
        return state.full ? trackDuration() : (state.unlocked || 0);
    }

    // La traccia cambia a ogni tentativo: la chiave dice se il pezzo scaricato
    // è ancora quello giusto o se va richiesto di nuovo.
    function currentClipKey() {
        return state ? [state.attempt, state.status, state.full ? 'full' : 'clip'].join('|') : '';
    }

    function clipReady() {
        return !!clipUrl && clipKey === currentClipKey();
    }

    /**
     * L'indirizzo da cui suonare quello che è sbloccato adesso.
     *
     * A partita in corso passa dal nostro proxy, che taglia i secondi e
     * nasconde il nome del file — che è il titolo dell'anime, cioè la
     * risposta. A partita finita non c'è più niente da nascondere e la sigla
     * intera si prende da AnimeThemes: sono un mega e mezzo per ogni partita
     * che finisce, e non c'è ragione di farli passare (e di tenerli in cache)
     * da noi.
     */
    function fetchClip() {
        const direct = state && state.full && state.answer && state.answer.audio_url;
        if (direct) return Promise.resolve(direct);

        return fetch('/api/animespot/audio.php?lang=' + LANG, {
            credentials: 'same-origin',
            cache: 'no-store',
        }).then((response) => {
            if (!response.ok) throw new Error('audio ' + response.status);
            return response.blob();
        }).then((blob) => URL.createObjectURL(blob));
    }

    /** Solo gli indirizzi che abbiamo creato noi vanno liberati. */
    function releaseClip(url) {
        if (url && url.startsWith('blob:')) URL.revokeObjectURL(url);
    }

    function ensureClip() {
        const key = currentClipKey();
        if (clipUrl && clipKey === key) return Promise.resolve();
        if (clipLoading && clipKey === key) return clipLoading;

        clipKey = key;
        clipLoading = fetchClip()
            .then((url) => {
                releaseClip(clipUrl);
                clipUrl = url;
                clipDuration = 0;
                clipSeconds = limitSeconds();
                audio.src = clipUrl;
                audio.load();
            })
            .catch((error) => {
                clipKey = '';
                throw error;
            })
            .finally(() => {
                clipLoading = null;
            });

        return clipLoading;
    }

    function dropClip() {
        releaseClip(clipUrl);
        clipUrl = null;
        clipKey = '';
        clipDuration = 0;
        clipSeconds = 0;
    }

    /** Aspetta un evento dell'elemento, o si arrende dopo un po'. */
    function once(el, events, timeout) {
        return new Promise((resolve) => {
            let settled = false;

            const done = () => {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                events.forEach((name) => el.removeEventListener(name, done));
                resolve();
            };

            const timer = setTimeout(done, timeout || 6000);
            events.forEach((name) => el.addEventListener(name, done));
        });
    }

    /** Aspetta che il lettore attuale arrivi in fondo al suo frammento. */
    function untilBoundary(el, limit) {
        if (el.paused || el.ended || (limit > 0 && el.currentTime >= limit - .06)) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            const stop = () => {
                clearInterval(poll);
                el.removeEventListener('ended', stop);
                resolve();
            };

            const poll = setInterval(() => {
                if (el.paused || el.ended || (limit > 0 && el.currentTime >= limit - .06)) stop();
            }, 30);

            el.addEventListener('ended', stop, { once: true });
        });
    }

    /**
     * Allunga la traccia senza interromperla: prepara il pezzo nuovo nel
     * lettore di riserva e lo fa partire quando il vecchio finisce.
     */
    /**
     * Saltare due volte di fila mentre la musica va avvia due passaggi che si
     * pestano i piedi: il gettone dice qual è quello buono, e chi resta
     * indietro molla il colpo invece di sostituire una sorgente che non è più
     * la sua.
     */
    async function handoff() {
        const token = ++handoffToken;
        const key = currentClipKey();
        const boundary = clipSeconds;

        handoffsInFlight++;
        try {
            await handoffSteps(boundary, key, token);
        } finally {
            handoffsInFlight--;
        }
    }

    async function handoffSteps(boundary, key, token) {
        const stale = () => token !== handoffToken || currentClipKey() !== key;
        const url = await fetchClip();

        // Nel frattempo può essere cambiato tutto (nuova partita, tentativo
        // andato a buon fine, un altro salto): il pezzo preso non serve più.
        if (stale()) {
            releaseClip(url);
            return;
        }

        spare.src = url;
        spare.load();
        await once(spare, ['loadedmetadata', 'error'], 6000);

        try {
            spare.currentTime = boundary;
        } catch (error) { /* niente metadati: partirà da capo, pazienza */ }

        await once(spare, ['seeked', 'canplay', 'error'], 4000);
        if (stale()) {
            releaseClip(url);
            return;
        }

        await untilBoundary(audio, boundary);

        if (stale()) {
            releaseClip(url);
            return;
        }

        const playing = !audio.paused;
        audio.pause();

        releaseClip(clipUrl);
        clipUrl = url;
        clipKey = key;
        swapPlayers();
        clipSeconds = limitSeconds();

        if (playing) {
            try {
                await audio.play();
                setPlayIcon(true);
                cancelAnimationFrame(rafId);
                rafId = requestAnimationFrame(tick);
            } catch (error) { /* il tasto resta lì per farla ripartire a mano */ }
        }
    }

    function setPlayIcon(isPlaying) {
        if (el.play) {
            el.play.innerHTML = '';
            el.play.appendChild(icon(isPlaying ? 'pause' : 'play'));
            el.play.classList.toggle('as-play--on', isPlaying);
            el.play.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
        }

        if (revealIcon) {
            revealIcon.innerHTML = '';
            revealIcon.appendChild(icon(isPlaying ? 'pause' : 'play'));
        }
    }

    function setPlayBusy(isBusy) {
        if (!el.play) return;
        el.play.disabled = isBusy;
        el.play.classList.toggle('as-play--busy', isBusy);
        if (isBusy) {
            el.play.innerHTML = '';
            el.play.appendChild(icon('circle-notch'));
        } else {
            setPlayIcon(!audio.paused);
        }
    }

    /**
     * Pausa: la testina torna all'inizio.
     *
     * Il frammento più corto dura un decimo di secondo. Riprendere da dove si
     * era interrotto vorrebbe dire ripartire da dentro quel decimo, cioè da
     * quasi la fine: schiacciare pausa e poi play deve rifar sentire il pezzo,
     * non finirlo.
     */
    function pausePlayback() {
        audio.pause();
        cancelAnimationFrame(rafId);
        rafId = 0;

        try {
            audio.currentTime = 0;
        } catch (error) { /* prima dei metadati non si può, ed è già a zero */ }

        setPlayIcon(false);
        paint(0);
    }

    /** Stop vero: il frammento è finito, la prossima volta si riparte da capo. */
    function stopPlayback() {
        audio.pause();
        spare.pause();
        cancelAnimationFrame(rafId);
        rafId = 0;
        try {
            audio.currentTime = 0;
        } catch (error) { /* prima dei metadati non si può, ed è già a zero */ }
        setPlayIcon(false);
        paint(0);
    }

    function tick() {
        const limit = limitSeconds();
        // Il server manda già il pezzo tagliato: questo è il freno di scorta
        // per i formati che non sappiamo tagliare.
        if (limit > 0 && audio.currentTime >= limit) {
            stopPlayback();
            return;
        }
        paint(audio.currentTime);
        rafId = requestAnimationFrame(tick);
    }

    /**
     * Fa partire la traccia dal punto voluto.
     *
     * Non si aspetta nessun evento di "pronto": ci pensa play(), che parte da
     * solo appena ha dati. Aspettare canplay a mano era il motivo per cui certe
     * tracce restavano a caricare all'infinito — quell'evento a volte non
     * arriva, e il timeout diventava un errore che non c'entrava niente.
     */
    function startPlayback(from) {
        // Spostare la testina prima dei metadati viene rifiutato: se serve,
        // si aspetta l'evento giusto invece di insistere.
        if (from > 0) {
            const seek = () => {
                try {
                    audio.currentTime = from;
                } catch (error) { /* pazienza: si parte da dove capita */ }
            };

            if (audio.readyState >= 1) seek();
            else audio.addEventListener('loadedmetadata', seek, { once: true });
        }

        return audio.play().then(() => {
            setPlayIcon(true);
            cancelAnimationFrame(rafId);
            rafId = requestAnimationFrame(tick);
        });
    }

    /**
     * Da dove riparte l'ascolto: sempre da capo.
     *
     * Il frammento è già corto e ogni errore lo allunga dall'inizio, quindi
     * "riprendere" non vuol dire niente: quello che serve è risentirlo tutto.
     * L'unica continuità che conta è quella del passaggio di testimone, che
     * avviene mentre la musica suona e non passa di qui.
     */
    function resumePoint() {
        return 0;
    }

    async function play(silent) {
        if (!state || starting) return;

        if (!audio.paused) {
            pausePlayback();
            return;
        }

        const from = resumePoint();

        // Percorso veloce: se il pezzo è già pronto si parte dentro al click,
        // che è quello che i browser vogliono per non bloccare l'audio.
        if (clipReady()) {
            try {
                await startPlayback(from);
                return;
            } catch (error) {
                // Un play interrotto a metà (AbortError) capita quando il
                // pezzo viene sostituito proprio in quell'istante: si rifà.
            }
        }

        starting = true;
        setPlayBusy(true);

        try {
            await ensureClip();
            await startPlayback(from);
        } catch (error) {
            // Un secondo tentativo con il pezzo riscaricato da zero copre i
            // guasti di passaggio: prima di dire che non parte, si riprova.
            try {
                dropClip();
                await ensureClip();
                await startPlayback(from);
            } catch (retryError) {
                if (!silent) toast(STRINGS.audioError);
            }
        } finally {
            starting = false;
            setPlayBusy(false);
        }
    }

    /* ── Barra a segmenti ──────────────────────────────────────────────── */

    function buildSegments() {
        if (!el.segments || !state) return;

        el.segments.innerHTML = '';
        segments = [];

        // Finita la partita non ci sono più scalini da mostrare: la barra
        // diventa un blocco solo, lungo quanto la traccia.
        const bounds = state.full
            ? [{ from: 0, to: null, grow: 1 }]
            : state.steps.map((to, index) => {
                const from = index === 0 ? 0 : state.steps[index - 1];
                return { from: from, to: to, grow: to - from };
            });

        bounds.forEach((bound) => {
            const seg = document.createElement('div');
            seg.className = 'as-seg';
            seg.style.flexGrow = String(bound.grow);
            seg.style.flexBasis = '5px';

            const unlocked = document.createElement('div');
            unlocked.className = 'as-seg__unlocked';

            const played = document.createElement('div');
            played.className = 'as-seg__played';

            seg.appendChild(unlocked);
            seg.appendChild(played);
            el.segments.appendChild(seg);

            segments.push({ from: bound.from, to: bound.to, unlocked: unlocked, played: played });
        });
    }

    /** Ridisegna barra, marcatore e cronometro per una data posizione. */
    function paint(current) {
        if (!state) return;

        const limit = limitSeconds();
        const unlockedTo = state.full ? Infinity : limit;

        segments.forEach((seg) => {
            const to = seg.to === null ? (trackDuration() || 1) : seg.to;
            const span = Math.max(to - seg.from, 1e-6);
            seg.unlocked.style.width = ratio((unlockedTo - seg.from) / span);
            seg.played.style.width = ratio((current - seg.from) / span);
        });

        if (el.marker) {
            if (state.full) {
                el.marker.hidden = true;
            } else {
                const total = state.steps[state.steps.length - 1] || 1;
                el.marker.hidden = false;
                el.marker.style.left = ratio(limit / total);
                text(el.marker, formatSeconds(limit));
            }
        }

        const playing = !audio.paused;
        text(el.clock, playing ? formatSeconds(current) : (limit > 0 ? formatSeconds(limit) : '—'));
        text(el.clockLabel, playing
            ? '/ ' + (limit > 0 ? formatSeconds(limit) : '—')
            : (state.full ? STRINGS.fullTrack : STRINGS.unlocked));
    }

    /* ── Ricerca ───────────────────────────────────────────────────────── */

    function closeList() {
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = 0;
        if (searchAbort) searchAbort.abort();
        searchAbort = null;
        searchedFor = null;

        if (!el.list) return;
        el.list.hidden = true;
        el.list.innerHTML = '';
        if (el.input) el.input.setAttribute('aria-expanded', 'false');
        highlighted = -1;
        filtered = [];
    }

    /** Una riga sola nell'elenco, per dire che si sta cercando o che non c'è niente. */
    function listNote(message, spinning) {
        if (!el.list) return;

        el.list.innerHTML = '';
        const note = document.createElement('li');
        note.className = spinning ? 'as-option__spin' : 'as-option__empty';

        if (spinning) {
            const spinner = icon('circle-notch');
            spinner.style.animation = 'asSpin .9s linear infinite';
            note.appendChild(spinner);
        }

        note.appendChild(document.createTextNode(message));
        el.list.appendChild(note);
        el.list.hidden = false;
        highlighted = -1;
    }

    /**
     * Apre l'elenco per quello che si è scritto.
     *
     * Le risposte possibili sono quasi cinquemila serie con tutti i loro
     * titoli alternativi: non si possono tenere nel browser, quindi si chiede
     * al server. Il ritardo prima di partire serve a non mandare una richiesta
     * per ogni tasto, e la richiesta precedente viene annullata.
     */
    function openList(query) {
        if (!el.list) return;

        const needle = String(query || '').trim();

        if (searchTimer) clearTimeout(searchTimer);

        if (needle.length < 1) {
            listNote(STRINGS.typeMore, false);
            filtered = [];
            searchedFor = null;
            if (el.input) el.input.setAttribute('aria-expanded', 'true');
            return;
        }

        // Già cercato e già a schermo: si evita di rifare tutto solo perché il
        // campo ha riavuto il fuoco.
        if (searchedFor === needle && filtered.length) {
            el.list.hidden = false;
            if (el.input) el.input.setAttribute('aria-expanded', 'true');
            return;
        }

        listNote(STRINGS.searching, true);
        if (el.input) el.input.setAttribute('aria-expanded', 'true');

        searchTimer = setTimeout(() => {
            searchAnime(needle)
                .then((results) => {
                    // Nel frattempo si è scritto altro: questa risposta parla
                    // di una domanda che non è più quella.
                    if (el.input && el.input.value.trim() !== needle) return;

                    searchedFor = needle;
                    paintList(results);
                })
                .catch((error) => {
                    if (error && error.name === 'AbortError') return;
                    listNote(STRINGS.noResults, false);
                });
        }, 190);
    }

    function paintList(results) {
        if (!el.list) return;

        // Un anime già provato non torna nell'elenco: sprecare un tentativo
        // due volte sulla stessa serie non è una scelta, è un incidente.
        filtered = (results || []).filter((anime) => !excluded.has(anime.id));

        if (!filtered.length) {
            listNote(STRINGS.noResults, false);
            return;
        }

        el.list.innerHTML = '';

        filtered.forEach((anime, index) => {
            const option = document.createElement('li');
            option.className = 'as-option';
            option.setAttribute('role', 'option');

            // La copertina c'è per tutte le serie: se comparisse solo per
            // qualcuna, quella qualcuna sarebbe la risposta.
            if (anime.cover) {
                const art = document.createElement('img');
                art.src = anime.cover;
                art.alt = '';
                art.loading = 'lazy';
                option.appendChild(art);
            }

            const body = document.createElement('div');
            body.className = 'as-option__body';

            const name = document.createElement('span');
            name.className = 'as-option__name';
            text(name, anime.nome);
            body.appendChild(name);

            // Anno e formato distinguono i seguiti, che si chiamano quasi
            // uguale; il titolo alternativo spiega perché la riga è comparsa.
            const details = [anime.via, anime.anno || null, anime.formato || null].filter(Boolean);

            if (details.length) {
                const sub = document.createElement('span');
                sub.className = 'as-option__sub';
                text(sub, details.join(' · '));
                body.appendChild(sub);
            }

            option.appendChild(body);

            option.addEventListener('mousedown', (event) => {
                event.preventDefault();
                choose(index);
            });

            el.list.appendChild(option);
        });

        highlighted = 0;
        paintHighlight();
        el.list.hidden = false;
    }

    function paintHighlight() {
        if (!el.list) return;
        Array.from(el.list.children).forEach((node, index) => {
            node.classList.toggle('as-option--active', index === highlighted);
        });
        const active = el.list.children[highlighted];
        if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest' });
    }

    /**
     * Scegliere un anime lo scrive nel campo e arma il tasto: il tentativo
     * parte solo quando si conferma. Un click sbagliato nell'elenco non deve
     * costare un tentativo.
     */
    function choose(index) {
        const anime = filtered[index];
        if (!anime || busy) return;

        selected = anime;
        if (el.input) el.input.value = anime.nome;
        closeList();
        syncControls();
        if (el.skip) el.skip.focus();
    }

    /* ── Vetrina ───────────────────────────────────────────────────────── */

    function renderRows() {
        if (!el.rows || !state) return;

        el.rows.innerHTML = '';

        for (let index = 0; index < state.max_attempts; index += 1) {
            const guess = state.guesses[index];
            const row = document.createElement('li');
            row.className = 'as-row';

            const number = document.createElement('span');
            number.className = 'as-row__no';
            text(number, index + 1);

            const label = document.createElement('span');
            label.className = 'as-row__text';

            if (guess) {
                row.classList.add('as-row--' + guess.type);
                text(label, guess.type === 'skip' ? STRINGS.skipped : (guess.nome || '—'));
            } else if (index === state.guesses.length && state.status === 'playing') {
                row.classList.add('as-row--now');
                text(label, formatStep(state.steps[Math.min(index, state.steps.length - 1)]));
            } else {
                text(label, '—');
            }

            row.appendChild(number);
            row.appendChild(label);
            el.rows.appendChild(row);
        }
    }

    /**
     * I frammenti: quali sono accesi nella partita e quali si vogliono giocare.
     *
     * Un chip dice due cose insieme. Barrato vuol dire "questo frammento l'ho
     * spento e non lo voglio"; acceso di colore vuol dire "in questa partita ci
     * sono già arrivato". Sono due informazioni diverse e devono restare
     * distinguibili, altrimenti spegnere un frammento sembra averlo già usato.
     */
    function renderChips() {
        if (!el.chips || !state) return;

        const chosen = (state.options && state.options.passi) || [0, 1, 2, 3, 4];
        const last = ALL_STEPS.length - 1;

        el.chips.innerHTML = '';

        ALL_STEPS.forEach((step, index) => {
            const on = chosen.indexOf(index) !== -1;
            const position = chosen.indexOf(index);
            const reached = on && (state.full || position <= state.attempt);

            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'as-chip'
                + (reached ? ' as-chip--on' : '')
                + (on ? '' : ' as-chip--off')
                + (index === last ? ' as-chip--locked' : '');
            text(chip, formatStep(step));

            if (index === last) {
                chip.disabled = true;
                chip.title = STRINGS.stepsHint;
            } else {
                chip.addEventListener('click', () => toggleStep(index));
            }

            el.chips.appendChild(chip);
        });
    }

    /** La scala delle difficoltà, con quante sigle ha dietro ogni gradino. */
    /**
     * La scala delle difficoltà, che qui è anche la mappa della serie.
     *
     * Ogni riga è uno dei cinque posti: quella in corso è accesa, quelle già
     * chiuse portano il segno di com'è andata, e cliccandole ci si torna
     * sopra — la sigla è ancora quella, con i tentativi che si erano fatti.
     */
    function renderLevels() {
        if (!el.levels || !state) return;

        const slots = state.slots || [];
        const pool = state.pool || {};

        el.levels.innerHTML = '';

        LEVELS.forEach((name, index) => {
            const level = index + 1;
            const info = slots[index] || { status: 'nuova', points: 0, attempt: 0 };
            const on = level === (state.slot || 1);

            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'as-level'
                + (on ? ' as-level--on' : '')
                + (info.status === 'won' ? ' as-level--won' : '')
                + (info.status === 'lost' ? ' as-level--lost' : '');
            row.style.setProperty('--as-level', levelTint(level));
            row.setAttribute('aria-pressed', on ? 'true' : 'false');

            const dot = document.createElement('span');
            dot.className = 'as-level__dot';
            row.appendChild(dot);

            const label = document.createElement('span');
            label.className = 'as-level__name';
            text(label, name);
            row.appendChild(label);

            const mark = document.createElement('span');
            mark.className = 'as-level__count';

            if (info.status === 'won') {
                mark.appendChild(icon('check'));
                if (info.points) mark.appendChild(document.createTextNode(' ' + info.points));
            } else if (info.status === 'lost') {
                mark.appendChild(icon('xmark'));
            } else if (info.status === 'playing' && info.attempt > 0) {
                text(mark, info.attempt + '/' + (state.max_attempts || 5));
            } else {
                text(mark, pool[level] || pool[String(level)] || 0);
            }

            row.appendChild(mark);
            row.addEventListener('click', () => pickLevel(level));
            el.levels.appendChild(row);
        });
    }

    /**
     * Sposta il gioco su un'altra difficoltà della serie.
     *
     * Non è più "vale dalla prossima": il posto c'è già, con la sua sigla, e
     * ci si va sopra subito. Se quella difficoltà l'hai già finita ritrovi la
     * schermata della risposta, non una sigla nuova.
     */
    async function pickLevel(level) {
        if (!state || level === (state.slot || 1)) return;
        sendOption({ difficulty: level });
    }

    async function toggleStep(index) {
        if (!state) return;

        const chosen = ((state.options && state.options.passi) || []).slice();
        const at = chosen.indexOf(index);

        if (at === -1) chosen.push(index);
        else chosen.splice(at, 1);

        sendOption({ steps: chosen });
    }

    /**
     * La fila delle epoche.
     *
     * "Qualsiasi" sta davanti perché è il modo normale di giocare; gli altri
     * sono decenni. Il numero accanto è quante sigle restano scegliendolo, ed
     * è l'unico modo per capire prima di cliccare che gli anni Venti hanno un
     * decimo delle sigle degli anni Dieci.
     */
    function renderEras() {
        if (!el.eras || !state) return;

        const current = (state.options && state.options.era) || 0;
        const totals = state.eras || {};

        el.eras.innerHTML = '';

        ERAS.forEach((name, era) => {
            const count = totals[era] || totals[String(era)] || 0;

            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'as-era' + (era === current ? ' as-era--on' : '');
            chip.setAttribute('aria-pressed', era === current ? 'true' : 'false');

            const label = document.createElement('span');
            text(label, name);
            chip.appendChild(label);

            const tally = document.createElement('small');
            text(tally, count);
            chip.appendChild(tally);

            if (count <= 0) chip.disabled = true;
            else chip.addEventListener('click', () => pickEra(era));

            el.eras.appendChild(chip);
        });
    }

    /**
     * I due interruttori a due voci: da dove parte l'ascolto e quanto è
     * generosa la ricerca. Sono la stessa cosa con etichette diverse, quindi
     * li disegna una funzione sola.
     */
    function renderSwitch(node, options, current, onPick) {
        if (!node) return;

        node.innerHTML = '';

        options.forEach(([value, label, hint]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'as-switch__side' + (value === current ? ' as-switch__side--on' : '');
            button.setAttribute('aria-pressed', value === current ? 'true' : 'false');
            if (hint) button.title = hint;
            text(button, label);

            button.addEventListener('click', () => onPick(value));
            node.appendChild(button);
        });
    }

    function renderToggles() {
        if (!state || !state.options) return;

        renderSwitch(el.playback, [
            ['inizio', STRINGS.fromStart, STRINGS.fromStartHint],
            ['anteprima', STRINGS.fromPreview, STRINGS.fromPreviewHint],
        ], state.options.avvio, (value) => sendOption({ start: value }));

        renderSwitch(el.search, [
            ['facile', STRINGS.searchEasy, STRINGS.searchEasyHint],
            ['stretta', STRINGS.searchStrict, STRINGS.searchStrictHint],
        ], state.options.ricerca, (value) => sendOption({ search: value }));
    }

    /**
     * Manda un'opzione al server e ridisegna i pannelli.
     *
     * `restart` dice se la scelta ha senso solo su una sigla nuova: la
     * difficoltà, l'epoca e il punto di partenza sì (a metà partita cambiare
     * traccia sarebbe un modo per scappare da una sigla difficile), la
     * generosità della ricerca no — quella si può cambiare mentre si scrive.
     */
    async function sendOption(body) {
        if (busy || !state) return;

        const wasSearch = state.options && state.options.ricerca;
        const wasTrack = state.answer || null;

        try {
            const payload = await sendOptions(body);
            const changed = payload.slot !== state.slot || payload.attempt !== state.attempt;

            state = payload;

            // Cambiando posto la sigla è un'altra: quello che stava suonando
            // non c'entra più niente e va fermato prima di ridisegnare.
            if (changed || wasTrack !== (payload.answer || null)) {
                stopPlayback();
                dropClip();
                selected = null;
                if (el.input) el.input.value = '';
                closeList();
            }

            render();

            // Frammenti e punto di partenza non toccano una sigla già
            // cominciata: si applicano alla prossima, e conviene dirlo o il
            // pulsante sembra non aver fatto niente.
            if ((body.steps || body.start) && state.status === 'playing' && state.attempt > 0) {
                toast(STRINGS.levelHint);
            }

            // La ricerca cambia subito: quello che è già scritto nel campo va
            // ricercato con le regole nuove, o l'elenco resta quello di prima
            // e sembra che l'interruttore non serva a niente.
            if (state.options.ricerca !== wasSearch) {
                searchedFor = null;
                if (el.input && el.input.value && state.status === 'playing') openList(el.input.value);
            }
        } catch (error) {
            toast(error.message || STRINGS.loadError);
        }
    }

    function pickEra(era) {
        if (!state || era === ((state.options && state.options.era) || 0)) return;
        sendOption({ era: era });
    }


    function renderMeta() {
        if (!state) return;

        const done = state.status !== 'playing';
        text(el.metaLeft, STRINGS.attempt(
            Math.min(state.attempt + (done ? 0 : 1), state.max_attempts),
            state.max_attempts
        ));

        const streak = (state.stats && state.stats.streak) || 0;
        text(el.metaRight, streak > 0 ? STRINGS.streak(streak) : '');
    }

    function renderControls() {
        const playing = state && state.status === 'playing';

        if (el.controls) el.controls.hidden = !playing;
        if (!playing) return;

        syncControls();
    }

    /**
     * Un tasto solo, due mestieri: salta finché non si è scelto un personaggio,
     * poi diventa la conferma del tentativo.
     */
    function paintActionButton() {
        if (!el.skip || !state) return;

        const guessing = selected !== null;
        el.skip.classList.toggle('as-skip--guess', guessing);

        if (guessing) {
            if (skipIcon) skipIcon.className = 'fa-solid fa-check';
            text(skipWord, STRINGS.guess);
            text(el.skipBonus, '');
            return;
        }

        if (skipIcon) skipIcon.className = 'fa-solid fa-forward-step';

        const index = state.guesses.length;
        const next = state.steps[index + 1];

        if (typeof next === 'number') {
            text(skipWord, skipWordText);
            text(el.skipBonus, '+' + formatStep(Math.round((next - state.steps[index]) * 10) / 10));
        } else {
            text(skipWord, STRINGS.giveUp);
            text(el.skipBonus, '');
        }
    }

    function syncControls() {
        if (el.skip) el.skip.disabled = busy || !state || state.status !== 'playing';
        if (el.input) el.input.disabled = busy;
        if (el.clear) el.clear.hidden = !el.input || !el.input.value;
        paintActionButton();
    }

    function button(className, iconName, label, handler) {
        const node = document.createElement('button');
        node.type = 'button';
        node.className = className;
        node.appendChild(icon(iconName));
        node.appendChild(document.createTextNode(label));
        node.addEventListener('click', handler);
        return node;
    }

    /** In quanti secondi di traccia è stata presa: è il numero che si vanta. */
    function winningSeconds() {
        return formatStep(state.steps[Math.min(state.guesses.length - 1, state.steps.length - 1)]);
    }

    function shareText() {
        // La difficoltà fa parte del risultato: indovinare una sigla facile in
        // due secondi e una impossibile in due secondi non sono la stessa cosa.
        const level = state.difficulty_name || LEVELS[(state.difficulty || 1) - 1];

        const line = state.status === 'won'
            ? STRINGS.shareWon(winningSeconds(), state.guesses.length, state.max_attempts, level)
            : STRINGS.shareLost(state.max_attempts, level);

        return line + '\n' + window.location.origin + '/' + LANG + '/animespot';
    }

    async function share() {
        const payload = shareText();

        if (navigator.share) {
            try {
                await navigator.share({ text: payload });
                return;
            } catch (error) {
                if (error && error.name === 'AbortError') return;
            }
        }

        try {
            await navigator.clipboard.writeText(payload);
            toast(STRINGS.copied);
        } catch (error) {
            toast(STRINGS.copyFailed);
        }
    }

    /* ── Rivelazione ───────────────────────────────────────────────────── */

    function renderReveal() {
        revealIcon = null;

        if (!el.reveal) return;

        if (!state || state.status === 'playing' || !state.answer) {
            el.reveal.hidden = true;
            el.reveal.innerHTML = '';
            el.reveal.classList.remove('as-reveal--waiting');
            if (el.player) el.player.hidden = false;
            document.body.classList.remove('as-is-reveal', 'as-is-suspense');
            return;
        }

        const won = state.status === 'won';

        // Il lettore sparisce: da qui in poi la scena è del personaggio.
        if (el.player) el.player.hidden = true;
        el.reveal.hidden = false;
        el.reveal.innerHTML = '';

        // Due tempi. Prima il fascio si stringe a lama e la musica parte: per
        // un attimo c'è solo il suono e nessuna risposta. Poi la luce si apre
        // e il personaggio arriva dentro quel gesto.
        const suspense = celebrate || autoplayReveal;

        clearTimeout(suspenseTimer);
        document.body.classList.remove('as-is-suspense');
        document.body.classList.add('as-is-reveal');
        if (suspense && !reducedMotion) {
            document.body.classList.add('as-is-suspense');
            el.reveal.classList.add('as-reveal--waiting');
        } else {
            el.reveal.classList.remove('as-reveal--waiting');
        }

        const answer = state.answer;

        const card = document.createElement('div');
        card.className = 'as-card';

        const art = document.createElement('button');
        art.type = 'button';
        art.className = 'as-card__art';
        art.addEventListener('click', () => play());

        if (answer.cover) {
            const image = document.createElement('img');
            image.src = answer.cover;
            image.alt = answer.anime;
            art.appendChild(image);
        }

        revealIcon = document.createElement('span');
        revealIcon.className = 'as-card__icon';
        revealIcon.appendChild(icon('play'));
        art.appendChild(revealIcon);
        card.appendChild(art);

        const name = document.createElement('h2');
        name.className = 'as-card__name';
        text(name, answer.anime);
        card.appendChild(name);

        // Prima riga di contorno: quando è uscito, com'era fatto e quale delle
        // sue sigle era questa. "OP2" da solo non dice niente a chi non
        // conosce la sigla, quindi la parola per esteso resta accanto.
        const meta = document.createElement('p');
        meta.className = 'as-card__meta';
        text(meta, [
            answer.anno || null,
            answer.formato || null,
            (answer.tipo === 'ED' ? STRINGS.endings : STRINGS.openings) + ' ' + answer.sigla.replace(/^(OP|ED)/, ''),
        ].filter(Boolean).join(' · '));
        card.appendChild(meta);

        if (answer.canzone) {
            const song = document.createElement('p');
            song.className = 'as-card__song';
            const title = document.createElement('b');
            text(title, '« ' + answer.canzone + ' »');
            song.appendChild(title);
            card.appendChild(song);
        }

        if (answer.artisti) {
            const artists = document.createElement('p');
            artists.className = 'as-card__artists';
            text(artists, answer.artisti);
            card.appendChild(artists);
        }

        const pill = document.createElement('span');
        pill.className = 'as-pill' + (won ? '' : ' as-pill--lost');
        text(pill, won
            ? STRINGS.wonIn(winningSeconds())
            : STRINGS.lostPill);
        card.appendChild(pill);

        // I punti solo se sono stati presi: uno zero grosso in mezzo allo
        // schermo non aggiunge niente a una sconfitta che si è già capita.
        if (won && state.points) {
            const points = document.createElement('div');
            points.className = 'as-points';
            text(points, '+' + state.points);

            const unit = document.createElement('small');
            text(unit, STRINGS.points);
            points.appendChild(unit);

            card.appendChild(points);
        }

        const actions = document.createElement('div');
        actions.className = 'as-reveal__actions';
        actions.appendChild(button('as-btn', 'share-nodes', STRINGS.share, share));
        actions.appendChild(button('as-btn as-btn--go', 'forward', STRINGS.newTrack, () => start('next=1')));
        card.appendChild(actions);

        // Il video della sigla è la cosa che si vuole vedere davvero, ma pesa
        // decine di megabyte e sta su un sito che ce lo regala: si scarica solo
        // se lo si chiede, e per farlo bisogna prima aver finito la partita.
        if (answer.video_url) {
            const frame = document.createElement('div');
            frame.className = 'as-video';

            const open = document.createElement('button');
            open.type = 'button';
            open.className = 'as-video__open';
            open.appendChild(icon('circle-play'));
            open.appendChild(document.createTextNode(STRINGS.watch));

            open.addEventListener('click', () => {
                stopPlayback();

                const video = document.createElement('video');
                video.src = answer.video_url;
                video.controls = true;
                video.autoplay = true;
                video.playsInline = true;
                video.volume = audio.volume;

                frame.innerHTML = '';
                frame.appendChild(video);
            });

            frame.appendChild(open);
            card.appendChild(frame);
        }

        const links = [];
        if (answer.link) links.push([answer.link, STRINGS.onThemes]);
        if (answer.mal) links.push([answer.mal, STRINGS.onMal]);

        if (links.length) {
            const row = document.createElement('div');
            row.className = 'as-links';

            links.forEach(([href, label]) => {
                const link = document.createElement('a');
                link.href = href;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                text(link, label);
                row.appendChild(link);
            });

            card.appendChild(row);
        }

        el.reveal.appendChild(card);

        // I coriandoli festeggiano il momento, non lo stato: ricaricando la
        // pagina su una partita già vinta non devono ripartire.
        const party = won && celebrate;
        celebrate = false;

        if (!suspense || reducedMotion) {
            if (party) confetti();
            return;
        }

        suspenseTimer = setTimeout(() => {
            document.body.classList.remove('as-is-suspense');
            el.reveal.classList.remove('as-reveal--waiting');
            if (party) confetti();
        }, 1150);
    }

    let confettiRaf = 0;

    function confetti() {
        const canvas = el.confetti;
        if (!canvas || reducedMotion) return;

        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        if (!width || !height) return;

        const scale = Math.min(2, window.devicePixelRatio || 1);
        canvas.width = width * scale;
        canvas.height = height * scale;

        const ctx = canvas.getContext('2d');
        ctx.setTransform(scale, 0, 0, scale, 0, 0);

        const accent = getComputedStyle(document.body).getPropertyValue('--as-accent').trim() || '#22e07d';
        const colors = [accent, accent, '#ffffff', 'rgba(255,255,255,.65)'];
        const pieces = [];

        for (let i = 0; i < 120; i += 1) {
            pieces.push({
                x: width / 2 + (Math.random() - .5) * width * .5,
                y: height * .34 + (Math.random() - .5) * 70,
                vx: (Math.random() - .5) * 7.5,
                vy: -5 - Math.random() * 10,
                gravity: .22 + Math.random() * .14,
                size: 4 + Math.random() * 7,
                rotation: Math.random() * Math.PI,
                spin: (Math.random() - .5) * .32,
                color: colors[(Math.random() * colors.length) | 0],
            });
        }

        let frame = 0;
        cancelAnimationFrame(confettiRaf);

        (function step() {
            frame += 1;
            ctx.clearRect(0, 0, width, height);

            let alive = 0;
            const fade = Math.max(0, 1 - frame / 200);

            pieces.forEach((piece) => {
                piece.vy += piece.gravity;
                piece.x += piece.vx;
                piece.y += piece.vy;
                piece.rotation += piece.spin;
                if (piece.y < height + 40) alive += 1;

                ctx.save();
                ctx.translate(piece.x, piece.y);
                ctx.rotate(piece.rotation);
                ctx.globalAlpha = fade;
                ctx.fillStyle = piece.color;
                ctx.fillRect(-piece.size / 2, -piece.size / 4, piece.size, piece.size * .55);
                ctx.restore();
            });

            if (alive && frame < 210) confettiRaf = requestAnimationFrame(step);
            else ctx.clearRect(0, 0, width, height);
        })();
    }

    /* ── Statistiche ───────────────────────────────────────────────────── */

    function renderStats() {
        if (!el.statsBody || !state) return;

        const stats = state.stats || {};
        el.statsBody.innerHTML = '';

        const figures = document.createElement('div');
        figures.className = 'as-figures';

        [
            [stats.played || 0, STRINGS.played],
            [(stats.win_rate || 0) + '%', STRINGS.winRate],
            [stats.streak || 0, STRINGS.streakLabel],
            [stats.best_streak || 0, STRINGS.best],
            [(stats.points || 0).toLocaleString(LANG === 'en' ? 'en-GB' : 'it-IT'), STRINGS.totalPoints],
        ].forEach(([value, label]) => {
            const figure = document.createElement('div');
            figure.className = 'as-figure';

            const valueNode = document.createElement('div');
            valueNode.className = 'as-figure__value';
            text(valueNode, value);

            const labelNode = document.createElement('div');
            labelNode.className = 'as-figure__label';
            text(labelNode, label);

            figure.appendChild(valueNode);
            figure.appendChild(labelNode);
            figures.appendChild(figure);
        });

        el.statsBody.appendChild(figures);

        const title = document.createElement('div');
        title.className = 'as-figure__label';
        title.style.marginBottom = '.55rem';
        text(title, STRINGS.distribution);
        el.statsBody.appendChild(title);

        const distribution = stats.distribution || [];
        const peak = Math.max(1, ...distribution);
        const bars = document.createElement('div');
        bars.className = 'as-bars';

        distribution.forEach((count, index) => {
            // La barra da illuminare è quella del frammento, non quella del
            // tentativo: con una scala accorciata i due numeri non coincidono.
            const wonAt = state.status === 'won'
                ? (state.step_index || [])[state.guesses.length - 1]
                : -1;
            const isCurrent = wonAt === index;

            const bar = document.createElement('div');
            bar.className = 'as-bar-row' + (isCurrent ? ' as-bar-row--now' : '');

            const label = document.createElement('span');
            label.className = 'as-bar-row__label';
            text(label, String(index + 1));

            const track = document.createElement('div');
            track.className = 'as-bar-row__track';

            const fill = document.createElement('div');
            fill.className = 'as-bar-row__fill';
            fill.style.width = Math.max(6, (count / peak) * 100) + '%';
            text(fill, String(count));

            track.appendChild(fill);
            bar.appendChild(label);
            bar.appendChild(track);
            bars.appendChild(bar);
        });

        el.statsBody.appendChild(bars);

        // Quanto si vince a ogni gradino della scala. È l'unico posto in cui
        // si vede se la difficoltà scelta è davvero quella giusta per sé:
        // vincere il 90% in "facile" vuol dire che è ora di salire.
        const byLevel = stats.by_level || {};
        const levelRows = LEVELS.map((name, index) => {
            const row = byLevel[index + 1] || byLevel[String(index + 1)] || { played: 0, won: 0 };

            return { name: name, level: index + 1, played: row.played || 0, won: row.won || 0 };
        }).filter((row) => row.played > 0);

        if (levelRows.length) {
            const levelTitle = document.createElement('div');
            levelTitle.className = 'as-figure__label';
            levelTitle.style.margin = '1.2rem 0 .55rem';
            text(levelTitle, STRINGS.byLevel);
            el.statsBody.appendChild(levelTitle);

            const list = document.createElement('div');
            list.className = 'as-levelstats';

            levelRows.forEach((row) => {
                const rate = Math.round((row.won / row.played) * 100);

                const line = document.createElement('div');
                line.className = 'as-levelstat';
                line.style.setProperty('--as-level', levelTint(row.level));

                const label = document.createElement('span');
                label.className = 'as-levelstat__name';
                text(label, row.name);

                const track = document.createElement('div');
                track.className = 'as-levelstat__track';

                const fill = document.createElement('div');
                fill.className = 'as-levelstat__fill';
                fill.style.width = Math.max(2, rate) + '%';
                track.appendChild(fill);

                const value = document.createElement('span');
                value.className = 'as-levelstat__value';
                text(value, rate + '% · ' + row.played);

                line.appendChild(label);
                line.appendChild(track);
                line.appendChild(value);
                list.appendChild(line);
            });

            el.statsBody.appendChild(list);
        }

        if (stats.persisted === false) {
            const note = document.createElement('p');
            note.className = 'as-note';
            text(note, STRINGS.noStats);
            el.statsBody.appendChild(note);
        }
    }

    function render() {
        if (!state) return;

        excluded = new Set();
        state.guesses.forEach((guess) => {
            if (guess.id) excluded.add(guess.id);
        });

        // Il colore è quello della difficoltà in corso, non di quella scelta:
        // cambiando livello a metà partita la pagina non deve tingersi di una
        // sigla che si sta ancora giocando con le vecchie regole.
        setAccent(state.difficulty || (state.options && state.options.difficolta) || 1);

        buildSegments();
        renderRows();
        renderChips();
        renderLevels();
        renderEras();
        renderToggles();
        renderMeta();
        renderControls();
        renderReveal();
        renderStats();

        // Se la traccia sta ancora suonando (skip al volo) non si azzera nulla:
        // il lettore continua e la barra lo segue.
        setPlayIcon(!audio.paused);
        paint(audio.paused ? 0 : audio.currentTime);

        // Il pezzo si scarica prima che serva: al click deve partire subito,
        // non dopo un viaggio in rete. Se però la traccia sta suonando, il
        // cambio lo sta già gestendo handoff() e qui non si tocca niente.
        if (!audio.paused) return;

        // A partita finita la traccia è quella intera, un mega e mezzo preso
        // da AnimeThemes: scaricarla senza che nessuno l'abbia chiesta faceva
        // sembrare lentissimo arrendersi. Ora parte solo se c'è da festeggiare,
        // altrimenti aspetta che si prema sulla copertina.
        if (state.full && !autoplayReveal) {
            paint(0);
            return;
        }

        ensureClip().then(() => {
            paint(0);

            // La sigla parte mentre la luce si stringe: è lei a reggere
            // l'attesa. Se il browser la blocca resta il pulsante sulla
            // copertina.
            if (state && state.full && autoplayReveal) {
                autoplayReveal = false;
                play(true);
            }
        }).catch(() => {});
    }

    /* ── Classifica ────────────────────────────────────────────────────── */

    let boardPeriod = 'settimana';
    let boardRows = null;

    function loadBoard(period) {
        return request('/api/animespot/board.php?lang=' + LANG + '&period=' + encodeURIComponent(period));
    }

    /** Una riga della classifica: posto, faccia, nome e i tre numeri. */
    function boardRow(row, mine) {
        const line = document.createElement('div');
        line.className = 'as-rank' + (mine ? ' as-rank--me' : '') + (row.rank <= 3 ? ' as-rank--top' : '');

        const place = document.createElement('span');
        place.className = 'as-rank__place';
        text(place, row.rank);
        line.appendChild(place);

        const face = document.createElement('img');
        face.className = 'as-rank__face';
        face.src = row.avatar;
        face.alt = '';
        face.loading = 'lazy';
        line.appendChild(face);

        const name = document.createElement('a');
        name.className = 'as-rank__name';
        name.href = '/u/' + encodeURIComponent(row.username || '');
        text(name, row.name);
        line.appendChild(name);

        // Il numero grosso è quello per cui si è in classifica; gli altri due
        // stanno accanto perché indovinare presto e indovinare tanto sono due
        // bravure diverse e vanno viste insieme.
        const guessed = document.createElement('span');
        guessed.className = 'as-rank__score';
        text(guessed, row.guessed);
        line.appendChild(guessed);

        const rest = document.createElement('span');
        rest.className = 'as-rank__meta';
        text(rest, row.rate + '% · ' + row.points.toLocaleString(LANG === 'en' ? 'en-GB' : 'it-IT'));
        line.appendChild(rest);

        return line;
    }

    function renderBoard() {
        if (!el.boardBody) return;

        // I tre periodi. Il primo è la settimana e non è un caso: su "sempre"
        // chi arriva adesso non ha nessuna speranza, e una classifica senza
        // speranza non fa giocare nessuno.
        renderSwitch(el.boardPeriods, [
            ['settimana', STRINGS.week],
            ['mese', STRINGS.month],
            ['sempre', STRINGS.ever],
        ], boardPeriod, (value) => { boardPeriod = value; openBoard(); });

        el.boardBody.innerHTML = '';

        if (boardRows === null) {
            const wait = document.createElement('p');
            wait.className = 'as-note';
            text(wait, STRINGS.searching);
            el.boardBody.appendChild(wait);
            return;
        }

        if (!boardRows.rows.length) {
            const empty = document.createElement('p');
            empty.className = 'as-note';
            text(empty, boardRows.persisted ? STRINGS.boardEmpty : STRINGS.noStats);
            el.boardBody.appendChild(empty);
            return;
        }

        const head = document.createElement('div');
        head.className = 'as-rank as-rank--head';

        // Le prime due colonne (posto e faccia) restano vuote: la loro
        // intestazione sarebbe una parola per dire una cosa che si vede.
        [
            ['as-rank__place', ''],
            ['as-rank__face', ''],
            ['as-rank__name', STRINGS.boardWho],
            ['as-rank__score', STRINGS.boardGuessed],
            ['as-rank__meta', STRINGS.boardRest],
        ].forEach(([cls, label]) => {
            const cell = document.createElement('span');
            cell.className = cls;
            text(cell, label);
            head.appendChild(cell);
        });

        el.boardBody.appendChild(head);

        const list = document.createElement('div');
        list.className = 'as-ranks';

        const me = boardRows.me;
        boardRows.rows.forEach((row) => list.appendChild(boardRow(row, me && row.user_id === me.user_id)));
        el.boardBody.appendChild(list);

        // Se il giocatore è fuori dai cinquanta lo si mostra comunque in fondo,
        // staccato: è l'unica riga che gli dice se sta salendo.
        if (me && !boardRows.rows.some((row) => row.user_id === me.user_id)) {
            const gap = document.createElement('div');
            gap.className = 'as-ranks__gap';
            text(gap, '⋯');
            el.boardBody.appendChild(gap);

            const mine = document.createElement('div');
            mine.className = 'as-ranks';
            mine.appendChild(boardRow(me, true));
            el.boardBody.appendChild(mine);
        }
    }

    async function openBoard() {
        openModal(el.boardModal);
        boardRows = null;
        renderBoard();

        try {
            boardRows = await loadBoard(boardPeriod);
        } catch (error) {
            boardRows = { rows: [], me: null, persisted: true };
            toast(error.message || STRINGS.loadError);
        }

        renderBoard();
    }


    /* ── Modali ────────────────────────────────────────────────────────── */

    function openModal(modal) {
        if (modal) modal.hidden = false;
    }

    function closeModal(modal) {
        if (modal) modal.hidden = true;
    }

    function openStats() {
        renderStats();
        openModal(el.statsModal);
    }

    /* ── Avvio ─────────────────────────────────────────────────────────── */

    function wait(ms) {
        return new Promise((resolve) => setTimeout(resolve, reducedMotion ? 0 : ms));
    }

    /**
     * Carica una sigla e rifà la scena.
     *
     * `query` dice al server che cosa vogliamo: niente per riprendere quella
     * dove si era, `new=1` per ripescare questo posto, `next=1` per passare al
     * posto successivo della serie.
     */
    async function start(query) {
        if (busy) return;
        busy = true;

        const showing = el.game && !el.game.hidden;

        // Anche la prima apparizione entra in dissolvenza: la classe c'e' gia'
        // quando il riquadro smette di essere nascosto.
        root.classList.add('as-stage--swap');

        // Passare da un personaggio all'altro è un cambio di scena: si spegne
        // e si riaccende, invece di sostituire tutto di scatto.
        if (showing) {
            await wait(280);
        } else if (el.boot) {
            el.boot.hidden = false;
        }

        if (el.error) el.error.hidden = true;

        stopPlayback();
        dropClip();
        closeList();
        clearTimeout(suspenseTimer);
        document.body.classList.remove('as-is-suspense');
        selected = null;
        document.body.classList.remove('as-is-reveal');
        if (el.input) el.input.value = '';

        try {
            const payload = await loadState(query);
            state = payload;
            if (el.boot) el.boot.hidden = true;
            if (el.game) el.game.hidden = false;
            render();
            await wait(30);
            root.classList.remove('as-stage--swap');
        } catch (error) {
            if (el.boot) el.boot.hidden = true;
            root.classList.remove('as-stage--swap');
            if (el.error) {
                el.error.hidden = false;
                text(el.error.querySelector('[data-as-error-text]') || el.error, error.message || STRINGS.loadError);
            }
        } finally {
            busy = false;
            syncControls();
        }
    }

    async function submitGuess(body) {
        if (busy) return;
        busy = true;
        syncControls();

        try {
            const payload = await sendGuess(body);

            // Fotografia dell'ascolto prima che lo stato cambi: dove era la
            // testina, se stava suonando, e fin dove era arrivato lo sblocco.
            const wasPlaying = !audio.paused;

            state = Object.assign({}, state, payload);
            autoplayReveal = state.status === 'won';
            celebrate = state.status === 'won';

            if (state.status !== 'playing') {
                stopPlayback();
                dropClip();
            } else if (wasPlaying) {
                // Stava suonando: il pezzo più lungo si prepara di lato e
                // subentra al confine, senza far tacere niente.
                handoff().catch(() => {});
            } else {
                // Era ferma: il frammento nuovo si prende quando si ripreme
                // play, e riparte da capo come tutti.
                stopPlayback();
                dropClip();
            }

            selected = null;
            if (el.input) el.input.value = '';
            closeList();
            render();
        } catch (error) {
            toast(error.message || STRINGS.loadError);
        } finally {
            busy = false;
            syncControls();
        }
    }

    /* ── Volume ────────────────────────────────────────────────────────── */

    function applyVolume(value, remember) {
        const level = Math.max(0, Math.min(1, value));
        audio.volume = level;
        spare.volume = level;

        if (el.volume) {
            el.volume.value = String(Math.round(level * 100));
            // Il cursore da solo non dice quanto è alzato: la parte a sinistra
            // la coloriamo a mano, perché il track non si riempie da sé.
            el.volume.style.background = 'linear-gradient(90deg, var(--as-accent) '
                + (level * 100) + '%, rgba(255, 255, 255, .1) ' + (level * 100) + '%)';
        }

        if (!remember) return;
        try {
            localStorage.setItem(VOLUME_KEY, String(level));
        } catch (error) { /* niente storage, niente memoria: si riparte da 80% */ }
    }

    let savedVolume = 0.8;
    try {
        const stored = parseFloat(localStorage.getItem(VOLUME_KEY));
        if (isFinite(stored)) savedVolume = stored;
    } catch (error) { /* vedi sopra */ }
    applyVolume(savedVolume, false);

    if (el.volume) {
        el.volume.addEventListener('input', () => applyVolume(el.volume.value / 100, true));
    }

    /* ── Eventi ────────────────────────────────────────────────────────── */

    if (el.play) el.play.addEventListener('click', () => play());

    if (el.skip) {
        el.skip.addEventListener('click', () => {
            if (selected) {
                submitGuess({ action: 'guess', anime_id: selected.id });
                return;
            }
            submitGuess({ action: 'skip' });
        });
    }

    if (el.clear) {
        el.clear.addEventListener('click', () => {
            el.input.value = '';
            selected = null;
            closeList();
            syncControls();
            el.input.focus();
        });
    }

    if (el.input) {
        el.input.addEventListener('input', () => {
            selected = null;
            openList(el.input.value);
            syncControls();
        });

        el.input.addEventListener('focus', () => {
            if (state && state.status === 'playing') openList(el.input.value);
        });

        el.input.addEventListener('blur', () => setTimeout(closeList, 120));

        el.input.addEventListener('keydown', (event) => {
            if (el.list.hidden) {
                if (event.key === 'ArrowDown') openList(el.input.value);
                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (!filtered.length) return;
                highlighted = (highlighted + (event.key === 'ArrowDown' ? 1 : -1) + filtered.length) % filtered.length;
                paintHighlight();
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (highlighted >= 0) choose(highlighted);
            } else if (event.key === 'Escape') {
                closeList();
            }
        });
    }

    root.querySelectorAll('[data-as-new]').forEach((node) => {
        node.addEventListener('click', () => start('new=1'));
    });

    document.querySelectorAll('[data-as-open-stats]').forEach((node) => {
        node.addEventListener('click', openStats);
    });

    document.querySelectorAll('[data-as-open-board]').forEach((node) => {
        node.addEventListener('click', openBoard);
    });

    document.querySelectorAll('[data-as-open-rules]').forEach((node) => {
        node.addEventListener('click', () => openModal(el.rulesModal));
    });

    document.querySelectorAll('[data-as-close]').forEach((node) => {
        node.addEventListener('click', () => closeModal(node.closest('.as-modal')));
    });

    document.querySelectorAll('.as-modal').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal(modal);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.as-modal').forEach(closeModal);
            return;
        }

        // La barra spaziatrice fa partire la traccia, ma non mentre si scrive.
        if (event.code === 'Space') {
            const tag = (document.activeElement && document.activeElement.tagName) || '';
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'BUTTON' || tag === 'SELECT') return;
            event.preventDefault();
            play();
        }
    });

    window.addEventListener('pagehide', dropClip);

    start('');
})();
