<?php
// =============================================
// users.php - Complete User Management (Single File)
// =============================================

// Include configuration
require_once 'config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get current user info
$currentUser = getCurrentUser();

// =============================================
// Handle Report Generation - ENHANCED VERSION
// =============================================
if (isset($_GET['action']) && $_GET['action'] === 'report') {
    try {
        // Fetch all users with additional details
        $stmt = $pdo->query("
            SELECT 
                u.user_id,
                u.username,
                u.email,
                u.full_name,
                u.role,
                u.is_active,
                u.created_at,
                u.last_login,
                u.updated_at,
                (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.user_id) as activity_count,
                (SELECT MAX(created_at) FROM activity_logs WHERE user_id = u.user_id) as last_activity
            FROM users u
            ORDER BY u.user_id DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get statistics
        $totalUsers = count($users);
        $activeUsers = count(array_filter($users, function($u) { return $u['is_active'] == 1; }));
        $inactiveUsers = $totalUsers - $activeUsers;
        
        // Get role breakdown
        $roleCounts = [];
        foreach ($users as $user) {
            $role = $user['role'] ?? 'viewer';
            if (!isset($roleCounts[$role])) {
                $roleCounts[$role] = 0;
            }
            $roleCounts[$role]++;
        }
        
        // Get login statistics
        $usersWithLogin = count(array_filter($users, function($u) { return !empty($u['last_login']); }));
        $usersNeverLogged = $totalUsers - $usersWithLogin;
        
        // Get most recent login
        $mostRecentLogin = '';
        foreach ($users as $user) {
            if (!empty($user['last_login'])) {
                if (empty($mostRecentLogin) || $user['last_login'] > $mostRecentLogin) {
                    $mostRecentLogin = $user['last_login'];
                }
            }
        }
        
        // Set Word document headers
        header('Content-Type: application/msword');
        header('Content-Disposition: attachment; filename="SmartBin_Users_Report_' . date('Y-m-d') . '.doc"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        $generatedBy = $_SESSION['full_name'] ?? 'System';
        $generatedAt = date('F d, Y h:i:s A');
        
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
                   xmlns:w="urn:schemas-microsoft-com:office:word" 
                   xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="UTF-8">
            <title>SmartBin - Users Report</title>
            <!--[if gte mso 9]>
            <xml>
                <w:WordDocument>
                    <w:View>Print</w:View>
                    <w:Zoom>100</w:Zoom>
                    <w:DoNotOptimizeForBrowser/>
                </w:WordDocument>
            </xml>
            <![endif]-->
            <style>
                /* Page Setup */
                @page {
                    size: 8.5in 11in;
                    margin: 0.6in 0.6in 0.6in 0.6in;
                    mso-page-orientation: portrait;
                }
                
                body {
                    font-family: "Segoe UI", Arial, sans-serif;
                    font-size: 10pt;
                    color: #2C3E50;
                    line-height: 1.5;
                    background: #FFFFFF;
                }
                
                /* Header Styles */
                .report-header {
                    border-bottom: 4px solid #8BC34A;
                    padding-bottom: 15px;
                    margin-bottom: 20px;
                    text-align: center;
                    background: linear-gradient(to right, #F9FBE7, #FFFFFF);
                    padding: 20px 20px 15px 20px;
                    border-radius: 8px 8px 0 0;
                }
                
                .report-header h1 {
                    font-size: 28pt;
                    color: #2E7D32;
                    margin: 0;
                    font-weight: 800;
                    letter-spacing: -0.5px;
                }
                
                .report-header .subtitle {
                    font-size: 14pt;
                    color: #558B2F;
                    margin-top: 3px;
                    font-weight: 500;
                }
                
                .report-header .meta {
                    font-size: 9pt;
                    color: #757575;
                    margin-top: 8px;
                    background: #F5F5F5;
                    padding: 6px 12px;
                    display: inline-block;
                    border-radius: 20px;
                }
                
                .report-header .meta span {
                    margin: 0 6px;
                    color: #BDBDBD;
                }
                
                /* Section Titles */
                .section-title {
                    font-size: 15pt;
                    font-weight: 700;
                    color: #2E7D32;
                    border-bottom: 3px solid #C5E1A5;
                    padding-bottom: 6px;
                    margin-top: 22px;
                    margin-bottom: 14px;
                    background: #F9FBE7;
                    padding: 8px 14px;
                    border-radius: 6px 6px 0 0;
                }
                
                .section-title-light {
                    font-size: 12pt;
                    font-weight: 600;
                    color: #388E3C;
                    margin-top: 18px;
                    margin-bottom: 10px;
                    padding: 6px 12px;
                    background: #E8F5E9;
                    border-radius: 4px;
                }
                
                /* Statistics Grid */
                .stats-grid {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 16px;
                    border-radius: 8px;
                    overflow: hidden;
                }
                
                .stats-grid td {
                    padding: 14px 16px;
                    background-color: #F5F5F5;
                    border: 1px solid #E0E0E0;
                    text-align: center;
                    font-weight: 600;
                    transition: all 0.3s ease;
                }
                
                .stats-grid td:hover {
                    background-color: #E8F5E9;
                }
                
                .stat-value {
                    font-size: 22pt;
                    color: #8BC34A;
                    display: block;
                    font-weight: 800;
                }
                
                .stat-label {
                    font-size: 9pt;
                    color: #757575;
                    font-weight: 400;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .stat-value-primary { color: #8BC34A; }
                .stat-value-success { color: #4CAF50; }
                .stat-value-danger { color: #F44336; }
                .stat-value-info { color: #2196F3; }
                .stat-value-warning { color: #FF9800; }
                
                /* Tables */
                .data-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                    font-size: 9pt;
                    border-radius: 8px;
                    overflow: hidden;
                }
                
                .data-table th {
                    background: linear-gradient(135deg, #8BC34A, #689F38);
                    color: #FFFFFF;
                    padding: 10px 12px;
                    text-align: left;
                    font-weight: 700;
                    border: 1px solid #7CB342;
                    text-transform: uppercase;
                    font-size: 8pt;
                    letter-spacing: 0.3px;
                }
                
                .data-table td {
                    padding: 8px 12px;
                    border: 1px solid #E0E0E0;
                    vertical-align: middle;
                }
                
                .data-table tr:nth-child(even) {
                    background-color: #F9FBE7;
                }
                
                .data-table tr:hover {
                    background-color: #E8F5E9;
                }
                
                /* Badges */
                .badge {
                    display: inline-block;
                    padding: 3px 12px;
                    border-radius: 12px;
                    font-size: 8pt;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                }
                
                .badge-active {
                    background: linear-gradient(135deg, #E8F5E9, #C8E6C9);
                    color: #2E7D32;
                    border: 1px solid #A5D6A7;
                }
                
                .badge-inactive {
                    background: linear-gradient(135deg, #FFEBEE, #FFCDD2);
                    color: #C62828;
                    border: 1px solid #EF9A9A;
                }
                
                .role-badge {
                    display: inline-block;
                    padding: 3px 12px;
                    border-radius: 4px;
                    font-size: 8pt;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                }
                
                .role-admin {
                    background: linear-gradient(135deg, #E3F2FD, #BBDEFB);
                    color: #0D47A1;
                }
                
                .role-operator {
                    background: linear-gradient(135deg, #FFF3E0, #FFE0B2);
                    color: #BF360C;
                }
                
                .role-viewer {
                    background: linear-gradient(135deg, #F5F5F5, #E0E0E0);
                    color: #424242;
                }
                
                /* Info Boxes */
                .info-box {
                    margin-top: 14px;
                    padding: 12px 16px;
                    background: linear-gradient(135deg, #FFF8E1, #FFECB3);
                    border-left: 5px solid #FFC107;
                    font-size: 9pt;
                    color: #616161;
                    border-radius: 6px;
                }
                
                .info-box strong {
                    color: #424242;
                }
                
                .info-box-success {
                    background: linear-gradient(135deg, #E8F5E9, #C8E6C9);
                    border-left-color: #4CAF50;
                }
                
                .notes-box {
                    margin-top: 16px;
                    padding: 14px 18px;
                    background: linear-gradient(135deg, #F5F5F5, #EEEEEE);
                    border-left: 5px solid #8BC34A;
                    font-size: 9pt;
                    color: #616161;
                    border-radius: 6px;
                }
                
                .notes-box ul {
                    margin: 6px 0 0 20px;
                    padding-left: 0;
                }
                
                .notes-box ul li {
                    margin-bottom: 4px;
                    padding-left: 4px;
                }
                
                .notes-box ul li strong {
                    color: #2E7D32;
                }
                
                /* Footer */
                .report-footer {
                    border-top: 3px solid #8BC34A;
                    padding-top: 14px;
                    margin-top: 24px;
                    text-align: center;
                    font-size: 8pt;
                    color: #9E9E9E;
                }
                
                .report-footer .footer-line {
                    margin-top: 4px;
                }
                
                .report-id {
                    font-family: "Consolas", monospace;
                    font-size: 8pt;
                    color: #BDBDBD;
                    margin-top: 6px;
                    background: #F5F5F5;
                    padding: 4px 12px;
                    display: inline-block;
                    border-radius: 12px;
                }
                
                .system-info {
                    margin-top: 14px;
                    padding: 10px 16px;
                    background: linear-gradient(135deg, #E3F2FD, #BBDEFB);
                    border-radius: 6px;
                    font-size: 8pt;
                    color: #0D47A1;
                    text-align: center;
                }
                
                .system-info strong {
                    color: #0D47A1;
                }
                
                /* Utility */
                .text-center { text-align: center; }
                .text-muted { color: #9E9E9E; }
                .text-success { color: #4CAF50; }
                .text-danger { color: #F44336; }
                .text-warning { color: #FF9800; }
                .fw-bold { font-weight: 700; }
                .mt-1 { margin-top: 4px; }
                .mb-1 { margin-bottom: 4px; }
                .border-radius { border-radius: 6px; }
            </style>
        </head>
        <body>
        
        <!-- ============================================================
        HEADER
        ============================================================ -->
        <div class="report-header">
            <h1>♻️ SmartBin</h1>
            <div class="subtitle">Users Management Report</div>
            <div class="meta">
                <strong>Generated:</strong> ' . $generatedAt . ' 
                <span>|</span> 
                <strong>By:</strong> ' . htmlspecialchars($generatedBy) . '
                <span>|</span> 
                <strong>Version:</strong> v2.0
            </div>
        </div>
        
        <!-- ============================================================
        EXECUTIVE SUMMARY
        ============================================================ -->
        <div class="section-title">📊 Executive Summary</div>
        <table class="stats-grid">
            <tr>
                <td>
                    <span class="stat-value stat-value-primary">' . $totalUsers . '</span>
                    <span class="stat-label">Total Users</span>
                </td>
                <td>
                    <span class="stat-value stat-value-success">' . $activeUsers . '</span>
                    <span class="stat-label">Active Users</span>
                </td>
                <td>
                    <span class="stat-value stat-value-danger">' . $inactiveUsers . '</span>
                    <span class="stat-label">Inactive Users</span>
                </td>
                <td>
                    <span class="stat-value stat-value-info">' . $usersWithLogin . '</span>
                    <span class="stat-label">Ever Logged In</span>
                </td>
            </tr>
        </table>
        
        <!-- ============================================================
        ROLE BREAKDOWN
        ============================================================ -->
        <div class="section-title">📊 Role Breakdown</div>
        <table class="data-table" style="width: 50%;">
            <thead>
                <tr>
                    <th>Role</th>
                    <th style="text-align: center;">Count</th>
                    <th style="text-align: center;">Percentage</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($roleCounts as $role => $count) {
            $percentage = $totalUsers > 0 ? round(($count / $totalUsers) * 100, 1) : 0;
            echo '
                <tr>
                    <td><span class="role-badge role-' . $role . '">' . ucfirst($role) . '</span></td>
                    <td style="text-align: center; font-weight: 700; font-size: 11pt;">' . $count . '</td>
                    <td style="text-align: center; font-weight: 600; color: #2E7D32;">' . $percentage . '%</td>
                </tr>';
        }
        
        echo '
            </tbody>
        </table>
        
        <!-- ============================================================
        LOGIN STATISTICS
        ============================================================ -->
        <div class="section-title">🔐 Login Statistics</div>
        <table class="stats-grid" style="width: 60%;">
            <tr>
                <td>
                    <span class="stat-value stat-value-success">' . $usersWithLogin . '</span>
                    <span class="stat-label">Users Who Logged In</span>
                </td>
                <td>
                    <span class="stat-value stat-value-warning">' . $usersNeverLogged . '</span>
                    <span class="stat-label">Never Logged In</span>
                </td>
                <td>
                    <span class="stat-value stat-value-info">' . (!empty($mostRecentLogin) ? date('M d, Y', strtotime($mostRecentLogin)) : 'N/A') . '</span>
                    <span class="stat-label">Most Recent Login</span>
                </td>
            </tr>
        </table>
        
        <!-- ============================================================
        COMPLETE USERS LIST
        ============================================================ -->
        <div class="section-title">📋 Complete Users List</div>
        <div class="info-box info-box-success">
            <strong>ℹ️ Report Information:</strong> 
            This report contains <strong style="color: #2E7D32;">' . $totalUsers . '</strong> user records. 
            Active users: <strong style="color: #4CAF50;">' . $activeUsers . '</strong> | 
            Inactive users: <strong style="color: #F44336;">' . $inactiveUsers . '</strong>
            <span style="display: block; margin-top: 4px; font-size: 8pt; color: #9E9E9E;">
                <i>Last updated: ' . date('F d, Y h:i:s A') . '</i>
            </span>
        </div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 35px; text-align: center;">#</th>
                    <th style="width: 120px;">Username</th>
                    <th style="width: 150px;">Full Name</th>
                    <th style="width: 170px;">Email</th>
                    <th style="width: 70px; text-align: center;">Role</th>
                    <th style="width: 70px; text-align: center;">Status</th>
                    <th style="width: 85px; text-align: center;">Created</th>
                    <th style="width: 85px; text-align: center;">Last Login</th>
                    <th style="width: 45px; text-align: center;">Acts</th>
                </tr>
            </thead>
            <tbody>';
        
        if (empty($users)) {
            echo '
                <tr>
                    <td colspan="9" style="text-align: center; padding: 30px; color: #757575; font-style: italic;">
                        No users found in the system.
                    </td>
                </tr>';
        } else {
            $rowNumber = 1;
            foreach ($users as $user) {
                $statusText = $user['is_active'] == 1 ? 'Active' : 'Inactive';
                $statusBadge = $user['is_active'] == 1 ? 'badge-active' : 'badge-inactive';
                $roleBadge = 'role-' . ($user['role'] ?? 'viewer');
                $createdAt = date('M d, Y', strtotime($user['created_at']));
                $lastLogin = $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : '<span style="color: #BDBDBD; font-style: italic;">Never</span>';
                $activityCount = $user['activity_count'] ?? 0;
                
                echo '
                    <tr>
                        <td style="text-align: center; font-weight: 600; color: #9E9E9E;">' . $rowNumber . '</td>
                        <td><strong>' . htmlspecialchars($user['username']) . '</strong></td>
                        <td>' . htmlspecialchars($user['full_name'] ?? '-') . '</td>
                        <td>' . htmlspecialchars($user['email'] ?? '-') . '</td>
                        <td style="text-align: center;"><span class="role-badge ' . $roleBadge . '">' . ucfirst($user['role'] ?? 'viewer') . '</span></td>
                        <td style="text-align: center;"><span class="badge ' . $statusBadge . '">' . $statusText . '</span></td>
                        <td style="text-align: center; font-size: 8pt;">' . $createdAt . '</td>
                        <td style="text-align: center; font-size: 8pt;">' . $lastLogin . '</td>
                        <td style="text-align: center; font-weight: 700; color: #2E7D32;">' . $activityCount . '</td>
                    </tr>';
                $rowNumber++;
            }
        }
        
        echo '
            </tbody>
        </table>
        
        <!-- ============================================================
        ACCOUNT STATUS SUMMARY
        ============================================================ -->
        <div class="section-title">📈 Account Status Summary</div>
        <table class="data-table" style="width: 55%;">
            <thead>
                <tr>
                    <th>Account Status</th>
                    <th style="text-align: center;">Count</th>
                    <th style="text-align: center;">Percentage</th>
                    <th style="text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge badge-active">● Active</span></td>
                    <td style="text-align: center; font-weight: 700; font-size: 11pt;">' . $activeUsers . '</td>
                    <td style="text-align: center; font-weight: 600; color: #2E7D32;">' . ($totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0) . '%</td>
                    <td style="text-align: center;"><span style="color: #4CAF50; font-weight: 700;">✓ Can access system</span></td>
                </tr>
                <tr>
                    <td><span class="badge badge-inactive">● Inactive</span></td>
                    <td style="text-align: center; font-weight: 700; font-size: 11pt;">' . $inactiveUsers . '</td>
                    <td style="text-align: center; font-weight: 600; color: #C62828;">' . ($totalUsers > 0 ? round(($inactiveUsers / $totalUsers) * 100, 1) : 0) . '%</td>
                    <td style="text-align: center;"><span style="color: #F44336; font-weight: 700;">✗ Cannot access system</span></td>
                </tr>
            </tbody>
        </table>
        
        <!-- ============================================================
        REPORT NOTES
        ============================================================ -->
        <div class="notes-box">
            <strong style="font-size: 11pt; color: #2E7D32;">📌 Report Notes:</strong>
            <ul>
                <li>This report includes all <strong>' . $totalUsers . '</strong> registered users in the SmartBin system.</li>
                <li><strong>Admin</strong> users have full access to all features, settings, and user management.</li>
                <li><strong>Operator</strong> users can manage waste logs, bin operations, and view reports.</li>
                <li><strong>Viewer</strong> users have read-only access to view data and dashboards.</li>
                <li>Inactive users cannot login to the system and are not counted in active sessions.</li>
                <li>Activity count (Acts) shows the number of actions performed by each user in the system.</li>
                <li>Last login shows the most recent login date and time for each user.</li>
            </ul>
        </div>
        
        <!-- ============================================================
        SYSTEM INFORMATION
        ============================================================ -->
        <div class="system-info">
            <strong>🖥️ System Information:</strong>
            SmartBin v2.0 · Automated Trash Bin Management System
            <span style="display: inline-block; margin-left: 10px;">
                <i>ESP32 · AI Vision · Ultrasonic · Solar-Powered</i>
            </span>
        </div>
        
        <!-- ============================================================
        FOOTER
        ============================================================ -->
        <div class="report-footer">
            <div style="font-weight: 600; color: #2E7D32; font-size: 10pt;">SmartBin v2.0 · Automated Trash Bin Management System</div>
            <div class="footer-line" style="color: #757575;">
                <span>ESP32 · AI Vision · Ultrasonic · Solar-Powered</span>
            </div>
            <div class="report-id">
                <strong>Report ID:</strong> SB-USER-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) . '
            </div>
            <div class="footer-line" style="font-size: 7pt; color: #BDBDBD; margin-top: 4px;">
                This report is auto-generated and reflects the current state of the system.
                <br>© ' . date('Y') . ' SmartBin. All rights reserved.
            </div>
        </div>
        
        </body>
        </html>';
        exit();
    } catch(PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        error_log("Report error: " . $e->getMessage());
    }
}

// =============================================
// Handle AJAX Delete Request
// =============================================
if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    header('Content-Type: application/json');
    
    try {
        $userId = (int)$_POST['user_id'];
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        
        // Prevent admin from deleting themselves
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
            exit();
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Delete from otp_logs
            $stmt = $pdo->prepare("DELETE FROM otp_logs WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete from activity_logs
            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Now delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Commit transaction
            $pdo->commit();
            
            // Log the activity
            logActivity($_SESSION['user_id'] ?? 0, 'delete_user', "Deleted user ID: $userId");
            
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            exit();
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            throw $e;
        }
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }
}

// =============================================
// Fetch Users from Database
// =============================================
try {
    $query = "SELECT * FROM users ORDER BY user_id DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    // Get statistics
    $totalUsers = count($users);
    $activeCount = 0;
    $inactiveCount = 0;
    $adminCount = 0;
    
    foreach ($users as $user) {
        $status = strtolower($user['status'] ?? $user['is_active'] ?? 'active');
        if ($status === 'active' || $status === '1') {
            $activeCount++;
        } else {
            $inactiveCount++;
        }
        
        $type = strtolower($user['user_type'] ?? $user['role'] ?? 'user');
        if ($type === 'admin') {
            $adminCount++;
        }
    }
} catch(PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
    $users = [];
    $totalUsers = 0;
    $activeCount = 0;
    $inactiveCount = 0;
    $adminCount = 0;
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 -->
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
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
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
        
        .stat-icon {
            background: rgba(139, 195, 74, 0.10);
            border: 1px solid rgba(139, 195, 74, 0.10);
        }
        
        .stat-icon.purple {
            background: rgba(76, 175, 80, 0.10);
            border-color: rgba(76, 175, 80, 0.10);
        }
        
        .stat-icon.green {
            background: rgba(139, 195, 74, 0.12);
            border-color: rgba(139, 195, 74, 0.12);
        }
        
        .stat-icon.red {
            background: rgba(239, 68, 68, 0.10);
            border-color: rgba(239, 68, 68, 0.10);
        }
        
        .search-input {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            transition: all 0.3s ease;
        }
        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 195, 74, 0.12);
            outline: none;
        }
        .search-input::placeholder { color: var(--text-light); }
        
        .filter-select {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            transition: all 0.3s ease;
        }
        .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 195, 74, 0.12);
            outline: none;
        }
        .filter-select option {
            background: var(--card-bg);
            color: var(--text);
        }
        
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
            letter-spacing: 0.02em;
        }
        
        .badge-active {
            background: rgba(139, 195, 74, 0.12);
            color: #4CAF50;
            border: 1px solid rgba(139, 195, 74, 0.15);
        }
        
        .badge-inactive {
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.15);
        }
        
        .badge-pending {
            background: rgba(234, 179, 8, 0.12);
            color: #eab308;
            border: 1px solid rgba(234, 179, 8, 0.15);
        }
        
        .type-badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .type-admin {
            background: rgba(76, 175, 80, 0.12);
            color: #4CAF50;
        }
        
        .type-user {
            background: rgba(139, 195, 74, 0.12);
            color: #8BC34A;
        }
        
        .user-avatar {
            background: linear-gradient(135deg, var(--primary), var(--accent));
        }
        .user-avatar-admin {
            background: linear-gradient(135deg, #4CAF50, #388E3C);
        }
        .user-avatar-user {
            background: linear-gradient(135deg, #8BC34A, #66BB6A);
        }
        
        .action-btn {
            transition: all 0.2s ease;
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            text-decoration: none;
        }
        
        .action-btn:hover { transform: scale(1.1); }
        
        .action-btn.view {
            color: var(--primary);
            background: rgba(139, 195, 74, 0.08);
        }
        .action-btn.view:hover {
            background: rgba(139, 195, 74, 0.15);
        }
        
        .action-btn.edit {
            color: #eab308;
            background: rgba(234, 179, 8, 0.08);
        }
        .action-btn.edit:hover {
            background: rgba(234, 179, 8, 0.15);
        }
        
        .action-btn.delete {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.08);
        }
        .action-btn.delete:hover {
            background: rgba(239, 68, 68, 0.15);
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .scrollbar-thin::-webkit-scrollbar-track {
            background: var(--border);
            border-radius: 10px;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: var(--accent);
        }
        
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
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }
        .pulse-dot {
            animation: pulse-dot 2s ease-in-out infinite;
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
    </style>
</head>
<body>
    
    <!-- SIDEBAR OVERLAY -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- SIDEBAR -->
    <nav class="sidebar fixed top-0 left-0 h-full w-[270px] p-6 flex flex-col shadow-xl overflow-y-auto" id="sidebar">
        <!-- Brand -->
        <div class="flex items-center gap-3 pb-5 mb-5 border-b border-[var(--border)]">
            <div class="w-10 h-10 rounded-xl bg-[var(--primary)]/20 flex items-center justify-center">
                <i class="fas fa-trash-alt text-[var(--primary)] text-xl"></i>
            </div>
            <div>
                <a href="dashboard.php" class="text-xl font-bold tracking-tight hover:opacity-80 transition">
                    CT-<span class="text-[var(--primary)]">ABS</span>
                </a>
                <span class="block text-[10px] uppercase tracking-wider text-[var(--text-light)] font-medium">Automated Bin Segragation</span>
            </div>
        </div>
        
        
        <!-- Navigation -->
        <div class="text-[10px] uppercase tracking-wider text-[var(--text-light)]/60 font-semibold mt-1 mb-3 px-3">Main</div>
        <ul class="flex-1 space-y-0.5">
            <li>
                <a href="index.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-chart-pie w-5 text-[var(--text-light)]"></i> 
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="active">
                <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-all duration-200">
                    <i class="fas fa-users w-5 text-[var(--text-light)]"></i> 
                    <span class="font-medium">User Management</span>
                </a>
            </li>
            <li>
                <a href="binloc.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-microchip w-5 text-[var(--text-light)]"></i> 
                    <span>Trash Bin List</span>
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[var(--text-light)] hover:bg-[var(--hover-bg)] hover:text-[var(--primary)] transition-all duration-200">
                    <i class="fas fa-database w-5 text-[var(--text-light)]"></i> 
                    <span>Waste Log</span>
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
        
        <!-- Header -->
        <div class="card p-6 mb-6 flex justify-between items-center flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-[var(--primary)] hover:text-[var(--accent)] transition flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i>
                    <span class="hidden sm:inline">Back to Dashboard</span>
                </a>
                <div class="h-6 w-px bg-[var(--border)] hidden sm:block"></div>
                <h1 class="text-2xl font-bold text-[var(--text)] flex items-center gap-3">
                    <i class="fas fa-users text-[var(--primary)]"></i>
                    <span class="gradient-text">User Management</span>
                </h1>
            </div>
            
            <div class="flex gap-3 items-center flex-wrap">
                <!-- Theme Toggle -->
                <button class="theme-toggle px-3 py-2 rounded-xl text-sm font-medium flex items-center gap-2" id="themeToggle">
                    <i class="fas fa-moon" id="themeIcon"></i>
                    <span id="themeLabel" class="hidden sm:inline">Dark</span>
                </button>
                
                <!-- User Info -->
                <div class="flex items-center gap-3 bg-[var(--card-bg)] px-4 py-2 rounded-xl border border-[var(--border)] hover:border-[var(--primary)] transition">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[var(--primary)] to-[var(--accent)] flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-[var(--primary)]/20">
                        <?php 
                        $userInitials = '';
                        if ($currentUser) {
                            $name = $currentUser['full_name'] ?? $currentUser['username'] ?? 'User';
                            $nameParts = explode(' ', $name);
                            $userInitials = strtoupper(substr($nameParts[0], 0, 1));
                            if (isset($nameParts[1])) {
                                $userInitials .= strtoupper(substr($nameParts[1], 0, 1));
                            }
                        } else {
                            $userInitials = 'U';
                        }
                        echo $userInitials;
                        ?>
                    </div>
                    <div class="hidden sm:block">
                        <div class="text-sm font-semibold text-[var(--text)]">
                            <?php echo htmlspecialchars($currentUser['full_name'] ?? $currentUser['username'] ?? 'User'); ?>
                        </div>
                        <div class="text-xs text-[var(--text-light)]">
                            <i class="fas fa-user-tag mr-1"></i>
                            <?php echo ucfirst($currentUser['role'] ?? $currentUser['user_type'] ?? 'User'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="h-6 w-px bg-[var(--border)] hidden sm:block"></div>
                
                <span class="text-[var(--text-light)] text-sm flex items-center gap-2 hidden md:flex">
                    <i class="far fa-calendar-alt"></i>
                    <?php echo date('F d, Y'); ?>
                </span>
                
                <!-- Report Button -->
                <a href="?action=report" target="_blank" class="btn-report px-4 py-2 rounded-xl text-sm flex items-center gap-2 shadow-sm">
                    <i class="fas fa-file-word"></i>
                    <span class="hidden sm:inline">Export Report</span>
                </a>
                
                <a href="adduser.php" class="btn-primary px-4 py-2 rounded-xl text-sm flex items-center gap-2 shadow-sm">
                    <i class="fas fa-user-plus"></i>
                    <span class="hidden sm:inline">Add User</span>
                </a>
                
                <a href="logout.php" class="text-red-500 hover:text-red-600 transition text-sm flex items-center gap-1 bg-red-500/10 px-3 py-2 rounded-xl border border-red-500/20 hover:border-red-500/40">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="hidden sm:inline">Logout</span>
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card p-5">
                <div class="flex items-center gap-4">
                    <div class="stat-icon w-12 h-12 rounded-xl flex items-center justify-center text-[var(--primary)]">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-[var(--text)]"><?php echo $totalUsers; ?></h3>
                        <p class="text-[var(--text-light)] text-sm">Total Users</p>
                    </div>
                </div>
            </div>
            <div class="stat-card p-5">
                <div class="flex items-center gap-4">
                    <div class="stat-icon green w-12 h-12 rounded-xl flex items-center justify-center text-[var(--primary)]">
                        <i class="fas fa-user-check text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-[var(--text)]"><?php echo $activeCount; ?></h3>
                        <p class="text-[var(--text-light)] text-sm">Active</p>
                    </div>
                </div>
            </div>
            <div class="stat-card p-5">
                <div class="flex items-center gap-4">
                    <div class="stat-icon red w-12 h-12 rounded-xl flex items-center justify-center text-red-500">
                        <i class="fas fa-user-times text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-[var(--text)]"><?php echo $inactiveCount; ?></h3>
                        <p class="text-[var(--text-light)] text-sm">Inactive</p>
                    </div>
                </div>
            </div>
            <div class="stat-card p-5">
                <div class="flex items-center gap-4">
                    <div class="stat-icon purple w-12 h-12 rounded-xl flex items-center justify-center text-[var(--accent)]">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-[var(--text)]"><?php echo $adminCount; ?></h3>
                        <p class="text-[var(--text-light)] text-sm">Admins</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card p-4 mb-6 flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px] relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-light)]"></i>
                <input type="text" id="searchInput" placeholder="Search users by name, email..." onkeyup="filterTable()" 
                       class="search-input w-full pl-10 pr-4 py-2.5 rounded-xl focus:outline-none">
            </div>
            <select id="statusFilter" onchange="filterTable()" class="filter-select px-4 py-2.5 rounded-xl focus:outline-none">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="pending">Pending</option>
            </select>
            <select id="typeFilter" onchange="filterTable()" class="filter-select px-4 py-2.5 rounded-xl focus:outline-none">
                <option value="all">All Types</option>
                <option value="admin">Admin</option>
                <option value="user">User</option>
            </select>
            <button onclick="resetFilters()" class="btn-outline px-4 py-2.5 rounded-xl text-sm flex items-center gap-2">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto scrollbar-thin">
                <table class="w-full" id="userTable">
                    <thead>
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-medium">User</th>
                            <th class="px-6 py-4 text-left text-xs font-medium">Email</th>
                            <th class="px-6 py-4 text-left text-xs font-medium hidden md:table-cell">Full Name</th>
                            <th class="px-6 py-4 text-left text-xs font-medium">Type</th>
                            <th class="px-6 py-4 text-left text-xs font-medium">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-medium hidden lg:table-cell">Created</th>
                            <th class="px-6 py-4 text-left text-xs font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border)]">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center text-[var(--text-light)]">
                                    <i class="fas fa-users-slash text-5xl text-[var(--border)] mb-4 block"></i>
                                    <p class="text-lg font-medium">No users found</p>
                                    <p class="text-sm mt-1">Start by adding your first user</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): 
                                $userId = $user['user_id'] ?? $user['id'] ?? 0;
                                $username = $user['username'] ?? 'N/A';
                                $email = $user['email'] ?? 'N/A';
                                $fullName = $user['full_name'] ?? ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
                                $fullName = trim($fullName) ?: 'N/A';
                                
                                $status = strtolower($user['status'] ?? $user['is_active'] ?? 'active');
                                if ($status === '1') $status = 'active';
                                if ($status === '0') $status = 'inactive';
                                
                                $userType = strtolower($user['user_type'] ?? $user['role'] ?? 'user');
                                $createdAt = $user['created_at'] ?? $user['created_date'] ?? null;
                                
                                // Get initials
                                $initials = 'U';
                                if ($fullName !== 'N/A') {
                                    $nameParts = explode(' ', $fullName);
                                    $initials = strtoupper(substr($nameParts[0], 0, 1));
                                    if (isset($nameParts[1])) $initials .= strtoupper(substr($nameParts[1], 0, 1));
                                } else {
                                    $initials = strtoupper(substr($username, 0, 2));
                                }
                                
                                $avatarClass = $userType === 'admin' ? 'user-avatar-admin' : 'user-avatar-user';
                                $statusClass = $status === 'active' ? 'badge-active' : ($status === 'pending' ? 'badge-pending' : 'badge-inactive');
                                $typeClass = $userType === 'admin' ? 'type-admin' : 'type-user';
                                $typeIcon = $userType === 'admin' ? 'fa-shield' : 'fa-user';
                            ?>
                            <tr id="user-row-<?php echo $userId; ?>" data-user-id="<?php echo $userId; ?>" class="table-row-hover">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full <?php echo $avatarClass; ?> flex items-center justify-center text-white font-bold text-sm shadow-lg">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-[var(--text)]"><?php echo htmlspecialchars($username); ?></div>
                                            <div class="text-xs text-[var(--text-light)]">ID: #<?php echo $userId; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="mailto:<?php echo htmlspecialchars($email); ?>" class="text-[var(--primary)] hover:text-[var(--accent)] transition hover:underline">
                                        <?php echo htmlspecialchars($email); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 hidden md:table-cell text-[var(--text)]"><?php echo htmlspecialchars($fullName); ?></td>
                                <td class="px-6 py-4">
                                    <span class="type-badge <?php echo $typeClass; ?>">
                                        <i class="fas <?php echo $typeIcon; ?> mr-1"></i>
                                        <?php echo ucfirst($userType); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="badge-status <?php echo $statusClass; ?>">
                                        <i class="fas fa-circle text-[6px] mr-1.5 align-middle"></i>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 hidden lg:table-cell text-[var(--text-light)] text-sm">
                                    <?php echo $createdAt ? date('M d, Y', strtotime($createdAt)) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-1.5">
                                        <a href="view-user.php?id=<?php echo $userId; ?>" class="action-btn view" title="View">
                                            <i class="fas fa-eye text-sm"></i>
                                        </a>
                                        <a href="edit-user.php?id=<?php echo $userId; ?>" class="action-btn edit" title="Edit">
                                            <i class="fas fa-edit text-sm"></i>
                                        </a>
                                        <button onclick="deleteUser(<?php echo $userId; ?>, '<?php echo htmlspecialchars($username); ?>')" class="action-btn delete" title="Delete">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 bg-[var(--bg)] border-t border-[var(--border)] flex justify-between items-center flex-wrap gap-2">
                <span class="text-sm flex items-center gap-2">
                    <i class="fas fa-info-circle text-[var(--text-light)]"></i>
                    Showing <span id="userCount" class="text-[var(--text)] font-semibold"><?php echo count($users); ?></span> users
                </span>
                <span class="text-sm flex items-center gap-2">
                    <i class="fas fa-clock text-[var(--text-light)]"></i>
                    Updated: <?php echo date('h:i A'); ?>
                </span>
            </div>
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
        
        // Filter table
        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('statusFilter').value.toLowerCase();
            const type = document.getElementById('typeFilter').value.toLowerCase();
            const rows = document.querySelectorAll('#userTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                if (row.cells.length === 0) return;
                const text = row.textContent.toLowerCase();
                const rowStatus = row.querySelector('td:nth-child(5)')?.textContent.toLowerCase().trim() || '';
                const rowType = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase().trim() || '';
                
                const matchSearch = text.includes(search);
                const matchStatus = status === 'all' || rowStatus.includes(status);
                const matchType = type === 'all' || rowType.includes(type);
                
                if (matchSearch && matchStatus && matchType) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            document.getElementById('userCount').textContent = visibleCount;
        }

        // Reset filters
        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('typeFilter').value = 'all';
            filterTable();
        }

        // Delete user function with SweetAlert2
        function deleteUser(userId, username) {
            Swal.fire({
                title: 'Delete User?',
                html: `Are you sure you want to delete <strong class="text-[var(--text)]">"${username}"</strong>?<br><span style="color: #ef4444; font-size: 14px;">This action cannot be undone!</span>`,
                icon: 'warning',
                background: 'var(--card-bg)',
                color: 'var(--text)',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: 'var(--text-light)',
                confirmButtonText: '<i class="fas fa-trash"></i> Yes, delete!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                iconColor: '#ef4444',
                customClass: {
                    popup: 'card'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Deleting...',
                        html: 'Please wait while we delete the user.',
                        allowOutsideClick: false,
                        background: 'var(--card-bg)',
                        color: 'var(--text)',
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `action=delete_user&user_id=${userId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const row = document.getElementById(`user-row-${userId}`);
                            if (row) {
                                row.style.transition = 'all 0.3s ease';
                                row.style.opacity = '0';
                                row.style.transform = 'scale(0.95)';
                                setTimeout(() => {
                                    row.remove();
                                    const count = document.querySelectorAll('#userTable tbody tr:not([style*="display: none"])').length;
                                    document.getElementById('userCount').textContent = count;
                                    setTimeout(() => location.reload(), 1500);
                                }, 300);
                            }
                            
                            Swal.fire({
                                title: 'Deleted!',
                                text: data.message || 'User has been deleted successfully.',
                                icon: 'success',
                                timer: 2000,
                                timerProgressBar: true,
                                background: 'var(--card-bg)',
                                color: 'var(--text)',
                                confirmButtonColor: 'var(--primary)'
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: data.message || 'Failed to delete user.',
                                icon: 'error',
                                background: 'var(--card-bg)',
                                color: 'var(--text)',
                                confirmButtonColor: 'var(--primary)'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error!',
                            text: 'An error occurred: ' + error,
                            icon: 'error',
                            background: 'var(--card-bg)',
                            color: 'var(--text)',
                            confirmButtonColor: 'var(--primary)'
                        });
                    });
                }
            });
        }

        // Enter key support for search
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function(e) {
                    if (e.key === 'Enter') {
                        filterTable();
                    }
                });
            }
            
            // Sidebar toggle for mobile
            const hamburger = document.getElementById('hamburgerBtn');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            // Add hamburger button for mobile if it doesn't exist
            if (!hamburger) {
                const topBar = document.querySelector('.card:first-child .flex.items-center.gap-4');
                if (topBar) {
                    const btn = document.createElement('button');
                    btn.id = 'hamburgerBtn';
                    btn.className = 'md:hidden text-2xl text-[var(--text)] hover:scale-110 transition-transform';
                    btn.innerHTML = '<i class="fas fa-bars"></i>';
                    topBar.prepend(btn);
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