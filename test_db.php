<?php
// test_db.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'atb';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connected successfully!\n\n";
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Users table exists\n";
    } else {
        echo "❌ Users table does NOT exist! Please import the database.\n";
        exit;
    }
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "✅ Admin user found!\n";
        echo "   Username: " . $admin['username'] . "\n";
        echo "   Email: " . $admin['email'] . "\n";
        echo "   Role: " . $admin['role'] . "\n";
        echo "   Active: " . ($admin['is_active'] ? 'Yes' : 'No') . "\n";
        echo "   Password Hash: " . $admin['password_hash'] . "\n";
    } else {
        echo "❌ Admin user NOT found! Please insert the admin user.\n";
    }
    
    // Count total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $count = $stmt->fetch();
    echo "\n📊 Total users in database: " . $count['total'] . "\n";
    
} catch(PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}
?>