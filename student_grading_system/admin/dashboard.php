<?php
// admin/dashboard.php - Fixed with correct top performing subjects by grade
$pageTitle = 'Admin Dashboard';
require_once '../includes/header.php';

$conn = db();

// Check if connection exists
if (!$conn) {
    die('<div class="alert alert-danger">Database connection failed. Please check your configuration.</div>');
}

// Get current academic year with error handling
$currentYear = ['year_id' => 2, 'year_name' => '2025/26'];
$yearId = 2;

$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
if ($yearResult && $yearResult->num_rows > 0) {
    $yearData = $yearResult->fetch_assoc();
    $currentYear = $yearData;
    $yearId = $yearData['year_id'];
}

// Get activity filter from GET parameter
$activityFilter = isset($_GET['activity']) ? $_GET['activity'] : 'all';

// Recent Activities - Only LOGIN and LOGOUT (compact view)
if ($activityFilter == 'login') {
    $recentActivities = $conn->query("
        SELECT a.*, u.full_name, u.username 
        FROM audit_log a
        LEFT JOIN users u ON a.username = u.username
        WHERE a.action IN ('LOGIN', 'LOGIN_SUCCESS')
        ORDER BY a.created_at DESC 
        LIMIT 30
    ");
} elseif ($activityFilter == 'logout') {
    $recentActivities = $conn->query("
        SELECT a.*, u.full_name, u.username 
        FROM audit_log a
        LEFT JOIN users u ON a.username = u.username
        WHERE a.action = 'LOGOUT'
        ORDER BY a.created_at DESC 
        LIMIT 30
    ");
} else {
    $recentActivities = $conn->query("
        SELECT a.*, u.full_name, u.username 
        FROM audit_log a
        LEFT JOIN users u ON a.username = u.username
        WHERE a.action IN ('LOGIN', 'LOGIN_SUCCESS', 'LOGOUT')
        ORDER BY a.created_at DESC 
        LIMIT 30
    ");
}

// Monthly grade entries
$monthlyData = [];
for ($i = 1; $i <= 12; $i++) {
    $monthResult = $conn->query("
        SELECT COUNT(*) as count 
        FROM mark 
        WHERE MONTH(grade_date) = $i 
        AND YEAR(grade_date) = YEAR(CURDATE())
        AND academic_year_id = $yearId
    ");
    if ($monthResult && $monthResult->num_rows > 0) {
        $monthlyData[] = $monthResult->fetch_assoc()['count'];
    } else {
        $monthlyData[] = 0;
    }
}

// Total Students
$totalStudents = 0;
$studentResult = $conn->query("SELECT COUNT(*) as total FROM student WHERE student_status = 'active'");
if ($studentResult && $studentResult->num_rows > 0) {
    $totalStudents = $studentResult->fetch_assoc()['total'];
}

// Total Teachers
$totalTeachers = 0;
$teacherResult = $conn->query("SELECT COUNT(*) as total FROM teacher WHERE status = 'active'");
if ($teacherResult && $teacherResult->num_rows > 0) {
    $totalTeachers = $teacherResult->fetch_assoc()['total'];
}

// Total Subjects
$totalSubjects = 0;
$subjectResult = $conn->query("SELECT COUNT(*) as total FROM subject WHERE is_active = 1");
if ($subjectResult && $subjectResult->num_rows > 0) {
    $totalSubjects = $subjectResult->fetch_assoc()['total'];
}

// Number of Subjects that have grades
$subjectsWithGrades = 0;
$gradeResult = $conn->query("SELECT COUNT(DISTINCT subject_id) as total FROM mark WHERE academic_year_id = $yearId");
if ($gradeResult && $gradeResult->num_rows > 0) {
    $subjectsWithGrades = $gradeResult->fetch_assoc()['total'];
}

// Top performing subjects for Grade 9
$topSubjectsGrade9 = [];
$topSubjectsResult9 = $conn->query("
    SELECT 
        s.subject_id, 
        s.subject_name,
        ROUND(AVG((m.score / at.default_weight) * 100), 1) as avg_score
    FROM mark m
    JOIN subject s ON m.subject_id = s.subject_id
    LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
    JOIN student st ON m.student_id = st.student_id
    WHERE m.academic_year_id = $yearId 
    AND st.current_grade_level = '9'
    GROUP BY s.subject_id, s.subject_name
    ORDER BY avg_score DESC
    LIMIT 5
");
if ($topSubjectsResult9 && $topSubjectsResult9->num_rows > 0) {
    while ($row = $topSubjectsResult9->fetch_assoc()) {
        $topSubjectsGrade9[] = $row;
    }
}

// Top performing subjects for Grade 10
$topSubjectsGrade10 = [];
$topSubjectsResult10 = $conn->query("
    SELECT 
        s.subject_id, 
        s.subject_name,
        ROUND(AVG((m.score / at.default_weight) * 100), 1) as avg_score
    FROM mark m
    JOIN subject s ON m.subject_id = s.subject_id
    LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
    JOIN student st ON m.student_id = st.student_id
    WHERE m.academic_year_id = $yearId 
    AND st.current_grade_level = '10'
    GROUP BY s.subject_id, s.subject_name
    ORDER BY avg_score DESC
    LIMIT 5
");
if ($topSubjectsResult10 && $topSubjectsResult10->num_rows > 0) {
    while ($row = $topSubjectsResult10->fetch_assoc()) {
        $topSubjectsGrade10[] = $row;
    }
}

// Top performing subjects for Grade 11
$topSubjectsGrade11 = [];
$topSubjectsResult11 = $conn->query("
    SELECT 
        s.subject_id, 
        s.subject_name,
        ROUND(AVG((m.score / at.default_weight) * 100), 1) as avg_score
    FROM mark m
    JOIN subject s ON m.subject_id = s.subject_id
    LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
    JOIN student st ON m.student_id = st.student_id
    WHERE m.academic_year_id = $yearId 
    AND st.current_grade_level = '11'
    GROUP BY s.subject_id, s.subject_name
    ORDER BY avg_score DESC
    LIMIT 5
");
if ($topSubjectsResult11 && $topSubjectsResult11->num_rows > 0) {
    while ($row = $topSubjectsResult11->fetch_assoc()) {
        $topSubjectsGrade11[] = $row;
    }
}

// Top performing subjects for Grade 12
$topSubjectsGrade12 = [];
$topSubjectsResult12 = $conn->query("
    SELECT 
        s.subject_id, 
        s.subject_name,
        ROUND(AVG((m.score / at.default_weight) * 100), 1) as avg_score
    FROM mark m
    JOIN subject s ON m.subject_id = s.subject_id
    LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
    JOIN student st ON m.student_id = st.student_id
    WHERE m.academic_year_id = $yearId 
    AND st.current_grade_level = '12'
    GROUP BY s.subject_id, s.subject_name
    ORDER BY avg_score DESC
    LIMIT 5
");
if ($topSubjectsResult12 && $topSubjectsResult12->num_rows > 0) {
    while ($row = $topSubjectsResult12->fetch_assoc()) {
        $topSubjectsGrade12[] = $row;
    }
}

// Gender distribution
$maleCount = 0;
$femaleCount = 0;
$genderResult = $conn->query("SELECT sex, COUNT(*) as count FROM student GROUP BY sex");
if ($genderResult && $genderResult->num_rows > 0) {
    while ($row = $genderResult->fetch_assoc()) {
        if ($row['sex'] == 'M') {
            $maleCount = $row['count'];
        } elseif ($row['sex'] == 'F') {
            $femaleCount = $row['count'];
        }
    }
}

// Get pass rate for current year
$passRate = 0;
$passResult = $conn->query("
    SELECT 
        COUNT(CASE WHEN total_score >= 50 THEN 1 END) as passed,
        COUNT(*) as total
    FROM final_grade 
    WHERE academic_year_id = $yearId AND total_score IS NOT NULL
");
if ($passResult && $passResult->num_rows > 0) {
    $pr = $passResult->fetch_assoc();
    $passRate = ($pr['total'] > 0) ? round(($pr['passed'] / $pr['total']) * 100) : 0;
}
?>

<style>
.dashboard-stat-card {
    transition: transform 0.2s;
}
.dashboard-stat-card:hover {
    transform: translateY(-3px);
}
.stat-icon {
    font-size: 2rem;
    opacity: 0.5;
}
.pass-rate-progress {
    height: 6px;
    border-radius: 3px;
}
.chart-container {
    position: relative;
    min-height: 200px;
}
.chart-container canvas {
    max-height: 200px;
    width: 100%;
}
.gender-chart-container {
    position: relative;
    max-width: 180px;
    margin: 0 auto;
}
.stat-number {
    font-size: 20px;
    font-weight: bold;
}
.stat-label {
    font-size: 11px;
    text-transform: uppercase;
    color: #6c757d;
}
.top-subject-card {
    border-radius: 12px;
    overflow: hidden;
}
.top-subject-header {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
    padding: 8px 8px;
    font-size: 12px;
    font-weight: 600;
}
.activity-badge-login {
    background: #28a745;
    color: white;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 9px;
}
.activity-badge-logout {
    background: #dc3545;
    color: white;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 9px;
}
.activity-filter-btn {
    padding: 2px 10px;
    font-size: 11px;
    border-radius: 20px;
    background: #f8f9fa;
    color: #6c757d;
    border: 1px solid #dee2e6;
    transition: all 0.2s;
    text-decoration: none;
}
.activity-filter-btn:hover, .activity-filter-btn.active {
    background: #1e3c72;
    color: white;
    border-color: #1e3c72;
}
/* Compact table */
.table-compact td, .table-compact th {
    padding: 6px 8px !important;
    font-size: 11px !important;
}
.activity-scroll {
    max-height: 200px;
    overflow-y: auto;
}
</style>

<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <i class="fas fa-tachometer-alt me-2 text-primary"></i> Dashboard
            <small class="text-muted fs-6">Welcome, <?php echo htmlspecialchars($currentUser['full_name'] ?? 'Administrator'); ?></small>
        </h1>
        <div>
            <span class="badge bg-primary"><?php echo htmlspecialchars($currentYear['year_name']); ?></span>
            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-2 mb-3">
        <div class="col-md-3 col-6">
            <div class="card dashboard-stat-card shadow-sm border-0">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Total Students</div>
                            <div class="stat-number mb-0"><?php echo number_format($totalStudents); ?></div>
                        </div>
                        <div class="text-primary"><i class="fas fa-users fa-2x stat-icon"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="card dashboard-stat-card shadow-sm border-0">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Total Teachers</div>
                            <div class="stat-number mb-0"><?php echo number_format($totalTeachers); ?></div>
                        </div>
                        <div class="text-success"><i class="fas fa-chalkboard-user fa-2x stat-icon"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="card dashboard-stat-card shadow-sm border-0">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Active Subjects</div>
                            <div class="stat-number mb-0"><?php echo number_format($totalSubjects); ?></div>
                        </div>
                        <div class="text-info"><i class="fas fa-book fa-2x stat-icon"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="card dashboard-stat-card shadow-sm border-0">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Subjects with Grades</div>
                            <div class="stat-number mb-0"><?php echo number_format($subjectsWithGrades); ?></div>
                        </div>
                        <div class="text-warning"><i class="fas fa-chart-line fa-2x stat-icon"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pass Rate Banner -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: #fff;">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <i class="fas fa-chart-line opacity-75"></i>
                        </div>
                        <div class="col">
                            <small class="fw-semibold">Pass Rate — <?php echo htmlspecialchars($currentYear['year_name']); ?></small>
                            <div class="progress pass-rate-progress mt-1" style="background: rgba(255,255,255,0.2);">
                                <div class="progress-bar bg-warning" style="width: <?php echo $passRate; ?>%"></div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <span class="fw-bold"><?php echo $passRate; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-3">
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-2">
                    <i class="fas fa-chart-line me-1 text-primary"></i> <small>Grade Entry Trends (<?php echo date('Y'); ?>)</small>
                </div>
                <div class="card-body py-2">
                    <div class="chart-container">
                        <canvas id="gradeTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-2">
                    <i class="fas fa-chart-pie me-1 text-primary"></i> <small>Gender Distribution</small>
                </div>
                <div class="card-body py-2">
                    <div class="gender-chart-container">
                        <canvas id="genderChart"></canvas>
                    </div>
                    <div class="text-center mt-2">
                        <span class="badge bg-primary me-1"><i class="fas fa-male"></i> <?php echo $maleCount; ?></span>
                        <span class="badge bg-danger"><i class="fas fa-female"></i> <?php echo $femaleCount; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Subjects by Grade Level -->
    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm top-subject-card">
                <div class="top-subject-header">
                    <i class="fas fa-trophy me-1"></i> Gr 9
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($topSubjectsGrade9)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($topSubjectsGrade9 as $index => $subject): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">
                                    <div>
                                        <span class="badge bg-secondary me-1" style="font-size: 9px;"><?php echo $index + 1; ?></span>
                                        <small><strong><?php echo htmlspecialchars($subject['subject_id']); ?></strong></small>
                                    </div>
                                    <div>
                                        <span class="badge bg-success" style="font-size: 9px;"><?php echo round($subject['avg_score'], 1); ?>%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-2 text-muted">
                            <small>No data</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm top-subject-card">
                <div class="top-subject-header">
                    <i class="fas fa-trophy me-1"></i> Gr 10
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($topSubjectsGrade10)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($topSubjectsGrade10 as $index => $subject): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">
                                    <div>
                                        <span class="badge bg-secondary me-1" style="font-size: 9px;"><?php echo $index + 1; ?></span>
                                        <small><strong><?php echo htmlspecialchars($subject['subject_id']); ?></strong></small>
                                    </div>
                                    <div>
                                        <span class="badge bg-success" style="font-size: 9px;"><?php echo round($subject['avg_score'], 1); ?>%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-2 text-muted">
                            <small>No data</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm top-subject-card">
                <div class="top-subject-header">
                    <i class="fas fa-trophy me-1"></i> Gr 11
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($topSubjectsGrade11)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($topSubjectsGrade11 as $index => $subject): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">
                                    <div>
                                        <span class="badge bg-secondary me-1" style="font-size: 9px;"><?php echo $index + 1; ?></span>
                                        <small><strong><?php echo htmlspecialchars($subject['subject_id']); ?></strong></small>
                                    </div>
                                    <div>
                                        <span class="badge bg-success" style="font-size: 9px;"><?php echo round($subject['avg_score'], 1); ?>%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-2 text-muted">
                            <small>No data</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm top-subject-card">
                <div class="top-subject-header">
                    <i class="fas fa-trophy me-1"></i> Gr 12
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($topSubjectsGrade12)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($topSubjectsGrade12 as $index => $subject): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">
                                    <div>
                                        <span class="badge bg-secondary me-1" style="font-size: 9px;"><?php echo $index + 1; ?></span>
                                        <small><strong><?php echo htmlspecialchars($subject['subject_id']); ?></strong></small>
                                    </div>
                                    <div>
                                        <span class="badge bg-success" style="font-size: 9px;"><?php echo round($subject['avg_score'], 1); ?>%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-2 text-muted">
                            <small>No data</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities - Very Small Compact Table with Scroll -->
    <div class="row g-3 mb-3">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-1 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-history me-1 text-info" style="font-size: 12px;"></i>
                        <small class="fw-semibold">Login/Logout Activity</small>
                    </div>
                    <div class="d-flex gap-1">
                        <a href="?activity=all" class="activity-filter-btn <?php echo $activityFilter == 'all' ? 'active' : ''; ?>">All</a>
                        <a href="?activity=login" class="activity-filter-btn <?php echo $activityFilter == 'login' ? 'active' : ''; ?>">Login</a>
                        <a href="?activity=logout" class="activity-filter-btn <?php echo $activityFilter == 'logout' ? 'active' : ''; ?>">Logout</a>
                    </div>
                </div>
                <div class="card-body p-0 activity-scroll">
                    <table class="table table-compact table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 20%">Time</th>
                                <th style="width: 45%">User</th>
                                <th style="width: 35%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentActivities && $recentActivities->num_rows > 0): ?>
                                <?php while ($activity = $recentActivities->fetch_assoc()): 
                                    $actionClass = (strpos(strtoupper($activity['action'] ?? ''), 'LOGIN') !== false) ? 'activity-badge-login' : 'activity-badge-logout';
                                    $actionDisplay = (strpos(strtoupper($activity['action'] ?? ''), 'LOGIN') !== false) ? '🔓 Login' : '🔒 Logout';
                                ?>
                                    <tr>
                                        <td><small><?php echo date('H:i:s', strtotime($activity['created_at'])); ?></small></small>
                                        <td>
                                            <small><strong><?php echo htmlspecialchars($activity['full_name'] ?? $activity['username'] ?? 'System'); ?></strong></small>
                                            <br>
                                            <small class="text-muted" style="font-size: 9px;"><?php echo htmlspecialchars($activity['username'] ?? ''); ?></small>
                                        </small>
                                        <td>
                                            <span class="badge <?php echo $actionClass; ?>"><?php echo $actionDisplay; ?></span>
                                        </small>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-2 text-muted">
                                        <small>No login/logout activities</small>
                                    </small>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-1 px-3">
                    <i class="fas fa-bolt me-1 text-warning" style="font-size: 12px;"></i> <small>Quick Actions</small>
                </div>
                <div class="card-body py-1 px-2">
                    <div class="row g-1">
                        <div class="col-md-3 col-6">
                            <a href="manage_students.php" class="btn btn-outline-primary w-100 py-1 btn-sm" style="font-size: 11px;">
                                <i class="fas fa-user-graduate"></i> Students
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="manage_teachers.php" class="btn btn-outline-success w-100 py-1 btn-sm" style="font-size: 11px;">
                                <i class="fas fa-chalkboard-user"></i> Teachers
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="manage_subjects.php" class="btn btn-outline-info w-100 py-1 btn-sm" style="font-size: 11px;">
                                <i class="fas fa-book"></i> Subjects
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="reports.php" class="btn btn-outline-secondary w-100 py-1 btn-sm" style="font-size: 11px;">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Grade Trend Chart
    const ctx1 = document.getElementById('gradeTrendChart').getContext('2d');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Grades',
                data: <?php echo json_encode($monthlyData); ?>,
                borderColor: '#1e3c72',
                backgroundColor: 'rgba(30, 60, 114, 0.1)',
                tension: 0.3,
                fill: true,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: '#1e3c72',
                borderWidth: 1.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { 
                legend: { display: false },
                tooltip: { 
                    bodyFont: { size: 11 },
                    callbacks: {
                        label: function(context) {
                            return context.raw + ' grades';
                        }
                    }
                }
            },
            scales: { 
                y: { 
                    beginAtZero: true, 
                    ticks: { stepSize: 5, font: { size: 9 } },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: { 
                    ticks: { font: { size: 9 }, maxRotation: 45, minRotation: 45 }
                }
            }
        }
    });

    // Gender Distribution Chart
    const ctx2 = document.getElementById('genderChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['Male', 'Female'],
            datasets: [{
                data: [<?php echo $maleCount; ?>, <?php echo $femaleCount; ?>],
                backgroundColor: ['#1e3c72', '#dc3545'],
                borderWidth: 0,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '65%',
            plugins: { 
                legend: { display: false },
                tooltip: {
                    bodyFont: { size: 11 },
                    callbacks: {
                        label: function(context) {
                            const total = <?php echo $maleCount + $femaleCount; ?>;
                            const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                            return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?> do this don't change anything