<?php
// admin/view_grades.php - Completely fixed version
$pageTitle = 'View All Grades';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$message = '';
$error = '';

// Get filter parameters
$grade_filter = isset($_GET['grade']) ? trim($_GET['grade']) : '';
$section_filter = isset($_GET['section']) ? trim($_GET['section']) : '';
$subject_filter = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

// Get current academic year
$currentYear = ['year_id' => 1, 'year_name' => '2024/25'];
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
if ($yearResult && $yearResult->num_rows > 0) {
    $yearData = $yearResult->fetch_assoc();
    $currentYear = $yearData;
}
$yearId = $currentYear['year_id'];

// Build query - Simple query without prepared statements for reliability
$where = "WHERE m.academic_year_id = $yearId";

if ($grade_filter) {
    $where .= " AND s.current_grade_level = '$grade_filter'";
}

if ($section_filter) {
    $where .= " AND s.current_section = '$section_filter'";
}

if ($subject_filter) {
    $where .= " AND m.subject_id = $subject_filter";
}

// Get grades with student and subject info - Simple query
$sql = "SELECT m.mark_id, m.score, m.grade_date, m.assessment_type,
        s.student_id, s.student_code, s.current_grade_level, s.current_section,
        u.full_name as student_name,
        sub.subject_id, sub.subject_code, sub.subject_name,
        t.teacher_code,
        tu.full_name as teacher_name
        FROM mark m
        JOIN student s ON m.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        JOIN subject sub ON m.subject_id = sub.subject_id
        JOIN teacher t ON m.teacher_id = t.teacher_id
        JOIN users tu ON t.user_id = tu.user_id
        $where
        ORDER BY m.grade_date DESC, sub.subject_code, u.full_name
        LIMIT 500";

$grades = $conn->query($sql);

