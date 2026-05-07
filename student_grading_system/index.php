<?php
// index.php - Login page (Role removed - detected by session)
$skipAuth = true;
require_once 'includes/init.php';

$error = isset($_GET['error']) ? $_GET['error'] : null;
$timeout = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e3c72;
            --primary-light: #2a5298;
            --primary-dark: #152c52;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2000 2000"><path fill="rgba(255,255,255,0.05)" d="M0,0 L2000,0 L2000,2000 L0,2000 Z M500,500 L1500,500 L1500,1500 L500,1500 Z M1000,1000 L1000,1000"/></svg>') repeat;
            opacity: 0.3;
            pointer-events: none;
        }

        /* Floating shapes animation */
        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }

        .shape {
            position: absolute;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
            animation: float 20s infinite ease-in-out;
        }

        .shape:nth-child(1) { width: 100px; height: 100px; top: 10%; left: 5%; animation-duration: 25s; }
        .shape:nth-child(2) { width: 150px; height: 150px; bottom: 15%; right: 8%; animation-duration: 30s; }
        .shape:nth-child(3) { width: 80px; height: 80px; top: 30%; right: 15%; animation-duration: 20s; }
        .shape:nth-child(4) { width: 120px; height: 120px; bottom: 25%; left: 10%; animation-duration: 28s; }
        .shape:nth-child(5) { width: 60px; height: 60px; top: 60%; left: 20%; animation-duration: 22s; }
        .shape:nth-child(6) { width: 200px; height: 200px; top: 70%; right: 25%; animation-duration: 35s; }
        .shape:nth-child(7) { width: 90px; height: 90px; top: 20%; right: 35%; animation-duration: 18s; }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-50px) rotate(180deg); }
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 30px;
            box-shadow: 0 35px 60px -15px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 460px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 45px 70px -20px rgba(0,0,0,0.35);
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            text-align: center;
            padding: 45px 30px;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            animation: pulse 8s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .school-icon {
            width: 90px;
            height: 90px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 4px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }

        .school-icon:hover {
            transform: scale(1.05);
        }

        .school-icon i {
            font-size: 45px;
        }

        .login-header h2 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .login-body {
            padding: 40px 35px;
            background: white;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            display: block;
            transition: color 0.3s ease;
        }

        .form-group label i {
            margin-right: 10px;
            color: var(--primary);
            font-size: 14px;
        }

        .input-group-custom {
            position: relative;
        }

        .input-group-custom input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e9ecef;
            border-radius: 14px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .input-group-custom input:hover {
            border-color: var(--primary-light);
            background: white;
        }

        .input-group-custom input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(30,60,114,0.1);
            background: white;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #adb5bd;
            transition: color 0.3s ease;
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: none;
            padding: 15px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(30,60,114,0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert-custom {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            font-size: 13px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger-custom {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-left: 4px solid var(--danger);
            color: #991b1b;
        }

        .alert-warning-custom {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-left: 4px solid var(--warning);
            color: #92400e;
        }

        .login-footer {
            background: linear-gradient(135deg, #f8f9fa, #fff);
            padding: 20px 35px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }

        .login-footer p {
            font-size: 12px;
            color: #6c757d;
            margin: 0;
        }

        .login-footer p:first-child {
            margin-bottom: 5px;
        }

        @media (max-width: 480px) {
            .login-card {
                max-width: 100%;
                margin: 0 15px;
            }
            .login-header {
                padding: 35px 20px;
            }
            .login-body {
                padding: 30px 20px;
            }
            .login-footer {
                padding: 15px 20px;
            }
            .school-icon {
                width: 70px;
                height: 70px;
            }
            .school-icon i {
                font-size: 35px;
            }
            .login-header h2 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated floating shapes -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="school-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h2>Gada Secondary School</h2>
                <p>Student Grading System</p>
            </div>

            <div class="login-body">
                <?php if ($error == 1): ?>
                    <div class="alert-custom alert-danger-custom">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Login Failed!</strong> Invalid username or password.
                    </div>
                <?php endif; ?>
                
                <?php if ($timeout): ?>
                    <div class="alert-custom alert-warning-custom">
                        <i class="fas fa-clock me-2"></i>
                        <strong>Session Expired!</strong> Please login again.
                    </div>
                <?php endif; ?>

                <form id="loginForm" action="authenticate.php" method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username</label>
                        <div class="input-group-custom">
                            <input type="text" name="username" id="username" 
                                   placeholder="Enter your username" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <div class="input-group-custom" style="position: relative;">
                            <input type="password" name="password" id="password" 
                                   placeholder="Enter your password" required>
                            <span class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
            </div>

            <div class="login-footer">
                <p><i class="fas fa-copyright"></i> <?php echo date('Y'); ?> Gada Secondary School</p>
                <p><small>Jimma University - CBTP Phase III | Grading System v3.0</small></p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Add input focus animation
        document.querySelectorAll('.input-group-custom input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.01)';
            });
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Enter key support
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>