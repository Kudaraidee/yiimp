<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use yii\helpers\Html;

$this->title = $name;
?>
<div class="site-error">

    <div class="text-center mb-4">
        <h1 class="display-1"><?= Html::encode(isset($exception->statusCode) ? $exception->statusCode : 500) ?></h1>
        <h2><?= Html::encode($this->title) ?></h2>
    </div>

    <div class="alert alert-danger">
        <?= nl2br(Html::encode($message)) ?>
    </div>

    <?php if (YII_DEBUG && isset($exception)): ?>
        <div class="card mt-4">
            <div class="card-header bg-warning">
                <h5 class="mb-0">Debug Information (visible only in development mode)</h5>
            </div>
            <div class="card-body">
                <p><strong>Exception:</strong> <?= Html::encode(get_class($exception)) ?></p>
                <p><strong>File:</strong> <?= Html::encode($exception->getFile()) ?></p>
                <p><strong>Line:</strong> <?= Html::encode($exception->getLine()) ?></p>
                
                <?php if ($exception->getPrevious()): ?>
                    <hr>
                    <p><strong>Previous Exception:</strong> <?= Html::encode(get_class($exception->getPrevious())) ?></p>
                    <p><?= nl2br(Html::encode($exception->getPrevious()->getMessage())) ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-4">
        <p class="text-muted">
            The above error occurred while the web server was processing your request.
        </p>
        
        <?php if (isset($exception->statusCode) && $exception->statusCode == 404): ?>
            <p>
                The page you are looking for could not be found. Please check the URL or return to the 
                <?= Html::a('homepage', Yii::$app->homeUrl) ?>.
            </p>
        <?php elseif (isset($exception->statusCode) && $exception->statusCode == 403): ?>
            <p>
                You do not have permission to access this resource. If you believe this is an error, 
                please contact the site administrator.
            </p>
        <?php elseif (isset($exception->statusCode) && $exception->statusCode >= 500): ?>
            <p>
                An internal server error occurred. The error has been logged and will be reviewed by our team.
                Please try again later or contact us if the problem persists.
            </p>
        <?php else: ?>
            <p>
                Please contact us if you think this is a server error. Thank you.
            </p>
        <?php endif; ?>
        
        <div class="mt-3">
            <?= Html::a('Return to Homepage', Yii::$app->homeUrl, ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Go Back', 'javascript:history.back()', ['class' => 'btn btn-secondary']) ?>
        </div>
    </div>

</div>
