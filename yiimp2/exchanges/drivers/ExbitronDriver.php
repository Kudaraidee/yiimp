<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Balances;
use app\models\Coins;
use app\models\Markets;
use app\models\Orders;

class ExbitronDriver extends ExchangeDriver
{
    public function name(): string { return 'exbitron'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://app.exbitron.com/exchange/?market={$symbol}-{$base}"; }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }
    public function supportsTrading(): bool  { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    protected function publicApiQuery(string $method, string $params = '', string $returnType = 'object'): mixed
    {
        $url = "https://api.exbitron.com/api/v1/{$method}";
        if ($params !== '') $url .= "?{$params}";
        $raw = $this->curlRequest('GET', $url, [], '', false);
        return $returnType === 'object' ? json_decode($raw) : json_decode($raw, true);
    }

    // ── Authenticated API ─────────────────────────────────────────────────────

    protected function authenticatedRequest(string $method, mixed $params = [], string $httpMethod = 'GET', string $returnType = 'object'): mixed
    {
        $secret = defined('EXCH_EXBITRON_SECRET') ? EXCH_EXBITRON_SECRET : '';
        if (empty($secret)) return false;

        $base    = 'https://api.exbitron.com';
        $path    = '/api/v1/' . $method;
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $secret,
        ];

        $payload = '';
        $url     = $base . $path;
        if ($httpMethod === 'POST') {
            $payload = is_string($params) ? $params : json_encode($params);
        } elseif (!empty($params)) {
            $request = is_array($params) ? http_build_query($params) : $params;
            $url    .= '?' . $request;
        }

        $raw = $this->curlRequest($httpMethod, $url, $headers, $payload, false);
        // handle rate limiting
        if ($raw === '' && $httpMethod !== 'GET') {
            sleep(5);
            $raw = $this->curlRequest($httpMethod, $url, $headers, $payload, false);
        }
        return $returnType === 'object' ? json_decode($raw) : json_decode($raw, true);
    }

    // ── Cancel order ──────────────────────────────────────────────────────────

    public function cancelOrder(string $orderId): bool
    {
        $res = $this->authenticatedRequest('order/' . $orderId . '/cancel', [], 'GET', 'array');
        if (!is_array($res)) return false;

        Orders::deleteAll(['market' => $this->name(), 'uuid' => $orderId]);
        return true;
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $list = Markets::find()->where(['like', 'name', 'exbitron%', false])->all();
        if (empty($list)) return;
        $data = $this->publicApiQuery('cmc/summary');
        if (!is_array($data)) return;

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) continue;
            $symbol = $coin->getOfficialSymbol();
            $pair   = strtoupper($symbol . '_' . ($market->base_coin ?: 'BTC'));
            if ($this->marketDisabled($symbol, $market)) continue;

            foreach ($data as $ticker) {
                if (strtoupper($ticker->trading_pairs) !== $pair) continue;
                $price2         = ($ticker->highest_bid + $ticker->lowest_ask) / 2;
                $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker->highest_bid);
                $market->pricetime = time();
                $market->save();
                if (empty($coin->price) && $ticker->lowest_ask) {
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

        $list = $this->publicApiQuery('cmc/summary');
        if (!is_array($list) || empty($list)) return;

        $this->softDeleteMarkets();
        foreach ($list as $data) {
            $base   = strtoupper($data->quote_currency);
            $symbol = strtoupper($data->base_currency);
            if ($symbol === 'BTC' && in_array($base, ['USDT','USDC'], true)) {
                [$symbol, $base] = [$base, $symbol];
            } elseif ($symbol === 'BTC') {
                continue;
            }
            $this->upsertMarket($symbol, $symbol, $base === 'BTC' ? null : $base);
        }
    }

    public function trade(): void
    {
        if ($this->isDisabled()) return;

        $balancesResult = $this->authenticatedRequest('balances', ['zero' => 'true'], 'GET', 'array');
        if (!is_array($balancesResult) || ($balancesResult['status'] ?? '') !== 'OK') return;

        $balances    = $balancesResult['data']['user']['currencies'] ?? [];
        $saveBalance = Balances::find()->where(['name' => $this->name()])->one();

        foreach ($balances as $balance) {
            $sym = strtoupper($balance['id']);
            if ($sym === 'BTC') {
                if ($saveBalance) {
                    $saveBalance->balance = (float) $balance['balance'];
                    $saveBalance->onsell  = (float) $balance['lockedBalance'];
                    $saveBalance->save();
                }
                continue;
            }
            $coins = Coins::find()->where(['or', ['symbol' => $sym], ['symbol2' => $sym]])->all();
            foreach ($coins as $coin) {
                $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
                if (!$market) continue;
                $market->balance     = (float) $balance['balance'];
                $market->ontrade     = (float) $balance['lockedBalance'];
                $market->balancetime = time();
                $market->save();
            }
        }

        if (!defined('YIIMP_ALLOW_EXCHANGE') || !YIIMP_ALLOW_EXCHANGE) return;

        $flushAll     = rand(0, 8) === 0;
        $minBtcTrade  = 0.000001;
        $cancelAskPct = 1.20;

        foreach ($balances as $balance) {
            $sym = strtoupper($balance['id']);
            if ($sym === 'BTC') continue;
            if (!$balance['balance'] && !$balance['lockedBalance']) continue;

            $marketId    = $sym . '-BTC';
            $orderResult = $this->authenticatedRequest('order/market/' . $marketId, ['status' => 'open'], 'GET', 'array');
            if (!is_array($orderResult) || ($orderResult['status'] ?? '') !== 'OK') continue;

            $orderBook = $this->authenticatedRequest('orderbook/' . $marketId, [], 'GET', 'array');
            if (!is_array($orderBook)) continue;

            $ask = 0.0; $bid = 0.0;
            foreach ($orderBook['bids'] ?? [] as $b) { if ($b[0] > $bid) $bid = $b[0]; }
            foreach ($orderBook['asks'] ?? [] as $a) { if (!$ask || $a[0] < $ask) $ask = $a[0]; }

            $coin = Coins::find()->where(['symbol' => $sym, 'dontsell' => 0])->one();
            if (!$coin) continue;
            $symbol = !empty($coin->symbol2) ? $coin->symbol2 : $coin->symbol;
            $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
            if (!$market) continue;

            $orders   = $orderResult['data']['userOrders']['result'] ?? [];
            $exOrders = [];
            foreach ($orders as $order) {
                if (stripos($order['side'], 'sell') === false) continue;
                $exOrders[$order['id']] = $order;
                $sp = Yii::$app->ConversionUtils->bitcoinvaluetoa($order['price']);
                if ($sp > Yii::$app->ConversionUtils->bitcoinvaluetoa($ask) * $cancelAskPct || $flushAll) {
                    $this->cancelOrder($order['id']);
                } else {
                    $dbOrder = Orders::find()->where(['market' => $this->name(), 'uuid' => $order['id']])->one();
                    if ($dbOrder) continue;
                    $dbOrder = new Orders();
                    $dbOrder->market  = $this->name(); $dbOrder->coinid  = $coin->id;
                    $dbOrder->amount  = $order['amount']; $dbOrder->price = $sp;
                    $dbOrder->ask     = $ask; $dbOrder->bid = $bid;
                    $dbOrder->uuid    = $order['id']; $dbOrder->created = time();
                    $dbOrder->save();
                }
            }

            $dbOrders = Orders::find()->where(['coinid' => $coin->id, 'market' => $this->name()])->all();
            foreach ($dbOrders as $dbOrder) {
                if (!isset($exOrders[$dbOrder->uuid])) $dbOrder->delete();
            }

            if ($coin->dontsell) continue;
            $market->lasttraded = time(); $market->save();

            $amount = (float) $balance['balance'];
            if (!$amount || $amount * $coin->price < $minBtcTrade) continue;

            $precision = 8;
            if ($coin->sellonbid) {
                for ($i = 0; $i < 5 && $amount > 0; $i++) {
                    $nb = $orderBook['bids'][$i] ?? null;
                    if (!$nb || $amount * 1.1 < $nb[1]) break;
                    $sp = Yii::$app->ConversionUtils->bitcoinvaluetoa($nb[0], $precision);
                    $sa = min($amount, $nb[1]);
                    if ($sa * $sp < $minBtcTrade) continue;
                    $res = $this->authenticatedRequest('order', json_encode(['market' => strtoupper($symbol) . '-BTC', 'price' => (float) $sp, 'side' => 'sell', 'type' => 'limit', 'amount' => $sa]), 'POST', 'array');
                    if (!is_array($res)) break;
                    $amount -= $sa;
                }
            }
            if ($amount <= 0) continue;

            $sp = $coin->sellonbid
                ? Yii::$app->ConversionUtils->bitcoinvaluetoa($bid, $precision)
                : Yii::$app->ConversionUtils->bitcoinvaluetoa($ask - pow(10, -$precision), $precision);
            if ($amount * $sp < $minBtcTrade) continue;

            $res = $this->authenticatedRequest('order', json_encode(['market' => strtoupper($symbol) . '-BTC', 'price' => (float) $sp, 'side' => 'sell', 'type' => 'limit', 'amount' => $amount]), 'POST', 'array');
            if (!is_array($res) || ($res['status'] ?? '') !== 'OK') continue;

            $dbOrder = new Orders();
            $dbOrder->market  = $this->name(); $dbOrder->coinid  = $coin->id;
            $dbOrder->amount  = $amount; $dbOrder->price = $sp;
            $dbOrder->ask     = $ask; $dbOrder->bid = $bid;
            $dbOrder->uuid    = $res['id'] ?? ''; $dbOrder->created = time();
            $dbOrder->save();
        }
    }
}
