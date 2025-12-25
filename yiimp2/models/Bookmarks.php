<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Bookmarks model
 * 
 * @property int $id
 * @property int $idcoin
 * @property string $label
 * @property string $address
 * @property int $lastused
 */
class Bookmarks extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'bookmarks';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['address', 'idcoin'], 'required'],
            [['idcoin', 'lastused'], 'integer'],
            [['label'], 'string', 'max' => 32],
            [['address'], 'string', 'max' => 128],
            [['lastused'], 'default', 'value' => time()],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'idcoin' => 'Coin ID',
            'label' => 'Label',
            'address' => 'Wallet Address',
            'lastused' => 'Last Used',
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
     * Update last used timestamp
     */
    public function updateLastUsed()
    {
        $this->lastused = time();
        return $this->save(false, ['lastused']);
    }
}
