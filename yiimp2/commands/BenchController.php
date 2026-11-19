<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Benchmark statistics browser.
 *
 * Usage:
 *   php yii bench/check <algo> [chip]   — avg/min/max hashrate per chip for an algo
 *   php yii bench/rates <chip>          — all algo rates for a specific chip
 */
class BenchController extends Controller
{
    public $defaultAction = 'check';

    /** Report average, min, max hashrate per GPU chip for the given algo. */
    public function actionCheck(string $algo, string $chip = ''): int
    {
        $db    = Yii::$app->db;
        $chips = $db->createCommand(
            "SELECT DISTINCT C.chip AS name FROM benchmarks B
             INNER JOIN bench_chips C ON C.id = B.idchip
             WHERE B.algo = :algo AND C.devicetype = 'gpu' ORDER BY name",
            [':algo' => $algo]
        )->queryColumn();

        foreach ($chips as $c) {
            if ($chip !== '' && $c !== $chip) continue;
            $row = $db->createCommand(
                "SELECT AVG(khps) AS avg, MIN(khps) AS min, MAX(khps) AS max, COUNT(id) AS cnt
                 FROM benchmarks WHERE algo = :algo AND chip = :chip",
                [':algo' => $algo, ':chip' => $c]
            )->queryOne();
            $avg = round((float)$row['avg']);
            $min = round((float)$row['min']);
            $max = round((float)$row['max']);
            $this->stdout("{$algo} {$c}\t{$avg} kH/s {$min}-{$max} ({$row['cnt']} records)\n");
        }
        return ExitCode::OK;
    }

    /** Report all algo rates for a specific chip. */
    public function actionRates(string $chip): int
    {
        $rows = Yii::$app->db->createCommand(
            "SELECT algo, AVG(khps) AS avg, MIN(khps) AS min, MAX(khps) AS max, COUNT(id) AS cnt
             FROM benchmarks WHERE chip = :chip GROUP BY algo ORDER BY algo",
            [':chip' => $chip]
        )->queryAll();

        foreach ($rows as $r) {
            $avg = round((float)$r['avg']);
            $min = round((float)$r['min']);
            $max = round((float)$r['max']);
            $this->stdout("{$chip} {$r['algo']}\t{$avg} kH/s {$min}-{$max} ({$r['cnt']} records)\n");
        }
        return ExitCode::OK;
    }
}
