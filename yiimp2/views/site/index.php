<?php

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->registerJsFile('@web/js/auto_refresh.js', ['depends' => [yii\web\JqueryAsset::className()]]);

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$homeUrl    = Yii::$app->homeUrl;

$minPayout  = floatval(YIIMP_PAYMENTS_MINI);
$minSunday  = $minPayout / 10;
$payoutFreq = (YIIMP_PAYMENTS_FREQ / 3600) . ' hours';

// ── Mining command generator — same component/sorting as /site/mining ────────
/** @var array $algoNav active algo + items (with coins, ports and TLS ports) */
$algoNav  = $algoNav ?? ['active' => 'all', 'items' => []];

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
     LEGACY — compact hero · asymmetric workspace · full-width tables
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="resume_update_button"
     style="color:#444;background:#ffd;border:1px solid #eea;padding:10px;
            margin:15px 20px;cursor:pointer;display:none;"
     onclick="auto_page_resume()" align="center">
    <b>Auto refresh is paused — click to resume</b>
</div>

<div id="home-cards">

    <!-- ── Hero ────────────────────────────────────────────────────────────── -->
    <div class="main-left-box home-hero">
        <div class="main-left-title">
            <span class="hm-head"><span class="hm-dot"></span><?= Html::encode(YIIMP_SITE_URL) ?></span>
            <span class="hm-kicker">simple · anonymous · ready in a minute</span>
            <span class="hm-live"><i></i> live · 60s <span id="home-last-update">just now</span></span>
        </div>
        <div class="main-left-inner home-hero-inner">
            <div class="hero-copy">
                <span class="hero-eyebrow">START MINING IN UNDER A MINUTE</span>
                <h1>Mine with your wallet.<br>Get paid automatically.</h1>
                <p>Welcome to <strong><?= Html::encode(YIIMP_SITE_URL) ?></strong>. No account and no registration —
                   your wallet address is your username. Pick a coin, copy the command and start hashing.</p>
            </div>
            <div class="hero-facts">
                <div class="hero-fact">
                    <span class="hf-ico">✓</span>
                    <span><b>No registration</b><small>Wallet address = username</small></span>
                </div>
                <div class="hero-fact">
                    <span class="hf-ico">◷</span>
                    <span><b>Payouts every <?= Html::encode($payoutFreq) ?></b><small>Above <?= Html::encode($minPayout) ?> · <?= Html::encode($minSunday) ?> on Sunday</small></span>
                </div>
                <div class="hero-fact">
                    <span class="hf-ico">◈</span>
                    <span><b>Fair by design</b><small>Blocks split by valid shares</small></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Main workspace: generator (wide) + coins (narrow) ──────────────── -->
    <div class="home-workspace">
        <div class="hw-main">
            <?= $this->render('_mining_command', ['algoNav' => $algoNav]) ?>
        </div>
        <div class="hw-side">
            <div id="pool_coins_info" class="home-ajax"></div>
        </div>
    </div>

    <!-- ── Block stats (full width) ───────────────────────────────────────── -->
    <div id="pool_history_results" class="home-ajax home-blocks"></div>

    <!-- ── Pool links (compact footer) ────────────────────────────────────── -->
    <div class="main-left-box home-links">
        <div class="main-left-title">
            <span class="mn-head-main"><span>Pool links</span><span class="mn-sub">quick access</span></span>
            <span class="mc-badge">resources</span>
        </div>
        <div class="main-left-inner home-links-inner">
            <div class="home-link-grid">
                <?php foreach ($links as $label => $url): ?>
                <a class="home-link" href="<?= Html::encode($url) ?>">
                    <span class="hl-ico">↗</span>
                    <span class="hl-text">
                        <span class="hl-label"><?= Html::encode($label) ?></span>
                        <span class="hl-url"><?= Html::encode($url) ?></span>
                    </span>
                </a>
                <?php endforeach ?>
                <a class="home-link home-link-help" href="https://discord.gg/DrsrWQh3qC">
                    <span class="hl-ico hl-ico-discord"><img src="/images/discord.png" alt=""></span>
                    <span class="hl-text">
                        <span class="hl-label">Need help?</span>
                        <span class="hl-url">Join our Discord</span>
                    </span>
                </a>
            </div>
        </div>
    </div>

