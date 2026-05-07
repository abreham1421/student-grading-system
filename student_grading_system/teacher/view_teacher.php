<?php
// teacher/view_teacher.php - For teacher to view their own profile
$pageTitle = 'My Profile';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$teacherId = $_SESSION['profile_id'] ?? 0;

if (!$teacherId && isset($_SESSION['user_id'])) {
    $result = $conn->query("SELECT teacher_id FROM teacher WHERE user_id = {$_SESSION['user_id']}");
    if ($result && $result->num_rows > 0) {
        $teacherId = $result->fetch_assoc()['teacher_id'];
        $_SESSION['profile_id'] = $teacherId;
    }
}

if (!$teacherId) {
    echo '<div class="alert alert-danger">Teacher profile not found.</div>';
    require_once '../includes/footer.php';
    exit();
}

// Get teacher information
$teacherQuery = $conn->query("SELECT t.*, u.user_id, u.username, u.email, u.full_name, u.phone, u.address, u.profile_image,
                              u.created_at, u.last_login
                              FROM teacher t 
                              JOIN users u ON t.user_id = u.user_id 
                              WHERE t.teacher_id = $teacherId");

if (!$teacherQuery || $teacherQuery->num_rows == 0) {
    echo '<div class="alert alert-danger">Teacher information not found.</div>';
    require_once '../includes/footer.php';
    exit();
}

$teacher = $teacherQuery->fetch_assoc();

// Get assigned subjects
$assignedSubjects = $conn->query("SELECT s.subject_id, s.subject_id, s.subject_name, s.credits, s.subject_type
                                   FROM teacher_subject ts
                                   JOIN subject s ON ts.subject_id = s.subject_id
                                   WHERE ts.teacher_id = $teacherId
                                   ORDER BY s.subject_id");

// Get students count
$studentCount = 0;
if ($assignedSubjects && $assignedSubjects->num_rows > 0) {
    $subjectIds = [];
    $assignedSubjects->data_seek(0);
    while ($subject = $assignedSubjects->fetch_assoc()) {
        $subjectIds[] = $subject['subject_id'];
    }
    $assignedSubjects->data_seek(0);
    
    if (!empty($subjectIds)) {
        $ids = implode(',', $subjectIds);
        $studentResult = $conn->query("SELECT COUNT(DISTINCT student_id) as total 
                                        FROM student_subject 
                                        WHERE subject_id IN ($ids) AND is_active = 1");
        if ($studentResult && $studentResult->num_rows > 0) {
            $studentCount = $studentResult->fetch_assoc()['total'];
        }
    }
}

// Get grades count
$gradesCount = $conn->query("SELECT COUNT(*) as total FROM mark WHERE teacher_id = $teacherId")->fetch_assoc()['total'] ?? 0;
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="fas fa-chalkboard-user me-2"></i> My Profile
        </h1>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
            <a href="profile.php" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i> Edit Profile
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-user-circle me-2"></i> My Profile
                </div>
                <div class="card-body text-center">
                    <div class="rounded-circle bg-primary d-inline-flex p-4 mb-3">
                        <i class="fas fa-chalkboard-user fa-4x text-white"></i>
                    </div>
                    <h4><?php echo htmlspecialchars($teacher['full_name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($teacher['teacher_code']); ?></p>
                    <hr>
                    <div class="text-start">
                        <p><strong><i class="fas fa-envelope"></i> Email:</strong><br>
                        <?php echo htmlspecialchars($teacher['email']); ?></p>
                        <p><strong><i class="fas fa-phone"></i> Phone:</strong><br>
                        <?php echo htmlspecialchars($teacher['phone'] ?: 'N/A'); ?></p>
                        <p><strong><i class="fas fa-map-marker-alt"></i> Address:</strong><br>
                        <?php echo htmlspecialchars($teacher['address'] ?: 'N/A'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="card shadow mt-4">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-chart-line me-2"></i> My Statistics
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h3><?php echo $assignedSubjects ? $assignedSubjects->num_rows : 0; ?></h3>
                            <small>Subjects</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h3><?php echo $studentCount; ?></h3>
                            <small>Students</small>
                        </div>
                        <div class="col-6">
                            <h3><?php echo $gradesCount; ?></h3>
                            <small>Grades Entered</small>
                        </div>
                        <div class="col-6">
                            <h3><?php echo $teacher['experience_years'] ?? 0; ?>+</h3>
                            <small>Experience</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-graduation-cap me-2"></i> Professional Information
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Qualification:</strong><br>
                            <?php echo htmlspecialchars($teacher['qualification'] ?: 'Not Specified'); ?></p>
                            <p><strong>Specialization:</strong><br>
                            <?php echo htmlspecialchars($teacher['specialization'] ?: 'General'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Department:</strong><br>
                            <?php echo htmlspecialchars($teacher['department'] ?: 'General'); ?></p>
                            <p><strong>Join Date:</strong><br>
                            <?php echo $teacher['join_date'] ? date('M d, Y', strtotime($teacher['join_date'])) : 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow mt-4">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-book me-2"></i> My Subjects
                </div>
                <div class="card-body">
                    <?php if ($assignedSubjects && $assignedSubjects->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Credits</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($subject = $assignedSubjects->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></small>
                                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></small>
                                            <td><span class="badge bg-info"><?php echo $subject['credits']; ?> credits</span></small>
                                            <td>
                                                <span class="badge bg-<?php echo $subject['subject_type'] == 'core' ? 'primary' : 'warning'; ?>">
                                                    <?php echo ucfirst($subject['subject_type']); ?>
                                                </span>
                                            </small>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center mb-0">
                            No subjects assigned yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>