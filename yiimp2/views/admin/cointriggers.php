<?php

/** @var yii\web\View              $this          */
/** @var app\models\Coins          $coin          */
/** @var array|false               $info          */
/** @var app\models\Notifications[] $notifications */

use yii\helpers\Html;

$this->title = 'Triggers — ' . $coin->symbol;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$conv       = Yii::$app->ConversionUtils;

$backUrl  = '/admin/coinwallet?id=' . $coin->id;
$addUrl   = '/admin/cointrigger-add?id=' . $coin->id;

$notifyTypes = ['email' => 'Email', 'rpc' => 'RPC command', 'system' => 'System command'];

$helpVars = [
    '$X'   => 'current value',
    '$F'   => 'condition db field',
    '$T'   => 'condition type',
    '$V'   => 'condition ref value',
    '$SYM' => 'coin symbol',
    '$S2'  => 'coin symbol2',
    '$N'   => 'coin name',
    '$A'   => 'wallet address',
];

// Summary counts for the mining-style header.
$ruleCount      = count($notifications);
$activeCount    = 0;
$triggeredCount = 0;
$lastChecked    = 0;
foreach ($notifications as $triggerRule) {
    if ($triggerRule->enabled) $activeCount++;
    if ($triggerRule->lasttriggered && $triggerRule->lasttriggered == $triggerRule->lastchecked) {
        $triggeredCount++;
    }
    $lastChecked = max($lastChecked, (int) $triggerRule->lastchecked);
}

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY — mining-style admin chrome
     ══════════════════════════════════════════════════════════════════════════ -->
<?php
$walletLinks = str_replace([' || ', '<br/>', '<br />'], '',
    Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'triggers'));
?>
<div class="admn-page">
<?= Yii::$app->ViewUtils->getAdminMiningToolbar(
    'Triggers',
    $coin->symbol . ' · ' . $coin->algo . ' · notification rules',
    $ruleCount . ' RULES',
    'static · on load'
) ?>
<div class="admn-selectbar">
    <label>Coin</label>
    <div class="admn-toolbar-links"><?= $walletLinks ?></div>
</div>

<div class="admn-kpis">
    <div class="admn-kpi accent">
        <span class="k-label">Rules</span>
        <span class="k-value"><?= (int) $ruleCount ?></span>
        <span class="k-sub">configured for this coin</span>
    </div>
    <div class="admn-kpi good">
        <span class="k-label">Active</span>
        <span class="k-value"><?= (int) $activeCount ?></span>
        <span class="k-sub">enabled rules</span>
    </div>
    <div class="admn-kpi <?= $triggeredCount ? 'warn' : '' ?>">
        <span class="k-label">Triggered</span>
        <span class="k-value"><?= (int) $triggeredCount ?></span>
        <span class="k-sub">pending attention</span>
    </div>
    <div class="admn-kpi">
        <span class="k-label">Last check</span>
        <span class="k-value"><?= $lastChecked ? Html::encode($conv->datetoa2($lastChecked)) : '—' ?></span>
        <span class="k-sub">latest rule poll</span>
    </div>
</div>

<?php if (!$info): ?>
<div class="admn-card">
    <div class="admn-card-head">
        <span class="admn-head-main"><span class="admn-title">Triggers Unavailable</span>
        <span class="admn-sub">rules are shown when the daemon answers</span></span>
        <span class="admn-badge warn">offline</span>
    </div>
    <div class="admn-card-body">
        Unable to connect to <b><?= Html::encode($coin->symbol) ?></b> wallet daemon.
    </div>
</div>
<?php else: ?>
<div class="admn-card">
    <div class="admn-card-head">
        <span class="admn-head-main"><span class="admn-title">Notification Triggers</span>
        <span class="admn-sub">wallet conditions · alert routing</span></span>
        <span class="admn-badge <?= $triggeredCount ? 'warn' : 'good' ?>"><?= (int) $ruleCount ?> rules</span>
    </div>
    <div class="admn-card-body flush">
