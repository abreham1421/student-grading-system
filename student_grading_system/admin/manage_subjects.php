<?php
// admin/manage_subjects.php - Professional subject management
$pageTitle = 'Manage Subjects';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$message = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $subject_id = $conn->real_escape_string($_GET['delete']);
    
    // Check if subject has any associated data
    $hasEnrollments = $conn->query("SELECT COUNT(*) as count FROM student_subject WHERE subject_id = '$subject_id'")->fetch_assoc()['count'] ?? 0;
    $hasEnrollments = $hasEnrollments > 0;
    
    $hasGrades = $conn->query("SELECT COUNT(*) as count FROM mark WHERE subject_id = '$subject_id'")->fetch_assoc()['count'] ?? 0;
    $hasGrades = $hasGrades > 0;
    
    $hasTeacher = $conn->query("SELECT COUNT(*) as count FROM teacher_subject WHERE subject_id = '$subject_id'")->fetch_assoc()['count'] ?? 0;
    $hasTeacher = $hasTeacher > 0;
    
    if ($hasEnrollments || $hasGrades || $hasTeacher) {
        $error = "Cannot delete subject because it has associated data (enrollments, grades, or teacher assignments)!";
    } else {
        if ($conn->query("DELETE FROM subject WHERE subject_id = '$subject_id'")) {
            $message = "Subject deleted successfully!";
        } else {
            $error = "Error deleting subject: " . $conn->error;
        }
    }
}

// Handle Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $subject_id = strtoupper(trim($_POST['subject_id']));
    $subject_name = trim($_POST['subject_name']);
    $credits = (int)$_POST['credits'];
    $grade_level = $_POST['grade_level'];
    $subject_type = $_POST['subject_type'];
    $description = trim($_POST['description'] ?? '');
    
    // Validate required fields
    if (empty($subject_id) || empty($subject_name)) {
        $error = "Subject ID and Subject Name are required!";
    } elseif (!preg_match('/^[A-Z0-9]+$/', $subject_id)) {
        $error = "Subject ID must contain only uppercase letters and numbers!";
    } else {
        // Check if subject ID already exists
        $check = $conn->query("SELECT subject_id FROM subject WHERE subject_id = '$subject_id'");
        if ($check && $check->num_rows > 0) {
            $error = "Subject ID already exists!";
        } else {
            $sql = "INSERT INTO subject (subject_id, subject_name, credits, grade_level, subject_type, description, is_active) 
                    VALUES ('$subject_id', '$subject_name', $credits, '$grade_level', '$subject_type', " . ($description ? "'$description'" : "NULL") . ", 1)";
            
            if ($conn->query($sql)) {
                $message = "Subject added successfully!";
            } else {
                $error = "Error adding subject: " . $conn->error;
            }
        }
    }
}

// Get current academic year for teacher display
$yearResult = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
$currentYear = ($yearResult && $yearResult->num_rows > 0) ? $yearResult->fetch_assoc() : ['year_id' => 2, 'year_name' => '2025/26'];

