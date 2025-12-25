<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Algos model
 * 
 * @property int $id
 * @property string $name
 * @property double $profit
 * @property double $rent
 * @property double $factor
 * @property int $overflow
 * @property string $color
 * @property double $speedfactor
 * @property int $port
 * @property int $visible
 * @property int $powlimit_bits
 */
class Algos extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'algos';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 16],
            [['name'], 'unique'],
            [['profit', 'rent', 'factor', 'speedfactor'], 'number'],
            [['overflow', 'port', 'visible', 'powlimit_bits'], 'integer'],
            [['color'], 'string', 'max' => 16],
            [['overflow', 'visible'], 'boolean'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Algorithm Name',
            'profit' => 'Profit',
            'rent' => 'Rent',
            'factor' => 'Factor',
            'overflow' => 'Overflow',
            'color' => 'Color',
            'speedfactor' => 'Speed Factor',
            'port' => 'Port',
            'visible' => 'Visible',
            'powlimit_bits' => 'PoW Limit Bits',
        ];
    }

    /**
     * Get coins using this algorithm
     */
    public function getCoins()
    {
        return $this->hasMany(Coins::class, ['algo' => 'name']);
    }

    /**
     * Get stratums for this algorithm
     */
    public function getStratums()
    {
        return $this->hasMany(Stratums::class, ['algo' => 'name']);
    }

    /**
     * Check if algorithm is visible
     */
    public function isVisible()
    {
        return $this->visible == 1;
    }

}