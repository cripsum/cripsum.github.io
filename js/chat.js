// filepath: global-chat-app/global-chat-app/js/chat.js
document.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('messageInput');
    const sendButton = document.getElementById('sendButton');
    const chatDisplay = document.getElementById('chatDisplay');
    const timeoutDuration = 5000; // 5 seconds
    let lastSendTime = 0;

    sendButton.addEventListener('click', function() {
        const message = messageInput.value.trim();

        if (message.length === 0 || message.length > 30) {
            alert('Message must be between 1 and 30 characters.');
            return;
        }

        const currentTime = Date.now();
        if (currentTime - lastSendTime < timeoutDuration) {
            alert('Please wait before sending another message.');
            return;
        }

        sendMessage(message);
        lastSendTime = currentTime;
        messageInput.value = '';
    });

    function sendMessage(message) {
        fetch('api/send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ message: message })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMessage(data.message);
            } else {
                alert(data.error);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function displayMessage(message) {
        const messageElement = document.createElement('div');
        messageElement.classList.add('message');
        messageElement.innerText = message.content;

        const deleteButton = document.createElement('button');
        deleteButton.innerText = 'Delete';
        deleteButton.addEventListener('click', function() {
            deleteMessage(message.id);
        });

        messageElement.appendChild(deleteButton);
        chatDisplay.appendChild(messageElement);
    }

    function deleteMessage(messageId) {
        fetch('api/delete_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: messageId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const messageElement = document.getElementById(`message-${messageId}`);
                if (messageElement) {
                    messageElement.remove();
                }
            } else {
                alert(data.error);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Load messages on page load
    loadMessages();

    function loadMessages() {
        fetch('api/get_messages.php')
            .then(response => response.json())
            .then(data => {
                data.messages.forEach(message => {
                    displayMessage(message);
                });
            })
            .catch(error => console.error('Error:', error));
    }
});