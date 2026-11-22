<?php

/**
 * Coin details + project links card for /site/block.
 *
 * Rendered statically above the AJAX block list so it does not flicker on the
 * 60s refresh. Uses existing Coins.link_* columns; no DB changes required.
 */

use yii\helpers\Html;

if (!$coin) return;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$conv       = Yii::$app->ConversionUtils;

$symbol = $coin->getOfficialSymbol() ?: $coin->symbol;
$icon   = $coin->imageUrl();

// Only allow plain http(s) URLs coming from admin-editable fields.
$safeUrl = static function ($value): ?string {
    $value = trim((string) $value);
    if ($value === '' || !preg_match('#^https?://#i', $value)) {
        return null;
    }
    return $value;
};

$linkDefs = [
    ['link_site',        'home.webp',        'Website',     'globe'],
    ['link_explorer',    'blockchain.webp',  'Explorer',    'search'],
    ['link_github',      'Github.png',       'GitHub',      'github'],
    ['link_twitter',     'Twitter.png',      'Twitter',     'twitter'],
    ['link_discord',     'discord.png',      'Discord',     'message-circle'],
    ['link_bitcointalk', 'bitcointalk.webp', 'Bitcointalk', 'message-square'],
    ['link_exchange',    'exchange.png',     'Exchange',    'arrow-left-right'],
    ['link_facebook',    'Facebook.png',     'Facebook',    'facebook'],
];

$links = [];
foreach ($linkDefs as [$field, $img, $label, $icon]) {
    $url = $safeUrl(isset($coin->$field) ? $coin->$field : null);
    if ($url === null) continue;
    $links[] = ['url' => $url, 'img' => $img, 'label' => $label, 'icon' => $icon, 'external' => true];
}

$homeBase = rtrim((string) Yii::$app->homeUrl, '/');
$links[] = [
    'url'      => $homeBase . '/explorer/peers?id=' . (int) $coin->id,
    'img'      => 'nodes16.png',
    'label'    => 'Nodes',
    'icon'     => 'network',
    'external' => false,
];

$donation = trim((string) $coin->donation_address);
$donationShort = strlen($donation) > 34
    ? substr($donation, 0, 12) . '…' . substr($donation, -8)
    : $donation;
$faucet = (bool) $coin->usefaucet;

// ── Specs ──────────────────────────────────────────────────────────────────────
$reward = (float) $coin->reward;
$mul    = (float) $coin->reward_mul;
if ($mul > 0) $reward *= $mul;
$rewardText = $reward > 0
    ? rtrim(rtrim(number_format($reward, 4, '.', ''), '0'), '.') . ' ' . $symbol
    : '—';

$blockTime = (int) $coin->block_time > 0 ? (int) $coin->block_time . ' s' : '—';
$maturity  = (int) $coin->mature_blocks > 0 ? (int) $coin->mature_blocks . ' blocks' : '—';
$height    = (int) $coin->block_height > 0 ? number_format((int) $coin->block_height) : '—';
if ((int) $coin->target_height > 0) {
    $height .= ' / ' . number_format((int) $coin->target_height);
}

$netHash = Yii::$app->YiimpUtils->display_network_hashrate($coin);
$netHashText = ($netHash !== null && $netHash > 0)
    ? $conv->Itoa2((float) $netHash, 2) . 'h/s'
    : '—';

$market = trim((string) $coin->market);
if ($market === '' || in_array(strtolower($market), ['unknown', 'best'], true)) {
    $market = '—';
}

$price = (float) $coin->price;
$priceText = $price > 0 ? $conv->bitcoinvaluetoa($price) . ' BTC' : '—';

$specs = [
    ['Algorithm',    (string) ($coin->algo ?: '—')],
    ['Block time',   $blockTime],
    ['Reward',       $rewardText],
    ['Maturity',     $maturity],
    ['Height',       $height],
    ['Difficulty',   (string) $conv->round_difficulty((float) $coin->difficulty)],
    ['Network hash', $netHashText],
    ['Market',       $market],
    ['Price',        $priceText],
];

