<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Balances;
use app\models\Coins;
use app\models\Markets;
use app\models\Orders;

class BinanceDriver extends ExchangeDriver
{
    public function name(): string { return 'binance'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://www.binance.com/trade.html?symbol={$symbol}_{$base}"; }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }
    public function supportsTrading(): bool  { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    protected function publicApiQuery(string $method): mixed
    {
        $raw = $this->curlRequest('GET', "https://www.binance.com/api/v1/{$method}");
        $obj = json_decode($raw);
        if (!is_object($obj) && !is_array($obj)) {
            Yii::warning($this->name() . ": {$method} returned unexpected data", __CLASS__);
        }
        return $obj;
    }

    // ── Authenticated API ─────────────────────────────────────────────────────

    protected function authenticatedRequest(string $method, array $params = [], string $httpMethod = 'GET'): mixed
    {
        $key    = defined('EXCH_BINANCE_KEY')    ? EXCH_BINANCE_KEY    : '';
        $secret = defined('EXCH_BINANCE_SECRET') ? EXCH_BINANCE_SECRET : '';
        if (empty($key) || empty($secret)) return false;

        $mt = explode(' ', microtime());
        $params['timestamp'] = $mt[1] . substr($mt[0], 2, 3);
        $query   = http_build_query($params, '', '&');
        $sig     = strtolower(hash_hmac('sha256', $query, $secret));
        $isPost  = ($httpMethod === 'POST');
        $headers = [
            'Content-Type: application/json;charset=UTF-8',
            'X-MBX-APIKEY: ' . $key,
        ];

        if ($isPost) {
            $raw = $this->curlRequest('POST', "https://api.binance.com/api/v3/{$method}", $headers, $query . '&signature=' . $sig);
        } else {
            $url = "https://api.binance.com/api/v3/{$method}?{$query}&signature={$sig}";
            $raw = $this->curlRequest($httpMethod, $url, $headers);
        }

        $result = json_decode($raw);
        if (!is_object($result) && !is_array($result)) {
            Yii::warning($this->name() . ": {$method} returned unexpected data", __CLASS__);
        }
        return $result;
    }

    // ── Cancel order ──────────────────────────────────────────────────────────

    public function cancelOrder(string $orderId): bool
    {
        $dbOrder = Orders::find()->where(['market' => $this->name(), 'uuid' => $orderId])->one();
        if (!$dbOrder) return false;
        $coin = Coins::findOne((int) $dbOrder->coinid);
        if (!$coin) return false;

        $key    = defined('EXCH_BINANCE_KEY')    ? EXCH_BINANCE_KEY    : '';
        $secret = defined('EXCH_BINANCE_SECRET') ? EXCH_BINANCE_SECRET : '';
        if (empty($key) || empty($secret)) return false;

        $mt    = explode(' ', microtime());
        $query = http_build_query([
            'symbol'    => $coin->getOfficialSymbol() . 'BTC',
            'orderId'   => $orderId,
            'timestamp' => $mt[1] . substr($mt[0], 2, 3),
        ]);
        $sig  = hash_hmac('sha256', $query, $secret);
        $url  = "https://api.binance.com/api/v3/order?{$query}&signature={$sig}";

        $raw = $this->curlRequest('DELETE', $url, ['X-MBX-APIKEY: ' . $key]);
        $obj = json_decode($raw);
        if (is_object($obj) && isset($obj->code)) return false;

        Orders::deleteAll(['market' => $this->name(), 'uuid' => $orderId]);
        return true;
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $list = Markets::find()->where(['like', 'name', 'binance%', false])->all();
        if (empty($list)) return;
        $tickers = $this->publicApiQuery('ticker/allBookTickers');
        if (!is_array($tickers)) return;

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) continue;
            $symbol = $coin->getOfficialSymbol();
            if ($this->marketDisabled($symbol, $market)) continue;

            $pair = $symbol . 'BTC';
            foreach ($tickers as $ticker) {
                if ($pair !== $ticker->symbol) continue;
                $price2         = ($ticker->bidPrice + $ticker->askPrice) / 2;
                $market->price  = $this->averageIncrement((float) $market->price,  (float) $ticker->bidPrice);
                $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                $market->pricetime = time();
                if ($market->disabled < 9) {
                    $market->disabled = (int) ((float) $ticker->bidQty < 0.01);
                }
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

        $list = $this->publicApiQuery('ticker/allBookTickers');
        if (!is_array($list)) return;

        $this->softDeleteMarkets();
        foreach ($list as $ticker) {
            $base = substr($ticker->symbol, -3);
            if ($base !== 'BTC') continue;
            $symbol = substr($ticker->symbol, 0, strlen($ticker->symbol) - 3);
            $this->upsertMarket($symbol);
        }
    }

    public function trade(): void
    {
        if ($this->isDisabled()) return;

        $data = $this->authenticatedRequest('account');
        if (!is_object($data) || empty($data->balances)) return;

        $saveBalance = Balances::find()->where(['name' => $this->name()])->one();
        foreach ($data->balances as $balance) {
            if ($balance->asset === 'BTC') {
                if ($saveBalance) {
                    $saveBalance->balance = (float) $balance->free;
                    $saveBalance->onsell  = (float) $balance->locked;
                    $saveBalance->save();
                }
                continue;
            }
            $coins = Coins::find()->where(['or', ['symbol' => $balance->asset], ['symbol2' => $balance->asset]])->all();
            foreach ($coins as $coin) {
                $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->orderBy('balance')->one();
                if (!$market) continue;
                $market->balance = (float) $balance->free;
                $market->ontrade = (float) $balance->locked;
                $market->balancetime = time();
                $market->save();
            }
        }
    }
}
