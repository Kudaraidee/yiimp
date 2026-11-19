<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Balances;
use app\models\Coins;
use app\models\Markets;
use app\models\Orders;

class YobitDriver extends ExchangeDriver
{
    public function name(): string { return 'yobit'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://yobit.net/en/trade/{$symbol}/{$base}"; }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }
    public function supportsTrading(): bool  { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    protected function publicApiQuery(string $method): mixed
    {
        $raw = $this->curlRequest('GET', "https://yobit.net/api/3/{$method}");
        return json_decode($raw);
    }

    // ── Authenticated API (Trade API) ─────────────────────────────────────────

    protected function authenticatedRequest(string $method, array $params = []): mixed
    {
        $key    = defined('EXCH_YOBIT_KEY')    ? EXCH_YOBIT_KEY    : '';
        $secret = defined('EXCH_YOBIT_SECRET') ? EXCH_YOBIT_SECRET : '';
        if (empty($secret)) return null;

        $params['method'] = $method;
        $params['nonce']  = time();
        $postData = http_build_query($params, '', '&');
        $sign     = hash_hmac('sha512', $postData, $secret);
        $headers  = ['Sign: ' . $sign, 'Key: ' . $key];

        $raw = $this->curlRequest('POST', 'https://yobit.net/tapi/', $headers, $postData, false, [CURLOPT_ENCODING => 'gzip']);
        $result = json_decode($raw, true);
        if (!$result) {
            Yii::warning($this->name() . ": {$method} returned unexpected data", __CLASS__);
        }
        return $result;
    }

    // ── Cancel order ──────────────────────────────────────────────────────────

    public function cancelOrder(string $orderId): bool
    {
        $res = $this->authenticatedRequest('CancelOrder', ['order_id' => $orderId]);
        if (!$res || !($res['success'] ?? false)) return false;

        Orders::deleteAll(['market' => $this->name(), 'uuid' => $orderId]);
        return true;
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $count = (int) Yii::$app->db->createCommand("SELECT COUNT(id) FROM markets WHERE name LIKE 'yobit%'")->queryScalar();
        if (!$count) return;

        $res = $this->publicApiQuery('info');
        if (!is_object($res)) return;

        foreach ($res->pairs as $i => $item) {
            $parts      = explode('_', $i);
            $symbol     = strtoupper($parts[0]);
            $baseSymbol = strtoupper($parts[1]);
            if ($symbol === 'BTC') continue;

            $coin = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin) continue;

            if ($baseSymbol !== 'BTC') {
                $inDb = (int) Yii::$app->db->createCommand(
                    "SELECT COUNT(M.id) FROM markets M INNER JOIN coins C ON C.id=M.coinid
                     WHERE C.installed AND C.symbol=:sym AND M.name LIKE 'yobit %' AND M.base_coin=:base",
                    [':sym' => $symbol, ':base' => $baseSymbol]
                )->queryScalar();
                if (!$inDb) continue;
            }

            $market = Markets::find()
                ->where(['coinid' => $coin->id])
                ->andWhere(['like', 'name', 'yobit%', false])
                ->andFilterWhere(['base_coin' => $baseSymbol === 'BTC' ? null : $baseSymbol])
                ->one();
            if (!$market) continue;

            $market->txfee = $item->fee ?? 0.2;
            if ($market->disabled < 9) {
                $market->disabled = $item->hidden ?? 0;
            }
            if (time() - (int) $market->pricetime > 6 * 3600) {
                $market->price = 0;
            }
            if ($this->marketDisabled($symbol, $market)) continue;
            $market->save();
            if ($market->deleted || $market->disabled) continue;
            if (!$coin->installed && !$coin->watch) continue;

            $symbol = $coin->getOfficialSymbol();
            $pair   = strtolower("{$symbol}_{$baseSymbol}");
            $ticker = $this->publicApiQuery("ticker/{$pair}");
            if (!$ticker || !isset($ticker->$pair)) continue;
            if (!isset($ticker->$pair->buy)) {
                Yii::warning('yobit: invalid data for ' . $pair, __CLASS__);
                continue;
            }

            $price2         = ($ticker->$pair->buy + $ticker->$pair->sell) / 2;
            $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
            $market->price  = $this->averageIncrement((float) $market->price,  (float) $ticker->$pair->buy);
            if ($ticker->$pair->buy < $market->price) {
                $market->price = $ticker->$pair->buy;
            }
            $market->pricetime = time();
            $market->save();

            $hasKey = !empty(defined('EXCH_YOBIT_KEY') ? EXCH_YOBIT_KEY : '');
            if ($hasKey) {
                $cacheKey    = $this->name() . "-deposit_address-check-{$symbol}";
                $lastChecked = Yii::$app->cache->get($cacheKey);
                if ($lastChecked) continue;
                sleep(1);
                $address = $this->authenticatedRequest('GetDepositAddress', ['coinName' => $symbol]);
                if (!empty($address) && isset($address['return']) && $address['success']) {
                    $addr = $address['return']['address'];
                    if (!empty($addr) && $addr !== $market->deposit_address) {
                        $market->deposit_address = $addr;
                        Yii::info('yobit: deposit address for ' . $symbol . ' updated', __CLASS__);
                        $market->save();
                    }
                }
                Yii::$app->cache->set($cacheKey, time(), 24 * 3600);
            }
        }
    }

    public function discoverCoins(): void
    {
        if ($this->isDisabled()) return;

        $res = $this->publicApiQuery('info');
        if (!$res) return;

        $this->softDeleteMarkets();
        foreach ($res->pairs as $i => $item) {
            $symbol = strtoupper(explode('_', $i)[0]);
            $this->upsertMarket($symbol);
        }
    }

    public function trade(): void
    {
        if ($this->isDisabled()) return;

        $raw = $this->authenticatedRequest('getInfo');
        if (!$raw || !isset($raw['return']['funds'])) return;

        $funds    = $raw['return']['funds'];
        $fundsAll = $raw['return']['funds_incl_orders'] ?? [];

        $saveBalance = Balances::find()->where(['name' => $this->name()])->one();
        if ($saveBalance) { $saveBalance->balance = 0; $saveBalance->save(); }

        foreach ($funds as $symbol => $amount) {
            if ($symbol === 'btc') {
                if ($saveBalance) {
                    $saveBalance->balance = (float) $amount;
                    $saveBalance->onsell  = ($fundsAll[$symbol] ?? 0.0) - (float) $amount;
                    $saveBalance->save();
                }
                continue;
            }
            $sym   = strtoupper($symbol);
            $coins = Coins::find()->where(['or', ['symbol' => $sym], ['symbol2' => $sym]])->all();
            foreach ($coins as $coin) {
                $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
                if (!$market) continue;
                $market->balance     = (float) $amount;
                $market->ontrade     = ($fundsAll[$symbol] ?? 0.0) - (float) $amount;
                $market->balancetime = time();
                $market->save();
            }
        }

        if (!defined('YIIMP_ALLOW_EXCHANGE') || !YIIMP_ALLOW_EXCHANGE) return;

        $flushAll     = rand(0, 8) === 0;
        $minBtcTrade  = $this->configFloat('trade_min_btc', 0.0001);
        $sellAskPct   = $this->configFloat('trade_sell_ask_pct', 1.05);
        $cancelAskPct = $this->configFloat('trade_cancel_ask_pct', 1.20);

        $coins = Coins::find()
            ->where(['enable' => 1])
            ->andWhere(['or', ['dontsell' => 0], ['dontsell' => null]])
            ->andWhere(['in', 'id', (new \yii\db\Query())->select('coinid')->from('markets')->where(['name' => $this->name()])])
            ->all();

        foreach ($coins as $coin) {
            $pair = strtolower("{$coin->symbol}_btc");
            sleep(1);
            $res      = $this->authenticatedRequest('ActiveOrders', ['pair' => $pair]);
            $exOrders = $res['return'] ?? [];

            foreach ($exOrders as $uuid => $order) {
                $ticker = $this->publicApiQuery("ticker/{$pair}");
                if (!$ticker) continue;
                if ($order['rate'] > $cancelAskPct * $ticker->$pair->sell || $flushAll) {
                    sleep(1);
                    $this->cancelOrder($uuid);
                } else {
                    $dbOrder = Orders::find()->where(['market' => $this->name(), 'uuid' => $uuid])->one();
                    if ($dbOrder) continue;
                    $dbOrder = new Orders();
                    $dbOrder->market  = $this->name(); $dbOrder->coinid  = $coin->id;
                    $dbOrder->amount  = $order['amount']; $dbOrder->price   = $order['rate'];
                    $dbOrder->ask     = $ticker->$pair->sell; $dbOrder->bid = $ticker->$pair->buy;
                    $dbOrder->uuid    = $uuid; $dbOrder->created = time();
                    $dbOrder->save();
                }
            }

            $dbOrders = Orders::find()->where(['coinid' => $coin->id, 'market' => $this->name()])->all();
            foreach ($dbOrders as $dbOrder) {
                if (!array_key_exists($dbOrder->uuid, $exOrders)) $dbOrder->delete();
            }
        }

        sleep(2);

        foreach ($funds as $symbol => $amount) {
            $amount = (float) $amount - 0.0001;
            if ($amount <= 0 || $symbol === 'btc') continue;
            $sym  = strtoupper($symbol);
            $coin = Coins::find()->where(['or', ['symbol' => $sym], ['symbol2' => $sym]])->one();
            if (!$coin || $coin->dontsell) continue;
            $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
            if ($market) { $market->lasttraded = time(); $market->save(); }
            if ($amount * $coin->price < $minBtcTrade) continue;
            $pair = "{$symbol}_btc";

            if ($coin->sellonbid) {
                sleep(1);
                $data = $this->publicApiQuery("depth/{$pair}?limit=11");
                if ($data) {
                    for ($i = 0; $i < 10 && $amount > 0; $i++) {
                        if (!isset($data->$pair->bids[$i])) break;
                        $nb = $data->$pair->bids[$i];
                        if ($amount * 1.1 < $nb[1]) break;
                        $sp = Yii::$app->ConversionUtils->bitcoinvaluetoa($nb[0]);
                        $sa = min($amount, $nb[1]);
                        if ($sa * $sp < $minBtcTrade) continue;
                        sleep(1);
                        $r = $this->authenticatedRequest('Trade', ['pair' => $pair, 'type' => 'sell', 'rate' => $sp, 'amount' => $sa]);
                        if (!$r || !($r['success'] ?? false)) break;
                        $amount -= $sa;
                    }
                }
            }

            sleep(1);
            $ticker = $this->publicApiQuery("ticker/{$pair}");
            if (!$ticker || $amount <= 0) continue;
            $sellprice = $coin->sellonbid
                ? Yii::$app->ConversionUtils->bitcoinvaluetoa($ticker->$pair->buy)
                : Yii::$app->ConversionUtils->bitcoinvaluetoa($ticker->$pair->sell * $sellAskPct);
            if ($amount * $sellprice < $minBtcTrade) continue;
            sleep(1);
            $r = $this->authenticatedRequest('Trade', ['pair' => $pair, 'type' => 'sell', 'rate' => $sellprice, 'amount' => $amount]);
            if (!$r || !($r['success'] ?? false)) continue;
            $dbOrder = new Orders();
            $dbOrder->market  = $this->name(); $dbOrder->coinid  = $coin->id;
            $dbOrder->amount  = $amount; $dbOrder->price   = $sellprice;
            $dbOrder->ask     = $ticker->$pair->sell; $dbOrder->bid = $ticker->$pair->buy;
            $dbOrder->uuid    = $r['return']['order_id'] ?? ''; $dbOrder->created = time();
            $dbOrder->save();
        }

        $withdrawMin = $this->configFloat('withdraw_min_btc', defined('EXCH_AUTO_WITHDRAW') ? (float) EXCH_AUTO_WITHDRAW : 0.0);
        $withdrawFee = $this->configFloat('withdraw_fee_btc', 0.0015);
        $btcAddr     = defined('YIIMP_BTCADDRESS') ? YIIMP_BTCADDRESS : '';
        if ($saveBalance && $withdrawMin > 0 && $btcAddr && $saveBalance->balance >= ($withdrawMin + $withdrawFee)) {
            $amount = $saveBalance->balance - $withdrawFee;
            Yii::info($this->name() . ": withdraw {$amount} BTC to {$btcAddr}", __CLASS__);
            sleep(1);
            $r = $this->authenticatedRequest('WithdrawCoinsToAddress', ['coinName' => 'BTC', 'amount' => $amount, 'address' => $btcAddr]);
            if ($r && ($r['success'] ?? false)) {
                $this->recordWithdrawal($btcAddr, $amount);
                $saveBalance->balance = 0; $saveBalance->save();
            }
        }
    }
}
