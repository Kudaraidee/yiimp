<?php

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;

/**
 * Validates stratum database integration
 * 
 * This controller validates that the database schema contains all columns
 * required by the stratum mining server for writing shares, blocks, and workers.
 */
class ValidateStratumController extends Controller
{
    /**
     * Validates stratum database integration
     * @return int Exit code
     */
    public function actionIndex()
    {
        $this->stdout("=== Stratum Database Integration Validation ===\n\n", \yii\helpers\Console::BOLD);
        
        $allValid = true;
        
        // Validate shares table
        $this->stdout("Checking shares table...\n", \yii\helpers\Console::FG_CYAN);
        if (!$this->validateSharesTable()) {
            $allValid = false;
        }
        
        // Validate blocks table
        $this->stdout("\nChecking blocks table...\n", \yii\helpers\Console::FG_CYAN);
        if (!$this->validateBlocksTable()) {
            $allValid = false;
        }
        
        // Validate workers table
        $this->stdout("\nChecking workers table...\n", \yii\helpers\Console::FG_CYAN);
        if (!$this->validateWorkersTable()) {
            $allValid = false;
        }
        
        // Validate supporting tables
        $this->stdout("\nChecking supporting tables...\n", \yii\helpers\Console::FG_CYAN);
        if (!$this->validateSupportingTables()) {
            $allValid = false;
        }
        
        $this->stdout("\n=== Validation Summary ===\n", \yii\helpers\Console::BOLD);
        if ($allValid) {
            $this->stdout("✓ All stratum database requirements validated successfully\n", \yii\helpers\Console::FG_GREEN);
            return ExitCode::OK;
        } else {
            $this->stdout("✗ Some validation checks failed\n", \yii\helpers\Console::FG_RED);
            return ExitCode::DATAERR;
        }
    }
    
    /**
     * Validates shares table has all required columns
     * Based on stratum/share.cpp share_write() function
     */
    private function validateSharesTable()
    {
        $requiredColumns = [
            'userid' => 'int',
            'workerid' => 'int',
            'coinid' => 'int',
            'jobid' => 'int',
            'pid' => 'int',
            'valid' => 'tinyint',
            'extranonce1' => 'tinyint',
            'difficulty' => 'double',
            'share_diff' => 'double',
            'time' => 'int',
            'algo' => 'varchar',
            'error' => 'int',
            'solo' => 'tinyint',
            'blocknumber' => 'int',
        ];
        
        return $this->validateTableColumns('shares', $requiredColumns);
    }
    
    /**
     * Validates blocks table has all required columns
     * Based on stratum/share.cpp block_prune() function
     */
    private function validateBlocksTable()
    {
        $requiredColumns = [
            'height' => 'int',
            'blockhash' => 'varchar',
            'coin_id' => 'int',
            'userid' => 'int',
            'workerid' => 'int',
            'category' => 'varchar',
            'difficulty' => 'double',
            'difficulty_user' => 'double',
            'time' => 'int',
            'algo' => 'varchar',
            'segwit' => 'tinyint',
            'solo' => 'tinyint',
        ];
        
        return $this->validateTableColumns('blocks', $requiredColumns);
    }
    
    /**
     * Validates workers table has all required columns
     * Based on stratum/user.cpp db_add_worker() function
     */
    private function validateWorkersTable()
    {
        $requiredColumns = [
            'id' => 'int',
            'userid' => 'int',
            'ip' => 'varchar',
            'name' => 'varchar',
            'difficulty' => 'double',
            'version' => 'varchar',
            'password' => 'varchar',
            'worker' => 'varchar',
            'algo' => 'varchar',
            'time' => 'int',
            'pid' => 'int',
            'subscribe' => 'tinyint',
        ];
        
        return $this->validateTableColumns('workers', $requiredColumns);
    }
    
