<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\helpers\Html;

/**
 * ViewHelper provides reusable view helper functions
 * to avoid function redeclaration issues in view files
 */
class ViewHelper extends Component
{
    /**
     * Render a box header with title
     * @param string $title The title to display in the box header
     * @param array $options Optional attributes for the box container
     */
    public static function renderBoxHeader($title, $options = [])
    {
        $class = isset($options['class']) ? $options['class'] : 'main-left-box';
        echo "<div class='{$class}'>";
        echo "<div class='main-left-title'>" . Html::encode($title) . "</div>";
        echo "<div class='main-left-inner'>";
    }
    
    /**
     * Render a box footer
     * Closes the divs opened by renderBoxHeader
     */
    public static function renderBoxFooter()
    {
        echo "</div></div>";
    }
    
    /**
     * Render a data table with optional sorting
     * @param array $headers Array of header labels
     * @param array $rows Array of row data (each row is an array of cell values)
     * @param array $options Optional table attributes (class, id, sortable)
     * @return string HTML table markup
     */
    public static function renderDataTable($headers, $rows, $options = [])
    {
        $class = isset($options['class']) ? $options['class'] : 'table table-striped';
        $id = isset($options['id']) ? " id='{$options['id']}'" : '';
        $sortable = isset($options['sortable']) ? $options['sortable'] : false;
        
        $html = "<table class='{$class}'{$id}>";
        
        // Render headers
        $html .= "<thead><tr>";
        foreach ($headers as $header) {
            $headerClass = $sortable ? ' class="sortable"' : '';
            $html .= "<th{$headerClass}>" . Html::encode($header) . "</th>";
        }
        $html .= "</tr></thead>";
        
        // Render rows
        $html .= "<tbody>";
        foreach ($rows as $row) {
            $html .= "<tr>";
            foreach ($row as $cell) {
                $html .= "<td>" . Html::encode($cell) . "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</tbody>";
        
        $html .= "</table>";
        
        return $html;
    }
    
    /**
     * Format hashrate with appropriate units (h/s, Kh/s, Mh/s, Gh/s, Th/s, Ph/s)
     * @param float $hashrate Hashrate value in h/s
     * @param int $precision Number of decimal places (default: 1)
     * @return string Formatted hashrate with unit
     */
    public static function formatHashrate($hashrate, $precision = 1)
    {
        if ($hashrate === null || $hashrate === '') {
            return '0 h/s';
        }
        
        $formatted = Yii::$app->ConversionUtils->Itoa2($hashrate, $precision);
        return $formatted . 'h/s';
    }
    
    /**
     * Format timestamp for display
     * @param int|string $timestamp Unix timestamp or date string
     * @param string $format Date format string (default: 'Y-m-d H:i:s')
     * @return string Formatted date/time string
     */
    public static function formatTimestamp($timestamp, $format = 'Y-m-d H:i:s')
    {
        if (empty($timestamp)) {
            return '';
        }
        
        // If timestamp is a string with colons, it's already formatted
        if (is_string($timestamp) && strpos($timestamp, ':') !== false) {
            return $timestamp;
        }
        
        // Convert to integer if needed
        if (is_string($timestamp)) {
            $timestamp = (int)$timestamp;
        }
        
        return date($format, $timestamp);
    }
}
