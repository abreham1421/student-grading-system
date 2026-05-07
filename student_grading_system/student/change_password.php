<?php
// student/change_password.php - Professional Password Change Page
$pageTitle = 'Change Password';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();
$message = '';
$error = '';

// Get student info for display
$username = $_SESSION['username'] ?? '';
$studentInfo = [];
if (!empty($username)) {
    $studentQuery = $conn->query("
        SELECT s.*, u.full_name 
        FROM student s 
        JOIN users u ON s.username = u.username 
        WHERE s.username = '$username'
    ");
    if ($studentQuery && $studentQuery->num_rows > 0) {
        $studentInfo = $studentQuery->fetch_assoc();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Verify current password using username
        $result = $conn->query("SELECT password FROM users WHERE username = '$username'");
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
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
        } else {
            $error = "User not found.";
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

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.animate-in {
    animation: fadeInUp 0.5s ease-out;
}

.shake {
    animation: shake 0.3s ease-in-out;
}

/* Password Card */
.password-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: all 0.3s ease;
}

.password-card-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    padding: 25px 30px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.password-card-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
}

.password-icon {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    border: 3px solid rgba(255,255,255,0.3);
}

.password-card-body {
    padding: 30px;
}

/* Form Fields */
.form-group {
    margin-bottom: 20px;
}

.form-label {
    font-size: 13px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
    display: block;
}

.input-group-custom {
    position: relative;
}

.input-group-custom input {
    width: 100%;
    padding: 12px 15px;
    padding-right: 45px;
    border: 2px solid var(--gray-200);
    border-radius: 12px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
}

.input-group-custom input:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(30,60,114,0.1);
}

.input-group-custom input.is-invalid {
    border-color: var(--danger);
    background: #fff0f0;
}

.toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #6c757d;
    transition: color 0.3s ease;
}

.toggle-password:hover {
    color: var(--primary);
}

/* Password Strength Meter */
.strength-meter {
    margin-top: 8px;
}

.strength-bar {
    height: 4px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
}

.strength-progress {
    width: 0%;
    height: 100%;
    transition: width 0.3s ease;
}

.strength-text {
    font-size: 10px;
    margin-top: 5px;
    display: inline-block;
}

.strength-weak { background: var(--danger); width: 25%; }
.strength-fair { background: var(--warning); width: 50%; }
.strength-good { background: var(--info); width: 75%; }
.strength-strong { background: var(--success); width: 100%; }

.text-weak { color: var(--danger); }
.text-fair { color: var(--warning); }
.text-good { color: var(--info); }
.text-strong { color: var(--success); }

/* Button */
.btn-change-password {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border: none;
    padding: 12px 25px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    width: 100%;
    color: white;
}

.btn-change-password:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(30,60,114,0.4);
}

