<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Hashstats model
 * 
 * @property int $id
 * @property int $time
 * @property double $hashrate
 * @property double $earnings
 * @property string $algo
 */
class Hashstats extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'hashstats';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['time'], 'integer'],
            [['hashrate', 'earnings'], 'number'],
            [['algo'], 'string', 'max' => 32],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'time' => 'Time',
            'hashrate' => 'Hashrate',
            'earnings' => 'Earnings',
            'algo' => 'Algorithm',
        ];
    }

    /**
     * Get hashstats for a specific algorithm and time range
     * 
     * @param string $algo Algorithm name
     * @param int $timeFrom Start time (unix timestamp)
     * @param int $timeTo End time (unix timestamp)
     * @return array
     */
    public static function getStatsForAlgo($algo, $timeFrom, $timeTo = null)
    {
        $query = static::find()
            ->where(['algo' => $algo])
            ->andWhere(['>=', 'time', $timeFrom]);
        
        if ($timeTo !== null) {
            $query->andWhere(['<=', 'time', $timeTo]);
        }
        
        return $query->orderBy(['time' => SORT_ASC])->all();
    }

    /**
     * Get formatted data for charts
     * 
     * @param string $algo Algorithm name
     * @param int $timeFrom Start time (unix timestamp)
     * @param string $field Field to extract (hashrate, earnings)
     * @param float $factor Conversion factor
     * @return array
     */
    public static function getChartData($algo, $timeFrom, $field = 'hashrate', $factor = 1000000)
    {
        $stats = static::getStatsForAlgo($algo, $timeFrom);
        $data = [];
        
        foreach ($stats as $stat) {
            $value = round($stat->$field / $factor, 3);
            $date = date('Y-m-d H:i:s', $stat->time);
            $data[] = [$date, $value];
        }
        
        return $data;
    }

    /**
     * Get earnings chart data with bitcoin formatting
     * 
     * @param string $algo Algorithm name
     * @param int $timeFrom Start time (unix timestamp)
     * @param float $multiplier Earnings multiplier (e.g., 24 for daily)
     * @return array
     */
    public static function getEarningsChartData($algo, $timeFrom, $multiplier = 24)
    {
        $stats = static::getStatsForAlgo($algo, $timeFrom);
        $data = [];
        
        foreach ($stats as $stat) {
            $earnings = $stat->earnings * $multiplier;
            $date = date('Y-m-d H:i:s', $stat->time);
            $data[] = [$date, $earnings];
        }
        
        return $data;
    }

    /**
     * Get BTC per unit per day chart data
     * 
     * @param string $algo Algorithm name
     * @param int $timeFrom Start time (unix timestamp)
     * @param float $algoUnitFactor Algorithm unit factor
     * @return array
     */
    public static function getBtcPerUnitChartData($algo, $timeFrom, $algoUnitFactor)
    {
        $stats = static::getStatsForAlgo($algo, $timeFrom);
        $data = [];
        
        foreach ($stats as $stat) {
            $value = $stat->hashrate ? 
                ($stat->earnings * 24 * $algoUnitFactor * 1000000 / $stat->hashrate) : 0;
            $date = date('Y-m-d H:i:s', $stat->time);
            $data[] = [$date, $value];
        }
        
        return $data;
    }

    /**
     * Get aggregated hashrate chart data
     * 
     * @param string $algo Algorithm name
     * @param int $timeFrom Start time (unix timestamp)
     * @param int $interval Aggregation interval in seconds
     * @param float $factor Conversion factor
     * @return array
     */
    public static function getAggregatedHashrateData($algo, $timeFrom, $interval, $factor)
    {
        $stats = static::getStatsForAlgo($algo, $timeFrom);
        $res = [];
        $divisor = $interval / 3600; // Convert interval to hours for averaging
        
        foreach ($stats as $stat) {
            $i = floor($stat->time / $interval) * $interval;
            
            if (!isset($res[$i])) {
                $res[$i] = 0;
            }
            
            $res[$i] += $stat->hashrate / $divisor;
        }
        
        $data = [];
        foreach ($res as $time => $hashrate) {
            $value = round($hashrate / $factor, 3);
            $date = date('Y-m-d H:i:s', $time);
            $data[] = [$date, $value];
        }
        
        return $data;
    }

    /**
     * Get aggregated earnings chart data
     * 
     * @param string $algo Algorithm name
     * @param int $timeFrom Start time (unix timestamp)
     * @param int $interval Aggregation interval in seconds
     * @param float $multiplier Earnings multiplier
     * @return array
     */
    public static function getAggregatedEarningsData($algo, $timeFrom, $interval, $multiplier = 24)
    {
        $stats = static::getStatsForAlgo($algo, $timeFrom);
        $res = [];
        $divisor = $interval / 3600; // Convert interval to hours for averaging
        
        foreach ($stats as $stat) {
            $i = floor($stat->time / $interval) * $interval;
            
            if (!isset($res[$i])) {
                $res[$i] = 0;
            }
            
            $res[$i] += $stat->earnings / $divisor;
        }
        
        $data = [];
        foreach ($res as $time => $earnings) {
            $value = $earnings * $multiplier;
            $date = date('Y-m-d H:i:s', $time);
            $data[] = [$date, $value];
        }
        
        return $data;
    }

    /**
     * Get aggregated BTC per unit chart data
     * 
     * @param string $algo Algorithm name
     * @param int $timeFrom Start time (unix timestamp)
     * @param int $interval Aggregation interval in seconds
     * @param float $algoUnitFactor Algorithm unit factor
     * @return array
     */
    public static function getAggregatedBtcPerUnitData($algo, $timeFrom, $interval, $algoUnitFactor)
    {
        $stats = static::getStatsForAlgo($algo, $timeFrom);
        $res = [];
        
        foreach ($stats as $stat) {
            $i = floor($stat->time / $interval) * $interval;
            
            if (!isset($res[$i])) {
                $res[$i] = ['earnings' => 0, 'hashrate' => 0];
            }
            
            $res[$i]['earnings'] += $stat->earnings;
            $res[$i]['hashrate'] += $stat->hashrate / 24;
        }
        
        $data = [];
        foreach ($res as $time => $values) {
            $value = $values['hashrate'] ? 
                ($values['earnings'] * $algoUnitFactor * 1000000 / $values['hashrate']) : 0;
            $date = date('Y-m-d H:i:s', $time);
            $data[] = [$date, $value];
        }
        
        return $data;
    }
}