// Get all subjects with statistics
$subjects = $conn->query("
    SELECT s.*, 
           (SELECT COUNT(DISTINCT student_id) FROM student_subject WHERE subject_id = s.subject_id AND is_active = 1) as student_count,
           (SELECT COUNT(*) FROM mark WHERE subject_id = s.subject_id) as grade_count,
           (SELECT u.full_name 
            FROM teacher_subject ts 
            JOIN teacher t ON ts.teacher_id = t.teacher_id 
            JOIN users u ON t.username = u.username 
            WHERE ts.subject_id = s.subject_id 
            AND ts.academic_year_id = {$currentYear['year_id']}
            AND ts.is_primary = 1 
            LIMIT 1) as teacher_name
    FROM subject s 
    ORDER BY s.subject_id
");

// Get counts by type
$coreCount = $conn->query("SELECT COUNT(*) as total FROM subject WHERE subject_type = 'core' AND is_active = 1")->fetch_assoc()['total'] ?? 0;
$electiveCount = $conn->query("SELECT COUNT(*) as total FROM subject WHERE subject_type = 'elective' AND is_active = 1")->fetch_assoc()['total'] ?? 0;
$optionalCount = $conn->query("SELECT COUNT(*) as total FROM subject WHERE subject_type = 'optional' AND is_active = 1")->fetch_assoc()['total'] ?? 0;
$totalSubjects = $coreCount + $electiveCount + $optionalCount;
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

.btn-add-subject {
    background: linear-gradient(135deg, #28a745, #20c997);
    border: none;
    padding: 12px 28px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.btn-add-subject:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

/* Modern Table Styles */
.subjects-card {
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

.subject-id-badge {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    background: #e8f0fe;
    color: var(--primary);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: inline-block;
}

.badge-credits {
    background: var(--info);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-core { background: linear-gradient(135deg, var(--success), #34ce57); color: white; }
.badge-elective { background: linear-gradient(135deg, var(--warning), #ffce3a); color: #212529; }
.badge-optional { background: linear-gradient(135deg, var(--info), #3dd5f3); color: white; }

.status-active { background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.status-inactive { background: #f8d7da; color: #721c24; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }

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

.empty-state {
    text-align: center;
    padding: 50px;
    color: #6c757d;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}
</style>

<div class="container-fluid">
    <!-- Attractive Page Header -->
    <div class="page-header-wrapper">
        <div class="page-header-content">
            <div class="page-title-section">
                <h1>
                    <i class="fas fa-book"></i>
                    Manage Subjects
                </h1>
                <p><i class="fas fa-chalkboard-user me-1"></i> Manage all academic subjects and their details</p>
            </div>
            <div class="header-stats">
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $totalSubjects; ?></div>
                    <div class="header-stat-label">Total Subjects</div>
                </div>
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $coreCount; ?></div>
                    <div class="header-stat-label">Core</div>
                </div>
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $electiveCount; ?></div>
                    <div class="header-stat-label">Elective</div>
                </div>
                <div class="header-stat-item">
                    <div class="header-stat-number"><?php echo $optionalCount; ?></div>
                    <div class="header-stat-label">Optional</div>
                </div>
            </div>
            <div>
                <button class="btn btn-add-subject" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    <i class="fas fa-plus-circle me-2"></i> Add New Subject
                </button>
            </div>
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
    
    <!-- Subjects Table -->
    <div class="card subjects-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-modern" id="subjectsTable">
                    <thead>
                        <tr>
                            <th>Subject ID</th>
                            <th>Subject Name</th>
                            <th class="text-center">Credits</th>
                            <th>Level</th>
                            <th>Type</th>
                            <th>Teacher</th>
                            <th class="text-center">Students</th>
                            <th class="text-center">Grades</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($subjects && $subjects->num_rows > 0): ?>
                            <?php while ($subject = $subjects->fetch_assoc()): 
                                $typeClass = '';
                                $typeLabel = ucfirst($subject['subject_type'] ?? 'Core');
                                if ($subject['subject_type'] == 'core') $typeClass = 'badge-core';
                                elseif ($subject['subject_type'] == 'elective') $typeClass = 'badge-elective';
                                else $typeClass = 'badge-optional';
                            ?>
                                <tr>
                                    <td>
                                        <span class="subject-id-badge">
                                            <i class="fas fa-tag me-1"></i>
                                            <?php echo htmlspecialchars($subject['subject_id']); ?>
                                        </span>
                                    </small>
                                    <td>
                                        <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                        <?php if (!empty($subject['description'])): ?>
                                            <i class="fas fa-info-circle text-muted ms-1" 
                                               title="<?php echo htmlspecialchars(substr($subject['description'], 0, 100)); ?>"></i>
                                        <?php endif; ?>
                                    </small>
                                    <td class="text-center">
                                        <span class="badge-credits"><?php echo $subject['credits']; ?> cr</span>
                                    </small>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($subject['grade_level'] ?? '9-12'); ?></span>
                                    </small>
                                    <td>
                                        <span class="badge <?php echo $typeClass; ?>"><?php echo $typeLabel; ?></span>
                                    </small>
                                    <td>
                                        <?php if ($subject['teacher_name']): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-chalkboard-user text-success me-1 small"></i>
                                                <span class="small"><?php echo htmlspecialchars($subject['teacher_name']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">Not assigned</span>
                                        <?php endif; ?>
                                    </small>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo number_format($subject['student_count'] ?? 0); ?></span>
                                    </small>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo number_format($subject['grade_count'] ?? 0); ?></span>
                                    </small>
                                    <td>
                                        <?php if (($subject['is_active'] ?? 1) == 1): ?>
                                            <span class="status-active"><i class="fas fa-circle me-1" style="font-size: 6px;"></i> Active</span>
                                        <?php else: ?>
                                            <span class="status-inactive"><i class="fas fa-circle me-1" style="font-size: 6px;"></i> Inactive</span>
                                        <?php endif; ?>
                                    </small>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="view_subject.php?id=<?php echo urlencode($subject['subject_id']); ?>" 
                                               class="btn action-btn btn-view" title="View Subject">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_subject.php?id=<?php echo urlencode($subject['subject_id']); ?>" 
                                               class="btn action-btn btn-edit" title="Edit Subject">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn action-btn btn-delete" 
                                                    title="Delete Subject"
                                                    onclick="confirmDelete('<?php echo htmlspecialchars($subject['subject_id']); ?>', '<?php echo htmlspecialchars($subject['subject_name']); ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </small>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-book-open"></i>
                                        <h5>No Subjects Found</h5>
                                        <p class="text-muted">Click "Add New Subject" to create your first subject.</p>
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

<!-- Add Subject Modal -->
<div class="modal fade modal-modern" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="return validateSubjectForm()">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject ID *</label>
                            <input type="text" name="subject_id" id="subject_id" class="form-control text-uppercase" 
                                   placeholder="e.g., MATH101" required pattern="[A-Z0-9]+">
                            <small class="text-muted">Unique identifier (letters and numbers only, no spaces)</small>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Subject Name *</label>
                            <input type="text" name="subject_name" class="form-control" 
                                   placeholder="e.g., Mathematics" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Credits</label>
                            <input type="number" name="credits" class="form-control" value="3" min="1" max="10" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Grade Level</label>
                            <select name="grade_level" class="form-select">
                                <option value="9-12">All Grades (9-12)</option>
                                <option value="9-10">Grades 9-10</option>
                                <option value="11-12">Grades 11-12</option>
                                <option value="9">Grade 9 Only</option>
                                <option value="10">Grade 10 Only</option>
                                <option value="11">Grade 11 Only</option>
                                <option value="12">Grade 12 Only</option>
                            </select>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Subject Type</label>
                            <select name="subject_type" class="form-select">
                                <option value="core">Core Subject (Required)</option>
                                <option value="elective">Elective Subject (Student choice)</option>
                                <option value="optional">Optional Subject (Additional)</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" 
                                      placeholder="Brief description of the subject content and objectives"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Subject</button>
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
                <i class="fas fa-trash-alt fa-4x text-danger mb-3 d-block"></i>
                <p>Are you sure you want to delete this subject?</p>
                <p><strong id="deleteSubjectName"></strong></p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. Subjects with enrollments or grades cannot be deleted.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Yes, Delete Subject</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#subjectsTable').DataTable({
        pageLength: 15,
        responsive: true,
        order: [[0, 'asc']],
        language: {
            search: "<i class='fas fa-search me-1'></i> Search:",
            searchPlaceholder: "Type to search...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ subjects",
            infoEmpty: "Showing 0 to 0 of 0 subjects",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                previous: "<i class='fas fa-chevron-left'></i>",
                next: "<i class='fas fa-chevron-right'></i>"
            }
        },
        columnDefs: [
            { orderable: false, targets: [9] }
        ]
    });
});

function validateSubjectForm() {
    const subjectId = document.getElementById('subject_id');
    const pattern = /^[A-Z0-9]+$/;
    if (!pattern.test(subjectId.value)) {
        alert('Subject ID must contain only uppercase letters and numbers!');
        subjectId.focus();
        return false;
    }
    return true;
}

function confirmDelete(subjectId, subjectName) {
    $('#deleteSubjectName').html('<strong>' + subjectId + ' - ' + subjectName + '</strong>');
    $('#confirmDeleteBtn').attr('href', '?delete=' + encodeURIComponent(subjectId));
    $('#deleteModal').modal('show');
}

// Auto uppercase subject ID
const subjectIdInput = document.getElementById('subject_id');
if (subjectIdInput) {
    subjectIdInput.addEventListener('input', function(e) {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });
}
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<?php require_once '../includes/footer.php'; ?>