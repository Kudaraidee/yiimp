<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\helpers\Html;

class ConversionUtils extends Component
{
	public function round_difficulty($diff)
	{
		// only keep 8/9 significant numbers
		$sigdigits = 8;
		return round($diff, (int) ceil(0 - log10($diff)) + $sigdigits);
	}

	public function target_to_diff($target)
	{
		if(!$target) return 0;

		$d = (double) 0x0000ffff00000000/$target;
		return $this->round_difficulty($d);
	}

	public function decode_compact($input)
	{
		$c = $this->htoi($input);

		$nShift = ($c >> 24) & 0xff;
		$d = 0x0000ffff / ($c & 0x00ffffff);

		while ($nShift < 29)
		{
			$d *= 256.0;
			$nShift++;
		}

		while ($nShift > 29)
		{
			$d /= 256.0;
			$nShift--;
		}

		$v = 0x0000ffff00000000/$d;
		return $v;
	}

	public function hash_to_difficulty($coin, $hash)
	{
		$target = (double) 0.;
		$bin = pack('H*', $hash);
		// direct 'P' (uint64) type requires PHP 5.6.3
		$bytes = unpack('C*', $bin);
		// 00000000660556fdc8eabd080369...;
		// zeros on the left, and we ignore the 2 first bytes (0000)
		$oft = 2 + 1; // $bytes keys start at offset 1
		for ($i=0; $i<8; $i++) {
			$target += $bytes[$oft+$i] << ((7-$i)*8);
		}

		return $this->target_to_diff($target);
	}

	public function htoi($s)
	{
		$val = 0.0;
		$x = 0;

		if($s[$x] == '0' && ($s[$x+1] == 'x' || $s[$x+1] == 'X'))
			$x += 2;

		while(isset($s[$x]))
		{
	//		debuglog("{$s[$x]}");

			if($s[$x] >= '0' && $s[$x] <='9')
				$val = $val * 16 + $s[$x] - '0';

			else if($s[$x]>='A' && $s[$x] <='F')
			{
				$val = $val * 16 + ord($s[$x]) - ord('A') + 10;
			}

			else if($s[$x]>='a' && $s[$x] <='f')
				$val = $val * 16 + ord($s[$x]) - ord('a') + 10;

			else
				return 0;

			$x++;
		}

		return $val;
	}

	/////////////////////////////////////////////////////////////////////////////////////////////

	public function GetMonthString($n)
	{
		$timestamp = mktime(0, 0, 0, $n, 1, 2005);
		return date("F", $timestamp);
	}

	public function bitcoinvaluetoa($v, $precision = 8)
	{
		$format_string = '%.'.$precision.'f';
		return sprintf($format_string, round($v, $precision, PHP_ROUND_HALF_DOWN));
	}

	public function mbitcoinvaluetoa($v)
	{
		return sprintf('%.5f', round($v, 5, PHP_ROUND_HALF_DOWN));
	}

	/** Format a coin amount as a BTC value, wrapping trailing zeros in a dim span. */
	public function valuetocell(mixed $amount): string
	{
		$html = $amount ? $this->bitcoinvaluetoa($amount) : '-';
		return preg_replace('/(0+)$/', '<span class="eov">$1</span>', $html);
	}

	public function altcoinvaluetoa($v)
	{
		return sprintf('%.6f', round($v, 6, PHP_ROUND_HALF_DOWN));
	}

	public function percentvaluetoa($v)
	{
		return sprintf('%.3f', round($v, 3, PHP_ROUND_HALF_DOWN));
	}

	public function timestampfromstr($str)
	{
		if (strpos("$str", ':')) {
			$dt = \DateTime::createFromFormat('Y-m-d H:i:s e', $str);
			if (is_object($dt))
				return $dt->getTimestamp();
		}
		return 0;
	}

