<?php

namespace app\controllers;

use Yii;
use yii\base\InlineAction;
use yii\web\Controller;
use app\components\rpc\WalletRPC;

use app\models\Coins;

class ExplorerController extends BaseController
{
	public $defaultAction='index';

	/////////////////////////////////////////////////
	// dynamic reroute action on coin symbol
	public function createAction($id)
	{
		if ($id === '') {
			$id = $this->defaultAction;
		}
		$actionMap = $this->actions();
		if (isset($actionMap[$id])) {
			return Yii::createObject($actionMap[$id], [$id, $this]);
		}

		if (strlen($id) <= 10) {
			$coin = Coins::findOne(['symbol' => $id]);
			
			if ($coin && ($coin->visible)) {
				$id = $this->defaultAction;
			}
		}
		if (preg_match('/^(?:[a-z0-9_]+-)*[a-z0-9_]+$/', $id)) {
			$methodName = 'action' . str_replace(' ', '', ucwords(str_replace('-', ' ', $id)));
			if (method_exists($this, $methodName)) {
				$method = new \ReflectionMethod($this, $methodName);
				if ($method->isPublic() && $method->getName() === $methodName) {
					return new InlineAction($id, $this, $methodName);
				}
			}
		}

		return null;
	}
	/////////////////////////////////////////////////

	// Hide coin id from explorer links... created by createUrl()
	public function createUrl($route,$params=array(),$ampersand='&')
	{
		if ($route == '/explorer' && isset($params['id'])) {
			$coin = getdbo('db_coins', intval($params['id']));
			if ($coin && $coin->visible && !is_numeric($coin->symbol)) {
				unset($params['id']);
				$route = '/explorer/'.$coin->symbol.'?'.http_build_query($params,'',$ampersand);
				$params = array();
			}
		}
		return parent::createUrl($route, $params, $ampersand);
	}

	/////////////////////////////////////////////////

	public function actionIndex()
	{
		if(isset($_COOKIE['mainbtc'])) return;
		//if(!LimitRequest('explorer')) return;

		$id = (int) Yii::$app->getRequest()->getQueryParam('id');
		$coin = Coins::findOne(['id'=>$id]);
		if($coin && $coin->no_explorer) {
			$link = $coin->link_explorer;
			die("Block explorer disabled, please use <a href=\"$link\">$link</a>");
		}
		$height = (int) Yii::$app->getRequest()->getQueryParam('height');
		if($coin && intval($height)>0)
		{
			$remote = new WalletRPC($coin);
			$hash = $remote->getblockhash(intval($height));
		} else {
			$hash = Yii::$app->YiimpUtils->gethexparam('hash');
		}

		$txid = Yii::$app->YiimpUtils->gethexparam('txid');
		$q = Yii::$app->YiimpUtils->gethexparam('q');
		if (strlen($q) >= 32 && ctype_xdigit($q)) {
			$remote = new WalletRPC($coin);
			$block = $remote->getblock($q);
			if ($block) {
				$hash = $q;
				$height = Yii::$app->ConversionUtils->objSafeVal($hash, 'height');
			} else {
				$txid = $q;
			}
		}

		if($coin && !empty($txid))
		{
			$remote = new WalletRPC($coin);
			$tx = $remote->getrawtransaction($txid, 1);
			if (!$tx) $tx = $remote->gettransaction($txid);

			$hash = Yii::$app->ConversionUtils->arraySafeVal($tx,'blockhash');
		}

		if($coin && !empty($hash))
			return $this->render('block', array('coin'=>$coin, 'hash'=>$hash));

		else if($coin)
			return $this->render('coin', array('coin'=>$coin));

		else
			return $this->render('index');
	}

	// alias...
	public function actionId()
	{
		return $this->actionIndex();
	}

	// redirect POST request with url cleanup...
	public function actionSearch()
	{
		$height = getiparam('height');
		$txid = gethexparam('txid');
		$hash = gethexparam('hash');
		$q = gethexparam('q');
		$url = '/'; // defaults to home on invalid search
		if (isset($_GET['SYM'])) {
			// only for visible coins
			$url = "/explorer/".$_GET['SYM']."?";
		} else if (isset($_GET['id'])) {
			// only for hidden coins
			$url = "/explorer/".$_GET['id']."?";
		}
		if (!empty($height)) $url .= "&height=$height";
		if (!empty($txid)) $url .= "&txid=$txid";
		if (!empty($hash)) $url .= "&hash=$hash";
		if (!empty($q)) $url .= "&q=$q";

		return $this->redirect(str_replace('?&', '?', $url));
	}

	/**
	 * Difficulty Graph
	 */
	public function actionGraph()
	{
		$id = (int) Yii::$app->getRequest()->getQueryParam('id');
		$coin = Coins::findOne(['id' => $id]);
		if ($coin)
			return $this->renderPartial('graph', array('coin'=>$coin));
		else
			return "[]";
	}

	/**
	 * Public nodes
	 */
	public function actionPeers()
	{
		$id = (int) Yii::$app->getRequest()->getQueryParam('id');
		$coin = Coins::find()->where(['id'=>$id])->one();
		if ($coin)
			return $this->renderPartial('peers', array('coin'=>$coin));
		else
			return $this->goBack();
	}

}
