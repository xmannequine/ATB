
<?php
// ============================================================
// CT-ABS DEVICE REST API
// ============================================================
//
// POST /api/device.php?action=register
// POST /api/device.php?action=post_data
// GET  /api/device.php?action=get_cmds
// POST /api/device.php?action=ack_cmd
//
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

allow_cors();
handle_preflight();

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {

    if (!$action) {
        send_json([
            'success' => false,
            'message' => 'No action specified'
        ], 400);
    }

    // ========================================================
    // REGISTER DEVICE
    // ========================================================

    if ($action === 'register') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_json([
                'success' => false,
                'message' => 'Method not allowed'
            ], 405);
        }

        $input = get_json_input() ?? $_POST;

        $device_code = trim(
            $input['device_code'] ?? ($input['code'] ?? '')
        );

        $device_name = trim(
            $input['device_name'] ?? ($input['name'] ?? 'ESP32 Device')
        );

        $bin_id = isset($input['bin_id'])
            ? (int)$input['bin_id']
            : null;

        if (empty($device_code)) {
            send_json([
                'success' => false,
                'message' => 'device_code is required'
            ], 400);
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM devices WHERE device_code = ? LIMIT 1'
        );

        $stmt->execute([$device_code]);

        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($device) {
            send_json([
                'success' => true,
                'message' => 'Device already registered',
                'device' => $device
            ]);
        }

        $api_key = generate_api_key(48);

        $stmt = $pdo->prepare(
            'INSERT INTO devices
            (device_name, device_code, api_key, bin_id, meta, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())'
        );

        $meta = json_encode([
            'registered_from' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        $stmt->execute([
            $device_name,
            $device_code,
            $api_key,
            $bin_id,
            $meta
        ]);

        $device_id = $pdo->lastInsertId();

        $newDevice = [
            'device_id' => $device_id,
            'device_name' => $device_name,
            'device_code' => $device_code,
            'api_key' => $api_key,
            'bin_id' => $bin_id
        ];

        send_json([
            'success' => true,
            'message' => 'Device registered',
            'device' => $newDevice
        ], 201);
    }

    // ========================================================
    // POST SENSOR DATA
    // ========================================================

    if ($action === 'post_data') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_json([
                'success' => false,
                'message' => 'Method not allowed'
            ], 405);
        }

        $device = authenticate_device($pdo);

        if (!$device) {
            send_json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $payload = get_json_input();

        if ($payload === null) {
            $payload = $_POST ?: null;
        }

        if (empty($payload)) {
            send_json([
                'success' => false,
                'message' => 'Empty payload'
            ], 400);
        }

        $binType = strtolower(trim(
            (string)($payload['bin_type'] ?? $payload['type'] ?? '')
        ));

        $allowedBinTypes = [
            'biodegradable',
            'plastic',
            'metal'
        ];

        $fillLevel = $payload['fill_level'] ?? $payload['fill'] ?? null;
        $weightKg = $payload['weight_kg'] ?? $payload['weight'] ?? null;

        $ultrasonicReadings =
            $payload['ultrasonic_cm'] ?? $payload['ultrasonic'] ?? null;

        $fillLevels = $payload['fill_levels'] ?? null;

        $isDisposal =
            ($payload['event'] ?? '') === 'disposal' ||
            !empty($payload['disposal']);

        $processed = [];

        if (
            $binType !== '' &&
            !in_array($binType, $allowedBinTypes, true)
        ) {
            send_json([
                'success' => false,
                'message' => 'Invalid bin_type'
            ], 422);
        }

        if (
            $fillLevel !== null &&
            (!is_numeric($fillLevel) ||
            (float)$fillLevel < 0 ||
            (float)$fillLevel > 100)
        ) {
            send_json([
                'success' => false,
                'message' => 'fill_level must be between 0 and 100'
            ], 422);
        }

        if (
            $weightKg !== null &&
            (!is_numeric($weightKg) || (float)$weightKg < 0)
        ) {
            send_json([
                'success' => false,
                'message' => 'weight_kg must be a non-negative number'
            ], 422);
        }

        if ($ultrasonicReadings !== null) {

            if (
                !is_array($ultrasonicReadings) ||
                count($ultrasonicReadings) !== 5
            ) {
                send_json([
                    'success' => false,
                    'message' => 'ultrasonic must contain exactly 5 readings'
                ], 422);
            }

            foreach ($ultrasonicReadings as $reading) {

                if ($reading === null) {
                    continue;
                }

                if (
                    !is_numeric($reading) ||
                    (float)$reading < 0
                ) {
                    send_json([
                        'success' => false,
                        'message' => 'ultrasonic readings must be non-negative numbers'
                    ], 422);
                }
            }
        }

        if ($fillLevels !== null) {

            if (
                !is_array($fillLevels) ||
                count($fillLevels) !== 5
            ) {
                send_json([
                    'success' => false,
                    'message' => 'fill_levels must contain exactly 5 values'
                ], 422);
            }

            foreach ($fillLevels as $level) {

                if ($level === null) {
                    continue;
                }

                if (
                    !is_numeric($level) ||
                    (float)$level < 0 ||
                    (float)$level > 100
                ) {
                    send_json([
                        'success' => false,
                        'message' => 'fill_levels must be between 0 and 100'
                    ], 422);
                }
            }
        }

        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($payloadJson === false) {
            send_json([
                'success' => false,
                'message' => 'Payload could not be encoded'
            ], 422);
        }

        $pdo->beginTransaction();

        // Save raw device message.

        $stmt = $pdo->prepare(
            'INSERT INTO device_messages
            (device_id, payload, ip_address, received_at)
            VALUES (?, ?, ?, NOW())'
        );

        $stmt->execute([
            $device['device_id'],
            $payloadJson,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        $messageId = (int)$pdo->lastInsertId();

        // Update linked physical bin.

        if (
            $device['bin_id'] &&
            ($fillLevel !== null || $weightKg !== null)
        ) {

            $updates = [];
            $values = [];

            if ($fillLevel !== null) {
                $updates[] = 'fill_level = ?';
                $values[] = round((float)$fillLevel, 2);
            }

            if ($weightKg !== null) {
                $updates[] = 'weight_kg = ?';
                $values[] = round((float)$weightKg, 2);
            }

            $values[] = (int)$device['bin_id'];

            $stmt = $pdo->prepare(
                'UPDATE trash_bins SET ' .
                implode(', ', $updates) .
                ', updated_at = NOW() WHERE bin_id = ?'
            );

            $stmt->execute($values);

            if ($stmt->rowCount() > 0) {
                $processed[] = 'trash_bin';
            }
        }

        // Update five-bin sensor readings.

        if (is_array($fillLevels)) {

            $stmt = $pdo->query(
                "SELECT bin_id
                 FROM trash_bins
                 WHERE status = 'active'
                 ORDER BY bin_id
                 LIMIT 5"
            );

            $binIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($binIds) !== 5) {

                $pdo->rollBack();

                send_json([
                    'success' => false,
                    'message' => 'Five active bins are required before sensor data can be stored'
                ], 422);
            }

            $stmt = $pdo->prepare(
                'UPDATE trash_bins
                 SET fill_level = ?, updated_at = NOW()
                 WHERE bin_id = ?'
            );

            foreach (array_values($fillLevels) as $sensorIndex => $level) {

                if ($level === null) {
                    continue;
                }

                $stmt->execute([
                    round((float)$level, 2),
                    (int)$binIds[$sensorIndex]
                ]);
            }

            $processed[] = 'five_bin_fill_levels';
        }

        // Update bin status.

        if (
            !$isDisposal &&
            $binType !== '' &&
            ($fillLevel !== null || $weightKg !== null)
        ) {

            $updates = [];
            $values = [];

            if ($fillLevel !== null) {

                $updates[] = 'fill_level = ?';
                $values[] = round((float)$fillLevel, 2);

                $updates[] = 'is_full = ?';
                $values[] = (float)$fillLevel >= 100 ? 1 : 0;
            }

            if ($weightKg !== null) {
                $updates[] = 'weight_kg = ?';
                $values[] = round((float)$weightKg, 2);
            }

            $values[] = $binType;

            $stmt = $pdo->prepare(
                'UPDATE bin_status SET ' .
                implode(', ', $updates) .
                ', last_updated = NOW() WHERE bin_type = ?'
            );

            $stmt->execute($values);

            if ($stmt->rowCount() > 0) {
                $processed[] = 'bin_status';
            }
        }

        // Waste disposal event.

        if ($isDisposal) {

            if ($binType === '' || $weightKg === null) {

                $pdo->rollBack();

                send_json([
                    'success' => false,
                    'message' => 'Disposal events require bin_type and weight_kg'
                ], 422);
            }

            $stmt = $pdo->prepare(
                'CALL sp_log_waste_disposal(NULL, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $binType,
                round((float)$weightKg, 2),

                isset($payload['size_cm']) &&
                is_numeric($payload['size_cm'])
                    ? round((float)$payload['size_cm'], 2)
                    : null,

                json_encode(
                    $payload['sensor_data'] ?? $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),

                !empty($payload['is_shredded']) ? 1 : 0,

                in_array(
                    ($payload['status'] ?? 'success'),
                    ['success', 'failed', 'rejected'],
                    true
                ) ? $payload['status'] : 'success'
            ]);

            $stmt->closeCursor();

            $processed[] = 'waste_log';
        }

        $pdo->commit();

        send_json([
            'success' => true,
            'message' => 'Data accepted',
            'message_id' => $messageId,
            'processed' => $processed
        ]);
    }

    // ========================================================
    // GET PENDING SERVO COMMANDS
    // ========================================================

    if ($action === 'get_cmds') {

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            send_json([
                'success' => false,
                'message' => 'Method not allowed'
            ], 405);
        }

        $device = authenticate_device($pdo);

        if (!$device) {
            send_json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $stmt = $pdo->prepare(
            "SELECT
                command_id,
                servo_number,
                servo_name,
                command,
                target_angle
             FROM servo_commands
             WHERE device_id = ?
               AND status = 'pending'
             ORDER BY command_id ASC
             LIMIT 10"
        );

        $stmt->execute([
            (int)$device['device_id']
        ]);

        $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);

        send_json([
            'success' => true,
            'message' => 'Commands retrieved',
            'commands' => $commands
        ]);
    }

    // ========================================================
    // ACKNOWLEDGE SERVO COMMAND
    // ========================================================

    if ($action === 'ack_cmd') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_json([
                'success' => false,
                'message' => 'Method not allowed'
            ], 405);
        }

        $device = authenticate_device($pdo);

        if (!$device) {
            send_json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $input = get_json_input();

        if (!is_array($input)) {
            send_json([
                'success' => false,
                'message' => 'Invalid JSON payload'
            ], 400);
        }

        $commandId = filter_var(
            $input['command_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $status = $input['status'] ?? '';

        if (!$commandId || $commandId < 1) {
            send_json([
                'success' => false,
                'message' => 'Valid command_id is required'
            ], 422);
        }

        if (!in_array($status, ['executed', 'failed'], true)) {
            send_json([
                'success' => false,
                'message' => 'Invalid command status'
            ], 422);
        }

        // Update only commands belonging to this device.

        $stmt = $pdo->prepare(
            "UPDATE servo_commands
             SET
                status = ?,
                executed_at = CASE
                    WHEN ? = 'executed' THEN NOW()
                    ELSE NULL
                END
             WHERE command_id = ?
               AND device_id = ?
               AND status = 'pending'"
        );

        $stmt->execute([
            $status,
            $status,
            $commandId,
            (int)$device['device_id']
        ]);

        if ($stmt->rowCount() > 0) {

            send_json([
                'success' => true,
                'message' => 'Command acknowledged',
                'command_id' => $commandId,
                'status' => $status
            ]);
        }

        // Check whether already acknowledged.

        $stmt = $pdo->prepare(
            'SELECT status
             FROM servo_commands
             WHERE command_id = ?
               AND device_id = ?
             LIMIT 1'
        );

        $stmt->execute([
            $commandId,
            (int)$device['device_id']
        ]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing && $existing['status'] === $status) {

            send_json([
                'success' => true,
                'message' => 'Command already acknowledged',
                'command_id' => $commandId,
                'status' => $status
            ]);
        }

        send_json([
            'success' => false,
            'message' => 'Command not found or already processed'
        ], 404);
    }

    // ========================================================
    // UNKNOWN ACTION
    // ========================================================

    send_json([
        'success' => false,
        'message' => 'Unknown action'
    ], 400);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('API error: ' . $e->getMessage());

    send_json([
        'success' => false,
        'message' => 'Server error'
    ], 500);
}

?>
