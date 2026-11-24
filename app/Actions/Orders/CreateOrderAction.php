<?php

namespace App\Actions\Orders;

use App\Models\ActivityLog;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\Table;
use App\Models\Voucher;
use App\Services\BranchContext;
use App\Services\BranchMenuResolver;
use App\Services\InventoryService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateOrderAction
{
    /**
     * Mengeksekusi pembuatan pesanan dengan segala rules domainnya.
     */
    public function execute(Shop $shop, array $data, $fulfillmentType = 'DINE_IN'): Order
    {
        return DB::transaction(function () use ($shop, $data, $fulfillmentType) {
            if (! empty($data['request_id'])) {
                Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
                $order = Order::where('shop_id', $shop->id)->where('qr_request_id', $data['request_id'])->first();
                if ($order) {
                    return $order;
                }
            }

            // 1. Resolve Table (Only for Dine In or if table is provided)
            $tableModel = isset($data['resolved_table_id']) ? Table::where('shop_id', $shop->id)->findOrFail($data['resolved_table_id']) : null;
            if ($tableModel === null && ! empty($data['table_id'])) {
                $tableModel = Table::where('shop_id', $shop->id)->where('name', $data['table_id'])->first();
                if (! $tableModel) {
                    $tableModel = Table::create(['shop_id' => $shop->id, 'name' => $data['table_id']]);
                }
            }

            // 2. Validate & Calculate Items
            $total = 0;
            $totalCogs = 0;
            $orderItems = [];

            $branch = app(BranchContext::class)->branch();
            $menuResolver = app(BranchMenuResolver::class);

            foreach ($data['items'] as $item) {
                $rawProduct = Product::where('id', $item['id'])
                    ->where('shop_id', $shop->id)
                    ->first();

                $product = $rawProduct ? $menuResolver->resolveForCheckout($rawProduct, $branch) : null;

                if (! $product) {
                    throw new Exception('Produk tidak tersedia: '.$item['id']);
                }

                $price = (float) $product->effective_price;
                $cogs = $product->cogs;

                $variantId = $item['variant_id'] ?? null;
                $variantName = null;

                if ($variantId) {
                    $variant = ProductVariant::where('id', $variantId)
                        ->where('product_id', $product->id)
                        ->where('is_active', true)
                        ->first();

                    if (! $variant) {
                        throw new Exception('Varian produk tidak valid.');
                    }

                    $variantName = $variant->name;
                    $price += $variant->price_adjustment;
                    $cogs += $variant->cogs_adjustment;
                }

                $modifiersSnapshot = [];
                if (! empty($item['modifiers']) && is_array($item['modifiers'])) {
                    foreach (array_unique($item['modifiers']) as $modId) {
                        $modifier = Modifier::where('id', $modId)
                            ->availableFor($product)
                            ->first();

                        if ($modifier) {
                            $price += $modifier->price_adjustment;
                            $cogs += $modifier->cogs_adjustment;
                            $modifiersSnapshot[] = [
                                'id' => $modifier->id,
                                'name' => $modifier->name,
                                'price_adjustment' => $modifier->price_adjustment,
                            ];
                        }
                    }
                }

                $qty = (int) ($item['qty'] ?? $item['quantity'] ?? 1);
                $subtotal = $price * $qty;
                $itemCogs = $cogs * $qty;

                $total += $subtotal;
                $totalCogs += $itemCogs;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variant_id' => $variantId,
                    'variant_name' => $variantName,
                    'modifiers' => empty($modifiersSnapshot) ? null : $modifiersSnapshot,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $subtotal,
                    'cogs_total' => $itemCogs,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            if (empty($orderItems)) {
                throw new Exception('Keranjang pesanan tidak valid.');
            }

            // 3. Resolve optional voucher / promo code
            $voucherCode = ! empty($data['voucher_code']) ? trim(strtoupper($data['voucher_code'])) : null;
            $discountAmount = 0;
            $voucher = null;

            if ($voucherCode) {
                $voucher = Voucher::where('shop_id', $shop->id)
                    ->with('branch:id,name')
                    ->where('code', $voucherCode)
                    ->first();

                if ($voucher) {
                    $check = $voucher->validateForContext($total, app(BranchContext::class)->id());
                    if ($check['valid']) {
                        $discountAmount = $voucher->calculateDiscount($total);
                        $voucher->increment('used_count');
                    }
                }
            }

            $grandTotal = max(0, $total - $discountAmount);

            // 4. Create the Order Parent
            $order = Order::create([
                'shop_id' => $shop->id,
                'qr_request_id' => $data['request_id'] ?? null,
                'table_id' => $tableModel?->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'payment_method' => $data['payment_method'],
                'subtotal' => $total,
                'discount_amount' => $discountAmount,
                'voucher_code' => $voucher?->code,
                'discount_reason' => $voucher?->name,
                'grand_total' => $grandTotal,
                'amount_paid' => $grandTotal,
                'change_amount' => 0,
                'total_cogs' => $totalCogs,
                'order_status' => 'CONFIRMED',
                'payment_status' => 'PAID',
                'paid_at' => now(),
                'fulfillment_type' => $fulfillmentType,
            ]);

            // 4. Attach Items
            $hasPriceColumn = Schema::hasColumn('order_items', 'price');
            $hasBatchNoColumn = Schema::hasColumn('order_items', 'batch_no');
            $hasStatusColumn = Schema::hasColumn('order_items', 'status');
            foreach ($orderItems as $orderItem) {
                $orderItem['order_id'] = $order->id;
                if ($hasPriceColumn && ! isset($orderItem['price'])) {
                    $orderItem['price'] = $orderItem['unit_price'] ?? 0;
                }
                if ($hasBatchNoColumn && ! isset($orderItem['batch_no'])) {
                    $orderItem['batch_no'] = 1;
                }
                if ($hasStatusColumn && ! isset($orderItem['status'])) {
                    $orderItem['status'] = 'new';
                }
                OrderItem::create($orderItem);
            }

            $order->load('items.product');

            // Deduct inventory stock based on recipes
            app(InventoryService::class)->deductStockForOrder($order);

            if (class_exists(ActivityLog::class)) {
                $tableLabel = $tableModel ? 'Meja '.$tableModel->name : ($order->fulfillment_type === 'TAKEAWAY' ? 'Takeaway' : 'Tanpa Meja');
                ActivityLog::create([
                    'shop_id' => $shop->id,
                    'user_id' => auth()->id(),
                    'action' => 'order.qr_placed',
                    'entity_type' => Order::class,
                    'entity_id' => $order->id,
                    'description' => 'Pesanan baru masuk via QR Menu #'.$order->id.' ('.$tableLabel.' · '.($order->customer_name ?: 'Pelanggan').') - Total: Rp '.number_format((float) $order->grand_total, 0, ',', '.'),
                    'ip_address' => request()->ip(),
                    'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
                ]);
            }

            return $order;
        });
    }
}