// Get filter options
$grades_list = $conn->query("SELECT DISTINCT current_grade_level FROM student WHERE current_grade_level IS NOT NULL ORDER BY current_grade_level");
$sections_list = $conn->query("SELECT DISTINCT current_section FROM student WHERE current_section IS NOT NULL ORDER BY current_section");
$subjects_list = $conn->query("SELECT subject_id, subject_code, subject_name FROM subject WHERE is_active = 1 ORDER BY subject_code");
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="fas fa-chart-line me-2"></i> View All Grades</h1>
        <div>
            <button class="btn btn-success" onclick="exportToCSV()">
                <i class="fas fa-file-excel me-2"></i> Export to Excel
            </button>
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print me-2"></i> Print
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Grade Level</label>
                    <select name="grade" class="form-select" onchange="this.form.submit()">
                        <option value="">All Grades</option>
                        <?php if ($grades_list && $grades_list->num_rows > 0): ?>
                            <?php while ($grade = $grades_list->fetch_assoc()): ?>
                                <option value="<?php echo $grade['current_grade_level']; ?>" <?php echo $grade_filter == $grade['current_grade_level'] ? 'selected' : ''; ?>>
                                    Grade <?php echo $grade['current_grade_level']; ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Section</label>
                    <select name="section" class="form-select" onchange="this.form.submit()">
                        <option value="">All Sections</option>
                        <?php if ($sections_list && $sections_list->num_rows > 0): ?>
                            <?php while ($section = $sections_list->fetch_assoc()): ?>
                                <option value="<?php echo $section['current_section']; ?>" <?php echo $section_filter == $section['current_section'] ? 'selected' : ''; ?>>
                                    Section <?php echo $section['current_section']; ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Subject</label>
                    <select name="subject" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Subjects</option>
                        <?php if ($subjects_list && $subjects_list->num_rows > 0): ?>
                            <?php while ($subject = $subjects_list->fetch_assoc()): ?>
                                <option value="<?php echo $subject['subject_id']; ?>" <?php echo $subject_filter == $subject['subject_id'] ? 'selected' : ''; ?>>
                                    <?php echo $subject['subject_code'] . ' - ' . $subject['subject_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="view_grades.php" class="btn btn-secondary w-100">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Grades Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="gradesTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Student Code</th>
                            <th>Student Name</th>
                            <th>Grade/Section</th>
                            <th>Subject</th>
                            <th>Assessment</th>
                            <th>Score</th>
                            <th>Letter Grade</th>
                            <th>Teacher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($grades && $grades->num_rows > 0): ?>
                            <?php while ($grade = $grades->fetch_assoc()): 
                                $letterGrade = getLetterGrade($grade['score']);
                                $badgeClass = $grade['score'] >= 60 ? 'success' : ($grade['score'] >= 45 ? 'warning' : 'danger');
                            ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($grade['grade_date'])); ?></small>
                                    <td><strong><?php echo htmlspecialchars($grade['student_code']); ?></strong></small>
                                    <td><?php echo htmlspecialchars($grade['student_name']); ?></small>
                                    <td>
                                        <span class="badge bg-primary">G<?php echo $grade['current_grade_level']; ?></span>
                                        <span class="badge bg-secondary">Sec <?php echo $grade['current_section']; ?></span>
                                    </small>
                                    <td>
                                        <?php echo htmlspecialchars($grade['subject_code']); ?>
                                        <br>
                                        <small><?php echo htmlspecialchars($grade['subject_name']); ?></small>
                                    </small>
                                    <td><?php echo htmlspecialchars($grade['assessment_type']); ?></small>
                                    <td class="text-center">
                                        <strong><?php echo number_format($grade['score'], 1); ?>%</strong>
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar bg-<?php echo $badgeClass; ?>" style="width: <?php echo $grade['score']; ?>%"></div>
                                        </div>
                                    </small>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $letterGrade['letter']; ?></span>
                                    </small>
                                    <td>
                                        <?php echo htmlspecialchars($grade['teacher_name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo $grade['teacher_code']; ?></small>
                                    </small>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-chart-line fa-3x text-muted mb-3 d-block"></i>
                                    No grades found for the selected filters
                                 </small>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Summary Statistics -->
    <?php if ($grades && $grades->num_rows > 0): 
        // Reset pointer and calculate statistics
        $grades->data_seek(0);
        $scores = [];
        while ($grade = $grades->fetch_assoc()) {
            $scores[] = $grade['score'];
        }
        $avgScore = count($scores) > 0 ? array_sum($scores) / count($scores) : 0;
        $maxScore = count($scores) > 0 ? max($scores) : 0;
        $minScore = count($scores) > 0 ? min($scores) : 0;
        $passCount = count(array_filter($scores, function($s) { return $s >= 50; }));
        $passRate = count($scores) > 0 ? ($passCount / count($scores)) * 100 : 0;
    ?>
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-chart-bar me-2"></i> Summary Statistics
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h4><?php echo number_format($avgScore, 1); ?>%</h4>
                                <small class="text-muted">Average Score</small>
                            </div>
                            <div class="col-md-3">
                                <h4><?php echo number_format($maxScore, 1); ?>%</h4>
                                <small class="text-muted">Highest Score</small>
                            </div>
                            <div class="col-md-3">
                                <h4><?php echo number_format($minScore, 1); ?>%</h4>
                                <small class="text-muted">Lowest Score</small>
                            </div>
                            <div class="col-md-3">
                                <h4><?php echo number_format($passRate, 1); ?>%</h4>
                                <small class="text-muted">Pass Rate (≥50%)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    $('#gradesTable').DataTable({
        pageLength: 50,
        responsive: true,
        order: [[0, 'desc']],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries"
        }
    });
});

function exportToCSV() {
    const table = document.getElementById('gradesTable');
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = Array.from(cols).map(col => col.innerText.replace(/,/g, ';'));
        csv.push(rowData.join(','));
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'grades_export.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}
</script>

<?php require_once '../includes/footer.php'; ?>