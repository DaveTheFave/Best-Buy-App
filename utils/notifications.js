/**
 * Notification Manager Utility
 * Handles browser notifications for hourly pet-feeding reminders
 * Includes fallback to in-app banner if notifications are unavailable
 */

class NotificationManager {
    constructor() {
        this.notificationTimer = null;
        this.intervalTimer = null;
        this.permissionGranted = false;
        this.fallbackBanner = null;
    }

    /**
     * Request notification permission and initialize scheduler
     * @param {Function} onPermissionDenied - Callback when permission is denied
     * @returns {Promise<boolean>} True if permission granted
     */
    async requestPermission(onPermissionDenied = null) {
        // Check if Notification API is supported
        if (!('Notification' in window)) {
            console.warn('Notifications not supported in this browser');
            if (onPermissionDenied) onPermissionDenied();
            return false;
        }

        // Check current permission
        if (Notification.permission === 'granted') {
            this.permissionGranted = true;
            return true;
        } else if (Notification.permission === 'denied') {
            console.warn('Notification permission was denied');
            if (onPermissionDenied) onPermissionDenied();
            return false;
        }

        // Request permission
        try {
            const permission = await Notification.requestPermission();
            this.permissionGranted = permission === 'granted';
            
            if (!this.permissionGranted && onPermissionDenied) {
                onPermissionDenied();
            }
            
            return this.permissionGranted;
        } catch (error) {
            console.error('Error requesting notification permission:', error);
            if (onPermissionDenied) onPermissionDenied();
            return false;
        }
    }

    /**
     * Start hourly notifications (at the top of each hour)
     */
    startHourlyNotifications() {
        // Clear any existing timers
        this.stopNotifications();

        // Calculate milliseconds until next hour
        const now = new Date();
        const nextHour = new Date(now);
        nextHour.setHours(now.getHours() + 1, 0, 0, 0);
        const msUntilNextHour = nextHour - now;

        console.log(`Scheduling first notification in ${Math.round(msUntilNextHour / 1000)} seconds`);

        // Set timeout for first notification at next hour
        this.notificationTimer = setTimeout(() => {
            this.showNotification();
            
            // After first notification, set interval for every hour
            this.intervalTimer = setInterval(() => {
                this.showNotification();
            }, 60 * 60 * 1000); // 60 minutes
        }, msUntilNextHour);
    }

    /**
     * Show a notification (browser or fallback banner)
     */
    showNotification() {
        if (this.permissionGranted && 'Notification' in window) {
            // Show browser notification
            const notification = new Notification('🐾 Time to feed your pet!', {
                body: 'Your pet is hungry! Enter some revenue to keep them happy.',
                icon: 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Ctext%20y%3D%2275%22%20font-size%3D%2275%22%3E%F0%9F%90%BE%3C%2Ftext%3E%3C%2Fsvg%3E',
                tag: 'pet-feeding-reminder',
                requireInteraction: false
            });

            // Auto-close after 10 seconds
            setTimeout(() => notification.close(), 10000);

            // Optional: vibrate if supported
            if ('vibrate' in navigator) {
                navigator.vibrate([200, 100, 200]);
            }
        } else {
            // Fallback: show in-app banner
            this.showFallbackBanner();
        }
    }

    /**
     * Show in-app banner as fallback for notifications
     */
    showFallbackBanner() {
        // Remove existing banner if present
        if (this.fallbackBanner) {
            this.fallbackBanner.remove();
        }

        // Create banner element
        const banner = document.createElement('div');
        banner.className = 'notification-banner';
        banner.innerHTML = `
            <span class="banner-icon">🐾</span>
            <span class="banner-text">Time to feed your pet!</span>
            <button class="banner-close" onclick="this.parentElement.remove()">✕</button>
        `;

        // Add styles if not already present
        if (!document.getElementById('notification-banner-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-banner-styles';
            style.textContent = `
                .notification-banner {
                    position: fixed;
                    top: 20px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: linear-gradient(135deg, #0046be 0%, #0077be 100%);
                    color: white;
                    padding: 15px 25px;
                    border-radius: 10px;
                    box-shadow: 0 4px 15px rgba(0, 70, 190, 0.4);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    animation: slideDown 0.3s ease-out;
                }
                
                @keyframes slideDown {
                    from {
                        transform: translateX(-50%) translateY(-100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(-50%) translateY(0);
                        opacity: 1;
                    }
                }
                
                .banner-icon {
                    font-size: 1.5em;
                }
                
                .banner-text {
                    font-weight: 600;
                }
                
                .banner-close {
                    background: rgba(255, 255, 255, 0.2);
                    border: none;
                    color: white;
                    width: 24px;
                    height: 24px;
                    border-radius: 50%;
                    cursor: pointer;
                    font-size: 14px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: background 0.2s;
                }
                
                .banner-close:hover {
                    background: rgba(255, 255, 255, 0.3);
                }
            `;
            document.head.appendChild(style);
        }

        // Add to page
        document.body.appendChild(banner);
        this.fallbackBanner = banner;

        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (banner.parentElement) {
                banner.remove();
            }
        }, 10000);

        // Optional: vibrate if supported
        if ('vibrate' in navigator) {
            navigator.vibrate([200, 100, 200]);
        }
    }

    /**
     * Stop all notifications and clear timers
     */
    stopNotifications() {
        if (this.notificationTimer) {
            clearTimeout(this.notificationTimer);
            this.notificationTimer = null;
        }
        
        if (this.intervalTimer) {
            clearInterval(this.intervalTimer);
            this.intervalTimer = null;
        }

        // Remove any visible fallback banner
        if (this.fallbackBanner && this.fallbackBanner.parentElement) {
            this.fallbackBanner.remove();
            this.fallbackBanner = null;
        }
    }

    /**
     * Check if notifications are currently active
     * @returns {boolean} True if notifications are scheduled
     */
    isActive() {
        return this.notificationTimer !== null || this.intervalTimer !== null;
    }
}

// Export singleton instance
const notificationManager = new NotificationManager();
