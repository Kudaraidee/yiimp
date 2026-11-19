<?php

/** @var yii\web\View $this */
/** @var string       $content */

use app\assets\AdminLteAsset;
use app\widgets\Alert;
use app\models\Mining;
use yii\bootstrap5\Html;
use yii\bootstrap5\Breadcrumbs;

AdminLteAsset::register($this);

// Bootstrap Icons (bundled with AdminLTE 4 npm; loaded from CDN here)
$this->registerCssFile('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11/font/bootstrap-icons.min.css');

$pageTitle  = empty($this->title) ? YIIMP_SITE_NAME : YIIMP_SITE_NAME . ' - ' . $this->title;
$darkMode   = !empty($_COOKIE['yiimp_darkmode']);
$bsTheme    = $darkMode ? 'dark' : 'light';
$isAdmin    = !is_null(Yii::$app->user->identity) && Yii::$app->user->identity->is_admin;
$controller = Yii::$app->controller->id;

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
<html lang="<?= Yii::$app->language ?>" data-bs-theme="<?= $bsTheme ?>">
<head>
    <title><?= Html::encode($pageTitle) ?></title>
    <?php $this->head() ?>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<?php $this->beginBody() ?>

<div class="app-wrapper">

    <!-- ── Top navbar ──────────────────────────────────────────────────── -->
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                        <i class="bi bi-list fs-5"></i>
                    </a>
                </li>
                <li class="nav-item d-none d-md-block">
                    <a class="nav-link fw-bold" href="<?= Yii::$app->homeUrl ?>">
                        <?= Html::encode(YIIMP_SITE_NAME) ?>
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto">
                <!-- Next payout -->
                <?php if ($nextpayment): ?>
                <li class="nav-item d-none d-sm-block">
                    <span class="nav-link text-muted small">
                        Next payout: <?= Html::encode($nextpayment) ?> (<?= Html::encode($etaLabel) ?>)
                    </span>
                </li>
                <?php endif ?>

                <!-- Dark mode toggle -->
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="toggleDarkMode()" title="Toggle dark mode">
                        <i id="dark-mode-icon" class="bi bi-<?= $darkMode ? 'sun' : 'moon' ?>"></i>
                    </a>
                </li>

                <!-- User menu -->
                <?php if ($isAdmin): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?= Html::encode(Yii::$app->user->identity->username) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?= Html::beginForm(['/admin/logout']) ?>
                        <li><button class="dropdown-item" type="submit">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </button></li>
                        <?= Html::endForm() ?>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/login">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Login
                    </a>
                </li>
                <?php endif ?>
            </ul>
        </div>
    </nav>

    <!-- ── Sidebar ─────────────────────────────────────────────────────── -->
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand d-flex align-items-center px-3 py-3">
            <a href="<?= Yii::$app->homeUrl ?>" class="text-white fw-bold text-decoration-none">
                <i class="bi bi-grid-3x3-gap-fill me-2"></i><?= Html::encode(YIIMP_SITE_NAME) ?>
            </a>
        </div>

        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <?= Yii::$app->ViewUtils->renderSidebarNav() ?>
            </nav>
        </div>
    </aside>

    <!-- ── Main content ────────────────────────────────────────────────── -->
    <main class="app-main">
        <?php if (!empty($this->title)): ?>
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0"><?= Html::encode($this->title) ?></h3>
                    </div>
                    <div class="col-sm-6">
                        <?php if (!empty($this->params['breadcrumbs'])): ?>
                            <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif ?>

        <div class="app-content">
            <div class="container-fluid">
                <?= Alert::widget() ?>
                <?= $content ?>
            </div>
        </div>
    </main>

    <!-- ── Footer ──────────────────────────────────────────────────────── -->
    <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">
            <a href="https://github.com/tpfuemp/yiimp">Open source Project</a>
        </div>
        <strong>&copy; <?= date('Y') ?> <?= Html::encode(YIIMP_SITE_NAME) ?></strong>
    </footer>

</div><!-- /.app-wrapper -->

<script>
function toggleDarkMode() {
    const html   = document.documentElement;
    const isDark = html.getAttribute('data-bs-theme') === 'dark';
    html.setAttribute('data-bs-theme', isDark ? 'light' : 'dark');
    document.cookie = isDark
        ? 'yiimp_darkmode=; path=/; max-age=0'
        : 'yiimp_darkmode=1; path=/; max-age=31536000';
    document.getElementById('dark-mode-icon').className =
        'bi bi-' + (isDark ? 'moon' : 'sun');
}

document.addEventListener('DOMContentLoaded', function () {
    var wrapper = document.querySelector('.sidebar-wrapper');
    if (wrapper) {
        var saved = sessionStorage.getItem('sidebar-scroll');
        if (saved) wrapper.scrollTop = parseInt(saved, 10) || 0;
        wrapper.addEventListener('scroll', function () {
            sessionStorage.setItem('sidebar-scroll', wrapper.scrollTop);
        });
    }
});
</script>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
