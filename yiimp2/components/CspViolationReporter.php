<?php

namespace app\components;

use Yii;
use yii\base\Component;

/**
 * CSP Violation Reporter
 * 
 * Provides a simple interface for reporting and handling CSP violations
 * from browser console or server-side detection.
 * 
 * Feature: csp-library-compliance
 * Validates: Requirements 2.1, 3.3
 */
class CspViolationReporter extends Component
{
    /**
     * @var CspViolationAnalyzer
     */
    private $analyzer;

    public function init()
    {
        parent::init();
        $this->analyzer = Yii::$app->get('cspViolationAnalyzer');
    }

    /**
     * Report a CSP violation from browser console
     * 
     * This method should be called from a CSP violation report endpoint
     * that receives real browser CSP violation reports.
     * 
     * @param string $violationMessage The CSP violation message
     * @param array $context Additional context (URL, user agent, etc.)
     * @return array Analysis results
     */
    public function reportViolation(string $violationMessage, array $context = []): array
    {
        // Analyze the violation
        $analysis = $this->analyzer->analyzeCspViolation($violationMessage);
        
        // Add context information
        $analysis['context'] = array_merge([
            'url' => Yii::$app->request->getUrl(),
            'userAgent' => Yii::$app->request->getUserAgent(),
            'timestamp' => date('Y-m-d H:i:s'),
            'sessionId' => Yii::$app->session->getId(),
        ], $context);
        
        // Log the complete analysis with ERROR level since CSP violations are serious
        Yii::getLogger()->log(
            'REAL CSP VIOLATION DETECTED: ' . json_encode($analysis, JSON_PRETTY_PRINT),
            \yii\log\Logger::LEVEL_ERROR,
            'csp.violation.report'
        );
        
        return $analysis;
    }

    /**
     * Get violation statistics for dashboard/monitoring
     * 
     * @param int $hours Hours to look back
     * @return array Statistics
     */
    public function getViolationStats(int $hours = 24): array
    {
        return $this->analyzer->getViolationStatistics($hours);
    }

    /**
     * Check if a hash should be added to the CSP whitelist
     * 
     * @param string $hash The SHA-256 hash
     * @param string $library The identified library
     * @param string $content The violating content
     * @return array Recommendation with reasoning
     */
    public function getWhitelistRecommendation(string $hash, string $library, string $content): array
    {
        $shouldWhitelist = $this->analyzer->shouldWhitelistHash($hash, $library, $content);
        
        return [
            'shouldWhitelist' => $shouldWhitelist,
            'hash' => $hash,
            'library' => $library,
            'reasoning' => $shouldWhitelist 
                ? "Hash from known safe library ({$library}) - recommended for whitelist"
                : "Hash not recommended for whitelist - unknown or unsafe source",
            'cspDirective' => $this->generateCspDirective($hash)
        ];
    }

    /**
     * Generate CSP directive for hash whitelisting
     * 
     * @param string $hash The SHA-256 hash
     * @return string CSP directive fragment
     */
    private function generateCspDirective(string $hash): string
    {
        return "'{$hash}'";
    }
}