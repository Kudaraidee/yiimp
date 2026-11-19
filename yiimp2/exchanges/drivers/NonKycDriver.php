<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Balances;
use app\models\Coins;
use app\models\Markets;
use app\models\Orders;

class NonKycDriver extends ExchangeDriver
{
    public function name(): string { return 'nonkyc'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://nonkyc.io/market/{$symbol}_{$base}"; }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }
    public function supportsTrading(): bool  { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    protected function publicApiQuery(string $method, string $params = '', string $returnType = 'object'): mixed
    {
        $url = "https://api.nonkyc.io/api/v2/{$method}";
        if ($params !== '') $url .= "?{$params}";
        $raw = $this->curlRequest('GET', $url, [], '', false);
        return $returnType === 'object' ? json_decode($raw) : json_decode($raw, true);
    }

    // ── Authenticated API ─────────────────────────────────────────────────────

    protected function authenticatedRequest(string $method, mixed $params = [], string $httpMethod = 'GET', string $returnType = 'object'): mixed
    {
        $key    = defined('EXCH_NONKYC_KEY')    ? EXCH_NONKYC_KEY    : '';
        $secret = defined('EXCH_NONKYC_SECRET') ? EXCH_NONKYC_SECRET : '';
        if (empty($key) || empty($secret)) return false;

        $nonce = (time() + 3) . rand(100, 999);
        $base  = 'https://api.nonkyc.io';
        $path  = '/api/v2/' . $method;
        $url   = $base . $path;

        $payload = '';
        if ($httpMethod === 'POST') {
            $payload = is_string($params) ? $params : json_encode($params);
        } elseif (!empty($params)) {
            $request = is_array($params) ? http_build_query($params) : $params;
            $url    .= '?' . $request;
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

    // ── Cancel order ──────────────────────────────────────────────────────────

    public function cancelOrder(string $orderId): bool
    {
        $res = $this->authenticatedRequest('cancelorder', json_encode(['id' => $orderId]), 'POST', 'array');
        if (!is_array($res)) return false;

        Orders::deleteAll(['market' => $this->name(), 'uuid' => $orderId]);
        return true;
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $list = Markets::find()->where(['like', 'name', 'nonkyc%', false])->all();
        if (empty($list)) return;
        $data = $this->publicApiQuery('tickers', '', 'array');
        if (!is_array($data)) return;

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) continue;
            $symbol      = $coin->getOfficialSymbol();
            $pair        = strtolower($symbol . '_' . ($market->base_coin ?: 'btc'));
            $pairReverse = 'btc_' . strtolower($symbol);
            if ($this->marketDisabled($symbol, $market)) continue;

            foreach ($data as $ticker) {
                if ($ticker['type'] !== 'market') continue;
                $tickerId = strtolower($ticker['ticker_id']);
                if ($tickerId === $pair) {
                    $price2         = ($ticker['bid'] + $ticker['ask']) / 2;
                    $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                    $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker['bid']);
                    $market->pricetime = time();
                    $market->save();
                    if (empty($coin->price) && $ticker['ask']) {
                        $coin->price  = $market->price;
                        $coin->price2 = $price2;
                        $coin->save();
                    }
                    break;
                }
                if ($tickerId === $pairReverse) {
                    $tmpBid = $ticker['ask'] ? 1 / $ticker['ask'] : 0.0;
                    $tmpAsk = $ticker['bid'] ? 1 / $ticker['bid'] : 0.0;
                    $price2         = ($tmpBid + $tmpAsk) / 2;
                    $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                    $market->price  = $this->averageIncrement((float) $market->price, $tmpBid);
                    $market->pricetime = time();
                    $market->save();
                    if (empty($coin->price) && $tmpAsk) {
                        $coin->price  = $market->price;
                        $coin->price2 = $price2;
                        $coin->save();
                    }
                    break;
                }
            }
        }
    }

    public function discoverCoins(): void
    {
        if ($this->isDisabled()) return;

        $list = $this->publicApiQuery('tickers', '', 'array');
        if (!is_array($list) || empty($list)) return;

        $this->softDeleteMarkets();
        foreach ($list as $ticker) {
            $base   = strtoupper($ticker['target_currency']);
            $symbol = strtoupper($ticker['base_currency']);
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

        $balances = $this->authenticatedRequest('balances', '', 'GET', 'array');
        if (!is_array($balances)) return;

        $saveBalance = Balances::find()->where(['name' => $this->name()])->one();
        foreach ($balances as $balance) {
            $sym = strtoupper($balance['asset']);
            if ($sym === 'BTC') {
                if ($saveBalance) { $saveBalance->balance = (float) ($balance['available'] ?? 0); $saveBalance->onsell = (float) ($balance['held'] ?? 0); $saveBalance->save(); }
                continue;
            }
            $coins = Coins::find()->where(['or', ['symbol' => $sym], ['symbol2' => $sym]])->all();
            foreach ($coins as $coin) {
                $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
                if (!$market) continue;
                $market->balance = (float) ($balance['available'] ?? 0); $market->ontrade = (float) ($balance['held'] ?? 0);
                $market->balancetime = time(); $market->save();
            }
        }

        if (!defined('YIIMP_ALLOW_EXCHANGE') || !YIIMP_ALLOW_EXCHANGE) return;

        $flushAll     = rand(0, 8) === 0;
        $minBtcTrade  = 0.000001;
        $cancelAskPct = 1.20;

        $rawPrices = $this->publicApiQuery('tickers', '', 'array');
        if (!is_array($rawPrices)) return;
        $prices = [];
        foreach ($rawPrices as $m) { if (isset($m['ticker_id'])) $prices[$m['ticker_id']] = $m; }

        foreach ($balances as $balance) {
            $sym = strtoupper($balance['asset']);
            if ($sym === 'BTC' || (!($balance['available'] ?? 0) && !($balance['held'] ?? 0))) continue;
            $marketSummary = $prices[strtoupper($sym . '_btc')] ?? null;
            if (!$marketSummary) continue;
            $coin = Coins::find()->where(['symbol' => $sym, 'dontsell' => 0])->one();
            if (!$coin) continue;
            $symbol = !empty($coin->symbol2) ? $coin->symbol2 : $coin->symbol;
            $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
            if (!$market) continue;
            $held = (float) ($balance['held'] ?? 0);
            $exOrders = [];
            if ($held > 0) {
                $orders = $this->authenticatedRequest('getorders', ['symbol' => strtoupper($symbol . '_btc'), 'status' => 'active', 'limit' => 20, 'skip' => 0], 'GET', 'array');
                if (is_array($orders)) {
                    foreach ($orders as $order) {
                        if (stripos($order['side'], 'sell') === false) continue;
                        $exOrders[$order['id']] = $order;
                        $ask = Yii::$app->ConversionUtils->bitcoinvaluetoa($marketSummary['ask']);
                        $sp  = Yii::$app->ConversionUtils->bitcoinvaluetoa($order['price']);
                        if ($sp > $ask * $cancelAskPct || $flushAll) {
                            $this->cancelOrder($order['id']);
                        } else {
                            $dbOrder = Orders::find()->where(['market' => $this->name(), 'uuid' => $order['id']])->one();
                            if ($dbOrder) continue;
                            $dbOrder = new Orders();
                            $dbOrder->market  = $this->name(); $dbOrder->coinid  = $coin->id;
                            $dbOrder->amount  = $order['quantity']; $dbOrder->price = $sp;
                            $dbOrder->ask     = $marketSummary['ask']; $dbOrder->bid = $marketSummary['bid'];
                            $dbOrder->uuid    = $order['id']; $dbOrder->created = time();
                            $dbOrder->save();
                        }
                    }
                }
            }
            $dbOrders = Orders::find()->where(['coinid' => $coin->id, 'market' => $this->name()])->all();
            foreach ($dbOrders as $dbOrder) { if (!isset($exOrders[$dbOrder->uuid])) $dbOrder->delete(); }
            if ($coin->dontsell) continue;
            $market->lasttraded = time(); $market->save();
            $amount = (float) ($balance['available'] ?? 0);
            if (!$amount || $amount * $coin->price < $minBtcTrade) continue;
            $cfg = $this->publicApiQuery('market/getbysymbol/' . strtoupper($symbol . '_btc'), '', 'array');
            if (!is_array($cfg)) continue;
            $precision = (int) ($cfg['priceDecimals'] ?? 8);
            if ($coin->sellonbid) {
                $depth = $this->publicApiQuery('orderbook', 'ticker_id=' . strtoupper($symbol . '_btc') . '&depth=10', 'array');
                if (is_array($depth)) {
                    for ($i = 0; $i < 5 && $amount > 0; $i++) {
                        if (!isset($depth['bids'][$i])) break;
                        $nb = $depth['bids'][$i];
                        if ($amount * 1.1 < $nb[1]) break;
                        $sp = Yii::$app->ConversionUtils->bitcoinvaluetoa($nb[0], $precision);
                        $sa = min($amount, $nb[1]);
                        if ($sa * $sp < $minBtcTrade) continue;
                        $res = $this->authenticatedRequest('createorder', json_encode(['symbol' => strtoupper($balance['asset'] . '_btc'), 'price' => number_format($sp, 10), 'side' => 'sell', 'quantity' => "$sa"]), 'POST', 'array');
                        if (!is_array($res)) break;
                        $amount -= $sa;
                    }
                }
            }
            if ($amount <= 0) continue;
            $sp = $coin->sellonbid
                ? Yii::$app->ConversionUtils->bitcoinvaluetoa($marketSummary['bid'], $precision)
                : Yii::$app->ConversionUtils->bitcoinvaluetoa($marketSummary['ask'] - pow(10, -$precision), $precision);
            if ($amount * $sp < $minBtcTrade) continue;
            $res = $this->authenticatedRequest('createorder', json_encode(['symbol' => strtoupper($balance['asset'] . '_btc'), 'price' => number_format($sp, 10), 'side' => 'sell', 'quantity' => "$amount"]), 'POST', 'array');
            if (!is_array($res)) continue;
            $dbOrder = new Orders();
            $dbOrder->market  = $this->name(); $dbOrder->coinid  = $coin->id;
            $dbOrder->amount  = $amount; $dbOrder->price = $sp;
            $dbOrder->ask     = $marketSummary['ask']; $dbOrder->bid = $marketSummary['bid'];
            $dbOrder->uuid    = $res['id'] ?? ''; $dbOrder->created = time();
            $dbOrder->save();
        }
    }
}
