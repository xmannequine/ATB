<?php
// =============================================
// register.php - ATB System Registration Page
// Simplified version with better error handling
// =============================================

require_once 'config/config.php';
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';
$step = 'register';
$user_data = [];
$otp_display = '';

// =============================================
// Process Registration
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'register';
    
    // =============================================
    // Step 1: Register
    // =============================================
    if ($action === 'register') {
        $full_name = sanitize($_POST['full_name'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate
        if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($full_name) < 2) {
            $error = 'Full name must be at least 2 characters.';
        } elseif (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            try {
                // Check if username exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username already taken.';
                }
                // Check if email exists
                else {
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = 'Email already registered.';
                    } else {
                        // Hash password
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Generate OTP
                        $otp = generateOTP();
                        $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        
                        // Insert user (activate immediately for testing)
                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, password_hash, full_name, email, role, is_active, email_verified_at, created_at) 
                            VALUES (?, ?, ?, ?, 'viewer', 1, NOW(), NOW())
                        ");
                        $stmt->execute([$username, $password_hash, $full_name, $email]);
                        $user_id = $pdo->lastInsertId();
                        
                        // Store OTP in database
                        $stmt = $pdo->prepare("
                            INSERT INTO otp_logs (user_id, otp, expiry, ip_address, user_agent, purpose, created_at) 
                            VALUES (?, ?, ?, ?, ?, 'registration', NOW())
                        ");
                        $stmt->execute([
                            $user_id,
                            password_hash($otp, PASSWORD_DEFAULT),
                            $otp_expiry,
                            $_SERVER['REMOTE_ADDR'],
                            $_SERVER['HTTP_USER_AGENT']
                        ]);
                        
                        // Send OTP email
                        if (sendOTPEmail($email, $otp, $full_name, 'registration')) {
                            // Store in session
                            $_SESSION['reg_otp'] = $otp;
                            $_SESSION['reg_otp_expiry'] = $otp_expiry;
                            $_SESSION['reg_user_id'] = $user_id;
                            $_SESSION['reg_email'] = $email;
                            
                            $step = 'verify_otp';
                            $success = "OTP sent to " . maskEmail($email);
                            if (isLocalTestMode()) {
                                $otp_display = $otp;
                            }
                            $user_data = [
                                'full_name' => $full_name,
                                'username' => $username,
                                'email' => $email,
                                'user_id' => $user_id
                            ];
                        } else {
                            // Rollback if email fails
                            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$user_id]);
                            $error = 'Failed to send verification email. Please try again.';
                        }
                    }
                }
            } catch(PDOException $e) {
                $error = 'An error occurred: ' . $e->getMessage();
                error_log("Registration error: " . $e->getMessage());
            }
        }
    }
    
    // =============================================
    // Step 2: Verify OTP
    // =============================================
    elseif ($action === 'verify_otp') {
        $entered_otp = sanitize($_POST['otp'] ?? '');
        
        if (empty($entered_otp)) {
            $error = 'Please enter the OTP.';
        } else {
            // Check OTP
            if ($entered_otp === $_SESSION['reg_otp']) {
                // OTP verified - user is already active from registration
                $step = 'success';
                $success = "Registration successful! You can now login.";
                
                // Clear session
                unset($_SESSION['reg_otp'], $_SESSION['reg_otp_expiry'], $_SESSION['reg_user_id']);
            } else {
                $error = 'Invalid OTP. Please try again.';
                $_SESSION['reg_otp_attempts'] = ($_SESSION['reg_otp_attempts'] ?? 0) + 1;
                if ($_SESSION['reg_otp_attempts'] >= 3) {
                    $error = 'Too many failed attempts. Please request a new OTP.';
                    unset($_SESSION['reg_otp'], $_SESSION['reg_otp_expiry'], $_SESSION['reg_user_id'], $_SESSION['reg_otp_attempts']);
                    $step = 'register';
                }
            }
        }
    }
    
    // =============================================
    // Resend OTP
    // =============================================
    elseif ($action === 'resend_otp') {
        $user_id = $_SESSION['reg_user_id'] ?? 0;
        $email = $_SESSION['reg_email'] ?? '';
        
        if ($user_id && $email) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $otp = generateOTP();
                    $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    $_SESSION['reg_otp'] = $otp;
                    $_SESSION['reg_otp_expiry'] = $otp_expiry;
                    $_SESSION['reg_otp_attempts'] = 0;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO otp_logs (user_id, otp, expiry, ip_address, user_agent, purpose, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'registration', NOW())
                    ");
                    $stmt->execute([
                        $user['user_id'],
                        password_hash($otp, PASSWORD_DEFAULT),
                        $otp_expiry,
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT']
                    ]);
                    
                    if (sendOTPEmail($user['email'], $otp, $user['full_name'], 'registration')) {
                        $success = "New OTP sent to " . maskEmail($user['email']);
                        $step = 'verify_otp';
                    } else {
                        $error = 'Failed to send OTP. Please try again.';
                    }
                }
            } catch(PDOException $e) {
                $error = 'An error occurred. Please try again.';
            }
        }
    }
}

