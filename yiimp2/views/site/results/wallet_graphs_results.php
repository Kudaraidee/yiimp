<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use app\models\Workers;

$user = Yii::$app->YiimpUtils->getuserbyaddress(Yii::$app->getRequest()->getQueryParam('address'));
if(!$user) return;

echo <<<end
<div class="main-left-box">
<div class="main-left-title">Last 24 Hours Hashrate: $user->username</div>
<div class="main-left-inner"><br>
end;

foreach(Yii::$app->YiimpUtils->get_algos() as $algo)
{
	$delay = time()-24*60*60;

    $user_shares = Yii::$app->cache->get("wallet_hashuser-$user->id-$algo");
    if (!$user_shares) {
        $user_shares = (new \yii\db\Query())
                ->select(['count(*)'])
                ->from('hashuser')
                ->where([ 'userid' => $user->id , 'algo' => $algo ])
				->andWhere(['>','time',$delay])
                ->scalar();
        Yii::$app->cache->set("wallet_hashuser-$user->id-$algo", $user_shares);
    }

	$minercount = Workers::find()
					->where(['userid'=>$user->id , 'algo'=> $algo])
					->count();
	if(!$user_shares && !$minercount) continue;

	echo <<<end
<input type=hidden id=$algo class='graph_algo'>
<div id='graph_results_$algo' class='chart-container-240'></div><br>
end;
}

echo "</div></div><br>";






