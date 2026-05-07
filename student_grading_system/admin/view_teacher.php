<?php
// admin/view_teacher.php - Professional teacher details page with enhanced tables
$pageTitle = 'View Teacher';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$teacher_id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : '';

if (empty($teacher_id)) {
    header('Location: manage_teachers.php');
    exit();
}

// Helper function for letter grade
function getViewTeacherLetterGrade($score, $maxPoints = 100) {
    $percentage = ($score / max(1, $maxPoints)) * 100;
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

// Get teacher information
$teacherQuery = $conn->query("
    SELECT t.*, u.username, u.email, u.full_name, u.phone, u.address, u.created_at, u.last_login
    FROM teacher t 
    JOIN users u ON t.username = u.username 
    WHERE t.teacher_id = '$teacher_id'
");

if (!$teacherQuery || $teacherQuery->num_rows == 0) {
    $error = "Teacher not found!";
} else {
    $teacher = $teacherQuery->fetch_assoc();
}

// Get assigned subjects for current academic year with statistics
$assignedSubjects = $conn->query("
    SELECT s.subject_id, s.subject_name, s.credits, s.subject_type,
           ts.assigned_date,
           COUNT(DISTINCT ss.student_id) as student_count,
           COUNT(DISTINCT m.mark_id) as grade_count
    FROM teacher_subject ts
    JOIN subject s ON ts.subject_id = s.subject_id
    LEFT JOIN student_subject ss ON s.subject_id = ss.subject_id AND ss.is_active = 1 AND ss.academic_year_id = $yearId
    LEFT JOIN mark m ON s.subject_id = m.subject_id AND m.academic_year_id = $yearId
    WHERE ts.teacher_id = '$teacher_id' AND ts.academic_year_id = $yearId AND ts.is_primary = 1
    GROUP BY s.subject_id
    ORDER BY s.subject_id
");

// Calculate total subject scores for overall average
$subjectScores = [];
$subjectScoreResult = $conn->query("
    SELECT 
        s.subject_id,
        ROUND(AVG((m.score / at.default_weight) * 100), 1) as subject_avg_score
    FROM teacher_subject ts
    JOIN subject s ON ts.subject_id = s.subject_id
    LEFT JOIN mark m ON s.subject_id = m.subject_id AND m.academic_year_id = $yearId
    LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
    WHERE ts.teacher_id = '$teacher_id' AND ts.academic_year_id = $yearId AND ts.is_primary = 1
    GROUP BY s.subject_id
    HAVING subject_avg_score > 0
");

$totalSubjectScoreSum = 0;
$subjectCountForAvg = 0;
if ($subjectScoreResult && $subjectScoreResult->num_rows > 0) {
    while ($row = $subjectScoreResult->fetch_assoc()) {
        $totalSubjectScoreSum += $row['subject_avg_score'];
        $subjectCountForAvg++;
        $subjectScores[] = $row['subject_avg_score'];
    }
}

// Calculate Overall Average = total of all subject scores / number of subjects
$overallAvgScore = $subjectCountForAvg > 0 ? round($totalSubjectScoreSum / $subjectCountForAvg, 1) : 0;

// Calculate Overall Pass Rate = (subjects with avg_score >= 50) / total subjects * 100
$passedSubjectsCount = 0;
foreach ($subjectScores as $score) {
    if ($score >= 50) {
        $passedSubjectsCount++;
    }
}
$overallPassRate = $subjectCountForAvg > 0 ? round(($passedSubjectsCount / $subjectCountForAvg) * 100, 1) : 0;

// Get total statistics
$totalStats = $conn->query("
    SELECT 
        COUNT(DISTINCT s.subject_id) as total_subjects,
        COUNT(DISTINCT ss.student_id) as total_students,
        COUNT(DISTINCT m.mark_id) as total_grades
    FROM teacher_subject ts
    JOIN subject s ON ts.subject_id = s.subject_id
    LEFT JOIN student_subject ss ON s.subject_id = ss.subject_id AND ss.is_active = 1 AND ss.academic_year_id = $yearId
    LEFT JOIN mark m ON s.subject_id = m.subject_id AND m.academic_year_id = $yearId
    WHERE ts.teacher_id = '$teacher_id' AND ts.academic_year_id = $yearId AND ts.is_primary = 1
")->fetch_assoc();

// Get assessment types for horizontal table
$assessmentTypes = $conn->query("SELECT assessment_id, assessment_name, default_weight FROM assessment_type ORDER BY assessment_id");
$assessmentList = [];
if ($assessmentTypes && $assessmentTypes->num_rows > 0) {
    while ($type = $assessmentTypes->fetch_assoc()) {
        $assessmentList[] = $type;
    }
}

// Get recent grades grouped by student and subject for horizontal display
$recentGradesGrouped = [];
$recentGradesQuery = $conn->query("
    SELECT 
        m.mark_id,
        m.score,
        m.grade_date,
        m.assessment_type_id,
        m.student_id,
        m.subject_id,
        u.full_name as student_name,
        sub.subject_name,
        at.assessment_name,
        at.default_weight as max_points
    FROM mark m
    JOIN student s ON m.student_id = s.student_id
    JOIN users u ON s.username = u.username
    JOIN subject sub ON m.subject_id = sub.subject_id
    LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
    WHERE m.teacher_id = '$teacher_id' AND m.academic_year_id = $yearId
    ORDER BY m.grade_date DESC, m.mark_id DESC
    LIMIT 30
");

// Group grades by student and subject for horizontal display
$groupedGrades = [];
if ($recentGradesQuery && $recentGradesQuery->num_rows > 0) {
    while ($grade = $recentGradesQuery->fetch_assoc()) {
        $key = $grade['student_id'] . '_' . $grade['subject_id'] . '_' . date('Y-m-d', strtotime($grade['grade_date']));
        if (!isset($groupedGrades[$key])) {
            $groupedGrades[$key] = [
                'student_id' => $grade['student_id'],
                'student_name' => $grade['student_name'],
                'subject_id' => $grade['subject_id'],
                'subject_name' => $grade['subject_name'],
                'grade_date' => $grade['grade_date'],
                'marks' => []
            ];
        }
        $groupedGrades[$key]['marks'][$grade['assessment_type_id']] = [
            'score' => $grade['score'],
            'max_points' => $grade['max_points'] ?? 100
        ];
    }
}

// Get grade distribution per subject
$subjectPerformance = [];
if ($assignedSubjects && $assignedSubjects->num_rows > 0) {
    $assignedSubjects->data_seek(0);
    while ($subject = $assignedSubjects->fetch_assoc()) {
        $gradeDist = $conn->query("
            SELECT 
                SUM(CASE WHEN score >= 90 THEN 1 ELSE 0 END) as Aplus,
                SUM(CASE WHEN score >= 85 AND score < 90 THEN 1 ELSE 0 END) as A,
                SUM(CASE WHEN score >= 80 AND score < 85 THEN 1 ELSE 0 END) as Amin,
                SUM(CASE WHEN score >= 75 AND score < 80 THEN 1 ELSE 0 END) as Bplus,
                SUM(CASE WHEN score >= 70 AND score < 75 THEN 1 ELSE 0 END) as B,
                SUM(CASE WHEN score >= 65 AND score < 70 THEN 1 ELSE 0 END) as Bmin,
                SUM(CASE WHEN score >= 60 AND score < 65 THEN 1 ELSE 0 END) as Cplus,
                SUM(CASE WHEN score >= 55 AND score < 60 THEN 1 ELSE 0 END) as C,
                SUM(CASE WHEN score >= 50 AND score < 55 THEN 1 ELSE 0 END) as Cmin,
                SUM(CASE WHEN score >= 45 AND score < 50 THEN 1 ELSE 0 END) as D,
                SUM(CASE WHEN score < 45 THEN 1 ELSE 0 END) as F
            FROM mark 
            WHERE subject_id = '{$subject['subject_id']}' AND teacher_id = '$teacher_id' AND academic_year_id = $yearId
        ")->fetch_assoc();
        $subject['grade_distribution'] = $gradeDist;
        $subjectPerformance[] = $subject;
    }
    $assignedSubjects->data_seek(0);
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

.avatar-circle {
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

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}

.stat-label {
    font-size: 11px;
    color: #6c757d;
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

.table-professional {
    margin-bottom: 0;
}

.table-professional th {
    background: var(--gray-100);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px;
    border-bottom: 2px solid var(--gray-200);
}

.table-professional td {
    padding: 12px;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-200);
}

.table-professional tbody tr:hover {
    background: var(--gray-100);
}

/* Horizontal Grade Table Styles */
.horizontal-grade-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.horizontal-grade-table thead th {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    padding: 10px 8px;
    border: none;
    white-space: nowrap;
}
.horizontal-grade-table tbody td {
    padding: 8px;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}
.horizontal-grade-table tbody tr:hover {
    background: #f8f9fa;
}
.horizontal-grade-table .sticky-col {
    position: sticky;
    background: white;
    z-index: 10;
}
.horizontal-grade-table .sticky-col-left {
    left: 0;
    box-shadow: 2px 0 5px -2px rgba(0,0,0,0.05);
}
.horizontal-grade-table .sticky-col-left-2 {
    left: 100px;
}
.horizontal-grade-table .sticky-col-left-3 {
    left: 200px;
}

.progress-custom {
    height: 6px;
    border-radius: 10px;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }

.grade-badge-sm {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.total-badge-sm {
    background: linear-gradient(135deg, #17a2b8, #3dd5f3);
    color: white;
    padding: 3px 8px;
    border-radius: 15px;
    font-size: 10px;
    font-weight: 600;
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-chalkboard-user me-2" style="color: var(--primary);"></i> Teacher Details
            </h1>
            <p class="text-muted mt-1 mb-0">Complete teacher profile and academic records</p>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_teachers.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
            <a href="edit_teacher.php?id=<?php echo urlencode($teacher_id); ?>" class="btn btn-outline-primary">
                <i class="fas fa-edit me-2"></i> Edit
            </a>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php elseif (isset($teacher)): ?>
        
        <!-- Profile Header -->
        <div class="profile-card mb-4">
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <div class="avatar-circle">
                            <i class="fas fa-chalkboard-user fa-5x text-white"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h2 class="mb-1"><?php echo htmlspecialchars($teacher['full_name']); ?></h2>
                        <p class="mb-2 opacity-75">
                            <i class="fas fa-user me-1"></i> @<?php echo htmlspecialchars($teacher['username']); ?>
                        </p>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="status-badge status-<?php echo $teacher['status'] ?? 'active'; ?>">
                                <i class="fas fa-circle me-1" style="font-size: 8px;"></i> <?php echo ucfirst($teacher['status'] ?? 'Active'); ?>
                            </span>
                            <span class="badge bg-light text-dark px-3 py-2">
                                <i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($teacher['teacher_id']); ?>
                            </span>
                            <span class="badge bg-info px-3 py-2">
                                <i class="fas fa-calendar-alt me-1"></i> <?php echo $teacher['experience_years'] ?? 0; ?>+ Years Experience
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-white bg-opacity-10 rounded-3 p-3">
                            <div class="small opacity-75 mb-2">Contact Information</div>
                            <div class="mb-2">
                                <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($teacher['email']); ?>
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($teacher['phone'] ?: 'Not provided'); ?>
                            </div>
                            <div>
                                <i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($teacher['address'] ?: 'Not provided'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Left Column -->
            <div class="col-lg-4">
                <!-- Professional Information -->
                <div class="info-section mb-4">
                    <div class="info-title">
                        <i class="fas fa-graduation-cap me-2"></i> Professional Information
                    </div>
                    <div class="row g-2">
                        <div class="col-12">
                            <small class="text-muted">Qualification</small>
                            <div class="fw-semibold"><?php echo htmlspecialchars($teacher['qualification'] ?: 'Not Specified'); ?></div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Specialization</small>
                            <div class="fw-semibold"><?php echo htmlspecialchars($teacher['specialization'] ?: 'General'); ?></div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Department</small>
                            <div class="fw-semibold"><?php echo htmlspecialchars($teacher['department'] ?: 'General'); ?></div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Join Date</small>
                            <div class="fw-semibold"><?php echo $teacher['join_date'] ? date('M d, Y', strtotime($teacher['join_date'])) : 'N/A'; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="stat-card">
                            <div class="stat-number text-primary"><?php echo number_format($totalStats['total_subjects'] ?? 0); ?></div>
                            <div class="stat-label">Subjects</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card">
                            <div class="stat-number text-success"><?php echo number_format($totalStats['total_students'] ?? 0); ?></div>
                            <div class="stat-label">Students</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card">
                            <div class="stat-number text-info"><?php echo number_format($totalStats['total_grades'] ?? 0); ?></div>
                            <div class="stat-label">Grades Recorded</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card">
                            <div class="stat-number text-warning"><?php echo $overallAvgScore; ?>%</div>
                            <div class="stat-label">Avg Score</div>
                        </div>
                    </div>
                </div>
                
                <!-- Overall Performance -->
                <div class="info-section">
                    <div class="info-title">
                        <i class="fas fa-chart-line me-2"></i> Overall Performance
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1 small">
                            <span>Pass Rate</span>
                            <strong><?php echo $overallPassRate; ?>%</strong>
                        </div>
                        <div class="progress progress-custom">
                            <div class="progress-bar bg-success" style="width: <?php echo $overallPassRate; ?>%"></div>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <small class="text-muted">Subjects Passed</small>
                            <div class="fw-semibold"><?php echo $passedSubjectsCount; ?>/<?php echo $subjectCountForAvg; ?></div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Active Subjects</small>
                            <div class="fw-semibold"><?php echo $totalStats['total_subjects'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <small class="text-muted">Account Created</small>
                            <div class="small fw-semibold"><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Last Login</small>
                            <div class="small fw-semibold"><?php echo $teacher['last_login'] ? date('M d, Y', strtotime($teacher['last_login'])) : 'Never'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="col-lg-8">
                <!-- Assigned Subjects Table -->
                <div class="info-section mb-4 p-0">
                    <div class="info-title p-3 pb-0 mb-0">
                        <i class="fas fa-book me-2"></i> Assigned Subjects (<?php echo htmlspecialchars($currentYear['year_name']); ?>)
                        <span class="badge bg-primary ms-2"><?php echo $assignedSubjects ? $assignedSubjects->num_rows : 0; ?> Subjects</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-professional" id="subjectsTable">
                            <thead>
                                <tr>
                                    <th>Subject ID</th>
                                    <th>Subject Name</th>
                                    <th>Credits</th>
                                    <th>Students</th>
                                    <th>Grades</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($assignedSubjects && $assignedSubjects->num_rows > 0): ?>
                                    <?php while ($subject = $assignedSubjects->fetch_assoc()): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($subject['subject_id']); ?></code></small>
                                            <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></small>
                                            <td><span class="badge bg-secondary"><?php echo $subject['credits']; ?> cr</span></small>
                                            <td><span class="badge bg-primary"><?php echo number_format($subject['student_count'] ?? 0); ?></span></small>
                                            <td><span class="badge bg-info"><?php echo number_format($subject['grade_count'] ?? 0); ?></span></small>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="fas fa-book-open fa-3x mb-2 d-block"></i>
                                            <p>No subjects assigned for <?php echo htmlspecialchars($currentYear['year_name']); ?></p>
                                        </small>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Recent Grades Entered - Horizontal Table like grade entry -->
                <?php if (!empty($groupedGrades)): ?>
                <div class="info-section p-0">
                    <div class="info-title p-3 pb-0 mb-0">
                        <i class="fas fa-history me-2"></i> Recently Entered Grades
                        <span class="badge bg-info ms-2">Last <?php echo count($groupedGrades); ?> Entries</span>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="horizontal-grade-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 100px; position: sticky; left: 0; background: linear-gradient(135deg, #1e3c72, #2a5298); z-index: 20;">Date</th>
                                    <th style="min-width: 150px; position: sticky; left: 100px; background: linear-gradient(135deg, #1e3c72, #2a5298); z-index: 20;">Student</th>
                                    <th style="min-width: 100px; position: sticky; left: 250px; background: linear-gradient(135deg, #1e3c72, #2a5298); z-index: 20;">Subject</th>
                                    <?php foreach ($assessmentList as $assessment): ?>
                                        <th class="text-center" style="min-width: 90px;">
                                            <?php echo htmlspecialchars($assessment['assessment_name']); ?>
                                            <div class="small opacity-75">(max <?php echo $assessment['default_weight']; ?>)</div>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="text-center" style="min-width: 80px;">Total</th>
                                    <th class="text-center" style="min-width: 70px;">Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $displayCount = 0;
                                foreach ($groupedGrades as $entry): 
                                    if ($displayCount >= 10) break;
                                    $totalEarned = 0;
                                    $totalPossible = 0;
                                    foreach ($assessmentList as $assessment) {
                                        if (isset($entry['marks'][$assessment['assessment_id']])) {
                                            $score = $entry['marks'][$assessment['assessment_id']]['score'];
                                            $maxPoints = $entry['marks'][$assessment['assessment_id']]['max_points'];
                                            $totalEarned += $score;
                                            $totalPossible += $maxPoints;
                                        }
                                    }
                                    $percentage = $totalPossible > 0 ? ($totalEarned / $totalPossible) * 100 : 0;
                                    $letterGrade = getViewTeacherLetterGrade($totalEarned, $totalPossible);
                                    $badgeClass = $percentage >= 70 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');
                                ?>
                                    <tr>
                                        <td class="sticky-col sticky-col-left" style="background: white;">
                                            <small><?php echo date('M d', strtotime($entry['grade_date'])); ?></small>
                                        </small>
                                        <td class="sticky-col sticky-col-left-2" style="left: 100px; background: white;">
                                            <strong><?php echo htmlspecialchars(explode(' ', $entry['student_name'])[0]); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($entry['student_id']); ?></small>
                                        </small>
                                        <td class="sticky-col sticky-col-left-3" style="left: 250px; background: white;">
                                            <strong><?php echo htmlspecialchars($entry['subject_id']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($entry['subject_name']); ?></small>
                                        </small>
                                        
                                        <?php foreach ($assessmentList as $assessment): 
                                            $mark = isset($entry['marks'][$assessment['assessment_id']]) ? $entry['marks'][$assessment['assessment_id']] : null;
                                            $score = $mark ? $mark['score'] : null;
                                            $maxPoints = $assessment['default_weight'];
                                        ?>
                                            <td class="text-center">
                                                <?php if ($score !== null && $score > 0): ?>
                                                    <span class="fw-bold"><?php echo number_format($score, 1); ?></span>
                                                    <br><small class="text-muted">/<?php echo $maxPoints; ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </small>
                                        <?php endforeach; ?>
                                        
                                        <td class="text-center bg-light">
                                            <span class="total-badge-sm"><?php echo number_format($totalEarned, 1); ?></span>
                                        </small>
                                        <td class="text-center">
                                            <?php if ($totalEarned > 0): ?>
                                                <span class="badge bg-<?php echo $badgeClass; ?> grade-badge-sm"><?php echo $letterGrade; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </small>
                                    </tr>
                                <?php 
                                    $displayCount++;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
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
            infoEmpty: "No subjects found"
        }
    });
});
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<?php require_once '../includes/footer.php'; ?>