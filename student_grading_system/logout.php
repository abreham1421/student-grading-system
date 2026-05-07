<?php
// logout.php - Logout handler
require_once 'includes/init.php';

// Call the logout method from the Auth class
auth()->logout();

// Redirect to login page
header('Location: index.php');
exit();
?>