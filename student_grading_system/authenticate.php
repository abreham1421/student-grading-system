<?php
// authenticate.php - Role-based authentication handler (No role selection)
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/auth.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validate inputs
if (empty($username) || empty($password)) {
    header('Location: index.php?error=1');
    exit();
}

// Get user from database to check role
$conn = db();
$stmt = $conn->prepare("SELECT username, password, role, full_name, is_active FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header('Location: index.php?error=1');
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!password_verify($password, $user['password'])) {
    header('Location: index.php?error=1');
    exit();
}

// Check if account is active
if ($user['is_active'] != 1) {
    header('Location: index.php?error=1');
    exit();
}

// Get the role from database
$role = $user['role'];

// Update last login
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$updateStmt = $conn->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE username = ?");
$updateStmt->bind_param("ss", $ip, $username);
$updateStmt->execute();
$updateStmt->close();

// Log login attempt
$logStmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, attempt_time) VALUES (?, ?, NOW())");
$logStmt->bind_param("ss", $username, $ip);
$logStmt->execute();
$logStmt->close();

// Set session variables
$_SESSION['logged_in'] = true;
$_SESSION['username'] = $username;
$_SESSION['role'] = $role;
$_SESSION['full_name'] = $user['full_name'];

// Get profile ID based on role
if ($role === 'student') {
    $profileQuery = $conn->query("SELECT student_id FROM student WHERE username = '$username'");
    if ($profileQuery && $profileQuery->num_rows > 0) {
        $profile = $profileQuery->fetch_assoc();
        $_SESSION['profile_id'] = $profile['student_id'];
    }
} elseif ($role === 'teacher') {
    $profileQuery = $conn->query("SELECT teacher_id FROM teacher WHERE username = '$username'");
    if ($profileQuery && $profileQuery->num_rows > 0) {
        $profile = $profileQuery->fetch_assoc();
        $_SESSION['profile_id'] = $profile['teacher_id'];
    }
} elseif ($role === 'admin') {
    $_SESSION['profile_id'] = $username;
}

$conn->close();

// Redirect to role-specific dashboard
$redirect = APP_URL . '/' . $role . '/dashboard.php';
header("Location: $redirect");
exit();
?>