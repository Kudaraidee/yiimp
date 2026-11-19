<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Balances;
use app\models\Coins;
use app\models\Markets;

class KrakenDriver extends ExchangeDriver
{
    public function name(): string { return 'kraken'; }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }
    public function supportsBalance(): bool  { return true; }
    public function supportsBtcEur(): bool   { return true; }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Query Kraken public endpoints via the KrakenAPI class.
     * $params: array of request parameters, or a symbol string for Ticker shorthand.
     */
    protected function publicApiQuery(string $method, mixed $params = ''): mixed
    {
        if (!class_exists('KrakenAPI')) {
            Yii::warning($this->name() . ': KrakenAPI class not available', __CLASS__);
            return false;
        }
        $kraken = new \KrakenAPI('', '');

        $arrParams = [];
        if (is_array($params)) {
            $arrParams = $params;
        } elseif ($method === 'Ticker' && is_string($params) && $params !== '') {
            $arrParams = ['pair' => $this->krakenConvertPair($params)];
        }

        try {
            $res = $kraken->QueryPublic($method, $arrParams);
        } catch (\Exception $e) {
            Yii::warning($this->name() . ": {$method}: " . $e->getMessage(), __CLASS__);
            return false;
        }

        if (isset($res['error']) && !empty($res['error'])) {
            Yii::warning($this->name() . ": {$method} errors: " . implode(', ', $res['error']), __CLASS__);
            return false;
        }

        return $this->krakenNormalizeResult($method, $res['result'] ?? []);
    }

    // ── Authenticated API ─────────────────────────────────────────────────────

    protected function authenticatedRequest(string $method, mixed $params = ''): mixed
    {
        if (!class_exists('KrakenAPI')) {
            Yii::warning($this->name() . ': KrakenAPI class not available', __CLASS__);
            return false;
        }

        $key    = defined('EXCH_KRAKEN_KEY')    ? EXCH_KRAKEN_KEY    : '';
        $secret = defined('EXCH_KRAKEN_SECRET') ? EXCH_KRAKEN_SECRET : '';
        if (empty($key) || empty($secret)) return false;

        $kraken    = new \KrakenAPI($key, $secret);
        $arrParams = [];

        switch ($method) {
            case 'OpenOrders':
                $arrParams = ['trades' => (bool) $params];
                break;
            case 'Withdraw':
            case 'Balance':
            default:
                if (is_array($params)) $arrParams = $params;
                break;
        }

        try {
            $res = $kraken->QueryPrivate($method, $arrParams);
        } catch (\Exception $e) {
            Yii::warning($this->name() . ": {$method}: " . $e->getMessage(), __CLASS__);
            return false;
        }

        if (isset($res['error']) && !empty($res['error'])) {
            Yii::warning($this->name() . ": {$method} errors: " . implode(', ', $res['error']), __CLASS__);
            return false;
        }

        return $res['result'] ?? false;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function krakenConvertPair(string $symbol, string $base = 'BTC'): string
    {
        return $this->krakenSymbolToIso($base) . $this->krakenSymbolToIso($symbol);
    }

    private function krakenSymbolToIso(string $symbol): string
    {
        $conv = ['BTC' => 'XXBT', 'ETH' => 'XETH', 'LTC' => 'XLTC', 'XRP' => 'XXRP', 'XLM' => 'XXLM', 'EUR' => 'ZEUR', 'USD' => 'ZUSD'];
        return $conv[$symbol] ?? 'X' . $symbol;
    }

    private function krakenIsoToSymbol(string $iso): string
    {
        $conv = ['XXBT' => 'BTC', 'XETH' => 'ETH', 'XLTC' => 'LTC', 'XXRP' => 'XRP', 'XXLM' => 'XLM', 'ZEUR' => 'EUR', 'ZUSD' => 'USD'];
        return $conv[$iso] ?? (str_starts_with($iso, 'X') || str_starts_with($iso, 'Z') ? substr($iso, 1) : $iso);
    }

    private function krakenNormalizeResult(string $method, array $result): mixed
    {
        if (empty($result)) return [];
        switch ($method) {
            case 'AssetPairs':
                $proper = [];
                foreach ($result as $pairk => $asset) {
                    $symk1  = $this->krakenIsoToSymbol(substr($pairk, 0, 4));
                    $symk2  = $this->krakenIsoToSymbol(substr($pairk, 4));
                    $proper[$symk1 . '-' . $symk2] = $asset;
                }
                return $proper;
            case 'Ticker':
                $proper = [];
                foreach ($result as $pairk => $asset) {
                    $symk1  = $this->krakenIsoToSymbol(substr($pairk, 0, 4));
                    $symk2  = $this->krakenIsoToSymbol(substr($pairk, 4));
                    $proper[$symk1 . '-' . $symk2] = $asset;
                }
                return $proper;
            default:
                return $result;
        }
    }

    // ── btcEur ────────────────────────────────────────────────────────────────

    public function btcEur(): float
    {
        if (!class_exists('KrakenAPI')) return 0.0;
        $res = $this->publicApiQuery('Ticker', ['pair' => 'XXBTZEUR']);
        if (empty($res)) return 0.0;
        foreach ($res as $t) {
            return isset($t['c'][0]) ? (float) $t['c'][0] : 0.0;
        }
        return 0.0;
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;

        $count = (int) Yii::$app->db->createCommand("SELECT COUNT(id) FROM markets WHERE name LIKE 'kraken%'")->queryScalar();
        if (!$count) return;

        $result = $this->publicApiQuery('AssetPairs');
        if (!is_array($result)) return;

        foreach ($result as $pair => $data) {
            $parts  = explode('-', $pair);
            $base   = reset($parts);
            $symbol = end($parts);
            if ($symbol === 'BTC' || $base !== 'BTC') continue;
            if (in_array($symbol, ['GBP','CAD','EUR','USD','JPY'], true)) continue;
            if (str_contains($symbol, '.d')) continue;

            $coin = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin || (!$coin->installed && !$coin->watch)) continue;

            $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
            if (!$market) continue;

            $fees          = reset($data['fees']);
            $market->txfee = is_array($fees) ? end($fees) : null;

            if ($this->marketDisabled($symbol, $market)) continue;
            $market->save();
            if ($market->disabled || $market->deleted) continue;

            sleep(1);
            $ticker = $this->publicApiQuery('Ticker', $symbol);
            if (!is_array($ticker) || !isset($ticker[$pair])) continue;
            $t = $ticker[$pair] ?? [];
            if (!isset($t['b'])) continue;

            $price1 = (float) $t['a'][0];
            $price2 = (float) $t['b'][0];

            if ($price2 > $price1) {
                [$price1, $price2] = [$price2 ? 1 / $price2 : 0, $price1 ? 1 / $price1 : 0];
            } else {
                [$price1, $price2] = [$price1 ? 1 / $price1 : 0, $price2 ? 1 / $price2 : 0];
            }

            $market->price     = $this->averageIncrement((float) $market->price,  $price1);
            $market->price2    = $this->averageIncrement((float) $market->price2, $price2);
            $market->pricetime = time();
            $market->save();
        }
    }

    public function discoverCoins(): void
    {
        if ($this->isDisabled()) return;

        $list = $this->publicApiQuery('AssetPairs');
        if (!is_array($list)) return;

        $this->softDeleteMarkets();
        foreach ($list as $pair => $item) {
            $parts  = explode('-', $pair);
            $base   = reset($parts);
            $symbol = end($parts);
            if ($symbol === 'BTC' || $base !== 'BTC') continue;
            if (in_array($symbol, ['GBP','CAD','EUR','USD','JPY'], true)) continue;
            if (str_contains($symbol, '.d')) continue;
            $this->upsertMarket(strtoupper($symbol));
        }
    }

    public function syncBalance(): void
    {
        if ($this->isDisabled()) return;

        $balances = $this->authenticatedRequest('Balance');
        if (!is_array($balances)) return;

        foreach ($balances as $symbol => $balance) {
            if ($symbol === 'BTC') {
                $row = Balances::find()->where(['name' => $this->name()])->one();
                if ($row) { $row->balance = (float) $balance; $row->save(); }
                continue;
            }
            $coins = Coins::find()->where(['or', ['symbol' => $symbol], ['symbol2' => $symbol]])->all();
            foreach ($coins as $coin) {
                $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
                if (!$market) continue;
                $market->balance     = (float) $balance;
                $market->balancetime = time();
                $market->save();
            }
        }
    }
}
