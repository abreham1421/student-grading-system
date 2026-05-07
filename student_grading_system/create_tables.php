<?php
// create_tables.php - Run this file to create missing tables
require_once 'includes/config.php';
require_once 'includes/db_connection.php';

$conn = db();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Create Database Tables</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; background: #f4f7fc; }
        .container { max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #1e3c72; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 Database Table Creator</h1>";

// Create academic_year table
echo "<h3>Creating academic_year table...</h3>";
$sql = "CREATE TABLE IF NOT EXISTS academic_year (
    year_id INT PRIMARY KEY AUTO_INCREMENT,
    year_name VARCHAR(20) UNIQUE NOT NULL,
    start_date DATE,
    end_date DATE,
    is_current BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql)) {
    echo "<div class='success'>✓ academic_year table created successfully</div>";
    // Insert default year
    $conn->query("INSERT INTO academic_year (year_name, start_date, end_date, is_current) 
                  SELECT '2024/25', '2024-09-01', '2025-06-30', 1 
                  WHERE NOT EXISTS (SELECT 1 FROM academic_year)");
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}

// Create semester table
echo "<h3>Creating semester table...</h3>";
$sql = "CREATE TABLE IF NOT EXISTS semester (
    semester_id INT PRIMARY KEY AUTO_INCREMENT,
    year_id INT NOT NULL,
    semester_number INT NOT NULL,
    semester_name VARCHAR(50),
    start_date DATE,
    end_date DATE,
    is_current BOOLEAN DEFAULT FALSE
)";
if ($conn->query($sql)) {
    echo "<div class='success'>✓ semester table created successfully</div>";
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}

// Create audit_log table
echo "<h3>Creating audit_log table...</h3>";
$sql = "CREATE TABLE IF NOT EXISTS audit_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_data TEXT,
    new_data TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql)) {
    echo "<div class='success'>✓ audit_log table created successfully</div>";
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}

// Create login_attempts table
echo "<h3>Creating login_attempts table...</h3>";
$sql = "CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql)) {
    echo "<div class='success'>✓ login_attempts table created successfully</div>";
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}

// Create assessment_type table
echo "<h3>Creating assessment_type table...</h3>";
$sql = "CREATE TABLE IF NOT EXISTS assessment_type (
    assessment_id INT PRIMARY KEY AUTO_INCREMENT,
    assessment_name VARCHAR(50) UNIQUE NOT NULL,
    default_weight DECIMAL(5,2),
    description TEXT
)";
if ($conn->query($sql)) {
    echo "<div class='success'>✓ assessment_type table created successfully</div>";
    // Insert data
    $conn->query("INSERT IGNORE INTO assessment_type (assessment_name, default_weight) VALUES
        ('Quiz', 10),
        ('Assignment', 10),
        ('Class Work', 5),
        ('Project', 15),
        ('Mid Exam', 20),
        ('Final Exam', 40)");
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}

// Create final_grade table
echo "<h3>Creating final_grade table...</h3>";
$sql = "CREATE TABLE IF NOT EXISTS final_grade (
    final_grade_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    academic_year_id INT,
    semester_id INT,
    total_score DECIMAL(5,2),
    letter_grade VARCHAR(2),
    grade_point DECIMAL(3,2),
    remarks VARCHAR(100),
    is_published BOOLEAN DEFAULT FALSE,
    published_date DATE,
    calculated_date DATE
)";
if ($conn->query($sql)) {
    echo "<div class='success'>✓ final_grade table created successfully</div>";
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}

// Create notification table
echo "<h3>Creating notification table...</h3>";
$sql = "CREATE TABLE IF NOT EXISTS notification (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    type VARCHAR(20) DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql)) {
    echo "<div class='success'>✓ notification table created successfully</div>";
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}

// Create system_settings table
echo "<h3>Creating system_settings table...</h3>";
$sql = "CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'text',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if ($conn->query($sql)) {
    echo "<div class='success'>✓ system_settings table created successfully</div>";
    $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
        ('passing_mark', '50', 'number', 'Minimum passing mark'),
        ('max_score', '100', 'number', 'Maximum score per assessment')");
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}

// Create indexes
echo "<h3>Creating indexes for performance...</h3>";
$indexes = [
    "CREATE INDEX idx_mark_student ON mark(student_id)",
    "CREATE INDEX idx_mark_subject ON mark(subject_id)",
    "CREATE INDEX idx_mark_academic_year ON mark(academic_year_id)",
    "CREATE INDEX idx_audit_user ON audit_log(user_id)",
    "CREATE INDEX idx_audit_created ON audit_log(created_at)"
];
foreach ($indexes as $index) {
    @$conn->query($index);
}
echo "<div class='info'>✓ Indexes created (or already exist)</div>";

echo "<h2>✅ All tables are ready!</h2>";
echo "<div class='info'>You can now use the system.</div>";
echo "<p><a href='index.php' class='btn btn-primary'>Go to Login</a></p>";
echo "</div></body></html>";
?>