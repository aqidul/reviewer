<?php
/**
 * Run Chatbot Tables Migration
 * This script creates the required chatbot tables if they don't exist
 */

require_once __DIR__ . '/includes/config.php';

echo "=== ReviewFlow Chatbot Tables Migration ===\n\n";

try {
    // Read the migration SQL file
    $sql = file_get_contents(__DIR__ . '/migrations/chatbot_tables.sql');
    
    if ($sql === false) {
        die("ERROR: Could not read migration file\n");
    }
    
    // Split into individual statements (remove the final SELECT for verification)
    $statements = array_filter(
        array_map('trim', preg_split('/;[\s]*[\n\r]/m', $sql)),
        function($stmt) {
            return !empty($stmt) && stripos($stmt, 'SELECT') !== 0;
        }
    );
    
    echo "Found " . count($statements) . " SQL statements to execute\n\n";
    
    // Execute each statement
    foreach ($statements as $index => $statement) {
        if (empty($statement)) continue;
        
        echo "Executing statement " . ($index + 1) . "...\n";
        
        try {
            $pdo->exec($statement);
            echo "✓ Success\n\n";
        } catch (PDOException $e) {
            // Ignore duplicate key errors on INSERT (already exists)
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "⚠ Skipped (data already exists)\n\n";
            } else {
                echo "✗ Error: " . $e->getMessage() . "\n\n";
            }
        }
    }
    
    // Verify tables exist
    echo "=== Verification ===\n\n";
    
    $tables = ['chatbot_unanswered', 'chatbot_faq'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch();
            echo "✓ Table '$table' exists with {$result['count']} rows\n";
            
            // Show structure
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "  Columns: " . implode(', ', $columns) . "\n\n";
        } catch (PDOException $e) {
            echo "✗ Table '$table' does not exist: " . $e->getMessage() . "\n\n";
        }
    }
    
    echo "\n=== Migration Complete ===\n";
    
} catch (Exception $e) {
    die("FATAL ERROR: " . $e->getMessage() . "\n");
}
?>
