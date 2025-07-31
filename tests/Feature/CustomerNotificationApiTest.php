<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TenantTestCase;

class CustomerNotificationApiTest extends TenantTestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_list_customer_notifications(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        
        CustomerNotification::factory(5)->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/customer-notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'customer_id',
                            'type',
                            'title',
                            'message',
                            'status',
                            'scheduled_at',
                            'customer',
                        ]
                    ]
                ]
            ]);
    }

    public function test_can_create_custom_occasion_notification(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);

        $notificationData = [
            'customer_id' => $customer->id,
            'title' => 'سالگرد ازدواج',
            'title_en' => 'Wedding Anniversary',
            'message' => 'سالگرد ازدواج مشتری فرا رسیده است',
            'message_en' => 'Customer wedding anniversary is approaching',
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'channels' => ['email', 'sms'],
            'metadata' => [
                'occasion_type' => 'wedding_anniversary',
                'years' => 5,
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer-notifications', $notificationData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'customer_id',
                    'type',
                    'title',
                    'message',
                    'scheduled_at',
                ]
            ]);

        $this->assertDatabaseHas('customer_notifications', [
            'customer_id' => $customer->id,
            'type' => 'occasion',
            'title' => 'سالگرد ازدواج',
            'status' => 'pending',
        ]);
    }

    public function test_can_show_notification_details(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        $notification = CustomerNotification::factory()->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/customer-notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'customer_id',
                    'type',
                    'title',
                    'message',
                    'customer',
                ]
            ]);
    }

    public function test_can_update_pending_notification(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        $notification = CustomerNotification::factory()->pending()->create(['customer_id' => $customer->id]);

        $updateData = [
            'title' => 'Updated Title',
            'title_en' => 'Updated Title EN',
            'message' => 'Updated message',
            'message_en' => 'Updated message EN',
            'scheduled_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'channels' => ['email'],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/customer-notifications/{$notification->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notification updated successfully',
            ]);

        $this->assertDatabaseHas('customer_notifications', [
            'id' => $notification->id,
            'title' => 'Updated Title',
            'message' => 'Updated message',
        ]);
    }

    public function test_cannot_update_sent_notification(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        $notification = CustomerNotification::factory()->sent()->create(['customer_id' => $customer->id]);

        $updateData = [
            'title' => 'Updated Title',
            'message' => 'Updated message',
            'scheduled_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/customer-notifications/{$notification->id}", $updateData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Only pending notifications can be updated',
            ]);
    }

    public function test_can_cancel_pending_notification(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        $notification = CustomerNotification::factory()->pending()->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/customer-notifications/{$notification->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notification cancelled successfully',
            ]);

        $this->assertDatabaseHas('customer_notifications', [
            'id' => $notification->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_can_get_pending_notifications(): void
    {
        $user = User::factory()->create();
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

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/customer-notifications/pending');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'customer_id',
                            'type',
                            'status',
                            'scheduled_at',
                        ]
                    ]
                ]
            ]);

        // Should only return notifications that are due (3 notifications)
        $notifications = $response->json('data.data');
        $this->assertCount(3, $notifications);
    }

    public function test_can_create_birthday_notifications(): void
    {
        $user = User::factory()->create();
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

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer-notifications/create-birthday', [
                'days_ahead' => 7,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'created'
                ]
            ]);

        $this->assertDatabaseHas('customer_notifications', [
            'type' => 'birthday',
            'status' => 'pending',
        ]);
    }

    public function test_can_get_customer_notification_history(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        
        CustomerNotification::factory(10)->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/customer-notifications/customer/{$customer->id}/history?limit=5");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'customer_id',
                        'type',
                        'title',
                        'status',
                    ]
                ]
            ]);

        $notifications = $response->json('data');
        $this->assertCount(5, $notifications);
    }

    public function test_can_get_notification_statistics(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        
        CustomerNotification::factory(5)->pending()->create(['customer_id' => $customer->id]);
        CustomerNotification::factory(3)->sent()->create(['customer_id' => $customer->id]);
        CustomerNotification::factory(2)->birthday()->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/customer-notifications/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_notifications',
                    'pending_notifications',
                    'sent_notifications',
                    'failed_notifications',
                    'due_notifications',
                    'notifications_by_type',
                    'recent_notifications',
                ]
            ]);

        $stats = $response->json('data');
        $this->assertEquals(10, $stats['total_notifications']);
        $this->assertEquals(5, $stats['pending_notifications']);
        $this->assertEquals(3, $stats['sent_notifications']);
    }

    public function test_can_filter_notifications_by_customer(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer1 = Customer::factory()->create(['customer_group_id' => $group->id]);
        $customer2 = Customer::factory()->create(['customer_group_id' => $group->id]);
        
        CustomerNotification::factory(3)->create(['customer_id' => $customer1->id]);
        CustomerNotification::factory(2)->create(['customer_id' => $customer2->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/customer-notifications?customer_id={$customer1->id}");

        $response->assertStatus(200);
        
        $notifications = $response->json('data.data');
        $this->assertCount(3, $notifications);
        
        foreach ($notifications as $notification) {
            $this->assertEquals($customer1->id, $notification['customer_id']);
        }
    }

    public function test_can_filter_notifications_by_type(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        
        CustomerNotification::factory(3)->birthday()->create(['customer_id' => $customer->id]);
        CustomerNotification::factory(2)->overdue()->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/customer-notifications?type=birthday');

        $response->assertStatus(200);
        
        $notifications = $response->json('data.data');
        $this->assertCount(3, $notifications);
        
        foreach ($notifications as $notification) {
            $this->assertEquals('birthday', $notification['type']);
        }
    }

    public function test_can_filter_notifications_by_status(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);
        
        CustomerNotification::factory(3)->pending()->create(['customer_id' => $customer->id]);
        CustomerNotification::factory(2)->sent()->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/customer-notifications?status=pending');

        $response->assertStatus(200);
        
        $notifications = $response->json('data.data');
        $this->assertCount(3, $notifications);
        
        foreach ($notifications as $notification) {
            $this->assertEquals('pending', $notification['status']);
        }
    }
}