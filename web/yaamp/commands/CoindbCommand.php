<?php
/**
 * CoindbCommand is a console command :
 *  - labels: complete Unknown Coins Labels from CryptoCoinCharts.info
 *  - icons: grab coin icons from web sites
 *
 * To use this command, enter the following on the command line:
 * <pre>
 * yiimp coindb labels
 * yiimp coindb icons
 * </pre>
 *
 * @property string $help The command description.
 *
 */
class CoindbCommand extends CConsoleCommand
{
	protected $basePath;

	/**
	 * Execute the action.
	 * @param array $args command line parameters specific for this command
	 * @return integer non zero application exit code after printing help
	 */
	public function run($args)
	{
		$runner=$this->getCommandRunner();
		$commands=$runner->commands;

		$root = realpath(Yii::app()->getBasePath().DIRECTORY_SEPARATOR.'..');
		$this->basePath = str_replace(DIRECTORY_SEPARATOR, '/', $root);

		if (!isset($args[0])) {

			echo "Yiimp coindb command\n";
			echo "Usage: yiimp coindb labels\n";
			echo "       yiimp coindb icons\n";
			return 1;

		} elseif ($args[0] == 'labels') {

			$nbUpdated  = $this->updateCoinCapLabels();
			$nbUpdated += $this->updateYiimpLabels("api.yiimp.eu");
			$nbUpdated += $this->updateFromJson();

			echo "total updated: $nbUpdated\n";
			return 0;

		} elseif ($args[0] == 'icons') {

			$nbUpdated  = $this->grabKuCoinIcons();
			
			echo "total updated: $nbUpdated\n";
			return 0;
		}
	}

	/**
	 * Provides the command description.
	 * @return string the command description.
	 */
	public function getHelp()
	{
		return parent::getHelp().'coindb labels';
	}

	/**
	 * coincap.io api
	 */
	public static function getCoinCapData()
	{
		$json = file_get_contents('http://coincap.io/front');
		$data = json_decode($json,true);
		$array = array();
		foreach ($data as $coin) {
			$key = strtoupper($coin['short']);
			if (empty($key)) continue;
			$array[$key] = $coin;
		}
		return $array;
	}

	public function updateCoinCapLabels()
	{
		$coins = new db_coins;
		$nbUpdated = 0;

		$dataset = $coins->findAll(array(
			'condition'=>"name='unknown' OR name=symbol"
		));

		if (!empty($dataset))
		{
			$json = self::getCoinCapData();
			if (empty($json)) return 0;

			foreach ($dataset as $coin) {
				if ($coin->name == 'unknown' && isset($json[$coin->symbol])) {
					$data = $json[$coin->symbol];
					if ($data['long'] != $coin->name) {
						echo "{$coin->symbol}: {$data['long']}\n";
						$coin->name = trim($data['long']);
						$nbUpdated += $coin->save();
					}
				}
			}
			if ($nbUpdated)
				echo "$nbUpdated coin labels updated from coincap.io\n";
		}
		return $nbUpdated;
	}

	public function updateYiimpLabels($pool)
	{
		$coins = new db_coins;
		$nbUpdated = 0; $nbAlgos = 0;

		$dataset = $coins->findAll(array(
			'condition'=>"name=:u OR algo='' OR algo='scrypt'",
			'params'=>array(':u'=>'unknown')
		));

		if (!empty($dataset))
		{
			$url = "http://{$pool}/api/currencies";
			$data = @ file_get_contents($url);
			if (empty($data)) return 0;
			$json = json_decode($data, true);

			if (!empty($json))
			foreach ($dataset as $coin) {
				if (!isset($json[$coin->symbol])) continue;
				$cc = $json[$coin->symbol];
				if ($coin->name == 'unknown') {
					echo "{$coin->symbol}: {$cc['name']}\n";
					$coin->name = $cc['name'];
					$nbUpdated += $coin->save();
				}
				if (empty($cc['algo'])) continue;
				if (empty($coin->algo) || $coin->algo != $cc['algo']) {
					$coin->algo = strtolower($cc['algo']);
					echo "{$coin->symbol}: algo set to {$cc['algo']}\n";
					$nbAlgos += $coin->save();
				}
			}
			if ($nbUpdated)
				echo "$nbUpdated labels and $nbAlgos algos updated from $pool\n";
		}
		return $nbUpdated;
	}

	/**
	 * To import from a json file placed in the sql/ folder
	 */
	public function updateFromJson()
	{
		$sqlFolder = $this->basePath.'/../sql/';
		$jsonFile = $sqlFolder.'labels.json';
		//$jsonFile = $sqlFolder.'yobit.txt';
		if (!file_exists($jsonFile))
			return 0;

		$nbUpdated = 0;

		$json = json_decode(file_get_contents($jsonFile), true);
	/*
		$json = array();
		$txt = explode("\n", file_get_contents($jsonFile));
		foreach ($txt as $line)
		{
			$cells = explode("\t", $line);
			if (count($cells) < 2) continue;
			$json[$cells[0]] = $cells[1];
		}
	*/
		if (!empty($json))
		{
			$coins = new db_coins;
			$dataset = $coins->findAll(array(
				'condition'=>"name=:u",
				'params'=>array(':u'=>'unknown')
			));

			if (!empty($dataset))
			foreach ($dataset as $coin) {
				if (isset($json[$coin->symbol])) {
					$name = $json[$coin->symbol];
					echo "{$coin->symbol}: {$name}\n";
					$coin->name = $name;
					$nbUpdated += $coin->save();
				}
			}
			if ($nbUpdated)
				echo "$nbUpdated coin labels updated from labels.json file\n";
		}
		return $nbUpdated;
	}

	/**
	 * Icon grabber - KuCoin
	 */
	public function grabKuCoinIcons()
	{
		$url = 'https://assets.kucoin.com/www/1.2.0/assets/coins/';//QLC.png
		$nbUpdated = 0;
		$sql = "SELECT DISTINCT coins.id FROM coins INNER JOIN markets M ON M.coinid = coins.id ".
			"WHERE M.name='kucoin' AND IFNULL(coins.image,'') = ''";
		$coins = dbolist($sql);
		if (empty($coins))
			return 0;
		echo "kucoin: try to download new icons...\n";
		foreach ($coins as $coin) {
			$coin = getdbo('db_coins', $coin["id"]);
			$symbol = $coin->getOfficialSymbol();
			$canonical = yaamp_coin_image_symbol($coin);
			$local = $this->basePath.$canonical;
			try {
				$data = @ file_get_contents($url.$symbol.'.png');
			} catch (Exception $e) {
				continue;
			}
			if (strlen($data) < 2048) continue;
			echo $symbol." icon found\n";
			file_put_contents($local, $data);
			if (filesize($local) > 0) {
				$coin->image = $canonical;
				$nbUpdated += $coin->save();
			}
		}
		if ($nbUpdated)
			echo "$nbUpdated icons downloaded from kucoin\n";
		return $nbUpdated;
	}

}
