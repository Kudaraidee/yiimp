<?php

namespace yiimp2\tests\unit\database;

use yii\db\Connection;
use Codeception\Test\Unit;
use Faker\Factory as FakerFactory;

/**
 * Property-based test for Database Schema Completeness
 * 
 * **Feature: yiimp2-routing-database-alignment, Property 3: Database Schema Completeness**
 * **Validates: Requirements 2.1, 2.2**
 * 
 * This test verifies that:
 * 1. All model attributes map to database columns
 * 2. No database columns are missing from models
 * 3. Random model instances can be saved without schema errors
 */
class SchemaCompletenessPropertyTest extends Unit
{
    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var \Faker\Generator
     */
    protected $faker;

    protected function _before()
    {
        $this->db = \Yii::$app->db;
        $this->faker = FakerFactory::create();
    }

    /**
     * Property 3: Database Schema Completeness
     * 
     * For any Active Record model in yiimp2, all referenced table columns 
     * should exist in the database schema.
     * 
     * This test generates random model instances and verifies:
     * 1. All model attributes map to database columns
     * 2. No database columns are missing
     * 3. Models can be saved without schema errors
     * 
     * @dataProvider modelProvider
     */
    public function testModelAttributesMapToDatabaseColumns($modelClass, $tableName)
    {
        // Get database columns
        $dbColumns = $this->getDatabaseColumns($tableName);
        
        // Get model attributes (excluding virtual properties)
        $model = new $modelClass();
        $modelAttributes = $this->getModelAttributes($model);
        
        // Verify all model attributes exist as database columns
        foreach ($modelAttributes as $attribute) {
            // Skip virtual properties and relations
            if ($this->isVirtualProperty($model, $attribute)) {
                continue;
            }
            
            $this->assertArrayHasKey(
                $attribute,
                $dbColumns,
                "Model attribute '{$attribute}' in {$modelClass} should exist as a column in table '{$tableName}'"
            );
        }
    }

    /**
     * Test that all database columns are accessible through the model
     * 
     * @dataProvider modelProvider
     */
    public function testAllDatabaseColumnsAccessibleThroughModel($modelClass, $tableName)
    {
        // Get database columns
        $dbColumns = $this->getDatabaseColumns($tableName);
        
        // Get model
        $model = new $modelClass();
        
        // Verify all database columns can be accessed through the model
        foreach ($dbColumns as $columnName => $columnInfo) {
            // Check if model can handle this attribute
            $this->assertTrue(
                $model->hasAttribute($columnName) || $model->canGetProperty($columnName),
                "Database column '{$columnName}' in table '{$tableName}' should be accessible through model {$modelClass}"
            );
        }
    }

    /**
     * Test that random model instances can be created without schema errors
     * 
     * This property test generates random data for models and verifies they can be
     * validated and saved without database schema errors.
     * 
     * @dataProvider modelWithRequiredFieldsProvider
     */
    public function testRandomModelInstancesCanBeCreated($modelClass, $tableName, $requiredFields)
    {
        // Run 10 iterations with random data
        for ($i = 0; $i < 10; $i++) {
            $model = new $modelClass();
            
            // Generate random data for required fields
            foreach ($requiredFields as $field => $type) {
                $model->$field = $this->generateRandomValue($type);
            }
            
            // Validate the model
            $isValid = $model->validate();
            
            if (!$isValid) {
                // If validation fails, it should be due to business logic, not schema issues
                $errors = $model->getErrors();
                foreach ($errors as $attribute => $errorMessages) {
                    // Verify the attribute exists in the database
                    $dbColumns = $this->getDatabaseColumns($tableName);
                    if (!$this->isVirtualProperty($model, $attribute)) {
                        $this->assertArrayHasKey(
                            $attribute,
                            $dbColumns,
                            "Validation error on attribute '{$attribute}' but it doesn't exist in database schema"
                        );
                    }
                }
            }
            
            // Try to save (in a transaction that we'll roll back)
            $transaction = $this->db->beginTransaction();
            try {
                $saved = $model->save(false); // Skip validation since we already validated
                
                if ($saved) {
                    // Verify the record was actually inserted
                    $this->assertNotNull($model->id, "Model should have an ID after save");
                    
                    // Verify we can retrieve it
                    $retrieved = $modelClass::findOne($model->id);
                    $this->assertNotNull($retrieved, "Should be able to retrieve saved model");
                }
                
                $transaction->rollBack();
            } catch (\yii\db\Exception $e) {
                $transaction->rollBack();
                
                // If we get a database exception, it should not be about missing columns
                $this->assertStringNotContainsString(
                    'Unknown column',
                    $e->getMessage(),
                    "Database error should not be about unknown columns: " . $e->getMessage()
                );
                
                $this->assertStringNotContainsString(
                    "doesn't exist",
                    $e->getMessage(),
                    "Database error should not be about non-existent tables: " . $e->getMessage()
                );
            }
        }
    }

    /**
     * Data provider for models and their table names
     */
    public function modelProvider()
    {
        return [
            [\app\models\Coins::class, 'coins'],
            [\app\models\Workers::class, 'workers'],
            [\app\models\Shares::class, 'shares'],
            [\app\models\Blocks::class, 'blocks'],
            [\app\models\Accounts::class, 'accounts'],
            [\app\models\Earnings::class, 'earnings'],
            [\app\models\Payouts::class, 'payouts'],
            [\app\models\Markets::class, 'markets'],
            [\app\models\Algos::class, 'algos'],
        ];
    }

