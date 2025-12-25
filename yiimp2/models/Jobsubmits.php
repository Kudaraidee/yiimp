<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Jobsubmits model
 * 
 * @property int $id
 * @property int $jobid
 * @property int $time
 * @property int $valid
 * @property int $status
 * @property double $difficulty
 * @property double $amount
 * @property string $algo
 */
class Jobsubmits extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'jobsubmits';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['jobid'], 'required'],
            [['jobid', 'time', 'valid', 'status'], 'integer'],
            [['difficulty', 'amount'], 'number'],
            [['algo'], 'string', 'max' => 16],
            [['valid'], 'boolean'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'jobid' => 'Job ID',
            'time' => 'Time',
            'valid' => 'Valid',
            'status' => 'Status',
            'difficulty' => 'Difficulty',
            'amount' => 'Amount',
            'algo' => 'Algorithm',
        ];
    }

    /**
     * Get job relation
     */
    public function getJob()
    {
        return $this->hasOne(Jobs::class, ['id' => 'jobid']);
    }

    /**
     * Check if submit is valid
     */
    public function isValid()
    {
        return $this->valid == 1;
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
