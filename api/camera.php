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

cameraResponse(['success' => true, 'message' => 'Camera image uploaded']);