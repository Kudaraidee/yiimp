<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;

/**
 * CSP Controller
 * 
 * Handles Content Security Policy violation reports from browsers.
 * This controller receives real CSP violation reports and processes them
 * to identify external library issues.
 * 
 * Feature: csp-library-compliance
 * Validates: Requirements 2.1, 3.3
 */
class CspController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'report' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * CSP Violation Report endpoint
     * 
     * This endpoint receives CSP violation reports from browsers when
     * the CSP policy includes a report-uri directive.
     * 
     * Browser sends POST requests with JSON payload containing violation details.
     * 
     * @return Response
     */
    public function actionReport()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        try {
            // Get the raw POST data (JSON)
            $rawData = Yii::$app->request->getRawBody();
            $violationData = json_decode($rawData, true);
            
            if (!$violationData || !isset($violationData['csp-report'])) {
                Yii::getLogger()->log(
                    'Invalid CSP violation report received: ' . $rawData,
                    \yii\log\Logger::LEVEL_WARNING,
                    'csp.violation.invalid'
                );
                return ['status' => 'error', 'message' => 'Invalid report format'];
            }
            
            $report = $violationData['csp-report'];
            
            // Extract violation information
            $violationMessage = $this->buildViolationMessage($report);
            $context = [
                'documentUri' => $report['document-uri'] ?? '',
                'referrer' => $report['referrer'] ?? '',
                'blockedUri' => $report['blocked-uri'] ?? '',
                'violatedDirective' => $report['violated-directive'] ?? '',
                'originalPolicy' => $report['original-policy'] ?? '',
                'sourceFile' => $report['source-file'] ?? '',
                'lineNumber' => $report['line-number'] ?? 0,
                'columnNumber' => $report['column-number'] ?? 0,
            ];
            
            // Use the violation reporter to analyze and log
            $reporter = Yii::$app->get('cspViolationReporter');
            $analysis = $reporter->reportViolation($violationMessage, $context);
            
            // Log successful processing
            Yii::getLogger()->log(
                sprintf(
                    'CSP violation processed: Library=%s, Type=%s, Known=%s',
                    $analysis['library'],
                    $analysis['type'],
                    $analysis['isKnown'] ? 'yes' : 'no'
                ),
                \yii\log\Logger::LEVEL_INFO,
                'csp.violation.processed'
            );
            
            return [
                'status' => 'success',
                'message' => 'Violation report processed',
                'analysis' => [
                    'library' => $analysis['library'],
                    'type' => $analysis['type'],
                    'isKnown' => $analysis['isKnown'],
                    'recommendationsCount' => count($analysis['recommendations'])
                ]
            ];
            
        } catch (\Exception $e) {
            Yii::getLogger()->log(
                'Error processing CSP violation report: ' . $e->getMessage(),
                \yii\log\Logger::LEVEL_ERROR,
                'csp.violation.error'
            );
            
            return [
                'status' => 'error',
                'message' => 'Failed to process violation report'
            ];
        }
    }

    /**
     * Build a human-readable violation message from browser report
     * 
     * @param array $report CSP violation report from browser
     * @return string Formatted violation message
     */
    private function buildViolationMessage(array $report): string
    {
        $directive = $report['violated-directive'] ?? 'unknown-directive';
        $blockedUri = $report['blocked-uri'] ?? 'unknown-uri';
        $sourceFile = $report['source-file'] ?? '';
        $lineNumber = $report['line-number'] ?? 0;
        
        $message = "Content Security Policy violation: ";
        $message .= "Directive '{$directive}' was violated. ";
        $message .= "Blocked URI: '{$blockedUri}'. ";
        
        if ($sourceFile) {
            $message .= "Source: {$sourceFile}";
            if ($lineNumber > 0) {
                $message .= ":{$lineNumber}";
            }
            $message .= ". ";
        }
        
        // Add context clues for library identification
        if (strpos($blockedUri, 'inline') !== false) {
            $message .= "Inline content detected. ";
        }
        
        if (strpos($sourceFile, 'jquery') !== false) {
            $message .= "jQuery library involved. ";
        }
        
        if (strpos($sourceFile, 'tablesorter') !== false) {
            $message .= "TableSorter plugin involved. ";
        }
        
        if (strpos($sourceFile, 'bootstrap') !== false) {
            $message .= "Bootstrap library involved. ";
        }
        
        if (strpos($sourceFile, 'chart') !== false) {
            $message .= "Chart library involved. ";
        }
        
        return trim($message);
    }

    /**
     * Get CSP violation statistics (for admin dashboard)
     * 
     * @return Response
     */
    public function actionStats()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        // This would typically require admin authentication
        // For now, just return basic stats
        
        $reporter = Yii::$app->get('cspViolationReporter');
        $stats = $reporter->getViolationStats(24);
        
        return [
            'status' => 'success',
            'stats' => $stats
        ];
    }
}