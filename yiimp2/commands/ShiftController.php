<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Coins;
use app\components\rpc\WalletRPC;

/**
 * ShapeShift exchange integration.
 *
 * Note: ShapeShift's legacy API (api.shapeshift.io) has been largely deprecated
 * since ShapeShift moved to a DEX model (~2018). These commands may require
 * updating to the current ShapeShift API if still needed.
 *
 * Usage:
 *   php yii shift/list                           — installed coins with ShapeShift markets
 *   php yii shift/query <SYM> [destSYM]          — query exchange rate
 *   php yii shift/start <SYM> <destSYM> [addr]   — initiate a shift transaction
 *   php yii shift/status <depositAddr>            — check order status
 *   php yii shift/cancel <depositAddr>            — cancel pending order
 *   php yii shift/send <depositAddr> <amount> <SYM>  — send coins (YIIMP_CLI_ALLOW_TXS required)
 */
class ShiftController extends Controller
{
    private const API = 'https://shapeshift.io';

    private function apiGet(string $endpoint, string $param = ''): mixed
    {
        $url  = self::API . '/' . $endpoint . ($param ? '/' . $param : '');
        $data = @file_get_contents($url);
        return $data ? json_decode($data, true) : null;
    }

    private function apiPost(string $endpoint, array $data): mixed
    {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/json',
            'content' => json_encode($data),
        ]]);
        $res = @file_get_contents(self::API . '/' . $endpoint, false, $ctx);
        return $res ? json_decode($res, true) : null;
    }

    private function findCoin(string $symbol): ?Coins
    {
        $c = Coins::find()->where(['symbol' => $symbol])->one();
        if (!$c) $this->stderr("coin {$symbol} not found!\n");
        return $c;
    }

    private function shapeshiftAllowed(string $symbol): bool
    {
        return (bool) Yii::$app->db->createCommand(
            "SELECT COUNT(M.id) FROM coins C
             INNER JOIN markets M ON M.coinid = C.id
             WHERE C.symbol = :s AND M.name = 'shapeshift'",
            [':s' => $symbol]
        )->queryScalar();
    }

    /** List installed coins with ShapeShift markets. */
    public function actionList(): int
    {
        $rows = Yii::$app->db->createCommand(
            "SELECT C.symbol, C.available FROM coins C
             INNER JOIN markets M ON M.coinid = C.id
             WHERE M.name = 'shapeshift' AND C.installed ORDER BY symbol"
        )->queryAll();

        $cu = Yii::$app->ConversionUtils;
        foreach ($rows as $c) {
            $avail = $c['available'] ? ' (' . $cu->bitcoinvaluetoa($c['available']) . ')' : '';
            $this->stdout($c['symbol'] . $avail . "\n");
        }
        return ExitCode::OK;
    }

    /** Query ShapeShift rate for a coin pair. */
    public function actionQuery(string $src, string $dst = 'BTC'): int
    {
        $srcCoin = $this->findCoin($src);
        $dstCoin = $this->findCoin($dst);
        if (!$srcCoin || !$dstCoin) return ExitCode::UNSPECIFIED_ERROR;
        if (!$this->shapeshiftAllowed($src)) { $this->stderr("{$src} not on shapeshift\n"); return ExitCode::UNSPECIFIED_ERROR; }
        if (!$this->shapeshiftAllowed($dst)) { $this->stderr("{$dst} not on shapeshift\n"); return ExitCode::UNSPECIFIED_ERROR; }

        $pair = strtolower($srcCoin->getOfficialSymbol() . '_' . $dstCoin->getOfficialSymbol());
        $res  = $this->apiGet('marketinfo', $pair);
        if (!is_array($res)) { $this->stderr(json_encode($res) . "\n"); return ExitCode::UNSPECIFIED_ERROR; }

        $this->stdout("info: {$pair} " . json_encode($res) . "\n");
        $rate = $srcCoin->price && $dstCoin->price ? round($srcCoin->price / $dstCoin->price, 8) : 0;
        $diff = round(($res['rate'] ?? 1) - $rate, 8);
        $this->stdout("DB prices: " . Yii::$app->ConversionUtils->bitcoinvaluetoa($srcCoin->price) .
            " " . Yii::$app->ConversionUtils->bitcoinvaluetoa($dstCoin->price) . " {$rate}:1 ({$diff})\n");
        return ExitCode::OK;
    }

    /** Initiate a ShapeShift transaction. */
    public function actionStart(string $src, string $dst, string $dstAddr = ''): int
    {
        $srcCoin = $this->findCoin($src);
        $dstCoin = $this->findCoin($dst);
        if (!$srcCoin || !$dstCoin) return ExitCode::UNSPECIFIED_ERROR;
        if (!$this->shapeshiftAllowed($src)) { $this->stderr("{$src} not on shapeshift\n"); return ExitCode::UNSPECIFIED_ERROR; }
        if (!$this->shapeshiftAllowed($dst)) { $this->stderr("{$dst} not on shapeshift\n"); return ExitCode::UNSPECIFIED_ERROR; }

        $pair = strtolower($srcCoin->getOfficialSymbol() . '_' . $dstCoin->getOfficialSymbol());
        $info = $this->apiGet('marketinfo', $pair);
        if (!is_array($info)) { $this->stdout(json_encode($info) . "\n"); return ExitCode::UNSPECIFIED_ERROR; }
        $this->stdout("info: " . json_encode($info) . "\n");

        $res = $this->apiPost('shift', [
            'pair'          => $pair,
            'returnAddress' => $srcCoin->master_wallet,
            'withdrawal'    => $dstAddr ?: $dstCoin->master_wallet,
        ]);
        if (isset($res['error'])) { $this->stdout(json_encode($res) . "\n"); return ExitCode::UNSPECIFIED_ERROR; }

        $this->stdout(json_encode($res) . "\n");
        if (isset($res['deposit'])) {
            $this->stdout("Transaction ready. Send {$src} to deposit address: {$res['deposit']}\n");
            $this->stdout("Then run: php yii shift/status {$res['deposit']}\n");
        }
        return ExitCode::OK;
    }

    /** Check ShapeShift order status. */
    public function actionStatus(string $depositAddr): int
    {
        $res = $this->apiGet('txStat', $depositAddr);
        $this->stdout(json_encode($res) . "\n");
        if (isset($res['outgoingCoin'], $res['incomingCoin'])) {
            $this->stdout("real rate: " . round($res['outgoingCoin'] / $res['incomingCoin'], 8) . "\n");
        }
        return ExitCode::OK;
    }

    /** Cancel a pending ShapeShift order. */
    public function actionCancel(string $depositAddr): int
    {
        $res = $this->apiPost('cancelpending', ['address' => $depositAddr]);
        $this->stdout(json_encode($res) . "\n");
        return ExitCode::OK;
    }

    /** Send coins to a ShapeShift deposit address (requires YIIMP_CLI_ALLOW_TXS). */
    public function actionSend(string $depositAddr, string $amount, string $symbol): int
    {
        if (!defined('YIIMP_CLI_ALLOW_TXS') || !YIIMP_CLI_ALLOW_TXS) {
            $this->stderr("YIIMP_CLI_ALLOW_TXS is not enabled in serverconfig.php\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $coin = $this->findCoin($symbol);
        if (!$coin) return ExitCode::UNSPECIFIED_ERROR;

        $remote = new WalletRPC($coin);
        $valid  = $remote->validateaddress($depositAddr);
        if (!($valid['isvalid'] ?? false)) {
            $this->stderr("invalid deposit address\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $res = $remote->sendtoaddress($depositAddr, (float)$amount, '', '', true);
        $this->stdout(json_encode($res) . "\n");
        return ExitCode::OK;
    }
}
