/**
 * NotificationManager - Manages hourly pet-feeding notifications with session and time restrictions
 * 
 * Features:
 * - Schedules notifications only during allowed hours (e.g., 09:00-21:00)
 * - Respects employee session windows (shift start/end times)
 * - Automatically stops notifications at session end
 * - Supports both browser notifications and fallback banner notifications
 */
class NotificationManager {
    // Constants
    static DEFAULT_START_HOUR = 9;
    static DEFAULT_END_HOUR = 21;
    static BANNER_AUTO_HIDE_MS = 5000;
    static BANNER_ANIMATION_MS = 300;
    static MS_PER_SECOND = 1000;
    static MS_PER_MINUTE = 60000;
    static MS_PER_HOUR = 3600000;
    static MINUTES_PER_HOUR = 60;
    static HOURS_PER_DAY = 24;

    constructor(options = {}) {
        this.options = {
            title: options.title || 'Pet Feeding Reminder',
            message: options.message || 'Time to check on your pet!',
            icon: options.icon || null,
            allowedHours: options.allowedHours || { 
                startHour: NotificationManager.DEFAULT_START_HOUR, 
                endHour: NotificationManager.DEFAULT_END_HOUR 
            },
            useFallbackBanner: options.useFallbackBanner !== false,
            onNotification: options.onNotification || null
        };
        
        this.timers = [];
        this.isRunning = false;
        this.sessionStart = null;
        this.sessionEnd = null;
        this.checkSessionActive = null;
    }

    /**
     * Start the notification manager with optional session information
     * @param {Object} sessionInfo - Optional session configuration
     * @param {Date|string} sessionInfo.sessionStart - Session start timestamp
     * @param {Date|string} sessionInfo.sessionEnd - Session end timestamp
     * @param {Function} sessionInfo.checkSessionActive - Function to check if session is active
     */
    start(sessionInfo = {}) {
        if (this.isRunning) {
            console.log('NotificationManager already running');
            return;
        }

        // Parse session information
        if (sessionInfo.sessionStart) {
            this.sessionStart = new Date(sessionInfo.sessionStart);
        }
        if (sessionInfo.sessionEnd) {
            this.sessionEnd = new Date(sessionInfo.sessionEnd);
        }
        if (typeof sessionInfo.checkSessionActive === 'function') {
            this.checkSessionActive = sessionInfo.checkSessionActive;
        }

        this.isRunning = true;
        console.log('NotificationManager started', {
            sessionStart: this.sessionStart,
            sessionEnd: this.sessionEnd,
            allowedHours: this.options.allowedHours
        });

        // Request notification permission if using browser notifications
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Schedule first notification
        this._scheduleNextNotification();

        // Set timer to stop at session end if provided
        if (this.sessionEnd) {
            const msUntilSessionEnd = this.sessionEnd.getTime() - Date.now();
            if (msUntilSessionEnd > 0) {
                const sessionEndTimer = setTimeout(() => {
                    console.log('Session ended, stopping notifications');
                    this.stop();
                }, msUntilSessionEnd);
                this.timers.push(sessionEndTimer);
            }
        }
    }

    /**
     * Stop all notifications and clear timers
     */
    stop() {
        console.log('Stopping NotificationManager');
        this.isRunning = false;
        this.timers.forEach(timer => clearTimeout(timer));
        this.timers = [];
        this.sessionStart = null;
        this.sessionEnd = null;
        this.checkSessionActive = null;
    }

    /**
     * Schedule the next notification at the top of the hour
     * @private
     */
    _scheduleNextNotification() {
        if (!this.isRunning) {
            return;
        }

        const now = new Date();
        const msUntilNext = this._msUntilNextTopOfHourWithinWindow(
            now,
            this.options.allowedHours,
            this.sessionEnd
        );

        if (msUntilNext === null) {
            console.log('No more notifications to schedule (outside window or session)');
            return;
        }

        console.log(`Next notification scheduled in ${Math.round(msUntilNext / 1000 / 60)} minutes`);

        const timer = setTimeout(() => {
            this._showNotification();
            this._scheduleNextNotification();
        }, msUntilNext);

        this.timers.push(timer);
    }

