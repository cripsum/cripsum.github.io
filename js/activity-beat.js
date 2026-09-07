/**
 * Cripsum™ — Activity Beat
 *
 * Sostituisce il vecchio `fetch('/api/update_activity.php')` inline.
 *
 * Differenza sostanziale rispetto al contatore basato su cookie: il tempo
 * scorre solo mentre la scheda è davvero visibile. Il vecchio `timeSpent`
 * cresceva di un secondo al secondo anche con la pagina in secondo piano da
 * ore, e quel totale non era utilizzabile per nessuna statistica seria.
 *
 * Il server ricontrolla comunque il valore contro il proprio orologio, quindi
 * questo file è una fonte di comodità, non di verità.
 */
(() => {
    'use strict';

    if (window.__cripsumActivityBeat) {
        return;
    }
    window.__cripsumActivityBeat = true;

    const ENDPOINT = '/api/update_activity.php';
    const BEAT_MS = 25000;
    const TICK_MS = 1000;

    // Oltre questo limite senza alcuna interazione consideriamo l'utente
    // assente anche a scheda visibile (monitor acceso, persona altrove).
    const IDLE_LIMIT_MS = 5 * 60 * 1000;

    let activeSeconds = 0;
    let lastInteraction = Date.now();
    let firstBeatSent = false;
    let finalSent = false;

    function isEngaged() {
        if (document.visibilityState !== 'visible') return false;
        return (Date.now() - lastInteraction) < IDLE_LIMIT_MS;
    }

    function noteInteraction() {
        lastInteraction = Date.now();
    }

    ['pointerdown', 'keydown', 'wheel', 'scroll', 'touchstart', 'focus'].forEach((event) => {
        window.addEventListener(event, noteInteraction, { passive: true, capture: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            noteInteraction();
        } else {
            // Uscendo dalla scheda mandiamo subito quello che c'è, così il
            // tempo non resta appeso a una scheda che magari non torna più.
            sendBeat(false);
        }
    });

    window.setInterval(() => {
        if (isEngaged()) {
            activeSeconds += 1;
        }
    }, TICK_MS);

    function readCookie(name) {
        try {
            const parts = document.cookie ? document.cookie.split('; ') : [];
            for (const part of parts) {
                const index = part.indexOf('=');
                if (index === -1) continue;
                if (part.slice(0, index) !== name) continue;
                return JSON.parse(decodeURIComponent(part.slice(index + 1)));
            }
        } catch (_) {
            return null;
        }
        return null;
    }

    function buildPayload(isFinal) {
        const payload = {
            page: location.pathname,
            delta: activeSeconds,
            first: firstBeatSent ? 0 : 1,
            final: isFinal ? 1 : 0
        };

        // Al primo battito alleghiamo i vecchi contatori del browser. Il
        // server li importa una sola volta e poi ignora l'informazione.
        if (!firstBeatSent) {
            const legacySeconds = readCookie('timeSpent');
            const legacyDays = readCookie('daysVisited');

            if (Number.isFinite(Number(legacySeconds))) {
                payload.legacy_seconds = Number(legacySeconds);
            }
            if (Array.isArray(legacyDays) && legacyDays.length) {
                payload.legacy_days = legacyDays.slice(-4000);
            }
        }

        return payload;
    }

    function sendBeat(isFinal) {
        if (finalSent) return;
        if (isFinal) finalSent = true;

        const payload = buildPayload(isFinal);

        // Niente da dire e non è il primo battito: risparmiamo la richiesta.
        if (!payload.first && !payload.final && payload.delta === 0) {
            return;
        }

        activeSeconds = 0;

        const body = JSON.stringify(payload);

        if (isFinal && navigator.sendBeacon) {
            // text/plain evita la preflight; il server accetta comunque JSON.
            navigator.sendBeacon(ENDPOINT, new Blob([body], { type: 'text/plain' }));
            firstBeatSent = true;
            return;
        }

        fetch(ENDPOINT, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'text/plain' },
            body,
            keepalive: isFinal
        }).catch(() => {
            /* Una statistica persa non deve disturbare la pagina. */
        });

        firstBeatSent = true;
    }

    window.addEventListener('pagehide', () => sendBeat(true));

    // Tornando indietro la pagina può essere ripescata dalla bfcache: il
    // battito finale è già partito, quindi va riarmato.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            finalSent = false;
            noteInteraction();
        }
    });

    // Primo battito subito: registra la visualizzazione di pagina e importa
    // i cookie storici senza aspettare 25 secondi.
    sendBeat(false);
    window.setInterval(() => sendBeat(false), BEAT_MS);
})();
