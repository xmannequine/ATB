<?php
// =============================================
// manualcontrol.php - SmartBin Manual Control Dashboard
// =============================================

require_once 'config/config.php';
session_start();

// Check if user is logged in (redirect if not)
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get flash messages if any
$flash = getFlash();

// Simulated sensor data (replace with real API calls later)
$sensors = [
    'ultrasonic' => [45, 67, 32, 89, 54],  // cm
    'servo'      => [0, 90, 180, 45, 135]   // degrees
];
$wifi_status = 'connected';
$api_status = 'online';
$mode = 'AUTO'; // or 'MANUAL'

// If POST request, handle command (for demo we just redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In production, you'd send commands to hardware via API
    // For now, we just set a flash message and reload
    setFlash('success', 'Command sent successfully.');
    header('Location: manualcontrol.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Control – SmartBin Dashboard</title>
    
    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Same custom styles as landing.php (copied here for consistency) -->
    <style>
        /* ============================================================
           CUSTOM COLOR PALETTE (same as landing)
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
            --hover-bg: rgba(139, 195, 74, 0.08);
            --danger: #ef4444;
            --success: #22c55e;
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
        }
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: var(--bg);
            min-height: 100vh;
            transition: background 0.3s ease;
        }
        /* Glass cards */
        .glass-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px var(--shadow);
            transition: all 0.3s ease;
        }
        .glass-card-light {
            background: var(--card-bg);
            border: 1px solid var(--border);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            transition: all 0.3s ease;
            padding: 0.5rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 195, 74, 0.25);
        }
        .btn-secondary {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            transition: all 0.3s ease;
            padding: 0.5rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-secondary:hover {
            border-color: var(--primary);
            background: var(--hover-bg);
        }
        .btn-danger {
            background: var(--danger);
            color: white;
            transition: all 0.3s ease;
            padding: 0.5rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.25);
        }
        /* Toggle Switch */
        .toggle {
            position: relative;
            width: 48px;
            height: 26px;
            background: #ccc;
            border-radius: 13px;
            cursor: pointer;
            transition: background 0.3s;
            flex-shrink: 0;
        }
        .toggle.active {
            background: var(--accent);
        }
        .toggle .knob {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 22px;
            height: 22px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .toggle.active .knob {
            transform: translateX(22px);
        }
        /* Status dot */
        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .status-dot.online { background: var(--success); }
        .status-dot.offline { background: var(--danger); }
        .status-dot.warning { background: #f59e0b; }
        /* Slide-up animation */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .slide-up { animation: slideUp 0.4s ease-out forwards; opacity: 0; }
        .delay-1 { animation-delay: 0.05s; }
        .delay-2 { animation-delay: 0.1s; }
        .delay-3 { animation-delay: 0.15s; }
        .delay-4 { animation-delay: 0.2s; }
        /* Servo slider */
        input[type="range"] {
           
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: var(--border);
            outline: none;
            transition: background 0.3s;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--accent);
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        input[type="range"]::-moz-range-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--accent);
            cursor: pointer;
            border: none;
        }
        /* Responsive tweaks */
        @media (max-width: 768px) {
            .grid-cols-3 { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .grid-cols-3 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- ============================================= -->
    <!-- NAVIGATION                                    -->
    <!-- ============================================= -->
    <nav class="fixed top-0 left-0 right-0 z-50 px-6 py-4 bg-[var(--card-bg)]/90 backdrop-blur-md border-b border-[var(--border)]">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[var(--primary)] to-[var(--accent)] flex items-center justify-center shadow-lg shadow-[var(--primary)]/20 overflow-hidden">
                    <img src="logo3.jpg" alt="CT-ABS Logo" class="w-full h-full object-cover">
                </div>
                <span class="text-xl font-bold text-[var(--text)]">CT-<span class="text-[var(--primary)]">ABS</span></span>
                <span class="text-sm text-[var(--text-light)] ml-2 hidden sm:inline">| Manual Control</span>
            </div>
            <div class="flex items-center gap-3">
                <a href="index.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
                <!-- Theme toggle -->
                <button class="theme-toggle" id="themeToggle" style="background:var(--card-bg);border:1px solid var(--border);color:var(--text);padding:0.4rem 0.8rem;border-radius:10px;cursor:pointer;transition:all 0.3s;display:flex;align-items:center;gap:0.3rem;">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- ============================================= -->
    <!-- MAIN CONTENT                                  -->
    <!-- ============================================= -->
    <main class="pt-24 pb-12 px-6 max-w-7xl mx-auto">
        <!-- Flash messages -->
        <?php if (isset($flash['message'])): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Header & Mode Toggle -->
        <div class="flex flex-wrap justify-between items-center gap-4 mb-8 slide-up">
            <div>
                <h1 class="text-3xl font-bold text-[var(--text)]">Manual Controller</h1>
                <p class="text-[var(--text-light)]">Direct hardware control and sensor monitoring</p>
            </div>
            <div class="flex items-center gap-4 bg-[var(--card-bg)] border border-[var(--border)] rounded-xl px-4 py-2">
                <span class="font-semibold text-[var(--text)]">Mode:</span>
                <div class="flex items-center gap-2">
                    <span class="text-sm <?php echo $mode === 'AUTO' ? 'text-[var(--accent)] font-bold' : 'text-[var(--text-light)]'; ?>" id="modeLabelAuto">AUTO</span>
                    <div class="toggle <?php echo $mode === 'MANUAL' ? 'active' : ''; ?>" id="modeToggle" onclick="toggleMode()">
                        <div class="knob"></div>
                    </div>
                    <span class="text-sm <?php echo $mode === 'MANUAL' ? 'text-[var(--accent)] font-bold' : 'text-[var(--text-light)]'; ?>" id="modeLabelManual">MANUAL</span>
                </div>
            </div>
        </div>

        <!-- System Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 slide-up delay-1">
            <div class="glass-card-light rounded-2xl p-5 border border-[var(--border)] flex items-center gap-4">
                <i class="fas fa-wifi text-2xl <?php echo $wifi_status === 'connected' ? 'text-[var(--success)]' : 'text-[var(--danger)]'; ?>"></i>
                <div>
                    <div class="text-sm text-[var(--text-light)]">Wi‑Fi</div>
                    <div class="font-semibold text-[var(--text)] capitalize"><?php echo $wifi_status; ?></div>
                </div>
                <span class="ml-auto status-dot <?php echo $wifi_status === 'connected' ? 'online' : 'offline'; ?>"></span>
            </div>
            <div class="glass-card-light rounded-2xl p-5 border border-[var(--border)] flex items-center gap-4">
                <i class="fas fa-cloud text-2xl <?php echo $api_status === 'online' ? 'text-[var(--success)]' : 'text-[var(--danger)]'; ?>"></i>
                <div>
                    <div class="text-sm text-[var(--text-light)]">API</div>
                    <div class="font-semibold text-[var(--text)] capitalize"><?php echo $api_status; ?></div>
                </div>
                <span class="ml-auto status-dot <?php echo $api_status === 'online' ? 'online' : 'offline'; ?>"></span>
            </div>
            <div class="glass-card-light rounded-2xl p-5 border border-[var(--border)] flex items-center gap-4">
                <i class="fas fa-microchip text-2xl text-[var(--primary)]"></i>
                <div>
                    <div class="text-sm text-[var(--text-light)]">Controller</div>
                    <div class="font-semibold text-[var(--text)]">ESP32</div>
                </div>
                <span class="ml-auto status-dot online"></span>
            </div>
        </div>

        <!-- Ultrasonic Sensors -->
        <div class="mb-10 slide-up delay-2">
            <h2 class="text-xl font-bold text-[var(--text)] mb-4 flex items-center gap-2">
                <i class="fas fa-ruler-vertical text-[var(--primary)]"></i> Ultrasonic Sensors
                <span class="text-sm font-normal text-[var(--text-light)] ml-2">(distance in cm)</span>
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="glass-card-light rounded-xl p-4 border border-[var(--border)] text-center">
                        <div class="text-xs text-[var(--text-light)] uppercase tracking-wider">Sensor #<?php echo $i+1; ?></div>
                        <div class="text-2xl font-bold text-[var(--text)] mt-1"><?php echo $sensors['ultrasonic'][$i]; ?></div>
                        <div class="text-xs text-[var(--text-light)]">cm</div>
                        <!-- Fake bar to show fill level -->
                        <div class="w-full h-1.5 bg-[var(--border)] rounded-full mt-2 overflow-hidden">
                            <div class="h-full bg-[var(--primary)] rounded-full" style="width: <?php echo min(100, $sensors['ultrasonic'][$i] * 1.2); ?>%;"></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Servo Controls -->
        <div class="mb-10 slide-up delay-3">
            <h2 class="text-xl font-bold text-[var(--text)] mb-4 flex items-center gap-2">
                <i class="fas fa-cogs text-[var(--primary)]"></i> Servo Motors
                <span class="text-sm font-normal text-[var(--text-light)] ml-2">(manual override)</span>
            </h2>
            <form method="POST" action="" id="servoForm">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                        <div class="glass-card-light rounded-2xl p-5 border border-[var(--border)]">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="font-semibold text-[var(--text)]">Servo #<?php echo $i+1; ?></h3>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-[var(--text-light)]">ON</span>
                                    <input type="checkbox" class="servo-toggle" data-servo="<?php echo $i; ?>" <?php echo $sensors['servo'][$i] > 0 ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <input type="range" min="0" max="180" value="<?php echo $sensors['servo'][$i]; ?>" class="servo-slider flex-1" data-servo="<?php echo $i; ?>">
                                <span class="servo-value text-sm font-mono text-[var(--text)] w-10 text-center"><?php echo $sensors['servo'][$i]; ?>°</span>
                            </div>
                            <!-- Hidden input to send value -->
                            <input type="hidden" name="servo_<?php echo $i; ?>" value="<?php echo $sensors['servo'][$i]; ?>">
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="mt-6 flex flex-wrap gap-4">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Apply Servo Positions
                    </button>
                    <button type="button" class="btn-secondary" onclick="resetServos()">
                        <i class="fas fa-undo"></i> Reset to 0°
                    </button>
                    <button type="button" class="btn-secondary" onclick="setAllServos(90)">
                        <i class="fas fa-arrows-spin"></i> Set All 90°
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick Actions -->
        <div class="slide-up delay-4">
            <h2 class="text-xl font-bold text-[var(--text)] mb-4 flex items-center gap-2">
                <i class="fas fa-bolt text-[var(--primary)]"></i> Quick Actions
            </h2>
            <div class="flex flex-wrap gap-4">
                <button class="btn-danger" onclick="if(confirm('Emergency stop will halt all servos. Continue?')){ emergencyStop(); }">
                    <i class="fas fa-stop-circle"></i> Emergency Stop
                </button>
                <button class="btn-secondary" onclick="testAllSensors()">
                    <i class="fas fa-microchip"></i> Test Sensors
                </button>
                <button class="btn-secondary" onclick="rebootController()">
                    <i class="fas fa-sync-alt"></i> Reboot Controller
                </button>
            </div>
        </div>
    </main>

    <!-- ============================================= -->
    <!-- FOOTER                                        -->
    <!-- ============================================= -->
    <footer class="py-6 px-6 border-t border-[var(--border)] mt-8">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-4 text-sm text-[var(--text-light)]">
            <div class="flex items-center gap-3">
                <span>&copy; <?php echo date('Y'); ?> CT-ABS – Manual Control</span>
            </div>
            <div class="flex items-center gap-4">
                <span><i class="fas fa-lock mr-1"></i> Authorized</span>
                <span><i class="fas fa-code mr-1"></i> v<?php echo APP_VERSION; ?></span>
            </div>
        </div>
    </footer>

    <script>
        // ============================================================
        // Theme Toggle
        // ============================================================
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const savedTheme = localStorage.getItem('smartbin-theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', function() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('smartbin-theme', next);
            updateThemeIcon(next);
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // ============================================================
        // Mode Toggle (AUTO/MANUAL)
        // ============================================================
        let currentMode = '<?php echo $mode; ?>';

        function toggleMode() {
            const toggle = document.getElementById('modeToggle');
            const isManual = toggle.classList.toggle('active');
            currentMode = isManual ? 'MANUAL' : 'AUTO';
            document.getElementById('modeLabelAuto').className = 'text-sm ' + (currentMode === 'AUTO' ? 'text-[var(--accent)] font-bold' : 'text-[var(--text-light)]');
            document.getElementById('modeLabelManual').className = 'text-sm ' + (currentMode === 'MANUAL' ? 'text-[var(--accent)] font-bold' : 'text-[var(--text-light)]');

            // Send mode change to backend (AJAX)
            fetch('api.php?action=set_mode', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mode: currentMode })
            }).then(res => res.json()).then(data => {
                console.log('Mode updated:', data);
            }).catch(err => console.error('Mode update failed:', err));
        }

        // ============================================================
        // Servo Controls
        // ============================================================
        const sliders = document.querySelectorAll('.servo-slider');
        const values = document.querySelectorAll('.servo-value');
        const toggles = document.querySelectorAll('.servo-toggle');
        const hiddenInputs = document.querySelectorAll('input[name^="servo_"]');

        // Update value display and hidden input on slider change
        sliders.forEach((slider, idx) => {
            slider.addEventListener('input', function() {
                const val = this.value;
                values[idx].textContent = val + '°';
                hiddenInputs[idx].value = val;
                // If slider moved, enable the toggle (set to ON)
                if (parseInt(val) > 0) {
                    toggles[idx].checked = true;
                } else {
                    toggles[idx].checked = false;
                }
            });
        });

        // Toggle ON/OFF: set slider to 0 or 90
        toggles.forEach((toggle, idx) => {
            toggle.addEventListener('change', function() {
                if (this.checked) {
                    // Turn on: set to 90° (or last known? we'll set 90)
                    sliders[idx].value = 90;
                    values[idx].textContent = '90°';
                    hiddenInputs[idx].value = 90;
                } else {
                    // Turn off: set to 0°
                    sliders[idx].value = 0;
                    values[idx].textContent = '0°';
                    hiddenInputs[idx].value = 0;
                }
            });
        });

        // Reset all servos to 0°
        function resetServos() {
            sliders.forEach((slider, idx) => {
                slider.value = 0;
                values[idx].textContent = '0°';
                hiddenInputs[idx].value = 0;
                toggles[idx].checked = false;
            });
            // Submit form automatically? We'll just update UI; user can click Apply.
            showToast('All servos set to 0°. Click "Apply" to send.');
        }

        // Set all servos to a given angle
        function setAllServos(angle) {
            sliders.forEach((slider, idx) => {
                slider.value = angle;
                values[idx].textContent = angle + '°';
                hiddenInputs[idx].value = angle;
                toggles[idx].checked = angle > 0;
            });
            showToast('All servos set to ' + angle + '°. Click "Apply" to send.');
        }

        // Emergency stop
        function emergencyStop() {
            sliders.forEach((slider, idx) => {
                slider.value = 0;
                values[idx].textContent = '0°';
                hiddenInputs[idx].value = 0;
                toggles[idx].checked = false;
            });
            // Also send emergency stop command
            fetch('api.php?action=emergency_stop', { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    showToast('Emergency stop activated! All servos stopped.');
                })
                .catch(err => showToast('Error: ' + err.message, 'error'));
        }

        // Test sensors (dummy)
        function testAllSensors() {
            showToast('Testing sensors... (simulated)');
            // In production, call API to trigger sensor test
        }

        // Reboot controller (dummy)
        function rebootController() {
            if (confirm('Reboot the ESP32 controller? This will take a few seconds.')) {
                showToast('Rebooting controller...');
                fetch('api.php?action=reboot', { method: 'POST' })
                    .then(res => res.json())
                    .then(data => showToast('Controller rebooted.'))
                    .catch(err => showToast('Reboot failed.', 'error'));
            }
        }

        // ============================================================
        // Toast notification (simple)
        // ============================================================
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-6 right-6 px-6 py-3 rounded-xl shadow-lg text-white z-50 transition-all duration-500 ' +
                (type === 'error' ? 'bg-red-500' : 'bg-[var(--accent)]');
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }

        // ============================================================
        // Auto-refresh sensor data (every 5 seconds) – simulation
        // ============================================================
        function refreshSensorData() {
            // In production, fetch from API and update DOM
            // For demo, we just randomize a bit
            const sensorEls = document.querySelectorAll('.glass-card-light .text-2xl');
            sensorEls.forEach((el, idx) => {
                if (idx < 5) { // ultrasonic values
                    const newVal = Math.floor(Math.random() * 100) + 10;
                    el.textContent = newVal;
                    // Update bar
                    const bar = el.parentElement.querySelector('.h-full');
                    if (bar) bar.style.width = Math.min(100, newVal * 1.2) + '%';
                }
            });
        }
        // Uncomment to enable auto-refresh:
        // setInterval(refreshSensorData, 5000);

        // ============================================================
        // Form submission override to show toast
        // ============================================================
        document.getElementById('servoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // Collect data
            const data = new FormData(this);
            fetch('manual.php', {
                method: 'POST',
                body: data
            }).then(res => res.text()).then(() => {
                showToast('Servo positions applied successfully!');
            }).catch(err => showToast('Error applying positions', 'error'));
            // For demo, we just show toast and reload after a delay
            // Actually we could do AJAX to api.php, but we'll just reload
            // after showing toast.
            setTimeout(() => {
                location.reload();
            }, 1000);
        });

        console.log('Manual Controller ready.');
    </script>
</body>
</html>