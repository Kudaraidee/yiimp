<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Coins;
use app\models\Markets;

class SafeTradeDriver extends ExchangeDriver
{
    public function name(): string { return 'safetrade'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return 'https://safetrade.com/exchange/' . strtolower($symbol) . '-' . strtolower($base); }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    protected function publicApiQuery(string $method, string $params = '', string $returnType = 'object'): mixed
    {
        $url = "https://safe.trade/api/v2/{$method}";
        if ($params !== '') $url .= "?{$params}";
        $raw = $this->curlRequest('GET', $url, [], '', false);
        return $returnType === 'object' ? json_decode($raw) : json_decode($raw, true);
    }

    // ── Authenticated API ─────────────────────────────────────────────────────

    protected function authenticatedRequest(string $method, mixed $params = [], string $httpMethod = 'GET', string $returnType = 'object'): mixed
    {
        $key    = defined('EXCH_SAFETRADE_KEY')    ? EXCH_SAFETRADE_KEY    : '';
        $secret = defined('EXCH_SAFETRADE_SECRET') ? EXCH_SAFETRADE_SECRET : '';
        if (empty($key) || empty($secret)) return false;

        $nonce   = (time() + 3) . rand(100, 999);
        $base    = 'https://safe.trade';
        $path    = '/api/v2/' . $method;
        $url     = $base . $path;
        $payload = '';

        if ($httpMethod === 'POST') {
            $payload = is_array($params) ? http_build_query($params) : $params;
        } elseif (!empty($params)) {
            $url .= '?' . (is_array($params) ? http_build_query($params) : $params);
        }

        $sig     = hash_hmac('sha256', $key . $url . $payload . $nonce, $secret);
        $headers = [
            'Content-Type: application/json',
            'X-API-KEY: '   . $key,
            'X-API-NONCE: ' . $nonce,
            'X-API-SIGN: '  . $sig,
        ];

        $raw = $this->curlRequest($httpMethod, $url, $headers, $payload, false);
        return $returnType === 'object' ? json_decode($raw) : json_decode($raw, true);
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $marketList = $this->publicApiQuery('trade/public/markets', '', 'array');
        if (empty($marketList) || !is_array($marketList)) return;

        $marketNames = [];
        foreach ($marketList as $data) {
            $marketNames[$data['id']] = ['coinsymbol' => $data['base_unit'], 'basesymbol' => $data['quote_unit']];
        }

        $tickers = $this->publicApiQuery('trade/public/tickers');
        if (!is_array($tickers)) return;

        foreach ($tickers as $key => $data) {
            if (!isset($marketNames[$key])) continue;
            $symbol = strtoupper($marketNames[$key]['coinsymbol']);
            $base   = strtoupper($marketNames[$key]['basesymbol']);
            if ($base !== 'BTC') continue;

            $coin = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin) continue;

            $market = $this->findOrCreateMarket($coin);
            if ($this->marketDisabled($coin->getOfficialSymbol(), $market)) continue;

            $bid    = (float) $data->low;
            $ask    = (float) $data->high;
            $price2 = ($ask + $bid) / 2;

            $market->price2    = $this->averageIncrement((float) $market->price2, $price2);
            $market->price     = $this->averageIncrement((float) $market->price,  $bid);
            $market->priority  = -1;
            $market->txfee     = 0.2;
            $market->pricetime = time();
            $market->save();

            if (!empty($market->price2) && (empty($coin->price2) || $coin->price2 == 0)) {
                $coin->price  = $market->price;
                $coin->price2 = $market->price2;
                $coin->save();
            }
        }
    }

    public function discoverCoins(): void
    {
        if ($this->isDisabled()) return;

        $list = $this->publicApiQuery('trade/public/markets', '', 'array');
        if (!is_array($list) || empty($list)) return;

        $this->softDeleteMarkets();
        foreach ($list as $ticker) {
            $base   = strtoupper($ticker['quote_unit']);
            $symbol = strtoupper($ticker['base_unit']);
            $this->upsertMarket($symbol, $symbol, $base === 'BTC' ? null : $base);
        }
    }
}
