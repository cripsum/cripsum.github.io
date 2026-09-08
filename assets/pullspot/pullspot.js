/*
 * Cripsum™ — Pullspot
 *
 * Il client non sa mai chi sia il personaggio finché la partita non è finita:
 * i tentativi li valida il server e l'audio arriva già tagliato ai secondi
 * sbloccati. Qui dentro ci sono il lettore, la ricerca e la rivelazione.
 *
 * Alla rivelazione la pagina prende il colore del personaggio, ricavato dalla
 * sua immagine: tutto quello che è colorato legge --ps-accent, quindi cambia
 * insieme senza che nessuno debba saperlo.
 */

(function () {
    'use strict';

    const root = document.querySelector('[data-ps-root]');
    if (!root) return;

    const LANG = window.PULLSPOT_LANG === 'en' ? 'en' : 'it';
    const CSRF = window.PULLSPOT_CSRF || '';
    const DECIMAL = LANG === 'en' ? '.' : ',';
    const VOLUME_KEY = 'cripsum.pullspot.volume';

    const STRINGS = {
        it: {
            skipped: 'Saltato',
            giveUp: 'Arrenditi',
            noResults: 'Nessun personaggio',
            attempt: (n, max) => 'Tentativo ' + n + '/' + max,
            streak: (n) => 'Serie ' + n,
            unlocked: 'sbloccati',
            fullTrack: 'traccia intera',
            wonIn: (s) => 'Indovinato in ' + s,
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
            distribution: 'Tentativi usati',
            noStats: 'Le statistiche non sono ancora attive su questo sito: quello che giochi adesso non viene salvato.',
            loadError: 'Non riesco a caricare il gioco. Riprova tra poco.',
            audioError: 'La traccia non parte. Riprova.',
            shareWon: (s, n, max) => 'Pullspot: indovinato in ' + s + ', al tentativo ' + n + ' su ' + max + '.',
            shareLost: (max) => 'Pullspot: non l\'ho presa, ' + max + ' tentativi buttati.',
        },
        en: {
            skipped: 'Skipped',
            giveUp: 'Give up',
            noResults: 'No character',
            attempt: (n, max) => 'Guess ' + n + '/' + max,
            streak: (n) => 'Streak ' + n,
            unlocked: 'unlocked',
            fullTrack: 'full track',
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
            distribution: 'Guess distribution',
            noStats: 'Statistics are not enabled on this site yet: what you play now is not being saved.',
            loadError: 'Could not load the game. Try again shortly.',
            audioError: 'The track will not start. Try again.',
            shareWon: (s, n, max) => 'Pullspot: guessed in ' + s + ', on guess ' + n + ' of ' + max + '.',
            shareLost: (max) => 'Pullspot: missed it, all ' + max + ' guesses gone.',
        },
    }[LANG];

    const el = {
        boot: root.querySelector('[data-ps-boot]'),
        game: root.querySelector('[data-ps-game]'),
        error: root.querySelector('[data-ps-error]'),
        metaLeft: root.querySelector('[data-ps-meta-left]'),
        metaRight: root.querySelector('[data-ps-meta-right]'),
        rows: root.querySelector('[data-ps-rows]'),
        player: root.querySelector('[data-ps-player]'),
        segments: root.querySelector('[data-ps-segments]'),
        marker: root.querySelector('[data-ps-marker]'),
        play: root.querySelector('[data-ps-play]'),
        clock: root.querySelector('[data-ps-clock]'),
        clockLabel: root.querySelector('[data-ps-clock-label]'),
        controls: root.querySelector('[data-ps-controls]'),
        input: root.querySelector('[data-ps-input]'),
        clear: root.querySelector('[data-ps-clear]'),
        list: root.querySelector('[data-ps-list]'),
        skip: root.querySelector('[data-ps-skip]'),
        skipBonus: root.querySelector('[data-ps-skip-bonus]'),
        chips: root.querySelector('[data-ps-chips]'),
        volume: root.querySelector('[data-ps-volume]'),
        reveal: root.querySelector('[data-ps-reveal]'),
        toast: root.querySelector('[data-ps-toast]'),
        confetti: document.querySelector('[data-ps-confetti]'),
        statsModal: document.querySelector('[data-ps-stats-modal]'),
        statsBody: document.querySelector('[data-ps-stats-body]'),
        rulesModal: document.querySelector('[data-ps-rules-modal]'),
    };

    const skipWord = el.skip ? el.skip.querySelector('span') : null;
    const skipWordText = skipWord ? skipWord.textContent : '';

    let state = null;
    let characters = [];
    let excluded = new Set();
    let highlighted = -1;
    let filtered = [];
    let busy = false;
    let segments = [];
    let revealIcon = null;

    const audio = new Audio();
    audio.preload = 'auto';
    let clipUrl = null;
    let clipKey = '';
    let clipLoading = null;
    let rafId = 0;
    let starting = false;
    let autoplayReveal = false;
    let celebrate = false;

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

    function normalize(value) {
        return String(value)
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '');
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
        el.toast.classList.add('ps-toast--on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.toast.classList.remove('ps-toast--on'), 2400);
    }

    /* ── Colore del personaggio ────────────────────────────────────────── */

    const DEFAULT_ACCENT = { h: 152, s: 75, l: 51 };

    function setAccent(accent) {
        const a = accent || DEFAULT_ACCENT;
        const tone = (alpha) => 'hsl(' + a.h + ' ' + a.s + '% ' + a.l + '%' + (alpha == null ? '' : ' / ' + alpha) + ')';
        const style = document.body.style;

        style.setProperty('--ps-accent', tone());
        style.setProperty('--ps-accent-soft', tone(.13));
        style.setProperty('--ps-accent-line', tone(.34));
        style.setProperty('--ps-accent-glow', tone(.34));
        style.setProperty('--ps-accent-ink', 'hsl(' + a.h + ' 55% 7%)');
    }

    function rgbToAccent(r, g, b) {
        const max = Math.max(r, g, b) / 255;
        const min = Math.min(r, g, b) / 255;
        const delta = max - min;
        let h = 0;

        if (delta > 0) {
            if (max === r / 255) h = ((g - b) / 255 / delta) % 6;
            else if (max === g / 255) h = (b - r) / 255 / delta + 2;
            else h = (r - g) / 255 / delta + 4;
            h = Math.round(h * 60);
            if (h < 0) h += 360;
        }

        const l = (max + min) / 2;
        const s = delta === 0 ? 0 : delta / (1 - Math.abs(2 * l - 1));

        // Sul nero un colore spento sparisce: lo tiriamo su in saturazione e
        // lo teniamo in una fascia di luminosità dove il testo scuro si legge.
        return {
            h: h,
            s: Math.round(Math.max(.55, Math.min(.95, s)) * 100),
            l: Math.round(Math.max(52, Math.min(68, l * 100))),
        };
    }

    /** Colore di ripiego quando l'immagine manca: stabile per personaggio. */
    function accentFromId(id) {
        return { h: Math.round((id * 137.508) % 360), s: 72, l: 58 };
    }

    function accentFromImage(url) {
        return new Promise((resolve) => {
            const image = new Image();

            image.onload = function () {
                try {
                    const size = 28;
                    const canvas = document.createElement('canvas');
                    canvas.width = size;
                    canvas.height = size;

                    const ctx = canvas.getContext('2d', { willReadFrequently: true });
                    ctx.drawImage(image, 0, 0, size, size);

                    const data = ctx.getImageData(0, 0, size, size).data;
                    let r = 0, g = 0, b = 0, weight = 0;

                    for (let i = 0; i < data.length; i += 4) {
                        if (data[i + 3] < 128) continue;
                        const max = Math.max(data[i], data[i + 1], data[i + 2]);
                        const min = Math.min(data[i], data[i + 1], data[i + 2]);
                        // I pixel spenti pesano poco: il colore lo danno i vivi.
                        const w = (max - min) / 255 + .06;
                        r += data[i] * w;
                        g += data[i + 1] * w;
                        b += data[i + 2] * w;
                        weight += w;
                    }

                    resolve(weight ? rgbToAccent(r / weight, g / weight, b / weight) : null);
                } catch (error) {
                    // Immagine da un altro dominio: la tela è marchiata e non
                    // si può leggere. Si va di ripiego.
                    resolve(null);
                }
            };

            image.onerror = () => resolve(null);
            image.src = url;
        });
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

    function loadState(fresh) {
        return request('/api/pullspot/state.php?lang=' + LANG + (fresh ? '&new=1' : ''));
    }

    function sendGuess(body) {
        return request('/api/pullspot/guess.php?lang=' + LANG, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(Object.assign({ csrf_token: CSRF }, body)),
        });
    }

    /* ── Lettore ───────────────────────────────────────────────────────── */

    function trackDuration() {
        return isFinite(audio.duration) && audio.duration > 0 ? audio.duration : 0;
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
        return !!clipUrl && clipKey === currentClipKey() && audio.readyState >= 2;
    }

    function ensureClip() {
        const key = currentClipKey();
        if (clipUrl && clipKey === key) return Promise.resolve();
        if (clipLoading && clipKey === key) return clipLoading;

        clipKey = key;
        clipLoading = fetch('/api/pullspot/audio.php?lang=' + LANG, {
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then((response) => {
                if (!response.ok) throw new Error('audio ' + response.status);
                return response.blob();
            })
            .then((blob) => {
                if (clipUrl) URL.revokeObjectURL(clipUrl);
                clipUrl = URL.createObjectURL(blob);
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
        if (clipUrl) URL.revokeObjectURL(clipUrl);
        clipUrl = null;
        clipKey = '';

        // Senza questo l'elemento continua a dichiarare la durata del pezzo
        // precedente, e il cronometro mostra un totale che non esiste più.
        audio.removeAttribute('src');
        audio.load();
    }

    /**
     * Aspetta che l'elemento abbia abbastanza dati per suonare.
     *
     * Senza questa attesa `currentTime = 0` può essere rifiutato dal browser
     * (i metadati non ci sono ancora) e il play muore in silenzio: era il
     * motivo per cui a volte premevi e non partiva niente.
     */
    function whenReady() {
        if (audio.readyState >= 2) return Promise.resolve();

        return new Promise((resolve, reject) => {
            let settled = false;

            function cleanup() {
                clearTimeout(timer);
                audio.removeEventListener('canplay', ok);
                audio.removeEventListener('loadeddata', ok);
                audio.removeEventListener('error', ko);
            }

            function ok() {
                if (settled) return;
                settled = true;
                cleanup();
                resolve();
            }

            function ko() {
                if (settled) return;
                settled = true;
                cleanup();
                reject(new Error('audio'));
            }

            const timer = setTimeout(ko, 9000);
            audio.addEventListener('canplay', ok);
            audio.addEventListener('loadeddata', ok);
            audio.addEventListener('error', ko);
        });
    }

    function setPlayIcon(isPlaying) {
        if (el.play) {
            el.play.innerHTML = '';
            el.play.appendChild(icon(isPlaying ? 'pause' : 'play'));
            el.play.classList.toggle('ps-play--on', isPlaying);
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
        el.play.classList.toggle('ps-play--busy', isBusy);
        if (isBusy) {
            el.play.innerHTML = '';
            el.play.appendChild(icon('circle-notch'));
        } else {
            setPlayIcon(!audio.paused);
        }
    }

    function stopPlayback() {
        audio.pause();
        cancelAnimationFrame(rafId);
        rafId = 0;
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

    function startPlayback() {
        try {
            audio.currentTime = 0;
        } catch (error) { /* alcuni browser lo rifiutano: si parte da dove sta */ }

        return audio.play().then(() => {
            setPlayIcon(true);
            cancelAnimationFrame(rafId);
            rafId = requestAnimationFrame(tick);
        });
    }

    async function play(silent) {
        if (!state || starting) return;

        if (!audio.paused) {
            stopPlayback();
            return;
        }

        // Percorso veloce: se il pezzo è già pronto si parte dentro al click,
        // che è quello che i browser vogliono per non bloccare l'audio.
        if (clipReady()) {
            startPlayback().catch(() => {
                if (!silent) toast(STRINGS.audioError);
            });
            return;
        }

        starting = true;
        setPlayBusy(true);

        try {
            await ensureClip();
            await whenReady();
            await startPlayback();
        } catch (error) {
            if (!silent) toast(STRINGS.audioError);
        } finally {
            starting = false;
            setPlayBusy(false);
        }
    }

    audio.addEventListener('ended', stopPlayback);

    // A partita finita la traccia è intera: la durata la conosciamo solo dopo
    // che il browser ha letto l'intestazione del file.
    audio.addEventListener('loadedmetadata', function () {
        if (audio.paused) paint(0);
    });

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
            seg.className = 'ps-seg';
            seg.style.flexGrow = String(bound.grow);
            seg.style.flexBasis = '5px';

            const unlocked = document.createElement('div');
            unlocked.className = 'ps-seg__unlocked';

            const played = document.createElement('div');
            played.className = 'ps-seg__played';

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
        if (!el.list) return;
        el.list.hidden = true;
        el.list.innerHTML = '';
        if (el.input) el.input.setAttribute('aria-expanded', 'false');
        highlighted = -1;
        filtered = [];
    }

    function openList(query) {
        if (!el.list) return;

        const needle = normalize(query);

        // Un nome già provato non torna nell'elenco: sprecare un tentativo
        // due volte sullo stesso personaggio non è una scelta, è un incidente.
        const pool = characters.filter((character) => !excluded.has(character.id));

        filtered = (needle === ''
            ? pool.slice(0, 40)
            : pool.filter((character) => normalize(character.nome).includes(needle)).slice(0, 40));

        el.list.innerHTML = '';

        if (!filtered.length) {
            const empty = document.createElement('li');
            empty.className = 'ps-option__empty';
            text(empty, STRINGS.noResults);
            el.list.appendChild(empty);
            el.list.hidden = false;
            highlighted = -1;
            return;
        }

        filtered.forEach((character, index) => {
            const option = document.createElement('li');
            option.className = 'ps-option';
            option.setAttribute('role', 'option');

            if (character.image_url) {
                const art = document.createElement('img');
                art.src = character.image_url;
                art.alt = '';
                art.loading = 'lazy';
                option.appendChild(art);
            }

            const name = document.createElement('span');
            text(name, character.nome);
            option.appendChild(name);

            option.addEventListener('mousedown', (event) => {
                event.preventDefault();
                choose(index);
            });

            el.list.appendChild(option);
        });

        highlighted = 0;
        paintHighlight();
        el.list.hidden = false;
        if (el.input) el.input.setAttribute('aria-expanded', 'true');
    }

    function paintHighlight() {
        if (!el.list) return;
        Array.from(el.list.children).forEach((node, index) => {
            node.classList.toggle('ps-option--active', index === highlighted);
        });
        const active = el.list.children[highlighted];
        if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest' });
    }

    /** Scegliere un nome dall'elenco è già il tentativo: non c'è conferma. */
    function choose(index) {
        const character = filtered[index];
        if (!character || busy) return;

        closeList();
        submitGuess({ action: 'guess', character_id: character.id });
    }

    /* ── Vetrina ───────────────────────────────────────────────────────── */

    function renderRows() {
        if (!el.rows || !state) return;

        el.rows.innerHTML = '';

        for (let index = 0; index < state.max_attempts; index += 1) {
            const guess = state.guesses[index];
            const row = document.createElement('li');
            row.className = 'ps-row';

            const number = document.createElement('span');
            number.className = 'ps-row__no';
            text(number, index + 1);

            const label = document.createElement('span');
            label.className = 'ps-row__text';

            if (guess) {
                row.classList.add('ps-row--' + guess.type);
                text(label, guess.type === 'skip' ? STRINGS.skipped : (guess.nome || '—'));
            } else if (index === state.guesses.length && state.status === 'playing') {
                row.classList.add('ps-row--now');
                text(label, formatStep(state.steps[Math.min(index, state.steps.length - 1)]));
            } else {
                text(label, '—');
            }

            row.appendChild(number);
            row.appendChild(label);
            el.rows.appendChild(row);
        }
    }

    function renderChips() {
        if (!el.chips || !state) return;

        el.chips.innerHTML = '';

        state.steps.forEach((step, index) => {
            const chip = document.createElement('span');
            chip.className = 'ps-chip' + (state.full || index <= state.attempt ? ' ps-chip--on' : '');
            text(chip, formatStep(step));
            el.chips.appendChild(chip);
        });
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

        const index = state.guesses.length;
        const next = state.steps[index + 1];

        if (typeof next === 'number') {
            text(skipWord, skipWordText);
            text(el.skipBonus, '+' + formatStep(Math.round((next - state.steps[index]) * 10) / 10));
        } else {
            text(skipWord, STRINGS.giveUp);
            text(el.skipBonus, '');
        }

        syncControls();
    }

    function syncControls() {
        if (el.skip) el.skip.disabled = busy || !state || state.status !== 'playing';
        if (el.input) el.input.disabled = busy;
        if (el.clear) el.clear.hidden = !el.input || !el.input.value;
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
        const line = state.status === 'won'
            ? STRINGS.shareWon(winningSeconds(), state.guesses.length, state.max_attempts)
            : STRINGS.shareLost(state.max_attempts);

        return line + '\n' + window.location.origin + '/' + LANG + '/pullspot';
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
            if (el.player) el.player.hidden = false;
            document.body.classList.remove('ps-is-reveal');
            return;
        }

        const won = state.status === 'won';

        // Il lettore sparisce: da qui in poi la scena è del personaggio.
        if (el.player) el.player.hidden = true;
        el.reveal.hidden = false;
        el.reveal.innerHTML = '';
        document.body.classList.add('ps-is-reveal');

        const card = document.createElement('div');
        card.className = 'ps-card';

        const art = document.createElement('button');
        art.type = 'button';
        art.className = 'ps-card__art';
        art.addEventListener('click', () => play());

        if (state.answer.image_url) {
            const image = document.createElement('img');
            image.src = state.answer.image_url;
            image.alt = state.answer.nome;
            art.appendChild(image);
        }

        revealIcon = document.createElement('span');
        revealIcon.className = 'ps-card__icon';
        revealIcon.appendChild(icon('play'));
        art.appendChild(revealIcon);
        card.appendChild(art);

        const name = document.createElement('h2');
        name.className = 'ps-card__name';
        text(name, state.answer.nome);
        card.appendChild(name);

        const meta = document.createElement('p');
        meta.className = 'ps-card__meta';
        text(meta, [
            state.answer.rarita || null,
            won
                ? (state.guesses.length === 1 ? STRINGS.oneTry : STRINGS.triesUsed(state.guesses.length, state.max_attempts))
                : STRINGS.triesUsed(state.max_attempts, state.max_attempts),
        ].filter(Boolean).join(' · '));
        card.appendChild(meta);

        const pill = document.createElement('span');
        pill.className = 'ps-pill' + (won ? '' : ' ps-pill--lost');
        text(pill, won ? STRINGS.wonIn(winningSeconds()) : STRINGS.lostPill);
        card.appendChild(pill);

        const actions = document.createElement('div');
        actions.className = 'ps-reveal__actions';
        actions.appendChild(button('ps-btn', 'share-nodes', STRINGS.share, share));
        actions.appendChild(button('ps-btn ps-btn--go', 'forward', STRINGS.newTrack, () => start(true)));
        card.appendChild(actions);

        el.reveal.appendChild(card);

        // I coriandoli festeggiano il momento, non lo stato: ricaricando la
        // pagina su una partita già vinta non devono ripartire.
        const party = won && celebrate;
        celebrate = false;

        // Il colore lo detta il personaggio, e con lui si tingono fascio,
        // pastiglia, cornice e coriandoli.
        const paintAccent = (accent) => {
            setAccent(accent || accentFromId(state.answer.id));
            if (party) confetti();
        };

        if (state.answer.image_url) {
            accentFromImage(state.answer.image_url).then(paintAccent);
        } else {
            paintAccent(null);
        }
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

        const accent = getComputedStyle(document.body).getPropertyValue('--ps-accent').trim() || '#22e07d';
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
        figures.className = 'ps-figures';

        [
            [stats.played || 0, STRINGS.played],
            [(stats.win_rate || 0) + '%', STRINGS.winRate],
            [stats.streak || 0, STRINGS.streakLabel],
            [stats.best_streak || 0, STRINGS.best],
        ].forEach(([value, label]) => {
            const figure = document.createElement('div');
            figure.className = 'ps-figure';

            const valueNode = document.createElement('div');
            valueNode.className = 'ps-figure__value';
            text(valueNode, value);

            const labelNode = document.createElement('div');
            labelNode.className = 'ps-figure__label';
            text(labelNode, label);

            figure.appendChild(valueNode);
            figure.appendChild(labelNode);
            figures.appendChild(figure);
        });

        el.statsBody.appendChild(figures);

        const title = document.createElement('div');
        title.className = 'ps-figure__label';
        title.style.marginBottom = '.55rem';
        text(title, STRINGS.distribution);
        el.statsBody.appendChild(title);

        const distribution = stats.distribution || [];
        const peak = Math.max(1, ...distribution);
        const bars = document.createElement('div');
        bars.className = 'ps-bars';

        distribution.forEach((count, index) => {
            const isCurrent = state.status === 'won' && state.guesses.length === index + 1;

            const bar = document.createElement('div');
            bar.className = 'ps-bar-row' + (isCurrent ? ' ps-bar-row--now' : '');

            const label = document.createElement('span');
            label.className = 'ps-bar-row__label';
            text(label, String(index + 1));

            const track = document.createElement('div');
            track.className = 'ps-bar-row__track';

            const fill = document.createElement('div');
            fill.className = 'ps-bar-row__fill';
            fill.style.width = Math.max(6, (count / peak) * 100) + '%';
            text(fill, String(count));

            track.appendChild(fill);
            bar.appendChild(label);
            bar.appendChild(track);
            bars.appendChild(bar);
        });

        el.statsBody.appendChild(bars);

        if (stats.persisted === false) {
            const note = document.createElement('p');
            note.className = 'ps-note';
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

        buildSegments();
        renderRows();
        renderChips();
        renderMeta();
        renderControls();
        renderReveal();
        renderStats();
        setPlayIcon(false);
        paint(0);

        // Il pezzo si scarica prima che serva: al click deve partire subito,
        // non dopo un viaggio in rete.
        ensureClip().then(() => {
            paint(0);

            // La musica del personaggio parte da sola appena si scopre chi
            // era. Se il browser la blocca resta il pulsante sulla figura.
            if (state && state.full && autoplayReveal) {
                autoplayReveal = false;
                play(true);
            }
        }).catch(() => {});
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

    async function start(fresh) {
        if (busy) return;
        busy = true;

        if (el.boot) el.boot.hidden = false;
        if (el.game) el.game.hidden = true;
        if (el.error) el.error.hidden = true;

        stopPlayback();
        dropClip();
        closeList();
        setAccent(null);
        document.body.classList.remove('ps-is-reveal');
        if (el.input) el.input.value = '';

        try {
            const payload = await loadState(fresh);
            state = payload;
            characters = payload.characters || characters;
            if (el.boot) el.boot.hidden = true;
            if (el.game) el.game.hidden = false;
            render();
        } catch (error) {
            if (el.boot) el.boot.hidden = true;
            if (el.error) {
                el.error.hidden = false;
                text(el.error.querySelector('[data-ps-error-text]') || el.error, error.message || STRINGS.loadError);
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
            const wasPlaying = state && state.status === 'playing';

            state = Object.assign({}, state, payload);
            autoplayReveal = state.status !== 'playing';
            celebrate = state.status === 'won';
            if (el.input) el.input.value = '';
            closeList();
            stopPlayback();
            dropClip();
            render();

            // Il frammento più lungo parte da solo: il click sul pulsante vale
            // come gesto dell'utente. Alla rivelazione ci pensa render().
            if (wasPlaying && state.status === 'playing') play();
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

        if (el.volume) {
            el.volume.value = String(Math.round(level * 100));
            // Il cursore da solo non dice quanto è alzato: la parte a sinistra
            // la coloriamo a mano, perché il track non si riempie da sé.
            el.volume.style.background = 'linear-gradient(90deg, var(--ps-accent) '
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

    if (el.skip) el.skip.addEventListener('click', () => submitGuess({ action: 'skip' }));

    if (el.clear) {
        el.clear.addEventListener('click', () => {
            el.input.value = '';
            closeList();
            syncControls();
            el.input.focus();
        });
    }

    if (el.input) {
        el.input.addEventListener('input', () => {
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

    root.querySelectorAll('[data-ps-new]').forEach((node) => {
        node.addEventListener('click', () => start(true));
    });

    document.querySelectorAll('[data-ps-open-stats]').forEach((node) => {
        node.addEventListener('click', openStats);
    });

    document.querySelectorAll('[data-ps-open-rules]').forEach((node) => {
        node.addEventListener('click', () => openModal(el.rulesModal));
    });

    document.querySelectorAll('[data-ps-close]').forEach((node) => {
        node.addEventListener('click', () => closeModal(node.closest('.ps-modal')));
    });

    document.querySelectorAll('.ps-modal').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal(modal);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.ps-modal').forEach(closeModal);
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

    start(false);
})();
