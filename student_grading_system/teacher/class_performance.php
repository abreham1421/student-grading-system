<?php
// teacher/class_performance.php - Premium Class Performance Dashboard with Compact Graph
$pageTitle = 'Class Performance';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$teacherId = $_SESSION['profile_id'] ?? '';

if (empty($teacherId) && isset($_SESSION['username'])) {
    $username = $conn->real_escape_string($_SESSION['username']);
    $result = $conn->query("SELECT teacher_id FROM teacher WHERE username = '$username'");
    if ($result && $result->num_rows > 0) {
        $teacherId = $result->fetch_assoc()['teacher_id'];
        $_SESSION['profile_id'] = $teacherId;
    }
}

if (empty($teacherId)) {
    echo '<div class="alert alert-danger">Teacher profile not found.</div>';
    require_once '../includes/footer.php';
    exit();
}

// Get current academic year
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
$currentYear = ($yearResult && $yearResult->num_rows > 0) ? $yearResult->fetch_assoc() : ['year_id' => 2, 'year_name' => '2025/26'];
$yearId = $currentYear['year_id'];

$selectedSubject = isset($_GET['subject_id']) ? $conn->real_escape_string($_GET['subject_id']) : '';

// Get teacher's subjects
$subjects = $conn->query("
    SELECT s.* FROM teacher_subject ts
    JOIN subject s ON ts.subject_id = s.subject_id
    WHERE ts.teacher_id = '$teacherId' AND ts.academic_year_id = $yearId
");

$subjectName = '';
$performanceData = [];
$chartLabels = [];
$chartScores = [];

if ($selectedSubject) {
    $subjResult = $conn->query("SELECT subject_name FROM subject WHERE subject_id = '$selectedSubject'");
    if ($subjResult && $subjResult->num_rows > 0) {
        $subj = $subjResult->fetch_assoc();
        $subjectName = $subj['subject_name'];
    }
    
    // Get student performance with assessment totals
    $performanceQuery = $conn->query("
        SELECT s.student_id, u.full_name, s.current_grade_level, s.current_section,
               COALESCE(SUM(m.score), 0) as total_earned,
               COALESCE(SUM(at.default_weight), 0) as total_possible,
               COUNT(DISTINCT m.mark_id) as grade_count
        FROM student s
        JOIN users u ON s.username = u.username
        JOIN student_subject ss ON s.student_id = ss.student_id
        LEFT JOIN mark m ON s.student_id = m.student_id AND m.subject_id = '$selectedSubject' AND m.academic_year_id = $yearId
        LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
        WHERE ss.subject_id = '$selectedSubject' AND ss.is_active = 1 AND ss.academic_year_id = $yearId
        GROUP BY s.student_id
        ORDER BY u.full_name
    ");
    
    if ($performanceQuery && $performanceQuery->num_rows > 0) {
        while ($row = $performanceQuery->fetch_assoc()) {
            $totalPossible = $row['total_possible'] > 0 ? $row['total_possible'] : 100;
            $avgScore = $totalPossible > 0 ? ($row['total_earned'] / $totalPossible) * 100 : 0;
            
            $performanceData[] = [
                'student_id' => $row['student_id'],
                'full_name' => $row['full_name'],
                'grade_level' => $row['current_grade_level'],
                'section' => $row['current_section'],
                'total_earned' => round($row['total_earned'], 1),
                'total_possible' => $totalPossible,
                'avg_score' => round($avgScore, 1),
                'grade_count' => $row['grade_count']
            ];
        }
    }
    
    // Get data for chart - top 10 students
    if (!empty($performanceData)) {
        $sortedData = $performanceData;
        usort($sortedData, function($a, $b) {
            return $b['avg_score'] <=> $a['avg_score'];
        });
        $topStudents = array_slice($sortedData, 0, 10);
        
        foreach ($topStudents as $student) {
            $shortName = explode(' ', $student['full_name'])[0];
            $chartLabels[] = $shortName;
            $chartScores[] = $student['avg_score'];
        }
    }
    
    // Calculate statistics
    $stats = ['avg' => 0, 'max' => 0, 'min' => 100, 'pass' => 0, 'fail' => 0, 'total' => 0];
    $scores = [];
    
    foreach ($performanceData as $student) {
        $score = $student['avg_score'];
        if ($score > 0 || $student['grade_count'] > 0) {
            $scores[] = $score;
            $stats['total']++;
            if ($score >= 50) $stats['pass']++;
            else $stats['fail']++;
            if ($score > $stats['max']) $stats['max'] = $score;
            if ($score < $stats['min']) $stats['min'] = $score;
        }
    }
    
    $stats['avg'] = !empty($scores) ? array_sum($scores) / count($scores) : 0;
    $stats['min'] = $stats['min'] == 100 ? 0 : $stats['min'];
    $stats['pass_rate'] = $stats['total'] > 0 ? round(($stats['pass'] / $stats['total']) * 100, 1) : 0;
}

// Helper function for letter grade
function getPerformanceLetterGrade($score) {
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
@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-in {
    animation: slideUp 0.5s ease-out;
}

/* Subject Cards */
.subject-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    margin-bottom: 8px;
    border: 1px solid var(--gray-200);
    background: white;
}

.subject-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: var(--primary);
}

.subject-card.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-color: transparent;
}

