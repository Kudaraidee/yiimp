<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;

use app\models\Mining;

AppAsset::register($this);

$pageTitle = empty($this->title) ? YIIMP_SITE_NAME : YIIMP_SITE_NAME." - ".$this->title;

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($pageTitle) ?></title>
    <?php $this->head() ?>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<header id="header">
    <?php
    NavBar::begin([
        'brandLabel' => YIIMP_SITE_NAME,
        'brandUrl' => Yii::$app->homeUrl,
        'options' => ['class' => 'navbar-expand-md navbar-dark bg-dark fixed-top']
    ]);

    $mining = Mining::find()->one();
	$nextpayment = $mining ? date('H:i T', $mining->last_payout+YIIMP_PAYMENTS_FREQ) : '';
	$eta = $mining ? ($mining->last_payout+YIIMP_PAYMENTS_FREQ) - time() : 0;
	$eta_mn = $eta ? 'in '.round($eta / 60).' minutes' : '';

    $items_navbar = [
            ['label' => 'Home', 'url' => ['/site/index']],
            ['label' => 'Pool', 'url' => ['/site/mining']],
            ['label' => 'Wallet', 'url' => ['/?address=']],
            ['label' => 'Graphs', 'url' => ['/stats'], 'active' => in_array(\Yii::$app->controller->id, ['stats']),],
            ['label' => 'Miners', 'url' => ['/site/miners']],
            ['label' => 'API', 'url' => ['/site/api']],
            ['label' => 'Explorers', 'url' => ['/explorer'], 'active' => in_array(\Yii::$app->controller->id, ['explorer']),],
    ];

    if (defined('YIIMP_PUBLIC_BENCHMARK') && YIIMP_PUBLIC_BENCHMARK) {
        $items_navbar[] = [
            'label'  => 'Benchmarks',
            'url'    => ['/bench'],
            'active' => \Yii::$app->controller->id === 'bench',
        ];
    }

    if (defined('YIIMP_RENTAL') && YIIMP_RENTAL) {
        $items_navbar[] = [
            'label'  => 'Rental',
            'url'    => ['/renting'],
            'active' => \Yii::$app->controller->id === 'renting',
        ];
    }

    if ((!is_null(Yii::$app->user->identity)) && (Yii::$app->user->identity->is_admin)) {
        $admin_navbar = [
            '&nbsp;&nbsp;&nbsp;&nbsp;',
            ['label' => 'Dashboard', 'url' => ['/admin/dashboard']],
            ['label' => 'Wallets',   'url' => ['/admin/coinwallets']],
            ['label' => 'Coins',     'url' => ['/admin/coinlist']],
            ['label' => 'Jobs',      'url' => ['/jobs/index'],
             'active' => \Yii::$app->controller->id === 'jobs'],
            ...(defined('YIIMP_RENTAL') && YIIMP_RENTAL ? [[
                'label'  => 'Renting',
                'url'    => ['/renting/admin'],
                'active' => \Yii::$app->controller->id === 'renting',
            ]] : []),
            ...(defined('YIIMP_USE_NICEHASH_API') && YIIMP_USE_NICEHASH_API ? [[
                'label'  => 'NiceHash',
                'url'    => ['/nicehash'],
                'active' => \Yii::$app->controller->id === 'nicehash',
            ]] : []),
             '<li class="nav-item">'
                    . Html::beginForm(['/admin/logout'])
                    . Html::submitButton(
                        'Logout (' . Yii::$app->user->identity->username . ')',
                        ['class' => 'nav-link btn btn-link logout']
                    )
                    . Html::endForm()
                    . '</li>'
        ];
    }
    else {
         $admin_navbar = [
            ['label' => 'Login', 'url' => ['/admin/login'], 'active' => in_array(\Yii::$app->controller->id, ['admin']),]
         ];
    }

    $items = array_merge($items_navbar, $admin_navbar);
    echo Nav::widget([
        'options' => ['class' => 'navbar-nav'],
        'items' => $items
    ]);
    echo Html::tag('div', 'Next Payout: '.$nextpayment, 
                    ['class' => 'navbar-text ms-auto' ]);
    NavBar::end();
   ?>
</header>

<main id="main" class="flex-shrink-0" role="main">
    <div class="container">
        <?php if (!empty($this->params['breadcrumbs'])): ?>
            <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
        <?php endif ?>
        <?= Alert::widget() ?>
        <?= $content ?>
    </div>
</main>

<footer id="footer" class="mt-auto py-3 bg-light">
    <div class="container">
        <div class="row text-muted">
            <div class="text-center">&copy; <?php echo date('Y').' '.YIIMP_SITE_NAME ?> - <a href="https://github.com/tpfuemp/yiimp">Open source Project</a></p></div>
        </div>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
