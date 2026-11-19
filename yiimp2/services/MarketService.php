<?php

namespace app\services;

use Yii;
use app\exchanges\ExchangeFactory;
use app\models\Coins;
use app\models\Markets;
use app\models\Market_history;
use app\models\Balances;
use app\components\rpc\WalletRPC;

/**
 * MarketService — coin price updates, market history, and exchange orchestration.
 *
 * Per-exchange logic (API calls, balance sync, trading) lives in individual
 * ExchangeDriver subclasses under app\exchanges\drivers\.
 * This service is responsible only for pool-level orchestration and the
 * coin/market records that aggregate data from all exchanges.
 *
 * Ported from: web/yaamp/core/backend/markets.php
 */
class MarketService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Sync all market prices and compute the best BTC price for every installed coin.
     * Ports: BackendPricesUpdate()
     */
    public function updatePrices(): void
    {
        Yii::debug(__METHOD__, __CLASS__);

        $settings = Yii::$app->settings;
        $settings->marketSetDefault('yobit', 'DCR', 'disabled', true);
        $settings->prefetchAll();

        // Update prices per active exchange
        $exchanges = Balances::find()->all();
        foreach ($exchanges as $exchange) {
            $this->updatePricesForExchange($exchange->name);
        }

        // Propagate prices from coins with a symbol2 alias
        $aliasCoins = Coins::find()
            ->where(['installed' => 1])
            ->andWhere(['not', ['symbol2' => null]])
            ->andWhere(['not', ['symbol2' => '']])
            ->all();

        foreach ($aliasCoins as $coin2) {
            $coin = Coins::find()->where(['symbol' => $coin2->symbol2])->one();
            if (!$coin) {
                continue;
            }

            $markets = Markets::find()->where(['coinid' => $coin->id])->all();
            foreach ($markets as $market) {
                $market2 = Markets::find()
                    ->where(['coinid' => $coin2->id, 'name' => $market->name])
                    ->one();
                if (!$market2) {
                    continue;
                }
                $market2->price          = $market->price;
                $market2->price2         = $market->price2;
                $market2->deposit_address = $market->deposit_address;
                $market2->pricetime      = $market->pricetime;
                $market2->save();
            }
        }

        // Set best price on each coin
        $db    = Yii::$app->db;
        $coins = Coins::find()
            ->where(['installed' => 1])
            ->andWhere(['in', 'id',
                (new \yii\db\Query())->select('coinid')->from('markets')->distinct()
            ])
            ->all();

        $exchangeFee = defined('YIIMP_FEES_EXCHANGE') ? (float) YIIMP_FEES_EXCHANGE : 2.0;

        foreach ($coins as $coin) {
            if ($coin->symbol === 'BTC') {
                $coin->price  = 1;
                $coin->price2 = 1;
                $coin->save();
                continue;
            }

            $market = $this->getBestMarket($coin);
            if ($market) {
                if (is_null($market->base_coin)) {
                    $coin->price  = $market->price * (1 - $exchangeFee / 100);
                    $coin->price2 = $market->price2;
                } else {
                    $baseCoin = Coins::find()->where(['symbol' => $market->base_coin])->one();
                    if ($baseCoin) {
                        $coin->price  = $market->price * $baseCoin->price;
                        $coin->price2 = $market->price2 * $baseCoin->price;
                    }
                }
            } else {
                $coin->price  = 0;
                $coin->price2 = 0;
            }

            $coin->save();
            $db->createCommand(
                "UPDATE earnings SET price={$coin->price} WHERE status!=2 AND coinid={$coin->id}"
            )->execute();
        }

        $db->createCommand(
            "UPDATE markets SET message=NULL WHERE disabled=0 AND message='disabled from settings'"
        )->execute();

        Yii::debug('==== END ' . __METHOD__ . ' ====', __CLASS__);
    }

    /**
     * Dispatch the per-exchange price update for a single exchange.
     * Ports: BackendPricesUpdateExchange()
     */
    public function updatePricesForExchange(string $exchange): void
    {
        Yii::debug("==== Start Sync Market Price {$exchange} ====", __CLASS__);
        ExchangeFactory::make($exchange)->updateMarkets();
        Yii::debug('==== End Sync Market Price ====', __CLASS__);
    }

    /**
     * Record price and balance history snapshots for watched coins and DCR stake tracking.
     * Ports: BackendWatchMarkets()
     */
    public function watchMarkets(?string $marketName = null): void
    {
        // Populate watch flag from config
        if (defined('YIIMP_WATCH_CURRENCIES')) {
            foreach (explode(',', YIIMP_WATCH_CURRENCIES) as $symbol) {
                Yii::$app->db->createCommand(
                    'UPDATE coins SET watch=1 WHERE symbol=:sym',
                    [':sym' => trim($symbol)]
                )->execute();
            }
        }

        $coins = Coins::find()->where(['watch' => 1])->all();

        foreach ($coins as $coin) {
            // BTC: record USD price from mining table
            if ($coin->symbol === 'BTC') {
                if ($marketName) {
                    continue;
                }
                $mh          = new Market_history();
                $mh->time    = time();
                $mh->idcoin  = $coin->id;
                $mh->idmarket = null;
                $mh->price   = Yii::$app->db->createCommand("SELECT usdbtc FROM mining LIMIT 1")->queryScalar();

                if (defined('YIIMP_FIAT_ALTERNATIVE') && YIIMP_FIAT_ALTERNATIVE === 'EUR') {
                    $eurDriver = ExchangeFactory::make('kraken');
                    if ($eurDriver->supportsBtcEur()) {
                        $mh->price2 = $eurDriver->btcEur();
                    }
                }

                $mh->balance = Yii::$app->db->createCommand("SELECT SUM(balance) FROM balances")->queryScalar();
                $mh->save();
                continue;
            }

            if ($coin->installed) {
                $mh           = new Market_history();
                $mh->time     = time();
                $mh->idcoin   = $coin->id;
                $mh->idmarket = null;
                $mh->price    = $coin->price;
                $mh->price2   = $coin->price2;
                $mh->balance  = $coin->balance;
                $mh->save();
            }

            // DCR: record locked-by-tickets balance as a "stake" market entry
            if ($coin->rpcencoding === 'DCR') {
                $remote   = new WalletRPC($coin);
                $stake    = 0.0;
                $balances = $remote->getbalance('*', 0);
                if (isset($balances['balances'])) {
                    foreach ($balances['balances'] as $accb) {
                        $stake += (float) ($accb['lockedbytickets'] ?? 0);
                    }
                }
                $stakeInfo = $remote->getstakeinfo();
                if (empty($remote->error) && isset($stakeInfo['difficulty'])) {
                    Yii::$app->db->createCommand(
                        "UPDATE markets SET balance=0, ontrade=:stake, balancetime=:t,
                             price=:ticketprice, price2=:live, pricetime=NULL
                         WHERE coinid=:id AND name='stake'",
                        [
                            ':ticketprice' => $stakeInfo['difficulty'],
                            ':live'        => $stakeInfo['live'],
                            ':stake'       => $stake,
                            ':id'          => $coin->id,
                            ':t'           => time(),
                        ]
                    )->execute();
                }
            }

            // Per-market history snapshots
            $markets = Markets::find()
                ->where(['coinid' => $coin->id, 'disabled' => 0])
                ->all();

            foreach ($markets as $market) {
                if ($marketName && $market->name !== $marketName) {
                    continue;
                }
                if (!empty($market->base_coin)) {
                    continue;
                }
                if (empty($market->price)) {
                    continue;
                }

                $mh           = new Market_history();
                $mh->time     = time();
                $mh->idcoin   = $coin->id;
                $mh->idmarket = $market->id;
                $mh->price    = $market->price;
                $mh->price2   = $market->price2;
                $mh->balance  = (float) $market->balance + (float) $market->ontrade;
                $mh->save();
            }
        }
    }

    /**
     * Find the market record with the best (highest) BTC price for a given coin.
     * Ports: getBestMarket()
     */
    public function getBestMarket(Coins $coin): ?object
    {
        if ($coin->symbol === 'BTC') {
            return null;
        }

        // Recurse through symbol2 aliases
        if (!empty($coin->symbol2)) {
            $alt = Coins::find()->where(['symbol' => $coin->symbol2])->one();
            if ($alt && $alt->symbol2 !== $coin->symbol2) {
                return $this->getBestMarket($alt);
            }
        }

        $market = null;

        // Prefer the coin's configured market if set
        if (!empty($coin->market) && $coin->market !== 'BEST' && $coin->market !== 'unknown') {
            $market = Markets::find()
                ->where(['coinid' => $coin->id, 'name' => $coin->market])
                ->andWhere(['not', ['price' => 0]])
                ->andWhere(['deleted' => 0, 'disabled' => 0])
                ->andWhere(['not', ['deposit_address' => null]])
                ->andWhere(['not', ['deposit_address' => '']])
                ->one();
        }

        // Fall back to best price across all markets
        if (!$market) {
            $bestId = Yii::$app->db->createCommand(
                "SELECT markets.id
                 FROM markets
                 LEFT JOIN coins ON coins.symbol = markets.base_coin
                 WHERE markets.coinid = :cid AND markets.price != 0
                   AND NOT markets.deleted AND NOT markets.disabled
                 ORDER BY markets.priority DESC,
                   CASE markets.base_coin
                     WHEN markets.base_coin THEN markets.price * COALESCE(coins.price, 1)
                     ELSE markets.price
                   END DESC
                 LIMIT 1",
                [':cid' => $coin->id]
            )->queryScalar();

            if ($bestId) {
                $market = Markets::findOne((int) $bestId);
            }
        }

        if (!$market && empty($coin->market)) {
            Yii::debug("best market for {$coin->symbol} is unknown", __CLASS__);
            $coin->market = 'unknown';
            $coin->save();
        }

        return $market;
    }

    // =========================================================================
    // Exchange balance sync (state 1) and automated trading (state 2)
    // =========================================================================

    /**
     * Sync BTC and altcoin balances from all configured exchange accounts.
     * Ports: getBitstampBalances() + getCexIoBalances() + doKrakenTrading() + doPoloniexTrading()
     * main.sh state 1 (prod only)
     */
    public function updateExchangeBalances(): void
    {
        foreach (ExchangeFactory::withBalance() as $driver) {
            $driver->syncBalance();
        }
    }

    /**
     * Execute automated trading on all configured exchanges.
     * Ports: doBinanceTrading() + doKuCoinTrading() + doYobitTrading() + doNestexTrading() + doNonkycTrading()
     * main.sh state 2 (prod only)
     */
    public function doTrading(): void
    {
        foreach (ExchangeFactory::withTrading() as $driver) {
            $driver->trade();
        }
    }

    // =========================================================================
    // Sell coins to exchanges (ported from sell.php)
    // =========================================================================

    /**
     * Send excess balances from all enabled non-BTC coin wallets to their best market.
     * Ports: TradingSellCoins()
     */
    public function sellCoins(): void
    {
        $coins = Coins::find()
            ->where(['enable' => 1])
            ->andWhere(['>', 'balance', 0])
            ->andWhere(['!=', 'symbol', 'BTC'])
            ->all();

        foreach ($coins as $coin) {
            $this->sellCoinToExchange($coin);
        }
    }

    /**
     * Send available balance for a single coin to its best market deposit address.
     * Handles z-address shielding for Zcash-family coins and per-coin amount caps.
     * Ports: sellCoinToExchange()
     */
    private function sellCoinToExchange(Coins $coin): void
    {
        if ($coin->dontsell) {
            return;
        }

        $remote        = new \app\components\rpc\WalletRPC($coin);
        $walletZAddress = $coin->wallet_zaddress;
        $zBalance      = false;

        if (!empty($walletZAddress)) {
            $zBalance = $remote->z_getbalance($walletZAddress);
        }

        $info = $remote->getinfo();
        if (!$info || (!(float) ($info['balance'] ?? 0) && !$zBalance)) {
            return;
        }

        // Send to symbol2 master wallet (alias coin relay)
        if (!empty($coin->symbol2)) {
            $aliasCoin = Coins::find()->where(['symbol' => $coin->symbol2])->one();
            if (!$aliasCoin) {
                return;
            }
            $amount = ((float) ($info['balance'] ?? 0) - (float) ($info['paytxfee'] ?? 0)) * 0.9;
            $remote->sendtoaddress($aliasCoin->master_wallet, $amount);
            return;
        }

        $market = $this->getBestMarket($coin);
        if (!$market) {
            return;
        }

        // Skip if a previous send is still pending confirmation
        if ($market->lastsent !== null && $market->lastsent > $market->lasttraded) {
            return;
        }

        $depositAddress = $market->deposit_address;
        $marketName     = $market->name;

        if (empty($depositAddress)) {
            return;
        }

        $reserved1 = (float) Yii::$app->db->createCommand(
            "SELECT SUM(balance) FROM accounts WHERE coinid=:cid",
            [':cid' => $coin->id]
        )->queryScalar();
        $reserved2 = (float) Yii::$app->db->createCommand(
            "SELECT SUM(amount*price) FROM earnings
             WHERE status!=2 AND userid IN (SELECT id FROM accounts WHERE coinid=:cid)",
            [':cid' => $coin->id]
        )->queryScalar();

        $txFee    = (float) ($info['paytxfee'] ?? 0);
        $reserved = ($reserved1 + $reserved2) * 10;
        $amount   = (float) ($info['balance'] ?? 0) - $txFee - $reserved;

        // ZCash-family: shield coinbase to z-address first, then send from z-address
        if (!empty($walletZAddress)) {
            if ($amount > $coin->sellthreshold) {
                $result = $remote->z_shieldcoinbase('*', $walletZAddress);
                if (!$result) {
                    return;
                }
            }
            $zAmount = (float) $zBalance - $txFee - $reserved;
            if ($zAmount < $coin->sellthreshold) {
                return;
            }
            $remote->z_sendmany($walletZAddress, [
                ['address' => $depositAddress, 'amount' => round($zAmount - 0.0001, 8)],
            ]);
            return;
        }

        if ($amount < (float) $coin->sellthreshold) {
            return;
        }
        if ($amount > (float) $coin->sellthreshold && $amount < (float) $coin->reward / 4) {
            return;
        }

        // Validate deposit address before sending
        $addrInfo = $remote->validateaddress($depositAddress);
        if (!($addrInfo['isvalid'] ?? false)) {
            Yii::warning("sell: invalid deposit address {$depositAddress} for {$coin->symbol}", __CLASS__);
            return;
        }

        $amount = round($amount, 8);
        $tx     = $remote->sendtoaddress($depositAddress, $amount);

        if (!$tx) {
            // Retry with a reduced amount for known high-UTXO coins
            $caps = ['DIME' => 10000000, 'CNOTE' => 10000, 'SRC' => 500];
            $amount = isset($caps[$coin->symbol])
                ? min($amount, $caps[$coin->symbol])
                : round($amount * 0.99, 8);

            sleep(1);
            $tx = $remote->sendtoaddress($depositAddress, $amount);
            if (!$tx) {
                Yii::warning("sell: {$coin->symbol} failed to send {$amount} → {$depositAddress}: {$remote->error}", __CLASS__);
                return;
            }
        }

        $market->lastsent = time();
        $market->save();

        $deposit             = new \app\models\Exchange_deposit();
        $deposit->market     = $marketName;
        $deposit->coinid     = $coin->id;
        $deposit->send_time  = time();
        $deposit->quantity   = $amount;
        $deposit->price_estimate = $coin->price;
        $deposit->status     = 'waiting';
        $deposit->tx         = $tx;
        $deposit->save();
    }
}
