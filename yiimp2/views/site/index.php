<?php

/** @var yii\web\View $this */

use app\models\Coins;
use app\models\Stratums;
use yii\helpers\Html;

$this->registerJsFile('@web/js/auto_refresh.js', ['depends' => [yii\web\JqueryAsset::className()]]);

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$homeUrl    = Yii::$app->homeUrl;

$minPayout  = floatval(YIIMP_PAYMENTS_MINI);
$minSunday  = $minPayout / 10;
$payoutFreq = (YIIMP_PAYMENTS_FREQ / 3600) . ' hours';

// ── Coin list for stratum generator ───────────────────────────────────────────
$coinList = Coins::find()
    ->where(['enable' => 1, 'visible' => 1, 'auto_ready' => 1])
    ->orderBy(['algo' => SORT_ASC])
    ->all();

// ── Build coin <option> HTML (shared) ─────────────────────────────────────────
$coinOptions = '';
if (!$coinList) {
    $coinOptions = '<option disabled>No Coins Available</option>';
} else {
    $lastAlgo = '';
    foreach ($coinList as $coin) {
        $name    = Html::encode(substr($coin->name, 0, 18));
        $symbol  = $coin->getOfficialSymbol();
        $algo    = $coin->algo;
        $noExch  = isset($coin->auto_exchange) && $coin->auto_exchange == 0;
        $portRow = Stratums::find()->where(['symbol' => $symbol, 'algo' => $algo])->one();
        $port    = $portRow ? $portRow->port : '0000';
        $mc      = $noExch ? ",mc={$symbol}" : '';

        if ($algo !== $lastAlgo) {
            $coinOptions .= '<option disabled>' . Html::encode($algo) . '</option>';
            $lastAlgo = $algo;
        }
        $coinOptions .= '<option value="' . Html::encode($symbol) . '"'
            . ' data-port="' . Html::encode($port) . '"'
            . ' data-algo="-a ' . Html::encode($algo) . '"'
            . ' data-symbol="' . Html::encode($symbol) . '"'
            . ' data-extra="-p c=' . Html::encode($symbol . $mc) . '">'
            . $name . ' (' . Html::encode($symbol) . ')</option>';
    }
}

// ── Pool links ─────────────────────────────────────────────────────────────────
$links = [
    'API'          => $homeUrl . 'site/api',
    'Difficulty'   => $homeUrl . 'site/diff',
];
if (YIIMP_PUBLIC_BENCHMARK) $links['Benchmarks']    = $homeUrl . 'site/benchmarks';
if (YIIMP_ALLOW_EXCHANGE)   $links['Algo Switching'] = $homeUrl . 'site/multialgo';

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="resume_update_button"
     style="color:#444;background:#ffd;border:1px solid #eea;padding:10px;
            margin:15px 20px;cursor:pointer;display:none;"
     onclick="auto_page_resume()" align="center">
    <b>Auto refresh is paused — click to resume</b>
</div>

<table cellspacing="20" width="100%"><tr><td valign="top" width="50%">

<div class="main-left-box">
<div class="main-left-title"><?= Html::encode(YIIMP_SITE_URL) ?></div>
<div class="main-left-inner">
<ul>
<li>Welcome to <?= Html::encode(YIIMP_SITE_URL) ?>!</li>
<li>No registration required. Use your wallet address as username.</li>
<li>Payouts every <?= Html::encode($payoutFreq) ?> for balances above <b><?= $minPayout ?></b>, or <b><?= $minSunday ?></b> on Sunday.</li>
<li>Blocks distributed proportionally among valid submitted shares.</li>
</ul>
</div></div><br/>

<div class="main-left-box">
<div class="main-left-title">How to mine with <?= Html::encode(YIIMP_SITE_URL) ?></div>
<div class="main-left-inner">
<?= $this->render('_stratum_form', ['coinOptions' => $coinOptions]) ?>
</div></div><br>

<div class="main-left-box">
<div class="main-left-title"><?= Html::encode(YIIMP_SITE_URL) ?> Links</div>
<div class="main-left-inner">
<ul>
<?php foreach ($links as $label => $url): ?>
<li><b><?= Html::encode($label) ?></b> — <a href="<?= Html::encode($url) ?>"><?= Html::encode($url) ?></a></li>
<?php endforeach ?>
</ul>
</div></div><br>

<div class="main-left-box">
<div class="main-left-title">Support</div>
<div class="main-left-inner">
<ul class="social-icons">
    <li><a href="https://discord.gg/DrsrWQh3qC"><img src="/images/discord.png" height="16" alt="Discord"> Discord</a></li>
</ul>
</div></div><br>

</td><td valign="top">
<div id="pool_current_results"></div>
<div id="pool_history_results"></div>
<div id="pool_coins_info"></div>
</td></tr></table>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="resume_update_button"
     class="alert alert-warning d-flex align-items-center gap-2 mb-3"
     style="cursor:pointer;display:none!important;"
     onclick="auto_page_resume()">
    <i class="bi bi-pause-circle-fill"></i>
    <strong>Auto refresh is paused</strong> — click to resume
</div>

