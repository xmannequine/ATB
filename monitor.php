<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('smartbin_session');
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
    }
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$monitorPassword = getenv('MONITOR_PASSWORD');
if ($monitorPassword === false || $monitorPassword === '') {
    $monitorPassword = $_ENV['MONITOR_PASSWORD'] ?? $_SERVER['MONITOR_PASSWORD'] ?? 'atb2026';
}
$submittedKey = (string)($_POST['key'] ?? $_GET['key'] ?? '');
$isAuthorized = !empty($_SESSION['user_id']) || !empty($_SESSION['monitor_authorized']);

if ($monitorPassword !== '' && $submittedKey !== '' && hash_equals($monitorPassword, $submittedKey)) {
    $_SESSION['monitor_authorized'] = true;
    $isAuthorized = true;
}

if (!$isAuthorized) {
    $hasAttempt = $_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['key']);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Monitor Access - SmartBin</title>
        <style>
            :root { color-scheme: light; font-family: Arial, sans-serif; }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #eef5ef; color: #17321f; }
            main { width: min(100%, 420px); padding: 32px; background: #fff; border: 1px solid #d4e2d6; border-radius: 16px; box-shadow: 0 18px 50px rgba(20, 61, 31, .12); }
            h1 { margin: 0 0 8px; font-size: 28px; }
            p { color: #5b6d60; }
            label { display: block; margin: 24px 0 8px; font-weight: 700; }
            input { width: 100%; padding: 14px; border: 1px solid #b9cdbd; border-radius: 8px; font-size: 18px; }
            button { width: 100%; margin-top: 16px; padding: 14px; border: 0; border-radius: 8px; background: #2f7d43; color: #fff; font-size: 16px; font-weight: 700; cursor: pointer; }
            .error { color: #a82d2d; font-weight: 700; }
        </style>
    </head>
    <body>
        <main>
            <h1>SmartBin Monitor</h1>
            <p>Enter the monitor access code to continue.</p>
            <?php if ($hasAttempt): ?><p class="error">Invalid access code.</p><?php endif; ?>
            <form method="post">
                <label for="key">Access code</label>
                <input id="key" name="key" type="password" autocomplete="current-password" required autofocus>
                <button type="submit">Open monitor</button>
            </form>
        </main>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/config/config.php';

$binDefinitions = [
    ['key' => 'reject', 'label' => 'Reject Waste', 'icon' => '!', 'color' => '#c94b4b', 'terms' => ['reject', 'residual', 'general', 'other']],
    ['key' => 'bio', 'label' => 'Shredded Biodegradable', 'icon' => 'BIO', 'color' => '#3e9b61', 'terms' => ['biodegradable', 'bio', 'shred bio']],
    ['key' => 'metal', 'label' => 'Metal Waste', 'icon' => 'MET', 'color' => '#657486', 'terms' => ['metal']],
    ['key' => 'thin-plastic', 'label' => 'Shredded Thin Plastic', 'icon' => 'TP', 'color' => '#d18a32', 'terms' => ['thin plastic', 'shredded plastic', 'thin', 'shred']],
    ['key' => 'bottle', 'label' => 'Plastic Bottle', 'icon' => 'PET', 'color' => '#3386b8', 'terms' => ['bottle', 'pet']],
];

$rows = [];
$loadError = '';
try {
    $stmt = $pdo->query("SELECT bin_id, bin_name, bin_code, bin_type, status, fill_level, last_updated FROM v_bin_status ORDER BY bin_id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log('Five-bin monitor error: ' . $error->getMessage());
    $loadError = 'Bin status is temporarily unavailable.';
}

$assigned = array_fill(0, count($binDefinitions), null);
$remaining = $rows;
foreach ($binDefinitions as $index => $definition) {
    foreach ($remaining as $rowIndex => $row) {
        $searchText = strtolower(trim(($row['bin_name'] ?? '') . ' ' . ($row['bin_code'] ?? '') . ' ' . ($row['bin_type'] ?? '')));
        foreach ($definition['terms'] as $term) {
            if ($searchText !== '' && strpos($searchText, strtolower($term)) !== false) {
                $assigned[$index] = $row;
                unset($remaining[$rowIndex]);
                break 2;
            }
        }
    }
}
foreach ($assigned as $index => $row) {
    if ($row === null && $remaining) {
        $assigned[$index] = array_shift($remaining);
    }
}

function monitorFill($row) {
    return max(0, min(100, (float)($row['fill_level'] ?? 0)));
}

function monitorStatus($fill, $row) {
    if ($row === null) return ['label' => 'No data', 'class' => 'offline'];
    if (($row['status'] ?? '') === 'maintenance') return ['label' => 'Maintenance', 'class' => 'maintenance'];
    if ($fill >= 80) return ['label' => 'Full', 'class' => 'full'];
    if ($fill >= 60) return ['label' => 'High', 'class' => 'high'];
    return ['label' => 'Available', 'class' => 'available'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <title>Five-Bin Monitor - SmartBin</title>
    <style>
        :root { font-family: Arial, sans-serif; color: #183124; background: #f2f6f1; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: clamp(16px, 3vw, 42px); background: radial-gradient(circle at top right, #dcecdf, transparent 42%), #f2f6f1; }
        header { max-width: 1500px; margin: 0 auto 28px; display: flex; justify-content: space-between; gap: 20px; align-items: end; }
        h1 { margin: 0; font-size: clamp(28px, 4vw, 52px); letter-spacing: .02em; }
        header p { margin: 8px 0 0; color: #5d7463; }
        time { color: #5d7463; white-space: nowrap; }
        .notice { max-width: 1500px; margin: 0 auto 18px; padding: 12px 16px; border-radius: 8px; background: #fff4df; color: #80551f; }
        .bins { max-width: 1500px; margin: 0 auto; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; align-items: stretch; }
        .bin { min-width: 0; padding: 22px 16px; background: #fff; border: 1px solid #d5e2d6; border-top: 7px solid var(--color); border-radius: 12px; box-shadow: 0 12px 28px rgba(30, 70, 39, .08); }
        .position { color: #78907d; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; }
        .icon { width: 62px; height: 62px; margin: 18px auto; display: grid; place-items: center; border-radius: 50%; background: #edf5ee; color: var(--color); font-size: 15px; font-weight: 800; }
        h2 { min-height: 48px; margin: 0; text-align: center; font-size: clamp(16px, 1.5vw, 22px); }
        .value { margin: 20px 0 8px; text-align: center; color: var(--color); font-size: clamp(32px, 4vw, 56px); font-weight: 800; }
        .meter { height: 10px; overflow: hidden; background: #e7eee8; border-radius: 10px; }
        .meter span { display: block; height: 100%; width: var(--fill); background: var(--color); border-radius: inherit; }
        .status { display: block; margin-top: 16px; text-align: center; font-weight: 700; }
        .available { color: #2f7d43; }.high { color: #b4771e; }.full, .offline { color: #b33d3d; }.maintenance { color: #657486; }
        .details { margin-top: 12px; text-align: center; color: #78907d; font-size: 12px; overflow-wrap: anywhere; }
        footer { max-width: 1500px; margin: 24px auto 0; color: #78907d; font-size: 13px; }
        @media (max-width: 1000px) { .bins { grid-template-columns: repeat(2, minmax(0, 1fr)); } header { align-items: start; flex-direction: column; } }
        @media (max-width: 560px) { .bins { grid-template-columns: 1fr; } .bin { padding: 18px; } }
    </style>
</head>
<body>
    <header>
        <div><h1>Five-Bin Monitor</h1><p>Live waste-separation status, left to right</p></div>
        <time><?php echo htmlspecialchars(date('M d, Y h:i A')); ?></time>
    </header>
    <?php if ($loadError): ?><div class="notice"><?php echo htmlspecialchars($loadError); ?></div><?php endif; ?>
    <section class="bins" aria-label="Five waste bins">
        <?php foreach ($binDefinitions as $index => $definition): $row = $assigned[$index]; $fill = monitorFill($row); $status = monitorStatus($fill, $row); ?>
            <article class="bin" style="--color: <?php echo $definition['color']; ?>; --fill: <?php echo $fill; ?>%">
                <div class="position">Position <?php echo $index + 1; ?></div>
                <div class="icon" aria-hidden="true"><?php echo $definition['icon']; ?></div>
                <h2><?php echo htmlspecialchars($definition['label']); ?></h2>
                <div class="value"><?php echo number_format($fill, 0); ?>%</div>
                <div class="meter" role="progressbar" aria-valuenow="<?php echo $fill; ?>" aria-valuemin="0" aria-valuemax="100"><span></span></div>
                <span class="status <?php echo $status['class']; ?>"><?php echo htmlspecialchars($status['label']); ?></span>
                <div class="details"><?php echo $row ? htmlspecialchars(($row['bin_name'] ?: $row['bin_code'] ?: 'Connected')) : 'Not configured'; ?></div>
            </article>
        <?php endforeach; ?>
    </section>
    <footer>Order: Reject Waste, Shredded Biodegradable, Metal Waste, Shredded Thin Plastic, Plastic Bottle.</footer>
</body>
</html>