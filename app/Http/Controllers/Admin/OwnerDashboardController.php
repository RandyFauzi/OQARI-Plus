<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Branch\BuildOwnerDashboard;
use App\Http\Controllers\Controller;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OwnerDashboardController extends Controller
{
    public function __invoke(Request $request, BranchContext $context, BuildOwnerDashboard $buildDashboard): View
    {
        $owner = $request->user();
        abort_unless($owner->role === 'owner' && $owner->shop_id, 403);
        $context->clear();

        $filters = $request->validate([
            'days' => ['nullable', 'integer', Rule::in([1, 7, 14, 30])],
            'branch' => ['nullable', 'string', 'max:64'],
        ]);
        $days = (int) ($filters['days'] ?? 7);
        $branches = $context->accessible($owner)->orderByDesc('is_primary')->orderBy('name')->get();
        $focus = isset($filters['branch']) ? $branches->firstWhere('slug', $filters['branch']) : null;
        abort_if(isset($filters['branch']) && ! $focus, 404);

        $dashboard = $buildDashboard->execute($owner, $branches, $days, $focus);

        return view('Admin.Branches.dashboard', compact('branches', 'dashboard', 'days'));
    }
}
