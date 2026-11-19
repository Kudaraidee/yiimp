<?php

/** @var yii\web\View $this        */
/** @var string       $coinOptions */

use yii\helpers\Html;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

$selectCls = $isTailwind
    ? 'px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600
       bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
       focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono'
    : 'form-select form-select-sm font-monospace';

$inputCls = $isTailwind
    ? 'px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600
       bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
       focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono'
    : 'form-control form-control-sm font-monospace';

$labelCls = $isTailwind
    ? 'text-xs text-gray-400 dark:text-gray-500 mb-0.5'
    : 'text-muted small';

$outputCls = $isTailwind
    ? 'w-full px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200
       dark:border-gray-700 font-mono text-xs text-gray-700 dark:text-gray-300 break-all mt-2'
    : 'font-monospace small p-2 bg-light border rounded mt-2';
?>

<div class="<?= $isTailwind ? 'space-y-3' : '' ?>">
    <div class="<?= $isTailwind ? 'grid grid-cols-2 gap-3 sm:grid-cols-3' : 'row g-2' ?>">

        <div class="<?= $isTailwind ? '' : 'col-auto' ?>">
            <div class="<?= $labelCls ?>">Stratum</div>
            <select id="drop-stratum" class="<?= $selectCls ?>" onchange="generate()">
                <option value="">Main</option>
            </select>
        </div>

        <div class="<?= $isTailwind ? 'col-span-2 sm:col-span-1' : 'col' ?>">
            <div class="<?= $labelCls ?>">Coin</div>
            <select id="drop-coin" class="<?= $selectCls ?> w-full" onchange="generate()">
                <?= $coinOptions ?>
            </select>
        </div>

        <div class="<?= $isTailwind ? '' : 'col-auto' ?>">
            <div class="<?= $labelCls ?>">Type</div>
            <select id="drop-solo" class="<?= $selectCls ?>" onchange="generate()">
                <option value="">Shared</option>
                <option value=",m=solo">Solo</option>
            </select>
        </div>

        <div class="<?= $isTailwind ? '' : 'col' ?>">
            <div class="<?= $labelCls ?>">Wallet address</div>
            <input id="text-wallet" type="text" class="<?= $inputCls ?> w-full"
                   placeholder="WALLET_ADDRESS" onkeyup="generate()">
        </div>

        <div class="<?= $isTailwind ? '' : 'col-auto' ?>">
            <div class="<?= $labelCls ?>">Rig name</div>
            <input id="text-rig-name" type="text" class="<?= $inputCls ?>"
                   style="width:80px" placeholder="001" onkeyup="generate()">
        </div>

    </div>

    <pre id="stratum-output" class="<?= $outputCls ?>">-a  -o stratum+tcp://<?= Html::encode(YIIMP_STRATUM_URL) ?>:0000 -u WALLET.WORKER -p c=</pre>

    <p class="<?= $isTailwind ? 'text-xs text-gray-400 dark:text-gray-500' : 'text-muted small mb-0' ?>">
        Use your wallet address as username. Payouts are in the mined currency.
    </p>
</div>
