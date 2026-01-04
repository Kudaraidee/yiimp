/**
 * Homepage CSP Violation Analysis
 * 
 * Focused analysis of CSP violations on the homepage where most violations occur.
 * 
 * Feature: csp-library-compliance
 * Task: 2. Identify and document current CSP violations
 */

const { test, expect } = require('@playwright/test');
const { waitForPageLoad } = require('../utils/visual-test-helpers');
const fs = require('fs');
const path = require('path');

test.describe('Homepage CSP Analysis', () => {
  test('Capture all CSP violations on homepage', async ({ page }) => {
    const violations = [];
    
    // Set up comprehensive console listener
    page.on('console', (msg) => {
      const text = msg.text();
      // Capture all CSP-related messages
      if (text.includes('Content Security Policy') || 
          text.includes('CSP') ||
          text.includes('Refused to') ||
          text.includes('violates the following') ||
          text.includes('style-src') ||
          text.includes('script-src') ||
          text.includes('sha256-')) {
        violations.push({
          type: msg.type(),
          text: text,
          timestamp: new Date().toISOString()
        });
        console.log(`CSP Violation: ${text}`);
      }
    });

    // Navigate to homepage
    console.log('Loading homepage...');
    await page.goto('/');
    await waitForPageLoad(page);
    
    // Wait longer to catch all violations
    await page.waitForTimeout(5000);
    
    console.log(`\nTotal violations captured: ${violations.length}`);
    
    // Analyze violations
    const analysis = analyzeHomepageViolations(violations);
    
    // Generate detailed report
    await generateDetailedReport(analysis);
    
    console.log('\nDetailed analysis complete!');
  });
});

function analyzeHomepageViolations(violations) {
  const analysis = {
    totalViolations: violations.length,
    violationsByType: {},
    hashesFound: [],
    librariesIdentified: {},
    detailedViolations: []
  };
  
  violations.forEach((violation, index) => {
    const detail = {
      id: index + 1,
      message: violation.text,
      timestamp: violation.timestamp,
      type: 'unknown',
      library: 'unknown',
      hash: null,
      source: null
    };
    
    // Extract violation type
    if (violation.text.includes('style-src') || violation.text.includes('inline style')) {
      detail.type = 'style';
    } else if (violation.text.includes('script-src') || violation.text.includes('inline script')) {
      detail.type = 'script';
    }
    
    // Extract hash
    const hashMatch = violation.text.match(/sha256-([A-Za-z0-9+\/=]+)/);
    if (hashMatch) {
      detail.hash = 'sha256-' + hashMatch[1];
      analysis.hashesFound.push(detail.hash);
    }
    
    // Extract source file
    const sourceMatch = violation.text.match(/jquery\.min\.js|tablesorter|bootstrap|chart\.js/i);
    if (sourceMatch) {
      detail.source = sourceMatch[0];
    }
    
    // Identify library
    if (violation.text.includes('jquery')) {
      detail.library = 'jquery';
    } else if (violation.text.includes('tablesorter')) {
      detail.library = 'tablesorter';
    } else if (violation.text.includes('bootstrap')) {
      detail.library = 'bootstrap';
    } else if (violation.text.includes('chart')) {
      detail.library = 'chartjs';
    }
    
    analysis.detailedViolations.push(detail);
    
    // Update counters
    if (!analysis.violationsByType[detail.type]) {
      analysis.violationsByType[detail.type] = 0;
    }
    analysis.violationsByType[detail.type]++;
    
    if (!analysis.librariesIdentified[detail.library]) {
      analysis.librariesIdentified[detail.library] = { count: 0, hashes: [] };
    }
    analysis.librariesIdentified[detail.library].count++;
    if (detail.hash) {
      analysis.librariesIdentified[detail.library].hashes.push(detail.hash);
    }
  });
  
  // Remove duplicate hashes
  analysis.hashesFound = [...new Set(analysis.hashesFound)];
  
  return analysis;
}

