<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\db\Query;
use app\services\BenchService;

/**
 * Public benchmark browser.
 * Ported from web/yaamp/modules/bench/BenchController.php.
 * actionDel() lives in AdminController::actionBenchdel().
 */
class BenchController extends BaseController
{
    public function actionIndex(): string
    {
        $request = Yii::$app->request;
        $session = Yii::$app->session;
        $db      = Yii::$app->db;

        $algo = substr($request->get('algo', ''), 0, 32);
        if ($algo) {
            $exists = (new Query())->from('benchmarks')->where(['algo' => $algo])->exists();
            $session->set('bench-algo', $exists ? $algo : 'all');
        }
        $algo   = $session->get('bench-algo', 'all');
        $vid    = substr($request->get('vid', ''), 0, 20);
        $idchip = (int) $request->get('chip', 0);

        // Algos for the dropdown
        $vidParam  = $vid ? [':vid' => $vid] : [];
        $vidClause = $vid ? ' WHERE vendorid = :vid' : '';
        $algoRows  = $db->createCommand(
            "SELECT algo, COUNT(id) AS cnt FROM benchmarks{$vidClause} GROUP BY algo ORDER BY algo ASC",
            $vidParam
        )->queryAll();
        $algos = array_column($algoRows, 'cnt', 'algo');

        // Chips for the dropdown
        $params     = [];
        $conditions = ['B.idchip IS NOT NULL'];
        if ($algo !== 'all') { $conditions[] = 'B.algo = :algo'; $params[':algo'] = $algo; }
        if ($vid)            { $conditions[] = 'B.vendorid = :vid'; $params[':vid'] = $vid; }
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $chipRows = $db->createCommand(
            "SELECT DISTINCT B.idchip AS id, C.chip AS name
             FROM benchmarks B
             LEFT JOIN bench_chips C ON C.id = B.idchip
             {$where}
             GROUP BY B.idchip ORDER BY B.type DESC, name ASC",
            $params
        )->queryAll();
        $chips = array_column($chipRows, 'name', 'id');

        // Benchmark rows
        $rowConds  = [];
        $rowParams = [];
        if ($algo !== 'all') { $rowConds[] = 'B.algo = :algo'; $rowParams[':algo'] = $algo; }
        if ($vid)            { $rowConds[] = 'B.vendorid = :vid'; $rowParams[':vid'] = $vid; }
        if ($idchip)         { $rowConds[] = 'B.idchip = :chip'; $rowParams[':chip'] = $idchip; }
        $rowWhere = $rowConds ? 'WHERE ' . implode(' AND ', $rowConds) : '';

        $rows = $db->createCommand(
            "SELECT B.*, C.chip AS chip_name
             FROM benchmarks B
             LEFT JOIN bench_chips C ON C.id = B.idchip
             {$rowWhere}
             ORDER BY B.time DESC LIMIT 100",
            $rowParams
        )->queryAll();

        // Footer averages
        $avg = null;
        if ($algo !== 'all') {
            $avgParams = [':algo' => $algo];
            $avgConds  = ['algo = :algo', 'B.intensity > 0', 'power > 5'];
            if ($idchip) { $avgConds[] = 'B.idchip = :chip'; $avgParams[':chip'] = $idchip; }
            if ($vid)    { $avgConds[] = 'B.vendorid = :vid'; $avgParams[':vid'] = $vid; }
            $avgWhere = 'WHERE ' . implode(' AND ', $avgConds);

            $avg = $db->createCommand(
                "SELECT AVG(khps) AS khps, AVG(power) AS power,
                        AVG(B.intensity) AS intensity, AVG(freq) AS freq, COUNT(*) AS records
                 FROM benchmarks B {$avgWhere}",
                $avgParams
            )->queryOne();

            if ((int)($avg['records'] ?? 0) === 0) {
                unset($avgParams[':chip'], $avgParams[':vid']);
                $simpleParams = [':algo' => $algo];
                if ($idchip) $simpleParams[':chip'] = $idchip;
                if ($vid)    $simpleParams[':vid']  = $vid;
                $simpleConds = ['algo = :algo'];
                if ($idchip) $simpleConds[] = 'B.idchip = :chip';
                if ($vid)    $simpleConds[] = 'B.vendorid = :vid';
                $avg = $db->createCommand(
                    'SELECT AVG(khps) AS khps, NULL AS power, NULL AS intensity,
                            NULL AS freq, COUNT(*) AS records
                     FROM benchmarks B WHERE ' . implode(' AND ', $simpleConds),
                    $simpleParams
                )->queryOne();
            }
        }

        $isAdmin = !Yii::$app->user->isGuest && (Yii::$app->user->identity?->is_admin ?? false);

        return $this->render('index', compact('algo', 'vid', 'idchip', 'algos', 'chips', 'rows', 'avg', 'isAdmin'));
    }