</div>


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

<div class="home-cards-alt">

    <!-- Hero -->
    <div class="card shadow-sm">
        <div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
            <strong class="small"><i class="bi bi-hdd-rack text-secondary me-1"></i><?= Html::encode(YIIMP_SITE_URL) ?></strong>
            <span class="small text-muted">simple · anonymous · ready in a minute</span>
        </div>
        <div class="card-body d-flex flex-column flex-lg-row gap-4 align-items-lg-center">
            <div class="flex-fill">
                <span class="hero-eyebrow-alt">START MINING IN UNDER A MINUTE</span>
                <h4 class="fw-bold mb-1">Mine with your wallet.<br>Get paid automatically.</h4>
                <p class="small text-muted mb-0">No account and no registration — your wallet address is your username.
                   Pick a coin, copy the command and start hashing.</p>
            </div>
            <div class="d-grid gap-2" style="min-width:260px;">
                <div class="d-flex align-items-center gap-2 border rounded px-2 py-1 bg-light">
                    <span class="badge bg-primary rounded-pill">✓</span>
                    <span class="small"><b>No registration</b><br><span class="text-muted">Wallet address = username</span></span>
                </div>
                <div class="d-flex align-items-center gap-2 border rounded px-2 py-1 bg-light">
                    <span class="badge bg-primary rounded-pill">◷</span>
                    <span class="small"><b>Payouts every <?= Html::encode($payoutFreq) ?></b><br><span class="text-muted">Above <?= Html::encode($minPayout) ?> · <?= Html::encode($minSunday) ?> on Sunday</span></span>
                </div>
                <div class="d-flex align-items-center gap-2 border rounded px-2 py-1 bg-light">
                    <span class="badge bg-primary rounded-pill">◈</span>
                    <span class="small"><b>Fair by design</b><br><span class="text-muted">Blocks split by valid shares</span></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Generator + coins -->
    <div class="row gx-3 gy-3">
        <div class="col-12 col-lg-7"><?= $this->render('_mining_command', ['algoNav' => $algoNav]) ?></div>
        <div class="col-12 col-lg-5"><div id="pool_coins_info"></div></div>
    </div>

    <!-- Block stats full width -->
    <div id="pool_history_results"></div>

    <!-- Links footer -->
    <div class="card shadow-sm">
        <div class="card-header py-2 small fw-semibold"><i class="bi bi-link-45deg text-secondary me-1"></i>Pool links</div>
        <div class="card-body py-2">
            <div class="row g-2">
                <?php foreach ($links as $label => $url): ?>
                <div class="col-6 col-md-3">
                    <a href="<?= Html::encode($url) ?>" class="d-flex align-items-center gap-2 h-100 p-2 border rounded text-decoration-none bg-light">
                        <span class="badge bg-primary rounded-pill">↗</span>
                        <span class="overflow-hidden">
                            <span class="d-block small fw-bold text-dark"><?= Html::encode($label) ?></span>
                            <span class="d-block text-muted small text-truncate"><?= Html::encode($url) ?></span>
                        </span>
                    </a>
                </div>
                <?php endforeach ?>
                <div class="col-6 col-md-3">
                    <a href="https://discord.gg/DrsrWQh3qC" class="d-flex align-items-center gap-2 h-100 p-2 border rounded text-decoration-none bg-light">
                        <img src="/images/discord.png" height="16" alt="">
                        <span class="small fw-bold text-dark">Need help?<br><span class="text-muted">Join Discord</span></span>
                    </a>
                </div>
            </div>
        </div>
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

