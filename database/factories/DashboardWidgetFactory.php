<?php

namespace Database\Factories;

use App\Models\DashboardWidget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DashboardWidgetFactory extends Factory
{
    protected $model = DashboardWidget::class;

    public function definition(): array
    {
        $widgetTypes = ['kpi', 'chart', 'alert', 'list', 'metric'];
        $widgetType = $this->faker->randomElement($widgetTypes);
        
        return [
            'user_id' => User::factory(),
            'widget_type' => $widgetType,
            'title' => $this->getWidgetTitle($widgetType),
            'position_x' => $this->faker->numberBetween(0, 11),
            'position_y' => $this->faker->numberBetween(0, 10),
            'width' => $this->faker->randomElement([1, 2, 3, 4]),
            'height' => $this->faker->randomElement([1, 2, 3]),
            'settings' => $this->getWidgetSettings($widgetType),
            'is_active' => $this->faker->boolean(90),
        ];
    }

    private function getWidgetTitle(string $type): string
    {
        return match ($type) {
            'kpi' => $this->faker->randomElement(['Sales', 'Profit', 'Customers', 'Gold Sold']),
            'chart' => $this->faker->randomElement(['Sales Trend', 'Product Performance', 'Customer Growth']),
            'alert' => 'Alerts & Notifications',
            'list' => $this->faker->randomElement(['Top Products', 'Recent Orders', 'New Customers']),
            'metric' => $this->faker->randomElement(['Inventory Value', 'Pending Payments', 'Monthly Revenue']),
            default => 'Dashboard Widget',
        };
    }

    private function getWidgetSettings(string $type): array
    {
        return match ($type) {
            'kpi' => [
                'metric' => $this->faker->randomElement(['sales', 'profit', 'customers', 'gold_weight']),
                'period' => $this->faker->randomElement(['today', 'week', 'month']),
                'show_trend' => true,
                'color' => $this->faker->randomElement(['blue', 'green', 'purple', 'orange']),
            ],
            'chart' => [
                'chart_type' => $this->faker->randomElement(['line', 'bar', 'area']),
                'period' => $this->faker->randomElement(['7_days', '30_days', '90_days']),
                'data_source' => $this->faker->randomElement(['sales', 'orders', 'customers']),
                'show_legend' => true,
            ],
            'alert' => [
                'alert_types' => $this->faker->randomElements(['overdue', 'low_stock', 'cheques_due'], 2),
                'severity_filter' => $this->faker->randomElement(['all', 'high', 'critical']),
                'max_items' => $this->faker->numberBetween(5, 20),
            ],
            'list' => [
                'list_type' => $this->faker->randomElement(['products', 'customers', 'orders']),
                'sort_by' => $this->faker->randomElement(['revenue', 'quantity', 'date']),
                'limit' => $this->faker->numberBetween(5, 15),
            ],
            'metric' => [
                'metric_type' => $this->faker->randomElement(['inventory_value', 'pending_payments', 'monthly_revenue']),
                'format' => $this->faker->randomElement(['currency', 'number', 'percentage']),
                'show_comparison' => true,
            ],
            default => [],
        };
    }

    public function kpi(): static
    {
        return $this->state(fn (array $attributes) => [
            'widget_type' => 'kpi',
            'title' => $this->faker->randomElement(['Total Sales', 'Profit', 'New Customers', 'Gold Sold']),
            'width' => 1,
            'height' => 1,
            'settings' => [
                'metric' => $this->faker->randomElement(['sales', 'profit', 'customers', 'gold_weight']),
                'period' => 'today',
                'show_trend' => true,
                'color' => $this->faker->randomElement(['blue', 'green', 'purple', 'orange']),
            ],
        ]);
    }

    public function chart(): static
    {
        return $this->state(fn (array $attributes) => [
            'widget_type' => 'chart',
            'title' => 'Sales Trend',
            'width' => 2,
            'height' => 2,
            'settings' => [
                'chart_type' => 'line',
                'period' => '30_days',
                'data_source' => 'sales',
                'show_legend' => true,
            ],
        ]);
    }

    public function alert(): static
    {
        return $this->state(fn (array $attributes) => [
            'widget_type' => 'alert',
            'title' => 'Alerts & Notifications',
            'width' => 1,
            'height' => 2,
            'settings' => [
                'alert_types' => ['overdue', 'low_stock', 'cheques_due'],
                'severity_filter' => 'all',
                'max_items' => 10,
            ],
        ]);
    }
}