<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;

class StatsController extends Controller
{
	public $defaultAction='index';

	/////////////////////////////////////////////////

	public function actionIndex()
	{
		return $this->render('index');
	}

	public function actionGraph_results_1()
	{
		return $this->renderPartial('graph_results_1');
	}

	public function actionGraph_results_2()
	{
		return $this->renderPartial('graph_results_2');
	}

	public function actionGraph_results_3()
	{
		return $this->renderPartial('graph_results_3');
	}

	public function actionGraph_results_4()
	{
		return $this->renderPartial('graph_results_4');
	}

	public function actionGraph_results_5()
	{
		return $this->renderPartial('graph_results_5');
	}

	public function actionGraph_results_6()
	{
		return $this->renderPartial('graph_results_6');
	}

	public function actionGraph_results_7()
	{
		return $this->renderPartial('graph_results_7');
	}

	public function actionGraph_results_8()
	{
		return $this->renderPartial('graph_results_8');
	}

	public function actionGraph_results_9()
	{
		return $this->renderPartial('graph_results_9');
	}

}
