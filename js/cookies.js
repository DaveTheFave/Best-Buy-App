/**
 * Cookie and Session Persistence Utilities
 * 
 * Handles storing and retrieving session data including shift times
 * 
 * IMPORTANT SECURITY NOTES:
 * - These are client-side cookies and can be manipulated by users
 * - Not HTTP-only, vulnerable to XSS attacks
 * - For production, use server-side session management
 * - This is a demonstration/prototype implementation only
 */

const SessionStorage = {
    /**
     * Save session data to localStorage
     * @param {Object} session - Session object with shiftStart, shiftEnd, etc.
     */
    saveSession(session) {
        try {
            const sessionData = {
                ...session,
                savedAt: new Date().toISOString()
            };
            localStorage.setItem('adminSession', JSON.stringify(sessionData));
            console.log('Session saved to localStorage', sessionData);
            
            // Development warning
            if (typeof console !== 'undefined') {
                console.warn('⚠️ SECURITY NOTICE: Using client-side session storage. Not suitable for production!');
            }
        } catch (error) {
            console.error('Error saving session:', error);
        }
    },

    /**
     * Load session data from localStorage
     * @returns {Object|null} Session object or null if not found/expired
     */
    loadSession() {
        try {
            const sessionJson = localStorage.getItem('adminSession');
            if (!sessionJson) {
                return null;
            }

            const session = JSON.parse(sessionJson);
            
            // Check if session has expired
            if (session.shiftEnd) {
                const shiftEnd = new Date(session.shiftEnd);
                if (Date.now() >= shiftEnd.getTime()) {
                    console.log('Session expired, clearing');
                    this.clearSession();
                    return null;
                }
            }

            console.log('Session loaded from localStorage', session);
            return session;
        } catch (error) {
            console.error('Error loading session:', error);
            return null;
        }
    },

    /**
     * Clear session data from localStorage
     */
    clearSession() {
        try {
            localStorage.removeItem('adminSession');
            console.log('Session cleared from localStorage');
        } catch (error) {
            console.error('Error clearing session:', error);
        }
    },

    /**
     * Check if a session exists and is valid
     * @returns {boolean}
     */
    hasValidSession() {
        const session = this.loadSession();
        return session !== null;
    },

    /**
     * Update specific session fields
     * @param {Object} updates - Fields to update
     */
    updateSession(updates) {
        const session = this.loadSession();
        if (session) {
            this.saveSession({ ...session, ...updates });
        }
    }
};

/**
 * Legacy cookie utilities (kept for compatibility)
 * Note: Using localStorage is preferred for this use case
 */
const CookieUtils = {
    /**
     * Set a cookie
     * @param {string} name - Cookie name
     * @param {string} value - Cookie value
     * @param {number} days - Expiration in days
     */
    setCookie(name, value, days = 1) {
        const expires = new Date();
        expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
        document.cookie = `${name}=${encodeURIComponent(value)};expires=${expires.toUTCString()};path=/`;
    },

    /**
     * Get a cookie value
     * @param {string} name - Cookie name
     * @returns {string|null} Cookie value or null
     */
    getCookie(name) {
        const nameEQ = name + '=';
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            cookie = cookie.trim();
            if (cookie.indexOf(nameEQ) === 0) {
                return decodeURIComponent(cookie.substring(nameEQ.length));
            }
        }
        return null;
    },

    /**
     * Delete a cookie
     * @param {string} name - Cookie name
     */
    deleteCookie(name) {
        document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/`;
    }
};

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SessionStorage, CookieUtils };
}
