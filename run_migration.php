<?php
// =============================================
// run_migration.php - Run Database Migration
// =============================================

session_start();
require_once 'config/config.php';

// Check if user is admin
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'admin') {
    die('Access denied. Admin only.');
}

$migrationFile = 'migrate_address.sql';
$sql = file_get_contents($migrationFile);

if (!$sql) {
    die('Migration file not found!');
}

// Split SQL into individual statements
$statements = array_filter(
    array_map('trim', 
        explode(';', $sql)
    )
);

$successCount = 0;
$errorCount = 0;
$errors = [];

try {
    $pdo->beginTransaction();
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            $successCount++;
        } catch (PDOException $e) {
            $errorCount++;
            $errors[] = "Error: " . $e->getMessage() . "\nStatement: " . substr($statement, 0, 100) . "...";
        }
    }
    
    $pdo->commit();
    
    echo "<h1>Migration Complete!</h1>";
    echo "<p>✅ Successful statements: $successCount</p>";
    
    if ($errorCount > 0) {
        echo "<p>❌ Failed statements: $errorCount</p>";
        echo "<pre>" . implode("\n\n", $errors) . "</pre>";
    } else {
        echo "<p>🎉 All migrations completed successfully!</p>";
        echo "<p>Migration finished. Use the configured monitor access link to open the monitor.</p>";
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<h1>Migration Failed!</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>