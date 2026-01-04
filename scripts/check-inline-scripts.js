#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const viewsDir = path.join(__dirname, '..', 'yiimp2', 'views');

function findPhpFiles(dir) {
    let results = [];
    const files = fs.readdirSync(dir);
    
    for (const file of files) {
        const filePath = path.join(dir, file);
        const stat = fs.statSync(filePath);
        
        if (stat.isDirectory()) {
            results = results.concat(findPhpFiles(filePath));
        } else if (file.endsWith('.php')) {
            results.push(filePath);
        }
    }
    
    return results;
}

function checkForInlineScripts(filePath) {
    const content = fs.readFileSync(filePath, 'utf8');
    const lines = content.split('\n');
    const issues = [];
    
    // Pattern to match <script> tags that are NOT using CspHelper
    // We're looking for direct <script> tags in the HTML
    const scriptTagPattern = /<script(?:\s+[^>]*)?>/gi;
    
    lines.forEach((line, index) => {
        // Skip lines that are using CspHelper
        if (line.includes('CspHelper::beginScript()') || 
            line.includes('CspHelper::script(') ||
            line.includes('registerJsFile') ||
            line.includes('registerJs(')) {
            return;
        }
        
        // Check for direct <script> tags
        const matches = line.match(scriptTagPattern);
        if (matches) {
            // Check if the line has a nonce attribute
            const hasNonce = line.includes('nonce=');
            if (!hasNonce) {
                issues.push({
                    line: index + 1,
                    content: line.trim(),
                    type: 'script without nonce'
                });
            }
        }
    });
    
    return issues;
}

console.log('Checking PHP files for inline scripts without nonces...\n');

const phpFiles = findPhpFiles(viewsDir);
let totalIssues = 0;
const filesWithIssues = [];

for (const file of phpFiles) {
    const issues = checkForInlineScripts(file);
    if (issues.length > 0) {
        totalIssues += issues.length;
        filesWithIssues.push({ file, issues });
    }
}

if (totalIssues === 0) {
    console.log('✓ No inline scripts without nonces found!');
    console.log(`Checked ${phpFiles.length} PHP files.`);
    process.exit(0);
} else {
    console.log(`✗ Found ${totalIssues} inline script(s) without nonces in ${filesWithIssues.length} file(s):\n`);
    
    for (const { file, issues } of filesWithIssues) {
        const relativePath = path.relative(path.join(__dirname, '..'), file);
        console.log(`${relativePath}:`);
        for (const issue of issues) {
            console.log(`  Line ${issue.line}: ${issue.content}`);
        }
        console.log('');
    }
    
    process.exit(1);
}
