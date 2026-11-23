<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Coins;
use app\models\Markets;
use app\models\Orders;

/**
 * HitBTC REST API v3 driver.
 *
 * v1 (`/api/1/public/*`, nonce + apikey query signing) and v2 are retired;
 * v3 public data is map-shaped and authenticated calls use Basic auth.
 */
class HitBtcDriver extends ExchangeDriver
{
    private const API = 'https://api.hitbtc.com/api/3';

    // HitBTC rejects non-browser user agents on public endpoints.
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 ' .
                               '(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function name(): string { return 'hitbtc'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://hitbtc.com/{$symbol}-to-{$base}"; }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    protected function publicApiQuery(string $method, string $params = '', string $returnType = 'object'): mixed
    {
        $method = ltrim($method, '/');
        if ($method === 'symbols') $method = 'public/symbol';
        else if (str_starts_with($method, 'public/') === false) $method = 'public/' . $method;

        $url = self::API . '/' . $method;
        if ($params !== '') $url .= '?' . $params;

        $raw = $this->curlRequest(
            'GET',
            $url,
            [],
            '',
            true,
            [CURLOPT_ENCODING => '', CURLOPT_USERAGENT => self::USER_AGENT]
        );
        $ret = $returnType === 'object' ? json_decode($raw) : json_decode($raw, true);
        if (!is_object($ret) && !is_array($ret)) {
            Yii::warning($this->name() . ": {$method} returned unexpected data", __CLASS__);
            return false;
        }
        return $ret;
    }

    // ── Authenticated API (v3 Basic auth) ────────────────────────────────────

    /**
     * @param string $endpoint  Full v3 path, e.g. /spot/balance
     * @param array|null $params Query params for GET, JSON body for other methods
     */
    protected function authenticatedRequest(
        string $endpoint,
        ?array $params = null,
        string $httpMethod = 'GET'
    ): mixed {
        $key    = defined('EXCH_HITBTC_KEY')    ? EXCH_HITBTC_KEY    : '';
        $secret = defined('EXCH_HITBTC_SECRET') ? EXCH_HITBTC_SECRET : '';
        if (empty($key) || empty($secret)) return false;

        $httpMethod = strtoupper($httpMethod);
        $url = self::API . '/' . ltrim($endpoint, '/');
        $body = '';
        if ($params) {
            if ($httpMethod === 'GET') {
                $url .= '?' . http_build_query($params);
            } else {
                $body = json_encode($params, JSON_UNESCAPED_SLASHES);
            }
        }

        $headers = [
            'Authorization: Basic ' . base64_encode($key . ':' . $secret),
            'Content-Type: application/json; charset=utf-8',
        ];

        $raw = $this->curlRequest(
            $httpMethod,
            $url,
            $headers,
            $body,
            true,
            [CURLOPT_ENCODING => '', CURLOPT_USERAGENT => self::USER_AGENT]
        );

        $result = json_decode($raw);
        if (!is_object($result) && !is_array($result)) {
            Yii::warning($this->name() . ": {$endpoint} returned unexpected data", __CLASS__);
        }
        return $result;
    }

    // ── Cancel order ──────────────────────────────────────────────────────────

    public function cancelOrder(string $orderId): bool
    {
        $res = $this->authenticatedRequest('/spot/order/' . rawurlencode($orderId), null, 'DELETE');
        if (!is_object($res) || !in_array($res->status ?? '', ['canceled', 'new'], true)) return false;

        Orders::deleteAll(['market' => $this->name(), 'uuid' => $orderId]);
        return true;
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $list = Markets::find()->where(['like', 'name', 'hitbtc%', false])->all();
        if (empty($list)) return;

        // v3 /public/ticker returns a map keyed by concatenated symbol.
        $data = $this->publicApiQuery('ticker', '', 'array');
        if (!is_array($data) || empty($data)) return;

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) continue;
            $base   = $market->base_coin ?: 'BTC';
            $symbol = $coin->getOfficialSymbol();
            $pair   = empty($market->base_coin)
                ? strtoupper($symbol . $base)
                : strtoupper($base . $symbol);
            if ($this->marketDisabled($symbol, $market)) continue;
            if (!isset($data[$pair])) continue;

            $ticker = $data[$pair];
            $price2         = ((float) ($ticker['bid'] ?? 0) + (float) ($ticker['ask'] ?? 0)) / 2;
            $market->price  = $this->averageIncrement((float) $market->price,  (float) ($ticker['bid'] ?? 0));
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

    public function discoverCoins(): void
    {
        if ($this->isDisabled()) return;

        // v3 /public/symbol returns a map keyed by symbol.
        $list = $this->publicApiQuery('symbols', '', 'array');
        if (!is_array($list) || empty($list)) return;

        $this->softDeleteMarkets();
        foreach ($list as $pair => $data) {
            if (strtoupper($data['quote_currency'] ?? '') !== 'BTC') continue;
            $symbol = strtoupper($data['base_currency'] ?? '');
            if ($symbol === '' || $symbol === 'BTC') continue;
            $this->upsertMarket($symbol, $data['name'] ?? $symbol);
        }
    }
}
