<?php
// =============================================
// wastelog.php - Waste Logging Database Viewer
// =============================================

require_once 'config/config.php';
session_start();

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get flash messages (if any)
$flash = getFlash();

// =============================================
// Database connection
// =============================================
$db = $pdo;

// =============================================
// Filter parameters
// =============================================
$wasteType = isset($_GET['type']) ? trim($_GET['type']) : '';
$binFilter = isset($_GET['bin']) ? (int)$_GET['bin'] : 0;
$dateFrom  = isset($_GET['from']) ? $_GET['from'] : '';
$dateTo    = isset($_GET['to']) ? $_GET['to'] : '';
$page      = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

// =============================================
// Build query with filters
// =============================================
$sql = "SELECT * FROM waste_logs WHERE 1=1";
$params = [];

if (!empty($wasteType)) {
    $sql .= " AND waste_type = :type";
    $params[':type'] = $wasteType;
}
if ($binFilter > 0 && $binFilter <= 4) {
    $sql .= " AND bin_number = :bin";
    $params[':bin'] = $binFilter;
}
if (!empty($dateFrom)) {
    $sql .= " AND DATE(created_at) >= :from";
    $params[':from'] = $dateFrom;
}
if (!empty($dateTo)) {
    $sql .= " AND DATE(created_at) <= :to";
    $params[':to'] = $dateTo;
}

// Count total rows for pagination
$countSql = str_replace("SELECT *", "SELECT COUNT(*)", $sql);
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$totalRows = $stmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Fetch paginated rows
$sql .= " ORDER BY created_at DESC LIMIT :offset, :perPage";
$params[':offset'] = $offset;
$params[':perPage'] = $perPage;

