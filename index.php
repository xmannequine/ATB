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

    // Get fill levels by bin type before AI suggestion checks
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
        
        html { height: 100%; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            height: 100vh;
            overflow: hidden;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent); }
        
        /* ============================================================
           SIDEBAR
           ============================================================ */
        .sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: 258px;
            height: 100vh;
            padding: 16px 14px;
            display: flex;
            flex-direction: column;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 50;
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
        
        /* ============================================================
           MAIN WRAP — SINGLE FRAME
           ============================================================ */
        .main-wrap {
            margin-left: 258px;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 12px 16px;
        }
        
        /* Panels */
        .panel {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            min-height: 0;
            min-width: 0;
            overflow: hidden;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }
        .panel:hover {
            border-color: rgba(139, 195, 74, 0.5);
            box-shadow: 0 6px 22px var(--shadow);
        }
        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .panel-title {
            font-size: 11.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .panel-sub {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-light);
            white-space: nowrap;
        }
        .panel-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 10px 12px;
        }
        
        /* ============================================================
           STATS
           ============================================================ */
        .stats-grid {
            flex-shrink: 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 9px 11px;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            transition: all 0.25s ease;
        }
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px var(--shadow);
        }
        .stat-ico {
            width: 34px; height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }
        .stat-val { font-size: 18px; font-weight: 800; line-height: 1.1; }
        .stat-lbl {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-light);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }
        .stat-sub {
            font-size: 9.5px;
            color: var(--text-light);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }
        
        /* ============================================================
           DASHBOARD GRID — SINGLE FRAME
           ============================================================ */
        .dash-grid {
            flex: 1 1 auto;
            min-height: 0;
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            grid-template-rows: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }
        .monitor-panel { grid-column: 1 / span 5; grid-row: 1 / span 3; }
        .fill-panel    { grid-column: span 4; }
        .activity-panel{ grid-column: span 3; }
        .weekly-panel  { grid-column: span 4; }
        .ai-panel      { grid-column: span 3; }
        .pie-panel     { grid-column: span 3; }
        .trend-panel   { grid-column: span 4; }
        
        /* ============================================================
           MONITOR
           ============================================================ */
        .monitor-embed {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
            border-radius: 10px;
            background: var(--card-bg);
        }
        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: var(--primary);
        }
        
        /* ============================================================
           CHART CONTAINERS
           ============================================================ */
        .chart-container {
            position: relative;
            flex: 1 1 auto;
            min-height: 0;
            width: 100%;
        }
        
        /* ============================================================
           PROGRESS RING / BIN CARD (legacy monitor markup)
           ============================================================ */
        .bin-card {
            background: rgba(255, 255, 255, 0.70);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(76, 175, 80, 0.12);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        [data-theme="dark"] .bin-card {
            background: rgba(30, 60, 30, 0.60);
            border-color: rgba(76, 175, 80, 0.15);
        }
        .progress-ring { transform: rotate(-90deg); }
        .progress-ring circle { transition: stroke-dashoffset 1.8s cubic-bezier(0.4, 0, 0.2, 1); }
        
        /* ============================================================
           ANIMATIONS
           ============================================================ */
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }
        .pulse-dot { animation: pulse-dot 2s ease-in-out infinite; }
        
        .flash-message { animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .chart-bar { transition: height 0.8s cubic-bezier(0.4, 0, 0.2, 1); }
        
        /* ============================================================
           BUTTONS / INPUTS
           ============================================================ */
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
        
        /* ============================================================
           ICON BACKGROUNDS
           ============================================================ */
        .icon-bg-primary { background: rgba(139, 195, 74, 0.12); color: var(--primary); }
        .icon-bg-secondary { background: rgba(197, 225, 165, 0.20); color: var(--accent); }
        .icon-bg-accent { background: rgba(76, 175, 80, 0.12); color: var(--accent); }
        .icon-bg-text { background: rgba(66, 66, 66, 0.06); color: var(--text); }
        
        /* Status colors */
        .status-full { background: #ef4444; }
        .status-high { background: #eab308; }
        .status-medium { background: #3b82f6; }
        .status-low { background: #22c55e; }
        
        /* User avatar */
        .user-avatar {
            background: linear-gradient(135deg, var(--primary), var(--accent));
        }
        
        /* AI suggestion card */
        .ai-suggestion {
            border-left: 3px solid var(--primary);
            background: var(--card-bg);
            transition: all 0.3s ease;
        }
        .ai-suggestion.warning { border-left-color: #eab308; }
        .ai-suggestion.success { border-left-color: #22c55e; }
        .ai-suggestion.info { border-left-color: #3b82f6; }
        
        /* Chip */
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 999px;
            background: var(--bg);
            border: 1px solid var(--border);
            font-size: 9.5px;
            font-weight: 500;
            color: var(--text);
            white-space: nowrap;
        }
        
        /* Footer bar */
        .footer-bar {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 6px 12px;
        }
        
        /* ============================================================
           RESPONSIVE — below lg, allow normal document scrolling
           ============================================================ */
        @media (max-width: 1023px) {
            body { height: auto; min-height: 100vh; overflow: auto; }
            
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.25);
                z-index: 1000;
            }
            .sidebar.open { transform: translateX(0); }
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.40);
                z-index: 999;
                backdrop-filter: blur(4px);
            }
            .sidebar-overlay.active { display: block; }
            
            .main-wrap {
                margin-left: 0;
                height: auto;
                min-height: 100vh;
                overflow: visible;
                padding: 12px;
            }
            
            .dash-grid {
                grid-template-columns: minmax(0, 1fr);
                grid-template-rows: none;
            }
            .monitor-panel,
            .fill-panel,
            .activity-panel,
            .weekly-panel,
            .ai-panel,
            .pie-panel,
            .trend-panel {
                grid-column: auto !important;
                grid-row: auto !important;
            }
            .monitor-panel { height: 560px; }
            .panel { min-height: 210px; }
            .chart-container { min-height: 150px; }
        }
        
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- ================================================================
    SIDEBAR
    ================================================================ -->
    <nav class="sidebar" id="sidebar">
        
        <!-- Brand -->
        <div class="flex items-center gap-3 pb-4 mb-3 border-b border-[var(--border)]">
            <div class="w-11 h-11 rounded-xl bg-[var(--primary)]/20 flex items-center justify-center overflow-hidden flex-shrink-0">
                <img src="logo5.png" alt="CT-ABS Logo" class="w-full h-full object-cover">
            </div>
            <div class="min-w-0">
                <a href="index.php" class="text-lg font-bold tracking-tight hover:opacity-80 transition block leading-tight">
                    CT-<span class="text-[var(--primary)]">ABS</span>
                </a>
                <span class="block text-[9px] uppercase tracking-wider text-[var(--text-light)] font-medium truncate">Automated Bin Segregation</span>
            </div>
        </div>
        
        <!-- Navigation -->
        <div class="text-[9px] uppercase tracking-wider text-[var(--text-light)]/60 font-semibold mb-2 px-3">Main</div>
        <ul class="flex-1 space-y-0.5">
            <li class="active">
                <a href="index.php" class="flex items-center gap-3 px-3 py-2 rounded-xl transition-all duration-200">
                    <i class="fas fa-chart-pie w-4 text-[var(--primary)] text-sm"></i> 
                    <span class="font-medium text-sm">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="users.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-users w-4 text-[var(--text-light)] text-sm"></i> 
                    <span class="text-sm">User Management</span>
                </a>
            </li>
            <?php if ($role === 'admin'): ?>
            <li>
                <a href="userlogs.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-clipboard-list w-4 text-[var(--text-light)] text-sm"></i>
                    <span class="text-sm">User Logs</span>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="binloc.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-microchip w-4 text-[var(--text-light)] text-sm"></i> 
                    <span class="text-sm">Trash Bin List</span>
                </a>
            </li>
        </ul>
        
        <div class="text-[9px] uppercase tracking-wider text-[var(--text-light)]/60 font-semibold mt-4 mb-2 px-3">System</div>
        <ul class="space-y-0.5">
            <li>
                <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-cogs w-4 text-[var(--text-light)] text-sm"></i> 
                    <span class="text-sm">Shredder</span>
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-solar-panel w-4 text-[var(--text-light)] text-sm"></i> 
                    <span class="text-sm">Solar Power</span>
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-bell w-4 text-[var(--text-light)] text-sm"></i> 
                    <span class="text-sm">Alerts</span>
                    <?php if ($alertCount > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-[9px] font-bold px-2 py-0.5 rounded-full"><?php echo $alertCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
        
        <div class="text-[9px] uppercase tracking-wider text-[var(--text-light)]/60 font-semibold mt-4 mb-2 px-3">Admin</div>
        <ul class="space-y-0.5">
            <li>
                <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-user-cog w-4 text-[var(--text-light)] text-sm"></i> 
                    <span class="text-sm">Settings</span>
                </a>
            </li>
            <li>
                <a href="logout.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-[var(--text-light)] hover:bg-red-500/10 hover:text-red-500 transition-all duration-200">
                    <i class="fas fa-sign-out-alt w-4 text-[var(--text-light)] text-sm"></i> 
                    <span class="text-sm">Logout</span>
                </a>
            </li>
        </ul>
        
        <!-- Status -->
        <div class="mt-4 pt-3 border-t border-[var(--border)]">
            <div class="flex items-center gap-2 text-[10px] text-[var(--text-light)]">
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-[var(--primary)] pulse-dot"></span>
                    Online
                </span>
                <span class="ml-auto bg-[var(--hover-bg)] px-2 py-0.5 rounded-full text-[9px] font-medium">v2.0</span>
            </div>
            <p class="mt-2 text-[8px] leading-relaxed text-[var(--text-light)]/60 tracking-wide text-center">
                <i class="fas fa-microchip mr-1"></i>ESP32 · AI Vision · Capacitive · Inductive · Ultrasonic · Solar-Powered · Web-Based
            </p>
        </div>
    </nav>
    
    <!-- ================================================================
    MAIN CONTENT — SINGLE FRAME
    ================================================================ -->
    <div class="main-wrap">
        
        <!-- Flash Messages -->
        <?php if ($flash): ?>
            <div class="flash-message flex-shrink-0 p-3 rounded-xl flex items-center gap-2.5 text-xs font-medium
                <?php echo $flash['type'] === 'success' 
                    ? 'bg-[var(--primary)] text-white' 
                    : 'bg-red-500 text-white'; ?>">
                <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- ============================================================
        TOP BAR
        ============================================================ -->
        <header class="topbar flex-shrink-0 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <button class="lg:hidden text-xl text-[var(--text)] hover:scale-110 transition-transform" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="min-w-0">
                    <h1 class="text-lg md:text-xl font-bold tracking-tight text-[var(--text)] leading-tight truncate">
                        Dashboard
                    </h1>
                    <p class="text-[10.5px] text-[var(--text-light)] font-medium truncate">
                        Automated Trash Bin · Real-time Monitoring
                    </p>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <!-- Theme Toggle -->
                <button class="theme-toggle px-2.5 py-1.5 rounded-xl text-xs font-medium flex items-center gap-2" id="themeToggle">
                    <i class="fas fa-moon" id="themeIcon"></i>
                    <span id="themeLabel" class="hidden sm:inline">Dark</span>
                </button>
                
                <!-- User Profile -->
                <a href="logout.php" class="flex items-center gap-2.5 bg-[var(--card-bg)] px-2.5 py-1.5 rounded-xl border border-[var(--border)] hover:border-[var(--primary)] transition-all cursor-pointer group">
                    <div class="user-avatar w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-xs">
                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                    </div>
                    <div class="hidden sm:block text-left leading-tight">
                        <p class="text-xs font-semibold text-[var(--text)]"><?php echo htmlspecialchars($full_name); ?></p>
                        <p class="text-[9px] text-[var(--text-light)] font-medium capitalize"><?php echo htmlspecialchars($role); ?></p>
                    </div>
                    <i class="fas fa-sign-out-alt text-xs text-[var(--text-light)] group-hover:text-red-500 transition-colors"></i>
                </a>
            </div>
        </header>
        
        <!-- ============================================================
        ACTIVE BIN SELECTOR
        ============================================================ -->
        <div class="binbar flex-shrink-0 flex flex-wrap items-center justify-between gap-2.5 bg-[var(--card-bg)] border border-[var(--border)] rounded-2xl px-3 py-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-[var(--primary)]/10 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-trash-alt text-[var(--primary)] text-xs"></i>
                </div>
                <div class="min-w-0">
                    <span class="block text-[8.5px] font-bold text-[var(--text-light)] uppercase tracking-wider">Active Bin</span>
                    <?php if ($activeBin): ?>
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span class="text-xs font-bold text-[var(--text)] truncate"><?php echo htmlspecialchars($activeBin['bin_name']); ?></span>
                        <span class="text-[10px] text-[var(--text-light)]">•</span>
                        <span class="text-[10px] text-[var(--text-light)] truncate"><?php echo htmlspecialchars($activeBin['location']); ?></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-[var(--primary)] pulse-dot flex-shrink-0"></span>
                    </div>
                    <?php else: ?>
                    <p class="text-[11px] text-[var(--text-light)]">No active bin selected</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($allBins)): ?>
            <div class="flex items-center gap-2 flex-wrap">
                <select id="binSelector" onchange="changeActiveBin(this.value)" 
                        class="input-field px-3 py-1.5 rounded-xl text-xs min-w-[190px] font-medium">
                    <?php foreach ($allBins as $bin): ?>
                    <option value="<?php echo $bin['bin_id']; ?>" <?php echo ($activeBin && $activeBin['bin_id'] == $bin['bin_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($bin['bin_name'] . ' — ' . $bin['location']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <a href="binloc.php" class="btn-primary px-3 py-1.5 rounded-xl text-xs font-medium shadow-sm">
                    <i class="fas fa-sliders-h mr-1"></i> Manage
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- ============================================================
        STATS GRID
        ============================================================ -->
        <div class="stats-grid">
            <!-- Total Users -->
            <div class="stat-card">
                <div class="stat-ico icon-bg-primary"><i class="fas fa-users"></i></div>
                <div class="min-w-0">
                    <span class="stat-lbl">Total Users</span>
                    <div class="stat-val text-[var(--text)]"><?php echo number_format($total_users); ?></div>
                    <span class="stat-sub">Registered accounts</span>
                </div>
            </div>
            
            <!-- Today's Disposals -->
            <div class="stat-card">
                <div class="stat-ico icon-bg-secondary"><i class="fas fa-recycle"></i></div>
                <div class="min-w-0">
                    <span class="stat-lbl">Today's Disposals</span>
                    <div class="stat-val text-[var(--text)]"><?php echo number_format($todayStats['total_disposals'] ?? 0); ?></div>
                    <span class="stat-sub">Items disposed</span>
                </div>
            </div>
            
            <!-- Shredded Today -->
            <div class="stat-card">
                <div class="stat-ico icon-bg-accent"><i class="fas fa-cut"></i></div>
                <div class="min-w-0">
                    <span class="stat-lbl">Shredded Today</span>
                    <div class="stat-val text-[var(--text)]"><?php echo number_format($todayStats['shredded_count'] ?? 0); ?></div>
                    <span class="stat-sub">Items processed</span>
                </div>
            </div>
            
            <!-- Alerts -->
            <div class="stat-card <?php echo $alertCount > 0 ? 'border-red-500/40' : ''; ?>">
                <div class="stat-ico <?php echo $alertCount > 0 ? 'bg-red-500/10 text-red-500' : 'icon-bg-text'; ?>">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="min-w-0">
                    <span class="stat-lbl">Alerts</span>
                    <div id="dashboardAlertCount" class="stat-val <?php echo $alertCount > 0 ? 'text-red-500' : 'text-[var(--text)]'; ?>"><?php echo $alertCount; ?></div>
                    <span class="stat-sub <?php echo $alertCount > 0 ? 'text-red-500/80' : ''; ?>">
                        <?php echo $alertCount > 0 ? '⚠️ Needs attention' : 'All systems OK'; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- ============================================================
        DASHBOARD GRID — SINGLE FRAME
        ============================================================ -->
        <div class="dash-grid">
            
            <!-- ============ LIVE MONITOR ============ -->
            <section class="panel monitor-panel">
                <div class="panel-head">
                    <h2 class="panel-title">
                        <i class="fas fa-tv text-[var(--primary)]"></i> Live Monitor
                    </h2>
                    <div class="flex items-center gap-2">
                        <span class="live-pill">
                            <span class="w-1.5 h-1.5 rounded-full bg-[var(--primary)] pulse-dot"></span>
                            LIVE
                        </span>
                        <a href="monitor.php" target="_blank" class="btn-monitor text-[9.5px] px-2 py-1 rounded-lg shadow-sm font-medium">
                            <i class="fas fa-expand text-[8px] mr-1"></i> Full Screen
                        </a>
                    </div>
                </div>
                <div class="flex-1 min-h-0 p-2">
                    <iframe class="monitor-embed" src="monitor.php" title="Live five-bin community monitor" loading="lazy"></iframe>
                </div>
                
                <!-- Legacy monitor markup preserved (hidden, as before) -->
                <div class="legacy-monitor-panel" style="display:none;">
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
                
                <div class="legacy-monitor-footer" style="display:none;">
                    <span><i class="fas fa-signal mr-1.5"></i> Signal: Strong</span>
                    <span><i class="fas fa-bolt mr-1.5"></i> Connected</span>
                    <span><i class="fas fa-sync-alt mr-1.5"></i> <span id="previewTime">Updating...</span></span>
                    <span><i class="fas fa-exclamation-triangle mr-1.5 <?php echo $alertCount > 0 ? 'text-yellow-600' : 'text-[var(--text-light)]/30'; ?>"></i> <?php echo $alertCount; ?> active alerts</span>
                    <span><i class="fas fa-clock mr-1.5"></i> Last: <?php echo $lastDisposalTime; ?></span>
                </div>
            </section>
            
            <!-- ============ FILL LEVELS ============ -->
            <section class="panel fill-panel">
                <div class="panel-head">
                    <h3 class="panel-title">
                        <i class="fas fa-chart-simple text-[var(--primary)]"></i> Fill Levels
                    </h3>
                    <span class="panel-sub">Ultrasonic Sensors</span>
                </div>
                
                <div class="panel-body flex flex-col justify-between gap-1.5">
                    
                    <!-- Biodegradable -->
                    <div class="flex items-center gap-2.5 py-1">
                        <div class="w-7 h-7 rounded-lg bg-[var(--primary)]/10 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-leaf text-[var(--primary)] text-[10px]"></i>
                        </div>
                        <span class="text-xs font-medium text-[var(--text)] flex-1 truncate">Biodegradable</span>
                        <div class="w-24 h-1.5 bg-[var(--border)] rounded-full overflow-hidden flex-shrink-0">
                            <div class="h-full <?php echo getFillColor($bioFill); ?> rounded-full transition-all duration-1000" style="width:<?php echo $bioFill; ?>%"></div>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[var(--bg)] text-[var(--text)] min-w-[40px] text-center flex-shrink-0"><?php echo number_format($bioFill, 0); ?>%</span>
                    </div>
                    
                    <!-- Plastic -->
                    <div class="flex items-center gap-2.5 py-1">
                        <div class="w-7 h-7 rounded-lg bg-[var(--secondary)]/20 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-bottle-water text-[var(--accent)] text-[10px]"></i>
                        </div>
                        <span class="text-xs font-medium text-[var(--text)] flex-1 truncate">Plastic</span>
                        <div class="w-24 h-1.5 bg-[var(--border)] rounded-full overflow-hidden flex-shrink-0">
                            <div class="h-full <?php echo getFillColor($plasticFill); ?> rounded-full transition-all duration-1000" style="width:<?php echo $plasticFill; ?>%"></div>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[var(--bg)] text-[var(--text)] min-w-[40px] text-center flex-shrink-0"><?php echo number_format($plasticFill, 0); ?>%</span>
                    </div>
                    
                    <!-- Metal -->
                    <div class="flex items-center gap-2.5 py-1">
                        <div class="w-7 h-7 rounded-lg bg-[var(--accent)]/10 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-cube text-[var(--accent)] text-[10px]"></i>
                        </div>
                        <span class="text-xs font-medium text-[var(--text)] flex-1 truncate">Metal</span>
                        <div class="w-24 h-1.5 bg-[var(--border)] rounded-full overflow-hidden flex-shrink-0">
                            <div class="h-full <?php echo getFillColor($metalFill); ?> rounded-full transition-all duration-1000" style="width:<?php echo $metalFill; ?>%"></div>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[var(--bg)] text-[var(--text)] min-w-[40px] text-center flex-shrink-0"><?php echo number_format($metalFill, 0); ?>%</span>
                    </div>
                    
                    <!-- Today's Summary -->
                    <div class="mt-1 grid grid-cols-4 gap-2 p-2 rounded-xl bg-[var(--bg)] border border-[var(--border)]">
                        <div class="text-center">
                            <div class="text-[8.5px] font-bold uppercase tracking-wider text-[var(--text-light)]">Total</div>
                            <div class="text-sm font-bold text-[var(--text)]"><?php echo number_format($todayStats['total_disposals'] ?? 0); ?></div>
                        </div>
                        <div class="text-center">
                            <div class="text-[8.5px] font-bold uppercase tracking-wider text-[var(--text-light)]">Bio</div>
                            <div class="text-sm font-bold text-[var(--primary)]"><?php echo number_format($todayStats['bio_count'] ?? 0); ?></div>
                        </div>
                        <div class="text-center">
                            <div class="text-[8.5px] font-bold uppercase tracking-wider text-[var(--text-light)]">Plastic</div>
                            <div class="text-sm font-bold text-[var(--accent)]"><?php echo number_format($todayStats['plastic_count'] ?? 0); ?></div>
                        </div>
                        <div class="text-center">
                            <div class="text-[8.5px] font-bold uppercase tracking-wider text-[var(--text-light)]">Metal</div>
                            <div class="text-sm font-bold text-[#757575]"><?php echo number_format($todayStats['metal_count'] ?? 0); ?></div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- ============ RECENT ACTIVITY ============ -->
            <section class="panel activity-panel">
                <div class="panel-head">
                    <h3 class="panel-title">
                        <i class="fas fa-clock text-[var(--primary)]"></i> Recent Activity
                    </h3>
                    <span class="panel-sub">Last 5</span>
                </div>
                
                <div class="panel-body">
                    <?php if (count($recentLogs) > 0): ?>
                        <div class="space-y-1.5">
                            <?php foreach ($recentLogs as $log): ?>
                            <div class="flex items-center justify-between gap-2 p-1.5 rounded-lg hover:bg-[var(--bg)] transition-colors">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0
                                        <?php echo $log['bin_type'] == 'biodegradable' ? 'bg-[var(--primary)]/10 text-[var(--primary)]' : 
                                            ($log['bin_type'] == 'plastic' ? 'bg-[var(--accent)]/10 text-[var(--accent)]' : 
                                            'bg-[var(--text)]/10 text-[var(--text)]'); ?>">
                                        <i class="fas fa-<?php echo $log['bin_type'] == 'biodegradable' ? 'leaf' : ($log['bin_type'] == 'plastic' ? 'bottle-water' : 'cube'); ?> text-[9px]"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold text-[var(--text)] capitalize truncate leading-tight"><?php echo $log['bin_type']; ?></p>
                                        <p class="text-[9px] text-[var(--text-light)] truncate leading-tight"><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></p>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <span class="text-[8.5px] font-bold px-1.5 py-0.5 rounded-full whitespace-nowrap
                                        <?php echo $log['status'] == 'success' ? 'bg-[var(--primary)]/10 text-[var(--primary)]' : 
                                            ($log['status'] == 'failed' ? 'bg-red-500/10 text-red-500' : 
                                            'bg-yellow-500/10 text-yellow-500'); ?>">
                                        <?php echo $log['is_shredded'] ? 'Shredded' : 'Collected'; ?>
                                    </span>
                                    <p class="text-[9px] text-[var(--text-light)] mt-0.5 leading-tight"><?php echo date('H:i', strtotime($log['created_at'])); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-2 p-2 rounded-lg bg-[var(--bg)] text-[10px] font-medium text-[var(--text)] flex items-center gap-1.5">
                            <i class="fas fa-database text-[var(--primary)]"></i>
                            <?php echo count($recentLogs); ?> recent records
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6">
                            <div class="w-12 h-12 rounded-xl bg-[var(--bg)] flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-inbox text-lg text-[var(--text-light)]/30"></i>
                            </div>
                            <p class="text-[11px] font-medium text-[var(--text-light)]">No records yet</p>
                            <p class="text-[9.5px] text-[var(--text-light)]/60 mt-0.5">Waste logs will appear here</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- ============ WEEKLY OVERVIEW ============ -->
            <section class="panel weekly-panel">
                <div class="panel-head">
                    <h3 class="panel-title">
                        <i class="fas fa-chart-bar text-[var(--primary)]"></i> Weekly Overview
                    </h3>
                    <span class="panel-sub">Last 7 days</span>
                </div>
                
                <div class="panel-body flex flex-col">
                    <?php if (count($weeklyStats) > 0): ?>
                        <?php 
                        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        $dayData = [];
                        foreach ($weeklyStats as $stat) {
                            $dayIndex = date('N', strtotime($stat['log_date'])) - 1;
                            $dayData[$dayIndex] = $stat;
                        }
                        $maxCount = max(array_column($weeklyStats, 'daily_disposals')) ?: 1;
                        ?>
                        <div class="flex-1 min-h-0 grid grid-cols-7 gap-1.5">
                            <?php for ($i = 0; $i < 7; $i++): 
                                $data = isset($dayData[$i]) ? $dayData[$i] : null;
                                $percentage = $data ? ($data['daily_disposals'] / $maxCount * 100) : 0;
                            ?>
                            <div class="flex flex-col items-center min-h-0 rounded-lg bg-[var(--bg)] p-1">
                                <div class="text-[8.5px] font-bold uppercase tracking-wider text-[var(--text-light)]"><?php echo $days[$i]; ?></div>
                                <div class="flex-1 min-h-0 w-full flex items-end justify-center py-0.5">
                                    <div class="w-full max-w-[22px] rounded-t-md transition-all duration-1000 chart-bar" 
                                         style="height: <?php echo max(6, $percentage); ?>%; 
                                                <?php echo $data 
                                                    ? 'background: linear-gradient(180deg, var(--primary), var(--accent));' 
                                                    : 'background: var(--border); opacity: 0.3;'; ?>">
                                    </div>
                                </div>
                                <div class="text-[11px] font-bold text-[var(--text)] leading-none">
                                    <?php echo $data ? number_format($data['daily_disposals']) : '0'; ?>
                                </div>
                                <div class="text-[7.5px] font-semibold text-[var(--text-light)]/60 uppercase tracking-wider">items</div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 flex items-center justify-center">
                            <p class="text-[11px] font-medium text-[var(--text-light)]">No weekly data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- ============ AI SUGGESTION ============ -->
            <section class="panel ai-panel">
                <div class="panel-head">
                    <h3 class="panel-title">
                        <i class="fas fa-robot text-[var(--primary)]"></i> AI Suggestion
                    </h3>
                    <span class="panel-sub">Live</span>
                </div>
                <div class="flex-1 min-h-0 p-2.5">
                    <div class="ai-suggestion <?php echo $suggestionType; ?> rounded-lg p-3 h-full flex flex-col">
                        <p class="text-[11px] text-[var(--text)] leading-relaxed flex-1 overflow-y-auto"><?php echo $aiSuggestion; ?></p>
                        <div class="mt-2 pt-2 border-t border-[var(--border)] flex justify-between text-[9px] text-[var(--text-light)] flex-shrink-0">
                            <span><i class="far fa-clock mr-1"></i> <?php echo date('H:i'); ?></span>
                            <span><i class="fas fa-sync-alt mr-1"></i> Auto</span>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- ============ WASTE TYPE DISTRIBUTION ============ -->
            <section class="panel pie-panel">
                <div class="panel-head">
                    <h3 class="panel-title">
                        <i class="fas fa-chart-pie text-[var(--primary)]"></i> Waste Distribution
                    </h3>
                    <span class="panel-sub">Today</span>
                </div>
                <div class="panel-body flex flex-col">
                    <div class="chart-container">
                        <canvas id="pieChart"></canvas>
                    </div>
                    <div class="flex-shrink-0 mt-1.5 flex flex-wrap justify-center gap-x-3 gap-y-1 text-[9.5px] font-medium">
                        <span><span class="inline-block w-2 h-2 rounded-full bg-[#8BC34A] mr-1"></span> Bio: <?php echo $chartData['bio']; ?></span>
                        <span><span class="inline-block w-2 h-2 rounded-full bg-[#C5E1A5] mr-1"></span> Plastic: <?php echo $chartData['plastic']; ?></span>
                        <span><span class="inline-block w-2 h-2 rounded-full bg-[#4CAF50] mr-1"></span> Metal: <?php echo $chartData['metal']; ?></span>
                    </div>
                </div>
            </section>
            
            <!-- ============ DAILY TREND ============ -->
            <section class="panel trend-panel">
                <div class="panel-head">
                    <h3 class="panel-title">
                        <i class="fas fa-chart-line text-[var(--primary)]"></i> Daily Trend
                    </h3>
                    <span class="panel-sub">Last 7 days</span>
                </div>
                <div class="panel-body flex flex-col">
                    <div class="chart-container">
                        <canvas id="lineChart"></canvas>
                    </div>
                </div>
            </section>
        </div>
        
        <!-- ============================================================
        SYSTEM INFO + USER INFO FOOTER
        ============================================================ -->
        <footer class="footer-bar">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="chip">
                    <i class="fas fa-check-circle text-[var(--primary)]"></i> Segregation: <strong id="systemSegregation"><?php echo htmlspecialchars($systemMetrics['segregation']); ?></strong>
                </span>
                <span class="chip">
                    <i class="fas fa-gauge-high text-[var(--primary)]"></i> Performance: <strong id="systemPerformance"><?php echo htmlspecialchars($systemMetrics['performance']); ?></strong>
                </span>
                <span class="chip">
                    <i class="fas fa-thumbs-up text-[var(--primary)]"></i> Acceptability: <strong id="systemAcceptability"><?php echo htmlspecialchars($systemMetrics['acceptability']); ?></strong>
                </span>
                <span class="chip">
                    <i class="fas fa-tools text-[var(--primary)]"></i> Maintainability: <strong id="systemMaintainability"><?php echo htmlspecialchars($systemMetrics['maintainability']); ?></strong>
                </span>
                <span class="chip text-[var(--text-light)]">
                    <i class="fas fa-clock"></i> ISO 25010
                </span>
            </div>
            
            <div class="flex flex-wrap items-center gap-2 text-[10px] font-medium text-[var(--text-light)]">
                <span><i class="fas fa-user mr-1"></i> <strong class="text-[var(--text)]"><?php echo htmlspecialchars($full_name); ?></strong></span>
                <span class="text-[var(--border)]">|</span>
                <span><i class="fas fa-tag mr-1"></i> <span class="capitalize text-[var(--text)]"><?php echo htmlspecialchars($role); ?></span></span>
                <?php if ($role === 'admin'): ?>
                    <span class="px-1.5 py-0.5 rounded-full bg-[var(--primary)]/10 text-[var(--primary)] text-[8.5px] font-bold uppercase tracking-wider">Admin</span>
                <?php endif; ?>
                <span class="text-[var(--border)]">|</span>
                <span><i class="fas fa-clock mr-1"></i> <?php echo date('H:i', $_SESSION['login_time'] ?? time()); ?></span>
            </div>
        </footer>
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
                    if (window.innerWidth <= 1023) {
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
                            borderWidth: 2,
                            pointBackgroundColor: '#4CAF50',
                            pointBorderColor: '#388E3C',
                            pointRadius: 3,
                            pointHoverRadius: 5,
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
                                    color: 'var(--text-light)',
                                    font: { size: 9 }
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
                                    color: 'var(--text-light)',
                                    font: { size: 9 }
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