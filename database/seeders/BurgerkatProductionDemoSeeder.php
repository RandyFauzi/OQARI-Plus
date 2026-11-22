<?php

namespace Database\Seeders;

use App\Actions\Inventory\ManageProduction;
use App\Actions\Inventory\TransferStock;
use App\Models\ProductionWorkOrder;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BurgerkatProductionDemoSeeder extends Seeder
{
    public function run(ManageProduction $production, TransferStock $transfers): void
    {
        if (! app()->environment('local', 'staging')) {
            return;
        }

        $order = DB::transaction(function () use ($production, $transfers): ProductionWorkOrder {
            $shop = Shop::where('slug', 'burgerkat-demo')->where('name', 'Burgerkat Demo')->lockForUpdate()->sole();
            $owner = User::where('shop_id', $shop->id)->where('role', 'owner')->where('is_active', true)->unarchived()->sole();
            $requestId = '6d16100a-47a8-4dbf-836b-b9f60b335357';
            $existing = ProductionWorkOrder::where('shop_id', $shop->id)->where('request_id', $requestId)->first();
            if ($existing) {
                return $existing;
            }

            $sauce = RawMaterial::where('shop_id', $shop->id)->where('name', 'Saus Burgerkat')->where('unit', 'ml')->sole();
            $ingredients = [
                ['Basis Mayones (Demo CK)', 4000, 30],
                ['Basis Tomat (Demo CK)', 550, 25],
                ['Larutan Bumbu (Demo CK)', 450, 20],
            ];
            $recipeItems = [];
            foreach ($ingredients as [$name, $quantity, $unitCost]) {
                $material = RawMaterial::firstOrCreate(
                    ['shop_id' => $shop->id, 'name' => $name],
                    ['unit' => 'ml', 'cost_per_unit' => $unitCost, 'current_stock' => 0],
                );
                if (! $material->wasRecentlyCreated) {
                    throw new RuntimeException('Nama bahan simulasi sudah digunakan. Saldo lama tidak diubah.');
                }

                $transfers->setWarehouseStock($owner, $material->id, (float) $quantity, 'Stok awal simulasi Central Kitchen');
                $transfer = $transfers->send($owner, $material->id, (float) $quantity, (string) Str::uuid(), 'warehouse-kitchen');
                $transfers->receive($owner, $transfer->id, (float) $quantity);
                $recipeItems[] = ['raw_material_id' => $material->id, 'quantity' => $quantity];
            }

            $recipe = $production->createRecipe($owner, [
                'request_id' => 'a1ce9cae-d133-4e19-bca1-f8463372f972',
                'output_material_id' => $sauce->id,
                'yield_quantity' => 5000,
                'notes' => 'Resep dummy untuk simulasi produksi.',
                'items' => $recipeItems,
            ]);
            $order = $production->createWorkOrder($owner, $recipe->id, 5000, $requestId, 'Simulasi batch Saus Burgerkat');
            $production->recordResult($owner, $order->id, [
                'revision' => 0, 'good_quantity' => 4600, 'waste_quantity' => 400,
                'waste_reason' => 'Sisa saus pada alat pencampur dan wadah.',
                'items' => $order->items()->get()->map(fn ($item) => [
                    'work_order_item_id' => $item->id, 'actual_quantity' => $item->planned_quantity,
                ])->all(),
            ]);
            $production->postResult($owner, $order->id, $order->fresh()->result_revision);

            return $order->refresh();
        }, 3);

        $this->command?->info('Burgerkat Demo · Surat kerja #'.$order->id.' · '.$order->statusLabel());
    }
}
