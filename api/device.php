<?php
// Simple device REST endpoints for ESP32 integration
// Endpoints:
// POST /api/device.php?action=register -> register device, returns api_key
// POST /api/device.php?action=post_data  -> authenticated by X-API-KEY, stores and processes payload
// GET  /api/device.php?action=get_cmds  -> authenticated, get pending commands (placeholder)

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

allow_cors();
handle_preflight();

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    if (!$action) {
        send_json(['success' => false, 'message' => 'No action specified'], 400);
    }

    if ($action === 'register') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_json(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        // Allow device to register and receive api_key
        $input = get_json_input() ?? $_POST;
        $device_code = trim($input['device_code'] ?? ($input['code'] ?? ''));
        $device_name = trim($input['device_name'] ?? ($input['name'] ?? 'ESP32 Device'));
        $bin_id = isset($input['bin_id']) ? (int)$input['bin_id'] : null;

        if (empty($device_code)) {
            send_json(['success' => false, 'message' => 'device_code is required'], 400);
        }

        // Check if device exists
        $stmt = $pdo->prepare('SELECT * FROM devices WHERE device_code = ? LIMIT 1');
        $stmt->execute([$device_code]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($device) {
            // Return existing key
            send_json(['success' => true, 'message' => 'Device already registered', 'device' => $device]);
        }

        $api_key = generate_api_key(48);

        $stmt = $pdo->prepare('INSERT INTO devices (device_name, device_code, api_key, bin_id, meta, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $meta = json_encode(['registered_from' => $_SERVER['REMOTE_ADDR'] ?? '']);
        $stmt->execute([$device_name, $device_code, $api_key, $bin_id, $meta]);

        $device_id = $pdo->lastInsertId();
        $newDevice = [
            'device_id' => $device_id,
            'device_name' => $device_name,
            'device_code' => $device_code,
            'api_key' => $api_key,
            'bin_id' => $bin_id
        ];

        send_json(['success' => true, 'message' => 'Device registered', 'device' => $newDevice], 201);
    }

    if ($action === 'post_data') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_json(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        // Authenticate device using API key
        $device = authenticate_device($pdo);
        if (!$device) {
            send_json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Read JSON payload
        $payload = get_json_input();
        if ($payload === null) {
            // Fallback to form data
            $payload = $_POST ?: null;
        }

        if (empty($payload)) {
            send_json(['success' => false, 'message' => 'Empty payload'], 400);
        }

        $binType = strtolower(trim((string)($payload['bin_type'] ?? $payload['type'] ?? '')));
        $allowedBinTypes = ['biodegradable', 'plastic', 'metal'];
        $fillLevel = $payload['fill_level'] ?? $payload['fill'] ?? null;
        $weightKg = $payload['weight_kg'] ?? $payload['weight'] ?? null;
        $isDisposal = ($payload['event'] ?? '') === 'disposal' || !empty($payload['disposal']);
        $processed = [];

        if ($binType !== '' && !in_array($binType, $allowedBinTypes, true)) {
            send_json(['success' => false, 'message' => 'Invalid bin_type'], 422);
        }

        if ($fillLevel !== null && (!is_numeric($fillLevel) || (float)$fillLevel < 0 || (float)$fillLevel > 100)) {
            send_json(['success' => false, 'message' => 'fill_level must be between 0 and 100'], 422);
        }

        if ($weightKg !== null && (!is_numeric($weightKg) || (float)$weightKg < 0)) {
            send_json(['success' => false, 'message' => 'weight_kg must be a non-negative number'], 422);
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            send_json(['success' => false, 'message' => 'Payload could not be encoded'], 422);
        }

        $pdo->beginTransaction();

        // Always retain the raw device message for diagnostics and replay.
        $stmt = $pdo->prepare('INSERT INTO device_messages (device_id, payload, ip_address, received_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$device['device_id'], $payloadJson, $_SERVER['REMOTE_ADDR'] ?? '']);
        $messageId = (int)$pdo->lastInsertId();

        // Update the physical bin when this device is linked to one.
        if ($device['bin_id'] && ($fillLevel !== null || $weightKg !== null)) {
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
            $stmt = $pdo->prepare('UPDATE trash_bins SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE bin_id = ?');
            $stmt->execute($values);
            if ($stmt->rowCount() > 0) {
                $processed[] = 'trash_bin';
            }
        }

        // Keep the type-level status used by v_bin_status in sync when a type is supplied.
        if (!$isDisposal && $binType !== '' && ($fillLevel !== null || $weightKg !== null)) {
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
            $stmt = $pdo->prepare('UPDATE bin_status SET ' . implode(', ', $updates) . ', last_updated = NOW() WHERE bin_type = ?');
            $stmt->execute($values);
            if ($stmt->rowCount() > 0) {
                $processed[] = 'bin_status';
            }
        }

        // A waste log is created only for an explicit disposal event, not every sensor heartbeat.
        if ($isDisposal) {
            if ($binType === '' || $weightKg === null) {
                $pdo->rollBack();
                send_json(['success' => false, 'message' => 'Disposal events require bin_type and weight_kg'], 422);
            }

            $stmt = $pdo->prepare('CALL sp_log_waste_disposal(NULL, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $binType,
                round((float)$weightKg, 2),
                isset($payload['size_cm']) && is_numeric($payload['size_cm']) ? round((float)$payload['size_cm'], 2) : null,
                json_encode($payload['sensor_data'] ?? $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                !empty($payload['is_shredded']) ? 1 : 0,
                in_array(($payload['status'] ?? 'success'), ['success', 'failed', 'rejected'], true) ? $payload['status'] : 'success'
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

    if ($action === 'get_cmds') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            send_json(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        $device = authenticate_device($pdo);
        if (!$device) {
            send_json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Placeholder - no queued commands yet
        send_json(['success' => true, 'commands' => []]);
    }

    send_json(['success' => false, 'message' => 'Unknown action'], 400);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('API error: ' . $e->getMessage());
    send_json(['success' => false, 'message' => 'Server error'], 500);
}

?>
