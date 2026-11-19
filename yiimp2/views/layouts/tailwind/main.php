<?php

/** @var yii\web\View $this */
/** @var string       $content */

use app\assets\TailwindAsset;
use app\widgets\Alert;
use app\models\Mining;
use yii\helpers\Html;

TailwindAsset::register($this);

$pageTitle  = empty($this->title) ? YIIMP_SITE_NAME : YIIMP_SITE_NAME . ' - ' . $this->title;
$darkMode   = !empty($_COOKIE['yiimp_darkmode']);
$isAdmin    = !is_null(Yii::$app->user->identity) && Yii::$app->user->identity->is_admin;

$mining      = Mining::find()->one();
$nextpayment = $mining ? date('H:i T', $mining->last_payout + YIIMP_PAYMENTS_FREQ) : '';
$eta         = $mining ? ($mining->last_payout + YIIMP_PAYMENTS_FREQ) - time() : 0;
$etaLabel    = $eta > 60 ? 'in ' . round($eta / 60) . ' min' : 'soon';

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>"<?= $darkMode ? ' class="dark"' : '' ?>>
<head>
    <title><?= Html::encode($pageTitle) ?></title>
    <?php $this->head() ?>
    <style>
    /* Bootstrap 5 pagination compat — works in both Play CDN and compiled mode */
    .pagination{display:flex;flex-wrap:wrap;list-style:none;padding:0;margin:0}
    .page-item{display:flex}
    .page-link{display:block;padding:.25rem .6rem;font-size:.8125rem;line-height:1.5;
               text-decoration:none;color:#6366f1;background:#fff;
               border:1px solid #d1d5db;transition:background .15s,color .15s}
    .dark .page-link{background:#1f2937;border-color:#4b5563;color:#a5b4fc}
    .page-item:not(:first-child)>.page-link{margin-left:-1px}
    .page-item:first-child>.page-link{border-radius:.375rem 0 0 .375rem}
    .page-item:last-child>.page-link{border-radius:0 .375rem .375rem 0}
    .page-item.active>.page-link{background:#6366f1;border-color:#6366f1;color:#fff;z-index:1}
    .dark .page-item.active>.page-link{background:#4f46e5;border-color:#4f46e5}
    .page-item.disabled>.page-link{color:#9ca3af;pointer-events:none}
    .dark .page-item.disabled>.page-link{color:#6b7280}
    .page-link:hover:not(.page-item.disabled>.page-link){background:#eef2ff}
    .dark .page-link:hover{background:#374151}
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 overflow-hidden">
<?php $this->beginBody() ?>

<!-- ── Fixed header ──────────────────────────────────────────────────────── -->
<header class="fixed top-0 left-0 right-0 z-40 h-14 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center px-4 gap-4">

    <!-- Hamburger (mobile) -->
    <button id="sidebar-toggle" class="lg:hidden p-1.5 rounded text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
            onclick="document.getElementById('sidebar').classList.toggle('hidden')">
        <i data-lucide="menu" class="w-5 h-5"></i>
    </button>

    <!-- Logo -->
    <a href="<?= Yii::$app->homeUrl ?>" class="font-bold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
        <?= Html::encode(YIIMP_SITE_NAME) ?>
    </a>

    <!-- Next payout -->
    <?php if ($nextpayment): ?>
    <span class="hidden sm:block text-xs text-gray-400 dark:text-gray-500">
        Next payout: <?= Html::encode($nextpayment) ?> (<?= Html::encode($etaLabel) ?>)
    </span>
    <?php endif ?>

    <div class="flex items-center gap-2 ml-auto">
        <!-- Dark mode toggle -->
        <button onclick="toggleDarkMode()"
                class="p-1.5 rounded text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                title="Toggle dark mode">
            <i data-lucide="<?= $darkMode ? 'sun' : 'moon' ?>" class="w-4 h-4"></i>
        </button>

        <!-- User / Login -->
        <?php if ($isAdmin): ?>
        <div class="relative group">
            <button class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 px-2 py-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                <i data-lucide="user-circle" class="w-4 h-4"></i>
                <?= Html::encode(Yii::$app->user->identity->username) ?>
                <i data-lucide="chevron-down" class="w-3 h-3"></i>
            </button>
            <div class="absolute right-0 mt-1 w-40 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded shadow-lg hidden group-hover:block z-50">
                <?= Html::beginForm(['/admin/logout']) ?>
                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                    <i data-lucide="log-out" class="w-4 h-4"></i>Logout
                </button>
                <?= Html::endForm() ?>
            </div>
        </div>
        <?php else: ?>
        <a href="/admin/login" class="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-indigo-600 px-2 py-1 rounded">
            <i data-lucide="log-in" class="w-4 h-4"></i>Login
        </a>
        <?php endif ?>
    </div>
</header>

<!-- ── Body layout ───────────────────────────────────────────────────────── -->
<div class="flex mt-14" style="height:calc(100vh - 3.5rem);">

    <!-- ── Sidebar ───────────────────────────────────────────────────────── -->
    <aside id="sidebar"
           class="hidden lg:flex flex-col w-60 shrink-0 bg-white dark:bg-gray-800
                  border-r border-gray-200 dark:border-gray-700 overflow-y-auto">
        <nav class="flex flex-col gap-0.5 p-3">
            <?= Yii::$app->ViewUtils->renderSidebarNav() ?>
        </nav>
    </aside>

    <!-- ── Content + footer ──────────────────────────────────────────────── -->
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">
        <main class="flex-1 overflow-y-auto p-6">

            <?php if (!empty($this->title)): ?>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-100 mb-4">
                <?= Html::encode($this->title) ?>
            </h1>
            <?php endif ?>

            <?php if (!empty($this->params['breadcrumbs'])): ?>
            <nav class="text-sm text-gray-500 dark:text-gray-400 mb-4 flex gap-1 items-center flex-wrap">
                <?php $crumbs = $this->params['breadcrumbs'];
                $last = array_pop($crumbs);
                foreach ($crumbs as $crumb):
                    $label = is_array($crumb) ? ($crumb['label'] ?? '') : $crumb;
                    $url   = is_array($crumb) ? ($crumb['url']   ?? '') : '';
                ?>
                    <?= $url ? Html::a(Html::encode($label), $url, ['class' => 'hover:text-indigo-600']) : Html::encode($label) ?>
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <?php endforeach ?>
                <span class="text-gray-700 dark:text-gray-200"><?= Html::encode(is_array($last) ? ($last['label'] ?? $last) : $last) ?></span>
            </nav>
            <?php endif ?>

            <?= Alert::widget() ?>
            <?= $content ?>
        </main>

        <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-3 px-6 text-center text-xs text-gray-400">
            &copy; <?= date('Y') ?> <?= Html::encode(YIIMP_SITE_NAME) ?> &mdash;
            <a href="https://github.com/tpfuemp/yiimp" class="hover:text-indigo-500">Open source Project</a>
        </footer>
    </div>

</div><!-- /.flex body -->

<script>
function toggleDarkMode() {
    const html   = document.documentElement;
    const isDark = html.classList.contains('dark');
    html.classList.toggle('dark', !isDark);
    document.cookie = isDark
        ? 'yiimp_darkmode=; path=/; max-age=0'
        : 'yiimp_darkmode=1; path=/; max-age=31536000';
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Lucide icons
    if (typeof lucide !== 'undefined') lucide.createIcons();

    // Persist sidebar scroll position across page navigations
    var sidebar = document.getElementById('sidebar');
    if (sidebar) {
        var saved = sessionStorage.getItem('sidebar-scroll');
        if (saved) sidebar.scrollTop = parseInt(saved, 10) || 0;
        sidebar.addEventListener('scroll', function () {
            sessionStorage.setItem('sidebar-scroll', sidebar.scrollTop);
        });
    }
});
</script>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