<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    textExtraction: { 5: function(node, table, n) { return \$(node).attr('data'); } },
    widgets: ['zebra','Storage','saveSort'],
    widgetOptions: { saveSort: true }
}") ?>
<thead><tr>
<th>Type</th><th>Condition</th><th>Value</th><th width="50%">Description / Command</th>
<th>Status</th><th width="80">Last check</th><th align="right" width="180">Operations</th>
</tr></thead><tbody>
<?php if (empty($notifications)): ?>
<tr><td colspan="7" class="admn-empty">No triggers defined.</td></tr>
<?php endif ?>
<?php foreach ($notifications as $rule): ?>
<?php
    $rid        = (int) $rule->id;
    $triggered  = $rule->lasttriggered && $rule->lasttriggered == $rule->lastchecked;

    $ops = $rule->enabled
        ? '<a href="/admin/cointrigger-enable?id=' . $rid . '&en=0">disable</a>'
        : '<a href="/admin/cointrigger-enable?id=' . $rid . '&en=1">enable</a>';
    $ops .= ' <a class="red" href="/admin/cointrigger-del?id=' . $rid . '">delete</a>';
    if ($triggered) {
        $ops = '<a href="/admin/cointrigger-reset?id=' . $rid . '">reset</a> ' . $ops;
    }

    if ($triggered) {
        $status = '<span class="admn-badge good">Triggered</span>';
    } elseif ($rule->enabled) {
        $status = '<span class="admn-badge">Active</span>';
    } else {
        $status = '<span class="admn-badge muted">Off</span>';
    }

    $desc = Html::encode($rule->description ?? '');
    if (!empty($rule->description) && !empty($rule->notifycmd)) $desc .= '<br/>';
    $desc .= '<span class="admn-muted mono">' . Html::encode($rule->notifycmd ?? '') . '</span>';
?>
<tr class="ssrow<?= $rule->enabled ? '' : ' disabled' ?>">
    <td><b><?= Html::encode($rule->notifytype) ?></b></td>
    <td class="mono"><?= Html::encode($rule->conditiontype) ?></td>
    <td class="mono"><?= Html::encode($rule->conditionvalue) ?></td>
    <td><?= $desc ?></td>
    <td><?= $status ?></td>
    <td data="<?= (int) $rule->lastchecked ?>"><?= Html::encode($conv->datetoa2($rule->lastchecked)) ?></td>
    <td class="admn-actions" align="right"><?= $ops ?></td>
</tr>
<?php endforeach ?>
</tbody></table>
    </div>
</div>

<div class="admn-grid">
    <div class="admn-card">
        <div class="admn-card-head">
            <span class="admn-head-main"><span class="admn-title">Add Trigger</span>
            <span class="admn-sub">create a wallet condition</span></span>
            <span class="admn-badge muted">POST</span>
        </div>
        <div class="admn-card-body">
            <form action="<?= Html::encode($addUrl) ?>" method="post" class="admn-form">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <div class="admn-form-row">
                    <div class="admn-field grow">
                        <label>Type</label>
                        <select name="notifytype" class="admn-input">
                        <?php foreach ($notifyTypes as $v => $l): ?>
                            <option value="<?= Html::encode($v) ?>"><?= Html::encode($l) ?></option>
                        <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="admn-form-row">
                    <div class="admn-field grow">
                        <label>Condition</label>
                        <input type="text" name="conditiontype" class="admn-input mono" placeholder="Condition like 'balance >'">
                    </div>
                    <div class="admn-field narrow">
                        <label>Value</label>
                        <input type="text" name="conditionvalue" class="admn-input mono" placeholder="Value">
                    </div>
                </div>
                <div class="admn-form-row">
                    <div class="admn-field grow">
                        <label>Command / Email <span class="admn-muted">(optional)</span></label>
                        <input type="text" name="notifycmd" class="admn-input mono" placeholder="Email address or shell command">
                    </div>
                </div>
                <div class="admn-form-row">
                    <div class="admn-field grow">
                        <label>Description <span class="admn-muted">(optional)</span></label>
                        <input type="text" name="description" class="admn-input" placeholder="Human-readable label">
                    </div>
                </div>
                <div class="admn-form-row">
                    <button type="submit" class="admn-pill primary">Add rule</button>
                </div>
            </form>
        </div>
    </div>

    <div class="admn-card">
        <div class="admn-card-head">
            <span class="admn-head-main"><span class="admn-title">Command Variables</span>
            <span class="admn-sub">expand in notify commands</span></span>
            <span class="admn-badge">HELP</span>
        </div>
        <div class="admn-card-body">
            <div class="admn-help-list">
            <?php foreach ($helpVars as $var => $helpDesc): ?>
                <span class="h-var"><?= Html::encode($var) ?></span>
                <span class="h-desc"><?= Html::encode($helpDesc) ?></span>
            <?php endforeach ?>
            </div>
        </div>
    </div>
