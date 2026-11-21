<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'API';

$specUrl     = Url::to(['/site/api-spec']);
$specUrlJson = json_encode($specUrl);
$siteUrl     = defined('YIIMP_SITE_URL') ? YIIMP_SITE_URL : Yii::$app->request->host;

$this->registerCssFile('https://unpkg.com/swagger-ui-dist@5/swagger-ui.css');
?>
<style>
/* ── Same card chrome and 16px rhythm as /site/mining ──────────────────────── */
#api-cards {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
#api-cards .main-left-box {
    border: 1px solid #d8e2ef !important;
    border-radius: 7px !important;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
    overflow: hidden;
    margin-bottom: 0;
}
#api-cards .main-left-title {
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
#api-cards .mn-head-main {
    display: flex;
    align-items: baseline;
    gap: 9px;
    flex-wrap: wrap;
    min-width: 0;
}
#api-cards .mn-sub {
    font-size: 10px;
    color: #8a94a6;
    font-weight: 500;
}
#api-cards .mc-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 999px;
    background: #eef4ff;
    border: 1px solid #d7e5ff;
    color: #2c5aa0;
    font-size: 9px;
    font-weight: 700;
    line-height: 14px;
    white-space: nowrap;
}
#api-cards .main-left-inner { padding: 0 !important; }
#api-cards .api-intro {
    padding: 12px;
    font-size: 11px;
    line-height: 1.5;
    color: #4a586c;
}
#api-cards .api-intro b { color: #223a5e; }
#api-cards .api-intro a { color: #2c5aa0; text-decoration: none; }
#api-cards .api-intro a:hover { text-decoration: underline; }
#api-cards .api-foot {
    padding: 7px 12px !important;
    background: #fcfdff !important;
    border-top: 1px solid #eef2f7;
    color: #98a3b5;
    font-size: 9px;
    line-height: 1.5;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
#api-cards .api-foot b { color: #7d8aa0; font-weight: 700; }

/* ── A: overview metadata grid ─────────────────────────────────────────────── */
#api-cards .meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    padding: 0 12px 12px;
}
#api-cards .meta-cell {
    border: 1px solid #e8eef6;
    border-radius: 6px;
    background: #fcfdff;
    padding: 8px 10px;
    min-width: 0;
}
#api-cards .meta-cell span {
    display: block;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #98a3b5;
}
#api-cards .meta-cell b {
    display: block;
    margin-top: 3px;
    font-size: 11px;
    color: #26385a;
    overflow-wrap: anywhere;
}

/* ── C: table header above Swagger ────────────────────────────────────────── */
#api-cards .api-table-head {
    display: grid;
    grid-template-columns: 62px minmax(0, 1fr) minmax(0, 1.25fr) 16px;
    gap: 10px;
    align-items: center;
    padding: 7px 12px;
    background: #fff;
    border-bottom: 1px solid #e8eef6;
    color: #98a3b5;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
}

/* ── Swagger rendered as compact table rows (Try it out still works) ───────── */
#swagger-ui .topbar,
#swagger-ui .info,
#swagger-ui .scheme-container,
#swagger-ui .schemes,
#swagger-ui .servers-title,
#swagger-ui .servers { display: none !important; }
#swagger-ui .swagger-ui { font-family: inherit; font-size: 11px; color: #334155; }
#swagger-ui .swagger-ui .wrapper { padding: 0; }
#swagger-ui .opblock-tag-section { margin: 0; }
#swagger-ui .opblock-tag {
    margin: 0 !important;
    padding: 6px 12px !important;
    background: #f8fafc !important;
    border: 0 !important;
    border-bottom: 1px solid #e8eef6 !important;
    color: #8a94a6;
    font-size: 9px !important;
    font-weight: 800 !important;
    letter-spacing: .08em;
    text-transform: uppercase;
    line-height: 1.4;
}
#swagger-ui .opblock-tag small {
    font-size: 9px;
    color: #a9b4c4;
    font-weight: 500;
    letter-spacing: 0;
    text-transform: none;
}
#swagger-ui .opblock {
    margin: 0 !important;
    border: 0 !important;
    border-bottom: 1px solid #eef2f7 !important;
    border-radius: 0 !important;
    background: #fff !important;
    box-shadow: none;
}
#swagger-ui .opblock:hover { background: #f7faff !important; }
#swagger-ui .opblock.is-open { background: #fff !important; }
#swagger-ui .opblock .opblock-summary {
    display: flex !important;
    align-items: center;
    gap: 8px;
    border: 0 !important;
    padding: 8px 12px !important;
    min-height: 36px;
    cursor: pointer;
}
#swagger-ui .opblock .opblock-summary-control {
    display: flex !important;
    flex: 1 1 auto;
    align-items: center;
    gap: 10px;
    min-width: 0;
    margin: 0;
    padding: 0;
    background: none;
    border: 0;
    text-align: left;
}
#swagger-ui .opblock .opblock-summary-method {
    flex: 0 0 44px;
    width: 44px;
    min-width: 44px;
    margin: 0;
    padding: 2px 7px !important;
    border-radius: 3px !important;
    background: #eef4ff !important;
    color: #2c5aa0 !important;
    font-size: 9px !important;
    font-weight: 800 !important;
    line-height: 14px;
    text-align: center;
    text-shadow: none;
    box-shadow: none;
}
#swagger-ui .opblock .opblock-summary-path-description-wrapper {
    display: flex !important;
    flex: 1 1 auto;
    align-items: center;
    gap: 10px;
    min-width: 0;
}
#swagger-ui .opblock .opblock-summary-path,
#swagger-ui .opblock .opblock-summary-path__deprecated {
    flex: 0 0 34%;
    min-width: 0;
    font-family: monospace;
    font-size: 11px !important;
    font-weight: 600;
    color: #26385a;
    overflow-wrap: anywhere;
}
#swagger-ui .opblock .opblock-summary-description {
    flex: 1 1 auto;
    min-width: 0;
    margin: 0;
    text-align: right;
    font-size: 10px !important;
    color: #64748b !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
