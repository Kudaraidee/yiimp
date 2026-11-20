<?php

namespace app\controllers;

use Yii;

class StatsController extends BaseController
{
    public $defaultAction = 'index';

    public function actionIndex(): string
    {
        $algo       = Yii::$app->session->get('yaamp-algo');
        $algoFactor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
        $algoUnit   = $this->algoUnit($algoFactor);

        // Enabled algo list for the selector
        $algos = array_column(
            (new \yii\db\Query())
                ->select(['algo', 'COUNT(id) AS count'])
                ->from('coins')
                ->where(['enable' => 1, 'visible' => 1])
                ->groupBy('algo')
                ->orderBy('algo')
                ->all(),
            'count',
            'algo'
        );

        $summaries = $this->buildSummaries($algo);

        return $this->render('index', [
            'algo'     => $algo,
            'algoUnit' => $algoUnit,
            'algos'    => $algos,
            'stats1'   => $summaries[1],
            'stats2'   => $summaries[2],
            'stats3'   => $summaries[3],
        ]);
    }

    /**
     * Lightweight JSON used by the 60s page refresh to update the summary
     * values above the graphs (graphs have their own endpoints).
     */
    public function actionSummary(): \yii\web\Response
    {
        return $this->asJson($this->buildSummaries(Yii::$app->session->get('yaamp-algo')));
    }

    private function algoUnit(float $factor): string
    {
        return match (true) {
            $factor == 0.001      => 'Kh',
            $factor == 1000       => 'Gh',
            $factor == 1000000    => 'Th',
            $factor == 1000000000 => 'Ph',
            default               => 'Mh',
        };
    }

    /**
     * @return array<int,array<string,string>> keyed by period number (1/2/3)
     */
    private function buildSummaries(string $algo): array
    {
        $algoFactor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
        $hour  = 3600;
        $days  = 86400;
        $cache = Yii::$app->cache;
        $conv  = Yii::$app->ConversionUtils;

        // Most-recent hashstats timestamp (cached 5 min)
        $dbMax = $cache->getOrSet("stats_maxt-{$algo}", function () use ($algo, $hour) {
            return (new \yii\db\Query())
                ->select(['(MAX(time) - 30*60)'])
                ->from('hashstats')
                ->where(['algo' => $algo])
                ->andWhere(['>', 'time', time() - 2 * $hour])
                ->scalar();
        }, 300);

        $dtMax = max(time() - $hour, (int) $dbMax);

        // Aggregate stats per period (cached 5 min each).
        // Bump the key suffix so old unfiltered values are not reused.
        $fetchRow = fn(int $since) => $this->fetchStatsRow($algo, $since);

        $rows = [
            1 => $cache->getOrSet("stats_col1-v2-{$algo}", fn() => $fetchRow($dtMax - 2 * $days), 300) ?: [],
            2 => $cache->getOrSet("stats_col2-v2-{$algo}", fn() => $fetchRow($dtMax - 7 * $days), 300) ?: [],
            3 => $cache->getOrSet("stats_col3-v2-{$algo}", fn() => $fetchRow($dtMax - 30 * $days), 300) ?: [],
        ];

        $daysByPeriod = [1 => 2, 2 => 7, 3 => 30];
        $summaries = [];
        foreach ($rows as $period => $row) {
            $a = max(1.0, (float) ($row['a'] ?? 0));
            $b = (float) ($row['b'] ?? 0);
            $periodDays = $daysByPeriod[$period];
            $summaries[$period] = [
                'hashrate'  => $conv->Itoa2($a),
                'total'     => $conv->bitcoinvaluetoa($b),
                'btcPerMhd' => ($a > 0 && $b > 0)
                    ? $conv->bitcoinvaluetoa(($b / $periodDays) * $algoFactor * (1_000_000 / $a))
                    : '0',
            ];
        }
        return $summaries;
    }

    /**
     * Average hashstats while ignoring corrupt spikes (e.g. a stray 1.9 Th/s
     * row in an otherwise ~100 Mh/s pool). The median of the window is used
     * as a robust baseline; anything over 100x it is discarded. Earnings are
     * still summed across every row.
     *
     * @return array{a:float,b:float}
     */
    private function fetchStatsRow(string $algo, int $since): array
    {
        $rows = (new \yii\db\Query())
            ->select(['hashrate', 'earnings'])
            ->from('hashstats')
            ->where(['algo' => $algo])
            ->andWhere(['>', 'time', $since])
            ->all();

        $rates = [];
        $earnings = 0.0;
        foreach ($rows as $row) {
            $rate = (float) ($row['hashrate'] ?? 0);
            if ($rate > 0) $rates[] = $rate;
            $earnings += (float) ($row['earnings'] ?? 0);
        }

        if ($rates) {
            sort($rates);
            $median = $rates[intdiv(count($rates), 2)];
            if ($median > 0) {
                $cap = $median * 100;
                $rates = array_filter($rates, fn(float $rate): bool => $rate <= $cap);
            }
        }

        return [
            'a' => $rates ? array_sum($rates) / count($rates) : 0.0,
            'b' => $earnings,
        ];
    }

    public function actionGraph_results_1(): string { return $this->renderPartial('graph_results_1'); }
    public function actionGraph_results_2(): string { return $this->renderPartial('graph_results_2'); }
    public function actionGraph_results_3(): string { return $this->renderPartial('graph_results_3'); }
    public function actionGraph_results_4(): string { return $this->renderPartial('graph_results_4'); }
    public function actionGraph_results_5(): string { return $this->renderPartial('graph_results_5'); }
    public function actionGraph_results_6(): string { return $this->renderPartial('graph_results_6'); }
    public function actionGraph_results_7(): string { return $this->renderPartial('graph_results_7'); }
    public function actionGraph_results_8(): string { return $this->renderPartial('graph_results_8'); }
    public function actionGraph_results_9(): string { return $this->renderPartial('graph_results_9'); }
}