<div class="row gx-3">

    <!-- LEFT -->
    <div class="col-12 col-md-6">

        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 small fw-semibold"><?= Html::encode(YIIMP_SITE_URL) ?></div>
            <div class="card-body">
                <ul class="mb-0 small">
                    <li>Welcome to <strong><?= Html::encode(YIIMP_SITE_URL) ?></strong>! No registration required — use your wallet address as username.</li>
                    <li class="mt-1">Payouts every <strong><?= Html::encode($payoutFreq) ?></strong> for balances above <strong><?= $minPayout ?></strong>, or <strong><?= $minSunday ?></strong> on Sunday.</li>
                    <li class="mt-1">Blocks distributed proportionally among valid submitted shares.</li>
                </ul>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 small fw-semibold">How to mine</div>
            <div class="card-body">
                <?= $this->render('_stratum_form', ['coinOptions' => $coinOptions]) ?>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 small fw-semibold">Links</div>
            <div class="card-body py-2">
                <?php foreach ($links as $label => $url): ?>
                <div class="mb-1 small">
                    <strong><?= Html::encode($label) ?></strong>
                    <a href="<?= Html::encode($url) ?>" class="ms-2 text-muted"><?= Html::encode($url) ?></a>
                </div>
                <?php endforeach ?>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 small fw-semibold">Support</div>
            <div class="card-body py-2">
                <a href="https://discord.gg/DrsrWQh3qC" class="btn btn-sm btn-outline-secondary">
                    <img src="/images/discord.png" height="16" class="me-1" alt=""> Discord
                </a>
            </div>
        </div>

    </div>

    <!-- RIGHT -->
    <div class="col-12 col-md-6">
        <div id="pool_current_results" class="mb-3"></div>
        <div id="pool_history_results" class="mb-3"></div>
        <div id="pool_coins_info"></div>
    </div>

</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="resume_update_button"
     class="flex items-center gap-3 px-4 py-3 mb-4 rounded-xl
            bg-amber-50 dark:bg-amber-900/20
            border border-amber-200 dark:border-amber-800
            text-amber-700 dark:text-amber-300 text-sm cursor-pointer"
     style="display:none;"
     onclick="auto_page_resume()">
    <i data-lucide="pause-circle" class="w-5 h-5 shrink-0"></i>
    <strong>Auto refresh is paused</strong> — click to resume
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <!-- LEFT -->
    <div class="flex flex-col gap-4">

        <div class="rounded-xl border border-gray-200 dark:border-gray-700
                    bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                        text-sm font-semibold text-gray-800 dark:text-gray-100">
                <?= Html::encode(YIIMP_SITE_URL) ?>
            </div>
            <div class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 space-y-1.5">
                <p>Welcome to <strong class="text-gray-900 dark:text-gray-100"><?= Html::encode(YIIMP_SITE_URL) ?></strong>! No registration required — use your wallet address as username.</p>
                <p>Payouts every <strong><?= Html::encode($payoutFreq) ?></strong> for balances above <strong><?= $minPayout ?></strong>, or <strong><?= $minSunday ?></strong> on Sunday.</p>
                <p>Blocks distributed proportionally among valid submitted shares.</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700
                    bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                        text-sm font-semibold text-gray-800 dark:text-gray-100">
                How to mine
            </div>
            <div class="px-4 py-3">
                <?= $this->render('_stratum_form', ['coinOptions' => $coinOptions]) ?>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700
                    bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                        text-sm font-semibold text-gray-800 dark:text-gray-100">Links</div>
            <div class="px-4 py-3 space-y-1.5 text-sm">
                <?php foreach ($links as $label => $url): ?>
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300"><?= Html::encode($label) ?></span>
                    <a href="<?= Html::encode($url) ?>"
                       class="ml-2 text-indigo-500 hover:underline transition-colors text-xs font-mono">
                        <?= Html::encode($url) ?>
                    </a>
                </div>
                <?php endforeach ?>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700
                    bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                        text-sm font-semibold text-gray-800 dark:text-gray-100">Support</div>
            <div class="px-4 py-3">
                <a href="https://discord.gg/DrsrWQh3qC"
                   class="inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg
                          border border-gray-300 dark:border-gray-600
                          bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300
                          hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    <img src="/images/discord.png" class="h-4 w-auto" alt=""> Discord
                </a>
            </div>
        </div>

    </div>

    <!-- RIGHT -->
    <div class="flex flex-col gap-4">
        <div id="pool_current_results"></div>
        <div id="pool_history_results"></div>
        <div id="pool_coins_info"></div>
    </div>

</div>

<?php endif ?>

<script>
function page_refresh() {
    pool_current_refresh();
    pool_history_refresh();
    pool_coins_info_refresh();
}
function select_algo(algo) {
    window.location.href = '<?= $homeUrl ?>site/algo?algo=' + encodeURIComponent(algo) + '&r=/';
}
function pool_current_refresh() {
    $.get('<?= $homeUrl ?>site/current_results', '', function(d){ $('#pool_current_results').html(d); if(typeof lucide!=='undefined')lucide.createIcons(); });
}
function pool_history_refresh() {
    $.get('<?= $homeUrl ?>site/history_results', '', function(d){ $('#pool_history_results').html(d); });
}
function pool_coins_info_refresh() {
    $.get('<?= $homeUrl ?>site/coins_info', '', function(d){ $('#pool_coins_info').html(d); });
}

// ── Stratum command generator ─────────────────────────────────────────────────
function generate() {
    var stratum = document.getElementById('drop-stratum');
    var coin    = document.getElementById('drop-coin');
    var solo    = document.getElementById('drop-solo');
    var wallet  = document.getElementById('text-wallet').value.trim();
    var rig     = document.getElementById('text-rig-name').value.trim();
    var sel     = coin.options[coin.selectedIndex];

    var cmd = (sel.dataset.algo || '') + ' -o stratum+tcp://'
            + (stratum.value || '') + '<?= Html::encode(YIIMP_STRATUM_URL) ?>:'
            + (sel.dataset.port || '0000') + ' -u '
            + (wallet || 'WALLET_ADDRESS')
            + '.' + (rig || 'WORKER_NAME')
            + ' ' + (sel.dataset.extra || '')
            + (solo.value || '');

    document.getElementById('stratum-output').textContent = cmd;
}
generate();
</script>
