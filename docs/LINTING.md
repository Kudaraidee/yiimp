# Linting Configuration

This document describes the linting setup for maintaining CSP-compliant code in the yiimp2 project.

## Overview

The project uses automated linting to enforce:
- **CSS quality standards** via stylelint
- **No inline styles in PHP templates** via custom script
- **CSP compliance** by preventing `style=` attributes

## Setup

### Install Dependencies

```bash
npm install
```

This installs:
- `stylelint` - CSS linter
- `stylelint-config-standard` - Standard CSS rules
- `glob` - File pattern matching for PHP checks

## Usage

### Lint CSS Files

Check all CSS files for style issues:

```bash
npm run lint:css
```

Auto-fix CSS issues where possible:

```bash
npm run lint:fix
```

### Check PHP Files for Inline Styles

Scan all PHP view templates for `style=` attributes:

```bash
npm run lint:php-styles
```

This script will:
- Search `yiimp2/views/**/*.php` and `yiimp2/widgets/**/*.php` (yiimp2 only)
- Report any `style=` attributes found
- Exit with error code 1 if violations exist (fails CI/CD)

**Note:** Currently only checks the yiimp2 directory. The legacy `web/` directory is not checked.

### Run All Linting

```bash
npm run lint
```

This runs both CSS linting and PHP inline style checks.

## CI/CD Integration

### GitHub Actions

The linting checks run automatically on:
- Push to `main`, `master`, or `develop` branches
- Pull requests to these branches

See `.github/workflows/lint.yml` for the complete workflow.

The workflow includes:
1. **CSS Linting** - Validates CSS files with stylelint
2. **PHP Inline Style Check** - Ensures no `style=` attributes in templates
3. **C++ Build Check** - Validates stratum server compilation
4. **PHP Syntax Check** - Validates PHP syntax across the codebase

### Local Pre-commit Hook (Optional)

To run linting before each commit, create `.git/hooks/pre-commit`:

```bash
#!/bin/bash
npm run lint
if [ $? -ne 0 ]; then
  echo "Linting failed. Commit aborted."
  exit 1
fi
```

Make it executable:

```bash
chmod +x .git/hooks/pre-commit
```

## Configuration Files

### `.stylelintrc.json`

Stylelint configuration for CSS files. Extends `stylelint-config-standard` with project-specific rules:

- Allows flexible class naming (no pattern enforcement)
- Disables descending specificity warnings
- Uses legacy color function notation for compatibility

### `.stylelintignore`

Excludes vendor code, runtime files, and third-party libraries from linting.

### `scripts/check-php-inline-styles.js`

Custom Node.js script that:
- Scans PHP view files for `style=` attributes
- Reports violations with file, line, and column numbers
- Exits with error code for CI/CD integration

## CSP Compliance

Inline styles violate Content Security Policy (CSP) best practices. Instead of:

```php
<!-- ❌ BAD: Inline style -->
<div style="background-color: #ffcc00; padding: 10px;">
```

Use CSS classes:

```php
<!-- ✅ GOOD: CSS class -->
<div class="algo-bg-sha256 p-2">
```

See `.kiro/specs/csp-inline-styles-removal/design.md` for the complete styling architecture.

## Troubleshooting

### "Cannot find module 'glob'"

Run `npm install` to install dependencies.

### "stylelint: command not found"

Use npm scripts instead of calling stylelint directly:
```bash
npm run lint:css
```

### False Positives in PHP Check

If the script incorrectly flags a comment or string containing "style=", update the regex in `scripts/check-php-inline-styles.js`.

### Linting Passes Locally but Fails in CI

Ensure you've committed all CSS files and the linting configuration:
```bash
git add .stylelintrc.json .stylelintignore package.json package-lock.json
git commit -m "Add linting configuration"
```

## Adding New CSS Files

New CSS files in `yiimp2/web/css/` are automatically linted. No configuration changes needed.

## Disabling Rules (Not Recommended)

To disable a specific stylelint rule for a file, add a comment at the top:

```css
/* stylelint-disable rule-name */
```

To disable for a specific line:

```css
color: red; /* stylelint-disable-line color-named */
```

**Note:** Disabling rules should be rare and well-justified.

## Resources

- [Stylelint Documentation](https://stylelint.io/)
- [CSP Inline Styles Removal Spec](.kiro/specs/csp-inline-styles-removal/)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
