<?php

namespace app\components;

use Yii;
use yii\base\Component;

/**
 * CSP Hash Whitelist Manager
 * 
 * Manages approved SHA-256 hashes for external library content that can be
 * safely whitelisted in the Content Security Policy.
 * 
 * Feature: csp-library-compliance
 * Task: 2.1 Create hash whitelist documentation
 * Validates: Requirements 2.3
 */
class CspHashWhitelistManager extends Component
{
    /**
     * @var string Path to the hash whitelist configuration file
     */
    public $whitelistConfigPath = '@app/config/csp-hash-whitelist.php';

    /**
     * @var array Cached whitelist data
     */
    private $whitelist = null;

    /**
     * @var array Known safe libraries that can have hashes whitelisted
     */
    private $safeLibraries = [
        'jquery' => [
            'name' => 'jQuery',
            'description' => 'Popular JavaScript library for DOM manipulation',
            'trustLevel' => 'medium', // Requires review due to dynamic content generation
        ],
        'bootstrap' => [
            'name' => 'Bootstrap',
            'description' => 'CSS framework with JavaScript components',
            'trustLevel' => 'high',
        ],
        'chartjs' => [
            'name' => 'Chart.js',
            'description' => 'JavaScript charting library',
            'trustLevel' => 'high',
        ],
        'tablesorter' => [
            'name' => 'TableSorter',
            'description' => 'jQuery plugin for sortable tables',
            'trustLevel' => 'high',
        ]
    ];

    /**
     * Initialize the component
     */
    public function init()
    {
        parent::init();
        $this->loadWhitelist();
    }

    /**
     * Add a hash to the approved whitelist
     * 
     * @param string $hash The SHA-256 hash (with sha256- prefix)
     * @param string $library The source library name
     * @param string $type The content type (script, style)
     * @param string $description Description of what the hash represents
     * @param array $metadata Additional metadata
     * @return bool Success status
     */
    public function addApprovedHash(string $hash, string $library, string $type, string $description, array $metadata = []): bool
    {
        // Validate hash format
        if (!$this->isValidHash($hash)) {
            Yii::error("Invalid hash format: {$hash}", 'csp.whitelist');
            return false;
        }

        // Check if library is known and safe
        if (!$this->isLibrarySafe($library)) {
            Yii::warning("Library '{$library}' is not in the safe libraries list", 'csp.whitelist');
        }

        // Prepare hash entry
        $hashEntry = [
            'hash' => $hash,
            'library' => $library,
            'type' => $type,
            'description' => $description,
            'dateAdded' => date('Y-m-d H:i:s'),
            'addedBy' => $this->getCurrentUser(),
            'status' => 'active',
            'metadata' => array_merge([
                'sourceFile' => $metadata['sourceFile'] ?? 'unknown',
                'violationCount' => $metadata['violationCount'] ?? 1,
                'lastSeen' => date('Y-m-d H:i:s'),
            ], $metadata)
        ];

        // Add to whitelist
        if (!isset($this->whitelist['hashes'])) {
            $this->whitelist['hashes'] = [];
        }

        $this->whitelist['hashes'][$hash] = $hashEntry;

        // Update library statistics
        $this->updateLibraryStats($library, $type);

        // Save whitelist
        $success = $this->saveWhitelist();

        if ($success) {
            Yii::info("Added hash to whitelist: {$hash} from {$library}", 'csp.whitelist');
        }

        return $success;
    }

    /**
     * Remove a hash from the whitelist
     * 
     * @param string $hash The SHA-256 hash to remove
     * @param string $reason Reason for removal
     * @return bool Success status
     */
    public function removeHash(string $hash, string $reason = 'Manual removal'): bool
    {
        if (!isset($this->whitelist['hashes'][$hash])) {
            return false;
        }

        // Mark as removed instead of deleting for audit trail
        $this->whitelist['hashes'][$hash]['status'] = 'removed';
        $this->whitelist['hashes'][$hash]['dateRemoved'] = date('Y-m-d H:i:s');
        $this->whitelist['hashes'][$hash]['removedBy'] = $this->getCurrentUser();
        $this->whitelist['hashes'][$hash]['removalReason'] = $reason;

        $success = $this->saveWhitelist();

        if ($success) {
            Yii::info("Removed hash from whitelist: {$hash} - {$reason}", 'csp.whitelist');
        }

        return $success;
    }

