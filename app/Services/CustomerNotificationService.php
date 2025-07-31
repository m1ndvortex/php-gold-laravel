<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CustomerNotificationService
{
    /**
     * Get all pending notifications for customers
     */
    public function getPendingNotifications(): Collection
    {
        return CustomerNotification::with('customer')
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Create birthday notifications for upcoming birthdays
     */
    public function createBirthdayNotifications(int $daysAhead = 7): int
    {
        $customers = Customer::active()
            ->whereNotNull('birth_date')
            ->get();

        $notificationsCreated = 0;

        foreach ($customers as $customer) {
            if ($customer->isBirthdayWithinDays($daysAhead)) {
                $birthday = $customer->birth_date->setYear(now()->year);
                
                // If birthday has passed this year, set for next year
                if ($birthday->lt(now())) {
                    $birthday->addYear();
                }

                // Check if notification already exists for this birthday
                $existingNotification = CustomerNotification::where('customer_id', $customer->id)
                    ->where('type', 'birthday')
                    ->where('scheduled_at', $birthday->startOfDay())
                    ->where('status', '!=', 'cancelled')
                    ->first();

                if (!$existingNotification) {
                    $this->createNotification($customer, [
                        'type' => 'birthday',
                        'title' => 'تولد مشتری',
                        'title_en' => 'Customer Birthday',
                        'message' => "امروز تولد {$customer->name} است",
                        'message_en' => "Today is {$customer->name}'s birthday",
                        'scheduled_at' => $birthday->startOfDay(),
                        'channels' => $this->getCustomerNotificationChannels($customer),
                        'metadata' => [
                            'customer_age' => $birthday->diffInYears($customer->birth_date),
                            'birthday_date' => $birthday->format('Y-m-d'),
                        ],
                    ]);

                    $notificationsCreated++;
                }
            }
        }

        return $notificationsCreated;
    }

    /**
     * Create custom occasion notifications
     */
    public function createOccasionNotification(Customer $customer, array $data): CustomerNotification
    {
        return $this->createNotification($customer, [
            'type' => 'occasion',
            'title' => $data['title'],
            'title_en' => $data['title_en'] ?? $data['title'],
            'message' => $data['message'],
            'message_en' => $data['message_en'] ?? $data['message'],
            'scheduled_at' => $data['scheduled_at'],
            'channels' => $data['channels'] ?? $this->getCustomerNotificationChannels($customer),
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Create overdue payment notifications
     */
    public function createOverduePaymentNotifications(): int
    {
        $overdueInvoices = \App\Models\Invoice::with('customer')
            ->where('status', 'overdue')
            ->whereHas('customer', function ($query) {
                $query->active();
            })
            ->get();

        $notificationsCreated = 0;

        foreach ($overdueInvoices as $invoice) {
            // Check if notification already sent for this invoice
            $existingNotification = CustomerNotification::where('customer_id', $invoice->customer_id)
                ->where('type', 'overdue_payment')
                ->where('metadata->invoice_id', $invoice->id)
                ->where('status', '!=', 'cancelled')
                ->first();

            if (!$existingNotification) {
                $this->createNotification($invoice->customer, [
                    'type' => 'overdue_payment',
                    'title' => 'پرداخت معوقه',
                    'title_en' => 'Overdue Payment',
                    'message' => "فاکتور شماره {$invoice->invoice_number} معوق شده است",
                    'message_en' => "Invoice #{$invoice->invoice_number} is overdue",
                    'scheduled_at' => now(),
                    'channels' => $this->getCustomerNotificationChannels($invoice->customer),
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => $invoice->total_amount,
                        'due_date' => $invoice->due_date,
                    ],
                ]);

                $notificationsCreated++;
            }
        }

        return $notificationsCreated;
    }

    /**
     * Create credit limit exceeded notifications
     */
    public function createCreditLimitNotifications(): int
    {
        $customers = Customer::active()
            ->where('credit_limit', '>', 0)
            ->whereRaw('current_balance > credit_limit')
            ->get();

        $notificationsCreated = 0;

        foreach ($customers as $customer) {
            // Check if notification already sent recently (within 7 days)
            $recentNotification = CustomerNotification::where('customer_id', $customer->id)
                ->where('type', 'credit_limit_exceeded')
                ->where('created_at', '>=', now()->subDays(7))
                ->where('status', '!=', 'cancelled')
                ->first();

            if (!$recentNotification) {
                $this->createNotification($customer, [
                    'type' => 'credit_limit_exceeded',
                    'title' => 'تجاوز از حد اعتبار',
                    'title_en' => 'Credit Limit Exceeded',
                    'message' => "مشتری {$customer->name} از حد اعتبار تجاوز کرده است",
                    'message_en' => "Customer {$customer->name} has exceeded credit limit",
                    'scheduled_at' => now(),
                    'channels' => ['system'], // Internal notification only
                    'metadata' => [
                        'credit_limit' => $customer->credit_limit,
                        'current_balance' => $customer->current_balance,
                        'exceeded_amount' => $customer->current_balance - $customer->credit_limit,
                    ],
                ]);

                $notificationsCreated++;
            }
        }

        return $notificationsCreated;
    }

    /**
     * Process and send pending notifications
     */
    public function processPendingNotifications(): int
    {
        $notifications = $this->getPendingNotifications();
        $processed = 0;

        foreach ($notifications as $notification) {
            try {
                $this->sendNotification($notification);
                $notification->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                $processed++;

            } catch (\Exception $e) {
                Log::error('Failed to send customer notification', [
                    'notification_id' => $notification->id,
                    'customer_id' => $notification->customer_id,
                    'error' => $e->getMessage(),
                ]);

                $notification->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Send notification through specified channels
     */
    private function sendNotification(CustomerNotification $notification): void
    {
        $channels = $notification->channels ?? ['system'];

        foreach ($channels as $channel) {
            switch ($channel) {
                case 'email':
                    $this->sendEmailNotification($notification);
                    break;
                case 'sms':
                    $this->sendSmsNotification($notification);
                    break;
                case 'whatsapp':
                    $this->sendWhatsAppNotification($notification);
                    break;
                case 'system':
                default:
                    $this->sendSystemNotification($notification);
                    break;
            }
        }
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(CustomerNotification $notification): void
    {
        if (!$notification->customer->email) {
            return;
        }

        // TODO: Implement email sending logic
        // This would integrate with your email service (e.g., Laravel Mail)
        Log::info('Email notification sent', [
            'customer_id' => $notification->customer_id,
            'email' => $notification->customer->email,
            'type' => $notification->type,
        ]);
    }

    /**
     * Send SMS notification
     */
    private function sendSmsNotification(CustomerNotification $notification): void
    {
        if (!$notification->customer->phone) {
            return;
        }

        // TODO: Implement SMS sending logic
        // This would integrate with your SMS service
        Log::info('SMS notification sent', [
            'customer_id' => $notification->customer_id,
            'phone' => $notification->customer->phone,
            'type' => $notification->type,
        ]);
    }

    /**
     * Send WhatsApp notification
     */
    private function sendWhatsAppNotification(CustomerNotification $notification): void
    {
        if (!$notification->customer->phone) {
            return;
        }

        // TODO: Implement WhatsApp sending logic
        // This would integrate with WhatsApp Business API
        Log::info('WhatsApp notification sent', [
            'customer_id' => $notification->customer_id,
            'phone' => $notification->customer->phone,
            'type' => $notification->type,
        ]);
    }

    /**
     * Send system notification (internal dashboard notification)
     */
    private function sendSystemNotification(CustomerNotification $notification): void
    {
        // TODO: Implement system notification logic
        // This could broadcast to dashboard via WebSockets
        Log::info('System notification sent', [
            'customer_id' => $notification->customer_id,
            'type' => $notification->type,
        ]);
    }

    /**
     * Create a new notification record
     */
    private function createNotification(Customer $customer, array $data): CustomerNotification
    {
        return CustomerNotification::create([
            'customer_id' => $customer->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'title_en' => $data['title_en'],
            'message' => $data['message'],
            'message_en' => $data['message_en'],
            'scheduled_at' => $data['scheduled_at'],
            'channels' => $data['channels'],
            'metadata' => $data['metadata'],
            'status' => 'pending',
        ]);
    }

    /**
     * Get notification channels for customer based on their preferences
     */
    private function getCustomerNotificationChannels(Customer $customer): array
    {
        $preferences = $customer->contact_preferences ?? [];
        $channels = ['system']; // Always include system notifications

        if (in_array('email', $preferences) && $customer->email) {
            $channels[] = 'email';
        }

        if (in_array('sms', $preferences) && $customer->phone) {
            $channels[] = 'sms';
        }

        if (in_array('whatsapp', $preferences) && $customer->phone) {
            $channels[] = 'whatsapp';
        }

        return $channels;
    }

    /**
     * Cancel notification
     */
    public function cancelNotification(int $notificationId): bool
    {
        $notification = CustomerNotification::find($notificationId);
        
        if ($notification && $notification->status === 'pending') {
            $notification->update(['status' => 'cancelled']);
            return true;
        }

        return false;
    }

    /**
     * Get notification history for customer
     */
    public function getCustomerNotificationHistory(int $customerId, int $limit = 50): Collection
    {
        return CustomerNotification::where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}