import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: true
});

class Chat {
    constructor(userId) {
        this.userId = userId;
        this.typingTimeout = null;
        this.initializeEchoListeners();
    }

    initializeEchoListeners() {
        window.Echo.private(`chat.${this.userId}`)
            .listen('MessageSent', (e) => {
                this.handleNewMessage(e.message);
                this.updateMessageStatus(e.message.id, 'delivered');
            })
            .listen('UserTyping', (e) => {
                this.handleTypingIndicator(e);
            });
    }

    async sendMessage(receiverId, content, attachment = null) {
        const formData = new FormData();
        formData.append('receiver_id', receiverId);
        formData.append('content', content);
        
        if (attachment) {
            formData.append('attachment', attachment);
        }

        try {
            const response = await fetch('/api/messages', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            return await response.json();
        } catch (error) {
            console.error('Error sending message:', error);
            throw error;
        }
    }

    async getMessages(userId) {
        try {
            const response = await fetch(`/api/messages?user_id=${userId}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });

            return await response.json();
        } catch (error) {
            console.error('Error fetching messages:', error);
            throw error;
        }
    }

    async updateMessageStatus(messageId, status) {
        try {
            await fetch(`/api/messages/${messageId}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ status })
            });
        } catch (error) {
            console.error('Error updating message status:', error);
        }
    }

    handleNewMessage(message) {
        // Implement your UI update logic here
        console.log('New message received:', message);
    }

    handleTypingIndicator(data) {
        // Implement your typing indicator UI logic here
        console.log('Typing status:', data);
    }

    async sendTypingIndicator(receiverId, isTyping) {
        try {
            await fetch('/api/messages/typing', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    receiver_id: receiverId,
                    is_typing: isTyping
                })
            });
        } catch (error) {
            console.error('Error sending typing indicator:', error);
        }
    }

    startTyping(receiverId) {
        if (this.typingTimeout) {
            clearTimeout(this.typingTimeout);
        }

        this.sendTypingIndicator(receiverId, true);

        this.typingTimeout = setTimeout(() => {
            this.sendTypingIndicator(receiverId, false);
        }, 3000);
    }
}

export default Chat; 