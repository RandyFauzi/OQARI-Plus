<?php

namespace App\Actions;

use App\Models\CashRegisterSession;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockCount;
use App\Models\Table;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\BranchStorefrontResolver;
use App\Services\SchemaCompatibility;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RenderAdminPage
{
    public function __construct(private SchemaCompatibility $schema) {}

    /** @param array<string, mixed> $data */
    public function execute(User $user, string $page, array $data = []): View
    {
        $definition = config('admin_pages.'.$page);
        abort_unless(is_array($definition) && $user->shop_id, 404);
        $shop = $user->shop;
        abort_unless($shop, 404);
        $activeSession = $user->can('manage-register') && $this->schema->missing(['register']) === []
            ? CashRegisterSession::whereHas('register', fn ($query) => $query->where('shop_id', $shop->id))->where('user_id', $user->id)->where('status', 'OPEN')->first()
            : null;
        $categories = in_array($page, ['menu', 'receipt', 'settings', 'orders'], true) ? Category::where('shop_id', $shop->id)->orderBy('sort_order')->get() : collect();
        $tables = in_array($page, ['orders', 'settings', 'qr'], true) ? Table::where('shop_id', $shop->id)->orderBy('name')->get() : collect();
        $shopProducts = ($user->can('manage-settings') || $user->can('manage-menu') || in_array($page, ['settings', 'addons', 'menu', 'dashboard'], true))
            ? Product::where('shop_id', $shop->id)->with('category:id,name')->select('id', 'shop_id', 'category_id', 'name', 'price', 'discount_price', 'is_discount_active', 'description', 'is_best_seller', 'image_path')->orderBy('name')->get()
            : collect();
        $modifierGroups = in_array($page, ['addons', 'menu', 'dashboard'], true)
            ? ModifierGroup::where('shop_id', $shop->id)->with(['modifiers', 'products:id,name'])->withCount('products')->orderBy('id', 'desc')->get()
            : collect();
        $operatingBranch = app(BranchContext::class)->branch();
        $storefrontResolver = app(BranchStorefrontResolver::class);
        $effectiveShop = $operatingBranch ? $storefrontResolver->apply($shop, $operatingBranch) : $shop;
        $branchOverriddenKeys = $storefrontResolver->overriddenKeys($operatingBranch);
        $dashboardShop = $user->can('manage-settings') ? $effectiveShop->toArray() : $effectiveShop->only(['id', 'name', 'slug', 'logo_url', 'primary_color', 'sales_modes']);
        $pageView = $definition['view'];
        $pageLabel = $definition['label'];
        $pageLinks = collect(config('admin_pages'))->filter(fn (array $item): bool => $user->can($item['ability']))
            ->map(fn (array $item, string $id): array => ['id' => $id, 'name' => $item['label'], 'url' => route($item['route'])])->values()->all();

        $stockCounts = collect();
        $opnameMaterials = collect();
        if ($page === 'inventory' && ($user->can('record-inventory-controls') || $user->can('approve-inventory-controls'))) {
            if (Schema::hasTable('stock_counts')) {
                $stockCounts = StockCount::where('shop_id', $shop->id)
                    ->with(['location', 'items.rawMaterial', 'requester', 'reviewer'])
                    ->latest('id')
                    ->limit(20)
                    ->get();
            }
            $opnameMaterials = RawMaterial::where('shop_id', $shop->id)->orderBy('name')->get(['id', 'name', 'unit']);
        }

        return view('Admin.Dashboard.dashboard', array_merge($data, compact('shop', 'effectiveShop', 'operatingBranch', 'branchOverriddenKeys', 'activeSession', 'categories', 'tables', 'dashboardShop', 'shopProducts', 'modifierGroups', 'page', 'pageView', 'pageLabel', 'pageLinks', 'stockCounts', 'opnameMaterials')));
    }
}
