<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Rentertxs model (Renter Transactions)
 * 
 * @property int $id
 * @property int $renterid
 * @property int $time
 * @property double $amount
 * @property string $type
 * @property string $address
 * @property string $tx
 */
class Rentertxs extends ActiveRecord
{
    // Transaction types
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_ORDER = 'order';
    const TYPE_REFUND = 'refund';
    const TYPE_WITHDRAWAL = 'withdrawal';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'rentertxs';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['renterid', 'amount', 'type'], 'required'],
            [['renterid', 'time'], 'integer'],
            [['amount'], 'number'],
            [['type'], 'string', 'max' => 32],
            [['address', 'tx'], 'string', 'max' => 1024],
            [['type'], 'in', 'range' => [self::TYPE_DEPOSIT, self::TYPE_ORDER, self::TYPE_REFUND, self::TYPE_WITHDRAWAL]],
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
            'time' => 'Time',
            'amount' => 'Amount',
            'type' => 'Type',
            'address' => 'Address',
            'tx' => 'Transaction Hash',
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
     * Check if transaction is a deposit
     */
    public function isDeposit()
    {
        return $this->type === self::TYPE_DEPOSIT;
    }

    /**
     * Check if transaction is an order cost
     */
    public function isOrder()
    {
        return $this->type === self::TYPE_ORDER;
    }

    /**
     * Check if transaction is a refund
     */
    public function isRefund()
    {
        return $this->type === self::TYPE_REFUND;
    }

    /**
     * Check if transaction is a withdrawal
     */
    public function isWithdrawal()
    {
        return $this->type === self::TYPE_WITHDRAWAL;
    }

    /**
     * Get formatted amount with sign
     */
    public function getFormattedAmount()
    {
        $sign = ($this->isDeposit() || $this->isRefund()) ? '+' : '-';
        return $sign . number_format($this->amount, 8);
    }

    /**
     * Before save
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->time = time();
            }
            return true;
        }
        return false;
    }

    /**
     * Create deposit transaction
     */
    public static function createDeposit($renterid, $amount, $address = null, $tx = null)
    {
        $transaction = new self();
        $transaction->renterid = $renterid;
        $transaction->amount = $amount;
        $transaction->type = self::TYPE_DEPOSIT;
        $transaction->address = $address;
        $transaction->tx = $tx;
        return $transaction->save();
    }

    /**
     * Create order transaction
     */
    public static function createOrder($renterid, $amount, $jobid = null)
    {
        $transaction = new self();
        $transaction->renterid = $renterid;
        $transaction->amount = $amount;
        $transaction->type = self::TYPE_ORDER;
        $transaction->tx = $jobid ? "Job #$jobid" : null;
        return $transaction->save();
    }

    /**
     * Create refund transaction
     */
    public static function createRefund($renterid, $amount, $reason = null)
    {
        $transaction = new self();
        $transaction->renterid = $renterid;
        $transaction->amount = $amount;
        $transaction->type = self::TYPE_REFUND;
        $transaction->tx = $reason;
        return $transaction->save();
    }
}
