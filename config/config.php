<?php
// =============================================
// config.php - ATB System Configuration
// =============================================

function configValue($key, $default = '') {
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
    return $value;
}

// Error reporting (disable in production)
$runtimeHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$configuredEnvironment = configValue('APP_ENV');
$appEnvironment = $configuredEnvironment ?: (in_array($runtimeHost, ['localhost', '127.0.0.1', '[::1]'], true) ? 'local' : 'production');
$isProduction = strtolower($appEnvironment) === 'production';
error_reporting($isProduction ? 0 : E_ALL);
ini_set('display_errors', $isProduction ? '0' : '1');

// Keep production sessions on one hostname so CSRF tokens remain valid.
if ($isProduction && str_starts_with($runtimeHost, 'www.')) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://ct-abs.com' . $requestUri, true, 301);
    exit();
}

// =============================================
// Database Configuration
// =============================================
define('DB_HOST', configValue('DB_HOST', 'localhost'));
define('DB_NAME', configValue('DB_NAME', 'atb'));
define('DB_USER', configValue('DB_USER', 'root'));
define('DB_PASS', configValue('DB_PASS'));

// =============================================
// Application Configuration
// =============================================
define('APP_NAME', 'SmartBin');
define('APP_VERSION', '2.0');
define('APP_ENV', strtolower($appEnvironment));

// Auto-detect domain for APP_URL (works on both localhost and live server)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
define('APP_URL', $protocol . $host . $basePath . '/');

// =============================================
// Page Redirects (for landing page integration)
// =============================================
define('LANDING_PAGE', 'landing.php');
define('LOGIN_PAGE', 'login.php');
define('DASHBOARD_PAGE', 'index.php');

// =============================================
// Email Configuration (SMTP)
// =============================================
define('SMTP_HOST', configValue('SMTP_HOST', strtolower($appEnvironment) === 'production' ? 'smtp.hostinger.com' : 'smtp.gmail.com'));
define('SMTP_PORT', (int)configValue('SMTP_PORT', 587));
define('SMTP_USERNAME', configValue('SMTP_USERNAME'));
define('SMTP_PASSWORD', configValue('SMTP_PASSWORD'));
define('SMTP_FROM_EMAIL', configValue('SMTP_FROM_EMAIL'));
define('SMTP_FROM_NAME', configValue('SMTP_FROM_NAME', 'SmartBin System'));
define('SMTP_SECURE', strtolower(configValue('SMTP_SECURE', 'tls')));
$smtpCredentialsAvailable = SMTP_USERNAME !== '' && SMTP_PASSWORD !== '' && SMTP_FROM_EMAIL !== '';
define('SMTP_ENABLED', filter_var(configValue('SMTP_ENABLED', $smtpCredentialsAvailable ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN));
define('MONITOR_PASSWORD', configValue('MONITOR_PASSWORD', 'atb2026'));

// =============================================
// Session Configuration
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('smartbin_session');
    session_cache_limiter('nocache');
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    
    // Auto-detect HTTPS for secure cookies
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $isHttps, true);
    }
}

// =============================================
// Security Configuration
// =============================================
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);

// =============================================
// Database Connection
// =============================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// =============================================
// Autoload PHPMailer
// =============================================
function loadPHPMailer() {
    $paths = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
        __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    return false;
}

$hasPHPMailer = loadPHPMailer();

