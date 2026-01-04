<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

use app\models\Coins;
use app\models\Blocks;

/**
 * ExplorerController handles blockchain exploration features
 */
class ExplorerController extends Controller
{
    /**
     * Displays list of all explorable coins
     * 
     * @return string
     */
    public function actionIndex()
    {
        // Get all visible coins with explorer enabled
        $coins = Coins::find()
            ->where(['visible' => 1, 'enable' => 1])
            ->andWhere(['or', ['no_explorer' => 0], ['no_explorer' => null]])
            ->orderBy(['name' => SORT_ASC])
            ->all();

        return $this->render('index', [
            'coins' => $coins,
        ]);
    }

    /**
     * Displays coin-specific explorer page
     * 
     * @param string $symbol Coin symbol
     * @return string
     */
    public function actionCoin($symbol)
    {
        $coin = $this->findCoinBySymbol($symbol);
        
        if ($coin->no_explorer) {
            throw new NotFoundHttpException('Block explorer is disabled for this coin.');
        }

        return $this->render('coin', [
            'coin' => $coin,
        ]);
    }

    /**
     * Displays block details
     * 
     * @param int $id Coin ID
     * @param string $hash Block hash (optional)
     * @param int $height Block height (optional)
     * @return string
     */
    public function actionBlock($id, $hash = null, $height = null)
    {
        $coin = $this->findCoin($id);
        
        if ($coin->no_explorer) {
            throw new NotFoundHttpException('Block explorer is disabled for this coin.');
        }

        // If height is provided, get hash from RPC
        if ($height !== null && empty($hash)) {
            $blockDetails = Yii::$app->ExplorerUtils->getBlockDetails($coin, null, intval($height));
            if ($blockDetails && isset($blockDetails['hash'])) {
                $hash = $blockDetails['hash'];
            }
        }

        if (empty($hash)) {
            throw new NotFoundHttpException('Block hash or height is required.');
        }

        return $this->render('block', [
            'coin' => $coin,
            'hash' => $hash,
        ]);
    }

    /**
     * Displays transaction details
     * 
     * @param int $id Coin ID
     * @param string $txid Transaction ID
     * @return string
     */
    public function actionTx($id, $txid)
    {
        $coin = $this->findCoin($id);
        
        if ($coin->no_explorer) {
            throw new NotFoundHttpException('Block explorer is disabled for this coin.');
        }

        if (empty($txid)) {
            throw new NotFoundHttpException('Transaction ID is required.');
        }

        return $this->render('tx', [
            'coin' => $coin,
            'txid' => $txid,
        ]);
    }

    /**
     * Search for block or transaction by hash or height
     * 
     * @param int $id Coin ID
     * @param string $query Search query (hash or height)
     * @return mixed
     */
    public function actionSearch($id, $query)
    {
        $coin = $this->findCoin($id);
        
        if ($coin->no_explorer) {
            throw new NotFoundHttpException('Block explorer is disabled for this coin.');
        }

        if (empty($query)) {
            throw new NotFoundHttpException('Search query is required.');
        }

        $query = trim($query);

        // Check if query is a numeric height
        if (is_numeric($query)) {
            $height = intval($query);
            return $this->redirect(['block', 'id' => $coin->id, 'height' => $height]);
        }

        // Validate hex string
        if (!ctype_xdigit($query)) {
            throw new NotFoundHttpException('Invalid search query. Must be a block height, block hash, or transaction ID.');
        }

        // Try to determine if it's a block or transaction
        $hashType = Yii::$app->ExplorerUtils->searchHash($coin, $query);

        if ($hashType === 'block') {
            return $this->redirect(['block', 'id' => $coin->id, 'hash' => $query]);
        } elseif ($hashType === 'transaction') {
            return $this->redirect(['tx', 'id' => $coin->id, 'txid' => $query]);
        } else {
            throw new NotFoundHttpException('Hash not found in blockchain.');
        }
    }

    /**
     * Displays peer connections for a coin
     * 
     * @param int $id Coin ID
     * @return string
     */
    public function actionPeers($id)
    {
        $coin = $this->findCoin($id);
        
        return $this->render('peers', [
            'coin' => $coin,
        ]);
    }

    /**
     * Displays blockchain statistics graphs
     * 
     * @param int $id Coin ID
     * @return string
     */
    public function actionGraph($id)
    {
        $coin = $this->findCoin($id);
        
        return $this->renderPartial('graph', [
            'coin' => $coin,
        ]);
    }

    /**
     * Finds coin by ID
     * 
     * @param int $id
     * @return Coins
     * @throws NotFoundHttpException
     */
    protected function findCoin($id)
    {
        $coin = Coins::findOne($id);
        
        if ($coin === null) {
            throw new NotFoundHttpException('Coin not found.');
        }
        
        return $coin;
    }

    /**
     * Finds coin by symbol
     * 
     * @param string $symbol
     * @return Coins
     * @throws NotFoundHttpException
     */
    protected function findCoinBySymbol($symbol)
    {
        $coin = Coins::find()
            ->where(['symbol' => strtoupper($symbol)])
            ->orWhere(['symbol2' => strtoupper($symbol)])
            ->one();
        
        if ($coin === null) {
            throw new NotFoundHttpException('Coin not found.');
        }
        
        return $coin;
    }
}
