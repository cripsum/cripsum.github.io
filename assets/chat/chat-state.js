// assets/chat/chat-state.js
// State Management module for Cripsum™ Group & Private Chat.

const ChatState = {
    // Session state
    myUserId: 0,
    
    // UI active states
    currentChatType: null, // 'private' or 'group'
    currentChatId: 0,      // private conversation_id or group chat_id
    recipientId: 0,        // private chat recipient
    lastMessageId: 0,      // message polling cursor
    
    // Lists
    conversations: [],     // Unified private + group list
    invites: [],           // Group invites
    messages: [],          // Message log
    members: [],           // Group members list
    
    // Filters & Tabs
    activeTab: 'active',   // 'active' or 'archived'
    searchQuery: '',
    
    // Feature flags / ephemeral UI state
    replyToId: null,
    editMessageId: null,
    isDetailsOpen: false,
    ephemeralTimer: 0,
    pollInterval: null,

    init(userId) {
        this.myUserId = parseInt(userId);
        this.resetActiveChat();
    },

    resetActiveChat() {
        this.currentChatType = null;
        this.currentChatId = 0;
        this.recipientId = 0;
        this.lastMessageId = 0;
        this.messages = [];
        this.members = [];
        this.replyToId = null;
        this.editMessageId = null;
    },

    setActiveChat(type, id, recipientId = 0) {
        this.currentChatType = type;
        this.currentChatId = parseInt(id);
        this.recipientId = parseInt(recipientId);
        this.lastMessageId = 0;
        this.messages = [];
        this.members = [];
        this.replyToId = null;
        this.editMessageId = null;
    },

    getChatFromList(type, id) {
        id = parseInt(id);
        if (type === 'group') {
            return this.conversations.find(c => c.isGroupChat && c.chat_id === id);
        } else {
            return this.conversations.find(c => !c.isGroupChat && c.conversation_id === id);
        }
    }
};
