<?php
/** @var yii\web\View         $this */
/** @var app\models\Coins     $coin */
/** @var array|false|null     $info */
/** @var string               $src  */

use yii\helpers\Html;

$html = Html::a('<b>COIN PROPERTIES</b>', '/admin/coinwallet_update?id=' . $coin->id);

if ($info) {
    $html .= ' || ' . $coin->createExplorerLink('<b>EXPLORER</b>');
    $html .= ' || ' . Html::a('<b>PEERS</b>',    '/admin/coinpeers?id='   . $coin->id);
    if (YIIMP_ADMIN_WEBCONSOLE)
        $html .= ' || ' . Html::a('<b>CONSOLE</b>', '/admin/coinwallet-console?id=' . $coin->id);
    $html .= ' || ' . Html::a('<b>TRIGGERS</b>', '/admin/cointriggers?id='        . $coin->id);
    if ($src !== 'wallet')
        $html .= ' || ' . Html::a('<b>' . Html::encode($coin->symbol) . '</b>', '/admin/coinwallet?id=' . $coin->id);
}

if (!$info && $coin->enable)
    $html .= '<br/>' . Html::a('<b>STOP COIND</b>', '/admin/stopcoin?id=' . $coin->id);

$html .= '<br/>' . ($coin->auto_ready
    ? Html::a('<b>UNSET AUTO</b>', '/admin/coinwallet-unsetauto?id=' . $coin->id)
    : Html::a('<b>SET AUTO</b>',   '/admin/coinwallet-setauto?id='   . $coin->id));

$html .= '<br/>';
if (!empty($coin->link_bitcointalk)) $html .= Html::a('forum',  $coin->link_bitcointalk, ['target' => '_blank']) . ' ';
if (!empty($coin->link_github))      $html .= Html::a('git',    $coin->link_github,      ['target' => '_blank']) . ' ';
if (!empty($coin->link_site))        $html .= Html::a('site',   $coin->link_site,        ['target' => '_blank']) . ' ';
if (!empty($coin->link_explorer))    $html .= Html::a('chain',  $coin->link_explorer,    ['target' => '_blank', 'title' => 'External Blockchain Explorer']) . ' ';
$html .= Html::a('google', 'http://google.com/search?q=' . urlencode($coin->name . ' ' . $coin->symbol . ' bitcointalk'), ['target' => '_blank']);

echo $html;
