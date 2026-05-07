<?php
// admin/edit_subject.php - Complete edit subject page (Short name removed)
$pageTitle = 'Edit Subject';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$message = '';
$error = '';

// Get subject ID from URL
$subject_id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : '';

if (empty($subject_id)) {
    header('Location: manage_subjects.php');
    exit();
}

// Get subject information
$subjectQuery = $conn->query("SELECT * FROM subject WHERE subject_id = '$subject_id'");

if (!$subjectQuery || $subjectQuery->num_rows == 0) {
    $error = "Subject not found!";
} else {
    $subject = $subjectQuery->fetch_assoc();
}

// Get current academic year
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
if ($yearResult && $yearResult->num_rows > 0) {
    $currentYear = $yearResult->fetch_assoc();
} else {
    $currentYear = ['year_id' => 2, 'year_name' => '2025/26'];
}
$currentYearId = $currentYear['year_id'];

// Get assigned teacher for current academic year
$assignedTeacher = $conn->query("
    SELECT t.teacher_id, u.full_name 
    FROM teacher_subject ts
    JOIN teacher t ON ts.teacher_id = t.teacher_id
    JOIN users u ON t.username = u.username
    WHERE ts.subject_id = '$subject_id' 
    AND ts.academic_year_id = $currentYearId
    AND ts.is_primary = 1
    LIMIT 1
");
$currentTeacher = ($assignedTeacher && $assignedTeacher->num_rows > 0) ? $assignedTeacher->fetch_assoc() : null;

// Get all teachers for assignment
$allTeachers = $conn->query("
    SELECT t.teacher_id, u.full_name 
    FROM teacher t 
    JOIN users u ON t.username = u.username 
    WHERE t.status = 'active'
    ORDER BY u.full_name
");

// Handle form submission - Update Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subject'])) {
    $subject_name = trim($_POST['subject_name']);
    $credits = (int)$_POST['credits'];
    $grade_level = trim($_POST['grade_level']);
    $subject_type = $_POST['subject_type'];
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate required fields
    if (empty($subject_name)) {
        $error = "Subject name is required!";
    } else {
        // Check if subject name already exists (excluding current subject)
        $check = $conn->query("SELECT subject_id FROM subject WHERE subject_name = '$subject_name' AND subject_id != '$subject_id'");
        if ($check && $check->num_rows > 0) {
            $error = "Subject name already exists!";
        } else {
            // Build update query
            $updateFields = [];
            $updateFields[] = "subject_name = '$subject_name'";
            $updateFields[] = "credits = $credits";
            $updateFields[] = "grade_level = '$grade_level'";
            $updateFields[] = "subject_type = '$subject_type'";
            $updateFields[] = "description = " . ($description ? "'$description'" : "NULL");
            $updateFields[] = "is_active = $is_active";
            
            $sql = "UPDATE subject SET " . implode(", ", $updateFields) . " WHERE subject_id = '$subject_id'";
            
            if ($conn->query($sql)) {
                $message = "Subject updated successfully!";
                // Refresh subject data
                $subjectQuery = $conn->query("SELECT * FROM subject WHERE subject_id = '$subject_id'");
                if ($subjectQuery && $subjectQuery->num_rows > 0) {
                    $subject = $subjectQuery->fetch_assoc();
                }
            } else {
                $error = "Error updating subject: " . $conn->error;
            }
        }
    }
}

// Handle teacher assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_teacher'])) {
    $teacher_id = isset($_POST['teacher_id']) ? $conn->real_escape_string($_POST['teacher_id']) : '';
    
    // Get current semester
    $semesterResult = $conn->query("SELECT semester_id FROM semester WHERE year_id = $currentYearId AND is_current = 1 LIMIT 1");
    $semesterId = ($semesterResult && $semesterResult->num_rows > 0) ? $semesterResult->fetch_assoc()['semester_id'] : null;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Remove existing primary assignment for this subject and year
        $conn->query("DELETE FROM teacher_subject 
                      WHERE subject_id = '$subject_id' 
                      AND academic_year_id = $currentYearId
                      AND is_primary = 1");
        
        // Add new assignment if teacher selected
        if (!empty($teacher_id)) {
            $semesterValue = $semesterId ? $semesterId : 'NULL';
            $sql = "INSERT INTO teacher_subject (teacher_id, subject_id, academic_year_id, semester_id, is_primary, assigned_date) 
                    VALUES ('$teacher_id', '$subject_id', $currentYearId, $semesterValue, 1, CURDATE())";
            
            if (!$conn->query($sql)) {
                throw new Exception($conn->error);
            }
        }
        
        $conn->commit();
        $message = "Teacher assigned successfully!";
        
        // Refresh assigned teacher
        $assignedTeacher = $conn->query("
            SELECT t.teacher_id, u.full_name 
            FROM teacher_subject ts
            JOIN teacher t ON ts.teacher_id = t.teacher_id
            JOIN users u ON t.username = u.username
            WHERE ts.subject_id = '$subject_id' 
            AND ts.academic_year_id = $currentYearId
            AND ts.is_primary = 1
            LIMIT 1
        ");
        $currentTeacher = ($assignedTeacher && $assignedTeacher->num_rows > 0) ? $assignedTeacher->fetch_assoc() : null;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error assigning teacher: " . $e->getMessage();
    }
}

// Get statistics for this subject
$studentCountResult = $conn->query("SELECT COUNT(DISTINCT student_id) as total 
                                    FROM student_subject 
                                    WHERE subject_id = '$subject_id' AND is_active = 1");
$studentCount = ($studentCountResult && $studentCountResult->num_rows > 0) ? $studentCountResult->fetch_assoc()['total'] : 0;

$gradeCountResult = $conn->query("SELECT COUNT(*) as total FROM mark WHERE subject_id = '$subject_id'");
$gradeCount = ($gradeCountResult && $gradeCountResult->num_rows > 0) ? $gradeCountResult->fetch_assoc()['total'] : 0;

$avgScoreResult = $conn->query("SELECT AVG(score) as avg FROM mark WHERE subject_id = '$subject_id'");
$avgScore = ($avgScoreResult && $avgScoreResult->num_rows > 0) ? round($avgScoreResult->fetch_assoc()['avg'] ?? 0, 1) : 0;
?>

<style>
.stat-card {
    text-align: center;
    padding: 15px;
    border-radius: 8px;
    background: #f8f9fa;
    margin-bottom: 10px;
}
.stat-number {
    font-size: 28px;
    font-weight: bold;
    margin: 0;
}
.stat-label {
    color: #6c757d;
    font-size: 12px;
    text-transform: uppercase;
    margin: 0;
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="fas fa-edit me-2"></i> Edit Subject
        </h1>
        <div>
            <a href="manage_subjects.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Subjects
            </a>
            <a href="view_subject.php?id=<?php echo urlencode($subject_id); ?>" class="btn btn-info">
                <i class="fas fa-eye me-2"></i> View Subject
            </a>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($subject)): ?>
        <div class="row">
            <div class="col-md-8">
                <!-- Subject Information Card -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-book me-2"></i> Subject Information
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Subject ID *</label>
                                    <input type="text" class="form-control bg-light" 
                                           value="<?php echo htmlspecialchars($subject['subject_id']); ?>" 
                                           readonly disabled>
                                    <small class="text-muted">Subject ID cannot be changed</small>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Subject Name *</label>
                                    <input type="text" name="subject_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($subject['subject_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Credits</label>
                                    <input type="number" name="credits" class="form-control" 
                                           value="<?php echo $subject['credits']; ?>" min="1" max="10" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Grade Level</label>
                                    <select name="grade_level" class="form-select">
                                        <option value="9-12" <?php echo ($subject['grade_level'] ?? '9-12') == '9-12' ? 'selected' : ''; ?>>All Grades (9-12)</option>
                                        <option value="9-10" <?php echo ($subject['grade_level'] ?? '') == '9-10' ? 'selected' : ''; ?>>Grades 9-10</option>
                                        <option value="11-12" <?php echo ($subject['grade_level'] ?? '') == '11-12' ? 'selected' : ''; ?>>Grades 11-12</option>
                                        <option value="9" <?php echo ($subject['grade_level'] ?? '') == '9' ? 'selected' : ''; ?>>Grade 9 Only</option>
                                        <option value="10" <?php echo ($subject['grade_level'] ?? '') == '10' ? 'selected' : ''; ?>>Grade 10 Only</option>
                                        <option value="11" <?php echo ($subject['grade_level'] ?? '') == '11' ? 'selected' : ''; ?>>Grade 11 Only</option>
                                        <option value="12" <?php echo ($subject['grade_level'] ?? '') == '12' ? 'selected' : ''; ?>>Grade 12 Only</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Subject Type</label>
                                    <select name="subject_type" class="form-select">
                                        <option value="core" <?php echo ($subject['subject_type'] ?? 'core') == 'core' ? 'selected' : ''; ?>>Core Subject</option>
                                        <option value="elective" <?php echo ($subject['subject_type'] ?? '') == 'elective' ? 'selected' : ''; ?>>Elective Subject</option>
                                        <option value="optional" <?php echo ($subject['subject_type'] ?? '') == 'optional' ? 'selected' : ''; ?>>Optional Subject</option>
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="3" 
                                              placeholder="Enter subject description..."><?php echo htmlspecialchars($subject['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                               value="1" <?php echo ($subject['is_active'] ?? 1) == 1 ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            <i class="fas fa-check-circle text-success"></i> Subject is Active
                                        </label>
                                        <br>
                                        <small class="text-muted">Inactive subjects won't appear in enrollment forms</small>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="update_subject" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Statistics Card -->
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-chart-line me-2"></i> Subject Statistics
                    </div>
                    <div class="card-body">
                        <div class="stat-card">
                            <div class="stat-number text-primary"><?php echo $studentCount; ?></div>
                            <div class="stat-label">Enrolled Students</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number text-success"><?php echo $gradeCount; ?></div>
                            <div class="stat-label">Grades Recorded</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number text-warning"><?php echo $avgScore; ?>%</div>
                            <div class="stat-label">Average Score</div>
                        </div>
                    </div>
                </div>
                
                <!-- Teacher Assignment Card -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-chalkboard-user me-2"></i> Teacher Assignment
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">
                                    Assign Teacher (<?php echo htmlspecialchars($currentYear['year_name']); ?>)
                                </label>
                                <select name="teacher_id" class="form-select">
                                    <option value="">-- None Assigned --</option>
                                    <?php 
                                    if ($allTeachers && $allTeachers->num_rows > 0):
                                        $allTeachers->data_seek(0);
                                        while ($teacher = $allTeachers->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($teacher['teacher_id']); ?>" 
                                            <?php echo ($currentTeacher && $currentTeacher['teacher_id'] == $teacher['teacher_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($teacher['full_name']); ?>
                                        </option>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <option value="" disabled>No teachers available</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <button type="submit" name="assign_teacher" class="btn btn-success w-100">
                                <i class="fas fa-save me-2"></i> Assign Teacher
                            </button>
                        </form>
                        
                        <?php if ($currentTeacher): ?>
                            <hr>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Currently Assigned:</strong><br>
                                <?php echo htmlspecialchars($currentTeacher['full_name']); ?>
                            </div>
                        <?php else: ?>
                            <hr>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No teacher assigned for <?php echo htmlspecialchars($currentYear['year_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Status Information Card -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-secondary text-white">
                        <i class="fas fa-info-circle me-2"></i> Status Information
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Current Status:</strong>
                            <span class="badge bg-<?php echo ($subject['is_active'] ?? 1) == 1 ? 'success' : 'danger'; ?> ms-2">
                                <?php echo ($subject['is_active'] ?? 1) == 1 ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <div class="mb-2">
                            <strong>Subject Type:</strong>
                            <span class="badge bg-<?php echo ($subject['subject_type'] ?? 'core') == 'core' ? 'primary' : 'warning'; ?> ms-2">
                                <?php echo ucfirst($subject['subject_type'] ?? 'Core'); ?>
                            </span>
                        </div>
                        <div class="mb-2">
                            <strong>Credits:</strong>
                            <?php echo $subject['credits']; ?>
                        </div>
                        <?php if (isset($subject['created_at']) && $subject['created_at']): ?>
                        <div>
                            <strong>Created:</strong><br>
                            <small><?php echo date('M d, Y H:i', strtotime($subject['created_at'])); ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Warning Card for Deletion -->
                <?php if ($studentCount > 0 || $gradeCount > 0): ?>
                <div class="card shadow-sm mt-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <i class="fas fa-exclamation-triangle me-2"></i> Important Notice
                    </div>
                    <div class="card-body">
                        <small class="text-danger">
                            <i class="fas fa-info-circle me-1"></i>
                            This subject has <?php echo $studentCount; ?> student(s) enrolled and 
                            <?php echo $gradeCount; ?> grade(s) recorded. Deactivation is recommended instead of deletion.
                        </small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Subject not found! Please check the subject ID and try again.
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>