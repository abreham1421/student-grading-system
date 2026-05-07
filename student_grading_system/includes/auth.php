<?php
// includes/auth.php - Authentication system

class Auth {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    public function login($username, $password) {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }
        
        if (!$this->conn) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        $stmt = $this->conn->prepare("SELECT username, password, role, full_name, is_active FROM users WHERE username = ?");
        
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        if ($user['is_active'] != 1) {
            return ['success' => false, 'message' => 'Account is deactivated. Please contact administrator.'];
        }
        
        $selected_role = isset($_POST['role']) ? $_POST['role'] : '';
        
        if ($user['role'] !== $selected_role) {
            return ['success' => false, 'message' => 'Role mismatch! You are registered as ' . ucfirst($user['role']) . '.'];
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $updateStmt = $this->conn->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE username = ?");
        
        if ($updateStmt) {
            $updateStmt->bind_param("ss", $ip, $username);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        
        if ($user['role'] === 'student') {
            $profileQuery = $this->conn->query("SELECT student_id FROM student WHERE username = '" . $this->conn->real_escape_string($username) . "'");
            if ($profileQuery && $profileQuery->num_rows > 0) {
                $profile = $profileQuery->fetch_assoc();
                $_SESSION['profile_id'] = $profile['student_id'];
            }
        } elseif ($user['role'] === 'teacher') {
            $profileQuery = $this->conn->query("SELECT teacher_id FROM teacher WHERE username = '" . $this->conn->real_escape_string($username) . "'");
            if ($profileQuery && $profileQuery->num_rows > 0) {
                $profile = $profileQuery->fetch_assoc();
                $_SESSION['profile_id'] = $profile['teacher_id'];
            }
        } elseif ($user['role'] === 'admin') {
            $_SESSION['profile_id'] = $username;
        }
        
        return ['success' => true, 'role' => $user['role'], 'message' => 'Login successful'];
    }
    
    public function logout() {
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function currentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'username' => $_SESSION['username'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'role' => $_SESSION['role'] ?? '',
            'profile_id' => $_SESSION['profile_id'] ?? ''
        ];
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . APP_URL . '/index.php?timeout=1');
            exit();
        }
    }
    
    public function requireRole($role) {
        $this->requireLogin();
        if (isset($_SESSION['role']) && $_SESSION['role'] !== $role) {
            header('Location: ' . APP_URL . '/index.php?error=1');
            exit();
        }
    }
}

// Singleton instance
function auth() {
    static $instance = null;
    if ($instance === null) {
        $conn = db();
        $instance = new Auth($conn);
    }
    return $instance;
}

// Global helper functions
function requireLogin() {
    auth()->requireLogin();
}

function requireRole($role) {
    auth()->requireRole($role);
}

function currentUser() {
    return auth()->currentUser();
}

function isLoggedIn() {
    return auth()->isLoggedIn();
}
?>