<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\BuildVarianceReport;
use App\Actions\Inventory\ManageOutletRequests;
use App\Actions\Inventory\ManagePettyPurchases;
use App\Actions\Inventory\ManageStockCounts;
use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\InventoryLocation;
use App\Models\OutletStockRequest;
use App\Models\PettyPurchase;
use App\Models\RawMaterial;
use App\Models\StockCount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryControlsController extends Controller
{
    public const Tabs = ['requests' => 'Permintaan barang', 'counts' => 'Stok opname', 'purchases' => 'Pembelian kecil'];

    public function index(Request $request): View
    {
        $filters = $request->validate(['tab' => ['nullable', Rule::in(array_keys(self::Tabs))], 'record' => ['nullable', 'integer', 'min:1']]);
        $tab = $filters['tab'] ?? 'requests';
        $user = $request->user();
        $shop = $user->shop;
        $materials = $user->can('record-inventory-controls')
            ? RawMaterial::where('shop_id', $shop->id)->orderBy('name')->get(['id', 'name', 'unit'])
            : collect();
        $sessions = collect();
        $records = match ($tab) {
            'requests' => OutletStockRequest::where('shop_id', $shop->id)->with(['rawMaterial', 'transfer']),
            'counts' => StockCount::where('shop_id', $shop->id)->with(['location', 'items.rawMaterial']),
            'purchases' => PettyPurchase::where('shop_id', $shop->id)->with('rawMaterial')
                ->when($user->cannot('approve-inventory-controls'), fn ($query) => $query->where('created_by', $user->id)),
        };
        if ($tab === 'purchases' && $user->can('record-inventory-controls')) {
            $sessions = CashRegisterSession::where('user_id', $user->id)->where('status', 'OPEN')
                ->whereHas('register', fn ($query) => $query->where('shop_id', $shop->id))->with('register')->latest('id')->get();
        }

        return view('Admin.Dashboard.tabs.inventory-controls', [
            'shop' => $shop, 'tab' => $tab, 'tabs' => self::Tabs, 'materials' => $materials, 'sessions' => $sessions,
            'records' => $records->when(isset($filters['record']), fn ($query) => $query->whereKey($filters['record']))->with(['requester', 'reviewer'])->latest('id')->paginate(15)->withQueryString(),
            'recordId' => $filters['record'] ?? null,
            'today' => now(config('attendance.timezone'))->toDateString(),
        ]);
    }

    public function requestStock(Request $request, ManageOutletRequests $requests): RedirectResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'], 'raw_material_id' => ['required', 'integer'],
            'source' => ['required', Rule::in([InventoryLocation::Warehouse, InventoryLocation::Kitchen])],
            'quantity' => $this->quantityRules(),
            'notes' => ['nullable', 'string', 'max:250'],
        ]);
        $requests->create($request->user(), $data);

        return $this->updated('requests', 'Permintaan dikirim untuk ditinjau.');
    }

    public function reviewRequest(Request $request, int $id, ManageOutletRequests $requests): RedirectResponse
    {
        $data = $request->validate($this->decisionRules() + [
            'approved_quantity' => ['nullable', 'required_if:decision,approve', 'numeric', 'decimal:0,6', 'between:0.000001,999999999.999999'],
        ]);
        $quantity = isset($data['approved_quantity']) ? (float) $data['approved_quantity'] : null;
        $requests->review($request->user(), $id, $data['decision'] === 'approve', $quantity, $data['decision_note'] ?? null);

        return $this->updated('requests', 'Keputusan permintaan disimpan.');
    }

    public function dispatch(Request $request, int $id, ManageOutletRequests $requests): RedirectResponse
    {
        $data = $request->validate(['request_id' => ['required', 'uuid']]);
        $requests->dispatch($request->user(), $id, $data['request_id']);

        return $this->updated('requests', 'Transfer dikirim.');
    }

    public function recordCount(Request $request, ManageStockCounts $counts): RedirectResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'location' => ['required', Rule::in([InventoryLocation::Warehouse, InventoryLocation::Kitchen, InventoryLocation::Outlet])],
            'frequency' => ['required', 'in:DAILY,WEEKLY'],
            'count_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now(config('attendance.timezone'))->toDateString()],
            'notes' => ['nullable', 'string', 'max:250'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:raw_material_id,counted_quantity'],
            'items.*.raw_material_id' => ['required', 'integer', 'distinct'],
            'items.*.counted_quantity' => $this->quantityRules(allowZero: true),
        ]);
        $counts->record($request->user(), $data);

        return $this->updated('counts', 'Hitungan disimpan untuk persetujuan.');
    }

    public function reviewCount(Request $request, int $id, ManageStockCounts $counts): RedirectResponse
    {
        $data = $request->validate($this->decisionRules());
        $counts->review($request->user(), $id, $data['decision'] === 'approve', $data['decision_note'] ?? null);

        return $this->updated('counts', 'Keputusan opname disimpan.');
    }

    public function recordPurchase(Request $request, ManagePettyPurchases $purchases): RedirectResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'description' => ['required', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:250'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,999999999.99'],
            'funding' => ['required', 'in:DRAWER,EXTERNAL'],
            'cash_register_session_id' => ['nullable', 'required_if:funding,DRAWER', 'integer'],
            'raw_material_id' => ['nullable', 'required_with:quantity', 'integer'],
            'quantity' => ['nullable', 'required_with:raw_material_id', 'numeric', 'decimal:0,6', 'between:0.000001,999999999.999999'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $purchases->create($request->user(), $data);

        return $this->updated('purchases', 'Pembelian kecil diajukan.');
    }

    public function reviewPurchase(Request $request, int $id, ManagePettyPurchases $purchases): RedirectResponse
    {
        $data = $request->validate($this->decisionRules());
        $purchases->review($request->user(), $id, $data['decision'] === 'approve', $data['decision_note'] ?? null);

        return $this->updated('purchases', 'Keputusan pembelian disimpan.');
    }

    public function receivePurchase(Request $request, int $id, ManagePettyPurchases $purchases): RedirectResponse
    {
        $purchase = $this->visiblePurchase($request, $id);
        $purchases->receive($request->user(), $purchase->id);

        return $this->updated('purchases', 'Barang masuk ke stok outlet.');
    }

    public function proof(Request $request, int $id): StreamedResponse
    {
        $purchase = $this->visiblePurchase($request, $id);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($purchase->proof_path), 404);

        return $disk->download($purchase->proof_path, 'bukti-'.$purchase->id.'.'.pathinfo($purchase->proof_path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function report(Request $request, BuildVarianceReport $reports): View
    {
        $types = BuildVarianceReport::Types;
        if ($request->user()->shop->branches_enabled && $request->user()->role !== 'owner') {
            unset($types['production']);
        }
        $data = $request->validate([
            'type' => ['nullable', Rule::in(array_keys($types))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'through' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
        ]);
        $shop = $request->user()->shop;
        $type = $data['type'] ?? 'counts';
        $from = $data['from'] ?? null;
        $through = $data['through'] ?? null;
        $rows = $reports->execute($shop->id, $type, $from, $through);

        return view('Admin.Dashboard.tabs.inventory-variance', compact('shop', 'rows', 'type', 'types', 'from', 'through'));
    }

    private function visiblePurchase(Request $request, int $id): PettyPurchase
    {
        $user = $request->user();

        return PettyPurchase::where('shop_id', $user->shop_id)->whereKey($id)
            ->when($user->cannot('approve-inventory-controls'), fn ($query) => $query->where('created_by', $user->id))
            ->firstOrFail();
    }

    private function updated(string $tab, string $status): RedirectResponse
    {
        $returnTo = request()->input('return_to');
        if (is_string($returnTo) && str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//')) {
            return redirect()->to($returnTo)->with('status', $status);
        }

        return redirect()->route('admin.controls.index', ['tab' => $tab])->with('status', $status);
    }

    /** @return array<string, list<string>> */
    private function decisionRules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],
            'decision_note' => ['nullable', 'required_if:decision,reject', 'string', 'max:250'],
        ];
    }

    /** @return list<string> */
    private function quantityRules(bool $allowZero = false): array
    {
        return ['required', 'numeric', 'decimal:0,6', 'between:'.($allowZero ? '0' : '0.000001').',999999999.999999'];
    }
}
