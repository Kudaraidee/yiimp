<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Nicehash model
 * 
 * @property int $id
 * @property int $active
 * @property int $orderid
 * @property int $last_decrease
 * @property string $algo
 * @property double $btc
 * @property double $price
 * @property double $speed
 * @property int $workers
 * @property double $accepted
 * @property double $rejected
 */
class Nicehash extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'nicehash';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['algo'], 'required'],
            [['active', 'orderid', 'last_decrease', 'workers'], 'integer'],
            [['btc', 'price', 'speed', 'accepted', 'rejected'], 'number'],
            [['algo'], 'string', 'max' => 32],
            [['active'], 'boolean'],
            [['btc', 'price', 'speed', 'accepted', 'rejected'], 'default', 'value' => 0],
            [['workers'], 'default', 'value' => 0],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'active' => 'Active',
            'orderid' => 'Order ID',
            'last_decrease' => 'Last Decrease',
            'algo' => 'Algorithm',
            'btc' => 'BTC Balance',
            'price' => 'Price',
            'speed' => 'Speed',
            'workers' => 'Workers',
            'accepted' => 'Accepted Shares',
            'rejected' => 'Rejected Shares',
        ];
    }

    /**
     * Get algorithm relation
     */
    public function getAlgorithm()
    {
        return $this->hasOne(Algos::class, ['name' => 'algo']);
    }

    /**
     * Check if order is active
     */
    public function isActive()
    {
        return (bool) $this->active;
    }

    /**
     * Get formatted hashrate with unit
     */
    public function getFormattedSpeed()
    {
        if ($this->speed === null || $this->speed <= 0) {
            return '0 H/s';
        }
        
        // Speed is typically in H/s, convert to appropriate unit
        if ($this->speed >= 1000000000000) {
            return number_format($this->speed / 1000000000000, 2) . ' TH/s';
        } elseif ($this->speed >= 1000000000) {
            return number_format($this->speed / 1000000000, 2) . ' GH/s';
        } elseif ($this->speed >= 1000000) {
            return number_format($this->speed / 1000000, 2) . ' MH/s';
        } elseif ($this->speed >= 1000) {
            return number_format($this->speed / 1000, 2) . ' kH/s';
        } else {
            return number_format($this->speed, 2) . ' H/s';
        }
    }

    /**
     * Get formatted BTC amount
     */
    public function getFormattedBtc()
    {
        if ($this->btc === null) {
            return '0.00000000 BTC';
        }
        
        return number_format($this->btc, 8) . ' BTC';
    }

    /**
     * Get formatted price
     */
    public function getFormattedPrice()
    {
        if ($this->price === null) {
            return '0.0000';
        }
        
        return number_format($this->price, 4);
    }

    /**
     * Get acceptance rate percentage
     */
    public function getAcceptanceRate()
    {
        $total = $this->accepted + $this->rejected;
        
        if ($total <= 0) {
            return 0;
        }
        
        return ($this->accepted / $total) * 100;
    }

    /**
     * Get formatted acceptance rate
     */
    public function getFormattedAcceptanceRate()
    {
        return number_format($this->getAcceptanceRate(), 2) . '%';
    }

    /**
     * Get total shares submitted
     */
    public function getTotalShares()
    {
        return $this->accepted + $this->rejected;
    }

    /**
     * Calculate earnings based on accepted shares and price
     */
    public function getEstimatedEarnings()
    {
        if ($this->accepted === null || $this->price === null) {
            return 0;
        }
        
        return $this->accepted * $this->price;
    }

    /**
     * Get formatted estimated earnings
     */
    public function getFormattedEstimatedEarnings()
    {
        return number_format($this->getEstimatedEarnings(), 8) . ' BTC';
    }

    /**
     * Find active NiceHash orders
     */
    public static function findActive()
    {
        return static::find()->where(['active' => 1])->all();
    }

    /**
     * Find NiceHash order by algorithm
     */
    public static function findByAlgo($algo)
    {
        return static::find()->where(['algo' => $algo])->one();
    }

    /**
     * Find NiceHash order by order ID
     */
    public static function findByOrderId($orderId)
    {
        return static::find()->where(['orderid' => $orderId])->one();
    }

    /**
     * Get statistics for all NiceHash orders
     */
    public static function getStatistics()
    {
        $orders = static::findActive();
        
        $stats = [
            'total_orders' => count($orders),
            'total_hashrate' => 0,
            'total_workers' => 0,
            'total_accepted' => 0,
            'total_rejected' => 0,
            'total_btc' => 0,
        ];
        
        foreach ($orders as $order) {
            $stats['total_hashrate'] += $order->speed ?? 0;
            $stats['total_workers'] += $order->workers ?? 0;
            $stats['total_accepted'] += $order->accepted ?? 0;
            $stats['total_rejected'] += $order->rejected ?? 0;
            $stats['total_btc'] += $order->btc ?? 0;
        }
        
        return $stats;
    }
}
