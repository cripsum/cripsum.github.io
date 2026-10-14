/*
 * Le pagine del pannello in cui si lavora, non solo si configura: ticket
 * (leggere, rispondere, assegnare, chiudere), giveaway (lanciare, chiudere,
 * rifare l'estrazione), ruoli a scelta e candidature (pubblicare, decidere).
 *
 * Si appoggiano a BotPanel (panel.js) per campi, bozze, salvataggio e avvisi.
 * Ogni azione passa dal sito, che ricontrolla i permessi, e dal bot, che fa
 * la stessa cosa che farebbe il comando su Discord.
 */
(() => {
    'use strict';

    const P = window.BotPanel;
    if (!P) return;

    const { LANG, S, state, sections, api, esc } = P;
    const it = LANG === 'it';

    /* ── Pezzi comuni ─────────────────────────────────────── */

    const C = {
        it: {
            failed: {
                module_off: 'Il modulo è spento: accendilo, salva e riprova.',
                not_saved: 'Salva le modifiche prima.',
                channel_not_found: 'Poppy non trova quel canale.',
                send_failed: 'Poppy non riesce a scrivere lì: controlla i suoi permessi in quel canale.',
                no_categories: 'Aggiungi almeno una categoria.',
                no_roles: 'Il menu non ha ruoli validi.',
                no_forms: 'Aggiungi almeno un modulo con delle domande.',
                menu_not_found: 'Salva il menu prima di pubblicarlo.',
                ticket_not_found: 'Questo ticket non c\'è più su Discord.',
                member_not_found: 'Quella persona non è più nel server.',
                taken: 'Qualcun altro lo ha appena preso in carico.',
                closed: 'Il ticket è chiuso: riaprilo per rispondere.',
                forbidden: 'Su Discord non hai più i permessi per gestire questo server.',
                not_found: 'Non lo trovo più.',
                already: 'Qualcuno ha già deciso.',
                refused: 'Il sito ha rifiutato: per i premi del sito serve un account amministratore collegato a Discord.',
                site_unreachable: 'Il bot non raggiunge il sito.',
                site_prizes_home_only: 'I premi del sito si regalano solo nel server di Cripsum.',
                bot_not_in_guild: 'Poppy non è più in questo server.',
                failed: 'Non è andata. Riprova fra poco.',
            },
            never: 'mai pubblicato',
            openDiscord: 'Apri su Discord',
            publishIn: 'Pubblica in',
            publish: 'Pubblica',
            update: 'Aggiorna',
            published: 'Pubblicato.',
            updated: 'Aggiornato sul posto.',
            saveFirst: 'Salva prima le modifiche.',
            none: 'Nessuno',
        },
        en: {
            failed: {
                module_off: 'The module is off: turn it on, save and try again.',
                not_saved: 'Save your changes first.',
                channel_not_found: 'Poppy can\'t find that channel.',
                send_failed: 'Poppy can\'t write there: check its permissions in that channel.',
                no_categories: 'Add at least one category.',
                no_roles: 'The menu has no valid roles.',
                no_forms: 'Add at least one form with questions.',
                menu_not_found: 'Save the menu before posting it.',
                ticket_not_found: 'This ticket is not on Discord anymore.',
                member_not_found: 'That person is not in the server anymore.',
                taken: 'Someone else just took it.',
                closed: 'The ticket is closed: reopen it to reply.',
                forbidden: 'On Discord you no longer have the permissions to manage this server.',
                not_found: 'I can\'t find it anymore.',
                already: 'Someone already decided.',
                refused: 'The website refused: website prizes need an admin account linked to Discord.',
                site_unreachable: 'The bot can\'t reach the website.',
                site_prizes_home_only: 'Website prizes can only be given away in the Cripsum server.',
                bot_not_in_guild: 'Poppy is not in this server anymore.',
                failed: 'It didn\'t work. Try again in a moment.',
            },
            never: 'never posted',
            openDiscord: 'Open on Discord',
            publishIn: 'Post in',
            publish: 'Post',
            update: 'Update',
            published: 'Posted.',
            updated: 'Updated in place.',
            saveFirst: 'Save your changes first.',
            none: 'None',
        }
    }[LANG];

    /** Il messaggio di un'azione andata male, nella lingua della pagina. */
    function failure(response) {
        return C.failed[response.reason] || C.failed[response.code] || P.failText(response);
    }

    /** Una richiesta POST con il bottone spento finche' non arriva la risposta. */
    async function act(button, path, body) {
        if (button) button.disabled = true;
        const response = await api(path, { method: 'POST', body: { guild: state.guildId, ...body } });
        if (button && button.isConnected) button.disabled = false;
        if (!response.ok) P.toast(failure(response), true);
        return response;
    }

    function person(id, users) {
        if (!id) return '<span class="bp-dim">—</span>';

        const user = users?.[id];
        const avatar = user?.avatar ? `<img src="${esc(user.avatar)}" alt="">` : '';

        return `<span class="bp-who">${avatar}${esc(user?.name || id)}</span>`;
    }

    function roleOptions(selected, { empty = C.none } = {}) {
        return `<option value="">${esc(empty)}</option>`
            + state.data.roles.map(role => `<option value="${esc(role.id)}"${role.id === selected ? ' selected' : ''}>@${esc(role.name)}</option>`).join('');
    }

    /** Testo di Discord, leggibile: grassetto, corsivo, codice, a capo. */
    function md(text) {
        return esc(text)
            .replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>')
            .replace(/(^|[\s(])\*([^*\n]+)\*/g, '$1<i>$2</i>')
            .replace(/`([^`\n]+)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');
    }

    /** Un modulo con modifiche non salvate non si pubblica: si pubblica quello salvato. */
    function moduleReady(moduleId) {
        const module = state.data.modules[moduleId];
        return module.configured && module.enabled && !P.draftOf(moduleId)?.dirty;
    }

    function savedConfig(moduleId) {
        return state.data.modules[moduleId]?.config || {};
    }

    /* ── Ticket ───────────────────────────────────────────── */

    const TT = {
        it: {
            panel: 'Pannello su Discord',
            panelLead: 'Il messaggio da cui le persone aprono un ticket, nel canale dei ticket. Se cambi categorie o testi, salvalo e premi «Aggiorna»: Poppy riscrive quello già pubblicato.',
            panelPublish: 'Pubblica il pannello',
            panelUpdate: 'Aggiorna il pannello',
            list: 'Ticket',
            open: 'Aperti',
            closedTab: 'Chiusi di recente',
            notInstalled: 'I ticket non sono ancora attivi sul sito: manca la migrazione della fase 2.',
            noneOpen: 'Nessun ticket aperto.',
            noneClosed: 'Nessun ticket chiuso negli ultimi 14 giorni.',
            stats: s => [
                s.open + (s.open === 1 ? ' aperto' : ' aperti'),
                s.closed_30d + ' chiusi negli ultimi 30 giorni',
                s.rating_30d !== null ? 'voto medio ' + String(s.rating_30d).replace('.', ',') + ' su 5 (' + s.rated_30d + (s.rated_30d === 1 ? ' voto)' : ' voti)') : '',
                s.minutes_30d !== null ? 'chiusi in media dopo ' + span(s.minutes_30d) : '',
            ].filter(Boolean).join(' · '),
            n: 'N°', subject: 'Oggetto', category: 'Categoria', opener: 'Aperto da', assignee: 'Segue', when: 'Aperto', closedAt: 'Chiuso', rating: 'Voto',
            back: 'Tutti i ticket',
            take: 'Prendilo tu',
            release: 'Liberalo',
            assignTo: 'Assegna a…',
            close: 'Chiudi il ticket',
            closeAsk: 'Chiudere questo ticket? La trascrizione va nel canale dei log.',
            reopen: 'Riapri',
            reply: 'Rispondi',
            replyHint: 'Esce su Discord a nome di Poppy, con il tuo nome e la tua foto in testa.',
            replied: 'Risposta inviata.',
            closedOk: 'Ticket chiuso.',
            reopenedOk: 'Ticket riaperto.',
            assignedOk: 'Fatto.',
            loadingConvo: 'Leggo la conversazione…',
            empty: 'Nessun messaggio.',
            auto: 'chiuso da solo',
            deleted: 'thread cancellato',
            discordOnly: 'Qui ci sono i ticket di Discord (#0001). Quelli del supporto del sito (TK-…) si gestiscono dall\'area supporto.',
        },
        en: {
            panel: 'Panel on Discord',
            panelLead: 'The message people open a ticket from, in the ticket channel. If you change categories or texts, save and press "Update": Poppy rewrites the one already posted.',
            panelPublish: 'Post the panel',
            panelUpdate: 'Update the panel',
            list: 'Tickets',
            open: 'Open',
            closedTab: 'Recently closed',
            notInstalled: 'Tickets are not active on the website yet: the phase 2 migration is missing.',
            noneOpen: 'No open tickets.',
            noneClosed: 'No tickets closed in the last 14 days.',
            stats: s => [
                s.open + ' open',
                s.closed_30d + ' closed in the last 30 days',
                s.rating_30d !== null ? 'average rating ' + s.rating_30d + ' out of 5 (' + s.rated_30d + (s.rated_30d === 1 ? ' rating)' : ' ratings)') : '',
                s.minutes_30d !== null ? 'closed after ' + span(s.minutes_30d) + ' on average' : '',
            ].filter(Boolean).join(' · '),
            n: 'No.', subject: 'Subject', category: 'Category', opener: 'Opened by', assignee: 'Handled by', when: 'Opened', closedAt: 'Closed', rating: 'Rating',
            back: 'All tickets',
            take: 'Take it',
            release: 'Release it',
            assignTo: 'Assign to…',
            close: 'Close the ticket',
            closeAsk: 'Close this ticket? The transcript goes to the log channel.',
            reopen: 'Reopen',
            reply: 'Reply',
            replyHint: 'Goes out on Discord as Poppy, with your name and picture on top.',
            replied: 'Reply sent.',
            closedOk: 'Ticket closed.',
            reopenedOk: 'Ticket reopened.',
            assignedOk: 'Done.',
            loadingConvo: 'Reading the conversation…',
            empty: 'No messages.',
            auto: 'closed on its own',
            deleted: 'thread deleted',
            discordOnly: 'These are the Discord tickets (#0001). Website support tickets (TK-…) are handled in the support area.',
        }
    }[LANG];

    /** Minuti in parole: 45 min, 3 h, 2 giorni. */
    function span(minutes) {
        if (minutes < 60) return minutes + ' min';
        if (minutes < 60 * 48) return Math.round(minutes / 60) + ' h';
        return Math.round(minutes / 1440) + (it ? ' giorni' : ' days');
    }

    function code(number) {
        return '#' + String(number).padStart(4, '0');
    }

    function conversation(messages) {
        if (!messages.length) return `<p class="bp-empty">${esc(TT.empty)}</p>`;

        return messages.map(message => {
            const embeds = message.embeds.map(embed => `<div class="dc-embed" style="--embed:#4e5058">
                    ${embed.author ? `<p class="dc-embed__foot" style="grid-column:1;margin:0;color:#f2f3f5;font-weight:600">${esc(embed.author)}</p>` : ''}
                    ${embed.title ? `<p class="dc-embed__title" style="grid-column:1">${md(embed.title)}</p>` : ''}
                    ${embed.description ? `<p class="dc-embed__text" style="grid-column:1">${md(embed.description)}</p>` : ''}
                    ${embed.fields.length ? `<div class="dc-embed__fields" style="grid-column:1/-1">${embed.fields.map(field => `<div><b>${esc(field.name)}</b><br>${md(field.value)}</div>`).join('')}</div>` : ''}
                </div>`).join('');

            const files = message.attachments.map(file => file.image
                ? `<a href="${esc(file.url)}" target="_blank" rel="noopener"><img class="bp-convo__image" src="${esc(file.url)}" alt="${esc(file.name)}" loading="lazy" referrerpolicy="no-referrer"></a>`
                : `<a class="bp-convo__file" href="${esc(file.url)}" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip" aria-hidden="true"></i>${esc(file.name)}</a>`).join('');

            const avatar = message.author.avatar
                ? `<img class="dc-avatar" src="${esc(message.author.avatar)}" alt="" loading="lazy">`
                : '<span class="dc-avatar dc-ph" aria-hidden="true"><i class="fa-solid fa-user"></i></span>';

            return `<div class="dc-msg">
                ${avatar}
                <div>
                    <span class="dc-name">${esc(message.author.name)}</span>${message.author.bot ? '<span class="dc-app">APP</span>' : ''}<span class="dc-time"> ${esc(P.formatWhen(message.when))}</span>
                    ${message.content ? `<p style="margin:0">${md(message.content)}</p>` : ''}
                    ${embeds}${files}
                </div>
            </div>`;
        }).join('');
    }

    sections.tickets = {
        data: null,
        tab: 'open',
        view: null,

        reset() {
            this.data = null;
            this.view = null;
            this.tab = 'open';
        },

        panelBar() {
            const ready = moduleReady('tickets');
            const posted = this.data?.panel;

            return `<section>
                <h2 class="bp-h">${esc(TT.panel)}</h2>
                <p class="bp-lead" style="margin-bottom:1rem">${esc(TT.panelLead)}</p>
                <div class="bp-bar">
                    <button class="nx-btn nx-btn--soft nx-btn--sm" type="button" data-tk-publish${ready ? '' : ' disabled'}><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>${esc(posted ? TT.panelUpdate : TT.panelPublish)}</button>
                    ${posted?.url ? `<a class="nx-btn nx-btn--outline nx-btn--sm bp-always" href="${esc(posted.url)}" target="_blank" rel="noopener">${esc(C.openDiscord)}</a>` : ''}
                    ${ready ? '' : `<span class="nx-field__hint">${esc(C.saveFirst)}</span>`}
                </div>
            </section>`;
        },

        list() {
            if (this.data === null) return `<p class="bp-empty">${esc(S.loading)}</p>`;
            if (!this.data.available) return `<p class="bp-empty">${esc(TT.notInstalled)}</p>`;

            const open = this.tab === 'open';
            const rows = this.data.tickets.filter(ticket => (ticket.status === 'open') === open);
            const users = this.data.users;

            const tabs = `<div class="nx-tabs" role="tablist">
                <button class="nx-tab bp-always${open ? ' is-active' : ''}" role="tab" aria-selected="${open}" data-tk-tab="open">${esc(TT.open)} · ${this.data.stats.open}</button>
                <button class="nx-tab bp-always${open ? '' : ' is-active'}" role="tab" aria-selected="${!open}" data-tk-tab="closed">${esc(TT.closedTab)}</button>
            </div>`;

            const body = rows.length ? `<div class="bp-table-wrap"><table class="nx-table">
                <thead><tr><th>${esc(TT.n)}</th><th>${esc(TT.subject)}</th><th>${esc(TT.opener)}</th><th>${esc(open ? TT.assignee : TT.rating)}</th><th>${esc(open ? TT.when : TT.closedAt)}</th></tr></thead>
                <tbody>${rows.map(ticket => `<tr>
                    <td class="bp-num">${esc(code(ticket.number))}</td>
                    <td><button class="bp-rowlink bp-always" type="button" data-tk-view="${esc(ticket.thread_id)}">${esc(ticket.subject)}</button><br><span class="bp-dim">${esc(ticket.category)}</span></td>
                    <td>${person(ticket.opener_id, users)}</td>
                    <td>${open
                        ? (ticket.claimed_by ? person(ticket.claimed_by, users) : '<span class="bp-dim">—</span>')
                        : (ticket.rating ? esc('★'.repeat(ticket.rating) + '☆'.repeat(5 - ticket.rating)) : `<span class="bp-dim">${esc(ticket.close_reason === 'auto' ? TT.auto : ticket.close_reason === 'deleted' ? TT.deleted : '—')}</span>`)}</td>
                    <td class="bp-dim">${esc(P.formatWhen(open ? ticket.opened : ticket.closed))}</td>
                </tr>`).join('')}</tbody>
            </table></div>` : `<p class="bp-empty">${esc(open ? TT.noneOpen : TT.noneClosed)}</p>`;

            return `<p class="bp-lead" style="margin-bottom:1rem">${esc(TT.stats(this.data.stats))}</p>
                <div class="bp-bar">${tabs}</div>
                ${body}
                <p class="bp-dim" style="margin-top:1rem;font-size:.8125rem">${esc(TT.discordOnly)}</p>`;
        },

        ticketView() {
            const view = this.view;
            const summary = this.data?.tickets.find(ticket => ticket.thread_id === view.thread_id);
            const ticket = view.data?.ticket || null;
            const open = (ticket?.status || summary?.status) === 'open';
            const users = this.data?.users || {};
            const claimed = ticket?.claimed_by ?? summary?.claimed_by ?? null;
            const me = state.session?.discord_id;

            const staffOptions = (view.staff || [])
                .map(member => `<option value="${esc(member.id)}">${esc(member.name)}</option>`).join('');

            const actions = open ? `<div class="bp-bar">
                    ${claimed && claimed === me
                        ? `<button class="nx-btn nx-btn--outline nx-btn--sm" type="button" data-tk-act="release">${esc(TT.release)}</button>`
                        : `<button class="nx-btn nx-btn--outline nx-btn--sm" type="button" data-tk-act="take">${esc(TT.take)}</button>`}
                    <select class="nx-input nx-select" style="width:13rem" data-tk-assign aria-label="${esc(TT.assignTo)}"><option value="">${esc(TT.assignTo)}</option>${staffOptions}</select>
                    ${view.data?.url ? `<a class="nx-btn nx-btn--outline nx-btn--sm bp-always" href="${esc(view.data.url)}" target="_blank" rel="noopener">${esc(C.openDiscord)}</a>` : ''}
                    <button class="nx-btn nx-btn--danger nx-btn--sm bp-bar__end" type="button" data-tk-act="close">${esc(TT.close)}</button>
                </div>` : `<div class="bp-bar">
                    <button class="nx-btn nx-btn--outline nx-btn--sm" type="button" data-tk-act="reopen">${esc(TT.reopen)}</button>
                    ${view.data?.url ? `<a class="nx-btn nx-btn--outline nx-btn--sm bp-always" href="${esc(view.data.url)}" target="_blank" rel="noopener">${esc(C.openDiscord)}</a>` : ''}
                </div>`;

            const composer = open ? `<div class="bp-composer">
                    <textarea class="nx-input" rows="3" maxlength="4000" data-tk-text aria-label="${esc(TT.reply)}">${esc(view.draft || '')}</textarea>
                    <div class="bp-bar">
                        <button class="nx-btn nx-btn--soft nx-btn--sm" type="button" data-tk-act="reply"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>${esc(TT.reply)}</button>
                        <span class="nx-field__hint">${esc(TT.replyHint)}</span>
                    </div>
                </div>` : '';

            return `<section>
                <button class="nx-link bp-always" type="button" data-tk-back><i class="fa-solid fa-chevron-left" aria-hidden="true" style="font-size:.7rem"></i><span class="nx-link__label">${esc(TT.back)}</span></button>
                <h2 class="bp-title" style="margin-top:1rem">${esc(summary ? code(summary.number) + ' · ' + summary.subject : '')}</h2>
                <p class="bp-lead">${summary ? `${esc(summary.category)} · ${esc(TT.opener.toLowerCase())} ${person(summary.opener_id, users)}${claimed ? ` · ${esc(TT.assignee.toLowerCase())} ${person(claimed, users)}` : ''}` : ''}</p>
                ${actions}
                <div class="dc bp-convo" data-tk-convo>${view.data ? conversation(view.data.messages) : `<p class="bp-empty">${esc(TT.loadingConvo)}</p>`}</div>
                ${composer}
            </section>`;
        },

        render() {
            const { single, lists } = P.moduleFields('tickets');

            if (this.view) return `${P.moduleHead('tickets')}${this.ticketView()}`;

            return `${P.moduleHead('tickets')}
                <section class="bp-settings">${single}</section>
                ${lists}
                ${this.panelBar()}
                <section><h2 class="bp-h">${esc(TT.list)}</h2>${this.list()}</section>`;
        },

        async load() {
            this.loading = true;
            const response = await api(`tickets.php?guild=${state.guildId}`);
            this.loading = false;

            this.data = response.ok
                ? { available: response.available !== false, stats: response.stats || {}, tickets: response.tickets || [], users: response.users || {}, panel: response.panel || null }
                : { available: true, stats: { open: 0, closed_30d: 0, rated_30d: 0, rating_30d: null, minutes_30d: null }, tickets: [], users: {}, panel: null };

            if (state.section === 'tickets') P.renderSection();
        },

        async openTicket(threadId) {
            this.view = { thread_id: threadId, data: null, staff: this.view?.staff || null, draft: '' };
            P.renderSection();

            const [conversationResponse, staffResponse] = await Promise.all([
                api(`tickets.php?guild=${state.guildId}&thread=${threadId}`),
                this.view.staff ? Promise.resolve({ ok: true, staff: this.view.staff }) : api(`tickets.php?guild=${state.guildId}&staff=1`)
            ]);

            if (this.view?.thread_id !== threadId) return;

            if (!conversationResponse.ok) {
                P.toast(failure(conversationResponse), true);
                this.view = null;
                return P.renderSection();
            }

            this.view.data = conversationResponse;
            this.view.staff = staffResponse.ok ? staffResponse.staff : [];
            P.renderSection();

            // La conversazione si legge dal fondo, come su Discord.
            const box = document.querySelector('[data-tk-convo]');
            if (box) box.scrollTop = box.scrollHeight;
        },

        mount() {
            if (this.guild !== state.guildId) {
                this.guild = state.guildId;
                this.reset();
            }

            if (this.data === null && !this.loading) this.load();
        },

        onChange(target) {
            if (target.matches('[data-tk-text]') && this.view) {
                this.view.draft = target.value;
                return true;
            }

            if (target.matches('[data-tk-assign]') && target.value) {
                this.assign(target.value, target);
                return true;
            }

            return false;
        },

        async assign(assignee, control) {
            const response = await act(control, 'tickets.php', { action: 'assign', thread: this.view.thread_id, assignee });
            if (!response.ok) return;

            P.toast(TT.assignedOk);
            this.data = null;
            await this.load();
            return this.openTicket(this.view.thread_id);
        },

        async onClick(target) {
            if (target.closest('[data-tk-tab]')) {
                this.tab = target.closest('[data-tk-tab]').dataset.tkTab;
                return P.renderSection();
            }

            if (target.closest('[data-tk-view]')) return this.openTicket(target.closest('[data-tk-view]').dataset.tkView);

            if (target.closest('[data-tk-back]')) {
                this.view = null;
                return P.renderSection();
            }

            const publish = target.closest('[data-tk-publish]');
            if (publish && !publish.disabled) {
                const response = await act(publish, 'tickets.php', { action: 'publish' });
                if (!response.ok) return;

                P.toast(response.updated ? C.updated : C.published);
                this.data = null;
                return this.load();
            }

            const button = target.closest('[data-tk-act]');
            if (!button || button.disabled || !this.view) return;

            const what = button.dataset.tkAct;
            const me = state.session?.discord_id || '';

            if (what === 'take' || what === 'release') return this.assign(what === 'take' ? me : '', button);

            if (what === 'close' && !window.confirm(TT.closeAsk)) return;

            if (what === 'reply') {
                const text = (this.view.draft || '').trim();
                if (!text) return;

                const response = await act(button, 'tickets.php', { action: 'reply', thread: this.view.thread_id, text });
                if (!response.ok) return;

                P.toast(TT.replied);
                this.view.draft = '';
                return this.openTicket(this.view.thread_id);
            }

            const response = await act(button, 'tickets.php', { action: what, thread: this.view.thread_id });
            if (!response.ok) return;

            P.toast(what === 'close' ? TT.closedOk : TT.reopenedOk);
            this.data = null;
            await this.load();
            return this.openTicket(this.view.thread_id);
        }
    };

    /* ── Giveaway ─────────────────────────────────────────── */

    const TG = {
        it: {
            launch: 'Nuovo giveaway',
            launchLead: 'Poppy pubblica l\'annuncio nel canale che scegli, raccoglie le partecipazioni ed estrae da solo alla scadenza. Si può fare anche da Discord con /giveaway start.',
            prize: 'Premio',
            prizeHint: 'Quello che si vince, detto chiaro: «Nitro per un mese», «Ruolo VIP».',
            description: 'Descrizione',
            channel: 'Canale',
            duration: 'Durata',
            custom: 'Altro…',
            hours: 'Ore',
            winners: 'Vincitori',
            requiredRole: 'Solo per chi ha il ruolo',
            bonusRole: 'Chi ha questo ruolo vale di più',
            bonusEntries: 'Partecipazioni in più',
            repeats: 'Riparte da solo ogni volta che finisce',
            sitePrizes: 'Premi del sito',
            sitePrizesHint: 'Godos, personaggi, Premium: arrivano da soli nella posta del sito di chi vince. Con un premio del sito il giveaway non si ripete.',
            addPrize: 'Aggiungi premio del sito',
            value: 'Valore',
            valueHint: 'Quantità di Godos o Shards, nome del personaggio, ID del badge, 1 per il Premium.',
            quantity: 'Copie',
            start: 'Lancia il giveaway',
            started: code => 'Giveaway ' + code + ' lanciato.',
            cancelForm: 'Lascia stare',
            list: 'In corso e appena finiti',
            notInstalled: 'I giveaway dei server non sono ancora attivi sul sito: manca la migrazione della fase 2.',
            none: 'Nessun giveaway in corso.',
            code: 'N°', entries: 'Partecipanti', ends: 'Termina', winnersCol: 'Vincitori',
            ended: 'finito', repeatsTag: 'si ripete', site: 'premi del sito',
            end: 'Chiudi ora',
            endAsk: 'Chiudere subito questo giveaway ed estrarre i vincitori?',
            cancel: 'Annulla',
            cancelAsk: 'Annullare questo giveaway? Nessuno vince.',
            reroll: 'Estrai di nuovo',
            rerollAsk: 'Estrarre un altro vincitore?',
            done: {
                cancelled: 'Annullato.',
                drawn: n => n ? 'Estratto: ' + n + (n === 1 ? ' vincitore.' : ' vincitori.') : 'Nessuna partecipazione valida, nessun vincitore.',
                not_drawn: { already_drawn: 'Già estratto: usa «Estrai di nuovo».', cancelled: 'Era stato annullato.', still_running: 'È ancora in corso.' },
                not_cancelled: 'Era già finito.',
            },
            types: { points: 'Godos', godoshards: 'Godo Shards', character: 'Personaggio', badge: 'Badge', premium: 'Premium' },
            errors: {
                title: { required: 'Scrivi cosa si vince.', too_long: 'Al massimo 200 caratteri.' },
                description: { too_long: 'Al massimo 1000 caratteri.' },
                channel_id: { required: 'Scegli dove pubblicarlo.', channel: 'Scegli un canale di questo server.', 'bot_cannot:send': 'Poppy non può scrivere in questo canale.' },
                duration_minutes: { range: 'Fra un minuto e 30 giorni.' },
                winners: { range: 'Da 1 a 20.' },
                bonus_entries: { range: 'Da 1 a 10.' },
                required_role_id: { role: 'Scegli un ruolo di questo server.' },
                bonus_role_id: { role: 'Scegli un ruolo di questo server.' },
                rewards: { reward: 'Controlla i premi del sito.', home_only: 'Solo nel server di Cripsum.' },
                reward: 'Controlla questo premio.',
            },
            presets: [[10, '10 minuti'], [60, '1 ora'], [360, '6 ore'], [720, '12 ore'], [1440, '1 giorno'], [4320, '3 giorni'], [10080, '1 settimana']],
        },
        en: {
            launch: 'New giveaway',
            launchLead: 'Poppy posts the announcement in the channel you pick, collects the entries and draws on its own when time is up. You can also do it from Discord with /giveaway start.',
            prize: 'Prize',
            prizeHint: 'What people win, said plainly: "Nitro for a month", "VIP role".',
            description: 'Description',
            channel: 'Channel',
            duration: 'Duration',
            custom: 'Other…',
            hours: 'Hours',
            winners: 'Winners',
            requiredRole: 'Only for members with the role',
            bonusRole: 'Members with this role count more',
            bonusEntries: 'Extra entries',
            repeats: 'Starts again on its own every time it ends',
            sitePrizes: 'Website prizes',
            sitePrizesHint: 'Godos, characters, Premium: they land on their own in the website inbox of whoever wins. With a website prize the giveaway does not repeat.',
            addPrize: 'Add a website prize',
            value: 'Value',
            valueHint: 'Amount of Godos or Shards, character name, badge ID, 1 for Premium.',
            quantity: 'Copies',
            start: 'Start the giveaway',
            started: code => 'Giveaway ' + code + ' started.',
            cancelForm: 'Never mind',
            list: 'Running and just ended',
            notInstalled: 'Server giveaways are not active on the website yet: the phase 2 migration is missing.',
            none: 'No giveaway is running.',
            code: 'No.', entries: 'Entries', ends: 'Ends', winnersCol: 'Winners',
            ended: 'ended', repeatsTag: 'repeats', site: 'website prizes',
            end: 'Close now',
            endAsk: 'Close this giveaway now and draw the winners?',
            cancel: 'Cancel',
            cancelAsk: 'Cancel this giveaway? Nobody wins.',
            reroll: 'Draw again',
            rerollAsk: 'Draw another winner?',
            done: {
                cancelled: 'Cancelled.',
                drawn: n => n ? 'Drawn: ' + n + (n === 1 ? ' winner.' : ' winners.') : 'No valid entries, so no winner.',
                not_drawn: { already_drawn: 'Already drawn: use "Draw again".', cancelled: 'It had been cancelled.', still_running: 'It is still running.' },
                not_cancelled: 'It had already ended.',
            },
            types: { points: 'Godos', godoshards: 'Godo Shards', character: 'Character', badge: 'Badge', premium: 'Premium' },
            errors: {
                title: { required: 'Write what people win.', too_long: 'At most 200 characters.' },
                description: { too_long: 'At most 1000 characters.' },
                channel_id: { required: 'Pick where to post it.', channel: 'Pick a channel of this server.', 'bot_cannot:send': 'Poppy can\'t write in this channel.' },
                duration_minutes: { range: 'Between one minute and 30 days.' },
                winners: { range: 'From 1 to 20.' },
                bonus_entries: { range: 'From 1 to 10.' },
                required_role_id: { role: 'Pick a role of this server.' },
                bonus_role_id: { role: 'Pick a role of this server.' },
                rewards: { reward: 'Check the website prizes.', home_only: 'Only in the Cripsum server.' },
                reward: 'Check this prize.',
            },
            presets: [[10, '10 minutes'], [60, '1 hour'], [360, '6 hours'], [720, '12 hours'], [1440, '1 day'], [4320, '3 days'], [10080, '1 week']],
        }
    }[LANG];

    function emptyGiveaway() {
        return {
            title: '', description: '', channel_id: '', preset: 1440, hours: 24, winners: 1,
            required_role_id: '', bonus_role_id: '', bonus_entries: 1, repeats: false, rewards: []
        };
    }

    sections.giveaway = {
        list: null,
        form: null,
        errors: {},

        formView() {
            const f = this.form;
            const err = key => {
                const code = this.errors[key];
                if (!code) return '';

                const text = key.startsWith('rewards.') ? TG.errors.reward : (TG.errors[key]?.[code] || P.ERRORS[code] || code);
                return `<span class="bp-error" data-gw-error="${esc(key)}">${esc(text)}</span>`;
            };
            const invalid = key => (this.errors[key] ? ' is-invalid' : '');
            const home = state.data.guild.is_home;

            const rewards = f.rewards.map((reward, index) => `<div class="bp-item">
                    <div class="nx-field"><span class="nx-field__label">${esc(TG.prize)}</span>
                        <select class="nx-input nx-select" data-gw-reward="${index}" data-k="type">${Object.entries(TG.types).map(([value, label]) => `<option value="${value}"${reward.type === value ? ' selected' : ''}>${esc(label)}</option>`).join('')}</select></div>
                    <div class="nx-field"><span class="nx-field__label">${esc(TG.value)}</span>
                        <input class="nx-input${invalid('rewards.' + index)}" maxlength="190" value="${esc(reward.value)}" data-gw-reward="${index}" data-k="value">${err('rewards.' + index)}</div>
                    <div class="nx-field"><span class="nx-field__label">${esc(TG.quantity)}</span>
                        <input class="nx-input" type="number" min="1" max="999" value="${esc(reward.quantity)}" data-gw-reward="${index}" data-k="quantity"></div>
                    <button class="nx-icon-btn" type="button" data-gw-reward-remove="${index}" aria-label="${esc(S.remove)}"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
                </div>`).join('');

            return `<section class="bp-item--block">
                <div class="bp-form">
                    <div class="nx-field"><span class="nx-field__label">${esc(TG.prize)}</span>
                        <input class="nx-input${invalid('title')}" maxlength="200" value="${esc(f.title)}" data-gw="title">
                        <span class="nx-field__hint">${esc(TG.prizeHint)}</span>${err('title')}</div>
                    <div class="nx-field"><span class="nx-field__label">${esc(TG.description)}</span>
                        <textarea class="nx-input${invalid('description')}" rows="3" maxlength="1000" data-gw="description">${esc(f.description)}</textarea>${err('description')}</div>
                    <div class="bp-form__pair">
                        <div class="nx-field"><span class="nx-field__label">${esc(TG.channel)}</span>
                            <select class="nx-input nx-select${invalid('channel_id')}" data-gw="channel_id">${P.channelOptions({ need: ['send', 'embed'] }, f.channel_id)}</select>${err('channel_id')}</div>
                        <div class="nx-field"><span class="nx-field__label">${esc(TG.winners)}</span>
                            <input class="nx-input${invalid('winners')}" type="number" min="1" max="20" value="${esc(f.winners)}" data-gw="winners">${err('winners')}</div>
                    </div>
                    <div class="bp-form__pair">
                        <div class="nx-field"><span class="nx-field__label">${esc(TG.duration)}</span>
                            <select class="nx-input nx-select" data-gw="preset">${TG.presets.map(([minutes, label]) => `<option value="${minutes}"${Number(f.preset) === minutes ? ' selected' : ''}>${esc(label)}</option>`).join('')}<option value="custom"${f.preset === 'custom' ? ' selected' : ''}>${esc(TG.custom)}</option></select></div>
                        ${f.preset === 'custom' ? `<div class="nx-field"><span class="nx-field__label">${esc(TG.hours)}</span>
                            <input class="nx-input${invalid('duration_minutes')}" type="number" min="1" max="720" value="${esc(f.hours)}" data-gw="hours">${err('duration_minutes')}</div>` : '<div></div>'}
                    </div>
                    <div class="nx-field"><span class="nx-field__label">${esc(TG.requiredRole)}</span>
                        <select class="nx-input nx-select${invalid('required_role_id')}" data-gw="required_role_id">${roleOptions(f.required_role_id)}</select>${err('required_role_id')}</div>
                    <div class="bp-form__pair">
                        <div class="nx-field"><span class="nx-field__label">${esc(TG.bonusRole)}</span>
                            <select class="nx-input nx-select${invalid('bonus_role_id')}" data-gw="bonus_role_id">${roleOptions(f.bonus_role_id)}</select>${err('bonus_role_id')}</div>
                        <div class="nx-field"><span class="nx-field__label">${esc(TG.bonusEntries)}</span>
                            <input class="nx-input${invalid('bonus_entries')}" type="number" min="1" max="10" value="${esc(f.bonus_entries)}" data-gw="bonus_entries"${f.bonus_role_id ? '' : ' disabled'}>${err('bonus_entries')}</div>
                    </div>
                    <div class="bp-setting bp-setting--switch" style="border:0;padding:0">
                        <div><p class="bp-setting__name">${esc(TG.repeats)}</p></div>
                        <label class="nx-switch"><input type="checkbox" data-gw="repeats"${f.repeats ? ' checked' : ''}${f.rewards.length ? ' disabled' : ''} aria-label="${esc(TG.repeats)}"><i></i></label>
                    </div>
                    ${home ? `<div class="nx-field">
                        <span class="nx-field__label">${esc(TG.sitePrizes)}</span>
                        ${rewards}
                        ${err('rewards')}
                        <span class="bp-form__foot">
                            <button class="bp-chip bp-chip--add" type="button" data-gw-reward-add${f.rewards.length >= 3 ? ' disabled' : ''}><i class="fa-solid fa-plus" aria-hidden="true"></i>${esc(TG.addPrize)}</button>
                            <span class="nx-field__hint">${esc(f.rewards.length ? TG.valueHint : TG.sitePrizesHint)}</span>
                        </span>
                    </div>` : ''}
                    <div class="bp-bar">
                        <button class="nx-btn nx-btn--soft nx-btn--sm" type="button" data-gw-start><i class="fa-solid fa-gift" aria-hidden="true"></i>${esc(TG.start)}</button>
                        <button class="nx-btn nx-btn--outline nx-btn--sm" type="button" data-gw-close-form>${esc(TG.cancelForm)}</button>
                    </div>
                </div>
            </section>`;
        },

        table() {
            // Con il modulo spento il bot non chiude ne' annulla: i bottoni lo dicono subito.
            const off = !state.data.modules.giveaway.enabled ? ' disabled' : '';
            if (this.list === null) return `<p class="bp-empty">${esc(S.loading)}</p>`;
            if (this.available === false) return `<p class="bp-empty">${esc(TG.notInstalled)}</p>`;
            if (!this.list.length) return `<p class="bp-empty">${esc(TG.none)}</p>`;

            const rows = this.list.map(g => {
                const tags = (g.site ? ` <span class="nx-tag">${esc(TG.site)}</span>` : '')
                    + (g.repeats ? ` <span class="nx-tag">${esc(TG.repeatsTag)}</span>` : '')
                    + (g.running ? '' : ` <span class="nx-tag">${esc(TG.ended)}</span>`);

                const buttons = g.running
                    ? `<button class="nx-btn nx-btn--outline nx-btn--sm" type="button" data-gw-act="end" data-code="${esc(g.code)}"${off}>${esc(TG.end)}</button>
                       <button class="nx-btn nx-btn--danger nx-btn--sm" type="button" data-gw-act="cancel" data-code="${esc(g.code)}"${off}>${esc(TG.cancel)}</button>`
                    : (g.site ? '' : `<button class="nx-btn nx-btn--outline nx-btn--sm" type="button" data-gw-act="reroll" data-code="${esc(g.code)}"${off}>${esc(TG.reroll)}</button>`);

                return `<tr>
                    <td class="bp-num">${g.url ? `<a href="${esc(g.url)}" target="_blank" rel="noopener">${esc(g.code)}</a>` : esc(g.code)}</td>
                    <td>${esc(g.title)}${tags}${g.prizes?.length ? `<br><span class="bp-dim">${esc(g.prizes.join(', '))}</span>` : ''}</td>
                    <td>${esc(g.entries)}</td>
                    <td>${g.winners.length ? g.winners.map(id => person(id, this.users)).join(' ') : esc(g.winners_count)}</td>
                    <td class="bp-dim">${esc(P.formatWhen(g.ends))}</td>
                    <td><span class="bp-bar" style="margin:0;flex-wrap:nowrap">${buttons}</span></td>
                </tr>`;
            }).join('');

            return `<div class="bp-table-wrap"><table class="nx-table">
                <thead><tr><th>${esc(TG.code)}</th><th>${esc(TG.prize)}</th><th>${esc(TG.entries)}</th><th>${esc(TG.winnersCol)}</th><th>${esc(TG.ends)}</th><th></th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`;
        },

        render() {
            const { single, lists } = P.moduleFields('giveaway');
            const enabled = state.data.modules.giveaway.enabled;

            return `${P.moduleHead('giveaway')}
                <section>
                    <h2 class="bp-h">${esc(TG.launch)}</h2>
                    <p class="bp-lead" style="margin-bottom:1rem">${esc(TG.launchLead)}</p>
                    ${this.form ? this.formView() : `<div class="bp-bar"><button class="nx-btn nx-btn--soft nx-btn--sm" type="button" data-gw-new${enabled ? '' : ' disabled'}><i class="fa-solid fa-plus" aria-hidden="true"></i>${esc(TG.launch)}</button>${enabled ? '' : `<span class="nx-field__hint">${esc(C.failed.module_off)}</span>`}</div>`}
                </section>
                <section><h2 class="bp-h">${esc(TG.list)}</h2>${this.table()}</section>
                <section class="bp-settings">${single}</section>
                ${lists}`;
        },

        async load() {
            this.loading = true;
            const response = await api(`giveaways.php?guild=${state.guildId}`);
            this.loading = false;

            this.list = response.ok ? (response.giveaways || []) : [];
            this.users = response.ok ? (response.users || {}) : {};
            this.available = response.ok ? response.available !== false : true;

            if (state.section === 'giveaway') P.renderSection();
        },

        mount() {
            if (this.guild !== state.guildId) {
                this.guild = state.guildId;
                this.list = null;
                this.form = null;
                this.errors = {};
            }

            if (this.list === null && !this.loading) this.load();
        },

        onChange(target) {
            if (!this.form) return false;

            if (target.matches('[data-gw]')) {
                const key = target.dataset.gw;
                this.form[key] = target.type === 'checkbox' ? target.checked : target.value;

                // Corretto il campo, l'errore se ne va subito, senza ridisegnare
                // (chi sta scrivendo non perde il punto in cui e').
                const errorKey = key === 'hours' ? 'duration_minutes' : key;
                if (this.errors[errorKey]) {
                    delete this.errors[errorKey];
                    target.classList.remove('is-invalid');
                    document.querySelector(`[data-gw-error="${errorKey}"]`)?.remove();
                }

                // Cambiano campi che dipendono da questo: si ridisegna.
                if (['preset', 'bonus_role_id'].includes(key)) P.renderSection();
                return true;
            }

            if (target.matches('[data-gw-reward]')) {
                const index = Number(target.dataset.gwReward);
                this.form.rewards[index][target.dataset.k] = target.value;

                if (this.errors['rewards.' + index]) {
                    delete this.errors['rewards.' + index];
                    target.classList.remove('is-invalid');
                    document.querySelector(`[data-gw-error="rewards.${index}"]`)?.remove();
                }
                return true;
            }

            return false;
        },

        async start(button) {
            const f = this.form;
            const minutes = f.preset === 'custom' ? Math.round(Number(f.hours) * 60) : Number(f.preset);

            const response = await act(button, 'giveaways.php', {
                action: 'start',
                title: f.title,
                description: f.description,
                channel_id: f.channel_id,
                duration_minutes: minutes,
                winners: Number(f.winners),
                required_role_id: f.required_role_id,
                bonus_role_id: f.bonus_role_id,
                bonus_entries: Number(f.bonus_entries),
                repeats: f.repeats && !f.rewards.length,
                rewards: f.rewards.map(reward => ({ type: reward.type, value: reward.value, quantity: Number(reward.quantity) || 1 }))
            });

            if (!response.ok) {
                this.errors = response.errors || {};
                return P.renderSection();
            }

            P.toast(TG.started(response.code));
            this.form = null;
            this.errors = {};
            this.list = null;
            return this.load();
        },

        async onClick(target) {
            if (target.closest('[data-gw-new]')) {
                this.form = emptyGiveaway();
                this.errors = {};
                return P.renderSection();
            }

            if (target.closest('[data-gw-close-form]')) {
                this.form = null;
                return P.renderSection();
            }

            if (target.closest('[data-gw-reward-add]') && this.form && this.form.rewards.length < 3) {
                this.form.rewards.push({ type: 'points', value: '', quantity: 1 });
                this.form.repeats = false;
                return P.renderSection();
            }

            if (target.closest('[data-gw-reward-remove]')) {
                this.form.rewards.splice(Number(target.closest('[data-gw-reward-remove]').dataset.gwRewardRemove), 1);
                return P.renderSection();
            }

            const start = target.closest('[data-gw-start]');
            if (start) return this.start(start);

            const button = target.closest('[data-gw-act]');
            if (!button || button.disabled) return;

            const action = button.dataset.gwAct;
            if (!window.confirm({ end: TG.endAsk, cancel: TG.cancelAsk, reroll: TG.rerollAsk }[action])) return;

            const response = await act(button, 'giveaways.php', { action, code: button.dataset.code });
            if (!response.ok) return;

            const done = TG.done;
            P.toast(response.outcome === 'drawn'
                ? done.drawn(response.winners.length)
                : response.outcome === 'not_drawn'
                    ? (done.not_drawn[response.reason] || done.not_cancelled)
                    : (done[response.outcome] || done.cancelled));

            this.list = null;
            return this.load();
        }
    };

    /* ── Ruoli a scelta ───────────────────────────────────── */

    const TR = {
        it: {
            publishTitle: 'Dove escono',
            publishLead: 'Ogni menu è un messaggio a sé. Scegli il canale e premi «Pubblica»; dopo una modifica, salva e premi «Aggiorna»: Poppy riscrive quello già pubblicato.',
            empty: 'Nessun menu salvato ancora: creane uno qui sopra e salva.',
            untitled: 'Menu senza titolo',
            buttons: n => n + (n === 1 ? ' bottone' : ' bottoni'),
        },
        en: {
            publishTitle: 'Where they go',
            publishLead: 'Each menu is a message of its own. Pick the channel and press "Post"; after a change, save and press "Update": Poppy rewrites the one already posted.',
            empty: 'No saved menus yet: create one above and save.',
            untitled: 'Untitled menu',
            buttons: n => n + (n === 1 ? ' button' : ' buttons'),
        }
    }[LANG];

    sections.roles = {
        posts: null,
        channels: {},

        render() {
            const { single, lists } = P.moduleFields('roles');
            const menus = Array.isArray(savedConfig('roles').menus) ? savedConfig('roles').menus : [];
            const ready = moduleReady('roles');

            const rows = menus.map(menu => {
                const post = this.posts?.[menu.id];
                const channel = this.channels[menu.id] ?? post?.channel_id ?? '';
                const same = post && post.channel_id === channel;

                return `<li class="bp-row bp-row--plain">
                    <div>
                        <p class="bp-row__title">${esc(menu.title || TR.untitled)}</p>
                        <p class="bp-row__meta">${esc(TR.buttons((menu.entries || []).length))} · ${post ? `<a href="${esc(post.url)}" target="_blank" rel="noopener">${esc(C.openDiscord)}</a>` : esc(C.never)}</p>
                    </div>
                    <div class="bp-row__side bp-bar" style="margin:0">
                        <select class="nx-input nx-select" style="width:13rem" data-sr-channel="${esc(menu.id)}" aria-label="${esc(C.publishIn)}">${P.channelOptions({ need: ['send', 'embed'] }, channel)}</select>
                        <button class="nx-btn nx-btn--soft nx-btn--sm" type="button" data-sr-publish="${esc(menu.id)}"${ready ? '' : ' disabled'}>${esc(same ? C.update : C.publish)}</button>
                    </div>
                </li>`;
            }).join('');

            return `${P.moduleHead('roles')}
                <section class="bp-settings">${single}</section>
                ${lists}
                <section>
                    <h2 class="bp-h">${esc(TR.publishTitle)}</h2>
                    <p class="bp-lead" style="margin-bottom:1rem">${esc(TR.publishLead)}</p>
                    ${menus.length ? `<ul class="bp-rows">${rows}</ul>` : `<p class="bp-empty">${esc(TR.empty)}</p>`}
                    ${menus.length && !ready ? `<p class="nx-field__hint" style="margin-top:.75rem">${esc(C.saveFirst)}</p>` : ''}
                </section>`;
        },

        async load() {
            this.loading = true;
            const response = await api(`roles.php?guild=${state.guildId}`);
            this.loading = false;

            this.posts = response.ok ? (response.posts || {}) : {};
            if (state.section === 'roles') P.renderSection();
        },

        mount() {
            if (this.guild !== state.guildId) {
                this.guild = state.guildId;
                this.posts = null;
                this.channels = {};
            }

            if (this.posts === null && !this.loading) this.load();
        },

        onChange(target) {
            if (target.matches('[data-sr-channel]')) {
                this.channels[target.dataset.srChannel] = target.value;
                P.renderSection();
                return true;
            }

            return false;
        },

        async onClick(target) {
            const button = target.closest('[data-sr-publish]');
            if (!button || button.disabled) return;

            const menu = button.dataset.srPublish;
            const channel = this.channels[menu] ?? this.posts?.[menu]?.channel_id ?? '';

            const response = await act(button, 'roles.php', { menu, channel_id: channel });
            if (!response.ok) return;

            P.toast(response.updated ? C.updated : C.published);
            this.posts = null;
            return this.load();
        }
    };

    /* ── Candidature ──────────────────────────────────────── */

    const TA = {
        it: {
            panel: 'Pannello su Discord',
            panelLead: 'Il messaggio con un bottone per ogni modulo. Chi lo preme risponde alle domande; le risposte arrivano nel canale scelto e qui sotto.',
            list: 'Candidature',
            pending: 'In attesa',
            decided: 'Decise',
            notInstalled: 'Le candidature non sono ancora attive sul sito: manca la migrazione della fase 3.',
            nonePending: 'Nessuna candidatura in attesa.',
            noneDecided: 'Nessuna candidatura decisa negli ultimi 30 giorni.',
            accept: 'Accetta',
            reject: 'Rifiuta',
            reason: 'Perché? Lo leggerà chi ha fatto domanda.',
            confirmReject: 'Rifiuta',
            accepted: 'accettata',
            rejected: 'rifiutata',
            by: 'da',
            doneAccept: 'Accettata.',
            doneReject: 'Rifiutata.',
            dmClosed: ' Ha i messaggi privati chiusi: non l\'ha saputo da Poppy.',
            show: 'Leggi',
            hide: 'Chiudi',
        },
        en: {
            panel: 'Panel on Discord',
            panelLead: 'The message with a button for each form. Whoever presses one answers the questions; the answers land in the chosen channel and down here.',
            list: 'Applications',
            pending: 'Waiting',
            decided: 'Decided',
            notInstalled: 'Applications are not active on the website yet: the phase 3 migration is missing.',
            nonePending: 'No applications waiting.',
            noneDecided: 'No applications decided in the last 30 days.',
            accept: 'Accept',
            reject: 'Reject',
            reason: 'Why? The applicant will read this.',
            confirmReject: 'Reject',
            accepted: 'accepted',
            rejected: 'rejected',
            by: 'by',
            doneAccept: 'Accepted.',
            doneReject: 'Rejected.',
            dmClosed: ' Their DMs are closed: Poppy could not tell them.',
            show: 'Read',
            hide: 'Close',
        }
    }[LANG];

    sections.applications = {
        data: null,
        tab: 'pending',
        expanded: null,
        rejecting: null,
        channel: null,

        item(entry) {
            const users = this.data.users;
            const open = this.expanded === entry.id;
            const status = entry.status === 'pending'
                ? ''
                : ` · ${esc(entry.status === 'accepted' ? TA.accepted : TA.rejected)} ${esc(TA.by)} ${person(entry.reviewer_id, users)}`;

            const answers = open ? `<div class="bp-answers">${entry.answers.map(answer => `<p><b>${esc(answer.question)}</b><br>${md(answer.answer)}</p>`).join('')}
                    ${entry.reason ? `<p class="bp-dim">${md(entry.reason)}</p>` : ''}
                    ${entry.status === 'pending' ? (this.rejecting === entry.id
                        ? `<div class="nx-field"><textarea class="nx-input" rows="2" maxlength="500" data-ap-reason placeholder="${esc(TA.reason)}" aria-label="${esc(TA.reason)}"></textarea></div>
                           <div class="bp-bar"><button class="nx-btn nx-btn--danger nx-btn--sm" type="button" data-ap-decide="rejected" data-id="${entry.id}">${esc(TA.confirmReject)}</button></div>`
                        : `<div class="bp-bar">
                            <button class="nx-btn nx-btn--soft nx-btn--sm" type="button" data-ap-decide="accepted" data-id="${entry.id}">${esc(TA.accept)}</button>
                            <button class="nx-btn nx-btn--outline nx-btn--sm" type="button" data-ap-reject="${entry.id}">${esc(TA.reject)}</button>
                        </div>`) : ''}
                </div>` : '';

            return `<li class="bp-app">
                <div class="bp-app__head">
                    <div>
                        <p class="bp-row__title">${esc(entry.form)} · ${person(entry.user_id, users)}</p>
                        <p class="bp-row__meta">${esc(P.formatWhen(entry.created))}${status}</p>
                    </div>
                    <button class="nx-btn nx-btn--outline nx-btn--sm bp-always" type="button" data-ap-toggle="${entry.id}">${esc(open ? TA.hide : TA.show)}</button>
                </div>
                ${answers}
            </li>`;
        },

        render() {
            const { single, lists } = P.moduleFields('applications');
            const ready = moduleReady('applications');
            const panel = this.data?.panel;
            const channel = this.channel ?? panel?.channel_id ?? '';

            let body;
            if (this.data === null) {
                body = `<p class="bp-empty">${esc(S.loading)}</p>`;
            } else if (!this.data.available) {
                body = `<p class="bp-empty">${esc(TA.notInstalled)}</p>`;
            } else {
                const pending = this.tab === 'pending';
                const list = this.data.applications.filter(entry => (entry.status === 'pending') === pending);
                const count = this.data.applications.filter(entry => entry.status === 'pending').length;

                body = `<div class="bp-bar"><div class="nx-tabs" role="tablist">
                        <button class="nx-tab bp-always${pending ? ' is-active' : ''}" role="tab" aria-selected="${pending}" data-ap-tab="pending">${esc(TA.pending)} · ${count}</button>
                        <button class="nx-tab bp-always${pending ? '' : ' is-active'}" role="tab" aria-selected="${!pending}" data-ap-tab="decided">${esc(TA.decided)}</button>
                    </div></div>
                    ${list.length ? `<ul class="bp-rows">${list.map(entry => this.item(entry)).join('')}</ul>` : `<p class="bp-empty">${esc(pending ? TA.nonePending : TA.noneDecided)}</p>`}`;
            }

            return `${P.moduleHead('applications')}
                <section class="bp-settings">${single}</section>
                ${lists}
                <section>
                    <h2 class="bp-h">${esc(TA.panel)}</h2>
                    <p class="bp-lead" style="margin-bottom:1rem">${esc(TA.panelLead)}</p>
                    <div class="bp-bar">
                        <select class="nx-input nx-select" style="width:14rem" data-ap-channel aria-label="${esc(C.publishIn)}">${P.channelOptions({ need: ['send', 'embed'] }, channel)}</select>
                        <button class="nx-btn nx-btn--soft nx-btn--sm" type="button" data-ap-publish${ready ? '' : ' disabled'}>${esc(panel && panel.channel_id === channel ? C.update : C.publish)}</button>
                        ${panel?.url ? `<a class="nx-btn nx-btn--outline nx-btn--sm bp-always" href="${esc(panel.url)}" target="_blank" rel="noopener">${esc(C.openDiscord)}</a>` : ''}
                        ${ready ? '' : `<span class="nx-field__hint">${esc(C.saveFirst)}</span>`}
                    </div>
                </section>
                <section><h2 class="bp-h">${esc(TA.list)}</h2>${body}</section>`;
        },

        async load() {
            this.loading = true;
            const response = await api(`applications.php?guild=${state.guildId}`);
            this.loading = false;

            this.data = response.ok
                ? { available: response.available !== false, applications: response.applications || [], users: response.users || {}, panel: response.panel || null }
                : { available: true, applications: [], users: {}, panel: null };

            if (state.section === 'applications') P.renderSection();
        },

        mount() {
            if (this.guild !== state.guildId) {
                this.guild = state.guildId;
                this.data = null;
                this.expanded = null;
                this.rejecting = null;
                this.channel = null;
            }

            if (this.data === null && !this.loading) this.load();
        },

        onChange(target) {
            if (target.matches('[data-ap-channel]')) {
                this.channel = target.value;
                P.renderSection();
                return true;
            }

            return target.matches('[data-ap-reason]');
        },

        async onClick(target) {
            const tab = target.closest('[data-ap-tab]');
            if (tab) {
                this.tab = tab.dataset.apTab;
                return P.renderSection();
            }

            const toggle = target.closest('[data-ap-toggle]');
            if (toggle) {
                const id = Number(toggle.dataset.apToggle);
                this.expanded = this.expanded === id ? null : id;
                this.rejecting = null;
                return P.renderSection();
            }

            const reject = target.closest('[data-ap-reject]');
            if (reject) {
                this.rejecting = Number(reject.dataset.apReject);
                P.renderSection();
                return document.querySelector('[data-ap-reason]')?.focus();
            }

            const publish = target.closest('[data-ap-publish]');
            if (publish && !publish.disabled) {
                const channel = this.channel ?? this.data?.panel?.channel_id ?? '';
                const response = await act(publish, 'applications.php', { action: 'publish', channel_id: channel });
                if (!response.ok) return;

                P.toast(response.updated ? C.updated : C.published);
                this.data = null;
                return this.load();
            }

            const decide = target.closest('[data-ap-decide]');
            if (!decide || decide.disabled) return;

            const decision = decide.dataset.apDecide;
            const reason = decision === 'rejected' ? (document.querySelector('[data-ap-reason]')?.value || '') : '';

            const response = await act(decide, 'applications.php', { action: 'decide', id: Number(decide.dataset.id), decision, reason });
            if (!response.ok) return;

            P.toast((decision === 'accepted' ? TA.doneAccept : TA.doneReject) + (response.dm_sent ? '' : TA.dmClosed));
            this.expanded = null;
            this.rejecting = null;
            this.data = null;
            return this.load();
        }
    };
})();
