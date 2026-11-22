<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\ManagePurchasing;
use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\PettyPurchase;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $shop = $user->shop;
        $orders = PurchaseOrder::where('shop_id', $shop->id)->with('supplier')->withCount('items')->latest('id')->paginate(20);
        $suppliers = Supplier::where('shop_id', $shop->id)->orderBy('name')->get();
        $materials = ($user->can('manage-purchases') || $user->can('record-inventory-controls'))
            ? RawMaterial::where('shop_id', $shop->id)->orderBy('name')->get(['id', 'name', 'unit', 'cost_per_unit'])
            : collect();

        $pettyPurchases = ($user->can('record-inventory-controls') || $user->can('approve-inventory-controls')) && Schema::hasTable('petty_purchases')
            ? PettyPurchase::where('shop_id', $shop->id)
                ->with(['rawMaterial', 'requester', 'reviewer'])
                ->when($user->cannot('approve-inventory-controls'), fn ($query) => $query->where('created_by', $user->id))
                ->latest('id')
                ->limit(25)
                ->get()
            : collect();

        $sessions = $user->can('record-inventory-controls') && Schema::hasTable('cash_register_sessions')
            ? CashRegisterSession::where('user_id', $user->id)
                ->where('status', 'OPEN')
                ->whereHas('register', fn ($query) => $query->where('shop_id', $shop->id))
                ->with('register')
                ->latest('id')
                ->get()
            : collect();

        return view('Admin.Dashboard.tabs.purchasing', compact('shop', 'orders', 'suppliers', 'materials', 'pettyPurchases', 'sessions'));
    }

    public function show(Request $request, int $id): View
    {
        $shop = $request->user()->shop;
        $order = PurchaseOrder::where('shop_id', $shop->id)->whereKey($id)
            ->with(['supplier', 'creator', 'approver', 'items.rawMaterial', 'receipts' => fn ($receipts) => $receipts
                ->latest('id')->with(['receiver', 'items.purchaseOrderItem.rawMaterial'])])
            ->firstOrFail();

        return view('Admin.Dashboard.tabs.purchasing', compact('shop', 'order'));
    }

    public function addSupplier(Request $request, ManagePurchasing $purchasing): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact' => ['nullable', 'string', 'max:160'],
        ]);
        $purchasing->addSupplier($request->user(), $data['name'], $data['contact'] ?? null);

        return redirect()->route('admin.purchases.index')->with('status', 'Supplier tersimpan.');
    }

    public function store(Request $request, ManagePurchasing $purchasing): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'request_id' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:250'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:raw_material_id,quantity,unit_cost'],
            'items.*.raw_material_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,6', 'between:0.000001,999999999.999999'],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,6', 'between:0,999999999.999999'],
        ]);
        $order = $purchasing->createOrder($request->user(), $data);

        return redirect()->route('admin.purchases.show', $order)->with('status', 'PO tersimpan.');
    }

    public function approve(Request $request, int $id, ManagePurchasing $purchasing): RedirectResponse
    {
        $purchasing->approve($request->user(), $id);

        return redirect()->route('admin.purchases.show', $id)->with('status', 'PO disetujui.');
    }

    public function receive(Request $request, int $id, ManagePurchasing $purchasing): RedirectResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'delivery_reference' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:250'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:purchase_order_item_id,accepted_quantity,rejected_quantity,rejection_reason'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.accepted_quantity' => ['required', 'numeric', 'decimal:0,6', 'between:0,999999999.999999'],
            'items.*.rejected_quantity' => ['required', 'numeric', 'decimal:0,6', 'between:0,999999999.999999'],
            'items.*.rejection_reason' => ['nullable', 'string', 'max:250'],
        ]);
        $purchasing->receive($request->user(), $id, $data);

        return redirect()->route('admin.purchases.show', $id)->with('status', 'Penerimaan tersimpan.');
    }
}
