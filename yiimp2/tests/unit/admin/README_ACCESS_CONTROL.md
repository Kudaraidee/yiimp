# Access Control Property Test

## Overview

This document describes the property-based test for access control enforcement in the AdminController.

## Property 45: Access Control Enforcement

**Statement**: For any protected controller action (admin functions), the system should enforce access control using Yii2 filters and deny access to unauthorized users.

**Validates**: Requirements 13.6

## Test Implementation

The test is implemented in `AccessControlPropertyTest.php` and verifies that:

1. **Guest users** (not logged in) are denied access to all protected admin actions
2. **Non-admin authenticated users** are denied access to all protected admin actions  
3. **Admin users** are granted access to all protected admin actions

## Test Strategy

The test uses a property-based approach with 100 iterations:

1. For each iteration, randomly select a protected admin action
2. Test access with three user types:
   - Guest (not authenticated)
   - Non-admin (authenticated but not admin)
   - Admin (authenticated with admin privileges)
3. Verify that access control behaves correctly for each user type

## Protected Actions Tested

The test covers all admin actions including:

- Dashboard and statistics
- Coin management (CRUD operations)
- User management
- Worker monitoring
- Payment monitoring
- Earnings monitoring
- Exchange management
- Connection monitoring
- Security monitoring (botnets, monsters)
- Cache management
- Version monitoring

## Access Control Implementation

The AdminController uses Yii2's AccessControl filter with the following rules:

1. **Login action**: Allowed for all users
2. **All other actions**: Require authentication AND admin privileges
   - Checked via `Yii::$app->user->identity->is_admin === true`
3. **Deny callback**: Redirects guests to login, throws ForbiddenHttpException for non-admins

## Test Results

The test verifies that:

- ✅ Guest users are redirected to login or receive 403 Forbidden
- ✅ Non-admin users receive 403 Forbidden
- ✅ Admin users can access all protected actions

## Running the Test

```bash
cd yiimp2
vendor/bin/codecept run unit admin/AccessControlPropertyTest
```

## Test Configuration

The test requires the following constants to be defined in `tests/_data/serverconfig.test.php`:

- `YAAMP_ADMIN_USER`: Admin username
- `YAAMP_ADMIN_PASS`: Admin password

These are used to create the admin user for testing.

## Property-Based Testing Approach

This test uses property-based testing to ensure that access control works correctly across:

- All protected admin actions (40+ actions)
- All user types (guest, non-admin, admin)
- Multiple iterations (100) to catch edge cases

The property holds if and only if:
- No guest or non-admin user can access any protected action
- All admin users can access all protected actions

## Related Files

- `yiimp2/controllers/AdminController.php`: Controller with access control
- `yiimp2/models/User.php`: User identity model
- `yiimp2/models/LoginForm.php`: Login form model
- `yiimp2/tests/_data/serverconfig.test.php`: Test configuration
