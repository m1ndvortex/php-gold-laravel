<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AlertService
{
    protected $webSocketService;

    public function __construct(WebSocketService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    public function getAllAlerts()
    {
        $cacheKey = "dashboard_alerts_" . auth()->id();
        
        return Cache::remember($cacheKey, 300, function () {
            return [
                'overdue_invoices' => $this->getOverdueInvoices(),
                'cheques_due' => $this->getChequesDue(),
                'low_inventory' => $this->getLowInventoryAlerts(),
                'high_value_transactions' => $this->getHighValueTransactions(),
                'credit_limit_warnings' => $this->getCreditLimitWarnings(),
            ];
        });
    }

    /**
     * Get all alerts and broadcast updates to connected clients
     */
    public function getAllAlertsWithBroadcast()
    {
        $alerts = $this->getAllAlerts();
        
        // Broadcast alert updates to all connected users in the tenant
        $this->webSocketService->broadcastAlertUpdate($alerts);
        
        return $alerts;
    }

    /**
     * Send real-time alert for specific events
     */
    public function sendRealTimeAlert(string $type, string $title, string $message, array $data = [], $userId = null)
    {
        $this->webSocketService->sendAlert($type, $title, $message, $data, $userId);
    }

    public function getOverdueInvoices()
    {
        $overdueInvoices = Invoice::with('customer')
            ->where('status', 'pending')
            ->where('due_date', '<', Carbon::now())
            ->orderBy('due_date')
            ->get();

        $totalOverdueAmount = $overdueInvoices->sum('total_amount');
        $criticalOverdue = $overdueInvoices->where('due_date', '<', Carbon::now()->subDays(30));

        return [
            'count' => $overdueInvoices->count(),
            'total_amount' => $totalOverdueAmount,
            'critical_count' => $criticalOverdue->count(),
            'items' => $overdueInvoices->take(10)->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $invoice->customer->name,
                    'amount' => $invoice->total_amount,
                    'due_date' => $invoice->due_date,
                    'days_overdue' => Carbon::parse($invoice->due_date)->diffInDays(Carbon::now()),
                    'severity' => $this->getOverdueSeverity($invoice->due_date),
                ];
            }),
        ];
    }

    public function getChequesDue()
    {
        $chequesDue = Payment::with(['invoice.customer'])
            ->where('payment_method', 'cheque')
            ->where('status', 'pending')
            ->where('cheque_due_date', '<=', Carbon::now()->addDays(7))
            ->orderBy('cheque_due_date')
            ->get();

        return [
            'count' => $chequesDue->count(),
            'total_amount' => $chequesDue->sum('amount'),
            'due_today' => $chequesDue->where('cheque_due_date', '<=', Carbon::now()->endOfDay())->count(),
            'items' => $chequesDue->take(10)->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'cheque_number' => $payment->cheque_number,
                    'customer_name' => $payment->invoice->customer->name,
                    'amount' => $payment->amount,
                    'due_date' => $payment->cheque_due_date,
                    'days_until_due' => Carbon::now()->diffInDays(Carbon::parse($payment->cheque_due_date), false),
                    'severity' => $this->getChequeSeverity($payment->cheque_due_date),
                ];
            }),
        ];
    }

    public function getLowInventoryAlerts()
    {
        $lowStockProducts = Product::with('category')
            ->whereRaw('current_stock <= minimum_stock')
            ->where('minimum_stock', '>', 0)
            ->orderBy('current_stock')
            ->get();

        $outOfStockProducts = $lowStockProducts->where('current_stock', '<=', 0);
        $criticalStockProducts = $lowStockProducts->where('current_stock', '>', 0)
            ->where('current_stock', '<=', function ($product) {
                return $product->minimum_stock * 0.5;
            });

        return [
            'count' => $lowStockProducts->count(),
            'out_of_stock_count' => $outOfStockProducts->count(),
            'critical_count' => $criticalStockProducts->count(),
            'items' => $lowStockProducts->take(10)->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category->name ?? 'N/A',
                    'current_stock' => $product->current_stock,
                    'minimum_stock' => $product->minimum_stock,
                    'stock_percentage' => $product->minimum_stock > 0 
                        ? round(($product->current_stock / $product->minimum_stock) * 100, 2) 
                        : 0,
                    'severity' => $this->getStockSeverity($product->current_stock, $product->minimum_stock),
                ];
            }),
        ];
    }

    public function getHighValueTransactions()
    {
        $threshold = 50000; // Configurable threshold
        $highValueInvoices = Invoice::with('customer')
            ->where('total_amount', '>=', $threshold)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->orderByDesc('total_amount')
            ->take(5)
            ->get();

        return [
            'count' => $highValueInvoices->count(),
            'total_amount' => $highValueInvoices->sum('total_amount'),
            'items' => $highValueInvoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $invoice->customer->name,
                    'amount' => $invoice->total_amount,
                    'created_at' => $invoice->created_at,
                    'type' => $invoice->type,
                ];
            }),
        ];
    }

    public function getCreditLimitWarnings()
    {
        $customers = \App\Models\Customer::with(['invoices' => function ($query) {
                $query->where('status', 'pending');
            }])
            ->where('credit_limit', '>', 0)
            ->get()
            ->filter(function ($customer) {
                $pendingAmount = $customer->invoices->sum('total_amount');
                $usedPercentage = ($pendingAmount / $customer->credit_limit) * 100;
                return $usedPercentage >= 80; // 80% threshold
            });

        return [
            'count' => $customers->count(),
            'items' => $customers->take(10)->map(function ($customer) {
                $pendingAmount = $customer->invoices->sum('total_amount');
                $usedPercentage = ($pendingAmount / $customer->credit_limit) * 100;
                
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'credit_limit' => $customer->credit_limit,
                    'used_amount' => $pendingAmount,
                    'used_percentage' => round($usedPercentage, 2),
                    'available_credit' => $customer->credit_limit - $pendingAmount,
                    'severity' => $usedPercentage >= 100 ? 'critical' : ($usedPercentage >= 90 ? 'high' : 'medium'),
                ];
            }),
        ];
    }

    public function getAlertCounts()
    {
        return [
            'overdue_invoices' => $this->getOverdueInvoices()['count'],
            'cheques_due' => $this->getChequesDue()['count'],
            'low_inventory' => $this->getLowInventoryAlerts()['count'],
            'credit_warnings' => $this->getCreditLimitWarnings()['count'],
        ];
    }

    private function getOverdueSeverity($dueDate)
    {
        $daysOverdue = Carbon::parse($dueDate)->diffInDays(Carbon::now());
        
        if ($daysOverdue >= 30) return 'critical';
        if ($daysOverdue >= 14) return 'high';
        if ($daysOverdue >= 7) return 'medium';
        return 'low';
    }

    private function getChequeSeverity($dueDate)
    {
        $daysUntilDue = Carbon::now()->diffInDays(Carbon::parse($dueDate), false);
        
        if ($daysUntilDue <= 0) return 'critical';
        if ($daysUntilDue <= 1) return 'high';
        if ($daysUntilDue <= 3) return 'medium';
        return 'low';
    }

    private function getStockSeverity($currentStock, $minimumStock)
    {
        if ($currentStock <= 0) return 'critical';
        if ($minimumStock > 0) {
            $percentage = ($currentStock / $minimumStock) * 100;
            if ($percentage <= 25) return 'high';
            if ($percentage <= 50) return 'medium';
        }
        return 'low';
    }
}