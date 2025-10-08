<?php
/**
 * Helper Functions
 * Common utility functions used throughout the application
 */

/**
 * Escape HTML output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user
 */
function currentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Check if current user is supplier
 */
function isSupplier() {
    return isset($_SESSION['user']) && $_SESSION['user']['is_supplier'] == 1;
}

/**
 * Format date
 */
function formatDate($date, $format = 'M j, Y') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($datetime, $format = 'M j, Y g:i A') {
    if (!$datetime) return '-';
    return date($format, strtotime($datetime));
}

/**
 * Time ago format
 */
function timeAgo($datetime) {
    if (!$datetime) return '-';

    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime);
    }
}

/**
 * Flash messages
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Truncate string
 */
function truncate($string, $length = 100, $append = '...') {
    if (strlen($string) <= $length) {
        return $string;
    }
    return substr($string, 0, $length) . $append;
}

/**
 * Generate random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Sanitize filename
 */
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return $filename;
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'USD') {
    return '$' . number_format($amount, 2);
}

/**
 * Check if module is enabled for company
 */
function isModuleEnabled($companyId, $moduleKey) {
    // This will be implemented with proper database checks
    // For now, return true
    return true;
}

/**
 * Get user initials
 */
function getInitials($firstName, $lastName) {
    return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
}

/**
 * Debug helper (only in debug mode)
 */
function dd($data) {
    if (APP_DEBUG) {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
        die();
    }
}

/**
 * Asset URL helper
 */
function asset($path) {
    return APP_URL . '/assets/' . ltrim($path, '/');
}

/**
 * URL helper
 */
function url($path = '') {
    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * Content Moderation - Check for blacklisted words
 * Returns array with: ['is_clean' => bool, 'found_words' => array, 'highest_severity' => string]
 */
function checkBlacklistedWords($text) {
    global $pdo;

    if (empty($text)) {
        return ['is_clean' => true, 'found_words' => [], 'highest_severity' => null];
    }

    // Get all active blacklisted words
    static $blacklistedWords = null;

    if ($blacklistedWords === null) {
        $stmt = $pdo->prepare("SELECT word, severity FROM blacklisted_words WHERE is_active = 1");
        $stmt->execute();
        $blacklistedWords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $text = strtolower($text);
    $foundWords = [];
    $severityLevels = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
    $highestSeverity = null;
    $highestSeverityLevel = 0;

    foreach ($blacklistedWords as $entry) {
        $word = strtolower($entry['word']);
        $severity = $entry['severity'];

        // Check if word exists as whole word or part of text
        if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $text)) {
            $foundWords[] = [
                'word' => $word,
                'severity' => $severity
            ];

            $level = $severityLevels[$severity] ?? 0;
            if ($level > $highestSeverityLevel) {
                $highestSeverityLevel = $level;
                $highestSeverity = $severity;
            }
        }
    }

    return [
        'is_clean' => empty($foundWords),
        'found_words' => $foundWords,
        'highest_severity' => $highestSeverity
    ];
}

/**
 * Filter/censor blacklisted words from text
 */
function filterBlacklistedWords($text, $replacement = '***') {
    global $pdo;

    if (empty($text)) {
        return $text;
    }

    // Get all active blacklisted words
    static $blacklistedWords = null;

    if ($blacklistedWords === null) {
        $stmt = $pdo->prepare("SELECT word FROM blacklisted_words WHERE is_active = 1");
        $stmt->execute();
        $blacklistedWords = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    foreach ($blacklistedWords as $word) {
        $text = preg_replace('/\b' . preg_quote($word, '/') . '\b/i', $replacement, $text);
    }

    return $text;
}
