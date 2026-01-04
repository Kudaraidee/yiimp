<?php

namespace tests\helpers;

/**
 * Helper trait for tests that require database access
 * 
 * Provides methods to check database availability and skip tests
 * when the database is not accessible.
 */
trait DatabaseTestHelper
{
    /**
     * Check if database connection is available
     * 
     * @return bool
     */
    protected function isDatabaseAvailable(): bool
    {
        try {
            if (!\Yii::$app->has('db')) {
                return false;
            }
            
            $db = \Yii::$app->db;
            $db->open();
            
            // Try a simple query to verify connection works
            $db->createCommand('SELECT 1')->queryScalar();
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Require database connection or skip test
     * 
     * Call this at the beginning of tests that require database access.
     * The test will be skipped if database is not available.
     * 
     * @param string $message Optional custom skip message
     */
    protected function requireDatabase(string $message = null): void
    {
        if (!$this->isDatabaseAvailable()) {
            $defaultMessage = 'Database connection not available. This test requires a working database connection.';
            
            // Try to get the actual error for better diagnostics
            try {
                \Yii::$app->db->open();
            } catch (\Exception $e) {
                $defaultMessage .= ' Error: ' . $e->getMessage();
            }
            
            $this->markTestSkipped($message ?? $defaultMessage);
        }
    }
    
    /**
     * Get test database connection
     * 
     * @return \yii\db\Connection
     * @throws \Exception if database not available
     */
    protected function getTestDatabase(): \yii\db\Connection
    {
        if (!$this->isDatabaseAvailable()) {
            throw new \Exception('Database not available for testing');
        }
        
        return \Yii::$app->db;
    }
    
    /**
     * Clean up test data from database
     * 
     * @param string $tableName
     * @param array $conditions
     */
    protected function cleanupTestData(string $tableName, array $conditions = []): void
    {
        if (!$this->isDatabaseAvailable()) {
            return;
        }
        
        try {
            $db = \Yii::$app->db;
            
            if (empty($conditions)) {
                // Don't allow deleting all data without explicit conditions
                return;
            }
            
            $db->createCommand()->delete($tableName, $conditions)->execute();
        } catch (\Exception $e) {
            // Silently fail cleanup - test is already done
        }
    }
    
    /**
     * Check if a specific table exists in the database
     * 
     * @param string $tableName
     * @return bool
     */
    protected function tableExists(string $tableName): bool
    {
        if (!$this->isDatabaseAvailable()) {
            return false;
        }
        
        try {
            $db = \Yii::$app->db;
            $tables = $db->schema->getTableNames();
            return in_array($tableName, $tables);
        } catch (\Exception $e) {
            return false;
        }
    }
}
