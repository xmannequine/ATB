<?php
// =============================================
// binloc.php - Trash Bin Locations Management
// Automated Trash Bin System
// =============================================

session_start();
require_once 'config/config.php';

// Check login
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$currentUser = getCurrentUser();

// =============================================
// Handle Report Generation - Built-in
// =============================================
if (isset($_GET['action']) && $_GET['action'] === 'report') {
    // Fetch all bins with fill levels
    try {
        $stmt = $pdo->query("
            SELECT 
                tb.*,
                COALESCE(vbs.fill_level, 0) as fill_level,
                COALESCE(vbs.temperature, 0) as temperature,
                COALESCE(vbs.humidity, 0) as humidity,
                COALESCE(vbs.last_updated, tb.created_at) as last_updated
            FROM trash_bins tb
            LEFT JOIN v_bin_status vbs ON tb.bin_id = vbs.bin_id
            WHERE tb.status = 'active' OR tb.status = 'maintenance' OR tb.status = 'inactive'
            ORDER BY tb.bin_id
        ");
        $bins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $bins = [];
    }
    
    // Get statistics
    $totalBins = count($bins);
    $activeBins = 0;
    $maintenanceBins = 0;
    $typeCounts = [];
    $sumFill = 0;
    
    foreach ($bins as $bin) {
        if ($bin['status'] === 'active') $activeBins++;
        if ($bin['status'] === 'maintenance') $maintenanceBins++;
        
        $type = $bin['bin_type'] ?? 'general';
        if (!isset($typeCounts[$type])) $typeCounts[$type] = 0;
        $typeCounts[$type]++;
        
        $sumFill += floatval($bin['fill_level'] ?? 0);
    }
    
    $avgFill = $totalBins > 0 ? $sumFill / $totalBins : 0;
    
    // Set Word document headers
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="SmartBin_Report_' . date('Y-m-d') . '.doc"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // Build the report HTML
    $generatedBy = $_SESSION['full_name'] ?? 'System';
    $generatedAt = date('F d, Y h:i:s A');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
               xmlns:w="urn:schemas-microsoft-com:office:word" 
               xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="UTF-8">
        <title>SmartBin - Bin Locations Report</title>
        <!--[if gte mso 9]>
        <xml>
            <w:WordDocument>
                <w:View>Print</w:View>
                <w:Zoom>100</w:Zoom>
            </w:WordDocument>
        </xml>
        <![endif]-->
        <style>
            @page {
                size: 8.5in 11in;
                margin: 1in 0.75in 1in 0.75in;
                mso-page-orientation: portrait;
            }
            
            body {
                font-family: "Segoe UI", Arial, sans-serif;
                font-size: 11pt;
                color: #333333;
                line-height: 1.6;
            }
            
            .report-header {
                border-bottom: 3px solid #8BC34A;
                padding-bottom: 15px;
                margin-bottom: 20px;
                text-align: center;
            }
            
            .report-header h1 {
                font-size: 28pt;
                color: #2E7D32;
                margin: 0;
                font-weight: 700;
            }
            
            .report-header .subtitle {
                font-size: 14pt;
                color: #558B2F;
                margin-top: 5px;
            }
            
            .report-header .meta {
                font-size: 10pt;
                color: #757575;
                margin-top: 8px;
            }
            
            .section-title {
                font-size: 16pt;
                font-weight: 700;
                color: #2E7D32;
                border-bottom: 2px solid #C5E1A5;
                padding-bottom: 8px;
                margin-top: 24px;
                margin-bottom: 16px;
            }
            
            .stats-grid {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            .stats-grid td {
                padding: 12px 16px;
                background-color: #F5F5F5;
                border: 1px solid #E0E0E0;
                text-align: center;
                font-weight: 600;
            }
            
            .stat-value {
                font-size: 20pt;
                color: #8BC34A;
                display: block;
                font-weight: 700;
            }
            
            .stat-label {
                font-size: 10pt;
                color: #757575;
                font-weight: 400;
            }
            
            .data-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 12px;
                font-size: 10pt;
            }
            
            .data-table th {
                background-color: #8BC34A;
                color: #FFFFFF;
                padding: 10px 12px;
                text-align: left;
                font-weight: 600;
                border: 1px solid #7CB342;
            }
            
            .data-table td {
                padding: 8px 12px;
                border: 1px solid #E0E0E0;
                vertical-align: middle;
            }
            
            .data-table tr:nth-child(even) {
                background-color: #F9FBE7;
            }
            
            .badge {
                display: inline-block;
                padding: 2px 12px;
                border-radius: 12px;
                font-size: 9pt;
                font-weight: 600;
            }
            
            .badge-active {
                background-color: #E8F5E9;
                color: #2E7D32;
                border: 1px solid #A5D6A7;
            }
            
            .badge-maintenance {
                background-color: #FFF3E0;
                color: #E65100;
                border: 1px solid #FFCC80;
            }
            
            .badge-inactive {
                background-color: #FFEBEE;
                color: #C62828;
                border: 1px solid #EF9A9A;
            }
            
            .type-badge {
                display: inline-block;
                padding: 2px 10px;
                border-radius: 4px;
                font-size: 9pt;
                font-weight: 600;
            }
            
            .type-biodegradable {
                background-color: #E8F5E9;
                color: #2E7D32;
            }
            
            .type-plastic {
                background-color: #E3F2FD;
                color: #1565C0;
            }
            
            .type-metal {
                background-color: #F5F5F5;
                color: #616161;
            }
            
            .type-general {
                background-color: #FFF3E0;
                color: #E65100;
            }
            
            .fill-bar {
                display: inline-block;
                width: 80px;
                height: 8px;
                background-color: #E0E0E0;
                border-radius: 4px;
                overflow: hidden;
                vertical-align: middle;
                margin-right: 6px;
            }
            
            .fill-bar .fill {
                height: 100%;
                border-radius: 4px;
            }
            
            .fill-low .fill { background-color: #8BC34A; }
            .fill-medium .fill { background-color: #FFC107; }
            .fill-high .fill { background-color: #FF9800; }
            .fill-full .fill { background-color: #F44336; }
            
            .report-footer {
                border-top: 2px solid #E0E0E0;
                padding-top: 12px;
                margin-top: 24px;
                text-align: center;
                font-size: 9pt;
                color: #9E9E9E;
            }
            
            .report-footer .footer-line {
                margin-top: 4px;
            }
            
            .notes-box {
                margin-top: 20px;
                padding: 12px 16px;
                background-color: #F5F5F5;
                border-left: 4px solid #8BC34A;
                font-size: 10pt;
                color: #616161;
            }
            
            .notes-box ul {
                margin: 6px 0 0 20px;
                padding-left: 0;
            }
        </style>
    </head>
    <body>
    
    <!-- HEADER -->
    <div class="report-header">
        <h1>♻️ SmartBin</h1>
        <div class="subtitle">Trash Bin Locations Report</div>
        <div class="meta">
            Generated: ' . $generatedAt . ' &nbsp;|&nbsp; By: ' . htmlspecialchars($generatedBy) . '
        </div>
    </div>
    
    <!-- SUMMARY STATS -->
    <table class="stats-grid">
        <tr>
            <td>
                <span class="stat-value">' . $totalBins . '</span>
                <span class="stat-label">Total Bins</span>
            </td>
            <td>
                <span class="stat-value" style="color: #4CAF50;">' . $activeBins . '</span>
                <span class="stat-label">Active</span>
            </td>
            <td>
                <span class="stat-value" style="color: #FF9800;">' . $maintenanceBins . '</span>
                <span class="stat-label">Maintenance</span>
            </td>
            <td>
                <span class="stat-value" style="color: #2196F3;">' . number_format($avgFill, 1) . '%</span>
                <span class="stat-label">Avg Fill Level</span>
            </td>
        </tr>
    </table>
    
    <!-- BIN TYPES BREAKDOWN -->
    <div class="section-title">📊 Bin Type Breakdown</div>
    <table class="data-table" style="width: 60%;">
        <thead>
            <tr>
                <th>Bin Type</th>
                <th style="text-align: center;">Count</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($typeCounts as $type => $count) {
        echo '
            <tr>
                <td><span class="type-badge type-' . $type . '">' . ucfirst($type) . '</span></td>
                <td style="text-align: center; font-weight: 600;">' . $count . '</td>
            </tr>';
    }
    
    echo '
        </tbody>
    </table>
    
    <!-- BIN LIST -->
    <div class="section-title">📋 Complete Bin List</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Bin Name</th>
                <th>Code</th>
                <th>Location</th>
                <th>Building</th>
                <th>Type</th>
                <th>Status</th>
                <th style="text-align: center;">Fill Level</th>
            </tr>
        </thead>
        <tbody>';
    
    if (empty($bins)) {
        echo '
            <tr>
                <td colspan="8" style="text-align: center; padding: 30px; color: #757575;">
                    <i>No bins found in the system.</i>
                </td>
            </tr>';
    } else {
        foreach ($bins as $bin) {
            $fillLevel = floatval($bin['fill_level'] ?? 0);
            $fillClass = $fillLevel >= 80 ? 'fill-full' : ($fillLevel >= 50 ? 'fill-high' : ($fillLevel >= 25 ? 'fill-medium' : 'fill-low'));
            $statusClass = $bin['status'] === 'active' ? 'badge-active' : ($bin['status'] === 'maintenance' ? 'badge-maintenance' : 'badge-inactive');
            
            echo '
                <tr>
                    <td style="font-weight: 600;">#' . $bin['bin_id'] . '</td>
                    <td><strong>' . htmlspecialchars($bin['bin_name']) . '</strong></td>
                    <td style="font-family: monospace; color: #757575;">' . htmlspecialchars($bin['bin_code']) . '</td>
                    <td>' . htmlspecialchars($bin['location']) . '</td>
                    <td>' . htmlspecialchars($bin['building'] ?? '-') . '</td>
                    <td><span class="type-badge type-' . $bin['bin_type'] . '">' . ucfirst($bin['bin_type']) . '</span></td>
                    <td><span class="badge ' . $statusClass . '">' . ucfirst($bin['status']) . '</span></td>
                    <td style="text-align: center;">
                        <span class="fill-bar ' . $fillClass . '">
                            <span class="fill" style="width: ' . $fillLevel . '%;"></span>
                        </span>
                        ' . number_format($fillLevel, 0) . '%
                    </td>
                </tr>';
        }
    }
    
    echo '
        </tbody>
    </table>
    
    <!-- NOTES -->
    <div class="notes-box">
        <strong>📌 Report Notes:</strong>
        <ul>
            <li>Fill levels are measured using ultrasonic sensors and are updated in real-time.</li>
            <li>Bins marked as <strong>Maintenance</strong> are currently unavailable for waste disposal.</li>
            <li>This report is auto-generated and reflects the current state of the system.</li>
        </ul>
    </div>
    
    <!-- FOOTER -->
    <div class="report-footer">
        <div>SmartBin v2.0 · Automated Trash Bin Management System</div>
        <div class="footer-line">
            <span>ESP32 · AI Vision · Ultrasonic · Solar-Powered</span>
        </div>
        <div class="footer-line" style="margin-top: 4px; font-size: 8pt; color: #BDBDBD;">
            Report ID: SB-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) . '
        </div>
    </div>
    
    </body>
    </html>';
    exit();
}

// =============================================
// Handle AJAX Requests
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            $bin_name = sanitize($_POST['bin_name']);
            $bin_code = sanitize($_POST['bin_code']);
            $location = sanitize($_POST['location']);
            $building = sanitize($_POST['building']);
            $floor = sanitize($_POST['floor']);
            $bin_type = sanitize($_POST['bin_type']);
            $status = sanitize($_POST['status']);
            
            $stmt = $pdo->prepare("INSERT INTO trash_bins (bin_name, bin_code, location, building, floor, bin_type, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$bin_name, $bin_code, $location, $building, $floor, $bin_type, $status]);
            
            echo json_encode(['success' => true, 'message' => 'Bin added successfully']);
            exit();
        }
        
        if ($action === 'edit') {
            $bin_id = (int)$_POST['bin_id'];
            $bin_name = sanitize($_POST['bin_name']);
            $bin_code = sanitize($_POST['bin_code']);
            $location = sanitize($_POST['location']);
            $building = sanitize($_POST['building']);
            $floor = sanitize($_POST['floor']);
            $bin_type = sanitize($_POST['bin_type']);
            $status = sanitize($_POST['status']);
            
            $stmt = $pdo->prepare("UPDATE trash_bins SET bin_name=?, bin_code=?, location=?, building=?, floor=?, bin_type=?, status=? WHERE bin_id=?");
            $stmt->execute([$bin_name, $bin_code, $location, $building, $floor, $bin_type, $status, $bin_id]);
            
            echo json_encode(['success' => true, 'message' => 'Bin updated successfully']);
            exit();
        }
        
        if ($action === 'delete') {
            $bin_id = (int)$_POST['bin_id'];
            $stmt = $pdo->prepare("DELETE FROM trash_bins WHERE bin_id = ?");
            $stmt->execute([$bin_id]);
            
            echo json_encode(['success' => true, 'message' => 'Bin deleted successfully']);
            exit();
        }
        
        if ($action === 'set_active') {
            $bin_id = (int)$_POST['bin_id'];
            $_SESSION['active_bin_id'] = $bin_id;
            
            // Get bin details
            $stmt = $pdo->prepare("SELECT * FROM trash_bins WHERE bin_id = ?");
            $stmt->execute([$bin_id]);
            $bin = $stmt->fetch();

            if (function_exists('logActivity')) {
                logActivity($_SESSION['user_id'] ?? null, 'set_active_bin', 'Selected bin: ' . ($bin['bin_name'] ?? $bin_id));
            }
            
            echo json_encode(['success' => true, 'message' => 'Active bin updated', 'bin' => $bin]);
            exit();
        }
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// =============================================
// Fetch Bins
// =============================================
try {
    $stmt = $pdo->query("SELECT * FROM trash_bins ORDER BY bin_id DESC");
    $bins = $stmt->fetchAll();
    
    // Get active bin from session
    $activeBinId = $_SESSION['active_bin_id'] ?? 0;
    $activeBin = null;
    if ($activeBinId) {
        $stmt = $pdo->prepare("SELECT * FROM trash_bins WHERE bin_id = ?");
        $stmt->execute([$activeBinId]);
        $activeBin = $stmt->fetch();
    }
    
} catch(PDOException $e) {
    $bins = [];
    $activeBin = null;
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bin Locations - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
            --sidebar-bg: #FFFFFF;
            --sidebar-text: #424242;
            --hover-bg: rgba(139, 195, 74, 0.08);
        }
        
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
        }
        
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        
        body {
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
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
        
        .input-field {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            transition: all 0.3s ease;
        }
        .input-field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 195, 74, 0.12);
            outline: none;
        }
        .input-field::placeholder { color: var(--text-light); }
        .input-field option { background: var(--card-bg); color: var(--text); }
        
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
        
        .btn-outline {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            transition: all 0.3s ease;
        }
        .btn-outline:hover {
            border-color: var(--primary);
            background: var(--hover-bg);
        }
        
        .btn-report {
            background: #1a73e8;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-report:hover {
            background: #1557b0;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(26, 115, 232, 0.30);
        }
        
        .badge-status {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-active { background: rgba(139, 195, 74, 0.12); color: #4CAF50; border: 1px solid rgba(139, 195, 74, 0.15); }
        .badge-inactive { background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.15); }
        .badge-maintenance { background: rgba(234, 179, 8, 0.12); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.15); }
        
        .type-badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        .type-biodegradable { background: rgba(139, 195, 74, 0.12); color: #4CAF50; }
        .type-plastic { background: rgba(76, 175, 80, 0.10); color: #388E3C; }
        .type-metal { background: rgba(76, 175, 80, 0.08); color: #2E7D32; }
        .type-general { background: rgba(66, 66, 66, 0.06); color: var(--text-light); }
        
        .table-row-hover:hover { background: var(--hover-bg); }
        
        .scrollbar-thin::-webkit-scrollbar { width: 6px; height: 6px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: var(--border); border-radius: 10px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: var(--accent); }
        
        .action-btn {
            width: 32px; height: 32px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 8px; transition: all 0.2s ease;
        }
        .action-btn:hover { transform: scale(1.1); }
        .action-btn.edit { color: #eab308; background: rgba(234, 179, 8, 0.08); }
        .action-btn.edit:hover { background: rgba(234, 179, 8, 0.15); }
        .action-btn.delete { color: #ef4444; background: rgba(239, 68, 68, 0.08); }
        .action-btn.delete:hover { background: rgba(239, 68, 68, 0.15); }
        .action-btn.active-select { color: #4CAF50; background: rgba(76, 175, 80, 0.08); }
        .action-btn.active-select:hover { background: rgba(76, 175, 80, 0.15); }
        
        .gradient-text {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            color: transparent;
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
        
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px); z-index: 999;
            display: none; align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            max-width: 550px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
        }
        .modal-box::-webkit-scrollbar { width: 4px; }
        .modal-box::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .sidebar {
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            transition: background 0.3s ease;
        }
        .sidebar a {
            transition: all 0.2s ease;
            color: var(--sidebar-text);
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
        }
        
        .stat-icon-green { background: rgba(139, 195, 74, 0.10); border: 1px solid rgba(139, 195, 74, 0.10); color: var(--primary); }
        .stat-icon-emerald { background: rgba(76, 175, 80, 0.10); border: 1px solid rgba(76, 175, 80, 0.10); color: var(--accent); }
        .stat-icon-yellow { background: rgba(234, 179, 8, 0.10); border: 1px solid rgba(234, 179, 8, 0.10); color: #eab308; }
        .stat-icon-purple { background: rgba(76, 175, 80, 0.08); border: 1px solid rgba(76, 175, 80, 0.08); color: #2E7D32; }
        
        @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.3; transform: scale(0.8); } }
        .pulse-dot { animation: pulse-dot 2s ease-in-out infinite; }
    </style>
</head>
<body>
    
    <!-- SIDEBAR OVERLAY -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- SIDEBAR -->
    <nav class="sidebar fixed top-0 left-0 h-full w-[270px] p-6 flex flex-col shadow-xl overflow-y-auto" id="sidebar">
        <div class="flex items-center gap-3 pb-5 mb-5 border-b border-[var(--border)]">
            <div class="w-10 h-10 rounded-xl bg-[var(--primary)]/20 flex items-center justify-center">
                <i class="fas fa-trash-alt text-[var(--primary)] text-xl"></i>
            </div>
            <div>
                <a href="dashboard.php" class="text-xl font-bold tracking-tight hover:opacity-80 transition">
                    Smart<span class="text-[var(--primary)]">Bin</span>
                </a>
                <span class="block text-[10px] uppercase tracking-wider text-[var(--text-light)] font-medium">v2.0 · IoT Platform</span>
            </div>
        </div>
        
        <div class="text-[10px] uppercase tracking-wider text-[var(--text-light)]/60 font-semibold mt-1 mb-3 px-3">Main</div>
        <ul class="flex-1 space-y-0.5">
            <li>
                <a href="index.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-chart-pie w-5 text-[var(--text-light)]"></i> <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-users w-5 text-[var(--text-light)]"></i> <span>User Management</span>
                </a>
            </li>
            <li class="active">
                <a href="binloc.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-all duration-200">
                    <i class="fas fa-microchip w-5 text-[var(--text-light)]"></i> <span class="font-medium">Trash Bin List</span>
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-database w-5 text-[var(--text-light)]"></i> <span>Waste Log</span>
                </a>
            </li>
        </ul>
        
        <div class="text-[10px] uppercase tracking-wider text-[var(--text-light)]/60 font-semibold mt-4 mb-3 px-3">System</div>
        <ul class="space-y-0.5">
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-cogs w-5 text-[var(--text-light)]"></i> <span>Shredder</span>
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-solar-panel w-5 text-[var(--text-light)]"></i> <span>Solar Power</span>
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-bell w-5 text-[var(--text-light)]"></i> <span>Alerts</span>
                </a>
            </li>
        </ul>
        
        <div class="text-[10px] uppercase tracking-wider text-[var(--text-light)]/60 font-semibold mt-4 mb-3 px-3">Admin</div>
        <ul class="space-y-0.5">
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-user-cog w-5 text-[var(--text-light)]"></i> <span>Settings</span>
                </a>
            </li>
            <li>
                <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-red-500/10 hover:text-red-500 transition-all duration-200">
                    <i class="fas fa-sign-out-alt w-5 text-[var(--text-light)]"></i> <span>Logout</span>
                </a>
            </li>
        </ul>
        
        <div class="mt-5 pt-4 border-t border-[var(--border)] flex items-center gap-2 text-xs text-[var(--text-light)]">
            <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-[var(--primary)] pulse-dot"></span>
                Online
            </span>
            <span class="ml-auto bg-[var(--hover-bg)] px-2.5 py-0.5 rounded-full text-[10px] font-medium">v2.0</span>
        </div>
    </nav>
    
    <!-- MAIN CONTENT -->
    <div class="md:ml-[270px] p-4 md:p-8 min-h-screen transition-all duration-300">
        
        <!-- Header -->
        <div class="card p-6 mb-6 flex justify-between items-center flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="text-[var(--primary)] hover:text-[var(--accent)] transition flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <div class="h-6 w-px bg-[var(--border)] hidden sm:block"></div>
                <h1 class="text-2xl font-bold text-[var(--text)] flex items-center gap-3">
                    <i class="fas fa-map-marker-alt text-[var(--primary)]"></i>
                    <span class="gradient-text">Bin Locations</span>
                </h1>
            </div>
            <div class="flex gap-3 items-center flex-wrap">
                <button class="theme-toggle px-3 py-2 rounded-xl text-sm font-medium flex items-center gap-2" id="themeToggle">
                    <i class="fas fa-moon" id="themeIcon"></i>
                    <span id="themeLabel" class="hidden sm:inline">Dark</span>
                </button>
                
                <?php if ($activeBin): ?>
                <div class="text-sm text-[var(--text-light)] hidden md:flex items-center gap-2 bg-[var(--hover-bg)] px-3 py-1.5 rounded-full border border-[var(--border)]">
                    <i class="fas fa-check-circle text-[var(--primary)] text-xs"></i>
                    Active: <span class="text-[var(--text)] font-medium"><?php echo htmlspecialchars($activeBin['bin_name']); ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Report Button -->
                <a href="?action=report" target="_blank" class="btn-report px-4 py-2 rounded-xl text-sm flex items-center gap-2 shadow-sm">
                    <i class="fas fa-file-word"></i> Export Report
                </a>
                
                <button onclick="openModal('addModal')" class="btn-primary px-4 py-2 rounded-xl text-sm flex items-center gap-2 shadow-sm">
                    <i class="fas fa-plus"></i> Add Bin
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card p-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl stat-icon-green flex items-center justify-center">
                        <i class="fas fa-trash-alt text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-[var(--text)]"><?php echo count($bins); ?></h3>
                        <p class="text-[var(--text-light)] text-sm">Total Bins</p>
                    </div>
                </div>
            </div>
            <div class="stat-card p-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl stat-icon-emerald flex items-center justify-center">
                        <i class="fas fa-check-circle text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-[var(--text)]">
                            <?php echo count(array_filter($bins, fn($b) => $b['status'] === 'active')); ?>
                        </h3>
                        <p class="text-[var(--text-light)] text-sm">Active</p>
                    </div>
                </div>
            </div>
            <div class="stat-card p-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl stat-icon-yellow flex items-center justify-center">
                        <i class="fas fa-tools text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-[var(--text)]">
                            <?php echo count(array_filter($bins, fn($b) => $b['status'] === 'maintenance')); ?>
                        </h3>
                        <p class="text-[var(--text-light)] text-sm">Maintenance</p>
                    </div>
                </div>
            </div>
            <div class="stat-card p-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl stat-icon-purple flex items-center justify-center">
                        <i class="fas fa-layer-group text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-[var(--text)]">
                            <?php echo count(array_unique(array_column($bins, 'building'))); ?>
                        </h3>
                        <p class="text-[var(--text-light)] text-sm">Buildings</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="card p-4 mb-6 flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px] relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-light)]"></i>
                <input type="text" id="searchInput" placeholder="Search bins..." onkeyup="filterTable()" 
                       class="input-field w-full pl-10 pr-4 py-2.5 rounded-xl">
            </div>
            <select id="statusFilter" onchange="filterTable()" class="input-field px-4 py-2.5 rounded-xl">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="maintenance">Maintenance</option>
            </select>
            <select id="typeFilter" onchange="filterTable()" class="input-field px-4 py-2.5 rounded-xl">
                <option value="all">All Types</option>
                <option value="biodegradable">Biodegradable</option>
                <option value="plastic">Plastic</option>
                <option value="metal">Metal</option>
                <option value="general">General</option>
            </select>
            <button onclick="resetFilters()" class="btn-outline px-4 py-2.5 rounded-xl text-sm flex items-center gap-2">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto scrollbar-thin">
                <table class="w-full" id="binTable">
                    <thead>
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-medium text-[var(--text-light)] uppercase tracking-wider">Bin</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-[var(--text-light)] uppercase tracking-wider hidden md:table-cell">Code</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-[var(--text-light)] uppercase tracking-wider">Location</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-[var(--text-light)] uppercase tracking-wider hidden lg:table-cell">Building</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-[var(--text-light)] uppercase tracking-wider">Type</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-[var(--text-light)] uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-[var(--text-light)] uppercase tracking-wider">Fill</th>
                            <th class="px-6 py-4 text-center text-xs font-medium text-[var(--text-light)] uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border)]">
                        <?php if (empty($bins)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center text-[var(--text-light)]">
                                <i class="fas fa-trash-alt text-5xl text-[var(--border)] mb-4 block"></i>
                                <p class="text-lg font-medium">No bins found</p>
                                <p class="text-sm mt-1">Add your first trash bin location</p>
                            </td>
                        </tr>
                        <?php else: foreach ($bins as $bin): 
                            $fillLevel = floatval($bin['fill_level'] ?? 0);
                            $fillColor = $fillLevel >= 80 ? 'text-red-500' : ($fillLevel >= 50 ? 'text-yellow-500' : 'text-[var(--primary)]');
                            $fillBg = $fillLevel >= 80 ? 'bg-red-500' : ($fillLevel >= 50 ? 'bg-yellow-500' : 'bg-[var(--primary)]');
                        ?>
                        <tr id="bin-row-<?php echo $bin['bin_id']; ?>" class="table-row-hover">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-[var(--primary)] to-[var(--accent)] flex items-center justify-center text-white font-bold text-sm">
                                        <?php echo strtoupper(substr($bin['bin_name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-[var(--text)]"><?php echo htmlspecialchars($bin['bin_name']); ?></div>
                                        <div class="text-xs text-[var(--text-light)]">ID: #<?php echo $bin['bin_id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell text-[var(--text-light)] font-mono text-sm"><?php echo htmlspecialchars($bin['bin_code']); ?></td>
                            <td class="px-6 py-4 text-[var(--text)]"><?php echo htmlspecialchars($bin['location']); ?></td>
                            <td class="px-6 py-4 hidden lg:table-cell text-[var(--text-light)]"><?php echo htmlspecialchars($bin['building'] ?? '-'); ?></td>
                            <td class="px-6 py-4">
                                <span class="type-badge type-<?php echo $bin['bin_type']; ?>">
                                    <?php echo ucfirst($bin['bin_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="badge-status badge-<?php echo $bin['status']; ?>">
                                    <?php echo ucfirst($bin['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 h-1.5 bg-[var(--border)] rounded-full overflow-hidden">
                                        <div class="h-full rounded-full <?php echo $fillBg; ?>" 
                                             style="width: <?php echo $fillLevel; ?>%"></div>
                                    </div>
                                    <span class="text-xs font-medium <?php echo $fillColor; ?>"><?php echo number_format($fillLevel, 0); ?>%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex gap-1.5 justify-center">
                                    <button onclick="selectBin(<?php echo $bin['bin_id']; ?>)" class="action-btn active-select" title="Set Active">
                                        <i class="fas fa-check-circle text-sm"></i>
                                    </button>
                                    <button onclick="editBin(<?php echo htmlspecialchars(json_encode($bin)); ?>)" class="action-btn edit" title="Edit">
                                        <i class="fas fa-edit text-sm"></i>
                                    </button>
                                    <button onclick="deleteBin(<?php echo $bin['bin_id']; ?>)" class="action-btn delete" title="Delete">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 bg-[var(--bg)] border-t border-[var(--border)] flex justify-between items-center flex-wrap gap-2">
                <span class="text-sm text-[var(--text-light)]">Showing <span id="rowCount" class="text-[var(--text)] font-semibold"><?php echo count($bins); ?></span> bins</span>
                <span class="text-sm text-[var(--text-light)]"><i class="fas fa-clock mr-1"></i> <?php echo date('h:i A'); ?></span>
            </div>
        </div>
    </div>

    <!-- ADD MODAL -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-[var(--text)]">
                    <i class="fas fa-plus-circle text-[var(--primary)] mr-2"></i>Add New Bin
                </h2>
                <button onclick="closeModal('addModal')" class="text-[var(--text-light)] hover:text-[var(--text)] text-xl">&times;</button>
            </div>
            <form id="addForm" onsubmit="submitForm(event, 'add')">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-[var(--text-light)] block mb-1">Bin Name *</label>
                        <input type="text" name="bin_name" required class="input-field w-full px-4 py-2.5 rounded-xl">
                    </div>
                    <div>
                        <label class="text-sm text-[var(--text-light)] block mb-1">Bin Code *</label>
                        <input type="text" name="bin_code" required placeholder="BIN-XXX" class="input-field w-full px-4 py-2.5 rounded-xl">
                    </div>
                </div>
                <div class="mt-4">
                    <label class="text-sm text-[var(--text-light)] block mb-1">Location *</label>
                    <input type="text" name="location" required placeholder="e.g., Main Building - Ground Floor" class="input-field w-full px-4 py-2.5 rounded-xl">
                </div>
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="text-sm text-[var(--text-light)] block mb-1">Building</label>
                        <input type="text" name="building" placeholder="Building name" class="input-field w-full px-4 py-2.5 rounded-xl">
                    </div>
                    <div>
                        <label class="text-sm text-[var(--text-light)] block mb-1">Floor</label>
                        <input type="text" name="floor" placeholder="Floor/Level" class="input-field w-full px-4 py-2.5 rounded-xl">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="text-sm text-[var(--text-light)] block mb-1">Bin Type *</label>
                        <select name="bin_type" required class="input-field w-full px-4 py-2.5 rounded-xl">
                            <option value="biodegradable">Biodegradable</option>
                            <option value="plastic">Plastic</option>
                            <option value="metal">Metal</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-[var(--text-light)] block mb-1">Status *</label>
                        <select name="status" required class="input-field w-full px-4 py-2.5 rounded-xl">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 btn-primary py-2.5 rounded-xl font-semibold">Add Bin</button>
                    <button type="button" onclick="closeModal('addModal')" class="btn-outline px-6 py-2.5 rounded-xl">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-box">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-[var(--text)]">
                    <i class="fas fa-edit text-yellow-500 mr-2"></i>Edit Bin
                </h2>
                <button onclick="closeModal('editModal')" class="text-[var(--text-light)] hover:text-[var(--text)] text-xl">&times;</button>
            </div>
            <form id="editForm" onsubmit="submitForm(event, 'edit')">
                <input type="hidden" name="bin_id" id="edit_bin_id">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-[var(--text-light)] block mb-1">Bin Name *</label>
                        <input type="text" name="bin_name" id="edit_bin_name" required class="input-field w-full px-4 py-2.5 rounded-xl">
                    </div>
                    <div>
                        <label class="text-sm text-[var(--text-light)] block mb-1">Bin Code *</label>
                        <input type="text" name="bin_code" id="edit_bin_code" required class="input-field w-full px-4 py-2.5 rounded-xl">
                    </div>
                </div>
                <div class="mt-4">
                    <label class="text-sm text-[var(--text-light)] block mb-1">Location *</label>
                    <input type="text" name="location" id="edit_location" required class="input-field w-full px-4 py-2.5 rounded-xl">
                </div>
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="text-sm text-[var(--text-light)] block mb-1">Building</label>
                        <input type="text" name="building" id="edit_building" class="input-field w-full px-4 py-2.5 rounded-xl">
                    </div>
                    <div>
                        <label class="text-sm text-[var(--text-light)] block mb-1">Floor</label>
                        <input type="text" name="floor" id="edit_floor" class="input-field w-full px-4 py-2.5 rounded-xl">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="text-sm text-[var(--text-light)] block mb-1">Bin Type *</label>
                        <select name="bin_type" id="edit_bin_type" required class="input-field w-full px-4 py-2.5 rounded-xl">
                            <option value="biodegradable">Biodegradable</option>
                            <option value="plastic">Plastic</option>
                            <option value="metal">Metal</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-[var(--text-light)] block mb-1">Status *</label>
                        <select name="status" id="edit_status" required class="input-field w-full px-4 py-2.5 rounded-xl">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 bg-gradient-to-r from-yellow-500 to-orange-500 text-white py-2.5 rounded-xl font-semibold hover:shadow-lg transition">Update Bin</button>
                    <button type="button" onclick="closeModal('editModal')" class="btn-outline px-6 py-2.5 rounded-xl">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
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

    // Modal Controls
    function openModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = '';
    }
    
    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    // CRUD Operations
    function submitForm(e, action) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        formData.append('action', action);
        formData.append('ajax', '1');
        
        Swal.fire({
            title: 'Saving...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            background: 'var(--card-bg)',
            color: 'var(--text)',
            customClass: { popup: 'card' }
        });
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false,
                    background: 'var(--card-bg)',
                    color: 'var(--text)'
                });
                setTimeout(() => location.reload(), 1600);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message,
                    background: 'var(--card-bg)',
                    color: 'var(--text)'
                });
            }
        })
        .catch(err => {
            Swal.fire({ 
                icon: 'error', 
                title: 'Error', 
                text: 'Something went wrong', 
                background: 'var(--card-bg)', 
                color: 'var(--text)' 
            });
        });
    }

    function editBin(bin) {
        document.getElementById('edit_bin_id').value = bin.bin_id;
        document.getElementById('edit_bin_name').value = bin.bin_name;
        document.getElementById('edit_bin_code').value = bin.bin_code;
        document.getElementById('edit_location').value = bin.location;
        document.getElementById('edit_building').value = bin.building || '';
        document.getElementById('edit_floor').value = bin.floor || '';
        document.getElementById('edit_bin_type').value = bin.bin_type;
        document.getElementById('edit_status').value = bin.status;
        openModal('editModal');
    }

    function deleteBin(id) {
        Swal.fire({
            title: 'Delete Bin?',
            text: 'This action cannot be undone!',
            icon: 'warning',
            background: 'var(--card-bg)',
            color: 'var(--text)',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: 'var(--text-light)',
            confirmButtonText: '<i class="fas fa-trash"></i> Delete',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'card' }
        }).then(result => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('ajax', '1');
                formData.append('bin_id', id);
                
                Swal.fire({ 
                    title: 'Deleting...', 
                    allowOutsideClick: false, 
                    didOpen: () => Swal.showLoading(), 
                    background: 'var(--card-bg)', 
                    color: 'var(--text)',
                    customClass: { popup: 'card' }
                });
                
                fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        document.getElementById('bin-row-' + id)?.remove();
                        Swal.fire({ 
                            icon: 'success', 
                            title: 'Deleted!', 
                            text: data.message, 
                            timer: 1500, 
                            showConfirmButton: false, 
                            background: 'var(--card-bg)', 
                            color: 'var(--text)' 
                        });
                        setTimeout(() => location.reload(), 1600);
                    } else {
                        Swal.fire({ 
                            icon: 'error', 
                            title: 'Error', 
                            text: data.message, 
                            background: 'var(--card-bg)', 
                            color: 'var(--text)' 
                        });
                    }
                });
            }
        });
    }

    function selectBin(id) {
        const formData = new FormData();
        formData.append('action', 'set_active');
        formData.append('ajax', '1');
        formData.append('bin_id', id);
        
        Swal.fire({ 
            title: 'Setting active bin...', 
            allowOutsideClick: false, 
            didOpen: () => Swal.showLoading(), 
            background: 'var(--card-bg)', 
            color: 'var(--text)',
            customClass: { popup: 'card' }
        });
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                Swal.fire({ 
                    icon: 'success', 
                    title: 'Active Bin Updated', 
                    text: `Now viewing: ${data.bin.bin_name}`, 
                    timer: 2000, 
                    showConfirmButton: false, 
                    background: 'var(--card-bg)', 
                    color: 'var(--text)' 
                });
                setTimeout(() => location.reload(), 2100);
            }
        });
    }

    // Filter Table
    function filterTable() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        const type = document.getElementById('typeFilter').value;
        const rows = document.querySelectorAll('#binTable tbody tr');
        let count = 0;
        
        rows.forEach(row => {
            if (row.cells.length < 8) return;
            const text = row.textContent.toLowerCase();
            const rowStatus = row.cells[5]?.textContent.trim().toLowerCase() || '';
            const rowType = row.cells[4]?.textContent.trim().toLowerCase() || '';
            
            const match = text.includes(search) && 
                         (status === 'all' || rowStatus.includes(status)) &&
                         (type === 'all' || rowType.includes(type));
            
            row.style.display = match ? '' : 'none';
            if (match) count++;
        });
        document.getElementById('rowCount').textContent = count;
    }

    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = 'all';
        document.getElementById('typeFilter').value = 'all';
        filterTable();
    }

    // Sidebar Toggle for Mobile
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (!hamburger) {
            const header = document.querySelector('.card:first-child .flex.items-center.gap-4');
            if (header) {
                const btn = document.createElement('button');
                btn.id = 'hamburgerBtn';
                btn.className = 'md:hidden text-2xl text-[var(--text)] hover:scale-110 transition-transform';
                btn.innerHTML = '<i class="fas fa-bars"></i>';
                header.prepend(btn);
            }
        }
        
        const newHamburger = document.getElementById('hamburgerBtn');
        if (newHamburger && sidebar) {
            newHamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('open');
                if (overlay) overlay.classList.toggle('active');
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            });
        }
    });
    </script>
</body>
</html>