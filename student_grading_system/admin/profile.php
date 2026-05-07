<?php
// admin/profile.php - Updated for database schema (using username)
$pageTitle = 'My Profile';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$username = $_SESSION['username'] ?? '';
$message = '';
$error = '';

if (empty($username)) {
    echo '<div class="alert alert-danger">Admin not found. Please login again.</div>';
    require_once '../includes/footer.php';
    exit();
}

// Create uploads directory if not exists
$uploadDir = '../uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Get admin information
$adminInfo = [];
$adminQuery = $conn->query("SELECT u.* FROM users u WHERE u.username = '$username' AND u.role = 'admin'");
if ($adminQuery && $adminQuery->num_rows > 0) {
    $adminInfo = $adminQuery->fetch_assoc();
} else {
    echo '<div class="alert alert-danger">Admin information not found.</div>';
    require_once '../includes/footer.php';
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $new_username = trim($_POST['username'] ?? '');
    
    $errors = [];
    
    // Validate required fields
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($new_username)) {
        $errors[] = "Username is required.";
    }
    
    // Check if email already exists (excluding current user)
    $checkEmail = $conn->query("SELECT username FROM users WHERE email = '$email' AND username != '$username'");
    if ($checkEmail && $checkEmail->num_rows > 0) {
        $errors[] = "Email already exists for another user.";
    }
    
    // Check if username already exists (excluding current user)
    $checkUsername = $conn->query("SELECT username FROM users WHERE username = '$new_username' AND username != '$username'");
    if ($checkUsername && $checkUsername->num_rows > 0) {
        $errors[] = "Username already exists for another user.";
    }
    
    if (empty($errors)) {
        $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, username = ? WHERE username = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssssss", $full_name, $email, $phone, $address, $new_username, $username);
            if ($stmt->execute()) {
                $message = "Profile updated successfully!";
                // Update session variables if username changed
                if ($new_username !== $username) {
                    $_SESSION['username'] = $new_username;
                    $username = $new_username;
                }
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                // Refresh admin info
                $adminInfo['full_name'] = $full_name;
                $adminInfo['email'] = $email;
                $adminInfo['phone'] = $phone;
                $adminInfo['address'] = $address;
                $adminInfo['username'] = $new_username;
            } else {
                $error = "Error updating profile: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Error preparing statement: " . $conn->error;
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['profile_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $fileSize = $_FILES['profile_image']['size'];
        
        if (in_array($ext, $allowed)) {
            if ($fileSize <= 5 * 1024 * 1024) { // 5MB limit
                $newFilename = 'admin_' . $username . '_' . time() . '.' . $ext;
                $destination = $uploadDir . $newFilename;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                    // Delete old image if exists
                    if (!empty($adminInfo['profile_image']) && file_exists($uploadDir . $adminInfo['profile_image'])) {
                        unlink($uploadDir . $adminInfo['profile_image']);
                    }
                    
                    $sql = "UPDATE users SET profile_image = ? WHERE username = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ss", $newFilename, $username);
                        if ($stmt->execute()) {
                            $message = "Profile picture updated successfully!";
                            $adminInfo['profile_image'] = $newFilename;
                        } else {
                            $error = "Error updating profile picture: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                } else {
                    $error = "Error uploading file.";
                }
            } else {
                $error = "File size too large. Maximum 5MB allowed.";
            }
        } else {
            $error = "File type not allowed. Allowed: JPG, JPEG, PNG, GIF, WEBP";
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

// Handle remove profile picture
if (isset($_GET['remove_picture'])) {
    if (!empty($adminInfo['profile_image']) && file_exists($uploadDir . $adminInfo['profile_image'])) {
        unlink($uploadDir . $adminInfo['profile_image']);
    }
    $conn->query("UPDATE users SET profile_image = NULL WHERE username = '$username'");
    $adminInfo['profile_image'] = null;
    $message = "Profile picture removed successfully!";
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
        $userCheck = $conn->query("SELECT password FROM users WHERE username = '$username'");
        if ($userCheck && $userCheck->num_rows > 0) {
            $user = $userCheck->fetch_assoc();
            if (password_verify($current_password, $user['password'])) {
                $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->query("UPDATE users SET password = '$new_hashed' WHERE username = '$username'");
                if ($update) {
                    $message = "Password changed successfully! Please login again with your new password.";
                } else {
                    $error = "Error updating password.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        }
    }
}

// Get system statistics for admin dashboard summary
$totalStudents = $conn->query("SELECT COUNT(*) as total FROM student WHERE student_status = 'active'")->fetch_assoc()['total'] ?? 0;
$totalTeachers = $conn->query("SELECT COUNT(*) as total FROM teacher WHERE status = 'active'")->fetch_assoc()['total'] ?? 0;
$totalSubjects = $conn->query("SELECT COUNT(*) as total FROM subject WHERE is_active = 1")->fetch_assoc()['total'] ?? 0;

// Get last login info
$lastLoginInfo = $conn->query("SELECT last_login, last_ip FROM users WHERE username = '$username'")->fetch_assoc();
?>

<style>
.profile-image {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border: 3px solid #1e3c72;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.profile-image-placeholder {
    width: 150px;
    height: 150px;
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
.stat-card-mini {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 10px;
    text-align: center;
    transition: transform 0.2s;
}
.stat-card-mini:hover {
    transform: translateY(-2px);
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="fas fa-user-shield me-2"></i> My Profile
        </h1>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Profile Picture Card -->
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-camera me-2"></i> Profile Picture
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($adminInfo['profile_image'])): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($adminInfo['profile_image']); ?>" 
                             alt="Profile Picture" 
                             class="rounded-circle profile-image mb-3">
                    <?php else: ?>
                        <div class="profile-image-placeholder rounded-circle mx-auto mb-3">
                            <i class="fas fa-user-shield fa-5x text-white"></i>
                        </div>
                    <?php endif; ?>
                    
                    <h5><?php echo htmlspecialchars($adminInfo['full_name']); ?></h5>
                    <p class="text-muted">System Administrator</p>
                    
                    <hr>
                    
                    <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#uploadPictureModal">
                        <i class="fas fa-upload me-2"></i> Change Profile Picture
                    </button>
                    
                    <?php if (!empty($adminInfo['profile_image'])): ?>
                        <a href="?remove_picture=1" class="btn btn-danger btn-sm w-100 mt-2" 
                           onclick="return confirm('Are you sure you want to remove your profile picture?')">
                            <i class="fas fa-trash me-2"></i> Remove Picture
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- System Summary Card -->
            <div class="card shadow mt-4">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-chart-line me-2"></i> System Summary
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="stat-card-mini">
                                <i class="fas fa-user-graduate fa-2x text-primary mb-2"></i>
                                <h4><?php echo $totalStudents; ?></h4>
                                <small class="text-muted">Students</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-card-mini">
                                <i class="fas fa-chalkboard-user fa-2x text-success mb-2"></i>
                                <h4><?php echo $totalTeachers; ?></h4>
                                <small class="text-muted">Teachers</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-card-mini">
                                <i class="fas fa-book fa-2x text-info mb-2"></i>
                                <h4><?php echo $totalSubjects; ?></h4>
                                <small class="text-muted">Subjects</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account Security Info -->
            <div class="card shadow mt-4">
                <div class="card-header bg-secondary text-white">
                    <i class="fas fa-shield-alt me-2"></i> Account Security
                </div>
                <div class="card-body">
                    <div class="small">
                        <p><strong><i class="fas fa-clock me-2"></i> Created:</strong><br>
                        <?php echo date('M d, Y H:i', strtotime($adminInfo['created_at'])); ?></p>
                        <p><strong><i class="fas fa-sign-in-alt me-2"></i> Last Login:</strong><br>
                        <?php echo $lastLoginInfo['last_login'] ? date('M d, Y H:i', strtotime($lastLoginInfo['last_login'])) : 'Not recorded'; ?></p>
                        <p><strong><i class="fas fa-network-wired me-2"></i> Last IP:</strong><br>
                        <?php echo $lastLoginInfo['last_ip'] ?? 'Not recorded'; ?></p>
                        <p><strong><i class="fas fa-user-tag me-2"></i> Role:</strong><br>
                        <span class="badge bg-primary">System Administrator</span></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Profile Information Form -->
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-edit me-2"></i> Edit Profile Information
                </div>
                <div class="card-body">
                    <form method="POST" id="profileForm">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($adminInfo['full_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" class="form-control" 
                                       value="<?php echo htmlspecialchars($adminInfo['username']); ?>" required>
                                <small class="text-muted">Used for login - changing this will affect future logins</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($adminInfo['email']); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($adminInfo['phone'] ?? ''); ?>">
                                <small class="text-muted">e.g., +251-912-345678</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="System Administrator" disabled>
                                <small class="text-muted">Role cannot be changed</small>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="2" 
                                          placeholder="Enter your address"><?php echo htmlspecialchars($adminInfo['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Note:</strong> Changing username or email will affect your login credentials. You will need to use the new username/email next time you login.
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Save All Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Password Change Card -->
            <div class="card shadow mt-4">
                <div class="card-header bg-warning text-dark">
                    <i class="fas fa-key me-2"></i> Change Password
                </div>
                <div class="card-body">
                    <form method="POST" onsubmit="return validatePasswordForm()">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" id="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-warning w-100">
                            <i class="fas fa-save me-2"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Quick Access Card -->
            <div class="card shadow mt-4">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-bolt me-2"></i> Quick Access
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4 mb-2">
                            <a href="manage_students.php" class="btn btn-outline-primary w-100 py-2">
                                <i class="fas fa-user-graduate d-block mb-1 fa-2x"></i>
                                <small>Students</small>
                            </a>
                        </div>
                        <div class="col-md-4 mb-2">
                            <a href="manage_teachers.php" class="btn btn-outline-success w-100 py-2">
                                <i class="fas fa-chalkboard-user d-block mb-1 fa-2x"></i>
                                <small>Teachers</small>
                            </a>
                        </div>
                        <div class="col-md-4 mb-2">
                            <a href="manage_subjects.php" class="btn btn-outline-info w-100 py-2">
                                <i class="fas fa-book d-block mb-1 fa-2x"></i>
                                <small>Subjects</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Picture Modal -->
<div class="modal fade" id="uploadPictureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-camera me-2"></i> Upload Profile Picture</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <?php if (!empty($adminInfo['profile_image'])): ?>
                            <img id="previewImage" src="../uploads/<?php echo htmlspecialchars($adminInfo['profile_image']); ?>" 
                                 alt="Preview" 
                                 class="rounded-circle"
                                 style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #1e3c72;">
                        <?php else: ?>
                            <div id="previewIcon" class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center mx-auto"
                                 style="width: 150px; height: 150px;">
                                <i class="fas fa-user-shield fa-5x text-white"></i>
                            </div>
                            <img id="previewImage" src="" style="display: none; width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 3px solid #1e3c72;">
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Image</label>
                        <input type="file" name="profile_image" id="profileImageInput" class="form-control" accept="image/*" required>
                        <small class="text-muted">Allowed formats: JPG, JPEG, PNG, GIF, WEBP. Max size: 5MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_picture" class="btn btn-primary">Upload Picture</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Image preview
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

// Password validation
function validatePasswordForm() {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass !== confirmPass) {
        alert('New passwords do not match!');
        return false;
    }
    
    if (newPass.length < 6) {
        alert('Password must be at least 6 characters long!');
        return false;
    }
    
    return true;
}

// Form change detection
let formChanged = false;
const formFields = document.querySelectorAll('#profileForm input, #profileForm textarea');
formFields.forEach(field => {
    field.addEventListener('change', () => { formChanged = true; });
    field.addEventListener('input', () => { formChanged = true; });
});

window.addEventListener('beforeunload', (e) => {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
    }
});

// Mark form as saved when submitted
document.getElementById('profileForm')?.addEventListener('submit', function() {
    formChanged = false;
});
</script>

<?php require_once '../includes/footer.php'; ?>