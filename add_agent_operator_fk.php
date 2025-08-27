<?php
/*
 * Script to add operator_id foreign key column to agents table
 * This creates a direct relationship between agents and operators
 */

include_once('app/common/includes/config_read.php');

echo "Adding operator_id foreign key column to agents table...\n";

// Database connection
$mysqli = new mysqli(
    $configValues['CONFIG_DB_HOST'], 
    $configValues['CONFIG_DB_USER'], 
    $configValues['CONFIG_DB_PASS'], 
    $configValues['CONFIG_DB_NAME']
);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

try {
    // Check if column already exists
    $result = $mysqli->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                             WHERE TABLE_SCHEMA = '{$configValues['CONFIG_DB_NAME']}' 
                             AND TABLE_NAME = 'agents' 
                             AND COLUMN_NAME = 'operator_id'");
    
    if ($result && $result->num_rows > 0) {
        echo "Column 'operator_id' already exists in agents table.\n";
    } else {
        // Add the column
        $sql = "ALTER TABLE agents ADD COLUMN operator_id INT(11) NULL COMMENT 'Foreign key to operators table'";
        
        if ($mysqli->query($sql)) {
            echo "Successfully added 'operator_id' column to agents table.\n";
            
            // Add foreign key constraint
            $fk_sql = "ALTER TABLE agents ADD CONSTRAINT fk_agent_operator 
                       FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE SET NULL";
            
            if ($mysqli->query($fk_sql)) {
                echo "Successfully added foreign key constraint.\n";
            } else {
                echo "Warning: Could not add foreign key constraint: " . $mysqli->error . "\n";
            }
            
        } else {
            echo "Error adding column: " . $mysqli->error . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$mysqli->close();
echo "Script completed.\n";
?>