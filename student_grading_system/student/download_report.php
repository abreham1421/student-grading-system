<?php
// student/download_report.php - Student Report Card with Rank
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

// Get student ID from session
$studentId = $_SESSION['student_id'] ?? '';

// Fallback: get student ID from username
if (empty($studentId) && isset($_SESSION['username'])) {
    $conn = db();
    $username = $conn->real_escape_string($_SESSION['username']);
    $result = $conn->query("SELECT student_id FROM student WHERE username = '$username'");
    if ($result && $result->num_rows > 0) {
        $studentId = $result->fetch_assoc()['student_id'];
        $_SESSION['student_id'] = $studentId;
    }
}

if (empty($studentId)) {
    die("Student ID not found. Please login again.");
}

$conn = db();

// Get current academic year
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
if (!$yearResult || $yearResult->num_rows == 0) {
    $currentYear = ['year_id' => 2, 'year_name' => '2025/26'];
} else {
    $currentYear = $yearResult->fetch_assoc();
}
$yearId = $currentYear['year_id'];

// Get student info
$studentInfo = $conn->query("
    SELECT s.*, u.full_name, u.email, u.phone, u.username 
    FROM student s 
    JOIN users u ON s.username = u.username 
    WHERE s.student_id = '$studentId'
")->fetch_assoc();

if (!$studentInfo) {
    die("Student information not found.");
}

// Get subjects with grades
$subjects = $conn->query("
    SELECT s.subject_id, s.subject_name, s.credits,
           fg.total_score, fg.letter_grade, fg.grade_point
    FROM student_subject ss
    JOIN subject s ON ss.subject_id = s.subject_id
    LEFT JOIN final_grade fg ON s.subject_id = fg.subject_id 
        AND fg.student_id = ss.student_id 
        AND fg.academic_year_id = ss.academic_year_id
    WHERE ss.student_id = '$studentId' AND ss.academic_year_id = $yearId AND ss.is_active = 1
    ORDER BY s.subject_id
");

// Calculate GPA
$gpaResult = $conn->query("
    SELECT SUM(fg.grade_point * s.credits) as total_points, SUM(s.credits) as total_credits
    FROM final_grade fg
    JOIN subject s ON fg.subject_id = s.subject_id
    WHERE fg.student_id = '$studentId' AND fg.academic_year_id = $yearId
");
$gpaData = $gpaResult->fetch_assoc();
$totalPoints = $gpaData['total_points'] ?? 0;
$totalCredits = $gpaData['total_credits'] ?? 0;
$gpa = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0;

// ========== RANK CALCULATION ==========
// Get all students in the same grade and section with their total scores
$rankData = [];
$rankQuery = $conn->query("
    SELECT s.student_id, u.full_name, 
           COALESCE(SUM(fg.total_score), 0) as total_score,
           COUNT(fg.total_score) as subjects_count,
           COALESCE(AVG(fg.grade_point), 0) as student_gpa
    FROM student s
    JOIN users u ON s.username = u.username
    LEFT JOIN final_grade fg ON s.student_id = fg.student_id AND fg.academic_year_id = $yearId
    WHERE s.current_grade_level = '{$studentInfo['current_grade_level']}' 
      AND s.current_section = '{$studentInfo['current_section']}'
      AND s.student_status = 'active'
    GROUP BY s.student_id
    ORDER BY COALESCE(SUM(fg.total_score), 0) DESC
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
            'full_name' => $row['full_name'],
            'gpa' => $row['student_gpa']
        ];
        $rankPosition++;
        $prevScore = $row['total_score'];
    }
}

$currentStudentRank = $studentRanks[$studentId]['rank'] ?? 0;
$totalStudentsInClass = count($studentRanks);
$rankPercentage = $totalStudentsInClass > 0 ? round((($currentStudentRank - 1) / $totalStudentsInClass) * 100, 1) : 0;

// Helper function for rank badge
function getRankText($rank) {
    if ($rank == 1) return '🥇 1st Place';
    if ($rank == 2) return '🥈 2nd Place';
    if ($rank == 3) return '🥉 3rd Place';
    return "#{$rank}";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card - <?php echo htmlspecialchars($studentInfo['full_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .report-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 30px 20px 20px;
            border-bottom: 2px solid #1e3c72;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }
        .school-name {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .report-title {
            font-size: 20px;
            font-weight: bold;
            margin-top: 15px;
            color: #ffd700;
        }
        .student-info {
            margin: 20px 25px;
            padding: 18px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #1e3c72;
        }
        .student-info table {
            width: 100%;
        }
        .student-info td {
            padding: 6px 8px;
        }
        .grades-table {
            width: calc(100% - 50px);
            margin: 20px 25px;
            border-collapse: collapse;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .grades-table th, .grades-table td {
            border: 1px solid #dee2e6;
            padding: 12px 10px;
            text-align: center;
        }
        .grades-table th {
            background: #1e3c72;
            color: white;
            font-weight: 600;
        }
        .grades-table td {
            background: white;
        }
        .grades-table tr:hover td {
            background: #f8f9fa;
        }
        .summary {
            margin: 20px 25px;
            padding: 18px;
            background: #e8f4f8;
            border-radius: 10px;
            border-left: 4px solid #17a2b8;
        }
        .rank-section {
            margin: 20px 25px;
            padding: 18px;
            background: linear-gradient(135deg, #fff8e1, #fff3cd);
            border-radius: 10px;
            border-left: 4px solid #ffc107;
        }
        .rank-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: bold;
            font-size: 18px;
        }
        .rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #212529; }
        .rank-2 { background: linear-gradient(135deg, #c0c0c0, #e8e8e8); color: #212529; }
        .rank-3 { background: linear-gradient(135deg, #cd7f32, #e8a870); color: white; }
        .rank-other { background: linear-gradient(135deg, #6c757d, #8a929a); color: white; }
        .signature {
            margin: 30px 25px 20px;
            display: flex;
            justify-content: space-between;
            padding-top: 20px;
            border-top: 1px dashed #dee2e6;
        }
        .signature div {
            text-align: center;
            width: 30%;
        }
        .footer {
            text-align: center;
            padding: 15px;
            font-size: 11px;
            color: #6c757d;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            .report-container {
                box-shadow: none;
                margin: 0;
                border-radius: 0;
            }
            .grades-table th {
                background: #1e3c72 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .rank-section {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        .btn-print {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 10px;
            font-size: 14px;
            transition: transform 0.2s;
        }
        .btn-print:hover {
            transform: translateY(-2px);
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .badge-pass { color: #28a745; font-weight: bold; }
        .badge-fail { color: #dc3545; font-weight: bold; }
        .badge-pending { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>
    <div class="no-print text-center">
        <button class="btn-print" onclick="window.print()">
            🖨️ Print Report Card
        </button>
        <button class="btn-print" onclick="window.history.back()">
            ⬅️ Back
        </button>
    </div>
    
    <div class="report-container">
        <div class="header">
            <div class="school-name">🏫 Gada Secondary School</div>
            <div>Bocho Bore Kebele, Addis Ababa, Ethiopia</div>
            <div>📞 +251-911-234567 | ✉️ info@gadaschool.edu.et</div>
            <div class="report-title">📊 STUDENT REPORT CARD</div>
            <div>Academic Year: <?php echo $currentYear['year_name']; ?></div>
        </div>
        
        <div class="student-info">
            <table>
                <tr>
                    <td width="50%"><strong>👨‍🎓 Student Name:</strong> <?php echo htmlspecialchars($studentInfo['full_name']); ?></td>
                    <td width="50%"><strong>🆔 Student ID:</strong> <?php echo htmlspecialchars($studentInfo['student_id']); ?></td>
                </tr>
                <tr>
                    <td><strong>📚 Grade Level:</strong> <?php echo $studentInfo['current_grade_level']; ?></td>
                    <td><strong>📌 Section:</strong> <?php echo $studentInfo['current_section']; ?></td>
                </tr>
                <tr>
                    <td><strong>🔬 Field of Study:</strong> <?php echo $studentInfo['field_of_study'] ?: 'General'; ?></td>
                    <td><strong>📅 Enrollment Date:</strong> <?php echo date('M d, Y', strtotime($studentInfo['enrollment_date'])); ?></td>
                </tr>
                <tr>
                    <td><strong>👪 Parent Name:</strong> <?php echo htmlspecialchars($studentInfo['parent_name'] ?: 'N/A'); ?></td>
                    <td><strong>📞 Parent Phone:</strong> <?php echo htmlspecialchars($studentInfo['parent_phone'] ?: 'N/A'); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Rank Section -->
        <?php if ($currentStudentRank > 0 && $totalStudentsInClass > 0): ?>
        <div class="rank-section">
            <table width="100%">
                <tr>
                    <td width="33%" class="text-center">
                        <div class="rank-badge <?php echo $currentStudentRank <= 3 ? 'rank-' . $currentStudentRank : 'rank-other'; ?>">
                            <?php echo getRankText($currentStudentRank); ?>
                        </div>
                    </td>
                    <td width="34%" class="text-center">
                        <div>
                            <strong>📊 Class Size:</strong> <?php echo $totalStudentsInClass; ?> students
                        </div>
                    </td>
                    <td width="33%" class="text-center">
                        <div>
                            <strong>📈 Top <?php echo $rankPercentage; ?>%</strong>
                            <br>
                            <small>of class</small>
                        </div>
                    </td>
                </tr>
                <?php if ($currentStudentRank <= 3): ?>
                <tr>
                    <td colspan="3" class="text-center" style="padding-top: 10px;">
                        <span style="color: #ffc107;">🏆 Congratulations! You are in the Honor Roll! 🏆</span>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <table class="grades-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Subject Name</th>
                    <th>Credits</th>
                    <th>Score (%)</th>
                    <th>Grade</th>
                    <th>Points</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totalCreditsEarned = 0;
                $totalGradePoints = 0;
                $hasGrades = false;
                $subjects->data_seek(0);
                while ($subject = $subjects->fetch_assoc()): 
                    $hasGrades = true;
                    $score = $subject['total_score'];
                    $status = $score >= 50 ? 'Passed' : ($score !== null ? 'Failed' : 'Pending');
                    $statusClass = $score >= 50 ? 'badge-pass' : ($score !== null ? 'badge-fail' : 'badge-pending');
                    if ($status == 'Passed') {
                        $totalCreditsEarned += $subject['credits'];
                    }
                    if ($subject['grade_point']) {
                        $totalGradePoints += $subject['grade_point'] * $subject['credits'];
                    }
                ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($subject['subject_id']); ?></strong></td>
                        <td class="text-left"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                        <td><?php echo $subject['credits']; ?></td>
                        <td><?php echo $score ? number_format($score, 1) . '%' : '-'; ?></td>
                        <td><?php echo $subject['letter_grade'] ?: '-'; ?></td>
                        <td><?php echo $subject['grade_point'] ? number_format($subject['grade_point'], 2) : '-'; ?></td>
                        <td class="<?php echo $statusClass; ?>"><?php echo $status; ?></td>
                    </tr>
                <?php endwhile; ?>
                
                <?php if (!$hasGrades): ?>
                    <tr>
                        <td colspan="7" class="text-center">No grades available for this academic year</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="summary">
            <table width="100%">
                <tr>
                    <td width="33%"><strong>📊 Total Credits Taken:</strong> <?php echo $totalCredits; ?></td>
                    <td width="33%"><strong>✅ Credits Earned:</strong> <?php echo $totalCreditsEarned; ?></td>
                    <td width="33%"><strong>🎯 GPA:</strong> <?php echo number_format($gpa, 2); ?></td>
                </tr>
                <tr>
                    <td><strong>⭐ CGPA:</strong> <?php echo number_format($gpa, 2); ?></td>
                    <td><strong>🏆 Academic Standing:</strong> 
                        <?php 
                        if ($gpa >= 3.5) echo '<span style="color:#28a745;">🌟 Excellent</span>';
                        elseif ($gpa >= 2.5) echo '<span style="color:#17a2b8;">👍 Good</span>';
                        elseif ($gpa >= 2.0) echo '<span style="color:#ffc107;">📖 Satisfactory</span>';
                        else echo '<span style="color:#dc3545;">⚠️ Probation</span>';
                        ?>
                    </td>
                    <td><strong>📌 Class Rank:</strong> 
                        <?php 
                        if ($currentStudentRank > 0) {
                            echo "#{$currentStudentRank} of {$totalStudentsInClass}";
                        } else {
                            echo "N/A";
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="signature">
            <div>
                _____________________<br>
                <strong>Class Teacher</strong><br>
                <small>Name & Signature</small>
            </div>
            <div>
                _____________________<br>
                <strong>Department Head</strong><br>
                <small>Name & Signature</small>
            </div>
            <div>
                _____________________<br>
                <strong>School Director</strong><br>
                <small>Name & Signature</small>
            </div>
        </div>
        
        <div class="footer">
            <p>📌 This is a computer-generated document and does not require a physical signature.</p>
            <p>🕒 Generated on: <?php echo date('F d, Y H:i:s'); ?></p>
            <p>© <?php echo date('Y'); ?> Gada Secondary School - Student Grading System v2.0</p>
        </div>
    </div>
</body>
</html>