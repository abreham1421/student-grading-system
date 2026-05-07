<?php
// teacher/my_students.php - Fixed for simplified database
$pageTitle = 'My Students';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$teacherId = $_SESSION['teacher_id'] ?? 0;

// Fallback: get teacher ID from username
if (!$teacherId && isset($_SESSION['username'])) {
    $result = $conn->query("SELECT teacher_id FROM teacher WHERE username = '{$_SESSION['username']}'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $teacherId = $row['teacher_id'];
        $_SESSION['teacher_id'] = $teacherId;
    }
}

if (!$teacherId) {
    echo '<div class="alert alert-danger">Teacher ID not found. Please login again.</div>';
    require_once '../includes/footer.php';
    exit();
}

$selectedSubject = isset($_GET['subject_id']) ? $_GET['subject_id'] : 0;

// Get teacher's subjects - using teacher_id directly
$subjects = $conn->query("SELECT s.* FROM teacher_subject ts
                          JOIN subject s ON ts.subject_id = s.subject_id
                          WHERE ts.teacher_id = '$teacherId'");

// Get students for selected subject
$students = null;
$subjectName = '';
$subjectInfo = null;

if ($selectedSubject) {
    $subjResult = $conn->query("SELECT subject_name, subject_id FROM subject WHERE subject_id = '$selectedSubject'");
    if ($subjResult && $subjResult->num_rows > 0) {
        $subj = $subjResult->fetch_assoc();
        $subjectName = $subj['subject_name'] . ' (' . $subj['subject_id'] . ')';
        $subjectInfo = $subj;
    }
    
    $students = $conn->query("SELECT s.student_id, s.username, u.full_name, s.current_grade_level, s.current_section,
                              s.parent_name, s.parent_phone, s.enrollment_date
                              FROM student s
                              JOIN users u ON s.username = u.username
                              JOIN student_subject ss ON s.student_id = ss.student_id
                              WHERE ss.subject_id = '$selectedSubject' AND ss.is_active = 1
                              ORDER BY u.full_name");
}
?>

<style>
.students-header {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    border-radius: 20px;
    padding: 20px 25px;
    margin-bottom: 25px;
    color: white;
}
.subject-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.subject-list {
    max-height: 500px;
    overflow-y: auto;
}
.subject-item {
    transition: all 0.2s;
    border-left: 3px solid transparent;
}
.subject-item:hover {
    background: #f8f9fa;
}
.subject-item.active {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
    border-left-color: #ffc107;
}
.subject-item.active small {
    color: rgba(255,255,255,0.8);
}
.student-table th {
    background: #f8fafc;
    padding: 12px;
    font-size: 12px;
    font-weight: 600;
    color: #1e293b;
}
.student-table td {
    padding: 10px 12px;
    font-size: 13px;
    vertical-align: middle;
}
.student-table tr:hover td {
    background: #f8f9fa;
}
.badge-grade {
    background: #e0f2fe;
    color: #0284c7;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
}
.badge-section {
    background: #dcfce7;
    color: #16a34a;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
}
.action-btn {
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 12px;
    transition: all 0.2s;
}
.action-btn:hover {
    transform: translateY(-2px);
}
.empty-state {
    text-align: center;
    padding: 50px 20px;
}
.empty-icon {
    font-size: 48px;
    color: #cbd5e1;
    margin-bottom: 15px;
}
@media (max-width: 768px) {
    .student-table {
        min-width: 700px;
    }
    .table-responsive {
        overflow-x: auto;
    }
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="students-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h4 class="mb-1 fw-bold">
                    <i class="fas fa-users me-2"></i> My Students
                </h4>
                <p class="mb-0 small opacity-75">View and manage students enrolled in your subjects</p>
            </div>
            <div class="mt-2 mt-sm-0">
                <span class="badge bg-light text-dark">
                    <i class="fas fa-chalkboard-user me-1"></i> Teacher ID: <?php echo htmlspecialchars($teacherId); ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="row g-3">
        <!-- Subjects List -->
        <div class="col-md-3">
            <div class="subject-card">
                <div class="card-header bg-primary text-white py-2">
                    <i class="fas fa-book me-2"></i> My Subjects
                    <span class="float-end badge bg-light text-dark"><?php echo ($subjects) ? $subjects->num_rows : 0; ?></span>
                </div>
                <div class="subject-list">
                    <?php if ($subjects && $subjects->num_rows > 0): ?>
                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                            <a href="?subject_id=<?php echo $subject['subject_id']; ?>" 
                               class="list-group-item list-group-item-action subject-item <?php echo $selectedSubject == $subject['subject_id'] ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($subject['subject_id']); ?></strong>
                                        <br>
                                        <small class="<?php echo $selectedSubject == $subject['subject_id'] ? 'text-white' : 'text-muted'; ?>">
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        </small>
                                    </div>
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="fas fa-book fa-2x mb-2 d-block"></i>
                            No subjects assigned
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Students List -->
        <div class="col-md-9">
            <?php if ($selectedSubject && $students && $students->num_rows > 0): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white py-2">
                        <i class="fas fa-users me-2"></i> Students - <?php echo htmlspecialchars($subjectName); ?>
                        <span class="float-end">Total: <?php echo $students->num_rows; ?> students</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table student-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Class</th>
                                        <th>Parent Info</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = $students->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($student['student_id']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($student['username']); ?></small>
                                             </small>
                                            <td>
                                                <?php echo htmlspecialchars($student['full_name']); ?>
                                             </small>
                                            <td>
                                                <span class="badge-grade">Grade <?php echo $student['current_grade_level']; ?></span>
                                                <span class="badge-section ms-1">Sec <?php echo $student['current_section']; ?></span>
                                             </small>
                                            <td>
                                                <div class="small">
                                                    <div><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($student['parent_name'] ?: '-'); ?></div>
                                                    <div><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($student['parent_phone'] ?: '-'); ?></div>
                                                </div>
                                             </small>
                                            <td class="text-center">
                                                <a href="enter_grades.php?subject_id=<?php echo $selectedSubject; ?>" 
                                                   class="btn btn-sm btn-primary action-btn">
                                                    <i class="fas fa-edit me-1"></i> Enter Grade
                                                </a>
                                             </small>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-calendar-alt me-1"></i> 
                                Enrollment as of <?php echo date('F d, Y'); ?>
                            </small>
                            <small class="text-muted">
                                <i class="fas fa-check-circle me-1 text-success"></i> 
                                Active enrollments
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="row g-2 mt-3">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body py-2 text-center">
                                <h5 class="mb-0"><?php echo $students->num_rows; ?></h5>
                                <small>Total Students</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body py-2 text-center">
                                <h5 class="mb-0"><?php echo $students->num_rows; ?></h5>
                                <small>Active Enrollments</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body py-2 text-center">
                                <h5 class="mb-0"><?php echo $subjectInfo['subject_id'] ?? $selectedSubject; ?></h5>
                                <small>Subject Code</small>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($selectedSubject): ?>
                <div class="card shadow-sm">
                    <div class="card-body empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h5>No Students Enrolled</h5>
                        <p class="text-muted">No students are currently enrolled in this subject.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h5>Select a Subject</h5>
                        <p class="text-muted">Choose a subject from the left to view enrolled students.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>