    /**
     * Data provider for models with their required fields
     */
    public function modelWithRequiredFieldsProvider()
    {
        return [
            [
                \app\models\Coins::class,
                'coins',
                [
                    'name' => 'string',
                    'symbol' => 'string',
                ]
            ],
            [
                \app\models\Workers::class,
                'workers',
                [
                    'userid' => 'integer',
                ]
            ],
            [
                \app\models\Accounts::class,
                'accounts',
                [
                    'username' => 'string',
                ]
            ],
        ];
    }

    /**
     * Get database columns for a table
     * 
     * @param string $tableName
     * @return array Column name => column info
     */
    protected function getDatabaseColumns($tableName)
    {
        $schema = $this->db->getTableSchema($tableName);
        
        $this->assertNotNull(
            $schema,
            "Table '{$tableName}' should exist in the database"
        );
        
        $columns = [];
        foreach ($schema->columns as $column) {
            $columns[$column->name] = [
                'type' => $column->type,
                'phpType' => $column->phpType,
                'allowNull' => $column->allowNull,
                'isPrimaryKey' => $column->isPrimaryKey,
            ];
        }
        
        return $columns;
    }

    /**
     * Get model attributes (excluding virtual properties)
     * 
     * @param \yii\db\ActiveRecord $model
     * @return array
     */
    protected function getModelAttributes($model)
    {
        return $model->attributes();
    }

    /**
     * Check if an attribute is a virtual property (not stored in database)
     * 
     * @param \yii\db\ActiveRecord $model
     * @param string $attribute
     * @return bool
     */
    protected function isVirtualProperty($model, $attribute)
    {
        // Check if it's defined in the model's fields() method as excluded
        $fields = $model->fields();
        
        // If the model explicitly excludes it from fields, it's likely virtual
        if (!array_key_exists($attribute, $fields)) {
            return true;
        }
        
        // Check if it's a relation
        try {
            $relation = $model->getRelation($attribute, false);
            if ($relation !== null) {
                return true;
            }
        } catch (\yii\base\InvalidArgumentException $e) {
            // Not a relation
        }
        
        // Check if it has a getter but no database column
        $getter = 'get' . ucfirst($attribute);
        if (method_exists($model, $getter)) {
            $tableName = $model::tableName();
            $schema = $this->db->getTableSchema($tableName);
            if ($schema && !isset($schema->columns[$attribute])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate random value based on type
     * 
     * @param string $type
     * @return mixed
     */
    protected function generateRandomValue($type)
    {
        switch ($type) {
            case 'string':
                return $this->faker->word . '_' . $this->faker->randomNumber(5);
            case 'integer':
                return $this->faker->numberBetween(1, 1000);
            case 'double':
            case 'float':
                return $this->faker->randomFloat(8, 0, 1000);
            case 'boolean':
                return $this->faker->boolean;
            default:
                return $this->faker->word;
        }
    }

    /**
     * Test that primary keys are properly defined in models
     * 
     * @dataProvider modelProvider
     */
    public function testPrimaryKeysAreProperlyDefined($modelClass, $tableName)
    {
        $schema = $this->db->getTableSchema($tableName);
        $model = new $modelClass();
        
        // Get primary key from database
        $dbPrimaryKey = $schema->primaryKey;
        
        // Get primary key from model
        $modelPrimaryKey = $model::primaryKey();
        
        // Verify they match
        $this->assertEquals(
            $dbPrimaryKey,
            $modelPrimaryKey,
            "Primary key definition in model {$modelClass} should match database table '{$tableName}'"
        );
    }

    /**
     * Test that column types in database match model expectations
     * 
     * @dataProvider modelProvider
     */
    public function testColumnTypesMatchModelExpectations($modelClass, $tableName)
    {
        $schema = $this->db->getTableSchema($tableName);
        $model = new $modelClass();
        
        // Get validation rules
        $rules = $model->rules();
        
        foreach ($rules as $rule) {
            if (!is_array($rule) || count($rule) < 2) {
                continue;
            }
            
            $attributes = (array)$rule[0];
            $validator = $rule[1];
            
            foreach ($attributes as $attribute) {
                // Skip virtual properties
                if ($this->isVirtualProperty($model, $attribute)) {
                    continue;
                }
                
                // Check if column exists
                if (!isset($schema->columns[$attribute])) {
                    continue;
                }
                
                $column = $schema->columns[$attribute];
                
                // Verify integer validators match integer columns
                if ($validator === 'integer') {
                    $this->assertContains(
                        $column->phpType,
                        ['integer', 'int'],
                        "Column '{$attribute}' in table '{$tableName}' has integer validator but PHP type is '{$column->phpType}'"
                    );
                }
                
                // Verify number validators match numeric columns
                if ($validator === 'number' || $validator === 'double') {
                    $this->assertContains(
                        $column->phpType,
                        ['double', 'float', 'integer', 'int'],
                        "Column '{$attribute}' in table '{$tableName}' has number validator but PHP type is '{$column->phpType}'"
                    );
                }
                
                // Verify string validators match string columns
                if ($validator === 'string') {
                    $this->assertEquals(
                        'string',
                        $column->phpType,
                        "Column '{$attribute}' in table '{$tableName}' has string validator but PHP type is '{$column->phpType}'"
                    );
                }
            }
        }
    }
}
