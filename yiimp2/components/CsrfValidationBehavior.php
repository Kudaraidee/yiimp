<?php

namespace app\components;

use Yii;
use yii\base\ActionFilter;
use yii\web\BadRequestHttpException;

/**
 * CSRF Validation Behavior
 * 
 * This behavior ensures that CSRF tokens are properly validated for state-changing requests
 * (POST, PUT, DELETE) and provides comprehensive logging for validation events.
 * 
 * Requirements:
 * - 2.1: Reject POST requests without CSRF token
 * - 2.2: Reject PUT requests without CSRF token
 * - 2.3: Reject DELETE requests without CSRF token
 * - 2.4: Return HTTP 400 status code for missing tokens
 * - 2.5: Log validation failures with request details
 */
class CsrfValidationBehavior extends ActionFilter
{
    /**
     * Actions to skip CSRF validation for
     * @var array
     */
    public $except = [];
    
    /**
     * Before action handler - validates CSRF token
     * 
     * @param \yii\base\Action $action
     * @return bool
     * @throws BadRequestHttpException if CSRF validation fails
     */
    public function beforeAction($action)
    {
        // Check if this action should be skipped
        if (in_array($action->id, $this->except)) {
            return parent::beforeAction($action);
        }
        
        $request = Yii::$app->request;
        $method = $request->getMethod();
        
        // Only validate state-changing methods (Requirements 2.1, 2.2, 2.3)
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return parent::beforeAction($action);
        }
        
        // Get CSRF token from request
        $csrfParam = $request->csrfParam;
        $submittedToken = $request->post($csrfParam) ?? $request->get($csrfParam);
        $sessionToken = $request->getCsrfToken();
        
        // Log CSRF validation attempt (Requirement 2.5)
        Yii::info([
            'event' => 'csrf_validation_attempt',
            'controller' => $action->controller->id,
            'action' => $action->id,
            'method' => $method,
            'url' => $request->getUrl(),
            'ip' => $request->getUserIP(),
            'user_agent' => $request->getUserAgent(),
            'token_present' => $submittedToken !== null,
            'token_empty' => empty($submittedToken),
            'session_id' => Yii::$app->session->getId(),
            'session_active' => Yii::$app->session->getIsActive(),
        ], __METHOD__);
        
        // Check if token is missing (Requirement 2.4)
        if ($submittedToken === null || $submittedToken === '') {
            // Log missing token event (Requirement 2.5)
            Yii::warning([
                'event' => 'csrf_token_missing',
                'controller' => $action->controller->id,
                'action' => $action->id,
                'method' => $method,
                'url' => $request->getUrl(),
                'ip' => $request->getUserIP(),
                'user_agent' => $request->getUserAgent(),
                'session_id' => Yii::$app->session->getId(),
                'session_active' => Yii::$app->session->getIsActive(),
                'post_keys' => array_keys($request->post()),
                'get_keys' => array_keys($request->get()),
            ], __METHOD__);
            
            // Return HTTP 400 status code (Requirement 2.4)
            throw new BadRequestHttpException(
                'CSRF token is missing. Please refresh the page and try again.'
            );
        }
        
        // Validate CSRF token
        try {
            $isValid = $request->validateCsrfToken($submittedToken);
        } catch (\Exception $e) {
            // Log validation exception (Requirement 2.5)
            Yii::error([
                'event' => 'csrf_validation_exception',
                'controller' => $action->controller->id,
                'action' => $action->id,
                'method' => $method,
                'url' => $request->getUrl(),
                'ip' => $request->getUserIP(),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'session_id' => Yii::$app->session->getId(),
            ], __METHOD__);
            
            throw $e;
        }
        
        if (!$isValid) {
            // Log validation failure (Requirement 2.5)
            Yii::warning([
                'event' => 'csrf_validation_failed',
                'controller' => $action->controller->id,
                'action' => $action->id,
                'method' => $method,
                'url' => $request->getUrl(),
                'ip' => $request->getUserIP(),
                'user_agent' => $request->getUserAgent(),
                'submitted_token' => substr($submittedToken, 0, 20) . '...',
                'expected_token' => substr($sessionToken, 0, 20) . '...',
                'session_id' => Yii::$app->session->getId(),
                'session_active' => Yii::$app->session->getIsActive(),
            ], __METHOD__);
            
            // Return HTTP 400 status code (Requirement 2.4)
            throw new BadRequestHttpException(
                'CSRF token validation failed. Please refresh the page and try again.'
            );
        }
        
        // Log successful validation (Requirement 2.5)
        Yii::info([
            'event' => 'csrf_validation_success',
            'controller' => $action->controller->id,
            'action' => $action->id,
            'method' => $method,
            'url' => $request->getUrl(),
            'ip' => $request->getUserIP(),
            'session_id' => Yii::$app->session->getId(),
        ], __METHOD__);
        
        return parent::beforeAction($action);
    }
}
