<?php

/** @var yii\web\View $this */

use app\models\Coins;
use app\models\Stratums;
use app\components\CspHelper;

$homeUrl = Yii::$app->homeUrl;

// Register homepage functions via external JS file
$this->registerJsFile('@web/js/homepage-functions.js', ['depends' => [yii\web\JqueryAsset::className()]]);

$this->registerJsFile('@web/js/auto_refresh.js', ['depends' => [yii\web\JqueryAsset::className()]]);
$this->registerJsFile('@web/js/bookmarks.js', ['depends' => [yii\web\JqueryAsset::className()]]);
$this->registerJsFile('@web/js/bookmark-autoload.js', ['depends' => [yii\web\JqueryAsset::className()]]);

$height = '240px';

$min_payout = floatval(YAAMP_PAYMENTS_MINI);
$min_sunday = $min_payout / 10;

$payout_freq = (YAAMP_PAYMENTS_FREQ / 3600) . " hours";
?>

<div id='resume_update_button' class='resume-update-button'>
	<b>Auto refresh is paused - Click to resume</b>
</div>

<table cellspacing=20 width=100%>
<tr><td valign=top width=50%>

<!--  -->

<div class="main-left-box">
<div class="main-left-title"><?=YAAMP_SITE_URL?></div>
<div class="main-left-inner">

<ul>

<li>Welcome to <?=YAAMP_SITE_URL?>! </li>
<li>This fork was based on the yaamp source code and is now an open source project.</li>
<li>No registration is required, we do payouts in the currency you mine. Use your wallet address as the username.</li>
<li>Payouts are made automatically every <?= $payout_freq ?> for all balances above <b><?= $min_payout ?></b>, or <b><?= $min_sunday ?></b> on Sunday.</li>
<li>For some coins, there is an initial delay before the first payout, please wait at least 6 hours before asking for support.</li>
<li>Blocks are distributed proportionally among valid submitted shares.</li>

<br/>

</ul>
</div></div>
<br/>

<!-- Stratum Auto generation code, will automatically add coins when they are enabled and auto ready -->

<div class="main-left-box">
<div class="main-left-title">How to mine with <?=YAAMP_SITE_URL?></div>
<div class="main-left-inner">

<table>
	<thead>
		<tr>
			<th>Stratum Location</th>
			<th>Choose Coin</th>
			<th>Your Wallet Address</th>
			<th>Rig (opt.)</th>
			<th>Type</th>
		</tr>
	</thead>

<tbody>
	<tr>
		<td>
			<select id="drop-stratum" class="code-block">

			<!-- Add your stratum locations here -->
			<option value="">Main Stratum</option>
			<!--<option value="mine.">Asia Stratum</option>
			<option value="eu.">Europe Stratum</option>
			<option value="cad.">CAD Stratum</option>
			<option value="uk.">UK Stratum</option> -->
			</select>
		</td>

		<td>
			<select id="drop-coin" class="code-block">
       <?php
$list = Coins::find()
            ->where(['enable' => 1, 'visible' => 1, 'auto_ready' => 1])
            ->orderBy(['algo' => SORT_ASC])
            ->all();

if (!$list) {
    echo "<option disabled>No Coins Available</option>";
} else {
    $algoheading = "";
    $count = 0;

    foreach ($list as $coin) {
        $name = substr($coin->name, 0, 18);
        $symbol = $coin->getOfficialSymbol();
        $algo = $coin->algo;
        $auto_exchange = isset($coin->auto_exchange) ? $coin->auto_exchange : 1; // Default to 1 if null

        $port_db = Stratums::find()
                ->where(['symbol' => $symbol, 'algo' => $algo])
                ->one();

        $port = $port_db ? $port_db->port : '0000';

        // Add algorithm headings correctly
        if ($count == 0 || $algo != $algoheading) {
            echo "<option disabled='disabled'>$algo</option>";
        }

        // Append mc=SYMBOL only if auto_exchange is 0
        $mc_param = ($auto_exchange == 0) ? ",mc=$symbol" : "";

        echo "<option value='$symbol' data-port='$port' data-algo='-a $algo' data-symbol='$symbol' data-extra='-p c=$symbol$mc_param'>$name ($symbol)</option>";

        $count++;
        $algoheading = $algo;
    }
}
?>

			</select>
		</td>
		<td>
			<input id="text-wallet" type="text" size="30" placeholder="RF9D1R3Vt7CECzvb1SawieUC9cYmAY1qoj" class="code-block">
		</td>
		<td>
			<input id="text-rig-name" type="text" size="10" placeholder="001" class="code-block">
		</td>
		<td>
			<select id="drop-solo" class="code-block">
			<option value="">Shared</option>
			<option value=",m=solo">Solo</option>
			</select>
		</td>
	
</tbody>
<tbody>
	<tr>
		<td colspan="5"><p class="main-left-box" class="code-highlight" id="output">-a  -o stratum+tcp://<?=YAAMP_STRATUM_URL?>:0000 -u . -p c=</p></td>
	</tr>
</tbody>
</table>

