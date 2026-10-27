<?php

namespace App\Console\Commands;

use App\Services\MarketplaceSyncService;
use Illuminate\Console\Command;

class SyncMarketplaceProductsCommand extends Command
{
    protected $signature = 'marketplace:sync-products';
    protected $description = 'Sinkron produk ke marketplace (GoFood, ShopeeFood, GrabFood)';

    public function handle(MarketplaceSyncService $syncService)
    {
        $this->info('Memulai sinkron produk ke marketplace...');

        $outlets = \App\Models\Outlet::where('is_active', true)->get();

        $totalSynced = 0;
        $totalFailed = 0;

        foreach ($outlets as $outlet) {
            $this->info("Sinkron outlet: {$outlet->code} ({$outlet->name})");

            try {
                $results = $syncService->syncProducts($outlet->id, 'all');

                $totalSynced += $results['synced'] ?? 0;
                $totalFailed += $results['failed'] ?? 0;

                $this->info("  -> Sinkronisasi: {$results['synced']} berhasil, {$results['failed']} gagal");
            } catch (\Exception $e) {
                $this->error("  -> Error: " . $e->getMessage());
                Log::error("Marketplace sync error for outlet {$outlet->id}: " . $e->getMessage());
            }
        }

        $this->info("Selesai sinkron produk ke marketplace.");
        $this->info("Total sinkron: {$totalSynced} berhasil, {$totalFailed} gagal");

        return Command::SUCCESS;
    }
}