</div>
<?php endif ?>
</div><!-- /.admn-page -->

<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div><?= Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'triggers') ?></div>
    <a href="<?= Html::encode($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?= \app\widgets\Alert::widget() ?>

<?php if (!$info): ?>
<div class="alert alert-danger">Unable to connect to <strong><?= Html::encode($coin->symbol) ?></strong> wallet daemon.</div>
<?php else: ?>

<!-- Trigger table -->
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-bell text-secondary"></i>
        <strong class="small">Notification Triggers</strong>
        <span class="badge bg-secondary ms-1"><?= count($notifications) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small">Type</th>
                    <th class="small">Condition</th>
                    <th class="small">Value</th>
                    <th class="small">Description / Command</th>
                    <th class="small">Status</th>
                    <th class="small">Last check</th>
                    <th class="small text-end">Operations</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($notifications)): ?>
            <tr><td colspan="7" class="text-center text-muted small py-3">No triggers defined.</td></tr>
            <?php endif ?>
            <?php foreach ($notifications as $rule): ?>
            <?php
                $triggered = $rule->lasttriggered && $rule->lasttriggered == $rule->lastchecked;
                $rowCls    = !$rule->enabled ? 'table-secondary opacity-75' : ($triggered ? 'table-success' : '');
            ?>
            <tr class="<?= $rowCls ?>">
                <td class="small fw-bold"><?= Html::encode($rule->notifytype) ?></td>
                <td class="small font-monospace"><?= Html::encode($rule->conditiontype) ?></td>
                <td class="small font-monospace"><?= Html::encode($rule->conditionvalue) ?></td>
                <td class="small">
                    <?= Html::encode($rule->description ?? '') ?>
                    <?php if (!empty($rule->notifycmd)): ?>
                    <br><span class="text-muted font-monospace"><?= Html::encode($rule->notifycmd) ?></span>
                    <?php endif ?>
                </td>
                <td class="small">
                    <?php if ($triggered): ?>
                    <span class="badge bg-success">Triggered</span>
                    <?php elseif ($rule->enabled): ?>
                    <span class="badge bg-secondary">Active</span>
                    <?php else: ?>
                    <span class="badge bg-light text-dark border">Off</span>
                    <?php endif ?>
                </td>
                <td class="small text-muted text-nowrap"><?= $conv->datetoa2($rule->lastchecked) ?></td>
                <td class="small text-end text-nowrap">
                    <?php if ($triggered): ?>
                    <a href="/admin/cointrigger-reset?id=<?= $rule->id ?>" class="me-1">reset</a>
                    <?php endif ?>
                    <?php if ($rule->enabled): ?>
                    <a href="/admin/cointrigger-enable?id=<?= $rule->id ?>&en=0" class="me-1">disable</a>
                    <?php else: ?>
                    <a href="/admin/cointrigger-enable?id=<?= $rule->id ?>&en=1" class="me-1">enable</a>
                    <?php endif ?>
                    <a href="/admin/cointrigger-del?id=<?= $rule->id ?>" class="text-danger">delete</a>
                </td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Add trigger form -->
