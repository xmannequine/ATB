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
        <title>Monitor Access Â· SmartBin</title>
        <style>
            :root {
                color-scheme: light;
                --ink: #14261b;
                --muted: #6b7f70;
                --line: #d9e4db;
                --brand: #2f7d43;
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 24px;
                color: var(--ink);
                font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background:
                    radial-gradient(900px 500px at 12% -5%, #dcefe0, transparent 60%),
                    radial-gradient(800px 520px at 105% 105%, #e4f0e6, transparent 55%),
                    #eef3ee;
            }
            .gate {
                width: min(100%, 420px);
                padding: 34px 30px 30px;
                background: #fff;
                border: 1px solid var(--line);
                border-radius: 22px;
                box-shadow: 0 30px 70px -35px rgba(16, 44, 26, .45), 0 2px 4px rgba(16, 44, 26, .05);
            }
            .gate__mark {
                width: 52px; height: 52px;
                display: grid; place-items: center;
                border-radius: 15px;
                color: #fff;
                background: linear-gradient(150deg, #3f9a56, #22663a);
                box-shadow: 0 12px 26px -14px rgba(35, 102, 58, 1);
            }
            h1 { margin: 22px 0 6px; font-size: 26px; letter-spacing: -.02em; }
            .gate__sub { margin: 0; color: var(--muted); font-size: 14.5px; line-height: 1.55; }
            .gate__error {
                display: flex; align-items: center; gap: 9px;
                margin: 18px 0 0; padding: 11px 13px;
                border: 1px solid #f4cdca; border-radius: 11px;
                background: #fdeceb; color: #a82d2d;
                font-size: 13.5px; font-weight: 600;
            }
            .gate__error::before {
                content: "";
                width: 8px; height: 8px; flex: none;
                border-radius: 50%; background: #d64a44;
            }
            .gate label {
                display: block; margin: 24px 0 8px;
                font-size: 11.5px; font-weight: 800;
                letter-spacing: .12em; text-transform: uppercase;
                color: var(--muted);
            }
            .gate input {
                width: 100%; padding: 14px 15px;
                font-family: inherit; font-size: 17px; color: var(--ink);
                background: #f7faf7;
                border: 1px solid var(--line); border-radius: 12px;
                outline: none;
                transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
            }
            .gate input:focus {
                background: #fff;
                border-color: var(--brand);
                box-shadow: 0 0 0 4px rgba(47, 125, 67, .14);
            }
            .gate button {
                width: 100%; margin-top: 16px; padding: 15px;
                font-family: inherit; font-size: 15.5px; font-weight: 700;
                color: #fff; cursor: pointer;
                border: 0; border-radius: 12px;
                background: linear-gradient(180deg, #37914e, #256b38);
                box-shadow: 0 12px 24px -14px rgba(35, 102, 58, 1);
                transition: filter .2s ease, transform .15s ease, box-shadow .2s ease;
            }
            .gate button:hover { filter: brightness(1.06); box-shadow: 0 16px 28px -14px rgba(35, 102, 58, 1); }
            .gate button:active { transform: translateY(1px); }
        </style>
    </head>
    <body>
        <main class="gate">
            <div class="gate__mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 7h18"/>
                    <path d="M8 7V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/>
                    <path d="M6 7l1 12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-12"/>
                    <path d="M10 11v6M14 11v6"/>
                </svg>
            </div>
            <h1>SmartBin Monitor</h1>
            <p class="gate__sub">Enter the monitor access code to continue.</p>
            <?php if ($hasAttempt): ?>
                <p class="gate__error" role="alert">Invalid access code. Please try again.</p>
            <?php endif; ?>
            <form method="post">
                <label for="key">Access code</label>
                <input id="key" name="key" type="password" autocomplete="current-password" spellcheck="false" required autofocus>
                <button type="submit">Open monitor</button>
            </form>
        </main>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/config/config.php';
$monitorUpdatedTime = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));

$monitorHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$monitorHost = preg_replace('/:\\d+$/', '', $monitorHost);
$cameraIsLocalStream = APP_ENV === 'local'
    || in_array($monitorHost, ['localhost', '127.0.0.1', '[::1]'], true);
$cameraUrl = $cameraIsLocalStream
    ? 'http://192.168.8.206:81/stream'
    : '/uploads/camera/latest.jpg?t=' . time();
$cameraImagePath = __DIR__ . '/uploads/camera/latest.jpg';
$cameraImageAvailable = $cameraIsLocalStream || is_file($cameraImagePath);
$cameraState = null;
try {
    $stmt = $pdo->query('SELECT camera_status, last_event, category, confidence, detection_at, last_seen FROM camera_status WHERE id = 1 LIMIT 1');
    $cameraState = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $error) {
    error_log('Camera status unavailable: ' . $error->getMessage());
}
$cameraOnline = $cameraState && !empty($cameraState['last_seen'])
    && strtotime($cameraState['last_seen']) >= time() - 120;
$cameraEventLabels = [
    'system_ready' => 'System ready',
    'camera_error' => 'Camera error',
    'object_detected' => 'Object detected',
    'ai_result' => 'AI classification complete',
    'ai_failed' => 'AI classification failed',
    'object_removed' => 'Object removed'
];
$cameraCategoryLabels = [
    'plastic_bottle' => 'Plastic Bottle',
    'shredded_thin_plastic' => 'Thin Plastic',
    'metal_waste' => 'Metal Waste',
    'shredded_biodegradable' => 'Biodegradable',
    'reject' => 'Reject'
];
$cameraCategory = $cameraCategoryLabels[strtolower((string)($cameraState['category'] ?? ''))] ?? '';
$cameraObjectDetected = in_array(($cameraState['last_event'] ?? ''), ['object_detected', 'ai_result', 'ai_failed'], true);

$binDefinitions = [
    ['key' => 'bottle', 'code' => 'BIN-001', 'label' => 'Plastic Bottle', 'icon' => 'PET', 'color' => '#3386b8', 'terms' => ['bottle', 'pet']],
    ['key' => 'thin-plastic', 'code' => 'BIN-002', 'label' => 'Thin Plastic', 'icon' => 'TP', 'color' => '#d18a32', 'terms' => ['thin plastic', 'shredded plastic', 'thin', 'shred']],
    ['key' => 'metal', 'code' => 'BIN-003', 'label' => 'Metal Waste', 'icon' => 'MET', 'color' => '#657486', 'terms' => ['metal']],
    ['key' => 'bio', 'code' => 'BIN-004', 'label' => 'Bio Waste', 'icon' => 'BIO', 'color' => '#3e9b61', 'terms' => ['biodegradable', 'bio', 'shred bio']],
    ['key' => 'reject', 'code' => 'BIN-005', 'label' => 'Reject Waste', 'icon' => '!', 'color' => '#c94b4b', 'terms' => ['reject', 'residual', 'general', 'other']],
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
        if (strcasecmp(trim((string)($row['bin_code'] ?? '')), $definition['code']) === 0) {
            $assigned[$index] = $row;
            unset($remaining[$rowIndex]);
            continue 2;
        }
    }
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

if (($_GET['api'] ?? '') === 'status') {
    $statusRows = $rows;
    if (isset($_GET['bin_id'])) {
        $requestedBinId = (int)$_GET['bin_id'];
        $statusRows = array_values(array_filter($rows, static function ($row) use ($requestedBinId) {
            return (int)$row['bin_id'] === $requestedBinId;
        }));
    }

    $fillLevels = ['biodegradable' => 0, 'plastic' => 0, 'metal' => 0];
    foreach ($statusRows as $statusRow) {
        $binType = strtolower((string)($statusRow['bin_type'] ?? ''));
        if (array_key_exists($binType, $fillLevels)) {
            $fillLevels[$binType] = (float)$statusRow['fill_level'];
        }
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_resolved = 0");
        $alertCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->query('SELECT camera_status, last_event, category, confidence, detection_at, last_seen FROM camera_status WHERE id = 1 LIMIT 1');
        $statusCamera = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $statusCameraOnline = $statusCamera && !empty($statusCamera['last_seen'])
            && strtotime($statusCamera['last_seen']) >= time() - 120;
        $statusDetectionTime = null;
        if (!empty($statusCamera['detection_at'])) {
            $statusDetectionTime = (new DateTimeImmutable(
                $statusCamera['detection_at'],
                new DateTimeZone(date_default_timezone_get())
            ))->setTimezone(new DateTimeZone('Asia/Manila'))->format('g:i A') . ' PHT';
        }

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
        $systemMetrics = [
            'segregation' => $totalLogs > 0
                ? number_format(((int)$metricData['successful_logs'] / $totalLogs) * 100, 1) . '%'
                : 'N/A',
            'performance' => $activeDevices > 0
                ? number_format(((int)$metricData['responsive_devices'] / $activeDevices) * 100, 1) . '%'
                : 'N/A',
            'acceptability' => 'N/A',
            'maintainability' => $totalBins > 0
                ? number_format(((int)$metricData['maintainable_bins'] / $totalBins) * 100, 1) . '%'
                : 'N/A'
        ];
    } catch (Throwable $error) {
        error_log('Five-bin monitor status API error: ' . $error->getMessage());
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Monitor status is temporarily unavailable.']);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'fill_levels' => $fillLevels,
        'alert_count' => $alertCount,
        'system_metrics' => $systemMetrics,
        'camera' => [
            'online' => $statusCameraOnline,
            'status' => $statusCamera['camera_status'] ?? 'offline',
            'event' => $statusCamera['last_event'] ?? '',
            'category' => $statusCamera['category'] ?? '',
            'confidence' => isset($statusCamera['confidence']) ? (float)$statusCamera['confidence'] : null,
            'detection_at' => $statusCamera['detection_at'] ?? null,
            'detection_time' => $statusDetectionTime
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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

// One warning per bin per load (no duplicates); page auto-refresh naturally re-evaluates every cycle.
$binWarnings = [];
foreach ($binDefinitions as $index => $definition) {
    $fill = monitorFill($assigned[$index]);
    if ($assigned[$index] !== null && $fill >= 90) {
        $binWarnings[] = sprintf('Warning: %s is %s%% full. Please empty the bin.', $definition['label'], number_format($fill, 0));
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <title>Five-Bin Monitor Â· SmartBin</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #eef3ee;
            --surface: #ffffff;
            --line: #d9e4db;
            --ink: #14261b;
            --ink-2: #3d5747;
            --muted: #708a77;
            --brand: #2f7d43;
            --radius: 18px;
            --shadow-sm: 0 1px 2px rgba(16, 44, 26, .05), 0 10px 26px -18px rgba(16, 44, 26, .35);
            --shadow-lg: 0 26px 54px -26px rgba(16, 44, 26, .38);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            padding: clamp(16px, 2.4vw, 40px);
            color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            background:
                radial-gradient(1100px 560px at 100% -12%, #dcefe0, transparent 58%),
                radial-gradient(900px 520px at -10% 108%, #e5f0e7, transparent 55%),
                var(--bg);
            background-attachment: fixed;
        }

        .shell { width: min(1560px, 100%); margin-inline: auto; }

        @media (min-width: 861px) {
            body { padding: 14px 24px; }
            .topbar { margin-bottom: 14px; }
            .bins { gap: 10px; }
            .bin { padding: 15px 12px 13px; }
            .bin__name { margin-top: 10px; min-height: 38px; }
            .bin__value { margin-top: 8px; font-size: 38px; }
            .meter { margin-top: 10px; }
            .bin__foot { margin-top: 10px; padding-top: 10px; gap: 6px; }
            .camera { margin-top: 14px; }
            .camera__head { padding: 10px 16px; }
            .camera__frame { padding: 8px; }
            .camera__screen { height: min(42vh, 440px); min-height: 180px; }
            .pagefoot { margin-top: 10px; }
        }

        /* ---------- Top bar ---------- */
        .topbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: clamp(18px, 2.2vw, 28px);
        }
        .brand { display: flex; align-items: center; gap: 14px; }
        .brand__mark {
            width: 52px; height: 52px; flex: none;
            display: grid; place-items: center;
            border-radius: 15px;
            color: #fff;
            background: linear-gradient(150deg, #3f9a56, #22663a);
            box-shadow: 0 12px 26px -14px rgba(35, 102, 58, 1);
        }
        .brand h1 {
            margin: 0;
            font-size: clamp(24px, 2.6vw, 34px);
            letter-spacing: -.025em;
            line-height: 1.1;
        }
        .brand p {
            margin: 5px 0 0;
            font-size: 13.5px;
            color: var(--muted);
        }

        .clock {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 16px;
            background: rgba(255, 255, 255, .78);
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: var(--shadow-sm);
            backdrop-filter: blur(8px);
        }
        .clock svg { color: var(--brand); flex: none; }
        .clock__label {
            display: block;
            font-size: 10px; font-weight: 800;
            letter-spacing: .15em; text-transform: uppercase;
            color: var(--muted);
        }
        .clock time {
            font-size: 14.5px; font-weight: 700;
            color: var(--ink-2);
            font-variant-numeric: tabular-nums;
        }

        /* ---------- Notice ---------- */
        .notice {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 18px;
            padding: 12px 16px;
            border: 1px solid #f0dcb4;
            border-radius: 12px;
            background: #fff7e8;
            color: #80551f;
            font-size: 13.5px;
            font-weight: 600;
        }
        .notice::before {
            content: "";
            width: 8px; height: 8px; flex: none;
            border-radius: 50%; background: #d99b2b;
        }
        .notice.notice--danger {
            border-color: #f4cdca;
            background: #fdecea;
            color: #b3342f;
        }
        .notice.notice--danger::before { background: #d64a44; }

        /* ---------- Bin grid ---------- */
        .bins {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: clamp(12px, 1.1vw, 18px);
        }

        .bin {
            position: relative;
            display: flex;
            flex-direction: column;
            padding: 20px 18px 18px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: transform .25s ease, box-shadow .25s ease;
        }
        .bin::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 6px;
            background: var(--color);
        }

        @media (hover: hover) {
            .bin:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        }

        .bin__top {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            margin-top: 4px;
        }
        .bin__pos {
            font-size: 10.5px; font-weight: 800;
            letter-spacing: .15em; text-transform: uppercase;
            color: var(--muted);
        }
        .bin__icon {
            width: 38px; height: 38px; flex: none;
            display: grid; place-items: center;
            border-radius: 11px;
            font-size: 11.5px; font-weight: 800; letter-spacing: .02em;
            color: var(--color);
            background: #f3f7f4;
            box-shadow: inset 0 0 0 1px rgba(16, 44, 26, .07);
        }

        .bin__name {
            min-height: 41px;
            margin: 16px 0 0;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.35;
            letter-spacing: -.01em;
            color: var(--ink-2);
        }

        .bin__value {
            display: flex; align-items: baseline; gap: 3px;
            margin-top: 12px;
            font-size: clamp(34px, 3.3vw, 50px);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -.035em;
            color: var(--color);
            font-variant-numeric: tabular-nums;
        }
        .bin__value small { font-size: .4em; font-weight: 700; opacity: .62; }

        .meter {
            margin-top: 14px;
            height: 8px;
            border-radius: 99px;
            background: #e9f0ea;
            box-shadow: inset 0 1px 2px rgba(16, 44, 26, .07);
            overflow: hidden;
        }
        .meter span {
            display: block;
            height: 100%;
            width: var(--fill);
            border-radius: inherit;
            background: var(--color);
            animation: meterGrow .9s cubic-bezier(.22, .9, .24, 1) both;
        }
        @keyframes meterGrow { from { width: 0; } }

        .bin__foot {
            display: flex; flex-direction: column; gap: 8px;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed rgba(16, 44, 26, .1);
        }

        .pill {
            display: inline-flex; align-items: center; gap: 7px;
            align-self: flex-start;
            padding: 5px 11px;
            border: 1px solid var(--line);
            border-radius: 99px;
            font-size: 11.5px; font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--ink-2);
            background: #f4f7f4;
        }
        .pill i { width: 7px; height: 7px; flex: none; border-radius: 50%; background: currentColor; }

        .pill.available   { color: #2f7d43; background: #eaf6ee; border-color: #c8e5d2; }
        .pill.high        { color: #a2670f; background: #fdf4e3; border-color: #f1ddb6; }
        .pill.full        { color: #b3342f; background: #fdecea; border-color: #f4cdca; }
        .pill.offline     { color: #78838f; background: #f2f4f6; border-color: #dee3e8; }
        .pill.maintenance { color: #56677e; background: #eef2f8; border-color: #d7e0eb; }

        .bin__meta {
            font-size: 12px;
            color: var(--muted);
            overflow-wrap: anywhere;
        }

        /* ---------- Camera panel ---------- */
        .camera {
            margin-top: clamp(20px, 2.2vw, 30px);
            border: 1px solid #1d3327;
            border-radius: var(--radius);
            background: #0f1c15;
            box-shadow: 0 34px 74px -44px rgba(8, 28, 16, .95);
            overflow: hidden;
        }
        .camera__head {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px;
            padding: 16px clamp(16px, 1.8vw, 22px);
            background: linear-gradient(180deg, #16291e, #101d15);
            border-bottom: 1px solid #1d3327;
        }
        .camera__title { display: flex; align-items: center; gap: 13px; }
        .camera__title .glyph {
            width: 40px; height: 40px; flex: none;
            display: grid; place-items: center;
            border-radius: 12px;
            color: #7fe0a3;
            background: rgba(63, 154, 86, .14);
            box-shadow: inset 0 0 0 1px rgba(127, 224, 163, .22);
        }
        .camera__title h2 {
            margin: 0;
            font-size: clamp(16px, 1.5vw, 19px);
            letter-spacing: -.01em;
            color: #e9f5ee;
        }
        .camera__title p {
            margin: 3px 0 0;
            font-size: 12.5px;
            color: #7fa38d;
        }

        .live {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 13px;
            border: 1px solid rgba(239, 68, 68, .38);
            border-radius: 99px;
            background: rgba(239, 68, 68, .12);
            color: #ff8f88;
            font-size: 10.5px; font-weight: 800;
            letter-spacing: .16em; text-transform: uppercase;
        }
        .live i {
            width: 7px; height: 7px; flex: none;
            border-radius: 50%;
            background: #ef4444;
            animation: livePulse 2s ease-out infinite;
        }
        @keyframes livePulse {
            0%   { box-shadow: 0 0 0 0 rgba(239, 68, 68, .65); }
            70%  { box-shadow: 0 0 0 9px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .camera__frame {
            padding: clamp(10px, 1.1vw, 16px);
            background: #0b1510;
        }
        .camera__screen {
            position: relative;
            aspect-ratio: 16 / 9;
            min-height: 260px;
            border: 1px solid #1e3529;
            border-radius: 12px;
            background: #000;
            overflow: hidden;
        }
        .camera__screen img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            object-fit: contain;
        }
        .camera-unavailable {
            min-height: 260px;
            display: grid;
            place-items: center;
            padding: 24px;
            color: #b6f0cc;
            text-align: center;
            font-size: 14px;
        }
        .camera__tag {
            position: absolute;
            top: 10px; left: 10px;
            z-index: 2;
            padding: 5px 10px;
            border-radius: 7px;
            background: rgba(6, 18, 11, .62);
            color: #b6f0cc;
            font-size: 10.5px; font-weight: 700;
            letter-spacing: .12em; text-transform: uppercase;
            pointer-events: none;
            backdrop-filter: blur(3px);
        }

        .camera__results {
            display: grid;
            grid-template-columns: minmax(0, 2fr) repeat(3, minmax(0, 1fr));
            align-items: center;
            gap: 10px 18px;
            padding: 12px clamp(14px, 2vw, 24px);
            border-bottom: 1px solid var(--line);
            background: #f7faf7;
        }
        .camera__result { min-width: 0; }
        .camera__result--prediction {
            padding-left: 14px;
            border-left: 4px solid var(--brand);
        }
        .camera__result-label {
            display: block;
            margin-bottom: 3px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .camera__result-value {
            display: block;
            overflow: hidden;
            color: var(--ink-2);
            font-size: 15px;
            font-weight: 700;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .camera__result--prediction .camera__result-value {
            color: var(--brand);
            font-size: 32px;
            font-weight: 900;
            line-height: 1.1;
        }
        .camera__confidence {
            margin-top: 5px;
            color: var(--ink-2);
            font-size: 14px;
            font-weight: 700;
        }
        .camera__state {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .camera__state::before {
            content: "";
            width: 9px;
            height: 9px;
            flex: none;
            border-radius: 50%;
            background: #9aa79e;
        }
        .camera__state.is-online::before { background: #2f9b58; }
        .camera__state.is-detecting::before { background: #d18a32; }

        /* ---------- Footer ---------- */
        .pagefoot {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px;
            margin-top: clamp(18px, 2vw, 26px);
            padding-bottom: 6px;
            color: var(--muted);
            font-size: 12.5px;
        }
        .legend { display: flex; flex-wrap: wrap; gap: 8px; }
        .legend span {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 5px 11px 5px 8px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 99px;
            font-weight: 600;
            color: var(--ink-2);
        }
        .legend i {
            width: 9px; height: 9px; flex: none;
            border-radius: 3px;
            background: var(--c);
        }
        .legend b { font-weight: 800; color: var(--muted); font-variant-numeric: tabular-nums; }

        /* ---------- Responsive ---------- */
        @media (max-width: 1280px) {
            .bins { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 860px) {
            .bins { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .topbar { align-items: flex-start; flex-direction: column; }
        }
        @media (max-width: 520px) {
            .bins { grid-template-columns: 1fr; }
            .bin__name { min-height: 0; }
            .camera__screen { min-height: 200px; aspect-ratio: 4 / 3; }
            .pagefoot { flex-direction: column; align-items: flex-start; }
            .camera__results { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .camera__result--prediction { grid-column: 1 / -1; }
            .camera__result--prediction .camera__result-value { font-size: 24px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .001ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <div class="brand">
                <div class="brand__mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 7h18"/>
                        <path d="M8 7V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/>
                        <path d="M6 7l1 12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-12"/>
                        <path d="M10 11v6M14 11v6"/>
                    </svg>
                </div>
                <div>
                    <h1>CT-ABS MONITORING SCREEN</h1>
                    <p>Live waste-separation status, left to right</p>
                </div>
            </div>
            <div class="clock">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
                <div>
                    <span class="clock__label">Updated</span>
                    <time><?php echo htmlspecialchars($monitorUpdatedTime->format('M d, Y h:i A')); ?> PHT</time>
                </div>
            </div>
        </header>

        <?php if ($loadError): ?>
            <div class="notice" role="status"><?php echo htmlspecialchars($loadError); ?></div>
        <?php endif; ?>

        <?php foreach ($binWarnings as $binWarning): ?>
            <div class="notice notice--danger" role="alert"><?php echo htmlspecialchars($binWarning); ?></div>
        <?php endforeach; ?>

        <section class="bins" aria-label="Five waste bins">
            <?php foreach ($binDefinitions as $index => $definition): $row = $assigned[$index]; $fill = monitorFill($row); $status = monitorStatus($fill, $row); ?>
                <article class="bin" style="--color: <?php echo $definition['color']; ?>; --fill: <?php echo $fill; ?>%">
                    <div class="bin__top">
                        <span class="bin__pos">Position <?php echo $index + 1; ?></span>
                        <span class="bin__icon" aria-hidden="true"><?php echo htmlspecialchars($definition['icon']); ?></span>
                    </div>
                    <h2 class="bin__name"><?php echo htmlspecialchars($definition['label']); ?></h2>
                    <div class="bin__value"><?php echo number_format($fill, 0); ?><small>%</small></div>
                    <div class="meter" role="progressbar" aria-valuenow="<?php echo $fill; ?>" aria-valuemin="0" aria-valuemax="100"><span></span></div>
                    <div class="bin__foot">
                        <span class="pill <?php echo $status['class']; ?>"><i aria-hidden="true"></i><?php echo htmlspecialchars($status['label']); ?></span>
                        <span class="bin__meta"><?php echo $row ? htmlspecialchars(($row['bin_name'] ?: $row['bin_code'] ?: 'Connected')) : 'Not configured'; ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="camera" aria-labelledby="camera-heading">
            <div class="camera__head">
                <div class="camera__title">
                    <span class="glyph" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M23 7l-7 5 7 5V7z"/>
                            <rect x="1" y="5" width="15" height="14" rx="2"/>
                        </svg>
                    </span>
                    <div>
                        <h2 id="camera-heading">ESP32-CAM Live Camera</h2>
                        <p>Real-time view of the bin station</p>
                    </div>
                </div>
                <span class="live"><i aria-hidden="true"></i>Live</span>
            </div>
            <div class="camera__results" aria-label="Camera and AI results">
                <div class="camera__result">
                    <span class="camera__result-label">Camera status</span>
                    <span id="camera-status" class="camera__result-value camera__state<?php echo $cameraOnline ? ' is-online' : ''; ?>"><?php echo $cameraOnline ? 'Online' : 'Offline'; ?></span>
                </div>
                <div class="camera__result">
                    <span class="camera__result-label">Detection</span>
                    <span id="camera-detection" class="camera__result-value camera__state<?php echo $cameraObjectDetected ? ' is-detecting' : ''; ?>"><?php echo $cameraObjectDetected ? 'Object detected' : 'No object detected'; ?></span>
                </div>
                <div class="camera__result camera__result--prediction">
                    <span class="camera__result-label">AI classification</span>
                    <span id="camera-prediction" class="camera__result-value"><?php echo htmlspecialchars($cameraCategory !== '' ? $cameraCategory : 'Waiting for detection'); ?></span>
                    <span id="camera-confidence" class="camera__confidence"><?php echo $cameraCategory !== '' && $cameraState['confidence'] !== null ? 'Confidence: ' . number_format((float)$cameraState['confidence'] * 100, 1) . '%' : 'Confidence: --'; ?></span>
                </div>
                <div class="camera__result">
                    <span class="camera__result-label">Last detection</span>
                    <span id="camera-detection-time" class="camera__result-value"><?php echo !empty($cameraState['detection_at']) ? htmlspecialchars((new DateTimeImmutable($cameraState['detection_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Manila'))->format('g:i A')) . ' PHT' : 'No detection yet'; ?></span>
                </div>
            </div>
            <div class="camera__frame">
                <div class="camera__screen">
                    <span class="camera__tag">CAM 01 Â· ESP32-CAM</span>
                    <?php if (!$cameraImageAvailable): ?>
                        <div class="camera-unavailable" role="status">Camera image unavailable</div>
                    <?php elseif ($cameraIsLocalStream): ?>
                        <img src="<?php echo htmlspecialchars($cameraUrl); ?>" alt="ESP32-CAM local live stream">
                    <?php else: ?>
                        <img id="camera-snapshot" src="<?php echo htmlspecialchars($cameraUrl); ?>" alt="Latest ESP32-CAM snapshot" onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
                        <div class="camera-unavailable" role="status" hidden>Camera image unavailable</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if (!$cameraIsLocalStream && $cameraImageAvailable): ?>
            <script>
                setInterval(function () {
                    var camera = document.getElementById('camera-snapshot');
                    if (camera) {
                        camera.src = '/uploads/camera/latest.jpg?t=' + Date.now();
                    }
                }, 30000);
            </script>
        <?php endif; ?>

        <script>
            (function () {
                var categoryLabels = {
                    plastic_bottle: 'Plastic Bottle',
                    shredded_thin_plastic: 'Thin Plastic',
                    metal_waste: 'Metal Waste',
                    shredded_biodegradable: 'Biodegradable',
                    reject: 'Reject'
                };

                function refreshCameraResult() {
                    fetch('monitor.php?api=status', {
                        cache: 'no-store',
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Camera status request failed');
                        return response.json();
                    })
                    .then(function (data) {
                        if (!data || !data.success || !data.camera) return;
                        var camera = data.camera;
                        var status = document.getElementById('camera-status');
                        var detection = document.getElementById('camera-detection');
                        var prediction = document.getElementById('camera-prediction');
                        var confidence = document.getElementById('camera-confidence');
                        var detectionTime = document.getElementById('camera-detection-time');
                        var objectDetected = ['object_detected', 'ai_result', 'ai_failed'].indexOf(camera.event) !== -1;

                        if (status) {
                            status.textContent = camera.online ? 'Online' : 'Offline';
                            status.classList.toggle('is-online', !!camera.online);
                        }
                        if (detection) {
                            detection.textContent = objectDetected ? 'Object detected' : 'No object detected';
                            detection.classList.toggle('is-detecting', objectDetected);
                        }
                        if (prediction) {
                            prediction.textContent = categoryLabels[camera.category] || (camera.event === 'ai_failed' ? 'Classification failed' : 'Waiting for detection');
                        }
                        if (confidence) {
                            confidence.textContent = camera.confidence === null ? 'Confidence: --' : 'Confidence: ' + (Number(camera.confidence) * 100).toFixed(1) + '%';
                        }
                        if (detectionTime) {
                            detectionTime.textContent = camera.detection_time || 'No detection yet';
                        }
                    })
                    .catch(function () {});
                }

                refreshCameraResult();
                setInterval(refreshCameraResult, 1500);
            })();
        </script>

        <footer class="pagefoot">
            <div class="legend" aria-label="Bin order, left to right">
                <?php foreach ($binDefinitions as $index => $definition): ?>
                    <span style="--c: <?php echo $definition['color']; ?>">
                        <i aria-hidden="true"></i>
                        <b><?php echo $index + 1; ?></b>
                        <?php echo htmlspecialchars($definition['label']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <div>Auto-refreshes every 60 seconds.</div>
        </footer>
    </div>
</body>
</html> 
