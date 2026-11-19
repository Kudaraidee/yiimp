<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Balances;
use app\models\Coins;
use app\models\Markets;
use app\models\Orders;

class NestexDriver extends ExchangeDriver
{
    public function name(): string { return 'nestex'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://trade.nestex.one/spot/{$symbol}"; }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }
    public function supportsTrading(): bool  { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    protected function publicApiQuery(string $method = 'cg/tickers', string $params = '', string $returnType = 'array'): mixed
    {
        $url = "https://trade.nestex.one/api/{$method}";
        if ($params !== '') $url .= "?{$params}";
        $raw = $this->curlRequest('GET', $url, [], '', false);
        return $returnType === 'object' ? json_decode($raw) : json_decode($raw, true);
    }

    // ── Authenticated API ─────────────────────────────────────────────────────

    protected function authenticatedRequest(string $method, array $params = [], string $httpMethod = 'POST', string $returnType = 'array'): mixed
    {
        $key    = defined('EXCH_NESTEX_KEY')    ? EXCH_NESTEX_KEY    : '';
        $secret = defined('EXCH_NESTEX_SECRET') ? EXCH_NESTEX_SECRET : '';
        if (empty($secret)) return false;

        $reqParams = array_merge(['apikey' => $key, 'apisecret' => $secret], $params);
        ksort($reqParams);

        $url     = "https://trade.nestex.one/api/v2/{$method}";
        $payload = json_encode($reqParams);
        $headers = ['Content-Type: application/json'];

        $raw = $this->curlRequest($httpMethod, $url, $headers, $httpMethod === 'POST' ? $payload : '');
        $statusKey = '__http_status__';

        if (str_contains($raw, '"error"')) {
            Yii::warning($this->name() . ": {$method} error: " . $raw, __CLASS__);
        }

        return $returnType === 'object' ? json_decode($raw) : json_decode($raw, true);
    }

    // ── Cancel order ──────────────────────────────────────────────────────────

    public function cancelOrder(string $orderId): bool
    {
        $res = $this->authenticatedRequest('cancelorder', ['order_id' => $orderId], 'POST', 'array');
        if (!is_array($res)) return false;

        Orders::deleteAll(['market' => $this->name(), 'uuid' => $orderId]);
        return true;
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $list = Markets::find()->where(['like', 'name', 'nestex%', false])->all();
        if (empty($list)) return;
        $data = $this->publicApiQuery();
        if (!is_array($data)) return;

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) continue;
            $symbol = strtoupper($coin->getOfficialSymbol());
            $pair   = $symbol . '_USDT';
            if ($this->marketDisabled($symbol, $market)) continue;

            foreach ($data as $ticker) {
                if (!isset($ticker['ticker_id'])) continue;
                if (strtoupper($ticker['ticker_id']) !== $pair) continue;
                $bid = (float) $ticker['bid'];
                $ask = (float) $ticker['ask'];
                if (!$bid || !$ask) continue;
                $price2         = ($bid + $ask) / 2;
                $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                $market->price  = $this->averageIncrement((float) $market->price,  $bid);
                $market->pricetime = time();
                $market->save();
                if (empty($coin->price) && $ask) {
                    $coin->price  = $market->price;
                    $coin->price2 = $price2;
                    $coin->save();
                }
                break;
            }
        }
    }

    public function discoverCoins(): void
    {
        if ($this->isDisabled()) return;

        $list = $this->publicApiQuery();
        if (!is_array($list) || empty($list)) return;

        $this->softDeleteMarkets();
        foreach ($list as $data) {
            if (empty($data['base_currency']) || empty($data['target_currency'])) continue;
            $symbol = strtoupper($data['base_currency']);
            $base   = strtoupper($data['target_currency']);
            if ($base !== 'USDT') continue;
            $this->upsertMarket($symbol, $symbol, $base);
        }
    }