#swagger-ui .opblock .opblock-control-arrow {
    flex: 0 0 16px;
    width: 16px;
    padding: 0;
    opacity: .55;
}
#swagger-ui .opblock .opblock-control-arrow svg {
    width: 12px;
    height: 12px;
}
#swagger-ui .opblock .view-line-link.copy-to-clipboard { display: none !important; }
#swagger-ui .opblock.is-open .opblock-body { padding: 0 12px 12px; }
#swagger-ui .opblock .opblock-section-header { background: #f8fafc; padding: 8px 10px; }
#swagger-ui .opblock .btn.try-out__btn { font-size: 10px; padding: 5px 10px; }
#swagger-ui .opblock .responses-wrapper { font-size: 10px; }

/* ── Schemas = third card (moved out of #swagger-ui in onComplete) ──────────── */
#api-schemas { min-height: 0; }
#api-schemas section.models {
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    overflow: visible;
}
#api-schemas section.models h4 { display: none !important; }
#api-schemas section.models .models-scroll { padding: 0 !important; max-height: none; }
#api-schemas section.models .model-container,
#api-schemas section.models .models-virtual-item {
    margin: 0 !important;
    padding: 8px 12px !important;
    border: 0 !important;
    border-bottom: 1px solid #eef2f7 !important;
    border-radius: 0 !important;
    background: #fff !important;
}
#api-schemas section.models .model-container:last-child { border-bottom: 0 !important; }
#api-schemas section.models .model-container:hover { background: #f7faff !important; }
#api-schemas section.models .model-title,
#api-schemas section.models .json-schema-2020-12__title {
    font-family: monospace;
    font-size: 11px !important;
    color: #26385a !important;
}
#api-schemas section.models .model-hint,
#api-schemas section.models .json-schema-2020-12-core-keyword__value--secondary {
    font-size: 10px !important;
    color: #64748b !important;
}

@media (max-width: 620px) {
    #api-cards .api-table-head { grid-template-columns: 52px minmax(0, 1fr) 16px; }
    #api-cards .api-table-head span:nth-child(3),
    #swagger-ui .opblock .opblock-summary-description { display: none; }
    #swagger-ui .opblock .opblock-summary-path { flex-basis: auto; }
}
</style>

<div id="api-cards">
    <div class="main-left-box">
        <div class="main-left-title">
            <span class="mn-head-main">
                <span>API Overview</span>
                <span class="mn-sub">OpenAPI 3.0 · base info</span>
            </span>
            <span class="mc-badge">REST</span>
        </div>
        <div class="main-left-inner">
            <div class="api-intro">
                Public REST API for <b><?= Html::encode($siteUrl) ?></b>.
                Browse the endpoints below, or use the
                <?= Html::a('OpenAPI specification', $specUrl) ?> for clients and code generators.
            </div>
            <div class="meta-grid">
                <div class="meta-cell"><span>Spec</span><b><?= Html::encode($specUrl) ?></b></div>
                <div class="meta-cell"><span>Format</span><b>OpenAPI 3.0 / JSON</b></div>
                <div class="meta-cell"><span>Auth</span><b>Public · read-only</b></div>
            </div>
        </div>
    </div>

    <div class="main-left-box">
        <div class="main-left-title">
            <span class="mn-head-main">
                <span>Endpoints</span>
                <span class="mn-sub">interactive · try it out</span>
            </span>
            <span class="mc-badge">GET only</span>
        </div>
        <div class="main-left-inner">
            <div class="api-table-head">
                <span>Method</span><span>Path</span><span>Description</span><span></span>
            </div>
            <div id="swagger-ui"></div>
        </div>
        <div class="api-foot">
            <span>Loaded from <b><?= Html::encode($specUrl) ?></b></span>
            <span>Click a row to expand · Try it out enabled</span>
        </div>
    </div>

    <div class="main-left-box">
        <div class="main-left-title">
            <span class="mn-head-main">
                <span>Schemas</span>
                <span class="mn-sub">components.schemas · response models</span>
            </span>
            <span class="mc-badge">MODEL</span>
        </div>
        <div class="main-left-inner">
            <div id="api-schemas" class="swagger-ui"></div>
        </div>
        <div class="api-foot">
            <span>Defined in <b><?= Html::encode($specUrl) ?></b></span>
            <span>Click a model to expand its fields</span>
        </div>
    </div>
</div>

<?php
$this->registerJsFile('https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js',
    ['position' => \yii\web\View::POS_END]);
$this->registerJs("
    function moveApiSchemas() {
        var models = document.querySelector('#swagger-ui section.models');
        var host   = document.getElementById('api-schemas');
        if (!models || !host || models.parentNode === host) return !!models && !!host;
        host.appendChild(models);
        return true;
    }
    SwaggerUIBundle({
        url:             {$specUrlJson},
        dom_id:          '#swagger-ui',
        deepLinking:     true,
        tryItOutEnabled: true,
        defaultModelsExpandDepth: 1,
        defaultModelExpandDepth: 1,
        presets:  [ SwaggerUIBundle.presets.apis ],
        plugins:  [ SwaggerUIBundle.plugins.DownloadUrl ],
        layout:   'BaseLayout',
        onComplete: function () {
            if (!moveApiSchemas()) {
                var tries = 0;
                (function retry() {
                    if (moveApiSchemas() || tries++ > 30) return;
                    requestAnimationFrame(retry);
                })();
            }
        }
    });
", \yii\web\View::POS_END);
