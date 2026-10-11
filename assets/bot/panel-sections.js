/*
 * Le sezioni del pannello che hanno una forma loro. Si appoggiano a
 * BotPanel (panel.js) per tutto quello che e' comune: campi, bozze,
 * salvataggio, avvisi.
 */
(() => {
    'use strict';

    const P = window.BotPanel;
    if (!P) return;

    const { LANG, S, state, sections, api, esc, tr } = P;

    const T = {
        it: {
            lastActions: 'Ultime azioni',
            noActions: 'Nessuna azione di moderazione ancora. Compaiono qui appena qualcuno usa /mod.',
            n: 'N°', action: 'Azione', member: 'Membro', reason: 'Motivo', moderator: 'Moderatore', when: 'Quando',
            automatic: 'automatico',
            more: 'Più vecchie',
            actions: {
                ban: 'Ban', unban: 'Ban tolto', kick: 'Espulsione', timeout: 'Timeout', untimeout: 'Timeout tolto',
                warn: 'Warn', unwarn: 'Warn annullato', purge: 'Messaggi cancellati', lock: 'Canale chiuso',
                unlock: 'Canale riaperto', slowmode: 'Modalità lenta'
            },
            durations: { s: 's', m: 'min', h: 'h', d: 'g' },
        },
        en: {
            lastActions: 'Latest actions',
            noActions: 'No moderation actions yet. They show up here as soon as someone uses /mod.',
            n: 'No.', action: 'Action', member: 'Member', reason: 'Reason', moderator: 'Moderator', when: 'When',
            automatic: 'automatic',
            more: 'Older',
            actions: {
                ban: 'Ban', unban: 'Unban', kick: 'Kick', timeout: 'Timeout', untimeout: 'Timeout removed',
                warn: 'Warning', unwarn: 'Warning revoked', purge: 'Messages purged', lock: 'Channel locked',
                unlock: 'Channel unlocked', slowmode: 'Slowmode'
            },
            durations: { s: 's', m: 'min', h: 'h', d: 'd' },
        }
    }[LANG];

    function duration(seconds) {
        if (!seconds) return '';
        if (seconds >= 86400) return ` · ${Math.round(seconds / 86400)}${T.durations.d}`;
        if (seconds >= 3600) return ` · ${Math.round(seconds / 3600)}${T.durations.h}`;
        if (seconds >= 60) return ` · ${Math.round(seconds / 60)}${T.durations.m}`;
        return ` · ${seconds}${T.durations.s}`;
    }

    function person(id, users) {
        if (!id) return '<span class="bp-dim">—</span>';

        const user = users[id];
        const avatar = user?.avatar ? `<img src="${esc(user.avatar)}" alt="">` : '';

        return `<span class="bp-who">${avatar}${esc(user?.name || id)}</span>`;
    }

    /* ── Moderazione ──────────────────────────────────────────
       Le impostazioni del modulo, con in testa «chi puo' moderare», che
       appartiene allo staff del server ma e' qui che lo si cerca. Sotto, lo
       storico delle azioni. */

    sections.moderation = {
        actions: null,
        users: {},
        page: 0,
        more: false,

        render() {
            const staff = P.moduleFields('general', ['mod_role_ids']);
            const { single, lists } = P.moduleFields('moderation');

            const rows = (this.actions || []).map(action => `<tr>
                <td class="bp-num">#${action.id}</td>
                <td>${esc(T.actions[action.action] || action.action)}${esc(duration(action.duration))}${action.automatic ? ` <span class="nx-tag">${esc(T.automatic)}</span>` : ''}</td>
                <td>${person(action.target_id, this.users)}</td>
                <td class="bp-dim">${esc(action.reason)}</td>
                <td>${person(action.moderator_id, this.users)}</td>
                <td class="bp-dim">${esc(P.formatWhen(action.when))}</td>
            </tr>`).join('');

            return `${P.moduleHead('moderation')}
                <section class="bp-settings">${staff.single}${single}</section>
                ${lists}
                <section>
                    <h2 class="bp-h">${esc(T.lastActions)}</h2>
                    ${this.actions === null ? `<p class="bp-empty">${esc(S.loading)}</p>` : (this.actions.length ? `<div class="bp-table-wrap"><table class="nx-table">
                        <thead><tr><th>${esc(T.n)}</th><th>${esc(T.action)}</th><th>${esc(T.member)}</th><th>${esc(T.reason)}</th><th>${esc(T.moderator)}</th><th>${esc(T.when)}</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table></div>` : `<p class="bp-empty">${esc(T.noActions)}</p>`)}
                    ${this.more ? `<div class="bp-bar"><button class="nx-btn nx-btn--soft nx-btn--sm bp-always" type="button" data-mod-more>${esc(T.more)}</button></div>` : ''}
                </section>`;
        },

        async load() {
            // Ogni ridisegno richiama mount(): senza questo segno una lettura
            // ancora in corso ne farebbe partire un'altra uguale.
            this.loading = true;
            const response = await api(`moderation.php?guild=${state.guildId}&page=${this.page}`);
            this.loading = false;

            if (response.ok) {
                this.actions = (this.page === 0 ? [] : this.actions || []).concat(response.actions);
                this.users = { ...this.users, ...response.users };
                this.more = response.more;
            } else {
                this.actions = this.actions || [];
            }

            if (state.section === 'moderation') P.renderSection();
        },

        mount() {
            if (this.guild !== state.guildId) {
                this.guild = state.guildId;
                this.actions = null;
                this.page = 0;
            }

            if (this.actions === null && !this.loading) this.load();
        },

        onClick(target) {
            if (target.matches('[data-mod-more]')) {
                this.page += 1;
                this.load();
            }
        }
    };

    /* ── Anteprima di Discord ─────────────────────────────────
       Disegna un messaggio come lo mostrera' Discord: abbastanza fedele da
       far capire cosa uscira', senza pretendere di rifare il loro client. */

    const SAMPLE = () => ({
        'user': `<span class="dc-mention">@${esc(state.session?.username || 'tu')}</span>`,
        'mention': `<span class="dc-mention">@${esc(state.session?.username || 'tu')}</span>`,
        'user.name': esc(state.session?.username || 'tu'),
        'user.display': esc(state.session?.username || 'tu'),
        'user.tag': esc(state.session?.username || 'tu'),
        'user.id': '123456789012345678',
        'user.created': LANG === 'it' ? '2 anni fa' : '2 years ago',
        'server': esc(state.data.guild.name),
        'server.id': esc(state.data.guild.id),
        'membercount': String(state.data.guild.member_count),
        'membercount.ordinal': `${state.data.guild.member_count}th`,
        'boostcount': '14',
        'boosttier': '2',
        'site.username': esc(state.session?.username || 'tu'),
        'date': new Date().toLocaleDateString('en-GB'),
        'time': new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }),
        'timestamp': new Date().toLocaleString(LANG === 'it' ? 'it-IT' : 'en-GB', { dateStyle: 'long', timeStyle: 'short' }),
    });

    /** Testo di un messaggio come lo vedra' chi legge: segnaposto e grassetti. */
    function richText(text) {
        const sample = SAMPLE();

        return esc(text)
            .replace(/\{([a-z.]+)\}/gi, (match, key) => sample[key.toLowerCase()] ?? match)
            .replace(/\[([^\]]+)\]\((https?:[^)\s]+)\)/g, '<u>$1</u>')
            .replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>')
            .replace(/(^|[\s(])\*([^*\n]+)\*/g, '$1<i>$2</i>')
            .replace(/`([^`\n]+)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');
    }

    /** Un'immagine dell'anteprima: l'indirizzo scritto, o un riquadro al posto di un segnaposto. */
    function previewImage(value, className) {
        if (!value) return '';
        if (/^https:\/\//i.test(value)) return `<img class="${className}" src="${esc(value)}" alt="" referrerpolicy="no-referrer">`;

        return `<span class="${className} dc-ph" aria-hidden="true"><i class="fa-solid fa-image"></i></span>`;
    }

    function discordMessage({ content, embed, extra = '' }, channelName) {
        const body = embed ? `<div class="dc-embed" style="--embed:${esc(/^#[0-9a-f]{6}$/i.test(embed.color || '') ? embed.color : '#4e5058')}">
                ${embed.author_name ? `<p class="dc-embed__foot" style="grid-column:1;margin:0;color:#f2f3f5;font-weight:600">${richText(embed.author_name)}</p>` : ''}
                ${embed.title ? `<p class="dc-embed__title" style="grid-column:1">${richText(embed.title)}</p>` : ''}
                ${previewImage(embed.thumbnail, 'dc-embed__thumb')}
                ${embed.description ? `<p class="dc-embed__text" style="grid-column:1">${richText(embed.description)}</p>` : ''}
                ${embed.image ? `<div style="grid-column:1/-1">${previewImage(embed.image, 'dc-embed__image')}</div>` : ''}
                ${embed.footer_text || embed.timestamp ? `<p class="dc-embed__foot">${richText(embed.footer_text || '')}${embed.footer_text && embed.timestamp ? ' • ' : ''}${embed.timestamp ? esc(TM.todayAt) : ''}</p>` : ''}
            </div>` : '';

        return `<p class="bp-preview__label"><span>${esc(TM.preview)}</span><span>${channelName ? `# ${esc(channelName)}` : ''}</span></p>
            <div class="dc"><div class="dc-msg">
                <span class="dc-avatar dc-ph" aria-hidden="true"><i class="fa-solid fa-robot"></i></span>
                <div>
                    <span class="dc-name">Poppy</span><span class="dc-app">APP</span><span class="dc-time">${esc(TM.todayAt)}</span>
                    ${content ? `<p style="margin:0">${richText(content)}</p>` : ''}
                    ${body}${extra}
                </div>
            </div></div>`;
    }

    /* ── Messaggi ─────────────────────────────────────────────
       Un editor solo per tutti: quelli che partono da soli (benvenuto,
       addio, boost) e quelli salvati per nome, che si pubblicano a mano e
       si possono riscrivere sul posto. A destra, come uscira' su Discord. */

    const TM = {
        it: {
            preview: 'Come appare su Discord',
            todayAt: 'oggi alle 18:42',
            tabs: { welcome: 'Benvenuto', welcome_dm: 'In privato', goodbye: 'Addio', boost: 'Boost', premium: 'Premium', custom: 'Salvati' },
            leads: {
                welcome: 'Parte ogni volta che qualcuno entra nel server.',
                welcome_dm: 'Arriva in privato a chi entra. Chi ha i messaggi diretti chiusi non lo riceve.',
                goodbye: 'Parte quando qualcuno esce dal server.',
                boost: 'Parte quando qualcuno potenzia il server.',
                premium: 'Parte quando qualcuno compra il Premium sul sito.',
            },
            on: 'Acceso',
            channel: 'Canale',
            channelHint: 'Dove viene pubblicato.',
            content: 'Testo sopra il riquadro',
            title: 'Titolo',
            description: 'Descrizione',
            color: 'Colore',
            thumbnail: 'Immagine piccola',
            image: 'Immagine grande',
            footer: 'Piè di pagina',
            timestamp: 'Mostra data e ora',
            urlHint: 'Un indirizzo https, oppure {user.avatar} o {server.icon}.',
            notYet: 'Questo messaggio non è ancora stato scritto: quello che vedi è un punto di partenza. Parte da solo dopo che lo salvi.',
            test: 'Invia una prova',
            testHint: 'Come se fosse appena successo a te. Prima salva.',
            tested: 'Prova inviata.',
            testedDm: 'Prova inviata in privato.',
            sendFailed: {
                module_off: 'Il modulo Messaggi è spento: accendilo e riprova.',
                channel_not_found: 'Poppy non trova quel canale.',
                dm_closed: 'Hai i messaggi diretti chiusi: Poppy non può scriverti.',
                message_not_found: 'Il messaggio pubblicato non c\'è più: pubblicalo di nuovo.',
                unreachable: 'Poppy non risponde.',
            },
            saved: 'Messaggi salvati',
            savedLead: 'Regole, annunci, qualsiasi messaggio fisso. Li scrivi qui, li pubblichi dove vuoi e li puoi riscrivere senza ripubblicarli.',
            newMessage: 'Nuovo messaggio',
            name: 'Nome',
            nameHint: 'Serve a ritrovarlo: «regole», «annuncio natale».',
            noSaved: 'Nessun messaggio salvato ancora.',
            published: 'Pubblicato',
            notPublished: 'Mai pubblicato',
            publishIn: 'Pubblica in',
            publish: 'Pubblica',
            update: 'Aggiorna quello pubblicato',
            publishedOk: 'Pubblicato.',
            updatedOk: 'Aggiornato sul posto.',
            del: 'Elimina',
            delAsk: 'Eliminare questo messaggio salvato? Quelli già pubblicati restano dove sono.',
            deleted: 'Eliminato.',
            back: 'Tutti i messaggi salvati',
            badName: 'Solo lettere, cifre, spazi e trattini, fino a 60.',
            url: 'Serve un indirizzo https.',
            tooMany: 'Hai raggiunto il numero massimo di messaggi salvati.',
        },
        en: {
            preview: 'How it looks on Discord',
            todayAt: 'Today at 18:42',
            tabs: { welcome: 'Welcome', welcome_dm: 'In DMs', goodbye: 'Goodbye', boost: 'Boost', premium: 'Premium', custom: 'Saved' },
            leads: {
                welcome: 'Goes out every time someone joins the server.',
                welcome_dm: 'Arrives in the DMs of whoever joins. People with DMs closed don\'t get it.',
                goodbye: 'Goes out when someone leaves the server.',
                boost: 'Goes out when someone boosts the server.',
                premium: 'Goes out when someone buys Premium on the website.',
            },
            on: 'On',
            channel: 'Channel',
            channelHint: 'Where it gets posted.',
            content: 'Text above the box',
            title: 'Title',
            description: 'Description',
            color: 'Colour',
            thumbnail: 'Small image',
            image: 'Large image',
            footer: 'Footer',
            timestamp: 'Show date and time',
            urlHint: 'An https address, or {user.avatar} or {server.icon}.',
            notYet: 'This message hasn\'t been written yet: what you see is a starting point. It goes out on its own once you save it.',
            test: 'Send a test',
            testHint: 'As if it had just happened to you. Save first.',
            tested: 'Test sent.',
            testedDm: 'Test sent to your DMs.',
            sendFailed: {
                module_off: 'The Messages module is off: turn it on and try again.',
                channel_not_found: 'Poppy can\'t find that channel.',
                dm_closed: 'Your DMs are closed: Poppy can\'t message you.',
                message_not_found: 'The posted message is gone: post it again.',
                unreachable: 'Poppy isn\'t answering.',
            },
            saved: 'Saved messages',
            savedLead: 'Rules, announcements, any fixed message. You write them here, post them where you want and can rewrite them without posting again.',
            newMessage: 'New message',
            name: 'Name',
            nameHint: 'To find it again: "rules", "christmas announcement".',
            noSaved: 'No saved messages yet.',
            published: 'Posted',
            notPublished: 'Never posted',
            publishIn: 'Post in',
            publish: 'Post',
            update: 'Update the posted one',
            publishedOk: 'Posted.',
            updatedOk: 'Updated in place.',
            del: 'Delete',
            delAsk: 'Delete this saved message? The ones already posted stay where they are.',
            deleted: 'Deleted.',
            back: 'All saved messages',
            badName: 'Letters, digits, spaces and dashes only, up to 60.',
            url: 'An https address is needed.',
            tooMany: 'You reached the maximum number of saved messages.',
        }
    }[LANG];

    const TOKENS = ['user', 'user.name', 'user.avatar', 'server', 'server.icon', 'membercount', 'membercount.ordinal', 'boostcount', 'date'];
    const AUTO_KINDS = ['welcome', 'welcome_dm', 'goodbye', 'boost', 'premium'];

    sections.messages = {
        tab: 'welcome',
        store: null,
        draft: null,
        editing: null,
        errors: {},

        kinds() {
            return ['welcome', 'welcome_dm', 'goodbye', 'boost', ...(state.data.guild.is_home ? ['premium'] : []), 'custom'];
        },

        find(kind, name) {
            return this.store?.templates.find(template => template.kind === kind && template.name === name) || null;
        },

        /** Apre un messaggio nell'editor: quello salvato, o il testo di partenza. */
        open(kind, name, isNew = false) {
            const saved = isNew ? null : this.find(kind, name);
            const base = saved || this.store?.defaults?.[kind] || { enabled: true, content: '', embed: {} };

            this.editing = { kind, name, isNew, saved: !!saved };
            this.draft = {
                enabled: saved ? saved.enabled : true,
                channel_id: saved?.channel_id || '',
                content: base.content || '',
                embed: { title: '', description: '', color: '', thumbnail: '', image: '', footer_text: '', timestamp: false, ...P.clone(base.embed || {}) },
            };
            this.errors = {};
            P.setExtra(null);
        },

        field(key, label, { multiline = false, max = 256, hint = '', placeholder = '' } = {}) {
            const value = key === 'content' ? this.draft.content : this.draft.embed[key.replace('embed.', '')] ?? '';
            const error = this.errors[key];
            const message = error ? `<span class="bp-error">${esc({ url: TM.url }[error] || P.ERRORS[error] || error)}</span>` : '';
            const attrs = `data-tpl="${esc(key)}" maxlength="${max}" aria-label="${esc(label)}"${placeholder ? ` placeholder="${esc(placeholder)}"` : ''}`;

            return `<div class="nx-field">
                <span class="nx-field__label">${esc(label)}</span>
                ${multiline
                    ? `<textarea class="nx-input${error ? ' is-invalid' : ''}" rows="5" ${attrs}>${esc(value)}</textarea>`
                    : `<input class="nx-input${error ? ' is-invalid' : ''}" value="${esc(value)}" ${attrs}>`}
                ${hint ? `<span class="nx-field__hint">${esc(hint)}</span>` : ''}${message}
            </div>`;
        },

        channelName() {
            return state.data.channels.find(channel => channel.id === this.draft.channel_id)?.name || '';
        },

        preview() {
            const hasEmbed = Object.entries(this.draft.embed).some(([key, value]) => key !== 'color' && key !== 'timestamp' && value);
            return discordMessage({ content: this.draft.content, embed: hasEmbed ? this.draft.embed : null }, this.channelName());
        },

        editor() {
            const { kind, name, isNew, saved } = this.editing;
            const custom = kind === 'custom';
            const error = key => (this.errors[key] ? `<span class="bp-error">${esc(P.ERRORS[this.errors[key]] || { name: TM.badName, too_many: TM.tooMany }[this.errors[key]] || this.errors[key])}</span>` : '');
            const sent = custom ? this.store.sent[name] : null;

            const top = custom
                ? `${isNew ? `<div class="nx-field">
                        <span class="nx-field__label">${esc(TM.name)}</span>
                        <input class="nx-input${this.errors.name ? ' is-invalid' : ''}" value="${esc(name)}" maxlength="60" data-tpl-name aria-label="${esc(TM.name)}">
                        <span class="nx-field__hint">${esc(TM.nameHint)}</span>${error('name')}
                    </div>` : ''}`
                : `<div class="bp-settings">
                        <div class="bp-setting bp-setting--switch">
                            <div><p class="bp-setting__name">${esc(TM.tabs[kind])} · ${esc(TM.on.toLowerCase())}</p><p class="bp-setting__hint">${esc(TM.leads[kind])}</p></div>
                            <label class="nx-switch"><input type="checkbox" data-tpl-enabled${this.draft.enabled ? ' checked' : ''} aria-label="${esc(TM.on)}"><i></i></label>
                        </div>
                        ${kind === 'welcome_dm' ? '' : `<div class="bp-setting">
                            <div><p class="bp-setting__name">${esc(TM.channel)}</p><p class="bp-setting__hint">${esc(TM.channelHint)}</p></div>
                            <div class="bp-setting__control">
                                <select class="nx-input nx-select${this.errors.channel_id ? ' is-invalid' : ''}" data-tpl-channel aria-label="${esc(TM.channel)}">${P.channelOptions({ need: ['send', 'embed'] }, this.draft.channel_id)}</select>
                                ${error('channel_id')}
                            </div>
                        </div>`}
                    </div>`;

            const publish = custom && !isNew ? `<div class="bp-bar">
                    <select class="nx-input nx-select" style="width:14rem" data-tpl-publish-channel aria-label="${esc(TM.publishIn)}">${P.channelOptions({ need: ['send', 'embed'] }, sent?.channel_id || this.draft.channel_id)}</select>
                    <button class="nx-btn nx-btn--outline nx-btn--sm" type="button" data-tpl-send="publish"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>${esc(TM.publish)}</button>
                    ${sent ? `<button class="nx-btn nx-btn--outline nx-btn--sm" type="button" data-tpl-send="update"><i class="fa-solid fa-pen" aria-hidden="true"></i>${esc(TM.update)}</button>` : ''}
                    <button class="nx-btn nx-btn--danger nx-btn--sm bp-bar__end" type="button" data-tpl-delete>${esc(TM.del)}</button>
                </div>` : '';

            const test = !custom ? `<div class="bp-bar" style="margin-top:.4rem">
                    <button class="nx-btn nx-btn--outline nx-btn--sm" type="button" data-tpl-send="test"${saved ? '' : ' disabled'}><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>${esc(TM.test)}</button>
                    <span class="bp-dim">${esc(TM.testHint)}</span>
                </div>` : '';

            return `<div class="bp-split">
                <section class="bp-form">
                    ${custom ? `<button class="nx-link bp-always" type="button" data-tpl-back><i class="fa-solid fa-chevron-left" aria-hidden="true" style="font-size:.7rem"></i><span class="nx-link__label">${esc(TM.back)}</span></button>` : ''}
                    ${!custom && !saved ? `<p class="nx-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>${esc(TM.notYet)}</span></p>` : ''}
                    ${top}
                    ${this.field('content', TM.content, { max: 2000 })}
                    ${this.field('embed.title', TM.title)}
                    <div class="nx-field">
                        <span class="nx-field__label">${esc(TM.description)}</span>
                        <textarea class="nx-input${this.errors['embed.description'] ? ' is-invalid' : ''}" rows="6" maxlength="4000" data-tpl="embed.description" aria-label="${esc(TM.description)}">${esc(this.draft.embed.description || '')}</textarea>
                        <span class="bp-tokens">${TOKENS.map(token => `<button class="bp-token" type="button" data-tpl-token="{${token}}">{${token}}</button>`).join('')}</span>
                        ${error('embed.description')}
                    </div>
                    <div class="bp-form__pair">
                        <div class="nx-field">
                            <span class="nx-field__label">${esc(TM.color)}</span>
                            <span class="bp-color"><input type="color" class="bp-swatch" value="${esc(/^#[0-9a-f]{6}$/i.test(this.draft.embed.color || '') ? this.draft.embed.color : '#5865f2')}" data-tpl="embed.color" aria-label="${esc(TM.color)}"><input class="nx-input${this.errors['embed.color'] ? ' is-invalid' : ''}" maxlength="7" value="${esc(this.draft.embed.color || '')}" data-tpl="embed.color" aria-label="${esc(TM.color)}"></span>
                            ${error('embed.color')}
                        </div>
                        ${this.field('embed.thumbnail', TM.thumbnail, { max: 400, placeholder: '{user.avatar}' })}
                    </div>
                    <div class="bp-form__pair">
                        ${this.field('embed.image', TM.image, { max: 400, placeholder: 'https://…' })}
                        ${this.field('embed.footer_text', TM.footer, { max: 2048 })}
                    </div>
                    <div class="bp-setting bp-setting--switch" style="border:0;padding:0">
                        <div><p class="bp-setting__name">${esc(TM.timestamp)}</p></div>
                        <label class="nx-switch"><input type="checkbox" data-tpl-timestamp${this.draft.embed.timestamp ? ' checked' : ''} aria-label="${esc(TM.timestamp)}"><i></i></label>
                    </div>
                    ${test}${publish}
                </section>
                <aside class="bp-preview" aria-label="${esc(TM.preview)}" data-tpl-preview>${this.preview()}</aside>
            </div>`;
        },

        savedList() {
            const list = this.store.templates.filter(template => template.kind === 'custom');
            const channel = id => state.data.channels.find(entry => entry.id === id)?.name;

            const rows = list.map(template => {
                const sent = this.store.sent[template.name];

                return `<li class="bp-row bp-row--plain">
                    <div>
                        <p class="bp-row__title">${esc(template.name)}</p>
                        <p class="bp-row__meta">${sent ? `${esc(TM.published)}${channel(sent.channel_id) ? ` · # ${esc(channel(sent.channel_id))}` : ''}` : esc(TM.notPublished)}</p>
                    </div>
                    <div class="bp-row__side"><button class="nx-icon-btn bp-always" type="button" data-tpl-open="${esc(template.name)}" aria-label="${esc(S.open)} ${esc(template.name)}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button></div>
                </li>`;
            }).join('');

            return `<section>
                <p class="bp-lead" style="margin-bottom:1rem">${esc(TM.savedLead)}</p>
                ${list.length ? `<ul class="bp-rows">${rows}</ul>` : `<p class="bp-empty">${esc(TM.noSaved)}</p>`}
                <div class="bp-bar" style="margin-top:1rem">
                    <button class="nx-btn nx-btn--soft nx-btn--sm" type="button" data-tpl-new${list.length >= this.store.custom_max ? ' disabled' : ''}><i class="fa-solid fa-plus" aria-hidden="true"></i>${esc(TM.newMessage)}</button>
                    <span class="bp-dim">${list.length} / ${this.store.custom_max}</span>
                </div>
            </section>`;
        },

        render() {
            const count = this.store ? this.store.templates.filter(template => template.kind === 'custom').length : 0;
            const tabs = `<div class="nx-tabs" role="tablist">${this.kinds().map(kind => `<button class="nx-tab bp-always${this.tab === kind ? ' is-active' : ''}" role="tab" aria-selected="${this.tab === kind}" data-tpl-tab="${kind}">${esc(TM.tabs[kind])}${kind === 'custom' && count ? ` · ${count}` : ''}</button>`).join('')}</div>`;

            let body;
            if (!this.store) {
                body = `<p class="bp-empty">${esc(S.loading)}</p>`;
            } else if (this.tab === 'custom' && !this.editing) {
                body = this.savedList();
            } else {
                // I ruoli all'ingresso stanno con il benvenuto: e' li' che li si cerca.
                const autorole = this.tab === 'welcome' ? `<section class="bp-settings">${P.moduleFields('messages', ['autorole_ids']).single}</section>` : '';
                body = `${this.editor()}${autorole}`;
            }

            return `${P.moduleHead('messages', tabs)}${body}`;
        },

        async load() {
            this.loading = true;
            const response = await api(`templates.php?guild=${state.guildId}`);
            this.loading = false;

            this.store = response.ok
                ? { templates: response.templates, defaults: response.defaults, sent: response.sent || {}, custom_max: response.custom_max || 50 }
                : { templates: [], defaults: {}, sent: {}, custom_max: 50 };

            if (this.tab !== 'custom') this.open(this.tab, 'default');
            if (state.section === 'messages') P.renderSection();
        },

        mount() {
            if (this.guild !== state.guildId) {
                this.guild = state.guildId;
                this.store = null;
                this.editing = null;
                this.tab = 'welcome';
            }

            if (!this.store && !this.loading) this.load();
        },

        dirty() {
            P.setExtra({
                save: () => this.save(),
                discard: () => { if (this.editing) this.open(this.editing.kind, this.editing.name, this.editing.isNew); }
            });
        },

        async save() {
            const { kind, name } = this.editing;

            const response = await api('templates.php', {
                method: 'POST',
                body: { guild: state.guildId, action: 'save', kind, name, template: this.draft }
            });

            if (!response.ok) {
                this.errors = response.errors || {};
                return response;
            }

            this.store.templates = this.store.templates.filter(template => !(template.kind === kind && template.name === response.template.name));
            this.store.templates.push(response.template);
            this.editing = { kind, name: response.template.name, isNew: false, saved: true };
            this.errors = {};

            return response;
        },

        onChange(target) {
            if (target.matches('[data-tpl]')) {
                const key = target.dataset.tpl;
                if (key === 'content') this.draft.content = target.value;
                else this.draft.embed[key.replace('embed.', '')] = target.value;

                // Il campione del colore e il suo codice restano allineati.
                if (key === 'embed.color') {
                    target.closest('.bp-color')?.querySelectorAll('input').forEach(input => {
                        if (input !== target && /^#[0-9a-f]{6}$/i.test(target.value)) input.value = target.value;
                    });
                }
            } else if (target.matches('[data-tpl-name]')) {
                this.editing.name = target.value.trim();
            } else if (target.matches('[data-tpl-enabled]')) {
                this.draft.enabled = target.checked;
            } else if (target.matches('[data-tpl-timestamp]')) {
                this.draft.embed.timestamp = target.checked;
            } else if (target.matches('[data-tpl-channel]')) {
                this.draft.channel_id = target.value;
            } else {
                return;
            }

            this.dirty();
            const preview = document.querySelector('[data-tpl-preview]');
            if (preview) preview.innerHTML = this.preview();
        },

        async send(mode, button) {
            // Si invia quello che e' salvato: con modifiche in sospeso sarebbe un altro.
            if (state.extra) return P.toast(TM.testHint, true);

            button.disabled = true;
            const channel = document.querySelector('[data-tpl-publish-channel]')?.value || '';

            const response = await api('templates.php', {
                method: 'POST',
                body: { guild: state.guildId, action: 'send', mode, kind: this.editing.kind, name: this.editing.name, channel_id: channel }
            });

            button.disabled = false;

            if (!response.ok) {
                return P.toast(TM.sendFailed[response.reason] || P.ERRORS[response.errors?.publish_channel] || P.failText(response), true);
            }

            if (mode === 'publish') {
                this.store.sent[this.editing.name] = { channel_id: response.channel_id, message_id: response.message_id };
                P.renderSection();
            }

            P.toast({ test: response.dm ? TM.testedDm : TM.tested, publish: TM.publishedOk, update: TM.updatedOk }[mode]);
        },

        onClick(target) {
            if (target.matches('[data-tpl-tab]')) {
                if (state.extra && !window.confirm(S.leave)) return;

                this.tab = target.dataset.tplTab;
                this.editing = null;
                P.setExtra(null);
                if (this.tab !== 'custom' && this.store) this.open(this.tab, 'default');
                return P.renderSection();
            }

            if (target.matches('[data-tpl-open]')) {
                this.open('custom', target.dataset.tplOpen);
                return P.renderSection();
            }

            if (target.matches('[data-tpl-new]')) {
                this.open('custom', '', true);
                this.dirty();
                return P.renderSection();
            }

            if (target.matches('[data-tpl-back]')) {
                if (state.extra && !window.confirm(S.leave)) return;
                this.editing = null;
                P.setExtra(null);
                return P.renderSection();
            }

            if (target.matches('[data-tpl-token]')) {
                const area = document.querySelector('[data-tpl="embed.description"]');
                const at = area.selectionStart ?? area.value.length;
                area.value = area.value.slice(0, at) + target.dataset.tplToken + area.value.slice(area.selectionEnd ?? at);
                area.focus();
                area.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }

            if (target.matches('[data-tpl-send]')) return this.send(target.dataset.tplSend, target);

            if (target.matches('[data-tpl-delete]')) {
                if (!window.confirm(TM.delAsk)) return;

                return api('templates.php', {
                    method: 'POST',
                    body: { guild: state.guildId, action: 'delete', kind: 'custom', name: this.editing.name }
                }).then(response => {
                    if (!response.ok) return P.toast(P.failText(response), true);

                    this.store.templates = this.store.templates.filter(template => !(template.kind === 'custom' && template.name === this.editing.name));
                    this.editing = null;
                    P.setExtra(null);
                    P.toast(TM.deleted);
                    P.renderSection();
                });
            }
        }
    };

    /* ── Ticket ───────────────────────────────────────────────
       Le impostazioni, il bottone che fa pubblicare il pannello a Poppy e,
       sotto, quello che sta succedendo: i ticket aperti e qualche numero. */

    const TT = {
        it: {
            panel: 'Pannello su Discord',
            panelLead: 'È il messaggio da cui le persone aprono un ticket. Poppy lo pubblica nel canale dei ticket; se cambi categorie o testi, pubblicalo di nuovo e cancella quello vecchio.',
            publish: 'Pubblica il pannello',
            publishHint: 'Salva e accendi il modulo prima di pubblicare.',
            published: 'Pannello pubblicato.',
            openIt: 'Apri su Discord',
            failed: {
                module_off: 'Il modulo Ticket è spento: accendilo, salva e riprova.',
                channel_not_found: 'Poppy non trova il canale dei ticket.',
                no_categories: 'Aggiungi almeno una categoria.',
                send_failed: 'Poppy non riesce a scrivere nel canale dei ticket.',
                unreachable: 'Poppy non risponde.',
            },
            now: 'Adesso',
            notInstalled: 'I ticket non sono ancora attivi sul sito: manca la migrazione della fase 2.',
            none: 'Nessun ticket aperto.',
            stats: (s) => [
                s.open + (s.open === 1 ? ' aperto' : ' aperti'),
                s.closed_30d + ' chiusi negli ultimi 30 giorni',
                s.rating_30d !== null ? 'voto medio ' + String(s.rating_30d).replace('.', ',') + ' su 5 (' + s.rated_30d + (s.rated_30d === 1 ? ' voto)' : ' voti)') : '',
                s.minutes_30d !== null ? 'chiusi in media dopo ' + span(s.minutes_30d, 'it') : ''
            ].filter(Boolean).join(' · '),
            n: 'N°', subject: 'Oggetto', category: 'Categoria', opener: 'Aperto da', assignee: 'Assegnato a', when: 'Quando',
            nobody: 'nessuno',
        },
        en: {
            panel: 'Panel on Discord',
            panelLead: 'It is the message people open a ticket from. Poppy posts it in the ticket channel; if you change categories or texts, post it again and delete the old one.',
            publish: 'Post the panel',
            publishHint: 'Save and turn the module on before posting.',
            published: 'Panel posted.',
            openIt: 'Open on Discord',
            failed: {
                module_off: 'The Tickets module is off: turn it on, save and try again.',
                channel_not_found: 'Poppy can\'t find the ticket channel.',
                no_categories: 'Add at least one category.',
                send_failed: 'Poppy can\'t write in the ticket channel.',
                unreachable: 'Poppy isn\'t answering.',
            },
            now: 'Right now',
            notInstalled: 'Tickets are not active on the website yet: the phase 2 migration is missing.',
            none: 'No open tickets.',
            stats: (s) => [
                s.open + ' open',
                s.closed_30d + ' closed in the last 30 days',
                s.rating_30d !== null ? 'average rating ' + s.rating_30d + ' out of 5 (' + s.rated_30d + (s.rated_30d === 1 ? ' rating)' : ' ratings)') : '',
                s.minutes_30d !== null ? 'closed after ' + span(s.minutes_30d, 'en') + ' on average' : ''
            ].filter(Boolean).join(' · '),
            n: 'No.', subject: 'Subject', category: 'Category', opener: 'Opened by', assignee: 'Assigned to', when: 'When',
            nobody: 'nobody',
        }
    }[LANG];

    /** Minuti in parole: 45 min, 3 h, 2 giorni. */
    function span(minutes, lang) {
        if (minutes < 60) return minutes + ' min';
        if (minutes < 60 * 48) return Math.round(minutes / 60) + ' h';

        const days = Math.round(minutes / 1440);
        return days + (lang === 'it' ? ' giorni' : ' days');
    }

    sections.tickets = {
        data: null,

        render() {
            const { single, lists } = P.moduleFields('tickets');
            const module = state.data.modules.tickets;
            const ready = module.configured && module.enabled && !P.draftOf('tickets')?.dirty;

            let activity;
            if (this.data === null) {
                activity = '<p class="bp-empty">' + esc(S.loading) + '</p>';
            } else if (!this.data.available) {
                activity = '<p class="bp-empty">' + esc(TT.notInstalled) + '</p>';
            } else {
                const rows = this.data.open.map(ticket => '<tr>'
                    + '<td class="bp-num"><a href="https://discord.com/channels/' + esc(state.guildId) + '/' + esc(ticket.thread_id) + '" target="_blank" rel="noopener">#' + String(ticket.number).padStart(4, '0') + '</a></td>'
                    + '<td>' + esc(ticket.subject) + '</td>'
                    + '<td class="bp-dim">' + esc(ticket.category) + '</td>'
                    + '<td>' + person(ticket.opener_id, this.data.users) + '</td>'
                    + '<td>' + (ticket.claimed_by ? person(ticket.claimed_by, this.data.users) : '<span class="bp-dim">' + esc(TT.nobody) + '</span>') + '</td>'
                    + '<td class="bp-dim">' + esc(P.formatWhen(ticket.when)) + '</td>'
                    + '</tr>').join('');

                activity = '<p class="bp-lead" style="margin-bottom:1rem">' + esc(TT.stats(this.data.stats)) + '</p>'
                    + (this.data.open.length
                        ? '<div class="bp-table-wrap"><table class="nx-table"><thead><tr><th>' + [TT.n, TT.subject, TT.category, TT.opener, TT.assignee, TT.when].map(esc).join('</th><th>') + '</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
                        : '<p class="bp-empty">' + esc(TT.none) + '</p>');
            }

            return P.moduleHead('tickets')
                + '<section class="bp-settings">' + single + '</section>'
                + lists
                + '<section><h2 class="bp-h">' + esc(TT.panel) + '</h2>'
                + '<p class="bp-lead" style="margin-bottom:1rem">' + esc(TT.panelLead) + '</p>'
                + '<div class="bp-bar"><button class="nx-btn nx-btn--soft nx-btn--sm" type="button" data-tk-publish' + (ready ? '' : ' disabled') + '><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>' + esc(TT.publish) + '</button>'
                + (this.url ? '<a class="nx-btn nx-btn--outline nx-btn--sm" href="' + esc(this.url) + '" target="_blank" rel="noopener">' + esc(TT.openIt) + '</a>' : '')
                + (ready ? '' : '<span class="nx-field__hint">' + esc(TT.publishHint) + '</span>') + '</div></section>'
                + '<section><h2 class="bp-h">' + esc(TT.now) + '</h2>' + activity + '</section>';
        },

        async load() {
            this.loading = true;
            const response = await api('activity.php?guild=' + state.guildId + '&what=tickets');
            this.loading = false;

            this.data = response.ok
                ? { available: response.available !== false, stats: response.stats, open: response.open || [], users: response.users || {} }
                : { available: true, stats: { open: 0, closed_30d: 0, rated_30d: 0, rating_30d: null, minutes_30d: null }, open: [], users: {} };

            if (state.section === 'tickets') P.renderSection();
        },

        mount() {
            if (this.guild !== state.guildId) {
                this.guild = state.guildId;
                this.data = null;
                this.url = null;
            }

            if (this.data === null && !this.loading) this.load();
        },

        async onClick(target) {
            const button = target.closest('[data-tk-publish]');
            if (!button || button.disabled) return;

            button.disabled = true;
            const response = await api('activity.php', { method: 'POST', body: { guild: state.guildId, action: 'publish_tickets' } });
            button.disabled = false;

            if (!response.ok) {
                return P.toast(TT.failed[response.reason] || P.failText(response), true);
            }

            this.url = response.url;
            P.toast(TT.published);
            P.renderSection();
        }
    };

    /* ── Giveaway ─────────────────────────────────────────────
       Le impostazioni e l'elenco di quelli in corso. Si lanciano da Discord,
       nel canale in cui devono comparire. */

    const TG = {
        it: {
            running: 'In corso e appena finiti',
            how: 'Un giveaway si lancia da Discord, nel canale in cui deve comparire: /giveaway start. Con «repeat» riparte da solo ogni volta che finisce.',
            none: 'Nessun giveaway in corso.',
            code: 'N°', prize: 'Premio', entries: 'Partecipanti', winners: 'Vincitori', ends: 'Termina',
            ended: 'finito', repeats: 'si ripete', site: 'premi del sito',
        },
        en: {
            running: 'Running and just ended',
            how: 'A giveaway is started from Discord, in the channel where it should show up: /giveaway start. With "repeat" it starts again on its own every time it ends.',
            none: 'No giveaway is running.',
            code: 'No.', prize: 'Prize', entries: 'Entries', winners: 'Winners', ends: 'Ends',
            ended: 'ended', repeats: 'repeats', site: 'website prizes',
        }
    }[LANG];

    sections.giveaway = {
        list: null,

        render() {
            const { single, lists } = P.moduleFields('giveaway');

            const rows = (this.list || []).map(g => {
                const link = g.message_id
                    ? '<a href="https://discord.com/channels/' + esc(state.guildId) + '/' + esc(g.channel_id) + '/' + esc(g.message_id) + '" target="_blank" rel="noopener">' + esc(g.code) + '</a>'
                    : esc(g.code);

                const tags = (g.site ? ' <span class="nx-tag">' + esc(TG.site) + '</span>' : '')
                    + (g.repeats ? ' <span class="nx-tag">' + esc(TG.repeats) + '</span>' : '')
                    + (g.running ? '' : ' <span class="nx-tag">' + esc(TG.ended) + '</span>');

                return '<tr><td class="bp-num">' + link + '</td><td>' + esc(g.title) + tags + '</td>'
                    + '<td>' + g.entries + '</td><td>' + g.winners + '</td>'
                    + '<td class="bp-dim">' + esc(P.formatWhen(g.ends)) + '</td></tr>';
            }).join('');

            return P.moduleHead('giveaway')
                + '<section class="bp-settings">' + single + '</section>'
                + lists
                + '<section><h2 class="bp-h">' + esc(TG.running) + '</h2>'
                + '<p class="bp-lead" style="margin-bottom:1rem">' + esc(TG.how) + '</p>'
                + (this.list === null ? '<p class="bp-empty">' + esc(S.loading) + '</p>'
                    : (this.list.length
                        ? '<div class="bp-table-wrap"><table class="nx-table"><thead><tr><th>' + [TG.code, TG.prize, TG.entries, TG.winners, TG.ends].map(esc).join('</th><th>') + '</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
                        : '<p class="bp-empty">' + esc(TG.none) + '</p>'))
                + '</section>';
        },

        async load() {
            this.loading = true;
            const response = await api('activity.php?guild=' + state.guildId + '&what=giveaways');
            this.loading = false;

            this.list = response.ok ? (response.giveaways || []) : [];
            if (state.section === 'giveaway') P.renderSection();
        },

        mount() {
            if (this.guild !== state.guildId) {
                this.guild = state.guildId;
                this.list = null;
            }

            if (this.list === null && !this.loading) this.load();
        }
    };

    /* @@ALTRE-SEZIONI@@ */
})();
