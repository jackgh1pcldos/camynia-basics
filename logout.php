<?php
/**
 * Logout
 * Destroys session and redirects to homepage
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Session::destroy();
redirect('/public/');