<div class="grid grid-cols-1 md:grid-cols-12 gap-4">

    <!-- Hero -->
    <div class="md:col-span-12 rounded-xl border border-gray-200 dark:border-gray-700
                bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                    flex items-center justify-between gap-3">
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <i data-lucide="server" class="w-4 h-4 text-gray-400"></i><?= Html::encode(YIIMP_SITE_URL) ?>
            </span>
            <span class="text-xs text-gray-400 uppercase tracking-wider">simple · anonymous · ready in a minute</span>
        </div>
        <div class="px-4 py-4">
            <span class="text-[10px] font-bold tracking-widest text-indigo-500">START MINING IN UNDER A MINUTE</span>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1 mb-2">
                Mine with your wallet.<br>Get paid automatically.
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                No account and no registration — your wallet address is your username.
                Pick a coin, copy the command and start hashing.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                <div class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                    <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 font-bold text-sm shrink-0">✓</span>
                    <span class="text-xs"><b class="block text-gray-800 dark:text-gray-100">No registration</b><span class="text-gray-400">Wallet address = username</span></span>
                </div>
                <div class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                    <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 font-bold text-sm shrink-0">◷</span>
                    <span class="text-xs"><b class="block text-gray-800 dark:text-gray-100">Payouts every <?= Html::encode($payoutFreq) ?></b><span class="text-gray-400">Above <?= Html::encode($minPayout) ?> · <?= Html::encode($minSunday) ?> on Sunday</span></span>
                </div>
                <div class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                    <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 font-bold text-sm shrink-0">◈</span>
                    <span class="text-xs"><b class="block text-gray-800 dark:text-gray-100">Fair by design</b><span class="text-gray-400">Blocks split by valid shares</span></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Generator + coins -->
    <div class="md:col-span-7"><?= $this->render('_mining_command', ['algoNav' => $algoNav]) ?></div>
    <div class="md:col-span-5"><div id="pool_coins_info"></div></div>

    <!-- Block stats full width -->
    <div class="md:col-span-12"><div id="pool_history_results"></div></div>

    <!-- Links footer -->
    <div class="md:col-span-12 rounded-xl border border-gray-200 dark:border-gray-700
                bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                    text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
            <i data-lucide="link" class="w-4 h-4 text-gray-400"></i>Pool links
        </div>
        <div class="px-4 py-3 grid grid-cols-2 md:grid-cols-4 gap-2">
            <?php foreach ($links as $label => $url): ?>
            <a href="<?= Html::encode($url) ?>"
               class="flex items-center gap-2 p-2 rounded-lg border border-gray-200 dark:border-gray-700
                      bg-gray-50 dark:bg-gray-900/40 hover:bg-gray-100 dark:hover:bg-gray-700
                      transition-colors no-underline min-w-0">
                <span class="flex items-center justify-center w-6 h-6 rounded-md
                             bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300
                             text-xs font-bold shrink-0">↗</span>
                <span class="min-w-0">
                    <span class="block text-xs font-semibold text-gray-800 dark:text-gray-100"><?= Html::encode($label) ?></span>
                    <span class="block text-[10px] text-gray-400 truncate"><?= Html::encode($url) ?></span>
                </span>
            </a>
            <?php endforeach ?>
            <a href="https://discord.gg/DrsrWQh3qC"
               class="flex items-center gap-2 p-2 rounded-lg border border-gray-200 dark:border-gray-700
                      bg-gray-50 dark:bg-gray-900/40 hover:bg-gray-100 dark:hover:bg-gray-700
                      transition-colors no-underline min-w-0">
                <img src="/images/discord.png" class="h-5 w-5 object-contain shrink-0" alt="">
                <span class="min-w-0">
                    <span class="block text-xs font-semibold text-gray-800 dark:text-gray-100">Need help?</span>
                    <span class="block text-[10px] text-gray-400 truncate">Join our Discord</span>
                </span>
            </a>
        </div>
    </div>

</div>

<?php endif ?>

<script>
var HOME_AUTO_MAX = 7 * 24 * 60 * 60 * 1000;
var home_last_refresh = Date.now();

function page_refresh() {
    home_last_refresh = Date.now();
    home_tick();
    pool_history_refresh();
    pool_coins_info_refresh();
}
function select_algo(algo) {
    window.location.href = '<?= $homeUrl ?>site/algo?algo=' + encodeURIComponent(algo) + '&r=/';
}
function pool_history_refresh() {
    $.get('<?= $homeUrl ?>site/history_results', '', function(d){ $('#pool_history_results').html(d); });
}
function pool_coins_info_refresh() {
    $.get('<?= $homeUrl ?>site/coins_info', '', function(d){ $('#pool_coins_info').html(d); });
}