	public function datetoa($d)
	{
		if (strpos($d, ':')) {
			$d = $this->timestampfromstr($d);
		}

		if(empty($d)) return '';

		$t = $this->wp_mktime($d);
		$e = time() - $t;

		$table = array(
						// limit         divider
			array('year',  60*60*24*365, 60*60*24*365),
			array('month', 60*60*24*60,  60*60*24*30),
			array('week',  60*60*24*14,  60*60*24*7),
			array('day',   60*60*24*2,   60*60*24),
			array('hour',  60*60*2,      60*60),
			array('min',   60*2,         60),
			array('sec',   0,            1),
		);

		foreach($table as $r)
		{
			if($e >= $r[1])
			{
				$res = floor($e/$r[2]) . " " . $r[0] . (($e/$r[2])>=2?"s":"");	//. " ago";
				break;
			}
		}

		return "<span title='$d'>$res</span>";
	}

	public function datetoa2($d)
	{
		if (strpos($d, ':')) {
			$d = $this->timestampfromstr($d);
		}

		if(empty($d)) return '';

		$table = array(
						// limit         divider
			array('y',	60*60*24*365, 60*60*24*365),
			array('mo',	60*60*24*60,  60*60*24*30),
			array('w',	60*60*24*14,  60*60*24*7),
			array('d',	60*60*24*2,   60*60*24),
			array('h',	60*60*2,      60*60),
			array('m',	90,           60),
			array('s',	0,            1),
		);

		$e = time() - $d;
		foreach($table as $r)
		{
			if($e >= $r[1])
			{
			//	$res = floor($e/$r[2]) . " " . $r[0] . (($e/$r[2])>=2?"s":""). " ago";
			//	$res = ''.floor($e/$r[2]) . " " . $r[0] . (($e/$r[2])>=2?"s":"");
				$res = ''.floor($e/$r[2]) . $r[0];
				break;
			}
		}

		$f = date('Y-m-d H:i:s', $d);
		if(empty($res)) $res = 'now';
		return "<span title='$f'>$res</span>";
	}

	public function sectoa($i)
	{
	//	if($i < (60*60))
	//		return sprintf("%d:%02d", $i%(60*60)/60, $i%60);
	//	else
			return sprintf("%d:%02d:%02d", $i/(60*60), $i%(60*60)/60, $i%60);
	}

	public function sectoa2($i)
	{
		$table = array(
						// limit         divider
			array('year',  60*60*24*365, 60*60*24*365),
			array('month', 60*60*24*60,  60*60*24*30),
			array('week',  60*60*24*14,  60*60*24*7),
			array('day',   60*60*24*2,   60*60*24),
			array('hour',  60*60*2,      60*60),
			array('min',   60*2,         60),
			array('sec',   0,            1),
		);

		$res = '';
		foreach($table as $r)
		{
		//	debuglog("testing {$r[0]}");
			if($i >= $r[1])
			{
				$res = floor($i/$r[2]) . " " . $r[0] . (($i/$r[2]) >= 2? "s": "");
				break;
			}
		}

		return $res;
	}

	/////////////////////////////////////////////////////////////////////////////////////////////

	public function Itoa($i)
	{
		$s = '';
		if($i >= 1024*1024*1024)
			$s = round(floatval($i)/1024/1024/1024, 1) ."G";
		else if($i >= 1024*1024)
			$s = round(floatval($i)/1024/1024, 1) ."M";
		else if($i >= 1024)
			$s = round(floatval($i)/1024, 1) ."K";
		else
			$s = round(floatval($i), 1);

		return $s;
	}

	public function Itoa2($i, $precision=1)
	{
		$s = '';
		if($i >= 1000*1000*1000*1000*1000)
			$s = round(floatval($i)/1000/1000/1000/1000/1000, $precision) ." P";
		else if($i >= 1000*1000*1000*1000)
			$s = round(floatval($i)/1000/1000/1000/1000, $precision) ." T";
		else if($i >= 1000*1000*1000)
			$s = round(floatval($i)/1000/1000/1000, $precision) ." G";
		else if($i >= 1000*1000)
			$s = round(floatval($i)/1000/1000, $precision) ." M";
		else if($i >= 1000)
			$s = round(floatval($i)/1000, $precision) ." k";
		else
			$s = round(floatval($i), $precision);

		return $s;
	}

