<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Benchmarks model
 * 
 * @property int $id
 * @property string $algo
 * @property string $type
 * @property double $khps
 * @property string $device
 * @property string $vendorid
 * @property string $chip
 * @property int $idchip
 * @property string $arch
 * @property int $power
 * @property int $plimit
 * @property int $freq
 * @property int $realfreq
 * @property int $memf
 * @property int $realmemf
 * @property string $client
 * @property string $os
 * @property string $driver
 * @property double $intensity
 * @property int $throughput
 * @property int $userid
 * @property int $time
 */
class Benchmarks extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'benchmarks';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['algo', 'type', 'time'], 'required'],
            [['algo'], 'string', 'max' => 16],
            [['type'], 'string', 'max' => 8],
            [['khps', 'intensity'], 'number'],
            [['device'], 'string', 'max' => 80],
            [['vendorid'], 'string', 'max' => 12],
            [['chip'], 'string', 'max' => 32],
            [['idchip', 'power', 'plimit', 'freq', 'realfreq', 'memf', 'realmemf', 'throughput', 'userid', 'time'], 'integer'],
            [['arch'], 'string', 'max' => 8],
            [['client'], 'string', 'max' => 48],
            [['os'], 'string', 'max' => 8],
            [['driver'], 'string', 'max' => 32],
            [['power', 'plimit', 'freq', 'realfreq', 'memf', 'realmemf', 'throughput'], 'integer', 'min' => 0],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'algo' => 'Algorithm',
            'type' => 'Type',
            'khps' => 'Hashrate (kH/s)',
            'device' => 'Device',
            'vendorid' => 'Vendor ID',
            'chip' => 'Chip',
            'idchip' => 'Chip ID',
            'arch' => 'Architecture',
            'power' => 'Power (W)',
            'plimit' => 'Power Limit',
            'freq' => 'Frequency',
            'realfreq' => 'Real Frequency',
            'memf' => 'Memory Frequency',
            'realmemf' => 'Real Memory Frequency',
            'client' => 'Client',
            'os' => 'OS',
            'driver' => 'Driver',
            'intensity' => 'Intensity',
            'throughput' => 'Throughput',
            'userid' => 'User ID',
            'time' => 'Timestamp',
        ];
    }

    /**
     * Get bench_chip relation
     */
    public function getBenchChip()
    {
        return $this->hasOne(BenchChips::class, ['id' => 'idchip']);
    }

    /**
     * Get user relation
     */
    public function getUser()
    {
        return $this->hasOne(Accounts::class, ['id' => 'userid']);
    }

    /**
     * Get formatted hashrate with unit
     */
    public function getFormattedHashrate()
    {
        if ($this->khps === null) {
            return 'N/A';
        }
        
        if ($this->khps >= 1000000) {
            return number_format($this->khps / 1000000, 2) . ' GH/s';
        } elseif ($this->khps >= 1000) {
            return number_format($this->khps / 1000, 2) . ' MH/s';
        } else {
            return number_format($this->khps, 2) . ' kH/s';
        }
    }

    /**
     * Get formatted power consumption
     */
    public function getFormattedPower()
    {
        if ($this->power === null || $this->power <= 0) {
            return 'N/A';
        }
        
        return $this->power . ' W';
    }

    /**
     * Get efficiency (kH/s per Watt)
     */
    public function getEfficiency()
    {
        if ($this->power === null || $this->power <= 0 || $this->khps === null) {
            return null;
        }
        
        return $this->khps / $this->power;
    }

    /**
     * Get formatted efficiency
     */
    public function getFormattedEfficiency()
    {
        $efficiency = $this->getEfficiency();
        
        if ($efficiency === null) {
            return 'N/A';
        }
        
        return number_format($efficiency, 2) . ' kH/W';
    }

}
