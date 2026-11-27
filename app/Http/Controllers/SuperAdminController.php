<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopDomain;
use App\Models\SuperAdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    public function index(Request $request)
    {
        $since30d = now()->subDays(30);

        $totalShops = Shop::count();
        $activeShops = Shop::active()->count();
        $totalUsers = User::count();

        $ordersLast30d = Order::where('created_at', '>=', $since30d)->count();
        $revenueLast30d = Order::where('created_at', '>=', $since30d)->sum('grand_total');

        $pendingDomains = ShopDomain::with('shop')
            ->whereIn('status', [
                ShopDomain::STATUS_PENDING_APPROVAL,
                ShopDomain::STATUS_PENDING,
                ShopDomain::STATUS_FAILED,
            ])
            ->orderByRaw("CASE WHEN status = 'pending_approval' THEN 0 WHEN status = 'pending' THEN 1 ELSE 2 END")
            ->latest('id')
            ->get();

        $recentActivity = SuperAdminAuditLog::with('actor')->latest()->take(10)->get();

        return view('SuperAdmin.dashboard', compact(
            'totalShops', 'activeShops', 'totalUsers',
            'ordersLast30d', 'revenueLast30d',
            'pendingDomains', 'recentActivity'
        ));
    }
}
