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
        $algoUnit   = match (true) {
            $algoFactor == 0.001      => 'Kh',
            $algoFactor == 1000       => 'Gh',
            $algoFactor == 1000000    => 'Th',
            $algoFactor == 1000000000 => 'Ph',
            default                   => 'Mh',
        };

        $hour = 3600;
        $days = 86400;
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
        $t1    = $dtMax - 2  * $days;
        $t2    = $dtMax - 7  * $days;
        $t3    = $dtMax - 30 * $days;

        // Aggregate stats per period (cached 5 min each)
        $fetchRow = fn(int $since) => (new \yii\db\Query())
            ->select(['AVG(hashrate) AS a', 'SUM(earnings) AS b'])
            ->from('hashstats')
            ->where(['algo' => $algo])
            ->andWhere(['>', 'time', $since])
            ->one();

        $row1 = $cache->getOrSet("stats_col1-{$algo}", fn() => $fetchRow($t1), 300) ?: [];
        $row2 = $cache->getOrSet("stats_col2-{$algo}", fn() => $fetchRow($t2), 300) ?: [];
        $row3 = $cache->getOrSet("stats_col3-{$algo}", fn() => $fetchRow($t3), 300) ?: [];

        // Summarise one period into display-ready strings
        $summarise = function (array $row, int $periodDays) use ($conv, $algoFactor): array {
            $a = max(1.0, (float) ($row['a'] ?? 0));
            $b = (float) ($row['b'] ?? 0);
            return [
                'hashrate'  => $conv->Itoa2($a),
                'total'     => $conv->bitcoinvaluetoa($b),
                'btcPerMhd' => ($a > 0 && $b > 0)
                    ? $conv->bitcoinvaluetoa(($b / $periodDays) * $algoFactor * (1_000_000 / $a))
                    : '0',
            ];
        };

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

        return $this->render('index', [
            'algo'     => $algo,
            'algoUnit' => $algoUnit,
            'algos'    => $algos,
            'stats1'   => $summarise($row1, 2),
            'stats2'   => $summarise($row2, 7),
            'stats3'   => $summarise($row3, 30),
        ]);
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