<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2"><strong class="small">Add Trigger</strong></div>
            <div class="card-body">
                <form action="<?= Html::encode($addUrl) ?>" method="post">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <div class="mb-2">
                    <label class="form-label small fw-semibold mb-1">Type</label>
                    <select name="notifytype" class="form-select form-select-sm">
                    <?php foreach ($notifyTypes as $v => $l): ?>
                        <option value="<?= $v ?>"><?= Html::encode($l) ?></option>
                    <?php endforeach ?>
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-7">
                        <label class="form-label small fw-semibold mb-1">Condition</label>
                        <input type="text" name="conditiontype" class="form-control form-control-sm font-monospace" placeholder="e.g. balance >">
                    </div>
                    <div class="col-5">
                        <label class="form-label small fw-semibold mb-1">Value</label>
                        <input type="text" name="conditionvalue" class="form-control form-control-sm font-monospace" placeholder="e.g. 5">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold mb-1">Command / Email <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" name="notifycmd" class="form-control form-control-sm font-monospace" placeholder="Email address or shell command">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold mb-1">Description <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" name="description" class="form-control form-control-sm" placeholder="Human-readable label">
                </div>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Add Rule
                </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm mb-3 border-warning">
            <div class="card-header py-2 bg-warning bg-opacity-10">
                <strong class="small">Command Variables</strong>
            </div>
            <div class="card-body py-2">
                <div class="row row-cols-2 g-1">
                <?php foreach ($helpVars as $var => $desc): ?>
                <div class="col">
                    <span class="font-monospace text-primary small"><?= Html::encode($var) ?></span>
                    <span class="text-muted small ms-1"><?= Html::encode($desc) ?></span>
                </div>
                <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif ?>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
    <div><?= Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'triggers') ?></div>
    <a href="<?= Html::encode($backUrl) ?>"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
              border border-gray-300 dark:border-gray-600
              bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
              hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors shrink-0">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back
    </a>
</div>

<?= \app\widgets\Alert::widget() ?>

<?php if (!$info): ?>
<div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 px-4 py-3 text-sm text-red-600 dark:text-red-400">
    Unable to connect to <strong><?= Html::encode($coin->symbol) ?></strong> wallet daemon.
</div>
<?php else: ?>

<!-- Trigger table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">
    <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
        <i data-lucide="bell" class="w-4 h-4 text-gray-400 shrink-0"></i>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Notification Triggers</span>
        <span class="ml-2 text-xs px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400"><?= count($notifications) ?></span>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-xs">
        <thead>
        <tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">
            <th class="px-3 py-2 text-left">Type</th>
            <th class="px-3 py-2 text-left">Condition</th>
            <th class="px-3 py-2 text-left">Value</th>
            <th class="px-3 py-2 text-left">Description / Command</th>
            <th class="px-3 py-2 text-left">Status</th>
            <th class="px-3 py-2 text-left">Last check</th>
            <th class="px-3 py-2 text-right">Operations</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($notifications)): ?>
        <tr><td colspan="7" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">No triggers defined.</td></tr>
        <?php endif ?>
        <?php foreach ($notifications as $rule): ?>
        <?php
            $triggered = $rule->lasttriggered && $rule->lasttriggered == $rule->lastchecked;
            $rowCls    = !$rule->enabled
                ? 'opacity-50'
                : ($triggered ? 'bg-green-50/40 dark:bg-green-900/10' : '');
        ?>
        <tr class="<?= $rowCls ?> border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50/50 dark:hover:bg-gray-700/20">
            <td class="px-3 py-1.5 font-semibold text-gray-700 dark:text-gray-300"><?= Html::encode($rule->notifytype) ?></td>
            <td class="px-3 py-1.5 font-mono text-gray-600 dark:text-gray-400"><?= Html::encode($rule->conditiontype) ?></td>
            <td class="px-3 py-1.5 font-mono text-gray-600 dark:text-gray-400"><?= Html::encode($rule->conditionvalue) ?></td>
            <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300">
                <?= Html::encode($rule->description ?? '') ?>
                <?php if (!empty($rule->notifycmd)): ?>
                <span class="block font-mono text-gray-400 dark:text-gray-500"><?= Html::encode($rule->notifycmd) ?></span>
                <?php endif ?>
            </td>
            <td class="px-3 py-1.5">
                <?php if ($triggered): ?>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">Triggered</span>
                <?php elseif ($rule->enabled): ?>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">Active</span>
                <?php else: ?>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500">Off</span>
                <?php endif ?>
            </td>
            <td class="px-3 py-1.5 text-gray-400 dark:text-gray-500 whitespace-nowrap"><?= $conv->datetoa2($rule->lastchecked) ?></td>
            <td class="px-3 py-1.5 text-right whitespace-nowrap space-x-2">
                <?php if ($triggered): ?>
                <a href="/admin/cointrigger-reset?id=<?= $rule->id ?>" class="text-indigo-500 hover:underline">reset</a>
                <?php endif ?>
                <?php if ($rule->enabled): ?>
                <a href="/admin/cointrigger-enable?id=<?= $rule->id ?>&en=0" class="text-gray-500 hover:underline">disable</a>
                <?php else: ?>
                <a href="/admin/cointrigger-enable?id=<?= $rule->id ?>&en=1" class="text-gray-500 hover:underline">enable</a>
                <?php endif ?>
                <a href="/admin/cointrigger-del?id=<?= $rule->id ?>" class="text-red-500 hover:underline">delete</a>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Add trigger form + help -->
