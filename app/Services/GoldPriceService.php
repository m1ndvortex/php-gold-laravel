<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GoldPriceService
{
    protected string $cacheKey = 'gold_price_current';
    protected int $cacheDuration = 3600; // 1 hour in seconds

    /**
     * Get current gold price per gram
     */
    public function getCurrentGoldPrice(): float
    {
        return Cache::remember($this->cacheKey, $this->cacheDuration, function () {
            return $this->fetchGoldPriceFromApi();
        });
    }

    /**
     * Get gold price for a specific date
     */
    public function getGoldPriceForDate(Carbon $date): float
    {
        $cacheKey = "gold_price_" . $date->format('Y-m-d');
        
        return Cache::remember($cacheKey, 86400, function () use ($date) {
            return $this->fetchGoldPriceFromApi($date);
        });
    }

    /**
     * Fetch gold price from external API
     */
    protected function fetchGoldPriceFromApi(Carbon $date = null): float
    {
        try {
            // For demo purposes, we'll use a mock API response
            // In production, you would integrate with a real gold price API
            $response = $this->mockGoldPriceApi($date);
            
            if ($response && isset($response['price_per_gram'])) {
                Log::info('Gold price fetched successfully', [
                    'price' => $response['price_per_gram'],
                    'date' => $date ? $date->format('Y-m-d') : 'current',
                ]);
                
                return (float) $response['price_per_gram'];
            }
            
            // Fallback to default price if API fails
            return $this->getDefaultGoldPrice();
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch gold price from API', [
                'error' => $e->getMessage(),
                'date' => $date ? $date->format('Y-m-d') : 'current',
            ]);
            
            return $this->getDefaultGoldPrice();
        }
    }

    /**
     * Mock gold price API for demonstration
     * In production, replace this with actual API integration
     */
    protected function mockGoldPriceApi(Carbon $date = null): array
    {
        // Simulate API response with realistic gold prices in IRR per gram
        $basePrice = 2500000; // Base price in IRR (Iranian Rial)
        
        // Add some variation based on date
        if ($date) {
            $dayOfYear = $date->dayOfYear;
            $variation = sin($dayOfYear / 365 * 2 * pi()) * 100000; // ±100,000 IRR variation
            $price = $basePrice + $variation;
        } else {
            // Current price with small random variation
            $variation = (mt_rand(-50, 50) / 100) * 50000; // ±25,000 IRR variation
            $price = $basePrice + $variation;
        }
        
        return [
            'price_per_gram' => round($price, 2),
            'currency' => 'IRR',
            'date' => $date ? $date->format('Y-m-d') : now()->format('Y-m-d'),
            'source' => 'mock_api',
        ];
    }

    /**
     * Get default gold price when API is unavailable
     */
    protected function getDefaultGoldPrice(): float
    {
        // Default fallback price in IRR per gram
        return 2500000.00;
    }

    /**
     * Update gold price manually
     */
    public function updateGoldPrice(float $pricePerGram, string $source = 'manual'): void
    {
        Cache::put($this->cacheKey, $pricePerGram, $this->cacheDuration);
        
        Log::info('Gold price updated manually', [
            'price' => $pricePerGram,
            'source' => $source,
        ]);
    }

    /**
     * Clear gold price cache
     */
    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
        
        // Clear date-specific caches (last 30 days)
        for ($i = 0; $i < 30; $i++) {
            $date = now()->subDays($i);
            $cacheKey = "gold_price_" . $date->format('Y-m-d');
            Cache::forget($cacheKey);
        }
        
        Log::info('Gold price cache cleared');
    }

    /**
     * Get gold price history for a date range
     */
    public function getGoldPriceHistory(Carbon $startDate, Carbon $endDate): array
    {
        $history = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            $history[] = [
                'date' => $currentDate->format('Y-m-d'),
                'price_per_gram' => $this->getGoldPriceForDate($currentDate),
            ];
            
            $currentDate->addDay();
        }
        
        return $history;
    }

    /**
     * Calculate gold value for given weight
     */
    public function calculateGoldValue(float $weightInGrams, Carbon $date = null): float
    {
        $pricePerGram = $date ? $this->getGoldPriceForDate($date) : $this->getCurrentGoldPrice();
        return $weightInGrams * $pricePerGram;
    }

    /**
     * Convert gold price between currencies (placeholder for future implementation)
     */
    public function convertGoldPrice(float $price, string $fromCurrency, string $toCurrency): float
    {
        // For now, just return the same price
        // In production, implement currency conversion
        return $price;
    }

    /**
     * Get gold price statistics
     */
    public function getGoldPriceStatistics(int $days = 30): array
    {
        $endDate = now();
        $startDate = $endDate->copy()->subDays($days);
        $history = $this->getGoldPriceHistory($startDate, $endDate);
        
        $prices = array_column($history, 'price_per_gram');
        
        return [
            'current_price' => $this->getCurrentGoldPrice(),
            'min_price' => min($prices),
            'max_price' => max($prices),
            'avg_price' => array_sum($prices) / count($prices),
            'price_change' => $this->getCurrentGoldPrice() - $prices[0],
            'price_change_percentage' => (($this->getCurrentGoldPrice() - $prices[0]) / $prices[0]) * 100,
            'period_days' => $days,
        ];
    }
}