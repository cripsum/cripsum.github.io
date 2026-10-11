/*
 * Pannello del bot.
 *
 * La pagina arriva vuota: qui si chiede a api/botpanel chi sta guardando e
 * cosa puo' vedere, e si disegna di conseguenza. Niente di quello che c'e'
 * in questo file decide un permesso: nasconde o mostra, e basta. Chi puo'
 * leggere o salvare lo decidono gli endpoint, a ogni richiesta.
 *
 * Le sezioni con una forma loro (ticket, messaggi, giveaway) stanno in
 * panel-sections.js e si aggiungono a BotPanel.sections.
 */
(() => {
    'use strict';

    const root = document.getElementById('bp-root');
    if (!root) return;

    const LANG = root.dataset.lang === 'en' ? 'en' : 'it';
    const BASE = `/${LANG}/bot`;

    const STRINGS = {
        it: {
            loading: 'Carico…',
            poppyLead: 'Il bot di Cripsum. Scegli il server da configurare.',
            addToServer: 'Aggiungi a un server',
            yourServers: 'I tuoi server',
            manage: 'Gestisci',
            members: n => `${n.toLocaleString('it-IT')} ${n === 1 ? 'membro' : 'membri'}`,
            yourServer: 'il tuo server',
            homeServer: 'server di Cripsum',
            pickerNote: 'Qui compaiono solo i server in cui c\'è Poppy e in cui sei proprietario o hai il permesso «Gestisci server».',
            refresh: 'Aggiorna l\'elenco',
            noServers: 'Poppy non è ancora in nessun server che gestisci. Aggiungilo e torna qui: lo trovi subito.',
            loginTitle: 'Accedi per usare il pannello',
            loginText: 'Il pannello di Poppy è per chi gestisce un server Discord. Serve un account Cripsum con Discord collegato.',
            login: 'Accedi',
            linkTitle: 'Collega il tuo account Discord',
            linkText: 'Per sapere quali server gestisci, il pannello ha bisogno del tuo account Discord. Lo colleghi una volta sola.',
            link: 'Collega Discord',
            botDownTitle: 'Poppy non risponde',
            botDownText: 'Il bot è spento o irraggiungibile. Senza una sua risposta il pannello non può sapere cosa puoi gestire, quindi resta chiuso. Riprova fra poco.',
            retry: 'Riprova',
            notInstalled: 'Il pannello non è ancora attivo su questo sito.',
            forbiddenTitle: 'Non puoi gestire questo server',
            forbiddenText: 'Serve essere proprietario del server, oppure avere il permesso «Amministratore» o «Gestisci server».',
            missingTitle: 'Poppy non è in questo server',
            missingText: 'Aggiungilo al server e poi torna qui.',
            backToServers: 'Scegli un altro server',
            changeServer: 'Cambia server',
            overview: 'Panoramica',
            modules: 'Moduli',
            server: 'Server',
            off: 'spento',
            registry: 'Registro',
            toFix: 'Da sistemare',
            allGood: 'Niente da sistemare: Poppy ha tutto quello che gli serve.',
            open: 'Apri',
            moduleOn: 'Modulo acceso',
            fromEnv: 'Questo modulo sta ancora usando le impostazioni di prima del pannello. La prima volta che salvi, da qui in poi valgono queste.',
            readOnly: 'Nel server di Cripsum può salvare solo un amministratore del sito: tu puoi guardare.',
            unsaved: 'Modifiche non salvate',
            cancel: 'Annulla',
            save: 'Salva',
            saving: 'Salvo…',
            saved: 'Salvato.',
            savedLater: 'Salvato. Poppy non ha confermato: applicherà le modifiche entro mezz\'ora.',
            conflict: 'Qualcun altro ha salvato nel frattempo. Ricarica la pagina per vedere le sue modifiche.',
            invalid: 'Alcuni valori non vanno bene: sono segnati in rosso.',
            network: 'Qualcosa non ha funzionato. Riprova.',
            rate: 'Troppe richieste: aspetta qualche secondo.',
            expired: 'La sessione è scaduta: ricarica la pagina e riprova.',
            leave: 'Hai modifiche non salvate. Vuoi lasciarle perdere?',
            none: 'Nessuno',
            addRole: 'Aggiungi ruolo',
            remove: 'Togli',
            add: 'Aggiungi',
            startHere: 'Per cominciare',
            startText: 'Poppy è appena arrivato in questo server. Tre cose, in quest\'ordine, e sei a posto.',
            start1: ['Scegli lo staff', 'Quali ruoli possono usare i comandi di moderazione.'],
            start2: ['Dai un canale ai log', 'Così ogni azione di moderazione lascia traccia.'],
            start3: ['Accendi quello che ti serve', 'Ticket, messaggi di benvenuto, giveaway: ognuno ha la sua pagina.'],
            copyTitle: 'Copia da un altro server',
            copyText: 'Porta qui le regole di un altro tuo server: soglie, testi, categorie. Canali e ruoli vanno scelti di nuovo, perché sono diversi in ogni server.',
            copyFrom: 'Da quale server',
            copyGo: 'Copia',
            copied: n => `Copiati ${n} moduli. Controlla canali e ruoli.`,
            copiedNone: 'Niente da copiare: quel server non ha ancora salvato questi moduli.',
            registryLead: 'Ogni modifica alle impostazioni di questo server, con chi l\'ha fatta. Da qui puoi anche tornare indietro.',
            when: 'Quando',
            who: 'Chi',
            where: 'Dove',
            what: 'Cosa',
            from: 'Da',
            all: 'Tutto',
            before: 'Prima',
            after: 'Dopo',
            nothingBefore: 'Non c\'era ancora niente.',
            restore: 'Torna a come era prima',
            restored: 'Ripristinato.',
            restoreAsk: 'Riportare il modulo a come era prima di questa modifica?',
            registryEmpty: 'Nessuna modifica ancora.',
            more: 'Più vecchie',
            actions: {
                save: 'Impostazioni salvate', restore: 'Ripristinate le impostazioni di prima', copy: 'Copiate da un altro server',
                template_save: 'Messaggio salvato', template_delete: 'Messaggio cancellato', template_publish: 'Messaggio pubblicato',
                discord_template_save: 'Messaggio salvato', discord_template_delete: 'Messaggio cancellato',
            },
            sources: { panel: 'Pannello', discord: 'Discord', system: 'Automatico', owner: 'Cripsum' },
            today: 'oggi',
            yesterday: 'ieri',
            idsHint: 'Un ID per riga.',
        },
        en: {
            loading: 'Loading…',
            poppyLead: 'The Cripsum bot. Pick the server to set up.',
            addToServer: 'Add to a server',
            yourServers: 'Your servers',
            manage: 'Manage',
            members: n => `${n.toLocaleString('en-GB')} ${n === 1 ? 'member' : 'members'}`,
            yourServer: 'your server',
            homeServer: 'the Cripsum server',
            pickerNote: 'Only servers where Poppy is present and where you are the owner or have the "Manage Server" permission show up here.',
            refresh: 'Refresh the list',
            noServers: 'Poppy isn\'t in any server you manage yet. Add it and come back: it shows up right away.',
            loginTitle: 'Sign in to use the panel',
            loginText: 'Poppy\'s panel is for people who run a Discord server. You need a Cripsum account with Discord connected.',
            login: 'Sign in',
            linkTitle: 'Connect your Discord account',
            linkText: 'To know which servers you manage, the panel needs your Discord account. You connect it once.',
            link: 'Connect Discord',
            botDownTitle: 'Poppy isn\'t answering',
            botDownText: 'The bot is off or unreachable. Without its answer the panel can\'t know what you can manage, so it stays closed. Try again shortly.',
            retry: 'Try again',
            notInstalled: 'The panel isn\'t active on this site yet.',
            forbiddenTitle: 'You can\'t manage this server',
            forbiddenText: 'You need to be the server owner, or have the "Administrator" or "Manage Server" permission.',
            missingTitle: 'Poppy isn\'t in this server',
            missingText: 'Add it to the server, then come back.',
            backToServers: 'Pick another server',
            changeServer: 'Change server',
            overview: 'Overview',
            modules: 'Modules',
            server: 'Server',
            off: 'off',
            registry: 'Log of changes',
            toFix: 'Needs fixing',
            allGood: 'Nothing to fix: Poppy has everything it needs.',
            open: 'Open',
            moduleOn: 'Module on',
            fromEnv: 'This module is still using the settings from before the panel. The first time you save, these take over.',
            readOnly: 'In the Cripsum server only a site administrator can save: you can look.',
            unsaved: 'Unsaved changes',
            cancel: 'Cancel',
            save: 'Save',
            saving: 'Saving…',
            saved: 'Saved.',
            savedLater: 'Saved. Poppy didn\'t confirm: it will apply the changes within half an hour.',
            conflict: 'Someone else saved in the meantime. Reload the page to see their changes.',
            invalid: 'Some values aren\'t right: they\'re marked in red.',
            network: 'Something went wrong. Try again.',
            rate: 'Too many requests: wait a few seconds.',
            expired: 'Your session expired: reload the page and try again.',
            leave: 'You have unsaved changes. Discard them?',
            none: 'None',
            addRole: 'Add role',
            remove: 'Remove',
            add: 'Add',
            startHere: 'Getting started',
            startText: 'Poppy has just joined this server. Three things, in this order, and you\'re set.',
            start1: ['Pick the staff', 'Which roles can use the moderation commands.'],
            start2: ['Give the log a channel', 'So every moderation action leaves a trail.'],
            start3: ['Turn on what you need', 'Tickets, welcome messages, giveaways: each has its own page.'],
            copyTitle: 'Copy from another server',
            copyText: 'Bring over the rules from another server of yours: thresholds, texts, categories. Channels and roles have to be picked again, because they differ in every server.',
            copyFrom: 'From which server',
            copyGo: 'Copy',
            copied: n => `Copied ${n} modules. Check channels and roles.`,
            copiedNone: 'Nothing to copy: that server hasn\'t saved these modules yet.',
            registryLead: 'Every change to this server\'s settings, with who made it. You can also go back from here.',
            when: 'When',
            who: 'Who',
            where: 'Where',
            what: 'What',
            from: 'From',
            all: 'All',
            before: 'Before',
            after: 'After',
            nothingBefore: 'There was nothing yet.',
            restore: 'Go back to how it was',
            restored: 'Restored.',
            restoreAsk: 'Put the module back to how it was before this change?',
            registryEmpty: 'No changes yet.',
            more: 'Older',
            actions: {
                save: 'Settings saved', restore: 'Previous settings restored', copy: 'Copied from another server',
                template_save: 'Message saved', template_delete: 'Message deleted', template_publish: 'Message published',
                discord_template_save: 'Message saved', discord_template_delete: 'Message deleted',
            },
            sources: { panel: 'Panel', discord: 'Discord', system: 'Automatic', owner: 'Cripsum' },
            today: 'today',
            yesterday: 'yesterday',
            idsHint: 'One ID per line.',
        }
    };

    const S = STRINGS[LANG];

    /** Cosa non va in un campo, detto a chi sta configurando. */
    const ERRORS = {
        it: {
            required: 'Questa scelta è obbligatoria quando il modulo è acceso.',
            channel: 'Questo canale non esiste più: scegline un altro.',
            role: 'Uno di questi ruoli non esiste più: toglilo.',
            role_too_high: 'Poppy non può assegnare questo ruolo: sta sopra il suo.',
            role_dangerous: 'Questo ruolo ha permessi di moderazione: Poppy non lo assegna da solo.',
            role_above_you: 'Questo ruolo è alto quanto il tuo, o di più: non puoi farlo assegnare.',
            too_long: 'Testo troppo lungo.',
            too_many: 'Troppi elementi.',
            range: 'Numero fuori misura.',
            number: 'Serve un numero.',
            choice: 'Scelta non valida.',
            color: 'Il colore va scritto così: #5865f2.',
            user: 'Uno di questi non è un ID Discord.',
            duplicate: 'Ci sono due voci uguali.',
            'bot_cannot:view': 'Poppy non vede questo canale.',
            'bot_cannot:send': 'Poppy non può scrivere in questo canale.',
            'bot_cannot:embed': 'In questo canale a Poppy manca «Incorpora link».',
            'bot_cannot:attach': 'In questo canale a Poppy manca «Allega file».',
            'bot_cannot:manage_messages': 'In questo canale a Poppy manca «Gestisci messaggi».',
            'bot_cannot:private_threads': 'In questo canale a Poppy manca «Crea thread privati».',
            'bot_cannot:send_in_threads': 'In questo canale a Poppy manca «Invia messaggi nei thread».',
            'bot_cannot:manage_threads': 'In questo canale a Poppy manca «Gestisci thread».',
            'bot_cannot:manage_channel': 'In questo canale a Poppy manca «Gestisci canali».',
            'bot_cannot:move_members': 'In questo canale a Poppy manca «Sposta membri».',
            'bot_permission:manage_channels': 'A Poppy manca il permesso «Gestisci canali».',
            'bot_permission:move_members': 'A Poppy manca il permesso «Sposta membri».',
            'bot_permission:moderate': 'A Poppy manca il permesso «Metti in timeout i membri».',
            'bot_permission:kick': 'A Poppy manca il permesso «Espelli membri».',
            'bot_permission:ban': 'A Poppy manca il permesso «Banna membri».',
            'bot_permission:manage_messages': 'A Poppy manca il permesso «Gestisci messaggi».',
            'bot_permission:manage_threads': 'A Poppy manca il permesso «Gestisci thread».',
            'bot_permission:manage_roles': 'A Poppy manca il permesso «Gestisci ruoli».',
        },
        en: {
            required: 'This is required while the module is on.',
            channel: 'This channel no longer exists: pick another.',
            role: 'One of these roles no longer exists: remove it.',
            role_too_high: 'Poppy can\'t give this role: it sits above its own.',
            role_dangerous: 'This role has moderation permissions: Poppy won\'t hand it out on its own.',
            role_above_you: 'This role is as high as yours, or higher: you can\'t have it handed out.',
            too_long: 'Text too long.',
            too_many: 'Too many items.',
            range: 'Number out of range.',
            number: 'A number is needed.',
            choice: 'Invalid choice.',
            color: 'Write the colour like this: #5865f2.',
            user: 'One of these is not a Discord ID.',
            duplicate: 'Two entries are the same.',
            'bot_cannot:view': 'Poppy can\'t see this channel.',
            'bot_cannot:send': 'Poppy can\'t write in this channel.',
            'bot_cannot:embed': 'Poppy is missing "Embed Links" in this channel.',
            'bot_cannot:attach': 'Poppy is missing "Attach Files" in this channel.',
            'bot_cannot:manage_messages': 'Poppy is missing "Manage Messages" in this channel.',
            'bot_cannot:private_threads': 'Poppy is missing "Create Private Threads" in this channel.',
            'bot_cannot:send_in_threads': 'Poppy is missing "Send Messages in Threads" in this channel.',
            'bot_cannot:manage_threads': 'Poppy is missing "Manage Threads" in this channel.',
            'bot_cannot:manage_channel': 'Poppy is missing "Manage Channels" in this channel.',
            'bot_cannot:move_members': 'Poppy is missing "Move Members" in this channel.',
            'bot_permission:manage_channels': 'Poppy is missing the "Manage Channels" permission.',
            'bot_permission:move_members': 'Poppy is missing the "Move Members" permission.',
            'bot_permission:moderate': 'Poppy is missing the "Timeout Members" permission.',
            'bot_permission:kick': 'Poppy is missing the "Kick Members" permission.',
            'bot_permission:ban': 'Poppy is missing the "Ban Members" permission.',
            'bot_permission:manage_messages': 'Poppy is missing the "Manage Messages" permission.',
            'bot_permission:manage_threads': 'Poppy is missing the "Manage Threads" permission.',
            'bot_permission:manage_roles': 'Poppy is missing the "Manage Roles" permission.',
        }
    }[LANG];

    /* ── Attrezzi ─────────────────────────────────────────── */

    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    /** Testo nella lingua della pagina, da una coppia {it, en}. */
    const tr = pair => (pair && typeof pair === 'object' ? (pair[LANG] ?? pair.it ?? '') : String(pair ?? ''));

    const clone = value => JSON.parse(JSON.stringify(value));

    function getPath(object, path) {
        return path.split('.').reduce((at, key) => (at == null ? undefined : at[key]), object);
    }

    function setPath(object, path, value) {
        const keys = path.split('.');
        const last = keys.pop();
        const target = keys.reduce((at, key) => at[key], object);
        target[last] = value;
    }

    function serverIcon(guild, className) {
        if (guild.icon) {
            const url = `https://cdn.discordapp.com/icons/${guild.id}/${guild.icon}.png?size=128`;
            return `<img class="${className}" src="${esc(url)}" alt="">`;
        }

        const initials = String(guild.name || '?').split(/\s+/).map(word => word[0]).join('').slice(0, 2).toUpperCase();
        return `<span class="${className}" aria-hidden="true">${esc(initials)}</span>`;
    }

    function formatWhen(unix) {
        const date = new Date(unix * 1000);
        const now = new Date();
        const days = Math.floor((new Date(now.getFullYear(), now.getMonth(), now.getDate()) - new Date(date.getFullYear(), date.getMonth(), date.getDate())) / 86400000);
        const time = date.toLocaleTimeString(LANG === 'it' ? 'it-IT' : 'en-GB', { hour: '2-digit', minute: '2-digit' });

        if (days === 0) return `${S.today}, ${time}`;
        if (days === 1) return `${S.yesterday}, ${time}`;

        return `${date.toLocaleDateString(LANG === 'it' ? 'it-IT' : 'en-GB', { day: 'numeric', month: 'short' })}, ${time}`;
    }

    /* ── Stato ────────────────────────────────────────────── */

    const state = {
        csrf: '',
        guildId: root.dataset.guild || '',
        section: root.dataset.section || 'panoramica',
        session: null,
        data: null,
        // Bozze: quello che si sta modificando e non e' ancora salvato.
        drafts: {},
        errors: {},
        saving: false,
        // Una sezione con un salvataggio suo (un messaggio in scrittura) si
        // aggancia qui: { save, discard }. La barra in basso e' la stessa.
        extra: null,
    };

    async function api(path, { method = 'GET', body } = {}) {
        let json;

        try {
            const response = await fetch(`/api/botpanel/${path}`, {
                method,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    ...(method === 'POST' ? { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrf } : {})
                },
                body: method === 'POST' ? JSON.stringify(body || {}) : undefined
            });

            json = await response.json();
        } catch {
            json = { ok: false, code: 'network' };
        }

        if (json && json.csrf) state.csrf = json.csrf;
        return json || { ok: false, code: 'network' };
    }

    /* ── Avvisi ───────────────────────────────────────────── */

    let toasts = null;

    function toast(text, danger = false) {
        if (!toasts) {
            toasts = document.createElement('div');
            toasts.className = 'bp-toasts';
            toasts.setAttribute('aria-live', 'polite');
            document.body.appendChild(toasts);
        }

        const item = document.createElement('div');
        item.className = `bp-toast${danger ? ' bp-toast--danger' : ''}`;
        item.innerHTML = `<i class="fa-solid ${danger ? 'fa-circle-exclamation' : 'fa-circle-check'}" aria-hidden="true"></i><span>${esc(text)}</span>`;
        toasts.appendChild(item);
        setTimeout(() => item.remove(), danger ? 7000 : 3500);
    }

    function failText(response) {
        return {
            conflict: S.conflict,
            invalid: S.invalid,
            rate_limited: S.rate,
            csrf: S.expired,
            origin: S.expired,
            login_required: S.expired,
            home_admin_required: S.readOnly,
            forbidden: S.forbiddenText,
            bot_unreachable: S.botDownText,
        }[response.code] || S.network;
    }

    /* ── Bozze ────────────────────────────────────────────── */

    function draftOf(moduleId) {
        if (!state.drafts[moduleId]) {
            const module = state.data.modules[moduleId];
            state.drafts[moduleId] = { enabled: module.enabled, config: clone(module.config), version: module.version, dirty: false };
        }

        return state.drafts[moduleId];
    }

    /** Valore corrente di un modulo: la bozza se c'e', altrimenti il salvato. */
    function current(moduleId) {
        return state.drafts[moduleId] || state.data.modules[moduleId];
    }

    function markDirty(moduleId) {
        draftOf(moduleId).dirty = true;
        if (state.errors[moduleId]) delete state.errors[moduleId];
        syncSavebar();
    }

    const isDirty = () => Object.values(state.drafts).some(draft => draft.dirty) || !!state.extra;

    function discardDrafts() {
        state.drafts = {};
        state.errors = {};
        state.extra?.discard?.();
        state.extra = null;
    }

    /** Una sezione dice che ha qualcosa di suo da salvare (o non piu'). */
    function setExtra(extra) {
        state.extra = extra;
        syncSavebar();
    }

    /* ── Campi ────────────────────────────────────────────── */

    const canSave = () => !!state.data?.access?.can_save;

    function errorFor(moduleId, path) {
        const code = state.errors[moduleId]?.[path];
        return code ? `<span class="bp-error">${esc(ERRORS[code] || code)}</span>` : '';
    }

    function channelOptions(spec, value) {
        const kinds = spec.kinds || ['text', 'announcement'];
        const need = spec.need || [];

        const options = state.data.channels
            .filter(channel => kinds.includes(channel.kind))
            .map(channel => {
                const missing = need.find(ability => !channel.bot?.[ability]) || (!channel.bot?.view ? 'view' : null);
                const note = missing ? ` — ${ERRORS[`bot_cannot:${missing}`] || ''}` : '';

                // Un canale gia' scelto resta selezionabile anche se adesso
                // manca un permesso: si deve poter vedere cosa e' salvato.
                // Il cancelletto e' dei canali di testo: un vocale non lo ha.
                const mark = channel.kind === 'voice' ? '' : '# ';

                return `<option value="${esc(channel.id)}"${channel.id === value ? ' selected' : ''}${missing && channel.id !== value ? ' disabled' : ''}>${mark}${esc(channel.name)}${esc(note)}</option>`;
            });

        const gone = value && !state.data.channels.some(channel => channel.id === value)
            ? `<option value="${esc(value)}" selected>${esc(ERRORS.channel)}</option>`
            : '';

        return `<option value="">${esc(S.none)}</option>${gone}${options.join('')}`;
    }

    function roleChip(role, id, removable, path, moduleId) {
        const name = role ? role.name : id;
        const color = role?.color || 'var(--nx-text-3)';

        return `<span class="bp-chip"><b style="--c:${esc(color)}"></b>${esc(name)}${removable
            ? `<button type="button" data-role-remove="${esc(id)}" data-m="${esc(moduleId)}" data-p="${esc(path)}" aria-label="${esc(S.remove)} ${esc(name)}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>`
            : ''}</span>`;
    }

    function rolesControl(moduleId, path, spec, value) {
        const chosen = Array.isArray(value) ? value : [];
        const byId = new Map(state.data.roles.map(role => [role.id, role]));
        const top = state.data.access.top_role_position;
        const owner = state.data.access.owner;

        const options = state.data.roles
            .filter(role => !chosen.includes(role.id))
            .map(role => {
                let blocked = '';

                // Stesse regole del salvataggio: qui servono solo a non far
                // scegliere qualcosa che verrebbe rifiutato.
                if (spec.assign) {
                    if (!role.assignable) blocked = ERRORS.role_too_high;
                    else if (role.dangerous) blocked = ERRORS.role_dangerous;
                    else if (!owner && role.position >= top) blocked = ERRORS.role_above_you;
                }

                return `<option value="${esc(role.id)}"${blocked ? ' disabled' : ''}>${esc(role.name)}</option>`;
            });

        const full = chosen.length >= (spec.max || 10);
        const invalid = state.errors[moduleId]?.[path] ? ' is-invalid' : '';

        return `<span class="bp-chips${invalid}">${chosen.map(id => roleChip(byId.get(id), id, true, path, moduleId)).join('')}${full ? '' : `
            <select class="bp-chip bp-chip--add" data-role-add data-m="${esc(moduleId)}" data-p="${esc(path)}" aria-label="${esc(S.addRole)}">
                <option value="">+ ${esc(S.addRole)}</option>${options.join('')}
            </select>`}</span>`;
    }

    /** Il comando di un campo, senza etichetta. */
    function control(moduleId, path, spec, value) {
        const attrs = `data-m="${esc(moduleId)}" data-p="${esc(path)}" data-t="${esc(spec.type)}"`;
        const invalid = state.errors[moduleId]?.[path] ? ' is-invalid' : '';
        const label = esc(tr(spec.label));

        switch (spec.type) {
            case 'bool':
                return `<label class="nx-switch"><input type="checkbox" ${attrs}${value ? ' checked' : ''} aria-label="${label}"><i></i></label>`;

            case 'int':
                return `<input class="nx-input${invalid}" type="number" inputmode="numeric" min="${spec.min}" max="${spec.max}" value="${esc(value)}" ${attrs} aria-label="${label}">`;

            case 'text':
                return spec.multiline
                    ? `<textarea class="nx-input${invalid}" rows="4" maxlength="${spec.max}" ${attrs} aria-label="${label}">${esc(value)}</textarea>`
                    : `<input class="nx-input${invalid}" maxlength="${spec.max}" value="${esc(value)}" ${attrs} aria-label="${label}">`;

            case 'choice':
                return `<select class="nx-input nx-select${invalid}" ${attrs} aria-label="${label}">${Object.entries(spec.options)
                    .map(([option, text]) => `<option value="${esc(option)}"${String(option) === String(value) ? ' selected' : ''}>${esc(tr(text))}</option>`)
                    .join('')}</select>`;

            case 'color':
                return `<span class="bp-color"><input type="color" class="bp-swatch" value="${esc(/^#[0-9a-f]{6}$/i.test(value) ? value : '#5865f2')}" ${attrs} data-color-pick aria-label="${label}"><input class="nx-input${invalid}" maxlength="7" value="${esc(value)}" ${attrs} aria-label="${label}"></span>`;

            case 'channel':
                return `<select class="nx-input nx-select${invalid}" ${attrs} aria-label="${label}">${channelOptions(spec, value)}</select>`;

            case 'roles':
                return rolesControl(moduleId, path, spec, value);

            case 'users':
                return `<textarea class="nx-input${invalid}" rows="3" ${attrs} aria-label="${label}" placeholder="123456789012345678">${esc((value || []).join('\n'))}</textarea>`;

            default:
                return '';
        }
    }

    /** Un campo come riga di impostazione: nome e spiegazione a sinistra. */
    function settingRow(moduleId, path, spec, value) {
        const hint = spec.hint ? `<p class="bp-setting__hint">${esc(tr(spec.hint))}</p>` : (spec.type === 'users' ? `<p class="bp-setting__hint">${esc(S.idsHint)}</p>` : '');

        return `<div class="bp-setting${spec.type === 'bool' ? ' bp-setting--switch' : ''}">
            <div><p class="bp-setting__name">${esc(tr(spec.label))}</p>${hint}</div>
            <div class="bp-setting__control">${control(moduleId, path, spec, value)}${errorFor(moduleId, path)}</div>
        </div>`;
    }

    /** Un campo come campo di modulo: etichetta sopra. */
    function formField(moduleId, path, spec, value) {
        if (spec.type === 'bool') return settingRow(moduleId, path, spec, value);

        return `<div class="nx-field">
            <span class="nx-field__label">${esc(tr(spec.label))}</span>
            ${control(moduleId, path, spec, value)}
            ${spec.hint ? `<span class="nx-field__hint">${esc(tr(spec.hint))}</span>` : ''}
            ${errorFor(moduleId, path)}
        </div>`;
    }

    const SIMPLE = ['int', 'choice', 'text', 'color', 'bool'];

    function defaultItem(itemSpec) {
        const item = {};

        for (const [key, spec] of Object.entries(itemSpec)) {
            if ('default' in spec) item[key] = clone(spec.default);
            else if (spec.type === 'bool') item[key] = false;
            else if (spec.type === 'int') item[key] = spec.min ?? 0;
            else if (['roles', 'users', 'list'].includes(spec.type)) item[key] = [];
            else if (spec.type === 'choice') item[key] = Object.keys(spec.options)[0];
            else item[key] = '';
        }

        return item;
    }

    /**
     * Un elenco di voci: in fila se i campi sono semplici, a blocchi se no.
     * `nested` e' un elenco dentro la voce di un altro (le domande di una
     * categoria): stessa cosa, con un titolo piu' piccolo.
     */
    function listField(moduleId, path, spec, value, nested = false) {
        const items = Array.isArray(value) ? value : [];
        const compact = Object.values(spec.item).every(field => SIMPLE.includes(field.type) && !field.multiline);
        const full = items.length >= spec.max;
        const removeButton = index => `<button class="nx-icon-btn" type="button" data-list-remove="${index}" data-m="${esc(moduleId)}" data-p="${esc(path)}" aria-label="${esc(S.remove)}"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>`;

        const rows = items.map((item, index) => {
            const fields = Object.entries(spec.item)
                .map(([key, field]) => (field.type === 'list'
                    ? listField(moduleId, `${path}.${index}.${key}`, field, item?.[key], true)
                    : formField(moduleId, `${path}.${index}.${key}`, field, item?.[key])))
                .join('');

            return compact
                ? `<div class="bp-item">${fields}${removeButton(index)}</div>`
                : `<div class="bp-item bp-item--block"><div class="bp-form">${fields}<div class="bp-form__foot">${removeButton(index)}</div></div></div>`;
        }).join('');

        if (nested) {
            return `<div class="nx-field">
                <span class="nx-field__label">${esc(tr(spec.label))}</span>
                ${rows}
                ${errorFor(moduleId, path)}
                <span class="bp-form__foot">
                    <button class="bp-chip bp-chip--add" type="button" data-list-add data-m="${esc(moduleId)}" data-p="${esc(path)}"${full ? ' disabled' : ''}><i class="fa-solid fa-plus" aria-hidden="true"></i>${esc(tr(spec.add) || S.add)}</button>
                    ${spec.hint ? `<span class="nx-field__hint">${esc(tr(spec.hint))}</span>` : ''}
                </span>
            </div>`;
        }

        return `<section>
            <h3 class="bp-h">${esc(tr(spec.label))}</h3>
            ${spec.hint ? `<p class="bp-lead" style="margin-bottom:1rem">${esc(tr(spec.hint))}</p>` : ''}
            ${rows}
            ${errorFor(moduleId, path)}
            <div class="bp-bar" style="margin-top:1rem">
                <button class="nx-btn nx-btn--soft nx-btn--sm" type="button" data-list-add data-m="${esc(moduleId)}" data-p="${esc(path)}"${full ? ' disabled' : ''}><i class="fa-solid fa-plus" aria-hidden="true"></i>${esc(tr(spec.add) || S.add)}</button>
                <span class="bp-dim">${items.length} / ${spec.max}</span>
            </div>
        </section>`;
    }

    /**
     * I campi di un modulo: prima le impostazioni singole in righe, poi gli
     * elenchi, ognuno come sezione a se'. `only` limita ai campi indicati.
     */
    function moduleFields(moduleId, only = null) {
        const module = state.data.modules[moduleId];
        const config = current(moduleId).config;
        const entries = Object.entries(module.fields).filter(([key]) => !only || only.includes(key));

        const single = entries.filter(([, spec]) => spec.type !== 'list')
            .map(([key, spec]) => settingRow(moduleId, key, spec, config[key]))
            .join('');

        const lists = entries.filter(([, spec]) => spec.type === 'list')
            .map(([key, spec]) => listField(moduleId, key, spec, config[key]))
            .join('');

        return { single, lists };
    }

    /* ── Pagine di ingresso ───────────────────────────────── */

    function gate(title, text, action = '') {
        return `<div class="bp-gate"><h2>${esc(title)}</h2><p>${esc(text)}</p>${action}</div>`;
    }

    function pickerHead(extra = '') {
        return `<header class="bp-head">
            <span class="bp-head__icon" aria-hidden="true"><i class="fa-solid fa-robot"></i></span>
            <div><h1>Poppy</h1><p>${esc(S.poppyLead)}</p></div>
            ${extra ? `<div class="bp-head__side">${extra}</div>` : ''}
        </header>`;
    }

    async function showPicker(refresh = false) {
        root.innerHTML = `${pickerHead()}<p class="bp-loading">${esc(S.loading)}</p>`;

        const session = await api(`session.php${refresh ? '?refresh=1' : ''}`);
        state.session = session;

        if (!session.ok) {
            root.innerHTML = pickerHead() + gate(S.botDownTitle, session.code === 'not_installed' ? S.notInstalled : failText(session));
            return;
        }

        if (!session.logged) {
            root.innerHTML = pickerHead() + gate(S.loginTitle, S.loginText,
                `<a class="nx-btn nx-btn--primary" href="/${LANG}/accedi">${esc(S.login)}</a>`);
            return;
        }

        if (!session.discord_linked) {
            root.innerHTML = pickerHead() + gate(S.linkTitle, S.linkText,
                `<a class="nx-btn nx-btn--primary" href="/auth/discord_connect.php?return_url=${encodeURIComponent(BASE)}"><i class="fa-brands fa-discord" aria-hidden="true"></i>${esc(S.link)}</a>`);
            return;
        }

        if (!session.bot_online) {
            root.innerHTML = pickerHead() + gate(S.botDownTitle, S.botDownText,
                `<button class="nx-btn nx-btn--outline" type="button" data-picker-refresh>${esc(S.retry)}</button>`);
            return;
        }

        const invite = session.invite_url
            ? `<a class="nx-btn nx-btn--primary" href="${esc(session.invite_url)}" target="_blank" rel="noopener"><i class="fa-solid fa-plus" aria-hidden="true"></i>${esc(S.addToServer)}</a>`
            : '';

        const rows = session.guilds.map(guild => `<li class="bp-row">
            ${serverIcon(guild, 'bp-tile')}
            <div>
                <p class="bp-row__title">${esc(guild.name)}</p>
                <p class="bp-row__meta">${esc(S.members(guild.member_count))}${guild.is_home ? ` · ${esc(S.homeServer)}` : ''}</p>
            </div>
            <div class="bp-row__side">
                <a class="nx-btn nx-btn--outline nx-btn--sm" href="${BASE}/${esc(guild.id)}" data-open-guild="${esc(guild.id)}">${esc(S.manage)}</a>
            </div>
        </li>`).join('');

        root.innerHTML = `${pickerHead(invite)}
            <div class="bp-main bp-narrow">
                <section>
                    <h2 class="bp-h">${esc(S.yourServers)} <small>${session.guilds.length}</small></h2>
                    ${session.guilds.length ? `<ul class="bp-rows">${rows}</ul>` : `<p class="bp-empty">${esc(S.noServers)}</p>`}
                </section>
                <p class="bp-foot">${esc(S.pickerNote)}
                    <button class="nx-link" type="button" data-picker-refresh><span class="nx-link__label">${esc(S.refresh)}</span></button>
                </p>
            </div>`;
    }

    /* ── Il pannello di un server ─────────────────────────── */

    const sections = {};

    function navItems() {
        const modules = Object.values(state.data.modules);
        const item = (id, icon, label, offNote = '') => `<a href="${BASE}/${state.guildId}/${id}" data-nav="${id}"${state.section === id ? ' class="is-active" aria-current="page"' : ''}><i class="fa-solid ${esc(icon)}" aria-hidden="true"></i>${esc(label)}${offNote}</a>`;

        return [
            item('panoramica', 'fa-house', S.overview),
            `<p class="bp-nav__group">${esc(S.modules)}</p>`,
            ...modules.filter(module => module.toggle).map(module => item(
                module.id,
                module.icon,
                tr(module.name),
                current(module.id).enabled ? '' : `<span class="bp-nav__off">${esc(S.off)}</span>`
            )),
            `<p class="bp-nav__group">${esc(S.server)}</p>`,
            ...modules.filter(module => !module.toggle).map(module => item(module.id, module.icon, tr(module.name))),
            item('registro', 'fa-list-check', S.registry),
        ].join('');
    }

    function shell(body) {
        const guild = state.data.guild;

        return `<header class="bp-head">
                ${serverIcon(guild, 'bp-head__icon')}
                <div>
                    <h1>${esc(guild.name)}</h1>
                    <p>${esc(S.members(guild.member_count))}${guild.is_home ? ` · ${esc(S.yourServer)}` : ''}</p>
                </div>
                <div class="bp-head__side">
                    <a class="nx-btn nx-btn--outline nx-btn--sm" href="${BASE}" data-change-server><i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>${esc(S.changeServer)}</a>
                </div>
            </header>
            <div class="bp-layout">
                <nav class="bp-nav" aria-label="${esc(S.modules)}">${navItems()}</nav>
                <div class="bp-main${canSave() ? '' : ' bp-readonly'}">
                    ${canSave() ? '' : `<p class="nx-note"><i class="fa-solid fa-eye" aria-hidden="true"></i><span>${esc(S.readOnly)}</span></p>`}
                    ${body}
                </div>
            </div>
            <div class="bp-savebar" role="status" hidden>
                <span>${esc(S.unsaved)}</span>
                <span class="bp-savebar__actions">
                    <button class="nx-btn nx-btn--sm" type="button" data-discard>${esc(S.cancel)}</button>
                    <button class="nx-btn nx-btn--primary nx-btn--sm" type="button" data-save>${esc(S.save)}</button>
                </span>
            </div>`;
    }

    function syncSavebar() {
        const bar = root.querySelector('.bp-savebar');
        if (!bar) return;

        bar.hidden = !isDirty() || !canSave();
        const button = bar.querySelector('[data-save]');
        button.disabled = state.saving;
        button.textContent = state.saving ? S.saving : S.save;
    }

    function fieldSpecAt(moduleId, path) {
        let fields = state.data.modules[moduleId]?.fields;
        let spec = null;

        for (const key of path.split('.')) {
            if (/^\d+$/.test(key)) continue;
            spec = fields?.[key];
            fields = spec?.item;
        }

        return spec;
    }

    function problemText(problem) {
        const module = state.data.modules[problem.module];
        const spec = problem.path ? fieldSpecAt(problem.module, problem.path) : null;
        const what = ERRORS[problem.code] || problem.code;

        return { title: `${tr(module?.name)}${spec ? ` · ${tr(spec.label)}` : ''}`, text: what };
    }

    /** Titolo, spiegazione, interruttore: la testa di ogni pagina di modulo. */
    function moduleHead(moduleId, extra = '') {
        const module = state.data.modules[moduleId];
        const legacy = state.data.guild.is_home && !module.configured && module.toggle
            ? `<p class="nx-note" style="margin-top:1.25rem"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>${esc(S.fromEnv)}</span></p>`
            : '';

        return `<section>
            <h2 class="bp-title">${esc(tr(module.name))}</h2>
            <p class="bp-lead">${esc(tr(module.lead))}</p>
            ${module.toggle || extra ? `<div class="bp-bar">${extra}${module.toggle ? `<span class="bp-bar__end"><span class="nx-switch">${esc(S.moduleOn)} <label class="nx-switch"><input type="checkbox" data-module-toggle="${esc(moduleId)}"${current(moduleId).enabled ? ' checked' : ''} aria-label="${esc(S.moduleOn)}"><i></i></label></span></span>` : ''}</div>` : ''}
            ${legacy}
        </section>`;
    }

    sections.panoramica = {
        render() {
            const { modules, problems, guild } = state.data;
            const list = Object.values(modules).filter(module => module.toggle);
            const fresh = !guild.is_home && !Object.values(modules).some(module => module.configured);

            const problemRows = problems.map(problem => {
                const { title, text } = problemText(problem);
                return `<li class="bp-row bp-row--stack">
                    <span class="bp-row__icon bp-row__icon--danger"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
                    <div><p class="bp-row__title">${esc(title)}</p><p class="bp-row__meta">${esc(text)}</p></div>
                    <div class="bp-row__side"><a class="nx-btn nx-btn--outline nx-btn--sm" href="${BASE}/${state.guildId}/${esc(problem.module)}" data-nav="${esc(problem.module)}">${esc(S.open)}</a></div>
                </li>`;
            }).join('');

            const start = fresh ? `<section>
                <h2 class="bp-h">${esc(S.startHere)}</h2>
                <p class="bp-lead" style="margin-bottom:1rem">${esc(S.startText)}</p>
                <ol class="bp-rows">${[['general', S.start1], ['moderation', S.start2], ['tickets', S.start3]].map(([target, [title, text]], index) => `
                    <li class="bp-row">
                        <span class="bp-row__icon" aria-hidden="true">${index + 1}</span>
                        <div><p class="bp-row__title">${esc(title)}</p><p class="bp-row__meta">${esc(text)}</p></div>
                        <div class="bp-row__side"><a class="nx-icon-btn" href="${BASE}/${state.guildId}/${target}" data-nav="${target}" aria-label="${esc(S.open)} ${esc(title)}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a></div>
                    </li>`).join('')}</ol>
            </section>` : '';

            const moduleRows = list.map(module => `<li class="bp-row">
                <span class="bp-row__icon"><i class="fa-solid ${esc(module.icon)}" aria-hidden="true"></i></span>
                <div><p class="bp-row__title">${esc(tr(module.name))}</p><p class="bp-row__meta">${esc(tr(module.lead))}</p></div>
                <div class="bp-row__side">
                    <label class="nx-switch"><input type="checkbox" data-module-toggle="${esc(module.id)}" data-now${current(module.id).enabled ? ' checked' : ''} aria-label="${esc(tr(module.name))}"><i></i></label>
                    <a class="nx-icon-btn" href="${BASE}/${state.guildId}/${esc(module.id)}" data-nav="${esc(module.id)}" aria-label="${esc(S.open)} ${esc(tr(module.name))}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                </div>
            </li>`).join('');

            const others = (state.session?.guilds || []).filter(other => other.id !== state.guildId);
            const copy = others.length && canSave() ? `<section>
                <h2 class="bp-h">${esc(S.copyTitle)}</h2>
                <p class="bp-lead" style="margin-bottom:1rem">${esc(S.copyText)}</p>
                <div class="bp-bar">
                    <select class="nx-input nx-select" style="width:16rem" data-copy-from aria-label="${esc(S.copyFrom)}">${others.map(other => `<option value="${esc(other.id)}">${esc(other.name)}</option>`).join('')}</select>
                    <button class="nx-btn nx-btn--outline" type="button" data-copy-go>${esc(S.copyGo)}</button>
                </div>
            </section>` : '';

            return `${start}
                <section>
                    <h2 class="bp-h">${esc(S.toFix)} <small>${problems.length || ''}</small></h2>
                    ${problems.length ? `<ul class="bp-rows">${problemRows}</ul>` : `<p class="bp-empty">${esc(S.allGood)}</p>`}
                </section>
                <section><h2 class="bp-h">${esc(S.modules)}</h2><ul class="bp-rows">${moduleRows}</ul></section>
                ${copy}`;
        }
    };

    /** Pagina di un modulo senza una forma sua: testa, righe, elenchi. */
    function genericSection(moduleId) {
        return {
            render() {
                const { single, lists } = moduleFields(moduleId);

                return `${moduleHead(moduleId)}
                    ${single ? `<section class="bp-settings">${single}</section>` : ''}
                    ${lists}`;
            }
        };
    }

    /**
     * Il contenuto di una riga del registro, leggibile: gli ID di canali e
     * ruoli diventano i loro nomi.
     */
    function pretty(value) {
        const channels = new Map(state.data.channels.map(channel => [channel.id, `#${channel.name}`]));
        const roles = new Map(state.data.roles.map(role => [role.id, `@${role.name}`]));

        return JSON.stringify(value?.config ?? value, null, 2)
            .replace(/"(\d{15,25})"/g, (match, id) => (channels.get(id) || roles.get(id) ? `"${channels.get(id) || roles.get(id)}"` : match));
    }

    sections.registro = {
        page: 0,
        filter: '',
        entries: [],
        more: false,
        open: null,

        render() {
            const modules = Object.values(state.data.modules);
            const tab = (id, label) => `<button class="nx-tab${this.filter === id ? ' is-active' : ''}" role="tab" aria-selected="${this.filter === id}" data-audit-filter="${esc(id)}">${esc(label)}</button>`;

            const rows = this.entries.map(entry => {
                const module = state.data.modules[entry.module];
                const opened = this.open === entry.id;
                const detail = opened ? `<tr><td colspan="5" style="padding-left:0"><dl class="bp-diff">
                        <div><dt>${esc(S.before)}</dt><dd>${entry.before ? `<pre>${esc(pretty(entry.before))}</pre>` : esc(S.nothingBefore)}</dd></div>
                        <div><dt>${esc(S.after)}</dt><dd>${entry.after ? `<pre>${esc(pretty(entry.after))}</pre>` : '—'}</dd></div>
                        ${entry.restorable && canSave() ? `<div class="bp-diff__foot"><button class="nx-btn nx-btn--outline nx-btn--sm" type="button" data-audit-restore="${entry.id}"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i>${esc(S.restore)}</button></div>` : ''}
                    </dl></td></tr>` : '';

                return `<tr>
                        <td class="bp-dim">${esc(formatWhen(entry.when))}</td>
                        <td><span class="bp-who">${esc(entry.who || entry.discord_id || '—')}</span></td>
                        <td>${esc(tr(module?.name) || entry.module)}</td>
                        <td><button class="bp-rowlink bp-always" type="button" data-audit-open="${entry.id}" aria-expanded="${opened}">${esc(S.actions[entry.action] || entry.action)} <i class="fa-solid fa-chevron-${opened ? 'up' : 'down'}" aria-hidden="true" style="font-size:.7rem;color:var(--nx-text-3)"></i></button></td>
                        <td class="bp-dim">${esc(S.sources[entry.source] || entry.source)}</td>
                    </tr>${detail}`;
            }).join('');

            return `<section>
                    <h2 class="bp-title">${esc(S.registry)}</h2>
                    <p class="bp-lead">${esc(S.registryLead)}</p>
                    <div class="bp-bar"><div class="nx-tabs" role="tablist">${tab('', S.all)}${modules.map(module => tab(module.id, tr(module.name))).join('')}</div></div>
                </section>
                <section>
                    ${this.entries.length ? `<div class="bp-table-wrap"><table class="nx-table">
                        <thead><tr><th>${esc(S.when)}</th><th>${esc(S.who)}</th><th>${esc(S.where)}</th><th>${esc(S.what)}</th><th>${esc(S.from)}</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table></div>` : `<p class="bp-empty">${esc(this.loaded ? S.registryEmpty : S.loading)}</p>`}
                    ${this.more ? `<div class="bp-bar"><button class="nx-btn nx-btn--soft nx-btn--sm bp-always" type="button" data-audit-more>${esc(S.more)}</button></div>` : ''}
                </section>`;
        },

        async load(reset = true) {
            if (reset) {
                this.page = 0;
                this.entries = [];
            }

            this.loading = true;
            const response = await api(`audit.php?guild=${state.guildId}&page=${this.page}${this.filter ? `&module=${encodeURIComponent(this.filter)}` : ''}`);
            this.loading = false;
            this.loaded = true;

            if (response.ok) {
                this.entries = this.entries.concat(response.entries);
                this.more = response.more;
            }

            if (state.section === 'registro') renderSection();
        },

        mount() {
            // Cambiato server: il registro di prima non vale piu'.
            if (this.guild !== state.guildId) {
                this.guild = state.guildId;
                this.loaded = false;
                this.entries = [];
                this.open = null;
            }

            if (!this.loaded && !this.loading) this.load();
        }
    };

    function renderSection() {
        if (!sections[state.section]) {
            const module = state.data.modules[state.section];
            if (module) sections[state.section] = genericSection(state.section);
            else state.section = 'panoramica';
        }

        const section = sections[state.section];
        root.innerHTML = shell(section.render());
        syncSavebar();
        section.mount?.();
    }

    async function showGuild() {
        root.innerHTML = `<p class="bp-loading">${esc(S.loading)}</p>`;

        // L'elenco dei server serve per «copia da un altro server»; se non
        // c'e' ancora lo si chiede insieme, senza aspettarlo.
        if (!state.session) api('session.php').then(session => { state.session = session; });

        const data = await api(`guild.php?guild=${state.guildId}`);

        if (!data.ok) {
            const back = `<a class="nx-btn nx-btn--outline" href="${BASE}">${esc(S.backToServers)}</a>`;

            if (data.code === 'login_required') return showPicker();
            if (data.code === 'discord_not_linked') return showPicker();

            root.innerHTML = pickerHead() + ({
                forbidden: gate(S.forbiddenTitle, S.forbiddenText, back),
                bot_not_in_guild: gate(S.missingTitle, S.missingText, back),
                not_installed: gate(S.botDownTitle, S.notInstalled),
            }[data.code] || gate(S.botDownTitle, S.botDownText, `<button class="nx-btn nx-btn--outline" type="button" data-guild-retry>${esc(S.retry)}</button>`));
            return;
        }

        state.data = data;
        discardDrafts();
        renderSection();
    }

    /* ── Salvataggio ──────────────────────────────────────── */

    async function saveModule(moduleId) {
        const draft = state.drafts[moduleId];

        const response = await api('settings.php', {
            method: 'POST',
            body: { guild: state.guildId, module: moduleId, enabled: draft.enabled, version: draft.version, config: draft.config }
        });

        if (!response.ok) {
            if (response.code === 'invalid') state.errors[moduleId] = response.errors || {};
            return response;
        }

        Object.assign(state.data.modules[moduleId], {
            enabled: response.enabled,
            config: response.config,
            version: response.version,
            configured: true
        });
        delete state.drafts[moduleId];
        delete state.errors[moduleId];

        return response;
    }

    async function saveAll() {
        if (state.saving) return;
        state.saving = true;
        syncSavebar();

        let failure = null;
        let applied = true;

        for (const moduleId of Object.keys(state.drafts)) {
            if (!state.drafts[moduleId].dirty) continue;

            const response = await saveModule(moduleId);
            if (!response.ok) {
                failure = response;
                break;
            }
            applied = applied && response.applied;
        }

        if (!failure && state.extra) {
            const response = await state.extra.save();

            if (!response.ok) {
                failure = response;
            } else {
                applied = applied && response.applied !== false;
                state.extra = null;
            }
        }

        state.saving = false;

        if (failure) {
            toast(failText(failure), true);
        } else {
            toast(applied ? S.saved : S.savedLater);
            // I problemi dipendono dalle impostazioni: si ricalcolano.
            const fresh = await api(`guild.php?guild=${state.guildId}`);
            if (fresh.ok) state.data = fresh;
        }

        renderSection();

        const firstError = root.querySelector('.bp-error');
        if (firstError) firstError.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    /* ── Navigazione ──────────────────────────────────────── */

    function navigate(section, push = true) {
        if (isDirty() && !window.confirm(S.leave)) return;

        discardDrafts();
        state.section = section;
        if (push) history.pushState({ section }, '', `${BASE}/${state.guildId}/${section === 'panoramica' ? '' : section}`.replace(/\/$/, ''));
        renderSection();
        window.scrollTo({ top: 0 });
    }

    window.addEventListener('popstate', () => {
        const match = location.pathname.match(/\/bot\/(\d{15,25})(?:\/([a-z-]+))?/);

        if (!match) {
            state.guildId = '';
            return showPicker();
        }

        if (match[1] !== state.guildId) {
            state.guildId = match[1];
            state.section = match[2] || 'panoramica';
            return showGuild();
        }

        state.section = match[2] || 'panoramica';
        discardDrafts();
        renderSection();
    });

    window.addEventListener('beforeunload', event => {
        if (isDirty()) event.preventDefault();
    });

    /* ── Eventi ───────────────────────────────────────────── */

    function readControl(target) {
        switch (target.dataset.t) {
            case 'bool':
                return target.checked;
            case 'int':
                return target.value === '' ? '' : Number(target.value);
            case 'users':
                return target.value.split(/[\s,]+/).map(id => id.trim()).filter(Boolean);
            case 'choice': {
                // Le scelte numeriche (durate, giorni) restano numeri.
                const spec = fieldSpecAt(target.dataset.m, target.dataset.p);
                const numeric = spec && Object.keys(spec.options).every(option => /^\d+$/.test(option));
                return numeric ? Number(target.value) : target.value;
            }
            default:
                return target.value;
        }
    }

    root.addEventListener('input', event => {
        const target = event.target;

        // Un campo che non e' un'impostazione di modulo appartiene alla
        // sezione aperta (l'editor dei messaggi): ci pensa lei.
        if (!target.dataset?.m || !target.dataset.p || !target.dataset.t) {
            sections[state.section]?.onChange?.(target);
            return;
        }

        const draft = draftOf(target.dataset.m);
        setPath(draft.config, target.dataset.p, readControl(target));
        markDirty(target.dataset.m);

        // Il campione del colore e il suo codice restano allineati.
        if (target.dataset.t === 'color') {
            const pair = target.closest('.bp-color');
            pair?.querySelectorAll('input').forEach(input => {
                if (input !== target && /^#[0-9a-f]{6}$/i.test(target.value)) input.value = target.value;
            });
        }

        sections[state.section]?.onChange?.(target);
    });

    root.addEventListener('change', async event => {
        const target = event.target;

        if (target.matches('[data-role-add]') && target.value) {
            const draft = draftOf(target.dataset.m);
            const list = getPath(draft.config, target.dataset.p) || [];
            setPath(draft.config, target.dataset.p, [...list, target.value]);
            markDirty(target.dataset.m);
            return renderSection();
        }

        if (target.matches('[data-module-toggle]')) {
            const moduleId = target.dataset.moduleToggle;
            draftOf(moduleId).enabled = target.checked;
            markDirty(moduleId);

            // Nella panoramica l'interruttore vale subito, senza barra.
            if ('now' in target.dataset) {
                const response = await saveModule(moduleId);

                if (!response.ok) {
                    discardDrafts();
                    toast(response.code === 'invalid' ? S.invalid : failText(response), true);
                    // Manca qualcosa di obbligatorio: lo si sceglie nella sua pagina.
                    if (response.code === 'invalid') {
                        state.section = moduleId;
                        history.pushState({ section: moduleId }, '', `${BASE}/${state.guildId}/${moduleId}`);
                        draftOf(moduleId).enabled = true;
                        markDirty(moduleId);
                        state.errors[moduleId] = response.errors || {};
                    }
                } else {
                    toast(response.applied ? S.saved : S.savedLater);
                    const fresh = await api(`guild.php?guild=${state.guildId}`);
                    if (fresh.ok) state.data = fresh;
                }

                return renderSection();
            }

            return renderSection();
        }
    });

    root.addEventListener('click', async event => {
        const target = event.target.closest('button, a');
        if (!target) return;

        if (target.matches('[data-picker-refresh]')) return showPicker(true);
        if (target.matches('[data-guild-retry]')) return showGuild();

        if (target.matches('[data-open-guild]')) {
            event.preventDefault();
            state.guildId = target.dataset.openGuild;
            state.section = 'panoramica';
            history.pushState({}, '', `${BASE}/${state.guildId}`);
            return showGuild();
        }

        if (target.matches('[data-change-server]')) {
            event.preventDefault();
            if (isDirty() && !window.confirm(S.leave)) return;
            discardDrafts();
            state.guildId = '';
            state.data = null;
            history.pushState({}, '', BASE);
            return showPicker();
        }

        if (target.matches('[data-nav]')) {
            event.preventDefault();
            return navigate(target.dataset.nav);
        }

        if (target.matches('[data-role-remove]')) {
            const draft = draftOf(target.dataset.m);
            const list = getPath(draft.config, target.dataset.p) || [];
            setPath(draft.config, target.dataset.p, list.filter(id => id !== target.dataset.roleRemove));
            markDirty(target.dataset.m);
            return renderSection();
        }

        if (target.matches('[data-list-add]')) {
            const draft = draftOf(target.dataset.m);
            const spec = fieldSpecAt(target.dataset.m, target.dataset.p);
            const list = getPath(draft.config, target.dataset.p) || [];
            setPath(draft.config, target.dataset.p, [...list, defaultItem(spec.item)]);
            markDirty(target.dataset.m);
            return renderSection();
        }

        if (target.matches('[data-list-remove]')) {
            const draft = draftOf(target.dataset.m);
            const list = getPath(draft.config, target.dataset.p) || [];
            setPath(draft.config, target.dataset.p, list.filter((_, index) => index !== Number(target.dataset.listRemove)));
            markDirty(target.dataset.m);
            return renderSection();
        }

        if (target.matches('[data-discard]')) {
            discardDrafts();
            return renderSection();
        }

        if (target.matches('[data-save]')) return saveAll();

        if (target.matches('[data-copy-go]')) {
            const from = root.querySelector('[data-copy-from]')?.value;
            const modules = Object.keys(state.data.modules);
            target.disabled = true;

            const response = await api('copy.php', { method: 'POST', body: { guild: state.guildId, from, modules } });
            if (!response.ok) {
                toast(failText(response), true);
                target.disabled = false;
                return;
            }

            toast(response.copied.length ? S.copied(response.copied.length) : S.copiedNone, !response.copied.length);
            return showGuild();
        }

        const registro = sections.registro;

        if (target.matches('[data-audit-filter]')) {
            registro.filter = target.dataset.auditFilter;
            registro.loaded = false;
            registro.open = null;
            renderSection();
            return registro.load();
        }

        if (target.matches('[data-audit-open]')) {
            const id = Number(target.dataset.auditOpen);
            registro.open = registro.open === id ? null : id;
            return renderSection();
        }

        if (target.matches('[data-audit-more]')) {
            registro.page += 1;
            return registro.load(false);
        }

        if (target.matches('[data-audit-restore]')) {
            if (!window.confirm(S.restoreAsk)) return;

            const response = await api('audit.php', { method: 'POST', body: { guild: state.guildId, audit_id: Number(target.dataset.auditRestore) } });
            if (!response.ok) return toast(failText(response), true);

            toast(S.restored);
            registro.loaded = false;
            registro.open = null;
            const fresh = await api(`guild.php?guild=${state.guildId}`);
            if (fresh.ok) state.data = fresh;
            renderSection();
            return registro.load();
        }

        sections[state.section]?.onClick?.(target, event);
    });

    /* ── Per le sezioni in panel-sections.js ──────────────── */

    window.BotPanel = {
        LANG, S, ERRORS, state, sections, api, esc, tr, clone, toast, failText,
        draftOf, current, markDirty, canSave, moduleHead, moduleFields, control, formField, settingRow,
        errorFor, roleChip, renderSection, formatWhen, getPath, setPath, defaultItem, setExtra, channelOptions, syncSavebar
    };

    // Le sezioni con una forma loro si registrano al caricamento del loro
    // file, che arriva dopo questo: si parte quando la pagina e' pronta.
    document.addEventListener('DOMContentLoaded', () => (state.guildId ? showGuild() : showPicker()));
})();
