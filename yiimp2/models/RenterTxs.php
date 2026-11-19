<?php

namespace app\models;

use yii\db\ActiveRecord;

/** Deposit and withdrawal transactions for renters. */
class RenterTxs extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%rentertxs}}';
    }
}
