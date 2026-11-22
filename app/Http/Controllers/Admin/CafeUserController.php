<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Crew\ArchiveCafeUser;
use App\Actions\Crew\ManageCafeUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCafeUserRequest;
use App\Models\User;
use App\Policies\CafeUserPolicy;
use App\Services\BranchContext;
use App\Services\SchemaCompatibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CafeUserController extends Controller
{
    public function index(Request $request, CafeUserPolicy $policy, SchemaCompatibility $schema, BranchContext $context): View
    {
        $this->authorize('manage-users');
        $shop = $request->user()->shop;
        abort_unless($shop, 403);
        $activeBranch = $context->branch();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'branch_id' => ['nullable', 'string', 'max:20'],
        ]);

        $users = $context->employees(User::query())
            ->where('shop_id', $shop->id)
            ->unarchived()
            ->where('role', '!=', 'superadmin')
            ->with(['branchMemberships.branch:id,name,slug,is_primary'])
            ->when($filters['q'] ?? null, fn ($query, string $term) => $query->where(fn ($search) => $search->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->when(! $activeBranch ? ($filters['branch_id'] ?? null) : null, function ($query, string $branchFilter): void {
                if ($branchFilter === 'unassigned') {
                    $query->where('role', '!=', 'owner')->whereDoesntHave('branchMemberships');
                } elseif (ctype_digit($branchFilter)) {
                    $query->whereHas('branchMemberships', fn ($q) => $q->where('branch_id', (int) $branchFilter));
                }
            })
            ->orderBy('name')->paginate(20)->withQueryString();

        $branches = $shop->branches_enabled
            ? $shop->branches()->where('is_active', true)->orderByDesc('is_primary')->orderBy('name')->get(['id', 'name', 'slug', 'is_primary'])
            : collect();
        $archivingAvailable = in_array('archived_at', $schema->columns('users'), true);

        return view('Admin.Users.index', compact('users', 'filters', 'policy', 'archivingAvailable', 'branches', 'activeBranch', 'shop'));
    }

    public function create(Request $request): View
    {
        return $this->form($request);
    }

    public function edit(Request $request, int $cafeUser, CafeUserPolicy $policy): View
    {
        $target = User::where('shop_id', $request->user()->shop_id)->unarchived()->where('role', '!=', 'superadmin')->findOrFail($cafeUser);
        abort_unless($policy->update($request->user(), $target), 403);

        return $this->form($request, $target);
    }

    private function form(Request $request, ?User $target = null): View
    {
        $this->authorize('manage-users');
        $shop = $request->user()->shop;
        abort_unless($shop, 403);
        $activeBranch = app(BranchContext::class)->branch();
        $roles = $request->user()->role === 'owner' ? config('access.roles') : array_intersect_key(config('access.roles'), array_flip(['cashier', 'barista', 'kitchen', 'crew']));
        $assignable = $request->user()->role === 'owner' ? array_keys(config('access.permissions')) : array_values(array_diff($request->user()->effectivePermissions(), ['manage-users']));
        $groups = collect(config('access.permissions'))->filter(fn (array $value, string $key): bool => in_array($key, $assignable, true))->groupBy('group', preserveKeys: true);
        $branches = ($shop->branches_enabled && $request->user()->role === 'owner' && ! $activeBranch)
            ? $shop->branches()->where('is_active', true)->orderByDesc('is_primary')->orderBy('name')->get(['id', 'name', 'is_primary'])
            : collect();
        $assignedBranchIds = $target ? $target->branchMemberships()->pluck('branch_id')->map(fn ($id) => (int) $id)->all() : [];

        return view('Admin.Users.form', compact('target', 'roles', 'groups', 'assignable', 'branches', 'assignedBranchIds', 'activeBranch', 'shop'));
    }

    public function store(SaveCafeUserRequest $request, ManageCafeUser $manage): JsonResponse|RedirectResponse
    {
        return $this->saved($request, $manage->execute($request->user(), $request->validated()));
    }

    public function update(SaveCafeUserRequest $request, int $cafeUser, ManageCafeUser $manage): JsonResponse|RedirectResponse
    {
        return $this->saved($request, $manage->execute($request->user(), $request->validated(), $cafeUser));
    }

    public function deactivate(Request $request, int $cafeUser, ManageCafeUser $manage): JsonResponse|RedirectResponse
    {
        $this->authorize('manage-users');
        abort_unless($request->user()->shop_id, 403);

        return $this->saved($request, $manage->execute($request->user(), ['is_active' => false], $cafeUser));
    }

    public function destroy(Request $request, int $cafeUser, ArchiveCafeUser $archive): JsonResponse|RedirectResponse
    {
        $this->authorize('manage-users');
        abort_unless($request->user()->shop_id, 403);
        $archive->execute($request->user(), $cafeUser);
        $message = 'Staf dihapus dari daftar dan akses login dihentikan. Riwayat transaksi serta absensi tetap tersimpan.';

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message])
            : redirect()->route('admin.users.index')->with('success', $message);
    }

    private function saved(Request $request, User $user): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'user' => $user->only(['id', 'name', 'email', 'role', 'is_active']), 'message' => 'Akun dan hak akses tersimpan. Sesi lama akan diminta login kembali jika akses berubah.']);
        }

        return redirect()->route('admin.users.index')->with('success', 'Akun dan hak akses tersimpan. Perubahan akses berlaku pada permintaan berikutnya; user perlu login kembali.');
    }
}
