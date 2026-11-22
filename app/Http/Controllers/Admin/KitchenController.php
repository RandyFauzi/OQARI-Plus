<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    public function index()
    {
        $shopId = Auth::user()->shop_id;

        $orders = Order::where('shop_id', $shopId)
            ->whereIn('order_status', ['CONFIRMED', 'PREPARING', 'READY'])
            ->with(['items', 'table'])
            ->latest()
            ->limit(150)
            ->get();

        $kitchenOrders = $orders->map(fn ($o) => [
            'id' => $o->id,
            'table' => $o->table?->name ?? ($o->fulfillment_type === 'TAKEAWAY' ? 'Takeaway' : ($o->fulfillment_type === 'DELIVERY' ? 'Delivery' : 'Tanpa Meja')),
            'customer' => $o->customer_name ?: 'Walk-in Customer',
            'type' => $o->fulfillment_type === 'DINE_IN' ? 'Dine In' : ($o->fulfillment_type === 'TAKEAWAY' ? 'Takeaway' : 'Delivery'),
            'fulfillment_type' => $o->fulfillment_type ?? 'DINE_IN',
            'status' => $o->order_status,
            'time' => $o->created_at->format('H:i'),
            'items' => $o->items->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->product_name,
                'qty' => $i->quantity,
                'variant' => $i->variant_name,
                'modifiers' => $i->modifiers ?? [],
                'notes' => $i->notes,
            ]),
        ]);

        return view('Admin.kitchen.index', compact('orders', 'kitchenOrders'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorize('update-kitchen-status');

        $shopId = Auth::user()->shop_id;

        if ($order->shop_id !== $shopId) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:PREPARING,READY,COMPLETED'],
        ]);

        DB::transaction(function () use ($shopId, $order, $validated): void {
            $locked = Order::where('shop_id', $shopId)->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->order_status === 'CANCELLED', 422, 'Pesanan yang dibatalkan tidak bisa dikirim kembali.');
            app(InventoryService::class)->deductStockForOrder($locked);
            $locked->update(['order_status' => $validated['status']]);
        }, 3);

        return response()->json(['success' => true, 'status' => $validated['status']]);
    }
}
