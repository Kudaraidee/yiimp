<?php

/**
 * Public coin catalog — a searchable card grid of all minable coins.
 *
 * One self-contained page: filtering/sorting is client-side so it stays
 * instant with hundreds of coins and never needs an AJAX endpoint.
 *
 * @var yii\web\View      $this
 * @var app\models\Coins[] $coins
 */

use yii\helpers\Html;

$this->title = 'Coins';

$util = Yii::$app->YiimpUtils;
$conv = Yii::$app->ConversionUtils;

$algoSet = [];
foreach ($coins as $coin) {
    $algoSet[(string) $coin->algo] = true;
}
$algos = array_keys($algoSet);
sort($algos, SORT_NATURAL | SORT_FLAG_CASE);

// Project links shown on each card, using the icons already shipped for coin details.
$linkDefs = [
    ['link_site',        '/images/home.webp',        'Website'],
    ['link_explorer',    '/images/blockchain.webp',  'Explorer'],
    ['link_github',      '/images/Github.png',       'GitHub'],
    ['link_discord',     '/images/discord.png',      'Discord'],
    ['link_twitter',     '/images/Twitter.png',      'Twitter'],
    ['link_exchange',    '/images/exchange.png',     'Exchange'],
    ['link_bitcointalk', '/images/bitcointalk.webp', 'Bitcointalk'],
];

$safeUrl = static function ($value): string {
    $value = trim((string) $value);
    return preg_match('#^https?://#i', $value) ? $value : '';
};

$cards = '';
foreach ($coins as $coin) {
    $symbol  = (string) $coin->getOfficialSymbol();
    $name    = (string) $coin->name;
    $algo    = (string) $coin->algo;
    $port    = (int) $util->getAlgoPort($algo);
    $color   = Html::encode((string) $util->getAlgoColors($algo));
    $initial = strtoupper(substr($symbol !== '' ? $symbol : $name, 0, 3));
    $img     = !empty($coin->image)
        ? "<img src='" . Html::encode($coin->image) . "' alt='' loading='lazy' onerror=\"this.remove()\">"
        : '';

    $reward     = round((float) $coin->reward, 4);
    $difficulty = $conv->Itoa2($coin->difficulty, 3);
    $height     = number_format((float) $coin->block_height, 0, '.', ' ');
    $payBadge   = $coin->auto_exchange
        ? "<span class='cc-pay' title='Rewards are automatically exchanged'>⇄ Auto</span>"
        : "<span class='cc-pay manual' title='Rewards are paid in {$symbol}'>Paid in " . Html::encode($symbol) . "</span>";

    $links = '';
    foreach ($linkDefs as [$field, $icon, $label]) {
        $url = $safeUrl($coin->$field ?? '');
        if ($url === '') continue;
        $links .= "<a href='" . Html::encode($url) . "' target='_blank' rel='noopener noreferrer' title='" . Html::encode($label) . "'>"
            . "<img src='" . $icon . "' alt='" . Html::encode($label) . "' loading='lazy'></a>";
    }

    $search = mb_strtolower($name . ' ' . $symbol . ' ' . $algo, 'UTF-8');

    $cards .= "<article class='cc-card' data-algo='" . Html::encode($algo) . "' data-search='" . Html::encode($search) . "'"
        . " data-name='" . Html::encode(mb_strtolower($name, 'UTF-8')) . "'"
        . " data-reward='" . Html::encode((string) $reward) . "'>"
        . "<div class='cc-card-top'>"
        . "<span class='cc-ico' style='--cc-color:{$color}'>{$initial}{$img}</span>"
        . "<div class='cc-id'>"
        . "<a class='cc-name' href='" . Html::encode($coin->blockUrl) . "'>" . Html::encode(mb_substr($name, 0, 28)) . "</a>"
        . "<span class='cc-symbol'>" . Html::encode($symbol) . "</span>"
        . "</div></div>"
        . "<div class='cc-tags'>"
        . "<span class='cc-algo' style='--cc-color:{$color}'><i></i>" . Html::encode($algo) . "</span>"
        . "<span class='cc-port'>port {$port}</span>"
        . $payBadge
        . "</div>"
        . "<div class='cc-stats'>"
        . "<div><span class='cc-label'>Height</span><b class='cc-val'>" . Html::encode($height) . "</b></div>"
        . "<div><span class='cc-label'>Difficulty</span><b class='cc-val'>" . Html::encode($difficulty) . "</b></div>"
        . "<div><span class='cc-label'>Reward</span><b class='cc-val'>" . Html::encode((string) $reward) . " " . Html::encode($symbol) . "</b></div>"
        . "</div>"
        . "<div class='cc-card-foot'>"
        . "<span class='cc-links'>" . ($links !== '' ? $links : '<span class="cc-nolinks">no project links</span>') . "</span>"
        . "<a class='cc-view' href='" . Html::encode($coin->blockUrl) . "'>Blocks &amp; stats →</a>"
        . "</div>"
        . "</article>";
}
?>

