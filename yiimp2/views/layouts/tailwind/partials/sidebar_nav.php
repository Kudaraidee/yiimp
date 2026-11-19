<?php
/** @var yii\web\View $this       */
/** @var bool         $isAdmin    */
/** @var string       $controller */
/** @var string       $action     */
/** @var bool         $hasAddress */

use yii\helpers\Html;

$vu = Yii::$app->ViewUtils;

$route = $controller . '/' . $action;

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

$navSection = fn(string $label) =>
    '<p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">'
    . Html::encode($label) . '</p>';

$navLink = fn(string $icon, string $label, string $url, bool $isActive = false) =>
    '<a href="' . Html::encode($url) . '" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium '
    . ($isActive
        ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300'
        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700')
    . '">'
    . $vu->icon($icon, 'w-4 h-4 shrink-0')
    . Html::encode($label)
    . '</a>';

$publicNav = [
    ['home',     'Home',      '/site/index'],
    ['pool',     'Pool',      '/site/mining'],
    ['wallet',   'Wallet',    '/?address='],
    ['graphs',   'Graphs',    '/stats'],
    ['miners',   'Miners',    '/site/miners'],
    ['api',      'API',       '/site/api'],
    ['explorer', 'Explorers', '/explorer'],
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
<?= $navSection('Pool') ?>
<?php foreach ($publicNav as [$icon, $label, $url]):
    echo $navLink($icon, $label, $url, $active($url));
endforeach ?>

<?php if (defined('YIIMP_PUBLIC_BENCHMARK') && YIIMP_PUBLIC_BENCHMARK):
    echo $navLink('benchmark', 'Benchmarks', '/bench', $active('/bench'));
endif ?>

<?php if (defined('YIIMP_RENTAL') && YIIMP_RENTAL):
    echo $navLink('rental', 'Rental', '/renting', $active('/renting'));
endif ?>

<?php if ($isAdmin): ?>
<?= $navSection('Admin') ?>
<?php foreach ($adminNav as [$icon, $label, $url]):
    echo $navLink($icon, $label, $url, $active($url));
endforeach ?>

<?php if (defined('YIIMP_RENTAL') && YIIMP_RENTAL):
    echo $navLink('renting-admin', 'Renting Admin', '/renting/admin', $active('/renting/admin'));
endif ?>

<?php if (defined('YIIMP_USE_NICEHASH_API') && YIIMP_USE_NICEHASH_API):
    echo $navLink('nicehash', 'NiceHash', '/nicehash', $active('/nicehash'));
endif ?>
<?php endif ?>
