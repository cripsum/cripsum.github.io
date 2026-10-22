/**
 * Duelli 2.0 — le icone disegnate (tavola 4 delle bozze): stati, ruoli,
 * mosse, terreni, emblemi dei ranghi. Tratto sottile, colore dal testo.
 *
 *   D2Icons.svg('veleno')            stringa SVG
 *   D2Icons.hydrate(root)            ogni <i data-i="nome"> sotto root diventa l'icona
 *   D2Icons.emblem('oro')            emblema pieno del rango (colore dal testo)
 *
 * Generato da proposte-home/strumenti con il set delle bozze approvate.
 */
(function () {
    'use strict';
    const P = {
        // Stati
        veleno: '<path d="M12 3c3 4 6 7.2 6 10.5A6 6 0 0 1 6 13.5C6 10.2 9 7 12 3z"/><circle cx="10.2" cy="14" r="1.3"/><circle cx="13.8" cy="11.6" r="0.9"/>',
        sanguinamento: '<path d="M12 3c3 4 6 7.2 6 10.5A6 6 0 0 1 6 13.5C6 10.2 9 7 12 3z"/><path d="M9 17l6-6"/>',
        stordimento: '<path d="M12 12m-2 0a2 2 0 1 0 4 0a4 4 0 1 0-8 0a6 6 0 1 0 12 0"/><path d="M19 4l1 2 2 1-2 1-1 2-1-2-2-1 2-1z"/>',
        congelamento: '<path d="M12 2v20M3.3 7l17.4 10M3.3 17L20.7 7"/><path d="M9.5 3.8L12 6l2.5-2.2M9.5 20.2L12 18l2.5 2.2"/>',
        silenzio: '<path d="M4 5h16v10H9l-5 4z"/><path d="M3 3l18 18"/>',
        provocazione: '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.5"/><path d="M12 1v4M12 19v4M1 12h4M19 12h4"/>',
        scudo: '<path d="M12 3l7 3v5c0 5-3.2 8.2-7 10-3.8-1.8-7-5-7-10V6z"/>',
        rigenerazione: '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v9M7.5 12h9"/>',
        immunita: '<path d="M12 3l7 3v5c0 5-3.2 8.2-7 10-3.8-1.8-7-5-7-10V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
        atk_su: '<path d="M5 19L15 9M13 7l4-4 4 4-4 4"/><path d="M5 13v6h6"/>',
        def_giu: '<path d="M12 3l7 3v5c0 5-3.2 8.2-7 10-3.8-1.8-7-5-7-10V6z"/><path d="M12 8v7M9 12.5l3 3 3-3"/>',
        vel_su: '<path d="M4 17l5-5-5-5M12 17l5-5-5-5"/>',
        crit_su: '<path d="M12 2l2.2 6.2L20 6l-2.6 5.8L23 14l-6.2.8L17 21l-5-4-5 4 .2-6.2L1 14l5.6-2.2L4 6l5.8 2.2z"/>',
        contrattacco: '<path d="M9 7H4v5"/><path d="M4 12a8 8 0 1 0 2.3-5.7L4 8"/>',
        marchio: '<path d="M12 3l9 9-9 9-9-9z"/><path d="M12 9v6M9 12h6"/>',
        cariche: '<path d="M4 7h16M4 12h16M4 17h10"/>',
        // Stati nuovi del 2.0
        bruciatura: '<path d="M12 3c1 3 5 5.5 5 10a5 5 0 0 1-10 0c0-2.5 1.2-4 2.5-5 0 2 .8 3.3 2 3.5C11 9 11 6 12 3z"/>',
        corrosione: '<path d="M9 3h6M10 3v5l-5 10a2 2 0 0 0 1.8 3h10.4a2 2 0 0 0 1.8-3L14 8V3"/><path d="M7.5 14h9"/>',
        maledizione: '<path d="M12 3l9.5 17h-19z"/><circle cx="12" cy="14" r="2.2"/>',
        elettro: '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        sonno: '<path d="M4 9h6l-6 8h6M13 4h7l-7 8h7"/>',
        paura: '<path d="M6 20V10a6 6 0 0 1 12 0v10l-2-2-2 2-2-2-2 2-2-2z"/><path d="M10 10h.01M14 10h.01"/>',
        confusione: '<path d="M9 9a3 3 0 1 1 4.5 2.6c-1 .6-1.5 1.2-1.5 2.4"/><path d="M12 18h.01"/><circle cx="12" cy="12" r="9.5"/>',
        disarmo: '<path d="M4 20l4-4M6 14l4 4M8 16L20 4v4L10 18"/><path d="M3 3l18 18"/>',
        fascino: '<path d="M12 20s-7.5-4.6-7.5-10.2A4.2 4.2 0 0 1 12 7.3a4.2 4.2 0 0 1 7.5 2.5C19.5 15.4 12 20 12 20z"/>',
        vulnerabile: '<circle cx="12" cy="12" r="9"/><path d="M12 3l-2 6 4 3-3 5 1 4"/>',
        ferita: '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/><path d="M4 20L20 4"/>',
        guardia_rotta: '<path d="M12 3l7 3v5c0 5-3.2 8.2-7 10-3.8-1.8-7-5-7-10V6z"/><path d="M12 6l-2 5 4 2-2 6"/>',
        esposto: '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><path d="M12 9v6M9 12h6"/>',
        taglia: '<circle cx="12" cy="12" r="9"/><path d="M12 6.5v11M15 9h-4a1.6 1.6 0 0 0 0 3.2h2a1.6 1.6 0 0 1 0 3.2H9"/>',
        prosciugato: '<path d="M12 1l2.6 7.4L22 9l-5.8 4.6L18 21l-6-4.2L6 21l1.8-7.4L2 9l7.4-.6z"/><path d="M4 4l16 16"/>',
        rubavita: '<path d="M12 3c3 4 6 7.2 6 10.5A6 6 0 0 1 6 13.5C6 10.2 9 7 12 3z"/><path d="M9.5 14h5M12 11.5v5"/>',
        concentrazione: '<circle cx="12" cy="12" r="2.5"/><path d="M12 3v4M12 17v4M3 12h4M17 12h4"/>',
        ispirato: '<path d="M12 2l2.4 6.6L21 9l-5.2 4.2L17.6 20 12 16.3 6.4 20l1.8-6.8L3 9l6.6-.4z"/><path d="M12 9v5M10 11l2-2 2 2"/>',
        furia: '<path d="M6 13l6-6 6 6M6 20l6-6 6 6"/>',
        barriera: '<path d="M12 2l8.5 5v10L12 22 3.5 17V7z"/>',
        riflesso: '<path d="M3 12h11M10 8l4 4-4 4M19 3v18"/>',
        schivata: '<path d="M3 8h11a3 3 0 1 0-3-3M3 16h14a3 3 0 1 1-3 3M3 12h7"/>',
        furtivita: '<path d="M3 3l18 18"/><path d="M10.6 5.1C11 5 11.5 5 12 5c6.4 0 10 7 10 7a17 17 0 0 1-2.6 3.4M6.6 6.6C3.9 8.3 2 12 2 12s3.6 7 10 7c1.9 0 3.5-.6 4.9-1.5"/>',
        guardiano: '<path d="M9 4l6 2.5V11c0 4.5-2.7 7.3-6 9-3.3-1.7-6-4.5-6-9V6.5z"/><path d="M15 6.5l6 2.5v4c0 3.5-2.2 6-5.4 7.5"/>',
        indistruttibile: '<path d="M12 20s-7.5-4.6-7.5-10.2A4.2 4.2 0 0 1 12 7.3a4.2 4.2 0 0 1 7.5 2.5C19.5 15.4 12 20 12 20z"/><path d="M9 12h6"/>',
        rinascita: '<path d="M12 21V10M8 14l4-4 4 4"/><path d="M4.5 7.5a10 10 0 0 1 15 0"/>',
        forma: '<path d="M12 2l3 6 6 1-4.5 4.2L18 20l-6-3.2L6 20l1.5-6.8L3 9l6-1z"/><circle cx="12" cy="12" r="2"/>',
        terreno: '<path d="M3 19l6-10 4 6 3-4 5 8z"/>',
        resistenza: '<path d="M12 3l7 3v5c0 5-3.2 8.2-7 10-3.8-1.8-7-5-7-10V6z"/><path d="M9 9l6 6M15 9l-6 6"/>',
        notte: '<path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
        tempesta: '<path d="M7 16a4 4 0 0 1-.5-8 5.5 5.5 0 0 1 10.6-1A3.8 3.8 0 0 1 17.5 16"/><path d="M12.5 12l-2 4h3.5l-2 4"/>',
        palude: '<path d="M3 9c2-2 4-2 6 0s4 2 6 0 4-2 6 0M3 15c2-2 4-2 6 0s4 2 6 0 4-2 6 0"/>',
        santuario: '<path d="M3 10l9-6 9 6M5 10v9M19 10v9M9.5 10v9M14.5 10v9M3 21h18"/>',
        precisione: '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>',
        tenacia: '<circle cx="12" cy="5" r="2"/><path d="M12 7v14M8 11h8M5 14a7 7 0 0 0 14 0"/>',
        diffusione: '<path d="M4 12h6M10 12l8-6M10 12l8 6M10 12h9"/>',
        furto: '<path d="M3 12h11M10 8l4 4-4 4M15 4h5v16h-5"/>',
        detonazione: '<circle cx="12" cy="13" r="7"/><path d="M15 7l3-3M18 4h2v2"/>',
        // Ruoli
        tank: '<path d="M12 3l7 3v5c0 5-3.2 8.2-7 10-3.8-1.8-7-5-7-10V6z"/><path d="M12 7v10"/>',
        bruiser: '<path d="M7 11V6.5a1.5 1.5 0 0 1 3 0V10M10 9.5V5a1.5 1.5 0 0 1 3 0v4.5M13 9.5V6a1.5 1.5 0 0 1 3 0v6M16 10a1.5 1.5 0 0 1 3 0v3a7 7 0 0 1-7 7h-1a6 6 0 0 1-6-6v-2a1.5 1.5 0 0 1 2-1.4"/>',
        dps: '<path d="M4 20l4-4M6 14l4 4M8 16L20 4v4L10 18"/>',
        burst: '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        subdps: '<path d="M4 20l3-3M5.5 15.5l3 3M7 17l9-9 1-5-5 1-9 9"/><path d="M15 21l6-6"/>',
        support: '<path d="M12 3l7 3v5c0 5-3.2 8.2-7 10-3.8-1.8-7-5-7-10V6z"/><path d="M12 8.5v7M8.5 12h7"/>',
        healer: '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/>',
        controller: '<path d="M6 3h12M6 21h12M7 3c0 5 10 6 10 9s-10 4-10 9M17 3c0 5-10 6-10 9s10 4 10 9"/>',
        debuffer: '<circle cx="12" cy="10" r="7"/><path d="M9 17v4h6v-4"/><circle cx="9.5" cy="10" r="1.2"/><circle cx="14.5" cy="10" r="1.2"/>',
        buffer: '<path d="M5 21V4M5 4h12l-3 4 3 4H5"/>',
        // Azioni e interfaccia
        attacco: '<path d="M4 20l4-4M6 14l4 4M8 16L20 4v4L10 18"/>',
        abilita: '<path d="M12 2l1.8 5.2L19 9l-5.2 1.8L12 16l-1.8-5.2L5 9l5.2-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
        ultimate: '<path d="M12 1l2.6 7.4L22 9l-5.8 4.6L18 21l-6-4.2L6 21l1.8-7.4L2 9l7.4-.6z"/>',
        guardia: '<path d="M12 3l7 3v5c0 5-3.2 8.2-7 10-3.8-1.8-7-5-7-10V6z"/><path d="M8 11h8"/>',
        occhio: '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        suono: '<path d="M4 9h4l5-4v14l-5-4H4z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12"/>',
        log: '<path d="M5 6h14M5 12h14M5 18h9"/>',
        chat: '<path d="M4 5h16v11H9l-5 4z"/>',
        indietro: '<path d="M15 5l-7 7 7 7"/>',
        orologio: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        mirino: '<circle cx="12" cy="12" r="7"/><path d="M12 2v5M12 17v5M2 12h5M17 12h5"/>',
        replay: '<path d="M4 12a8 8 0 1 0 2.3-5.7"/><path d="M4 4v4h4"/><path d="M10 9l5 3-5 3z"/>',
        coppa: '<path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4v1a3 3 0 0 0 3 3M17 6h3v1a3 3 0 0 1-3 3M12 14v4M8 21h8"/>',
        utenti: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        robot: '<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M9 13h.01M15 13h.01M9 17h6"/>',
        fulmine: '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        calendario: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        posta: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        freccia: '<path d="M5 12h14M13 6l6 6-6 6"/>',
        check: '<path d="M5 12.5l4.5 4.5L19 7"/>',
        piu: '<path d="M12 5v14M5 12h14"/>',
        cerca: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        matita: '<path d="M4 20l1-4L16 5l3 3L8 19z"/>',
        grafico: '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
        dadi: '<rect x="3" y="3" width="18" height="18" rx="4"/><circle cx="8.5" cy="8.5" r="1.2"/><circle cx="15.5" cy="15.5" r="1.2"/><circle cx="12" cy="12" r="1.2"/>',
        lucchetto: '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        campana: '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 21h4"/>',
        x: '<path d="M6 6l12 12M18 6L6 18"/>',
    
        // Aggiunte per l'arena (fase 4).
        atk_giu: '<path d="M19 5L9 15M11 17l-4 4-4-4 4-4"/><path d="M19 5l-6 1M19 5l-1 6" />',
        def_su: '<path d="M12 3l7 3v5c0 5-3.2 8.2-7 10-3.8-1.8-7-5-7-10V6z"/><path d="M12 15V8M9 11l3-3 3 3"/>',
        vel_giu: '<path d="M20 17l-5-5 5-5M12 17l-5-5 5-5"/>',
        crit_giu: '<path d="M12 3l2 5.5 5.5.5-4.2 3.6 1.3 5.6L12 15.3 7.4 18.2l1.3-5.6L4.5 9l5.5-.5z"/><path d="M4 21h16"/>',
        cdmg_su: '<path d="M12 2l2.2 6.2L20 6l-2.6 5.8L23 14l-6 .8.6 6.2-5.6-3.6L6.4 21 7 14.8 1 14l5.6-2.2L4 6l5.8 2.2z"/>',
        cielo_stellato: '<path d="M12 3l1.6 3.6L17 8l-3.4 1.4L12 13l-1.6-3.6L7 8l3.4-1.4z"/><path d="M18 14l.9 2.1L21 17l-2.1.9L18 20l-.9-2.1L15 17l2.1-.9zM6 15l.6 1.4L8 17l-1.4.6L6 19l-.6-1.4L4 17l1.4-.6z"/>',
        impostazioni: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        bandiera: '<path d="M5 21V4M5 4h12l-3 4 3 4H5"/>',
        muto: '<path d="M4 9h4l5-4v14l-5-4H4z"/><path d="M17 9l4 6M21 9l-4 6"/>',
        passa: '<path d="M5 12h14M13 6l6 6-6 6"/>',
    };
    // Alias: stati del motore che usano l'icona di un altro nome.
    const ALIAS = { elettrizzato: 'elettro', protetto: 'guardiano', abilita: 'abilita' };
    const FILL = { burst: 1, ultimate: 1, fulmine: 1 };
    const E = {
        bronzo: '<path d="M32 4l22 8v18c0 14-9.5 24-22 30C19.5 54 10 44 10 30V12z" fill="currentColor"/><path d="M32 16v30" stroke="#0a0a0b" stroke-width="3"/>',
        argento: '<path d="M32 4l22 8v18c0 14-9.5 24-22 30C19.5 54 10 44 10 30V12z" fill="currentColor"/><path d="M21 26l11 8 11-8M21 36l11 8 11-8" fill="none" stroke="#0a0a0b" stroke-width="3" stroke-linejoin="round"/>',
        oro: '<path d="M32 4l22 8v18c0 14-9.5 24-22 30C19.5 54 10 44 10 30V12z" fill="currentColor"/><path d="M21 20l11 7.5 11-7.5M21 29l11 7.5 11-7.5M21 38l11 7.5 11-7.5" fill="none" stroke="#0a0a0b" stroke-width="3" stroke-linejoin="round"/>',
        diamante: '<path d="M32 4l24 22-24 34L8 26z" fill="currentColor"/><path d="M8 26h48M20 26l12 34 12-34M20 26l12-22 12 22" fill="none" stroke="#0a0a0b" stroke-width="2.5" stroke-linejoin="round"/>',
        campione: '<path d="M32 3l25 14.5v29L32 61 7 46.5v-29z" fill="currentColor"/><path d="M32 17l4.3 9 9.7 1.3-7 6.8 1.7 9.7L32 39.2l-8.7 4.6 1.7-9.7-7-6.8 9.7-1.3z" fill="#0a0a0b"/>',
        leggenda: '<path d="M32 2l7 15 16-6-6 16 15 7-15 7 6 16-16-6-7 15-7-15-16 6 6-16-15-7 15-7-6-16 16 6z" fill="currentColor"/><circle cx="32" cy="34" r="9" fill="#0a0a0b"/>',
        };

    function svg(name, cls) {
        const key = P[name] ? name : (ALIAS[name] || name);
        const path = P[key];
        if (!path) {
            return '';
        }
        const fill = FILL[key] ? 'currentColor' : 'none';
        return '<svg class="ic ic-' + key + (cls ? ' ' + cls : '') + '" viewBox="0 0 24 24" fill="' + fill
            + '" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + path + '</svg>';
    }

    function hydrate(root) {
        (root || document).querySelectorAll('i[data-i]').forEach((el) => {
            const html = svg(el.getAttribute('data-i'), el.className);
            if (html) {
                el.outerHTML = html;
            }
        });
    }

    function emblem(key) {
        return E[key] ? '<svg class="emb-svg" viewBox="0 0 64 64" aria-hidden="true" focusable="false">' + E[key] + '</svg>' : '';
    }

    window.D2Icons = { svg, hydrate, emblem, has: (name) => Boolean(P[name] || P[ALIAS[name]]) };
})();
