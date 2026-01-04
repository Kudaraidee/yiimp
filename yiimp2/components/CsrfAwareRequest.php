<?php

namespace app\components;

use Yii;
use yii\web\Request;

/**
 * CSRF-Aware Request Component
 * 
 * Extends Yii2's Request component to integrate with CsrfTokenManager
 * for proper token lifecycle management.
 * 
 * This component ensures that CSRF tokens are properly managed according
 * to Requirements 5.1-5.5.
 */
class CsrfAwareRequest extends Request
{
    /**
     * @var string|null Last known session ID
     */
    private $_lastSessionId = null;
    
    /**
     * @var bool Flag to prevent infinite recursion
     */
    private $_generatingToken = false;
    
    /**
     * Get CSRF token with simplified lifecycle management
     * 
     * Simplified version that doesn't interfere with Yii2's built-in CSRF handling.
     * This fixes the "Unable to verify your data submission" error by ensuring
     * tokens remain consistent between form load and submission.
     * 
     * @param bool $regenerate Whether to regenerate the token
     * @return string The CSRF token
     */
    public function getCsrfToken($regenerate = false)
    {
        // For POST requests, always use the parent implementation
        // to avoid token regeneration during validation
        $isPostRequest = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
        
        if ($isPostRequest) {
            return parent::getCsrfToken(false); // Never regenerate during POST
        }
        
        // For GET requests, use normal token generation
        return parent::getCsrfToken($regenerate);
    }
}

