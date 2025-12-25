<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Accounts model
 * 
 * @property int $id
 * @property int $coinid
 * @property int $last_earning
 * @property int $is_locked
 * @property int $no_fees
 * @property int $donation
 * @property int $logtraffic
 * @property double $balance
 * @property string $username
 * @property string $coinsymbol
 * @property int $swap_time
 * @property string $login
 * @property string $hostaddr
 */
class Accounts extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'accounts';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['username'], 'required'],
            [['username'], 'string', 'max' => 128],
            [['username'], 'unique'],
            [['coinid', 'last_earning', 'is_locked', 'no_fees', 'donation', 'logtraffic', 'swap_time'], 'integer'],
            [['balance'], 'number'],
            [['coinsymbol'], 'string', 'max' => 16],
            [['login'], 'string', 'max' => 45],
            [['hostaddr'], 'string', 'max' => 39],
            [['is_locked', 'no_fees', 'logtraffic'], 'boolean'],
            [['donation'], 'integer', 'min' => 0, 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'coinid' => 'Coin ID',
            'last_earning' => 'Last Earning',
            'is_locked' => 'Locked',
            'no_fees' => 'No Fees',
            'donation' => 'Donation %',
            'logtraffic' => 'Log Traffic',
            'balance' => 'Balance',
            'username' => 'Wallet Address',
            'coinsymbol' => 'Coin Symbol',
            'swap_time' => 'Swap Time',
            'login' => 'Login',
            'hostaddr' => 'Host Address',
        ];
    }

    /**
     * Get coin relation
     */
    public function getCoin()
    {
        return $this->hasOne(Coins::class, ['id' => 'coinid']);
    }

    /**
     * Get workers relation
     */
    public function getWorkers()
    {
        return $this->hasMany(Workers::class, ['userid' => 'id']);
    }

    /**
     * Get blocks relation
     */
    public function getBlocks()
    {
        return $this->hasMany(Blocks::class, ['userid' => 'id']);
    }

    /**
     * Get payouts relation
     */
    public function getPayouts()
    {
        return $this->hasMany(Payouts::class, ['account_id' => 'id']);
    }

    /**
     * Get earnings relation
     */
    public function getEarnings()
    {
        return $this->hasMany(Earnings::class, ['userid' => 'id']);
    }

}