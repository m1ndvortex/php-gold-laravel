<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerNotification;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CustomerNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class CustomerNotificationServiceTest extends TenantTestCase
{
    use RefreshDatabase;

    private CustomerNotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = new CustomerNotificationService();
    }

    public function test_can_create_birthday_notifications(): void
    {
        $group = CustomerGroup::factory()->create();
        
        // Create customers with birthdays in the next 7 days
        Customer::factory()->create([
            'customer_group_id' => $group->id,
            'birth_date' => now()->addDays(3),
            'is_active' => true,
        ]);
        
        Customer::factory()->create([
            'customer_group_id' => $group->id,
            'birth_date' => now()->addDays(5),
            'is_active' => true,
        ]);
        
        // Customer with birthday outside range
        Customer::factory()->create([
            'customer_group_id' => $group->id,
            'birth_date' => now()->addDays(10),
            'is_active' => true,
        ]);
        
        // Inactive customer
        Customer::factory()->create([
            'customer_group_id' => $group->id,
            'birth_date' => now()->addDays(2),
            'is_active' => false,
        ]);

        $created = $this->notificationService->createBirthdayNotifications(7);

        $this->assertEquals(2, $created);
        
        $this->assertDatabaseHas('customer_notifications', [
            'type' => 'birthday',
            'title' => 'تولد مشتری',
            'status' => 'pending',
        ]);
    }

    public function test_does_not_create_duplicate_birthday_notifications(): void
    {
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create([
            'customer_group_id' => $group->id,
            'birth_date' => now()->addDays(3),
            'is_active' => true,
        ]);

        // Create first notification
        $created1 = $this->notificationService->createBirthdayNotifications(7);
        $this->assertEquals(1, $created1);

        // Try to create again - should not create duplicate
        $created2 = $this->notificationService->createBirthdayNotifications(7);
        $this->assertEquals(0, $created2);

        $this->assertEquals(1, CustomerNotification::where('customer_id', $customer->id)
            ->where('type', 'birthday')
            ->count());
    }

    public function test_can_create_custom_occasion_notification(): void
    {
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);

        $occasionData = [
            'title' => 'سالگرد ازدواج',
            'title_en' => 'Wedding Anniversary',
            'message' => 'سالگرد ازدواج مشتری فرا رسیده است',
            'message_en' => 'Customer wedding anniversary is approaching',
            'scheduled_at' => now()->addDays(3),
            'channels' => ['email', 'sms'],
            'metadata' => [
                'occasion_type' => 'wedding_anniversary',
                'years' => 5,
            ],
        ];

        $notification = $this->notificationService->createOccasionNotification($customer, $occasionData);

        $this->assertInstanceOf(CustomerNotification::class, $notification);
        $this->assertEquals($customer->id, $notification->customer_id);
        $this->assertEquals('occasion', $notification->type);
        $this->assertEquals('سالگرد ازدواج', $notification->title);
        $this->assertEquals('pending', $notification->status);
        $this->assertEquals(['email', 'sms'], $notification->channels);
    }

    public function test_can_create_overdue_payment_notifications(): void
    {
        $group = CustomerGroup::factory()->create();
        $customer1 = Customer::factory()->create([
            'customer_group_id' => $group->id,
            'is_active' => true,
        ]);
        $customer2 = Customer::factory()->create([
            'customer_group_id' => $group->id,
            'is_active' => true,
        ]);

        // Create overdue invoices
        Invoice::factory()->create([
            'customer_id' => $customer1->id,
            'status' => 'overdue',
            'invoice_number' => 'INV-001',
        ]);
        
        Invoice::factory()->create([
            'customer_id' => $customer2->id,
            'status' => 'overdue',
            'invoice_number' => 'INV-002',
        ]);
        
        // Create paid invoice (should not trigger notification)
        Invoice::factory()->create([
            'customer_id' => $customer1->id,
            'status' => 'paid',
            'invoice_number' => 'INV-003',
        ]);

        $created = $this->notificationService->createOverduePaymentNotifications();

        $this->assertEquals(2, $created);
        
        $this->assertDatabaseHas('customer_notifications', [
            'customer_id' => $customer1->id,
            'type' => 'overdue_payment',
            'title' => 'پرداخت معوقه',
            'status' => 'pending',
        ]);
        
        $this->assertDatabaseHas('customer_notifications', [
            'customer_id' => $customer2->id,
            'type' => 'overdue_payment',
            'title' => 'پرداخت معوقه',
            'status' => 'pending',
        ]);
    }

    public function test_can_create_credit_limit_exceeded_notifications(): void
    {
        $group = CustomerGroup::factory()->create();
        
        // Customer who exceeded credit limit
        $customer1 = Customer::factory()->create([
            'customer_group_id' => $group->id,
            'credit_limit' => 1000,
            'current_balance' => 1500,
            'is_active' => true,
        ]);
        
        // Customer within credit limit
        $customer2 = Customer::factory()->create([
            'customer_group_id' => $group->id,
            'credit_limit' => 2000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);
        
        // Customer with no credit limit
        $customer3 = Customer::factory()->create([
            'customer_group_id' => $group->id,
            'credit_limit' => 0,
            'current_balance' => 500,
            'is_active' => true,
        ]);

        $created = $this->notificationService->createCreditLimitNotifications();

        $this->assertEquals(1, $created);
        
        $this->assertDatabaseHas('customer_notifications', [
            'customer_id' => $customer1->id,
            'type' => 'credit_limit_exceeded',
            'title' => 'تجاوز از حد اعتبار',
            'status' => 'pending',
        ]);
        
        $this->assertDatabaseMissing('customer_notifications', [
            'customer_id' => $customer2->id,
            'type' => 'credit_limit_exceeded',
        ]);
    }

    public function test_does_not_create_duplicate_credit_limit_notifications(): void
    {
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create([
            'customer_group_id' => $group->id,
            'credit_limit' => 1000,
            'current_balance' => 1500,
            'is_active' => true,
        ]);

        // Create first notification
        $created1 = $this->notificationService->createCreditLimitNotifications();
        $this->assertEquals(1, $created1);

        // Try to create again within 7 days - should not create duplicate
        $created2 = $this->notificationService->createCreditLimitNotifications();
        $this->assertEquals(0, $created2);

        $this->assertEquals(1, CustomerNotification::where('customer_id', $customer->id)
            ->where('type', 'credit_limit_exceeded')
            ->count());
    }

    public function test_can_get_pending_notifications(): void
    {
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        
        // Create pending notifications due now
        CustomerNotification::factory(3)->pending()->create([
            'customer_id' => $customer->id,
            'scheduled_at' => now()->subHour(),
        ]);
        
        // Create future pending notifications
        CustomerNotification::factory(2)->pending()->create([
            'customer_id' => $customer->id,
            'scheduled_at' => now()->addDays(5),
        ]);
        
        // Create sent notifications
        CustomerNotification::factory(2)->sent()->create([
            'customer_id' => $customer->id,
        ]);

        $pendingNotifications = $this->notificationService->getPendingNotifications();

        $this->assertCount(3, $pendingNotifications);
        
        foreach ($pendingNotifications as $notification) {
            $this->assertEquals('pending', $notification->status);
            $this->assertTrue($notification->scheduled_at <= now());
        }
    }

    public function test_can_process_pending_notifications(): void
    {
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        
        // Create pending notifications due now
        CustomerNotification::factory(3)->pending()->create([
            'customer_id' => $customer->id,
            'scheduled_at' => now()->subHour(),
        ]);

        $processed = $this->notificationService->processPendingNotifications();

        $this->assertEquals(3, $processed);
        
        // All notifications should now be marked as sent
        $this->assertEquals(3, CustomerNotification::where('customer_id', $customer->id)
            ->where('status', 'sent')
            ->count());
        
        // All notifications should have sent_at timestamp
        $this->assertEquals(3, CustomerNotification::where('customer_id', $customer->id)
            ->whereNotNull('sent_at')
            ->count());
    }

    public function test_can_cancel_notification(): void
    {
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        $notification = CustomerNotification::factory()->pending()->create(['customer_id' => $customer->id]);

        $cancelled = $this->notificationService->cancelNotification($notification->id);

        $this->assertTrue($cancelled);
        
        $notification->refresh();
        $this->assertEquals('cancelled', $notification->status);
    }

    public function test_cannot_cancel_sent_notification(): void
    {
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        $notification = CustomerNotification::factory()->sent()->create(['customer_id' => $customer->id]);

        $cancelled = $this->notificationService->cancelNotification($notification->id);

        $this->assertFalse($cancelled);
        
        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
    }

    public function test_can_get_customer_notification_history(): void
    {
        $group = CustomerGroup::factory()->create();
        $customer1 = Customer::factory()->create(['customer_group_id' => $group->id]);
        $customer2 = Customer::factory()->create(['customer_group_id' => $group->id]);
        
        // Create notifications for customer1
        CustomerNotification::factory(10)->create(['customer_id' => $customer1->id]);
        
        // Create notifications for customer2
        CustomerNotification::factory(5)->create(['customer_id' => $customer2->id]);

        $history = $this->notificationService->getCustomerNotificationHistory($customer1->id, 5);

        $this->assertCount(5, $history);
        
        foreach ($history as $notification) {
            $this->assertEquals($customer1->id, $notification->customer_id);
        }
    }

    public function test_uses_customer_contact_preferences_for_channels(): void
    {
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create([
            'customer_group_id' => $group->id,
            'email' => 'test@example.com',
            'phone' => '09123456789',
            'contact_preferences' => ['email', 'sms'],
        ]);

        $occasionData = [
            'title' => 'Test Occasion',
            'message' => 'Test message',
            'scheduled_at' => now()->addDays(1),
        ];

        $notification = $this->notificationService->createOccasionNotification($customer, $occasionData);

        // Should include system + customer preferences
        $expectedChannels = ['system', 'email', 'sms'];
        $this->assertEquals($expectedChannels, $notification->channels);
    }

    public function test_only_includes_available_contact_channels(): void
    {
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create([
            'customer_group_id' => $group->id,
            'email' => null, // No email
            'phone' => '09123456789',
            'contact_preferences' => ['email', 'sms'], // Wants email but doesn't have it
        ]);

        $occasionData = [
            'title' => 'Test Occasion',
            'message' => 'Test message',
            'scheduled_at' => now()->addDays(1),
        ];

        $notification = $this->notificationService->createOccasionNotification($customer, $occasionData);

        // Should only include system + sms (email excluded because no email address)
        $expectedChannels = ['system', 'sms'];
        $this->assertEquals($expectedChannels, $notification->channels);
    }
}