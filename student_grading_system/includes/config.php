<?php
// includes/config.php - Configuration file

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gada_grading_system');

// Application Configuration
define('APP_NAME', 'Gada Secondary School Grading System');
define('APP_URL', 'http://localhost/student_grading_system');
define('APP_ENV', 'development'); // development or production
define('APP_DEBUG', true);

// Academic Year Configuration
define('CURRENT_ACADEMIC_YEAR', '2025/26');
define('CURRENT_ACADEMIC_YEAR_ID', 2);
define('CURRENT_SEMESTER', 1);

// Time Zone
date_default_timezone_set('Africa/Addis_Ababa');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Security
define('SESSION_LIFETIME', 7200);
define('PASSWORD_MIN_LENGTH', 6);

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>