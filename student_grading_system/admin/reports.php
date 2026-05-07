<?php
// admin/reports.php - Fixed with correct average score calculation
$pageTitle = 'Generate Reports';
require_once '../includes/header.php';

$conn = db();
$report_html = '';
$report_type = isset($_GET['type']) ? $_GET['type'] : '';

// Get current academic year
$currentYear = ['year_id' => 2, 'year_name' => '2025/26'];
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
if ($yearResult && $yearResult->num_rows > 0) {
    $yearData = $yearResult->fetch_assoc();
    $currentYear = $yearData;
}
$yearId = $currentYear['year_id'];

// Get statistics
$totalStudents = 0;
$studentResult = $conn->query("SELECT COUNT(*) as total FROM student WHERE student_status = 'active'");
if ($studentResult && $studentResult->num_rows > 0) {
    $totalStudents = $studentResult->fetch_assoc()['total'];
}

$totalTeachers = 0;
$teacherResult = $conn->query("SELECT COUNT(*) as total FROM teacher WHERE status = 'active'");
if ($teacherResult && $teacherResult->num_rows > 0) {
    $totalTeachers = $teacherResult->fetch_assoc()['total'];
}

$totalSubjects = 0;
$subjectResult = $conn->query("SELECT COUNT(*) as total FROM subject WHERE is_active = 1");
if ($subjectResult && $subjectResult->num_rows > 0) {
    $totalSubjects = $subjectResult->fetch_assoc()['total'];
}

$totalGrades = 0;
$gradeResult = $conn->query("SELECT COUNT(*) as total FROM mark WHERE academic_year_id = $yearId");
if ($gradeResult && $gradeResult->num_rows > 0) {
    $totalGrades = $gradeResult->fetch_assoc()['total'];
}

