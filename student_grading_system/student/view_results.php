<?php
// student/view_results.php - Professional Student Results Dashboard with Rank
$pageTitle = 'My Results';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$studentId = $_SESSION['profile_id'] ?? '';

if (empty($studentId) && isset($_SESSION['username'])) {
    $username = $conn->real_escape_string($_SESSION['username']);
    $result = $conn->query("SELECT student_id FROM student WHERE username = '$username'");
    if ($result && $result->num_rows > 0) {
        $studentId = $result->fetch_assoc()['student_id'];
        $_SESSION['profile_id'] = $studentId;
    }
}

if (empty($studentId)) {
    echo '<div class="alert alert-danger">Student ID not found. Please login again.</div>';
    require_once '../includes/footer.php';
    exit();
}

// Get current academic year
$currentYear = ['year_id' => 2, 'year_name' => '2025/26'];
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
if ($yearResult && $yearResult->num_rows > 0) {
    $currentYear = $yearResult->fetch_assoc();
}
$yearId = $currentYear['year_id'];

// Get student info
$studentInfoQuery = $conn->query("
    SELECT s.*, u.full_name, u.email, u.phone 
    FROM student s 
    JOIN users u ON s.username = u.username 
    WHERE s.student_id = '$studentId'
");
if (!$studentInfoQuery || $studentInfoQuery->num_rows == 0) {
    echo '<div class="alert alert-danger">Student information not found.</div>';
    require_once '../includes/footer.php';
    exit();
}
$studentInfo = $studentInfoQuery->fetch_assoc();

// Get all assessment types
$assessmentTypes = $conn->query("SELECT assessment_id, assessment_name, default_weight FROM assessment_type ORDER BY assessment_id");
$assessmentList = [];
if ($assessmentTypes && $assessmentTypes->num_rows > 0) {
    while ($type = $assessmentTypes->fetch_assoc()) {
        $assessmentList[] = $type;
    }
}

// Get all enrolled subjects with detailed marks
$subjects = $conn->query("
    SELECT DISTINCT s.subject_id, s.subject_name, s.credits, s.subject_type
    FROM student_subject ss
    JOIN subject s ON ss.subject_id = s.subject_id
    WHERE ss.student_id = '$studentId' AND ss.academic_year_id = $yearId AND ss.is_active = 1
    ORDER BY s.subject_id
");

// Get all marks for this student
$marksData = [];
$marksResult = $conn->query("
    SELECT m.subject_id, m.assessment_type_id, m.score, m.grade_date,
           at.assessment_name, at.default_weight as max_points
    FROM mark m
    LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
    WHERE m.student_id = '$studentId' AND m.academic_year_id = $yearId
");

if ($marksResult && $marksResult->num_rows > 0) {
    while ($mark = $marksResult->fetch_assoc()) {
        $marksData[$mark['subject_id']][$mark['assessment_type_id']] = [
            'score' => $mark['score'],
            'max_points' => $mark['max_points'] ?? 100,
            'assessment_name' => $mark['assessment_name'] ?? 'Assessment'
        ];
    }
}

// Get final grades
$finalGrades = [];
$finalResult = $conn->query("
    SELECT subject_id, total_score, letter_grade, grade_point
    FROM final_grade
    WHERE student_id = '$studentId' AND academic_year_id = $yearId
");
if ($finalResult && $finalResult->num_rows > 0) {
    while ($fg = $finalResult->fetch_assoc()) {
        $finalGrades[$fg['subject_id']] = $fg;
    }
}

// Calculate overall stats and collect scores for ranking
$totalScore = 0;
$totalSubjects = 0;
$passedSubjects = 0;
$subjectsArray = [];
$subjectScores = []; // For storing scores per subject

if ($subjects && $subjects->num_rows > 0) {
    while ($subject = $subjects->fetch_assoc()) {
        $subjectsArray[] = $subject;
        if (isset($finalGrades[$subject['subject_id']]['total_score'])) {
            $score = $finalGrades[$subject['subject_id']]['total_score'];
            $totalScore += $score;
            $totalSubjects++;
            if ($score >= 50) {
                $passedSubjects++;
            }
            $subjectScores[$subject['subject_id']] = $score;
        }
    }
    $subjects->data_seek(0);
}

$overallAverage = $totalSubjects > 0 ? $totalScore / $totalSubjects : 0;
$passRate = $totalSubjects > 0 ? ($passedSubjects / $totalSubjects) * 100 : 0;

// Calculate GPA
$creditPoints = 0;
$totalCredits = 0;
foreach ($subjectsArray as $subject) {
    if (isset($finalGrades[$subject['subject_id']]['grade_point'])) {
        $creditPoints += $finalGrades[$subject['subject_id']]['grade_point'] * $subject['credits'];
        $totalCredits += $subject['credits'];
    }
}
$gpa = $totalCredits > 0 ? round($creditPoints / $totalCredits, 2) : 0;

// ========== RANK CALCULATION ==========
// Get all students in the same grade and section with their total scores
$rankData = [];
$rankQuery = $conn->query("
    SELECT s.student_id, u.full_name, 
           COALESCE(SUM(fg.total_score), 0) as total_score,
           COUNT(fg.total_score) as subjects_count
    FROM student s
    JOIN users u ON s.username = u.username
    LEFT JOIN final_grade fg ON s.student_id = fg.student_id AND fg.academic_year_id = $yearId
    WHERE s.current_grade_level = '{$studentInfo['current_grade_level']}' 
      AND s.current_section = '{$studentInfo['current_section']}'
      AND s.student_status = 'active'
    GROUP BY s.student_id
    ORDER BY total_score DESC
");

$studentRanks = [];
$rank = 1;
$prevScore = -1;
$rankPosition = 1;

if ($rankQuery && $rankQuery->num_rows > 0) {
    while ($row = $rankQuery->fetch_assoc()) {
        if ($row['total_score'] != $prevScore) {
            $rank = $rankPosition;
        }
        $studentRanks[$row['student_id']] = [
            'rank' => $rank,
            'total_score' => $row['total_score'],
            'full_name' => $row['full_name']
        ];
        $rankPosition++;
        $prevScore = $row['total_score'];
    }
}

$currentStudentRank = $studentRanks[$studentId]['rank'] ?? 0;
$totalStudentsInClass = count($studentRanks);
$rankPercentage = $totalStudentsInClass > 0 ? round((($currentStudentRank - 1) / $totalStudentsInClass) * 100, 1) : 0;

// Helper function for letter grade
function getStudentResultLetterGrade($score) {
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

// Helper function for rank badge color
function getRankBadgeClass($rank) {
    if ($rank <= 1) return 'rank-1';
    if ($rank <= 3) return 'rank-2';
    if ($rank <= 5) return 'rank-3';
    if ($rank <= 10) return 'rank-top10';
    return 'rank-normal';
}
?>

<style>
:root {
    --primary: #1e3c72;
    --primary-light: #2a5298;
    --success: #28a745;
    --info: #17a2b8;
    --warning: #ffc107;
    --danger: #dc3545;
    --gray-50: #f8f9fa;
    --gray-100: #f1f3f5;
    --gray-200: #e9ecef;
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-in {
    animation: fadeInUp 0.5s ease-out;
}

/* Student Header */
.student-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
}

.student-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.student-avatar {
    width: 70px;
    height: 70px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid rgba(255,255,255,0.3);
}

/* Rank Badges */
.rank-badge-large {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #212529; }
.rank-2 { background: linear-gradient(135deg, #c0c0c0, #e8e8e8); color: #212529; }
.rank-3 { background: linear-gradient(135deg, #cd7f32, #e8a870); color: white; }
.rank-top10 { background: linear-gradient(135deg, #17a2b8, #3dd5f3); color: white; }
.rank-normal { background: linear-gradient(135deg, #6c757d, #8a929a); color: white; }

.rank-value {
    font-size: 32px;
    font-weight: 800;
    line-height: 1;
}

.rank-label {
    font-size: 10px;
    opacity: 0.9;
}

/* Stat Cards */
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
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

/* Premium Table */
.results-table-container {
    overflow-x: auto;
    border-radius: 16px;
}

.results-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.results-table thead th {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 10px;
    border: none;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
}

.results-table tbody td {
    padding: 10px;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-200);
}

.results-table tbody tr {
    transition: all 0.2s ease;
}

.results-table tbody tr:hover {
    background: var(--gray-50);
}

/* Sticky Columns */
.sticky-subject {
    position: sticky;
    left: 0;
    background: white;
    z-index: 5;
    box-shadow: 2px 0 5px -2px rgba(0,0,0,0.05);
}

.results-table tbody tr:hover .sticky-subject {
    background: var(--gray-50);
}

/* Score Badge */
.score-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 11px;
    display: inline-block;
}

.score-high { background: #d4edda; color: #155724; }
.score-medium { background: #fff3cd; color: #856404; }
.score-low { background: #f8d7da; color: #721c24; }

/* Grade Badges */
.grade-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 11px;
    display: inline-block;
}

.grade-a-plus, .grade-a, .grade-a-minus { background: linear-gradient(135deg, #28a745, #34ce57); color: white; }
.grade-b-plus, .grade-b, .grade-b-minus { background: linear-gradient(135deg, #17a2b8, #3dd5f3); color: white; }
.grade-c-plus, .grade-c, .grade-c-minus { background: linear-gradient(135deg, #ffc107, #ffce3a); color: #212529; }
.grade-d { background: linear-gradient(135deg, #fd7e14, #ff9f4a); color: white; }
.grade-f { background: linear-gradient(135deg, #dc3545, #e74c5c); color: white; }

/* GPA Circle */
.gpa-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.gpa-value {
    font-size: 28px;
    font-weight: 700;
    color: white;
    line-height: 1;
}

.gpa-label {
    font-size: 10px;
    color: rgba(255,255,255,0.8);
    margin-top: 2px;
}

/* Progress Bar */
.progress-premium {
    height: 4px;
    border-radius: 10px;
    background: var(--gray-200);
    width: 60px;
    margin: 0 auto;
}

/* Subject Total Badge */
.total-badge {
    background: linear-gradient(135deg, var(--info), #3dd5f3);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 12px;
    display: inline-block;
}

/* Print Styles */
@media print {
    .no-print, .sidebar, .navbar, .footer, .btn, .card-header button {
        display: none !important;
    }
    .results-table thead th {
        background: var(--primary) !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .stat-number {
        font-size: 22px;
    }
    .gpa-circle {
        width: 80px;
        height: 80px;
    }
    .gpa-value {
        font-size: 22px;
    }
    .rank-badge-large {
        width: 70px;
        height: 70px;
    }
    .rank-value {
        font-size: 24px;
    }
    .results-table {
        font-size: 10px;
    }
    .results-table td, .results-table th {
        padding: 6px 4px;
    }
}
</style>

<div class="container-fluid animate-in">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-chart-line me-2" style="color: var(--primary);"></i>
                <span style="background: linear-gradient(135deg, var(--primary), var(--primary-light)); -webkit-background-clip: text; background-clip: text; color: transparent;">Academic Results</span>
            </h1>
            <p class="text-muted small mt-1 mb-0">Complete breakdown of your academic performance</p>
        </div>
        <div class="no-print">
            <button class="btn btn-success rounded-pill px-4" onclick="window.print()">
                <i class="fas fa-print me-2"></i> Print Results
            </button>
        </div>
    </div>
    
    <!-- Student Info Header -->
    <div class="student-header">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="student-avatar">
                    <i class="fas fa-user-graduate fa-2x text-white"></i>
                </div>
            </div>
            <div class="col">
                <h2 class="text-white mb-1"><?php echo htmlspecialchars($studentInfo['full_name'] ?? 'N/A'); ?></h2>
                <div class="d-flex flex-wrap gap-3 text-white-50">
                    <span><i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($studentInfo['student_id'] ?? 'N/A'); ?></span>
                    <span><i class="fas fa-graduation-cap me-1"></i> Grade <?php echo $studentInfo['current_grade_level'] ?? 'N/A'; ?> - Section <?php echo $studentInfo['current_section'] ?? 'N/A'; ?></span>
                    <span><i class="fas fa-calendar-alt me-1"></i> <?php echo htmlspecialchars($currentYear['year_name']); ?></span>
                </div>
            </div>
            <div class="col-auto">
                <div class="gpa-circle">
                    <div class="gpa-value"><?php echo number_format($gpa, 2); ?></div>
                    <div class="gpa-label">GPA</div>
                </div>
            </div>
            <div class="col-auto">
                <div class="rank-badge-large <?php echo getRankBadgeClass($currentStudentRank); ?>">
                    <div class="rank-value">#<?php echo $currentStudentRank; ?></div>
                    <div class="rank-label">CLASS RANK</div>
                </div>
            </div>
        </div>
        <?php if ($currentStudentRank > 0 && $totalStudentsInClass > 0): ?>
        <div class="row mt-3">
            <div class="col-12">
                <div class="bg-white bg-opacity-10 rounded-3 p-2">
                    <div class="d-flex justify-content-between align-items-center small">
                        <span>Out of <strong><?php echo $totalStudentsInClass; ?></strong> students in Grade <?php echo $studentInfo['current_grade_level']; ?> - Section <?php echo $studentInfo['current_section']; ?></span>
                        <span>Top <strong><?php echo $rankPercentage; ?>%</strong> of class</span>
                        <?php if ($currentStudentRank <= 3): ?>
                            <span><i class="fas fa-trophy me-1"></i> Honor Roll</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number text-success"><?php echo $passedSubjects; ?></div>
                <div class="stat-label">Subjects Passed</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number text-danger"><?php echo $totalSubjects - $passedSubjects; ?></div>
                <div class="stat-label">Subjects Failed</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number text-info"><?php echo $totalSubjects; ?></div>
                <div class="stat-label">Total Subjects</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number text-warning"><?php echo number_format($overallAverage, 1); ?>%</div>
                <div class="stat-label">Average Score</div>
            </div>
        </div>
    </div>
    
    <!-- Detailed Results Table - Horizontal Layout -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 pb-0">
            <h6 class="mb-0">
                <i class="fas fa-table me-2 text-primary"></i>
                Detailed Assessment Results
            </h6>
            <p class="text-muted small mt-1">Subject-wise performance with all assessment breakdowns</p>
        </div>
        <div class="card-body p-0">
            <div class="results-table-container">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th style="min-width: 120px; position: sticky; left: 0; background: linear-gradient(135deg, var(--primary), var(--primary-light)); z-index: 20;">Subject</th>
                            <th style="min-width: 80px; position: sticky; left: 120px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); z-index: 20;">Credits</th>
                            <?php foreach ($assessmentList as $assessment): ?>
                                <th class="text-center" style="min-width: 100px;">
                                    <?php echo htmlspecialchars($assessment['assessment_name']); ?>
                                    <div class="small opacity-75">(max <?php echo $assessment['default_weight']; ?>)</div>
                                </th>
                            <?php endforeach; ?>
                            <th class="text-center" style="min-width: 80px; background: linear-gradient(135deg, #17a2b8, #3dd5f3);">Total</th>
                            <th class="text-center" style="min-width: 70px; background: linear-gradient(135deg, #28a745, #34ce57);">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($subjectsArray)): ?>
                            <?php foreach ($subjectsArray as $subject): 
                                $subjectMarks = $marksData[$subject['subject_id']] ?? [];
                                $totalEarned = 0;
                                $totalPossible = 0;
                                foreach ($assessmentList as $assessment) {
                                    if (isset($subjectMarks[$assessment['assessment_id']])) {
                                        $score = $subjectMarks[$assessment['assessment_id']]['score'];
                                        $maxPoints = $subjectMarks[$assessment['assessment_id']]['max_points'];
                                        $totalEarned += $score;
                                        $totalPossible += $maxPoints;
                                    }
                                }
                                $percentage = $totalPossible > 0 ? ($totalEarned / $totalPossible) * 100 : 0;
                                $finalGrade = isset($finalGrades[$subject['subject_id']]) ? $finalGrades[$subject['subject_id']]['letter_grade'] : null;
                                $finalScore = isset($finalGrades[$subject['subject_id']]) ? $finalGrades[$subject['subject_id']]['total_score'] : null;
                                $gradeClass = '';
                                if ($finalGrade) {
                                    $gradeMap = [
                                        'A+' => 'grade-a-plus', 'A' => 'grade-a', 'A-' => 'grade-a-minus',
                                        'B+' => 'grade-b-plus', 'B' => 'grade-b', 'B-' => 'grade-b-minus',
                                        'C+' => 'grade-c-plus', 'C' => 'grade-c', 'C-' => 'grade-c-minus',
                                        'D' => 'grade-d', 'F' => 'grade-f'
                                    ];
                                    $gradeClass = $gradeMap[$finalGrade] ?? 'grade-f';
                                }
                                $scoreClass = $percentage >= 60 ? 'score-high' : ($percentage >= 45 ? 'score-medium' : 'score-low');
                            ?>
                                <tr>
                                    <td class="sticky-subject">
                                        <strong><?php echo htmlspecialchars($subject['subject_id']); ?></strong>
                                        <div class="small text-muted"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                    </small>
                                    <td class="sticky-subject" style="left: 120px;">
                                        <span class="badge bg-secondary"><?php echo $subject['credits']; ?> cr</span>
                                    </small>
                                    
                                    <?php foreach ($assessmentList as $assessment): 
                                        $mark = isset($subjectMarks[$assessment['assessment_id']]) ? $subjectMarks[$assessment['assessment_id']] : null;
                                        $score = $mark ? $mark['score'] : null;
                                        $maxPoints = $mark ? $mark['max_points'] : $assessment['default_weight'];
                                        $scorePercent = $score ? ($score / $maxPoints) * 100 : 0;
                                    ?>
                                        <td class="text-center">
                                            <?php if ($score !== null): ?>
                                                <div>
                                                    <span class="fw-bold"><?php echo number_format($score, 1); ?></span>
                                                    <small class="text-muted">/<?php echo $maxPoints; ?></small>
                                                </div>
                                                <div class="progress progress-premium mt-1">
                                                    <div class="progress-bar bg-<?php echo $scorePercent >= 70 ? 'success' : ($scorePercent >= 50 ? 'warning' : 'danger'); ?>" 
                                                         style="width: <?php echo $scorePercent; ?>%"></div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </small>
                                    <?php endforeach; ?>
                                    
                                    <td class="text-center bg-light">
                                        <span class="total-badge">
                                            <?php echo $finalScore ? number_format($finalScore, 1) . '%' : (round($percentage, 1) . '%'); ?>
                                        </span>
                                    </small>
                                    <td class="text-center">
                                        <?php if ($finalGrade): ?>
                                            <span class="grade-badge <?php echo $gradeClass; ?>"><?php echo $finalGrade; ?></span>
                                        <?php elseif ($percentage > 0): ?>
                                            <span class="grade-badge grade-c"><?php echo getStudentResultLetterGrade($percentage); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </small>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo count($assessmentList) + 3; ?>" class="text-center py-5">
                                    <i class="fas fa-chart-line fa-4x text-muted mb-3 d-block"></i>
                                    <h5>No Results Available</h5>
                                    <p class="text-muted small">Your results will appear here once grades are released.</p>
                                 </small>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($subjectsArray) && $totalSubjects > 0): ?>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="2"><strong>Summary</strong></td>
                            <?php foreach ($assessmentList as $assessment): ?>
                                <td class="text-center"><strong>—</strong></small>
                            <?php endforeach; ?>
                            <td class="text-center"><strong><?php echo number_format($overallAverage, 1); ?>%</strong></small>
                            <td class="text-center"><span class="grade-badge <?php echo $overallAverage >= 60 ? 'grade-b' : ($overallAverage >= 45 ? 'grade-c' : 'grade-d'); ?>">
                                <?php echo getStudentResultLetterGrade($overallAverage); ?>
                            </span></small>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Academic Legend -->
    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-header bg-white border-0 pt-3">
            <h6 class="mb-0"><i class="fas fa-info-circle me-2 text-info"></i> Grading Scale</h6>
        </div>
        <div class="card-body pt-0">
            <div class="row small">
                <div class="col-md-2 col-4 mb-2"><span class="grade-badge grade-a-plus px-3">A+</span> 90-100%</div>
                <div class="col-md-2 col-4 mb-2"><span class="grade-badge grade-a px-3">A</span> 85-89%</div>
                <div class="col-md-2 col-4 mb-2"><span class="grade-badge grade-a-minus px-3">A-</span> 80-84%</div>
                <div class="col-md-2 col-4 mb-2"><span class="grade-badge grade-b-plus px-3">B+</span> 75-79%</div>
                <div class="col-md-2 col-4 mb-2"><span class="grade-badge grade-b px-3">B</span> 70-74%</div>
                <div class="col-md-2 col-4 mb-2"><span class="grade-badge grade-b-minus px-3">B-</span> 65-69%</div>
                <div class="col-md-2 col-4 mb-2"><span class="grade-badge grade-c-plus px-3">C+</span> 60-64%</div>
                <div class="col-md-2 col-4 mb-2"><span class="grade-badge grade-c px-3">C</span> 55-59%</div>
                <div class="col-md-2 col-4 mb-2"><span class="grade-badge grade-c-minus px-3">C-</span> 50-54%</div>
                <div class="col-md-2 col-4 mb-2"><span class="grade-badge grade-d px-3">D</span> 45-49%</div>
                <div class="col-md-2 col-4 mb-2"><span class="grade-badge grade-f px-3">F</span> Below 45%</div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>