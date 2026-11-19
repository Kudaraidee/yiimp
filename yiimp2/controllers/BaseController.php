<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;

/**
 * Base controller for all web controllers.
 *
 * Resolves the active layout scheme from LayoutManager so that individual
 * controllers never need to set $this->layout themselves.
 */
class BaseController extends Controller
{
    public function init(): void
    {
        parent::init();
        $this->layout = Yii::$app->LayoutManager->layoutPath();
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Yii1 CommonController seeds the session with the default algo.
        // Without this, yiimp2 partials (miners/coins/hashrate) show
        // "No algo selected." for first-time/anonymous visitors.
        if (Yii::$app->has('session')) {
            $session = Yii::$app->session;
            if (!$session->get('yaamp-algo')) {
                $default = defined('YIIMP_DEFAULT_ALGO') ? YIIMP_DEFAULT_ALGO : 'ethash';
                $session->set('yaamp-algo', $default);
            }
        }

        return true;
    }
}