$chips = [
    ['label' => strtoupper((string) ($coin->algo ?: '—')), 'class' => 'blue'],
];
if ($coin->enable && $coin->auto_ready) {
    $chips[] = ['label' => 'mining', 'class' => 'green'];
} elseif ($coin->enable) {
    $chips[] = ['label' => 'enabled', 'class' => 'green'];
} elseif ($coin->watch) {
    $chips[] = ['label' => 'watch', 'class' => 'gray'];
}
$chips[] = ['label' => 'coin #' . (int) $coin->id, 'class' => 'gray'];

// ── Legacy ─────────────────────────────────────────────────────────────────────
if ($isLegacy): ?>
<style>
#coin-details-card {
    border: 1px solid #d8e2ef !important; border-radius: 7px !important;
    box-shadow: 0 1px 2px rgba(20,40,80,.04); overflow: hidden; margin-bottom: 1rem;
}
#coin-details-card > .main-left-title {
    display: flex !important; align-items: center; justify-content: space-between; gap: 10px;
    min-height: 34px; padding: 9px 12px !important;
    background: #f8fafc !important; border-bottom: 1px solid #e8eef6;
    color: #26385a; font-size: 13px !important; font-weight: 800 !important; line-height: 1.3;
}
#coin-details-card .cd-head-main { display: flex; align-items: center; gap: 9px; min-width: 0; }
#coin-details-card .cd-ico {
    position: relative; flex: 0 0 34px; height: 34px; border-radius: 8px; overflow: hidden;
    display: grid; place-items: center; color: #fff; font-size: 9px; font-weight: 800;
    background: linear-gradient(135deg, #4f79c8, #2c5aa0);
}
#coin-details-card .cd-ico img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; background: #fff; }
#coin-details-card .cd-title { font-size: 13px; font-weight: 800; color: #26385a; }
#coin-details-card .cd-symbol { font-size: 10px; font-weight: 600; color: #8a94a6; }
#coin-details-card .cd-chips { display: flex; gap: 5px; flex-wrap: wrap; justify-content: flex-end; }
#coin-details-card .cd-chip {
    display: inline-block; border-radius: 999px; padding: 2px 8px;
    font-size: 9px; font-weight: 700; line-height: 14px; white-space: nowrap;
    background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;
}
#coin-details-card .cd-chip.blue { background: #eef4ff; color: #2c5aa0; border-color: #d7e5ff; }
#coin-details-card .cd-chip.green { background: #e8f7ee; color: #187d46; border-color: #ccebd8; }
#coin-details-card .cd-body { display: grid; grid-template-columns: minmax(0,1.1fr) minmax(0,1fr); }
#coin-details-card .cd-section { padding: 12px; min-width: 0; }
#coin-details-card .cd-section + .cd-section { border-left: 1px solid #eef2f7; }
#coin-details-card .cd-section-title {
    font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
    color: #8a94a6; margin-bottom: 8px;
}
#coin-details-card .cd-specs {
    display: grid; grid-template-columns: auto minmax(0,1fr); gap: 5px 14px; margin: 0;
}
#coin-details-card .cd-specs dt { font-size: 10px; color: #8a94a6; }
#coin-details-card .cd-specs dd {
    margin: 0; font-size: 10.5px; font-weight: 700; color: #2b3d5b;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    overflow-wrap: anywhere;
}
#coin-details-card .cd-links { display: flex; flex-wrap: wrap; gap: 6px; }
#coin-details-card .cd-link {
    display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px;
    border: 1px solid #d7e5ff; border-radius: 999px; background: #f8faff;
    color: #2c5aa0; font-size: 10px; font-weight: 700; text-decoration: none; white-space: nowrap;
}
#coin-details-card .cd-link:hover { background: #eef4ff; border-color: #2c5aa0; color: #1d4383; }
#coin-details-card .cd-link img { width: 12px; height: 12px; object-fit: contain; }
#coin-details-card .cd-none { font-size: 10px; color: #98a3b5; }
#coin-details-card .cd-foot {
    display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;
    padding: 7px 12px; background: #fcfdff; border-top: 1px solid #eef2f7;
    font-size: 9px; color: #98a3b5;
}
#coin-details-card .cd-donation {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; overflow-wrap: anywhere;
}
#coin-details-card .cd-copy {
    margin-left: 6px; border: 1px solid #d7e5ff; border-radius: 999px;
    background: #eef4ff; color: #2c5aa0; font-size: 9px; font-weight: 700;
    padding: 1px 7px; cursor: pointer;
}
@media(max-width:820px){
    #coin-details-card .cd-body { grid-template-columns: 1fr; }
    #coin-details-card .cd-section + .cd-section { border-left: 0; border-top: 1px solid #eef2f7; }
    #coin-details-card .cd-chips { display: none; }
}
</style>
<div id='coin-details-card' class='main-left-box'>
    <div class='main-left-title'>
        <span class='cd-head-main'>
            <span class='cd-ico'><?= Html::encode(strtoupper(substr((string) $symbol, 0, 3))) ?><?php if ($icon !== ''): ?><img src="<?= Html::encode($icon) ?>" alt="" onerror="this.remove()"><?php endif ?></span>
            <span><span class='cd-title'><?= Html::encode($coin->name) ?></span> <span class='cd-symbol'>(<?= Html::encode($symbol) ?>)</span></span>
        </span>
        <span class='cd-chips'>
            <?php foreach ($chips as $chip): ?>
                <span class='cd-chip <?= $chip['class'] ?>'><?= Html::encode($chip['label']) ?></span>
            <?php endforeach ?>
        </span>
    </div>
    <div class='cd-body'>
        <div class='cd-section'>
            <div class='cd-section-title'>Coin specs</div>
            <dl class='cd-specs'>
                <?php foreach ($specs as [$label, $value]): ?>
                    <dt><?= Html::encode($label) ?></dt><dd><?= Html::encode($value) ?></dd>
                <?php endforeach ?>
            </dl>
        </div>
        <div class='cd-section'>
            <div class='cd-section-title'>Project links</div>
            <div class='cd-links'>
                <?php if (empty($links)): ?>
                    <span class='cd-none'>No links configured</span>
                <?php else: ?>
                    <?php foreach ($links as $link): ?>
                        <?php $attrs = $link['external'] ? " target='_blank' rel='noopener noreferrer'" : '' ?>
                        <a class='cd-link' href="<?= Html::encode($link['url']) ?>"<?= $attrs ?> title="<?= Html::encode($link['label'] . ' — ' . $link['url']) ?>">
                            <img src='/images/<?= Html::encode($link['img']) ?>' alt=""><?= Html::encode($link['label']) ?>
                        </a>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>
    </div>
    <div class='cd-foot'>
        <span class='cd-donation'>Donation: <?= $donation ? Html::encode($donationShort) . "<button type='button' class='cd-copy' id='cd-copy-donation' data-address='" . Html::encode($donation) . "'>copy</button>" : '—' ?></span>
        <span>Faucet: <?= $faucet ? 'enabled' : '—' ?> · data: pool DB + RPC cache 60s</span>
    </div>
