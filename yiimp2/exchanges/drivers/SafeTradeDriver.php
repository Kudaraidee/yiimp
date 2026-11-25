<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Balances;
use app\models\Coins;
use app\models\Markets;
use app\models\Orders;

class SafeTradeDriver extends ExchangeDriver
{
    private const API = 'https://safe.trade/api/v2/';

    public function name(): string { return 'safetrade'; }

    public function marketUrl(string $symbol, string $base = 'BTC'): string
    {
        return 'https://safetrade.com/exchange/' . strtolower($symbol) . '-' . strtolower($base);
    }

    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }
    public function supportsBalance(): bool  { return true; }
    public function supportsTrading(): bool  { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    protected function publicApiQuery(string $method, string $params = '', string $returnType = 'object'): mixed
    {
        $url = self::API . ltrim($method, '/');
        if ($params !== '') $url .= '?' . $params;

        $status = 0;
        $raw = $this->curlRequest('GET', $url, ['Accept: application/json'], '', false, [], $status);
        if ($status >= 300) {
            static $warned = false;
            if (!$warned) {
                $warned = true;
                Yii::warning($this->name() . ': public api returned HTTP ' . $status, __CLASS__);
            }
            return null;
        }

        $decoded = json_decode($raw, $returnType === 'object' ? false : true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Yii::warning($this->name() . ': invalid public response ' . substr($raw, 0, 200), __CLASS__);
            return null;
        }
        return $decoded;
    }

    // ── Authenticated API ─────────────────────────────────────────────────────

    private function nextNonce(): string
    {
        static $last = 0;
        $now  = (int) round(microtime(true) * 1000000);
        $nonce = max($now, $last + 1);
        $last = $nonce;
        return (string) $nonce;
    }

    /**
     * Official scheme: HMAC-SHA256(secret, nonce + apikey) with
     * X-Auth-Apikey / X-Auth-Nonce / X-Auth-Signature headers.
     */
    protected function authenticatedRequest(string $method, mixed $params = [], string $httpMethod = 'GET', string $returnType = 'object'): mixed
    {
        $key    = defined('EXCH_SAFETRADE_KEY')    ? trim((string) EXCH_SAFETRADE_KEY)    : '';
        $secret = defined('EXCH_SAFETRADE_SECRET') ? (string) EXCH_SAFETRADE_SECRET       : '';
        if ($key === '' || $secret === '') return false;

        $nonce = $this->nextNonce();
        $url   = self::API . ltrim($method, '/');
        $body  = '';

        if (strtoupper($httpMethod) === 'POST') {
            $body = is_string($params) ? $params : json_encode($params);
        } elseif (!empty($params)) {
            $query = is_array($params) ? http_build_query($params, '', '&') : $params;
            $url  .= '?' . $query;
        }

        $signature = hash_hmac('sha256', $nonce . $key, $secret);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Auth-Apikey: ' . $key,
            'X-Auth-Nonce: ' . $nonce,
            'X-Auth-Signature: ' . $signature,
        ];

        $raw = $this->curlRequest($httpMethod, $url, $headers, $body, false);
        $decoded = json_decode($raw, $returnType === 'object' ? false : true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Yii::warning($this->name() . ': invalid private response for ' . $method . ': ' . substr($raw, 0, 200), __CLASS__);
            return false;
        }
        if (is_array($decoded) && !empty($decoded['errors'])) {
            Yii::warning($this->name() . ': ' . $method . ' error: ' . json_encode($decoded['errors']), __CLASS__);
            return false;
        }
        return $decoded;
    }

    // ── Market helpers ────────────────────────────────────────────────────────

    private function supportedQuote(string $base): bool
    {
        return in_array(strtoupper($base), ['BTC', 'USDT'], true);
    }

    private function marketId(string $symbol, string $base): string
    {
        return strtolower($symbol) . strtolower($base);
    }

    private function tradeSymbol(Coins $coin): string
    {
        return strtoupper(!empty($coin->symbol2) ? $coin->symbol2 : $coin->getOfficialSymbol());
    }

    private function publicMarkets(bool $refresh = false): array
    {
        static $cache = null;
        if ($cache !== null && !$refresh) return $cache;

        $list = $this->publicApiQuery('trade/public/markets', '', 'array');
        if (!is_array($list)) return is_array($cache) ? $cache : [];

        $indexed = [];
        foreach ($list as $market) {
            if (isset($market['id'])) $indexed[strtolower($market['id'])] = $market;
        }
        $cache = $indexed;
        return $cache;
    }

    /** Best bid/ask from the public depth endpoint. */
    private function depth(string $marketId, int $limit = 10): ?array
    {
        $raw = $this->publicApiQuery(
            'trade/public/markets/' . rawurlencode($marketId) . '/depth?limit=' . $limit,
            '', 'array'
        );
        if (!is_array($raw) || empty($raw['asks']) || empty($raw['bids'])) return null;

        $normalize = function ($levels, bool $ascending): array {
            $out = [];
            foreach ((array) $levels as $level) {
                if (!isset($level[0], $level[1])) continue;
                $out[] = [(float) $level[0], (float) $level[1]];
            }
            usort($out, fn($a, $b) => $ascending ? ($a[0] <=> $b[0]) : ($b[0] <=> $a[0]));
            return $out;
        };

        $asks = $normalize($raw['asks'], true);
        $bids = $normalize($raw['bids'], false);
        if (!$asks || !$bids) return null;

        return ['ask' => $asks[0][0], 'bid' => $bids[0][0], 'asks' => $asks, 'bids' => $bids];
    }

    /** Depth first; last traded price only as a fallback. */
    private function bestPrices(string $marketId, mixed $ticker = null): ?array
    {
        $depth = $this->depth($marketId);
        if ($depth && $depth['bid'] > 0 && $depth['ask'] > 0) return $depth;

        $last = 0.0;
        if (is_object($ticker) && isset($ticker->last)) $last = (float) $ticker->last;
        if (is_array($ticker) && isset($ticker['last'])) $last = (float) $ticker['last'];
        if ($last <= 0) return null;

        return ['bid' => $last, 'ask' => $last, 'bids' => [], 'asks' => []];
    }

    private function choosePair(Coins $coin, array $markets): ?array
    {
        $symbol = $this->tradeSymbol($coin);
        foreach (['BTC', 'USDT'] as $base) {
            $market = $this->findMarket($coin, $base);
            if (!$market || $market->deleted || $market->disabled) continue;

            $id = $this->marketId($symbol, $base);
            if (!isset($markets[$id])) continue;
            if (isset($markets[$id]['state']) && $markets[$id]['state'] !== 'enabled') continue;

            return ['id' => $id, 'base' => $base, 'market' => $market, 'meta' => $markets[$id]];
        }
        return null;
    }

    private function safeFindOrCreateMarket(Coins $coin, string $base): Markets
    {
        $base = strtoupper($base);
        $market = $this->findMarket($coin, $base);
        if (!$market) {
            $market = new Markets();
            $market->coinid = $coin->id;
            $market->name = $this->marketName($base);
            $market->base_coin = ($base === 'BTC') ? null : $base;
            $market->deleted = false;
        }
        return $market;
    }

    private function applyMarketPrices(Markets $market, Coins $coin, float $bid, float $ask, string $base): bool
    {
        if ($bid <= 0 && $ask <= 0) return false;
        if ($bid <= 0) $bid = $ask;
        if ($ask <= 0) $ask = $bid;

        $base = $this->supportedQuote($base) ? strtoupper($base) : 'BTC';
        $price2 = ($ask + $bid) / 2;

        $market->price  = $this->averageIncrement((float) $market->price, $bid);
        $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
        $market->base_coin = ($base === 'BTC') ? null : $base;
        if ($base !== 'BTC' && $market->name === $this->name()) {
            $market->name = $this->marketName($base);
        }
        $market->priority = (int) Yii::$app->settings->marketGet(
            $this->name(), $coin->getOfficialSymbol(), 'priority', 0, $base
        );
        $market->txfee = 0.2;
        $market->pricetime = time();
        $market->deleted = false;
        $market->save();

        // coins.price is stored in BTC; convert USDT quotes before seeding.
        $quoteBtc = 1.0;
        if ($base !== 'BTC') {
            $quoteBtc = (float) Yii::$app->settings->get(strtolower($base) . '_btc', 0);
        }
        if (empty($coin->price) && $quoteBtc > 0) {
            $coin->price  = $market->price * $quoteBtc;
            $coin->price2 = $market->price2 * $quoteBtc;
            $coin->save();
        }

        return true;
    }

    // ── Deposit addresses ─────────────────────────────────────────────────────

    private function networkFor(string $currency, string $base = 'BTC'): string
    {
        $currency = strtoupper($currency);
        $base = $this->supportedQuote($base) ? strtoupper($base) : 'BTC';

        $override = Yii::$app->settings->marketGet($this->name(), $currency, 'network', '', $base);
        if (!empty($override)) return (string) $override;

        $override = $this->config(strtolower($currency) . '_network', '');
        if (!empty($override)) return (string) $override;

        $override = $this->config('deposit_network', '');
        if (!empty($override)) return (string) $override;

        $defaults = [
            'BTC' => 'bitcoin-key',
            'USDT' => 'bsc-tokens',
            'USDC' => 'spl-key',
            'DAI' => 'erc20_key',
        ];
        if (isset($defaults[$currency])) return $defaults[$currency];

        static $cache = [];
        if (!isset($cache[$currency])) {
            $info = $this->publicApiQuery('trade/public/currencies/' . rawurlencode(strtolower($currency)), '', 'array');
            $network = '';
            if (is_array($info) && !empty($info['networks'])) {
                foreach ($info['networks'] as $item) {
                    if (!empty($item['deposit_enabled']) && !empty($item['blockchain_key'])) {
                        $network = $item['blockchain_key'];
                        break;
                    }
                }
            }
            $cache[$currency] = $network;
        }
        if ($cache[$currency] !== '') return $cache[$currency];

        return strtolower($currency);
    }

    private function extractAddress(mixed $data, string $network = ''): string
    {
        if (is_object($data)) $data = (array) $data;
        if (!is_array($data)) return '';
        if (!empty($data['address'])) return trim((string) $data['address']);

        $addresses = $data['deposit_addresses'] ?? (isset($data['deposit_address']) ? [$data['deposit_address']] : []);
        foreach ((array) $addresses as $item) {
            if (is_object($item)) $item = (array) $item;
            if (!is_array($item) || empty($item['address'])) continue;
            if ($network !== '' && isset($item['network']) && strcasecmp($item['network'], $network) !== 0) continue;
            return trim((string) $item['address']);
        }
        foreach ((array) $addresses as $item) {
            if (is_object($item)) $item = (array) $item;
            if (is_array($item) && !empty($item['address'])) return trim((string) $item['address']);
        }
        return '';
    }

    private function syncDepositAddress(Coins $coin, string $base, mixed $accounts = null): string
    {
        $market = $this->findMarket($coin, $base);
        if (!$market || !empty($market->deposit_address)) return '';

        $symbol = $this->tradeSymbol($coin);
        $network = $this->networkFor($symbol, $base);
        $address = '';

        if (is_array($accounts)) {
            foreach ($accounts as $account) {
                if (!isset($account['currency']) || strcasecmp($account['currency'], $symbol) !== 0) continue;
                $address = $this->extractAddress($account, $network);
                break;
            }
        }

        if ($address === '') {
            $info = $this->authenticatedRequest(
                'trade/account/deposit_address/' . rawurlencode(strtolower($symbol)),
                ['network' => $network], 'GET', 'array'
            );
            $address = $this->extractAddress($info, $network);
        }

        if ($address !== '') {
            $market->deposit_address = $address;
            $market->save();
        }
        return $address;
    }

    // ── Trading helpers ───────────────────────────────────────────────────────

    private function roundDown(float $value, int $precision): float
    {
        $factor = pow(10, max(0, $precision));
        return floor($value * $factor) / $factor;
    }

    private function createLimitSell(string $marketId, float $price, float $amount, array $meta): array|false
    {
        $pricePrecision  = (int) ($meta['price_precision'] ?? 8);
        $amountPrecision = (int) ($meta['amount_precision'] ?? 8);
        $minAmount = (float) ($meta['min_amount'] ?? 0);
        $minPrice  = (float) ($meta['min_price'] ?? 0);

        $price = round($price, $pricePrecision);
        $amount = $this->roundDown($amount, $amountPrecision);
        if ($price <= 0 || $amount <= 0) return false;
        if ($minPrice > 0 && $price < $minPrice) return false;
        if ($minAmount > 0 && $amount + 1e-12 < $minAmount) return false;

        // SafeTrade's CreateOrderParams has no "type" field: a price makes it
        // a limit order, and extra fields can trip strict validation.
        $res = $this->authenticatedRequest('trade/market/orders', [
            'market' => $marketId,
            'side' => 'sell',
            'price' => (float) $price,
            'amount' => (float) $amount,
        ], 'POST', 'array');

        if (!is_array($res) || !empty($res['errors']) || !isset($res['id'])) {
            Yii::warning($this->name() . ': create order failed ' . json_encode($res), __CLASS__);
            return false;
        }

        $res['_submitted_price'] = $price;
        $res['_submitted_amount'] = $amount;
        return $res;
    }

    private function storeOrder(string $marketName, int $coinid, float $amount, float $price, float $bid, float $ask, string $orderId): void
    {
        $dbOrder = Orders::find()->where(['market' => $marketName, 'uuid' => $orderId])->one();
        if ($dbOrder) return;

        $dbOrder = new Orders();
        $dbOrder->market = $marketName;
        $dbOrder->coinid = $coinid;
        $dbOrder->amount = $amount;
        $dbOrder->price = $price;
        $dbOrder->bid = $bid;
        $dbOrder->ask = $ask;
        $dbOrder->uuid = $orderId;
        $dbOrder->created = time();
        $dbOrder->save();
    }

    public function cancelOrder(string $orderId): bool
    {
        $res = $this->authenticatedRequest(
            'trade/market/orders/' . rawurlencode($orderId) . '/cancel', [], 'POST', 'array'
        );
        if (!is_array($res) || !empty($res['errors'])) return false;

        foreach (Orders::find()->all() as $order) {
            if (strpos((string) $order->market, $this->name()) === 0 && (string) $order->uuid === $orderId) {
                $order->delete();
            }
        }
        return true;
    }

    /** Auto-withdraw BTC. Controlled by withdraw_min_btc, not by auto-trade. */
    private function autoWithdrawBtc($saveBalance): void
    {
        if (!$saveBalance) return;

        $withdrawMin = $this->configFloat('withdraw_min_btc', defined('EXCH_AUTO_WITHDRAW') ? (float) EXCH_AUTO_WITHDRAW : 0.0);
        $withdrawFee = $this->configFloat('withdraw_fee_btc', 0.0001);
        $btcAddr     = defined('YIIMP_BTCADDRESS') ? YIIMP_BTCADDRESS : '';

        if ($withdrawMin <= 0 || $btcAddr === '') return;
        $balance = (float) $saveBalance->balance;
        if ($balance < $withdrawMin + $withdrawFee) return;

        $amount = $this->roundDown($balance - $withdrawFee, 8);
        if ($amount <= 0) return;

        $body = ['currency' => 'btc', 'amount' => (float) $amount];
        $beneficiary = (int) $this->config('withdraw_beneficiary_id', 0);
        if ($beneficiary > 0) {
            $body['beneficiary_id'] = $beneficiary;
        } else {
            // SafeTrade's CreateWithdrawParams requires email_code without a
            // beneficiary_id; otp_code is only an additional factor.
            $emailCode = trim((string) $this->config('withdraw_email_code', ''));
            $otpCode = trim((string) $this->config('withdraw_otp_code', ''));
            if ($emailCode === '') {
                Yii::warning($this->name() . ': withdraw_min_btc set but no withdraw_beneficiary_id or withdraw_email_code configured', __CLASS__);
                return;
            }
            $body['address'] = $btcAddr;
            $body['email_code'] = $emailCode;
            if ($otpCode !== '') $body['otp_code'] = $otpCode;
        }

        $blockchain = trim((string) $this->config('withdraw_blockchain_key', 'bitcoin-key'));
        if ($blockchain !== '') $body['blockchain_key'] = $blockchain;

        $password = trim((string) $this->config('withdraw_password', ''));
        if ($password !== '') $body['withdraw_password'] = $password;

        Yii::info($this->name() . ": withdraw {$amount} BTC to {$btcAddr}", __CLASS__);
        $res = $this->authenticatedRequest('trade/account/withdraws', $body, 'POST', 'array');
        if (!is_array($res) || !empty($res['errors']) || !isset($res['id'])) {
            Yii::warning($this->name() . ': withdraw failed ' . json_encode($res), __CLASS__);
            return;
        }

        $this->recordWithdrawal($btcAddr, $amount);
        $saveBalance->balance = 0;
        $saveBalance->save();
    }

    /**
     * Withdraw USDT to a worker's BEP20 (Binance Smart Chain) address.
     * Returns the SafeTrade withdrawal id on success, false otherwise.
     */
    public function withdrawUsdt(string $address, float $amount): string|false
    {
        if ($this->isDisabled()) return false;

        $address = trim($address);
        if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) {
            Yii::warning($this->name() . ': invalid BEP20 USDT address ' . $address, __CLASS__);
            return false;
        }

        $networkKey = trim((string) $this->config('withdraw_usdt_blockchain_key', 'bsc-tokens'));
        $currency   = $this->publicApiQuery('trade/public/currencies/usdt', '', 'array');
        $network    = null;
        if (is_array($currency)) {
            foreach (($currency['networks'] ?? []) as $item) {
                if (($item['blockchain_key'] ?? '') === $networkKey) {
                    $network = $item;
                    break;
                }
            }
        }
        if (!$network || empty($network['withdraw_enabled'])) {
            Yii::warning($this->name() . ': USDT network ' . $networkKey . ' does not allow withdrawals', __CLASS__);
            return false;
        }

        $minWithdraw = (float) ($network['min_withdraw_amount'] ?? 2);
        if ($amount < $minWithdraw) {
            Yii::warning(
                $this->name() . ": USDT withdraw {$amount} is below network minimum {$minWithdraw}",
                __CLASS__
            );
            return false;
        }

        // Guard against an accidental double withdrawal after a retry.
        $recent = (int) Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM withdraws
             WHERE market = :m AND address = :a AND amount = :amt AND time > :t',
            [':m' => $this->name(), ':a' => $address, ':amt' => $amount, ':t' => time() - 3600]
        )->queryScalar();
        if ($recent > 0) {
            Yii::warning($this->name() . ': duplicate USDT withdraw suppressed for ' . $address, __CLASS__);
            return false;
        }

        $body = [
            'currency'       => 'usdt',
            'amount'         => $this->roundDown($amount, 6),
            'blockchain_key' => $networkKey,
        ];

        $beneficiary = (int) $this->config('withdraw_usdt_beneficiary_id', 0);
        if ($beneficiary > 0) {
            $body['beneficiary_id'] = $beneficiary;
        } else {
            $emailCode = trim((string) $this->config('withdraw_usdt_email_code', ''));
            $otpCode   = trim((string) $this->config('withdraw_usdt_otp_code', ''));
            if ($emailCode === '') {
                Yii::warning(
                    $this->name() . ': USDT withdrawal needs withdraw_usdt_beneficiary_id or withdraw_usdt_email_code',
                    __CLASS__
                );
                return false;
            }
            $body['address']    = $address;
            $body['email_code'] = $emailCode;
            if ($otpCode !== '') $body['otp_code'] = $otpCode;
        }

        $password = trim((string) $this->config('withdraw_usdt_password', ''));
        if ($password !== '') $body['withdraw_password'] = $password;

        Yii::info($this->name() . ": withdraw {$amount} USDT ({$networkKey}) to {$address}", __CLASS__);
        $res = $this->authenticatedRequest('trade/account/withdraws', $body, 'POST', 'array');
        if (!is_array($res) || !empty($res['errors']) || !isset($res['id'])) {
            Yii::warning($this->name() . ': USDT withdraw failed ' . json_encode($res), __CLASS__);
            return false;
        }

        $uuid = (string) $res['id'];
        $this->recordWithdrawal($address, $amount, $uuid);
        return $uuid;
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $markets = $this->publicMarkets(true);
        if (!$markets) return;

        $tickers = $this->publicApiQuery('trade/public/tickers', '', 'array');
        if (!is_array($tickers)) return;

        foreach ($tickers as $id => $ticker) {
            $id = strtolower((string) $id);
            if (!isset($markets[$id])) continue;

            $meta = $markets[$id];
            if (isset($meta['state']) && $meta['state'] !== 'enabled') continue;

            $symbol = strtoupper((string) ($meta['base_unit'] ?? ''));
            $base = strtoupper((string) ($meta['quote_unit'] ?? ''));
            if ($symbol === '' || !$this->supportedQuote($base)) continue;

            $coin = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin) continue;

            $market = $this->safeFindOrCreateMarket($coin, $base);
            if ($this->marketDisabled($coin->getOfficialSymbol(), $market)) continue;

            $prices = $this->bestPrices($id, $ticker);
            if (!$prices) continue;

            $this->applyMarketPrices($market, $coin, $prices['bid'], $prices['ask'], $base);
        }
    }

    public function discoverCoins(): void
    {
        if ($this->isDisabled()) return;

        $list = $this->publicMarkets(true);
        if (!$list) return;

        $this->softDeleteMarkets();
        foreach ($list as $meta) {
            if (isset($meta['state']) && $meta['state'] !== 'enabled') continue;

            $base = strtoupper((string) ($meta['quote_unit'] ?? ''));
            if (!$this->supportedQuote($base)) continue;

            $symbol = strtoupper((string) ($meta['base_unit'] ?? ''));
            if ($symbol === '' || $symbol === 'BTC') continue;

            $this->upsertMarket($symbol, $symbol, $base === 'BTC' ? null : $base);
        }
    }

    /** Balance/address sync used by state 1. */
    public function syncBalance(): void
    {
        $this->syncBalanceData(true);
    }

    private function syncBalanceData(bool $withdraw = true): array|false
    {
        $accounts = $this->authenticatedRequest('trade/account/balances/spot', '', 'GET', 'array');
        if (!is_array($accounts)) return false;

        $saveBalance = Balances::find()->where(['name' => $this->name()])->one();
        if (!$saveBalance) {
            $saveBalance = new Balances();
            $saveBalance->name = $this->name();
        }

        foreach ($accounts as $account) {
            if (!isset($account['currency'])) continue;
            $symbol = strtoupper((string) $account['currency']);
            $available = (float) ($account['balance'] ?? 0);
            $locked = (float) ($account['locked'] ?? 0);

            if ($symbol === 'BTC') {
                $saveBalance->balance = $available;
                $saveBalance->onsell = $locked;
                $saveBalance->save();
                continue;
            }

            $coins = Coins::find()->where(['or', ['symbol' => $symbol], ['symbol2' => $symbol]])->all();
            foreach ($coins as $coin) {
                foreach ($this->marketsForCoin($coin) as $market) {
                    $market->balance = $available;
                    $market->ontrade = $locked;
                    $market->balancetime = time();
                    $market->save();
                }
                foreach (['BTC', 'USDT'] as $base) {
                    if ($this->findMarket($coin, $base)) {
                        $this->syncDepositAddress($coin, $base, $accounts);
                    }
                }
            }
        }

        if ($withdraw) $this->autoWithdrawBtc($saveBalance);
        return $accounts;
    }

    public function trade(): void
    {
        if ($this->isDisabled()) return;

        $accounts = $this->syncBalanceData(false);
        if (!is_array($accounts)) return;
        if (!defined('YIIMP_ALLOW_EXCHANGE') || !YIIMP_ALLOW_EXCHANGE) return;

        $markets = $this->publicMarkets(true);
        if (!$markets) return;

        $flushAll = rand(0, 8) === 0;
        $minBtcTrade = $this->configFloat('trade_min_btc', 0.000001);
        $cancelAskPct = $this->configFloat('trade_cancel_ask_pct', 1.20);
        if ($cancelAskPct <= 1) $cancelAskPct = 1.20;

        $openResult = $this->authenticatedRequest('trade/market/orders', [
            'state' => 'wait,pending',
            'side' => 'sell',
            'limit' => 100,
        ], 'GET', 'array');

        $openOrders = [];
        if (is_array($openResult)) {
            foreach ($openResult as $order) {
                if (!isset($order['market'])) continue;
                $id = strtolower((string) $order['market']);
                $openOrders[$id][] = $order;
            }
        }

        foreach ($accounts as $account) {
            if (!isset($account['currency'])) continue;
            $symbol = strtoupper((string) $account['currency']);
            if (in_array($symbol, ['BTC', 'USDT'], true)) continue;

            $available = (float) ($account['balance'] ?? 0);
            $locked = (float) ($account['locked'] ?? 0);

            $coin = Coins::find()->where(['symbol' => $symbol, 'dontsell' => 0])->one();
            if (!$coin) continue;

            $pair = $this->choosePair($coin, $markets);
            if (!$pair) continue;

            $market = $pair['market'];
            $marketName = (string) $market->name;
            $marketId = $pair['id'];
            $meta = $pair['meta'];

            $depth = $this->depth($marketId, 10);
            if (!$depth) continue;

            $bid = $depth['bid'];
            $ask = $depth['ask'];
            $pricePrecision = (int) ($meta['price_precision'] ?? 8);
            $amountPrecision = (int) ($meta['amount_precision'] ?? 8);

            $btcValue = (float) $coin->price;
            if ($btcValue <= 0) {
                $quoteBtc = $pair['base'] === 'BTC'
                    ? 1.0
                    : (float) Yii::$app->settings->get('usdt_btc', 0);
                $btcValue = $bid * $quoteBtc;
            }

            $existing = $openOrders[$marketId] ?? [];
            $liveIds = [];
            foreach ($existing as $order) {
                $orderId = isset($order['id']) ? (string) $order['id'] : '';
                if ($orderId === '') continue;
                if (isset($order['side']) && stripos($order['side'], 'sell') === false) continue;

                $orderPrice = (float) ($order['price'] ?? 0);
                if ($orderPrice > $ask * $cancelAskPct || $flushAll) {
                    if ($this->cancelOrder($orderId)) continue;
                }

                $liveIds[$orderId] = true;
                $orderAmount = (float) ($order['origin_amount'] ?? $order['amount'] ?? 0);
                $this->storeOrder($marketName, (int) $coin->id, $orderAmount, $orderPrice, $bid, $ask, $orderId);
            }

            foreach (Orders::find()->where(['coinid' => $coin->id, 'market' => $marketName])->all() as $dbOrder) {
                if (!isset($liveIds[(string) $dbOrder->uuid])) $dbOrder->delete();
            }

            // Reconcile stale orders even after a balance has been fully sold.
            if ($available <= 0 && $locked <= 0) continue;

            $market->lasttraded = time();
            $market->save();

            $amount = $this->roundDown($available, $amountPrecision);
            if ($amount <= 0 || $btcValue <= 0 || $amount * $btcValue < $minBtcTrade) continue;

            if ($coin->sellonbid) {
                for ($i = 0; $i < 5 && $amount > 0; $i++) {
                    if (!isset($depth['bids'][$i])) break;
                    $level = $depth['bids'][$i];
                    $levelAmount = (float) ($level[1] ?? 0);
                    if ($amount * 1.1 < $levelAmount) break;

                    $sellPrice = round((float) $level[0], $pricePrecision);
                    $sellAmount = $this->roundDown(min($amount, $levelAmount), $amountPrecision);
                    if ($sellAmount <= 0 || $sellAmount * $btcValue < $minBtcTrade) continue;

                    $res = $this->createLimitSell($marketId, $sellPrice, $sellAmount, $meta);
                    if (!$res) continue;

                    $this->storeOrder($marketName, (int) $coin->id, $res['_submitted_amount'],
                        $res['_submitted_price'], $bid, $ask, (string) $res['id']);
                    $amount = $this->roundDown($amount - $res['_submitted_amount'], $amountPrecision);
                }
            }

            if ($amount <= 0) continue;

            $sellPrice = $coin->sellonbid ? (float) $bid : (float) ($ask - pow(10, -$pricePrecision));
            if ($ask > 0) $sellPrice = min($sellPrice, $ask - pow(10, -$pricePrecision));
            $sellPrice = round($sellPrice, $pricePrecision);
            if ($sellPrice <= 0 || $amount * $btcValue < $minBtcTrade) continue;

            $res = $this->createLimitSell($marketId, $sellPrice, $amount, $meta);
            if (!$res) continue;

            $this->storeOrder($marketName, (int) $coin->id, $res['_submitted_amount'],
                $res['_submitted_price'], $bid, $ask, (string) $res['id']);
        }
    }
}
