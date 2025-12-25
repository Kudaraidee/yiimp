/**
 * CSP Violation Analysis Test
 * 
 * This test runs through all major pages to identify and document
 * actual CSP violations caused by external libraries.
 * 
 * Feature: csp-library-compliance
 * Task: 2. Identify and document current CSP violations
 * Validates: Requirements 2.3
 */

const { test, expect } = require('@playwright/test');
const { 
  waitForPageLoad, 
  collectCspViolations,
  checkInlineStyles 
} = require('../utils/visual-test-helpers');
const fs = require('fs');
const path = require('path');

// Pages to analyze for CSP violations
const PAGES_TO_ANALYZE = [
  { path: '/', name: 'homepage' },
  { path: '/site/mining', name: 'mining' },
  { path: '/admin', name: 'admin' },
  { path: '/explorer', name: 'explorer' },
  { path: '/stats', name: 'stats' },
];

test.describe('CSP Violation Analysis', () => {
  let allViolations = [];
  let allInlineStyles = [];
  let libraryMapping = {};

  test.beforeAll(async () => {
    // Ensure output directory exists
    const outputDir = path.join(__dirname, '../../../yiimp2/docs/csp-violations');
    if (!fs.existsSync(outputDir)) {
      fs.mkdirSync(outputDir, { recursive: true });
    }
  });

  for (const pageInfo of PAGES_TO_ANALYZE) {
    test(`Analyze CSP violations on ${pageInfo.name}`, async ({ page }) => {
      const violations = [];
      const inlineStyles = [];
      
      // Set up console listener for CSP violations
      page.on('console', (msg) => {
        const text = msg.text();
        if (text.includes('Content Security Policy') || 
            text.includes('Refused to execute inline script') ||
            text.includes('Refused to apply inline style') ||
            text.includes('style-src') ||
            text.includes('script-src')) {
          violations.push({
            page: pageInfo.name,
            message: text,
            timestamp: new Date().toISOString(),
            url: page.url()
          });
        }
      });

      // Navigate to page
      console.log(`Analyzing ${pageInfo.name} at ${pageInfo.path}`);
      await page.goto(pageInfo.path);
      await waitForPageLoad(page);

      // Check for inline styles
      const styleCheck = await checkInlineStyles(page);
      if (styleCheck.count > 0) {
        inlineStyles.push({
          page: pageInfo.name,
          count: styleCheck.count,
          elements: styleCheck.elements,
          url: page.url()
        });
      }

      // Wait a bit more to catch any delayed violations
      await page.waitForTimeout(2000);

      // Store results
      allViolations.push(...violations);
      allInlineStyles.push(...inlineStyles);

      console.log(`Found ${violations.length} CSP violations and ${styleCheck.count} inline styles on ${pageInfo.name}`);
      
      // Log violations for this page
      violations.forEach(v => {
        console.log(`  CSP Violation: ${v.message}`);
      });
      
      if (styleCheck.count > 0) {
        console.log(`  Inline styles found: ${styleCheck.count}`);
        styleCheck.elements.slice(0, 3).forEach(el => {
          console.log(`    ${el.tag}${el.id ? '#' + el.id : ''}${el.class ? '.' + el.class.split(' ')[0] : ''}: ${el.style.substring(0, 50)}...`);
        });
      }
    });
  }

  test.afterAll(async () => {
    console.log('\n=== CSP VIOLATION ANALYSIS COMPLETE ===');
    console.log(`Total violations found: ${allViolations.length}`);
    console.log(`Pages with inline styles: ${allInlineStyles.length}`);

    // Analyze violations to identify libraries
    const analysisResults = analyzeViolations(allViolations, allInlineStyles);
    
    // Generate documentation
    await generateViolationReport(analysisResults);
    await generateHashWhitelist(analysisResults);
    
    console.log('\nDocumentation generated in yiimp2/docs/csp-violations/');
  });
});

/**
 * Analyze violations to identify source libraries
 */
