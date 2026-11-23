<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Balances;
use app\models\Coins;
use app\models\Markets;
use app\models\Orders;

/**
 * KuCoin REST API v2 (Classic/Spot) driver.
 *
 * The retired v1 API (`/v1/...`, KC-API-NONCE) is gone. All calls now use
 * `/api/v1/...` with timestamp + passphrase signing.
 */
class KuCoinDriver extends ExchangeDriver
{
    private const API = 'https://api.kucoin.com';

    public function name(): string { return 'kucoin'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://www.kucoin.com/trade/{$symbol}-{$base}"; }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }
    public function supportsTrading(): bool  { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    protected function publicApiQuery(string $method, string $params = '', string $returnType = 'object'): mixed
    {
        $url = self::API . '/api/v1/' . ltrim($method, '/');
        if ($params !== '') $url .= '?' . $params;
        $raw = $this->curlRequest('GET', $url, [], '', true, [CURLOPT_ENCODING => '']);
        $ret = $returnType === 'object' ? json_decode($raw) : json_decode($raw, true);
        if (!$ret) {
            Yii::warning($this->name() . ": {$method} returned unexpected data", __CLASS__);
            return false;
        }
        return $ret;
    }

    private function kucoinResultValid(mixed $obj): bool
    {
        if (!is_object($obj) || !isset($obj->data)) return false;
        if (isset($obj->code) && (string) $obj->code !== '200000') return false;
        return true;
    }

    // ── Authenticated API (v2 signature scheme) ──────────────────────────────

    /**
     * @param string $endpoint  Full API path, e.g. /api/v1/accounts
     * @param array|null $params Query params (GET/DELETE) or JSON body (POST)
     */
    protected function apiRequest(string $endpoint, ?array $params = null, string $httpMethod = 'GET'): mixed
    {
        $key        = defined('EXCH_KUCOIN_KEY')         ? EXCH_KUCOIN_KEY         : '';
        $secret     = defined('EXCH_KUCOIN_SECRET')      ? EXCH_KUCOIN_SECRET      : '';
        $passphrase = defined('EXCH_KUCOIN_PASSPHRASE')  ? EXCH_KUCOIN_PASSPHRASE  : '';
        $keyVersion = defined('EXCH_KUCOIN_KEY_VERSION') ? EXCH_KUCOIN_KEY_VERSION : '2';

        if (empty($key) || empty($secret) || empty($passphrase)) return false;

        $params = $params ?? [];
        $httpMethod = strtoupper($httpMethod);
        $body = '';
        if ($params) {
            if ($httpMethod === 'POST') {
                $body = json_encode($params, JSON_UNESCAPED_SLASHES);
            } else {
                $endpoint .= (str_contains($endpoint, '?') ? '&' : '?') . http_build_query($params);
            }
        }

        $timestamp = (string) (int) (microtime(true) * 1000);
        // v2 prehash: timestamp + UPPERCASE method + endpoint (with query) + body
        $toSign = $timestamp . $httpMethod . $endpoint . $body;
        $sign   = base64_encode(hash_hmac('sha256', $toSign, $secret, true));
        $pass   = base64_encode(hash_hmac('sha256', $passphrase, $secret, true));

        $headers = [
            'Content-Type: application/json',
            'KC-API-KEY: ' . $key,
            'KC-API-SIGN: ' . $sign,
            'KC-API-TIMESTAMP: ' . $timestamp,
            'KC-API-PASSPHRASE: ' . $pass,
            'KC-API-KEY-VERSION: ' . $keyVersion,
        ];

        $raw = $this->curlRequest(
            $httpMethod,
            self::API . $endpoint,
            $headers,
            $body,
            true,
            [CURLOPT_ENCODING => '']
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
        $res = $this->apiRequest('/api/v1/orders/' . rawurlencode($orderId), null, 'DELETE');
        if (!$this->kucoinResultValid($res)) return false;

        Orders::deleteAll(['market' => $this->name(), 'uuid' => $orderId]);
        return true;
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $list = Markets::find()->where(['like', 'name', 'kucoin%', false])->all();
        if (empty($list)) return;

        $symbols = $this->publicApiQuery('symbols', 'market=BTC');
        if (!$this->kucoinResultValid($symbols) || empty($symbols->data)) return;
        usleep(500);
        $markets = $this->publicApiQuery('market/allTickers');
        if (!$this->kucoinResultValid($markets) || !isset($markets->data->ticker)) return;
        $tickers = $markets->data->ticker;

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) continue;
            $symbol = $coin->getOfficialSymbol();
            if ($this->marketDisabled($symbol, $market)) continue;

            $pair          = strtoupper($symbol) . '-BTC';
            $enableTrading = false;
            foreach ($symbols->data as $sym) {
                if (($sym->symbol ?? null) !== $pair) continue;
                $enableTrading = (bool) ($sym->enableTrading ?? false);
                break;
            }

            if ($market->disabled == (int) $enableTrading) {
                $market->disabled = (int) (!$enableTrading);
                $market->save();
                if ($market->disabled) continue;
            }

            foreach ($tickers as $ticker) {
                if (($ticker->symbol ?? null) !== $pair) continue;
                if (!isset($ticker->buy) || $ticker->buy == -1) continue;
                $market->price     = $this->averageIncrement((float) $market->price,  (float) $ticker->buy);
                $market->price2    = $this->averageIncrement((float) $market->price2, (float) ($ticker->sell ?? $ticker->buy));
                $market->priority  = -1;
                $market->pricetime = time();
                if ((float) ($ticker->vol ?? 0) > 0.01) $market->save();
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

        $list = $this->publicApiQuery('currencies');
        if (!$this->kucoinResultValid($list) || empty($list->data)) return;

        $this->softDeleteMarkets();
        foreach ($list->data as $item) {
            // v2 uses "currency" instead of the old "name" field.
            $symbol = $item->currency ?? $item->name ?? null;
            if (!$symbol) continue;
            $this->upsertMarket($symbol, $item->fullName ?? $symbol);
        }
    }

    public function trade(): void
    {
        if ($this->isDisabled()) return;

        // v2: GET /api/v1/accounts returns all currency accounts at once.
        $data = $this->apiRequest('/api/v1/accounts');
        if (!$this->kucoinResultValid($data) || empty($data->data)) return;

        $saveBalance = Balances::find()->where(['name' => $this->name()])->one();
        foreach ($data->data as $account) {
            $currency = $account->currency ?? null;
            if (!$currency) continue;

            $total = (float) ($account->balance ?? ((float) ($account->available ?? 0) + (float) ($account->holds ?? 0)));
            $held  = (float) ($account->holds ?? 0);

            if ($currency === 'BTC') {
                if ($saveBalance) {
                    $saveBalance->balance = $total;
                    $saveBalance->onsell  = $held;
                    $saveBalance->save();
                }
                continue;
            }

            $coins = Coins::find()->where(['or', ['symbol' => $currency], ['symbol2' => $currency]])->all();
            foreach ($coins as $coin) {
                $markets = $this->marketsForCoin($coin);
                if (empty($markets)) continue;
                foreach ($markets as $market) {
                    $market->balance     = $total;
                    $market->ontrade     = $held;
                    $market->balancetime = time();
                    $market->save();
                }
                $cacheKey = $this->name() . "-deposit_address-check-{$coin->symbol}";
                if ($coin->installed && !Yii::$app->cache->get($cacheKey)) {
                    sleep(1);
                    $obj = $this->apiRequest('/api/v1/deposit-addresses', ['currency' => $coin->symbol]);
                    if (!$this->kucoinResultValid($obj) || empty($obj->data)) continue;
                    $addr = $obj->data[0]->address ?? null;
                    if (!empty($addr)) {
                        foreach ($markets as $row) {
                            $row->deposit_address = $addr;
                            $row->save();
                        }
                        Yii::info($this->name() . ": deposit address for {$coin->symbol} updated", __CLASS__);
                    }
                    Yii::$app->cache->set($cacheKey, time(), 24 * 3600);
                }
            }
        }
    }
}
