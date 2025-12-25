<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "shares".
 *
 * @property int $id
 * @property int $userid
 * @property int $workerid
 * @property int $coinid
 * @property int $valid
 * @property double $difficulty
 * @property int $time
 * @property string $algo
 * @property int $solo
 * @property int $error
 */
class Shares extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shares';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['userid', 'workerid', 'coinid', 'valid', 'time', 'solo', 'error'], 'integer'],
            [['difficulty'], 'number'],
            [['algo'], 'string', 'max' => 32],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'userid' => 'User ID',
            'workerid' => 'Worker ID',
            'coinid' => 'Coin ID',
            'valid' => 'Valid',
            'difficulty' => 'Difficulty',
            'time' => 'Time',
            'algo' => 'Algorithm',
            'solo' => 'Solo',
            'error' => 'Error',
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(Accounts::class, ['id' => 'userid']);
    }

    /**
     * Gets query for [[Worker]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getWorker()
    {
        return $this->hasOne(Workers::class, ['id' => 'workerid']);
    }

    /**
     * Gets query for [[Coin]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCoin()
    {
        return $this->hasOne(Coins::class, ['id' => 'coinid']);
    }
}
