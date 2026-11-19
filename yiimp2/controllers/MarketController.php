<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use app\models\Markets;
use app\models\Coins;
use app\components\rpc\WalletRPC;
use app\services\BlockService;

/**
 * Admin-only market actions, routed via /admin/market/* URL rules.
 * Ported from web/yaamp/modules/market/MarketController.php.
 */
class MarketController extends BaseController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
                'denyCallback' => function () {
                    return $this->goHome();
                },
            ],
        ];
    }

    // -------------------------------------------------------------------------

    public function actionUpdate(): mixed
    {
        $this->requireAdmin();
        $market = Markets::findOne((int) Yii::$app->request->get('id'));
        if (!$market) {
            return $this->goHome();
        }
        $coin = Coins::findOne($market->coinid);

        if (Yii::$app->request->isPost) {
            $market->setAttributes(Yii::$app->request->post('Markets', []), false);
            if ($market->save()) {
                return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
            }
        }

        return $this->render('update', ['market' => $market, 'coin' => $coin]);
    }

    public function actionEnable(): mixed
    {
        $this->requireAdmin();
        $market = Markets::findOne((int) Yii::$app->request->get('id'));
        if ($market) {
            $market->disabled = Yii::$app->request->get('en') ? 0 : 9;
            $market->save();
        }
        return $this->redirect(Yii::$app->request->referrer ?: Yii::$app->homeUrl);
    }

    public function actionDelete(): mixed
    {
        $this->requireAdmin();
        $market = Markets::findOne((int) Yii::$app->request->get('id'));
        if ($market) {
            $market->delete();
        }
        return $this->redirect(Yii::$app->request->referrer ?: Yii::$app->homeUrl);
    }

    public function actionSellto(): mixed
    {
        $this->requireAdmin();
        $market = Markets::findOne((int) Yii::$app->request->get('id'));
        if (!$market) {
            Yii::$app->session->setFlash('error', 'Invalid market.');
            return $this->redirect(Yii::$app->request->referrer ?: Yii::$app->homeUrl);
        }

        $coin = Coins::findOne($market->coinid);
        if (!$coin) {
            Yii::$app->session->setFlash('error', 'Coin not found for this market.');
            return $this->redirect(Yii::$app->request->referrer ?: Yii::$app->homeUrl);
        }

        $amount = (float) Yii::$app->request->get('amount', 0);

        $remote = new WalletRPC($coin);
        $info   = $remote->getinfo();

        if (!$info || empty($info['balance'])) {
            Yii::$app->session->setFlash('error', "not enough balance {$coin->name}");
            return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
        }

        $depositInfo = $remote->validateaddress($market->deposit_address);
        if (!$depositInfo || empty($depositInfo['isvalid'])) {
            Yii::$app->session->setFlash('error', "invalid address {$coin->name}, {$market->deposit_address}");
            return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
        }

        $amount = round(min($amount, $info['balance'] - ($info['paytxfee'] ?? 0)), 8);

        $tx = $remote->sendtoaddress($market->deposit_address, $amount);
        if (!$tx) {
            Yii::$app->session->setFlash('error', $remote->error);
            return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
        }

        $market->lastsent = time();
        $market->save();

        (new BlockService())->updatePoolBalances($coin->id);

        try {
            Yii::$app->db->createCommand()->insert('exchange_deposit', [
                'market'         => $market->name,
                'coinid'         => $coin->id,
                'send_time'      => time(),
                'quantity'       => $amount,
                'price_estimate' => $coin->price,
                'status'         => 'waiting',
                'tx'             => $tx,
            ])->execute();
        } catch (\Throwable $e) {
            Yii::warning("exchange_deposit insert failed: {$e->getMessage()}", __CLASS__);
        }

        return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
    }

    private function requireAdmin(): void
    {
        $identity = Yii::$app->user->identity;
        if (!$identity || !$identity->is_admin) {
            throw new \yii\web\ForbiddenHttpException();
        }
    }
}
