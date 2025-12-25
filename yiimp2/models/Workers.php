<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Workers model
 * 
 * Database columns:
 * @property int $id
 * @property int $userid
 * @property string $name
 * @property string $worker
 * @property string $algo
 * @property string $ip
 * @property string $dns
 * @property string $nonce1
 * @property string $version
 * @property int $difficulty
 * @property int $subscribe
 * @property string $password
 * @property int $pid
 * @property int $time
 * 
 * Virtual properties (NOT database columns - do not use in queries):
 * @property double $hashrate Virtual property - calculated from shares, not stored in DB
 * @property int $solo Virtual property - not stored in DB
 * @property int $shares Virtual property - not stored in DB
 * @property int $subscribe_time Virtual property - not stored in DB
 * 
 * IMPORTANT: Virtual properties cannot be used in WHERE clauses, SUM(), or other SQL operations.
 * They are only available on loaded model instances.
 */
class Workers extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'workers';
    }

    /**
     * Virtual properties (not in DB, used for testing/display)
     */
    private $_hashrate;
    private $_solo;
    private $_shares;
    private $_subscribe_time;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['userid'], 'required'],
            [['userid', 'difficulty', 'subscribe', 'solo', 'pid', 'time', 'shares', 'subscribe_time'], 'integer'],
            [['hashrate'], 'number'],
            [['name', 'worker', 'nonce1', 'version'], 'string', 'max' => 64],
            [['algo'], 'string', 'max' => 16],
            [['ip'], 'string', 'max' => 32],
            [['dns'], 'string', 'max' => 1024],
            [['password'], 'string', 'max' => 64],
            [['solo'], 'boolean'],
        ];
    }

    /**
     * Get hashrate (virtual property)
     */
    public function getHashrate()
    {
        return $this->_hashrate;
    }

    /**
     * Set hashrate (virtual property)
     */
    public function setHashrate($value)
    {
        $this->_hashrate = $value;
    }

    /**
     * Get solo (virtual property)
     */
    public function getSolo()
    {
        return $this->_solo;
    }

    /**
     * Set solo (virtual property)
     */
    public function setSolo($value)
    {
        $this->_solo = $value;
    }

    /**
     * Get shares (virtual property)
     */
    public function getShares()
    {
        return $this->_shares;
    }

    /**
     * Set shares (virtual property)
     */
    public function setShares($value)
    {
        $this->_shares = $value;
    }

    /**
     * Get subscribe_time (virtual property)
     */
    public function getSubscribe_time()
    {
        return $this->_subscribe_time;
    }

    /**
     * Set subscribe_time (virtual property)
     */
    public function setSubscribe_time($value)
    {
        $this->_subscribe_time = $value;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'userid' => 'User ID',
            'name' => 'Name',
            'worker' => 'Worker Name',
            'algo' => 'Algorithm',
            'ip' => 'IP Address',
            'difficulty' => 'Difficulty',
            'subscribe' => 'Subscribe',
            'password' => 'Password',
            'solo' => 'Solo Mining',
            'pid' => 'Process ID',
            'time' => 'Last Activity',
            'shares' => 'Shares',
            'subscribe_time' => 'Subscribe Time',
        ];
    }

    /**
     * Get account relation
     */
    public function getAccount()
    {
        return $this->hasOne(Accounts::class, ['id' => 'userid']);
    }

    /**
     * Get shares relation
     */
    public function getSharesRecords()
    {
        return $this->hasMany(Shares::class, ['workerid' => 'id']);
    }

    /**
     * Check if worker is active (activity within last 5 minutes)
     */
    public function isActive()
    {
        return $this->time && ($this->time > (time() - 300));
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        $fields = parent::fields();
        // Remove virtual properties from fields
        unset($fields['hashrate'], $fields['solo'], $fields['shares'], $fields['subscribe_time']);
        return $fields;
    }

}