function home_tick() {
    if (typeof window.auto_max_time !== 'undefined' && window.auto_max_time !== HOME_AUTO_MAX) {
        window.auto_max_time = HOME_AUTO_MAX;
        var resume = document.getElementById('resume_update_button');
        if (resume) resume.style.display = 'none';
    }
    var age  = Math.floor((Date.now() - home_last_refresh) / 1000);
    var next = Math.max(0, 60 - age);
    var label = document.getElementById('home-last-update');
    if (label) label.textContent = age < 2 ? 'just now' : age + 's ago · next ' + next + 's';
}

function home_start() {
    setInterval(home_tick, 1000);
    home_tick();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', home_start);
} else {
    home_start();
}
</script>

<style>
/* ══ Homepage chrome — consistent with /site/mining ═════════════════════════ */
#home-cards { color: #334155; display: flex; flex-direction: column; gap: 14px; }
#home-cards .main-left-box {
    border: 1px solid #d8e2ef !important;
    border-radius: 7px !important;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
    overflow: hidden;
    background: #fff;
    margin-bottom: 0;
}
#home-cards .main-left-title {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-height: 34px;
    padding: 9px 12px !important;
    background: #f8fafc !important;
    border-bottom: 1px solid #e8eef6;
    color: #26385a;
    font-size: 13px !important;
    font-weight: 800 !important;
    line-height: 1.3;
}
#home-cards .mn-head-main {
    display: flex; align-items: baseline; gap: 9px; flex-wrap: wrap; min-width: 0;
}
#home-cards .mn-sub { font-size: 10px; color: #8a94a6; font-weight: 500; }
#home-cards .mc-badge {
    display: inline-block; padding: 2px 7px; border-radius: 999px;
    background: #eef4ff; border: 1px solid #d7e5ff; color: #2c5aa0;
    font-size: 9px; font-weight: 700; line-height: 14px; white-space: nowrap;
}

/* ── Hero ─────────────────────────────────────────────────────────────────── */
#home-cards .hm-head { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
#home-cards .hm-dot {
    width: 7px; height: 7px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15); flex: 0 0 auto;
}
#home-cards .hm-kicker {
    font-size: 10px; color: #8a94a6; font-weight: 500;
    text-transform: uppercase; letter-spacing: .05em;
}
#home-cards .hm-live {
    margin-left: auto; white-space: nowrap; font-size: 11px; color: #5b6675;
    display: inline-flex; align-items: center; gap: 6px;
}
#home-cards .hm-live i {
    width: 7px; height: 7px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15);
}
#home-cards .hm-live #home-last-update {
    color: #98a3b5; font-size: 10px; font-variant-numeric: tabular-nums;
}
#home-cards .home-hero-inner {
    padding: 16px 18px !important;
    display: flex; flex-direction: column; gap: 14px;
}
#home-cards .hero-eyebrow {
    display: block; margin-bottom: 6px;
    font-size: 9px; font-weight: 800; letter-spacing: .14em;
    text-transform: uppercase; color: #2c5aa0;
}
#home-cards .hero-copy h1 {
    margin: 0 0 6px; font-size: 21px; line-height: 1.3;
    font-weight: 800; color: #1e2f4d;
}
#home-cards .hero-copy p {
    margin: 0; font-size: 12px; line-height: 1.6; color: #64748b;
    max-width: 760px;
}
#home-cards .hero-copy strong { color: #2c5aa0; }
#home-cards .hero-facts {
    display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px;
}
#home-cards .hero-fact {
    display: flex; align-items: center; gap: 10px; min-width: 0;
    padding: 9px 11px; border: 1px solid #e8eef6;
    border-radius: 6px; background: #fcfdff;
}
#home-cards .hf-ico {
    flex: 0 0 26px; width: 26px; height: 26px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 7px; background: #eef4ff; color: #2c5aa0;
    font-size: 13px; font-weight: 800;
}
#home-cards .hero-fact b { display: block; font-size: 11px; color: #26385a; }
#home-cards .hero-fact small { display: block; font-size: 10px; color: #8a94a6; }

