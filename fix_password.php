<?php
// fix_password_fresh.php - Generate a fresh hash
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'atb';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔐 Generating fresh password hash\n";
    echo "=================================\n\n";
    
    // Generate a NEW hash for "Admin123!"
    $new_password = 'Admin123!';
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    echo "Password: $new_password\n";
    echo "New Hash: $new_hash\n\n";
    
    // Update the admin user
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    $stmt->execute([$new_hash]);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Admin password updated successfully!\n\n";
        
        // Verify it works
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'admin'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if (password_verify($new_password, $result['password_hash'])) {
            echo "✅✅✅ VERIFICATION SUCCESSFUL! ✅✅✅\n\n";
            echo "You can now login with:\n";
            echo "   👤 Username: admin\n";
            echo "   🔑 Password: Admin123!\n";
            echo "   🌐 URL: http://localhost/your-project/login.php\n";
        } else {
            echo "❌ Verification failed! Please try again.\n";
        }
    } else {
        echo "❌ No user updated. Make sure 'admin' exists.\n";
    }
    
} catch(PDOException $e) {
    die("❌ Error: " . $e->getMessage());
}
?>