<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Earnings model
 * 
 * @property int $id
 * @property int $userid
 * @property int $coinid
 * @property int $blockid
 * @property int $create_time
 * @property int $mature_time
 * @property double $amount
 * @property double $price
 * @property int $status
 * @property string $algo Virtual property
 */
class Earnings extends ActiveRecord
{
    /**
     * Virtual property
     */
    private $_algo;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'earnings';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['userid', 'coinid'], 'required'],
            [['userid', 'coinid', 'blockid', 'create_time', 'mature_time', 'status'], 'integer'],
            [['amount', 'price'], 'number'],
            [['algo'], 'string', 'max' => 16],
        ];
    }

    /**
     * Get algo (virtual property)
     */
    public function getAlgo()
    {
        return $this->_algo;
    }

    /**
     * Set algo (virtual property)
     */
    public function setAlgo($value)
    {
        $this->_algo = $value;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'userid' => 'User ID',
            'coinid' => 'Coin ID',
            'blockid' => 'Block ID',
            'create_time' => 'Created',
            'amount' => 'Amount',
            'price' => 'Price',
            'status' => 'Status',
            'algo' => 'Algorithm',
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
     * Get coin relation
     */
    public function getCoin()
    {
        return $this->hasOne(Coins::class, ['id' => 'coinid']);
    }

    /**
     * Get block relation
     */
    public function getBlock()
    {
        return $this->hasOne(Blocks::class, ['id' => 'blockid']);
    }
}