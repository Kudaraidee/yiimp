<?php

namespace app\components;

use Yii;

/**
 * CSP Helper
 * 
 * Provides helper methods for CSP-compliant inline scripts and styles
 */
class CspHelper
{
    /**
     * Get the current CSP nonce
     * 
     * @return string|null The nonce value or null if not available
     */
    public static function getNonce(): ?string
    {
        if (Yii::$app->has('cspNonce')) {
            return Yii::$app->get('cspNonce')->getNonce();
        }
        
        // Fallback: try to get from view params
        if (isset(Yii::$app->view->params['cspNonce'])) {
            return Yii::$app->view->params['cspNonce'];
        }
        
        return null;
    }
    
    /**
     * Generate opening script tag with nonce
     * 
     * @param array $options Additional HTML attributes for the script tag
     * @return string The opening script tag with nonce attribute
     */
    public static function beginScript(array $options = []): string
    {
        $nonce = self::getNonce();
        $nonceAttr = $nonce ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"' : '';
        
        $attributes = '';
        foreach ($options as $key => $value) {
            if ($key === 'nonce') continue; // Skip nonce, we handle it separately
            $attributes .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            if ($value !== true) {
                $attributes .= '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }
        
        return "<script{$nonceAttr}{$attributes}>";
    }
    
    /**
     * Generate closing script tag
     * 
     * @return string The closing script tag
     */
    public static function endScript(): string
    {
        return '</script>';
    }
    
    /**
     * Generate opening style tag with nonce
     * 
     * @param array $options Additional HTML attributes for the style tag
     * @return string The opening style tag with nonce attribute
     */
    public static function beginStyle(array $options = []): string
    {
        $nonce = self::getNonce();
        $nonceAttr = $nonce ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"' : '';
        
        $attributes = '';
        foreach ($options as $key => $value) {
            if ($key === 'nonce') continue; // Skip nonce, we handle it separately
            $attributes .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            if ($value !== true) {
                $attributes .= '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }
        
        return "<style{$nonceAttr}{$attributes}>";
    }
    
    /**
     * Generate closing style tag
     * 
     * @return string The closing style tag
     */
    public static function endStyle(): string
    {
        return '</style>';
    }
    
    /**
     * Wrap JavaScript code in a script tag with nonce
     * 
     * @param string $js The JavaScript code
     * @param array $options Additional HTML attributes for the script tag
     * @return string Complete script tag with nonce and JavaScript code
     */
    public static function script(string $js, array $options = []): string
    {
        return self::beginScript($options) . "\n" . $js . "\n" . self::endScript();
    }
    
    /**
     * Wrap CSS code in a style tag with nonce
     * 
     * @param string $css The CSS code
     * @param array $options Additional HTML attributes for the style tag
     * @return string Complete style tag with nonce and CSS code
     */
    public static function style(string $css, array $options = []): string
    {
        return self::beginStyle($options) . "\n" . $css . "\n" . self::endStyle();
    }
}
