<?php
// student/dashboard.php - Professional Student Dashboard (Recent Grades Removed)
$pageTitle = 'Student Dashboard';
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
    echo '<div class="alert alert-danger">Student profile not found. Please login again.</div>';
    require_once '../includes/footer.php';
    exit();
}

// Get current academic year
$currentYear = ['year_id' => 2, 'year_name' => '2025/26'];
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
if ($yearResult && $yearResult->num_rows > 0) {
    $yearData = $yearResult->fetch_assoc();
    $currentYear = $yearData;
}
$yearId = $currentYear['year_id'];

// Get student information
$studentInfo = [];
$studentQuery = $conn->query("
    SELECT s.*, u.full_name, u.email, u.phone 
    FROM student s 
    JOIN users u ON s.username = u.username 
    WHERE s.student_id = '$studentId'
");
if ($studentQuery && $studentQuery->num_rows > 0) {
    $studentInfo = $studentQuery->fetch_assoc();
}

// Get enrolled subjects count
$subjectsResult = $conn->query("
    SELECT COUNT(*) as total FROM student_subject 
    WHERE student_id = '$studentId' AND academic_year_id = $yearId AND is_active = 1
");
$subjectsCount = ($subjectsResult && $subjectsResult->num_rows > 0) ? $subjectsResult->fetch_assoc()['total'] : 0;

// Get grades count - Count DISTINCT subjects with grades
$gradesResult = $conn->query("
    SELECT COUNT(DISTINCT subject_id) as total FROM mark 
    WHERE student_id = '$studentId' AND academic_year_id = $yearId
");
$gradesCount = ($gradesResult && $gradesResult->num_rows > 0) ? $gradesResult->fetch_assoc()['total'] : 0;

// Get all subjects with their total scores (percentage)
$allSubjectsScore = $conn->query("
    SELECT 
        sub.subject_id,
        COALESCE(
            ROUND((SUM(m.score) / NULLIF(SUM(at.default_weight), 0)) * 100, 1),
            0
        ) as subject_score
    FROM student_subject ss
    JOIN subject sub ON ss.subject_id = sub.subject_id
    LEFT JOIN mark m ON sub.subject_id = m.subject_id 
        AND m.student_id = '$studentId' 
        AND m.academic_year_id = $yearId
    LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
    WHERE ss.student_id = '$studentId' 
    AND ss.is_active = 1 
    AND ss.academic_year_id = $yearId
    GROUP BY sub.subject_id
");

// Calculate Average Score = total of all subject scores / number of subjects
$totalSubjectScore = 0;
$subjectScoreList = [];
if ($allSubjectsScore && $allSubjectsScore->num_rows > 0) {
    while ($row = $allSubjectsScore->fetch_assoc()) {
        $subjectScoreList[] = $row['subject_score'];
        $totalSubjectScore += $row['subject_score'];
    }
}
$averageScore = $subjectsCount > 0 ? round($totalSubjectScore / $subjectsCount, 1) : 0;

// Calculate Pass Rate = (subjects with score >= 50) / total subjects * 100
$passedCount = 0;
foreach ($subjectScoreList as $score) {
    if ($score >= 50) {
        $passedCount++;
    }
}
$passRate = $subjectsCount > 0 ? round(($passedCount / $subjectsCount) * 100, 1) : 0;

// Calculate GPA from subject scores
$gpa = 0;
if (!empty($subjectScoreList)) {
    $totalPoints = 0;
    $gradedSubjects = 0;
    foreach ($subjectScoreList as $score) {
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

// Get subject performance for chart
$subjectPerformance = $conn->query("
    SELECT 
        sub.subject_id, 
        sub.subject_name, 
        COALESCE(
            ROUND((SUM(m.score) / NULLIF(SUM(at.default_weight), 0)) * 100, 1),
            0
        ) as avg_score
    FROM student_subject ss
    JOIN subject sub ON ss.subject_id = sub.subject_id
    LEFT JOIN mark m ON sub.subject_id = m.subject_id 
        AND m.student_id = '$studentId' 
        AND m.academic_year_id = $yearId
    LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
    WHERE ss.student_id = '$studentId' 
    AND ss.is_active = 1 
    AND ss.academic_year_id = $yearId
    GROUP BY sub.subject_id, sub.subject_name
    ORDER BY avg_score DESC
    LIMIT 6
");

// Helper function for letter grade
function getStudentDashboardLetterGrade($score) {
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

/* Welcome Header */
.welcome-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 20px;
    padding: 20px 25px;
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
}

.welcome-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
}

/* Stat Cards */
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 15px;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
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

/* GPA Circle */
.gpa-circle {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.gpa-value {
    font-size: 24px;
    font-weight: 700;
    color: white;
    line-height: 1;
}

.gpa-label {
    font-size: 9px;
    color: rgba(255,255,255,0.8);
}

/* Chart Container */
.chart-container {
    position: relative;
    min-height: 220px;
    max-height: 220px;
}

/* Profile Card */
.profile-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--gray-200);
}

.profile-avatar {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Responsive */
@media (max-width: 768px) {
    .stat-number {
        font-size: 22px;
    }
    .gpa-circle {
        width: 70px;
        height: 70px;
    }
    .gpa-value {
        font-size: 18px;
    }
}
</style>

<div class="container-fluid animate-in">
    <!-- Welcome Header -->
    <div class="welcome-header">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="profile-avatar">
                    <i class="fas fa-user-graduate fa-2x text-white"></i>
                </div>
            </div>
            <div class="col">
                <h2 class="text-white mb-1">Welcome back, <?php echo htmlspecialchars(explode(' ', $studentInfo['full_name'] ?? 'Student')[0]); ?>!</h2>
                <div class="d-flex flex-wrap gap-3 text-white-50 small">
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
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number text-primary"><?php echo $subjectsCount; ?></div>
                        <div class="stat-label">Enrolled Subjects</div>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10">
                        <i class="fas fa-book text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number text-success"><?php echo number_format($averageScore, 1); ?>%</div>
                        <div class="stat-label">Average Score</div>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10">
                        <i class="fas fa-chart-line text-success"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number text-info"><?php echo number_format($passRate, 1); ?>%</div>
                        <div class="stat-label">Pass Rate</div>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10">
                        <i class="fas fa-flag-checkered text-info"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number text-warning"><?php echo $gradesCount; ?></div>
                        <div class="stat-label">Subjects with Grades</div>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10">
                        <i class="fas fa-clipboard-list text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Performance Chart -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-bar me-2 text-primary"></i>
                        Subject Performance
                    </h6>
                    <p class="text-muted small mt-1">Your performance across different subjects</p>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Card -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h6 class="mb-0">
                        <i class="fas fa-user-circle me-2 text-primary"></i>
                        My Profile
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="profile-avatar me-3">
                            <i class="fas fa-user-graduate fa-2x text-white"></i>
                        </div>
                        <div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($studentInfo['full_name'] ?? 'N/A'); ?></h5>
                            <small class="text-muted"><?php echo htmlspecialchars($studentInfo['student_id'] ?? 'N/A'); ?></small>
                        </div>
                    </div>
                    <div class="row small g-2">
                        <div class="col-6">
                            <div class="bg-light rounded-3 p-2">
                                <div class="text-muted">Grade Level</div>
                                <strong><?php echo $studentInfo['current_grade_level'] ?? 'N/A'; ?></strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded-3 p-2">
                                <div class="text-muted">Section</div>
                                <strong><?php echo $studentInfo['current_section'] ?? 'N/A'; ?></strong>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="bg-light rounded-3 p-2">
                                <div class="text-muted">Email</div>
                                <strong><small><?php echo htmlspecialchars($studentInfo['email'] ?? 'N/A'); ?></small></strong>
                            </div>
                        </div>
                        <?php if (!empty($studentInfo['phone'])): ?>
                        <div class="col-12">
                            <div class="bg-light rounded-3 p-2">
                                <div class="text-muted">Phone</div>
                                <strong><small><?php echo htmlspecialchars($studentInfo['phone']); ?></small></strong>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h6 class="mb-0">
                        <i class="fas fa-bolt me-2 text-warning"></i>
                        Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="view_results.php" class="btn btn-outline-primary rounded-pill px-4">
                            <i class="fas fa-chart-line me-1"></i> View Results
                        </a>
                        <a href="my_subjects.php" class="btn btn-outline-info rounded-pill px-4">
                            <i class="fas fa-book me-1"></i> My Subjects
                        </a>
                        <a href="profile.php" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-user me-1"></i> Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('performanceChart').getContext('2d');
    const labels = [];
    const scores = [];
    
    <?php if ($subjectPerformance && $subjectPerformance->num_rows > 0): ?>
        <?php while ($subject = $subjectPerformance->fetch_assoc()): ?>
            labels.push('<?php echo addslashes($subject['subject_id']); ?>');
            scores.push(<?php echo round($subject['avg_score'], 1); ?>);
        <?php endwhile; ?>
    <?php endif; ?>
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels.length ? labels : ['No Data'],
            datasets: [{
                label: 'Your Score (%)',
                data: scores.length ? scores : [0],
                backgroundColor: 'rgba(30, 60, 114, 0.7)',
                borderColor: '#1e3c72',
                borderWidth: 1,
                borderRadius: 6,
                barPercentage: 0.6,
                categoryPercentage: 0.8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { 
                    position: 'top', 
                    labels: { font: { size: 10 }, boxWidth: 10, padding: 10 }
                },
                tooltip: { 
                    bodyFont: { size: 10 },
                    callbacks: {
                        label: function(context) {
                            return `Score: ${context.raw}%`;
                        }
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    max: 100,
                    title: { display: true, text: 'Score (%)', font: { size: 9 } },
                    ticks: { stepSize: 25, font: { size: 9 } },
                    grid: { color: '#e9ecef' }
                },
                x: { 
                    ticks: { font: { size: 9 } },
                    grid: { display: false }
                }
            }
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>