<?php

namespace app\exchanges;

use Yii;
use app\models\Coins;
use app\models\Markets;

/**
 * Abstract base for all exchange API drivers.
 *
 * Declare capabilities by overriding supports*() and implement the matching
 * operation methods. Shared plumbing (config access, disable checks, DB helpers)
 * is provided as final protected methods so subclasses never repeat boilerplate.
 */
abstract class ExchangeDriver
{
    // ── Capability flags ─────────────────────────────────────────────────────

    public function supportsMarkets(): bool  { return false; }
    public function supportsDiscover(): bool { return false; }
    public function supportsBalance(): bool  { return false; }
    public function supportsTrading(): bool  { return false; }
    public function supportsBtcEur(): bool   { return false; }

    // ── Operations (no-op by default; override per capability) ───────────────

    public function updateMarkets(): void { }
    public function discoverCoins(): void { }
    public function syncBalance(): void   { }
    public function trade(): void         { }
    public function btcEur(): float       { return 0.0; }

    /** Cancel a single open order by its exchange-side UUID. Override in trading drivers. */
    public function cancelOrder(string $orderId): bool { return false; }

    /** URL of the trading page for the given symbol/base pair on this exchange. */
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return ''; }

    // ── Identity ─────────────────────────────────────────────────────────────

    abstract public function name(): string;

    // ── Shared helpers ───────────────────────────────────────────────────────

    final protected function config(string $key, mixed $default = ''): mixed
    {
        return Yii::$app->settings->exchangeGet($this->name(), $key, $default);
    }

    final protected function configFloat(string $key, float $default): float
    {
        return (float) $this->config($key, $default);
    }

    final protected function isDisabled(): bool
    {
        return (bool) Yii::$app->settings->exchangeGet($this->name(), 'disabled', false);
    }

    final protected function marketDisabled(string $symbol, Markets $market): bool
    {
        if (Yii::$app->settings->marketGet($this->name(), $symbol, 'disabled')) {
            $market->disabled = 1;
            $market->message  = 'disabled from settings';
            $market->save();
            return true;
        }
        return false;
    }

    final protected function findOrCreateMarket(Coins $coin): Markets
    {
        $market = Markets::find()
            ->where(['coinid' => $coin->id, 'name' => $this->name()])
            ->andWhere(['or', ['base_coin' => null], ['base_coin' => ''], ['base_coin' => 'BTC']])
            ->one();

        if (!$market) {
            $market          = new Markets();
            $market->coinid  = $coin->id;
            $market->deleted = 0;
            $market->name    = $this->name();
        }

        return $market;
    }

    final protected function averageIncrement(float $old, float $new): float
    {
        return ($old * 20 + $new * 80) / 100;
    }

    /**
     * Mark all market records for this exchange as deleted.
     * Must be called at the start of discoverCoins() before iterating the live listing.
     */
    final protected function softDeleteMarkets(): void
    {
        Yii::$app->db->createCommand(
            'UPDATE markets SET deleted=true WHERE name=:ex',
            [':ex' => $this->name()]
        )->execute();
    }

    /**
     * Upsert a market record for the given symbol, auto-creating the coin when configured.
     * Ports: updateRawCoin()
     */
    final protected function upsertMarket(
        string  $symbol,
        string  $name     = 'unknown',
        ?string $baseCoin = null
    ): void {
        if ($symbol === 'BTC') {
            return;
        }

        $exchange  = $this->name();
        $coin      = Coins::find()->where(['symbol' => $symbol])->one();
        $createNew = defined('YIIMP_CREATE_NEW_COINS') && YIIMP_CREATE_NEW_COINS;

        if (!$coin && $createNew) {
            if (in_array($exchange, ['askcoin','binance','hitbtc','yobit','kucoin'], true)) {
                return;
            }
            if (Yii::$app->settings->marketGet($exchange, $symbol, 'disabled')) {
                return;
            }
            Yii::info("new coin {$exchange} {$symbol} {$name}", __CLASS__);
            $coin                 = new Coins();
            $coin->txmessage      = true;
            $coin->hassubmitblock = true;
            $coin->name           = $name;
            $coin->algo           = '';
            $coin->symbol         = $symbol;
            $coin->created        = time();
            $coin->save();
            sleep(1);
        } elseif ($coin && $coin->name === 'unknown' && $name !== 'unknown') {
            $coin->name = $name;
            $coin->save();
        }

        // Non-BTC markets are named "exchange BASECOIN" (e.g. "nonkyc USDT").
        // BTC markets keep the bare exchange name.
        $marketName = ($baseCoin !== null) ? "{$exchange} {$baseCoin}" : $exchange;

        foreach (Coins::find()->where(['symbol' => $symbol])->orWhere(['symbol2' => $symbol])->all() as $c) {
            $query = Markets::find()->where(['coinid' => $c->id]);
            if (is_null($baseCoin)) {
                $query->andWhere(['name' => $exchange])
                      ->andWhere(['or', ['base_coin' => null], ['base_coin' => '']]);
            } else {
                // Accept both the qualified name and the legacy bare name so that
                // existing records are found and renamed rather than duplicated.
                $query->andWhere(['base_coin' => $baseCoin])
                      ->andWhere(['or', ['name' => $marketName], ['name' => $exchange]]);
            }
            $market = $query->one();
            if (!$market) {
                $market            = new Markets();
                $market->coinid    = $c->id;
                $market->base_coin = $baseCoin;
            }
            $market->name    = $marketName; // always write/upgrade to the qualified name
            $market->deleted = false;
            $market->save();
        }
    }

    final protected function recordWithdrawal(string $address, float $amount): void
    {
        Yii::$app->db->createCommand()->insert('withdraws', [
            'market'  => $this->name(),
            'address' => $address,
            'amount'  => $amount,
            'time'    => time(),
        ])->execute();
    }

    /**
     * Execute an HTTP request and return the raw response body (HTML tags stripped).
     * $method: GET | POST | DELETE
     * $extraOpts: additional CURLOPT_* constants keyed by constant value.
     */
    final protected function curlRequest(
        string $method,
        string $url,
        array  $headers   = [],
        string $body      = '',
        bool   $sslVerify = true,
        array  $extraOpts = []
    ): string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT,
            'Mozilla/4.0 (compatible; ' . $this->name() . ' API PHP; ' .
            php_uname('s') . '; PHP/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ')'
        );
        if ($headers)    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if (!$sslVerify) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                break;
        }
        foreach ($extraOpts as $opt => $val) curl_setopt($ch, $opt, $val);
        $res = curl_exec($ch);
        if ($res === false) {
            Yii::warning($this->name() . ': curl ' . $method . ' ' . $url . ': ' . curl_error($ch), __CLASS__);
            $res = '';
        }
        curl_close($ch);
        return strip_tags((string) $res);
    }
}
