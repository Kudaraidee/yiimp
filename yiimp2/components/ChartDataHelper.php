<?php

namespace app\components;

use Yii;

/**
 * ChartDataHelper provides utilities for preparing chart data
 */
class ChartDataHelper
{
    /**
     * Prepare hashrate chart data
     * 
     * @param array $data Raw hashrate data with timestamps
     * @param string $timeFormat Time format for labels
     * @return array Chart data with labels and datasets
     */
    public static function prepareHashrateData($data, $timeFormat = 'H:i')
    {
        $labels = [];
        $values = [];
        
        foreach ($data as $item) {
            $labels[] = date($timeFormat, $item['time']);
            $values[] = $item['hashrate'];
        }
        
        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Hashrate',
                'data' => $values,
                'borderColor' => 'rgba(54, 162, 235, 1)',
                'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                'borderWidth' => 2,
                'fill' => true,
                'tension' => 0.4,
            ]],
        ];
    }
    
    /**
     * Prepare profitability chart data
     * 
     * @param array $data Raw profitability data by algorithm
     * @return array Chart data with labels and datasets
     */
    public static function prepareProfitabilityData($data)
    {
        $labels = [];
        $values = [];
        
        foreach ($data as $algo => $profitability) {
            $labels[] = $algo;
            $values[] = $profitability;
        }
        
        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Profitability (BTC/Day)',
                'data' => $values,
                'backgroundColor' => 'rgba(75, 192, 192, 0.8)',
                'borderColor' => 'rgba(75, 192, 192, 1)',
                'borderWidth' => 1,
            ]],
        ];
    }
    
    /**
     * Prepare earnings chart data
     * 
     * @param array $data Raw earnings data with dates
     * @param string $dateFormat Date format for labels
     * @return array Chart data with labels and datasets
     */
    public static function prepareEarningsData($data, $dateFormat = 'M d')
    {
        $labels = [];
        $values = [];
        
        foreach ($data as $item) {
            $labels[] = date($dateFormat, $item['date']);
            $values[] = $item['amount'];
        }
        
        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Earnings',
                'data' => $values,
                'borderColor' => 'rgba(255, 206, 86, 1)',
                'backgroundColor' => 'rgba(255, 206, 86, 0.2)',
                'borderWidth' => 2,
                'fill' => true,
            ]],
        ];
    }
    
    /**
     * Prepare difficulty chart data
     * 
     * @param array $data Raw difficulty data with timestamps
     * @param string $timeFormat Time format for labels
     * @return array Chart data with labels and datasets
     */
    public static function prepareDifficultyData($data, $timeFormat = 'M d')
    {
        $labels = [];
        $values = [];
        
        foreach ($data as $item) {
            $labels[] = date($timeFormat, $item['time']);
            $values[] = $item['difficulty'];
        }
        
        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Difficulty',
                'data' => $values,
                'borderColor' => 'rgba(255, 99, 132, 1)',
                'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                'borderWidth' => 2,
                'fill' => true,
            ]],
        ];
    }
    
    /**
     * Prepare multi-algorithm comparison chart data
     * 
     * @param array $data Array of algorithms with their metrics over time
     * @return array Chart data with labels and multiple datasets
     */
    public static function prepareMultiAlgoData($data)
    {
        $labels = [];
        $datasets = [];
        
        // Extract time labels from first algorithm
        $firstAlgo = reset($data);
        foreach ($firstAlgo as $item) {
            $labels[] = date('H:i', $item['time']);
        }
        
        // Create dataset for each algorithm
        $colors = [
            'rgba(54, 162, 235, 1)',
            'rgba(255, 99, 132, 1)',
            'rgba(255, 206, 86, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(153, 102, 255, 1)',
            'rgba(255, 159, 64, 1)',
        ];
        
        $colorIndex = 0;
        foreach ($data as $algo => $values) {
            $dataPoints = [];
            foreach ($values as $item) {
                $dataPoints[] = $item['value'];
            }
            
            $color = $colors[$colorIndex % count($colors)];
            $datasets[] = [
                'label' => $algo,
                'data' => $dataPoints,
                'borderColor' => $color,
                'backgroundColor' => str_replace('1)', '0.2)', $color),
                'borderWidth' => 2,
                'fill' => false,
            ];
            
            $colorIndex++;
        }
        
        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }
    
    /**
     * Prepare worker distribution pie chart data
     * 
     * @param array $data Worker data by algorithm
     * @return array Chart data for pie chart
     */
    public static function prepareWorkerDistribution($data)
    {
        $labels = [];
        $values = [];
        
        foreach ($data as $algo => $count) {
            $labels[] = $algo;
            $values[] = $count;
        }
        
        return [
            'labels' => $labels,
            'datasets' => [[
                'data' => $values,
                'backgroundColor' => [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)',
                ],
                'borderWidth' => 1,
            ]],
        ];
    }
    
    /**
     * Prepare block time chart data
     * 
     * @param array $data Block data with timestamps
     * @return array Chart data with labels and datasets
     */
    public static function prepareBlockTimeData($data)
    {
        $labels = [];
        $values = [];
        
        foreach ($data as $item) {
            $labels[] = 'Block ' . $item['height'];
            $values[] = $item['time_to_find'];
        }
        
        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Time to Find (seconds)',
                'data' => $values,
                'backgroundColor' => 'rgba(153, 102, 255, 0.8)',
                'borderColor' => 'rgba(153, 102, 255, 1)',
                'borderWidth' => 1,
            ]],
        ];
    }
    
    /**
     * Get default chart options for hashrate charts
     * 
     * @return array Chart options
     */
    public static function getHashrateChartOptions()
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => true,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Hashrate (H/s)',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Time',
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Get default chart options for profitability charts
     * 
     * @return array Chart options
     */
    public static function getProfitabilityChartOptions()
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => true,
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { return context.parsed.y.toFixed(8) + " BTC/Day"; }',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Profitability (BTC/Day)',
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Format hashrate value for display
     * 
     * @param float $hashrate Hashrate in H/s
     * @return string Formatted hashrate with unit
     */
    public static function formatHashrate($hashrate)
    {
        $units = ['H/s', 'KH/s', 'MH/s', 'GH/s', 'TH/s', 'PH/s'];
        $unitIndex = 0;
        
        while ($hashrate >= 1000 && $unitIndex < count($units) - 1) {
            $hashrate /= 1000;
            $unitIndex++;
        }
        
        return number_format($hashrate, 2) . ' ' . $units[$unitIndex];
    }
}
