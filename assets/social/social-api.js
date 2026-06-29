/* assets/social/social-api.js - Cripsum™ Social Graph API Client */

const SocialAPI = {
    // Helper comune per le chiamate fetch
    async call(endpoint, options = {}) {
        const url = `/api/social/${endpoint}`;
        options.headers = options.headers || {};
        
        // Se non e' una richiesta GET, inseriamo il token CSRF
        if (options.method && options.method !== 'GET') {
            const csrfToken = document.body.dataset.csrf || window.socialCsrfToken || '';
            options.headers['X-CSRF-Token'] = csrfToken;
            options.headers['Content-Type'] = 'application/json';
            
            if (options.body && typeof options.body === 'object') {
                options.body = JSON.stringify(options.body);
            }
        }
        
        try {
            const response = await fetch(url, options);
            if (response.status === 419) {
                // Sessione CSRF scaduta
                alert("Sessione scaduta. La pagina verrà ricaricata.");
                window.location.reload();
                return { success: false, error: { message: "Sessione scaduta." } };
            }
            return await response.json();
        } catch (error) {
            console.error(`Errore API Social (${endpoint}):`, error);
            return {
                success: false,
                error: { code: "NETWORK_ERROR", message: "Impossibile connettersi al server. Riprova." }
            };
        }
    },

    // --- FOLLOW ---
    async follow(followedId) {
        return this.call('follow.php', { method: 'POST', body: { followed_id: followedId } });
    },

    async unfollow(followedId) {
        return this.call('unfollow.php', { method: 'POST', body: { followed_id: followedId } });
    },

    // --- AMICIZIA ---
    async sendFriendRequest(receiverId) {
        return this.call('send_friend_request.php', { method: 'POST', body: { receiver_id: receiverId } });
    },

    async acceptFriendRequest(senderId) {
        return this.call('accept_friend_request.php', { method: 'POST', body: { sender_id: senderId } });
    },

    async declineFriendRequest(senderId) {
        return this.call('decline_friend_request.php', { method: 'POST', body: { sender_id: senderId } });
    },

    async cancelFriendRequest(receiverId) {
        return this.call('cancel_friend_request.php', { method: 'POST', body: { receiver_id: receiverId } });
    },

    async removeFriend(friendId) {
        return this.call('remove_friend.php', { method: 'POST', body: { friend_id: friendId } });
    },

    // --- BLOCCHI ---
    async blockUser(blockedId) {
        return this.call('block_user.php', { method: 'POST', body: { blocked_id: blockedId } });
    },

    async unblockUser(blockedId) {
        return this.call('unblock_user.php', { method: 'POST', body: { blocked_id: blockedId } });
    },

    // --- QUERY LISTE ---
    async getFollowers(targetId, page = 1, query = '') {
        return this.call(`followers.php?target_id=${targetId}&page=${page}&q=${encodeURIComponent(query)}`);
    },

    async getFollowing(targetId, page = 1, query = '') {
        return this.call(`following.php?target_id=${targetId}&page=${page}&q=${encodeURIComponent(query)}`);
    },

    async getFriends(targetId) {
        return this.call(`friends.php?target_id=${targetId}`);
    },

    async getFriendRequests() {
        return this.call('friend_requests.php');
    },

    async getUserCard(targetId = 0, username = '') {
        const param = targetId > 0 ? `target_id=${targetId}` : `username=${encodeURIComponent(username)}`;
        return this.call(`user_card.php?${param}`);
    },

    async searchUsers(query, limit = 20, offset = 0) {
        return this.call(`search_users.php?q=${encodeURIComponent(query)}&limit=${limit}&offset=${offset}`);
    },

    async getSuggestedUsers() {
        return this.call('suggested_users.php');
    },

    async getRelationshipStatus(userIds) {
        const idsStr = Array.isArray(userIds) ? userIds.join(',') : userIds;
        return this.call(`relationship_status.php?user_ids=${idsStr}`);
    }
};
