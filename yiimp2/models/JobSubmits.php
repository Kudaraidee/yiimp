<?php

namespace app\models;

use yii\db\ActiveRecord;

/** Shares submitted against a renting job. */
class JobSubmits extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%jobsubmits}}';
    }
}
