<?php
// fix_passwords.php - Password reset and setup tool for new installations
require_once 'includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Password Reset Tool - Gada Grading System</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #155724;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #0c5460;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #856404;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        th {
            background: #1e3c72;
            color: white;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e9ecef;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .credential-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
            border: 1px solid #dee2e6;
        }
        .credential-card h3 {
            margin: 0 0 10px;
            color: #1e3c72;
        }
        .credential-item {
            display: inline-block;
            background: white;
            padding: 5px 12px;
            border-radius: 20px;
            margin: 5px;
            font-family: monospace;
            border: 1px solid #dee2e6;
        }
        .btn-login {
            display: inline-block;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            margin-top: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,60,114,0.4);
        }
        .btn-close {
            background: #6c757d;
            color: white;
            padding: 8px 20px;
            text-decoration: none;
            border-radius: 20px;
            margin-left: 10px;
            transition: background 0.3s;
        }
        .btn-close:hover {
            background: #5a6268;
        }
        hr {
            margin: 20px 0;
            border-color: #e9ecef;
        }
        code {
            background: #f1f3f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔐 Password Reset Tool</h1>
            <p>Gada Secondary School - Student Grading System</p>
        </div>
        <div class='content'>";

// New password hashes
$admin_hash = password_hash('admin123', PASSWORD_DEFAULT);
$teacher_hash = password_hash('teacher123', PASSWORD_DEFAULT);
$student_hash = password_hash('student123', PASSWORD_DEFAULT);

echo "<div class='info-box'>";
echo "<strong>📌 Updating passwords...</strong><br>";
echo "Resetting passwords for default users...";
echo "</div>";

// Update passwords
$conn->query("UPDATE users SET password = '$admin_hash' WHERE username = 'admin'");
$conn->query("UPDATE users SET password = '$teacher_hash' WHERE username = 'nahil.ahmed'");
$conn->query("UPDATE users SET password = '$student_hash' WHERE username = 'abebe.b'");

echo "<div class='success-box'>";
echo "✅ <strong>Passwords updated successfully!</strong><br>";
echo "All default user passwords have been reset.";
echo "</div>";

// Check if users exist, if not create them
echo "<div class='info-box'>";
echo "<strong>📋 Checking user accounts...</strong><br>";
echo "Verifying that all required user accounts exist...";
echo "</div>";

// Check admin
$admin_check = $conn->query("SELECT * FROM users WHERE username = 'admin'");
if ($admin_check->num_rows == 0) {
    $conn->query("INSERT INTO users (username, password, role, full_name, is_active) 
                  VALUES ('admin', '$admin_hash', 'admin', 'System Administrator', 1)");
    echo "<p>✅ Created admin user</p>";
}

// Check teacher
$teacher_check = $conn->query("SELECT * FROM users WHERE username = 'nahil.ahmed'");
if ($teacher_check->num_rows == 0) {
    $conn->query("INSERT INTO users (username, password, role, full_name, is_active) 
                  VALUES ('nahil.ahmed', '$teacher_hash', 'teacher', 'Mr. Nahil Ahmed', 1)");
    echo "<p>✅ Created teacher user</p>";
}

// Check student
$student_check = $conn->query("SELECT * FROM users WHERE username = 'abebe.b'");
if ($student_check->num_rows == 0) {
    $conn->query("INSERT INTO users (username, password, role, full_name, is_active) 
                  VALUES ('abebe.b', '$student_hash', 'student', 'Abebe Bikila', 1)");
    echo "<p>✅ Created student user</p>";
}

// Verify users exist
echo "<h2>📊 Current Users in Database</h2>";
$users = $conn->query("SELECT username, role, is_active FROM users ORDER BY role, username");
echo "<table>";
echo "<tr><th>Username</th><th>Role</th><th>Status</th></tr>";
while ($user = $users->fetch_assoc()) {
    $status = $user['is_active'] == 1 ? '✅ Active' : '❌ Inactive';
    $role_badge = '';
    if ($user['role'] == 'admin') $role_badge = '👑';
    if ($user['role'] == 'teacher') $role_badge = '👨‍🏫';
    if ($user['role'] == 'student') $role_badge = '🎓';
    echo "<tr>
            <td><strong>{$user['username']}</strong></td>
            <td>{$role_badge} " . ucfirst($user['role']) . "</td>
            <td>{$status}</td>
        </table>";
}
echo "</table>";

echo "<h2>🔑 Login Credentials</h2>";
echo "<div class='credential-card'>";
echo "<h3>👑 Administrator Access</h3>";
echo "<div class='credential-item'><strong>Username:</strong> admin</div>";
echo "<div class='credential-item'><strong>Password:</strong> admin123</div>";
echo "</div>";

echo "<div class='credential-card'>";
echo "<h3>👨‍🏫 Teacher Access</h3>";
echo "<div class='credential-item'><strong>Username:</strong> nahil.ahmed</div>";
echo "<div class='credential-item'><strong>Password:</strong> teacher123</div>";
echo "</div>";

echo "<div class='credential-card'>";
echo "<h3>🎓 Student Access</h3>";
echo "<div class='credential-item'><strong>Username:</strong> abebe.b</div>";
echo "<div class='credential-item'><strong>Password:</strong> student123</div>";
echo "</div>";

echo "<hr>";

echo "<div class='warning-box'>";
echo "<strong>⚠️ Important Setup Notes for New PC:</strong><br><br>";
echo "<strong>1. Database Setup:</strong><br>";
echo "   • Import the database file (gada_grading_system.sql) into MySQL<br>";
echo "   • Make sure database name matches 'includes/config.php'<br><br>";
echo "<strong>2. Web Server:</strong><br>";
echo "   • Place files in XAMPP/WAMP htdocs folder<br>";
echo "   • Start Apache and MySQL services<br><br>";
echo "<strong>3. XAMPP Setup:</strong><br>";
echo "   • Install XAMPP from https://www.apachefriends.org/<br>";
echo "   • Copy this folder to <code>C:\\xampp\\htdocs\\student_grading_system\\</code><br>";
echo "   • Start Apache and MySQL from XAMPP Control Panel<br>";
echo "   • Open phpMyAdmin and import the database<br><br>";
echo "<strong>4. Database Configuration:</strong><br>";
echo "   • Open <code>includes/config.php</code><br>";
echo "   • Verify database credentials:<br>";
echo "   <code>define('DB_HOST', 'localhost');</code><br>";
echo "   <code>define('DB_USER', 'root');</code><br>";
echo "   <code>define('DB_PASS', '');</code><br>";
echo "   <code>define('DB_NAME', 'gada_grading_system');</code><br>";
echo "</div>";

echo "<div style='text-align: center;'>";
echo "<a href='index.php' class='btn-login'>🚀 Go to Login Page →</a>";
echo "<a href='javascript:window.close()' class='btn-close'>✖ Close</a>";
echo "</div>";

echo "</div></div></body></html>";

$conn->close();
?>