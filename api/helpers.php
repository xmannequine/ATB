<?php
// API helpers for SmartBin - lightweight, framework-free
// Usage: require_once __DIR__ . '/helpers.php';

// Allow CORS for devices (adjust origins in production)
function allow_cors() {
    // Allow all origins for now; lock this down when deploying
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
}

function handle_preflight() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        allow_cors();
        http_response_code(204);
        exit();
    }
}

function get_json_input() {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function send_json($data, $status = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit();
}

function generate_api_key($length = 40) {
    return bin2hex(random_bytes(max(16, (int)($length/2))));
}

function find_device_by_key($pdo, $api_key) {
    $stmt = $pdo->prepare('SELECT * FROM devices WHERE api_key = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$api_key]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function authenticate_device($pdo) {
    // Accept api key from header X-API-KEY or query param
    $apiKey = null;
    $headers = getallheaders();
    if (!empty($headers['X-API-KEY'])) $apiKey = $headers['X-API-KEY'];
    if (!$apiKey && !empty($_SERVER['HTTP_X_API_KEY'])) $apiKey = $_SERVER['HTTP_X_API_KEY'];
    if (!$apiKey && isset($_REQUEST['api_key'])) $apiKey = $_REQUEST['api_key'];

    if (empty($apiKey)) return null;
    return find_device_by_key($pdo, $apiKey);
}

?>
