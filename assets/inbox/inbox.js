/**
 * Cripsum™ — Posta: messaggi del sito e ticket di supporto.
 *
 * Due sezioni nello stesso guscio. «Messaggi» è la posta vera (comunicazioni,
 * sicurezza, premi): elenco a pagine, lettura a destra, azioni singole e in
 * blocco. «Ticket» sono conversazioni con lo staff, fatte con gli stessi
 * componenti delle chat; quello che lo staff scrive dal thread Discord
 * collegato arriva qui, e viceversa.
 *
 * Gli aggiornamenti arrivano dal controllo leggero del sito (assets/rt/rt.js,
 * eventi «ib» e «tk»); se non è disponibile si ripiega su un controllo lento.
 * I testi stanno in includes/inbox_strings.php.
 */
(() => {
    'use strict';

    const K = window.ChatKit;
    const cfg = window.CripsumInbox;
    if (!K || !cfg) return;

    const { h, icon } = K;
    const T = cfg.strings;
    const RT = window.CripsumRT || null;
    const me = cfg.user;
    const lang = cfg.lang;
    const t = (key, vars) => {
        let text = T[key] || key;
        Object.keys(vars || {}).forEach((name) => {
            text = text.split('{' + name + '}').join(String(vars[name]));
        });
        return text;
    };

    const $ = (id) => document.getElementById(id);
    const els = {
        app: $('ibApp'), sections: $('ibSections'), search: $('ibSearch'), filter: $('ibFilter'), filterLabel: $('ibFilterLabel'),
        chips: $('ibChips'), bulk: $('ibBulk'), list: $('ibList'), welcome: $('ibWelcome'), reader: $('ibReader'),
        select: $('ibSelect'), more: $('ibMore')
    };

    const SYSTEM = ['system', 'changelog', 'security', 'moderation'];
    const CATEGORY_ICONS = {
        system: 'fa-solid fa-gear', changelog: 'fa-solid fa-wand-magic-sparkles', security: 'fa-solid fa-shield-halved',
        moderation: 'fa-solid fa-gavel', social: 'fa-solid fa-user-group', rewards: 'fa-solid fa-gift', special: 'fa-solid fa-star'
    };

    const state = {
        section: 'messages',
        // posta
        category: '', status: '', query: '',
        messages: [], hasMore: false, loading: false, failed: false, loadToken: 0,
        counts: { unread: 0, important: 0, archived: 0, total: 0, rewards: 0, categories: {}, totals: {} },
        openId: 0,
        selecting: false, selected: new Set(),
        // ticket
        tickets: [], ticketsLoaded: false, ticketStatus: 'open', ticketQuery: '',
        openTicket: null, ticketToken: 0
    };

    // ── Piccoli aiuti ──────────────────────────────────────────────────────

    const titleOf = (message) => (lang === 'en' ? message.title_en : message.title_it) || message.title_it || '';
    const contentOf = (message) => (lang === 'en' ? message.content_en : message.content_it) || message.content_it || '';
    const groupOf = (category) => (SYSTEM.includes(category) ? 'system' : category);
    const categoryLabel = (category) => T['cat_' + category] || T.cat_system;
    const isMobile = () => window.matchMedia('(max-width: 900px)').matches;

    function setUrl(params) {
        const url = new URL(window.location.href);
        ['m', 't', 'section', 'ticket_id'].forEach((key) => url.searchParams.delete(key));
        Object.keys(params || {}).forEach((key) => url.searchParams.set(key, params[key]));
        history.replaceState(history.state, '', url.pathname + (url.search || ''));
    }

    /** Anteprima per l'elenco: il testo senza i segni della formattazione. */
    function excerpt(text) {
        return String(text || '')
            .replace(/!\[[^\]]*\]\([^)]*\)/g, '')
            .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
            .replace(/^\s*(#{1,3}|>|[-*])\s+/gm, '')
            .replace(/\*\*/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 110);
    }

    // ── Testo formattato, senza HTML ───────────────────────────────────────

    /**
     * I messaggi della posta usano una formattazione minima (titoli, elenchi,
     * citazioni, grassetto, link, immagini). Qui diventa nodi del DOM, mai
     * HTML: quello che non è riconosciuto resta testo, e un link entra nella
     * pagina solo se porta a un indirizzo http(s) o a un percorso del sito.
     */
    function inline(node, text) {
        const pattern = /!\[([^\]]*)\]\(([^)\s]+)\)|\[([^\]]+)\]\(([^)\s]+)\)|\*\*([^*]+)\*\*|(https?:\/\/[^\s<>"')\]]+)/g;
        let last = 0;
        let match;
        const link = (href, label) => {
            const external = /^https?:\/\//i.test(href) && !href.startsWith(window.location.origin);
            return h('a', external ? { href, target: '_blank', rel: 'noopener noreferrer nofollow' } : { href }, label);
        };

        while ((match = pattern.exec(text)) !== null) {
            if (match.index > last) node.appendChild(document.createTextNode(text.slice(last, match.index)));
            if (match[2] !== undefined) {
                const src = K.safeUrl(match[2], '');
                node.appendChild(src ? h('img', { class: 'ib-md__img', src, alt: match[1] || '', loading: 'lazy' }) : document.createTextNode(match[0]));
            } else if (match[4] !== undefined) {
                const href = K.safeUrl(match[4], '');
                node.appendChild(href ? link(href, match[3]) : document.createTextNode(match[3]));
            } else if (match[5] !== undefined) {
                node.appendChild(h('strong', null, match[5]));
            } else {
                node.appendChild(link(match[6], match[6]));
            }
            last = pattern.lastIndex;
        }
        if (last < text.length) node.appendChild(document.createTextNode(text.slice(last)));
        return node;
    }

    function renderContent(text) {
        const root = h('div', { class: 'ib-md' });
        let list = null;
        let paragraph = null;

        String(text || '').replace(/\r\n?/g, '\n').split('\n').forEach((line) => {
            const heading = line.match(/^(#{1,3})\s+(.*)$/);
            const quote = line.match(/^>\s?(.*)$/);
            const item = line.match(/^\s*[-*]\s+(.*)$/);

            if (heading) {
                list = paragraph = null;
                root.appendChild(inline(h('h' + (heading[1].length + 2)), heading[2]));
            } else if (quote) {
                list = paragraph = null;
                root.appendChild(inline(h('blockquote'), quote[1]));
            } else if (item) {
                paragraph = null;
                if (!list) {
                    list = h('ul');
                    root.appendChild(list);
                }
                list.appendChild(inline(h('li'), item[1]));
            } else if (line.trim() === '') {
                list = paragraph = null;
            } else {
                list = null;
                if (!paragraph) {
                    paragraph = h('p');
                    root.appendChild(paragraph);
                } else {
                    paragraph.appendChild(h('br'));
                }
                inline(paragraph, line);
            }
        });
        return root;
    }

    // ── Posta: dati ────────────────────────────────────────────────────────

    async function loadMessages(options = {}) {
        const append = !!options.append;
        const token = ++state.loadToken;
        state.loading = true;
        if (!append && !options.quiet) paintList();

        const params = new URLSearchParams();
        if (state.category) params.set('category', state.category);
        if (state.status) params.set('status', state.status);
        if (state.query) params.set('q', state.query);
        if (append && state.messages.length) params.set('before', String(state.messages[state.messages.length - 1].id));
        // Aggiornando in silenzio si richiede tanto quanto è già a schermo.
        if (options.quiet) params.set('limit', String(Math.min(60, Math.max(30, state.messages.length))));

        try {
            const data = await K.api('/api/inbox.php?' + params.toString());
            if (token !== state.loadToken) return;
            state.messages = append ? state.messages.concat(data.messages || []) : (data.messages || []);
            state.hasMore = !!data.has_more;
            state.failed = false;
            applyCounts(data);
        } catch (_) {
            if (token !== state.loadToken) return;
            state.failed = true;
        } finally {
            if (token === state.loadToken) {
                state.loading = false;
                paintList();
                if (state.openId && !options.keepReader) refreshReader();
            }
        }
    }

    function applyCounts(data) {
        if (data.counts) state.counts = data.counts;
        paintSections();
        paintChips();
        // Il numero in navbar somma posta e ticket: lo riallinea chi lo conosce.
        if (RT) RT.refreshCounters();
    }

    const reloadQuiet = K.debounce(() => {
        if (state.section === 'messages') loadMessages({ quiet: true, keepReader: true });
    }, 500);

    async function act(action, ids, extra) {
        const body = Object.assign({ action }, extra || {});
        if (Array.isArray(ids)) body.ids = ids;
        else if (ids) body.message_id = ids;
        const data = await K.api('/api/inbox.php', { body });
        applyCounts(data);
        return data;
    }

    function patchMessages(ids, changes) {
        const wanted = new Set(ids.map(Number));
        state.messages.forEach((message) => {
            if (wanted.has(Number(message.id))) Object.assign(message, changes);
        });
    }

    /** Dopo un cambio di stato un messaggio può non appartenere più al filtro attivo. */
    function stillListed(message) {
        if (state.status === 'archived') return Number(message.is_archived) === 1;
        if (Number(message.is_archived) === 1) return false;
        if (state.status === 'important') return Number(message.is_important) === 1;
        return true;
    }

    // ── Posta: elenco ──────────────────────────────────────────────────────

    function paintSections() {
        const unreadTickets = state.tickets.filter((ticket) => ticket.status === 'open' && Number(ticket.is_unread_status) === 0).length;
        const counts = { messages: state.counts.unread, tickets: unreadTickets };
        els.sections.querySelectorAll('.ib-section').forEach((button) => {
            const active = button.dataset.section === state.section;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            const badge = button.querySelector('[data-count]');
            const value = counts[badge.dataset.count] || 0;
            badge.textContent = value > 99 ? '99+' : String(value);
            badge.hidden = value <= 0;
        });
    }

    function paintChips() {
        K.clear(els.chips);

        if (state.section === 'tickets') {
            [['open', T.tab_open], ['closed', T.tab_closed]].forEach(([key, label]) => {
                const count = state.tickets.filter((ticket) => ticket.status === key).length;
                els.chips.appendChild(h('button', {
                    type: 'button', class: 'ib-chip' + (state.ticketStatus === key ? ' is-active' : ''),
                    onClick: () => {
                        state.ticketStatus = key;
                        paintChips();
                        paintList();
                    }
                }, label, count ? h('small', null, String(count)) : null));
            });
            els.chips.appendChild(h('a', { class: 'ib-chip ib-chip--action', href: cfg.supportUrl }, icon('fa-solid fa-plus'), T.new_ticket));
            return;
        }

        const totals = state.counts.totals || {};
        const unread = state.counts.categories || {};
        const chips = [['', T.cat_all, state.counts.unread]];
        ['system', 'rewards', 'special', 'social'].forEach((key) => {
            // Una categoria senza messaggi non occupa spazio (tranne quella scelta).
            if ((totals[key] || 0) > 0 || state.category === key) chips.push([key, categoryLabel(key), unread[key] || 0]);
        });

        chips.forEach(([key, label, count]) => {
            els.chips.appendChild(h('button', {
                type: 'button', class: 'ib-chip' + (state.category === key ? ' is-active' : ''),
                onClick: () => {
                    state.category = key;
                    paintChips();
                    loadMessages();
                }
            }, label, count > 0 ? h('span', { class: 'ck-count' }, count > 99 ? '99+' : String(count)) : null));
        });
    }

    function paintFilterLabel() {
        const labels = { '': T.tab_inbox, unread: T.tab_unread, important: T.tab_starred, archived: T.tab_archive };
        els.filterLabel.textContent = labels[state.status];
        els.filter.classList.toggle('is-set', state.status !== '');
        els.filter.hidden = state.section !== 'messages';
        els.select.hidden = state.section !== 'messages';
        els.search.placeholder = state.section === 'tickets' ? T.search_tickets : T.search;
    }

    function messageRow(message) {
        const unread = Number(message.is_read) === 0;
        const pendingRewards = message.has_rewards > 0 && !message.claimed_at;
        const row = h('div', {
            class: 'ib-row' + (unread ? ' is-unread' : '') + (Number(message.id) === Number(state.openId) ? ' is-active' : '')
                + (state.selected.has(Number(message.id)) ? ' is-selected' : ''),
            dataset: { id: message.id }
        });

        if (state.selecting) {
            const box = h('input', { type: 'checkbox', class: 'ib-row__check', checked: state.selected.has(Number(message.id)), 'aria-label': t('select') });
            box.addEventListener('change', () => toggleSelected(message.id, box.checked));
            row.appendChild(box);
        }

        row.appendChild(h('button', {
            type: 'button', class: 'ib-row__main',
            onClick: () => (state.selecting ? toggleSelected(message.id, !state.selected.has(Number(message.id))) : openMessage(message.id))
        },
            h('span', { class: 'ib-row__icon ib-cat--' + groupOf(message.category) }, icon(CATEGORY_ICONS[message.category] || CATEGORY_ICONS.system)),
            h('span', { class: 'ib-row__body' },
                h('span', { class: 'ib-row__top' },
                    h('strong', null, titleOf(message)),
                    h('time', null, K.listTime(message.ts))),
                h('span', { class: 'ib-row__bottom' },
                    h('span', { class: 'ib-row__preview' }, excerpt(contentOf(message))),
                    h('span', { class: 'ib-row__flags' },
                        Number(message.is_important) === 1 ? icon('fa-solid fa-star', 'is-star') : null,
                        pendingRewards ? icon('fa-solid fa-gift', 'is-gift') : null,
                        unread ? h('i', { class: 'ib-dot' }) : null)))));
        return row;
    }

    function ticketRow(ticket) {
        const unread = ticket.status === 'open' && Number(ticket.is_unread_status) === 0;
        return h('div', { class: 'ib-row' + (unread ? ' is-unread' : '') + (state.openTicket === ticket.ticket_id ? ' is-active' : ''), dataset: { ticket: ticket.ticket_id } },
            h('button', { type: 'button', class: 'ib-row__main', onClick: () => openTicket(ticket.ticket_id) },
                h('span', { class: 'ib-row__icon ib-cat--ticket' }, icon('fa-solid fa-headset')),
                h('span', { class: 'ib-row__body' },
                    h('span', { class: 'ib-row__top' },
                        h('strong', null, ticket.title),
                        h('time', null, K.listTime(ticket.updated_ts))),
                    h('span', { class: 'ib-row__bottom' },
                        h('span', { class: 'ib-row__preview' }, ticket.ticket_id + ' · ' + ticket.topic + (ticket.username ? ' · @' + ticket.username : '')),
                        h('span', { class: 'ib-row__flags' },
                            Number(ticket.on_discord) === 1 ? icon('fa-brands fa-discord', 'is-discord') : null,
                            unread ? h('i', { class: 'ib-dot' }) : null)))));
    }

    function skeletonRows() {
        const rows = [];
        for (let i = 0; i < 6; i += 1) {
            rows.push(h('div', { class: 'ib-row ib-row--skeleton' },
                h('span', { class: 'ib-row__icon ck-skeleton' }),
                h('span', { class: 'ib-row__body' }, h('span', { class: 'ck-skeleton' }), h('span', { class: 'ck-skeleton' }))));
        }
        return rows;
    }

    function listEmpty(iconName, title, text, action) {
        return h('div', { class: 'ib-list__empty' }, K.emptyState(iconName, title, text, action || null));
    }

    function paintList() {
        K.clear(els.list);
        paintFilterLabel();

        if (state.section === 'tickets') {
            if (!state.ticketsLoaded) {
                skeletonRows().forEach((row) => els.list.appendChild(row));
                return;
            }
            const query = state.ticketQuery.toLowerCase();
            const tickets = state.tickets.filter((ticket) => ticket.status === state.ticketStatus
                && (!query || (ticket.title + ' ' + ticket.ticket_id + ' ' + ticket.topic + ' ' + (ticket.username || '')).toLowerCase().includes(query)));
            if (!tickets.length) {
                const none = state.tickets.length === 0;
                els.list.appendChild(listEmpty('fa-solid fa-headset', none ? T.empty_tickets : T.empty_filter, none ? T.empty_tickets_sub : T.empty_filter_sub,
                    none ? h('a', { class: 'ck-btn ck-btn--primary', href: cfg.supportUrl }, icon('fa-solid fa-plus'), T.new_ticket) : null));
                return;
            }
            tickets.forEach((ticket) => els.list.appendChild(ticketRow(ticket)));
            return;
        }

        if (state.loading && !state.messages.length) {
            skeletonRows().forEach((row) => els.list.appendChild(row));
            return;
        }
        if (state.failed && !state.messages.length) {
            els.list.appendChild(listEmpty('fa-solid fa-triangle-exclamation', T.load_error, '',
                h('button', { type: 'button', class: 'ck-btn ck-btn--primary', onClick: () => loadMessages() }, T.retry)));
            return;
        }
        if (!state.messages.length) {
            const filtered = state.category !== '' || state.status !== '' || state.query !== '';
            els.list.appendChild(listEmpty('fa-regular fa-envelope-open', filtered ? T.empty_filter : T.empty_inbox, filtered ? T.empty_filter_sub : T.empty_inbox_sub));
            return;
        }

        let day = '';
        state.messages.forEach((message) => {
            const label = K.dayLabel(message.ts);
            if (label !== day) {
                day = label;
                els.list.appendChild(h('div', { class: 'ib-list__day' }, label));
            }
            els.list.appendChild(messageRow(message));
        });

        if (state.hasMore) {
            els.list.appendChild(h('button', {
                type: 'button', class: 'ck-btn ck-btn--ghost ck-btn--sm ib-list__more',
                onClick: (event) => {
                    event.currentTarget.disabled = true;
                    loadMessages({ append: true, keepReader: true });
                }
            }, T.load_more));
        }
    }

    // ── Posta: selezione in blocco ─────────────────────────────────────────

    function toggleSelected(id, on) {
        if (on) state.selected.add(Number(id));
        else state.selected.delete(Number(id));
        const row = els.list.querySelector('.ib-row[data-id="' + Number(id) + '"]');
        if (row) {
            row.classList.toggle('is-selected', on);
            const box = row.querySelector('.ib-row__check');
            if (box) box.checked = on;
        }
        paintBulk();
    }

    function setSelecting(on) {
        state.selecting = on;
        state.selected.clear();
        els.select.classList.toggle('is-active', on);
        els.select.setAttribute('aria-pressed', on ? 'true' : 'false');
        paintBulk();
        paintList();
    }

    function paintBulk() {
        K.clear(els.bulk);
        els.bulk.hidden = !state.selecting;
        if (!state.selecting) return;

        const ids = [...state.selected];
        const run = async (action, changes, remove) => {
            if (!ids.length) return;
            try {
                await act(action, ids);
                if (remove) state.messages = state.messages.filter((message) => !state.selected.has(Number(message.id)));
                else patchMessages(ids, changes);
                state.messages = state.messages.filter(stillListed);
                if (ids.includes(Number(state.openId)) && !state.messages.some((m) => Number(m.id) === Number(state.openId))) closeReader();
                setSelecting(false);
            } catch (error) {
                K.toast(error.message, 'error');
            }
        };
        const tool = (iconName, label, onClick, danger) => h('button', {
            type: 'button', class: 'ck-icon-btn' + (danger ? ' is-danger' : ''), title: label, 'aria-label': label, disabled: !ids.length, onClick
        }, icon(iconName));

        const archived = state.status === 'archived';
        els.bulk.append(
            h('strong', null, t('selected', { n: ids.length })),
            h('span', { class: 'ib-bulk__tools' },
                tool('fa-regular fa-envelope-open', T.mark_read, () => run('read', { is_read: 1 })),
                tool('fa-solid fa-box-archive', archived ? T.restore : T.archive, () => run(archived ? 'unarchive' : 'archive', { is_archived: archived ? 0 : 1 })),
                tool('fa-regular fa-trash-can', T.delete, async () => {
                    const ok = await K.confirm({ title: T.delete_many_title, text: T.delete_text, confirmLabel: T.delete, danger: true, icon: 'fa-regular fa-trash-can' });
                    if (ok) run('delete', null, true);
                }, true),
                h('button', { type: 'button', class: 'ck-icon-btn', 'aria-label': K.t('close'), onClick: () => setSelecting(false) }, icon('fa-solid fa-xmark'))));
    }

    // ── Posta: lettura ─────────────────────────────────────────────────────

    function showReader() {
        els.welcome.hidden = true;
        els.reader.hidden = false;
        if (!els.app.classList.contains('is-reading')) {
            els.app.classList.add('is-reading');
            if (isMobile()) history.pushState({ ibReading: true }, '');
        }
    }

    function closeReader(fromHistory) {
        state.openId = 0;
        leaveTicket();
        els.reader.hidden = true;
        els.welcome.hidden = false;
        K.clear(els.reader);
        els.app.classList.remove('is-reading');
        els.list.querySelectorAll('.ib-row.is-active').forEach((row) => row.classList.remove('is-active'));
        setUrl(state.section === 'tickets' ? { section: 'tickets' } : {});
        if (!fromHistory && isMobile() && history.state && history.state.ibReading) history.back();
    }

    function rewardRow(reward) {
        const amount = (Number(reward.reward_value) || 0) * (Number(reward.quantity) || 1);
        let media;
        let label;
        let sub;
        switch (reward.reward_type) {
            case 'points':
                media = h('img', { src: '/img/godos.png', alt: '' });
                label = '+' + amount + ' Godos';
                sub = T.reward_money_sub;
                break;
            case 'godoshards':
                media = h('img', { src: '/img/godoshards.png', alt: '' });
                label = '+' + amount + ' Godo Shards';
                sub = T.reward_shard_sub;
                break;
            case 'character':
                media = icon('fa-solid fa-user-astronaut');
                label = T.reward_character + ' #' + reward.reward_value;
                sub = T.reward_character_sub;
                break;
            case 'badge':
                media = icon('fa-solid fa-medal');
                label = T.reward_badge + ' #' + reward.reward_value;
                sub = T.reward_badge_sub;
                break;
            case 'premium':
                media = K.premiumGem();
                label = T.reward_premium;
                sub = T.reward_premium_sub;
                break;
            default:
                media = icon('fa-solid fa-gift');
                label = String(reward.reward_type || '');
                sub = '';
        }
        return h('div', { class: 'ib-reward' }, h('span', { class: 'ib-reward__icon' }, media), h('span', { class: 'ib-reward__text' }, h('strong', null, label), h('small', null, sub)));
    }

    /** Finestra dopo il riscatto: cosa è arrivato sull'account. */
    function showClaimed(rewards) {
        if (!rewards || !rewards.length) return;
        const body = h('div', { class: 'ib-claimed' },
            h('p', { class: 'ck-dialog__text' }, T.claimed_sub),
            rewards.map((reward) => h('div', { class: 'ib-reward' },
                h('span', { class: 'ib-reward__icon' }, reward.type === 'points' ? h('img', { src: '/img/godos.png', alt: '' })
                    : reward.type === 'godoshards' ? h('img', { src: '/img/godoshards.png', alt: '' }) : icon('fa-solid fa-gift')),
                h('span', { class: 'ib-reward__text' }, h('strong', null, String(reward.label || ''))))));
        K.dialog({ title: T.claimed_title, icon: 'fa-solid fa-gift', body, actions: [{ label: T.claimed_close, kind: 'primary', value: true }] });
    }

    function paintReader(message) {
        K.clear(els.reader);
        const index = state.messages.findIndex((m) => Number(m.id) === Number(message.id));
        const starred = Number(message.is_important) === 1;
        const archived = Number(message.is_archived) === 1;

        const tool = (iconName, label, onClick, extra) => h('button', {
            type: 'button', class: 'ck-icon-btn' + (extra || ''), title: label, 'aria-label': label, onClick
        }, icon(iconName));

        const head = h('header', { class: 'ib-reader__head' },
            h('button', { type: 'button', class: 'ck-icon-btn ib-reader__back', 'aria-label': T.back, onClick: () => closeReader() }, icon('fa-solid fa-chevron-left')),
            h('span', { class: 'ib-tag ib-cat--' + groupOf(message.category) }, icon(CATEGORY_ICONS[message.category] || CATEGORY_ICONS.system), categoryLabel(message.category)),
            h('span', { class: 'ib-reader__tools' },
                tool('fa-solid fa-chevron-up', T.previous, () => step(-1), index <= 0 ? ' is-off' : ''),
                tool('fa-solid fa-chevron-down', T.next, () => step(1), index < 0 || index >= state.messages.length - 1 ? ' is-off' : ''),
                tool(starred ? 'fa-solid fa-star' : 'fa-regular fa-star', starred ? T.unstar : T.star, () => toggleStar(message), starred ? ' is-star' : ''),
                tool('fa-solid fa-box-archive', archived ? T.restore : T.archive, () => toggleArchive(message)),
                tool('fa-solid fa-ellipsis-vertical', T.more, (event) => K.menu(event.currentTarget, [
                    { label: T.mark_unread, icon: 'fa-regular fa-envelope', onSelect: () => markUnread(message) },
                    { divider: true },
                    { label: T.delete, icon: 'fa-regular fa-trash-can', danger: true, onSelect: () => deleteMessage(message) }
                ], { alignRight: true }))));

        const body = h('div', { class: 'ib-reader__body ck-scroll' },
            h('h2', null, titleOf(message)),
            h('time', { class: 'ib-reader__date' }, K.dayLabel(message.ts) + ' · ' + K.formatTime(message.ts)),
            renderContent(contentOf(message)));

        if (message.has_rewards > 0 && message.rewards && message.rewards.length) {
            const claimed = !!message.claimed_at;
            const box = h('section', { class: 'ib-rewards' + (claimed ? ' is-claimed' : '') },
                h('h3', null, icon('fa-solid fa-gift'), claimed ? T.rewards_claimed : T.rewards_included),
                h('div', { class: 'ib-rewards__list' }, message.rewards.map(rewardRow)));
            if (!claimed) {
                box.appendChild(h('button', {
                    type: 'button', class: 'ck-btn ck-btn--primary ck-btn--block',
                    onClick: async (event) => {
                        const button = event.currentTarget;
                        button.disabled = true;
                        try {
                            const data = await act('claim_rewards', message.id);
                            patchMessages([message.id], { claimed_at: 'now', is_read: 1 });
                            paintList();
                            paintReader(message);
                            showClaimed(data.rewards);
                        } catch (error) {
                            button.disabled = false;
                            K.toast(error.message, 'error');
                        }
                    }
                }, icon('fa-solid fa-gift'), T.claim_rewards));
            } else {
                box.appendChild(h('p', { class: 'ib-rewards__done' }, icon('fa-solid fa-circle-check'), T.rewards_claimed));
            }
            body.appendChild(box);
        }

        els.reader.append(head, body);
    }

    function refreshReader() {
        const message = state.messages.find((m) => Number(m.id) === Number(state.openId));
        if (message) paintReader(message);
    }

    async function openMessage(id) {
        leaveTicket();
        let message = state.messages.find((m) => Number(m.id) === Number(id));
        if (!message) {
            // Arrivati da un link: il messaggio può non essere nella pagina caricata.
            try {
                const data = await K.api('/api/inbox.php?id=' + encodeURIComponent(id));
                message = (data.messages || [])[0];
            } catch (_) {
                message = null;
            }
            if (!message) return;
            if (!state.messages.some((m) => Number(m.id) === Number(message.id))) {
                state.messages.push(message);
                state.messages.sort((a, b) => Number(b.id) - Number(a.id));
            }
        }

        state.openId = Number(message.id);
        showReader();
        paintReader(message);
        setUrl({ m: message.id });

        if (Number(message.is_read) === 0) {
            message.is_read = 1;
            act('read', message.id).catch(() => {
                message.is_read = 0;
            });
        }
        paintList();
    }

    function step(direction) {
        const index = state.messages.findIndex((m) => Number(m.id) === Number(state.openId));
        const next = state.messages[index + direction];
        if (next) openMessage(next.id);
    }

    async function toggleStar(message) {
        const on = Number(message.is_important) !== 1;
        message.is_important = on ? 1 : 0;
        paintReader(message);
        paintList();
        try {
            await act(on ? 'star' : 'unstar', message.id);
        } catch (error) {
            message.is_important = on ? 0 : 1;
            paintReader(message);
            paintList();
            K.toast(error.message, 'error');
        }
    }

    async function toggleArchive(message) {
        const archive = Number(message.is_archived) !== 1;
        const apply = async (value) => {
            await act(value ? 'archive' : 'unarchive', message.id);
            message.is_archived = value ? 1 : 0;
        };
        try {
            await apply(archive);
            const kept = state.messages.slice();
            state.messages = state.messages.filter(stillListed);
            closeReader();
            paintList();
            // Qualche secondo per ripensarci, senza finestre di conferma.
            const undo = await K.toastAction(archive ? T.archived_done : T.restored_done, T.undo, 5);
            if (undo) {
                await apply(!archive);
                state.messages = kept;
                paintList();
            }
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    async function markUnread(message) {
        try {
            await act('unread', message.id);
            message.is_read = 0;
            closeReader();
            paintList();
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    async function deleteMessage(message) {
        const ok = await K.confirm({ title: T.delete_title, text: T.delete_text, confirmLabel: T.delete, danger: true, icon: 'fa-regular fa-trash-can' });
        if (!ok) return;
        try {
            await act('delete', message.id);
            state.messages = state.messages.filter((m) => Number(m.id) !== Number(message.id));
            closeReader();
            paintList();
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    function openPageMenu(anchor) {
        K.menu(anchor, [
            {
                label: T.read_all, icon: 'fa-solid fa-check-double', disabled: state.counts.unread <= 0,
                onSelect: async () => {
                    try {
                        await act('read_all', null, { category: state.category });
                        K.toast(T.read_all_done, 'success');
                        loadMessages({ quiet: true });
                    } catch (error) {
                        K.toast(error.message, 'error');
                    }
                }
            },
            {
                label: T.claim_all + (state.counts.rewards > 0 ? ' (' + state.counts.rewards + ')' : ''), icon: 'fa-solid fa-gift', disabled: state.counts.rewards <= 0,
                onSelect: async () => {
                    try {
                        const data = await act('claim_all');
                        if (!data.claimed) K.toast(T.nothing_to_claim);
                        else showClaimed(data.rewards);
                        loadMessages({ quiet: true });
                    } catch (error) {
                        K.toast(error.message, 'error');
                    }
                }
            },
            { divider: true },
            { label: T.alerts, icon: 'fa-regular fa-bell', onSelect: () => RT && RT.openSettings() }
        ], { alignRight: true });
    }

    // ── Ticket ─────────────────────────────────────────────────────────────

    let ticketList = null;
    let ticketComposer = null;
    let ticketPoll = null;

    async function loadTickets() {
        try {
            const data = await K.api('/api/tickets.php');
            state.tickets = data.tickets || [];
        } catch (_) {
            /* resta l'elenco che c'era */
        }
        state.ticketsLoaded = true;
        paintSections();
        if (state.section === 'tickets') {
            paintChips();
            paintList();
        }
        if (state.openTicket) paintTicketHead();
    }

    const reloadTickets = K.debounce(loadTickets, 500);

    /**
     * Le risposte scritte da Discord da chi non ha l'account collegato arrivano
     * come «**Nome** (Discord)» sulla prima riga, firmate dall'account del bot:
     * qui tornano ad avere il loro autore.
     */
    function ticketItem(message) {
        const senderId = Number(message.sender_id) || 0;
        const mine = senderId === Number(me.id);
        const staff = message.ruolo === 'admin' || message.ruolo === 'owner';
        let name = message.username || T.guest;
        let text = String(message.message || '');
        let authorId = senderId || 'guest';
        let badge = null;

        const relayed = text.match(/^\*\*([^*\n]{1,80})\*\* \(Discord\)(?:\n([\s\S]*))?$/);
        if (relayed) {
            name = relayed[1];
            text = relayed[2] || '';
            authorId = 'discord:' + name;
            badge = { name: 'Discord', icon: 'fa-brands fa-discord' };
        }
        if (text === '📎' && message.attachment_url) text = '';

        return {
            id: message.id,
            ts: Number(message.ts) || Math.floor(Date.now() / 1000),
            mine: mine && !relayed,
            author: {
                id: authorId,
                name,
                username: message.username || '',
                url: senderId > 0 && message.username && !relayed ? '/u/' + encodeURIComponent(message.username) : '',
                avatar: senderId > 0 && !relayed ? K.avatarUrl(senderId) : '/img/abdul.jpg',
                role: staff ? 'admin' : null,
                badge
            },
            text,
            attachments: message.attachment_url ? [{ file_type: 'image', file_path: message.attachment_url, file_name: T.image_label }] : [],
            reactions: []
        };
    }

    function leaveTicket() {
        state.openTicket = null;
        if (RT) RT.setActiveTicket(null);
        state.ticketToken += 1;
        clearInterval(ticketPoll);
        ticketPoll = null;
        ticketList = null;
        ticketComposer = null;
    }

    function paintTicketHead() {
        const ticket = state.tickets.find((entry) => entry.ticket_id === state.openTicket);
        const host = els.reader.querySelector('.ib-ticket__head');
        if (!ticket || !host) return;
        K.clear(host);

        const open = ticket.status === 'open';
        host.append(
            h('button', { type: 'button', class: 'ck-icon-btn ib-reader__back', 'aria-label': T.back, onClick: () => closeReader() }, icon('fa-solid fa-chevron-left')),
            h('div', { class: 'ib-ticket__title' },
                h('strong', null, ticket.title),
                h('small', null,
                    ticket.ticket_id, ' · ', ticket.topic,
                    ticket.username ? ' · @' + ticket.username : '',
                    Number(ticket.on_discord) === 1 ? h('span', { class: 'ib-ticket__discord', title: ticket.source === 'discord' ? T.ticket_from_discord : T.ticket_on_discord }, icon('fa-brands fa-discord')) : null)),
            h('span', { class: 'ib-state' + (open ? ' is-open' : '') }, open ? T.status_open : T.status_closed));
        // Element.append scriverebbe «null» per un figlio mancante.
        if (me.staff) {
            host.appendChild(h('button', {
                type: 'button', class: 'ck-btn ck-btn--ghost ck-btn--sm',
                onClick: () => toggleTicket(ticket)
            }, icon(open ? 'fa-solid fa-lock' : 'fa-solid fa-lock-open'), open ? T.ticket_close : T.ticket_reopen));
        }

        if (ticketComposer) {
            const locked = !open;
            ticketComposer.setDisabled(locked, locked ? (me.staff ? T.ticket_closed_staff : T.ticket_closed_banner) : '');
        }
    }

    async function toggleTicket(ticket) {
        if (ticket.status === 'open') {
            const ok = await K.confirm({ title: T.ticket_close_title, text: T.ticket_close_text, confirmLabel: T.ticket_close, icon: 'fa-solid fa-lock' });
            if (!ok) return;
        }
        const form = new FormData();
        form.append('action', 'toggle_status');
        form.append('ticket_id', ticket.ticket_id);
        try {
            const data = await K.api('/api/tickets.php', { form });
            ticket.status = data.status;
            paintTicketHead();
            paintChips();
            paintList();
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    async function fetchTicket(ticketId, token, initial) {
        const after = initial || !ticketList ? 0 : ticketList.lastId();
        const data = await K.api('/api/tickets.php?ticket_id=' + encodeURIComponent(ticketId) + (after ? '&after=' + after : ''));
        if (token !== state.ticketToken || !ticketList) return;
        const items = (data.messages || []).map(ticketItem);

        if (initial) {
            if (items.length) ticketList.reset(items);
            else ticketList.showPlaceholder(K.emptyState('fa-solid fa-headset', T.no_chat_messages, ''));
        } else if (items.length) {
            if (ticketList.size === 0) ticketList.showPlaceholder(null);
            ticketList.upsert(items);
        }

        // Aprire il ticket lo segna come letto: l'elenco deve saperlo.
        const ticket = state.tickets.find((entry) => entry.ticket_id === ticketId);
        if (ticket) {
            if (data.ticket && data.ticket.status && data.ticket.status !== ticket.status) {
                ticket.status = data.ticket.status;
                paintTicketHead();
                paintChips();
            }
            if (Number(ticket.is_unread_status) === 0) {
                ticket.is_unread_status = 1;
                paintSections();
                paintList();
                if (RT) RT.refreshCounters();
            }
        }
    }

    async function openTicket(ticketId) {
        if (!state.tickets.some((entry) => entry.ticket_id === ticketId)) return;
        leaveTicket();
        state.openId = 0;
        state.openTicket = ticketId;
        if (RT) RT.setActiveTicket(ticketId);
        const token = state.ticketToken;

        showReader();
        K.clear(els.reader);
        const head = h('header', { class: 'ib-ticket__head' });
        const messages = h('div', { class: 'ib-ticket__messages' });
        const composerHost = h('div', { class: 'ib-ticket__composer' });
        els.reader.append(head, messages, composerHost);

        ticketList = new K.MessageList({
            container: messages,
            authors: 'others',
            tools: false,
            onAction: (action, item, event) => {
                if (action === 'user' && item.author.url) window.location.href = item.author.url;
                if (event && event.preventDefault) event.preventDefault();
            }
        });
        ticketList.showPlaceholder(K.spinner());

        ticketComposer = new K.Composer({
            root: composerHost,
            maxLength: 5000,
            maxFiles: 1,
            attachments: true,
            gif: false,
            placeholder: T.reply_placeholder,
            onSend: (payload) => sendTicketReply(ticketId, payload)
        });
        ticketComposer.fileInput.accept = 'image/png,image/jpeg,image/webp,image/gif';
        ticketComposer.setDraftKey('tk-' + ticketId);

        paintTicketHead();
        paintList();
        setUrl({ section: 'tickets', t: ticketId });

        try {
            await fetchTicket(ticketId, token, true);
        } catch (error) {
            if (token === state.ticketToken && ticketList) ticketList.showPlaceholder(K.emptyState('fa-solid fa-triangle-exclamation', error.message, ''));
        }

        // Riserva: se il tempo reale non è disponibile si chiede ogni tanto.
        ticketPoll = setInterval(() => {
            if (token !== state.ticketToken) return;
            if (document.visibilityState === 'visible' && (!RT || RT.isOff())) fetchTicket(ticketId, token, false).catch(() => {});
        }, 12000);

        ticketComposer.focus();
    }

    async function sendTicketReply(ticketId, payload) {
        const file = payload.files[0] || null;
        if (file && !file.type.startsWith('image/')) {
            K.toast(T.only_images, 'error');
            throw new Error(T.only_images);
        }
        if (file && file.size > 5 * 1024 * 1024) {
            K.toast(T.image_too_big, 'error');
            throw new Error(T.image_too_big);
        }

        const token = state.ticketToken;
        const tempId = 'tmp-' + Date.now().toString(36);
        const local = K.localAttachments(file ? [file] : []);
        if (ticketList.size === 0) ticketList.showPlaceholder(null);
        ticketList.upsert([{
            id: tempId, ts: Math.floor(Date.now() / 1000), mine: true,
            author: { id: me.id, name: me.username, username: me.username, avatar: K.avatarUrl(me.id) },
            text: payload.text, attachments: local, reactions: [], status: 'sending'
        }]);

        const form = new FormData();
        form.append('ticket_id', ticketId);
        form.append('message', payload.text);
        if (file) form.append('attachment', file, file.name);

        try {
            await K.upload('/api/tickets.php', form, (fraction) => ticketComposer && ticketComposer.setProgress(fraction));
            if (token !== state.ticketToken || !ticketList) return;
            ticketList.remove(tempId);
            await fetchTicket(ticketId, token, false);
            reloadTickets();
        } catch (error) {
            if (ticketList) ticketList.remove(tempId);
            K.toast(error.message, 'error');
            throw error;
        } finally {
            K.releaseLocalAttachments(local);
        }
    }

    // ── Sezioni, filtri, tastiera ──────────────────────────────────────────

    function setSection(section) {
        if (state.section === section) return;
        state.section = section;
        if (state.selecting) setSelecting(false);
        closeReader();
        els.search.value = section === 'tickets' ? state.ticketQuery : state.query;
        paintSections();
        paintChips();
        paintList();
        setUrl(section === 'tickets' ? { section: 'tickets' } : {});
        if (section === 'tickets' && !state.ticketsLoaded) loadTickets();
    }

    els.sections.addEventListener('click', (event) => {
        const button = event.target.closest('.ib-section');
        if (button) setSection(button.dataset.section);
    });

    const searchSoon = K.debounce(() => loadMessages(), 350);
    els.search.addEventListener('input', () => {
        const value = els.search.value.trim();
        if (state.section === 'tickets') {
            state.ticketQuery = value;
            paintList();
        } else {
            state.query = value;
            searchSoon();
        }
    });

    els.filter.addEventListener('click', () => {
        const option = (key, label, count) => ({
            label: label + (count ? '  (' + count + ')' : ''),
            icon: state.status === key ? 'fa-solid fa-check' : null,
            onSelect: () => {
                state.status = key;
                paintFilterLabel();
                loadMessages();
            }
        });
        K.menu(els.filter, [
            option('', T.tab_inbox, 0),
            option('unread', T.tab_unread, state.counts.unread),
            option('important', T.tab_starred, state.counts.important),
            option('archived', T.tab_archive, state.counts.archived)
        ], { alignRight: true });
    });

    els.select.addEventListener('click', () => setSelecting(!state.selecting));
    els.more.addEventListener('click', (event) => openPageMenu(event.currentTarget));

    document.addEventListener('keydown', (event) => {
        if (event.target.closest('input, textarea, [contenteditable], .cnav-pop') || event.ctrlKey || event.metaKey || event.altKey) return;
        if (document.querySelector('.ck-dialog, .ck-menu')) return;
        if (event.key === '/') {
            event.preventDefault();
            els.search.focus();
            return;
        }
        if (state.section !== 'messages' || !state.openId) return;
        const message = state.messages.find((m) => Number(m.id) === Number(state.openId));
        if (!message) return;
        if (event.key === 'j' || event.key === 'ArrowDown') step(1);
        else if (event.key === 'k' || event.key === 'ArrowUp') step(-1);
        else if (event.key === 's') toggleStar(message);
        else if (event.key === 'e') toggleArchive(message);
        else if (event.key === 'u') markUnread(message);
        else if (event.key === 'Delete') deleteMessage(message);
        else if (event.key === 'Escape') closeReader();
        else return;
        event.preventDefault();
    });

    window.addEventListener('popstate', () => {
        if (isMobile() && els.app.classList.contains('is-reading')) closeReader(true);
    });

    // ── Tempo reale ────────────────────────────────────────────────────────

    if (RT) {
        RT.onUser((event) => {
            if (event.t === 'ib' || event.t === 'resync') reloadQuiet();
            if (event.t === 'tk' || event.t === 'resync') {
                reloadTickets();
                if (state.openTicket && (event.t === 'resync' || event.k === state.openTicket)) {
                    fetchTicket(state.openTicket, state.ticketToken, false).catch(() => {});
                }
            }
        });
        RT.setMode('chat');
    }

    // «Segna tutto come letto» dal menu della campanella, con la posta aperta sotto.
    document.addEventListener('cripsum:inbox-changed', () => reloadQuiet());

    // Senza tempo reale la posta si riallinea da sola ogni minuto, a scheda visibile.
    setInterval(() => {
        if (document.visibilityState !== 'visible' || (RT && !RT.isOff())) return;
        reloadQuiet();
        reloadTickets();
    }, 60000);

    // ── Layout e avvio ─────────────────────────────────────────────────────

    function layout() {
        const navbar = document.querySelector('.navbarutenti');
        const bottom = navbar ? Math.max(0, navbar.getBoundingClientRect().bottom) : 0;
        document.documentElement.style.setProperty('--ck-nav', Math.round(bottom + 12) + 'px');
        const viewport = window.visualViewport;
        document.documentElement.style.setProperty('--ck-vh', Math.round(viewport ? viewport.height : window.innerHeight) + 'px');
    }

    layout();
    window.addEventListener('resize', layout, { passive: true });
    window.visualViewport?.addEventListener('resize', layout, { passive: true });
    setTimeout(layout, 400);

    (async () => {
        const params = new URLSearchParams(window.location.search);
        // `ticket_id` è il nome che usano i link del pannello admin.
        const ticketId = params.get('t') || params.get('ticket_id') || '';
        const wantTickets = params.get('section') === 'tickets' || ticketId !== '';
        const messageId = Number(params.get('m')) || 0;

        if (wantTickets) state.section = 'tickets';
        paintSections();
        paintChips();
        paintList();

        await Promise.all([loadMessages({ keepReader: true }), loadTickets()]);

        if (wantTickets && ticketId) openTicket(ticketId);
        else if (!wantTickets && messageId) openMessage(messageId);
    })();
})();
