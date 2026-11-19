<?php

// Functions commonly used in admin pages

function getAdminSideBarLinks()
{
$links = <<<end
<a href="/admin/exchange">Exchanges</a>&nbsp;
<a href="/admin/botnets">Botnets</a>&nbsp;
<a href="/admin/user">Users</a>&nbsp;
<a href="/admin/worker">Workers</a>&nbsp;
<a href="/admin/version">Version</a>&nbsp;
<a href="/admin/earning">Earnings</a>&nbsp;
<a href="/admin/payments">Payments</a>&nbsp;
<a href="/admin/monsters">Big Miners</a>&nbsp;
end;
	return $links;
}

// shared by wallet "tabs", to move in another php file...
function getAdminWalletLinks($coin, $info=NULL, $src='wallet')
{
	$html = CHtml::link("<b>COIN PROPERTIES</b>", '/admin/coinupdate?id='.$coin->id);
	if($info) {
		$html .= ' || '.$coin->createExplorerLink("<b>EXPLORER</b>");
		$html .= ' || '.CHtml::link("<b>PEERS</b>", '/admin/coinpeers?id='.$coin->id);
		if (YAAMP_ADMIN_WEBCONSOLE)
			$html .= ' || '.CHtml::link("<b>CONSOLE</b>", '/admin/coinconsole?id='.$coin->id);
		$html .= ' || '.CHtml::link("<b>TRIGGERS</b>", '/admin/cointriggers?id='.$coin->id);
		if ($src != 'wallet')
			$html .= ' || '.CHtml::link("<b>{$coin->symbol}</b>", '/admin/coin?id='.$coin->id);
	}

	if(!$info && $coin->enable)
		$html .= '<br/>'.CHtml::link("<b>STOP COIND</b>", '/admin/stopcoin?id='.$coin->id);

	if($coin->auto_ready)
		$html .= '<br/>'.CHtml::link("<b>UNSET AUTO</b>", '/admin/coinunsetauto?id='.$coin->id);
	else
		$html .= '<br/>'.CHtml::link("<b>SET AUTO</b>", '/admin/coinsetauto?id='.$coin->id);

	$html .= '<br/>';

	if(!empty($coin->link_bitcointalk))
		$html .= CHtml::link('forum', $coin->link_bitcointalk, array('target'=>'_blank')).' ';

	if(!empty($coin->link_github))
		$html .= CHtml::link('git', $coin->link_github, array('target'=>'_blank')).' ';

	if(!empty($coin->link_site))
		$html .= CHtml::link('site', $coin->link_site, array('target'=>'_blank')).' ';

	if(!empty($coin->link_explorer))
		$html .= CHtml::link('chain', $coin->link_explorer, array('target'=>'_blank','title'=>'External Blockchain Explorer')).' ';

	$html .= CHtml::link('google', 'http://google.com/search?q='.urlencode($coin->name.' '.$coin->symbol.' bitcointalk'), array('target'=>'_blank'));

	return $html;
}

/////////////////////////////////////////////////////////////////////////////////////////////

// Check if $IP is in $CIDR range
function ipCIDRCheck($IP, $CIDR)
{
	list($net, $mask) = explode('/', $CIDR);

	$ip_net = ip2long($net);
	$ip_mask = ~((1 << (32 - $mask)) - 1);

	$ip_ip = ip2long($IP);
	$ip_ip_net = $ip_ip & $ip_mask;

	return ($ip_ip_net === $ip_net);
}

function isAdminIP($ip)
{
	// Behind a local trusted reverse proxy REMOTE_ADDR is loopback and the
	// real client is in X-Real-IP. Only trust the header in that case;
	// X-Forwarded-For is deliberately ignored because it is spoofable.
	if (!empty($_SERVER['HTTP_X_REAL_IP'])
		&& filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)
		&& in_array(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '', array('127.0.0.1', '::1'), true))
		$ip = $_SERVER['HTTP_X_REAL_IP'];

	foreach(explode(',', YAAMP_ADMIN_IP) as $range)
	{
		if (strpos($range, '/')) {
			if(ipCIDRCheck($ip, $range) === true) return true;
		} else if ($range === $ip) {
			return true;
		}
	}
	return false;
}

/////////////////////////////////////////////////////////////////////////////////////////////
