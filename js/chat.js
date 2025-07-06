console.log("JS caricato");
document.addEventListener('DOMContentLoaded', function() {
     console.log("Clic su Invia");
    const messageInput = document.getElementById('message');
    const sendButton = document.getElementById('send-button');
    const messagesContainer = document.getElementById('messages');
    const notificationSound = document.getElementById('notification-sound');
    
    let lastMessageId = 0;
    let lastSendTime = 0;
    let replyingTo = null;

    // Carica messaggi iniziali
    loadMessages();

    // Auto-refresh messaggi ogni 2 secondi
    setInterval(loadNewMessages, window.AUTO_REFRESH_INTERVAL || 2000);

    // Event listener per il pulsante invio
    sendButton.addEventListener('click', sendMessage);

    // Event listener per invio con Enter
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    function loadMessages() {
        fetch('../api/get_message.php')
            .then(response => response.text())
            .then(html => {
                messagesContainer.innerHTML = html;
                scrollToBottom();
                updateLastMessageId();
            })
            .catch(error => {
                console.error('Errore nel caricamento dei messaggi:', error);
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
                    playNotificationSound();
                }
            })
            .catch(error => {
                console.error('Errore nel caricamento dei nuovi messaggi:', error);
            });
    }

    function sendMessage() {
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

        fetch('../api/send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageInput.value = '';
                lastSendTime = currentTime;
                clearReply();
                loadNewMessages(); // Ricarica per mostrare il nuovo messaggio
            } else {
                alert(data.error || 'Errore nell\'invio del messaggio');
            }
        })
        .catch(error => {
            console.error('Errore nell\'invio del messaggio:', error);
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
            }
        }
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function playNotificationSound() {
        if (notificationSound) {
            notificationSound.play().catch(e => {
                // Ignora errori di autoplay
            });
        }
    }

    function clearReply() {
        replyingTo = null;
        const replyIndicator = document.getElementById('reply-indicator');
        if (replyIndicator) {
            replyIndicator.remove();
        }
        messageInput.placeholder = `Scrivi un messaggio... (max ${window.maxMessageLength} caratteri)`;
    }

    // Funzioni globali per i pulsanti nei messaggi
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
                loadMessages(); // Ricarica tutti i messaggi
            } else {
                alert(data.error || 'Errore nell\'eliminazione del messaggio');
            }
        })
        .catch(error => {
            console.error('Errore nell\'eliminazione del messaggio:', error);
            alert('Errore nell\'eliminazione del messaggio');
        });
    };

    window.startReply = function(messageId, username, messageText) {
        replyingTo = messageId;
        
        // Rimuovi indicatore di risposta precedente
        clearReply();
        
        // Crea indicatore di risposta
        const replyIndicator = document.createElement('div');
        replyIndicator.id = 'reply-indicator';
        replyIndicator.className = 'reply-indicator';
        replyIndicator.innerHTML = `
            <span>Rispondendo a @${username}: ${messageText.substring(0, 50)}${messageText.length > 50 ? '...' : ''}</span>
            <button type="button" onclick="clearReply()" class="btn-close"></button>
        `;
        
        messageInput.parentElement.insertBefore(replyIndicator, messageInput);
        messageInput.placeholder = `Rispondi a @${username}...`;
        messageInput.focus();
    };

    window.clearReply = clearReply;
});