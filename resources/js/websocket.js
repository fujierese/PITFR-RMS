// resources/js/websocket.js
// WebSocket client for PITFR real-time updates using Laravel Echo

class PitfrWebSocket {
    constructor() {
        this.connected = false;
        this.channel = null;
        this.eventListeners = {};
    }

    connect() {
        if (this.channel || !window.Echo) {
            return; // Already connected or Echo not available
        }

        try {
            this.channel = window.Echo.channel('facility-requests');

            this.channel.listen('.request.created', (e) => {
                console.log('Request created:', e);
                this.emit('request_created', e);
            });

            this.channel.listen('.request.approved', (e) => {
                console.log('Request approved:', e);
                this.emit('request_approved', e);
            });

            this.channel.listen('.request.rejected', (e) => {
                console.log('Request rejected:', e);
                this.emit('request_rejected', e);
            });

            this.channel.listen('.request.cancelled', (e) => {
                console.log('Request cancelled:', e);
                this.emit('request_cancelled', e);
            });

            this.channel.listen('.equipment.returned', (e) => {
                console.log('Equipment returned:', e);
                this.emit('equipment_returned', e);
            });

            this.connected = true;
            console.log('Connected to Laravel Echo facility-requests channel');
            this.emit('connected');

        } catch (error) {
            console.error('Failed to connect to Echo channel:', error);
            this.emit('error', error);
        }
    }

    disconnect() {
        if (this.channel) {
            this.channel.stopListening('.request.created');
            this.channel.stopListening('.request.approved');
            this.channel.stopListening('.request.rejected');
            this.channel.stopListening('.request.cancelled');
            this.channel.stopListening('.equipment.returned');
            window.Echo.leave('facility-requests');
            this.channel = null;
        }
        this.connected = false;
    }

    on(event, callback) {
        if (!this.eventListeners[event]) {
            this.eventListeners[event] = [];
        }
        this.eventListeners[event].push(callback);
    }

    off(event, callback) {
        if (this.eventListeners[event]) {
            const index = this.eventListeners[event].indexOf(callback);
            if (index > -1) {
                this.eventListeners[event].splice(index, 1);
            }
        }
    }

    emit(event, data) {
        if (this.eventListeners[event]) {
            this.eventListeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (e) {
                    console.error('Error in WebSocket event listener:', e);
                }
            });
        }
    }

    isConnected() {
        return this.connected;
    }
}

// Create global instance
window.PitfrWebSocket = new PitfrWebSocket();

// Auto-connect when DOM is ready and Echo is available
document.addEventListener('DOMContentLoaded', function() {
    if (window.Echo) {
        window.PitfrWebSocket.connect();
    } else {
        console.warn('Laravel Echo not available, WebSocket features disabled');
    }
});

// Export for ES modules
export default PitfrWebSocket;