    public function actionDevices(): string
    {
        $db    = Yii::$app->db;
        $cache = Yii::$app->cache;

        $devices = $cache->getOrSet('bench-devices', function () use ($db) {
            return $db->createCommand(
                'SELECT DISTINCT device, type, chip, idchip, vendorid
                 FROM benchmarks WHERE idchip > 0 ORDER BY type DESC, device, vendorid'
            )->queryAll();
        }, 120);

        $month = time() - 30 * 86400;
        $algos = $cache->getOrSet('bench-algos', function () use ($db, $month) {
            return $db->createCommand(
                'SELECT DISTINCT algo FROM benchmarks WHERE time > :m ORDER BY algo LIMIT 20',
                [':m' => $month]
            )->queryColumn();
        }, 120);

        // Pre-fetch algo coverage per vendorid (GPU) and per device (CPU) in two queries
        $gpuCoverage = [];
        foreach ($db->createCommand(
            'SELECT vendorid, algo FROM benchmarks WHERE vendorid != "" GROUP BY vendorid, algo'
        )->queryAll() as $r) {
            $gpuCoverage[$r['vendorid']][] = $r['algo'];
        }

        $cpuCoverage = [];
        foreach ($db->createCommand(
            'SELECT device, algo FROM benchmarks WHERE (vendorid IS NULL OR vendorid = "") GROUP BY device, algo'
        )->queryAll() as $r) {
            $cpuCoverage[$r['device']][] = $r['algo'];
        }

        return $this->render('devices', compact('devices', 'algos', 'gpuCoverage', 'cpuCoverage'));
    }

    public function actionAlgo(): string
    {
        $algo = substr(Yii::$app->request->get('algo', ''), 0, 32);
        if (empty($algo)) {
            return $this->redirect(['/bench']);
        }

        $db = Yii::$app->db;

        $algoRows = $db->createCommand(
            'SELECT algo, COUNT(id) AS cnt FROM benchmarks GROUP BY algo ORDER BY algo ASC'
        )->queryAll();
        $algos = array_column($algoRows, 'cnt', 'algo');

        $rows = $db->createCommand(
            "SELECT B.type, B.idchip, C.chip,
                    AVG(B.khps) AS khps, AVG(B.power) AS power,
                    AVG(B.intensity) AS intensity, AVG(B.freq) AS freq
             FROM benchmarks B
             LEFT JOIN bench_chips C ON C.id = B.idchip
             WHERE B.idchip > 0 AND B.algo = :algo
             GROUP BY B.type, B.idchip ORDER BY khps DESC",
            [':algo' => $algo]
        )->queryAll();

        $t1      = time() - 86400;
        $price24 = (float) $db->createCommand(
            'SELECT AVG(price) FROM hashrate WHERE time > :t AND algo = :algo',
            [':t' => $t1, ':algo' => $algo]
        )->queryScalar();
        $factor  = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
        $algo24E = ($factor > 0 && $price24 > 0) ? $price24 / (1000 * $factor) : 0.0;

        $btcusd = (float) ($db->createCommand('SELECT usdbtc FROM mining LIMIT 1')->queryScalar() ?: 500.0);

        return $this->render('algo', compact('algo', 'algos', 'rows', 'algo24E', 'btcusd'));
    }
}