// Performance by grade - Calculate average = total_score / number_of_subjects
$performance_by_grade = [];
$perfResult = $conn->query("
    SELECT 
        s.current_grade_level, 
        COUNT(DISTINCT s.student_id) as student_count,
        ROUND(AVG(
            CASE 
                WHEN student_data.total_score > 0 AND student_data.subject_count > 0 
                THEN student_data.total_score / student_data.subject_count
                ELSE 0 
            END
        ), 1) as avg_score
    FROM student s
    LEFT JOIN (
        SELECT 
            fg.student_id,
            SUM(fg.total_score) as total_score,
            COUNT(fg.subject_id) as subject_count
        FROM final_grade fg
        WHERE fg.academic_year_id = $yearId
        GROUP BY fg.student_id
    ) as student_data ON s.student_id = student_data.student_id
    WHERE s.student_status = 'active'
    GROUP BY s.current_grade_level
    ORDER BY s.current_grade_level
");
if ($perfResult && $perfResult->num_rows > 0) {
    while ($row = $perfResult->fetch_assoc()) {
        $performance_by_grade[] = $row;
    }
}

// Top students - Calculate average = total_score / number_of_subjects
$top_students = [];
$topResult = $conn->query("
    SELECT 
        s.student_id, 
        u.full_name, 
        s.current_grade_level, 
        s.current_section,
        ROUND(SUM(fg.total_score) / COUNT(fg.subject_id), 1) as avg_score
    FROM student s
    JOIN users u ON s.username = u.username
    LEFT JOIN final_grade fg ON s.student_id = fg.student_id AND fg.academic_year_id = $yearId
    WHERE s.student_status = 'active'
    GROUP BY s.student_id
    HAVING avg_score IS NOT NULL AND avg_score > 0
    ORDER BY avg_score DESC
    LIMIT 10
");
if ($topResult && $topResult->num_rows > 0) {
    while ($row = $topResult->fetch_assoc()) {
        $top_students[] = $row;
    }
}

// If no final_grade data, calculate from marks
if (empty($top_students)) {
    $topResult = $conn->query("
        SELECT 
            s.student_id, 
            u.full_name, 
            s.current_grade_level, 
            s.current_section,
            ROUND(SUM((m.score / at.default_weight) * 100) / COUNT(DISTINCT m.subject_id), 1) as avg_score
        FROM student s
        JOIN users u ON s.username = u.username
        LEFT JOIN mark m ON s.student_id = m.student_id AND m.academic_year_id = $yearId
        LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
        WHERE s.student_status = 'active'
        GROUP BY s.student_id
        HAVING avg_score IS NOT NULL AND avg_score > 0
        ORDER BY avg_score DESC
        LIMIT 10
    ");
    if ($topResult && $topResult->num_rows > 0) {
        while ($row = $topResult->fetch_assoc()) {
            $top_students[] = $row;
        }
    }
}

// All students list
$all_students = [];
$studentListResult = $conn->query("
    SELECT s.student_id, u.full_name, s.current_grade_level, s.current_section, 
           s.field_of_study, s.enrollment_date, u.email, u.phone
    FROM student s
    JOIN users u ON s.username = u.username
    WHERE s.student_status = 'active'
    ORDER BY s.current_grade_level, s.current_section, u.full_name
");
if ($studentListResult && $studentListResult->num_rows > 0) {
    while ($row = $studentListResult->fetch_assoc()) {
        $all_students[] = $row;
    }
}

// Grade distribution - from final_grade table (already calculated percentages)
$grade_distribution = [
    'A+' => 0, 'A' => 0, 'A-' => 0,
    'B+' => 0, 'B' => 0, 'B-' => 0,
    'C+' => 0, 'C' => 0, 'C-' => 0,
    'D' => 0, 'F' => 0
];

$finalResult = $conn->query("
    SELECT total_score 
    FROM final_grade 
    WHERE academic_year_id = $yearId AND total_score IS NOT NULL
");
if ($finalResult && $finalResult->num_rows > 0) {
    while ($fg = $finalResult->fetch_assoc()) {
        $score = $fg['total_score'];
        if ($score >= 90) $grade_distribution['A+']++;
        elseif ($score >= 85) $grade_distribution['A']++;
        elseif ($score >= 80) $grade_distribution['A-']++;
        elseif ($score >= 75) $grade_distribution['B+']++;
        elseif ($score >= 70) $grade_distribution['B']++;
        elseif ($score >= 65) $grade_distribution['B-']++;
        elseif ($score >= 60) $grade_distribution['C+']++;
        elseif ($score >= 55) $grade_distribution['C']++;
        elseif ($score >= 50) $grade_distribution['C-']++;
        elseif ($score >= 45) $grade_distribution['D']++;
        else $grade_distribution['F']++;
    }
} else {
    // Fallback: Calculate proper percentage from marks
    $marksData = [];
    $marksResult = $conn->query("
        SELECT 
            m.student_id, 
            m.subject_id,
            SUM(m.score) as total_earned,
            SUM(at.default_weight) as total_possible
        FROM mark m
        LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
        WHERE m.academic_year_id = $yearId
        GROUP BY m.student_id, m.subject_id
    ");
    
    while ($row = $marksResult->fetch_assoc()) {
        $percentage = ($row['total_possible'] > 0) ? ($row['total_earned'] / $row['total_possible']) * 100 : 0;
        $marksData[] = $percentage;
    }
    
    foreach ($marksData as $score) {
        if ($score >= 90) $grade_distribution['A+']++;
        elseif ($score >= 85) $grade_distribution['A']++;
        elseif ($score >= 80) $grade_distribution['A-']++;
        elseif ($score >= 75) $grade_distribution['B+']++;
        elseif ($score >= 70) $grade_distribution['B']++;
        elseif ($score >= 65) $grade_distribution['B-']++;
        elseif ($score >= 60) $grade_distribution['C+']++;
        elseif ($score >= 55) $grade_distribution['C']++;
        elseif ($score >= 50) $grade_distribution['C-']++;
        elseif ($score >= 45) $grade_distribution['D']++;
        else $grade_distribution['F']++;
    }
}

$total_marks = array_sum($grade_distribution);
?>

<style>
:root {
    --primary: #1e3c72;
    --primary-light: #2a5298;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
    --info: #17a2b8;
}

.report-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--primary);
}

.report-title {
    color: var(--primary);
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 5px;
}

.report-subtitle {
    color: #6c757d;
    font-size: 14px;
}

.stat-card {
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    color: white;
}

.stat-card-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
.stat-card-success { background: linear-gradient(135deg, #28a745, #34ce57); }
.stat-card-info { background: linear-gradient(135deg, #17a2b8, #3dd5f3); }
.stat-card-warning { background: linear-gradient(135deg, #ffc107, #ffce3a); color: #212529; }

.stat-number {
    font-size: 32px;
    font-weight: 700;
    margin: 0;
}

.stat-label {
    font-size: 12px;
    opacity: 0.9;
    margin-top: 5px;
}

.nav-btn {
    border-radius: 12px;
    padding: 15px;
    transition: all 0.3s ease;
}

.nav-btn:hover {
    transform: translateY(-3px);
}

.nav-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-color: transparent;
}

.table-professional {
    font-size: 13px;
}

.table-professional th {
    background: var(--primary);
    color: white;
    font-weight: 600;
    padding: 12px;
}

.table-professional td {
    padding: 10px 12px;
    vertical-align: middle;
}

.progress-custom {
    height: 8px;
    border-radius: 10px;
}

@media print {
    .no-print, .sidebar, .navbar, .footer, .btn, .report-nav {
        display: none !important;
    }
    .card {
        break-inside: avoid;
        border: 1px solid #ddd;
        box-shadow: none;
    }
    .main-content {
        padding: 0;
        margin: 0;
    }
    .container-fluid {
        padding: 0;
    }
    .table-professional th {
        background: var(--primary) !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-chart-line me-2" style="color: var(--primary);"></i> Reports Dashboard
            </h1>
            <p class="text-muted mt-1 mb-0">Generate and view academic reports</p>
        </div>
        <div class="no-print">
            <button class="btn btn-success" onclick="window.print()">
                <i class="fas fa-print me-2"></i> Print Report
            </button>
        </div>
    </div>
    
    <!-- Report Navigation -->
    <div class="row g-3 mb-4 report-nav">
        <div class="col-md-3">
            <a href="?type=summary" class="btn btn-outline-primary nav-btn w-100 <?php echo ($report_type == 'summary' || !$report_type) ? 'active' : ''; ?>">
                <i class="fas fa-chart-simple fa-2x d-block mb-2"></i>
                <strong>Summary Report</strong>
                <small class="d-block">Overall statistics</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="?type=performance" class="btn btn-outline-success nav-btn w-100 <?php echo $report_type == 'performance' ? 'active' : ''; ?>">
                <i class="fas fa-trophy fa-2x d-block mb-2"></i>
                <strong>Top Performers</strong>
                <small class="d-block">Best students</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="?type=students" class="btn btn-outline-info nav-btn w-100 <?php echo $report_type == 'students' ? 'active' : ''; ?>">
                <i class="fas fa-users fa-2x d-block mb-2"></i>
                <strong>Student Directory</strong>
                <small class="d-block">Complete list</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="?type=grade_distribution" class="btn btn-outline-warning nav-btn w-100 <?php echo $report_type == 'grade_distribution' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie fa-2x d-block mb-2"></i>
                <strong>Grade Analysis</strong>
                <small class="d-block">Distribution chart</small>
            </a>
        </div>
    </div>
    
    <!-- Report Content -->
    <?php if ($report_type == 'summary' || !$report_type): ?>
        <!-- Summary Report -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="report-header">
                    <div class="report-title">Gada Secondary School</div>
                    <div class="report-subtitle">Academic Summary Report - <?php echo htmlspecialchars($currentYear['year_name']); ?></div>
                    <div class="report-subtitle">Generated: <?php echo date('F d, Y H:i:s'); ?></div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card stat-card-primary">
                            <div class="stat-number"><?php echo number_format($totalStudents); ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stat-card-success">
                            <div class="stat-number"><?php echo number_format($totalTeachers); ?></div>
                            <div class="stat-label">Total Teachers</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stat-card-info">
                            <div class="stat-number"><?php echo number_format($totalSubjects); ?></div>
                            <div class="stat-label">Active Subjects</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stat-card-warning">
                            <div class="stat-number"><?php echo number_format($totalGrades); ?></div>
                            <div class="stat-label">Grades Recorded</div>
                        </div>
                    </div>
                </div>
                
                <!-- Performance by Grade -->
                <h5 class="mb-3"><i class="fas fa-chart-line me-2 text-primary"></i> Performance by Grade Level</h5>
                <div class="table-responsive">
                    <table class="table table-professional table-bordered">
                        <thead>
                            <tr>
                                <th>Grade Level</th>
                                <th class="text-center">Students</th>
                                <th class="text-center">Average Score (%)</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($performance_by_grade)): ?>
                                <?php foreach ($performance_by_grade as $grade): ?>
                                    <tr>
                                        <td><strong>Grade <?php echo htmlspecialchars($grade['current_grade_level']); ?></strong></td>
                                        <td class="text-center"><?php echo number_format($grade['student_count']); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?php echo number_format($grade['avg_score'] ?? 0, 1); ?>%</span>
                                        </td>
                                        <td style="width: 40%;">
                                            <div class="progress progress-custom">
                                                <div class="progress-bar bg-success" style="width: <?php echo $grade['avg_score'] ?? 0; ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No performance data available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <small>This report is system-generated and includes all active students and recorded grades for the <?php echo htmlspecialchars($currentYear['year_name']); ?> academic year.</small>
                </div>
            </div>
        </div>
        
    <?php elseif ($report_type == 'performance'): ?>
        <!-- Top Performers Report -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-trophy me-2"></i> Top Performing Students
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-professional table-hover">
                        <thead>
                            <tr>
                                <th class="text-center">Rank</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Grade</th>
                                <th>Section</th>
                                <th class="text-center">Average Score (%)</th>
                                <th class="text-center">Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($top_students)): ?>
                                <?php $rank = 1; foreach ($top_students as $student): 
                                    $letterGrade = getLetterGradeLetter($student['avg_score']);
                                    $badgeClass = $student['avg_score'] >= 70 ? 'success' : ($student['avg_score'] >= 50 ? 'warning' : 'danger');
                                ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php if ($rank <= 3): ?>
                                                <i class="fas fa-medal text-warning fa-lg"></i>
                                            <?php else: ?>
                                                <strong>#<?php echo $rank; ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($student['student_id']); ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                        <td>Grade <?php echo $student['current_grade_level']; ?></td>
                                        <td><?php echo $student['current_section']; ?></td>
                                        <td class="text-center" style="min-width: 150px;">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="fw-bold"><?php echo number_format($student['avg_score'], 1); ?>%</span>
                                                <div class="progress flex-grow-1 progress-custom">
                                                    <div class="progress-bar bg-<?php echo $badgeClass; ?>" style="width: <?php echo $student['avg_score']; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $badgeClass; ?> fs-6"><?php echo $letterGrade; ?></span>
                                        </td>
                                    </tr>
                                <?php $rank++; endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-chart-line fa-3x mb-2 d-block"></i>
                                        No performance data available
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    <?php elseif ($report_type == 'students'): ?>
        <!-- Student Directory Report -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-users me-2"></i> Student Directory
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-professional table-bordered" id="studentTable">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Grade</th>
                                <th>Section</th>
                                <th>Field of Study</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Enrollment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($all_students)): ?>
                                <?php foreach ($all_students as $student): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($student['student_id']); ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                        <td class="text-center"><?php echo $student['current_grade_level']; ?></td>
                                        <td class="text-center"><?php echo $student['current_section']; ?></td>
                                        <td><?php echo htmlspecialchars($student['field_of_study'] ?: 'General'); ?></td>
                                        <td><small><?php echo htmlspecialchars($student['email']); ?></small></td>
                                        <td><?php echo htmlspecialchars($student['phone'] ?: '-'); ?></td>
                                        <td><small><?php echo $student['enrollment_date'] ? date('M d, Y', strtotime($student['enrollment_date'])) : '-'; ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="fas fa-users fa-3x mb-2 d-block"></i>
                                        No students found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="8"><strong>Total Students: <?php echo count($all_students); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
    <?php elseif ($report_type == 'grade_distribution'): ?>
        <!-- Grade Distribution Report -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-chart-pie me-2"></i> Grade Distribution Analysis
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <canvas id="gradeDistributionChart" height="350"></canvas>
                    </div>
                    <div class="col-lg-6">
                        <div class="table-responsive">
                            <table class="table table-professional">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Grade</th>
                                        <th>Score Range</th>
                                        <th class="text-center">Count</th>
                                        <th class="text-center">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $gradeRanges = [
                                        'A+' => '90-100%', 'A' => '85-89%', 'A-' => '80-84%',
                                        'B+' => '75-79%', 'B' => '70-74%', 'B-' => '65-69%',
                                        'C+' => '60-64%', 'C' => '55-59%', 'C-' => '50-54%',
                                        'D' => '45-49%', 'F' => '0-44%'
                                    ];
                                    foreach ($grade_distribution as $grade => $count): 
                                        $percentage = $total_marks > 0 ? ($count / $total_marks) * 100 : 0;
                                        $badgeClass = ($grade == 'A+' || $grade == 'A') ? 'success' : (($grade == 'F') ? 'danger' : 'info');
                                    ?>
                                        <tr>
                                            <td><strong><?php echo $grade; ?></strong></td>
                                            <td><?php echo $gradeRanges[$grade] ?? '-'; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary"><?php echo number_format($count); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="progress progress-custom">
                                                    <div class="progress-bar bg-<?php echo $badgeClass; ?>" style="width: <?php echo $percentage; ?>%">
                                                        <?php echo number_format($percentage, 1); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr class="fw-bold">
                                        <td colspan="2"><strong>Total</strong></td>
                                        <td class="text-center"><strong><?php echo number_format($total_marks); ?></strong></td>
                                        <td class="text-center"><strong>100%</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        new Chart(document.getElementById('gradeDistributionChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($grade_distribution)); ?>,
                datasets: [{
                    label: 'Number of Grades',
                    data: <?php echo json_encode(array_values($grade_distribution)); ?>,
                    backgroundColor: ['#28a745', '#28a745', '#28a745', '#17a2b8', '#17a2b8', '#17a2b8', '#ffc107', '#ffc107', '#ffc107', '#fd7e14', '#dc3545'],
                    borderWidth: 0,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { callbacks: { label: function(context) { return 'Grades: ' + context.raw; } } }
                },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Number of Grades' }, ticks: { stepSize: 1 } },
                    x: { title: { display: true, text: 'Letter Grade' } }
                }
            }
        });
        </script>
    <?php endif; ?>
</div>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#studentTable').DataTable({
        pageLength: 25,
        responsive: true,
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ students",
            paginate: { previous: "<", next: ">" }
        }
    });
});
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<?php require_once '../includes/footer.php'; ?>