    /**
     * Get all active approved hashes
     * 
     * @param string|null $type Filter by type (script, style)
     * @param string|null $library Filter by library
     * @return array Array of approved hashes
     */
    public function getApprovedHashes(?string $type = null, ?string $library = null): array
    {
        $hashes = [];

        if (!isset($this->whitelist['hashes'])) {
            return $hashes;
        }

        foreach ($this->whitelist['hashes'] as $hash => $entry) {
            // Skip removed hashes
            if ($entry['status'] !== 'active') {
                continue;
            }

            // Apply filters
            if ($type && $entry['type'] !== $type) {
                continue;
            }

            if ($library && $entry['library'] !== $library) {
                continue;
            }

            $hashes[$hash] = $entry;
        }

        return $hashes;
    }

    /**
     * Generate CSP directive string for approved hashes
     * 
     * @param string $type The directive type (script-src, style-src)
     * @return string CSP directive fragment
     */
    public function generateCspDirective(string $type): string
    {
        $contentType = $type === 'script-src' ? 'script' : 'style';
        $hashes = $this->getApprovedHashes($contentType);

        if (empty($hashes)) {
            return "'self'";
        }

        $hashStrings = array_keys($hashes);
        $directive = "'self' " . implode(' ', array_map(fn($h) => "'{$h}'", $hashStrings));

        // Add unsafe-hashes for style attributes if needed
        if ($type === 'style-src' && !empty($hashes)) {
            $directive .= " 'unsafe-hashes'";
        }

        return $directive;
    }

    /**
     * Get whitelist statistics
     * 
     * @return array Statistics about the whitelist
     */
    public function getWhitelistStats(): array
    {
        $stats = [
            'totalHashes' => 0,
            'activeHashes' => 0,
            'removedHashes' => 0,
            'byType' => ['script' => 0, 'style' => 0],
            'byLibrary' => [],
            'lastUpdated' => $this->whitelist['metadata']['lastUpdated'] ?? 'Never'
        ];

        if (!isset($this->whitelist['hashes'])) {
            return $stats;
        }

        foreach ($this->whitelist['hashes'] as $entry) {
            $stats['totalHashes']++;

            if ($entry['status'] === 'active') {
                $stats['activeHashes']++;
                $stats['byType'][$entry['type']]++;

                if (!isset($stats['byLibrary'][$entry['library']])) {
                    $stats['byLibrary'][$entry['library']] = 0;
                }
                $stats['byLibrary'][$entry['library']]++;
            } else {
                $stats['removedHashes']++;
            }
        }

        return $stats;
    }

    /**
     * Export whitelist documentation in markdown format
     * 
     * @return string Markdown documentation
     */
    public function exportDocumentation(): string
    {
        $doc = "# CSP Hash Whitelist\n\n";
        $doc .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

        $stats = $this->getWhitelistStats();
        $doc .= "## Summary\n\n";
        $doc .= "- **Total active hashes:** {$stats['activeHashes']}\n";
        $doc .= "- **Script hashes:** {$stats['byType']['script']}\n";
        $doc .= "- **Style hashes:** {$stats['byType']['style']}\n";
        $doc .= "- **Last updated:** {$stats['lastUpdated']}\n\n";

        $doc .= "## CSP Configuration\n\n";
        $doc .= "Add these directives to your Content Security Policy:\n\n";
        $doc .= "```\n";
        $doc .= "script-src " . $this->generateCspDirective('script-src') . ";\n";
        $doc .= "style-src " . $this->generateCspDirective('style-src') . ";\n";
        $doc .= "```\n\n";

        $doc .= "## Approved Hashes by Library\n\n";
        foreach ($stats['byLibrary'] as $library => $count) {
            $doc .= "### " . strtoupper($library) . " ({$count} hashes)\n\n";
            
            $libraryHashes = $this->getApprovedHashes(null, $library);
            foreach ($libraryHashes as $hash => $entry) {
                $doc .= "**Hash:** `{$hash}`\n\n";
                $doc .= "- **Type:** {$entry['type']}\n";
                $doc .= "- **Description:** {$entry['description']}\n";
                $doc .= "- **Source:** {$entry['metadata']['sourceFile']}\n";
                $doc .= "- **Added:** {$entry['dateAdded']}\n\n";
            }
        }

        return $doc;
    }

