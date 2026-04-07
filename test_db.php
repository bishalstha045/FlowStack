<?php
/**
 * Simple script to test FlowStack Database Connection
 */
require_once __DIR__ . '/backend/config/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "[SUCCESS] Successfully connected to the FlowStack Database.\n\n";
    echo "Environment: " . ($_fs_is_local ? "Local (XAMPP)" : "Production (InfinityFree)") . "\n";
    echo "Database Host: " . DB_HOST . "\n";
    echo "Database Name: " . DB_NAME . "\n";
    
    // Test a basic query
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "\nTables found in database (" . count($tables) . "):\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "[ERROR] Could not connect to the database.\n\n";
    echo "Environment: " . ($_fs_is_local ? "Local (XAMPP)" : "Production (InfinityFree)") . "\n";
    echo "Database Host: " . DB_HOST . "\n";
    echo "Database Name: " . DB_NAME . "\n";
    echo "Database User: " . DB_USER . "\n\n";
    echo "Raw Error Message: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "The database '" . DB_NAME . "' does not exist. You need to create this database in phpMyAdmin.\n";
    } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "Incorrect username/password. XAMPP locally uses 'root' and '' (empty password) by default.\n";
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "MySQL is not running. Please open the XAMPP Control Panel and start MySQL.\n";
    }
}