/* ── Workspace: 7 / 5 split ──────────────────────────────────────────────── */
#home-cards .home-workspace {
    display: grid; grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
    gap: 14px; align-items: start;
}
#home-cards .home-workspace > div {
    min-width: 0; display: flex; flex-direction: column;
}
#home-cards .home-workspace > div > * { width: 100%; }
#home-cards .home-workspace .main-left-inner { padding: 0 !important; }
#home-cards .home-workspace .mc-gen { padding: 12px; }

/* ── Links footer ────────────────────────────────────────────────────────── */
#home-cards .home-links-inner { padding: 12px !important; }
#home-cards .home-link-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
}
#home-cards .home-link {
    display: flex; align-items: center; gap: 9px; min-width: 0;
    padding: 9px 10px; border: 1px solid #e8eef6; border-radius: 6px;
    background: #fcfdff; text-decoration: none; color: #26385a;
}
#home-cards .home-link:hover { background: #f7faff; border-color: #d7e5ff; }
#home-cards .hl-ico {
    flex: 0 0 22px; width: 22px; height: 22px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 6px; background: #eef4ff; color: #2c5aa0;
    font-size: 12px; font-weight: 800;
}
#home-cards .hl-ico-discord { background: #f4f2ff; overflow: hidden; }
#home-cards .hl-ico-discord img { width: 14px; height: 14px; object-fit: contain; }
#home-cards .hl-text { min-width: 0; }
#home-cards .hl-label { display: block; font-size: 11px; font-weight: 700; }
#home-cards .hl-url {
    display: block; max-width: 210px; font-size: 9px; color: #98a3b5;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

/* ── AJAX partials + tables ──────────────────────────────────────────────── */
#home-cards .home-ajax { width: 100%; display: flex; flex-direction: column; }
#home-cards .home-ajax > br { display: none; }
#home-cards .home-ajax > .main-left-box { width: 100%; }
#home-cards .home-ajax:empty::after {
    content: "Loading block stats…";
    display: flex; align-items: center; justify-content: center;
    min-height: 180px; border: 1px dashed #d8e2ef; border-radius: 7px;
    color: #98a3b5; font-size: 11px;
}
#home-cards .dataGrid2 {
    width: 100% !important; border-collapse: collapse;
    font-size: 11px; color: #334155;
}
#home-cards .dataGrid2 th {
    padding: 7px 9px !important; background: #fff !important;
    border-bottom: 1px solid #e8eef6; color: #8a94a6;
    font-size: 9px !important; font-weight: 700 !important;
    letter-spacing: .05em; text-transform: uppercase; white-space: nowrap;
}
#home-cards .dataGrid2 td {
    padding: 8px 9px !important; border-bottom: 1px solid #eef2f7;
    vertical-align: middle;
}
#home-cards .dataGrid2 tbody tr:last-child td { border-bottom: 0; }
#home-cards .dataGrid2 tbody tr:hover { background: #f7faff; }
#home-cards .dataGrid2 img { border-radius: 3px; object-fit: contain; }

/* ── Keep long tables contained (50–100+ coins) ─────────────────────────────── */
#home-cards .hw-side .main-left-inner,
#home-cards .home-blocks .main-left-inner {
    overflow: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
}
#home-cards .hw-side .main-left-inner   { max-height: 420px; }
#home-cards .home-blocks .main-left-inner { max-height: 360px; }
#home-cards .home-ajax .dataGrid2 thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff !important;
    box-shadow: 0 1px 0 #e8eef6;
}

.hero-eyebrow-alt {
    display: block; margin-bottom: 4px; font-size: 9px;
    font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #2c5aa0;
}
.home-cards-alt { display: flex; flex-direction: column; gap: 16px; }

@media (max-width: 991.98px) {
    #home-cards .home-workspace { grid-template-columns: 1fr; }
}
@media (max-width: 767.98px) {
    #home-cards .hero-facts { grid-template-columns: 1fr; }
    #home-cards .home-hero-inner { padding: 14px !important; }
    #home-cards .hero-copy h1 { font-size: 18px; }
    #home-cards .hm-kicker { display: none; }
}
</style>
