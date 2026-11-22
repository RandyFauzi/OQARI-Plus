<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\ManageProduction;
use App\Http\Controllers\Controller;
use App\Models\ProductionRecipe;
use App\Models\ProductionWorkOrder;
use App\Models\RawMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CentralKitchenController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $request->user()->shop;
        $orders = ProductionWorkOrder::where('shop_id', $shop->id)->with('recipe.outputMaterial')->latest('id')->paginate(20);
        $recipes = ProductionRecipe::where('shop_id', $shop->id)->with(['outputMaterial', 'items.rawMaterial'])
            ->orderBy('output_material_id')->orderByDesc('version')->get();
        $materials = $request->user()->can('manage-production-recipes')
            ? RawMaterial::where('shop_id', $shop->id)->orderBy('name')->get(['id', 'name', 'unit'])
            : collect();

        return view('Admin.Dashboard.tabs.central-kitchen', compact('shop', 'orders', 'recipes', 'materials'));
    }

    public function show(Request $request, int $id): View
    {
        $shop = $request->user()->shop;
        $order = ProductionWorkOrder::where('shop_id', $shop->id)->whereKey($id)
            ->with(['recipe.outputMaterial', 'location', 'items.rawMaterial', 'recorder', 'validator'])->firstOrFail();
        $balances = $order->location->balances()->whereIn('raw_material_id', $order->items->pluck('raw_material_id'))
            ->pluck('quantity', 'raw_material_id');

        return view('Admin.Dashboard.tabs.central-kitchen', compact('shop', 'order', 'balances'));
    }

    public function recipe(Request $request, ManageProduction $production): RedirectResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'output_material_id' => ['required', 'integer'],
            'yield_quantity' => $this->quantityRules(),
            'notes' => ['nullable', 'string', 'max:250'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:raw_material_id,quantity'],
            'items.*.raw_material_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => $this->quantityRules(),
        ]);
        $recipe = $production->createRecipe($request->user(), $data);

        return redirect()->route('admin.production.index')->with('status', 'Resep versi '.$recipe->version.' tersimpan.');
    }

    public function workOrder(Request $request, ManageProduction $production): RedirectResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'production_recipe_id' => ['required', 'integer'],
            'target_quantity' => $this->quantityRules(),
            'notes' => ['nullable', 'string', 'max:250'],
        ]);
        $order = $production->createWorkOrder($request->user(), $data['production_recipe_id'], (float) $data['target_quantity'], $data['request_id'], $data['notes'] ?? null);

        return redirect()->route('admin.production.show', $order)->with('status', 'Surat kerja dibuat.');
    }

    public function result(Request $request, int $id, ManageProduction $production): RedirectResponse
    {
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'good_quantity' => $this->quantityRules(allowZero: true),
            'waste_quantity' => $this->quantityRules(allowZero: true),
            'waste_reason' => ['nullable', 'string', 'max:250'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:work_order_item_id,actual_quantity'],
            'items.*.work_order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.actual_quantity' => $this->quantityRules(allowZero: true),
        ]);
        $production->recordResult($request->user(), $id, $data);

        return redirect()->route('admin.production.show', $id)->with('status', 'Hasil tersimpan dan menunggu validasi.');
    }

    public function post(Request $request, int $id, ManageProduction $production): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        $production->postResult($request->user(), $id, (int) $data['revision']);

        return redirect()->route('admin.production.show', $id)->with('status', 'Hasil produksi dibukukan.');
    }

    /** @return list<string> */
    private function quantityRules(bool $allowZero = false): array
    {
        return ['required', 'numeric', 'decimal:0,6', 'between:'.($allowZero ? '0' : '0.000001').',999999999.999999'];
    }
}
