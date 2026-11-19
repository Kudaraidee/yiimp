<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\helpers\Html;

class ViewUtils extends Component
{
    // ── Icon map ──────────────────────────────────────────────────────────────
    //
    // Canonical name → per-library identifiers.
    // Bootstrap Icons (AdminLTE) and Lucide (Tailwind) use different naming
    // conventions; this table is the single source of truth.
    //
    // Usage:  Yii::$app->ViewUtils->icon('home')
    //         Yii::$app->ViewUtils->icon('logout', 'me-2')   // with extra CSS class
    private const ICON_MAP = [
        // ── Public navigation ─────────────────────────────────────────────
        'home'          => ['bi' => 'bi-house',                 'lucide' => 'home'],
        'pool'          => ['bi' => 'bi-cpu',                   'lucide' => 'cpu'],
        'wallet'        => ['bi' => 'bi-wallet2',               'lucide' => 'wallet'],
        'graphs'        => ['bi' => 'bi-graph-up',              'lucide' => 'trending-up'],
        'miners'        => ['bi' => 'bi-people',                'lucide' => 'users'],
        'api'           => ['bi' => 'bi-code-slash',            'lucide' => 'code-2'],
        'explorer'      => ['bi' => 'bi-search',                'lucide' => 'telescope'],
        'benchmark'     => ['bi' => 'bi-speedometer2',          'lucide' => 'gauge'],
        'rental'        => ['bi' => 'bi-lightning-charge',      'lucide' => 'zap'],
        // ── Admin navigation ──────────────────────────────────────────────
        'dashboard'     => ['bi' => 'bi-speedometer',           'lucide' => 'layout-dashboard'],
        'wallets'       => ['bi' => 'bi-wallet',                'lucide' => 'wallet-cards'],
        'coins'         => ['bi' => 'bi-coin',                  'lucide' => 'coins'],
        'exchange'      => ['bi' => 'bi-arrow-left-right',      'lucide' => 'arrow-left-right'],
        'balances'      => ['bi' => 'bi-bar-chart',             'lucide' => 'bar-chart-2'],
        'users'         => ['bi' => 'bi-people-fill',           'lucide' => 'users-2'],
        'workers'       => ['bi' => 'bi-pc-display',            'lucide' => 'monitor'],
        'version'       => ['bi' => 'bi-tag',                   'lucide' => 'tag'],
        'earnings'      => ['bi' => 'bi-cash-stack',            'lucide' => 'receipt'],
        'payments'      => ['bi' => 'bi-credit-card',           'lucide' => 'credit-card'],
        'botnets'       => ['bi' => 'bi-bug',                   'lucide' => 'shield-alert'],
        'monsters'      => ['bi' => 'bi-person-dash',           'lucide' => 'user-x'],
        'jobs'          => ['bi' => 'bi-briefcase',             'lucide' => 'briefcase'],
        'renting-admin' => ['bi' => 'bi-lightning-charge-fill', 'lucide' => 'zap-off'],
        'nicehash'      => ['bi' => 'bi-hdd-network',           'lucide' => 'server'],
        // ── Chrome / UI ───────────────────────────────────────────────────
        'menu'          => ['bi' => 'bi-list',                  'lucide' => 'menu'],
        'site-logo'     => ['bi' => 'bi-grid-3x3-gap-fill',    'lucide' => 'grid-3x3'],
        'user'          => ['bi' => 'bi-person-circle',         'lucide' => 'user-circle'],
        'logout'        => ['bi' => 'bi-box-arrow-right',       'lucide' => 'log-out'],
        'login'         => ['bi' => 'bi-box-arrow-in-right',    'lucide' => 'log-in'],
        'dark-mode'     => ['bi' => 'bi-moon',                  'lucide' => 'moon'],
        'light-mode'    => ['bi' => 'bi-sun',                   'lucide' => 'sun'],
        'chevron-down'  => ['bi' => 'bi-chevron-down',          'lucide' => 'chevron-down'],
        'chevron-right' => ['bi' => 'bi-chevron-right',         'lucide' => 'chevron-right'],
        'trash'         => ['bi' => 'bi-trash',                 'lucide' => 'trash-2'],
        'edit'          => ['bi' => 'bi-pencil',                'lucide' => 'pencil'],
        'check'         => ['bi' => 'bi-check-circle',          'lucide' => 'check-circle'],
        'warning'       => ['bi' => 'bi-exclamation-triangle',  'lucide' => 'alert-triangle'],
        'info'          => ['bi' => 'bi-info-circle',           'lucide' => 'info'],
        'refresh'       => ['bi' => 'bi-arrow-clockwise',       'lucide' => 'refresh-cw'],
        'download'      => ['bi' => 'bi-download',              'lucide' => 'download'],
        'external'      => ['bi' => 'bi-box-arrow-up-right',    'lucide' => 'external-link'],
    ];

