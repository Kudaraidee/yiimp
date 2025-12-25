<?php

namespace app\components;

use Yii;
use yii\base\Component;

/**
 * DynamicStyleManager
 * 
 * Helper component for generating CSP-compliant dynamic styles.
 * Converts PHP-generated values (algorithm colors, chart heights) into CSS custom properties
 * and provides methods for generating CSS class names.
 */
class DynamicStyleManager extends Component
{
    /**
     * Generate CSS custom properties for algorithm colors
     * 
     * @param array $algos Array of algorithm data with 'name' and 'color' keys
     * @return string CSS custom property declarations
     */
    public static function generateAlgoColorVars(array $algos): string
    {
        if (empty($algos)) {
            return '';
        }

        $cssVars = [];
        foreach ($algos as $algo) {
            if (isset($algo['name']) && isset($algo['color'])) {
                $algoName = self::sanitizeAlgoName($algo['name']);
                $color = self::sanitizeColor($algo['color']);
                $cssVars[] = "  --algo-color-{$algoName}: {$color};";
            }
        }

        return implode("\n", $cssVars);
    }

    /**
     * Get CSS class name for algorithm background color
     * 
     * @param string $algo Algorithm name
     * @return string CSS class name (e.g., 'algo-bg-sha256')
     */
    public static function getAlgoColorClass(string $algo): string
    {
        if (empty($algo)) {
            return 'algo-bg-default';
        }

        $algoName = self::sanitizeAlgoName($algo);
        return "algo-bg-{$algoName}";
    }

    /**
     * Render complete dynamic style tag with CSS variables
     * 
     * @param array $config Configuration array with keys:
     *                      - 'algos': Array of algorithm data
     *                      - 'chartHeight': Optional chart height in pixels
     * @return string HTML style tag with CSS variables
     */
    public static function renderDynamicStyles(array $config): string
    {
        $styles = [];

        // Generate algorithm color variables
        if (isset($config['algos']) && is_array($config['algos'])) {
            $algoVars = self::generateAlgoColorVars($config['algos']);
            if (!empty($algoVars)) {
                $styles[] = $algoVars;
            }
        }

        // Generate chart height variable if provided
        if (isset($config['chartHeight']) && is_numeric($config['chartHeight'])) {
            $height = (int) $config['chartHeight'];
            $styles[] = "  --chart-height-custom: {$height}px;";
        }

        // If no styles to generate, return empty string
        if (empty($styles)) {
            return '';
        }

        // Build complete style tag
        $cssContent = implode("\n", $styles);
        return <<<HTML
<style>
:root {
{$cssContent}
}
</style>
HTML;
    }

    /**
     * Sanitize algorithm name for use in CSS class names
     * Converts to lowercase and replaces non-alphanumeric characters with hyphens
     * 
     * @param string $algo Algorithm name
     * @return string Sanitized algorithm name
     */
    private static function sanitizeAlgoName(string $algo): string
    {
        // Convert to lowercase
        $sanitized = strtolower($algo);
        
        // Replace non-alphanumeric characters with hyphens
        $sanitized = preg_replace('/[^a-z0-9]+/', '-', $sanitized);
        
        // Remove leading/trailing hyphens
        $sanitized = trim($sanitized, '-');
        
        return $sanitized;
    }

    /**
     * Sanitize color value for CSS
     * Validates hex color format
     * 
     * @param string $color Color value (hex format expected)
     * @return string Sanitized color value or fallback
     */
    private static function sanitizeColor(string $color): string
    {
        // Trim whitespace
        $color = trim($color);
        
        // Validate hex color format (#RGB or #RRGGBB)
        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
            return $color;
        }
        
        // Fallback to default gray color
        return '#cccccc';
    }
}
