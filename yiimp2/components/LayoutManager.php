<?php

namespace app\components;

use yii\base\Component;

/**
 * Resolves the active view-layout scheme and exposes helpers used by layouts,
 * asset bundles, and ViewUtils partials.
 *
 * Configured via the YIIMP_LAYOUT constant ('legacy' | 'adminlte' | 'tailwind').
 * Unknown values fall back to 'legacy' so a misconfigured serverconfig.php never
 * causes a blank page.
 *
 * Register in config/web.php:
 *   'LayoutManager' => ['class' => 'app\components\LayoutManager', 'scheme' => YIIMP_LAYOUT]
 *
 * Usage in layouts / components:
 *   Yii::$app->LayoutManager->layoutPath()   // '@app/views/layouts/legacy/main'
 *   Yii::$app->LayoutManager->is('tailwind') // bool
 */
class LayoutManager extends Component
{
    public const SCHEMES = ['legacy', 'adminlte', 'tailwind'];

    public string $scheme = 'legacy';

    public function init(): void
    {
        parent::init();
        if (!in_array($this->scheme, self::SCHEMES, true)) {
            \Yii::warning(
                "Unknown YIIMP_LAYOUT value '{$this->scheme}', falling back to 'legacy'.",
                __CLASS__
            );
            $this->scheme = 'legacy';
        }
    }

    /** Yii2 layout alias consumed by BaseController::init(). */
    public function layoutPath(): string
    {
        return "@app/views/layouts/{$this->scheme}/main";
    }

    public function is(string $scheme): bool     { return $this->scheme === $scheme; }
    public function isLegacy(): bool             { return $this->scheme === 'legacy'; }
    public function isAdminLte(): bool           { return $this->scheme === 'adminlte'; }
    public function isTailwind(): bool           { return $this->scheme === 'tailwind'; }

    /** Whether to load Tailwind from CDN instead of the compiled local file. */
    public function tailwindCdn(): bool          { return (bool) YIIMP_TAILWIND_CDN; }
}