    /**
     * Validates supporting tables used by stratum
     */
    private function validateSupportingTables()
    {
        $allValid = true;
        
        // Validate stratums table (db_register_stratum)
        $this->stdout("  Checking stratums table...\n");
        $stratumColumns = [
            'pid' => 'int',
            'time' => 'int',
            'started' => 'int',
            'algo' => 'varchar',
            'url' => 'varchar',
            'port' => 'int',
            'workers' => 'int',
            'fds' => 'int',
            'symbol' => 'varchar',
        ];
        if (!$this->validateTableColumns('stratums', $stratumColumns, false)) {
            $allValid = false;
        }
        
        // Validate benchmarks table (db_store_stats)
        $this->stdout("  Checking benchmarks table...\n");
        $benchmarkColumns = [
            'time' => 'int',
            'algo' => 'varchar',
            'type' => 'varchar',
            'device' => 'varchar',
            'arch' => 'varchar',
            'vendorid' => 'varchar',
            'os' => 'varchar',
            'driver' => 'varchar',
            'client' => 'varchar',
            'khps' => 'double',
            'freq' => 'int',
            'memf' => 'int',
            'realfreq' => 'int',
            'realmemf' => 'int',
            'power' => 'int',
            'plimit' => 'int',
            'intensity' => 'double',
            'throughput' => 'double',
            'userid' => 'int',
        ];
        if (!$this->validateTableColumns('benchmarks', $benchmarkColumns, false)) {
            $allValid = false;
        }
        
        // Validate jobsubmits table (submit_prune)
        $this->stdout("  Checking jobsubmits table...\n");
        $jobsubmitColumns = [
            'jobid' => 'int',
            'valid' => 'tinyint',
            'difficulty' => 'double',
            'time' => 'int',
            'algo' => 'varchar',
            'status' => 'int',
        ];
        if (!$this->validateTableColumns('jobsubmits', $jobsubmitColumns, false)) {
            $allValid = false;
        }
        
        return $allValid;
    }
    
    /**
     * Validates a table has all required columns
     * @param string $tableName Table name
     * @param array $requiredColumns Map of column name => expected type
     * @param bool $verbose Whether to print detailed output
     * @return bool True if all columns exist
     */
    private function validateTableColumns($tableName, $requiredColumns, $verbose = true)
    {
        $db = \Yii::$app->db;
        
        try {
            // Get table schema
            $schema = $db->getTableSchema($tableName);
            
            if (!$schema) {
                $this->stdout("  ✗ Table '$tableName' does not exist\n", \yii\helpers\Console::FG_RED);
                return false;
            }
            
            $allColumnsExist = true;
            $missingColumns = [];
            $typeWarnings = [];
            
            foreach ($requiredColumns as $columnName => $expectedType) {
                $column = $schema->getColumn($columnName);
                
                if (!$column) {
                    $missingColumns[] = $columnName;
                    $allColumnsExist = false;
                } else {
                    // Check type compatibility
                    $actualType = $column->dbType;
                    if (!$this->isTypeCompatible($actualType, $expectedType)) {
                        $typeWarnings[] = "$columnName (expected: $expectedType, actual: $actualType)";
                    }
                }
            }
            
            if ($allColumnsExist && empty($typeWarnings)) {
                if ($verbose) {
                    $this->stdout("  ✓ Table '$tableName' has all required columns (" . count($requiredColumns) . " columns)\n", \yii\helpers\Console::FG_GREEN);
                }
                return true;
            } else {
                if (!empty($missingColumns)) {
                    $this->stdout("  ✗ Table '$tableName' is missing columns:\n", \yii\helpers\Console::FG_RED);
                    foreach ($missingColumns as $col) {
                        $this->stdout("    - $col\n", \yii\helpers\Console::FG_RED);
                    }
                }
                if (!empty($typeWarnings)) {
                    $this->stdout("  ⚠ Table '$tableName' has type mismatches:\n", \yii\helpers\Console::FG_YELLOW);
                    foreach ($typeWarnings as $warning) {
                        $this->stdout("    - $warning\n", \yii\helpers\Console::FG_YELLOW);
                    }
                }
                return false;
            }
            
        } catch (\Exception $e) {
            $this->stdout("  ✗ Error checking table '$tableName': " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return false;
        }
    }
    
    /**
     * Checks if actual database type is compatible with expected type
     * @param string $actualType Actual database type
     * @param string $expectedType Expected type category
     * @return bool True if compatible
     */
    private function isTypeCompatible($actualType, $expectedType)
    {
        $actualType = strtolower($actualType);
        $expectedType = strtolower($expectedType);
        
        // Integer types
        if ($expectedType === 'int') {
            return preg_match('/^(int|integer|bigint|smallint|mediumint|tinyint)/', $actualType);
        }
        
        // String types
        if ($expectedType === 'varchar') {
            return preg_match('/^(varchar|char|text|tinytext|mediumtext|longtext)/', $actualType);
        }
        
        // Floating point types
        if ($expectedType === 'double') {
            return preg_match('/^(double|float|decimal|numeric)/', $actualType);
        }
        
        // Boolean/tinyint types
        if ($expectedType === 'tinyint') {
            return preg_match('/^(tinyint|bool|boolean)/', $actualType);
        }
        
        return false;
    }
}
