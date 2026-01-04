<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Balances model
 * 
 * @property int $id
 * @property string $name
 * @property string $symbol
 * @property double $balance
 * @property string $deposit_address
 */
class Balances extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'balances';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'symbol'], 'required'],
            [['balance'], 'number'],
            [['name'], 'string', 'max' => 64],
            [['symbol'], 'string', 'max' => 16],
            [['deposit_address'], 'string', 'max' => 128],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Exchange',
            'symbol' => 'Symbol',
            'balance' => 'Balance',
            'deposit_address' => 'Deposit Address',
        ];
    }
}