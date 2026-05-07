<?php
// admin/delete_student.php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db_connection.php';

$conn = db();
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$student_id) {
    $_SESSION['error'] = "Invalid student ID!";
    header('Location: manage_students.php');
    exit();
}

// Get student info for confirmation message
$studentInfo = $conn->query("SELECT s.student_code, u.full_name 
                              FROM student s 
                              JOIN users u ON s.user_id = u.user_id 
                              WHERE s.student_id = $student_id");

if (!$studentInfo || $studentInfo->num_rows == 0) {
    $_SESSION['error'] = "Student not found!";
    header('Location: manage_students.php');
    exit();
}

$student = $studentInfo->fetch_assoc();

// Check if student has any grades or enrollments
$hasGrades = $conn->query("SELECT COUNT(*) as count FROM mark WHERE student_id = $student_id")->fetch_assoc()['count'] > 0;
$hasEnrollments = $conn->query("SELECT COUNT(*) as count FROM student_subject WHERE student_id = $student_id")->fetch_assoc()['count'] > 0;
$hasFinalGrades = $conn->query("SELECT COUNT(*) as count FROM final_grade WHERE student_id = $student_id")->fetch_assoc()['count'] > 0;

// Process deletion if confirmed
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    // Get user_id
    $userResult = $conn->query("SELECT user_id FROM student WHERE student_id = $student_id");
    if ($userResult && $userResult->num_rows > 0) {
        $user = $userResult->fetch_assoc();
        $user_id = $user['user_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete related records first (foreign key constraints)
            $conn->query("DELETE FROM mark WHERE student_id = $student_id");
            $conn->query("DELETE FROM student_subject WHERE student_id = $student_id");
            $conn->query("DELETE FROM final_grade WHERE student_id = $student_id");
            $conn->query("DELETE FROM student WHERE student_id = $student_id");
            $conn->query("DELETE FROM users WHERE user_id = $user_id");
            
            $conn->commit();
            $_SESSION['message'] = "Student '" . $student['student_code'] . " - " . $student['full_name'] . "' has been deleted successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error deleting student: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Student not found!";
    }
    
    header('Location: manage_students.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Student - Gada Secondary School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f4f7fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .delete-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .delete-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 550px;
            width: 100%;
            overflow: hidden;
        }
        .delete-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .delete-header i {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        .delete-body {
            padding: 30px;
        }
        .student-info {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
        }
        .btn-cancel:hover {
            background: #5a6268;
            color: white;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
        }
        .btn-delete:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="delete-container">
        <div class="delete-card">
            <div class="delete-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h2>Delete Student</h2>
                <p>This action cannot be undone</p>
            </div>
            <div class="delete-body">
                <div class="student-info">
                    <strong><i class="fas fa-user-graduate me-2"></i> Student Information:</strong><br>
                    <strong>Student Code:</strong> <?php echo htmlspecialchars($student['student_code']); ?><br>
                    <strong>Full Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?>
                </div>
                
                <?php if ($hasGrades || $hasEnrollments || $hasFinalGrades): ?>
                    <div class="warning-box">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Warning:</strong> This student has associated data:<br>
                        <?php if ($hasEnrollments): ?>
                            <span class="badge bg-warning mt-1">📚 Enrolled in subjects</span>
                        <?php endif; ?>
                        <?php if ($hasGrades): ?>
                            <span class="badge bg-info mt-1">📊 Has grades recorded</span>
                        <?php endif; ?>
                        <?php if ($hasFinalGrades): ?>
                            <span class="badge bg-secondary mt-1">🎓 Has final grades</span>
                        <?php endif; ?>
                        <br><br>
                        Deleting this student will permanently remove all associated data including:
                        <ul class="mt-2">
                            <li>All grades and marks</li>
                            <li>Subject enrollments</li>
                            <li>Final grades and GPA</li>
                            <li>Student account</li>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-danger">
                    <i class="fas fa-trash-alt me-2"></i>
                    Are you sure you want to delete this student? This action is permanent and cannot be reversed.
                </div>
                
                <div class="d-flex justify-content-between gap-3">
                    <a href="manage_students.php" class="btn-cancel text-center flex-grow-1">
                        <i class="fas fa-times me-2"></i> Cancel
                    </a>
                    <a href="?id=<?php echo $student_id; ?>&confirm=yes" class="btn-delete flex-grow-1 text-center" 
                       onclick="return confirm('Are you ABSOLUTELY sure you want to delete this student? This cannot be undone!')">
                        <i class="fas fa-trash-alt me-2"></i> Yes, Delete Student
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>