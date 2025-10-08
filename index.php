<?php
/**
 * Main Entry Point
 * Redirects to appropriate portal based on user status
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

// Check if system is installed
$checkInstall = true;
try {
    $db = Database::getInstance()->getConnection();
    // Check if suppliers table exists
    $result = $db->query("SHOW TABLES LIKE 'suppliers'");
    if ($result->rowCount() == 0) {
        $checkInstall = false;
    }
} catch (Exception $e) {
    $checkInstall = false;
}

if (!$checkInstall) {
    // Redirect to installer
    redirect('/installer/');
}

// If user is logged in, redirect to their portal
if (isLoggedIn()) {
    $user = currentUser();

    if ($user['is_supplier']) {
        redirect('/supplier/');
    } else {
        redirect('/staff/');
    }
} else {
    // Show public homepage
    redirect('/public/');
}
