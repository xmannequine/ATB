<?php
// logout.php - Log the user out and redirect to the landing page
require_once 'config/config.php';
session_start();

// Log activity if available
if (isLoggedIn()) {
    if (function_exists('logActivity')) {
        logActivity($_SESSION['user_id'] ?? null, 'logout', 'User logged out');
    }
}

// Set a flash message for the next request
setFlash('success', 'You have been logged out successfully.');

// Clear session data and destroy the session cookie
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}

session_destroy();

header('Location: landing.php');
exit();
