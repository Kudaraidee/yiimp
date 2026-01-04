<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\bootstrap5\Html;

/**
 * Coins model
 * 
 * @property int $id
 * @property string $name
 * @property string $symbol
 * @property string $symbol2
 * @property string $algo
 * @property string $version
 * @property string $image
 * @property string $market
 * @property int $marketid
 * @property string $master_wallet
 * @property string $wallet_zaddress
 * @property string $charity_address
 * @property double $charity_amount
 * @property double $charity_percent
 * @property string $deposit_address
 * @property double $deposit_minimum
 * @property int $sellonbid
 * @property int $dontsell
 * @property double $sellthreshold
 * @property int $auto_exchange
 * @property string $block_explorer
 * @property double $index_avg
 * @property int $connections
 * @property string $errors
 * @property double $balance
 * @property double $immature
 * @property double $cleared
 * @property double $available
 * @property double $stake
 * @property double $mint
 * @property double $txfee
 * @property double $payout_min
 * @property double $payout_max
 * @property int $block_time
 * @property double $difficulty
 * @property double $difficulty_pos
 * @property int $block_height
 * @property int $target_height
 * @property int $powend_height
 * @property double $network_hash
 * @property double $price
 * @property double $price2
 * @property double $reward
 * @property double $reward_mul
 * @property int $mature_blocks
 * @property int $enable
 * @property int $visible
 * @property int $auto_ready
 * @property int $auxpow
 * @property int $no_explorer
 * @property int $max_miners
 * @property int $max_shares
 * @property int $installed
 * @property int $watch
 * @property string $conf_folder
 * @property string $program
 * @property string $rpcuser
 * @property string $rpcpasswd
 * @property string $rpchost
 * @property int $rpcport
 * @property string $rpcwallet
 * @property string $serveruser
 * @property int $dedicatedport
 * @property int $rpccurl
 * @property int $rpcssl
 * @property string $rpccert
 * @property string $rpcencoding
 * @property string $account
 * @property int $hasgetinfo
 * @property int $hassubmitblock
 * @property int $txmessage
 * @property int $hasmasternodes
 * @property int $usesegwit
 * @property int $usemweb
 * @property int $enable_rpcdebug
 * @property double $actual_ttf
 * @property int $powlimit_bits
 * @property int $decimals
 * @property string $personalization
 * @property string $version_github
 * @property string $version_installed
 * @property string $link_bitcointalk
 * @property string $link_github
 * @property string $link_site
 * @property string $link_exchange
 * @property string $link_explorer
 * @property string $link_twitter
 * @property string $link_discord
 * @property string $link_facebook
 * @property blob $specifications
 */
