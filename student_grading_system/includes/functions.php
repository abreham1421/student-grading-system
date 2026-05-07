<?php
// includes/functions.php - Global helper functions

// Sanitization Functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function sanitizeArray($array) {
    return array_map('sanitizeInput', $array);
}

// Date Formatting
function formatDate($date, $format = 'M d, Y') {
    if (!$date || $date == '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'M d, Y H:i') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

// Grade Functions
function getLetterGrade($score) {
    if ($score >= 90) return ['letter' => 'A+', 'point' => 4.00, 'remark' => 'Excellent'];
    if ($score >= 85) return ['letter' => 'A', 'point' => 4.00, 'remark' => 'Excellent'];
    if ($score >= 80) return ['letter' => 'A-', 'point' => 3.75, 'remark' => 'Very Good'];
    if ($score >= 75) return ['letter' => 'B+', 'point' => 3.50, 'remark' => 'Good'];
    if ($score >= 70) return ['letter' => 'B', 'point' => 3.00, 'remark' => 'Good'];
    if ($score >= 65) return ['letter' => 'B-', 'point' => 2.75, 'remark' => 'Above Average'];
    if ($score >= 60) return ['letter' => 'C+', 'point' => 2.50, 'remark' => 'Average'];
    if ($score >= 55) return ['letter' => 'C', 'point' => 2.00, 'remark' => 'Average'];
    if ($score >= 50) return ['letter' => 'C-', 'point' => 1.75, 'remark' => 'Below Average'];
    if ($score >= 45) return ['letter' => 'D', 'point' => 1.00, 'remark' => 'Poor'];
    return ['letter' => 'F', 'point' => 0.00, 'remark' => 'Fail'];
}

function getLetterGradeLetter($score) {
    if ($score >= 90) return 'A+';
    if ($score >= 85) return 'A';
    if ($score >= 80) return 'A-';
    if ($score >= 75) return 'B+';
    if ($score >= 70) return 'B';
    if ($score >= 65) return 'B-';
    if ($score >= 60) return 'C+';
    if ($score >= 55) return 'C';
    if ($score >= 50) return 'C-';
    if ($score >= 45) return 'D';
    return 'F';
}

function getGradePoint($score) {
    if ($score >= 90) return 4.00;
    if ($score >= 85) return 4.00;
    if ($score >= 80) return 3.75;
    if ($score >= 75) return 3.50;
    if ($score >= 70) return 3.00;
    if ($score >= 65) return 2.75;
    if ($score >= 60) return 2.50;
    if ($score >= 55) return 2.00;
    if ($score >= 50) return 1.75;
    if ($score >= 45) return 1.00;
    return 0.00;
}

function calculateGPA($grades) {
    if (empty($grades)) return 0;
    $totalPoints = 0;
    $totalCredits = 0;
    foreach ($grades as $grade) {
        $totalPoints += $grade['point'] * $grade['credits'];
        $totalCredits += $grade['credits'];
    }
    return $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0;
}

// Academic Year Functions
function getCurrentAcademicYear() {
    $conn = db();
    $result = $conn->query("SELECT year_id, year_name FROM academic_year WHERE is_current = 1 LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return ['year_id' => 2, 'year_name' => '2025/26'];
}

function getAcademicYearById($year_id) {
    $conn = db();
    $result = $conn->query("SELECT year_name FROM academic_year WHERE year_id = $year_id");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['year_name'];
    }
    return '2025/26';
}

// Validation Functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[0-9+\-\s()]{10,20}$/', $phone);
}

// Role Functions
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Redirect Function
function redirect($url) {
    header("Location: " . APP_URL . "/" . $url);
    exit();
}

// Alert Function
function displayAlert($type, $message) {
    $icons = [
        'success' => 'check-circle',
        'error' => 'exclamation-circle',
        'warning' => 'exclamation-triangle',
        'info' => 'info-circle'
    ];
    $icon = $icons[$type] ?? 'info-circle';
    $alertClass = $type === 'error' ? 'danger' : $type;
    
    return '<div class="alert alert-' . $alertClass . ' alert-dismissible fade show" role="alert">
                <i class="fas fa-' . $icon . ' me-2"></i>
                ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}

// Pagination Function
function paginate($totalItems, $currentPage = 1, $itemsPerPage = 20) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'items_per_page' => $itemsPerPage,
        'total_items' => $totalItems,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'prev_page' => $currentPage - 1,
        'next_page' => $currentPage + 1
    ];
}
?>