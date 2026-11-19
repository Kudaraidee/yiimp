<?php

namespace app\models;

use yii\db\ActiveRecord;

class HashRenter extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%hashrenter}}';
    }
}
