<?php

namespace Database\Seeders;

use App\Actions\Inventory\ManageOutletRequests;
use App\Actions\Inventory\ManagePettyPurchases;
use App\Actions\Inventory\ManageStockCounts;
use App\Actions\Inventory\TransferStock;
use App\Models\InventoryLocation;
use App\Models\OutletStockRequest;
use App\Models\PettyPurchase;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\StockCount;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BurgerkatInventoryControlsDemoSeeder extends Seeder
{
    public function run(ManageOutletRequests $requests, TransferStock $transfers, ManageStockCounts $counts, ManagePettyPurchases $purchases): void
    {
        if (! app()->environment('local', 'staging')) {
            return;
        }
        $shop = Shop::where('slug', 'burgerkat-demo')->where('name', 'Burgerkat Demo')->sole();
        $owner = User::where('shop_id', $shop->id)->where('role', 'owner')->where('is_active', true)->unarchived()->sole();

        [$request, $count] = DB::transaction(function () use ($shop, $owner, $requests, $transfers, $counts): array {
            Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            $requestId = '89a613db-d01d-4877-9674-9a3bb74c53f3';
            $countId = '3c0966b3-e120-4625-9325-45b0c55cae63';
            $request = OutletStockRequest::where('shop_id', $shop->id)->where('request_id', $requestId)->first();
            if ($request) {
                return [$request, StockCount::where('shop_id', $shop->id)->where('request_id', $countId)->sole()];
            }

            $sauce = RawMaterial::where('shop_id', $shop->id)->where('name', 'Saus Burgerkat')->where('unit', 'ml')->sole();
            $request = $requests->create($owner, [
                'request_id' => $requestId, 'raw_material_id' => $sauce->id, 'source' => InventoryLocation::Kitchen,
                'quantity' => 500, 'notes' => 'Simulasi permintaan Saus Burgerkat dari outlet.',
            ]);
            $requests->review($owner, $request->id, true, 500, 'Disetujui untuk simulasi.');
            $requests->dispatch($owner, $request->id, '0907c645-0ed2-4200-957b-fffba292ee17');
            $request->refresh();
            $transfers->receive($owner, $request->stock_transfer_id, 480);
            $system = (float) $sauce->fresh()->current_stock;
            if ($system < 20) {
                throw new RuntimeException('Stok demo tidak cukup untuk simulasi selisih opname.');
            }
            $count = $counts->record($owner, [
                'request_id' => $countId, 'location' => InventoryLocation::Outlet, 'frequency' => 'WEEKLY',
                'count_date' => now(config('attendance.timezone'))->toDateString(),
                'notes' => 'Simulasi hitung fisik: selisih 20 ml pada wadah outlet.',
                'items' => [['raw_material_id' => $sauce->id, 'counted_quantity' => round($system - 20, 6)]],
            ]);
            $counts->review($owner, $count->id, true, 'Selisih demo dikonfirmasi.');

            return [$request, $count->refresh()];
        }, 3);

        $purchaseId = '6c4d0651-258f-410e-9a3c-ba92eb415ccf';
        $purchase = PettyPurchase::where('shop_id', $shop->id)->where('request_id', $purchaseId)->first();
        if (! $purchase) {
            $egg = RawMaterial::where('shop_id', $shop->id)->where('name', 'Telur Ayam')->where('unit', 'pcs')->sole();
            $temporary = tempnam(sys_get_temp_dir(), 'oqari-proof-');
            if ($temporary === false) {
                throw new RuntimeException('Tidak bisa menyiapkan kuitansi demo.');
            }
            try {
                $pdf = Pdf::loadHTML('<h1>Kuitansi simulasi Burgerkat Demo</h1><p>12 telur ayam × Rp2.000 = Rp24.000.</p><p>Dokumen dummy untuk pengujian OQARI.</p>')->output();
                if (file_put_contents($temporary, $pdf) === false) {
                    throw new RuntimeException('Tidak bisa menulis kuitansi demo.');
                }
                $purchase = $purchases->create($owner, [
                    'request_id' => $purchaseId, 'description' => 'Pembelian telur untuk simulasi outlet',
                    'amount' => 24000, 'funding' => 'EXTERNAL', 'raw_material_id' => $egg->id, 'quantity' => 12,
                    'proof' => new UploadedFile($temporary, 'kuitansi-burgerkat-demo.pdf', 'application/pdf', null, true),
                ]);
            } finally {
                unlink($temporary);
            }
        }
        $purchases->review($owner, $purchase->id, true, 'Bukti dummy diperiksa.');
        $purchases->receive($owner, $purchase->id);

        $this->command?->info("Burgerkat Demo · Permintaan #{$request->id} · Opname #{$count->id} · Pembelian kecil #{$purchase->id}");
    }
}
