<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $model app\models\Benchmarks */
/* @var $form yii\widgets\ActiveForm */
/* @var $algos array */
/* @var $chips array */

$this->title = 'Submit Benchmark';
$this->params['breadcrumbs'][] = ['label' => 'Benchmarks', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="bench-submit">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Benchmark Submission Form</h5>
                </div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'benchmark-form',
                        'options' => ['class' => 'form-horizontal'],
                    ]); ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?= $form->field($model, 'algo')->dropDownList(
                                ArrayHelper::map(array_map(function($a) { return ['algo' => $a]; }, $algos), 'algo', 'algo'),
                                ['prompt' => 'Select Algorithm']
                            )->label('Algorithm *') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'type')->dropDownList([
                                'gpu' => 'GPU',
                                'cpu' => 'CPU',
                                'asic' => 'ASIC',
                                'fpga' => 'FPGA',
                            ], ['prompt' => 'Select Type'])->label('Device Type *') ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?= $form->field($model, 'device')->textInput([
                                'maxlength' => true,
                                'placeholder' => 'e.g., GeForce RTX 3080'
                            ])->label('Device Name') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'idchip')->dropDownList(
                                ArrayHelper::map($chips, 'id', function($chip) {
                                    return strtoupper($chip->devicetype) . ' - ' . $chip->chip;
                                }),
                                ['prompt' => 'Select Chip (Optional)']
                            )->label('Chip') ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?= $form->field($model, 'khps')->textInput([
                                'type' => 'number',
                                'step' => '0.01',
                                'placeholder' => 'Hashrate in kH/s'
                            ])->label('Hashrate (kH/s) *')->hint('Enter hashrate in kilohashes per second') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'power')->textInput([
                                'type' => 'number',
                                'placeholder' => 'Power consumption in Watts'
                            ])->label('Power (W)')->hint('Power consumption in Watts') ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?= $form->field($model, 'vendorid')->textInput([
                                'maxlength' => true,
                                'placeholder' => 'e.g., 10DE:2206'
                            ])->label('Vendor ID')->hint('PCI Vendor:Device ID') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'arch')->textInput([
                                'maxlength' => true,
                                'placeholder' => 'e.g., Ampere'
                            ])->label('Architecture') ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <?= $form->field($model, 'freq')->textInput([
                                'type' => 'number',
                                'placeholder' => 'Core frequency in MHz'
                            ])->label('Core Frequency (MHz)') ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'memf')->textInput([
                                'type' => 'number',
                                'placeholder' => 'Memory frequency in MHz'
                            ])->label('Memory Frequency (MHz)') ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'plimit')->textInput([
                                'type' => 'number',
                                'placeholder' => 'Power limit in Watts'
                            ])->label('Power Limit (W)') ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <?= $form->field($model, 'intensity')->textInput([
                                'type' => 'number',
                                'step' => '0.01',
                                'placeholder' => 'Mining intensity'
                            ])->label('Intensity') ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'throughput')->textInput([
                                'type' => 'number',
                                'placeholder' => 'Throughput value'
                            ])->label('Throughput') ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'client')->textInput([
                                'maxlength' => true,
                                'placeholder' => 'e.g., ccminer 2.3'
                            ])->label('Mining Client') ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <?= $form->field($model, 'os')->textInput([
                                'maxlength' => true,
                                'placeholder' => 'e.g., Linux'
                            ])->label('Operating System') ?>
                        </div>
                        <div class="col-md-8">
                            <?= $form->field($model, 'driver')->textInput([
                                'maxlength' => true,
                                'placeholder' => 'e.g., NVIDIA 525.60.11'
                            ])->label('Driver Version') ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <?= Html::submitButton('Submit Benchmark', ['class' => 'btn btn-primary']) ?>
                        <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Submission Guidelines</h5>
                </div>
                <div class="card-body">
                    <h6>Required Fields:</h6>
                    <ul>
                        <li>Algorithm</li>
                        <li>Device Type</li>
                        <li>Hashrate (kH/s)</li>
                    </ul>

                    <h6>Tips:</h6>
                    <ul>
                        <li>Enter hashrate in kilohashes per second (kH/s)</li>
                        <li>Include power consumption for efficiency calculations</li>
                        <li>Select the chip from the list if available</li>
                        <li>Provide accurate device and driver information</li>
                    </ul>

                    <h6>Hashrate Conversion:</h6>
                    <ul>
                        <li>1 MH/s = 1,000 kH/s</li>
                        <li>1 GH/s = 1,000,000 kH/s</li>
                        <li>1 TH/s = 1,000,000,000 kH/s</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
