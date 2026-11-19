<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\bootstrap5\Html;

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
			
			$urlParams = array_merge(['/explorer/'.$this->getOfficialSymbol(), 'id'=>$this->id], $params);

			return Html::a($label, $urlParams, ['class' => 'profile-link']);
		}
		return $label;
	}

}