async function generateDetailedReport(analysis) {
  const outputDir = path.join(__dirname, '../../../yiimp2/docs/csp-violations');
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }
  
  // Generate comprehensive report
  let content = `# Comprehensive CSP Violation Analysis\n\n`;
  content += `Generated: ${new Date().toISOString()}\n\n`;
  content += `## Executive Summary\n\n`;
  content += `This analysis identified **${analysis.totalViolations} CSP violations** on the homepage, `;
  content += `with **${analysis.hashesFound.length} unique hashes** that can be whitelisted.\n\n`;
  
  content += `### Key Findings\n\n`;
  content += `- **Total violations:** ${analysis.totalViolations}\n`;
  content += `- **Unique hashes found:** ${analysis.hashesFound.length}\n`;
  content += `- **Libraries involved:** ${Object.keys(analysis.librariesIdentified).length}\n`;
  content += `- **Violation types:** ${Object.keys(analysis.violationsByType).join(', ')}\n\n`;
  
  content += `## Violations by Type\n\n`;
  for (const [type, count] of Object.entries(analysis.violationsByType)) {
    content += `- **${type}:** ${count} violations\n`;
  }
  content += `\n`;
  
  content += `## Libraries Analysis\n\n`;
  for (const [library, info] of Object.entries(analysis.librariesIdentified)) {
    content += `### ${library.toUpperCase()}\n\n`;
    content += `- **Violations:** ${info.count}\n`;
    if (info.hashes.length > 0) {
      content += `- **Hashes found:** ${info.hashes.length}\n`;
      info.hashes.forEach(hash => {
        content += `  - \`${hash}\`\n`;
      });
    }
    content += `\n`;
  }
  
  content += `## Hash Whitelist\n\n`;
  content += `The following hashes were identified and can be added to the CSP policy:\n\n`;
  analysis.hashesFound.forEach((hash, index) => {
    content += `### Hash ${index + 1}\n\n`;
    content += `**Hash:** \`${hash}\`\n\n`;
    
    // Find the violation that contains this hash
    const violation = analysis.detailedViolations.find(v => v.hash === hash);
    if (violation) {
      content += `**Type:** ${violation.type}\n\n`;
      content += `**Library:** ${violation.library}\n\n`;
      content += `**Source:** ${violation.source || 'Unknown'}\n\n`;
      content += `**CSP Directive:**\n`;
      content += `\`\`\`\n`;
      if (violation.type === 'style') {
        content += `style-src 'self' '${hash}' 'unsafe-hashes';\n`;
      } else {
        content += `script-src 'self' '${hash}';\n`;
      }
      content += `\`\`\`\n\n`;
    }
    content += `---\n\n`;
  });
  
  content += `## Detailed Violation Log\n\n`;
  analysis.detailedViolations.forEach(violation => {
    content += `### Violation ${violation.id}\n\n`;
    content += `- **Type:** ${violation.type}\n`;
    content += `- **Library:** ${violation.library}\n`;
    content += `- **Hash:** ${violation.hash || 'N/A'}\n`;
    content += `- **Source:** ${violation.source || 'N/A'}\n`;
    content += `- **Timestamp:** ${violation.timestamp}\n`;
    content += `- **Message:** ${violation.message}\n\n`;
  });
  
  content += `## Implementation Recommendations\n\n`;
  content += `### Immediate Actions\n\n`;
  content += `1. **Add identified hashes to CSP policy** - All ${analysis.hashesFound.length} hashes are from known libraries\n`;
  content += `2. **Update CSP configuration** - Include 'unsafe-hashes' for style attributes\n`;
  content += `3. **Test thoroughly** - Verify all functionality works after CSP updates\n\n`;
  
  content += `### Long-term Solutions\n\n`;
  content += `1. **jQuery optimization** - Replace .css() calls with CSS classes\n`;
  content += `2. **Library updates** - Check for CSP-compliant versions\n`;
  content += `3. **Code refactoring** - Move inline handlers to external files\n\n`;
  
  const reportPath = path.join(outputDir, 'comprehensive-violation-analysis.md');
  fs.writeFileSync(reportPath, content);
  console.log(`Comprehensive report saved to: ${reportPath}`);
  
  // Also generate a simple hash list for easy CSP configuration
  const hashListPath = path.join(outputDir, 'csp-hash-list.txt');
  let hashList = `# CSP Hash List for Whitelisting\n`;
  hashList += `# Generated: ${new Date().toISOString()}\n\n`;
  hashList += `# Add these hashes to your CSP policy:\n\n`;
  
  const styleHashes = [];
  const scriptHashes = [];
  
  analysis.detailedViolations.forEach(v => {
    if (v.hash) {
      if (v.type === 'style') {
        styleHashes.push(v.hash);
      } else {
        scriptHashes.push(v.hash);
      }
    }
  });
  
  if (styleHashes.length > 0) {
    hashList += `# Style hashes (requires 'unsafe-hashes'):\n`;
    hashList += `style-src 'self' ${[...new Set(styleHashes)].map(h => `'${h}'`).join(' ')} 'unsafe-hashes';\n\n`;
  }
  
  if (scriptHashes.length > 0) {
    hashList += `# Script hashes:\n`;
    hashList += `script-src 'self' ${[...new Set(scriptHashes)].map(h => `'${h}'`).join(' ')};\n\n`;
  }
  
  fs.writeFileSync(hashListPath, hashList);
  console.log(`Hash list saved to: ${hashListPath}`);
}