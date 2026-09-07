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

    const STRINGS = {
        it: {
            skipped: 'Saltato',
            skip: 'Salta',
            giveUp: 'Arrenditi',
            submit: 'Indovina',
            placeholder: 'Cerca un personaggio…',
            noResults: 'Nessun personaggio',
            wonAt: (n) => (n === 1 ? 'Preso al primo colpo!' : 'Indovinato al ' + n + 'º tentativo!'),
            lost: 'Nessuno l\'ha presa. Era:',
            nextIn: 'Prossimo Pullspot tra',
            share: 'Condividi',
            copied: 'Risultato copiato',
            copyFailed: 'Non sono riuscito a copiare',
            newTrack: 'Nuova traccia',
            stats: 'Statistiche',
            played: 'Giocate',
            winRate: '% vinte',
            streak: 'Serie',
            best: 'Record',
            distribution: 'Tentativi usati',
            noStats: 'Le statistiche non sono ancora attive su questo sito: la partita di oggi resta salvata solo nella sessione del browser.',
            loadError: 'Non riesco a caricare il gioco. Riprova tra poco.',
            audioError: 'Traccia non disponibile.',
            practiceHint: 'Traccia a caso, non conta per le statistiche.',
            puzzle: (n) => 'Pullspot #' + n,
        },
        en: {
            skipped: 'Skipped',
            skip: 'Skip',
            giveUp: 'Give up',
            submit: 'Guess',
            placeholder: 'Search a character…',
            noResults: 'No character',
            wonAt: (n) => (n === 1 ? 'First try!' : 'Got it on try ' + n + '!'),
            lost: 'Nobody got it. It was:',
            nextIn: 'Next Pullspot in',
            share: 'Share',
            copied: 'Result copied',
            copyFailed: 'Could not copy',
            newTrack: 'New track',
            stats: 'Statistics',
            played: 'Played',
            winRate: 'Win %',
            streak: 'Streak',
            best: 'Best',
            distribution: 'Guess distribution',
            noStats: 'Statistics are not enabled on this site yet: today\'s round is only kept in the browser session.',
            loadError: 'Could not load the game. Try again shortly.',
            audioError: 'Track unavailable.',
            practiceHint: 'Random track, it does not count towards your stats.',
            puzzle: (n) => 'Pullspot #' + n,
        },
    }[LANG];

    const el = {
        boot: root.querySelector('[data-ps-boot]'),
        game: root.querySelector('[data-ps-game]'),
        error: root.querySelector('[data-ps-error]'),
        subtitle: root.querySelector('[data-ps-subtitle]'),
        rows: root.querySelector('[data-ps-rows]'),
        unlocked: root.querySelector('[data-ps-unlocked]'),
        played: root.querySelector('[data-ps-played]'),
        marks: root.querySelector('[data-ps-marks]'),
        play: root.querySelector('[data-ps-play]'),
        time: root.querySelector('[data-ps-time]'),
        input: root.querySelector('[data-ps-input]'),
        clear: root.querySelector('[data-ps-clear]'),
        list: root.querySelector('[data-ps-list]'),
        skip: root.querySelector('[data-ps-skip]'),
        submit: root.querySelector('[data-ps-submit]'),
        controls: root.querySelector('[data-ps-controls]'),
        result: root.querySelector('[data-ps-result]'),
        toast: root.querySelector('[data-ps-toast]'),
        statsModal: document.querySelector('[data-ps-stats-modal]'),
        statsBody: document.querySelector('[data-ps-stats-body]'),
        rulesModal: document.querySelector('[data-ps-rules-modal]'),
    };

    let state = null;
    let characters = [];
    let mode = 'daily';
    let selected = null;
    let highlighted = -1;
    let filtered = [];
    let busy = false;
    let countdownTimer = null;

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
        return String(value).replace('.', DECIMAL);
    }

    function formatCountdown(total) {
        const hours = Math.floor(total / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        const seconds = Math.floor(total % 60);
        return [hours, minutes, seconds].map((n) => String(n).padStart(2, '0')).join(':');
    }

    // I nomi arrivano dal database: in pagina ci vanno come testo, mai come HTML.
    function text(node, value) {
        node.textContent = value == null ? '' : String(value);
        return node;
    }

    function normalize(value) {
        return String(value)
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '');
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

    function loadState(options) {
        const params = new URLSearchParams({ mode: mode, lang: LANG });
        if (options && options.fresh) params.set('new', '1');
        return request('/api/pullspot/state.php?' + params.toString());
    }

    function sendGuess(body) {
        return request('/api/pullspot/guess.php?lang=' + LANG, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(Object.assign({ mode: mode, csrf_token: CSRF }, body)),
        });
    }

    /* ── Lettore ───────────────────────────────────────────────────────── */

    function limitSeconds() {
        if (!state) return 0;
        if (state.full) return isFinite(audio.duration) && audio.duration > 0 ? audio.duration : 0;
        return state.unlocked || 0;
    }

    function spanSeconds() {
        const max = state ? state.steps[state.steps.length - 1] : 15;
        if (state && state.full) {
            return isFinite(audio.duration) && audio.duration > 0 ? audio.duration : max;
        }
        return max;
    }

    // La traccia cambia a ogni tentativo: la chiave dice se il pezzo scaricato
    // è ancora quello giusto o se va richiesto di nuovo.
    function currentClipKey() {
        return [mode, state ? state.day : '', state ? state.attempt : 0, state && state.full ? 'full' : 'clip'].join('|');
    }

    function ensureClip() {
        const key = currentClipKey();
        if (clipUrl && clipKey === key) return Promise.resolve();
        if (clipLoading && clipKey === key) return clipLoading;

        clipKey = key;
        clipLoading = fetch('/api/pullspot/audio.php?mode=' + encodeURIComponent(mode) + '&lang=' + LANG, {
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

    function stopPlayback() {
        audio.pause();
        cancelAnimationFrame(rafId);
        rafId = 0;
        renderProgress(0);
        setPlayIcon(false);
    }

    function setPlayIcon(isPlaying) {
        if (!el.play) return;
        el.play.innerHTML = '<i class="fa-solid fa-' + (isPlaying ? 'pause' : 'play') + '"></i>';
        el.play.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
    }

    function renderProgress(current) {
        const span = spanSeconds() || 1;
        const limit = limitSeconds();

        if (el.played) el.played.style.width = Math.min(100, (current / span) * 100) + '%';
        if (el.unlocked) el.unlocked.style.width = (state && state.full ? 100 : Math.min(100, (limit / span) * 100)) + '%';

        // A partita finita la traccia è intera, ma quanto duri lo si sa solo
        // dopo averla scaricata: fino ad allora il totale resta una lineetta.
        if (el.time) text(el.time, formatSeconds(current) + ' / ' + (limit > 0 ? formatSeconds(limit) : '—'));
    }

    function tick() {
        const limit = limitSeconds();
        // Il server manda già il pezzo tagliato: questo è il freno di scorta
        // per i formati che non sappiamo tagliare.
        if (limit > 0 && audio.currentTime >= limit) {
            stopPlayback();
            return;
        }
        renderProgress(audio.currentTime);
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
        if (audio.paused) renderProgress(0);
    });

    /* ── Ricerca ───────────────────────────────────────────────────────── */

    function closeList() {
        if (!el.list) return;
        el.list.hidden = true;
        el.list.innerHTML = '';
        highlighted = -1;
        filtered = [];
    }

    function openList(query) {
        if (!el.list) return;

        const needle = normalize(query);
        filtered = (needle === ''
            ? characters.slice(0, 80)
            : characters.filter((character) => normalize(character.nome).includes(needle)).slice(0, 80));

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
            option.dataset.index = String(index);
            text(option, character.nome);
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
            node.classList.toggle('ps-option--active', index === highlighted);
        });
        const active = el.list.children[highlighted];
        if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest' });
    }

    function choose(index) {
        const character = filtered[index];
        if (!character) return;

        selected = character;
        el.input.value = character.nome;
        closeList();
        syncControls();
        el.submit.focus();
    }

    function clearSelection() {
        selected = null;
        el.input.value = '';
        closeList();
        syncControls();
        el.input.focus();
    }

    /* ── Vetrina ───────────────────────────────────────────────────────── */

    function renderMarks() {
        if (!el.marks || !state) return;

        const span = state.steps[state.steps.length - 1] || 1;
        el.marks.innerHTML = '';

        // I primi due scalini distano meno di mezzo secondo su quindici: le
        // tacche ci stanno tutte, i numeri no. Se ne scrive uno solo ogni
        // tanto, l'ultimo sempre, e il valore esatto resta accanto al lettore.
        let lastLabelled = -Infinity;

        state.steps.forEach((step, index) => {
            const position = (step / span) * 100;
            const mark = document.createElement('span');
            mark.className = 'ps-mark' + (index <= (state.attempt || 0) ? ' ps-mark--reached' : '');
            mark.style.left = position + '%';

            if (position - lastLabelled >= 8 || index === state.steps.length - 1) {
                text(mark, formatStep(step));
                lastLabelled = position;
            }

            el.marks.appendChild(mark);
        });
    }

    function renderRows() {
        if (!el.rows || !state) return;

        el.rows.innerHTML = '';

        for (let index = 0; index < state.max_attempts; index += 1) {
            const guess = state.guesses[index];
            const row = document.createElement('li');
            row.className = 'ps-row';

            const icon = document.createElement('span');
            icon.className = 'ps-row__icon';

            const label = document.createElement('span');
            label.className = 'ps-row__text';

            if (guess) {
                row.classList.add('ps-row--' + guess.type);
                if (guess.type === 'skip') {
                    icon.innerHTML = '<i class="fa-solid fa-forward"></i>';
                    text(label, STRINGS.skipped);
                } else {
                    icon.innerHTML = '<i class="fa-solid fa-' + (guess.type === 'correct' ? 'check' : 'xmark') + '"></i>';
                    text(label, guess.nome || '—');
                }
            } else if (index === state.guesses.length && state.status === 'playing') {
                row.classList.add('ps-row--active');
                icon.innerHTML = '<i class="fa-solid fa-headphones"></i>';
                text(label, formatStep(state.steps[Math.min(index, state.steps.length - 1)]) + 's');
            }

            row.appendChild(icon);
            row.appendChild(label);
            el.rows.appendChild(row);
        }
    }

    function renderControls() {
        const playing = state && state.status === 'playing';

        if (el.controls) el.controls.hidden = !playing;
        if (!playing) return;

        el.input.disabled = false;
        el.input.placeholder = STRINGS.placeholder;

        const index = state.guesses.length;
        const next = state.steps[index + 1];
        const current = state.steps[index];

        if (typeof next === 'number') {
            const bonus = Math.round((next - current) * 10) / 10;
            text(el.skip, STRINGS.skip + ' (+' + formatStep(bonus) + 's)');
        } else {
            text(el.skip, STRINGS.giveUp);
        }

        text(el.submit, STRINGS.submit);
        syncControls();
    }

    function syncControls() {
        const playing = state && state.status === 'playing';
        el.submit.disabled = busy || !playing || !selected;
        el.skip.disabled = busy || !playing;
        if (el.clear) el.clear.hidden = !el.input.value;
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
        const header = state.puzzle
            ? STRINGS.puzzle(state.puzzle) + ' ' + (state.status === 'won' ? state.guesses.length : 'X') + '/' + state.max_attempts
            : 'Pullspot ' + (state.status === 'won' ? state.guesses.length : 'X') + '/' + state.max_attempts;

        return header + '\n' + squares() + '\n' + window.location.origin + '/' + LANG + '/pullspot';
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

    function renderResult() {
        if (!el.result) return;

        clearInterval(countdownTimer);

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

        const actions = document.createElement('div');
        actions.className = 'ps-actions';

        if (mode === 'daily') {
            const next = document.createElement('p');
            next.className = 'ps-result__next';
            el.result.appendChild(next);

            let remaining = state.next_in;
            const paint = () => {
                text(next, STRINGS.nextIn + ' ' + formatCountdown(Math.max(0, remaining)));
                remaining -= 1;
            };
            paint();
            countdownTimer = setInterval(paint, 1000);

            const shareButton = document.createElement('button');
            shareButton.type = 'button';
            shareButton.className = 'ps-btn ps-btn--primary';
            shareButton.innerHTML = '<i class="fa-solid fa-share-nodes"></i> ';
            shareButton.appendChild(document.createTextNode(STRINGS.share));
            shareButton.addEventListener('click', share);
            actions.appendChild(shareButton);
        } else {
            const again = document.createElement('button');
            again.type = 'button';
            again.className = 'ps-btn ps-btn--primary';
            again.innerHTML = '<i class="fa-solid fa-rotate"></i> ';
            again.appendChild(document.createTextNode(STRINGS.newTrack));
            again.addEventListener('click', () => start({ fresh: true }));
            actions.appendChild(again);
        }

        const statsButton = document.createElement('button');
        statsButton.type = 'button';
        statsButton.className = 'ps-btn';
        statsButton.innerHTML = '<i class="fa-solid fa-chart-simple"></i> ';
        statsButton.appendChild(document.createTextNode(STRINGS.stats));
        statsButton.addEventListener('click', openStats);
        actions.appendChild(statsButton);

        el.result.appendChild(actions);
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
            [stats.streak || 0, STRINGS.streak],
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
            bar.className = 'ps-bar' + (isCurrent ? ' ps-bar--best' : '');

            const label = document.createElement('span');
            label.className = 'ps-bar__label';
            text(label, String(index + 1));

            const track = document.createElement('div');
            track.className = 'ps-bar__track';

            const fill = document.createElement('div');
            fill.className = 'ps-bar__fill';
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

        if (el.subtitle) {
            text(el.subtitle, mode === 'practice'
                ? STRINGS.practiceHint
                : (state.puzzle ? STRINGS.puzzle(state.puzzle) : ''));
        }

        renderMarks();
        renderRows();
        renderControls();
        renderProgress(0);
        renderResult();
        renderStats();
        setPlayIcon(false);
    }

    /* ── Modali ────────────────────────────────────────────────────────── */

    function openModal(modal) {
        if (!modal) return;
        modal.hidden = false;
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.hidden = true;
    }

    function openStats() {
        renderStats();
        openModal(el.statsModal);
    }

    /* ── Avvio ─────────────────────────────────────────────────────────── */

    async function start(options) {
        if (el.boot) el.boot.hidden = false;
        if (el.game) el.game.hidden = true;
        if (el.error) el.error.hidden = true;

        stopPlayback();
        if (clipUrl) URL.revokeObjectURL(clipUrl);
        clipUrl = null;
        clipKey = '';
        selected = null;
        if (el.input) el.input.value = '';

        try {
            const payload = await loadState(options);
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
        }
    }

    async function submitGuess(body, autoplay) {
        if (busy) return;
        busy = true;
        syncControls();

        try {
            const payload = await sendGuess(body);
            const wasPlaying = state && state.status === 'playing';
            state = Object.assign({}, state, payload);
            selected = null;
            el.input.value = '';
            closeList();
            stopPlayback();
            render();

            // Come su Songspot il frammento più lungo parte da solo: il click
            // sul pulsante vale come gesto dell'utente per l'autoplay.
            if (autoplay && wasPlaying && state.status === 'playing') play();
        } catch (error) {
            toast(error.message || STRINGS.loadError);
        } finally {
            busy = false;
            syncControls();
        }
    }

    /* ── Eventi ────────────────────────────────────────────────────────── */

    if (el.play) el.play.addEventListener('click', play);

    if (el.skip) {
        el.skip.addEventListener('click', () => submitGuess({ action: 'skip' }, true));
    }

    if (el.submit) {
        el.submit.addEventListener('click', () => {
            if (!selected) return;
            submitGuess({ action: 'guess', character_id: selected.id }, true);
        });
    }

    if (el.clear) el.clear.addEventListener('click', clearSelection);

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

    root.querySelectorAll('[data-ps-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            const next = tab.dataset.psTab === 'practice' ? 'practice' : 'daily';
            if (next === mode) return;

            mode = next;
            root.querySelectorAll('[data-ps-tab]').forEach((other) => {
                other.setAttribute('aria-selected', other === tab ? 'true' : 'false');
            });
            start();
        });
    });

    document.querySelectorAll('[data-ps-open-stats]').forEach((button) => {
        button.addEventListener('click', openStats);
    });

    document.querySelectorAll('[data-ps-open-rules]').forEach((button) => {
        button.addEventListener('click', () => openModal(el.rulesModal));
    });

    document.querySelectorAll('[data-ps-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('.ps-modal')));
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
        if (event.code === 'Space' && document.activeElement !== el.input) {
            const tag = (document.activeElement && document.activeElement.tagName) || '';
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'BUTTON') return;
            event.preventDefault();
            play();
        }
    });

    window.addEventListener('pagehide', () => {
        if (clipUrl) URL.revokeObjectURL(clipUrl);
    });

    start();
})();
