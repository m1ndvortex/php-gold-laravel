<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerNotification>
 */
class CustomerNotificationFactory extends Factory
{
    protected $model = CustomerNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['birthday', 'occasion', 'overdue_payment', 'credit_limit_exceeded'];
        $type = $this->faker->randomElement($types);
        
        return [
            'customer_id' => Customer::factory(),
            'type' => $type,
            'title' => $this->getTitleForType($type),
            'title_en' => $this->getTitleForType($type, 'en'),
            'message' => $this->getMessageForType($type),
            'message_en' => $this->getMessageForType($type, 'en'),
            'scheduled_at' => $this->faker->dateTimeBetween('-1 week', '+1 week'),
            'sent_at' => $this->faker->optional(0.6)->dateTimeBetween('-1 week', 'now'),
            'channels' => $this->faker->randomElements(['email', 'sms', 'whatsapp', 'system'], $this->faker->numberBetween(1, 3)),
            'metadata' => $this->getMetadataForType($type),
            'status' => $this->faker->randomElement(['pending', 'sent', 'failed', 'cancelled']),
            'error_message' => $this->faker->optional(0.1)->sentence(),
        ];
    }

    private function getTitleForType(string $type, string $lang = 'fa'): string
    {
        $titles = [
            'birthday' => [
                'fa' => 'تولد مشتری',
                'en' => 'Customer Birthday'
            ],
            'occasion' => [
                'fa' => 'مناسبت ویژه',
                'en' => 'Special Occasion'
            ],
            'overdue_payment' => [
                'fa' => 'پرداخت معوقه',
                'en' => 'Overdue Payment'
            ],
            'credit_limit_exceeded' => [
                'fa' => 'تجاوز از حد اعتبار',
                'en' => 'Credit Limit Exceeded'
            ],
        ];

        return $titles[$type][$lang] ?? $titles[$type]['fa'];
    }

    private function getMessageForType(string $type, string $lang = 'fa'): string
    {
        $messages = [
            'birthday' => [
                'fa' => 'امروز تولد مشتری است',
                'en' => 'Today is customer\'s birthday'
            ],
            'occasion' => [
                'fa' => 'مناسبت ویژه مشتری فرا رسیده است',
                'en' => 'Customer\'s special occasion has arrived'
            ],
            'overdue_payment' => [
                'fa' => 'فاکتور مشتری معوق شده است',
                'en' => 'Customer invoice is overdue'
            ],
            'credit_limit_exceeded' => [
                'fa' => 'مشتری از حد اعتبار تجاوز کرده است',
                'en' => 'Customer has exceeded credit limit'
            ],
        ];

        return $messages[$type][$lang] ?? $messages[$type]['fa'];
    }

    private function getMetadataForType(string $type): array
    {
        switch ($type) {
            case 'birthday':
                return [
                    'customer_age' => $this->faker->numberBetween(18, 80),
                    'birthday_date' => $this->faker->date(),
                ];
            case 'overdue_payment':
                return [
                    'invoice_id' => $this->faker->numberBetween(1, 1000),
                    'invoice_number' => 'INV-' . $this->faker->numberBetween(1000, 9999),
                    'amount' => $this->faker->randomFloat(2, 100, 10000),
                    'due_date' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
                ];
            case 'credit_limit_exceeded':
                return [
                    'credit_limit' => $this->faker->randomFloat(2, 1000, 50000),
                    'current_balance' => $this->faker->randomFloat(2, 1100, 60000),
                    'exceeded_amount' => $this->faker->randomFloat(2, 100, 10000),
                ];
            default:
                return [];
        }
    }

    public function birthday(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'birthday',
            'title' => 'تولد مشتری',
            'title_en' => 'Customer Birthday',
            'message' => 'امروز تولد مشتری است',
            'message_en' => 'Today is customer\'s birthday',
            'metadata' => [
                'customer_age' => $this->faker->numberBetween(18, 80),
                'birthday_date' => $this->faker->date(),
            ],
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'overdue_payment',
            'title' => 'پرداخت معوقه',
            'title_en' => 'Overdue Payment',
            'message' => 'فاکتور مشتری معوق شده است',
            'message_en' => 'Customer invoice is overdue',
            'metadata' => [
                'invoice_id' => $this->faker->numberBetween(1, 1000),
                'invoice_number' => 'INV-' . $this->faker->numberBetween(1000, 9999),
                'amount' => $this->faker->randomFloat(2, 100, 10000),
                'due_date' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
            ],
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'sent_at' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }
}
