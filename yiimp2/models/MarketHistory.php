<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * MarketHistory model
 * 
 * @property int $id
 * @property int $time
 * @property int $idcoin
 * @property double $price
 * @property double $price2
 * @property double $balance
 * @property int $idmarket
 */
class MarketHistory extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'market_history';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['time', 'idcoin'], 'required'],
            [['time', 'idcoin', 'idmarket'], 'integer'],
            [['price', 'price2', 'balance'], 'number'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'time' => 'Time',
            'idcoin' => 'Coin ID',
            'price' => 'Price',
            'price2' => 'Price 2',
            'balance' => 'Balance',
            'idmarket' => 'Market ID',
        ];
    }

    /**
     * Get coin relation
     */
    public function getCoin()
    {
        return $this->hasOne(Coins::class, ['id' => 'idcoin']);
    }

    /**
     * Get market relation
     */
    public function getMarket()
    {
        return $this->hasOne(Markets::class, ['id' => 'idmarket']);
    }
}
