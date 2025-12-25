#!/usr/bin/env node

/**
 * Check PHP view files for inline style attributes
 * This script enforces CSP compliance by preventing style= attributes in PHP templates
 */

const fs = require('fs');
const path = require('path');
const { glob } = require('glob');

// ANSI color codes for terminal output
const colors = {
  reset: '\x1b[0m',
  red: '\x1b[31m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  cyan: '\x1b[36m',
  bold: '\x1b[1m'
};

/**
 * Find all inline style attributes in a file
 * @param {string} filePath - Path to the PHP file
 * @returns {Array} Array of violations with line numbers and content
 */
function findInlineStyles(filePath) {
  const content = fs.readFileSync(filePath, 'utf8');
  const lines = content.split('\n');
  const violations = [];

  // Regex to match style= attributes (both single and double quotes)
  // Matches: style="..." or style='...'
  const styleRegex = /\bstyle\s*=\s*["'][^"']*["']/gi;

  lines.forEach((line, index) => {
    const matches = line.match(styleRegex);
    if (matches) {
      matches.forEach(match => {
        violations.push({
          line: index + 1,
          column: line.indexOf(match) + 1,
          match: match.substring(0, 80) + (match.length > 80 ? '...' : ''),
          fullLine: line.trim()
        });
      });
    }
  });

  return violations;
}

/**
 * Main execution function
 */
async function main() {
  console.log(`${colors.bold}${colors.cyan}Checking PHP files for inline styles...${colors.reset}\n`);

  // Define patterns to search for PHP view files
  const patterns = [
    'yiimp2/views/**/*.php',
    'yiimp2/widgets/**/*.php'
  ];

  // Patterns to exclude
  const ignorePatterns = [
    '**/vendor/**',
    '**/runtime/**',
    '**/tests/**'
  ];

  let totalFiles = 0;
  let filesWithViolations = 0;
  let totalViolations = 0;
  const violationsByFile = {};

  // Process each pattern
  for (const pattern of patterns) {
    try {
      const files = await glob(pattern, {
        ignore: ignorePatterns,
        nodir: true
      });

      for (const file of files) {
        totalFiles++;
        const violations = findInlineStyles(file);

        if (violations.length > 0) {
          filesWithViolations++;
          totalViolations += violations.length;
          violationsByFile[file] = violations;
        }
      }
    } catch (error) {
      // Pattern might not match any files, continue
      continue;
    }
  }

  // Report results
  if (totalViolations === 0) {
    console.log(`${colors.green}✓ No inline styles found!${colors.reset}`);
    console.log(`${colors.cyan}Checked ${totalFiles} PHP files.${colors.reset}\n`);
    process.exit(0);
  } else {
    console.log(`${colors.red}${colors.bold}✗ Found ${totalViolations} inline style violation(s) in ${filesWithViolations} file(s):${colors.reset}\n`);

    // Print violations grouped by file
    for (const [file, violations] of Object.entries(violationsByFile)) {
      console.log(`${colors.bold}${file}${colors.reset}`);
      violations.forEach(violation => {
        console.log(`  ${colors.yellow}${violation.line}:${violation.column}${colors.reset}  ${colors.red}Inline style attribute found${colors.reset}`);
        console.log(`    ${colors.cyan}${violation.match}${colors.reset}`);
      });
      console.log('');
    }

    console.log(`${colors.red}${colors.bold}Error: Inline styles violate CSP policy${colors.reset}`);
    console.log(`${colors.yellow}Use CSS classes instead of style= attributes${colors.reset}`);
    console.log(`${colors.yellow}See: .kiro/specs/csp-inline-styles-removal/design.md${colors.reset}\n`);

    process.exit(1);
  }
}

// Run the script
main().catch(error => {
  console.error(`${colors.red}Error: ${error.message}${colors.reset}`);
  process.exit(1);
});
