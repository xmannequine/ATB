<?php
// =============================================
// userlogs.php - User Activity Audit Log
// =============================================

session_start();
require_once 'config/config.php';

if (!isLoggedIn()) {
    setFlash('error', 'Please login to access user logs.');
    redirectToLogin();
}

$currentUser = getCurrentUser();
if (!$currentUser || ($currentUser['role'] ?? '') !== 'admin') {
    setFlash('error', 'Only administrators can access user logs.');
    redirectToDashboard();
}

$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$dateFrom = trim($_GET['from'] ?? '');
$dateTo = trim($_GET['to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where = ['1 = 1'];
$params = [];

if ($search !== '') {
    $where[] = '(u.username LIKE :search OR u.full_name LIKE :search OR al.details LIKE :search OR al.ip_address LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($actionFilter !== '') {
    $where[] = 'al.action = :action';
    $params[':action'] = $actionFilter;
}
if ($dateFrom !== '') {
    $where[] = 'DATE(al.created_at) >= :date_from';
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(al.created_at) <= :date_to';
    $params[':date_to'] = $dateTo;
}

$whereSql = implode(' AND ', $where);

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON u.user_id = al.user_id WHERE $whereSql");
    $stmt->execute($params);
    $totalRows = (int)$stmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("SELECT al.id, al.user_id, al.action, al.details, al.ip_address, al.user_agent, al.created_at,
            COALESCE(u.full_name, 'System / Deleted User') AS full_name, u.username
        FROM activity_logs al
        LEFT JOIN users u ON u.user_id = al.user_id
        WHERE $whereSql
        ORDER BY al.created_at DESC, al.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $actionStmt = $pdo->query("SELECT DISTINCT action FROM activity_logs WHERE action <> '' ORDER BY action");
    $actions = $actionStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('User log query error: ' . $e->getMessage());
    $logs = [];
    $actions = [];
    $totalRows = 0;
    $totalPages = 1;
    $page = 1;
}

function userLogUrl($pageNumber) {
    $query = $_GET;
    $query['page'] = $pageNumber;
    return 'userlogs.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Logs - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary:#8BC34A; --accent:#4CAF50; --bg:#F5F5F5; --text:#424242; --text-light:#757575; --border:#E0E0E0; --card-bg:#FFF; }
        [data-theme="dark"] { --bg:#1a1a1a; --text:#E8E8E8; --text-light:#B0B0B0; --border:#404040; --card-bg:#2d2d2d; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:'Inter', sans-serif; background:var(--bg); color:var(--text); }
        .card { background:var(--card-bg); border:1px solid var(--border); border-radius:16px; }
        .input { background:var(--card-bg); color:var(--text); border:1px solid var(--border); }
        .input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(139,195,74,.15); }
        .action-badge { background:rgba(139,195,74,.12); color:var(--accent); }
        .theme-toggle { background:var(--card-bg); color:var(--text); border:1px solid var(--border); }
        @media (max-width: 768px) { .log-table { min-width:900px; } }
    </style>
</head>
<body>
    <main class="max-w-7xl mx-auto p-4 md:p-8">
        <header class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <a href="index.php" class="text-[var(--text-light)] hover:text-[var(--primary)]" title="Back to dashboard"><i class="fas fa-arrow-left text-lg"></i></a>
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold">User Activity Logs</h1>
                    <p class="text-sm text-[var(--text-light)] mt-1">Authentication and account activity audit trail</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-[var(--text-light)]"><i class="fas fa-database mr-1"></i><?php echo number_format($totalRows); ?> records</span>
                <button class="theme-toggle px-3 py-2 rounded-xl text-sm" id="themeToggle" type="button" title="Toggle theme"><i class="fas fa-moon" id="themeIcon"></i></button>
            </div>
        </header>

        <form method="GET" class="card p-4 mb-5 grid grid-cols-1 md:grid-cols-5 gap-3">
            <input class="input px-3 py-2.5 rounded-xl text-sm" type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search user, details, IP">
            <select class="input px-3 py-2.5 rounded-xl text-sm" name="action">
                <option value="">All actions</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $actionFilter === $action ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $action))); ?></option>
                <?php endforeach; ?>
            </select>
            <input class="input px-3 py-2.5 rounded-xl text-sm" type="date" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>" aria-label="From date">
            <input class="input px-3 py-2.5 rounded-xl text-sm" type="date" name="to" value="<?php echo htmlspecialchars($dateTo); ?>" aria-label="To date">
            <div class="flex gap-2">
                <button class="flex-1 px-4 py-2.5 rounded-xl bg-[var(--primary)] text-white text-sm font-semibold hover:bg-[var(--accent)]" type="submit"><i class="fas fa-filter mr-1.5"></i>Filter</button>
                <a class="px-4 py-2.5 rounded-xl border border-[var(--border)] text-sm flex items-center" href="userlogs.php" title="Clear filters"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>

        <section class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="log-table w-full text-left text-sm">
                    <thead class="border-b border-[var(--border)] text-xs uppercase tracking-wider text-[var(--text-light)]">
                        <tr><th class="px-5 py-4">Date and time</th><th class="px-5 py-4">User</th><th class="px-5 py-4">Action</th><th class="px-5 py-4">Details</th><th class="px-5 py-4">IP address</th><th class="px-5 py-4">Browser</th></tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border)]">
                    <?php if (!$logs): ?>
                        <tr><td colspan="6" class="px-5 py-12 text-center text-[var(--text-light)]"><i class="fas fa-clipboard-list text-3xl mb-3 block opacity-40"></i>No activity records found.</td></tr>
                    <?php else: foreach ($logs as $log): ?>
                        <tr class="hover:bg-black/[.03] dark:hover:bg-white/[.03] align-top">
                            <td class="px-5 py-4 whitespace-nowrap text-[var(--text-light)]"><?php echo htmlspecialchars(date('M d, Y H:i:s', strtotime($log['created_at']))); ?></td>
                            <td class="px-5 py-4"><strong><?php echo htmlspecialchars($log['full_name']); ?></strong><span class="block text-xs text-[var(--text-light)]"><?php echo htmlspecialchars($log['username'] ?? ''); ?></span></td>
                            <td class="px-5 py-4"><span class="action-badge px-2.5 py-1 rounded-full text-xs font-semibold"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))); ?></span></td>
                            <td class="px-5 py-4 max-w-xs break-words"><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                            <td class="px-5 py-4 text-[var(--text-light)]"><?php echo htmlspecialchars($log['ip_address'] ?: 'Unknown'); ?></td>
                            <td class="px-5 py-4 max-w-xs break-words text-xs text-[var(--text-light)]"><?php echo htmlspecialchars($log['user_agent'] ?: 'Unknown'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($totalPages > 1): ?>
        <nav class="flex items-center justify-between mt-5 text-sm" aria-label="Pagination">
            <span class="text-[var(--text-light)]">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?><a class="px-3 py-2 rounded-lg border border-[var(--border)]" href="<?php echo htmlspecialchars(userLogUrl($page - 1)); ?>">Previous</a><?php endif; ?>
                <?php if ($page < $totalPages): ?><a class="px-3 py-2 rounded-lg bg-[var(--primary)] text-white" href="<?php echo htmlspecialchars(userLogUrl($page + 1)); ?>">Next</a><?php endif; ?>
            </div>
        </nav>
        <?php endif; ?>
    </main>
    <script>
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const theme = localStorage.getItem('smartbin-theme') || 'light';
        document.documentElement.setAttribute('data-theme', theme);
        themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        themeToggle.addEventListener('click', function() {
            const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('smartbin-theme', next);
            themeIcon.className = next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        });
    </script>
</body>
</html>
