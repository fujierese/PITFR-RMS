// resources/js/websocket.js
// WebSocket client for PITFR real-time updates using Laravel Echo

class PitfrWebSocket {
    constructor() {
        this.connected = false;
        this.channel = null;
        this.eventListeners = {};
        this.dedupeSeen = new Set();
    }

    connect() {
        if (this.channel || !window.Echo) {
            return; // Already connected or Echo not available
        }

        try {
            // Subscribe to a private channel for the current user (owner notifications)
            const userMeta = document.querySelector('meta[name="user-id"]');
            const userId = userMeta ? userMeta.content : null;
            if (userId) {
                this.privateChannel = window.Echo.private(`App.Models.User.${userId}`);

                const process = (eventName, e) => {
                    const uid = e.event_uid || (eventName + ':' + (e.request_id || e.requestId || ''));
                    if (this.dedupeSeen.has(uid)) return;
                    this.dedupeSeen.add(uid);
                    this.emit(eventName, e);
                };

                this.privateChannel.listen('.request.created', (e) => { process('request_created', e); });
                this.privateChannel.listen('.request.approved', (e) => { process('request_approved', e); });
                this.privateChannel.listen('.request.rejected', (e) => { process('request_rejected', e); });
                this.privateChannel.listen('.request.cancelled', (e) => { process('request_cancelled', e); });
                this.privateChannel.listen('.equipment.returned', (e) => { process('equipment_returned', e); });

                // attempt to subscribe to custodian private channel too; server authorizer will deny non-custodians
                try {
                    this.custodianChannel = window.Echo.private(`facility-requests.custodian.${userId}`);
                    this.custodianChannel.listen('.request.created', (e) => { process('request_created', e); });
                    this.custodianChannel.listen('.request.approved', (e) => { process('request_approved', e); });
                    this.custodianChannel.listen('.request.rejected', (e) => { process('request_rejected', e); });
                    this.custodianChannel.listen('.request.cancelled', (e) => { process('request_cancelled', e); });
                    this.custodianChannel.listen('.equipment.returned', (e) => { process('equipment_returned', e); });
                } catch (err) {
                    // ignore: Echo may throw if private subscription fails
                }
            }

            // Also attempt to subscribe to the admin channel; server will authorize appropriately
            this.adminChannel = window.Echo.channel('facility-requests.admin');
            this.adminChannel.listen('.request.created', (e) => { const uid = e.event_uid || ('request_created:' + (e.request_id || e.requestId || '')); if (!this.dedupeSeen.has(uid)) { this.dedupeSeen.add(uid); this.emit('request_created', e); } });
            this.adminChannel.listen('.request.approved', (e) => { const uid = e.event_uid || ('request_approved:' + (e.request_id || e.requestId || '')); if (!this.dedupeSeen.has(uid)) { this.dedupeSeen.add(uid); this.emit('request_approved', e); } });
            this.adminChannel.listen('.request.rejected', (e) => { const uid = e.event_uid || ('request_rejected:' + (e.request_id || e.requestId || '')); if (!this.dedupeSeen.has(uid)) { this.dedupeSeen.add(uid); this.emit('request_rejected', e); } });
            this.adminChannel.listen('.request.cancelled', (e) => { const uid = e.event_uid || ('request_cancelled:' + (e.request_id || e.requestId || '')); if (!this.dedupeSeen.has(uid)) { this.dedupeSeen.add(uid); this.emit('request_cancelled', e); } });
            this.adminChannel.listen('.equipment.returned', (e) => { const uid = e.event_uid || ('equipment_returned:' + (e.request_id || e.requestId || '')); if (!this.dedupeSeen.has(uid)) { this.dedupeSeen.add(uid); this.emit('equipment_returned', e); } });

            this.connected = true;
            console.log('Connected to Laravel Echo facility-requests channel');
            this.emit('connected');

        } catch (error) {
            console.error('Failed to connect to Echo channel:', error);
            this.emit('error', error);
        }
    }

    disconnect() {
        if (this.privateChannel) {
            this.privateChannel.stopListening('.request.created');
            this.privateChannel.stopListening('.request.approved');
            this.privateChannel.stopListening('.request.rejected');
            this.privateChannel.stopListening('.request.cancelled');
            this.privateChannel.stopListening('.equipment.returned');
            window.Echo.leavePrivate(`App.Models.User.${document.querySelector('meta[name="user-id"]')?.content}`);
            this.privateChannel = null;
        }
        if (this.custodianChannel) {
            this.custodianChannel.stopListening('.request.created');
            this.custodianChannel.stopListening('.request.approved');
            this.custodianChannel.stopListening('.request.rejected');
            this.custodianChannel.stopListening('.request.cancelled');
            this.custodianChannel.stopListening('.equipment.returned');
            try { window.Echo.leavePrivate(`facility-requests.custodian.${document.querySelector('meta[name="user-id"]')?.content}`); } catch (e) {}
            this.custodianChannel = null;
        }
        if (this.adminChannel) {
            this.adminChannel.stopListening('.request.created');
            this.adminChannel.stopListening('.request.approved');
            this.adminChannel.stopListening('.request.rejected');
            this.adminChannel.stopListening('.request.cancelled');
            this.adminChannel.stopListening('.equipment.returned');
            window.Echo.leave('facility-requests.admin');
            this.adminChannel = null;
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