<div class="flex flex-col xl:flex-row gap-4">

    <!-- Form -->
    <div class="flex-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700">
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Add Trigger</span>
        </div>
        <div class="px-4 py-4">
        <form action="<?= Html::encode($addUrl) ?>" method="post">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>

            <div class="mb-3">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Type</label>
                <select name="notifytype"
                        class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600
                               bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-3 py-1.5
                               focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <?php foreach ($notifyTypes as $v => $l): ?>
                    <option value="<?= $v ?>"><?= Html::encode($l) ?></option>
                <?php endforeach ?>
                </select>
            </div>

            <div class="flex gap-2 mb-3">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Condition</label>
                    <input type="text" name="conditiontype"
                           class="w-full text-sm font-mono rounded-lg border border-gray-300 dark:border-gray-600
                                  bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-3 py-1.5
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500"
                           placeholder="e.g. balance >">
                </div>
                <div class="w-28">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Value</label>
                    <input type="text" name="conditionvalue"
                           class="w-full text-sm font-mono rounded-lg border border-gray-300 dark:border-gray-600
                                  bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-3 py-1.5
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500"
                           placeholder="e.g. 5">
                </div>
            </div>

            <div class="mb-3">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                    Command / Email <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <input type="text" name="notifycmd"
                       class="w-full text-sm font-mono rounded-lg border border-gray-300 dark:border-gray-600
                              bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-3 py-1.5
                              focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       placeholder="Email address or shell command">
            </div>

            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                    Description <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <input type="text" name="description"
                       class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600
                              bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-3 py-1.5
                              focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       placeholder="Human-readable label">
            </div>

            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white
                           bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>Add Rule
            </button>
        </form>
        </div>
    </div>

    <!-- Help -->
    <div class="xl:w-64 bg-amber-50 dark:bg-amber-900/10 rounded-xl border border-amber-200 dark:border-amber-700/50 shadow-sm overflow-hidden self-start">
        <div class="px-4 py-2.5 border-b border-amber-200 dark:border-amber-700/50">
            <span class="text-sm font-semibold text-amber-800 dark:text-amber-300">Command Variables</span>
        </div>
        <div class="px-4 py-3">
        <dl class="grid grid-cols-2 gap-x-3 gap-y-1">
            <?php foreach ($helpVars as $var => $desc): ?>
            <dt class="text-xs font-mono text-indigo-600 dark:text-indigo-400"><?= Html::encode($var) ?></dt>
            <dd class="text-xs text-gray-600 dark:text-gray-400"><?= Html::encode($desc) ?></dd>
            <?php endforeach ?>
        </dl>
        </div>
    </div>

</div>

<?php endif ?>

<?php
Yii::$app->ViewUtils->JavascriptReady(<<<JS
if (typeof lucide !== 'undefined') lucide.createIcons();
JS);
?>

<?php endif ?>
