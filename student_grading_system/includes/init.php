<?php
// includes/init.php - Central initialization file

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration
require_once __DIR__ . '/config.php';

// Load database connection
require_once __DIR__ . '/db_connection.php';

// Load authentication
require_once __DIR__ . '/auth.php';

// Load global functions
require_once __DIR__ . '/functions.php';
?>