    /**
     * Validate hash format
     * 
     * @param string $hash The hash to validate
     * @return bool Whether the hash is valid
     */
    private function isValidHash(string $hash): bool
    {
        return preg_match('/^sha256-[A-Za-z0-9+\/=]+$/', $hash) && strlen($hash) >= 51;
    }

    /**
     * Check if a library is considered safe for hash whitelisting
     * 
     * @param string $library The library name
     * @return bool Whether the library is safe
     */
    private function isLibrarySafe(string $library): bool
    {
        return isset($this->safeLibraries[$library]);
    }

    /**
     * Update library statistics
     * 
     * @param string $library Library name
     * @param string $type Content type
     */
    private function updateLibraryStats(string $library, string $type): void
    {
        if (!isset($this->whitelist['libraries'])) {
            $this->whitelist['libraries'] = [];
        }

        if (!isset($this->whitelist['libraries'][$library])) {
            $this->whitelist['libraries'][$library] = [
                'name' => $this->safeLibraries[$library]['name'] ?? $library,
                'hashCount' => 0,
                'types' => [],
                'lastUpdated' => date('Y-m-d H:i:s')
            ];
        }

        $this->whitelist['libraries'][$library]['hashCount']++;
        $this->whitelist['libraries'][$library]['types'][$type] = 
            ($this->whitelist['libraries'][$library]['types'][$type] ?? 0) + 1;
        $this->whitelist['libraries'][$library]['lastUpdated'] = date('Y-m-d H:i:s');
    }

    /**
     * Load whitelist from configuration file
     */
    private function loadWhitelist(): void
    {
        $configPath = Yii::getAlias($this->whitelistConfigPath);
        
        if (file_exists($configPath)) {
            $this->whitelist = require $configPath;
        } else {
            $this->whitelist = [
                'metadata' => [
                    'version' => '1.0',
                    'created' => date('Y-m-d H:i:s'),
                    'lastUpdated' => date('Y-m-d H:i:s')
                ],
                'hashes' => [],
                'libraries' => []
            ];
        }
    }

    /**
     * Save whitelist to configuration file
     * 
     * @return bool Success status
     */
    private function saveWhitelist(): bool
    {
        $configPath = Yii::getAlias($this->whitelistConfigPath);
        $configDir = dirname($configPath);

        // Ensure directory exists
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        // Update metadata
        $this->whitelist['metadata']['lastUpdated'] = date('Y-m-d H:i:s');

        // Generate PHP configuration file
        $content = "<?php\n\n";
        $content .= "/**\n";
        $content .= " * CSP Hash Whitelist Configuration\n";
        $content .= " * \n";
        $content .= " * This file contains approved SHA-256 hashes for external library content.\n";
        $content .= " * Generated automatically by CspHashWhitelistManager.\n";
        $content .= " * \n";
        $content .= " * Last updated: " . $this->whitelist['metadata']['lastUpdated'] . "\n";
        $content .= " */\n\n";
        $content .= "return " . var_export($this->whitelist, true) . ";\n";

        $success = file_put_contents($configPath, $content) !== false;

        if ($success) {
            // Also generate markdown documentation
            $docPath = dirname($configPath) . '/csp-hash-whitelist.md';
            file_put_contents($docPath, $this->exportDocumentation());
        }

        return $success;
    }

    /**
     * Get current user for audit trail
     * 
     * @return string Current user identifier
     */
    private function getCurrentUser(): string
    {
        // Check if we're in a console application
        if (Yii::$app instanceof \yii\console\Application) {
            return 'console-script';
        }
        
        // Check if user component exists and user is logged in
        if (Yii::$app->has('user') && !Yii::$app->user->isGuest) {
            return Yii::$app->user->identity->username ?? 'user-' . Yii::$app->user->id;
        }
        
        return 'system';
    }
}