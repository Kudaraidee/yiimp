<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Coins;
use app\models\Markets;

class KlingexDriver extends ExchangeDriver
{
    private const BASE_URL = 'https://api.klingex.io';

    public function name(): string { return 'klingex'; }
    public function marketUrl(string $symbol, string $base = 'USDT'): string
    {
        return 'https://klingex.io/market/' . strtoupper($symbol) . '_' . strtoupper($base);
    }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    private function fetchTickers(): array
    {
        $raw  = $this->curlRequest('GET', self::BASE_URL . '/api/tickers');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $list = Markets::find()->where(['like', 'name', 'klingex%', false])->all();
        if (empty($list)) return;

        $tickers = $this->fetchTickers();
        if (empty($tickers)) return;

        $index = [];
        foreach ($tickers as $ticker) {
            if (isset($ticker['ticker_id'])) {
                $index[strtoupper($ticker['ticker_id'])] = $ticker;
            }
        }

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) continue;
            $symbol = $coin->getOfficialSymbol();
            if ($this->marketDisabled($symbol, $market)) continue;

            $base   = strtoupper($market->base_coin ?: 'USDT'); // guard: base_coin should always be USDT
            $ticker = $index[strtoupper($symbol) . '_' . $base] ?? null;
            if (!$ticker) continue;

            $bid = (float) ($ticker['bid'] ?? 0);
            $ask = (float) ($ticker['ask'] ?? 0);
            if (!$bid && !$ask) continue;

            $price2            = ($bid + $ask) / 2;
            $market->price     = $this->averageIncrement((float) $market->price, $bid);
            $market->price2    = $this->averageIncrement((float) $market->price2, $price2);
            $market->pricetime = time();
            $market->save();

            if (empty($coin->price) && $ask) {
                $coin->price  = $market->price;
                $coin->price2 = $price2;
                $coin->save();
            }
        }
    }

    public function discoverCoins(): void
    {
        if ($this->isDisabled()) return;

        $tickers = $this->fetchTickers();
        if (empty($tickers)) return;

        $this->softDeleteMarkets();

        // Klingex is USDT-only. Any market row stored with base_coin NULL/empty
        // (the BTC convention) was created incorrectly — migrate it in-place so
        // upsertMarket can find and reactivate it rather than leaving a phantom
        // BTC row behind alongside a duplicate USDT row.
        Yii::$app->db->createCommand(
            "UPDATE markets SET base_coin = 'USDT' WHERE name = :ex AND (base_coin IS NULL OR base_coin = '')",
            [':ex' => $this->name()]
        )->execute();

        foreach ($tickers as $ticker) {
            $symbol = strtoupper($ticker['base_currency'] ?? '');
            $base   = strtoupper($ticker['target_currency'] ?? '');
            if (!$symbol || !$base) continue;
            $this->upsertMarket($symbol, $symbol, $base);
        }
    }
}
