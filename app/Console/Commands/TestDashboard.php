<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DashboardService;
use App\Services\AlertService;

class TestDashboard extends Command
{
    protected $signature = 'test:dashboard';
    protected $description = 'Test dashboard functionality';

    public function handle()
    {
        $this->info('Testing Dashboard Service...');
        
        try {
            $dashboardService = new DashboardService();
            $alertService = new AlertService();
            
            // Test KPIs
            $this->info('Testing KPIs...');
            $kpis = $dashboardService->getKPIs('today');
            $this->info('KPIs loaded: ' . implode(', ', array_keys($kpis)));
            
            // Test Sales Trend
            $this->info('Testing Sales Trend...');
            $trend = $dashboardService->getSalesTrend('7_days');
            $this->info('Sales trend data points: ' . $trend->count());
            
            // Test Top Products
            $this->info('Testing Top Products...');
            $products = $dashboardService->getTopProducts(5);
            $this->info('Top products count: ' . $products->count());
            
            // Test Alerts
            $this->info('Testing Alerts...');
            $alerts = $alertService->getAllAlerts();
            $this->info('Alert types: ' . implode(', ', array_keys($alerts)));
            
            // Test Alert Counts
            $alertCounts = $alertService->getAlertCounts();
            $this->info('Alert counts: ' . json_encode($alertCounts));
            
            $this->info('✅ All dashboard services working correctly!');
            
        } catch (\Exception $e) {
            $this->error('❌ Dashboard test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}