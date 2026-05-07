<?php
// admin/edit_student.php - Updated for database schema
$pageTitle = 'Edit Student';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$message = '';
$error = '';

// Get student ID from URL
$student_id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : '';

if (empty($student_id)) {
    header('Location: manage_students.php');
    exit();
}

// Get student information
$studentQuery = $conn->query("
    SELECT s.*, u.username, u.email, u.full_name, u.phone, u.address 
    FROM student s 
    JOIN users u ON s.username = u.username 
    WHERE s.student_id = '$student_id'
");

if (!$studentQuery || $studentQuery->num_rows == 0) {
    $error = "Student not found!";
} else {
    $student = $studentQuery->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $sex = $_POST['sex'];
    $current_grade_level = $_POST['current_grade_level'];
    $current_section = strtoupper(trim($_POST['current_section']));
    $field_of_study = trim($_POST['field_of_study']);
    $parent_name = trim($_POST['parent_name']);
    $parent_phone = trim($_POST['parent_phone']);
    $parent_email = trim($_POST['parent_email']);
    $student_status = $_POST['student_status'];
    
    $conn->begin_transaction();
    
    try {
        // Update users table
        $sql1 = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE username = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("sssss", $full_name, $email, $phone, $address, $student['username']);
        $stmt1->execute();
        
        // Update student table (address is not in student table, it's in users table)
        $sql2 = "UPDATE student SET date_of_birth = ?, sex = ?,
                 current_grade_level = ?, current_section = ?, field_of_study = ?, parent_name = ?,
                 parent_phone = ?, parent_email = ?, student_status = ?
                 WHERE student_id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("ssssssssss", $date_of_birth, $sex,
                          $current_grade_level, $current_section, $field_of_study, $parent_name,
                          $parent_phone, $parent_email, $student_status, $student_id);
        $stmt2->execute();
        
        $conn->commit();
        $message = "Student updated successfully!";
        
        // Refresh data
        $studentQuery = $conn->query("
            SELECT s.*, u.username, u.email, u.full_name, u.phone, u.address 
            FROM student s 
            JOIN users u ON s.username = u.username 
            WHERE s.student_id = '$student_id'
        ");
        $student = $studentQuery->fetch_assoc();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error updating student: " . $e->getMessage();
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'] ?? 'student123';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql = "UPDATE users SET password = ? WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hashed_password, $student['username']);
    
    if ($stmt->execute()) {
        $message = "Password reset successfully! New password: " . htmlspecialchars($new_password);
    } else {
        $error = "Error resetting password: " . $stmt->error;
    }
    $stmt->close();
}
?>

<style>
.student-avatar {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
}
.form-section-header {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
}
.info-badge {
    background: #f8f9fa;
    padding: 8px;
    border-radius: 8px;
    text-align: center;
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="fas fa-edit me-2"></i> Edit Student
        </h1>
        <div>
            <a href="manage_students.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Students
            </a>
            <a href="view_student.php?id=<?php echo urlencode($student_id); ?>" class="btn btn-info">
                <i class="fas fa-eye me-2"></i> View Student
            </a>
            <a href="enroll_student.php?id=<?php echo urlencode($student_id); ?>" class="btn btn-success">
                <i class="fas fa-book-open me-2"></i> Enroll Subjects
            </a>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($student)): ?>
        <form method="POST">
            <div class="row">
                <div class="col-md-8">
                    <!-- Student Information Card -->
                    <div class="card shadow">
                        <div class="card-header form-section-header">
                            <i class="fas fa-user-graduate me-2"></i> Student Information
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="full_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($student['email']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control bg-light" 
                                           value="<?php echo htmlspecialchars($student['username']); ?>" disabled>
                                    <small class="text-muted">Username cannot be changed</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Student ID</label>
                                    <input type="text" class="form-control bg-light" 
                                           value="<?php echo htmlspecialchars($student['student_id']); ?>" disabled>
                                    <small class="text-muted">Student ID cannot be changed</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>"
                                           placeholder="+251-912-345678">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control" 
                                           value="<?php echo $student['date_of_birth']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sex *</label>
                                    <select name="sex" class="form-select" required>
                                        <option value="M" <?php echo $student['sex'] == 'M' ? 'selected' : ''; ?>>Male</option>
                                        <option value="F" <?php echo $student['sex'] == 'F' ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="2" 
                                              placeholder="Enter address"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Academic Information Card -->
                    <div class="card shadow mt-4">
                        <div class="card-header form-section-header">
                            <i class="fas fa-graduation-cap me-2"></i> Academic Information
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Grade Level *</label>
                                    <select name="current_grade_level" class="form-select" required>
                                        <option value="9" <?php echo ($student['current_grade_level'] ?? '') == '9' ? 'selected' : ''; ?>>Grade 9</option>
                                        <option value="10" <?php echo ($student['current_grade_level'] ?? '') == '10' ? 'selected' : ''; ?>>Grade 10</option>
                                        <option value="11" <?php echo ($student['current_grade_level'] ?? '') == '11' ? 'selected' : ''; ?>>Grade 11</option>
                                        <option value="12" <?php echo ($student['current_grade_level'] ?? '') == '12' ? 'selected' : ''; ?>>Grade 12</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Section *</label>
                                    <input type="text" name="current_section" class="form-control text-uppercase" 
                                           value="<?php echo htmlspecialchars($student['current_section']); ?>" 
                                           required maxlength="2" placeholder="A">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Field of Study</label>
                                    <select name="field_of_study" class="form-select">
                                        <option value="General" <?php echo ($student['field_of_study'] ?? 'General') == 'General' ? 'selected' : ''; ?>>General</option>
                                        <option value="Science" <?php echo ($student['field_of_study'] ?? '') == 'Science' ? 'selected' : ''; ?>>Natural Science</option>
                                        <option value="Social Science" <?php echo ($student['field_of_study'] ?? '') == 'Social Science' ? 'selected' : ''; ?>>Social Science</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Student Status</label>
                                    <select name="student_status" class="form-select">
                                        <option value="active" <?php echo ($student['student_status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="graduated" <?php echo ($student['student_status'] ?? '') == 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                                        <option value="transferred" <?php echo ($student['student_status'] ?? '') == 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                                        <option value="suspended" <?php echo ($student['student_status'] ?? '') == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Enrollment Date</label>
                                    <input type="text" class="form-control bg-light" 
                                           value="<?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?>" disabled>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Parent/Guardian Information Card -->
                    <div class="card shadow mt-4">
                        <div class="card-header form-section-header">
                            <i class="fas fa-users me-2"></i> Parent/Guardian Information
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Parent/Guardian Name</label>
                                    <input type="text" name="parent_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($student['parent_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Parent/Guardian Phone</label>
                                    <input type="tel" name="parent_phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Parent/Guardian Email</label>
                                    <input type="email" name="parent_email" class="form-control" 
                                           value="<?php echo htmlspecialchars($student['parent_email'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Profile Card -->
                    <div class="card shadow">
                        <div class="card-header form-section-header">
                            <i class="fas fa-user-circle me-2"></i> Profile
                        </div>
                        <div class="card-body text-center">
                            <div class="rounded-circle student-avatar d-inline-flex p-4 mb-3">
                                <i class="fas fa-user-graduate fa-4x text-white"></i>
                            </div>
                            <h5><?php echo htmlspecialchars($student['full_name']); ?></h5>
                            <p class="text-muted">
                                <i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($student['student_id']); ?>
                            </p>
                            <hr>
                            <div class="row">
                                <div class="col-6">
                                    <div class="info-badge">
                                        <small class="text-muted">Username</small>
                                        <div><strong><?php echo htmlspecialchars($student['username']); ?></strong></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="info-badge">
                                        <small class="text-muted">Enrolled Since</small>
                                        <div><strong><?php echo date('M Y', strtotime($student['enrollment_date'])); ?></strong></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions Card -->
                    <div class="card shadow mt-4">
                        <div class="card-header bg-warning text-dark">
                            <i class="fas fa-tools me-2"></i> Actions
                        </div>
                        <div class="card-body">
                            <button type="submit" name="update_student" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                <i class="fas fa-key me-2"></i> Reset Password
                            </button>
                        </div>
                    </div>
                    
                    <!-- Account Info Card -->
                    <div class="card shadow mt-4">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-clock me-2"></i> Account Info
                        </div>
                        <div class="card-body">
                            <p><strong><i class="fas fa-calendar-alt me-2"></i> Enrollment Date:</strong><br>
                            <?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></p>
                            <p><strong><i class="fas fa-calendar-plus me-2"></i> Account Created:</strong><br>
                            <?php echo date('M d, Y', strtotime($student['created_at'] ?? 'now')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Reset Password Modal -->
        <div class="modal fade" id="resetPasswordModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-key me-2"></i> Reset Student Password</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <p>Reset password for: <strong><?php echo htmlspecialchars($student['full_name']); ?></strong></p>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="text" name="new_password" class="form-control" value="student123">
                                <small class="text-muted">Default: student123</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="reset_password" class="btn btn-danger">Reset Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i> Student not found!
        </div>
        <a href="manage_students.php" class="btn btn-primary">Back to Students</a>
    <?php endif; ?>
</div>

<script>
// Auto uppercase section input
const sectionInput = document.querySelector('input[name="current_section"]');
if (sectionInput) {
    sectionInput.addEventListener('input', function(e) {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>