<ul>
<li>&lt;WALLET_ADDRESS&gt; must be valid for the currency you mine. <b>DO NOT USE a BTC address here, the auto exchange is disabled on these stratums</b>!</li>
<!-- <li><b>Our stratums are now NiceHASH compatible and ASICBoost enabled, please message support if you have any issues.</b></li> -->
<li>See the "<?=YAAMP_SITE_NAME?> coins" area on the right for PORT numbers. You may mine any coin regardless if the coin is enabled or not for autoexchange. Payouts will only be made in that coins currency.</li>
<li>Payouts are made automatically every hour for all balances above <b><?=$min_payout?></b>, or <b><?=$min_sunday?></b> on Sunday.</li>
<br>
</ul>
</div></div><br>

<!-- End new stratum generation code  -->

<div class="main-left-box">
<div class="main-left-title"><?=YAAMP_SITE_URL?> Links</div>
<div class="main-left-inner">

<ul>

<li><b>API</b> - <a href='<?php echo $homeUrl ?>site/api'>http://<?=YAAMP_SITE_URL?>/site/api</a></li>
<li><b>Difficulty</b> - <a href='<?php echo $homeUrl ?>site/diff'>http://<?=YAAMP_SITE_URL?>/site/diff</a></li>
<?php
if (YIIMP_PUBLIC_BENCHMARK):
?>
<li><b>Benchmarks</b> - <a href='<?php echo $homeUrl ?>site/benchmarks'>http://<?=YAAMP_SITE_URL?>/site/benchmarks</a></li>
<?php
endif;
?>

<?php
if (YAAMP_ALLOW_EXCHANGE):
?>
<li><b>Algo Switching</b> - <a href='<?php echo $homeUrl ?>site/multialgo'>http://<?=YAAMP_SITE_URL?>/site/multialgo</a></li>
<?php
endif;
?>

<br>

</ul>
</div></div><br>

<div class="main-left-box">
<div class="main-left-title"><?=YAAMP_SITE_URL?> Support</div>
<div class="main-left-inner">

<ul class="social-icons">
<!--    <li><a href="http://www.facebook.com"><img src='/images/Facebook.png' /></a></li>
    <li><a href="http://www.twitter.com"><img src='/images/Twitter.png' /></a></li>
    <li><a href="http://www.youtube.com"><img src='/images/YouTube.png' /></a></li>
    <li><a href="http://www.github.com"><img src='/images/Github.png' /></a></li> -->
    <li><a href="https://discord.gg/DrsrWQh3qC"><img src='/images/discord.png' /></a></li>
</ul>

</div></div><br>
</td><td valign=top>
<!--  -->

<div id='pool_current_results'>
<br><br><br><br><br><br><br><br><br><br>
</div>

<div id='pool_history_results'>
<br><br><br><br><br><br><br><br><br><br>
</div>

<div id='pool_coins_info'>
<br><br><br><br><br><br><br><br><br><br>
</div>

</td></tr></table>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<?= CspHelper::beginScript() ?>
// Event listeners for form elements
document.addEventListener('DOMContentLoaded', function() {
    var resumeBtn = document.getElementById('resume_update_button');
    if (resumeBtn) {
        resumeBtn.addEventListener('click', function() {
            auto_page_resume();
        });
    }
    
    var dropStratum = document.getElementById('drop-stratum');
    if (dropStratum) {
        dropStratum.addEventListener('change', generate);
    }
    
    var dropCoin = document.getElementById('drop-coin');
    if (dropCoin) {
        dropCoin.addEventListener('change', generate);
    }
    
    var textWallet = document.getElementById('text-wallet');
    if (textWallet) {
        textWallet.addEventListener('keyup', generate);
    }
    
    var textRigName = document.getElementById('text-rig-name');
    if (textRigName) {
        textRigName.addEventListener('keyup', generate);
    }
    
    var dropSolo = document.getElementById('drop-solo');
    if (dropSolo) {
        dropSolo.addEventListener('change', generate);
    }
});

function getLastUpdated(){
    var stratum = document.getElementById('drop-stratum');
    var coin = document.getElementById('drop-coin');
    var solo = document.getElementById('drop-solo');
    var wallet = document.getElementById('text-wallet').value.trim();
    var rigName = document.getElementById('text-rig-name').value.trim();
    var result = '';

    // Check if coin dropdown has valid selection with dataset
    var selectedOption = coin.options[coin.selectedIndex];
    if (!selectedOption || !selectedOption.dataset || !selectedOption.dataset.algo) {
        return '-a ALGO -o stratum+tcp://<?=YAAMP_STRATUM_URL?>:PORT -u WALLET_ADDRESS.WORKER_NAME -p c=COIN';
    }

    var algo = selectedOption.dataset.algo;
    var port = selectedOption.dataset.port;
    var symbol = selectedOption.dataset.symbol;
    var extra = selectedOption.dataset.extra; // Already contains "-p c=MTBC,mc=MTBC" if needed

    result += algo + ' -o stratum+tcp://';
    result += stratum.value + '<?=YAAMP_STRATUM_URL?>:' + port + ' -u ';

    result += wallet ? wallet : 'WALLET_ADDRESS';
    result += rigName ? '.' + rigName : '.WORKER_NAME';

    result += ' ' + extra; // Removed second "-p"
    result += solo.value;  // Append solo mining option if selected

    return result;
}

function generate(){
    var result = getLastUpdated();
    document.getElementById('output').innerHTML = result;
}
generate();
<?= CspHelper::endScript() ?>
