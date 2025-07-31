<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Payment;
use App\Models\DashboardWidget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    protected $webSocketService;

    public function __construct(WebSocketService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    public function getKPIs($period = 'today')
    {
        $cacheKey = "dashboard_kpis_{$period}_" . auth()->id();
        
        return Cache::remember($cacheKey, 300, function () use ($period) {
            $dateRange = $this->getDateRange($period);
            
            return [
                'sales' => $this->getSalesKPI($dateRange),
                'profit' => $this->getProfitKPI($dateRange),
                'customers' => $this->getCustomersKPI($dateRange),
                'gold_metrics' => $this->getGoldMetrics($dateRange),
                'inventory_value' => $this->getInventoryValue(),
                'pending_payments' => $this->getPendingPayments(),
            ];
        });
    }

    /**
     * Get KPIs and broadcast updates to connected clients
     */
    public function getKPIsWithBroadcast($period = 'today')
    {
        $kpis = $this->getKPIs($period);
        
        // Broadcast KPI updates to all connected users in the tenant
        $this->webSocketService->broadcastKpiUpdate($kpis);
        
        return $kpis;
    }

    public function getSalesKPI($dateRange)
    {
        $current = Invoice::whereBetween('created_at', $dateRange['current'])
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        $previous = Invoice::whereBetween('created_at', $dateRange['previous'])
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        $change = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;

        return [
            'value' => $current,
            'previous_value' => $previous,
            'change_percentage' => round($change, 2),
            'trend' => $change >= 0 ? 'up' : 'down',
            'count' => Invoice::whereBetween('created_at', $dateRange['current'])
                ->where('status', '!=', 'cancelled')
                ->count(),
        ];
    }

    public function getProfitKPI($dateRange)
    {
        $invoices = Invoice::with('items.product')
            ->whereBetween('created_at', $dateRange['current'])
            ->where('status', '!=', 'cancelled')
            ->get();

        $totalProfit = 0;
        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                $cost = $item->product->manufacturing_cost ?? 0;
                $profit = ($item->unit_price - $cost) * $item->quantity;
                $totalProfit += $profit;
            }
        }

        $previousInvoices = Invoice::with('items.product')
            ->whereBetween('created_at', $dateRange['previous'])
            ->where('status', '!=', 'cancelled')
            ->get();

        $previousProfit = 0;
        foreach ($previousInvoices as $invoice) {
            foreach ($invoice->items as $item) {
                $cost = $item->product->manufacturing_cost ?? 0;
                $profit = ($item->unit_price - $cost) * $item->quantity;
                $previousProfit += $profit;
            }
        }

        $change = $previousProfit > 0 ? (($totalProfit - $previousProfit) / $previousProfit) * 100 : 0;

        return [
            'value' => $totalProfit,
            'previous_value' => $previousProfit,
            'change_percentage' => round($change, 2),
            'trend' => $change >= 0 ? 'up' : 'down',
            'margin_percentage' => $invoices->sum('total_amount') > 0 
                ? round(($totalProfit / $invoices->sum('total_amount')) * 100, 2) 
                : 0,
        ];
    }

    public function getCustomersKPI($dateRange)
    {
        $newCustomers = Customer::whereBetween('created_at', $dateRange['current'])->count();
        $previousNewCustomers = Customer::whereBetween('created_at', $dateRange['previous'])->count();
        
        $change = $previousNewCustomers > 0 ? (($newCustomers - $previousNewCustomers) / $previousNewCustomers) * 100 : 0;

        return [
            'new_customers' => $newCustomers,
            'total_customers' => Customer::count(),
            'change_percentage' => round($change, 2),
            'trend' => $change >= 0 ? 'up' : 'down',
            'active_customers' => Customer::whereHas('invoices', function ($query) use ($dateRange) {
                $query->whereBetween('created_at', $dateRange['current']);
            })->count(),
        ];
    }

    public function getGoldMetrics($dateRange)
    {
        $goldSold = Invoice::with('items.product')
            ->whereBetween('created_at', $dateRange['current'])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sum(function ($invoice) {
                return $invoice->items->sum(function ($item) {
                    return $item->product->gold_weight * $item->quantity;
                });
            });

        $previousGoldSold = Invoice::with('items.product')
            ->whereBetween('created_at', $dateRange['previous'])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sum(function ($invoice) {
                return $invoice->items->sum(function ($item) {
                    return $item->product->gold_weight * $item->quantity;
                });
            });

        $change = $previousGoldSold > 0 ? (($goldSold - $previousGoldSold) / $previousGoldSold) * 100 : 0;

        return [
            'gold_sold_grams' => round($goldSold, 2),
            'previous_gold_sold' => round($previousGoldSold, 2),
            'change_percentage' => round($change, 2),
            'trend' => $change >= 0 ? 'up' : 'down',
            'current_gold_price' => app(GoldPriceService::class)->getCurrentGoldPrice(),
            'gold_inventory_grams' => Product::sum('gold_weight'),
        ];
    }

    public function getInventoryValue()
    {
        return Product::selectRaw('SUM(current_stock * unit_price) as total_value')
            ->value('total_value') ?? 0;
    }

    public function getPendingPayments()
    {
        return Invoice::where('status', 'pending')
            ->sum('total_amount');
    }

    public function getSalesTrend($period = '30_days')
    {
        $days = $period === '30_days' ? 30 : ($period === '7_days' ? 7 : 365);
        $startDate = Carbon::now()->subDays($days);

        return Invoice::selectRaw('DATE(created_at) as date, SUM(total_amount) as total, COUNT(*) as count')
            ->where('created_at', '>=', $startDate)
            ->where('status', '!=', 'cancelled')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'total' => (float) $item->total,
                    'count' => (int) $item->count,
                ];
            });
    }

    /**
     * Get sales trend and broadcast updates to connected clients
     */
    public function getSalesTrendWithBroadcast($period = '30_days')
    {
        $salesData = $this->getSalesTrend($period);
        
        // Broadcast sales trend updates to all connected users in the tenant
        $this->webSocketService->broadcastSalesTrendUpdate($salesData->toArray());
        
        return $salesData;
    }

    public function getTopProducts($limit = 10)
    {
        return DB::table('invoice_items')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->selectRaw('products.name, products.sku, SUM(invoice_items.quantity) as total_sold, SUM(invoice_items.quantity * invoice_items.unit_price) as total_revenue')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();
    }

    public function getWidgetLayout($userId)
    {
        return DashboardWidget::forUser($userId)
            ->active()
            ->orderBy('position_y')
            ->orderBy('position_x')
            ->get();
    }

    public function updateWidgetLayout($userId, $widgets)
    {
        foreach ($widgets as $widget) {
            DashboardWidget::where('id', $widget['id'])
                ->where('user_id', $userId)
                ->update([
                    'position_x' => $widget['position_x'],
                    'position_y' => $widget['position_y'],
                    'width' => $widget['width'] ?? 1,
                    'height' => $widget['height'] ?? 1,
                ]);
        }

        return true;
    }

    private function getDateRange($period)
    {
        $now = Carbon::now();
        
        switch ($period) {
            case 'today':
                return [
                    'current' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                    'previous' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
                ];
            case 'week':
                return [
                    'current' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
                    'previous' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
                ];
            case 'month':
                return [
                    'current' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                    'previous' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
                ];
            case 'year':
                return [
                    'current' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
                    'previous' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
                ];
            default:
                return [
                    'current' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                    'previous' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
                ];
        }
    }
}