<?php
// admin/enroll_student.php - Professional student enrollment management
$pageTitle = 'Student Enrollment';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$message = '';
$error = '';

// Get student ID from URL
$student_id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : '';

if (empty($student_id)) {
    header('Location: manage_students.php');
    exit();
}

// Get student information
$studentInfo = $conn->query("
    SELECT s.*, u.full_name, u.email, u.phone 
    FROM student s 
    JOIN users u ON s.username = u.username 
    WHERE s.student_id = '$student_id'
");

if (!$studentInfo || $studentInfo->num_rows == 0) {
    $error = "Student not found!";
} else {
    $student = $studentInfo->fetch_assoc();
}

// Get current academic year
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
if ($yearResult && $yearResult->num_rows > 0) {
    $currentYear = $yearResult->fetch_assoc();
} else {
    $currentYear = ['year_id' => 2, 'year_name' => '2025/26'];
}
$yearId = $currentYear['year_id'];

// Get current semester
$semesterResult = $conn->query("SELECT semester_id, semester_number, semester_name FROM semester WHERE year_id = $yearId AND is_current = 1 LIMIT 1");
$currentSemester = ($semesterResult && $semesterResult->num_rows > 0) ? $semesterResult->fetch_assoc() : null;
$semesterId = $currentSemester ? $currentSemester['semester_id'] : null;

// Get all subjects
$allSubjects = $conn->query("
    SELECT subject_id, subject_name, credits, grade_level, subject_type 
    FROM subject 
    WHERE is_active = 1 
    ORDER BY subject_id
");

// Get currently enrolled subjects for this student
$enrolledSubjects = [];
$enrolledResult = $conn->query("
    SELECT subject_id FROM student_subject 
    WHERE student_id = '$student_id' AND academic_year_id = $yearId AND is_active = 1
");
if ($enrolledResult && $enrolledResult->num_rows > 0) {
    while ($row = $enrolledResult->fetch_assoc()) {
        $enrolledSubjects[] = $row['subject_id'];
    }
}

// Handle enrollment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_enrollment'])) {
    $selected_subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    
    $conn->begin_transaction();
    
    try {
        // First, mark all current enrollments as inactive
        $conn->query("UPDATE student_subject SET is_active = 0 WHERE student_id = '$student_id' AND academic_year_id = $yearId");
        
        // Insert new enrollments
        $enrolledCount = 0;
        foreach ($selected_subjects as $subject_id) {
            $subject_id = $conn->real_escape_string($subject_id);
            $check = $conn->query("SELECT enrollment_id FROM student_subject 
                                   WHERE student_id = '$student_id' AND subject_id = '$subject_id' AND academic_year_id = $yearId");
            
            if ($check && $check->num_rows > 0) {
                // Update existing to active
                $semesterValue = $semesterId ? $semesterId : 'NULL';
                $conn->query("UPDATE student_subject SET is_active = 1, enrollment_date = CURDATE(), semester_id = $semesterValue 
                              WHERE student_id = '$student_id' AND subject_id = '$subject_id' AND academic_year_id = $yearId");
                $enrolledCount++;
            } else {
                // Insert new enrollment
                $semesterValue = $semesterId ? $semesterId : 'NULL';
                $conn->query("INSERT INTO student_subject (student_id, subject_id, academic_year_id, semester_id, enrollment_date, is_active) 
                              VALUES ('$student_id', '$subject_id', $yearId, $semesterValue, CURDATE(), 1)");
                $enrolledCount++;
            }
        }
        
        $conn->commit();
        $message = "Student enrollment updated successfully! $enrolledCount subject(s) enrolled.";
        
        // Refresh enrolled subjects list
        $enrolledSubjects = $selected_subjects;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error updating enrollment: " . $e->getMessage();
    }
}

// Calculate total credits
$totalCredits = 0;
if (!empty($enrolledSubjects)) {
    $ids = "'" . implode("','", array_map([$conn, 'real_escape_string'], $enrolledSubjects)) . "'";
    $creditQuery = $conn->query("SELECT SUM(credits) as total FROM subject WHERE subject_id IN ($ids)");
    if ($creditQuery && $creditQuery->num_rows > 0) {
        $totalCredits = $creditQuery->fetch_assoc()['total'] ?? 0;
    }
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

.enrollment-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
}

.student-avatar {
    width: 70px;
    height: 70px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary);
}

.stat-label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
}

.subject-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.subject-row {
    transition: all 0.3s ease;
}

.subject-row:hover {
    background: var(--gray-100);
    cursor: pointer;
}

.table-professional {
    border-radius: 12px;
    overflow: hidden;
}

.table-professional thead th {
    background: var(--gray-100);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    padding: 12px;
    border-bottom: 2px solid var(--gray-200);
}

.table-professional tbody td {
    padding: 12px;
    vertical-align: middle;
}

.badge-core { background: linear-gradient(135deg, var(--success), #34ce57); }
.badge-elective { background: linear-gradient(135deg, var(--warning), #ffce3a); color: #212529; }
.badge-optional { background: linear-gradient(135deg, var(--info), #3dd5f3); }

.enrolled-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.enrolled-active { background: #d4edda; color: #155724; }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-book-open me-2" style="color: var(--primary);"></i> Subject Enrollment
            </h1>
            <p class="text-muted mt-1 mb-0">Manage student subject registrations</p>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_students.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
            <a href="view_student.php?id=<?php echo urlencode($student_id); ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-2"></i> View Student
            </a>
        </div>
    </div>
    
    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($student)): ?>
        <!-- Student Info Header -->
        <div class="enrollment-header">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="student-avatar">
                        <i class="fas fa-user-graduate fa-3x text-white"></i>
                    </div>
                </div>
                <div class="col">
                    <h4 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h4>
                    <div class="d-flex flex-wrap gap-3">
                        <span><i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($student['student_id']); ?></span>
                        <span><i class="fas fa-graduation-cap me-1"></i> Grade <?php echo $student['current_grade_level']; ?> - Section <?php echo $student['current_section']; ?></span>
                        <span><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($student['email']); ?></span>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="text-end">
                        <div class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-calendar-alt me-1"></i> <?php echo htmlspecialchars($currentYear['year_name']); ?>
                        </div>
                        <?php if ($currentSemester): ?>
                            <div class="badge bg-info mt-2 px-3 py-2">
                                <i class="fas fa-clock me-1"></i> <?php echo htmlspecialchars($currentSemester['semester_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($enrolledSubjects); ?></div>
                    <div class="stat-label">Enrolled Subjects</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalCredits; ?></div>
                    <div class="stat-label">Total Credits</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $allSubjects ? $allSubjects->num_rows : 0; ?></div>
                    <div class="stat-label">Available Subjects</div>
                </div>
            </div>
        </div>
        
        <!-- Subject Enrollment Form -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="mb-0"><i class="fas fa-list-check me-2 text-primary"></i> Subject Selection</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="table-responsive">
                        <table class="table table-professional" id="subjectsTable">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="selectAll" onclick="toggleAll(this)">
                                    </th>
                                    <th>Subject ID</th>
                                    <th>Subject Name</th>
                                    <th class="text-center">Credits</th>
                                    <th>Level</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($allSubjects && $allSubjects->num_rows > 0): ?>
                                    <?php while ($subject = $allSubjects->fetch_assoc()): 
                                        $checked = in_array($subject['subject_id'], $enrolledSubjects) ? 'checked' : '';
                                        $typeClass = $subject['subject_type'] == 'core' ? 'badge-core' : ($subject['subject_type'] == 'elective' ? 'badge-elective' : 'badge-optional');
                                    ?>
                                        <tr class="subject-row" onclick="toggleRowCheckbox(this, event)">
                                            <td class="text-center" onclick="event.stopPropagation()">
                                                <input type="checkbox" name="subjects[]" value="<?php echo htmlspecialchars($subject['subject_id']); ?>" 
                                                       class="subject-checkbox" <?php echo $checked; ?> onchange="updateSelectedCount()">
                                            </td>
                                            <td><code><?php echo htmlspecialchars($subject['subject_id']); ?></code></td>
                                            <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                                            <td class="text-center"><span class="badge bg-secondary"><?php echo $subject['credits']; ?> cr</span></td>
                                            <td><?php echo htmlspecialchars($subject['grade_level'] ?? '9-12'); ?></td>
                                            <td><span class="badge <?php echo $typeClass; ?>"><?php echo ucfirst($subject['subject_type']); ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fas fa-book-open fa-3x mb-2 d-block"></i>
                                            No subjects available
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="2">
                                        <strong>Selected: <span id="selectedCount"><?php echo count($enrolledSubjects); ?></span></strong>
                                        <span class="text-muted ms-2" id="selectedCredits"></span>
                                    </td>
                                    <td colspan="4" class="text-end">
                                        <button type="submit" name="update_enrollment" class="btn btn-primary px-4">
                                            <i class="fas fa-save me-2"></i> Save Enrollment
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Currently Enrolled Subjects -->
        <?php if (!empty($enrolledSubjects)): ?>
        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="mb-0"><i class="fas fa-check-circle me-2 text-success"></i> Currently Enrolled Subjects</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-professional">
                        <thead>
                            <tr>
                                <th>Subject ID</th>
                                <th>Subject Name</th>
                                <th class="text-center">Credits</th>
                                <th>Type</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($enrolledSubjects)) {
                                $ids = "'" . implode("','", array_map([$conn, 'real_escape_string'], $enrolledSubjects)) . "'";
                                $enrolledList = $conn->query("
                                    SELECT subject_id, subject_name, credits, subject_type 
                                    FROM subject 
                                    WHERE subject_id IN ($ids)
                                    ORDER BY subject_id
                                ");
                                if ($enrolledList && $enrolledList->num_rows > 0) {
                                    while ($sub = $enrolledList->fetch_assoc()) {
                                        $typeClass = $sub['subject_type'] == 'core' ? 'badge-core' : ($sub['subject_type'] == 'elective' ? 'badge-elective' : 'badge-optional');
                                        echo "<tr>
                                                <td><code>" . htmlspecialchars($sub['subject_id']) . "</code></td>
                                                <td><strong>" . htmlspecialchars($sub['subject_name']) . "</strong></td>
                                                <td class='text-center'><span class='badge bg-secondary'>{$sub['credits']} cr</span></td>
                                                <td><span class='badge $typeClass'>" . ucfirst($sub['subject_type']) . "</span></td>
                                                <td class='text-center'><span class='enrolled-badge enrolled-active'><i class='fas fa-check-circle me-1'></i> Active</span></td>
                                              </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='text-center py-4 text-muted'>No subjects enrolled</td></tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center py-4 text-muted'>No subjects enrolled</td></tr>";
                            }
                            ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5"><strong>Total Credits: <?php echo $totalCredits; ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Instructions -->
        <div class="alert alert-info mt-4 small">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Instructions:</strong>
            <ul class="mb-0 mt-2">
                <li>Check the boxes next to subjects to enroll the student</li>
                <li>Uncheck subjects to remove the student from those courses</li>
                <li>Click on any row to toggle the checkbox</li>
                <li>Changes will be recorded for <strong><?php echo htmlspecialchars($currentYear['year_name']); ?></strong> academic year</li>
                <?php if ($currentSemester): ?>
                    <li>Current semester: <strong><?php echo htmlspecialchars($currentSemester['semester_name']); ?></strong></li>
                <?php endif; ?>
            </ul>
        </div>
        
    <?php else: ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i> Student not found!
        </div>
    <?php endif; ?>
</div>

<script>
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.subject-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = source.checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.subject-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').innerText = count;
    
    // Calculate total credits
    let totalCredits = 0;
    checkboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const creditsCell = row.cells[3];
        const creditsText = creditsCell ? creditsCell.innerText : '0';
        const credits = parseInt(creditsText) || 0;
        totalCredits += credits;
    });
    
    const creditsSpan = document.getElementById('selectedCredits');
    if (creditsSpan) {
        creditsSpan.textContent = '| Total Credits: ' + totalCredits;
    }
}

function toggleRowCheckbox(row, event) {
    // Don't toggle if clicking on the checkbox itself
    if (event.target.type !== 'checkbox') {
        const checkbox = row.querySelector('.subject-checkbox');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            checkbox.dispatchEvent(new Event('change'));
            updateSelectedCount();
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
    
    // Add change event listeners to all checkboxes
    document.querySelectorAll('.subject-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>