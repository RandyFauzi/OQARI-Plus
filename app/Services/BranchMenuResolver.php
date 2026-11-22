<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class BranchMenuResolver
{
    public function applyToProduct(Product $product, ?Branch $branch, ?BranchProduct $override = null): Product
    {
        if (! $branch || (int) $branch->shop_id !== (int) $product->shop_id) {
            $product->setAttribute('is_available', true);
            $product->setAttribute('has_branch_override', false);

            return $product;
        }

        if ($override === null && $this->tableReady()) {
            $override = BranchProduct::where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->first();
        }

        $resolved = clone $product;
        $resolved->setAttribute('master_price', $product->price);
        $resolved->setAttribute('master_discount_price', $product->discount_price);
        $resolved->setAttribute('master_is_discount_active', (bool) $product->is_discount_active);
        $resolved->setAttribute('master_is_sold_out', (bool) $product->is_sold_out);

        if (! $override) {
            $resolved->setAttribute('is_available', true);
            $resolved->setAttribute('has_branch_override', false);
            $resolved->setAttribute('branch_override', null);

            return $resolved;
        }

        $resolved->setAttribute('is_available', (bool) $override->is_available);
        $resolved->setAttribute('has_branch_override', true);
        $resolved->setAttribute('branch_override', [
            'is_available' => (bool) $override->is_available,
            'is_sold_out' => $override->is_sold_out !== null ? (bool) $override->is_sold_out : null,
            'price_override' => $override->price_override,
            'discount_price_override' => $override->discount_price_override,
            'is_discount_active_override' => $override->is_discount_active_override !== null ? (bool) $override->is_discount_active_override : null,
        ]);

        if ($override->is_sold_out !== null) {
            $resolved->setAttribute('is_sold_out', (bool) $override->is_sold_out);
        }
        if ($override->price_override !== null) {
            $resolved->setAttribute('price', $override->price_override);
        }
        if ($override->discount_price_override !== null) {
            $resolved->setAttribute('discount_price', $override->discount_price_override);
        }
        if ($override->is_discount_active_override !== null) {
            $resolved->setAttribute('is_discount_active', (bool) $override->is_discount_active_override);
        }

        return $resolved;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public function applyToCollection(Collection $products, ?Branch $branch): Collection
    {
        if (! $branch || $products->isEmpty() || ! $this->tableReady()) {
            return $products->map(function (Product $product): Product {
                $product->setAttribute('is_available', true);
                $product->setAttribute('has_branch_override', false);

                return $product;
            });
        }

        $overrides = BranchProduct::where('branch_id', $branch->id)
            ->whereIn('product_id', $products->pluck('id')->all())
            ->get()
            ->keyBy('product_id');

        return $products->map(
            fn (Product $product): Product => $this->applyToProduct($product, $branch, $overrides->get($product->id))
        );
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public function forCustomerMenu(Collection $products, ?Branch $branch): Collection
    {
        return $this->applyToCollection($products, $branch)
            ->filter(fn (Product $product): bool => (bool) ($product->getAttribute('is_available') ?? true))
            ->values();
    }

    public function resolveForCheckout(Product $product, ?Branch $branch): ?Product
    {
        $resolved = $this->applyToProduct($product, $branch);
        if (! ($resolved->getAttribute('is_available') ?? true) || $resolved->is_sold_out) {
            return null;
        }

        return $resolved;
    }

    private function tableReady(): bool
    {
        return Schema::hasTable('branch_products');
    }
}
