# Session and Device Management System

This document describes the session and device management system implemented for the Jewelry SaaS Platform.

## Overview

The session management system provides comprehensive tracking of user sessions across different devices, automatic logout functionality, and login anomaly detection to enhance security.

## Components

### 1. SessionDeviceService

The core service that handles all session-related operations:

- **Session Creation**: Creates new session records with device information
- **Session Tracking**: Tracks active sessions and updates activity timestamps
- **Remote Logout**: Allows users to logout from specific sessions or all sessions
- **Cleanup**: Automatically cleans up expired sessions
- **Anomaly Detection**: Detects suspicious login patterns

#### Key Methods

```php
// Create a new session
createSession(User $user, Request $request, string $sessionId): UserSession

// Update session activity
updateSessionActivity(string $sessionId): void

// Get all active sessions for a user
getUserActiveSessions(User $user): Collection

// Logout specific session
logoutSession(User $user, string $sessionId): bool

// Logout all other sessions except current
logoutOtherSessions(User $user, string $currentSessionId): int

// Logout all sessions
logoutAllSessions(User $user): int

// Clean up expired sessions
cleanupExpiredSessions(int $maxIdleMinutes = 120): int

// Detect login anomalies
detectLoginAnomalies(User $user, Request $request): array
```

### 2. SessionTimeout Middleware

Automatically handles session timeout and idle logout:

- Checks if the current session exists and is valid
- Verifies session hasn't exceeded the idle timeout
- Updates session activity on each request
- Automatically logs out expired sessions
- Provides session timeout information in response headers

**Configuration**: Set `SESSION_TIMEOUT` environment variable (default: 120 minutes)

### 3. LoginAnomalyDetection Middleware

Detects and logs suspicious login patterns:

- **New IP Address**: Login from previously unseen IP
- **New Location**: Login from different country
- **New Device Type**: Login from different device type (mobile/desktop/tablet)
- **Rapid Location Changes**: Quick successive logins from different locations

Anomalies are logged and stored in session for user notification.

### 4. SessionController API

Provides REST API endpoints for session management:

#### Endpoints

```
GET /api/sessions
- Get all active sessions for authenticated user

DELETE /api/sessions/{sessionId}
- Logout specific session

POST /api/sessions/logout-others
- Logout all other sessions except current

POST /api/sessions/logout-all
- Logout all sessions including current

GET /api/sessions/timeout
- Get session timeout information

GET /api/sessions/anomalies
- Get login anomalies (if any)
```

### 5. CleanupExpiredSessions Command

Console command to clean up expired sessions:

```bash
php artisan sessions:cleanup --timeout=120
```

This command should be scheduled to run periodically (e.g., hourly) to clean up expired sessions.

## Database Schema

The `user_sessions` table stores session information:

```sql
CREATE TABLE user_sessions (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(255) NOT NULL,
    user_agent TEXT NOT NULL,
    device_type VARCHAR(255),
    device_name VARCHAR(255),
    browser VARCHAR(255),
    platform VARCHAR(255),
    location JSON,
    is_current BOOLEAN DEFAULT FALSE,
    last_activity TIMESTAMP NOT NULL,
    logged_out_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_user_session (user_id, session_id),
    INDEX idx_user_current (user_id, is_current)
);
```

## Security Features

### 1. Session Isolation
- Each session is tracked independently
- Sessions can be terminated remotely
- Current session is clearly identified

### 2. Device Fingerprinting
- User agent parsing for device information
- IP address tracking
- Geolocation detection (when available)

### 3. Anomaly Detection
- Pattern analysis of login behavior
- Automatic logging of suspicious activities
- User notification of anomalies

### 4. Automatic Cleanup
- Expired sessions are automatically logged out
- Configurable timeout periods
- Scheduled cleanup commands

## Configuration

### Environment Variables

```env
# Session timeout in minutes (default: 120)
SESSION_TIMEOUT=120

# Session lifetime for Laravel sessions (default: 120)
SESSION_LIFETIME=120

# Session driver (recommended: redis)
SESSION_DRIVER=redis
```

### Middleware Registration

The middleware is automatically registered in `app/Http/Kernel.php`:

```php
// API middleware group
'api' => [
    // ... other middleware
    \App\Http\Middleware\SessionTimeout::class,
],

// Route-specific middleware
'session.timeout' => \App\Http\Middleware\SessionTimeout::class,
'login.anomaly' => \App\Http\Middleware\LoginAnomalyDetection::class,
```

## Usage Examples

### Frontend Integration

```javascript
// Get session information
const response = await fetch('/api/sessions/timeout');
const { data } = await response.json();

// Display remaining time
const remainingMinutes = data.remaining_minutes;
console.log(`Session expires in ${remainingMinutes} minutes`);

// Get all active sessions
const sessionsResponse = await fetch('/api/sessions');
const { data: sessionsData } = await sessionsResponse.json();

// Display active sessions
sessionsData.sessions.forEach(session => {
    console.log(`${session.device_info} - ${session.location} - ${session.last_activity_human}`);
});

// Logout specific session
await fetch(`/api/sessions/${sessionId}`, { method: 'DELETE' });

// Logout all other sessions
await fetch('/api/sessions/logout-others', { method: 'POST' });
```

### Scheduled Tasks

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Clean up expired sessions every hour
    $schedule->command('sessions:cleanup')->hourly();
}
```

## Testing

The system includes comprehensive tests:

- **Unit Tests**: `tests/Unit/SessionDeviceServiceTest.php`
- **Feature Tests**: `tests/Feature/SessionManagementTest.php`

Run tests with:

```bash
php artisan test tests/Unit/SessionDeviceServiceTest.php
php artisan test tests/Feature/SessionManagementTest.php
```

## Requirements Fulfilled

This implementation fulfills the following requirements from the specification:

- **9.3**: Session device tracker with remote logout functionality
- **9.4**: Idle timeout and automatic logout
- **9.5**: Login anomaly detection with IP and geo-location tracking

The system provides a comprehensive solution for session management with security features appropriate for a multi-tenant jewelry business platform.