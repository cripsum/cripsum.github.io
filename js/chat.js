console.log("JS caricato - chat.js loaded successfully");

document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM loaded, initializing chat");
    
    // Test that elements exist
    const messageInput = document.getElementById('message');
    const sendButton = document.getElementById('send-button');
    const messagesContainer = document.getElementById('messages');
    
    console.log('Elements found:', {
        messageInput: !!messageInput,
        sendButton: !!sendButton, 
        messagesContainer: !!messagesContainer
    });
    
    if (!messageInput || !sendButton || !messagesContainer) {
        console.error('Required elements not found!');
        return;
    }
    
    console.log("Setting up event listeners");
    
    let lastMessageId = 0;
    let lastSendTime = 0;
    let replyingTo = null;

    // Load initial messages
    loadMessages();

    // Auto-refresh every 2 seconds
    setInterval(loadNewMessages, window.AUTO_REFRESH_INTERVAL || 2000);

    // Send button click
    sendButton.addEventListener('click', function() {
        console.log("Send button clicked");
        sendMessage();
    });

    // Enter key press
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            console.log("Enter key pressed");
            sendMessage();
        }
    });

    function loadMessages() {
        console.log("Loading messages...");
        fetch('../api/get_message.php')
            .then(response => {
                console.log("Messages response:", response.status);
                return response.text();
            })
            .then(html => {
                console.log("Messages HTML received, length:", html.length);
                messagesContainer.innerHTML = html;
                scrollToBottom();
                updateLastMessageId();
            })
            .catch(error => {
                console.error('Error loading messages:', error);
                messagesContainer.innerHTML = '<div class="text-center text-danger"><p>Errore nel caricamento dei messaggi</p></div>';
            });
    }

    function loadNewMessages() {
        if (lastMessageId === 0) return;

        fetch(`../api/get_message.php?last_id=${lastMessageId}`)
            .then(response => response.text())
            .then(html => {
                if (html.trim()) {
                    messagesContainer.insertAdjacentHTML('beforeend', html);
                    scrollToBottom();
                    updateLastMessageId();
                }
            })
            .catch(error => {
                console.error('Error loading new messages:', error);
            });
    }

    function sendMessage() {
        console.log("Sending message...");
        const message = messageInput.value.trim();

        if (!message) {
            alert('Il messaggio non può essere vuoto');
            return;
        }

        if (message.length > window.maxMessageLength) {
            alert(`Il messaggio non può superare ${window.maxMessageLength} caratteri`);
            return;
        }

        const currentTime = Date.now();
        if (currentTime - lastSendTime < window.messageTimeout) {
            const remainingTime = Math.ceil((window.messageTimeout - (currentTime - lastSendTime)) / 1000);
            alert(`Aspetta ${remainingTime} secondi prima di inviare un altro messaggio`);
            return;
        }

        const payload = {
            message: message
        };

        if (replyingTo) {
            payload.reply_to = replyingTo;
        }

        console.log("Sending payload:", payload);

        fetch('../api/send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => {
            console.log("Send response:", response.status);
            return response.json();
        })
        .then(data => {
            console.log("Send result:", data);
            if (data.success) {
                messageInput.value = '';
                lastSendTime = currentTime;
                clearReply();
                setTimeout(loadMessages, 500); // Reload after short delay
            } else {
                alert(data.error || 'Errore nell\'invio del messaggio');
            }
        })
        .catch(error => {
            console.error('Error sending message:', error);
            alert('Errore nell\'invio del messaggio');
        });
    }

    function updateLastMessageId() {
        const messages = messagesContainer.querySelectorAll('.message[data-message-id]');
        if (messages.length > 0) {
            const lastMessage = messages[messages.length - 1];
            const messageId = parseInt(lastMessage.getAttribute('data-message-id'));
            if (messageId > lastMessageId) {
                lastMessageId = messageId;
                console.log("Updated lastMessageId to:", lastMessageId);
            }
        }
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function clearReply() {
        replyingTo = null;
        const replyIndicator = document.getElementById('reply-indicator');
        if (replyIndicator) {
            replyIndicator.remove();
        }
        if (messageInput) {
            messageInput.placeholder = `Scrivi un messaggio... (max ${window.maxMessageLength} caratteri)`;
        }
    }

    // Global functions for message buttons
    window.deleteMessage = function(messageId) {
        if (!confirm('Sei sicuro di voler eliminare questo messaggio?')) {
            return;
        }

        fetch('../api/delete_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: messageId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadMessages();
            } else {
                alert(data.error || 'Errore nell\'eliminazione del messaggio');
            }
        })
        .catch(error => {
            console.error('Error deleting message:', error);
            alert('Errore nell\'eliminazione del messaggio');
        });
    };

    window.startReply = function(messageId, username, messageText) {
        replyingTo = messageId;
        clearReply();
        
        const replyIndicator = document.createElement('div');
        replyIndicator.id = 'reply-indicator';
        replyIndicator.className = 'reply-indicator';
        replyIndicator.innerHTML = `
            <span>Rispondendo a @${username}: ${messageText.substring(0, 50)}${messageText.length > 50 ? '...' : ''}</span>
            <button type="button" onclick="clearReply()" class="btn-close">×</button>
        `;
        
        if (messageInput && messageInput.parentElement) {
            messageInput.parentElement.insertBefore(replyIndicator, messageInput);
            messageInput.placeholder = `Rispondi a @${username}...`;
            messageInput.focus();
        }
    };

    window.clearReply = clearReply;
});