<?php
// admin/view_subject.php - Professional view subject page with modern grade distribution
$pageTitle = 'View Subject';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$subject_id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : '';

if (empty($subject_id)) {
    header('Location: manage_subjects.php');
    exit();
}

// Helper function for letter grade using percentage
function getSubjectLetterGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 85) return 'A';
    if ($percentage >= 80) return 'A-';
    if ($percentage >= 75) return 'B+';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 65) return 'B-';
    if ($percentage >= 60) return 'C+';
    if ($percentage >= 55) return 'C';
    if ($percentage >= 50) return 'C-';
    if ($percentage >= 45) return 'D';
    return 'F';
}

// Get current academic year
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
$currentYear = ($yearResult && $yearResult->num_rows > 0) ? $yearResult->fetch_assoc() : ['year_id' => 2, 'year_name' => '2025/26'];
$yearId = $currentYear['year_id'];

// Get subject information
$subjectQuery = $conn->query("SELECT * FROM subject WHERE subject_id = '$subject_id'");

if (!$subjectQuery || $subjectQuery->num_rows == 0) {
    $error = "Subject not found!";
} else {
    $subject = $subjectQuery->fetch_assoc();
}

// Get assigned teacher for current academic year
$assignedTeacher = $conn->query("
    SELECT t.teacher_id, u.full_name, u.email, u.phone, u.address,
           ts.assigned_date
    FROM teacher_subject ts
    JOIN teacher t ON ts.teacher_id = t.teacher_id
    JOIN users u ON t.username = u.username
    WHERE ts.subject_id = '$subject_id' 
    AND ts.academic_year_id = $yearId
    AND ts.is_primary = 1
    LIMIT 1
");
$teacher = ($assignedTeacher && $assignedTeacher->num_rows > 0) ? $assignedTeacher->fetch_assoc() : null;

// Get enrolled students with their grades (using percentage calculation)
$students = $conn->query("
    SELECT 
        s.student_id, 
        u.full_name, 
        s.current_grade_level, 
        s.current_section,
        ss.enrollment_date,
        COALESCE(
            ROUND((SUM(m.score) / NULLIF(SUM(at.default_weight), 0)) * 100, 1),
            0
        ) as average_score,
        (SELECT ROUND((score / at2.default_weight) * 100, 1) 
         FROM mark m2 
         LEFT JOIN assessment_type at2 ON m2.assessment_type_id = at2.assessment_id
         WHERE m2.student_id = s.student_id 
         AND m2.subject_id = '$subject_id' 
         AND m2.academic_year_id = $yearId 
         ORDER BY m2.grade_date DESC LIMIT 1) as last_score,
        (SELECT letter_grade FROM final_grade 
         WHERE student_id = s.student_id AND subject_id = '$subject_id' 
         AND academic_year_id = $yearId LIMIT 1) as final_grade
    FROM student_subject ss
    JOIN student s ON ss.student_id = s.student_id
    JOIN users u ON s.username = u.username
    LEFT JOIN mark m ON s.student_id = m.student_id 
        AND m.subject_id = '$subject_id' 
        AND m.academic_year_id = $yearId
    LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
    WHERE ss.subject_id = '$subject_id' 
    AND ss.is_active = 1 
    AND ss.academic_year_id = $yearId
    GROUP BY s.student_id, u.full_name, s.current_grade_level, s.current_section, ss.enrollment_date
    ORDER BY average_score DESC
");

$totalStudents = $students ? $students->num_rows : 0;

// Calculate total score sum and count students with scores
$totalScoreSum = 0;
$studentsWithScores = 0;
$studentScoresList = [];

if ($students && $students->num_rows > 0) {
    $students->data_seek(0);
    while ($student = $students->fetch_assoc()) {
        if ($student['average_score'] > 0) {
            $totalScoreSum += $student['average_score'];
            $studentsWithScores++;
            $studentScoresList[] = $student['average_score'];
        }
    }
    $students->data_seek(0);
}

// Calculate Average Score = total of all student scores / number of students with scores
$overallAverageScore = $studentsWithScores > 0 ? round($totalScoreSum / $studentsWithScores, 1) : 0;

// Get grade statistics based on student average scores
$highestScore = !empty($studentScoresList) ? max($studentScoresList) : 0;
$lowestScore = !empty($studentScoresList) ? min($studentScoresList) : 0;
$passedCount = 0;
$failedCount = 0;
foreach ($studentScoresList as $score) {
    if ($score >= 50) $passedCount++;
    else $failedCount++;
}
$passRate = $studentsWithScores > 0 ? round(($passedCount / $studentsWithScores) * 100, 1) : 0;

// Get grade distribution based on student average scores
$gradeDistribution = [
    'A+' => 0, 'A' => 0, 'A-' => 0,
    'B+' => 0, 'B' => 0, 'B-' => 0,
    'C+' => 0, 'C' => 0, 'C-' => 0,
    'D' => 0, 'F' => 0
];

foreach ($studentScoresList as $percentage) {
    if ($percentage >= 90) $gradeDistribution['A+']++;
    elseif ($percentage >= 85) $gradeDistribution['A']++;
    elseif ($percentage >= 80) $gradeDistribution['A-']++;
    elseif ($percentage >= 75) $gradeDistribution['B+']++;
    elseif ($percentage >= 70) $gradeDistribution['B']++;
    elseif ($percentage >= 65) $gradeDistribution['B-']++;
    elseif ($percentage >= 60) $gradeDistribution['C+']++;
    elseif ($percentage >= 55) $gradeDistribution['C']++;
    elseif ($percentage >= 50) $gradeDistribution['C-']++;
    elseif ($percentage >= 45) $gradeDistribution['D']++;
    else $gradeDistribution['F']++;
}

$totalGradesRecorded = array_sum($gradeDistribution);

// Calculate grade range percentages
$distinctionRate = $totalGradesRecorded > 0 ? round(($gradeDistribution['A+'] + $gradeDistribution['A'] + $gradeDistribution['A-']) / $totalGradesRecorded * 100, 1) : 0;
$goodRate = $totalGradesRecorded > 0 ? round(($gradeDistribution['B+'] + $gradeDistribution['B'] + $gradeDistribution['B-']) / $totalGradesRecorded * 100, 1) : 0;
$needsImprovementRate = $totalGradesRecorded > 0 ? round(($gradeDistribution['C+'] + $gradeDistribution['C'] + $gradeDistribution['C-']) / $totalGradesRecorded * 100, 1) : 0;
$failureRate = $totalGradesRecorded > 0 ? round($gradeDistribution['F'] / $totalGradesRecorded * 100, 1) : 0;

// Grade colors for charts
$gradeColors = [
    'A+' => '#28a745', 'A' => '#34ce57', 'A-' => '#48e06b',
    'B+' => '#17a2b8', 'B' => '#3dd5f3', 'B-' => '#62e0f5',
    'C+' => '#ffc107', 'C' => '#ffce3a', 'C-' => '#ffda6b',
    'D' => '#fd7e14', 'F' => '#dc3545'
];
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
    --gray-600: #6c757d;
}

/* Modern Grade Distribution Styles */
.grade-dist-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    overflow: hidden;
}

