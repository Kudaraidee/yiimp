<?php

namespace app\components;

use Yii;
use yii\web\View;

/**
 * CSP-aware View Component
 * 
 * Extends Yii2's View to automatically add CSP nonces to inline scripts and styles
 * registered via registerJs() and registerCss().
 */
class CspView extends View
{
    /**
     * @inheritdoc
     */
    public function registerJs($js, $position = self::POS_READY, $key = null)
    {
        $key = $key ?: md5($js);
        $this->js[$position][$key] = $js;
        return $this;
    }
    
    /**
     * @inheritdoc
     * Override to automatically inject CSP nonce into inline styles
     */
    public function registerCss($css, $options = [], $key = null)
    {
        // Add nonce to options if available and not already set
        $nonce = $this->getCspNonce();
        if ($nonce && !isset($options['nonce'])) {
            $options['nonce'] = $nonce;
        }
        
        // Store CSS with nonce-enhanced options
        $key = $key ?: md5($css);
        $this->css[$key] = \yii\helpers\Html::style($css, $options);
        
        return $this;
    }
    
    /**
     * @inheritdoc
     * Override to add nonce attributes to script tags in body end
     */
    protected function renderBodyEndHtml($ajaxMode)
    {
        $lines = [];
        $nonce = $this->getCspNonce();
        $nonceAttr = $nonce ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"' : '';

        if (!empty($this->jsFiles[self::POS_END])) {
            foreach ($this->jsFiles[self::POS_END] as $key => $item) {
                $lines[] = $item;
            }
        }

        if (!empty($this->js[self::POS_END])) {
            $lines[] = "<script{$nonceAttr}>" . implode("\n", $this->js[self::POS_END]) . '</script>';
        }

        if (!empty($this->js[self::POS_READY])) {
            $js = "jQuery(function ($) {\n" . implode("\n", $this->js[self::POS_READY]) . "\n});";
            $lines[] = "<script{$nonceAttr}>{$js}</script>";
        }

        if (!empty($this->js[self::POS_LOAD])) {
            $js = "jQuery(window).on('load', function () {\n" . implode("\n", $this->js[self::POS_LOAD]) . "\n});";
            $lines[] = "<script{$nonceAttr}>{$js}</script>";
        }

        return empty($lines) ? '' : implode("\n", $lines);
    }

    /**
     * Get the CSP nonce, trying multiple sources
     * @return string|null
     */
    protected function getCspNonce(): ?string
    {
        // First try view params
        if (!empty($this->params['cspNonce'])) {
            return $this->params['cspNonce'];
        }
        
        // Then try the cspNonce component
        if (Yii::$app->has('cspNonce')) {
            return Yii::$app->get('cspNonce')->getNonce();
        }
        
        // Finally try the server variable directly
        if (isset($_SERVER['HTTP_X_CSP_NONCE']) && !empty($_SERVER['HTTP_X_CSP_NONCE'])) {
            return $_SERVER['HTTP_X_CSP_NONCE'];
        }
        
        return null;
    }
    
    /**
     * @inheritdoc
     * Override to add nonce attributes to script tags in head
     */
    protected function renderHeadHtml()
    {
        $lines = [];
        $nonce = $this->getCspNonce();
        $nonceAttr = $nonce ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"' : '';

        if (!empty($this->metaTags)) {
            $lines[] = implode("\n", $this->metaTags);
        }

        if (!empty($this->linkTags)) {
            $lines[] = implode("\n", $this->linkTags);
        }
        
        if (!empty($this->cssFiles)) {
            $lines[] = implode("\n", $this->cssFiles);
        }
        
        if (!empty($this->css)) {
            // CSS is already rendered by parent's registerCss() using Html::style()
            // which includes the nonce option we passed
            $lines[] = implode("\n", $this->css);
        }
        
        if (!empty($this->jsFiles[self::POS_HEAD])) {
            $lines[] = implode("\n", $this->jsFiles[self::POS_HEAD]);
        }
        
        if (!empty($this->js[self::POS_HEAD])) {
            $lines[] = "<script{$nonceAttr}>" . implode("\n", $this->js[self::POS_HEAD]) . '</script>';
        }

        if (!empty($this->js[self::POS_BEGIN])) {
            $lines[] = "<script{$nonceAttr}>" . implode("\n", $this->js[self::POS_BEGIN]) . '</script>';
        }

        return empty($lines) ? '' : implode("\n", $lines);
    }
}