$csrf_token = generateCSRFToken();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME ?? 'ATB System'; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #e0e7ff 0%, #f1f5f9 100%); }
        .flash-message { animation: slideDown 0.3s ease; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .register-card { transition: all 0.3s ease; }
        .register-card:hover { box-shadow: 0 20px 40px rgba(37, 99, 235, 0.15); }
        .otp-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .success-icon { animation: bounceIn 0.6s ease; }
        @keyframes bounceIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Branding -->
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-gradient-to-br from-blue-600 to-blue-400 rounded-2xl inline-flex items-center justify-center shadow-lg shadow-blue-500/30 mb-4">
                <i class="fas fa-user-plus text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight"><?php echo APP_NAME ?? 'SmartBin'; ?></h1>
            <p class="text-slate-500 text-sm mt-1">Create your account</p>
        </div>
        
        <!-- Registration Card -->
        <div class="bg-white rounded-2xl p-8 shadow-lg register-card border border-white/50">
            
            <?php if ($step === 'register'): ?>
                <div class="text-center mb-6">
                    <h2 class="text-xl font-semibold text-slate-900">Create Account</h2>
                    <p class="text-slate-500 text-sm mt-1">Join the Smart Waste Management System</p>
                </div>
                
                <?php if ($flash): ?>
                    <div class="flash-message mb-4 p-3 rounded-xl flex items-center gap-2 text-sm font-medium
                        <?php echo $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'; ?>">
                        <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="flash-message mb-4 p-3 rounded-xl flex items-center gap-2 text-sm font-medium bg-red-50 text-red-800 border border-red-200">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($otp_display)): ?>
                    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <div class="flex items-center gap-2 font-semibold">
                            <i class="fas fa-info-circle"></i>
                            Local test OTP
                        </div>
                        <div class="mt-2 text-center font-mono text-2xl tracking-[0.4em] text-amber-900">
                            <?php echo htmlspecialchars($otp_display); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registerForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="mb-3">
                        <label for="full_name" class="block text-sm font-medium text-slate-700 mb-1.5">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" id="full_name" name="full_name" placeholder="Enter your full name"
                                value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                                required class="w-full pl-10 pr-3 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:outline-none transition-all">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="block text-sm font-medium text-slate-700 mb-1.5">
                            Username <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-user-tag absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" id="username" name="username" placeholder="Choose a username"
                                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                required minlength="3" class="w-full pl-10 pr-3 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:outline-none transition-all">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">
                            Email Address <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="email" id="email" name="email" placeholder="Enter your email"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                required class="w-full pl-10 pr-3 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:outline-none transition-all">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="password" id="password" name="password" placeholder="Create a password"
                                required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                                class="w-full pl-10 pr-12 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:outline-none transition-all">
                            <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="block text-sm font-medium text-slate-700 mb-1.5">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-check-circle absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password"
                                required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                                class="w-full pl-10 pr-3 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:outline-none transition-all">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="flex items-start gap-2 text-sm text-slate-600 cursor-pointer">
                            <input type="checkbox" id="terms" name="terms" required class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500 mt-0.5">
                            <span>I agree to the <a href="#" class="text-blue-600 hover:underline">Terms of Service</a></span>
                        </label>
                    </div>
                    
                    <button type="submit" id="registerBtn" class="w-full py-3.5 bg-gradient-to-r from-blue-600 to-blue-400 text-white font-semibold rounded-xl hover:shadow-lg hover:shadow-blue-500/30 transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                        <span id="btnText"><i class="fas fa-user-plus"></i> Create Account</span>
                        <span id="btnSpinner" class="hidden"><i class="fas fa-spinner fa-spin"></i></span>
                    </button>
                </form>
                
                <div class="text-center mt-6 pt-6 border-t border-slate-200">
                    <p class="text-sm text-slate-500">
                        Already have an account? 
                        <a href="login.php" class="text-blue-600 font-semibold hover:underline">Login here</a>
                    </p>
                </div>
                
            <?php elseif ($step === 'verify_otp'): ?>
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-blue-100 rounded-full inline-flex items-center justify-center mb-3">
                        <i class="fas fa-shield-alt text-blue-600 text-2xl"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-slate-900">Verify Your Email</h2>
                    <p class="text-slate-500 text-sm mt-1">
                        Enter the 6-digit code sent to<br>
                        <strong class="text-slate-700"><?php echo htmlspecialchars(maskEmail($_SESSION['reg_email'] ?? '')); ?></strong>
                    </p>
                </div>
                
                <?php if ($error): ?>
                    <div class="flash-message mb-4 p-3 rounded-xl flex items-center gap-2 text-sm font-medium bg-red-50 text-red-800 border border-red-200">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="flash-message mb-4 p-3 rounded-xl flex items-center gap-2 text-sm font-medium bg-green-50 text-green-800 border border-green-200">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="otpForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="verify_otp">
                    
                    <div class="mb-4">
                        <label for="otp" class="block text-sm font-medium text-slate-700 mb-1.5">
                            6-Digit OTP <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-key absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" id="otp" name="otp" placeholder="Enter 6-digit OTP"
                                required pattern="[0-9]{6}" maxlength="6" autofocus
                                class="w-full pl-10 pr-3 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:outline-none transition-all text-center text-2xl font-bold tracking-widest otp-input">
                        </div>
                        <p class="text-xs text-slate-400 mt-1.5 flex items-center gap-2">
                            <i class="fas fa-clock"></i> 
                            OTP expires in <span id="timerDisplay" class="font-semibold text-blue-600">10:00</span>
                        </p>
                    </div>
                    
                    <button type="submit" id="verifyBtn" class="w-full py-3.5 bg-gradient-to-r from-green-600 to-green-400 text-white font-semibold rounded-xl hover:shadow-lg hover:shadow-green-500/30 transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                        <i class="fas fa-check-circle"></i> Verify OTP
                    </button>
                    
                    <div class="text-center mt-4">
                        <p class="text-sm text-slate-500">
                            Didn't receive the code? 
                            <button type="button" id="resendBtn" class="text-blue-600 font-semibold hover:underline">
                                Resend OTP
                            </button>
                        </p>
                    </div>
                </form>
                
                <form id="resendForm" method="POST" action="" class="hidden">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="resend_otp">
                </form>
                
            <?php else: ?>
                <div class="text-center">
                    <div class="w-24 h-24 bg-green-100 rounded-full inline-flex items-center justify-center mb-6 success-icon">
                        <i class="fas fa-check-circle text-green-600 text-5xl"></i>
                    </div>
                    
                    <h2 class="text-2xl font-bold text-slate-900 mb-2">Registration Successful!</h2>
                    <p class="text-slate-500 mb-4">
                        Your account has been created and activated.
                    </p>
                    
                    <a href="login.php" class="inline-block w-full py-3.5 bg-gradient-to-r from-blue-600 to-blue-400 text-white font-semibold rounded-xl hover:shadow-lg hover:shadow-blue-500/30 transition-all transform hover:-translate-y-0.5 text-center">
                        <i class="fas fa-sign-in-alt"></i> Login Now
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-6 text-xs text-slate-400">
            <span><i class="fas fa-code"></i> v<?php echo APP_VERSION ?? '1.0'; ?></span>
            <span class="mx-2">|</span>
            <span><i class="fas fa-shield-alt"></i> Secure Registration</span>
            <span class="mx-2">|</span>
            <span><i class="fas fa-clock"></i> <?php echo date('Y'); ?></span>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle Password
            const togglePassword = document.getElementById('togglePassword');
            if (togglePassword) {
                togglePassword.addEventListener('click', function() {
                    const passwordInput = document.getElementById('password');
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }
            
            // Password confirmation
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            if (password && confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    if (password.value !== this.value) {
                        this.setCustomValidity('Passwords do not match');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
            
            // OTP auto-submit
            const otpInput = document.getElementById('otp');
            if (otpInput) {
                otpInput.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '');
                    if (this.value.length === 6) {
                        document.getElementById('otpForm').submit();
                    }
                });
                otpInput.focus();
            }
            
            // OTP Timer
            const timerDisplay = document.getElementById('timerDisplay');
            if (timerDisplay) {
                let timeLeft = 600;
                const timerInterval = setInterval(() => {
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                    if (timeLeft <= 0) {
                        clearInterval(timerInterval);
                        timerDisplay.textContent = 'Expired';
                        timerDisplay.className = 'font-semibold text-red-600';
                    }
                    timeLeft--;
                }, 1000);
            }
            
            // Resend OTP
            const resendBtn = document.getElementById('resendBtn');
            if (resendBtn) {
                resendBtn.addEventListener('click', function() {
                    if (confirm('Request a new OTP?')) {
                        document.getElementById('resendForm').submit();
                    }
                });
            }
        });
    </script>
</body>
</html>