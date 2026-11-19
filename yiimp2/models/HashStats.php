<?php

namespace app\models;

use yii\db\ActiveRecord;

class HashStats extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%hashstats}}';
    }
}
