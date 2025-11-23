/**
 * Cookie Management Utility
 * Provides functions to set, get, and delete cookies with security best practices
 */

/**
 * Set a cookie with security flags
 * @param {string} name - Cookie name
 * @param {string} value - Cookie value
 * @param {number} days - Expiration in days (default: 7)
 */
function setCookie(name, value, days = 7) {
    const maxAge = days * 24 * 60 * 60; // Convert days to seconds
    const isSecure = window.location.protocol === 'https:';
    
    // Build cookie string with security flags
    let cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}`;
    cookie += `; path=/`;
    cookie += `; max-age=${maxAge}`;
    cookie += `; SameSite=Lax`;
    
    // Add Secure flag if using HTTPS
    if (isSecure) {
        cookie += `; Secure`;
    }
    
    document.cookie = cookie;
}

/**
 * Get a cookie value by name
 * @param {string} name - Cookie name
 * @returns {string|null} Cookie value or null if not found
 */
function getCookie(name) {
    const nameEQ = encodeURIComponent(name) + "=";
    const cookies = document.cookie.split(';');
    
    for (let i = 0; i < cookies.length; i++) {
        let cookie = cookies[i].trim();
        if (cookie.indexOf(nameEQ) === 0) {
            return decodeURIComponent(cookie.substring(nameEQ.length));
        }
    }
    return null;
}

/**
 * Delete a cookie by name
 * @param {string} name - Cookie name
 */
function deleteCookie(name) {
    // Set cookie with past expiration date
    document.cookie = `${encodeURIComponent(name)}=; path=/; max-age=0`;
}

/**
 * Check if cookies are available
 * @returns {boolean} True if cookies are available
 */
function areCookiesAvailable() {
    try {
        // Try to set and read a test cookie
        const testKey = '_cookie_test_';
        setCookie(testKey, 'test', 1);
        const result = getCookie(testKey) === 'test';
        deleteCookie(testKey);
        return result;
    } catch (e) {
        return false;
    }
}
