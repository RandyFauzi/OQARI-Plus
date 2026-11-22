<?php

namespace App\Actions\Inventory;

use App\Models\InventoryLocation;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\StockCount;
use App\Models\StockMutation;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\InventoryService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageStockCounts
{
    public function __construct(private InventoryService $inventory) {}

    /** @param array{request_id: string, location: string, frequency: string, count_date: string, notes?: ?string, items: array<array{raw_material_id: int, counted_quantity: numeric}>} $data */
    public function record(User $actor, array $data): StockCount
    {
        return DB::transaction(function () use ($actor, $data): StockCount {
            Shop::whereKey($actor->shop_id)->lockForUpdate()->firstOrFail();
            $existing = StockCount::where('shop_id', $actor->shop_id)->where('request_id', $data['request_id'])->first();
            if ($existing) {
                return $existing;
            }
            abort_if(app(BranchContext::class)->id() && $actor->role !== 'owner' && $data['location'] !== InventoryLocation::Outlet, 403);
            $location = $this->inventory->location($actor->shop_id, $data['location']);
            $date = CarbonImmutable::parse($data['count_date'], config('attendance.timezone'));
            $start = $data['frequency'] === 'WEEKLY' ? $date->startOfWeek(CarbonInterface::MONDAY) : $date;
            $end = $data['frequency'] === 'WEEKLY' ? $start->addDays(6) : $start;
            $physical = collect($data['items'])->mapWithKeys(fn (array $item) => [$item['raw_material_id'] => (float) $item['counted_quantity']]);
            $materials = RawMaterial::where('shop_id', $actor->shop_id)->whereIn('id', $physical->keys())
                ->orderBy('id')->lockForUpdate()->get();
            abort_unless($materials->count() === $physical->count(), 404);

            $count = StockCount::create([
                'shop_id' => $actor->shop_id, 'inventory_location_id' => $location->id, 'request_id' => $data['request_id'],
                'frequency' => $data['frequency'], 'counted_on' => $date->toDateString(), 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
                'notes' => $data['notes'] ?? null, 'status' => 'PENDING', 'created_by' => $actor->id,
            ]);
            foreach ($materials as $material) {
                $system = $this->inventory->quantity($material, $location);
                $counted = $physical[$material->id];
                $difference = round($counted - $system, 6);
                if (abs($difference) > 999999999.999999) {
                    throw ValidationException::withMessages(['items' => 'Selisih melampaui batas penyimpanan.']);
                }
                $count->items()->create([
                    'raw_material_id' => $material->id, 'last_mutation_id' => $this->lastMutation($location, $material),
                    'unit' => $material->unit, 'system_quantity' => $system, 'counted_quantity' => $counted, 'difference' => $difference,
                ]);
            }

            return $count;
        }, 3);
    }

    public function review(User $actor, int $id, bool $approve, ?string $note): void
    {
        DB::transaction(function () use ($actor, $id, $approve, $note): void {
            $count = StockCount::where('shop_id', $actor->shop_id)->whereKey($id)->lockForUpdate()->firstOrFail();
            $status = $approve ? 'POSTED' : 'REJECTED';
            if ($count->status === $status) {
                return;
            }
            if ($count->status !== 'PENDING') {
                throw ValidationException::withMessages(['decision' => 'Opname sudah ditinjau.']);
            }
            if ($approve) {
                $this->post($actor, $count);
            }
            $count->update(['status' => $status, 'decision_note' => $note, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        }, 3);
    }

    private function post(User $actor, StockCount $count): void
    {
        $location = $count->location;
        $items = $count->items()->get()->keyBy('raw_material_id');
        $materials = RawMaterial::where('shop_id', $actor->shop_id)->whereIn('id', $items->keys())->orderBy('id')->lockForUpdate()->get();
        if ($materials->count() !== $items->count()) {
            throw ValidationException::withMessages(['items' => 'Ada bahan yang diarsipkan. Lakukan hitung ulang.']);
        }
        foreach ($materials as $material) {
            $item = $items[$material->id];
            if (strcasecmp($item->unit, $material->unit) !== 0
                || $this->lastMutation($location, $material) !== $item->last_mutation_id
                || $this->inventory->quantity($material, $location) !== (float) $item->system_quantity) {
                throw ValidationException::withMessages(['items' => 'Stok '.$material->name.' berubah setelah dihitung. Tolak catatan ini dan lakukan hitung ulang.']);
            }
            if ((float) $item->difference !== 0.0) {
                $this->inventory->move($material, $location, (float) $item->difference, 'adjustment', 'StockCount', $count->id,
                    'Penyesuaian opname #'.$count->id, $actor->id);
            }
        }
    }

    private function lastMutation(InventoryLocation $location, RawMaterial $material): ?int
    {
        $last = StockMutation::where('shop_id', $location->shop_id)->where('inventory_location_id', $location->id)
            ->where('raw_material_id', $material->id)->latest('id')->lockForUpdate()->value('id');

        return $last === null ? null : (int) $last;
    }
}
