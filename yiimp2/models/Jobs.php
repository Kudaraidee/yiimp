<?php

namespace app\models;

use yii\db\ActiveRecord;

/** Hash power rental jobs (orders placed by renters on the pool). */
class Jobs extends ActiveRecord
{
    public function getRenter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Renters::class, ['id' => 'renterid']);
    }
}
