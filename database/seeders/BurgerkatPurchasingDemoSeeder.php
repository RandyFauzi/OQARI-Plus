<?php

namespace Database\Seeders;

use App\Actions\Inventory\ManagePurchasing;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BurgerkatPurchasingDemoSeeder extends Seeder
{
    public function run(ManagePurchasing $purchasing): void
    {
        if (! app()->environment('local', 'staging')) {
            return;
        }

        $order = DB::transaction(function () use ($purchasing): PurchaseOrder {
            $shop = Shop::where('slug', 'burgerkat-demo')->where('name', 'Burgerkat Demo')->lockForUpdate()->sole();
            $owner = User::where('shop_id', $shop->id)->where('role', 'owner')->where('is_active', true)->unarchived()->sole();
            $requestId = '870ccbd0-3c6f-4cb6-954a-d89ebf5305a1';
            $existing = PurchaseOrder::where('shop_id', $shop->id)->where('request_id', $requestId)->first();
            if ($existing) {
                return $existing;
            }

            $patty = RawMaterial::where('shop_id', $shop->id)->where('name', 'Patty Sapi Tebal')->sole();
            $supplier = $purchasing->addSupplier($owner, 'Supplier Daging Burgerkat (Demo)', null);

            return $purchasing->createOrder($owner, [
                'supplier_id' => $supplier->id,
                'request_id' => $requestId,
                'notes' => 'Contoh pembelian Burgerkat untuk simulasi penerimaan sebagian.',
                'items' => [['raw_material_id' => $patty->id, 'quantity' => 100, 'unit_cost' => $patty->cost_per_unit]],
            ]);
        }, 3);

        $this->command?->info('Burgerkat Demo · PO #'.$order->id.' · '.$order->statusLabel());
    }
}
