<?php

namespace App\Actions\POS;

use App\Models\Modifier;
use App\Models\Product;
use App\Models\Shop;
use App\Services\BranchContext;
use App\Services\BranchMenuResolver;
use Illuminate\Validation\ValidationException;

/**
 * Pure calculation action — no DB writes.
 * Returns a normalized cart array with all prices, cogs, and line totals resolved.
 */
class CalculateCart
{
    /**
     * @param  array  $items  Raw cart items from the client
     * @return array{items: array, subtotal: float, total_cogs: float}
     *
     * @throws ValidationException
     */
    public function execute(Shop $shop, array $items): array
    {
        $subtotal = 0;
        $totalCogs = 0;
        $resolved = [];

        $branch = app(BranchContext::class)->branch();
        $menuResolver = app(BranchMenuResolver::class);

        foreach ($items as $item) {
            $rawProduct = Product::where('shop_id', $shop->id)
                ->where('id', $item['id'])
                ->first();

            $product = $rawProduct ? $menuResolver->resolveForCheckout($rawProduct, $branch) : null;

            if (! $product) {
                throw ValidationException::withMessages(['items' => 'Produk tidak tersedia atau sudah habis.']);
            }

            $price = (float) $product->effective_price;
            $cogs = (float) ($product->cogs ?? 0);
            $variantId = $item['variant_id'] ?? null;
            $variantName = null;

            // Resolve variant — always via parent scope to prevent IDOR
            if ($variantId) {
                $variant = $product->variants()
                    ->where('id', $variantId)
                    ->where('is_active', true)
                    ->first();

                if (! $variant) {
                    throw ValidationException::withMessages(['items' => 'Varian tidak valid untuk produk ini.']);
                }

                $variantName = $variant->name;
                $price += (float) $variant->price_adjustment;
                $cogs += (float) $variant->cogs_adjustment;
            }

            // Resolve modifiers — always via parent scope
            $modifiersSnapshot = [];
            foreach (array_unique($item['modifiers'] ?? []) as $modId) {
                $modifier = Modifier::where('id', $modId)
                    ->availableFor($product)
                    ->first();

                if ($modifier) {
                    $price += (float) $modifier->price_adjustment;
                    $cogs += (float) $modifier->cogs_adjustment;
                    $modifiersSnapshot[] = [
                        'id' => $modifier->id,
                        'name' => $modifier->name,
                        'price_adjustment' => (float) $modifier->price_adjustment,
                    ];
                }
            }

            $qty = (int) $item['qty'];
            $lineSale = $price * $qty;
            $lineCogs = $cogs * $qty;

            $subtotal += $lineSale;
            $totalCogs += $lineCogs;

            $rawServingType = strtoupper((string) ($item['serving_type'] ?? 'DINE_IN'));
            $servingType = in_array($rawServingType, ['DINE_IN', 'TAKEAWAY', 'DELIVERY'], true) ? $rawServingType : 'DINE_IN';

            $resolved[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant_id' => $variantId,
                'variant_name' => $variantName,
                'modifiers' => empty($modifiersSnapshot) ? null : $modifiersSnapshot,
                'quantity' => $qty,
                'unit_price' => $price,
                'subtotal' => $lineSale,
                'cogs_total' => $lineCogs,
                'notes' => isset($item['notes']) && trim($item['notes']) !== '' ? trim($item['notes']) : null,
                'serving_type' => $servingType,
            ];
        }

        if (empty($resolved)) {
            throw ValidationException::withMessages(['items' => 'Keranjang pesanan kosong atau tidak valid.']);
        }

        return [
            'items' => $resolved,
            'subtotal' => $subtotal,
            'total_cogs' => $totalCogs,
        ];
    }
}
