<?php
/**
 * Core Configuration
 * Loads environment variables and defines constants
 */

// Prevent direct access
defined('CAMYNIA_LOADED') or define('CAMYNIA_LOADED', true);

// Check for generated config first (created by installer)
$generatedConfigPath = __DIR__ . '/config-generated.php';
if (file_exists($generatedConfigPath)) {
    require_once $generatedConfigPath;
} else {
    // Fallback to .env file for backwards compatibility or manual setup
    function loadEnv($path) {
        if (!file_exists($path)) {
            die('Configuration file missing. Please run the installer at /installer/ or copy .env.example to .env and configure it.');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                $value = trim($value, '"\'');

                // Set as environment variable and constant
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    // Load .env file
    loadEnv(__DIR__ . '/../../.env');
}

// Helper function to get env variables
function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

// Database Constants
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'camynia'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Application Constants
define('APP_NAME', env('APP_NAME', 'Camynia'));
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', env('APP_DEBUG', 'false') === 'true');
define('APP_URL', env('APP_URL', ''));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'UTC'));

// Path Constants
define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('PUBLIC_PATH', ROOT_PATH . '/public_html');
define('INCLUDES_PATH', PUBLIC_PATH . '/includes');
define('ASSETS_PATH', PUBLIC_PATH . '/assets');

// Session Configuration
define('SESSION_LIFETIME', (int)env('SESSION_LIFETIME', 7200));
define('SESSION_SECURE', env('SESSION_SECURE', 'true') === 'true');
define('SESSION_HTTPONLY', env('SESSION_HTTPONLY', 'true') === 'true');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/error.log');
}

// Set session parameters
ini_set('session.cookie_httponly', SESSION_HTTPONLY ? '1' : '0');
ini_set('session.cookie_secure', SESSION_SECURE ? '1' : '0');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_samesite', 'Lax');
