<?php

use yii\helpers\Html;
use app\models\Workers;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

$user = Yii::$app->YiimpUtils->getuserbyaddress(Yii::$app->getRequest()->getQueryParam('address'));
if (!$user) return;

$delay = time() - 24 * 3600;
$algos = Yii::$app->YiimpUtils->get_algos() ?? [];

// ── Batch-check which algos have hashuser data or active workers ──────────────
// Replaces per-algo cache+query and Workers::find()->count() inside the loop.
$activeAlgos = [];
if (!empty($algos)) {
    // Hashuser presence (cached per user)
    $huMap = [];
    foreach ($algos as $algo) {
        $key = "wallet_hashuser-{$user->id}-{$algo}";
        $cnt = Yii::$app->cache->get($key);
        if ($cnt === false) {
            $cnt = (int) (new \yii\db\Query())
                ->from('hashuser')
                ->where(['userid' => $user->id, 'algo' => $algo])
                ->andWhere(['>', 'time', $delay])
                ->count();
            Yii::$app->cache->set($key, $cnt, 60);
        }
        $huMap[$algo] = (int) $cnt;
    }

    // Worker counts — one batch query instead of N per-algo counts
    $workerRows = (new \yii\db\Query())
        ->select(['algo', 'COUNT(*) AS c'])
        ->from('workers')
        ->where(['userid' => $user->id, 'algo' => array_keys($algos)])
        ->groupBy('algo')
        ->all();
    $workerMap = array_column($workerRows, 'c', 'algo');

    foreach ($algos as $algo => $_) {
        if ($huMap[$algo] || ($workerMap[$algo] ?? 0)) {
            $activeAlgos[] = $algo;
        }
    }
}

if (empty($activeAlgos)) return;

?>

<?php if ($isLegacy): ?>
<!-- LEGACY ───────────────────────────────────────────────────────────────── -->
<div class="main-left-box">
<div class="main-left-title">Last 24 Hours Hashrate: <?= Html::encode($user->username) ?></div>
<div class="main-left-inner"><br>
<?php foreach ($activeAlgos as $algo): ?>
<input type="hidden" id="<?= Html::encode($algo) ?>" class="graph_algo">
<div id="graph_results_<?= Html::encode($algo) ?>" style="height:240px;"></div><br>
<?php endforeach ?>
</div></div><br>


<?php elseif (!$isTailwind): ?>
<!-- ADMINLTE ─────────────────────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-activity text-secondary"></i>
        <strong class="small">Last 24h Hashrate — <?= Html::encode($user->username) ?></strong>
    </div>
    <div class="card-body">
    <?php foreach ($activeAlgos as $algo): ?>
        <input type="hidden" id="<?= Html::encode($algo) ?>" class="graph_algo">
        <div id="graph_results_<?= Html::encode($algo) ?>" style="height:200px;"></div>
        <?php if ($algo !== end($activeAlgos)): ?>
        <div style="margin-top:1rem;"></div>
        <?php endif ?>
    <?php endforeach ?>
    </div>
</div>


<?php else: ?>
<!-- TAILWIND ─────────────────────────────────────────────────────────────── -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-2">
        <i data-lucide="activity" class="w-4 h-4 text-indigo-500 shrink-0"></i>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
            Last 24h Hashrate
        </span>
    </div>
    <div class="p-4 space-y-4">
    <?php foreach ($activeAlgos as $algo): ?>
        <input type="hidden" id="<?= Html::encode($algo) ?>" class="graph_algo">
        <div>
            <div class="text-xs font-mono text-gray-400 dark:text-gray-500 mb-1"><?= Html::encode($algo) ?></div>
            <div id="graph_results_<?= Html::encode($algo) ?>" style="height:200px;"></div>
        </div>
    <?php endforeach ?>
    </div>
</div>

<?php endif ?>
