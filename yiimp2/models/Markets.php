<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Markets model
 * 
 * @property int $id
 * @property int $coinid
 * @property string $name
 * @property int $marketid
 * @property double $price
 * @property double $price2
 * @property int $lastsent
 * @property int $lasttraded
 * @property string $message
 * @property int $disabled
 * @property double $balance
 * @property double $ontrade
 * @property int $pricetime
 * @property int $balancetime
 * @property double $txfee
 * @property string $deposit_address
 * @property string $base_coin
 * @property int $priority
 * @property int $deleted
 */
class Markets extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'markets';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['coinid'], 'required'],
            [['coinid', 'marketid', 'lastsent', 'lasttraded', 'disabled', 'pricetime', 'balancetime', 'priority', 'deleted'], 'integer'],
            [['price', 'price2', 'balance', 'ontrade', 'txfee'], 'number'],
            [['name'], 'string', 'max' => 16],
            [['base_coin'], 'string', 'max' => 64],
            [['deposit_address'], 'string', 'max' => 1024],
            [['message'], 'string', 'max' => 2048],
            [['disabled'], 'boolean'],
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
            'name' => 'Exchange Name',
            'marketid' => 'Market ID',
            'price' => 'Price',
            'price2' => 'Price 2',
            'lastsent' => 'Last Sent',
            'lasttraded' => 'Last Traded',
            'message' => 'Message',
            'disabled' => 'Disabled',
            'balance' => 'Balance',
            'ontrade' => 'On Trade',
            'pricetime' => 'Price Time',
            'balancetime' => 'Balance Time',
            'txfee' => 'Transaction Fee',
            'deposit_address' => 'Deposit Address',
            'base_coin' => 'Base Coin',
            'priority' => 'Priority',
            'deleted' => 'Deleted',
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
     * Check if market is active
     */
    public function isActive()
    {
        return $this->disabled == 0;
    }

    /**
     * Check if market data is recent (within last hour)
     */
    public function isRecent()
    {
        return $this->lasttraded && ($this->lasttraded > (time() - 3600));
    }

}