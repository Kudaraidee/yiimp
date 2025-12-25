<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;
use yii\helpers\Console;

/**
 * Database Schema Validation Controller
 * 
 * Validates that all Active Record models align with the database schema.
 * Checks for missing tables, columns, and indexes.
 * 
 * Usage:
 *   php yii validate-schema/index
 *   php yii validate-schema/index --verbose
 *   php yii validate-schema/index --format=json
 */
class ValidateSchemaController extends Controller
{
    /**
     * @var bool Enable verbose output
     */
    public $verbose = false;

    /**
     * @var string Output format (text|json)
     */
    public $format = 'text';

    /**
     * @var array List of model classes to validate
     */
    private $modelClasses = [
        'app\models\Accounts',
        'app\models\Algos',
        'app\models\Balances',
        'app\models\Balanceuser',
        'app\models\BenchChips',
        'app\models\Benchmarks',
        'app\models\Blocks',
        'app\models\Bookmarks',
        'app\models\Coins',
        'app\models\Earnings',
        'app\models\Hashrate',
        'app\models\Hashrenter',
        'app\models\Hashuser',
        'app\models\Jobs',
        'app\models\Jobsubmits',
        'app\models\MarketHistory',
        'app\models\Markets',
        'app\models\Mining',
        'app\models\Nicehash',
        'app\models\Orders',
        'app\models\Payouts',
        'app\models\Renters',
        'app\models\Rentertxs',
        'app\models\Shares',
        'app\models\Stats',
        'app\models\Stratums',
        'app\models\Workers',
    ];

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['verbose', 'format']);
    }

    /**
     * Validate database schema against Active Record models
     * 
     * @return int Exit code
     */
    public function actionIndex()
    {
        $this->stdout("Database Schema Validation\n", Console::BOLD);
        $this->stdout(str_repeat("=", 80) . "\n\n");

        $db = Yii::$app->db;
        
        // Test database connection
        try {
            $db->open();
            $this->stdout("✓ Database connection successful\n", Console::FG_GREEN);
            $this->stdout("  Database: {$db->dsn}\n\n");
        } catch (\Exception $e) {
            $this->stderr("✗ Database connection failed: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => $db->dsn,
            'summary' => [
                'total_models' => 0,
                'models_ok' => 0,
                'models_with_issues' => 0,
                'missing_tables' => 0,
                'missing_columns' => 0,
                'missing_indexes' => 0,
            ],
            'models' => [],
        ];

        // Validate each model
        foreach ($this->modelClasses as $modelClass) {
            $report['summary']['total_models']++;
            
            if (!class_exists($modelClass)) {
                $this->stdout("⚠ Skipping {$modelClass} (class not found)\n", Console::FG_YELLOW);
                continue;
            }

            $modelReport = $this->validateModel($modelClass, $db);
            $report['models'][] = $modelReport;

            if ($modelReport['has_issues']) {
                $report['summary']['models_with_issues']++;
                $report['summary']['missing_tables'] += count($modelReport['missing_tables']);
                $report['summary']['missing_columns'] += count($modelReport['missing_columns']);
                $report['summary']['missing_indexes'] += count($modelReport['missing_indexes']);
            } else {
                $report['summary']['models_ok']++;
            }
        }

        // Output report
        if ($this->format === 'json') {
            $this->stdout(json_encode($report, JSON_PRETTY_PRINT) . "\n");
        } else {
            $this->outputTextReport($report);
        }

        // Return appropriate exit code
        return $report['summary']['models_with_issues'] > 0 
            ? ExitCode::DATAERR 
            : ExitCode::OK;
    }

    /**
     * Validate a single model against database schema
     * 
     * @param string $modelClass Model class name
     * @param Connection $db Database connection
     * @return array Validation report
     */
    private function validateModel($modelClass, $db)
    {
        $report = [
            'model' => $modelClass,
            'table' => null,
            'has_issues' => false,
            'missing_tables' => [],
            'missing_columns' => [],
            'missing_indexes' => [],
            'extra_columns' => [],
            'column_type_mismatches' => [],
        ];

        try {
            /** @var \yii\db\ActiveRecord $model */
            $model = new $modelClass();
            $tableName = $model::tableName();
            $report['table'] = $tableName;

            // Check if table exists
            $tableSchema = $db->getTableSchema($tableName);
            if ($tableSchema === null) {
                $report['has_issues'] = true;
                $report['missing_tables'][] = $tableName;
                $this->stdout("✗ {$modelClass}: Table '{$tableName}' does not exist\n", Console::FG_RED);
                return $report;
            }

            $this->stdout("✓ {$modelClass} ({$tableName})\n", Console::FG_GREEN);

            // Get model attributes from rules
            $modelAttributes = $this->getModelAttributes($model);
            $dbColumns = array_keys($tableSchema->columns);

            // Check for missing columns
            foreach ($modelAttributes as $attribute) {
                if (!in_array($attribute, $dbColumns)) {
                    $report['has_issues'] = true;
                    $report['missing_columns'][] = $attribute;
                    $this->stdout("  ✗ Missing column: {$attribute}\n", Console::FG_RED);
                }
            }

            // Check for extra columns (in DB but not in model)
            if ($this->verbose) {
                foreach ($dbColumns as $column) {
                    if (!in_array($column, $modelAttributes) && $column !== 'id') {
                        $report['extra_columns'][] = $column;
                        $this->stdout("  ⚠ Extra column in DB: {$column}\n", Console::FG_YELLOW);
                    }
                }
            }

            // Check indexes
            $indexReport = $this->validateIndexes($tableName, $tableSchema, $db);
            if (!empty($indexReport)) {
                $report['missing_indexes'] = $indexReport;
                if ($this->verbose) {
                    foreach ($indexReport as $indexInfo) {
                        $this->stdout("  ⚠ Recommended index: {$indexInfo}\n", Console::FG_YELLOW);
                    }
                }
            }

            if (!$report['has_issues'] && $this->verbose) {
                $this->stdout("  All columns present\n", Console::FG_GREEN);
            }

        } catch (\Exception $e) {
            $report['has_issues'] = true;
            $report['error'] = $e->getMessage();
            $this->stderr("✗ {$modelClass}: Error - {$e->getMessage()}\n", Console::FG_RED);
        }

        return $report;
    }

    /**
     * Get model attributes from rules and property annotations
     * 
     * @param \yii\db\ActiveRecord $model
     * @return array List of attribute names
     */
    private function getModelAttributes($model)
    {
        $attributes = [];

        // Get attributes from rules
        foreach ($model->rules() as $rule) {
            if (is_array($rule) && isset($rule[0])) {
                $attrs = is_array($rule[0]) ? $rule[0] : [$rule[0]];
                foreach ($attrs as $attr) {
                    if (!in_array($attr, $attributes)) {
                        $attributes[] = $attr;
                    }
                }
            }
        }

        // Get attributes from model's attributes() method
        try {
            $modelAttrs = $model->attributes();
            foreach ($modelAttrs as $attr) {
                if (!in_array($attr, $attributes)) {
                    $attributes[] = $attr;
                }
            }
        } catch (\Exception $e) {
            // Ignore if attributes() fails
        }

        return $attributes;
    }

    /**
     * Validate indexes for a table
     * 
     * @param string $tableName Table name
     * @param \yii\db\TableSchema $tableSchema Table schema
     * @param Connection $db Database connection
     * @return array List of recommended indexes
     */
    private function validateIndexes($tableName, $tableSchema, $db)
    {
        $recommendations = [];

        // Get existing indexes
        $existingIndexes = [];
        foreach ($tableSchema->columns as $column) {
            if ($column->isPrimaryKey) {
                $existingIndexes[] = $column->name;
            }
        }

        // Query for additional indexes
        try {
            $sql = "SHOW INDEX FROM `{$tableName}`";
            $indexes = $db->createCommand($sql)->queryAll();
            foreach ($indexes as $index) {
                $existingIndexes[] = $index['Column_name'];
            }
            $existingIndexes = array_unique($existingIndexes);
        } catch (\Exception $e) {
            // Ignore if SHOW INDEX fails
        }

        // Check for common patterns that should have indexes
        $columnsToCheck = [
            'time' => 'Time-based queries',
            'userid' => 'User lookups',
            'workerid' => 'Worker lookups',
            'coinid' => 'Coin lookups',
            'coin_id' => 'Coin lookups',
            'algo' => 'Algorithm filtering',
            'valid' => 'Valid/invalid filtering',
            'category' => 'Category filtering',
            'enable' => 'Enabled/disabled filtering',
        ];

        foreach ($columnsToCheck as $column => $reason) {
            if (isset($tableSchema->columns[$column]) && !in_array($column, $existingIndexes)) {
                $recommendations[] = "{$column} ({$reason})";
            }
        }

        return $recommendations;
    }

    /**
     * Output text format report
     * 
     * @param array $report Validation report
     */
    private function outputTextReport($report)
    {
        $this->stdout("\n" . str_repeat("=", 80) . "\n");
        $this->stdout("VALIDATION SUMMARY\n", Console::BOLD);
        $this->stdout(str_repeat("=", 80) . "\n\n");

        $summary = $report['summary'];
        $this->stdout("Total Models:           {$summary['total_models']}\n");
        $this->stdout("Models OK:              ", Console::FG_GREEN);
        $this->stdout("{$summary['models_ok']}\n");
        
        if ($summary['models_with_issues'] > 0) {
            $this->stdout("Models with Issues:     ", Console::FG_RED);
            $this->stdout("{$summary['models_with_issues']}\n");
            $this->stdout("Missing Tables:         ", Console::FG_RED);
            $this->stdout("{$summary['missing_tables']}\n");
            $this->stdout("Missing Columns:        ", Console::FG_RED);
            $this->stdout("{$summary['missing_columns']}\n");
            
            if ($this->verbose) {
                $this->stdout("Recommended Indexes:    ", Console::FG_YELLOW);
                $this->stdout("{$summary['missing_indexes']}\n");
            }
        } else {
            $this->stdout("\n✓ All models validated successfully!\n", Console::FG_GREEN);
        }

        // Detailed issues
        if ($summary['models_with_issues'] > 0) {
            $this->stdout("\n" . str_repeat("=", 80) . "\n");
            $this->stdout("DETAILED ISSUES\n", Console::BOLD);
            $this->stdout(str_repeat("=", 80) . "\n\n");

            foreach ($report['models'] as $modelReport) {
                if (!$modelReport['has_issues']) {
                    continue;
                }

                $this->stdout("{$modelReport['model']} ({$modelReport['table']})\n", Console::BOLD);

                if (!empty($modelReport['missing_tables'])) {
                    $this->stdout("  Missing Tables:\n", Console::FG_RED);
                    foreach ($modelReport['missing_tables'] as $table) {
                        $this->stdout("    - {$table}\n");
                    }
                }

                if (!empty($modelReport['missing_columns'])) {
                    $this->stdout("  Missing Columns:\n", Console::FG_RED);
                    foreach ($modelReport['missing_columns'] as $column) {
                        $this->stdout("    - {$column}\n");
                    }
                }

                if ($this->verbose && !empty($modelReport['missing_indexes'])) {
                    $this->stdout("  Recommended Indexes:\n", Console::FG_YELLOW);
                    foreach ($modelReport['missing_indexes'] as $index) {
                        $this->stdout("    - {$index}\n");
                    }
                }

                $this->stdout("\n");
            }
        }

        $this->stdout(str_repeat("=", 80) . "\n");
    }
}
