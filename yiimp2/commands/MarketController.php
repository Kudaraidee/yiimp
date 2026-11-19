<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Coins;
use app\models\Markets;
use app\models\Market_history;

/**
 * Market data inspection and settings management.
 *
 * Usage:
 *   php yii market/list <SYM>                         — list all markets for a coin
 *   php yii market/histo <SYM> <exchange>             — last 100 market history entries
 *   php yii market/prune <SYM>                        — prune old market history
 *   php yii market/get <SYM> <exchange> <key>
 *   php yii market/set <SYM> <exchange> <key> <value>
 *   php yii market/unset <SYM> <exchange> <key>
 *   php yii market/settings <SYM> <exchange>
 */
class MarketController extends Controller
{
    private function findCoin(string $symbol): ?Coins
    {
        $c = Coins::find()->where(['symbol' => $symbol])->one();
        if (!$c) $this->stderr("coin {$symbol} not found!\n");
        return $c;
    }

    private function exchangeExists(string $exchange): bool
    {
        return (bool) Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM markets WHERE name = :e", [':e' => $exchange]
        )->queryScalar();
    }

    /** List all markets for a coin with price and status. */
    public function actionList(string $symbol): int
    {
        $coin = $this->findCoin($symbol);
        if (!$coin) return ExitCode::UNSPECIFIED_ERROR;

        $markets = Markets::find()->where(['coinid' => $coin->id])->orderBy('disabled, price DESC')->all();
        $cu      = Yii::$app->ConversionUtils;
        foreach ($markets as $m) {
            $disabled = Yii::$app->settings->marketGet($m->name, $symbol, 'disabled');
            if ($disabled || $m->deleted) {
                $price = '*DELETED*';
            } elseif ($m->disabled) {
                $price = '*DISABLED*';
            } else {
                $price = $cu->bitcoinvaluetoa($m->price);
            }
            $this->stdout("{$price} {$m->name}\n");
        }
        return ExitCode::OK;
    }

    /** Show last 100 market history entries for a coin on a specific exchange. */
    public function actionHisto(string $symbol, string $exchange): int
    {
        $coin = $this->findCoin($symbol);
        if (!$coin) return ExitCode::UNSPECIFIED_ERROR;

        $cu   = Yii::$app->ConversionUtils;
        $rows = Market_history::find()
            ->where(['idcoin' => $coin->id])
            ->andFilterWhere(['name' => $symbol !== 'BTC' ? $exchange : null])
            ->orderBy(['time' => SORT_DESC])
            ->limit(100)
            ->all();

        foreach ($rows as $h) {
            $date   = date('Y-m-d H:i:s', $h->time);
            $price1 = $cu->bitcoinvaluetoa($h->price);
            $price2 = $cu->bitcoinvaluetoa($h->price2);
            $this->stdout("{$date} {$price1} {$price2}\n");
        }
        return ExitCode::OK;
    }

    /** Prune market history older than 30 days for a coin. */
    public function actionPrune(string $symbol): int
    {
        $coin = $this->findCoin($symbol);
        if (!$coin) return ExitCode::UNSPECIFIED_ERROR;

        $cutoff  = time() - 30 * 86400;
        $deleted = Yii::$app->db->createCommand()->delete(
            'market_history',
            'idcoin = :id AND time < :t',
            [':id' => $coin->id, ':t' => $cutoff]
        )->execute();
        $this->stdout("{$deleted} old history records deleted for {$symbol}\n");
        return ExitCode::OK;
    }

    // ── Market settings ────────────────────────────────────────────────────────

    public function actionGet(string $symbol, string $exchange, string $key): int
    {
        $value = Yii::$app->settings->marketGet($exchange, $symbol, $key);
        $this->stdout($value . "\n");
        return ExitCode::OK;
    }

    public function actionSet(string $symbol, string $exchange, string $key, string $value): int
    {
        if (!$this->findCoin($symbol))    return ExitCode::UNSPECIFIED_ERROR;
        if (!$this->exchangeExists($exchange)) {
            $this->stderr("exchange '{$exchange}' not found\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        Yii::$app->settings->marketSet($exchange, $symbol, $key, $value);
        $val = Yii::$app->settings->marketGet($exchange, $symbol, $key);
        $this->stdout("{$symbol} {$exchange} {$key} " . json_encode($val) . "\n");
        return ExitCode::OK;
    }

    public function actionUnset(string $symbol, string $exchange, string $key): int
    {
        if (!$this->findCoin($symbol))    return ExitCode::UNSPECIFIED_ERROR;
        if (!$this->exchangeExists($exchange)) {
            $this->stderr("exchange '{$exchange}' not found\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        Yii::$app->settings->marketUnset($exchange, $symbol, $key);
        $this->stdout("ok\n");
        return ExitCode::OK;
    }

    public function actionSettings(string $symbol, string $exchange): int
    {
        if (!$this->findCoin($symbol))    return ExitCode::UNSPECIFIED_ERROR;
        if (!$this->exchangeExists($exchange)) {
            $this->stderr("exchange '{$exchange}' not found\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        Yii::$app->settings->marketPrefetch($exchange);
        $keys = Yii::$app->db->createCommand(
            "SELECT param, value FROM settings WHERE param LIKE :pat",
            [':pat' => "{$exchange}-%{$symbol}%"]
        )->queryAll();
        foreach ($keys as $row) {
            $this->stdout("{$row['param']} " . json_encode($row['value']) . "\n");
        }
        return ExitCode::OK;
    }
}
