<?php

namespace app\widgets;

use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use app\assets\ChartJsAsset;

/**
 * ChartWidget renders interactive charts using Chart.js
 * 
 * Usage:
 * ```php
 * echo ChartWidget::widget([
 *     'type' => 'line',
 *     'data' => [
 *         'labels' => ['Jan', 'Feb', 'Mar'],
 *         'datasets' => [[
 *             'label' => 'Hashrate',
 *             'data' => [100, 150, 200],
 *         ]],
 *     ],
 *     'options' => [
 *         'responsive' => true,
 *     ],
 * ]);
 * ```
 */
class ChartWidget extends Widget
{
    /**
     * @var string Chart type (line, bar, pie, doughnut, radar, polarArea, bubble, scatter)
     */
    public $type = 'line';
    
    /**
     * @var array Chart data
     */
    public $data = [];
    
    /**
     * @var array Chart options
     */
    public $options = [];
    
    /**
     * @var array HTML options for the canvas element
     */
    public $canvasOptions = ['class' => 'chart-canvas'];
    
    /**
     * @var int Canvas width
     */
    public $width = null;
    
    /**
     * @var int Canvas height
     */
    public $height = null;
    
    /**
     * @var string Chart ID (auto-generated if not provided)
     */
    public $id;
    
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        
        if ($this->id === null) {
            $this->id = $this->getId();
        }
        
        // Set default options
        if (!isset($this->options['responsive'])) {
            $this->options['responsive'] = true;
        }
        
        if (!isset($this->options['maintainAspectRatio'])) {
            $this->options['maintainAspectRatio'] = true;
        }
        
        // Register Chart.js asset
        ChartJsAsset::register($this->view);
    }
    
    /**
     * @inheritdoc
     */
    public function run()
    {
        $canvasId = 'chart-' . $this->id;
        
        // Set canvas dimensions if provided
        if ($this->width !== null) {
            $this->canvasOptions['width'] = $this->width;
        }
        
        if ($this->height !== null) {
            $this->canvasOptions['height'] = $this->height;
        }
        
        // Render canvas
        $canvas = Html::tag('canvas', '', array_merge(
            $this->canvasOptions,
            ['id' => $canvasId]
        ));
        
        // Wrap in container
        $output = Html::tag('div', $canvas, [
            'class' => 'chart-container',
            'style' => 'position: relative;'
        ]);
        
        // Register JavaScript
        $this->registerJs($canvasId);
        
        return $output;
    }
    
    /**
     * Register JavaScript to initialize the chart
     * 
     * @param string $canvasId Canvas element ID
     */
    protected function registerJs($canvasId)
    {
        $type = Json::encode($this->type);
        $data = Json::encode($this->data);
        $options = Json::encode($this->options);
        
        $js = <<<JS
(function() {
    var ctx = document.getElementById('$canvasId').getContext('2d');
    var chart = new Chart(ctx, {
        type: $type,
        data: $data,
        options: $options
    });
    
    // Store chart instance for later access
    $('#$canvasId').data('chart', chart);
})();
JS;
        
        $this->view->registerJs($js);
    }
    
    /**
     * Create a line chart
     * 
     * @param array $labels X-axis labels
     * @param array $datasets Array of datasets
     * @param array $options Chart options
     * @return string
     */
    public static function line($labels, $datasets, $options = [])
    {
        return static::widget([
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => $datasets,
            ],
            'options' => $options,
        ]);
    }
    
    /**
     * Create a bar chart
     * 
     * @param array $labels X-axis labels
     * @param array $datasets Array of datasets
     * @param array $options Chart options
     * @return string
     */
    public static function bar($labels, $datasets, $options = [])
    {
        return static::widget([
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => $datasets,
            ],
            'options' => $options,
        ]);
    }
    
    /**
     * Create a pie chart
     * 
     * @param array $labels Labels
     * @param array $data Data values
     * @param array $options Chart options
     * @return string
     */
    public static function pie($labels, $data, $options = [])
    {
        return static::widget([
            'type' => 'pie',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'data' => $data,
                    'backgroundColor' => static::getDefaultColors(count($data)),
                ]],
            ],
            'options' => $options,
        ]);
    }
    
    /**
     * Create a doughnut chart
     * 
     * @param array $labels Labels
     * @param array $data Data values
     * @param array $options Chart options
     * @return string
     */
    public static function doughnut($labels, $data, $options = [])
    {
        return static::widget([
            'type' => 'doughnut',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'data' => $data,
                    'backgroundColor' => static::getDefaultColors(count($data)),
                ]],
            ],
            'options' => $options,
        ]);
    }
    
    /**
     * Get default color palette
     * 
     * @param int $count Number of colors needed
     * @return array
     */
    protected static function getDefaultColors($count)
    {
        $colors = [
            'rgba(54, 162, 235, 0.8)',   // Blue
            'rgba(255, 99, 132, 0.8)',   // Red
            'rgba(255, 206, 86, 0.8)',   // Yellow
            'rgba(75, 192, 192, 0.8)',   // Green
            'rgba(153, 102, 255, 0.8)',  // Purple
            'rgba(255, 159, 64, 0.8)',   // Orange
            'rgba(199, 199, 199, 0.8)',  // Grey
            'rgba(83, 102, 255, 0.8)',   // Indigo
            'rgba(255, 99, 255, 0.8)',   // Pink
            'rgba(99, 255, 132, 0.8)',   // Light Green
        ];
        
        // Repeat colors if needed
        while (count($colors) < $count) {
            $colors = array_merge($colors, $colors);
        }
        
        return array_slice($colors, 0, $count);
    }
}
