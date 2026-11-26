<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Branch\ManageBranches;
use App\Actions\Branch\ManageStaticQris;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\MediaService;
use App\Services\SchemaCompatibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request, BranchContext $context, MediaService $media, SchemaCompatibility $schema): View
    {
        $user = $request->user();
        abort_unless($user->shop_id, 404);
        $context->clear();

        $branches = $context->accessible($user)->withCount('memberships')->orderByDesc('is_primary')->orderBy('name')->get();

        $staticQrisReady = $user->role === 'owner' && $schema->missing(['static_qris']) === [];
        $staticQrisUrls = $staticQrisReady
            ? $branches->mapWithKeys(fn (Branch $branch): array => [$branch->id => $media->url($branch->static_qris_path)])
            : [];

        return view('Admin.Branches.index', compact('branches', 'staticQrisUrls', 'staticQrisReady'));
    }

    public function updateStaticQris(Request $request, int $branch, ManageStaticQris $action): RedirectResponse
    {
        $outlet = $this->activeOwnedBranch($request, $branch);
        $data = $request->validateWithBag('qris'.$branch, [
            'static_qris' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:3072', 'dimensions:min_width=200,min_height=200,max_width=2400,max_height=2400'],
        ], ['static_qris.dimensions' => 'Gunakan gambar QRIS berukuran 200 sampai 2400 piksel pada tiap sisi.']);
        $action->execute($outlet, $data['static_qris']);

        return back()->with('success', 'QRIS statis outlet disimpan.');
    }

    public function removeStaticQris(Request $request, int $branch, ManageStaticQris $action): RedirectResponse
    {
        $action->execute($this->activeOwnedBranch($request, $branch), null);

        return back()->with('success', 'QRIS statis outlet dinonaktifkan.');
    }

    private function activeOwnedBranch(Request $request, int $branch): Branch
    {
        return Branch::where('shop_id', $this->owner($request)->shop_id)->where('is_active', true)->findOrFail($branch);
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
