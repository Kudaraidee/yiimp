<?php

namespace tests\unit\components;

use Codeception\Test\Unit;
use app\components\ViewHelper;
use yii\helpers\Html;

/**
 * Property-based tests for ViewHelper component
 * 
 * Feature: yiimp2-admin-panel-fixes, Property 1: ViewHelper output is well-formed HTML
 */
class ViewHelperPropertyTest extends Unit
{
    protected function _before()
    {
        parent::_before();
    }
    
    protected function _after()
    {
        parent::_after();
    }
    
    /**
     * Property 1: ViewHelper output is well-formed HTML
     * 
     * For any valid input to ViewHelper methods, the output should be well-formed HTML
     * that can be safely rendered in a browser without XSS vulnerabilities.
     * 
     * Validates: Requirements 5.4
     * 
     * @test
     */
    public function testViewHelperOutputIsWellFormedHtml()
    {
        // Feature: yiimp2-admin-panel-fixes, Property 1: ViewHelper output is well-formed HTML
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Test renderBoxHeader and renderBoxFooter
                $title = $this->generateRandomString();
                ob_start();
                ViewHelper::renderBoxHeader($title);
                ViewHelper::renderBoxFooter();
                $boxHtml = ob_get_clean();
                
                // Verify box HTML is well-formed
                $this->verifyWellFormedHtml($boxHtml, 'renderBoxHeader/Footer', $i, $failures);
                
                // Verify title is properly encoded
                if (strpos($boxHtml, Html::encode($title)) === false) {
                    $failures[] = [
                        'iteration' => $i,
                        'method' => 'renderBoxHeader',
                        'reason' => 'Title not properly encoded',
                        'title' => $title
                    ];
                }
                
                // Test renderDataTable
                $headers = $this->generateRandomHeaders();
                $rows = $this->generateRandomRows(rand(1, 10));
                $tableHtml = ViewHelper::renderDataTable($headers, $rows);
                
                // Verify table HTML is well-formed
                $this->verifyWellFormedHtml($tableHtml, 'renderDataTable', $i, $failures);
                
                // Verify table structure
                if (strpos($tableHtml, '<table') === false || 
                    strpos($tableHtml, '</table>') === false) {
                    $failures[] = [
                        'iteration' => $i,
                        'method' => 'renderDataTable',
                        'reason' => 'Missing table tags'
                    ];
                }
                
                if (strpos($tableHtml, '<thead>') === false || 
                    strpos($tableHtml, '</thead>') === false) {
                    $failures[] = [
                        'iteration' => $i,
                        'method' => 'renderDataTable',
                        'reason' => 'Missing thead tags'
                    ];
                }
                
                if (strpos($tableHtml, '<tbody>') === false || 
                    strpos($tableHtml, '</tbody>') === false) {
                    $failures[] = [
                        'iteration' => $i,
                        'method' => 'renderDataTable',
                        'reason' => 'Missing tbody tags'
                    ];
                }
                
                // Test formatHashrate
                $hashrate = $this->generateRandomHashrate();
                $formatted = ViewHelper::formatHashrate($hashrate);
                
                // Verify hashrate format
                if (!preg_match('/^[\d\.\s]+[kMGTP]?h\/s$/', $formatted)) {
                    $failures[] = [
                        'iteration' => $i,
                        'method' => 'formatHashrate',
                        'reason' => 'Invalid hashrate format',
                        'input' => $hashrate,
                        'output' => $formatted
                    ];
                }
                
                // Test formatTimestamp
                $timestamp = $this->generateRandomTimestamp();
                $formatted = ViewHelper::formatTimestamp($timestamp);
                
                // Verify timestamp format (should be Y-m-d H:i:s by default)
                if (!empty($formatted) && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $formatted)) {
                    $failures[] = [
                        'iteration' => $i,
                        'method' => 'formatTimestamp',
                        'reason' => 'Invalid timestamp format',
                        'input' => $timestamp,
                        'output' => $formatted
                    ];
                }
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Test execution failed',
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ];
            }
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    /**
     * Verify HTML is well-formed
     * 
     * @param string $html
     * @param string $method
     * @param int $iteration
     * @param array &$failures
     */
    protected function verifyWellFormedHtml($html, $method, $iteration, &$failures)
    {
        // Check for balanced tags
        $openTags = [];
        $pattern = '/<(\/?)([\w-]+)[^>]*>/';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $isClosing = $match[1] === '/';
            $tagName = $match[2];
            
            // Skip self-closing tags
            if (in_array($tagName, ['br', 'hr', 'img', 'input', 'meta', 'link'])) {
                continue;
            }
            
            if ($isClosing) {
                if (empty($openTags) || end($openTags) !== $tagName) {
                    $failures[] = [
                        'iteration' => $iteration,
                        'method' => $method,
                        'reason' => 'Unbalanced closing tag',
                        'tag' => $tagName,
                        'html' => substr($html, 0, 200)
                    ];
                    return;
                }
                array_pop($openTags);
            } else {
                $openTags[] = $tagName;
            }
        }
        
        if (!empty($openTags)) {
            $failures[] = [
                'iteration' => $iteration,
                'method' => $method,
                'reason' => 'Unclosed tags',
                'tags' => $openTags,
                'html' => substr($html, 0, 200)
            ];
        }
    }
    
    /**
     * Generate random string for testing
     * 
     * @return string
     */
    protected function generateRandomString()
    {
        $types = [
            // Normal strings
            function() { return 'Test Title ' . rand(1, 1000); },
            // Strings with special characters
            function() { return 'Title <script>alert("xss")</script>'; },
            function() { return 'Title & Special " Characters \''; },
            function() { return 'Title with <b>HTML</b> tags'; },
            // Unicode strings
            function() { return 'Título con ñ y ü'; },
            function() { return '标题 中文'; },
            // Empty and whitespace
            function() { return ''; },
            function() { return '   '; },
        ];
        
        $generator = $types[array_rand($types)];
        return $generator();
    }
    
    /**
     * Generate random table headers
     * 
     * @return array
     */
    protected function generateRandomHeaders()
    {
        $count = rand(2, 6);
        $headers = [];
        for ($i = 0; $i < $count; $i++) {
            $headers[] = $this->generateRandomString();
        }
        return $headers;
    }
    
    /**
     * Generate random table rows
     * 
     * @param int $count
     * @return array
     */
    protected function generateRandomRows($count)
    {
        $rows = [];
        $colCount = rand(2, 6);
        for ($i = 0; $i < $count; $i++) {
            $row = [];
            for ($j = 0; $j < $colCount; $j++) {
                $row[] = $this->generateRandomString();
            }
            $rows[] = $row;
        }
        return $rows;
    }
    
    /**
     * Generate random hashrate value
     * 
     * @return float
     */
    protected function generateRandomHashrate()
    {
        $types = [
            function() { return 0; },
            function() { return rand(1, 999); }, // h/s
            function() { return rand(1000, 999999); }, // Kh/s
            function() { return rand(1000000, 999999999); }, // Mh/s
            function() { return rand(1000000000, 999999999999); }, // Gh/s
            function() { return rand(1000000000000, 999999999999999); }, // Th/s
            function() { return null; },
        ];
        
        $generator = $types[array_rand($types)];
        return $generator();
    }
    
    /**
     * Generate random timestamp
     * 
     * @return int|string
     */
    protected function generateRandomTimestamp()
    {
        $types = [
            function() { return time(); },
            function() { return time() - rand(0, 86400 * 365); }, // Past year
            function() { return strtotime('2024-01-01 12:00:00'); },
            function() { return 0; },
            function() { return ''; },
            function() { return '2024-01-01 12:00:00'; }, // Already formatted
        ];
        
        $generator = $types[array_rand($types)];
        return $generator();
    }
    
    /**
     * Test renderBoxHeader with options
     * 
     * @test
     */
    public function testRenderBoxHeaderWithOptions()
    {
        // Feature: yiimp2-admin-panel-fixes, Property 1: ViewHelper output is well-formed HTML (box header options)
        
        ob_start();
        ViewHelper::renderBoxHeader('Test Title', ['class' => 'custom-box']);
        ViewHelper::renderBoxFooter();
        $html = ob_get_clean();
        
        $this->assertStringContainsString('custom-box', $html);
        $this->assertStringContainsString('Test Title', $html);
    }
    
    /**
     * Test renderDataTable with sortable option
     * 
     * @test
     */
    public function testRenderDataTableWithSortable()
    {
        // Feature: yiimp2-admin-panel-fixes, Property 1: ViewHelper output is well-formed HTML (sortable table)
        
        $headers = ['Name', 'Value', 'Status'];
        $rows = [
            ['Item 1', '100', 'Active'],
            ['Item 2', '200', 'Inactive']
        ];
        
        $html = ViewHelper::renderDataTable($headers, $rows, ['sortable' => true]);
        
        $this->assertStringContainsString('sortable', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
    }
    
    /**
     * Test formatHashrate with different precisions
     * 
     * @test
     */
    public function testFormatHashrateWithPrecision()
    {
        // Feature: yiimp2-admin-panel-fixes, Property 1: ViewHelper output is well-formed HTML (hashrate precision)
        
        $hashrate = 1234567890;
        
        $formatted1 = ViewHelper::formatHashrate($hashrate, 1);
        $formatted2 = ViewHelper::formatHashrate($hashrate, 2);
        
        $this->assertStringContainsString('h/s', $formatted1);
        $this->assertStringContainsString('h/s', $formatted2);
        $this->assertNotEquals($formatted1, $formatted2);
    }
    
    /**
     * Test formatTimestamp with custom format
     * 
     * @test
     */
    public function testFormatTimestampWithCustomFormat()
    {
        // Feature: yiimp2-admin-panel-fixes, Property 1: ViewHelper output is well-formed HTML (timestamp format)
        
        $timestamp = strtotime('2024-01-15 14:30:45');
        
        $formatted1 = ViewHelper::formatTimestamp($timestamp);
        $formatted2 = ViewHelper::formatTimestamp($timestamp, 'Y-m-d');
        
        $this->assertEquals('2024-01-15 14:30:45', $formatted1);
        $this->assertEquals('2024-01-15', $formatted2);
    }
    
    /**
     * Test XSS protection in all methods
     * 
     * @test
     */
    public function testXssProtection()
    {
        // Feature: yiimp2-admin-panel-fixes, Property 1: ViewHelper output is well-formed HTML (XSS protection)
        
        $xssString = '<script>alert("xss")</script>';
        
        // Test box header
        ob_start();
        ViewHelper::renderBoxHeader($xssString);
        ViewHelper::renderBoxFooter();
        $boxHtml = ob_get_clean();
        
        $this->assertStringNotContainsString('<script>', $boxHtml);
        $this->assertStringContainsString(Html::encode($xssString), $boxHtml);
        
        // Test data table
        $headers = [$xssString];
        $rows = [[$xssString]];
        $tableHtml = ViewHelper::renderDataTable($headers, $rows);
        
        $this->assertStringNotContainsString('<script>', $tableHtml);
        $this->assertStringContainsString(Html::encode($xssString), $tableHtml);
    }
}