    /**
     * Render a scheme-appropriate icon element by canonical name.
     *
     * AdminLTE → <i class="bi {bi-name} {$extraClass}"></i>
     * Tailwind  → <i data-lucide="{lucide-name}" class="{$extraClass}"></i>
     * Legacy    → '' (legacy uses plain text; no icon library loaded)
     *
     * $extraClass adds to the element's class attribute — e.g. 'nav-icon' for
     * AdminLTE sidebar items, or 'w-4 h-4' for Tailwind inline icons.
     *
     * Unknown canonical names log a warning and return an empty string.
     */
    public function icon(string $name, string $extraClass = ''): string
    {
        $map    = self::ICON_MAP[$name] ?? null;
        $scheme = Yii::$app->LayoutManager->scheme;

        if (!$map) {
            Yii::warning("ViewUtils::icon() — unknown canonical icon name '{$name}'", __CLASS__);
            return '';
        }

        if ($scheme === 'adminlte') {
            $cls = trim('bi ' . $map['bi'] . ' ' . $extraClass);
            return "<i class=\"{$cls}\"></i>";
        }

        if ($scheme === 'tailwind') {
            $cls = trim($extraClass);
            return $cls
                ? "<i data-lucide=\"{$map['lucide']}\" class=\"{$cls}\"></i>"
                : "<i data-lucide=\"{$map['lucide']}\"></i>";
        }

        return ''; // legacy: no icon library
    }

    // ── Generic helpers ───────────────────────────────────────────────────────

    public function showButtonHeader(): void
    {
        echo "<div class='buttonwrapper'>";
    }

    public function showButton(string $name, string $link, array $htmlOptions = []): void
    {
        echo Html::a($name, $link, $htmlOptions);
    }

    public function showButtonPost(string $name, array $htmlOptions): void
    {
        echo Html::submitButton($name, $htmlOptions);
    }

    public function showTextTeaser(string $text, string $more, int $count = 120, string $class = 'text'): void
    {
        if (empty($text)) return;
        $text = strip_tags($text);
        if (strlen($text) < $count) {
            echo "<p class='$class'>$text</p>";
            return;
        }
        echo "<p class='$class'>" . substr($text, 0, $count) . '... [' . Html::a('more...', $more) . ']</p>';
    }

    public function getTextTeaser(string $text, int $count = 120): string
    {
        if (empty($text)) return '';
        $text = strip_tags($text);
        return strlen($text) < $count ? $text : substr($text, 0, $count) . '...';
    }

    public function getTextTitle(string $text): string
    {
        preg_match('/([^\.\r\n]*)/', $text, $match);
        return $match[1] ?? '';
    }

    public function JavascriptFile(string $filename): void
    {
        echo Html::jsFile($filename);
    }

    public function Javascript(string $javascript): void
    {
        echo "<script>$javascript</script>";
    }

    public function JavascriptReady(string $javascript): void
    {
        echo "<script>$(function(){ $javascript})</script>";
    }

    // ── Layout-aware helpers ──────────────────────────────────────────────────

    /**
     * Render a per-scheme partial from views/layouts/{scheme}/partials/{name}.php.
     * $params are extracted into the partial's scope.
     */
    private function renderLayoutPartial(string $name, array $params = []): string
    {
        $scheme  = Yii::$app->LayoutManager->scheme;
        $partial = "@app/views/layouts/{$scheme}/partials/{$name}";
        return Yii::$app->controller->renderPartial($partial, $params);
    }