class Coins extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'coins';
    }

    /**
     * @inheritdoc
     * Set default values for new records
     */
    public function init()
    {
        parent::init();
        
        // Set default values for required numeric fields
        if ($this->isNewRecord) {
            $this->decimals = $this->decimals ?? 8;
            $this->block_time = $this->block_time ?? 60;
            $this->mature_blocks = $this->mature_blocks ?? 100;
            $this->reward_mul = $this->reward_mul ?? 1;
            $this->enable = $this->enable ?? 0;
            $this->visible = $this->visible ?? 0;
            $this->auto_ready = $this->auto_ready ?? 0;
            $this->installed = $this->installed ?? 0;
            $this->watch = $this->watch ?? 0;
            $this->auxpow = $this->auxpow ?? 0;
            $this->no_explorer = $this->no_explorer ?? 0;
            $this->dontsell = $this->dontsell ?? 0;
            $this->sellonbid = $this->sellonbid ?? 0;
            $this->auto_exchange = $this->auto_exchange ?? 0;
            $this->rpccurl = $this->rpccurl ?? 0;
            $this->rpcssl = $this->rpcssl ?? 0;
            $this->hasgetinfo = $this->hasgetinfo ?? 0;
            $this->hassubmitblock = $this->hassubmitblock ?? 0;
            $this->txmessage = $this->txmessage ?? 0;
            $this->hasmasternodes = $this->hasmasternodes ?? 0;
            $this->usesegwit = $this->usesegwit ?? 0;
            $this->usemweb = $this->usemweb ?? 0;
            $this->enable_rpcdebug = $this->enable_rpcdebug ?? 0;
        }
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            // Required fields
            [['name', 'symbol'], 'required'],
            
            // String fields with length constraints
            [['name'], 'string', 'min' => 1, 'max' => 64],
            [['symbol'], 'string', 'min' => 1, 'max' => 16],
            [['symbol2', 'algo', 'rpcencoding'], 'string', 'max' => 16],
            [['version'], 'string', 'max' => 32],
            [['rpcwallet', 'account'], 'string', 'max' => 64],
            [['market'], 'string', 'max' => 64],
            [['conf_folder', 'program', 'rpcuser', 'rpcpasswd', 'rpchost', 'serveruser'], 'string', 'max' => 128],
            [['rpccert'], 'string', 'max' => 255],
            [['image', 'master_wallet', 'wallet_zaddress', 'charity_address', 'deposit_address', 'block_explorer', 
              'version_github', 'version_installed', 'personalization', 'errors',
              'link_bitcointalk', 'link_github', 'link_site', 'link_exchange', 'link_explorer', 
              'link_twitter', 'link_discord', 'link_facebook'], 'string', 'max' => 1024],
            
            // Integer fields
            [['marketid', 'connections', 'block_height', 'target_height', 'powend_height', 
              'mature_blocks', 'max_miners', 'max_shares', 'dedicatedport'], 'integer'],
            
            // Boolean/tinyint fields
            [['enable', 'visible', 'auto_ready', 'auxpow', 'no_explorer', 'installed', 'watch',
              'sellonbid', 'dontsell', 'auto_exchange', 'enable_rpcdebug',
              'rpccurl', 'rpcssl', 'hasgetinfo', 'hassubmitblock', 'txmessage', 'hasmasternodes', 
              'usesegwit', 'usemweb'], 'boolean'],
            
            // Tinyint fields with specific ranges
            [['decimals'], 'integer', 'min' => 0, 'max' => 255],
            [['powlimit_bits'], 'integer', 'min' => 0, 'max' => 255],
            
            // RPC port validation (1-65535)
            [['rpcport'], 'integer', 'min' => 1, 'max' => 65535],
            
            // Charity percent validation (0-100)
            [['charity_percent'], 'number', 'min' => 0, 'max' => 100],
            
            // Positive numeric fields (exchange and payout fields)
            [['sellthreshold', 'payout_min', 'payout_max', 'deposit_minimum'], 'number', 'min' => 0],
            
            // General numeric fields (can be any value)
            [['charity_amount', 'index_avg', 'difficulty', 'difficulty_pos', 'reward', 'reward_mul', 
              'price', 'price2', 'block_time', 'actual_ttf', 'network_hash', 'txfee',
              'balance', 'immature', 'cleared', 'available', 'stake', 'mint'], 'number'],
            
            // URL validation for link fields
            [['link_bitcointalk', 'link_github', 'link_site', 'link_exchange', 'link_explorer', 
              'link_twitter', 'link_discord', 'link_facebook'], 'url', 'skipOnEmpty' => true],
            
            // Blob field
            [['specifications'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'symbol' => 'Symbol',
            'symbol2' => 'Official Symbol',
            'algo' => 'Algorithm',
            'version' => 'Version',
            'version_github' => 'GitHub Version',
            'version_installed' => 'Installed Version',
            'image' => 'Image URL',
            'market' => 'Market',
            'marketid' => 'Market ID',
            'master_wallet' => 'Master Wallet',
            'wallet_zaddress' => 'Z-Address (Privacy Coins)',
            'charity_address' => 'Charity Address',
            'charity_amount' => 'Charity Amount',
            'charity_percent' => 'Charity Percent',
            'deposit_address' => 'Deposit Address',
            'deposit_minimum' => 'Minimum Deposit',
            'sellonbid' => 'Sell on Bid',
            'dontsell' => 'Don\'t Sell',
            'sellthreshold' => 'Sell Threshold',
            'auto_exchange' => 'Auto Exchange',
            'block_explorer' => 'Block Explorer',
            'index_avg' => 'Index Average',
            'connections' => 'Connections',
            'errors' => 'Errors',
            'balance' => 'Balance',
            'immature' => 'Immature',
            'cleared' => 'Cleared',
            'available' => 'Available',
            'stake' => 'Stake',
            'mint' => 'Mint',
            'txfee' => 'Transaction Fee',
            'payout_min' => 'Minimum Payout',
            'payout_max' => 'Maximum Payout',
            'block_time' => 'Block Time',
            'difficulty' => 'Difficulty',
            'difficulty_pos' => 'PoS Difficulty',
            'block_height' => 'Block Height',
            'target_height' => 'Target Height',
            'powend_height' => 'PoW End Height',
            'network_hash' => 'Network Hashrate',
            'price' => 'Price (BTC)',
            'price2' => 'Price 2',
            'reward' => 'Block Reward',
            'reward_mul' => 'Reward Multiplier',
            'mature_blocks' => 'Mature Blocks',
            'enable' => 'Enabled',
            'visible' => 'Visible',
            'auto_ready' => 'Auto Ready',
            'auxpow' => 'AuxPoW',
            'no_explorer' => 'No Explorer',
            'max_miners' => 'Max Miners',
            'max_shares' => 'Max Shares',
            'installed' => 'Installed',
            'watch' => 'Watch',
            'conf_folder' => 'Config Folder',
            'program' => 'Program',
            'rpcuser' => 'RPC Username',
            'rpcpasswd' => 'RPC Password',
            'rpchost' => 'RPC Host',
            'rpcport' => 'RPC Port',
            'rpcwallet' => 'RPC Wallet',
            'serveruser' => 'Server User',
            'dedicatedport' => 'Dedicated Port',
            'rpccurl' => 'Force cURL for RPC',
            'rpcssl' => 'RPC SSL',
            'rpccert' => 'RPC Certificate',
            'rpcencoding' => 'RPC Encoding',
            'account' => 'Account',
            'hasgetinfo' => 'Has getinfo',
            'hassubmitblock' => 'Has submitblock',
            'txmessage' => 'TX Message',
            'hasmasternodes' => 'Has Masternodes',
            'usesegwit' => 'Use SegWit',
            'usemweb' => 'Use MWeb',
            'enable_rpcdebug' => 'Enable RPC Debug',
            'actual_ttf' => 'Actual TTF',
            'powlimit_bits' => 'PoW Limit Bits',
            'decimals' => 'Decimals',
            'personalization' => 'Personalization',
            'link_bitcointalk' => 'BitcoinTalk Link',
            'link_github' => 'GitHub Link',
            'link_site' => 'Website Link',
            'link_exchange' => 'Exchange Link',
            'link_explorer' => 'Explorer Link',
            'link_twitter' => 'Twitter Link',
            'link_discord' => 'Discord Link',
            'link_facebook' => 'Facebook Link',
            'specifications' => 'Specifications',
        ];
    }

    /**
     * Get blocks relation
     */
    public function getBlocks()
    {
        return $this->hasMany(Blocks::class, ['coin_id' => 'id']);
    }

    /**
     * Get markets relation
     */
    public function getMarkets()
    {
        return $this->hasMany(Markets::class, ['coinid' => 'id']);
    }

    /**
     * Get market history relation
     */
    public function getMarketHistory()
    {
        return $this->hasMany(MarketHistory::class, ['idcoin' => 'id']);
    }

    /**
     * Get official symbol (symbol2 if set, otherwise symbol)
     * Symbol2 takes precedence over symbol
     */
    public function getOfficialSymbol()
    {
        // Trim whitespace and check if symbol2 has a meaningful value
        // Note: We use strlen() instead of empty() because empty('0') returns true in PHP
        // but '0' is a valid symbol
        $symbol2Trimmed = trim((string)$this->symbol2);
        if(strlen($symbol2Trimmed) > 0)
            return $this->symbol2;
        else
            return $this->symbol;
    }

    /**
     * Generate a secure random password for RPC
     * @return string
     */
    public function generateSecurePassword()
    {
        $length = 32;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+-=[]{}|;:,.<>?';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }

    /**
     * Get sample daemon configuration file content
     * @return string Configuration file content
     */
    public function getSampleConfig()
    {
        if ($this->isNewRecord) {
            return '';
        }

        // Get the stratum port for this algo
        $port = $this->dedicatedport;
        if (!$port) {
            // Try to get port from algos table
            $algo = Algos::findOne(['name' => $this->algo]);
            $port = $algo ? $algo->port : 3333;
        }

        // Get stratum URL from config
        $stratumUrl = defined('YAAMP_STRATUM_URL') ? YAAMP_STRATUM_URL : 'localhost';

        $config = [
            'rpcuser=' . $this->rpcuser,
            'rpcpassword=' . $this->rpcpasswd,
            'rpcport=' . $this->rpcport,
            'rpcallowip=127.0.0.1',
        ];

        // Add wallet parameter for Bitcoin Core 0.17+
        if (!empty($this->rpcwallet)) {
            $config[] = '# For Bitcoin Core 0.17+ multi-wallet support';
            $config[] = 'wallet=' . $this->rpcwallet;
        }

        // Add blocknotify
        $config[] = 'blocknotify=/var/stratum/blocknotify ' . $stratumUrl . ':' . $port . ' ' . $this->id . ' %s';

        // Add algorithm-specific settings
        if ($this->algo == 'equihash' && !empty($this->personalization)) {
            $config[] = '# Equihash personalization string';
            $config[] = 'personalization=' . $this->personalization;
        }

        // Add server=1 for daemon mode
        $config[] = 'server=1';
        $config[] = 'daemon=1';

        // Add txindex for explorer functionality
        if (!$this->no_explorer) {
            $config[] = 'txindex=1';
        }

        return implode("\n", $config);
    }

    /**
     * Get sample miner command line
     * @return string Sample command for miners
     */
    public function getSampleMinerCommand()
    {
        if ($this->isNewRecord) {
            return '';
        }

        // Get the stratum port for this algo
        $port = $this->dedicatedport;
        if (!$port) {
            // Try to get port from algos table
            $algo = Algos::findOne(['name' => $this->algo]);
            $port = $algo ? $algo->port : 3333;
        }

        // Get stratum URL from config
        $stratumUrl = defined('YAAMP_STRATUM_URL') ? YAAMP_STRATUM_URL : 'localhost';

        // Determine miner software based on algorithm
        $minerSoftware = 'ccminer'; // default
        $algoParam = $this->algo;

        // Map algorithms to common miner software
        $minerMap = [
            'sha256' => 'cgminer',
            'scrypt' => 'cgminer',
            'x11' => 'ccminer',
            'x13' => 'ccminer',
            'x15' => 'ccminer',
            'equihash' => 'ewbf-miner',
            'ethash' => 'ethminer',
            'kawpow' => 't-rex',
            'randomx' => 'xmrig',
        ];

        if (isset($minerMap[$this->algo])) {
            $minerSoftware = $minerMap[$this->algo];
        }

        // Build command based on miner type
        $command = '';
        switch ($minerSoftware) {
            case 'cgminer':
                $command = "cgminer -o stratum+tcp://{$stratumUrl}:{$port} -u <WALLET_ADDRESS> -p c=<COIN_SYMBOL>";
                break;
            case 'ewbf-miner':
                $command = "miner --server {$stratumUrl} --port {$port} --user <WALLET_ADDRESS> --pass c=<COIN_SYMBOL>";
                break;
            case 'ethminer':
                $command = "ethminer -P stratum+tcp://<WALLET_ADDRESS>@{$stratumUrl}:{$port}";
                break;
            case 't-rex':
                $command = "t-rex -a {$algoParam} -o stratum+tcp://{$stratumUrl}:{$port} -u <WALLET_ADDRESS> -p c=<COIN_SYMBOL>";
                break;
            case 'xmrig':
                $command = "xmrig -o {$stratumUrl}:{$port} -u <WALLET_ADDRESS> -p c=<COIN_SYMBOL>";
                break;
            default:
                $command = "ccminer -a {$algoParam} -o stratum+tcp://{$stratumUrl}:{$port} -u <WALLET_ADDRESS> -p c=<COIN_SYMBOL>";
                break;
        }

        return $command;
    }

    /**
     * Before validation hook to set default values
     */
    public function beforeValidate()
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        // Set default values for new records
        if ($this->isNewRecord) {
            // Default RPC user
            if (empty($this->rpcuser)) {
                $this->rpcuser = 'yiimprpc';
            }

            // Generate secure password if not set
            if (empty($this->rpcpasswd)) {
                $this->rpcpasswd = $this->generateSecurePassword();
            }

            // Default RPC port based on coin ID
            // Note: For new records without ID yet, this will be 0
            // The port should be set after the record is saved and has an ID
            if (empty($this->rpcport) && !empty($this->id)) {
                $this->rpcport = $this->id * 10;
            }
        }

        return true;
    }

    /**
     * Virtual property for symbol display
     */
    public function getSymbol_show()
    {
        return $this->getOfficialSymbol();
    }

    /**
     * Get decimals (default 8 if not set)
     */
    public function getDecimals()
    {
        return $this->decimals ?? 8;
    }

    /**
     * Link for txs
     * @param string $label link content
     * @param array $params 'height'=>123 or 'hash'=>'xxx' or 'txid'=>'xxx'
     * @param array $htmlOptions target/title ...
     */
    public function createExplorerLink($label, $params=array(), $htmlOptions=array(), $force=false)
    {
        if($this->id == 6 && isset($params['txid'])) {
            // BTC txid
            $url = 'https://blockchain.info/tx/'.$params['txid'];
            $htmlOpts = array_merge(array('target'=>'_blank', 'class' => 'profile-link'), $htmlOptions);
            return Html::a($label, $url, $htmlOpts);
        }
        else if (defined('YIIMP_PUBLIC_EXPLORER') && (YIIMP_PUBLIC_EXPLORER || $force || 
                ((!is_null(Yii::$app->user->identity)) && (Yii::$app->user->identity->is_admin)))) {
            
            $urlParams = array_merge(['/explorer/'.$this->getOfficialSymbol(), 'id'=>$this->id], $params);

            return Html::a($label, $urlParams, ['class' => 'profile-link']);
        }
        return $label;
    }

}