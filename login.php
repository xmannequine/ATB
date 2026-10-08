<?php
// =============================================
// login.php - ATB System Login
// Automated Trash Bin System
// =============================================

require_once 'config/config.php';
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// If already logged in, go to dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$username = '';
$step = 'login';

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    
    if ($action === 'login') {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Security validation failed.';
            error_log('Login CSRF mismatch: session_has_token=' . (isset($_SESSION['csrf_token']) ? 'yes' : 'no') . ', session_id=' . session_id());
        } elseif (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            try {
                // Check both username AND email (case-insensitive)
                $stmt = $pdo->prepare("SELECT * FROM users WHERE (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)) AND is_active = 1");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];

                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $stmt->execute([$user['user_id']]);

                    if (function_exists('logActivity')) {
                        logActivity($user['user_id'], 'login', 'User logged in');
                    }

                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Invalid username/email or password.';
                }
            } catch(PDOException $e) {
                $error = 'An error occurred. Please try again.';
                error_log("Login error: " . $e->getMessage());
            }
        }
    }
}

$csrf_token = generateCSRFToken();
$flash = getFlash();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 (for layout) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ============================================================
           COLOR PALETTE (from original ATB System)
           Primary:    #8BC34A  (Sage Green)
           Secondary:  #C5E1A5  (Light Green)
           Accent:     #4CAF50  (Forest Green)
           Background: #F5F5F5  (Light Gray)
           Text:       #424242  (Charcoal Gray)
           ============================================================ */
        
        :root {
            --primary: #8BC34A;
            --primary-dark: #7CB342;
            --primary-light: #C5E1A5;
            --accent: #4CAF50;
            --accent-dark: #388E3C;
            --bg: #F5F5F5;
            --bg-white: #FFFFFF;
            --text: #424242;
            --text-light: #757575;
            --border: #E0E0E0;
            --shadow: rgba(66, 66, 66, 0.08);
            --card-bg: #FFFFFF;
            --input-bg: #F8F9FA;
            --success: #28a745;
            --danger: #dc3545;
        }
        
        /* Dark Mode (kept from original) */
        [data-theme="dark"] {
            --bg: #1a1a1a;
            --bg-white: #2d2d2d;
            --text: #E8E8E8;
            --text-light: #B0B0B0;
            --border: #404040;
            --shadow: rgba(0, 0, 0, 0.3);
            --card-bg: #2d2d2d;
            --input-bg: #3a3a3a;
        }
        
        * {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(145deg, var(--primary-dark) 0%, var(--primary) 50%, var(--primary-light) 100%);
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated Background Shapes */
        .bg-shape {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }
        .bg-shape .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            animation: float 20s infinite ease-in-out;
        }
        .shape-1 {
            width: 500px;
            height: 500px;
            top: -200px;
            right: -100px;
            animation-delay: 0s;
        }
        .shape-2 {
            width: 400px;
            height: 400px;
            bottom: -150px;
            left: -100px;
            animation-delay: -5s;
        }
        .shape-3 {
            width: 300px;
            height: 300px;
            bottom: 50%;
            right: 20%;
            animation-delay: -10s;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(10deg); }
        }
        
        /* Login Container (two-column) */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Left Panel - Branding */
        .brand-panel {
            background: linear-gradient(145deg, var(--primary-dark) 0%, var(--primary) 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .brand-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.08"><path d="M20 20 L80 20 L80 80 L20 80 Z" fill="none" stroke="white" stroke-width="2"/><circle cx="50" cy="50" r="20" fill="none" stroke="white" stroke-width="2"/></svg>') repeat;
            background-size: 50px 50px;
            animation: movePattern 30s linear infinite;
        }
        @keyframes movePattern {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(50px) translateY(50px); }
        }
        .brand-logo {
            position: relative;
            z-index: 10;
            margin-bottom: 30px;
        }
        .brand-logo img {
            width: 150px;
            height: 150px;
            border-radius: 30px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            object-fit: cover;
            background: white;
            padding: 5px;
            transition: transform 0.3s ease;
        }
        .brand-logo img:hover { transform: scale(1.05); }
        .brand-title {
            position: relative;
            z-index: 10;
        }
        .brand-title h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            line-height: 1.2;
        }
        .brand-title .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }
        .brand-title .system-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            padding: 8px 20px;
            border-radius: 50px;
            display: inline-block;
            font-size: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }
        
        /* Intro Message */
        .intro-message {
            position: relative;
            z-index: 10;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            border-radius: 20px;
            padding: 25px;
            margin: 20px 0;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.15);
            text-align: left;
        }
        .intro-message p {
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 15px;
            color: rgba(255,255,255,0.9);
        }
        .intro-message .highlight {
            color: white;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 6px;
        }
        .intro-message ul {
            list-style: none;
            padding: 0;
            margin: 15px 0 0;
        }
        .intro-message li {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 0.95rem;
            color: rgba(255,255,255,0.85);
        }
        .intro-message li i { color: white; font-size: 1rem; width: 20px; }
        
        /* Live Time Display */
        .time-display {
            position: relative;
            z-index: 10;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            border-radius: 20px;
            padding: 20px;
            margin-top: 20px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .time-display .date {
            font-size: 1rem;
            margin-bottom: 5px;
            opacity: 0.9;
        }
        .time-display .time {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 2px;
        }
        .time-display .timezone {
            font-size: 0.8rem;
            opacity: 0.7;
            margin-top: 5px;
        }
        
        /* Right Panel - Login Form */
        .login-panel {
            padding: 60px 50px;
            background: var(--bg-white);
        }
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .login-header h2 {
            color: var(--primary-dark);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .login-header p {
            color: var(--text-light);
            font-size: 0.95rem;
        }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-light);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .role-badge i { color: white; }
        
        /* Input Groups */
        .input-group-custom {
            margin-bottom: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: var(--input-bg);
        }
        .input-group-custom:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(139, 195, 74, 0.1);
        }
        .input-group-custom .input-group-text {
            background: transparent;
            border: none;
            padding: 0 20px;
            color: var(--primary);
            font-size: 1.2rem;
        }
        .input-group-custom .form-control {
            border: none;
            padding: 15px;
            font-size: 1rem;
            background: transparent;
            color: var(--text);
        }
        .input-group-custom .form-control:focus {
            outline: none;
            box-shadow: none;
            background: transparent;
        }
        .input-group-custom .form-control::placeholder {
            color: var(--text-light);
        }
        
        /* Password toggle */
        .password-field {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            z-index: 10;
        }
        .toggle-password:hover { color: var(--primary); }
        
        /* Remember & Forgot */
        .remember-forgot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 20px 0;
        }
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--text);
        }
        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
        }
        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .forgot-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .security-notice {
            background: rgba(139, 195, 74, 0.08);
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            color: var(--text);
        }
        .security-notice i { color: var(--primary); font-size: 1.2rem; }
        
        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(145deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 16px;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            cursor: pointer;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(139, 195, 74, 0.3);
        }
        .btn-login:active { transform: translateY(0); }
        
        /* Error & Success Alerts */
        .error-alert, .success-alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        .error-alert {
            background: #fee2e2;
            border-left: 4px solid var(--danger);
            color: #b91c1c;
        }
        .success-alert {
            background: rgba(139, 195, 74, 0.10);
            border-left: 4px solid var(--success);
            color: var(--accent-dark);
        }
        
        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 30px 0;
            color: #94a3b8;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }
        .divider span {
            padding: 0 10px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Employee Link */
        .employee-link {
            text-align: center;
            margin-top: 20px;
        }
        .employee-link a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .employee-link a:hover {
            color: var(--primary-dark);
            gap: 12px;
        }
        .employee-link a i {
            transition: transform 0.3s ease;
        }
        .employee-link a:hover i { transform: rotate(180deg); }
        
        /* Security Badge */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            color: var(--text-light);
            font-size: 0.8rem;
        }
        .security-badge span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .security-badge i { color: var(--primary); }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }
        .loading-spinner {
            text-align: center;
            color: white;
        }
        .loading-spinner i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary-light);
        }
        .loading-spinner p { font-size: 1.2rem; font-weight: 500; }
        
        /* Theme Toggle (top-right) */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 0.65rem 0.9rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }
        .theme-toggle:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 500px;
            }
            .brand-panel { padding: 40px; }
            .login-panel { padding: 40px 30px; }
            .intro-message { display: none; }
        }
        @media (max-width: 576px) {
            .login-panel { padding: 30px 20px; }
            .brand-title h1 { font-size: 1.8rem; }
            .time-display .time { font-size: 1.5rem; }
            .remember-forgot {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            .security-badge {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-circle-notch fa-spin"></i>
            <p>Authenticating...</p>
            <small class="text-white-50">Please wait</small>
        </div>
    </div>

    

    <!-- Animated Background Shapes -->
    <div class="bg-shape">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- Main Container -->
    <div class="login-container">
        <!-- Brand Panel (Left) -->
        <div class="brand-panel">
            <div class="brand-logo">
                <img src="logo5.png" alt="CT-ABS Logo" onerror="this.src='https://via.placeholder.com/120x120/8BC34A/ffffff?text=CT-ABS'">
            </div>
            <div class="brand-title">
                <span class="system-badge">
                    <i class="fas fa-crown me-2"></i>
                </span>
                <h1>AUTOMATED<br>BIN SEGREGATION</h1>
                <p class="subtitle">SYSTEM</p>
            </div>
            
            <!-- Intro Message -->
            <div class="intro-message">
                <p><span class="highlight">Automated Bin Segregation</span>  Monitor fill levels, schedule pickups, and track analytics in real-time.</p>
                <ul>
                    <li><i class="fas fa-check-circle"></i> Real-time bin monitoring</li>
                    <li><i class="fas fa-check-circle"></i> Automated Segregation</li>
                    <li><i class="fas fa-check-circle"></i> Analytics & reporting</li>
                </ul>
            </div>
            
            <!-- Live Time Display -->
            <div class="time-display">
                <div class="date">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?= date('l, F j, Y') ?>
                </div>
                <div class="time">
                    <i class="fas fa-clock me-2"></i>
                    <?= date('h:i:s A') ?>
                </div>
                <div class="timezone">
                    <i class="fas fa-globe-asia me-1"></i>
                    Philippine Time (PHT)
                </div>
            </div>
            
            <div class="mt-4 text-white-50 small">
                <i class="fas fa-shield-alt me-1"></i> Secure Admin Portal
            </div>
        </div>

        <!-- Login Panel (Right) -->
        <div class="login-panel">
            <div class="login-header">
                <div class="role-badge">
                    <i class="fas fa-user-tie"></i>
                    <span>Administrator Access</span>
                </div>
                <h2>Welcome Back!</h2>
                <p>Sign in to manage your smart bin system</p>
            </div>

            <!-- Error / Success Messages -->
            <?php if ($error): ?>
                <div class="error-alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?> 
                </div>
            <?php endif; ?>
            <?php if (isset($success_message)): ?>
                <div class="success-alert">
                    <i class="fas fa-check-circle"></i>
                    <?= $success_message ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
                <form method="POST" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="login">

                    <div class="input-group-custom">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="form-control" 
                               placeholder="Username or Email" required autocomplete="off"
                               value="<?= htmlspecialchars($username) ?>">
                    </div>
                    
                    <div class="input-group-custom password-field">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control" 
                               placeholder="Enter password" required minlength="6">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>

                    <div class="remember-forgot">
                        <label class="remember-me">
                            <input type="checkbox" name="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>

                    <div class="security-notice">
                        <i class="fas fa-shield-alt"></i>
                        <span>Username and password authentication</span>
                    </div>

                    <button type="submit" class="btn-login" id="loginButton">
                        <i class="fas fa-sign-in-alt"></i>
                        Access Dashboard
                    </button>
                </form>

                <div class="divider"><span>system access</span></div>

                <div class="employee-link">
                    <a href="landing.php">
                        <i class="fas fa-exchange-alt"></i>
                        Back to CT-ABS Homepage
                    </a>
                </div>

            <!-- Security Badge -->
            <div class="security-badge">
                <span><i class="fas fa-shield-alt"></i> 256-bit SSL</span>
                <span><i class="fas fa-lock"></i> Password Protected</span>
                <span><i class="fas fa-clock"></i> Real-time Sync</span>
            </div>
            <div class="text-center mt-3">
                <small class="text-muted"><?= APP_NAME ?> v<?= APP_VERSION ?> • © <?= date('Y') ?> All rights reserved</small>
            </div>
        </div>
    </div>

    <script>
        // ============================================================
        // Theme Toggle (preserved)
        // ============================================================
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const themeLabel = document.getElementById('themeLabel');
        
        const savedTheme = localStorage.getItem('smartbin-theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeUI(savedTheme);
        
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const current = document.documentElement.getAttribute('data-theme');
                const next = current === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('smartbin-theme', next);
                updateThemeUI(next);
            });
        }
        
        function updateThemeUI(theme) {
            if (theme === 'dark') {
                themeIcon.className = 'fas fa-sun';
                themeLabel.textContent = 'Light';
            } else {
                themeIcon.className = 'fas fa-moon';
                themeLabel.textContent = 'Dark';
            }
        }

        // ============================================================
        // Toggle Password Visibility
        // ============================================================
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
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

        // ============================================================
        // Form loading overlay
        // ============================================================
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const overlay = document.getElementById('loadingOverlay');
                if (overlay) overlay.style.display = 'flex';
            });
        });

        // ============================================================
        // Reload on bfcache restore so a stale CSRF token can't be resubmitted
        // ============================================================
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) {
                window.location.reload();
            }
        });

        // ============================================================
        // Auto-hide error/success after 5s
        // ============================================================
        document.querySelectorAll('.error-alert, .success-alert').forEach(el => {
            setTimeout(() => {
                el.style.transition = 'opacity 0.5s ease';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            }, 5000);
        });

        // ============================================================
        // Live time update (every second)
        // ============================================================
        function updateTime() {
            const timeElem = document.querySelector('.time-display .time');
            if (timeElem) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', { 
                    hour12: true,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                timeElem.innerHTML = `<i class="fas fa-clock me-2"></i>${timeString}`;
            }
        }
        setInterval(updateTime, 1000);
    </script>
</body>
</html>