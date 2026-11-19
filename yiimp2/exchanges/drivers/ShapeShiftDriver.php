<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Coins;
use app\models\Markets;

class ShapeShiftDriver extends ExchangeDriver
{
    private const BASE = 'https://shapeshift.io';

    public function name(): string { return 'shapeshift'; }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $list = Markets::find()->where(['like', 'name', 'shapeshift%', false])->all();
        if (empty($list)) return;

        $markets = $this->apiGet('marketinfo');
        if (!is_array($markets) || empty($markets)) return;

        foreach ($list as $market) {
            $coin = Coins::findOne((int)$market->coinid);
            if (!$coin) continue;
            if ($this->marketDisabled($coin->symbol, $market)) continue;

            $symbol = $coin->getOfficialSymbol();
            $pair   = empty($market->base_coin)
                ? strtoupper($symbol) . '_BTC'
                : strtoupper($symbol) . '_' . strtoupper($market->base_coin);

            foreach ($markets as $ticker) {
                if ($ticker['pair'] !== $pair) continue;
                $market->price     = $this->averageIncrement((float)$market->price,  (float)$ticker['rate']);
                $market->price2    = $this->averageIncrement((float)$market->price2, (float)$ticker['rate']);
                $market->txfee     = $ticker['minerFee'] * 100;
                $market->pricetime = time();
                $market->priority  = -1;
                $market->save();
                if (empty($coin->price2)) {
                    $coin->price  = $market->price;
                    $coin->price2 = $market->price2;
                    $coin->save();
                }
            }
        }
    }

    public function discoverCoins(): void
    {
        if ($this->isDisabled()) return;

        $list = $this->apiGet('getcoins');
        if (!is_array($list) || empty($list)) return;

        $this->softDeleteMarkets();
        foreach ($list as $item) {
            if (($item['status'] ?? '') !== 'available') continue;
            $this->upsertMarket(strtoupper($item['symbol']), trim($item['name']));
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function apiGet(string $method, string $param = ''): mixed
    {
        $url = self::BASE . '/' . $method . ($param !== '' ? '/' . $param : '');
        $raw = $this->curlRequest('GET', $url);
        return $raw ? json_decode($raw, true) : null;
    }
}
