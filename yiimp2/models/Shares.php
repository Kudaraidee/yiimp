<?php

namespace app\models;

use yii\db\ActiveRecord;

class Shares extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%shares}}';
    }
}
