<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $coins app\models\Coins[] */

$this->title = 'Block Explorer';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="explorer-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Available Coins</h3>
                </div>
                <div class="card-body">
                    <p>Select a coin to explore its blockchain:</p>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Coin</th>
                                    <th>Symbol</th>
                                    <th>Algorithm</th>
                                    <th>Current Height</th>
                                    <th>Difficulty</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coins as $coin): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($coin->image)): ?>
                                            <img src="<?= Html::encode($coin->image) ?>" alt="<?= Html::encode($coin->name) ?>" class="img-24 mr-8">
                                        <?php endif; ?>
                                        <?= Html::encode($coin->name) ?>
                                    </td>
                                    <td><strong><?= Html::encode($coin->getOfficialSymbol()) ?></strong></td>
                                    <td><?= Html::encode($coin->algo) ?></td>
                                    <td>
                                        <?php
                                        // Get latest block height from blocks table
                                        $latestBlock = \app\models\Blocks::find()
                                            ->where(['coinid' => $coin->id])
                                            ->orderBy(['height' => SORT_DESC])
                                            ->one();
                                        echo $latestBlock ? number_format($latestBlock->height) : 'N/A';
                                        ?>
                                    </td>
                                    <td><?= $coin->difficulty ? number_format($coin->difficulty, 8) : 'N/A' ?></td>
                                    <td>
                                        <?= Html::a('Explore', ['coin', 'symbol' => $coin->getOfficialSymbol()], ['class' => 'btn btn-sm btn-primary']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($coins)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No coins available for exploration.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