// =============================================
// Ensure Required Tables Exist
// =============================================
function ensureRequiredTables() {
    global $pdo;

    $tables = [
        'otp_logs' => "
            CREATE TABLE IF NOT EXISTS otp_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                otp VARCHAR(255) NOT NULL,
                expiry DATETIME NOT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                purpose VARCHAR(20) NOT NULL DEFAULT 'login',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_expiry (expiry)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'activity_logs' => "
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED DEFAULT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
            , 'devices' => "
                CREATE TABLE IF NOT EXISTS devices (
                    device_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    device_name VARCHAR(191) DEFAULT NULL,
                    device_code VARCHAR(191) NOT NULL UNIQUE,
                    api_key VARCHAR(191) NOT NULL UNIQUE,
                    bin_id INT UNSIGNED DEFAULT NULL,
                    meta JSON DEFAULT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL,
                    INDEX idx_bin_id (bin_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'device_messages' => "
                CREATE TABLE IF NOT EXISTS device_messages (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    device_id INT UNSIGNED NOT NULL,
                    payload JSON NOT NULL,
                    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    INDEX idx_device_id (device_id),
                    INDEX idx_received_at (received_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
    ];

    foreach ($tables as $sql) {
        try {
            $pdo->exec($sql);
        } catch(PDOException $e) {
            error_log("Table creation error: " . $e->getMessage());
        }
    }

    try {
        $pdo->query("ALTER TABLE otp_logs ADD COLUMN purpose VARCHAR(20) NOT NULL DEFAULT 'login'");
    } catch (PDOException $e) {
        // Ignore if column already exists
    }
}

ensureRequiredTables();

// =============================================
// Helper Functions
// =============================================

// Session management
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

// Flash messages
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// CSRF protection
function generateCSRFToken() {
    $isExpired = isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_LIFETIME;
    if (empty($_SESSION['csrf_token']) || $isExpired) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    
    // Check token expiry
    if (isset($_SESSION['csrf_token_time']) && 
        (time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_LIFETIME) {
        return false;
    }
    
    return true;
}

// Sanitization
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// =============================================
// Redirect Functions
// =============================================
function redirectToLogin() {
    header('Location: ' . LOGIN_PAGE);
    exit();
}

function redirectToDashboard() {
    header('Location: ' . DASHBOARD_PAGE);
    exit();
}

function redirectToLanding() {
    header('Location: ' . LANDING_PAGE);
    exit();
}

function redirectTo($page) {
    header('Location: ' . $page);
    exit();
}

// =============================================
// Logout Function
// =============================================
function logoutUser() {
    if (function_exists('logActivity') && isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to landing page
    redirectToLanding();
}

// =============================================
// Email Helper Functions
// =============================================
function isPlaceholderEmailConfig() {
    $username = trim((string)(defined('SMTP_USERNAME') ? SMTP_USERNAME : ''));
    $password = trim((string)(defined('SMTP_PASSWORD') ? SMTP_PASSWORD : ''));
    $fromEmail = trim((string)(defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : ''));

    $placeholderUsernames = ['your-gmail-address@gmail.com', 'your-email@gmail.com', 'your@example.com', ''];
    $placeholderPasswords = ['your-16-character-app-password', 'your-app-password', 'your-password', ''];
    $placeholderFroms = ['your-gmail-address@gmail.com', 'your-email@gmail.com', 'your@example.com', ''];

    return in_array($username, $placeholderUsernames, true)
        || in_array($password, $placeholderPasswords, true)
        || in_array($fromEmail, $placeholderFroms, true);
}

function isLocalTestMode() {
    return APP_ENV === 'local' && !SMTP_ENABLED;
}

// =============================================
// Email Function using PHPMailer or PHP mail()
// =============================================
function sendEmail($to, $subject, $body, $altBody = '') {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/otp_mail.log';
    $entry = date('Y-m-d H:i:s') . " | TO: {$to} | SUBJECT: {$subject}\n";
    file_put_contents($logFile, $entry, FILE_APPEND);

    if (isLocalTestMode()) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " | LOCAL OTP TEST MODE | TO: {$to}\n", FILE_APPEND);
        return true;
    }

    if (!SMTP_ENABLED) {
        error_log('SMTP is disabled. OTP email not sent.');
        file_put_contents($logFile, date('Y-m-d H:i:s') . " | SMTP DISABLED\n", FILE_APPEND);
        return false;
    }

    if (isPlaceholderEmailConfig()) {
        if (APP_ENV === 'local') {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " | LOCAL TEST MODE | TO: {$to}\n", FILE_APPEND);
            return true;
        }

        error_log('SMTP credentials are incomplete. OTP email not sent.');
        file_put_contents($logFile, date('Y-m-d H:i:s') . " | SMTP CONFIGURATION INCOMPLETE\n", FILE_APPEND);
        return false;
    }

    if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->Port       = SMTP_PORT;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            
            if (SMTP_SECURE === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (SMTP_SECURE === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
            
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);

            $mail->send();
            file_put_contents($logFile, date('Y-m-d H:i:s') . " | SENT VIA PHPMailer\n", FILE_APPEND);
            return true;
        } catch (\Throwable $e) {
            error_log("PHPMailer error: " . $e->getMessage());
            file_put_contents($logFile, date('Y-m-d H:i:s') . " | PHPMailer ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    // Fallback to PHP mail()
    if (function_exists('mail')) {
        $headers = "From: " . SMTP_FROM_EMAIL . "\r\n" .
            "Reply-To: " . SMTP_FROM_EMAIL . "\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "X-Mailer: PHP/" . phpversion();

        $result = mail($to, $subject, $body, $headers);
        if ($result) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " | SENT VIA PHP mail()\n", FILE_APPEND);
        }
        return $result;
    }

    error_log('No email transport available.');
    file_put_contents($logFile, date('Y-m-d H:i:s') . " | NO EMAIL TRANSPORT\n", FILE_APPEND);
    return false;
}

// =============================================
// OTP Functions
// =============================================
function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function sendOTPEmail($email, $otp, $full_name = 'User', $purpose = 'login') {
    $subject = $purpose === 'registration'
        ? '🔐 Verify your SmartBin account'
        : '🔐 Your OTP for SmartBin Login';
    
    $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f8fafc; border-radius: 12px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <div style='display: inline-block; background: linear-gradient(135deg, #2563eb, #3b82f6); padding: 15px 20px; border-radius: 16px;'>
                    <span style='color: white; font-size: 32px;'>🗑️</span>
                </div>
                <h2 style='color: #0f172a; margin-top: 15px;'>SmartBin System</h2>
                <p style='color: #64748b;'>Secure Login Verification</p>
            </div>
            
            <div style='background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);'>
                <p style='color: #1e293b; font-size: 16px;'>Hello <strong>{$full_name}</strong>,</p>
                <p style='color: #475569; font-size: 15px;'>You requested to login to your SmartBin account. Use the OTP below to complete your login:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <div style='display: inline-block; background: #f1f5f9; padding: 15px 30px; border-radius: 12px; letter-spacing: 8px; font-size: 36px; font-weight: 700; color: #2563eb;'>
                        {$otp}
                    </div>
                </div>
                
                <p style='color: #64748b; font-size: 14px;'>This OTP is valid for <strong>5 minutes</strong>. Do not share this code with anyone.</p>
                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='color: #94a3b8; font-size: 12px; text-align: center;'>
                    If you didn't request this, please ignore this email.<br>
                    &copy; " . date('Y') . " SmartBin System. All rights reserved.
                </p>
            </div>
        </div>
    ";
    
    $altBody = "Hello {$full_name},\n\nYou requested to login to your SmartBin account.\nYour OTP is: {$otp}\n\nThis OTP is valid for 5 minutes.\n\nIf you didn't request this, please ignore this email.";
    
    return sendEmail($email, $subject, $body, $altBody);
}

function maskEmail($email) {
    if (empty($email) || strpos($email, '@') === false) {
        return $email;
    }
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1] ?? '';
    $masked = substr($name, 0, 2) . str_repeat('*', max(3, strlen($name) - 3)) . substr($name, -1);
    return $masked . '@' . $domain;
}

// Logging function
function logActivity($user_id, $action, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        return true;
    } catch(PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}

// =============================================
// Domain Helper Functions
// =============================================
function getBaseURL() {
    return APP_URL;
}

function getCurrentURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    return $protocol . $host . $uri;
}

// =============================================
// Error Handler for Production
// =============================================
if (!defined('DEBUG_MODE') || DEBUG_MODE === false) {
    ini_set('display_errors', 0);
    error_reporting(0);
}
?>