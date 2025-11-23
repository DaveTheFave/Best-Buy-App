/**
 * Session Management Utility
 * Handles persistent employee login using cookies with localStorage fallback
 */

const SESSION_COOKIE_NAME = 'bestbuy_employee_session';
const SESSION_STORAGE_KEY = 'bestbuy_employee_session';
const SESSION_EXPIRATION_DAYS = 7;
const SESSION_EXPIRATION_MS = SESSION_EXPIRATION_DAYS * 24 * 60 * 60 * 1000;

/**
 * Save employee session to cookies (or localStorage as fallback)
 * @param {Object} userData - User data to persist
 * @param {number} userData.id - User ID
 * @param {string} userData.username - Username
 * @param {string} userData.name - User full name
 * @param {boolean} userData.is_admin - Admin flag
 */
function saveSession(userData) {
    const sessionData = JSON.stringify({
        id: userData.id,
        username: userData.username,
        name: userData.name,
        is_admin: userData.is_admin,
        timestamp: Date.now()
    });
    
    // Try to save to cookie first
    if (areCookiesAvailable()) {
        setCookie(SESSION_COOKIE_NAME, sessionData, SESSION_EXPIRATION_DAYS);
    } else {
        // Fallback to localStorage
        try {
            localStorage.setItem(SESSION_STORAGE_KEY, sessionData);
        } catch (e) {
            console.warn('Failed to save session to localStorage:', e);
        }
    }
}

/**
 * Restore employee session from cookies or localStorage
 * @returns {Object|null} User data or null if no session found
 */
function restoreSession() {
    let sessionData = null;
    
    // Try to restore from cookie first
    const cookieData = getCookie(SESSION_COOKIE_NAME);
    if (cookieData) {
        try {
            sessionData = JSON.parse(cookieData);
        } catch (e) {
            console.warn('Failed to parse session cookie:', e);
        }
    }
    
    // Fallback to localStorage if cookie not found
    if (!sessionData) {
        try {
            const storageData = localStorage.getItem(SESSION_STORAGE_KEY);
            if (storageData) {
                sessionData = JSON.parse(storageData);
            }
        } catch (e) {
            console.warn('Failed to restore session from localStorage:', e);
        }
    }
    
    // Validate session data (check if expired)
    if (sessionData) {
        const isExpired = Date.now() - sessionData.timestamp > SESSION_EXPIRATION_MS;
        
        if (isExpired) {
            clearSession();
            return null;
        }
        
        return sessionData;
    }
    
    return null;
}

/**
 * Clear employee session from all storage
 */
function clearSession() {
    // Clear cookie
    deleteCookie(SESSION_COOKIE_NAME);
    
    // Clear localStorage
    try {
        localStorage.removeItem(SESSION_STORAGE_KEY);
    } catch (e) {
        console.warn('Failed to clear session from localStorage:', e);
    }
}

/**
 * Check if there's an active session
 * @returns {boolean} True if session exists and is valid
 */
function hasActiveSession() {
    return restoreSession() !== null;
}
