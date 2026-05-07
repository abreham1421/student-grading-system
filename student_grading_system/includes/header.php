<?php
// includes/header.php
require_once __DIR__ . '/init.php';

// Check authentication (skip if $skipAuth is set)
if (!isset($skipAuth)) {
    requireLogin();
}

$currentUser = currentUser();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="description" content="<?php echo APP_NAME; ?> - School Management System">
    <title><?php echo APP_NAME; ?> - <?php echo $pageTitle ?? 'Dashboard'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
    <style>
        :root {
            --primary: #1e3c72;
            --secondary: #2a5298;
            --sidebar-w: 260px;
            --navbar-h: 58px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f4f7fc;
            overflow-x: hidden;
        }

        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 2px 12px rgba(0,0,0,.15);
            height: var(--navbar-h);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
        }

        .navbar-brand {
            font-weight: 700;
            color: #fff !important;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .btn-logout {
            background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.25);
            color: #fff;
            padding: 6px 14px;
            border-radius: 8px;
            transition: background .25s;
        }

        .btn-logout:hover {
            background: rgba(255,255,255,.3);
            color: #fff;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: var(--navbar-h);
            left: 0;
            bottom: 0;
            width: var(--sidebar-w);
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            overflow-y: auto;
            z-index: 1020;
            transition: transform .3s ease;
        }

        .sidebar-section-title {
            font-size: .65rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(255,255,255,.4);
            padding: 18px 20px 4px;
            font-weight: 600;
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,.82);
            padding: 10px 20px;
            margin: 2px 10px;
            border-radius: 10px;
            transition: all .25s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,.12);
            color: #fff;
        }

        /* Sidebar Toggle */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.3rem;
            cursor: pointer;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 1015;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-w);
            margin-top: var(--navbar-h);
            padding: 24px;
            min-height: calc(100vh - var(--navbar-h));
            transition: margin-left .3s ease;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,.07);
            margin-bottom: 22px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary)) !important;
            border-radius: 14px 14px 0 0 !important;
            padding: 14px 20px;
            font-weight: 600;
            color: #fff;
            border: none;
        }

        /* Tables */
        .table thead th {
            background: var(--primary);
            color: #fff;
            font-weight: 600;
            padding: 11px 14px;
        }

        /* Responsive */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(calc(-1 * var(--sidebar-w)));
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar-toggle {
                display: inline-flex;
            }
        }

        @media print {
            .sidebar, .navbar, .btn, .no-print {
                display: none !important;
            }
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>

<nav class="navbar px-3">
    <div class="d-flex align-items-center">
        <button class="sidebar-toggle me-3" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <a class="navbar-brand" href="<?php echo APP_URL; ?>/<?php echo $currentUser['role']; ?>/dashboard.php">
            <i class="fas fa-graduation-cap"></i>
            <span><?php echo APP_NAME; ?></span>
        </a>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2">
        <span class="badge bg-light text-primary d-none d-md-inline-flex align-items-center gap-1">
            <i class="fas fa-calendar-alt"></i>
            <?php echo CURRENT_ACADEMIC_YEAR; ?>
        </span>

        <div class="dropdown">
            <button class="btn btn-logout dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle"></i>
                <span class="d-none d-sm-inline"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <span class="dropdown-item-text text-muted small px-3 py-1">
                        <i class="fas fa-shield-alt me-1"></i>
                        <?php echo ucfirst($currentUser['role']); ?>
                    </span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/<?php echo $currentUser['role']; ?>/profile.php">
                        <i class="fas fa-user me-2"></i> My Profile
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <nav class="nav flex-column py-3">
        <?php if ($currentUser['role'] === 'admin'): ?>
            <span class="sidebar-section-title">Main</span>
            <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <span class="sidebar-section-title">Management</span>
            <a class="nav-link <?php echo $current_page === 'manage_students.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin/manage_students.php">
                <i class="fas fa-user-graduate"></i> Students
            </a>
            <a class="nav-link <?php echo $current_page === 'manage_teachers.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin/manage_teachers.php">
                <i class="fas fa-chalkboard-user"></i> Teachers
            </a>
            <a class="nav-link <?php echo $current_page === 'manage_subjects.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin/manage_subjects.php">
                <i class="fas fa-book"></i> Subjects
            </a>
            <span class="sidebar-section-title">Academics</span>
            <a class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin/reports.php">
                <i class="fas fa-file-alt"></i> Reports
            </a>
            <span class="sidebar-section-title">Account</span>
            <a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin/profile.php">
                <i class="fas fa-user-circle"></i> My Profile
            </a>

        <?php elseif ($currentUser['role'] === 'teacher'): ?>
            <span class="sidebar-section-title">Main</span>
            <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/teacher/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <span class="sidebar-section-title">Teaching</span>
            <a class="nav-link <?php echo $current_page === 'enter_grades.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/teacher/enter_grades.php">
                <i class="fas fa-edit"></i> Enter Grades
            </a>
            <a class="nav-link <?php echo $current_page === 'class_performance.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/teacher/class_performance.php">
                <i class="fas fa-chart-bar"></i> Class Performance
            </a>
            <span class="sidebar-section-title">Account</span>
            <a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/teacher/profile.php">
                <i class="fas fa-user-circle"></i> My Profile
            </a>

        <?php elseif ($currentUser['role'] === 'student'): ?>
            <span class="sidebar-section-title">Main</span>
            <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/student/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <span class="sidebar-section-title">Academics</span>
            <a class="nav-link <?php echo $current_page === 'view_results.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/student/view_results.php">
                <i class="fas fa-chart-line"></i> My Results
            </a>
            <a class="nav-link <?php echo $current_page === 'download_report.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/student/download_report.php">
                <i class="fas fa-file-download"></i> Download Report
            </a>
            <span class="sidebar-section-title">Account</span>
            <a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/student/profile.php">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a class="nav-link <?php echo $current_page === 'change_password.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/student/change_password.php">
                <i class="fas fa-key"></i> Change Password
            </a>
        <?php endif; ?>
    </nav>
</div>

<div class="main-content" id="mainContent">