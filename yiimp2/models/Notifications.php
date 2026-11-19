<?php

namespace app\models;

use yii\db\ActiveRecord;

class Notifications extends ActiveRecord
{
    public function getCoin(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Coins::class, ['id' => 'idcoin']);
    }
}
