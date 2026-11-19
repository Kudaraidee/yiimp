<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Coins;

/**
 * Coin database enrichment: labels and icons from external sources.
 *
 * Usage:
 *   php yii coindb/labels   — update unknown coin names from CoinCap and pool APIs
 *   php yii coindb/icons    — download missing coin icons from KuCoin CDN
 */
class CoindbController extends Controller
{
    /** Update unknown coin names from external APIs and labels.json. */
    public function actionLabels(): int
    {
        $total  = $this->updateCoinCapLabels();
        $total += $this->updateYiimpLabels('api.yiimp.eu');
        $total += $this->updateFromJson();
        $this->stdout("total updated: {$total}\n");
        return ExitCode::OK;
    }

    /** Download missing coin icons from KuCoin CDN. */
    public function actionIcons(): int
    {
        $total = $this->grabKuCoinIcons();
        $this->stdout("total updated: {$total}\n");
        return ExitCode::OK;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function updateCoinCapLabels(): int
    {
        $json = @file_get_contents('http://coincap.io/front');
        if (!$json) {
            $this->stdout("coincap.io: no data (endpoint may have changed)\n");
            return 0;
        }
        $data = json_decode($json, true) ?? [];
        $map  = [];
        foreach ($data as $c) {
            $key = strtoupper($c['short'] ?? '');
            if ($key) $map[$key] = $c;
        }

        $coins     = Coins::find()->where(['or', ['name' => 'unknown'], 'name = symbol'])->all();
        $updated   = 0;
        foreach ($coins as $coin) {
            if (isset($map[$coin->symbol]) && $coin->name === 'unknown') {
                $name = trim($map[$coin->symbol]['long'] ?? '');
                if ($name && $name !== $coin->name) {
                    $this->stdout("{$coin->symbol}: {$name}\n");
                    $coin->name = $name;
                    $updated   += (int) $coin->save(false);
                }
            }
        }
        if ($updated) $this->stdout("{$updated} labels updated from coincap.io\n");
        return $updated;
    }

    private function updateYiimpLabels(string $pool): int
    {
        $data = @file_get_contents("http://{$pool}/api/currencies");
        if (!$data) return 0;
        $json = json_decode($data, true) ?? [];

        $coins   = Coins::find()->where(['or', ['name' => 'unknown'], ['algo' => ''], ['algo' => 'scrypt']])->all();
        $updated = 0;
        $algos   = 0;
        foreach ($coins as $coin) {
            if (!isset($json[$coin->symbol])) continue;
            $cc = $json[$coin->symbol];
            if ($coin->name === 'unknown' && !empty($cc['name'])) {
                $this->stdout("{$coin->symbol}: {$cc['name']}\n");
                $coin->name = $cc['name'];
                $updated   += (int) $coin->save(false);
            }
            if (!empty($cc['algo']) && $coin->algo !== strtolower($cc['algo'])) {
                $coin->algo = strtolower($cc['algo']);
                $this->stdout("{$coin->symbol}: algo set to {$cc['algo']}\n");
                $algos += (int) $coin->save(false);
            }
        }
        if ($updated) $this->stdout("{$updated} labels and {$algos} algos updated from {$pool}\n");
        return $updated;
    }

    private function updateFromJson(): int
    {
        $path = defined('YIIMP_HTDOCS') ? YIIMP_HTDOCS . '/../sql/labels.json' : '';
        if (!$path || !file_exists($path)) return 0;

        $json    = json_decode(file_get_contents($path), true) ?? [];
        $coins   = Coins::find()->where(['name' => 'unknown'])->all();
        $updated = 0;
        foreach ($coins as $coin) {
            if (isset($json[$coin->symbol])) {
                $this->stdout("{$coin->symbol}: {$json[$coin->symbol]}\n");
                $coin->name = $json[$coin->symbol];
                $updated   += (int) $coin->save(false);
            }
        }
        if ($updated) $this->stdout("{$updated} labels updated from labels.json\n");
        return $updated;
    }

    private function grabKuCoinIcons(): int
    {
        $root    = defined('YIIMP_HTDOCS') ? YIIMP_HTDOCS : '';
        $base    = 'https://assets.kucoin.com/www/1.2.0/assets/coins/';
        $updated = 0;

        $rows = Yii::$app->db->createCommand(
            "SELECT DISTINCT coins.id FROM coins
             INNER JOIN markets M ON M.coinid = coins.id
             WHERE M.name = 'kucoin' AND IFNULL(coins.image,'') = ''"
        )->queryAll();

        if (empty($rows)) return 0;
        $this->stdout("kucoin: trying to download new icons...\n");

        foreach ($rows as $row) {
            $coin   = Coins::findOne($row['id']);
            if (!$coin) continue;
            $symbol = $coin->getOfficialSymbol();
            $local  = "{$root}/images/coin-{$symbol}.png";
            $data   = @file_get_contents($base . $symbol . '.png');
            if (!$data || strlen($data) < 2048) continue;
            $this->stdout("{$symbol} icon found\n");
            file_put_contents($local, $data);
            if (filesize($local) > 0) {
                $coin->image = "/images/coin-{$symbol}.png";
                $updated    += (int) $coin->save(false);
            }
        }
        if ($updated) $this->stdout("{$updated} icons downloaded from kucoin\n");
        return $updated;
    }
}
