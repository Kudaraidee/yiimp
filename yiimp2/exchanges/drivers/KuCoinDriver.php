<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Balances;
use app\models\Coins;
use app\models\Markets;
use app\models\Orders;

class KuCoinDriver extends ExchangeDriver
{
    public function name(): string { return 'kucoin'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://www.kucoin.com/#/trade.pro/{$symbol}-{$base}"; }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }
    public function supportsTrading(): bool  { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    protected function publicApiQuery(string $method, string $params = '', string $returnType = 'object'): mixed
    {
        $url = "https://api.kucoin.com/v1/{$method}/";
        if ($params !== '') $url .= "?{$params}";
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
        return true;
    }

    // ── Authenticated API ─────────────────────────────────────────────────────

    protected function authenticatedRequest(string $method, ?array $params = null, bool $isPost = false): mixed
    {
        $key    = defined('EXCH_KUCOIN_KEY')    ? EXCH_KUCOIN_KEY    : '';
        $secret = defined('EXCH_KUCOIN_SECRET') ? EXCH_KUCOIN_SECRET : '';
        if (empty($key) || empty($secret)) return false;

        $mt       = explode(' ', microtime());
        $nonce    = $mt[1] . substr($mt[0], 2, 3);
        $endpoint = "/v1/{$method}";
        $toSign   = "{$endpoint}/{$nonce}/";
        $url      = $endpoint;

        $params  = $params ?? [];
        $query   = http_build_query($params);
        $postData = null;

        if ($query && !$isPost) {
            $url .= '&' . $query;
            $query = '';
        }
        if ($isPost) $postData = $params;

        $hmac    = strtolower(hash_hmac('sha256', base64_encode($toSign . $query), $secret));
        $headers = [
            'Content-Type: application/json;charset=UTF-8',
            'KC-API-KEY: '       . $key,
            'KC-API-NONCE: '     . $nonce,
            'KC-API-SIGNATURE: ' . $hmac,
        ];

        $fullUrl = 'https://api.kucoin.com' . $url;
        $raw = $isPost
            ? $this->curlRequest('POST', $fullUrl, $headers, http_build_query($postData ?? []), true, [CURLOPT_ENCODING => ''])
            : $this->curlRequest('GET',  $fullUrl, $headers, '', true, [CURLOPT_ENCODING => '']);

        $result = json_decode($raw);
        if (!is_object($result) && !is_array($result)) {
            Yii::warning($this->name() . ": {$method} returned unexpected data", __CLASS__);
        }
        return $result;
    }

    // ── Cancel order ──────────────────────────────────────────────────────────

    public function cancelOrder(string $orderId): bool
    {
        $res = $this->authenticatedRequest('cancel/' . $orderId);
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
                if ($ticker->symbol !== $pair) continue;
                if (!isset($ticker->buy) || $ticker->buy == -1) continue;
                $market->price     = $this->averageIncrement((float) $market->price,  (float) $ticker->buy);
                $market->price2    = $this->averageIncrement((float) $market->price2, (float) ($ticker->sell ?? $ticker->buy));
                $market->priority  = -1;
                $market->pricetime = time();
                if ((float) $ticker->vol > 0.01) $market->save();
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
            $this->upsertMarket($item->name, $item->fullName);
        }
    }

    public function trade(): void
    {
        if ($this->isDisabled()) return;

        $data = $this->authenticatedRequest('account/balance');
        if (!$this->kucoinResultValid($data) || empty($data->data)) return;

        $saveBalance = Balances::find()->where(['name' => $this->name()])->one();
        foreach ($data->data as $balance) {
            if ($balance->coinType === 'BTC') {
                if ($saveBalance) {
                    $saveBalance->balance = (float) $balance->balance;
                    $saveBalance->onsell  = (float) $balance->freezeBalance;
                    $saveBalance->save();
                }
                continue;
            }
            $coins = Coins::find()->where(['or', ['symbol' => $balance->coinType], ['symbol2' => $balance->coinType]])->all();
            foreach ($coins as $coin) {
                $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->orderBy('balance')->one();
                if (!$market) continue;
                $market->balance     = (float) $balance->balance;
                $market->ontrade     = (float) $balance->freezeBalance;
                $market->balancetime = time();
                $market->save();
                $cacheKey = $this->name() . "-deposit_address-check-{$coin->symbol}";
                if ($coin->installed && !Yii::$app->cache->get($cacheKey)) {
                    sleep(1);
                    $obj = $this->authenticatedRequest("account/{$coin->symbol}/wallet/address");
                    if (!$this->kucoinResultValid($obj)) continue;
                    $addr = $obj->data->address ?? null;
                    if (!empty($addr) && $addr !== $market->deposit_address) {
                        $market->deposit_address = $addr;
                        $market->save();
                        Yii::info($this->name() . ": deposit address for {$coin->symbol} updated", __CLASS__);
                    }
                    Yii::$app->cache->set($cacheKey, time(), 24 * 3600);
                }
            }
        }
    }
}
