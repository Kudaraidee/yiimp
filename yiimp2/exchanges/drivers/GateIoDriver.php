<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Coins;
use app\models\Markets;

class GateIoDriver extends ExchangeDriver
{
    public function name(): string { return 'gateio'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://gate.io/trade/{$symbol}_{$base}"; }
    public function supportsMarkets(): bool { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    protected function publicApiQuery(string $method, string $params = ''): mixed
    {
        $url = "https://data.gate.io/api2/1/{$method}";
        if ($params !== '') $url .= "/{$params}";
        $raw = $this->curlRequest('GET', $url, [], '', false);
        return json_decode($raw, true);
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $list = Markets::find()->where(['like', 'name', 'gateio%', false])->all();
        if (empty($list)) return;
        $tickers = $this->publicApiQuery('tickers');
        if (!is_array($tickers)) return;

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) continue;
            $symbol = $coin->getOfficialSymbol();
            if ($this->marketDisabled($symbol, $market)) continue;
            $dbPair = strtolower($symbol) . '_btc';

            foreach ($tickers as $pair => $ticker) {
                if ($pair !== $dbPair) continue;
                $price2         = ((float) $ticker['highestBid'] + (float) $ticker['lowestAsk']) / 2;
                $market->price  = $this->averageIncrement((float) $market->price,  (float) $ticker['highestBid']);
                $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                $market->pricetime = time();
                $market->priority  = -1;
                $market->txfee     = 0.2;
                $market->save();
                if (empty($coin->price2)) {
                    $coin->price  = $market->price;
                    $coin->price2 = $market->price2;
                    $coin->market = $this->name();
                    $coin->save();
                }
            }
        }
    }
}
