import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
import './websocket';

const userId = document.head.querySelector('meta[name="user-id"]')?.content;

if (userId && window.Echo) {
    window.Echo.private(`App.Models.User.${userId}`)
        .notification((notification) => {
            console.log('Realtime notification received:', notification);

            // Update notification badge in header
            const countEl = document.getElementById('notification-count');
            if (countEl) {
                const current = parseInt(countEl.textContent || '0', 10) || 0;
                const next = current + 1;
                countEl.textContent = next > 9 ? '9+' : next;
            }

            // Optionally refresh page when request/custodian data changes
            // (useful to keep request tables in sync without manual reload)
            if (window.location.pathname.includes('/requestor') || window.location.pathname.includes('/custodian')) {
                window.location.reload();
            }
        })
        .listen('Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', (event) => {
            console.log('BroadcastNotificationCreated event:', event);
        });
}

// WebSocket event listeners for facility request updates
if (window.PitfrWebSocket) {
    window.PitfrWebSocket.on('request_created', (data) => {
        console.log('New request created:', data);
        // Show toast notification
        showToast(`New request ${data.control_number} created by ${data.user_name}`, 'info');

        // Refresh tables if on relevant pages
        if (window.location.pathname.includes('/admin') || window.location.pathname.includes('/custodian')) {
            // Small delay to allow backend processing
            setTimeout(() => {
                if (typeof refreshRequestsTable === 'function') {
                    refreshRequestsTable();
                } else {
                    window.location.reload();
                }
            }, 1000);
        }
    });

    window.PitfrWebSocket.on('request_approved', (data) => {
        console.log('Request approved:', data);
        showToast(`Request ${data.control_number} ${data.approval_type} approved by ${data.user_name}`, 'success');

        if (window.location.pathname.includes('/requestor') || window.location.pathname.includes('/custodian')) {
            setTimeout(() => {
                if (typeof refreshRequestsTable === 'function') {
                    refreshRequestsTable();
                } else {
                    window.location.reload();
                }
            }, 1000);
        }
    });

    window.PitfrWebSocket.on('request_rejected', (data) => {
        console.log('Request rejected:', data);
        showToast(`Request ${data.control_number} ${data.rejection_type} rejected by ${data.user_name}`, 'error');

        if (window.location.pathname.includes('/requestor') || window.location.pathname.includes('/custodian')) {
            setTimeout(() => {
                if (typeof refreshRequestsTable === 'function') {
                    refreshRequestsTable();
                } else {
                    window.location.reload();
                }
            }, 1000);
        }
    });

    window.PitfrWebSocket.on('request_cancelled', (data) => {
        console.log('Request cancelled:', data);
        showToast(`Request ${data.control_number} cancelled by ${data.user_name}`, 'warning');

        if (window.location.pathname.includes('/admin') || window.location.pathname.includes('/custodian')) {
            setTimeout(() => {
                if (typeof refreshRequestsTable === 'function') {
                    refreshRequestsTable();
                } else {
                    window.location.reload();
                }
            }, 1000);
        }
    });

    window.PitfrWebSocket.on('equipment_returned', (data) => {
        console.log('Equipment returned:', data);
        showToast(`Equipment returned for request ${data.control_number} by ${data.user_name}`, 'success');

        if (window.location.pathname.includes('/custodian') || window.location.pathname.includes('/admin')) {
            setTimeout(() => {
                if (typeof refreshRequestsTable === 'function') {
                    refreshRequestsTable();
                } else {
                    window.location.reload();
                }
            }, 1000);
        }
    });
}

// Simple toast notification function
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.setAttribute('aria-atomic', 'true');
    toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-lg text-white font-medium shadow-lg transform transition-all duration-300 translate-x-full`;

    // Set color based on type
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    toast.classList.add(colors[type] || colors.info);

    toast.textContent = message;
    document.body.appendChild(toast);

    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
    }, 100);

    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 5000);
}

