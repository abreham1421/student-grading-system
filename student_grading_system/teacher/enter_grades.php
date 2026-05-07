<?php
// teacher/enter_grades.php - Professional Grade Entry System
$pageTitle = 'Enter Grades';
require_once '../includes/header.php'; // This already loads functions.php

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
    echo '<div class="alert alert-danger">Teacher profile not found. Please contact administrator.</div>';
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

// Get current semester
$currentSemester = null;
$semesterResult = $conn->query("SELECT semester_id, semester_number, semester_name FROM semester WHERE year_id = $yearId AND is_current = 1 LIMIT 1");
if ($semesterResult && $semesterResult->num_rows > 0) {
    $currentSemester = $semesterResult->fetch_assoc();
}
$semesterId = $currentSemester ? $currentSemester['semester_id'] : null;

// Get teacher's subjects
$subjects = $conn->query("
    SELECT s.*, ts.academic_year_id 
    FROM teacher_subject ts
    JOIN subject s ON ts.subject_id = s.subject_id
    WHERE ts.teacher_id = '$teacherId' AND ts.academic_year_id = $yearId
");

$selectedSubject = isset($_GET['subject_id']) ? $conn->real_escape_string($_GET['subject_id']) : null;
$selectedSubjectInfo = null;
$students = null;
$assessmentTypes = null;
$message = '';
$error = '';
$validationErrors = [];

// Assessment Management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assessment'])) {
    $assessment_name = trim($_POST['assessment_name']);
    $assessment_weight = (float)$_POST['assessment_weight'];
    
    $currentTotal = $conn->query("SELECT SUM(default_weight) as total FROM assessment_type")->fetch_assoc()['total'] ?? 0;
    if ($currentTotal + $assessment_weight > 100) {
        $error = "Total weight would exceed 100%! Current total: " . $currentTotal . "%";
    } else {
        $check = $conn->query("SELECT assessment_id FROM assessment_type WHERE assessment_name = '$assessment_name'");
        if ($check && $check->num_rows == 0) {
            $conn->query("INSERT INTO assessment_type (assessment_name, default_weight) VALUES ('$assessment_name', $assessment_weight)");
            $message = "Assessment type added successfully!";
        } else {
            $error = "Assessment type already exists!";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_assessment'])) {
    $assessment_id = (int)$_POST['assessment_id'];
    $new_weight = (float)$_POST['edit_weight'];
    $new_name = trim($_POST['edit_name']);
    
    $currentTotal = $conn->query("SELECT SUM(default_weight) as total FROM assessment_type WHERE assessment_id != $assessment_id")->fetch_assoc()['total'] ?? 0;
    if ($currentTotal + $new_weight > 100) {
        $error = "Total weight would exceed 100%! Current total without this assessment: " . $currentTotal . "%";
    } else {
        $conn->query("UPDATE assessment_type SET assessment_name = '$new_name', default_weight = $new_weight WHERE assessment_id = $assessment_id");
        $message = "Assessment updated successfully!";
    }
}

if (isset($_GET['delete_assessment'])) {
    $assessment_id = (int)$_GET['delete_assessment'];
    $check = $conn->query("SELECT COUNT(*) as count FROM mark WHERE assessment_type_id = $assessment_id");
    if ($check && $check->fetch_assoc()['count'] == 0) {
        $conn->query("DELETE FROM assessment_type WHERE assessment_id = $assessment_id");
        $message = "Assessment type deleted successfully!";
    } else {
        $error = "Cannot delete assessment type because it has grades associated!";
    }
}

$assessmentTypes = $conn->query("SELECT assessment_id, assessment_name, default_weight FROM assessment_type ORDER BY assessment_id");

if ($selectedSubject) {
    $subjectInfoResult = $conn->query("SELECT * FROM subject WHERE subject_id = '$selectedSubject'");
    if ($subjectInfoResult && $subjectInfoResult->num_rows > 0) {
        $selectedSubjectInfo = $subjectInfoResult->fetch_assoc();
    }
    
    $students = $conn->query("
        SELECT s.student_id, u.full_name, s.current_grade_level, s.current_section
        FROM student s
        JOIN users u ON s.username = u.username
        JOIN student_subject ss ON s.student_id = ss.student_id
        WHERE ss.subject_id = '$selectedSubject' 
        AND ss.academic_year_id = $yearId
        AND ss.is_active = 1
        AND s.student_status = 'active'
        ORDER BY u.full_name
    ");
}

// Handle single grade edit via modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_single_grade'])) {
    $mark_id = (int)$_POST['mark_id'];
    $new_score = (float)$_POST['new_score'];
    $max_points = (float)$_POST['max_points'];
    
    if ($new_score < 0) {
        $error = "Score cannot be negative.";
    } elseif ($new_score > $max_points) {
        $error = "Score cannot exceed maximum points ($max_points).";
    } else {
        $updateSql = "UPDATE mark SET score = $new_score, grade_date = CURDATE() WHERE mark_id = $mark_id";
        if ($conn->query($updateSql)) {
            $message = "Grade updated successfully!";
        } else {
            $error = "Error updating grade.";
        }
    }
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $subject_id = $conn->real_escape_string($_POST['subject_id']);
    $grades = $_POST['grades'] ?? [];
    $hasError = false;
    
    foreach ($grades as $student_id => $student_grades) {
        foreach ($student_grades as $assessment_id => $score) {
            if ($score !== '' && is_numeric($score)) {
                $score = (float)$score;
                $maxResult = $conn->query("SELECT default_weight FROM assessment_type WHERE assessment_id = $assessment_id");
                if ($maxResult && $maxResult->num_rows > 0) {
                    $maxValue = $maxResult->fetch_assoc()['default_weight'];
                    if ($score < 0) {
                        $validationErrors["{$student_id}_{$assessment_id}"] = "Value cannot be negative";
                        $hasError = true;
                    } elseif ($score > $maxValue) {
                        $validationErrors["{$student_id}_{$assessment_id}"] = "Maximum is $maxValue points";
                        $hasError = true;
                    }
                }
            }
        }
    }
    
    if (!$hasError) {
        foreach ($grades as $student_id => $student_grades) {
            foreach ($student_grades as $assessment_id => $score) {
                if ($score !== '' && is_numeric($score)) {
                    $score = (float)$score;
                    $assessment_type_id = (int)$assessment_id;
                    $student_id_escaped = $conn->real_escape_string($student_id);
                    $subject_id_escaped = $conn->real_escape_string($subject_id);
                    
                    $checkSql = "SELECT mark_id FROM mark WHERE student_id = '$student_id_escaped' 
                                 AND subject_id = '$subject_id_escaped' 
                                 AND assessment_type_id = $assessment_type_id
                                 AND academic_year_id = $yearId";
                    $existing = $conn->query($checkSql);
                    
                    if ($existing && $existing->num_rows > 0) {
                        $row = $existing->fetch_assoc();
                        $conn->query("UPDATE mark SET score = $score, grade_date = CURDATE() WHERE mark_id = {$row['mark_id']}");
                    } else {
                        $semesterIdValue = $semesterId ? $semesterId : 'NULL';
                        $conn->query("INSERT INTO mark (student_id, subject_id, teacher_id, assessment_type_id, 
                                      score, grade_date, academic_year_id, semester_id, entered_by) 
                                      VALUES ('$student_id_escaped', '$subject_id_escaped', '$teacherId', $assessment_type_id, 
                                      $score, CURDATE(), $yearId, $semesterIdValue, '{$_SESSION['username']}')");
                    }
                }
            }
            
            $total_score = 0;
            $weights = $conn->query("SELECT assessment_id, default_weight FROM assessment_type");
            while ($weight = $weights->fetch_assoc()) {
                $assess_id = $weight['assessment_id'];
                $grade_value = isset($grades[$student_id][$assess_id]) ? (float)$grades[$student_id][$assess_id] : 0;
                $total_score += $grade_value;
            }
            
            if ($total_score > 0) {
                $letter_grade = getLetterGradeLetter($total_score);
                $grade_point = getGradePoint($total_score);
                $student_id_escaped = $conn->real_escape_string($student_id);
                $subject_id_escaped = $conn->real_escape_string($subject_id);
                
                $checkFinal = $conn->query("SELECT final_grade_id FROM final_grade 
                                            WHERE student_id = '$student_id_escaped' 
                                            AND subject_id = '$subject_id_escaped' 
                                            AND academic_year_id = $yearId");
                if ($checkFinal && $checkFinal->num_rows > 0) {
                    $conn->query("UPDATE final_grade SET total_score = $total_score, letter_grade = '$letter_grade', 
                                  grade_point = $grade_point, calculated_date = CURDATE() 
                                  WHERE student_id = '$student_id_escaped' AND subject_id = '$subject_id_escaped' AND academic_year_id = $yearId");
                } else {
                    $semesterIdValue = $semesterId ? $semesterId : 'NULL';
                    $conn->query("INSERT INTO final_grade (student_id, subject_id, academic_year_id, semester_id, total_score, 
                                  letter_grade, grade_point, calculated_date) 
                                  VALUES ('$student_id_escaped', '$subject_id_escaped', $yearId, $semesterIdValue, $total_score, 
                                  '$letter_grade', $grade_point, CURDATE())");
                }
            }
        }
        $message = "Grades saved successfully!";
    } else {
        $error = "Please fix the validation errors below.";
    }
}

// Get existing grades with mark_id
$existingGrades = [];
$gradeIds = [];
if ($selectedSubject) {
    $gradeResult = $conn->query("
        SELECT mark_id, student_id, assessment_type_id, score 
        FROM mark 
        WHERE subject_id = '$selectedSubject' 
        AND academic_year_id = $yearId
    ");
    if ($gradeResult && $gradeResult->num_rows > 0) {
        while ($row = $gradeResult->fetch_assoc()) {
            $existingGrades[$row['student_id']][$row['assessment_type_id']] = $row['score'];
            $gradeIds[$row['student_id']][$row['assessment_type_id']] = $row['mark_id'];
        }
    }
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

/* Premium Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.grade-table-container {
    animation: fadeIn 0.4s ease-out;
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

/* Assessment Items */
.assessment-item {
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.assessment-item:hover {
    background: var(--gray-50);
    border-left-color: var(--primary);
}

/* Premium Table */
.grade-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
}

.grade-table thead th {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 14px 12px;
    border: none;
    position: sticky;
    top: 0;
    z-index: 20;
}

.grade-table tbody td {
    padding: 12px;
    border-bottom: 1px solid var(--gray-200);
    vertical-align: middle;
    background: white;
}

.grade-table tbody tr {
    transition: all 0.2s ease;
}

.grade-table tbody tr:hover {
    background: var(--gray-50);
}

.grade-table tbody tr:hover td {
    background: var(--gray-50);
}

/* Sticky Columns */
.sticky-col {
    position: sticky;
    z-index: 10;
}

.sticky-col-left {
    left: 0;
    box-shadow: 2px 0 5px -2px rgba(0,0,0,0.05);
}

.sticky-col-left-2 {
    left: 180px;
    box-shadow: 2px 0 5px -2px rgba(0,0,0,0.05);
}

/* Grade Input */
.grade-input {
    width: 85px;
    text-align: center;
    border-radius: 10px;
    border: 1px solid var(--gray-200);
    padding: 8px 5px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.grade-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(30,60,114,0.1);
    outline: none;
}

.grade-input.is-invalid {
    border-color: var(--danger);
    background: #fff0f0;
}

/* Edit Button */
.edit-grade-btn {
    background: transparent;
    border: 1px solid var(--warning);
    color: #856404;
    border-radius: 20px;
    padding: 3px 12px;
    font-size: 10px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.edit-grade-btn:hover {
    background: var(--warning);
    color: #212529;
    transform: scale(1.02);
}

/* Total Badge */
.total-badge {
    background: linear-gradient(135deg, var(--info), #3dd5f3);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 13px;
    display: inline-block;
}

/* Grade Badge */
.grade-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 13px;
    display: inline-block;
}

.grade-a { background: linear-gradient(135deg, #28a745, #34ce57); color: white; }
.grade-b { background: linear-gradient(135deg, #17a2b8, #3dd5f3); color: white; }
.grade-c { background: linear-gradient(135deg, #ffc107, #ffce3a); color: #212529; }
.grade-d { background: linear-gradient(135deg, #fd7e14, #ff9f4a); color: white; }
.grade-f { background: linear-gradient(135deg, #dc3545, #e74c5c); color: white; }

/* Save Button */
.save-btn {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border: none;
    padding: 10px 30px;
    border-radius: 30px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(30,60,114,0.3);
}

.save-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(30,60,114,0.4);
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}

::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-light);
}

/* Responsive */
@media (max-width: 768px) {
    .grade-input {
        width: 60px;
        font-size: 11px;
        padding: 5px;
    }
    .grade-table {
        font-size: 11px;
    }
    .total-badge, .grade-badge {
        padding: 3px 8px;
        font-size: 10px;
    }
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-edit me-2" style="color: var(--primary);"></i>
                <span style="background: linear-gradient(135deg, var(--primary), var(--primary-light)); -webkit-background-clip: text; background-clip: text; color: transparent;">Grade Entry System</span>
            </h1>
            <p class="text-muted small mt-1 mb-0">Enter and manage student grades efficiently</p>
        </div>
        <?php if ($selectedSubjectInfo): ?>
            <div class="d-flex gap-2">
                <div class="badge bg-primary px-3 py-2 rounded-pill">
                    <i class="fas fa-book me-1"></i> <?php echo htmlspecialchars($selectedSubjectInfo['subject_id']); ?>
                </div>
                <div class="badge bg-info px-3 py-2 rounded-pill">
                    <i class="fas fa-users me-1"></i> <?php echo ($students) ? $students->num_rows : 0; ?> Students
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4">
            <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Left Sidebar -->
        <div class="col-lg-3">
            <!-- Subjects Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h6 class="mb-0">
                        <i class="fas fa-book me-2 text-primary"></i>
                        My Subjects (<?php echo htmlspecialchars($currentYear['year_name']); ?>)
                    </h6>
                </div>
                <div class="card-body p-2">
                    <?php if ($subjects && $subjects->num_rows > 0): ?>
                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                            <a href="?subject_id=<?php echo urlencode($subject['subject_id']); ?>" 
                               class="subject-card d-flex justify-content-between align-items-center p-3 text-decoration-none <?php echo $selectedSubject == $subject['subject_id'] ? 'active' : ''; ?>">
                                <div>
                                    <strong><?php echo htmlspecialchars($subject['subject_id']); ?></strong>
                                    <div class="small <?php echo $selectedSubject == $subject['subject_id'] ? 'text-white-50' : 'text-muted'; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right <?php echo $selectedSubject == $subject['subject_id'] ? 'text-white' : 'text-muted'; ?>"></i>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-book-open fa-3x mb-2 d-block"></i>
                            No subjects assigned
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Assessment Management Card -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list-check me-2 text-success"></i>
                        Assessments
                    </h6>
                    <button class="btn btn-sm btn-success rounded-pill" data-bs-toggle="modal" data-bs-target="#addAssessmentModal">
                        <i class="fas fa-plus me-1"></i> Add
                    </button>
                </div>
                <div class="card-body p-2">
                    <?php 
                    $assessList = $conn->query("SELECT * FROM assessment_type ORDER BY assessment_id");
                    if ($assessList && $assessList->num_rows > 0):
                        $totalWeight = 0;
                        while ($assess = $assessList->fetch_assoc()): 
                            $totalWeight += $assess['default_weight'];
                    ?>
                        <div class="assessment-item d-flex justify-content-between align-items-center p-3">
                            <div>
                                <strong><?php echo htmlspecialchars($assess['assessment_name']); ?></strong>
                                <div class="small text-muted"><?php echo $assess['default_weight']; ?> points</div>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-warning rounded-circle me-1" 
                                        onclick="editAssessment(<?php echo $assess['assessment_id']; ?>, '<?php echo htmlspecialchars($assess['assessment_name']); ?>', <?php echo $assess['default_weight']; ?>)">
                                    <i class="fas fa-edit fa-xs"></i>
                                </button>
                                <?php if ($assess['assessment_id'] > 6): ?>
                                    <button class="btn btn-sm btn-outline-danger rounded-circle" 
                                            onclick="if(confirm('Delete this assessment?')) window.location.href='?delete_assessment=<?php echo $assess['assessment_id']; ?>'">
                                        <i class="fas fa-trash fa-xs"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                        <div class="assessment-item d-flex justify-content-between align-items-center p-3 bg-light rounded">
                            <strong>Total Points</strong>
                            <span class="badge <?php echo $totalWeight == 100 ? 'bg-success' : 'bg-warning'; ?> rounded-pill">
                                <?php echo $totalWeight; ?> / 100
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-clipboard-list fa-2x mb-2 d-block"></i>
                            No assessments configured
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <?php if ($selectedSubject && $students && $students->num_rows > 0): ?>
                <div class="grade-table-container">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white border-0 pt-4 pb-0">
                            <h6 class="mb-0">
                                <i class="fas fa-table me-2 text-primary"></i>
                                Grade Entry Table - <?php echo htmlspecialchars($selectedSubjectInfo['subject_name']); ?>
                            </h6>
                            <p class="text-muted small mt-1">Enter points for each assessment (maximum shown in parentheses)</p>
                        </div>
                        <div class="card-body p-0">
                            <form method="POST" id="gradeForm">
                                <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($selectedSubject); ?>">
                                <input type="hidden" name="save_grades" value="1">
                                
                                <div style="overflow-x: auto; max-height: 70vh;">
                                    <table class="grade-table">
                                        <thead>
                                            <tr>
                                                <th style="min-width: 180px; position: sticky; left: 0; z-index: 30;">Student Name</th>
                                                <th style="min-width: 110px; position: sticky; left: 180px; z-index: 30;">Student ID</th>
                                                <?php 
                                                $assessmentTypes->data_seek(0);
                                                $assessmentList = [];
                                                while ($type = $assessmentTypes->fetch_assoc()): 
                                                    $assessmentList[] = $type;
                                                ?>
                                                    <th class="text-center" style="min-width: 110px;">
                                                        <?php echo htmlspecialchars($type['assessment_name']); ?>
                                                        <div class="small opacity-75">(max <?php echo $type['default_weight']; ?>)</div>
                                                    </th>
                                                <?php endwhile; ?>
                                                <th class="text-center" style="min-width: 90px; background: linear-gradient(135deg, #17a2b8, #3dd5f3);">Total</th>
                                                <th class="text-center" style="min-width: 80px; background: linear-gradient(135deg, #28a745, #34ce57);">Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $assessmentTypes->data_seek(0);
                                            $students->data_seek(0);
                                            while ($student = $students->fetch_assoc()): 
                                                $currentTotal = 0;
                                                $hasAnyGrade = false;
                                                foreach ($assessmentList as $type) {
                                                    $score = $existingGrades[$student['student_id']][$type['assessment_id']] ?? 0;
                                                    if ($score > 0) {
                                                        $hasAnyGrade = true;
                                                        $currentTotal += $score;
                                                    }
                                                }
                                                $gradeLetter = getLetterGradeLetter($currentTotal);
                                                $gradeClass = 
                                                    $gradeLetter == 'A+' || $gradeLetter == 'A' ? 'grade-a' :
                                                    ($gradeLetter == 'A-' || $gradeLetter == 'B+' || $gradeLetter == 'B' ? 'grade-b' :
                                                    ($gradeLetter == 'B-' || $gradeLetter == 'C+' || $gradeLetter == 'C' ? 'grade-c' :
                                                    ($gradeLetter == 'C-' || $gradeLetter == 'D' ? 'grade-d' : 'grade-f')));
                                            ?>
                                                <tr>
                                                    <td class="sticky-col sticky-col-left" style="background: white;">
                                                        <strong><?php echo htmlspecialchars(explode(' ', $student['full_name'])[0]); ?></strong>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($student['student_id']); ?></div>
                                                    </td>
                                                    <td class="sticky-col sticky-col-left-2" style="left: 180px; background: white;">
                                                        <code><?php echo htmlspecialchars($student['student_id']); ?></code>
                                                    </td>
                                                    
                                                    <?php foreach ($assessmentList as $type): 
                                                        $errorKey = $student['student_id'] . "_" . $type['assessment_id'];
                                                        $hasError = isset($validationErrors[$errorKey]);
                                                        $currentScore = $existingGrades[$student['student_id']][$type['assessment_id']] ?? '';
                                                        $markId = $gradeIds[$student['student_id']][$type['assessment_id']] ?? 0;
                                                    ?>
                                                        <td class="text-center">
                                                            <div class="d-flex flex-column align-items-center gap-1">
                                                                <input type="number" 
                                                                       name="grades[<?php echo htmlspecialchars($student['student_id']); ?>][<?php echo $type['assessment_id']; ?>]"
                                                                       class="grade-input <?php echo $hasError ? 'is-invalid' : ''; ?>"
                                                                       value="<?php echo htmlspecialchars($currentScore); ?>"
                                                                       min="0" 
                                                                       max="<?php echo $type['default_weight']; ?>" 
                                                                       step="0.5"
                                                                       data-weight="<?php echo $type['default_weight']; ?>"
                                                                       data-student="<?php echo htmlspecialchars($student['student_id']); ?>"
                                                                       oninput="validateAndCalculate(this, <?php echo $type['default_weight']; ?>)">
                                                                <?php if ($markId > 0): ?>
                                                                    <button type="button" 
                                                                            class="edit-grade-btn"
                                                                            onclick="openEditModal(<?php echo $markId; ?>, <?php echo $currentScore ?: 0; ?>, <?php echo $type['default_weight']; ?>, '<?php echo htmlspecialchars($student['student_id']); ?>', '<?php echo htmlspecialchars($type['assessment_name']); ?>')">
                                                                        <i class="fas fa-pen me-1"></i> edit
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if ($hasError): ?>
                                                                    <div class="invalid-feedback d-block small">
                                                                        <?php echo $validationErrors[$errorKey]; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    
                                                    <td class="text-center bg-light">
                                                        <span class="total-badge" id="total_<?php echo htmlspecialchars($student['student_id']); ?>">
                                                            <?php echo $hasAnyGrade ? number_format($currentTotal, 1) : '0.0'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="grade-badge <?php echo $gradeClass; ?>" id="grade_<?php echo htmlspecialchars($student['student_id']); ?>">
                                                            <?php echo $hasAnyGrade ? $gradeLetter : '-'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="p-4 text-end bg-white border-top">
                                    <button type="submit" class="btn save-btn">
                                        <i class="fas fa-save me-2"></i> Save All Grades
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3 rounded-4 small">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-lightbulb fa-2x text-primary"></i>
                        </div>
                        <div>
                            <strong>Quick Guide:</strong>
                            <ul class="mb-0 mt-1">
                                <li>Enter points for each assessment (maximum shown in parentheses)</li>
                                <li>Total score and final grade are calculated automatically</li>
                                <li>Click the "edit" button to modify an individual grade</li>
                                <li>All changes are saved when you click "Save All Grades"</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($selectedSubject): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-users-slash fa-4x text-muted mb-3 d-block"></i>
                        <h5>No Students Enrolled</h5>
                        <p class="text-muted small">No students are currently enrolled in this subject for <?php echo htmlspecialchars($currentYear['year_name']); ?>.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-hand-point-left fa-4x text-muted mb-3 d-block"></i>
                        <h5>Select a Subject</h5>
                        <p class="text-muted small">Choose a subject from the left sidebar to start entering grades.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Assessment Modal -->
<div class="modal fade" id="addAssessmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i> Add Assessment Type</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_assessment" value="1">
                    <div class="mb-3">
                        <label class="form-label">Assessment Name</label>
                        <input type="text" name="assessment_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Points (Weight)</label>
                        <input type="number" name="assessment_weight" class="form-control" min="1" max="100" step="1" required>
                        <small class="text-muted">Total of all assessments should be 100 points</small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Assessment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Assessment Modal -->
<div class="modal fade" id="editAssessmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-warning text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Assessment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_assessment" value="1">
                    <input type="hidden" name="assessment_id" id="edit_assessment_id">
                    <div class="mb-3">
                        <label class="form-label">Assessment Name</label>
                        <input type="text" name="edit_name" id="edit_assessment_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Points (Weight)</label>
                        <input type="number" name="edit_weight" id="edit_assessment_weight" class="form-control" min="1" max="100" step="1" required>
                        <small class="text-muted">Total of all assessments should be 100 points</small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Assessment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Single Grade Modal -->
<div class="modal fade" id="editGradeModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-warning text-white border-0 rounded-top-4">
                <h6 class="modal-title"><i class="fas fa-edit me-1"></i> Edit Grade</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_single_grade" value="1">
                    <input type="hidden" name="mark_id" id="edit_mark_id">
                    <input type="hidden" name="max_points" id="edit_max_points">
                    
                    <div class="mb-2">
                        <label class="form-label small text-muted">Student</label>
                        <input type="text" id="edit_student_name" class="form-control form-control-sm bg-light" readonly>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted">Assessment</label>
                        <input type="text" id="edit_assessment" class="form-control form-control-sm bg-light" readonly>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted">Current Score</label>
                        <input type="text" id="edit_current_score" class="form-control form-control-sm bg-light" readonly>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted">New Score</label>
                        <input type="number" name="new_score" id="edit_new_score" class="form-control form-control-sm" step="0.5" required>
                        <small class="text-muted">Maximum: <span id="max_points_display"></span> points</small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm">Update Grade</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function validateAndCalculate(inputElement, maxValue) {
    let value = parseFloat(inputElement.value);
    const errorDiv = inputElement.parentElement.querySelector('.invalid-feedback');
    
    inputElement.classList.remove('is-invalid');
    if (errorDiv) errorDiv.remove();
    
    if (!isNaN(value) && value !== '') {
        if (value < 0) {
            showError(inputElement, 'Value cannot be negative');
            value = 0;
            inputElement.value = 0;
        } else if (value > maxValue) {
            showError(inputElement, 'Maximum is ' + maxValue + ' points');
            value = maxValue;
            inputElement.value = maxValue;
        }
    }
    
    calculateStudentTotal(inputElement);
}

function showError(inputElement, message) {
    inputElement.classList.add('is-invalid');
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback d-block small';
    errorDiv.textContent = message;
    inputElement.parentElement.appendChild(errorDiv);
}

function calculateStudentTotal(inputElement) {
    const row = inputElement.closest('tr');
    const gradeInputs = row.querySelectorAll('.grade-input');
    let total = 0;
    let hasGrade = false;
    
    gradeInputs.forEach(input => {
        const score = parseFloat(input.value);
        if (!isNaN(score) && score !== '') {
            hasGrade = true;
            total += score;
        }
    });
    
    const studentId = inputElement.dataset.student;
    const totalSpan = document.getElementById(`total_${studentId}`);
    const gradeSpan = document.getElementById(`grade_${studentId}`);
    
    if (hasGrade) {
        const finalTotal = Math.round(total * 10) / 10;
        totalSpan.textContent = finalTotal.toFixed(1);
        
        const letter = getLetterGrade(finalTotal);
        gradeSpan.textContent = letter;
        
        // Update grade badge class
        const gradeClass = 
            letter == 'A+' || letter == 'A' ? 'grade-a' :
            (letter == 'A-' || letter == 'B+' || letter == 'B' ? 'grade-b' :
            (letter == 'B-' || letter == 'C+' || letter == 'C' ? 'grade-c' :
            (letter == 'C-' || letter == 'D' ? 'grade-d' : 'grade-f')));
        gradeSpan.className = 'grade-badge ' + gradeClass;
    } else {
        totalSpan.textContent = '0.0';
        gradeSpan.textContent = '-';
        gradeSpan.className = 'grade-badge grade-f';
    }
}

function getLetterGrade(score) {
    if (score >= 90) return 'A+';
    if (score >= 85) return 'A';
    if (score >= 80) return 'A-';
    if (score >= 75) return 'B+';
    if (score >= 70) return 'B';
    if (score >= 65) return 'B-';
    if (score >= 60) return 'C+';
    if (score >= 55) return 'C';
    if (score >= 50) return 'C-';
    if (score >= 45) return 'D';
    return 'F';
}

function editAssessment(id, name, weight) {
    document.getElementById('edit_assessment_id').value = id;
    document.getElementById('edit_assessment_name').value = name;
    document.getElementById('edit_assessment_weight').value = weight;
    new bootstrap.Modal(document.getElementById('editAssessmentModal')).show();
}

function openEditModal(markId, currentScore, maxPoints, studentCode, assessmentName) {
    if (markId == 0) {
        alert('Please save the grade first before editing.');
        return;
    }
    document.getElementById('edit_mark_id').value = markId;
    document.getElementById('edit_current_score').value = currentScore;
    document.getElementById('edit_new_score').value = currentScore;
    document.getElementById('edit_max_points').value = maxPoints;
    document.getElementById('max_points_display').textContent = maxPoints;
    document.getElementById('edit_student_name').value = studentCode;
    document.getElementById('edit_assessment').value = assessmentName;
    new bootstrap.Modal(document.getElementById('editGradeModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    const gradeInputs = document.querySelectorAll('.grade-input');
    gradeInputs.forEach(input => {
        if (input.value !== '') {
            calculateStudentTotal(input);
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>