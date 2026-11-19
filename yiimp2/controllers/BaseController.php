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
}
