<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * BenchChips model
 * 
 * @property int $id
 * @property string $devicetype
 * @property string $vendorid
 * @property string $chip
 * @property int $year
 * @property double $maxtdp
 * @property double $blake_rate
 * @property double $blake_power
 * @property double $x11_rate
 * @property double $x11_power
 * @property double $sha_rate
 * @property double $sha_power
 * @property double $scrypt_rate
 * @property double $scrypt_power
 * @property double $dag_rate
 * @property double $dag_power
 * @property double $lyra_rate
 * @property double $lyra_power
 * @property double $neo_rate
 * @property double $neo_power
 * @property string $url
 * @property string $features
 * @property string $perfdata
 */
class BenchChips extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'bench_chips';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['chip'], 'required'],
            [['devicetype'], 'string', 'max' => 8],
            [['vendorid'], 'string', 'max' => 12],
            [['chip'], 'string', 'max' => 32],
            [['year'], 'integer', 'min' => 1900, 'max' => 2100],
            [['maxtdp', 'blake_rate', 'blake_power', 'x11_rate', 'x11_power', 
              'sha_rate', 'sha_power', 'scrypt_rate', 'scrypt_power', 
              'dag_rate', 'dag_power', 'lyra_rate', 'lyra_power', 
              'neo_rate', 'neo_power'], 'number'],
            [['url'], 'string', 'max' => 255],
            [['features'], 'string', 'max' => 255],
            [['perfdata'], 'string'],
            [['url'], 'url'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'devicetype' => 'Device Type',
            'vendorid' => 'Vendor ID',
            'chip' => 'Chip Name',
            'year' => 'Year',
            'maxtdp' => 'Max TDP',
            'blake_rate' => 'Blake Rate',
            'blake_power' => 'Blake Power',
            'x11_rate' => 'X11 Rate',
            'x11_power' => 'X11 Power',
            'sha_rate' => 'SHA Rate',
            'sha_power' => 'SHA Power',
            'scrypt_rate' => 'Scrypt Rate',
            'scrypt_power' => 'Scrypt Power',
            'dag_rate' => 'DAG Rate',
            'dag_power' => 'DAG Power',
            'lyra_rate' => 'Lyra Rate',
            'lyra_power' => 'Lyra Power',
            'neo_rate' => 'Neo Rate',
            'neo_power' => 'Neo Power',
            'url' => 'URL',
            'features' => 'Features',
            'perfdata' => 'Performance Data',
        ];
    }

    /**
     * Get benchmarks relation
     */
    public function getBenchmarks()
    {
        return $this->hasMany(Benchmarks::class, ['idchip' => 'id']);
    }

    /**
     * Get device type display name
     */
    public function getDeviceTypeDisplay()
    {
        $types = [
            'gpu' => 'GPU',
            'cpu' => 'CPU',
            'asic' => 'ASIC',
            'fpga' => 'FPGA',
        ];
        
        return isset($types[$this->devicetype]) ? $types[$this->devicetype] : strtoupper($this->devicetype);
    }

}
