<?php

use yii\helpers\Html;
use app\models\Coins;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

$algo  = Yii::$app->session->get('yaamp-algo');
$title = "Coin Information ({$algo})";

$query = Coins::find()->where(['enable' => 1, 'visible' => 1, 'auto_ready' => 1]);
if ($algo !== 'all') $query->andWhere(['algo' => $algo]);
$list = $query->orderBy(['index_avg' => SORT_DESC])->all();

// ── Link definition: [field, image, label, lucide-icon] ───────────────────────
$linkDefs = [
    ['link_bitcointalk', '/images/bitcointalk.webp', 'Bitcointalk', 'message-square'],
    ['link_site',        '/images/home.webp',        'Website',     'globe'],
    ['link_discord',     '/images/discord.png',      'Discord',     'message-circle'],
    ['link_explorer',    '/images/blockchain.webp',  'Explorer',    'search'],
    ['link_github',      '/images/Github.png',       'GitHub',      'github'],
    ['link_exchange',    '/images/exchange.png',      'Exchange',    'arrow-left-right'],
    ['link_twitter',     '/images/Twitter.png',       'Twitter',     'twitter'],
];

// ── Card open ─────────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo "<div class='main-left-box'>";
    echo "<div class='main-left-title'>" . Html::encode($title) . "</div>";
    echo "<div class='main-left-inner'>";
    echo '<style>td.symb,th.symb{width:50px;max-width:50px;text-align:right;}td.symb{font-size:.8em;}</style>';
    echo "<table class='dataGrid2'><thead><tr><th></th><th>Name</th><th>Information</th></tr></thead><tbody>";
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm mb-3">';
    echo '<div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-coin text-secondary"></i>';
    echo '<strong class="small">' . Html::encode($title) . '</strong>';
    echo '</div><div class="card-body p-0"><div class="overflow-auto">';
    echo '<table class="table table-sm table-bordered mb-0">';
    echo '<thead class="table-light"><tr><th style="width:24px"></th><th class="small">Name</th><th class="small">Links</th></tr></thead><tbody>';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="coins" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">' . Html::encode($title) . '</span>';
    echo '</div><div class="overflow-x-auto">';
    echo '<table class="w-full text-xs">';
    echo '<thead><tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">';
    echo '<th class="px-3 py-2.5 w-8"></th><th class="px-3 py-2.5 text-left">Name</th><th class="px-3 py-2.5 text-left">Links</th>';
    echo '</tr></thead><tbody>';
}

// ── Rows ──────────────────────────────────────────────────────────────────────
foreach ($list as $coin) {
    $id   = $coin->id;
    $name = substr($coin->name, 0, 20);

    if ($isLegacy) {
        echo '<tr class="ssrow">';
        echo '<td width="18"><img width="16" src="' . Html::encode($coin->image) . '"></td>';
        echo '<td><a href="/site/block?id=' . $id . '"><b>' . Html::encode($name) . '</b></a></td>';
        echo '<td>';
        foreach ($linkDefs as [$field, $img, $label]) {
            if ($coin->$field)
                echo '<a href="' . Html::encode($coin->$field) . '">'
                   . '<img width="16" src="' . $img . '">' . Html::encode($label)
                   . '</a>&nbsp;&nbsp;&nbsp;';
        }
        echo '<a href="/explorer/peers?id=' . $id . '"><img width="16" src="/images/nodes16.png">Nodes</a>';
        echo '</td></tr>';

    } elseif (!$isTailwind) {
        $coinImg = !empty($coin->image)
            ? "<img src='" . Html::encode($coin->image) . "' width='16' style='object-fit:contain' onerror='this.style.display=\"none\"'>"
            : '';
        echo '<tr>';
        echo '<td>' . $coinImg . '</td>';
        echo '<td class="small fw-bold"><a href="/site/block?id=' . $id . '">' . Html::encode($name) . '</a></td>';
        echo '<td><div class="d-flex flex-wrap gap-1 py-1">';
        foreach ($linkDefs as [$field, $img, $label]) {
            if ($coin->$field)
                echo '<a href="' . Html::encode($coin->$field) . '" target="_blank" rel="noopener"'
                   . ' class="d-inline-flex align-items-center gap-1 px-2 py-0.5 text-nowrap'
                   . ' rounded border border-secondary-subtle text-secondary small text-decoration-none hover:bg-light">'
                   . '<img src="' . $img . '" width="12" height="12" style="object-fit:contain">'
                   . Html::encode($label) . '</a>';
        }
        echo '<a href="/explorer/peers?id=' . $id . '"'
           . ' class="d-inline-flex align-items-center gap-1 px-2 py-0.5 text-nowrap'
           . ' rounded border border-secondary-subtle text-secondary small text-decoration-none">'
           . '<img src="/images/nodes16.png" width="12" height="12">Nodes</a>';
        echo '</div></td></tr>';

    } else {
        $coinImg = !empty($coin->image)
            ? "<img src='" . Html::encode($coin->image) . "' width='16' height='16' class='rounded object-contain' onerror='this.style.display=\"none\"'>"
            : '';
        echo '<tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10 transition-colors border-b border-gray-100 dark:border-gray-700/50">';
        echo '<td class="px-3 py-2">' . $coinImg . '</td>';
        echo '<td class="px-3 py-2 font-medium whitespace-nowrap">';
        echo '<a href="/site/block?id=' . $id . '" class="text-gray-800 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">'
           . Html::encode($name) . '</a>';
        echo '</td>';
        echo '<td class="px-3 py-2"><div class="flex flex-wrap gap-1">';
        foreach ($linkDefs as [$field, $img, $label, $icon]) {
            if ($coin->$field)
                echo '<a href="' . Html::encode($coin->$field) . '" target="_blank" rel="noopener"'
                   . ' class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs'
                   . ' border border-gray-200 dark:border-gray-600'
                   . ' bg-gray-50 dark:bg-gray-700/50'
                   . ' text-gray-600 dark:text-gray-300'
                   . ' hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors no-underline">'
                   . '<i data-lucide="' . $icon . '" class="w-3 h-3 shrink-0"></i>'
                   . Html::encode($label) . '</a>';
        }
        echo '<a href="/explorer/peers?id=' . $id . '"'
           . ' class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs'
           . ' border border-gray-200 dark:border-gray-600'
           . ' bg-gray-50 dark:bg-gray-700/50'
           . ' text-gray-600 dark:text-gray-300'
           . ' hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors no-underline">'
           . '<i data-lucide="network" class="w-3 h-3 shrink-0"></i>Nodes</a>';
        echo '</div></td></tr>';
    }
}

// ── Card close ────────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo '</tbody></table></div></div>';
} elseif (!$isTailwind) {
    echo '</tbody></table></div></div></div>';
} else {
    echo '</tbody></table></div></div>';
    Yii::$app->ViewUtils->JavascriptReady("
        if (typeof lucide !== 'undefined') lucide.createIcons();
    ");
}
