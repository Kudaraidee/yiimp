<?php
/** @var yii\web\View $this       */
/** @var bool         $isAdmin    */
/** @var string       $controller */
/** @var string       $action     */
/** @var bool         $hasAddress */

use yii\helpers\Html;

$vu = Yii::$app->ViewUtils;

$route = $controller . '/' . $action;

// Returns true when the nav item's URL matches the current page.
// '/?address=' is the wallet URL — active only when an address param is present.
// '/site/index'  (Home)  — active only when no address param.
$active = function (string $url) use ($route, $hasAddress): bool {
    if (strpos($url, '/?address=') !== false) {
        return $route === 'site/index' && $hasAddress;
    }
    $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');
    if ($path === '') return false;
    if (strpos($path, '/') === false) $path .= '/index';
    if ($path === 'site/index') return $route === 'site/index' && !$hasAddress;
    return $path === $route;
};

$publicNav = [
    ['home',      'Home',      '/site/index'],
    ['pool',      'Pool',      '/site/mining'],
    ['wallet',    'Wallet',    '/?address='],
    ['graphs',    'Graphs',    '/stats'],
    ['miners',    'Miners',    '/site/miners'],
    ['api',       'API',       '/site/api'],
    ['explorer',  'Explorers', '/explorer'],
];
$adminNav = [
    ['dashboard',     'Dashboard',  '/admin/dashboard'],
    ['wallets',       'Wallets',    '/admin/coinwallets'],
    ['coins',         'Coins',      '/admin/coinlist'],
    ['exchange',      'Exchange',   '/admin/exchange'],
    ['balances',      'Balances',   '/admin/balances'],
    ['users',         'Users',      '/admin/user'],
    ['workers',       'Workers',    '/admin/worker'],
    ['version',       'Version',    '/admin/version'],
    ['earnings',      'Earnings',   '/admin/earning'],
    ['payments',      'Payments',   '/admin/payments'],
    ['botnets',       'Botnets',    '/admin/botnets'],
    ['monsters',      'Big Miners', '/admin/monsters'],
    ['jobs',          'Jobs',       '/jobs/index'],
];
?>
<ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview"
    role="menu" data-accordion="false">

    <li class="nav-header text-uppercase px-3 py-1 small opacity-50">Pool</li>
    <?php foreach ($publicNav as [$icon, $label, $url]): ?>
    <li class="nav-item">
        <a href="<?= Html::encode($url) ?>" class="nav-link<?= $active($url) ? ' active' : '' ?>">
            <?= $vu->icon($icon, 'nav-icon') ?><p><?= Html::encode($label) ?></p>
        </a>
    </li>
    <?php endforeach ?>

    <?php if (defined('YIIMP_PUBLIC_BENCHMARK') && YIIMP_PUBLIC_BENCHMARK): ?>
    <li class="nav-item">
        <a href="/bench" class="nav-link<?= $active('/bench') ? ' active' : '' ?>">
            <?= $vu->icon('benchmark', 'nav-icon') ?><p>Benchmarks</p>
        </a>
    </li>
    <?php endif ?>

    <?php if (defined('YIIMP_RENTAL') && YIIMP_RENTAL): ?>
    <li class="nav-item">
        <a href="/renting" class="nav-link<?= $active('/renting') ? ' active' : '' ?>">
            <?= $vu->icon('rental', 'nav-icon') ?><p>Rental</p>
        </a>
    </li>
    <?php endif ?>

    <?php if ($isAdmin): ?>
    <li class="nav-header text-uppercase px-3 py-1 small opacity-50 mt-2">Admin</li>
    <?php foreach ($adminNav as [$icon, $label, $url]): ?>
    <li class="nav-item">
        <a href="<?= Html::encode($url) ?>" class="nav-link<?= $active($url) ? ' active' : '' ?>">
            <?= $vu->icon($icon, 'nav-icon') ?><p><?= Html::encode($label) ?></p>
        </a>
    </li>
    <?php endforeach ?>

    <?php if (defined('YIIMP_RENTAL') && YIIMP_RENTAL): ?>
    <li class="nav-item">
        <a href="/renting/admin" class="nav-link<?= $active('/renting/admin') ? ' active' : '' ?>">
            <?= $vu->icon('renting-admin', 'nav-icon') ?><p>Renting Admin</p>
        </a>
    </li>
    <?php endif ?>

    <?php if (defined('YIIMP_USE_NICEHASH_API') && YIIMP_USE_NICEHASH_API): ?>
    <li class="nav-item">
        <a href="/nicehash" class="nav-link<?= $active('/nicehash') ? ' active' : '' ?>">
            <?= $vu->icon('nicehash', 'nav-icon') ?><p>NiceHash</p>
        </a>
    </li>
    <?php endif ?>
    <?php endif ?>

</ul>
