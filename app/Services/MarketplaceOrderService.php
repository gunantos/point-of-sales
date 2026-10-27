<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Warehouse as WarehouseModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class MarketplaceOrderService
{
    public function handleOrder(string $platform, array $payload): Transaction
    {
        return DB::transaction(function () use ($platform, $payload) {
            // 1. Parse data dari payload platform menjadi format internal
            $orderData = $this->parsePlatformPayload($platform, $payload);

            // 2. Cari product di POS berdasarkan nama/sku
            $product = $this->findProduct($orderData['product_name'], $orderData['product_sku']);

            if (! $product) {
                throw new \Exception("Product not found: {$orderData['product_name']}");
            }

            // 3. Dapatkan warehouse/outlet dari payload
            $outletId = $orderData['outlet_id'] ?? null;
            $warehouse = null;

            if ($outletId) {
                $warehouse = WarehouseModel::where('outlet_id', $outletId)
                    ->where('is_active', true)
                    ->first();
            }

            if (! $warehouse) {
                // Fallback: gunakan warehouse default outlet pengguna
                $warehouse = WarehouseModel::where('is_active', true)->first();
            }

            if (! $warehouse) {
                throw new \Exception('No active warehouse found');
            }

            // 4. Kurangi stok dengan lockForUpdate
            $productWarehouse = ProductWarehouse::where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->lockForUpdate()
                ->first();

            if (! $productWarehouse) {
                // Buat record baru jika belum ada
                $productWarehouse = new ProductWarehouse();
                $productWarehouse->product_id = $product->id;
                $productWarehouse->warehouse_id = $warehouse->id;
            }

            $orderedQty = $orderData['qty'];

            if ($productWarehouse->pivot->stock < $orderedQty) {
                throw new \Exception("Stok tidak mencukup untuk {$product->title}: tersedia {$productWarehouse->pivot->stock}, dipesan {$orderedQty}");
            }

            $productWarehouse->pivot->stock -= $orderedQty;
            $productWarehouse->save();

            // 5. Update stok global produk (decrement)
            // Product::where('id', $product->id)->decrement('stock', $orderedQty);

            // 6. Buat Transaction
            $invoice = $orderData['invoice_number'] ?? 'MW-' . uniqid();

            $transaction = Transaction::create([
                'warehouse_id' => $warehouse->id,
                'customer_id' => $orderData['customer_id'] ?? null,
                'invoice' => $invoice,
                'order_type' => $platform, // 'gofood' / 'shopeefood' / 'grabfood'
                'payment_status' => 'pending',
                'payment_method' => $platform,
                'grand_total' => $orderData['total_amount'],
                'cash' => 0,
                'change' => 0,
                'discount' => 0,
                'tax_total' => 0,
                'note' => "Order from {$platform} - " . ($orderData['note'] ?? ''),
                'client_uuid' => $orderData['client_uuid'] ?? null,
            ]);

            // 7. Tambah detail transaction
            $transaction->details()->create([
                'product_id' => $product->id,
                'qty' => $orderedQty,
                'price' => $orderData['price_per_unit'],
                'total' => $orderData['total_amount'],
            ]);

            // 8. Catat log sinkronisasi
            Log::info("Marketplace order created: {$platform} - {$transaction->invoice} - {$product->title} x {$orderedQty}");

            return $transaction;
        });
    }

    protected function parsePlatformPayload(string $platform, array $payload): array
    {
        // Parse payload masing-masing platform menjadi format internal
        // Setiap platform punya struktur JSON berbeda

        return match($platform) {
            'gofood' => [
                'product_name' => $payload['items'][0]['name'] ?? '',
                'product_sku' => $payload['items'][0]['sku'] ?? '',
                'qty' => $payload['items'][0]['qty'] ?? 1,
                'price_per_unit' => $payload['items'][0]['price'] ?? 0,
                'total_amount' => $payload['gross_amount'] ?? 0,
                'outlet_id' => $payload['outlet_code'] ?? null,
                'customer_id' => $payload['customer_id'] ?? null,
                'invoice_number' => $payload['order_id'] ?? '',
                'client_uuid' => $payload['client_uuid'] ?? null,
                'note' => $payload['note'] ?? '',
            ],
            'shopee_food' => [
                'product_name' => $payload['item_name'] ?? '',
                'product_sku' => $payload['sku'] ?? '',
                'qty' => $payload['item_qty'] ?? 1,
                'price_per_unit' => $payload['item_price'] ?? 0,
                'total_amount' => $payload['total'] ?? 0,
                'outlet_id' => $payload['outlet_id'] ?? null,
                'customer_id' => $payload['buyer_id'] ?? null,
                'invoice_number' => $payload['order_sn'] ?? '',
                'client_uuid' => $payload['client_uuid'] ?? null,
                'note' => $payload['note'] ?? '',
            ],
            'grab_food' => [
                'product_name' => $payload['item_name'] ?? '',
                'product_sku' => $payload['sku'] ?? '',
                'qty' => $payload['quantity'] ?? 1,
                'price_per_unit' => $payload['unit_price'] ?? 0,
                'total_amount' => $payload['total_amount'] ?? 0,
                'outlet_id' => $payload['outlet_code'] ?? null,
                'customer_id' => $payload['customer_id'] ?? null,
                'invoice_number' => $payload['order_id'] ?? '',
                'client_uuid' => $payload['client_uuid'] ?? null,
                'note' => $payload['note'] ?? '',
            ],
            default => [
                'product_name' => $payload['product_name'] ?? '',
                'product_sku' => $payload['product_sku'] ?? '',
                'qty' => $payload['qty'] ?? 1,
                'price_per_unit' => $payload['price'] ?? 0,
                'total_amount' => $payload['total'] ?? 0,
                'outlet_id' => null,
                'customer_id' => null,
                'invoice_number' => '',
                'client_uuid' => null,
            ],
        };
    }

    protected function findProduct(string $name, ?string $sku = null): ?Product
    {
        // Cari product berdasarkan nama terlebih dahulu
        $product = Product::where('title', 'like', '%' . $name . '%')->first();

        // Jika ada SKU, coba cari juga berdasarkan SKU
        if ($sku && ! $product) {
            $product = Product::where('sku', $sku)->first();
        }

        // Jika tidak ditemukan, cari yang paling sesuai
        if (! $product && $name) {
            $product = Product::where('title', 'like', '%' . $name . '%')
                ->latest('updated_at')
                ->first();
        }

        return $product;
    }
}