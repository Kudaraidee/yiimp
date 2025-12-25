<?php

namespace app\components;

use Yii;
use yii\base\Component;

/**
 * CSP Violation Analyzer
 * 
 * Analyzes and categorizes Content Security Policy violations, with special focus
 * on identifying violations caused by external JavaScript libraries.
 * 
 * Feature: csp-library-compliance
 * Validates: Requirements 2.1, 3.3
 */
class CspViolationAnalyzer extends Component
{
    /**
     * @var array Known external libraries and their violation patterns
     */
    private $knownLibraries = [
        'jquery' => [
            'patterns' => [
                '/jquery.*\.js/i',
                '/\$\(/i',
                '/jQuery/i',
                '/jquery.*event/i',
                '/jquery.*handler/i',
            ],
            'commonViolations' => [
                'inline-style' => 'jQuery DOM manipulation creating inline styles',
                'inline-script' => 'jQuery event handlers or dynamic script creation',
            ]
        ],
        'tablesorter' => [
            'patterns' => [
                '/tablesorter/i',
                '/jquery\.tablesorter/i',
                '/sortable.*table/i',
                '/sorting.*indicator/i',
            ],
            'commonViolations' => [
                'inline-style' => 'TableSorter plugin generating inline styles for sorting indicators',
                'inline-script' => 'TableSorter event handlers',
            ]
        ],
        'bootstrap' => [
            'patterns' => [
                '/bootstrap/i',
                '/\.modal\(/i',
                '/\.tooltip\(/i',
                '/\.popover\(/i',
                '/modal.*backdrop/i',
                '/tooltip.*component/i',
            ],
            'commonViolations' => [
                'inline-style' => 'Bootstrap components creating dynamic styles',
                'inline-script' => 'Bootstrap JavaScript components',
            ]
        ],
        'chartjs' => [
            'patterns' => [
                '/chart.*\.js/i',
                '/Chart\./i',
                '/canvas.*chart/i',
                '/chart.*canvas/i',
            ],
            'commonViolations' => [
                'inline-style' => 'Chart.js canvas styling',
                'inline-script' => 'Chart.js initialization scripts',
            ]
        ]
    ];

    /**
     * Analyze a CSP violation and identify its source
     * 
     * @param string $violationMessage The CSP violation message from browser console
     * @return array ViolationSource structure with analysis results
     */
    public function analyzeCspViolation(string $violationMessage): array
    {
        $violation = [
            'library' => 'unknown',
            'hash' => null,
            'type' => $this->extractViolationType($violationMessage),
            'content' => $this->extractViolatingContent($violationMessage),
            'isKnown' => false,
            'originalMessage' => $violationMessage,
            'timestamp' => time(),
            'recommendations' => []
        ];

        // Extract hash if present
        $violation['hash'] = $this->extractHashFromViolation($violationMessage);

        // Identify source library
        $libraryInfo = $this->identifySourceLibrary($violationMessage, $violation['content']);
        if ($libraryInfo) {
            $violation['library'] = $libraryInfo['name'];
            $violation['isKnown'] = true;
            $violation['recommendations'] = $this->generateRecommendations($libraryInfo, $violation);
        }

        // Log the violation for analysis
        $this->logViolation($violation);

        return $violation;
    }

    /**
     * Extract the type of CSP violation (script, style, etc.)
     * 
     * @param string $violationMessage
     * @return string
     */
    private function extractViolationType(string $violationMessage): string
    {
        // Check for script violations
        if (preg_match('/script-src|inline script|execute.*script/i', $violationMessage)) {
            return 'script';
        }
        
        // Check for style violations
        if (preg_match('/style-src|inline style|apply.*style/i', $violationMessage)) {
            return 'style';
        }
        
        // Check for image violations
        if (preg_match('/img-src/i', $violationMessage)) {
            return 'image';
        }
        
        // Check for font violations
        if (preg_match('/font-src/i', $violationMessage)) {
            return 'font';
        }
        
        // Check for connection violations
        if (preg_match('/connect-src/i', $violationMessage)) {
            return 'connect';
        }
        
        return 'unknown';
    }