<style>
#coin-catalog {
    --cc-border: #d8e2ef;
    --cc-ink: #26385a;
    --cc-muted: #8a94a6;
    --cc-blue: #2c5aa0;
    padding: 0;
    overflow: hidden;
}
#coin-catalog .cc-head {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    padding: 12px 14px; background: #f8fafc; border-bottom: 1px solid #e8eef6;
}
#coin-catalog .cc-heading { display: flex; align-items: baseline; gap: 9px; flex-wrap: wrap; }
#coin-catalog .cc-title { font-size: 13px; font-weight: 800; color: var(--cc-ink); letter-spacing: .01em; }
#coin-catalog .cc-sub { font-size: 10px; color: var(--cc-muted); }
#coin-catalog .cc-controls { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-left: auto; }
#coin-catalog .cc-input, #coin-catalog .cc-select {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 9px; border: 1px solid #dfe5ec; border-radius: 7px;
    background: #fff; color: var(--cc-muted);
}
#coin-catalog .cc-input:focus-within, #coin-catalog .cc-select:focus-within { border-color: #a9c4ea; }
#coin-catalog .cc-input input {
    width: 175px; border: 0; outline: 0; background: transparent;
    font: 11px/1.4 inherit; color: var(--cc-ink);
}
#coin-catalog .cc-select select {
    border: 0; outline: 0; background: transparent; color: var(--cc-ink);
    font: 11px/1.4 inherit; max-width: 150px;
}
#coin-catalog .cc-count {
    font-size: 10px; color: var(--cc-muted); font-variant-numeric: tabular-nums; white-space: nowrap;
}
#coin-catalog .cc-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 10px; padding: 12px;
    max-height: 620px; overflow: auto; overscroll-behavior: contain;
    scrollbar-width: thin;
}
#coin-catalog .cc-card {
    display: flex; flex-direction: column; gap: 9px; min-width: 0;
    padding: 11px 12px 9px; background: #fff;
    border: 1px solid var(--cc-border); border-radius: 8px;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
}
#coin-catalog .cc-card:hover { border-color: #a9c4ea; box-shadow: 0 3px 10px rgba(20,40,80,.09); }
#coin-catalog .cc-card[hidden] { display: none; }
#coin-catalog .cc-card-top { display: flex; align-items: center; gap: 9px; min-width: 0; }
#coin-catalog .cc-ico {
    position: relative; flex: 0 0 34px; height: 34px; border-radius: 8px; overflow: hidden;
    display: grid; place-items: center; background: var(--cc-color, #2c5aa0);
    color: #fff; font-size: 8px; font-weight: 800; letter-spacing: -.02em;
}
#coin-catalog .cc-ico img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; background: #fff; }
#coin-catalog .cc-id { display: flex; flex-direction: column; min-width: 0; }
#coin-catalog .cc-name {
    font-size: 12px; font-weight: 700; color: var(--cc-ink); text-decoration: none;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
#coin-catalog .cc-name:hover { color: var(--cc-blue); }
#coin-catalog .cc-symbol { font-size: 9px; font-weight: 700; color: var(--cc-muted); letter-spacing: .05em; }
#coin-catalog .cc-tags { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
#coin-catalog .cc-algo, #coin-catalog .cc-port, #coin-catalog .cc-pay {
    display: inline-flex; align-items: center; gap: 4px; border-radius: 3px; padding: 1px 5px;
    font-size: 9px; font-weight: 700; line-height: 15px; white-space: nowrap;
}
#coin-catalog .cc-algo { color: var(--cc-blue); background: #eef4ff; border: 1px solid #d7e5ff; }
#coin-catalog .cc-algo i { width: 6px; height: 6px; border-radius: 50%; background: var(--cc-color, #888); }
#coin-catalog .cc-port { color: #64748b; background: #f4f6f9; border: 1px solid #e6ebf2; font-variant-numeric: tabular-nums; }
#coin-catalog .cc-pay { color: #187d46; background: #e8f7ee; border: 1px solid #ccebd8; }
#coin-catalog .cc-pay.manual { color: #a86400; background: #fff7e8; border-color: #ffe2b8; }
#coin-catalog .cc-stats {
    display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px;
    padding: 8px 0; border-top: 1px solid #eef2f7; border-bottom: 1px solid #eef2f7;
}
#coin-catalog .cc-stats > div { min-width: 0; }
#coin-catalog .cc-label { display: block; font-size: 8px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--cc-muted); }
#coin-catalog .cc-val {
    display: block; margin-top: 2px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 10px; font-variant-numeric: tabular-nums; color: #2b3d5b;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
