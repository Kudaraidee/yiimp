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

	// Explorer search forms are read-only legacy POST forms without CSRF tokens.
	public $enableCsrfValidation = false;

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

		$request = Yii::$app->getRequest();
		if (strlen($id) <= 10 && !$request->getQueryParam('id')) {
			$coin = Coins::findOne(['symbol' => strtoupper($id)]);
			if (!$coin && ctype_digit($id)) {
				$coin = Coins::findOne((int) $id);
			}

			$isAdmin = !Yii::$app->user->isGuest && (Yii::$app->user->identity?->is_admin ?? false);
			if ($coin && ($coin->visible || $isAdmin)) {
				$query = $request->getQueryParams();
				if ($request->getIsPost()) {
					// Mirror the Yii1 controller: POST searches redirect through
					// actionSearch() so the coin is retained in the URL.
					$key = $coin->visible ? 'SYM' : 'id';
					$query[$key] = $coin->visible ? $coin->symbol : $coin->id;
					$id = 'search';
				} else {
					// /explorer/KIIRO and /explorer/8 are aliases for /explorer?id=N
					$query['id'] = $coin->id;
					$id = $this->defaultAction;
				}
				$request->setQueryParams($query);
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
			$coin = Coins::findOne((int) $params['id']);
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
		if ($coin && strlen($q) >= 32 && ctype_xdigit($q)) {
			$remote = new WalletRPC($coin);
			$block = $remote->getblock($q);
			if ($block) {
				$hash = $q;
				$height = (int) Yii::$app->ConversionUtils->arraySafeVal($block, 'height');
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
		$utils = Yii::$app->YiimpUtils;
		$height = (int) $utils->getparam('height');
		$txid = $utils->gethexparam('txid');
		$hash = $utils->gethexparam('hash');
		$q = $utils->gethexparam('q');
		$url = '/'; // defaults to home on invalid search
		$sym = $utils->getparam('SYM', NULL);
		if ($sym !== null) {
			// only for visible coins
			$sym = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $sym);
			if ($sym !== '') $url = "/explorer/{$sym}?";
		} else if ($utils->getparam('id', NULL) !== NULL) {
			// only for hidden coins
			$url = '/explorer/'.(int) $utils->getparam('id').'?';
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
