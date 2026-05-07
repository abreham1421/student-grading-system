<?php
// teacher/profile.php - Fixed for simplified database
$pageTitle = 'My Profile';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$teacherId = $_SESSION['teacher_id'] ?? 0;
$message = '';
$error = '';

// Fallback: get teacher ID from username
if (!$teacherId && isset($_SESSION['username'])) {
    $result = $conn->query("SELECT teacher_id FROM teacher WHERE username = '{$_SESSION['username']}'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $teacherId = $row['teacher_id'];
        $_SESSION['teacher_id'] = $teacherId;
    }
}

if (!$teacherId) {
    echo '<div class="alert alert-danger">Teacher profile not found. Please contact administrator.</div>';
    require_once '../includes/footer.php';
    exit();
}

// Create uploads directory if not exists
$uploadDir = '../uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Get teacher information - using username instead of user_id
$teacherInfo = [];
$teacherQuery = $conn->query("SELECT t.*, u.full_name, u.email, u.phone, u.address, u.profile_image, u.username
                              FROM teacher t 
                              JOIN users u ON t.username = u.username 
                              WHERE t.teacher_id = '$teacherId'");
if ($teacherQuery && $teacherQuery->num_rows > 0) {
    $teacherInfo = $teacherQuery->fetch_assoc();
} else {
    echo '<div class="alert alert-danger">Teacher information not found.</div>';
    require_once '../includes/footer.php';
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $experience_years = (int)($_POST['experience_years'] ?? 0);
    
    $sql = "UPDATE users SET phone = ?, address = ? WHERE username = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sss", $phone, $address, $teacherInfo['username']);
        if ($stmt->execute()) {
            // Update teacher table
            $sql2 = "UPDATE teacher SET qualification = ?, specialization = ?, department = ?, experience_years = ? WHERE teacher_id = ?";
            $stmt2 = $conn->prepare($sql2);
            if ($stmt2) {
                $stmt2->bind_param("sssss", $qualification, $specialization, $department, $experience_years, $teacherId);
                $stmt2->execute();
                $stmt2->close();
            }
            $message = "Profile updated successfully!";
            
            // Refresh data
            $teacherQuery = $conn->query("SELECT t.*, u.full_name, u.email, u.phone, u.address, u.profile_image
                                          FROM teacher t 
                                          JOIN users u ON t.username = u.username 
                                          WHERE t.teacher_id = '$teacherId'");
            if ($teacherQuery && $teacherQuery->num_rows > 0) {
                $teacherInfo = $teacherQuery->fetch_assoc();
            }
        } else {
            $error = "Error updating profile: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Error preparing statement: " . $conn->error;
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $newFilename = 'teacher_' . $teacherId . '_' . time() . '.' . $ext;
            $destination = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                // Delete old image if exists
                if (!empty($teacherInfo['profile_image']) && file_exists($uploadDir . $teacherInfo['profile_image'])) {
                    unlink($uploadDir . $teacherInfo['profile_image']);
                }
                
                $sql = "UPDATE users SET profile_image = ? WHERE username = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("ss", $newFilename, $teacherInfo['username']);
                    if ($stmt->execute()) {
                        $message = "Profile picture updated successfully!";
                        $teacherInfo['profile_image'] = $newFilename;
                    } else {
                        $error = "Error updating profile picture: " . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                $error = "Error uploading file.";
            }
        } else {
            $error = "File type not allowed. Allowed: JPG, JPEG, PNG, GIF";
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Verify current password
        $userCheck = $conn->query("SELECT password FROM users WHERE username = '{$teacherInfo['username']}'");
        if ($userCheck && $userCheck->num_rows > 0) {
            $user = $userCheck->fetch_assoc();
            if (password_verify($current_password, $user['password'])) {
                $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->query("UPDATE users SET password = '$new_hashed' WHERE username = '{$teacherInfo['username']}'");
                if ($update) {
                    $message = "Password changed successfully!";
                } else {
                    $error = "Error updating password.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        }
    }
}

// Get assigned subjects count
$assignedSubjectsCount = $conn->query("SELECT COUNT(*) as total FROM teacher_subject WHERE teacher_id = '$teacherId'")->fetch_assoc()['total'] ?? 0;
?>

<style>
.profile-header {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    border-radius: 20px;
    padding: 20px 25px;
    margin-bottom: 25px;
    color: white;
}
.info-card {
    background: white;
    border-radius: 12px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.info-value {
    font-size: 24px;
    font-weight: 700;
}
.info-label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
}
@media (max-width: 768px) {
    .info-card { margin-bottom: 10px; }
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="profile-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h4 class="mb-1 fw-bold">
                    <i class="fas fa-user-circle me-2"></i> My Profile
                </h4>
                <p class="mb-0 small opacity-75">View and manage your profile information</p>
            </div>
            <div class="mt-2 mt-sm-0">
                <a href="dashboard.php" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Left Column -->
        <div class="col-md-4">
            <!-- Profile Picture Card -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white py-2">
                    <i class="fas fa-camera me-2"></i> Profile Picture
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($teacherInfo['profile_image'])): ?>
                        <img src="../uploads/<?php echo $teacherInfo['profile_image']; ?>" 
                             alt="Profile Picture" 
                             class="rounded-circle mb-3"
                             style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #1e3c72;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center mb-3"
                             style="width: 120px; height: 120px;">
                            <i class="fas fa-chalkboard-user fa-4x text-white"></i>
                        </div>
                    <?php endif; ?>
                    
                    <h5 class="mb-1"><?php echo htmlspecialchars($teacherInfo['full_name']); ?></h5>
                    <p class="text-muted small"><?php echo htmlspecialchars($teacherInfo['teacher_id']); ?></p>
                    
                    <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#uploadPictureModal">
                        <i class="fas fa-upload me-1"></i> Change Photo
                    </button>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-2 mb-4">
                <div class="col-6">
                    <div class="info-card">
                        <div class="info-value text-primary"><?php echo $assignedSubjectsCount; ?></div>
                        <div class="info-label">Subjects</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="info-card">
                        <div class="info-value text-info"><?php echo $teacherInfo['experience_years'] ?? 0; ?></div>
                        <div class="info-label">Years Exp</div>
                    </div>
                </div>
            </div>
            
            <!-- Password Change -->
            <div class="card shadow">
                <div class="card-header bg-warning text-white py-2">
                    <i class="fas fa-key me-2"></i> Change Password
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-2">
                            <label class="form-label small">Current Password</label>
                            <input type="password" name="current_password" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">New Password</label>
                            <input type="password" name="new_password" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control form-control-sm" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-warning btn-sm w-100">
                            <i class="fas fa-save me-1"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="col-md-8">
            <!-- Profile Information -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white py-2">
                    <i class="fas fa-edit me-2"></i> Profile Information
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-12 mb-2">
                                <label class="form-label small">Full Name</label>
                                <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($teacherInfo['full_name']); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small">Email</label>
                                <input type="email" class="form-control form-control-sm" value="<?php echo htmlspecialchars($teacherInfo['email']); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small">Username</label>
                                <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($teacherInfo['username']); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small">Phone Number</label>
                                <input type="tel" name="phone" class="form-control form-control-sm" value="<?php echo htmlspecialchars($teacherInfo['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small">Teacher ID</label>
                                <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($teacherInfo['teacher_id']); ?>" disabled>
                            </div>
                            <div class="col-md-12 mb-2">
                                <label class="form-label small">Address</label>
                                <textarea name="address" class="form-control form-control-sm" rows="2"><?php echo htmlspecialchars($teacherInfo['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <h6 class="mt-3 mb-2">Professional Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label small">Qualification</label>
                                <input type="text" name="qualification" class="form-control form-control-sm" value="<?php echo htmlspecialchars($teacherInfo['qualification'] ?? ''); ?>">
                                <small class="text-muted d-block">e.g., MSc, PhD, BEd</small>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small">Specialization</label>
                                <input type="text" name="specialization" class="form-control form-control-sm" value="<?php echo htmlspecialchars($teacherInfo['specialization'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small">Department</label>
                                <select name="department" class="form-select form-select-sm">
                                    <option value="">Select Department</option>
                                    <option value="Science" <?php echo ($teacherInfo['department'] ?? '') == 'Science' ? 'selected' : ''; ?>>Science</option>
                                    <option value="Mathematics" <?php echo ($teacherInfo['department'] ?? '') == 'Mathematics' ? 'selected' : ''; ?>>Mathematics</option>
                                    <option value="Language" <?php echo ($teacherInfo['department'] ?? '') == 'Language' ? 'selected' : ''; ?>>Language</option>
                                    <option value="Social Science" <?php echo ($teacherInfo['department'] ?? '') == 'Social Science' ? 'selected' : ''; ?>>Social Science</option>
                                    <option value="ICT" <?php echo ($teacherInfo['department'] ?? '') == 'ICT' ? 'selected' : ''; ?>>ICT</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small">Experience Years</label>
                                <input type="number" name="experience_years" class="form-control form-control-sm" value="<?php echo $teacherInfo['experience_years'] ?? 0; ?>">
                            </div>
                        </div>
                        
                        <div class="text-end mt-3">
                            <button type="submit" name="update_profile" class="btn btn-primary btn-sm">
                                <i class="fas fa-save me-1"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Assigned Subjects -->
            <div class="card shadow">
                <div class="card-header bg-info text-white py-2">
                    <i class="fas fa-book me-2"></i> Assigned Subjects
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject ID</th>
                                    <th>Subject Name</th>
                                    <th class="text-center">Credits</th>
                                    <th class="text-center">Students</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $assignedSubjects = $conn->query("SELECT s.subject_id, s.subject_name, s.credits 
                                                                   FROM teacher_subject ts
                                                                   JOIN subject s ON ts.subject_id = s.subject_id
                                                                   WHERE ts.teacher_id = '$teacherId'");
                                if ($assignedSubjects && $assignedSubjects->num_rows > 0):
                                    while ($subject = $assignedSubjects->fetch_assoc()):
                                        $studentCount = $conn->query("SELECT COUNT(DISTINCT student_id) as total 
                                                                       FROM student_subject 
                                                                       WHERE subject_id = '{$subject['subject_id']}' AND is_active = 1")->fetch_assoc()['total'] ?? 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($subject['subject_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                        <td class="text-center"><?php echo $subject['credits']; ?></td>
                                        <td class="text-center"><span class="badge bg-primary"><?php echo $studentCount; ?></span></td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No subjects assigned</small>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Picture Modal -->
<div class="modal fade" id="uploadPictureModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-camera me-2"></i> Upload Photo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <?php if (!empty($teacherInfo['profile_image'])): ?>
                            <img id="previewImage" src="../uploads/<?php echo $teacherInfo['profile_image']; ?>" 
                                 style="width: 120px; height: 120px; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <div id="previewIcon" class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center"
                                 style="width: 120px; height: 120px;">
                                <i class="fas fa-chalkboard-user fa-4x text-white"></i>
                            </div>
                            <img id="previewImage" src="" style="display: none; width: 120px; height: 120px; object-fit: cover; border-radius: 50%;">
                        <?php endif; ?>
                    </div>
                    <input type="file" name="profile_image" id="profileImageInput" class="form-control form-control-sm" accept="image/*" required>
                    <small class="text-muted d-block mt-1">JPG, PNG, GIF (Max 5MB)</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_picture" class="btn btn-primary btn-sm">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('profileImageInput')?.addEventListener('change', function(e) {
    const reader = new FileReader();
    reader.onload = function(event) {
        const previewImage = document.getElementById('previewImage');
        const previewIcon = document.getElementById('previewIcon');
        if (previewImage) {
            previewImage.style.display = 'block';
            previewImage.src = event.target.result;
        }
        if (previewIcon) {
            previewIcon.style.display = 'none';
        }
    };
    if (e.target.files[0]) {
        reader.readAsDataURL(e.target.files[0]);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>