// assets/chat/chat-api.js
// API client module for Cripsum™ Group & Private Chat.

const ChatAPI = {
    csrfToken: '',

    init() {
        this.csrfToken = document.body.dataset.csrf || '';
    },

    async call(endpoint, options = {}) {
        const url = `/api/chat/${endpoint}`;
        options.headers = options.headers || {};
        
        // Add content type and CSRF headers
        if (!(options.body instanceof FormData)) {
            options.headers['Content-Type'] = 'application/json';
            
            // If body is object, inject CSRF token
            if (options.body && typeof options.body === 'object') {
                options.body.csrf_token = this.csrfToken;
                options.body = JSON.stringify(options.body);
            }
        }
        
        // Always add the header for security
        options.headers['X-CSRF-Token'] = this.csrfToken;
        options.headers['X-Requested-With'] = 'XMLHttpRequest';

        try {
            const res = await fetch(url, options);
            if (res.status === 401) {
                // Session expired
                showToast("Sessione scaduta. Ricarica la pagina.", true);
                setTimeout(() => window.location.reload(), 3000);
                return { ok: false, error: "Non autenticato" };
            }
            return await res.json();
        } catch (e) {
            console.error("API call error:", e);
            return { ok: false, error: "Errore di connessione al server." };
        }
    },

    // GET
    async getChatList(filter = 'active') {
        return this.call(`list.php?filter=${filter}`);
    },

    async getChatDetails(chatId) {
        return this.call(`get.php?chat_id=${chatId}`);
    },

    async getChatMembers(chatId) {
        return this.call(`members.php?chat_id=${chatId}`);
    },

    async getMessages(chatId, beforeMessageId = 0, afterMessageId = 0) {
        let query = `messages.php?chat_id=${chatId}`;
        if (beforeMessageId > 0) query += `&before_message_id=${beforeMessageId}`;
        if (afterMessageId > 0) query += `&after_message_id=${afterMessageId}`;
        return this.call(query);
    },

    async getPrivateMessages(conversationId, beforeMessageId = 0) {
        let query = `get_messages.php?conversation_id=${conversationId}`;
        if (beforeMessageId > 0) query += `&before_message_id=${beforeMessageId}`;
        return this.call(query);
    },

    async searchMessages(chatId, queryText) {
        return this.call(`search_messages.php?chat_id=${chatId}&query=${encodeURIComponent(queryText)}`);
    },

    async getGifs(query = '', nextOffset = '') {
        let queryStr = `gifs.php?q=${encodeURIComponent(query)}`;
        if (nextOffset) queryStr += `&page=${encodeURIComponent(nextOffset)}`;
        return this.call(queryStr);
    },

    // POST
    async createGroup(name, description, invitedUsers) {
        return this.call('create_group.php', {
            method: 'POST',
            body: { name, description, invited_users: invitedUsers }
        });
    },

    async updateGroup(chatId, data) {
        return this.call('update_group.php', {
            method: 'POST',
            body: { chat_id: chatId, ...data }
        });
    },

    async updateAvatar(formData) {
        return this.call('update_avatar.php', {
            method: 'POST',
            body: formData
        });
    },

    async sendMessage(chatId, message, replyToId = null, extra = {}) {
        return this.call('send_message.php', {
            method: 'POST',
            body: { chat_id: chatId, message, reply_to_message_id: replyToId, ...extra }
        });
    },

    async sendPrivateMessage(conversationId, recipientId, message, replyToId = null, extra = {}) {
        return this.call('send_message.php', {
            method: 'POST',
            body: { conversation_id: conversationId, recipient_id: recipientId, message, reply_to_id: replyToId, ...extra }
        });
    },

    async editMessage(messageId, content) {
        return this.call('edit_message.php', {
            method: 'POST',
            body: { message_id: messageId, content }
        });
    },

    async editPrivateMessage(messageId, content) {
        return this.call('manage_message.php', {
            method: 'POST',
            body: { action: 'edit', message_id: messageId, content }
        });
    },

    async deleteMessage(messageId) {
        return this.call('delete_message.php', {
            method: 'POST',
            body: { message_id: messageId }
        });
    },

    async deletePrivateMessage(messageId) {
        return this.call('manage_message.php', {
            method: 'POST',
            body: { action: 'delete_for_all', message_id: messageId }
        });
    },

    async markRead(chatId, messageId = null) {
        return this.call('mark_read.php', {
            method: 'POST',
            body: { chat_id: chatId, message_id: messageId }
        });
    },

    async markPrivateRead(conversationId, messageId) {
        return this.call('get_messages.php', {
            method: 'POST',
            body: { conversation_id: conversationId, before_message_id: 0 }
        });
    },

    async inviteUser(chatId, inviteeId) {
        return this.call('invite_user.php', {
            method: 'POST',
            body: { chat_id: chatId, invitee_id: inviteeId }
        });
    },

    async acceptInvite(chatId) {
        return this.call('accept_invite.php', {
            method: 'POST',
            body: { chat_id: chatId }
        });
    },

    async declineInvite(chatId) {
        return this.call('decline_invite.php', {
            method: 'POST',
            body: { chat_id: chatId }
        });
    },

    async cancelInvite(chatId, inviteeId) {
        return this.call('cancel_invite.php', {
            method: 'POST',
            body: { chat_id: chatId, invitee_id: inviteeId }
        });
    },

    async removeMember(chatId, memberId) {
        return this.call('remove_member.php', {
            method: 'POST',
            body: { chat_id: chatId, member_id: memberId }
        });
    },

    async leaveChat(chatId) {
        return this.call('leave_chat.php', {
            method: 'POST',
            body: { chat_id: chatId }
        });
    },

    async promoteAdmin(chatId, memberId) {
        return this.call('promote_admin.php', {
            method: 'POST',
            body: { chat_id: chatId, member_id: memberId }
        });
    },

    async demoteAdmin(chatId, memberId) {
        return this.call('demote_admin.php', {
            method: 'POST',
            body: { chat_id: chatId, member_id: memberId }
        });
    },

    async muteChat(chatId, duration) {
        return this.call('mute.php', {
            method: 'POST',
            body: { chat_id: chatId, duration }
        });
    },

    async unmuteChat(chatId) {
        return this.call('unmute.php', {
            method: 'POST',
            body: { chat_id: chatId }
        });
    },

    async updateNotificationLevel(chatId, level) {
        return this.call('update_notification_level.php', {
            method: 'POST',
            body: { chat_id: chatId, notification_level: level }
        });
    },

    async archiveChat(chatId) {
        return this.call('archive.php', {
            method: 'POST',
            body: { chat_id: chatId, action: 'archive' }
        });
    },

    async unarchiveChat(chatId) {
        return this.call('archive.php', {
            method: 'POST',
            body: { chat_id: chatId, action: 'unarchive' }
        });
    }
};
