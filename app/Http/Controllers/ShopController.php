<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateOrderAction;
use App\Events\OrderCreated;
use App\Jobs\SendOrderReceiptEmail;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopSlugHistory;
use App\Models\Table;
use App\Models\Voucher;
use App\Services\BranchContext;
use App\Services\BranchMenuResolver;
use App\Services\BranchStorefrontResolver;
use App\Services\MediaService;
use App\Services\SchemaCompatibility;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class ShopController extends Controller
{
    protected function resolveShop($slug)
    {
        if ($customShop = request()->attributes->get('custom_domain_shop')) {
            abort_if($customShop->isSuspended(), 404);

            return $customShop;
        }

        $shop = Shop::where('slug', $slug)->first();
        if (! $shop) {
            $history = ShopSlugHistory::where('old_slug', $slug)->first();
            if ($history && $history->shop) {
                $newUrl = str_replace("/{$slug}", '/'.$history->shop->slug, request()->fullUrl());
                throw new HttpResponseException(redirect($newUrl, 301));
            }
            abort(404);
        }

        abort_if($shop->isSuspended(), 404);

        return $shop;
    }

    protected function resolveBranchContext(Request $request, Shop $shop, ?string $branchSlug = null, ?Table $table = null): ?Branch
    {
        if (! $shop->branches_enabled) {
            $request->session()->forget('customer_branch_id.'.$shop->id);

            return null;
        }

        $branch = null;
        if ($table && $table->branch_id) {
            $branch = $shop->branches()->where('is_active', true)->find($table->branch_id);
        }

        if (! $branch && $branchSlug !== null && $branchSlug !== '') {
            $branch = $shop->branches()->where('is_active', true)->where('slug', $branchSlug)->first();
            if (! $branch) {
                abort(404);
            }
        }

        if (! $branch && $request->filled('branch')) {
            $branch = $shop->branches()->where('is_active', true)->where('slug', $request->query('branch'))->first();
        }

        if (! $branch && $request->filled('t')) {
            $tokenTable = Table::where('shop_id', $shop->id)->where('public_token', $request->query('t'))->first();
            if ($tokenTable && $tokenTable->branch_id) {
                $branch = $shop->branches()->where('is_active', true)->find($tokenTable->branch_id);
            }
        }

        if (! $branch && ($sessionBranchId = $request->session()->get('customer_branch_id.'.$shop->id))) {
            $branch = $shop->branches()->where('is_active', true)->find($sessionBranchId);
        }

        $branch ??= $shop->branches()->where('is_active', true)->where('is_primary', true)->first()
            ?? $shop->branches()->where('is_active', true)->first();

        if ($branch) {
            $request->session()->put('customer_branch_id.'.$shop->id, $branch->id);
        }

        return $branch;
    }

    public function showCustomDomain(Request $request)
    {
        $shop = $request->attributes->get('custom_domain_shop');
        abort_unless($shop, 404);

        return $this->show($request, $shop->slug, '');
    }

    public function cartCustomDomain(Request $request)
    {
        $shop = $request->attributes->get('custom_domain_shop');
        abort_unless($shop, 404);

        return $this->cart($request, $shop->slug);
    }

    public function trackingCustomDomain(Request $request)
    {
        $shop = $request->attributes->get('custom_domain_shop');
        abort_unless($shop, 404);

        return $this->tracking($request, $shop->slug);
    }

    public function validateVoucherCustomDomain(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('custom_domain_shop');
        abort_unless($shop, 404);

        return $this->validateVoucher($request, $shop->slug);
    }

    public function submitOrderCustomDomain(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('custom_domain_shop');
        abort_unless($shop, 404);

        return $this->submitOrder($request, $shop->slug);
    }

    public function showBranch(Request $request, string $slug, string $branchSlug)
    {
        return $this->show($request, $slug, $branchSlug);
    }

    public function show(Request $request, $slug, ?string $branchSlug = null)
    {
        if ($branchSlug === null && ($customShop = $request->attributes->get('custom_domain_shop'))) {
            $branchSlug = $slug;
            $slug = $customShop->slug;
        }

        $shop = $this->resolveShop($slug);

        $categories = Category::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $validCategoryIds = $categories->pluck('id')->all();

        // Data reconciliation: ensure orphaned products from categories deleted in DB are cleaned up
        if (empty($validCategoryIds)) {
            Product::where('shop_id', $shop->id)
                ->where(function ($q): void {
                    $q->whereNotNull('category_id')->orWhereNotNull('category_name');
                })
                ->update(['category_id' => null, 'category_name' => null]);
        } else {
            Product::where('shop_id', $shop->id)
                ->whereNotNull('category_id')
                ->whereNotIn('category_id', $validCategoryIds)
                ->update(['category_id' => null, 'category_name' => null]);

            Product::where('shop_id', $shop->id)
                ->whereNull('category_id')
                ->whereNotNull('category_name')
                ->whereNotIn('category_name', $categories->pluck('name')->all())
                ->update(['category_name' => null]);
        }

        $menuItems = Product::where('shop_id', $shop->id)
            ->with(['category', 'variants', 'modifierGroups.modifiers'])
            ->get()
            ->each(function ($product) use ($validCategoryIds): void {
                if (! $product->category || ! in_array($product->category_id, $validCategoryIds, true)) {
                    $product->category_id = null;
                    $product->category_name = null;
                }
            });

        $table = null;
        $tableModel = null;
        if ($request->filled('t')) {
            $tableModel = Table::where('shop_id', $shop->id)
                ->where('public_token', $request->query('t'))
                ->first();
            $table = $tableModel ? $tableModel->name : null;
        } elseif ($request->filled('table')) {
            // Fallback for legacy QR codes (temporary backward compatibility)
            $table = $request->query('table');
        }

        $activeBranch = null;
        if ($shop->branches_enabled) {
            $activeBranch = $this->resolveBranchContext($request, $shop, $branchSlug, $tableModel);
            $shop = app(BranchStorefrontResolver::class)->apply($shop, $activeBranch);
            $menuItems = app(BranchMenuResolver::class)->forCustomerMenu($menuItems, $activeBranch);
        }

        return view('shop.menu', compact('shop', 'menuItems', 'table', 'categories', 'activeBranch'));
    }

    public function cartBranch(Request $request, string $slug, string $branchSlug)
    {
        return $this->cart($request, $slug, $branchSlug);
    }

    public function cart(Request $request, $slug, ?string $branchSlug = null)
    {
        $shop = $this->resolveShop($slug);
        $activeBranch = $this->resolveBranchContext($request, $shop, $branchSlug);
        if ($shop->branches_enabled && $activeBranch) {
            $shop = app(BranchStorefrontResolver::class)->apply($shop, $activeBranch);
        }

        $staticQrisAvailable = filled($activeBranch?->static_qris_path)
            && app(SchemaCompatibility::class)->missing(['static_qris']) === [];

        return view('shop.cart', compact('shop', 'activeBranch', 'staticQrisAvailable'));
    }

    public function trackingBranch(Request $request, string $slug, string $branchSlug)
    {
        return $this->tracking($request, $slug, $branchSlug);
    }

    public function tracking(Request $request, $slug, ?string $branchSlug = null)
    {
        $shop = $this->resolveShop($slug);
        $activeBranch = $this->resolveBranchContext($request, $shop, $branchSlug);
        if ($shop->branches_enabled && $activeBranch) {
            $shop = app(BranchStorefrontResolver::class)->apply($shop, $activeBranch);
        }

        $trackedOrder = null;
        $staticQrisUrl = null;
        if ($request->has('order')) {
            abort_unless($activeBranch && $request->hasValidSignature(), 403);
            app(SchemaCompatibility::class)->requireFeatures(['static_qris']);
            $trackedOrder = Order::where('shop_id', $shop->id)->where('branch_id', $activeBranch->id)
                ->where('payment_channel', Order::StaticQrisChannel)->findOrFail($request->query('order'));
            if ($trackedOrder->isAwaitingStaticQrisPayment()) {
                $staticQrisUrl = app(MediaService::class)->url($trackedOrder->payment_qr_path);
            }
        }

        return response()->view('shop.tracking', compact('shop', 'activeBranch', 'trackedOrder', 'staticQrisUrl'))
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function submitOrderBranch(Request $request, string $slug, string $branchSlug, CreateOrderAction $createOrder)
    {
        return $this->submitOrder($request, $slug, $createOrder, $branchSlug);
    }

    public function submitOrder(Request $request, $slug, CreateOrderAction $createOrder, ?string $branchSlug = null)
    {
        $shop = $this->resolveShop($slug);
        if (is_string($request->input('payment_method'))) {
            $request->merge(['payment_method' => strtoupper($request->input('payment_method'))]);
        }

        $validated = $request->validate([
            'request_id' => ['nullable', 'uuid'],
            'branch_slug' => ['nullable', 'string', 'max:100'],
            'table_id' => 'nullable|string|max:50',
            'table_token' => ['nullable', 'string', 'max:64'],
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'fulfillment_type' => 'nullable|string|in:DINE_IN,TAKEAWAY,DELIVERY',
            'voucher_code' => 'nullable|string|max:50',
            'payment_method' => ['required', 'string', 'in:CASH,QRIS'],
            'items' => 'required|array|min:1|max:50',
            'items.*.id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1|max:100',
            'items.*.notes' => 'nullable|string|max:200',
            'items.*.variant_id' => 'nullable|integer',
            'items.*.modifiers' => 'nullable|array',
            'items.*.modifiers.*' => 'integer',
        ]);
        $fulfillmentType = $validated['fulfillment_type'] ?? 'DINE_IN';
        $context = app(BranchContext::class);
        $context->clear();
        $branch = null;
        if ($shop->branches_enabled) {
            $tableModel = null;
            if ($fulfillmentType === 'DINE_IN' && ! empty($validated['table_token'])) {
                $tableModel = Table::where('shop_id', $shop->id)->where('public_token', $validated['table_token'])->first();
                if (! $tableModel || ! ($shop->branches()->where('is_active', true)->find($tableModel->branch_id))) {
                    throw ValidationException::withMessages(['table_token' => 'QR meja sudah tidak aktif atau tidak valid.']);
                }
                $validated['table_id'] = $tableModel->name;
                $validated['resolved_table_id'] = $tableModel->id;
            }

            $branch = $this->resolveBranchContext(
                $request,
                $shop,
                $branchSlug ?: ($validated['branch_slug'] ?? null),
                $tableModel,
            );

            if (! $branch) {
                abort(404);
            }

            $context->select($branch);
        }

        if ($fulfillmentType === 'DINE_IN' && empty($validated['table_id'])) {
            return response()->json(['success' => false, 'message' => 'Nomor meja wajib diisi untuk pesanan Dine In.'], 422);
        }

        if ($validated['payment_method'] === 'QRIS') {
            app(SchemaCompatibility::class)->requireFeatures(['static_qris']);
        }

        try {
            $order = $createOrder->execute($shop, $validated, $fulfillmentType);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        if ($order->wasRecentlyCreated && ! $order->isAwaitingStaticQrisPayment()) {
            rescue(fn () => OrderCreated::dispatch($order), report: true);
            if ($order->customer_email) {
                rescue(fn () => dispatch(new SendOrderReceiptEmail($order->id)), report: true);
            }
        }

        $trackingUrl = $order->payment_channel === Order::StaticQrisChannel
            ? URL::signedRoute('shop.branch.tracking', ['slug' => $shop->slug, 'branchSlug' => $branch->slug, 'order' => $order->id])
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully',
            'payment_url' => null,
            'tracking_url' => $trackingUrl,
            'order' => $order,
        ]);
    }

    public function validateVoucherBranch(Request $request, string $slug, string $branchSlug): JsonResponse
    {
        return $this->validateVoucher($request, $slug, $branchSlug);
    }

    public function validateVoucher(Request $request, $slug, ?string $branchSlug = null): JsonResponse
    {
        $shop = $this->resolveShop($slug);
        $activeBranch = $this->resolveBranchContext($request, $shop, $branchSlug);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $code = trim(strtoupper($validated['code']));
        $subtotal = (float) $validated['subtotal'];

        $voucher = Voucher::where('shop_id', $shop->id)
            ->with('branch:id,name')
            ->where('code', $code)
            ->first();

        if (! $voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Kode promo "'.$code.'" tidak ditemukan.',
            ], 422);
        }

        $check = $voucher->validateForContext($subtotal, $activeBranch?->id);
        if (! $check['valid']) {
            return response()->json([
                'success' => false,
                'message' => $check['message'],
            ], 422);
        }

        $discount = $voucher->calculateDiscount($subtotal);

        return response()->json([
            'success' => true,
            'message' => 'Kode promo "'.$voucher->name.'" berhasil diterapkan!',
            'voucher' => [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'name' => $voucher->name,
                'discount_type' => $voucher->discount_type,
                'discount_value' => $voucher->discount_value,
                'min_spend' => $voucher->min_spend,
                'max_discount' => $voucher->max_discount,
            ],
            'discount_amount' => $discount,
            'net_total' => max(0, $subtotal - $discount),
        ]);
    }
}
