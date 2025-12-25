<?php

namespace app\components;

use Yii;

/**
 * ConversionUtils component
 * 
 * Provides utility functions for value conversions
 */
class ConversionUtils
{
    /**
     * Convert bitcoin value to formatted string
     * 
     * @param float $value Bitcoin value
     * @return string Formatted bitcoin value
     */
    public function bitcoinvaluetoa($value)
    {
        if ($value == 0) return '0';
        
        if ($value < 0.00001) {
            return sprintf('%.8f', $value);
        } elseif ($value < 0.001) {
            return sprintf('%.6f', $value);
        } elseif ($value < 0.1) {
            return sprintf('%.4f', $value);
        } else {
            return sprintf('%.2f', $value);
        }
    }

    /**
     * Format large numbers with appropriate units (Itoa2 equivalent)
     * 
     * @param float $value The value to format
     * @return string Formatted value with unit
     */
    public function Itoa2($value)
    {
        if ($value >= 1000000000000) {
            return round($value / 1000000000000, 2) . ' T';
        } elseif ($value >= 1000000000) {
            return round($value / 1000000000, 2) . ' G';
        } elseif ($value >= 1000000) {
            return round($value / 1000000, 2) . ' M';
        } elseif ($value >= 1000) {
            return round($value / 1000, 2) . ' K';
        } else {
            return round($value, 2);
        }
    }

    /**
     * Convert mBTC value to formatted string
     * 
     * @param float $value mBTC value
     * @return string Formatted mBTC value
     */
    public function mbitcoinvaluetoa($value)
    {
        if ($value == 0) return '0';
        
        if ($value < 0.001) {
            return sprintf('%.6f', $value);
        } elseif ($value < 0.1) {
            return sprintf('%.4f', $value);
        } elseif ($value < 10) {
            return sprintf('%.3f', $value);
        } else {
            return sprintf('%.2f', $value);
        }
    }

    /**
     * Convert timestamp to formatted date string (datetoa2 equivalent)
     * 
     * @param int $timestamp Unix timestamp
     * @return string Formatted date string
     */
    public function datetoa2($timestamp)
    {
        if (empty($timestamp) || $timestamp == 0) {
            return '-';
        }
        
        return date('Y-m-d H:i:s', $timestamp);
    }
}