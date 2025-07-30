# Authentication and Authorization System

This document describes the authentication and authorization system implemented for the Jewelry SaaS Platform.

## Overview

The system provides:
- Laravel Sanctum API authentication with tenant context
- Role-based access control (RBAC) with permissions
- Two-factor authentication (2FA) support
- Session management with device tracking
- Comprehensive security features

## Models

### User Model
- Extends Laravel's Authenticatable
- Includes HasApiTokens trait for Sanctum
- Supports two-factor authentication
- Tracks login sessions and device information
- Provides permission checking methods

### Role Model
- Manages user roles with display names and descriptions
- Supports system roles (cannot be deleted)
- Provides permission management methods
- Includes active/inactive status

### Permission Model
- Defines granular permissions grouped by functionality
- Supports active/inactive status
- Provides grouped permission retrieval

### UserSession Model
- Tracks user login sessions across devices
- Stores device information and location data
- Supports remote logout functionality

## Services

### AuthService
- Handles login/logout operations
- Manages two-factor authentication flow
- Creates and manages user sessions
- Provides device tracking and location detection

### TwoFactorService
- Enables/disables 2FA for users
- Generates QR codes for authenticator apps
- Manages recovery codes
- Verifies TOTP codes

## Middleware

### CheckPermission
- Validates user permissions for routes
- Returns JSON error responses for API endpoints
- Supports tenant context

### CheckRole
- Validates user roles for routes
- Supports multiple role checking
- Returns JSON error responses

### UpdateSessionActivity
- Updates user session activity timestamps
- Runs automatically for authenticated requests

## API Endpoints

### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/verify-2fa` - Two-factor verification
- `POST /api/auth/logout` - Logout current session
- `POST /api/auth/logout-all` - Logout all sessions
- `POST /api/auth/logout-session` - Logout specific session
- `GET /api/auth/me` - Get current user info
- `GET /api/auth/sessions` - Get active sessions

### Two-Factor Authentication
- `GET /api/2fa/status` - Get 2FA status
- `POST /api/2fa/enable` - Enable 2FA
- `POST /api/2fa/disable` - Disable 2FA
- `POST /api/2fa/regenerate-codes` - Regenerate recovery codes

## Usage Examples

### Login
```javascript
const response = await fetch('/api/auth/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        email: 'user@example.com',
        password: 'password123'
    })
});

const data = await response.json();
if (data.success) {
    if (data.data.requires_2fa) {
        // Handle 2FA verification
        const twoFaResponse = await fetch('/api/auth/verify-2fa', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: data.data.user_id,
                code: '123456',
                temp_token: data.data.temp_token
            })
        });
    } else {
        // Store token and user data
        localStorage.setItem('token', data.data.token);
    }
}
```

### Using Protected Routes
```javascript
const response = await fetch('/api/protected-route', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
    }
});
```

### Middleware Usage in Routes
```php
// Require specific permission
Route::middleware(['auth:sanctum', 'tenant', 'permission:users.view'])
    ->get('/users', [UserController::class, 'index']);

// Require specific role
Route::middleware(['auth:sanctum', 'tenant', 'role:admin,manager'])
    ->get('/admin-panel', [AdminController::class, 'index']);
```

### Policy Usage
```php
// In controller
public function update(User $user)
{
    $this->authorize('update', $user);
    // Update user logic
}

// In Blade template
@can('update', $user)
    <button>Edit User</button>
@endcan
```

## Permissions

The system includes the following permission groups:
- **users**: User management permissions
- **roles**: Role management permissions
- **dashboard**: Dashboard access permissions
- **customers**: Customer management permissions
- **invoices**: Invoice management permissions
- **inventory**: Inventory management permissions
- **accounting**: Accounting system permissions
- **settings**: System settings permissions
- **reports**: Reporting permissions

## Default Roles

- **super_admin**: Full system access
- **tenant_admin**: Full tenant workspace access
- **manager**: Management level access
- **accountant**: Accounting and financial access
- **cashier**: Sales and customer service access
- **inventory_manager**: Inventory management access
- **employee**: Basic employee access

## Security Features

- Password hashing using Laravel's default hasher
- API token expiration (30 days default)
- Session device tracking
- IP address logging
- Two-factor authentication support
- Recovery codes for 2FA
- Comprehensive audit logging
- Permission-based access control
- Role-based access control

## Database Schema

The system creates the following tables:
- `users` - User accounts
- `roles` - User roles
- `permissions` - System permissions
- `role_permissions` - Role-permission relationships
- `user_sessions` - User login sessions
- `personal_access_tokens` - Sanctum API tokens

## Testing

Unit tests are provided for:
- Authentication service functionality
- Model method availability
- Permission checking logic
- Role management features

Run tests with:
```bash
php artisan test tests/Unit/AuthServiceTest.php
```

**Note:** You may see deprecation warnings during testing from third-party packages:
- Laravel Sanctum v3.3.3 has deprecated implicit nullable parameters
- jenssegers/agent v2.6.4 has deprecated implicit nullable parameters

These are known issues in the dependencies and do not affect the functionality of our authentication system.

## Configuration

The system uses standard Laravel configuration:
- Database connections in `config/database.php`
- Authentication guards in `config/auth.php`
- Sanctum configuration in `config/sanctum.php`

## Migration and Seeding

Run migrations and seed default data:
```bash
php artisan migrate
php artisan db:seed
```

This will create all necessary tables and populate them with default roles and permissions.