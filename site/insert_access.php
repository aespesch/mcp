<?php
/**
 * Automated Access Test Script
 * This script inserts a sequential number into the access_test table
 * Designed to be called every 15 minutes via cron job
 */

// Load environment variables from .ENV file
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        die("ERROR: .ENV file not found at: " . $filePath);
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set as environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Load environment variables
$envFile = __DIR__ . '/.ENV';
loadEnv($envFile);

// Get database credentials from environment
$dbHost = getenv('DB_HOST');
$dbName = getenv('database');
$dbUser = getenv('user');
$dbPass = getenv('pwd');

// Validate required environment variables
if (!$dbHost || !$dbName || !$dbUser || !$dbPass) {
    die("ERROR: Missing required database credentials in .ENV file");
}

// Initialize database connection
$conn = null;

try {
    // Create connection
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to avoid encoding issues
    $conn->set_charset("utf8mb4");
    
    // Start transaction
    $conn->begin_transaction();
    
    // Get the maximum acce_number from the table
    $sql = "SELECT MAX(acce_number) as max_number FROM access_test";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $row = $result->fetch_assoc();
    $maxNumber = $row['max_number'];
    
    // If table is empty, start with 1, otherwise increment
    $newNumber = ($maxNumber === null) ? 1 : ($maxNumber + 1);
    
    // Insert new record with incremented number
    $insertSql = "INSERT INTO access_test (acce_number, acce_date) VALUES (?, NOW())";
    $stmt = $conn->prepare($insertSql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $newNumber);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Success message
    $insertedId = $conn->insert_id;
    echo "SUCCESS: Inserted record #$insertedId with acce_number=$newNumber at " . date('Y-m-d H:i:s') . "\n";
    
    // Close statement
    $stmt->close();
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn && $conn->ping()) {
        $conn->rollback();
    }
    
    // Log error
    error_log("Error in access_test script: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
    
} finally {
    // Close connection
    if ($conn && $conn->ping()) {
        $conn->close();
    }
}
?>