</div>

<?php elseif (!$isTailwind): ?>
<div class="card shadow-sm mb-3" id="coin-details-card">
    <div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2 min-w-0">
            <?php if ($icon !== ''): ?>
                <img src="<?= Html::encode($icon) ?>" width="24" height="24" class="rounded" style="object-fit:contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
            <strong class="small text-truncate"><?= Html::encode($coin->name) ?></strong>
            <span class="text-muted small">(<?= Html::encode($symbol) ?>)</span>
        </div>
        <div class="d-flex flex-wrap gap-1 justify-content-end">
            <?php foreach ($chips as $chip): ?>
                <span class="badge bg-light text-dark border"><?= Html::encode($chip['label']) ?></span>
            <?php endforeach ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="row g-0">
            <div class="col-lg-7 border-end p-3">
                <h6 class="text-uppercase text-muted small fw-bold">Coin specs</h6>
                <dl class="row mb-0 small">
                    <?php foreach ($specs as [$label, $value]): ?>
                        <dt class="col-5 col-sm-4 text-muted fw-normal"><?= Html::encode($label) ?></dt>
                        <dd class="col-7 col-sm-8 mb-1 font-monospace text-break fw-semibold"><?= Html::encode($value) ?></dd>
                    <?php endforeach ?>
                </dl>
            </div>
            <div class="col-lg-5 p-3">
                <h6 class="text-uppercase text-muted small fw-bold">Project links</h6>
                <div class="d-flex flex-wrap gap-1">
                    <?php if (empty($links)): ?>
                        <span class="text-muted small">No links configured</span>
                    <?php else: ?>
                        <?php foreach ($links as $link): ?>
                            <a href="<?= Html::encode($link['url']) ?>"
                               <?= $link['external'] ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                               class="d-inline-flex align-items-center gap-1 px-2 py-0.5 rounded-pill border small text-decoration-none"
                               title="<?= Html::encode($link['label'] . ' — ' . $link['url']) ?>">
                                <i class="bi bi-<?= Html::encode($link['icon']) ?>"></i><?= Html::encode($link['label']) ?>
                            </a>
                        <?php endforeach ?>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer small text-muted d-flex justify-content-between flex-wrap gap-2">
        <span class="font-monospace text-break">Donation: <?= $donation ? Html::encode($donationShort) . " <button type='button' class='btn btn-sm btn-outline-secondary py-0 px-1' id='cd-copy-donation' data-address='" . Html::encode($donation) . "'>copy</button>" : '—' ?></span>
        <span>Faucet: <?= $faucet ? 'enabled' : '—' ?> · data: pool DB + RPC cache 60s</span>
    </div>
