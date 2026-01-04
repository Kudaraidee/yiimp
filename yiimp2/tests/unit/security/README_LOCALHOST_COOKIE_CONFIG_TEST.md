# Localhost Cookie Configuration Property Tests

## Overview

This document describes the property-based tests for cookie configuration in the localhost redirect loop fix feature.

## Test File

`LocalhostCookieConfigPropertyTest.php`

## Properties Tested

### Property 1: HTTP Cookie Configuration
**Validates: Requirements 1.4, 2.1**

For any HTTP request to localhost, the session cookie secure flag should be set to false to allow the browser to send the cookie.

**Test Coverage:**
- Tests 100 iterations across 4 different localhost configurations
- Verifies `secure` flag is `false` for HTTP localhost
- Verifies `httponly` flag is always `true`
- Verifies `sameSite` is `null` for HTTP

**Localhost Configurations Tested:**
1. `localhost:8090` with SERVER_NAME=localhost
2. `localhost` with SERVER_NAME=localhost
3. `127.0.0.1:8090` with SERVER_NAME=127.0.0.1
4. `127.0.0.1` with SERVER_NAME=127.0.0.1

### Property 2: HTTPS Cookie Configuration
**Validates: Requirements 1.5, 2.2**

For any HTTPS request, the session cookie secure flag should be set to true to ensure cookies are only sent over secure connections.

**Test Coverage:**
- Tests 100 iterations across 4 different HTTPS detection methods
- Verifies `secure` flag is `true` for HTTPS
- Verifies `httponly` flag is always `true`
- Verifies `sameSite` is `'Lax'` for HTTPS

**HTTPS Detection Methods Tested:**
1. Direct HTTPS: `$_SERVER['HTTPS'] = 'on'`
2. Direct HTTPS (alternate): `$_SERVER['HTTPS'] = '1'`
3. Proxy header: `$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https'`
4. Proxy header (alternate): `$_SERVER['HTTP_X_FORWARDED_SSL'] = 'on'`

### Property 5: Session Cookie Presence
**Validates: Requirements 1.3, 2.1, 2.2**

For any successful page load, a session cookie should be present in the response when the protocol matches the secure flag setting.

**Test Coverage:**
- Tests 100 iterations across HTTP and HTTPS scenarios
- Verifies session activates successfully
- Verifies session ID is generated
- Verifies cookie params match the protocol

**Scenarios Tested:**
1. HTTP localhost with `secure=false`
2. HTTPS with `secure=true`

## Running the Tests

```bash
cd yiimp2
php vendor/bin/codecept run unit tests/unit/security/LocalhostCookieConfigPropertyTest.php --verbose
```

## Test Results

All tests pass successfully:
- ✔ testHttpCookieConfiguration (100 iterations)
- ✔ testHttpsCookieConfiguration (100 iterations)
- ✔ testSessionCookiePresence (100 iterations)

## Implementation Details

### HTTPS Detection Logic

The tests use the same HTTPS detection logic as `yiimp2/config/web.php`:

```php
protected function detectHttps()
{
    $isHttps = false;
    
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $isHttps = true;
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $isHttps = true;
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        $isHttps = true;
    } elseif (getenv('YIIMP_FORCE_HTTPS') === 'true' || getenv('YIIMP_FORCE_HTTPS') === '1') {
        $isHttps = true;
    }
    
    return $isHttps;
}
```

### Cookie Configuration

The tests verify that session cookies are configured as follows:

**For HTTP:**
```php
'cookieParams' => [
    'httponly' => true,
    'secure' => false,
    'sameSite' => null,
]
```

**For HTTPS:**
```php
'cookieParams' => [
    'httponly' => true,
    'secure' => true,
    'sameSite' => 'Lax',
]
```

## Key Findings

1. **Case Sensitivity**: Yii2 uses lowercase `'samesite'` in the cookie params array, not `'sameSite'`
2. **Null Handling**: When `sameSite` is set to `null`, it may not appear in the cookie params array
3. **Localhost Detection**: The system correctly identifies localhost by SERVER_NAME, SERVER_ADDR, and HTTP_HOST
4. **HTTPS Detection**: Multiple methods are supported for HTTPS detection (direct, proxy headers, environment variable)

## Related Files

- `yiimp2/config/web.php` - Main configuration file with HTTPS detection and cookie setup
- `yiimp2/tests/unit/security/HttpsCookieSecurityPropertyTest.php` - Related HTTPS cookie tests from csrf-validation-fix feature
- `.kiro/specs/localhost-redirect-loop-fix/design.md` - Design document with property definitions
- `.kiro/specs/localhost-redirect-loop-fix/requirements.md` - Requirements document

## Troubleshooting

If tests fail:

1. **Check Environment Variables**: Ensure `YIIMP_FORCE_HTTPS` is not set or is set to `false`
2. **Check Server Variables**: Verify `$_SERVER` variables are being set correctly in tests
3. **Check Cookie Params**: Use `var_dump($session->getCookieParams())` to inspect actual values
4. **Check Case**: Remember that Yii2 uses lowercase keys in cookie params array

## Future Enhancements

Potential improvements to these tests:

1. Add tests for edge cases (empty SERVER_NAME, missing HTTP_HOST, etc.)
2. Add tests for IPv6 localhost (::1)
3. Add tests for custom ports
4. Add integration tests that verify actual HTTP responses contain correct Set-Cookie headers
