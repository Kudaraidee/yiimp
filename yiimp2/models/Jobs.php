<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Jobs model (Rental Orders)
 * 
 * @property int $id
 * @property int $renterid
 * @property int $ready
 * @property int $active
 * @property int $time
 * @property double $price
 * @property double $speed
 * @property double $difficulty
 * @property string $algo
 * @property string $host
 * @property int $port
 * @property string $username
 * @property string $password
 * @property double $percent
 */
class Jobs extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'jobs';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['renterid', 'algo', 'host', 'port', 'username'], 'required'],
            [['renterid', 'ready', 'active', 'time', 'port'], 'integer'],
            [['price', 'speed', 'difficulty', 'percent'], 'number'],
            [['algo'], 'string', 'max' => 16],
            [['host', 'username', 'password'], 'string', 'max' => 1024],
            [['ready', 'active'], 'boolean'],
            [['price'], 'number', 'min' => 0],
            [['speed'], 'number', 'min' => 0],
            [['percent'], 'number', 'min' => 0, 'max' => 100],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'renterid' => 'Renter ID',
            'ready' => 'Ready',
            'active' => 'Active',
            'time' => 'Time',
            'price' => 'Price',
            'speed' => 'Speed (Hashrate)',
            'difficulty' => 'Difficulty',
            'algo' => 'Algorithm',
            'host' => 'Target Host',
            'port' => 'Target Port',
            'username' => 'Username',
            'password' => 'Password',
            'percent' => 'Percent',
        ];
    }

    /**
     * Get renter relation
     */
    public function getRenter()
    {
        return $this->hasOne(Renters::class, ['id' => 'renterid']);
    }

    /**
     * Get job submits relation
     */
    public function getSubmits()
    {
        return $this->hasMany(Jobsubmits::class, ['jobid' => 'id']);
    }

    /**
     * Get hashrate records relation
     */
    public function getHashrenter()
    {
        return $this->hasMany(Hashrenter::class, ['jobid' => 'id']);
    }

    /**
     * Check if job is active
     */
    public function isActive()
    {
        return $this->active == 1;
    }

    /**
     * Check if job is ready
     */
    public function isReady()
    {
        return $this->ready == 1;
    }

    /**
     * Activate job
     */
    public function activate()
    {
        $this->active = 1;
        $this->time = time();
        return $this->save(false);
    }

    /**
     * Deactivate job
     */
    public function deactivate()
    {
        $this->active = 0;
        return $this->save(false);
    }

    /**
     * Calculate total cost based on hashrate and time
     */
    public function calculateCost($duration)
    {
        // Cost = price * speed * duration (in hours)
        return $this->price * $this->speed * ($duration / 3600);
    }

    /**
     * Get time remaining (if there's a duration field, otherwise return null)
     */
    public function getTimeRemaining()
    {
        // This would need additional logic based on how duration is tracked
        // For now, return null as the schema doesn't show a duration field
        return null;
    }

    /**
     * Before save
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->time = time();
                $this->ready = 0;
                $this->active = 0;
            }
            return true;
        }
        return false;
    }
}
