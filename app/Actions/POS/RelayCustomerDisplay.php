<?php

namespace App\Actions\POS;

use App\Events\PosCustomerAction;
use App\Events\PosDisplayUpdated;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class RelayCustomerDisplay
{
    public function __construct(private CalculateCart $cart) {}

    /** @return array{state: ?array, actions: list<array>} */
    public function snapshot(int $shopId, ?int $terminalId = null): array
    {
        $entry = Cache::get($this->key($shopId, $terminalId), []);
        $state = $entry['state'] ?? null;
        if ($state && (now()->diffInMinutes($state['updated_at'], absolute: true) >= 10
            || ($state['status'] === 'paid' && now()->getTimestampMs() >= $state['paid_until']))) {
            $state = null;
        }

        return ['state' => $state, 'actions' => array_values($entry['actions'] ?? [])];
    }

    public function sync(int $shopId, array $state, ?int $terminalId = null): void
    {
        $key = $this->key($shopId, $terminalId);
        $broadcast = Cache::lock($key.':lock', 5)->block(3, function () use ($key, $state): ?array {
            $entry = Cache::get($key, []);
            $previous = $entry['state'] ?? null;
            foreach ($state['acknowledged_action_ids'] ?? [] as $id) {
                unset($entry['actions'][$id]);
                $entry['acked'][$id] = true;
            }
            $entry['acked'] = array_slice($entry['acked'] ?? [], -200, null, true);

            if ($previous && $state['cart_id'] === $previous['cart_id'] && $state['revision'] < $previous['revision']) {
                Cache::put($key, $entry, now()->addMinutes(10));

                return null;
            }
            $state['updated_at'] = now()->toISOString();
            $state['paid_until'] = $state['status'] === 'paid'
                ? (($previous['status'] ?? null) === 'paid' && ($previous['order_id'] ?? null) === ($state['order_id'] ?? null)
                    ? $previous['paid_until'] : now()->getTimestampMs() + 10000)
                : null;
            $entry['state'] = $state;
            Cache::put($key, $entry, now()->addMinutes(10));

            return $state;
        });
        if ($broadcast !== null && config('broadcasting.default') !== 'log') {
            rescue(fn () => event(new PosDisplayUpdated($shopId, $broadcast, $terminalId)), report: true);
        }
    }

    public function action(Shop $shop, array $action, ?int $terminalId = null): void
    {
        foreach (match ($action['action']) {
            'add_item' => [$action['item']],
            'sync_cart' => $action['items'],
            default => [],
        } as $item) {
            if (! in_array($item['serving_type'], $shop->sales_modes ?? ['DINE_IN', 'TAKEAWAY'], true)) {
                throw ValidationException::withMessages(['item' => 'Jenis pesanan tidak tersedia di toko ini.']);
            }
            $resolved = $this->cart->execute($shop, [$item])['items'][0];
            if (count($resolved['modifiers'] ?? []) !== count($item['modifiers'] ?? [])) {
                throw ValidationException::withMessages(['item' => 'Pilihan tambahan tidak tersedia untuk menu ini.']);
            }
        }

        $key = $this->key($shop->id, $terminalId);
        $queued = Cache::lock($key.':lock', 5)->block(3, function () use ($key, $action): bool {
            $entry = Cache::get($key, []);
            $id = $action['action_id'];
            if (isset($entry['actions'][$id]) || isset($entry['acked'][$id])) {
                return false;
            }
            if (count($entry['actions'] ?? []) >= 100) {
                throw ValidationException::withMessages(['action' => 'Antrean kiosk penuh. Buka POS untuk melanjutkan.']);
            }
            $entry['actions'][$id] = $action;
            Cache::put($key, $entry, now()->addMinutes(10));

            return true;
        });
        if ($queued && config('broadcasting.default') !== 'log') {
            rescue(fn () => event(new PosCustomerAction($shop->id, $action, $terminalId)), report: true);
        }
    }

    /** @return list<array<string, mixed>> */
    public static function catalog(Collection $products): array
    {
        return $products->map(fn (Product $product) => [
            'id' => $product->id, 'name' => $product->name, 'category_id' => $product->category_id,
            'category_name' => $product->category?->name ?? 'Menu', 'image_url' => $product->image_url,
            'effective_price' => $product->effective_price, 'price' => $product->price,
            'has_active_discount' => $product->has_active_discount, 'discount_percentage' => $product->discount_percentage,
            'is_best_seller' => $product->is_best_seller, 'is_sold_out' => $product->is_sold_out,
            'variants' => $product->variants->where('is_active', true)->map->only(['id', 'name', 'price_adjustment'])->values()->all(),
            'modifier_groups' => $product->modifierGroups->where('is_active', true)->map(fn ($group) => [
                ...$group->only(['id', 'name', 'is_required', 'selection_type', 'min_selections', 'max_selections']),
                'modifiers' => $group->modifiers->where('is_active', true)->map->only(['id', 'name', 'price_adjustment'])->values()->all(),
            ])->filter(fn ($group) => $group['modifiers'] !== [])->values()->all(),
        ])->values()->all();
    }

    private function key(int $shopId, ?int $terminalId): string
    {
        return 'pos-customer-display:'.$shopId.($terminalId ? ':'.$terminalId : '');
    }
}
