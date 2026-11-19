<?php

namespace app\models;

use yii\db\ActiveRecord;

class BenchChips extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bench_chips}}';
    }
}
