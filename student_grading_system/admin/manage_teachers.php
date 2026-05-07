<?php
// admin/manage_teachers.php - Professional teacher management
$pageTitle = 'Manage Teachers';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$message = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $teacher_id = $conn->real_escape_string($_GET['delete']);
    
    // Get username first
    $userResult = $conn->query("SELECT username FROM teacher WHERE teacher_id = '$teacher_id'");
    if ($userResult && $userResult->num_rows > 0) {
        $user = $userResult->fetch_assoc();
        $username = $user['username'];
        
        $conn->query("DELETE FROM teacher WHERE teacher_id = '$teacher_id'");
        $conn->query("DELETE FROM users WHERE username = '$username'");
        $message = "Teacher deleted successfully!";
    } else {
        $error = "Teacher not found!";
    }
}

// Handle Add Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $teacher_id = strtoupper(trim($_POST['teacher_id']));
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $department = trim($_POST['department'] ?? 'General');
    $join_date = $_POST['join_date'] ?? date('Y-m-d');
    
    $errors = [];
    
    // Validate required fields
    if (empty($teacher_id)) $errors[] = "Teacher ID is required.";
    if (empty($username)) $errors[] = "Username is required.";
    if (empty($email)) $errors[] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (empty($full_name)) $errors[] = "Full name is required.";
    
    // Check if username already exists
    $checkUser = $conn->query("SELECT username FROM users WHERE username = '$username'");
    if ($checkUser && $checkUser->num_rows > 0) $errors[] = "Username already exists!";
    
    // Check if email already exists
    $checkEmail = $conn->query("SELECT email FROM users WHERE email = '$email'");
    if ($checkEmail && $checkEmail->num_rows > 0) $errors[] = "Email already exists!";
    
    // Check if teacher ID already exists
    $checkTeacher = $conn->query("SELECT teacher_id FROM teacher WHERE teacher_id = '$teacher_id'");
    if ($checkTeacher && $checkTeacher->num_rows > 0) $errors[] = "Teacher ID already exists!";
    
    if (empty($errors)) {
        // Insert into users table
        $sql1 = "INSERT INTO users (username, password, email, full_name, phone, address, role, is_active) 
                 VALUES ('$username', '$password', '$email', '$full_name', " . ($phone ? "'$phone'" : "NULL") . ", " . ($address ? "'$address'" : "NULL") . ", 'teacher', 1)";
        
        if ($conn->query($sql1)) {
            // Insert into teacher table
            $sql2 = "INSERT INTO teacher (teacher_id, username, qualification, specialization, department, join_date, status) 
                     VALUES ('$teacher_id', '$username', " . ($qualification ? "'$qualification'" : "NULL") . ", " . ($specialization ? "'$specialization'" : "NULL") . ", '$department', '$join_date', 'active')";
            
            if ($conn->query($sql2)) {
                $message = "Teacher added successfully!<br>
                           <strong>Teacher ID:</strong> $teacher_id<br>
                           <strong>Username:</strong> $username<br>
                           <strong>Password:</strong> " . htmlspecialchars($_POST['password']);
            } else {
                $error = "Error adding teacher: " . $conn->error;
                $conn->query("DELETE FROM users WHERE username = '$username'");
            }
        } else {
            $error = "Error adding user: " . $conn->error;
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Get all teachers
$teachers = $conn->query("
    SELECT t.*, u.full_name, u.email, u.phone, u.address 
    FROM teacher t 
    JOIN users u ON t.username = u.username 
    WHERE t.status = 'active'
    ORDER BY t.teacher_id
");

// Get department counts
$deptScience = $conn->query("SELECT COUNT(*) as total FROM teacher WHERE department = 'Science' AND status = 'active'")->fetch_assoc()['total'] ?? 0;
$deptMath = $conn->query("SELECT COUNT(*) as total FROM teacher WHERE department = 'Mathematics' AND status = 'active'")->fetch_assoc()['total'] ?? 0;
$deptLanguage = $conn->query("SELECT COUNT(*) as total FROM teacher WHERE department = 'Language' AND status = 'active'")->fetch_assoc()['total'] ?? 0;
$deptSocial = $conn->query("SELECT COUNT(*) as total FROM teacher WHERE department = 'Social Science' AND status = 'active'")->fetch_assoc()['total'] ?? 0;
$totalTeachers = $deptScience + $deptMath + $deptLanguage + $deptSocial;
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

/* Page Header Styles */
.page-header-wrapper {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    border-radius: 20px;
    padding: 25px 30px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.page-header-wrapper::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 8s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.page-header-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.page-title-section h1 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    color: white;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title-section h1 i {
    font-size: 2rem;
    background: rgba(255,255,255,0.2);
    padding: 12px;
    border-radius: 50%;
}

.page-title-section p {
    margin: 8px 0 0 0;
    color: rgba(255,255,255,0.9);
    font-size: 0.9rem;
}

.header-stats {
    display: flex;
    gap: 20px;
    background: rgba(255,255,255,0.15);
    padding: 12px 25px;
    border-radius: 50px;
    backdrop-filter: blur(10px);
}

.header-stat-item {
    text-align: center;
}

.header-stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    line-height: 1;
}

.header-stat-label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.8);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.btn-add-teacher {
    background: linear-gradient(135deg, #28a745, #20c997);
    border: none;
    padding: 12px 28px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    color: white;
}

.btn-add-teacher:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    color: white;
}

/* Table Styles */
.teachers-card {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: none;
}

.table-modern {
    margin-bottom: 0;
}

.table-modern thead th {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 14px 16px;
    border: none;
}

.table-modern tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid var(--gray-200);
}

.table-modern tbody tr:hover {
    background: var(--gray-100);
    transform: translateX(2px);
}

.table-modern tbody td {
    padding: 14px 16px;
    vertical-align: middle;
    font-size: 13px;
}

.teacher-id-badge {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    background: #e8f0fe;
    color: var(--primary);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: inline-block;
}

.department-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.dept-science { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
.dept-math { background: linear-gradient(135deg, #4facfe, #00f2fe); color: white; }
.dept-language { background: linear-gradient(135deg, #f093fb, #f5576c); color: white; }
.dept-social { background: linear-gradient(135deg, #43e97b, #38f9d7); color: white; }
.dept-ict { background: linear-gradient(135deg, #ffc371, #ff5f6d); color: white; }
.dept-general { background: #e2e8f0; color: #475569; }

.action-btn {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    margin: 0 2px;
}

.action-btn:hover {
    transform: translateY(-2px);
}

.btn-view { background: #17a2b8; color: white; border: none; }
.btn-view:hover { background: #138496; }

.btn-edit { background: #ffc107; color: #212529; border: none; }
.btn-edit:hover { background: #e0a800; }

.btn-delete { background: #dc3545; color: white; border: none; }
.btn-delete:hover { background: #c82333; }

.modal-modern .modal-content {
    border-radius: 20px;
    border: none;
    overflow: hidden;
}

.modal-modern .modal-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    padding: 15px 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .action-buttons {
        flex-direction: column;
        gap: 4px;
    }
    .action-btn {
        width: 28px;
        height: 28px;
    }
    .header-stats {
        padding: 8px 15px;
        gap: 10px;
    }
    .header-stat-number {
        font-size: 1.2rem;
    }
}
</style>

<div class="container-fluid">
    <!-- Attractive Page Header -->
    <div class="page-header-wrapper">
        <div class="page-header-content">
            <div class="page-title-section">
                <h1>
                    <i class="fas fa-chalkboard-user"></i>
                    Manage Teachers
                </h1>
                <p><i class="fas fa-users me-1"></i> Manage all teacher records and assignments</p>
            </div>
            <div class="header-stats">
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $totalTeachers; ?></div>
                    <div class="header-stat-label">Total Teachers</div>
                </div>
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $deptScience; ?></div>
                    <div class="header-stat-label">Science</div>
                </div>
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $deptMath; ?></div>
                    <div class="header-stat-label">Math</div>
                </div>
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $deptLanguage; ?></div>
                    <div class="header-stat-label">Language</div>
                </div>
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $deptSocial; ?></div>
                    <div class="header-stat-label">Social</div>
                </div>
            </div>
            <div>
                <button class="btn btn-add-teacher" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="fas fa-plus-circle me-2"></i> Add New Teacher
                </button>
            </div>
        </div>
    </div>
    
    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
            <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Teachers Table -->
    <div class="card teachers-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-modern" id="teachersTable">
                    <thead>
                        <tr>
                            <th>Teacher ID</th>
                            <th>Teacher Name</th>
                            <th>Contact</th>
                            <th>Department</th>
                            <th>Qualification</th>
                            <th>Join Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($teachers && $teachers->num_rows > 0): ?>
                            <?php while ($teacher = $teachers->fetch_assoc()): 
                                $deptClass = 'dept-general';
                                if ($teacher['department'] == 'Science') $deptClass = 'dept-science';
                                elseif ($teacher['department'] == 'Mathematics') $deptClass = 'dept-math';
                                elseif ($teacher['department'] == 'Language') $deptClass = 'dept-language';
                                elseif ($teacher['department'] == 'Social Science') $deptClass = 'dept-social';
                                elseif ($teacher['department'] == 'ICT') $deptClass = 'dept-ict';
                            ?>
                                <tr>
                                    <td>
                                        <span class="teacher-id-badge">
                                            <i class="fas fa-id-card me-1"></i>
                                            <?php echo htmlspecialchars($teacher['teacher_id']); ?>
                                        </span>
                                    </small>
                                    <td>
                                        <strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($teacher['username']); ?></small>
                                    </small>
                                    <td>
                                        <div><i class="fas fa-envelope text-muted me-1 fa-xs"></i> <?php echo htmlspecialchars(substr($teacher['email'], 0, 25)); ?></div>
                                        <?php if ($teacher['phone']): ?>
                                            <div><i class="fas fa-phone text-muted me-1 fa-xs"></i> <?php echo htmlspecialchars($teacher['phone']); ?></div>
                                        <?php endif; ?>
                                    </small>
                                    <td>
                                        <span class="department-badge <?php echo $deptClass; ?>">
                                            <?php echo htmlspecialchars($teacher['department'] ?: 'General'); ?>
                                        </span>
                                    </small>
                                    <td><?php echo htmlspecialchars($teacher['qualification'] ?: 'Not Specified'); ?> </small>
                                    <td><?php echo date('M d, Y', strtotime($teacher['join_date'])); ?> </small>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="view_teacher.php?id=<?php echo urlencode($teacher['teacher_id']); ?>" 
                                               class="btn action-btn btn-view" title="View Teacher">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_teacher.php?id=<?php echo urlencode($teacher['teacher_id']); ?>" 
                                               class="btn action-btn btn-edit" title="Edit Teacher">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn action-btn btn-delete" 
                                                    title="Delete Teacher"
                                                    onclick="confirmDelete('<?php echo htmlspecialchars($teacher['teacher_id']); ?>', '<?php echo htmlspecialchars($teacher['full_name']); ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </small>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fas fa-chalkboard-user fa-4x text-muted mb-3 d-block"></i>
                                        <h5>No Teachers Found</h5>
                                        <p class="text-muted">Click "Add New Teacher" to add your first teacher.</p>
                                    </div>
                                </small>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Teacher Modal -->
<div class="modal fade modal-modern" id="addTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Add New Teacher</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="return validateTeacherForm()">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-id-card me-1 text-primary"></i> Teacher ID *</label>
                            <input type="text" name="teacher_id" id="teacher_id" class="form-control text-uppercase" 
                                   placeholder="e.g., TCH001" required pattern="[A-Z0-9]+">
                            <small class="text-muted">Unique identifier (letters and numbers only)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-user me-1 text-primary"></i> Username *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-user-circle me-1 text-primary"></i> Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-envelope me-1 text-primary"></i> Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-key me-1 text-primary"></i> Password *</label>
                            <input type="text" name="password" class="form-control" value="teacher123" required>
                            <small class="text-muted">Default: teacher123</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-phone me-1 text-primary"></i> Phone Number</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+251-912-345678">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-graduation-cap me-1 text-primary"></i> Qualification</label>
                            <input type="text" name="qualification" class="form-control" placeholder="e.g., MSc, PhD, BEd">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-flask me-1 text-primary"></i> Specialization</label>
                            <input type="text" name="specialization" class="form-control" placeholder="e.g., Mathematics, English">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-building me-1 text-primary"></i> Department</label>
                            <select name="department" class="form-select">
                                <option value="">Select Department</option>
                                <option value="Science">Science</option>
                                <option value="Mathematics">Mathematics</option>
                                <option value="Language">Language</option>
                                <option value="Social Science">Social Science</option>
                                <option value="ICT">ICT</option>
                                <option value="General">General</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-calendar me-1 text-primary"></i> Join Date</label>
                            <input type="date" name="join_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label"><i class="fas fa-map-marker-alt me-1 text-primary"></i> Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Enter address"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Teacher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade modal-modern" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-user-times fa-4x text-danger mb-3 d-block"></i>
                <p>Are you sure you want to delete this teacher?</p>
                <p><strong id="deleteTeacherName"></strong></p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All teacher data including subject assignments and entered grades will be permanently deleted.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Yes, Delete Teacher</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#teachersTable').DataTable({
        pageLength: 15,
        responsive: true,
        order: [[0, 'asc']],
        language: {
            search: "<i class='fas fa-search me-1'></i> Search:",
            searchPlaceholder: "Type to search...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ teachers",
            infoEmpty: "Showing 0 to 0 of 0 teachers",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                previous: "<i class='fas fa-chevron-left'></i>",
                next: "<i class='fas fa-chevron-right'></i>"
            }
        },
        columnDefs: [
            { orderable: false, targets: [6] }
        ]
    });
});

function validateTeacherForm() {
    const teacherId = document.getElementById('teacher_id');
    const pattern = /^[A-Z0-9]+$/;
    if (!pattern.test(teacherId.value)) {
        alert('Teacher ID must contain only uppercase letters and numbers!');
        teacherId.focus();
        return false;
    }
    return true;
}

function confirmDelete(teacherId, teacherName) {
    $('#deleteTeacherName').html('<strong>' + teacherId + ' - ' + teacherName + '</strong>');
    $('#confirmDeleteBtn').attr('href', '?delete=' + encodeURIComponent(teacherId));
    $('#deleteModal').modal('show');
}

// Auto uppercase Teacher ID
const teacherIdInput = document.getElementById('teacher_id');
if (teacherIdInput) {
    teacherIdInput.addEventListener('input', function(e) {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });
}
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<?php require_once '../includes/footer.php'; ?>