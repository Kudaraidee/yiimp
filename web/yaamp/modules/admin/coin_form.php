<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Register Bootstrap 5 and other required assets
Yii::app()->clientScript->registerCssFile('https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css');
Yii::app()->clientScript->registerScriptFile('https://code.jquery.com/jquery-3.6.0.min.js', CClientScript::POS_HEAD);
Yii::app()->clientScript->registerScriptFile('https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js', CClientScript::POS_END);

// Add custom styles
echo <<<EOT
<style type="text/css">
.container { max-width: 1200px; margin: 20px auto; }
.form-group { margin-bottom: 1rem; }
.form-hint { color: #666; font-size: 0.875rem; margin-top: 0.25rem; }
[readonly] { background-color: #e9ecef; }
.nav-tabs { margin-bottom: 20px; border-bottom: 1px solid #dee2e6; }
.nav-tabs .nav-link { color: #495057; }
.nav-tabs .nav-link.active { color: #007bff; border-color: #dee2e6 #dee2e6 #fff; }
.tab-content { padding: 20px 0; }
.btn-primary { margin-top: 20px; }
.form-control { max-width: 100%; }
.form-check-input { margin-top: 0.3rem; }
</style>
EOT;

echo '<div class="container">';
echo getAdminSideBarLinks();

if (!isset($coin)) {
    echo '<div class="alert alert-danger">Error: Coin object not initialized</div>';
    return;
}

if (!is_null($coin->id)) {
    echo '<h2>Edit Coin: ' . CHtml::link($coin->name, '/admin/coin?id='.$coin->id) . '</h2>';
} else {
    echo '<h2>Create New Coin</h2>';
}

$this->widget('UniForm');

echo CUFHtml::beginForm('', 'post', array('class'=>'form'));
echo CUFHtml::errorSummary($coin, '', '', array('class'=>'alert alert-danger'));

// Replace the existing tabs HTML with Bootstrap tabs
echo <<<EOT
<ul class="nav nav-tabs" id="coinTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="general-tab" data-bs-toggle="tab" href="#tabs-1" role="tab">General</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="settings-tab" data-bs-toggle="tab" href="#tabs-2" role="tab">Settings</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="exchange-tab" data-bs-toggle="tab" href="#tabs-3" role="tab">Exchange</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="daemon-tab" data-bs-toggle="tab" href="#tabs-4" role="tab">Daemon</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="links-tab" data-bs-toggle="tab" href="#tabs-5" role="tab">Links</a>
    </li>
</ul>
<div class="tab-content" id="coinTabsContent">
EOT;

echo '<div class="tab-pane fade show active" id="tabs-1" role="tabpanel">';

echo CUFHtml::openActiveCtrlHolder($coin, 'name');
echo CUFHtml::activeLabelEx($coin, 'name');
echo CUFHtml::activeTextField($coin, 'name', array('maxlength'=>200));
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'symbol');
echo CUFHtml::activeLabelEx($coin, 'symbol');
echo CUFHtml::activeTextField($coin, 'symbol', array('maxlength'=>200,'style'=>'width: 120px;'));
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'symbol2');
echo CUFHtml::activeLabelEx($coin, 'symbol2');
echo CUFHtml::activeTextField($coin, 'symbol2', array('maxlength'=>200,'style'=>'width: 120px;'));
echo '<p class="formHint2">Set it if symbol is different</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'algo');
echo CUFHtml::activeLabelEx($coin, 'algo');
$ListAlgos = array();
$db_algos = getdbolist('db_algos');
foreach ($db_algos as $algo) {
	$NameAlgo = $algo->name;
	$ListAlgos[$NameAlgo] = $NameAlgo;
	
}
echo CUFHtml::dropDownList('db_coins[algo]', $coin->algo, $ListAlgos, array(
	'style' => 'border: 1px solid #dfdfdf; height: 26px; width:135px',
	'class' => 'textInput tweetnews-input'
));
$coin_algo = ($coin->algo)? '<span style="color: green;">'.$coin->algo.'</span>' : '<span style="color: red;">None</span>';
echo '<label style="padding-left: 20px;" for="algo">Algo Selected: '.$coin_algo.'</label>';
echo '<p class="formHint2">Required all lower case</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'auto_exchange');
echo CUFHtml::activeLabelEx($coin, 'auto_exchange');
echo CUFHtml::activeCheckBox($coin, 'auto_exchange');
echo '<p class="formHint2">include in automatic miningselection</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'image');
echo CUFHtml::activeLabelEx($coin, 'image');
echo CUFHtml::activeTextField($coin, 'image', array('maxlength'=>200));
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'version_installed');
echo CUFHtml::activeLabelEx($coin, 'version_installed');
echo CUFHtml::activeTextField($coin, 'version_installed', array('maxlength'=>64,'style'=>'width: 120px;'));
echo '<p class="formHint2">walletversion installed</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'version_github');
echo CUFHtml::activeLabelEx($coin, 'version_github');
echo CUFHtml::activeTextField($coin, 'version_github', array('maxlength'=>200,'style'=>'width: 100px;','readonly'=>'readonly'));
echo '<p class="formHint2">walletversion on GitHub</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'payout_min');
echo CUFHtml::activeLabelEx($coin, 'payout_min');
echo CUFHtml::activeTextField($coin, 'payout_min', array('maxlength'=>200,'style'=>'width: 120px;'));
echo '<p class="formHint2">Pay users when they reach this amount</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'payout_max');
echo CUFHtml::activeLabelEx($coin, 'payout_max');
echo CUFHtml::activeTextField($coin, 'payout_max', array('maxlength'=>200,'style'=>'width: 120px;'));
echo '<p class="formHint2">Maximum transaction amount</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'txfee');
echo CUFHtml::activeLabelEx($coin, 'txfee');
echo CUFHtml::activeTextField($coin, 'txfee', array('maxlength'=>200,'style'=>'width: 100px;','readonly'=>'readonly'));
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'block_height');
echo CUFHtml::activeLabelEx($coin, 'block_height');
echo CUFHtml::activeTextField($coin, 'block_height', array('readonly'=>'readonly','style'=>'width: 120px;'));
echo '<p class="formHint2">Current height</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'target_height');
echo CUFHtml::activeLabelEx($coin, 'target_height');
echo CUFHtml::activeTextField($coin, 'target_height', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">Known height of the network</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'powend_height');
echo CUFHtml::activeLabelEx($coin, 'powend_height');
echo CUFHtml::activeTextField($coin, 'powend_height', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">Height of the end of PoW mining</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'mature_blocks');
echo CUFHtml::activeLabelEx($coin, 'mature_blocks');
echo CUFHtml::activeTextField($coin, 'mature_blocks', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">Required block count to mature</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'powlimit_bits');
echo CUFHtml::activeLabelEx($coin, 'powlimit_bits');
echo CUFHtml::activeTextField($coin, 'powlimit_bits', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">number of leading \'0\' bits on powlimit (basehash for diff 1)</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'block_time');
echo CUFHtml::activeLabelEx($coin, 'block_time');
echo CUFHtml::activeTextField($coin, 'block_time', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">Average block time (sec)</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'errors');
echo CUFHtml::activeLabelEx($coin, 'errors');
echo CUFHtml::activeTextField($coin, 'errors', array('maxlength'=>200,'readonly'=>'readonly','style'=>'width: 600px;'));
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'specifications');
echo CUFHtml::activeLabelEx($coin, 'specifications');
echo CUFHtml::activeTextArea($coin, 'specifications', array('maxlength'=>2048,'lines'=>5,'class'=>'tweetnews-input','style'=>'width: 600px;'));
echo CUFHtml::closeCtrlHolder();

echo '</div>';

//////////////////////////////////////////////////////////////////////////////////////////

echo '<div class="tab-pane fade" id="tabs-2" role="tabpanel">';

echo CUFHtml::openActiveCtrlHolder($coin, 'enable');
echo CUFHtml::activeLabelEx($coin, 'enable');
echo CUFHtml::activeCheckBox($coin, 'enable');
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'auto_ready');
echo CUFHtml::activeLabelEx($coin, 'auto_ready');
echo CUFHtml::activeCheckBox($coin, 'auto_ready');
echo '<p class="formHint2">Allowed to mine</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'visible');
echo CUFHtml::activeLabelEx($coin, 'visible');
echo CUFHtml::activeCheckBox($coin, 'visible');
echo '<p class="formHint2">Visibility for the public</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'installed');
echo CUFHtml::activeLabelEx($coin, 'installed');
echo CUFHtml::activeCheckBox($coin, 'installed');
echo '<p class="formHint2">Required to be visible in the Wallets board</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'no_explorer');
echo CUFHtml::activeLabelEx($coin, 'no_explorer');
echo CUFHtml::activeCheckBox($coin, 'no_explorer');
echo '<p class="formHint2">Disable block explorer for the public</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'watch');
echo CUFHtml::activeLabelEx($coin, 'watch');
echo CUFHtml::activeCheckBox($coin, 'watch');
echo '<p class="formHint2">Track balance and markets history</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'auxpow');
echo CUFHtml::activeLabelEx($coin, 'auxpow');
echo CUFHtml::activeCheckBox($coin, 'auxpow');
echo '<p class="formHint2">Merged mining</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'enable_rpcdebug');
echo CUFHtml::activeLabelEx($coin, 'enable_rpcdebug');
echo CUFHtml::activeCheckBox($coin, 'enable_rpcdebug');
echo '<p class="formHint2">enable debug of rpc-communication from stratum to wallet</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'personalization');
echo CUFHtml::activeLabelEx($coin, 'personalization');
echo CUFHtml::activeTextField($coin, 'personalization', array('maxlength'=>100));
echo '<p class="formHint2">personalization-string for equihash-coins<br>default "ZcashPoW" (see src/crypto/equihash.cpp for value)</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'max_miners');
echo CUFHtml::activeLabelEx($coin, 'max_miners');
echo CUFHtml::activeTextField($coin, 'max_miners', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">Miners allowed by the stratum</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'max_shares');
echo CUFHtml::activeLabelEx($coin, 'max_shares');
echo CUFHtml::activeTextField($coin, 'max_shares', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">Auto restart stratum after this amount of shares</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'master_wallet');
echo CUFHtml::activeLabelEx($coin, 'master_wallet');
echo CUFHtml::activeTextField($coin, 'master_wallet', array('maxlength'=>200));
echo '<p class="formHint2">The pool wallet address</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'wallet_zaddress');
echo CUFHtml::activeLabelEx($coin, 'wallet_zaddress');
echo CUFHtml::activeTextField($coin, 'wallet_zaddress', array('maxlength'=>200));
echo '<p class="formHint2">zaddress for privacy-coins (zcash)</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'reward');
echo CUFHtml::activeLabelEx($coin, 'reward');
echo CUFHtml::activeTextField($coin, 'reward', array('maxlength'=>200,'readonly'=>'readonly','style'=>'width: 120px;'));
echo '<p class="formHint2">PoW block value</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'reward_mul');
echo CUFHtml::activeLabelEx($coin, 'reward_mul');
echo CUFHtml::activeTextField($coin, 'reward_mul', array('maxlength'=>200,'style'=>'width: 120px;'));
echo '<p class="formHint2">Adjust the block reward if incorrect</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'charity_percent');
echo CUFHtml::activeLabelEx($coin, 'charity_percent');
echo CUFHtml::activeTextField($coin, 'charity_percent', array('maxlength'=>10,'style'=>'width: 30px;'));
echo '<p class="formHint2">Reward for foundation or dev fees, generally between 1 and 10 %</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'charity_address');
echo CUFHtml::activeLabelEx($coin, 'charity_address');
echo CUFHtml::activeTextField($coin, 'charity_address', array('maxlength'=>200));
echo '<p class="formHint2">Foundation address if "dev fees" are required</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'hasgetinfo');
echo CUFHtml::activeLabelEx($coin, 'hasgetinfo');
echo CUFHtml::activeCheckBox($coin, 'hasgetinfo');
echo '<p class="formHint2">Enable if getinfo rpc method is present</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'hassubmitblock');
echo CUFHtml::activeLabelEx($coin, 'hassubmitblock');
echo CUFHtml::activeCheckBox($coin, 'hassubmitblock');
echo '<p class="formHint2">Enable if submitblock method is present</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'txmessage');
echo CUFHtml::activeLabelEx($coin, 'txmessage');
echo CUFHtml::activeCheckBox($coin, 'txmessage');
echo '<p class="formHint2">Block template with a tx message</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'hasmasternodes');
echo CUFHtml::activeLabelEx($coin, 'hasmasternodes');
echo CUFHtml::activeCheckBox($coin, 'hasmasternodes');
echo '<p class="formHint2">Require "payee" and "payee_amount", or masternode object in getblocktemplate</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'usesegwit');
echo CUFHtml::activeLabelEx($coin, 'usesegwit');
echo CUFHtml::activeCheckBox($coin, 'usesegwit');
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'usemweb');
echo CUFHtml::activeLabelEx($coin, 'usemweb');
echo CUFHtml::activeCheckBox($coin, 'usemweb');
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

echo '</div>';

//////////////////////////////////////////////////////////////////////////////////////////

echo '<div class="tab-pane fade" id="tabs-3" role="tabpanel">';

echo CUFHtml::openActiveCtrlHolder($coin, 'dontsell');
echo CUFHtml::activeLabelEx($coin, 'dontsell');
echo CUFHtml::activeCheckBox($coin, 'dontsell');
echo '<p class="formHint2">Disable auto send to exchange</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'sellonbid');
echo CUFHtml::activeLabelEx($coin, 'sellonbid');
echo CUFHtml::activeCheckBox($coin, 'sellonbid');
echo '<p class="formHint2">Reduce the sell price on exchanges</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'sellthreshold');
echo CUFHtml::activeLabelEx($coin, 'sellthreshold');
echo CUFHtml::activeTextField($coin, 'sellthreshold', array('maxlength'=>16,'style'=>'width: 120px;'));
echo '<p class="formHint2">min amount to sell</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'market');
echo CUFHtml::activeLabelEx($coin, 'market');
echo CUFHtml::activeTextField($coin, 'market', array('maxlength'=>128,'style'=>'width: 180px;'));
echo '<p class="formHint2">Selected exchange</p>';
echo CUFHtml::closeCtrlHolder();

if (empty($coin->price) || empty($coin->market) || $coin->market == 'unknown') {

	echo CUFHtml::openActiveCtrlHolder($coin, 'price');
	echo CUFHtml::activeLabelEx($coin, 'price');
	echo CUFHtml::activeTextField($coin, 'price', array('maxlength'=>16,'style'=>'width: 180px;'));
	echo '<p class="formHint2">Manually set the BTC price if missing</p>';
	echo CUFHtml::closeCtrlHolder();

}

//echo CUFHtml::openActiveCtrlHolder($coin, 'marketid');
//echo CUFHtml::activeLabelEx($coin, 'marketid');
//echo CUFHtml::activeTextField($coin, 'marketid', array('maxlength'=>20,'style'=>'width: 120px;'));
//echo "<p class='formHint2'>Required on cryptsy ?</p>";
//echo CUFHtml::closeCtrlHolder();

//echo CUFHtml::openActiveCtrlHolder($coin, 'deposit_address');
//echo CUFHtml::activeLabelEx($coin, 'deposit_address');
//echo CUFHtml::activeTextField($coin, 'deposit_address', array('maxlength'=>20));
//echo "<p class='formHint2'>For donations or exchange withdraws ?</p>";
//echo CUFHtml::closeCtrlHolder();

//echo CUFHtml::openActiveCtrlHolder($coin, 'deposit_minimum');
//echo CUFHtml::activeLabelEx($coin, 'deposit_minimum');
//echo CUFHtml::activeTextField($coin, 'deposit_minimum', array('maxlength'=>20,'style'=>'width: 120px;'));
//echo "<p class='formHint2'>Unused</p>";
//echo CUFHtml::closeCtrlHolder();

echo '</div>';

//////////////////////////////////////////////////////////////////////////////////////////

echo '<div class="tab-pane fade" id="tabs-4" role="tabpanel">';

echo CUFHtml::openActiveCtrlHolder($coin, 'program');
echo CUFHtml::activeLabelEx($coin, 'program');
echo CUFHtml::activeTextField($coin, 'program', array('maxlength'=>128,'style'=>'width: 180px;'));
echo '<p class="formHint2">Daemon process name</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'conf_folder');
echo CUFHtml::activeLabelEx($coin, 'conf_folder');
echo CUFHtml::activeTextField($coin, 'conf_folder', array('maxlength'=>128,'style'=>'width: 180px;'));
echo '<p class="formHint2">Generally close to the process name (.bitcoin)</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'rpchost');
echo CUFHtml::activeLabelEx($coin, 'rpchost');
echo CUFHtml::activeTextField($coin, 'rpchost', array('maxlength'=>128,'style'=>'width: 180px;'));
echo '<p class="formHint2">Daemon (Wallet) IP</p>';
echo CUFHtml::closeCtrlHolder();

if(empty($coin->rpcport))
	$coin->rpcport = $coin->id*10;

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcport');
echo CUFHtml::activeLabelEx($coin, 'rpcport');
echo CUFHtml::activeTextField($coin, 'rpcport', array('maxlength'=>5,'style'=>'width: 60px;'));
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcwallet');
echo CUFHtml::activeLabelEx($coin, 'rpcwallet');
echo CUFHtml::activeTextField($coin, 'rpcwallet', array('maxlength'=>64,'style'=>'width: 180px;'));
echo '<p class="formHint2">Wallet name for Bitcoin Core 0.17+ (e.g., "pool" for Briskcoin 3.0)</p>';
echo CUFHtml::closeCtrlHolder();

if(empty($coin->rpcuser))
	$coin->rpcuser = 'yiimprpc';

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcuser');
echo CUFHtml::activeLabelEx($coin, 'rpcuser');
echo CUFHtml::activeTextField($coin, 'rpcuser', array('maxlength'=>128,'style'=>'width: 180px;'));
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

// generate a random password
if(empty($coin->rpcpasswd))
	$coin->rpcpasswd = preg_replace("|[^\w]|m",'',base64_encode(pack("H*",md5("".time().YAAMP_SITE_URL))));

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcpasswd');
echo CUFHtml::activeLabelEx($coin, 'rpcpasswd');
echo CUFHtml::activeTextField($coin, 'rpcpasswd', array('maxlength'=>128));
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'serveruser');
echo CUFHtml::activeLabelEx($coin, 'serveruser');
echo CUFHtml::activeTextField($coin, 'serveruser', array('maxlength'=>35,'style'=>'width: 180px;'));
echo '<p class="formHint2">Daemon process username</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcencoding');
echo CUFHtml::activeLabelEx($coin, 'rpcencoding');
echo CUFHtml::activeTextField($coin, 'rpcencoding', array('maxlength'=>5,'style'=>'width: 60px;'));
echo '<p class="formHint2">POW/POS</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'dedicatedport');
echo CUFHtml::activeLabelEx($coin, 'dedicatedport');
echo CUFHtml::activeTextField($coin, 'dedicatedport', array(
    'maxlength' => 5,
    'style' => 'width: 60px;'
));
echo '<p class="formHint2">Run addport to get Port Number</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'rpccurl');
echo CUFHtml::activeLabelEx($coin, 'rpccurl');
echo CUFHtml::activeCheckBox($coin, 'rpccurl');
echo '<p class="formHint2">Force the stratum to use curl for RPC</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcssl');
echo CUFHtml::activeLabelEx($coin, 'rpcssl');
echo CUFHtml::activeCheckBox($coin, 'rpcssl');
echo '<p class="formHint2">Wallet RPC secured via SSL</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'rpccert');
echo CUFHtml::activeLabelEx($coin, 'rpccert');
echo CUFHtml::activeTextField($coin, 'rpccert');
echo "<p class='formHint2'>Certificat file for RPC via SSL</p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'account');
echo CUFHtml::activeLabelEx($coin, 'account');
echo CUFHtml::activeTextField($coin, 'account', array('maxlength'=>128,'style'=>'width: 180px;'));
echo '<p class="formHint2">Wallet account to use</p>';
echo CUFHtml::closeCtrlHolder();

if ($coin->id) {
	echo CHtml::tag("hr");
	echo "<b>Sample config</b>:";
	echo CHtml::opentag("pre");
	$port = getAlgoPort($coin->algo);
	$dedport = $coin->dedicatedport;
	echo "rpcuser={$coin->rpcuser}\n";
	echo "rpcpassword={$coin->rpcpasswd}\n";
	echo "rpcport={$coin->rpcport}\n";
	echo "rpcthreads=8\n";
	echo "rpcallowip=127.0.0.1\n";
	echo "# onlynet=ipv4\n";
	echo "maxconnections=12\n";
	echo "daemon=1\n";
	echo "gen=0\n";
	echo "\n";
	echo "alertnotify=echo %s | mail -s \"{$coin->name} alert!\" ".YAAMP_ADMIN_EMAIL."\n";
	if (empty($coin->dedicatedport))
        {
            echo "blocknotify=/var/stratum/blocknotify ".YAAMP_STRATUM_URL.":$port {$coin->id} %s\n";
        }
        else
        {
            echo "blocknotify=/var/stratum/blocknotify ".YAAMP_STRATUM_URL.":$dedport {$coin->id} %s\n";
        }
    echo " \n";
    echo CHtml::closetag("pre");

	echo CHtml::tag("hr");
	echo "<b>Miner command line</b>:";
	echo CHtml::opentag("pre");
	echo "-a {$coin->algo} ";
	if (empty($coin->dedicatedport))
        {
            echo "-o stratum+tcp://" . YAAMP_STRATUM_URL . ':' . $port . ' ';
        }
        else
        {
            echo "-o stratum+tcp://" . YAAMP_STRATUM_URL . ':' . $dedport . ' ';
        }echo "-u {$coin->master_wallet} ";
	echo "-p c={$coin->symbol} ";
	echo "\n";
	echo CHtml::closetag("pre");
}

echo '</div>';


//////////////////////////////////////////////////////////////////////////////////////////

echo '<div class="tab-pane fade" id="tabs-5" role="tabpanel">';

echo CUFHtml::openActiveCtrlHolder($coin, 'link_bitcointalk');
echo CUFHtml::activeLabelEx($coin, 'link_bitcointalk');
echo CUFHtml::activeTextField($coin, 'link_bitcointalk');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_github');
echo CUFHtml::activeLabelEx($coin, 'link_github');
echo CUFHtml::activeTextField($coin, 'link_github');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_site');
echo CUFHtml::activeLabelEx($coin, 'link_site');
echo CUFHtml::activeTextField($coin, 'link_site');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_exchange');
echo CUFHtml::activeLabelEx($coin, 'link_exchange');
echo CUFHtml::activeTextField($coin, 'link_exchange');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_explorer');
echo CUFHtml::activeLabelEx($coin, 'link_explorer');
echo CUFHtml::activeTextField($coin, 'link_explorer');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_twitter');
echo CUFHtml::activeLabelEx($coin, 'link_twitter');
echo CUFHtml::activeTextField($coin, 'link_twitter');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_discord');
echo CUFHtml::activeLabelEx($coin, 'link_discord');
echo CUFHtml::activeTextField($coin, 'link_discord');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_facebook');
echo CUFHtml::activeLabelEx($coin, 'link_facebook');
echo CUFHtml::activeTextField($coin, 'link_facebook');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo '</div>';


echo '</div>';

echo CUFHtml::closeTag('fieldset');
showSubmitButton($update? 'Save': 'Create');
echo CUFHtml::endForm();

Yii::app()->clientScript->registerScript('init-tabs', "
jQuery(document).ready(function($) {
    // Get active tab from URL hash or default to first tab
    let hash = window.location.hash;
    if (hash) {
        $('#coinTabs a[href=\"'+hash+'\"]').tab('show');
    }

    // Update URL hash when tab changes
    $('#coinTabs a').on('click', function (e) {
        e.preventDefault();
        $(this).tab('show');
        window.location.hash = $(this).attr('href');
    });
});
", CClientScript::POS_END);
?>



