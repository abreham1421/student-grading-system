<?php
// admin/view_student.php - Professional student details page (Clean version)
$pageTitle = 'View Student';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$student_id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : '';

if (empty($student_id)) {
    header('Location: manage_students.php');
    exit();
}

// Helper function for letter grade
function getStudentLetterGrade($score) {
    if ($score >= 90) return 'A+';
    if ($score >= 85) return 'A';
    if ($score >= 80) return 'A-';
    if ($score >= 75) return 'B+';
    if ($score >= 70) return 'B';
    if ($score >= 65) return 'B-';
    if ($score >= 60) return 'C+';
    if ($score >= 55) return 'C';
    if ($score >= 50) return 'C-';
    if ($score >= 45) return 'D';
    return 'F';
}

// Get current academic year
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
$currentYear = ($yearResult && $yearResult->num_rows > 0) ? $yearResult->fetch_assoc() : ['year_id' => 2, 'year_name' => '2025/26'];
$yearId = $currentYear['year_id'];

// Get student information
$studentQuery = $conn->query("
    SELECT s.*, u.username, u.email, u.full_name, u.phone, u.address, u.created_at, u.last_login,
           ay.year_name as current_year
    FROM student s 
    JOIN users u ON s.username = u.username 
    LEFT JOIN academic_year ay ON ay.is_current = 1
    WHERE s.student_id = '$student_id'
");

if (!$studentQuery || $studentQuery->num_rows == 0) {
    $error = "Student not found!";
} else {
    $student = $studentQuery->fetch_assoc();
}

// Get enrolled subjects with correct percentage scores from marks
$subjects = $conn->query("
    SELECT 
        sub.subject_id, 
        sub.subject_name, 
        sub.credits, 
        sub.subject_type,
        ss.enrollment_date,
        COALESCE(
            ROUND((SUM(m.score) / NULLIF(SUM(at.default_weight), 0)) * 100, 1),
            0
        ) as total_score,
        fg.letter_grade as final_grade
    FROM student_subject ss
    JOIN subject sub ON ss.subject_id = sub.subject_id
    LEFT JOIN mark m ON sub.subject_id = m.subject_id 
        AND m.student_id = ss.student_id 
        AND m.academic_year_id = ss.academic_year_id
    LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
    LEFT JOIN final_grade fg ON sub.subject_id = fg.subject_id 
        AND fg.student_id = ss.student_id 
        AND fg.academic_year_id = ss.academic_year_id
    WHERE ss.student_id = '$student_id' AND ss.is_active = 1 AND ss.academic_year_id = $yearId
    GROUP BY sub.subject_id, sub.subject_name, sub.credits, sub.subject_type, ss.enrollment_date, fg.letter_grade
    ORDER BY sub.subject_id
");

$totalSubjects = $subjects ? $subjects->num_rows : 0;

// Calculate total score for ALL enrolled subjects
$totalScoreSum = 0;
$subjectScoresList = [];
$passedSubjectsCount = 0;
$subjectsWithGrades = 0;

if ($subjects && $subjects->num_rows > 0) {
    $subjects->data_seek(0);
    while ($subject = $subjects->fetch_assoc()) {
        $score = $subject['total_score'];
        $subjectScoresList[] = $score;
        $totalScoreSum += $score;
        
        // Count subjects with actual grades (score > 0) for pass/fail stats
        if ($score > 0) {
            $subjectsWithGrades++;
            if ($score >= 50) {
                $passedSubjectsCount++;
            }
        }
    }
    $subjects->data_seek(0);
}

// Calculate Average Score = total of ALL subject scores / total number of subjects
$overallAverageScore = $totalSubjects > 0 ? round($totalScoreSum / $totalSubjects, 1) : 0;

// Calculate Pass Rate = (passed subjects / total subjects with grades) * 100
$passRate = $subjectsWithGrades > 0 ? round(($passedSubjectsCount / $subjectsWithGrades) * 100, 1) : 0;

// Get GPA from final_grade or calculate from scores
$gpaResult = $conn->query("
    SELECT ROUND(AVG(grade_point), 2) as gpa 
    FROM final_grade 
    WHERE student_id = '$student_id' AND academic_year_id = $yearId
");
$gpa = ($gpaResult && $gpaResult->num_rows > 0) ? $gpaResult->fetch_assoc()['gpa'] : 0;

// If no final_grade, calculate GPA from subject total scores (only subjects with grades)
if ($gpa == 0 && !empty($subjectScoresList)) {
    $totalPoints = 0;
    $gradedSubjects = 0;
    foreach ($subjectScoresList as $score) {
        if ($score > 0) {
            if ($score >= 90) $totalPoints += 4.00;
            elseif ($score >= 85) $totalPoints += 4.00;
            elseif ($score >= 80) $totalPoints += 3.75;
            elseif ($score >= 75) $totalPoints += 3.50;
            elseif ($score >= 70) $totalPoints += 3.00;
            elseif ($score >= 65) $totalPoints += 2.75;
            elseif ($score >= 60) $totalPoints += 2.50;
            elseif ($score >= 55) $totalPoints += 2.00;
            elseif ($score >= 50) $totalPoints += 1.75;
            elseif ($score >= 45) $totalPoints += 1.00;
            else $totalPoints += 0.00;
            $gradedSubjects++;
        }
    }
    $gpa = $gradedSubjects > 0 ? round($totalPoints / $gradedSubjects, 2) : 0;
}

// Get highest and lowest scores from subject totals
$highestScore = !empty($subjectScoresList) ? max($subjectScoresList) : 0;
$lowestScore = !empty($subjectScoresList) ? min($subjectScoresList) : 0;
$failedSubjectsCount = $subjectsWithGrades - $passedSubjectsCount;
?>

<!-- The rest of the HTML remains the same -->
<style>
:root {
    --primary: #1e3c72;
    --primary-dark: #152c52;
    --primary-light: #2a5298;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
    --info: #17a2b8;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-600: #6c757d;
}

/* Professional Card Styles */
.profile-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: all 0.3s ease;
}

.profile-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    padding: 30px;
    color: white;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: rgba(30, 60, 114, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}

.stat-label {
    font-size: 12px;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 5px;
}

.info-section {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--gray-200);
}

.info-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray-200);
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-200);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-size: 13px;
    color: var(--gray-600);
}

