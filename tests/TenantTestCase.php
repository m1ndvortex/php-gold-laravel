<?php

namespace Tests;

use App\Tenant;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $tenantService;
    
    // Shared resources per test process
    protected static $processDatabase;
    protected static $processConnectionName;
    protected static $processInitialized = false;

    /**
     * Set up before the first test in the class
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize process-specific database (once per test process)
        if (!static::$processInitialized) {
            $this->initializeProcessDatabase();
            static::$processInitialized = true;
        }
        
        // Create tenant service
        $this->tenantService = app(TenantService::class);
        
        // Create tenant record for this test
        $this->createTenantRecord();
        
        // Set tenant context
        $this->tenantService->setCurrentTenant($this->tenant);
        
        // Switch to tenant database
        $this->switchToTenantDatabase();
        
        // Set headers for API requests
        $this->withHeaders([
            'X-Tenant-Subdomain' => $this->tenant->subdomain,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up tenant record from main database
        $this->cleanupTenantRecord();
        
        // Reset tenant context
        if ($this->tenantService) {
            $this->tenantService->setCurrentTenant(null);
        }
        
        parent::tearDown();
    }

    /**
     * Clean up after all tests in the class
     */
    public static function tearDownAfterClass(): void
    {
        // Drop the process database
        if (static::$processDatabase) {
            try {
                Config::set('database.default', 'mysql');
                DB::reconnect('mysql');
                
                $mainConnection = DB::connection('mysql');
                $mainConnection->statement("DROP DATABASE IF EXISTS `" . static::$processDatabase . "`");
                
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
            
            static::$processDatabase = null;
            static::$processConnectionName = null;
            static::$processInitialized = false;
        }
        
        parent::tearDownAfterClass();
    }

    /**
     * Initialize process-specific database (once per test process)
     */
    protected function initializeProcessDatabase(): void
    {
        // Generate unique database name for this test process
        $testToken = env('TEST_TOKEN', 'default');
        $processId = getmypid(); // Get process ID for uniqueness
        static::$processDatabase = 'test_tenant_' . $testToken . '_' . $processId;
        static::$processConnectionName = 'tenant_' . $testToken . '_' . $processId;
        
        // Create the database
        $this->createProcessDatabase();
        $this->configureProcessConnection();
        $this->runProcessMigrations();
    }
    
    /**
     * Create process database
     */
    protected function createProcessDatabase(): void
    {
        // Ensure we're on main connection
        Config::set('database.default', 'mysql');
        DB::reconnect('mysql');
        
        // Create the tenant database
        $mainConnection = DB::connection('mysql');
        $mainConnection->statement("CREATE DATABASE IF NOT EXISTS `" . static::$processDatabase . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    /**
     * Configure process database connection
     */
    protected function configureProcessConnection(): void
    {
        $config = [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => static::$processDatabase,
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ];
        
        Config::set("database.connections." . static::$processConnectionName, $config);
        
        // Force Laravel to recognize the new connection
        DB::purge(static::$processConnectionName);
        app('db')->extend(static::$processConnectionName, function () use ($config) {
            return app('db.factory')->make($config, static::$processConnectionName);
        });
    }

    /**
     * Run migrations on process database
     */
    protected function runProcessMigrations(): void
    {
        // Run migrations on the process database
        Artisan::call('migrate', [
            '--database' => static::$processConnectionName,
            '--force' => true,
        ]);
    }

    /**
     * Create tenant record for this specific test
     */
    protected function createTenantRecord(): void
    {
        // Ensure we're on main connection for tenant creation
        Config::set('database.default', 'mysql');
        DB::reconnect('mysql');
        
        // Generate unique subdomain for this test
        $uniqueSubdomain = 'test-' . uniqid();
        
        $this->tenant = Tenant::create([
            'name' => 'Test Jewelry Store',
            'subdomain' => $uniqueSubdomain,
            'database_name' => static::$processDatabase,
            'status' => 'active',
            'settings' => [],
        ]);
    }

    /**
     * Switch to tenant database for this test
     */
    protected function switchToTenantDatabase(): void
    {
        // Set tenant connection as default
        Config::set('database.default', static::$processConnectionName);
        
        // Purge and reconnect to ensure fresh connection
        DB::purge(static::$processConnectionName);
        DB::reconnect(static::$processConnectionName);
        
        // Test the connection
        try {
            DB::connection(static::$processConnectionName)->getPdo();
        } catch (\Exception $e) {
            throw new \Exception("Failed to connect to tenant database: " . $e->getMessage());
        }
    }

    /**
     * Clean up tenant record (per test)
     */
    protected function cleanupTenantRecord(): void
    {
        if ($this->tenant) {
            try {
                // Switch to main database
                Config::set('database.default', 'mysql');
                DB::reconnect('mysql');
                
                // Delete the tenant record
                $this->tenant->delete();
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
    }

    /**
     * Get the tenant instance
     */
    protected function getTenant(): Tenant
    {
        return $this->tenant;
    }

    /**
     * Get the tenant service
     */
    protected function getTenantService(): TenantService
    {
        return $this->tenantService;
    }

    /**
     * Override the RefreshDatabase to use tenant database
     */
    protected function refreshTestDatabase()
    {
        // Use transactions for fast cleanup between tests
        DB::connection(static::$processConnectionName)->beginTransaction();
        
        $this->beforeApplicationDestroyed(function () {
            try {
                if (DB::connection(static::$processConnectionName)->transactionLevel() > 0) {
                    DB::connection(static::$processConnectionName)->rollBack();
                }
            } catch (\Exception $e) {
                // Ignore rollback errors during cleanup
            }
        });
    }
}