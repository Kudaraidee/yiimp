<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Stratums model
 * 
 * @property int $pid Primary key
 * @property string $algo
 * @property int $port
 * @property int $time
 * @property int $started
 * @property int $workers
 * @property int $fds
 * @property string $symbol
 * @property string $url
 * @property string $host Virtual property (alias for url)
 * @property string $password Virtual property for testing
 * @property int $active Virtual property for testing
 */
class Stratums extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'stratums';
    }

    /**
     * Virtual properties for testing
     */
    private $_host;
    private $_password;
    private $_active;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['algo'], 'string', 'max' => 64],
            [['port', 'pid', 'time', 'started', 'workers', 'fds', 'active'], 'integer'],
            [['symbol'], 'string', 'max' => 16],
            [['url'], 'string', 'max' => 128],
            [['host', 'password'], 'string', 'max' => 256],
            [['active'], 'boolean'],
        ];
    }

    /**
     * Get host (virtual property, alias for url)
     */
    public function getHost()
    {
        return $this->_host ?: $this->url;
    }

    /**
     * Set host (virtual property, alias for url)
     */
    public function setHost($value)
    {
        $this->_host = $value;
        $this->url = $value;
    }

    /**
     * Get password (virtual property)
     */
    public function getPassword()
    {
        return $this->_password;
    }

    /**
     * Set password (virtual property)
     */
    public function setPassword($value)
    {
        $this->_password = $value;
    }

    /**
     * Get active (virtual property)
     */
    public function getActive()
    {
        return $this->_active;
    }

    /**
     * Set active (virtual property)
     */
    public function setActive($value)
    {
        $this->_active = $value;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'pid' => 'Process ID',
            'algo' => 'Algorithm',
            'port' => 'Port',
            'time' => 'Last Activity',
            'started' => 'Started Time',
            'workers' => 'Worker Count',
            'fds' => 'File Descriptors',
            'active' => 'Active',
            'symbol' => 'Symbol',
            'url' => 'URL',
        ];
    }

    /**
     * Get algorithm relation
     */
    public function getAlgo()
    {
        return $this->hasOne(Algos::class, ['name' => 'algo']);
    }

    /**
     * Check if stratum is active
     */
    public function isActive()
    {
        return $this->time && ($this->time > (time() - 300));
    }

    /**
     * Check if stratum process is running
     */
    public function isRunning()
    {
        if (!$this->pid) {
            return false;
        }
        
        // Check if process exists
        return file_exists("/proc/{$this->pid}");
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            // Generate a random PID for testing if not set
            if ($insert && !$this->pid) {
                $this->pid = rand(10000, 99999);
            }
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        $fields = parent::fields();
        // Remove virtual properties from fields
        unset($fields['host'], $fields['password'], $fields['active']);
        return $fields;
    }

}