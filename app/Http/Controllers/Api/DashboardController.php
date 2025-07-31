<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
        private AlertService $alertService
    ) {}

    public function getKPIs(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        
        $kpis = $this->dashboardService->getKPIs($period);
        
        return response()->json([
            'success' => true,
            'data' => $kpis,
        ]);
    }

    public function getSalesTrend(Request $request): JsonResponse
    {
        $period = $request->get('period', '30_days');
        
        $trend = $this->dashboardService->getSalesTrend($period);
        
        return response()->json([
            'success' => true,
            'data' => $trend,
        ]);
    }

    public function getTopProducts(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        
        $products = $this->dashboardService->getTopProducts($limit);
        
        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function getAlerts(): JsonResponse
    {
        $alerts = $this->alertService->getAllAlerts();
        
        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    public function getAlertCounts(): JsonResponse
    {
        $counts = $this->alertService->getAlertCounts();
        
        return response()->json([
            'success' => true,
            'data' => $counts,
        ]);
    }

    public function getWidgetLayout(): JsonResponse
    {
        $layout = $this->dashboardService->getWidgetLayout(auth()->id());
        
        return response()->json([
            'success' => true,
            'data' => $layout,
        ]);
    }

    public function updateWidgetLayout(Request $request): JsonResponse
    {
        $request->validate([
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|integer',
            'widgets.*.position_x' => 'required|integer|min:0',
            'widgets.*.position_y' => 'required|integer|min:0',
            'widgets.*.width' => 'integer|min:1|max:12',
            'widgets.*.height' => 'integer|min:1|max:12',
        ]);

        $result = $this->dashboardService->updateWidgetLayout(
            auth()->id(),
            $request->widgets
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Widget layout updated successfully' : 'Failed to update widget layout',
        ]);
    }

    public function getDashboardData(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        
        $data = [
            'kpis' => $this->dashboardService->getKPIs($period),
            'alerts' => $this->alertService->getAlertCounts(),
            'sales_trend' => $this->dashboardService->getSalesTrend('7_days'),
            'top_products' => $this->dashboardService->getTopProducts(5),
            'widget_layout' => $this->dashboardService->getWidgetLayout(auth()->id()),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}