	public function YesNo($b)
	{
		if($b) return 'Yes';
		else return '';
	}

	public function Booltoa($b)
	{
		if($b)
			return '<img src=/images/ui/green-check.png>';
	}

	public function precision($n, $p)
	{
		if($p < 0) return 0;

		$temp = pow(10, $p);
		return round($n * $temp) / $temp;
	}

	///////////////////////////////////////

	public function wp_mktime($_timestamp = '')
	{
		if($_timestamp){
			$_split_datehour = explode(' ',$_timestamp);
			$_split_data = explode("-", $_split_datehour[0]);
			$_split_hour = explode(":", $_split_datehour[1]);

			return mktime ($_split_hour[0], $_split_hour[1], $_split_hour[2], $_split_data[1], $_split_data[2], $_split_data[0]);
		}
	}

	///////////////////////

	public function strip_tags_content($text, $tags = '', $invert = FALSE)
	{
	preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
	$tags = array_unique($tags[1]);

	if(is_array($tags) AND count($tags) > 0) {
		if($invert == FALSE) {
		return preg_replace('@<(?!(?:'. implode('|', $tags) .')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
		}
		else {
		return preg_replace('@<('. implode('|', $tags) .')\b.*?>.*?</\1>@si', '', $text);
		}
	}
	elseif($invert == FALSE) {
		return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
	}
	return $text;
	}

	///////////////////////////////////////////////////////////////////

	public function formatText($text)
	{
		$text = preg_replace('/http:\/\/([^\s]*)/i',
				"<a href='http://$1' target='_blank'>http://$1</a>", $text);

		$text = preg_replace('/https:\/\/([^\s]*)/i',
				"<a href='https://$1' target='_blank'>https://$1</a>", $text);

		$text = preg_replace('/\#([a-zA-Z0-9_Ã‡-Ã ]*)/',
				"<a href='http://twitter.com/search?q=%23$1' target='_blank'>#$1</a>", $text);

		$text = preg_replace('/\@([a-zA-Z0-9_Ã‡-Ã ]*)/',
				"<a href='https://twitter.com/$1' target='_blank'>@$1</a> ", $text);

		$text = $this->forceWordbreak($text, 80);
		return $text;
	}

	public function formatWalletVersion($coin)
	{
		$version = substr($coin->version, 0, 20);
		if (is_numeric($version)) {
			$decver = sprintf("%08d", 0 + $version);
			$version = intval(substr($decver, 0, 2)).'.'.intval(substr($decver, 2, 2)).
				'.'.intval(substr($decver, 4, 2));
			if (intval(substr($decver, 6)))
				$version .= '.'.intval(substr($decver, 6));
		} else {
			$version = ltrim($version, 'v');
		}
		return $version;
	}
	public function objSafeVal($obj,$key,$default=NULL)
	{
		if (is_object($obj) && property_exists($obj,$key))
			return $obj->$key;
		elseif (is_array($obj))
			return $this->arraySafeVal($obj,$key,$default);
		return $default;
	}

	public function arraySafeVal($arr,$key,$default=NULL)
	{
		if (is_array($arr) && isset($arr[$key]))
			return $arr[$key];
		elseif (is_object($arr))
			return $this->objSafeVal($arr,$key,$default);
		return $default;
	}

	/** Insert a space whenever a word runs longer than $max chars (ported from legacy force_wordbreak). */
	private function forceWordbreak(string $text, int $max): string
	{
		$last = 0;
		for ($i = 0; $i < strlen($text); $i++) {
			if ($text[$i] === ' ') {
				$last = $i;
			} elseif ($i - $last > $max) {
				$text = substr_replace($text, ' ', $i, 0);
				$i++;
				$last = $i;
			}
		}
		return $text;
	}

}