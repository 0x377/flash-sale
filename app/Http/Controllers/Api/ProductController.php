<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RedisStockCacheService;
use App\Repositories\ProductRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    protected ProductRepository $productRepository;
    protected RedisStockCacheService $cacheService;

    public function __construct(ProductRepository $productRepository, RedisStockCacheService $cacheService)
    {
        $this->productRepository = $productRepository;
        $this->cacheService = $cacheService;
    }

    /**
     * Show product details with dynamic stock.
     */
    public function show(int $id): JsonResponse
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer|exists:products,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'validation_failed',
                'message' => 'Invalid product ID',
                'errors' => $validator->errors()
            ], 422);
        }

        $cacheKey = "product:{$id}:details";

        // Cache without tags for file-based stores
        $productData = Cache::remember($cacheKey, 5, function () use ($id) {
            Log::debug('Product cache miss', ['product_id' => $id]);
            return $this->productRepository->getProductWithStock($id);
        });

        if (!$productData) {
            return response()->json([
                'error' => 'product_not_found',
                'message' => 'Product not found or inactive'
            ], 404);
        }

        $productData['cache_info'] = [
            'cached' => Cache::has($cacheKey),
            'response_time' => microtime(true) - LARAVEL_START
        ];

        Log::info('Product view', [
            'product_id' => $id,
            'available_stock' => $productData['available_stock'],
            'cached' => $productData['cache_info']['cached']
        ]);

        return response()->json([
            'data' => $productData,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID')
            ]
        ]);
    }

    /**
     * Optimized stock endpoint
     */
    public function stock(int $id): JsonResponse
    {
        $stock = $this->cacheService->getStockWithFallback($id, function () use ($id) {
            return $this->productRepository->calculateAvailableStock($id);
        });

        return response()->json([
            'data' => [
                'product_id'      => $id,
                'available_stock' => $stock,
                'timestamp'       => now()->toISOString()
            ]
        ]);
    }
}
