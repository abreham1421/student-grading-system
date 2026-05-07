<?php
// teacher/dashboard.php - Fixed with horizontal recent grades (total per subject)
$pageTitle = 'Teacher Dashboard';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$username = $_SESSION['username'] ?? '';

// Get teacher ID from username
$teacherId = '';
if (!empty($username)) {
    $result = $conn->query("SELECT teacher_id FROM teacher WHERE username = '$username'");
    if ($result && $result->num_rows > 0) {
        $teacherId = $result->fetch_assoc()['teacher_id'];
        $_SESSION['profile_id'] = $teacherId;
    }
}

// Fallback to session if exists
if (empty($teacherId) && isset($_SESSION['profile_id'])) {
    $teacherId = $_SESSION['profile_id'];
}

if (empty($teacherId)) {
    echo '<div class="alert alert-danger">Teacher profile not found. Please contact administrator.</div>';
    require_once '../includes/footer.php';
    exit();
}

// Helper function for letter grade
function getTeacherLetterGrade($percentage) {
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
$currentYear = ['year_id' => 2, 'year_name' => '2025/26'];
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
if ($yearResult && $yearResult->num_rows > 0) {
    $yearData = $yearResult->fetch_assoc();
    $currentYear = $yearData;
}
$yearId = $currentYear['year_id'];

// Get teacher information
$teacherInfo = [];
$teacherQuery = $conn->query("
    SELECT t.*, u.full_name, u.email, u.phone 
    FROM teacher t 
    JOIN users u ON t.username = u.username 
    WHERE t.teacher_id = '$teacherId'
");
if ($teacherQuery && $teacherQuery->num_rows > 0) {
    $teacherInfo = $teacherQuery->fetch_assoc();
}

// Get teacher's subjects
$subjects = $conn->query("
    SELECT s.*, ts.academic_year_id 
    FROM teacher_subject ts
    JOIN subject s ON ts.subject_id = s.subject_id
    WHERE ts.teacher_id = '$teacherId' AND ts.academic_year_id = $yearId
");
$subjectsCount = ($subjects) ? $subjects->num_rows : 0;

// Get total students taught
$studentCount = 0;
$subjectIds = [];
if ($subjects && $subjects->num_rows > 0) {
    while ($subject = $subjects->fetch_assoc()) {
        $subjectIds[] = "'" . $conn->real_escape_string($subject['subject_id']) . "'";
    }
    $subjects->data_seek(0);
    
    if (!empty($subjectIds)) {
        $ids = implode(',', $subjectIds);
        $studentResult = $conn->query("
            SELECT COUNT(DISTINCT student_id) as total 
            FROM student_subject 
            WHERE subject_id IN ($ids) AND is_active = 1
        ");
        if ($studentResult && $studentResult->num_rows > 0) {
            $studentCount = $studentResult->fetch_assoc()['total'];
        }
    }
}

// Get grades entered count - Count number of subjects that have grades
$subjectsWithGrades = 0;
$gradeResult = $conn->query("
    SELECT COUNT(DISTINCT subject_id) as total 
    FROM mark 
    WHERE teacher_id = '$teacherId' AND academic_year_id = $yearId
");
if ($gradeResult && $gradeResult->num_rows > 0) {
    $subjectsWithGrades = $gradeResult->fetch_assoc()['total'];
}

// Get subject performance averages for chart - Calculate proper percentage
$subjectPerformance = [];
if ($subjects) {
    $subjects->data_seek(0);
    while ($subject = $subjects->fetch_assoc()) {
        $avgResult = $conn->query("
            SELECT ROUND(AVG((m.score / at.default_weight) * 100), 1) as avg_score
            FROM mark m
            LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
            WHERE m.subject_id = '{$subject['subject_id']}' 
            AND m.academic_year_id = $yearId
            AND m.teacher_id = '$teacherId'
        ");
        $avg = ($avgResult && $avgResult->num_rows > 0) ? $avgResult->fetch_assoc()['avg_score'] : 0;
        $subjectPerformance[] = [
            'id' => $subject['subject_id'],
            'name' => $subject['subject_name'],
            'average' => round($avg, 1)
        ];
    }
    $subjects->data_seek(0);
}

// Calculate average performance
$avgPerf = 0;
if (!empty($subjectPerformance)) {
    $total = 0;
    $count = 0;
    foreach ($subjectPerformance as $sp) {
        if ($sp['average'] > 0) {
            $total += $sp['average'];
            $count++;
        }
    }
    $avgPerf = $count > 0 ? round($total / $count, 1) : 0;
}

// Get assessment types for horizontal header
$assessmentTypes = $conn->query("SELECT assessment_id, assessment_name, default_weight FROM assessment_type ORDER BY assessment_id");
$assessmentList = [];
if ($assessmentTypes && $assessmentTypes->num_rows > 0) {
    while ($type = $assessmentTypes->fetch_assoc()) {
        $assessmentList[] = $type;
    }
}

// Get recent grades - Grouped by student and subject (total per subject)
$recentEntries = $conn->query("
    SELECT 
        MAX(m.grade_date) as grade_date,
        s.student_id,
        u.full_name as student_name,
        sub.subject_id,
        sub.subject_name,
        m.teacher_id
    FROM mark m
    JOIN student s ON m.student_id = s.student_id
    JOIN users u ON s.username = u.username
    JOIN subject sub ON m.subject_id = sub.subject_id
    WHERE m.teacher_id = '$teacherId' AND m.academic_year_id = $yearId
    GROUP BY s.student_id, sub.subject_id
    ORDER BY grade_date DESC
    LIMIT 10
");

// Get all marks for these entries to calculate totals per assessment
$marksData = [];
if ($recentEntries && $recentEntries->num_rows > 0) {
    $studentSubjects = [];
    while ($row = $recentEntries->fetch_assoc()) {
        $key = $row['student_id'] . '_' . $row['subject_id'];
        $studentSubjects[$key] = [
            'student_id' => $row['student_id'],
            'student_name' => $row['student_name'],
            'subject_id' => $row['subject_id'],
            'subject_name' => $row['subject_name'],
            'grade_date' => $row['grade_date']
        ];
    }
    
    // Fetch all marks for these student-subject combinations
    if (!empty($studentSubjects)) {
        $conditions = [];
        foreach ($studentSubjects as $key => $data) {
            $conditions[] = "(m.student_id = '{$data['student_id']}' AND m.subject_id = '{$data['subject_id']}')";
        }
        $whereClause = implode(' OR ', $conditions);
        
        $allMarks = $conn->query("
            SELECT 
                m.student_id,
                m.subject_id,
                m.assessment_type_id,
                m.score,
                at.default_weight as max_points
            FROM mark m
            LEFT JOIN assessment_type at ON m.assessment_type_id = at.assessment_id
            WHERE ($whereClause) AND m.academic_year_id = $yearId
        ");
        
        if ($allMarks && $allMarks->num_rows > 0) {
            while ($mark = $allMarks->fetch_assoc()) {
                $key = $mark['student_id'] . '_' . $mark['subject_id'];
                if (!isset($marksData[$key])) {
                    $marksData[$key] = [];
                }
                $marksData[$key][$mark['assessment_type_id']] = [
                    'score' => $mark['score'],
                    'max_points' => $mark['max_points'] ?? 100
                ];
            }
        }
        
        $recentEntries->data_seek(0);
    }
}
?>

<style>
.dashboard-stat-card {
    transition: transform 0.2s;
    cursor: pointer;
}
.dashboard-stat-card:hover {
    transform: translateY(-3px);
}
.subject-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.subject-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.stat-number {
    font-size: 28px;
    font-weight: bold;
}

/* Chart Container - Smaller */
.chart-container {
    position: relative;
    min-height: 200px;
}
.chart-container canvas {
    max-height: 200px;
    width: 100%;
}

/* Horizontal Grade Table */
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
    letter-spacing: 0.5px;
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
.sticky-col {
    position: sticky;
    background: white;
    z-index: 10;
}
.sticky-col-left-1 {
    left: 0;
    box-shadow: 2px 0 5px -2px rgba(0,0,0,0.05);
}
.sticky-col-left-2 {
    left: 100px;
    box-shadow: 2px 0 5px -2px rgba(0,0,0,0.05);
}
.grade-badge-sm {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}
.total-badge {
    background: linear-gradient(135deg, #17a2b8, #3dd5f3);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 11px;
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4">
            <i class="fas fa-chalkboard-user me-2"></i> 
            Welcome, <?php echo htmlspecialchars($teacherInfo['full_name'] ?? 'Teacher'); ?>!
        </h1>
        <div>
            <span class="badge bg-primary"><?php echo htmlspecialchars($currentYear['year_name']); ?></span>
            <span class="badge bg-info ms-2"><?php echo htmlspecialchars($teacherInfo['department'] ?? 'General'); ?></span>
            <span class="badge bg-secondary ms-2">ID: <?php echo htmlspecialchars($teacherId); ?></span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card dashboard-stat-card shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase">My Subjects</div>
                            <div class="stat-number mb-0"><?php echo $subjectsCount; ?></div>
                        </div>
                        <div class="text-primary"><i class="fas fa-book fa-3x opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-stat-card shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase">Total Students</div>
                            <div class="stat-number mb-0"><?php echo $studentCount; ?></div>
                        </div>
                        <div class="text-success"><i class="fas fa-users fa-3x opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-stat-card shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase">Subjects with Grades</div>
                            <div class="stat-number mb-0"><?php echo number_format($subjectsWithGrades); ?></div>
                        </div>
                        <div class="text-info"><i class="fas fa-chart-line fa-3x opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-stat-card shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase">Avg Performance</div>
                            <div class="stat-number mb-0"><?php echo $avgPerf; ?>%</div>
                        </div>
                        <div class="text-warning"><i class="fas fa-star fa-3x opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Performance Chart - Smaller -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white py-2">
                    <i class="fas fa-chart-bar me-2"></i> Subject Performance Overview
                </div>
                <div class="card-body">
                    <?php if (!empty($subjectPerformance)): ?>
                        <div class="chart-container">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-chart-bar fa-3x mb-2"></i>
                            <p class="small">No performance data available yet.<br>Start entering grades to see statistics.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white py-2">
                    <i class="fas fa-user-circle me-2"></i> Teacher Information
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary d-inline-flex p-3 me-3">
                            <i class="fas fa-chalkboard-user fa-2x text-white"></i>
                        </div>
                        <div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($teacherInfo['full_name'] ?? 'N/A'); ?></h5>
                            <small class="text-muted"><?php echo htmlspecialchars($teacherId); ?></small>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="row small">
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Qualification</small>
                            <strong><?php echo htmlspecialchars($teacherInfo['qualification'] ?: 'Not Specified'); ?></strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Specialization</small>
                            <strong><?php echo htmlspecialchars($teacherInfo['specialization'] ?: 'General'); ?></strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Department</small>
                            <strong><?php echo htmlspecialchars($teacherInfo['department'] ?: 'General'); ?></strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Experience</small>
                            <strong><?php echo $teacherInfo['experience_years'] ?? 0; ?> years</strong>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block">Email</small>
                            <strong><small><?php echo htmlspecialchars($teacherInfo['email'] ?? 'N/A'); ?></small></strong>
                        </div>
                        <?php if (!empty($teacherInfo['phone'])): ?>
                        <div class="col-12 mt-2">
                            <small class="text-muted d-block">Phone</small>
                            <strong><small><?php echo htmlspecialchars($teacherInfo['phone']); ?></small></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- My Subjects List -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white py-2">
                    <i class="fas fa-list me-2"></i> My Subjects (<?php echo htmlspecialchars($currentYear['year_name']); ?>)
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if ($subjects && $subjects->num_rows > 0): ?>
                            <?php while ($subject = $subjects->fetch_assoc()): 
                                $studentCountSubj = 0;
                                $countResult = $conn->query("
                                    SELECT COUNT(DISTINCT student_id) as total 
                                    FROM student_subject 
                                    WHERE subject_id = '{$subject['subject_id']}' 
                                    AND academic_year_id = $yearId 
                                    AND is_active = 1
                                ");
                                if ($countResult && $countResult->num_rows > 0) {
                                    $studentCountSubj = $countResult->fetch_assoc()['total'];
                                }
                            ?>
                                <div class="col-md-4">
                                    <div class="card subject-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title text-primary mb-0">
                                                    <?php echo htmlspecialchars($subject['subject_id']); ?>
                                                </h5>
                                                <span class="badge bg-secondary"><?php echo $subject['credits']; ?> cr</span>
                                            </div>
                                            <h6 class="card-subtitle mb-2 text-muted">
                                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                                            </h6>
                                            <p class="card-text small">
                                                <i class="fas fa-users me-1 text-muted"></i> 
                                                <strong><?php echo $studentCountSubj; ?></strong> students
                                                <br>
                                                <i class="fas fa-tag me-1 text-muted"></i> 
                                                <span class="badge bg-<?php echo $subject['subject_type'] == 'core' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($subject['subject_type'] ?? 'Core'); ?>
                                                </span>
                                            </p>
                                            <div class="d-flex gap-2 mt-3">
                                                <a href="enter_grades.php?subject_id=<?php echo urlencode($subject['subject_id']); ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit me-1"></i> Enter Grades
                                                </a>
                                                <a href="my_students.php?subject_id=<?php echo urlencode($subject['subject_id']); ?>" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="fas fa-users me-1"></i> Students
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info text-center">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No subjects assigned for <?php echo htmlspecialchars($currentYear['year_name']); ?>.
                                    Please contact the administrator.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Grades Entered - Horizontal Table (Total per Subject) -->
    <?php if ($recentEntries && $recentEntries->num_rows > 0): ?>
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white py-2">
                    <i class="fas fa-history me-2"></i> Recently Entered Grades
                    <span class="float-end">Last <?php echo $recentEntries->num_rows; ?> entries</span>
                </div>
                <div class="card-body p-0">
                    <div style="overflow-x: auto;">
                        <table class="horizontal-grade-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 100px; position: sticky; left: 0; background: linear-gradient(135deg, #1e3c72, #2a5298); z-index: 20;">Date</th>
                                    <th style="min-width: 150px; position: sticky; left: 100px; background: linear-gradient(135deg, #1e3c72, #2a5298); z-index: 20;">Student Name</th>
                                    <th style="min-width: 100px; position: sticky; left: 250px; background: linear-gradient(135deg, #1e3c72, #2a5298); z-index: 20;">Subject</th>
                                    <?php foreach ($assessmentList as $assessment): ?>
                                        <th class="text-center" style="min-width: 90px;">
                                            <?php echo htmlspecialchars($assessment['assessment_name']); ?>
                                            <div class="small opacity-75">(max <?php echo $assessment['default_weight']; ?>)</div>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="text-center" style="min-width: 80px;">Total</th>
                                    <th class="text-center" style="min-width: 70px;">Grade</th>
                                <tr>
                            </thead>
                            <tbody>
                                <?php 
                                $displayCount = 0;
                                $recentEntries->data_seek(0);
                                while ($entry = $recentEntries->fetch_assoc()): 
                                    if ($displayCount >= 10) break;
                                    $key = $entry['student_id'] . '_' . $entry['subject_id'];
                                    $marks = isset($marksData[$key]) ? $marksData[$key] : [];
                                    
                                    $totalEarned = 0;
                                    $totalPossible = 0;
                                    foreach ($assessmentList as $assessment) {
                                        $assessId = $assessment['assessment_id'];
                                        if (isset($marks[$assessId])) {
                                            $score = $marks[$assessId]['score'];
                                            $maxPoints = $marks[$assessId]['max_points'];
                                            $totalEarned += $score;
                                            $totalPossible += $maxPoints;
                                        }
                                    }
                                    $percentage = $totalPossible > 0 ? ($totalEarned / $totalPossible) * 100 : 0;
                                    $letterGrade = getTeacherLetterGrade($percentage);
                                    $badgeClass = $percentage >= 60 ? 'success' : ($percentage >= 45 ? 'warning' : 'danger');
                                ?>
                                    <tr>
                                        <td class="sticky-col sticky-col-left-1" style="background: white;">
                                            <small><?php echo date('M d', strtotime($entry['grade_date'])); ?></small>
                                         </small>
                                        <td class="sticky-col sticky-col-left-2" style="left: 100px; background: white;">
                                            <strong><?php echo htmlspecialchars(explode(' ', $entry['student_name'])[0]); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($entry['student_id']); ?></small>
                                         </small>
                                        <td class="sticky-col sticky-col-left-2" style="left: 250px; background: white;">
                                            <strong><?php echo htmlspecialchars($entry['subject_id']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($entry['subject_name']); ?></small>
                                         </small>
                                        
                                        <?php foreach ($assessmentList as $assessment): 
                                            $assessId = $assessment['assessment_id'];
                                            $score = isset($marks[$assessId]) ? $marks[$assessId]['score'] : '';
                                            $maxPoints = $assessment['default_weight'];
                                        ?>
                                            <td class="text-center">
                                                <?php if ($score !== '' && $score > 0): ?>
                                                    <span class="fw-bold"><?php echo number_format($score, 1); ?></span>
                                                    <br><small class="text-muted">/<?php echo $maxPoints; ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                             </small>
                                        <?php endforeach; ?>
                                        
                                        <td class="text-center bg-light">
                                            <span class="total-badge"><?php echo number_format($totalEarned, 1); ?></span>
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
                                endwhile; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white py-2">
                    <i class="fas fa-bolt me-2"></i> Quick Actions
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="enter_grades.php" class="btn btn-outline-primary">
                            <i class="fas fa-edit me-1"></i> Enter Grades
                        </a>
                        <a href="my_students.php" class="btn btn-outline-info">
                            <i class="fas fa-users me-1"></i> View All Students
                        </a>
                        <a href="profile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-user me-1"></i> My Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if (!empty($subjectPerformance)): ?>
<script>
    const ctx = document.getElementById('performanceChart').getContext('2d');
    const subjectNames = [];
    const subjectAverages = [];
    
    <?php foreach ($subjectPerformance as $subject): ?>
        subjectNames.push('<?php echo addslashes($subject['id']); ?>');
        subjectAverages.push(<?php echo $subject['average']; ?>);
    <?php endforeach; ?>
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: subjectNames,
            datasets: [{
                label: 'Class Average (%)',
                data: subjectAverages,
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
                legend: { position: 'top', labels: { font: { size: 10 } } },
                tooltip: { 
                    callbacks: {
                        label: function(context) {
                            return 'Average Score: ' + context.raw + '%';
                        }
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    max: 100,
                    title: { display: true, text: 'Score (%)', font: { size: 9 } },
                    ticks: { stepSize: 25, font: { size: 9 } }
                },
                x: { 
                    ticks: { font: { size: 9 } }
                }
            }
        }
    });
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>