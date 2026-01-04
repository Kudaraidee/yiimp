<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Payouts model
 * 
 * @property int $id
 * @property int $account_id
 * @property int $idcoin
 * @property int $time
 * @property double $amount
 * @property double $fee
 * @property string $tx
 * @property string $memoid
 * @property string $errmsg
 * @property int $completed
 * @property string $address Virtual property for testing
 * @property string $memo Virtual property for testing
 * @property int $coinid Alias for idcoin (backward compatibility)
 */
class Payouts extends ActiveRecord
{
    /**
     * Virtual properties for testing
     */
    private $_address;
    private $_memo;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'payouts';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['account_id'], 'required'],
            [['account_id', 'idcoin', 'coinid', 'time', 'completed'], 'integer'],
            [['amount', 'fee'], 'number'],
            [['address', 'tx', 'memoid'], 'string', 'max' => 128],
            [['memo', 'errmsg'], 'string'],
            [['completed'], 'boolean'],
        ];
    }

    /**
     * Get coinid (alias for idcoin for backward compatibility)
     */
    public function getCoinid()
    {
        return $this->idcoin;
    }

    /**
     * Set coinid (alias for idcoin for backward compatibility)
     */
    public function setCoinid($value)
    {
        $this->idcoin = $value;
    }

    /**
     * Get address (virtual property)
     */
    public function getAddress()
    {
        return $this->_address;
    }

    /**
     * Set address (virtual property)
     */
    public function setAddress($value)
    {
        $this->_address = $value;
    }

    /**
     * Get memo (virtual property)
     */
    public function getMemo()
    {
        return $this->_memo;
    }

    /**
     * Set memo (virtual property)
     */
    public function setMemo($value)
    {
        $this->_memo = $value;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'account_id' => 'Account ID',
            'idcoin' => 'Coin ID',
            'coinid' => 'Coin ID',
            'time' => 'Time',
            'amount' => 'Amount',
            'fee' => 'Fee',
            'address' => 'Address',
            'tx' => 'Transaction ID',
            'memoid' => 'Memo ID',
            'errmsg' => 'Error Message',
            'completed' => 'Completed',
            'memo' => 'Memo',
        ];
    }

    /**
     * Get account relation
     */
    public function getAccount()
    {
        return $this->hasOne(Accounts::class, ['id' => 'account_id']);
    }

    /**
     * Get coin relation
     */
    public function getCoin()
    {
        return $this->hasOne(Coins::class, ['id' => 'idcoin']);
    }

    /**
     * Check if payout is completed
     */
    public function isCompleted()
    {
        return $this->completed == 1;
    }

    /**
     * Check if payout is pending
     */
    public function isPending()
    {
        return $this->completed == 0;
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        $fields = parent::fields();
        // Remove virtual properties from fields
        unset($fields['address'], $fields['memo'], $fields['coinid']);
        return $fields;
    }

}