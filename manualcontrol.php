<?php
// ============================================================
// CT-ABS - MANUAL SERVO CONTROL
// Five Individual Servos | ON / OFF Only
// ============================================================

require_once 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$deviceId = 1;

// ============================================================
// SERVO CONFIGURATION
// ============================================================

$servos = [
    1 => [
        'name' => 'Thin Plastic Shredder',
        'off' => 0,
        'on' => 90
    ],
    2 => [
        'name' => 'Biodegradable Shredder',
        'off' => 0,
        'on' => 90
    ],
    3 => [
        'name' => 'Plastic Bottle Flapper (Right)',
        'off' => 0,
        'on' => 180
    ],
    4 => [
        'name' => 'Reject / Metal Flapper',
        'off' => 80,
        'on' => 140,
        'extra_angles' => [0]
    ],
    5 => [
        'name' => 'Left Flapper',
        'off' => 0,
        'on' => 180
    ]
];

// ============================================================
// AJAX COMMAND HANDLER
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_GET['action'] ?? '') === 'queue_servo'
) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            throw new Exception('Invalid request.');
        }

        $servoNumber = filter_var(
            $input['servo_number'] ?? null,
            FILTER_VALIDATE_INT
        );

        $targetAngle = filter_var(
            $input['target_angle'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $servoNumber === false ||
            !isset($servos[$servoNumber]) ||
            $targetAngle === false
        ) {
            throw new Exception('Invalid servo number or angle.');
        }

        $servo = $servos[$servoNumber];

        $allowedAngles = [
            $servo['off'],
            $servo['on']
        ];

        if (isset($servo['extra_angles'])) {
            $allowedAngles = array_merge(
                $allowedAngles,
                $servo['extra_angles']
            );
        }

        if (
            !in_array($targetAngle, $allowedAngles, true)
        ) {
            throw new Exception('Invalid angle for this servo.');
        }

        $commandType = ($targetAngle === $servo['on'])
            ? 'ON'
            : 'OFF';

        $pdo->beginTransaction();

        // Insert each selected servo command into the pending queue.
        $stmt = $pdo->prepare("
            INSERT INTO servo_commands (
                device_id,
                servo_number,
                servo_name,
                command,
                target_angle,
                status
            ) VALUES (
                :device_id,
                :servo_number,
                :servo_name,
                :command,
                :target_angle,
                'pending'
            )
        ");

        $stmt->execute([
            ':device_id' => $deviceId,
            ':servo_number' => $servoNumber,
            ':servo_name' => $servo['name'],
            ':command' => $commandType,
            ':target_angle' => $targetAngle
        ]);

        $commandId = (int) $pdo->lastInsertId();

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Servo command added successfully.',
            'commands' => [
                [
                    'command_id' => $commandId,
                    'servo_number' => $servoNumber,
                    'servo_name' => $servo['name'],
                    'command' => $commandType,
                    'target_angle' => $targetAngle
                ]
            ]
        ]);

    } catch (Throwable $e) {

        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => $e instanceof PDOException
                ? 'Database error while saving the command.'
                : $e->getMessage()
        ]);
    }

    exit();
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manual Control - CT-ABS</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --primary: #8BC34A;
            --primary-dark: #7CB342;
            --accent: #4CAF50;
            --bg: #F5F5F5;
            --card-bg: #FFFFFF;
            --text: #424242;
            --text-light: #757575;
            --border: #E0E0E0;
            --success: #22c55e;
            --danger: #ef4444;
        }

        [data-theme="dark"] {
            --bg: #1a1a1a;
            --card-bg: #2d2d2d;
            --text: #E8E8E8;
            --text-light: #B0B0B0;
            --border: #404040;
        }

        * {
            font-family: 'Inter', sans-serif;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            transition: background 0.3s, color 0.3s;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.04);
        }

        .btn {
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.2s;
            cursor: pointer;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #388E3C;
        }

        .btn-secondary {
            background: var(--card-bg);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover:not(:disabled) {
            border-color: var(--primary);
            background: rgba(139,195,74,0.08);
        }

        .status-message {
            display: none;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-weight: 500;
        }

        .status-message.success {
            display: block;
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .status-message.error {
            display: block;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .position-button {
            flex: 1;
            min-width: 100px;
        }

        .theme-toggle {
            border: 1px solid var(--border);
            background: var(--card-bg);
            color: var(--text);
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <!-- NAVIGATION -->
    <nav class="sticky top-0 z-50 px-5 py-4 border-b"
         style="background:var(--card-bg);border-color:var(--border);">

        <div class="max-w-7xl mx-auto flex justify-between items-center gap-4">

            <div class="flex items-center gap-3">

                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white"
                     style="background:linear-gradient(135deg,var(--primary),var(--accent));">
                    <i class="fas fa-recycle text-xl"></i>
                </div>

                <div>
                    <div class="text-xl font-bold">
                        CT-<span style="color:var(--primary);">ABS</span>
                    </div>

                    <div class="text-xs" style="color:var(--text-light);">
                        Manual Control
                    </div>
                </div>

            </div>

            <div class="flex items-center gap-2">

                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <span class="hidden sm:inline">Dashboard</span>
                </a>

                <button class="theme-toggle" id="themeToggle" type="button">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>

            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-5 py-8">

        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-2">
                Manual Controller
            </h1>

            <p style="color:var(--text-light);">
                Control the five servo motors connected to your ESP32-S3.
            </p>
        </div>

        <div id="statusMessage" class="status-message"></div>

        <!-- SYSTEM INFORMATION -->
        <div class="card p-5 mb-8 flex flex-wrap items-center gap-4">

            <div class="w-12 h-12 rounded-xl flex items-center justify-center"
                 style="background:rgba(139,195,74,0.15);color:var(--accent);">
                <i class="fas fa-microchip text-xl"></i>
            </div>

            <div class="flex-1">
                <div class="font-bold">
                    ESP32-S3 Servo Controller
                </div>

                <div class="text-sm" style="color:var(--text-light);">
                    Commands are saved to the database queue.
                    The ESP32 retrieves them through the device API.
                </div>
            </div>

            <span class="text-sm font-semibold" style="color:var(--accent);">
                <i class="fas fa-cloud-arrow-up mr-1"></i>
                Queue Control
            </span>

        </div>

        <!-- FIVE INDIVIDUAL SERVO CONTROLS -->
        <section>

            <div class="mb-5">
                <h2 class="text-xl font-bold flex items-center gap-2">
                    <i class="fas fa-gears" style="color:var(--primary);"></i>
                    Individual Servo Motors
                </h2>

                <p class="text-sm mt-1" style="color:var(--text-light);">
                    Each button controls only its assigned servo.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- SERVO 1 -->
                <div class="card p-6">

                    <h3 class="font-bold text-lg mb-4">Servo #1</h3>

                    <p class="text-sm mb-5" style="color:var(--text-light);">
                        Thin Plastic Shredder | GPIO 38
                    </p>

                    <div class="flex justify-between items-center mb-5">
                        <span class="text-sm">OFF: 0°</span>
                        <span class="text-sm">ON: 90°</span>
                    </div>

                    <div class="flex gap-3">
                        <button class="btn btn-secondary position-button servo-command-button"
                                onclick="sendServoCommand(1, 0)">
                            OFF
                        </button>

                        <button class="btn btn-primary position-button servo-command-button"
                                onclick="sendServoCommand(1, 90)">
                            ON
                        </button>
                    </div>

                </div>

                <!-- SERVO 2 -->
                <div class="card p-6">

                    <h3 class="font-bold text-lg mb-4">Servo #2</h3>

                    <p class="text-sm mb-5" style="color:var(--text-light);">
                        Biodegradable Shredder | GPIO 4
                    </p>

                    <div class="flex justify-between items-center mb-5">
                        <span class="text-sm">OFF: 0°</span>
                        <span class="text-sm">ON: 90°</span>
                    </div>

                    <div class="flex gap-3">
                        <button class="btn btn-secondary position-button servo-command-button"
                                onclick="sendServoCommand(2, 0)">
                            OFF
                        </button>

                        <button class="btn btn-primary position-button servo-command-button"
                                onclick="sendServoCommand(2, 90)">
                            ON
                        </button>
                    </div>

                </div>

                <!-- SERVO 3 -->
                <div class="card p-6">

                    <h3 class="font-bold text-lg mb-4">Servo #3</h3>

                    <p class="text-sm mb-5" style="color:var(--text-light);">
                        Plastic Bottle Flapper (Right) | GPIO 40
                    </p>

                    <div class="flex justify-between items-center mb-5">
                        <span class="text-sm">OFF: 0°</span>
                        <span class="text-sm">ON: 180°</span>
                    </div>

                    <div class="flex gap-3">
                        <button class="btn btn-secondary position-button servo-command-button"
                                onclick="sendServoCommand(3, 0)">
                            OFF
                        </button>

                        <button class="btn btn-primary position-button servo-command-button"
                                onclick="sendServoCommand(3, 180)">
                            ON
                        </button>
                    </div>

                </div>

                <!-- SERVO 4 -->
                <div class="card p-6">

                    <h3 class="font-bold text-lg mb-4">Servo #4</h3>

                    <p class="text-sm mb-5" style="color:var(--text-light);">
                        Reject / Metal Flapper | GPIO 6
                    </p>

                    <div class="flex justify-between items-center mb-5">
                        <span class="text-sm">OFF: 80°</span>
                        <span class="text-sm">ON: 140°</span>
                    </div>

                    <div class="flex gap-3">
                        <button class="btn btn-secondary position-button servo-command-button"
                                onclick="sendServoCommand(4, 80)">
                            OFF
                        </button>

                        <button class="btn btn-primary position-button servo-command-button"
                                onclick="sendServoCommand(4, 140)">
                            ON
                        </button>

                        <button class="btn btn-secondary position-button servo-command-button"
                                onclick="sendServoCommand(4, 0)">
                            0°
                        </button>
                    </div>

                </div>

                <!-- SERVO 5 -->
                <div class="card p-6">

                    <h3 class="font-bold text-lg mb-4">Servo #5</h3>

                    <p class="text-sm mb-5" style="color:var(--text-light);">
                        Left Flapper | GPIO 15
                    </p>

                    <div class="flex justify-between items-center mb-5">
                        <span class="text-sm">OFF: 0°</span>
                        <span class="text-sm">ON: 180°</span>
                    </div>

                    <div class="flex gap-3">
                        <button class="btn btn-secondary position-button servo-command-button"
                                onclick="sendServoCommand(5, 0)">
                            OFF
                        </button>

                        <button class="btn btn-primary position-button servo-command-button"
                                onclick="sendServoCommand(5, 180)">
                            ON
                        </button>
                    </div>

                </div>

            </div>
        </section>

        <!-- IMPORTANT INFORMATION -->
        <div class="card p-5 mt-8">

            <h3 class="font-bold mb-3 flex items-center gap-2">
                <i class="fas fa-circle-info" style="color:var(--primary);"></i>
                Important Information
            </h3>

            <ul class="text-sm space-y-2" style="color:var(--text-light);">

                <li>
                    <i class="fas fa-check mr-2" style="color:var(--success);"></i>
                    Each button sends one command to its selected servo.
                </li>

                <li>
                    <i class="fas fa-check mr-2" style="color:var(--success);"></i>
                    Wait for the ESP32 to process a command before sending another.
                </li>

                <li>
                    <i class="fas fa-info-circle mr-2" style="color:var(--primary);"></i>
                    A queued command does not confirm physical servo movement.
                </li>

            </ul>

        </div>

    </main>

    <!-- FOOTER -->
    <footer class="border-t mt-10 py-6 px-5"
            style="border-color:var(--border);">

        <div class="max-w-7xl mx-auto flex flex-wrap justify-between gap-3 text-sm"
             style="color:var(--text-light);">

            <span>
                &copy; <?php echo date('Y'); ?> CT-ABS - Manual Control
            </span>

            <span>
                <i class="fas fa-lock mr-1"></i>
                Authorized Access
            </span>

        </div>

    </footer>

    <script>
        // ========================================================
        // THEME TOGGLE
        // ========================================================

        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');

        const savedTheme = localStorage.getItem('smartbin-theme') || 'light';

        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', function () {
            const current =
                document.documentElement.getAttribute('data-theme');

            const next = current === 'light' ? 'dark' : 'light';

            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('smartbin-theme', next);

            updateThemeIcon(next);
        });

        function updateThemeIcon(theme) {
            themeIcon.className =
                theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // ========================================================
        // INDIVIDUAL SERVO COMMAND
        // ========================================================

        let requestInProgress = false;

        async function sendServoCommand(servoNumber, targetAngle) {

            if (requestInProgress) {
                return;
            }

            requestInProgress = true;

            const message = document.getElementById('statusMessage');

            const buttons = document.querySelectorAll(
                '.servo-command-button'
            );

            buttons.forEach(button => button.disabled = true);

            message.className = 'status-message';
            message.textContent = '';
            message.style.display = 'none';

            try {

                const response = await fetch(
                    'manualcontrol.php?action=queue_servo',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            servo_number: servoNumber,
                            target_angle: targetAngle
                        })
                    }
                );

                const rawResponse = await response.text();

                let data;

                try {
                    data = JSON.parse(rawResponse);
                } catch (error) {
                    console.error('Invalid server response:', rawResponse);

                    throw new Error(
                        'Invalid server response. Check the Network Response.'
                    );
                }

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.message || 'Unable to queue servo command.'
                    );
                }

                const command = data.commands[0];

                message.className = 'status-message success';

                message.textContent =
                    'Command queued successfully!\n' +
                    'Servo: #' + command.servo_number + '\n' +
                    'Name: ' + command.servo_name + '\n' +
                    'Action: ' + command.command + '\n' +
                    'Angle: ' + command.target_angle + '°\n' +
                    'Command ID: ' + command.command_id;

                message.style.display = 'block';

                message.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });

            } catch (error) {

                message.className = 'status-message error';
                message.textContent = error.message;
                message.style.display = 'block';

            } finally {

                requestInProgress = false;

                buttons.forEach(button => button.disabled = false);

            }
        }

        console.log('CT-ABS Manual Controller ready.');
    </script>

</body>
</html>
