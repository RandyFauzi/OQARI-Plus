<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\TransferStock;
use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Models\Modifier;
use App\Models\RawMaterial;
use App\Models\StockTransfer;
use App\Services\BranchContext;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationInventoryController extends Controller
{
    public function index(Request $request, InventoryService $inventory): View
    {
        $filter = $request->validate(['transfer' => ['nullable', 'integer', 'min:1']]);
        $shop = $request->user()->shop;
        $warehouse = $inventory->location($shop->id, InventoryLocation::Warehouse);
        $outlet = $inventory->location($shop->id, InventoryLocation::Outlet);
        $kitchen = $inventory->location($shop->id, InventoryLocation::Kitchen);
        $showCentralBalances = ! $shop->branches_enabled || $request->user()->role === 'owner';
        $kitchenBalances = $showCentralBalances ? $kitchen->balances()->pluck('quantity', 'raw_material_id') : collect();
        $locations = collect([InventoryLocation::Warehouse => $warehouse, InventoryLocation::Kitchen => $kitchen, InventoryLocation::Outlet => $outlet]);
        $materials = RawMaterial::where('shop_id', $shop->id)->orderBy('name')->get();
        $warehouseBalances = $showCentralBalances ? $warehouse->balances()->pluck('quantity', 'raw_material_id') : collect();
        $outletBalances = $outlet->balances()->pluck('quantity', 'raw_material_id');
        $transfers = StockTransfer::where('shop_id', $shop->id)
            ->when(app(BranchContext::class)->id(), fn ($query) => $query->where(fn ($destinations) => $destinations->where('to_location_id', $outlet->id)
                ->when($showCentralBalances, fn ($scope) => $scope->orWhereIn('to_location_id', [$warehouse->id, $kitchen->id]))))
            ->when(isset($filter['transfer']), fn ($query) => $query->whereKey($filter['transfer']))
            ->with(['rawMaterial', 'fromLocation', 'toLocation'])
            ->orderByRaw('CASE WHEN sent_quantity > received_quantity THEN 0 ELSE 1 END')->latest('id')->paginate(20);
        $modifiers = $request->user()->can('manage-hpp') ? $this->modifiers($shop->id)->with(['group', 'ingredients'])->orderBy('name')->get() : collect();

        return view('Admin.Dashboard.tabs.inventory-locations', compact('shop', 'warehouse', 'outlet', 'materials', 'warehouseBalances', 'outletBalances', 'transfers', 'modifiers', 'kitchen', 'kitchenBalances', 'locations'));
    }

    public function stock(Request $request, TransferStock $transfer): RedirectResponse
    {
        $data = $request->validate([
            'raw_material_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'decimal:0,6', 'between:0,999999999.999999'],
            'notes' => ['required', 'string', 'max:200'],
        ]);
        $transfer->setWarehouseStock($request->user(), $data['raw_material_id'], (float) $data['quantity'], $data['notes']);

        return back()->with('status', 'Saldo gudang dicatat.');
    }

    public function send(Request $request, TransferStock $transfer): RedirectResponse
    {
        $data = $request->validate([
            'raw_material_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'decimal:0,6', 'between:0.000001,999999999.999999'],
            'request_id' => ['required', 'uuid'],
            'route' => ['nullable', Rule::in(array_keys(TransferStock::Routes))],
        ]);
        $transfer->send($request->user(), $data['raw_material_id'], (float) $data['quantity'], $data['request_id'], $data['route'] ?? TransferStock::DefaultRoute);

        return back()->with('status', 'Transfer dikirim.');
    }

    public function receive(Request $request, int $id, TransferStock $transfer): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'decimal:0,6', 'between:0.000001,999999999.999999'],
        ]);
        $transfer->receive($request->user(), $id, (float) $data['quantity']);

        return back()->with('status', 'Penerimaan transfer dicatat.');
    }

    public function saveModifierRecipe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'modifier_id' => ['required', 'integer'],
            'raw_material_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'decimal:0,6', 'between:0,999999999.999999'],
        ]);
        $shopId = $request->user()->shop_id;
        DB::transaction(function () use ($shopId, $data): void {
            $modifier = $this->modifiers($shopId)->whereKey($data['modifier_id'])->lockForUpdate()->firstOrFail();
            $material = RawMaterial::where('shop_id', $shopId)->whereKey($data['raw_material_id'])->lockForUpdate()->firstOrFail();
            if ((float) $data['amount'] === 0.0) {
                $modifier->ingredients()->detach($material->id);
            } else {
                DB::table('modifier_recipes')->upsert([[
                    'modifier_id' => $modifier->id, 'raw_material_id' => $material->id, 'amount' => $data['amount'],
                ]], ['modifier_id', 'raw_material_id'], ['amount']);
            }
        }, 3);

        return back()->with('status', 'Resep tambahan disimpan.');
    }

    private function modifiers(int $shopId): Builder
    {
        return Modifier::whereHas('group', fn (Builder $query) => $query->where('shop_id', $shopId));
    }
}
