<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Hashrenter model
 * 
 * @property int $id
 * @property int $renterid
 * @property int $jobid
 * @property int $time
 * @property double $hashrate
 * @property double $hashrate_bad
 */
class Hashrenter extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'hashrenter';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['renterid', 'jobid'], 'required'],
            [['renterid', 'jobid', 'time'], 'integer'],
            [['hashrate', 'hashrate_bad'], 'number'],
            [['hashrate', 'hashrate_bad'], 'default', 'value' => 0],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'renterid' => 'Renter ID',
            'jobid' => 'Job ID',
            'time' => 'Time',
            'hashrate' => 'Hashrate',
            'hashrate_bad' => 'Bad Hashrate',
        ];
    }

    /**
     * Get renter relation
     */
    public function getRenter()
    {
        return $this->hasOne(Renters::class, ['id' => 'renterid']);
    }

    /**
     * Get job relation
     */
    public function getJob()
    {
        return $this->hasOne(Jobs::class, ['id' => 'jobid']);
    }

    /**
     * Get effective hashrate (good hashrate)
     */
    public function getEffectiveHashrate()
    {
        return max(0, $this->hashrate - $this->hashrate_bad);
    }

    /**
     * Before save
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->time = time();
            }
            return true;
        }
        return false;
    }
}
