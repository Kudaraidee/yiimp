<?php

use yii\helpers\Html;
use app\components\ViewHelper;

/* @var $this yii\web\View */
/* @var $coins array */
/* @var $algo string */
/* @var $algoModel app\models\Algos */
/* @var $totalRate float */
/* @var $workerCount int */

// Helper functions moved inline to avoid redeclaration
$formatHashrate = function($hashrate) {
    if ($hashrate >= 1000000000000) {
        return number_format($hashrate / 1000000000000, 2) . ' Th/s';
    } elseif ($hashrate >= 1000000000) {
        return number_format($hashrate / 1000000000, 2) . ' Gh/s';
    } elseif ($hashrate >= 1000000) {
        return number_format($hashrate / 1000000, 2) . ' Mh/s';
    } elseif ($hashrate >= 1000) {
        return number_format($hashrate / 1000, 2) . ' Kh/s';
    } else {
        return number_format($hashrate, 2) . ' h/s';
    }
};

$formatDuration = function($seconds) {
    if (!$seconds) return '';
    
    if ($seconds < 60) {
        return round($seconds) . 's';
    } elseif ($seconds < 3600) {
        return round($seconds / 60) . 'm';
    } elseif ($seconds < 86400) {
        return round($seconds / 3600, 1) . 'h';
    } else {
        return round($seconds / 86400, 1) . 'd';
    }
};

$totalRateFormatted = $formatHashrate($totalRate);
$coinCount = count($coins);

?>

<div class="mining-profitability">
    <div class="mb-3">
        <h6>
            Mining <?= $coinCount ?> coins at <?= $totalRateFormatted ?> with <?= $workerCount ?> miners 
            (<?= Html::encode(strtoupper($algo)) ?>)
        </h6>
    </div>

    <?php if (empty($coins)): ?>
        <p class="text-muted">No coins available for this algorithm.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover">
                <thead>
                    <tr>
                        <th></th>
                        <th>Name</th>
                        <th class="text-end">Reward</th>
                        <th class="text-end">Difficulty</th>
                        <th class="text-end">Height</th>
                        <th class="text-end">TTF</th>
                        <th class="text-end">Pool Hash</th>
                        <th class="text-end">Profitability</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coins as $data): ?>
                        <?php
                        $coin = $data['coin'];
                        $profitability = $data['profitability'];
                        $poolHashrate = $data['pool_hashrate'];
                        $poolTtf = $data['pool_ttf'];
                        
                        // Format values
                        $difficulty = $formatHashrate($coin->difficulty);
                        $reward = number_format($coin->reward, 3);
                        $height = number_format($coin->block_height, 0, '.', ' ');
                        $ttf = $formatDuration($poolTtf);
                        $poolHash = $formatHashrate($poolHashrate);
                        $profitabilityFormatted = number_format($profitability, 4);
                        
                        // Determine row opacity based on auto_ready status
                        $rowClass = $coin->auto_ready ? '' : 'opacity-50';
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="w-20">
                                <?php if ($coin->image): ?>
                                    <img src="<?= Html::encode($coin->image) ?>" 
                                         alt="<?= Html::encode($coin->symbol) ?>" 
                                         width="16" height="16">
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong>
                                    <?= Html::a(
                                        Html::encode(substr($coin->name, 0, 12)),
                                        ['site/coin', 'id' => $coin->id]
                                    ) ?>
                                </strong>
                            </td>
                            <td class="text-end text-smaller">
                                <strong><?= $reward ?> <?= Html::encode($coin->symbol) ?></strong>
                            </td>
                            <td class="text-end text-smaller">
                                <?= $difficulty ?>
                            </td>
                            <td class="text-end text-smaller">
                                <?php if (!empty($coin->errors)): ?>
                                    <span class="text-danger" title="<?= Html::encode($coin->errors) ?>">
                                        <?= $height ?>
                                    </span>
                                <?php else: ?>
                                    <?= $height ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-smaller">
                                <?= $ttf ?>
                            </td>
                            <td class="text-end text-smaller">
                                <?= $poolHash ?>
                            </td>
                            <td class="text-end text-smaller">
                                <strong><?= $profitabilityFormatted ?> mBTC</strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            <small class="text-muted">
                <strong>Profitability</strong> is calculated as estimated mBTC per MH/day based on current difficulty, 
                block reward, and market price. Higher values indicate more profitable mining.
            </small>
        </div>

        <div class="mt-2">
            <small class="text-muted">
                <strong>TTF</strong> = Time To Find (estimated time for pool to find next block)
            </small>
        </div>
    <?php endif; ?>
</div>