    public function trade(): void
    {
        if ($this->isDisabled()) return;

        $balances = $this->authenticatedRequest('balances', [], 'POST', 'array');
        if (!is_array($balances) || !isset($balances['balances'])) return;

        $prices = $this->publicApiQuery('cg/tickers', '', 'array');
        if (!is_array($prices)) return;

        $summary = []; $usdtPrice = 0.0;
        foreach ($prices as $m) {
            if (($m['target_currency'] ?? '') === 'USDT') {
                $summary[$m['base_currency']] = $m;
                if ($m['base_currency'] === 'BTC') $usdtPrice = (float) $m['last_price'];
            }
        }

        $btcBal = 0.0; $btcLock = 0.0;
        foreach ($balances['balances'] as $currency => $balance) {
            $locked = (float) ($balances['locked'][$currency] ?? 0);
            $sym    = strtoupper($currency);
            if ($sym === 'BTC')  { $btcBal += (float) $balance - $locked; $btcLock += $locked; continue; }
            if ($sym === 'USDT' && $usdtPrice > 0) { $btcBal += ((float) $balance - $locked) / $usdtPrice; $btcLock += $locked / $usdtPrice; }
            $coins = Coins::find()->where(['or', ['symbol' => $sym], ['symbol2' => $sym]])->all();
            foreach ($coins as $coin) {
                $market = Markets::find()->where(['coinid' => $coin->id])->andWhere(['like', 'name', 'nestex%', false])->one();
                if (!$market) continue;
                $market->balance = (float) $balance - $locked; $market->ontrade = $locked;
                $market->balancetime = time(); $market->save();
            }
        }

        $saveBalance = Balances::find()->where(['name' => $this->name()])->one();
        if ($saveBalance) { $saveBalance->balance = $btcBal; $saveBalance->onsell = $btcLock; $saveBalance->save(); }

        if (!defined('YIIMP_ALLOW_EXCHANGE') || !YIIMP_ALLOW_EXCHANGE) return;

        $ordersBook   = $this->authenticatedRequest('orders', [], 'POST', 'array');
        $flushAll     = rand(0, 8) === 0;
        $cancelAskPct = 1.20;
        $minTrade     = 0.1;

        foreach ($balances['balances'] as $currency => $balance) {
            $sym = strtoupper($currency);
            if ($sym === 'USDT') continue;
            $coin = Coins::find()->where(['symbol' => $sym])->one();
            if (!$coin) continue;
            $market = Markets::find()->where(['coinid' => $coin->id, 'name' => 'nestex USDT'])->one();
            if (!$market) continue;
            $locked   = (float) ($balances['locked'][$currency] ?? 0);
            $exOrders = [];
            if (is_array($ordersBook['orders'] ?? null)) {
                foreach ($ordersBook['orders'] as $order) {
                    if (strtoupper($order['cur']) !== $sym || stripos($order['order_type'], 'SELL') === false) continue;
                    $exOrders[$order['order_id']] = $order;
                    if (!isset($summary[$sym])) continue;
                    $ask = Yii::$app->ConversionUtils->bitcoinvaluetoa($summary[$sym]['ask']);
                    $sp  = Yii::$app->ConversionUtils->bitcoinvaluetoa($order['price']);
                    if ($sp > $ask * $cancelAskPct || $flushAll) {
                        $this->cancelOrder($order['order_id']);
                    } else {
                        $dbOrder = Orders::find()->where(['market' => $this->name(), 'uuid' => $order['order_id']])->one();
                        if ($dbOrder) continue;
                        $dbOrder = new Orders();
                        $dbOrder->market  = $this->name(); $dbOrder->coinid  = $coin->id;
                        $dbOrder->amount  = $order['quantity']; $dbOrder->price = $sp;
                        $dbOrder->ask     = $summary[$sym]['ask']; $dbOrder->bid = $summary[$sym]['bid'];
                        $dbOrder->uuid    = $order['order_id']; $dbOrder->created = time();
                        $dbOrder->save();
                    }
                }
            }
            $dbOrders = Orders::find()->where(['coinid' => $coin->id, 'market' => $this->name()])->all();
            foreach ($dbOrders as $dbOrder) { if (!isset($exOrders[$dbOrder->uuid])) $dbOrder->delete(); }
            if ($coin->dontsell) continue;
            $market->lasttraded = time(); $market->save();
            $available = (float) $balance - $locked;
            if (!$available || !isset($summary[$sym])) continue;
            $precision = 10;
            $sp = $coin->sellonbid
                ? Yii::$app->ConversionUtils->bitcoinvaluetoa($summary[$sym]['bid'], $precision)
                : Yii::$app->ConversionUtils->bitcoinvaluetoa($summary[$sym]['ask'] - pow(10, -$precision), $precision);
            if ($available * $sp < $minTrade) continue;
            $res = $this->authenticatedRequest('placelimitorder', ['cur' => $sym, 'price' => number_format($sp, 10), 'side' => 'SELL', 'qty' => "$available"], 'POST', 'array');
            if (!is_array($res) || isset($res['error'])) continue;
            $dbOrder = new Orders();
            $dbOrder->market  = $this->name(); $dbOrder->coinid  = $coin->id;
            $dbOrder->amount  = $available; $dbOrder->price = $sp;
            $dbOrder->ask     = $summary[$sym]['ask']; $dbOrder->bid = $summary[$sym]['bid'];
            $dbOrder->uuid    = $res['order_id'] ?? ''; $dbOrder->created = time();
            $dbOrder->save();
        }
    }
}
