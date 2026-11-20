<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use app\components\rpc\WalletRPC;

class Coins extends ActiveRecord
{
	public function rules()
    {
        return [
            [['name', 'symbol'], 'required'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            // specifications column repurposed as a JSON wallet_info cache;
            // written by CoinService::updateCoinStats(), read by the explorer overview.
            'specifications' => 'Wallet Info',
        ];
    }

    /**
     * Return the decoded wallet_info JSON stored in specifications, or [].
     * Keys: networkhashps (float), updated_at (int unix timestamp).
     */
    public function getWalletInfo(): array
    {
        if (empty($this->specifications)) {
            return [];
        }
        return json_decode($this->specifications, true) ?: [];
    }

    /**
     * Live peer/node lines for the block explorer. The wallet_info cache does
     * not contain peers, so query the daemon directly like the legacy page did.
     */
    public function getPeerAddresses(): array
    {
        if (empty($this->rpchost)) {
            return [];
        }

        $remote = new WalletRPC($this);
        if ($remote->error !== null) {
            return [];
        }

        if ($this->rpcencoding === 'GETH') {
            // Geth usually disables admin_peers over the public RPC port; show
            // the peer count and client string instead of an empty node list.
            $lines = [];
            $count = $remote->net_peerCount();
            if (is_string($count) && preg_match('/^0x[0-9a-f]+$/i', $count)) {
                $count = hexdec($count);
            }
            if ($count !== false && $count !== null) {
                $lines[] = 'Peer count: ' . (int) $count;
            }
            $version = $remote->web3_clientVersion();
            if (is_string($version) && $version !== '') {
                $lines[] = 'Client: ' . $version;
            }
            return $lines;
        }

        $list = $remote->getpeerinfo();
        if (!is_array($list)) {
            return [];
        }

        $prefix = $this->rpcencoding === 'DCR' ? 'addpeer=' : 'addnode=';
        $lines  = [];
        foreach ($list as $peer) {
            $addr = is_array($peer) ? (string) ($peer['addr'] ?? '') : (string) $peer;
            if ($addr === '' || $this->isLocalPeerAddress($addr)) {
                continue;
            }
            $lines[] = $prefix . $addr;
        }

        sort($lines, SORT_STRING);
        return $lines;
    }

    private function isLocalPeerAddress(string $addr): bool
    {
        $host = preg_replace('/:\d+$/', '', $addr);
        if ($host === '' || stripos($host, 'localhost') !== false || stripos($host, 'yiimp') !== false) {
            return true;
        }
        return (bool) preg_match(
            '/^(?:127\.|10\.|192\.168\.|172\.(?:1[6-9]|2\d|3[01])\.|169\.254\.|\[::1\])/',
            $host
        );
    }

    public function getOfficialSymbol()
	{
		if(!empty($this->symbol2))
			return $this->symbol2;
		else
			return $this->symbol;
	}
	public function getSymbol_show()
	{
		// virtual property $coin->symbol_show
		return $this->getOfficialSymbol();
	}

	/**
	 * Internal explorer URL. Visible coins use the clean /explorer/SYMBOL form;
	 * the numeric id is only needed for hidden or numeric-symbol coins.
	 */
	public function getExplorerUrl($params=array())
	{
		if ($this->id == 6 && isset($params['txid'])) {
			return 'https://blockchain.info/tx/'.$params['txid'];
		}

		$symbol = $this->getOfficialSymbol();
		$route = ['/explorer/'.$symbol];
		if (!$this->visible || is_numeric($symbol)) {
			$route['id'] = $this->id;
		}

		return Url::to(array_merge($route, $params));
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
		else if (YIIMP_PUBLIC_EXPLORER || $force || 
				((!is_null(Yii::$app->user->identity)) && (Yii::$app->user->identity->is_admin))) {
			
			$url = $this->getExplorerUrl($params);

			return Html::a($label, $url, ['class' => 'profile-link']);
		}
		return $label;
	}

}