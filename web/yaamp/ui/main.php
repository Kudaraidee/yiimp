<?php

require('misc.php');
echo <<<END

<!doctype html>
<!--[if IE 7 ]>		 <html class="no-js ie ie7 lte7 lte8 lte9" lang="en-US"> <![endif]-->
<!--[if IE 8 ]>		 <html class="no-js ie ie8 lte8 lte9" lang="en-US"> <![endif]-->
<!--[if IE 9 ]>		 <html class="no-js ie ie9 lte9>" lang="en-US"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html class="no-js" lang="en-US"> <!--<![endif]-->

<head>
<script src="https://web.cmp.usercentrics.eu/modules/autoblocker.js"></script>
<script id="usercentrics-cmp" src="https://web.cmp.usercentrics.eu/ui/loader.js" data-settings-id="uhyxayek5s3Wwn" async></script>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-5JW693BKTS"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-5JW693BKTS');
</script>
<meta charset="utf-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

<meta name="description" content="Your Pool Name - Cryptocurrency Mining Pool">
<meta name="keywords" content="mining,pool,altcoin,sha256">
<meta name="robots" content="index, follow">
<meta name="googlebot" content="index, follow">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

END;

$pageTitle = empty($this->pageTitle) ? YAAMP_SITE_NAME : YAAMP_SITE_NAME." - ".$this->pageTitle;

echo '<title>'.$pageTitle.'</title>';

echo CHtml::cssFile("/extensions/jquery/themes/ui-lightness/jquery-ui.css");
echo CHtml::cssFile('/yaamp/ui/css/main.css');
echo CHtml::cssFile('/yaamp/ui/css/table.css');

//echo CHtml::scriptFile('/extensions/jquery/js/jquery-1.8.3-dev.js');
//echo CHtml::scriptFile('/extensions/jquery/js/jquery-ui-1.9.1.custom.min.js');

$cs = app()->getClientScript();
// Ensure jQuery loads in HEAD before any inline scripts
$cs->registerCoreScript('jquery');
$cs->registerCoreScript('jquery.ui');
//$cs->registerScriptFile('/yaamp/ui/js/jquery.tablesorter.js', CClientScript::POS_END);

echo CHtml::scriptFile('/yaamp/ui/js/jquery.tablesorter.js');

// if(!controller()->admin)
// echo <<<end
// <script>
// (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
// (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
// m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
// })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

// ga('create', 'UA-58136019-1', 'auto');
// ga('send', 'pageview');

// $(document).ajaxSuccess(function(){ga('send', 'pageview');});

// </script>
// end;

echo "</head>";

///////////////////////////////////////////////////////////////

echo '<body class="page">';
echo '<a href="/site/mainbtc" style="display: none;">main</a>';

showPageHeader();
showPageContent($content);
showPageFooter();

echo "</body></html>";
return;

/////////////////////////////////////////////////////////////////////

function showItemHeader($selected, $url, $name)
{
	if($selected) $selected_text = "class='selected'";
	else $selected_text = '';

	echo "<span><a $selected_text href='$url'>$name</a></span>";
	echo "&nbsp;";
}

function showPageHeader()
{
	echo '<div class="tabmenu-out">';
	echo '<div class="tabmenu-inner">';

	echo '&nbsp;&nbsp;<a href="/">'.YAAMP_SITE_NAME.'</a>';

	$action = controller()->action->id;
	$wallet = user()->getState('yaamp-wallet');
	$ad = isset($_GET['address']);

	showItemHeader(controller()->id=='site' && $action=='index' && !$ad, '/', 'Home');
	showItemHeader($action=='mining', '/site/mining', 'Pool');
	showItemHeader(controller()->id=='site'&&($action=='index' || $action=='wallet') && $ad, "/?address=$wallet", 'Wallet');
	showItemHeader(controller()->id=='stats', '/stats', 'Graphs');
	showItemHeader($action=='miners', '/site/miners', 'Miners');
	showItemHeader(controller()->id=='api', '/site/api', 'API');
	showItemHeader($action=='privacypolicy', '/site/privacypolicy', 'Privacy Policy');
	if (YIIMP_PUBLIC_EXPLORER)
		showItemHeader(controller()->id=='explorer', '/explorer', 'Explorers');

	if (YIIMP_PUBLIC_BENCHMARK)
		showItemHeader(controller()->id=='bench', '/bench', 'Benchs');

	if (YAAMP_RENTAL)
		showItemHeader(controller()->id=='renting', '/renting', 'Rental');

	if (YIIMP_ADMIN_LOGIN) {
		if(controller()->admin)
		{
			if (isAdminIP($_SERVER['REMOTE_ADDR']) === false)
				debuglog("admin {$_SERVER['REMOTE_ADDR']}");

			showItemHeader(controller()->id=='coin', '/coin', 'Coins');
			showItemHeader($action=='common', '/admin/dashboard', 'Dashboard');
			showItemHeader(controller()->id=='admin'&&$action=='coinwallets', "/admin/coinwallets", 'Wallets');

			if (YAAMP_RENTAL)
				showItemHeader(controller()->id=='renting' && $action=='admin', '/renting/admin', 'Jobs');

			if (YAAMP_ALLOW_EXCHANGE)
				showItemHeader(controller()->id=='trading', '/trading', 'Trading');

			if (YAAMP_USE_NICEHASH_API)
				showItemHeader(controller()->id=='nicehash', '/nicehash', 'Nicehash');

			showItemHeader(controller()->id=='logout', '/admin/logout', 'Logout');
		}
		else {
			showItemHeader(controller()->id=='login', '/admin/login', 'Login');
		}
	}

	echo '<span style="float: right;">';

	$mining = getdbosql('db_mining');
	$nextpayment = date('H:i T', $mining->last_payout+YAAMP_PAYMENTS_FREQ);
	$eta = ($mining->last_payout+YAAMP_PAYMENTS_FREQ) - time();
	$eta_mn = 'in '.round($eta / 60).' minutes';

	echo '<span id="nextpayout" style="font-size: .8em;" title="'.$eta_mn.'">Next Payout: '.$nextpayment.'</span>';

	echo "</div>";
	echo "</div>";
}

function showPageFooter()
{
	echo '<div class="footer">';
	$year = date("Y", time());

	echo "<p>&copy; $year ".YAAMP_SITE_NAME.' - '.
		'<a href="https://github.com/your-repo/yiimp">Powered by Yiimp</a></p>';
	echo '</div><!-- footer -->';
}
