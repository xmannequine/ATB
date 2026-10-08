<?php
// Receives the latest JPEG snapshot from the ESP32-CAM.

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Content-Type: application/json; charset=utf-8');

function cameraResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cameraResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$cameraSecretPath = __DIR__ . '/../config/camera_secret.php';
$configuredKey = is_file($cameraSecretPath) ? require $cameraSecretPath : '';
if (!is_string($configuredKey) || $configuredKey === '') {
    cameraResponse(['success' => false, 'message' => 'Camera API key is not configured'], 500);
}

$requestKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($configuredKey === '' || $requestKey === '' || !hash_equals($configuredKey, $requestKey)) {
    cameraResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

require_once __DIR__ . '/../config/config.php';

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($contentType, 'application/json') === 0) {
    $eventPayload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($eventPayload)) {
        cameraResponse(['success' => false, 'message' => 'Invalid JSON event'], 400);
    }

    $event = (string)($eventPayload['event'] ?? '');
    $allowedEvents = ['system_ready', 'camera_error', 'object_detected', 'ai_result', 'ai_failed', 'object_removed'];
    if (!in_array($event, $allowedEvents, true)) {
        cameraResponse(['success' => false, 'message' => 'Invalid camera event'], 422);
    }

    $category = isset($eventPayload['category']) ? trim((string)$eventPayload['category']) : null;
    if ($category !== null && ($category === '' || strlen($category) > 80)) {
        cameraResponse(['success' => false, 'message' => 'Invalid category'], 422);
    }

    $confidence = $eventPayload['confidence'] ?? null;
    if ($confidence !== null && (!is_numeric($confidence) || (float)$confidence < 0 || (float)$confidence > 1)) {
        cameraResponse(['success' => false, 'message' => 'Confidence must be between 0 and 1'], 422);
    }

    $statusByEvent = [
        'system_ready' => 'ready',
        'camera_error' => 'camera_error',
        'object_detected' => 'object_detected',
        'ai_result' => 'waiting_for_removal',
        'ai_failed' => 'classification_failed',
        'object_removed' => 'ready'
    ];
    $detectionAt = in_array($event, ['object_detected', 'ai_result'], true) ? date('Y-m-d H:i:s') : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO camera_status
                (id, camera_status, last_event, category, confidence, detection_at, last_seen, updated_at)
            VALUES (1, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                camera_status = VALUES(camera_status),
                last_event = VALUES(last_event),
                category = COALESCE(VALUES(category), category),
                confidence = COALESCE(VALUES(confidence), confidence),
                detection_at = COALESCE(VALUES(detection_at), detection_at),
                last_seen = NOW(),
                updated_at = NOW()");
        $stmt->execute([
            $statusByEvent[$event],
            $event,
            $category,
            $confidence !== null ? round((float)$confidence, 5) : null,
            $detectionAt
        ]);
    } catch (Throwable $error) {
        error_log('Camera event storage error: ' . $error->getMessage());
        cameraResponse(['success' => false, 'message' => 'Camera event could not be stored'], 500);
    }

    cameraResponse(['success' => true, 'message' => 'Camera event stored']);
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    cameraResponse(['success' => false, 'message' => 'JPEG image field is required'], 400);
}

$upload = $_FILES['image'];
if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    cameraResponse(['success' => false, 'message' => 'Image upload failed'], 400);
}

$maxBytes = 2 * 1024 * 1024;
if (($upload['size'] ?? 0) < 1 || $upload['size'] > $maxBytes || !is_uploaded_file($upload['tmp_name'])) {
    cameraResponse(['success' => false, 'message' => 'Invalid image size'], 422);
}

$imageInfo = @getimagesize($upload['tmp_name']);
if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== 'image/jpeg') {
    cameraResponse(['success' => false, 'message' => 'Only JPEG images are accepted'], 422);
}

$cameraDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'camera';
if (!is_dir($cameraDirectory) && !mkdir($cameraDirectory, 0755, true) && !is_dir($cameraDirectory)) {
    cameraResponse(['success' => false, 'message' => 'Camera upload directory is unavailable'], 500);
}

$temporaryPath = $cameraDirectory . DIRECTORY_SEPARATOR . 'latest-' . bin2hex(random_bytes(8)) . '.tmp';
$latestPath = $cameraDirectory . DIRECTORY_SEPARATOR . 'latest.jpg';

$saved = move_uploaded_file($upload['tmp_name'], $temporaryPath);
if ($saved && DIRECTORY_SEPARATOR === '\\' && is_file($latestPath)) {
    $saved = unlink($latestPath);
}
if ($saved && !rename($temporaryPath, $latestPath)) {
    $saved = false;
}

if (!$saved) {
    if (is_file($temporaryPath)) {
        unlink($temporaryPath);
    }
    cameraResponse(['success' => false, 'message' => 'Could not save latest image'], 500);
}

try {
    $pdo->exec("INSERT INTO camera_status (id, camera_status, last_seen, updated_at)
        VALUES (1, 'ready', NOW(), NOW())
        ON DUPLICATE KEY UPDATE last_seen = NOW(), updated_at = NOW()");
} catch (Throwable $error) {
    error_log('Camera heartbeat storage error: ' . $error->getMessage());
    cameraResponse(['success' => false, 'message' => 'Camera image saved but status could not be updated'], 500);
}

cameraResponse(['success' => true, 'message' => 'Camera image uploaded']);