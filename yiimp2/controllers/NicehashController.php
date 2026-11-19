<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use app\models\Nicehash;

/**
 * Admin UI for NiceHash order management (pool buys hash power FROM NiceHash).
 * Ported from web/yaamp/modules/nicehash/NicehashController.php.
 */
class NicehashController extends BaseController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [[
                    'allow'         => true,
                    'roles'         => ['@'],
                    'matchCallback' => fn() => Yii::$app->user->identity?->is_admin,
                ]],
                'denyCallback' => fn() => $this->redirect(['/admin/login']),
            ],
        ];
    }

    public function actionIndex(): string
    {
        return $this->render('index');
    }

    public function actionIndex_results(): string
    {
        return $this->renderPartial('index_results');
    }

    public function actionStart(): \yii\web\Response
    {
        $order = Nicehash::findOne((int) Yii::$app->request->get('id'));
        if ($order) {
            $order->active = true;
            $order->save(false);
        }
        return $this->redirect(Yii::$app->request->referrer ?: ['/nicehash']);
    }

    public function actionStop(): \yii\web\Response
    {
        $order = Nicehash::findOne((int) Yii::$app->request->get('id'));
        if ($order) {
            $order->active = false;
            $order->save(false);
        }
        return $this->redirect(Yii::$app->request->referrer ?: ['/nicehash']);
    }
}