    /**
     * Extract the violating content from the CSP violation message
     * 
     * @param string $violationMessage
     * @return string
     */
    private function extractViolatingContent(string $violationMessage): string
    {
        // Try to extract content between quotes or after "blocked URI"
        if (preg_match('/blocked URI: ["\']([^"\']+)["\']/', $violationMessage, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/["\']([^"\']{10,})["\']/', $violationMessage, $matches)) {
            return substr($matches[1], 0, 200); // Truncate long content
        }
        
        // Fallback: return truncated message
        return substr($violationMessage, 0, 200);
    }

    /**
     * Extract SHA-256 hash from CSP violation message
     * 
     * @param string $violationMessage
     * @return string|null
     */
    private function extractHashFromViolation(string $violationMessage): ?string
    {
        // Look for SHA-256 hash patterns
        if (preg_match('/sha256-([A-Za-z0-9+\/=]+)/', $violationMessage, $matches)) {
            return 'sha256-' . $matches[1];
        }
        
        return null;
    }

    /**
     * Identify which external library is causing the violation
     * 
     * @param string $violationMessage
     * @param string $content
     * @return array|null
     */
    private function identifySourceLibrary(string $violationMessage, string $content): ?array
    {
        // Combine message and content for pattern matching
        $searchText = $violationMessage . ' ' . $content;
        
        // Check for specific library patterns with priority order
        // TableSorter should be checked before jQuery since it contains jquery in the name
        $libraryOrder = ['tablesorter', 'bootstrap', 'chartjs', 'jquery'];
        
        foreach ($libraryOrder as $libraryName) {
            if (!isset($this->knownLibraries[$libraryName])) {
                continue;
            }
            
            $libraryInfo = $this->knownLibraries[$libraryName];
            foreach ($libraryInfo['patterns'] as $pattern) {
                if (preg_match($pattern, $searchText)) {
                    return [
                        'name' => $libraryName,
                        'info' => $libraryInfo
                    ];
                }
            }
        }
        
        return null;
    }

    /**
     * Generate recommendations for fixing the violation
     * 
     * @param array $libraryInfo
     * @param array $violation
     * @return array
     */
    private function generateRecommendations(array $libraryInfo, array $violation): array
    {
        $recommendations = [];
        $libraryName = $libraryInfo['name'];
        $violationType = $violation['type'];
        
        switch ($libraryName) {
            case 'jquery':
                if ($violationType === 'style') {
                    $recommendations[] = 'Use external CSS classes instead of jQuery .css() method';
                    $recommendations[] = 'Consider using data attributes and CSS selectors';
                } elseif ($violationType === 'script') {
                    $recommendations[] = 'Move jQuery event handlers to external files with nonces';
                    $recommendations[] = 'Use jQuery .on() with external script files';
                }
                break;
                
            case 'tablesorter':
                if ($violationType === 'style') {
                    $recommendations[] = 'Configure TableSorter to use CSS classes instead of inline styles';
                    $recommendations[] = 'Add TableSorter-generated styles to approved hash whitelist';
                    $recommendations[] = 'Consider alternative sorting library with better CSP support';
                }
                break;
                
            case 'bootstrap':
                $recommendations[] = 'Ensure Bootstrap components use external CSS';
                $recommendations[] = 'Configure Bootstrap to avoid inline style generation';
                break;
                
            case 'chartjs':
                $recommendations[] = 'Chart.js should use canvas rendering without inline styles';
                $recommendations[] = 'Verify Chart.js configuration for CSP compliance';
                break;
        }
        
        // Generic recommendations
        if (empty($recommendations)) {
            $recommendations[] = 'Add content hash to CSP policy if safe';
            $recommendations[] = 'Configure library to avoid inline content generation';
            $recommendations[] = 'Consider CSP-compliant alternative library';
        }
        
        return $recommendations;
    }

    /**
     * Log CSP violation with enhanced details for library attribution
     * 
     * @param array $violation
     */
    private function logViolation(array $violation): void
    {
        $logMessage = sprintf(
            "CSP Violation Detected - Library: %s, Type: %s, Hash: %s, Known: %s",
            $violation['library'],
            $violation['type'],
            $violation['hash'] ?? 'none',
            $violation['isKnown'] ? 'yes' : 'no'
        );
        
        $logContext = [
            'violation' => $violation,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s', $violation['timestamp'])
        ];
        
        // Log to application log with CSP category
        Yii::getLogger()->log($logMessage, \yii\log\Logger::LEVEL_WARNING, 'csp.violation');
        
        // Also log detailed context for debugging
        Yii::getLogger()->log(
            'CSP Violation Details: ' . json_encode($logContext, JSON_PRETTY_PRINT),
            \yii\log\Logger::LEVEL_INFO,
            'csp.violation.detail'
        );
    }

    /**
     * Get statistics about CSP violations
     * 
     * @param int $hours Number of hours to look back (default: 24)
     * @return array Statistics about violations
     */
    public function getViolationStatistics(int $hours = 24): array
    {
        // This would typically query a database or log files
        // For now, return a structure that could be populated
        return [
            'totalViolations' => 0,
            'violationsByLibrary' => [],
            'violationsByType' => [],
            'timeRange' => [
                'start' => date('Y-m-d H:i:s', time() - ($hours * 3600)),
                'end' => date('Y-m-d H:i:s'),
            ],
            'topRecommendations' => []
        ];
    }

    /**
     * Check if a hash should be whitelisted based on analysis
     * 
     * @param string $hash The SHA-256 hash
     * @param string $library The identified library
     * @param string $content The violating content
     * @return bool Whether the hash appears safe to whitelist
     */
    public function shouldWhitelistHash(string $hash, string $library, string $content): bool
    {
        // Basic safety checks - proper SHA-256 hash should be at least 44 characters (base64 encoded)
        if (strlen($hash) < 44) {
            return false;
        }
        
        // Verify it's a proper SHA-256 hash format
        if (!preg_match('/^sha256-[A-Za-z0-9+\/=]+$/', $hash)) {
            return false;
        }
        
        // Check if it's from a known safe library
        $knownSafeLibraries = ['tablesorter', 'bootstrap', 'chartjs'];
        if (in_array($library, $knownSafeLibraries)) {
            // Additional content analysis could go here
            return true;
        }
        
        // For unknown libraries, be more cautious
        if ($library === 'unknown') {
            return false;
        }
        
        return false;
    }
}