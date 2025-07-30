# Multi-Tenant Infrastructure

This document describes the multi-tenant infrastructure implementation for the Jewelry SaaS Platform.

## Overview

The platform uses a **database-per-tenant** approach for maximum data isolation and security. Each tenant gets their own MySQL database while sharing the same application code.

## Components

### 1. Tenant Model (`app/Tenant.php`)

The Tenant model manages tenant information and database operations:

```php
// Create a tenant
$tenant = Tenant::create([
    'name' => 'Jewelry Store Name',
    'subdomain' => 'jewelry-store',
    'database_name' => 'tenant_unique_id',
    'status' => 'active'
]);

// Find tenant by subdomain
$tenant = Tenant::findBySubdomain('jewelry-store');

// Configure database connection
$tenant->configureDatabaseConnection();
```

### 2. TenantResolver Middleware (`app/Http/Middleware/TenantResolver.php`)

Automatically resolves tenant context from:
- Subdomain: `jewelry-store.example.com`
- Localhost: `jewelry-store.localhost` (for development)
- Header: `X-Tenant-Subdomain: jewelry-store` (for API testing)

The middleware:
- Extracts tenant identifier from request
- Loads tenant from database
- Configures database connection
- Sets tenant context in application
- Updates last accessed timestamp

### 3. TenantService (`app/Services/TenantService.php`)

Provides high-level tenant management operations:

```php
$tenantService = app(TenantService::class);

// Create new tenant with database
$tenant = $tenantService->createTenant([
    'name' => 'Store Name',
    'subdomain' => 'store-subdomain'
]);

// Switch to tenant database
$tenantService->switchToTenant($tenant);

// Run migrations for tenant
$tenantService->runTenantMigrations($tenant);

// Delete tenant and database
$tenantService->deleteTenant($tenant);
```

### 4. Console Commands

#### Create Tenant
```bash
php artisan tenant:create "Store Name" store-subdomain --plan=premium
```

#### Run Migrations
```bash
# Migrate all tenants
php artisan tenant:migrate

# Migrate specific tenant
php artisan tenant:migrate --tenant=store-subdomain

# Fresh migration with seeding
php artisan tenant:migrate --fresh --seed
```

## Database Structure

### Main Database
- `tenants` table: Stores tenant information and metadata

### Tenant Databases
- Each tenant gets a separate database: `tenant_{unique_id}`
- Tenant-specific migrations in `database/migrations/tenant/`
- Identical schema across all tenant databases

## Configuration

### Database Configuration (`config/database.php`)
- `mysql`: Main database connection for tenant management
- `tenant_template`: Template for dynamic tenant connections
- Dynamic connections: `tenant_{tenant_id}`

### Middleware Registration (`app/Http/Kernel.php`)
```php
'tenant' => \App\Http\Middleware\TenantResolver::class,
```

### Service Provider (`app/Providers/TenantServiceProvider.php`)
- Registers TenantService as singleton
- Provides `tenant()` helper function

## Usage Examples

### In Controllers
```php
class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        // Get current tenant
        $tenant = $request->attributes->get('tenant');
        // or
        $tenant = tenant();
        
        // Query tenant-specific data
        $invoices = Invoice::all(); // Automatically uses tenant database
    }
}
```

### In Routes
```php
// Apply tenant middleware to route groups
Route::middleware(['tenant'])->group(function () {
    Route::apiResource('invoices', InvoiceController::class);
    Route::apiResource('customers', CustomerController::class);
});
```

### Helper Functions
```php
// Get current tenant anywhere in the application
$currentTenant = tenant();

// Check if we're in tenant context
if (tenant()) {
    // Tenant-specific logic
}
```

## Security Features

### Data Isolation
- Complete database separation between tenants
- No shared tables or data
- Tenant context validation on every request

### Subdomain Validation
- Only lowercase letters, numbers, and hyphens
- 3-63 character length limit
- Reserved subdomain protection (www, api, admin, etc.)
- No leading/trailing hyphens

### Access Control
- Tenant status checking (active/inactive/suspended)
- Route-based tenant resolution skipping
- Automatic tenant context cleanup

## Development Workflow

### 1. Create Tenant
```bash
php artisan tenant:create "Test Store" test-store
```

### 2. Add Tenant-Specific Migration
```bash
# Create migration in tenant directory
touch database/migrations/tenant/2025_07_30_000002_create_products_table.php
```

### 3. Run Tenant Migrations
```bash
php artisan tenant:migrate --tenant=test-store
```

### 4. Test with Subdomain
```bash
# Access via subdomain
curl -H "Host: test-store.localhost" http://localhost/api/products

# Or use header for testing
curl -H "X-Tenant-Subdomain: test-store" http://localhost/api/products
```

## Error Handling

### Tenant Not Found
- Returns 404 JSON response
- Logs tenant resolution failures
- Graceful fallback for invalid subdomains

### Database Connection Failures
- Automatic connection retry
- Fallback to main database for error logging
- Detailed error messages in logs

### Migration Failures
- Rollback database creation on migration failure
- Cleanup tenant record on setup failure
- Detailed error reporting in console commands

## Performance Considerations

### Connection Pooling
- Reuse database connections where possible
- Automatic connection cleanup
- Connection purging for tenant switches

### Caching
- Tenant information caching
- Database connection configuration caching
- Last accessed timestamp batching

### Monitoring
- Track tenant database sizes
- Monitor connection counts
- Log slow tenant operations

## Testing

The multi-tenant infrastructure includes comprehensive tests:

```bash
# Run tenant-specific tests
php artisan test --filter=TenantTest

# Test tenant creation and validation
# Test middleware subdomain extraction
# Test service tenant management
# Test database connection switching
```

## Troubleshooting

### Common Issues

1. **Tenant not found**: Check subdomain format and tenant status
2. **Database connection errors**: Verify MySQL credentials and permissions
3. **Migration failures**: Check tenant database exists and is accessible
4. **Middleware not applied**: Ensure routes use 'tenant' middleware

### Debug Commands

```bash
# List all tenants
php artisan tinker
>>> App\Tenant::all()

# Check tenant database connection
>>> $tenant = App\Tenant::find(1)
>>> $tenant->configureDatabaseConnection()
>>> DB::connection()->getDatabaseName()

# Test subdomain validation
>>> app(App\Services\TenantService::class)->validateSubdomain('test-store')
```