$stmt = $db->prepare($sql);
foreach ($params as $key => &$val) {
    if ($key == ':offset' || $key == ':perPage') {
        $stmt->bindParam($key, $val, PDO::PARAM_INT);
    } else {
        $stmt->bindParam($key, $val);
    }
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// Waste type labels & colors
// =============================================
$typeLabels = [
    'biodegradable' => ['label' => 'Biodegradable', 'color' => 'text-green-600 bg-green-100 border-green-200'],
    'plastic'       => ['label' => 'Plastic',       'color' => 'text-blue-600 bg-blue-100 border-blue-200'],
    'metal'         => ['label' => 'Metal',         'color' => 'text-yellow-600 bg-yellow-100 border-yellow-200'],
    'other'         => ['label' => 'Other',         'color' => 'text-gray-600 bg-gray-100 border-gray-200'],
];

$binColors = ['', 'bg-indigo-100 text-indigo-800', 'bg-purple-100 text-purple-800', 'bg-pink-100 text-pink-800', 'bg-teal-100 text-teal-800'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waste Logs - SmartBin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* Reuse same theme variables as landing page */
        :root {
            --primary: #8BC34A;
            --accent: #4CAF50;
            --bg: #F5F5F5;
            --text: #424242;
            --text-light: #757575;
            --border: #E0E0E0;
            --card-bg: #FFFFFF;
            --shadow: rgba(66, 66, 66, 0.08);
        }
        [data-theme="dark"] {
            --bg: #1a1a1a;
            --card-bg: #2d2d2d;
            --text: #E8E8E8;
            --text-light: #B0B0B0;
            --border: #404040;
            --shadow: rgba(0, 0, 0, 0.3);
        }
        * { font-family: 'Inter', sans-serif; }
        body {
            background: var(--bg);
            color: var(--text);
            transition: background 0.3s;
            min-height: 100vh;
        }
        .glass-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            box-shadow: 0 8px 32px var(--shadow);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            transition: all 0.3s ease;
            padding: 0.5rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(139, 195, 74, 0.25);
        }
        .btn-secondary {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 0.5rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-secondary:hover {
            border-color: var(--primary);
            background: rgba(139, 195, 74, 0.08);
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid transparent;
            display: inline-block;
        }
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 0.5rem 0.8rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .theme-toggle:hover {
            border-color: var(--primary);
            background: rgba(139, 195, 74, 0.08);
        }
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        th {
            background: var(--bg);
            color: var(--text-light);
            font-weight: 600;
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 2px solid var(--border);
        }
        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            color: var(--text);
        }
        tr:hover td {
            background: rgba(139, 195, 74, 0.04);
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            margin: 0 0.2rem;
            border: 1px solid var(--border);
            color: var(--text);
            transition: all 0.2s;
            font-size: 0.8rem;
        }
        .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .pagination .active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        @media (max-width: 640px) {
            .filter-form {
                flex-direction: column;
                gap: 0.5rem;
            }
            .filter-form > div {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Theme Toggle -->
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon" id="themeIcon"></i>
        <span id="themeLabel">Dark</span>
    </button>

    <div class="container mx-auto px-4 py-6 max-w-7xl">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-[var(--text)]">
                    <i class="fas fa-history text-[var(--primary)] mr-2"></i>Waste Logs
                </h1>
                <p class="text-sm text-[var(--text-light)]">View and filter waste disposal records</p>
            </div>
            <div class="flex gap-3">
                <a href="index.php" class="btn-primary">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
                <a href="export_logs.php" class="btn-secondary">
                    <i class="fas fa-download"></i> Export
                </a>
            </div>
        </div>

        <?php if (!empty($flash['message'])): ?>
            <div class="mb-4 p-4 rounded-lg <?php echo ($flash['type'] == 'success') ? 'bg-green-100 border border-green-300 text-green-800' : 'bg-red-100 border border-red-300 text-red-800'; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="glass-card rounded-xl p-4 mb-6">
            <form method="GET" class="filter-form flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-medium text-[var(--text-light)] mb-1">Waste Type</label>
                    <select name="type" class="rounded-lg border border-[var(--border)] bg-[var(--bg)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)]">
                        <option value="">All Types</option>
                        <option value="biodegradable" <?php echo $wasteType == 'biodegradable' ? 'selected' : ''; ?>>Biodegradable</option>
                        <option value="plastic" <?php echo $wasteType == 'plastic' ? 'selected' : ''; ?>>Plastic</option>
                        <option value="metal" <?php echo $wasteType == 'metal' ? 'selected' : ''; ?>>Metal</option>
                        <option value="other" <?php echo $wasteType == 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-[var(--text-light)] mb-1">Bin</label>
                    <select name="bin" class="rounded-lg border border-[var(--border)] bg-[var(--bg)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)]">
                        <option value="0">All Bins</option>
                        <option value="1" <?php echo $binFilter == 1 ? 'selected' : ''; ?>>Bin 1</option>
                        <option value="2" <?php echo $binFilter == 2 ? 'selected' : ''; ?>>Bin 2</option>
                        <option value="3" <?php echo $binFilter == 3 ? 'selected' : ''; ?>>Bin 3</option>
                        <option value="4" <?php echo $binFilter == 4 ? 'selected' : ''; ?>>Bin 4</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-[var(--text-light)] mb-1">Date From</label>
                    <input type="date" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="rounded-lg border border-[var(--border)] bg-[var(--bg)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-[var(--text-light)] mb-1">Date To</label>
                    <input type="date" name="to" value="<?php echo htmlspecialchars($dateTo); ?>" class="rounded-lg border border-[var(--border)] bg-[var(--bg)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)]">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="wastelog.php" class="btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Log Table -->
        <div class="glass-card rounded-xl overflow-hidden">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Timestamp</th>
                            <th>Waste Type</th>
                            <th>Bin</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-8 text-[var(--text-light)]">
                                    <i class="fas fa-inbox text-3xl block mb-2"></i>
                                    No waste logs found matching your criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = $offset + 1; ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                        $typeKey = strtolower($log['waste_type']);
                                        $info = $typeLabels[$typeKey] ?? ['label' => ucfirst($typeKey), 'color' => 'text-gray-600 bg-gray-100 border-gray-200'];
                                        ?>
                                        <span class="badge <?php echo $info['color']; ?>">
                                            <?php echo htmlspecialchars($info['label']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $binColors[$log['bin_number']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                            Bin <?php echo $log['bin_number']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $details = [];
                                        if (!empty($log['sensor_data'])) {
                                            $details[] = 'Sensor: ' . htmlspecialchars($log['sensor_data']);
                                        }
                                        if (!empty($log['notes'])) {
                                            $details[] = htmlspecialchars($log['notes']);
                                        }
                                        echo implode(' · ', $details) ?: '—';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination px-4 py-3 border-t border-[var(--border)] flex flex-wrap justify-between items-center text-sm">
                    <span class="text-[var(--text-light)]">Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></span>
                    <div>
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Prev</a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Theme Toggle Script -->
    <script>
        const toggle = document.getElementById('themeToggle');
        const icon = document.getElementById('themeIcon');
        const label = document.getElementById('themeLabel');
        const saved = localStorage.getItem('smartbin-theme') || 'light';
        document.documentElement.setAttribute('data-theme', saved);
        updateUI(saved);
        toggle.addEventListener('click', function() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('smartbin-theme', next);
            updateUI(next);
        });
        function updateUI(theme) {
            if (theme === 'dark') {
                icon.className = 'fas fa-sun';
                label.textContent = 'Light';
            } else {
                icon.className = 'fas fa-moon';
                label.textContent = 'Dark';
            }
        }
    </script>
</body>
</html>