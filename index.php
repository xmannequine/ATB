<?php
// =============================================
// dashboard.php - CT-ABS System Dashboard
// Automated Trash Bin System
// =============================================

// Start session and include config
require_once 'config/config.php';
session_start();

// =============================================
// 🔒 PROTECTED PAGE - Check if user is logged in
// =============================================
if (!isLoggedIn()) {
    redirectToLanding();
}

// User is logged in, get user data
$user = getCurrentUser();

// If user doesn't exist in database, logout
if (!$user) {
    session_destroy();
    setFlash('error', 'User account not found.');
    redirectToLogin();
}

// Check if user is active
if ($user['is_active'] != 1) {
    session_destroy();
    setFlash('error', 'Your account has been deactivated.');
    redirectToLogin();
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser();
}

// Get flash messages
$flash = getFlash();

// =============================================
// Page Variables
// =============================================
$full_name = $_SESSION['full_name'] ?? 'User';
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? 'viewer';
$user_id = $_SESSION['user_id'] ?? 0;

if (function_exists('logActivity')) {
    logActivity($user_id, 'view_dashboard', 'Viewed the dashboard');
}

// =============================================
// Get Active Bin from Session
// =============================================
$activeBinId = $_SESSION['active_bin_id'] ?? 0;
$activeBin = null;
$allBins = [];
$systemMetrics = [
    'segregation' => 'N/A',
    'performance' => 'N/A',
    'acceptability' => 'N/A',
    'maintainability' => 'N/A'
];

try {
    // Get all active bins for dropdown
    $stmt = $pdo->query("SELECT * FROM trash_bins WHERE status = 'active' ORDER BY bin_name");
    $allBins = $stmt->fetchAll();
    
    if ($activeBinId) {
        $stmt = $pdo->prepare("SELECT * FROM trash_bins WHERE bin_id = ?");
        $stmt->execute([$activeBinId]);
        $activeBin = $stmt->fetch();
    }
    
    // If no active bin set, use the first available
    if (!$activeBin && !empty($allBins)) {
        $activeBin = $allBins[0];
        $_SESSION['active_bin_id'] = $activeBin['bin_id'];
    }
} catch(PDOException $e) {
    $allBins = [];
    $activeBin = null;
}

