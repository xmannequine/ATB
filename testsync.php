<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$stateFile = __DIR__ . DIRECTORY_SEPARATOR . 'testsync-state.json';
$allowedCategories = [
    'plastic_bottle',
    'shredded_thin_plastic',
    'metal_waste',
    'shredded_biodegradable',
    'reject'
];

function sendJson(array $payload, int $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!is_file($stateFile)) {
        sendJson(['success' => true, 'state' => null]);
    }

    $contents = file_get_contents($stateFile);
    if ($contents === false) {
        sendJson(['success' => false, 'error' => 'Unable to read demo state.'], 500);
    }

    $state = json_decode($contents, true);
    if (!is_array($state)) {
        sendJson(['success' => false, 'error' => 'Demo state is invalid.'], 500);
    }

    sendJson(['success' => true, 'state' => $state]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$requestBody = file_get_contents('php://input');
$request = json_decode($requestBody ?: '', true);

if (!is_array($request)
    || !isset($request['category'])
    || !in_array($request['category'], $allowedCategories, true)
    || !isset($request['selectedAt'])
    || !is_string($request['selectedAt'])
) {
    sendJson(['success' => false, 'error' => 'Invalid demo selection.'], 400);
}

$state = [
    'category' => $request['category'],
    'selectedAt' => $request['selectedAt']
];

if (file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
    sendJson(['success' => false, 'error' => 'Unable to save demo state.'], 500);
}

sendJson(['success' => true, 'state' => $state]);
