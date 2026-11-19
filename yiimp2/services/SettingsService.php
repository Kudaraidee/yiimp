<?php

namespace app\services;

use Yii;
use yii\base\Component;

/**
 * SettingsService — read/write pool operator settings from the `settings` table.
 *
 * Settings are keyed with structured prefixes:
 *   exchange-<name>-<key>          e.g. "binance-disabled"
 *   exchange-<name>-<symbol>-<base>-<key>  e.g. "yobit-DCR-BTC-disabled"
 *   coin-<symbol>-<key>
 *
 * Ported from: web/yaamp/core/functions/settings.php
 *
 * Register as application component 'settings' in web.php and console.php:
 *   'settings' => ['class' => \app\services\SettingsService::class]
 */
class SettingsService extends Component
{
    /** Per-request exchange-settings cache. Keyed like the settings table. */
    private array $cacheExchange = [];

    /** Per-request market-settings cache. Keyed like the settings table. */
    private array $cacheMarket = [];

    // =========================================================================
    // Core settings table
    // =========================================================================

    public function get(string $key, mixed $default = null): mixed
    {
        $row = Yii::$app->db->createCommand(
            'SELECT value, type FROM settings WHERE param = :k',
            [':k' => $key]
        )->queryOne();

        if (!$row) return $default;

        $type = $row['type'] ?: $this->keyType($key);
        return $this->cast($row['value'], $type);
    }

    public function set(string $key, mixed $value): void
    {
        $type = $this->keyType($key);
        $value = $this->normalise($value, $type);

        Yii::$app->db->createCommand()->upsert('settings', [
            'param' => $key,
            'value' => $value,
            'type'  => $type,
        ], ['value' => $value, 'type' => $type])->execute();
    }

    public function setDefault(string $key, mixed $value): bool
    {
        $exists = (bool) Yii::$app->db->createCommand(
            'SELECT COUNT(param) FROM settings WHERE param = :k', [':k' => $key]
        )->queryScalar();

        if ($exists) return false;
        $this->set($key, $value);
        return true;
    }

    // =========================================================================
    // Exchange-level settings  (key pattern: "<exchange>-<key>")
    // =========================================================================

    public function exchangeGet(string $exchange, string $key, mixed $default = null): mixed
    {
        if (isset($this->cacheExchange[$exchange])) {
            return $this->cacheExchange["{$exchange}-{$key}"] ?? $default;
        }
        return $this->get("{$exchange}-{$key}", $default);
    }

    public function exchangeSetDefault(string $exchange, string $key, mixed $value): bool
    {
        $res = $this->setDefault("{$exchange}-{$key}", $value);
        if ($res) $this->cacheExchange = [];
        return $res;
    }

    public function exchangePrefetch(string $exchange): void
    {
        if (isset($this->cacheExchange[$exchange])) return;

        $keys = Yii::$app->db->createCommand(
            "SELECT param FROM settings WHERE param LIKE :pat",
            [':pat' => "{$exchange}-%"]
        )->queryColumn();

        foreach ($keys as $k) {
            if (substr_count($k, '-') > 2) continue; // skip market-level keys
            $this->cacheExchange[$k] = $this->get($k);
        }
        $this->cacheExchange[$exchange] = true;
    }

    public function exchangeSet(string $exchange, string $key, mixed $value): void
    {
        $this->cacheExchange = [];
        $this->set("{$exchange}-{$key}", $value);
    }

    public function exchangeUnset(string $exchange, string $key): void
    {
        $this->cacheExchange = [];
        Yii::$app->db->createCommand()->delete('settings', ['param' => "{$exchange}-{$key}"])->execute();
    }

    /** Returns the documented keys for exchange-level settings. */
    public function validExchangeKeys(): array
    {
        return [
            'disabled'           => 'Fully disable the exchange',
            'trade_min_btc'      => 'Minimum order on the exchange',
            'trade_sell_ask_pct' => 'Initial order ask price related to the lowest ask (%)',
            'trade_cancel_ask_pct' => 'Cancel orders if the lowest ask reaches this % of your order',
            'withdraw_min_btc'   => 'Auto-withdraw when BTC balance exceeds this amount (0=disabled)',
            'withdraw_fee_btc'   => 'Fees in BTC required to withdraw on the exchange',
        ];
    }

