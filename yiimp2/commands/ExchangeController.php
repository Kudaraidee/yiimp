<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Exchange settings management and API key validation.
 *
 * Usage:
 *   php yii exchange/get <exchange> <key>
 *   php yii exchange/set <exchange> <key> <value>
 *   php yii exchange/unset <exchange> <key>
 *   php yii exchange/settings <exchange>
 *   php yii exchange/apitest
 */
class ExchangeController extends Controller
{
    public function actionGet(string $exchange, string $key): int
    {
        $value = Yii::$app->settings->exchangeGet($exchange, $key);
        $this->stdout($value . "\n");
        return ExitCode::OK;
    }

    public function actionSet(string $exchange, string $key, string $value): int
    {
        $valid = Yii::$app->settings->validExchangeKeys();
        if (!isset($valid[$key])) {
            $this->stderr("error: key '{$key}' is not a recognised exchange setting\n");
            $this->stdout("Valid keys: " . implode(', ', array_keys($valid)) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        Yii::$app->settings->exchangeSet($exchange, $key, $value);
        $val = Yii::$app->settings->exchangeGet($exchange, $key);
        $this->stdout("{$exchange} {$key} " . json_encode($val) . "\n");
        return ExitCode::OK;
    }

    public function actionUnset(string $exchange, string $key): int
    {
        Yii::$app->settings->exchangeUnset($exchange, $key);
        $this->stdout("ok\n");
        return ExitCode::OK;
    }

    public function actionSettings(string $exchange): int
    {
        $valid = Yii::$app->settings->validExchangeKeys();
        foreach (array_keys($valid) as $key) {
            $val = Yii::$app->settings->exchangeGet($exchange, $key);
            if ($val !== null) {
                $this->stdout("{$exchange} {$key} " . json_encode($val) . "\n");
            }
        }
        return ExitCode::OK;
    }

    /**
     * Test configured exchange API keys by fetching BTC balances.
     * Guards on EXCH_* constants being non-empty.
     */
    public function actionApitest(): int
    {
        $tested = 0;

        if (!empty(EXCH_BINANCE_KEY ?? '')) {
            $this->testExchange('binance', function () {
                if (!function_exists('binance_api_user')) return '(binance_api_user not loaded)';
                $b = binance_api_user('account');
                foreach (($b->balances ?? []) as $a) {
                    if ($a->asset === 'BTC') return json_encode($a);
                }
                return 'BTC balance not found';
            });
            $tested++;
        }

        if (!empty(EXCH_KRAKEN_KEY ?? '')) {
            $this->testExchange('kraken', function () {
                if (!function_exists('kraken_api_user')) return '(kraken_api_user not loaded)';
                return json_encode(kraken_api_user('Balance'));
            });
            $tested++;
        }

        if (!empty(EXCH_KUCOIN_SECRET ?? '')) {
            $this->testExchange('kucoin', function () {
                if (!function_exists('kucoin_api_user')) return '(kucoin_api_user not loaded)';
                $b = kucoin_api_user('account/BTC/balance');
                return json_encode($b->data ?? $b);
            });
            $tested++;
        }

        if (!empty(EXCH_POLONIEX_KEY ?? '')) {
            $this->testExchange('poloniex', function () {
                if (!class_exists('poloniex')) return '(poloniex class not loaded)';
                $p = new \poloniex();
                return json_encode($p->get_available_balances());
            });
            $tested++;
        }

        if (!empty(EXCH_YOBIT_KEY ?? '')) {
            $this->testExchange('yobit', function () {
                if (!function_exists('yobit_api_query2')) return '(yobit_api_query2 not loaded)';
                $info = yobit_api_query2('getInfo');
                return json_encode($info['return']['funds']['btc'] ?? $info);
            });
            $tested++;
        }

        if ($tested === 0) {
            $this->stdout("No EXCH_* API keys configured in serverconfig.php\n");
        }
        return ExitCode::OK;
    }

    private function testExchange(string $name, callable $fn): void
    {
        try {
            $result = $fn();
            $this->stdout("{$name}: {$result}\n");
        } catch (\Throwable $e) {
            $this->stderr("{$name} error: {$e->getMessage()}\n");
        }
    }
}
