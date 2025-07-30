<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subdomain',
        'database_name',
        'status',
        'subscription_plan',
        'settings',
        'last_accessed_at'
    ];

    protected $casts = [
        'settings' => 'array',
        'last_accessed_at' => 'datetime',
    ];

    /**
     * Get tenant by subdomain
     */
    public static function findBySubdomain(string $subdomain): ?self
    {
        return static::where('subdomain', $subdomain)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Configure database connection for this tenant
     */
    public function configureDatabaseConnection(): void
    {
        $connectionName = 'tenant_' . $this->id;
        
        Config::set("database.connections.{$connectionName}", [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $this->database_name,
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]);

        // Set as default connection
        Config::set('database.default', $connectionName);
        DB::purge($connectionName);
        DB::reconnect($connectionName);
    }

    /**
     * Create tenant database
     */
    public function createDatabase(): bool
    {
        try {
            // Use the main connection to create the database
            $mainConnection = DB::connection('mysql');
            $mainConnection->statement("CREATE DATABASE IF NOT EXISTS `{$this->database_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to create database for tenant {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Drop tenant database
     */
    public function dropDatabase(): bool
    {
        try {
            $mainConnection = DB::connection('mysql');
            $mainConnection->statement("DROP DATABASE IF EXISTS `{$this->database_name}`");
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to drop database for tenant {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Run migrations for tenant database
     */
    public function runMigrations(): bool
    {
        try {
            $this->configureDatabaseConnection();
            
            // Run tenant-specific migrations
            \Artisan::call('migrate', [
                '--database' => 'tenant_' . $this->id,
                '--path' => 'database/migrations/tenant',
                '--force' => true
            ]);
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to run migrations for tenant {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update last accessed timestamp
     */
    public function updateLastAccessed(): void
    {
        $this->update(['last_accessed_at' => now()]);
    }
}