/* Info Box */
.info-box {
    background: linear-gradient(135deg, #e8f0fe, #d4e4fc);
    border-radius: 16px;
    padding: 15px;
    margin-top: 20px;
    border-left: 4px solid var(--primary);
}

/* Responsive */
@media (max-width: 768px) {
    .password-card-body {
        padding: 20px;
    }
    .password-icon {
        width: 60px;
        height: 60px;
    }
    .password-icon i {
        font-size: 24px !important;
    }
}
</style>

<div class="container-fluid animate-in">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-key me-2" style="color: var(--primary);"></i>
                <span style="background: linear-gradient(135deg, var(--primary), var(--primary-light)); -webkit-background-clip: text; background-clip: text; color: transparent;">Change Password</span>
            </h1>
            <p class="text-muted small mt-1 mb-0">Update your account password for better security</p>
        </div>
        <div>
            <a href="profile.php" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> Back to Profile
            </a>
        </div>
    </div>
    
    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="password-card">
                <div class="password-card-header">
                    <div class="password-icon">
                        <i class="fas fa-lock fa-3x text-white"></i>
                    </div>
                    <h4 class="text-white mb-1">Security Settings</h4>
                    <p class="text-white-50 small mb-0">Protect your account with a strong password</p>
                </div>
                <div class="password-card-body">
                    <form method="POST" id="passwordForm" onsubmit="return validateForm()">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-shield-alt me-1"></i> Current Password
                            </label>
                            <div class="input-group-custom">
                                <input type="password" name="current_password" id="current_password" 
                                       class="form-control" required placeholder="Enter your current password">
                                <span class="toggle-password" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-plus-circle me-1"></i> New Password
                            </label>
                            <div class="input-group-custom">
                                <input type="password" name="new_password" id="new_password" 
                                       class="form-control" required placeholder="Enter new password"
                                       onkeyup="checkPasswordStrength()">
                                <span class="toggle-password" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div class="strength-meter">
                                <div class="strength-bar">
                                    <div class="strength-progress" id="strengthProgress"></div>
                                </div>
                                <span class="strength-text" id="strengthText"></span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-check-circle me-1"></i> Confirm New Password
                            </label>
                            <div class="input-group-custom">
                                <input type="password" name="confirm_password" id="confirm_password" 
                                       class="form-control" required placeholder="Confirm your new password">
                                <span class="toggle-password" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div id="matchMessage" class="small mt-1"></div>
                        </div>
                        
                        <button type="submit" class="btn-change-password">
                            <i class="fas fa-save me-2"></i> Change Password
                        </button>
                    </form>
                    
                    <div class="info-box">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-info-circle fa-2x text-primary"></i>
                            </div>
                            <div>
                                <strong class="small">Password Requirements:</strong>
                                <ul class="small mb-0 mt-1 ps-3">
                                    <li>Minimum 6 characters long</li>
                                    <li>Include both letters and numbers</li>
                                    <li>Avoid common passwords</li>
                                    <li>Don't share your password with anyone</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const toggleIcon = field.nextElementSibling.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Check password strength
function checkPasswordStrength() {
    const password = document.getElementById('new_password').value;
    const strengthProgress = document.getElementById('strengthProgress');
    const strengthText = document.getElementById('strengthText');
    
    let strength = 0;
    let message = '';
    let classname = '';
    
    if (password.length > 0) {
        // Length check
        if (password.length >= 8) strength += 25;
        else if (password.length >= 6) strength += 15;
        
        // Contains number
        if (/\d/.test(password)) strength += 25;
        
        // Contains uppercase
        if (/[A-Z]/.test(password)) strength += 25;
        
        // Contains special character
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength += 25;
        
        // Determine strength level
        if (strength >= 80) {
            message = 'Strong Password';
            classname = 'text-strong';
        } else if (strength >= 60) {
            message = 'Good Password';
            classname = 'text-good';
        } else if (strength >= 40) {
            message = 'Fair Password';
            classname = 'text-fair';
        } else {
            message = 'Weak Password';
            classname = 'text-weak';
        }
        
        strengthProgress.style.width = strength + '%';
        strengthProgress.className = 'strength-progress';
        if (strength >= 80) strengthProgress.classList.add('strength-strong');
        else if (strength >= 60) strengthProgress.classList.add('strength-good');
        else if (strength >= 40) strengthProgress.classList.add('strength-fair');
        else strengthProgress.classList.add('strength-weak');
        
        strengthText.textContent = message;
        strengthText.className = 'strength-text ' + classname;
    } else {
        strengthProgress.style.width = '0%';
        strengthText.textContent = '';
    }
    
    // Check password match
    checkPasswordMatch();
}

// Check if passwords match
function checkPasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchMessage = document.getElementById('matchMessage');
    const confirmField = document.getElementById('confirm_password');
    
    if (confirmPassword.length > 0) {
        if (newPassword === confirmPassword) {
            matchMessage.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Passwords match';
            matchMessage.className = 'small mt-1 text-success';
            confirmField.classList.remove('is-invalid');
            confirmField.classList.add('is-valid');
        } else {
            matchMessage.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i> Passwords do not match';
            matchMessage.className = 'small mt-1 text-danger';
            confirmField.classList.remove('is-valid');
            confirmField.classList.add('is-invalid');
        }
    } else {
        matchMessage.innerHTML = '';
        confirmField.classList.remove('is-valid', 'is-invalid');
    }
}

// Form validation
function validateForm() {
    const currentPass = document.getElementById('current_password').value;
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    const matchMessage = document.getElementById('matchMessage');
    
    if (!currentPass || !newPass || !confirmPass) {
        alert('Please fill in all fields');
        return false;
    }
    
    if (newPass !== confirmPass) {
        alert('New passwords do not match!');
        document.getElementById('confirm_password').focus();
        return false;
    }
    
    if (newPass.length < 6) {
        alert('Password must be at least 6 characters long!');
        document.getElementById('new_password').focus();
        return false;
    }
    
    return true;
}

// Add event listener for confirm password field
document.getElementById('confirm_password').addEventListener('keyup', checkPasswordMatch);
document.getElementById('new_password').addEventListener('keyup', checkPasswordStrength);
</script>

<?php require_once '../includes/footer.php'; ?>