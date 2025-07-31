<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Exception;

class BarcodeService
{
    /**
     * Generate barcode for product
     */
    public function generateBarcode(Product $product, string $format = 'CODE128'): array
    {
        $barcodeData = $product->barcode ?: $product->sku;
        
        // For now, we'll create a simple barcode representation
        // In a real implementation, you would use a library like picqer/php-barcode-generator
        $barcodeInfo = [
            'data' => $barcodeData,
            'format' => $format,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'price' => $product->selling_price,
            'generated_at' => now(),
        ];
        
        // Generate barcode image path (placeholder for actual implementation)
        $filename = "barcodes/{$product->id}_{$format}_" . time() . ".png";
        $barcodeInfo['image_path'] = $filename;
        $barcodeInfo['image_url'] = "/storage/{$filename}";
        
        // In a real implementation, you would generate the actual barcode image here
        // For now, we'll just return the metadata
        
        return $barcodeInfo;
    }
    
    /**
     * Generate QR code for product
     */
    public function generateQrCode(Product $product, array $options = []): array
    {
        $qrData = [
            'type' => 'product',
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'price' => $product->selling_price,
            'category' => $product->category->name,
            'url' => url("/products/{$product->id}"),
        ];
        
        // Add additional data if requested
        if ($options['include_stock'] ?? false) {
            $qrData['stock'] = $product->current_stock;
        }
        
        if ($options['include_specs'] ?? false) {
            $qrData['specifications'] = $product->specifications;
        }
        
        $qrCodeInfo = [
            'data' => json_encode($qrData),
            'raw_data' => $qrData,
            'product_id' => $product->id,
            'size' => $options['size'] ?? 200,
            'error_correction' => $options['error_correction'] ?? 'M',
            'generated_at' => now(),
        ];
        
        // Generate QR code image path (placeholder for actual implementation)
        $filename = "qrcodes/{$product->id}_" . time() . ".png";
        $qrCodeInfo['image_path'] = $filename;
        $qrCodeInfo['image_url'] = "/storage/{$filename}";
        
        // In a real implementation, you would generate the actual QR code image here
        // using a library like endroid/qr-code
        
        return $qrCodeInfo;
    }
    
    /**
     * Generate printable labels for products
     */
    public function generateProductLabels(array $productIds, array $options = []): array
    {
        $products = Product::with(['category'])->whereIn('id', $productIds)->get();
        $labels = [];
        
        foreach ($products as $product) {
            $labelData = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'price' => $product->selling_price,
                'category' => $product->category->name,
                'weight' => $product->total_weight,
                'gold_weight' => $product->gold_weight,
            ];
            
            // Generate barcode and QR code
            if ($options['include_barcode'] ?? true) {
                $labelData['barcode_info'] = $this->generateBarcode($product);
            }
            
            if ($options['include_qr'] ?? true) {
                $labelData['qr_info'] = $this->generateQrCode($product, $options);
            }
            
            // Add custom fields if specified
            if (isset($options['custom_fields'])) {
                foreach ($options['custom_fields'] as $field) {
                    if (isset($product->$field)) {
                        $labelData[$field] = $product->$field;
                    }
                }
            }
            
            $labels[] = $labelData;
        }
        
        return [
            'labels' => $labels,
            'total_count' => count($labels),
            'options' => $options,
            'generated_at' => now(),
        ];
    }
    
    /**
     * Scan and decode barcode/QR code data
     */
    public function scanCode(string $codeData, string $type = 'auto'): array
    {
        $result = [
            'success' => false,
            'type' => null,
            'data' => null,
            'product' => null,
        ];
        
        try {
            // Try to find product by barcode first
            $product = Product::where('barcode', $codeData)->first();
            
            if ($product) {
                $result['success'] = true;
                $result['type'] = 'barcode';
                $result['data'] = $codeData;
                $result['product'] = $product->load(['category']);
                return $result;
            }
            
            // Try to find product by SKU
            $product = Product::where('sku', $codeData)->first();
            
            if ($product) {
                $result['success'] = true;
                $result['type'] = 'sku';
                $result['data'] = $codeData;
                $result['product'] = $product->load(['category']);
                return $result;
            }
            
            // Try to decode as JSON (QR code)
            $decodedData = json_decode($codeData, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($decodedData['type']) && $decodedData['type'] === 'product') {
                $product = Product::find($decodedData['id']);
                
                if ($product) {
                    $result['success'] = true;
                    $result['type'] = 'qr_code';
                    $result['data'] = $decodedData;
                    $result['product'] = $product->load(['category']);
                    return $result;
                }
            }
            
            // If no product found, return the scanned data anyway
            $result['data'] = $codeData;
            $result['type'] = 'unknown';
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Validate barcode format
     */
    public function validateBarcodeFormat(string $barcode, string $format = 'EAN13'): bool
    {
        switch ($format) {
            case 'EAN13':
                return preg_match('/^\d{13}$/', $barcode) === 1;
            case 'EAN8':
                return preg_match('/^\d{8}$/', $barcode) === 1;
            case 'CODE128':
                return strlen($barcode) >= 1 && strlen($barcode) <= 80;
            case 'CODE39':
                return preg_match('/^[A-Z0-9\-\.\$\/\+\%\s]+$/', $barcode) === 1;
            default:
                return true; // Allow any format for unknown types
        }
    }
    
    /**
     * Generate batch of barcodes for multiple products
     */
    public function generateBatchBarcodes(array $productIds, string $format = 'CODE128'): array
    {
        $products = Product::whereIn('id', $productIds)->get();
        $barcodes = [];
        
        foreach ($products as $product) {
            try {
                $barcodes[] = $this->generateBarcode($product, $format);
            } catch (Exception $e) {
                $barcodes[] = [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return [
            'barcodes' => $barcodes,
            'total_count' => count($barcodes),
            'format' => $format,
            'generated_at' => now(),
        ];
    }
    
    /**
     * Get barcode/QR code statistics
     */
    public function getCodeStatistics(): array
    {
        $totalProducts = Product::count();
        $productsWithBarcode = Product::whereNotNull('barcode')->count();
        $productsWithoutBarcode = $totalProducts - $productsWithBarcode;
        
        $categoryStats = Product::select('type')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(barcode) as with_barcode')
            ->groupBy('type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->type,
                    'total' => $item->total,
                    'with_barcode' => $item->with_barcode,
                    'without_barcode' => $item->total - $item->with_barcode,
                    'percentage' => $item->total > 0 ? round(($item->with_barcode / $item->total) * 100, 2) : 0,
                ];
            });
        
        return [
            'total_products' => $totalProducts,
            'products_with_barcode' => $productsWithBarcode,
            'products_without_barcode' => $productsWithoutBarcode,
            'barcode_coverage_percentage' => $totalProducts > 0 ? round(($productsWithBarcode / $totalProducts) * 100, 2) : 0,
            'category_statistics' => $categoryStats,
        ];
    }
}