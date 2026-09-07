<?php
// =============================================
// monitor.php - Community Trash Bin Monitor
// No session required - Large Display Optimized
// Enhanced with logo5.png and improved UI
// =============================================

require_once __DIR__ . '/config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$monitorPassword = MONITOR_PASSWORD;

// Check if password is submitted via URL parameter
$isAuthorized = isLoggedIn();
if ($monitorPassword !== '' && isset($_GET['key']) && hash_equals($monitorPassword, (string)$_GET['key'])) {
    $_SESSION['monitor_authorized'] = true;
    if (!isset($_GET['api'])) {
        header('Location: monitor.php');
        exit();
    }
}
if (!empty($_SESSION['monitor_authorized'])) {
    $isAuthorized = true;
}

// If not authorized, show password form
if (!$isAuthorized) {
    ?>
    <!DOCTYPE html>
    <html lang="en" data-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Monitor Access - SmartBin</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --primary: #8BC34A;
                --accent: #4CAF50;
                --bg: #F5F5F5;
                --text: #424242;
                --text-light: #757575;
                --border: #E0E0E0;
                --card-bg: #FFFFFF;
            }
            
            [data-theme="dark"] {
                --bg: #1a1a1a;
                --text: #E8E8E8;
                --text-light: #B0B0B0;
                --border: #404040;
                --card-bg: #2d2d2d;
            }
            
            * { font-family: 'Inter', sans-serif; }
            body {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                background: var(--bg);
                transition: background 0.3s ease;
                position: relative;
            }
            
            .bg-overlay {
                position: fixed;
                inset: 0;
                z-index: 0;
            }
            .bg-overlay .bg-image {
                position: absolute;
                inset: 0;
                background: url('indexbg.jpg') center/cover no-repeat;
                opacity: 0.06;
            }
            [data-theme="dark"] .bg-overlay .bg-image {
                opacity: 0.03;
            }
            
            body > * { position: relative; z-index: 1; }
            
            .glass-card {
                background: var(--card-bg);
                border: 1px solid var(--border);
                border-radius: 16px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.08);
                transition: all 0.3s ease;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, var(--primary), var(--accent));
                color: white;
                transition: all 0.3s ease;
                padding: 12px 24px;
                border-radius: 12px;
                border: none;
                font-weight: 600;
                cursor: pointer;
            }
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 30px rgba(139, 195, 74, 0.25);
            }
            
            .theme-toggle {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 100;
                background: var(--card-bg);
                border: 1px solid var(--border);
                color: var(--text);
                padding: 10px 16px;
                border-radius: 12px;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
            }
            .theme-toggle:hover {
                border-color: var(--primary);
                transform: scale(1.05);
            }
        </style>
    </head>
    <body>
        <!-- Background -->
        <div class="bg-overlay">
            <div class="bg-image"></div>
        </div>
        
        <!-- Theme Toggle -->
        <button class="theme-toggle" id="themeToggle">
            <i class="fas fa-moon" id="themeIcon"></i>
            <span id="themeLabel">Dark</span>
        </button>
        
        <div class="w-full max-w-md">
            <!-- Brand with logo (no box) -->
            <div class="text-center mb-8">
                <img src="logo5.png" alt="CT-ABS Logo" class="w-28 h-28 md:w-32 md:h-32 object-contain mx-auto">
                <h1 class="text-4xl font-extrabold text-[var(--text)] mt-4">SmartBin</h1>
                <p class="text-[var(--text-light)] text-base">Community Waste Monitor</p>
            </div>
            
            <!-- Login Card -->
            <div class="glass-card p-8">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 rounded-full bg-[var(--primary)]/10 border border-[var(--primary)]/20 flex items-center justify-center mx-auto">
                        <i class="fas fa-lock text-[var(--primary)] text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-[var(--text)] mt-3">Access Required</h2>
                    <p class="text-[var(--text-light)] text-base">Enter the access code to view the monitor</p>
                </div>
                
                <form method="GET" action="">
                    <div class="mb-4">
                        <input type="password" 
                               name="key" 
                               placeholder="Enter access code" 
                               class="w-full px-4 py-4 bg-[var(--bg)] border border-[var(--border)] rounded-xl text-[var(--text)] placeholder-[var(--text-light)] focus:outline-none focus:ring-2 focus:ring-[var(--primary)]/50 focus:border-transparent text-center text-2xl tracking-widest transition"
                               autofocus>
                    </div>
                    <button type="submit" class="btn-primary w-full text-lg">
                        <i class="fas fa-unlock mr-2"></i> View Monitor
                    </button>
                </form>
                
                <div class="text-center mt-4 text-sm text-[var(--text-light)]">
                    <i class="fas fa-shield-alt mr-1"></i> 
                    Contact administrator if you need access
                </div>
            </div>
            
            <!-- Footer -->
            <div class="text-center mt-6 text-[var(--text-light)] text-sm">
                <span><i class="fas fa-code"></i> v3.0</span>
                <span class="mx-2 text-[var(--border)]">|</span>
                <span><i class="fas fa-bolt"></i> Live Monitoring</span>
            </div>
        </div>
        
        <script>
            (function() {
                'use strict';
                
                // Theme Toggle
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
                        if (themeIcon) themeIcon.className = 'fas fa-sun';
                        if (themeLabel) themeLabel.textContent = 'Light';
                    } else {
                        if (themeIcon) themeIcon.className = 'fas fa-moon';
                        if (themeLabel) themeLabel.textContent = 'Dark';
                    }
                }
            })();
        </script>
    </body>
    </html>
    <?php
    exit();
}