.subject-card.active .text-muted {
    color: rgba(255,255,255,0.7) !important;
}

/* Stat Cards - Compact */
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 12px;
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}

.stat-label {
    font-size: 10px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}

/* Chart Container - Compact */
.chart-container {
    position: relative;
    min-height: 200px;
    max-height: 200px;
}

/* Rank Badge */
.rank-badge {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 11px;
}

.rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #212529; }
.rank-2 { background: linear-gradient(135deg, #c0c0c0, #e8e8e8); color: #212529; }
.rank-3 { background: linear-gradient(135deg, #cd7f32, #e8a870); color: white; }

/* Grade Badges - Compact */
.grade-badge-premium {
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 10px;
    display: inline-block;
}

.grade-A { background: linear-gradient(135deg, #28a745, #34ce57); color: white; }
.grade-B { background: linear-gradient(135deg, #17a2b8, #3dd5f3); color: white; }
.grade-C { background: linear-gradient(135deg, #ffc107, #ffce3a); color: #212529; }
.grade-D { background: linear-gradient(135deg, #fd7e14, #ff9f4a); color: white; }
.grade-F { background: linear-gradient(135deg, #dc3545, #e74c5c); color: white; }

/* Status Badge - Compact */
.status-pass {
    background: #d4edda;
    color: #155724;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.status-fail {
    background: #f8d7da;
    color: #721c24;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

/* Progress Bar - Compact */
.progress-premium {
    height: 4px;
    border-radius: 10px;
    background: var(--gray-200);
}

/* Table - Compact */
.table-premium {
    font-size: 12px;
}

.table-premium thead th {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 8px;
    border: none;
}

.table-premium tbody td {
    padding: 8px;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-200);
}

.table-premium tbody tr:hover {
    background: var(--gray-50);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state i {
    font-size: 48px;
    color: var(--gray-200);
    margin-bottom: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .stat-number {
        font-size: 20px;
    }
    .table-premium {
        font-size: 10px;
    }
    .table-premium td, .table-premium th {
        padding: 6px 4px;
    }
}
</style>

<div class="container-fluid animate-in">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                <i class="fas fa-chart-line me-2" style="color: var(--primary);"></i>
                Class Performance
            </h1>
            <p class="text-muted small mt-1 mb-0">Monitor student performance and class analytics</p>
        </div>
        <?php if ($selectedSubject): ?>
            <div class="badge bg-primary px-3 py-2 rounded-pill">
                <i class="fas fa-calendar-alt me-1"></i> <?php echo htmlspecialchars($currentYear['year_name']); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h6 class="mb-0">
                        <i class="fas fa-book me-2 text-primary"></i>
                        My Subjects
                    </h6>
                </div>
                <div class="card-body p-2">
                    <?php if ($subjects && $subjects->num_rows > 0): ?>
                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                            <a href="?subject_id=<?php echo urlencode($subject['subject_id']); ?>" 
                               class="subject-card d-flex justify-content-between align-items-center p-2 text-decoration-none <?php echo $selectedSubject == $subject['subject_id'] ? 'active' : ''; ?>">
                                <div>
                                    <small><strong><?php echo htmlspecialchars($subject['subject_id']); ?></strong></small>
                                    <div class="small <?php echo $selectedSubject == $subject['subject_id'] ? 'text-white-50' : 'text-muted'; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right fa-xs <?php echo $selectedSubject == $subject['subject_id'] ? 'text-white' : 'text-muted'; ?>"></i>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-book-open fa-2x mb-2 d-block"></i>
                            <small>No subjects assigned</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <?php if ($selectedSubject && !empty($performanceData)): ?>
                <!-- Statistics Row - Compact -->
                <div class="row g-2 mb-3">
                    <div class="col-3">
                        <div class="stat-card">
                            <div class="stat-number text-primary"><?php echo number_format($stats['avg'], 1); ?>%</div>
                            <div class="stat-label">Average</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-card">
                            <div class="stat-number text-success"><?php echo number_format($stats['max'], 1); ?>%</div>
                            <div class="stat-label">Highest</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-card">
                            <div class="stat-number text-info"><?php echo number_format($stats['min'], 1); ?>%</div>
                            <div class="stat-label">Lowest</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-card">
                            <div class="stat-number text-warning"><?php echo $stats['pass_rate']; ?>%</div>
                            <div class="stat-label">Pass Rate</div>
                        </div>
                    </div>
                </div>
                
                <!-- Pass Rate Progress - Compact -->
                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-body py-2">
                        <div class="row align-items-center">
                            <div class="col-4">
                                <div class="text-center">
                                    <small class="text-muted">Total Students</small>
                                    <h4 class="mb-0"><?php echo $stats['total']; ?></h4>
                                </div>
                            </div>
                            <div class="col-8">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span><i class="fas fa-check-circle text-success"></i> Passed: <?php echo $stats['pass']; ?></span>
                                    <span><i class="fas fa-times-circle text-danger"></i> Failed: <?php echo $stats['fail']; ?></span>
                                </div>
                                <div class="progress progress-premium">
                                    <div class="progress-bar bg-success" style="width: <?php echo $stats['pass_rate']; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Chart - Smaller -->
                <?php if (!empty($chartLabels)): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h6 class="mb-0 small">
                            <i class="fas fa-chart-bar me-2 text-primary"></i>
                            Top 10 Students - <?php echo htmlspecialchars($subjectName); ?>
                        </h6>
                    </div>
                    <div class="card-body py-2">
                        <div class="chart-container">
                            <canvas id="performanceChart" style="max-height: 180px; width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Performance Table - Compact -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h6 class="mb-0 small">
                            <i class="fas fa-table me-2 text-primary"></i>
                            Student Performance Details
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-premium mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Student</th>
                                        <th style="width: 70px;">Grade</th>
                                        <th style="width: 90px;">Earned</th>
                                        <th style="width: 90px;">Score</th>
                                        <th style="width: 60px;">Grade</th>
                                        <th style="width: 70px;">Status</th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    usort($performanceData, function($a, $b) {
                                        return $b['avg_score'] <=> $a['avg_score'];
                                    });
                                    $rank = 1;
                                    foreach ($performanceData as $student): 
                                        $score = $student['avg_score'];
                                        $letter = getPerformanceLetterGrade($score);
                                        $gradeClass = 
                                            $letter == 'A+' || $letter == 'A' ? 'grade-A' :
                                            ($letter == 'A-' || $letter == 'B+' || $letter == 'B' ? 'grade-B' :
                                            ($letter == 'B-' || $letter == 'C+' || $letter == 'C' ? 'grade-C' :
                                            ($letter == 'C-' || $letter == 'D' ? 'grade-D' : 'grade-F')));
                                        $statusClass = $score >= 50 ? 'status-pass' : 'status-fail';
                                        $statusText = $score >= 50 ? 'Pass' : 'Fail';
                                        
                                        $rankClass = '';
                                        if ($rank == 1) $rankClass = 'rank-1';
                                        elseif ($rank == 2) $rankClass = 'rank-2';
                                        elseif ($rank == 3) $rankClass = 'rank-3';
                                        else $rankClass = 'bg-secondary text-white';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="rank-badge <?php echo $rankClass; ?>">
                                                    <?php echo $rank; ?>
                                                </div>
                                             </small>
                                            <td>
                                                <strong><?php echo htmlspecialchars(explode(' ', $student['full_name'])[0]); ?></strong>
                                                <div class="small text-muted"><?php echo htmlspecialchars($student['student_id']); ?></div>
                                             </small>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $student['grade_level']; ?>-<?php echo $student['section']; ?></span>
                                             </small>
                                            <td class="text-center">
                                                <small><?php echo $student['total_earned']; ?> / <?php echo $student['total_possible']; ?></small>
                                             </small>
                                            <td class="text-center" style="min-width: 100px;">
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="fw-bold small"><?php echo number_format($score, 1); ?>%</span>
                                                    <div class="progress progress-premium flex-grow-1">
                                                        <div class="progress-bar bg-<?php echo $score >= 60 ? 'success' : ($score >= 45 ? 'warning' : 'danger'); ?>" 
                                                             style="width: <?php echo $score; ?>%"></div>
                                                    </div>
                                                </div>
                                             </small>
                                            <td class="text-center">
                                                <span class="grade-badge-premium <?php echo $gradeClass; ?>"><?php echo $letter; ?></span>
                                             </small>
                                            <td class="text-center">
                                                <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                             </small>
                                            <td class="text-center">
                                                <a href="enter_grades.php?subject_id=<?php echo urlencode($selectedSubject); ?>" 
                                                   class="btn btn-sm btn-outline-primary rounded-circle" style="width: 26px; height: 26px; padding: 0; line-height: 24px;" title="Enter Grades">
                                                    <i class="fas fa-edit fa-xs"></i>
                                                </a>
                                            </small>
                                        </tr>
                                    <?php $rank++; endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($selectedSubject): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h6>No Performance Data</h6>
                    <p class="small text-muted">No performance data available for this subject yet.</p>
                    <a href="enter_grades.php?subject_id=<?php echo urlencode($selectedSubject); ?>" class="btn btn-primary btn-sm rounded-pill">
                        <i class="fas fa-edit me-1"></i> Start Entering Grades
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-hand-point-left"></i>
                    <h6>Select a Subject</h6>
                    <p class="small text-muted">Choose a subject from the left sidebar to view class performance.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if ($selectedSubject && !empty($chartLabels)): ?>
    const ctx = document.getElementById('performanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Score (%)',
                data: <?php echo json_encode($chartScores); ?>,
                backgroundColor: 'rgba(30, 60, 114, 0.7)',
                borderColor: '#1e3c72',
                borderWidth: 1,
                borderRadius: 4,
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
                    labels: { 
                        font: { size: 9 },
                        boxWidth: 10,
                        padding: 5
                    } 
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
                    title: { display: true, text: 'Score (%)', font: { size: 8 } },
                    ticks: { stepSize: 25, font: { size: 8 } },
                    grid: { color: '#e9ecef' }
                },
                x: { 
                    ticks: { font: { size: 8, maxRotation: 0 } },
                    grid: { display: false }
                }
            }
        }
    });
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>