<?php
// =============================================
// add-user.php - Add New User (Admin Only)
// =============================================

// Start session and include config
require_once 'config/config.php';
session_start();

// =============================================
// 🔒 PROTECTED PAGE - Check if user is logged in
// =============================================
if (!isLoggedIn()) {
    setFlash('error', 'Please login to access this page.');
    header('Location: login.php');
    exit();
}

// Get current user
$currentUser = getCurrentUser();

// Check if user is admin
if (!$currentUser || ($currentUser['role'] !== 'admin' && $currentUser['user_type'] !== 'admin')) {
    setFlash('error', 'You do not have permission to access this page.');
    header('Location: index.php');
    exit();
}

// =============================================
// Initialize Variables
// =============================================
$error = '';
$success = '';
$username = '';
$email = '';
$full_name = '';
$role = 'viewer'; // Default role
$is_active = 1;

// =============================================
// Process Form Submission
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'viewer';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed. Please try again.';
    }
    // Validate required fields
    elseif (empty($username) || empty($email) || empty($full_name) || empty($password)) {
        $error = 'Please fill in all required fields.';
    }
    // Validate username length
    elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long.';
    }
    // Validate email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    }
    // Validate password length
    elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    }
    // Validate password confirmation
    elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    }
    else {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists. Please choose a different username.';
            }
            // Check if email already exists
            else {
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already exists. Please use a different email address.';
                }
                else {
                    // Hash password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user - Using only columns that exist in your table
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password_hash, full_name, role, is_active, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$username, $email, $password_hash, $full_name, $role, $is_active]);
                    
                    $new_user_id = $pdo->lastInsertId();
                    
                    // Log activity
                    if (function_exists('logActivity')) {
                        logActivity($_SESSION['user_id'], 'add_user', "Added new user: $username (ID: $new_user_id)");
                    }
                    
                    // Set success message
                    $success = "User <strong>$username</strong> has been created successfully!";
                    
                    // Clear form
                    $username = '';
                    $email = '';
                    $full_name = '';
                    $password = '';
                    $confirm_password = '';
                    $role = 'viewer';
                    $is_active = 1;
                    
                    // Set flash message for redirect
                    setFlash('success', "User $username has been created successfully.");
                }
            }
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
            error_log("Add user error: " . $e->getMessage());
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Get flash messages
$flash = getFlash();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - SmartBin</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .flash-message { animation: slideDown 0.3s ease; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .sidenav { transition: transform 0.25s ease; }
        .sidenav.open { transform: translateX(0) !important; }
        
        [data-theme="dark"] .dark\:bg-slate-900 { background-color: #0f172a; }
        [data-theme="dark"] .dark\:bg-slate-800 { background-color: #1e293b; }
        [data-theme="dark"] .dark\:text-white { color: #f1f5f9; }
        [data-theme="dark"] .dark\:text-gray-300 { color: #cbd5e1; }
        [data-theme="dark"] .dark\:text-gray-400 { color: #94a3b8; }
        [data-theme="dark"] .dark\:border-gray-700 { border-color: #334155; }
        
        .sidenav::-webkit-scrollbar { width: 4px; }
        .sidenav::-webkit-scrollbar-track { background: transparent; }
        .sidenav::-webkit-scrollbar-thumb { background: #475569; border-radius: 20px; }
        
        .form-input {
            transition: all 0.3s ease;
        }
        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-slate-900 text-gray-800 dark:text-gray-200 transition-colors duration-300 flex min-h-screen">
    
    <!-- ========== SIDENAV ========== -->
    <nav class="sidenav fixed top-0 left-0 h-full w-[270px] bg-slate-900 dark:bg-slate-950 text-slate-300 p-6 flex flex-col z-50 shadow-xl overflow-y-auto -translate-x-full md:translate-x-0" id="sidenav">
        <div class="flex items-center gap-3 pb-4 mb-6 border-b border-white/10">
            <i class="fas fa-trash-alt text-3xl text-cyan-400"></i>
            <h2 class="text-xl font-semibold bg-gradient-to-r from-cyan-100 to-sky-200 bg-clip-text text-transparent">
                SmartBin <span class="text-xs font-normal text-slate-500 bg-slate-800 px-2 py-0.5 rounded-full">v2</span>
            </h2>
        </div>
        
        <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold mt-2 mb-3">Main</div>
        <ul class="flex-1 space-y-1">
            <li><a href="index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all duration-200"><i class="fas fa-chart-pie w-6 text-slate-500"></i> Dashboard</a></li>
            <li><a href="users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all duration-200"><i class="fas fa-users w-6 text-slate-500"></i> User Management</a></li>
            <li class="active"><a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all duration-200 bg-slate-800 shadow-[inset_3px_0_0_#38bdf8]"><i class="fas fa-user-plus w-6 text-slate-500"></i> Add User</a></li>
            <li><a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all duration-200"><i class="fas fa-microchip w-6 text-slate-500"></i> List of Trashbin</a></li>
            <li><a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all duration-200"><i class="fas fa-database w-6 text-slate-500"></i> Waste Log</a></li>
        </ul>
        
        <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold mt-4 mb-3">System</div>
        <ul class="space-y-1">
            <li><a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all duration-200"><i class="fas fa-cogs w-6 text-slate-500"></i> Shredder</a></li>
            <li><a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all duration-200"><i class="fas fa-solar-panel w-6 text-slate-500"></i> Solar</a></li>
            <li><a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all duration-200"><i class="fas fa-bell w-6 text-slate-500"></i> Alerts</a></li>
        </ul>
        
        <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold mt-4 mb-3">Admin</div>
        <ul class="space-y-1">
            <li><a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all duration-200"><i class="fas fa-user-cog w-6 text-slate-500"></i> Settings</a></li>
            <li><a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all duration-200"><i class="fas fa-sign-out-alt w-6 text-slate-500"></i> Logout</a></li>
        </ul>
        
        <div class="mt-6 pt-4 border-t border-white/10 flex items-center gap-2 text-sm text-slate-500">
            <i class="fas fa-wifi"></i> IoT · Online
            <span class="ml-auto bg-slate-800 px-2 py-0.5 rounded-full text-xs">v1.4</span>
        </div>
    </nav>
    
    <!-- ========== MAIN CONTENT ========== -->
    <div class="flex-1 md:ml-[270px] p-4 md:p-8 min-h-screen bg-gray-50 dark:bg-slate-900 transition-all duration-300">
        
        <!-- Flash Messages -->
        <?php if ($flash): ?>
            <div class="flash-message mb-4 p-3 rounded-xl flex items-center gap-2 text-sm font-medium
                <?php echo $flash['type'] === 'success' ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800' : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800'; ?>">
                <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Top Bar -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <button class="md:hidden text-2xl text-gray-700 dark:text-gray-300" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="text-xl md:text-2xl font-semibold text-gray-900 dark:text-white">
                    <i class="fas fa-user-plus text-blue-500 mr-2"></i>Add New User
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <button class="theme-toggle px-4 py-2 rounded-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors flex items-center gap-2" id="themeToggle">
                    <i class="fas fa-moon" id="themeIcon"></i>
                    <span id="themeLabel">Dark</span>
                </button>
                <a href="users.php" class="px-4 py-2 rounded-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
        </div>
        
        <!-- Add User Form -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 md:p-8 border border-gray-200 dark:border-gray-700 shadow-sm max-w-2xl mx-auto">
            
            <!-- Success Message -->
            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-green-500 text-xl mt-0.5"></i>
                        <div>
                            <p class="text-green-800 dark:text-green-200"><?php echo $success; ?></p>
                            <a href="users.php" class="text-sm text-blue-600 dark:text-blue-400 hover:underline mt-1 inline-block">
                                <i class="fas fa-arrow-right mr-1"></i> View all users
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl mt-0.5"></i>
                        <p class="text-red-800 dark:text-red-200"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="space-y-5" id="addUserForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_user">
                
                <!-- Username -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Username <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <i class="fas fa-user absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"></i>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               value="<?php echo htmlspecialchars($username); ?>"
                               placeholder="Enter username" 
                               required
                               minlength="3"
                               class="form-input w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-slate-700/50 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition">
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Minimum 3 characters</p>
                </div>
                
                <!-- Full Name -->
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <i class="fas fa-user-tag absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"></i>
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               value="<?php echo htmlspecialchars($full_name); ?>"
                               placeholder="Enter full name" 
                               required
                               class="form-input w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-slate-700/50 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition">
                    </div>
                </div>
                
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"></i>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($email); ?>"
                               placeholder="Enter email address" 
                               required
                               class="form-input w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-slate-700/50 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition">
                    </div>
                </div>
                
                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               placeholder="Enter password" 
                               required
                               minlength="6"
                               class="form-input w-full pl-10 pr-12 py-2.5 bg-gray-50 dark:bg-slate-700/50 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition">
                        <button type="button" 
                                id="togglePassword" 
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Minimum 6 characters</p>
                </div>
                
                <!-- Confirm Password -->
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Confirm Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"></i>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               placeholder="Confirm password" 
                               required
                               class="form-input w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-slate-700/50 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition">
                    </div>
                </div>
                
                <!-- Role Selection -->
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        User Role <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <i class="fas fa-user-shield absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"></i>
                        <select id="role" 
                                name="role" 
                                class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-slate-700/50 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition appearance-none">
                            <option value="viewer" <?php echo $role === 'viewer' ? 'selected' : ''; ?>>Viewer - Read Only</option>
                            <option value="operator" <?php echo $role === 'operator' ? 'selected' : ''; ?>>Operator - Manage Operations</option>
                            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin - Full Access</option>
                        </select>
                        <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none"></i>
                    </div>
                    <div class="mt-2 p-3 bg-blue-50 dark:bg-blue-900/10 rounded-lg text-xs text-gray-600 dark:text-gray-400">
                        <p class="font-medium text-blue-700 dark:text-blue-300 mb-1">Role Permissions:</p>
                        <ul class="space-y-0.5">
                            <li>• <strong>Admin</strong> - Full access to all features, settings, and user management</li>
                            <li>• <strong>Operator</strong> - Can manage waste logs, bin operations, and view reports</li>
                            <li>• <strong>Viewer</strong> - Read-only access to view data and dashboards</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Active Status -->
                <div class="flex items-center gap-3 p-4 bg-gray-50 dark:bg-slate-700/30 rounded-xl">
                    <input type="checkbox" 
                           id="is_active" 
                           name="is_active" 
                           <?php echo $is_active ? 'checked' : ''; ?>
                           class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 focus:ring-2 transition">
                    <label for="is_active" class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
                        <i class="fas fa-circle text-green-500 text-xs mr-1"></i>
                        Active Account
                    </label>
                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-auto">User can login immediately</span>
                </div>
                
                <!-- Submit Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 pt-3">
                    <button type="submit" 
                            id="submitBtn"
                            class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-semibold rounded-xl transition transform hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-blue-500/20 flex items-center justify-center gap-2">
                        <i class="fas fa-user-plus"></i>
                        <span id="btnText">Create User</span>
                    </button>
                    <a href="users.php" class="px-6 py-3 bg-gray-200 dark:bg-slate-700 hover:bg-gray-300 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-300 font-medium rounded-xl transition text-center">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Info Box -->
        <div class="mt-6 max-w-2xl mx-auto bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <i class="fas fa-info-circle text-blue-500 text-lg mt-0.5"></i>
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    <p class="font-medium">💡 Quick Tips:</p>
                    <ul class="mt-1 space-y-0.5 text-gray-600 dark:text-gray-400">
                        <li>• Username and email must be unique</li>
                        <li>• Password must be at least 6 characters</li>
                        <li>• New users will receive a welcome email (coming soon)</li>
                        <li>• You can change roles later from the user management page</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-8 pt-4 border-t border-gray-200 dark:border-gray-700 text-center text-xs text-gray-400 dark:text-gray-500">
            <i class="fas fa-microchip"></i> ESP32 · AI camera · capacitive · inductive · ultrasonic · solar-powered · web-based monitoring
        </div>
    </div>
    
    <script>
        // ----- Sidebar toggle -----
        const hamburger = document.getElementById('hamburgerBtn');
        const sidenav = document.getElementById('sidenav');
        
        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            sidenav.classList.toggle('open');
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!sidenav.contains(e.target) && !hamburger.contains(e.target)) {
                    sidenav.classList.remove('open');
                }
            }
        });
        
        // ----- Theme toggle -----
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const themeLabel = document.getElementById('themeLabel');
        
        const savedTheme = localStorage.getItem('smartbin-theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeUI(savedTheme);
        
        themeToggle.addEventListener('click', function() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('smartbin-theme', next);
            updateThemeUI(next);
        });
        
        function updateThemeUI(theme) {
            if (theme === 'dark') {
                themeIcon.className = 'fas fa-sun';
                themeLabel.textContent = 'Light';
                document.documentElement.classList.add('dark');
            } else {
                themeIcon.className = 'fas fa-moon';
                themeLabel.textContent = 'Dark';
                document.documentElement.classList.remove('dark');
            }
        }
        
        // ----- Toggle password visibility -----
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
        
        // ----- Form validation -----
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            submitBtn.disabled = true;
            btnText.textContent = 'Creating...';
            submitBtn.querySelector('i').className = 'fas fa-spinner fa-spin';
        });
    </script>
</body>
</html>