#coin-catalog .cc-card-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
#coin-catalog .cc-links { display: inline-flex; align-items: center; gap: 4px; flex-wrap: wrap; }
#coin-catalog .cc-links img { width: 13px; height: 13px; object-fit: contain; opacity: .65; }
#coin-catalog .cc-links a:hover img { opacity: 1; }
#coin-catalog .cc-nolinks { font-size: 9px; color: #b7c0cd; }
#coin-catalog .cc-view { font-size: 9px; font-weight: 700; color: var(--cc-blue); text-decoration: none; white-space: nowrap; }
#coin-catalog .cc-view:hover { text-decoration: underline; }
#coin-catalog .cc-empty {
    display: none; padding: 28px 14px; text-align: center;
    color: var(--cc-muted); font-size: 12px;
}
#coin-catalog .cc-foot {
    padding: 8px 14px; border-top: 1px solid #eef2f7; background: #fcfdff;
    color: var(--cc-muted); font-size: 9px;
}
@media (max-width: 640px) {
    #coin-catalog .cc-controls { margin-left: 0; width: 100%; }
    #coin-catalog .cc-input { flex: 1 1 auto; }
    #coin-catalog .cc-input input { width: 100%; }
    #coin-catalog .cc-grid { grid-template-columns: 1fr; }
}
</style>

<div id="coin-catalog" class="main-left-box">
    <div class="cc-head">
        <div class="cc-heading">
            <span class="cc-title">Coins</span>
            <span class="cc-sub">List of coins available for mining</span>
        </div>
        <div class="cc-controls">
            <label class="cc-input">
                <span aria-hidden="true">⌕</span>
                <input type="search" id="cc-search" placeholder="Search coins…" autocomplete="off"
                       aria-label="Search coins">
            </label>
            <label class="cc-select">
                <select id="cc-algo" aria-label="Filter by algorithm">
                    <option value="">All algos (<?= count($algos) ?>)</option>
                    <?php foreach ($algos as $algo): ?>
                        <option value="<?= Html::encode($algo) ?>"><?= Html::encode($algo) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <label class="cc-select">
                <select id="cc-sort" aria-label="Sort coins">
                    <option value="name">Sort: Name</option>
                    <option value="algo">Sort: Algo</option>
                    <option value="reward">Sort: Reward</option>
                </select>
            </label>
            <span class="cc-count" id="cc-count"><?= count($coins) ?> coins</span>
        </div>
    </div>

    <div class="cc-grid" id="cc-grid">
        <?= $cards ?>
    </div>

    <div class="cc-empty" id="cc-empty">No coins match that search.</div>
    <div class="cc-foot">Rewards are paid automatically — wallet address is your username. Select a coin for blocks, difficulty and stratum details.</div>
</div>

<?php
Yii::$app->ViewUtils->JavascriptReady(<<<'JS'
(function () {
    var root   = document.getElementById('coin-catalog');
    if (!root) return;
    var search = document.getElementById('cc-search');
    var algo   = document.getElementById('cc-algo');
    var sort   = document.getElementById('cc-sort');
    var grid   = document.getElementById('cc-grid');
    var count  = document.getElementById('cc-count');
    var empty  = document.getElementById('cc-empty');
    var cards  = Array.prototype.slice.call(grid.children);

    function apply() {
        var q = (search.value || '').toLowerCase().trim();
        var a = algo.value || '';
        var shown = 0;
        cards.forEach(function (card) {
            var ok = (!q || card.dataset.search.indexOf(q) !== -1) &&
                     (!a || card.dataset.algo === a);
            card.hidden = !ok;
            if (ok) shown++;
        });
        count.textContent = shown + ' / ' + cards.length + ' coins';
        empty.style.display = shown ? 'none' : 'block';
    }

    function doSort() {
        var mode = sort.value;
        cards.sort(function (x, y) {
            if (mode === 'reward') return parseFloat(y.dataset.reward || 0) - parseFloat(x.dataset.reward || 0);
            if (mode === 'algo') return (x.dataset.algo || '').localeCompare(y.dataset.algo || '');
            return (x.dataset.name || '').localeCompare(y.dataset.name || '');
        });
        cards.forEach(function (card) { grid.appendChild(card); });
    }

    search.addEventListener('input', apply);
    search.addEventListener('search', apply);
    algo.addEventListener('change', apply);
    sort.addEventListener('change', doSort);
})();
JS
);
