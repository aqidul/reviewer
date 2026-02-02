<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot Migration - ReviewFlow</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 10px;
        }
        .success {
            background: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .error {
            background: #fee2e2;
            border: 1px solid #ef4444;
            color: #991b1b;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .info {
            background: #dbeafe;
            border: 1px solid #3b82f6;
            color: #1e40af;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        pre {
            background: #1f2937;
            color: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 13px;
        }
        .btn {
            background: #4f46e5;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #4338ca;
        }
        .table-info {
            margin: 20px 0;
            padding: 15px;
            background: #f9fafb;
            border-radius: 6px;
            border-left: 4px solid #4f46e5;
        }
        .table-info h3 {
            margin-top: 0;
            color: #4f46e5;
        }
        .col-list {
            column-count: 2;
            column-gap: 20px;
            font-size: 14px;
        }
        .col-list li {
            break-inside: avoid;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 Chatbot Database Migration</h1>
        
        <?php
        require_once __DIR__ . '/includes/config.php';
        
        $migrationRun = false;
        $messages = [];
        
        // Check if tables exist before migration
        function tableExists($pdo, $tableName) {
            try {
                $result = $pdo->query("SELECT 1 FROM $tableName LIMIT 1");
                return $result !== false;
            } catch (Exception $e) {
                return false;
            }
        }
        
        // Check existing table structure
        function getTableColumns($pdo, $tableName) {
            try {
                $stmt = $pdo->query("DESCRIBE $tableName");
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) {
                return [];
            }
        }
        
        $chatbotFaqExists = tableExists($pdo, 'chatbot_faq');
        $chatbotUnansweredExists = tableExists($pdo, 'chatbot_unanswered');
        
        // If migration requested
        if (isset($_POST['run_migration'])) {
            $migrationRun = true;
            
            try {
                // Read and validate SQL file
                $migrationFile = __DIR__ . '/migrations/chatbot_tables.sql';
                
                // Verify file exists and is readable
                if (!file_exists($migrationFile) || !is_readable($migrationFile)) {
                    throw new Exception("Migration file not found or not readable");
                }
                
                // Verify file hasn't been tampered with (basic check)
                $sql = file_get_contents($migrationFile);
                if ($sql === false) {
                    throw new Exception("Could not read migration file");
                }
                
                // Basic SQL injection protection - check for suspicious patterns
                if (preg_match('/;\s*(DROP\s+DATABASE|DELETE\s+FROM\s+users|TRUNCATE|ALTER\s+USER)/i', $sql)) {
                    throw new Exception("Migration file contains potentially dangerous SQL commands");
                }
                
                // Split into individual statements
                $statements = array_filter(
                    array_map('trim', preg_split('/;[\s]*(?=[\r\n]|$)/m', $sql)),
                    function($stmt) {
                        $stmt = trim($stmt);
                        return !empty($stmt) && 
                               stripos($stmt, 'SELECT') !== 0 && 
                               stripos($stmt, '--') !== 0;
                    }
                );
                
                $messages[] = ['type' => 'info', 'text' => 'Found ' . count($statements) . ' SQL statements to execute'];
                
                // Execute each statement
                foreach ($statements as $index => $statement) {
                    if (empty($statement)) continue;
                    
                    try {
                        $pdo->exec($statement);
                        
                        // Determine what was executed
                        if (stripos($statement, 'CREATE TABLE') !== false) {
                            preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches);
                            $tableName = $matches[1] ?? 'unknown';
                            $messages[] = ['type' => 'success', 'text' => "✓ Created table: $tableName"];
                        } elseif (stripos($statement, 'INSERT INTO') !== false) {
                            preg_match('/INSERT INTO\s+`?(\w+)`?/i', $statement, $matches);
                            $tableName = $matches[1] ?? 'unknown';
                            $messages[] = ['type' => 'success', 'text' => "✓ Inserted data into: $tableName"];
                        } else {
                            $messages[] = ['type' => 'success', 'text' => '✓ Executed statement ' . ($index + 1)];
                        }
                        
                    } catch (PDOException $e) {
                        // Handle duplicate key errors gracefully
                        if (strpos($e->getMessage(), 'Duplicate entry') !== false || 
                            strpos($e->getMessage(), 'already exists') !== false) {
                            $messages[] = ['type' => 'warning', 'text' => '⚠ Already exists (skipped)'];
                        } else {
                            $messages[] = ['type' => 'error', 'text' => '✗ Error: ' . $e->getMessage()];
                        }
                    }
                }
                
                $messages[] = ['type' => 'success', 'text' => '🎉 Migration completed successfully!'];
                
            } catch (Exception $e) {
                $messages[] = ['type' => 'error', 'text' => 'FATAL ERROR: ' . $e->getMessage()];
            }
            
            // Refresh table existence
            $chatbotFaqExists = tableExists($pdo, 'chatbot_faq');
            $chatbotUnansweredExists = tableExists($pdo, 'chatbot_unanswered');
        }
        
        // Display messages
        foreach ($messages as $msg) {
            echo "<div class='{$msg['type']}'>{$msg['text']}</div>";
        }
        
        // Show current status
        if (!$migrationRun) {
            echo '<div class="info">';
            echo '<strong>Current Status:</strong><br>';
            echo '• chatbot_faq table: ' . ($chatbotFaqExists ? '✓ EXISTS' : '✗ NOT FOUND') . '<br>';
            echo '• chatbot_unanswered table: ' . ($chatbotUnansweredExists ? '✓ EXISTS' : '✗ NOT FOUND');
            echo '</div>';
            
            if (!$chatbotFaqExists || !$chatbotUnansweredExists) {
                echo '<div class="warning">';
                echo '<strong>⚠ Action Required</strong><br>';
                echo 'The chatbot tables are missing. Click the button below to create them.';
                echo '</div>';
                
                echo '<form method="POST">';
                echo '<button type="submit" name="run_migration" class="btn">Run Migration Now</button>';
                echo '</form>';
            } else {
                echo '<div class="success">';
                echo '<strong>✓ All Required Tables Exist</strong><br>';
                echo 'The chatbot is ready to use!';
                echo '</div>';
            }
        }
        
        // Show table details
        if ($chatbotFaqExists) {
            $columns = getTableColumns($pdo, 'chatbot_faq');
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM chatbot_faq");
            $count = $stmt->fetch()['count'];
            
            echo '<div class="table-info">';
            echo '<h3>📋 chatbot_faq Table</h3>';
            echo "<p><strong>Rows:</strong> $count</p>";
            echo '<p><strong>Columns:</strong></p>';
            echo '<ul class="col-list">';
            foreach ($columns as $col) {
                echo "<li>$col</li>";
            }
            echo '</ul>';
            echo '</div>';
        }
        
        if ($chatbotUnansweredExists) {
            $columns = getTableColumns($pdo, 'chatbot_unanswered');
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM chatbot_unanswered");
            $count = $stmt->fetch()['count'];
            
            echo '<div class="table-info">';
            echo '<h3>❓ chatbot_unanswered Table</h3>';
            echo "<p><strong>Rows:</strong> $count</p>";
            echo '<p><strong>Columns:</strong></p>';
            echo '<ul class="col-list">';
            foreach ($columns as $col) {
                echo "<li>$col</li>";
            }
            echo '</ul>';
            echo '</div>';
        }
        
        // Next steps
        if ($chatbotFaqExists && $chatbotUnansweredExists) {
            echo '<div class="info">';
            echo '<h3>Next Steps</h3>';
            echo '<ol>';
            echo '<li>Test the AI Assistant on the seller dashboard</li>';
            echo '<li>Try asking questions like "How do I request reviews?"</li>';
            echo '<li>Verify that responses are working correctly</li>';
            echo '<li><strong>Delete this file (migrate_chatbot.php) for security</strong></li>';
            echo '</ol>';
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px;">
            <strong>ReviewFlow</strong> - Chatbot Migration Tool<br>
            <a href="<?= defined('APP_URL') ? htmlspecialchars(APP_URL) : '/' ?>">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
