/**
 * Cripsum™ — scheda utente.
 *
 * Si apre da qualunque elemento con la classe .user-card-trigger (attributi
 * data-user-id o data-username) oppure da codice:
 *
 *     CripsumUserCard.open({ id: 12 })
 *     CripsumUserCard.open({ username: 'mario' })
 *
 * Mostra chi è la persona e permette di fare subito le cose che servono:
 * amicizia, messaggio, invito in un gruppo, blocco. Ha bisogno di kit.js e
 * kit.css. Dopo ogni azione lancia l'evento «cripsum:social-changed», che la
 * pagina Amici ascolta per aggiornarsi.
 */
(() => {
    'use strict';

    const K = window.ChatKit;
    if (!K) return;

    const { h, icon, t } = K;
    const lang = K.lang;

    K.extend({
        uc_add: { it: 'Aggiungi', en: 'Add friend' },
        uc_accept: { it: 'Accetta richiesta', en: 'Accept request' },
        uc_decline: { it: 'Rifiuta', en: 'Decline' },
        uc_pending: { it: 'Richiesta inviata', en: 'Request sent' },
        uc_cancel_request: { it: 'Annulla richiesta', en: 'Cancel request' },
        uc_friends: { it: 'Amici', en: 'Friends' },
        uc_message: { it: 'Messaggio', en: 'Message' },
        uc_profile: { it: 'Profilo', en: 'Profile' },
        uc_more: { it: 'Altro', en: 'More' },
        uc_remove: { it: 'Rimuovi dagli amici', en: 'Remove friend' },
        uc_remove_title: { it: 'Rimuovere {name} dagli amici?', en: 'Remove {name} from your friends?' },
        uc_remove_text: { it: 'Non verrà avvisato. Potrete sempre mandarvi una nuova richiesta.', en: 'They will not be notified. You can always send each other a new request.' },
        uc_invite: { it: 'Invita in un gruppo', en: 'Invite to a group' },
        uc_invite_none: { it: 'Non hai gruppi in cui invitarlo.', en: 'You have no groups to invite them to.' },
        uc_invite_done: { it: 'Invito mandato.', en: 'Invitation sent.' },
        uc_block: { it: 'Blocca', en: 'Block' },
        uc_unblock: { it: 'Sblocca', en: 'Unblock' },
        uc_block_title: { it: 'Bloccare {name}?', en: 'Block {name}?' },
        uc_block_text: { it: 'Non potrà più scriverti né mandarti richieste, e l\'amicizia verrà rimossa. Non verrà avvisato.', en: 'They will no longer be able to message you or send requests, and the friendship will be removed. They will not be notified.' },
        uc_blocked_note: { it: 'Hai bloccato questa persona.', en: 'You blocked this person.' },
        uc_report: { it: 'Segnala', en: 'Report' },
        uc_copy_name: { it: 'Copia nome utente', en: 'Copy username' },
        uc_about: { it: 'Su di me', en: 'About me' },
        uc_mutual_one: { it: '1 amico in comune', en: '1 mutual friend' },
        uc_mutual: { it: '{n} amici in comune', en: '{n} mutual friends' },
        uc_friends_count_one: { it: '1 amico', en: '1 friend' },
        uc_friends_count: { it: '{n} amici', en: '{n} friends' },
        uc_private: { it: 'Questo profilo è privato.', en: 'This profile is private.' },
        uc_privacy_title: { it: 'Privacy', en: 'Privacy' },
        uc_privacy_dm: { it: 'Solo gli amici possono scrivermi', en: 'Only friends can message me' },
        uc_privacy_dm_hint: { it: 'Spento: chi non è tuo amico può scriverti, ma finisce tra le Richieste.', en: 'Off: people who are not your friends can write, but they land in Requests.' },
        uc_privacy_requests: { it: 'Non accetto richieste di amicizia', en: 'I do not accept friend requests' },
        uc_privacy_requests_hint: { it: 'Nessuno potrà mandarti una richiesta finché resta acceso.', en: 'Nobody can send you a request while this is on.' },
        uc_privacy_receipts: { it: 'Conferme di lettura', en: 'Read receipts' },
        uc_privacy_receipts_hint: { it: 'Se le spegni non vedi nemmeno quelle degli altri.', en: 'If you turn them off you will not see other people\'s either.' },
        uc_privacy_typing: { it: 'Mostra quando sto scrivendo', en: 'Show when I am typing' },
        uc_privacy_typing_hint: { it: 'Gli altri vedono «sta scrivendo» mentre componi un messaggio.', en: 'Others see "is typing" while you write a message.' },
        uc_privacy_off: { it: 'Le impostazioni di privacy non sono ancora attive su questo server.', en: 'Privacy settings are not available on this server yet.' },
        uc_saved: { it: 'Salvato.', en: 'Saved.' },
        uc_you: { it: 'Questo sei tu.', en: 'This is you.' }
    });

    let overlay = null;
    let current = null;
    let previousFocus = null;

    const isLoggedIn = () => document.body.dataset.loggedIn === '1' || window.isLoggedIn === true
        || !!(window.CNAV_STATE && window.CNAV_STATE.userId > 0);

    function announce(userId) {
        document.dispatchEvent(new CustomEvent('cripsum:social-changed', { detail: { userId } }));
    }

    function close() {
        if (!overlay) return;
        const node = overlay;
        overlay = null;
        current = null;
        document.removeEventListener('keydown', onKey, true);
        node.classList.add('is-closing');
        setTimeout(() => node.remove(), 160);
        if (previousFocus && previousFocus.focus) previousFocus.focus();
    }

    function onKey(event) {
        // Un menu o una finestra aperti sopra la scheda hanno la precedenza.
        if (event.key === 'Escape' && !document.querySelector('.ck-menu, .ck-dialog:not(.ck-ucard-overlay)')) {
            event.stopPropagation();
            close();
        }
    }

    function skeleton() {
        return h('div', { class: 'ck-ucard' },
            h('div', { class: 'ck-ucard__banner ck-skeleton' }),
            h('div', { class: 'ck-ucard__top' }, h('span', { class: 'ck-ucard__avatar ck-skeleton' })),
            h('div', { class: 'ck-ucard__body' },
                h('span', { class: 'ck-skeleton', style: 'width: 55%; height: 20px' }),
                h('span', { class: 'ck-skeleton', style: 'width: 35%; height: 13px' }),
                h('span', { class: 'ck-skeleton', style: 'width: 100%; height: 42px; margin-top: 14px' })));
    }

    async function open(target) {
        const id = Number(target && target.id) || 0;
        const username = String((target && target.username) || '').trim();
        if (!id && !username) return;

        if (!overlay) {
            previousFocus = document.activeElement;
            overlay = h('div', { class: 'ck-dialog ck-ucard-overlay', role: 'dialog', 'aria-modal': 'true' });
            overlay.addEventListener('mousedown', (event) => {
                if (event.target === overlay) close();
            });
            document.addEventListener('keydown', onKey, true);
            K.mount(overlay);
        }
        const host = overlay;
        K.clear(host);
        host.appendChild(skeleton());

        try {
            const query = id ? 'target_id=' + id : 'username=' + encodeURIComponent(username);
            const data = await K.api('/api/social/user_card.php?' + query);
            if (overlay !== host) return;
            current = data.data;
            K.clear(host);
            host.appendChild(render(current));
            const first = host.querySelector('.ck-ucard__actions .ck-btn, .ck-ucard__close');
            if (first) first.focus();
        } catch (error) {
            if (overlay !== host) return;
            K.clear(host);
            host.appendChild(h('div', { class: 'ck-ucard ck-ucard--error' },
                K.emptyState('fa-solid fa-user-slash', error.message, ''),
                h('button', { type: 'button', class: 'ck-btn ck-btn--ghost', onClick: close }, t('close'))));
        }
    }

    /** Colore valido in forma #rrggbb, altrimenti niente: finisce dentro uno stile. */
    function safeColor(value) {
        return /^#[0-9a-f]{6}$/i.test(String(value || '')) ? value : '';
    }

    function banner(user) {
        const accent = safeColor(user.style && user.style.accent_color) || '#2f6bff';
        const node = h('div', { class: 'ck-ucard__banner' });
        node.style.background = 'linear-gradient(135deg, ' + accent + ', #0a0e1a 130%)';

        const url = K.safeUrl(user.profile_banner_url, '');
        if (url) {
            if (String(user.profile_banner_type || '').startsWith('video/')) {
                node.appendChild(h('video', { src: url, autoplay: true, loop: true, muted: true, playsinline: true }));
            } else {
                node.appendChild(h('img', { src: url, alt: '' }));
            }
        }
        return node;
    }

    async function act(button, endpoint, body, after) {
        if (button) button.disabled = true;
        try {
            const data = await K.api('/api/social/' + endpoint, { body });
            if (data.message) K.toast(data.message, 'success');
            announce(current ? current.id : 0);
            if (window.CripsumRT) window.CripsumRT.refreshCounters();
            if (after) after(data);
            else if (current && overlay) open({ id: current.id });
        } catch (error) {
            K.toast(error.message, 'error');
            if (button) button.disabled = false;
        }
    }

    async function removeFriend(user) {
        const ok = await K.confirm({
            title: t('uc_remove_title', { name: user.display_name }), text: t('uc_remove_text'),
            confirmLabel: t('uc_remove'), danger: true, icon: 'fa-solid fa-user-minus'
        });
        if (ok) act(null, 'remove_friend.php', { friend_id: user.id });
    }

    async function block(user) {
        const ok = await K.confirm({
            title: t('uc_block_title', { name: user.display_name }), text: t('uc_block_text'),
            confirmLabel: t('uc_block'), danger: true, icon: 'fa-solid fa-ban'
        });
        if (ok) act(null, 'block_user.php', { blocked_id: user.id });
    }

    async function inviteToGroup(user, anchor) {
        try {
            const data = await K.api('/api/chat/my_manageable_groups.php?target_id=' + user.id);
            const groups = data.groups || [];
            if (!groups.length) {
                K.toast(t('uc_invite_none'));
                return;
            }
            K.menu(anchor, groups.map((group) => ({
                label: group.name,
                icon: 'fa-solid fa-user-group',
                onSelect: async () => {
                    try {
                        await K.api('/api/chat/invite_user.php', { body: { chat_id: group.chat_id, invitee_id: user.id } });
                        K.toast(t('uc_invite_done'), 'success');
                    } catch (error) {
                        K.toast(error.message, 'error');
                    }
                }
            })), { header: t('uc_invite') });
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    function moreMenu(user, anchor) {
        const r = user.relationship;
        K.menu(anchor, [
            { label: t('uc_copy_name'), icon: 'fa-regular fa-copy', onSelect: () => K.copy('@' + user.username) },
            r.is_friend ? { label: t('uc_invite'), icon: 'fa-solid fa-user-plus', onSelect: () => inviteToGroup(user, anchor) } : null,
            r.is_friend ? { label: t('uc_remove'), icon: 'fa-solid fa-user-minus', danger: true, onSelect: () => removeFriend(user) } : null,
            { divider: true },
            r.is_blocked_by_viewer
                ? { label: t('uc_unblock'), icon: 'fa-solid fa-unlock', onSelect: () => act(null, 'unblock_user.php', { blocked_id: user.id }) }
                : { label: t('uc_block'), icon: 'fa-solid fa-ban', danger: true, onSelect: () => block(user) },
            { label: t('uc_report'), icon: 'fa-regular fa-flag', danger: true, onSelect: () => { window.location.href = '/' + lang + '/supporto'; } }
        ], { alignRight: true });
    }

    function actions(user) {
        const r = user.relationship;
        const row = h('div', { class: 'ck-ucard__actions' });
        if (r.is_self) return row;

        if (r.is_blocked_by_viewer) {
            row.appendChild(h('button', {
                type: 'button', class: 'ck-btn ck-btn--ghost',
                onClick: (event) => act(event.currentTarget, 'unblock_user.php', { blocked_id: user.id })
            }, icon('fa-solid fa-unlock'), t('uc_unblock')));
        } else if (r.is_friend) {
            row.appendChild(h('span', { class: 'ck-ucard__state' }, icon('fa-solid fa-user-check'), t('uc_friends')));
        } else if (r.friend_request_received) {
            row.append(
                h('button', {
                    type: 'button', class: 'ck-btn ck-btn--primary',
                    onClick: (event) => act(event.currentTarget, 'accept_friend_request.php', { sender_id: user.id })
                }, icon('fa-solid fa-user-check'), t('uc_accept')),
                h('button', {
                    type: 'button', class: 'ck-btn ck-btn--ghost',
                    onClick: (event) => act(event.currentTarget, 'decline_friend_request.php', { sender_id: user.id })
                }, t('uc_decline')));
        } else if (r.friend_request_sent) {
            row.appendChild(h('button', {
                type: 'button', class: 'ck-btn ck-btn--ghost', title: t('uc_cancel_request'),
                onClick: (event) => act(event.currentTarget, 'cancel_friend_request.php', { receiver_id: user.id })
            }, icon('fa-solid fa-user-clock'), t('uc_pending')));
        } else if (r.can_send_friend_request) {
            row.appendChild(h('button', {
                type: 'button', class: 'ck-btn ck-btn--primary',
                onClick: (event) => act(event.currentTarget, 'send_friend_request.php', { receiver_id: user.id })
            }, icon('fa-solid fa-user-plus'), t('uc_add')));
        }

        if (r.can_message && !r.is_blocked_by_viewer) {
            row.appendChild(h('a', { class: 'ck-btn ck-btn--ghost', href: '/' + lang + '/chat?user_id=' + user.id },
                icon('fa-solid fa-comment'), t('uc_message')));
        }
        return row;
    }

    function render(user) {
        const r = user.relationship;
        const profileUrl = '/u/' + encodeURIComponent(user.username);
        const stats = user.stats || {};
        const mutual = Number(stats.mutual_friends_count) || 0;
        const friends = Number(stats.friends_count) || 0;

        const card = h('div', { class: 'ck-ucard' });

        card.appendChild(banner(user));
        card.appendChild(h('div', { class: 'ck-ucard__tools' },
            r.is_self ? null : h('button', {
                type: 'button', class: 'ck-icon-btn ck-ucard__tool', 'aria-label': t('uc_more'), title: t('uc_more'),
                onClick: (event) => moreMenu(user, event.currentTarget)
            }, icon('fa-solid fa-ellipsis')),
            h('button', { type: 'button', class: 'ck-icon-btn ck-ucard__tool ck-ucard__close', 'aria-label': t('close'), onClick: close }, icon('fa-solid fa-xmark'))));

        card.appendChild(h('div', { class: 'ck-ucard__top' },
            h('a', { class: 'ck-ucard__avatar', href: profileUrl },
                h('img', { src: K.avatarUrl(user.id), alt: '' }),
                user.is_online ? h('span', { class: 'ck-dot is-online' }) : null),
            h('a', { class: 'ck-btn ck-btn--ghost ck-btn--sm', href: profileUrl }, icon('fa-regular fa-user'), t('uc_profile'))));

        const body = h('div', { class: 'ck-ucard__body' });
        body.appendChild(h('div', { class: 'ck-ucard__name' },
            h('strong', null, user.display_name),
            user.is_premium ? K.premiumGem() : null,
            K.roleBadge(user.ruolo)));
        body.appendChild(h('div', { class: 'ck-ucard__handle' },
            h('span', null, '@' + user.username),
            !r.is_blocked_by_viewer && (user.is_online || user.last_seen_ts)
                ? h('span', { class: user.is_online ? 'is-online' : '' }, K.presenceLabel(user.is_online, user.last_seen_ts)) : null));

        if (user.custom_status) body.appendChild(h('p', { class: 'ck-ucard__status' }, user.custom_status));

        if (r.is_self) body.appendChild(h('p', { class: 'ck-ucard__note' }, t('uc_you')));
        else if (r.is_blocked_by_viewer) body.appendChild(h('p', { class: 'ck-ucard__note' }, icon('fa-solid fa-ban'), ' ', t('uc_blocked_note')));
        else if (user.profile_hidden) body.appendChild(h('p', { class: 'ck-ucard__note' }, icon('fa-solid fa-lock'), ' ', t('uc_private')));

        if (user.bio) {
            body.appendChild(h('div', { class: 'ck-ucard__section' },
                h('h4', null, t('uc_about')),
                h('p', null, user.bio)));
        }

        if (!r.is_self && (mutual > 0 || friends > 0)) {
            const line = h('div', { class: 'ck-ucard__mutual' });
            if (mutual > 0) {
                const faces = h('span', { class: 'ck-ucard__faces' });
                (user.mutual_friends || []).slice(0, 4).forEach((friend) => {
                    faces.appendChild(h('img', { src: K.avatarUrl(friend.id), alt: '', title: '@' + friend.username, loading: 'lazy' }));
                });
                line.append(faces, h('span', null, mutual === 1 ? t('uc_mutual_one') : t('uc_mutual', { n: mutual })));
            } else {
                line.append(icon('fa-solid fa-user-group'), h('span', null, friends === 1 ? t('uc_friends_count_one') : t('uc_friends_count', { n: friends })));
            }
            body.appendChild(line);
        }

        body.appendChild(actions(user));
        card.appendChild(body);
        return card;
    }

    /** Finestra delle impostazioni di privacy: chi può scrivere, richieste, conferme di lettura. */
    async function privacy() {
        const body = h('div', { class: 'ck-privacy' }, K.spinner());
        K.dialog({ title: t('uc_privacy_title'), icon: 'fa-solid fa-user-shield', body, actions: [{ label: t('close'), kind: 'ghost', value: null }] });
        try {
            const data = await K.api('/api/social/privacy.php');
            const settings = data.data.settings;
            K.clear(body);
            if (!settings.available) {
                body.appendChild(h('p', { class: 'ck-dialog__text' }, t('uc_privacy_off')));
                return;
            }

            const row = (label, hint, checked, toPayload, disabled) => {
                const input = h('input', { type: 'checkbox', checked, disabled });
                input.addEventListener('change', async () => {
                    try {
                        await K.api('/api/social/privacy.php', { body: toPayload(input.checked) });
                        K.toast(t('uc_saved'), 'success');
                    } catch (error) {
                        input.checked = !input.checked;
                        K.toast(error.message, 'error');
                    }
                });
                return h('label', { class: 'ck-switch ck-privacy__row' }, h('span', null, h('strong', null, label), h('small', null, hint)), input, h('i'));
            };

            body.append(
                row(t('uc_privacy_dm'), t('uc_privacy_dm_hint'), settings.dm_from === 'friends', (on) => ({ dm_from: on ? 'friends' : 'all' })),
                row(t('uc_privacy_requests'), t('uc_privacy_requests_hint'), settings.requests_from === 'none', (on) => ({ requests_from: on ? 'none' : 'all' }), !settings.requests_setting_available),
                row(t('uc_privacy_receipts'), t('uc_privacy_receipts_hint'), !!settings.read_receipts, (on) => ({ read_receipts: on })),
                row(t('uc_privacy_typing'), t('uc_privacy_typing_hint'), !!settings.typing, (on) => ({ typing: on })));
        } catch (error) {
            K.clear(body);
            body.appendChild(h('p', { class: 'ck-dialog__text' }, error.message));
        }
    }

    // Clic su avatar e nomi sparsi per il sito: per chi non ha fatto l'accesso
    // il link resta un link normale al profilo.
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.user-card-trigger');
        if (!trigger || !isLoggedIn()) return;
        const id = parseInt(trigger.dataset.userId || trigger.dataset.id || '0', 10) || 0;
        const username = trigger.dataset.username || '';
        if (!id && !username) return;
        event.preventDefault();
        event.stopPropagation();
        open({ id, username });
    });

    window.CripsumUserCard = { open, close, privacy };
    // Nome usato dalle pagine vecchie.
    window.closeUserCard = close;
})();
