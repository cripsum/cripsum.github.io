/**
 * Cripsum™ — chat private e di gruppo.
 *
 * La pagina è fatta di tre colonne: l'elenco delle chat, la conversazione
 * aperta, i dettagli. Quello che succede mentre è aperta (messaggi nuovi,
 * modifiche, reazioni, «sta scrivendo», conferme di lettura) arriva dal
 * controllo leggero del sito (assets/rt/rt.js): gli eventi portano solo gli
 * id, e i contenuti si chiedono agli endpoint della chat, che controllano i
 * permessi a ogni richiesta.
 */
(() => {
    'use strict';

    const K = window.ChatKit;
    const cfg = window.CripsumPrivateChat;
    if (!K || !cfg) return;

    const { h, icon, t } = K;
    const RT = window.CripsumRT || null;
    const me = cfg.user;
    const lang = K.lang;

    K.extend({
        pc_all: { it: 'Tutte', en: 'All' },
        pc_unread: { it: 'Non lette', en: 'Unread' },
        pc_groups: { it: 'Gruppi', en: 'Groups' },
        pc_requests: { it: 'Richieste', en: 'Requests' },
        pc_archived: { it: 'Archiviate', en: 'Archived' },
        pc_list_empty: { it: 'Nessuna chat qui.', en: 'No chats here.' },
        pc_list_empty_all: { it: 'Non hai ancora chat. Scrivi a un amico per cominciare.', en: 'You have no chats yet. Write to a friend to get started.' },
        pc_people: { it: 'Persone', en: 'People' },
        pc_you: { it: 'Tu', en: 'You' },
        pc_typing: { it: 'sta scrivendo...', en: 'is typing...' },
        pc_typing_named: { it: '{name} sta scrivendo...', en: '{name} is typing...' },
        pc_deleted: { it: 'Messaggio eliminato', en: 'Message deleted' },
        pc_members: { it: '{n} membri', en: '{n} members' },
        pc_member_one: { it: '1 membro', en: '1 member' },
        pc_invite_from: { it: 'Invito da @{name}', en: 'Invite from @{name}' },
        pc_accept: { it: 'Accetta', en: 'Accept' },
        pc_decline: { it: 'Rifiuta', en: 'Decline' },
        pc_block: { it: 'Blocca', en: 'Block' },
        pc_unblock: { it: 'Sblocca', en: 'Unblock' },
        pc_first_message: { it: 'Scrivi il primo messaggio a {name}.', en: 'Write the first message to {name}.' },
        pc_no_messages: { it: 'Ancora nessun messaggio.', en: 'No messages yet.' },
        pc_request_text: { it: '{name} non è tra i tuoi amici e vuole scriverti. Se rispondi o accetti, la chat passa tra le tue conversazioni.', en: '{name} is not in your friends and wants to message you. If you reply or accept, the chat moves to your conversations.' },
        pc_awaiting: { it: 'In attesa che {name} accetti la richiesta: per ora puoi mandare pochi messaggi.', en: 'Waiting for {name} to accept your request: for now you can only send a few messages.' },
        pc_blocked_note: { it: 'Hai bloccato questa persona. Sbloccala dai dettagli per scriverle.', en: 'You blocked this person. Unblock them from the details to write.' },
        pc_cant_write: { it: 'Non puoi scrivere in questa chat.', en: 'You cannot write in this chat.' },
        pc_admins_only: { it: 'In questo gruppo scrivono solo gli amministratori.', en: 'Only admins can write in this group.' },
        pc_history: { it: 'Stai guardando messaggi vecchi.', en: 'You are viewing older messages.' },
        pc_history_back: { it: 'Torna al presente', en: 'Back to now' },
        pc_search_chat: { it: 'Cerca in questa chat', en: 'Search this chat' },
        pc_search_placeholder: { it: 'Cerca nei messaggi...', en: 'Search messages...' },
        pc_search_none: { it: 'Nessun messaggio trovato.', en: 'No messages found.' },
        pc_search_hint: { it: 'Scrivi almeno due lettere.', en: 'Type at least two letters.' },
        pc_details: { it: 'Dettagli', en: 'Details' },
        pc_copy: { it: 'Copia testo', en: 'Copy text' },
        pc_forward: { it: 'Inoltra', en: 'Forward' },
        pc_pin: { it: 'Fissa', en: 'Pin' },
        pc_unpin: { it: 'Togli dai fissati', en: 'Unpin' },
        pc_save: { it: 'Salva', en: 'Save' },
        pc_unsave: { it: 'Togli dai salvati', en: 'Remove from saved' },
        pc_edit: { it: 'Modifica', en: 'Edit' },
        pc_delete_me: { it: 'Elimina per me', en: 'Delete for me' },
        pc_delete_all: { it: 'Elimina per tutti', en: 'Delete for everyone' },
        pc_delete: { it: 'Elimina', en: 'Delete' },
        pc_delete_title: { it: 'Eliminare il messaggio?', en: 'Delete this message?' },
        pc_delete_all_text: { it: 'Sparirà per tutti i partecipanti. Non si può annullare.', en: 'It will disappear for everyone in the chat. This cannot be undone.' },
        pc_delete_me_text: { it: 'Sparirà solo per te. Gli altri continueranno a vederlo.', en: 'It will disappear only for you. The others will still see it.' },
        pc_saved_done: { it: 'Messaggio salvato.', en: 'Message saved.' },
        pc_unsaved_done: { it: 'Tolto dai salvati.', en: 'Removed from saved.' },
        pc_pinned_done: { it: 'Messaggio fissato.', en: 'Message pinned.' },
        pc_unpinned_done: { it: 'Messaggio tolto dai fissati.', en: 'Message unpinned.' },
        pc_pinned_label: { it: 'Fissato', en: 'Pinned' },
        pc_uploading: { it: 'Caricamento di {n} file...', en: 'Uploading {n} files...' },
        pc_uploading_one: { it: 'Caricamento del file...', en: 'Uploading file...' },
        pc_menu_saved: { it: 'Messaggi salvati', en: 'Saved messages' },
        pc_menu_privacy: { it: 'Privacy', en: 'Privacy' },
        pc_menu_alerts: { it: 'Avvisi e notifiche', en: 'Alerts and notifications' },
        pc_menu_friends: { it: 'Amici e utenti bloccati', en: 'Friends and blocked users' },
        pc_menu_policy: { it: 'Regolamento della chat', en: 'Chat policy' },
        pc_newchat_title: { it: 'Nuova chat', en: 'New chat' },
        pc_newchat_placeholder: { it: 'Cerca per nome utente...', en: 'Search by username...' },
        pc_newchat_empty: { it: 'Nessuno trovato.', en: 'Nobody found.' },
        pc_newchat_friends_empty: { it: 'Non hai ancora amici. Cerca qualcuno per nome utente.', en: 'You have no friends yet. Search for someone by username.' },
        pc_friend: { it: 'Amico', en: 'Friend' },
        pc_newgroup_title: { it: 'Nuovo gruppo', en: 'New group' },
        pc_group_name: { it: 'Nome del gruppo', en: 'Group name' },
        pc_group_desc: { it: 'Descrizione (facoltativa)', en: 'Description (optional)' },
        pc_group_pick: { it: 'Chi inviti? Puoi invitare solo i tuoi amici.', en: 'Who do you invite? You can only invite your friends.' },
        pc_group_no_friends: { it: 'Per creare un gruppo ti serve almeno un amico.', en: 'You need at least one friend to create a group.' },
        pc_group_create: { it: 'Crea gruppo', en: 'Create group' },
        pc_group_created: { it: 'Gruppo creato.', en: 'Group created.' },
        pc_forward_title: { it: 'Inoltra a...', en: 'Forward to...' },
        pc_forward_hint: { it: 'Scegli fino a 5 chat.', en: 'Pick up to 5 chats.' },
        pc_forward_done: { it: 'Messaggio inoltrato.', en: 'Message forwarded.' },
        pc_saved_title: { it: 'Messaggi salvati', en: 'Saved messages' },
        pc_saved_empty: { it: 'Non hai ancora salvato messaggi. Tieni premuto (o clic destro) su un messaggio e scegli «Salva».', en: 'You have not saved any messages yet. Long-press (or right-click) a message and pick "Save".' },
        pc_saved_off: { it: 'I messaggi salvati non sono ancora attivi su questo server.', en: 'Saved messages are not available on this server yet.' },
        pc_profile: { it: 'Profilo', en: 'Profile' },
        pc_add_friend: { it: 'Aggiungi agli amici', en: 'Add friend' },
        pc_request_sent: { it: 'Richiesta inviata', en: 'Request sent' },
        pc_nickname: { it: 'Soprannome', en: 'Nickname' },
        pc_nickname_hint: { it: 'Lo vedi solo tu.', en: 'Only you can see it.' },
        pc_nickname_set: { it: 'Imposta un soprannome', en: 'Set a nickname' },
        pc_mute: { it: 'Silenzia', en: 'Mute' },
        pc_unmute: { it: 'Riattiva notifiche', en: 'Unmute' },
        pc_mute_1h: { it: 'Per 1 ora', en: 'For 1 hour' },
        pc_mute_8h: { it: 'Per 8 ore', en: 'For 8 hours' },
        pc_mute_1d: { it: 'Per 1 giorno', en: 'For 1 day' },
        pc_mute_forever: { it: 'Finché non le riattivo', en: 'Until I turn them back on' },
        pc_muted_until: { it: 'Silenziata fino alle {time}', en: 'Muted until {time}' },
        pc_muted: { it: 'Silenziata', en: 'Muted' },
        pc_pin_chat: { it: 'Fissa in cima', en: 'Pin to top' },
        pc_unpin_chat: { it: 'Togli dalla cima', en: 'Unpin from top' },
        pc_archive: { it: 'Archivia', en: 'Archive' },
        pc_unarchive: { it: 'Togli dall\'archivio', en: 'Unarchive' },
        pc_mark_unread: { it: 'Segna come non letta', en: 'Mark as unread' },
        pc_clear: { it: 'Svuota conversazione', en: 'Clear conversation' },
        pc_clear_title: { it: 'Svuotare la conversazione?', en: 'Clear this conversation?' },
        pc_clear_text: { it: 'I messaggi spariscono solo per te; l\'altra persona continua a vederli. Non si può annullare.', en: 'The messages disappear only for you; the other person still sees them. This cannot be undone.' },
        pc_block_title: { it: 'Bloccare {name}?', en: 'Block {name}?' },
        pc_block_text: { it: 'Non potrà più scriverti né mandarti richieste, e l\'amicizia verrà rimossa. Non verrà avvisato.', en: 'They will no longer be able to message you or send requests, and the friendship will be removed. They will not be notified.' },
        pc_report: { it: 'Segnala', en: 'Report' },
        pc_report_title: { it: 'Segnalare questa persona', en: 'Report this person' },
        pc_report_text: { it: 'Lo staff non legge le chat private. Per segnalare qualcuno apri un ticket e racconta cosa è successo: puoi allegare tu i messaggi che vuoi mostrare. Intanto puoi bloccare la persona.', en: 'The staff does not read private chats. To report someone, open a ticket and explain what happened: you can attach the messages you want to show. Meanwhile you can block the person.' },
        pc_report_ticket: { it: 'Apri un ticket', en: 'Open a ticket' },
        pc_media: { it: 'Media', en: 'Media' },
        pc_files: { it: 'File', en: 'Files' },
        pc_links: { it: 'Link', en: 'Links' },
        pc_pinned_messages: { it: 'Messaggi fissati', en: 'Pinned messages' },
        pc_nothing: { it: 'Niente qui.', en: 'Nothing here.' },
        pc_group_members: { it: 'Membri', en: 'Members' },
        pc_group_invited: { it: 'invitato', en: 'invited' },
        pc_group_invite: { it: 'Invita amici', en: 'Invite friends' },
        pc_group_invite_none: { it: 'Tutti i tuoi amici sono già nel gruppo.', en: 'All your friends are already in the group.' },
        pc_group_invited_done: { it: 'Inviti mandati.', en: 'Invitations sent.' },
        pc_group_edit: { it: 'Modifica gruppo', en: 'Edit group' },
        pc_group_photo: { it: 'Cambia immagine', en: 'Change picture' },
        pc_group_perms: { it: 'Permessi', en: 'Permissions' },
        pc_group_perm_invite: { it: 'Tutti possono invitare', en: 'Everyone can invite' },
        pc_group_perm_write: { it: 'Tutti possono scrivere', en: 'Everyone can write' },
        pc_group_perm_edit: { it: 'Tutti possono modificare nome e immagine', en: 'Everyone can edit name and picture' },
        pc_group_leave: { it: 'Esci dal gruppo', en: 'Leave group' },
        pc_group_leave_title: { it: 'Uscire dal gruppo?', en: 'Leave the group?' },
        pc_group_leave_text: { it: 'Non riceverai più i messaggi. Per rientrare servirà un nuovo invito.', en: 'You will stop receiving messages. You will need a new invite to come back.' },
        pc_group_leave_owner: { it: 'Sei il proprietario: uscendo, il gruppo passa all\'amministratore o al membro che c\'è da più tempo.', en: 'You are the owner: if you leave, the group goes to the admin or member who has been here the longest.' },
        pc_group_promote: { it: 'Rendi admin', en: 'Make admin' },
        pc_group_demote: { it: 'Togli admin', en: 'Remove admin' },
        pc_group_transfer: { it: 'Cedi la proprietà', en: 'Transfer ownership' },
        pc_group_transfer_text: { it: '{name} diventerà il proprietario del gruppo e tu resterai amministratore.', en: '{name} will become the group owner and you will stay as an admin.' },
        pc_group_remove: { it: 'Rimuovi dal gruppo', en: 'Remove from group' },
        pc_group_remove_text: { it: 'Rimuovere {name} dal gruppo?', en: 'Remove {name} from the group?' },
        pc_group_cancel_invite: { it: 'Annulla invito', en: 'Cancel invite' },
        pc_role_owner: { it: 'Proprietario', en: 'Owner' },
        pc_role_admin: { it: 'Admin', en: 'Admin' },
        pc_sys_create: { it: 'Gruppo creato', en: 'Group created' },
        pc_sys_join: { it: '@{username} è entrato nel gruppo', en: '@{username} joined the group' },
        pc_sys_leave: { it: '@{username} ha lasciato il gruppo', en: '@{username} left the group' },
        pc_sys_invite: { it: '@{inviter} ha invitato @{invitee}', en: '@{inviter} invited @{invitee}' },
        pc_sys_remove: { it: '@{actor} ha rimosso @{target}', en: '@{actor} removed @{target}' },
        pc_sys_rename: { it: '@{username} ha rinominato il gruppo in «{new_name}»', en: '@{username} renamed the group to "{new_name}"' },
        pc_sys_avatar: { it: '@{username} ha cambiato l\'immagine del gruppo', en: '@{username} changed the group picture' },
        pc_sys_promote: { it: '@{actor} ha reso admin @{target}', en: '@{actor} made @{target} an admin' },
        pc_sys_demote: { it: '@{actor} ha tolto il ruolo di admin a @{target}', en: '@{actor} removed @{target} as admin' },
        pc_sys_owner: { it: '@{target} è il nuovo proprietario del gruppo', en: '@{target} is the new group owner' }
    });

    K.setCustomEmojis(cfg.emojis);

    const $ = (id) => document.getElementById(id);
    const els = {
        app: $('pcApp'), list: $('pcList'), filters: $('pcFilters'), search: $('pcSearch'),
        welcome: $('pcWelcome'), conversation: $('pcConversation'), messages: $('pcMessages'), composer: $('pcComposer'),
        headAvatar: $('pcHeadAvatar'), headName: $('pcHeadName'), headStatus: $('pcHeadStatus'), headActions: $('pcHeadActions'),
        request: $('pcRequest'), pinned: $('pcPinned'), chatSearch: $('pcChatSearch'), banner: $('pcBanner'),
        details: $('pcDetails'), detailsBackdrop: $('pcDetailsBackdrop')
    };

    const state = {
        privates: [], groups: [], invites: [],
        counters: { chats: 0, requests: 0, invites: 0 },
        features: {},
        filter: 'all', query: '', people: [],
        active: null,            // { kind, id, recipient? }
        openToken: 0,
        detached: false, reachedStart: false, loadingOlder: false,
        otherRead: null, lastMarked: 0,
        pending: new Map(),
        typing: new Map(),       // chiave chat → { name, until }
        details: null, detailsOpen: false
    };

    // ── Piccoli aiuti ──────────────────────────────────────────────────────

    const keyOf = (kind, id) => (kind === 'group' ? 'g' : 'p') + id;
    const findChat = (kind, id) => (kind === 'group' ? state.groups : state.privates).find((c) => Number(c.id) === Number(id)) || null;
    const activeChat = () => (state.active && state.active.id ? findChat(state.active.kind, state.active.id) : null);
    const isActive = (kind, id) => !!state.active && state.active.kind === kind && Number(state.active.id) === Number(id);

    function chatTitle(chat) {
        return chat.kind === 'group' ? chat.name : (chat.other_nickname || chat.other_display_name || chat.other_username);
    }

    /** Avatar di una chat: la foto della persona, l'immagine del gruppo o un'icona. */
    function avatarNode(chat, size) {
        const wrap = h('span', { class: 'pc-avatar' + (size ? ' pc-avatar--' + size : '') });
        if (chat.kind === 'group') {
            if (chat.avatar_url) wrap.appendChild(h('img', { src: K.safeUrl(chat.avatar_url, ''), alt: '', loading: 'lazy' }));
            else wrap.appendChild(h('span', { class: 'pc-avatar__icon' }, icon('fa-solid fa-user-group')));
        } else {
            wrap.appendChild(h('img', { src: K.avatarUrl(chat.other_user_id), alt: '', loading: 'lazy' }));
            if (chat.is_online) wrap.appendChild(h('span', { class: 'ck-dot is-online' }));
        }
        return wrap;
    }

    function attachmentLabel(type) {
        return t(type === 'image' ? 'att_photo' : type === 'video' ? 'att_video' : type === 'audio' ? 'att_audio' : 'att_file');
    }

    function systemText(system, fallback) {
        if (!system || !system.event) return fallback || '';
        const key = 'pc_sys_' + system.event;
        const text = t(key, system);
        return text === key ? (fallback || '') : text;
    }

    function previewOf(chat) {
        const typing = state.typing.get(chat.key);
        if (typing && typing.until > Date.now()) {
            return { text: chat.kind === 'group' ? t('pc_typing_named', { name: typing.name }) : t('pc_typing'), typing: true };
        }
        if (!chat.last_message_id) return { text: '' };

        if (chat.kind === 'group') {
            if (chat.last_message_type === 'system') return { text: systemText(chat.last_message_system, chat.last_message_body) };
            const who = Number(chat.last_message_sender_id) === Number(me.id) ? t('pc_you') : '@' + (chat.last_message_sender_username || '');
            const body = chat.last_message_type === 'gif' ? 'GIF'
                : (chat.last_message_type === 'media' && !chat.last_message_body ? attachmentLabel('file') : chat.last_message_body);
            return { text: who + ': ' + (body || '') };
        }

        const mine = Number(chat.last_message_sender_id) === Number(me.id);
        let body;
        if (chat.last_message_type === 'deleted') body = t('pc_deleted');
        else if (chat.last_message_type === 'gif') body = 'GIF';
        else if (chat.last_message_type === 'media') body = attachmentLabel(chat.last_message_attachment_type) + (chat.last_message_text ? ' · ' + chat.last_message_text : '');
        else body = chat.last_message_text || '';
        return { text: (mine ? t('pc_you') + ': ' : '') + body, mine };
    }

    // ── Elenco delle chat ──────────────────────────────────────────────────

    async function loadList() {
        try {
            const data = await K.api('/api/chat/list.php');
            state.privates = (data.privates || []).map((c) => Object.assign(c, { kind: 'private', id: c.conversation_id, key: 'p' + c.conversation_id }));
            state.groups = (data.groups || []).map((c) => Object.assign(c, { kind: 'group', id: c.chat_id, key: 'g' + c.chat_id }));
            state.invites = data.invites || [];
            state.counters = data.counters || state.counters;
            state.features = data.features || {};
            paintFilters();
            paintList();
            if (state.active && state.active.id) paintHeader();
            return true;
        } catch (error) {
            if (!state.privates.length && !state.groups.length) {
                K.clear(els.list);
                els.list.appendChild(h('p', { class: 'pc-list__note' }, error.message));
            }
            return false;
        }
    }

    const refreshList = K.debounce(loadList, 500);

    function visibleChats() {
        const all = [...state.privates, ...state.groups];
        const query = state.query.toLowerCase();
        let list;

        switch (state.filter) {
            case 'unread':
                list = all.filter((c) => !c.is_archived && !c.is_request && c.unread_count > 0);
                break;
            case 'groups':
                list = state.groups.filter((c) => !c.is_archived);
                break;
            case 'requests':
                list = state.privates.filter((c) => c.is_request && !c.is_empty);
                break;
            case 'archived':
                list = all.filter((c) => c.is_archived);
                break;
            default:
                list = all.filter((c) => !c.is_archived && !c.is_request);
        }

        // Una conversazione senza messaggi (appena svuotata) compare solo se è quella aperta.
        list = list.filter((c) => c.kind === 'group' || !c.is_empty || isActive(c.kind, c.id));

        if (query) {
            list = all.filter((c) => {
                const haystack = (chatTitle(c) + ' ' + (c.other_username || '')).toLowerCase();
                return haystack.includes(query) && (c.kind === 'group' || !c.is_empty);
            });
        }

        return list.sort((a, b) => (Number(b.is_pinned || 0) - Number(a.is_pinned || 0)) || (b.last_ts - a.last_ts));
    }

    function paintFilters() {
        const unread = [...state.privates, ...state.groups].filter((c) => !c.is_archived && !c.is_request && c.unread_count > 0).length;
        const requests = state.privates.filter((c) => c.is_request && !c.is_empty).length;
        const archived = [...state.privates, ...state.groups].filter((c) => c.is_archived).length;

        const chips = [
            ['all', t('pc_all'), 0],
            ['unread', t('pc_unread'), unread],
            ['groups', t('pc_groups'), state.invites.length],
            requests || state.filter === 'requests' ? ['requests', t('pc_requests'), requests] : null,
            archived || state.filter === 'archived' ? ['archived', t('pc_archived'), 0] : null
        ].filter(Boolean);

        K.clear(els.filters);
        chips.forEach(([key, label, count]) => {
            els.filters.appendChild(h('button', {
                type: 'button', role: 'tab', class: 'pc-chip' + (state.filter === key ? ' is-active' : ''),
                'aria-selected': state.filter === key ? 'true' : 'false',
                onClick: () => {
                    state.filter = key;
                    paintFilters();
                    paintList();
                }
            }, label, count > 0 ? h('span', { class: 'ck-count' }, String(count)) : null));
        });
    }

    function chatRow(chat) {
        const preview = previewOf(chat);
        const active = isActive(chat.kind, chat.id);
        const row = h('button', {
            type: 'button',
            class: 'pc-row' + (active ? ' is-active' : '') + (chat.unread_count > 0 && !active ? ' is-unread' : ''),
            dataset: { key: chat.key },
            onClick: () => openChat(chat.kind, chat.id)
        },
            avatarNode(chat),
            h('span', { class: 'pc-row__body' },
                h('span', { class: 'pc-row__top' },
                    h('strong', null, chatTitle(chat), chat.kind === 'private' && chat.other_premium ? K.premiumGem() : null),
                    h('time', null, K.listTime(chat.last_ts))),
                h('span', { class: 'pc-row__bottom' },
                    h('span', { class: 'pc-row__preview' + (preview.typing ? ' is-typing' : '') }, preview.text),
                    h('span', { class: 'pc-row__flags' },
                        chat.is_pinned ? icon('fa-solid fa-thumbtack') : null,
                        chat.is_muted ? icon('fa-solid fa-bell-slash') : null,
                        chat.unread_count > 0 && !active ? h('span', { class: 'ck-count' + (chat.is_muted ? ' is-muted' : '') }, chat.unread_count > 99 ? '99+' : String(chat.unread_count)) : null))));

        row.addEventListener('contextmenu', (event) => {
            event.preventDefault();
            openChatMenu(chat, { x: event.clientX, y: event.clientY });
        });
        return row;
    }

    function inviteRow(invite) {
        return h('div', { class: 'pc-invite' },
            avatarNode({ kind: 'group', avatar_url: invite.chat_avatar }),
            h('div', { class: 'pc-invite__body' },
                h('strong', null, invite.chat_name),
                h('small', null, t('pc_invite_from', { name: invite.inviter_username })),
                h('div', { class: 'pc-invite__actions' },
                    h('button', { type: 'button', class: 'ck-btn ck-btn--primary ck-btn--sm', onClick: () => answerInvite(invite.chat_id, true) }, t('pc_accept')),
                    h('button', { type: 'button', class: 'ck-btn ck-btn--ghost ck-btn--sm', onClick: () => answerInvite(invite.chat_id, false) }, t('pc_decline')))));
    }

    function paintList() {
        const chats = visibleChats();
        K.clear(els.list);

        if (!state.query && (state.filter === 'all' || state.filter === 'groups')) {
            state.invites.forEach((invite) => els.list.appendChild(inviteRow(invite)));
        }

        chats.forEach((chat) => els.list.appendChild(chatRow(chat)));

        if (state.query && state.people.length) {
            els.list.appendChild(h('div', { class: 'pc-list__label' }, t('pc_people')));
            state.people.forEach((user) => {
                els.list.appendChild(h('button', { type: 'button', class: 'pc-row', onClick: () => openWithUser(user) },
                    h('span', { class: 'pc-avatar' }, h('img', { src: K.avatarUrl(user.id), alt: '', loading: 'lazy' })),
                    h('span', { class: 'pc-row__body' },
                        h('span', { class: 'pc-row__top' }, h('strong', null, user.display_name || user.username, user.is_premium ? K.premiumGem() : null)),
                        h('span', { class: 'pc-row__bottom' }, h('span', { class: 'pc-row__preview' }, '@' + user.username + (user.is_friend ? ' · ' + t('pc_friend') : ''))))));
            });
        }

        if (!els.list.children.length) {
            const everything = state.privates.length + state.groups.length + state.invites.length;
            els.list.appendChild(h('p', { class: 'pc-list__note' }, t(everything === 0 && !state.query ? 'pc_list_empty_all' : 'pc_list_empty')));
        }
    }

    const searchPeople = K.debounce(async () => {
        const query = state.query;
        if (query.length < 2) {
            state.people = [];
            paintList();
            return;
        }
        try {
            const data = await K.api('/api/chat/search.php?q=' + encodeURIComponent(query));
            if (query !== state.query) return;
            const known = new Set(state.privates.filter((c) => !c.is_empty).map((c) => Number(c.other_user_id)));
            state.people = (data.results || []).filter((u) => !known.has(Number(u.id)) && u.can_message !== false);
            paintList();
        } catch (_) {
            state.people = [];
        }
    }, 350);

    async function answerInvite(chatId, accept) {
        try {
            await K.api('/api/chat/' + (accept ? 'accept_invite.php' : 'decline_invite.php'), { body: { chat_id: chatId } });
            await loadList();
            if (accept) openChat('group', chatId);
            if (RT) RT.refreshCounters();
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    // ── Messaggi ───────────────────────────────────────────────────────────

    const list = new K.MessageList({
        container: els.messages,
        authors: 'never',
        onAction: handleAction,
        onReachTop: loadOlder,
        onBottom: () => {
            list.clearUnreadDivider();
            markRead();
        }
    });

    function toItem(message) {
        const mine = Number(message.sender_id) === Number(me.id);
        const isGroup = message.kind === 'group';
        const system = message.message_type === 'system' ? systemText(message.system, message.body) : null;
        const chat = activeChat();
        const reply = message.reply ? {
            id: message.reply.id,
            name: Number(message.reply.sender_id) === Number(me.id) ? t('pc_you')
                : (!isGroup && chat ? chatTitle(chat) : (message.reply.username || '')),
            text: message.reply.deleted ? '' : (message.reply.text || (message.reply.type === 'gif' ? 'GIF' : message.reply.type === 'media' ? attachmentLabel('file') : ''))
        } : null;

        let status = null;
        if (mine && !isGroup && !message.is_deleted) {
            status = state.otherRead !== null && Number(message.id) <= Number(state.otherRead) ? 'read' : 'sent';
        }

        return {
            id: message.id,
            ts: Number(message.ts) || Math.floor(Date.now() / 1000),
            mine,
            author: {
                id: message.sender_id,
                name: message.sender_display_name || message.sender_username,
                username: message.sender_username,
                url: '/u/' + encodeURIComponent(message.sender_username || ''),
                avatar: K.avatarUrl(message.sender_id),
                premium: !!message.sender_premium
            },
            system,
            deleted: !!message.is_deleted,
            text: message.is_deleted ? '' : (message.body || ''),
            gif: !message.is_deleted && message.message_type === 'gif' && message.media_url
                ? { url: message.media_url, preview: message.media_url, title: message.media_title || 'GIF' } : null,
            attachments: message.attachments || [],
            reply,
            forwarded: !!message.forwarded,
            edited: !!message.is_edited,
            pinned: !!message.is_pinned,
            favorite: !!message.is_favorite,
            reactions: (message.reactions || []).map((r) => ({ emoji: r.reaction, count: r.count, mine: !!r.user_reacted, title: r.usernames })),
            status,
            kind: message.kind,
            type: message.message_type
        };
    }

    function messagesUrl(active, query) {
        return active.kind === 'group'
            ? '/api/chat/messages.php?chat_id=' + active.id + '&noread=1' + (query ? '&' + query : '')
            : '/api/chat/get_messages.php?conversation_id=' + active.id + '&noread=1' + (query ? '&' + query : '');
    }

    function applyMeta(data) {
        if ('other_last_read_id' in data) state.otherRead = data.other_last_read_id;
    }

    function emptyConversation() {
        const chat = activeChat();
        const name = chat ? chatTitle(chat) : (state.active.recipient ? (state.active.recipient.display_name || state.active.recipient.username) : '');
        return K.emptyState('fa-regular fa-paper-plane', name, state.active.kind === 'private' ? t('pc_first_message', { name }) : t('pc_no_messages'));
    }

    async function openChat(kind, id, options = {}) {
        let chat = findChat(kind, id);
        if (!chat) {
            await loadList();
            chat = findChat(kind, id);
            if (!chat) return;
        }

        const token = ++state.openToken;
        state.active = { kind, id: Number(id) };
        state.detached = false;
        state.reachedStart = false;
        state.otherRead = kind === 'private' ? chat.other_last_read_id : null;
        state.lastMarked = 0;
        state.details = null;

        showConversation();
        paintHeader();
        paintList();
        paintStrips();
        toggleChatSearch(false);
        composer.setDraftKey(chat.key);
        list.options.authors = kind === 'group' ? 'others' : 'never';
        list.showPlaceholder(K.spinner());
        if (RT) RT.setActiveChat({ kind, id: Number(id) });
        history.replaceState(history.state, '', '?' + (kind === 'group' ? 'g' : 'c') + '=' + id);
        if (state.detailsOpen) paintDetails();

        try {
            const query = options.focusId ? 'around=' + options.focusId : '';
            const data = await K.api(messagesUrl(state.active, query));
            if (token !== state.openToken) return;

            applyMeta(data);
            const items = (data.messages || []).map(toItem);
            state.detached = !!options.focusId && !!data.has_newer;
            state.reachedStart = !data.has_more;

            if (!items.length) {
                list.showPlaceholder(emptyConversation());
            } else {
                list.reset(items, {
                    unreadAfter: !options.focusId && chat.unread_count > 0 ? Number(data.last_read_id || 0) : null,
                    focusId: options.focusId || null
                });
            }
            paintBanner();
            markRead();
            loadDetails(token);
        } catch (error) {
            if (token !== state.openToken) return;
            list.showPlaceholder(K.emptyState('fa-solid fa-triangle-exclamation', error.message, ''));
        }
        composer.focus();
    }

    /** Conversazione non ancora nata: si apre al primo messaggio. */
    function openDraft(user) {
        state.openToken += 1;
        state.active = { kind: 'private', id: 0, recipient: user };
        state.detached = false;
        state.otherRead = null;
        state.details = null;
        showConversation();
        paintHeader();
        paintList();
        paintStrips();
        composer.setDraftKey('u' + user.id);
        list.options.authors = 'never';
        list.showPlaceholder(emptyConversation());
        if (RT) RT.setActiveChat(null);
        history.replaceState(history.state, '', '?user_id=' + user.id);
        closeDetails();
        composer.focus();
    }

    function openWithUser(user) {
        const existing = state.privates.find((c) => Number(c.other_user_id) === Number(user.id));
        if (existing) openChat('private', existing.id);
        else openDraft(user);
        els.search.value = '';
        state.query = '';
        state.people = [];
        paintList();
    }

    function showConversation() {
        els.welcome.hidden = true;
        els.conversation.hidden = false;
        if (!els.app.classList.contains('is-chat-open')) {
            els.app.classList.add('is-chat-open');
            if (K.isMobile()) history.pushState({ pcChat: true }, '');
        }
    }

    function closeConversation(fromHistory) {
        els.app.classList.remove('is-chat-open');
        if (K.isMobile()) {
            // Su telefono si torna alla lista: la chat resta dov'è, ma non
            // è più «quella sullo schermo» e i nuovi messaggi tornano ad avvisare.
            if (RT) RT.setActiveChat(null);
            closeDetails();
            if (!fromHistory && history.state && history.state.pcChat) history.back();
        }
    }

    async function loadOlder() {
        const active = state.active;
        if (!active || !active.id || state.loadingOlder || state.reachedStart || !list.firstId()) return;
        state.loadingOlder = true;
        const token = state.openToken;
        try {
            const data = await K.api(messagesUrl(active, 'before=' + list.firstId()));
            if (token !== state.openToken) return;
            state.reachedStart = !data.has_more;
            list.upsert((data.messages || []).map(toItem), { older: true });
        } catch (_) {
            /* si riprova al prossimo scorrimento */
        } finally {
            state.loadingOlder = false;
        }
    }

    async function fetchNew() {
        const active = state.active;
        if (!active || !active.id || state.detached) return;
        const token = state.openToken;
        try {
            const data = await K.api(messagesUrl(active, 'after=' + list.lastId()));
            if (token !== state.openToken) return;
            applyMeta(data);
            const items = (data.messages || []).map(toItem);
            if (items.length && list.size === 0) list.showPlaceholder(null);
            list.upsert(items);
            markRead();
        } catch (_) {
            /* il prossimo evento ci riprova */
        }
    }

    async function fetchIds(ids) {
        const active = state.active;
        if (!active || !active.id || !ids.length) return;
        const token = state.openToken;
        try {
            const data = await K.api(messagesUrl(active, 'ids=' + ids.join(',')));
            if (token !== state.openToken) return;
            const items = (data.messages || []).filter((m) => list.get(m.id)).map(toItem);
            list.upsert(items);
            (data.gone || []).forEach((id) => list.remove(id));
        } catch (_) {
            /* resta la versione che c'è */
        }
    }

    async function jumpTo(id) {
        if (list.reveal(id)) return;
        const active = state.active;
        if (!active || !active.id) return;
        try {
            const data = await K.api(messagesUrl(active, 'around=' + id));
            const messages = data.messages || [];
            if (!messages.some((m) => Number(m.id) === Number(id))) {
                K.toast(t('msg_reply_gone'), 'error');
                return;
            }
            applyMeta(data);
            state.detached = !!data.has_newer;
            state.reachedStart = !data.has_more;
            list.reset(messages.map(toItem), { focusId: id });
            paintBanner();
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    function paintBanner() {
        K.clear(els.banner);
        els.banner.hidden = !state.detached;
        if (!state.detached) return;
        els.banner.append(
            h('span', null, icon('fa-solid fa-clock-rotate-left'), ' ', t('pc_history')),
            h('button', { type: 'button', class: 'ck-btn ck-btn--primary ck-btn--sm', onClick: () => openChat(state.active.kind, state.active.id) }, t('pc_history_back')));
    }

    /** Segna come letto solo quello che è davvero sullo schermo. */
    async function markRead() {
        const active = state.active;
        if (!active || !active.id || state.detached) return;
        if (document.visibilityState !== 'visible' || !list.atBottom()) return;
        if (K.isMobile() && !els.app.classList.contains('is-chat-open')) return;

        const last = list.lastId();
        const chat = activeChat();
        if (!last || last <= state.lastMarked) return;
        if (chat && chat.unread_count === 0 && last <= Number(chat.last_read_id || 0)) {
            state.lastMarked = last;
            return;
        }
        state.lastMarked = last;
        try {
            const body = active.kind === 'group' ? { chat_id: active.id, message_id: last } : { conversation_id: active.id, message_id: last };
            await K.api('/api/chat/mark_read.php', { body });
            if (chat) {
                chat.unread_count = 0;
                chat.last_read_id = last;
                paintFilters();
                paintList();
            }
        } catch (_) {
            state.lastMarked = 0;
        }
    }

    function refreshTicks() {
        if (!state.active || state.active.kind !== 'private') return;
        let changed = false;
        list.all().forEach((item) => {
            if (!item.mine || item.deleted || String(item.id).startsWith('tmp-') || !item.status || item.status === 'sending' || item.status === 'error') return;
            const next = state.otherRead !== null && Number(item.id) <= Number(state.otherRead) ? 'read' : 'sent';
            if (item.status !== next) {
                item.status = next;
                changed = true;
            }
        });
        if (changed) list.render();
    }

    // ── Testata e strisce ──────────────────────────────────────────────────

    function paintHeader() {
        const active = state.active;
        if (!active) return;
        const chat = activeChat();
        K.clear(els.headAvatar);
        K.clear(els.headActions);

        if (!chat) {
            const user = active.recipient;
            els.headAvatar.appendChild(h('img', { src: K.avatarUrl(user.id), alt: '' }));
            els.headName.textContent = user.display_name || user.username;
            els.headStatus.textContent = '@' + user.username;
            return;
        }

        const avatar = avatarNode(chat);
        while (avatar.firstChild) els.headAvatar.appendChild(avatar.firstChild);
        K.clear(els.headName);
        els.headName.append(chatTitle(chat));
        if (chat.kind === 'private' && chat.other_premium) els.headName.appendChild(K.premiumGem());
        paintStatus();

        els.headActions.append(
            h('button', { type: 'button', class: 'ck-icon-btn', title: t('pc_search_chat'), 'aria-label': t('pc_search_chat'), onClick: () => toggleChatSearch() }, icon('fa-solid fa-magnifying-glass')),
            h('button', { type: 'button', class: 'ck-icon-btn', title: t('pc_details'), 'aria-label': t('pc_details'), onClick: () => toggleDetails() }, icon('fa-solid fa-circle-info')),
            h('button', { type: 'button', class: 'ck-icon-btn', title: t('act_more'), 'aria-label': t('act_more'), onClick: (event) => openChatMenu(chat, event.currentTarget, true) }, icon('fa-solid fa-ellipsis-vertical')));
    }

    function paintStatus() {
        const chat = activeChat();
        if (!chat) return;
        const typing = state.typing.get(chat.key);
        els.headStatus.classList.remove('is-typing', 'is-online');

        if (typing && typing.until > Date.now()) {
            els.headStatus.textContent = chat.kind === 'group' ? t('pc_typing_named', { name: typing.name }) : t('pc_typing');
            els.headStatus.classList.add('is-typing');
            return;
        }
        if (chat.kind === 'group') {
            els.headStatus.textContent = chat.members_count === 1 ? t('pc_member_one') : t('pc_members', { n: chat.members_count });
            return;
        }
        if (chat.is_blocked) {
            els.headStatus.textContent = '@' + chat.other_username;
            return;
        }
        els.headStatus.textContent = K.presenceLabel(chat.is_online, chat.last_seen_ts);
        els.headStatus.classList.toggle('is-online', !!chat.is_online);
    }

    function paintStrips() {
        const chat = activeChat();
        K.clear(els.request);
        els.request.hidden = true;
        composer.setDisabled(false);

        if (!chat) {
            paintPinned([]);
            return;
        }

        if (chat.kind === 'private') {
            const name = chatTitle(chat);
            if (chat.is_blocked && state.details && state.details.other && state.details.other.is_blocked_by_me) {
                composer.setDisabled(true, t('pc_blocked_note'));
            } else if (chat.is_request) {
                els.request.hidden = false;
                els.request.append(
                    h('p', null, t('pc_request_text', { name })),
                    h('div', { class: 'pc-strip__actions' },
                        h('button', { type: 'button', class: 'ck-btn ck-btn--primary ck-btn--sm', onClick: () => conversationAction('accept') }, t('pc_accept')),
                        h('button', { type: 'button', class: 'ck-btn ck-btn--danger ck-btn--sm', onClick: () => blockUser(chat.other_user_id, name) }, t('pc_block'))));
            } else if (chat.awaiting_accept) {
                els.request.hidden = false;
                els.request.appendChild(h('p', null, t('pc_awaiting', { name })));
            }
        } else if (state.details && state.details.settings && state.details.settings.message_permission === 'admins_only'
            && !['owner', 'admin'].includes(state.details.my_membership.role)) {
            composer.setDisabled(true, t('pc_admins_only'));
        }

        paintPinned(state.details && state.details.pinned_messages ? state.details.pinned_messages : []);
    }

    function paintPinned(pinned) {
        K.clear(els.pinned);
        els.pinned.hidden = !pinned.length;
        if (!pinned.length) return;
        const first = pinned[0];
        els.pinned.appendChild(h('button', { type: 'button', class: 'pc-pinned', onClick: () => jumpTo(first.message_id) },
            icon('fa-solid fa-thumbtack'),
            h('span', null,
                h('strong', null, t('pc_pinned_label') + (pinned.length > 1 ? ' · ' + pinned.length : '')),
                h('em', null, first.message || attachmentLabel('file')))));
    }

    // ── Invio ──────────────────────────────────────────────────────────────

    const composer = new K.Composer({
        root: els.composer,
        maxLength: cfg.maxLength,
        maxFiles: cfg.maxFiles,
        attachments: true,
        gif: true,
        onSend: send,
        onTyping: (typing) => {
            const active = state.active;
            if (!active || !active.id) return;
            const body = active.kind === 'group' ? { chat_id: active.id, typing } : { conversation_id: active.id, typing };
            K.api('/api/chat/typing.php', { body }).catch(() => {});
        },
        onEditLast: () => {
            const mine = list.all().filter((m) => canEdit(m));
            if (mine.length) composer.setEdit(mine[mine.length - 1]);
        }
    });
    K.dropZone(els.conversation, (files) => composer.addFiles(files));

    function canEdit(item) {
        return item.mine && !item.deleted && !item.system && item.type !== 'gif' && !String(item.id).startsWith('tmp-')
            && (Math.floor(Date.now() / 1000) - item.ts) < cfg.editWindow && (item.text || item.type === 'media');
    }

    function pendingItem(nonce, payload) {
        const count = payload.files.length;
        return {
            id: 'tmp-' + nonce,
            ts: Math.floor(Date.now() / 1000),
            mine: true,
            author: { id: me.id, name: me.displayName, username: me.username, avatar: K.avatarUrl(me.id) },
            text: payload.text || (count ? (count === 1 ? t('pc_uploading_one') : t('pc_uploading', { n: count })) : ''),
            gif: payload.gif ? { url: payload.gif.url, preview: payload.gif.preview_url || payload.gif.url, title: payload.gif.title || 'GIF' } : null,
            reply: payload.replyTo ? { id: payload.replyTo.id, name: payload.replyTo.name, text: payload.replyTo.text } : null,
            attachments: [],
            reactions: [],
            status: 'sending'
        };
    }

    async function send(payload) {
        const active = state.active;
        if (!active) return;

        if (payload.editId) {
            try {
                const data = active.kind === 'group'
                    ? await K.api('/api/chat/edit_message.php', { body: { message_id: payload.editId, content: payload.text } })
                    : await K.api('/api/chat/manage_message.php', { body: { action: 'edit', message_id: payload.editId, content: payload.text } });
                if (data.message) list.upsert([toItem(data.message)]);
                refreshList();
            } catch (error) {
                K.toast(error.message, 'error');
                throw error;
            }
            return;
        }

        if (state.detached) await openChat(active.kind, active.id);

        const nonce = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
        const job = {
            kind: active.kind,
            id: active.id,
            recipient: active.recipient ? active.recipient.id : 0,
            text: payload.text,
            replyId: payload.replyTo ? payload.replyTo.id : 0,
            gif: payload.gif,
            files: payload.files
        };

        if (list.size === 0) list.showPlaceholder(null);
        const temp = pendingItem(nonce, payload);
        list.upsert([temp]);
        await deliver(temp.id, job);
    }

    async function deliver(tempId, job) {
        const token = state.openToken;
        try {
            let data;
            if (job.files.length) {
                const form = new FormData();
                if (job.kind === 'group') form.append('chat_id', job.id);
                else if (job.id) form.append('conversation_id', job.id);
                else form.append('recipient_id', job.recipient);
                form.append('message', job.text);
                if (job.replyId) form.append('reply_to_id', job.replyId);
                job.files.forEach((file) => form.append('files[]', file, file.name));
                data = await K.upload('/api/chat/upload_media.php', form, (fraction) => composer.setProgress(fraction));
            } else {
                const body = { message: job.text, message_type: job.gif ? 'gif' : 'text' };
                if (job.gif) {
                    body.media_url = job.gif.url;
                    body.media_title = job.gif.title || 'GIF';
                }
                if (job.kind === 'group') {
                    body.chat_id = job.id;
                    body.reply_to_message_id = job.replyId || null;
                } else {
                    if (job.id) body.conversation_id = job.id;
                    else body.recipient_id = job.recipient;
                    body.reply_to_id = job.replyId || null;
                }
                data = await K.api('/api/chat/send_message.php', { body });
            }

            state.pending.delete(tempId);

            // Primo messaggio a qualcuno: la conversazione adesso esiste.
            if (job.kind === 'private' && !job.id && data.conversation_id) {
                await loadList();
                if (token === state.openToken) {
                    state.openToken += 1;
                    state.active = { kind: 'private', id: Number(data.conversation_id) };
                    state.otherRead = null;
                    const chat = activeChat();
                    composer.draftKey = chat ? chat.key : null;
                    if (RT) RT.setActiveChat({ kind: 'private', id: Number(data.conversation_id) });
                    history.replaceState(history.state, '', '?c=' + data.conversation_id);
                    paintHeader();
                    paintStrips();
                    loadDetails(state.openToken);
                }
            }

            if (isActive(job.kind, job.id || data.conversation_id)) {
                list.swap(tempId, toItem(data.message));
                state.lastMarked = Math.max(state.lastMarked, Number(data.message.id));
            }
            refreshList();
        } catch (error) {
            const refused = error.status >= 400 && error.status < 500;
            if (refused) {
                state.pending.delete(tempId);
                list.remove(tempId);
                if (list.size === 0 && state.active) list.showPlaceholder(emptyConversation());
                K.toast(error.message, 'error');
                throw error;
            }
            state.pending.set(tempId, job);
            list.patch(tempId, { status: 'error', errorText: error.message });
            const keep = new Error(error.message);
            keep.restore = false;
            throw keep;
        }
    }

    // ── Azioni sui messaggi ────────────────────────────────────────────────

    async function react(item, emoji) {
        const active = state.active;
        try {
            const data = active.kind === 'group'
                ? await K.api('/api/chat/group_react.php', { body: { message_id: item.id, reaction: emoji } })
                : await K.api('/api/chat/manage_reaction.php', { body: { message_id: item.id, reaction: emoji, action: 'toggle' } });
            list.patch(item.id, { reactions: (data.reactions || []).map((r) => ({ emoji: r.reaction, count: r.count, mine: !!r.user_reacted, title: r.usernames })) });
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    function handleAction(action, item, event, element) {
        switch (action) {
            case 'reply':
                composer.setReply(replyTarget(item));
                break;
            case 'react-pick':
                K.reactionPicker(element, (emoji) => react(item, emoji));
                break;
            case 'react':
                react(item, element.dataset.emoji);
                break;
            case 'user':
                if (window.CripsumUserCard && Number(item.author.id) !== Number(me.id)) window.CripsumUserCard.open({ id: item.author.id });
                else window.location.href = item.author.url;
                break;
            case 'jump':
                jumpTo(item.id);
                break;
            case 'retry': {
                const job = state.pending.get(item.id);
                if (!job) return list.remove(item.id);
                list.patch(item.id, { status: 'sending', errorText: '' });
                deliver(item.id, job).catch(() => {});
                break;
            }
            case 'discard':
                state.pending.delete(item.id);
                list.remove(item.id);
                break;
            case 'more':
                openMessageMenu(item, event, element);
                break;
        }
    }

    /** Nella citazione di una chat a due basta «Tu» o il nome dell'altro. */
    function replyTarget(item) {
        const copy = Object.assign({}, item);
        copy.author = Object.assign({}, item.author, { name: item.mine ? t('pc_you') : item.author.name });
        if (!copy.text && item.attachments && item.attachments.length) copy.text = attachmentLabel(item.attachments[0].file_type);
        return copy;
    }

    function openMessageMenu(item, event, element) {
        if (item.deleted || item.system) return;
        const active = state.active;
        const isGroup = active.kind === 'group';
        const anchor = event && event.type === 'contextmenu' ? { x: event.clientX, y: event.clientY } : element;
        const staff = isGroup && state.details && state.details.my_membership && ['owner', 'admin'].includes(state.details.my_membership.role);

        const items = [
            { label: t('act_reply'), icon: 'fa-solid fa-reply', onSelect: () => composer.setReply(replyTarget(item)) },
            { label: t('act_react'), icon: 'fa-regular fa-face-smile', onSelect: () => K.reactionPicker(anchor, (emoji) => react(item, emoji)) },
            item.text ? { label: t('pc_copy'), icon: 'fa-regular fa-copy', onSelect: () => K.copy(item.text) } : null,
            (item.type === 'text' || item.type === 'gif') ? { label: t('pc_forward'), icon: 'fa-solid fa-share', onSelect: () => openForward(item) } : null
        ];

        if (!isGroup) {
            if (state.features.pins) {
                items.push({ label: t(item.pinned ? 'pc_unpin' : 'pc_pin'), icon: 'fa-solid fa-thumbtack', onSelect: () => messageAction(item, 'toggle_pin') });
            }
            if (state.features.favorites) {
                items.push({ label: t(item.favorite ? 'pc_unsave' : 'pc_save'), icon: item.favorite ? 'fa-solid fa-star' : 'fa-regular fa-star', onSelect: () => messageAction(item, 'toggle_favorite') });
            }
        }
        if (canEdit(item)) items.push({ label: t('pc_edit'), icon: 'fa-solid fa-pen', onSelect: () => composer.setEdit(item) });

        items.push({ divider: true });
        if (!isGroup) {
            items.push({ label: t('pc_delete_me'), icon: 'fa-regular fa-trash-can', danger: true, onSelect: () => deleteMessage(item, false) });
            if (item.mine) items.push({ label: t('pc_delete_all'), icon: 'fa-solid fa-trash-can', danger: true, onSelect: () => deleteMessage(item, true) });
        } else if (item.mine || staff) {
            items.push({ label: t('pc_delete'), icon: 'fa-regular fa-trash-can', danger: true, onSelect: () => deleteMessage(item, true) });
        } else {
            items.pop();
        }

        K.menu(anchor, items);
    }

    async function messageAction(item, action) {
        try {
            const data = await K.api('/api/chat/manage_message.php', { body: { action, message_id: item.id } });
            if (action === 'toggle_pin') {
                list.patch(item.id, { pinned: !!data.pinned });
                K.toast(t(data.pinned ? 'pc_pinned_done' : 'pc_unpinned_done'));
                loadDetails(state.openToken);
            } else {
                list.patch(item.id, { favorite: !!data.favorited });
                K.toast(t(data.favorited ? 'pc_saved_done' : 'pc_unsaved_done'));
            }
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    async function deleteMessage(item, everyone) {
        const ok = await K.confirm({
            title: t('pc_delete_title'),
            text: t(everyone ? 'pc_delete_all_text' : 'pc_delete_me_text'),
            confirmLabel: t('pc_delete'),
            danger: true,
            icon: 'fa-regular fa-trash-can'
        });
        if (!ok) return;

        try {
            if (state.active.kind === 'group') {
                await K.api('/api/chat/delete_message.php', { body: { message_id: item.id } });
                list.remove(item.id);
            } else {
                const data = await K.api('/api/chat/manage_message.php', { body: { action: everyone ? 'delete_for_all' : 'delete_for_self', message_id: item.id } });
                if (everyone && data.message) list.upsert([toItem(data.message)]);
                else list.remove(item.id);
            }
            if (list.size === 0) list.showPlaceholder(emptyConversation());
            refreshList();
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    // ── Azioni sulla chat ──────────────────────────────────────────────────

    async function conversationAction(action, extra = {}) {
        const chat = activeChat();
        if (!chat) return false;
        return chatAction(chat, action, extra);
    }

    async function chatAction(chat, action, extra = {}) {
        try {
            if (chat.kind === 'group') {
                if (action === 'mute') await K.api('/api/chat/mute.php', { body: { chat_id: chat.id, duration: extra.seconds } });
                else if (action === 'archive') await K.api('/api/chat/archive.php', { body: { chat_id: chat.id, action: extra.archived ? 'archive' : 'unarchive' } });
            } else {
                await K.api('/api/chat/conversation.php', { body: Object.assign({ conversation_id: chat.id, action }, extra) });
            }
            await loadList();
            if (isActive(chat.kind, chat.id)) {
                if (action === 'clear') {
                    list.showPlaceholder(emptyConversation());
                    closeDetails();
                }
                paintStrips();
                loadDetails(state.openToken);
            }
            if (RT) RT.refreshCounters();
            return true;
        } catch (error) {
            K.toast(error.message, 'error');
            return false;
        }
    }

    function muteItems(chat) {
        if (chat.is_muted) return [{ label: t('pc_unmute'), icon: 'fa-regular fa-bell', onSelect: () => chatAction(chat, 'mute', { seconds: 0 }) }];
        const timed = chat.kind === 'group' || state.features.timed_mute;
        return [
            timed ? { label: t('pc_mute') + ' · ' + t('pc_mute_1h'), icon: 'fa-regular fa-bell-slash', onSelect: () => chatAction(chat, 'mute', { seconds: 3600 }) } : null,
            timed ? { label: t('pc_mute') + ' · ' + t('pc_mute_8h'), icon: 'fa-regular fa-bell-slash', onSelect: () => chatAction(chat, 'mute', { seconds: 28800 }) } : null,
            timed ? { label: t('pc_mute') + ' · ' + t('pc_mute_1d'), icon: 'fa-regular fa-bell-slash', onSelect: () => chatAction(chat, 'mute', { seconds: 86400 }) } : null,
            { label: t('pc_mute') + ' · ' + t('pc_mute_forever'), icon: 'fa-solid fa-bell-slash', onSelect: () => chatAction(chat, 'mute', { seconds: -1 }) }
        ];
    }

    function openChatMenu(chat, anchor, alignRight) {
        const items = [...muteItems(chat)];
        if (chat.kind === 'private') {
            if (state.features.conversation_pins) {
                items.push({ label: t(chat.is_pinned ? 'pc_unpin_chat' : 'pc_pin_chat'), icon: 'fa-solid fa-thumbtack', onSelect: () => chatAction(chat, 'pin', { pinned: !chat.is_pinned }) });
            }
            if (!chat.is_empty && chat.unread_count === 0) {
                items.push({ label: t('pc_mark_unread'), icon: 'fa-regular fa-envelope', onSelect: () => chatAction(chat, 'unread') });
            }
        }
        items.push({ label: t(chat.is_archived ? 'pc_unarchive' : 'pc_archive'), icon: 'fa-solid fa-box-archive', onSelect: () => chatAction(chat, 'archive', { archived: !chat.is_archived }) });

        if (chat.kind === 'private') {
            items.push({ divider: true });
            if (state.features.clear) {
                items.push({ label: t('pc_clear'), icon: 'fa-solid fa-eraser', danger: true, onSelect: () => clearConversation(chat) });
            }
            items.push({ label: t('pc_block'), icon: 'fa-solid fa-ban', danger: true, onSelect: () => blockUser(chat.other_user_id, chatTitle(chat)) });
        }
        K.menu(anchor, items, { alignRight: !!alignRight, header: chatTitle(chat) });
    }

    async function clearConversation(chat) {
        const ok = await K.confirm({ title: t('pc_clear_title'), text: t('pc_clear_text'), confirmLabel: t('pc_clear'), danger: true, icon: 'fa-solid fa-eraser' });
        if (ok) chatAction(chat, 'clear');
    }

    async function blockUser(userId, name) {
        const ok = await K.confirm({ title: t('pc_block_title', { name }), text: t('pc_block_text'), confirmLabel: t('pc_block'), danger: true, icon: 'fa-solid fa-ban' });
        if (!ok) return;
        try {
            await K.api('/api/social/block_user.php', { body: { blocked_id: userId } });
            await loadList();
            paintStrips();
            loadDetails(state.openToken);
            if (RT) RT.refreshCounters();
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    async function unblockUser(userId) {
        try {
            await K.api('/api/social/unblock_user.php', { body: { blocked_id: userId } });
            await loadList();
            loadDetails(state.openToken);
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    function reportUser(userId, name) {
        K.dialog({
            title: t('pc_report_title'),
            icon: 'fa-regular fa-flag',
            body: t('pc_report_text'),
            actions: [
                { label: t('cancel'), value: null, kind: 'ghost' },
                { label: t('pc_block'), kind: 'danger', onClick: () => { blockUser(userId, name); } },
                { label: t('pc_report_ticket'), kind: 'primary', onClick: () => { window.open(cfg.supportUrl, '_blank', 'noopener'); } }
            ]
        });
    }

    // ── Ricerca nella conversazione ────────────────────────────────────────

    function toggleChatSearch(open) {
        const show = open === undefined ? els.chatSearch.hidden : open;
        els.chatSearch.hidden = !show;
        K.clear(els.chatSearch);
        if (!show || !state.active || !state.active.id) return;

        const results = h('div', { class: 'pc-searchbar__results ck-scroll' }, h('p', { class: 'pc-list__note' }, t('pc_search_hint')));
        const input = h('input', { type: 'search', class: 'ck-input', placeholder: t('pc_search_placeholder'), maxlength: '80', autocomplete: 'off' });

        const run = K.debounce(async () => {
            const query = input.value.trim();
            K.clear(results);
            if (query.length < 2) {
                results.appendChild(h('p', { class: 'pc-list__note' }, t('pc_search_hint')));
                return;
            }
            results.appendChild(K.spinner());
            try {
                const active = state.active;
                const param = active.kind === 'group' ? 'chat_id=' + active.id : 'conversation_id=' + active.id;
                const data = await K.api('/api/chat/search_messages.php?' + param + '&query=' + encodeURIComponent(query));
                K.clear(results);
                if (!(data.results || []).length) {
                    results.appendChild(h('p', { class: 'pc-list__note' }, t('pc_search_none')));
                    return;
                }
                data.results.forEach((row) => {
                    results.appendChild(h('button', {
                        type: 'button', class: 'pc-result',
                        onClick: () => {
                            toggleChatSearch(false);
                            jumpTo(row.id);
                        }
                    },
                        h('strong', null, Number(row.sender_id) === Number(me.id) ? t('pc_you') : '@' + row.sender_username, h('time', null, K.dayLabel(row.ts) + ' ' + K.formatTime(row.ts))),
                        h('span', null, row.text)));
                });
            } catch (error) {
                K.clear(results);
                results.appendChild(h('p', { class: 'pc-list__note' }, error.message));
            }
        }, 350);

        input.addEventListener('input', run);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') toggleChatSearch(false);
        });
        els.chatSearch.append(
            h('div', { class: 'pc-searchbar__bar' }, icon('fa-solid fa-magnifying-glass'), input,
                h('button', { type: 'button', class: 'ck-icon-btn', 'aria-label': t('close'), onClick: () => toggleChatSearch(false) }, icon('fa-solid fa-xmark'))),
            results);
        input.focus();
    }

    // ── Dettagli ───────────────────────────────────────────────────────────

    async function loadDetails(token) {
        const active = state.active;
        if (!active || !active.id) return;
        try {
            const data = active.kind === 'group'
                ? await K.api('/api/chat/get.php?chat_id=' + active.id)
                : await K.api('/api/chat/get_chat_details.php?conversation_id=' + active.id);
            if (token !== state.openToken) return;
            state.details = data;
            paintStrips();
            if (state.detailsOpen) paintDetails();
        } catch (_) {
            /* i dettagli si ricaricano alla prossima apertura */
        }
    }

    function toggleDetails(open) {
        const show = open === undefined ? !state.detailsOpen : open;
        if (!show) return closeDetails();
        state.detailsOpen = true;
        els.details.hidden = false;
        els.detailsBackdrop.hidden = false;
        els.app.classList.add('has-details');
        paintDetails();
        if (!state.details) loadDetails(state.openToken);
    }

    function closeDetails() {
        state.detailsOpen = false;
        els.details.hidden = true;
        els.detailsBackdrop.hidden = true;
        els.app.classList.remove('has-details');
    }

    function section(title, ...children) {
        return h('section', { class: 'pc-section' }, title ? h('h3', null, title) : null, children);
    }

    function actionRow(iconName, label, onClick, danger) {
        return h('button', { type: 'button', class: 'pc-action' + (danger ? ' is-danger' : ''), onClick }, icon(iconName), h('span', null, label));
    }

    function paintDetails() {
        const chat = activeChat();
        K.clear(els.details);
        els.details.appendChild(h('header', { class: 'pc-details__head' },
            h('h2', null, t('pc_details')),
            h('button', { type: 'button', class: 'ck-icon-btn', 'aria-label': t('close'), onClick: closeDetails }, icon('fa-solid fa-xmark'))));
        const body = h('div', { class: 'pc-details__body ck-scroll' });
        els.details.appendChild(body);

        if (!chat || !state.details) {
            body.appendChild(K.spinner());
            return;
        }
        if (chat.kind === 'group') paintGroupDetails(body, chat, state.details);
        else paintPrivateDetails(body, chat, state.details);
    }

    function paintPrivateDetails(body, chat, details) {
        const other = details.other;
        const name = chatTitle(chat);

        body.appendChild(h('div', { class: 'pc-profile' },
            avatarNode(chat, 'xl'),
            h('strong', null, name, other.is_premium ? K.premiumGem() : null),
            h('small', null, '@' + other.username),
            h('span', { class: 'pc-profile__status' + (other.is_online ? ' is-online' : '') }, other.is_blocked_by_me ? '' : K.presenceLabel(other.is_online, other.last_seen_ts)),
            h('div', { class: 'pc-profile__actions' },
                h('a', { class: 'ck-btn ck-btn--ghost ck-btn--sm', href: '/u/' + encodeURIComponent(other.username) }, icon('fa-regular fa-user'), t('pc_profile')),
                other.can_send_friend_request
                    ? h('button', {
                        type: 'button', class: 'ck-btn ck-btn--primary ck-btn--sm',
                        onClick: async (event) => {
                            const button = event.currentTarget;
                            button.disabled = true;
                            try {
                                await K.api('/api/social/send_friend_request.php', { body: { receiver_id: other.id } });
                                button.textContent = t('pc_request_sent');
                            } catch (error) {
                                button.disabled = false;
                                K.toast(error.message, 'error');
                            }
                        }
                    }, icon('fa-solid fa-user-plus'), t('pc_add_friend'))
                    : (other.friend_request_sent ? h('span', { class: 'pc-tag' }, t('pc_request_sent')) : (other.is_friend ? h('span', { class: 'pc-tag' }, icon('fa-solid fa-user-check'), ' ', t('pc_friend')) : null)))));

        const actions = [];
        if (state.features.requests !== undefined) {
            actions.push(actionRow('fa-solid fa-signature', other.nickname ? t('pc_nickname') + ': ' + other.nickname : t('pc_nickname_set'), async () => {
                const value = await K.prompt({ title: t('pc_nickname'), label: t('pc_nickname_hint'), value: other.nickname || '', maxLength: 40, icon: 'fa-solid fa-signature' });
                if (value !== null) chatAction(chat, 'nickname', { nickname: value });
            }));
        }
        actions.push(actionRow(chat.is_muted ? 'fa-regular fa-bell' : 'fa-regular fa-bell-slash',
            chat.is_muted ? (chat.muted_until_ts ? t('pc_muted_until', { time: K.formatTime(chat.muted_until_ts) }) + ' · ' + t('pc_unmute') : t('pc_unmute')) : t('pc_mute'),
            (event) => K.menu(event.currentTarget, muteItems(chat))));
        if (state.features.conversation_pins) {
            actions.push(actionRow('fa-solid fa-thumbtack', t(chat.is_pinned ? 'pc_unpin_chat' : 'pc_pin_chat'), () => chatAction(chat, 'pin', { pinned: !chat.is_pinned })));
        }
        actions.push(actionRow('fa-solid fa-box-archive', t(chat.is_archived ? 'pc_unarchive' : 'pc_archive'), () => chatAction(chat, 'archive', { archived: !chat.is_archived })));
        body.appendChild(section(null, actions));

        if ((details.pinned_messages || []).length) {
            body.appendChild(section(t('pc_pinned_messages'), details.pinned_messages.map((pin) =>
                h('button', { type: 'button', class: 'pc-result', onClick: () => { if (K.isMobile()) closeDetails(); jumpTo(pin.message_id); } },
                    h('strong', null, '@' + pin.sender_username, h('time', null, K.dayLabel(pin.ts))),
                    h('span', null, pin.message || attachmentLabel('file'))))));
        }

        body.appendChild(galleryTabs(details.gallery || {}));

        const danger = [];
        if (state.features.clear) danger.push(actionRow('fa-solid fa-eraser', t('pc_clear'), () => clearConversation(chat), true));
        danger.push(other.is_blocked_by_me
            ? actionRow('fa-solid fa-unlock', t('pc_unblock'), () => unblockUser(other.id))
            : actionRow('fa-solid fa-ban', t('pc_block'), () => blockUser(other.id, name), true));
        danger.push(actionRow('fa-regular fa-flag', t('pc_report'), () => reportUser(other.id, name), true));
        body.appendChild(section(null, danger));
    }

    function galleryTabs(gallery) {
        const media = gallery.media || [];
        const files = gallery.files || [];
        const links = gallery.links || [];
        const content = h('div', { class: 'pc-gallery__content' });
        const tabs = h('div', { class: 'pc-tabs' });

        const show = (key) => {
            tabs.querySelectorAll('button').forEach((button) => button.classList.toggle('is-active', button.dataset.tab === key));
            K.clear(content);
            if (key === 'media') {
                if (!media.length) return content.appendChild(h('p', { class: 'pc-list__note' }, t('pc_nothing')));
                const grid = h('div', { class: 'pc-gallery' });
                media.forEach((file, index) => {
                    const cell = h('button', { type: 'button', class: 'pc-gallery__item', onClick: () => K.lightbox(media.map((m) => ({ type: m.file_type === 'video' ? 'video' : 'image', src: m.file_path, name: m.file_name })), index) });
                    if (file.file_type === 'video') cell.append(h('video', { src: K.safeUrl(file.file_path, '') + '#t=0.1', preload: 'metadata', muted: true }), h('span', null, icon('fa-solid fa-play')));
                    else cell.appendChild(h('img', { src: K.safeUrl(file.file_path, ''), alt: '', loading: 'lazy' }));
                    grid.appendChild(cell);
                });
                content.appendChild(grid);
            } else if (key === 'files') {
                if (!files.length) return content.appendChild(h('p', { class: 'pc-list__note' }, t('pc_nothing')));
                files.forEach((file) => content.appendChild(h('a', { class: 'ck-file pc-file', href: K.safeUrl(file.file_path), download: file.file_name || '', target: '_blank', rel: 'noopener' },
                    h('span', { class: 'ck-file__icon' }, icon(file.file_type === 'audio' ? 'fa-solid fa-music' : 'fa-solid fa-file-arrow-down')),
                    h('span', { class: 'ck-file__body' }, h('strong', null, file.file_name), h('small', null, K.fileSize(file.file_size) + ' · ' + K.dayLabel(file.ts))))));
            } else {
                if (!links.length) return content.appendChild(h('p', { class: 'pc-list__note' }, t('pc_nothing')));
                links.forEach((link) => content.appendChild(h('a', { class: 'pc-linkrow', href: K.safeUrl(link.url), target: '_blank', rel: 'noopener noreferrer nofollow' },
                    icon('fa-solid fa-link'), h('span', null, link.url))));
            }
        };

        [['media', t('pc_media'), media.length], ['files', t('pc_files'), files.length], ['links', t('pc_links'), links.length]].forEach(([key, label, count]) => {
            tabs.appendChild(h('button', { type: 'button', dataset: { tab: key }, onClick: () => show(key) }, label, count ? h('small', null, String(count)) : null));
        });
        const wrap = h('section', { class: 'pc-section' }, tabs, content);
        show('media');
        return wrap;
    }

    function paintGroupDetails(body, chat, details) {
        const mine = details.my_membership;
        const isOwner = mine.role === 'owner';
        const isStaff = isOwner || mine.role === 'admin';

        const photoInput = h('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp,image/gif', hidden: true });
        photoInput.addEventListener('change', async () => {
            if (!photoInput.files.length) return;
            const form = new FormData();
            form.append('chat_id', chat.id);
            form.append('avatar', photoInput.files[0]);
            try {
                await K.api('/api/chat/update_avatar.php', { form });
                await loadList();
                loadDetails(state.openToken);
            } catch (error) {
                K.toast(error.message, 'error');
            }
        });

        body.appendChild(h('div', { class: 'pc-profile' },
            avatarNode(chat, 'xl'),
            h('strong', null, details.chat.name),
            details.chat.description ? h('p', { class: 'pc-profile__desc' }, details.chat.description) : null,
            h('small', null, chat.members_count === 1 ? t('pc_member_one') : t('pc_members', { n: chat.members_count })),
            mine.can_edit_info ? h('div', { class: 'pc-profile__actions' },
                h('button', { type: 'button', class: 'ck-btn ck-btn--ghost ck-btn--sm', onClick: () => editGroup(chat, details) }, icon('fa-solid fa-pen'), t('pc_group_edit')),
                h('button', { type: 'button', class: 'ck-btn ck-btn--ghost ck-btn--sm', onClick: () => photoInput.click() }, icon('fa-solid fa-camera'), t('pc_group_photo')),
                photoInput) : null));

        body.appendChild(section(null, [
            actionRow(chat.is_muted ? 'fa-regular fa-bell' : 'fa-regular fa-bell-slash',
                chat.is_muted ? t('pc_unmute') : t('pc_mute'), (event) => K.menu(event.currentTarget, muteItems(chat))),
            actionRow('fa-solid fa-box-archive', t(chat.is_archived ? 'pc_unarchive' : 'pc_archive'), () => chatAction(chat, 'archive', { archived: !chat.is_archived }))
        ]));

        const canInvite = isStaff || details.settings.invite_permission === 'everyone';
        const members = h('div', { class: 'pc-members' });
        (details.members || []).forEach((member) => {
            const isMe = Number(member.user_id) === Number(me.id);
            const row = h('div', { class: 'pc-member' },
                h('span', { class: 'pc-avatar pc-avatar--sm' }, h('img', { src: K.avatarUrl(member.user_id), alt: '', loading: 'lazy' }), member.is_online ? h('span', { class: 'ck-dot is-online' }) : null),
                h('span', { class: 'pc-member__body' },
                    h('strong', null, member.display_name, isMe ? ' (' + t('pc_you').toLowerCase() + ')' : ''),
                    h('small', null, '@' + member.username)),
                member.status === 'invited' ? h('span', { class: 'pc-tag' }, t('pc_group_invited'))
                    : (member.role === 'owner' ? h('span', { class: 'ck-role ck-role--owner' }, t('pc_role_owner'))
                        : (member.role === 'admin' ? h('span', { class: 'ck-role ck-role--admin' }, t('pc_role_admin')) : null)));

            const canManage = !isMe && (isOwner || (mine.role === 'admin' && member.role === 'member'));
            if (canManage) {
                row.appendChild(h('button', {
                    type: 'button', class: 'ck-icon-btn', 'aria-label': t('act_more'),
                    onClick: (event) => memberMenu(event.currentTarget, chat, member, isOwner)
                }, icon('fa-solid fa-ellipsis')));
            }
            members.appendChild(row);
        });
        body.appendChild(section(t('pc_group_members'),
            canInvite ? actionRow('fa-solid fa-user-plus', t('pc_group_invite'), () => inviteFriends(chat, details)) : null,
            members));

        if (isOwner) {
            const settings = details.settings;
            const toggle = (label, key, onValue, offValue) => {
                const input = h('input', { type: 'checkbox', checked: settings[key] === onValue });
                input.addEventListener('change', async () => {
                    try {
                        await K.api('/api/chat/update_group.php', { body: { chat_id: chat.id, [key]: input.checked ? onValue : offValue } });
                        loadDetails(state.openToken);
                    } catch (error) {
                        input.checked = !input.checked;
                        K.toast(error.message, 'error');
                    }
                });
                return h('label', { class: 'ck-switch pc-switch' }, h('span', null, h('strong', null, label)), input, h('i'));
            };
            body.appendChild(section(t('pc_group_perms'),
                toggle(t('pc_group_perm_write'), 'message_permission', 'members', 'admins_only'),
                toggle(t('pc_group_perm_invite'), 'invite_permission', 'everyone', 'owner_admins'),
                toggle(t('pc_group_perm_edit'), 'edit_info_permission', 'everyone', 'owner_admins')));
        }

        body.appendChild(section(null, actionRow('fa-solid fa-right-from-bracket', t('pc_group_leave'), async () => {
            const ok = await K.confirm({
                title: t('pc_group_leave_title'),
                text: t('pc_group_leave_text') + (isOwner && (details.members || []).filter((m) => m.status === 'active').length > 1 ? ' ' + t('pc_group_leave_owner') : ''),
                confirmLabel: t('pc_group_leave'), danger: true, icon: 'fa-solid fa-right-from-bracket'
            });
            if (!ok) return;
            try {
                await K.api('/api/chat/leave_chat.php', { body: { chat_id: chat.id } });
                state.active = null;
                closeDetails();
                els.conversation.hidden = true;
                els.welcome.hidden = false;
                els.app.classList.remove('is-chat-open');
                if (RT) RT.setActiveChat(null);
                history.replaceState(history.state, '', window.location.pathname);
                await loadList();
            } catch (error) {
                K.toast(error.message, 'error');
            }
        }, true)));
    }

    function memberMenu(anchor, chat, member, isOwner) {
        const call = async (endpoint, body) => {
            try {
                await K.api('/api/chat/' + endpoint, { body: Object.assign({ chat_id: chat.id, member_id: member.user_id }, body || {}) });
                loadDetails(state.openToken);
                refreshList();
            } catch (error) {
                K.toast(error.message, 'error');
            }
        };
        const name = member.display_name;
        const items = [];
        if (member.status === 'invited') {
            items.push({
                label: t('pc_group_cancel_invite'), icon: 'fa-solid fa-user-xmark', danger: true,
                onSelect: () => call('cancel_invite.php', { invitee_id: member.user_id })
            });
        } else {
            if (isOwner && member.role === 'member') items.push({ label: t('pc_group_promote'), icon: 'fa-solid fa-user-shield', onSelect: () => call('promote_admin.php') });
            if (isOwner && member.role === 'admin') items.push({ label: t('pc_group_demote'), icon: 'fa-solid fa-user', onSelect: () => call('demote_admin.php') });
            if (isOwner) {
                items.push({
                    label: t('pc_group_transfer'), icon: 'fa-solid fa-crown',
                    onSelect: async () => {
                        const ok = await K.confirm({ title: t('pc_group_transfer'), text: t('pc_group_transfer_text', { name }), confirmLabel: t('pc_group_transfer'), icon: 'fa-solid fa-crown' });
                        if (ok) call('transfer_owner.php');
                    }
                });
            }
            items.push({ divider: true });
            items.push({
                label: t('pc_group_remove'), icon: 'fa-solid fa-user-minus', danger: true,
                onSelect: async () => {
                    const ok = await K.confirm({ title: t('pc_group_remove'), text: t('pc_group_remove_text', { name }), confirmLabel: t('pc_group_remove'), danger: true, icon: 'fa-solid fa-user-minus' });
                    if (ok) call('remove_member.php');
                }
            });
        }
        K.menu(anchor, items, { alignRight: true, header: name });
    }

    async function editGroup(chat, details) {
        const name = h('input', { class: 'ck-input', type: 'text', maxlength: '60', value: details.chat.name });
        const description = h('textarea', { class: 'ck-input', rows: '3', maxlength: '300' });
        description.value = details.chat.description || '';
        await K.dialog({
            title: t('pc_group_edit'),
            icon: 'fa-solid fa-pen',
            body: h('div', { class: 'ck-field' }, h('label', { class: 'ck-label' }, t('pc_group_name')), name, h('label', { class: 'ck-label' }, t('pc_group_desc')), description),
            actions: [
                { label: t('cancel'), value: null, kind: 'ghost' },
                {
                    label: t('save'), kind: 'primary',
                    onClick: async () => {
                        try {
                            await K.api('/api/chat/update_group.php', { body: { chat_id: chat.id, name: name.value.trim(), description: description.value.trim() } });
                            await loadList();
                            loadDetails(state.openToken);
                            return true;
                        } catch (error) {
                            K.toast(error.message, 'error');
                            return false;
                        }
                    }
                }
            ]
        });
    }

    // ── Finestre: nuova chat, nuovo gruppo, inviti, inoltro, salvati, privacy ──

    function userPickRow(user, right) {
        return h('span', { class: 'pc-pick__row' },
            h('span', { class: 'pc-avatar pc-avatar--sm' }, h('img', { src: K.avatarUrl(user.id), alt: '', loading: 'lazy' }), user.is_online ? h('span', { class: 'ck-dot is-online' }) : null),
            h('span', { class: 'pc-member__body' }, h('strong', null, user.display_name || user.username, user.is_premium ? K.premiumGem() : null), h('small', null, '@' + user.username)),
            right || null);
    }

    function openNewChat() {
        const input = h('input', { type: 'search', class: 'ck-input', placeholder: t('pc_newchat_placeholder'), maxlength: '30', autocomplete: 'off', autofocus: true });
        const results = h('div', { class: 'pc-pick ck-scroll' }, K.spinner());
        let closeDialog = () => {};

        const paint = (users, emptyKey) => {
            K.clear(results);
            if (!users.length) {
                results.appendChild(h('p', { class: 'pc-list__note' }, t(emptyKey)));
                return;
            }
            users.forEach((user) => {
                results.appendChild(h('button', {
                    type: 'button', class: 'pc-pick__item',
                    onClick: () => {
                        closeDialog(null);
                        openWithUser(user);
                    }
                }, userPickRow(user, user.is_friend ? h('span', { class: 'pc-tag' }, t('pc_friend')) : null)));
            });
        };

        const run = K.debounce(async () => {
            const query = input.value.trim();
            try {
                const data = await K.api('/api/chat/search.php?q=' + encodeURIComponent(query.length >= 2 ? query : ''));
                if (query !== input.value.trim()) return;
                paint((data.results || []).filter((u) => u.can_message !== false), query.length >= 2 ? 'pc_newchat_empty' : 'pc_newchat_friends_empty');
            } catch (error) {
                K.clear(results);
                results.appendChild(h('p', { class: 'pc-list__note' }, error.message));
            }
        }, 300);
        input.addEventListener('input', run);

        K.dialog({
            title: t('pc_newchat_title'), icon: 'fa-solid fa-pen-to-square',
            body: h('div', { class: 'ck-field' }, input, results),
            actions: [],
            onOpen: ({ close }) => {
                closeDialog = close;
                run();
            }
        });
    }

    async function openNewGroup(preselectId) {
        const name = h('input', { class: 'ck-input', type: 'text', maxlength: '60', placeholder: t('pc_group_name'), autofocus: true });
        const description = h('textarea', { class: 'ck-input', rows: '2', maxlength: '300', placeholder: t('pc_group_desc') });
        const friends = h('div', { class: 'pc-pick ck-scroll' }, K.spinner());
        const selected = new Set(preselectId ? [Number(preselectId)] : []);

        K.dialog({
            title: t('pc_newgroup_title'), icon: 'fa-solid fa-user-group', wide: true,
            body: h('div', { class: 'ck-field' }, name, description, h('small', { class: 'ck-hint' }, t('pc_group_pick')), friends),
            actions: [
                { label: t('cancel'), value: null, kind: 'ghost' },
                {
                    label: t('pc_group_create'), kind: 'primary',
                    onClick: async () => {
                        try {
                            const data = await K.api('/api/chat/create_group.php', { body: { name: name.value.trim(), description: description.value.trim(), invited_users: [...selected] } });
                            K.toast(t('pc_group_created'), 'success');
                            await loadList();
                            openChat('group', data.chat_id);
                            return true;
                        } catch (error) {
                            K.toast(error.message, 'error');
                            return false;
                        }
                    }
                }
            ]
        });

        try {
            const data = await K.api('/api/social/friends.php');
            const all = (data.data && data.data.all) || [];
            K.clear(friends);
            if (!all.length) {
                friends.appendChild(h('p', { class: 'pc-list__note' }, t('pc_group_no_friends')));
                return;
            }
            all.forEach((user) => {
                const box = h('input', { type: 'checkbox', checked: selected.has(Number(user.id)) });
                box.addEventListener('change', () => (box.checked ? selected.add(Number(user.id)) : selected.delete(Number(user.id))));
                friends.appendChild(h('label', { class: 'pc-pick__item' }, userPickRow(user, box)));
            });
        } catch (error) {
            K.clear(friends);
            friends.appendChild(h('p', { class: 'pc-list__note' }, error.message));
        }
    }

    async function inviteFriends(chat, details) {
        const friends = h('div', { class: 'pc-pick ck-scroll' }, K.spinner());
        const selected = new Set();
        K.dialog({
            title: t('pc_group_invite'), icon: 'fa-solid fa-user-plus',
            body: friends,
            actions: [
                { label: t('cancel'), value: null, kind: 'ghost' },
                {
                    label: t('pc_group_invite'), kind: 'primary',
                    onClick: async () => {
                        let sent = 0;
                        let lastError = '';
                        for (const id of selected) {
                            try {
                                await K.api('/api/chat/invite_user.php', { body: { chat_id: chat.id, invitee_id: id } });
                                sent += 1;
                            } catch (error) {
                                lastError = error.message;
                            }
                        }
                        if (sent) K.toast(t('pc_group_invited_done'), 'success');
                        else if (lastError) K.toast(lastError, 'error');
                        loadDetails(state.openToken);
                        return sent > 0 || selected.size === 0;
                    }
                }
            ]
        });

        try {
            const data = await K.api('/api/social/friends.php');
            const inside = new Set((details.members || []).map((m) => Number(m.user_id)));
            const candidates = ((data.data && data.data.all) || []).filter((u) => !inside.has(Number(u.id)));
            K.clear(friends);
            if (!candidates.length) {
                friends.appendChild(h('p', { class: 'pc-list__note' }, t('pc_group_invite_none')));
                return;
            }
            candidates.forEach((user) => {
                const box = h('input', { type: 'checkbox' });
                box.addEventListener('change', () => (box.checked ? selected.add(Number(user.id)) : selected.delete(Number(user.id))));
                friends.appendChild(h('label', { class: 'pc-pick__item' }, userPickRow(user, box)));
            });
        } catch (error) {
            K.clear(friends);
            friends.appendChild(h('p', { class: 'pc-list__note' }, error.message));
        }
    }

    function openForward(item) {
        const chats = [...state.privates.filter((c) => !c.is_empty && !c.is_blocked && !c.is_request), ...state.groups]
            .sort((a, b) => b.last_ts - a.last_ts);
        const selected = new Map();
        const pick = h('div', { class: 'pc-pick ck-scroll' });
        chats.forEach((chat) => {
            const box = h('input', { type: 'checkbox' });
            box.addEventListener('change', () => {
                if (box.checked && selected.size >= 5) {
                    box.checked = false;
                    return;
                }
                if (box.checked) selected.set(chat.key, { kind: chat.kind, id: chat.id });
                else selected.delete(chat.key);
            });
            pick.appendChild(h('label', { class: 'pc-pick__item' },
                h('span', { class: 'pc-pick__row' }, avatarNode(chat, 'sm'), h('span', { class: 'pc-member__body' }, h('strong', null, chatTitle(chat)), h('small', null, chat.kind === 'group' ? t('pc_groups') : '@' + chat.other_username)), box)));
        });
        if (!chats.length) pick.appendChild(h('p', { class: 'pc-list__note' }, t('pc_list_empty')));

        K.dialog({
            title: t('pc_forward_title'), icon: 'fa-solid fa-share',
            body: h('div', { class: 'ck-field' }, h('small', { class: 'ck-hint' }, t('pc_forward_hint')), pick),
            actions: [
                { label: t('cancel'), value: null, kind: 'ghost' },
                {
                    label: t('pc_forward'), kind: 'primary',
                    onClick: async () => {
                        if (!selected.size) return false;
                        try {
                            await K.api('/api/chat/forward.php', { body: { kind: state.active.kind, message_id: item.id, targets: [...selected.values()] } });
                            K.toast(t('pc_forward_done'), 'success');
                            refreshList();
                            return true;
                        } catch (error) {
                            K.toast(error.message, 'error');
                            return false;
                        }
                    }
                }
            ]
        });
    }

    async function openSaved() {
        const body = h('div', { class: 'pc-pick ck-scroll' }, K.spinner());
        let closeDialog = () => {};
        K.dialog({ title: t('pc_saved_title'), icon: 'fa-solid fa-star', wide: true, body, actions: [], onOpen: ({ close }) => { closeDialog = close; } });
        try {
            const data = await K.api('/api/chat/saved.php');
            K.clear(body);
            if (!data.available) return body.appendChild(h('p', { class: 'pc-list__note' }, t('pc_saved_off')));
            if (!(data.messages || []).length) return body.appendChild(h('p', { class: 'pc-list__note' }, t('pc_saved_empty')));
            data.messages.forEach((row) => {
                const text = row.text || (row.message_type === 'gif' ? 'GIF' : attachmentLabel(row.attachment_type));
                body.appendChild(h('button', {
                    type: 'button', class: 'pc-result',
                    onClick: () => {
                        closeDialog(null);
                        openChat('private', row.conversation_id, { focusId: row.id });
                    }
                },
                    h('strong', null, (Number(row.sender_id) === Number(me.id) ? t('pc_you') : '@' + row.sender_username) + '  →  @' + row.other_username, h('time', null, K.dayLabel(row.ts))),
                    h('span', null, text)));
            });
        } catch (error) {
            K.clear(body);
            body.appendChild(h('p', { class: 'pc-list__note' }, error.message));
        }
    }

    function openPageMenu(anchor) {
        K.menu(anchor, [
            { label: t('pc_menu_saved'), icon: 'fa-regular fa-star', onSelect: openSaved },
            { label: t('pc_menu_privacy'), icon: 'fa-solid fa-user-shield', onSelect: () => window.CripsumUserCard && window.CripsumUserCard.privacy() },
            { label: t('pc_menu_alerts'), icon: 'fa-regular fa-bell', onSelect: () => RT && RT.openSettings() },
            { divider: true },
            { label: t('pc_menu_friends'), icon: 'fa-solid fa-user-group', onSelect: () => { window.location.href = cfg.friendsUrl; } },
            { label: t('pc_menu_policy'), icon: 'fa-solid fa-book-open', onSelect: () => window.open(cfg.policyUrl, '_blank', 'noopener') }
        ], { alignRight: true });
    }

    // ── Tempo reale ────────────────────────────────────────────────────────

    const changed = { ids: new Set(), timer: null };

    function queueIds(id) {
        changed.ids.add(Number(id));
        clearTimeout(changed.timer);
        changed.timer = setTimeout(() => {
            const ids = [...changed.ids];
            changed.ids.clear();
            fetchIds(ids);
        }, 250);
    }

    function setTyping(key, name, on, until) {
        if (on) state.typing.set(key, { name, until: Math.min(Number(until) * 1000 || Date.now() + 6000, Date.now() + 8000) });
        else state.typing.delete(key);
        const refresh = () => {
            const entry = state.typing.get(key);
            if (entry && entry.until <= Date.now()) state.typing.delete(key);
            const row = els.list.querySelector('[data-key="' + key + '"]');
            const chat = [...state.privates, ...state.groups].find((c) => c.key === key);
            if (row && chat) row.replaceWith(chatRow(chat));
            if (state.active && keyOf(state.active.kind, state.active.id) === key) paintStatus();
        };
        refresh();
        if (on) setTimeout(refresh, 8200);
    }

    function onUserEvent(event) {
        switch (event.t) {
            case 'resync':
                loadList();
                if (state.active && state.active.id && !state.detached) fetchNew();
                break;
            case 'pm':
            case 'gm': {
                const kind = event.t === 'gm' ? 'group' : 'private';
                const id = kind === 'group' ? event.g : event.c;
                setTyping(keyOf(kind, id), '', false);
                if (isActive(kind, id)) fetchNew();
                refreshList();
                break;
            }
            case 'pu':
            case 'gu': {
                const kind = event.t === 'gu' ? 'group' : 'private';
                const id = kind === 'group' ? event.g : event.c;
                if (isActive(kind, id)) {
                    queueIds(event.m);
                    if (event.pin) loadDetails(state.openToken);
                }
                refreshList();
                break;
            }
            case 'rd':
                if (event.c && isActive('private', event.c) && Number(event.f) !== Number(me.id)) {
                    state.otherRead = Math.max(Number(state.otherRead) || 0, Number(event.m));
                    refreshTicks();
                } else if (Number(event.f) === Number(me.id)) {
                    refreshList();
                }
                break;
            case 'ty': {
                if (Number(event.f) === Number(me.id)) break;
                const key = event.g ? keyOf('group', event.g) : keyOf('private', event.c);
                setTyping(key, event.n || '', !!event.on, event.until);
                break;
            }
            case 'gx':
                if (event.g && isActive('group', event.g)) loadDetails(state.openToken);
                refreshList();
                break;
            case 'ls':
            case 'sl':
            case 'gi':
                refreshList();
                break;
        }
    }

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

    $('pcNewChat').addEventListener('click', openNewChat);
    $('pcWelcomeNew').addEventListener('click', openNewChat);
    $('pcNewGroup').addEventListener('click', () => openNewGroup());
    $('pcMenu').addEventListener('click', (event) => openPageMenu(event.currentTarget));
    $('pcBack').addEventListener('click', () => closeConversation(false));
    $('pcHeadWho').addEventListener('click', () => {
        if (state.active && state.active.id) toggleDetails();
    });
    els.detailsBackdrop.addEventListener('click', closeDetails);

    els.search.addEventListener('input', () => {
        state.query = els.search.value.trim();
        paintList();
        searchPeople();
    });

    window.addEventListener('popstate', () => {
        if (K.isMobile() && els.app.classList.contains('is-chat-open')) closeConversation(true);
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') markRead();
    });
    window.addEventListener('focus', markRead);

    if (RT) {
        RT.setMode('chat');
        RT.onUser(onUserEvent);
        RT.setOpenChatHandler((chat) => openChat(chat.kind, chat.id));
        let lastFallback = 0;
        RT.onFallback(() => {
            if (Date.now() - lastFallback < 5500) return;
            lastFallback = Date.now();
            loadList();
            fetchNew();
        });
    }

    // Lo stato online in lista invecchia: una rinfrescata al minuto, a scheda visibile.
    setInterval(() => {
        if (document.visibilityState === 'visible') loadList();
    }, 60000);

    (async () => {
        K.clear(els.list);
        for (let i = 0; i < 6; i += 1) {
            els.list.appendChild(h('div', { class: 'pc-row pc-row--skeleton' }, h('span', { class: 'pc-avatar ck-skeleton' }), h('span', { class: 'pc-row__body' }, h('span', { class: 'ck-skeleton' }), h('span', { class: 'ck-skeleton' }))));
        }
        await loadList();

        const params = new URLSearchParams(window.location.search);
        const conversationId = Number(params.get('c')) || 0;
        const groupId = Number(params.get('g')) || 0;
        const userId = Number(params.get('user_id')) || 0;
        const groupWith = Number(params.get('create_group_with')) || 0;

        if (conversationId && findChat('private', conversationId)) {
            const chat = findChat('private', conversationId);
            if (chat.is_request) state.filter = 'requests';
            else if (chat.is_archived) state.filter = 'archived';
            paintFilters();
            openChat('private', conversationId);
        } else if (groupId && findChat('group', groupId)) {
            openChat('group', groupId);
        } else if (userId && userId !== Number(me.id)) {
            const existing = state.privates.find((c) => Number(c.other_user_id) === userId);
            if (existing) {
                openChat('private', existing.id);
            } else {
                try {
                    const data = await K.api('/api/social/user_card.php?target_id=' + userId);
                    const user = data.data;
                    if (user.relationship && user.relationship.can_message) openDraft({ id: user.id, username: user.username, display_name: user.display_name, is_premium: user.is_premium });
                    else K.toast(t('pc_cant_write'), 'error');
                } catch (error) {
                    K.toast(error.message, 'error');
                }
            }
        } else if (groupWith) {
            openNewGroup(groupWith);
        }
    })();
})();