// =============================================
// Fetch ALL Data from Database
// =============================================
try {
    // Get total users count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch()['total'];
    
    // Get active users count
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM users WHERE is_active = 1");
    $active_users = $stmt->fetch()['active'];
    
    // Get bin status
    if ($activeBin) {
        $stmt = $pdo->prepare("SELECT * FROM v_bin_status WHERE bin_id = ?");
        $stmt->execute([$activeBin['bin_id']]);
        $binStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("SELECT * FROM v_bin_status");
        $binStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get today's statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_disposals,
            SUM(CASE WHEN bin_type = 'biodegradable' THEN 1 ELSE 0 END) as bio_count,
            SUM(CASE WHEN bin_type = 'plastic' THEN 1 ELSE 0 END) as plastic_count,
            SUM(CASE WHEN bin_type = 'metal' THEN 1 ELSE 0 END) as metal_count,
            SUM(CASE WHEN is_shredded = 1 THEN 1 ELSE 0 END) as shredded_count
        FROM waste_logs 
        WHERE DATE(created_at) = CURDATE()
    ");
    $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get weekly statistics
    $stmt = $pdo->query("
        SELECT 
            DATE(created_at) as log_date,
            COUNT(*) as daily_disposals
        FROM waste_logs 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY log_date ASC
    ");
    $weeklyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active alerts count
    $stmt = $pdo->query("SELECT COUNT(*) as alert_count FROM alerts WHERE is_resolved = 0");
    $alertCount = $stmt->fetch()['alert_count'];

    // Get live system quality metrics from recorded system data
    $stmt = $pdo->query("SELECT
            (SELECT COUNT(*) FROM waste_logs) as total_logs,
            (SELECT COUNT(*) FROM waste_logs WHERE status = 'success') as successful_logs,
            (SELECT COUNT(*) FROM devices WHERE is_active = 1) as active_devices,
            (SELECT COUNT(*) FROM devices WHERE is_active = 1 AND updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as responsive_devices,
            (SELECT COUNT(*) FROM trash_bins) as total_bins,
            (SELECT COUNT(*) FROM trash_bins WHERE status <> 'maintenance') as maintainable_bins");
    $metricData = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalLogs = (int)($metricData['total_logs'] ?? 0);
    $activeDevices = (int)($metricData['active_devices'] ?? 0);
    $totalBins = (int)($metricData['total_bins'] ?? 0);
    $systemMetrics['segregation'] = $totalLogs > 0
        ? number_format(((int)$metricData['successful_logs'] / $totalLogs) * 100, 1) . '%'
        : 'N/A';
    $systemMetrics['performance'] = $activeDevices > 0
        ? number_format(((int)$metricData['responsive_devices'] / $activeDevices) * 100, 1) . '%'
        : 'N/A';
    $systemMetrics['maintainability'] = $totalBins > 0
        ? number_format(((int)$metricData['maintainable_bins'] / $totalBins) * 100, 1) . '%'
        : 'N/A';
    
    // Get recent waste logs (last 5)
    $stmt = $pdo->query("
        SELECT 
            wl.*, 
            u.full_name,
            u.username
        FROM waste_logs wl
        LEFT JOIN users u ON wl.user_id = u.user_id
        ORDER BY wl.created_at DESC 
        LIMIT 5
    ");
    $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get last disposal time
    $stmt = $pdo->query("SELECT created_at FROM waste_logs ORDER BY created_at DESC LIMIT 1");
    $lastDisposal = $stmt->fetch();
    $lastDisposalTime = $lastDisposal ? date('M d, H:i', strtotime($lastDisposal['created_at'])) : 'No records';
    
    // ----- DATA FOR CHARTS -----
    // 1. Waste type distribution (today)
    $chartData = [
        'bio' => (int)($todayStats['bio_count'] ?? 0),
        'plastic' => (int)($todayStats['plastic_count'] ?? 0),
        'metal' => (int)($todayStats['metal_count'] ?? 0)
    ];
    
    // 2. Daily trend for last 7 days (labels & values)
    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $dayCounts = array_fill(0, 7, 0);
    foreach ($weeklyStats as $stat) {
        $dayIndex = date('N', strtotime($stat['log_date'])) - 1;
        $dayCounts[$dayIndex] = (int)$stat['daily_disposals'];
    }
    $trendLabels = $days;
    $trendValues = $dayCounts;
    
    // 3. AI Suggestion - rule-based
    $aiSuggestion = '';
    $suggestionType = 'info'; // 'warning', 'success', 'info'
    
    if ($alertCount > 0) {
        $aiSuggestion = "⚠️ There are {$alertCount} unresolved alerts. Please check the Alerts section immediately.";
        $suggestionType = 'warning';
    } elseif ($bioFill >= 80 || $plasticFill >= 80 || $metalFill >= 80) {
        $fullBins = [];
        if ($bioFill >= 80) $fullBins[] = 'Biodegradable';
        if ($plasticFill >= 80) $fullBins[] = 'Plastic';
        if ($metalFill >= 80) $fullBins[] = 'Metal';
        $aiSuggestion = "🚨 The " . implode(', ', $fullBins) . " bin" . (count($fullBins)>1?'s':'') . " is near full. Schedule collection soon.";
        $suggestionType = 'warning';
    } elseif (array_sum($chartData) > 20 && ($bioFill < 30 || $plasticFill < 30 || $metalFill < 30)) {
        $lowBins = [];
        if ($bioFill < 30) $lowBins[] = 'Biodegradable';
        if ($plasticFill < 30) $lowBins[] = 'Plastic';
        if ($metalFill < 30) $lowBins[] = 'Metal';
        $aiSuggestion = "📊 The " . implode(', ', $lowBins) . " bin" . (count($lowBins)>1?'s':'') . " has low fill level. Consider redistributing waste or optimizing collection routes.";
        $suggestionType = 'info';
    } elseif ($todayStats['total_disposals'] > 0 && $todayStats['shredded_count'] < $todayStats['total_disposals'] * 0.3) {
        $aiSuggestion = "♻️ Only " . number_format(($todayStats['shredded_count'] / max(1, $todayStats['total_disposals']) * 100), 0) . "% of today's waste was shredded. Increase shredding to improve recycling efficiency.";
        $suggestionType = 'info';
    } else {
        $aiSuggestion = "✅ All systems operational. Waste segregation and monitoring are running smoothly. Keep up the good work!";
        $suggestionType = 'success';
    }
    
} catch(PDOException $e) {
    error_log('Dashboard data error: ' . $e->getMessage());
    $total_users = 0;
    $active_users = 0;
    $binStatus = [];
    $todayStats = ['total_disposals' => 0, 'bio_count' => 0, 'plastic_count' => 0, 'metal_count' => 0, 'shredded_count' => 0];
    $weeklyStats = [];
    $alertCount = 0;
    $recentLogs = [];
    $lastDisposalTime = 'No records';
    $systemMetrics = [
        'segregation' => 'N/A',
        'performance' => 'N/A',
        'acceptability' => 'N/A',
        'maintainability' => 'N/A'
    ];
    $chartData = ['bio' => 0, 'plastic' => 0, 'metal' => 0];
    $trendLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $trendValues = [0,0,0,0,0,0,0];
    $aiSuggestion = "Unable to generate AI suggestion. Please check system logs.";
    $suggestionType = 'warning';
}

// Get fill levels by bin type; SQL result order is not guaranteed.
$fillLevels = [
    'biodegradable' => 0,
    'plastic' => 0,
    'metal' => 0
];
foreach ($binStatus as $status) {
    $binType = strtolower((string)($status['bin_type'] ?? ''));
    if (array_key_exists($binType, $fillLevels)) {
        $fillLevels[$binType] = (float)$status['fill_level'];
    }
}
$bioFill = $fillLevels['biodegradable'];
$plasticFill = $fillLevels['plastic'];
$metalFill = $fillLevels['metal'];

// ============================================================
// HELPER FUNCTIONS - Defined only ONCE
// ============================================================
function getStatusColor($fillLevel) {
    if ($fillLevel >= 80) return 'text-red-500';
    if ($fillLevel >= 60) return 'text-yellow-500';
    if ($fillLevel >= 30) return 'text-blue-500';
    return 'text-green-500';
}

function getStatusBadge($fillLevel) {
    if ($fillLevel >= 80) return 'bg-red-500 text-white';
    if ($fillLevel >= 60) return 'bg-yellow-500 text-white';
    if ($fillLevel >= 30) return 'bg-blue-500 text-white';
    return 'bg-green-500 text-white';
}

function getStatusText($fillLevel) {
    if ($fillLevel >= 80) return 'FULL';
    if ($fillLevel >= 60) return 'HIGH';
    if ($fillLevel >= 30) return 'MEDIUM';
    return 'LOW';
}

function getFillColor($fillLevel) {
    if ($fillLevel >= 80) return 'bg-red-500';
    if ($fillLevel >= 60) return 'bg-yellow-500';
    if ($fillLevel >= 30) return 'bg-blue-500';
    return 'bg-green-500';
}

// Helper function for chart gradient
function getColorGradient() {
    return 'var(--primary), var(--accent)';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartBin · Automated Waste Management</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* ============================================================
           CUSTOM COLOR PALETTE
           Primary:    #8BC34A  (Sage Green)
           Secondary:  #C5E1A5  (Light Green)
           Accent:     #4CAF50  (Forest Green)
           Background: #F5F5F5  (Light Gray)
           Text:       #424242  (Charcoal Gray)
           ============================================================ */
        
        :root {
            --primary: #8BC34A;
            --primary-dark: #7CB342;
            --secondary: #C5E1A5;
            --accent: #4CAF50;
            --accent-dark: #388E3C;
            --bg: #F5F5F5;
            --bg-white: #FFFFFF;
            --text: #424242;
            --text-light: #757575;
            --border: #E0E0E0;
            --shadow: rgba(66, 66, 66, 0.08);
            --card-bg: #FFFFFF;
            --sidebar-bg: #FFFFFF;
            --sidebar-text: #424242;
            --hover-bg: rgba(139, 195, 74, 0.08);
            /* Monitor colors - light and airy */
            --monitor-bg-start: #E8F5E9;
            --monitor-bg-end: #C8E6C9;
            --monitor-border: rgba(76, 175, 80, 0.15);
            --monitor-text: #2E7D32;
            --monitor-text-light: #558B2F;
        }
        
        /* Dark Mode */
        [data-theme="dark"] {
            --bg: #1a1a1a;
            --bg-white: #2d2d2d;
            --text: #E8E8E8;
            --text-light: #B0B0B0;
            --border: #404040;
            --shadow: rgba(0, 0, 0, 0.3);
            --card-bg: #2d2d2d;
            --sidebar-bg: #1a1a1a;
            --sidebar-text: #E8E8E8;
            --hover-bg: rgba(139, 195, 74, 0.12);
            /* Dark mode monitor - darker greens */
            --monitor-bg-start: #1B3A1B;
            --monitor-bg-end: #0D1F0D;
            --monitor-border: rgba(76, 175, 80, 0.20);
            --monitor-text: #A5D6A7;
            --monitor-text-light: #81C784;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--border); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent); }
        
        /* Sidebar Styles */
        .sidebar {
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), background 0.3s ease;
        }
        .sidebar a {
            transition: all 0.2s ease;
            color: var(--sidebar-text);
            position: relative;
        }
        .sidebar a:hover {
            background: var(--hover-bg);
            color: var(--primary);
        }
        .sidebar .active a {
            background: var(--hover-bg);
            color: var(--primary);
            border-left: 3px solid var(--primary);
        }
        
        /* Card Styles */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 8px 30px var(--shadow);
            transform: translateY(-2px);
        }
        
        /* Stats Card */
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 30px var(--shadow);
            transform: translateY(-3px);
        }
        
        /* Monitor Card - NEW DESIGN */
        .monitor-card {
            background: linear-gradient(135deg, var(--monitor-bg-start), var(--monitor-bg-end));
            border-radius: 16px;
            border: 1px solid var(--monitor-border);
            transition: all 0.3s ease;
        }
        .monitor-card:hover {
            box-shadow: 0 8px 30px rgba(76, 175, 80, 0.12);
            transform: translateY(-2px);
        }
        
            .monitor-embed {
                display: block;
                width: 100%;
                height: 760px;
                border: 0;
                border-radius: 14px;
                background: var(--card-bg);
            }
        
            .monitor-card > .legacy-monitor-panel,
            .monitor-card > .legacy-monitor-footer {
                display: none;
            }
        
        /* Bin Card inside Monitor - NEW DESIGN */
        .bin-card {
            background: rgba(255, 255, 255, 0.70);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(76, 175, 80, 0.12);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .bin-card:hover {
            background: rgba(255, 255, 255, 0.85);
            transform: scale(1.02);
            border-color: rgba(76, 175, 80, 0.25);
        }
        [data-theme="dark"] .bin-card {
            background: rgba(30, 60, 30, 0.60);
            border-color: rgba(76, 175, 80, 0.15);
        }
        [data-theme="dark"] .bin-card:hover {
            background: rgba(30, 60, 30, 0.80);
            border-color: rgba(76, 175, 80, 0.25);
        }
        
        /* Progress Ring */
        .progress-ring {
            transform: rotate(-90deg);
        }
        .progress-ring circle {
            transition: stroke-dashoffset 1.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Pulse Animation */
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }
        .pulse-dot {
            animation: pulse-dot 2s ease-in-out infinite;
        }
        
        /* Flash Message */
        .flash-message {
            animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Chart Bar Animation */
        .chart-bar {
            transition: height 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Gradient Text */
        .gradient-text {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            color: transparent;
        }
        
        /* Button Styles */
        .btn-primary {
            background: var(--primary);
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: var(--accent);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(139, 195, 74, 0.30);
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: var(--text);
            transition: all 0.3s ease;
        }
        .btn-secondary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(139, 195, 74, 0.20);
        }
        
        .btn-monitor {
            background: var(--primary);
            color: white;
            transition: all 0.3s ease;
        }
        .btn-monitor:hover {
            background: var(--accent-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.30);
        }
        
        /* Theme Toggle */
        .theme-toggle {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            transition: all 0.3s ease;
        }
        .theme-toggle:hover {
            border-color: var(--primary);
            background: var(--hover-bg);
        }
        
        /* Responsive Sidebar */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1000;
                width: 280px;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.40);
                z-index: 999;
                backdrop-filter: blur(4px);
            }
            .sidebar-overlay.active {
                display: block;
            }
        }
        
        /* Icon background colors */
        .icon-bg-primary { background: rgba(139, 195, 74, 0.12); color: var(--primary); }
        .icon-bg-secondary { background: rgba(197, 225, 165, 0.20); color: var(--accent); }
        .icon-bg-accent { background: rgba(76, 175, 80, 0.12); color: var(--accent); }
        .icon-bg-text { background: rgba(66, 66, 66, 0.06); color: var(--text); }
        
        /* Status colors */
        .status-full { background: #ef4444; }
        .status-high { background: #eab308; }
        .status-medium { background: #3b82f6; }
        .status-low { background: #22c55e; }
        
        /* Input field */
        .input-field {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            transition: all 0.3s ease;
        }
        .input-field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 195, 74, 0.15);
            outline: none;
        }
        .input-field option {
            background: var(--card-bg);
            color: var(--text);
        }
        
        /* User avatar */
        .user-avatar {
            background: linear-gradient(135deg, var(--primary), var(--accent));
        }
        
        /* Monitor text colors */
        .monitor-text {
            color: var(--monitor-text);
        }
        .monitor-text-light {
            color: var(--monitor-text-light);
        }
        
        /* Monitor status badge */
        .monitor-badge {
            background: rgba(76, 175, 80, 0.12);
            color: var(--monitor-text);
            border: 1px solid rgba(76, 175, 80, 0.10);
        }
        [data-theme="dark"] .monitor-badge {
            background: rgba(76, 175, 80, 0.15);
            color: #A5D6A7;
            border-color: rgba(76, 175, 80, 0.15);
        }

        /* AI Suggestion card */
        .ai-suggestion {
            border-left: 4px solid var(--primary);
            background: var(--card-bg);
            transition: all 0.3s ease;
        }
        .ai-suggestion.warning {
            border-left-color: #eab308;
        }
        .ai-suggestion.success {
            border-left-color: #22c55e;
        }
        .ai-suggestion.info {
            border-left-color: #3b82f6;
        }

        .chart-container {
            position: relative;
            height: 200px;
            max-height: 200px;
        }
        @media (min-width: 768px) {
            .chart-container {
                height: 250px;
                max-height: 250px;
            }
        }
    </style>
</head>
<body>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- ================================================================
    SIDEBAR
    ================================================================ -->
    <nav class="sidebar fixed top-0 left-0 h-full w-[270px] p-6 flex flex-col shadow-xl overflow-y-auto" id="sidebar">
        
      <!-- Brand -->
<div class="flex items-center gap-3 pb-5 mb-5 border-b border-[var(--border)]">
    <!-- Logo image -->
    <div class="w-15 h-15 rounded-xl bg-[var(--primary)]/20 flex items-center justify-center overflow-hidden">
        <img src="logo5.png" alt="CT-ABS Logo" class="w-full h-full object-cover">
    </div>
    <div>
        <a href="index.php" class="text-xl font-bold tracking-tight hover:opacity-80 transition">
            CT-<span class="text-[var(--primary)]">ABS</span>
        </a>
        <span class="block text-[10px] uppercase tracking-wider text-[var(--text-light)] font-medium">Automated Bin Segregation</span>
    </div>
</div>
        
        <!-- Navigation -->
        <div class="text-[10px] uppercase tracking-wider text-[var(--text-light)]/60 font-semibold mt-1 mb-3 px-3">Main</div>
        <ul class="flex-1 space-y-0.5">
            <li class="active">
                <a href="index.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-all duration-200">
                    <i class="fas fa-chart-pie w-5 text-[var(--text-light)]"></i> 
                    <span class="font-medium">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-users w-5 text-[var(--text-light)]"></i> 
                    <span>User Management</span>
                </a>
            </li>
            <?php if ($role === 'admin'): ?>
            <li>
                <a href="userlogs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-clipboard-list w-5 text-[var(--text-light)]"></i>
                    <span>User Logs</span>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="binloc.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-microchip w-5 text-[var(--text-light)]"></i> 
                    <span>Trash Bin List</span>
                </a>
            </li>
            
        </ul>
        
        <div class="text-[10px] uppercase tracking-wider text-[var(--text-light)]/60 font-semibold mt-4 mb-3 px-3">System</div>
        <ul class="space-y-0.5">
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-cogs w-5 text-[var(--text-light)]"></i> 
                    <span>Shredder</span>
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-solar-panel w-5 text-[var(--text-light)]"></i> 
                    <span>Solar Power</span>
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-bell w-5 text-[var(--text-light)]"></i> 
                    <span>Alerts</span>
                    <?php if ($alertCount > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo $alertCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
        
        <div class="text-[10px] uppercase tracking-wider text-[var(--text-light)]/60 font-semibold mt-4 mb-3 px-3">Admin</div>
        <ul class="space-y-0.5">
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-user-cog w-5 text-[var(--text-light)]"></i> 
                    <span>Settings</span>
                </a>
            </li>
            <li>
                <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-red-500/10 hover:text-red-500 transition-all duration-200">
                    <i class="fas fa-sign-out-alt w-5 text-[var(--text-light)]"></i> 
                    <span>Logout</span>
                </a>
            </li>
        </ul>
        
        <!-- Status -->
        <div class="mt-5 pt-4 border-t border-[var(--border)] flex items-center gap-2 text-xs text-[var(--text-light)]">
            <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-[var(--primary)] pulse-dot"></span>
                Online
            </span>
            <span class="ml-auto bg-[var(--hover-bg)] px-2.5 py-0.5 rounded-full text-[10px] font-medium">v2.0</span>
        </div>
    </nav>
    
    <!-- ================================================================
    MAIN CONTENT
    ================================================================ -->
    <div class="md:ml-[270px] p-4 md:p-8 min-h-screen transition-all duration-300">
        
        <!-- Flash Messages -->
        <?php if ($flash): ?>
            <div class="flash-message mb-5 p-4 rounded-xl flex items-center gap-3 text-sm font-medium
                <?php echo $flash['type'] === 'success' 
                    ? 'bg-[var(--primary)] text-white' 
                    : 'bg-red-500 text-white'; ?>">
                <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> text-lg"></i>
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- ============================================================
        TOP BAR
        ============================================================ -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <button class="md:hidden text-2xl text-[var(--text)] hover:scale-110 transition-transform" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-[var(--text)]">
                        Dashboard
                    </h1>
                    <p class="text-sm text-[var(--text-light)] font-medium mt-0.5">
                        Automated Trash Bin · Real-time Monitoring
                    </p>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <!-- Theme Toggle -->
                <button class="theme-toggle px-3 py-2 rounded-xl text-sm font-medium flex items-center gap-2" id="themeToggle">
                    <i class="fas fa-moon" id="themeIcon"></i>
                    <span id="themeLabel" class="hidden sm:inline">Dark</span>
                </button>
                
                <!-- User Profile -->
                <a href="logout.php" class="flex items-center gap-3 bg-[var(--card-bg)] px-3 py-2 rounded-xl border border-[var(--border)] hover:border-[var(--primary)] transition-all cursor-pointer group">
                    <div class="user-avatar w-9 h-9 rounded-xl flex items-center justify-center text-white font-bold text-sm">
                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-sm font-semibold text-[var(--text)] leading-tight"><?php echo htmlspecialchars($full_name); ?></p>
                        <p class="text-[10px] text-[var(--text-light)] font-medium"><?php echo htmlspecialchars($role); ?></p>
                    </div>
                    <i class="fas fa-sign-out-alt text-sm text-[var(--text-light)] group-hover:text-red-500 transition-colors"></i>
                </a>
            </div>
        </div>
        
        <!-- ============================================================
        ACTIVE BIN SELECTOR
        ============================================================ -->
        <div class="card p-4 mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[var(--primary)]/10 flex items-center justify-center">
                    <i class="fas fa-trash-alt text-[var(--primary)]"></i>
                </div>
                <div>
                    <span class="text-xs font-medium text-[var(--text-light)] uppercase tracking-wider">Active Bin</span>
                    <?php if ($activeBin): ?>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold text-[var(--text)]"><?php echo htmlspecialchars($activeBin['bin_name']); ?></span>
                        <span class="text-xs text-[var(--text-light)]">•</span>
                        <span class="text-xs text-[var(--text-light)]"><?php echo htmlspecialchars($activeBin['location']); ?></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-[var(--primary)] pulse-dot"></span>
                    </div>
                    <?php else: ?>
                    <p class="text-sm text-[var(--text-light)]">No active bin selected</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($allBins)): ?>
            <div class="flex items-center gap-2 flex-wrap">
                <select id="binSelector" onchange="changeActiveBin(this.value)" 
                        class="input-field px-4 py-2.5 rounded-xl text-sm min-w-[200px] font-medium">
                    <?php foreach ($allBins as $bin): ?>
                    <option value="<?php echo $bin['bin_id']; ?>" <?php echo ($activeBin && $activeBin['bin_id'] == $bin['bin_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($bin['bin_name'] . ' — ' . $bin['location']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <a href="binloc.php" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-medium shadow-sm">
                    <i class="fas fa-sliders-h mr-1.5"></i> Manage
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- ============================================================
        STATS GRID
        ============================================================ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total Users -->
            <div class="stat-card p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[var(--text-light)]">
                        <i class="fas fa-users mr-1.5"></i> Total Users
                    </span>
                    <div class="w-9 h-9 rounded-xl icon-bg-primary flex items-center justify-center">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="text-2xl md:text-3xl font-bold text-[var(--text)] mt-2"><?php echo number_format($total_users); ?></div>
                <div class="text-xs text-[var(--text-light)] mt-1">Registered accounts</div>
            </div>
            
            <!-- Today's Disposals -->
            <div class="stat-card p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[var(--text-light)]">
                        <i class="fas fa-trash-alt mr-1.5"></i> Today's Disposals
                    </span>
                    <div class="w-9 h-9 rounded-xl icon-bg-secondary flex items-center justify-center">
                        <i class="fas fa-recycle"></i>
                    </div>
                </div>
                <div class="text-2xl md:text-3xl font-bold text-[var(--text)] mt-2"><?php echo number_format($todayStats['total_disposals'] ?? 0); ?></div>
                <div class="text-xs text-[var(--text-light)] mt-1">Items disposed</div>
            </div>
            
            <!-- Shredded Today -->
            <div class="stat-card p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[var(--text-light)]">
                        <i class="fas fa-cut mr-1.5"></i> Shredded Today
                    </span>
                    <div class="w-9 h-9 rounded-xl icon-bg-accent flex items-center justify-center">
                        <i class="fas fa-cut"></i>
                    </div>
                </div>
                <div class="text-2xl md:text-3xl font-bold text-[var(--text)] mt-2"><?php echo number_format($todayStats['shredded_count'] ?? 0); ?></div>
                <div class="text-xs text-[var(--text-light)] mt-1">Items processed</div>
            </div>
            
            <!-- Alerts -->
            <div class="stat-card p-5 <?php echo $alertCount > 0 ? 'border-red-500/30' : ''; ?>">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[var(--text-light)]">
                        <i class="fas fa-bell mr-1.5"></i> Alerts
                    </span>
                    <div class="w-9 h-9 rounded-xl <?php echo $alertCount > 0 ? 'bg-red-500/10 text-red-500' : 'icon-bg-text'; ?> flex items-center justify-center">
                        <i class="fas fa-bell"></i>
                    </div>
                </div>
                <div id="dashboardAlertCount" class="text-2xl md:text-3xl font-bold <?php echo $alertCount > 0 ? 'text-red-500' : 'text-[var(--text)]'; ?> mt-2"><?php echo $alertCount; ?></div>
                <div class="text-xs <?php echo $alertCount > 0 ? 'text-red-500/70' : 'text-[var(--text-light)]'; ?> mt-1">
                    <?php echo $alertCount > 0 ? '⚠️ Needs attention' : 'All systems OK'; ?>
                </div>
            </div>
        </div>
        
        <!-- ============================================================
        LIVE COMMUNITY MONITOR - REFRESHED GREEN THEME
        ============================================================ -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-bold text-[var(--text)]">
                    <i class="fas fa-tv text-[var(--primary)] mr-2"></i> Live Monitor
                </h2>
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1.5 text-xs font-semibold text-[var(--primary)]">
                        <span class="w-1.5 h-1.5 rounded-full bg-[var(--primary)] pulse-dot"></span>
                        LIVE
                    </span>
                    <a href="monitor.php" target="_blank" class="btn-monitor text-xs px-3 py-1.5 rounded-xl shadow-sm">
                        <i class="fas fa-external-link-alt text-[10px] mr-1"></i> Full Screen
                    </a>
                </div>
            </div>
            
            <!-- Monitor Card - Green Theme -->
            <div class="monitor-card p-6">
                <iframe class="monitor-embed" src="monitor.php" title="Live five-bin community monitor" loading="lazy"></iframe>

                <div class="legacy-monitor-panel grid grid-cols-1 md:grid-cols-3 gap-5">
                    
                    <!-- Biodegradable -->
                    <div class="bin-card p-5 text-center">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-semibold monitor-text">
                                <i class="fas fa-leaf text-[var(--primary)] mr-2"></i> Biodegradable
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide <?php echo getStatusBadge($bioFill); ?>">
                                <?php echo getStatusText($bioFill); ?>
                            </span>
                        </div>
                        <div class="relative w-28 h-28 mx-auto">
                            <svg class="w-28 h-28 progress-ring" viewBox="0 0 120 120">
                                <circle cx="60" cy="60" r="45" fill="none" stroke="rgba(139, 195, 74, 0.15)" stroke-width="10"/>
                                <circle cx="60" cy="60" r="45" fill="none" 
                                        stroke="#8BC34A" 
                                        stroke-width="10"
                                        stroke-linecap="round"
                                        stroke-dasharray="282.74"
                                        stroke-dashoffset="282.74"
                                        id="preview-bio"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-2xl font-bold monitor-text">
                                    <span data-monitor-value="bio"><?php echo number_format($bioFill, 0); ?></span>%
                                </span>
                            </div>
                        </div>
                        <div class="mt-2 text-[10px] font-medium monitor-text-light uppercase tracking-wider">Ultrasonic Level</div>
                    </div>
                    
                    <!-- Plastic -->
                    <div class="bin-card p-5 text-center">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-semibold monitor-text">
                                <i class="fas fa-bottle-water text-[var(--secondary)] mr-2"></i> Plastic
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide <?php echo getStatusBadge($plasticFill); ?>">
                                <?php echo getStatusText($plasticFill); ?>
                            </span>
                        </div>
                        <div class="relative w-28 h-28 mx-auto">
                            <svg class="w-28 h-28 progress-ring" viewBox="0 0 120 120">
                                <circle cx="60" cy="60" r="45" fill="none" stroke="rgba(197, 225, 165, 0.20)" stroke-width="10"/>
                                <circle cx="60" cy="60" r="45" fill="none" 
                                        stroke="#C5E1A5" 
                                        stroke-width="10"
                                        stroke-linecap="round"
                                        stroke-dasharray="282.74"
                                        stroke-dashoffset="282.74"
                                        id="preview-plastic"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-2xl font-bold monitor-text">
                                    <span data-monitor-value="plastic"><?php echo number_format($plasticFill, 0); ?></span>%
                                </span>
                            </div>
                        </div>
                        <div class="mt-2 text-[10px] font-medium monitor-text-light uppercase tracking-wider">Ultrasonic Level</div>
                    </div>
                    
                    <!-- Metal -->
                    <div class="bin-card p-5 text-center">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-semibold monitor-text">
                                <i class="fas fa-cubes text-[var(--accent)] mr-2"></i> Metal
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide <?php echo getStatusBadge($metalFill); ?>">
                                <?php echo getStatusText($metalFill); ?>
                            </span>
                        </div>
                        <div class="relative w-28 h-28 mx-auto">
                            <svg class="w-28 h-28 progress-ring" viewBox="0 0 120 120">
                                <circle cx="60" cy="60" r="45" fill="none" stroke="rgba(76, 175, 80, 0.15)" stroke-width="10"/>
                                <circle cx="60" cy="60" r="45" fill="none" 
                                        stroke="#4CAF50" 
                                        stroke-width="10"
                                        stroke-linecap="round"
                                        stroke-dasharray="282.74"
                                        stroke-dashoffset="282.74"
                                        id="preview-metal"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-2xl font-bold monitor-text">
                                    <span data-monitor-value="metal"><?php echo number_format($metalFill, 0); ?></span>%
                                </span>
                            </div>
                        </div>
                        <div class="mt-2 text-[10px] font-medium monitor-text-light uppercase tracking-wider">Ultrasonic Level</div>
                    </div>
                </div>
                
                <!-- Monitor Footer - Green Theme -->
                <div class="legacy-monitor-footer mt-5 pt-4 border-t border-[var(--monitor-border)] flex flex-wrap justify-between items-center text-[10px] font-medium monitor-text-light gap-2">
                    <span><i class="fas fa-signal mr-1.5"></i> Signal: Strong</span>
                    <span><i class="fas fa-bolt mr-1.5"></i> Connected</span>
                    <span><i class="fas fa-sync-alt mr-1.5"></i> <span id="previewTime">Updating...</span></span>
                    <span><i class="fas fa-exclamation-triangle mr-1.5 <?php echo $alertCount > 0 ? 'text-yellow-600' : 'text-[var(--text-light)]/30'; ?>"></i> <?php echo $alertCount; ?> active alerts</span>
                    <span><i class="fas fa-clock mr-1.5"></i> Last: <?php echo $lastDisposalTime; ?></span>
                </div>
            </div>
        </div>
        
        <!-- ============================================================
        PANELS GRID
        ============================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
            
            <!-- Bin Fill Levels (2 columns) -->
            <div class="lg:col-span-2 card p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-bold text-[var(--text)]">
                        <i class="fas fa-chart-simple text-[var(--primary)] mr-2"></i> Fill Levels
                    </h3>
                    <span class="text-[10px] font-medium text-[var(--text-light)] uppercase tracking-wider">Ultrasonic Sensors</span>
                </div>
                
                <!-- Biodegradable -->
                <div class="flex items-center justify-between py-3.5 border-b border-[var(--border)]">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-[var(--primary)]/10 flex items-center justify-center">
                            <i class="fas fa-leaf text-[var(--primary)]"></i>
                        </div>
                        <span class="font-medium text-[var(--text)]">Biodegradable</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-32 h-1.5 bg-[var(--border)] rounded-full overflow-hidden">
                            <div class="h-full <?php echo getFillColor($bioFill); ?> rounded-full transition-all duration-1000" style="width:<?php echo $bioFill; ?>%"></div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-[var(--bg)] text-[var(--text)] min-w-[48px] text-center"><?php echo number_format($bioFill, 0); ?>%</span>
                    </div>
                </div>
                
                <!-- Plastic -->
                <div class="flex items-center justify-between py-3.5 border-b border-[var(--border)]">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-[var(--secondary)]/20 flex items-center justify-center">
                            <i class="fas fa-bottle-water text-[var(--accent)]"></i>
                        </div>
                        <span class="font-medium text-[var(--text)]">Plastic</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-32 h-1.5 bg-[var(--border)] rounded-full overflow-hidden">
                            <div class="h-full <?php echo getFillColor($plasticFill); ?> rounded-full transition-all duration-1000" style="width:<?php echo $plasticFill; ?>%"></div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-[var(--bg)] text-[var(--text)] min-w-[48px] text-center"><?php echo number_format($plasticFill, 0); ?>%</span>
                    </div>
                </div>
                
                <!-- Metal -->
                <div class="flex items-center justify-between py-3.5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-[var(--accent)]/10 flex items-center justify-center">
                            <i class="fas fa-cube text-[var(--accent)]"></i>
                        </div>
                        <span class="font-medium text-[var(--text)]">Metal</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-32 h-1.5 bg-[var(--border)] rounded-full overflow-hidden">
                            <div class="h-full <?php echo getFillColor($metalFill); ?> rounded-full transition-all duration-1000" style="width:<?php echo $metalFill; ?>%"></div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-[var(--bg)] text-[var(--text)] min-w-[48px] text-center"><?php echo number_format($metalFill, 0); ?>%</span>
                    </div>
                </div>
                
                <!-- Today's Summary -->
                <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3 p-4 rounded-xl bg-[var(--bg)] border border-[var(--border)]">
                    <div class="text-center">
                        <div class="text-[10px] font-medium uppercase tracking-wider text-[var(--text-light)]">Total</div>
                        <div class="text-lg font-bold text-[var(--text)]"><?php echo number_format($todayStats['total_disposals'] ?? 0); ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-[10px] font-medium uppercase tracking-wider text-[var(--text-light)]">Bio</div>
                        <div class="text-lg font-bold text-[var(--primary)]"><?php echo number_format($todayStats['bio_count'] ?? 0); ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-[10px] font-medium uppercase tracking-wider text-[var(--text-light)]">Plastic</div>
                        <div class="text-lg font-bold text-[var(--accent)]"><?php echo number_format($todayStats['plastic_count'] ?? 0); ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-[10px] font-medium uppercase tracking-wider text-[var(--text-light)]">Metal</div>
                        <div class="text-lg font-bold text-[#757575]"><?php echo number_format($todayStats['metal_count'] ?? 0); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity (1 column) -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-bold text-[var(--text)]">
                        <i class="fas fa-clock text-[var(--primary)] mr-2"></i> Recent Activity
                    </h3>
                    <span class="text-[10px] font-medium text-[var(--text-light)] uppercase tracking-wider">Last 5 logs</span>
                </div>
                
                <?php if (count($recentLogs) > 0): ?>
                    <div class="space-y-2">
                        <?php foreach ($recentLogs as $log): ?>
                        <div class="flex items-center justify-between p-2.5 rounded-xl hover:bg-[var(--bg)] transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center
                                    <?php echo $log['bin_type'] == 'biodegradable' ? 'bg-[var(--primary)]/10 text-[var(--primary)]' : 
                                        ($log['bin_type'] == 'plastic' ? 'bg-[var(--accent)]/10 text-[var(--accent)]' : 
                                        'bg-[var(--text)]/10 text-[var(--text)]'); ?>">
                                    <i class="fas fa-<?php echo $log['bin_type'] == 'biodegradable' ? 'leaf' : ($log['bin_type'] == 'plastic' ? 'bottle-water' : 'cube'); ?> text-xs"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-[var(--text)] capitalize"><?php echo $log['bin_type']; ?></p>
                                    <p class="text-[10px] text-[var(--text-light)]"><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full 
                                    <?php echo $log['status'] == 'success' ? 'bg-[var(--primary)]/10 text-[var(--primary)]' : 
                                        ($log['status'] == 'failed' ? 'bg-red-500/10 text-red-500' : 
                                        'bg-yellow-500/10 text-yellow-500'); ?>">
                                    <?php echo $log['is_shredded'] ? 'Shredded' : 'Collected'; ?>
                                </span>
                                <p class="text-[10px] text-[var(--text-light)] mt-0.5"><?php echo date('H:i', strtotime($log['created_at'])); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 p-3 rounded-xl bg-[var(--bg)] text-xs font-medium text-[var(--text)] flex items-center gap-2">
                        <i class="fas fa-database text-[var(--primary)]"></i>
                        <?php echo count($recentLogs); ?> recent records
                    </div>
                <?php else: ?>
                    <div class="text-center py-10">
                        <div class="w-16 h-16 rounded-2xl bg-[var(--bg)] flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-inbox text-2xl text-[var(--text-light)]/30"></i>
                        </div>
                        <p class="text-sm font-medium text-[var(--text-light)]">No records yet</p>
                        <p class="text-xs text-[var(--text-light)]/60 mt-0.5">Waste logs will appear here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ============================================================
        WEEKLY OVERVIEW + CHARTS ROW
        ============================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            
            <!-- Weekly Bar Chart (existing) -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-bold text-[var(--text)]">
                        <i class="fas fa-chart-bar text-[var(--primary)] mr-2"></i> Weekly Overview
                    </h3>
                    <span class="text-[10px] font-medium text-[var(--text-light)] uppercase tracking-wider">Last 7 days</span>
                </div>
                
                <?php if (count($weeklyStats) > 0): ?>
                    <div class="grid grid-cols-2 md:grid-cols-7 gap-3">
                        <?php 
                        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        $dayData = [];
                        foreach ($weeklyStats as $stat) {
                            $dayIndex = date('N', strtotime($stat['log_date'])) - 1;
                            $dayData[$dayIndex] = $stat;
                        }
                        $maxCount = max(array_column($weeklyStats, 'daily_disposals')) ?: 1;
                        for ($i = 0; $i < 7; $i++) {
                            $data = isset($dayData[$i]) ? $dayData[$i] : null;
                            $percentage = $data ? ($data['daily_disposals'] / $maxCount * 100) : 0;
                        ?>
                        <div class="text-center p-3 rounded-xl bg-[var(--bg)]">
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--text-light)]"><?php echo $days[$i]; ?></div>
                            <div class="h-20 flex items-end justify-center mt-2">
                                <div class="w-full max-w-8 rounded-t-lg transition-all duration-1000 chart-bar" 
                                     style="height: <?php echo max(6, $percentage); ?>%; 
                                            <?php echo $data 
                                                ? 'background: linear-gradient(180deg, var(--primary), var(--accent));' 
                                                : 'background: var(--border); opacity: 0.3;'; ?>">
                                </div>
                            </div>
                            <div class="text-sm font-bold text-[var(--text)] mt-1.5">
                                <?php echo $data ? number_format($data['daily_disposals']) : '0'; ?>
                            </div>
                            <div class="text-[9px] font-medium text-[var(--text-light)]/60 uppercase tracking-wider">items</div>
                        </div>
                        <?php } ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-sm font-medium text-[var(--text-light)]">No weekly data available</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Waste Type Distribution (Pie Chart) -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-[var(--text)]">
                        <i class="fas fa-chart-pie text-[var(--primary)] mr-2"></i> Waste Type Distribution
                    </h3>
                    <span class="text-[10px] font-medium text-[var(--text-light)] uppercase tracking-wider">Today</span>
                </div>
                <div class="chart-container">
                    <canvas id="pieChart"></canvas>
                </div>
                <div class="mt-3 flex flex-wrap justify-center gap-4 text-xs font-medium">
                    <span><span class="inline-block w-3 h-3 rounded-full bg-[#8BC34A] mr-1"></span> Bio: <?php echo $chartData['bio']; ?></span>
                    <span><span class="inline-block w-3 h-3 rounded-full bg-[#C5E1A5] mr-1"></span> Plastic: <?php echo $chartData['plastic']; ?></span>
                    <span><span class="inline-block w-3 h-3 rounded-full bg-[#4CAF50] mr-1"></span> Metal: <?php echo $chartData['metal']; ?></span>
                </div>
            </div>
        </div>

        <!-- ============================================================
        AI SUGGESTION + TREND CHART ROW
        ============================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <!-- AI Suggestion -->
            <div class="lg:col-span-1">
                <div class="ai-suggestion <?php echo $suggestionType; ?> rounded-xl p-5 border border-[var(--border)] h-full flex flex-col">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-robot text-2xl text-[var(--primary)]"></i>
                        <span class="font-bold text-[var(--text)]">AI Suggestion</span>
                        <span class="ml-auto text-[10px] font-medium text-[var(--text-light)] uppercase tracking-wider">Live</span>
                    </div>
                    <p class="text-sm text-[var(--text)] leading-relaxed flex-1"><?php echo $aiSuggestion; ?></p>
                    <div class="mt-4 pt-3 border-t border-[var(--border)] flex justify-between text-[10px] text-[var(--text-light)]">
                        <span><i class="far fa-clock mr-1"></i> <?php echo date('H:i'); ?></span>
                        <span><i class="fas fa-sync-alt mr-1"></i> Auto-updates</span>
                    </div>
                </div>
            </div>

            <!-- Daily Trend Line Chart -->
            <div class="lg:col-span-2 card p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-[var(--text)]">
                        <i class="fas fa-chart-line text-[var(--primary)] mr-2"></i> Daily Trend
                    </h3>
                    <span class="text-[10px] font-medium text-[var(--text-light)] uppercase tracking-wider">Last 7 days</span>
                </div>
                <div class="chart-container">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- ============================================================
        SYSTEM INFO
        ============================================================ -->
        <div class="card p-4 flex flex-wrap gap-2 mb-6">
            <span class="px-3 py-1.5 rounded-xl bg-[var(--bg)] text-xs font-medium text-[var(--text)]">
                <i class="fas fa-check-circle text-[var(--primary)] mr-1.5"></i> Segregation success: <strong id="systemSegregation"><?php echo htmlspecialchars($systemMetrics['segregation']); ?></strong>
            </span>
            <span class="px-3 py-1.5 rounded-xl bg-[var(--bg)] text-xs font-medium text-[var(--text)]">
                <i class="fas fa-gauge-high text-[var(--primary)] mr-1.5"></i> Performance: <strong id="systemPerformance"><?php echo htmlspecialchars($systemMetrics['performance']); ?></strong>
            </span>
            <span class="px-3 py-1.5 rounded-xl bg-[var(--bg)] text-xs font-medium text-[var(--text)]">
                <i class="fas fa-thumbs-up text-[var(--primary)] mr-1.5"></i> Acceptability: <strong id="systemAcceptability"><?php echo htmlspecialchars($systemMetrics['acceptability']); ?></strong>
            </span>
            <span class="px-3 py-1.5 rounded-xl bg-[var(--bg)] text-xs font-medium text-[var(--text)]">
                <i class="fas fa-tools text-[var(--primary)] mr-1.5"></i> Maintainability: <strong id="systemMaintainability"><?php echo htmlspecialchars($systemMetrics['maintainability']); ?></strong>
            </span>
            <span class="px-3 py-1.5 rounded-xl bg-[var(--bg)] text-xs font-medium text-[var(--text-light)]">
                <i class="fas fa-clock mr-1.5"></i> ISO 25010
            </span>
        </div>
        
        <!-- ============================================================
        USER INFO & FOOTER
        ============================================================ -->
        <div class="flex flex-wrap justify-between gap-3 text-xs font-medium text-[var(--text-light)]">
            <div class="flex flex-wrap items-center gap-2">
                <span><i class="fas fa-user mr-1.5"></i> Logged in as: <strong class="text-[var(--text)]"><?php echo htmlspecialchars($full_name); ?></strong></span>
                <span class="text-[var(--border)]">|</span>
                <span><i class="fas fa-tag mr-1.5"></i> Role: <span class="capitalize text-[var(--text)]"><?php echo htmlspecialchars($role); ?></span></span>
                <?php if ($role === 'admin'): ?>
                    <span class="px-2 py-0.5 rounded-full bg-[var(--primary)]/10 text-[var(--primary)] text-[9px] font-bold uppercase tracking-wider">Admin</span>
                <?php endif; ?>
            </div>
            <span><i class="fas fa-clock mr-1.5"></i> Session: <?php echo date('H:i', $_SESSION['login_time'] ?? time()); ?></span>
        </div>
        
        <div class="mt-6 pt-4 border-t border-[var(--border)] text-center text-[10px] font-medium text-[var(--text-light)]/50 tracking-wide">
            <i class="fas fa-microchip mr-1.5"></i> ESP32 · AI Vision · Capacitive · Inductive · Ultrasonic · Solar-Powered · Web-Based
        </div>
    </div>
    
    <!-- ================================================================
    JAVASCRIPT
    ================================================================ -->
    <script>
        (function() {
            'use strict';
            
            // ----- Hamburger Toggle -----
            const hamburger = document.getElementById('hamburgerBtn');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (hamburger && sidebar) {
                hamburger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('open');
                    if (overlay) overlay.classList.toggle('active');
                });
                
                if (overlay) {
                    overlay.addEventListener('click', function() {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('active');
                    });
                }
                
                document.addEventListener('click', function(e) {
                    if (window.innerWidth <= 768) {
                        if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                            sidebar.classList.remove('open');
                            if (overlay) overlay.classList.remove('active');
                        }
                    }
                });
            }
            
            // ----- Theme Toggle -----
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');
            const themeLabel = document.getElementById('themeLabel');
            
            // Check for saved theme preference
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
                    if (themeIcon) themeIcon.className = 'fas fa-sun';
                    if (themeLabel) themeLabel.textContent = 'Light';
                } else {
                    if (themeIcon) themeIcon.className = 'fas fa-moon';
                    if (themeLabel) themeLabel.textContent = 'Dark';
                }
            }
            
            // ----- Animate Preview Progress Rings -----
            const previewData = [
                { id: 'preview-bio', fill: <?php echo $bioFill; ?> },
                { id: 'preview-plastic', fill: <?php echo $plasticFill; ?> },
                { id: 'preview-metal', fill: <?php echo $metalFill; ?> }
            ];
            
            const circumference = 2 * Math.PI * 45;
            
            previewData.forEach(function(item, index) {
                const circle = document.getElementById(item.id);
                if (circle) {
                    const offset = circumference - (item.fill / 100) * circumference;
                    setTimeout(function() {
                        circle.style.strokeDashoffset = offset;
                    }, 300 + (index * 200));
                }
            });
            
            // ----- Update Preview Time -----
            function updatePreviewTime() {
                const el = document.getElementById('previewTime');
                if (el) {
                    const now = new Date();
                    el.textContent = 'Updated: ' + now.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                }
            }
            updatePreviewTime();
            setInterval(updatePreviewTime, 1000);

            // ----- Sync live monitor data from monitor.php -----
            const monitorApiUrl = 'monitor.php?api=status&bin_id=<?php echo (int)$activeBinId; ?>';
            function updateMonitorRing(type, value) {
                const ring = document.getElementById('preview-' + type);
                if (!ring) return;

                const circumference = 2 * Math.PI * 45;
                const offset = circumference - (Number(value) / 100) * circumference;
                ring.style.strokeDashoffset = String(offset);
            }

            function updateMonitorValue(type, value) {
                const element = document.querySelector('[data-monitor-value="' + type + '"]');
                if (!element) return;
                element.textContent = Math.max(0, Math.min(100, Number(value) || 0)).toFixed(0);
            }

            function refreshMonitorData() {
                fetch(monitorApiUrl, {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('Monitor request failed');
                    return response.json();
                })
                .then(function(data) {
                    if (!data || !data.success || !data.fill_levels) return;

                    const levels = data.fill_levels;
                    updateMonitorRing('bio', levels.biodegradable || 0);
                    updateMonitorRing('plastic', levels.plastic || 0);
                    updateMonitorRing('metal', levels.metal || 0);

                    updateMonitorValue('bio', levels.biodegradable || 0);
                    updateMonitorValue('plastic', levels.plastic || 0);
                    updateMonitorValue('metal', levels.metal || 0);

                    const alertCountEl = document.getElementById('dashboardAlertCount');
                    if (alertCountEl) {
                        alertCountEl.textContent = Number(data.alert_count || 0);
                    }

                    const metrics = data.system_metrics || {};
                    ['segregation', 'performance', 'acceptability', 'maintainability'].forEach(function(metric) {
                        const element = document.getElementById('system' + metric.charAt(0).toUpperCase() + metric.slice(1));
                        if (element && metrics[metric] !== undefined) {
                            element.textContent = metrics[metric];
                        }
                    });
                })
                .catch(function() {
                    // Ignore failed refreshes; the page still loads with the server-rendered values.
                });
            }

            refreshMonitorData();
            setInterval(refreshMonitorData, 15000);
            
            // ----- Change Active Bin -----
            window.changeActiveBin = function(binId) {
                const formData = new FormData();
                formData.append('action', 'set_active');
                formData.append('ajax', '1');
                formData.append('bin_id', binId);
                
                const selector = document.getElementById('binSelector');
                if (selector) {
                    selector.disabled = true;
                    selector.style.opacity = '0.5';
                }
                
                fetch('binloc.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to change active bin: ' + data.message);
                        if (selector) {
                            selector.disabled = false;
                            selector.style.opacity = '1';
                        }
                    }
                })
                .catch(function(error) {
                    alert('Error: ' + error);
                    if (selector) {
                        selector.disabled = false;
                        selector.style.opacity = '1';
                    }
                });
            };

            // ============================================================
            // CHARTS - using Chart.js
            // ============================================================
            // 1. Pie Chart - Waste Type Distribution
            const pieCtx = document.getElementById('pieChart');
            if (pieCtx) {
                new Chart(pieCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Biodegradable', 'Plastic', 'Metal'],
                        datasets: [{
                            data: [<?php echo $chartData['bio']; ?>, <?php echo $chartData['plastic']; ?>, <?php echo $chartData['metal']; ?>],
                            backgroundColor: ['#8BC34A', '#C5E1A5', '#4CAF50'],
                            borderColor: ['#7CB342', '#A5D6A7', '#388E3C'],
                            borderWidth: 2,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }

            // 2. Line Chart - Daily Trend
            const lineCtx = document.getElementById('lineChart');
            if (lineCtx) {
                new Chart(lineCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($trendLabels); ?>,
                        datasets: [{
                            label: 'Disposals',
                            data: <?php echo json_encode($trendValues); ?>,
                            borderColor: '#8BC34A',
                            backgroundColor: 'rgba(139, 195, 74, 0.1)',
                            borderWidth: 3,
                            pointBackgroundColor: '#4CAF50',
                            pointBorderColor: '#388E3C',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    color: 'var(--text-light)'
                                },
                                grid: {
                                    color: 'var(--border)',
                                    drawBorder: false
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: 'var(--text-light)'
                                }
                            }
                        }
                    }
                });
            }
            
        })();
    </script>
</body>
</html>