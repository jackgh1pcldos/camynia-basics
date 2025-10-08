<?php
/**
 * Application Bootstrap
 * Loads all core components in the correct order
 */

// Define that Camynia is loaded
define('CAMYNIA_LOADED', true);

// Load configuration
require_once __DIR__ . '/config.php';

// Load database
require_once __DIR__ . '/db.php';

// Create global PDO instance for legacy code compatibility
$pdo = Database::getInstance()->getConnection();

// Load session management
require_once __DIR__ . '/session.php';

// Load helper functions
require_once __DIR__ . '/helpers.php';

// Load helpdesk email functions
if (file_exists(__DIR__ . '/helpdesk-emails.php')) {
    require_once __DIR__ . '/helpdesk-emails.php';
}

// Load finance audit functions
if (file_exists(__DIR__ . '/finance-audit.php')) {
    require_once __DIR__ . '/finance-audit.php';
}

// Start session
Session::start();

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
