<?php
// admin/edit_teacher.php - Professional edit teacher page
$pageTitle = 'Edit Teacher';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$message = '';
$error = '';

// Get teacher ID from URL
$teacher_id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : '';

if (empty($teacher_id)) {
    header('Location: manage_teachers.php');
    exit();
}

// Get teacher information
$teacherQuery = $conn->query("
    SELECT t.*, u.username, u.email, u.full_name, u.phone, u.address 
    FROM teacher t 
    JOIN users u ON t.username = u.username 
    WHERE t.teacher_id = '$teacher_id'
");

if (!$teacherQuery || $teacherQuery->num_rows == 0) {
    $error = "Teacher not found!";
} else {
    $teacher = $teacherQuery->fetch_assoc();
}

// Get current academic year
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
$currentYear = ($yearResult && $yearResult->num_rows > 0) ? $yearResult->fetch_assoc() : ['year_id' => 2, 'year_name' => '2025/26'];
$yearId = $currentYear['year_id'];

// Get assigned subjects for current academic year
$assignedSubjects = $conn->query("
    SELECT s.subject_id, s.subject_name 
    FROM teacher_subject ts
    JOIN subject s ON ts.subject_id = s.subject_id
    WHERE ts.teacher_id = '$teacher_id' AND ts.academic_year_id = $yearId AND ts.is_primary = 1
");

$assignedSubjectIds = [];
if ($assignedSubjects && $assignedSubjects->num_rows > 0) {
    while ($subj = $assignedSubjects->fetch_assoc()) {
        $assignedSubjectIds[] = $subj['subject_id'];
    }
    $assignedSubjects->data_seek(0);
}

// Get all subjects for assignment
$allSubjects = $conn->query("
    SELECT subject_id, subject_name 
    FROM subject 
    WHERE is_active = 1 
    ORDER BY subject_id
");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_teacher'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $qualification = trim($_POST['qualification']);
    $specialization = trim($_POST['specialization']);
    $department = trim($_POST['department']);
    $experience_years = (int)$_POST['experience_years'];
    $join_date = $_POST['join_date'] ?: null;
    $status = $_POST['status'];
    
    $errors = [];
    
    // Validate required fields
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    
    // Check if email already exists (excluding current teacher)
    $checkEmail = $conn->query("SELECT username FROM users WHERE email = '$email' AND username != '{$teacher['username']}'");
    if ($checkEmail && $checkEmail->num_rows > 0) $errors[] = "Email already exists for another user.";
    
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Update users table
            $sql1 = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE username = ?";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bind_param("sssss", $full_name, $email, $phone, $address, $teacher['username']);
            $stmt1->execute();
            
            // Update teacher table
            $sql2 = "UPDATE teacher SET qualification = ?, specialization = ?, 
                     department = ?, experience_years = ?, join_date = ?, status = ?
                     WHERE teacher_id = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("sssisss", $qualification, $specialization, 
                              $department, $experience_years, $join_date, $status, $teacher_id);
            $stmt2->execute();
            
            $conn->commit();
            $message = "Teacher updated successfully!";
            
            // Refresh data
            $teacherQuery = $conn->query("
                SELECT t.*, u.username, u.email, u.full_name, u.phone, u.address 
                FROM teacher t 
                JOIN users u ON t.username = u.username 
                WHERE t.teacher_id = '$teacher_id'
            ");
            $teacher = $teacherQuery->fetch_assoc();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error updating teacher: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle subject assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_subjects'])) {
    $selected_subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    
    $conn->begin_transaction();
    
    try {
        // Remove existing assignments for current academic year
        $conn->query("DELETE FROM teacher_subject WHERE teacher_id = '$teacher_id' AND academic_year_id = $yearId");
        
        // Add new assignments
        if (!empty($selected_subjects)) {
            foreach ($selected_subjects as $subject_id) {
                $subject_id = $conn->real_escape_string($subject_id);
                $sql = "INSERT INTO teacher_subject (teacher_id, subject_id, academic_year_id, is_primary, assigned_date) 
                        VALUES ('$teacher_id', '$subject_id', $yearId, 1, CURDATE())";
                $conn->query($sql);
            }
        }
        
        $conn->commit();
        $message = "Subjects assigned successfully!";
        
        // Refresh assigned subjects
        $assignedSubjects = $conn->query("
            SELECT s.subject_id, s.subject_name 
            FROM teacher_subject ts
            JOIN subject s ON ts.subject_id = s.subject_id
            WHERE ts.teacher_id = '$teacher_id' AND ts.academic_year_id = $yearId AND ts.is_primary = 1
        ");
        $assignedSubjectIds = [];
        if ($assignedSubjects && $assignedSubjects->num_rows > 0) {
            while ($subj = $assignedSubjects->fetch_assoc()) {
                $assignedSubjectIds[] = $subj['subject_id'];
            }
            $assignedSubjects->data_seek(0);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error assigning subjects: " . $e->getMessage();
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'] ?? 'teacher123';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql = "UPDATE users SET password = ? WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hashed_password, $teacher['username']);
    
    if ($stmt->execute()) {
        $message = "Password reset successfully! New password: " . htmlspecialchars($new_password);
    } else {
        $error = "Error resetting password: " . $stmt->error;
    }
    $stmt->close();
}
?>

<style>
:root {
    --primary: #1e3c72;
    --primary-light: #2a5298;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
    --info: #17a2b8;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
}

.form-section {
    background: white;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 24px;
}

.form-section-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}

.form-section-body {
    padding: 20px;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    border: 4px solid rgba(255,255,255,0.2);
}

.subject-badge {
    background: #e8f0fe;
    color: var(--primary);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.select2-container .select2-selection--multiple {
    border-radius: 10px;
    border: 1px solid var(--gray-200);
    min-height: 120px;
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-edit me-2" style="color: var(--primary);"></i> Edit Teacher
            </h1>
            <p class="text-muted mt-1 mb-0">Update teacher information and subject assignments</p>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_teachers.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
            <a href="view_teacher.php?id=<?php echo urlencode($teacher_id); ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-2"></i> View Teacher
            </a>
        </div>
    </div>
    
    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($teacher)): ?>
        <form method="POST">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <i class="fas fa-user me-2"></i> Personal Information
                        </div>
                        <div class="form-section-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="full_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control bg-light" 
                                           value="<?php echo htmlspecialchars($teacher['username']); ?>" disabled>
                                    <small class="text-muted">Username cannot be changed</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Teacher ID</label>
                                    <input type="text" class="form-control bg-light" 
                                           value="<?php echo htmlspecialchars($teacher['teacher_id']); ?>" disabled>
                                    <small class="text-muted">Teacher ID cannot be changed</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>"
                                           placeholder="+251-912-345678">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="2" 
                                              placeholder="Enter address"><?php echo htmlspecialchars($teacher['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Professional Information -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <i class="fas fa-chalkboard-user me-2"></i> Professional Information
                        </div>
                        <div class="form-section-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Qualification</label>
                                    <input type="text" name="qualification" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher['qualification'] ?? ''); ?>"
                                           placeholder="e.g., MSc, PhD, BEd">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Specialization</label>
                                    <input type="text" name="specialization" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher['specialization'] ?? ''); ?>"
                                           placeholder="e.g., Mathematics, English">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Department</label>
                                    <select name="department" class="form-select">
                                        <option value="">Select Department</option>
                                        <option value="Science" <?php echo ($teacher['department'] ?? '') == 'Science' ? 'selected' : ''; ?>>Science</option>
                                        <option value="Mathematics" <?php echo ($teacher['department'] ?? '') == 'Mathematics' ? 'selected' : ''; ?>>Mathematics</option>
                                        <option value="Language" <?php echo ($teacher['department'] ?? '') == 'Language' ? 'selected' : ''; ?>>Language</option>
                                        <option value="Social Science" <?php echo ($teacher['department'] ?? '') == 'Social Science' ? 'selected' : ''; ?>>Social Science</option>
                                        <option value="ICT" <?php echo ($teacher['department'] ?? '') == 'ICT' ? 'selected' : ''; ?>>ICT</option>
                                        <option value="General" <?php echo ($teacher['department'] ?? '') == 'General' ? 'selected' : ''; ?>>General</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Experience Years</label>
                                    <input type="number" name="experience_years" class="form-control" 
                                           value="<?php echo $teacher['experience_years'] ?? 0; ?>" min="0" max="50">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Join Date</label>
                                    <input type="date" name="join_date" class="form-control" 
                                           value="<?php echo $teacher['join_date']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="active" <?php echo ($teacher['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($teacher['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="on_leave" <?php echo ($teacher['status'] ?? '') == 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Profile Card -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <i class="fas fa-user-circle me-2"></i> Profile
                        </div>
                        <div class="form-section-body text-center">
                            <div class="profile-avatar">
                                <i class="fas fa-chalkboard-user fa-5x text-white"></i>
                            </div>
                            <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($teacher['full_name']); ?></h5>
                            <p class="text-muted small"><?php echo htmlspecialchars($teacher['teacher_id']); ?></p>
                            <hr>
                            <div class="text-start">
                                <div class="info-row d-flex justify-content-between py-2">
                                    <span class="text-muted">Status</span>
                                    <span class="badge bg-<?php echo ($teacher['status'] ?? 'active') == 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($teacher['status'] ?? 'Active'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions Card -->
                    <div class="form-section">
                        <div class="form-section-header bg-warning">
                            <i class="fas fa-tools me-2"></i> Actions
                        </div>
                        <div class="form-section-body">
                            <button type="submit" name="update_teacher" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                <i class="fas fa-key me-2"></i> Reset Password
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Subject Assignment Section -->
        <div class="form-section">
            <div class="form-section-header bg-success">
                <i class="fas fa-book me-2"></i> Subject Assignment (<?php echo htmlspecialchars($currentYear['year_name']); ?>)
            </div>
            <div class="form-section-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Assign Subjects to <?php echo htmlspecialchars($teacher['full_name']); ?></label>
                            <select name="subjects[]" class="form-select select2-multiple" multiple>
                                <?php if ($allSubjects && $allSubjects->num_rows > 0): ?>
                                    <?php while ($subject = $allSubjects->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($subject['subject_id']); ?>" 
                                            <?php echo in_array($subject['subject_id'], $assignedSubjectIds) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject_id'] . ' - ' . $subject['subject_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple subjects</small>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="assign_subjects" class="btn btn-success w-100">
                                <i class="fas fa-save me-2"></i> Update Assignments
                            </button>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($assignedSubjectIds)): ?>
                    <hr>
                    <label class="form-label fw-semibold">Currently Assigned Subjects:</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($assignedSubjectIds as $subjectId): 
                            $subjectName = $conn->query("SELECT subject_name FROM subject WHERE subject_id = '$subjectId'")->fetch_assoc();
                        ?>
                            <span class="subject-badge">
                                <i class="fas fa-book"></i> <?php echo htmlspecialchars($subjectId); ?> - <?php echo htmlspecialchars($subjectName['subject_name'] ?? ''); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Reset Password Modal -->
        <div class="modal fade" id="resetPasswordModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-key me-2"></i> Reset Teacher Password</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <p>Reset password for: <strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong></p>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="text" name="new_password" class="form-control" value="teacher123">
                                <small class="text-muted">Default: teacher123</small>
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
            <i class="fas fa-exclamation-circle me-2"></i> Teacher not found!
        </div>
        <a href="manage_teachers.php" class="btn btn-primary">Back to Teachers</a>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-multiple').select2({
        placeholder: "Select subjects to assign",
        allowClear: true,
        theme: 'bootstrap-5',
        width: '100%'
    });
});
</script>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<?php require_once '../includes/footer.php'; ?>