// =============================================
// Authorized - Show the Monitor Dashboard
// =============================================

if (isset($_GET['api']) && $_GET['api'] === 'status') {
    header('Content-Type: application/json');

    $binStatus = [];
    $alertCount = 0;
    $lastDisposal = null;
    $requestedBinId = filter_input(INPUT_GET, 'bin_id', FILTER_VALIDATE_INT);

    try {
        if ($requestedBinId) {
            $stmt = $pdo->prepare("SELECT * FROM v_bin_status WHERE bin_id = ?");
            $stmt->execute([$requestedBinId]);
        } else {
            $stmt = $pdo->query("SELECT * FROM v_bin_status");
        }
        $binStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT COUNT(*) as alert_count FROM alerts WHERE is_resolved = 0");
        $alertCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['alert_count'] ?? 0);

        $stmt = $pdo->query("SELECT created_at FROM waste_logs ORDER BY created_at DESC LIMIT 1");
        $lastDisposal = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT
                (SELECT COUNT(*) FROM waste_logs) as total_logs,
                (SELECT COUNT(*) FROM waste_logs WHERE status = 'success') as successful_logs,
                (SELECT COUNT(*) FROM devices WHERE is_active = 1) as active_devices,
                (SELECT COUNT(*) FROM devices WHERE is_active = 1 AND updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as responsive_devices,
                (SELECT COUNT(*) FROM trash_bins) as total_bins,
                (SELECT COUNT(*) FROM trash_bins WHERE status <> 'maintenance') as maintainable_bins");
        $metricData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Monitor API error: ' . $e->getMessage());
        $binStatus = [];
        $alertCount = 0;
        $lastDisposal = null;
        $metricData = [];
    }

    $totalLogs = (int)($metricData['total_logs'] ?? 0);
    $activeDevices = (int)($metricData['active_devices'] ?? 0);
    $totalBins = (int)($metricData['total_bins'] ?? 0);

    $bioFill = 0;
    $plasticFill = 0;
    $metalFill = 0;

    foreach ($binStatus as $status) {
        $type = strtolower($status['bin_type'] ?? '');
        $fill = floatval($status['fill_level'] ?? 0);

        if ($type === 'biodegradable') {
            $bioFill = $fill;
        } elseif ($type === 'plastic') {
            $plasticFill = $fill;
        } elseif ($type === 'metal') {
            $metalFill = $fill;
        }
    }

    echo json_encode([
        'success' => true,
        'timestamp' => date('c'),
        'alert_count' => $alertCount,
        'last_disposal' => $lastDisposal['created_at'] ?? null,
        'system_metrics' => [
            'segregation' => $totalLogs > 0 ? number_format(((int)$metricData['successful_logs'] / $totalLogs) * 100, 1) . '%' : 'N/A',
            'performance' => $activeDevices > 0 ? number_format(((int)$metricData['responsive_devices'] / $activeDevices) * 100, 1) . '%' : 'N/A',
            'acceptability' => 'N/A',
            'maintainability' => $totalBins > 0 ? number_format(((int)$metricData['maintainable_bins'] / $totalBins) * 100, 1) . '%' : 'N/A'
        ],
        'fill_levels' => [
            'biodegradable' => round($bioFill, 2),
            'plastic' => round($plasticFill, 2),
            'metal' => round($metalFill, 2)
        ]
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// =============================================
// FETCH COMPLETE BIN DATA WITH FULL ADDRESS
// =============================================
$binStatus = [];
$binLocations = [];
$allBins = [];
$addressDetails = [];
$addressData = [];

try {
    // Get all bins with their full details including address fields
    $stmt = $pdo->query("
        SELECT 
            tb.bin_id,
            tb.bin_name,
            tb.bin_code,
            tb.location,
            tb.building,
            tb.floor,
            tb.street,
            tb.barangay,
            tb.city,
            tb.province,
            tb.zip_code,
            tb.bin_type,
            tb.status,
            COALESCE(vbs.fill_level, 0) as fill_level,
            COALESCE(vbs.temperature, 0) as temperature,
            COALESCE(vbs.humidity, 0) as humidity,
            COALESCE(vbs.last_updated, tb.created_at) as last_updated
        FROM trash_bins tb
        LEFT JOIN v_bin_status vbs ON tb.bin_id = vbs.bin_id
        WHERE tb.status = 'active'
        ORDER BY tb.bin_id
    ");
    $allBins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get bin status for the 3 main types
    $stmt = $pdo->query("SELECT * FROM v_bin_status");
    $binStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get the first bin's address as primary location
    if (!empty($allBins)) {
        $firstBin = $allBins[0];
        $addressData = [
            'location' => $firstBin['location'] ?? 'Community Center',
            'street' => $firstBin['street'] ?? '',
            'barangay' => $firstBin['barangay'] ?? '',
            'city' => $firstBin['city'] ?? '',
            'province' => $firstBin['province'] ?? '',
            'zip_code' => $firstBin['zip_code'] ?? '',
            'building' => $firstBin['building'] ?? '',
            'floor' => $firstBin['floor'] ?? ''
        ];
    }
    
    // Get unique locations with bin count
    $stmt = $pdo->query("
        SELECT 
            location,
            street,
            barangay,
            city,
            province,
            COUNT(*) as bin_count
        FROM trash_bins 
        WHERE status = 'active' AND location IS NOT NULL AND location != ''
        GROUP BY location, street, barangay, city, province
        ORDER BY bin_count DESC
        LIMIT 5
    ");
    $binLocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    } catch (Throwable $e) {
        error_log('Monitor bin data error: ' . $e->getMessage());
    $allBins = [];
    $binStatus = [];
    $binLocations = [];
    $addressData = [];
}

// =============================================
// BUILD COMPLETE ADDRESS STRING
// =============================================
$primaryLocation = 'Community Center';
$fullAddress = '';
$shortAddress = '';
$addressParts = [];

if (!empty($addressData)) {
    $primaryLocation = $addressData['location'] ?? 'Community Center';
    
    if (!empty($addressData['street'])) $addressParts[] = $addressData['street'];
    if (!empty($addressData['barangay'])) $addressParts[] = $addressData['barangay'];
    if (!empty($addressData['city'])) $addressParts[] = $addressData['city'];
    if (!empty($addressData['province'])) $addressParts[] = $addressData['province'];
    if (!empty($addressData['zip_code'])) $addressParts[] = $addressData['zip_code'];
    
    $fullAddress = implode(', ', $addressParts);
    $shortAddress = (!empty($addressData['barangay']) && !empty($addressData['city'])) 
        ? $addressData['barangay'] . ', ' . $addressData['city']
        : ($addressData['city'] ?? $fullAddress);
    
    $locationDetails = [];
    if (!empty($addressData['building'])) $locationDetails[] = $addressData['building'];
    if (!empty($addressData['floor'])) $locationDetails[] = 'Floor ' . $addressData['floor'];
    if (!empty($locationDetails)) $primaryLocation .= ' - ' . implode(' ', $locationDetails);
}
if (empty($fullAddress)) { $fullAddress = 'Philippines'; $shortAddress = 'Philippines'; }

// =============================================
// FETCH TODAY'S STATISTICS & METRICS
// =============================================
$todayStats = [];
$metricData = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_disposals FROM waste_logs WHERE DATE(created_at) = CURDATE()");
    $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM waste_logs) as total_logs,
            (SELECT COUNT(*) FROM waste_logs WHERE status = 'success') as successful_logs,
            (SELECT COUNT(*) FROM devices WHERE is_active = 1) as active_devices,
            (SELECT COUNT(*) FROM devices WHERE is_active = 1 AND updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as responsive_devices,
            (SELECT COUNT(*) FROM trash_bins) as total_bins,
            (SELECT COUNT(*) FROM trash_bins WHERE status <> 'maintenance') as maintainable_bins
    ");
    $metricData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Monitor statistics error: ' . $e->getMessage());
    $todayStats = ['total_disposals' => 0];
    $metricData = [];
}

// =============================================
// FETCH RECENT ALERTS
// =============================================
$alerts = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM alerts 
        WHERE is_resolved = 0 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Monitor alerts error: ' . $e->getMessage());
    $alerts = [];
}

// =============================================
// HELPER FUNCTIONS
// =============================================
function getStatusColor($fillLevel) {
    if ($fillLevel >= 80) return 'danger';
    if ($fillLevel >= 60) return 'warning';
    if ($fillLevel >= 30) return 'info';
    return 'success';
}

function getStatusText($fillLevel) {
    if ($fillLevel >= 80) return '⚠️ FULL';
    if ($fillLevel >= 60) return '⚡ HIGH';
    if ($fillLevel >= 30) return '📊 MEDIUM';
    return '✅ LOW';
}

// Get fill levels from bin status
$bioFill = 0;
$plasticFill = 0;
$metalFill = 0;
foreach ($binStatus as $status) {
    $type = strtolower($status['bin_type'] ?? '');
    $fill = floatval($status['fill_level'] ?? 0);
    if ($type === 'biodegradable') $bioFill = $fill;
    elseif ($type === 'plastic') $plasticFill = $fill;
    elseif ($type === 'metal') $metalFill = $fill;
}

$dayOfWeek = date('l');
$dateFormatted = date('F d, Y');
$timeFormatted = date('h:i:s A');
$totalActiveBins = count($allBins);

// Compute metrics for display
$totalLogs = (int)($metricData['total_logs'] ?? 0);
$segregation = ($totalLogs > 0) ? number_format(((int)($metricData['successful_logs'] ?? 0) / $totalLogs) * 100, 1) . '%' : 'N/A';
$activeDevices = (int)($metricData['active_devices'] ?? 0);
$performance = ($activeDevices > 0) ? number_format(((int)($metricData['responsive_devices'] ?? 0) / $activeDevices) * 100, 1) . '%' : 'N/A';
$totalBins = (int)($metricData['total_bins'] ?? 0);
$maintainability = ($totalBins > 0) ? number_format(((int)($metricData['maintainable_bins'] ?? 0) / $totalBins) * 100, 1) . '%' : 'N/A';

// Last disposal time
$lastDisposalTime = '';
try {
    $stmt = $pdo->query("SELECT created_at FROM waste_logs ORDER BY created_at DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $lastDisposalTime = date('h:i A', strtotime($row['created_at']));
} catch (Throwable $e) {
    error_log('Monitor last disposal error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bin Monitor - SmartBin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            --hover-bg: rgba(139, 195, 74, 0.08);
            --monitor-text: #2E7D32;
        }
        
        [data-theme="dark"] {
            --bg: #1a1a1a;
            --bg-white: #2d2d2d;
            --text: #E8E8E8;
            --text-light: #B0B0B0;
            --border: #404040;
            --shadow: rgba(0, 0, 0, 0.3);
            --card-bg: #2d2d2d;
            --hover-bg: rgba(139, 195, 74, 0.12);
            --monitor-text: #A5D6A7;
        }
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            user-select: none;
            cursor: default;
            margin: 0;
            padding: 0;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
            background: var(--bg);
            transition: background 0.3s ease;
            position: relative;
        }
        
        .bg-overlay {
            position: fixed;
            inset: 0;
            z-index: 0;
        }
        .bg-overlay .bg-image {
            position: absolute;
            inset: 0;
            background: url('indexbg.jpg') center/cover no-repeat;
            opacity: 0.04;
        }
        [data-theme="dark"] .bg-overlay .bg-image {
            opacity: 0.02;
        }
        
        body > * {
            position: relative;
            z-index: 1;
        }
        
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            box-shadow: 0 8px 32px var(--shadow);
            transition: all 0.3s ease;
            border-radius: 24px;
        }
        
        .glass-card-light {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            border-radius: 16px;
        }
        
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            border-radius: 16px;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: 0 8px 30px var(--shadow);
        }
        
        .bin-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            border-radius: 16px;
        }
        .bin-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
            box-shadow: 0 8px 30px var(--shadow);
        }
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        .progress-ring circle {
            transition: stroke-dashoffset 1.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .alert-item {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .pulse-dot { animation: pulse 2s ease-in-out infinite; }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 4px;
        }
        .scrollbar-thin::-webkit-scrollbar-track {
            background: var(--border);
            border-radius: 10px;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        
        .status-badge {
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        .badge-critical {
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.15);
        }
        .badge-warning {
            background: rgba(234, 179, 8, 0.12);
            color: #eab308;
            border: 1px solid rgba(234, 179, 8, 0.15);
        }
        .badge-info {
            background: rgba(59, 130, 246, 0.12);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.15);
        }
        .badge-success {
            background: rgba(139, 195, 74, 0.12);
            color: #4CAF50;
            border: 1px solid rgba(139, 195, 74, 0.15);
        }
        
        .no-interaction {
            pointer-events: none;
        }
        
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 10px 16px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .theme-toggle:hover {
            border-color: var(--primary);
            background: var(--hover-bg);
            transform: scale(1.05);
        }
        
        .location-badge {
            background: linear-gradient(135deg, rgba(139, 195, 74, 0.12), rgba(76, 175, 80, 0.12));
            border: 1px solid rgba(139, 195, 74, 0.20);
            max-width: 100%;
        }
        .location-badge i { color: var(--primary); }
        .location-address { font-size: 0.8rem; line-height: 1.4; }
        .location-address-sm { font-size: 0.7rem; line-height: 1.3; }
        [data-theme="dark"] .location-badge {
            background: linear-gradient(135deg, rgba(139, 195, 74, 0.08), rgba(76, 175, 80, 0.08));
            border: 1px solid rgba(139, 195, 74, 0.15);
        }
        
        /* Large screen optimizations */
        @media (min-width: 1280px) {
            body { padding: 30px; }
            .stat-card .text-4xl { font-size: 3.5rem; }
            .stat-card .text-sm { font-size: 1rem; }
            .bin-card .text-lg { font-size: 1.75rem; }
            .bin-card .text-xs { font-size: 0.9rem; }
            .progress-ring { width: 140px; height: 140px; }
            .bin-card .relative { width: 140px; height: 140px; }
            .bin-card .text-2xl { font-size: 2.5rem; }
            .location-text { font-size: 1.2rem; }
            .datetime-text { font-size: 1.1rem; }
            .location-address { font-size: 1rem; }
        }
        @media (min-width: 1536px) {
            body { padding: 40px; }
            .stat-card .text-4xl { font-size: 4rem; }
            .progress-ring { width: 180px; height: 180px; }
            .bin-card .relative { width: 180px; height: 180px; }
            .bin-card .text-2xl { font-size: 3rem; }
            .bin-card .text-lg { font-size: 2rem; }
            .bin-card .text-xs { font-size: 1rem; }
            .status-badge { font-size: 20px; padding: 8px 24px; }
            .location-text { font-size: 1.5rem; }
            .datetime-text { font-size: 1.3rem; }
            .location-address { font-size: 1.2rem; }
        }
        @media (max-width: 1024px) {
            .status-badge { font-size: 12px; padding: 4px 12px; }
            .bin-card .text-lg { font-size: 1rem; }
            .location-text { font-size: 0.9rem; }
            .datetime-text { font-size: 0.8rem; }
            .location-address { font-size: 0.7rem; }
        }
        @media (max-width: 640px) {
            .stat-card .text-2xl { font-size: 1.25rem; }
            .progress-ring { width: 70px; height: 70px; }
            .bin-card .relative { width: 70px; height: 70px; }
            .bin-card .text-2xl { font-size: 1.25rem; }
            .status-badge { font-size: 10px; padding: 3px 10px; }
            .location-text { font-size: 0.7rem; }
            .datetime-text { font-size: 0.6rem; }
            .location-address { font-size: 0.55rem; }
            .location-address-sm { font-size: 0.5rem; }
        }
        
        .monitor-wrapper {
            height: 100vh;
            max-height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
        }
        .content-container {
            width: 100%;
            max-height: 98vh;
        }
        
        /* Metric cards */
        .metric-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
        }
        @media (min-width: 1280px) {
            .metric-value { font-size: 2.5rem; }
        }
        @media (min-width: 1536px) {
            .metric-value { font-size: 3rem; }
        }
        .metric-label {
            font-size: 0.7rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        @media (min-width: 1280px) {
            .metric-label { font-size: 0.9rem; }
        }
        
        /* Refresh button */
        .refresh-btn {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 8px 14px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .refresh-btn:hover {
            border-color: var(--primary);
            background: var(--hover-bg);
        }
        .refresh-btn i {
            transition: transform 0.6s ease;
        }
        .refresh-btn:active i {
            transform: rotate(360deg);
        }
    </style>
</head>
<body>
    <!-- Background -->
    <div class="bg-overlay">
        <div class="bg-image"></div>
    </div>
    
    <!-- Theme Toggle -->
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon" id="themeIcon"></i>
        <span id="themeLabel">Dark</span>
    </button>
    
    <div class="monitor-wrapper w-full max-w-7xl mx-auto no-interaction">
        <!-- Main Container -->
        <div class="content-container glass-card p-4 md:p-6 lg:p-8">
            
            <!-- Header with Logo (no box) + Location, Day, Time, Refresh -->
            <div class="flex flex-wrap justify-between items-start mb-4 pb-3 border-b border-[var(--border)]">
                <div class="flex items-center gap-3 md:gap-4">
                    <!-- Logo as plain image -->
                    <img src="logo5.png" alt="Logo" style="width: 140px; height: 120px; object-fit: contain;">
                    <div>
                        <h1 class="text-xl md:text-3xl lg:text-4xl font-bold text-[var(--text)]">CT-ABS Monitoring</h1>
                        <p class="text-xs md:text-sm lg:text-base text-[var(--text-light)]"><?php echo $totalActiveBins; ?> active bins · Real-time monitoring</p>
                    </div>
                </div>
                
                <!-- Location, Day, Time, Refresh -->
                <div class="flex flex-wrap items-center gap-3 md:gap-4 mt-2 sm:mt-0">
                    <!-- Location -->
                    <div class="flex items-center gap-2 location-badge px-3 py-2 md:px-4 md:py-2 rounded-full max-w-xs md:max-w-sm lg:max-w-md">
                        <i class="fas fa-map-marker-alt location-text text-xs md:text-base flex-shrink-0"></i>
                        <div class="flex flex-col min-w-0">
                            <span class="location-text text-[var(--text)] font-semibold text-xs md:text-sm lg:text-base truncate">
                                <?php echo htmlspecialchars($primaryLocation); ?>
                            </span>
                            <span class="location-address text-[var(--text-light)] text-[10px] md:text-xs lg:text-sm truncate hidden sm:block">
                                <?php echo htmlspecialchars($fullAddress); ?>
                            </span>
                            <span class="location-address-sm text-[var(--text-light)] text-[8px] md:text-[10px] truncate sm:hidden">
                                <?php echo htmlspecialchars($shortAddress); ?>
                            </span>
                        </div>
                        <?php if ($totalActiveBins > 0): ?>
                        <span class="text-[10px] md:text-xs text-[var(--text-light)] bg-[var(--bg)] px-2 py-0.5 rounded-full flex-shrink-0">
                            <?php echo $totalActiveBins; ?> bins
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Day & Date -->
                    <div class="flex items-center gap-2 bg-[var(--bg)] px-3 py-1.5 md:px-4 md:py-2 rounded-full border border-[var(--border)]">
                        <i class="fas fa-calendar-day datetime-text text-[var(--accent)] text-xs md:text-base"></i>
                        <span class="datetime-text text-[var(--text-light)] font-medium text-xs md:text-sm lg:text-base">
                            <?php echo $dayOfWeek; ?>, <?php echo $dateFormatted; ?>
                        </span>
                    </div>
                    
                    <!-- Time -->
                    <div class="flex items-center gap-2 bg-[var(--bg)] px-3 py-1.5 md:px-4 md:py-2 rounded-full border border-[var(--border)]">
                        <i class="fas fa-clock datetime-text text-[var(--primary)] text-xs md:text-base"></i>
                        <span class="datetime-text text-[var(--text)] font-bold text-xs md:text-sm lg:text-base" id="currentTimeDisplay">
                            <?php echo $timeFormatted; ?>
                        </span>
                        <span class="flex items-center gap-1 text-[8px] md:text-xs text-[var(--accent)]">
                            <i class="fas fa-circle text-[4px] md:text-[6px] pulse-dot"></i>
                            LIVE
                        </span>
                    </div>
                    
                    <!-- Refresh Button -->
                    <button class="refresh-btn" id="refreshBtn" title="Refresh data">
                        <i class="fas fa-sync-alt"></i>
                        <span class="hidden sm:inline">Refresh</span>
                    </button>
                </div>
            </div>
            
            <!-- Stats Row: Today's Disposals + System Metrics -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 md:gap-4 lg:gap-6 mb-4">
                <div class="stat-card p-3 md:p-4 lg:p-5 text-center">
                    <div class="text-2xl md:text-3xl lg:text-5xl font-bold text-[var(--primary)]"><?php echo number_format($todayStats['total_disposals'] ?? 0); ?></div>
                    <div class="text-xs md:text-sm lg:text-base text-[var(--text-light)] mt-0.5 md:mt-1">Today's Disposals</div>
                </div>
                <div class="stat-card p-3 md:p-4 lg:p-5 text-center">
                    <div class="metric-value"><?php echo $segregation; ?></div>
                    <div class="metric-label">Segregation Rate</div>
                </div>
                <div class="stat-card p-3 md:p-4 lg:p-5 text-center">
                    <div class="metric-value"><?php echo $performance; ?></div>
                    <div class="metric-label">Device Performance</div>
                </div>
                <div class="stat-card p-3 md:p-4 lg:p-5 text-center">
                    <div class="metric-value"><?php echo $maintainability; ?></div>
                    <div class="metric-label">Maintainability</div>
                </div>
                <div class="stat-card p-3 md:p-4 lg:p-5 text-center">
                    <div class="text-2xl md:text-3xl lg:text-4xl font-bold text-[var(--text)]"><?php echo $lastDisposalTime ?: 'N/A'; ?></div>
                    <div class="text-xs md:text-sm lg:text-base text-[var(--text-light)] mt-0.5 md:mt-1">Last Disposal</div>
                </div>
            </div>
            
            <!-- Bin Status Grid -->
            <div class="grid grid-cols-3 gap-3 md:gap-4 lg:gap-6 mb-4">
                <?php 
                $binTypes = [
                    ['type' => 'biodegradable', 'label' => 'Biodegradable', 'icon' => 'fa-leaf', 'color' => '#4CAF50', 'fill' => $bioFill],
                    ['type' => 'plastic', 'label' => 'Plastic', 'icon' => 'fa-bottle-water', 'color' => '#8BC34A', 'fill' => $plasticFill],
                    ['type' => 'metal', 'label' => 'Metal', 'icon' => 'fa-cubes', 'color' => '#388E3C', 'fill' => $metalFill]
                ];
                
                foreach ($binTypes as $bin):
                    $fill = $bin['fill'];
                    $status = getStatusColor($fill);
                    $statusText = getStatusText($fill);
                    $circumference = 2 * M_PI * 40;
                    $offset = $circumference - ($fill / 100) * $circumference;
                    
                    $statusClass = $status === 'danger' ? 'badge-critical' : 
                                  ($status === 'warning' ? 'badge-warning' : 
                                  ($status === 'info' ? 'badge-info' : 'badge-success'));
                ?>
                <div class="bin-card p-3 md:p-4 lg:p-6 text-center">
                    <div class="flex items-center justify-between mb-2 md:mb-3">
                        <span class="text-xs md:text-sm lg:text-lg font-semibold text-[var(--text)]">
                            <i class="fas <?php echo $bin['icon']; ?>" style="color: <?php echo $bin['color']; ?>"></i>
                            <span class="hidden sm:inline"><?php echo $bin['label']; ?></span>
                            <span class="sm:hidden"><?php echo substr($bin['label'], 0, 4); ?></span>
                        </span>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </div>
                    
                    <div class="relative w-20 h-20 md:w-28 md:h-28 lg:w-36 lg:h-36 mx-auto">
                        <svg class="w-full h-full progress-ring" viewBox="0 0 120 120">
                            <circle cx="60" cy="60" r="45" fill="none" stroke="var(--border)" stroke-width="10"/>
                            <circle cx="60" cy="60" r="45" fill="none" 
                                    stroke="<?php echo $bin['color']; ?>" 
                                    stroke-width="10"
                                    stroke-linecap="round"
                                    stroke-dasharray="<?php echo $circumference; ?>"
                                    stroke-dashoffset="<?php echo $circumference; ?>"
                                    id="circle-<?php echo $bin['type']; ?>"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-xl md:text-2xl lg:text-4xl font-bold" style="color: <?php echo $bin['color']; ?>">
                                <?php echo number_format($fill, 0); ?>%
                            </span>
                        </div>
                    </div>
                    <div class="mt-1 flex items-center justify-center gap-1 text-[10px] md:text-xs text-[var(--text-light)]">
                        <i class="fas fa-circle text-[6px] md:text-[8px] text-green-500"></i>
                        <span>Online</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Alerts Row -->
            <div class="glass-card-light p-3 md:p-4 lg:p-5 border border-[var(--border)]">
                <div class="flex items-center gap-2 md:gap-3 mb-2 md:mb-3">
                    <i class="fas fa-bell text-yellow-500 text-sm md:text-lg lg:text-2xl"></i>
                    <span class="text-sm md:text-base lg:text-lg font-semibold text-[var(--text)]">Active Alerts</span>
                    <span class="ml-auto text-xs md:text-sm lg:text-base text-[var(--text-light)]"><?php echo count($alerts); ?> unresolved</span>
                </div>
                <?php if (count($alerts) > 0): ?>
                <div class="space-y-1 md:space-y-2 max-h-12 md:max-h-16 lg:max-h-20 overflow-y-auto scrollbar-thin">
                    <?php foreach (array_slice($alerts, 0, 4) as $alert): 
                        $severityColor = ($alert['severity'] == 'critical') ? 'text-red-500' : 'text-yellow-500';
                    ?>
                    <div class="alert-item flex items-center gap-2 md:gap-3 text-xs md:text-sm lg:text-base p-1.5 md:p-2 rounded-lg bg-[var(--bg)]">
                        <i class="fas fa-circle text-[6px] md:text-[8px] lg:text-[10px] <?php echo $severityColor; ?>"></i>
                        <span class="text-[var(--text)] flex-1 truncate"><?php echo htmlspecialchars($alert['message']); ?></span>
                        <span class="text-[var(--text-light)] text-[10px] md:text-xs lg:text-sm whitespace-nowrap">
                            <?php echo date('H:i', strtotime($alert['created_at'])); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center text-xs md:text-sm lg:text-base text-[var(--text-light)] py-1 md:py-2 lg:py-3">
                    <i class="fas fa-check-circle text-[var(--accent)] mr-2"></i> All systems operational
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Footer -->
            <div class="flex justify-between items-center pt-3 md:pt-4 mt-2 border-t border-[var(--border)]">
                <span class="text-[10px] md:text-sm lg:text-base text-[var(--text-light)]">
                    <i class="fas fa-signal mr-1 md:mr-2 text-[var(--primary)]"></i> 
                    <span id="signalStrength">Strong</span>
                </span>
                <span class="text-[10px] md:text-sm lg:text-base text-[var(--text-light)]">
                    <i class="fas fa-bolt mr-1 md:mr-2 text-[var(--accent)]"></i> 
                    <span id="batteryStatus">Connected</span>
                </span>
                <span class="text-[10px] md:text-sm lg:text-base text-[var(--text-light)]">
                    <i class="fas fa-sync-alt mr-1 md:mr-2 text-[var(--primary)]"></i>
                    <span id="updateTime">Updating...</span>
                </span>
            </div>
        </div>
    </div>

    <script>
        (function() {
            'use strict';
            
            // ============================================================
            // Theme Toggle
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
                    if (themeIcon) themeIcon.className = 'fas fa-sun';
                    if (themeLabel) themeLabel.textContent = 'Light';
                } else {
                    if (themeIcon) themeIcon.className = 'fas fa-moon';
                    if (themeLabel) themeLabel.textContent = 'Dark';
                }
            }
            
            // ============================================================
            // Update time
            // ============================================================
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                const timeDisplay = document.getElementById('currentTimeDisplay');
                if (timeDisplay) {
                    timeDisplay.textContent = timeString;
                }
                
                const updateTimeEl = document.getElementById('updateTime');
                if (updateTimeEl) {
                    updateTimeEl.textContent = 'Updated: ' + now.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            }
            
            updateTime();
            setInterval(updateTime, 1000);
            
            // ============================================================
            // Animate progress circles on load
            // ============================================================
            const binData = [
                { type: 'biodegradable', fill: <?php echo $bioFill; ?> },
                { type: 'plastic', fill: <?php echo $plasticFill; ?> },
                { type: 'metal', fill: <?php echo $metalFill; ?> }
            ];
            const circumference = 2 * Math.PI * 45;
            
            binData.forEach(function(bin, index) {
                const circle = document.getElementById('circle-' + bin.type);
                if (circle) {
                    const offset = circumference - (bin.fill / 100) * circumference;
                    setTimeout(function() {
                        circle.style.strokeDashoffset = offset;
                    }, 200 + (index * 150));
                }
            });
            
            // ============================================================
            // Refresh button: reload page with animation
            // ============================================================
            const refreshBtn = document.getElementById('refreshBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const icon = this.querySelector('i');
                    icon.style.transform = 'rotate(360deg)';
                    setTimeout(() => {
                        location.reload();
                    }, 300);
                });
            }
            
            // ============================================================
            // Simulate signal and battery status
            // ============================================================
            function updateStatus() {
                const signals = ['Strong', 'Good', 'Fair'];
                const signal = signals[Math.floor(Math.random() * 2)];
                const signalEl = document.getElementById('signalStrength');
                if (signalEl) signalEl.textContent = signal;
                
                const battery = ['Connected', 'Stable', 'Charging'];
                const status = battery[Math.floor(Math.random() * 2)];
                const batteryEl = document.getElementById('batteryStatus');
                if (batteryEl) batteryEl.textContent = status;
            }
            setInterval(updateStatus, 30000);
            
            // Auto-refresh every 60 seconds (if not manually refreshed)
            setInterval(function() {
                location.reload();
            }, 60000);
            
        })();
    </script>
</body>
</html>