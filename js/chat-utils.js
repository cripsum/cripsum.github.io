// filepath: global-chat-app/global-chat-app/js/chat-utils.js
const MAX_MESSAGE_LENGTH = 300;
const MESSAGE_TIMEOUT = 5000; // 5 seconds
let lastMessageTime = 0;

function isMessageValid(message) {
    return message.length > 0 && message.length <= MAX_MESSAGE_LENGTH;
}

function showNotification(message) {
    const notification = new Audio('assets/sounds/notification.mp3');
    notification.play();
    alert(message);
}

function formatMessage(message, username) {
    return `${username}: ${message}`;
}

function canSendMessage() {
    const currentTime = Date.now();
    if (currentTime - lastMessageTime < MESSAGE_TIMEOUT) {
        return false;
    }
    lastMessageTime = currentTime;
    return true;
}

function handleDeleteMessage(messageId, userId, isAdmin) {
    if (isAdmin || userId === getCurrentUserId()) {
        // Call API to delete the message
        fetch(`api/delete_message.php?id=${messageId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Message deleted successfully.');
                // Refresh messages or remove the message from the UI
            } else {
                showNotification('Failed to delete message.');
            }
        })
        .catch(error => {
            console.error('Error deleting message:', error);
        });
    } else {
        showNotification('You can only delete your own messages.');
    }
}

function getCurrentUserId() {
    // This function should return the current user's ID from session or context
    return document.getElementById('currentUserId').value;
}