.grade-bar-item {
    animation: slideIn 0.5s ease-out forwards;
    opacity: 0;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.grade-bar-item:nth-child(1) { animation-delay: 0.05s; }
.grade-bar-item:nth-child(2) { animation-delay: 0.10s; }
.grade-bar-item:nth-child(3) { animation-delay: 0.15s; }
.grade-bar-item:nth-child(4) { animation-delay: 0.20s; }
.grade-bar-item:nth-child(5) { animation-delay: 0.25s; }
.grade-bar-item:nth-child(6) { animation-delay: 0.30s; }
.grade-bar-item:nth-child(7) { animation-delay: 0.35s; }
.grade-bar-item:nth-child(8) { animation-delay: 0.40s; }
.grade-bar-item:nth-child(9) { animation-delay: 0.45s; }
.grade-bar-item:nth-child(10) { animation-delay: 0.50s; }
.grade-bar-item:nth-child(11) { animation-delay: 0.55s; }

.grade-letter-badge {
    display: inline-block;
    width: 45px;
    text-align: center;
    padding: 5px 0;
    border-radius: 25px;
    font-weight: 700;
    font-size: 13px;
    font-family: 'Courier New', monospace;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.grade-progress {
    height: 10px;
    border-radius: 10px;
    background: #e9ecef;
    overflow: hidden;
}

.grade-progress-bar {
    transition: width 1s ease-in-out;
    border-radius: 10px;
}

.summary-stat-card {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
}

.summary-stat-card:hover {
    transform: translateY(-3px);
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.summary-stat-value {
    font-size: 28px;
    font-weight: 700;
}

.summary-stat-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 5px;
}

/* Existing styles */
.profile-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    overflow: hidden;
}

.profile-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    padding: 25px 30px;
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

.subject-badge {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    background: rgba(255,255,255,0.2);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
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

.progress-custom {
    height: 8px;
    border-radius: 10px;
    background: var(--gray-200);
}

.progress-custom .progress-bar {
    border-radius: 10px;
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-book me-2" style="color: var(--primary);"></i> Subject Details
            </h1>
            <p class="text-muted mt-1 mb-0">Complete subject information and academic records</p>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_subjects.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
            <a href="edit_subject.php?id=<?php echo urlencode($subject_id); ?>" class="btn btn-outline-primary">
                <i class="fas fa-edit me-2"></i> Edit
            </a>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php elseif (isset($subject)): ?>
        
        <!-- Subject Header Card -->
        <div class="profile-card mb-4">
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-1"><?php echo htmlspecialchars($subject['subject_name']); ?></h2>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="subject-badge">
                                <i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($subject['subject_id']); ?>
                            </span>
                            <span class="badge bg-light text-dark px-3 py-2">
                                <i class="fas fa-star me-1"></i> <?php echo $subject['credits']; ?> Credits
                            </span>
                            <span class="badge bg-<?php echo ($subject['subject_type'] ?? 'core') == 'core' ? 'success' : 'warning'; ?> px-3 py-2">
                                <i class="fas fa-tag me-1"></i> <?php echo ucfirst($subject['subject_type'] ?? 'Core'); ?>
                            </span>
                            <span class="badge bg-<?php echo ($subject['is_active'] ?? 1) == 1 ? 'info' : 'secondary'; ?> px-3 py-2">
                                <i class="fas fa-circle me-1" style="font-size: 8px;"></i> <?php echo ($subject['is_active'] ?? 1) == 1 ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-white bg-opacity-10 rounded-3 p-3">
                            <div class="small opacity-75 mb-2">Grade Level</div>
                            <div class="fs-5 fw-bold">
                                <i class="fas fa-graduation-cap me-2"></i> <?php echo htmlspecialchars($subject['grade_level'] ?? '9-12'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Left Column -->
            <div class="col-lg-4">
                <!-- Teacher Information -->
                <div class="info-section mb-4">
                    <div class="info-title">
                        <i class="fas fa-chalkboard-user me-2"></i> Assigned Teacher
                    </div>
                    <?php if ($teacher): ?>
                        <div class="text-center mb-3">
                            <div class="rounded-circle bg-success d-inline-flex p-3 mb-2">
                                <i class="fas fa-user fa-2x text-white"></i>
                            </div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($teacher['full_name']); ?></h5>
                            <p class="text-muted small"><?php echo htmlspecialchars($teacher['teacher_id']); ?></p>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-envelope me-1"></i> Email</span>
                            <span class="info-value"><?php echo htmlspecialchars($teacher['email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-phone me-1"></i> Phone</span>
                            <span class="info-value"><?php echo htmlspecialchars($teacher['phone'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-calendar me-1"></i> Assigned Date</span>
                            <span class="info-value"><?php echo date('M d, Y', strtotime($teacher['assigned_date'])); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-user-slash fa-3x text-muted mb-2 d-block"></i>
                            <p class="text-muted mb-0">No teacher assigned yet.</p>
                            <a href="edit_subject.php?id=<?php echo urlencode($subject_id); ?>" class="btn btn-sm btn-primary mt-2">
                                <i class="fas fa-plus me-1"></i> Assign Teacher
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="stat-card p-3">
                            <div class="stat-icon" style="width: 40px; height: 40px;">
                                <i class="fas fa-users fa-lg" style="color: var(--primary);"></i>
                            </div>
                            <div class="stat-number fs-2"><?php echo $totalStudents; ?></div>
                            <div class="stat-label">Enrolled Students</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card p-3">
                            <div class="stat-icon" style="width: 40px; height: 40px;">
                                <i class="fas fa-chart-line fa-lg" style="color: var(--info);"></i>
                            </div>
                            <div class="stat-number fs-2"><?php echo number_format($overallAverageScore, 1); ?>%</div>
                            <div class="stat-label">Average Score</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card p-3">
                            <div class="stat-icon" style="width: 40px; height: 40px;">
                                <i class="fas fa-star fa-lg" style="color: var(--warning);"></i>
                            </div>
                            <div class="stat-number fs-2"><?php echo $studentsWithScores; ?></div>
                            <div class="stat-label">Students with Grades</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card p-3">
                            <div class="stat-icon" style="width: 40px; height: 40px;">
                                <i class="fas fa-flag-checkered fa-lg" style="color: var(--success);"></i>
                            </div>
                            <div class="stat-number fs-2"><?php echo $passRate; ?>%</div>
                            <div class="stat-label">Pass Rate</div>
                        </div>
                    </div>
                </div>
                
                <!-- Score Range -->
                <div class="info-section">
                    <div class="info-title">
                        <i class="fas fa-chart-simple me-2"></i> Score Range
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="text-center p-2 bg-light rounded-3">
                                <small class="text-muted">Highest</small>
                                <h4 class="text-success mb-0"><?php echo number_format($highestScore, 1); ?>%</h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 bg-light rounded-3">
                                <small class="text-muted">Lowest</small>
                                <h4 class="text-danger mb-0"><?php echo number_format($lowestScore, 1); ?>%</h4>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1 small">
                            <span>Passed: <?php echo $passedCount; ?></span>
                            <span>Failed: <?php echo $failedCount; ?></span>
                        </div>
                        <div class="progress progress-custom">
                            <div class="progress-bar bg-success" style="width: <?php echo $passRate; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="col-lg-8">
                <!-- Professional Grade Distribution Section -->
                <div class="grade-dist-card mb-4">
                    <div class="card-header bg-white border-0 pt-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-pie me-2 text-primary"></i> Grade Distribution
                            </h6>
                            <span class="badge bg-primary rounded-pill"><?php echo $totalGradesRecorded; ?> Students</span>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-4">
                            <!-- Horizontal Progress Bars -->
                            <div class="col-lg-7">
                                <?php foreach ($gradeDistribution as $grade => $count): 
                                    $percentage = $totalGradesRecorded > 0 ? ($count / $totalGradesRecorded) * 100 : 0;
                                    $barColor = $gradeColors[$grade] ?? '#6c757d';
                                ?>
                                    <div class="grade-bar-item mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="grade-letter-badge" style="background: <?php echo $barColor; ?>; color: <?php echo in_array($grade, ['C+', 'C', 'C-']) ? '#212529' : 'white'; ?>">
                                                    <?php echo $grade; ?>
                                                </span>
                                                <span class="badge bg-light text-dark"><?php echo $count; ?> student<?php echo $count != 1 ? 's' : ''; ?></span>
                                            </div>
                                            <span class="fw-semibold small"><?php echo number_format($percentage, 1); ?>%</span>
                                        </div>
                                        <div class="grade-progress">
                                            <div class="grade-progress-bar" style="width: <?php echo $percentage; ?>%; background: <?php echo $barColor; ?>;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Donut Chart & Summary -->
                            <div class="col-lg-5">
                                <div class="text-center">
                                    <canvas id="gradeDonutChart" style="max-height: 180px; width: 100%;"></canvas>
                                </div>
                                <div class="row g-2 mt-3">
                                    <div class="col-6">
                                        <div class="summary-stat-card">
                                            <div class="summary-stat-value text-success"><?php echo $distinctionRate; ?>%</div>
                                            <div class="summary-stat-label">Distinction</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="summary-stat-card">
                                            <div class="summary-stat-value text-info"><?php echo $goodRate; ?>%</div>
                                            <div class="summary-stat-label">Good</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="summary-stat-card">
                                            <div class="summary-stat-value text-warning"><?php echo $needsImprovementRate; ?>%</div>
                                            <div class="summary-stat-label">Needs Improvement</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="summary-stat-card">
                                            <div class="summary-stat-value text-danger"><?php echo $failureRate; ?>%</div>
                                            <div class="summary-stat-label">Failure</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Enrolled Students -->
                <div class="info-section p-0">
                    <div class="info-title p-3 pb-0 mb-0">
                        <i class="fas fa-users me-2"></i> Enrolled Students
                        <span class="badge bg-primary ms-2"><?php echo $totalStudents; ?> Students</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-professional" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Grade</th>
                                    <th>Section</th>
                                    <th class="text-center">Avg Score</th>
                                    <th class="text-center">Final Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($students && $students->num_rows > 0): ?>
                                    <?php while ($student = $students->fetch_assoc()): 
                                        $avgScore = $student['average_score'];
                                        $letterGrade = $student['final_grade'] ?: ($avgScore > 0 ? getSubjectLetterGrade($avgScore) : null);
                                        $badgeClass = $avgScore >= 60 ? 'success' : ($avgScore >= 45 ? 'warning' : 'danger');
                                    ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($student['student_id']); ?></code></small>
                                            <td><?php echo htmlspecialchars($student['full_name']); ?></small>
                                            <td><span class="badge bg-secondary">Grade <?php echo $student['current_grade_level']; ?></span></small>
                                            <td><?php echo $student['current_section']; ?></small>
                                            <td class="text-center">
                                                <?php if ($avgScore > 0): ?>
                                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                                        <span class="fw-bold"><?php echo number_format($avgScore, 1); ?>%</span>
                                                        <div class="progress" style="width: 50px; height: 4px;">
                                                            <div class="progress-bar bg-<?php echo $badgeClass; ?>" style="width: <?php echo $avgScore; ?>%"></div>
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
                                            <i class="fas fa-users fa-3x mb-2 d-block"></i>
                                            <p>No students enrolled in this subject.</p>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#studentsTable').DataTable({
        pageLength: 10,
        responsive: true,
        order: [[4, 'desc']],
        language: {
            search: "<i class='fas fa-search'></i> Search:",
            searchPlaceholder: "Type to search...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ students",
            infoEmpty: "No students found",
            paginate: {
                previous: "<",
                next: ">"
            }
        },
        columnDefs: [
            { orderable: false, targets: [2, 3, 5] }
        ]
    });
});

// Grade Distribution Donut Chart
const donutCtx = document.getElementById('gradeDonutChart').getContext('2d');
new Chart(donutCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_keys($gradeDistribution)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_values($gradeDistribution)); ?>,
            backgroundColor: [
                '#28a745', '#34ce57', '#48e06b',
                '#17a2b8', '#3dd5f3', '#62e0f5',
                '#ffc107', '#ffce3a', '#ffda6b',
                '#fd7e14', '#dc3545'
            ],
            borderWidth: 0,
            hoverOffset: 8,
            cutout: '60%'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    font: { size: 9 },
                    boxWidth: 8,
                    padding: 5,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = <?php echo $totalGradesRecorded; ?>;
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    }
});
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<?php require_once '../includes/footer.php'; ?>