<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Blocks model
 * 
 * @property int $id
 * @property int $coin_id
 * @property int $userid
 * @property int $workerid
 * @property string $category
 * @property double $difficulty
 * @property double $difficulty_user
 * @property string $blockhash
 * @property int $height
 * @property double $amount
 * @property int $confirmations
 * @property int $time
 * @property string $txhash
 * @property int $segwit
 * @property int $solo
 * @property string $algo
 * @property double $price
 * @property double $effort
 * @property int $coinid Alias for coin_id
 */
class Blocks extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'blocks';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['coin_id', 'coinid', 'userid', 'workerid', 'height', 'confirmations', 'time', 'segwit', 'solo'], 'integer'],
            [['difficulty', 'difficulty_user', 'amount', 'price', 'effort'], 'number'],
            [['category'], 'string', 'max' => 16],
            [['blockhash', 'txhash'], 'string', 'max' => 128],
            [['algo'], 'string', 'max' => 16],
            [['solo'], 'boolean'],
        ];
    }

    /**
     * Get coinid (alias for coin_id)
     */
    public function getCoinid()
    {
        return $this->coin_id;
    }

    /**
     * Set coinid (alias for coin_id)
     */
    public function setCoinid($value)
    {
        $this->coin_id = $value;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'coin_id' => 'Coin ID',
            'coinid' => 'Coin ID',
            'userid' => 'User ID',
            'workerid' => 'Worker ID',
            'category' => 'Category',
            'difficulty' => 'Difficulty',
            'difficulty_user' => 'User Difficulty',
            'blockhash' => 'Block Hash',
            'height' => 'Height',
            'amount' => 'Amount',
            'confirmations' => 'Confirmations',
            'time' => 'Time',
            'txhash' => 'Transaction Hash',
            'segwit' => 'SegWit',
            'solo' => 'Solo Mining',
            'algo' => 'Algorithm',
            'price' => 'Price',
            'effort' => 'Effort %',
        ];
    }

    /**
     * Get coin relation
     */
    public function getCoin()
    {
        return $this->hasOne(Coins::class, ['id' => 'coin_id']);
    }

    /**
     * Get account relation
     */
    public function getAccount()
    {
        return $this->hasOne(Accounts::class, ['id' => 'userid']);
    }

    /**
     * Get worker relation
     */
    public function getWorker()
    {
        return $this->hasOne(Workers::class, ['id' => 'workerid']);
    }

    /**
     * Check if block is confirmed
     */
    public function isConfirmed()
    {
        return $this->category === 'generate' || $this->category === 'immature';
    }

    /**
     * Check if block is orphaned
     */
    public function isOrphaned()
    {
        return $this->category === 'orphan';
    }

}