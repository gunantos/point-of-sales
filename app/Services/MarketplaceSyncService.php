<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MarketplaceSyncService
{
    protected $clientConfigs = [];

    public function __construct()
    {
        $this->clientConfigs = config('marketplaces');
    }

    public function syncProducts(int $outletId, string $platform = 'all')
    {
        $outlet = Outlet::find($outletId);
        if (! $outlet) {
            throw new \Exception('Outlet not found');
        }

        $setting = \App\Models\PaymentSetting::forOutlet($outlet);

        $isEnabled = match($platform) {
            'gofood' => $setting->gofood_enabled ?? false,
            'shopee_food' => $setting->shopee_food_enabled ?? false,
            'grab_food' => $setting->grab_food_enabled ?? false,
            default => true,
        };

        if (! $isEnabled) {
            Log::info("Marketplace {$platform} not enabled for outlet {$outletId}");
            return ['status' => 'skipped', 'reason' => 'not_enabled'];
        }

        $products = Product::whereHas('warehouses', function ($q) use ($outletId) {
            $q->where('warehouse_id', $outletId);
        })->get();

        $results = ['synced' => 0, 'failed' => 0];

        foreach ($products as $product) {
            $warehouse = $product->warehouses()->where('warehouse_id', $outletId)->first();
            $stock = $warehouse ? (int) $warehouse->pivot->stock : 0;

            $syncEnabled = match($platform) {
                'gofood' => $setting->gofood_sync_products ?? false,
                'shopee_food' => $setting->shopee_food_sync_products ?? false,
                'grab_food' => $setting->grab_food_sync_products ?? false,
                default => true,
            };

            if (! $syncEnabled) {
                continue;
            }

            $config = $this->getPlatformConfig($platform, $setting);

            if (! $config['enabled']) {
                Log::info("Marketplace {$platform} not configured for outlet {$outletId}");
                $results['failed']++;
                continue;
            }

            try {
                $data = $this->formatProductForPlatform($product, $stock, $platform);
                $response = $this->sendToPlatform($platform, $config, $data);

                if ($response && isset($response['status']) && $response['status'] === 'success') {
                    $results['synced']++;
                } else {
                    $results['failed']++;
                    Log::error("Marketplace {$platform} sync failed for product {$product->id}: " . json_encode($response));
                }
            } catch (\Exception $e) {
                $results['failed']++;
                Log::error("Marketplace {$platform} sync exception for product {$product->id}: " . $e->getMessage());
            }
        }

        Log::info("Marketplace sync completed for outlet {$outletId}: {$results['synced']} synced, {$results['failed']} failed");

        return $results;
    }

    protected function getPlatformConfig(string $platform, \App\Models\PaymentSetting $setting): array
    {
        return match($platform) {
            'gofood' => [
                'enabled' => $setting->gofood_enabled ?? false,
                'client_id' => $setting->gofood_client_id ?? config('marketplaces.gofood.client_id'),
                'client_secret' => $setting->resolvedSecret('gofood_client_id') ?? config('marketplaces.gofood.client_secret'),
            ],
            'shopee_food' => [
                'enabled' => $setting->shopee_food_enabled ?? false,
                'client_id' => $setting->shopee_food_client_id ?? config('marketplaces.shopee_food.client_id'),
                'client_secret' => $setting->resolvedSecret('shopee_food_client_id') ?? config('marketplaces.shopee_food.client_secret'),
            ],
            'grab_food' => [
                'enabled' => $setting->grab_food_enabled ?? false,
                'client_id' => $setting->grab_food_client_id ?? config('marketplaces.grab_food.client_id'),
                'client_secret' => $setting->resolvedSecret('grab_food_client_id') ?? config('marketplaces.grab_food.client_secret'),
            ],
            default => [],
        };
    }

    protected function formatProductForPlatform(Product $product, int $stock, string $platform): array
    {
        return match($platform) {
            'gofood' => [
                'name' => $product->title,
                'description' => $product->description ?? '',
                'price' => $product->sell_price,
                'stock' => $stock,
                'category' => $product->category?->title ?? 'Makanan',
                'image' => $product->image ? asset('storage/products/' . $product->image) : null,
                'sku' => $product->sku,
            ],
            'shopee_food' => [
                'name' => $product->title,
                'description' => $product->description ?? '',
                'price' => $product->sell_price,
                'stock' => $stock,
                'category' => $product->category?->title ?? 'Makanan',
                'image' => $product->image ? asset('storage/products/' . $product->image) : null,
                'sku' => $product->sku,
                'variant_specifics' => [
                    'brand' => $product->brand ?? '',
                ],
            ],
            'grab_food' => [
                'name' => $product->title,
                'description' => $product->description ?? '',
                'price' => $product->sell_price,
                'stock' => $stock,
                'category' => $product->category?->title ?? 'Makanan',
                'image' => $product->image ? asset('storage/products/' . $product->image) : null,
                'sku' => $product->sku,
            ],
            default => [
                'name' => $product->title,
                'price' => $product->sell_price,
                'stock' => $stock,
            ],
        };
    }

    protected function sendToPlatform(string $platform, array $config, array $data): ?array
    {
        // Implementasi HTTP client ke masing-masing platform API
        // Ini adalah placeholder - implementasi sebenarnya memerlukan API keys dan endpoint masing-masing platform
        //
        // Contoh struktur:
        // $token = $this->getAccessToken($config['client_id'], $config['client_secret']);
        // $response = Http::withToken($token)->post($this->getApiEndpoint($platform . '/products'), $data);
        // return $response->json();

        // Untuk saat ini, log saja dan return mock response
        Log::info("Marketplace {$platform} sync: " . json_encode(['config' => $config, 'data' => $data]));

        // Return mock success response
        return [
            'status' => 'success',
            'message' => 'Product synced to ' . $platform,
            'platform_product_id' => 'mock_' . uniqid(),
        ];
    }
}