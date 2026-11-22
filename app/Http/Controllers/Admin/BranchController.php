<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Branch\ManageBranches;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request, BranchContext $context): View
    {
        $user = $request->user();
        abort_unless($user->shop_id, 404);
        $context->clear();

        $branches = $context->accessible($user)->withCount('memberships')->orderByDesc('is_primary')->orderBy('name')->get();

        return view('Admin.Branches.index', compact('branches'));
    }

    public function store(Request $request, ManageBranches $branches): RedirectResponse
    {
        $shop = $this->owner($request)->shop;
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'address' => ['nullable', 'string', 'max:255']]);
        $branches->create($shop, $data['name'], $data['address'] ?? null);

        return redirect()->route('admin.branches.index')->with('success', 'Cabang ditambahkan.');
    }

    public function staff(Request $request, int $branch): View
    {
        $owner = $this->owner($request);
        $branch = Branch::where('shop_id', $owner->shop_id)->findOrFail($branch);
        $members = $branch->memberships()->with('user')->get()->filter(fn ($member) => $member->user?->isAccountActive());
        $users = User::where('shop_id', $owner->shop_id)->unarchived()->where('is_active', true)
            ->whereNotIn('role', ['owner', 'superadmin'])->orderBy('name')->get();

        return view('Admin.Branches.staff', compact('branch', 'members', 'users'));
    }

    public function assign(Request $request, int $branch, ManageBranches $branches): RedirectResponse
    {
        $owner = $this->owner($request);
        $branch = Branch::where('shop_id', $owner->shop_id)->where('is_active', true)->findOrFail($branch);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'role' => ['required', Rule::in(array_keys(config('access.roles')))],
        ]);
        $user = User::where('shop_id', $owner->shop_id)->unarchived()->findOrFail($data['user_id']);
        $branches->assign($branch, $user, $data['role']);

        return back()->with('success', 'Penugasan disimpan.');
    }

    public function unassign(Request $request, int $branch, int $user): RedirectResponse
    {
        $owner = $this->owner($request);
        $branch = Branch::where('shop_id', $owner->shop_id)->findOrFail($branch);
        $branch->memberships()->where('user_id', $user)->delete();

        return back()->with('success', 'Akses pengguna ke cabang dicabut.');
    }

    private function owner(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->role === 'owner' && $user->shop_id, 403);

        return $user;
    }
}
