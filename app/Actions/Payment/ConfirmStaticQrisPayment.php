<?php

namespace App\Actions\Payment;

use App\Events\OrderCreated;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmStaticQrisPayment
{
    public function __construct(private ProcessPayment $payments, private InventoryService $inventory) {}

    public function execute(User $cashier, Branch $branch, int $orderId, string $reference, float $receivedAmount): Order
    {
        return DB::transaction(function () use ($cashier, $branch, $orderId, $reference, $receivedAmount): Order {
            Branch::where('shop_id', $cashier->shop_id)->where('is_active', true)->lockForUpdate()->findOrFail($branch->id);

            $order = Order::where('shop_id', $cashier->shop_id)->where('branch_id', $branch->id)
                ->where('payment_channel', Order::StaticQrisChannel)->lockForUpdate()->findOrFail($orderId);

            if (round($receivedAmount * 100) !== round((float) $order->grand_total * 100)) {
                throw ValidationException::withMessages(['received_amount' => 'Nominal dana masuk harus sama dengan total pesanan.']);
            }

            if ($order->payment_status === 'PAID' && $order->payment_reference === $reference) {
                return $order;
            }

            abort_unless($order->isAwaitingStaticQrisPayment() && $order->order_status === 'HOLD', 422, 'Pesanan sudah lunas atau dibatalkan.');

            $referenceUsed = Order::where('branch_id', $branch->id)->where('payment_channel', Order::StaticQrisChannel)
                ->where('payment_reference', $reference)->exists();
            if ($referenceUsed) {
                throw ValidationException::withMessages(['reference' => 'Referensi pembayaran ini sudah dipakai di outlet ini.']);
            }

            $this->payments->execute($order, 'QRIS', $receivedAmount, $reference);
            $order->refresh()->update(['order_status' => 'CONFIRMED']);
            $this->inventory->deductStockForOrder($order);

            ActivityLog::create([
                'shop_id' => $cashier->shop_id,
                'user_id' => $cashier->id,
                'action' => 'order.static_qris_confirmed',
                'entity_type' => Order::class,
                'entity_id' => $order->id,
                'description' => 'Pembayaran QRIS statis dikonfirmasi untuk pesanan #'.$order->id,
            ]);

            DB::afterCommit(fn () => rescue(fn () => OrderCreated::dispatch($order)));

            return $order;
        }, 3);
    }
}