    /**
     * Show a notification (browser or fallback banner)
     * @private
     */
    _showNotification() {
        const now = new Date();

        // Check if within allowed hours and session
        if (!this._isWithinAllowedHours(now)) {
            console.log('Notification skipped: outside allowed hours');
            return;
        }

        if (!this._isWithinSession(now)) {
            console.log('Notification skipped: outside session window');
            return;
        }

        // Call custom callback if provided
        if (typeof this.options.onNotification === 'function') {
            this.options.onNotification();
        }

        // Try browser notification first
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(this.options.title, {
                body: this.options.message,
                icon: this.options.icon,
                tag: 'pet-feeding-reminder'
            });
        } 
        // Fallback to banner notification
        else if (this.options.useFallbackBanner) {
            this._showFallbackBanner();
        }
    }

    /**
     * Show a fallback banner notification
     * @private
     */
    _showFallbackBanner() {
        // Create banner element if it doesn't exist
        let banner = document.getElementById('notification-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'notification-banner';
            banner.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 15px 20px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                z-index: 10000;
                max-width: 300px;
                animation: slideIn 0.3s ease-out;
                cursor: pointer;
            `;
            document.body.appendChild(banner);

            // Add animation styles
            if (!document.getElementById('notification-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-styles';
                style.textContent = `
                    @keyframes slideIn {
                        from { transform: translateX(400px); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOut {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(400px); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }
        }

        banner.innerHTML = `
            <div style="font-weight: 600; margin-bottom: 5px;">${this.options.title}</div>
            <div style="font-size: 0.9em; opacity: 0.95;">${this.options.message}</div>
        `;
        banner.style.display = 'block';

        // Auto-hide after configured time
        setTimeout(() => {
            banner.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                banner.style.display = 'none';
            }, NotificationManager.BANNER_ANIMATION_MS);
        }, NotificationManager.BANNER_AUTO_HIDE_MS);

        // Click to dismiss
        banner.onclick = () => {
            banner.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                banner.style.display = 'none';
            }, NotificationManager.BANNER_ANIMATION_MS);
        };
    }

    /**
     * Check if a given date is within allowed hours
     * @param {Date} date - Date to check
     * @returns {boolean}
     * @private
     */
    _isWithinAllowedHours(date) {
        const hour = date.getHours();
        const { startHour, endHour } = this.options.allowedHours;
        return hour >= startHour && hour < endHour;
    }

    /**
     * Check if a given date is within the session window
     * @param {Date} date - Date to check
     * @returns {boolean}
     * @private
     */
    _isWithinSession(date) {
        // If custom session check function is provided, use it
        if (this.checkSessionActive) {
            return this.checkSessionActive();
        }

        // Otherwise check against session start/end times
        const timestamp = date.getTime();
        
        if (this.sessionStart && timestamp < this.sessionStart.getTime()) {
            return false;
        }
        
        if (this.sessionEnd && timestamp >= this.sessionEnd.getTime()) {
            return false;
        }
        
        return true;
    }

    /**
     * Calculate milliseconds until the next top-of-hour within allowed hours and session
     * @param {Date} date - Current date
     * @param {Object} allowedHours - Allowed hours configuration
     * @param {Date} sessionEnd - Optional session end time
     * @returns {number|null} Milliseconds until next valid notification time, or null if none
     * @private
     */
    _msUntilNextTopOfHourWithinWindow(date, allowedHours, sessionEnd) {
        const now = date.getTime();
        const currentHour = date.getHours();
        const currentMinute = date.getMinutes();
        const currentSecond = date.getSeconds();
        const currentMs = date.getMilliseconds();

        // Calculate ms to next top of hour
        const msToNextHour = (NotificationManager.MINUTES_PER_HOUR - currentMinute) * NotificationManager.MS_PER_MINUTE - 
                            currentSecond * NotificationManager.MS_PER_SECOND - currentMs;
        
        // Start checking from the next top of hour
        let checkDate = new Date(now + msToNextHour);
        
        // Try up to 24 hours ahead to find a valid slot
        for (let i = 0; i < NotificationManager.HOURS_PER_DAY; i++) {
            const checkHour = checkDate.getHours();
            const checkTime = checkDate.getTime();

            // Check if within allowed hours
            if (checkHour >= allowedHours.startHour && checkHour < allowedHours.endHour) {
                // Check if within session window
                if (sessionEnd && checkTime >= sessionEnd.getTime()) {
                    return null; // No valid time within session
                }

                // Valid time found
                return checkTime - now;
            }

            // Move to next hour
            checkDate = new Date(checkDate.getTime() + NotificationManager.MS_PER_HOUR);
        }

        // No valid time found within 24 hours
        return null;
    }

    /**
     * Get current status of the notification manager
     * @returns {Object} Status information
     */
    getStatus() {
        return {
            isRunning: this.isRunning,
            sessionStart: this.sessionStart,
            sessionEnd: this.sessionEnd,
            allowedHours: this.options.allowedHours,
            activeTimers: this.timers.length
        };
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationManager;
}
