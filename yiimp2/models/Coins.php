<?php

namespace app\models;

use Yii;
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
     * Network hashrate read directly from the coin daemon, cached for 60s.
     *
     * Bitcoin-like nodes expose getnetworkhashps / getmininginfo. Geth has no
     * equivalent RPC, so derive it from the latest block difficulty divided by
     * the timestamp gap to the previous block.
     *
     * Returns null when RPC is unavailable or the daemon cannot report a value.
     */
    public function getRpcNetworkHashrate(int $ttl = 60): ?float
    {
        if (empty($this->rpchost)) {
            return null;
        }

        // Keep the key alphanumeric so Yii2's Cache::buildKey() does not hash
        // it; the legacy Yii1 backend writes the same raw memcache key.
        $cacheKey = "yiimpRpcNethash{$this->id}";
        $cached   = Yii::$app->cache->get($cacheKey);
        if ($cached !== false && $cached !== null && (float) $cached > 0) {
            return (float) $cached;
        }

        $remote = new WalletRPC($this);
        if ($remote->error !== null) {
            return null;
        }

        $rate = null;

        if ($this->rpcencoding === 'GETH') {
            $rate = $this->gethNetworkHashrate($remote);
        } else {
            $raw = $remote->getnetworkhashps([120, -1]);
            $rate = is_array($raw)
                ? (float) ($raw[$this->algo] ?? 0)
                : (float) $raw;

            if ($rate <= 0) {
                $info = $remote->getmininginfo();
                if (is_array($info)) {
                    if (isset($info['networkhashps'])) {
                        $raw = $info['networkhashps'];
                        $rate = is_array($raw)
                            ? (float) ($raw[$this->algo] ?? 0)
                            : (float) $raw;
                    } elseif (isset($info['networkhps'])) {
                        $rate = (float) $info['networkhps'];
                    } elseif (isset($info['netmhashps'])) {
                        $rate = (float) $info['netmhashps'] * 1e6;
                    }
                }
            }
        }

        if ($rate > 0) {
            Yii::$app->cache->set($cacheKey, $rate, $ttl);
            return (float) $rate;
        }

        return null;
    }

    private function gethNetworkHashrate(WalletRPC $remote): ?float
    {
        $latest = $remote->eth_getBlockByNumber('latest', false);
        if (!is_object($latest) && !is_array($latest)) {
            return null;
        }

        $field = static function ($block, string $key): mixed {
            if (is_object($block)) return $block->$key ?? null;
            return is_array($block) ? ($block[$key] ?? null) : null;
        };

        $height = $this->rpcHexToNumber($field($latest, 'number'));
        if ($height <= 0) {
            return null;
        }

        // A single block gap is very noisy. Match getnetworkhashps' 120-block
        // window so the Geth estimate is stable across lucky/unlucky blocks.
        $blocks   = min(120, (int) $height);
        $previous = $remote->eth_getBlockByNumber('0x' . dechex((int) $height - $blocks), false);
        if (!is_object($previous) && !is_array($previous)) {
            return null;
        }

        $difficulty = $this->rpcHexToNumber($field($latest, 'difficulty'));
        $gap        = $this->rpcHexToNumber($field($latest, 'timestamp'))
                    - $this->rpcHexToNumber($field($previous, 'timestamp'));

        if ($difficulty <= 0 || $gap <= 0) {
            return null;
        }

        return $difficulty / ($gap / $blocks);
    }

    private function rpcHexToNumber(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = (string) $value;
        if (str_starts_with($value, '0x')) {
            $value = substr($value, 2);
        }
        if ($value !== '' && preg_match('/^[0-9a-f]+$/i', $value)) {
            return (float) hexdec($value);
        }

        return 0.0;
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

        // Geth now goes through the same path: WalletRPC maps admin_peers (or
        // falls back to a synthetic peer-count row when admin is disabled).
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
            if (is_array($peer) && !empty($peer['synthetic'])) {
                continue; // informational "Peer count: N", not an address
            }
            $lines[] = $prefix . $addr;
        }

        if (empty($lines) && $this->rpcencoding === 'GETH') {
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
	 * Canonical local icon path: symbol2 (official symbol) first, then symbol.
	 * This is what all writers should persist instead of /images/coin-<id>.png.
	 */
	public static function imagePathFor($symbol, $symbol2 = null): string
	{
		$symbol = preg_replace('/[^A-Z0-9_-]/', '', strtoupper(trim((string) ($symbol2 ?: $symbol))));
		return $symbol === '' ? '' : "/images/coin-{$symbol}.png";
	}

	/**
	 * Resolve the best available icon path for a coin row.
	 * Canonical symbol path wins; existing local and legacy id paths still work.
	 */
	public static function imageUrlFor(array $coin): string
	{
		$image = (string) ($coin['image'] ?? '');
		if ($image !== '' && stripos($image, 'http') === 0) return $image;

		$root      = defined('YIIMP_HTDOCS') ? YIIMP_HTDOCS : dirname(__DIR__) . '/web';
		$canonical = self::imagePathFor($coin['symbol'] ?? '', $coin['symbol2'] ?? null);
		if ($canonical !== '' && is_file($root . $canonical)) return $canonical;
		if ($image !== '' && is_file($root . $image)) return $image;

		$id = (int) ($coin['id'] ?? 0);
		if ($id > 0) {
			$legacy = "/images/coin-{$id}.png";
			if (is_file($root . $legacy)) return $legacy;
		}

		return $image;
	}

	public function imageUrl(): string
	{
		return self::imageUrlFor($this->attributes);
	}

	/**
	 * Canonical public block page URL: /coins/SYMBOL.
	 *
	 * Keep this in one place so every listing (mining, history, admin) agrees.
	 * Numeric/empty symbols fall back to /coins/<id>, which actionBlock()
	 * resolves just like a symbol-only route.
	 */
	public static function blockUrl($symbol, int $id = 0): string
	{
		$symbol = trim((string) $symbol);
		if ($symbol === '' || is_numeric($symbol)) {
			return '/coins/' . (int) $id;
		}
		return '/coins/' . rawurlencode($symbol);
	}

	public function getBlockUrl(): string
	{
		return static::blockUrl($this->getOfficialSymbol(), (int) $this->id);
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