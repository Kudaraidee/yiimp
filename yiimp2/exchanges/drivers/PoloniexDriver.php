<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Balances;
use app\models\Coins;
use app\models\Markets;
use app\models\Orders;

class PoloniexDriver extends ExchangeDriver
{
    public function name(): string { return 'poloniex'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return 'https://poloniex.com/exchange#' . strtolower($base) . '_' . strtolower($symbol); }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }
    public function supportsBalance(): bool  { return true; }

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;
        if (!class_exists('poloniex')) {
            Yii::warning('poloniex: poloniex class not available', __CLASS__);
            return;
        }

        $count = (int) Yii::$app->db->createCommand("SELECT COUNT(id) FROM markets WHERE name LIKE 'poloniex%'")->queryScalar();
        if (!$count) return;

        $poloniex = new \poloniex();
        $tickers  = $poloniex->get_ticker();
        if (!is_array($tickers)) return;

        foreach ($tickers as $symbol => $ticker) {
            $a = explode('_', $symbol);
            if (!isset($a[1]) || $a[0] !== 'BTC') continue;
            $symbol = $a[1];
            $coin   = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin) continue;
            $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
            if (!$market) continue;
            if ($this->marketDisabled($symbol, $market)) continue;
            if ($market->disabled || $market->deleted) continue;

            $price2         = ((float) $ticker['highestBid'] + (float) $ticker['lowestAsk']) / 2;
            $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
            $market->price  = $this->averageIncrement((float) $market->price,  (float) $ticker['highestBid']);
            $market->pricetime = time();
            $market->save();

            $hasKey = !empty(defined('EXCH_POLONIEX_KEY') ? EXCH_POLONIEX_KEY : '');
            if (empty($market->deposit_address) && $coin->installed && $hasKey) {
                $lastChecked = Yii::$app->cache->get($this->name() . '-deposit_address-check');
                if (time() - (int) $lastChecked < 3600) {
                    $poloniex->generate_address($coin->symbol);
                    sleep(1);
                }
                Yii::$app->cache->set($this->name() . '-deposit_address-check', 0, 10);
            }
        }

        $hasKey = !empty(defined('EXCH_POLONIEX_KEY') ? EXCH_POLONIEX_KEY : '');
        if ($hasKey) {
            $lastChecked = Yii::$app->cache->get($this->name() . '-deposit_address-check');
            if (!$lastChecked) {
                $addresses = $poloniex->get_deposit_addresses();
                if (!is_array($addresses)) return;
                foreach ($addresses as $sym => $addr) {
                    if ($sym === 'BTC') continue;
                    $coin = Coins::find()->where(['symbol' => $sym])->one();
                    if (!$coin) continue;
                    $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
                    if ($market && $market->deposit_address !== $addr) {
                        $market->deposit_address = $addr;
                        $market->save();
                        Yii::info($this->name() . ": deposit address for {$sym} updated", __CLASS__);
                    }
                }
                Yii::$app->cache->set($this->name() . '-deposit_address-check', time(), 12 * 3600);
            }
        }
    }

    public function discoverCoins(): void
    {
        if ($this->isDisabled() || !class_exists('poloniex')) return;

        $poloniex = new \poloniex();
        $tickers  = $poloniex->get_currencies();
        if (!$tickers) return;

        $this->softDeleteMarkets();
        foreach ($tickers as $symbol => $ticker) {
            if ($ticker['disabled'] ?? false) continue;
            if ($ticker['delisted']  ?? false) continue;
            $this->upsertMarket($symbol);
        }
    }

    // ── Cancel order ──────────────────────────────────────────────────────────

    public function cancelOrder(string $orderId): bool
    {
        if (!class_exists('poloniex')) return false;

        $dbOrder = Orders::find()->where(['market' => $this->name(), 'uuid' => $orderId])->one();
        if (!$dbOrder) return false;
        $coin = Coins::findOne((int) $dbOrder->coinid);
        if (!$coin) return false;

        $poloniex = new \poloniex();
        $pair = "BTC_{$coin->symbol}";
        $res  = $poloniex->cancel_order($pair, $orderId);
        if (!$res || !($res['success'] ?? false)) return false;

        Orders::deleteAll(['market' => $this->name(), 'uuid' => $orderId]);
        return true;
    }

    public function syncBalance(): void
    {
        if (!class_exists('poloniex')) return;

        $poloniex    = new \poloniex();
        $saveBalance = Balances::find()->where(['name' => $this->name()])->one();
        $balances    = $poloniex->get_complete_balances();

        if (is_array($balances)) {
            foreach ($balances as $symbol => $balance) {
                if ($symbol === 'BTC') {
                    if ($saveBalance) {
                        $saveBalance->balance = (float) ($balance['available'] ?? 0);
                        $saveBalance->onsell  = (float) ($balance['onOrders'] ?? 0);
                        $saveBalance->save();
                    }
                    continue;
                }
                $coins = Coins::find()->where(['or', ['symbol' => $symbol], ['symbol2' => $symbol]])->all();
                foreach ($coins as $coin) {
                    $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
                    if (!$market) continue;
                    $market->balance     = (float) ($balance['available'] ?? 0);
                    $market->ontrade     = (float) ($balance['onOrders'] ?? 0);
                    $market->balancetime = time();
                    $market->save();
                }
            }
        }

        if (!defined('YIIMP_ALLOW_EXCHANGE') || !YIIMP_ALLOW_EXCHANGE) return;

        $flushAll     = rand(0, 8) === 0;
        $minBtcTrade  = $this->configFloat('trade_min_btc', 0.0001);
        $sellAskPct   = $this->configFloat('trade_sell_ask_pct', 1.05);
        $cancelAskPct = $this->configFloat('trade_cancel_ask_pct', 1.20);

        sleep(1);
        $tickers = $poloniex->get_ticker();
        if (!$tickers) return;

        $coins = Coins::find()
            ->where(['enable' => 1, 'dontsell' => 0])
            ->andWhere(['in', 'id', (new \yii\db\Query())->select('coinid')->from('markets')->where(['name' => $this->name()])])
            ->all();

        foreach ($coins as $coin) {
            $pair = "BTC_{$coin->symbol}";
            if (!isset($tickers[$pair])) continue;

            sleep(1);
            $exOrders = $poloniex->get_open_orders($pair);
            if (!$exOrders || !isset($exOrders[0])) {
                Orders::deleteAll(['coinid' => $coin->id, 'market' => $this->name()]);
                continue;
            }

            foreach ($exOrders as $order) {
                if (!isset($order['orderNumber'])) continue;
                if ($order['rate'] > $tickers[$pair]['lowestAsk'] * $cancelAskPct || $flushAll) {
                    $this->cancelOrder($order['orderNumber']);
                } else {
                    $dbOrder = Orders::find()->where(['market' => $this->name(), 'uuid' => $order['orderNumber']])->one();
                    if ($dbOrder) continue;
                    $dbOrder          = new Orders();
                    $dbOrder->market  = $this->name();
                    $dbOrder->coinid  = $coin->id;
                    $dbOrder->amount  = $order['amount'];
                    $dbOrder->price   = $order['rate'];
                    $dbOrder->ask     = $tickers[$pair]['lowestAsk'];
                    $dbOrder->bid     = $tickers[$pair]['highestBid'];
                    $dbOrder->uuid    = $order['orderNumber'];
                    $dbOrder->created = time();
                    $dbOrder->save();
                }
            }

            $dbOrders = Orders::find()->where(['coinid' => $coin->id, 'market' => $this->name()])->all();
            foreach ($dbOrders as $dbOrder) {
                $found = false;
                foreach ($exOrders as $o) {
                    if (isset($o['orderNumber']) && $o['orderNumber'] == $dbOrder->uuid) { $found = true; break; }
                }
                if (!$found) $dbOrder->delete();
            }
        }

        if (!is_array($balances)) return;
        foreach ($balances as $symbol => $balance) {
            if (!($balance['available'] ?? 0) || $symbol === 'BTC') continue;
            $coin = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin || $coin->dontsell) continue;
            $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
            if ($market) { $market->lasttraded = time(); $market->balance = $balance['onOrders'] ?? 0; $market->save(); }
            $pair = "BTC_{$symbol}";
            if (!isset($tickers[$pair])) continue;
            $sellprice = $coin->sellonbid
                ? Yii::$app->ConversionUtils->bitcoinvaluetoa($tickers[$pair]['highestBid'])
                : Yii::$app->ConversionUtils->bitcoinvaluetoa($tickers[$pair]['lowestAsk'] * $sellAskPct);
            if (($balance['available'] ?? 0) * $sellprice < $minBtcTrade) continue;
            sleep(1);
            $res = $poloniex->sell($pair, $sellprice, $balance['available']);
            if (!isset($res['orderNumber'])) continue;
            $dbOrder = new Orders();
            $dbOrder->market  = $this->name(); $dbOrder->coinid  = $coin->id;
            $dbOrder->amount  = $balance['available']; $dbOrder->price   = $sellprice;
            $dbOrder->ask     = $tickers[$pair]['lowestAsk']; $dbOrder->bid = $tickers[$pair]['highestBid'];
            $dbOrder->uuid    = $res['orderNumber']; $dbOrder->created = time();
            $dbOrder->save();
        }

        $withdrawMin = $this->configFloat('withdraw_min_btc', defined('EXCH_AUTO_WITHDRAW') ? (float) EXCH_AUTO_WITHDRAW : 0.0);
        $withdrawFee = $this->configFloat('withdraw_fee_btc', 0.0001);
        $btcAddr     = defined('YIIMP_BTCADDRESS') ? YIIMP_BTCADDRESS : '';
        if ($saveBalance && $withdrawMin > 0 && $btcAddr && $saveBalance->balance >= ($withdrawMin + $withdrawFee)) {
            $amount = $saveBalance->balance - $withdrawFee;
            Yii::info($this->name() . ": withdraw {$amount} BTC to {$btcAddr}", __CLASS__);
            sleep(1);
            $res = $poloniex->withdraw('BTC', $amount, $btcAddr);
            if ($res && str_contains((string) ($res->response ?? ''), 'Withdrew')) {
                $this->recordWithdrawal($btcAddr, $amount);
                $saveBalance->balance = 0; $saveBalance->save();
            }
        }
    }
}
