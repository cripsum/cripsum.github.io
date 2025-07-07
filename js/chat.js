console.log("JS caricato - chat.js loaded successfully");

document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM loaded, initializing chat");
    
    // Declare all variables inside DOMContentLoaded scope
    const messageInput = document.getElementById('message');
    const sendButton = document.getElementById('send-button');
    const messagesContainer = document.getElementById('messages');
    
    let lastMessageId = 0;
    let lastSendTime = 0;
    let replyingTo = null;
    
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
        console.log("Current replyingTo value:", replyingTo); // DEBUG
        
        const message = messageInput.value.trim();

        if (!message) {
            alert('Il messaggio non può essere vuoto');
            return;
        }

        if (message.length > (window.maxMessageLength || 500)) {
            alert(`Il messaggio non può superare ${window.maxMessageLength || 500} caratteri`);
            return;
        }

        const currentTime = Date.now();
        if (currentTime - lastSendTime < (window.messageTimeout || 1000)) {
            const remainingTime = Math.ceil(((window.messageTimeout || 1000) - (currentTime - lastSendTime)) / 1000);
            alert(`Aspetta ${remainingTime} secondi prima di inviare un altro messaggio`);
            return;
        }

        const payload = {
            message: message
        };

        // DEBUG: Aggiungi più controlli
        console.log("Checking replyingTo before adding to payload:", replyingTo);
        console.log("Type of replyingTo:", typeof replyingTo);
        console.log("Is replyingTo null?", replyingTo === null);
        console.log("Is replyingTo undefined?", replyingTo === undefined);

        if (replyingTo !== null && replyingTo !== undefined) {
            payload.reply_to = replyingTo;
            console.log("Adding reply_to to payload:", replyingTo);
        } else {
            console.log("NOT adding reply_to to payload because replyingTo is:", replyingTo);
        }

        console.log("Final payload:", JSON.stringify(payload));

        fetch('../api/send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => {
            console.log("Send response:", response.status);
            return response.text();
        })
        .then(responseText => {
            console.log("Send response text:", responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error("Response is not valid JSON:", responseText);
                throw new Error("Server returned invalid response: " + responseText.substring(0, 100));
            }
            
            console.log("Send result:", data);
            if (data.success) {
                messageInput.value = '';
                lastSendTime = currentTime;
                clearReply();
                setTimeout(loadMessages, 500);
            } else {
                alert(data.error || 'Errore nell\'invio del messaggio');
            }
        })
        .catch(error => {
            console.error('Error sending message:', error);
            alert('Errore nell\'invio del messaggio: ' + error.message);
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
        console.log("Clearing reply... current replyingTo:", replyingTo);
        replyingTo = null;
        console.log("After clearing, replyingTo is:", replyingTo);
        
        const replyIndicator = document.getElementById('reply-indicator');
        if (replyIndicator) {
            replyIndicator.remove();
            console.log("Reply indicator removed");
        }
        if (messageInput) {
            messageInput.placeholder = `Scrivi un messaggio...`;
        }
    }

    // Global functions that have access to the local scope variables
    window.clearReply = function() {
        clearReply();
    };

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
        .then(response => response.text())
        .then(responseText => {
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error("Delete response is not valid JSON:", responseText);
                throw new Error("Server returned invalid response");
            }
            
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
        console.log("=== STARTING REPLY ===");
        console.log("messageId parameter:", messageId, typeof messageId);
        console.log("username parameter:", username);
        console.log("messageText parameter:", messageText);
        
        // Assicurati che gli argomenti siano validi
        if (!messageId || !username || !messageText) {
            console.error("Invalid reply parameters:", { messageId, username, messageText });
            return;
        }
        
        // Prima pulisci qualsiasi reply precedente
        clearReply();
        
        // Poi imposta la nuova reply
        replyingTo = parseInt(messageId);
        console.log("Set replyingTo to:", replyingTo, typeof replyingTo);
        
        // Verifica che sia stato impostato correttamente
        if (replyingTo !== parseInt(messageId)) {
            console.error("Failed to set replyingTo correctly!");
            return;
        }
        
        // Escape HTML per sicurezza
        const safeUsername = escapeHtml(username);
        const safeMessageText = escapeHtml(messageText);
        
        const replyIndicator = document.createElement('div');
        replyIndicator.id = 'reply-indicator';
        replyIndicator.className = 'reply-indicator';
        replyIndicator.innerHTML = `
            <span >Rispondendo a @${safeUsername}</span>
            <button type="button" onclick="window.clearReply()" style="color: black;" class="btn-close">×</button>
        `;
        
        if (messageInput && messageInput.parentElement) {
            messageInput.parentElement.insertBefore(replyIndicator, messageInput);
            messageInput.placeholder = `Rispondi a @${safeUsername}...`;
            messageInput.focus();
            console.log("Reply indicator added successfully");
            console.log("Final replyingTo value:", replyingTo);
        } else {
            console.error("Could not add reply indicator - messageInput or parent not found");
        }
        
        console.log("=== REPLY SETUP COMPLETE ===");
    };

    // Helper function to escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Make functions available globally
    window.loadMessages = loadMessages;
    window.sendMessage = sendMessage;
    
    // DEBUG: Esponi replyingTo per debug
    window.getReplyingTo = function() {
        console.log("Current replyingTo:", replyingTo);
        return replyingTo;
    };
});