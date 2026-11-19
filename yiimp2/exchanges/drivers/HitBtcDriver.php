<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Coins;
use app\models\Markets;
use app\models\Orders;

class HitBtcDriver extends ExchangeDriver
{
    public function name(): string { return 'hitbtc'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://hitbtc.com/exchange/{$symbol}-to-{$base}"; }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    protected function publicApiQuery(string $method, string $params = '', string $returnType = 'object'): mixed
    {
        $url = "https://api.hitbtc.com/api/1/public/{$method}";
        if ($params !== '') $url .= "?{$params}";
        $raw = $this->curlRequest('GET', $url, [], '', true, [CURLOPT_ENCODING => '']);
        return $returnType === 'object' ? json_decode($raw) : json_decode($raw, true);
    }

    // ── Authenticated API ─────────────────────────────────────────────────────

    protected function authenticatedRequest(string $method, array $params = [], bool $isPost = false): mixed
    {
        $key    = defined('EXCH_HITBTC_KEY')    ? EXCH_HITBTC_KEY    : '';
        $secret = defined('EXCH_HITBTC_SECRET') ? EXCH_HITBTC_SECRET : '';
        if (empty($key) || empty($secret)) return false;

        $mt    = explode(' ', microtime());
        $nonce = $mt[1] . substr($mt[0], 2, 3);
        $url   = "/api/1/{$method}?nonce={$nonce}&apikey={$key}";

        $query = http_build_query($params ?? []);
        if ($query && !$isPost) {
            $url .= '&' . $query;
            $query = '';
        }
        $hmac    = strtolower(hash_hmac('sha512', $url . $query, $secret));
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'X-Signature: ' . $hmac,
        ];

        $fullUrl = 'https://api.hitbtc.com' . $url;
        $raw = $isPost
            ? $this->curlRequest('POST', $fullUrl, $headers, $query, true, [CURLOPT_ENCODING => ''])
            : $this->curlRequest('GET',  $fullUrl, $headers, '',    true, [CURLOPT_ENCODING => '']);

        $result = json_decode($raw);
        if (!is_object($result) && !is_array($result)) {
            Yii::warning($this->name() . ": {$method} returned unexpected data", __CLASS__);
        }
        return $result;
    }

    // ── Cancel order ──────────────────────────────────────────────────────────

    public function cancelOrder(string $orderId): bool
    {
        $res = $this->authenticatedRequest('trading/cancel_order', ['clientOrderId' => $orderId], true);
        if (!is_object($res) || !isset($res->cancelledOrders)) return false;

        Orders::deleteAll(['market' => $this->name(), 'uuid' => $orderId]);
        return true;
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $list = Markets::find()->where(['like', 'name', 'hitbtc%', false])->all();
        if (empty($list)) return;
        $data = $this->publicApiQuery('ticker', '', 'array');
        if (!is_array($data) || empty($data)) return;

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) continue;
            $base   = $market->base_coin ?: 'BTC';
            $symbol = $coin->getOfficialSymbol();
            $pair   = empty($market->base_coin)
                ? strtoupper($symbol) . $base
                : strtoupper($base . $symbol);
            if ($this->marketDisabled($symbol, $market)) continue;

            foreach ($data as $p => $ticker) {
                if ($p !== $pair) continue;
                $price2         = ((float) $ticker['bid'] + (float) $ticker['ask']) / 2;
                $market->price  = $this->averageIncrement((float) $market->price,  (float) $ticker['bid']);
                $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                $market->pricetime = time();
                $market->priority  = -1;
                $market->save();
                if (empty($coin->price2) && str_contains($pair, 'BTC')) {
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

        $list = $this->publicApiQuery('symbols');
        if (!is_object($list) || !isset($list->symbols)) return;

        $this->softDeleteMarkets();
        foreach ($list->symbols as $data) {
            if (strtoupper($data->currency) !== 'BTC') continue;
            $this->upsertMarket(strtoupper($data->commodity));
        }
    }
}
