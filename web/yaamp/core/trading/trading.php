<?php
require_once('poloniex_trading.php');
require_once('binance_trading.php');
require_once('exbitron_trading.php');
require_once('nestex_trading.php');
require_once('kraken_trading.php');
require_once('yobit_trading.php');
require_once('hitbtc_trading.php');
require_once('kucoin_trading.php');
require_once('nonkyc_trading.php');

function cancelExchangeOrder($order=false)
{
	if ($order)
		switch ($order->market)
		{
			case 'yobit':
				doYobitCancelOrder($order->uuid);
				break;
			case 'binance':
				doBinanceCancelOrder($order->uuid);
				break;
			case 'hitbtc':
				doHitBTCCancelOrder($order->uuid);
				break;
			case 'kucoin':
				doKuCoinCancelOrder($order->uuid);
				break;
			case 'exbitron':
				doExbitronCancelOrder($order->uuid);
				break;
			case 'nestex':
				doNestexCancelOrder($order->uuid);
				break;
			case 'nonkyc':
				doNonkycCancelOrder($order->uuid);
				break;
		}
}

function runExchange($exchangeName=false)
{
	if (!empty($exchangeName))
	{
		switch($exchangeName)
		{
			case 'binance':
				doBinanceTrading(true);
				updateBinanceMarkets();
				break;

			case 'bitstamp':
				getBitstampBalances();
				break;

			case 'cexio':
				getCexIoBalances();
				break;

			case 'exbitron':
				doExbitronTrading(true);
				updateExbitronMarkets();
				break;
			
			case 'nestex':
				doNestexTrading(true);
				updateNestexMarkets();
				break;
				
			case 'yobit':
				doYobitTrading(true);
				updateYobitMarkets();
				break;

			case 'hitbtc':
				doHitBTCTrading(true);
				updateHitBTCMarkets();
				break;

			case 'kraken':
				doKrakenTrading(true);
				updateKrakenMarkets();
				break;

			case 'kucoin':
				doKuCoinTrading(true);
				updateKucoinMarkets();
				break;

			case 'poloniex':
				doPoloniexTrading(true);
				updatePoloniexMarkets();
				break;

			case 'nonkyc':
				doNonkycTrading(true);
				updateNonkycMarkets();
				break;

			default:
				debuglog(__FUNCTION__.' '.$exchangeName.' not implemented');
		}
	}
}