</div>

<?php else: ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4" id="coin-details-card">
    <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-2">
        <div class="flex items-center gap-2 min-w-0">
            <?php if ($icon !== ''): ?>
                <img src="<?= Html::encode($icon) ?>" width="24" height="24" class="rounded object-contain shrink-0" onerror="this.style.display='none'" alt="">
            <?php endif ?>
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate"><?= Html::encode($coin->name) ?></span>
            <span class="text-xs text-gray-400">(<?= Html::encode($symbol) ?>)</span>
        </div>
        <div class="flex flex-wrap gap-1 justify-end">
            <?php foreach ($chips as $chip): ?>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"><?= Html::encode($chip['label']) ?></span>
            <?php endforeach ?>
        </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-12">
        <div class="lg:col-span-7 p-4 lg:border-r border-gray-100 dark:border-gray-700">
            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2">Coin specs</h3>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                <?php foreach ($specs as [$label, $value]): ?>
                    <dt class="text-gray-400"><?= Html::encode($label) ?></dt>
                    <dd class="font-mono font-medium text-gray-700 dark:text-gray-200 break-words"><?= Html::encode($value) ?></dd>
                <?php endforeach ?>
            </dl>
        </div>
        <div class="lg:col-span-5 p-4">
            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2">Project links</h3>
            <div class="flex flex-wrap gap-1.5">
                <?php if (empty($links)): ?>
                    <span class="text-xs text-gray-400">No links configured</span>
                <?php else: ?>
                    <?php foreach ($links as $link): ?>
                        <a href="<?= Html::encode($link['url']) ?>"
                           <?= $link['external'] ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                           title="<?= Html::encode($link['label'] . ' — ' . $link['url']) ?>"
                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors no-underline">
                            <i data-lucide="<?= Html::encode($link['icon']) ?>" class="w-3 h-3 shrink-0"></i><?= Html::encode($link['label']) ?>
                        </a>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>
    </div>
    <div class="px-4 py-2 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30 flex justify-between flex-wrap gap-2 text-xs text-gray-400">
        <span class="font-mono break-words">Donation: <?= $donation ? Html::encode($donationShort) . " <button type='button' class='ml-1 px-1.5 py-0.5 rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700' id='cd-copy-donation' data-address='" . Html::encode($donation) . "'>copy</button>" : '—' ?></span>
        <span>Faucet: <?= $faucet ? 'enabled' : '—' ?> · data: pool DB + RPC cache 60s</span>
    </div>
</div>
<?php endif ?>

<?php
Yii::$app->ViewUtils->JavascriptReady("
(function () {
    var btn = document.getElementById('cd-copy-donation');
    if (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-address') || '';
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta);
            }
            var old = btn.textContent;
            btn.textContent = 'copied';
            setTimeout(function () { btn.textContent = old; }, 1200);
        });
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
})();
");
