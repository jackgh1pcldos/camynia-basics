<?php
/**
 * Session Management
 * Secure session handling with regeneration and timeout
 */

class Session {
    /**
     * Start session with security settings
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set session configuration
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', SESSION_SECURE ? '1' : '0');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

            session_name('camynia_session');
            session_start();

            // Regenerate session ID periodically
            if (!isset($_SESSION['created'])) {
                self::regenerate();
            } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
                self::regenerate();
            }

            // Check for session timeout
            if (isset($_SESSION['last_activity'])) {
                if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
                    self::destroy();
                    return false;
                }
            }

            $_SESSION['last_activity'] = time();

            return true;
        }
        return true;
    }

    /**
     * Regenerate session ID
     */
    public static function regenerate() {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }

    /**
     * Destroy session
     */
    public static function destroy() {
        $_SESSION = [];

        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        session_destroy();
    }

    /**
     * Set session variable
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    /**
     * Get session variable
     */
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session variable exists
     */
    public static function has($key) {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session variable
     */
    public static function remove($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
}
