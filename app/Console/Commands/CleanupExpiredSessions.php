<?php

namespace App\Console\Commands;

use App\Services\SessionDeviceService;
use Illuminate\Console\Command;

class CleanupExpiredSessions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sessions:cleanup {--timeout=120 : Session timeout in minutes}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up expired user sessions';

    protected SessionDeviceService $sessionDeviceService;

    public function __construct(SessionDeviceService $sessionDeviceService)
    {
        parent::__construct();
        $this->sessionDeviceService = $sessionDeviceService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeoutMinutes = (int) $this->option('timeout');
        
        $this->info("Cleaning up sessions idle for more than {$timeoutMinutes} minutes...");
        
        $cleanedCount = $this->sessionDeviceService->cleanupExpiredSessions($timeoutMinutes);
        
        $this->info("Cleaned up {$cleanedCount} expired sessions.");
        
        return Command::SUCCESS;
    }
}