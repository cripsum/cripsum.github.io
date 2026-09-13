/**
 * Cripsum™ — Titolo animato della scheda del profilo
 *
 * Lo usano il profilo (profile.js) e l'anteprima della scheda nell'editor,
 * cosi' quello che vedi nell'editor e' quello che succede davvero.
 *
 * PERCHE' "RIMBALZA" NON FACEVA NIENTE
 *
 * Il vecchio codice spostava il titolo aggiungendo spazi davanti. Ma il titolo
 * di una pagina passa sempre da "togli gli spazi in testa e in coda": gli
 * spazi sparivano e la scheda restava identica a ogni fotogramma. Qui lo
 * spostamento usa U+2800, un carattere vuoto che si vede come uno spazio ma
 * non e' uno spazio, quindi nessuno lo toglie. Anche gli spazi dentro il testo
 * diventano non separabili, altrimenti lo scorrimento li perderebbe quando
 * arrivano in testa.
 *
 * Nota: con la scheda in secondo piano i browser rallentano i timer a un
 * aggiornamento al secondo. Non si puo' evitare.
 */
(function (global) {
    'use strict';

    const BLANK = '⠀';
    const NBSP = ' ';

    const clean = (value) => String(value || '').replace(/\s+/g, ' ').trim();
    const keepSpaces = (value) => value.replace(/ /g, NBSP);

    // Posizioni di un rimbalzo: accelera partendo, rallenta in fondo.
    const BOUNCE = [0, 1, 2, 4, 6, 8, 9, 10, 10, 9, 8, 6, 4, 2, 1, 0];

    /**
     * I fotogrammi e la loro durata.
     *
     * speed ha il significato che aveva nell'editor: per "scorre" e' il tempo
     * per un carattere, per "alterna" il tempo di ogni testo, per "rimbalza"
     * la durata di un rimbalzo intero.
     *
     * @returns {{frames: string[], interval: number}|null}
     */
    const plan = ({ title, animation, speed = 1000, text = '' }) => {
        const base = clean(title);
        const alt = clean(text);
        const ms = Math.min(5000, Math.max(200, Number(speed) || 1000));
        if (!base) return null;

        if (animation === 'marquee') {
            const chars = Array.from(keepSpaces(alt || base) + BLANK.repeat(4));
            const frames = chars.map((_, i) => chars.slice(i).concat(chars.slice(0, i)).join(''));
            return { frames, interval: ms };
        }

        if (animation === 'bounce') {
            const label = keepSpaces(base);
            return { frames: BOUNCE.map((pos) => BLANK.repeat(pos) + label), interval: Math.max(40, Math.round(ms / BOUNCE.length)) };
        }

        if (animation === 'pulse') {
            return { frames: [base, alt || `${base} ♡`], interval: ms };
        }

        return null;
    };

    /**
     * Avvia l'animazione e restituisce la funzione per fermarla.
     *
     * @param {object} options title, animation, speed, text
     * @param {(frame: string) => void} [onFrame] di default scrive document.title
     */
    const start = (options, onFrame) => {
        const write = onFrame || ((frame) => { document.title = frame; });
        const p = plan(options);
        if (!p) {
            write(clean(options.title));
            return () => {};
        }
        let index = 0;
        write(p.frames[0]);
        const timer = setInterval(() => {
            index = (index + 1) % p.frames.length;
            write(p.frames[index]);
        }, p.interval);
        return () => clearInterval(timer);
    };

    global.CripsumTabTitle = { plan, start, BLANK };
})(window);