function analyzeViolations(violations, inlineStyles) {
  const libraryPatterns = {
    'jquery': [/jquery/i, /\$\(/i, /jQuery/i],
    'tablesorter': [/tablesorter/i, /sorting/i],
    'bootstrap': [/bootstrap/i, /modal/i, /tooltip/i],
    'chartjs': [/chart/i, /Chart\./i],
    'jqplot': [/jqplot/i, /jqPlot/i]
  };

  const results = {
    totalViolations: violations.length,
    totalInlineStyles: inlineStyles.reduce((sum, page) => sum + page.count, 0),
    violationsByPage: {},
    violationsByLibrary: {},
    inlineStylesByPage: {},
    detailedViolations: [],
    hashCandidates: []
  };

  // Process CSP violations
  violations.forEach(violation => {
    const analysis = {
      page: violation.page,
      message: violation.message,
      timestamp: violation.timestamp,
      library: 'unknown',
      type: 'unknown',
      hash: null
    };

    // Determine violation type
    if (violation.message.includes('style-src') || violation.message.includes('inline style')) {
      analysis.type = 'style';
    } else if (violation.message.includes('script-src') || violation.message.includes('inline script')) {
      analysis.type = 'script';
    }

    // Identify library
    for (const [library, patterns] of Object.entries(libraryPatterns)) {
      if (patterns.some(pattern => pattern.test(violation.message))) {
        analysis.library = library;
        break;
      }
    }

    // Extract hash if present
    const hashMatch = violation.message.match(/sha256-([A-Za-z0-9+\/=]+)/);
    if (hashMatch) {
      analysis.hash = 'sha256-' + hashMatch[1];
    }

    results.detailedViolations.push(analysis);

    // Update counters
    if (!results.violationsByPage[violation.page]) {
      results.violationsByPage[violation.page] = 0;
    }
    results.violationsByPage[violation.page]++;

    if (!results.violationsByLibrary[analysis.library]) {
      results.violationsByLibrary[analysis.library] = { count: 0, types: new Set() };
    }
    results.violationsByLibrary[analysis.library].count++;
    results.violationsByLibrary[analysis.library].types.add(analysis.type);

    // Add to hash candidates if from known safe library
    if (analysis.hash && ['tablesorter', 'bootstrap', 'chartjs'].includes(analysis.library)) {
      results.hashCandidates.push({
        hash: analysis.hash,
        library: analysis.library,
        type: analysis.type,
        page: analysis.page,
        safe: true
      });
    }
  });

  // Process inline styles
  inlineStyles.forEach(pageStyles => {
    results.inlineStylesByPage[pageStyles.page] = pageStyles;
  });

  return results;
}

/**
 * Generate comprehensive violation report
 */
async function generateViolationReport(results) {
  const reportPath = path.join(__dirname, '../../../yiimp2/docs/csp-violations/violation-analysis-report.md');
  
  let content = `# CSP Violation Analysis Report\n\n`;
  content += `Generated: ${new Date().toISOString()}\n\n`;
  content += `## Summary\n\n`;
  content += `- **Total CSP violations:** ${results.totalViolations}\n`;
  content += `- **Total inline styles:** ${results.totalInlineStyles}\n`;
  content += `- **Pages analyzed:** ${Object.keys(results.violationsByPage).length}\n`;
  content += `- **Libraries identified:** ${Object.keys(results.violationsByLibrary).length}\n`;
  content += `- **Hash whitelist candidates:** ${results.hashCandidates.length}\n\n`;

  content += `## Violations by Page\n\n`;
  for (const [page, count] of Object.entries(results.violationsByPage)) {
    content += `- **${page}:** ${count} violations\n`;
  }
  content += `\n`;

  content += `## Violations by Library\n\n`;
  for (const [library, info] of Object.entries(results.violationsByLibrary)) {
    content += `### ${library.toUpperCase()}\n\n`;
    content += `- **Count:** ${info.count}\n`;
    content += `- **Types:** ${Array.from(info.types).join(', ')}\n\n`;
  }

  content += `## Detailed Violations\n\n`;
  results.detailedViolations.forEach((violation, index) => {
    content += `### Violation ${index + 1}\n\n`;
    content += `- **Page:** ${violation.page}\n`;
    content += `- **Library:** ${violation.library}\n`;
    content += `- **Type:** ${violation.type}\n`;
    content += `- **Hash:** ${violation.hash || 'N/A'}\n`;
    content += `- **Message:** ${violation.message}\n`;
    content += `- **Timestamp:** ${violation.timestamp}\n\n`;
  });

  content += `## Inline Styles by Page\n\n`;
  for (const [page, styles] of Object.entries(results.inlineStylesByPage)) {
    content += `### ${page}\n\n`;
    content += `**Count:** ${styles.count}\n\n`;
    content += `**Examples:**\n`;
    styles.elements.slice(0, 5).forEach(el => {
      content += `- \`${el.tag}${el.id ? '#' + el.id : ''}${el.class ? '.' + el.class.split(' ')[0] : ''}\`: ${el.style}\n`;
    });
    content += `\n`;
  }

  fs.writeFileSync(reportPath, content);
  console.log(`Violation report saved to: ${reportPath}`);
}

/**
 * Generate hash whitelist documentation
 */
async function generateHashWhitelist(results) {
  const whitelistPath = path.join(__dirname, '../../../yiimp2/docs/csp-violations/hash-whitelist.md');
  
  let content = `# CSP Hash Whitelist Documentation\n\n`;
  content += `Generated: ${new Date().toISOString()}\n\n`;
  content += `This document contains approved SHA-256 hashes for external library content\n`;
  content += `that can be safely whitelisted in the Content Security Policy.\n\n`;

  if (results.hashCandidates.length === 0) {
    content += `## No Hash Candidates Found\n\n`;
    content += `The analysis did not find any specific hashes in the CSP violation messages.\n`;
    content += `This is common when violations are reported as 'inline' without specific hashes.\n\n`;
    content += `## Recommended Actions\n\n`;
    content += `1. Configure external libraries to avoid inline content generation\n`;
    content += `2. Use CSP-compliant alternatives where possible\n`;
    content += `3. For unavoidable violations, consider 'unsafe-inline' with strict nonce policies\n\n`;
  } else {
    content += `## Approved Hashes\n\n`;
    results.hashCandidates.forEach((candidate, index) => {
      content += `### Hash ${index + 1}: ${candidate.library.toUpperCase()}\n\n`;
      content += `**Hash:** \`${candidate.hash}\`\n\n`;
      content += `**Library:** ${candidate.library}\n\n`;
      content += `**Type:** ${candidate.type}\n\n`;
      content += `**Found on:** ${candidate.page}\n\n`;
      content += `**CSP Directive:**\n`;
      content += `\`\`\`\n`;
      if (candidate.type === 'style') {
        content += `style-src 'self' '${candidate.hash}';\n`;
      } else {
        content += `script-src 'self' '${candidate.hash}';\n`;
      }
      content += `\`\`\`\n\n`;
      content += `---\n\n`;
    });
  }

  content += `## Library-Specific Recommendations\n\n`;
  for (const [library, info] of Object.entries(results.violationsByLibrary)) {
    content += `### ${library.toUpperCase()}\n\n`;
    if (library === 'jquery') {
      content += `- Replace \`.css()\` calls with CSS classes\n`;
      content += `- Move event handlers to external files with nonces\n`;
      content += `- Use data attributes instead of inline styles\n`;
    } else if (library === 'tablesorter') {
      content += `- Configure TableSorter to use CSS classes for sorting indicators\n`;
      content += `- Consider alternative sorting libraries with better CSP support\n`;
    } else if (library === 'bootstrap') {
      content += `- Ensure Bootstrap components use external CSS\n`;
      content += `- Configure Bootstrap to avoid inline style generation\n`;
    } else if (library === 'chartjs') {
      content += `- Verify Chart.js configuration for CSP compliance\n`;
      content += `- Use canvas rendering without inline styles\n`;
    }
    content += `\n`;
  }

  fs.writeFileSync(whitelistPath, content);
  console.log(`Hash whitelist saved to: ${whitelistPath}`);
}