    /**
     * Emit the opening <table> tag and register the per-scheme sort/filter JS.
     *
     * IMPORTANT: this method outputs a <table id="$id"> opening tag.
     * Call it at the position in the view where the table should open;
     * close the table with </table> in the view HTML.
     * Do NOT call it in views that already define their own <table> element —
     * the duplicate opening tag creates an unclosed table that breaks layout
     * rendering below the call site (pagination, footers, etc.).
     * For views with an existing table, call JavascriptReady() directly instead.
     *
     * Legacy + AdminLTE: jQuery tablesorter plugin.
     * Tailwind: lightweight vanilla-JS sort + filter (no jQuery dependency).
     */
    public function showTableSorter(string $id, string $options = ''): void
    {
        echo "<table id='$id' class='dataGrid2'>";

        if (Yii::$app->LayoutManager->isTailwind()) {
            $this->emitVanillaSorter($id, $options);
        } else {
            $this->JavascriptReady("
                $('#{$id}').tablesorter({$options});
                $('.tablesorter-header').not('.sorter-false').css('cursor', 'pointer');
            ");
        }
    }

    /**
     * Vanilla-JS sort + filter that replaces jQuery tablesorter for the Tailwind
     * scheme. Respects data-sorter="false" / data-sorter="" headers, the custom
     * `data` attribute on cells for numeric sort, and the .search external filter.
     */
    private function emitVanillaSorter(string $id, string $options = ''): void
    {
        $jsId = json_encode($id);
        echo <<<JS
<script>
document.addEventListener('DOMContentLoaded', function () {
    var table  = document.getElementById({$jsId});
    if (!table) return;
    var tbody  = table.tBodies[0];
    var thead  = table.tHead;
    if (!tbody || !thead) return;
    var ths    = Array.from(thead.rows[0].cells);
    var asc    = ths.map(function () { return true; });

    // Column sort on header click
    ths.forEach(function (th, col) {
        var sorter = th.dataset.sorter;
        if (sorter === 'false' || sorter === '') return;
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            var rows   = Array.from(tbody.rows);
            var isNum  = sorter === 'numeric' || sorter === 'currency';
            rows.sort(function (a, b) {
                var av = cellVal(a, col);
                var bv = cellVal(b, col);
                if (isNum) {
                    var an = parseFloat(av), bn = parseFloat(bv);
                    if (!isNaN(an) && !isNaN(bn)) return asc[col] ? an - bn : bn - an;
                }
                return asc[col] ? av.localeCompare(bv) : bv.localeCompare(av);
            });
            asc[col] = !asc[col];
            rows.forEach(function (r) { tbody.appendChild(r); });
        });
    });

    // External search filter — wires to the first .search input on the page
    var search = document.querySelector('input.search');
    if (search) {
        search.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            Array.from(tbody.rows).forEach(function (r) {
                r.classList.toggle('filtered', q !== '' && r.textContent.toLowerCase().indexOf(q) === -1);
            });
        });
    }

    function cellVal(row, col) {
        var cell = row.cells[col];
        if (!cell) return '';
        return (cell.getAttribute('data') || cell.textContent || '').trim();
    }
});
</script>
JS;
    }

    // ── Admin navigation ──────────────────────────────────────────────────────

    /**
     * Quick-nav bar shown at the top of admin view pages.
     * Legacy: horizontal <a> list. Sidebar schemes: suppressed (sidebar already shows these links).
     */
    public function getAdminSideBarLinks(): string
    {
        return $this->renderLayoutPartial('admin_sidebar');
    }

    /**
     * Full sidebar navigation rendered inside layout files.
     * AdminLTE: <ul class="nav sidebar-menu"> with Bootstrap Icons.
     * Tailwind: <nav> with Lucide icon placeholders.
     * Legacy: empty (navigation lives in the Bootstrap NavBar widget).
     */
    public function renderSidebarNav(): string
    {
        $isAdmin    = !is_null(Yii::$app->user->identity)
                    && Yii::$app->user->identity->is_admin;
        $controller = Yii::$app->controller->id;
        $action     = Yii::$app->controller->action?->id ?? 'index';
        $hasAddress = Yii::$app->request->get('address') !== null;
        return $this->renderLayoutPartial('sidebar_nav', [
            'isAdmin'    => $isAdmin,
            'controller' => $controller,
            'action'     => $action,
            'hasAddress' => $hasAddress,
        ]);
    }

    // ── Coin wallet helpers ───────────────────────────────────────────────────

    /**
     * Coin management action bar shown on wallet/peers admin pages.
     * Layout-aware: legacy uses inline bold links; adminlte uses button groups;
     * tailwind uses Tailwind pill buttons.
     */
    public function getAdminWalletLinks(mixed $coin, mixed $info = null, string $src = 'wallet'): string
    {
        return $this->renderLayoutPartial('wallet_links', [
            'coin' => $coin,
            'info' => $info,
            'src'  => $src,
        ]);
    }
}
