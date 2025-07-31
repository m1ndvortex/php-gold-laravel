<?php

namespace App\Console\Commands;

use App\Services\CustomerNotificationService;
use Illuminate\Console\Command;

class ProcessCustomerNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:process-notifications 
                            {--create-birthdays : Create birthday notifications}
                            {--create-overdue : Create overdue payment notifications}
                            {--create-credit-limit : Create credit limit exceeded notifications}
                            {--send-pending : Send pending notifications}
                            {--days-ahead=7 : Days ahead for birthday notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process customer notifications (birthdays, overdue payments, etc.)';

    public function __construct(
        private CustomerNotificationService $notificationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing customer notifications...');

        $totalCreated = 0;
        $totalSent = 0;

        // Create birthday notifications
        if ($this->option('create-birthdays')) {
            $daysAhead = (int) $this->option('days-ahead');
            $created = $this->notificationService->createBirthdayNotifications($daysAhead);
            $totalCreated += $created;
            $this->info("Created {$created} birthday notifications");
        }

        // Create overdue payment notifications
        if ($this->option('create-overdue')) {
            $created = $this->notificationService->createOverduePaymentNotifications();
            $totalCreated += $created;
            $this->info("Created {$created} overdue payment notifications");
        }

        // Create credit limit exceeded notifications
        if ($this->option('create-credit-limit')) {
            $created = $this->notificationService->createCreditLimitNotifications();
            $totalCreated += $created;
            $this->info("Created {$created} credit limit exceeded notifications");
        }

        // Send pending notifications
        if ($this->option('send-pending')) {
            $sent = $this->notificationService->processPendingNotifications();
            $totalSent += $sent;
            $this->info("Sent {$sent} pending notifications");
        }

        // If no specific options provided, do everything
        if (!$this->option('create-birthdays') && 
            !$this->option('create-overdue') && 
            !$this->option('create-credit-limit') && 
            !$this->option('send-pending')) {
            
            $daysAhead = (int) $this->option('days-ahead');
            
            // Create all types of notifications
            $birthdayCreated = $this->notificationService->createBirthdayNotifications($daysAhead);
            $overdueCreated = $this->notificationService->createOverduePaymentNotifications();
            $creditLimitCreated = $this->notificationService->createCreditLimitNotifications();
            
            $totalCreated = $birthdayCreated + $overdueCreated + $creditLimitCreated;
            
            $this->info("Created {$birthdayCreated} birthday notifications");
            $this->info("Created {$overdueCreated} overdue payment notifications");
            $this->info("Created {$creditLimitCreated} credit limit exceeded notifications");
            
            // Send all pending notifications
            $sent = $this->notificationService->processPendingNotifications();
            $totalSent = $sent;
            $this->info("Sent {$sent} pending notifications");
        }

        $this->info("Total notifications created: {$totalCreated}");
        $this->info("Total notifications sent: {$totalSent}");
        $this->info('Customer notification processing completed!');

        return Command::SUCCESS;
    }
}