.info-value {
    font-weight: 500;
    color: #2c3e50;
}

.grade-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-pass { background: #d4edda; color: #155724; }
.badge-fail { background: #f8d7da; color: #721c24; }
.badge-core { background: #cce5ff; color: #004085; }
.badge-elective { background: #fff3cd; color: #856404; }

.subject-code {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    background: var(--gray-100);
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 12px;
}

.avatar-circle {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 4px solid rgba(255,255,255,0.2);
}

.progress-custom {
    height: 8px;
    border-radius: 10px;
    background: var(--gray-200);
}

.progress-custom .progress-bar {
    border-radius: 10px;
}

.table-professional {
    margin-bottom: 0;
}

.table-professional th {
    background: var(--gray-100);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 15px;
    border-bottom: 2px solid var(--gray-200);
}

.table-professional td {
    padding: 12px 15px;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-200);
}

.table-professional tbody tr:hover {
    background: var(--gray-100);
}

.btn-custom {
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-custom:hover {
    transform: translateY(-2px);
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-user-graduate me-2" style="color: var(--primary);"></i> Student Details
            </h1>
            <p class="text-muted mt-1 mb-0">Complete student profile and academic records</p>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_students.php" class="btn btn-outline-secondary btn-custom">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
            <a href="edit_student.php?id=<?php echo urlencode($student_id); ?>" class="btn btn-outline-primary btn-custom">
                <i class="fas fa-edit me-2"></i> Edit
            </a>
            <a href="enroll_student.php?id=<?php echo urlencode($student_id); ?>" class="btn btn-outline-success btn-custom">
                <i class="fas fa-book-open me-2"></i> Enroll
            </a>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php elseif (isset($student)): ?>
        
        <!-- Profile Header -->
        <div class="profile-card mb-4">
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <div class="avatar-circle mx-auto">
                            <i class="fas fa-user-graduate fa-4x text-white"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h2 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                        <p class="mb-2 opacity-75">
                            <i class="fas fa-user me-1"></i> @<?php echo htmlspecialchars($student['username']); ?>
                        </p>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="badge bg-light text-dark px-3 py-2">
                                <i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($student['student_id']); ?>
                            </span>
                            <span class="badge bg-success px-3 py-2">
                                <i class="fas fa-circle me-1" style="font-size: 8px;"></i> <?php echo ucfirst($student['student_status'] ?? 'Active'); ?>
                            </span>
                            <span class="badge bg-info px-3 py-2">
                                <i class="fas fa-graduation-cap me-1"></i> Grade <?php echo $student['current_grade_level']; ?> - Section <?php echo $student['current_section']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-white bg-opacity-10 rounded-3 p-3">
                            <div class="small opacity-75 mb-2">Contact Information</div>
                            <div class="mb-2">
                                <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($student['email']); ?>
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($student['phone'] ?: 'Not provided'); ?>
                            </div>
                            <div>
                                <i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($student['address'] ?: 'Not provided'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book fa-2x" style="color: var(--primary);"></i>
                    </div>
                    <div class="stat-number"><?php echo $totalSubjects; ?></div>
                    <div class="stat-label">Enrolled Subjects</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line fa-2x" style="color: var(--info);"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($overallAverageScore, 1); ?>%</div>
                    <div class="stat-label">Average Score</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star fa-2x" style="color: var(--warning);"></i>
                    </div>
                    <div class="stat-number"><?php echo $gpa; ?></div>
                    <div class="stat-label">Current GPA</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-flag-checkered fa-2x" style="color: var(--success);"></i>
                    </div>
                    <div class="stat-number"><?php echo $passRate; ?>%</div>
                    <div class="stat-label">Pass Rate</div>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Left Column - Academic Details -->
            <div class="col-lg-5">
                <!-- Score Range Card -->
                <div class="info-section mb-4">
                    <div class="info-title">
                        <i class="fas fa-chart-simple me-2"></i> Performance Overview
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded-3">
                                <small class="text-muted">Highest Score</small>
                                <h4 class="text-success mb-0"><?php echo number_format($highestScore, 1); ?>%</h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded-3">
                                <small class="text-muted">Lowest Score</small>
                                <h4 class="text-danger mb-0"><?php echo number_format($lowestScore, 1); ?>%</h4>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <small>Pass Rate (Subjects with Grades)</small>
                            <strong><?php echo $passRate; ?>%</strong>
                        </div>
                        <div class="progress progress-custom">
                            <div class="progress-bar bg-success" style="width: <?php echo $passRate; ?>%"></div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-3 pt-2">
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-success"><?php echo $passedSubjectsCount; ?></div>
                            <small class="text-muted">Subjects Passed</small>
                        </div>
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-danger"><?php echo $failedSubjectsCount; ?></div>
                            <small class="text-muted">Subjects Failed</small>
                        </div>
                        <div class="text-center">
                            <div class="fs-4 fw-bold"><?php echo $subjectsWithGrades; ?></div>
                            <small class="text-muted">Subjects with Grades</small>
                        </div>
                    </div>
                </div>
                
                <!-- Parent Information -->
                <?php if (!empty($student['parent_name'])): ?>
                <div class="info-section mb-4">
                    <div class="info-title">
                        <i class="fas fa-users me-2"></i> Parent/Guardian
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-user me-1"></i> Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['parent_name']); ?></span>
                    </div>
                    <?php if (!empty($student['parent_phone'])): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-phone me-1"></i> Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['parent_phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($student['parent_email'])): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-envelope me-1"></i> Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['parent_email']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Account Information -->
                <div class="info-section">
                    <div class="info-title">
                        <i class="fas fa-clock me-2"></i> Account Information
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-user me-1"></i> Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['username']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-calendar-alt me-1"></i> Enrolled</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-calendar-plus me-1"></i> Account Created</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-sign-in-alt me-1"></i> Last Login</span>
                        <span class="info-value"><?php echo $student['last_login'] ? date('M d, Y', strtotime($student['last_login'])) : 'Never'; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-7">
                <div class="info-section p-0">
                    <div class="info-title p-3 pb-0 mb-0">
                        <i class="fas fa-book me-2"></i> Enrolled Subjects
                        <span class="badge bg-primary ms-2"><?php echo $totalSubjects; ?> Subjects</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-professional" id="subjectsTable">
                            <thead>
                                <tr>
                                    <th>Subject ID</th>
                                    <th>Subject Name</th>
                                    <th class="text-center">Credits</th>
                                    <th>Type</th>
                                    <th class="text-center">Total Score (%)</th>
                                    <th class="text-center">Final Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($subjects && $subjects->num_rows > 0): ?>
                                    <?php while ($subject = $subjects->fetch_assoc()): 
                                        $score = $subject['total_score'];
                                        $letterGrade = $subject['final_grade'] ?: ($score > 0 ? getStudentLetterGrade($score) : null);
                                        $badgeClass = ($score >= 60) ? 'success' : (($score >= 45) ? 'warning' : 'danger');
                                    ?>
                                        <tr>
                                            <td><code class="subject-code"><?php echo htmlspecialchars($subject['subject_id']); ?></code></small>
                                            <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></small>
                                            <td class="text-center"><span class="badge bg-secondary"><?php echo $subject['credits']; ?> cr</span></small>
                                            <td>
                                                <span class="badge <?php echo $subject['subject_type'] == 'core' ? 'badge-core' : 'badge-elective'; ?>">
                                                    <?php echo ucfirst($subject['subject_type']); ?>
                                                </span>
                                            </small>
                                            <td class="text-center">
                                                <?php if ($score > 0): ?>
                                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                                        <span class="fw-bold"><?php echo number_format($score, 1); ?>%</span>
                                                        <div class="progress" style="width: 50px; height: 4px;">
                                                            <div class="progress-bar bg-<?php echo $badgeClass; ?>" style="width: <?php echo $score; ?>%"></div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </small>
                                            <td class="text-center">
                                                <?php if ($letterGrade): ?>
                                                    <span class="badge bg-primary"><?php echo $letterGrade; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </small>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-book-open fa-3x mb-3 d-block"></i>
                                            <p>No subjects enrolled for <?php echo htmlspecialchars($currentYear['year_name']); ?></p>
                                            <a href="enroll_student.php?id=<?php echo urlencode($student_id); ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus me-1"></i> Enroll Now
                                            </a>
                                        </small>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
    <?php endif; ?>
</div>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#subjectsTable').DataTable({
        pageLength: 10,
        responsive: true,
        order: [[0, 'asc']],
        language: {
            search: "<i class='fas fa-search'></i> Search:",
            searchPlaceholder: "Type to search...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ subjects",
            infoEmpty: "No subjects found",
            paginate: {
                previous: "<",
                next: ">"
            }
        },
        columnDefs: [
            { orderable: false, targets: [2, 3, 4, 5] }
        ]
    });
});
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<?php require_once '../includes/footer.php'; ?>