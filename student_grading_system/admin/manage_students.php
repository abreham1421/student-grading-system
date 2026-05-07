<?php
// admin/manage_students.php - Modern attractive table design with grade-level separation
$pageTitle = 'Manage Students';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$message = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $student_id = $conn->real_escape_string($_GET['delete']);
    
    // Get username first
    $userResult = $conn->query("SELECT username FROM student WHERE student_id = '$student_id'");
    if ($userResult && $userResult->num_rows > 0) {
        $student = $userResult->fetch_assoc();
        $username = $student['username'];
        
        // Delete from student table
        $conn->query("DELETE FROM student WHERE student_id = '$student_id'");
        // Delete from users table
        $conn->query("DELETE FROM users WHERE username = '$username'");
        $message = "Student deleted successfully!";
    } else {
        $error = "Student not found!";
    }
}

// Handle Add Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $student_id = strtoupper(trim($_POST['student_id']));
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $sex = $_POST['sex'];
    $current_grade_level = $_POST['current_grade_level'];
    $current_section = strtoupper(trim($_POST['current_section']));
    $field_of_study = trim($_POST['field_of_study'] ?? 'General');
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    
    $errors = [];
    
    // Validate required fields
    if (empty($student_id)) $errors[] = "Student ID is required.";
    if (empty($username)) $errors[] = "Username is required.";
    if (empty($email)) $errors[] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (empty($full_name)) $errors[] = "Full name is required.";
    
    // Check if username already exists
    $checkUser = $conn->query("SELECT username FROM users WHERE username = '$username'");
    if ($checkUser && $checkUser->num_rows > 0) $errors[] = "Username already exists!";
    
    // Check if email already exists
    $checkEmail = $conn->query("SELECT email FROM users WHERE email = '$email'");
    if ($checkEmail && $checkEmail->num_rows > 0) $errors[] = "Email already exists!";
    
    // Check if student ID already exists
    $checkStudent = $conn->query("SELECT student_id FROM student WHERE student_id = '$student_id'");
    if ($checkStudent && $checkStudent->num_rows > 0) $errors[] = "Student ID already exists!";
    
    if (empty($errors)) {
        // Insert into users table
        $sql1 = "INSERT INTO users (username, password, email, full_name, phone, address, role, is_active) 
                 VALUES ('$username', '$password', '$email', '$full_name', " . ($phone ? "'$phone'" : "NULL") . ", " . ($address ? "'$address'" : "NULL") . ", 'student', 1)";
        
        if ($conn->query($sql1)) {
            // Insert into student table
            $sql2 = "INSERT INTO student (student_id, username, date_of_birth, sex, current_grade_level, 
                     current_section, field_of_study, enrollment_date, parent_name, parent_phone, parent_email, student_status) 
                     VALUES ('$student_id', '$username', " . ($date_of_birth ? "'$date_of_birth'" : "NULL") . ", '$sex', 
                     '$current_grade_level', '$current_section', '$field_of_study', CURDATE(), " . ($parent_name ? "'$parent_name'" : "NULL") . ", 
                     " . ($parent_phone ? "'$parent_phone'" : "NULL") . ", " . ($parent_email ? "'$parent_email'" : "NULL") . ", 'active')";
            
            if ($conn->query($sql2)) {
                $message = "Student added successfully!<br>
                           <strong>Student ID:</strong> $student_id<br>
                           <strong>Username:</strong> $username<br>
                           <strong>Password:</strong> " . htmlspecialchars($_POST['password']);
            } else {
                $error = "Error adding student: " . $conn->error;
                $conn->query("DELETE FROM users WHERE username = '$username'");
            }
        } else {
            $error = "Error adding user: " . $conn->error;
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Get students by grade level
$students_grade9 = $conn->query("
    SELECT s.*, u.full_name, u.email, u.phone, u.address 
    FROM student s 
    JOIN users u ON s.username = u.username 
    WHERE s.student_status = 'active' AND s.current_grade_level = '9'
    ORDER BY s.student_id
");

$students_grade10 = $conn->query("
    SELECT s.*, u.full_name, u.email, u.phone, u.address 
    FROM student s 
    JOIN users u ON s.username = u.username 
    WHERE s.student_status = 'active' AND s.current_grade_level = '10'
    ORDER BY s.student_id
");

$students_grade11 = $conn->query("
    SELECT s.*, u.full_name, u.email, u.phone, u.address 
    FROM student s 
    JOIN users u ON s.username = u.username 
    WHERE s.student_status = 'active' AND s.current_grade_level = '11'
    ORDER BY s.student_id
");

$students_grade12 = $conn->query("
    SELECT s.*, u.full_name, u.email, u.phone, u.address 
    FROM student s 
    JOIN users u ON s.username = u.username 
    WHERE s.student_status = 'active' AND s.current_grade_level = '12'
    ORDER BY s.student_id
");

// Get counts for each grade
$count9 = $students_grade9 ? $students_grade9->num_rows : 0;
$count10 = $students_grade10 ? $students_grade10->num_rows : 0;
$count11 = $students_grade11 ? $students_grade11->num_rows : 0;
$count12 = $students_grade12 ? $students_grade12->num_rows : 0;
?>

<style>
/* Modern Table Styles */
.modern-table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

.modern-table thead th {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 15px;
    border: none;
    position: sticky;
    top: 0;
    z-index: 10;
}

.modern-table thead th:first-child {
    border-top-left-radius: 10px;
}

.modern-table thead th:last-child {
    border-top-right-radius: 10px;
}

.modern-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #eef2f7;
}

.modern-table tbody tr:hover {
    background: linear-gradient(90deg, #f8f9fa, #fff);
    transform: scale(1.01);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.modern-table tbody td {
    padding: 12px 15px;
    vertical-align: middle;
    font-size: 0.9rem;
}

/* Grade Table Containers */
.grade-table-container {
    margin-bottom: 30px;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.grade-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.grade-title {
    font-size: 1.1rem;
    font-weight: 600;
}

.grade-badge-count {
    background: rgba(255,255,255,0.2);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
}

/* Scrollable table body */
.table-scroll {
    max-height: 400px;
    overflow-y: auto;
    overflow-x: auto;
}

.table-scroll::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.table-scroll::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.table-scroll::-webkit-scrollbar-thumb {
    background: #1e3c72;
    border-radius: 10px;
}

.table-scroll::-webkit-scrollbar-thumb:hover {
    background: #2a5298;
}

.student-id-badge {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    background: #e8f0fe;
    color: #1e3c72;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    display: inline-block;
}

.grade-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.75rem;
    display: inline-block;
}

.grade-9 { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
.grade-10 { background: linear-gradient(135deg, #f093fb, #f5576c); color: white; }
.grade-11 { background: linear-gradient(135deg, #4facfe, #00f2fe); color: white; }
.grade-12 { background: linear-gradient(135deg, #43e97b, #38f9d7); color: white; }

.section-badge {
    background: #f1f3f5;
    color: #495057;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.75rem;
}

.field-badge {
    background: #fff3e0;
    color: #e67e22;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
}

.action-btn {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
}

.btn-view { background: #17a2b8; color: white; border: none; }
.btn-view:hover { background: #138496; }

.btn-enroll { background: #28a745; color: white; border: none; }
.btn-enroll:hover { background: #218838; }

.btn-edit { background: #ffc107; color: #212529; border: none; }
.btn-edit:hover { background: #e0a800; }

.btn-delete { background: #dc3545; color: white; border: none; }
.btn-delete:hover { background: #c82333; }

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1rem;
    margin-right: 10px;
}

.student-info {
    display: flex;
    align-items: center;
}

.student-details {
    line-height: 1.3;
}

.student-name {
    font-weight: 600;
    color: #2c3e50;
}

.student-email {
    font-size: 0.7rem;
    color: #6c757d;
}

/* Modal styles */
.modern-modal .modal-content {
    border-radius: 15px;
    border: none;
    overflow: hidden;
}

.modern-modal .modal-header {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
    border: none;
}

/* Responsive */
@media (max-width: 768px) {
    .action-buttons {
        flex-direction: column;
        gap: 4px;
    }
    .action-btn {
        width: 28px;
        height: 28px;
    }
    .table-scroll {
        max-height: 300px;
    }
}

/* Grade header colors */
.grade-header-9 { background: linear-gradient(135deg, #667eea, #764ba2); }
.grade-header-10 { background: linear-gradient(135deg, #f093fb, #f5576c); }
.grade-header-11 { background: linear-gradient(135deg, #4facfe, #00f2fe); }
.grade-header-12 { background: linear-gradient(135deg, #43e97b, #38f9d7); }

/* Page Header Styles */
.page-header-wrapper {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    border-radius: 20px;
    padding: 25px 30px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.page-header-wrapper::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 8s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.page-header-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.page-title-section h1 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    color: white;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title-section h1 i {
    font-size: 2rem;
    background: rgba(255,255,255,0.2);
    padding: 12px;
    border-radius: 50%;
}

.page-title-section p {
    margin: 8px 0 0 0;
    color: rgba(255,255,255,0.9);
    font-size: 0.9rem;
}

.header-stats {
    display: flex;
    gap: 20px;
    background: rgba(255,255,255,0.15);
    padding: 12px 25px;
    border-radius: 50px;
    backdrop-filter: blur(10px);
}

.header-stat-item {
    text-align: center;
}

.header-stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    line-height: 1;
}

.header-stat-label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.8);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.btn-register {
    background: linear-gradient(135deg, #28a745, #20c997);
    border: none;
    padding: 12px 28px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.btn-register:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.btn-register i {
    margin-right: 8px;
}
</style>

<div class="container-fluid">
    <!-- Attractive Page Header -->
    <div class="page-header-wrapper">
        <div class="page-header-content">
            <div class="page-title-section">
                <h1>
                    <i class="fas fa-users-graduate"></i>
                    Manage Students
                </h1>
                <p><i class="fas fa-chalkboard-user me-1"></i> Manage and monitor all student records by grade level</p>
            </div>
            <div class="header-stats">
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $count9 + $count10 + $count11 + $count12; ?></div>
                    <div class="header-stat-label">Total Students</div>
                </div>
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $count9; ?></div>
                    <div class="header-stat-label">Grade 9</div>
                </div>
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $count10; ?></div>
                    <div class="header-stat-label">Grade 10</div>
                </div>
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $count11; ?></div>
                    <div class="header-stat-label">Grade 11</div>
                </div>
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $count12; ?></div>
                    <div class="header-stat-label">Grade 12</div>
                </div>
            </div>
            <div>
                <button class="btn btn-register" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="fas fa-plus-circle"></i> Register New Student
                </button>
            </div>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
            <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Grade 9 Table -->
    <div class="grade-table-container">
        <div class="grade-header grade-header-9">
            <div class="grade-title">
                <i class="fas fa-graduation-cap me-2"></i>Grade 9 Students
            </div>
            <div class="grade-badge-count">
                <i class="fas fa-users me-1"></i><?php echo $count9; ?> Students
            </div>
        </div>
        <div class="table-scroll">
            <table class="table modern-table mb-0" id="studentsTable9">
                <thead>
                    <tr>
                        <th><i class="fas fa-id-card table-header-icon"></i>Student ID</th>
                        <th><i class="fas fa-user table-header-icon"></i>Student Info</th>
                        <th><i class="fas fa-book table-header-icon"></i>Section</th>
                        <th><i class="fas fa-flask table-header-icon"></i>Field</th>
                        <th><i class="fas fa-users table-header-icon"></i>Parent</th>
                        <th><i class="fas fa-cog table-header-icon"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students_grade9 && $students_grade9->num_rows > 0): ?>
                        <?php while ($student = $students_grade9->fetch_assoc()): 
                            $initials = strtoupper(substr($student['full_name'], 0, 2));
                        ?>
                            <tr>
                                <td>
                                    <span class="student-id-badge">
                                        <i class="fas fa-id-card me-1"></i>
                                        <?php echo htmlspecialchars($student['student_id']); ?>
                                    </span>
                                 </small>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div class="student-details">
                                            <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                            <div class="student-email">
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($student['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                 </small>
                                <td><span class="section-badge">Sec <?php echo htmlspecialchars($student['current_section']); ?></span></small>
                                <td>
                                    <?php if ($student['field_of_study'] && $student['field_of_study'] != 'General'): ?>
                                        <span class="field-badge"><?php echo htmlspecialchars($student['field_of_study']); ?></span>
                                    <?php else: ?>
                                        <span class="field-badge">General</span>
                                    <?php endif; ?>
                                 </small>
                                <td>
                                    <?php if ($student['parent_name']): ?>
                                        <div class="small">
                                            <div><i class="fas fa-user-friends text-muted me-1"></i><?php echo htmlspecialchars($student['parent_name']); ?></div>
                                            <?php if ($student['parent_phone']): ?>
                                                <div><i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($student['parent_phone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                 </small>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_student.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn action-btn btn-view" title="View Student">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="enroll_student.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn action-btn btn-enroll" title="Enroll in Subjects">
                                            <i class="fas fa-book-open"></i>
                                        </a>
                                        <a href="edit_student.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn action-btn btn-edit" title="Edit Student">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn action-btn btn-delete" title="Delete Student"
                                                onclick="confirmDelete('<?php echo htmlspecialchars($student['student_id']); ?>', '<?php echo htmlspecialchars($student['full_name']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                 </small>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-user-graduate fa-2x mb-2 d-block"></i>
                                No students in Grade 9
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Grade 10 Table -->
    <div class="grade-table-container">
        <div class="grade-header grade-header-10">
            <div class="grade-title">
                <i class="fas fa-graduation-cap me-2"></i>Grade 10 Students
            </div>
            <div class="grade-badge-count">
                <i class="fas fa-users me-1"></i><?php echo $count10; ?> Students
            </div>
        </div>
        <div class="table-scroll">
            <table class="table modern-table mb-0" id="studentsTable10">
                <thead>
                    <tr>
                        <th><i class="fas fa-id-card table-header-icon"></i>Student ID</th>
                        <th><i class="fas fa-user table-header-icon"></i>Student Info</th>
                        <th><i class="fas fa-book table-header-icon"></i>Section</th>
                        <th><i class="fas fa-flask table-header-icon"></i>Field</th>
                        <th><i class="fas fa-users table-header-icon"></i>Parent</th>
                        <th><i class="fas fa-cog table-header-icon"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students_grade10 && $students_grade10->num_rows > 0): ?>
                        <?php while ($student = $students_grade10->fetch_assoc()): 
                            $initials = strtoupper(substr($student['full_name'], 0, 2));
                        ?>
                            <tr>
                                <td>
                                    <span class="student-id-badge">
                                        <i class="fas fa-id-card me-1"></i>
                                        <?php echo htmlspecialchars($student['student_id']); ?>
                                    </span>
                                 </small>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div class="student-details">
                                            <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                            <div class="student-email">
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($student['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                 </small>
                                <td><span class="section-badge">Sec <?php echo htmlspecialchars($student['current_section']); ?></span></small>
                                <td>
                                    <?php if ($student['field_of_study'] && $student['field_of_study'] != 'General'): ?>
                                        <span class="field-badge"><?php echo htmlspecialchars($student['field_of_study']); ?></span>
                                    <?php else: ?>
                                        <span class="field-badge">General</span>
                                    <?php endif; ?>
                                 </small>
                                <td>
                                    <?php if ($student['parent_name']): ?>
                                        <div class="small">
                                            <div><i class="fas fa-user-friends text-muted me-1"></i><?php echo htmlspecialchars($student['parent_name']); ?></div>
                                            <?php if ($student['parent_phone']): ?>
                                                <div><i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($student['parent_phone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                 </small>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_student.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn action-btn btn-view" title="View Student">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="enroll_student.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn action-btn btn-enroll" title="Enroll in Subjects">
                                            <i class="fas fa-book-open"></i>
                                        </a>
                                        <a href="edit_student.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn action-btn btn-edit" title="Edit Student">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn action-btn btn-delete" title="Delete Student"
                                                onclick="confirmDelete('<?php echo htmlspecialchars($student['student_id']); ?>', '<?php echo htmlspecialchars($student['full_name']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                 </small>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-user-graduate fa-2x mb-2 d-block"></i>
                                No students in Grade 10
                            </td>
                        </table>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Grade 11 Table -->
    <div class="grade-table-container">
        <div class="grade-header grade-header-11">
            <div class="grade-title">
                <i class="fas fa-graduation-cap me-2"></i>Grade 11 Students
            </div>
            <div class="grade-badge-count">
                <i class="fas fa-users me-1"></i><?php echo $count11; ?> Students
            </div>
        </div>
        <div class="table-scroll">
            <table class="table modern-table mb-0" id="studentsTable11">
                <thead>
                    <tr>
                        <th><i class="fas fa-id-card table-header-icon"></i>Student ID</th>
                        <th><i class="fas fa-user table-header-icon"></i>Student Info</th>
                        <th><i class="fas fa-book table-header-icon"></i>Section</th>
                        <th><i class="fas fa-flask table-header-icon"></i>Field</th>
                        <th><i class="fas fa-users table-header-icon"></i>Parent</th>
                        <th><i class="fas fa-cog table-header-icon"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students_grade11 && $students_grade11->num_rows > 0): ?>
                        <?php while ($student = $students_grade11->fetch_assoc()): 
                            $initials = strtoupper(substr($student['full_name'], 0, 2));
                        ?>
                            <tr>
                                <td>
                                    <span class="student-id-badge">
                                        <i class="fas fa-id-card me-1"></i>
                                        <?php echo htmlspecialchars($student['student_id']); ?>
                                    </span>
                                 </small>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div class="student-details">
                                            <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                            <div class="student-email">
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($student['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                 </small>
                                <td><span class="section-badge">Sec <?php echo htmlspecialchars($student['current_section']); ?></span></small>
                                <td>
                                    <?php if ($student['field_of_study'] && $student['field_of_study'] != 'General'): ?>
                                        <span class="field-badge"><?php echo htmlspecialchars($student['field_of_study']); ?></span>
                                    <?php else: ?>
                                        <span class="field-badge">General</span>
                                    <?php endif; ?>
                                 </small>
                                <td>
                                    <?php if ($student['parent_name']): ?>
                                        <div class="small">
                                            <div><i class="fas fa-user-friends text-muted me-1"></i><?php echo htmlspecialchars($student['parent_name']); ?></div>
                                            <?php if ($student['parent_phone']): ?>
                                                <div><i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($student['parent_phone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                 </small>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_student.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn action-btn btn-view" title="View Student">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="enroll_student.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn action-btn btn-enroll" title="Enroll in Subjects">
                                            <i class="fas fa-book-open"></i>
                                        </a>
                                        <a href="edit_student.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn action-btn btn-edit" title="Edit Student">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn action-btn btn-delete" title="Delete Student"
                                                onclick="confirmDelete('<?php echo htmlspecialchars($student['student_id']); ?>', '<?php echo htmlspecialchars($student['full_name']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                 </small>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-user-graduate fa-2x mb-2 d-block"></i>
                                No students in Grade 11
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Grade 12 Table -->
    <div class="grade-table-container">
        <div class="grade-header grade-header-12">
            <div class="grade-title">
                <i class="fas fa-graduation-cap me-2"></i>Grade 12 Students
            </div>
            <div class="grade-badge-count">
                <i class="fas fa-users me-1"></i><?php echo $count12; ?> Students
            </div>
        </div>
        <div class="table-scroll">
            <table class="table modern-table mb-0" id="studentsTable12">
                <thead>
                    <tr>
                        <th><i class="fas fa-id-card table-header-icon"></i>Student ID</th>
                        <th><i class="fas fa-user table-header-icon"></i>Student Info</th>
                        <th><i class="fas fa-book table-header-icon"></i>Section</th>
                        <th><i class="fas fa-flask table-header-icon"></i>Field</th>
                        <th><i class="fas fa-users table-header-icon"></i>Parent</th>
                        <th><i class="fas fa-cog table-header-icon"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students_grade12 && $students_grade12->num_rows > 0): ?>
                        <?php while ($student = $students_grade12->fetch_assoc()): 
                            $initials = strtoupper(substr($student['full_name'], 0, 2));
                        ?>
                            <tr>
                                <td>
                                    <span class="student-id-badge">
                                        <i class="fas fa-id-card me-1"></i>
                                        <?php echo htmlspecialchars($student['student_id']); ?>
                                    </span>
                                 </small>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div class="student-details">
                                            <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                            <div class="student-email">
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($student['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                 </small>
                                <td><span class="section-badge">Sec <?php echo htmlspecialchars($student['current_section']); ?></span></small>
                                <td>
                                    <?php if ($student['field_of_study'] && $student['field_of_study'] != 'General'): ?>
                                        <span class="field-badge"><?php echo htmlspecialchars($student['field_of_study']); ?></span>
                                    <?php else: ?>
                                        <span class="field-badge">General</span>
                                    <?php endif; ?>
                                 </small>
                                <td>
                                    <?php if ($student['parent_name']): ?>
                                        <div class="small">
                                            <div><i class="fas fa-user-friends text-muted me-1"></i><?php echo htmlspecialchars($student['parent_name']); ?></div>
                                            <?php if ($student['parent_phone']): ?>
                                                <div><i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($student['parent_phone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                 </small>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_student.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn action-btn btn-view" title="View Student">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="enroll_student.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn action-btn btn-enroll" title="Enroll in Subjects">
                                            <i class="fas fa-book-open"></i>
                                        </a>
                                        <a href="edit_student.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn action-btn btn-edit" title="Edit Student">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn action-btn btn-delete" title="Delete Student"
                                                onclick="confirmDelete('<?php echo htmlspecialchars($student['student_id']); ?>', '<?php echo htmlspecialchars($student['full_name']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                 </small>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-user-graduate fa-2x mb-2 d-block"></i>
                                No students in Grade 12
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade modern-modal" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Register New Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="return validateStudentForm()">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-id-card me-1 text-primary"></i> Student ID *</label>
                            <input type="text" name="student_id" id="student_id" class="form-control text-uppercase" 
                                   placeholder="e.g., STU001" required pattern="[A-Z0-9]+">
                            <small class="text-muted">Unique identifier (letters and numbers only)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-user me-1 text-primary"></i> Username *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-user-circle me-1 text-primary"></i> Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-envelope me-1 text-primary"></i> Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-key me-1 text-primary"></i> Password *</label>
                            <input type="text" name="password" class="form-control" value="student123" required>
                            <small class="text-muted">Default: student123</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-phone me-1 text-primary"></i> Phone Number</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+251-912-345678">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label"><i class="fas fa-map-marker-alt me-1 text-primary"></i> Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Enter address"></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-calendar me-1 text-primary"></i> Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-venus-mars me-1 text-primary"></i> Sex *</label>
                            <select name="sex" class="form-select" required>
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-graduation-cap me-1 text-primary"></i> Grade Level *</label>
                            <select name="current_grade_level" class="form-select" required>
                                <option value="9">Grade 9</option>
                                <option value="10">Grade 10</option>
                                <option value="11">Grade 11</option>
                                <option value="12">Grade 12</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-users me-1 text-primary"></i> Section *</label>
                            <input type="text" name="current_section" class="form-control text-uppercase" 
                                   placeholder="A" required maxlength="2">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-flask me-1 text-primary"></i> Field of Study</label>
                            <select name="field_of_study" class="form-select">
                                <option value="General">General</option>
                                <option value="Science">Natural Science</option>
                                <option value="Social Science">Social Science</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-user-friends me-1 text-primary"></i> Parent/Guardian</label>
                            <input type="text" name="parent_name" class="form-control" placeholder="Full name">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-phone-alt me-1 text-primary"></i> Parent Phone</label>
                            <input type="tel" name="parent_phone" class="form-control" placeholder="Phone number">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-envelope me-1 text-primary"></i> Parent Email</label>
                            <input type="email" name="parent_email" class="form-control" placeholder="Email address">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Register Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-user-times fa-4x text-danger mb-3 d-block"></i>
                <p>Are you sure you want to delete this student?</p>
                <p><strong id="deleteStudentName"></strong></p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All student data including grades and enrollments will be permanently deleted.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Yes, Delete Student</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTables for each grade table
    $('#studentsTable9, #studentsTable10, #studentsTable11, #studentsTable12').DataTable({
        pageLength: 10,
        responsive: true,
        ordering: true,
        language: {
            search: '<i class="fas fa-search me-1"></i>',
            searchPlaceholder: "Search...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ students",
            infoEmpty: "No students",
            infoFiltered: "(filtered from _MAX_ total)",
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            }
        },
        columnDefs: [
            { orderable: false, targets: [5] }
        ]
    });
});

function validateStudentForm() {
    const studentId = document.getElementById('student_id');
    const pattern = /^[A-Z0-9]+$/;
    if (!pattern.test(studentId.value)) {
        alert('Student ID must contain only uppercase letters and numbers!');
        studentId.focus();
        return false;
    }
    return true;
}

function confirmDelete(studentId, studentName) {
    $('#deleteStudentName').html('<strong>' + studentId + ' - ' + studentName + '</strong>');
    $('#confirmDeleteBtn').attr('href', '?delete=' + encodeURIComponent(studentId));
    $('#deleteModal').modal('show');
}

// Auto uppercase Student ID and Section
const studentIdInput = document.getElementById('student_id');
if (studentIdInput) {
    studentIdInput.addEventListener('input', function(e) {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });
}

const sectionInput = document.querySelector('input[name="current_section"]');
if (sectionInput) {
    sectionInput.addEventListener('input', function(e) {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });
}
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<?php require_once '../includes/footer.php'; ?>