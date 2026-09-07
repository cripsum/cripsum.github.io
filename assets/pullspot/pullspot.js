/*
 * Cripsum™ — Pullspot
 *
 * Il client non sa mai chi sia il personaggio finché la partita non è finita:
 * i tentativi li valida il server e l'audio arriva già tagliato ai secondi
 * sbloccati. Qui dentro ci sono solo il lettore, la ricerca e la vetrina.
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
            wonAt: (n) => (n === 1 ? 'Preso al primo colpo' : 'Indovinato al ' + n + 'º tentativo'),
            lost: 'Nessuno l\'ha presa. Era:',
            share: 'Condividi',
            copied: 'Risultato copiato',
            copyFailed: 'Non sono riuscito a copiare',
            newTrack: 'Nuova traccia',
            stats: 'Statistiche',
            played: 'Giocate',
            winRate: '% vinte',
            streakLabel: 'Serie',
            best: 'Record',
            distribution: 'Tentativi usati',
            noStats: 'Le statistiche non sono ancora attive su questo sito: quello che giochi adesso non viene salvato.',
            loadError: 'Non riesco a caricare il gioco. Riprova tra poco.',
            audioError: 'Traccia non disponibile.',
        },
        en: {
            skipped: 'Skipped',
            giveUp: 'Give up',
            noResults: 'No character',
            attempt: (n, max) => 'Guess ' + n + '/' + max,
            streak: (n) => 'Streak ' + n,
            unlocked: 'unlocked',
            fullTrack: 'full track',
            wonAt: (n) => (n === 1 ? 'First try' : 'Got it on try ' + n),
            lost: 'Nobody got it. It was:',
            share: 'Share',
            copied: 'Result copied',
            copyFailed: 'Could not copy',
            newTrack: 'New track',
            stats: 'Statistics',
            played: 'Played',
            winRate: 'Win %',
            streakLabel: 'Streak',
            best: 'Best',
            distribution: 'Guess distribution',
            noStats: 'Statistics are not enabled on this site yet: what you play now is not being saved.',
            loadError: 'Could not load the game. Try again shortly.',
            audioError: 'Track unavailable.',
        },
    }[LANG];

    const el = {
        boot: root.querySelector('[data-ps-boot]'),
        game: root.querySelector('[data-ps-game]'),
        error: root.querySelector('[data-ps-error]'),
        metaLeft: root.querySelector('[data-ps-meta-left]'),
        metaRight: root.querySelector('[data-ps-meta-right]'),
        rows: root.querySelector('[data-ps-rows]'),
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
        result: root.querySelector('[data-ps-result]'),
        toast: root.querySelector('[data-ps-toast]'),
        statsModal: document.querySelector('[data-ps-stats-modal]'),
        statsBody: document.querySelector('[data-ps-stats-body]'),
        rulesModal: document.querySelector('[data-ps-rules-modal]'),
    };

    const skipLabel = el.skip ? el.skip.querySelector('span') : null;

    let state = null;
    let characters = [];
    let highlighted = -1;
    let filtered = [];
    let busy = false;
    let segments = [];

    const audio = new Audio();
    audio.preload = 'auto';
    let clipUrl = null;
    let clipKey = '';
    let clipLoading = null;
    let rafId = 0;

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

    let toastTimer = 0;
    function toast(message) {
        if (!el.toast) return;
        text(el.toast, message);
        el.toast.classList.add('ps-toast--on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.toast.classList.remove('ps-toast--on'), 2200);
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
        // precedente, e il cronometro mostra un totale che non esiste piu'.
        audio.removeAttribute('src');
        audio.load();
    }

    function setPlayIcon(isPlaying) {
        if (!el.play) return;
        el.play.innerHTML = '<i class="fa-solid fa-' + (isPlaying ? 'pause' : 'play') + '"></i>';
        el.play.classList.toggle('ps-play--on', isPlaying);
        el.play.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
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

    async function play() {
        if (!state) return;
        if (!audio.paused) {
            stopPlayback();
            return;
        }

        try {
            await ensureClip();
        } catch (error) {
            toast(STRINGS.audioError);
            return;
        }

        audio.currentTime = 0;
        try {
            await audio.play();
        } catch (error) {
            return;
        }

        setPlayIcon(true);
        cancelAnimationFrame(rafId);
        rafId = requestAnimationFrame(tick);
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
            : state.steps.map((to, index) => ({
                from: index === 0 ? 0 : state.steps[index - 1],
                to: to,
                grow: to - (index === 0 ? 0 : state.steps[index - 1]),
            }));

        bounds.forEach((bound) => {
            const seg = document.createElement('div');
            seg.className = 'ps-seg';
            seg.style.flexGrow = String(bound.grow);
            seg.style.flexBasis = '4px';

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
        filtered = (needle === ''
            ? characters.slice(0, 40)
            : characters.filter((character) => normalize(character.nome).includes(needle)).slice(0, 40));

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
        text(el.metaLeft, STRINGS.attempt(Math.min(state.attempt + (done ? 0 : 1), state.max_attempts), state.max_attempts));

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
            text(skipLabel, el.skip.dataset.psSkipWord || skipLabel.textContent);
            const bonus = Math.round((next - state.steps[index]) * 10) / 10;
            text(el.skipBonus, '+' + formatStep(bonus));
        } else {
            text(skipLabel, STRINGS.giveUp);
            text(el.skipBonus, '');
        }

        syncControls();
    }

    function syncControls() {
        if (el.skip) el.skip.disabled = busy || !state || state.status !== 'playing';
        if (el.input) el.input.disabled = busy;
        if (el.clear) el.clear.hidden = !el.input || !el.input.value;
    }

    function squares() {
        const marks = [];
        for (let index = 0; index < state.max_attempts; index += 1) {
            const guess = state.guesses[index];
            if (!guess) marks.push('⬜');
            else if (guess.type === 'skip') marks.push('🔇');
            else if (guess.type === 'correct') marks.push('🟩');
            else marks.push('🟥');
        }
        return marks.join('');
    }

    function shareText() {
        const score = (state.status === 'won' ? state.guesses.length : 'X') + '/' + state.max_attempts;

        return 'Pullspot ' + score + '\n' + squares() + '\n' + window.location.origin + '/' + LANG + '/pullspot';
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

    function button(className, icon, label, handler) {
        const node = document.createElement('button');
        node.type = 'button';
        node.className = className;
        node.innerHTML = '<i class="fa-solid fa-' + icon + '"></i> ';
        node.appendChild(document.createTextNode(label));
        node.addEventListener('click', handler);
        return node;
    }

    function renderResult() {
        if (!el.result) return;

        if (!state || state.status === 'playing' || !state.answer) {
            el.result.hidden = true;
            el.result.innerHTML = '';
            return;
        }

        const won = state.status === 'won';
        el.result.className = 'ps-result ps-result--' + (won ? 'won' : 'lost');
        el.result.hidden = false;
        el.result.innerHTML = '';

        const verdict = document.createElement('p');
        verdict.className = 'ps-result__verdict';
        text(verdict, won ? STRINGS.wonAt(state.guesses.length) : STRINGS.lost);
        el.result.appendChild(verdict);

        if (state.answer.image_url) {
            const art = document.createElement('img');
            art.className = 'ps-result__art';
            art.src = state.answer.image_url;
            art.alt = state.answer.nome;
            art.loading = 'lazy';
            el.result.appendChild(art);
        }

        const name = document.createElement('h2');
        name.className = 'ps-result__name';
        text(name, state.answer.nome);
        el.result.appendChild(name);

        if (state.answer.rarita) {
            const rarity = document.createElement('span');
            rarity.className = 'ps-result__rarity';
            text(rarity, state.answer.rarita);
            el.result.appendChild(rarity);
        }

        const marks = document.createElement('p');
        marks.className = 'ps-result__squares';
        text(marks, squares());
        el.result.appendChild(marks);

        const primary = document.createElement('div');
        primary.className = 'ps-result__actions';
        primary.appendChild(button('ps-btn ps-btn--go', 'rotate', STRINGS.newTrack, () => start(true)));
        el.result.appendChild(primary);

        const secondary = document.createElement('div');
        secondary.className = 'ps-result__actions';
        secondary.style.marginTop = '.5rem';
        secondary.appendChild(button('ps-btn', 'share-nodes', STRINGS.share, share));
        secondary.appendChild(button('ps-btn', 'chart-simple', STRINGS.stats, openStats));
        el.result.appendChild(secondary);
    }

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
        title.style.marginBottom = '.5rem';
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

        buildSegments();
        renderRows();
        renderChips();
        renderMeta();
        renderControls();
        renderResult();
        renderStats();
        setPlayIcon(false);
        paint(0);

        // A partita finita la traccia intera si scarica subito: serve la sua
        // durata vera per il cronometro, e comunque la si vuole risentire.
        if (state.full) ensureClip().then(() => paint(0)).catch(() => {});
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
            if (el.input) el.input.value = '';
            closeList();
            stopPlayback();
            dropClip();
            render();

            // Come su allspot il frammento più lungo parte da solo: il click
            // sul pulsante vale come gesto dell'utente per l'autoplay.
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
            // la coloriamo a mano, perché il track non si può riempire in CSS.
            el.volume.style.background = 'linear-gradient(90deg, var(--ps-green) '
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

    if (el.play) el.play.addEventListener('click', play);

    if (el.skip) {
        el.skip.dataset.psSkipWord = skipLabel ? skipLabel.textContent : '';
        el.skip.addEventListener('click', () => submitGuess({ action: 'skip' }));
    }

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