    // =========================================================================
    // Market-level settings  (key pattern: "<exchange>-<symbol>-<base>-<key>")
    // =========================================================================

    public function marketGet(string $exchange, string $symbol, string $key, mixed $default = null, string $base = 'BTC'): mixed
    {
        if (isset($this->cacheMarket[$exchange])) {
            return $this->cacheMarket["{$exchange}-{$symbol}-{$base}-{$key}"] ?? $default;
        }
        return $this->get("{$exchange}-{$symbol}-{$base}-{$key}", $default);
    }

    public function marketSetDefault(string $exchange, string $symbol, string $key, mixed $value, string $base = 'BTC'): bool
    {
        $res = $this->setDefault("{$exchange}-{$symbol}-{$base}-{$key}", $value);
        if ($res) $this->cacheMarket = [];
        return $res;
    }

    public function marketPrefetch(string $exchange): void
    {
        if (isset($this->cacheMarket[$exchange])) return;

        $keys = Yii::$app->db->createCommand(
            "SELECT param FROM settings WHERE param LIKE :pat",
            [':pat' => "{$exchange}-%"]
        )->queryColumn();

        foreach ($keys as $k) {
            if (substr_count($k, '-') < 3) continue; // skip exchange-level keys
            $this->cacheMarket[$k] = $this->get($k);
        }
        $this->cacheMarket[$exchange] = true;
    }

    public function marketSet(string $exchange, string $symbol, string $key, mixed $value, string $base = 'BTC'): void
    {
        $this->cacheMarket = [];
        $this->set("{$exchange}-{$symbol}-{$base}-{$key}", $value);
    }

    public function marketUnset(string $exchange, string $symbol, string $key, string $base = 'BTC'): void
    {
        $this->cacheMarket = [];
        Yii::$app->db->createCommand()->delete('settings', ['param' => "{$exchange}-{$symbol}-{$base}-{$key}"])->execute();
    }

    // =========================================================================
    // Coin-level settings  (key pattern: "coin-<symbol>-<key>")
    // =========================================================================

    public function coinGet(string $symbol, string $key, mixed $default = null): mixed
    {
        return $this->get("coin-{$symbol}-{$key}", $default);
    }

    public function coinSet(string $symbol, string $key, mixed $value): void
    {
        $this->set("coin-{$symbol}-{$key}", $value);
    }

    public function coinUnset(string $symbol, string $key): void
    {
        Yii::$app->db->createCommand()->delete('settings', ['param' => "coin-{$symbol}-{$key}"])->execute();
    }

    public function coinPrefetch(string $symbol): array
    {
        $keys = Yii::$app->db->createCommand(
            "SELECT param FROM settings WHERE param LIKE :pat",
            [':pat' => "coin-{$symbol}-%"]
        )->queryColumn();

        $result = [];
        foreach ($keys as $k) {
            $result[$k] = $this->get($k);
        }
        return $result;
    }

    // =========================================================================
    // Bulk prefetch — warm both caches for all active exchanges
    // =========================================================================

    public function prefetchAll(): void
    {
        $exchanges = Yii::$app->db->createCommand(
            'SELECT DISTINCT name FROM markets'
        )->queryColumn();

        foreach ($exchanges as $name) {
            $this->exchangePrefetch($name);
            $this->marketPrefetch($name);
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function keyType(string $key): string
    {
        if (str_contains($key, 'enabled'))  return 'bool';
        if (str_contains($key, 'disabled')) return 'bool';
        if (str_ends_with($key, 'pct'))     return 'percent';
        if (str_ends_with($key, 'btc'))     return 'price';
        if (str_ends_with($key, 'price'))   return 'price';
        return 'string';
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool'    => (bool) $value,
            'int'     => (int) $value,
            'percent' => (float) $value / 100.0,
            'price', 'real' => (float) $value,
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }

    private function normalise(mixed $value, string $type): mixed
    {
        if ($type === 'bool') {
            if (is_string($value) && strcasecmp($value, 'true')  === 0) return 1;
            if (is_string($value) && strcasecmp($value, 'false') === 0) return 0;
        }
        if ($type === 'percent' && is_string($value) && !str_contains($value, '%')) {
            return (float) $value * 100;
        }
        return $value;
    }
}
