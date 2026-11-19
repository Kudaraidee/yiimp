<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\db\ActiveQuery;

class Blocks extends ActiveRecord
{
    public function getCoin(): ActiveQuery
    {
        return $this->hasOne(Coins::class, ['id' => 'coin_id']);
    }
}