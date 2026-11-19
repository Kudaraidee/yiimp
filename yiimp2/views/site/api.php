<?php

/** @var yii\web\View $this */

use yii\helpers\Url;

$this->title = 'API';

$specUrl = json_encode(Url::to(['/site/api-spec']));

$this->registerCssFile('https://unpkg.com/swagger-ui-dist@5/swagger-ui.css');
?>

<style>
/* Fit Swagger UI into the pool's Bootstrap layout */
#swagger-ui .topbar          { display: none; }   /* hide Swagger's own branded header */
#swagger-ui .servers-title,
#swagger-ui .servers         { display: none; }   /* single server — no selection needed */
#swagger-ui .swagger-ui      { font-family: inherit; }
#swagger-ui .info             { margin: 0 0 1rem; }
#swagger-ui .scheme-container { padding: .5rem 0; box-shadow: none; }
</style>

<div id="swagger-ui"></div>

<?php
$this->registerJsFile('https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js',
    ['position' => \yii\web\View::POS_END]);
$this->registerJs("
    SwaggerUIBundle({
        url:             {$specUrl},
        dom_id:          '#swagger-ui',
        deepLinking:     true,
        tryItOutEnabled: true,
        presets:  [ SwaggerUIBundle.presets.apis ],
        plugins:  [ SwaggerUIBundle.plugins.DownloadUrl ],
        layout:   'BaseLayout'
    });
", \yii\web\View::POS_END);
?>
