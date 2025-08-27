<?php
/*
 * Script to add is_agent column to operators table
 * Run this script once to add the missing column
 */

// Include database configuration
include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');

echo "Adding is_agent column to operators table...\n";

try {
    // Check if column already exists
    $sql = "SELECT COUNT(*) as count FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
            AND table_name = 'operators' 
            AND column_name = 'is_agent'";
    
    $res = $dbSocket->query($sql);
    if (DB::isError($res)) {
        die("Error checking column existence: " . $res->getMessage() . "\n");
    }
    
    $row = $res->fetchRow();
    $columnExists = $row[0] > 0;
    
    if ($columnExists) {
        echo "Column 'is_agent' already exists in operators table.\n";
    } else {
        // Add the column
        $sql = "ALTER TABLE operators ADD COLUMN is_agent TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if operator is an agent, 0 otherwise'";
        
        $res = $dbSocket->query($sql);
        if (DB::isError($res)) {
            die("Error adding column: " . $res->getMessage() . "\n");
        }
        
        echo "Successfully added 'is_agent' column to operators table.\n";
    }
    
    echo "Setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

include_once('app/common/includes/db_close.php');
?>